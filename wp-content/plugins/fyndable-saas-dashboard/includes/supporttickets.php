<?php

namespace SSEOAISaaS;

/**
 * Support Ticket System
 *
 * Handles support tickets submitted from client sites and the SaaS dashboard.
 * Stores tickets/replies in the SaaS database, exposes REST endpoints for the
 * client plugin and provides helpers for the admin interface.
 */
class SupportTickets
{
    private const TICKETS_TABLE = 'sseo_ai_support_tickets';
    private const REPLIES_TABLE = 'sseo_ai_support_replies';

    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes used by the client plugin.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ai-seo-saas/v1', '/support/tickets', [
            'methods' => 'GET',
            'callback' => [$this, 'listTickets'],
            'permission_callback' => [$this, 'validateRequest'],
        ]);

        register_rest_route('ai-seo-saas/v1', '/support/tickets', [
            'methods' => 'POST',
            'callback' => [$this, 'createTicket'],
            'permission_callback' => [$this, 'validateRequest'],
        ]);

        register_rest_route('ai-seo-saas/v1', '/support/ticket/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getTicket'],
            'permission_callback' => [$this, 'validateRequest'],
        ]);

        register_rest_route('ai-seo-saas/v1', '/support/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'addReply'],
            'permission_callback' => [$this, 'validateRequest'],
        ]);

        register_rest_route('ai-seo-saas/v1', '/support/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'uploadScreenshot'],
            'permission_callback' => [$this, 'validateRequest'],
        ]);
    }

    /**
     * Validate a client request by license key and tenant key.
     */
    public function validateRequest(\WP_REST_Request $request): bool
    {
        [$licenseKey, $tenantKey] = $this->getCredentialsFromRequest($request);
        if (empty($licenseKey) || empty($tenantKey)) {
            return false;
        }

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return false;
        }

        return $tenant['status'] === 'active';
    }

    private function getCredentialsFromRequest(\WP_REST_Request $request): array
    {
        $licenseKey = $request->get_header('X-License-Key');
        $tenantKey = $request->get_header('X-Tenant-Key');

        if (empty($licenseKey) || empty($tenantKey)) {
            $licenseKey = $request->get_param('license_key') ?? '';
            $tenantKey = $request->get_param('tenant_key') ?? '';
        }

        return [sanitize_text_field($licenseKey), sanitize_text_field($tenantKey)];
    }

    private function getTenantFromRequest(\WP_REST_Request $request): ?array
    {
        [$licenseKey, $tenantKey] = $this->getCredentialsFromRequest($request);
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return null;
        }
        return $tenant;
    }

    /**
     * List tickets for the authenticated tenant.
     */
    public function listTickets(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $tickets = $this->getTicketsForTenant($tenant['id']);

        return new \WP_REST_Response([
            'success' => true,
            'tickets' => $tickets,
        ], 200);
    }

    /**
     * Create a new support ticket from a client site.
     */
    public function createTicket(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $subject = sanitize_text_field($request->get_param('subject') ?? '');
        $message = sanitize_textarea_field($request->get_param('message') ?? '');
        $priority = sanitize_text_field($request->get_param('priority') ?? 'middle');
        $screenshots = $request->get_param('screenshots') ?? [];

        if (empty($subject) || empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_fields',
                'message' => __('Subject and message are required.', 'sseo-ai-saas'),
            ], 400);
        }

        if (!in_array($priority, ['low', 'middle', 'high'], true)) {
            $priority = 'middle';
        }

        $screenshots = $this->sanitizeScreenshots($screenshots);

        $ticketId = $this->insertTicket($tenant['id'], $subject, $message, $priority, $screenshots);

        $this->sendNewTicketNotification($tenant, $ticketId, $subject, $message);

        return new \WP_REST_Response([
            'success' => true,
            'ticket_id' => $ticketId,
            'ticket' => $this->getTicketById($ticketId),
        ], 201);
    }

    /**
     * Get a single ticket including replies for the authenticated tenant.
     */
    public function getTicket(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $ticketId = (int)$request->get_param('id');
        $ticket = $this->getTicketById($ticketId);

        if (!$ticket || (int)$ticket['tenant_id'] !== (int)$tenant['id']) {
            return new \WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'ticket' => $ticket,
        ], 200);
    }

    /**
     * Add a reply to a ticket from a client site.
     */
    public function addReply(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $ticketId = (int)$request->get_param('ticket_id');
        $message = sanitize_textarea_field($request->get_param('message') ?? '');
        $screenshots = $request->get_param('screenshots') ?? [];

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket || (int)$ticket['tenant_id'] !== (int)$tenant['id']) {
            return new \WP_REST_Response(['success' => false, 'error' => 'not_found'], 404);
        }

        if (empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_fields',
                'message' => __('Reply message is required.', 'sseo-ai-saas'),
            ], 400);
        }

        $screenshots = $this->sanitizeScreenshots($screenshots);

        $replyId = $this->insertReply($ticketId, 0, null, $message, $screenshots);
        $this->updateTicketStatus($ticketId, 'open');

        $this->sendReplyNotification($tenant, $ticket, $message, false);

        return new \WP_REST_Response([
            'success' => true,
            'reply_id' => $replyId,
            'ticket' => $this->getTicketById($ticketId),
        ], 201);
    }

    /**
     * Upload a screenshot and return the URL.
     */
    public function uploadScreenshot(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $files = $request->get_file_params();
        if (empty($files['screenshot'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'no_file',
                'message' => __('No screenshot uploaded.', 'sseo-ai-saas'),
            ], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_handle_upload($files['screenshot'], ['test_form' => false]);
        if (!empty($upload['error'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'upload_failed',
                'message' => $upload['error'],
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'url' => $upload['url'],
        ], 200);
    }

    /**
     * -------------------------------------------------------------------------
     * Admin helpers (used by SupportAdmin)
     * -------------------------------------------------------------------------
     */

    /**
     * Get all tickets across tenants, optionally filtered.
     */
    public function getAllTickets(array $filters = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';

        $where = [];
        $args = [];

        if (!empty($filters['status'])) {
            $where[] = 't.status = %s';
            $args[] = sanitize_text_field($filters['status']);
        }
        if (!empty($filters['priority'])) {
            $where[] = 't.priority = %s';
            $args[] = sanitize_text_field($filters['priority']);
        }
        if (!empty($filters['tenant_id'])) {
            $where[] = 't.tenant_id = %d';
            $args[] = (int)$filters['tenant_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(t.subject LIKE %s OR t.message LIKE %s OR tn.name LIKE %s OR tn.email LIKE %s)';
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $sql = "SELECT t.*, tn.name AS tenant_name, tn.email AS tenant_email, tn.tenant_key, tn.license_key
                FROM {$table} t
                LEFT JOIN {$tenantsTable} tn ON tn.id = t.tenant_id";

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY t.updated_at DESC';

        if (!empty($args)) {
            $sql = $wpdb->prepare($sql, ...$args);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);
        if (empty($results)) {
            return [];
        }

        foreach ($results as &$row) {
            $row['screenshots'] = !empty($row['screenshots']) ? json_decode($row['screenshots'], true) : [];
        }

        return $results;
    }

    /**
     * Get a ticket by ID, including replies, for admin use.
     */
    public function getTicketById(int $ticketId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;
        $repliesTable = $wpdb->prefix . self::REPLIES_TABLE;
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, tn.name AS tenant_name, tn.email AS tenant_email, tn.tenant_key, tn.license_key
             FROM {$table} t
             LEFT JOIN {$tenantsTable} tn ON tn.id = t.tenant_id
             WHERE t.id = %d",
            $ticketId
        ), ARRAY_A);

        if (!$ticket) {
            return null;
        }

        $ticket['screenshots'] = !empty($ticket['screenshots']) ? json_decode($ticket['screenshots'], true) : [];

        $replies = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$repliesTable} WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticketId
        ), ARRAY_A);

        foreach ($replies as &$reply) {
            $reply['screenshots'] = !empty($reply['screenshots']) ? json_decode($reply['screenshots'], true) : [];
        }

        $ticket['replies'] = $replies;

        return $ticket;
    }

    /**
     * Update ticket status or priority from the admin.
     */
    public function updateTicket(int $ticketId, array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;

        $update = [];
        if (isset($data['status']) && in_array($data['status'], ['open', 'reaction', 'closed'], true)) {
            $update['status'] = $data['status'];
        }
        if (isset($data['priority']) && in_array($data['priority'], ['low', 'middle', 'high'], true)) {
            $update['priority'] = $data['priority'];
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update($table, $update, ['id' => $ticketId], null, ['%d']);
        return $result !== false;
    }

    /**
     * Add a staff reply from the SaaS dashboard.
     */
    public function addStaffReply(int $ticketId, string $authorName, string $message, array $screenshots = []): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) {
            return false;
        }

        $screenshots = $this->sanitizeScreenshots($screenshots);

        $this->insertReply($ticketId, 1, sanitize_text_field($authorName), $message, $screenshots);
        $this->updateTicketStatus($ticketId, 'reaction');

        $tenant = $this->tenants->getTenant($ticket['tenant_key']);
        if ($tenant) {
            $this->sendReplyNotification($tenant, $ticket, $message, true);
        }

        return true;
    }

    /**
     * Get tickets for a set of tenant IDs (used by agency portal).
     */
    public function getTicketsForTenants(array $tenantIds, array $filters = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';

        if (empty($tenantIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($tenantIds), '%d'));
        $where = ['t.tenant_id IN (' . $placeholders . ')'];
        $args = array_map('intval', $tenantIds);

        if (!empty($filters['status'])) {
            $where[] = 't.status = %s';
            $args[] = sanitize_text_field($filters['status']);
        }
        if (!empty($filters['priority'])) {
            $where[] = 't.priority = %s';
            $args[] = sanitize_text_field($filters['priority']);
        }
        if (!empty($filters['search'])) {
            $where[] = '(t.subject LIKE %s OR t.message LIKE %s OR tn.name LIKE %s OR tn.email LIKE %s)';
            $like = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $sql = "SELECT t.*, tn.name AS tenant_name, tn.email AS tenant_email, tn.tenant_key, tn.license_key
                FROM {$table} t
                LEFT JOIN {$tenantsTable} tn ON tn.id = t.tenant_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.updated_at DESC";

        $sql = $wpdb->prepare($sql, ...$args);
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (empty($results)) {
            return [];
        }

        foreach ($results as &$row) {
            $row['screenshots'] = !empty($row['screenshots']) ? json_decode($row['screenshots'], true) : [];
        }

        return $results;
    }

    /**
     * Count open tickets for a set of tenant IDs (used by agency dashboard).
     */
    public function countOpenTicketsForTenants(array $tenantIds): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;

        if (empty($tenantIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($tenantIds), '%d'));
        $args = array_map('intval', $tenantIds);
        $args[] = 'open';

        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE tenant_id IN ($placeholders) AND status = %s",
            ...$args
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * Internal data methods
     * -------------------------------------------------------------------------
     */

    private function getTicketsForTenant(int $tenantId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE tenant_id = %d ORDER BY updated_at DESC",
            $tenantId
        ), ARRAY_A);

        foreach ($results as &$row) {
            $row['screenshots'] = !empty($row['screenshots']) ? json_decode($row['screenshots'], true) : [];
        }

        return $results ?: [];
    }

    private function insertTicket(int $tenantId, string $subject, string $message, string $priority, array $screenshots): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;

        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'subject' => $subject,
            'message' => $message,
            'priority' => $priority,
            'status' => 'open',
            'screenshots' => wp_json_encode($screenshots),
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);

        return (int)$wpdb->insert_id;
    }

    private function insertReply(int $ticketId, int $isStaff, ?string $authorName, string $message, array $screenshots): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::REPLIES_TABLE;

        $wpdb->insert($table, [
            'ticket_id' => $ticketId,
            'is_staff' => $isStaff,
            'author_name' => $authorName,
            'message' => $message,
            'screenshots' => wp_json_encode($screenshots),
        ], ['%d', '%d', '%s', '%s', '%s']);

        return (int)$wpdb->insert_id;
    }

    private function updateTicketStatus(int $ticketId, string $status): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TICKETS_TABLE;

        if (!in_array($status, ['open', 'reaction', 'closed'], true)) {
            return;
        }

        $wpdb->update($table, ['status' => $status], ['id' => $ticketId], ['%s'], ['%d']);
    }

    private function sanitizeScreenshots($screenshots): array
    {
        if (empty($screenshots) || !is_array($screenshots)) {
            return [];
        }

        $clean = [];
        foreach ($screenshots as $url) {
            $url = esc_url_raw($url);
            if (!empty($url)) {
                $clean[] = $url;
            }
        }

        return $clean;
    }

    /**
     * -------------------------------------------------------------------------
     * Notifications
     * -------------------------------------------------------------------------
     */

    private function getSupportEmail(): string
    {
        return get_option('ai_seo_saas_support_email', get_option('admin_email'));
    }

    private function sendNewTicketNotification(array $tenant, int $ticketId, string $subject, string $message): void
    {
        $to = $this->getSupportEmail();
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $emailSubject = sprintf(
            __('[%s] New support ticket #%d from %s', 'sseo-ai-saas'),
            get_bloginfo('name'),
            $ticketId,
            $tenant['name']
        );

        $body = sprintf(
            "<h2>%s</h2>
            <p><strong>%s:</strong> %s</p>
            <p><strong>%s:</strong> %s</p>
            <p><strong>%s:</strong> %s</p>
            <p><strong>%s:</strong> %s</p>
            <hr>
            <p>%s</p>",
            __('New support ticket received', 'sseo-ai-saas'),
            __('Tenant', 'sseo-ai-saas'),
            esc_html($tenant['name']),
            __('Email', 'sseo-ai-saas'),
            esc_html($tenant['email']),
            __('License', 'sseo-ai-saas'),
            esc_html($tenant['license_key']),
            __('Subject', 'sseo-ai-saas'),
            esc_html($subject),
            nl2br(esc_html($message))
        );

        wp_mail($to, $emailSubject, $body, $headers);
    }

    private function sendReplyNotification(array $tenant, array $ticket, string $message, bool $isStaffReply): void
    {
        if ($isStaffReply) {
            $to = $tenant['email'];
            $emailSubject = sprintf(
                __('[%s] Reply to your support ticket #%d', 'sseo-ai-saas'),
                get_bloginfo('name'),
                $ticket['id']
            );
            $heading = __('A reply has been added to your support ticket', 'sseo-ai-saas');
        } else {
            $to = $this->getSupportEmail();
            $emailSubject = sprintf(
                __('[%s] New reply on ticket #%d from %s', 'sseo-ai-saas'),
                get_bloginfo('name'),
                $ticket['id'],
                $tenant['name']
            );
            $heading = __('A client replied to a support ticket', 'sseo-ai-saas');
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $body = sprintf(
            "<h2>%s</h2>
            <p><strong>%s:</strong> %s</p>
            <p><strong>%s:</strong> #%d - %s</p>
            <hr>
            <p>%s</p>",
            $heading,
            __('Tenant', 'sseo-ai-saas'),
            esc_html($tenant['name']),
            __('Ticket', 'sseo-ai-saas'),
            (int)$ticket['id'],
            esc_html($ticket['subject']),
            nl2br(esc_html($message))
        );

        wp_mail($to, $emailSubject, $body, $headers);
    }
}

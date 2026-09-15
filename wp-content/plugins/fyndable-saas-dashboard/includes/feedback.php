<?php

namespace SSEOAISaaS;

/**
 * Feedback System
 *
 * Handles product feedback submitted from client sites.
 * Stores feedback entries in the SaaS database and exposes REST endpoints
 * for the client plugin. Uses the same license/tenant authentication
 * pattern as the support ticket system.
 */
class Feedback
{
    private const FEEDBACK_TABLE = 'sseo_ai_feedback';

    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('admin_init', [$this, 'maybeCreateTable']);
    }

    /**
     * Create the feedback table if it does not exist yet.
     */
    public function maybeCreateTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        $table = $prefix . self::FEEDBACK_TABLE;

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            category varchar(50) NOT NULL DEFAULT 'general',
            message longtext NOT NULL,
            page_url varchar(500) DEFAULT NULL,
            screenshots longtext DEFAULT NULL COMMENT 'JSON array of attachment URLs',
            status varchar(20) NOT NULL DEFAULT 'new',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tenant_id (tenant_id),
            KEY category (category),
            KEY status (status),
            KEY created_at (created_at)
        ) $charsetCollate;";

        dbDelta($sql);
    }

    /**
     * Register REST API routes used by the client plugin.
     */
    public function registerRoutes(): void
    {
        register_rest_route('ai-seo-saas/v1', '/feedback', [
            'methods' => 'POST',
            'callback' => [$this, 'createFeedback'],
            'permission_callback' => [$this, 'validateRequest'],
        ]);

        register_rest_route('ai-seo-saas/v1', '/feedback', [
            'methods' => 'GET',
            'callback' => [$this, 'listFeedback'],
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
     * Create a new feedback entry from a client site.
     */
    public function createFeedback(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $category = sanitize_text_field($request->get_param('category') ?? 'general');
        $message = sanitize_textarea_field($request->get_param('message') ?? '');
        $pageUrl = esc_url_raw($request->get_param('page_url') ?? '');
        $screenshots = $request->get_param('screenshots') ?? [];

        if (empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_fields',
                'message' => __('Feedback message is required.', 'sseo-ai-saas'),
            ], 400);
        }

        $allowedCategories = ['bug', 'feature_request', 'compliment', 'question', 'general'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'general';
        }

        $screenshots = $this->sanitizeScreenshots($screenshots);

        $feedbackId = $this->insertFeedback(
            (int)$tenant['id'],
            $category,
            $message,
            $pageUrl,
            $screenshots
        );

        return new \WP_REST_Response([
            'success' => true,
            'feedback_id' => $feedbackId,
        ], 201);
    }

    /**
     * List feedback entries for the authenticated tenant.
     */
    public function listFeedback(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->getTenantFromRequest($request);
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_tenant'], 403);
        }

        $entries = $this->getFeedbackForTenant((int)$tenant['id']);

        return new \WP_REST_Response([
            'success' => true,
            'feedback' => $entries,
        ], 200);
    }

    /**
     * Get all feedback entries for a tenant.
     */
    private function getFeedbackForTenant(int $tenantId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::FEEDBACK_TABLE;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE tenant_id = %d ORDER BY created_at DESC",
            $tenantId
        ), ARRAY_A);

        if (empty($results)) {
            return [];
        }

        foreach ($results as &$row) {
            $row['screenshots'] = !empty($row['screenshots']) ? json_decode($row['screenshots'], true) : [];
        }

        return $results;
    }

    /**
     * Insert a feedback entry into the database.
     */
    private function insertFeedback(int $tenantId, string $category, string $message, string $pageUrl, array $screenshots): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::FEEDBACK_TABLE;

        $wpdb->insert($table, [
            'tenant_id' => $tenantId,
            'category' => $category,
            'message' => $message,
            'page_url' => $pageUrl,
            'screenshots' => wp_json_encode($screenshots),
            'status' => 'new',
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);

        return (int)$wpdb->insert_id;
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
     * Get all feedback entries across all tenants (admin view).
     */
    public function getAllFeedback(array $filters = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::FEEDBACK_TABLE;
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';

        $where = [];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = 'f.category = %s';
            $params[] = $filters['category'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'f.status = %s';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(f.message LIKE %s OR t.name LIKE %s OR t.email LIKE %s)';
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT f.*, t.name AS tenant_name, t.email AS tenant_email, t.domain AS tenant_domain
            FROM {$table} f
            LEFT JOIN {$tenantsTable} t ON f.tenant_id = t.id
            {$whereClause}
            ORDER BY f.created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
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
     * Get a single feedback entry by ID (admin view).
     */
    public function getFeedbackById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::FEEDBACK_TABLE;
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT f.*, t.name AS tenant_name, t.email AS tenant_email, t.domain AS tenant_domain, t.license_key AS tenant_license
            FROM {$table} f
            LEFT JOIN {$tenantsTable} t ON f.tenant_id = t.id
            WHERE f.id = %d",
            $id
        ), ARRAY_A);

        if (empty($row)) {
            return null;
        }

        $row['screenshots'] = !empty($row['screenshots']) ? json_decode($row['screenshots'], true) : [];

        return $row;
    }

    /**
     * Update the status of a feedback entry.
     */
    public function updateFeedbackStatus(int $id, string $status): bool
    {
        $allowed = ['new', 'reviewed', 'resolved', 'archived'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::FEEDBACK_TABLE;

        $wpdb->update($table, ['status' => $status], ['id' => $id], ['%s'], ['%d']);

        return $wpdb->rows_affected > 0;
    }
}

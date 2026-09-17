<?php

namespace SSEOAISaaS;

/**
 * Admin REST API
 *
 * Exposes admin-only REST endpoints (under ai-seo-saas/v1/admin/*) for the
 * Fyndable SaaS portal. These mirror the existing WordPress admin pages
 * (license management, support tickets, GEO Readiness scans, usage reports
 * and AI model configuration) so they can be consumed by the internal
 * Android management app.
 *
 * Authentication: WordPress Application Passwords (HTTP Basic Auth).
 * Every route requires an authenticated user with the `manage_options`
 * capability — the same bar as the WP admin pages they mirror.
 */
class AdminApi
{
    private LicenseKeyGenerator $licenseGenerator;
    private TenantRepository $tenants;
    private SupportTickets $supportTickets;
    private GeoScanner $geoScanner;
    private GeoScanRepository $geoScanRepository;
    private ProviderRouter $providerRouter;
    private SaaSSettings $settings;
    private RevenueDashboard $revenueDashboard;

    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(
        LicenseKeyGenerator $licenseGenerator,
        TenantRepository $tenants,
        SupportTickets $supportTickets,
        GeoScanner $geoScanner,
        GeoScanRepository $geoScanRepository,
        ProviderRouter $providerRouter,
        SaaSSettings $settings,
        RevenueDashboard $revenueDashboard
    ) {
        $this->licenseGenerator = $licenseGenerator;
        $this->tenants = $tenants;
        $this->supportTickets = $supportTickets;
        $this->geoScanner = $geoScanner;
        $this->geoScanRepository = $geoScanRepository;
        $this->providerRouter = $providerRouter;
        $this->settings = $settings;
        $this->revenueDashboard = $revenueDashboard;
    }

    /**
     * Register all admin REST routes.
     */
    public function register(): void
    {
        $perm = [$this, 'canManage'];

        // --- Dashboard / ping ---
        register_rest_route($this->namespace, '/admin/ping', [
            'methods' => 'GET',
            'callback' => [$this, 'ping'],
            'permission_callback' => $perm,
        ]);

        // --- License keys ---
        register_rest_route($this->namespace, '/admin/licenses/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getLicenseStats'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/licenses', [
            'methods' => 'GET',
            'callback' => [$this, 'listLicenses'],
            'permission_callback' => $perm,
            'args' => [
                'status' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'type'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tier'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'search' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'limit'  => ['type' => 'integer', 'default' => 50],
                'offset' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route($this->namespace, '/admin/licenses', [
            'methods' => 'POST',
            'callback' => [$this, 'generateLicenses'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/licenses/(?P<key>[A-Za-z0-9\-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getLicense'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/licenses/(?P<key>[A-Za-z0-9\-]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'updateLicense'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/licenses/(?P<key>[A-Za-z0-9\-]+)/revoke', [
            'methods' => 'POST',
            'callback' => [$this, 'revokeLicense'],
            'permission_callback' => $perm,
        ]);

        // --- Tenants / usage reports ---
        register_rest_route($this->namespace, '/admin/tenants', [
            'methods' => 'GET',
            'callback' => [$this, 'listTenants'],
            'permission_callback' => $perm,
            'args' => [
                'status'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tier'     => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'platform' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'search'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'limit'    => ['type' => 'integer', 'default' => 100],
                'offset'   => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route($this->namespace, '/admin/tenants/(?P<tenant_key>[A-Za-z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getTenant'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/tenants/(?P<tenant_key>[A-Za-z0-9_]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteTenant'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/tenants/(?P<tenant_key>[A-Za-z0-9_]+)/usage/history', [
            'methods' => 'GET',
            'callback' => [$this, 'getTenantUsageHistory'],
            'permission_callback' => $perm,
            'args' => [
                'months' => ['type' => 'integer', 'default' => 12],
            ],
        ]);

        register_rest_route($this->namespace, '/admin/usage', [
            'methods' => 'GET',
            'callback' => [$this, 'getUsageOverview'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/revenue/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getRevenueStats'],
            'permission_callback' => $perm,
        ]);

        // --- Support tickets (admin side) ---
        register_rest_route($this->namespace, '/admin/support/tickets', [
            'methods' => 'GET',
            'callback' => [$this, 'listTickets'],
            'permission_callback' => $perm,
            'args' => [
                'status'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'priority' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'search'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route($this->namespace, '/admin/support/tickets/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getTicket'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/support/tickets/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'updateTicket'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/support/tickets/(?P<id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [$this, 'replyTicket'],
            'permission_callback' => $perm,
        ]);

        // --- GEO Readiness scan ---
        register_rest_route($this->namespace, '/admin/geo-scan', [
            'methods' => 'POST',
            'callback' => [$this, 'runGeoScan'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/geo-scan/recent', [
            'methods' => 'GET',
            'callback' => [$this, 'recentGeoScans'],
            'permission_callback' => $perm,
            'args' => [
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route($this->namespace, '/admin/geo-scan/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getGeoScan'],
            'permission_callback' => $perm,
        ]);

        // --- AI models ---
        register_rest_route($this->namespace, '/admin/ai-models', [
            'methods' => 'GET',
            'callback' => [$this, 'getAiModels'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/ai-models', [
            'methods' => 'POST',
            'callback' => [$this, 'saveAiModels'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($this->namespace, '/admin/ai-models/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refreshAiModels'],
            'permission_callback' => $perm,
        ]);
    }

    /**
     * Permission callback: require an authenticated manage_options user.
     * Application Passwords authenticate via Basic Auth and populate the
     * current user, so this is sufficient for the Android app.
     */
    public function canManage(\WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    // -------------------------------------------------------------------------
    // Dashboard / ping
    // -------------------------------------------------------------------------

    public function ping(\WP_REST_Request $request): \WP_REST_Response
    {
        $user = wp_get_current_user();
        return new \WP_REST_Response([
            'success' => true,
            'authenticated' => true,
            'user' => $user->exists() ? [
                'id' => (int) $user->ID,
                'login' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
            ] : null,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // License keys
    // -------------------------------------------------------------------------

    public function getLicenseStats(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'stats' => $this->licenseGenerator->getLicenseStats(),
        ], 200);
    }

    public function listLicenses(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = array_filter([
            'status' => $request->get_param('status'),
            'type'   => $request->get_param('type'),
            'tier'   => $request->get_param('tier'),
            'search' => $request->get_param('search'),
        ]);

        $limit = max(1, min(200, (int) $request->get_param('limit')));
        $offset = max(0, (int) $request->get_param('offset'));

        $licenses = $this->licenseGenerator->getLicenses($filters, $limit, $offset);
        $total = $this->licenseGenerator->countLicenses($filters);

        return new \WP_REST_Response([
            'success' => true,
            'licenses' => $licenses,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    public function generateLicenses(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();
        $count = (int) ($body['count'] ?? 1);

        $options = [
            'type'            => sanitize_text_field($body['type'] ?? 'paid'),
            'tier'            => sanitize_text_field($body['tier'] ?? 'starter'),
            'max_sites'       => (int) ($body['max_sites'] ?? 1),
            'rate_limit'      => (int) ($body['rate_limit'] ?? 0),
            'api_calls_limit' => (int) ($body['api_calls_limit'] ?? 0),
            'expires_days'    => isset($body['expires_days']) && $body['expires_days'] !== '' ? (int) $body['expires_days'] : null,
            'assigned_to'     => sanitize_email($body['assigned_to'] ?? ''),
            'notes'           => sanitize_textarea_field($body['notes'] ?? ''),
            'key_prefix'      => sanitize_text_field($body['key_prefix'] ?? ''),
        ];

        if ($count <= 1) {
            $result = $this->licenseGenerator->generateLicense($options);
        } else {
            $count = min(100, $count);
            $result = $this->licenseGenerator->batchGenerateLicenses($count, $options);
        }

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'result' => $result,
        ], 201);
    }

    public function getLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('key');
        $license = $this->licenseGenerator->getLicense($licenseKey);

        if (!$license) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_found',
                'message' => __('License key not found', 'sseo-ai-saas'),
            ], 404);
        }

        // Include the associated tenant (if activated) for convenience.
        $tenant = $this->tenants->getTenantByLicense($licenseKey);

        return new \WP_REST_Response([
            'success' => true,
            'license' => $license,
            'tenant' => $tenant,
        ], 200);
    }

    public function updateLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('key');
        $body = $request->get_json_params();

        $data = [];
        foreach (['assigned_to', 'notes', 'max_sites', 'rate_limit', 'api_calls_limit', 'license_type'] as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $field === 'notes' || $field === 'assigned_to'
                    ? sanitize_text_field($body[$field])
                    : $body[$field];
            }
        }

        if (empty($data)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'no_data',
                'message' => __('No valid fields to update', 'sseo-ai-saas'),
            ], 400);
        }

        $result = $this->licenseGenerator->updateLicense($licenseKey, $data);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'license' => $this->licenseGenerator->getLicense($licenseKey),
        ], 200);
    }

    public function revokeLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('key');
        $body = $request->get_json_params();
        $reason = sanitize_textarea_field($body['reason'] ?? __('Revoked via admin app', 'sseo-ai-saas'));

        $result = $this->licenseGenerator->revokeLicense($licenseKey, $reason);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'license' => $this->licenseGenerator->getLicense($licenseKey),
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Tenants / usage reports
    // -------------------------------------------------------------------------

    public function listTenants(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = array_filter([
            'status'   => $request->get_param('status'),
            'tier'     => $request->get_param('tier'),
            'search'   => $request->get_param('search'),
            'platform' => $request->get_param('platform'),
        ]);

        $limit = max(1, min(500, (int) $request->get_param('limit')));
        $offset = max(0, (int) $request->get_param('offset'));

        $tenants = $this->tenants->getTenants($filters, $limit, $offset);

        return new \WP_REST_Response([
            'success' => true,
            'tenants' => $tenants,
            'limit' => $limit,
            'offset' => $offset,
        ], 200);
    }

    public function getTenant(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        if (!$tenant) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_found',
                'message' => __('Tenant not found', 'sseo-ai-saas'),
            ], 404);
        }

        $usage = $this->tenants->getTenantUsage($tenantKey);
        $limits = $this->tenants->checkTenantLimits($tenantKey);
        $onboardingCompleted = (bool) $this->tenants->getTenantSetting($tenantKey, 'onboarding_completed', false);
        $onboardingCompletedAt = $this->tenants->getTenantSetting($tenantKey, 'onboarding_completed_at', '');

        return new \WP_REST_Response([
            'success' => true,
            'tenant' => $tenant,
            'usage' => $usage,
            'limits' => [
                'valid' => $limits['valid'] ?? true,
                'error' => $limits['error'] ?? null,
                'checks' => (object)($limits['checks'] ?? []),
            ],
            'onboarding' => [
                'completed' => $onboardingCompleted,
                'completed_at' => $onboardingCompletedAt,
            ],
        ], 200);
    }

    public function getTenantUsageHistory(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_param('tenant_key');
        $months = max(1, min(36, (int) $request->get_param('months')));

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_found',
                'message' => __('Tenant not found', 'sseo-ai-saas'),
            ], 404);
        }

        $history = $this->tenants->getTenantUsageHistory($tenantKey, $months);

        return new \WP_REST_Response([
            'success' => true,
            'history' => $history,
        ], 200);
    }

    public function deleteTenant(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_param('tenant_key');
        $body = $request->get_json_params();

        $licenseAction = sanitize_text_field($body['license_action'] ?? 'keep');
        if (!in_array($licenseAction, ['keep', 'free', 'revoke', 'delete'], true)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_license_action',
                'message' => __('license_action must be one of: keep, free, revoke, delete', 'sseo-ai-saas'),
            ], 400);
        }

        $result = $this->tenants->deleteTenant($tenantKey, $licenseAction);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], $result->get_error_code() === 'not_found' ? 404 : 400);
        }

        return new \WP_REST_Response([
            'success' => (bool) $result,
            'tenant_key' => $tenantKey,
        ], 200);
    }

    public function getUsageOverview(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenants = $this->tenants->getTenants(['status' => 'active'], 500, 0);

        $overview = [];
        foreach ($tenants as $tenant) {
            $tenantKey = $tenant['tenant_key'];
            $usage = $this->tenants->getTenantUsage($tenantKey);
            $limits = $this->tenants->checkTenantLimits($tenantKey);
            $onboardingCompleted = (bool) $this->tenants->getTenantSetting($tenantKey, 'onboarding_completed', false);
            $onboardingCompletedAt = $this->tenants->getTenantSetting($tenantKey, 'onboarding_completed_at', '');

            $overview[] = [
                'tenant_key' => $tenantKey,
                'name' => $tenant['name'],
                'domain' => $tenant['domain'] ?: '',
                'tier' => $tenant['tier'],
                'email' => $tenant['email'] ?: '',
                'usage' => [
                    'api_calls' => (int) ($usage['api_calls'] ?? 0),
                    'api_cost' => (float) ($usage['api_cost'] ?? 0),
                    'serp_requests' => (int) ($usage['serp_requests'] ?? 0),
                    'content_generated' => (int) ($usage['content_generated'] ?? 0),
                    'keywords_tracked' => (int) ($usage['keywords_tracked'] ?? 0),
                ],
                'limits' => (object)($limits['checks'] ?? []),
                'onboarding' => [
                    'completed' => $onboardingCompleted,
                    'completed_at' => $onboardingCompletedAt,
                ],
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'tenants' => $overview,
            'count' => count($overview),
        ], 200);
    }

    public function getRevenueStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $stats = $this->revenueDashboard->getStats();
        $stats['revenue_by_tier'] = (object)($stats['revenue_by_tier'] ?? []);
        return new \WP_REST_Response([
            'success' => true,
            'stats' => $stats,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // Support tickets (admin side)
    // -------------------------------------------------------------------------

    public function listTickets(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = array_filter([
            'status'   => $request->get_param('status'),
            'priority' => $request->get_param('priority'),
            'search'   => $request->get_param('search'),
        ]);

        $tickets = $this->supportTickets->getAllTickets($filters);

        return new \WP_REST_Response([
            'success' => true,
            'tickets' => $tickets,
            'count' => count($tickets),
        ], 200);
    }

    public function getTicket(\WP_REST_Request $request): \WP_REST_Response
    {
        $ticketId = (int) $request->get_param('id');
        $ticket = $this->supportTickets->getTicketById($ticketId);

        if (!$ticket) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_found',
                'message' => __('Ticket not found', 'sseo-ai-saas'),
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'ticket' => $ticket,
        ], 200);
    }

    public function updateTicket(\WP_REST_Request $request): \WP_REST_Response
    {
        $ticketId = (int) $request->get_param('id');
        $body = $request->get_json_params();

        $data = [];
        if (isset($body['status']) && in_array($body['status'], ['open', 'reaction', 'closed'], true)) {
            $data['status'] = $body['status'];
        }
        if (isset($body['priority']) && in_array($body['priority'], ['low', 'middle', 'high'], true)) {
            $data['priority'] = $body['priority'];
        }

        if (empty($data)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'no_data',
                'message' => __('No valid fields to update', 'sseo-ai-saas'),
            ], 400);
        }

        $this->supportTickets->updateTicket($ticketId, $data);

        return new \WP_REST_Response([
            'success' => true,
            'ticket' => $this->supportTickets->getTicketById($ticketId),
        ], 200);
    }

    public function replyTicket(\WP_REST_Request $request): \WP_REST_Response
    {
        $ticketId = (int) $request->get_param('id');
        $body = $request->get_json_params();
        $message = sanitize_textarea_field($body['message'] ?? '');

        if (empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_message',
                'message' => __('Reply message is required', 'sseo-ai-saas'),
            ], 400);
        }

        $ticket = $this->supportTickets->getTicketById($ticketId);
        if (!$ticket) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_found',
                'message' => __('Ticket not found', 'sseo-ai-saas'),
            ], 404);
        }

        $user = wp_get_current_user();
        $authorName = $user->exists() ? $user->display_name : __('Support', 'sseo-ai-saas');

        $screenshots = [];
        if (!empty($body['screenshots']) && is_array($body['screenshots'])) {
            foreach ($body['screenshots'] as $url) {
                $clean = esc_url_raw($url);
                if (!empty($clean)) {
                    $screenshots[] = $clean;
                }
            }
        }

        $this->supportTickets->addStaffReply($ticketId, $authorName, $message, $screenshots);

        return new \WP_REST_Response([
            'success' => true,
            'ticket' => $this->supportTickets->getTicketById($ticketId),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GEO Readiness scan
    // -------------------------------------------------------------------------

    public function runGeoScan(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();
        $url = esc_url_raw($body['url'] ?? '');
        $rawKeywords = $body['keywords'] ?? [];
        $language = sanitize_text_field($body['language'] ?? 'nl');

        if (is_string($rawKeywords)) {
            $rawKeywords = array_filter(array_map('trim', explode("\n", $rawKeywords)));
        }
        $keywords = array_values(array_map('sanitize_text_field', $rawKeywords));

        if (empty($url) || empty($keywords)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_params',
                'message' => __('URL and keywords are required', 'sseo-ai-saas'),
            ], 400);
        }

        // GEO scans can take 30-90s; give PHP enough runway.
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $result = $this->geoScanner->scan($url, $keywords, $language);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        return new \WP_REST_Response([
            'success' => true,
            'scan_id' => $result['scan_id'],
            'report' => $result['report'],
        ], 201);
    }

    public function recentGeoScans(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = max(1, min(100, (int) $request->get_param('limit')));
        $this->geoScanRepository->deleteExpired();
        $scans = $this->geoScanRepository->getRecent($limit);

        return new \WP_REST_Response([
            'success' => true,
            'scans' => $scans,
            'count' => count($scans),
        ], 200);
    }

    public function getGeoScan(\WP_REST_Request $request): \WP_REST_Response
    {
        $scanId = (int) $request->get_param('id');
        $scan = $this->geoScanRepository->getById($scanId);

        if (!$scan) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_found',
                'message' => __('Scan not found', 'sseo-ai-saas'),
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'scan' => $scan,
        ], 200);
    }

    // -------------------------------------------------------------------------
    // AI models
    // -------------------------------------------------------------------------

    public function getAiModels(\WP_REST_Request $request): \WP_REST_Response
    {
        $useCases = ProviderRouter::getUseCases();
        $standardRouting = get_option('sseo_ai_saas_standard_routing', []);
        $premiumRouting = get_option('sseo_ai_saas_premium_routing', []);
        $standardDefaults = ProviderRouter::getRoutingForModelTier('standard');
        $premiumDefaults = ProviderRouter::getRoutingForModelTier('premium');

        $standardModels = $this->providerRouter->getMergedStandardModels();
        $premiumModels = $this->providerRouter->getMergedPremiumModels();
        $allModels = $this->providerRouter->getMergedAvailableModels();

        return new \WP_REST_Response([
            'success' => true,
            'use_cases' => (object)$useCases,
            'standard' => [
                'routing' => (object)$standardRouting,
                'defaults' => (object)$standardDefaults,
                'models' => (object)$standardModels,
            ],
            'premium' => [
                'routing' => (object)$premiumRouting,
                'defaults' => (object)$premiumDefaults,
                'models' => (object)$premiumModels,
            ],
            'all_models' => (object)$allModels,
            'all_model_count' => count($allModels),
        ], 200);
    }

    public function saveAiModels(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();

        $saved = [];

        if (isset($body['standard_routing']) && is_array($body['standard_routing'])) {
            $standard = $this->sanitizeRoutingMap($body['standard_routing']);
            update_option('sseo_ai_saas_standard_routing', $standard);
            $saved['standard_routing'] = $standard;
        }

        if (isset($body['premium_routing']) && is_array($body['premium_routing'])) {
            $premium = $this->sanitizeRoutingMap($body['premium_routing']);
            update_option('sseo_ai_saas_premium_routing', $premium);
            $saved['premium_routing'] = $premium;
        }

        if (empty($saved)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'no_data',
                'message' => __('No routing data provided', 'sseo-ai-saas'),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'saved' => (object)$saved,
            'standard' => (object)get_option('sseo_ai_saas_standard_routing', []),
            'premium' => (object)get_option('sseo_ai_saas_premium_routing', []),
        ], 200);
    }

    public function refreshAiModels(\WP_REST_Request $request): \WP_REST_Response
    {
        $allModels = $this->providerRouter->getMergedAvailableModels(true);
        $standardModels = $this->providerRouter->getMergedStandardModels(true);
        $premiumModels = $this->providerRouter->getMergedPremiumModels(true);

        return new \WP_REST_Response([
            'success' => true,
            'all_models' => (object)$allModels,
            'standard_models' => (object)$standardModels,
            'premium_models' => (object)$premiumModels,
            'all_model_count' => count($allModels),
        ], 200);
    }

    /**
     * Sanitize a routing map (use_case => model_slug).
     */
    private function sanitizeRoutingMap(array $map): array
    {
        $clean = [];
        $validKeys = array_keys(ProviderRouter::getUseCases());
        foreach ($map as $key => $model) {
            if (in_array($key, $validKeys, true)) {
                $clean[$key] = sanitize_text_field($model);
            }
        }
        return $clean;
    }
}

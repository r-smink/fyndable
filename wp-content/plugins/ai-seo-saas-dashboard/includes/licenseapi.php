<?php

namespace SSEOAISaaS;

/**
 * License API REST Endpoints
 * 
 * Provides REST API endpoints for client plugins to:
 * - Validate license keys
 * - Activate licenses
 * - Check tenant limits
 * - Report usage
 */
class LicenseAPI
{
    private LicenseKeyGenerator $licenseGenerator;
    private TenantRepository $tenants;
    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(LicenseKeyGenerator $licenseGenerator, TenantRepository $tenants)
    {
        $this->licenseGenerator = $licenseGenerator;
        $this->tenants = $tenants;
    }

    /**
     * Register REST routes
     */
    public function register(): void
    {
        // Validate license (check if valid without activating)
        register_rest_route($this->namespace, '/license/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'validateLicense'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'site_url' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_url',
                ],
            ],
        ]);

        // Activate license (converts key to tenant)
        register_rest_route($this->namespace, '/license/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'activateLicense'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'site_url' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_url',
                ],
                'site_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get tenant limits/status
        register_rest_route($this->namespace, '/tenant/status', [
            'methods' => 'POST',
            'callback' => [$this, 'getTenantStatus'],
            'permission_callback' => '__return_true',
            'args' => [
                'tenant_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Report usage from client
        register_rest_route($this->namespace, '/usage/report', [
            'methods' => 'POST',
            'callback' => [$this, 'reportUsage'],
            'permission_callback' => '__return_true',
            'args' => [
                'tenant_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'metric' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['api_calls', 'api_cost', 'serp_requests', 'content_generated', 'keywords_tracked'],
                ],
                'count' => [
                    'required' => false,
                    'type' => 'integer',
                    'default' => 1,
                ],
                'cost' => [
                    'required' => false,
                    'type' => 'number',
                    'default' => 0,
                ],
            ],
        ]);

        // Deactivate license
        register_rest_route($this->namespace, '/license/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'deactivateLicense'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'tenant_key' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Validate license endpoint
     */
    public function validateLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        $siteUrl = $request->get_param('site_url');

        // Log validation attempt
        do_action('ai_seo_saas_license_validate_attempt', $licenseKey, $siteUrl);

        $result = $this->licenseGenerator->validateLicense($licenseKey);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'valid' => true,
            'license' => $result,
        ], 200);
    }

    /**
     * Activate license endpoint
     */
    public function activateLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        $siteUrl = $request->get_param('site_url');
        $siteName = $request->get_param('site_name') ?: parse_url($siteUrl, PHP_URL_HOST);

        error_log('SSEO AI Dashboard: License activation request received');
        error_log('SSEO AI Dashboard: License Key: ' . substr($licenseKey, 0, 15) . '...');
        error_log('SSEO AI Dashboard: Site URL: ' . $siteUrl);

        // Get client IP
        $ipAddress = $request->get_header('X-Forwarded-For') 
            ?: $request->get_header('X-Real-IP') 
            ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $activationData = [
            'site_url' => $siteUrl,
            'site_name' => $siteName,
            'ip_address' => $ipAddress,
        ];

        $result = $this->licenseGenerator->activateLicense($licenseKey, $activationData);

        if (is_wp_error($result)) {
            error_log('SSEO AI Dashboard: Activation failed - ' . $result->get_error_code() . ': ' . $result->get_error_message());
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 400);
        }

        error_log('SSEO AI Dashboard: Activation successful - Tenant: ' . $result['tenant_key']);
        
        // Get white-label settings to sync to client
        $whiteLabelData = $this->getWhiteLabelData($result['tenant_key']);
        
        return new \WP_REST_Response([
            'success' => true,
            'activated' => true,
            'tenant_key' => $result['tenant_key'],
            'tier' => $result['tier'],
            'expires_at' => $result['expires_at'],
            'max_sites' => $result['max_sites'],
            'rate_limit' => $result['rate_limit'],
            'api_calls_limit' => $result['api_calls_limit'],
            'is_reactivation' => $result['reactivation'] ?? false,
            'white_label' => $whiteLabelData,
        ], 200);
    }
    
    /**
     * Get white-label data for tenant
     */
    private function getWhiteLabelData(string $tenantKey): array
    {
        // First check tenant-specific white-label settings
        $tenantBrand = $this->tenants->getTenantSetting($tenantKey, 'white_label_brand', null);
        
        if ($tenantBrand) {
            return is_array($tenantBrand) ? $tenantBrand : json_decode($tenantBrand, true) ?: [];
        }
        
        // Fall back to global white-label settings
        return [
            'company_name' => get_option('sseo_ai_saas_wl_company_name', ''),
            'company_logo' => get_option('sseo_ai_saas_wl_company_logo', ''),
            'primary_color' => get_option('sseo_ai_saas_wl_primary_color', '#2563eb'),
            'secondary_color' => get_option('sseo_ai_saas_wl_secondary_color', '#1e40af'),
            'support_email' => get_option('sseo_ai_saas_wl_support_email', ''),
            'support_url' => get_option('sseo_ai_saas_wl_support_url', ''),
        ];
    }

    /**
     * Get tenant status endpoint
     */
    public function getTenantStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_param('tenant_key');
        $licenseKey = $request->get_param('license_key');

        // Verify tenant belongs to this license
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_tenant',
                'message' => 'Tenant not found or license mismatch',
            ], 403);
        }

        $limits = $this->tenants->checkTenantLimits($tenantKey);

        return new \WP_REST_Response([
            'success' => true,
            'valid' => $limits['valid'],
            'tier' => $tenant['tier'],
            'status' => $tenant['status'],
            'limits' => $limits['checks'] ?? [],
            'rate_limit' => (int)$tenant['rate_limit'],
            'expires_at' => $tenant['expires_at'],
        ], 200);
    }

    /**
     * Report usage endpoint
     */
    public function reportUsage(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_param('tenant_key');
        $licenseKey = $request->get_param('license_key');
        $metric = $request->get_param('metric');
        $count = (int)$request->get_param('count');
        $cost = (float)$request->get_param('cost');

        // Verify tenant belongs to this license
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_tenant',
                'message' => 'Tenant not found or license mismatch',
            ], 403);
        }

        // Track usage
        $this->tenants->trackUsage($tenantKey, $metric, $count, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'tracked' => true,
        ], 200);
    }

    /**
     * Deactivate license endpoint
     */
    public function deactivateLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        $tenantKey = $request->get_param('tenant_key');

        // Verify tenant belongs to this license
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_tenant',
                'message' => 'Tenant not found or license mismatch',
            ], 403);
        }

        // Suspend tenant (soft delete)
        $this->tenants->suspendTenant($tenantKey, 'License deactivated by client');

        return new \WP_REST_Response([
            'success' => true,
            'deactivated' => true,
        ], 200);
    }
}

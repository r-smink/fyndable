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

        // Get tenant dashboard data (for client plugin)
        register_rest_route($this->namespace, '/tenant/dashboard', [
            'methods' => 'POST',
            'callback' => [$this, 'getTenantDashboard'],
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

        // Admin: Get license features
        register_rest_route($this->namespace, '/license/features', [
            'methods' => 'GET',
            'callback' => [$this, 'getLicenseFeatures'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        // Admin: Update license features
        register_rest_route($this->namespace, '/license/features', [
            'methods' => 'POST',
            'callback' => [$this, 'updateLicenseFeatures'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string'],
                'features' => ['required' => true, 'type' => 'array'],
            ],
        ]);

        // Admin: Get all available features
        register_rest_route($this->namespace, '/features/all', [
            'methods' => 'GET',
            'callback' => [$this, 'getAllFeatures'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
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

        // Get SaaS settings instance
        $settings = new \SSEOAISaaS\SaaSSettings();
        
        return new \WP_REST_Response([
            'success' => true,
            'valid' => true,
            'license' => $result,
            'image_api' => [
                'provider' => $settings->getImageApiProvider(),
                'key' => $settings->getImageApiKey(),
                'model' => $settings->getImageApiModel(),
            ],
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
        
        // Get SaaS settings for image API
        $settings = new \SSEOAISaaS\SaaSSettings();
        
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
            'image_api' => [
                'provider' => $settings->getImageApiProvider(),
                'key' => $settings->getImageApiKey(),
                'model' => $settings->getImageApiModel(),
            ],
        ], 200);
    }
    
    /**
     * Get white-label data for tenant - ONLY tenant-level (no global fallback)
     */
    private function getWhiteLabelData(string $tenantKey): array
    {
        // Only tenant-specific white-label settings (no global fallback)
        $tenantBrand = $this->tenants->getTenantSetting($tenantKey, 'white_label_brand', null);
        $enabled = $this->tenants->getTenantSetting($tenantKey, 'enable_whitelabel', false);
        
        // Only return white-label if explicitly enabled and configured
        if ($enabled && $tenantBrand) {
            $brand = is_array($tenantBrand) ? $tenantBrand : (json_decode($tenantBrand, true) ?: []);
            if (!empty($brand['company_name'])) {
                return $brand;
            }
        }
        
        // Return empty if no tenant white-label configured
        return [
            'company_name' => '',
            'company_logo' => '',
            'primary_color' => '#2563eb',
            'secondary_color' => '#1e40af',
            'support_email' => '',
            'support_url' => '',
            'enabled' => false,
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
        
        // Get SaaS settings for image API
        $settings = new \SSEOAISaaS\SaaSSettings();

        return new \WP_REST_Response([
            'success' => true,
            'valid' => $limits['valid'],
            'tier' => $tenant['tier'],
            'status' => $tenant['status'],
            'limits' => $limits['checks'] ?? [],
            'rate_limit' => (int)($tenant['rate_limit'] ?: LicenseKeyGenerator::getDefaultRateLimit($tenant['tier'])),
            'api_calls_limit' => (int)($tenant['api_calls_limit'] ?: LicenseKeyGenerator::getDefaultApiLimit($tenant['tier'])),
            'expires_at' => $tenant['expires_at'],
            'image_api' => [
                'provider' => $settings->getImageApiProvider(),
                'key' => $settings->getImageApiKey(),
                'model' => $settings->getImageApiModel(),
            ],
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

        // Set tenant to inactive (not suspended — suspended is only for revoked licenses)
        $this->tenants->updateTenant($tenantKey, [
            'status' => 'inactive',
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'deactivated' => true,
        ], 200);
    }

    /**
     * Get tenant dashboard data for client plugin
     */
    public function getTenantDashboard(\WP_REST_Request $request): \WP_REST_Response
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

        $currentPeriod = current_time('Y-m');
        $usage = $this->tenants->getTenantUsage($tenantKey, $currentPeriod);
        $limits = $this->tenants->checkTenantLimits($tenantKey);

        // Get effective features for this license/tenant
        $featureManager = $this->getFeatureManager();
        $effectiveFeatures = $featureManager->getEffectiveFeatures($licenseKey, $tenantKey);

        return new \WP_REST_Response([
            'success' => true,
            'tenant' => [
                'tenant_key' => $tenant['tenant_key'],
                'name' => $tenant['name'],
                'email' => $tenant['email'],
                'tier' => $tenant['tier'],
                'status' => $tenant['status'],
                'created_at' => $tenant['created_at'],
                'expires_at' => $tenant['expires_at'],
                'max_sites' => (int)$tenant['max_sites'],
                'rate_limit' => (int)$tenant['rate_limit'],
                'api_calls_limit' => (int)$tenant['api_calls_limit'],
            ],
            'usage' => [
                'current_month' => [
                    'api_calls' => (int)($usage['api_calls'] ?? 0),
                    'api_calls_limit' => (int)$tenant['api_calls_limit'],
                    'content_generated' => (int)($usage['content_generated'] ?? 0),
                    'serp_requests' => (int)($usage['serp_requests'] ?? 0),
                    'keywords_tracked' => (int)($usage['keywords_tracked'] ?? 0),
                    'api_cost' => (float)($usage['api_cost'] ?? 0),
                ],
                'remaining_calls' => max(0, (int)$tenant['api_calls_limit'] - (int)($usage['api_calls'] ?? 0)),
            ],
            'white_label' => $this->getWhiteLabelData($tenantKey),
            'limits' => $limits['checks'] ?? [],
            'features' => array_keys($effectiveFeatures), // Include enabled features
        ], 200);
    }

    /**
     * Get license features (admin endpoint)
     */
    public function getLicenseFeatures(\WP_REST_Request $request): \WP_REST_Response
    {
        // Admin only
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'unauthorized',
                'message' => 'Admin access required',
            ], 403);
        }

        $licenseKey = $request->get_param('license_key');
        $featureManager = $this->getFeatureManager();

        $data = $featureManager->getFeatureToggleData($licenseKey);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Update license features (admin endpoint)
     */
    public function updateLicenseFeatures(\WP_REST_Request $request): \WP_REST_Response
    {
        // Admin only
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'unauthorized',
                'message' => 'Admin access required',
            ], 403);
        }

        $licenseKey = $request->get_param('license_key');
        $features = $request->get_param('features');

        if (!is_array($features)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_features',
                'message' => 'Features must be an array',
            ], 400);
        }

        $featureManager = $this->getFeatureManager();
        $success = $featureManager->updateLicenseFeatures($licenseKey, $features);

        if ($success) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => 'Features updated successfully',
            ], 200);
        } else {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'update_failed',
                'message' => 'Failed to update features',
            ], 500);
        }
    }

    /**
     * Get all available features (admin endpoint)
     */
    public function getAllFeatures(\WP_REST_Request $request): \WP_REST_Response
    {
        // Admin only
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'unauthorized',
                'message' => 'Admin access required',
            ], 403);
        }

        $featureManager = $this->getFeatureManager();
        $features = $featureManager->getAllFeatures();

        return new \WP_REST_Response([
            'success' => true,
            'features' => $features,
        ], 200);
    }

    /**
     * Get feature manager instance
     */
    private function getFeatureManager(): LicenseFeatureManager
    {
        return new LicenseFeatureManager($this->tenants, $this->licenseGenerator);
    }
}

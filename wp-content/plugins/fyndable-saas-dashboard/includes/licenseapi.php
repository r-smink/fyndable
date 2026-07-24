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

        // Start free trial (no license key needed â€” creates one)
        register_rest_route($this->namespace, '/license/trial', [
            'methods' => 'POST',
            'callback' => [$this, 'startTrial'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'name' => [
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

        // Google OAuth proxy: get client ID (for GIS init in browser)
        register_rest_route($this->namespace, '/google/oauth-config', [
            'methods' => 'POST',
            'callback' => [$this, 'getGoogleOAuthConfig'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Google OAuth proxy: exchange auth code for tokens (secret stays server-side)
        register_rest_route($this->namespace, '/google/exchange', [
            'methods' => 'POST',
            'callback' => [$this, 'exchangeGoogleCode'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'code' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Google Ads: get developer token (for authenticated tenants)
        register_rest_route($this->namespace, '/google/ads-dev-token', [
            'methods' => 'POST',
            'callback' => [$this, 'getGoogleAdsDevToken'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Google API usage reporting (client reports its Google API calls)
        register_rest_route($this->namespace, '/google/report-usage', [
            'methods' => 'POST',
            'callback' => [$this, 'reportGoogleUsage'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'service' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'calls' => ['required' => false, 'type' => 'integer', 'default' => 1],
                'cost' => ['required' => false, 'type' => 'number', 'default' => 0],
            ],
        ]);

        // Google OAuth start page â€” renders HTML with GIS popup (only SaaS domain needed in Google Console)
        register_rest_route($this->namespace, '/google/oauth-start', [
            'methods' => 'GET',
            'callback' => [$this, 'googleOAuthStart'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // GDPR: Request data deletion (tenant can self-delete)
        register_rest_route($this->namespace, '/gdpr/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'gdprDelete'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'confirm' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // GDPR: Export all tenant data
        register_rest_route($this->namespace, '/gdpr/export', [
            'methods' => 'POST',
            'callback' => [$this, 'gdprExport'],
            'permission_callback' => '__return_true',
            'args' => [
                'license_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
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

        // Get SaaS settings instance
        $settings = new \SSEOAISaaS\SaaSSettings();
        
        return new \WP_REST_Response([
            'success' => true,
            'valid' => true,
            'license' => $result,
            'model_routing' => get_option('sseo_ai_saas_model_routing', []),
            'image_api' => [
                'provider' => $settings->getImageApiProvider(),
                'key' => match ($settings->getImageApiProvider()) {
                    'openart' => get_option('ai_seo_saas_openart_api_key', ''),
                    'openrouter' => get_option('sseo_ai_saas_openrouter_api_key', ''),
                    default => $settings->getImageApiKey(),
                },
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
            'model_routing' => get_option('sseo_ai_saas_model_routing', []),
            'image_api' => [
                'provider' => $settings->getImageApiProvider(),
                'key' => match ($settings->getImageApiProvider()) {
                    'openart' => get_option('ai_seo_saas_openart_api_key', ''),
                    'openrouter' => get_option('sseo_ai_saas_openrouter_api_key', ''),
                    default => $settings->getImageApiKey(),
                },
                'model' => $settings->getImageApiModel(),
            ],
        ], 200);
    }
    
    /**
     * Get white-label data for tenant.
     *
     * Falls back to the global agency white-label settings when no tenant-specific
     * brand is configured. This ensures agency/whitelabel API keys sync the correct
     * branding to the client plugin.
     */
    private function getWhiteLabelData(string $tenantKey): array
    {
        $isGlobalEnabled = (bool) get_option('sseo_ai_saas_wl_enabled', false);

        // Tenant-specific white-label takes priority if enabled.
        $tenantBrand = $this->tenants->getTenantSetting($tenantKey, 'white_label_brand', null);
        $tenantEnabled = (bool) $this->tenants->getTenantSetting($tenantKey, 'enable_whitelabel', false);
        if ($tenantEnabled && $tenantBrand) {
            $brand = is_array($tenantBrand) ? $tenantBrand : (json_decode($tenantBrand, true) ?: []);
            if (!empty($brand['company_name'])) {
                return $brand;
            }
        }

        // Fall back to global agency white-label settings.
        if ($isGlobalEnabled) {
            return [
                'company_name' => get_option('sseo_ai_saas_wl_company_name', ''),
                'company_logo' => get_option('sseo_ai_saas_wl_company_logo', ''),
                'primary_color' => get_option('sseo_ai_saas_wl_primary_color', '#379fd3'),
                'secondary_color' => get_option('sseo_ai_saas_wl_secondary_color', '#8f39ac'),
                'use_primary_only' => false,
                'support_email' => get_option('sseo_ai_saas_wl_support_email', ''),
                'support_url' => get_option('sseo_ai_saas_wl_support_url', ''),
                'enabled' => true,
            ];
        }

        return [
            'company_name' => '',
            'company_logo' => '',
            'primary_color' => '',
            'secondary_color' => '',
            'use_primary_only' => false,
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
            'monthly_auto_posts' => (int)$settings->getAutoPostLimitForTier($tenant['tier']),
            'expires_at' => $tenant['expires_at'],
            'white_label' => $this->getWhiteLabelData($tenantKey),
            'model_routing' => get_option('sseo_ai_saas_model_routing', []),
            'image_api' => [
                'provider' => $settings->getImageApiProvider(),
                'key' => match ($settings->getImageApiProvider()) {
                    'openart' => get_option('ai_seo_saas_openart_api_key', ''),
                    'openrouter' => get_option('sseo_ai_saas_openrouter_api_key', ''),
                    default => $settings->getImageApiKey(),
                },
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

        // Set tenant to inactive (not suspended â€” suspended is only for revoked licenses)
        $this->tenants->updateTenant($tenantKey, [
            'status' => 'inactive',
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'deactivated' => true,
        ], 200);
    }

    /**
     * Start a free trial â€” creates a trial tenant with 14-day expiry.
     * No license key needed; one is generated automatically.
     */
    public function startTrial(\WP_REST_Request $request): \WP_REST_Response
    {
        $email = $request->get_param('email');
        $name = $request->get_param('name');
        $siteUrl = $request->get_param('site_url');

        // Check if email already has a tenant (prevent trial abuse)
        $allTenants = $this->tenants->getAllTenants(500);
        foreach ($allTenants as $existing) {
            if (($existing['email'] ?? '') === $email) {
                $tier = $existing['tier'] ?? '';
                if ($tier === 'trial') {
                    return new \WP_REST_Response([
                        'success' => false,
                        'error' => 'trial_exists',
                        'message' => 'You already have a trial account. Use your existing license key or upgrade.',
                        'license_key' => $existing['license_key'] ?? '',
                    ], 409);
                }
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'account_exists',
                    'message' => 'An account with this email already exists.',
                ], 409);
            }
        }

        // Generate license key for trial
        $licenseResult = $this->licenseGenerator->generateLicense([
            'tier' => 'trial',
            'name' => $name,
        ]);

        if (is_wp_error($licenseResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to generate license key.',
            ], 500);
        }

        $licenseKey = $licenseResult['license_key'] ?? '';

        // Create trial tenant (14 days)
        $trialDays = (int) get_option('sseo_ai_saas_trial_days', 14);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $trialDays * DAY_IN_SECONDS);

        $tenantResult = $this->tenants->createTenant([
            'name' => $name,
            'email' => $email,
            'domain' => $siteUrl,
            'tier' => 'trial',
            'license_key' => $licenseKey,
            'status' => 'active',
            'max_sites' => 1,
            'rate_limit' => 200,
            'api_calls_limit' => 5000,
            'expires_at' => $expiresAt,
        ]);

        if (is_wp_error($tenantResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $tenantResult->get_error_message(),
            ], 500);
        }

        $tenantKey = $tenantResult['tenant_key'];

        // Fire activation hook for welcome email
        do_action('sseo_ai_license_activated', $tenantKey, [
            'email' => $email,
            'tier' => 'trial',
            'license_key' => $licenseKey,
            'expires_at' => $expiresAt,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'license_key' => $licenseKey,
            'tenant_key' => $tenantKey,
            'tier' => 'trial',
            'expires_at' => $expiresAt,
            'trial_days' => $trialDays,
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

    /**
     * Validate tenant credentials from request
     */
    private function validateTenant(\WP_REST_Request $request): array|\WP_Error
    {
        $licenseKey = $request->get_param('license_key');
        $tenantKey = $request->get_param('tenant_key');

        if (empty($licenseKey) || empty($tenantKey)) {
            return new \WP_Error('missing_credentials', 'License key and tenant key are required');
        }

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_Error('invalid_credentials', 'Invalid license or tenant credentials');
        }

        if ($tenant['status'] !== 'active') {
            return new \WP_Error('inactive_tenant', 'Tenant is not active');
        }

        return $tenant;
    }

    /**
     * REST: Get Google OAuth config (client ID only â€” secret stays server-side)
     */
    public function getGoogleOAuthConfig(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->validateTenant($request);
        if (is_wp_error($tenant)) {
            return new \WP_REST_Response(['success' => false, 'error' => $tenant->get_error_code(), 'message' => $tenant->get_error_message()], 403);
        }

        $settings = new SaaSSettings();
        $clientId = $settings->getGoogleClientId();

        if (empty($clientId)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'not_configured', 'message' => 'Google OAuth is not configured on the SaaS dashboard'], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'client_id' => $clientId,
            'scopes' => 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/indexing https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/adwords',
        ], 200);
    }

    /**
     * REST: Exchange Google auth code for tokens (server-side, secret never exposed)
     */
    public function exchangeGoogleCode(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->validateTenant($request);
        if (is_wp_error($tenant)) {
            return new \WP_REST_Response(['success' => false, 'error' => $tenant->get_error_code(), 'message' => $tenant->get_error_message()], 403);
        }

        // Track OAuth exchange call
        $this->tenants->trackGoogleApiUsage($request->get_param('tenant_key'), 'oauth');

        $code = $request->get_param('code');
        if (empty($code)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'missing_code', 'message' => 'Authorization code is required'], 400);
        }

        $settings = new SaaSSettings();
        $clientId = $settings->getGoogleClientId();
        $clientSecret = $settings->getGoogleClientSecret();

        if (empty($clientId) || empty($clientSecret)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'not_configured', 'message' => 'Google OAuth is not configured on the SaaS dashboard'], 400);
        }

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body' => [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => 'postmessage',
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'exchange_failed', 'message' => $response->get_error_message()], 500);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || !is_array($body)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'exchange_failed', 'message' => 'Token exchange failed', 'details' => $body], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'tokens' => $body,
        ], 200);
    }

    /**
     * REST: Get Google Ads developer token (for authenticated tenants only)
     */
    public function getGoogleAdsDevToken(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->validateTenant($request);
        if (is_wp_error($tenant)) {
            return new \WP_REST_Response(['success' => false, 'error' => $tenant->get_error_code(), 'message' => $tenant->get_error_message()], 403);
        }

        $settings = new SaaSSettings();
        $devToken = $settings->getGoogleAdsDevToken();

        if (empty($devToken)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'not_configured', 'message' => 'Google Ads developer token is not configured'], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'dev_token' => $devToken,
        ], 200);
    }

    /**
     * REST: Report Google API usage from client plugin
     * Clients report their Google API calls (gsc, ga4, ads) for cost tracking.
     */
    public function reportGoogleUsage(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->validateTenant($request);
        if (is_wp_error($tenant)) {
            return new \WP_REST_Response(['success' => false, 'error' => $tenant->get_error_code(), 'message' => $tenant->get_error_message()], 403);
        }

        $service = $request->get_param('service');
        $calls = (int)$request->get_param('calls');
        $cost = (float)$request->get_param('cost');

        $validServices = ['gsc', 'ga4', 'ads', 'oauth'];
        if (!in_array($service, $validServices, true)) {
            return new \WP_REST_Response(['success' => false, 'error' => 'invalid_service', 'message' => 'Service must be one of: gsc, ga4, ads, oauth'], 400);
        }

        $this->tenants->trackGoogleApiUsage($request->get_param('tenant_key'), $service, $calls, $cost);

        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * REST: Render Google OAuth start page (GIS popup runs on SaaS domain)
     * This page loads GIS, gets the auth code, exchanges it server-side,
     * and sends tokens back to the client site via postMessage.
     */
    public function googleOAuthStart(\WP_REST_Request $request): void
    {
        $licenseKey = $request->get_param('license_key');
        $tenantKey = $request->get_param('tenant_key');

        // Validate tenant
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            wp_die(__('Invalid credentials.', 'sseo-ai-saas'), 403);
        }
        if ($tenant['status'] !== 'active') {
            wp_die(__('Tenant is not active.', 'sseo-ai-saas'), 403);
        }

        $settings = new SaaSSettings();
        $clientId = $settings->getGoogleClientId();
        if (empty($clientId)) {
            wp_die(__('Google OAuth is not configured on the SaaS dashboard.', 'sseo-ai-saas'), 500);
        }

        $whiteLabel = $this->getWhiteLabelData($tenantKey);
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $globalCompanyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : ($globalCompanyName ?: 'Fyndable');

        $scopes = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/indexing https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/adwords';
        $exchangeUrl = rest_url($this->namespace . '/google/exchange');

        // SECURITY: restrict postMessage target origin to the tenant's registered
        // domain so OAuth tokens cannot be intercepted by an arbitrary opener.
        // Falls back to '*' only when the domain cannot be parsed (never breaks flow).
        $clientOrigin = '*';
        if (!empty($tenant['domain'])) {
            $parsed = wp_parse_url($tenant['domain']);
            if (!empty($parsed['host'])) {
                $clientOrigin = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
                if (!empty($parsed['port'])) {
                    $clientOrigin .= ':' . $parsed['port'];
                }
            }
        }

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(sprintf(__('Connect Google â€” %s', 'sseo-ai-saas'), $companyName)); ?></title>
    <style>
        body { font-family: Outfit, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f0f0f1; }
        .card { background: #fff; border-radius: 12px; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); text-align: center; max-width: 420px; }
        .logo { font-size: 24px; font-weight: 700; color: #379fd3; margin-bottom: 8px; }
        .status { color: #555; margin: 16px 0; }
        .error { color: #d63638; }
        .spinner { display: inline-block; width: 32px; height: 32px; border: 3px solid #e0e0e0; border-top-color: #379fd3; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 20px 0; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn { display: inline-block; background: #379fd3; color: #fff; border: none; border-radius: 8px; padding: 14px 32px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn:hover { background: #2a7ba8; }
        .btn:disabled { background: #93a3bf; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"><?php echo esc_html($companyName); ?></div>
        <div id="status-area">
            <p class="status">Click the button below to connect your Google account.</p>
            <button class="btn" id="google-connect-btn" onclick="sseoStartGoogleAuth()">Connect Google Account</button>
        </div>
    </div>

    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>
        (function() {
            var CLIENT_ID = <?php echo wp_json_encode($clientId); ?>;
            var SCOPES = <?php echo wp_json_encode($scopes); ?>;
            var EXCHANGE_URL = <?php echo wp_json_encode($exchangeUrl); ?>;
            var LICENSE_KEY = <?php echo wp_json_encode($licenseKey); ?>;
            var TENANT_KEY = <?php echo wp_json_encode($tenantKey); ?>;
            var TARGET_ORIGIN = <?php echo wp_json_encode($clientOrigin); ?>;

            var statusArea = document.getElementById('status-area');

            function setStatus(msg, isError) {
                statusArea.innerHTML = '<p class="status' + (isError ? ' error' : '') + '">' + msg + '</p>';
            }

            function closePopup() {
                setTimeout(function() { window.close(); }, 1500);
            }

            var tokenClient = null;

            function initGIS() {
                if (!window.google || !google.accounts || !google.accounts.oauth2) {
                    setTimeout(initGIS, 100);
                    return;
                }

                tokenClient = google.accounts.oauth2.initCodeClient({
                    client_id: CLIENT_ID,
                    scope: SCOPES,
                    ux_mode: 'popup',
                    callback: function(response) {
                            setStatus('Exchanging authorization code...');

                            fetch(EXCHANGE_URL, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    license_key: LICENSE_KEY,
                                    tenant_key: TENANT_KEY,
                                    code: response.code
                                })
                            }).then(function(r) { return r.json(); }).then(function(data) {
                                if (data.success && data.tokens) {
                                    // Send tokens to opener window
                                    if (window.opener) {
                                        window.opener.postMessage({
                                            type: 'fyndable_google_tokens',
                                            tokens: data.tokens,
                                            success: true
                                        }, TARGET_ORIGIN);
                                    }
                                    setStatus('Successfully connected! Closing...');
                                    closePopup();
                                } else {
                                    var msg = data.message || 'Token exchange failed';
                                    if (window.opener) {
                                        window.opener.postMessage({
                                            type: 'fyndable_google_tokens',
                                            success: false,
                                            error: msg
                                        }, TARGET_ORIGIN);
                                    }
                                    setStatus(msg, true);
                                    closePopup();
                                }
                            }).catch(function(err) {
                                var msg = err.message || 'Network error during exchange';
                                if (window.opener) {
                                    window.opener.postMessage({
                                        type: 'fyndable_google_tokens',
                                        success: false,
                                        error: msg
                                    }, TARGET_ORIGIN);
                                }
                                setStatus(msg, true);
                                closePopup();
                            });
                        },
                        error_callback: function(error) {
                            var msg = error.message || error.type || 'Google login failed';
                            if (window.opener) {
                                window.opener.postMessage({
                                    type: 'fyndable_google_tokens',
                                    success: false,
                                    error: msg
                                }, TARGET_ORIGIN);
                            }
                            setStatus(msg, true);
                            closePopup();
                        }
                });
            }

            window.sseoStartGoogleAuth = function() {
                var btn = document.getElementById('google-connect-btn');
                if (btn) btn.disabled = true;
                setStatus('Connecting to Google...');
                if (tokenClient) {
                    tokenClient.requestCode();
                } else {
                    setStatus('Google library still loading, please wait...', true);
                    if (btn) btn.disabled = false;
                }
            }

            window.addEventListener('load', initGIS);
        })();
    </script>
</body>
</html>
        <?php
        exit;
    }

    /**
     * GDPR: Delete all tenant data (right to erasure).
     * Anonymizes PII and removes tenant records.
     */
    public function gdprDelete(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        $tenantKey = $request->get_param('tenant_key');
        $confirm = $request->get_param('confirm');

        if ($confirm !== 'DELETE') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Confirmation required. Send confirm=DELETE to proceed.',
            ], 400);
        }

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid tenant or license mismatch.',
            ], 403);
        }

        global $wpdb;

        // Anonymize and delete tenant record
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';
        $wpdb->update($tenantsTable, [
            'name' => '[deleted]',
            'email' => '[deleted]',
            'domain' => null,
            'status' => 'deleted',
            'license_key' => null,
            'metadata' => null,
        ], ['tenant_key' => $tenantKey]);

        // Delete usage records
        $usageTable = $wpdb->prefix . 'sseo_ai_tenant_usage';
        $wpdb->delete($usageTable, ['tenant_key' => $tenantKey]);

        // Delete license key record
        $licenseTable = $wpdb->prefix . 'sseo_ai_license_keys';
        $wpdb->delete($licenseTable, ['license_key' => $licenseKey]);

        // Delete support tickets
        $ticketsTable = $wpdb->prefix . 'sseo_ai_support_tickets';
        $wpdb->delete($ticketsTable, ['tenant_key' => $tenantKey]);

        // Delete Google tokens if any
        delete_option('sseo_ai_google_tokens_' . $tenantKey);

        // Fire action for extensions
        do_action('sseo_ai_gdpr_tenant_deleted', $tenantKey, $licenseKey);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'All personal data has been deleted.',
            'deleted_at' => current_time('mysql'),
        ], 200);
    }

    /**
     * GDPR: Export all tenant data (data portability).
     */
    public function gdprExport(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        $tenantKey = $request->get_param('tenant_key');

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid tenant or license mismatch.',
            ], 403);
        }

        global $wpdb;

        // Gather all tenant data
        $exportData = [
            'tenant' => [
                'name' => $tenant['name'],
                'email' => $tenant['email'],
                'domain' => $tenant['domain'],
                'tier' => $tenant['tier'],
                'status' => $tenant['status'],
                'created_at' => $tenant['created_at'],
                'expires_at' => $tenant['expires_at'],
                'license_key' => $tenant['license_key'],
            ],
        ];

        // Usage data
        $usageTable = $wpdb->prefix . 'sseo_ai_tenant_usage';
        $usageRecords = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $usageTable WHERE tenant_key = %s ORDER BY period DESC LIMIT 12",
            $tenantKey
        ), ARRAY_A);
        $exportData['usage_history'] = $usageRecords;

        // Support tickets
        $ticketsTable = $wpdb->prefix . 'sseo_ai_support_tickets';
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT id, subject, status, created_at, updated_at FROM $ticketsTable WHERE tenant_key = %s ORDER BY created_at DESC",
            $tenantKey
        ), ARRAY_A);
        $exportData['support_tickets'] = $tickets;

        // Google integration status
        $googleTokens = get_option('sseo_ai_google_tokens_' . $tenantKey, []);
        $exportData['google_integrations'] = [
            'connected_services' => array_keys($googleTokens),
            'has_tokens' => !empty($googleTokens),
        ];

        $exportData['exported_at'] = current_time('mysql');

        return new \WP_REST_Response([
            'success' => true,
            'data' => $exportData,
        ], 200);
    }
}

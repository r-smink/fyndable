<?php

namespace SSEOAIClient;

/**
 * License Validator
 * 
 * Validates licenses locally and with the SaaS Dashboard
 */
class LicenseValidator
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Check if license is valid
     */
    public function isLicenseValid(): bool
    {
        $status = get_option('sseo_ai_client_license_status', 'inactive');
        return $status === 'active';
    }

    /**
     * Get current license tier
     */
    public function getLicenseTier(): string
    {
        return get_option('sseo_ai_client_license_tier', 'free');
    }

    /**
     * Validate stored license with dashboard
     */
    public function validateStoredLicense(): void
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            update_option('sseo_ai_client_license_status', 'inactive');
            return;
        }

        // Only validate once per hour (use transient)
        $cacheKey = 'ai_seo_license_check_' . md5($licenseKey);
        if (get_transient($cacheKey)) {
            return;
        }

        set_transient($cacheKey, true, HOUR_IN_SECONDS);

        // Make API call to validate
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/tenant/status',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => $this->settings->sslVerify(),
            ]
        );

        if (is_wp_error($response)) {
            // If network error, keep current status but mark as maybe stale
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['success']) || !$body['valid']) {
            update_option('sseo_ai_client_license_status', 'invalid');
            return;
        }

        // Update local options with latest data
        update_option('sseo_ai_client_license_status', 'active');
        update_option('sseo_ai_client_license_tier', $body['tier'] ?? 'free');
        update_option('sseo_ai_client_license_type', $body['type'] ?? 'paid');
        if (!empty($body['expires_at'])) {
            update_option('sseo_ai_client_license_expires', $body['expires_at']);
        }
        if (!empty($body['rate_limit'])) {
            update_option('sseo_ai_client_rate_limit', $body['rate_limit']);
        }
        if (!empty($body['api_calls_limit'])) {
            update_option('sseo_ai_client_api_limit', $body['api_calls_limit']);
        }
        if (!empty($body['model_routing']) && is_array($body['model_routing'])) {
            update_option('sseo_ai_client_model_routing', $body['model_routing']);
        }

        // Store image API credentials from SaaS dashboard
        if (!empty($body['image_api'])) {
            update_option('sseo_ai_client_image_api', $body['image_api']);
        }

        // Store white-label settings from SaaS dashboard
        if (!empty($body['white_label']) && is_array($body['white_label'])) {
            update_option('sseo_ai_white_label', $body['white_label']);
        }

        // Store enabled features from SaaS dashboard (feature overrides)
        if (!empty($body['features']) && is_array($body['features'])) {
            update_option('sseo_ai_client_enabled_features', $body['features']);
        }
    }

    /**
     * Check if specific feature is available
     */
    public function hasFeature(string $feature): bool
    {
        $tier = $this->getLicenseTier();
        
        // DEV tier has ALL features (for development/testing)
        if ($tier === 'dev') {
            return true;
        }
        
        // Check if features are overridden from SaaS dashboard
        $enabledFeatures = get_option('sseo_ai_client_enabled_features', null);
        if ($enabledFeatures !== null && is_array($enabledFeatures)) {
            // Use SaaS-provided feature list
            return in_array($feature, $enabledFeatures);
        }
        
        // Fallback to tier-based features
        $features = [
            'free' => ['content_analysis', 'meta_optimization'],
            'starter' => ['content_analysis', 'meta_optimization'],
            'trial' => ['content_analysis', 'meta_optimization', 'serp_tracking', 'keyword_research'],
            'professional' => ['content_analysis', 'meta_optimization', 'serp_tracking', 'keyword_research'],
            'business' => ['content_analysis', 'meta_optimization', 'serp_tracking', 'keyword_research', 'content_decay', 'ai_generation'],
            'agency' => ['content_analysis', 'meta_optimization', 'serp_tracking', 'keyword_research', 'content_decay', 'ai_generation', 'multi_site', 'white_label'],
        ];

        return in_array($feature, $features[$tier] ?? []);
    }

    /**
     * Default rate limits per tier (requests per hour)
     */
    private const TIER_RATE_LIMITS = [
        'free'         => 30,
        'starter'      => 60,
        'trial'        => 200,
        'professional' => 200,
        'business'     => 500,
        'agency'       => 1000,
        'dev'          => PHP_INT_MAX,
    ];
    
    /**
     * Default API call limits per tier (per month)
     */
    private const TIER_API_LIMITS = [
        'free'         => 500,
        'starter'      => 1000,
        'trial'        => 5000,
        'professional' => 10000,
        'business'     => 50000,
        'agency'       => 200000,
        'dev'          => PHP_INT_MAX,
    ];

    /**
     * Get API rate limit (requests per hour)
     */
    public function getRateLimit(): int
    {
        $tier = $this->getLicenseTier();
        
        // DEV tier has no rate limiting
        if ($tier === 'dev') {
            return PHP_INT_MAX;
        }
        
        $tierDefault = self::TIER_RATE_LIMITS[$tier] ?? 60;
        return (int)get_option('sseo_ai_client_rate_limit', $tierDefault);
    }

    /**
     * Get API calls limit per month
     */
    public function getApiLimit(): int
    {
        $tier = $this->getLicenseTier();
        
        // DEV tier has unlimited API calls
        if ($tier === 'dev') {
            return PHP_INT_MAX;
        }
        
        $tierDefault = self::TIER_API_LIMITS[$tier] ?? 1000;
        return (int)get_option('sseo_ai_client_api_limit', $tierDefault);
    }

    /**
     * Check if this is a DEV tier (unlimited testing)
     */
    public function isDev(): bool
    {
        return $this->getLicenseTier() === 'dev';
    }

    /**
     * Check if license is about to expire
     */
    public function isExpiringSoon(int $days = 7): bool
    {
        $expires = get_option('sseo_ai_client_license_expires', '');
        if (empty($expires)) {
            return false;
        }

        $expiresTime = strtotime($expires);
        if ($expiresTime === false) {
            return false;
        }

        $diff = $expiresTime - time();
        return $diff > 0 && $diff < ($days * DAY_IN_SECONDS);
    }
}

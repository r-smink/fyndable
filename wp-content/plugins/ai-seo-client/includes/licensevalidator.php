<?php

namespace AISEOClient;

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
        $status = get_option('ai_seo_client_license_status', 'inactive');
        return $status === 'active';
    }

    /**
     * Get current license tier
     */
    public function getLicenseTier(): string
    {
        return get_option('ai_seo_client_license_tier', 'free');
    }

    /**
     * Validate stored license with dashboard
     */
    public function validateStoredLicense(): void
    {
        $licenseKey = get_option(AISEO_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(AISEO_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('ai_seo_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            update_option('ai_seo_client_license_status', 'inactive');
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
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            // If network error, keep current status but mark as maybe stale
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['success']) || !$body['valid']) {
            update_option('ai_seo_client_license_status', 'invalid');
            return;
        }

        // Update local options with latest data
        update_option('ai_seo_client_license_status', 'active');
        update_option('ai_seo_client_license_tier', $body['tier'] ?? 'free');
        if (!empty($body['expires_at'])) {
            update_option('ai_seo_client_license_expires', $body['expires_at']);
        }
        if (!empty($body['rate_limit'])) {
            update_option('ai_seo_client_rate_limit', $body['rate_limit']);
        }
    }

    /**
     * Check if specific feature is available
     */
    public function hasFeature(string $feature): bool
    {
        $tier = $this->getLicenseTier();
        
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
     * Get API rate limit
     */
    public function getRateLimit(): int
    {
        return (int)get_option('ai_seo_client_rate_limit', 60);
    }

    /**
     * Get API calls limit per month
     */
    public function getApiLimit(): int
    {
        return (int)get_option('ai_seo_client_api_limit', 1000);
    }

    /**
     * Check if license is about to expire
     */
    public function isExpiringSoon(int $days = 7): bool
    {
        $expires = get_option('ai_seo_client_license_expires', '');
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

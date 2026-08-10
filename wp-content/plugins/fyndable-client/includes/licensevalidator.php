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

        // Normalize the dashboard URL (force HTTPS, strip trailing slash) to
        // prevent 301/302 redirects that convert POST into GET and break the
        // REST endpoint. This mirrors what DashboardAPI::normalizeDashboardUrl
        // does during activation.
        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);

        // Persist the normalized URL so every subsequent call uses it too.
        if ($dashboardUrl !== get_option('sseo_ai_client_dashboard_url')) {
            update_option('sseo_ai_client_dashboard_url', $dashboardUrl);
        }

        $apiUrl = rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/tenant/status';
        $postData = [
            'license_key' => $licenseKey,
            'tenant_key' => $tenantKey,
        ];

        // Make API call to validate. Use redirection => 0 so WordPress does not
        // silently follow redirects (which turns POST into GET). We handle
        // redirects manually below to preserve the POST body.
        $response = wp_remote_post(
            $apiUrl,
            [
                'body' => $postData,
                'timeout' => 30,
                'sslverify' => $this->settings->sslVerify(),
                'redirection' => 0,
            ]
        );

        // If the WordPress HTTP API fails entirely, try a native curl fallback
        // (same strategy used by DashboardAPI::activateLicense).
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: wp_remote_post failed (' . $response->get_error_message() . '), trying curl fallback');
            $response = $this->curlPost($apiUrl, $postData);
        }

        if (is_wp_error($response)) {
            // Network error — keep current status. Don't invalidate the license
            // just because the dashboard is temporarily unreachable.
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: All HTTP methods failed, keeping current status');
            return;
        }

        // Handle 301/302/307/308 redirects manually to preserve POST data.
        // WordPress converts POST to GET on redirect, which breaks REST endpoints.
        $statusCode = wp_remote_retrieve_response_code($response);
        if (in_array($statusCode, [301, 302, 307, 308], true)) {
            $headers = wp_remote_retrieve_headers($response);
            $location = $headers['location'] ?? '';
            if ($location) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: Following redirect to ' . $location);
                $response = wp_remote_post(
                    $location,
                    [
                        'body' => $postData,
                        'timeout' => 30,
                        'sslverify' => $this->settings->sslVerify(),
                        'redirection' => 0,
                    ]
                );
                if (is_wp_error($response)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: Redirected request failed, keeping current status');
                    return;
                }
            }
        }

        $rawBody = wp_remote_retrieve_body($response);
        $body = json_decode($rawBody, true);

        if (empty($body['success']) || empty($body['valid'])) {
            // Grace period: if the license was activated less than 1 hour ago,
            // don't mark it invalid on a failed validation. The activation
            // response is authoritative — a subsequent validation failure is
            // almost certainly a transient network/redirect issue, not a real
            // revocation. This prevents the "activates then immediately shows
            // inactive" loop.
            $lastActivation = (int) get_option('sseo_ai_client_last_activation', 0);
            if ($lastActivation && (time() - $lastActivation) < HOUR_IN_SECONDS) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: Validation failed but within 1h grace period after activation, keeping active status');
                return;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: Validation failed, marking invalid. Body: ' . substr($rawBody, 0, 200));
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
        if (!empty($body['monthly_auto_posts'])) {
            update_option('sseo_ai_client_monthly_auto_posts', $body['monthly_auto_posts']);
        }
        if (isset($body['monthly_geo_scans'])) {
            update_option('sseo_ai_client_monthly_geo_scans', (int) $body['monthly_geo_scans']);
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

            // Extract individual settings
            if (isset($body['white_label']['fynable_login_enabled'])) {
                update_option('sseo_ai_fynable_login_enabled', $body['white_label']['fynable_login_enabled']);
            }
            if (isset($body['white_label']['company_name'])) {
                update_option('sseo_ai_wl_company_name', $body['white_label']['company_name']);
            }
            if (isset($body['white_label']['company_logo'])) {
                update_option('sseo_ai_wl_company_logo', $body['white_label']['company_logo']);
            }
            if (isset($body['white_label']['primary_color'])) {
                update_option('sseo_ai_wl_primary_color', $body['white_label']['primary_color']);
            }
            if (isset($body['white_label']['secondary_color'])) {
                update_option('sseo_ai_wl_secondary_color', $body['white_label']['secondary_color']);
            }
        }

        // Store enabled features from SaaS dashboard (feature overrides)
        if (!empty($body['features']) && is_array($body['features'])) {
            update_option('sseo_ai_client_enabled_features', $body['features']);
        }
    }

    /**
     * Normalize a dashboard URL: force HTTPS and strip trailing slash.
     * Local copy to avoid a hard dependency on DashboardAPI at construction
     * time (the validator is created before the API client).
     */
    private function normalizeDashboardUrl(string $url): string
    {
        $url = str_replace('http://', 'https://', $url);
        $url = rtrim($url, '/');
        return $url;
    }

    /**
     * Native curl POST fallback when the WordPress HTTP API fails.
     * Mirrors DashboardAPI::curlPost so validation is as resilient as
     * activation.
     */
    private function curlPost(string $url, array $data): array|\WP_Error
    {
        if (!function_exists('curl_init')) {
            return new \WP_Error('curl_not_available', 'cURL extension not available');
        }

        $sslVerify = $this->settings->sslVerify();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Validator: cURL error: ' . $error);
            return new \WP_Error('curl_error', 'cURL error: ' . $error);
        }

        // Mimic the wp_remote_* response structure so the caller can use
        // wp_remote_retrieve_* helpers uniformly.
        return [
            'response' => ['code' => $httpCode],
            'body' => $response,
        ];
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
            'business' => ['content_analysis', 'meta_optimization', 'serp_tracking', 'keyword_research', 'content_decay', 'ai_generation', 'content_optimizer_calibration', 'advanced_backlinks', 'content_workflow', 'prompt_template_library'],
            'agency' => ['content_analysis', 'meta_optimization', 'serp_tracking', 'keyword_research', 'content_decay', 'ai_generation', 'multi_site', 'white_label', 'content_optimizer_calibration', 'advanced_backlinks', 'content_workflow', 'prompt_template_library'],
        ];

        return in_array($feature, $features[$tier] ?? []);
    }

    /**
     * Check if current tier is Business or higher (Business, Agency or Dev).
     */
    public function isBusinessPlus(): bool
    {
        return in_array($this->getLicenseTier(), ['business', 'agency', 'dev'], true);
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
     * Default monthly auto-scheduled post limits per tier
     */
    private const TIER_AUTO_POST_LIMITS = [
        'free'         => 0,
        'starter'      => 15,
        'trial'        => 10,
        'professional' => 35,
        'business'     => 150,
        'agency'       => PHP_INT_MAX,
        'dev'          => PHP_INT_MAX,
    ];

    /**
     * Default monthly GEO scan/audit limits per tier
     */
    private const TIER_GEO_SCAN_LIMITS = [
        'free'         => 0,
        'starter'      => 5,
        'trial'        => 5,
        'professional' => 35,
        'business'     => 90,
        'agency'       => PHP_INT_MAX,
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
     * Get monthly auto-scheduled posts limit
     */
    public function getMonthlyAutoPostLimit(): int
    {
        $tier = $this->getLicenseTier();

        // DEV tier has unlimited automation
        if ($tier === 'dev') {
            return PHP_INT_MAX;
        }

        $tierDefault = self::TIER_AUTO_POST_LIMITS[$tier] ?? 0;
        return (int)get_option('sseo_ai_client_monthly_auto_posts', $tierDefault);
    }

    /**
     * Get monthly GEO scan/audit limit
     */
    public function getMonthlyGeoScanLimit(): int
    {
        $tier = $this->getLicenseTier();

        if ($tier === 'dev') {
            return PHP_INT_MAX;
        }

        $tierDefault = self::TIER_GEO_SCAN_LIMITS[$tier] ?? 0;
        return (int)get_option('sseo_ai_client_monthly_geo_scans', $tierDefault);
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

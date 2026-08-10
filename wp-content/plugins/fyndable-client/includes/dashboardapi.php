<?php

namespace SSEOAIClient;

/**
 * Dashboard API Client
 * 
 * Handles all communication with the SaaS Dashboard REST API
 */
class DashboardAPI
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get SSL verification setting for API calls
     */
    private function getSslVerify(): bool
    {
        return $this->settings->sslVerify();
    }

    /**
     * Activate license on dashboard
     */
    public function activateLicense(string $licenseKey, string $dashboardUrl): array|\WP_Error
    {
        // Normalize the license key before sending it to the dashboard.
        // Keys are generated in uppercase; trimming avoids leading/trailing
        // spaces that cause "License key not found" errors.
        $licenseKey = strtoupper(trim($licenseKey));

        $siteUrl = get_site_url();
        $siteName = get_bloginfo('name');
        
        // Ensure HTTPS and normalize URL to prevent 301 redirects
        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Attempting license activation');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Dashboard URL: ' . $dashboardUrl);

        // Check if SaaS Dashboard plugin is active on this same WordPress installation
        // This avoids HTTP loopback issues on shared hosting
        if ($this->isSameSiteActivation($dashboardUrl)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Detected same-site installation, using direct PHP activation');
            return $this->activateLicenseDirectly($licenseKey, $siteUrl, $siteName);
        }

        $apiUrl = rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/activate';
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: API URL: ' . $apiUrl);

        // First test basic connectivity with a simple GET request
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Testing basic connectivity to ' . $dashboardUrl);
        $testResponse = wp_remote_get($dashboardUrl, [
            'timeout' => 15,
            'sslverify' => $this->getSslVerify(),
        ]);
        
        if (is_wp_error($testResponse)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: WordPress HTTP API failed: ' . $testResponse->get_error_message());
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Attempting native curl fallback...');
            
            // Try native curl as fallback
            $response = $this->curlPost($apiUrl, [
                'license_key' => $licenseKey,
                'site_url' => $siteUrl,
                'site_name' => $siteName,
            ]);
            
            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Native curl also failed: ' . $response->get_error_message());
                return $response;
            }
        } else {
            $testCode = wp_remote_retrieve_response_code($testResponse);
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Basic connectivity test passed, status: ' . $testCode);
            
            // Use WordPress HTTP API
            $response = wp_remote_post(
                $apiUrl,
                [
                    'body' => [
                        'license_key' => $licenseKey,
                        'site_url' => $siteUrl,
                        'site_name' => $siteName,
                    ],
                    'timeout' => 60,
                    'sslverify' => $this->getSslVerify(),
                    'redirection' => 0,
                ]
            );
            
            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: wp_remote_post failed, trying curl fallback...');
                $response = $this->curlPost($apiUrl, [
                    'license_key' => $licenseKey,
                    'site_url' => $siteUrl,
                    'site_name' => $siteName,
                ]);
            }
        }

        // Handle 301/302 redirect manually to preserve POST data
        if (!is_wp_error($response) && isset($response['headers']) && isset($response['response'])) {
            // This is a WP_HTTP_Response object from wp_remote_post
            $statusCode = wp_remote_retrieve_response_code($response);
            if (in_array($statusCode, [301, 302, 307, 308])) {
                $headers = wp_remote_retrieve_headers($response);
                $location = $headers['location'] ?? '';
                if ($location) {
                    if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Following redirect to: ' . $location);
                    $response = wp_remote_post(
                        $location,
                        [
                            'body' => [
                                'license_key' => $licenseKey,
                                'site_url' => $siteUrl,
                                'site_name' => $siteName,
                            ],
                            'timeout' => 60,
                            'sslverify' => $this->getSslVerify(),
                            'redirection' => 0,
                        ]
                    );
                }
            }
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $statusCode = wp_remote_retrieve_response_code($response);
            $rawBody = wp_remote_retrieve_body($response);
            $body = json_decode($rawBody, true);
        } elseif (!is_wp_error($response)) {
            // This is the direct result from curl (already decoded JSON)
            $body = $response;
            $statusCode = 200; // curl method already validates
        } else {
            return $response;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Response status: ' . $statusCode);

        if ($statusCode !== 200 || empty($body['success'])) {
            $message = $body['message'] ?? __('License activation failed.', 'ai-seo-client');
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Activation failed: ' . $message);
            if (!empty($body['error'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Error code: ' . $body['error']);
            }
            return new \WP_Error('activation_failed', $message);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Activation successful');
        return $body;
    }

    /**
     * Normalize dashboard URL to prevent 301 redirects.
     *
     * Made public so callers (handleLicenseActivation, onboarding, validator)
     * can store the same normalized URL that the API client uses internally,
     * avoiding http->https 301 redirects on subsequent API calls.
     */
    public function normalizeDashboardUrl(string $url): string
    {
        $url = trim($url);

        // Prepend a protocol when the user only types the domain.
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        } else {
            // Force HTTPS if an HTTP protocol is present.
            $url = str_replace('http://', 'https://', $url);
        }

        // Remove trailing slash
        $url = rtrim($url, '/');

        return $url;
    }

    /**
     * Native curl POST request fallback when WordPress HTTP API fails
     */
    private function curlPost(string $url, array $data): array|\WP_Error
    {
        if (!function_exists('curl_init')) {
            return new \WP_Error('curl_not_available', __('cURL extension not available', 'ai-seo-client'));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Using native curl for POST to: ' . $url);

        $ch = curl_init();
        
        $sslVerify = $this->getSslVerify();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
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
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: cURL error: ' . $error);
            return new \WP_Error('curl_error', 'cURL error: ' . $error);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: cURL response code: ' . $httpCode);

        $body = json_decode($response, true);
        
        if ($httpCode !== 200 || empty($body['success'])) {
            $message = $body['message'] ?? __('License activation failed.', 'ai-seo-client');
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: cURL activation failed: ' . $message);
            return new \WP_Error('activation_failed', $message);
        }

        return $body;
    }

    /**
     * Deactivate license on dashboard
     */
    public function deactivateLicense(string $licenseKey, string $tenantKey, string $dashboardUrl): bool
    {
        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/deactivate',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Validate license with dashboard
     */
    public function validateLicense(string $licenseKey, string $dashboardUrl): array|\WP_Error
    {
        $siteUrl = get_site_url();
        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);

        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/validate',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'site_url' => $siteUrl,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('connection_error', __('Could not connect to license server.', 'ai-seo-client'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            return new \WP_Error(
                $body['error'] ?? 'validation_failed',
                $body['message'] ?? __('License validation failed.', 'ai-seo-client')
            );
        }

        return $body['license'] ?? [];
    }

    /**
     * Report usage to dashboard
     */
    public function reportUsage(string $metric, int $count = 1, float $cost = 0): bool
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return false;
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/usage/report',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                    'metric' => $metric,
                    'count' => $count,
                    'cost' => $cost,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Report Google API usage to SaaS dashboard for cost tracking
     */
    public function reportGoogleUsage(string $service, int $calls = 1, float $cost = 0): bool
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return false;
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/google/report-usage',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                    'service' => $service,
                    'calls' => $calls,
                    'cost' => $cost,
                ],
                'timeout' => 10,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Check tenant status with dashboard
     */
    public function checkTenantStatus(string $tenantKey, string $licenseKey, string $dashboardUrl): array|\WP_Error
    {
        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/tenant/status',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('connection_error', __('Could not connect to license server.', 'ai-seo-client'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            return new \WP_Error(
                $body['error'] ?? 'status_failed',
                $body['message'] ?? __('Could not retrieve tenant status.', 'ai-seo-client')
            );
        }

        // Sync white-label settings from the SaaS dashboard
        if (!empty($body['white_label']) && is_array($body['white_label'])) {
            update_option('sseo_ai_white_label', $body['white_label']);
        }

        return $body;
    }

    /**
     * Generate AI content through dashboard proxy
     */
    public function aiGenerate(array $messages, string $model, int $maxTokens, float $temperature, string $useCase = 'content_generation'): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/ai/generate',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ],
                'body' => json_encode([
                    'messages' => $messages,
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'use_case' => $useCase,
                ]),
                'timeout' => 90,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('connection_error', __('Could not connect to AI service.', 'ai-seo-client'));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode === 429) {
            return new \WP_Error('usage_exceeded', $body['message'] ?? __('Usage limit exceeded', 'ai-seo-client'));
        }

        if ($statusCode !== 200 || empty($body['success'])) {
            return new \WP_Error(
                $body['error'] ?? 'ai_failed',
                $body['message'] ?? __('AI generation failed', 'ai-seo-client')
            );
        }

        return $body;
    }

    /**
     * Check usage status from dashboard
     */
    public function checkUsageStatus(): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $cacheKey = 'sseo_ai_usage_status_' . md5($licenseKey);
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_get(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/usage/check',
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('connection_error', __('Could not retrieve usage status.', 'ai-seo-client'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            return new \WP_Error(
                $body['error'] ?? 'status_failed',
                $body['message'] ?? __('Could not retrieve usage status.', 'ai-seo-client')
            );
        }

        set_transient($cacheKey, $body, 5 * MINUTE_IN_SECONDS);

        return $body;
    }

    /**
     * Generic API request to dashboard proxy endpoints (e.g. serp/search, serp/query)
     * Used by ContentBrief, ContentOptimizer, SerpCompetitor, KeywordExplorer
     *
     * @param string $endpoint The API endpoint path (e.g. 'serp/search')
     * @param array  $params   Request parameters
     * @return array|\WP_Error
     */
    public function request(string $endpoint, array $params = []): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);

        // Map client-side endpoint names to SaaS dashboard REST routes
        $endpointMap = [
            'serp/search'  => '/serp/query',
            'serp/query'   => '/serp/query',
        ];

        $route = $endpointMap[$endpoint] ?? '/' . ltrim($endpoint, '/');
        $apiUrl = rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1' . $route;

        $response = wp_remote_post($apiUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-License-Key' => $licenseKey,
                'X-Tenant-Key' => $tenantKey,
            ],
            'body' => json_encode($params),
            'timeout' => 60,
            'sslverify' => $this->getSslVerify(),
            'redirection' => 0,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('connection_error', __('Could not connect to API service.', 'ai-seo-client'));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode === 429) {
            return new \WP_Error('usage_exceeded', $body['message'] ?? __('Usage limit exceeded', 'ai-seo-client'));
        }

        if ($statusCode !== 200 || empty($body['success'])) {
            return new \WP_Error(
                $body['error'] ?? 'request_failed',
                $body['message'] ?? __('API request failed', 'ai-seo-client')
            );
        }

        return $body;
    }

    /**
     * Alias for request() — used by CompetitorResearch, InternationalSeo
     *
     * @param string $endpoint The API endpoint path (e.g. '/serp/competitor-keywords')
     * @param array  $params   Request parameters
     * @return array|\WP_Error
     */
    public function makeRequest(string $endpoint, array $params = []): array|\WP_Error
    {
        return $this->request(ltrim($endpoint, '/'), $params);
    }

    /**
     * Check if the dashboard URL points to the same WordPress installation
     * This helps avoid HTTP loopback issues on shared hosting
     */
    private function isSameSiteActivation(string $dashboardUrl): bool
    {
        // Compare URLs first (normalize both)
        $currentSite = rtrim(get_site_url(), '/');
        $dashboardSite = rtrim($dashboardUrl, '/');
        
        // Remove protocol for comparison
        $currentSiteNorm = preg_replace('#^https?://#', '', $currentSite);
        $dashboardSiteNorm = preg_replace('#^https?://#', '', $dashboardSite);
        
        $urlsMatch = strcasecmp($currentSiteNorm, $dashboardSiteNorm) === 0;
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Same-site check - Current: ' . $currentSiteNorm . ', Dashboard: ' . $dashboardSiteNorm . ', Match: ' . ($urlsMatch ? 'yes' : 'no'));
        
        if (!$urlsMatch) {
            return false;
        }
        
        // Check if SaaS Dashboard plugin classes are available
        // Try multiple class checks as they may be loaded at different times
        $saasAvailable = class_exists('\\SSEOAISaaS\\TenantRepository') || 
                         class_exists('\\SSEOAISaaS\\LicenseKeyGenerator') ||
                         class_exists('\\SSEOAISaaS\\LicenseAPI') ||
                         defined('SSEO_AI_SAAS_VERSION');
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: SaaS Dashboard available: ' . ($saasAvailable ? 'yes' : 'no'));
        
        // If URLs match but SaaS not loaded, try to load it
        if (!$saasAvailable && $urlsMatch) {
            // Check if the SaaS plugin file exists
            $saasPluginFile = WP_PLUGIN_DIR . '/fyndable-saas-dashboard/ai-seo-saas-dashboard.php';
            if (file_exists($saasPluginFile) && is_plugin_active('fyndable-saas-dashboard/ai-seo-saas-dashboard.php')) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: SaaS plugin exists and is active, attempting to load classes');
                // Include the necessary files
                $saasDir = WP_PLUGIN_DIR . '/fyndable-saas-dashboard/includes/';
                if (file_exists($saasDir . 'tenantrepository.php')) {
                    require_once $saasDir . 'tenantrepository.php';
                }
                if (file_exists($saasDir . 'licensekeygenerator.php')) {
                    require_once $saasDir . 'licensekeygenerator.php';
                }
                $saasAvailable = class_exists('\\SSEOAISaaS\\TenantRepository');
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: After manual load, SaaS available: ' . ($saasAvailable ? 'yes' : 'no'));
            }
        }
        
        return $urlsMatch && $saasAvailable;
    }

    /**
     * Activate license directly via PHP when on same WordPress installation
     * Bypasses HTTP completely to avoid loopback timeout issues
     */
    private function activateLicenseDirectly(string $licenseKey, string $siteUrl, string $siteName): array|\WP_Error
    {
        // Get the SaaS Dashboard classes
        if (!class_exists('\\SSEOAISaaS\\TenantRepository') || !class_exists('\\SSEOAISaaS\\LicenseKeyGenerator')) {
            return new \WP_Error('saas_not_available', __('SaaS Dashboard plugin is not properly loaded.', 'ai-seo-client'));
        }

        try {
            $tenants = new \SSEOAISaaS\TenantRepository();
            $licenseGenerator = new \SSEOAISaaS\LicenseKeyGenerator($tenants);

            // Validate the license key using LicenseKeyGenerator
            $license = $licenseGenerator->getLicense($licenseKey);
            
            if (!$license) {
                return new \WP_Error('invalid_license', __('Invalid license key.', 'ai-seo-client'));
            }

            if ($license['status'] === 'revoked') {
                return new \WP_Error('license_revoked', __('This license has been revoked.', 'ai-seo-client'));
            }

            if ($license['status'] === 'expired' || 
                (!empty($license['expires_at']) && strtotime($license['expires_at']) < time())) {
                return new \WP_Error('license_expired', __('This license has expired.', 'ai-seo-client'));
            }

            // Check if license is already used
            $domain = parse_url($siteUrl, PHP_URL_HOST);
            $existingTenant = $tenants->getTenantByLicense($licenseKey);

            if ($license['status'] === 'used' && $existingTenant) {
                // Check if it's the same site reactivating
                if ($existingTenant['domain'] !== $domain) {
                    return new \WP_Error('license_in_use', __('This license is already activated on another site.', 'ai-seo-client'));
                }
            }

            if ($existingTenant) {
                // Reactivate existing tenant
                $tenants->updateTenant($existingTenant['tenant_key'], [
                    'status' => 'active',
                    'domain' => $domain,
                    'name' => $siteName,
                ]);
                $tenantKey = $existingTenant['tenant_key'];
            } else {
                // Create new tenant
                $tenantResult = $tenants->createTenant([
                    'name' => $siteName,
                    'domain' => $domain,
                    'email' => get_option('admin_email'),
                    'license_key' => $licenseKey,
                    'tier' => $license['tier'],
                    'max_sites' => $license['max_sites'],
                    'rate_limit' => $license['rate_limit'],
                    'api_calls_limit' => $license['api_calls_limit'],
                    'expires_at' => $license['expires_at'],
                ]);

                if (is_wp_error($tenantResult) || empty($tenantResult['tenant_key'])) {
                    return new \WP_Error('tenant_creation_failed', __('Failed to create tenant.', 'ai-seo-client'));
                }

                $tenantKey = $tenantResult['tenant_key'];

                // Mark license as used
                $licenseGenerator->markLicenseUsed($licenseKey);
            }

            // Get tenant-specific white-label settings
            $whiteLabel = $this->getTenantWhiteLabelData($tenants, $tenantKey);

            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Direct activation successful, tenant_key: ' . $tenantKey);

            return [
                'success' => true,
                'tenant_key' => $tenantKey,
                'tier' => $license['tier'],
                'expires_at' => $license['expires_at'],
                'rate_limit' => $license['rate_limit'],
                'api_calls_limit' => $license['api_calls_limit'],
                'white_label' => $whiteLabel,
            ];

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Direct activation error: ' . $e->getMessage());
            return new \WP_Error('activation_error', $e->getMessage());
        }
    }

    /**
     * Get tenant-specific white-label data for same-site activation
     */
    private function getTenantWhiteLabelData(\SSEOAISaaS\TenantRepository $tenants, string $tenantKey): array
    {
        // Global SaaS white-label switch overrides tenant-level settings
        if (!get_option('sseo_ai_saas_wl_enabled', false)) {
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

        $enabled = $tenants->getTenantSetting($tenantKey, 'enable_whitelabel', false);
        $brand = $tenants->getTenantSetting($tenantKey, 'white_label_brand', null);

        if ($enabled && is_array($brand) && !empty($brand['company_name'])) {
            return $brand;
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
     * -------------------------------------------------------------------------
     * Support ticket API
     * -------------------------------------------------------------------------
     */

    /**
     * Sync white-label from SaaS dashboard on every admin page load.
     */
    public function syncWhiteLabel(): void
    {
        if (get_transient('sseo_ai_white_label_sync')) {
            return;
        }

        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return;
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/tenant/status',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 15,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['success']) && isset($body['white_label']) && is_array($body['white_label'])) {
            update_option('sseo_ai_white_label', $body['white_label']);
        }

        set_transient('sseo_ai_white_label_sync', true, MINUTE_IN_SECONDS);
    }

    /**
     * Get support tickets for the current tenant.
     */
    public function getSupportTickets(): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $cacheKey = 'sseo_ai_support_tickets_' . md5($licenseKey);
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_get(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/support/tickets',
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        $result = $this->handleSupportResponse($response, __('Could not retrieve support tickets.', 'ai-seo-client'));

        if (!is_wp_error($result)) {
            set_transient($cacheKey, $result, 2 * MINUTE_IN_SECONDS);
        }

        return $result;
    }

    /**
     * Create a new support ticket.
     */
    public function createSupportTicket(string $subject, string $message, string $priority, array $screenshots = []): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/support/tickets',
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'subject' => $subject,
                    'message' => $message,
                    'priority' => $priority,
                    'screenshots' => $screenshots,
                ]),
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        $result = $this->handleSupportResponse($response, __('Could not create support ticket.', 'ai-seo-client'));

        if (!is_wp_error($result)) {
            $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
            delete_transient('sseo_ai_support_tickets_' . md5($licenseKey));
        }

        return $result;
    }

    /**
     * Get a single support ticket with replies.
     */
    public function getSupportTicket(int $ticketId): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_get(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/support/ticket/' . $ticketId,
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        return $this->handleSupportResponse($response, __('Could not retrieve support ticket.', 'ai-seo-client'));
    }

    /**
     * Add a reply to a support ticket.
     */
    public function addSupportReply(int $ticketId, string $message, array $screenshots = []): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/support/reply',
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'ticket_id' => $ticketId,
                    'message' => $message,
                    'screenshots' => $screenshots,
                ]),
                'timeout' => 30,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        return $this->handleSupportResponse($response, __('Could not send reply.', 'ai-seo-client'));
    }

    /**
     * Upload a screenshot to the SaaS dashboard.
     */
    public function uploadSupportScreenshot(array $file): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

        if (empty($file['tmp_name'])) {
            return new \WP_Error('no_file', __('No screenshot uploaded.', 'ai-seo-client'));
        }

        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        $url = rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/support/upload';

        $boundary = wp_generate_password(24, false);
        $body = $this->buildMultipartBody($file, $boundary);

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $body,
                'timeout' => 60,
                'sslverify' => $this->getSslVerify(),
                'redirection' => 0,
            ]
        );

        return $this->handleSupportResponse($response, __('Could not upload screenshot.', 'ai-seo-client'));
    }

    /**
     * Shared response handler for support ticket endpoints.
     */
    private function handleSupportResponse($response, string $errorMessage): array|\WP_Error
    {
        if (is_wp_error($response)) {
            return new \WP_Error('connection_error', $errorMessage);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 && $statusCode !== 201) {
            $message = $body['message'] ?? $errorMessage;
            return new \WP_Error(
                $body['error'] ?? 'support_request_failed',
                $message
            );
        }

        if (empty($body['success'])) {
            return new \WP_Error(
                $body['error'] ?? 'support_request_failed',
                $body['message'] ?? $errorMessage
            );
        }

        return $body;
    }

    /**
     * Build multipart body for a single file upload.
     */
    private function buildMultipartBody(array $file, string $boundary): string
    {
        $fileName = sanitize_file_name($file['name'] ?? 'screenshot.png');
        $fileType = sanitize_text_field($file['type'] ?? 'image/png');
        $fileContent = file_get_contents($file['tmp_name']);

        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"screenshot\"; filename=\"{$fileName}\"\r\n";
        $body .= "Content-Type: {$fileType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return $body;
    }
}

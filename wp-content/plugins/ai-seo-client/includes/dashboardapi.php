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
     * Activate license on dashboard
     */
    public function activateLicense(string $licenseKey, string $dashboardUrl): array|\WP_Error
    {
        $siteUrl = get_site_url();
        $siteName = get_bloginfo('name');
        
        // Ensure HTTPS and normalize URL to prevent 301 redirects
        $dashboardUrl = $this->normalizeDashboardUrl($dashboardUrl);
        
        $apiUrl = rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/activate';
        
        error_log('SSEO AI: Attempting license activation');
        error_log('SSEO AI: API URL: ' . $apiUrl);
        error_log('SSEO AI: License Key: ' . substr($licenseKey, 0, 15) . '...');

        // First test basic connectivity with a simple GET request
        error_log('SSEO AI: Testing basic connectivity to ' . $dashboardUrl);
        $testResponse = wp_remote_get($dashboardUrl, [
            'timeout' => 15,
            'sslverify' => false,
        ]);
        
        if (is_wp_error($testResponse)) {
            error_log('SSEO AI: WordPress HTTP API failed: ' . $testResponse->get_error_message());
            error_log('SSEO AI: Attempting native curl fallback...');
            
            // Try native curl as fallback
            $response = $this->curlPost($apiUrl, [
                'license_key' => $licenseKey,
                'site_url' => $siteUrl,
                'site_name' => $siteName,
            ]);
            
            if (is_wp_error($response)) {
                error_log('SSEO AI: Native curl also failed: ' . $response->get_error_message());
                return $response;
            }
        } else {
            $testCode = wp_remote_retrieve_response_code($testResponse);
            error_log('SSEO AI: Basic connectivity test passed, status: ' . $testCode);
            
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
                    'sslverify' => false,
                    'redirection' => 0,
                ]
            );
            
            if (is_wp_error($response)) {
                error_log('SSEO AI: wp_remote_post failed, trying curl fallback...');
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
                    error_log('SSEO AI: Following redirect to: ' . $location);
                    $response = wp_remote_post(
                        $location,
                        [
                            'body' => [
                                'license_key' => $licenseKey,
                                'site_url' => $siteUrl,
                                'site_name' => $siteName,
                            ],
                            'timeout' => 60,
                            'sslverify' => false,
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
        
        error_log('SSEO AI: Response status: ' . $statusCode);
        error_log('SSEO AI: Response body: ' . json_encode($body));

        if ($statusCode !== 200 || empty($body['success'])) {
            $message = $body['message'] ?? __('License activation failed.', 'ai-seo-client');
            error_log('SSEO AI: Activation failed: ' . $message);
            if (!empty($body['error'])) {
                error_log('SSEO AI: Error code: ' . $body['error']);
            }
            return new \WP_Error('activation_failed', $message);
        }

        error_log('SSEO AI: Activation successful');
        return $body;
    }

    /**
     * Normalize dashboard URL to prevent 301 redirects
     */
    private function normalizeDashboardUrl(string $url): string
    {
        // Force HTTPS
        $url = str_replace('http://', 'https://', $url);
        
        // Remove www. if present (most modern sites redirect www to non-www)
        $url = str_replace('https://www.', 'https://', $url);
        
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

        error_log('SSEO AI: Using native curl for POST to: ' . $url);

        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
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
            error_log('SSEO AI: cURL error: ' . $error);
            return new \WP_Error('curl_error', 'cURL error: ' . $error);
        }

        error_log('SSEO AI: cURL response code: ' . $httpCode);
        error_log('SSEO AI: cURL response body: ' . substr($response, 0, 500));

        $body = json_decode($response, true);
        
        if ($httpCode !== 200 || empty($body['success'])) {
            $message = $body['message'] ?? __('License activation failed.', 'ai-seo-client');
            error_log('SSEO AI: cURL activation failed: ' . $message);
            return new \WP_Error('activation_failed', $message);
        }

        return $body;
    }

    /**
     * Deactivate license on dashboard
     */
    public function deactivateLicense(string $licenseKey, string $tenantKey, string $dashboardUrl): bool
    {
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/deactivate',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 5,
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

        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/validate',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'site_url' => $siteUrl,
                ],
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 5,
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
                'sslverify' => true,
                'redirection' => 5,
            ]
        );

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Check tenant status with dashboard
     */
    public function checkTenantStatus(string $tenantKey, string $licenseKey, string $dashboardUrl): array|\WP_Error
    {
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/tenant/status',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 5,
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

        return $body;
    }

    /**
     * Generate AI content through dashboard proxy
     */
    public function aiGenerate(array $messages, string $model, int $maxTokens, float $temperature): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', __('Dashboard not configured', 'ai-seo-client'));
        }

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
                ]),
                'timeout' => 60,
                'sslverify' => true,
                'redirection' => 5,
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

        $response = wp_remote_get(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/usage/check',
            [
                'headers' => [
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ],
                'timeout' => 30,
                'sslverify' => true,
                'redirection' => 5,
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

        return $body;
    }
}

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

        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/activate',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'site_url' => $siteUrl,
                    'site_name' => $siteName,
                ],
                'timeout' => 30,
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            return new \WP_Error(
                'connection_error',
                __('Could not connect to license server. Please try again.', 'ai-seo-client')
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || empty($body['success'])) {
            $message = $body['message'] ?? __('License activation failed.', 'ai-seo-client');
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

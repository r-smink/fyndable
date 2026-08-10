<?php

namespace SSEOAIClient;

/**
 * Update Checker
 *
 * Hooks into WordPress's plugin update system to check for updates
 * from the SaaS Dashboard's UpdateServer endpoint.
 *
 * Uses `pre_set_site_transient_update_plugins` for update checks
 * and `plugins_api` for the "View details" popup.
 */
class UpdateChecker
{
    private Settings $settings;
    private const CACHE_KEY = 'sseo_ai_update_check';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdates']);
        add_filter('plugins_api', [$this, 'filterPluginInfo'], 10, 3);
        add_filter('plugin_row_meta', [$this, 'addUpdateMeta'], 10, 2);
    }

    /**
     * Check for plugin updates via SaaS dashboard.
     */
    public function checkForUpdates($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        // Only check if license is active
        $licenseStatus = get_option('sseo_ai_client_license_status', 'inactive');
        if ($licenseStatus === 'inactive') {
            return $transient;
        }

        // Use cached result to avoid API call on every page load
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            if (!empty($cached['has_update']) && !empty($cached['download_url'])) {
                $transient = $this->addUpdateToTransient($transient, $cached);
            }
            return $transient;
        }

        // Make API call to check for updates
        $result = $this->fetchUpdateInfo();
        if (is_wp_error($result)) {
            // Cache negative result for shorter time on error
            set_transient(self::CACHE_KEY, ['has_update' => false], HOUR_IN_SECONDS);
            return $transient;
        }

        set_transient(self::CACHE_KEY, $result, self::CACHE_TTL);

        if (!empty($result['has_update']) && !empty($result['download_url'])) {
            $transient = $this->addUpdateToTransient($transient, $result);
        }

        return $transient;
    }

    /**
     * Add update data to the transient.
     */
    private function addUpdateToTransient($transient, array $result): object
    {
        $pluginFile = plugin_basename(SSEO_AI_CLIENT_PLUGIN_FILE);

        $obj = new \stdClass();
        $obj->slug = 'fyndable-client';
        $obj->plugin = $pluginFile;
        $obj->new_version = $result['latest_version'] ?? SSEO_AI_CLIENT_VERSION;
        $obj->url = get_option('sseo_ai_client_dashboard_url', '');
        $obj->package = $result['download_url'] ?? '';
        $obj->tested = get_bloginfo('version');
        $obj->requires = $result['min_wp_version'] ?? '6.0';

        $transient->response[$pluginFile] = $obj;

        return $transient;
    }

    /**
     * Fetch update info from SaaS dashboard.
     */
    private function fetchUpdateInfo(): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('not_configured', 'Dashboard not configured');
        }

        // Normalize URL and disable auto-redirect to prevent POST->GET conversion
        $dashboardUrl = str_replace('http://', 'https://', rtrim($dashboardUrl, '/'));
        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/updates/check',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                    'current_version' => SSEO_AI_CLIENT_VERSION,
                ],
                'timeout' => 15,
                'sslverify' => $this->settings->sslVerify(),
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['success'])) {
            return new \WP_Error('check_failed', $body['message'] ?? 'Update check failed');
        }

        return $body;
    }

    /**
     * Filter the plugin info popup (View details).
     */
    public function filterPluginInfo($result, $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== 'fyndable-client') {
            return $result;
        }

        $cached = get_transient(self::CACHE_KEY);
        if (!$cached || !is_array($cached)) {
            $cached = $this->fetchUpdateInfo();
            if (is_wp_error($cached)) {
                return $result;
            }
        }

        $pluginInfo = new \stdClass();
        $pluginInfo->name = 'Fyndable';
        $pluginInfo->slug = 'fyndable-client';
        $pluginInfo->version = $cached['latest_version'] ?? SSEO_AI_CLIENT_VERSION;
        $pluginInfo->author = '<a href="https://fyndable.com">Fyndable</a>';
        $pluginInfo->homepage = 'https://fyndable.com';
        $pluginInfo->requires = $cached['min_wp_version'] ?? '6.0';
        $pluginInfo->tested = get_bloginfo('version');
        $pluginInfo->last_updated = $cached['last_checked'] ?? current_time('mysql');
        $pluginInfo->downloaded = 0;
        $pluginInfo->active_installs = 0;
        $pluginInfo->sections = [
            'description' => '<p>Advanced AI-powered SEO plugin by Fyndable with comprehensive optimization features.</p>',
            'changelog' => $this->formatChangelog($cached['changelog'] ?? ''),
        ];

        return $pluginInfo;
    }

    /**
     * Format changelog as HTML.
     */
    private function formatChangelog(string $changelog): string
    {
        if (empty($changelog)) {
            return '<p>No changelog available.</p>';
        }

        $html = '<div style="font-family: monospace; white-space: pre-wrap;">' . esc_html($changelog) . '</div>';

        return $html;
    }

    /**
     * Add "Check for updates" link in the plugin row.
     */
    public function addUpdateMeta(array $links, string $file): array
    {
        if ($file !== plugin_basename(SSEO_AI_CLIENT_PLUGIN_FILE)) {
            return $links;
        }

        $links[] = '<a href="' . esc_url(admin_url('admin.php?page=ai-seo-settings')) . '">' .
            __('Check for Updates', 'ai-seo-client') . '</a>';

        return $links;
    }

    /**
     * Force a manual update check (clears cache).
     */
    public function forceCheck(): void
    {
        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
    }
}

<?php

namespace SSEOAISaaS;

/**
 * Update Server
 *
 * Serves plugin update information and package URLs to client plugins.
 * Provides REST endpoints compatible with WordPress's update system.
 *
 * The client plugin hooks into `pre_set_site_transient_update_plugins`
 * and calls our endpoint to check for updates.
 */
class UpdateServer
{
    private TenantRepository $tenants;
    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function register(): void
    {
        // Check for updates (called by client plugin)
        register_rest_route($this->namespace, '/updates/check', [
            'methods'  => 'POST',
            'callback' => [$this, 'checkForUpdates'],
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
                'current_version' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Serve plugin info (for the "View version x.x.x details" popup)
        register_rest_route($this->namespace, '/updates/info', [
            'methods'  => 'GET',
            'callback' => [$this, 'getPluginInfo'],
            'permission_callback' => '__return_true',
        ]);

        // Admin settings for configuring update versions
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Register settings for update configuration.
     */
    public function registerSettings(): void
    {
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_latest_version', [
            'type' => 'string',
            'default' => SSEO_AI_SAAS_VERSION,
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_download_url', [
            'type' => 'string',
            'default' => '',
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_update_changelog', [
            'type' => 'string',
            'default' => '',
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_min_wp_version', [
            'type' => 'string',
            'default' => '6.0',
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_beta_enabled', [
            'type' => 'string',
            'default' => '0',
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_beta_version', [
            'type' => 'string',
            'default' => '',
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_beta_download_url', [
            'type' => 'string',
            'default' => '',
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_beta_changelog', [
            'type' => 'string',
            'default' => '',
        ]);
    }

    /**
     * Check for updates — called by client plugin.
     */
    public function checkForUpdates(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = $request->get_param('license_key');
        $tenantKey = $request->get_param('tenant_key');
        $currentVersion = $request->get_param('current_version');
        $wantBeta = (bool) $request->get_param('beta');

        // Validate tenant
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid license or tenant.',
            ], 403);
        }

        // Check if tenant is expired
        $expiresAt = $tenant['expires_at'] ?? '';
        if (!empty($expiresAt) && strtotime($expiresAt) < time()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'License expired. Updates require an active license.',
            ], 403);
        }

        $latestVersion = get_option('sseo_ai_saas_latest_version', SSEO_AI_SAAS_VERSION);
        $downloadUrl = get_option('sseo_ai_saas_download_url', '');
        $changelog = get_option('sseo_ai_saas_update_changelog', '');
        $minWpVersion = get_option('sseo_ai_saas_min_wp_version', '6.0');

        // Beta channel
        if ($wantBeta && get_option('sseo_ai_saas_beta_enabled', '0') === '1') {
            $betaVersion = get_option('sseo_ai_saas_beta_version', '');
            $betaUrl = get_option('sseo_ai_saas_beta_download_url', '');
            if (!empty($betaVersion) && !empty($betaUrl) && version_compare($betaVersion, $latestVersion, '>')) {
                $latestVersion = $betaVersion;
                $downloadUrl = $betaUrl;
                $changelog = get_option('sseo_ai_saas_beta_changelog', '');
            }
        }

        $hasUpdate = version_compare($latestVersion, $currentVersion, '>');

        return new \WP_REST_Response([
            'success'       => true,
            'has_update'    => $hasUpdate,
            'latest_version' => $latestVersion,
            'download_url'  => $downloadUrl,
            'changelog'     => $changelog,
            'min_wp_version' => $minWpVersion,
            'last_checked'  => current_time('mysql'),
        ], 200);
    }

    /**
     * Get plugin info for the "View details" popup in WP admin.
     */
    public function getPluginInfo(\WP_REST_Request $request): \WP_REST_Response
    {
        $version = get_option('sseo_ai_saas_latest_version', SSEO_AI_SAAS_VERSION);
        $changelog = get_option('sseo_ai_saas_update_changelog', '');
        $minWpVersion = get_option('sseo_ai_saas_min_wp_version', '6.0');

        return new \WP_REST_Response([
            'name'          => 'Fyndable',
            'slug'          => 'fyndable-client',
            'version'       => $version,
            'author'        => 'Fyndable',
            'homepage'      => home_url(),
            'requires'      => $minWpVersion,
            'tested'        => get_bloginfo('version'),
            'last_updated'  => current_time('mysql'),
            'sections'      => [
                'description' => 'Advanced AI-powered SEO plugin by Fyndable.',
                'changelog'   => $changelog,
            ],
            'banners' => [
                'low'  => '',
                'high' => '',
            ],
        ], 200);
    }
}

<?php

namespace SSEOAIClient;

/**
 * Google OAuth2 Handler (Unified — SaaS Dashboard Proxy)
 * 
 * Manages OAuth2 authentication flow for multiple Google services:
 * - Google Search Console (webmasters.readonly)
 * - Google Analytics 4 (analytics.readonly)
 * - Google Ads (adwords)
 * 
 * OAuth credentials (client ID, secret, dev token) are stored on the
 * Fyndable.ai SaaS Dashboard and never exposed to client sites.
 * The SaaS dashboard acts as a proxy for the token exchange.
 */
class GscOAuth
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->dashboardAPI = new DashboardAPI($settings);
    }

    /**
     * Register REST routes for OAuth callback
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register REST API endpoints
     */
    public function registerRestRoutes(): void
    {
        // Exchange authorization code for tokens (GIS postmessage flow)
        register_rest_route('sseo-ai/v1', '/google-exchange', [
            'methods' => 'POST',
            'callback' => [$this, 'restExchangeCode'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Disconnect endpoint
        register_rest_route('sseo-ai/v1', '/gsc-disconnect', [
            'methods' => 'POST',
            'callback' => [$this, 'restDisconnect'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Check connection status (legacy GSC route)
        register_rest_route('sseo-ai/v1', '/gsc-status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Unified Google routes
        register_rest_route('sseo-ai/v1', '/google-status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Unified Google disconnect
        register_rest_route('sseo-ai/v1', '/google-disconnect', [
            'methods' => 'POST',
            'callback' => [$this, 'restDisconnect'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Legacy callback (kept for backward compatibility with existing connected sites)
        register_rest_route('sseo-ai/v1', '/gsc-callback', [
            'methods' => 'GET',
            'callback' => [$this, 'restHandleCallback'],
            'permission_callback' => '__return_true',
        ]);

        // Store tokens received from SaaS dashboard (postMessage flow)
        register_rest_route('sseo-ai/v1', '/google/store-tokens', [
            'methods' => 'POST',
            'callback' => [$this, 'restStoreTokens'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * REST: Exchange authorization code for tokens (GIS postmessage flow)
     * Receives the code from the GIS JavaScript callback in the browser.
     */
    public function restExchangeCode(\WP_REST_Request $request): \WP_REST_Response|array
    {
        $code = $request->get_param('code');

        if (!$code) {
            return new \WP_REST_Response(['error' => 'Authorization code missing'], 400);
        }

        $result = $this->exchangeCode(sanitize_text_field($code));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        return ['success' => true, 'message' => 'Google account connected successfully.'];
    }

    /**
     * REST: Store tokens received from SaaS dashboard (postMessage flow)
     */
    public function restStoreTokens(\WP_REST_Request $request): \WP_REST_Response|array
    {
        $tokens = $request->get_param('tokens');
        if (!is_array($tokens) || empty($tokens['access_token'])) {
            return new \WP_REST_Response(['error' => 'Invalid token data'], 400);
        }

        update_option('aiseoclient_gsc_tokens', $tokens, false);

        return ['success' => true, 'message' => 'Google account connected successfully.'];
    }

    /**
     * REST: Handle legacy OAuth callback (redirect-based, kept for backward compat)
     */
    public function restHandleCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');

        $storedState = get_transient('aiseo_gsc_oauth_state');
        if (empty($state) || empty($storedState) || !hash_equals($storedState, $state)) {
            return new \WP_REST_Response(['error' => 'Invalid state parameter'], 403);
        }
        delete_transient('aiseo_gsc_oauth_state');

        if ($error) {
            return new \WP_REST_Response(['error' => sanitize_text_field($error)], 400);
        }

        if (!$code) {
            return new \WP_REST_Response(['error' => 'Authorization code missing'], 400);
        }

        $result = $this->exchangeCode(sanitize_text_field($code));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        $redirectUrl = admin_url('admin.php?page=ai-seo-integrations&gsc_connected=1');
        wp_redirect($redirectUrl);
        exit;
    }

    /**
     * REST: Disconnect GSC
     */
    public function restDisconnect(\WP_REST_Request $request): array
    {
        delete_option('aiseoclient_gsc_tokens');
        delete_option('sseo_ai_gsc_site_url');
        
        return ['success' => true, 'message' => __('Google services disconnected.', 'ai-seo-client')];
    }

    /**
     * REST: Get connection status
     */
    public function restGetStatus(\WP_REST_Request $request): array
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $siteUrl = $this->settings->get('gsc_site_url', home_url());
        $ga4PropertyId = get_option('sseo_ai_ga4_property_id', '');
        $googleAdsCustomerId = get_option('sseo_ai_google_ads_customer_id', '');
        $scopes = $tokens['scope'] ?? '';
        $clientId = $this->getClientId();
        
        return [
            'connected' => !empty($tokens['access_token']),
            'has_credentials' => !empty($clientId),
            'client_id' => $clientId,
            'site_url' => $siteUrl,
            'auth_url' => '',
            'services' => [
                'gsc' => strpos($scopes, 'webmasters') !== false || !empty($tokens['access_token']),
                'ga4' => strpos($scopes, 'analytics') !== false || !empty($tokens['access_token']),
                'ads' => strpos($scopes, 'adwords') !== false || !empty($tokens['access_token']),
            ],
            'ga4_property_id' => $ga4PropertyId,
            'google_ads_customer_id' => $googleAdsCustomerId,
        ];
    }

    /**
     * Get the Google OAuth client ID from the SaaS dashboard.
     * Cached locally for 1 hour to avoid repeated API calls.
     */
    public function getClientId(): string
    {
        $cached = get_transient('sseo_google_client_id');
        if ($cached) return $cached;

        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return '';
        }

        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/google/oauth-config',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 15,
                'sslverify' => $this->settings->sslVerify(),
            ]
        );

        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $clientId = $body['client_id'] ?? '';

        if ($clientId) {
            set_transient('sseo_google_client_id', $clientId, HOUR_IN_SECONDS);
        }

        return $clientId;
    }

    /**
     * Get the Google Ads developer token from the SaaS dashboard.
     * Cached locally for 1 hour.
     */
    public function getAdsDevToken(): string
    {
        $cached = get_transient('sseo_google_ads_dev_token');
        if ($cached) return $cached;

        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return '';
        }

        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/google/ads-dev-token',
            [
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ],
                'timeout' => 15,
                'sslverify' => $this->settings->sslVerify(),
            ]
        );

        if (is_wp_error($response)) return '';
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $devToken = $body['dev_token'] ?? '';

        if ($devToken) {
            set_transient('sseo_google_ads_dev_token', $devToken, HOUR_IN_SECONDS);
        }

        return $devToken;
    }

    /**
     * Exchange an authorization code for tokens via the SaaS dashboard proxy.
     * The client secret never touches the client site.
     */
    public function exchangeCode(string $code): array|\WP_Error
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return new \WP_Error('gsc_config', __('SaaS dashboard not configured.', 'ai-seo-client'));
        }

        $resp = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/google/exchange',
            [
                'timeout' => 15,
                'sslverify' => $this->settings->sslVerify(),
                'body' => [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                    'code' => $code,
                ],
            ]
        );

        if (is_wp_error($resp)) return $resp;
        $codeResp = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($codeResp !== 200 || empty($body['success'])) {
            $msg = $body['message'] ?? __('Token exchange failed', 'ai-seo-client');
            return new \WP_Error('gsc_token', $msg, $resp);
        }

        $tokens = $body['tokens'] ?? null;
        if (!is_array($tokens)) {
            return new \WP_Error('gsc_token', __('Invalid token response', 'ai-seo-client'));
        }

        update_option('aiseoclient_gsc_tokens', $tokens, false);
        return $tokens;
    }

    /**
     * Legacy alias for backward compatibility.
     */
    public function handleCallback(string $code): array|\WP_Error
    {
        return $this->exchangeCode($code);
    }

    public function refresh(): array|\WP_Error
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $refresh = $tokens['refresh_token'] ?? '';
        if (!$refresh) return new \WP_Error('gsc_refresh', __('Missing refresh token', 'ai-seo-client'));
        $clientId = $this->getClientId();
        if (!$clientId) return new \WP_Error('gsc_refresh', __('Cannot get Google client ID from SaaS dashboard', 'ai-seo-client'));
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body' => [
                'refresh_token' => $refresh,
                'client_id' => $clientId,
                'grant_type' => 'refresh_token',
            ],
        ]);
        if (is_wp_error($resp)) return $resp;
        if (wp_remote_retrieve_response_code($resp) !== 200) return new \WP_Error('gsc_refresh', __('Refresh failed', 'ai-seo-client'), $resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) return new \WP_Error('gsc_refresh', __('Invalid refresh response', 'ai-seo-client'));
        $body['refresh_token'] = $refresh;
        update_option('aiseoclient_gsc_tokens', $body, false);
        return $body;
    }

    public function getAccessToken(): string
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        if (!empty($tokens['access_token']) && isset($tokens['expires_in'])) {
            return $tokens['access_token'];
        }
        $ref = $this->refresh();
        if (is_wp_error($ref)) {
            return '';
        }
        return $ref['access_token'] ?? '';
    }
}

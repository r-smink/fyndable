<?php

namespace SSEOAIClient;

/**
 * Google Search Console OAuth2 Handler
 * 
 * Manages OAuth2 authentication flow for Google Search Console API access.
 */
class GscOAuth
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
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
        // OAuth callback endpoint
        register_rest_route('sseo-ai/v1', '/gsc-callback', [
            'methods' => 'GET',
            'callback' => [$this, 'restHandleCallback'],
            'permission_callback' => '__return_true', // OAuth callback is public
        ]);

        // Disconnect endpoint
        register_rest_route('sseo-ai/v1', '/gsc-disconnect', [
            'methods' => 'POST',
            'callback' => [$this, 'restDisconnect'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Check connection status
        register_rest_route('sseo-ai/v1', '/gsc-status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * REST: Handle OAuth callback
     */
    public function restHandleCallback(\WP_REST_Request $request): \WP_REST_Response
    {
        $code = $request->get_param('code');
        $state = $request->get_param('state');
        $error = $request->get_param('error');

        // Verify state token (stored in transient during auth URL generation)
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

        $result = $this->handleCallback(sanitize_text_field($code));

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 400);
        }

        // Redirect back to settings page with success
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
        
        return ['success' => true, 'message' => __('Google Search Console disconnected.', 'ai-seo-client')];
    }

    /**
     * REST: Get connection status
     */
    public function restGetStatus(\WP_REST_Request $request): array
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $clientId = $this->settings->get('gsc_client_id', '');
        $clientSecret = $this->settings->get('gsc_client_secret', '');
        $siteUrl = $this->settings->get('gsc_site_url', home_url());
        
        return [
            'connected' => !empty($tokens['access_token']),
            'has_credentials' => !empty($clientId) && !empty($clientSecret),
            'site_url' => $siteUrl,
            'auth_url' => $this->authUrl(),
        ];
    }

    public function authUrl(): string
    {
        $clientId = $this->settings->get('gsc_client_id', '');
        $redirect = $this->callbackUrl();
        $scope = 'https://www.googleapis.com/auth/webmasters.readonly';
        $state = wp_generate_password(40, false);
        set_transient('aiseo_gsc_oauth_state', $state, 10 * MINUTE_IN_SECONDS);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'state' => $state,
            'prompt' => 'consent',
        ]);
    }

    public function callbackUrl(): string
    {
        return rest_url('sseo-ai/v1/gsc-callback');
    }

    public function handleCallback(string $code): array|\WP_Error
    {
        $clientId = $this->settings->get('gsc_client_id', '');
        $clientSecret = $this->settings->get('gsc_client_secret', '');
        if (!$clientId || !$clientSecret) {
            return new \WP_Error('gsc_config', __('GSC client id/secret missing', 'ai-seo-assistant'));
        }
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body' => [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $this->callbackUrl(),
                'grant_type' => 'authorization_code',
            ],
        ]);
        if (is_wp_error($resp)) return $resp;
        $codeResp = wp_remote_retrieve_response_code($resp);
        if ($codeResp !== 200) return new \WP_Error('gsc_token', __('Token exchange failed', 'ai-seo-assistant'), $resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) return new \WP_Error('gsc_token', __('Invalid token response', 'ai-seo-assistant'));
        update_option('aiseoclient_gsc_tokens', $body, false);
        return $body;
    }

    public function refresh(): array|\WP_Error
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $refresh = $tokens['refresh_token'] ?? '';
        if (!$refresh) return new \WP_Error('gsc_refresh', __('Missing refresh token', 'ai-seo-assistant'));
        $clientId = $this->settings->get('gsc_client_id', '');
        $clientSecret = $this->settings->get('gsc_client_secret', '');
        $resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body' => [
                'refresh_token' => $refresh,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ],
        ]);
        if (is_wp_error($resp)) return $resp;
        if (wp_remote_retrieve_response_code($resp) !== 200) return new \WP_Error('gsc_refresh', __('Refresh failed', 'ai-seo-assistant'), $resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) return new \WP_Error('gsc_refresh', __('Invalid refresh response', 'ai-seo-assistant'));
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

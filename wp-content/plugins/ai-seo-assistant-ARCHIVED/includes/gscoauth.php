<?php

namespace AISEOAssistant;

class GscOAuth
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function authUrl(): string
    {
        $clientId = $this->settings->get('gsc_client_id', '');
        $redirect = $this->callbackUrl();
        $scope = 'https://www.googleapis.com/auth/webmasters.readonly';
        $state = wp_create_nonce('aiseo_gsc');
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
        return rest_url('aiseoassistant/v1/gsc-callback');
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
        update_option('aiseoassistant_gsc_tokens', $body, false);
        return $body;
    }

    public function refresh(): array|\WP_Error
    {
        $tokens = get_option('aiseoassistant_gsc_tokens', []);
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
        update_option('aiseoassistant_gsc_tokens', $body, false);
        return $body;
    }

    public function getAccessToken(): string
    {
        $tokens = get_option('aiseoassistant_gsc_tokens', []);
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

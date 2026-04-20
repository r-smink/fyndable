<?php

namespace SSEOAIClient;

/**
 * Google Search Console API Client
 * 
 * Uses OAuth2 authentication to fetch Search Analytics data.
 * Compatible with GscOAuth for token management.
 */
class GscClient
{
    private Settings $settings;
    private GscOAuth $oauth;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->oauth = new GscOAuth($settings);
    }

    /**
     * Check if GSC is connected (has valid tokens)
     */
    public function isConnected(): bool
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        return !empty($tokens['access_token']) || !empty($tokens['refresh_token']);
    }

    /**
     * Get the site URL for GSC queries (uses current site or configured site)
     */
    private function getSiteUrl(): string
    {
        // Try to get configured site first
        $configuredSite = $this->settings->get('gsc_site_url', '');
        if ($configuredSite) {
            return $configuredSite;
        }
        // Fallback to current site with protocol
        $siteUrl = home_url();
        // Ensure https:// prefix for GSC API
        if (!str_starts_with($siteUrl, 'http')) {
            $siteUrl = 'https://' . $siteUrl;
        }
        return $siteUrl;
    }

    /**
     * Query Google Search Console Search Analytics API.
     * 
     * @param array $params Query parameters:
     *   - startDate: YYYY-MM-DD
     *   - endDate: YYYY-MM-DD
     *   - dimensions: array of 'date', 'query', 'page', etc.
     *   - rowLimit: int (max 25000)
     */
    public function query(array $params): array|\WP_Error
    {
        if (!$this->isConnected()) {
            return new \WP_Error('gsc_not_connected', __('Google Search Console is not connected. Please connect via AI SEO → Integrations.', 'ai-seo-client'));
        }

        $accessToken = $this->oauth->getAccessToken();
        if (empty($accessToken)) {
            return new \WP_Error('gsc_auth', __('Unable to get access token. Please reconnect Google Search Console.', 'ai-seo-client'));
        }

        $siteUrl = $this->getSiteUrl();
        // URL-encode the site URL for the API path
        $encodedSite = urlencode($siteUrl);
        $apiUrl = "https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query";

        $resp = wp_remote_post($apiUrl, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($params),
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code === 401) {
            // Token expired, try to refresh once
            $refreshed = $this->oauth->refresh();
            if (!is_wp_error($refreshed)) {
                return $this->query($params); // Retry with new token
            }
            return new \WP_Error('gsc_auth_expired', __('Google Search Console authentication expired. Please reconnect.', 'ai-seo-client'));
        }

        if ($code !== 200) {
            $errorMsg = $body['error']['message'] ?? __('GSC API error', 'ai-seo-client');
            return new \WP_Error('gsc_api', $errorMsg, ['code' => $code]);
        }

        return $body;
    }

    /**
     * Disconnect GSC - remove stored tokens
     */
    public function disconnect(): void
    {
        delete_option('aiseoclient_gsc_tokens');
        delete_option('sseo_ai_gsc_site_url');
    }
}

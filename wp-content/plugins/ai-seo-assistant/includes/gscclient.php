<?php

namespace AISEOAssistant;

class GscClient
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Placeholder: uses Search Analytics API v3 via keyless fetch if proxy configured.
     */
    public function query(string $site, string $startDate, string $endDate, int $rowLimit = 10): array|\WP_Error
    {
        $endpoint = $this->settings->get('gsc_proxy', '');
        if (!$endpoint) {
            return new \WP_Error('gsc_proxy', __('GSC proxy endpoint ontbreekt', 'ai-seo-assistant'));
        }

        $resp = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'siteUrl' => $site,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['query'],
                'rowLimit' => $rowLimit,
            ]),
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return new \WP_Error('gsc_http', __('GSC proxy error', 'ai-seo-assistant'), $resp);
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return $body['rows'] ?? [];
    }
}

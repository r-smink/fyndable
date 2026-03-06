<?php

namespace AISEOAssistant\Providers;

use AISEOAssistant\Settings;
use WP_Error;

class ApiSerpProvider implements SerpProviderInterface
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function search(string $keyword, array $args = [])
    {
        $apiKey = $this->settings->get('api_key');
        if (!$apiKey) {
            return new WP_Error('missing_api_key', __('API key is required for API provider', 'ai-seo-assistant'));
        }

        $country = $args['country'] ?? 'us';

        $response = wp_remote_get('https://serpapi.com/search', [
            'timeout' => 15,
            'sslverify' => true,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'engine'  => 'google',
                'q'       => $keyword,
                'gl'      => $country,
                'api_key' => $apiKey,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('api_error', __('SERP API returned a non-200 response', 'ai-seo-assistant'), $response);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $organic = $body['organic_results'] ?? [];
        $normalized = [];
        foreach ($organic as $item) {
            $normalized[] = [
                'title'    => $item['title'] ?? '',
                'link'     => $item['link'] ?? '',
                'snippet'  => $item['snippet'] ?? ($item['rich_snippet'] ?? ''),
                'position' => $item['position'] ?? $item['result_position'] ?? null,
            ];
        }
        return $normalized;
    }
}

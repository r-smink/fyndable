<?php

namespace AISEOAssistant\Providers;

use AISEOAssistant\Settings;
use WP_Error;

class DataForSeoProvider implements SerpProviderInterface
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function search(string $keyword, array $args = [])
    {
        $login = $this->settings->get('dfs_login');
        $password = $this->settings->get('dfs_password');

        if (!$login || !$password) {
            return new WP_Error('missing_credentials', __('DataForSEO login/password required', 'ai-seo-assistant'));
        }

        $country = $args['country'] ?? 'us';

        $task = [
            'language_name' => 'English',
            'location_name' => strtoupper($country),
            'keyword'       => $keyword,
            'target'        => null,
            'device'        => 'desktop',
        ];

        $response = wp_remote_post('https://api.dataforseo.com/v3/serp/google/organic/live/regular', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 25,
            'sslverify' => true,
            'body'    => wp_json_encode([$task]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('dfs_api_error', __('DataForSEO returned a non-200 response', 'ai-seo-assistant'), $response);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $tasks = $body['tasks'] ?? [];
        $results = $tasks[0]['result'][0]['items'] ?? [];

        // Normalize output to match other providers.
        return array_map(static function ($item) {
            return [
                'title'    => $item['title'] ?? '',
                'link'     => $item['url'] ?? '',
                'snippet'  => $item['description'] ?? '',
                'position' => $item['rank_group'] ?? $item['rank_absolute'] ?? null,
            ];
        }, $results);
    }
}

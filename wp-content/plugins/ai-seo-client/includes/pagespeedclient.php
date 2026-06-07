<?php

namespace SSEOAIClient;

class PageSpeedClient
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function fetch(string $url, ?string $strategy = null): array|\WP_Error
    {
        $apiKey = $this->settings->get('pagespeed_key', '');
        $endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
        $query = [
            'url' => $url,
            'strategy' => $strategy ?: $this->settings->psiStrategy(),
        ];
        if ($apiKey) {
            $query['key'] = $apiKey;
        }
        $resp = wp_remote_get(add_query_arg($query, $endpoint), ['timeout' => 20]);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return new \WP_Error('pagespeed_http', __('PageSpeed API error', 'ai-seo-client'), $resp);
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return $this->extractMetrics($body, $strategy ?: $this->settings->psiStrategy());
    }

    private function extractMetrics(array $body, string $strategy): array
    {
        $lighthouse = $body['lighthouseResult'] ?? [];
        $categories = $lighthouse['categories'] ?? [];
        $perf = $categories['performance']['score'] ?? null;
        $audits = $lighthouse['audits'] ?? [];

        $getNum = function ($key) use ($audits) {
            return isset($audits[$key]['numericValue']) ? round($audits[$key]['numericValue'], 0) : null;
        };

        return [
            'performance' => $perf !== null ? $perf * 100 : null,
            'lcp' => $getNum('largest-contentful-paint'),
            'inp' => $getNum('experimental-interactive'),
            'cls' => isset($audits['cumulative-layout-shift']['numericValue']) ? $audits['cumulative-layout-shift']['numericValue'] : null,
            'ttfb' => $getNum('server-response-time'),
            'fcp' => $getNum('first-contentful-paint'),
            'strategy' => $strategy,
        ];
    }
}

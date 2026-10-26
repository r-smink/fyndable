<?php

namespace SSEOAIClient;

/**
 * DataForSEO Backlinks API Client
 *
 * Provides domain summary and backlink profile data from DataForSEO.
 * Used as fallback when the Portal proxy is unavailable and the customer
 * has configured their own DataForSEO key.
 */
class DataForSEOBacklinksClient
{
    private const API_BASE = 'https://api.dataforseo.com/v3/backlinks/';
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Get domain authority metrics for a target domain.
     */
    public function getDomainMetrics(string $domain): array
    {
        $domain = $this->normalizeTarget($domain);
        $response = $this->request(self::API_BASE . 'summary/live', [
            ['target' => $domain, 'rank_scale' => 'one_hundred'],
        ]);

        if (is_wp_error($response)) {
            return $this->getDefaultMetrics();
        }

        $summary = $response[0]['result'][0] ?? [];

        return [
            'domain_rating' => (int) ($summary['rank'] ?? 0),
            'referring_domains' => (int) ($summary['referring_domains'] ?? 0),
            'backlinks' => (int) ($summary['backlinks'] ?? 0),
            'organic_traffic' => 0, // Not part of the Backlinks summary
            'organic_keywords' => 0,
        ];
    }

    /**
     * Get raw backlink items (DataForSEO item shape).
     *
     * @param array $params Extra task params: limit, mode, filters, order_by, backlinks_status_type.
     */
    public function getBacklinkItems(string $domain, array $params = []): array|\WP_Error
    {
        $domain = $this->normalizeTarget($domain);
        $task = array_merge([
            'target' => $domain,
            'limit' => 1000,
            'rank_scale' => 'one_hundred',
        ], $params);

        $response = $this->request(self::API_BASE . 'backlinks/live', [$task]);
        if (is_wp_error($response)) {
            return $response;
        }

        return $response[0]['result'][0]['items'] ?? [];
    }

    /**
     * Make a POST request to the DataForSEO Backlinks API.
     */
    private function request(string $url, array $body)
    {
        $auth = $this->apiKey;
        if (str_contains($auth, ':')) {
            $auth = base64_encode($auth);
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . $auth,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || empty($data['tasks'][0]) || (int) ($data['tasks'][0]['status_code'] ?? 20000) >= 40000) {
            return new \WP_Error('dataforseo_backlinks_error', $data['tasks'][0]['status_message'] ?? __('DataForSEO request failed', 'ai-seo-client'));
        }

        return $data['tasks'];
    }

    /**
     * Normalize target for DataForSEO (domain/root_domain).
     */
    private function normalizeTarget(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        return rtrim($domain, '/');
    }

    private function getDefaultMetrics(): array
    {
        return [
            'domain_rating' => 0,
            'referring_domains' => 0,
            'backlinks' => 0,
            'organic_traffic' => 0,
            'organic_keywords' => 0,
        ];
    }
}

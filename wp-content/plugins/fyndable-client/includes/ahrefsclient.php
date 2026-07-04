<?php

namespace SSEOAIClient;

/**
 * Ahrefs API Client
 *
 * Handles communication with Ahrefs API v3.
 * https://api.ahrefs.com/v3/
 */
class AhrefsClient
{
    private const API_BASE = 'https://api.ahrefs.com/v3/';
    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    private function request(string $endpoint, array $params = []): array|\WP_Error
    {
        $url = self::API_BASE . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200 || empty($body)) {
            return new \WP_Error('ahrefs_error', $body['error'] ?? __('Ahrefs API error', 'ai-seo-client'));
        }

        return $body;
    }

    /**
     * Get domain rating for a target
     */
    public function getDomainRating(string $target): array|\WP_Error
    {
        return $this->request('site-explorer/domain-rating', ['target' => $target, 'mode' => 'subdomains']);
    }

    /**
     * Get organic traffic estimate
     */
    public function getOrganicTraffic(string $target): array|\WP_Error
    {
        return $this->request('site-explorer/overview', ['target' => $target, 'mode' => 'subdomains']);
    }

    /**
     * Get backlink summary
     */
    public function getBacklinksSummary(string $target): array|\WP_Error
    {
        return $this->request('site-explorer/backlinks-stats', ['target' => $target, 'mode' => 'subdomains']);
    }

    /**
     * Get referring domains
     */
    public function getReferringDomains(string $target, int $limit = 10): array|\WP_Error
    {
        return $this->request('site-explorer/ref-domains', [
            'target' => $target,
            'mode' => 'subdomains',
            'limit' => $limit,
        ]);
    }

    /**
     * Get top pages by traffic
     */
    public function getTopPages(string $target, int $limit = 10): array|\WP_Error
    {
        return $this->request('site-explorer/top-pages', [
            'target' => $target,
            'mode' => 'subdomains',
            'limit' => $limit,
        ]);
    }

    /**
     * Get organic keywords
     */
    public function getOrganicKeywords(string $target, int $limit = 10): array|\WP_Error
    {
        return $this->request('site-explorer/organic-keywords', [
            'target' => $target,
            'mode' => 'subdomains',
            'limit' => $limit,
        ]);
    }

    /**
     * Get pages by traffic
     */
    public function getPagesByTraffic(string $target, int $limit = 10): array|\WP_Error
    {
        return $this->request('site-explorer/pages-by-traffic', [
            'target' => $target,
            'mode' => 'subdomains',
            'limit' => $limit,
        ]);
    }
}

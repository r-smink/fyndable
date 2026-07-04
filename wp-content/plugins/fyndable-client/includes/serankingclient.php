<?php

namespace SSEOAIClient;

/**
 * SE Ranking API Client
 *
 * Handles communication with SE Ranking API v4.
 * https://api4.seranking.com/
 */
class SERankingClient
{
    private const API_BASE = 'https://api4.seranking.com/';
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
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiKey,
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
            return new \WP_Error('seranking_error', $body['error'] ?? __('SE Ranking API error', 'ai-seo-client'));
        }

        return $body;
    }

    /**
     * Get list of sites (projects)
     */
    public function getSites(): array|\WP_Error
    {
        return $this->request('sites');
    }

    /**
     * Get site details
     */
    public function getSite(int $siteId): array|\WP_Error
    {
        return $this->request("sites/{$siteId}");
    }

    /**
     * Get site rankings
     */
    public function getSiteRankings(int $siteId, string $dateFrom = '', string $dateTo = ''): array|\WP_Error
    {
        $params = [];
        if ($dateFrom) $params['date_from'] = $dateFrom;
        if ($dateTo) $params['date_to'] = $dateTo;
        return $this->request("sites/{$siteId}/positions", $params);
    }

    /**
     * Get backlink summary
     */
    public function getBacklinks(int $siteId): array|\WP_Error
    {
        return $this->request("sites/{$siteId}/backlinks");
    }

    /**
     * Get site audit summary
     */
    public function getAudit(int $siteId): array|\WP_Error
    {
        return $this->request("sites/{$siteId}/audit");
    }

    /**
     * Get competitors
     */
    public function getCompetitors(int $siteId): array|\WP_Error
    {
        return $this->request("sites/{$siteId}/competitors");
    }

    /**
     * Get analytics summary
     */
    public function getAnalytics(int $siteId): array|\WP_Error
    {
        return $this->request("sites/{$siteId}/analytics");
    }
}

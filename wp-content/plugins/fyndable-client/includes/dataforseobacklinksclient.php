<?php

namespace SSEOAIClient;

/**
 * DataForSEO Backlinks API Client
 * 
 * Provides domain summary and backlink profile data from DataForSEO.
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
        $response = $this->request(self::API_BASE . $domain . '/summary/live', [
            ['target' => $domain],
        ]);

        if (is_wp_error($response)) {
            return $this->getDefaultMetrics();
        }

        $result = $response[0]['result'][0] ?? [];
        $summary = $result['summary'] ?? $result ?? [];

        return [
            'domain_rating' => (int) ($summary['domain_rank'] ?? $summary['domain_rating'] ?? 0),
            'referring_domains' => (int) ($summary['referring_domains'] ?? 0),
            'backlinks' => (int) ($summary['backlinks'] ?? 0),
            'organic_traffic' => (int) ($summary['organic_traffic'] ?? 0),
            'organic_keywords' => (int) ($summary['organic_keywords'] ?? 0),
        ];
    }

    /**
     * Get backlink profile summary (dofollow/nofollow, top anchors).
     */
    public function getBacklinks(string $domain, int $limit = 1000): array
    {
        $domain = $this->normalizeTarget($domain);
        $response = $this->request(self::API_BASE . $domain . '/backlinks/live', [
            [
                'target' => $domain,
                'limit' => $limit,
            ],
        ]);

        if (is_wp_error($response)) {
            return $this->getDefaultProfile();
        }

        $items = $response[0]['result'][0]['items'] ?? [];

        $dofollow = 0;
        $nofollow = 0;
        $redirect = 0;
        $anchors = [];

        foreach ($items as $link) {
            $linkType = $link['link_type'] ?? '';
            $isDofollow = ($linkType === 'dofollow' || ($link['dofollow'] ?? false));

            if ($isDofollow) {
                $dofollow++;
            } else {
                $nofollow++;
            }

            if (!empty($link['is_redirect']) || stripos($link['type'] ?? '', 'redirect') !== false) {
                $redirect++;
            }

            $anchor = $link['anchor_text'] ?? $link['anchor'] ?? '';
            if (!empty($anchor)) {
                if (!isset($anchors[$anchor])) {
                    $anchors[$anchor] = 0;
                }
                $anchors[$anchor]++;
            }
        }

        arsort($anchors);
        $topAnchors = [];
        foreach (array_slice($anchors, 0, 10, true) as $text => $count) {
            $topAnchors[] = ['text' => $text, 'count' => $count];
        }

        return [
            'dofollow' => $dofollow,
            'nofollow' => $nofollow,
            'redirect' => $redirect,
            'top_anchors' => $topAnchors,
            'items' => $items,
        ];
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

        if ($statusCode !== 200 || empty($data['tasks'][0])) {
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
        return $domain;
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

    private function getDefaultProfile(): array
    {
        return [
            'dofollow' => 0,
            'nofollow' => 0,
            'redirect' => 0,
            'top_anchors' => [],
            'items' => [],
        ];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * CompetitorService
 *
 * Orchestrates SERP competitor analysis by combining SERP results, backlink
 * data, keyword metrics and an AI summary into a single analysis report.
 */
class CompetitorService
{
    public function __construct(private SaasProxyClient $saas) {}

    /**
     * Analyse the SERP for a keyword and return competitors with enriched data.
     *
     * @return array{competitors: array, serp_features: array, summary?: string}|array{error: string}
     */
    public function analyze(
        string $licenseKey,
        string $tenantKey,
        string $keyword,
        string $targetUrl,
        string $country = 'us',
        string $language = 'en'
    ): array {
        // 1. Fetch SERP results
        $serp = $this->saas->serpQuery($licenseKey, $tenantKey, $keyword, $country, $language);
        if (isset($serp['error'])) {
            return $serp;
        }

        $organicResults = $serp['organic_results'] ?? $serp['results'] ?? [];
        $serpFeatures = $serp['serp_features'] ?? $serp['features'] ?? [];

        // 2. Extract unique domains from top results
        $domains = [];
        foreach (array_slice($organicResults, 0, 10) as $result) {
            $url = $result['url'] ?? $result['link'] ?? '';
            $host = parse_url($url, PHP_URL_HOST);
            if ($host && ! isset($domains[$host])) {
                $domains[$host] = $result;
            }
        }

        // 3. Enrich top 5 domains with backlink data
        $competitors = [];
        $enrichCount = 0;
        foreach ($domains as $domain => $result) {
            $competitor = [
                'domain' => $domain,
                'url' => $result['url'] ?? $result['link'] ?? '',
                'title' => $result['title'] ?? '',
                'position' => $result['position'] ?? $result['rank'] ?? null,
                'snippet' => $result['snippet'] ?? $result['description'] ?? '',
            ];

            if ($enrichCount < 5) {
                $bl = $this->saas->backlinksSummary($licenseKey, $tenantKey, $domain);
                if (! isset($bl['error'])) {
                    $competitor['backlinks'] = [
                        'total' => $bl['total_backlinks'] ?? $bl['backlinks'] ?? null,
                        'referring_domains' => $bl['referring_domains'] ?? null,
                        'domain_rank' => $bl['rank'] ?? $bl['domain_rank'] ?? null,
                    ];
                }
                $enrichCount++;
            }

            $competitors[] = $competitor;
        }

        // 4. Check target position
        $targetPosition = null;
        $normalizedTarget = $this->normalizeUrl($targetUrl);
        foreach ($organicResults as $result) {
            $resultUrl = $result['url'] ?? $result['link'] ?? '';
            if (str_contains($this->normalizeUrl($resultUrl), $normalizedTarget)) {
                $targetPosition = $result['position'] ?? $result['rank'] ?? null;
                break;
            }
        }

        return [
            'keyword' => $keyword,
            'country' => $country,
            'target_url' => $targetUrl,
            'target_position' => $targetPosition,
            'competitors' => $competitors,
            'serp_features' => $serpFeatures,
            'total_results' => $serp['total_results'] ?? null,
        ];
    }

    /**
     * Compare two domains on backlinks and basic metrics.
     */
    public function compare(string $licenseKey, string $tenantKey, string $domainA, string $domainB): array
    {
        $blA = $this->saas->backlinksSummary($licenseKey, $tenantKey, $domainA);
        $blB = $this->saas->backlinksSummary($licenseKey, $tenantKey, $domainB);

        return [
            'domains' => [
                $domainA => isset($blA['error']) ? ['error' => $blA['error']] : $blA,
                $domainB => isset($blB['error']) ? ['error' => $blB['error']] : $blB,
            ],
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://#', '', $url);

        return rtrim($url, '/');
    }
}

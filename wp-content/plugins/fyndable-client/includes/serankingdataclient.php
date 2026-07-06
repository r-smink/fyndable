<?php

namespace SSEOAIClient;

/**
 * SE Ranking Data API Client
 *
 * Handles communication with the SE Ranking Data/Research API v1.
 * https://api.seranking.com/v1/  (docs: https://seranking.com/api/data/)
 *
 * This is separate from SERankingClient, which targets the account/reporting
 * API (api4.seranking.com) and only exposes data about your own projects.
 * The Data API works on ANY domain/keyword and powers domain analysis,
 * keyword research, SERP and competitor research.
 */
class SERankingDataClient
{
    private const API_BASE = 'https://api.seranking.com/v1/';
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Perform a GET request against the Data API, with transient caching.
     */
    private function get(string $endpoint, array $params = [], bool $cache = true): array|\WP_Error
    {
        $url = self::API_BASE . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }

        $cacheKey = '';
        if ($cache) {
            $cacheKey = 'sseo_serd_' . md5($url);
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiKey,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $result = $this->parseResponse($response);
        if (!is_wp_error($result) && $cache) {
            set_transient($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * Perform a POST request (JSON body) against the Data API.
     */
    private function post(string $endpoint, array $query = [], array $body = [], bool $cache = true): array|\WP_Error
    {
        $url = self::API_BASE . ltrim($endpoint, '/');
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }

        $cacheKey = '';
        if ($cache) {
            $cacheKey = 'sseo_serd_' . md5($url . wp_json_encode($body));
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Token ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ]);

        $result = $this->parseResponse($response);
        if (!is_wp_error($result) && $cache) {
            set_transient($cacheKey, $result, self::CACHE_TTL);
        }

        return $result;
    }

    /**
     * Normalise a wp_remote_* response into an array or WP_Error.
     */
    private function parseResponse($response): array|\WP_Error
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status === 401 || $status === 403) {
            return new \WP_Error('seranking_auth', __('SE Ranking API key is invalid or lacks Data API access.', 'ai-seo-client'));
        }

        if ($status === 402) {
            return new \WP_Error('seranking_credits', __('Insufficient SE Ranking API credits.', 'ai-seo-client'));
        }

        if ($status < 200 || $status >= 300) {
            $message = is_array($body) ? ($body['message'] ?? $body['error'] ?? '') : '';
            return new \WP_Error('seranking_error', $message ?: sprintf(__('SE Ranking API error (HTTP %d).', 'ai-seo-client'), $status));
        }

        if ($body === null) {
            return new \WP_Error('seranking_empty', __('Empty response from SE Ranking API.', 'ai-seo-client'));
        }

        return is_array($body) ? $body : ['data' => $body];
    }

    // ---------------------------------------------------------------------
    // Domain Analysis
    // ---------------------------------------------------------------------

    /**
     * Get a domain overview (organic + paid keyword statistics) for a region.
     */
    public function getDomainOverview(string $domain, string $source = 'us', bool $withSubdomains = true): array|\WP_Error
    {
        return $this->get('domain/overview/db', [
            'source' => $source,
            'domain' => $domain,
            'with_subdomains' => $withSubdomains ? 1 : 0,
        ]);
    }

    /**
     * Get the keywords a domain (or URL) ranks for.
     */
    public function getDomainKeywords(string $domain, string $source = 'us', array $args = []): array|\WP_Error
    {
        $params = array_merge([
            'source' => $source,
            'domain' => $domain,
            'type' => 'organic',
            'limit' => 50,
            'order_field' => 'traffic',
            'order_type' => 'desc',
            'cols' => 'keyword,position,volume,cpc,difficulty,traffic,traffic_percent,url',
        ], $args);

        return $this->get('domain/keywords', $params);
    }

    /**
     * Get the top ranking pages for a domain.
     */
    public function getDomainPages(string $domain, string $source = 'us', string $type = 'organic', int $limit = 50): array|\WP_Error
    {
        return $this->get('domain/pages', [
            'target' => $domain,
            'scope' => 'base_domain',
            'source' => $source,
            'type' => $type,
            'limit' => $limit,
        ]);
    }

    /**
     * Get organic (or paid) competitor domains for a target domain.
     */
    public function getDomainCompetitors(string $domain, string $source = 'us', string $type = 'organic'): array|\WP_Error
    {
        return $this->get('domain/competitors', [
            'source' => $source,
            'domain' => $domain,
            'type' => $type,
        ]);
    }

    /**
     * Compare keyword rankings between two domains (keyword overlap / gap).
     * diff: 'common' = both rank, 'missing' = primary ranks but compare doesn't,
     *       'extra' = compare ranks but primary doesn't.
     */
    public function getKeywordComparison(string $primaryDomain, string $compareDomain, string $source = 'us', string $type = 'organic', string $diff = 'common', int $limit = 50): array|\WP_Error
    {
        return $this->get('domain/keywords/comparison', [
            'source' => $source,
            'domain' => $primaryDomain,
            'compare_domain' => $compareDomain,
            'type' => $type,
            'diff' => $diff,
            'limit' => $limit,
        ]);
    }

    // ---------------------------------------------------------------------
    // Keyword Research
    // ---------------------------------------------------------------------

    /**
     * Get bulk metrics (volume, CPC, competition, difficulty, trend) for keywords.
     */
    public function getKeywordMetrics(array $keywords, string $source = 'us'): array|\WP_Error
    {
        return $this->post('keywords/export', ['source' => $source], [
            'keywords' => array_values($keywords),
        ]);
    }

    /**
     * Get similar keywords for a seed keyword.
     */
    public function getSimilarKeywords(string $keyword, string $source = 'us', int $limit = 50): array|\WP_Error
    {
        return $this->get('keywords/similar', [
            'source' => $source,
            'keyword' => $keyword,
            'limit' => $limit,
            'sort' => 'volume',
            'sort_order' => 'desc',
        ]);
    }

    /**
     * Get related keywords for a seed keyword.
     */
    public function getRelatedKeywords(string $keyword, string $source = 'us', int $limit = 50): array|\WP_Error
    {
        return $this->get('keywords/related', [
            'source' => $source,
            'keyword' => $keyword,
            'limit' => $limit,
            'sort' => 'volume',
            'sort_order' => 'desc',
        ]);
    }

    /**
     * Get question keywords for a seed keyword.
     */
    public function getQuestionKeywords(string $keyword, string $source = 'us', int $limit = 50): array|\WP_Error
    {
        return $this->get('keywords/questions', [
            'source' => $source,
            'keyword' => $keyword,
            'limit' => $limit,
            'sort' => 'volume',
            'sort_order' => 'desc',
        ]);
    }

    /**
     * Get long-tail keywords for a seed keyword.
     */
    public function getLongtailKeywords(string $keyword, string $source = 'us', int $limit = 50): array|\WP_Error
    {
        return $this->get('keywords/longtail', [
            'source' => $source,
            'keyword' => $keyword,
            'limit' => $limit,
            'sort' => 'volume',
            'sort_order' => 'desc',
        ]);
    }

    // ---------------------------------------------------------------------
    // SERP (asynchronous task-based)
    // ---------------------------------------------------------------------

    /**
     * Cached map of common source codes to SE Ranking SERP location_ids.
     * These are country-level location IDs returned by the /serp/classic/locations endpoint.
     */
    private const SOURCE_LOCATION_MAP = [
        'us' => 2840,
        'uk' => 2826,
        'gb' => 2826,
        'nl' => 2740,
        'de' => 2756,
        'fr' => 2750,
        'be' => 2710,
        'es' => 2764,
        'it' => 2774,
        'au' => 2674,
        'ca' => 2700,
        'br' => 2670,
    ];

    /**
     * Search SE Ranking SERP locations by country code.
     * Returns an array of location objects with id, name, etc.
     */
    public function getSerpLocations(string $countryCode, string $q = ''): array|\WP_Error
    {
        $params = ['country_code' => strtoupper($countryCode)];
        if ($q !== '') {
            $params['q'] = $q;
        }
        return $this->get('serp/classic/locations', $params, true);
    }

    /**
     * Resolve a source country code to a numeric SERP location_id.
     * Uses a static map first, then falls back to the API locations endpoint.
     */
    public function resolveLocationId(string $source): int|\WP_Error
    {
        $source = strtolower($source);
        if (isset(self::SOURCE_LOCATION_MAP[$source])) {
            return self::SOURCE_LOCATION_MAP[$source];
        }

        $locations = $this->getSerpLocations($source);
        if (is_wp_error($locations)) {
            return $locations;
        }

        $locs = is_array($locations) ? $locations : [];
        foreach ($locs as $loc) {
            if (isset($loc['id']) && (int) $loc['id'] > 0) {
                return (int) $loc['id'];
            }
        }

        return new \WP_Error('seranking_location', sprintf(__('No SERP location found for source "%s".', 'ai-seo-client'), $source));
    }

    /**
     * Add a classic SERP task for a keyword. Returns a task id to poll.
     */
    public function addSerpTask(string $keyword, string $source = 'us'): array|\WP_Error
    {
        $locationId = $this->resolveLocationId($source);
        if (is_wp_error($locationId)) {
            return $locationId;
        }

        return $this->post('serp/classic/tasks', [], [
            'query' => $keyword,
            'location_id' => $locationId,
        ], false);
    }

    /**
     * Retrieve the status/results of a previously added SERP task.
     */
    public function getSerpTask(string $taskId): array|\WP_Error
    {
        return $this->get('serp/classic/tasks', ['id' => $taskId], false);
    }

    // ---------------------------------------------------------------------
    // AI Search Visibility
    // ---------------------------------------------------------------------

    /**
     * Get aggregated AI search overview (all engines) — current snapshot.
     */
    public function getAiSearchOverviewAggregated(string $target, string $source = 'us', ?string $brand = null, string $scope = 'base_domain'): array|\WP_Error
    {
        $params = [
            'target' => $target,
            'source' => $source,
            'scope' => $scope,
        ];
        if ($brand !== null && $brand !== '') {
            $params['brand'] = $brand;
        }
        return $this->get('ai-search/overview/aggregated/current', $params);
    }

    /**
     * Get AI search overview for a single engine — current snapshot.
     */
    public function getAiSearchOverviewByEngine(string $target, string $engine, string $source = 'us', ?string $brand = null, string $scope = 'base_domain'): array|\WP_Error
    {
        $params = [
            'target' => $target,
            'source' => $source,
            'engine' => $engine,
            'scope' => $scope,
        ];
        if ($brand !== null && $brand !== '') {
            $params['brand'] = $brand;
        }
        return $this->get('ai-search/overview/by-engine/current', $params);
    }

    /**
     * Discover the brand name associated with a target domain.
     */
    public function discoverBrand(string $target, string $source = 'us'): array|\WP_Error
    {
        return $this->get('ai-search/discover-brand', [
            'target' => $target,
            'source' => $source,
        ]);
    }

    /**
     * Get prompts by target — where a domain/URL appears in LLM responses.
     */
    public function getPromptsByTarget(string $target, string $source = 'us', string $engine = 'ai-overview', int $limit = 50): array|\WP_Error
    {
        return $this->get('ai-search/prompts-by-target', [
            'target' => $target,
            'source' => $source,
            'engine' => $engine,
            'limit' => $limit,
        ]);
    }
}

<?php

namespace SSEOAISaaS;

/**
 * DataForSEO Central API Client
 *
 * Single client for all DataForSEO v3 endpoints used by the SaaS Dashboard:
 *  - SERP (Google Organic Live Advanced)
 *  - AI Optimization (LLM Mentions, AI Keyword Data, LLM Responses)
 *  - Keywords Data (Google Trends, DataForSEO Trends)
 *  - Backlinks (Summary, Live)
 *
 * Authentication: DataForSEO uses Basic auth with base64(email:password).
 * The API key is stored as "email:password" in a single option.
 *
 * Includes circuit-breaker protection (same transient pattern as ApiGateway).
 */
class DataForSeoClient
{
    private const API_BASE = 'https://api.dataforseo.com/v3';
    private const CIRCUIT_BREAKER_FAILURES = 3;
    private const CIRCUIT_BREAKER_WINDOW = 900; // 15 minutes
    private const MAX_RETRIES = 3;

    // Approximate external cost per request used for cost tracking (converted to EUR for display).
    public const PRICING = [
        'serp'           => 0.002,
        'ai_mentions'    => 0.01,
        'ai_keyword'     => 0.005,
        'llm_response'   => 0.02,
        'google_trends'  => 0.002,
        'dfs_trends'     => 0.002,
        'backlinks'      => 0.005,
    ];

    private SaaSSettings $settings;

    public function __construct(SaaSSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get the DataForSEO API key (email:password format).
     */
    public function getApiKey(): string
    {
        return $this->settings->getDataForSeoApiKey();
    }

    /**
     * Check if DataForSEO is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    // -------------------------------------------------------------------------
    // SERP
    // -------------------------------------------------------------------------

    /**
     * Fetch Google organic SERP results (Live Advanced).
     *
     * @param string $keyword      Search keyword
     * @param int    $locationCode DataForSEO location code (e.g. 2840 = US)
     * @param string $languageCode Language code (e.g. 'en', 'nl')
     * @param string $countryCode  ISO country code for device/country params
     * @return array|\WP_Error     Normalized items array or WP_Error
     */
    public function serpOrganicLiveAdvanced(string $keyword, int $locationCode, string $languageCode, string $countryCode = ''): array|\WP_Error
    {
        $task = [
            'keyword'       => $keyword,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
        ];
        if (!empty($countryCode)) {
            $task['device'] = 'desktop';
        }

        $result = $this->request('/serp/google/organic/live/advanced', [$task], 'serp');

        if (is_wp_error($result)) {
            return $result;
        }

        // DataForSEO returns tasks[0].result[0].items
        $items = $result['tasks'][0]['result'][0]['items'] ?? [];
        return $items;
    }

    /**
     * Fetch Google Maps SERP results (Live Advanced) around GPS coordinates.
     *
     * @param string $keyword      Search keyword
     * @param string $coordinate   "latitude,longitude,zoom" string
     * @param string $languageCode Language code (e.g. 'en', 'nl')
     * @param bool   $searchThisArea Use Google Maps "search this area" flag for broader local results
     * @return array|\WP_Error     Normalized items array or WP_Error
     */
    public function serpGoogleMapsLiveAdvanced(string $keyword, string $coordinate, string $languageCode = 'en', bool $searchThisArea = true): array|\WP_Error
    {
        $task = [
            'keyword'             => $keyword,
            'location_coordinate' => $coordinate,
            'language_code'       => $languageCode,
        ];
        if ($searchThisArea) {
            $task['search_this_area'] = true;
        }

        $result = $this->request('/serp/google/maps/live/advanced', [$task], 'serp');

        if (is_wp_error($result)) {
            return $result;
        }

        $items = $result['tasks'][0]['result'][0]['items'] ?? [];
        return $items;
    }

    /**
     * Fetch Google Local Finder SERP results (Live Advanced) around GPS coordinates.
     *
     * @param string $keyword      Search keyword
     * @param string $coordinate   "latitude,longitude,zoom" string
     * @param string $languageCode Language code (e.g. 'en', 'nl')
     * @return array|\WP_Error     Normalized items array or WP_Error
     */
    public function serpGoogleLocalFinderLiveAdvanced(string $keyword, string $coordinate, string $languageCode = 'en'): array|\WP_Error
    {
        $task = [
            'keyword'             => $keyword,
            'location_coordinate' => $coordinate,
            'language_code'       => $languageCode,
        ];

        $result = $this->request('/serp/google/local_finder/live/advanced', [$task], 'serp');

        if (is_wp_error($result)) {
            return $result;
        }

        $items = $result['tasks'][0]['result'][0]['items'] ?? [];
        return $items;
    }

    /**
     * Convert a radius in kilometres to an approximate Google Maps zoom level.
     * Uses a constant pixel viewport assumption (640px) and aims for a
     * map view that covers at least 4x the requested radius.
     */
    public static function radiusToZoom(float $radiusKm, float $latitude = 52.0): int
    {
        if ($radiusKm <= 0) {
            $radiusKm = 5;
        }

        // metres per pixel at zoom z = 156543.03392 * cos(lat) / 2^z
        // we want a 640px viewport to cover 4x the requested radius
        $targetMpp = (4 * $radiusKm * 1000) / 640;
        $circumferenceMpp = 156543.03392 * max(0.1, abs(cos(deg2rad($latitude))));

        $zoom = (int) round(log($circumferenceMpp / $targetMpp, 2));
        return max(3, min(21, $zoom));
    }

    // -------------------------------------------------------------------------
    // AI Optimization — LLM Mentions
    // -------------------------------------------------------------------------

    /**
     * Search LLM Mentions for a keyword/target.
     * Endpoint: /v3/ai_optimization/llm_mentions/search_mentions/live
     */
    public function aiMentionsSearchMentions(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/search_mentions/live', [$params], 'ai_mentions');
    }

    /**
     * Get target metrics for LLM Mentions.
     * Endpoint: /v3/ai_optimization/llm_mentions/target_metrics/live
     */
    public function aiMentionsTargetMetrics(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/target_metrics/live', [$params], 'ai_mentions');
    }

    /**
     * Get top mentioned domains.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_domains/live
     */
    public function aiMentionsTopMentionedDomains(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_domains/live', [$params], 'ai_mentions');
    }

    /**
     * Get top mentioned pages.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_pages/live
     */
    public function aiMentionsTopMentionedPages(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_pages/live', [$params], 'ai_mentions');
    }

    /**
     * Get top mentioned brands.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_brands/live
     */
    public function aiMentionsTopMentionedBrands(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_brands/live', [$params], 'ai_mentions');
    }

    /**
     * Get top mentioned brand categories.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_brand_categories/live
     */
    public function aiMentionsTopMentionedBrandCategories(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_brand_categories/live', [$params], 'ai_mentions');
    }

    /**
     * Get historical LLM mentions data.
     * Endpoint: /v3/ai_optimization/llm_mentions/historical/live
     */
    public function aiMentionsHistorical(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/historical/live', [$params], 'ai_mentions');
    }

    /**
     * Get timeseries delta.
     * Endpoint: /v3/ai_optimization/llm_mentions/timeseries_delta/live
     */
    public function aiMentionsTimeseriesDelta(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/timeseries_delta/live', [$params], 'ai_mentions');
    }

    /**
     * Get timeseries new & lost mentions.
     * Endpoint: /v3/ai_optimization/llm_mentions/timeseries_new_and_lost/live
     */
    public function aiMentionsTimeseriesNewAndLost(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/timeseries_new_and_lost/live', [$params], 'ai_mentions');
    }

    // -------------------------------------------------------------------------
    // AI Optimization — AI Keyword Data
    // -------------------------------------------------------------------------

    /**
     * Get AI keyword search volume.
     * Endpoint: /v3/ai_optimization/ai_keyword_data/search_volume/live
     */
    public function aiKeywordDataSearchVolume(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/ai_keyword_data/search_volume/live', [$params], 'ai_keyword');
    }

    // -------------------------------------------------------------------------
    // AI Optimization — LLM Responses (ChatGPT, Claude, Gemini, Perplexity)
    // -------------------------------------------------------------------------

    /**
     * Get a live LLM response from the specified provider.
     *
     * @param string $provider One of: chatgpt, claude, gemini, perplexity
     * @param array  $params   Task parameters (prompt, model, etc.)
     * @return array|\WP_Error
     */
    public function llmResponseLive(string $provider, array $params): array|\WP_Error
    {
        $allowed = ['chatgpt', 'claude', 'gemini', 'perplexity'];
        $provider = strtolower($provider);
        if (!in_array($provider, $allowed, true)) {
            return new \WP_Error('invalid_provider', sprintf(__('Unknown LLM provider: %s', 'sseo-ai-saas'), $provider));
        }

        return $this->request('/ai_optimization/' . $provider . '/llm_responses/live', [$params], 'llm_response');
    }

    // -------------------------------------------------------------------------
    // Keywords Data — Google Trends
    // -------------------------------------------------------------------------

    /**
     * Google Trends Explore (Live).
     * Endpoint: /v3/keywords_data/google_trends/explore/live
     */
    public function googleTrendsExploreLive(array $params): array|\WP_Error
    {
        return $this->request('/keywords_data/google_trends/explore/live', [$params], 'google_trends');
    }

    // -------------------------------------------------------------------------
    // Keywords Data — DataForSEO Trends
    // -------------------------------------------------------------------------

    /**
     * DataForSEO Trends Explore (Live).
     * Endpoint: /v3/keywords_data/dataforseo_trends/explore/live
     */
    public function dataforseoTrendsExploreLive(array $params): array|\WP_Error
    {
        return $this->request('/keywords_data/dataforseo_trends/explore/live', [$params], 'dfs_trends');
    }

    // -------------------------------------------------------------------------
    // Backlinks
    // -------------------------------------------------------------------------

    /**
     * Get backlinks summary for a target domain.
     * Endpoint: /v3/backlinks/{target}/summary/live
     */
    public function backlinksSummaryLive(string $target): array|\WP_Error
    {
        $target = $this->normalizeTarget($target);
        return $this->request('/backlinks/' . urlencode($target) . '/summary/live', [['target' => $target]], 'backlinks');
    }

    /**
     * Get backlinks list for a target domain.
     * Endpoint: /v3/backlinks/{target}/backlinks/live
     */
    public function backlinksLive(string $target, array $params = []): array|\WP_Error
    {
        $target = $this->normalizeTarget($target);
        $task = array_merge(['target' => $target], $params);
        return $this->request('/backlinks/' . urlencode($target) . '/backlinks/live', [$task], 'backlinks');
    }

    // -------------------------------------------------------------------------
    // Core request logic
    // -------------------------------------------------------------------------

    /**
     * Make a POST request to a DataForSEO v3 endpoint.
     *
     * @param string $path     Path after /v3 (e.g. '/serp/google/organic/live/advanced')
     * @param array  $postBody JSON body (array of task objects)
     * @param string $type     Cost type for circuit breaker key
     * @return array|\WP_Error  Decoded JSON response or WP_Error
     */
    private function request(string $path, array $postBody, string $type): array|\WP_Error
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return new \WP_Error('dataforseo_not_configured', __('DataForSEO API key is not configured', 'sseo-ai-saas'));
        }

        // Circuit breaker
        if ($this->isCircuitOpen($type)) {
            return new \WP_Error('circuit_open', __('DataForSEO is temporarily skipped due to recent failures', 'sseo-ai-saas'));
        }

        $url = self::API_BASE . $path;
        $authHeader = $this->authHeader($apiKey);

        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $authHeader,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($postBody),
                'timeout' => 60,
            ]);

            if (is_wp_error($response)) {
                $lastError = $response;
            } else {
                $statusCode = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if ($statusCode === 200 && !empty($body['tasks'])) {
                    $this->recordSuccess($type);
                    return $body;
                }

                // Extract DataForSEO error message
                $message = $body['status_message'] ?? ($body['tasks'][0]['status_message'] ?? '');
                if (empty($message)) {
                    $message = sprintf(__('DataForSEO request failed (HTTP %d)', 'sseo-ai-saas'), $statusCode);
                }
                $lastError = new \WP_Error('dataforseo_error', is_string($message) ? $message : json_encode($message));
            }

            $this->recordFailure($type);

            if ($attempt < self::MAX_RETRIES) {
                usleep(min(4000000, 1000000 * (2 ** ($attempt - 1))));
            }
        }

        return $lastError ?: new \WP_Error('dataforseo_failed', __('DataForSEO request failed', 'sseo-ai-saas'));
    }

    /**
     * Build the Basic auth header value from the API key.
     * Key format is "email:password". If already base64, pass through.
     */
    private function authHeader(string $apiKey): string
    {
        if (str_contains($apiKey, ':')) {
            return base64_encode($apiKey);
        }
        // Already encoded
        return $apiKey;
    }

    /**
     * Normalize a domain target for DataForSEO Backlinks endpoints.
     */
    private function normalizeTarget(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        return $domain;
    }

    // -------------------------------------------------------------------------
    // Circuit breaker
    // -------------------------------------------------------------------------

    private function isCircuitOpen(string $type): bool
    {
        $failures = (int) get_transient('sseo_dfs_fail_' . $type);
        return $failures >= self::CIRCUIT_BREAKER_FAILURES;
    }

    private function recordFailure(string $type): void
    {
        $key = 'sseo_dfs_fail_' . $type;
        $failures = (int) get_transient($key);
        set_transient($key, $failures + 1, self::CIRCUIT_BREAKER_WINDOW);
    }

    private function recordSuccess(string $type): void
    {
        delete_transient('sseo_dfs_fail_' . $type);
    }
}

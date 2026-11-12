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
    // LLM Responses live tasks may take up to 120 s (DataForSEO docs). A shorter client timeout aborted
    // them, the retries were billed again and three timeouts opened the circuit breaker for every
    // following LLM test, so GEO scans reported "insufficient data" for all platforms.
    private const LLM_TIMEOUT = 130;

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
    public function serpOrganicLiveAdvanced(string $keyword, int $locationCode, string $languageCode, string $countryCode = '', array $extraTask = []): array|\WP_Error
    {
        // $extraTask: optional task fields, e.g. ['load_async_ai_overview' => true] for the AI Overview.
        $task = array_merge([
            'keyword'       => $keyword,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
        ], $extraTask);
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
        return $this->request('/ai_optimization/llm_mentions/search_mentions/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get target metrics for LLM Mentions.
     * Endpoint: /v3/ai_optimization/llm_mentions/target_metrics/live
     */
    public function aiMentionsTargetMetrics(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/target_metrics/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get top mentioned domains.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_domains/live
     */
    public function aiMentionsTopMentionedDomains(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_domains/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get top mentioned pages.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_pages/live
     */
    public function aiMentionsTopMentionedPages(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_pages/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get top mentioned brands.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_brands/live
     */
    public function aiMentionsTopMentionedBrands(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_brands/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get top mentioned brand categories.
     * Endpoint: /v3/ai_optimization/llm_mentions/top_mentioned_brand_categories/live
     */
    public function aiMentionsTopMentionedBrandCategories(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/top_mentioned_brand_categories/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get historical LLM mentions data.
     * Endpoint: /v3/ai_optimization/llm_mentions/historical/live
     */
    public function aiMentionsHistorical(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/historical/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get timeseries delta.
     * Endpoint: /v3/ai_optimization/llm_mentions/timeseries_delta/live
     */
    public function aiMentionsTimeseriesDelta(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/timeseries_delta/live', [$this->mentionsParams($params)], 'ai_mentions');
    }

    /**
     * Get timeseries new & lost mentions.
     * Endpoint: /v3/ai_optimization/llm_mentions/timeseries_new_and_lost/live
     */
    public function aiMentionsTimeseriesNewAndLost(array $params): array|\WP_Error
    {
        return $this->request('/ai_optimization/llm_mentions/timeseries_new_and_lost/live', [$this->mentionsParams($params)], 'ai_mentions');
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
        $prepared = $this->prepareLlmRequest($provider, $params);
        if (is_wp_error($prepared)) {
            return $prepared;
        }

        return $this->request($prepared['path'], [$prepared['params']], 'llm_response');
    }

    /**
     * Run several LLM Responses live tasks at the same time (DataForSEO allows 30 concurrent live
     * tasks per platform). Each task may take up to 120 s; run sequentially, a GEO scan with
     * 3 platforms × 3 keywords would exceed PHP's time limit.
     *
     * No retries and no circuit breaker here: a timed-out task is usually still billed, and one slow
     * platform must not block the others.
     *
     * @param array<string|int, array{provider: string, params: array}> $jobs
     * @return array<string|int, array|\WP_Error> same keys as $jobs: the decoded body, or the error
     */
    public function llmResponsesLiveBatch(array $jobs): array
    {
        $results = [];
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            $error = new \WP_Error('dataforseo_not_configured', __('DataForSEO API key is not configured', 'sseo-ai-saas'));
            return array_map(static fn() => $error, $jobs);
        }

        $requests = [];
        foreach ($jobs as $key => $job) {
            $prepared = $this->prepareLlmRequest((string) ($job['provider'] ?? ''), (array) ($job['params'] ?? []));
            if (is_wp_error($prepared)) {
                $results[$key] = $prepared;
                continue;
            }
            $requests[$key] = [
                'url' => self::API_BASE . $prepared['path'],
                'type' => 'POST',
                'headers' => ['Authorization' => 'Basic ' . $this->authHeader($apiKey), 'Content-Type' => 'application/json'],
                'data' => (string) wp_json_encode([$prepared['params']]),
            ];
        }

        $requestsClass = class_exists('\WpOrg\Requests\Requests') ? '\WpOrg\Requests\Requests' : '\Requests';
        $responses = [];
        if ($requests) {
            $options = ['timeout' => self::LLM_TIMEOUT, 'connect_timeout' => 15];
            if (defined('ABSPATH') && defined('WPINC') && is_readable(ABSPATH . WPINC . '/certificates/ca-bundle.crt')) {
                $options['verify'] = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
            }
            try {
                $responses = $requestsClass::request_multiple($requests, $options);
            } catch (\Throwable $e) {
                $responses = array_map(static fn() => $e, $requests);
            }
        }

        foreach ($requests as $key => $request) {
            $response = $responses[$key] ?? null;
            if ($response instanceof \Throwable || !is_object($response) || !isset($response->status_code)) {
                $message = $response instanceof \Throwable ? $response->getMessage() : __('No response from DataForSEO', 'sseo-ai-saas');
                $results[$key] = new \WP_Error('dataforseo_error', $message);
                continue;
            }
            $results[$key] = $this->interpretLlmBody((int) $response->status_code, json_decode((string) $response->body, true));
        }

        // Keep the caller's order.
        $ordered = [];
        foreach (array_keys($jobs) as $key) {
            $ordered[$key] = $results[$key];
        }
        return $ordered;
    }

    /**
     * Normalize LLM Responses parameters and resolve the model.
     *
     * @return array{path: string, params: array}|\WP_Error
     */
    private function prepareLlmRequest(string $provider, array $params): array|\WP_Error
    {
        $paths = [
            'chatgpt'    => 'chat_gpt',
            'claude'     => 'claude',
            'gemini'     => 'gemini',
            'perplexity' => 'perplexity',
        ];
        $provider = strtolower($provider);
        if (!isset($paths[$provider])) {
            return new \WP_Error('invalid_provider', sprintf(__('Unknown LLM provider: %s', 'sseo-ai-saas'), $provider));
        }

        // Older callers used prompt/model; DataForSEO requires user_prompt and model_name.
        if (!isset($params['user_prompt']) && isset($params['prompt'])) {
            $params['user_prompt'] = $params['prompt'];
        }
        if (!isset($params['model_name']) && isset($params['model'])) {
            $params['model_name'] = $params['model'];
        }
        unset($params['prompt'], $params['model'], $params['location_code'], $params['language_code']);
        $params['user_prompt'] = mb_substr(trim((string) ($params['user_prompt'] ?? '')), 0, 500);
        if ($params['user_prompt'] === '') {
            return new \WP_Error('missing_params', __('user_prompt is required', 'sseo-ai-saas'));
        }

        $model = $this->resolveLlmModel($paths[$provider], (string) ($params['model_name'] ?? ''), !empty($params['web_search']));
        if (is_wp_error($model)) {
            return $model;
        }
        $params['model_name'] = $model;

        return ['path' => '/ai_optimization/' . $paths[$provider] . '/llm_responses/live', 'params' => $params];
    }

    /** A DataForSEO LLM response body, or a WP_Error with DataForSEO's own message. */
    private function interpretLlmBody(int $httpStatus, $body): array|\WP_Error
    {
        if (!is_array($body)) {
            return new \WP_Error('dataforseo_error', sprintf(__('DataForSEO request failed (HTTP %d)', 'sseo-ai-saas'), $httpStatus));
        }
        $task = $body['tasks'][0] ?? null;
        if ($httpStatus !== 200 || !is_array($task)) {
            $message = $body['status_message'] ?? sprintf(__('DataForSEO request failed (HTTP %d)', 'sseo-ai-saas'), $httpStatus);
            return new \WP_Error('dataforseo_error', (string) $message);
        }
        $taskStatus = (int) ($task['status_code'] ?? 20000);
        if ($taskStatus !== 20000 || empty($task['result'])) {
            $message = (string) ($task['status_message'] ?? __('DataForSEO returned no result', 'sseo-ai-saas'));
            return new \WP_Error('dataforseo_task_error', $message . ' (' . $taskStatus . ')', ['status_code' => $taskStatus]);
        }
        return $body;
    }

    /**
     * A model_name DataForSEO currently offers for this provider: the
     * requested one when it is listed (full name or base name), otherwise
     * the first non-reasoning model (with web search when asked). The list
     * comes from GET .../llm_responses/models and is cached for a day.
     */
    public function resolveLlmModel(string $providerPath, string $requested, bool $webSearch = false): string|\WP_Error
    {
        $models = $this->llmModels($providerPath);
        if (is_wp_error($models)) {
            return $requested !== '' ? $requested : $models;
        }

        $names = array_column($models, 'model_name');
        if ($requested !== '') {
            foreach ($names as $name) {
                if ($name === $requested || str_starts_with((string) $name, $requested . '-')) {
                    return $requested;
                }
            }
        }

        $candidates = array_values(array_filter($models, fn ($m) => empty($m['reasoning']) && (!$webSearch || !empty($m['web_search_supported']))));
        $pick = $candidates[0]['model_name'] ?? ($names[0] ?? '');

        return $pick !== '' ? (string) $pick : new \WP_Error('no_llm_model', __('No DataForSEO model available for this provider', 'sseo-ai-saas'));
    }

    /**
     * @return array<int, array{model_name: string, reasoning?: bool, web_search_supported?: bool}>|\WP_Error
     */
    private function llmModels(string $providerPath): array|\WP_Error
    {
        $cacheKey = 'sseo_dfs_llm_models_' . $providerPath;
        $cached = get_transient($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return new \WP_Error('dataforseo_not_configured', __('DataForSEO API key is not configured', 'sseo-ai-saas'));
        }

        $response = wp_remote_get(self::API_BASE . '/ai_optimization/' . $providerPath . '/llm_responses/models', [
            'headers' => ['Authorization' => 'Basic ' . $this->authHeader($apiKey)],
            'timeout' => 30,
        ]);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $models = array_values(array_filter((array) ($body['tasks'][0]['result'] ?? []), fn ($m) => is_array($m) && !empty($m['model_name'])));
        if ($models === []) {
            return new \WP_Error('dataforseo_error', __('DataForSEO returned no models', 'sseo-ai-saas'));
        }

        set_transient($cacheKey, $models, DAY_IN_SECONDS);

        return $models;
    }

    /**
     * LLM Mentions params: target must be an array of entities, each with
     * one domain or one keyword. Accepts the older shapes callers send: a
     * string target, an array of strings, or a top-level keyword.
     */
    public function mentionsParams(array $params): array
    {
        $target = $params['target'] ?? null;
        if ($target === null && isset($params['keyword'])) {
            $target = ['keyword' => $params['keyword']];
        }
        unset($params['keyword']);

        if (is_string($target)) {
            $target = [$target];
        } elseif (is_array($target) && (isset($target['domain']) || isset($target['keyword']))) {
            $target = [$target];
        }

        $entities = [];
        foreach ((array) $target as $entity) {
            if (is_string($entity)) {
                $value = trim($entity);
                if ($value === '') {
                    continue;
                }
                // A host without spaces is a domain, anything else a keyword.
                $entity = (!preg_match('/\s/', $value) && str_contains($value, '.'))
                    ? ['domain' => strtok($this->normalizeTarget($value), '/')]
                    : ['keyword' => $value];
            }
            if (is_array($entity) && (!empty($entity['domain']) || !empty($entity['keyword']))) {
                $entities[] = $entity;
            }
        }

        if ($entities !== []) {
            $params['target'] = array_slice($entities, 0, 10);
        } else {
            unset($params['target']);
        }

        return $params;
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
     * Endpoint: /v3/backlinks/summary/live (target goes in the task body, not the path).
     * Ranks are returned on a 0-100 scale so they read like a Domain Rating.
     */
    public function backlinksSummaryLive(string $target): array|\WP_Error
    {
        $target = $this->normalizeTarget($target);
        return $this->request('/backlinks/summary/live', [[
            'target'     => $target,
            'rank_scale' => 'one_hundred',
        ]], 'backlinks');
    }

    /**
     * Get backlinks list for a target domain.
     * Endpoint: /v3/backlinks/backlinks/live (target goes in the task body, not the path).
     */
    public function backlinksLive(string $target, array $params = []): array|\WP_Error
    {
        $target = $this->normalizeTarget($target);
        $task = array_merge(['target' => $target, 'rank_scale' => 'one_hundred'], $params);
        return $this->request('/backlinks/backlinks/live', [$task], 'backlinks');
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

        // LLM Responses: long-running and billed per task, so one attempt with a long timeout and no
        // circuit breaker (a slow answer is not an outage). Everything else keeps retries + breaker.
        $isLlm = $type === 'llm_response';

        // Circuit breaker
        if (!$isLlm && $this->isCircuitOpen($type)) {
            return new \WP_Error('circuit_open', __('DataForSEO is temporarily skipped due to recent failures', 'sseo-ai-saas'));
        }

        $url = self::API_BASE . $path;
        $authHeader = $this->authHeader($apiKey);
        $maxAttempts = $isLlm ? 1 : self::MAX_RETRIES;

        $lastError = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $authHeader,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($postBody),
                'timeout' => $isLlm ? self::LLM_TIMEOUT : 60,
            ]);

            if (is_wp_error($response)) {
                $lastError = $response;
            } else {
                $statusCode = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if ($statusCode === 200 && !empty($body['tasks'])) {
                    $taskStatus = (int) ($body['tasks'][0]['status_code'] ?? 20000);
                    if ($taskStatus >= 40500 && $taskStatus < 40600) {
                        // Invalid request (405xx: invalid field, invalid POST
                        // data): retrying cannot help and it is not a provider
                        // outage, so no circuit breaker. Other task codes (for
                        // example 40102 no results) keep the old behaviour.
                        $message = $body['tasks'][0]['status_message'] ?? __('DataForSEO rejected the request', 'sseo-ai-saas');
                        return new \WP_Error('dataforseo_task_error', (string) $message, ['status_code' => $taskStatus]);
                    }
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

            if (!$isLlm) {
                $this->recordFailure($type);
            }

            if ($attempt < $maxAttempts) {
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

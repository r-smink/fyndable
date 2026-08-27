<?php

namespace SSEOAISaaS;

/**
 * API Gateway / Proxy
 * 
 * Central API management for OpenAI and SERP requests.
 * Validates tenant limits, tracks costs, and routes requests.
 */
class ApiGateway
{
    private TenantRepository $tenants;
    private SaaSSettings $settings;
    private ProviderRouter $providerRouter;
    private ?DataForSeoClient $dataForSeoClient = null;

    // SERP API pricing per request (approximate)
    private const SERP_PRICING = [
        'dataforseo' => 0.002,
        'serpapi' => 0.005,
        'seranking' => 0.003,
    ];

    // SERP provider fallback order
    private const FALLBACK_PROVIDERS = ['dataforseo', 'serpapi', 'seranking'];
    private const LOCAL_PACK_FALLBACK_PROVIDERS = ['dataforseo', 'serpapi'];
    private const MAX_RETRIES = 3;
    private const CIRCUIT_BREAKER_FAILURES = 3;
    private const CIRCUIT_BREAKER_WINDOW = 900; // 15 minutes

    // AI request resilience
    private const AI_TIMEOUT_SECONDS = 120;
    private const AI_MAX_RETRIES = 3;
    private const AI_RETRY_BASE_MS = 500000; // 0.5 seconds

    public function __construct(TenantRepository $tenants, SaaSSettings $settings, ProviderRouter $providerRouter, ?DataForSeoClient $dataForSeoClient = null)
    {
        $this->tenants = $tenants;
        $this->settings = $settings;
        $this->providerRouter = $providerRouter;
        $this->dataForSeoClient = $dataForSeoClient;
    }

    /**
     * Register REST API routes
     */
    public function register(): void
    {
        // OpenAI proxy endpoint
        register_rest_route('ai-seo-saas/v1', '/ai/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handleAiRequest'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);
        
        // SERP proxy endpoint
        register_rest_route('ai-seo-saas/v1', '/serp/query', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSerpRequest'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // SERP rank-check endpoint (used by Rank Tracker)
        register_rest_route('ai-seo-saas/v1', '/serp/rank-check', [
            'methods' => 'POST',
            'callback' => [$this, 'handleSerpRankCheck'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // Local SERP / local pack scan endpoint
        register_rest_route('ai-seo-saas/v1', '/serp/local-pack', [
            'methods' => 'POST',
            'callback' => [$this, 'handleLocalPackRequest'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // Local SERP geo-grid scan endpoint
        register_rest_route('ai-seo-saas/v1', '/serp/local-grid', [
            'methods' => 'POST',
            'callback' => [$this, 'handleLocalGridRequest'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // Usage check endpoint
        register_rest_route('ai-seo-saas/v1', '/usage/check', [
            'methods' => 'GET',
            'callback' => [$this, 'getUsageStatus'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO AI Optimization — LLM Mentions
        register_rest_route('ai-seo-saas/v1', '/ai/llm-mentions', [
            'methods' => 'POST',
            'callback' => [$this, 'handleAiLlmMentions'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO AI Optimization — AI Keyword Data
        register_rest_route('ai-seo-saas/v1', '/ai/keyword-data', [
            'methods' => 'POST',
            'callback' => [$this, 'handleAiKeywordData'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO AI Optimization — LLM Responses (ChatGPT/Claude/Gemini/Perplexity)
        register_rest_route('ai-seo-saas/v1', '/ai/llm-response', [
            'methods' => 'POST',
            'callback' => [$this, 'handleAiLlmResponse'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO Keywords Data — Google Trends
        register_rest_route('ai-seo-saas/v1', '/keywords/google-trends', [
            'methods' => 'POST',
            'callback' => [$this, 'handleGoogleTrends'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO Keywords Data — DataForSEO Trends
        register_rest_route('ai-seo-saas/v1', '/keywords/dataforseo-trends', [
            'methods' => 'POST',
            'callback' => [$this, 'handleDataForSeoTrends'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO Backlinks — Summary
        register_rest_route('ai-seo-saas/v1', '/backlinks/summary', [
            'methods' => 'POST',
            'callback' => [$this, 'handleBacklinksSummary'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // DataForSEO Backlinks — Live
        register_rest_route('ai-seo-saas/v1', '/backlinks/live', [
            'methods' => 'POST',
            'callback' => [$this, 'handleBacklinksLive'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // Google Places autocomplete proxy (client location settings)
        register_rest_route('ai-seo-saas/v1', '/places/autocomplete', [
            'methods' => 'POST',
            'callback' => [$this, 'handlePlaceAutocomplete'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);

        // Google Geocoding proxy (client address autofill)
        register_rest_route('ai-seo-saas/v1', '/places/geocode', [
            'methods' => 'POST',
            'callback' => [$this, 'handleGeocodeRequest'],
            'permission_callback' => [$this, 'validateTenantRequest'],
        ]);
    }

    /**
     * Validate tenant request - check license key and tenant key
     */
    public function validateTenantRequest(\WP_REST_Request $request): bool
    {
        $licenseKey = $request->get_header('X-License-Key');
        $tenantKey = $request->get_header('X-Tenant-Key');
        
        if (empty($licenseKey) || empty($tenantKey)) {
            $body = $request->get_body_params();
            $licenseKey = $body['license_key'] ?? '';
            $tenantKey = $body['tenant_key'] ?? '';
        }
        
        if (empty($licenseKey) || empty($tenantKey)) {
            return false;
        }
        
        // Validate tenant exists and matches license
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant || $tenant['license_key'] !== $licenseKey) {
            return false;
        }
        
        // Check if tenant is active
        if ($tenant['status'] !== 'active') {
            return false;
        }
        
        // Check usage limits
        if ($this->isOverLimit($tenant)) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if tenant is over their usage limit
     */
    private function isOverLimit(array $tenant): bool
    {
        $tier = $tenant['tier'];
        $apiLimit = (int)$tenant['api_calls_limit'] ?: $this->settings->getApiLimitForTier($tier);
        $costLimit = $this->settings->getCostLimitForTier($tier);
        
        // Get current month's usage
        global $wpdb;
        $tableUsage = $wpdb->prefix . 'sseo_ai_tenant_usage';
        $currentMonth = current_time('Y-m');
        
        $usage = $wpdb->get_row($wpdb->prepare(
            "SELECT api_calls as calls, api_cost as cost 
             FROM {$tableUsage} 
             WHERE tenant_id = %d AND period = %s",
            $tenant['id'],
            $currentMonth
        ));
        
        $currentCalls = (int)($usage->calls ?? 0);
        $currentCost = (float)($usage->cost ?? 0);
        
        // Check both API call limit and cost limit
        if ($currentCalls >= $apiLimit || $currentCost >= $costLimit) {
            return true;
        }
        
        return false;
    }

    /**
     * Handle AI generation request
     * Routes through ProviderRouter which supports OpenRouter and OpenAI.
     * Includes retry, timeout and circuit-breaker protection.
     */
    public function handleAiRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $messages = $body['messages'] ?? [];
        $model = $body['model'] ?? null;
        $useCase = $body['use_case'] ?? 'content_generation';
        $maxTokens = (int)($body['max_tokens'] ?? 2000);
        $temperature = (float)($body['temperature'] ?? 0.7);

        // Circuit breaker for the AI service as a whole
        if ($this->isProviderCircuitOpen('ai', 'ai')) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'ai_circuit_open',
                'message' => __('AI service is temporarily unavailable due to recent failures. Please try again later.', 'sseo-ai-saas')
            ], 503);
        }

        // Give the AI request enough runway before PHP times out
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::AI_TIMEOUT_SECONDS);
        }

        $lastError = null;
        for ($attempt = 1; $attempt <= self::AI_MAX_RETRIES; $attempt++) {
            $result = $this->providerRouter->routeRequest($messages, $model, $useCase, $maxTokens, $temperature);

            if (!is_wp_error($result)) {
                $this->recordProviderSuccess('ai', 'ai');
                $cost = $result['usage']['cost'] ?? 0;
                $this->trackUsage($tenant, 'ai_generation', 1, $cost);

                return new \WP_REST_Response([
                    'success' => true,
                    'content' => $result['content'],
                    'model' => $result['model'],
                    'provider' => $result['provider'],
                    'usage' => $result['usage'],
                    'fallback_used' => $result['fallback_used'] ?? false,
                ], 200);
            }

            $this->recordProviderFailure('ai', 'ai');
            $lastError = $result;

            if ($attempt < self::AI_MAX_RETRIES) {
                usleep(min(4000000, self::AI_RETRY_BASE_MS * (2 ** ($attempt - 1))));
            }
        }

        $errorCode = $lastError ? $lastError->get_error_code() : 'ai_request_failed';
        $isTimeout = $errorCode === 'ai_timeout';
        $statusCode = $lastError && $lastError->get_error_code() === 'no_provider' ? 503 : ($isTimeout ? 504 : 502);

        $message = $lastError
            ? $lastError->get_error_message()
            : __('AI generation failed', 'sseo-ai-saas');

        if ($isTimeout) {
            $message = __('AI generation timed out. The request took too long to complete. Try again or use async processing for large clusters.', 'sseo-ai-saas');
        }

        return new \WP_REST_Response([
            'success' => false,
            'error' => $errorCode,
            'message' => $message,
            'timeout' => $isTimeout,
            'details' => $lastError ? ($lastError->get_error_data() ?? null) : null,
        ], $statusCode);
    }

    /**
     * Handle SERP request
     */
    public function handleSerpRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);
        
        // Get SERP credentials
        $apiKey = $this->settings->getSerpApiKey();
        $provider = $this->settings->getSerpApiProvider();
        
        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'serp_not_configured',
                'message' => __('SERP service is not configured', 'sseo-ai-saas')
            ], 503);
        }
        
        $body = $request->get_json_params();
        $keyword = sanitize_text_field($body['keyword'] ?? '');
        $location = sanitize_text_field($body['location'] ?? 'United States');
        
        // Route to appropriate provider with fallback, retry and circuit breaker
        $result = $this->fetchSerpData($provider, $apiKey, $keyword, $location, true);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'serp_request_failed',
                'message' => $result->get_error_message()
            ], 502);
        }

        // Calculate and track cost using the provider that actually answered
        $actualProvider = $result['_provider'] ?? $provider;
        $cost = self::SERP_PRICING[$actualProvider] ?? 0.005;
        $this->trackUsage($tenant, 'serp_query', 1, $cost);
        
        return new \WP_REST_Response([
            'success' => true,
            'results' => $result,
            'usage' => [
                'cost' => $cost,
            ]
        ], 200);
    }

    /**
     * Handle SERP rank-check request.
     * Returns the position of a target URL in the SERP (0 if not found).
     */
    public function handleSerpRankCheck(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);
        
        $body = $request->get_json_params();
        $keyword = sanitize_text_field($body['keyword'] ?? '');
        $targetUrl = sanitize_text_field($body['target_url'] ?? '');
        $country = sanitize_text_field($body['country'] ?? 'us');

        if (empty($keyword) || empty($targetUrl)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_params',
                'message' => __('Keyword and target_url are required', 'sseo-ai-saas')
            ], 400);
        }

        $location = $this->countryToLocation($country);
        $result = $this->fetchSerpData($this->settings->getSerpApiProvider(), $this->settings->getSerpApiKey(), $keyword, $location, true, $country);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'serp_request_failed',
                'message' => $result->get_error_message()
            ], 502);
        }

        $position = $this->findUrlPosition($result, $targetUrl);
        $provider = $result['_provider'] ?? $this->settings->getSerpApiProvider();
        $cost = self::SERP_PRICING[$provider] ?? 0.005;
        $this->trackUsage($tenant, 'serp_query', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'position' => $position,
            'provider' => $provider,
            'checked_at' => current_time('mysql'),
            'usage' => [
                'cost' => $cost,
            ]
        ], 200);
    }

    /**
     * Handle local pack / Google Maps SERP request around GPS coordinates.
     *
     * Body: { keyword, latitude, longitude, radius, language, country, search_type: 'maps'|'local_finder' }
     */
    public function handleLocalPackRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $keyword = sanitize_text_field($body['keyword'] ?? '');
        $lat = filter_var($body['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $lng = filter_var($body['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $radius = filter_var($body['radius'] ?? 0, FILTER_VALIDATE_FLOAT);
        $country = sanitize_text_field($body['country'] ?? 'nl');
        $language = $this->countryToLanguage(sanitize_text_field($body['language'] ?? $country));
        $searchType = sanitize_text_field($body['search_type'] ?? 'maps');
        $targetName = sanitize_text_field($body['target_business_name'] ?? '');

        if (empty($keyword) || $lat === false || $lng === false || $radius <= 0) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_params',
                'message' => __('Keyword, latitude, longitude and radius are required', 'sseo-ai-saas')
            ], 400);
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_coordinates',
                'message' => __('Invalid latitude or longitude', 'sseo-ai-saas')
            ], 400);
        }

        $result = $this->fetchLocalPackData($keyword, $lat, $lng, $radius, $language, $country, $searchType);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'serp_request_failed',
                'message' => $result->get_error_message()
            ], 502);
        }

        $provider = $result['_provider'] ?? $this->settings->getSerpApiProvider();
        $cost = self::SERP_PRICING[$provider] ?? 0.005;
        $this->trackUsage($tenant, 'serp_query', 1, $cost);

        $items = $this->filterLocalPackByDistance($result['items'] ?? [], $lat, $lng, $radius);

        // Detect own business position if name provided
        $ownPosition = 0;
        if (!empty($targetName)) {
            foreach ($items as $index => $item) {
                if (isset($item['title']) && str_contains(strtolower($item['title']), strtolower($targetName))) {
                    $ownPosition = $index + 1;
                    break;
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'results' => $items,
            'center' => ['lat' => $lat, 'lng' => $lng, 'radius_km' => $radius],
            'own_position' => $ownPosition,
            'result_count' => count($items),
            'provider' => $provider,
            'usage' => ['cost' => $cost],
            'checked_at' => current_time('mysql'),
        ], 200);
    }

    /**
     * Handle local SERP geo-grid scan for Agency/Enterprise tiers.
     *
     * Body: { keyword, latitude, longitude, radius, grid_size: 3|5|7|9, language, country, search_type }
     */
    public function handleLocalGridRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $keyword = sanitize_text_field($body['keyword'] ?? '');
        $lat = filter_var($body['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $lng = filter_var($body['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $radius = filter_var($body['radius'] ?? 0, FILTER_VALIDATE_FLOAT);
        $gridSize = (int) ($body['grid_size'] ?? 3);
        $country = sanitize_text_field($body['country'] ?? 'nl');
        $language = $this->countryToLanguage(sanitize_text_field($body['language'] ?? $country));
        $searchType = sanitize_text_field($body['search_type'] ?? 'maps');
        $targetName = sanitize_text_field($body['target_business_name'] ?? '');

        if (empty($keyword) || $lat === false || $lng === false || $radius <= 0) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'missing_params',
                'message' => __('Keyword, latitude, longitude and radius are required', 'sseo-ai-saas')
            ], 400);
        }

        $allowedGrids = [3, 5, 7, 9];
        if (!in_array($gridSize, $allowedGrids, true)) {
            $gridSize = 3;
        }

        $points = $this->generateGridPoints($lat, $lng, $radius, $gridSize);
        $allItems = [];
        $totalCost = 0.0;
        $providers = [];

        foreach ($points as $point) {
            $result = $this->fetchLocalPackData($keyword, $point['lat'], $point['lng'], $radius, $language, $country, $searchType);
            if (is_wp_error($result)) {
                continue;
            }
            $provider = $result['_provider'] ?? $this->settings->getSerpApiProvider();
            $providers[$provider] = true;
            $totalCost += self::SERP_PRICING[$provider] ?? 0.005;

            $filtered = $this->filterLocalPackByDistance($result['items'] ?? [], $point['lat'], $point['lng'], $radius * 1.5);
            foreach ($filtered as $item) {
                $key = $item['place_id'] ?? $item['title'] ?? md5($item['url'] ?? '');
                if (!isset($allItems[$key])) {
                    $item['points_seen'] = 0;
                    $allItems[$key] = $item;
                }
                $allItems[$key]['points_seen']++;
            }
        }

        $this->trackUsage($tenant, 'serp_query', count($points), $totalCost);

        $items = $this->filterLocalPackByDistance(array_values($allItems), $lat, $lng, $radius);
        usort($items, fn($a, $b) => ($b['points_seen'] ?? 0) <=> ($a['points_seen'] ?? 0));

        $ownPresence = 0;
        $ownBestPosition = 0;
        if (!empty($targetName)) {
            foreach ($items as $index => $item) {
                if (isset($item['title']) && str_contains(strtolower($item['title']), strtolower($targetName))) {
                    $ownPresence = $item['points_seen'] ?? 0;
                    $ownBestPosition = $index + 1;
                    break;
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'results' => array_slice($items, 0, 100),
            'center' => ['lat' => $lat, 'lng' => $lng, 'radius_km' => $radius],
            'grid_size' => $gridSize,
            'points_scanned' => count($points),
            'own_presence' => $ownPresence,
            'own_best_position' => $ownBestPosition,
            'provider' => implode(',', array_keys($providers)) ?: 'unknown',
            'usage' => ['cost' => $totalCost],
            'checked_at' => current_time('mysql'),
        ], 200);
    }

    /**
     * Fetch local pack data from the configured provider with fallback.
     */
    private function fetchLocalPackData(string $keyword, float $lat, float $lng, float $radius, string $language, string $country, string $searchType): array|\WP_Error
    {
        $provider = $this->settings->getSerpApiProvider();
        // Local pack only supports dataforseo and serpapi; seranking has no GPS/radius local pack
        $providers = in_array($provider, self::LOCAL_PACK_FALLBACK_PROVIDERS, true) ? [$provider] : ['dataforseo'];
        foreach (self::LOCAL_PACK_FALLBACK_PROVIDERS as $p) {
            if ($p !== $provider && !in_array($p, $providers, true)) {
                $providers[] = $p;
            }
        }

        $lastError = null;
        foreach ($providers as $p) {
            if ($this->isProviderCircuitOpen($p)) {
                $lastError = new \WP_Error('circuit_open', sprintf(__('SERP provider %s is temporarily skipped due to recent failures', 'sseo-ai-saas'), $p));
                continue;
            }

            for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
                $result = $this->fetchLocalPackFromProvider($p, $keyword, $lat, $lng, $radius, $language, $country, $searchType);
                if (!is_wp_error($result)) {
                    $this->recordProviderSuccess($p);
                    return $result;
                }
                $this->recordProviderFailure($p);
                $lastError = $result;
                if ($attempt < self::MAX_RETRIES) {
                    usleep(min(4000000, 1000000 * (2 ** ($attempt - 1))));
                }
            }
        }

        return $lastError ?: new \WP_Error('serp_failed', __('All SERP providers failed', 'sseo-ai-saas'));
    }

    /**
     * Fetch local pack results from a specific provider.
     */
    private function fetchLocalPackFromProvider(string $provider, string $keyword, float $lat, float $lng, float $radius, string $language, string $country, string $searchType): array|\WP_Error
    {
        $apiKey = $this->getProviderApiKey($provider, $this->settings->getSerpApiKey());

        switch ($provider) {
            case 'dataforseo':
                return $this->fetchDataForSeoLocalPack($apiKey, $keyword, $lat, $lng, $radius, $language, $searchType);
            case 'serpapi':
                return $this->fetchSerpApiLocalPack($apiKey, $keyword, $lat, $lng, $radius, $language, $country);
            case 'seranking':
                // SE Ranking does not support GPS/radius local pack
                return new \WP_Error('seranking_no_local_pack', __('SE Ranking does not support GPS/radius local pack scans', 'sseo-ai-saas'));
            default:
                return new \WP_Error('unknown_provider', __('Unknown SERP provider', 'sseo-ai-saas'));
        }
    }

    /**
     * Fetch local pack from DataForSEO (maps or local_finder endpoint).
     */
    private function fetchDataForSeoLocalPack(string $apiKey, string $keyword, float $lat, float $lng, float $radius, string $language, string $searchType): array|\WP_Error
    {
        if (!$this->dataForSeoClient || !$this->dataForSeoClient->isConfigured()) {
            return new \WP_Error('dataforseo_not_configured', __('DataForSEO API is not configured', 'sseo-ai-saas'));
        }

        $zoom = DataForSeoClient::radiusToZoom($radius, $lat);
        $coordinate = sprintf('%.7f,%.7f,%dz', $lat, $lng, $zoom);

        if ($searchType === 'local_finder') {
            $items = $this->dataForSeoClient->serpGoogleLocalFinderLiveAdvanced($keyword, $coordinate, $language);
        } else {
            $items = $this->dataForSeoClient->serpGoogleMapsLiveAdvanced($keyword, $coordinate, $language, true);
        }

        if (is_wp_error($items)) {
            return $items;
        }

        return [
            '_provider' => 'dataforseo',
            'items' => $this->normalizeLocalPackItems($items, 'dataforseo'),
        ];
    }

    /**
     * Fetch local pack from SerpAPI (Google Maps engine with radius in metres).
     */
    private function fetchSerpApiLocalPack(string $apiKey, string $keyword, float $lat, float $lng, float $radius, string $language, string $country): array|\WP_Error
    {
        $locale = $this->getSerpApiLocaleByCountry($country);
        $radiusMeters = min(15028132, max(1, (int) round($radius * 1000)));

        $url = add_query_arg([
            'engine' => 'google_maps',
            'q' => $keyword,
            'lat' => $lat,
            'lon' => $lng,
            'm' => $radiusMeters,
            'hl' => $language,
            'gl' => $locale['gl'] ?? $country,
            'api_key' => $apiKey,
            'output' => 'json',
        ], 'https://serpapi.com/search');

        $response = wp_remote_get($url, ['timeout' => 60]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || !empty($body['error'])) {
            $message = $body['error'] ?? __('Unknown SerpApi error', 'sseo-ai-saas');
            return new \WP_Error('serpapi_error', is_string($message) ? $message : json_encode($message));
        }

        $items = $body['local_results'] ?? $body['maps_results'] ?? [];
        return [
            '_provider' => 'serpapi',
            'items' => $this->normalizeLocalPackItems($items, 'serpapi'),
        ];
    }

    /**
     * Normalize items from DataForSEO and SerpAPI into a common local pack shape.
     */
    private function normalizeLocalPackItems(array $items, string $source): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $title = sanitize_text_field($item['title'] ?? '');
            if (empty($title)) {
                continue;
            }

            $url = '';
            if (!empty($item['url'])) {
                $url = esc_url_raw($item['url']);
            } elseif (!empty($item['links']['website'])) {
                $url = esc_url_raw($item['links']['website']);
            } elseif (!empty($item['place_id_search'])) {
                $url = esc_url_raw($item['place_id_search']);
            }

            $rating = null;
            if (isset($item['rating']['value'])) {
                $rating = (float) $item['rating']['value'];
            } elseif (isset($item['rating'])) {
                $rating = (float) $item['rating'];
            }

            $reviews = null;
            if (isset($item['rating']['votes_count'])) {
                $reviews = (int) $item['rating']['votes_count'];
            } elseif (isset($item['reviews'])) {
                $reviews = (int) $item['reviews'];
            }

            $address = '';
            if (!empty($item['address'])) {
                $address = sanitize_text_field($item['address']);
            } elseif (!empty($item['address_info']['address'])) {
                $address = sanitize_text_field($item['address_info']['address']);
            }

            $gps = null;
            if (!empty($item['gps_coordinates']['latitude']) && !empty($item['gps_coordinates']['longitude'])) {
                $gps = [
                    'lat' => (float) $item['gps_coordinates']['latitude'],
                    'lng' => (float) $item['gps_coordinates']['longitude'],
                ];
            } elseif (isset($item['latitude']) && isset($item['longitude'])) {
                $gps = [
                    'lat' => (float) $item['latitude'],
                    'lng' => (float) $item['longitude'],
                ];
            }

            $placeId = sanitize_text_field($item['place_id'] ?? $item['data_id'] ?? $item['data_cid'] ?? '');

            $normalized[] = [
                'position' => (int) ($item['position'] ?? $item['rank_group'] ?? $item['rank_absolute'] ?? 0),
                'title' => $title,
                'url' => $url,
                'place_id' => $placeId,
                'type' => sanitize_text_field($item['type'] ?? $item['type_id'] ?? 'local'),
                'rating' => $rating,
                'reviews' => $reviews,
                'address' => $address,
                'gps' => $gps,
                'source' => $source,
                'thumbnail' => esc_url_raw($item['thumbnail'] ?? $item['serpapi_thumbnail'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * Filter local pack items by real distance from the center point.
     */
    private function filterLocalPackByDistance(array $items, float $centerLat, float $centerLng, float $maxKm): array
    {
        return array_values(array_filter($items, function ($item) use ($centerLat, $centerLng, $maxKm) {
            if (empty($item['gps']['lat']) || empty($item['gps']['lng'])) {
                // Keep items without coordinates; they cannot be distance-filtered
                return true;
            }
            return $this->haversineDistance($centerLat, $centerLng, $item['gps']['lat'], $item['gps']['lng']) <= $maxKm;
        }));
    }

    /**
     * Calculate great-circle distance between two coordinates in kilometres.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Generate a square grid of GPS points inside a circle.
     */
    private function generateGridPoints(float $centerLat, float $centerLng, float $radiusKm, int $gridSize): array
    {
        $points = [];
        $step = 2 * $radiusKm / max(1, $gridSize - 1);

        for ($i = 0; $i < $gridSize; $i++) {
            for ($j = 0; $j < $gridSize; $j++) {
                $x = -$radiusKm + $i * $step;
                $y = -$radiusKm + $j * $step;
                $distance = sqrt($x * $x + $y * $y);
                if ($distance > $radiusKm) {
                    continue;
                }
                $points[] = $this->offsetCoordinate($centerLat, $centerLng, $x, $y);
            }
        }

        // Always include the center point if grid was too coarse to produce it
        if (empty($points)) {
            $points[] = ['lat' => $centerLat, 'lng' => $centerLng];
        }

        return $points;
    }

    /**
     * Offset a coordinate by an east/west and north/south distance in kilometres.
     */
    private function offsetCoordinate(float $lat, float $lng, float $eastKm, float $northKm): array
    {
        $latDelta = ($northKm / 111.0);
        $lngDelta = ($eastKm / (111.32 * cos(deg2rad($lat))));
        return [
            'lat' => round($lat + $latDelta, 7),
            'lng' => round($lng + $lngDelta, 7),
        ];
    }

    /**
     * Map a country code to a valid Google/DataForSEO language code.
     */
    private function countryToLanguage(string $country): string
    {
        $map = [
            'nl' => 'nl',
            'be' => 'nl',
            'de' => 'de',
            'fr' => 'fr',
            'gb' => 'en',
            'uk' => 'en',
            'us' => 'en',
            'ca' => 'en',
            'au' => 'en',
            'es' => 'es',
            'it' => 'it',
        ];
        return $map[strtolower($country)] ?? 'en';
    }

    /**
     * Helper for SerpAPI locale (reuses existing logic but keyed by country code).
     */
    private function getSerpApiLocaleByCountry(string $country): array
    {
        $map = [
            'nl' => ['domain' => 'google.nl', 'gl' => 'nl', 'hl' => 'nl'],
            'be' => ['domain' => 'google.be', 'gl' => 'be', 'hl' => 'nl'],
            'de' => ['domain' => 'google.de', 'gl' => 'de', 'hl' => 'de'],
            'fr' => ['domain' => 'google.fr', 'gl' => 'fr', 'hl' => 'fr'],
            'gb' => ['domain' => 'google.co.uk', 'gl' => 'gb', 'hl' => 'en'],
            'uk' => ['domain' => 'google.co.uk', 'gl' => 'gb', 'hl' => 'en'],
            'us' => ['domain' => 'google.com', 'gl' => 'us', 'hl' => 'en'],
            'ca' => ['domain' => 'google.ca', 'gl' => 'ca', 'hl' => 'en'],
            'au' => ['domain' => 'google.com.au', 'gl' => 'au', 'hl' => 'en'],
            'es' => ['domain' => 'google.es', 'gl' => 'es', 'hl' => 'es'],
            'it' => ['domain' => 'google.it', 'gl' => 'it', 'hl' => 'it'],
        ];
        return $map[strtolower($country)] ?? ['domain' => 'google.com', 'gl' => 'us', 'hl' => 'en'];
    }

    /**
     * Get usage status for tenant
     */
    public function getUsageStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);
        
        $tier = $tenant['tier'];
        $apiLimit = (int)$tenant['api_calls_limit'] ?: $this->settings->getApiLimitForTier($tier);
        $costLimit = $this->settings->getCostLimitForTier($tier);
        
        global $wpdb;
        $tableUsage = $wpdb->prefix . 'sseo_ai_tenant_usage';
        $currentMonth = current_time('Y-m');
        
        $usage = $wpdb->get_row($wpdb->prepare(
            "SELECT
                api_calls as calls,
                api_cost as cost,
                serp_requests as serp_calls,
                content_generated,
                ai_mentions,
                llm_response_calls,
                trends_requests,
                backlinks_requests
             FROM {$tableUsage}
             WHERE tenant_id = %d AND period = %s",
            $tenant['id'],
            $currentMonth
        ));

        return new \WP_REST_Response([
            'success' => true,
            'tier' => $tier,
            'limits' => [
                'api_calls' => $apiLimit,
                'api_cost' => $costLimit,
            ],
            'usage' => [
                'api_calls' => (int)($usage->calls ?? 0),
                'api_cost' => (float)($usage->cost ?? 0),
                'serp_calls' => (int)($usage->serp_calls ?? 0),
                'content_generated' => (int)($usage->content_generated ?? 0),
                'ai_mentions' => (int)($usage->ai_mentions ?? 0),
                'llm_response_calls' => (int)($usage->llm_response_calls ?? 0),
                'trends_requests' => (int)($usage->trends_requests ?? 0),
                'backlinks_requests' => (int)($usage->backlinks_requests ?? 0),
            ],
            'remaining' => [
                'api_calls' => max(0, $apiLimit - (int)($usage->calls ?? 0)),
                'api_cost' => max(0, $costLimit - (float)($usage->cost ?? 0)),
            ]
        ], 200);
    }

    /**
     * Track usage for tenant
     */
    private function trackUsage(array $tenant, string $metric, int $count, float $cost): void
    {
        global $wpdb;
        $tenantId = (int)$tenant['id'];
        $tableUsage = $wpdb->prefix . 'sseo_ai_tenant_usage';
        $period = current_time('Y-m');

        // Upsert: update existing period row or insert new one
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tableUsage} WHERE tenant_id = %d AND period = %s",
            $tenantId,
            $period
        ));

        if ($existing) {
            // Determine which column to increment based on metric
            $column = match ($metric) {
                'serp_query'        => 'serp_requests',
                'content_generated' => 'content_generated',
                'ai_mention'        => 'ai_mentions',
                'llm_response'      => 'llm_response_calls',
                'trends'            => 'trends_requests',
                'backlinks'         => 'backlinks_requests',
                default             => 'api_calls',
            };

            $wpdb->query($wpdb->prepare(
                "UPDATE {$tableUsage} SET {$column} = {$column} + %d, api_cost = api_cost + %f WHERE id = %d",
                $count,
                $cost,
                $existing
            ));
        } else {
            $data = [
                'tenant_id'           => $tenantId,
                'period'              => $period,
                'api_calls'           => in_array($metric, ['ai_generation', 'ai_keyword'], true) ? $count : 0,
                'api_cost'            => $cost,
                'serp_requests'       => ($metric === 'serp_query') ? $count : 0,
                'content_generated'   => ($metric === 'content_generated') ? $count : 0,
                'keywords_tracked'    => 0,
                'ai_mentions'         => ($metric === 'ai_mention') ? $count : 0,
                'llm_response_calls'  => ($metric === 'llm_response') ? $count : 0,
                'trends_requests'     => ($metric === 'trends') ? $count : 0,
                'backlinks_requests'  => ($metric === 'backlinks') ? $count : 0,
            ];
            $wpdb->insert($tableUsage, $data);
        }

        $this->maybeNotifyUsageLimit($tenant);
    }

    /**
     * Fire usage limit hook once per tenant/period when the limit is reached.
     */
    private function maybeNotifyUsageLimit(array $tenant): void
    {
        $tenantKey = $tenant['tenant_key'] ?? '';
        if (empty($tenantKey)) {
            return;
        }

        $period = current_time('Y-m');
        $transientKey = 'sseo_ai_usage_limit_notified_' . $tenantKey . '_' . $period;
        if (get_transient($transientKey)) {
            return;
        }

        if (!$this->isOverLimit($tenant)) {
            return;
        }

        $apiLimit = (int)$tenant['api_calls_limit'] ?: $this->settings->getApiLimitForTier($tenant['tier']);

        do_action('sseo_ai_usage_limit_reached', $tenantKey, [
            'limit' => $apiLimit,
            'used' => $apiLimit,
        ]);

        set_transient($transientKey, true, MONTH_IN_SECONDS);
    }

    // -------------------------------------------------------------------------
    // DataForSEO proxy endpoints (AI Optimization, Keywords Data, Backlinks)
    // -------------------------------------------------------------------------

    /**
     * Get the DataForSeoClient instance or a 503 error response.
     */
    private function dataForSeoOrError(): \WP_REST_Response|null
    {
        if (!$this->dataForSeoClient || !$this->dataForSeoClient->isConfigured()) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'dataforseo_not_configured',
                'message' => __('DataForSEO API is not configured', 'sseo-ai-saas'),
            ], 503);
        }
        return null;
    }

    /**
     * Handle DataForSEO AI Optimization — LLM Mentions requests.
     *
     * Body: { action: search_mentions|target_metrics|top_domains|top_pages|top_brands|top_brand_categories|historical|timeseries_delta|timeseries_new_and_lost, ...params }
     */
    public function handleAiLlmMentions(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $action = sanitize_text_field($body['action'] ?? 'search_mentions');
        $params = $body['params'] ?? [];

        if (!is_array($params)) {
            $params = [];
        }

        $result = match ($action) {
            'search_mentions'           => $this->dataForSeoClient->aiMentionsSearchMentions($params),
            'target_metrics'            => $this->dataForSeoClient->aiMentionsTargetMetrics($params),
            'top_domains'               => $this->dataForSeoClient->aiMentionsTopMentionedDomains($params),
            'top_pages'                 => $this->dataForSeoClient->aiMentionsTopMentionedPages($params),
            'top_brands'                => $this->dataForSeoClient->aiMentionsTopMentionedBrands($params),
            'top_brand_categories'      => $this->dataForSeoClient->aiMentionsTopMentionedBrandCategories($params),
            'historical'                => $this->dataForSeoClient->aiMentionsHistorical($params),
            'timeseries_delta'          => $this->dataForSeoClient->aiMentionsTimeseriesDelta($params),
            'timeseries_new_and_lost'   => $this->dataForSeoClient->aiMentionsTimeseriesNewAndLost($params),
            default => new \WP_Error('invalid_action', sprintf(__('Unknown LLM Mentions action: %s', 'sseo-ai-saas'), $action)),
        };

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['ai_mentions'];
        $this->trackUsage($tenant, 'ai_mention', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'action'  => $action,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Handle DataForSEO AI Optimization — AI Keyword Data (search volume).
     *
     * Body: { keywords: [...], location_code, language_code }
     */
    public function handleAiKeywordData(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $params = [
            'keywords' => array_filter(array_map('sanitize_text_field', $body['keywords'] ?? [])),
        ];
        if (!empty($body['location_code'])) {
            $params['location_code'] = (int) $body['location_code'];
        }
        if (!empty($body['language_code'])) {
            $params['language_code'] = sanitize_text_field($body['language_code']);
        }

        if (empty($params['keywords'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('keywords array is required', 'sseo-ai-saas'),
            ], 400);
        }

        $result = $this->dataForSeoClient->aiKeywordDataSearchVolume($params);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['ai_keyword'];
        $this->trackUsage($tenant, 'ai_keyword', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Handle DataForSEO AI Optimization — LLM Responses (ChatGPT/Claude/Gemini/Perplexity).
     *
     * Body: { provider: chatgpt|claude|gemini|perplexity, prompt, model?, ... }
     */
    public function handleAiLlmResponse(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $provider = sanitize_text_field($body['provider'] ?? 'chatgpt');
        $prompt = sanitize_textarea_field($body['prompt'] ?? '');

        if (empty($prompt)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('prompt is required', 'sseo-ai-saas'),
            ], 400);
        }

        $params = ['prompt' => $prompt];
        if (!empty($body['model'])) {
            $params['model'] = sanitize_text_field($body['model']);
        }
        if (!empty($body['location_code'])) {
            $params['location_code'] = (int) $body['location_code'];
        }
        if (!empty($body['language_code'])) {
            $params['language_code'] = sanitize_text_field($body['language_code']);
        }

        $result = $this->dataForSeoClient->llmResponseLive($provider, $params);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['llm_response'];
        $this->trackUsage($tenant, 'llm_response', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'provider'=> $provider,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Handle DataForSEO Keywords Data — Google Trends Explore.
     *
     * Body: { keywords: [...], time_range?, location_code?, ... }
     */
    public function handleGoogleTrends(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $params = [
            'keywords' => array_filter(array_map('sanitize_text_field', $body['keywords'] ?? [])),
        ];
        if (!empty($body['time_range'])) {
            $params['time_range'] = sanitize_text_field($body['time_range']);
        }
        if (!empty($body['location_code'])) {
            $params['location_code'] = (int) $body['location_code'];
        }
        if (!empty($body['type'])) {
            $params['type'] = sanitize_text_field($body['type']);
        }

        if (empty($params['keywords'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('keywords array is required', 'sseo-ai-saas'),
            ], 400);
        }

        $result = $this->dataForSeoClient->googleTrendsExploreLive($params);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['google_trends'];
        $this->trackUsage($tenant, 'trends', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Handle DataForSEO Keywords Data — DataForSEO Trends Explore.
     *
     * Body: { keywords: [...], location_code?, ... }
     */
    public function handleDataForSeoTrends(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $params = [
            'keywords' => array_filter(array_map('sanitize_text_field', $body['keywords'] ?? [])),
        ];
        if (!empty($body['location_code'])) {
            $params['location_code'] = (int) $body['location_code'];
        }

        if (empty($params['keywords'])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('keywords array is required', 'sseo-ai-saas'),
            ], 400);
        }

        $result = $this->dataForSeoClient->dataforseoTrendsExploreLive($params);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['dfs_trends'];
        $this->trackUsage($tenant, 'trends', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Handle DataForSEO Backlinks — Summary.
     *
     * Body: { target: "example.com" }
     */
    public function handleBacklinksSummary(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $target = sanitize_text_field($body['target'] ?? '');

        if (empty($target)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('target domain is required', 'sseo-ai-saas'),
            ], 400);
        }

        $result = $this->dataForSeoClient->backlinksSummaryLive($target);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['backlinks'];
        $this->trackUsage($tenant, 'backlinks', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Handle DataForSEO Backlinks — Live backlinks list.
     *
     * Body: { target: "example.com", limit?, filters?, ... }
     */
    public function handleBacklinksLive(\WP_REST_Request $request): \WP_REST_Response
    {
        $err = $this->dataForSeoOrError();
        if ($err) { return $err; }

        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getTenant($tenantKey);

        $body = $request->get_json_params();
        $target = sanitize_text_field($body['target'] ?? '');

        if (empty($target)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('target domain is required', 'sseo-ai-saas'),
            ], 400);
        }

        $params = [];
        if (!empty($body['limit'])) {
            $params['limit'] = (int) $body['limit'];
        }
        if (!empty($body['offset'])) {
            $params['offset'] = (int) $body['offset'];
        }
        if (!empty($body['order_by'])) {
            $params['order_by'] = sanitize_text_field($body['order_by']);
        }
        if (!empty($body['filters']) && is_array($body['filters'])) {
            $params['filters'] = $body['filters'];
        }

        $result = $this->dataForSeoClient->backlinksLive($target, $params);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        $cost = DataForSeoClient::PRICING['backlinks'];
        $this->trackUsage($tenant, 'backlinks', 1, $cost);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
            'usage'   => ['cost' => $cost],
        ], 200);
    }

    /**
     * Fetch SERP data from provider with optional fallback, retry and circuit breaker.
     */
    private function fetchSerpData(string $provider, string $apiKey, string $keyword, string $location, bool $withFallback = false, string $countryCode = ''): array|\WP_Error
    {
        $providers = [$provider];
        if ($withFallback) {
            foreach (self::FALLBACK_PROVIDERS as $p) {
                if ($p !== $provider && !in_array($p, $providers, true)) {
                    $providers[] = $p;
                }
            }
        }

        $lastError = null;
        foreach ($providers as $p) {
            if ($this->isProviderCircuitOpen($p)) {
                $lastError = new \WP_Error('circuit_open', sprintf(__('SERP provider %s is temporarily skipped due to recent failures', 'sseo-ai-saas'), $p));
                continue;
            }

            for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
                $result = $this->fetchSerpFromProvider($p, $apiKey, $keyword, $location, $countryCode);
                if (!is_wp_error($result)) {
                    $this->recordProviderSuccess($p);
                    $result['_provider'] = $p;
                    return $result;
                }
                $this->recordProviderFailure($p);
                $lastError = $result;
                if ($attempt < self::MAX_RETRIES) {
                    usleep(min(4000000, 1000000 * (2 ** ($attempt - 1))));
                }
            }
        }

        return $lastError ?: new \WP_Error('serp_failed', __('All SERP providers failed', 'sseo-ai-saas'));
    }

    /**
     * Dispatch a single SERP provider request.
     */
    private function fetchSerpFromProvider(string $provider, string $apiKey, string $keyword, string $location, string $countryCode = ''): array|\WP_Error
    {
        $providerKey = $this->getProviderApiKey($provider, $apiKey);
        switch ($provider) {
            case 'dataforseo':
                return $this->fetchDataForSeo($providerKey, $keyword, $location, $countryCode);
            case 'serpapi':
                return $this->fetchSerpApi($providerKey, $keyword, $location, $countryCode);
            case 'seranking':
                return $this->fetchSerankingSerp($providerKey, $keyword, $location, $countryCode);
            default:
                return new \WP_Error('unknown_provider', __('Unknown SERP provider', 'sseo-ai-saas'));
        }
    }

    /**
     * Fetch from DataForSEO.
     *
     * Uses the central DataForSeoClient when available (preferred), falling
     * back to the inline implementation for backward compatibility when the
     * client was not injected.
     */
    private function fetchDataForSeo(string $apiKey, string $keyword, string $location, string $countryCode = ''): array|\WP_Error
    {
        // Prefer the central DataForSeoClient when injected and configured
        if ($this->dataForSeoClient && $this->dataForSeoClient->isConfigured()) {
            return $this->dataForSeoClient->serpOrganicLiveAdvanced(
                $keyword,
                $this->getLocationCode($location),
                'en',
                $countryCode
            );
        }

        // Inline fallback (legacy path)
        $response = wp_remote_post('https://api.dataforseo.com/v3/serp/google/organic/live/advanced', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($apiKey),
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                [
                    'keyword' => $keyword,
                    'location_code' => $this->getLocationCode($location),
                    'language_code' => 'en',
                ]
            ]),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['tasks'][0]['result'][0]['items'] ?? [];
    }

    /**
     * Fetch from SerpApi
     */
    private function fetchSerpApi(string $apiKey, string $keyword, string $location, string $countryCode = ''): array|\WP_Error
    {
        $locale = $this->getSerpApiLocale($location);

        $url = add_query_arg([
            'q' => $keyword,
            'location' => $location,
            'google_domain' => $locale['domain'],
            'gl' => $locale['gl'],
            'hl' => $locale['hl'],
            'api_key' => $apiKey,
            'output' => 'json',
        ], 'https://serpapi.com/search');

        $response = wp_remote_get($url, ['timeout' => 60]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || !empty($body['error'])) {
            $message = $body['error'] ?? __('Unknown SerpApi error', 'sseo-ai-saas');
            return new \WP_Error('serpapi_error', is_string($message) ? $message : json_encode($message));
        }

        return $body['organic_results'] ?? [];
    }

    /**
     * Map a location string to SerpApi google_domain, gl and hl values.
     */
    private function getSerpApiLocale(string $location): array
    {
        $locationLower = strtolower($location);
        $map = [
            'netherlands' => ['domain' => 'google.nl', 'gl' => 'nl', 'hl' => 'nl'],
            'belgium' => ['domain' => 'google.be', 'gl' => 'be', 'hl' => 'nl'],
            'germany' => ['domain' => 'google.de', 'gl' => 'de', 'hl' => 'de'],
            'france' => ['domain' => 'google.fr', 'gl' => 'fr', 'hl' => 'fr'],
            'united kingdom' => ['domain' => 'google.co.uk', 'gl' => 'gb', 'hl' => 'en'],
            'united states' => ['domain' => 'google.com', 'gl' => 'us', 'hl' => 'en'],
            'canada' => ['domain' => 'google.ca', 'gl' => 'ca', 'hl' => 'en'],
            'australia' => ['domain' => 'google.com.au', 'gl' => 'au', 'hl' => 'en'],
            'spain' => ['domain' => 'google.es', 'gl' => 'es', 'hl' => 'es'],
            'italy' => ['domain' => 'google.it', 'gl' => 'it', 'hl' => 'it'],
        ];

        foreach ($map as $country => $locale) {
            if (str_contains($locationLower, $country)) {
                return $locale;
            }
        }

        return ['domain' => 'google.com', 'gl' => 'us', 'hl' => 'en'];
    }

    /**
     * Get location code (simplified)
     */
    private function getLocationCode(string $location): int
    {
        $codes = [
            'United States' => 2840,
            'United Kingdom' => 2826,
            'Netherlands' => 2528,
            'Germany' => 2276,
            'France' => 2250,
        ];
        return $codes[$location] ?? 2840;
    }

    /**
     * Map country code to a location name used by DataForSEO/SerpApi.
     */
    private function countryToLocation(string $countryCode): string
    {
        $map = [
            'us' => 'United States',
            'gb' => 'United Kingdom',
            'uk' => 'United Kingdom',
            'nl' => 'Netherlands',
            'de' => 'Germany',
            'fr' => 'France',
            'be' => 'Belgium',
            'es' => 'Spain',
            'it' => 'Italy',
            'ca' => 'Canada',
            'au' => 'Australia',
        ];
        return $map[strtolower($countryCode)] ?? 'United States';
    }

    /**
     * Find the position of a target URL in SERP results.
     */
    private function findUrlPosition(array $results, string $targetUrl): int
    {
        $targetHost = strtolower(parse_url($targetUrl, PHP_URL_HOST) ?: '');
        $targetPath = parse_url($targetUrl, PHP_URL_PATH) ?: '/';

        foreach ($results as $index => $item) {
            $url = $item['url'] ?? $item['link'] ?? $item['target_url'] ?? '';
            if (empty($url) || !is_string($url)) {
                continue;
            }
            $itemHost = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
            $itemPath = parse_url($url, PHP_URL_PATH) ?: '/';
            if (strtolower($url) === strtolower($targetUrl) || ($itemHost === $targetHost && $itemPath === $targetPath)) {
                return $index + 1;
            }
        }

        return 0;
    }

    /**
     * Fetch SERP from SE Ranking (asynchronous task-based).
     */
    private function fetchSerankingSerp(string $apiKey, string $keyword, string $location, string $countryCode = ''): array|\WP_Error
    {
        $source = strtolower($countryCode ?: $this->locationToCountryCode($location));

        $locationIds = [
            'us' => 2840, 'uk' => 2826, 'gb' => 2826, 'nl' => 2740, 'de' => 2756,
            'fr' => 2750, 'be' => 2710, 'es' => 2764, 'it' => 2774, 'au' => 2674,
            'ca' => 2700, 'br' => 2670,
        ];
        $locationId = $locationIds[$source] ?? 0;

        if (!$locationId) {
            return new \WP_Error('seranking_location', sprintf(__('No SE Ranking location for country %s', 'sseo-ai-saas'), $source));
        }

        $taskResponse = wp_remote_post('https://api.seranking.com/v1/serp/classic/tasks', [
            'headers' => [
                'Authorization' => 'Token ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => json_encode([
                'query' => $keyword,
                'location_id' => $locationId,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($taskResponse)) {
            return $taskResponse;
        }

        $taskBody = json_decode(wp_remote_retrieve_body($taskResponse), true);
        $taskId = $taskBody['id'] ?? $taskBody['task_id'] ?? '';

        if (empty($taskId)) {
            return new \WP_Error('seranking_task', __('Could not create SE Ranking SERP task', 'sseo-ai-saas'));
        }

        $maxAttempts = 15;
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(1);
            $resultResponse = wp_remote_get('https://api.seranking.com/v1/serp/classic/tasks?id=' . urlencode($taskId), [
                'headers' => [
                    'Authorization' => 'Token ' . $apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($resultResponse)) {
                continue;
            }

            $resultBody = json_decode(wp_remote_retrieve_body($resultResponse), true);
            $status = $resultBody['status'] ?? $resultBody['state'] ?? '';

            if (in_array($status, ['finished', 'completed', 'done'], true)) {
                $results = $resultBody['result'] ?? $resultBody['data'] ?? [];
                $organic = $results['organic'] ?? $results['items'] ?? $results ?? [];
                $items = [];
                foreach ($organic as $item) {
                    $items[] = [
                        'url' => $item['url'] ?? $item['target'] ?? '',
                        'title' => $item['title'] ?? '',
                    ];
                }
                return $items;
            }

            if (in_array($status, ['failed', 'error'], true)) {
                return new \WP_Error('seranking_task_failed', __('SE Ranking SERP task failed', 'sseo-ai-saas'));
            }
        }

        return new \WP_Error('seranking_timeout', __('SE Ranking SERP task timed out', 'sseo-ai-saas'));
    }

    /**
     * Reverse-map a DataForSEO/SerpApi location to a country code.
     */
    private function locationToCountryCode(string $location): string
    {
        $map = [
            'united states' => 'us',
            'united kingdom' => 'gb',
            'netherlands' => 'nl',
            'germany' => 'de',
            'france' => 'fr',
            'belgium' => 'be',
            'spain' => 'es',
            'italy' => 'it',
            'canada' => 'ca',
            'australia' => 'au',
        ];
        return $map[strtolower($location)] ?? 'us';
    }

    /**
     * Proxy Google Places autocomplete requests from clients.
     * Keeps the Places API key on the SaaS side.
     */
    public function handlePlaceAutocomplete(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $input = sanitize_text_field($body['input'] ?? '');

        if (empty($input) || strlen($input) > 200) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_input',
                'predictions' => [],
            ], 400);
        }

        $apiKey = $this->settings->getGooglePlacesApiKey();
        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_configured',
                'predictions' => [],
            ], 503);
        }

        $cacheKey = 'sseo_saas_places_' . md5($input);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return new \WP_REST_Response(['success' => true, 'predictions' => $cached], 200);
        }

        $language = sanitize_text_field($body['language'] ?? 'nl');
        $components = sanitize_text_field($body['components'] ?? 'country:nl');
        $types = sanitize_text_field($body['types'] ?? '(cities)');

        $url = add_query_arg([
            'input' => $input,
            'key' => $apiKey,
            'types' => $types,
            'language' => $language,
            'components' => $components,
        ], 'https://maps.googleapis.com/maps/api/place/autocomplete/json');

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'google_places_error',
                'predictions' => [],
            ], 502);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data) || !isset($data['status']) || $data['status'] !== 'OK') {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $data['status'] ?? 'unknown',
                'predictions' => [],
            ], 502);
        }

        $predictions = array_map(function ($p) {
            return [
                'description' => $p['description'] ?? '',
                'place_id' => $p['place_id'] ?? '',
            ];
        }, $data['predictions'] ?? []);

        set_transient($cacheKey, $predictions, HOUR_IN_SECONDS);

        return new \WP_REST_Response(['success' => true, 'predictions' => $predictions], 200);
    }

    /**
     * Proxy Google Geocoding requests for client address autofill.
     * Takes a free-form address query and returns parsed components + coordinates.
     */
    public function handleGeocodeRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_body_params();
        $address = sanitize_text_field($body['address'] ?? '');

        if (empty($address) || strlen($address) > 250) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'invalid_address',
                'address' => [],
                'coordinates' => [],
            ], 400);
        }

        $apiKey = $this->settings->getGooglePlacesApiKey();
        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'not_configured',
                'address' => [],
                'coordinates' => [],
            ], 503);
        }

        $cacheKey = 'sseo_saas_geocode_' . md5($address);
        $cached = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['success'])) {
            return new \WP_REST_Response($cached, 200);
        }

        $url = add_query_arg([
            'address' => $address,
            'key' => $apiKey,
            'language' => 'nl',
            'region' => 'nl',
            'components' => 'country:NL',
        ], 'https://maps.googleapis.com/maps/api/geocode/json');

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'geocode_error',
                'address' => [],
                'coordinates' => [],
            ], 502);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data) || !isset($data['status']) || $data['status'] !== 'OK' || empty($data['results'][0])) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $data['status'] ?? 'unknown',
                'address' => [],
                'coordinates' => [],
            ], 502);
        }

        $result = $data['results'][0];
        $components = $this->parseGeocodeComponents($result['address_components'] ?? []);
        $coordinates = [
            'lat' => $result['geometry']['location']['lat'] ?? '',
            'lng' => $result['geometry']['location']['lng'] ?? '',
        ];

        $output = [
            'success' => true,
            'address' => $components,
            'coordinates' => $coordinates,
        ];

        set_transient($cacheKey, $output, HOUR_IN_SECONDS);

        return new \WP_REST_Response($output, 200);
    }

    /**
     * Parse Google Geocoding address_components into normalized fields.
     */
    private function parseGeocodeComponents(array $addressComponents): array
    {
        $components = [
            'street' => '',
            'city' => '',
            'state' => '',
            'postal' => '',
            'country' => '',
        ];

        $streetNumber = '';
        $route = '';

        foreach ($addressComponents as $c) {
            $types = $c['types'] ?? [];
            if (in_array('street_number', $types, true)) {
                $streetNumber = $c['long_name'] ?? '';
            } elseif (in_array('route', $types, true)) {
                $route = $c['long_name'] ?? '';
            } elseif (in_array('locality', $types, true)) {
                $components['city'] = $c['long_name'] ?? '';
            } elseif (in_array('postal_town', $types, true) && empty($components['city'])) {
                $components['city'] = $c['long_name'] ?? '';
            } elseif (in_array('administrative_area_level_1', $types, true)) {
                $components['state'] = $c['long_name'] ?? '';
            } elseif (in_array('postal_code', $types, true)) {
                $components['postal'] = $c['long_name'] ?? '';
            } elseif (in_array('country', $types, true)) {
                $components['country'] = $c['short_name'] ?? '';
            }
        }

        $components['street'] = trim($streetNumber . ' ' . $route);

        return $components;
    }

    /**
     * Get the API key for a specific provider. Falls back to the default key.
     */
    private function getProviderApiKey(string $provider, string $defaultKey): string
    {
        $specific = $this->settings->getSerpApiKeyForProvider($provider);
        return !empty($specific) ? $specific : $defaultKey;
    }

    /**
     * Circuit breaker: check if a provider should be skipped.
     */
    private function isProviderCircuitOpen(string $provider, string $type = 'serp'): bool
    {
        $failures = (int) get_transient('sseo_' . $type . '_fail_' . $provider);
        return $failures >= self::CIRCUIT_BREAKER_FAILURES;
    }

    /**
     * Record a provider failure for the circuit breaker.
     */
    private function recordProviderFailure(string $provider, string $type = 'serp'): void
    {
        $key = 'sseo_' . $type . '_fail_' . $provider;
        $failures = (int) get_transient($key);
        set_transient($key, $failures + 1, self::CIRCUIT_BREAKER_WINDOW);
    }

    /**
     * Reset the circuit breaker for a provider after a successful call.
     */
    private function recordProviderSuccess(string $provider, string $type = 'serp'): void
    {
        delete_transient('sseo_' . $type . '_fail_' . $provider);
    }
}

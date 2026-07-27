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
    
    // SERP API pricing per request (approximate)
    private const SERP_PRICING = [
        'dataforseo' => 0.002,
        'serpapi' => 0.005,
        'seranking' => 0.003,
    ];

    // SERP provider fallback order
    private const FALLBACK_PROVIDERS = ['dataforseo', 'serpapi', 'seranking'];
    private const MAX_RETRIES = 3;
    private const CIRCUIT_BREAKER_FAILURES = 3;
    private const CIRCUIT_BREAKER_WINDOW = 900; // 15 minutes

    // AI request resilience
    private const AI_TIMEOUT_SECONDS = 120;
    private const AI_MAX_RETRIES = 3;
    private const AI_RETRY_BASE_MS = 500000; // 0.5 seconds

    public function __construct(TenantRepository $tenants, SaaSSettings $settings, ProviderRouter $providerRouter)
    {
        $this->tenants = $tenants;
        $this->settings = $settings;
        $this->providerRouter = $providerRouter;
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
        
        // Usage check endpoint
        register_rest_route('ai-seo-saas/v1', '/usage/check', [
            'methods' => 'GET',
            'callback' => [$this, 'getUsageStatus'],
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

        $statusCode = ($lastError && $lastError->get_error_code() === 'no_provider') ? 503 : 502;
        return new \WP_REST_Response([
            'success' => false,
            'error' => $lastError ? $lastError->get_error_code() : 'ai_request_failed',
            'message' => $lastError ? $lastError->get_error_message() : __('AI generation failed', 'sseo-ai-saas')
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
                content_generated
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
            $column = 'api_calls';
            if ($metric === 'serp_query') {
                $column = 'serp_requests';
            } elseif ($metric === 'content_generated') {
                $column = 'content_generated';
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE {$tableUsage} SET {$column} = {$column} + %d, api_cost = api_cost + %f WHERE id = %d",
                $count,
                $cost,
                $existing
            ));
        } else {
            $data = [
                'tenant_id' => $tenantId,
                'period' => $period,
                'api_calls' => ($metric === 'ai_generation') ? $count : 0,
                'api_cost' => $cost,
                'serp_requests' => ($metric === 'serp_query') ? $count : 0,
                'content_generated' => ($metric === 'content_generated') ? $count : 0,
                'keywords_tracked' => 0,
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
     * Fetch from DataForSEO
     */
    private function fetchDataForSeo(string $apiKey, string $keyword, string $location, string $countryCode = ''): array|\WP_Error
    {
        // DataForSEO implementation
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

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
    
    // OpenAI pricing per 1K tokens (approximate, update as needed)
    private const OPENAI_PRICING = [
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
    ];
    
    // SERP API pricing per request (approximate)
    private const SERP_PRICING = [
        'dataforseo' => 0.002,
        'serpapi' => 0.005,
        'seranking' => 0.003,
    ];

    public function __construct(TenantRepository $tenants, SaaSSettings $settings)
    {
        $this->tenants = $tenants;
        $this->settings = $settings;
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
        $tenant = $this->tenants->getByTenantKey($tenantKey);
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
        $tier = $tenant['license_tier'];
        $apiLimit = $this->settings->getApiLimitForTier($tier);
        $costLimit = $this->settings->getCostLimitForTier($tier);
        
        // Get current month's usage
        global $wpdb;
        $tableUsage = $wpdb->prefix . SSEO_AI_TABLE_TENANT_USAGE;
        $currentMonth = date('Y-m');
        
        $usage = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(api_calls) as calls, SUM(api_cost) as cost 
             FROM {$tableUsage} 
             WHERE tenant_id = %d AND DATE_FORMAT(created_at, '%Y-%m') = %s",
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
     */
    public function handleAiRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getByTenantKey($tenantKey);
        
        // Get OpenAI credentials
        $apiKey = $this->settings->getOpenAiApiKey();
        $model = $this->settings->getOpenAiModel();
        
        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'ai_not_configured',
                'message' => __('AI service is not configured', 'ai-seo-saas')
            ], 503);
        }
        
        // Make request to OpenAI
        $body = $request->get_json_params();
        
        $openaiResponse = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $body['messages'] ?? [],
                'temperature' => $body['temperature'] ?? 0.7,
                'max_tokens' => $body['max_tokens'] ?? 2000,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($openaiResponse)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'ai_request_failed',
                'message' => $openaiResponse->get_error_message()
            ], 502);
        }
        
        $responseBody = json_decode(wp_remote_retrieve_body($openaiResponse), true);
        
        // Calculate cost
        $inputTokens = $responseBody['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $responseBody['usage']['completion_tokens'] ?? 0;
        $cost = $this->calculateAiCost($model, $inputTokens, $outputTokens);
        
        // Track usage
        $this->trackUsage($tenant['id'], 'ai_generation', 1, $cost);
        
        return new \WP_REST_Response([
            'success' => true,
            'content' => $responseBody['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $inputTokens,
                'completion_tokens' => $outputTokens,
                'total_tokens' => $responseBody['usage']['total_tokens'] ?? 0,
                'cost' => $cost,
            ]
        ], 200);
    }

    /**
     * Handle SERP request
     */
    public function handleSerpRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_header('X-Tenant-Key') ?? $request->get_param('tenant_key');
        $tenant = $this->tenants->getByTenantKey($tenantKey);
        
        // Get SERP credentials
        $apiKey = $this->settings->getSerpApiKey();
        $provider = $this->settings->getSerpApiProvider();
        
        if (empty($apiKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'serp_not_configured',
                'message' => __('SERP service is not configured', 'ai-seo-saas')
            ], 503);
        }
        
        $body = $request->get_json_params();
        $keyword = sanitize_text_field($body['keyword'] ?? '');
        $location = sanitize_text_field($body['location'] ?? 'United States');
        
        // Route to appropriate provider
        $result = $this->fetchSerpData($provider, $apiKey, $keyword, $location);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'serp_request_failed',
                'message' => $result->get_error_message()
            ], 502);
        }
        
        // Calculate and track cost
        $cost = self::SERP_PRICING[$provider] ?? 0.005;
        $this->trackUsage($tenant['id'], 'serp_query', 1, $cost);
        
        return new \WP_REST_Response([
            'success' => true,
            'results' => $result,
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
        $tenant = $this->tenants->getByTenantKey($tenantKey);
        
        $tier = $tenant['license_tier'];
        $apiLimit = $this->settings->getApiLimitForTier($tier);
        $costLimit = $this->settings->getCostLimitForTier($tier);
        
        global $wpdb;
        $tableUsage = $wpdb->prefix . SSEO_AI_TABLE_TENANT_USAGE;
        $currentMonth = date('Y-m');
        
        $usage = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(api_calls) as calls,
                SUM(api_cost) as cost,
                SUM(CASE WHEN metric = 'ai_generation' THEN api_calls ELSE 0 END) as ai_calls,
                SUM(CASE WHEN metric = 'ai_generation' THEN api_cost ELSE 0 END) as ai_cost,
                SUM(CASE WHEN metric = 'serp_query' THEN api_calls ELSE 0 END) as serp_calls,
                SUM(CASE WHEN metric = 'serp_query' THEN api_cost ELSE 0 END) as serp_cost
             FROM {$tableUsage} 
             WHERE tenant_id = %d AND DATE_FORMAT(created_at, '%Y-%m') = %s",
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
                'ai_calls' => (int)($usage->ai_calls ?? 0),
                'ai_cost' => (float)($usage->ai_cost ?? 0),
                'serp_calls' => (int)($usage->serp_calls ?? 0),
                'serp_cost' => (float)($usage->serp_cost ?? 0),
            ],
            'remaining' => [
                'api_calls' => max(0, $apiLimit - (int)($usage->calls ?? 0)),
                'api_cost' => max(0, $costLimit - (float)($usage->cost ?? 0)),
            ]
        ], 200);
    }

    /**
     * Calculate AI cost based on tokens
     */
    private function calculateAiCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::OPENAI_PRICING[$model] ?? self::OPENAI_PRICING['gpt-3.5-turbo'];
        
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        
        return round($inputCost + $outputCost, 4);
    }

    /**
     * Track usage for tenant
     */
    private function trackUsage(int $tenantId, string $metric, int $count, float $cost): void
    {
        global $wpdb;
        $tableUsage = $wpdb->prefix . SSEO_AI_TABLE_TENANT_USAGE;
        
        $wpdb->insert($tableUsage, [
            'tenant_id' => $tenantId,
            'metric' => $metric,
            'api_calls' => $count,
            'api_cost' => $cost,
            'created_at' => current_time('mysql'),
        ]);
        
        // Update tenant's monthly cost
        $this->tenants->updateMonthlyCost($tenantId, $cost);
    }

    /**
     * Fetch SERP data from provider
     */
    private function fetchSerpData(string $provider, string $apiKey, string $keyword, string $location): array|\WP_Error
    {
        switch ($provider) {
            case 'dataforseo':
                return $this->fetchDataForSeo($apiKey, $keyword, $location);
            case 'serpapi':
                return $this->fetchSerpApi($apiKey, $keyword, $location);
            default:
                return new \WP_Error('unknown_provider', __('Unknown SERP provider', 'ai-seo-saas'));
        }
    }

    /**
     * Fetch from DataForSEO
     */
    private function fetchDataForSeo(string $apiKey, string $keyword, string $location): array|\WP_Error
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
    private function fetchSerpApi(string $apiKey, string $keyword, string $location): array|\WP_Error
    {
        $url = add_query_arg([
            'q' => $keyword,
            'location' => $location,
            'api_key' => $apiKey,
            'output' => 'json',
        ], 'https://serpapi.com/search');
        
        $response = wp_remote_get($url, ['timeout' => 60]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['organic_results'] ?? [];
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
}

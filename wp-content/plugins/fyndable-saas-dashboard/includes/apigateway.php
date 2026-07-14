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
        
        // Route through provider router
        $result = $this->providerRouter->routeRequest($messages, $model, $useCase, $maxTokens, $temperature);
        
        if (is_wp_error($result)) {
            $statusCode = $result->get_error_code() === 'no_provider' ? 503 : 502;
            return new \WP_REST_Response([
                'success' => false,
                'error' => $result->get_error_code(),
                'message' => $result->get_error_message()
            ], $statusCode);
        }
        
        // Track usage
        $cost = $result['usage']['cost'] ?? 0;
        $this->trackUsage($tenant['id'], 'ai_generation', 1, $cost);
        
        return new \WP_REST_Response([
            'success' => true,
            'content' => $result['content'],
            'model' => $result['model'],
            'provider' => $result['provider'],
            'usage' => $result['usage'],
        ], 200);
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
    private function trackUsage(int $tenantId, string $metric, int $count, float $cost): void
    {
        global $wpdb;
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
                return new \WP_Error('unknown_provider', __('Unknown SERP provider', 'sseo-ai-saas'));
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

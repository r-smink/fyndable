<?php

namespace AISEOClient;

/**
 * LLM Client - Proxied through SaaS Dashboard
 * 
 * All AI requests go through the SaaS Dashboard proxy for:
 * - Cost tracking per tenant
 * - Usage limit enforcement
 * - API key protection (keys stay on dashboard only)
 */
class LlmClient
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;
    private HealthLogger $health;
    
    // Rate limiting per tenant
    private const RATE_LIMIT_KEY = 'ai_seo_llm_calls_';

    public function __construct(Settings $settings, HealthLogger $health, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->health = $health;
        $this->dashboardAPI = $dashboardAPI;
    }
    
    /**
     * Check if AI features are available (license active and dashboard configured)
     */
    public function isAvailable(): bool
    {
        $licenseKey = get_option(AISEO_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(AISEO_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('ai_seo_client_dashboard_url', '');
        
        return !empty($licenseKey) && !empty($tenantKey) && !empty($dashboardUrl);
    }

    /**
     * Get available AI models based on license tier
     */
    public function getAvailableModels(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        
        // Check usage status from dashboard
        $status = $this->dashboardAPI->checkUsageStatus();
        if (is_wp_error($status)) {
            return [];
        }
        
        $tier = $status['tier'] ?? 'free';
        
        // Tier-based model access
        $models = [
            'free' => ['gpt-3.5-turbo'],
            'starter' => ['gpt-3.5-turbo'],
            'trial' => ['gpt-3.5-turbo', 'gpt-4'],
            'professional' => ['gpt-3.5-turbo', 'gpt-4'],
            'business' => ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo'],
            'agency' => ['gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo', 'gpt-5'],
        ];
        
        return $models[$tier] ?? ['gpt-3.5-turbo'];
    }

    /**
     * Check if GPT-5 is available for this tenant
     */
    public function isGpt5Available(): bool
    {
        return in_array('gpt-5', $this->getAvailableModels());
    }

    /**
     * Check rate limit per tenant
     */
    private function checkRateLimit(): bool
    {
        $tenantKey = get_option(AISEO_CLIENT_TENANT_OPTION, '');
        if (empty($tenantKey)) {
            return false;
        }
        
        $key = self::RATE_LIMIT_KEY . $tenantKey;
        $calls = get_transient($key) ?: 0;
        $limit = (int)get_option('ai_seo_client_rate_limit', 60); // per hour
        
        return $calls < $limit;
    }

    /**
     * Increment rate limit counter
     */
    private function incrementRateLimit(): void
    {
        $tenantKey = get_option(AISEO_CLIENT_TENANT_OPTION, '');
        if (empty($tenantKey)) {
            return;
        }
        
        $key = self::RATE_LIMIT_KEY . $tenantKey;
        $calls = get_transient($key) ?: 0;
        set_transient($key, $calls + 1, HOUR_IN_SECONDS);
    }

    /**
     * Call AI through SaaS Dashboard proxy
     * This ensures costs are tracked and limits are enforced
     * 
     * @param string $prompt User prompt
     * @param string|null $model Model to use (gpt-3.5-turbo, gpt-4, gpt-4-turbo, gpt-5)
     * @param string|null $systemRole System role override
     * @param int|null $maxTokens Max tokens
     * @return array|\WP_Error ['text' => string, 'model' => string, 'usage' => array]
     */
    public function call(
        string $prompt, 
        ?string $model = null, 
        ?string $systemRole = null, 
        ?int $maxTokens = null
    ) {
        if (!$this->isAvailable()) {
            return new \WP_Error('not_licensed', __('AI features require an active license', 'ai-seo-client'));
        }

        if (!$this->checkRateLimit()) {
            return new \WP_Error('rate_limited', __('API rate limit reached. Try again later.', 'ai-seo-client'));
        }

        // Check usage before making request
        $status = $this->dashboardAPI->checkUsageStatus();
        if (is_wp_error($status)) {
            return $status;
        }
        
        // Check if over limits
        if (($status['remaining']['api_calls'] ?? 0) <= 0) {
            return new \WP_Error(
                'usage_exceeded', 
                __('You have reached your monthly API call limit. Please upgrade your plan.', 'ai-seo-client')
            );
        }
        
        if (($status['remaining']['api_cost'] ?? 0) <= 0) {
            return new \WP_Error(
                'cost_exceeded',
                __('You have reached your monthly cost limit. Please upgrade your plan.', 'ai-seo-client')
            );
        }

        // Validate requested model is available for tier
        $availableModels = $this->getAvailableModels();
        $requestedModel = $model ?? 'gpt-3.5-turbo';
        
        if (!in_array($requestedModel, $availableModels)) {
            // Fallback to best available model
            $requestedModel = $availableModels[count($availableModels) - 1] ?? 'gpt-3.5-turbo';
        }

        // Build messages
        $messages = [];
        if ($systemRole) {
            $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt($systemRole)];
        } else {
            $messages[] = ['role' => 'system', 'content' => $this->buildDefaultSystemPrompt()];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        // Make request through dashboard proxy
        $response = $this->dashboardAPI->aiGenerate(
            $messages,
            $requestedModel,
            $maxTokens ?? 2000,
            $this->settings->temperature()
        );

        if (is_wp_error($response)) {
            // Check if it's a usage limit error from dashboard
            if ($response->get_error_code() === 'usage_exceeded') {
                return new \WP_Error(
                    'usage_exceeded',
                    __('Your monthly AI usage limit has been reached. Please upgrade your plan or contact support.', 'ai-seo-client')
                );
            }
            return $response;
        }

        // Increment local rate limit
        $this->incrementRateLimit();

        return [
            'text' => $response['content'] ?? '',
            'model' => $response['model'] ?? $requestedModel,
            'usage' => $response['usage'] ?? [],
            'provider' => 'openai',
        ];
    }

    /**
     * Health check - verify AI is working
     */
    public function healthcheck(string $prompt = 'Test prompt')
    {
        $res = $this->call($prompt, 'gpt-3.5-turbo'); // Use cheapest model for healthcheck
        
        if (is_wp_error($res)) {
            return $res;
        }
        
        $wordCount = str_word_count($res['text'] ?? '');
        return [
            'provider' => $res['provider'] ?? 'openai',
            'model' => $res['model'] ?? 'unknown',
            'words' => $wordCount,
        ];
    }

    /**
     * Get remaining usage info
     */
    public function getUsageInfo(): array|\WP_Error
    {
        return $this->dashboardAPI->checkUsageStatus();
    }

    /**
     * Build system prompt based on role
     */
    private function buildSystemPrompt(string $role): string
    {
        $prompts = [
            'seo_expert' => 'You are an expert SEO content writer. Create optimized, engaging content that ranks well.',
            'content_writer' => 'You are a professional content writer. Create high-quality, engaging content.',
            'meta_optimizer' => 'You are an SEO meta tag specialist. Write compelling titles and descriptions.',
            'keyword_researcher' => 'You are a keyword research expert. Identify valuable keywords and search intent.',
            'content_strategist' => 'You are a content strategist. Plan effective content that drives traffic.',
        ];

        return $prompts[$role] ?? $prompts['content_writer'];
    }

    /**
     * Build default system prompt
     */
    private function buildDefaultSystemPrompt(): string
    {
        return 'You are an AI SEO assistant. Help create optimized, engaging content that performs well in search engines.';
    }
}

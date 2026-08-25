<?php

namespace SSEOAIClient;

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
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        
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
        
        // Tier-based model access (OpenRouter multi-provider models)
        $models = [
            'free' => ['openai/gpt-4o-mini'],
            'starter' => ['openai/gpt-4o-mini', 'openai/gpt-4o'],
            'trial' => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3.5-sonnet'],
            'professional' => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3.5-sonnet', 'deepseek/deepseek-chat'],
            'business' => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3.5-sonnet', 'deepseek/deepseek-chat', 'anthropic/claude-3-haiku'],
            'agency' => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3.5-sonnet', 'deepseek/deepseek-chat', 'anthropic/claude-3-haiku', 'google/gemini-flash-1.5'],
        ];

        return $models[$tier] ?? ['openai/gpt-4o-mini'];
    }

    /**
     * Check if premium models are available for this tenant
     */
    public function isGpt5Available(): bool
    {
        $models = $this->getAvailableModels();
        return in_array('anthropic/claude-3.5-sonnet', $models) || in_array('openai/gpt-4o', $models);
    }

    /**
     * Check rate limit per tenant
     */
    private function checkRateLimit(): bool
    {
        // Bypass rate limit for test licenses
        $licenseType = get_option('sseo_ai_client_license_type', '');
        if ($licenseType === 'test') {
            return true;
        }
        
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        if (empty($tenantKey)) {
            return false;
        }
        
        $key = self::RATE_LIMIT_KEY . $tenantKey;
        $calls = get_transient($key) ?: 0;
        $limit = (int)get_option('sseo_ai_client_rate_limit', 60); // per hour
        
        return $calls < $limit;
    }
    
    /**
     * Get current rate limit status
     */
    public function getRateLimitStatus(): array
    {
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        if (empty($tenantKey)) {
            return ['calls' => 0, 'limit' => 0, 'remaining' => 0, 'reset_in' => 0];
        }
        
        $key = self::RATE_LIMIT_KEY . $tenantKey;
        $calls = get_transient($key) ?: 0;
        $limit = (int)get_option('sseo_ai_client_rate_limit', 60);
        
        // Get transient expiration time
        $expires = get_option('_transient_timeout_' . $key);
        $resetIn = $expires ? max(0, $expires - time()) : HOUR_IN_SECONDS;
        
        return [
            'calls' => (int)$calls,
            'limit' => $limit,
            'remaining' => max(0, $limit - $calls),
            'reset_in' => $resetIn,
            'reset_in_minutes' => ceil($resetIn / 60),
        ];
    }
    
    /**
     * Reset rate limit counter (for admin use)
     */
    public function resetRateLimit(): bool
    {
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        if (empty($tenantKey)) {
            return false;
        }
        
        $key = self::RATE_LIMIT_KEY . $tenantKey;
        return delete_transient($key);
    }

    /**
     * Increment rate limit counter
     */
    private function incrementRateLimit(): void
    {
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
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
     * @param string|null $model Model to use (openai/gpt-4o-mini, openai/gpt-4o, anthropic/claude-3.5-sonnet, etc.)
     * @param string|null $systemRole System role override
     * @param int|null $maxTokens Max tokens
     * @param array $trackExtra Extra tracking data (endpoint, post_id, context)
     * @return array|\WP_Error ['text' => string, 'model' => string, 'usage' => array]
     */
    public function call(
        string $prompt, 
        ?string $model = null, 
        ?string $systemRole = null, 
        ?int $maxTokens = null,
        array $trackExtra = [],
        string $useCase = 'content_generation'
    ) {
        if (!$this->isAvailable()) {
            return new \WP_Error('not_licensed', __('AI features require an active license', 'ai-seo-client'));
        }

        if (!$this->checkRateLimit()) {
            $status = $this->getRateLimitStatus();
            $minutes = $status['reset_in_minutes'] ?? 60;
            return new \WP_Error(
                'rate_limited', 
                sprintf(
                    __('API rate limit reached (%d/%d calls). Limit resets in %d minutes.', 'ai-seo-client'),
                    $status['calls'],
                    $status['limit'],
                    $minutes
                )
            );
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

        // Check SaaS dashboard per-function model routing (AI Model Routing per function)
        $modelRouting = get_option('sseo_ai_client_model_routing', []);
        if (empty($model) && is_array($modelRouting) && !empty($modelRouting[$useCase])) {
            $model = $modelRouting[$useCase];
        }

        // Validate requested model is available for tier
        $availableModels = $this->getAvailableModels();
        $requestedModel = $model ?? 'openai/gpt-4o-mini';
        
        if (!in_array($requestedModel, $availableModels)) {
            // Fallback to best available model
            $requestedModel = $availableModels[count($availableModels) - 1] ?? 'openai/gpt-4o-mini';
        }

        // Always enforce sentence case for any use case that produces titles,
        // headings, FAQ questions, meta titles or other visible copy. This
        // prevents the LLM from defaulting to Title Case ("Every Word
        // Capitalized") which looks unnatural in Dutch and most European
        // languages. Only the first word of a title/heading and proper nouns
        // should be capitalized.
        if (in_array($useCase, ['content_generation', 'meta_optimization', 'faq_generation', 'analysis'], true)) {
            $casingInstruction = "CASING REQUIREMENT (follow strictly):\n"
                . "Use sentence case for ALL titles, headings, subheadings, FAQ questions and meta titles. "
                . "Only the first word of a title/heading and proper nouns (brand names, place names, person names, acronyms) may be capitalized. "
                . "Do NOT use Title Case (where every word is capitalized). Example correct: \"How to improve your SEO in 2026\". Example wrong: \"How To Improve Your SEO In 2026\".\n\n";
            $prompt = $casingInstruction . $prompt;
        }

        // Inject brand voice instructions for content generation use cases
        if (in_array($useCase, ['content_generation', 'analysis', 'keyword_research'], true)) {
            $brandVoicePrompt = apply_filters('sseo_ai_brand_voice_prompt', '');
            if (!empty($brandVoicePrompt)) {
                $prompt = $brandVoicePrompt . $prompt;
            }
        }

        // Build messages
        $messages = [];
        if ($systemRole) {
            $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt($systemRole)];
        } else {
            $messages[] = ['role' => 'system', 'content' => $this->buildDefaultSystemPrompt()];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $startTime = microtime(true);

        // Make request through dashboard proxy
        $response = $this->dashboardAPI->aiGenerate(
            $messages,
            $requestedModel,
            $maxTokens ?? 2000,
            $this->settings->temperature(),
            $useCase
        );

        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        if (is_wp_error($response)) {
            $provider = $this->detectProvider($requestedModel);
            $this->health->logProviderError($provider, $response->get_error_code(), $response->get_error_message());

            // Log error
            LLMTracker::log([
                'prompt' => $prompt,
                'response' => '',
                'model' => $requestedModel,
                'endpoint' => $trackExtra['endpoint'] ?? 'llm.call',
                'status' => 'error',
                'error_message' => $response->get_error_message(),
                'duration_ms' => $durationMs,
                'post_id' => $trackExtra['post_id'] ?? 0,
                'context' => $trackExtra['context'] ?? '',
            ]);

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

        $result = [
            'text' => $response['content'] ?? '',
            'model' => $response['model'] ?? $requestedModel,
            'usage' => $response['usage'] ?? [],
            'provider' => $response['provider'] ?? 'openrouter',
        ];

        // Log success
        $usage = $result['usage'] ?? [];
        LLMTracker::log([
            'prompt' => $prompt,
            'response' => $result['text'],
            'model' => $result['model'],
            'tokens_input' => $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0,
            'tokens_output' => $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0,
            'cost' => $usage['cost'] ?? 0,
            'endpoint' => $trackExtra['endpoint'] ?? 'llm.call',
            'status' => 'success',
            'duration_ms' => $durationMs,
            'post_id' => $trackExtra['post_id'] ?? 0,
            'context' => $trackExtra['context'] ?? '',
        ]);

        return $result;
    }

    /**
     * Generate text using AI (compatibility wrapper for call method)
     * 
     * @param string $prompt The prompt to send
     * @param array $options Options like max_tokens, model, etc.
     * @return string|\WP_Error Generated text or error
     */
    public function generateText(string $prompt, array $options = [])
    {
        $model = $options['model'] ?? null;
        $maxTokens = $options['max_tokens'] ?? 2000;
        $systemRole = $options['system_role'] ?? null;
        $trackExtra = $options['track_extra'] ?? [];
        $useCase = $options['use_case'] ?? 'content_generation';

        // Casing + brand voice injection happens in call() so that direct
        // callers of call() also benefit.
        $result = $this->call($prompt, $model, $systemRole, $maxTokens, $trackExtra, $useCase);
        
        if (is_wp_error($result)) {
            return $result;
        }

        return $result['text'] ?? '';
    }

    /**
     * Call LLM with an image (vision support).
     * Sends both text prompt and image URL to a vision-capable model.
     *
     * @param string $prompt Text prompt
     * @param string $imageUrl URL of the image to analyze
     * @param string|null $model Model override (must be vision-capable)
     * @param int|null $maxTokens Max tokens
     * @param string $useCase Use case for tracking
     * @return array|\WP_Error ['text' => string, 'model' => string, 'usage' => array]
     */
    public function callWithImage(
        string $prompt,
        string $imageUrl,
        ?string $model = null,
        ?int $maxTokens = null,
        string $useCase = 'image_alt_text'
    ) {
        if (!$this->isAvailable()) {
            return new \WP_Error('not_licensed', __('AI features require an active license', 'ai-seo-client'));
        }

        // Use a vision-capable model by default
        if (empty($model)) {
            $model = 'openai/gpt-4o-mini'; // gpt-4o-mini supports vision
        }

        // Build messages with image content
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an expert at analyzing images and writing concise, descriptive alt text for SEO and accessibility.',
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                ],
            ],
        ];

        $startTime = microtime(true);

        $response = $this->dashboardAPI->aiGenerate(
            $messages,
            $model,
            $maxTokens ?? 500,
            0.3,
            $useCase
        );

        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        if (is_wp_error($response)) {
            LLMTracker::log([
                'prompt' => $prompt . ' [image: ' . $imageUrl . ']',
                'response' => '',
                'model' => $model,
                'endpoint' => 'llm.callWithImage',
                'status' => 'error',
                'error_message' => $response->get_error_message(),
                'duration_ms' => $durationMs,
                'context' => $useCase,
            ]);
            return $response;
        }

        $this->incrementRateLimit();

        $result = [
            'text' => $response['content'] ?? '',
            'model' => $response['model'] ?? $model,
            'usage' => $response['usage'] ?? [],
            'provider' => $response['provider'] ?? 'openrouter',
        ];

        LLMTracker::log([
            'prompt' => $prompt . ' [image]',
            'response' => $result['text'],
            'model' => $result['model'],
            'tokens_input' => $result['usage']['prompt_tokens'] ?? $result['usage']['input_tokens'] ?? 0,
            'tokens_output' => $result['usage']['completion_tokens'] ?? $result['usage']['output_tokens'] ?? 0,
            'cost' => $result['usage']['cost'] ?? 0,
            'endpoint' => 'llm.callWithImage',
            'status' => 'success',
            'duration_ms' => $durationMs,
            'context' => $useCase,
        ]);

        return $result;
    }

    /**
     * Health check - verify AI is working
     */
    public function healthcheck(string $prompt = 'Test prompt')
    {
        $res = $this->call($prompt, 'openai/gpt-4o-mini'); // Use cheapest model for healthcheck
        
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

    /**
     * Detect the AI provider from a model identifier for error logging.
     */
    private function detectProvider(string $model): string
    {
        $model = strtolower($model);

        if (strpos($model, '/') !== false) {
            return 'openrouter';
        }

        if (strpos($model, 'gpt-') === 0 || strpos($model, 'text-') === 0) {
            return 'openai';
        }

        if (strpos($model, 'claude') !== false) {
            return 'anthropic';
        }

        if (strpos($model, 'deepseek') !== false) {
            return 'deepseek';
        }

        if (strpos($model, 'gemini') !== false) {
            return 'google';
        }

        return 'ai';
    }
}

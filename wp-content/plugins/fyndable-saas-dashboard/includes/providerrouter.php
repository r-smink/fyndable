<?php

namespace SSEOAISaaS;

/**
 * Provider Router
 *
 * Routes AI requests to the correct provider based on the requested model.
 * Supports OpenRouter (gateway to many models) and direct OpenAI integration.
 * Architecture is extensible: add new adapters (Anthropic, Deepseek) by
 * implementing the same interface and registering them here.
 *
 * Per-function model routing: the SaaS admin configures which model to use
 * per use-case (content_generation, meta_optimization, etc.). The client
 * sends the use_case in the request; the router looks up the configured model.
 */
class ProviderRouter
{
    private OpenRouterAdapter $openRouter;
    private OpenAIAdapter $openAi;
    private SaaSSettings $settings;

    /**
     * Model pricing per 1K tokens (approximate).
     * Used for cost tracking when the provider doesn't return cost.
     */
    private const MODEL_PRICING = [
        // Legacy / existing models (kept for backward compatibility with saved routing)
        'openai/gpt-4o'               => ['input' => 0.0025, 'output' => 0.01],
        'openai/gpt-4o-mini'          => ['input' => 0.00015, 'output' => 0.0006],
        'openai/gpt-4-turbo'          => ['input' => 0.01, 'output' => 0.03],
        'openai/gpt-4'                => ['input' => 0.03, 'output' => 0.06],
        'openai/gpt-3.5-turbo'        => ['input' => 0.0005, 'output' => 0.0015],
        'anthropic/claude-3.5-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'anthropic/claude-3-haiku'    => ['input' => 0.00025, 'output' => 0.00125],
        'deepseek/deepseek-chat'      => ['input' => 0.00014, 'output' => 0.00028],
        'deepseek/deepseek-coder'     => ['input' => 0.00014, 'output' => 0.00028],
        'google/gemini-flash-1.5'     => ['input' => 0.000075, 'output' => 0.0003],
        'meta-llama/llama-3.1-70b-instruct' => ['input' => 0.00059, 'output' => 0.00079],
        // Modern OpenAI models
        'openai/gpt-5'                => ['input' => 0.005, 'output' => 0.015],
        'openai/gpt-5-mini'           => ['input' => 0.0004, 'output' => 0.0016],
        'openai/gpt-5-nano'           => ['input' => 0.00005, 'output' => 0.0002],
        // Modern Anthropic models
        'anthropic/claude-opus-4'     => ['input' => 0.015, 'output' => 0.075],
        'anthropic/claude-sonnet-4'   => ['input' => 0.003, 'output' => 0.015],
        'anthropic/claude-sonnet-4.5' => ['input' => 0.003, 'output' => 0.015],
        'anthropic/claude-haiku-4'    => ['input' => 0.001, 'output' => 0.005],
        // Modern Google models
        'google/gemini-2.5-pro'       => ['input' => 0.00125, 'output' => 0.01],
        'google/gemini-2.5-flash'     => ['input' => 0.00015, 'output' => 0.0006],
        'google/gemini-3.7-flash'     => ['input' => 0.000375, 'output' => 0.001875],
        // Modern Deepseek models
        'deepseek/deepseek-v3'        => ['input' => 0.00028, 'output' => 0.00042],
        'deepseek/deepseek-r1'        => ['input' => 0.00055, 'output' => 0.00219],
        // Modern Meta models
        'meta-llama/llama-4-scout'    => ['input' => 0.00011, 'output' => 0.00034],
        'meta-llama/llama-4-maverick' => ['input' => 0.0002, 'output' => 0.0006],
        // Other providers
        'qwen/qwen3.8-27b'            => ['input' => 0.00045, 'output' => 0.0032],
        'z-ai/glm-5.3'                => ['input' => 0.0014, 'output' => 0.0044],
        'x-ai/grok-4'                 => ['input' => 0.003, 'output' => 0.015],
    ];

    /**
     * Default model routing per use-case.
     * Can be overridden via sseo_ai_saas_model_routing option.
     */
    private const DEFAULT_ROUTING = [
        'content_generation'  => 'openai/gpt-5',
        'meta_optimization'   => 'openai/gpt-5-mini',
        'keyword_research'    => 'openai/gpt-5',
        'faq_generation'      => 'openai/gpt-5-mini',
        'content_analysis'    => 'openai/gpt-5-mini',
        'image_alt_text'      => 'openai/gpt-5-mini',
        'geo_readiness'       => 'google/gemini-3.7-flash',
    ];

    /**
     * Model fallback chain per use-case.
     * Used when the primary model or its provider fails.
     */
    private const FALLBACK_CHAIN = [
        'content_generation'  => ['openai/gpt-5', 'openai/gpt-4o', 'anthropic/claude-sonnet-4.5', 'openai/gpt-5-mini', 'openai/gpt-4o-mini', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat'],
        'meta_optimization'   => ['openai/gpt-5-mini', 'openai/gpt-4o-mini', 'openai/gpt-5', 'openai/gpt-4o', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat'],
        'keyword_research'    => ['openai/gpt-5', 'openai/gpt-4o', 'openai/gpt-5-mini', 'openai/gpt-4o-mini', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku'],
        'faq_generation'      => ['openai/gpt-5-mini', 'openai/gpt-4o-mini', 'openai/gpt-5', 'openai/gpt-4o', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat'],
        'content_analysis'    => ['openai/gpt-5-mini', 'openai/gpt-4o-mini', 'openai/gpt-5', 'openai/gpt-4o', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat'],
        'image_alt_text'      => ['openai/gpt-5-mini', 'openai/gpt-4o-mini', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat'],
        'geo_readiness'       => ['google/gemini-3.7-flash', 'google/gemini-2.5-flash', 'google/gemini-flash-1.5', 'anthropic/claude-haiku-4', 'anthropic/claude-3-haiku', 'openai/gpt-5-mini', 'openai/gpt-4o-mini', 'deepseek/deepseek-v3', 'deepseek/deepseek-chat'],
    ];

    /**
     * Available models for dropdowns.
     */
    private const AVAILABLE_MODELS = [
        // OpenAI
        'openai/gpt-5'                => 'OpenAI GPT-5 (Newest, best quality)',
        'openai/gpt-5-mini'           => 'OpenAI GPT-5 Mini (Fast, affordable)',
        'openai/gpt-5-nano'           => 'OpenAI GPT-5 Nano (Cheapest)',
        'openai/gpt-4o'               => 'OpenAI GPT-4o',
        'openai/gpt-4o-mini'          => 'OpenAI GPT-4o Mini (Fast, cheap)',
        'openai/gpt-4-turbo'          => 'OpenAI GPT-4 Turbo',
        'openai/gpt-4'                => 'OpenAI GPT-4',
        'openai/gpt-3.5-turbo'        => 'OpenAI GPT-3.5 Turbo (Cheapest legacy)',
        // Anthropic
        'anthropic/claude-opus-4'     => 'Anthropic Claude Opus 4 (Top quality)',
        'anthropic/claude-sonnet-4.5' => 'Anthropic Claude Sonnet 4.5',
        'anthropic/claude-sonnet-4'   => 'Anthropic Claude Sonnet 4',
        'anthropic/claude-haiku-4'    => 'Anthropic Claude Haiku 4 (Fast)',
        'anthropic/claude-3.5-sonnet' => 'Anthropic Claude 3.5 Sonnet (legacy)',
        'anthropic/claude-3-haiku'    => 'Anthropic Claude 3 Haiku (legacy, fast)',
        // Google
        'google/gemini-3.7-flash'     => 'Google Gemini 3.7 Flash (Fast, modern)',
        'google/gemini-2.5-pro'       => 'Google Gemini 2.5 Pro',
        'google/gemini-2.5-flash'     => 'Google Gemini 2.5 Flash (Fast, cheap)',
        'google/gemini-flash-1.5'     => 'Google Gemini Flash 1.5 (legacy)',
        // Deepseek
        'deepseek/deepseek-v3'        => 'Deepseek V3 (Cost-effective)',
        'deepseek/deepseek-r1'        => 'Deepseek R1 (Reasoning)',
        'deepseek/deepseek-chat'      => 'Deepseek Chat (legacy, cost-effective)',
        'deepseek/deepseek-coder'     => 'Deepseek Coder (legacy)',
        // Meta
        'meta-llama/llama-4-maverick' => 'Meta Llama 4 Maverick',
        'meta-llama/llama-4-scout'    => 'Meta Llama 4 Scout (Cheap)',
        'meta-llama/llama-3.1-70b-instruct' => 'Meta Llama 3.1 70B (legacy)',
        // Other
        'qwen/qwen3.8-27b'            => 'Qwen 3.8 27B',
        'z-ai/glm-5.3'                => 'Z.ai GLM 5.3 (Reasoning)',
        'x-ai/grok-4'                 => 'xAI Grok 4',
    ];

    /**
     * Standard tier models (Starter).
     * Cost-effective models for lower-tier plans.
     */
    private const STANDARD_MODELS = [
        'openai/gpt-5-mini',
        'openai/gpt-5-nano',
        'openai/gpt-4o-mini',
        'anthropic/claude-haiku-4',
        'anthropic/claude-3-haiku',
        'deepseek/deepseek-v3',
        'deepseek/deepseek-chat',
        'google/gemini-3.7-flash',
        'google/gemini-2.5-flash',
        'google/gemini-flash-1.5',
        'meta-llama/llama-4-scout',
    ];

    /**
     * Premium tier models (Professional / Business).
     * Higher-quality models for mid-tier plans.
     */
    private const PREMIUM_MODELS = [
        'openai/gpt-5',
        'openai/gpt-4o',
        'anthropic/claude-opus-4',
        'anthropic/claude-sonnet-4.5',
        'anthropic/claude-sonnet-4',
        'anthropic/claude-3.5-sonnet',
        'google/gemini-2.5-pro',
        'openai/gpt-4-turbo',
        'meta-llama/llama-4-maverick',
        'meta-llama/llama-3.1-70b-instruct',
        'deepseek/deepseek-r1',
        'qwen/qwen3.8-27b',
        'z-ai/glm-5.3',
        'x-ai/grok-4',
    ];

    /**
     * Default routing for standard model tier (Starter).
     */
    private const STANDARD_ROUTING = [
        'content_generation'  => 'openai/gpt-5-mini',
        'meta_optimization'   => 'openai/gpt-5-mini',
        'keyword_research'    => 'deepseek/deepseek-v3',
        'faq_generation'      => 'openai/gpt-5-mini',
        'content_analysis'    => 'openai/gpt-5-mini',
        'image_alt_text'      => 'openai/gpt-5-mini',
        'geo_readiness'       => 'google/gemini-3.7-flash',
    ];

    /**
     * Default routing for premium model tier (Professional / Business).
     */
    private const PREMIUM_ROUTING = [
        'content_generation'  => 'openai/gpt-5',
        'meta_optimization'   => 'openai/gpt-5-mini',
        'keyword_research'    => 'openai/gpt-5',
        'faq_generation'      => 'openai/gpt-5-mini',
        'content_analysis'    => 'openai/gpt-5',
        'image_alt_text'      => 'openai/gpt-5-mini',
        'geo_readiness'       => 'google/gemini-3.7-flash',
    ];

    public function __construct(SaaSSettings $settings)
    {
        $this->settings = $settings;
        $this->openRouter = new OpenRouterAdapter($settings);
        $this->openAi = new OpenAIAdapter($settings);
    }

    /**
     * Route an AI request to the correct provider.
     *
     * @param array $messages Chat messages
     * @param string|null $model Requested model (if null, use routing for useCase)
     * @param string $useCase Use-case key for model routing
     * @param int $maxTokens Max output tokens
     * @param float $temperature Temperature
     * @return array|\WP_Error ['content', 'model', 'usage', 'provider']
     */
    public function routeRequest(
        array $messages,
        ?string $model,
        string $useCase,
        int $maxTokens,
        float $temperature
    ): array|\WP_Error {
        // Build ordered list of candidate models: explicit/routing model + fallback chain
        $candidates = $this->getModelCandidates($model, $useCase);

        if (empty($candidates)) {
            return new \WP_Error(
                'no_provider',
                __('No AI model candidates available for this request.', 'sseo-ai-saas')
            );
        }

        $lastError = null;
        $attempted = [];
        $hasTimeout = false;

        foreach ($candidates as $candidateModel) {
            $provider = $this->getProviderForModel($candidateModel);
            $attempted[] = $candidateModel . '(' . $provider . ')';

            $result = $this->executeProviderChat($provider, $messages, $candidateModel, $maxTokens, $temperature);

            if (is_wp_error($result)) {
                $lastError = $result;
                if ($result->get_error_code() === 'ai_timeout') {
                    $hasTimeout = true;
                }
                continue;
            }

            // Normalize response and calculate cost based on the model that actually answered
            $inputTokens = $result['usage']['prompt_tokens'] ?? $result['usage']['input_tokens'] ?? 0;
            $outputTokens = $result['usage']['completion_tokens'] ?? $result['usage']['output_tokens'] ?? 0;
            $cost = $this->calculateCost($candidateModel, $inputTokens, $outputTokens);

            return [
                'content'  => $result['content'] ?? '',
                'model'    => $result['model'] ?? $candidateModel,
                'provider' => $provider,
                'usage'    => [
                    'prompt_tokens'     => $inputTokens,
                    'completion_tokens' => $outputTokens,
                    'total_tokens'      => $inputTokens + $outputTokens,
                    'cost'              => $cost,
                ],
                'fallback_used' => ($candidateModel !== $candidates[0]),
            ];
        }

        if ($lastError instanceof \WP_Error) {
            $errorCode = $hasTimeout ? 'ai_timeout' : 'model_fallback_exhausted';
            $errorTemplate = $hasTimeout
                ? __('AI request timed out. All models timed out. Attempted: %s. Last error: %s', 'sseo-ai-saas')
                : __('All AI models failed. Attempted: %s. Last error: %s', 'sseo-ai-saas');

            return new \WP_Error(
                $errorCode,
                sprintf(
                    $errorTemplate,
                    implode(' → ', $attempted),
                    $lastError->get_error_message()
                ),
                [
                    'attempted' => $attempted,
                    'last_error' => $lastError->get_error_message(),
                    'last_error_code' => $lastError->get_error_code(),
                    'timeout_detected' => $hasTimeout,
                ]
            );
        }

        return new \WP_Error(
            'no_provider',
            __('No AI provider is configured. Set up OpenRouter or OpenAI API key in SaaS settings.', 'sseo-ai-saas')
        );
    }

    /**
     * Execute a chat request against the requested provider.
     */
    private function executeProviderChat(
        string $provider,
        array $messages,
        string $model,
        int $maxTokens,
        float $temperature
    ): array|\WP_Error {
        switch ($provider) {
            case 'openrouter':
                return $this->openRouter->chat($messages, $model, $maxTokens, $temperature);
            case 'openai':
                return $this->openAi->chat($messages, $model, $maxTokens, $temperature);
            default:
                // Fallback to OpenRouter if configured, else OpenAI
                if ($this->openRouter->isConfigured()) {
                    return $this->openRouter->chat($messages, $model, $maxTokens, $temperature);
                }
                if ($this->openAi->isConfigured()) {
                    return $this->openAi->chat($messages, $model, $maxTokens, $temperature);
                }
                return new \WP_Error(
                    'no_provider',
                    __('No AI provider is configured. Set up OpenRouter or OpenAI API key in SaaS settings.', 'sseo-ai-saas')
                );
        }
    }

    /**
     * Build the ordered list of models to try for a request.
     */
    private function getModelCandidates(?string $requestedModel, string $useCase): array
    {
        $primary = $requestedModel;
        if (empty($primary)) {
            $primary = $this->resolveModelForUseCase($useCase);
        }

        $chain = self::FALLBACK_CHAIN[$useCase] ?? self::FALLBACK_CHAIN['content_generation'];

        $candidates = [];
        if (!empty($primary)) {
            $candidates[] = $primary;
        }

        foreach ($chain as $model) {
            if ($model !== $primary && !in_array($model, $candidates, true)) {
                $candidates[] = $model;
            }
        }

        return $candidates;
    }

    /**
     * Resolve which model to use for a given use-case.
     */
    public function resolveModelForUseCase(string $useCase): string
    {
        $routing = get_option('sseo_ai_saas_model_routing', []);
        if (!is_array($routing)) {
            $routing = [];
        }

        if (!empty($routing[$useCase])) {
            return $routing[$useCase];
        }

        return self::DEFAULT_ROUTING[$useCase] ?? self::DEFAULT_ROUTING['content_generation'];
    }

    /**
     * Determine which provider handles a given model.
     * Models with a slash (e.g. "openai/gpt-4o") go through OpenRouter.
     * Plain OpenAI model names go direct.
     */
    private function getProviderForModel(string $model): string
    {
        // OpenRouter models contain a provider prefix (e.g. "openai/gpt-4o")
        if (str_contains($model, '/')) {
            return 'openrouter';
        }

        // Plain OpenAI model names
        $openAiModels = ['gpt-4', 'gpt-4-turbo', 'gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'gpt-5'];
        if (in_array($model, $openAiModels, true)) {
            // Use OpenRouter if it's configured (preferred gateway), else direct OpenAI
            if ($this->openRouter->isConfigured()) {
                return 'openrouter';
            }
            return 'openai';
        }

        // Default: try OpenRouter
        return 'openrouter';
    }

    /**
     * Calculate cost based on model pricing and token usage.
     */
    private function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::MODEL_PRICING[$model] ?? ['input' => 0.001, 'output' => 0.002];

        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Get available models for dropdowns (hardcoded list only).
     * Use getMergedAvailableModels() to also include live-fetched OpenRouter models.
     */
    public static function getAvailableModels(): array
    {
        return self::AVAILABLE_MODELS;
    }

    /**
     * Get available models merged with live-fetched OpenRouter models.
     * Hardcoded models take precedence (their curated labels are preserved);
     * any additional models returned by OpenRouter's /api/v1/models endpoint
     * are appended with their provider-supplied name as the label.
     *
     * @param bool $forceRefresh Bypass the OpenRouter models cache.
     * @return array [id => label]
     */
    public function getMergedAvailableModels(bool $forceRefresh = false): array
    {
        $models = self::AVAILABLE_MODELS;

        $live = $this->openRouter->fetchModels($forceRefresh);
        if (!empty($live)) {
            foreach ($live as $id => $label) {
                if (!isset($models[$id])) {
                    $models[$id] = $label;
                }
            }
        }

        return $models;
    }

    /**
     * Get standard tier models merged with live OpenRouter models that are
     * not already classified as premium. This keeps the standard dropdown
     * focused on cost-effective options while still allowing admins to pick
     * any newly available model.
     *
     * @param bool $forceRefresh Bypass the OpenRouter models cache.
     * @return array [id => label]
     */
    public function getMergedStandardModels(bool $forceRefresh = false): array
    {
        $merged = $this->getMergedAvailableModels($forceRefresh);
        $standard = array_flip(self::STANDARD_MODELS);

        // Include hardcoded standard models plus any live model that is not
        // explicitly a premium model (so new cheap models show up here).
        $premium = array_flip(self::PREMIUM_MODELS);
        $result = [];
        foreach ($merged as $id => $label) {
            if (isset($standard[$id]) || !isset($premium[$id])) {
                $result[$id] = $label;
            }
        }
        return $result;
    }

    /**
     * Get premium tier models merged with live OpenRouter models.
     * Includes all hardcoded premium models plus every live model (so admins
     * can assign any high-end model to premium use-cases).
     *
     * @param bool $forceRefresh Bypass the OpenRouter models cache.
     * @return array [id => label]
     */
    public function getMergedPremiumModels(bool $forceRefresh = false): array
    {
        $merged = $this->getMergedAvailableModels($forceRefresh);
        $premium = array_flip(self::PREMIUM_MODELS);

        $result = [];
        // Premium models first (curated order), then the rest.
        foreach (self::PREMIUM_MODELS as $id) {
            if (isset($merged[$id])) {
                $result[$id] = $merged[$id];
            }
        }
        foreach ($merged as $id => $label) {
            if (!isset($result[$id])) {
                $result[$id] = $label;
            }
        }
        return $result;
    }

    /**
     * Get use-case definitions for routing config.
     */
    public static function getUseCases(): array
    {
        return [
            'content_generation'  => __('Content Generation', 'sseo-ai-saas'),
            'meta_optimization'   => __('Meta Optimization', 'sseo-ai-saas'),
            'keyword_research'    => __('Keyword Research', 'sseo-ai-saas'),
            'faq_generation'      => __('FAQ Generation', 'sseo-ai-saas'),
            'content_analysis'    => __('Content Analysis', 'sseo-ai-saas'),
            'image_alt_text'      => __('Image Alt Text', 'sseo-ai-saas'),
            'geo_readiness'       => __('GEO Readiness Scan', 'sseo-ai-saas'),
        ];
    }

    /**
     * Get default routing map.
     */
    public static function getDefaultRouting(): array
    {
        return self::DEFAULT_ROUTING;
    }

    /**
     * Get the model tier for a given subscription tier.
     * Returns 'standard' or 'premium'.
     */
    public static function getModelTierForTier(string $tier): string
    {
        $premiumTiers = ['professional', 'business', 'agency'];
        return in_array($tier, $premiumTiers, true) ? 'premium' : 'standard';
    }

    /**
     * Get routing map for a model tier ('standard' or 'premium').
     */
    public static function getRoutingForModelTier(string $modelTier): array
    {
        if ($modelTier === 'premium') {
            $custom = get_option('sseo_ai_saas_premium_routing', []);
            if (is_array($custom) && !empty($custom)) {
                return array_merge(self::PREMIUM_ROUTING, $custom);
            }
            return self::PREMIUM_ROUTING;
        }

        $custom = get_option('sseo_ai_saas_standard_routing', []);
        if (is_array($custom) && !empty($custom)) {
            return array_merge(self::STANDARD_ROUTING, $custom);
        }
        return self::STANDARD_ROUTING;
    }

    /**
     * Get standard tier models.
     */
    public static function getStandardModels(): array
    {
        return self::STANDARD_MODELS;
    }

    /**
     * Get premium tier models.
     */
    public static function getPremiumModels(): array
    {
        return self::PREMIUM_MODELS;
    }
}

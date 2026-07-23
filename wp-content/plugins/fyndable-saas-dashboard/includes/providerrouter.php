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
    ];

    /**
     * Default model routing per use-case.
     * Can be overridden via sseo_ai_saas_model_routing option.
     */
    private const DEFAULT_ROUTING = [
        'content_generation'  => 'openai/gpt-4o',
        'meta_optimization'   => 'openai/gpt-4o-mini',
        'keyword_research'    => 'openai/gpt-4o',
        'faq_generation'      => 'openai/gpt-4o-mini',
        'content_analysis'    => 'openai/gpt-4o-mini',
        'image_alt_text'      => 'openai/gpt-4o-mini',
        'geo_readiness'       => 'google/gemini-flash-1.5',
    ];

    /**
     * Model fallback chain per use-case.
     * Used when the primary model or its provider fails.
     */
    private const FALLBACK_CHAIN = [
        'content_generation'  => ['openai/gpt-4o', 'openai/gpt-4o-mini', 'anthropic/claude-3-haiku', 'deepseek/deepseek-chat'],
        'meta_optimization'   => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3-haiku', 'deepseek/deepseek-chat'],
        'keyword_research'    => ['openai/gpt-4o', 'openai/gpt-4o-mini', 'deepseek/deepseek-chat', 'anthropic/claude-3-haiku'],
        'faq_generation'      => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3-haiku', 'deepseek/deepseek-chat'],
        'content_analysis'    => ['openai/gpt-4o-mini', 'openai/gpt-4o', 'anthropic/claude-3-haiku', 'deepseek/deepseek-chat'],
        'image_alt_text'      => ['openai/gpt-4o-mini', 'anthropic/claude-3-haiku', 'deepseek/deepseek-chat'],
        'geo_readiness'       => ['google/gemini-flash-1.5', 'anthropic/claude-3-haiku', 'openai/gpt-4o-mini', 'deepseek/deepseek-chat'],
    ];

    /**
     * Available models for dropdowns.
     */
    private const AVAILABLE_MODELS = [
        'openai/gpt-4o'               => 'OpenAI GPT-4o (Best quality)',
        'openai/gpt-4o-mini'          => 'OpenAI GPT-4o Mini (Fast, cheap)',
        'openai/gpt-4-turbo'          => 'OpenAI GPT-4 Turbo',
        'openai/gpt-4'                => 'OpenAI GPT-4',
        'openai/gpt-3.5-turbo'        => 'OpenAI GPT-3.5 Turbo (Cheapest)',
        'anthropic/claude-3.5-sonnet' => 'Anthropic Claude 3.5 Sonnet',
        'anthropic/claude-3-haiku'    => 'Anthropic Claude 3 Haiku (Fast)',
        'deepseek/deepseek-chat'      => 'Deepseek Chat (Cost-effective)',
        'deepseek/deepseek-coder'     => 'Deepseek Coder',
        'google/gemini-flash-1.5'     => 'Google Gemini Flash 1.5',
        'meta-llama/llama-3.1-70b-instruct' => 'Meta Llama 3.1 70B',
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

        foreach ($candidates as $candidateModel) {
            $provider = $this->getProviderForModel($candidateModel);
            $attempted[] = $candidateModel . '(' . $provider . ')';

            $result = $this->executeProviderChat($provider, $messages, $candidateModel, $maxTokens, $temperature);

            if (is_wp_error($result)) {
                $lastError = $result;
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
            return new \WP_Error(
                'model_fallback_exhausted',
                sprintf(
                    __('All AI models failed. Attempted: %s. Last error: %s', 'sseo-ai-saas'),
                    implode(' → ', $attempted),
                    $lastError->get_error_message()
                ),
                ['attempted' => $attempted, 'last_error' => $lastError->get_error_message()]
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
     * Get available models for dropdowns.
     */
    public static function getAvailableModels(): array
    {
        return self::AVAILABLE_MODELS;
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
}

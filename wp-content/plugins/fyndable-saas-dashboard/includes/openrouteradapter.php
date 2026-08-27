<?php

namespace SSEOAISaaS;

/**
 * OpenRouter Adapter
 *
 * Sends chat completion requests to OpenRouter's API.
 * OpenRouter is an OpenAI-compatible gateway that provides access to
 * 100+ models (OpenAI, Anthropic, Deepseek, Google, Meta, etc.) via one API key.
 *
 * API docs: https://openrouter.ai/docs
 * Endpoint: https://openrouter.ai/api/v1/chat/completions
 */
class OpenRouterAdapter
{
    private SaaSSettings $settings;
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
    private string $modelsUrl = 'https://openrouter.ai/api/v1/models';

    /** Transient key for cached model list. */
    public const MODELS_TRANSIENT = 'sseo_ai_openrouter_models_cache';
    /** Cache lifetime in seconds (12 hours). */
    public const MODELS_CACHE_TTL = 12 * HOUR_IN_SECONDS;

    public function __construct(SaaSSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Check if OpenRouter is configured (API key set).
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get OpenRouter API key.
     */
    private function getApiKey(): string
    {
        return get_option('sseo_ai_saas_openrouter_api_key', '');
    }

    /**
     * Send a chat completion request to OpenRouter.
     *
     * @param array $messages Chat messages [{role, content}, ...]
     * @param string $model OpenRouter model identifier (e.g. "openai/gpt-4o")
     * @param int $maxTokens Max output tokens
     * @param float $temperature Temperature (0-2)
     * @return array|\WP_Error ['content', 'model', 'usage']
     */
    public function chat(array $messages, string $model, int $maxTokens, float $temperature): array|\WP_Error
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return new \WP_Error(
                'openrouter_not_configured',
                __('OpenRouter API key is not configured. Add it in SaaS Settings.', 'sseo-ai-saas')
            );
        }

        $siteUrl = get_site_url();
        $siteName = get_bloginfo('name');

        $response = wp_remote_post($this->apiUrl, [
            'headers' => [
                'Authorization'    => 'Bearer ' . $apiKey,
                'Content-Type'     => 'application/json',
                'HTTP-Referer'     => $siteUrl,
                'X-Title'          => substr($siteName, 0, 100),
            ],
            'body' => json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false || stripos($message, 'cURL error 28') !== false) {
                return new \WP_Error(
                    'ai_timeout',
                    sprintf(__('OpenRouter request timed out after 120s: %s', 'sseo-ai-saas'), $message)
                );
            }
            return new \WP_Error(
                'openrouter_request_failed',
                sprintf(__('OpenRouter request failed: %s', 'sseo-ai-saas'), $message)
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200) {
            $errorMsg = $body['error']['message'] ?? $body['message'] ?? __('Unknown OpenRouter error', 'sseo-ai-saas');
            $errorCode = $body['error']['code'] ?? 'openrouter_error';

            // Map common errors
            if ($statusCode === 401) {
                $errorCode = 'invalid_api_key';
                $errorMsg = __('Invalid OpenRouter API key. Check your settings.', 'sseo-ai-saas');
            } elseif ($statusCode === 402) {
                $errorCode = 'insufficient_credits';
                $errorMsg = __('OpenRouter credits exhausted. Top up at openrouter.ai.', 'sseo-ai-saas');
            } elseif ($statusCode === 429) {
                $errorCode = 'rate_limited';
                $errorMsg = __('OpenRouter rate limit reached. Try again shortly.', 'sseo-ai-saas');
            }

            return new \WP_Error($errorCode, $errorMsg);
        }

        $content = $body['choices'][0]['message']['content'] ?? '';
        $returnedModel = $body['model'] ?? $model;
        $usage = $body['usage'] ?? [];

        return [
            'content' => $content,
            'model'   => $returnedModel,
            'usage'   => [
                'prompt_tokens'     => $usage['prompt_tokens'] ?? 0,
                'completion_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens'      => $usage['total_tokens'] ?? 0,
                'cost'              => $usage['cost'] ?? 0,
            ],
        ];
    }

    /**
     * Fetch the list of available models from OpenRouter's /api/v1/models endpoint.
     *
     * Returns an associative array [model_id => label] filtered to text-output chat
     * models. Results are cached in a transient for self::MODELS_CACHE_TTL seconds.
     * Pass $forceRefresh = true to bypass the cache (used by the admin "Refresh" button).
     *
     * @param bool $forceRefresh Bypass the transient cache.
     * @return array [id => label]  (empty array if fetch fails / not configured)
     */
    public function fetchModels(bool $forceRefresh = false): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        if (!$forceRefresh) {
            $cached = get_transient(self::MODELS_TRANSIENT);
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get($this->modelsUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['data']) || !is_array($body['data'])) {
            return [];
        }

        $models = [];
        foreach ($body['data'] as $model) {
            $id = $model['id'] ?? '';
            if (empty($id)) {
                continue;
            }

            // Skip non-text output models (image/audio/video only) and batch variants.
            $outputModalities = $model['architecture']['output_modalities'] ?? ['text'];
            if (!in_array('text', $outputModalities, true)) {
                continue;
            }
            // Skip batch endpoints and free variants to keep the dropdown clean.
            if (str_contains($id, ':batch') || str_contains($id, ':free')) {
                continue;
            }

            $name = $model['name'] ?? $id;
            $models[$id] = $name;
        }

        asort($models, SORT_STRING | SORT_FLAG_CASE);

        if (!empty($models)) {
            set_transient(self::MODELS_TRANSIENT, $models, self::MODELS_CACHE_TTL);
        }

        return $models;
    }

    /**
     * Clear the cached model list (forces a fresh fetch on next request).
     */
    public static function clearModelsCache(): void
    {
        delete_transient(self::MODELS_TRANSIENT);
    }
}

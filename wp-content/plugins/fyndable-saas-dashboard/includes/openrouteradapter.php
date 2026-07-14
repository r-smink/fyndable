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
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error(
                'openrouter_request_failed',
                sprintf(__('OpenRouter request failed: %s', 'sseo-ai-saas'), $response->get_error_message())
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
}

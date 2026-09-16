<?php

namespace SSEOAISaaS;

/**
 * OpenAI Adapter
 *
 * Direct integration with OpenAI's chat completions API.
 * Used as a fallback provider when OpenRouter is not configured,
 * or when a plain OpenAI model name is requested without a provider prefix.
 *
 * API docs: https://platform.openai.com/docs/api-reference/chat
 * Endpoint: https://api.openai.com/v1/chat/completions
 */
class OpenAIAdapter
{
    private SaaSSettings $settings;
    private string $apiUrl = 'https://api.openai.com/v1/chat/completions';
    private string $imagesUrl = 'https://api.openai.com/v1/images/generations';

    public function __construct(SaaSSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Check if OpenAI is configured (API key set).
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get OpenAI API key.
     */
    private function getApiKey(): string
    {
        return get_option('ai_seo_saas_openai_api_key', '');
    }

    /**
     * Send a chat completion request to OpenAI.
     *
     * @param array $messages Chat messages [{role, content}, ...]
     * @param string $model OpenAI model name (e.g. "gpt-4", "gpt-4o")
     * @param int $maxTokens Max output tokens
     * @param float $temperature Temperature (0-2)
     * @return array|\WP_Error ['content', 'model', 'usage']
     */
    public function chat(array $messages, string $model, int $maxTokens, float $temperature): array|\WP_Error
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return new \WP_Error(
                'openai_not_configured',
                __('OpenAI API key is not configured. Add it in SaaS Settings.', 'sseo-ai-saas')
            );
        }

        // Strip provider prefix if present (e.g. "openai/gpt-4o" → "gpt-4o")
        if (str_contains($model, '/')) {
            $parts = explode('/', $model, 2);
            $model = $parts[1];
        }

        $response = wp_remote_post($this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model'       => $model,
                'messages'    => $messages,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
            ]),
            'timeout' => 300,
        ]);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false || stripos($message, 'cURL error 28') !== false) {
                return new \WP_Error(
                    'ai_timeout',
                    sprintf(__('OpenAI request timed out after 300s: %s', 'sseo-ai-saas'), $message)
                );
            }
            return new \WP_Error(
                'openai_request_failed',
                sprintf(__('OpenAI request failed: %s', 'sseo-ai-saas'), $message)
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200) {
            $errorMsg = $body['error']['message'] ?? __('Unknown OpenAI error', 'sseo-ai-saas');
            $errorCode = $body['error']['code'] ?? 'openai_error';

            if ($statusCode === 401) {
                $errorCode = 'invalid_api_key';
                $errorMsg = __('Invalid OpenAI API key. Check your settings.', 'sseo-ai-saas');
            } elseif ($statusCode === 429) {
                $errorCode = 'rate_limited';
                $errorMsg = __('OpenAI rate limit reached. Try again shortly.', 'sseo-ai-saas');
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
                'cost'              => 0,
            ],
        ];
    }

    /**
     * Generate an image via OpenAI's images API.
     *
     * Defaults to dall-e-3 because it returns a hosted URL — Shopify can fetch
     * it directly via productCreateMedia/fileCreate. gpt-image-1 only returns
     * b64_json, which needs staged uploads instead.
     *
     * @param string $prompt Image prompt
     * @param string $size   e.g. "1024x1024", "1024x1792", "1792x1024"
     * @param string $model  dall-e-3 | gpt-image-1
     * @return array|\WP_Error ['url'|'b64_json', 'model', 'revised_prompt']
     */
    public function image(string $prompt, string $size = '1024x1024', string $model = 'dall-e-3'): array|\WP_Error
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return new \WP_Error(
                'openai_not_configured',
                __('OpenAI API key is not configured. Add it in SaaS Settings.', 'sseo-ai-saas')
            );
        }

        $allowedSizes = ['1024x1024', '1024x1792', '1792x1024'];
        if (!in_array($size, $allowedSizes, true)) {
            $size = '1024x1024';
        }

        $response = wp_remote_post($this->imagesUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model'           => $model,
                'prompt'          => $prompt,
                'n'               => 1,
                'size'            => $size,
                'response_format' => 'url',
            ]),
            'timeout' => 180,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('openai_request_failed', sprintf(__('OpenAI image request failed: %s', 'sseo-ai-saas'), $response->get_error_message()));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200) {
            $errorMsg = $body['error']['message'] ?? __('Unknown OpenAI error', 'sseo-ai-saas');
            $errorCode = $body['error']['code'] ?? 'openai_image_error';
            if ($statusCode === 401) {
                $errorCode = 'invalid_api_key';
            } elseif ($statusCode === 429) {
                $errorCode = 'rate_limited';
            }

            return new \WP_Error($errorCode, $errorMsg);
        }

        $item = $body['data'][0] ?? [];

        return [
            'url'            => $item['url'] ?? null,
            'b64_json'       => $item['b64_json'] ?? null,
            'revised_prompt' => $item['revised_prompt'] ?? null,
            'model'          => $model,
        ];
    }
}

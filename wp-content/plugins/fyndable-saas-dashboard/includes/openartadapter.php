<?php

namespace SSEOAISaaS;

/**
 * OpenArt Adapter
 *
 * Generates images via OpenArt's API, supporting Flux models
 * (Flux-1-Schnell, Flux-1-Dev, Flux-1-Pro).
 *
 * API docs: https://openart.ai/api
 * Endpoint: https://api.openart.ai/api/v1/generation
 */
class OpenArtAdapter
{
    private string $apiUrl = 'https://api.openart.ai/api/v1/generation';

    /**
     * Check if OpenArt is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get OpenArt API key.
     */
    private function getApiKey(): string
    {
        return get_option('ai_seo_saas_openart_api_key', '');
    }

    /**
     * Generate an image via OpenArt (Flux models).
     *
     * @param string $prompt Image generation prompt
     * @param string $model Flux model identifier (flux-1-schnell, flux-1-dev, flux-1-pro)
     * @param string $size Image size (e.g. "1024x1024", "1024x576", "576x1024")
     * @return array|\WP_Error ['url', 'model', 'cost']
     */
    public function generateImage(string $prompt, string $model = 'flux-1-schnell', string $size = '1024x1024'): array|\WP_Error
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return new \WP_Error(
                'openart_not_configured',
                __('OpenArt API key is not configured. Add it in SaaS Settings.', 'sseo-ai-saas')
            );
        }

        // Parse dimensions
        $dimensions = $this->parseSize($size);
        $width = $dimensions['width'];
        $height = $dimensions['height'];

        $response = wp_remote_post($this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'model'         => $model,
                'prompt'        => $prompt,
                'width'         => $width,
                'height'        => $height,
                'num_images'    => 1,
                'guidance_scale' => 7,
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error(
                'openart_request_failed',
                sprintf(__('OpenArt request failed: %s', 'sseo-ai-saas'), $response->get_error_message())
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200) {
            $errorMsg = $body['error']['message'] ?? $body['message'] ?? __('Unknown OpenArt error', 'sseo-ai-saas');

            if ($statusCode === 401) {
                $errorMsg = __('Invalid OpenArt API key. Check your settings.', 'sseo-ai-saas');
            } elseif ($statusCode === 429) {
                $errorMsg = __('OpenArt rate limit reached. Try again shortly.', 'sseo-ai-saas');
            } elseif ($statusCode === 402) {
                $errorMsg = __('OpenArt credits exhausted. Top up your account.', 'sseo-ai-saas');
            }

            return new \WP_Error('openart_error', $errorMsg);
        }

        // OpenArt returns image URLs in the response
        $imageUrl = $body['data'][0]['url'] ?? $body['images'][0]['url'] ?? $body['url'] ?? '';

        if (empty($imageUrl)) {
            return new \WP_Error(
                'openart_no_image',
                __('OpenArt did not return an image URL.', 'sseo-ai-saas')
            );
        }

        // Cost estimation per Flux model
        $cost = $this->estimateCost($model);

        return [
            'url'   => $imageUrl,
            'model' => $model,
            'cost'  => $cost,
        ];
    }

    /**
     * Parse size string into width/height.
     */
    private function parseSize(string $size): array
    {
        $parts = explode('x', $size);
        $width = (int)($parts[0] ?? 1024);
        $height = (int)($parts[1] ?? 1024);

        // Clamp to reasonable bounds
        $width = max(256, min(1536, $width));
        $height = max(256, min(1536, $height));

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Estimate cost per image by model.
     */
    private function estimateCost(string $model): float
    {
        $costs = [
            'flux-1-schnell' => 0.003,
            'flux-1-dev'     => 0.01,
            'flux-1-pro'     => 0.025,
        ];

        return $costs[$model] ?? 0.01;
    }
}

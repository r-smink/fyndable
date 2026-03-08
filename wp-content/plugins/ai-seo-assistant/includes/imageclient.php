<?php

namespace AISEOAssistant;

class ImageClient
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function generate(string $prompt): array|\WP_Error
    {
        $provider = $this->settings->imgProvider();
        return match ($provider) {
            'openai' => $this->openai($prompt),
            default  => new \WP_Error('img_provider', __('Unknown image provider', 'ai-seo-assistant')),
        };
    }

    private function openai(string $prompt)
    {
        $apiKey = $this->settings->get('openai_key');
        if (!$apiKey) {
            return new \WP_Error('missing_key', __('OpenAI API key is missing', 'ai-seo-assistant'));
        }
        $resp = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-image-1',
                'prompt' => $prompt,
                'size' => '1024x1024',
                'n' => 1,
            ]),
        ]);
        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            return new \WP_Error('img_http', __('Image API error', 'ai-seo-assistant'), $resp);
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $url = $body['data'][0]['url'] ?? '';
        return $url ? ['url' => $url, 'provider' => 'openai'] : new \WP_Error('img_empty', __('Empty image result', 'ai-seo-assistant'));
    }
}

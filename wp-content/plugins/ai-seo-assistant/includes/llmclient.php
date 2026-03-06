<?php

namespace AISEOAssistant;

use WP_Error;

class LlmClient
{
    private Settings $settings;
    private HealthLogger $health;

    public function __construct(Settings $settings, HealthLogger $health)
    {
        $this->settings = $settings;
        $this->health = $health;
    }

    /**
     * Call the configured LLM with fallback.
     */
    public function call(string $prompt)
    {
        $providers = $this->buildProviderOrder($this->settings->llmProvider());
        $lastError = null;
        foreach ($providers as $provider) {
            $res = match ($provider) {
                'openai'    => $this->openaiCall($prompt),
                'anthropic' => $this->anthropicCall($prompt),
                'mistral'   => $this->mistralCall($prompt),
                default     => new WP_Error('llm_provider', __('Unknown LLM provider', 'ai-seo-assistant')),
            };
            if (is_wp_error($res)) {
                $lastError = $res;
                continue;
            }
            if ($res) {
                return ['provider' => $provider, 'text' => $res];
            }
        }
        return $lastError ?: new WP_Error('llm_failed', __('No LLM response', 'ai-seo-assistant'));
    }

    public function healthcheck(string $prompt = 'Test prompt')
    {
        $res = $this->call($prompt);
        if (is_wp_error($res)) {
            return $res;
        }
        $wordCount = str_word_count($res['text']);
        return [
            'provider' => $res['provider'],
            'words'    => $wordCount,
        ];
    }

    /**
     * Cron-invoked healthcheck logger.
     */
    public function runHealthcheckJob(): void
    {
        $res = $this->healthcheck('Korte SEO-outline over test keyword; 3 bullets');
        $status = is_wp_error($res) ? 'error' : 'ok';
        $provider = is_wp_error($res) ? '' : ($res['provider'] ?? '');
        $message = is_wp_error($res) ? $res->get_error_message() : sprintf('OK (%d woorden)', $res['words'] ?? 0);
        $this->health->log('llm', $provider, $status, $message);
    }

    private function buildProviderOrder(string $primary): array
    {
        $priority = ['openai', 'anthropic', 'mistral'];
        $ordered = array_merge([$primary], array_diff($priority, [$primary]));
        return array_values(array_unique($ordered));
    }

    private function openaiCall(string $prompt)
    {
        $apiKey = $this->settings->get('openai_key');
        if (!$apiKey) {
            return new WP_Error('openai_key', __('OpenAI key ontbreekt', 'ai-seo-assistant'));
        }
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4.1',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an SEO content strategist. Reply in Dutch.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->settings->temperature(),
                'max_tokens'  => $this->settings->maxTokens(),
            ]),
        ]);
        return $this->extractOpenAI($response);
    }

    private function anthropicCall(string $prompt)
    {
        $apiKey = $this->settings->get('anthropic_key');
        if (!$apiKey) {
            return new WP_Error('anthropic_key', __('Anthropic key ontbreekt', 'ai-seo-assistant'));
        }
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'claude-3-opus-20240229',
                'max_tokens' => $this->settings->maxTokens(),
                'temperature' => $this->settings->temperature(),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);
        return $this->extractAnthropic($response);
    }

    private function mistralCall(string $prompt)
    {
        $apiKey = $this->settings->get('mistral_key');
        if (!$apiKey) {
            return new WP_Error('mistral_key', __('Mistral key ontbreekt', 'ai-seo-assistant'));
        }
        $response = wp_remote_post('https://api.mistral.ai/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'mistral-large-latest',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an SEO content strategist. Reply in Dutch.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $this->settings->temperature(),
                'max_tokens'  => $this->settings->maxTokens(),
            ]),
        ]);
        return $this->extractOpenAI($response);
    }

    private function extractOpenAI($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('llm_http', __('LLM API error', 'ai-seo-assistant'), $response);
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        return $text ?: new WP_Error('llm_empty', __('Leeg antwoord van LLM', 'ai-seo-assistant'));
    }

    private function extractAnthropic($response)
    {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('llm_http', __('LLM API error', 'ai-seo-assistant'), $response);
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['content'][0]['text'] ?? '';
        return $text ?: new WP_Error('llm_empty', __('Leeg antwoord van LLM', 'ai-seo-assistant'));
    }
}

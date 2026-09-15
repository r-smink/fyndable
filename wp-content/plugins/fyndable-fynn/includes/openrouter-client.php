<?php
/**
 * OpenRouter API client.
 *
 * @package Fynn
 */

namespace Fynn;

if (!defined('ABSPATH')) {
    exit;
}

class OpenRouterClient {

    private string $apiKey;
    private string $model;
    private float $temperature;
    private int $maxTokens;
    private string $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct() {
        $this->apiKey = Settings::getOpenRouterKey();
        $this->model = get_option('sseo_ai_fynn_model', 'openai/gpt-4o-mini');
        $this->temperature = (float) get_option('sseo_ai_fynn_temperature', 0.7);
        $this->maxTokens = (int) get_option('sseo_ai_fynn_max_tokens', 1000);
    }

    public function ask(string $question, array $history = []): array|\WP_Error {
        if ($this->apiKey === '') {
            return new \WP_Error('no_api_key', __('OpenRouter API-key is niet geconfigureerd.', 'fyndable-fynn'));
        }

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->buildSystemPrompt()];

        $recentHistory = array_slice($history, -6);
        foreach ($recentHistory as $message) {
            if (!is_array($message) || empty($message['role']) || empty($message['content'])) {
                continue;
            }
            $role = sanitize_text_field($message['role']);
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = [
                'role' => $role,
                'content' => sanitize_textarea_field($message['content']),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => sanitize_textarea_field($question)];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ];

        $response = wp_remote_post($this->apiUrl, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => 'Fynn Chat',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 90,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            return new \WP_Error(
                'openrouter_http_error',
                sprintf(__('OpenRouter fout %d: %s', 'fyndable-fynn'), $code, $body)
            );
        }

        $data = json_decode($body, true);
        if (empty($data) || !is_array($data)) {
            return new \WP_Error('openrouter_invalid_json', __('Ongeldig antwoord van OpenRouter.', 'fyndable-fynn'));
        }

        if (!empty($data['error'])) {
            $message = is_array($data['error']) ? ($data['error']['message'] ?? '') : (string) $data['error'];
            return new \WP_Error('openrouter_api_error', $message);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';

        return [
            'text' => trim($content),
            'model' => $data['model'] ?? $this->model,
            'usage' => $data['usage'] ?? [],
        ];
    }

    private function buildSystemPrompt(): string {
        $brand = get_option('blogname', 'Fyndable');
        return "Je bent Fynn, de vriendelijke AI-assistent van {$brand}. "
            . "Je helpt bezoekers van de website met korte, heldere antwoorden in het Nederlands, "
            . "tenzij de vraag duidelijk in het Engels is. "
            . "Wees behulpzaam, professioneel en niet te formeel. "
            . "Als je het antwoord niet zeker weet, vraag je of de gebruiker een supportaanvraag wil indienen.";
    }
}

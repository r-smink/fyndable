<?php
/**
 * REST API routes for Fynn.
 *
 * @package Fynn
 */

namespace Fynn;

if (!defined('ABSPATH')) {
    exit;
}

class Assistant {

    public function __construct() {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void {
        register_rest_route('sseo-ai/v1/fynn', '/config', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getConfig'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sseo-ai/v1/fynn', '/public/ask', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'publicAsk'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getConfig(\WP_REST_Request $request): \WP_REST_Response {
        $config = [
            'name' => 'Fynn',
            'poses' => [
                'idle' => plugins_url('assets/fynn-poses/idle.svg', FYNN_PLUGIN_FILE),
                'listening' => plugins_url('assets/fynn-poses/listening.svg', FYNN_PLUGIN_FILE),
                'thinking' => plugins_url('assets/fynn-poses/thinking.svg', FYNN_PLUGIN_FILE),
                'found' => plugins_url('assets/fynn-poses/found.svg', FYNN_PLUGIN_FILE),
                'expert' => plugins_url('assets/fynn-poses/expert.svg', FYNN_PLUGIN_FILE),
                'celebration' => plugins_url('assets/fynn-poses/celebration.svg', FYNN_PLUGIN_FILE),
                'wave' => plugins_url('assets/fynn-poses/wave.svg', FYNN_PLUGIN_FILE),
            ],
            'colors' => [
                'primary' => '#4F46E5',
            ],
            'suggestedQuestions' => [],
            'i18n' => [
                'placeholder' => __('Typ je vraag...', 'fyndable-fynn'),
                'send' => __('Verstuur', 'fyndable-fynn'),
                'close' => __('Sluiten', 'fyndable-fynn'),
                'support' => __('Supportaanvraag maken', 'fyndable-fynn'),
                'thinking' => __('Fynn denkt na...', 'fyndable-fynn'),
                'error' => __('Er ging iets mis. Probeer het opnieuw.', 'fyndable-fynn'),
                'limit' => __('Je hebt het limiet bereikt. Probeer later opnieuw.', 'fyndable-fynn'),
            ],
        ];

        return new \WP_REST_Response($config);
    }

    public function publicAsk(\WP_REST_Request $request): \WP_REST_Response {
        $params = $request->get_json_params();
        $question = isset($params['question']) ? sanitize_text_field($params['question']) : '';
        $history = isset($params['history']) && is_array($params['history']) ? $params['history'] : [];

        if ($question === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Vraag is verplicht.', 'fyndable-fynn'),
            ], 400);
        }

        if (!$this->checkRateLimit()) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Je hebt het limiet bereikt. Probeer later opnieuw.', 'fyndable-fynn'),
            ], 429);
        }

        $client = new OpenRouterClient();
        $answer = $client->ask($question, $history);

        if (is_wp_error($answer)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $answer->get_error_message(),
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'answer' => $answer['text'],
            'source' => 'ai',
            'confidence' => 1,
            'ticket_suggested' => false,
        ]);
    }

    private function checkRateLimit(): bool {
        $ip = $this->getClientIp();
        $key = 'fynn_rate_' . md5($ip);
        $limit = (int) get_option('sseo_ai_fynn_rate_limit', 20);
        $calls = (int) get_transient($key);

        if ($calls >= $limit) {
            return false;
        }

        $calls++;
        set_transient($key, $calls, HOUR_IN_SECONDS);

        return true;
    }

    private function getClientIp(): string {
        $ip = '';

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0] ?? '');
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return $ip;
    }
}

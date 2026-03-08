<?php

namespace AISEOAssistant;

class SaasWebhook
{
    private Settings $settings;
    private SnapshotRepository $snapshots;
    private HealthLogger $health;

    public function __construct(Settings $settings, SnapshotRepository $snapshots, HealthLogger $health)
    {
        $this->settings = $settings;
        $this->snapshots = $snapshots;
        $this->health = $health;
    }

    public function register(): void
    {
        register_rest_route('aiseoassistant/v1', '/saas-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(\WP_REST_Request $request)
    {
        $secret = $this->settings->webhookSecret();
        if ($secret) {
            $sig = $request->get_header('x-aiseo-sign');
            $raw = $request->get_body();
            $calc = hash_hmac('sha256', $raw, $secret);
            if (!hash_equals($calc, $sig)) {
                return new \WP_Error('invalid_signature', __('Invalid webhook signature', 'ai-seo-assistant'), ['status' => 403]);
            }
        }

        $body = $request->get_json_params();
        $type = $body['type'] ?? '';
        switch ($type) {
            case 'serp_snapshot':
                $kw = $body['keyword'] ?? '';
                $provider = $body['provider'] ?? 'saas';
                $results = $body['results'] ?? [];
                if ($kw && is_array($results)) {
                    $this->snapshots->save($kw, $provider, $results);
                    do_action('aiseoassistant_snapshot_saved', $kw, ['provider' => $provider, 'results' => $results]);
                }
                break;
            case 'health':
                $this->health->log($body['domain'] ?? 'saas', $body['provider'] ?? 'saas', $body['status'] ?? 'info', $body['message'] ?? '');
                break;
            default:
                return new \WP_Error('unsupported', __('Unsupported webhook type', 'ai-seo-assistant'), ['status' => 400]);
        }

        return ['ok' => true];
    }
}

<?php

namespace AISEOAssistant;

class SaasClient
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function post(string $path, array $payload): array|\WP_Error
    {
        $base = rtrim($this->settings->get('saas_api_base', ''), '/');
        $tenant = $this->settings->get('tenant_key', '');
        if (!$base || !$tenant) {
            return new \WP_Error('saas_config', __('SaaS API base or tenant key missing', 'ai-seo-assistant'));
        }

        $body = wp_json_encode($payload);
        $resp = wp_remote_post($base . '/' . ltrim($path, '/'), [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Tenant-Key' => $tenant,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $out = json_decode(wp_remote_retrieve_body($resp), true);
        if ($code >= 300) {
            return new \WP_Error('saas_http', __('SaaS API error', 'ai-seo-assistant'), $out);
        }
        return is_array($out) ? $out : [];
    }
}

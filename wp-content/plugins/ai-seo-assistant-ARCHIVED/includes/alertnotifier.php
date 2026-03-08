<?php

namespace AISEOAssistant;

class AlertNotifier
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function send(string $text): void
    {
        $webhook = $this->settings->get('webhook_url', '');
        $enabled = !empty($this->settings->get('webhook_enabled'));
        if (!$enabled || empty($webhook)) {
            return;
        }

        wp_remote_post($webhook, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['text' => $text]),
            'timeout' => 5,
        ]);
    }

    public function formatMessage(string $type, string $provider, string $status, string $message): string
    {
        $emoji = $status === 'error' ? '🚨' : ($status === 'warn' ? '⚠️' : '✅');
        $prefix = strtoupper($type) . ' • ' . $provider;
        return sprintf('%s %s — %s', $emoji, $prefix, $message);
    }
}

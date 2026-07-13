<?php

namespace SSEOAIClient;

/**
 * Alert Notifier
 * 
 * Sends alert notifications when errors occur in the health logger.
 * Can be extended to support email, Slack, or other notification channels.
 */
class AlertNotifier
{
    private string $adminEmail;

    public function __construct()
    {
        $this->adminEmail = get_option('admin_email', '');
    }

    /**
     * Format an alert message
     */
    public function formatMessage(string $type, string $provider, string $status, string $message): string
    {
        return sprintf(
            '[%s] %s - %s (%s): %s',
            strtoupper($status),
            $type,
            $provider,
            current_time('mysql'),
            $message
        );
    }

    /**
     * Send an alert notification
     */
    public function send(string $message): bool
    {
        if (empty($this->adminEmail)) {
            return false;
        }

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $subject = sprintf(__('%s Alert', 'ai-seo-client'), $companyName);

        return wp_mail(
            $this->adminEmail,
            $subject,
            $message
        );
    }
}

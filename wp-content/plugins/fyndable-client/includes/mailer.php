<?php

namespace SSEOAIClient;

/**
 * Central sender for every e-mail this plugin sends.
 *
 * Mail always goes through the site's own wp_mail(), so it uses whatever the site has
 * configured (an SMTP plugin, or the host's PHP mail). This class only adds what is ours:
 * - per type on/off (EmailSettings),
 * - the sender name/address for OUR messages only (scoped filters, never site-wide),
 * - the last failure reason, so the settings page can show why a test mail failed.
 */
class Mailer
{
    public const TYPES = ['report', 'alert', 'serp', 'apexflow', 'calendar', 'test'];

    private static string $lastError = '';

    /**
     * @param string|string[] $to
     */
    public static function send(string $type, $to, string $subject, string $body, array $headers = []): bool
    {
        self::$lastError = '';
        if ($type !== 'test' && !EmailSettings::isEnabled($type)) {
            return false;
        }

        $recipients = array_values(array_filter(array_map('trim', is_array($to) ? $to : explode(',', (string)$to)), 'is_email'));
        if (!$recipients) {
            self::$lastError = __('No valid recipient address.', 'ai-seo-client');
            return false;
        }

        $fromName = EmailSettings::fromName();
        $fromEmail = EmailSettings::fromEmail();
        $nameFilter = static fn(): string => $fromName;
        $emailFilter = static fn(): string => $fromEmail;
        $failed = static function (\WP_Error $error): void {
            self::$lastError = $error->get_error_message();
        };

        // Scoped to this one wp_mail() call: other mail on the site keeps its own sender.
        add_filter('wp_mail_from_name', $nameFilter, 20);
        if ($fromEmail !== '') {
            add_filter('wp_mail_from', $emailFilter, 20);
        }
        add_action('wp_mail_failed', $failed);
        try {
            $sent = wp_mail($recipients, $subject, $body, $headers);
        } finally {
            remove_filter('wp_mail_from_name', $nameFilter, 20);
            remove_filter('wp_mail_from', $emailFilter, 20);
            remove_action('wp_mail_failed', $failed);
        }

        if (!$sent && self::$lastError === '') {
            self::$lastError = __('wp_mail() could not send the message. Check the mail settings of this site.', 'ai-seo-client');
        }
        return (bool)$sent;
    }

    public static function lastError(): string
    {
        return self::$lastError;
    }
}

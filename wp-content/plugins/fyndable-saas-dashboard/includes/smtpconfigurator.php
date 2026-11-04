<?php

namespace SSEOAISaaS;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Routes wp_mail through the SMTP server configured under
 * Settings → Email / SMTP (options sseo_ai_saas_smtp_*).
 */
class SmtpConfigurator
{
    public function register(): void
    {
        add_action('phpmailer_init', [$this, 'configureMailer']);
        add_filter('wp_mail_from', [$this, 'getSmtpFromEmail']);
        add_filter('wp_mail_from_name', [$this, 'getSmtpFromName']);
    }

    /**
     * Configure PHPMailer to use the configured SMTP server.
     */
    public function configureMailer($phpmailer): void
    {
        if (!get_option('sseo_ai_saas_smtp_enabled')) {
            return;
        }

        $host = get_option('sseo_ai_saas_smtp_host', '');
        if (empty($host)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = (int) get_option('sseo_ai_saas_smtp_port', 587);
        $phpmailer->SMTPAuth = (bool) get_option('sseo_ai_saas_smtp_auth', true);

        if ($phpmailer->SMTPAuth) {
            $phpmailer->Username = get_option('sseo_ai_saas_smtp_username', '');
            $phpmailer->Password = get_option('sseo_ai_saas_smtp_password', '');
        }

        $encryption = get_option('sseo_ai_saas_smtp_encryption', 'tls');
        $phpmailer->SMTPSecure = in_array($encryption, ['ssl', 'tls'], true) ? $encryption : '';
    }

    /**
     * Get the configured SMTP from email, if any.
     */
    public function getSmtpFromEmail(string $email): string
    {
        $configured = get_option('sseo_ai_saas_smtp_from_email', '');
        return !empty($configured) ? $configured : $email;
    }

    /**
     * Get the configured SMTP from name, if any.
     */
    public function getSmtpFromName(string $name): string
    {
        $configured = get_option('sseo_ai_saas_smtp_from_name', '');
        return !empty($configured) ? $configured : $name;
    }
}

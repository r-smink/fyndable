<?php

namespace SSEOAISaaS;

/**
 * Email Automation
 *
 * Sends transactional emails for key lifecycle events:
 * - Welcome / license activation
 * - Trial expiring (3 days, 1 day)
 * - License expired
 * - Payment receipt
 * - Payment failed
 * - Usage limit reached (upgrade prompt)
 *
 * Uses HTML email templates with white-label support.
 * Hooked into cron for scheduled emails.
 */
class EmailAutomation
{
    private TenantRepository $tenants;
    private EmailTemplateRenderer $renderer;

    public function __construct(TenantRepository $tenants, EmailTemplateRepository $repository)
    {
        $this->tenants = $tenants;
        $this->renderer = new EmailTemplateRenderer($repository, $tenants);
    }

    public function register(): void
    {
        // Scheduled email checks
        if (!wp_next_scheduled('sseo_ai_email_trial_check')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_email_trial_check');
        }
        add_action('sseo_ai_email_trial_check', [$this, 'checkTrialExpirations']);

        if (!wp_next_scheduled('sseo_ai_email_expired_check')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_email_expired_check');
        }
        add_action('sseo_ai_email_expired_check', [$this, 'checkExpiredLicenses']);

        // Hook into license activation
        add_action('sseo_ai_license_activated', [$this, 'sendWelcomeEmail'], 10, 2);

        // Hook into payment events
        add_action('sseo_ai_payment_success', [$this, 'sendPaymentReceipt'], 10, 3);
        add_action('sseo_ai_payment_failed', [$this, 'sendPaymentFailed'], 10, 2);

        // Hook into usage limit
        add_action('sseo_ai_usage_limit_reached', [$this, 'sendUsageLimitEmail'], 10, 2);
    }

    /**
     * Send welcome email on license activation.
     */
    public function sendWelcomeEmail(string $tenantKey, array $tenantData): void
    {
        $email = $tenantData['email'] ?? '';
        if (empty($email)) {
            return;
        }

        // Build a set-password URL when a WP user exists for this email,
        // so the welcome email can include a password-reset button.
        $setPasswordUrl = '';
        $portalUrl = '';
        $user = get_user_by('email', $email);
        if ($user) {
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                $setPasswordUrl = home_url('/set-password?key=' . rawurlencode($key) . '&login=' . rawurlencode($email));
            }
        }

        $rendered = $this->renderer->render('welcome', $tenantKey, [
            'dashboard_url'    => admin_url('admin.php?page=ai-seo-client'),
            'support_url'      => admin_url('admin.php?page=ai-seo-support'),
            'set_password_url' => $setPasswordUrl,
            'portal_url'       => $portalUrl,
        ]);

        $this->send($email, $rendered);
    }

    /**
     * Check for trials expiring soon and send warning emails.
     */
    public function checkTrialExpirations(): void
    {
        $allTenants = $this->tenants->getAllTenants();

        foreach ($allTenants as $tenant) {
            if (($tenant['tier'] ?? '') !== 'trial') {
                continue;
            }

            $expiresAt = $tenant['expires_at'] ?? '';
            if (empty($expiresAt)) {
                continue;
            }

            $expireTime = strtotime($expiresAt);
            $diff = $expireTime - time();

            // 3 days warning
            if ($diff > 2 * DAY_IN_SECONDS && $diff <= 3 * DAY_IN_SECONDS) {
                if (!get_transient('sseo_ai_trial_warn3_' . $tenant['tenant_key'])) {
                    $this->sendTrialExpiringEmail($tenant, 3);
                    set_transient('sseo_ai_trial_warn3_' . $tenant['tenant_key'], true, 3 * DAY_IN_SECONDS);
                }
            }

            // 1 day warning
            if ($diff > 0 && $diff <= DAY_IN_SECONDS) {
                if (!get_transient('sseo_ai_trial_warn1_' . $tenant['tenant_key'])) {
                    $this->sendTrialExpiringEmail($tenant, 1);
                    set_transient('sseo_ai_trial_warn1_' . $tenant['tenant_key'], true, DAY_IN_SECONDS);
                }
            }
        }
    }

    /**
     * Send trial expiring email.
     */
    private function sendTrialExpiringEmail(array $tenant, int $daysLeft): void
    {
        $email = $tenant['email'] ?? '';
        if (empty($email)) {
            return;
        }

        $rendered = $this->renderer->render('trial_expiring', $tenant['tenant_key'] ?? '', [
            'days_left'   => $daysLeft,
            'expires_at'  => $tenant['expires_at'] ?? '',
            'upgrade_url' => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $rendered);
    }

    /**
     * Check for expired licenses and send notification.
     */
    public function checkExpiredLicenses(): void
    {
        $allTenants = $this->tenants->getAllTenants();

        foreach ($allTenants as $tenant) {
            $expiresAt = $tenant['expires_at'] ?? '';
            if (empty($expiresAt)) {
                continue;
            }

            $expireTime = strtotime($expiresAt);
            $diff = $expireTime - time();

            // Just expired (within last 24h)
            if ($diff < 0 && $diff > -DAY_IN_SECONDS) {
                if (!get_transient('sseo_ai_expired_' . $tenant['tenant_key'])) {
                    $this->sendExpiredEmail($tenant);
                    set_transient('sseo_ai_expired_' . $tenant['tenant_key'], true, DAY_IN_SECONDS);
                }
            }
        }
    }

    /**
     * Send license expired email.
     */
    private function sendExpiredEmail(array $tenant): void
    {
        $email = $tenant['email'] ?? '';
        if (empty($email)) {
            return;
        }

        $rendered = $this->renderer->render('license_expired', $tenant['tenant_key'] ?? '', [
            'renew_url' => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $rendered);
    }

    /**
     * Send payment receipt.
     */
    public function sendPaymentReceipt(string $tenantKey, string $amount, array $paymentData): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return;
        }

        $email = $tenant['email'] ?? '';
        if (empty($email)) {
            return;
        }

        $rendered = $this->renderer->render('payment_receipt', $tenantKey, [
            'amount'       => $amount,
            'tier'         => ucfirst($paymentData['tier'] ?? ''),
            'payment_date' => $paymentData['date'] ?? date('Y-m-d'),
            'payment_id'   => $paymentData['payment_id'] ?? '',
            'receipt_url'  => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $rendered);
    }

    /**
     * Send payment failed email.
     */
    public function sendPaymentFailed(string $tenantKey, array $paymentData): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return;
        }

        $email = $tenant['email'] ?? '';
        if (empty($email)) {
            return;
        }

        $rendered = $this->renderer->render('payment_failed', $tenantKey, [
            'amount'       => $paymentData['amount'] ?? '',
            'retry_url'    => admin_url('admin.php?page=sseo-ai-licenses'),
            'support_url'  => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $rendered);
    }

    /**
     * Send usage limit reached email.
     */
    public function sendUsageLimitEmail(string $tenantKey, array $usageData): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return;
        }

        $email = $tenant['email'] ?? '';
        if (empty($email)) {
            return;
        }

        $rendered = $this->renderer->render('usage_limit', $tenantKey, [
            'limit'        => number_format_i18n((int)($usageData['limit'] ?? 0)),
            'used'         => number_format_i18n((int)($usageData['used'] ?? 0)),
            'upgrade_url'  => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $rendered);
    }


    /**
     * Send an email with HTML content type.
     */
    private function send(string $to, array $rendered): void
    {
        if (empty($rendered['is_active']) || empty($rendered['body'])) {
            return;
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        wp_mail($to, $rendered['subject'], $rendered['body'], $headers);
    }
}

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

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
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

        $tier = $tenantData['tier'] ?? 'free';
        $licenseKey = $tenantData['license_key'] ?? '';
        $siteName = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('Welcome to %s — Your SEO Journey Starts Here', 'sseo-ai-saas'),
            $siteName
        );

        $body = $this->renderTemplate('welcome', [
            'site_name'    => $siteName,
            'tier'         => ucfirst($tier),
            'license_key'  => $licenseKey,
            'dashboard_url' => admin_url('admin.php?page=ai-seo-client'),
            'support_url'  => admin_url('admin.php?page=ai-seo-support'),
        ]);

        $this->send($email, $subject, $body);
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

        $subject = sprintf(
            /* translators: %d: days left */
            _n('Your trial expires in %d day', 'Your trial expires in %d days', $daysLeft, 'sseo-ai-saas'),
            $daysLeft
        );

        $body = $this->renderTemplate('trial_expiring', [
            'site_name'   => get_bloginfo('name'),
            'days_left'   => $daysLeft,
            'expires_at'  => $tenant['expires_at'] ?? '',
            'upgrade_url' => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $subject, $body);
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

        $subject = __('Your Fyndable License Has Expired', 'sseo-ai-saas');

        $body = $this->renderTemplate('license_expired', [
            'site_name'   => get_bloginfo('name'),
            'tier'        => ucfirst($tenant['tier'] ?? 'free'),
            'renew_url'   => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $subject, $body);
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

        $subject = sprintf(
            /* translators: %s: amount */
            __('Payment Receipt — %s', 'sseo-ai-saas'),
            $amount
        );

        $body = $this->renderTemplate('payment_receipt', [
            'site_name'    => get_bloginfo('name'),
            'amount'       => $amount,
            'tier'         => ucfirst($paymentData['tier'] ?? ''),
            'payment_date' => $paymentData['date'] ?? date('Y-m-d'),
            'payment_id'   => $paymentData['payment_id'] ?? '',
            'receipt_url'  => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $subject, $body);
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

        $subject = __('Payment Failed — Action Required', 'sseo-ai-saas');

        $body = $this->renderTemplate('payment_failed', [
            'site_name'    => get_bloginfo('name'),
            'amount'       => $paymentData['amount'] ?? '',
            'retry_url'    => admin_url('admin.php?page=sseo-ai-licenses'),
            'support_url'  => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $subject, $body);
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

        $subject = __('You\'ve Reached Your Monthly API Limit', 'sseo-ai-saas');

        $body = $this->renderTemplate('usage_limit', [
            'site_name'    => get_bloginfo('name'),
            'current_tier' => ucfirst($tenant['tier'] ?? 'free'),
            'limit'        => $usageData['limit'] ?? 0,
            'used'         => $usageData['used'] ?? 0,
            'upgrade_url'  => admin_url('admin.php?page=sseo-ai-licenses'),
        ]);

        $this->send($email, $subject, $body);
    }

    /**
     * Render an email template.
     */
    private function renderTemplate(string $template, array $data): string
    {
        $siteName = $data['site_name'] ?? get_bloginfo('name');
        $headerColor = '#3b82f6';
        $secondaryColor = '#ec4899';

        $header = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f3f4f6;">';
        $header .= '<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">';
        $header .= '<div style="background:linear-gradient(135deg,' . $headerColor . ' 0%,' . $secondaryColor . ' 100%);padding:30px 40px;text-align:center;">';
        $header .= '<h1 style="color:#fff;margin:0;font-size:24px;font-weight:700;">' . esc_html($siteName) . '</h1>';
        $header .= '</div>';
        $header .= '<div style="padding:30px 40px;">';

        $content = '';

        switch ($template) {
            case 'welcome':
                $content .= '<h2 style="color:#111827;margin:0 0 16px 0;font-size:20px;">' . __('Welcome aboard! 🎉', 'sseo-ai-saas') . '</h2>';
                $content .= '<p style="color:#374151;line-height:1.6;">' . sprintf(
                    /* translators: %1$s: tier, %2$s: site name */
                    __('Your <strong>%1$s</strong> license is now active. You\'re ready to supercharge your SEO with %2$s.', 'sseo-ai-saas'),
                    esc_html($data['tier'] ?? 'Free'),
                    esc_html($siteName)
                ) . '</p>';
                $content .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:20px;margin:24px 0;">';
                $content .= '<p style="margin:0 0 8px 0;font-size:13px;color:#6b7280;text-transform:uppercase;font-weight:600;">' . __('Your License Key', 'sseo-ai-saas') . '</p>';
                $content .= '<p style="margin:0;font-family:monospace;font-size:14px;color:#111827;">' . esc_html($data['license_key'] ?? '') . '</p>';
                $content .= '</div>';
                $content .= '<a href="' . esc_url($data['dashboard_url'] ?? '#') . '" style="display:inline-block;background:' . $headerColor . ';color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;margin-top:8px;">' . __('Go to Dashboard', 'sseo-ai-saas') . '</a>';
                $content .= '<p style="color:#9ca3af;font-size:13px;margin-top:24px;">' . sprintf(
                    /* translators: %s: support url */
                    __('Need help? <a href="%s">Contact support</a>', 'sseo-ai-saas'),
                    esc_url($data['support_url'] ?? '#')
                ) . '</p>';
                break;

            case 'trial_expiring':
                $content .= '<h2 style="color:#111827;margin:0 0 16px 0;font-size:20px;">' . sprintf(
                    /* translators: %d: days */
                    _n('Your trial ends in %d day', 'Your trial ends in %d days', $data['days_left'] ?? 0, 'sseo-ai-saas'),
                    $data['days_left'] ?? 0
                ) . '</h2>';
                $content .= '<p style="color:#374151;line-height:1.6;">' . __('Don\'t lose access to your SEO tools. Upgrade now to keep all features running smoothly.', 'sseo-ai-saas') . '</p>';
                $content .= '<a href="' . esc_url($data['upgrade_url'] ?? '#') . '" style="display:inline-block;background:' . $headerColor . ';color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;margin-top:16px;">' . __('Upgrade Now', 'sseo-ai-saas') . '</a>';
                break;

            case 'license_expired':
                $content .= '<h2 style="color:#111827;margin:0 0 16px 0;font-size:20px;">' . __('Your license has expired', 'sseo-ai-saas') . '</h2>';
                $content .= '<p style="color:#374151;line-height:1.6;">' . sprintf(
                    /* translators: %s: tier */
                    __('Your <strong>%s</strong> license has expired. Renew now to restore access to all SEO features.', 'sseo-ai-saas'),
                    esc_html($data['tier'] ?? 'Free')
                ) . '</p>';
                $content .= '<a href="' . esc_url($data['renew_url'] ?? '#') . '" style="display:inline-block;background:' . $headerColor . ';color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;margin-top:16px;">' . __('Renew License', 'sseo-ai-saas') . '</a>';
                break;

            case 'payment_receipt':
                $content .= '<h2 style="color:#111827;margin:0 0 16px 0;font-size:20px;">' . __('Payment Confirmation', 'sseo-ai-saas') . '</h2>';
                $content .= '<div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:20px;margin:24px 0;">';
                $content .= '<p style="margin:0;font-size:28px;font-weight:700;color:#065f46;">' . esc_html($data['amount'] ?? '') . '</p>';
                $content .= '<p style="margin:4px 0 0 0;color:#047857;">' . __('Payment successful', 'sseo-ai-saas') . '</p>';
                $content .= '</div>';
                $content .= '<table style="width:100%;font-size:14px;color:#374151;margin-bottom:24px;">';
                $content .= '<tr><td style="padding:8px 0;color:#6b7280;">' . __('Plan', 'sseo-ai-saas') . '</td><td style="padding:8px 0;text-align:right;font-weight:600;">' . esc_html($data['tier'] ?? '') . '</td></tr>';
                $content .= '<tr><td style="padding:8px 0;color:#6b7280;">' . __('Date', 'sseo-ai-saas') . '</td><td style="padding:8px 0;text-align:right;">' . esc_html($data['payment_date'] ?? '') . '</td></tr>';
                $content .= '<tr><td style="padding:8px 0;color:#6b7280;">' . __('Transaction ID', 'sseo-ai-saas') . '</td><td style="padding:8px 0;text-align:right;font-family:monospace;">' . esc_html($data['payment_id'] ?? '') . '</td></tr>';
                $content .= '</table>';
                $content .= '<a href="' . esc_url($data['receipt_url'] ?? '#') . '" style="display:inline-block;background:' . $headerColor . ';color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;">' . __('View Details', 'sseo-ai-saas') . '</a>';
                break;

            case 'payment_failed':
                $content .= '<h2 style="color:#111827;margin:0 0 16px 0;font-size:20px;">' . __('Payment could not be processed', 'sseo-ai-saas') . '</h2>';
                $content .= '<p style="color:#374151;line-height:1.6;">' . sprintf(
                    /* translators: %s: amount */
                    __('We were unable to process your payment of %s. Please update your payment method to avoid service interruption.', 'sseo-ai-saas'),
                    esc_html($data['amount'] ?? '')
                ) . '</p>';
                $content .= '<a href="' . esc_url($data['retry_url'] ?? '#') . '" style="display:inline-block;background:#ef4444;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;margin-top:16px;">' . __('Update Payment', 'sseo-ai-saas') . '</a>';
                break;

            case 'usage_limit':
                $content .= '<h2 style="color:#111827;margin:0 0 16px 0;font-size:20px;">' . __('Monthly API limit reached', 'sseo-ai-saas') . '</h2>';
                $content .= '<p style="color:#374151;line-height:1.6;">' . sprintf(
                    /* translators: %1$s: used, %2$s: limit, %3$s: tier */
                    __('You\'ve used %1$s of %2$s API calls on your <strong>%3$s</strong> plan this month. Upgrade to get more calls and unlock additional features.', 'sseo-ai-saas'),
                    esc_html(number_format_i18n($data['used'] ?? 0)),
                    esc_html(number_format_i18n($data['limit'] ?? 0)),
                    esc_html($data['current_tier'] ?? 'Free')
                ) . '</p>';
                $content .= '<a href="' . esc_url($data['upgrade_url'] ?? '#') . '" style="display:inline-block;background:' . $headerColor . ';color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;margin-top:16px;">' . __('Upgrade Plan', 'sseo-ai-saas') . '</a>';
                break;

            default:
                $content .= '<p>' . __('Hello!', 'sseo-ai-saas') . '</p>';
                break;
        }

        $footer = '</div>';
        $footer .= '<div style="padding:20px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">';
        $footer .= '<p style="margin:0;font-size:13px;color:#9ca3af;">' . esc_html($siteName) . ' — ' . __('This is an automated email, please do not reply.', 'sseo-ai-saas') . '</p>';
        $footer .= '</div>';
        $footer .= '</div></body></html>';

        return $header . $content . $footer;
    }

    /**
     * Send an email with HTML content type.
     */
    private function send(string $to, string $subject, string $htmlBody): void
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        wp_mail($to, $subject, $htmlBody, $headers);
    }
}

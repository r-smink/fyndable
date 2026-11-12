<?php

namespace SSEOAIClient;

/**
 * Settings → E-mail & reports: one place for every e-mail the plugin sends.
 *
 * The values live in the options the modules already read (no migration):
 * - SEO report:      sseo_ai_report_frequency (off|daily|weekly|monthly) + sseo_ai_report_email
 * - SERP drops:      sseo_ai_serp_monitor_settings['notify_email']
 * - ApexFlow:        sseo_ai_apexflow_settings['notify_email']
 * - Content calendar: sseo_ai_notify_email
 * - New here:        sseo_ai_mail_settings (from_name, from_email, alerts, alerts_max)
 *
 * Delivery is the site's own wp_mail(); this plugin has no SMTP of its own. The card shows
 * which mail route the site uses and recommends an SMTP plugin when it is plain PHP mail().
 */
class EmailSettings
{
    public const OPTION = 'sseo_ai_mail_settings';
    public const FREQUENCIES = ['off', 'daily', 'weekly', 'monthly'];
    private const DEFAULTS = ['from_name' => '', 'from_email' => '', 'alerts' => true, 'alerts_max' => 5];

    private ?ExternalIntegrations $integrations;

    public function __construct(?ExternalIntegrations $integrations = null)
    {
        $this->integrations = $integrations;
    }

    public function register(): void
    {
        add_action('sseo_ai_render_email_settings', [$this, 'render']);
        add_action('sseo_ai_save_email_settings', [$this, 'save']);
        add_action('wp_ajax_sseo_ai_send_test_email', [$this, 'ajaxSendTest']);
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    public static function settings(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::DEFAULTS, is_array($saved) ? $saved : []);
    }

    public static function reportFrequency(): string
    {
        $frequency = (string)get_option('sseo_ai_report_frequency', 'weekly');
        return in_array($frequency, self::FREQUENCIES, true) ? $frequency : 'weekly';
    }

    /** @return string[] report recipients; empty setting = the site admin address */
    public static function reportRecipients(): array
    {
        $emails = array_values(array_filter(array_map('trim', explode(',', (string)get_option('sseo_ai_report_email', ''))), 'is_email'));
        return $emails ?: array_values(array_filter([(string)get_option('admin_email')], 'is_email'));
    }

    public static function isEnabled(string $type): bool
    {
        switch ($type) {
            case 'report':
                return self::reportFrequency() !== 'off';
            case 'alert':
                return !empty(self::settings()['alerts']);
            case 'serp':
                $serp = get_option('sseo_ai_serp_monitor_settings', []);
                return !is_array($serp) || !array_key_exists('notify_email', $serp) || !empty($serp['notify_email']);
            case 'apexflow':
                $apex = get_option('sseo_ai_apexflow_settings', []);
                return !is_array($apex) || !array_key_exists('notify_email', $apex) || !empty($apex['notify_email']);
            case 'calendar':
                return (bool)get_option('sseo_ai_notify_email', true);
            default:
                return true;
        }
    }

    public static function alertsMaxPerDay(): int
    {
        return max(1, min(50, (int)self::settings()['alerts_max']));
    }

    /** Sender name for our mail: own setting, else white-label company name, else the site name. */
    public static function fromName(): string
    {
        $name = trim((string)self::settings()['from_name']);
        if ($name !== '') {
            return $name;
        }
        $whiteLabel = get_option('sseo_ai_white_label', []);
        if (is_array($whiteLabel) && !empty($whiteLabel['company_name'])) {
            return (string)$whiteLabel['company_name'];
        }
        return wp_specialchars_decode((string)get_bloginfo('name'), ENT_QUOTES) ?: 'WordPress';
    }

    /** Sender address for our mail; empty = leave it to WordPress / the SMTP plugin. */
    public static function fromEmail(): string
    {
        $email = (string)self::settings()['from_email'];
        return is_email($email) ? $email : '';
    }

    /** Brand name for mail texts (white-label aware). */
    public static function brandName(): string
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        return is_array($whiteLabel) && !empty($whiteLabel['company_name']) ? (string)$whiteLabel['company_name'] : 'Fyndable';
    }

    /**
     * How this site sends mail.
     *
     * @return array{level: 'ok'|'warning', label: string, detail: string}
     */
    public static function transportStatus(): array
    {
        $plugins = [
            'WP Mail SMTP' => defined('WPMS_PLUGIN_VER') || function_exists('wp_mail_smtp'),
            'FluentSMTP' => defined('FLUENTMAIL') || defined('FLUENTMAIL_PLUGIN_VERSION'),
            'Post SMTP' => defined('POST_SMTP_VER') || class_exists('PostmanWpMail'),
            'Easy WP SMTP' => class_exists('EasyWPSMTP') || defined('EasyWPSMTP_PLUGIN_VERSION'),
            'SMTP Mailer' => class_exists('SMTP_MAILER'),
        ];
        foreach ($plugins as $name => $active) {
            if ($active) {
                return [
                    'level' => 'ok',
                    'label' => sprintf(__('Mail is sent via %s.', 'ai-seo-client'), $name),
                    'detail' => __('The sender address set in that plugin may override the sender address below.', 'ai-seo-client'),
                ];
            }
        }

        try {
            $file = (string)(new \ReflectionFunction('wp_mail'))->getFileName();
        } catch (\ReflectionException $e) {
            $file = '';
        }
        if ($file !== '' && !str_ends_with(str_replace('\\', '/', $file), 'wp-includes/pluggable.php')) {
            return [
                'level' => 'ok',
                'label' => __('Mail is sent via a mail plugin of this site (wp_mail is replaced).', 'ai-seo-client'),
                'detail' => '',
            ];
        }
        if (has_action('phpmailer_init')) {
            return [
                'level' => 'ok',
                'label' => __('Mail is sent with a custom SMTP configuration of this site.', 'ai-seo-client'),
                'detail' => '',
            ];
        }

        return [
            'level' => 'warning',
            'label' => __('Mail is sent with the PHP mail() function of the web server.', 'ai-seo-client'),
            'detail' => __('This often ends up in spam or is not delivered. Install an SMTP plugin (for example WP Mail SMTP or FluentSMTP) and connect it to your own mail server or mail service.', 'ai-seo-client'),
        ];
    }

    // ------------------------------------------------------------------
    // Settings card (rendered inside the main settings form)
    // ------------------------------------------------------------------

    public function render(): void
    {
        $settings = self::settings();
        $frequency = self::reportFrequency();
        $status = self::transportStatus();
        $apexAvailable = class_exists(ApexFlow::class);
        ?>
        <div class="fyn-card-header"><span class="dashicons dashicons-email-alt"></span><h3 class="fyn-card-title"><?php esc_html_e('E-mail & reports', 'ai-seo-client'); ?></h3></div>
        <p style="font-size:13px;color:var(--text-m);margin:0 0 16px;"><?php esc_html_e('Choose which e-mails this plugin sends and to whom. Mail is sent with the mail settings of this website.', 'ai-seo-client'); ?></p>

        <div class="fyn-notice fyn-notice--<?php echo $status['level'] === 'ok' ? 'success' : 'warning'; ?>" style="margin:0 0 16px;padding:10px 14px;border-radius:8px;background:<?php echo $status['level'] === 'ok' ? 'rgba(16,185,129,.1)' : 'rgba(245,158,11,.12)'; ?>;">
            <strong><?php echo esc_html($status['label']); ?></strong>
            <?php if ($status['detail'] !== '') : ?><br><span style="font-size:13px;"><?php echo esc_html($status['detail']); ?></span><?php endif; ?>
        </div>

        <div class="fyn-settings-field">
            <label for="sseo_mail_report_frequency"><strong><?php esc_html_e('SEO report', 'ai-seo-client'); ?></strong></label>
            <select name="sseo_mail_report_frequency" id="sseo_mail_report_frequency">
                <option value="off" <?php selected($frequency, 'off'); ?>><?php esc_html_e('Off', 'ai-seo-client'); ?></option>
                <option value="daily" <?php selected($frequency, 'daily'); ?>><?php esc_html_e('Daily', 'ai-seo-client'); ?></option>
                <option value="weekly" <?php selected($frequency, 'weekly'); ?>><?php esc_html_e('Weekly (Monday)', 'ai-seo-client'); ?></option>
                <option value="monthly" <?php selected($frequency, 'monthly'); ?>><?php esc_html_e('Monthly', 'ai-seo-client'); ?></option>
            </select>
            <p class="field-description"><?php esc_html_e('Summary of SEO scores, rankings, technical health and action items.', 'ai-seo-client'); ?></p>
        </div>

        <div class="fyn-settings-field">
            <label for="sseo_mail_report_email"><strong><?php esc_html_e('Report recipients', 'ai-seo-client'); ?></strong></label>
            <input type="text" class="regular-text" name="sseo_mail_report_email" id="sseo_mail_report_email" value="<?php echo esc_attr((string)get_option('sseo_ai_report_email', '')); ?>" placeholder="<?php echo esc_attr((string)get_option('admin_email')); ?>">
            <p class="field-description"><?php esc_html_e('Separate multiple addresses with a comma. Empty = the administrator address of this site.', 'ai-seo-client'); ?></p>
        </div>

        <div class="fyn-settings-field">
            <strong><?php esc_html_e('Notifications', 'ai-seo-client'); ?></strong>
            <label style="display:block;margin-top:6px;"><input type="checkbox" name="sseo_mail_serp" value="1" <?php checked(self::isEnabled('serp')); ?>> <?php esc_html_e('Ranking drops (SERP Monitor, when the monitor is switched on)', 'ai-seo-client'); ?></label>
            <?php if ($apexAvailable) : ?>
            <label style="display:block;"><input type="checkbox" name="sseo_mail_apexflow" value="1" <?php checked(self::isEnabled('apexflow')); ?>> <?php esc_html_e('ApexFlow: weekly summary of scheduled posts', 'ai-seo-client'); ?></label>
            <?php endif; ?>
            <label style="display:block;"><input type="checkbox" name="sseo_mail_calendar" value="1" <?php checked(self::isEnabled('calendar')); ?>> <?php esc_html_e('Content calendar: assignments and review requests', 'ai-seo-client'); ?></label>
            <label style="display:block;"><input type="checkbox" name="sseo_mail_alerts" value="1" <?php checked(!empty($settings['alerts'])); ?>> <?php esc_html_e('Technical errors (e.g. an AI service that does not respond)', 'ai-seo-client'); ?></label>
            <label style="display:block;margin-left:24px;font-size:13px;"><?php esc_html_e('At most', 'ai-seo-client'); ?>
                <input type="number" name="sseo_mail_alerts_max" min="1" max="50" value="<?php echo (int)self::alertsMaxPerDay(); ?>" style="width:64px;">
                <?php esc_html_e('error e-mails per day (identical errors at most once per hour)', 'ai-seo-client'); ?></label>
            <p class="field-description"><?php esc_html_e('Notifications go to the administrator address of this site; calendar e-mails go to the assigned user.', 'ai-seo-client'); ?></p>
        </div>

        <div class="fyn-settings-field">
            <label for="sseo_mail_from_name"><strong><?php esc_html_e('Sender name', 'ai-seo-client'); ?></strong></label>
            <input type="text" class="regular-text" name="sseo_mail_from_name" id="sseo_mail_from_name" value="<?php echo esc_attr((string)$settings['from_name']); ?>" placeholder="<?php echo esc_attr(self::fromName()); ?>">
            <label for="sseo_mail_from_email" style="margin-top:10px;display:block;"><strong><?php esc_html_e('Sender address', 'ai-seo-client'); ?></strong></label>
            <input type="email" class="regular-text" name="sseo_mail_from_email" id="sseo_mail_from_email" value="<?php echo esc_attr((string)$settings['from_email']); ?>" placeholder="<?php esc_attr_e('Leave empty to use the address of WordPress / your SMTP plugin', 'ai-seo-client'); ?>">
            <p class="field-description"><?php esc_html_e('Only applies to e-mails from this plugin. Use an address on your own domain that your mail server is allowed to send from, otherwise mail may be rejected.', 'ai-seo-client'); ?></p>
        </div>

        <div class="fyn-settings-field">
            <button type="button" class="fyn-btn fyn-btn-outline" data-sseo-mail-test="simple"><?php esc_html_e('Send test e-mail', 'ai-seo-client'); ?></button>
            <button type="button" class="fyn-btn fyn-btn-outline" data-sseo-mail-test="report"><?php esc_html_e('Send test report now', 'ai-seo-client'); ?></button>
            <span id="sseo-mail-test-result" style="margin-left:8px;font-size:13px;" role="status"></span>
            <p class="field-description"><?php esc_html_e('Uses the saved settings and sends to the report recipients. Save your changes first.', 'ai-seo-client'); ?></p>
        </div>
        <script>
        (function () {
            var nonce = <?php echo wp_json_encode(wp_create_nonce('sseo_ai_send_test_email')); ?>;
            var sending = <?php echo wp_json_encode(__('Sending…', 'ai-seo-client')); ?>;
            document.querySelectorAll('[data-sseo-mail-test]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var result = document.getElementById('sseo-mail-test-result');
                    result.textContent = sending;
                    button.disabled = true;
                    var body = new URLSearchParams({action: 'sseo_ai_send_test_email', nonce: nonce, kind: button.getAttribute('data-sseo-mail-test')});
                    fetch(window.ajaxurl, {method: 'POST', credentials: 'same-origin', body: body})
                        .then(function (response) { return response.json(); })
                        .then(function (json) { result.textContent = (json && json.data && json.data.message) || ''; result.style.color = json && json.success ? '#047857' : '#b91c1c'; })
                        .catch(function () { result.textContent = 'Error'; result.style.color = '#b91c1c'; })
                        .finally(function () { button.disabled = false; });
                });
            });
        })();
        </script>
        <?php
    }

    /** Called from Client::handleSettingsSave() (capability + nonce already checked there). */
    public function save(array $post): void
    {
        $frequency = sanitize_key((string)($post['sseo_mail_report_frequency'] ?? 'weekly'));
        update_option('sseo_ai_report_frequency', in_array($frequency, self::FREQUENCIES, true) ? $frequency : 'weekly');

        $emails = array_filter(array_map('sanitize_email', array_map('trim', explode(',', wp_unslash((string)($post['sseo_mail_report_email'] ?? ''))))), 'is_email');
        update_option('sseo_ai_report_email', implode(', ', array_unique($emails)));

        $fromEmail = sanitize_email(wp_unslash((string)($post['sseo_mail_from_email'] ?? '')));
        update_option(self::OPTION, [
            'from_name' => sanitize_text_field(wp_unslash((string)($post['sseo_mail_from_name'] ?? ''))),
            'from_email' => is_email($fromEmail) ? $fromEmail : '',
            'alerts' => !empty($post['sseo_mail_alerts']),
            'alerts_max' => max(1, min(50, (int)($post['sseo_mail_alerts_max'] ?? 5))),
        ]);

        $serp = get_option('sseo_ai_serp_monitor_settings', []);
        $serp = is_array($serp) ? $serp : [];
        $serp['notify_email'] = !empty($post['sseo_mail_serp']);
        update_option('sseo_ai_serp_monitor_settings', $serp);

        if (class_exists(ApexFlow::class)) {
            $apex = get_option('sseo_ai_apexflow_settings', false);
            // Only touch an existing ApexFlow config (a fresh one is created, with legacy migration, by ApexFlow itself).
            if (is_array($apex)) {
                $apex['notify_email'] = !empty($post['sseo_mail_apexflow']);
                update_option('sseo_ai_apexflow_settings', $apex);
            }
        }

        update_option('sseo_ai_notify_email', !empty($post['sseo_mail_calendar']));
    }

    // ------------------------------------------------------------------
    // Test mail
    // ------------------------------------------------------------------

    public function ajaxSendTest(): void
    {
        check_ajax_referer('sseo_ai_send_test_email', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'ai-seo-client')], 403);
        }

        $recipients = self::reportRecipients();
        if (($_POST['kind'] ?? '') === 'report' && $this->integrations !== null) {
            $frequency = self::reportFrequency();
            $sent = $this->integrations->sendReport($frequency === 'off' ? 'weekly' : $frequency, true);
        } else {
            $site = wp_specialchars_decode((string)get_bloginfo('name'), ENT_QUOTES);
            $sent = Mailer::send(
                'test',
                $recipients,
                sprintf(__('Test e-mail from %s', 'ai-seo-client'), $site),
                // Sentences translated separately: the .po → .mo tools do not unescape "\n" inside msgids.
                sprintf(__('This is a test e-mail from the %1$s plugin on %2$s.', 'ai-seo-client'), self::brandName(), home_url())
                . "\n\n" . __('If you received this, e-mail from this website arrives correctly.', 'ai-seo-client')
            );
        }

        if (!$sent) {
            wp_send_json_error(['message' => sprintf(__('Not sent: %s', 'ai-seo-client'), Mailer::lastError())]);
        }
        wp_send_json_success(['message' => sprintf(__('Sent to %s. Also check the spam folder.', 'ai-seo-client'), implode(', ', $recipients))]);
    }
}

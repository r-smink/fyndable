<?php

namespace SSEOAISaaS;

/**
 * Email Template Admin
 *
 * Adds an admin UI to list, edit and preview email templates.
 */
class EmailTemplateAdmin
{
    private EmailTemplateRepository $repository;
    private EmailTemplateRenderer $renderer;

    public function __construct(EmailTemplateRepository $repository, EmailTemplateRenderer $renderer)
    {
        $this->repository = $repository;
        $this->renderer = $renderer;
    }

    /**
     * Register the admin menu.
     */
    public function addMenu(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('Email Templates', 'sseo-ai-saas'),
            __('Email Templates', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-email-templates',
            [$this, 'renderPage']
        );
    }

    /**
     * Render the overview or edit page.
     */
    public function renderPage(): void
    {
        $action = sanitize_key($_GET['action'] ?? 'list');

        if ($action === 'edit' && !empty($_GET['template'])) {
            $this->renderEditPage(sanitize_key($_GET['template']));
            return;
        }

        $this->renderListPage();
    }

    /**
     * Render the templates list.
     */
    private function renderListPage(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('sseo_ai_email_templates')) {
            $this->handleBulkActions();
        }

        $templates = $this->repository->getAllTemplates();
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Email Templates', 'sseo-ai-saas'); ?></h1>
            <p><?php esc_html_e('Edit the content, colours and logo for every email the SaaS plugin sends.', 'sseo-ai-saas'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Template', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Layout', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Active', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Last updated', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="5"><?php esc_html_e('No templates found.', 'sseo-ai-saas'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?php echo esc_html($template['name']); ?></strong><br><code><?php echo esc_html($template['template_key']); ?></code></td>
                                <td><?php echo esc_html($this->repository->getLayouts()[$template['layout']] ?? $template['layout']); ?></td>
                                <td><?php echo !empty($template['is_active']) ? '✓' : '—'; ?></td>
                                <td><?php echo esc_html($template['updated_at']); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates&action=edit&template=' . $template['template_key'])); ?>" class="button button-small"><?php esc_html_e('Edit', 'sseo-ai-saas'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the edit page for a single template.
     */
    private function renderEditPage(string $templateKey): void
    {
        $template = $this->repository->getTemplate($templateKey);
        if (!$template) {
            wp_die(__('Template not found.', 'sseo-ai-saas'));
        }

        $saved = false;
        $preview = '';
        $testSent = false;
        $testError = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['sseo_ai_email_template_test']) && check_admin_referer('sseo_ai_email_template_test', 'sseo_ai_email_template_test')) {
                $testEmail = sanitize_email($_POST['test_email'] ?? '');
                if ($this->sendTestEmail($templateKey, $testEmail)) {
                    $testSent = true;
                } else {
                    $testError = __('Could not send test email. Check the address and template.', 'sseo-ai-saas');
                }
            } elseif (check_admin_referer('sseo_ai_email_template_edit')) {
                $data = [
                    'template_key' => $templateKey,
                    'name' => sanitize_text_field($_POST['name'] ?? $template['name']),
                    'subject' => sanitize_text_field($_POST['subject'] ?? $template['subject']),
                    'body_html' => wp_kses_post($_POST['body_html'] ?? ''),
                    'layout' => sanitize_key($_POST['layout'] ?? 'default'),
                    'brand_logo' => esc_url_raw($_POST['brand_logo'] ?? ''),
                    'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#379fd3'),
                    'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#8f39ac'),
                    'button_color' => sanitize_hex_color($_POST['button_color'] ?? ''),
                    'footer_text' => sanitize_textarea_field($_POST['footer_text'] ?? ''),
                    'is_active' => !empty($_POST['is_active']),
                ];
                $this->repository->saveTemplate($data);
                $template = $this->repository->getTemplate($templateKey);
                $saved = true;

                if (!empty($_POST['preview_template'])) {
                    $preview = $this->generatePreview($templateKey);
                }
            }
        }

        $layouts = $this->repository->getLayouts();
        $placeholders = $this->getPlaceholderGuide($templateKey);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php printf(esc_html__('Edit Template: %s', 'sseo-ai-saas'), esc_html($template['name'])); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates')); ?>">← <?php esc_html_e('Back to templates', 'sseo-ai-saas'); ?></a></p>

            <?php if ($saved): ?>
                <div class="notice notice-success"><p><?php esc_html_e('Template saved.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if ($testSent): ?>
                <div class="notice notice-success"><p><?php esc_html_e('Test email sent.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if ($testError): ?>
                <div class="notice notice-error"><p><?php echo esc_html($testError); ?></p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('sseo_ai_email_template_edit'); ?>
                <div class="sseo-ai-grid-2" style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
                    <div class="sseo-ai-card">
                        <h2><?php esc_html_e('Content', 'sseo-ai-saas'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="name"><?php esc_html_e('Name', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="name" name="name" value="<?php echo esc_attr($template['name']); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="subject"><?php esc_html_e('Subject', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="subject" name="subject" value="<?php echo esc_attr($template['subject']); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="body_html"><?php esc_html_e('Body HTML', 'sseo-ai-saas'); ?></label></th>
                                <td><textarea id="body_html" name="body_html" rows="14" class="large-text code"><?php echo esc_textarea($template['body_html']); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="footer_text"><?php esc_html_e('Footer text', 'sseo-ai-saas'); ?></label></th>
                                <td><textarea id="footer_text" name="footer_text" rows="3" class="large-text"><?php echo esc_textarea($template['footer_text']); ?></textarea></td>
                            </tr>
                        </table>
                    </div>
                    <div class="sseo-ai-card">
                        <h2><?php esc_html_e('Style', 'sseo-ai-saas'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="layout"><?php esc_html_e('Layout', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select id="layout" name="layout">
                                        <?php foreach ($layouts as $key => $label): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($template['layout'], $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="brand_logo"><?php esc_html_e('Logo URL', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="url" id="brand_logo" name="brand_logo" value="<?php echo esc_attr($template['brand_logo']); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="primary_color"><?php esc_html_e('Primary color', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($template['primary_color']); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="secondary_color"><?php esc_html_e('Secondary color', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="secondary_color" name="secondary_color" value="<?php echo esc_attr($template['secondary_color']); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="button_color"><?php esc_html_e('Button color', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="button_color" name="button_color" value="<?php echo esc_attr($template['button_color'] ?: $template['primary_color']); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="is_active"><?php esc_html_e('Active', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="checkbox" id="is_active" name="is_active" value="1" <?php checked($template['is_active'], 1); ?>></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="sseo-ai-card" style="margin-top:20px;">
                    <h2><?php esc_html_e('Available placeholders', 'sseo-ai-saas'); ?></h2>
                    <p><?php esc_html_e('Use the placeholders below in subject and body. They will be replaced when the email is sent.', 'sseo-ai-saas'); ?></p>
                    <code style="display:block;white-space:pre-wrap;background:#f9fafb;padding:10px;border-radius:4px;"><?php echo esc_html($placeholders); ?></code>
                </div>

                <p class="submit" style="display:flex;gap:10px;">
                    <?php submit_button(__('Save Template', 'sseo-ai-saas'), 'primary', 'submit', false); ?>
                    <?php submit_button(__('Save & Preview', 'sseo-ai-saas'), 'secondary', 'preview_template', false); ?>
                </p>
            </form>

            <?php if ($preview): ?>
                <div class="sseo-ai-card" style="margin-top:20px;">
                    <h2><?php esc_html_e('Preview', 'sseo-ai-saas'); ?></h2>
                    <div style="border:1px solid #e5e7eb;padding:10px;background:#fff;">
                        <?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- preview is escaped during rendering ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="sseo-ai-card" style="margin-top:20px;">
                <?php wp_nonce_field('sseo_ai_email_template_test', 'sseo_ai_email_template_test'); ?>
                <input type="hidden" name="template" value="<?php echo esc_attr($templateKey); ?>">
                <h2><?php esc_html_e('Send test email', 'sseo-ai-saas'); ?></h2>
                <p><?php esc_html_e('Enter an email address to receive a test of this template with sample data.', 'sseo-ai-saas'); ?></p>
                <p>
                    <input type="email" name="test_email" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" class="regular-text" required>
                </p>
                <?php submit_button(__('Send test email', 'sseo-ai-saas'), 'secondary', 'send_test_email', false); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Show a placeholder guide per template type.
     */
    private function getPlaceholderGuide(string $templateKey): string
    {
        $common = '{{site_name}}, {{admin_email}}, {{company_name}}, {{support_email}}, {{support_url}}, {{current_date}}, {{year}}';

        $specific = [
            'welcome' => '{{tenant_name}}, {{tenant_email}}, {{tenant_domain}}, {{license_key}}, {{tier}}, {{dashboard_url}}, {{support_url}}',
            'trial_expiring' => '{{days_left}}, {{expires_at}}, {{upgrade_url}}',
            'license_expired' => '{{tier}}, {{renew_url}}',
            'payment_receipt' => '{{amount}}, {{tier}}, {{payment_date}}, {{payment_id}}, {{receipt_url}}',
            'payment_failed' => '{{amount}}, {{retry_url}}, {{support_url}}',
            'usage_limit' => '{{current_tier}}, {{limit}}, {{used}}, {{upgrade_url}}',
            'support_new_ticket' => '{{tenant_name}}, {{tenant_email}}, {{tenant_domain}}, {{license_key}}, {{ticket_id}}, {{ticket_subject}}, {{ticket_message}}',
            'support_reply_customer' => '{{tenant_name}}, {{tenant_email}}, {{ticket_id}}, {{ticket_subject}}, {{reply_message}}',
            'support_reply_staff' => '{{staff_name}}, {{tenant_name}}, {{ticket_id}}, {{ticket_subject}}, {{reply_message}}',
            'admin_alert' => '{{alert_message}}, {{provider}}, {{error_code}}, {{status}}',
        ];

        return $common . "\n" . ($specific[$templateKey] ?? '');
    }

    /**
     * Send a test email with sample data for the current template.
     */
    private function sendTestEmail(string $templateKey, string $to): bool
    {
        if (empty($to) || !is_email($to)) {
            return false;
        }

        $dummy = $this->getDummyContext();
        $rendered = $this->renderer->render($templateKey, '', $dummy);

        if (empty($rendered['body'])) {
            return false;
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, __('TEST:', 'sseo-ai-saas') . ' ' . $rendered['subject'], $rendered['body'], $headers);
    }

    /**
     * Build a sample context for previews and test emails.
     */
    private function getDummyContext(): array
    {
        return [
            'tenant_name' => 'Acme Corp',
            'tenant_email' => 'customer@example.com',
            'tenant_domain' => 'example.com',
            'license_key' => 'FYN-1234-ABCD',
            'tier' => 'Professional',
            'current_tier' => 'Professional',
            'days_left' => '3',
            'expires_at' => date_i18n(get_option('date_format'), strtotime('+3 days')),
            'upgrade_url' => admin_url(),
            'renew_url' => admin_url(),
            'dashboard_url' => admin_url(),
            'support_url' => admin_url(),
            'receipt_url' => admin_url(),
            'retry_url' => admin_url(),
            'amount' => '€79,00',
            'payment_date' => date_i18n(get_option('date_format')),
            'payment_id' => 'pi_1234567890',
            'limit' => '1.000',
            'used' => '1.000',
            'ticket_id' => '42',
            'ticket_subject' => 'Login problem',
            'ticket_message' => 'I cannot access the dashboard after the latest update.',
            'reply_message' => 'We are looking into this and will update you shortly.',
            'staff_name' => 'Support Team',
            'alert_message' => '[ERROR] openrouter - rate_limited: Rate limit exceeded',
            'provider' => 'openrouter',
            'error_code' => 'rate_limited',
            'status' => 'error',
        ];
    }

    /**
     * Generate a preview for the current template.
     */
    private function generatePreview(string $templateKey): string
    {
        $dummy = $this->getDummyContext();
        $rendered = $this->renderer->render($templateKey, '', $dummy);
        return $rendered['body'];
    }

    /**
     * Handle any future bulk/list actions.
     */
    private function handleBulkActions(): void
    {
        // Reserved for reset-to-default, activate/deactivate, export, etc.
    }
}

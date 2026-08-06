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

        if ($action === 'layouts') {
            $this->renderLayoutsPage();
            return;
        }

        if ($action === 'edit_layout') {
            $this->renderEditLayoutPage(sanitize_key($_GET['layout'] ?? ''));
            return;
        }

        if ($action === 'delete_layout' && !empty($_GET['layout'])) {
            $this->handleDeleteLayout(sanitize_key($_GET['layout']));
            return;
        }

        if ($action === 'brand') {
            $this->renderBrandPage();
            return;
        }

        $this->renderListPage();
    }

    /**
     * Register the global email brand settings.
     */
    public function registerSettings(): void
    {
        $settings = [
            'sseo_ai_saas_email_brand_logo',
            'sseo_ai_saas_email_brand_company_name',
            'sseo_ai_saas_email_brand_company_address',
            'sseo_ai_saas_email_brand_company_postal_code',
            'sseo_ai_saas_email_brand_company_city',
            'sseo_ai_saas_email_brand_company_country',
            'sseo_ai_saas_email_brand_company_vat',
            'sseo_ai_saas_email_brand_company_kvk',
            'sseo_ai_saas_email_brand_company_iban',
            'sseo_ai_saas_email_brand_company_email',
            'sseo_ai_saas_email_brand_company_website',
            'sseo_ai_saas_email_brand_primary_color',
            'sseo_ai_saas_email_brand_secondary_color',
            'sseo_ai_saas_email_brand_button_color',
            'sseo_ai_saas_email_brand_footer_text',
        ];
        foreach ($settings as $name) {
            register_setting('sseo_ai_saas_email_brand', $name);
        }
    }

    /**
     * Render the global brand settings page.
     */
    private function renderBrandPage(): void
    {
        wp_enqueue_media();
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('sseo_ai_email_brand_save')) {
            $logoId = (int) ($_POST['brand_logo_id'] ?? 0);
            update_option('sseo_ai_saas_email_brand_logo', $logoId);
            update_option('sseo_ai_saas_email_brand_company_name', sanitize_text_field($_POST['company_name'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_address', sanitize_textarea_field($_POST['company_address'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_postal_code', sanitize_text_field($_POST['company_postal_code'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_city', sanitize_text_field($_POST['company_city'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_country', sanitize_text_field($_POST['company_country'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_vat', sanitize_text_field($_POST['company_vat'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_kvk', sanitize_text_field($_POST['company_kvk'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_iban', sanitize_text_field($_POST['company_iban'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_email', sanitize_email($_POST['company_email'] ?? ''));
            update_option('sseo_ai_saas_email_brand_company_website', esc_url_raw($_POST['company_website'] ?? ''));
            update_option('sseo_ai_saas_email_brand_primary_color', sanitize_hex_color($_POST['primary_color'] ?? '#379fd3'));
            update_option('sseo_ai_saas_email_brand_secondary_color', sanitize_hex_color($_POST['secondary_color'] ?? '#8f39ac'));
            update_option('sseo_ai_saas_email_brand_button_color', sanitize_hex_color($_POST['button_color'] ?? ''));
            update_option('sseo_ai_saas_email_brand_footer_text', sanitize_textarea_field($_POST['footer_text'] ?? ''));
            $saved = true;
        }

        $logoId = (int) get_option('sseo_ai_saas_email_brand_logo', 0);
        $logoUrl = $logoId > 0 ? wp_get_attachment_url($logoId) : '';
        $companyName = get_option('sseo_ai_saas_email_brand_company_name', '');
        $companyAddress = get_option('sseo_ai_saas_email_brand_company_address', '');
        $companyPostalCode = get_option('sseo_ai_saas_email_brand_company_postal_code', '');
        $companyCity = get_option('sseo_ai_saas_email_brand_company_city', '');
        $companyCountry = get_option('sseo_ai_saas_email_brand_company_country', '');
        $companyVat = get_option('sseo_ai_saas_email_brand_company_vat', '');
        $companyKvk = get_option('sseo_ai_saas_email_brand_company_kvk', '');
        $companyIban = get_option('sseo_ai_saas_email_brand_company_iban', '');
        $companyEmail = get_option('sseo_ai_saas_email_brand_company_email', '');
        $companyWebsite = get_option('sseo_ai_saas_email_brand_company_website', '');
        $primaryColor = get_option('sseo_ai_saas_email_brand_primary_color', '#379fd3');
        $secondaryColor = get_option('sseo_ai_saas_email_brand_secondary_color', '#8f39ac');
        $buttonColor = get_option('sseo_ai_saas_email_brand_button_color', '');
        $footerText = get_option('sseo_ai_saas_email_brand_footer_text', '');
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Global Email Brand', 'sseo-ai-saas'); ?></h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates')); ?>">← <?php esc_html_e('Back to templates', 'sseo-ai-saas'); ?></a></p>

            <?php if ($saved): ?>
                <div class="notice notice-success"><p><?php esc_html_e('Brand settings saved. These apply to all email templates unless a template overrides them.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <p class="description" style="margin:0 0 15px;max-width:640px;">
                <?php esc_html_e('Set your logo, company details and colours once here. Every email template will use these values automatically. Individual templates can still override the logo and colours if you fill them in on the template edit page.', 'sseo-ai-saas'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('sseo_ai_email_brand_save'); ?>
                <div class="sseo-ai-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div class="sseo-ai-card">
                        <h2><?php esc_html_e('Logo & Colours', 'sseo-ai-saas'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="brand_logo"><?php esc_html_e('Logo', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <div id="brand-logo-preview" style="margin-bottom:8px;">
                                        <?php if ($logoUrl): ?>
                                            <img src="<?php echo esc_url($logoUrl); ?>" style="max-height:60px;max-width:200px;border:1px solid #e5e7eb;border-radius:4px;padding:4px;background:#fff;">
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" id="brand_logo_id" name="brand_logo_id" value="<?php echo esc_attr($logoId); ?>">
                                    <button type="button" class="button" id="brand_logo_upload"><?php esc_html_e('Choose Logo', 'sseo-ai-saas'); ?></button>
                                    <button type="button" class="button" id="brand_logo_remove" <?php echo $logoId ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove', 'sseo-ai-saas'); ?></button>
                                    <p class="description"><?php esc_html_e('Upload a logo image. Used in the email header when a template has no logo of its own.', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="primary_color"><?php esc_html_e('Primary colour', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($primaryColor); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="secondary_color"><?php esc_html_e('Secondary colour', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="secondary_color" name="secondary_color" value="<?php echo esc_attr($secondaryColor); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="button_color"><?php esc_html_e('Button colour', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="button_color" name="button_color" value="<?php echo esc_attr($buttonColor ?: $primaryColor); ?>"></td>
                            </tr>
                        </table>
                    </div>

                    <div class="sseo-ai-card">
                        <h2><?php esc_html_e('Company Details', 'sseo-ai-saas'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="company_name"><?php esc_html_e('Company name', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($companyName); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_address"><?php esc_html_e('Street address', 'sseo-ai-saas'); ?></label></th>
                                <td><textarea id="company_address" name="company_address" rows="2" class="large-text"><?php echo esc_textarea($companyAddress); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="company_postal_code"><?php esc_html_e('Postal code', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_postal_code" name="company_postal_code" value="<?php echo esc_attr($companyPostalCode); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_city"><?php esc_html_e('City', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_city" name="company_city" value="<?php echo esc_attr($companyCity); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_country"><?php esc_html_e('Country', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_country" name="company_country" value="<?php echo esc_attr($companyCountry); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_vat"><?php esc_html_e('VAT number', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_vat" name="company_vat" value="<?php echo esc_attr($companyVat); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_kvk"><?php esc_html_e('Chamber of Commerce (KvK)', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_kvk" name="company_kvk" value="<?php echo esc_attr($companyKvk); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_iban"><?php esc_html_e('IBAN', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="company_iban" name="company_iban" value="<?php echo esc_attr($companyIban); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_email"><?php esc_html_e('Contact email', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="email" id="company_email" name="company_email" value="<?php echo esc_attr($companyEmail); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="company_website"><?php esc_html_e('Website', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="url" id="company_website" name="company_website" value="<?php echo esc_attr($companyWebsite); ?>" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="sseo-ai-card" style="margin-top:20px;">
                    <h2><?php esc_html_e('Footer text', 'sseo-ai-saas'); ?></h2>
                    <p class="description"><?php esc_html_e('Optional extra text shown in the email footer (e.g. a tagline or legal note). Company details above are shown automatically.', 'sseo-ai-saas'); ?></p>
                    <textarea name="footer_text" rows="3" class="large-text"><?php echo esc_textarea($footerText); ?></textarea>
                </div>

                <p class="submit">
                    <?php submit_button(__('Save Brand Settings', 'sseo-ai-saas'), 'primary', 'submit', false); ?>
                </p>
            </form>

            <script>
            (function() {
                var frame;
                document.getElementById('brand_logo_upload').addEventListener('click', function(e) {
                    e.preventDefault();
                    if (frame) { frame.open(); return; }
                    frame = wp.media({
                        title: '<?php echo esc_js(__('Choose Logo', 'sseo-ai-saas')); ?>',
                        button: { text: '<?php echo esc_js(__('Use as logo', 'sseo-ai-saas')); ?>' },
                        library: { type: 'image' },
                        multiple: false
                    });
                    frame.on('select', function() {
                        var att = frame.state().get('selection').first().toJSON();
                        document.getElementById('brand_logo_id').value = att.id;
                        var prev = document.getElementById('brand_logo_preview');
                        prev.innerHTML = '<img src="' + att.url + '" style="max-height:60px;max-width:200px;border:1px solid #e5e7eb;border-radius:4px;padding:4px;background:#fff;">';
                        document.getElementById('brand_logo_remove').style.display = '';
                    });
                    frame.open();
                });
                document.getElementById('brand_logo_remove').addEventListener('click', function(e) {
                    e.preventDefault();
                    document.getElementById('brand_logo_id').value = '0';
                    document.getElementById('brand_logo_preview').innerHTML = '';
                    this.style.display = 'none';
                });
            })();
            </script>
        </div>
        <?php
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
            <div class="sseo-ai-card">
                <p class="description" style="margin:0 0 15px;"><?php esc_html_e('Edit the content, colours and logo for every email the SaaS plugin sends.', 'sseo-ai-saas'); ?></p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates&action=layouts')); ?>" class="button button-primary">
                        <?php esc_html_e('Manage Layouts', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates&action=brand')); ?>" class="button">
                        <?php esc_html_e('Global Brand Settings', 'sseo-ai-saas'); ?>
                    </a>
                </p>
            </div>
            <div class="sseo-ai-card" style="padding:0;overflow:hidden;">
                <table class="wp-list-table widefat fixed striped" style="border:none;">
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
                                <td><input type="text" id="name" name="name" value="<?php echo esc_attr($template['name'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="subject"><?php esc_html_e('Subject', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="text" id="subject" name="subject" value="<?php echo esc_attr($template['subject'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="body_html"><?php esc_html_e('Body HTML', 'sseo-ai-saas'); ?></label></th>
                                <td><textarea id="body_html" name="body_html" rows="14" class="large-text code"><?php echo esc_textarea($template['body_html'] ?? ''); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="footer_text"><?php esc_html_e('Footer text', 'sseo-ai-saas'); ?></label></th>
                                <td><textarea id="footer_text" name="footer_text" rows="3" class="large-text"><?php echo esc_textarea($template['footer_text'] ?? ''); ?></textarea></td>
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
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($template['layout'] ?? 'default', $key); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="brand_logo"><?php esc_html_e('Logo URL', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="url" id="brand_logo" name="brand_logo" value="<?php echo esc_attr($template['brand_logo'] ?? ''); ?>" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label for="primary_color"><?php esc_html_e('Primary color', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($template['primary_color'] ?? '#379fd3'); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="secondary_color"><?php esc_html_e('Secondary color', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="secondary_color" name="secondary_color" value="<?php echo esc_attr($template['secondary_color'] ?? '#8f39ac'); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="button_color"><?php esc_html_e('Button color', 'sseo-ai-saas'); ?></label></th>
                                <td><input type="color" id="button_color" name="button_color" value="<?php echo esc_attr($template['button_color'] ?: $template['primary_color'] ?? '#379fd3'); ?>"></td>
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
        $common = '{{site_name}}, {{admin_email}}, {{company_name}}, {{company_address}}, {{company_postal_code}}, {{company_city}}, {{company_country}}, {{company_vat}}, {{company_kvk}}, {{company_iban}}, {{company_email}}, {{company_website}}, {{support_email}}, {{support_url}}, {{current_date}}, {{year}}';

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
     * Render the layout management list.
     */
    private function renderLayoutsPage(): void
    {
        $builtIn = ['default', 'minimal', 'announcement'];
        $custom = $this->repository->getCustomLayouts();
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Email Layouts', 'sseo-ai-saas'); ?></h1>
            <p><?php esc_html_e('Custom layouts can be added, edited or removed. Built-in layouts cannot be deleted.', 'sseo-ai-saas'); ?></p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates&action=edit_layout')); ?>" class="button">
                    <?php esc_html_e('Add Layout', 'sseo-ai-saas'); ?>
                </a>
            </p>

            <h2><?php esc_html_e('Built-in layouts', 'sseo-ai-saas'); ?></h2>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Slug', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($builtIn as $slug): ?>
                        <tr>
                            <td><code><?php echo esc_html($slug); ?></code></td>
                            <td><?php echo esc_html($this->repository->getLayouts()[$slug] ?? $slug); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Custom layouts', 'sseo-ai-saas'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Slug', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($custom)): ?>
                        <tr><td colspan="3"><?php esc_html_e('No custom layouts found.', 'sseo-ai-saas'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($custom as $slug => $data): ?>
                            <tr>
                                <td><code><?php echo esc_html($slug); ?></code></td>
                                <td><?php echo esc_html($data['name'] ?? $slug); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-email-templates&action=edit_layout&layout=' . $slug)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'sseo-ai-saas'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sseo-ai-email-templates&action=delete_layout&layout=' . $slug), 'sseo_ai_email_layout_delete')); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e('Delete this layout?', 'sseo-ai-saas'); ?>');">
                                        <?php esc_html_e('Delete', 'sseo-ai-saas'); ?>
                                    </a>
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
     * Render the add/edit layout form.
     */
    private function renderEditLayoutPage(string $layoutKey): void
    {
        $builtIn = ['default', 'minimal', 'announcement'];
        $isNew = empty($layoutKey);
        $saved = false;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('sseo_ai_email_layout_edit')) {
            $slug = sanitize_key($_POST['layout_slug'] ?? $layoutKey);
            $name = sanitize_text_field($_POST['layout_name'] ?? '');
            $html = wp_kses_post($_POST['layout_html'] ?? '');

            if (!empty($slug) && !in_array($slug, $builtIn, true)) {
                $this->repository->saveLayout($slug, ['name' => $name, 'html' => $html]);
                $saved = true;
                $layoutKey = $slug;
            }
        }

        $layout = $this->repository->getLayoutConfig($layoutKey);
        if (!$layout) {
            $layout = [
                'name' => '',
                'html' => $this->getDefaultLayoutHtml(),
            ];
        }
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php echo $isNew ? esc_html__('Add Layout', 'sseo-ai-saas') : esc_html__('Edit Layout', 'sseo-ai-saas'); ?></h1>
            <?php if ($saved): ?>
                <div class="notice notice-success"><p><?php esc_html_e('Layout saved.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field('sseo_ai_email_layout_edit'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="layout_slug"><?php esc_html_e('Slug', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="text" id="layout_slug" name="layout_slug" value="<?php echo esc_attr($layoutKey); ?>" class="regular-text" <?php echo $isNew ? '' : 'readonly'; ?>>
                            <p class="description"><?php esc_html_e('A unique machine name, e.g. "newsletter" or "promo".', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="layout_name"><?php esc_html_e('Name', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="text" id="layout_name" name="layout_name" value="<?php echo esc_attr($layout['name'] ?? ''); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="layout_html"><?php esc_html_e('Layout HTML', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <textarea id="layout_html" name="layout_html" rows="20" class="large-text code" style="font-family:monospace;"><?php echo esc_textarea($layout['html'] ?? ''); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Use {{content}} for the email body. Available placeholders: {{site_name}}, {{company_logo}}, {{primary_color}}, {{secondary_color}}, {{button_color}}, {{footer_text}}.', 'sseo-ai-saas'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button($isNew ? __('Add Layout', 'sseo-ai-saas') : __('Save Layout', 'sseo-ai-saas')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Delete a custom layout after nonce verification.
     */
    private function handleDeleteLayout(string $layoutKey): void
    {
        if (in_array($layoutKey, ['default', 'minimal', 'announcement'], true)) {
            wp_die(__('Built-in layouts cannot be deleted.', 'sseo-ai-saas'));
        }

        if (check_admin_referer('sseo_ai_email_layout_delete')) {
            $this->repository->deleteLayout($layoutKey);
        }

        wp_redirect(admin_url('admin.php?page=sseo-ai-email-templates&action=layouts'));
        exit;
    }

    /**
     * Get a starter HTML template for new custom layouts.
     */
    private function getDefaultLayoutHtml(): string
    {
        return "<!DOCTYPE html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'></head>\n"
            . "<body style='margin:0;padding:0;font-family:Outfit,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f3f4f6;'>\n"
            . "<div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);'>\n"
            . "<div style='background:linear-gradient(135deg, {{primary_color}} 0%, {{secondary_color}} 100%);padding:30px 40px;text-align:center;'>\n"
            . "    <h1 style='color:#fff;margin:0;font-size:24px;font-weight:700;'>{{site_name}}</h1>\n"
            . "</div>\n"
            . "<div style='padding:30px 40px;'>\n"
            . "    {{content}}\n"
            . "</div>\n"
            . "<div style='padding:20px 40px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;'>\n"
            . "    <p style='margin:0;font-size:13px;color:#9ca3af;'>{{site_name}} — " . __('This is an automated email, please do not reply.', 'sseo-ai-saas') . "</p>\n"
            . "</div>\n"
            . "</div>\n"
            . "</body></html>";
    }

    /**
     * Handle any future bulk/list actions.
     */
    private function handleBulkActions(): void
    {
        // Reserved for reset-to-default, activate/deactivate, export, etc.
    }
}

<?php

namespace SSEOAISaaS;

/**
 * Email Template Repository
 *
 * Stores and retrieves editable email templates for the SaaS dashboard.
 */
class EmailTemplateRepository
{
    private const TABLE = 'sseo_ai_email_templates';

    /**
     * Create the email templates table if it does not exist.
     */
    public function maybeCreateTables(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_key varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            body_html longtext NOT NULL,
            layout varchar(32) NOT NULL DEFAULT 'default',
            brand_logo varchar(255) DEFAULT NULL,
            primary_color varchar(7) DEFAULT '#379fd3',
            secondary_color varchar(7) DEFAULT '#8f39ac',
            button_color varchar(7) DEFAULT NULL,
            footer_text text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_key (template_key),
            KEY is_active (is_active)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Seed default templates based on the previously hardcoded emails.
     */
    public function seedDefaults(): void
    {
        $seedVersion = 1;
        if ((int)get_option('sseo_ai_email_templates_seed_version', 0) >= $seedVersion) {
            return;
        }

        $defaults = [
            [
                'template_key' => 'welcome',
                'name' => __('Welcome Email', 'sseo-ai-saas'),
                'subject' => __('Welcome to {{site_name}} — Your SEO Journey Starts Here', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('Welcome aboard!', 'sseo-ai-saas') . "</h2>\n<p>" . sprintf(__('Your <strong>{{tier}}</strong> license is now active. You\'re ready to supercharge your SEO with {{site_name}}.', 'sseo-ai-saas')) . "</p>\n<div class='license-box'>\n<p class='label'>" . __('Your License Key', 'sseo-ai-saas') . "</p>\n<p class='value'>{{license_key}}</p>\n</div>\n<a href='{{dashboard_url}}' class='button'>" . __('Go to Dashboard', 'sseo-ai-saas') . "</a>\n<p class='help'>" . __('Need help? <a href=\"{{support_url}}\">Contact support</a>', 'sseo-ai-saas') . "</p>",
                'layout' => 'default',
            ],
            [
                'template_key' => 'trial_expiring',
                'name' => __('Trial Expiring Email', 'sseo-ai-saas'),
                'subject' => __('Your trial expires in {{days_left}} day(s)', 'sseo-ai-saas'),
                'body_html' => "<h2>{{days_left}}</h2>\n<p>" . __('Don\'t lose access to your SEO tools. Upgrade now to keep all features running smoothly.', 'sseo-ai-saas') . "</p>\n<a href='{{upgrade_url}}' class='button'>" . __('Upgrade Now', 'sseo-ai-saas') . "</a>",
                'layout' => 'default',
            ],
            [
                'template_key' => 'license_expired',
                'name' => __('License Expired Email', 'sseo-ai-saas'),
                'subject' => __('Your {{site_name}} License Has Expired', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('Your license has expired', 'sseo-ai-saas') . "</h2>\n<p>" . sprintf(__('Your <strong>{{tier}}</strong> license has expired. Renew now to restore access to all SEO features.', 'sseo-ai-saas')) . "</p>\n<a href='{{renew_url}}' class='button'>" . __('Renew License', 'sseo-ai-saas') . "</a>",
                'layout' => 'default',
            ],
            [
                'template_key' => 'payment_receipt',
                'name' => __('Payment Receipt', 'sseo-ai-saas'),
                'subject' => __('Payment Receipt — {{amount}}', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('Payment Confirmation', 'sseo-ai-saas') . "</h2>\n<div class='success-box'>\n<p class='amount'>{{amount}}</p>\n<p class='status'>" . __('Payment successful', 'sseo-ai-saas') . "</p>\n</div>\n<table class='details'>\n<tr><td>" . __('Plan', 'sseo-ai-saas') . "</td><td>{{tier}}</td></tr>\n<tr><td>" . __('Date', 'sseo-ai-saas') . "</td><td>{{payment_date}}</td></tr>\n<tr><td>" . __('Transaction ID', 'sseo-ai-saas') . "</td><td>{{payment_id}}</td></tr>\n</table>\n<a href='{{receipt_url}}' class='button'>" . __('View Details', 'sseo-ai-saas') . "</a>",
                'layout' => 'default',
            ],
            [
                'template_key' => 'payment_failed',
                'name' => __('Payment Failed', 'sseo-ai-saas'),
                'subject' => __('Payment Failed — Action Required', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('Payment could not be processed', 'sseo-ai-saas') . "</h2>\n<p>" . sprintf(__('We were unable to process your payment of {{amount}}. Please update your payment method to avoid service interruption.', 'sseo-ai-saas')) . "</p>\n<a href='{{retry_url}}' class='button' style='background:#ef4444'>" . __('Update Payment', 'sseo-ai-saas') . "</a>",
                'layout' => 'default',
            ],
            [
                'template_key' => 'usage_limit',
                'name' => __('Usage Limit Reached', 'sseo-ai-saas'),
                'subject' => __('You\'ve Reached Your Monthly API Limit', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('Monthly API limit reached', 'sseo-ai-saas') . "</h2>\n<p>" . sprintf(__('You\'ve used {{used}} of {{limit}} API calls on your <strong>{{current_tier}}</strong> plan this month. Upgrade to get more calls and unlock additional features.', 'sseo-ai-saas')) . "</p>\n<a href='{{upgrade_url}}' class='button'>" . __('Upgrade Plan', 'sseo-ai-saas') . "</a>",
                'layout' => 'default',
            ],
            [
                'template_key' => 'support_new_ticket',
                'name' => __('Support: New Ticket (admin)', 'sseo-ai-saas'),
                'subject' => __('[{{site_name}}] New support ticket #{{ticket_id}} from {{tenant_name}}', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('New support ticket received', 'sseo-ai-saas') . "</h2>\n<p><strong>" . __('Tenant', 'sseo-ai-saas') . ":</strong> {{tenant_name}}</p>\n<p><strong>" . __('Email', 'sseo-ai-saas') . ":</strong> {{tenant_email}}</p>\n<p><strong>" . __('License', 'sseo-ai-saas') . ":</strong> {{license_key}}</p>\n<p><strong>" . __('Subject', 'sseo-ai-saas') . ":</strong> {{ticket_subject}}</p>\n<hr>\n<p>{{ticket_message}}</p>",
                'layout' => 'minimal',
            ],
            [
                'template_key' => 'support_reply_customer',
                'name' => __('Support: Reply to Customer', 'sseo-ai-saas'),
                'subject' => __('[{{site_name}}] Reply to your support ticket #{{ticket_id}}', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('A reply has been added to your support ticket', 'sseo-ai-saas') . "</h2>\n<p><strong>" . __('Ticket', 'sseo-ai-saas') . ":</strong> #{{ticket_id}} - {{ticket_subject}}</p>\n<p><strong>" . __('From', 'sseo-ai-saas') . ":</strong> {{tenant_name}}</p>\n<hr>\n<p>{{reply_message}}</p>",
                'layout' => 'minimal',
            ],
            [
                'template_key' => 'support_reply_staff',
                'name' => __('Support: Reply to Staff', 'sseo-ai-saas'),
                'subject' => __('[{{site_name}}] New reply on ticket #{{ticket_id}} from {{tenant_name}}', 'sseo-ai-saas'),
                'body_html' => "<h2>" . __('A client replied to a support ticket', 'sseo-ai-saas') . "</h2>\n<p><strong>" . __('Tenant', 'sseo-ai-saas') . ":</strong> {{tenant_name}}</p>\n<p><strong>" . __('Ticket', 'sseo-ai-saas') . ":</strong> #{{ticket_id}} - {{ticket_subject}}</p>\n<hr>\n<p>{{reply_message}}</p>",
                'layout' => 'minimal',
            ],
            [
                'template_key' => 'admin_alert',
                'name' => __('Admin: Technical Alert', 'sseo-ai-saas'),
                'subject' => __('{{company_name}} Alert', 'sseo-ai-saas'),
                'body_html' => "<pre>{{alert_message}}</pre>",
                'layout' => 'minimal',
            ],
        ];

        foreach ($defaults as $default) {
            if ($this->getTemplate($default['template_key'])) {
                continue;
            }
            $this->insertTemplate(array_merge($default, ['is_default' => 1, 'is_active' => 1]));
        }

        update_option('sseo_ai_email_templates_seed_version', $seedVersion, false);
    }

    /**
     * Retrieve a single template by key.
     */
    public function getTemplate(string $key): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE template_key = %s",
            $key
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * Retrieve all templates ordered by name.
     */
    public function getAllTemplates(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A) ?: [];
    }

    /**
     * Get built-in layout labels.
     */
    private function getBuiltInLayouts(): array
    {
        return [
            'default' => __('Default', 'sseo-ai-saas'),
            'minimal' => __('Minimal', 'sseo-ai-saas'),
            'announcement' => __('Announcement', 'sseo-ai-saas'),
        ];
    }

    /**
     * Get custom layouts stored in options.
     */
    public function getCustomLayouts(): array
    {
        return get_option('sseo_ai_email_layouts', []);
    }

    /**
     * Get available layout labels (built-in + custom).
     */
    public function getLayouts(): array
    {
        $custom = [];
        foreach ($this->getCustomLayouts() as $slug => $data) {
            $custom[$slug] = !empty($data['name']) ? $data['name'] : $slug;
        }
        return array_merge($this->getBuiltInLayouts(), $custom);
    }

    /**
     * Get a custom layout definition by slug.
     */
    public function getLayoutConfig(string $slug): ?array
    {
        $layouts = $this->getCustomLayouts();
        return $layouts[$slug] ?? null;
    }

    /**
     * Save a custom layout.
     */
    public function saveLayout(string $slug, array $data): void
    {
        $allowed = wp_kses_allowed_html('post');
        $allowed['html'] = [];
        $allowed['head'] = [];
        $allowed['body'] = [];
        $allowed['meta'] = ['charset' => true, 'name' => true, 'content' => true];
        $allowed['style'] = [];
        $allowed['title'] = [];

        $layouts = $this->getCustomLayouts();
        $layouts[$slug] = [
            'name' => sanitize_text_field($data['name'] ?? $slug),
            'html' => wp_kses($data['html'] ?? '', $allowed),
        ];
        update_option('sseo_ai_email_layouts', $layouts);
    }

    /**
     * Delete a custom layout.
     */
    public function deleteLayout(string $slug): void
    {
        $layouts = $this->getCustomLayouts();
        unset($layouts[$slug]);
        update_option('sseo_ai_email_layouts', $layouts);
    }

    /**
     * Save or update a template.
     */
    public function saveTemplate(array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $row = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'subject' => sanitize_text_field($data['subject'] ?? ''),
            'body_html' => wp_kses_post($data['body_html'] ?? ''),
            'layout' => sanitize_key($data['layout'] ?? 'default'),
            'brand_logo' => esc_url_raw($data['brand_logo'] ?? ''),
            'primary_color' => sanitize_hex_color($data['primary_color'] ?? '#379fd3'),
            'secondary_color' => sanitize_hex_color($data['secondary_color'] ?? '#8f39ac'),
            'button_color' => sanitize_hex_color($data['button_color'] ?? ''),
            'footer_text' => sanitize_textarea_field($data['footer_text'] ?? ''),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if (!empty($data['template_key'])) {
            $existing = $this->getTemplate($data['template_key']);
            if ($existing) {
                return false !== $wpdb->update($table, $row, ['template_key' => $data['template_key']], null, ['%s']);
            }
            $row['template_key'] = sanitize_key($data['template_key']);
            $row['is_default'] = 0;
            return false !== $this->insertTemplate($row);
        }

        return false;
    }

    /**
     * Insert a template row.
     */
    private function insertTemplate(array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return false !== $wpdb->insert($table, [
            'template_key' => $data['template_key'],
            'name' => $data['name'] ?? '',
            'subject' => $data['subject'] ?? '',
            'body_html' => $data['body_html'] ?? '',
            'layout' => $data['layout'] ?? 'default',
            'brand_logo' => $data['brand_logo'] ?? null,
            'primary_color' => $data['primary_color'] ?? '#379fd3',
            'secondary_color' => $data['secondary_color'] ?? '#8f39ac',
            'button_color' => $data['button_color'] ?? null,
            'footer_text' => $data['footer_text'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'is_default' => $data['is_default'] ?? 0,
        ]);
    }
}

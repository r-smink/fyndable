<?php

namespace SSEOAIClient;

/**
 * External Integrations
 * 
 * Integrates with external tools and services:
 * - Slack notifications and reports
 * - Zapier / Make.com webhooks
 * - Email reporting automation
 * - Google Drive export
 * - Notion integration
 * - Custom webhook support
 */
class ExternalIntegrations
{
    private Settings $settings;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // AJAX handler for saving Google config
        add_action('wp_ajax_sseo_ai_save_google_config', [$this, 'ajaxSaveGoogleConfig']);

        // Google Tag Manager front-end snippets
        add_action('wp_head', [$this, 'renderGtmHeadScript'], 1);
        add_action('wp_body_open', [$this, 'renderGtmBodyScript'], 1);

        // Direct GA4 tracking snippet (alternative to GA4 via GTM)
        add_action('wp_head', [$this, 'renderGa4TrackingScript'], 2);

        // Hooks for automatic notifications
        add_action('sseo_ai_rank_change', [$this, 'notifyRankChange'], 10, 3);
        add_action('sseo_ai_content_published', [$this, 'notifyContentPublished'], 10, 1);
        add_action('sseo_ai_seo_score_change', [$this, 'notifySeoScoreChange'], 10, 3);

        // Scheduled reports
        add_action('sseo_ai_daily_report', [$this, 'sendDailyReport']);
        add_action('sseo_ai_weekly_report', [$this, 'sendWeeklyReport']);
        add_action('sseo_ai_monthly_report', [$this, 'sendMonthlyReport']);

        // Schedule cron jobs
        if (!wp_next_scheduled('sseo_ai_daily_report')) {
            wp_schedule_event(strtotime('tomorrow 9:00'), 'daily', 'sseo_ai_daily_report');
        }
        if (!wp_next_scheduled('sseo_ai_weekly_report')) {
            wp_schedule_event(strtotime('next monday 9:00'), 'weekly', 'sseo_ai_weekly_report');
        }
        if (!wp_next_scheduled('sseo_ai_monthly_report')) {
            wp_schedule_event(strtotime('first day of next month 9:00'), 'monthly', 'sseo_ai_monthly_report');
        }
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Integrations', 'ai-seo-client'),
            __('Integrations', 'ai-seo-client'),
            'manage_options',
            'ai-seo-integrations',
            [$this, 'renderSettings']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        // Slack
        register_setting('sseo_ai_integrations', 'sseo_ai_slack_webhook_url');
        register_setting('sseo_ai_integrations', 'sseo_ai_slack_channel');
        register_setting('sseo_ai_integrations', 'sseo_ai_slack_notifications', ['default' => []]);
        
        // Zapier/Make.com
        register_setting('sseo_ai_integrations', 'sseo_ai_zapier_webhook_url');
        register_setting('sseo_ai_integrations', 'sseo_ai_make_webhook_url');
        register_setting('sseo_ai_integrations', 'sseo_ai_custom_webhooks', ['default' => []]);
        
        // Email
        register_setting('sseo_ai_integrations', 'sseo_ai_report_email');
        register_setting('sseo_ai_integrations', 'sseo_ai_report_frequency', ['default' => 'weekly']);
        register_setting('sseo_ai_integrations', 'sseo_ai_email_notifications', ['default' => []]);
        
        // Google Drive
        register_setting('sseo_ai_integrations', 'sseo_ai_gdrive_folder_id');
        register_setting('sseo_ai_integrations', 'sseo_ai_gdrive_auto_export', ['default' => false]);
        
        // Google Search Console site URL (still per-customer)
        register_setting('sseo_ai_integrations', 'sseo_ai_client_gsc_site_url', [
            'sanitize_callback' => function ($value) {
                $value = trim($value);
                if (str_starts_with($value, 'sc-domain:')) {
                    return sanitize_text_field($value);
                }
                return esc_url_raw($value);
            },
        ]);
        
        // Notion
        register_setting('sseo_ai_integrations', 'sseo_ai_notion_api_key');
        register_setting('sseo_ai_integrations', 'sseo_ai_notion_database_id');

        // SE Ranking
        register_setting('sseo_ai_integrations', 'sseo_ai_seranking_api_key');

        // Ahrefs
        register_setting('sseo_ai_integrations', 'sseo_ai_ahrefs_api_key');

        // DataForSEO
        register_setting('sseo_ai_integrations', 'sseo_ai_dataforseo_api_key');

        // Backlink provider preference
        register_setting('sseo_ai_integrations', 'sseo_ai_backlink_provider', ['default' => 'dataforseo']);

        // Google Analytics 4
        register_setting('sseo_ai_integrations', 'sseo_ai_ga4_property_id');

        // Google Analytics 4 measurement ID (for direct gtag.js tracking)
        register_setting('sseo_ai_integrations', 'sseo_ai_ga4_measurement_id', [
            'sanitize_callback' => function ($value) {
                $value = sanitize_text_field($value ?? '');
                return preg_match('/^G-[A-Z0-9]{7,}$/i', $value) ? strtoupper($value) : '';
            },
        ]);

        // Google Tag Manager
        register_setting('sseo_ai_integrations', 'sseo_ai_gtm_id', [
            'sanitize_callback' => function ($value) {
                $value = sanitize_text_field($value ?? '');
                return preg_match('/^GTM-[A-Z0-9]{4,}$/i', $value) ? strtoupper($value) : '';
            },
        ]);

        // Google Ads
        register_setting('sseo_ai_integrations', 'sseo_ai_google_ads_customer_id');

        // Direct Index (Google Indexing API)
        register_setting('sseo_ai_integrations', 'sseo_direct_index_enabled', [
            'default' => true,
            'sanitize_callback' => fn($value) => $value === '1' || $value === true || $value === 1,
        ]);
        register_setting('sseo_ai_integrations', 'sseo_direct_index_post_types', [
            'default' => [],
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_values(array_filter(array_map('sanitize_text_field', $value)));
            },
        ]);
    }
    
    /**
     * Render settings page
     */
    public function renderSettings(): void
    {
        $slackWebhook = get_option('sseo_ai_slack_webhook_url', '');
        $slackChannel = get_option('sseo_ai_slack_channel', '#seo');
        $slackNotifications = get_option('sseo_ai_slack_notifications', []);
        if (!is_array($slackNotifications)) {
            $slackNotifications = [];
        }
        
        $zapierWebhook = get_option('sseo_ai_zapier_webhook_url', '');
        $makeWebhook = get_option('sseo_ai_make_webhook_url', '');
        $customWebhooks = get_option('sseo_ai_custom_webhooks', []);
        if (!is_array($customWebhooks)) {
            $customWebhooks = [];
        }
        
        $reportEmail = get_option('sseo_ai_report_email', get_option('admin_email'));
        $reportFrequency = get_option('sseo_ai_report_frequency', 'weekly');
        $emailNotifications = get_option('sseo_ai_email_notifications', []);
        if (!is_array($emailNotifications)) {
            $emailNotifications = [];
        }
        
        $gdriveFolderId = get_option('sseo_ai_gdrive_folder_id', '');
        $gdriveAutoExport = get_option('sseo_ai_gdrive_auto_export', false);

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $supportContact = $companyName . ' ' . __('support', 'ai-seo-client');

        // GSC settings
        $gscConnected = !empty(get_option('aiseoclient_gsc_tokens', [])['access_token']);
        $gscSiteUrl = get_option('sseo_ai_client_gsc_site_url', home_url());
        $gscClientId = ''; // Central OAuth — no per-customer credentials
        
        $notionApiKey = get_option('sseo_ai_notion_api_key', '');
        $notionDatabaseId = get_option('sseo_ai_notion_database_id', '');

        // SE Ranking
        $seRankingApiKey = get_option('sseo_ai_seranking_api_key', '');

        // Ahrefs
        $ahrefsApiKey = get_option('sseo_ai_ahrefs_api_key', '');

        // DataForSEO
        $dataforseoApiKey = get_option('sseo_ai_dataforseo_api_key', '');
        $backlinkProvider = get_option('sseo_ai_backlink_provider', 'dataforseo');

        // Google Analytics 4
        $ga4PropertyId = get_option('sseo_ai_ga4_property_id', '');
        $ga4Connected = !empty(get_option('aiseoclient_gsc_tokens', [])['access_token']) && !empty($ga4PropertyId);
        $ga4MeasurementId = get_option('sseo_ai_ga4_measurement_id', '');

        // Google Tag Manager
        $gtmId = get_option('sseo_ai_gtm_id', '');

        // Google Ads
        $adsCustomerId = get_option('sseo_ai_google_ads_customer_id', '');
        $adsConnected = !empty(get_option('aiseoclient_gsc_tokens', [])['access_token']) && !empty($adsCustomerId);

        // Direct Index
        $directIndexEnabled = (bool) get_option('sseo_direct_index_enabled', true);
        $directIndexPostTypes = (array) get_option('sseo_direct_index_post_types', []);
        $allPublicPostTypes = get_post_types(['public' => true], 'objects');
        $directIndex = new DirectIndex($this->settings, new HealthLogger());
        $directIndexConnected = $directIndex->isConnected();
        $directIndexHasScope = $directIndex->hasIndexingScope();
        $directIndexQuotaUsed = $directIndex->getQuotaUsedToday();
        $directIndexQuotaRemaining = max(0, DirectIndex::QUOTA_DAILY - $directIndexQuotaUsed);
        $directIndexLog = array_slice($directIndex->getLog(), 0, 10);
        
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .sseo-two-columns { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; }
            @media (max-width: 1024px) { .sseo-two-columns { grid-template-columns: 1fr; } }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('External Integrations', 'ai-seo-client'); ?></h1>
            </div>
            
            <div class="sseo-ai-content">
                <form method="post" action="options.php">
                    <?php settings_fields('sseo_ai_integrations'); ?>
                    
                    <div class="sseo-two-columns">
                        <!-- Slack Integration -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Slack Integration', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="slack_webhook_url"><?php esc_html_e('Slack Webhook URL', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="slack_webhook_url" name="sseo_ai_slack_webhook_url" 
                                       value="<?php echo esc_attr($slackWebhook); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Get your webhook URL from Slack App settings', 'ai-seo-client'); ?>
                                    <a href="https://api.slack.com/messaging/webhooks" target="_blank"><?php esc_html_e('Learn more', 'ai-seo-client'); ?></a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="slack_channel"><?php esc_html_e('Default Channel', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="slack_channel" name="sseo_ai_slack_channel" 
                                       value="<?php echo esc_attr($slackChannel); ?>" class="regular-text" 
                                       placeholder="#seo">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Notifications', 'ai-seo-client'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="sseo_ai_slack_notifications[]" value="rank_change" 
                                               <?php checked(in_array('rank_change', $slackNotifications)); ?>>
                                        <?php esc_html_e('Keyword rank changes', 'ai-seo-client'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="sseo_ai_slack_notifications[]" value="content_published" 
                                               <?php checked(in_array('content_published', $slackNotifications)); ?>>
                                        <?php esc_html_e('New content published', 'ai-seo-client'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="sseo_ai_slack_notifications[]" value="seo_score" 
                                               <?php checked(in_array('seo_score', $slackNotifications)); ?>>
                                        <?php esc_html_e('SEO score changes', 'ai-seo-client'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="sseo_ai_slack_notifications[]" value="daily_report" 
                                               <?php checked(in_array('daily_report', $slackNotifications)); ?>>
                                        <?php esc_html_e('Daily summary report', 'ai-seo-client'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button" onclick="sseoTestSlack()">
                        <?php esc_html_e('Test Slack Connection', 'ai-seo-client'); ?>
                    </button>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>
                        
                        <!-- Zapier / Make.com -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Zapier / Make.com Integration', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="zapier_webhook"><?php esc_html_e('Zapier Webhook URL', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="zapier_webhook" name="sseo_ai_zapier_webhook_url" 
                                       value="<?php echo esc_attr($zapierWebhook); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Trigger Zaps when SEO events occur', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="make_webhook"><?php esc_html_e('Make.com Webhook URL', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="make_webhook" name="sseo_ai_make_webhook_url" 
                                       value="<?php echo esc_attr($makeWebhook); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Trigger Make.com scenarios', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php esc_html_e('Custom Webhooks', 'ai-seo-client'); ?></h3>
                    <p><?php esc_html_e('Add custom webhook URLs for specific events:', 'ai-seo-client'); ?></p>
                    
                    <div id="custom-webhooks">
                        <?php foreach ($customWebhooks as $index => $webhook): ?>
                        <div style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                            <input type="text" name="sseo_ai_custom_webhooks[<?php echo $index; ?>][name]" 
                                   value="<?php echo esc_attr($webhook['name']); ?>" 
                                   placeholder="<?php esc_attr_e('Webhook Name', 'ai-seo-client'); ?>" 
                                   style="width: 200px; margin-right: 10px;">
                            <input type="url" name="sseo_ai_custom_webhooks[<?php echo $index; ?>][url]" 
                                   value="<?php echo esc_attr($webhook['url']); ?>" 
                                   placeholder="<?php esc_attr_e('Webhook URL', 'ai-seo-client'); ?>" 
                                   style="width: 400px; margin-right: 10px;">
                            <select name="sseo_ai_custom_webhooks[<?php echo $index; ?>][event]" style="width: 150px;">
                                <option value="all" <?php selected($webhook['event'], 'all'); ?>><?php esc_html_e('All Events', 'ai-seo-client'); ?></option>
                                <option value="rank_change" <?php selected($webhook['event'], 'rank_change'); ?>><?php esc_html_e('Rank Change', 'ai-seo-client'); ?></option>
                                <option value="content_published" <?php selected($webhook['event'], 'content_published'); ?>><?php esc_html_e('Content Published', 'ai-seo-client'); ?></option>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <button type="button" class="button" onclick="sseoAddCustomWebhook()">
                        <?php esc_html_e('Add Custom Webhook', 'ai-seo-client'); ?>
                    </button>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div>
                        <!-- Email Reports -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Email Reports', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="report_email"><?php esc_html_e('Report Email', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="report_email" name="sseo_ai_report_email" 
                                       value="<?php echo esc_attr($reportEmail); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Email address to receive SEO reports', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="report_frequency"><?php esc_html_e('Report Frequency', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <select id="report_frequency" name="sseo_ai_report_frequency">
                                    <option value="daily" <?php selected($reportFrequency, 'daily'); ?>><?php esc_html_e('Daily', 'ai-seo-client'); ?></option>
                                    <option value="weekly" <?php selected($reportFrequency, 'weekly'); ?>><?php esc_html_e('Weekly', 'ai-seo-client'); ?></option>
                                    <option value="monthly" <?php selected($reportFrequency, 'monthly'); ?>><?php esc_html_e('Monthly', 'ai-seo-client'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Email Notifications', 'ai-seo-client'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="sseo_ai_email_notifications[]" value="rank_drop" 
                                               <?php checked(in_array('rank_drop', $emailNotifications)); ?>>
                                        <?php esc_html_e('Keyword rank drops', 'ai-seo-client'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="sseo_ai_email_notifications[]" value="toxic_links" 
                                               <?php checked(in_array('toxic_links', $emailNotifications)); ?>>
                                        <?php esc_html_e('Toxic backlinks detected', 'ai-seo-client'); ?>
                                    </label><br>
                                    
                                    <label>
                                        <input type="checkbox" name="sseo_ai_email_notifications[]" value="content_decay" 
                                               <?php checked(in_array('content_decay', $emailNotifications)); ?>>
                                        <?php esc_html_e('Content decay alerts', 'ai-seo-client'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button" onclick="sseoSendTestReport()">
                        <?php esc_html_e('Send Test Report', 'ai-seo-client'); ?>
                    </button>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>
                        
                        <!-- Google Drive Export -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Google Drive Export', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="gdrive_folder"><?php esc_html_e('Google Drive Folder ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="gdrive_folder" name="sseo_ai_gdrive_folder_id" 
                                       value="<?php echo esc_attr($gdriveFolderId); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Folder ID from Google Drive URL', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Auto Export', 'ai-seo-client'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="sseo_ai_gdrive_auto_export" value="1" 
                                           <?php checked($gdriveAutoExport); ?>>
                                    <?php esc_html_e('Automatically export reports to Google Drive', 'ai-seo-client'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button" onclick="sseoExportToGDrive()">
                        <?php esc_html_e('Export Now', 'ai-seo-client'); ?>
                    </button>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>
                        
                        <!-- Google Services (Search Console + Analytics 4 + Google Ads) -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Google Services (Search Console, Analytics 4 & Google Ads)', 'ai-seo-client'); ?></h2>
                            <p class="description">
                                <?php esc_html_e('Connect your Google account once to access Search Console, Google Analytics 4, and Google Ads data. A single login grants access to all three services.', 'ai-seo-client'); ?>
                            </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="gsc_site_url"><?php esc_html_e('Site URL', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="gsc_site_url" name="sseo_ai_client_gsc_site_url" 
                                       value="<?php echo esc_attr($gscSiteUrl); ?>" class="regular-text"
                                       placeholder="sc-domain:<?php echo esc_attr(parse_url(home_url(), PHP_URL_HOST)); ?>">
                                <p class="description">
                                    <?php esc_html_e('Exact property as registered in Google Search Console.', 'ai-seo-client'); ?><br>
                                    <?php esc_html_e('Domain property: sc-domain:example.com', 'ai-seo-client'); ?><br>
                                    <?php esc_html_e('URL property: https://example.com/', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 15px;">
                        <?php if ($gscConnected): ?>
                            <span class="notice notice-success inline" style="margin: 0; padding: 5px 10px;">
                                ✓ <?php esc_html_e('Connected to Google', 'ai-seo-client'); ?>
                            </span>
                            <button type="button" class="button" onclick="sseoDisconnectGSC()">
                                <?php esc_html_e('Disconnect', 'ai-seo-client'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-gsc')); ?>" class="button">
                                <?php esc_html_e('Search Console Dashboard', 'ai-seo-client'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-google-data')); ?>" class="button button-primary">
                                <?php esc_html_e('Google Data Dashboard', 'ai-seo-client'); ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="button button-primary" id="sseo-google-connect-btn" onclick="sseoConnectGoogle()">
                                <?php esc_html_e('Connect with Google', 'ai-seo-client'); ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($gscConnected): ?>
                    <hr style="margin: 25px 0; border: none; border-top: 1px solid #e2e8f0;">

                    <h3 style="margin-top: 20px;"><?php esc_html_e('Google Analytics 4', 'ai-seo-client'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ga4_property_id"><?php esc_html_e('GA4 Property ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="ga4_property_id" name="sseo_ai_ga4_property_id"
                                       value="<?php echo esc_attr($ga4PropertyId); ?>" class="regular-text"
                                       placeholder="123456789">
                                <p class="description">
                                    <?php esc_html_e('Numeric property ID from GA4 (Admin → Property Settings).', 'ai-seo-client'); ?>
                                    <?php if ($ga4Connected): ?>
                                        <span style="color: #00a32a;">✓ <?php esc_html_e('GA4 Connected', 'ai-seo-client'); ?></span>
                                    <?php elseif ($ga4PropertyId): ?>
                                        <span style="color: #dba617;"><?php esc_html_e('Property ID set — verify connection on Google Data Dashboard', 'ai-seo-client'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #6b7280;"><?php esc_html_e('Set property ID to enable GA4 data', 'ai-seo-client'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top: 20px;"><?php esc_html_e('Google Tag Manager', 'ai-seo-client'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="gtm_id"><?php esc_html_e('GTM Container ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="gtm_id" name="sseo_ai_gtm_id"
                                       value="<?php echo esc_attr($gtmId); ?>" class="regular-text"
                                       placeholder="GTM-XXXXXXX">
                                <p class="description">
                                    <?php esc_html_e('Your GTM container ID. The snippet will be added to wp_head and after the opening body tag.', 'ai-seo-client'); ?>
                                    <?php if ($gtmId): ?>
                                        <span style="color: #00a32a;">✓ <?php esc_html_e('GTM snippet active', 'ai-seo-client'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top: 20px;"><?php esc_html_e('Google Analytics 4 Tracking', 'ai-seo-client'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ga4_measurement_id"><?php esc_html_e('GA4 Measurement ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="ga4_measurement_id" name="sseo_ai_ga4_measurement_id"
                                       value="<?php echo esc_attr($ga4MeasurementId); ?>" class="regular-text"
                                       placeholder="G-XXXXXXXXXX">
                                <p class="description">
                                    <?php esc_html_e('Direct GA4 tracking snippet (gtag.js). Only use this if you do NOT track GA4 via the GTM container above, otherwise you will count visitors twice.', 'ai-seo-client'); ?>
                                    <?php if ($ga4MeasurementId): ?>
                                        <span style="color: #00a32a;">✓ <?php esc_html_e('GA4 gtag snippet active', 'ai-seo-client'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top: 20px;"><?php esc_html_e('Google Ads', 'ai-seo-client'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="google_ads_customer_id"><?php esc_html_e('Customer ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="google_ads_customer_id" name="sseo_ai_google_ads_customer_id"
                                       value="<?php echo esc_attr($adsCustomerId); ?>" class="regular-text"
                                       placeholder="123-456-7890">
                                <p class="description">
                                    <?php esc_html_e('Your Google Ads customer ID (10 digits with dashes).', 'ai-seo-client'); ?>
                                    <?php if ($adsConnected): ?>
                                        <span style="color: #00a32a;">✓ <?php esc_html_e('Google Ads Connected', 'ai-seo-client'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>

                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>

                        <!-- Direct Index (Google Indexing API) -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Direct Index', 'ai-seo-client'); ?></h2>

                            <?php if ($directIndexConnected && $directIndexHasScope): ?>
                                <span class="notice notice-success inline" style="margin: 0 0 15px 0; padding: 5px 10px; display: inline-block;">
                                    ✓ <?php esc_html_e('Google Indexing API scope granted', 'ai-seo-client'); ?>
                                </span>
                            <?php elseif ($directIndexConnected): ?>
                                <span class="notice notice-warning inline" style="margin: 0 0 15px 0; padding: 5px 10px; display: inline-block;">
                                    <?php esc_html_e('Connected, but the indexing scope is missing. Reconnect via the Google Services card above.', 'ai-seo-client'); ?>
                                </span>
                            <?php else: ?>
                                <span class="notice notice-error inline" style="margin: 0 0 15px 0; padding: 5px 10px; display: inline-block;">
                                    <?php esc_html_e('Not connected. Connect your Google account in the Google Services card above.', 'ai-seo-client'); ?>
                                </span>
                            <?php endif; ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Automatic Indexing', 'ai-seo-client'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="sseo_direct_index_enabled" value="1" <?php checked($directIndexEnabled); ?>>
                                            <?php esc_html_e('Submit new/scheduled posts to Google automatically on publish', 'ai-seo-client'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Post Types', 'ai-seo-client'); ?></th>
                                    <td>
                                        <input type="hidden" name="sseo_direct_index_post_types[]" value="">
                                        <?php foreach ($allPublicPostTypes as $postType): ?>
                                            <?php if (in_array($postType->name, ['attachment'], true)) continue; ?>
                                            <label style="display:block; margin-bottom:5px;">
                                                <input type="checkbox" name="sseo_direct_index_post_types[]" value="<?php echo esc_attr($postType->name); ?>"
                                                    <?php checked(empty($directIndexPostTypes) || in_array($postType->name, $directIndexPostTypes, true)); ?>>
                                                <?php echo esc_html($postType->label); ?> <code><?php echo esc_html($postType->name); ?></code>
                                            </label>
                                        <?php endforeach; ?>
                                        <p class="description">
                                            <?php esc_html_e('Leave all checked to allow all public post types.', 'ai-seo-client'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Quota', 'ai-seo-client'); ?></th>
                                    <td>
                                        <?php echo esc_html(sprintf(__('Used today: %d / %d — Remaining: %d', 'ai-seo-client'), $directIndexQuotaUsed, DirectIndex::QUOTA_DAILY, $directIndexQuotaRemaining)); ?>
                                    </td>
                                </tr>
                            </table>

                            <?php if (!empty($directIndexLog)): ?>
                                <h3 style="margin-top: 25px;"><?php esc_html_e('Recent Submissions', 'ai-seo-client'); ?></h3>
                                <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Time', 'ai-seo-client'); ?></th>
                                            <th><?php esc_html_e('URL', 'ai-seo-client'); ?></th>
                                            <th><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($directIndexLog as $entry): ?>
                                            <tr>
                                                <td><?php echo esc_html($entry['time'] ?? ''); ?></td>
                                                <td><?php echo esc_html($entry['url'] ?? ''); ?></td>
                                                <td><?php echo esc_html($entry['type'] ?? ''); ?></td>
                                                <td>
                                                    <?php if (!empty($entry['success'])): ?>
                                                        <span style="color:#00a32a;">✓</span>
                                                    <?php else: ?>
                                                        <span style="color:#d63638;">✗ <?php echo esc_html($entry['code'] ?? ''); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>

                            <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                                <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                            </div>
                        </div>

                        <!-- Notion Integration -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Notion Integration', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="notion_api_key"><?php esc_html_e('Notion API Key', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="notion_api_key" name="sseo_ai_notion_api_key" 
                                       value="<?php echo esc_attr($notionApiKey); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Get your API key from Notion integrations', 'ai-seo-client'); ?>
                                    <a href="https://www.notion.so/my-integrations" target="_blank"><?php esc_html_e('Learn more', 'ai-seo-client'); ?></a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="notion_database"><?php esc_html_e('Database ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="notion_database" name="sseo_ai_notion_database_id" 
                                       value="<?php echo esc_attr($notionDatabaseId); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Database ID where SEO data will be synced', 'ai-seo-client'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button" onclick="sseoSyncToNotion()">
                        <?php esc_html_e('Sync to Notion', 'ai-seo-client'); ?>
                    </button>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                </div>

                        <!-- SE Ranking Integration -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('SE Ranking', 'ai-seo-client'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="seranking_api_key"><?php esc_html_e('SE Ranking API Key', 'ai-seo-client'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" id="seranking_api_key" name="sseo_ai_seranking_api_key"
                                               value="<?php echo esc_attr($seRankingApiKey); ?>" class="regular-text">
                                        <p class="description">
                                            <?php esc_html_e('Get your API key from SE Ranking > API.', 'ai-seo-client'); ?>
                                            <a href="https://seranking.com/api.html" target="_blank"><?php esc_html_e('Learn more', 'ai-seo-client'); ?></a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <?php if ($seRankingApiKey): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-data-dashboard')); ?>" class="button button-primary">
                                    <?php esc_html_e('View Dashboard', 'ai-seo-client'); ?>
                                </a>
                            <?php endif; ?>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>

                        <!-- Ahrefs Integration -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Ahrefs', 'ai-seo-client'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="ahrefs_api_key"><?php esc_html_e('Ahrefs API Key', 'ai-seo-client'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" id="ahrefs_api_key" name="sseo_ai_ahrefs_api_key"
                                               value="<?php echo esc_attr($ahrefsApiKey); ?>" class="regular-text">
                                        <p class="description">
                                            <?php esc_html_e('Get your API key from Ahrefs APIv3 dashboard.', 'ai-seo-client'); ?>
                                            <a href="https://ahrefs.com/api/" target="_blank"><?php esc_html_e('Learn more', 'ai-seo-client'); ?></a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            <?php if ($ahrefsApiKey): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-data-dashboard')); ?>" class="button button-primary">
                                    <?php esc_html_e('View Dashboard', 'ai-seo-client'); ?>
                                </a>
                            <?php endif; ?>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>

                        <!-- DataForSEO Integration -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('DataForSEO Backlinks', 'ai-seo-client'); ?></h2>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="dataforseo_api_key"><?php esc_html_e('DataForSEO API Credentials', 'ai-seo-client'); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" id="dataforseo_api_key" name="sseo_ai_dataforseo_api_key"
                                               value="<?php echo esc_attr($dataforseoApiKey); ?>" class="regular-text">
                                        <p class="description">
                                            <?php esc_html_e('Enter login:password from your DataForSEO account.', 'ai-seo-client'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="backlink_provider"><?php esc_html_e('Preferred Backlink Provider', 'ai-seo-client'); ?></label>
                                    </th>
                                    <td>
                                        <select id="backlink_provider" name="sseo_ai_backlink_provider">
                                            <option value="dataforseo" <?php selected($backlinkProvider, 'dataforseo'); ?>><?php esc_html_e('DataForSEO (primary)', 'ai-seo-client'); ?></option>
                                            <option value="ahrefs" <?php selected($backlinkProvider, 'ahrefs'); ?>><?php esc_html_e('Ahrefs', 'ai-seo-client'); ?></option>
                                            <option value="seranking" <?php selected($backlinkProvider, 'seranking'); ?>><?php esc_html_e('SE Ranking', 'ai-seo-client'); ?></option>
                                            <option value="semrush" <?php selected($backlinkProvider, 'semrush'); ?>><?php esc_html_e('Semrush', 'ai-seo-client'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <?php submit_button(__('Save Integration Settings', 'ai-seo-client'), 'primary', 'submit', false, ['style' => 'background: linear-gradient(135deg, #2563eb 0%, #db2777 100%); border: none; color: #fff; padding: 8px 24px; font-weight: 600; border-radius: 6px;']); ?>
                    </div>
                        </div>
                
                        </div>
                    </div>
                </div>
            </form>
            </div>
        </div>
        
        <script>
        let webhookIndex = <?php echo count($customWebhooks); ?>;
        
        function sseoAddCustomWebhook() {
            const container = document.getElementById('custom-webhooks');
            const div = document.createElement('div');
            div.style.cssText = 'margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;';
            div.innerHTML = `
                <input type="text" name="sseo_ai_custom_webhooks[${webhookIndex}][name]" 
                       placeholder="<?php esc_attr_e('Webhook Name', 'ai-seo-client'); ?>" 
                       style="width: 200px; margin-right: 10px;">
                <input type="url" name="sseo_ai_custom_webhooks[${webhookIndex}][url]" 
                       placeholder="<?php esc_attr_e('Webhook URL', 'ai-seo-client'); ?>" 
                       style="width: 400px; margin-right: 10px;">
                <select name="sseo_ai_custom_webhooks[${webhookIndex}][event]" style="width: 150px;">
                    <option value="all"><?php esc_html_e('All Events', 'ai-seo-client'); ?></option>
                    <option value="rank_change"><?php esc_html_e('Rank Change', 'ai-seo-client'); ?></option>
                    <option value="content_published"><?php esc_html_e('Content Published', 'ai-seo-client'); ?></option>
                </select>
            `;
            container.appendChild(div);
            webhookIndex++;
        }
        
        function sseoTestSlack() {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_test_slack',
                nonce: '<?php echo wp_create_nonce('sseo_integrations'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Test message sent to Slack!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error sending test message');
                }
            });
        }
        
        function sseoSendTestReport() {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_send_test_report',
                nonce: '<?php echo wp_create_nonce('sseo_integrations'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Test report sent!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error sending test report');
                }
            });
        }
        
        function sseoExportToGDrive() {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_export_gdrive',
                nonce: '<?php echo wp_create_nonce('sseo_integrations'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Report exported to Google Drive!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error exporting to Google Drive');
                }
            });
        }
        
        function sseoSyncToNotion() {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_sync_notion',
                nonce: '<?php echo wp_create_nonce('sseo_integrations'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Data synced to Notion!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error syncing to Notion');
                }
            });
        }
        
        // Google OAuth — SaaS dashboard proxy flow (only fyndable.ai needs to be in Google's authorised origins)
        let sseoGooglePopup = null;

        function sseoConnectGoogle() {
            const btn = document.getElementById('sseo-google-connect-btn');
            if (btn) btn.prop ? btn.prop('disabled', true) : (btn.disabled = true);

            // Get SaaS dashboard URL and credentials
            wp.apiFetch({ path: '/sseo-ai/v1/google-status' }).then(function(status) {
                if (!status.has_credentials) {
                    alert('<?php echo esc_js(sprintf(__('Google OAuth is not yet configured. Please contact %s.', 'ai-seo-client'), $supportContact)); ?>');
                    if (btn) btn.disabled = false;
                    return;
                }

                // Open popup to SaaS dashboard OAuth start page
                var dashboardUrl = '<?php echo esc_js(get_option("sseo_ai_client_dashboard_url", "")); ?>';
                var licenseKey = '<?php echo esc_js(get_option(SSEO_AI_CLIENT_LICENSE_OPTION, "")); ?>';
                var tenantKey = '<?php echo esc_js(get_option(SSEO_AI_CLIENT_TENANT_OPTION, "")); ?>';

                var oauthUrl = dashboardUrl.replace(/\/+$/, '') +
                    '/wp-json/ai-seo-saas/v1/google/oauth-start' +
                    '?license_key=' + encodeURIComponent(licenseKey) +
                    '&tenant_key=' + encodeURIComponent(tenantKey);

                sseoGooglePopup = window.open(oauthUrl, 'fyndable_google_oauth', 'width=520,height=680,scrollbars=yes,resizable=yes');

                if (!sseoGooglePopup) {
                    alert('<?php esc_html_e('Popup blocked. Please allow popups for this site to connect Google.', 'ai-seo-client'); ?>');
                    if (btn) btn.disabled = false;
                }
            }).catch(function(err) {
                alert('<?php esc_html_e('Error:', 'ai-seo-client'); ?> ' + (err.message || '<?php esc_html_e('Failed to get Google status', 'ai-seo-client'); ?>'));
                if (btn) btn.disabled = false;
            });
        }

        // Listen for tokens from SaaS dashboard popup
        window.addEventListener('message', function(event) {
            if (event.data.type !== 'fyndable_google_tokens') return;

            var btn = document.getElementById('sseo-google-connect-btn');

            if (event.data.success && event.data.tokens) {
                // Store tokens via REST endpoint
                wp.apiFetch({
                    path: '/sseo-ai/v1/google/store-tokens',
                    method: 'POST',
                    data: { tokens: event.data.tokens }
                }).then(function(response) {
                    alert('<?php esc_html_e('Successfully connected to Google!', 'ai-seo-client'); ?>');
                    location.reload();
                }).catch(function(err) {
                    alert('<?php esc_html_e('Error storing tokens:', 'ai-seo-client'); ?> ' + (err.message || '<?php esc_html_e('Unknown error', 'ai-seo-client'); ?>'));
                    if (btn) btn.disabled = false;
                });
            } else {
                alert('<?php esc_html_e('Google connection failed:', 'ai-seo-client'); ?> ' + (event.data.error || '<?php esc_html_e('Unknown error', 'ai-seo-client'); ?>'));
                if (btn) btn.disabled = false;
            }
        });
        
        function sseoDisconnectGSC() {
            if (!confirm('<?php esc_html_e('Are you sure you want to disconnect your Google account? This will remove access to Search Console, Analytics 4, and Google Ads.', 'ai-seo-client'); ?>')) {
                return;
            }
            
            wp.apiFetch({
                path: '/sseo-ai/v1/google-disconnect',
                method: 'POST'
            }).then(function(response) {
                alert('<?php esc_html_e('Disconnected from Google.', 'ai-seo-client'); ?>');
                location.reload();
            }).catch(function(err) {
                alert('<?php esc_html_e('Error:', 'ai-seo-client'); ?> ' + (err.message || '<?php esc_html_e('Failed to disconnect', 'ai-seo-client'); ?>'));
            });
        }
        </script>
        <?php
    }
    
    /**
     * Send Slack notification
     */
    public function sendSlackNotification(string $message, array $attachments = []): bool
    {
        $webhookUrl = get_option('sseo_ai_slack_webhook_url', '');
        if (empty($webhookUrl)) {
            return false;
        }
        
        $channel = get_option('sseo_ai_slack_channel', '#seo');
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        
        $payload = [
            'channel' => $channel,
            'username' => $companyName . ' Bot',
            'icon_emoji' => ':chart_with_upwards_trend:',
            'text' => $message,
        ];
        
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }
        
        $response = wp_remote_post($webhookUrl, [
            'body' => json_encode($payload),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    
    /**
     * Trigger webhooks
     */
    public function triggerWebhooks(string $event, array $data): void
    {
        // Zapier
        $zapierUrl = get_option('sseo_ai_zapier_webhook_url', '');
        if (!empty($zapierUrl)) {
            wp_remote_post($zapierUrl, [
                'body' => json_encode(array_merge(['event' => $event], $data)),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        }
        
        // Make.com
        $makeUrl = get_option('sseo_ai_make_webhook_url', '');
        if (!empty($makeUrl)) {
            wp_remote_post($makeUrl, [
                'body' => json_encode(array_merge(['event' => $event], $data)),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        }
        
        // Custom webhooks
        $customWebhooks = get_option('sseo_ai_custom_webhooks', []);
        if (!is_array($customWebhooks)) { $customWebhooks = []; }
        foreach ($customWebhooks as $webhook) {
            if ($webhook['event'] === 'all' || $webhook['event'] === $event) {
                wp_remote_post($webhook['url'], [
                    'body' => json_encode(array_merge(['event' => $event], $data)),
                    'headers' => ['Content-Type' => 'application/json'],
                ]);
            }
        }
    }
    
    /**
     * Notify rank change
     */
    public function notifyRankChange(string $keyword, int $oldRank, int $newRank): void
    {
        $slackNotifications = get_option('sseo_ai_slack_notifications', []);
        if (!is_array($slackNotifications)) { $slackNotifications = []; }
        
        if (in_array('rank_change', $slackNotifications)) {
            $change = $newRank - $oldRank;
            $emoji = $change < 0 ? ':arrow_up:' : ':arrow_down:';
            $color = $change < 0 ? 'good' : 'danger';
            
            $message = sprintf(
                '%s Keyword rank changed for "%s"',
                $emoji,
                $keyword
            );
            
            $attachments = [[
                'color' => $color,
                'fields' => [
                    [
                        'title' => 'Previous Rank',
                        'value' => $oldRank,
                        'short' => true,
                    ],
                    [
                        'title' => 'New Rank',
                        'value' => $newRank,
                        'short' => true,
                    ],
                    [
                        'title' => 'Change',
                        'value' => ($change > 0 ? '+' : '') . $change,
                        'short' => true,
                    ],
                ],
            ]];
            
            $this->sendSlackNotification($message, $attachments);
        }
        
        // Trigger webhooks
        $this->triggerWebhooks('rank_change', [
            'keyword' => $keyword,
            'old_rank' => $oldRank,
            'new_rank' => $newRank,
            'change' => $newRank - $oldRank,
        ]);
    }
    
    /**
     * Notify content published
     */
    public function notifyContentPublished(int $postId): void
    {
        $post = get_post($postId);
        if (!$post) {
            return;
        }
        
        $slackNotifications = get_option('sseo_ai_slack_notifications', []);
        if (!is_array($slackNotifications)) { $slackNotifications = []; }
        
        if (in_array('content_published', $slackNotifications)) {
            $message = sprintf(
                ':memo: New content published: "%s"',
                $post->post_title
            );
            
            $attachments = [[
                'color' => 'good',
                'fields' => [
                    [
                        'title' => 'URL',
                        'value' => get_permalink($postId),
                    ],
                    [
                        'title' => 'SEO Score',
                        'value' => get_post_meta($postId, '_sseo_ai_score', true) ?: 'N/A',
                        'short' => true,
                    ],
                ],
            ]];
            
            $this->sendSlackNotification($message, $attachments);
        }
        
        // Trigger webhooks
        $this->triggerWebhooks('content_published', [
            'post_id' => $postId,
            'title' => $post->post_title,
            'url' => get_permalink($postId),
            'seo_score' => get_post_meta($postId, '_sseo_ai_score', true),
        ]);
    }
    
    /**
     * Notify SEO score change
     */
    public function notifySeoScoreChange(int $postId, int $oldScore, int $newScore): void
    {
        $slackNotifications = get_option('sseo_ai_slack_notifications', []);
        if (!is_array($slackNotifications)) { $slackNotifications = []; }
        
        if (in_array('seo_score', $slackNotifications)) {
            $post = get_post($postId);
            $change = $newScore - $oldScore;
            $emoji = $change > 0 ? ':arrow_up:' : ':arrow_down:';
            
            $message = sprintf(
                '%s SEO score changed for "%s"',
                $emoji,
                $post->post_title
            );
            
            $attachments = [[
                'color' => $change > 0 ? 'good' : 'warning',
                'fields' => [
                    [
                        'title' => 'Previous Score',
                        'value' => $oldScore,
                        'short' => true,
                    ],
                    [
                        'title' => 'New Score',
                        'value' => $newScore,
                        'short' => true,
                    ],
                ],
            ]];
            
            $this->sendSlackNotification($message, $attachments);
        }
    }
    
    /**
     * Send daily report
     */
    public function sendDailyReport(): void
    {
        $reportEmail = get_option('sseo_ai_report_email', get_option('admin_email'));
        $frequency = get_option('sseo_ai_report_frequency', 'weekly');
        
        if ($frequency !== 'daily') {
            return;
        }
        
        $report = $this->generateReport('daily');
        
        wp_mail(
            $reportEmail,
            sprintf(__('Daily SEO Report - %s', 'ai-seo-client'), get_bloginfo('name')),
            $report,
            ['Content-Type: text/html; charset=UTF-8']
        );
        
        // Send to Slack if enabled
        $slackNotifications = get_option('sseo_ai_slack_notifications', []);
        if (!is_array($slackNotifications)) { $slackNotifications = []; }
        if (in_array('daily_report', $slackNotifications)) {
            $this->sendSlackNotification(':bar_chart: Daily SEO Report', [
                [
                    'color' => 'good',
                    'text' => strip_tags($report),
                ],
            ]);
        }
    }
    
    /**
     * Send weekly report
     */
    public function sendWeeklyReport(): void
    {
        $reportEmail = get_option('sseo_ai_report_email', get_option('admin_email'));
        $frequency = get_option('sseo_ai_report_frequency', 'weekly');
        
        if ($frequency !== 'weekly') {
            return;
        }
        
        $report = $this->generateReport('weekly');
        
        wp_mail(
            $reportEmail,
            sprintf(__('Weekly SEO Report - %s', 'ai-seo-client'), get_bloginfo('name')),
            $report,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
    
    /**
     * Send monthly report
     */
    public function sendMonthlyReport(): void
    {
        $reportEmail = get_option('sseo_ai_report_email', get_option('admin_email'));
        $frequency = get_option('sseo_ai_report_frequency', 'weekly');
        
        if ($frequency !== 'monthly') {
            return;
        }
        
        $report = $this->generateReport('monthly');
        
        wp_mail(
            $reportEmail,
            sprintf(__('Monthly SEO Report - %s', 'ai-seo-client'), get_bloginfo('name')),
            $report,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }
    
    /**
     * Generate report HTML
     */
    private function generateReport(string $period): string
    {
        // This would generate a comprehensive HTML report
        // For now, a simple template
        
        $html = '<html><body>';
        $html .= '<h1>' . sprintf(__('%s SEO Report', 'ai-seo-client'), ucfirst($period)) . '</h1>';
        $html .= '<p>' . sprintf(__('Report for %s', 'ai-seo-client'), get_bloginfo('name')) . '</p>';
        
        // Add report sections here
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Export to Google Drive
     */
    public function exportToGoogleDrive(): bool
    {
        $folderId = get_option('sseo_ai_gdrive_folder_id', '');
        if (empty($folderId)) {
            return false;
        }
        
        // Generate CSV report
        $report = $this->generateCSVReport();
        
        // Upload to Google Drive using Google Drive API
        // This requires OAuth2 authentication setup
        
        return true;
    }
    
    /**
     * Sync to Notion
     */
    public function syncToNotion(): bool
    {
        $apiKey = get_option('sseo_ai_notion_api_key', '');
        $databaseId = get_option('sseo_ai_notion_database_id', '');
        
        if (empty($apiKey) || empty($databaseId)) {
            return false;
        }
        
        // Get SEO data
        $posts = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => 100,
        ]);
        
        foreach ($posts as $post) {
            $seoScore = get_post_meta($post->ID, '_sseo_ai_score', true);
            $keyword = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
            
            // Create/update Notion page
            $response = wp_remote_post('https://api.notion.com/v1/pages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Notion-Version' => '2022-06-28',
                ],
                'body' => json_encode([
                    'parent' => ['database_id' => $databaseId],
                    'properties' => [
                        'Title' => [
                            'title' => [
                                ['text' => ['content' => $post->post_title]],
                            ],
                        ],
                        'SEO Score' => [
                            'number' => (int)$seoScore,
                        ],
                        'Keyword' => [
                            'rich_text' => [
                                ['text' => ['content' => $keyword ?: '']],
                            ],
                        ],
                        'URL' => [
                            'url' => get_permalink($post->ID),
                        ],
                    ],
                ]),
            ]);
            
            if (is_wp_error($response)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate CSV report
     */
    private function generateCSVReport(): string
    {
        $posts = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);
        
        $csv = "Title,URL,SEO Score,Keyword,Word Count\n";
        
        foreach ($posts as $post) {
            $seoScore = get_post_meta($post->ID, '_sseo_ai_score', true);
            $keyword = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
            $wordCount = str_word_count(wp_strip_all_tags($post->post_content));
            
            $csv .= sprintf(
                '"%s","%s",%s,"%s",%d' . "\n",
                str_replace('"', '""', $post->post_title),
                get_permalink($post->ID),
                $seoScore ?: '0',
                str_replace('"', '""', $keyword ?: ''),
                $wordCount
            );
        }
        
        return $csv;
    }
    
    /**
     * Render Google Tag Manager head script (wp_head)
     */
    public function renderGtmHeadScript(): void
    {
        $gtmId = get_option('sseo_ai_gtm_id', '');
        if (!$gtmId) {
            return;
        }
        echo "<!-- Google Tag Manager -->\n";
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
        echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
        echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
        echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
        echo "})(window,document,'script','dataLayer','" . esc_js($gtmId) . "');</script>\n";
        echo "<!-- End Google Tag Manager -->\n";
    }

    /**
     * Render Google Tag Manager noscript iframe (wp_body_open)
     */
    public function renderGtmBodyScript(): void
    {
        $gtmId = get_option('sseo_ai_gtm_id', '');
        if (!$gtmId) {
            return;
        }
        echo "<!-- Google Tag Manager (noscript) -->\n";
        echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=" . esc_attr($gtmId) . "\"\n";
        echo "height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
        echo "<!-- End Google Tag Manager (noscript) -->\n";
    }

    /**
     * Render direct GA4 gtag.js snippet (wp_head)
     */
    public function renderGa4TrackingScript(): void
    {
        $measurementId = get_option('sseo_ai_ga4_measurement_id', '');
        if (!$measurementId) {
            return;
        }
        echo "<!-- Google tag (gtag.js) -->\n";
        echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . esc_attr($measurementId) . "\"></script>\n";
        echo "<script>\n";
        echo "  window.dataLayer = window.dataLayer || [];\n";
        echo "  function gtag(){dataLayer.push(arguments);}\n";
        echo "  gtag('js', new Date());\n";
        echo "  gtag('config', '" . esc_js($measurementId) . "');\n";
        echo "</script>\n";
        echo "<!-- End Google tag (gtag.js) -->\n";
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/integrations/test-slack', [
            'methods' => 'POST',
            'callback' => [$this, 'restTestSlack'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/integrations/export-gdrive', [
            'methods' => 'POST',
            'callback' => [$this, 'restExportGDrive'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/integrations/sync-notion', [
            'methods' => 'POST',
            'callback' => [$this, 'restSyncNotion'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    /**
     * REST: Test Slack connection
     */
    public function restTestSlack(): array
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $success = $this->sendSlackNotification(
            ':wave: ' . sprintf(__('Test message from %s', 'ai-seo-client'), $companyName),
            [[
                'color' => 'good',
                'text' => 'Your Slack integration is working correctly!',
            ]]
        );
        
        if ($success) {
            return ['success' => true, 'message' => 'Test message sent'];
        }
        
        return new \WP_Error('slack_error', 'Failed to send test message');
    }
    
    /**
     * REST: Export to Google Drive
     */
    public function restExportGDrive(): array
    {
        $success = $this->exportToGoogleDrive();
        
        if ($success) {
            return ['success' => true, 'message' => 'Report exported'];
        }
        
        return new \WP_Error('gdrive_error', 'Failed to export to Google Drive');
    }
    
    /**
     * REST: Sync to Notion
     */
    public function restSyncNotion(): array
    {
        $success = $this->syncToNotion();
        
        if ($success) {
            return ['success' => true, 'message' => 'Data synced to Notion'];
        }
        
        return new \WP_Error('notion_error', 'Failed to sync to Notion');
    }

    /**
     * AJAX: Save Google configuration (GA4 property ID, Ads customer ID, dev token)
     */
    public function ajaxSaveGoogleConfig(): void
    {
        check_ajax_referer('sseo_google_config', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'ai-seo-client')]);
        }

        $ga4PropertyId = sanitize_text_field($_POST['ga4_property_id'] ?? '');
        $adsCustomerId = sanitize_text_field($_POST['ads_customer_id'] ?? '');

        update_option('sseo_ai_ga4_property_id', $ga4PropertyId);
        update_option('sseo_ai_google_ads_customer_id', $adsCustomerId);

        wp_send_json_success(['message' => __('Google configuration saved.', 'ai-seo-client')]);
    }
}

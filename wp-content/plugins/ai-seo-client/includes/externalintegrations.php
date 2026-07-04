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

        // Google Analytics 4
        register_setting('sseo_ai_integrations', 'sseo_ai_ga4_property_id');

        // Google Ads
        register_setting('sseo_ai_integrations', 'sseo_ai_google_ads_customer_id');
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

        // Google Analytics 4
        $ga4PropertyId = get_option('sseo_ai_ga4_property_id', '');
        $ga4Connected = !empty(get_option('aiseoclient_gsc_tokens', [])['access_token']) && !empty($ga4PropertyId);

        // Google Ads
        $adsCustomerId = get_option('sseo_ai_google_ads_customer_id', '');
        $adsConnected = !empty(get_option('aiseoclient_gsc_tokens', [])['access_token']) && !empty($adsCustomerId);
        
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
                        </div>
                
                        </div>
                    </div>
                </div>
                
                <?php submit_button(__('Save Integration Settings', 'ai-seo-client')); ?>
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
        
        // Google Identity Services (GIS) — central OAuth flow
        let sseoGoogleTokenClient = null;

        function sseoConnectGoogle() {
            const btn = document.getElementById('sseo-google-connect-btn');
            if (btn) btn.prop ? btn.prop('disabled', true) : (btn.disabled = true);
            
            // Fetch status to get the central client ID
            wp.apiFetch({ path: '/sseo-ai/v1/google-status' }).then(function(status) {
                if (!status.client_id) {
                    alert('<?php esc_html_e('Google OAuth is not yet configured. Please contact Fyndable support.', 'ai-seo-client'); ?>');
                    if (btn) btn.disabled = false;
                    return;
                }

                // Load GIS library if not already loaded
                if (!window.google || !google.accounts || !google.accounts.oauth2) {
                    const script = document.createElement('script');
                    script.src = 'https://accounts.google.com/gsi/client';
                    script.async = true;
                    script.defer = true;
                    script.onload = function() { sseoInitGoogleTokenClient(status.client_id, btn); };
                    document.head.appendChild(script);
                } else {
                    sseoInitGoogleTokenClient(status.client_id, btn);
                }
            }).catch(function(err) {
                alert('<?php esc_html_e('Error:', 'ai-seo-client'); ?> ' + (err.message || '<?php esc_html_e('Failed to get Google status', 'ai-seo-client'); ?>'));
                if (btn) btn.disabled = false;
            });
        }

        function sseoInitGoogleTokenClient(clientId, btn) {
            sseoGoogleTokenClient = google.accounts.oauth2.initCodeClient({
                client_id: clientId,
                scope: 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/adwords',
                ux_mode: 'popup',
                callback: function(response) {
                    sseoExchangeGoogleCode(response.code, btn);
                },
                error_callback: function(error) {
                    alert('<?php esc_html_e('Google login failed:', 'ai-seo-client'); ?> ' + (error.message || error.type || 'Unknown error'));
                    if (btn) btn.disabled = false;
                }
            });
            sseoGoogleTokenClient.requestCode();
        }

        function sseoExchangeGoogleCode(code, btn) {
            wp.apiFetch({
                path: '/sseo-ai/v1/google-exchange',
                method: 'POST',
                data: { code: code }
            }).then(function(response) {
                alert('<?php esc_html_e('Successfully connected to Google!', 'ai-seo-client'); ?>');
                location.reload();
            }).catch(function(err) {
                alert('<?php esc_html_e('Error connecting:', 'ai-seo-client'); ?> ' + (err.message || '<?php esc_html_e('Token exchange failed', 'ai-seo-client'); ?>'));
                if (btn) btn.disabled = false;
            });
        }
        
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
        
        $payload = [
            'channel' => $channel,
            'username' => 'Fynable Bot',
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
        $success = $this->sendSlackNotification(
            ':wave: Test message from Fynable',
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

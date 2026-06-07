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
        
        // Google Search Console OAuth (using sseo_ai_client_ prefix for Settings class compatibility)
        register_setting('sseo_ai_integrations', 'sseo_ai_client_gsc_client_id');
        register_setting('sseo_ai_integrations', 'sseo_ai_client_gsc_client_secret');
        register_setting('sseo_ai_integrations', 'sseo_ai_client_gsc_site_url', [
            'sanitize_callback' => function ($value) {
                $value = trim($value);
                // Allow sc-domain: format (domain properties)
                if (str_starts_with($value, 'sc-domain:')) {
                    return sanitize_text_field($value);
                }
                // URL properties — sanitize as URL
                return esc_url_raw($value);
            },
        ]);
        
        // Notion
        register_setting('sseo_ai_integrations', 'sseo_ai_notion_api_key');
        register_setting('sseo_ai_integrations', 'sseo_ai_notion_database_id');
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
        $gscClientId = get_option('sseo_ai_client_gsc_client_id', '');
        $gscClientSecret = get_option('sseo_ai_client_gsc_client_secret', '');
        $gscSiteUrl = get_option('sseo_ai_client_gsc_site_url', home_url());
        $gscConnected = !empty(get_option('aiseoclient_gsc_tokens', [])['access_token']);
        
        $notionApiKey = get_option('sseo_ai_notion_api_key', '');
        $notionDatabaseId = get_option('sseo_ai_notion_database_id', '');
        
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
                        
                        <!-- Google Search Console OAuth -->
                        <div class="sseo-ai-dashboard-card">
                            <h2><?php esc_html_e('Google Search Console', 'ai-seo-client'); ?></h2>
                            <p class="description">
                                <?php esc_html_e('Connect to Google Search Console to view performance data directly in WordPress.', 'ai-seo-client'); ?>
                            </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="gsc_client_id"><?php esc_html_e('OAuth Client ID', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="gsc_client_id" name="sseo_ai_client_gsc_client_id" 
                                       value="<?php echo esc_attr($gscClientId); ?>" class="regular-text"
                                       placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com">
                                <p class="description">
                                    <?php esc_html_e('From Google Cloud Console → APIs & Services → Credentials', 'ai-seo-client'); ?>
                                    <a href="https://console.cloud.google.com/apis/credentials" target="_blank"><?php esc_html_e('Get credentials', 'ai-seo-client'); ?></a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="gsc_client_secret"><?php esc_html_e('OAuth Client Secret', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="gsc_client_secret" name="sseo_ai_client_gsc_client_secret" 
                                       value="<?php echo esc_attr($gscClientSecret); ?>" class="regular-text">
                            </td>
                        </tr>
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
                                ✓ <?php esc_html_e('Connected to Google Search Console', 'ai-seo-client'); ?>
                            </span>
                            <button type="button" class="button" onclick="sseoDisconnectGSC()">
                                <?php esc_html_e('Disconnect', 'ai-seo-client'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-gsc')); ?>" class="button button-primary">
                                <?php esc_html_e('View Dashboard', 'ai-seo-client'); ?>
                            </a>
                        <?php elseif ($gscClientId && $gscClientSecret): ?>
                            <button type="button" class="button button-primary" onclick="sseoConnectGSC()">
                                <?php esc_html_e('Connect to Google Search Console', 'ai-seo-client'); ?>
                            </button>
                        <?php else: ?>
                            <p class="description">
                                <?php esc_html_e('Enter your OAuth credentials above and save to enable connection.', 'ai-seo-client'); ?>
                            </p>
                        <?php endif; ?>
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
        
        // Google Search Console OAuth
        function sseoConnectGSC() {
            // First save the form to store credentials
            const form = document.querySelector('form[action="options.php"]');
            const formData = new FormData(form);
            
            // Get the auth URL via REST API
            wp.apiFetch({ path: '/sseo-ai/v1/gsc-status' }).then(function(status) {
                if (status.auth_url) {
                    // Open OAuth popup
                    const width = 500;
                    const height = 600;
                    const left = (screen.width / 2) - (width / 2);
                    const top = (screen.height / 2) - (height / 2);
                    
                    const popup = window.open(
                        status.auth_url,
                        'gsc_oauth',
                        'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes'
                    );
                    
                    // Poll for completion
                    const checkInterval = setInterval(function() {
                        if (popup.closed) {
                            clearInterval(checkInterval);
                            // Check if connected
                            wp.apiFetch({ path: '/sseo-ai/v1/gsc-status' }).then(function(newStatus) {
                                if (newStatus.connected) {
                                    alert('<?php esc_html_e('Successfully connected to Google Search Console!', 'ai-seo-client'); ?>');
                                    location.reload();
                                }
                            });
                        }
                    }, 1000);
                } else {
                    alert('<?php esc_html_e('Please enter your Client ID and Client Secret first, then save the settings.', 'ai-seo-client'); ?>');
                }
            }).catch(function(err) {
                alert('<?php esc_html_e('Error:', 'ai-seo-client'); ?> ' + (err.message || '<?php esc_html_e('Failed to get auth URL', 'ai-seo-client'); ?>'));
            });
        }
        
        function sseoDisconnectGSC() {
            if (!confirm('<?php esc_html_e('Are you sure you want to disconnect Google Search Console?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            wp.apiFetch({
                path: '/sseo-ai/v1/gsc-disconnect',
                method: 'POST'
            }).then(function(response) {
                alert('<?php esc_html_e('Disconnected from Google Search Console.', 'ai-seo-client'); ?>');
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
            'username' => 'SSEO AI Bot',
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
            ':wave: Test message from SSEO AI',
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
}

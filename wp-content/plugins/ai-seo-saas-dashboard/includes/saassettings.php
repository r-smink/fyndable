<?php

namespace SSEOAISaaS;

/**
 * SaaS Settings Manager
 * 
 * Manages API credentials and global settings for the SaaS Dashboard
 */
class SaaSSettings
{
    private const OPTION_PREFIX = 'ai_seo_saas_';
    
    /**
     * Register settings page
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addSettingsMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Add settings submenu
     */
    public function addSettingsMenu(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('SaaS Settings', 'sseo-ai-saas'),
            __('Settings', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-settings',
            [$this, 'renderSettingsPage']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Cost Dashboard', 'sseo-ai-saas'),
            __('Cost Dashboard', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-costs',
            [$this, 'renderCostDashboard']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_openai_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_openai_model', ['default' => 'gpt-4']);
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_api_provider', ['default' => 'dataforseo']);
        
        // Usage limits per tier
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_free_api_calls', ['default' => 50]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_starter_api_calls', ['default' => 200]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_professional_api_calls', ['default' => 1000]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_business_api_calls', ['default' => 5000]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_agency_api_calls', ['default' => 20000]);
        
        // Cost limits per tier (in USD)
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_free_cost_limit', ['default' => 5]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_starter_cost_limit', ['default' => 20]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_professional_cost_limit', ['default' => 100]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_business_cost_limit', ['default' => 500]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_agency_cost_limit', ['default' => 2000]);
        
        // Billing settings - Payment providers
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_payment_provider', ['default' => 'stripe']);
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_currency', ['default' => 'EUR']);
        
        // Stripe settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_key');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_secret');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_webhook_secret');
        
        // Mollie settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_mollie_api_key');
    }
    
    /**
     * Get OpenAI API key
     */
    public function getOpenAiApiKey(): string
    {
        return get_option('ai_seo_saas_openai_api_key', '');
    }
    
    /**
     * Get OpenAI model
     */
    public function getOpenAiModel(): string
    {
        return get_option('ai_seo_saas_openai_model', 'gpt-4');
    }
    
    /**
     * Get SERP API key
     */
    public function getSerpApiKey(): string
    {
        return get_option('ai_seo_saas_serp_api_key', '');
    }
    
    /**
     * Get SERP API provider
     */
    public function getSerpApiProvider(): string
    {
        return get_option('ai_seo_saas_serp_api_provider', 'dataforseo');
    }
    
    /**
     * Get API call limit for tier
     */
    public function getApiLimitForTier(string $tier): int
    {
        $optionName = "ai_seo_saas_{$tier}_api_calls";
        return (int)get_option($optionName, $this->getDefaultApiLimit($tier));
    }
    
    /**
     * Get cost limit for tier (USD)
     */
    public function getCostLimitForTier(string $tier): float
    {
        $optionName = "ai_seo_saas_{$tier}_cost_limit";
        return (float)get_option($optionName, $this->getDefaultCostLimit($tier));
    }
    
    /**
     * Default API limits
     */
    private function getDefaultApiLimit(string $tier): int
    {
        $defaults = [
            'free' => 50,
            'starter' => 200,
            'trial' => 500,
            'professional' => 1000,
            'business' => 5000,
            'agency' => 20000,
        ];
        return $defaults[$tier] ?? 100;
    }
    
    /**
     * Default cost limits (USD)
     */
    private function getDefaultCostLimit(string $tier): float
    {
        $defaults = [
            'free' => 5,
            'starter' => 20,
            'trial' => 50,
            'professional' => 100,
            'business' => 500,
            'agency' => 2000,
        ];
        return $defaults[$tier] ?? 10;
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SSEO AI SaaS Settings', 'sseo-ai-saas'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('ai_seo_saas_settings'); ?>
                
                <h2><?php esc_html_e('API Credentials', 'sseo-ai-saas'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="ai_seo_saas_openai_api_key" id="openai_api_key" 
                                   value="<?php echo esc_attr($this->getOpenAiApiKey()); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Your OpenAI API key for AI content generation. Keep this secret!', 'sseo-ai-saas'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openai_model"><?php esc_html_e('OpenAI Model', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <select name="ai_seo_saas_openai_model" id="openai_model">
                                <option value="gpt-4" <?php selected($this->getOpenAiModel(), 'gpt-4'); ?>>GPT-4 (Best quality, higher cost)</option>
                                <option value="gpt-4-turbo" <?php selected($this->getOpenAiModel(), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected($this->getOpenAiModel(), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Cheaper, faster)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="serp_api_key"><?php esc_html_e('SERP API Key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="ai_seo_saas_serp_api_key" id="serp_api_key" 
                                   value="<?php echo esc_attr($this->getSerpApiKey()); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('API key for SERP data (DataForSEO, SerpApi, etc.)', 'sseo-ai-saas'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="serp_api_provider"><?php esc_html_e('SERP Provider', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <select name="ai_seo_saas_serp_api_provider" id="serp_api_provider">
                                <option value="dataforseo" <?php selected($this->getSerpApiProvider(), 'dataforseo'); ?>>DataForSEO</option>
                                <option value="serpapi" <?php selected($this->getSerpApiProvider(), 'serpapi'); ?>>SerpApi</option>
                                <option value="seranking" <?php selected($this->getSerpApiProvider(), 'seranking'); ?>>SE Ranking</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'sseo-ai-saas')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render cost dashboard
     */
    public function renderCostDashboard(): void
    {
        global $wpdb;
        
        // Get current month's usage
        $currentMonth = date('Y-m');
        $tableUsage = $wpdb->prefix . 'sseo_ai_tenant_usage';
        
        $monthlyStats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(api_calls) as total_calls,
                SUM(api_cost) as total_cost,
                COUNT(DISTINCT tenant_id) as active_tenants
            FROM {$tableUsage} 
            WHERE period = %s",
            $currentMonth
        ));
        
        // Get top cost tenants
        $topTenants = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.tenant_key,
                t.domain,
                t.tier,
                SUM(u.api_calls) as total_calls,
                SUM(u.api_cost) as total_cost
            FROM {$tableUsage} u
            JOIN {$wpdb->prefix}sseo_ai_tenants t ON u.tenant_id = t.id
            WHERE u.period = %s
            GROUP BY t.id
            ORDER BY total_cost DESC
            LIMIT 10",
            $currentMonth
        ));
        
        // Get tier distribution
        $tierDistribution = $wpdb->get_results(
            "SELECT t.tier, COUNT(*) as count, COALESCE(SUM(u.api_cost), 0) as total_cost
            FROM {$wpdb->prefix}sseo_ai_tenants t
            LEFT JOIN {$tableUsage} u ON t.id = u.tenant_id AND u.period = '{$wpdb->_real_escape($currentMonth)}'
            WHERE t.status = 'active'
            GROUP BY t.tier"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cost Dashboard', 'sseo-ai-saas'); ?></h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php echo esc_html(date('F Y')); ?> - <?php esc_html_e('Current Month', 'sseo-ai-saas'); ?></h2>
                <div style="display: flex; gap: 30px; margin-top: 15px;">
                    <div>
                        <h3>$<?php echo number_format($monthlyStats->total_cost ?? 0, 2); ?></h3>
                        <p><?php esc_html_e('Total API Costs', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3><?php echo number_format($monthlyStats->total_calls ?? 0); ?></h3>
                        <p><?php esc_html_e('Total API Calls', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3><?php echo $monthlyStats->active_tenants ?? 0; ?></h3>
                        <p><?php esc_html_e('Active Tenants', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3>$<?php 
                            $avg = ($monthlyStats->active_tenants ?? 0) > 0 
                                ? ($monthlyStats->total_cost / $monthlyStats->active_tenants) 
                                : 0;
                            echo number_format($avg, 2);
                        ?></h3>
                        <p><?php esc_html_e('Avg Cost per Tenant', 'sseo-ai-saas'); ?></p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
                    <h3><?php esc_html_e('Top 10 Customers by Cost', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Site', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topTenants as $tenant): ?>
                            <tr>
                                <td><?php echo esc_html($tenant->domain); ?></td>
                                <td><span class="badge badge-<?php echo esc_attr($tenant->tier); ?>">
                                    <?php echo esc_html(ucfirst($tenant->tier)); ?>
                                </span></td>
                                <td><?php echo number_format($tenant->total_calls); ?></td>
                                <td>$<?php echo number_format($tenant->total_cost, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card" style="width: 350px;">
                    <h3><?php esc_html_e('Tier Distribution', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Tenants', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tierDistribution as $tier): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($tier->tier)); ?></td>
                                <td><?php echo $tier->count; ?></td>
                                <td>$<?php echo number_format($tier->total_cost ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h3><?php esc_html_e('API Cost Breakdown by Service', 'sseo-ai-saas'); ?></h3>
                <?php
                $serviceBreakdown = $wpdb->get_results($wpdb->prepare(
                    "SELECT 
                        'api_calls' as metric,
                        SUM(api_calls) as total_calls,
                        SUM(api_cost) as total_cost
                    FROM {$tableUsage}
                    WHERE period = %s
                    UNION ALL
                    SELECT 
                        'serp_requests' as metric,
                        SUM(serp_requests) as total_calls,
                        0 as total_cost
                    FROM {$tableUsage}
                    WHERE period = %s",
                    $currentMonth,
                    $currentMonth
                ));
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Service', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Calls', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('% of Total', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalCost = $monthlyStats->total_cost ?? 0;
                        foreach ($serviceBreakdown as $service): 
                            $percent = $totalCost > 0 ? ($service->total_cost / $totalCost * 100) : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $service->metric))); ?></td>
                            <td><?php echo number_format($service->total_calls); ?></td>
                            <td>$<?php echo number_format($service->total_cost, 2); ?></td>
                            <td><?php echo number_format($percent, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

<?php

namespace AISEOSaaS;

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
            'aiseo-licenses',
            __('SaaS Settings', 'ai-seo-saas'),
            __('Settings', 'ai-seo-saas'),
            'manage_options',
            'aiseo-settings',
            [$this, 'renderSettingsPage']
        );
        
        add_submenu_page(
            'aiseo-licenses',
            __('Cost Dashboard', 'ai-seo-saas'),
            __('Cost Dashboard', 'ai-seo-saas'),
            'manage_options',
            'aiseo-costs',
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
            <h1><?php esc_html_e('AI SEO SaaS Settings', 'ai-seo-saas'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('ai_seo_saas_settings'); ?>
                
                <h2><?php esc_html_e('API Credentials', 'ai-seo-saas'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'ai-seo-saas'); ?></label></th>
                        <td>
                            <input type="password" name="ai_seo_saas_openai_api_key" id="openai_api_key" 
                                   value="<?php echo esc_attr($this->getOpenAiApiKey()); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Your OpenAI API key for AI content generation. Keep this secret!', 'ai-seo-saas'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="openai_model"><?php esc_html_e('OpenAI Model', 'ai-seo-saas'); ?></label></th>
                        <td>
                            <select name="ai_seo_saas_openai_model" id="openai_model">
                                <option value="gpt-4" <?php selected($this->getOpenAiModel(), 'gpt-4'); ?>>GPT-4 (Best quality, higher cost)</option>
                                <option value="gpt-4-turbo" <?php selected($this->getOpenAiModel(), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?php selected($this->getOpenAiModel(), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Cheaper, faster)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="serp_api_key"><?php esc_html_e('SERP API Key', 'ai-seo-saas'); ?></label></th>
                        <td>
                            <input type="password" name="ai_seo_saas_serp_api_key" id="serp_api_key" 
                                   value="<?php echo esc_attr($this->getSerpApiKey()); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('API key for SERP data (DataForSEO, SerpApi, etc.)', 'ai-seo-saas'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="serp_api_provider"><?php esc_html_e('SERP Provider', 'ai-seo-saas'); ?></label></th>
                        <td>
                            <select name="ai_seo_saas_serp_api_provider" id="serp_api_provider">
                                <option value="dataforseo" <?php selected($this->getSerpApiProvider(), 'dataforseo'); ?>>DataForSEO</option>
                                <option value="serpapi" <?php selected($this->getSerpApiProvider(), 'serpapi'); ?>>SerpApi</option>
                                <option value="seranking" <?php selected($this->getSerpApiProvider(), 'seranking'); ?>>SE Ranking</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'ai-seo-saas')); ?>
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
        $tableUsage = $wpdb->prefix . AISEO_TABLE_TENANT_USAGE;
        
        $monthlyStats = $wpdb->get_row(
            "SELECT 
                SUM(api_calls) as total_calls,
                SUM(api_cost) as total_cost,
                COUNT(DISTINCT tenant_id) as active_tenants
            FROM {$tableUsage} 
            WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$currentMonth}'"
        );
        
        // Get top cost tenants
        $topTenants = $wpdb->get_results(
            "SELECT 
                t.tenant_key,
                t.site_url,
                t.license_tier,
                SUM(u.api_calls) as total_calls,
                SUM(u.api_cost) as total_cost
            FROM {$tableUsage} u
            JOIN {$wpdb->prefix}" . AISEO_TABLE_TENANTS . " t ON u.tenant_id = t.id
            WHERE DATE_FORMAT(u.created_at, '%Y-%m') = '{$currentMonth}'
            GROUP BY t.id
            ORDER BY total_cost DESC
            LIMIT 10"
        );
        
        // Get tier distribution
        $tierDistribution = $wpdb->get_results(
            "SELECT license_tier, COUNT(*) as count, SUM(monthly_api_cost) as total_cost
            FROM {$wpdb->prefix}" . AISEO_TABLE_TENANTS . "
            WHERE status = 'active'
            GROUP BY license_tier"
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cost Dashboard', 'ai-seo-saas'); ?></h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php echo esc_html(date('F Y')); ?> - <?php esc_html_e('Current Month', 'ai-seo-saas'); ?></h2>
                <div style="display: flex; gap: 30px; margin-top: 15px;">
                    <div>
                        <h3>$<?php echo number_format($monthlyStats->total_cost ?? 0, 2); ?></h3>
                        <p><?php esc_html_e('Total API Costs', 'ai-seo-saas'); ?></p>
                    </div>
                    <div>
                        <h3><?php echo number_format($monthlyStats->total_calls ?? 0); ?></h3>
                        <p><?php esc_html_e('Total API Calls', 'ai-seo-saas'); ?></p>
                    </div>
                    <div>
                        <h3><?php echo $monthlyStats->active_tenants ?? 0; ?></h3>
                        <p><?php esc_html_e('Active Tenants', 'ai-seo-saas'); ?></p>
                    </div>
                    <div>
                        <h3>$<?php 
                            $avg = ($monthlyStats->active_tenants ?? 0) > 0 
                                ? ($monthlyStats->total_cost / $monthlyStats->active_tenants) 
                                : 0;
                            echo number_format($avg, 2);
                        ?></h3>
                        <p><?php esc_html_e('Avg Cost per Tenant', 'ai-seo-saas'); ?></p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
                    <h3><?php esc_html_e('Top 10 Customers by Cost', 'ai-seo-saas'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Site', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Tier', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('API Calls', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Cost', 'ai-seo-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topTenants as $tenant): ?>
                            <tr>
                                <td><?php echo esc_html($tenant->site_url); ?></td>
                                <td><span class="badge badge-<?php echo esc_attr($tenant->license_tier); ?>">
                                    <?php echo esc_html(ucfirst($tenant->license_tier)); ?>
                                </span></td>
                                <td><?php echo number_format($tenant->total_calls); ?></td>
                                <td>$<?php echo number_format($tenant->total_cost, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card" style="width: 350px;">
                    <h3><?php esc_html_e('Tier Distribution', 'ai-seo-saas'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Tier', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Tenants', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Cost', 'ai-seo-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tierDistribution as $tier): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($tier->license_tier)); ?></td>
                                <td><?php echo $tier->count; ?></td>
                                <td>$<?php echo number_format($tier->total_cost ?? 0, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h3><?php esc_html_e('API Cost Breakdown by Service', 'ai-seo-saas'); ?></h3>
                <?php
                $serviceBreakdown = $wpdb->get_results(
                    "SELECT 
                        metric,
                        SUM(count) as total_calls,
                        SUM(cost) as total_cost
                    FROM {$tableUsage}
                    WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$currentMonth}'
                    GROUP BY metric
                    ORDER BY total_cost DESC"
                );
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Service', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Calls', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Cost', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('% of Total', 'ai-seo-saas'); ?></th>
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

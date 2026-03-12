<?php

namespace SSEOAISaaS;

/**
 * White-Label Admin for SaaS Dashboard
 * Manages white-label settings, client portal, team workspace, and billing
 */
class WhiteLabelAdmin
{
    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'sseo-ai') === false) {
            return;
        }
        
        wp_enqueue_style(
            'sseo-ai-saas-admin',
            plugins_url('assets/license-admin.css', SSEO_AI_SAAS_PLUGIN_FILE),
            [],
            SSEO_AI_SAAS_VERSION
        );
    }

    public function addMenu(): void
    {
        // Removed: Global White-Label menu - now only tenant-level white-label exists
        
        // Client Portal - where tenant white-label is managed
        add_submenu_page(
            'sseo-ai-licenses',
            __('Client Portal', 'sseo-ai-saas'),
            __('Client Portal', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-client-portal',
            [$this, 'renderClientPortal']
        );

        add_submenu_page(
            'sseo-ai-licenses',
            __('Team Management', 'sseo-ai-saas'),
            __('Team', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-team',
            [$this, 'renderTeamManagement']
        );

        add_submenu_page(
            'sseo-ai-licenses',
            __('Billing & Invoicing', 'sseo-ai-saas'),
            __('Billing', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-billing',
            [$this, 'renderBilling']
        );
    }

    public function registerSettings(): void
    {
        // Global white-label settings removed - now only tenant-level white-label
        // White-label is configured per-tenant in Client Portal
        
        // Billing settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_key');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_secret');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_currency');
    }

    public function renderWhiteLabelSettings(): void
    {
        $companyName = get_option('sseo_ai_saas_wl_company_name', '');
        $companyLogo = get_option('sseo_ai_saas_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_saas_wl_primary_color', '#2563eb');
        $secondaryColor = get_option('sseo_ai_saas_wl_secondary_color', '#1e40af');
        $supportEmail = get_option('sseo_ai_saas_wl_support_email', '');
        $supportUrl = get_option('sseo_ai_saas_wl_support_url', '');
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('White-Label Settings', 'sseo-ai-saas'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($companyName ?: '-'); ?></div>
                    <div class="stat-label"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;"><span style="color: <?php echo esc_attr($primaryColor); ?>;">●</span></div>
                    <div class="stat-label"><?php esc_html_e('Primary Color', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size: 24px;"><span style="color: <?php echo esc_attr($secondaryColor); ?>;">●</span></div>
                    <div class="stat-label"><?php esc_html_e('Secondary Color', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($supportEmail ? '✓' : '-'); ?></div>
                    <div class="stat-label"><?php esc_html_e('Support Configured', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('sseo_ai_saas_whitelabel'); ?>
                
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Branding', 'sseo-ai-saas'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="company_name"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" id="company_name" name="sseo_ai_saas_wl_company_name" 
                                       value="<?php echo esc_attr($companyName); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Your company name to display in the client plugin admin menu', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="company_logo"><?php esc_html_e('Company Logo URL', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="url" id="company_logo" name="sseo_ai_saas_wl_company_logo" 
                                       value="<?php echo esc_attr($companyLogo); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('URL to your company logo (recommended: 200x50px, transparent PNG)', 'sseo-ai-saas'); ?></p>
                                <?php if ($companyLogo): ?>
                                    <p><img src="<?php echo esc_url($companyLogo); ?>" alt="Logo preview" style="max-height: 50px; margin-top: 10px; background: #f0f0f0; padding: 5px;"></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="primary_color"><?php esc_html_e('Primary Color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="color" id="primary_color" name="sseo_ai_saas_wl_primary_color" 
                                       value="<?php echo esc_attr($primaryColor); ?>">
                                <span class="color-preview" style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr($primaryColor); ?>; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid #ccc;"></span>
                                <p class="description"><?php esc_html_e('Main brand color used for buttons, headers, and accents', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="secondary_color"><?php esc_html_e('Secondary Color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="color" id="secondary_color" name="sseo_ai_saas_wl_secondary_color" 
                                       value="<?php echo esc_attr($secondaryColor); ?>">
                                <span class="color-preview" style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr($secondaryColor); ?>; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid #ccc;"></span>
                                <p class="description"><?php esc_html_e('Secondary color used for hover states and gradients', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Support Information', 'sseo-ai-saas'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="support_email"><?php esc_html_e('Support Email', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="email" id="support_email" name="sseo_ai_saas_wl_support_email" 
                                       value="<?php echo esc_attr($supportEmail); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Email address shown in client plugin for support requests', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="support_url"><?php esc_html_e('Support URL', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="url" id="support_url" name="sseo_ai_saas_wl_support_url" 
                                       value="<?php echo esc_attr($supportUrl); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('URL to your support portal or documentation', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="sseo-ai-card" style="background: #f0f6fc; border-color: #2271b1;">
                    <h3>ℹ️ <?php esc_html_e('How White-Label Works', 'sseo-ai-saas'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Configure your branding settings above', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('When a client activates their license, these settings are automatically synced', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('The client plugin will display your company name in the admin menu', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('Colors are applied to buttons, headers, and accent elements', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('Support links direct clients to your support channels', 'sseo-ai-saas'); ?></li>
                    </ol>
                    <p><strong><?php esc_html_e('Note:', 'sseo-ai-saas'); ?></strong> <?php esc_html_e('Existing clients need to deactivate and reactivate their license to see updates.', 'sseo-ai-saas'); ?></p>
                </div>
                
                <?php submit_button(__('Save White-Label Settings', 'sseo-ai-saas'), 'primary', 'submit', true, ['style' => 'font-size: 16px; padding: 10px 30px; height: auto;']); ?>
            </form>
        </div>
        <?php
    }

    public function renderClientPortal(): void
    {
        $tenants = $this->tenants->getAllTenants();
        $activeCount = count(array_filter($tenants, fn($t) => $t['status'] === 'active'));
        $inactiveCount = count($tenants) - $activeCount;
        
        // Check if viewing a specific tenant's details
        $viewTenant = isset($_GET['view_tenant']) ? sanitize_text_field($_GET['view_tenant']) : null;
        $tenantDetail = isset($_GET['tenant_detail']) ? sanitize_text_field($_GET['tenant_detail']) : null;
        
        if ($tenantDetail) {
            $this->renderTenantDetail($tenantDetail);
            return;
        }
        
        if ($viewTenant) {
            $this->renderTenantWhiteLabel($viewTenant);
            return;
        }
        
        // Get current month's usage for all tenants
        $currentPeriod = current_time('Y-m');
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Client Portal', 'sseo-ai-saas'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(count($tenants)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Clients', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #00a32a;"><?php echo number_format($activeCount); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Clients', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #d63638;"><?php echo number_format($inactiveCount); ?></div>
                    <div class="stat-label"><?php esc_html_e('Inactive Clients', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($tenants, fn($t) => !empty($t['last_active']) && strtotime($t['last_active']) > strtotime('-7 days'))); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active This Week', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Active Clients', 'sseo-ai-saas'); ?></h2>
                <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Manage your client accounts and their usage. Click "View Details" for usage statistics or "White-Label" to configure custom branding.', 'sseo-ai-saas'); ?></p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Client', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Content', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('White-Label', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenants)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px;">
                                    <p style="font-size: 16px; color: #646970;"><?php esc_html_e('No clients found. Generate licenses to add new clients.', 'sseo-ai-saas'); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-generate-licenses'); ?>" class="button button-primary">
                                        <?php esc_html_e('Generate License', 'sseo-ai-saas'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tenants as $tenant): 
                                $tenantWhiteLabel = $this->tenants->getTenantSetting($tenant['tenant_key'], 'white_label_brand', null);
                                $hasWhiteLabel = !empty($tenantWhiteLabel) && !empty($tenantWhiteLabel['company_name']);
                                $usage = $this->tenants->getTenantUsage($tenant['tenant_key'], $currentPeriod);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($tenant['name'] ?? 'N/A'); ?></strong><br>
                                        <small><?php echo esc_html($tenant['email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo esc_html($tenant['domain'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo esc_attr($tenant['tier'] ?? 'basic'); ?>">
                                            <?php echo esc_html(ucfirst($tenant['tier'] ?? 'Basic')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($tenant['status'] === 'active'): ?>
                                            <span style="color: #00a32a;">● <?php esc_html_e('Active', 'sseo-ai-saas'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #d63638;">● <?php echo esc_html(ucfirst($tenant['status'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $apiCalls = $usage['api_calls'] ?? 0;
                                        $apiLimit = $tenant['api_calls_limit'] ?? 1000;
                                        $percentage = $apiLimit > 0 ? round(($apiCalls / $apiLimit) * 100) : 0;
                                        $color = $percentage > 90 ? '#d63638' : ($percentage > 70 ? '#dba617' : '#00a32a');
                                        ?>
                                        <span style="color: <?php echo esc_attr($color); ?>; font-weight: 600;">
                                            <?php echo number_format($apiCalls); ?> / <?php echo number_format($apiLimit); ?>
                                        </span>
                                        <div style="width: 60px; height: 4px; background: #f0f0f1; border-radius: 2px; margin-top: 4px;">
                                            <div style="width: <?php echo min($percentage, 100); ?>%; height: 100%; background: <?php echo esc_attr($color); ?>; border-radius: 2px;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo number_format($usage['content_generated'] ?? 0); ?>
                                    </td>
                                    <td>
                                        <?php if ($hasWhiteLabel): ?>
                                            <span style="color: #00a32a;">✓ <?php echo esc_html($tenantWhiteLabel['company_name'] ?? 'Custom'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #999;">- <?php esc_html_e('Default', 'sseo-ai-saas'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-client-portal&tenant_detail=' . esc_attr($tenant['tenant_key'])); ?>" class="button button-small">
                                            <?php esc_html_e('View', 'sseo-ai-saas'); ?>
                                        </a>
                                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-client-portal&view_tenant=' . esc_attr($tenant['tenant_key'])); ?>" class="button button-small">
                                            <?php esc_html_e('White-Label', 'sseo-ai-saas'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Multi-Level White-Label', 'sseo-ai-saas'); ?></h2>
                    <p style="color: #646970;"><?php esc_html_e('Configure custom branding for each client so they can resell under their own brand.', 'sseo-ai-saas'); ?></p>
                    
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><?php esc_html_e('Global White-Label: Your default branding', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('Tenant White-Label: Custom branding per client', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('Perfect for resellers and agencies', 'sseo-ai-saas'); ?></li>
                    </ul>
                </div>
                
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Quick Actions', 'sseo-ai-saas'); ?></h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-generate-licenses'); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('Generate New License', 'sseo-ai-saas'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-white-label'); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e('Global White-Label', 'sseo-ai-saas'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render detailed tenant view with usage statistics
     */
    private function renderTenantDetail(string $tenantKey): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            wp_die(__('Tenant not found', 'sseo-ai-saas'));
        }

        $currentPeriod = current_time('Y-m');
        $usage = $this->tenants->getTenantUsage($tenantKey, $currentPeriod);
        $usageHistory = $this->tenants->getTenantUsageHistory($tenantKey, 6);
        
        // Calculate totals
        $totalApiCalls = array_sum(array_column($usageHistory, 'api_calls'));
        $totalContent = array_sum(array_column($usageHistory, 'content_generated'));
        $totalSerp = array_sum(array_column($usageHistory, 'serp_requests'));
        $totalKeywords = array_sum(array_column($usageHistory, 'keywords_tracked'));
        $totalCost = array_sum(array_column($usageHistory, 'api_cost'));
        
        // Get white-label status
        $tenantWhiteLabel = $this->tenants->getTenantSetting($tenantKey, 'white_label_brand', null);
        $whiteLabelEnabled = $this->tenants->getTenantSetting($tenantKey, 'enable_whitelabel', false);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php printf(esc_html__('Client Details: %s', 'sseo-ai-saas'), esc_html($tenant['name'])); ?></h1>
            <p>
                <a href="<?php echo admin_url('admin.php?page=sseo-ai-client-portal'); ?>">← <?php esc_html_e('Back to Client Portal', 'sseo-ai-saas'); ?></a>
                <span style="margin: 0 10px;">|</span>
                <a href="<?php echo admin_url('admin.php?page=sseo-ai-client-portal&view_tenant=' . esc_attr($tenantKey)); ?>"><?php esc_html_e('Edit White-Label', 'sseo-ai-saas'); ?></a>
            </p>
            
            <!-- Client Info Card -->
            <div class="sseo-ai-card" style="background: #f6f7f7;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div>
                        <strong><?php esc_html_e('Tenant Key:', 'sseo-ai-saas'); ?></strong><br>
                        <code><?php echo esc_html($tenant['tenant_key']); ?></code>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Email:', 'sseo-ai-saas'); ?></strong><br>
                        <?php echo esc_html($tenant['email']); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Domain:', 'sseo-ai-saas'); ?></strong><br>
                        <?php echo esc_html($tenant['domain'] ?? '-'); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('License Tier:', 'sseo-ai-saas'); ?></strong><br>
                        <span class="badge badge-<?php echo esc_attr($tenant['tier']); ?>"><?php echo esc_html(ucfirst($tenant['tier'])); ?></span>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Status:', 'sseo-ai-saas'); ?></strong><br>
                        <?php if ($tenant['status'] === 'active'): ?>
                            <span style="color: #00a32a;">● <?php esc_html_e('Active', 'sseo-ai-saas'); ?></span>
                        <?php else: ?>
                            <span style="color: #d63638;">● <?php echo esc_html(ucfirst($tenant['status'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Created:', 'sseo-ai-saas'); ?></strong><br>
                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($tenant['created_at']))); ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('Last Active:', 'sseo-ai-saas'); ?></strong><br>
                        <?php echo $tenant['last_active'] ? esc_html(human_time_diff(strtotime($tenant['last_active']), current_time('timestamp'))) . ' ' . esc_html__('ago', 'sseo-ai-saas') : '-'; ?>
                    </div>
                    <div>
                        <strong><?php esc_html_e('White-Label:', 'sseo-ai-saas'); ?></strong><br>
                        <?php if ($whiteLabelEnabled && !empty($tenantWhiteLabel['company_name'])): ?>
                            <span style="color: #00a32a;">✓ <?php echo esc_html($tenantWhiteLabel['company_name']); ?></span>
                        <?php else: ?>
                            <span style="color: #999;">- <?php esc_html_e('Default', 'sseo-ai-saas'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Current Period Usage Stats -->
            <h2><?php printf(esc_html__('Usage Statistics - %s', 'sseo-ai-saas'), esc_html(date_i18n('F Y', strtotime($currentPeriod . '-01')))); ?></h2>
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($usage['api_calls'] ?? 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></div>
                    <div style="font-size: 12px; color: #646970; margin-top: 5px;">
                        <?php 
                        $apiLimit = $tenant['api_calls_limit'] ?? 1000;
                        $percentage = $apiLimit > 0 ? round((($usage['api_calls'] ?? 0) / $apiLimit) * 100) : 0;
                        echo $percentage; ?>% <?php esc_html_e('of limit', 'sseo-ai-saas'); ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($usage['content_generated'] ?? 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Content Generated', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($usage['serp_requests'] ?? 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('SERP Requests', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($usage['keywords_tracked'] ?? 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Keywords Tracked', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($usage['api_cost'] ?? 0, 2); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Cost', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($tenant['rate_limit'] ?? 60); ?></div>
                    <div class="stat-label"><?php esc_html_e('Rate Limit/min', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <div class="sseo-ai-grid-2">
                <!-- Usage History Table -->
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Usage History (Last 6 Months)', 'sseo-ai-saas'); ?></h2>
                    
                    <?php if (empty($usageHistory)): ?>
                        <p style="color: #646970;"><?php esc_html_e('No usage data available yet.', 'sseo-ai-saas'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat striped" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Period', 'sseo-ai-saas'); ?></th>
                                    <th><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></th>
                                    <th><?php esc_html_e('Content', 'sseo-ai-saas'); ?></th>
                                    <th><?php esc_html_e('SERP', 'sseo-ai-saas'); ?></th>
                                    <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usageHistory as $history): ?>
                                    <tr>
                                        <td><?php echo esc_html(date_i18n('F Y', strtotime($history['period'] . '-01'))); ?></td>
                                        <td><?php echo number_format($history['api_calls'] ?? 0); ?></td>
                                        <td><?php echo number_format($history['content_generated'] ?? 0); ?></td>
                                        <td><?php echo number_format($history['serp_requests'] ?? 0); ?></td>
                                        <td>$<?php echo number_format($history['api_cost'] ?? 0, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr style="background: #f0f6fc; font-weight: 600;">
                                    <td><?php esc_html_e('6 Month Total', 'sseo-ai-saas'); ?></td>
                                    <td><?php echo number_format($totalApiCalls); ?></td>
                                    <td><?php echo number_format($totalContent); ?></td>
                                    <td><?php echo number_format($totalSerp); ?></td>
                                    <td>$<?php echo number_format($totalCost, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Tenant Limits & Info -->
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Plan Limits & Configuration', 'sseo-ai-saas'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Maximum Sites:', 'sseo-ai-saas'); ?></th>
                            <td><?php echo number_format($tenant['max_sites'] ?? 1); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('API Rate Limit:', 'sseo-ai-saas'); ?></th>
                            <td><?php echo number_format($tenant['rate_limit'] ?? 60); ?> <?php esc_html_e('requests/minute', 'sseo-ai-saas'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Monthly API Limit:', 'sseo-ai-saas'); ?></th>
                            <td><?php echo number_format($tenant['api_calls_limit'] ?? 1000); ?> <?php esc_html_e('calls', 'sseo-ai-saas'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Current Usage:', 'sseo-ai-saas'); ?></th>
                            <td>
                                <?php 
                                $currentCalls = $usage['api_calls'] ?? 0;
                                $limit = $tenant['api_calls_limit'] ?? 1000;
                                $remaining = max(0, $limit - $currentCalls);
                                ?>
                                <span style="color: <?php echo $remaining < 100 ? '#d63638' : '#00a32a'; ?>;">
                                    <?php echo number_format($remaining); ?> <?php esc_html_e('remaining', 'sseo-ai-saas'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($tenant['expires_at'])): ?>
                        <tr>
                            <th><?php esc_html_e('Expires:', 'sseo-ai-saas'); ?></th>
                            <td>
                                <?php 
                                $expires = strtotime($tenant['expires_at']);
                                $isExpired = $expires < time();
                                ?>
                                <span style="color: <?php echo $isExpired ? '#d63638' : '#00a32a'; ?>;">
                                    <?php echo esc_html(date_i18n(get_option('date_format'), $expires)); ?>
                                    <?php if ($isExpired): ?> (<?php esc_html_e('Expired', 'sseo-ai-saas'); ?>)<?php endif; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    
                    <h3 style="margin-top: 25px;"><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></h3>
                    <code style="display: block; padding: 10px; background: #f0f0f1; border-radius: 4px; word-break: break-all;">
                        <?php echo esc_html($tenant['license_key'] ?? '-'); ?>
                    </code>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Quick Actions', 'sseo-ai-saas'); ?></h2>
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-client-portal&view_tenant=' . esc_attr($tenantKey)); ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Edit White-Label', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-view-licenses'); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('View All Licenses', 'sseo-ai-saas'); ?>
                    </a>
                    <button type="button" class="button button-secondary button-hero" onclick="alert('<?php esc_attr_e('Export feature coming soon', 'sseo-ai-saas'); ?>')">
                        <?php esc_html_e('Export Usage Report', 'sseo-ai-saas'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render tenant-specific white-label settings
     */
    private function renderTenantWhiteLabel(string $tenantKey): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            wp_die(__('Tenant not found', 'sseo-ai-saas'));
        }

        // Save tenant white-label settings
        if (isset($_POST['save_tenant_whitelabel']) && wp_verify_nonce($_POST['_wpnonce'], 'tenant_whitelabel_' . $tenantKey)) {
            $whiteLabelData = [
                'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
                'company_logo' => esc_url_raw($_POST['company_logo'] ?? ''),
                'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#2563eb'),
                'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#1e40af'),
                'support_email' => sanitize_email($_POST['support_email'] ?? ''),
                'support_url' => esc_url_raw($_POST['support_url'] ?? ''),
            ];
            
            $this->tenants->setTenantSetting($tenantKey, 'white_label_brand', $whiteLabelData);
            $this->tenants->setTenantSetting($tenantKey, 'enable_whitelabel', !empty($_POST['enable_whitelabel']));
            
            echo '<div class="notice notice-success"><p>' . esc_html__('Tenant white-label settings saved successfully!', 'sseo-ai-saas') . '</p></div>';
        }

        // Get current settings
        $whiteLabel = $this->tenants->getTenantSetting($tenantKey, 'white_label_brand', []);
        $enabled = $this->tenants->getTenantSetting($tenantKey, 'enable_whitelabel', false);
        
        // Global fallback values
        $globalWhiteLabel = [
            'company_name' => get_option('sseo_ai_saas_wl_company_name', ''),
            'company_logo' => get_option('sseo_ai_saas_wl_company_logo', ''),
            'primary_color' => get_option('sseo_ai_saas_wl_primary_color', '#2563eb'),
            'secondary_color' => get_option('sseo_ai_saas_wl_secondary_color', '#1e40af'),
            'support_email' => get_option('sseo_ai_saas_wl_support_email', ''),
            'support_url' => get_option('sseo_ai_saas_wl_support_url', ''),
        ];
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php printf(esc_html__('White-Label Settings for: %s', 'sseo-ai-saas'), esc_html($tenant['name'])); ?></h1>
            <p><a href="<?php echo admin_url('admin.php?page=sseo-ai-client-portal'); ?>">← <?php esc_html_e('Back to Client Portal', 'sseo-ai-saas'); ?></a></p>
            
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Tenant White-Label Configuration', 'sseo-ai-saas'); ?></h2>
                    
                    <form method="post">
                        <?php wp_nonce_field('tenant_whitelabel_' . $tenantKey); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Enable Custom White-Label', 'sseo-ai-saas'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="enable_whitelabel" value="1" <?php checked($enabled); ?>>
                                        <?php esc_html_e('Enable custom branding for this tenant', 'sseo-ai-saas'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, this tenant will see their own branding instead of your global branding.', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="company_name"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="text" id="company_name" name="company_name" 
                                           value="<?php echo esc_attr($whiteLabel['company_name'] ?? ''); ?>" class="regular-text"
                                           placeholder="<?php echo esc_attr($globalWhiteLabel['company_name']); ?>">
                                    <p class="description"><?php esc_html_e('Leave empty to use global setting', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="company_logo"><?php esc_html_e('Company Logo URL', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="url" id="company_logo" name="company_logo" 
                                           value="<?php echo esc_attr($whiteLabel['company_logo'] ?? ''); ?>" class="regular-text"
                                           placeholder="<?php echo esc_attr($globalWhiteLabel['company_logo']); ?>">
                                    <?php if (!empty($whiteLabel['company_logo'])): ?>
                                        <p><img src="<?php echo esc_url($whiteLabel['company_logo']); ?>" alt="Logo preview" style="max-height: 50px; margin-top: 10px; background: #f0f0f0; padding: 5px;"></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="primary_color"><?php esc_html_e('Primary Color', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="color" id="primary_color" name="primary_color" 
                                           value="<?php echo esc_attr($whiteLabel['primary_color'] ?? $globalWhiteLabel['primary_color']); ?>">
                                    <span class="color-preview" style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr($whiteLabel['primary_color'] ?? $globalWhiteLabel['primary_color']); ?>; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid #ccc;"></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="secondary_color"><?php esc_html_e('Secondary Color', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="color" id="secondary_color" name="secondary_color" 
                                           value="<?php echo esc_attr($whiteLabel['secondary_color'] ?? $globalWhiteLabel['secondary_color']); ?>">
                                    <span class="color-preview" style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr($whiteLabel['secondary_color'] ?? $globalWhiteLabel['secondary_color']); ?>; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid #ccc;"></span>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="support_email"><?php esc_html_e('Support Email', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="email" id="support_email" name="support_email" 
                                           value="<?php echo esc_attr($whiteLabel['support_email'] ?? ''); ?>" class="regular-text"
                                           placeholder="<?php echo esc_attr($globalWhiteLabel['support_email']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="support_url"><?php esc_html_e('Support URL', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="url" id="support_url" name="support_url" 
                                           value="<?php echo esc_attr($whiteLabel['support_url'] ?? ''); ?>" class="regular-text"
                                           placeholder="<?php echo esc_attr($globalWhiteLabel['support_url']); ?>">
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Save Tenant White-Label Settings', 'sseo-ai-saas'), 'primary', 'save_tenant_whitelabel'); ?>
                    </form>
                </div>
                
                <div class="sseo-ai-card" style="background: #f0f6fc; border-color: #2271b1;">
                    <h3>ℹ️ <?php esc_html_e('How Multi-Level White-Label Works', 'sseo-ai-saas'); ?></h3>
                    
                    <h4><?php esc_html_e('Hierarchy:', 'sseo-ai-saas'); ?></h4>
                    <ol>
                        <li><strong><?php esc_html_e('Global White-Label', 'sseo-ai-saas'); ?></strong> - <?php esc_html_e('Your default branding', 'sseo-ai-saas'); ?></li>
                        <li><strong><?php esc_html_e('Tenant White-Label', 'sseo-ai-saas'); ?></strong> - <?php esc_html_e('Custom branding per client (if enabled)', 'sseo-ai-saas'); ?></li>
                    </ol>
                    
                    <h4><?php esc_html_e('Use Cases:', 'sseo-ai-saas'); ?></h4>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><?php esc_html_e('Agencies reselling your platform under their brand', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('White-label partners with custom branding', 'sseo-ai-saas'); ?></li>
                        <li><?php esc_html_e('Enterprise clients wanting their own branding', 'sseo-ai-saas'); ?></li>
                    </ul>
                    
                    <p><strong><?php esc_html_e('Tenant:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($tenant['name']); ?> (<?php echo esc_html($tenant['tenant_key']); ?>)</p>
                    <p><strong><?php esc_html_e('License:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($tenant['tier']); ?> Tier</p>
                    <p><strong><?php esc_html_e('Domain:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($tenant['domain'] ?? '-'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderTeamManagement(): void
    {
        $users = get_users(['role__in' => ['administrator', 'editor']]);
        $adminCount = count(array_filter($users, fn($u) => in_array('administrator', $u->roles)));
        $editorCount = count($users) - $adminCount;
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Team Management', 'sseo-ai-saas'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(count($users)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Members', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #2271b1;"><?php echo number_format($adminCount); ?></div>
                    <div class="stat-label"><?php esc_html_e('Administrators', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #dba617;"><?php echo number_format($editorCount); ?></div>
                    <div class="stat-label"><?php esc_html_e('Editors', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">-</div>
                    <div class="stat-label"><?php esc_html_e('Last Added', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Team Members', 'sseo-ai-saas'); ?></h2>
                    <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Manage team members who have access to the SaaS dashboard.', 'sseo-ai-saas'); ?></p>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Email', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Role', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <?php echo get_avatar($user->ID, 32); ?>
                                        <strong style="margin-left: 8px;"><?php echo esc_html($user->display_name); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($user->user_email); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($user->roles[0] ?? 'user'); ?>"><?php echo esc_html(ucfirst($user->roles[0] ?? 'User')); ?></span></td>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>" class="button button-small">
                                            <?php esc_html_e('Edit', 'sseo-ai-saas'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p style="margin-top: 20px;">
                        <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('Add Team Member', 'sseo-ai-saas'); ?>
                        </a>
                    </p>
                </div>
                
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Role Permissions', 'sseo-ai-saas'); ?></h2>
                    <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Configure what each role can access in the SaaS dashboard.', 'sseo-ai-saas'); ?></p>
                    
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Role', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Access Level', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('Administrator', 'sseo-ai-saas'); ?></strong></td>
                                <td><?php esc_html_e('Full access to all features including billing and settings', 'sseo-ai-saas'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Editor', 'sseo-ai-saas'); ?></strong></td>
                                <td><?php esc_html_e('Can manage licenses and view client data, no billing access', 'sseo-ai-saas'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderBilling(): void
    {
        $providers = PaymentProcessor::getProviders();
        $currentProvider = get_option('sseo_ai_saas_payment_provider', 'stripe');
        $stripeKey = get_option('sseo_ai_saas_stripe_key', '');
        $stripeSecret = get_option('sseo_ai_saas_stripe_secret', '');
        $stripeWebhookSecret = get_option('sseo_ai_saas_stripe_webhook_secret', '');
        $mollieApiKey = get_option('sseo_ai_saas_mollie_api_key', '');
        $mollieWebhookSecret = get_option('sseo_ai_saas_mollie_webhook_secret', '');
        $currency = get_option('sseo_ai_saas_currency', 'EUR');
        
        // Get webhook URLs
        $webhookUrls = [
            'stripe' => rest_url('ai-seo-saas/v1/webhooks/stripe'),
            'mollie' => rest_url('ai-seo-saas/v1/webhooks/mollie'),
        ];
        
        // Get revenue stats
        $tenants = $this->tenants->getAllTenants();
        $totalRevenue = 0;
        $activeSubscriptions = 0;
        $paidTenants = array_filter($tenants, fn($t) => $t['status'] === 'active');
        
        foreach ($paidTenants as $tenant) {
            $activeSubscriptions++;
            switch ($tenant['tier'] ?? 'basic') {
                case 'agency':
                    $totalRevenue += 99;
                    break;
                case 'professional':
                    $totalRevenue += 49;
                    break;
                default:
                    $totalRevenue += 19;
            }
        }
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Billing & Invoicing', 'sseo-ai-saas'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value" style="color: #00a32a;">€<?php echo number_format($totalRevenue, 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Monthly Revenue (MRR)', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #2563eb;"><?php echo number_format($activeSubscriptions); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Subscriptions', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(count($tenants)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Clients', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">€<?php echo number_format($totalRevenue * 12, 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Est. Annual Revenue', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <div class="sseo-ai-grid-2">
                <!-- Payment Provider Settings -->
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Payment Provider Settings', 'sseo-ai-saas'); ?></h2>
                    <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Choose your payment provider and configure API credentials.', 'sseo-ai-saas'); ?></p>
                    
                    <form method="post" action="options.php">
                        <?php settings_fields('sseo_ai_saas_billing'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="payment_provider"><?php esc_html_e('Payment Provider', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select id="payment_provider" name="sseo_ai_saas_payment_provider" style="width: 100%;">
                                        <?php foreach ($providers as $key => $provider): ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($currentProvider, $key); ?>>
                                                <?php echo esc_html($provider['name']); ?> - <?php echo esc_html($provider['description']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Select the payment provider for automatic billing.', 'sseo-ai-saas'); ?><br>
                                        <strong>Stripe:</strong> <?php esc_html_e('Credit cards, global coverage', 'sseo-ai-saas'); ?><br>
                                        <strong>Mollie:</strong> <?php esc_html_e('iDEAL, Bancontact, SEPA - Best for Europe', 'sseo-ai-saas'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="currency"><?php esc_html_e('Currency', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select id="currency" name="sseo_ai_saas_currency">
                                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€) - Europe</option>
                                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($) - United States</option>
                                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£) - United Kingdom</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <hr style="margin: 25px 0;">
                        
                        <h3><?php esc_html_e('Stripe Configuration', 'sseo-ai-saas'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stripe_key"><?php esc_html_e('Publishable Key', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="text" id="stripe_key" name="sseo_ai_saas_stripe_key" 
                                           value="<?php echo esc_attr($stripeKey); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('pk_live_... or pk_test_...', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stripe_secret"><?php esc_html_e('Secret Key', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="password" id="stripe_secret" name="sseo_ai_saas_stripe_secret" 
                                           value="<?php echo $stripeSecret ? '••••••••••••' : ''; ?>" class="regular-text" 
                                           placeholder="<?php esc_attr_e('Enter to update', 'sseo-ai-saas'); ?>">
                                    <p class="description"><?php esc_html_e('sk_live_... or sk_test_... Keep this secure!', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stripe_webhook_secret"><?php esc_html_e('Webhook Secret', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="password" id="stripe_webhook_secret" name="sseo_ai_saas_stripe_webhook_secret" 
                                           value="<?php echo $stripeWebhookSecret ? '••••••••••••' : ''; ?>" class="regular-text"
                                           placeholder="<?php esc_attr_e('Enter to update', 'sseo-ai-saas'); ?>">
                                    <p class="description"><?php esc_html_e('whsec_... for webhook verification', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <hr style="margin: 25px 0;">
                        
                        <h3><?php esc_html_e('Mollie Configuration', 'sseo-ai-saas'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="mollie_api_key"><?php esc_html_e('API Key', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="password" id="mollie_api_key" name="sseo_ai_saas_mollie_api_key" 
                                           value="<?php echo $mollieApiKey ? '••••••••••••' : ''; ?>" class="regular-text"
                                           placeholder="<?php esc_attr_e('Enter to update', 'sseo-ai-saas'); ?>">
                                    <p class="description"><?php esc_html_e('live_... or test_... from Mollie Dashboard', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Save Billing Settings', 'sseo-ai-saas'), 'primary', 'submit', true, ['style' => 'font-size: 16px; padding: 10px 30px; height: auto; margin-top: 20px;']); ?>
                    </form>
                </div>
                
                <!-- Webhook URLs & Instructions -->
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Webhook URLs', 'sseo-ai-saas'); ?></h2>
                    <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Add these URLs to your payment provider dashboards to receive payment notifications.', 'sseo-ai-saas'); ?></p>
                    
                    <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                        <h4 style="margin-top: 0;">🔗 <?php esc_html_e('Stripe Webhook URL', 'sseo-ai-saas'); ?></h4>
                        <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; word-break: break-all; font-size: 12px;">
                            <?php echo esc_url($webhookUrls['stripe']); ?>
                        </code>
                        <p style="margin: 10px 0 0; font-size: 12px; color: #646970;">
                            <?php esc_html_e('Add this in Stripe Dashboard → Developers → Webhooks', 'sseo-ai-saas'); ?><br>
                            <?php esc_html_e('Events to listen for:', 'sseo-ai-saas'); ?> <code>invoice.payment_succeeded</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.deleted</code>
                        </p>
                    </div>
                    
                    <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                        <h4 style="margin-top: 0;">🔗 <?php esc_html_e('Mollie Webhook URL', 'sseo-ai-saas'); ?></h4>
                        <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; word-break: break-all; font-size: 12px;">
                            <?php echo esc_url($webhookUrls['mollie']); ?>
                        </code>
                        <p style="margin: 10px 0 0; font-size: 12px; color: #646970;">
                            <?php esc_html_e('Add this in Mollie Dashboard → Settings → Webhooks', 'sseo-ai-saas'); ?>
                        </p>
                    </div>
                    
                    <div style="background: #fcf9e8; border: 1px solid #dba617; padding: 15px; border-radius: 4px;">
                        <h4 style="margin-top: 0; color: #dba617;">⚠️ <?php esc_html_e('Important', 'sseo-ai-saas'); ?></h4>
                        <ul style="margin: 0; padding-left: 20px; font-size: 13px;">
                            <li><?php esc_html_e('Webhooks allow automatic subscription management', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Without webhooks, you must manually verify payments', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Keep your webhook secrets secure', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Test webhooks in sandbox mode first', 'sseo-ai-saas'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Pricing Tiers', 'sseo-ai-saas'); ?></h2>
                <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Current pricing structure and client distribution.', 'sseo-ai-saas'); ?></p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Price/Month', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('AI Requests', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Rate Limit', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Clients', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-basic">Basic</span></td>
                            <td><strong>€19/month</strong></td>
                            <td>1,000 requests</td>
                            <td>60/min</td>
                            <td><?php echo number_format(count(array_filter($tenants, fn($t) => ($t['tier'] ?? '') === 'basic'))); ?></td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-professional">Professional</span></td>
                            <td><strong>€49/month</strong></td>
                            <td>5,000 requests</td>
                            <td>120/min</td>
                            <td><?php echo number_format(count(array_filter($tenants, fn($t) => ($t['tier'] ?? '') === 'professional'))); ?></td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-agency">Agency</span></td>
                            <td><strong>€99/month</strong></td>
                            <td>Unlimited</td>
                            <td>300/min</td>
                            <td><?php echo number_format(count(array_filter($tenants, fn($t) => ($t['tier'] ?? '') === 'agency'))); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="sseo-ai-card" style="background: #f0f6fc; border-color: #2271b1;">
                <h3>💡 <?php esc_html_e('Payment Integration Guide', 'sseo-ai-saas'); ?></h3>
                
                <div class="sseo-ai-grid-2" style="margin-top: 20px;">
                    <div>
                        <h4><?php esc_html_e('Stripe Setup', 'sseo-ai-saas'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Sign up at stripe.com', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Get API keys from Dashboard', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Add webhook URL above', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Create subscription products', 'sseo-ai-saas'); ?></li>
                        </ol>
                        <p><strong><?php esc_html_e('Best for:', 'sseo-ai-saas'); ?></strong> <?php esc_html_e('Global credit card payments', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h4><?php esc_html_e('Mollie Setup', 'sseo-ai-saas'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Sign up at mollie.com', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Complete business verification', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Get API key from Dashboard', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Add webhook URL above', 'sseo-ai-saas'); ?></li>
                        </ol>
                        <p><strong><?php esc_html_e('Best for:', 'sseo-ai-saas'); ?></strong> <?php esc_html_e('European payments (iDEAL, Bancontact)', 'sseo-ai-saas'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

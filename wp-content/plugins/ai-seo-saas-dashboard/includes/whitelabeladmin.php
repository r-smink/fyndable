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
        add_submenu_page(
            'sseo-ai-licenses',
            __('White-Label Settings', 'sseo-ai-saas'),
            __('White-Label', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-white-label',
            [$this, 'renderWhiteLabelSettings']
        );

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
        // White-label branding
        register_setting('sseo_ai_saas_whitelabel', 'sseo_ai_saas_wl_company_name');
        register_setting('sseo_ai_saas_whitelabel', 'sseo_ai_saas_wl_company_logo');
        register_setting('sseo_ai_saas_whitelabel', 'sseo_ai_saas_wl_primary_color');
        register_setting('sseo_ai_saas_whitelabel', 'sseo_ai_saas_wl_secondary_color');
        register_setting('sseo_ai_saas_whitelabel', 'sseo_ai_saas_wl_support_email');
        register_setting('sseo_ai_saas_whitelabel', 'sseo_ai_saas_wl_support_url');
        
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
        $activeCount = count(array_filter($tenants, fn($t) => $t->is_active ?? false));
        $inactiveCount = count($tenants) - $activeCount;
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
                    <div class="stat-value"><?php echo count(array_filter($tenants, fn($t) => ($t->last_active ?? false) && strtotime($t->last_active) > strtotime('-7 days'))); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active This Week', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Active Clients', 'sseo-ai-saas'); ?></h2>
                <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Manage your client accounts and their access to the SEO platform.', 'sseo-ai-saas'); ?></p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Client', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('License Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenants)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <p style="font-size: 16px; color: #646970;"><?php esc_html_e('No clients found. Generate licenses to add new clients.', 'sseo-ai-saas'); ?></p>
                                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-generate-licenses'); ?>" class="button button-primary">
                                        <?php esc_html_e('Generate License', 'sseo-ai-saas'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tenants as $tenant): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($tenant->email ?? 'N/A'); ?></strong></td>
                                    <td><?php echo esc_html($tenant->domain ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo esc_attr(strtolower($tenant->tier ?? 'basic')); ?>">
                                            <?php echo esc_html(ucfirst($tenant->tier ?? 'Basic')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($tenant->is_active ?? false): ?>
                                            <span style="color: #00a32a;">● <?php esc_html_e('Active', 'sseo-ai-saas'); ?></span>
                                        <?php else: ?>
                                            <span style="color: #d63638;">● <?php esc_html_e('Inactive', 'sseo-ai-saas'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($tenant->last_active ?? 'Never'); ?></td>
                                    <td>
                                        <a href="#" class="button button-small"><?php esc_html_e('View', 'sseo-ai-saas'); ?></a>
                                        <a href="#" class="button button-small"><?php esc_html_e('Edit', 'sseo-ai-saas'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Client Portal Settings', 'sseo-ai-saas'); ?></h2>
                    <p><?php esc_html_e('Configure the client-facing portal where your clients can manage their SEO settings.', 'sseo-ai-saas'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Portal URL', 'sseo-ai-saas'); ?></th>
                            <td>
                                <code><?php echo esc_url(home_url('/client-portal/')); ?></code>
                                <p class="description"><?php esc_html_e('Share this URL with your clients to access their dashboard.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Quick Actions', 'sseo-ai-saas'); ?></h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-generate-licenses'); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('Generate New License', 'sseo-ai-saas'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=sseo-ai-view-licenses'); ?>" class="button button-secondary button-hero">
                            <?php esc_html_e('View All Licenses', 'sseo-ai-saas'); ?>
                        </a>
                    </div>
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
        $stripeKey = get_option('sseo_ai_saas_stripe_key', '');
        $currency = get_option('sseo_ai_saas_currency', 'EUR');
        
        // Get revenue stats
        $tenants = $this->tenants->getAllTenants();
        $totalRevenue = 0;
        $activeSubscriptions = 0;
        
        foreach ($tenants as $tenant) {
            if ($tenant->is_active ?? false) {
                $activeSubscriptions++;
                // Estimate revenue based on tier
                switch (strtolower($tenant->tier ?? 'basic')) {
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
                <div class="sseo-ai-card">
                    <h2><?php esc_html_e('Payment Gateway Settings', 'sseo-ai-saas'); ?></h2>
                    <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Configure your Stripe integration for automatic billing.', 'sseo-ai-saas'); ?></p>
                    
                    <form method="post" action="options.php">
                        <?php settings_fields('sseo_ai_saas_billing'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="stripe_key"><?php esc_html_e('Stripe Publishable Key', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="text" id="stripe_key" name="sseo_ai_saas_stripe_key" 
                                           value="<?php echo esc_attr($stripeKey); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e('Your Stripe publishable key (pk_live_...)', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="stripe_secret"><?php esc_html_e('Stripe Secret Key', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <input type="password" id="stripe_secret" name="sseo_ai_saas_stripe_secret" 
                                           value="" class="regular-text" placeholder="<?php esc_attr_e('Enter to update', 'sseo-ai-saas'); ?>">
                                    <p class="description"><?php esc_html_e('Your Stripe secret key (sk_live_...)', 'sseo-ai-saas'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="currency"><?php esc_html_e('Currency', 'sseo-ai-saas'); ?></label></th>
                                <td>
                                    <select id="currency" name="sseo_ai_saas_currency">
                                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€)</option>
                                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
                                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£)</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Save Billing Settings', 'sseo-ai-saas'), 'primary', 'submit', true, ['style' => 'font-size: 16px; padding: 10px 30px; height: auto;']); ?>
                    </form>
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
                                <th><?php esc_html_e('Clients', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge badge-basic">Basic</span></td>
                                <td><strong>€19/month</strong></td>
                                <td>1,000 requests</td>
                                <td><?php echo number_format(count(array_filter($tenants, fn($t) => strtolower($t->tier ?? '') === 'basic'))); ?></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-professional">Professional</span></td>
                                <td><strong>€49/month</strong></td>
                                <td>5,000 requests</td>
                                <td><?php echo number_format(count(array_filter($tenants, fn($t) => strtolower($t->tier ?? '') === 'professional'))); ?></td>
                            </tr>
                            <tr>
                                <td><span class="badge badge-agency">Agency</span></td>
                                <td><strong>€99/month</strong></td>
                                <td>Unlimited</td>
                                <td><?php echo number_format(count(array_filter($tenants, fn($t) => strtolower($t->tier ?? '') === 'agency'))); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="sseo-ai-card" style="background: #f0f6fc; border-color: #2271b1;">
                <h3>💡 <?php esc_html_e('Billing Integration Info', 'sseo-ai-saas'); ?></h3>
                <p><?php esc_html_e('To enable automatic billing:', 'sseo-ai-saas'); ?></p>
                <ol>
                    <li><?php esc_html_e('Sign up for a Stripe account at stripe.com', 'sseo-ai-saas'); ?></li>
                    <li><?php esc_html_e('Get your API keys from the Stripe Dashboard', 'sseo-ai-saas'); ?></li>
                    <li><?php esc_html_e('Enter the keys above and save', 'sseo-ai-saas'); ?></li>
                    <li><?php esc_html_e('Create subscription products in Stripe for each tier', 'sseo-ai-saas'); ?></li>
                </ol>
                <p><strong><?php esc_html_e('Note:', 'sseo-ai-saas'); ?></strong> <?php esc_html_e('Keep your secret key secure. Never share it or commit it to version control.', 'sseo-ai-saas'); ?></p>
            </div>
        </div>
        <?php
    }
}

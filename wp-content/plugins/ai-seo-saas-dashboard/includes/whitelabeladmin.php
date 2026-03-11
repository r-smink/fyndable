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

    public function addMenu(): void
    {
        add_submenu_page(
            'sseo-ai-saas',
            __('White-Label Settings', 'sseo-ai-saas'),
            __('White-Label', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-white-label',
            [$this, 'renderWhiteLabelSettings']
        );

        add_submenu_page(
            'sseo-ai-saas',
            __('Client Portal', 'sseo-ai-saas'),
            __('Client Portal', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-client-portal',
            [$this, 'renderClientPortal']
        );

        add_submenu_page(
            'sseo-ai-saas',
            __('Team Management', 'sseo-ai-saas'),
            __('Team', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-team',
            [$this, 'renderTeamManagement']
        );

        add_submenu_page(
            'sseo-ai-saas',
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
        <div class="wrap">
            <h1><?php esc_html_e('White-Label Settings', 'sseo-ai-saas'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('sseo_ai_saas_whitelabel'); ?>
                
                <div class="postbox" style="padding: 20px; margin-top: 20px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e('Branding', 'sseo-ai-saas'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="company_name"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" id="company_name" name="sseo_ai_saas_wl_company_name" 
                                       value="<?php echo esc_attr($companyName); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Your company name to display in the client plugin', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="company_logo"><?php esc_html_e('Company Logo URL', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="url" id="company_logo" name="sseo_ai_saas_wl_company_logo" 
                                       value="<?php echo esc_attr($companyLogo); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('URL to your company logo (recommended: 200x50px)', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="primary_color"><?php esc_html_e('Primary Color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="color" id="primary_color" name="sseo_ai_saas_wl_primary_color" 
                                       value="<?php echo esc_attr($primaryColor); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="secondary_color"><?php esc_html_e('Secondary Color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="color" id="secondary_color" name="sseo_ai_saas_wl_secondary_color" 
                                       value="<?php echo esc_attr($secondaryColor); ?>">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="postbox" style="padding: 20px; margin-top: 20px;">
                    <h2 style="margin-top: 0;"><?php esc_html_e('Support Information', 'sseo-ai-saas'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="support_email"><?php esc_html_e('Support Email', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="email" id="support_email" name="sseo_ai_saas_wl_support_email" 
                                       value="<?php echo esc_attr($supportEmail); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="support_url"><?php esc_html_e('Support URL', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="url" id="support_url" name="sseo_ai_saas_wl_support_url" 
                                       value="<?php echo esc_attr($supportUrl); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save White-Label Settings', 'sseo-ai-saas')); ?>
            </form>
        </div>
        <?php
    }

    public function renderClientPortal(): void
    {
        $tenants = $this->tenants->getAllTenants();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Client Portal', 'sseo-ai-saas'); ?></h1>
            <p><?php esc_html_e('Manage your client accounts and their access to the SEO platform.', 'sseo-ai-saas'); ?></p>
            
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Active Clients', 'sseo-ai-saas'); ?></h2>
                
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
                                <td colspan="6"><?php esc_html_e('No clients found.', 'sseo-ai-saas'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tenants as $tenant): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($tenant->email ?? 'N/A'); ?></strong></td>
                                    <td><?php echo esc_html($tenant->domain ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="license-tier tier-<?php echo esc_attr(strtolower($tenant->tier ?? 'basic')); ?>">
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
            
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Client Portal Settings', 'sseo-ai-saas'); ?></h2>
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
        </div>
        
        <style>
            .license-tier {
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .tier-basic { background: #f0f0f0; color: #50575e; }
            .tier-professional { background: #e7f3ff; color: #0073aa; }
            .tier-agency { background: #ecfdf5; color: #059669; }
        </style>
        <?php
    }

    public function renderTeamManagement(): void
    {
        $users = get_users(['role__in' => ['administrator', 'editor']]);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Team Management', 'sseo-ai-saas'); ?></h1>
            <p><?php esc_html_e('Manage team members who have access to the SaaS dashboard.', 'sseo-ai-saas'); ?></p>
            
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Team Members', 'sseo-ai-saas'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Email', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Role', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Login', 'sseo-ai-saas'); ?></th>
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
                                <td><?php echo esc_html(ucfirst($user->roles[0] ?? 'User')); ?></td>
                                <td><?php echo esc_html(get_user_meta($user->ID, 'last_login', true) ?: 'Never'); ?></td>
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
                    <a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="button button-primary">
                        <?php esc_html_e('Add Team Member', 'sseo-ai-saas'); ?>
                    </a>
                </p>
            </div>
            
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Role Permissions', 'sseo-ai-saas'); ?></h2>
                <p><?php esc_html_e('Configure what each role can access in the SaaS dashboard.', 'sseo-ai-saas'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Administrator', 'sseo-ai-saas'); ?></th>
                        <td><?php esc_html_e('Full access to all features including billing and settings', 'sseo-ai-saas'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Editor', 'sseo-ai-saas'); ?></th>
                        <td><?php esc_html_e('Can manage licenses and view client data, no billing access', 'sseo-ai-saas'); ?></td>
                    </tr>
                </table>
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
        <div class="wrap">
            <h1><?php esc_html_e('Billing & Invoicing', 'sseo-ai-saas'); ?></h1>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e('Monthly Revenue', 'sseo-ai-saas'); ?></h3>
                    <div style="font-size: 36px; font-weight: bold; color: #00a32a; margin: 10px 0;">
                        €<?php echo number_format($totalRevenue, 2); ?>
                    </div>
                    <p style="margin: 0; color: #999; font-size: 12px;"><?php esc_html_e('Estimated MRR', 'sseo-ai-saas'); ?></p>
                </div>
                
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e('Active Subscriptions', 'sseo-ai-saas'); ?></h3>
                    <div style="font-size: 36px; font-weight: bold; color: #2563eb; margin: 10px 0;">
                        <?php echo $activeSubscriptions; ?>
                    </div>
                    <p style="margin: 0; color: #999; font-size: 12px;"><?php esc_html_e('Paying customers', 'sseo-ai-saas'); ?></p>
                </div>
                
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php esc_html_e('Total Clients', 'sseo-ai-saas'); ?></h3>
                    <div style="font-size: 36px; font-weight: bold; color: #7c3aed; margin: 10px 0;">
                        <?php echo count($tenants); ?>
                    </div>
                    <p style="margin: 0; color: #999; font-size: 12px;"><?php esc_html_e('All time', 'sseo-ai-saas'); ?></p>
                </div>
            </div>
            
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Payment Gateway Settings', 'sseo-ai-saas'); ?></h2>
                
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
                    
                    <?php submit_button(__('Save Billing Settings', 'sseo-ai-saas')); ?>
                </form>
            </div>
            
            <div class="postbox" style="padding: 20px; margin-top: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Pricing Tiers', 'sseo-ai-saas'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Price/Month', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('AI Requests', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Active Clients', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Basic</strong></td>
                            <td>€19/month</td>
                            <td>1,000 requests</td>
                            <td><?php echo count(array_filter($tenants, fn($t) => strtolower($t->tier ?? '') === 'basic')); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Professional</strong></td>
                            <td>€49/month</td>
                            <td>5,000 requests</td>
                            <td><?php echo count(array_filter($tenants, fn($t) => strtolower($t->tier ?? '') === 'professional')); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Agency</strong></td>
                            <td>€99/month</td>
                            <td>Unlimited</td>
                            <td><?php echo count(array_filter($tenants, fn($t) => strtolower($t->tier ?? '') === 'agency')); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

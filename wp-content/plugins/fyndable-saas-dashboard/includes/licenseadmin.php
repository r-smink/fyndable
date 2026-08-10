<?php

namespace SSEOAISaaS;

/**
 * License Key Admin Interface
 * 
 * Provides admin pages for generating and managing license keys
 * for the self-hosted SaaS license system.
 */
class LicenseAdmin
{
    private LicenseKeyGenerator $licenseGenerator;
    private TenantRepository $tenants;
    private string $pluginFile;
    
    public function __construct(string $pluginFile, LicenseKeyGenerator $licenseGenerator, TenantRepository $tenants)
    {
        $this->pluginFile = $pluginFile;
        $this->licenseGenerator = $licenseGenerator;
        $this->tenants = $tenants;
    }
    
    /**
     * Register admin menu items
     */
    public function register(): void
    {
        // License Management — registered as hidden parent (shell is top-level)
        add_menu_page(
            __('License Management', 'sseo-ai-saas'),
            __('Licenses', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-licenses',
            [$this, 'renderLicenseDashboard'],
            'dashicons-admin-network',
            30
        );
        // Hide from WP admin menu (shell provides navigation)
        add_action('admin_head', function () {
            echo '<style>#toplevel_page_sseo-ai-licenses { display: none !important; }</style>';
        });
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('License Dashboard', 'sseo-ai-saas'),
            __('Dashboard', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-licenses',
            [$this, 'renderLicenseDashboard']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Generate License Keys', 'sseo-ai-saas'),
            __('Generate Keys', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-generate-licenses',
            [$this, 'renderGeneratePage']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('View All Licenses', 'sseo-ai-saas'),
            __('All Licenses', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-view-licenses',
            [$this, 'renderAllLicenses']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Tenants', 'sseo-ai-saas'),
            __('Tenants', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-tenants',
            [$this, 'renderTenantsPage']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Usage Reports', 'sseo-ai-saas'),
            __('Usage Reports', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-usage-reports',
            [$this, 'renderUsageReports']
        );

        // License Features (hidden from menu, accessed via license edit)
        add_submenu_page(
            null, // Hidden from menu
            __('License Features', 'sseo-ai-saas'),
            __('License Features', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-license-features',
            [$this, 'renderLicenseFeaturesPage']
        );

        // Agency Accounts
        add_submenu_page(
            'sseo-ai-licenses',
            __('Agency Accounts', 'sseo-ai-saas'),
            __('Agency Accounts', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-agency-accounts',
            [$this, 'renderAgencyAccountsPage']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'sseo-ai') === false) {
            return;
        }
        
        wp_enqueue_style(
            'sseo-ai-license-admin',
            plugins_url('assets/license-admin.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/license-admin.css')
        );
        
        wp_enqueue_script(
            'sseo-ai-license-admin',
            plugins_url('assets/license-admin.js', $this->pluginFile),
            ['jquery'],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/license-admin.js'),
            true
        );
    }
    
    /**
     * Render license dashboard
     */
    public function renderLicenseDashboard(): void
    {
        $stats = $this->licenseGenerator->getLicenseStats();
        $recentLicenses = $this->licenseGenerator->getLicenses(['status' => 'active'], 10, 0);
        $recentTenants = $this->tenants->getTenants([], 10, 0);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('License Management Dashboard', 'sseo-ai-saas'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Licenses', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['created_today']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Created Today', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['created_this_month']); ?></div>
                    <div class="stat-label"><?php esc_html_e('This Month', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(count($recentTenants)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Tenants', 'sseo-ai-saas'); ?></div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Quick Actions', 'sseo-ai-saas'); ?></h2>
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-generate-licenses'); ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Generate License Keys', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-view-licenses'); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('View All Licenses', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-tenants'); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('Manage Tenants', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Stats by Type -->
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Licenses by Status', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Count', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['by_status'] as $row): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($row['status'])); ?></td>
                                <td><?php echo number_format($row['count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Licenses by Type', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Type', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Count', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['by_type'] as $row): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($row['license_type'])); ?></td>
                                <td><?php echo number_format($row['count']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Licenses -->
            <div class="sseo-ai-card">
                <h3><?php esc_html_e('Recent Licenses', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Type', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Assigned To', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLicenses as $license): ?>
                        <tr>
                            <td><code><?php echo esc_html(substr($license['license_key'], 0, 20) . '...'); ?></code></td>
                            <td><?php echo esc_html(ucfirst($license['license_type'])); ?></td>
                            <td><?php echo esc_html(ucfirst($license['tier'])); ?></td>
                            <td><span class="badge badge-<?php echo esc_attr($license['status']); ?>"><?php echo esc_html(ucfirst($license['status'])); ?></span></td>
                            <td><?php echo esc_html($license['assigned_to'] ?: '-'); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($license['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render generate license page
     */
    public function renderGeneratePage(): void
    {
        $generated = null;
        $error = null;
        
        if (isset($_POST['generate_license']) && wp_verify_nonce($_POST['_wpnonce'], 'generate_license')) {
            $count = (int)($_POST['license_count'] ?? 1);
            $options = [
                'type' => sanitize_text_field($_POST['license_type'] ?? 'paid'),
                'tier' => sanitize_text_field($_POST['license_tier'] ?? 'starter'),
                'max_sites' => (int)($_POST['max_sites'] ?? 1),
                'rate_limit' => (int)($_POST['rate_limit'] ?? 60),
                'api_calls_limit' => (int)($_POST['api_calls_limit'] ?? 1000),
                'expires_days' => !empty($_POST['expires_days']) ? (int)$_POST['expires_days'] : null,
                'assigned_to' => sanitize_email($_POST['assigned_to'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            ];
            
            if ($count === 1) {
                $result = $this->licenseGenerator->generateLicense($options);
            } else {
                $result = $this->licenseGenerator->batchGenerateLicenses($count, $options);
            }
            
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                $generated = $result;
            }
        }
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Generate License Keys', 'sseo-ai-saas'); ?></h1>
            
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            
            <?php if ($generated): ?>
                <div class="notice notice-success">
                    <p><?php 
                        if (isset($generated['generated'])) {
                            printf(
                                esc_html__('Successfully generated %1$d license keys. %2$d failed.', 'sseo-ai-saas'),
                                $generated['generated'],
                                $generated['failed']
                            );
                        } else {
                            esc_html_e('License key generated successfully!', 'sseo-ai-saas');
                        }
                    ?></p>
                </div>
                
                <div class="sseo-ai-card generated-licenses">
                    <h3><?php esc_html_e('Generated License Keys', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Type', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $licenses = isset($generated['licenses']) ? $generated['licenses'] : [$generated];
                            foreach ($licenses as $license): 
                            ?>
                            <tr>
                                <td><code class="license-key"><?php echo esc_html($license['license_key']); ?></code></td>
                                <td><?php echo esc_html(ucfirst($license['type'] ?? $license['license_type'])); ?></td>
                                <td><?php echo esc_html(ucfirst($license['tier'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button" onclick="sseoAiCopyAllLicenses()">
                        <?php esc_html_e('Copy All to Clipboard', 'sseo-ai-saas'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Generate New License Keys', 'sseo-ai-saas'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('generate_license'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="license_count"><?php esc_html_e('Number of Licenses', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="license_count" id="license_count" value="1" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e('Generate up to 100 licenses at once', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="license_type"><?php esc_html_e('License Type', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="license_type" id="license_type">
                                    <option value="test"><?php esc_html_e('Test (Internal Testing)', 'sseo-ai-saas'); ?></option>
                                    <option value="free"><?php esc_html_e('Free (Complimentary)', 'sseo-ai-saas'); ?></option>
                                    <option value="trial"><?php esc_html_e('Trial (Time-limited)', 'sseo-ai-saas'); ?></option>
                                    <option value="paid" selected><?php esc_html_e('Paid (Standard)', 'sseo-ai-saas'); ?></option>
                                    <option value="lifetime"><?php esc_html_e('Lifetime (Never Expires)', 'sseo-ai-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="license_tier"><?php esc_html_e('License Tier', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="license_tier" id="license_tier">
                                    <option value="trial"><?php esc_html_e('Trial', 'sseo-ai-saas'); ?></option>
                                    <option value="starter" selected><?php echo esc_html(sprintf(__('Starter - €%s/month', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('starter'), 0, ',', '.'))); ?></option>
                                    <?php if (get_option('sseo_ai_saas_early_adopters_enabled', false)): ?>
                                        <option value="early_adopters"><?php echo esc_html(sprintf(__('Early Adopters - €%s/month', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('early_adopters'), 0, ',', '.'))); ?></option>
                                    <?php endif; ?>
                                    <option value="professional"><?php echo esc_html(sprintf(__('Professional - €%s/month', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('professional'), 0, ',', '.'))); ?></option>
                                    <option value="business"><?php echo esc_html(sprintf(__('Business - €%s/month', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('business'), 0, ',', '.'))); ?></option>
                                    <option value="agency"><?php echo esc_html(sprintf(__('Agency - €%s/month', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('agency'), 0, ',', '.'))); ?></option>
                                    <option value="dev"><?php esc_html_e('DEV - All Features (Internal Use Only)', 'sseo-ai-saas'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('DEV tier provides unlimited access to all features for internal development and testing. Do not distribute to clients.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="max_sites"><?php esc_html_e('Max Sites', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="max_sites" id="max_sites" value="1" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e('Number of sites allowed per license', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="rate_limit"><?php esc_html_e('Rate Limit (per hour)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="rate_limit" id="rate_limit" value="60" min="10" max="10000" class="small-text">
                                <p class="description"><?php esc_html_e('Maximum API calls per hour', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="api_calls_limit"><?php esc_html_e('Monthly API Limit', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="api_calls_limit" id="api_calls_limit" value="1000" min="100" max="1000000" class="small-text">
                                <p class="description"><?php esc_html_e('Maximum API calls per month', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="expires_days"><?php esc_html_e('Expires After (days)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="expires_days" id="expires_days" value="" min="1" class="small-text" placeholder="Never">
                                <p class="description"><?php esc_html_e('Leave empty for no expiration (from activation date)', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="assigned_to"><?php esc_html_e('Assign To (Email)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="email" name="assigned_to" id="assigned_to" class="regular-text" placeholder="customer@example.com">
                                <p class="description"><?php esc_html_e('Optional: Pre-assign to a customer email', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="notes"><?php esc_html_e('Notes', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <textarea name="notes" id="notes" rows="3" class="large-text" placeholder="Internal notes about this license..."></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Generate License Keys', 'sseo-ai-saas'), 'primary', 'generate_license'); ?>
                </form>
                
                <script>
                jQuery(document).ready(function($) {
                    var tierDefaults = <?php echo json_encode([
                        'rate_limits' => [
                            'starter' => LicenseKeyGenerator::getDefaultRateLimit('starter'),
                            'early_adopters' => LicenseKeyGenerator::getDefaultRateLimit('early_adopters'),
                            'trial' => LicenseKeyGenerator::getDefaultRateLimit('trial'),
                            'professional' => LicenseKeyGenerator::getDefaultRateLimit('professional'),
                            'business' => LicenseKeyGenerator::getDefaultRateLimit('business'),
                            'agency' => LicenseKeyGenerator::getDefaultRateLimit('agency'),
                            'dev' => LicenseKeyGenerator::getDefaultRateLimit('dev'),
                        ],
                        'api_limits' => [
                            'starter' => LicenseKeyGenerator::getDefaultApiLimit('starter'),
                            'early_adopters' => LicenseKeyGenerator::getDefaultApiLimit('early_adopters'),
                            'trial' => LicenseKeyGenerator::getDefaultApiLimit('trial'),
                            'professional' => LicenseKeyGenerator::getDefaultApiLimit('professional'),
                            'business' => LicenseKeyGenerator::getDefaultApiLimit('business'),
                            'agency' => LicenseKeyGenerator::getDefaultApiLimit('agency'),
                            'dev' => LicenseKeyGenerator::getDefaultApiLimit('dev'),
                        ],
                        'max_sites' => [
                            'starter' => 1, 'early_adopters' => 1, 'trial' => 3,
                            'professional' => 5, 'business' => 15, 'agency' => 50, 'dev' => 100,
                        ],
                    ]); ?>;
                    
                    $('#license_tier').on('change', function() {
                        var tier = $(this).val();
                        if (tierDefaults.rate_limits[tier]) {
                            $('#rate_limit').val(tierDefaults.rate_limits[tier]);
                        }
                        if (tierDefaults.api_limits[tier]) {
                            $('#api_calls_limit').val(tierDefaults.api_limits[tier]);
                        }
                        if (tierDefaults.max_sites[tier]) {
                            $('#max_sites').val(tierDefaults.max_sites[tier]);
                        }
                    }).trigger('change');
                });
                </script>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render all licenses page
     */
    public function renderAllLicenses(): void
    {
        // Handle actions
        if (isset($_GET['action'], $_GET['license']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'license_action')) {
            $licenseKey = sanitize_text_field($_GET['license']);
            
            if ($_GET['action'] === 'revoke') {
                $this->licenseGenerator->revokeLicense($licenseKey, sanitize_text_field($_GET['reason'] ?? 'Revoked by admin'));
                echo '<div class="notice notice-success"><p>' . esc_html__('License revoked successfully.', 'sseo-ai-saas') . '</p></div>';
            }
        }
        
        // Filters
        $filters = [
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'type' => sanitize_text_field($_GET['type'] ?? ''),
            'tier' => sanitize_text_field($_GET['tier'] ?? ''),
            'search' => sanitize_text_field($_GET['search'] ?? ''),
        ];
        
        $page = (int)($_GET['paged'] ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $licenses = $this->licenseGenerator->getLicenses(array_filter($filters), $perPage, $offset);
        $total = $this->licenseGenerator->countLicenses(array_filter($filters));
        $totalPages = ceil($total / $perPage);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('All License Keys', 'sseo-ai-saas'); ?></h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" class="alignleft actions">
                    <input type="hidden" name="page" value="sseo-ai-view-licenses">
                    
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'sseo-ai-saas'); ?></option>
                        <option value="active" <?php selected($filters['status'], 'active'); ?>><?php esc_html_e('Active', 'sseo-ai-saas'); ?></option>
                        <option value="used" <?php selected($filters['status'], 'used'); ?>><?php esc_html_e('Used', 'sseo-ai-saas'); ?></option>
                        <option value="revoked" <?php selected($filters['status'], 'revoked'); ?>><?php esc_html_e('Revoked', 'sseo-ai-saas'); ?></option>
                        <option value="expired" <?php selected($filters['status'], 'expired'); ?>><?php esc_html_e('Expired', 'sseo-ai-saas'); ?></option>
                    </select>
                    
                    <select name="type">
                        <option value=""><?php esc_html_e('All Types', 'sseo-ai-saas'); ?></option>
                        <option value="test" <?php selected($filters['type'], 'test'); ?>><?php esc_html_e('Test', 'sseo-ai-saas'); ?></option>
                        <option value="free" <?php selected($filters['type'], 'free'); ?>><?php esc_html_e('Free', 'sseo-ai-saas'); ?></option>
                        <option value="trial" <?php selected($filters['type'], 'trial'); ?>><?php esc_html_e('Trial', 'sseo-ai-saas'); ?></option>
                        <option value="paid" <?php selected($filters['type'], 'paid'); ?>><?php esc_html_e('Paid', 'sseo-ai-saas'); ?></option>
                        <option value="lifetime" <?php selected($filters['type'], 'lifetime'); ?>><?php esc_html_e('Lifetime', 'sseo-ai-saas'); ?></option>
                    </select>
                    
                    <select name="tier">
                        <option value=""><?php esc_html_e('All Tiers', 'sseo-ai-saas'); ?></option>
                        <option value="trial" <?php selected($filters['tier'], 'trial'); ?>><?php esc_html_e('Trial', 'sseo-ai-saas'); ?></option>
                        <option value="starter" <?php selected($filters['tier'], 'starter'); ?>><?php echo esc_html(sprintf(__('Starter - €%s', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('starter'), 0, ',', '.'))); ?></option>
                        <?php if (get_option('sseo_ai_saas_early_adopters_enabled', false)): ?>
                            <option value="early_adopters" <?php selected($filters['tier'], 'early_adopters'); ?>><?php echo esc_html(sprintf(__('Early Adopters - €%s', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('early_adopters'), 0, ',', '.'))); ?></option>
                        <?php endif; ?>
                        <option value="professional" <?php selected($filters['tier'], 'professional'); ?>><?php echo esc_html(sprintf(__('Professional - €%s', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('professional'), 0, ',', '.'))); ?></option>
                        <option value="business" <?php selected($filters['tier'], 'business'); ?>><?php echo esc_html(sprintf(__('Business - €%s', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('business'), 0, ',', '.'))); ?></option>
                        <option value="agency" <?php selected($filters['tier'], 'agency'); ?>><?php echo esc_html(sprintf(__('Agency - €%s', 'sseo-ai-saas'), number_format(SaaSSettings::tierPrice('agency'), 0, ',', '.'))); ?></option>
                    </select>
                    
                    <input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Search...">
                    
                    <?php submit_button(__('Filter', 'sseo-ai-saas'), '', '', false); ?>
                </form>
                
                <div class="alignright">
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sseo-ai-view-licenses&action=export'), 'export_licenses'); ?>" class="button button-export">
                        <?php esc_html_e('Export CSV', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Licenses Table -->
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Type', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Assigned To', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Expires', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><code class="license-key"><?php echo esc_html($license['license_key']); ?></code></td>
                        <td><?php echo esc_html(ucfirst($license['license_type'])); ?></td>
                        <td><?php echo esc_html(ucfirst($license['tier'])); ?></td>
                        <td><span class="badge badge-<?php echo esc_attr($license['status']); ?>"><?php echo esc_html(ucfirst($license['status'])); ?></span></td>
                        <td><?php echo esc_html($license['assigned_to'] ?: '-'); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license['created_at']))); ?></td>
                        <td><?php echo $license['expires_at'] ? esc_html(date_i18n(get_option('date_format'), strtotime($license['expires_at']))) : '<em>' . esc_html__('Never', 'sseo-ai-saas') . '</em>'; ?></td>
                        <td>
                            <?php if (in_array($license['status'], ['active', 'used'], true)): ?>
                                <a href="<?php echo admin_url('admin.php?page=sseo-ai-license-features&license=' . urlencode($license['license_key'])); ?>" 
                                   class="button button-small" 
                                   style="margin-right:5px;background:#f0f9ff;border-color:#0ea5e9;color:#0369a1;">
                                    <?php esc_html_e('Manage Features', 'sseo-ai-saas'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sseo-ai-view-licenses&action=revoke&license=' . urlencode($license['license_key'])), 'license_action'); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to revoke this license? This will also suspend the associated tenant.', 'sseo-ai-saas'); ?>')">
                                    <?php esc_html_e('Revoke', 'sseo-ai-saas'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php printf(esc_html__('%s items', 'sseo-ai-saas'), number_format($total)); ?>
                    </span>
                    <span class="pagination-links">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $totalPages,
                            'current' => $page,
                        ]);
                        ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render tenants page
     */
    public function renderTenantsPage(): void
    {
        $tenants = $this->tenants->getTenants([], 50, 0);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Tenant Management', 'sseo-ai-saas'); ?></h1>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Active Tenants', 'sseo-ai-saas'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tenant Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                        <tr>
                            <td><code><?php echo esc_html(substr($tenant['tenant_key'], 0, 20) . '...'); ?></code></td>
                            <td><?php echo esc_html($tenant['name']); ?></td>
                            <td><?php echo esc_html($tenant['domain'] ?: '-'); ?></td>
                            <td><?php echo esc_html(ucfirst($tenant['tier'])); ?></td>
                            <td><span class="badge badge-<?php echo esc_attr($tenant['status']); ?>"><?php echo esc_html(ucfirst($tenant['status'])); ?></span></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($tenant['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                            <td><?php echo $tenant['last_active'] ? esc_html(human_time_diff(strtotime($tenant['last_active']), current_time('timestamp')) . ' ago') : '<em>' . esc_html__('Never', 'sseo-ai-saas') . '</em>'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render usage reports page
     */
    public function renderUsageReports(): void
    {
        $tenants = $this->tenants->getTenants(['status' => 'active'], 100, 0);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Usage Reports', 'sseo-ai-saas'); ?></h1>
            
            <div class="sseo-ai-grid-3">
                <?php foreach ($tenants as $tenant): 
                    $usage = $this->tenants->getTenantUsage($tenant['tenant_key']);
                    $limits = $this->tenants->checkTenantLimits($tenant['tenant_key']);
                    $onboardingCompleted = (bool) $this->tenants->getTenantSetting($tenant['tenant_key'], 'onboarding_completed', false);
                    $onboardingCompletedAt = $this->tenants->getTenantSetting($tenant['tenant_key'], 'onboarding_completed_at', '');
                ?>
                <div class="sseo-ai-card tenant-usage-card">
                    <h3><?php echo esc_html($tenant['name']); ?></h3>
                    <p class="tenant-domain"><?php echo esc_html($tenant['domain'] ?: 'No domain'); ?></p>
                    <p class="tenant-onboarding" style="font-size:12px;color:#666;">
                        <?php if ($onboardingCompleted): ?>
                            <span style="color:#00a32a;">&#10003; <?php esc_html_e('Wizard completed', 'sseo-ai-saas'); ?></span>
                            <?php if ($onboardingCompletedAt): ?>
                                <em><?php echo esc_html(human_time_diff(strtotime($onboardingCompletedAt), current_time('timestamp')) . ' ago'); ?></em>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:#f59e0b;">&#8226; <?php esc_html_e('Wizard not completed', 'sseo-ai-saas'); ?></span>
                        <?php endif; ?>
                    </p>
                    
                    <div class="usage-stats">
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></span>
                            <span class="usage-value <?php echo $limits['checks']['api_calls']['exceeded'] ? 'exceeded' : ''; ?>">
                                <?php echo number_format($usage['api_calls'] ?? 0); ?> / <?php echo number_format($limits['checks']['api_calls']['limit']); ?>
                            </span>
                        </div>
                        
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('Est. Cost', 'sseo-ai-saas'); ?></span>
                            <span class="usage-value">
                                $<?php echo number_format($usage['api_cost'] ?? 0, 2); ?>
                            </span>
                        </div>
                        
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('Content Generated', 'sseo-ai-saas'); ?></span>
                            <span class="usage-value">
                                <?php echo number_format($usage['content_generated'] ?? 0); ?>
                            </span>
                        </div>
                    </div>
                    
                    <p class="tenant-tier"><?php echo esc_html(ucfirst($tenant['tier'])); ?> Tier</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle CSV export
     */
    public function handleExport(): void
    {
        if (!isset($_GET['action']) || $_GET['action'] !== 'export') {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'export_licenses')) {
            wp_die(__('Security check failed', 'sseo-ai-saas'));
        }
        
        $csv = $this->licenseGenerator->exportLicenses();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=licenses-' . date('Y-m-d') . '.csv');
        echo $csv;
        exit;
    }

    /**
     * Render license features management page
     */
    public function renderLicenseFeaturesPage(): void
    {
        $licenseKey = sanitize_text_field($_GET['license'] ?? '');
        if (empty($licenseKey)) {
            wp_die(__('License key required', 'sseo-ai-saas'));
        }

        $license = $this->licenseGenerator->getLicense($licenseKey);
        if (!$license) {
            wp_die(__('License not found', 'sseo-ai-saas'));
        }

        $noticeMessage = '';
        $noticeType = 'info';

        // Handle license e-mail assignment updates on this page.
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && !empty($_POST['sseo_ai_update_license_email'])
            && check_admin_referer('sseo_ai_update_license_email')
        ) {
            $newEmail = sanitize_email($_POST['assigned_to'] ?? '');
            if (!empty($newEmail) && is_email($newEmail)) {
                $update = $this->licenseGenerator->updateLicense($licenseKey, ['assigned_to' => $newEmail]);
                if (is_wp_error($update)) {
                    $noticeMessage = $update->get_error_message();
                    $noticeType = 'error';
                } else {
                    $noticeMessage = __('License email updated successfully.', 'sseo-ai-saas');
                    $noticeType = 'success';
                    $license = $this->licenseGenerator->getLicense($licenseKey);
                }
            } else {
                $noticeMessage = __('Please enter a valid email address.', 'sseo-ai-saas');
                $noticeType = 'error';
            }
        }

        $featureManager = new LicenseFeatureManager($this->tenants, $this->licenseGenerator);
        $featureData = $featureManager->getFeatureToggleData($licenseKey);
        
        // Get tier features for reference
        $tierFeatures = $featureManager->getFeaturesForTier($license['tier'] ?? 'starter');
        
        ?>
        <div class="wrap sseo-ai-admin">
            <h1><?php echo esc_html(sprintf(__('Manage Features for License: %s', 'sseo-ai-saas'), substr($licenseKey, 0, 20) . '...')); ?></h1>

            <?php if ($noticeMessage): ?>
                <div class="sseo-ai-notice <?php echo esc_attr($noticeType); ?>">
                    <p><?php echo esc_html($noticeMessage); ?></p>
                </div>
            <?php endif; ?>

            <div class="sseo-ai-license-assign">
                <h2><?php esc_html_e('License Assignment', 'sseo-ai-saas'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Assign or update the customer email address for this license.', 'sseo-ai-saas'); ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field('sseo_ai_update_license_email'); ?>
                    <input type="hidden" name="sseo_ai_update_license_email" value="1">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="assigned_to"><?php esc_html_e('Assigned To (Email)', 'sseo-ai-saas'); ?></label>
                            </th>
                            <td>
                                <input type="email" name="assigned_to" id="assigned_to"
                                       value="<?php echo esc_attr($license['assigned_to'] ?? ''); ?>"
                                       class="regular-text" placeholder="customer@example.com">
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Update Assignment', 'sseo-ai-saas'), 'primary', 'sseo_ai_update_license_email_submit'); ?>
                </form>
            </div>

            <div class="sseo-ai-notice info">
                <p><strong><?php esc_html_e('Tier:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html(ucfirst($license['tier'] ?? 'starter')); ?></p>
                <p><?php esc_html_e('Features with default tier access are pre-enabled. You can override these to enable/disable individual features.', 'sseo-ai-saas'); ?></p>
            </div>

            <div id="feature-toggle-container">
                <!-- Loaded via JavaScript -->
                <p><?php esc_html_e('Loading features...', 'sseo-ai-saas'); ?></p>
            </div>

            <script>
            jQuery(document).ready(function($) {
                var licenseKey = <?php echo json_encode($licenseKey); ?>;
                
                // Load feature data
                wp.apiFetch({
                    path: 'ai-seo-saas/v1/license/features?license_key=' + encodeURIComponent(licenseKey),
                    method: 'GET'
                }).then(function(response) {
                    if (response.success && response.data) {
                        renderFeatureToggleUI(response.data);
                    } else {
                        $('#feature-toggle-container').html('<p><?php esc_html_e('Failed to load features', 'sseo-ai-saas'); ?></p>');
                    }
                }).catch(function(error) {
                    $('#feature-toggle-container').html('<p><?php esc_html_e('Error loading features: ', 'sseo-ai-saas'); ?>' + (error.message || 'Unknown error') + '</p>');
                });
                
                function renderFeatureToggleUI(data) {
                    var html = '<div class="feature-categories">';
                    
                    Object.keys(data.categories).forEach(function(category) {
                        html += '<div class="feature-category">' +
                            '<h3 class="category-title">' + escapeHtml(category) + '</h3>' +
                            '<table class="wp-list-table widefat striped">' +
                            '<thead><tr>' +
                            '<th style="width:40px;">Enable</th>' +
                            '<th>Feature</th>' +
                            '<th style="width:120px;">Default Tier</th>' +
                            '<th style="width:100px;">Status</th>' +
                            '</tr></thead><tbody>';
                        
                        Object.keys(data.categories[category]).forEach(function(featureKey) {
                            var feature = data.categories[category][featureKey];
                            var isChecked = feature.enabled ? 'checked' : '';
                            var statusClass = feature.overridden ? 'overridden' : (feature.in_tier ? 'in-tier' : 'not-in-tier');
                            var statusText = feature.overridden ? '<?php esc_html_e('Overridden', 'sseo-ai-saas'); ?>' : 
                                            (feature.in_tier ? '<?php esc_html_e('From Tier', 'sseo-ai-saas'); ?>' : '<?php esc_html_e('Not Included', 'sseo-ai-saas'); ?>');
                            
                            html += '<tr>' +
                                '<td style="text-align:center;">' +
                                '<input type="checkbox" class="feature-toggle" ' +
                                'data-feature="' + escapeHtml(featureKey) + '" ' + isChecked + '>' +
                                '</td>' +
                                '<td><strong>' + escapeHtml(feature.name) + '</strong></td>' +
                                '<td><code>' + escapeHtml(ucfirst(feature.default_tier)) + '</code></td>' +
                                '<td><span class="status-badge ' + statusClass + '">' + statusText + '</span></td>' +
                                '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                    });
                    
                    html += '</div>' +
                        '<div style="margin-top:20px;padding:15px;background:#f0f9ff;border-left:4px solid #0ea5e9;">' +
                        '<p><strong><?php esc_html_e('Legend:', 'sseo-ai-saas'); ?></strong></p>' +
                        '<ul>' +
                        '<li><span class="status-badge from-tier"><?php esc_html_e('From Tier', 'sseo-ai-saas'); ?></span> - <?php esc_html_e('Enabled by default based on license tier', 'sseo-ai-saas'); ?></li>' +
                        '<li><span class="status-badge overridden"><?php esc_html_e('Overridden', 'sseo-ai-saas'); ?></span> - <?php esc_html_e('Manually enabled/disabled by admin', 'sseo-ai-saas'); ?></li>' +
                        '<li><span class="status-badge not-in-tier"><?php esc_html_e('Not Included', 'sseo-ai-saas'); ?></span> - <?php esc_html_e('Not in this tier, can be manually enabled', 'sseo-ai-saas'); ?></li>' +
                        '</ul></div>' +
                        '<div style="margin-top:20px;">' +
                        '<button type="button" id="save-features" class="button button-primary"><?php esc_html_e('Save Feature Overrides', 'sseo-ai-saas'); ?></button>' +
                        '<span id="save-status" style="margin-left:15px;display:none;"></span>' +
                        '</div>';
                    
                    $('#feature-toggle-container').html(html);
                    
                    // Bind save button
                    $('#save-features').on('click', function() {
                        var features = {};
                        $('.feature-toggle').each(function() {
                            var key = $(this).data('feature');
                            features[key] = $(this).is(':checked');
                        });
                        
                        $('#save-features').prop('disabled', true).text('<?php esc_html_e('Saving...', 'sseo-ai-saas'); ?>');
                        
                        wp.apiFetch({
                            path: 'ai-seo-saas/v1/license/features',
                            method: 'POST',
                            data: {
                                license_key: licenseKey,
                                features: features
                            }
                        }).then(function(response) {
                            if (response.success) {
                                $('#save-status').text('✓ <?php esc_html_e('Saved successfully!', 'sseo-ai-saas'); ?>').css('color', '#16a34a').show();
                                setTimeout(function() { $('#save-status').fadeOut(); }, 3000);
                            } else {
                                $('#save-status').text('❌ ' + (response.message || '<?php esc_html_e('Save failed', 'sseo-ai-saas'); ?>')).css('color', '#dc2626').show();
                            }
                            $('#save-features').prop('disabled', false).text('<?php esc_html_e('Save Feature Overrides', 'sseo-ai-saas'); ?>');
                        }).catch(function(error) {
                            $('#save-status').text('❌ <?php esc_html_e('Error: ', 'sseo-ai-saas'); ?>' + (error.message || '<?php esc_html_e('Unknown error', 'sseo-ai-saas'); ?>')).css('color', '#dc2626').show();
                            $('#save-features').prop('disabled', false).text('<?php esc_html_e('Save Feature Overrides', 'sseo-ai-saas'); ?>');
                        });
                    });
                }
                
                function escapeHtml(text) {
                    var div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                function ucfirst(str) {
                    return str.charAt(0).toUpperCase() + str.slice(1);
                }
            });
            </script>

            <style>
            .wrap.sseo-ai-admin {
                padding: 0 0 20px;
                max-width: 1200px;
            }
            .sseo-ai-admin h1 {
                color: #fff;
                font-family: 'Outfit', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 0 20px 20px;
                padding: 20px 0 10px;
            }
            #feature-toggle-container,
            .sseo-ai-notice,
            .sseo-ai-license-assign {
                margin: 0 20px;
            }
            .sseo-ai-license-assign {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px;
            }
            .sseo-ai-license-assign h2 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            .sseo-ai-admin .feature-category {
                margin-bottom: 30px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 15px;
            }
            .sseo-ai-admin .sseo-ai-notice {
                padding: 12px 15px;
                border-left: 4px solid #2271b1;
                background: #f0f6fc;
            }
            .sseo-ai-admin .sseo-ai-notice.info {
                border-left-color: #2271b1;
                background: #f0f6fc;
            }
            .sseo-ai-admin .sseo-ai-notice.error {
                border-left-color: #d63638;
                background: #fcf0f1;
            }
            .sseo-ai-admin .category-title {
                margin-top: 0;
                margin-bottom: 15px;
                padding-bottom: 10px;
                border-bottom: 2px solid #2271b1;
                color: #1d2327;
                font-size: 16px;
            }
            .sseo-ai-admin .status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
            }
            .sseo-ai-admin .status-badge.in-tier {
                background: #d1fae5;
                color: #065f46;
            }
            .sseo-ai-admin .status-badge.overridden {
                background: #fef3c7;
                color: #92400e;
            }
            .sseo-ai-admin .status-badge.not-in-tier {
                background: #fee2e2;
                color: #991b1b;
            }
            .sseo-ai-notice {
                padding: 12px 15px;
                margin: 20px 0;
                border-left: 4px solid #2271b1;
                background: #f0f6fc;
            }
            .sseo-ai-notice.info {
                border-left-color: #2271b1;
            }
            </style>
        </div>
        <?php
    }

    public function renderAgencyAccountsPage(): void
    {
        $error = null;
        $success = null;

        if (isset($_POST['create_agency_account']) && wp_verify_nonce($_POST['_wpnonce'], 'create_agency_account')) {
            $email = sanitize_email($_POST['agency_email'] ?? '');
            $companyName = sanitize_text_field($_POST['agency_name'] ?? '');
            $maxSubLicenses = (int)($_POST['max_sub_licenses'] ?? 10);
            $tier = sanitize_text_field($_POST['agency_tier'] ?? 'agency');

            if (empty($email) || !is_email($email)) {
                $error = __('Valid email is required.', 'sseo-ai-saas');
            } elseif (empty($companyName)) {
                $error = __('Company name is required.', 'sseo-ai-saas');
            } else {
                    $tenantResult = $this->tenants->createTenant([
                        'name' => $companyName,
                        'email' => $email,
                        'tier' => $tier,
                        'status' => 'active',
                    ]);

                    if (is_wp_error($tenantResult)) {
                        $error = $tenantResult->get_error_message();
                    } else {
                        $roleManager = new AgencyRoleManager($this->tenants);
                        $userResult = $roleManager->createAgencyUser($email, $companyName, (int)$tenantResult['id'], $maxSubLicenses);

                        if (is_wp_error($userResult)) {
                            $error = $userResult->get_error_message();
                        } else {
                            $success = sprintf(
                                __('Agency account created for %s with %d sub-license quota. A welcome email has been sent with login details.', 'sseo-ai-saas'),
                                $email,
                                $maxSubLicenses
                            );
                        }
                    }
                }
        }

        $agencyTenants = $this->tenants->getTenants(['tier' => 'agency'], 100, 0);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Agency Accounts', 'sseo-ai-saas'); ?></h1>

            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="notice notice-success"><p><?php echo esc_html($success); ?></p></div>
            <?php endif; ?>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Create Agency Account', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Create a new agency partner. This creates an agency tenant and a WordPress user with the agency_partner role. The agency gets portal access to manage their own sub-licenses — no license is consumed for the agency itself.', 'sseo-ai-saas'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('create_agency_account'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="agency_name"><?php esc_html_e('Agency Name', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="agency_name" id="agency_name" class="regular-text" required placeholder="Acme SEO Agency"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="agency_email"><?php esc_html_e('Agency Email', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="email" name="agency_email" id="agency_email" class="regular-text" required placeholder="contact@acme.com"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="agency_tier"><?php esc_html_e('Agency Tier', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="agency_tier" id="agency_tier">
                                    <option value="agency" selected><?php esc_html_e('Agency', 'sseo-ai-saas'); ?></option>
                                    <option value="business"><?php esc_html_e('Business', 'sseo-ai-saas'); ?></option>
                                    <option value="professional"><?php esc_html_e('Professional', 'sseo-ai-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_sub_licenses"><?php esc_html_e('Max Sub-Licenses', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="max_sub_licenses" id="max_sub_licenses" value="10" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e('Maximum number of sub-licenses this agency can generate.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Create Agency Account', 'sseo-ai-saas'), 'primary', 'create_agency_account'); ?>
                </form>
            </div>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Existing Agency Tenants', 'sseo-ai-saas'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Agency Name', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Email', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Sub-Licenses', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agencyTenants)): ?>
                            <tr><td colspan="6"><?php esc_html_e('No agency tenants yet.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($agencyTenants as $t):
                                $account = $this->tenants->getAgencyAccountByTenant((int)$t['id']);
                                $licenseCount = $this->licenseGenerator->countLicensesByAgency((int)$t['id']);
                                $maxSubs = $account ? (int)$account['max_sub_licenses'] : '-';
                            ?>
                                <tr>
                                    <td><?php echo esc_html($t['name']); ?></td>
                                    <td><?php echo esc_html($t['email']); ?></td>
                                    <td><code><?php echo esc_html($t['license_key'] ?: '-'); ?></code></td>
                                    <td><span class="badge badge-<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(ucfirst($t['status'])); ?></span></td>
                                    <td><?php echo esc_html($licenseCount . ' / ' . $maxSubs); ?></td>
                                    <td><?php echo esc_html($t['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

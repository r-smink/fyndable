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
        // Main License Management menu
        add_menu_page(
            __('License Management', 'ai-seo-saas'),
            __('Licenses', 'ai-seo-saas'),
            'manage_options',
            'sseo-ai-licenses',
            [$this, 'renderLicenseDashboard'],
            'dashicons-admin-network',
            30
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('License Dashboard', 'ai-seo-saas'),
            __('Dashboard', 'ai-seo-saas'),
            'manage_options',
            'sseo-ai-licenses',
            [$this, 'renderLicenseDashboard']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Generate License Keys', 'ai-seo-saas'),
            __('Generate Keys', 'ai-seo-saas'),
            'manage_options',
            'sseo-ai-generate-licenses',
            [$this, 'renderGeneratePage']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('View All Licenses', 'ai-seo-saas'),
            __('All Licenses', 'ai-seo-saas'),
            'manage_options',
            'sseo-ai-view-licenses',
            [$this, 'renderAllLicenses']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Tenants', 'ai-seo-saas'),
            __('Tenants', 'ai-seo-saas'),
            'manage_options',
            'sseo-ai-tenants',
            [$this, 'renderTenantsPage']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Usage Reports', 'ai-seo-saas'),
            __('Usage Reports', 'ai-seo-saas'),
            'manage_options',
            'sseo-ai-usage-reports',
            [$this, 'renderUsageReports']
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
            <h1><?php esc_html_e('License Management Dashboard', 'ai-seo-saas'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Licenses', 'ai-seo-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['created_today']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Created Today', 'ai-seo-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['created_this_month']); ?></div>
                    <div class="stat-label"><?php esc_html_e('This Month', 'ai-seo-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(count($recentTenants)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Tenants', 'ai-seo-saas'); ?></div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Quick Actions', 'ai-seo-saas'); ?></h2>
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-generate-licenses'); ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Generate License Keys', 'ai-seo-saas'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-view-licenses'); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('View All Licenses', 'ai-seo-saas'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=sseo-ai-tenants'); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('Manage Tenants', 'ai-seo-saas'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Stats by Type -->
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Licenses by Status', 'ai-seo-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Status', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Count', 'ai-seo-saas'); ?></th>
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
                    <h3><?php esc_html_e('Licenses by Type', 'ai-seo-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Type', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Count', 'ai-seo-saas'); ?></th>
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
                <h3><?php esc_html_e('Recent Licenses', 'ai-seo-saas'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('License Key', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Type', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Assigned To', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'ai-seo-saas'); ?></th>
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
            <h1><?php esc_html_e('Generate License Keys', 'ai-seo-saas'); ?></h1>
            
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            
            <?php if ($generated): ?>
                <div class="notice notice-success">
                    <p><?php 
                        if (isset($generated['generated'])) {
                            printf(
                                esc_html__('Successfully generated %1$d license keys. %2$d failed.', 'ai-seo-saas'),
                                $generated['generated'],
                                $generated['failed']
                            );
                        } else {
                            esc_html_e('License key generated successfully!', 'ai-seo-saas');
                        }
                    ?></p>
                </div>
                
                <div class="sseo-ai-card generated-licenses">
                    <h3><?php esc_html_e('Generated License Keys', 'ai-seo-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('License Key', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Type', 'ai-seo-saas'); ?></th>
                                <th><?php esc_html_e('Tier', 'ai-seo-saas'); ?></th>
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
                        <?php esc_html_e('Copy All to Clipboard', 'ai-seo-saas'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Generate New License Keys', 'ai-seo-saas'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('generate_license'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="license_count"><?php esc_html_e('Number of Licenses', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <input type="number" name="license_count" id="license_count" value="1" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e('Generate up to 100 licenses at once', 'ai-seo-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="license_type"><?php esc_html_e('License Type', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <select name="license_type" id="license_type">
                                    <option value="test"><?php esc_html_e('Test (Internal Testing)', 'ai-seo-saas'); ?></option>
                                    <option value="free"><?php esc_html_e('Free (Complimentary)', 'ai-seo-saas'); ?></option>
                                    <option value="trial"><?php esc_html_e('Trial (Time-limited)', 'ai-seo-saas'); ?></option>
                                    <option value="paid" selected><?php esc_html_e('Paid (Standard)', 'ai-seo-saas'); ?></option>
                                    <option value="lifetime"><?php esc_html_e('Lifetime (Never Expires)', 'ai-seo-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="license_tier"><?php esc_html_e('License Tier', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <select name="license_tier" id="license_tier">
                                    <option value="basic" selected><?php esc_html_e('Basic - €99/month', 'ai-seo-saas'); ?></option>
                                    <option value="professional"><?php esc_html_e('Professional - €199/month', 'ai-seo-saas'); ?></option>
                                    <option value="business"><?php esc_html_e('Business - €299/month', 'ai-seo-saas'); ?></option>
                                    <option value="agency"><?php esc_html_e('Agency - €499/month', 'ai-seo-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="max_sites"><?php esc_html_e('Max Sites', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <input type="number" name="max_sites" id="max_sites" value="1" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e('Number of sites allowed per license', 'ai-seo-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="rate_limit"><?php esc_html_e('Rate Limit (per hour)', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <input type="number" name="rate_limit" id="rate_limit" value="60" min="10" max="10000" class="small-text">
                                <p class="description"><?php esc_html_e('Maximum API calls per hour', 'ai-seo-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="api_calls_limit"><?php esc_html_e('Monthly API Limit', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <input type="number" name="api_calls_limit" id="api_calls_limit" value="1000" min="100" max="1000000" class="small-text">
                                <p class="description"><?php esc_html_e('Maximum API calls per month', 'ai-seo-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="expires_days"><?php esc_html_e('Expires After (days)', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <input type="number" name="expires_days" id="expires_days" value="" min="1" class="small-text" placeholder="Never">
                                <p class="description"><?php esc_html_e('Leave empty for no expiration (from activation date)', 'ai-seo-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="assigned_to"><?php esc_html_e('Assign To (Email)', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <input type="email" name="assigned_to" id="assigned_to" class="regular-text" placeholder="customer@example.com">
                                <p class="description"><?php esc_html_e('Optional: Pre-assign to a customer email', 'ai-seo-saas'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="notes"><?php esc_html_e('Notes', 'ai-seo-saas'); ?></label></th>
                            <td>
                                <textarea name="notes" id="notes" rows="3" class="large-text" placeholder="Internal notes about this license..."></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Generate License Keys', 'ai-seo-saas'), 'primary', 'generate_license'); ?>
                </form>
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
                echo '<div class="notice notice-success"><p>' . esc_html__('License revoked successfully.', 'ai-seo-saas') . '</p></div>';
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
            <h1><?php esc_html_e('All License Keys', 'ai-seo-saas'); ?></h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" class="alignleft actions">
                    <input type="hidden" name="page" value="sseo-ai-view-licenses">
                    
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'ai-seo-saas'); ?></option>
                        <option value="active" <?php selected($filters['status'], 'active'); ?>><?php esc_html_e('Active', 'ai-seo-saas'); ?></option>
                        <option value="used" <?php selected($filters['status'], 'used'); ?>><?php esc_html_e('Used', 'ai-seo-saas'); ?></option>
                        <option value="revoked" <?php selected($filters['status'], 'revoked'); ?>><?php esc_html_e('Revoked', 'ai-seo-saas'); ?></option>
                        <option value="expired" <?php selected($filters['status'], 'expired'); ?>><?php esc_html_e('Expired', 'ai-seo-saas'); ?></option>
                    </select>
                    
                    <select name="type">
                        <option value=""><?php esc_html_e('All Types', 'ai-seo-saas'); ?></option>
                        <option value="test" <?php selected($filters['type'], 'test'); ?>><?php esc_html_e('Test', 'ai-seo-saas'); ?></option>
                        <option value="free" <?php selected($filters['type'], 'free'); ?>><?php esc_html_e('Free', 'ai-seo-saas'); ?></option>
                        <option value="trial" <?php selected($filters['type'], 'trial'); ?>><?php esc_html_e('Trial', 'ai-seo-saas'); ?></option>
                        <option value="paid" <?php selected($filters['type'], 'paid'); ?>><?php esc_html_e('Paid', 'ai-seo-saas'); ?></option>
                        <option value="lifetime" <?php selected($filters['type'], 'lifetime'); ?>><?php esc_html_e('Lifetime', 'ai-seo-saas'); ?></option>
                    </select>
                    
                    <select name="tier">
                        <option value=""><?php esc_html_e('All Tiers', 'ai-seo-saas'); ?></option>
                        <option value="basic" <?php selected($filters['tier'], 'basic'); ?>><?php esc_html_e('Basic - €99', 'ai-seo-saas'); ?></option>
                        <option value="professional" <?php selected($filters['tier'], 'professional'); ?>><?php esc_html_e('Professional - €199', 'ai-seo-saas'); ?></option>
                        <option value="business" <?php selected($filters['tier'], 'business'); ?>><?php esc_html_e('Business - €299', 'ai-seo-saas'); ?></option>
                        <option value="agency" <?php selected($filters['tier'], 'agency'); ?>><?php esc_html_e('Agency - €499', 'ai-seo-saas'); ?></option>
                    </select>
                    
                    <input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Search...">
                    
                    <?php submit_button(__('Filter', 'ai-seo-saas'), '', '', false); ?>
                </form>
                
                <div class="alignright">
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sseo-ai-view-licenses&action=export'), 'export_licenses'); ?>" class="button">
                        <?php esc_html_e('Export CSV', 'ai-seo-saas'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Licenses Table -->
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('License Key', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Type', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Tier', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Status', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Assigned To', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Created', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Expires', 'ai-seo-saas'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-seo-saas'); ?></th>
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
                        <td><?php echo $license['expires_at'] ? esc_html(date_i18n(get_option('date_format'), strtotime($license['expires_at']))) : '<em>' . esc_html__('Never', 'ai-seo-saas') . '</em>'; ?></td>
                        <td>
                            <?php if ($license['status'] === 'active'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=sseo-ai-view-licenses&action=revoke&license=' . urlencode($license['license_key'])), 'license_action'); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to revoke this license?', 'ai-seo-saas'); ?>')">
                                    <?php esc_html_e('Revoke', 'ai-seo-saas'); ?>
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
                        <?php printf(esc_html__('%s items', 'ai-seo-saas'), number_format($total)); ?>
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
            <h1><?php esc_html_e('Tenant Management', 'ai-seo-saas'); ?></h1>
            
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Active Tenants', 'ai-seo-saas'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tenant Key', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Name', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'ai-seo-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'ai-seo-saas'); ?></th>
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
                            <td><?php echo $tenant['last_active'] ? esc_html(human_time_diff(strtotime($tenant['last_active']), current_time('timestamp')) . ' ago') : '<em>' . esc_html__('Never', 'ai-seo-saas') . '</em>'; ?></td>
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
            <h1><?php esc_html_e('Usage Reports', 'ai-seo-saas'); ?></h1>
            
            <div class="sseo-ai-grid-3">
                <?php foreach ($tenants as $tenant): 
                    $usage = $this->tenants->getTenantUsage($tenant['tenant_key']);
                    $limits = $this->tenants->checkTenantLimits($tenant['tenant_key']);
                ?>
                <div class="sseo-ai-card tenant-usage-card">
                    <h3><?php echo esc_html($tenant['name']); ?></h3>
                    <p class="tenant-domain"><?php echo esc_html($tenant['domain'] ?: 'No domain'); ?></p>
                    
                    <div class="usage-stats">
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('API Calls', 'ai-seo-saas'); ?></span>
                            <span class="usage-value <?php echo $limits['checks']['api_calls']['exceeded'] ? 'exceeded' : ''; ?>">
                                <?php echo number_format($usage['api_calls'] ?? 0); ?> / <?php echo number_format($limits['checks']['api_calls']['limit']); ?>
                            </span>
                        </div>
                        
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('Est. Cost', 'ai-seo-saas'); ?></span>
                            <span class="usage-value">
                                $<?php echo number_format($usage['api_cost'] ?? 0, 2); ?>
                            </span>
                        </div>
                        
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('Content Generated', 'ai-seo-saas'); ?></span>
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
            wp_die(__('Security check failed', 'ai-seo-saas'));
        }
        
        $csv = $this->licenseGenerator->exportLicenses();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=licenses-' . date('Y-m-d') . '.csv');
        echo $csv;
        exit;
    }
}

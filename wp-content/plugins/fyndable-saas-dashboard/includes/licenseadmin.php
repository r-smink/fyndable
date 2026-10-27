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

        // Custom plan admin actions
        add_action('admin_post_sseo_ai_create_custom_plan', [$this, 'handleCreateCustomPlan']);
        add_action('admin_post_sseo_ai_custom_plan_payment_link', [$this, 'handleCustomPlanPaymentLink']);
        add_action('admin_post_sseo_ai_custom_plan_update', [$this, 'handleCustomPlanUpdate']);
        add_action('admin_post_sseo_ai_custom_plan_sync', [$this, 'handleCustomPlanSync']);
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

        // The license features page calls the REST API via wp.apiFetch().
        if (strpos($hook, 'sseo-ai-license-features') !== false) {
            wp_enqueue_script('wp-api-fetch');
        }

        // Apply global white-label colors to the SaaS admin pages. Agency pages
        // are skipped because AgencyPortal::enqueueAssets() injects an
        // agency-specific override there.
        if (strpos($hook, 'sseo-ai-agency') !== false) {
            return;
        }

        $wlEnabled = (bool) get_option('sseo_ai_saas_wl_enabled', false);
        if (!$wlEnabled) {
            return;
        }

        $primary = sanitize_hex_color(get_option('sseo_ai_saas_wl_primary_color', '#379fd3')) ?: '#379fd3';
        $secondary = sanitize_hex_color(get_option('sseo_ai_saas_wl_secondary_color', '#8f39ac')) ?: '#8f39ac';

        $gradient = sprintf('linear-gradient(135deg, %s 0%%, %s 100%%)', $primary, $secondary);

        // White-label colours drive the brand accents only; the page surface
        // and headings stay on the shell tokens (see license-admin.css 1b).
        $css = ':root {
            --sseo-primary: ' . $primary . ';
            --sseo-blue: ' . $primary . ';
        }
        body.fyndable-ui {
            --fyn-purple: ' . $secondary . ';
            --fyn-blue: ' . $primary . ';
            --fyn-gradient: ' . $gradient . ';
            --fyn-gradient-flat: linear-gradient(90deg, ' . $secondary . ', ' . $primary . ');
        }
        .sseo-ai-upgrade-cta {
            background: ' . $gradient . ' !important;
        }';

        wp_add_inline_style('sseo-ai-license-admin', $css);
    }
    
    /**
     * Render license dashboard
     */
    private function licenseStatusLabel(string $status): string
    {
        $labels = [
            'active'  => __('Active', 'sseo-ai-saas'),
            'used'    => __('Connected', 'sseo-ai-saas'),
            'revoked' => __('Revoked', 'sseo-ai-saas'),
            'expired' => __('Expired', 'sseo-ai-saas'),
        ];
        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Map a licence tier to its Fyndable tier-badge modifier.
     */
    private function tierBadgeClass(string $tier): string
    {
        $known = ['trial', 'starter', 'professional', 'business', 'agency', 'free', 'lifetime'];
        $tier = strtolower($tier);

        return in_array($tier, $known, true) ? 'fyn-tier--' . $tier : 'fyn-tier--free';
    }

    /**
     * Map a licence status to its semantic badge modifier.
     */
    private function statusBadgeClass(string $status): string
    {
        switch (strtolower($status)) {
            case 'active':
                return 'fyn-badge--info';
            case 'connected':
                return 'fyn-badge--success';
            case 'revoked':
                return 'fyn-badge--danger';
            case 'expired':
                return 'fyn-badge--muted';
            default:
                return 'fyn-badge--muted';
        }
    }

    /**
     * Map a licence status to the dot colour used in the breakdown cards.
     */
    private function statusDotClass(string $status): string
    {
        switch (strtolower($status)) {
            case 'active':
                return 'fyn-dot--info';
            case 'connected':
                return 'fyn-dot--success';
            case 'revoked':
                return 'fyn-dot--danger';
            default:
                return 'fyn-dot--muted';
        }
    }

    /**
     * Map a tier to the dot colour used in the breakdown cards.
     */
    private function tierDotClass(string $tier): string
    {
        switch (strtolower($tier)) {
            case 'trial':
            case 'starter':
                return 'fyn-dot--info';
            case 'professional':
                return 'fyn-dot--accent';
            case 'business':
                return '';
            case 'agency':
                return 'fyn-dot--success';
            case 'lifetime':
                return 'fyn-dot--warning';
            default:
                return 'fyn-dot--muted';
        }
    }

    /**
     * Map a platform to the dot colour used in the breakdown cards.
     */
    private function platformDotClass(string $platform): string
    {
        switch (strtolower($platform)) {
            case 'wordpress':
                return '';
            case 'shopify':
                return 'fyn-dot--success';
            case 'webflow':
                return 'fyn-dot--info';
            default:
                return 'fyn-dot--muted';
        }
    }

    public function renderLicenseDashboard(): void
    {
        $stats = $this->licenseGenerator->getLicenseStats();
        $recentLicenses = $this->licenseGenerator->getLicenses(['status' => 'active'], 10, 0);
        $recentTenants = $this->tenants->getTenants([], 10, 0);

        // The three breakdown cards: per status, per tier, per platform.
        $breakdowns = [
            [
                'title' => __('By status', 'sseo-ai-saas'),
                'icon' => 'dashicons-chart-pie',
                'rows' => array_map(function (array $row): array {
                    return [
                        'label' => $this->licenseStatusLabel($row['status']),
                        'count' => (int)$row['count'],
                        'dot' => $this->statusDotClass((string)$row['status']),
                    ];
                }, $stats['by_status']),
            ],
            [
                'title' => __('By tier', 'sseo-ai-saas'),
                'icon' => 'dashicons-star-filled',
                'rows' => array_map(function (array $row): array {
                    return [
                        'label' => ucfirst((string)$row['tier']),
                        'count' => (int)$row['count'],
                        'dot' => $this->tierDotClass((string)$row['tier']),
                    ];
                }, $stats['by_tier']),
            ],
            [
                'title' => __('By platform', 'sseo-ai-saas'),
                'icon' => 'dashicons-admin-site',
                'rows' => array_map(function (array $row): array {
                    return [
                        'label' => ucfirst((string)$row['platform']),
                        'count' => (int)$row['count'],
                        'dot' => $this->platformDotClass((string)$row['platform']),
                    ];
                }, $stats['by_platform']),
            ],
        ];
        ?>
        <div class="wrap sseo-ai-license-admin">
            <div class="fyn-main">
                <div class="fyn-main-inner fyn-animate-in">

                    <div class="fyn-page-header">
                        <div class="fyn-page-header-left">
                            <div class="fyn-breadcrumb">
                                <?php esc_html_e('Provider', 'sseo-ai-saas'); ?>
                                <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span>
                                <span class="fyn-crumb"><?php esc_html_e('Dashboard', 'sseo-ai-saas'); ?></span>
                            </div>
                            <h1><?php esc_html_e('License Dashboard', 'sseo-ai-saas'); ?></h1>
                            <p class="fyn-page-desc"><?php esc_html_e('All issued licenses, their status and the latest activations at a glance.', 'sseo-ai-saas'); ?></p>
                        </div>
                        <div class="fyn-page-header-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-licenses')); ?>" class="fyn-btn fyn-btn-outline">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Refresh', 'sseo-ai-saas'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-generate-licenses')); ?>" class="fyn-btn fyn-btn-primary">
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php esc_html_e('Generate Keys', 'sseo-ai-saas'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="fyn-stats-grid">
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-tile-top">
                                <span class="fyn-stat-tile-label"><?php esc_html_e('Total Licenses', 'sseo-ai-saas'); ?></span>
                                <span class="dashicons dashicons-admin-network"></span>
                            </div>
                            <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n($stats['total'])); ?></div>
                            <div class="fyn-stat-tile-footer"><?php esc_html_e('Across all tiers', 'sseo-ai-saas'); ?></div>
                        </div>
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-tile-top">
                                <span class="fyn-stat-tile-label"><?php esc_html_e('Created Today', 'sseo-ai-saas'); ?></span>
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </div>
                            <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n($stats['created_today'])); ?></div>
                            <div class="fyn-stat-tile-footer"><?php esc_html_e('New keys today', 'sseo-ai-saas'); ?></div>
                        </div>
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-tile-top">
                                <span class="fyn-stat-tile-label"><?php esc_html_e('This Month', 'sseo-ai-saas'); ?></span>
                                <span class="dashicons dashicons-chart-bar"></span>
                            </div>
                            <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n($stats['created_this_month'])); ?></div>
                            <div class="fyn-stat-tile-footer"><?php esc_html_e('New keys this month', 'sseo-ai-saas'); ?></div>
                        </div>
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-tile-top">
                                <span class="fyn-stat-tile-label"><?php esc_html_e('Active Tenants', 'sseo-ai-saas'); ?></span>
                                <span class="dashicons dashicons-admin-multisite"></span>
                            </div>
                            <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n(count($recentTenants))); ?></div>
                            <div class="fyn-stat-tile-footer"><?php esc_html_e('Connected customer sites', 'sseo-ai-saas'); ?></div>
                        </div>
                    </div>

                    <div class="fyn-grid-3" style="margin-bottom:20px">
                        <?php foreach ($breakdowns as $breakdown): ?>
                            <div class="fyn-card">
                                <div class="fyn-card-header">
                                    <span class="dashicons <?php echo esc_attr($breakdown['icon']); ?>"></span>
                                    <h3 class="fyn-card-title"><?php echo esc_html($breakdown['title']); ?></h3>
                                </div>
                                <div class="fyn-breakdown">
                                    <?php if (empty($breakdown['rows'])): ?>
                                        <div class="fyn-help"><?php esc_html_e('No data yet.', 'sseo-ai-saas'); ?></div>
                                    <?php else: ?>
                                        <?php foreach ($breakdown['rows'] as $row): ?>
                                            <div class="fyn-breakdown-row">
                                                <span class="fyn-dot <?php echo esc_attr($row['dot']); ?>"></span>
                                                <span class="fyn-breakdown-label"><?php echo esc_html($row['label']); ?></span>
                                                <span class="fyn-breakdown-count"><?php echo esc_html(number_format_i18n($row['count'])); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="fyn-card fyn-card--flush">
                        <div class="fyn-card-header">
                            <span class="dashicons dashicons-admin-network"></span>
                            <h3 class="fyn-card-title"><?php esc_html_e('Recent Licenses', 'sseo-ai-saas'); ?></h3>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-view-licenses')); ?>" class="fyn-card-link">
                                <?php esc_html_e('All licenses', 'sseo-ai-saas'); ?> &rarr;
                            </a>
                        </div>
                        <div class="fyn-table-scroll">
                            <table class="fyn-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                                        <th><?php esc_html_e('Type', 'sseo-ai-saas'); ?></th>
                                        <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                                        <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                                        <th><?php esc_html_e('Assigned To', 'sseo-ai-saas'); ?></th>
                                        <th class="fyn-td-num"><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentLicenses)): ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="fyn-empty">
                                                    <span class="dashicons dashicons-admin-network"></span>
                                                    <div class="fyn-empty-title"><?php esc_html_e('No licenses yet', 'sseo-ai-saas'); ?></div>
                                                    <div><?php esc_html_e('Generate your first license key to get started.', 'sseo-ai-saas'); ?></div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentLicenses as $license): ?>
                                            <tr>
                                                <td><code class="fyn-mono"><?php echo esc_html(substr($license['license_key'], 0, 20) . '...'); ?></code></td>
                                                <td><?php echo esc_html(ucfirst($license['license_type'])); ?></td>
                                                <td><span class="fyn-tier <?php echo esc_attr($this->tierBadgeClass((string)$license['tier'])); ?>"><?php echo esc_html(ucfirst($license['tier'])); ?></span></td>
                                                <td><span class="fyn-badge <?php echo esc_attr($this->statusBadgeClass((string)$license['status'])); ?>"><?php echo esc_html($this->licenseStatusLabel($license['status'])); ?></span></td>
                                                <td><?php echo esc_html($license['assigned_to'] ?: '-'); ?></td>
                                                <td class="fyn-td-num" style="color:var(--text-m)">
                                                    <?php
                                                    printf(
                                                        /* translators: %s: human-readable time difference, e.g. "2 hours". */
                                                        esc_html__('%s ago', 'sseo-ai-saas'),
                                                        esc_html(human_time_diff(strtotime($license['created_at']), current_time('timestamp')))
                                                    );
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
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
        <div class="fyn-main"><div class="fyn-main-inner fyn-animate-in">

            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">
                        <?php esc_html_e('Provider', 'sseo-ai-saas'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span>
                        <span class="fyn-crumb"><?php esc_html_e('Generate Keys', 'sseo-ai-saas'); ?></span>
                    </div>
                    <h1><?php esc_html_e('Generate License Keys', 'sseo-ai-saas'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Issue one key or a batch. Changing the tier fills in that tier\'s default limits.', 'sseo-ai-saas'); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-view-licenses')); ?>" class="fyn-btn fyn-btn-outline">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('All Licenses', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>

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
            
            <div class="fyn-grid-main-side">
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

            <?php
            // Side reference card: tier name, spec and price. Values come from
            // the same helpers the form's tier defaults use.
            $tierRef = [
                'starter' => __('1 site · Core SEO', 'sseo-ai-saas'),
                'professional' => __('5 sites · AI content + rank tracking', 'sseo-ai-saas'),
                'business' => __('15 sites · Local SERP + 5x5 grid', 'sseo-ai-saas'),
                'agency' => __('50 sites · White-label + sub-licenses', 'sseo-ai-saas'),
            ];
            ?>
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-star-filled"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Tiers', 'sseo-ai-saas'); ?></h3>
                </div>
                <?php foreach ($tierRef as $tierKey => $tierSpec): ?>
                    <div style="padding:12px 0;border-bottom:1px solid var(--divide)">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:5px">
                            <span class="fyn-tier <?php echo esc_attr($this->tierBadgeClass($tierKey)); ?>"><?php echo esc_html(ucfirst($tierKey)); ?></span>
                            <span style="font-size:13.5px;font-weight:700;color:var(--text-h)">
                                &euro;<?php echo esc_html(number_format(SaaSSettings::tierPrice($tierKey), 0, ',', '.')); ?>
                                <span style="font-size:11.5px;font-weight:600;color:var(--text-m)">/<?php esc_html_e('mo', 'sseo-ai-saas'); ?></span>
                            </span>
                        </div>
                        <div class="fyn-help"><?php echo esc_html($tierSpec); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="fyn-help" style="margin-top:14px">
                    <?php esc_html_e('DEV is internal only and is never sold.', 'sseo-ai-saas'); ?>
                </div>
            </div>
            </div>

        </div></div>
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
            'platform' => sanitize_text_field($_GET['platform'] ?? ''),
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
        <div class="fyn-main"><div class="fyn-main-inner fyn-animate-in">

            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">
                        <?php esc_html_e('Provider', 'sseo-ai-saas'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span>
                        <span class="fyn-crumb"><?php esc_html_e('All Licenses', 'sseo-ai-saas'); ?></span>
                    </div>
                    <h1><?php esc_html_e('All License Keys', 'sseo-ai-saas'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Search, filter and manage every issued license key.', 'sseo-ai-saas'); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sseo-ai-view-licenses&action=export'), 'export_licenses')); ?>" class="fyn-btn fyn-btn-outline">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-generate-licenses')); ?>" class="fyn-btn fyn-btn-primary">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php esc_html_e('Generate Keys', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" class="fyn-filterbar">
                    <input type="hidden" name="page" value="sseo-ai-view-licenses">

                    <span class="fyn-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Search by key or email...', 'sseo-ai-saas'); ?>">
                    </span>

                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'sseo-ai-saas'); ?></option>
                        <option value="active" <?php selected($filters['status'], 'active'); ?>><?php esc_html_e('Active', 'sseo-ai-saas'); ?></option>
                        <option value="used" <?php selected($filters['status'], 'used'); ?>><?php esc_html_e('Connected', 'sseo-ai-saas'); ?></option>
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
                    
                    <select name="platform">
                        <option value=""><?php esc_html_e('All Platforms', 'sseo-ai-saas'); ?></option>
                        <option value="wordpress" <?php selected($filters['platform'], 'wordpress'); ?>><?php esc_html_e('WordPress', 'sseo-ai-saas'); ?></option>
                        <option value="shopify" <?php selected($filters['platform'], 'shopify'); ?>><?php esc_html_e('Shopify', 'sseo-ai-saas'); ?></option>
                        <option value="webflow" <?php selected($filters['platform'], 'webflow'); ?>><?php esc_html_e('Webflow', 'sseo-ai-saas'); ?></option>
                        <option value="unknown" <?php selected($filters['platform'], 'unknown'); ?>><?php esc_html_e('Unknown', 'sseo-ai-saas'); ?></option>
                    </select>
                    
                    <?php submit_button(__('Filter', 'sseo-ai-saas'), '', '', false); ?>
                </form>
            </div>

            <!-- Licenses Table -->
            <div class="fyn-card fyn-card--flush">
            <div class="fyn-table-scroll">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Type', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Platform', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Assigned To', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Expires', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Renews', 'sseo-ai-saas'); ?></th>
                        <th class="fyn-td-actions"><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($licenses)): ?>
                    <tr>
                        <td colspan="10">
                            <div class="fyn-empty">
                                <span class="dashicons dashicons-search"></span>
                                <div class="fyn-empty-title"><?php esc_html_e('No licenses match these filters', 'sseo-ai-saas'); ?></div>
                                <div><?php esc_html_e('Clear a filter or generate a new key.', 'sseo-ai-saas'); ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><code class="license-key"><?php echo esc_html($license['license_key']); ?></code></td>
                        <td><?php echo esc_html(ucfirst($license['license_type'])); ?></td>
                        <td><span class="fyn-tier <?php echo esc_attr($this->tierBadgeClass((string)$license['tier'])); ?>"><?php echo esc_html(ucfirst($license['tier'])); ?></span></td>
                        <td><span class="badge badge-<?php echo esc_attr($license['status']); ?>"><?php echo esc_html($this->licenseStatusLabel($license['status'])); ?></span></td>
                        <td><span class="badge badge-platform-<?php echo esc_attr($license['platform'] ?? 'unknown'); ?>"><?php echo esc_html(ucfirst($license['platform'] ?? 'Unknown')); ?></span></td>
                        <td><?php echo esc_html($license['assigned_to'] ?: '-'); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($license['created_at']))); ?></td>
                        <td><?php echo $license['expires_at'] ? esc_html(date_i18n(get_option('date_format'), strtotime($license['expires_at']))) : '<em>' . esc_html__('Never', 'sseo-ai-saas') . '</em>'; ?></td>
                        <td><?php echo !empty($license['tenant_expires_at']) ? esc_html(date_i18n(get_option('date_format'), strtotime($license['tenant_expires_at']))) : '<em>&mdash;</em>'; ?></td>
                        <td class="fyn-td-actions">
                            <?php if (in_array($license['status'], ['active', 'used'], true)): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-license-features&license=' . urlencode($license['license_key']))); ?>"
                                   class="fyn-btn fyn-btn-outline fyn-btn-sm" style="margin-right:5px">
                                    <?php esc_html_e('Features', 'sseo-ai-saas'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=sseo-ai-view-licenses&action=revoke&license=' . urlencode($license['license_key'])), 'license_action')); ?>"
                                   class="fyn-btn fyn-btn-danger fyn-btn-sm"
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to revoke this license? This will also suspend the associated tenant.', 'sseo-ai-saas'); ?>')">
                                    <?php esc_html_e('Revoke', 'sseo-ai-saas'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="fyn-pager">
                <span class="fyn-pager-info">
                    <?php
                    $from = $offset + 1;
                    $to = min($offset + $perPage, $total);
                    printf(
                        /* translators: 1: first row number, 2: last row number, 3: total rows. */
                        esc_html__('%1$s&ndash;%2$s of %3$s', 'sseo-ai-saas'),
                        esc_html(number_format_i18n($from)),
                        esc_html(number_format_i18n($to)),
                        esc_html(number_format_i18n($total))
                    );
                    ?>
                </span>
                <span class="fyn-pager-nav">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&lsaquo;',
                        'next_text' => '&rsaquo;',
                        'total' => $totalPages,
                        'current' => $page,
                    ]);
                    ?>
                </span>
            </div>
            <?php endif; ?>
            </div>

        </div></div>
        </div>
        <?php
    }
    
    /**
     * Render tenants page
     */
    public function renderTenantsPage(): void
    {
        // Handle tenant deletion
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tenant_action'] ?? '') === 'delete') {
            $deleteKey = sanitize_text_field($_POST['tenant_key'] ?? '');

            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'delete_tenant_' . $deleteKey)) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security check failed.', 'sseo-ai-saas') . '</p></div>';
            } else {
                $licenseAction = sanitize_text_field($_POST['license_action'] ?? 'keep');
                if (!in_array($licenseAction, ['keep', 'free', 'revoke', 'delete'], true)) {
                    $licenseAction = 'keep';
                }

                $result = $this->tenants->deleteTenant($deleteKey, $licenseAction);

                if (is_wp_error($result)) {
                    echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
                } elseif ($result) {
                    echo '<div class="notice notice-success"><p>' . esc_html__('Tenant deleted successfully.', 'sseo-ai-saas') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Failed to delete tenant.', 'sseo-ai-saas') . '</p></div>';
                }
            }
        }

        $filters = array_filter([
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'tier' => sanitize_text_field($_GET['tier'] ?? ''),
            'platform' => sanitize_text_field($_GET['platform'] ?? ''),
            'search' => sanitize_text_field($_GET['search'] ?? ''),
        ]);

        $page = (int)($_GET['paged'] ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $tenants = $this->tenants->getTenants($filters, $perPage, $offset);
        $total = $this->tenants->countTenants($filters);
        $totalPages = ceil($total / $perPage);
        ?>
        <div class="wrap sseo-ai-license-admin">
        <div class="fyn-main"><div class="fyn-main-inner fyn-animate-in">

            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">
                        <?php esc_html_e('Provider', 'sseo-ai-saas'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span>
                        <span class="fyn-crumb"><?php esc_html_e('Tenants', 'sseo-ai-saas'); ?></span>
                    </div>
                    <h1><?php esc_html_e('Tenant Management', 'sseo-ai-saas'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Every connected customer site, its tier, platform and activity.', 'sseo-ai-saas'); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-usage-reports')); ?>" class="fyn-btn fyn-btn-outline">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php esc_html_e('Usage Reports', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" class="fyn-filterbar">
                    <input type="hidden" name="page" value="sseo-ai-tenants">

                    <span class="fyn-search">
                        <span class="dashicons dashicons-search"></span>
                        <input type="text" name="search" value="<?php echo esc_attr($filters['search'] ?? ''); ?>" placeholder="<?php esc_attr_e('Search tenants...', 'sseo-ai-saas'); ?>">
                    </span>

                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'sseo-ai-saas'); ?></option>
                        <option value="active" <?php selected($filters['status'] ?? '', 'active'); ?>><?php esc_html_e('Active', 'sseo-ai-saas'); ?></option>
                        <option value="inactive" <?php selected($filters['status'] ?? '', 'inactive'); ?>><?php esc_html_e('Inactive', 'sseo-ai-saas'); ?></option>
                        <option value="suspended" <?php selected($filters['status'] ?? '', 'suspended'); ?>><?php esc_html_e('Suspended', 'sseo-ai-saas'); ?></option>
                        <option value="cancelled" <?php selected($filters['status'] ?? '', 'cancelled'); ?>><?php esc_html_e('Cancelled', 'sseo-ai-saas'); ?></option>
                    </select>
                    
                    <select name="tier">
                        <option value=""><?php esc_html_e('All Tiers', 'sseo-ai-saas'); ?></option>
                        <option value="free" <?php selected($filters['tier'] ?? '', 'free'); ?>><?php esc_html_e('Free', 'sseo-ai-saas'); ?></option>
                        <option value="trial" <?php selected($filters['tier'] ?? '', 'trial'); ?>><?php esc_html_e('Trial', 'sseo-ai-saas'); ?></option>
                        <option value="starter" <?php selected($filters['tier'] ?? '', 'starter'); ?>><?php esc_html_e('Starter', 'sseo-ai-saas'); ?></option>
                        <option value="professional" <?php selected($filters['tier'] ?? '', 'professional'); ?>><?php esc_html_e('Professional', 'sseo-ai-saas'); ?></option>
                        <option value="business" <?php selected($filters['tier'] ?? '', 'business'); ?>><?php esc_html_e('Business', 'sseo-ai-saas'); ?></option>
                        <option value="agency" <?php selected($filters['tier'] ?? '', 'agency'); ?>><?php esc_html_e('Agency', 'sseo-ai-saas'); ?></option>
                    </select>
                    
                    <select name="platform">
                        <option value=""><?php esc_html_e('All Platforms', 'sseo-ai-saas'); ?></option>
                        <option value="wordpress" <?php selected($filters['platform'] ?? '', 'wordpress'); ?>><?php esc_html_e('WordPress', 'sseo-ai-saas'); ?></option>
                        <option value="shopify" <?php selected($filters['platform'] ?? '', 'shopify'); ?>><?php esc_html_e('Shopify', 'sseo-ai-saas'); ?></option>
                        <option value="webflow" <?php selected($filters['platform'] ?? '', 'webflow'); ?>><?php esc_html_e('Webflow', 'sseo-ai-saas'); ?></option>
                    </select>
                    
                    <?php submit_button(__('Filter', 'sseo-ai-saas'), '', '', false); ?>
                </form>
            </div>

            <div class="fyn-card fyn-card--flush">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-admin-multisite"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Tenants', 'sseo-ai-saas'); ?></h3>
                    <span class="fyn-card-link" style="color:var(--text-m)"><?php echo esc_html(number_format_i18n($total)); ?></span>
                </div>
                <div class="fyn-table-scroll">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tenant Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Platform', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'sseo-ai-saas'); ?></th>
                            <th class="fyn-td-actions"><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenants)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="fyn-empty">
                                    <span class="dashicons dashicons-admin-multisite"></span>
                                    <div class="fyn-empty-title"><?php esc_html_e('No tenants found', 'sseo-ai-saas'); ?></div>
                                    <div><?php esc_html_e('Tenants appear here once a license is activated on a site.', 'sseo-ai-saas'); ?></div>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($tenants as $tenant): ?>
                        <tr>
                            <td><code><?php echo esc_html(substr($tenant['tenant_key'], 0, 20) . '...'); ?></code></td>
                            <td style="font-weight:600;color:var(--text-h)"><?php echo esc_html($tenant['name']); ?></td>
                            <td><?php echo esc_html($tenant['domain'] ?: '-'); ?></td>
                            <td><span class="badge badge-platform-<?php echo esc_attr($tenant['platform'] ?? 'unknown'); ?>"><?php echo esc_html(ucfirst($tenant['platform'] ?? 'Unknown')); ?></span></td>
                            <td><span class="fyn-tier <?php echo esc_attr($this->tierBadgeClass((string)$tenant['tier'])); ?>"><?php echo esc_html(ucfirst($tenant['tier'])); ?></span></td>
                            <td><span class="badge badge-<?php echo esc_attr($tenant['status']); ?>"><?php echo esc_html(ucfirst($tenant['status'])); ?></span></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($tenant['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                            <td><?php echo $tenant['last_active'] ? esc_html(human_time_diff(strtotime($tenant['last_active']), current_time('timestamp')) . ' ago') : '<em>' . esc_html__('Never', 'sseo-ai-saas') . '</em>'; ?></td>
                            <td class="fyn-td-actions">
                                <form method="post" style="display:flex;gap:6px;align-items:center;justify-content:flex-end"
                                      onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this tenant? This permanently removes its settings, usage data and support tickets. Invoices are kept.', 'sseo-ai-saas'); ?>')">
                                    <?php wp_nonce_field('delete_tenant_' . $tenant['tenant_key']); ?>
                                    <input type="hidden" name="tenant_action" value="delete">
                                    <input type="hidden" name="tenant_key" value="<?php echo esc_attr($tenant['tenant_key']); ?>">
                                    <?php if (!empty($tenant['license_key'])): ?>
                                    <select name="license_action" style="max-width:160px">
                                        <option value="keep"><?php esc_html_e('Keep license', 'sseo-ai-saas'); ?></option>
                                        <option value="free"><?php esc_html_e('Free license (reusable)', 'sseo-ai-saas'); ?></option>
                                        <option value="revoke"><?php esc_html_e('Revoke license', 'sseo-ai-saas'); ?></option>
                                        <option value="delete"><?php esc_html_e('Delete license', 'sseo-ai-saas'); ?></option>
                                    </select>
                                    <?php endif; ?>
                                    <button type="submit" class="fyn-btn fyn-btn-danger fyn-btn-sm">
                                        <?php esc_html_e('Delete', 'sseo-ai-saas'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="fyn-pager">
                    <span class="fyn-pager-info">
                        <?php
                        $from = $offset + 1;
                        $to = min($offset + $perPage, $total);
                        printf(
                            /* translators: 1: first row number, 2: last row number, 3: total rows. */
                            esc_html__('%1$s&ndash;%2$s of %3$s', 'sseo-ai-saas'),
                            esc_html(number_format_i18n($from)),
                            esc_html(number_format_i18n($to)),
                            esc_html(number_format_i18n($total))
                        );
                        ?>
                    </span>
                    <span class="fyn-pager-nav">
                        <?php
                        $base = add_query_arg([
                            'page' => 'sseo-ai-tenants',
                            'status' => $filters['status'] ?? '',
                            'tier' => $filters['tier'] ?? '',
                            'platform' => $filters['platform'] ?? '',
                            'search' => $filters['search'] ?? '',
                        ], admin_url('admin.php'));
                        echo paginate_links([
                            'base' => $base . '&paged=%#%',
                            'format' => '',
                            'prev_text' => '&lsaquo;',
                            'next_text' => '&rsaquo;',
                            'total' => $totalPages,
                            'current' => $page,
                        ]);
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

        </div></div>
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
        <div class="fyn-main"><div class="fyn-main-inner fyn-animate-in">

            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">
                        <?php esc_html_e('Provider', 'sseo-ai-saas'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span>
                        <span class="fyn-crumb"><?php esc_html_e('Usage Reports', 'sseo-ai-saas'); ?></span>
                    </div>
                    <h1><?php esc_html_e('Usage &amp; Reports', 'sseo-ai-saas'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('API consumption, estimated cost and onboarding status per active tenant.', 'sseo-ai-saas'); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-usage-reports')); ?>" class="fyn-btn fyn-btn-outline">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-costs')); ?>" class="fyn-btn fyn-btn-primary">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php esc_html_e('Cost Dashboard', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>

            <div class="sseo-ai-grid-3">
                <?php foreach ($tenants as $tenant):
                    $usage = $this->tenants->getTenantUsage($tenant['tenant_key']);
                    $limits = $this->tenants->checkTenantLimits($tenant['tenant_key']);
                    $onboardingCompleted = (bool) $this->tenants->getTenantSetting($tenant['tenant_key'], 'onboarding_completed', false);
                    $onboardingCompletedAt = $this->tenants->getTenantSetting($tenant['tenant_key'], 'onboarding_completed_at', '');
                    $limitsChecks = $limits['checks'] ?? [];
                    $apiCallsCheck = $limitsChecks['api_calls'] ?? ['exceeded' => false, 'limit' => (int) ($tenant['api_calls_limit'] ?? 0)];
                ?>
                <div class="sseo-ai-card tenant-usage-card">
                    <h3><?php echo esc_html($tenant['name']); ?></h3>
                    <p class="tenant-domain"><?php echo esc_html($tenant['domain'] ?: 'No domain'); ?></p>
                    <p class="tenant-platform"><span class="badge badge-platform-<?php echo esc_attr($tenant['platform'] ?? 'unknown'); ?>"><?php echo esc_html(ucfirst($tenant['platform'] ?? 'Unknown')); ?></span></p>
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
                    
                    <?php
                    // Colour-grade the limit bar by percentage used.
                    $apiCalls = (int)($usage['api_calls'] ?? 0);
                    $apiLimit = (int)($apiCallsCheck['limit'] ?? 0);
                    $apiPct = $apiLimit > 0 ? min(100, (int)round($apiCalls / $apiLimit * 100)) : 0;
                    $apiBar = $apiPct >= 90 ? 'fyn-progress-bar--danger' : ($apiPct >= 70 ? 'fyn-progress-bar--warning' : '');
                    ?>
                    <div class="fyn-usage" style="margin:14px 0 16px">
                        <div class="fyn-usage-meta">
                            <span><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></span>
                            <span style="font-weight:700;color:var(--text-h)"><?php echo esc_html(number_format_i18n($apiCalls)); ?> / <?php echo esc_html(number_format_i18n($apiLimit)); ?></span>
                        </div>
                        <div class="fyn-progress">
                            <div class="fyn-progress-bar <?php echo esc_attr($apiBar); ?>" style="width:<?php echo (int)$apiPct; ?>%"></div>
                        </div>
                    </div>

                    <div class="usage-stats">

                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('Est. Cost', 'sseo-ai-saas'); ?></span>
                            <span class="usage-value">
                                &euro;<?php echo number_format($usage['api_cost'] ?? 0, 2); ?>
                            </span>
                        </div>
                        
                        <div class="usage-stat">
                            <span class="usage-label"><?php esc_html_e('Content Generated', 'sseo-ai-saas'); ?></span>
                            <span class="usage-value">
                                <?php echo number_format($usage['content_generated'] ?? 0); ?>
                            </span>
                        </div>
                    </div>
                    
                    <p class="tenant-tier">
                        <span class="fyn-tier <?php echo esc_attr($this->tierBadgeClass((string)$tenant['tier'])); ?>"><?php echo esc_html(ucfirst($tenant['tier'])); ?></span>
                    </p>
                </div>
                <?php endforeach; ?>

                <?php if (empty($tenants)): ?>
                    <div class="fyn-card" style="grid-column:1/-1">
                        <div class="fyn-empty">
                            <span class="dashicons dashicons-chart-area"></span>
                            <div class="fyn-empty-title"><?php esc_html_e('No active tenants', 'sseo-ai-saas'); ?></div>
                            <div><?php esc_html_e('Usage appears here once a customer site is connected.', 'sseo-ai-saas'); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div></div>
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
                    path: '/ai-seo-saas/v1/license/features?license_key=' + encodeURIComponent(licenseKey),
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
                            path: '/ai-seo-saas/v1/license/features',
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
                color: var(--text-h, #1a1430);
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
                background: var(--card, #fff);
                border: 1px solid var(--card-border, #c3c4c7);
                border-radius: 14px;
                padding: 22px 26px;
            }
            .sseo-ai-license-assign h2 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            .sseo-ai-admin .feature-category {
                margin-bottom: 20px;
                background: var(--card, #fff);
                border: 1px solid var(--card-border, #c3c4c7);
                border-radius: 14px;
                padding: 22px 26px;
            }
            .sseo-ai-admin .sseo-ai-notice {
                padding: 12px 16px;
                border-left: 4px solid var(--fyn-info, #2271b1);
                background: var(--well, #f0f6fc);
                border-radius: 11px;
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

        // Summary tiles, derived from the rows already loaded below.
        $agencyCount = count($agencyTenants);
        $agencyActive = 0;
        $quotaTotal = 0;
        $quotaUsed = 0;
        foreach ($agencyTenants as $t) {
            if (($t['status'] ?? '') === 'active') {
                $agencyActive++;
            }
            $acct = $this->tenants->getAgencyAccountByTenant((int)$t['id']);
            if ($acct) {
                $quotaTotal += (int)$acct['max_sub_licenses'];
            }
            $quotaUsed += $this->licenseGenerator->countLicensesByAgency((int)$t['id']);
        }
        ?>
        <div class="wrap sseo-ai-license-admin">
        <div class="fyn-main"><div class="fyn-main-inner fyn-animate-in">

            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">
                        <?php esc_html_e('Provider', 'sseo-ai-saas'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;width:12px;height:12px;vertical-align:middle"></span>
                        <span class="fyn-crumb"><?php esc_html_e('Agency Accounts', 'sseo-ai-saas'); ?></span>
                    </div>
                    <h1><?php esc_html_e('Agency Accounts', 'sseo-ai-saas'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Reseller partners, their sub-license quota and custom pricing plans.', 'sseo-ai-saas'); ?></p>
                </div>
            </div>

            <div class="fyn-stats-grid">
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-tile-top">
                        <span class="fyn-stat-tile-label"><?php esc_html_e('Agencies', 'sseo-ai-saas'); ?></span>
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n($agencyCount)); ?></div>
                    <div class="fyn-stat-tile-footer"><?php esc_html_e('Reseller partners', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-tile-top">
                        <span class="fyn-stat-tile-label"><?php esc_html_e('Active', 'sseo-ai-saas'); ?></span>
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n($agencyActive)); ?></div>
                    <div class="fyn-stat-tile-footer"><?php esc_html_e('Currently active', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-tile-top">
                        <span class="fyn-stat-tile-label"><?php esc_html_e('Sub-licenses Used', 'sseo-ai-saas'); ?></span>
                        <span class="dashicons dashicons-admin-network"></span>
                    </div>
                    <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n($quotaUsed)); ?></div>
                    <div class="fyn-stat-tile-footer">
                        <?php
                        printf(
                            /* translators: %s: total sub-license quota across agencies. */
                            esc_html__('of %s quota', 'sseo-ai-saas'),
                            esc_html(number_format_i18n($quotaTotal))
                        );
                        ?>
                    </div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-tile-top">
                        <span class="fyn-stat-tile-label"><?php esc_html_e('Available', 'sseo-ai-saas'); ?></span>
                        <span class="dashicons dashicons-plus-alt2"></span>
                    </div>
                    <div class="fyn-stat-tile-value"><?php echo esc_html(number_format_i18n(max($quotaTotal - $quotaUsed, 0))); ?></div>
                    <div class="fyn-stat-tile-footer"><?php esc_html_e('Unused sub-licenses', 'sseo-ai-saas'); ?></div>
                </div>
            </div>

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
                            <th><?php esc_html_e('Agency', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Sub-Licenses', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agencyTenants)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="fyn-empty">
                                        <span class="dashicons dashicons-groups"></span>
                                        <div class="fyn-empty-title"><?php esc_html_e('No agency tenants yet', 'sseo-ai-saas'); ?></div>
                                        <div><?php esc_html_e('Create an agency account with the form above.', 'sseo-ai-saas'); ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($agencyTenants as $t):
                                $account = $this->tenants->getAgencyAccountByTenant((int)$t['id']);
                                $licenseCount = $this->licenseGenerator->countLicensesByAgency((int)$t['id']);
                                $maxSubs = $account ? (int)$account['max_sub_licenses'] : '-';
                            ?>
                                <?php
                                $quotaMax = $account ? (int)$account['max_sub_licenses'] : 0;
                                $quotaPct = $quotaMax > 0 ? min(100, (int)round($licenseCount / $quotaMax * 100)) : 0;
                                $quotaBar = $quotaPct >= 90 ? 'fyn-progress-bar--danger' : ($quotaPct >= 70 ? 'fyn-progress-bar--warning' : '');
                                ?>
                                <tr>
                                    <td>
                                        <span class="fyn-cell">
                                            <span class="fyn-icon-chip fyn-icon-chip--sm"><span class="dashicons dashicons-businessperson"></span></span>
                                            <span>
                                                <span class="fyn-cell-title"><?php echo esc_html($t['name']); ?></span><br>
                                                <span class="fyn-cell-sub"><?php echo esc_html($t['email']); ?></span>
                                            </span>
                                        </span>
                                    </td>
                                    <td><code><?php echo esc_html($t['license_key'] ?: '-'); ?></code></td>
                                    <td><span class="badge badge-<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(ucfirst($t['status'])); ?></span></td>
                                    <td>
                                        <div class="fyn-usage">
                                            <div class="fyn-usage-meta">
                                                <span><?php echo esc_html($licenseCount . ' / ' . ($quotaMax > 0 ? $quotaMax : '-')); ?></span>
                                            </div>
                                            <div class="fyn-progress fyn-progress--thin">
                                                <div class="fyn-progress-bar <?php echo esc_attr($quotaBar); ?>" style="width:<?php echo (int)$quotaPct; ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($t['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php $this->renderCustomPlansSection(); ?>

        </div></div>
        </div>
        <?php
    }

    /**
     * Redirect back to the agency accounts page with a notice.
     */
    private function customPlanRedirect(array $args = []): void
    {
        wp_safe_redirect(add_query_arg(
            array_merge(['page' => 'sseo-ai-agency-accounts'], $args),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Create a custom plan: agency-tier tenant + WP user (agency_partner)
     * + per-tenant pricing config. License quota stays 0 until the first
     * payment activates the subscription.
     */
    public function handleCreateCustomPlan(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'sseo-ai-saas'));
        }
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sseo_ai_create_custom_plan')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }

        $email = sanitize_email($_POST['cp_email'] ?? '');
        $name = sanitize_text_field($_POST['cp_name'] ?? '');
        $price = (float) ($_POST['cp_price_per_license'] ?? 0);
        $initial = max(1, (int) ($_POST['cp_initial_licenses'] ?? 1));
        $maxLicenses = max(0, (int) ($_POST['cp_max_licenses'] ?? 0));

        if (empty($email) || !is_email($email) || empty($name)) {
            $this->customPlanRedirect(['custom_error' => __('Valid name and email are required.', 'sseo-ai-saas')]);
        }
        if ($price <= 0) {
            $this->customPlanRedirect(['custom_error' => __('Price per license must be greater than zero.', 'sseo-ai-saas')]);
        }
        if ($maxLicenses > 0 && $initial > $maxLicenses) {
            $this->customPlanRedirect(['custom_error' => __('Initial licenses cannot exceed the maximum.', 'sseo-ai-saas')]);
        }

        // Guard against orphaned tenants: bail early when this email already
        // has an agency account or a tenant row.
        $existingUser = get_user_by('email', $email);
        if ($existingUser && $this->tenants->getAgencyAccount((int) $existingUser->ID)) {
            $this->customPlanRedirect(['custom_error' => __('This email already has an agency/custom-plan account.', 'sseo-ai-saas')]);
        }
        if ($this->tenants->findTenantByEmail($email)) {
            $this->customPlanRedirect(['custom_error' => __('A tenant with this email already exists.', 'sseo-ai-saas')]);
        }

        $discounts = $this->parseDiscountRows();

        $tenantResult = $this->tenants->createTenant([
            'name' => $name,
            'email' => $email,
            'tier' => 'agency',
            'status' => 'active',
        ]);
        if (is_wp_error($tenantResult)) {
            $this->customPlanRedirect(['custom_error' => $tenantResult->get_error_message()]);
        }

        $tenantKey = $tenantResult['tenant_key'];
        $this->tenants->updateTenant($tenantKey, ['payment_status' => 'pending']);

        $customPlan = new CustomPlan($this->tenants);
        $customPlan->savePlan($tenantKey, [
            'price_per_license' => $price,
            'discounts' => $discounts,
            'max_licenses' => $maxLicenses,
            'initial_licenses' => $initial,
            'name' => $name,
        ]);

        $roleManager = new AgencyRoleManager($this->tenants);
        $userResult = $roleManager->createAgencyUser($email, $name, (int) $tenantResult['id'], 0);
        if (is_wp_error($userResult)) {
            $this->customPlanRedirect(['custom_error' => $userResult->get_error_message()]);
        }

        $this->sendCustomPlanInvite($email, $name, (int) $userResult['user_id'], $price, $initial);

        $this->customPlanRedirect(['custom_notice' => sprintf(
            __('Custom plan created for %s (€%s per license). An invite email has been sent — the customer can activate the plan from the portal, or generate a payment link below.', 'sseo-ai-saas'),
            $email,
            number_format($price, 2, ',', '')
        )]);
    }

    /**
     * Generate a Mollie checkout link for a custom plan's first payment
     * and show it so the admin can copy/send it to the customer.
     */
    public function handleCustomPlanPaymentLink(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'sseo-ai-saas'));
        }
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sseo_ai_custom_plan_payment_link')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $tenant = $this->tenants->getTenantById($tenantId);
        if (!$tenant) {
            $this->customPlanRedirect(['custom_error' => __('Tenant not found.', 'sseo-ai-saas')]);
        }

        $sendEmail = !empty($_POST['cp_send_email']);
        $customPlan = new CustomPlan($this->tenants);
        $plan = $customPlan->getPlan($tenant['tenant_key']);
        if ($plan === null) {
            $this->customPlanRedirect(['custom_error' => __('No custom plan configured for this tenant.', 'sseo-ai-saas')]);
        }

        $licenseCount = max(1, $customPlan->paidLicenseCount($tenant) ?: $plan['initial_licenses']);

        $processor = new PaymentProcessor($this->tenants);
        $result = $processor->createCustomPlanCheckout($tenant, $licenseCount);
        if (is_wp_error($result)) {
            $this->customPlanRedirect(['custom_error' => $result->get_error_message()]);
        }

        $checkoutUrl = $result['checkout_url'] ?? '';
        if ($sendEmail && $checkoutUrl !== '') {
            $this->sendCustomPlanPaymentEmail($tenant, $result);
        }

        $this->customPlanRedirect([
            'custom_notice' => $sendEmail
                ? __('Payment link created and emailed to the customer.', 'sseo-ai-saas')
                : __('Payment link created — copy it below and send it to the customer.', 'sseo-ai-saas'),
            'payment_url' => $checkoutUrl,
        ]);
    }

    /**
     * Update an existing custom plan's pricing/discount settings.
     */
    public function handleCustomPlanUpdate(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'sseo-ai-saas'));
        }
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sseo_ai_custom_plan_update')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $tenant = $this->tenants->getTenantById($tenantId);
        if (!$tenant) {
            $this->customPlanRedirect(['custom_error' => __('Tenant not found.', 'sseo-ai-saas')]);
        }

        $price = (float) ($_POST['cp_price_per_license'] ?? 0);
        if ($price <= 0) {
            $this->customPlanRedirect(['custom_error' => __('Price per license must be greater than zero.', 'sseo-ai-saas'), 'edit_custom' => $tenantId]);
        }

        $customPlan = new CustomPlan($this->tenants);
        $customPlan->savePlan($tenant['tenant_key'], [
            'price_per_license' => $price,
            'discounts' => $this->parseDiscountRows(),
            'max_licenses' => max(0, (int) ($_POST['cp_max_licenses'] ?? 0)),
            'initial_licenses' => max(1, (int) ($_POST['cp_initial_licenses'] ?? 1)),
            'name' => sanitize_text_field($_POST['cp_name'] ?? ($tenant['name'] ?? '')),
        ]);

        $this->customPlanRedirect([
            'custom_notice' => __('Custom plan updated. Use "Sync subscription" to apply the new pricing to the live Mollie subscription if needed.', 'sseo-ai-saas'),
            'edit_custom' => $tenantId,
        ]);
    }

    /**
     * Sync the live Mollie subscription amount with the current computed
     * plan total (paid licenses × price × (1 - discount)).
     */
    public function handleCustomPlanSync(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'sseo-ai-saas'));
        }
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sseo_ai_custom_plan_sync')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }

        $tenantId = (int) ($_POST['tenant_id'] ?? 0);
        $tenant = $this->tenants->getTenantById($tenantId);
        if (!$tenant) {
            $this->customPlanRedirect(['custom_error' => __('Tenant not found.', 'sseo-ai-saas')]);
        }

        $customPlan = new CustomPlan($this->tenants);
        $plan = $customPlan->getPlan($tenant['tenant_key']);
        if ($plan === null) {
            $this->customPlanRedirect(['custom_error' => __('No custom plan configured for this tenant.', 'sseo-ai-saas')]);
        }

        $count = max(1, $customPlan->paidLicenseCount($tenant));
        $amount = $customPlan->monthlyAmount($plan, $count);

        $processor = new PaymentProcessor($this->tenants);
        $result = $processor->setMollieSubscriptionAmount($tenant['tenant_key'], $amount);
        if (is_wp_error($result)) {
            $this->customPlanRedirect(['custom_error' => $result->get_error_message(), 'edit_custom' => $tenantId]);
        }

        $this->customPlanRedirect([
            'custom_notice' => sprintf(
                __('Subscription amount synced to €%s/month (%d licenses).', 'sseo-ai-saas'),
                number_format($amount, 2, ',', ''),
                $count
            ),
            'edit_custom' => $tenantId,
        ]);
    }

    /**
     * Parse the discount threshold rows (min[] / pct[] pairs) from POST.
     */
    private function parseDiscountRows(): array
    {
        $mins = array_map('intval', (array) ($_POST['cp_discount_min'] ?? []));
        $pcts = array_map('floatval', (array) ($_POST['cp_discount_pct'] ?? []));

        $discounts = [];
        foreach ($mins as $i => $min) {
            $pct = $pcts[$i] ?? 0;
            if ($min >= 1 && $pct > 0 && $pct < 100) {
                $discounts[] = ['min' => $min, 'pct' => $pct];
            }
        }
        return $discounts;
    }

    /**
     * Invite email for a new custom-plan customer: set-password link +
     * portal URL where they can activate the subscription.
     */
    private function sendCustomPlanInvite(string $email, string $name, int $userId, float $price, int $initialLicenses): void
    {
        $user = get_user_by('ID', $userId);
        if (!$user) {
            return;
        }

        $portalUrl = admin_url('admin.php?page=sseo-ai-agency');
        $setPasswordUrl = wp_login_url($portalUrl);
        $key = get_password_reset_key($user);
        if (!is_wp_error($key)) {
            $setPasswordUrl = home_url(
                '/set-password?key=' . rawurlencode($key)
                . '&login=' . rawurlencode($user->user_login)
                . '&redirect_to=' . rawurlencode($portalUrl)
            );
        }

        $monthly = number_format($price * $initialLicenses, 2, ',', '');
        $subject = __('Your Fyndable custom plan is ready', 'sseo-ai-saas');
        $message = sprintf(
            __("Hi %s,\n\nYour custom Fyndable plan has been created: %d license(s) at €%s each, €%s per month.\n\nSet your password and log in to activate your subscription:\n%s\n\nAfter logging in, click \"Activate subscription\" to complete the first payment. Subsequent licenses are added to your monthly direct debit.\n\n— The Fyndable Team", 'sseo-ai-saas'),
            $name ?: 'there',
            $initialLicenses,
            number_format($price, 2, ',', ''),
            $monthly,
            $setPasswordUrl
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Email the Mollie checkout link for a custom plan's first payment.
     */
    private function sendCustomPlanPaymentEmail(array $tenant, array $checkout): void
    {
        $amount = number_format((float) ($checkout['amount'] ?? 0), 2, ',', '');
        $subject = __('Fyndable custom plan — payment link', 'sseo-ai-saas');
        $message = sprintf(
            __("Hi %s,\n\nUse this link to complete the payment for your custom plan (%d license(s), €%s per month):\n\n%s\n\nAfter payment, future monthly charges are collected automatically via direct debit.\n\n— The Fyndable Team", 'sseo-ai-saas'),
            $tenant['name'] ?? 'there',
            (int) ($checkout['license_count'] ?? 1),
            $amount,
            $checkout['checkout_url'] ?? ''
        );

        wp_mail($tenant['email'] ?? '', $subject, $message);
    }

    /**
     * Render the custom plans section on the agency accounts page:
     * create card, plans table, edit form, consent log.
     */
    private function renderCustomPlansSection(): void
    {
        $customPlan = new CustomPlan($this->tenants);
        $planTenants = $customPlan->getCustomPlanTenants();

        $editId = (int) ($_GET['edit_custom'] ?? 0);
        $consentId = (int) ($_GET['view_consent'] ?? 0);
        $notice = sanitize_text_field($_GET['custom_notice'] ?? '');
        $error = sanitize_text_field($_GET['custom_error'] ?? '');
        $paymentUrl = esc_url_raw($_GET['payment_url'] ?? '');
        ?>
            <?php if ($notice): ?>
                <div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($paymentUrl): ?>
                <div class="notice notice-info"><p>
                    <strong><?php esc_html_e('Payment link:', 'sseo-ai-saas'); ?></strong><br>
                    <code style="word-break: break-all;"><?php echo esc_html($paymentUrl); ?></code>
                </p></div>
            <?php endif; ?>

            <div class="sseo-ai-card" style="margin-top: 25px;">
                <h2><?php esc_html_e('Create Custom Plan', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Create a per-customer plan on agency-tier features with your own price per license. The customer activates it via a Mollie first payment; subsequent licenses are added to the monthly direct debit automatically.', 'sseo-ai-saas'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_create_custom_plan')); ?>">
                    <?php wp_nonce_field('sseo_ai_create_custom_plan'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="cp_name"><?php esc_html_e('Customer Name', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="cp_name" id="cp_name" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cp_email"><?php esc_html_e('Customer Email', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="email" name="cp_email" id="cp_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cp_price_per_license"><?php esc_html_e('Price per License (€/mo)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="cp_price_per_license" id="cp_price_per_license" value="150" min="0.01" step="0.01" class="small-text" required>
                                <p class="description"><?php esc_html_e('Monthly amount charged per license, e.g. 150.00.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cp_initial_licenses"><?php esc_html_e('Initial Licenses', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="cp_initial_licenses" id="cp_initial_licenses" value="1" min="1" max="100" class="small-text">
                                <p class="description"><?php esc_html_e('Licenses covered by the first payment.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cp_max_licenses"><?php esc_html_e('Max Licenses', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="cp_max_licenses" id="cp_max_licenses" value="0" min="0" max="500" class="small-text">
                                <p class="description"><?php esc_html_e('Hard cap on paid licenses. 0 = unlimited.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Volume Discounts', 'sseo-ai-saas'); ?></th>
                            <td>
                                <p>
                                    <?php esc_html_e('From', 'sseo-ai-saas'); ?>
                                    <input type="number" name="cp_discount_min[]" value="5" min="1" class="small-text">
                                    <?php esc_html_e('licenses', 'sseo-ai-saas'); ?> →
                                    <input type="number" name="cp_discount_pct[]" value="5" min="0" max="99" step="0.1" class="small-text">%
                                </p>
                                <p>
                                    <?php esc_html_e('From', 'sseo-ai-saas'); ?>
                                    <input type="number" name="cp_discount_min[]" value="10" min="1" class="small-text">
                                    <?php esc_html_e('licenses', 'sseo-ai-saas'); ?> →
                                    <input type="number" name="cp_discount_pct[]" value="10" min="0" max="99" step="0.1" class="small-text">%
                                </p>
                                <p>
                                    <?php esc_html_e('From', 'sseo-ai-saas'); ?>
                                    <input type="number" name="cp_discount_min[]" value="" min="1" class="small-text">
                                    <?php esc_html_e('licenses', 'sseo-ai-saas'); ?> →
                                    <input type="number" name="cp_discount_pct[]" value="" min="0" max="99" step="0.1" class="small-text">%
                                </p>
                                <p class="description"><?php esc_html_e('Discount applies to the total monthly amount once the license count reaches the threshold. Leave a row empty to skip it.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Create Custom Plan', 'sseo-ai-saas'), 'primary', 'create_custom_plan'); ?>
                </form>
            </div>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Custom Plans', 'sseo-ai-saas'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Customer', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Email', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Price/License', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Discounts', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Paid Licenses', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Monthly', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Payment Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planTenants)): ?>
                            <tr><td colspan="8"><?php esc_html_e('No custom plans yet.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($planTenants as $t):
                                $plan = $t['custom_plan'];
                                $paidCount = $customPlan->paidLicenseCount($t);
                                $monthly = $customPlan->monthlyAmount($plan, $paidCount);
                                $discountLabels = array_map(function ($d) {
                                    return $d['min'] . '+ → ' . rtrim(rtrim(number_format((float)$d['pct'], 1), '0'), '.') . '%';
                                }, $plan['discounts']);
                            ?>
                                <tr>
                                    <td><?php echo esc_html($t['name']); ?></td>
                                    <td><?php echo esc_html($t['email']); ?></td>
                                    <td>&euro;<?php echo esc_html(number_format($plan['price_per_license'], 2, ',', '')); ?></td>
                                    <td><?php echo esc_html($discountLabels ? implode(', ', $discountLabels) : '—'); ?></td>
                                    <td><?php echo esc_html($paidCount . ($plan['max_licenses'] > 0 ? ' / ' . $plan['max_licenses'] : '')); ?></td>
                                    <td>&euro;<?php echo esc_html(number_format($monthly, 2, ',', '')); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($t['payment_status'] ?: 'none'); ?>"><?php echo esc_html(ucfirst($t['payment_status'] ?: 'pending')); ?></span></td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg(['page' => 'sseo-ai-agency-accounts', 'edit_custom' => (int)$t['id']], admin_url('admin.php'))); ?>"><?php esc_html_e('Edit', 'sseo-ai-saas'); ?></a> |
                                        <a href="<?php echo esc_url(add_query_arg(['page' => 'sseo-ai-agency-accounts', 'view_consent' => (int)$t['id']], admin_url('admin.php'))); ?>"><?php esc_html_e('Consent log', 'sseo-ai-saas'); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            if ($editId > 0) {
                $this->renderCustomPlanEditForm($editId, $customPlan);
            }
            if ($consentId > 0) {
                $this->renderCustomPlanConsentLog($consentId, $customPlan);
            }
            ?>
        <?php
    }

    /**
     * Render the inline edit form + payment-link / sync actions for a
     * custom plan tenant.
     */
    private function renderCustomPlanEditForm(int $tenantId, CustomPlan $customPlan): void
    {
        $tenant = $this->tenants->getTenantById($tenantId);
        if (!$tenant) {
            return;
        }
        $plan = $customPlan->getPlan($tenant['tenant_key']);
        if ($plan === null) {
            return;
        }
        $discounts = array_pad($plan['discounts'], 3, ['min' => '', 'pct' => '']);
        ?>
            <div class="sseo-ai-card">
                <h2><?php printf(esc_html__('Edit Custom Plan — %s', 'sseo-ai-saas'), esc_html($tenant['name'])); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_custom_plan_update')); ?>">
                    <?php wp_nonce_field('sseo_ai_custom_plan_update'); ?>
                    <input type="hidden" name="tenant_id" value="<?php echo esc_attr($tenantId); ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="cp_edit_price"><?php esc_html_e('Price per License (€/mo)', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="number" name="cp_price_per_license" id="cp_edit_price" value="<?php echo esc_attr(number_format($plan['price_per_license'], 2, '.', '')); ?>" min="0.01" step="0.01" class="small-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cp_edit_initial"><?php esc_html_e('Initial Licenses', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="number" name="cp_initial_licenses" id="cp_edit_initial" value="<?php echo esc_attr($plan['initial_licenses']); ?>" min="1" max="100" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="cp_edit_max"><?php esc_html_e('Max Licenses', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="number" name="cp_max_licenses" id="cp_edit_max" value="<?php echo esc_attr($plan['max_licenses']); ?>" min="0" max="500" class="small-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Volume Discounts', 'sseo-ai-saas'); ?></th>
                            <td>
                                <?php foreach ($discounts as $d): ?>
                                <p>
                                    <?php esc_html_e('From', 'sseo-ai-saas'); ?>
                                    <input type="number" name="cp_discount_min[]" value="<?php echo esc_attr($d['min']); ?>" min="1" class="small-text">
                                    <?php esc_html_e('licenses', 'sseo-ai-saas'); ?> →
                                    <input type="number" name="cp_discount_pct[]" value="<?php echo esc_attr($d['pct']); ?>" min="0" max="99" step="0.1" class="small-text">%
                                </p>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Custom Plan', 'sseo-ai-saas'), 'primary', 'save_custom_plan'); ?>
                </form>

                <hr>
                <h3><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></h3>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_custom_plan_payment_link')); ?>">
                        <?php wp_nonce_field('sseo_ai_custom_plan_payment_link'); ?>
                        <input type="hidden" name="tenant_id" value="<?php echo esc_attr($tenantId); ?>">
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" name="cp_send_email" value="1">
                            <?php esc_html_e('Also email the link to the customer', 'sseo-ai-saas'); ?>
                        </label>
                        <?php submit_button(__('Generate Payment Link', 'sseo-ai-saas'), 'secondary', 'generate_payment_link', false); ?>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_custom_plan_sync')); ?>">
                        <?php wp_nonce_field('sseo_ai_custom_plan_sync'); ?>
                        <input type="hidden" name="tenant_id" value="<?php echo esc_attr($tenantId); ?>">
                        <?php submit_button(__('Sync Subscription Amount', 'sseo-ai-saas'), 'secondary', 'sync_subscription', false); ?>
                    </form>
                </div>
                <p class="description"><?php esc_html_e('The payment link creates a Mollie first payment for the plan\'s initial licenses — share it with the customer or let them use the "Activate subscription" button in their portal. "Sync" forces the live Mollie subscription amount to match the computed plan total (paid licenses × price − discount).', 'sseo-ai-saas'); ?></p>
            </div>
        <?php
    }

    /**
     * Render the consent log for a custom plan tenant.
     */
    private function renderCustomPlanConsentLog(int $tenantId, CustomPlan $customPlan): void
    {
        $tenant = $this->tenants->getTenantById($tenantId);
        if (!$tenant) {
            return;
        }
        $log = array_reverse($customPlan->getConsentLog($tenant['tenant_key']));
        ?>
            <div class="sseo-ai-card">
                <h2><?php printf(esc_html__('Consent Log — %s', 'sseo-ai-saas'), esc_html($tenant['name'])); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('User', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Licenses', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Monthly', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Effective', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('IP', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($log)): ?>
                            <tr><td colspan="6"><?php esc_html_e('No consent entries yet.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($log as $entry):
                                $user = get_user_by('ID', (int) ($entry['user_id'] ?? 0));
                            ?>
                                <tr>
                                    <td><?php echo esc_html($entry['at'] ?? ''); ?></td>
                                    <td><?php echo esc_html($user ? $user->user_email : '#' . (int) ($entry['user_id'] ?? 0)); ?></td>
                                    <td><?php echo esc_html(($entry['licenses_before'] ?? '?') . ' → ' . ($entry['licenses_after'] ?? '?')); ?></td>
                                    <td>&euro;<?php echo esc_html(number_format((float)($entry['old_amount'] ?? 0), 2, ',', '')) . ' → €' . esc_html(number_format((float)($entry['new_amount'] ?? 0), 2, ',', '')); ?></td>
                                    <td><?php echo esc_html($entry['next_payment_date'] ?? '—'); ?></td>
                                    <td><?php echo esc_html($entry['ip'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php
    }
}

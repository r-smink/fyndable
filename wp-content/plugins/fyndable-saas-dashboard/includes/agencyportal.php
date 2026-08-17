<?php

namespace SSEOAISaaS;

/**
 * Agency Portal
 *
 * Provides agency partners with a scoped dashboard to generate
 * sub-licenses, manage sub-tenants, and view support tickets
 * for their own tenants only. Fully in Fyndable style.
 */
class AgencyPortal
{
    private TenantRepository $tenants;
    private LicenseKeyGenerator $licenseGenerator;
    private SupportTickets $supportTickets;
    private AgencyRoleManager $roleManager;
    private InvoiceManager $invoiceManager;
    private PaymentProcessor $paymentProcessor;
    private string $pluginFile;

    public function __construct(
        string $pluginFile,
        TenantRepository $tenants,
        LicenseKeyGenerator $licenseGenerator,
        SupportTickets $supportTickets,
        AgencyRoleManager $roleManager,
        InvoiceManager $invoiceManager,
        PaymentProcessor $paymentProcessor
    ) {
        $this->pluginFile = $pluginFile;
        $this->tenants = $tenants;
        $this->licenseGenerator = $licenseGenerator;
        $this->supportTickets = $supportTickets;
        $this->roleManager = $roleManager;
        $this->invoiceManager = $invoiceManager;
        $this->paymentProcessor = $paymentProcessor;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_head', [$this, 'injectAgencyHeaderStyle']);
        add_action('admin_post_sseo_ai_agency_save_wl', [$this, 'handleSaveWhiteLabel']);
        add_action('admin_post_sseo_ai_agency_download_wl_client', [$this, 'handleDownloadWhiteLabelClient']);
        add_action('admin_post_sseo_ai_agency_add_licenses', [$this, 'handleAddLicenses']);
        add_action('admin_post_sseo_ai_agency_print_invoice', [$this, 'handlePrintInvoice']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'sseo-ai-agency') === false) {
            return;
        }
        wp_enqueue_style(
            'sseo-ai-license-admin',
            plugins_url('assets/license-admin.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/license-admin.css')
        );

        $wl = $this->getWhiteLabelSettings();
        $primary = sanitize_hex_color($wl['primary_color'] ?? '') ?: '#379fd3';
        $secondary = sanitize_hex_color($wl['secondary_color'] ?? '') ?: '#8f39ac';

        $css = ':root {
            --sseo-primary: ' . $primary . ';
            --sseo-blue: ' . $primary . ';
        }
        body[class*="sseo-ai"] {
            background: linear-gradient(135deg, ' . $primary . ' 0%, ' . $secondary . ' 100%) !important;
        }
        .wrap.sseo-ai-license-admin {
            background: linear-gradient(135deg, ' . $primary . ' 0%, ' . $secondary . ' 100%) !important;
        }
        .wrap.sseo-ai-license-admin h1 {
            background: linear-gradient(135deg, ' . $primary . ' 0%, ' . $secondary . ' 100%) !important;
            color: #fff !important;
            margin: 0 20px !important;
            padding: 24px 30px !important;
            border-radius: 12px !important;
        }
        .fyndable-agency-content > h1 + *,
        .wrap.sseo-ai-license-admin > h1 + * {
            margin-top: 0 !important;
        }
        .sseo-ai-license-admin .button-primary,
        .sseo-ai-license-admin .button-primary:hover,
        .sseo-ai-upgrade-cta,
        .tenant-login-btn,
        .tenant-login-btn:hover {
            background: linear-gradient(135deg, ' . $primary . ' 0%, ' . $secondary . ' 100%) !important;
        }';

        wp_add_inline_style('sseo-ai-license-admin', $css);
    }

    /**
     * Inject the Fyndable gradient topbar header on agency pages.
     */
    /**
     * Inner agency header styling is no longer used; the SaaSDashboardShell topbar
     * handles all branding and navigation for agency users.
     */
    public function injectAgencyHeaderStyle(): void
    {
    }

    /**
     * Inner agency topbar is no longer rendered; the SaaSDashboardShell provides the header.
     */
    private function renderAgencyHeader(): void
    {
    }

    /**
     * Generate a secure auto-login link for a sub-tenant.
     */
    private function getTenantLoginUrl(int $tenantId): string
    {
        $token = wp_generate_password(32, false);
        set_transient(
            'fyndable_autologin_' . $token,
            [
                'tenant_id' => $tenantId,
                'generated_by' => get_current_user_id(),
            ],
            300
        );
        return add_query_arg(
            ['fyndable_autologin' => $token],
            home_url('/wp-login.php')
        );
    }

    public function addMenu(): void
    {
        if (!$this->roleManager->isAgencyUser()) {
            return;
        }

        add_menu_page(
            __('Agency Portal', 'sseo-ai-saas'),
            __('Agency Portal', 'sseo-ai-saas'),
            'agency_view_dashboard',
            'sseo-ai-agency',
            [$this, 'renderDashboard'],
            'dashicons-businessperson',
            3
        );

        add_submenu_page('sseo-ai-agency', __('Dashboard', 'sseo-ai-saas'), __('Dashboard', 'sseo-ai-saas'), 'agency_view_dashboard', 'sseo-ai-agency', [$this, 'renderDashboard']);
        add_submenu_page('sseo-ai-agency', __('Generate Licenses', 'sseo-ai-saas'), __('Generate Licenses', 'sseo-ai-saas'), 'agency_generate_licenses', 'sseo-ai-agency-generate', [$this, 'renderGeneratePage']);
        add_submenu_page('sseo-ai-agency', __('All Licenses', 'sseo-ai-saas'), __('All Licenses', 'sseo-ai-saas'), 'agency_generate_licenses', 'sseo-ai-agency-licenses', [$this, 'renderLicensesPage']);
        add_submenu_page('sseo-ai-agency', __('Tenants', 'sseo-ai-saas'), __('Tenants', 'sseo-ai-saas'), 'agency_view_tenants', 'sseo-ai-agency-tenants', [$this, 'renderTenantsPage']);
        add_submenu_page('sseo-ai-agency', __('Tenant Detail', 'sseo-ai-saas'), __('Tenant Detail', 'sseo-ai-saas'), 'agency_view_tenants', 'sseo-ai-agency-tenant-detail', [$this, 'renderTenantDetailPage']);
        add_submenu_page('sseo-ai-agency', __('Usage & Costs', 'sseo-ai-saas'), __('Usage & Costs', 'sseo-ai-saas'), 'agency_view_tenants', 'sseo-ai-agency-usage', [$this, 'renderUsagePage']);
        add_submenu_page('sseo-ai-agency', __('Support', 'sseo-ai-saas'), __('Support', 'sseo-ai-saas'), 'agency_view_support', 'sseo-ai-agency-support', [$this, 'renderSupportPage']);
        add_submenu_page('sseo-ai-agency', __('White-Label', 'sseo-ai-saas'), __('White-Label', 'sseo-ai-saas'), 'agency_view_dashboard', 'sseo-ai-agency-wl', [$this, 'renderWhiteLabelSettings']);
        add_submenu_page('sseo-ai-agency', __('My Account', 'sseo-ai-saas'), __('My Account', 'sseo-ai-saas'), 'agency_view_dashboard', 'sseo-ai-agency-account', [$this, 'renderAccountPage']);
        add_submenu_page('sseo-ai-agency', __('Invoices', 'sseo-ai-saas'), __('Invoices', 'sseo-ai-saas'), 'agency_view_dashboard', 'sseo-ai-agency-invoices', [$this, 'renderInvoicesPage']);
        add_submenu_page('sseo-ai-agency', __('Extra Licenses', 'sseo-ai-saas'), __('Extra Licenses', 'sseo-ai-saas'), 'agency_view_dashboard', 'sseo-ai-agency-add-licenses', [$this, 'renderAddLicensesPage']);
    }

    private function getAgencyContext(): array
    {
        $account = $this->roleManager->getAgencyAccount();
        if (!$account) {
            return ['error' => 'no_account'];
        }
        $tenant = $this->tenants->getTenantById((int)$account['tenant_id']);
        if (!$tenant) {
            return ['error' => 'no_tenant'];
        }
        return ['account' => $account, 'tenant' => $tenant];
    }

    public function renderDashboard(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found. Please contact the administrator.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $account = $ctx['account'];
        $agencyTenantId = (int)$account['tenant_id'];
        $maxSubLicenses = (int)$account['max_sub_licenses'];

        $licenseCount = $this->licenseGenerator->countLicensesByAgency($agencyTenantId);
        $activeTenants = $this->tenants->countSubTenants($agencyTenantId, ['status' => 'active']);
        $totalTenants = $this->tenants->countSubTenants($agencyTenantId);
        $usage = $this->tenants->getAgencySubTenantsUsage($agencyTenantId);
        $subTenantIds = $this->tenants->getSubTenantIds($agencyTenantId);
        $openTickets = $this->supportTickets->countOpenTicketsForTenants($subTenantIds);
        $recentTenants = $this->tenants->getSubTenants($agencyTenantId, 10, 0);
        $recentLicenses = $this->licenseGenerator->getLicensesByAgency($agencyTenantId, 5, 0);

        $quotaPct = $maxSubLicenses > 0 ? round(($licenseCount / $maxSubLicenses) * 100) : 0;
        $quotaClass = $quotaPct >= 95 ? 'critical' : ($quotaPct >= 80 ? 'warning' : 'ok');

        $tierCounts = [];
        $statusCounts = ['active' => 0, 'suspended' => 0, 'cancelled' => 0];
        foreach ($recentTenants as $t) {
            $tier = $t['tier'] ?? 'starter';
            $tierCounts[$tier] = ($tierCounts[$tier] ?? 0) + 1;
            $status = $t['status'] ?? 'active';
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }

        $recentTickets = $this->supportTickets->getTicketsForTenants($subTenantIds);
        $recentTickets = array_slice($recentTickets, 0, 5);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('Agency Dashboard', 'sseo-ai-saas'); ?></h1>

            <!-- Stats Grid -->
            <div class="sseo-ai-stats-grid" style="margin-top:20px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html($licenseCount . ' / ' . $maxSubLicenses); ?></div>
                    <div class="stat-label"><?php esc_html_e('Sub-Licenses', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($activeTenants)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Sub-Tenants', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($usage['total_api_calls'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Calls This Month', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">&euro;<?php echo esc_html(number_format((float)($usage['total_api_cost'] ?? 0), 2)); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Cost This Month', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($openTickets)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Open Tickets', 'sseo-ai-saas'); ?></div>
                </div>
            </div>

            <!-- License Quota Bar -->
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('License Quota', 'sseo-ai-saas'); ?></h2>
                <div class="sseo-ai-usage-bar">
                    <div class="sseo-ai-usage-bar__fill sseo-ai-usage-bar__fill--<?php echo esc_attr($quotaClass); ?>" style="width: <?php echo esc_attr(min($quotaPct, 100)); ?>%"></div>
                </div>
                <p style="margin-top: 8px;">
                    <?php
                    $remaining = max($maxSubLicenses - $licenseCount, 0);
                    printf(
                        /* translators: 1: used count, 2: max count, 3: remaining count */
                        esc_html__('%1$d / %2$d used — %3$d remaining', 'sseo-ai-saas'),
                        $licenseCount,
                        $maxSubLicenses,
                        $remaining
                    );
                    ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-generate')); ?>" class="button button-primary" style="margin-left: 12px;">
                        <?php esc_html_e('Generate New License', 'sseo-ai-saas'); ?>
                    </a>
                </p>
            </div>

            <!-- Quick Actions -->
            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Quick Actions', 'sseo-ai-saas'); ?></h2>
                <div class="quick-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-generate')); ?>" class="button button-primary button-hero">
                        <?php esc_html_e('Generate License Keys', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-tenants')); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('View Tenants', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-usage')); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('Usage & Costs', 'sseo-ai-saas'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-support')); ?>" class="button button-secondary button-hero">
                        <?php esc_html_e('Support Tickets', 'sseo-ai-saas'); ?>
                    </a>
                </div>
            </div>

            <!-- Two-Column Grid: By Tier & By Status -->
            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Sub-Tenants by Tier', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Count', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tierCounts)): ?>
                                <tr><td colspan="2"><?php esc_html_e('No sub-tenants yet.', 'sseo-ai-saas'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($tierCounts as $tier => $count): ?>
                                    <tr>
                                        <td><?php echo esc_html(ucfirst($tier)); ?></td>
                                        <td><?php echo esc_html(number_format($count)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Sub-Tenants by Status', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Count', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusCounts as $status => $count): ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($status)); ?></td>
                                    <td><?php echo esc_html(number_format($count)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Sub-Tenants -->
            <div class="sseo-ai-card">
                <h3><?php esc_html_e('Recent Sub-Tenants', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Use', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTenants)): ?>
                            <tr><td colspan="6"><?php esc_html_e('No sub-tenants yet.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($recentTenants as $t):
                                $tUsage = $this->tenants->getTenantUsage($t['tenant_key']);
                                $apiUsed = (int)($tUsage['api_calls'] ?? 0);
                                $apiLimit = (int)$t['api_calls_limit'];
                                $usePct = $apiLimit > 0 ? min(round(($apiUsed / $apiLimit) * 100), 100) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-tenant-detail&tenant_id=' . $t['id'])); ?>">
                                            <?php echo esc_html($t['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(ucfirst($t['tier'])); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(ucfirst($t['status'])); ?></span></td>
                                    <td><?php echo esc_html($t['domain'] ?: '-'); ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div class="sseo-ai-usage-bar" style="flex:1;max-width:80px;height:6px;">
                                                <div class="sseo-ai-usage-bar__fill sseo-ai-usage-bar__fill--<?php echo $usePct >= 95 ? 'critical' : ($usePct >= 80 ? 'warning' : 'ok'); ?>" style="width:<?php echo esc_attr($usePct); ?>%"></div>
                                            </div>
                                            <span style="font-size:11px;"><?php echo esc_html(number_format($apiUsed) . '/' . number_format($apiLimit)); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($t['last_active'])) {
                                            echo esc_html(human_time_diff(strtotime($t['last_active']), current_time('timestamp')) . ' ago');
                                        } else {
                                            echo esc_html__('Never', 'sseo-ai-saas');
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Support Tickets -->
            <?php if (!empty($recentTickets)): ?>
            <div class="sseo-ai-card">
                <h3><?php esc_html_e('Recent Support Tickets', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e('Tenant', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Priority', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Subject', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Updated', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTickets as $ticket): ?>
                            <tr>
                                <td>#<?php echo esc_html($ticket['id']); ?></td>
                                <td><?php echo esc_html($ticket['tenant_name'] ?? '-'); ?></td>
                                <td><span class="badge badge-<?php echo esc_attr($ticket['priority']); ?>"><?php echo esc_html(ucfirst($ticket['priority'])); ?></span></td>
                                <td><span class="badge badge-<?php echo esc_attr($ticket['status']); ?>"><?php echo esc_html(ucfirst($ticket['status'])); ?></span></td>
                                <td><?php echo esc_html(mb_strlen($ticket['subject']) > 40 ? mb_substr($ticket['subject'], 0, 40) . '...' : $ticket['subject']); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($ticket['updated_at']), current_time('timestamp')) . ' ago'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 10px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-support')); ?>" class="button button-secondary">
                        <?php esc_html_e('View All Tickets', 'sseo-ai-saas'); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function renderGeneratePage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $account = $ctx['account'];
        $agencyTenantId = (int)$account['tenant_id'];
        $maxSubLicenses = (int)$account['max_sub_licenses'];
        $licenseCount = $this->licenseGenerator->countLicensesByAgency($agencyTenantId);
        $remaining = max($maxSubLicenses - $licenseCount, 0);

        $generated = null;
        $error = null;

        if (isset($_POST['agency_generate_license']) && wp_verify_nonce($_POST['_wpnonce'], 'agency_generate_license')) {
            if ($licenseCount >= $maxSubLicenses) {
                $error = __('You have reached your maximum sub-license quota.', 'sseo-ai-saas');
            } else {
                $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', sanitize_text_field($_POST['key_prefix'] ?? '')));
                if (empty($prefix) || strlen($prefix) > 6) {
                    $error = __('Prefix is required and must be 1-6 alphanumeric characters.', 'sseo-ai-saas');
                } else {
                    $options = [
                        'type' => 'paid',
                        'tier' => sanitize_text_field($_POST['license_tier'] ?? 'starter'),
                        'max_sites' => (int)($_POST['max_sites'] ?? 1),
                        'rate_limit' => (int)($_POST['rate_limit'] ?? 60),
                        'api_calls_limit' => (int)($_POST['api_calls_limit'] ?? 1000),
                        'expires_days' => !empty($_POST['expires_days']) ? (int)$_POST['expires_days'] : null,
                        'assigned_to' => sanitize_email($_POST['assigned_to'] ?? ''),
                        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                        'key_prefix' => $prefix,
                        'agency_tenant_id' => $agencyTenantId,
                    ];

                    $result = $this->licenseGenerator->generateLicense($options);

                    if (is_wp_error($result)) {
                        $error = $result->get_error_message();
                    } else {
                        $generated = $result;

                        $tenantResult = $this->tenants->createTenant([
                            'name' => sanitize_text_field($_POST['company_name'] ?? ($options['assigned_to'] ?: 'Sub-Tenant')),
                            'email' => $options['assigned_to'] ?: ('sub-' . time() . '@agency.local'),
                            'tier' => $options['tier'],
                            'license_key' => $result['license_key'],
                            'max_sites' => $options['max_sites'],
                            'rate_limit' => $options['rate_limit'],
                            'api_calls_limit' => $options['api_calls_limit'],
                            'expires_at' => $options['expires_days'] ? date('Y-m-d H:i:s', strtotime("+{$options['expires_days']} days")) : null,
                            'status' => 'active',
                            'parent_tenant_id' => $agencyTenantId,
                        ]);

                        if (is_wp_error($tenantResult)) {
                            $error = $tenantResult->get_error_message();
                        } else {
                            $modelTier = sanitize_text_field($_POST['model_tier'] ?? '');
                            if ($modelTier === 'standard' || $modelTier === 'premium') {
                                $this->tenants->setTenantSetting($tenantResult['tenant_key'], 'model_tier', $modelTier);
                            }
                        }
                    }
                }
            }
        }

        $defaultPrefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $ctx['tenant']['name'] ?? ''), 0, 4));
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('Generate Sub-License', 'sseo-ai-saas'); ?></h1>

            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <?php if ($generated): ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('License key generated successfully!', 'sseo-ai-saas'); ?></p>
                </div>
                <div class="sseo-ai-card generated-licenses">
                    <h3><?php esc_html_e('Generated License Key', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code class="license-key"><?php echo esc_html($generated['license_key']); ?></code></td>
                                <td><?php echo esc_html(ucfirst($generated['tier'])); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Generate New Sub-License', 'sseo-ai-saas'); ?></h2>
                <p class="description">
                    <?php
                    printf(
                        esc_html__('Quota: %1$d / %2$d used — %3$d remaining', 'sseo-ai-saas'),
                        $licenseCount,
                        $maxSubLicenses,
                        $remaining
                    );
                    ?>
                </p>

                <?php if ($remaining <= 0): ?>
                    <div class="notice notice-warning"><p><?php esc_html_e('You have reached your sub-license quota. Contact the administrator to increase your limit.', 'sseo-ai-saas'); ?></p></div>
                <?php else: ?>
                <form method="post">
                    <?php wp_nonce_field('agency_generate_license'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="key_prefix"><?php esc_html_e('License Key Prefix', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="key_prefix" id="key_prefix" value="<?php echo esc_attr($defaultPrefix); ?>" maxlength="6" class="small-text" style="" pattern="[A-Za-z0-9]{1,6}" required>
                                <p class="description"><?php esc_html_e('1-6 alphanumeric characters. Will be uppercased. Example: ACME produces ACME-AI-XXXX-XXXX-XXXX', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="company_name"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="company_name" id="company_name" class="regular-text" placeholder="Client company name">
                                <p class="description"><?php esc_html_e('Name for the sub-tenant', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="assigned_to"><?php esc_html_e('Client Email', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="email" name="assigned_to" id="assigned_to" class="regular-text" placeholder="client@example.com">
                                <p class="description"><?php esc_html_e('Email for the sub-tenant account', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="license_tier"><?php esc_html_e('License Tier', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="license_tier" id="license_tier">
                                    <option value="free"><?php esc_html_e('Free', 'sseo-ai-saas'); ?></option>
                                    <option value="trial"><?php esc_html_e('Trial', 'sseo-ai-saas'); ?></option>
                                    <option value="starter" selected><?php esc_html_e('Starter', 'sseo-ai-saas'); ?></option>
                                    <?php if (get_option('sseo_ai_saas_early_adopters_enabled', false)): ?>
                                        <option value="early_adopters"><?php esc_html_e('Early Adopters', 'sseo-ai-saas'); ?></option>
                                    <?php endif; ?>
                                    <option value="professional"><?php esc_html_e('Professional', 'sseo-ai-saas'); ?></option>
                                    <option value="business"><?php esc_html_e('Business', 'sseo-ai-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="model_tier"><?php esc_html_e('AI Model Tier', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="model_tier" id="model_tier">
                                    <option value="standard"><?php esc_html_e('Standard (cost-effective models)', 'sseo-ai-saas'); ?></option>
                                    <option value="premium"><?php esc_html_e('Premium (higher-quality models)', 'sseo-ai-saas'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Standard uses affordable models (GPT-4o-mini, Deepseek). Premium uses higher-quality models (GPT-4o, Claude 3.5).', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_sites"><?php esc_html_e('Max Sites', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="max_sites" id="max_sites" value="1" min="1" max="100" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="rate_limit"><?php esc_html_e('Rate Limit (per hour)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="rate_limit" id="rate_limit" value="60" min="10" max="10000" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="api_calls_limit"><?php esc_html_e('Monthly API Limit', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="api_calls_limit" id="api_calls_limit" value="1000" min="100" max="1000000" class="regular-text" style="max-width:200px;">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="expires_days"><?php esc_html_e('Expires After (days)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="expires_days" id="expires_days" value="" min="1" class="small-text" placeholder="Never">
                                <p class="description"><?php esc_html_e('Leave empty for no expiration', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notes"><?php esc_html_e('Notes', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <textarea name="notes" id="notes" rows="3" class="large-text" placeholder="Internal notes about this sub-license..."></textarea>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Generate Sub-License', 'sseo-ai-saas'), 'primary', 'agency_generate_license'); ?>
                </form>
                <?php endif; ?>
            </div>
            </div>
        </div>
        <?php
    }

    public function renderLicensesPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $agencyTenantId = (int)$ctx['account']['tenant_id'];
        $licenses = $this->licenseGenerator->getLicensesByAgency($agencyTenantId, 100, 0);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('All Sub-Licenses', 'sseo-ai-saas'); ?></h1>
            <div class="sseo-ai-card">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Max Sites', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Assigned To', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Prefix', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($licenses)): ?>
                            <tr><td colspan="7"><?php esc_html_e('No sub-licenses generated yet.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($licenses as $license): ?>
                                <tr>
                                    <td><code><?php echo esc_html($license['license_key']); ?></code></td>
                                    <td><?php echo esc_html(ucfirst($license['tier'])); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($license['status']); ?>"><?php echo esc_html(ucfirst($license['status'])); ?></span></td>
                                    <td><?php echo esc_html($license['max_sites']); ?></td>
                                    <td><?php echo esc_html($license['assigned_to'] ?: '-'); ?></td>
                                    <td><?php echo esc_html($license['key_prefix'] ?: '-'); ?></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($license['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
        <?php
    }

    public function renderTenantsPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $agencyTenantId = (int)$ctx['account']['tenant_id'];
        $tenants = $this->tenants->getSubTenants($agencyTenantId, 100, 0);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('Sub-Tenants', 'sseo-ai-saas'); ?></h1>
            <div class="sseo-ai-card">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Use', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Cost', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tenants)): ?>
                            <tr><td colspan="9"><?php esc_html_e('No sub-tenants yet.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($tenants as $t):
                                $tUsage = $this->tenants->getTenantUsage($t['tenant_key']);
                                $apiUsed = (int)($tUsage['api_calls'] ?? 0);
                                $apiLimit = (int)$t['api_calls_limit'];
                                $usePct = $apiLimit > 0 ? min(round(($apiUsed / $apiLimit) * 100), 100) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-tenant-detail&tenant_id=' . $t['id'])); ?>">
                                            <?php echo esc_html($t['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(ucfirst($t['tier'])); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(ucfirst($t['status'])); ?></span></td>
                                    <td><?php echo esc_html($t['domain'] ?: '-'); ?></td>
                                    <td><code><?php echo esc_html($t['license_key'] ?: '-'); ?></code></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div class="sseo-ai-usage-bar" style="flex:1;max-width:80px;height:6px;">
                                                <div class="sseo-ai-usage-bar__fill sseo-ai-usage-bar__fill--<?php echo $usePct >= 95 ? 'critical' : ($usePct >= 80 ? 'warning' : 'ok'); ?>" style="width:<?php echo esc_attr($usePct); ?>%"></div>
                                            </div>
                                            <span style="font-size:11px;"><?php echo esc_html(number_format($apiUsed) . '/' . number_format($apiLimit)); ?></span>
                                        </div>
                                    </td>
                                    <td>&euro;<?php echo esc_html(number_format((float)($tUsage['api_cost'] ?? 0), 2)); ?></td>
                                    <td>
                                        <?php
                                        if (!empty($t['last_active'])) {
                                            echo esc_html(human_time_diff(strtotime($t['last_active']), current_time('timestamp')) . ' ago');
                                        } else {
                                            echo esc_html__('Never', 'sseo-ai-saas');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($this->getTenantLoginUrl((int)$t['id'])); ?>" class="tenant-login-btn" target="_blank">
                                            <?php esc_html_e('Login', 'sseo-ai-saas'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
        <?php
    }

    public function renderTenantDetailPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $agencyTenantId = (int)$ctx['account']['tenant_id'];
        $tenantId = (int)($_GET['tenant_id'] ?? 0);

        $tenant = $this->tenants->getTenantById($tenantId);
        if (!$tenant || (int)$tenant['parent_tenant_id'] !== $agencyTenantId) {
            echo '<div class="wrap sseo-ai-license-admin"><div class="notice notice-error"><p>' . esc_html__('Tenant not found or not part of your agency.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $usage = $this->tenants->getTenantUsage($tenant['tenant_key']);
        $usageHistory = $this->tenants->getTenantUsageHistory($tenant['tenant_key'], 6);
        $tickets = $this->supportTickets->getTicketsForTenants([$tenantId]);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php echo esc_html($tenant['name']); ?></h1>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-tenants')); ?>">&larr; <?php esc_html_e('Back to Tenants', 'sseo-ai-saas'); ?></a>
                <a href="<?php echo esc_url($this->getTenantLoginUrl($tenantId)); ?>" class="tenant-login-btn" target="_blank" style="margin-left:15px;">
                    <?php esc_html_e('Login as Tenant', 'sseo-ai-saas'); ?>
                </a>
            </p>

            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(ucfirst($tenant['tier'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(ucfirst($tenant['status'])); ?></div>
                    <div class="stat-label"><?php esc_html_e('Status', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($usage['api_calls'] ?? 0)); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Calls This Month', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">&euro;<?php echo esc_html(number_format((float)($usage['api_cost'] ?? 0), 2)); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Cost This Month', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($usage['content_generated'] ?? 0)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Content Generated', 'sseo-ai-saas'); ?></div>
                </div>
            </div>

            <div class="sseo-ai-grid-2">
                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Tenant Details', 'sseo-ai-saas'); ?></h3>
                    <table class="form-table">
                        <tr><th><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></th><td><?php echo esc_html($tenant['domain'] ?: '-'); ?></td></tr>
                        <tr><th><?php esc_html_e('Email', 'sseo-ai-saas'); ?></th><td><?php echo esc_html($tenant['email']); ?></td></tr>
                        <tr><th><?php esc_html_e('License Key', 'sseo-ai-saas'); ?></th><td><code><?php echo esc_html($tenant['license_key'] ?: '-'); ?></code></td></tr>
                        <tr><th><?php esc_html_e('Max Sites', 'sseo-ai-saas'); ?></th><td><?php echo esc_html($tenant['max_sites']); ?></td></tr>
                        <tr><th><?php esc_html_e('Rate Limit', 'sseo-ai-saas'); ?></th><td><?php echo esc_html($tenant['rate_limit']); ?> /hr</td></tr>
                        <tr><th><?php esc_html_e('API Calls Limit', 'sseo-ai-saas'); ?></th><td><?php echo esc_html(number_format($tenant['api_calls_limit'])); ?> /mo</td></tr>
                        <tr><th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th><td><?php echo esc_html($tenant['created_at']); ?></td></tr>
                        <tr><th><?php esc_html_e('Expires', 'sseo-ai-saas'); ?></th><td><?php echo esc_html($tenant['expires_at'] ?: __('Never', 'sseo-ai-saas')); ?></td></tr>
                    </table>
                </div>

                <div class="sseo-ai-card">
                    <h3><?php esc_html_e('Usage History', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Period', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('API Cost', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('SERP', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Content', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usageHistory)): ?>
                                <tr><td colspan="5"><?php esc_html_e('No usage data yet.', 'sseo-ai-saas'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($usageHistory as $h): ?>
                                    <tr>
                                        <td><?php echo esc_html($h['period']); ?></td>
                                        <td><?php echo esc_html(number_format($h['api_calls'])); ?></td>
                                        <td><?php echo esc_html(number_format($h['api_cost'], 2)); ?></td>
                                        <td><?php echo esc_html(number_format($h['serp_requests'])); ?></td>
                                        <td><?php echo esc_html(number_format($h['content_generated'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($tickets)): ?>
            <div class="sseo-ai-card">
                <h3><?php esc_html_e('Support Tickets', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e('Priority', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Subject', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Updated', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td>#<?php echo esc_html($ticket['id']); ?></td>
                                <td><span class="badge badge-<?php echo esc_attr($ticket['priority']); ?>"><?php echo esc_html(ucfirst($ticket['priority'])); ?></span></td>
                                <td><span class="badge badge-<?php echo esc_attr($ticket['status']); ?>"><?php echo esc_html(ucfirst($ticket['status'])); ?></span></td>
                                <td><?php echo esc_html($ticket['subject']); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($ticket['updated_at']), current_time('timestamp')) . ' ago'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function renderSupportPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $agencyTenantId = (int)$ctx['account']['tenant_id'];
        $subTenantIds = $this->tenants->getSubTenantIds($agencyTenantId);

        $filters = [];
        if (!empty($_GET['status'])) {
            $filters['status'] = sanitize_text_field($_GET['status']);
        }
        if (!empty($_GET['priority'])) {
            $filters['priority'] = sanitize_text_field($_GET['priority']);
        }

        $tickets = $this->supportTickets->getTicketsForTenants($subTenantIds, $filters);
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('Support Tickets', 'sseo-ai-saas'); ?></h1>

            <div class="sseo-ai-card">
                <form method="get" style="margin-bottom: 15px;">
                    <input type="hidden" name="page" value="sseo-ai-agency-support">
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'sseo-ai-saas'); ?></option>
                        <option value="open" <?php selected($_GET['status'] ?? '', 'open'); ?>><?php esc_html_e('Open', 'sseo-ai-saas'); ?></option>
                        <option value="reaction" <?php selected($_GET['status'] ?? '', 'reaction'); ?>><?php esc_html_e('Awaiting Reply', 'sseo-ai-saas'); ?></option>
                        <option value="closed" <?php selected($_GET['status'] ?? '', 'closed'); ?>><?php esc_html_e('Closed', 'sseo-ai-saas'); ?></option>
                    </select>
                    <select name="priority">
                        <option value=""><?php esc_html_e('All Priorities', 'sseo-ai-saas'); ?></option>
                        <option value="low" <?php selected($_GET['priority'] ?? '', 'low'); ?>><?php esc_html_e('Low', 'sseo-ai-saas'); ?></option>
                        <option value="middle" <?php selected($_GET['priority'] ?? '', 'middle'); ?>><?php esc_html_e('Middle', 'sseo-ai-saas'); ?></option>
                        <option value="high" <?php selected($_GET['priority'] ?? '', 'high'); ?>><?php esc_html_e('High', 'sseo-ai-saas'); ?></option>
                    </select>
                    <?php submit_button(__('Filter', 'sseo-ai-saas'), 'secondary', 'filter', false); ?>
                </form>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e('Tenant', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Priority', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Subject', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Created', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Updated', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                            <tr><td colspan="7"><?php esc_html_e('No support tickets found.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td>#<?php echo esc_html($ticket['id']); ?></td>
                                    <td><?php echo esc_html($ticket['tenant_name'] ?? '-'); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($ticket['priority']); ?>"><?php echo esc_html(ucfirst($ticket['priority'])); ?></span></td>
                                    <td><span class="badge badge-<?php echo esc_attr($ticket['status']); ?>"><?php echo esc_html(ucfirst($ticket['status'])); ?></span></td>
                                    <td><?php echo esc_html($ticket['subject']); ?></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($ticket['created_at']), current_time('timestamp')) . ' ago'); ?></td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($ticket['updated_at']), current_time('timestamp')) . ' ago'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </div>
        </div>
        <?php
    }

    public function renderUsagePage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $agencyTenantId = (int)$ctx['account']['tenant_id'];

        $period = !empty($_GET['period']) ? sanitize_text_field($_GET['period']) : current_time('Y-m');
        $orderBy = !empty($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : 'api_cost';

        $usage = $this->tenants->getAgencySubTenantsUsage($agencyTenantId, $period);
        $subTenants = $this->tenants->getSubTenantsUsageByPeriod($agencyTenantId, $period, $orderBy);

        $periodLabel = date_i18n('F Y', strtotime($period . '-01'));
        $totalCost = (float)($usage['total_api_cost'] ?? 0);
        $totalCalls = (int)($usage['total_api_calls'] ?? 0);
        $totalContent = (int)($usage['total_content_generated'] ?? 0);
        $totalSerp = (int)($usage['total_serp_requests'] ?? 0);

        $prevPeriod = date('Y-m', strtotime($period . '-01 -1 month'));
        $nextPeriod = date('Y-m', strtotime($period . '-01 +1 month'));
        $isCurrentPeriod = $period === current_time('Y-m');
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('Usage & Costs', 'sseo-ai-saas'); ?></h1>

            <div class="sseo-ai-card" style="margin-bottom: 20px;">
                <form method="get" class="sseo-ai-inline-form" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="page" value="sseo-ai-agency-usage">
                    <label for="period"><strong><?php esc_html_e('Period:', 'sseo-ai-saas'); ?></strong></label>
                    <input type="month" id="period" name="period" value="<?php echo esc_attr($period); ?>">
                    <label for="order_by"><strong><?php esc_html_e('Sort by:', 'sseo-ai-saas'); ?></strong></label>
                    <select id="order_by" name="order_by">
                        <option value="api_cost" <?php selected($orderBy, 'api_cost'); ?>><?php esc_html_e('API Cost', 'sseo-ai-saas'); ?></option>
                        <option value="api_calls" <?php selected($orderBy, 'api_calls'); ?>><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></option>
                        <option value="content_generated" <?php selected($orderBy, 'content_generated'); ?>><?php esc_html_e('Content Generated', 'sseo-ai-saas'); ?></option>
                        <option value="serp_requests" <?php selected($orderBy, 'serp_requests'); ?>><?php esc_html_e('SERP Requests', 'sseo-ai-saas'); ?></option>
                    </select>
                    <?php submit_button(__('Filter', 'sseo-ai-saas'), 'secondary', 'filter', false); ?>
                    <?php if (!$isCurrentPeriod): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-usage')); ?>" class="button button-secondary"><?php esc_html_e('Current Month', 'sseo-ai-saas'); ?></a>
                    <?php endif; ?>
                </form>
                <p class="description" style="margin-top:10px;">
                    <?php printf(esc_html__('Showing usage for %s. Total values include all sub-tenants under your agency.', 'sseo-ai-saas'), esc_html($periodLabel)); ?>
                </p>
            </div>

            <div class="sseo-ai-stats-grid" style="margin-top:20px;">
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($totalCalls)); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">&euro;<?php echo esc_html(number_format($totalCost, 2)); ?></div>
                    <div class="stat-label"><?php esc_html_e('API Cost', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($totalContent)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Content Generated', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo esc_html(number_format($totalSerp)); ?></div>
                    <div class="stat-label"><?php esc_html_e('SERP Requests', 'sseo-ai-saas'); ?></div>
                </div>
            </div>

            <div class="sseo-ai-card">
                <h3><?php esc_html_e('Sub-Tenant Usage & Costs', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tenant', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Cost', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Use', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Content', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('SERP', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Last Active', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subTenants)): ?>
                            <tr><td colspan="9"><?php esc_html_e('No sub-tenants found.', 'sseo-ai-saas'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($subTenants as $t):
                                $apiUsed = (int)($t['api_calls'] ?? 0);
                                $apiLimit = (int)($t['api_calls_limit'] ?? 0);
                                $usePct = $apiLimit > 0 ? min(round(($apiUsed / $apiLimit) * 100), 100) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-tenant-detail&tenant_id=' . $t['id'])); ?>">
                                            <?php echo esc_html($t['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(ucfirst($t['tier'])); ?></td>
                                    <td><span class="badge badge-<?php echo esc_attr($t['status']); ?>"><?php echo esc_html(ucfirst($t['status'])); ?></span></td>
                                    <td><?php echo esc_html(number_format($apiUsed)); ?></td>
                                    <td>&euro;<?php echo esc_html(number_format((float)($t['api_cost'] ?? 0), 2)); ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <div class="sseo-ai-usage-bar" style="flex:1;max-width:80px;height:6px;">
                                                <div class="sseo-ai-usage-bar__fill sseo-ai-usage-bar__fill--<?php echo $usePct >= 95 ? 'critical' : ($usePct >= 80 ? 'warning' : 'ok'); ?>" style="width:<?php echo esc_attr($usePct); ?>%"></div>
                                            </div>
                                            <span style="font-size:11px;"><?php echo esc_html(number_format($apiUsed) . '/' . number_format($apiLimit)); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html(number_format((int)($t['content_generated'] ?? 0))); ?></td>
                                    <td><?php echo esc_html(number_format((int)($t['serp_requests'] ?? 0))); ?></td>
                                    <td>
                                        <?php
                                        if (!empty($t['last_active'])) {
                                            echo esc_html(human_time_diff(strtotime($t['last_active']), current_time('timestamp')) . ' ago');
                                        } else {
                                            esc_html_e('Never', 'sseo-ai-saas');
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:600;">
                            <td colspan="3"><?php esc_html_e('Total', 'sseo-ai-saas'); ?></td>
                            <td><?php echo esc_html(number_format($totalCalls)); ?></td>
                            <td>&euro;<?php echo esc_html(number_format($totalCost, 2)); ?></td>
                            <td colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get the current agency's white-label settings.
     */
    public function getWhiteLabelSettings(): array
    {
        $userId = get_current_user_id();
        $saved = get_user_meta($userId, 'sseo_ai_agency_wl', true);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge([
            'company_name' => '',
            'company_logo' => '',
            'primary_color' => '#379fd3',
            'secondary_color' => '#8f39ac',
            'support_email' => '',
            'support_url' => '',
        ], $saved);
    }

    /**
     * Render agency white-label settings page.
     */
    public function renderWhiteLabelSettings(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $wl = $this->getWhiteLabelSettings();
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('White-Label Settings', 'sseo-ai-saas'); ?></h1>

            <?php if ($message === 'saved'): ?>
                <div class="notice notice-success"><p><?php esc_html_e('White-label settings saved.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if ($error === 'invalid_type'): ?>
                <div class="notice notice-error"><p><?php esc_html_e('Please upload a valid image file (PNG, JPG, GIF or WEBP).', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if ($error === 'too_large'): ?>
                <div class="notice notice-error"><p><?php esc_html_e('The uploaded logo is too large.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <?php if ($error === 'upload_failed'): ?>
                <div class="notice notice-error"><p><?php esc_html_e('The logo could not be saved on the server.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_agency_save_wl')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('sseo_ai_agency_wl_save'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="company_name"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="text" id="company_name" name="company_name" value="<?php echo esc_attr($wl['company_name']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="company_logo"><?php esc_html_e('Company Logo', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="file" id="company_logo" name="company_logo" accept="image/png, image/jpeg, image/gif, image/webp" class="regular-text">
                            <p class="description"><?php esc_html_e('Recommended: 200x50px transparent PNG or JPG.', 'sseo-ai-saas'); ?></p>
                            <?php if ($wl['company_logo']): ?>
                                <p><img id="logo-preview" src="<?php echo esc_url($wl['company_logo']); ?>?<?php echo esc_attr(time()); ?>" alt="" style="max-height: 50px; margin-top: 10px; background: #f0f0f0; padding: 5px;"></p>
                            <?php else: ?>
                                <p><img id="logo-preview" src="" alt="" style="max-height: 50px; margin-top: 10px; background: #f0f0f0; padding: 5px; display: none;"></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="primary_color"><?php esc_html_e('Primary Color', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr($wl['primary_color']); ?>">
                            <span class="color-preview" style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr($wl['primary_color']); ?>; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid #ccc;"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="secondary_color"><?php esc_html_e('Secondary Color', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="color" id="secondary_color" name="secondary_color" value="<?php echo esc_attr($wl['secondary_color']); ?>">
                            <span class="color-preview" style="display: inline-block; width: 30px; height: 30px; background: <?php echo esc_attr($wl['secondary_color']); ?>; border-radius: 4px; margin-left: 10px; vertical-align: middle; border: 1px solid #ccc;"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="support_email"><?php esc_html_e('Support Email', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="email" id="support_email" name="support_email" value="<?php echo esc_attr($wl['support_email']); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="support_url"><?php esc_html_e('Support URL', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="url" id="support_url" name="support_url" value="<?php echo esc_attr($wl['support_url']); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save White-Label Settings', 'sseo-ai-saas'), 'primary'); ?>
            </form>

            <div class="sseo-ai-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Your White-Label Client Plugin', 'sseo-ai-saas'); ?></h2>
                <p style="color: #646970;">
                    <?php esc_html_e('Download a ready-to-install, re-branded client plugin for your customers. Your company name is used as the plugin name.', 'sseo-ai-saas'); ?>
                </p>
                <?php if (!empty($wl['company_name'])): ?>
                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_agency_download_wl_client&_wpnonce=' . wp_create_nonce('sseo_ai_agency_wl_download'))); ?>"
                       class="button button-primary button-hero" style="margin-top: 10px;">
                        <?php esc_html_e('Download White-Label Client (.zip)', 'sseo-ai-saas'); ?>
                    </a>
                <?php else: ?>
                    <p style="margin-top: 10px; color: #d63638;">
                        <?php esc_html_e('Set a Company Name above and save your settings to enable the plugin download.', 'sseo-ai-saas'); ?>
                    </p>
                <?php endif; ?>
            </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save agency white-label settings from the form post.
     */
    public function handleSaveWhiteLabel(): void
    {
        if (!check_admin_referer('sseo_ai_agency_wl_save')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }

        if (!$this->roleManager->isAgencyUser() || !current_user_can('agency_upload_logo')) {
            wp_die(__('You do not have permission to manage white-label settings.', 'sseo-ai-saas'));
        }

        $userId = get_current_user_id();
        $companyLogoUrl = '';

        if (!empty($_FILES['company_logo']['tmp_name']) && is_uploaded_file($_FILES['company_logo']['tmp_name'])) {
            $allowedMimes = [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ];
            $fileInfo = wp_check_filetype($_FILES['company_logo']['name'], $allowedMimes);
            if (empty($fileInfo['ext']) || empty($fileInfo['type'])) {
                wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-wl&error=invalid_type'));
                exit;
            }

            $maxSize = wp_max_upload_size() ?: (2 * MB_IN_BYTES);
            if ($_FILES['company_logo']['size'] > $maxSize) {
                wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-wl&error=too_large'));
                exit;
            }

            $uploadDir = wp_upload_dir();
            $agencyLogosDir = $uploadDir['basedir'] . '/fyndable-agency-logos/' . $userId;
            $agencyLogosUrl = $uploadDir['baseurl'] . '/fyndable-agency-logos/' . $userId;

            wp_mkdir_p($agencyLogosDir);

            // Remove the previous logo file in this agency's own directory.
            $existing = $this->getWhiteLabelSettings();
            if (!empty($existing['company_logo'])) {
                $oldPath = str_replace($uploadDir['baseurl'], $uploadDir['basedir'], $existing['company_logo']);
                if (file_exists($oldPath) && strpos($oldPath, $agencyLogosDir) === 0) {
                    @unlink($oldPath);
                }
            }

            $filename = 'logo.' . $fileInfo['ext'];
            $target = $agencyLogosDir . '/' . $filename;
            if (!move_uploaded_file($_FILES['company_logo']['tmp_name'], $target)) {
                wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-wl&error=upload_failed'));
                exit;
            }

            $companyLogoUrl = $agencyLogosUrl . '/' . $filename;
        } else {
            $existing = $this->getWhiteLabelSettings();
            $companyLogoUrl = $existing['company_logo'] ?? '';
        }

        $wl = [
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'company_logo' => esc_url_raw($companyLogoUrl),
            'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '') ?: '#379fd3',
            'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '') ?: '#8f39ac',
            'support_email' => sanitize_email($_POST['support_email'] ?? ''),
            'support_url' => esc_url_raw($_POST['support_url'] ?? ''),
        ];

        update_user_meta($userId, 'sseo_ai_agency_wl', $wl);

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-wl&message=saved'));
        exit;
    }

    /**
     * Build and serve a white-labeled client plugin .zip download for the
     * current agency, using the agency's own company name as branding.
     */
    public function handleDownloadWhiteLabelClient(): void
    {
        if (!check_admin_referer('sseo_ai_agency_wl_download')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }

        if (!$this->roleManager->isAgencyUser()) {
            wp_die(__('You do not have permission to download packages.', 'sseo-ai-saas'));
        }

        $wl = $this->getWhiteLabelSettings();
        $companyName = $wl['company_name'] ?? '';
        if (empty($companyName)) {
            wp_die(__('Please set a Company Name on the White-Label settings page first.', 'sseo-ai-saas'));
        }

        $builder = new WhiteLabelPackageBuilder();
        $zipPath = $builder->buildClientZip($companyName);

        if (is_wp_error($zipPath)) {
            wp_die(esc_html($zipPath->get_error_message()));
        }

        $companySlug = sanitize_title($companyName);
        $companySlug = preg_replace('/[^a-z0-9-]/', '', $companySlug) ?: 'agency';
        $zipName = $companySlug . '-client.zip';

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    public function renderAccountPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $account = $ctx['account'];
        $tenant = $ctx['tenant'];
        $user = wp_get_current_user();
        $message = '';
        $error = '';

        if (isset($_POST['agency_update_account']) && wp_verify_nonce($_POST['_wpnonce'], 'agency_update_account')) {
            $name = sanitize_text_field($_POST['company_name'] ?? '');
            $domain = esc_url_raw($_POST['domain'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $firstName = sanitize_text_field($_POST['first_name'] ?? '');
            $lastName = sanitize_text_field($_POST['last_name'] ?? '');
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $address = sanitize_textarea_field($_POST['address'] ?? '');
            $postal = sanitize_text_field($_POST['postal_code'] ?? '');
            $city = sanitize_text_field($_POST['city'] ?? '');
            $country = sanitize_text_field($_POST['country'] ?? '');

            $tenantUpdates = [];
            if (!empty($name)) {
                $tenantUpdates['name'] = $name;
            }
            if (!empty($domain)) {
                $tenantUpdates['domain'] = $domain;
            }
            if (!empty($tenantUpdates)) {
                $res = $this->tenants->updateTenant($tenant['tenant_key'], $tenantUpdates);
                if (is_wp_error($res)) {
                    $error = $res->get_error_message();
                } else {
                    $tenant = $this->tenants->getTenantById((int)$account['tenant_id']) ?: $tenant;
                }
            }

            if (empty($error) && !empty($email) && $email !== $user->user_email) {
                $existingId = email_exists($email);
                if ($existingId && (int) $existingId !== (int) $user->ID) {
                    $error = __('This email is already in use.', 'sseo-ai-saas');
                } else {
                    $updateResult = wp_update_user([
                        'ID' => $user->ID,
                        'user_email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ]);
                    if (is_wp_error($updateResult)) {
                        $error = $updateResult->get_error_message();
                    } else {
                        $user = get_userdata($user->ID) ?: $user;
                    }
                }
            } else {
                wp_update_user([
                    'ID' => $user->ID,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
            }

            update_user_meta($user->ID, 'fyndable_phone', $phone);
            update_user_meta($user->ID, 'fyndable_address', $address);
            update_user_meta($user->ID, 'fyndable_postal_code', $postal);
            update_user_meta($user->ID, 'fyndable_city', $city);
            update_user_meta($user->ID, 'fyndable_country', $country);

            // Update WordPress user locale (language) — empty string = site default.
            $localeInput = sanitize_text_field($_POST['wp_locale'] ?? '');
            $allowedLocales = array_merge(['en_US'], (array) \get_available_languages());
            if ($localeInput === '' || in_array($localeInput, $allowedLocales, true)) {
                $localeUpdate = \wp_update_user([
                    'ID' => $user->ID,
                    'locale' => $localeInput,
                ]);
                if (is_wp_error($localeUpdate)) {
                    $error = $localeUpdate->get_error_message();
                }
            } else {
                $error = __('Invalid language selected.', 'sseo-ai-saas');
            }

            if (empty($error)) {
                $message = __('Account updated successfully.', 'sseo-ai-saas');
            }
        }

        $firstName = $user->first_name ?: get_user_meta($user->ID, 'first_name', true);
        $lastName = $user->last_name ?: get_user_meta($user->ID, 'last_name', true);
        $phone = get_user_meta($user->ID, 'fyndable_phone', true);
        $address = get_user_meta($user->ID, 'fyndable_address', true);
        $postal = get_user_meta($user->ID, 'fyndable_postal_code', true);
        $city = get_user_meta($user->ID, 'fyndable_city', true);
        $country = get_user_meta($user->ID, 'fyndable_country', true);
        $currentLocale = get_user_meta($user->ID, 'locale', true);
        // Build the list of available languages for the selector.
        $availableLanguages = (array) \get_available_languages();
        $translations = \function_exists('wp_get_available_translations') ? \wp_get_available_translations() : [];
        // Fallback map of common locale codes → native names (used when
        // wp_get_available_translations() is not loaded or returns nothing).
        $nativeNames = [
            'nl_NL' => 'Nederlands',
            'nl_BE' => 'Nederlands (België)',
            'de_DE' => 'Deutsch',
            'de_AT' => 'Deutsch (Österreich)',
            'de_CH' => 'Deutsch (Schweiz)',
            'fr_FR' => 'Français',
            'fr_BE' => 'Français (Belgique)',
            'es_ES' => 'Español',
            'it_IT' => 'Italiano',
            'pt_PT' => 'Português',
            'pt_BR' => 'Português (Brasil)',
            'pl_PL' => 'Polski',
            'da_DK' => 'Dansk',
            'sv_SE' => 'Svenska',
            'nb_NO' => 'Norsk',
            'fi_FI' => 'Suomi',
            'cs_CZ' => 'Čeština',
            'tr_TR' => 'Türkçe',
            'ru_RU' => 'Русский',
            'ar' => 'العربية',
            'zh_CN' => '简体中文',
            'ja_JP' => '日本語',
            'ko_KR' => '한국어',
            'en_GB' => 'English (UK)',
        ];
        $languageOptions = [];
        // Site default first (empty value).
        $languageOptions[''] = __('Site Default', 'sseo-ai-saas');
        // English is always available (source language).
        $languageOptions['en_US'] = 'English';
        foreach ($availableLanguages as $lang) {
            if (isset($translations[$lang]['native_name'])) {
                $languageOptions[$lang] = $translations[$lang]['native_name'];
            } elseif (isset($nativeNames[$lang])) {
                $languageOptions[$lang] = $nativeNames[$lang];
            } else {
                $languageOptions[$lang] = $lang;
            }
        }
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('My Account', 'sseo-ai-saas'); ?></h1>
            <?php if ($message): ?>
                <div class="notice notice-success"><p><?php echo esc_html($message); ?></p></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <div class="sseo-ai-card">
                <form method="post">
                    <?php wp_nonce_field('agency_update_account'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="company_name"><?php esc_html_e('Company Name', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="company_name" id="company_name" value="<?php echo esc_attr($tenant['name'] ?? ''); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="email"><?php esc_html_e('Email', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="email" name="email" id="email" value="<?php echo esc_attr($user->user_email); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="domain"><?php esc_html_e('Domain', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="url" name="domain" id="domain" value="<?php echo esc_attr($tenant['domain'] ?? ''); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="first_name"><?php esc_html_e('First Name', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="first_name" id="first_name" value="<?php echo esc_attr($firstName); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="last_name"><?php esc_html_e('Last Name', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="last_name" id="last_name" value="<?php echo esc_attr($lastName); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="phone"><?php esc_html_e('Phone', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="phone" id="phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="address"><?php esc_html_e('Address', 'sseo-ai-saas'); ?></label></th>
                            <td><textarea name="address" id="address" rows="3" class="large-text"><?php echo esc_textarea($address); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="postal_code"><?php esc_html_e('Postal Code', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr($postal); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="city"><?php esc_html_e('City', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="city" id="city" value="<?php echo esc_attr($city); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="country"><?php esc_html_e('Country', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" name="country" id="country" value="<?php echo esc_attr($country); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wp_locale"><?php esc_html_e('Language', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="wp_locale" id="wp_locale" class="regular-text">
                                    <?php foreach ($languageOptions as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($currentLocale, $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose the language for the Fyndable dashboard. This sets your WordPress profile language. The dashboard reloads in the selected language after saving. Site Default follows the site language setting.', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Account', 'sseo-ai-saas'), 'primary', 'agency_update_account'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function renderInvoicesPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $tenant = $ctx['tenant'];
        $invoices = $this->invoiceManager->getInvoices($tenant['tenant_key']);
        $viewInvoiceId = isset($_GET['view']) ? (int) $_GET['view'] : 0;
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Invoices', 'sseo-ai-saas'); ?></h1>
            <div class="sseo-ai-card">
                <?php if (empty($invoices)): ?>
                    <p><?php esc_html_e('No invoices found.', 'sseo-ai-saas'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Invoice #', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Description', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Amount', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo esc_html($invoice['invoice_number']); ?></td>
                                    <td><?php echo esc_html($invoice['description']); ?></td>
                                    <td>&euro;<?php echo esc_html(number_format((float) ($invoice['amount'] ?? 0), 2)); ?></td>
                                    <td><?php echo esc_html(ucfirst($invoice['status'] ?? '')); ?></td>
                                    <td><?php echo esc_html($invoice['created_at']); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency-invoices&view=' . (int) $invoice['id'])); ?>" class="button button-small">
                                            <?php esc_html_e('View', 'sseo-ai-saas'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_agency_print_invoice&invoice_id=' . (int) $invoice['id'] . '&_wpnonce=' . wp_create_nonce('sseo_ai_agency_print_invoice_' . $invoice['id']))); ?>" class="button button-small" target="_blank">
                                            <?php esc_html_e('Print', 'sseo-ai-saas'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        if ($viewInvoiceId > 0) {
            $this->renderInvoiceViewPage($viewInvoiceId);
        }
    }

    private function renderInvoiceViewPage(int $invoiceId): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            return;
        }
        $invoice = $this->invoiceManager->getInvoice($invoiceId, $ctx['tenant']['tenant_key']);
        if (!$invoice) {
            echo '<div class="wrap sseo-ai-license-admin"><div class="notice notice-error"><p>' . esc_html__('Invoice not found or not part of your account.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }
        echo '<div class="wrap sseo-ai-license-admin"><div class="sseo-ai-card">' . $this->invoiceManager->renderInvoiceHtml($invoice, $ctx['tenant']) . '</div></div>';
    }

    public function handlePrintInvoice(): void
    {
        if (!isset($_GET['invoice_id'], $_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sseo_ai_agency_print_invoice_' . $_GET['invoice_id'])) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }
        if (!$this->roleManager->isAgencyUser()) {
            wp_die(__('You do not have permission to view invoices.', 'sseo-ai-saas'));
        }
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            wp_die(__('Agency account not found.', 'sseo-ai-saas'));
        }
        $invoice = $this->invoiceManager->getInvoice((int) $_GET['invoice_id'], $ctx['tenant']['tenant_key']);
        if (!$invoice) {
            wp_die(__('Invoice not found.', 'sseo-ai-saas'));
        }
        echo $this->invoiceManager->renderInvoicePrintHtml($invoice, $ctx['tenant']);
        exit;
    }

    public function renderAddLicensesPage(): void
    {
        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('Agency account not found.', 'sseo-ai-saas') . '</p></div></div>';
            return;
        }

        $account = $ctx['account'];
        $agencyTenantId = (int) $account['tenant_id'];
        $maxSubLicenses = (int) $account['max_sub_licenses'];
        $licenseCount = $this->licenseGenerator->countLicensesByAgency($agencyTenantId);
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
        $success = isset($_GET['success']) ? sanitize_text_field($_GET['success']) : '';
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Extra Licenses', 'sseo-ai-saas'); ?></h1>
            <p class="description" style="margin: 0 20px; color: #fff;">
                <?php
                printf(
                    esc_html__('Current plan: %1$d / %2$d sub-licenses used.', 'sseo-ai-saas'),
                    $licenseCount,
                    $maxSubLicenses
                );
                ?>
            </p>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($success === 'scheduled'): ?>
                <div class="notice notice-success"><p><?php esc_html_e('Licenses added successfully. The extra amount will be included in the next direct debit.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>
            <div class="sseo-ai-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Add more sub-licenses', 'sseo-ai-saas'); ?></h2>
                <p style="color: #646970;">
                    <?php esc_html_e('Additional licenses cost €49,99 each for the first 10 extra licenses (up to 20 total) and €34,99 each thereafter.', 'sseo-ai-saas'); ?><br>
                    <?php esc_html_e('The extra amount will be added to the next direct debit. You can use the licenses right away.', 'sseo-ai-saas'); ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_agency_add_licenses')); ?>">
                    <?php wp_nonce_field('sseo_ai_agency_add_licenses'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="extra_licenses"><?php esc_html_e('Number of extra licenses', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="extra_licenses" id="extra_licenses" value="1" min="1" max="100" class="small-text" required>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Add licenses', 'sseo-ai-saas'), 'primary', 'agency_add_licenses'); ?>
                </form>
            </div>
            <div class="sseo-ai-card">
                <p style="color: #646970;">
                    <?php esc_html_e('To reduce the number of licenses, please contact support.', 'sseo-ai-saas'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    public function handleAddLicenses(): void
    {
        if (!isset($_POST['agency_add_licenses'], $_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sseo_ai_agency_add_licenses')) {
            wp_die(__('Security check failed.', 'sseo-ai-saas'));
        }
        if (!$this->roleManager->isAgencyUser()) {
            wp_die(__('You do not have permission to add licenses.', 'sseo-ai-saas'));
        }

        $ctx = $this->getAgencyContext();
        if (isset($ctx['error'])) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-add-licenses&error=no_account'));
            exit;
        }

        $quantity = (int) ($_POST['extra_licenses'] ?? 0);
        if ($quantity < 1) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-add-licenses&error=invalid_quantity'));
            exit;
        }

        $account = $this->roleManager->getAgencyAccount();
        if (!$account) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-add-licenses&error=no_account'));
            exit;
        }

        $tenant = $ctx['tenant'];

        // Add the extra licenses to the next direct debit for this agency account.
        $newMax = (int) $account['max_sub_licenses'] + $quantity;
        $this->tenants->updateAgencyAccount((int) $account['id'], ['max_sub_licenses' => $newMax]);

        $currentMax = (int) $account['max_sub_licenses'];
        $monthlyExtra = 0.0;
        for ($i = 1; $i <= $quantity; $i++) {
            $licenseNumber = max($currentMax, 10) + $i;
            $monthlyExtra += $licenseNumber <= 20 ? 49.99 : 34.99;
        }

        $updateSub = $this->paymentProcessor->updateMollieSubscriptionAmount($tenant['tenant_key'], $monthlyExtra);
        if (is_wp_error($updateSub)) {
            wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-add-licenses&error=' . urlencode($updateSub->get_error_message())));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-add-licenses&success=scheduled'));
        exit;
    }
}

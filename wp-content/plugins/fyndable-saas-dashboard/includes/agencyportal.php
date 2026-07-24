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
    private string $pluginFile;

    public function __construct(
        string $pluginFile,
        TenantRepository $tenants,
        LicenseKeyGenerator $licenseGenerator,
        SupportTickets $supportTickets,
        AgencyRoleManager $roleManager
    ) {
        $this->pluginFile = $pluginFile;
        $this->tenants = $tenants;
        $this->licenseGenerator = $licenseGenerator;
        $this->supportTickets = $supportTickets;
        $this->roleManager = $roleManager;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_head', [$this, 'injectAgencyHeaderStyle']);
        add_action('admin_post_sseo_ai_agency_save_wl', [$this, 'handleSaveWhiteLabel']);
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
        wp_enqueue_media();
    }

    /**
     * Inject the Fyndable gradient topbar header on agency pages.
     */
    public function injectAgencyHeaderStyle(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'sseo-ai-agency') === false) {
            return;
        }

        $account = $this->roleManager->getAgencyAccount();
        $agencyName = '';
        if ($account) {
            $tenant = $this->tenants->getTenantById((int)$account['tenant_id']);
            if ($tenant) {
                $agencyName = $tenant['name'];
            }
        }

        $user = wp_get_current_user();
        ?>
        <style>
            .fyndable-agency-topbar {
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                color: #fff;
                padding: 16px 30px;
                margin: -10px -20px 0 -10px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }
            .fyndable-agency-topbar .brand {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .fyndable-agency-topbar .brand-logo {
                font-size: 22px;
                font-weight: 700;
                letter-spacing: -0.5px;
            }
            .fyndable-agency-topbar .brand-logo span {
                font-weight: 400;
                opacity: 0.85;
            }
            .fyndable-agency-topbar .agency-badge {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 4px 12px;
                border-radius: 20px;
                background: rgba(255,255,255,0.2);
            }
            .fyndable-agency-topbar .user-info {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 13px;
            }
            .fyndable-agency-topbar .user-info .avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                border: 2px solid rgba(255,255,255,0.3);
            }
            .fyndable-agency-content {
                padding: 0;
            }
            .fyndable-agency-content h1 {
                display: none !important;
            }
            .sseo-ai-license-admin .fyndable-agency-content .sseo-ai-stats-grid,
            .sseo-ai-license-admin .fyndable-agency-content .sseo-ai-card {
                margin-top: 20px;
            }
            .fyndable-agency-topbar .user-info .account-link,
            .fyndable-agency-topbar .user-info .logout-link {
                color: #fff;
                text-decoration: none;
                background: rgba(255,255,255,0.15);
                border: 1px solid rgba(255,255,255,0.3);
                border-radius: 8px;
                padding: 6px 14px;
                font-size: 13px;
                font-weight: 500;
                transition: background 0.15s;
            }
            .fyndable-agency-topbar .user-info .account-link:hover,
            .fyndable-agency-topbar .user-info .logout-link:hover {
                background: rgba(255,255,255,0.25);
            }
            .tenant-login-btn {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 12px;
                font-weight: 600;
                padding: 4px 10px;
                border-radius: 6px;
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                color: #fff !important;
                text-decoration: none;
                border: none;
                cursor: pointer;
                transition: transform 0.15s, box-shadow 0.15s;
            }
            .tenant-login-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(55,159,211,0.3);
                color: #fff !important;
            }
        </style>
        <?php
    }

    /**
     * Render the shared agency topbar header.
     */
    private function renderAgencyHeader(): void
    {
        $account = $this->roleManager->getAgencyAccount();
        $agencyName = '';
        if ($account) {
            $tenant = $this->tenants->getTenantById((int)$account['tenant_id']);
            if ($tenant) {
                $agencyName = $tenant['name'];
            }
        }

        $wl = $this->getWhiteLabelSettings();
        $companyName = !empty($wl['company_name']) ? $wl['company_name'] : $agencyName;
        $primaryColor = $wl['primary_color'] ?? '#379fd3';
        $secondaryColor = $wl['secondary_color'] ?? '#8f39ac';
        ?>
        <div class="fyndable-agency-topbar" style="background: linear-gradient(135deg, <?php echo esc_attr($primaryColor); ?> 0%, <?php echo esc_attr($secondaryColor); ?> 100%);">
            <div class="brand">
                <div class="brand-logo">Fyndable Smart SEO</div>
                <div class="agency-badge"><?php echo esc_html($companyName ?: __('Agency', 'sseo-ai-saas')); ?></div>
            </div>
            <div class="user-info">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-agency')); ?>" class="account-link"><?php esc_html_e('Agency account', 'sseo-ai-saas'); ?></a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="logout-link"><?php esc_html_e('Logout', 'sseo-ai-saas'); ?></a>
            </div>
        </div>
        <?php
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
            $tier = $t['tier'] ?? 'free';
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
                    <div class="stat-value">$<?php echo esc_html(number_format((float)($usage['total_api_cost'] ?? 0), 2)); ?></div>
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
                                <input type="text" name="key_prefix" id="key_prefix" value="<?php echo esc_attr($defaultPrefix); ?>" maxlength="6" class="small-text" style="text-transform:uppercase;" pattern="[A-Za-z0-9]{1,6}" required>
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
                                    <option value="professional"><?php esc_html_e('Professional', 'sseo-ai-saas'); ?></option>
                                    <option value="business"><?php esc_html_e('Business', 'sseo-ai-saas'); ?></option>
                                </select>
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
                                    <td>$<?php echo esc_html(number_format((float)($tUsage['api_cost'] ?? 0), 2)); ?></td>
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
                    <div class="stat-value">$<?php echo esc_html(number_format((float)($usage['api_cost'] ?? 0), 2)); ?></div>
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
                    <div class="stat-value">$<?php echo esc_html(number_format($totalCost, 2)); ?></div>
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
                                    <td>$<?php echo esc_html(number_format((float)($t['api_cost'] ?? 0), 2)); ?></td>
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
                            <td>$<?php echo esc_html(number_format($totalCost, 2)); ?></td>
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
        ?>
        <div class="wrap sseo-ai-license-admin">
            <?php $this->renderAgencyHeader(); ?>
            <div class="fyndable-agency-content">
            <h1><?php esc_html_e('White-Label Settings', 'sseo-ai-saas'); ?></h1>

            <?php if ($message === 'saved'): ?>
                <div class="notice notice-success"><p><?php esc_html_e('White-label settings saved.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_agency_save_wl')); ?>">
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
                            <input type="url" id="company_logo" name="company_logo" value="<?php echo esc_attr($wl['company_logo']); ?>" class="regular-text">
                            <input type="button" id="upload_agency_logo" class="button" value="<?php esc_attr_e('Upload Logo', 'sseo-ai-saas'); ?>">
                            <p class="description"><?php esc_html_e('Recommended: 200x50px transparent PNG.', 'sseo-ai-saas'); ?></p>
                            <?php if ($wl['company_logo']): ?>
                                <p><img id="logo-preview" src="<?php echo esc_url($wl['company_logo']); ?>" alt="" style="max-height: 50px; margin-top: 10px; background: #f0f0f0; padding: 5px;"></p>
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
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var frame;
            $('#upload_agency_logo').on('click', function(e) {
                e.preventDefault();
                if (frame) {
                    frame.open();
                    return;
                }
                frame = wp.media({
                    title: '<?php echo esc_js(__('Select Company Logo', 'sseo-ai-saas')); ?>',
                    button: { text: '<?php echo esc_js(__('Use this logo', 'sseo-ai-saas')); ?>' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#company_logo').val(attachment.url);
                    $('#logo-preview').attr('src', attachment.url).show();
                });
                frame.open();
            });
        });
        </script>
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

        if (!$this->roleManager->isAgencyUser()) {
            wp_die(__('You do not have permission to manage white-label settings.', 'sseo-ai-saas'));
        }

        $wl = [
            'company_name' => sanitize_text_field($_POST['company_name'] ?? ''),
            'company_logo' => esc_url_raw($_POST['company_logo'] ?? ''),
            'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '') ?: '#379fd3',
            'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '') ?: '#8f39ac',
            'support_email' => sanitize_email($_POST['support_email'] ?? ''),
            'support_url' => esc_url_raw($_POST['support_url'] ?? ''),
        ];

        update_user_meta(get_current_user_id(), 'sseo_ai_agency_wl', $wl);

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-agency-wl&message=saved'));
        exit;
    }
}

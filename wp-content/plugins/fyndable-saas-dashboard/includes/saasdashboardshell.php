<?php

namespace SSEOAISaaS;

/**
 * SaaS Full-Page Dashboard Shell
 *
 * Replaces the WordPress admin chrome with a branded SaaS experience:
 * a dark brand top bar with a Provider/Agency portal switcher, a grouped
 * Dashicons sidebar, and light/dark themes driven by CSS custom properties.
 * Existing admin pages are loaded inside an iframe within the shell.
 * An exit button returns the user to the standard WordPress admin.
 */
class SaaSDashboardShell
{
    private string $pluginFile;

    /** Grouped sidebar sections: [['label' => string, 'items' => [...]], ...]. */
    private array $menuSections = [];

    /** Memoised sidebar/top-bar counters. */
    private ?int $openTickets = null;
    private ?int $newFeedback = null;

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    public function register(): void
    {
        add_action('admin_head', [$this, 'hideWpChrome']);
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);
        add_action('wp_ajax_sseo_ai_saas_toggle_theme', [$this, 'toggleTheme']);
    }

    /**
     * Add the Fyndable shell body classes to the SaaS admin pages.
     *
     * This scopes the shell/dark styling to this plugin only, mirroring the
     * client plugin's addAdminBodyClass() mechanism.
     */
    public function addAdminBodyClass(string $classes): string
    {
        $screen = get_current_screen();
        if (!$screen) {
            return $classes;
        }

        $screenId = $screen->id;
        $isSaaSPage = strpos($screenId, 'sseo-ai') !== false
            || strpos($screenId, 'agency') !== false;

        if (!$isSaaSPage) {
            return $classes;
        }

        $classes .= ' fyndable-ui';

        // The shell passes the active theme in the URL so newly loaded iframe
        // pages render with the correct theme immediately, before the async
        // option update from the toggle has completed.
        if (isset($_GET['saas_dark'])) {
            $dark = $_GET['saas_dark'] === '1';
        } else {
            $dark = $this->isDarkTheme();
        }

        if ($dark) {
            $classes .= ' fyndable-dark';
        }

        return $classes;
    }

    /**
     * Persist the light/dark theme preference for the current user.
     */
    public function toggleTheme(): void
    {
        check_ajax_referer('sseo_ai_saas_toggle_theme', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('agency_view_dashboard')) {
            wp_send_json_error(['message' => __('Permission denied.', 'sseo-ai-saas')], 403);
        }

        $dark = isset($_POST['dark']) && $_POST['dark'] === '1';
        update_user_meta(get_current_user_id(), 'sseo_ai_saas_dark_theme', $dark ? '1' : '0');
        wp_send_json_success(['dark' => $dark]);
    }

    private function isDarkTheme(): bool
    {
        return get_user_meta(get_current_user_id(), 'sseo_ai_saas_dark_theme', true) === '1';
    }

    /**
     * Whether the current user may use the agency portal.
     */
    private function isAgencyUser(): bool
    {
        $user = wp_get_current_user();

        return $user && in_array('agency_partner', (array)$user->roles, true);
    }

    /**
     * Build the grouped sidebar menu sections for the active portal.
     *
     * @param string $portal 'provider' or 'agency'.
     */
    private function buildMenuSections(string $portal): void
    {
        if ($portal === 'agency') {
            $this->menuSections = [
                [
                    'label' => __('Overview', 'sseo-ai-saas'),
                    'items' => [
                        ['slug' => 'sseo-ai-agency', 'label' => __('Dashboard', 'sseo-ai-saas'), 'icon' => 'dashicons-chart-bar'],
                    ],
                ],
                [
                    'label' => __('Licenses', 'sseo-ai-saas'),
                    'items' => [
                        ['slug' => 'sseo-ai-agency-generate', 'label' => __('Generate Licenses', 'sseo-ai-saas'), 'icon' => 'dashicons-admin-network'],
                        ['slug' => 'sseo-ai-agency-licenses', 'label' => __('All Licenses', 'sseo-ai-saas'), 'icon' => 'dashicons-list-view'],
                        ['slug' => 'sseo-ai-agency-add-licenses', 'label' => __('Extra Licenses', 'sseo-ai-saas'), 'icon' => 'dashicons-plus-alt2'],
                    ],
                ],
                [
                    'label' => __('Customers', 'sseo-ai-saas'),
                    'items' => [
                        ['slug' => 'sseo-ai-agency-tenants', 'label' => __('Tenants', 'sseo-ai-saas'), 'icon' => 'dashicons-admin-multisite'],
                        ['slug' => 'sseo-ai-agency-usage', 'label' => __('Usage & Costs', 'sseo-ai-saas'), 'icon' => 'dashicons-chart-area'],
                    ],
                ],
                [
                    'label' => __('Financial', 'sseo-ai-saas'),
                    'items' => [
                        ['slug' => 'sseo-ai-agency-invoices', 'label' => __('Invoices', 'sseo-ai-saas'), 'icon' => 'dashicons-media-text'],
                        ['slug' => 'sseo-ai-agency-account', 'label' => __('My Account', 'sseo-ai-saas'), 'icon' => 'dashicons-admin-users'],
                    ],
                ],
                [
                    'label' => __('Support', 'sseo-ai-saas'),
                    'items' => [
                        ['slug' => 'sseo-ai-agency-support', 'label' => __('Support', 'sseo-ai-saas'), 'icon' => 'dashicons-sos'],
                        ['slug' => 'sseo-ai-agency-wl', 'label' => __('White-Label', 'sseo-ai-saas'), 'icon' => 'dashicons-art'],
                    ],
                ],
            ];

            return;
        }

        $this->menuSections = [
            [
                'label' => __('Overview', 'sseo-ai-saas'),
                'items' => [
                    ['slug' => 'sseo-ai-licenses', 'label' => __('Dashboard', 'sseo-ai-saas'), 'icon' => 'dashicons-chart-bar'],
                ],
            ],
            [
                'label' => __('Licenses', 'sseo-ai-saas'),
                'items' => [
                    ['slug' => 'sseo-ai-generate-licenses', 'label' => __('Generate Keys', 'sseo-ai-saas'), 'icon' => 'dashicons-admin-network'],
                    ['slug' => 'sseo-ai-view-licenses', 'label' => __('All Licenses', 'sseo-ai-saas'), 'icon' => 'dashicons-list-view'],
                    ['slug' => 'sseo-ai-agency-accounts', 'label' => __('Agency Accounts', 'sseo-ai-saas'), 'icon' => 'dashicons-groups'],
                    ['slug' => 'sseo-ai-client-versions', 'label' => __('Client Versions', 'sseo-ai-saas'), 'icon' => 'dashicons-update'],
                ],
            ],
            [
                'label' => __('Customers', 'sseo-ai-saas'),
                'items' => [
                    ['slug' => 'sseo-ai-tenants', 'label' => __('Tenants', 'sseo-ai-saas'), 'icon' => 'dashicons-admin-multisite'],
                    ['slug' => 'sseo-ai-usage-reports', 'label' => __('Usage Reports', 'sseo-ai-saas'), 'icon' => 'dashicons-chart-area'],
                ],
            ],
            [
                'label' => __('Financial', 'sseo-ai-saas'),
                'items' => [
                    ['slug' => 'sseo-ai-revenue', 'label' => __('Revenue', 'sseo-ai-saas'), 'icon' => 'dashicons-chart-line'],
                    ['slug' => 'sseo-ai-costs', 'label' => __('Cost Dashboard', 'sseo-ai-saas'), 'icon' => 'dashicons-money-alt'],
                    ['slug' => 'sseo-ai-checkout', 'label' => __('Checkout', 'sseo-ai-saas'), 'icon' => 'dashicons-cart'],
                    ['slug' => 'sseo-ai-billing', 'label' => __('Bookkeeping', 'sseo-ai-saas'), 'icon' => 'dashicons-media-spreadsheet'],
                ],
            ],
            [
                'label' => __('Growth & Support', 'sseo-ai-saas'),
                'items' => [
                    ['slug' => 'sseo-ai-support-tickets', 'label' => __('Support', 'sseo-ai-saas'), 'icon' => 'dashicons-sos', 'badge' => $this->countOpenTickets()],
                    ['slug' => 'sseo-ai-feedback', 'label' => __('Feedback', 'sseo-ai-saas'), 'icon' => 'dashicons-format-status', 'badge' => $this->countNewFeedback()],
                    ['slug' => 'sseo-ai-geo-scan', 'label' => __('GEO Scans', 'sseo-ai-saas'), 'icon' => 'dashicons-visibility'],
                    ['slug' => 'sseo-ai-email-templates', 'label' => __('Email Templates', 'sseo-ai-saas'), 'icon' => 'dashicons-email-alt'],
                    ['slug' => 'sseo-ai-chatbot', 'label' => __('Chatbot', 'sseo-ai-saas'), 'icon' => 'dashicons-format-chat'],
                ],
            ],
            [
                'label' => __('System', 'sseo-ai-saas'),
                'items' => [
                    ['slug' => 'sseo-ai-models', 'label' => __('AI Models', 'sseo-ai-saas'), 'icon' => 'dashicons-superhero-alt'],
                    ['slug' => 'sseo-ai-white-label', 'label' => __('White-Label', 'sseo-ai-saas'), 'icon' => 'dashicons-art'],
                    ['slug' => 'sseo-ai-settings', 'label' => __('Settings', 'sseo-ai-saas'), 'icon' => 'dashicons-admin-settings'],
                ],
            ],
        ];
    }

    /**
     * Whether a custom table exists. Keeps the sidebar counters safe on
     * installs that have not run the activation hook for a newer table yet.
     */
    private function tableExists(string $table): bool
    {
        global $wpdb;

        return (bool)$wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $table)
        );
    }

    /**
     * Open support tickets — the red count badge on the Support nav item.
     */
    private function countOpenTickets(): int
    {
        global $wpdb;

        if ($this->openTickets !== null) {
            return $this->openTickets;
        }

        if (!$this->tableExists('sseo_ai_support_tickets')) {
            return $this->openTickets = 0;
        }

        $table = $wpdb->prefix . 'sseo_ai_support_tickets';

        return $this->openTickets = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'open'");
    }

    /**
     * Unhandled feedback — the red count badge on the Feedback nav item.
     */
    private function countNewFeedback(): int
    {
        global $wpdb;

        if ($this->newFeedback !== null) {
            return $this->newFeedback;
        }

        if (!$this->tableExists('sseo_ai_feedback')) {
            return $this->newFeedback = 0;
        }

        $table = $wpdb->prefix . 'sseo_ai_feedback';

        return $this->newFeedback = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'new'");
    }

    /**
     * Sidebar card data: platform status for the provider, quota for an agency.
     *
     * @return array{title:string,value:string,meta:string,percent:int}
     */
    private function getSidebarCard(string $portal): array
    {
        if ($portal === 'agency') {
            $tenants = new TenantRepository();
            $roleManager = new AgencyRoleManager($tenants);
            $account = $roleManager->getAgencyAccount();

            $max = $account ? max((int)$account['max_sub_licenses'], 0) : 0;
            $used = 0;
            if ($account) {
                $generator = new LicenseKeyGenerator($tenants);
                $used = $generator->countLicensesByAgency((int)$account['tenant_id']);
            }

            return [
                'title' => __('License quota', 'sseo-ai-saas'),
                'value' => sprintf('%d / %d', $used, $max),
                'meta' => sprintf(
                    /* translators: %d: number of licenses still available. */
                    __('%d available', 'sseo-ai-saas'),
                    max($max - $used, 0)
                ),
                'percent' => $max > 0 ? (int)round(min($used / $max, 1) * 100) : 0,
            ];
        }

        $tenants = new TenantRepository();
        $total = $tenants->countTenants();
        $active = $tenants->countTenants(['status' => 'active']);

        return [
            'title' => __('Platform status', 'sseo-ai-saas'),
            'value' => sprintf('%d / %d', $active, $total),
            'meta' => __('Active tenants', 'sseo-ai-saas'),
            'percent' => $total > 0 ? (int)round(min($active / $total, 1) * 100) : 0,
        ];
    }

    /**
     * Hide WordPress admin chrome when on SaaS shell pages.
     */
    public function hideWpChrome(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $isShellPage = $screen->id === 'toplevel_page_sseo-ai-shell';
        $isIframePage = isset($_GET['saas_shell']);
        $isSaaSPage = strpos($screen->id, 'sseo-ai') !== false
            || strpos($screen->id, '_page_sseo-ai') !== false
            || strpos($screen->id, 'agency') !== false
            || $isShellPage;

        if (!$isSaaSPage) {
            return;
        }

        if ($isShellPage) {
            echo '<style>
                #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenu,
                #wpfooter, #screen-meta, #screen-meta-links,
                #contextual-help-link-wrap, #screen-options-link-wrap,
                .notice, .notice-success, .notice-error, .notice-warning,
                .update-nag, .updated, .error,
                #wpcontent > .wrap > h1, #wpbody-content > .wrap > h1 {
                    display: none !important;
                }
                #wpcontent, #wpbody, #wpbody-content {
                    margin-left: 0 !important;
                    padding: 0 !important;
                }
                html.wp-toolbar {
                    padding-top: 0 !important;
                    overflow: hidden !important;
                }
                body {
                    overflow: hidden !important;
                }
                #wpcontent > .wrap,
                #wpbody-content > .wrap {
                    margin: 0 !important;
                    padding: 0 !important;
                    max-width: none !important;
                }
            </style>';
        }

        if (!$isShellPage) {
            echo '<script>
            (function() {
                if (window.self !== window.top) {
                    var style = document.createElement("style");
                    style.textContent = "\
                        #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenu,\
                        #adminmenuback, #wpfooter, #screen-meta, #screen-meta-links,\
                        #contextual-help-link-wrap, #screen-options-link-wrap,\
                        .notice, .notice-success, .notice-error, .notice-warning, .notice-info,\
                        .update-nag, .updated, .error, .is-dismissible,\
                        #wp-version-message, .update-plugins, .update-count {\
                            display: none !important;\
                        }\
                        #wpcontent, #wpbody, #wpbody-content {\
                            margin-left: 0 !important;\
                            padding: 0 !important;\
                        }\
                        html.wp-toolbar {\
                            padding-top: 0 !important;\
                        }\
                        .wrap, .wrap.sseo-ai-license-admin {\
                            margin: 0 auto !important;\
                        }\
                        html, body {\
                            overflow-x: hidden !important;\
                        }\
                        *, *::before, *::after {\
                            box-sizing: border-box;\
                        }\
                    ";
                    document.head.appendChild(style);

                    document.addEventListener("click", function(e) {
                        var link = e.target.closest("a");
                        if (!link || !link.href) return;
                        var url = link.href;
                        if (url.indexOf("admin.php") === -1) return;
                        if (url.indexOf("page=sseo-ai") === -1) return;
                        if (url.indexOf("saas_shell") !== -1) return;
                        if (link.target === "_blank") return;
                        e.preventDefault();
                        var sep = url.indexOf("?") !== -1 ? "&" : "?";
                        window.location.href = url + sep + "saas_shell=1";
                    });

                    document.addEventListener("DOMContentLoaded", function() {
                        document.querySelectorAll("form").forEach(function(form) {
                            var formAction = form.getAttribute("action") || "";
                            if (formAction.indexOf("options.php") !== -1) {
                                var referrer = form.querySelector("input[name=_wp_http_referer]");
                                if (referrer && referrer.value.indexOf("saas_shell") === -1) {
                                    var sep = referrer.value.indexOf("?") !== -1 ? "&" : "?";
                                    referrer.value = referrer.value + sep + "saas_shell=1";
                                }
                            }
                        });
                    });
                }
            })();
            </script>';

            if ($isIframePage) {
                echo '<style>
                    #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenu,
                    #adminmenuback, #wpfooter, #screen-meta, #screen-meta-links,
                    #contextual-help-link-wrap, #screen-options-link-wrap,
                    .notice, .notice-success, .notice-error, .notice-warning, .notice-info,
                    .update-nag, .updated, .error, .is-dismissible,
                    #wp-version-message, .update-plugins, .update-count,
                    .auto-fold #adminmenu, .auto-fold #adminmenumain,
                    .auto-fold #adminmenuwrap, .auto-fold #adminmenuback {
                        display: none !important;
                    }
                    #wpcontent, #wpbody, #wpbody-content {
                        margin-left: 0 !important;
                        padding: 0 !important;
                    }
                    html.wp-toolbar {
                        padding-top: 0 !important;
                    }
                    .wrap, .wrap.sseo-ai-license-admin {
                        margin: 0 auto !important;
                    }
                    html, body {
                        overflow-x: hidden !important;
                    }
                    *, *::before, *::after {
                        box-sizing: border-box;
                    }
                </style>';
            }
        }
    }

    /**
     * Render the SaaS dashboard shell.
     */
    public function render(): void
    {
        $isAgencyUser = $this->isAgencyUser();
        $canProvider = current_user_can('manage_options');
        $canAgency = $isAgencyUser;

        // Portal selection: the segmented switch in the top bar. Falls back to
        // whichever portal the current user can actually reach.
        $portal = isset($_GET['saas_portal']) ? sanitize_key($_GET['saas_portal']) : '';
        if ($portal !== 'provider' && $portal !== 'agency') {
            $portal = $isAgencyUser ? 'agency' : 'provider';
        }
        if ($portal === 'agency' && !$canAgency) {
            $portal = 'provider';
        }
        if ($portal === 'provider' && !$canProvider) {
            $portal = 'agency';
        }

        $this->buildMenuSections($portal);

        $wlEnabled = (bool)get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $wlEnabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $companyLogo = $wlEnabled ? get_option('sseo_ai_saas_wl_company_logo', '') : '';
        $companyName = $companyName ?: 'Fyndable';
        $hasCustomBrand = $wlEnabled && (bool)get_option('sseo_ai_saas_wl_company_name', '');
        $primaryColor = $wlEnabled ? (sanitize_hex_color(get_option('sseo_ai_saas_wl_primary_color', '')) ?: '#8F39AC') : '#8F39AC';
        $secondaryColor = $wlEnabled ? (sanitize_hex_color(get_option('sseo_ai_saas_wl_secondary_color', '')) ?: '#379FD3') : '#379FD3';

        $user = wp_get_current_user();
        if ($isAgencyUser) {
            $wl = get_user_meta($user->ID, 'sseo_ai_agency_wl', true);
            if (is_array($wl) && !empty($wl['company_name'])) {
                $companyName = $wl['company_name'];
                $hasCustomBrand = true;
            }
            if (is_array($wl) && !empty($wl['company_logo'])) {
                $companyLogo = $wl['company_logo'];
            }
            if (is_array($wl) && !empty($wl['primary_color'])) {
                $primaryColor = sanitize_hex_color($wl['primary_color']) ?: $primaryColor;
            }
            if (is_array($wl) && !empty($wl['secondary_color'])) {
                $secondaryColor = sanitize_hex_color($wl['secondary_color']) ?: $secondaryColor;
            }
        }

        // The brand top bar is the dark Fyndable gradient. A white-labelled
        // portal keeps its own colours.
        $accentGradient = sprintf('linear-gradient(135deg, %s 0%%, %s 100%%)', $primaryColor, $secondaryColor);
        $topbarGradient = $hasCustomBrand ? $accentGradient : 'linear-gradient(135deg, #2A1533 0%, #132A38 100%)';

        $darkTheme = $this->isDarkTheme();

        $defaultPage = $portal === 'agency' ? 'sseo-ai-agency' : 'sseo-ai-licenses';
        $currentPage = isset($_GET['saas_page']) ? sanitize_key($_GET['saas_page']) : $defaultPage;

        $iframeUrl = admin_url('admin.php');
        $iframeUrl = add_query_arg('page', $currentPage, $iframeUrl);
        $iframeUrl = add_query_arg('saas_shell', '1', $iframeUrl);
        $iframeUrl = add_query_arg('saas_dark', $darkTheme ? '1' : '0', $iframeUrl);

        $passThroughParams = ['view', 'ticket_id', 'tenant_key', 'tenant_id', 'month', 'period'];
        foreach ($passThroughParams as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $iframeUrl = add_query_arg($param, sanitize_text_field($_GET[$param]), $iframeUrl);
            }
        }

        $exitUrl = admin_url('admin.php?page=' . $currentPage);
        $logoutUrl = wp_logout_url(home_url('/'));

        $portalLabel = $portal === 'agency'
            ? __('Agency Portal', 'sseo-ai-saas')
            : __('SaaS Provider', 'sseo-ai-saas');

        $initials = '';
        $roleLabel = $portal === 'agency' ? __('Agency', 'sseo-ai-saas') : __('Provider', 'sseo-ai-saas');
        if ($user->ID) {
            $fn = $user->first_name ?: '';
            $ln = $user->last_name ?: '';
            if ($fn && $ln) {
                $initials = mb_strtoupper(mb_substr($fn, 0, 1) . mb_substr($ln, 0, 1));
            } else {
                $initials = mb_strtoupper(mb_substr($user->display_name, 0, 2));
            }
        }

        $sidebarCard = $this->getSidebarCard($portal);
        $pluginUrl = dirname(plugin_dir_url(__FILE__));
        $switchBase = admin_url('admin.php?page=sseo-ai-shell');
        ?>
        <style>
            /* ======= SHELL LAYOUT ======= */
            .fyn-shell {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                z-index: 999999;
                display: flex;
                flex-direction: column;
                font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
                background: var(--shell-page, #f4f5f8);
            }

            /* ======= TOP BAR ======= */
            .fyn-topbar {
                height: 58px;
                background: <?php echo $topbarGradient; ?>;
                display: flex;
                align-items: center;
                padding: 0 22px;
                box-shadow: 0 1px 0 rgba(0,0,0,.2);
                flex-shrink: 0;
                z-index: 200;
                gap: 16px;
            }
            .fyn-topbar-brand {
                display: flex;
                align-items: center;
                gap: 0;
                flex-shrink: 0;
            }
            .fyn-topbar-logo {
                height: 26px;
                max-width: 170px;
                display: block;
            }
            .fyn-topbar-logo-text {
                font-size: 18px;
                font-weight: 700;
                color: #fff;
                letter-spacing: -.3px;
            }
            .fyn-topbar-divider {
                width: 1px;
                height: 24px;
                background: rgba(255,255,255,.2);
                margin: 0 12px;
                flex-shrink: 0;
            }
            .fyn-topbar-micro {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: rgba(255,255,255,.55);
                white-space: nowrap;
            }

            /* Provider / Agency segmented switch */
            .fyn-portal-switch {
                display: inline-flex;
                align-items: center;
                gap: 2px;
                padding: 3px;
                border-radius: 10px;
                background: rgba(255,255,255,.1);
                border: 1px solid rgba(255,255,255,.12);
                flex-shrink: 0;
            }
            .fyn-portal-opt {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                height: 28px;
                padding: 0 13px;
                border-radius: 8px;
                font-size: 12.5px;
                font-weight: 600;
                color: rgba(255,255,255,.7);
                text-decoration: none;
                transition: all .2s ease;
                white-space: nowrap;
            }
            .fyn-portal-opt:hover {
                background: rgba(255,255,255,.12);
                color: #fff;
            }
            .fyn-portal-opt.active {
                background: <?php echo $accentGradient; ?>;
                color: #fff;
                box-shadow: 0 3px 10px rgba(143,57,172,.35);
            }
            .fyn-portal-opt.disabled {
                opacity: .4;
                pointer-events: none;
            }
            .fyn-portal-opt .dashicons {
                font-size: 15px;
                width: 15px;
                height: 15px;
            }

            .fyn-topbar-search {
                display: flex;
                align-items: center;
                max-width: 420px;
                flex: 1;
                height: 36px;
                background: rgba(255,255,255,.1);
                border: 1px solid rgba(255,255,255,.12);
                border-radius: 9px;
                padding: 0 12px;
                gap: 8px;
            }
            .fyn-topbar-search .dashicons {
                color: rgba(255,255,255,.45);
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .fyn-topbar-search input {
                background: transparent;
                border: none;
                color: #fff;
                font-size: 13px;
                font-family: inherit;
                flex: 1;
                outline: none;
                padding: 0;
            }
            .fyn-topbar-search input::placeholder { color: rgba(255,255,255,.4); }
            .fyn-topbar-search-hint {
                font-size: 10px;
                font-weight: 600;
                color: rgba(255,255,255,.35);
                background: rgba(255,255,255,.1);
                border-radius: 5px;
                padding: 2px 7px;
                letter-spacing: .5px;
                flex-shrink: 0;
            }
            .fyn-topbar-spacer { flex: 1; }
            .fyn-topbar-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            .fyn-topbar-iconbtn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 34px;
                height: 34px;
                border-radius: 9px;
                background: rgba(255,255,255,.1);
                border: 1px solid rgba(255,255,255,.12);
                color: rgba(255,255,255,.85);
                cursor: pointer;
                transition: background .15s;
                position: relative;
                text-decoration: none;
            }
            .fyn-topbar-iconbtn:hover { background: rgba(255,255,255,.2); color: #fff; }
            .fyn-topbar-iconbtn .dashicons { font-size: 17px; width: 17px; height: 17px; }
            .fyn-topbar-dot {
                position: absolute;
                top: 5px;
                right: 5px;
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background: #ef4444;
            }
            .fyn-topbar-theme {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                height: 34px;
                padding: 0 10px;
                border-radius: 9px;
                background: rgba(255,255,255,.1);
                border: 1px solid rgba(255,255,255,.12);
                color: rgba(255,255,255,.85);
                cursor: pointer;
                transition: background .15s;
                font-size: 15px;
            }
            .fyn-topbar-theme:hover { background: rgba(255,255,255,.2); }
            .fyn-topbar-theme-track {
                position: relative;
                width: 30px;
                height: 16px;
                border-radius: 999px;
                background: rgba(255,255,255,.35);
            }
            .fyn-topbar-theme-track::after {
                content: '';
                position: absolute;
                top: 2px; left: 2px;
                width: 12px; height: 12px;
                border-radius: 50%;
                background: #fff;
                transition: transform .2s ease;
            }
            .fyn-topbar-theme[aria-pressed="true"] .fyn-topbar-theme-track::after {
                transform: translateX(14px);
            }
            .fyn-topbar-account {
                display: flex;
                align-items: center;
                gap: 9px;
                flex-shrink: 0;
            }
            .fyn-topbar-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: <?php echo $accentGradient; ?>;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 700;
                color: #fff;
                letter-spacing: .5px;
                flex-shrink: 0;
            }
            .fyn-topbar-account-name {
                font-size: 12.5px;
                font-weight: 600;
                color: #fff;
                line-height: 1.25;
                white-space: nowrap;
            }
            .fyn-topbar-account-role {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: rgba(255,255,255,.5);
                line-height: 1.25;
            }
            .fyn-topbar-exit {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                height: 34px;
                padding: 0 14px;
                border-radius: 9px;
                background: rgba(255,255,255,.1);
                border: 1px solid rgba(255,255,255,.12);
                color: rgba(255,255,255,.85);
                font-size: 13px;
                font-weight: 500;
                font-family: inherit;
                cursor: pointer;
                text-decoration: none;
                transition: background .15s;
            }
            .fyn-topbar-exit:hover { background: rgba(255,255,255,.2); color: #fff; }
            .fyn-topbar-exit .fyn-exit-x {
                font-size: 17px;
                line-height: 1;
                font-weight: 300;
            }

            /* ======= BODY (sidebar + main) ======= */
            .fyn-shell-body {
                display: flex;
                flex: 1;
                min-height: 0;
            }

            /* ======= SIDEBAR ======= */
            .fyn-sidebar {
                width: 252px;
                background: var(--shell-sidebar, #fff);
                border-right: 1px solid var(--shell-sb-border, #e9eaef);
                display: flex;
                flex-direction: column;
                overflow-y: auto;
                flex-shrink: 0;
                padding: 16px 14px 28px;
            }
            .fyn-sidebar-nav {
                list-style: none;
                margin: 0;
                padding: 0;
                flex: 1;
            }
            .fyn-nav-section {
                font-size: 10.5px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .07em;
                color: var(--nav-section, #9a95a8);
                padding: 14px 10px 6px;
                margin: 0;
            }
            .fyn-nav-section:first-child {
                padding-top: 0;
            }
            .fyn-nav-item {
                margin: 1px 0;
            }
            .fyn-nav-item a {
                display: flex;
                align-items: center;
                gap: 11px;
                padding: 9px 11px;
                border-radius: 9px;
                font-size: 13.5px;
                font-weight: 600;
                color: var(--nav-fg, #5a5568);
                text-decoration: none;
                transition: all .2s ease;
                cursor: pointer;
            }
            .fyn-nav-item a .dashicons {
                font-size: 17px;
                width: 17px;
                height: 17px;
                flex-shrink: 0;
            }
            .fyn-nav-item a:hover {
                background: var(--nav-hover, #f5f2fb);
                color: var(--text-h, #1a1430);
            }
            .fyn-nav-item a.active {
                background: <?php echo $accentGradient; ?>;
                color: #fff;
                box-shadow: 0 5px 14px rgba(143,57,172,.32);
            }
            .fyn-nav-badge {
                margin-left: auto;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 20px;
                height: 20px;
                padding: 0 6px;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                font-size: 11px;
                font-weight: 700;
            }
            .fyn-nav-item a.active .fyn-nav-badge {
                background: rgba(255,255,255,.25);
            }

            /* Plan / quota card below the nav */
            .fyn-plan-card {
                margin-top: 16px;
                padding: 14px;
                border-radius: 11px;
                background: linear-gradient(135deg, rgba(143,57,172,.08), rgba(55,159,211,.08));
                border: 1px solid var(--card-border, #ececf1);
            }
            .fyn-plan-card-title {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--text-m, #746e82);
                margin-bottom: 6px;
            }
            .fyn-plan-card-tier {
                font-size: 14px;
                font-weight: 700;
                color: var(--text-h, #1a1430);
                margin-bottom: 10px;
            }
            .fyn-plan-progress {
                height: 6px;
                background: var(--sev-track, #e9e7f0);
                border-radius: 999px;
                overflow: hidden;
            }
            .fyn-plan-progress-bar {
                height: 100%;
                border-radius: 999px;
                background: linear-gradient(90deg, #8F39AC, #379FD3);
                transition: width .3s ease;
            }
            .fyn-plan-card-meta {
                font-size: 11.5px;
                color: var(--text-m, #746e82);
                margin-top: 7px;
            }
            .fyn-sidebar-version {
                font-size: 10.5px;
                color: var(--text-s, #a39dae);
                text-align: center;
                margin-top: 14px;
            }

            /* ======= CONTENT (iframe) ======= */
            .fyn-content {
                flex: 1;
                overflow: hidden;
                position: relative;
                background: var(--shell-page, #f4f5f8);
            }
            .fyn-content iframe {
                width: 100%;
                height: 100%;
                border: none;
                display: block;
            }
            .fyn-loading {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: var(--shell-page, #f4f5f8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 50;
                transition: opacity .3s;
            }
            .fyn-loading.hidden {
                opacity: 0;
                pointer-events: none;
            }
            .fyn-spinner {
                width: 36px;
                height: 36px;
                border: 3px solid var(--divide, #efeef4);
                border-top-color: #8F39AC;
                border-radius: 50%;
                animation: fyn-spin .8s linear infinite;
            }
            @keyframes fyn-spin {
                to { transform: rotate(360deg); }
            }

            /* ======= RESPONSIVE ======= */
            @media (max-width: 1100px) {
                .fyn-topbar-search { display: none; }
            }
            @media (max-width: 782px) {
                .fyn-sidebar {
                    width: 60px;
                    padding: 12px 6px;
                }
                .fyn-nav-item a span:not(.dashicons):not(.fyn-nav-badge) {
                    display: none;
                }
                .fyn-nav-item a {
                    justify-content: center;
                    padding: 10px;
                }
                .fyn-nav-section { display: none; }
                .fyn-nav-badge { display: none; }
                .fyn-plan-card { display: none; }
                .fyn-sidebar-version { display: none; }
                .fyn-topbar-micro { display: none; }
                .fyn-topbar-divider { display: none; }
                .fyn-topbar-account-text { display: none; }
                .fyn-portal-opt span:not(.dashicons) { display: none; }
                .fyn-portal-opt { padding: 0 9px; }
            }
        </style>
        <div class="fyn-shell">
            <!-- Top bar -->
            <div class="fyn-topbar">
                <div class="fyn-topbar-brand">
                    <?php if ($companyLogo): ?>
                        <img src="<?php echo esc_url($companyLogo); ?>" alt="<?php echo esc_attr($companyName); ?>" class="fyn-topbar-logo">
                    <?php else: ?>
                        <img src="<?php echo esc_url($pluginUrl . '/assets/FYN-LOGO-DIA-LONG300ppi.png'); ?>" alt="<?php echo esc_attr($companyName); ?>" class="fyn-topbar-logo">
                    <?php endif; ?>
                    <span class="fyn-topbar-divider"></span>
                    <span class="fyn-topbar-micro"><?php echo esc_html($portalLabel); ?></span>
                </div>

                <div class="fyn-portal-switch" role="group" aria-label="<?php esc_attr_e('Portal', 'sseo-ai-saas'); ?>">
                    <a href="<?php echo esc_url(add_query_arg('saas_portal', 'provider', $switchBase)); ?>"
                       class="fyn-portal-opt <?php echo $portal === 'provider' ? 'active' : ''; ?> <?php echo $canProvider ? '' : 'disabled'; ?>"
                       <?php echo $canProvider ? '' : 'aria-disabled="true" title="' . esc_attr__('Provider console requires administrator access.', 'sseo-ai-saas') . '"'; ?>>
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <span><?php esc_html_e('Provider', 'sseo-ai-saas'); ?></span>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('saas_portal', 'agency', $switchBase)); ?>"
                       class="fyn-portal-opt <?php echo $portal === 'agency' ? 'active' : ''; ?> <?php echo $canAgency ? '' : 'disabled'; ?>"
                       <?php echo $canAgency ? '' : 'aria-disabled="true" title="' . esc_attr__('Agency portal is available to agency accounts.', 'sseo-ai-saas') . '"'; ?>>
                        <span class="dashicons dashicons-businessperson"></span>
                        <span><?php esc_html_e('Agency', 'sseo-ai-saas'); ?></span>
                    </a>
                </div>

                <div class="fyn-topbar-search">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="fyn-global-search" placeholder="<?php esc_attr_e('Search...', 'sseo-ai-saas'); ?>">
                    <span class="fyn-topbar-search-hint">&#8984;K</span>
                </div>

                <span class="fyn-topbar-spacer"></span>

                <div class="fyn-topbar-actions">
                    <button type="button" id="fyn-theme-toggle" class="fyn-topbar-theme" aria-pressed="<?php echo $darkTheme ? 'true' : 'false'; ?>" title="<?php esc_attr_e('Toggle light/dark theme', 'sseo-ai-saas'); ?>">
                        <span aria-hidden="true">&#9788;</span>
                        <span class="fyn-topbar-theme-track" aria-hidden="true"></span>
                        <span aria-hidden="true">&#9790;</span>
                    </button>
                    <button type="button" class="fyn-topbar-iconbtn" title="<?php esc_attr_e('Notifications', 'sseo-ai-saas'); ?>">
                        <span class="dashicons dashicons-bell"></span>
                        <?php if ($portal === 'provider' && ($this->countOpenTickets() > 0 || $this->countNewFeedback() > 0)): ?>
                            <span class="fyn-topbar-dot"></span>
                        <?php endif; ?>
                    </button>
                    <span class="fyn-topbar-divider"></span>
                    <div class="fyn-topbar-account">
                        <span class="fyn-topbar-avatar"><?php echo esc_html($initials); ?></span>
                        <span class="fyn-topbar-account-text">
                            <span class="fyn-topbar-account-name"><?php echo esc_html($user->display_name); ?></span><br>
                            <span class="fyn-topbar-account-role"><?php echo esc_html($roleLabel); ?></span>
                        </span>
                    </div>
                    <?php if ($isAgencyUser): ?>
                        <a href="<?php echo esc_url($logoutUrl); ?>" class="fyn-topbar-exit" title="<?php esc_attr_e('Logout', 'sseo-ai-saas'); ?>">
                            <span><?php esc_html_e('Logout', 'sseo-ai-saas'); ?></span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($exitUrl); ?>" class="fyn-topbar-exit" title="<?php esc_attr_e('Exit to WordPress', 'sseo-ai-saas'); ?>">
                            <span class="fyn-exit-x">&times;</span>
                            <span><?php esc_html_e('Exit', 'sseo-ai-saas'); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Body: sidebar + content -->
            <div class="fyn-shell-body">
                <nav class="fyn-sidebar">
                    <ul class="fyn-sidebar-nav">
                        <?php foreach ($this->menuSections as $section): ?>
                            <li class="fyn-nav-section"><?php echo esc_html($section['label']); ?></li>
                            <?php foreach ($section['items'] as $item): ?>
                                <?php $isActive = $item['slug'] === $currentPage; ?>
                                <li class="fyn-nav-item">
                                    <a href="#" data-slug="<?php echo esc_attr($item['slug']); ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                                        <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                                        <span><?php echo esc_html($item['label']); ?></span>
                                        <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                                            <span class="fyn-nav-badge"><?php echo (int)$item['badge']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                    <div class="fyn-plan-card">
                        <div class="fyn-plan-card-title"><?php echo esc_html($sidebarCard['title']); ?></div>
                        <div class="fyn-plan-card-tier"><?php echo esc_html($sidebarCard['value']); ?></div>
                        <div class="fyn-plan-progress">
                            <div class="fyn-plan-progress-bar" style="width:<?php echo (int)$sidebarCard['percent']; ?>%"></div>
                        </div>
                        <div class="fyn-plan-card-meta"><?php echo esc_html($sidebarCard['meta']); ?></div>
                    </div>
                    <div class="fyn-sidebar-version">
                        <?php echo esc_html($companyName); ?> SaaS v<?php echo esc_html(SSEO_AI_SAAS_VERSION); ?>
                    </div>
                </nav>

                <div class="fyn-content">
                    <div class="fyn-loading" id="fyn-loading">
                        <div class="fyn-spinner"></div>
                    </div>
                    <iframe id="fyn-frame" src="<?php echo esc_url($iframeUrl); ?>"></iframe>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var iframe = document.getElementById('fyn-frame');
            var loading = document.getElementById('fyn-loading');
            var navLinks = document.querySelectorAll('.fyn-nav-item a');
            var themeToggle = document.getElementById('fyn-theme-toggle');
            var adminUrl = '<?php echo esc_js(admin_url('admin.php')); ?>';
            var portal = '<?php echo esc_js($portal); ?>';
            var themeKey = 'fyndableSaasDarkTheme';
            var pageKey = 'fyndableSaasPage_' + portal;

            var storedDark = localStorage.getItem(themeKey);
            var isDark = storedDark !== null
                ? storedDark === '1'
                : themeToggle.getAttribute('aria-pressed') === 'true';

            function applyTheme(dark) {
                themeToggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
                document.body.classList.toggle('fyndable-dark', dark);
                try {
                    if (iframe.contentDocument && iframe.contentDocument.body) {
                        iframe.contentDocument.body.classList.toggle('fyndable-dark', dark);
                    }
                } catch (e) {}
            }

            function frameUrl(slug) {
                return adminUrl +
                    '?page=' + encodeURIComponent(slug) +
                    '&saas_shell=1' +
                    '&saas_dark=' + (isDark ? '1' : '0');
            }

            function setActive(slug) {
                navLinks.forEach(function(l) {
                    l.classList.toggle('active', l.getAttribute('data-slug') === slug);
                });
            }

            function go(slug) {
                if (!slug) return;
                setActive(slug);
                loading.classList.remove('hidden');
                iframe.src = frameUrl(slug);
                // Remember the last screen per portal.
                try { localStorage.setItem(pageKey, slug); } catch (e) {}
                window.location.hash = slug;
            }

            applyTheme(isDark);

            iframe.addEventListener('load', function() {
                loading.classList.add('hidden');
                applyTheme(isDark);
            });

            navLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    go(link.getAttribute('data-slug'));
                });
            });

            themeToggle.addEventListener('click', function() {
                isDark = !isDark;
                try { localStorage.setItem(themeKey, isDark ? '1' : '0'); } catch (e) {}
                applyTheme(isDark);

                var data = new FormData();
                data.append('action', 'sseo_ai_saas_toggle_theme');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('sseo_ai_saas_toggle_theme')); ?>');
                data.append('dark', isDark ? '1' : '0');
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                }).catch(function() {});
            });

            document.addEventListener('keydown', function(e) {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    var search = document.getElementById('fyn-global-search');
                    if (search) {
                        e.preventDefault();
                        search.focus();
                    }
                }
            });

            // Restore the screen from the hash, or the last screen for this portal.
            var known = Array.prototype.map.call(navLinks, function(l) {
                return l.getAttribute('data-slug');
            });
            var hash = window.location.hash.replace('#', '');
            var restore = '';
            if (hash && known.indexOf(hash) !== -1) {
                restore = hash;
            } else if (!hash) {
                try {
                    var last = localStorage.getItem(pageKey);
                    if (last && known.indexOf(last) !== -1) {
                        restore = last;
                    }
                } catch (e) {}
            }
            if (restore && restore !== '<?php echo esc_js($currentPage); ?>') {
                go(restore);
            }
        })();
        </script>
        <?php
    }
}

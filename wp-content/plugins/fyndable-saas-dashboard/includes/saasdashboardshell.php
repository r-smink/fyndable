<?php

namespace SSEOAISaaS;

/**
 * SaaS Full-Page Dashboard Shell
 *
 * Replaces the WordPress admin chrome with a branded SaaS experience.
 * Existing admin pages are loaded inside an iframe within the shell.
 * An exit button (×) returns the user to the standard WordPress admin.
 */
class SaaSDashboardShell
{
    private string $pluginFile;
    private array $menuItems = [];

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    public function register(): void
    {
        add_action('admin_head', [$this, 'hideWpChrome']);
    }

    /**
     * Build the sidebar menu items for the SaaS dashboard.
     * Agency partners get a scoped menu set.
     */
    private function buildMenuItems(): void
    {
        $user = wp_get_current_user();
        $isAgency = $user && in_array('agency_partner', (array)$user->roles, true);

        if ($isAgency) {
            $this->menuItems = [
                [
                    'slug' => 'sseo-ai-agency',
                    'label' => __('Dashboard', 'sseo-ai-saas'),
                    'icon' => '&#128202;',
                ],
                [
                    'slug' => 'sseo-ai-agency-generate',
                    'label' => __('Generate Licenses', 'sseo-ai-saas'),
                    'icon' => '&#128273;',
                ],
                [
                    'slug' => 'sseo-ai-agency-licenses',
                    'label' => __('All Licenses', 'sseo-ai-saas'),
                    'icon' => '&#128203;',
                ],
                [
                    'slug' => 'sseo-ai-agency-tenants',
                    'label' => __('Tenants', 'sseo-ai-saas'),
                    'icon' => '&#127970;',
                ],
                [
                    'slug' => 'sseo-ai-agency-support',
                    'label' => __('Support', 'sseo-ai-saas'),
                    'icon' => '&#128172;',
                ],
            ];
            return;
        }

        $this->menuItems = [
            [
                'slug' => 'sseo-ai-licenses',
                'label' => __('Dashboard', 'sseo-ai-saas'),
                'icon' => '&#128202;',
            ],
            [
                'slug' => 'sseo-ai-generate-licenses',
                'label' => __('Generate Keys', 'sseo-ai-saas'),
                'icon' => '&#128273;',
            ],
            [
                'slug' => 'sseo-ai-view-licenses',
                'label' => __('All Licenses', 'sseo-ai-saas'),
                'icon' => '&#128203;',
            ],
            [
                'slug' => 'sseo-ai-tenants',
                'label' => __('Tenants', 'sseo-ai-saas'),
                'icon' => '&#127970;',
            ],
            [
                'slug' => 'sseo-ai-usage-reports',
                'label' => __('Usage Reports', 'sseo-ai-saas'),
                'icon' => '&#128200;',
            ],
            [
                'slug' => 'sseo-ai-client-portal',
                'label' => __('Client Portal', 'sseo-ai-saas'),
                'icon' => '&#128241;',
            ],
            [
                'slug' => 'sseo-ai-team',
                'label' => __('Team', 'sseo-ai-saas'),
                'icon' => '&#128101;',
            ],
            [
                'slug' => 'sseo-ai-billing',
                'label' => __('Billing', 'sseo-ai-saas'),
                'icon' => '&#128179;',
            ],
            [
                'slug' => 'sseo-ai-support-tickets',
                'label' => __('Support', 'sseo-ai-saas'),
                'icon' => '&#128172;',
            ],
            [
                'slug' => 'sseo-ai-costs',
                'label' => __('Cost Dashboard', 'sseo-ai-saas'),
                'icon' => '&#128176;',
            ],
            [
                'slug' => 'sseo-ai-google-costs',
                'label' => __('Google Costs', 'sseo-ai-saas'),
                'icon' => '&#127907;',
            ],
            [
                'slug' => 'sseo-ai-settings',
                'label' => __('Settings', 'sseo-ai-saas'),
                'icon' => '&#9881;',
            ],
            [
                'slug' => 'sseo-ai-agency-accounts',
                'label' => __('Agency Accounts', 'sseo-ai-saas'),
                'icon' => '&#127970;',
            ],
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
                            margin: 0 !important;\
                            padding: 0 !important;\
                        }\
                        .sseo-ai-license-admin .sseo-ai-card,\
                        .sseo-ai-license-admin form,\
                        .sseo-ai-license-admin .sseo-ai-stats-grid,\
                        .sseo-ai-license-admin .sseo-ai-grid-2,\
                        .sseo-ai-license-admin .sseo-ai-grid-3 {\
                            margin: 16px 20px !important;\
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
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    .sseo-ai-license-admin .sseo-ai-card,
                    .sseo-ai-license-admin form,
                    .sseo-ai-license-admin .sseo-ai-stats-grid,
                    .sseo-ai-license-admin .sseo-ai-grid-2,
                    .sseo-ai-license-admin .sseo-ai-grid-3 {
                        margin: 16px 20px !important;
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
        $this->buildMenuItems();

        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $companyLogo = $enabled ? get_option('sseo_ai_saas_wl_company_logo', '') : '';
        $companyName = $companyName ?: 'Fyndable';

        $user = wp_get_current_user();
        $isAgency = $user && in_array('agency_partner', (array)$user->roles, true);
        $defaultPage = $isAgency ? 'sseo-ai-agency' : 'sseo-ai-licenses';
        $currentPage = isset($_GET['saas_page']) ? sanitize_key($_GET['saas_page']) : $defaultPage;

        $iframeUrl = admin_url('admin.php');
        $iframeUrl = add_query_arg('page', $currentPage, $iframeUrl);
        $iframeUrl = add_query_arg('saas_shell', '1', $iframeUrl);

        $passThroughParams = ['view', 'ticket_id', 'tenant_key', 'tenant_id', 'month'];
        foreach ($passThroughParams as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $iframeUrl = add_query_arg($param, sanitize_text_field($_GET[$param]), $iframeUrl);
            }
        }

        $exitUrl = admin_url('admin.php?page=' . $currentPage);
        ?>
        <style>
            .saas-shell-wrap {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                z-index: 999999;
                background: #f0f2f5;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            }

            .saas-topbar {
                height: 56px;
                background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%);
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                position: relative;
                z-index: 100;
                flex-shrink: 0;
            }
            .saas-topbar-brand {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #fff;
            }
            .saas-topbar-logo {
                font-size: 20px;
                font-weight: 700;
                letter-spacing: -0.3px;
            }
            .saas-topbar-logo span { font-weight: 400; opacity: 0.9; }
            .saas-topbar-logo .saas-saas-suffix {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                opacity: 0.85;
                margin-left: 6px;
                vertical-align: middle;
                background: rgba(255,255,255,0.2);
                padding: 2px 8px;
                border-radius: 20px;
            }
            .saas-topbar-badge {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 3px 10px;
                border-radius: 20px;
                background: rgba(255,255,255,0.2);
                color: #fff;
            }
            .saas-topbar-actions {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .saas-exit-btn {
                display: flex;
                align-items: center;
                gap: 6px;
                background: rgba(255,255,255,0.15);
                color: #fff;
                border: 1px solid rgba(255,255,255,0.3);
                border-radius: 8px;
                padding: 6px 14px;
                font-size: 13px;
                font-weight: 500;
                text-decoration: none;
                cursor: pointer;
                transition: background 0.15s;
            }
            .saas-exit-btn:hover {
                background: rgba(255,255,255,0.25);
                color: #fff;
            }
            .saas-exit-x {
                font-size: 18px;
                line-height: 1;
                font-weight: 300;
            }

            .saas-shell-body {
                display: flex;
                height: calc(100vh - 56px);
            }

            .saas-sidebar {
                width: 240px;
                background: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
                overflow-y: auto;
                flex-shrink: 0;
            }
            .saas-sidebar-nav {
                list-style: none;
                padding: 12px 0;
                flex: 1;
                margin: 0;
            }
            .saas-sidebar-nav li {
                margin: 2px 8px;
            }
            .saas-sidebar-nav a {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 14px;
                text-decoration: none;
                color: #374151;
                font-size: 13px;
                font-weight: 500;
                border-radius: 8px;
                transition: all 0.15s;
                cursor: pointer;
            }
            .saas-sidebar-nav a:hover {
                background: #f3f4f6;
                color: #1e40af;
            }
            .saas-sidebar-nav a.active {
                background: linear-gradient(135deg, #3b82f6 0%, #ec4899 100%);
                color: #fff;
                font-weight: 600;
                box-shadow: 0 2px 6px rgba(59,130,246,0.3);
            }
            .saas-sidebar-nav .saas-nav-icon {
                font-size: 16px;
                width: 20px;
                text-align: center;
                flex-shrink: 0;
            }
            .saas-sidebar-footer {
                padding: 16px;
                border-top: 1px solid #e5e7eb;
                font-size: 11px;
                color: #9ca3af;
                text-align: center;
            }

            .saas-content {
                flex: 1;
                overflow: hidden;
                position: relative;
            }
            .saas-content iframe {
                width: 100%;
                height: 100%;
                border: none;
                display: block;
            }

            .saas-loading {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: #f0f2f5;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 50;
                transition: opacity 0.3s;
            }
            .saas-loading.hidden {
                opacity: 0;
                pointer-events: none;
            }
            .saas-spinner {
                width: 36px;
                height: 36px;
                border: 3px solid #e5e7eb;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: saas-spin 0.8s linear infinite;
            }
            @keyframes saas-spin {
                to { transform: rotate(360deg); }
            }

            @media (max-width: 768px) {
                .saas-sidebar {
                    width: 60px;
                }
                .saas-sidebar-nav a {
                    justify-content: center;
                    padding: 12px;
                }
                .saas-sidebar-nav a span:not(.saas-nav-icon) {
                    display: none;
                }
                .saas-sidebar-footer { display: none; }
                .saas-topbar-badge { display: none; }
            }
        </style>
        <div class="saas-shell-wrap">
            <div class="saas-topbar">
                <div class="saas-topbar-brand">
                    <div class="saas-topbar-logo">
                        <?php if ($companyLogo): ?>
                            <img src="<?php echo esc_url($companyLogo); ?>" alt="<?php echo esc_attr($companyName . ' Smart SEO'); ?>" style="max-height: 36px; max-width: 180px; display: block;">
                        <?php else: ?>
                            <?php echo esc_html($companyName); ?> <span>Smart SEO</span><span class="saas-saas-suffix">SaaS</span>
                        <?php endif; ?>
                    </div>
                    <div class="saas-topbar-badge"><?php esc_html_e('Dashboard', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="saas-topbar-actions">
                    <a href="<?php echo esc_url($exitUrl); ?>" class="saas-exit-btn" title="<?php esc_attr_e('Exit to WordPress', 'sseo-ai-saas'); ?>">
                        <span class="saas-exit-x">&times;</span>
                        <span><?php esc_html_e('Exit', 'sseo-ai-saas'); ?></span>
                    </a>
                </div>
            </div>

            <div class="saas-shell-body">
                <nav class="saas-sidebar">
                    <ul class="saas-sidebar-nav">
                        <?php foreach ($this->menuItems as $item): ?>
                            <?php $isActive = $item['slug'] === $currentPage; ?>
                            <li>
                                <a href="#" data-slug="<?php echo esc_attr($item['slug']); ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                                    <span class="saas-nav-icon"><?php echo $item['icon']; ?></span>
                                    <span><?php echo esc_html($item['label']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="saas-sidebar-footer">
                        <?php echo esc_html($companyName); ?> SaaS v<?php echo esc_html(SSEO_AI_SAAS_VERSION); ?>
                    </div>
                </nav>

                <div class="saas-content">
                    <div class="saas-loading" id="saas-loading">
                        <div class="saas-spinner"></div>
                    </div>
                    <iframe id="saas-frame" src="<?php echo esc_url($iframeUrl); ?>"></iframe>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var iframe = document.getElementById('saas-frame');
            var loading = document.getElementById('saas-loading');
            var navLinks = document.querySelectorAll('.saas-sidebar-nav a');

            iframe.addEventListener('load', function() {
                loading.classList.add('hidden');
            });

            navLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var slug = link.getAttribute('data-slug');
                    if (!slug) return;

                    navLinks.forEach(function(l) { l.classList.remove('active'); });
                    link.classList.add('active');

                    loading.classList.remove('hidden');
                    var url = '<?php echo esc_js(admin_url('admin.php')); ?>' +
                        '?page=' + encodeURIComponent(slug) +
                        '&saas_shell=1';
                    iframe.src = url;

                    window.location.hash = slug;
                });
            });

            var hash = window.location.hash.replace('#', '');
            if (hash) {
                navLinks.forEach(function(link) {
                    if (link.getAttribute('data-slug') === hash) {
                        navLinks.forEach(function(l) { l.classList.remove('active'); });
                        link.classList.add('active');
                        loading.classList.remove('hidden');
                        iframe.src = '<?php echo esc_js(admin_url('admin.php')); ?>' +
                            '?page=' + encodeURIComponent(hash) +
                            '&saas_shell=1';
                    }
                });
            }
        })();
        </script>
        <?php
    }
}

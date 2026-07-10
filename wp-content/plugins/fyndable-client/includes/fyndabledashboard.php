<?php

namespace SSEOAIClient;

/**
 * Fyndable Full-Page Dashboard Shell
 *
 * Replaces the WordPress admin chrome with a branded Fyndable experience.
 * Existing admin pages are loaded inside an iframe within the shell.
 * An exit button (×) returns the user to the standard WordPress admin.
 */
class FyndableDashboard
{
    private Client $client;
    private array $menuItems = [];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function register(): void
    {
        add_action('admin_head', [$this, 'hideWpChrome']);
    }

    /**
     * Build the sidebar menu items based on license tier.
     */
    private function buildMenuItems(): void
    {
        $isLicenseValid = $this->client->licenseValidator->isLicenseValid();
        $tier = $this->client->licenseValidator->getLicenseTier();

        $this->menuItems = [
            [
                'slug' => 'ai-seo-client',
                'label' => __('Connection', 'ai-seo-client'),
                'icon' => '&#128279;',
                'always' => true,
            ],
        ];

        if ($isLicenseValid) {
            $this->menuItems[] = [
                'slug' => 'ai-seo-dashboard',
                'label' => __('Dashboard', 'ai-seo-client'),
                'icon' => '&#128202;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-content-calendar',
                'label' => __('Content Calendar', 'ai-seo-client'),
                'icon' => '&#128197;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-ideas',
                'label' => __('Ideas', 'ai-seo-client'),
                'icon' => '&#128161;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-created-posts',
                'label' => __('Created Posts', 'ai-seo-client'),
                'icon' => '&#9999;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-keywords',
                'label' => __('Keywords', 'ai-seo-client'),
                'icon' => '&#127919;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-link-manager',
                'label' => __('Link Manager', 'ai-seo-client'),
                'icon' => '&#128279;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-sitemaps',
                'label' => __('Sitemaps', 'ai-seo-client'),
                'icon' => '&#128506;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-bulk',
                'label' => __('Bulk Optimizer', 'ai-seo-client'),
                'icon' => '&#9989;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-data-dashboard',
                'label' => __('SEO Data', 'ai-seo-client'),
                'icon' => '&#128200;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-llm-tracker',
                'label' => __('LLM Tracker', 'ai-seo-client'),
                'icon' => '&#129504;',
            ];

            $professionalTiers = ['professional', 'business', 'agency', 'trial', 'dev'];
            if (in_array($tier, $professionalTiers)) {
                $this->menuItems[] = [
                    'slug' => 'ai-seo-topic-clusters',
                    'label' => __('Topic Clusters', 'ai-seo-client'),
                    'icon' => '&#127919;',
                ];
                $this->menuItems[] = [
                    'slug' => 'ai-seo-site-audit',
                    'label' => __('Site Audit', 'ai-seo-client'),
                    'icon' => '&#128269;',
                ];
                $this->menuItems[] = [
                    'slug' => 'ai-seo-rank-tracker',
                    'label' => __('Rank Tracker', 'ai-seo-client'),
                    'icon' => '&#128200;',
                ];
                $this->menuItems[] = [
                    'slug' => 'ai-seo-google-data',
                    'label' => __('Google Data', 'ai-seo-client'),
                    'icon' => '&#128202;',
                ];
                $this->menuItems[] = [
                    'slug' => 'ai-seo-ab-testing',
                    'label' => __('A/B Testing', 'ai-seo-client'),
                    'icon' => '&#129514;',
                ];
            }
        }

        // Utility items at the bottom of the sidebar
        if ($isLicenseValid) {
            $this->menuItems[] = [
                'slug' => 'ai-seo-ai-tools',
                'label' => __('AI Tools', 'ai-seo-client'),
                'icon' => '&#129302;',
            ];
            $this->menuItems[] = [
                'slug' => 'ai-seo-integrations',
                'label' => __('Integrations', 'ai-seo-client'),
                'icon' => '&#128268;',
            ];
        }

        $this->menuItems[] = [
            'slug' => 'ai-seo-settings',
            'label' => __('Settings', 'ai-seo-client'),
            'icon' => '&#9881;',
            'always' => true,
        ];
    }

    /**
     * Hide WordPress admin chrome when on Fyndable pages.
     * - On the shell page: hide ALL WP chrome (menu, bar, footer, notices)
     * - In the iframe (fyndable_shell=1): hide WP chrome for clean content
     */
    public function hideWpChrome(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $isShellPage = $screen->id === 'toplevel_page_fyndable-dashboard';
        $isIframePage = isset($_GET['fyndable_shell']);
        $isFyndablePage = strpos($screen->id, 'ai-seo') !== false
            || strpos($screen->id, 'fyndable') !== false
            || $isShellPage;

        if (!$isFyndablePage) {
            return;
        }

        if ($isShellPage) {
            // Shell page: hide everything from WordPress, take over full viewport
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

        // For all Fyndable pages (including iframe content): use JS to detect iframe
        // and hide WP chrome even when fyndable_shell param is missing from URL
        if (!$isShellPage) {
            echo '<script>
            (function() {
                if (window.self !== window.top) {
                    // We are inside an iframe — hide all WP chrome
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
                        .wrap, .wrap.sseo-ai-modern {\
                            margin: 0 !important;\
                            padding: 0 !important;\
                        }\
                        .sseo-ai-dashboard-card {\
                            margin: 20px 0px !important;\
                        }\
                        html, body {\
                            overflow-x: hidden !important;\
                        }\
                        *, *::before, *::after {\
                            box-sizing: border-box;\
                        }\
                    ";
                    document.head.appendChild(style);

                    // Intercept links to Fyndable admin pages: append fyndable_shell=1
                    // so they stay inside the iframe shell
                    document.addEventListener("click", function(e) {
                        var link = e.target.closest("a");
                        if (!link || !link.href) return;
                        var url = link.href;
                        // Only intercept links to admin.php with page=ai-seo- or page=fyndable-
                        if (url.indexOf("admin.php") === -1) return;
                        if (url.indexOf("page=ai-seo-") === -1 && url.indexOf("page=fyndable-") === -1) return;
                        // Skip if already has fyndable_shell
                        if (url.indexOf("fyndable_shell") !== -1) return;
                        // Skip links with target=_blank
                        if (link.target === "_blank") return;
                        e.preventDefault();
                        var sep = url.indexOf("?") !== -1 ? "&" : "?";
                        window.location.href = url + sep + "fyndable_shell=1";
                    });

                    // Also intercept form submissions that redirect to Fyndable admin pages
                    // (e.g. options.php redirects back to admin.php?page=ai-seo-*)
                    // Handle this by modifying the _wp_http_referer to include fyndable_shell
                    document.addEventListener("DOMContentLoaded", function() {
                        document.querySelectorAll("form").forEach(function(form) {
                            var formAction = form.getAttribute("action") || "";
                            if (formAction.indexOf("options.php") !== -1) {
                                // Update the _wp_http_referer to include fyndable_shell
                                var referrer = form.querySelector("input[name=_wp_http_referer]");
                                if (referrer && referrer.value.indexOf("fyndable_shell") === -1) {
                                    var sep = referrer.value.indexOf("?") !== -1 ? "&" : "?";
                                    referrer.value = referrer.value + sep + "fyndable_shell=1";
                                }
                            }
                        });
                    });
                }
            })();
            </script>';

            // Also apply CSS immediately if fyndable_shell is set (no waiting for JS)
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
                    .wrap, .wrap.sseo-ai-modern {
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    .sseo-ai-dashboard-card {
                        margin: 20px 0px !important;
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
     * Render the Fyndable dashboard shell (within WordPress .wrap).
     * WP chrome is hidden via hideWpChrome() CSS on admin_head.
     */
    public function render(): void
    {
        $this->buildMenuItems();

        // Determine which page to load in the iframe
        $currentPage = isset($_GET['fyndable_page']) ? sanitize_key($_GET['fyndable_page']) : 'ai-seo-dashboard';
        $licenseValid = $this->client->licenseValidator->isLicenseValid();

        // If not licensed, force connection page
        if (!$licenseValid) {
            $currentPage = 'ai-seo-client';
        }

        // Build iframe URL
        $iframeUrl = admin_url('admin.php');
        $iframeUrl = add_query_arg('page', $currentPage, $iframeUrl);
        $iframeUrl = add_query_arg('fyndable_shell', '1', $iframeUrl);

        $exitUrl = admin_url('admin.php?page=' . $currentPage);
        ?>
        <style>
            .fyndable-shell-wrap {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                z-index: 999999;
                background: #f0f2f5;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
            }

            /* Top bar with gradient */
            .fyndable-topbar {
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
            .fyndable-topbar-brand {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #fff;
            }
            .fyndable-topbar-logo {
                font-size: 20px;
                font-weight: 700;
                letter-spacing: -0.3px;
            }
            .fyndable-topbar-logo span { font-weight: 400; opacity: 0.9; }
            .fyndable-topbar-badge {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                padding: 3px 10px;
                border-radius: 20px;
                background: rgba(255,255,255,0.2);
                color: #fff;
            }
            .fyndable-topbar-actions {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .fyndable-exit-btn {
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
            .fyndable-exit-btn:hover {
                background: rgba(255,255,255,0.25);
                color: #fff;
            }
            .fyndable-exit-x {
                font-size: 18px;
                line-height: 1;
                font-weight: 300;
            }

            /* Main layout */
            .fyndable-shell-body {
                display: flex;
                height: calc(100vh - 56px);
            }

            /* Sidebar */
            .fyndable-sidebar {
                width: 240px;
                background: #fff;
                border-right: 1px solid #e5e7eb;
                display: flex;
                flex-direction: column;
                overflow-y: auto;
                flex-shrink: 0;
            }
            .fyndable-sidebar-nav {
                list-style: none;
                padding: 12px 0;
                flex: 1;
                margin: 0;
            }
            .fyndable-sidebar-nav li {
                margin: 2px 8px;
            }
            .fyndable-sidebar-nav a {
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
            .fyndable-sidebar-nav a:hover {
                background: #f3f4f6;
                color: #1e40af;
            }
            .fyndable-sidebar-nav a.active {
                background: linear-gradient(135deg, #3b82f6 0%, #ec4899 100%);
                color: #fff;
                font-weight: 600;
                box-shadow: 0 2px 6px rgba(59,130,246,0.3);
            }
            .fyndable-sidebar-nav .fyndable-nav-icon {
                font-size: 16px;
                width: 20px;
                text-align: center;
                flex-shrink: 0;
            }
            .fyndable-sidebar-footer {
                padding: 16px;
                border-top: 1px solid #e5e7eb;
                font-size: 11px;
                color: #9ca3af;
                text-align: center;
            }

            /* Content area */
            .fyndable-content {
                flex: 1;
                overflow: hidden;
                position: relative;
            }
            .fyndable-content iframe {
                width: 100%;
                height: 100%;
                border: none;
                display: block;
            }

            /* Loading overlay */
            .fyndable-loading {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: #f0f2f5;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 50;
                transition: opacity 0.3s;
            }
            .fyndable-loading.hidden {
                opacity: 0;
                pointer-events: none;
            }
            .fyndable-spinner {
                width: 36px;
                height: 36px;
                border: 3px solid #e5e7eb;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: fyndable-spin 0.8s linear infinite;
            }
            @keyframes fyndable-spin {
                to { transform: rotate(360deg); }
            }

            /* Mobile responsive */
            @media (max-width: 768px) {
                .fyndable-sidebar {
                    width: 60px;
                }
                .fyndable-sidebar-nav a {
                    justify-content: center;
                    padding: 12px;
                }
                .fyndable-sidebar-nav a span:not(.fyndable-nav-icon) {
                    display: none;
                }
                .fyndable-sidebar-footer { display: none; }
                .fyndable-topbar-badge { display: none; }
            }
        </style>
        <div class="fyndable-shell-wrap">
            <div class="fyndable-topbar">
                <div class="fyndable-topbar-brand">
                    <div class="fyndable-topbar-logo">Fyndable <span>SmartSEO</span></div>
                    <div class="fyndable-topbar-badge"><?php esc_html_e('Dashboard', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyndable-topbar-actions">
                    <a href="<?php echo esc_url($exitUrl); ?>" class="fyndable-exit-btn" title="<?php esc_attr_e('Exit to WordPress', 'ai-seo-client'); ?>">
                        <span class="fyndable-exit-x">&times;</span>
                        <span><?php esc_html_e('Exit', 'ai-seo-client'); ?></span>
                    </a>
                </div>
            </div>

            <div class="fyndable-shell-body">
                <nav class="fyndable-sidebar">
                    <ul class="fyndable-sidebar-nav">
                        <?php foreach ($this->menuItems as $item): ?>
                            <?php $isActive = $item['slug'] === $currentPage; ?>
                            <li>
                                <a href="#" data-slug="<?php echo esc_attr($item['slug']); ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                                    <span class="fyndable-nav-icon"><?php echo $item['icon']; ?></span>
                                    <span><?php echo esc_html($item['label']); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="fyndable-sidebar-footer">
                        Fyndable SmartSEO v<?php echo esc_html(get_option('sseo_ai_client_version', '1.4.0')); ?>
                    </div>
                </nav>

                <div class="fyndable-content">
                    <div class="fyndable-loading" id="fyndable-loading">
                        <div class="fyndable-spinner"></div>
                    </div>
                    <iframe id="fyndable-frame" src="<?php echo esc_url($iframeUrl); ?>"></iframe>
                </div>
            </div>
        </div>
        <script>
        (function() {
            var iframe = document.getElementById('fyndable-frame');
            var loading = document.getElementById('fyndable-loading');
            var navLinks = document.querySelectorAll('.fyndable-sidebar-nav a');

            // Hide loading when iframe loads
            iframe.addEventListener('load', function() {
                loading.classList.add('hidden');
            });

            // Navigation switching
            navLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var slug = link.getAttribute('data-slug');
                    if (!slug) return;

                    // Update active state
                    navLinks.forEach(function(l) { l.classList.remove('active'); });
                    link.classList.add('active');

                    // Show loading and update iframe
                    loading.classList.remove('hidden');
                    var url = '<?php echo esc_js(admin_url('admin.php')); ?>' +
                        '?page=' + encodeURIComponent(slug) +
                        '&fyndable_shell=1';
                    iframe.src = url;

                    // Update URL hash for state persistence
                    window.location.hash = slug;
                });
            });

            // Restore state from hash on load
            var hash = window.location.hash.replace('#', '');
            if (hash) {
                navLinks.forEach(function(link) {
                    if (link.getAttribute('data-slug') === hash) {
                        navLinks.forEach(function(l) { l.classList.remove('active'); });
                        link.classList.add('active');
                        loading.classList.remove('hidden');
                        iframe.src = '<?php echo esc_js(admin_url('admin.php')); ?>' +
                            '?page=' + encodeURIComponent(hash) +
                            '&fyndable_shell=1';
                    }
                });
            }
        })();
        </script>
        <?php
    }
}

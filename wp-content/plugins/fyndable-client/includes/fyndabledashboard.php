<?php

namespace SSEOAIClient;

/**
 * Full-Page Dashboard Shell
 *
 * Replaces the WordPress admin chrome with a branded dashboard experience.
 * Existing admin pages are loaded inside an iframe within the shell.
 * An exit button returns the user to the standard WordPress admin.
 */
class DashboardShell
{
    private Client $client;
    private array $menuSections = [];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function register(): void
    {
        add_action('admin_head', [$this, 'hideWpChrome']);
        add_action('wp_ajax_sseo_ai_toggle_theme', [$this, 'toggleTheme']);
    }

    public function toggleTheme(): void
    {
        check_ajax_referer('sseo_ai_toggle_theme', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'ai-seo-client')], 403);
        }

        $dark = isset($_POST['dark']) && $_POST['dark'] === '1';
        update_option('sseo_ai_client_dark_theme', $dark ? '1' : '0');
        wp_send_json_success(['dark' => $dark]);
    }

    /**
     * Build the grouped sidebar menu sections based on license tier.
     */
    private function buildMenuSections(): void
    {
        $isLicenseValid = $this->client->licenseValidator->isLicenseValid();
        $tier = $this->client->licenseValidator->getLicenseTier();
        $professionalTiers = ['professional', 'business', 'agency', 'trial', 'dev'];
        $isPro = in_array($tier, $professionalTiers, true);
        $isPaidPro = in_array($tier, ['professional', 'business', 'agency', 'dev'], true);

        $unreadCount = 0;
        if ($isLicenseValid) {
            try {
                $unreadCount = $this->client->dashboardAPI->getUnreadSupportCount();
            } catch (\Exception $e) {
                $unreadCount = 0;
            }
        }

        // Section 1: Overzicht (always visible)
        $overzicht = [
            'label' => 'Overview',
            'items' => [
                ['slug' => 'ai-seo-dashboard', 'label' => __('Dashboard', 'ai-seo-client'), 'icon' => 'dashicons-chart-bar'],
                ['slug' => 'ai-seo-client', 'label' => __('Connection', 'ai-seo-client'), 'icon' => 'dashicons-admin-links'],
            ],
        ];
        $this->menuSections[] = $overzicht;

        if (!$isLicenseValid) {
            return;
        }

        // Section 2: Content
        $content = [
            'label' => 'Content',
            'items' => [
                ['slug' => 'ai-seo-ideas', 'label' => __('Ideas', 'ai-seo-client'), 'icon' => 'dashicons-lightbulb'],
                ['slug' => 'ai-seo-content-calendar', 'label' => __('Content Calendar', 'ai-seo-client'), 'icon' => 'dashicons-calendar-alt'],
                ['slug' => 'ai-seo-created-posts', 'label' => __('Created Posts', 'ai-seo-client'), 'icon' => 'dashicons-edit-page'],
                ['slug' => 'ai-seo-topic-clusters', 'label' => __('Topic Clusters', 'ai-seo-client'), 'icon' => 'dashicons-admin-site-alt3', 'pro' => true],
            ],
        ];
        if ($isPaidPro && $this->client->apexFlow) {
            $content['items'][] = ['slug' => 'ai-seo-automation', 'label' => 'ApexFlow', 'icon' => 'dashicons-controls-play'];
        }
        $content['items'][] = ['slug' => 'ai-seo-bulk', 'label' => __('Bulk Optimization', 'ai-seo-client'), 'icon' => 'dashicons-yes-alt'];
        // Filter pro items if not professional tier
        if (!$isPro) {
            $content['items'] = array_values(array_filter($content['items'], function ($item) {
                return empty($item['pro']);
            }));
        }
        $this->menuSections[] = $content;

        // Section 3: Zoekwoorden & ranking
        $keywords = [
            'label' => 'Keywords & Ranking',
            'items' => [
                ['slug' => 'ai-seo-keywords', 'label' => __('Keywords', 'ai-seo-client'), 'icon' => 'dashicons-search'],
                ['slug' => 'ai-seo-rank-tracker', 'label' => __('Rank Tracker', 'ai-seo-client'), 'icon' => 'dashicons-chart-line', 'pro' => true],
                ['slug' => 'ai-seo-competitor-research', 'label' => __('Competitors', 'ai-seo-client'), 'icon' => 'dashicons-groups'],
                ['slug' => 'ai-seo-ab-testing', 'label' => __('A/B Testing', 'ai-seo-client'), 'icon' => 'dashicons-randomize', 'pro' => true],
            ],
        ];
        if (!$isPro) {
            $keywords['items'] = array_values(array_filter($keywords['items'], function ($item) {
                return empty($item['pro']);
            }));
        }
        $this->menuSections[] = $keywords;

        // Section 4: Links & techniek
        $links = [
            'label' => 'Links & Technical',
            'items' => [
                ['slug' => 'ai-seo-link-manager', 'label' => __('Link Manager', 'ai-seo-client'), 'icon' => 'dashicons-admin-links'],
            ],
        ];
        if ($isPro && $this->client->backlinkAnalyzer) {
            $links['items'][] = ['slug' => 'ai-seo-backlinks', 'label' => __('Backlinks', 'ai-seo-client'), 'icon' => 'dashicons-admin-links'];
            if ($this->client->advancedBacklinks) {
                $links['items'][] = ['slug' => 'ai-seo-advanced-backlinks', 'label' => __('Adv. Backlinks', 'ai-seo-client'), 'icon' => 'dashicons-admin-links'];
            }
        }
        if ($isPro) {
            $links['items'][] = ['slug' => 'ai-seo-site-audit', 'label' => __('Site Audit', 'ai-seo-client'), 'icon' => 'dashicons-clipboard'];
        }
        $links['items'][] = ['slug' => 'ai-seo-sitemaps', 'label' => __('Sitemaps', 'ai-seo-client'), 'icon' => 'dashicons-networking'];
        $this->menuSections[] = $links;

        // Section 5: Data & inzichten
        $data = [
            'label' => 'Data & Insights',
            'items' => [
                ['slug' => 'ai-seo-data-dashboard', 'label' => __('SEO Data', 'ai-seo-client'), 'icon' => 'dashicons-chart-area'],
            ],
        ];
        if ($isPro) {
            $data['items'][] = ['slug' => 'ai-seo-google-data', 'label' => __('Google Data', 'ai-seo-client'), 'icon' => 'dashicons-google'];
        }
        $data['items'][] = ['slug' => 'ai-seo-llm-tracker', 'label' => __('LLM Tracker', 'ai-seo-client'), 'icon' => 'dashicons-visibility'];
        $this->menuSections[] = $data;

        // Section 6: Systeem
        $systeem = [
            'label' => 'System',
            'items' => [
                ['slug' => 'ai-seo-ai-tools', 'label' => __('AI Tools', 'ai-seo-client'), 'icon' => 'dashicons-superhero-alt'],
                ['slug' => 'ai-seo-integrations', 'label' => __('Integrations', 'ai-seo-client'), 'icon' => 'dashicons-admin-plugins'],
                ['slug' => 'ai-seo-support', 'label' => __('Support', 'ai-seo-client'), 'icon' => 'dashicons-sos', 'badge' => $unreadCount],
            ],
        ];
        $this->menuSections[] = $systeem;
    }

    /**
     * Hide WordPress admin chrome when on dashboard pages.
     */
    public function hideWpChrome(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $isShellPage = $screen->id === 'toplevel_page_fyndable-dashboard';
        $isIframePage = isset($_GET['fyndable_shell']);
        $isDashboardPage = strpos($screen->id, 'ai-seo') !== false
            || strpos($screen->id, 'fyndable') !== false
            || $isShellPage;

        if (!$isDashboardPage) {
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
                        .wrap, .wrap.sseo-ai-modern {\
                            margin: 0 !important;\
                            padding: 0 !important;\
                        }\
                        .wrap.fyn-animate-in {\
                            margin: 0 auto !important;\
                            padding: 28px 32px 48px !important;\
                            max-width: 1440px;\
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
                        if (url.indexOf("page=ai-seo-") === -1 && url.indexOf("page=fyndable-") === -1) return;
                        if (link.target === "_blank") return;
                        e.preventDefault();
                        var sep = url.indexOf("?") !== -1 ? "&" : "?";
                        if (url.indexOf("fyndable_shell") === -1) {
                            url += sep + "fyndable_shell=1";
                            sep = "&";
                        }
                        if (url.indexOf("fyndable_dark") === -1) {
                            var darkParam = document.body.classList.contains("fyndable-dark") ? "1" : "0";
                            url += sep + "fyndable_dark=" + darkParam;
                        }
                        window.location.href = url;
                    });

                    document.addEventListener("DOMContentLoaded", function() {
                        document.querySelectorAll("form").forEach(function(form) {
                            var formAction = form.getAttribute("action") || "";
                            if (formAction.indexOf("options.php") !== -1) {
                                var referrer = form.querySelector("input[name=_wp_http_referer]");
                                if (referrer && referrer.value.indexOf("fyndable_shell") === -1) {
                                    var sep = referrer.value.indexOf("?") !== -1 ? "&" : "?";
                                    var darkParam = document.body.classList.contains("fyndable-dark") ? "1" : "0";
                                    referrer.value = referrer.value + sep + "fyndable_shell=1&fyndable_dark=" + darkParam;
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
                    .wrap, .wrap.sseo-ai-modern {
                        margin: 0 !important;
                        padding: 0 !important;
                    }
                    .wrap.fyn-animate-in {
                        margin: 0 auto !important;
                        padding: 28px 32px 48px !important;
                        max-width: 1440px;
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
     * Render the dashboard shell.
     */
    public function render(): void
    {
        $this->buildMenuSections();

        $currentPage = isset($_GET['fyndable_page']) ? sanitize_key($_GET['fyndable_page']) : 'ai-seo-dashboard';
        if (empty($currentPage) || $currentPage === 'fyndable-dashboard') {
            $currentPage = 'ai-seo-dashboard';
        }

        $licenseValid = $this->client->licenseValidator->isLicenseValid();
        if (!$licenseValid) {
            $currentPage = 'ai-seo-client';
        }

        $darkTheme = get_option('sseo_ai_client_dark_theme', '0') === '1';

        $iframeUrl = admin_url('admin.php');
        $iframeUrl = add_query_arg('page', $currentPage, $iframeUrl);
        $iframeUrl = add_query_arg('fyndable_shell', '1', $iframeUrl);
        $iframeUrl = add_query_arg('fyndable_dark', $darkTheme ? '1' : '0', $iframeUrl);

        $passThroughParams = ['keyword', 'error', 'activated', 'deactivated', 'validated'];
        foreach ($passThroughParams as $param) {
            if (isset($_GET[$param]) && $_GET[$param] !== '') {
                $iframeUrl = add_query_arg($param, sanitize_text_field($_GET[$param]), $iframeUrl);
            }
        }

        $exitUrl = admin_url('admin.php?page=' . $currentPage);

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $companyLogo = !empty($whiteLabel['company_logo']) ? $whiteLabel['company_logo'] : '';
        $hasCustomBrand = !empty($whiteLabel['company_name']);
        $primaryColor = $hasCustomBrand ? (sanitize_hex_color($whiteLabel['primary_color'] ?? '') ?: '#8F39AC') : '#8F39AC';
        $secondaryColor = $hasCustomBrand ? (sanitize_hex_color($whiteLabel['secondary_color'] ?? '') ?: '#379FD3') : '#379FD3';
        $usePrimaryOnly = $hasCustomBrand && !empty($whiteLabel['use_primary_only']);

        $pluginUrl = dirname(plugin_dir_url(__FILE__));

        $currentUser = wp_get_current_user();
        $initials = '';
        if ($currentUser->ID) {
            $fn = $currentUser->first_name ?: '';
            $ln = $currentUser->last_name ?: '';
            if ($fn && $ln) {
                $initials = mb_strtoupper(mb_substr($fn, 0, 1) . mb_substr($ln, 0, 1));
            } else {
                $initials = mb_strtoupper(mb_substr($currentUser->display_name, 0, 2));
            }
        }

        $tier = $this->client->licenseValidator->getLicenseTier();
        $tierLabel = ucfirst($tier ?: 'Free');
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
                background: linear-gradient(135deg, #2A1533 0%, #132A38 100%);
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
            }
            .fyn-topbar-search {
                display: flex;
                align-items: center;
                max-width: 460px;
                flex: 1;
                height: 36px;
                background: rgba(255,255,255,.1);
                border: 1px solid rgba(255,255,255,.12);
                border-radius: 9px;
                padding: 0 12px;
                gap: 8px;
                margin-left: 20px;
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
            .fyn-topbar-avatar {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                background: linear-gradient(135deg, #8F39AC, #379FD3);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: 700;
                color: #fff;
                letter-spacing: .5px;
                flex-shrink: 0;
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

            /* ======= BODY (sidebar + main) ======= */
            .fyn-shell-body {
                display: flex;
                flex: 1;
                min-height: 0;
            }

            /* ======= SIDEBAR ======= */
            .fyn-sidebar {
                width: 248px;
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
                transition: all .15s;
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
                background: linear-gradient(135deg, #8F39AC, #379FD3);
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

            /* Plan card below nav */
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
                .fyn-topbar-search { display: none; }
                .fyn-topbar-micro { display: none; }
                .fyn-topbar-divider { display: none; }
            }
        </style>
        <div class="fyn-shell">
            <!-- Top bar -->
            <div class="fyn-topbar">
                <div class="fyn-topbar-brand">
                    <?php if ($companyLogo): ?>
                        <img src="<?php echo esc_url($companyLogo); ?>" alt="<?php echo esc_attr($companyName); ?>" class="fyn-topbar-logo">
                    <?php else: ?>
                        <img src="<?php echo esc_url($pluginUrl . '/assets/FYN-LOGO-DIA-LONG300ppi.png'); ?>" alt="Fyndable" class="fyn-topbar-logo">
                    <?php endif; ?>
                    <span class="fyn-topbar-divider"></span>
                    <span class="fyn-topbar-micro">Client</span>
                </div>
                <div class="fyn-topbar-search">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" placeholder="<?php esc_attr_e('Search...', 'ai-seo-client'); ?>" id="fyn-global-search">
                    <span class="fyn-topbar-search-hint">&ctrlKey;K</span>
                </div>
                <span class="fyn-topbar-spacer"></span>
                <div class="fyn-topbar-actions">
                    <button type="button" id="fyn-theme-toggle" class="fyn-topbar-theme" aria-pressed="<?php echo $darkTheme ? 'true' : 'false'; ?>" title="<?php esc_attr_e('Toggle light/dark theme', 'ai-seo-client'); ?>">
                        <span aria-hidden="true">&#9788;</span>
                        <span class="fyn-topbar-theme-track" aria-hidden="true"></span>
                        <span aria-hidden="true">&#9790;</span>
                    </button>
                    <button type="button" class="fyn-topbar-iconbtn" title="<?php esc_attr_e('Notifications', 'ai-seo-client'); ?>">
                        <span class="dashicons dashicons-bell"></span>
                    </button>
                    <button type="button" class="fyn-topbar-iconbtn" title="<?php esc_attr_e('Help', 'ai-seo-client'); ?>">
                        <span class="dashicons dashicons-editor-help"></span>
                    </button>
                    <span class="fyn-topbar-divider"></span>
                    <span class="fyn-topbar-avatar"><?php echo esc_html($initials); ?></span>
                    <a href="<?php echo esc_url($exitUrl); ?>" class="fyn-topbar-exit" title="<?php esc_attr_e('Back to WordPress', 'ai-seo-client'); ?>">
                        <?php esc_html_e('Exit', 'ai-seo-client'); ?>
                    </a>
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
                                        <span><?php echo $item['label']; ?></span>
                                        <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                                            <span class="fyn-nav-badge"><?php echo (int)$item['badge']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                    <div class="fyn-plan-card">
                        <div class="fyn-plan-card-title"><?php esc_html_e('Current Plan', 'ai-seo-client'); ?></div>
                        <div class="fyn-plan-card-tier"><?php echo esc_html($tierLabel); ?></div>
                        <div class="fyn-plan-progress">
                            <div class="fyn-plan-progress-bar" style="width:45%"></div>
                        </div>
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
            var storageKey = 'fyndableDarkTheme';

            var storedDark = localStorage.getItem(storageKey);
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

            function setTheme(dark) {
                isDark = dark;
                localStorage.setItem(storageKey, dark ? '1' : '0');
                applyTheme(dark);
            }

            applyTheme(isDark);

            iframe.addEventListener('load', function() {
                loading.classList.add('hidden');
                applyTheme(isDark);
            });

            themeToggle.addEventListener('click', function() {
                var dark = !isDark;
                setTheme(dark);

                var data = new FormData();
                data.append('action', 'sseo_ai_toggle_theme');
                data.append('nonce', '<?php echo esc_js(wp_create_nonce('sseo_ai_toggle_theme')); ?>');
                data.append('dark', dark ? '1' : '0');
                fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: data
                });
            });

            function buildIframeUrl(slug) {
                return '<?php echo esc_js(admin_url('admin.php')); ?>' +
                    '?page=' + encodeURIComponent(slug) +
                    '&fyndable_shell=1' +
                    '&fyndable_dark=' + (isDark ? '1' : '0');
            }

            navLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var slug = link.getAttribute('data-slug');
                    if (!slug) return;

                    navLinks.forEach(function(l) { l.classList.remove('active'); });
                    link.classList.add('active');

                    loading.classList.remove('hidden');
                    iframe.src = buildIframeUrl(slug);

                    window.location.hash = slug;
                });
            });

            var hash = window.location.hash.replace('#', '');
            if (hash === 'fyndable-dashboard' || !hash) {
                hash = 'ai-seo-dashboard';
            }
            if (hash) {
                navLinks.forEach(function(link) {
                    if (link.getAttribute('data-slug') === hash) {
                        navLinks.forEach(function(l) { l.classList.remove('active'); });
                        link.classList.add('active');
                        loading.classList.remove('hidden');
                        iframe.src = buildIframeUrl(hash);
                    }
                });
            }
        })();
        </script>
        <?php
    }
}

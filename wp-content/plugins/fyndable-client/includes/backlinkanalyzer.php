<?php

namespace SSEOAIClient;

/**
 * Backlink & Authority Analysis
 * 
 * Integrates with DataForSEO (via Portal proxy), Ahrefs, SE Ranking and Semrush to provide:
 * - Domain Authority (DA) / Page Authority (PA)
 * - Backlink profile analysis
 * - Toxic link detection
 * - Link building opportunities (link gap vs. competitors)
 * - Anchor text analysis
 * - Competitor backlink analysis
 *
 * Also the shared backlink data source for AdvancedBacklinks and CompetitorResearch.
 */
class BacklinkAnalyzer
{
    private Settings $settings;
    private ?DashboardAPI $dashboardAPI = null;
    private const CACHE_TTL = DAY_IN_SECONDS;
    private const ERROR_CACHE_TTL = 15 * MINUTE_IN_SECONDS;
    private const COMPETITORS_OPTION = 'sseo_ai_competitor_domains';
    private const MAX_COMPETITORS = 5;
    /** DataForSEO backlink_spam_score (0-100) from which a link counts as toxic. */
    private const SPAM_SCORE_TOXIC = 50;

    public function __construct(Settings $settings, ?DashboardAPI $dashboardAPI = null)
    {
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_analyze_backlinks', [$this, 'ajaxAnalyzeBacklinks']);
        add_action('wp_ajax_sseo_ai_check_toxic_links', [$this, 'ajaxCheckToxicLinks']);
        add_action('wp_ajax_sseo_ai_add_competitor', [$this, 'ajaxAddCompetitor']);
        add_action('wp_ajax_sseo_ai_remove_competitor', [$this, 'ajaxRemoveCompetitor']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Backlink Analysis', 'ai-seo-client'),
            __('Backlinks', 'ai-seo-client'),
            'manage_options',
            'ai-seo-backlinks',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render backlink analysis dashboard
     */
    public function renderDashboard(): void
    {
        $siteUrl = get_site_url();
        $domain = parse_url($siteUrl, PHP_URL_HOST);
        
        // Get cached data
        $domainMetrics = $this->getDomainAuthority($domain);
        $backlinkProfile = $this->getBacklinkProfile($domain);
        $toxicLinks = $this->getToxicLinks($domain);
        $competitors = $this->getCompetitorDomains();

        ?>
        <style>
            .ba-toxic-score { font-weight: 700; }
            .ba-toxic-score[data-score="0"], .ba-toxic-score[data-score^="1"], .ba-toxic-score[data-score^="2"], .ba-toxic-score[data-score^="3"] { color: var(--fyn-success); }
            .ba-toxic-score[data-score^="4"], .ba-toxic-score[data-score^="5"], .ba-toxic-score[data-score^="6"] { color: var(--fyn-warning); }
            .ba-toxic-score[data-score^="7"], .ba-toxic-score[data-score^="8"], .ba-toxic-score[data-score^="9"], .ba-toxic-score[data-score="100"] { color: var(--fyn-danger); }
        </style>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <!-- Page header -->
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Backlink Analysis', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Backlink & Authority Analysis', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php echo esc_html($domain); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <button type="button" class="fyn-btn fyn-btn-primary" onclick="sseoRefreshDomainMetrics()">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh Metrics', 'ai-seo-client'); ?>
                    </button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-advanced-backlinks&fyndable_shell=1')); ?>" class="fyn-btn fyn-btn-outline">
                        <?php esc_html_e('Advanced Analysis', 'ai-seo-client'); ?>
                    </a>
                </div>
            </div>

            <!-- Domain Metrics -->
            <div class="fyn-stats-grid" style="margin-bottom:20px;">
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value"><?php echo esc_html($domainMetrics['domain_rating'] ?? 'N/A'); ?></div>
                    <div class="fyn-stat-label"><?php esc_html_e('Domain Rating (DR)', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value"><?php echo esc_html(number_format($domainMetrics['referring_domains'] ?? 0)); ?></div>
                    <div class="fyn-stat-label"><?php esc_html_e('Referring Domains', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value"><?php echo esc_html(number_format($domainMetrics['backlinks'] ?? 0)); ?></div>
                    <div class="fyn-stat-label"><?php esc_html_e('Total Backlinks', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value"><?php echo esc_html(number_format($domainMetrics['organic_traffic'] ?? 0)); ?></div>
                    <div class="fyn-stat-label"><?php esc_html_e('Est. Organic Traffic', 'ai-seo-client'); ?></div>
                </div>
            </div>

            <!-- Backlink Profile -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-admin-links"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Backlink Profile', 'ai-seo-client'); ?></h3>
                </div>
                <div class="fyn-grid-2">
                    <div>
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 10px;"><?php esc_html_e('Link Types', 'ai-seo-client'); ?></h4>
                        <table class="fyn-table">
                            <tbody>
                                <tr>
                                    <td><?php esc_html_e('Dofollow', 'ai-seo-client'); ?></td>
                                    <td><strong><?php echo esc_html(number_format($backlinkProfile['dofollow'] ?? 0)); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e('Nofollow', 'ai-seo-client'); ?></td>
                                    <td><?php echo esc_html(number_format($backlinkProfile['nofollow'] ?? 0)); ?></td>
                                </tr>
                                <tr>
                                    <td><?php esc_html_e('Redirect', 'ai-seo-client'); ?></td>
                                    <td><?php echo esc_html(number_format($backlinkProfile['redirect'] ?? 0)); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 10px;"><?php esc_html_e('Top Anchor Texts', 'ai-seo-client'); ?></h4>
                        <table class="fyn-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Anchor Text', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Count', 'ai-seo-client'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $anchors = $backlinkProfile['top_anchors'] ?? [];
                                foreach (array_slice($anchors, 0, 5) as $anchor):
                                ?>
                                <tr>
                                    <td><?php echo esc_html($anchor['text']); ?></td>
                                    <td><?php echo esc_html($anchor['count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Toxic Links -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-warning"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Toxic Link Detection', 'ai-seo-client'); ?></h3>
                </div>

                <?php if (!empty($toxicLinks)): ?>
                <div class="fyn-badge fyn-badge--<?php echo count($toxicLinks) > 10 ? 'danger' : 'warning'; ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;margin-bottom:14px;">
                    <strong><?php echo count($toxicLinks); ?></strong>
                    <?php esc_html_e('potentially toxic backlinks detected', 'ai-seo-client'); ?>
                </div>

                <table class="fyn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Source Domain', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Toxicity Score', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Reason', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Action', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($toxicLinks, 0, 10) as $link): ?>
                        <tr>
                            <td><?php echo esc_html($link['domain']); ?></td>
                            <td>
                                <span class="ba-toxic-score" data-score="<?php echo esc_attr($link['toxicity']); ?>">
                                    <?php echo esc_html($link['toxicity']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($link['reason']); ?></td>
                            <td>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;padding:4px 10px;"
                                        onclick="sseoDisavowLink('<?php echo esc_js($link['url']); ?>')">
                                    <?php esc_html_e('Disavow', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:var(--text-m);"><?php esc_html_e('No toxic links detected. Great job!', 'ai-seo-client'); ?></p>
                <?php endif; ?>

                <div style="margin-top:16px;">
                    <button type="button" class="fyn-btn fyn-btn-outline" onclick="sseoCheckToxicLinks()">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Run Toxic Link Check', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>

            <!-- Competitor Analysis -->
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-groups"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Competitor Backlink Analysis', 'ai-seo-client'); ?></h3>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="font-size:13px;font-weight:600;color:var(--text-h);">
                        <?php esc_html_e('Add Competitor Domain:', 'ai-seo-client'); ?>
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;margin-top:6px;">
                        <input type="text" id="competitor-domain" class="fyn-input" style="max-width:300px;"
                               placeholder="example.com">
                        <button type="button" class="fyn-btn fyn-btn-outline" onclick="sseoAddCompetitor()">
                            <?php esc_html_e('Add', 'ai-seo-client'); ?>
                        </button>
                    </div>
                    <p style="font-size:12px;color:var(--text-m);margin:6px 0 0;">
                        <?php echo esc_html(sprintf(__('Up to %d competitors. Used for link gap and broken-link prospecting.', 'ai-seo-client'), self::MAX_COMPETITORS)); ?>
                    </p>
                </div>

                <?php if (!empty($competitors)): ?>
                <table class="fyn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Competitor', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('DR', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Backlinks', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Ref. Domains', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitors as $compDomain): ?>
                        <?php $compMetrics = $this->getDomainAuthority($compDomain); ?>
                        <tr>
                            <td><?php echo esc_html($compDomain); ?></td>
                            <td><?php echo esc_html($compMetrics['domain_rating'] ?: 'N/A'); ?></td>
                            <td><?php echo esc_html(number_format($compMetrics['backlinks'] ?? 0)); ?></td>
                            <td><?php echo esc_html(number_format($compMetrics['referring_domains'] ?? 0)); ?></td>
                            <td>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;padding:4px 10px;"
                                        onclick="sseoFindLinkOpportunities('<?php echo esc_js($compDomain); ?>')">
                                    <?php esc_html_e('Find Opportunities', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;padding:4px 10px;color:var(--fyn-danger);border-color:var(--fyn-danger);"
                                        onclick="sseoRemoveCompetitor('<?php echo esc_js($compDomain); ?>')">
                                    <?php esc_html_e('Remove', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function sseoRefreshDomainMetrics() {
            if (!confirm('<?php esc_html_e('This will fetch fresh data from the API. Continue?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_analyze_backlinks',
                domain: '<?php echo esc_js($domain); ?>',
                force_refresh: true,
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error refreshing metrics');
                }
            });
        }
        
        function sseoCheckToxicLinks() {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_check_toxic_links',
                domain: '<?php echo esc_js($domain); ?>',
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error checking toxic links');
                }
            });
        }
        
        function sseoDisavowLink(url) {
            if (!confirm('<?php esc_html_e('Add this link to your disavow file?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            // Add to disavow list
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_disavow_link',
                url: url,
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Link added to disavow file', 'ai-seo-client'); ?>');
                    location.reload();
                }
            });
        }
        
        function sseoAddCompetitor() {
            const domain = jQuery('#competitor-domain').val().trim();
            if (!domain) return;
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_add_competitor',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error adding competitor');
                }
            });
        }

        function sseoRemoveCompetitor(domain) {
            if (!confirm('<?php echo esc_js(__('Remove this competitor?', 'ai-seo-client')); ?>')) {
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'sseo_ai_remove_competitor',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error removing competitor');
                }
            });
        }

        function sseoFindLinkOpportunities(domain) {
            var url = 'admin.php?page=ai-seo-advanced-backlinks&competitor=' + encodeURIComponent(domain);
            if (window.self !== window.top) {
                url += '&fyndable_shell=1';
                if (document.body.classList.contains('fyndable-dark')) {
                    url += '&fyndable_dark=1';
                }
            }
            window.location.href = url;
        }
        </script>
        <?php
    }
    
    /**
     * Get domain authority metrics. Tries primary provider, then fallbacks.
     */
    public function getDomainAuthority(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return $this->getDefaultMetrics();
        }
        $cacheKey = 'sseo_da_' . md5($domain);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $providers = $this->getProviderChain();
        $metrics = $this->getDefaultMetrics();
        
        foreach ($providers as $provider) {
            $metrics = $this->getDomainMetricsFromProvider($domain, $provider);
            if (!empty($metrics['backlinks']) || !empty($metrics['referring_domains']) || !empty($metrics['domain_rating'])) {
                break;
            }
        }
        
        set_transient($cacheKey, $metrics, self::CACHE_TTL);
        return $metrics;
    }
    
    /**
     * Get backlink profile. Tries primary provider, then fallbacks.
     */
    public function getBacklinkProfile(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return $this->getDefaultBacklinkProfile();
        }
        $cacheKey = 'sseo_backlinks_' . md5($domain);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $providers = $this->getProviderChain();
        $profile = $this->getDefaultBacklinkProfile();
        
        foreach ($providers as $provider) {
            $profile = $this->getBacklinksFromProvider($domain, $provider);
            if (!empty($profile['items']) || !empty($profile['backlinks']) || ($profile['dofollow'] + $profile['nofollow']) > 0) {
                break;
            }
        }
        
        set_transient($cacheKey, $profile, self::CACHE_TTL);
        return $profile;
    }

    /**
     * Normalized backlink list for a domain (cached).
     *
     * Without a query this returns the items of the regular backlink profile
     * (shared cache with the Backlinks page). With a query — mode, filters,
     * order_by, limit, backlinks_status_type — a dedicated DataForSEO
     * request is made, since only DataForSEO supports server-side filtering.
     *
     * Each item: source_url, source_domain, target_url, anchor_text,
     * domain_rating (0-100), page_rating, link_type (dofollow|nofollow),
     * item_type, attributes[], page_title, spam_score, is_broken,
     * target_status, first_seen.
     */
    public function getBacklinkItems(string $domain, array $query = []): array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return [];
        }

        if (empty($query)) {
            return $this->getBacklinkProfile($domain)['items'] ?? [];
        }

        $cacheKey = 'sseo_bl_items_' . md5($domain . wp_json_encode($query));
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $items = $this->fetchDataForSeoItems($domain, $query);
        if (is_wp_error($items)) {
            // Short negative cache so a failing API doesn't stall every page load.
            set_transient($cacheKey, [], self::ERROR_CACHE_TTL);
            return [];
        }

        $items = array_map([$this, 'normalizeLink'], $items);
        set_transient($cacheKey, $items, self::CACHE_TTL);
        return $items;
    }

    /**
     * Referring domains that link to a competitor but not to our own site.
     *
     * Compares one-link-per-domain lists (max 1000 referring domains each),
     * so for very large sites the result is an approximation.
     */
    public function getLinkGap(string $competitorDomain, int $limit = 100): array
    {
        $competitorDomain = $this->normalizeDomain($competitorDomain);
        $ownDomain = $this->getOwnDomain();
        if ($competitorDomain === '' || $competitorDomain === $ownDomain) {
            return [];
        }

        $query = [
            'mode'     => 'one_per_domain',
            'limit'    => 1000,
            'order_by' => ['domain_from_rank,desc'],
        ];

        $competitorLinks = $this->getBacklinkItems($competitorDomain, $query);
        if (empty($competitorLinks)) {
            return [];
        }

        $ownReferrers = array_flip(array_column($this->getBacklinkItems($ownDomain, $query), 'source_domain'));

        $gap = [];
        foreach ($competitorLinks as $link) {
            $source = $link['source_domain'];
            if ($source === '' || $source === $ownDomain || isset($ownReferrers[$source])) {
                continue;
            }
            if ($link['spam_score'] >= self::SPAM_SCORE_TOXIC) {
                continue;
            }
            $gap[] = $link;
            if (count($gap) >= $limit) {
                break;
            }
        }

        return $gap;
    }

    /**
     * Classify a normalized backlink by source type.
     *
     * @return string guest_posts|directories|forums|social|editorial|other
     */
    public function classifyLink(array $link): string
    {
        $domain = $link['source_domain'] ?? '';
        $url = strtolower($link['source_url'] ?? '');
        $title = strtolower($link['page_title'] ?? '');
        $attributes = array_map('strtolower', (array) ($link['attributes'] ?? []));

        if (preg_match('/(^|\.)(facebook\.com|twitter\.com|x\.com|linkedin\.com|instagram\.com|pinterest\.[a-z.]+|youtube\.com|tiktok\.com|threads\.net|mastodon\.social)$/', $domain)) {
            return 'social';
        }
        if (preg_match('/(^|\.)(reddit|quora|stackexchange|stackoverflow)\.[a-z.]+$/', $domain)
            || preg_match('#/(forum|forums|thread|threads|topic|community|discussion|viewtopic)[/.?-]#', $url)) {
            return 'forums';
        }
        if (in_array('sponsored', $attributes, true)
            || preg_match('/guest[- ]?post|gastblog|gastartikel|write[- ]for[- ]us|sponsored|gesponsord/', $url . ' ' . $title)) {
            return 'guest_posts';
        }
        if (preg_match('/(^|\.)(yelp|trustpilot|startpagina|goudengids|yellowpages|tripadvisor|kvk|openingstijden|cylex|hotfrog)\.[a-z.]+$/', $domain)
            || preg_match('#/(directory|directories|listing|listings|bedrijven|company|companies|links)[/.?-]#', $url)) {
            return 'directories';
        }
        if (($link['item_type'] ?? 'anchor') === 'anchor'
            && preg_match('#/(blog|blogs|news|nieuws|article|articles|artikel|artikelen|magazine|\d{4}/\d{2})/#', $url)) {
            return 'editorial';
        }

        return 'other';
    }

    /**
     * Configured competitor domains (normalized, unique).
     */
    public function getCompetitorDomains(): array
    {
        $domains = [];
        foreach ((array) get_option(self::COMPETITORS_OPTION, []) as $entry) {
            $domain = $this->normalizeDomain(is_array($entry) ? (string) ($entry['domain'] ?? '') : (string) $entry);
            if ($domain !== '') {
                $domains[$domain] = $domain;
            }
        }
        return array_values($domains);
    }

    /**
     * Own site domain (normalized; raw host for local/dev hosts).
     */
    public function getOwnDomain(): string
    {
        $host = (string) parse_url(get_site_url(), PHP_URL_HOST);
        return $this->normalizeDomain($host) ?: $host;
    }

    /**
     * Normalize user input / URLs to a bare registrable host. Returns '' when invalid.
     */
    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain);
        $domain = preg_replace('#[/?\#].*$#', '', $domain);
        $domain = preg_replace('#:\d+$#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9-]{2,}$/', $domain) ? $domain : '';
    }

    /**
     * Build provider chain: primary first, then configured fallbacks.
     */
    private function getProviderChain(): array
    {
        $primary = get_option('sseo_ai_backlink_provider', 'dataforseo');
        $all = ['dataforseo', 'ahrefs', 'seranking', 'semrush'];
        $chain = [$primary];
        foreach ($all as $p) {
            if ($p !== $primary) {
                $chain[] = $p;
            }
        }
        return $chain;
    }
    
    /**
     * Get domain metrics from a single provider.
     */
    private function getDomainMetricsFromProvider(string $domain, string $provider): array
    {
        switch ($provider) {
            case 'dataforseo':
                return $this->getDataForSEODomainMetrics($domain);
            case 'ahrefs':
                return $this->getAhrefsDomainMetrics($domain);
            case 'seranking':
                return $this->getSerankingDomainMetrics($domain);
            case 'semrush':
                return $this->getSemrushDomainMetrics($domain);
            default:
                return $this->getDefaultMetrics();
        }
    }
    
    /**
     * Get backlink profile from a single provider.
     */
    private function getBacklinksFromProvider(string $domain, string $provider): array
    {
        switch ($provider) {
            case 'dataforseo':
                return $this->getDataForSEOBacklinks($domain);
            case 'ahrefs':
                return $this->getAhrefsBacklinks($domain);
            case 'seranking':
                return $this->getSerankingBacklinks($domain);
            case 'semrush':
                return $this->getSemrushBacklinks($domain);
            default:
                return $this->getDefaultBacklinkProfile();
        }
    }
    
    /**
     * Ahrefs API integration
     */
    private function getAhrefsDomainMetrics(string $domain): array
    {
        $apiKey = get_option('sseo_ai_ahrefs_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultMetrics();
        }
        
        $response = wp_remote_get("https://api.ahrefs.com/v3/site-explorer/metrics-extended?target={$domain}&mode=domain", [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $this->getDefaultMetrics();
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data['metrics'])) {
            return $this->getDefaultMetrics();
        }
        
        $metrics = $data['metrics'];
        
        return [
            'domain_rating' => $metrics['domain_rating'] ?? 0,
            'referring_domains' => $metrics['refdomains'] ?? 0,
            'backlinks' => $metrics['backlinks'] ?? 0,
            'organic_traffic' => $metrics['organic_traffic'] ?? 0,
            'organic_keywords' => $metrics['organic_keywords'] ?? 0,
        ];
    }
    
    /**
     * Get Ahrefs backlinks
     */
    private function getAhrefsBacklinks(string $domain): array
    {
        $apiKey = get_option('sseo_ai_ahrefs_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultBacklinkProfile();
        }
        
        $response = wp_remote_get("https://api.ahrefs.com/v3/site-explorer/backlinks?target={$domain}&mode=domain&limit=1000", [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $this->getDefaultBacklinkProfile();
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $backlinks = is_array($data['backlinks'] ?? null) ? $data['backlinks'] : [];

        return $this->buildProfile(array_map([$this, 'normalizeLink'], $backlinks));
    }
    
    /**
     * Semrush API integration
     */
    private function getSemrushDomainMetrics(string $domain): array
    {
        $apiKey = get_option('sseo_ai_semrush_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultMetrics();
        }
        
        $response = wp_remote_get("https://api.semrush.com/?type=domain_ranks&key={$apiKey}&export_columns=Dn,Rk,Or,Ot,Oc&domain={$domain}", [
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $this->getDefaultMetrics();
        }
        
        $body = wp_remote_retrieve_body($response);
        $lines = explode("\n", trim($body));
        
        if (count($lines) < 2) {
            return $this->getDefaultMetrics();
        }
        
        $data = str_getcsv($lines[1], ';');
        
        return [
            'domain_rating' => (int)($data[1] ?? 0),
            'referring_domains' => 0, // Semrush doesn't provide this in basic API
            'backlinks' => 0,
            'organic_traffic' => (int)($data[2] ?? 0),
            'organic_keywords' => (int)($data[3] ?? 0),
        ];
    }
    
    /**
     * Get Semrush backlinks
     */
    private function getSemrushBacklinks(string $domain): array
    {
        $apiKey = get_option('sseo_ai_semrush_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultBacklinkProfile();
        }
        
        $response = wp_remote_get("https://api.semrush.com/?type=backlinks&key={$apiKey}&target={$domain}&target_type=root_domain&display_limit=1000", [
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $this->getDefaultBacklinkProfile();
        }
        
        $body = wp_remote_retrieve_body($response);
        $lines = explode("\n", trim($body));
        
        $dofollow = 0;
        $nofollow = 0;
        $anchors = [];
        
        foreach (array_slice($lines, 1) as $line) {
            $data = str_getcsv($line, ';');
            if (count($data) < 5) continue;
            
            $type = $data[4] ?? '';
            if ($type === 'text') {
                $dofollow++;
            } else {
                $nofollow++;
            }
            
            $anchor = $data[3] ?? '';
            if (!empty($anchor)) {
                if (!isset($anchors[$anchor])) {
                    $anchors[$anchor] = 0;
                }
                $anchors[$anchor]++;
            }
        }
        
        arsort($anchors);
        $topAnchors = [];
        foreach (array_slice($anchors, 0, 10, true) as $text => $count) {
            $topAnchors[] = ['text' => $text, 'count' => $count];
        }
        
        return [
            'dofollow' => $dofollow,
            'nofollow' => $nofollow,
            'redirect' => 0,
            'top_anchors' => $topAnchors,
        ];
    }
    
    /**
     * DataForSEO domain metrics.
     *
     * Uses the Portal proxy (via DashboardAPI) as the primary path so that
     * the central DataForSEO API key is used. Falls back to the legacy
     * client-side DataForSEOBacklinksClient when the proxy is unavailable
     * and a client-side key is configured.
     */
    private function getDataForSEODomainMetrics(string $domain): array
    {
        // Primary: Portal proxy
        if ($this->dashboardAPI) {
            $response = $this->dashboardAPI->request('backlinks/summary', ['target' => $domain]);
            if (!is_wp_error($response) && !empty($response['data'])) {
                return $this->parseDataForSeoSummaryMetrics($response['data']);
            }
        }

        // Fallback: client-side direct call with customer's own key
        $apiKey = get_option('sseo_ai_dataforseo_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultMetrics();
        }

        $client = new DataForSEOBacklinksClient($apiKey);
        return $client->getDomainMetrics($domain);
    }

    /**
     * DataForSEO backlinks.
     *
     * Uses the Portal proxy as primary, with client-side fallback.
     */
    private function getDataForSEOBacklinks(string $domain): array
    {
        $items = $this->fetchDataForSeoItems($domain, [
            'limit'    => 1000,
            'order_by' => ['domain_from_rank,desc'],
        ]);

        if (is_wp_error($items)) {
            return $this->getDefaultBacklinkProfile();
        }

        return $this->buildProfile(array_map([$this, 'normalizeLink'], $items));
    }

    /**
     * Raw DataForSEO backlink items: Portal proxy first (central key + usage
     * tracking), then the customer's own DataForSEO key.
     */
    private function fetchDataForSeoItems(string $domain, array $params): array|\WP_Error
    {
        $error = new \WP_Error('backlinks_unavailable', __('No backlink data source is available.', 'ai-seo-client'));

        if ($this->dashboardAPI) {
            $response = $this->dashboardAPI->request('backlinks/live', array_merge(['target' => $domain], $params));
            if (!is_wp_error($response) && !empty($response['data'])) {
                $items = $response['data']['tasks'][0]['result'][0]['items'] ?? [];
                return is_array($items) ? array_filter($items, 'is_array') : [];
            }
            if (is_wp_error($response)) {
                $error = $response;
            }
        }

        $apiKey = get_option('sseo_ai_dataforseo_api_key', '');
        if (empty($apiKey)) {
            return $error;
        }

        $client = new DataForSEOBacklinksClient($apiKey);
        $items = $client->getBacklinkItems($domain, $params);
        return is_wp_error($items) ? $items : array_filter($items, 'is_array');
    }

    /**
     * Parse DataForSEO summary response (from Portal proxy) into the
     * normalized metrics shape used by this class.
     */
    private function parseDataForSeoSummaryMetrics(array $data): array
    {
        $summary = $data['tasks'][0]['result'][0] ?? [];

        return [
            'domain_rating'    => (int) ($summary['rank'] ?? 0),
            'referring_domains'=> (int) ($summary['referring_domains'] ?? 0),
            'backlinks'        => (int) ($summary['backlinks'] ?? 0),
            'organic_traffic'  => 0, // Not part of the Backlinks summary
            'organic_keywords' => 0,
        ];
    }

    /**
     * Map a provider-specific backlink (DataForSEO, Ahrefs, SE Ranking) to
     * the normalized item shape documented on getBacklinkItems().
     */
    private function normalizeLink(array $link): array
    {
        $sourceUrl = (string) ($link['url_from'] ?? $link['source_url'] ?? $link['url'] ?? '');
        $sourceDomain = (string) ($link['domain_from'] ?? $link['source_domain'] ?? (parse_url($sourceUrl, PHP_URL_HOST) ?: ''));

        $dofollow = $link['dofollow'] ?? $link['is_dofollow'] ?? (isset($link['nofollow']) ? !$link['nofollow'] : null);
        if ($dofollow === null) {
            $dofollow = (($link['link_type'] ?? $link['type'] ?? '') === 'dofollow');
        }

        $status = (int) ($link['url_to_status_code'] ?? $link['http_code_target'] ?? 0);

        return [
            'source_url'    => $sourceUrl,
            'source_domain' => preg_replace('#^www\.#', '', strtolower($sourceDomain)),
            'target_url'    => (string) ($link['url_to'] ?? $link['target_url'] ?? ''),
            'anchor_text'   => trim((string) ($link['anchor'] ?? $link['anchor_text'] ?? '')),
            'domain_rating' => (int) round((float) ($link['domain_from_rank'] ?? $link['domain_rating_source'] ?? $link['domain_rating'] ?? 0)),
            'page_rating'   => (int) round((float) ($link['page_from_rank'] ?? $link['url_rating_source'] ?? 0)),
            'link_type'     => $dofollow ? 'dofollow' : 'nofollow',
            'item_type'     => (string) ($link['item_type'] ?? (!empty($link['is_redirect']) ? 'redirect' : 'anchor')),
            'attributes'    => array_values(array_filter((array) ($link['attributes'] ?? []), 'is_string')),
            'page_title'    => (string) ($link['page_from_title'] ?? $link['title'] ?? ''),
            'spam_score'    => (int) ($link['backlink_spam_score'] ?? 0),
            'is_broken'     => !empty($link['is_broken']) || $status >= 400,
            'target_status' => $status,
            'first_seen'    => (string) ($link['first_seen'] ?? ''),
        ];
    }

    /**
     * Aggregate normalized items into the backlink profile shape.
     */
    private function buildProfile(array $items): array
    {
        $dofollow = 0;
        $nofollow = 0;
        $redirect = 0;
        $anchors = [];

        foreach ($items as $link) {
            if ($link['link_type'] === 'dofollow') {
                $dofollow++;
            } else {
                $nofollow++;
            }

            if ($link['item_type'] === 'redirect') {
                $redirect++;
            }

            if ($link['anchor_text'] !== '') {
                $anchors[$link['anchor_text']] = ($anchors[$link['anchor_text']] ?? 0) + 1;
            }
        }

        arsort($anchors);
        $topAnchors = [];
        foreach (array_slice($anchors, 0, 10, true) as $text => $count) {
            $topAnchors[] = ['text' => (string) $text, 'count' => $count];
        }

        return [
            'dofollow'    => $dofollow,
            'nofollow'    => $nofollow,
            'redirect'    => $redirect,
            'top_anchors' => $topAnchors,
            'items'       => array_values($items),
        ];
    }
    
    /**
     * SE Ranking domain metrics
     */
    private function getSerankingDomainMetrics(string $domain): array
    {
        $apiKey = get_option('sseo_ai_seranking_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultMetrics();
        }

        $client = new SERankingDataClient($apiKey);
        $overview = $client->getDomainOverview($domain, 'us', true);
        if (is_wp_error($overview)) {
            return $this->getDefaultMetrics();
        }

        return [
            'domain_rating' => (int) ($overview['domain_trust'] ?? $overview['domain_rank'] ?? 0),
            'referring_domains' => (int) ($overview['referring_domains'] ?? 0),
            'backlinks' => (int) ($overview['backlinks'] ?? 0),
            'organic_traffic' => (int) ($overview['organic_traffic'] ?? 0),
            'organic_keywords' => (int) ($overview['organic_keywords'] ?? 0),
        ];
    }
    
    /**
     * SE Ranking backlinks (best-effort via Data API)
     */
    private function getSerankingBacklinks(string $domain): array
    {
        $apiKey = get_option('sseo_ai_seranking_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultBacklinkProfile();
        }

        $response = wp_remote_get(
            'https://api.seranking.com/v1/backlinks?domain=' . urlencode($domain) . '&token=' . urlencode($apiKey),
            ['timeout' => 30]
        );

        if (is_wp_error($response)) {
            return $this->getDefaultBacklinkProfile();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $items = $body['data'] ?? $body['backlinks'] ?? [];
        $items = is_array($items) ? array_filter($items, 'is_array') : [];

        return $this->buildProfile(array_map([$this, 'normalizeLink'], $items));
    }
    
    /**
     * Detect toxic links using backlink profile items.
     *
     * Score (0-100) = DataForSEO backlink_spam_score, plus penalties for
     * spam-vertical anchors and near-zero authority sources. Providers that
     * don't return per-link items (Semrush) yield no toxic list.
     */
    public function getToxicLinks(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        if ($domain === '') {
            return [];
        }
        $cacheKey = 'sseo_toxic_' . md5($domain);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $items = $this->getBacklinkItems($domain);
        $toxicLinks = [];

        $spamAnchorPattern = '/\b(casino|poker|gambling|betting|porn|xxx|viagra|cialis|escort|payday|replica|crypto ?signals?|cheap (?:pills|meds))\b/i';

        foreach ($items as $link) {
            $score = $link['spam_score'];
            $reasons = [];

            if ($link['spam_score'] >= self::SPAM_SCORE_TOXIC) {
                $reasons[] = sprintf(__('high spam score (%d)', 'ai-seo-client'), $link['spam_score']);
            }

            if ($link['anchor_text'] !== '' && preg_match($spamAnchorPattern, $link['anchor_text'])) {
                $score += 50;
                $reasons[] = __('spam-related anchor text', 'ai-seo-client');
            }

            if ($link['domain_rating'] > 0 && $link['domain_rating'] < 5) {
                $score += 15;
                $reasons[] = __('very low authority domain', 'ai-seo-client');
            }

            $score = min(100, $score);
            if ($score >= self::SPAM_SCORE_TOXIC) {
                $toxicLinks[] = [
                    'url' => $link['source_url'],
                    'domain' => $link['source_domain'],
                    'anchor' => $link['anchor_text'],
                    'toxicity' => $score,
                    'reason' => implode(', ', $reasons),
                    'reasons' => $reasons,
                ];
            }
        }

        usort($toxicLinks, fn($a, $b) => $b['toxicity'] <=> $a['toxicity']);

        set_transient($cacheKey, $toxicLinks, self::CACHE_TTL);
        return $toxicLinks;
    }

    /**
     * Default metrics when API is not available
     */
    private function getDefaultMetrics(): array
    {
        return [
            'domain_rating' => 0,
            'referring_domains' => 0,
            'backlinks' => 0,
            'organic_traffic' => 0,
            'organic_keywords' => 0,
        ];
    }

    /**
     * Default backlink profile
     */
    private function getDefaultBacklinkProfile(): array
    {
        return [
            'dofollow' => 0,
            'nofollow' => 0,
            'redirect' => 0,
            'top_anchors' => [],
            'items' => [],
        ];
    }

    /**
     * Drop cached metrics/profile/toxic data for a domain.
     */
    private function clearDomainCache(string $domain): void
    {
        $domain = $this->normalizeDomain($domain) ?: $domain;
        delete_transient('sseo_da_' . md5($domain));
        delete_transient('sseo_backlinks_' . md5($domain));
        delete_transient('sseo_toxic_' . md5($domain));
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/backlinks/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyzeBacklinks'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/backlinks/opportunities', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetLinkOpportunities'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * REST: Analyze a domain (default: own site).
     * Body: { domain?, force_refresh? }
     */
    public function restAnalyzeBacklinks(\WP_REST_Request $request): array|\WP_Error
    {
        $input = (string) ($request->get_param('domain') ?? '');
        $domain = $input === '' ? $this->getOwnDomain() : $this->normalizeDomain($input);
        if ($domain === '') {
            return new \WP_Error('invalid_domain', __('Invalid domain', 'ai-seo-client'), ['status' => 400]);
        }

        if (!empty($request->get_param('force_refresh'))) {
            $this->clearDomainCache($domain);
        }

        $profile = $this->getBacklinkProfile($domain);
        unset($profile['items']);

        return [
            'domain' => $domain,
            'metrics' => $this->getDomainAuthority($domain),
            'profile' => $profile,
            'toxic_links' => $this->getToxicLinks($domain),
        ];
    }

    /**
     * AJAX: Analyze backlinks
     */
    public function ajaxAnalyzeBacklinks(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $forceRefresh = !empty($_POST['force_refresh']);

        if ($forceRefresh) {
            $this->clearDomainCache($domain);
        }

        $metrics = $this->getDomainAuthority($domain);

        wp_send_json_success(['metrics' => $metrics]);
    }

    /**
     * AJAX: Check toxic links
     */
    public function ajaxCheckToxicLinks(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');

        delete_transient('sseo_toxic_' . md5($this->normalizeDomain($domain) ?: $domain));
        $toxicLinks = $this->getToxicLinks($domain);

        wp_send_json_success(['toxic_links' => $toxicLinks]);
    }

    /**
     * AJAX: Add a competitor domain
     */
    public function ajaxAddCompetitor(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $domain = $this->normalizeDomain(sanitize_text_field(wp_unslash($_POST['domain'] ?? '')));
        if ($domain === '') {
            wp_send_json_error(['message' => __('Please enter a valid domain, e.g. competitor.com', 'ai-seo-client')]);
        }
        if ($domain === $this->getOwnDomain()) {
            wp_send_json_error(['message' => __('This is your own domain.', 'ai-seo-client')]);
        }

        $competitors = $this->getCompetitorDomains();
        if (in_array($domain, $competitors, true)) {
            wp_send_json_error(['message' => __('This competitor is already added.', 'ai-seo-client')]);
        }
        if (count($competitors) >= self::MAX_COMPETITORS) {
            wp_send_json_error(['message' => sprintf(__('You can track up to %d competitors.', 'ai-seo-client'), self::MAX_COMPETITORS)]);
        }

        $this->saveCompetitorDomains(array_merge($competitors, [$domain]));

        wp_send_json_success([
            'domain' => $domain,
            'metrics' => $this->getDomainAuthority($domain),
        ]);
    }

    /**
     * AJAX: Remove a competitor domain
     */
    public function ajaxRemoveCompetitor(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $domain = $this->normalizeDomain(sanitize_text_field(wp_unslash($_POST['domain'] ?? '')));
        $this->saveCompetitorDomains(array_diff($this->getCompetitorDomains(), [$domain]));

        wp_send_json_success(['domain' => $domain]);
    }

    private function saveCompetitorDomains(array $domains): void
    {
        $existing = [];
        foreach ((array) get_option(self::COMPETITORS_OPTION, []) as $entry) {
            if (is_array($entry) && !empty($entry['domain'])) {
                $existing[$this->normalizeDomain((string) $entry['domain'])] = $entry;
            }
        }

        $entries = [];
        foreach (array_values(array_unique($domains)) as $domain) {
            $entries[] = $existing[$domain] ?? ['domain' => $domain, 'added_at' => current_time('mysql')];
        }

        update_option(self::COMPETITORS_OPTION, $entries, false);
    }

    /**
     * REST: Get link building opportunities
     * Query: competitor? (default: all configured competitors), keyword? (filters on anchor/page title)
     */
    public function restGetLinkOpportunities(\WP_REST_Request $request): array
    {
        $keyword = sanitize_text_field((string) ($request->get_param('keyword') ?? ''));
        $competitorDomain = sanitize_text_field((string) ($request->get_param('competitor') ?? ''));

        $opportunities = $this->findLinkOpportunities($keyword, $competitorDomain);

        return ['opportunities' => $opportunities];
    }

    /**
     * Find link building opportunities: referring domains of competitors
     * that don't link to us yet, optionally narrowed to a keyword.
     */
    private function findLinkOpportunities(string $keyword, string $competitorDomain): array
    {
        $competitors = $competitorDomain !== ''
            ? array_filter([$this->normalizeDomain($competitorDomain)])
            : $this->getCompetitorDomains();

        $opportunities = [];
        foreach ($competitors as $competitor) {
            foreach ($this->getLinkGap($competitor, 200) as $link) {
                if ($keyword !== ''
                    && stripos($link['anchor_text'], $keyword) === false
                    && stripos($link['page_title'], $keyword) === false) {
                    continue;
                }

                $domain = $link['source_domain'];
                if (!isset($opportunities[$domain])) {
                    $opportunities[$domain] = [
                        'domain' => $domain,
                        'domain_rating' => $link['domain_rating'],
                        'sample_url' => $link['source_url'],
                        'anchor_text' => $link['anchor_text'],
                        'link_type' => $link['link_type'],
                        'category' => $this->classifyLink($link),
                        'competitors' => [],
                    ];
                }
                $opportunities[$domain]['competitors'][] = $competitor;
            }
        }

        usort($opportunities, fn($a, $b) => [count($b['competitors']), $b['domain_rating']] <=> [count($a['competitors']), $a['domain_rating']]);

        return array_slice($opportunities, 0, 100);
    }
}

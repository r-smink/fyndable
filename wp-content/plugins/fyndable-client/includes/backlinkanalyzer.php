<?php

namespace SSEOAIClient;

/**
 * Backlink & Authority Analysis
 * 
 * Integrates with Ahrefs and Semrush APIs to provide:
 * - Domain Authority (DA) / Page Authority (PA)
 * - Backlink profile analysis
 * - Toxic link detection
 * - Referring domain tracking
 * - Link building opportunities
 * - Anchor text analysis
 * - Competitor backlink analysis
 */
class BacklinkAnalyzer
{
    private Settings $settings;
    private ?DashboardAPI $dashboardAPI = null;
    private const CACHE_TTL = DAY_IN_SECONDS;

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
        $competitors = get_option('sseo_ai_competitor_domains', []);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Backlink & Authority Analysis', 'ai-seo-client'); ?></h1>
            
            <!-- Domain Authority Overview -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Domain Metrics', 'ai-seo-client'); ?></h2>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div>
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html($domainMetrics['domain_rating'] ?? 'N/A'); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Domain Rating (DR)', 'ai-seo-client'); ?>
                        </p>
                    </div>
                    <div>
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html(number_format($domainMetrics['referring_domains'] ?? 0)); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Referring Domains', 'ai-seo-client'); ?>
                        </p>
                    </div>
                    <div>
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html(number_format($domainMetrics['backlinks'] ?? 0)); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Total Backlinks', 'ai-seo-client'); ?>
                        </p>
                    </div>
                    <div>
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html(number_format($domainMetrics['organic_traffic'] ?? 0)); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Est. Organic Traffic', 'ai-seo-client'); ?>
                        </p>
                    </div>
                </div>
                
                <button type="button" class="button button-primary" style="margin-top: 15px;" 
                        onclick="sseoRefreshDomainMetrics()">
                    <?php esc_html_e('Refresh Metrics', 'ai-seo-client'); ?>
                </button>
            </div>
            
            <!-- Backlink Profile -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Backlink Profile', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                    <div>
                        <h3><?php esc_html_e('Link Types', 'ai-seo-client'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
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
                        <h3><?php esc_html_e('Top Anchor Texts', 'ai-seo-client'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
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
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Toxic Link Detection', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($toxicLinks)): ?>
                <div class="notice notice-warning" style="margin: 15px 0;">
                    <p>
                        <strong><?php echo count($toxicLinks); ?></strong> 
                        <?php esc_html_e('potentially toxic backlinks detected', 'ai-seo-client'); ?>
                    </p>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
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
                                <span style="color: <?php echo $link['toxicity'] > 70 ? '#d63638' : '#dba617'; ?>;">
                                    <?php echo esc_html($link['toxicity']); ?>%
                                </span>
                            </td>
                            <td><?php echo esc_html($link['reason']); ?></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoDisavowLink('<?php echo esc_js($link['url']); ?>')">
                                    <?php esc_html_e('Disavow', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No toxic links detected. Great job!', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                
                <button type="button" class="button" style="margin-top: 15px;" 
                        onclick="sseoCheckToxicLinks()">
                    <?php esc_html_e('Run Toxic Link Check', 'ai-seo-client'); ?>
                </button>
            </div>
            
            <!-- Competitor Analysis -->
            <div class="card">
                <h2><?php esc_html_e('Competitor Backlink Analysis', 'ai-seo-client'); ?></h2>
                
                <div style="margin: 15px 0;">
                    <label>
                        <strong><?php esc_html_e('Add Competitor Domain:', 'ai-seo-client'); ?></strong>
                    </label>
                    <input type="text" id="competitor-domain" class="regular-text" 
                           placeholder="example.com">
                    <button type="button" class="button" onclick="sseoAddCompetitor()">
                        <?php esc_html_e('Add', 'ai-seo-client'); ?>
                    </button>
                </div>
                
                <?php if (!empty($competitors)): ?>
                <table class="wp-list-table widefat fixed striped">
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
                        <?php foreach ($competitors as $comp): ?>
                        <tr>
                            <td><?php echo esc_html($comp['domain']); ?></td>
                            <td><?php echo esc_html($comp['dr'] ?? 'N/A'); ?></td>
                            <td><?php echo esc_html(number_format($comp['backlinks'] ?? 0)); ?></td>
                            <td><?php echo esc_html(number_format($comp['ref_domains'] ?? 0)); ?></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoFindLinkOpportunities('<?php echo esc_js($comp['domain']); ?>')">
                                    <?php esc_html_e('Find Opportunities', 'ai-seo-client'); ?>
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
        
        function sseoFindLinkOpportunities(domain) {
            window.location.href = 'admin.php?page=ai-seo-link-opportunities&competitor=' + encodeURIComponent(domain);
        }
        </script>
        <?php
    }
    
    /**
     * Get domain authority metrics. Tries primary provider, then fallbacks.
     */
    public function getDomainAuthority(string $domain): array
    {
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
        $backlinks = $data['backlinks'] ?? [];
        
        $dofollow = 0;
        $nofollow = 0;
        $redirect = 0;
        $anchors = [];
        
        foreach ($backlinks as $link) {
            if ($link['is_dofollow']) {
                $dofollow++;
            } else {
                $nofollow++;
            }
            
            if ($link['is_redirect']) {
                $redirect++;
            }
            
            $anchor = $link['anchor'] ?? '';
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
            'redirect' => $redirect,
            'top_anchors' => $topAnchors,
        ];
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
        // Primary: Portal proxy
        if ($this->dashboardAPI) {
            $response = $this->dashboardAPI->request('backlinks/live', [
                'target' => $domain,
                'limit'  => 1000,
            ]);
            if (!is_wp_error($response) && !empty($response['data'])) {
                return $this->parseDataForSeoBacklinks($response['data']);
            }
        }

        // Fallback: client-side direct call
        $apiKey = get_option('sseo_ai_dataforseo_api_key', '');
        if (empty($apiKey)) {
            return $this->getDefaultBacklinkProfile();
        }

        $client = new DataForSEOBacklinksClient($apiKey);
        return $client->getBacklinks($domain);
    }

    /**
     * Parse DataForSEO summary response (from Portal proxy) into the
     * normalized metrics shape used by this class.
     */
    private function parseDataForSeoSummaryMetrics(array $data): array
    {
        $tasks = $data['tasks'] ?? [];
        $result = $tasks[0]['result'][0] ?? [];
        $summary = $result['summary'] ?? $result;

        return [
            'domain_rating'    => (int) ($summary['domain_rank'] ?? $summary['domain_rating'] ?? 0),
            'referring_domains'=> (int) ($summary['referring_domains'] ?? 0),
            'backlinks'        => (int) ($summary['backlinks'] ?? 0),
            'organic_traffic'  => (int) ($summary['organic_traffic'] ?? 0),
            'organic_keywords' => (int) ($summary['organic_keywords'] ?? 0),
        ];
    }

    /**
     * Parse DataForSEO backlinks list response (from Portal proxy) into the
     * normalized backlink profile shape used by this class.
     */
    private function parseDataForSeoBacklinks(array $data): array
    {
        $tasks = $data['tasks'] ?? [];
        $items = $tasks[0]['result'][0]['items'] ?? [];

        $dofollow = 0;
        $nofollow = 0;
        $redirect = 0;
        $anchors = [];

        foreach ($items as $link) {
            $linkType = $link['link_type'] ?? '';
            $isDofollow = ($linkType === 'dofollow' || ($link['dofollow'] ?? false));

            if ($isDofollow) {
                $dofollow++;
            } else {
                $nofollow++;
            }

            if (!empty($link['is_redirect']) || stripos($link['type'] ?? '', 'redirect') !== false) {
                $redirect++;
            }

            $anchor = $link['anchor_text'] ?? $link['anchor'] ?? '';
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
            'dofollow'    => $dofollow,
            'nofollow'    => $nofollow,
            'redirect'    => $redirect,
            'top_anchors' => $topAnchors,
            'items'       => $items,
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

        $dofollow = 0;
        $nofollow = 0;
        $anchors = [];

        foreach ($items as $link) {
            $isDofollow = ($link['type'] ?? '') === 'dofollow' || ($link['dofollow'] ?? false);
            if ($isDofollow) {
                $dofollow++;
            } else {
                $nofollow++;
            }

            $anchor = $link['anchor'] ?? $link['anchor_text'] ?? '';
            if (!empty($anchor)) {
                $anchors[$anchor] = ($anchors[$anchor] ?? 0) + 1;
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
            'items' => $items,
        ];
    }
    
    /**
     * Detect toxic links using backlink profile items.
     */
    public function getToxicLinks(string $domain): array
    {
        $cacheKey = 'sseo_toxic_' . md5($domain);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $profile = $this->getBacklinkProfile($domain);
        $toxicLinks = [];
        
        $items = $profile['items'] ?? [];
        if (empty($items)) {
            set_transient($cacheKey, [], self::CACHE_TTL);
            return [];
        }
        
        $suspiciousAnchors = ['click here', 'read more', 'here', 'link', 'website', 'buy now', 'cheap', 'free', 'porn', 'xxx', 'casino'];
        
        foreach ($items as $link) {
            $url = $link['url'] ?? $link['target'] ?? '';
            $anchor = strtolower($link['anchor_text'] ?? $link['anchor'] ?? '');
            $referringDomain = parse_url($url, PHP_URL_HOST) ?: '';
            $isToxic = false;
            $reasons = [];
            
            foreach ($suspiciousAnchors as $suspicious) {
                if (str_contains($anchor, $suspicious)) {
                    $isToxic = true;
                    $reasons[] = 'suspicious anchor';
                    break;
                }
            }
            
            if (!empty($link['referring_domain_attributes']['domain_rank']) && (int) $link['referring_domain_attributes']['domain_rank'] < 5) {
                $isToxic = true;
                $reasons[] = 'low authority domain';
            }
            
            if ($isToxic) {
                $toxicLinks[] = [
                    'url' => $url,
                    'domain' => $referringDomain,
                    'anchor' => $anchor,
                    'reasons' => array_unique($reasons),
                ];
            }
        }
        
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
        ];
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
            delete_transient('sseo_da_' . md5($domain));
            delete_transient('sseo_backlinks_' . md5($domain));
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
        
        delete_transient('sseo_toxic_' . md5($domain));
        $toxicLinks = $this->getToxicLinks($domain);
        
        wp_send_json_success(['toxic_links' => $toxicLinks]);
    }
    
    /**
     * REST: Get link building opportunities
     */
    public function restGetLinkOpportunities(\WP_REST_Request $request): array
    {
        $keyword = $request->get_param('keyword');
        $competitorDomain = $request->get_param('competitor');
        
        // Find link opportunities based on competitor backlinks
        $opportunities = $this->findLinkOpportunities($keyword, $competitorDomain);
        
        return ['opportunities' => $opportunities];
    }
    
    /**
     * Find link building opportunities
     */
    private function findLinkOpportunities(string $keyword, string $competitorDomain): array
    {
        // Get competitor's backlinks
        $competitorBacklinks = $this->getBacklinkProfile($competitorDomain);
        
        // Filter for high-quality opportunities
        $opportunities = [];
        
        // This would analyze competitor backlinks and suggest similar opportunities
        // Implementation depends on API capabilities
        
        return $opportunities;
    }
}

<?php

namespace SSEOAIClient;

/**
 * AI-Powered Competitor Research
 * 
 * Advanced competitor analysis including:
 * - Competitor keyword strategy detection
 * - Content calendar analysis
 * - AI-generated content detection
 * - Win/loss analysis
 * - Competitor backlink strategy
 * - Competitor ad copy analysis
 */
class CompetitorResearch
{
    private Settings $settings;
    private LLMClient $llm;
    private DashboardAPI $dashboardAPI;
    private ?BacklinkAnalyzer $backlinkAnalyzer;

    public function __construct(Settings $settings, LLMClient $llm, DashboardAPI $dashboardAPI, ?BacklinkAnalyzer $backlinkAnalyzer = null)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->dashboardAPI = $dashboardAPI;
        $this->backlinkAnalyzer = $backlinkAnalyzer;
    }

    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_analyze_competitor', [$this, 'ajaxAnalyzeCompetitor']);
        add_action('wp_ajax_sseo_ai_detect_ai_content', [$this, 'ajaxDetectAIContent']);
        add_action('wp_ajax_sseo_ai_get_backlink_strategy', [$this, 'ajaxGetBacklinkStrategy']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Competitor Research', 'ai-seo-client'),
            __('Competitor Research', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-competitor-research',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render competitor research dashboard
     */
    public function renderDashboard(): void
    {
        $competitors = get_option('sseo_ai_tracked_competitors', []);
        $currentDomain = parse_url(get_site_url(), PHP_URL_HOST);
        
        ?>
        <style>
            .cr-tab-content { display: none; padding: 20px 0 0; }
            .cr-tab-content.cr-active { display: block; }
        </style>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <!-- Page header -->
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Competitor Research', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Competitor Research', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('AI-powered analysis of competitors, keywords and content strategy.', 'ai-seo-client'); ?></p>
                </div>
            </div>

            <!-- Add Competitor -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Track Competitor', 'ai-seo-client'); ?></h3>
                </div>
                <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <label for="competitor-domain" style="font-size:12px;font-weight:700;color:var(--text-m);">
                            <?php esc_html_e('Competitor Domain', 'ai-seo-client'); ?>
                        </label><br>
                        <input type="text" id="competitor-domain" class="fyn-input" style="min-width:200px;"
                               placeholder="concurrent.nl">
                    </div>
                    <div>
                        <label for="competitor-sitemap" style="font-size:12px;font-weight:700;color:var(--text-m);">
                            <?php esc_html_e('Sitemap URL (optional)', 'ai-seo-client'); ?>
                        </label><br>
                        <input type="text" id="competitor-sitemap" class="fyn-input" style="min-width:300px;"
                               placeholder="https://competitor.com/sitemap_index.xml">
                        <p style="margin-top:2px;font-size:11px;color:var(--text-m);">
                            <?php esc_html_e('Leave empty to auto-detect. Use a sitemap index URL for a full-site check.', 'ai-seo-client'); ?>
                        </p>
                    </div>
                    <div style="display:flex;align-items:center;gap:5px;padding-top:18px;">
                        <input type="checkbox" id="competitor-fullsite" value="1">
                        <label for="competitor-fullsite" style="white-space:nowrap;font-size:13px;color:var(--text-b);">
                            <?php esc_html_e('Full Site', 'ai-seo-client'); ?>
                        </label>
                        <span style="font-size:11px;color:var(--text-m);cursor:help;" title="<?php esc_attr_e('Follow all sub-sitemaps in a sitemap index for a complete scan', 'ai-seo-client'); ?>">&#9432;</span>
                    </div>
                    <div style="padding-top:18px;">
                        <button type="button" class="fyn-btn fyn-btn-primary" onclick="sseoAddCompetitorTracking()">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add & Analyze', 'ai-seo-client'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Competitor Overview -->
            <?php if (!empty($competitors)): ?>
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-groups"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Tracked Competitors', 'ai-seo-client'); ?></h3>
                </div>

                <table class="fyn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Competitor', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Keywords', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Content Strategy', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('AI-content %', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Updated', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitors as $comp): ?>
                        <tr>
                            <td>
                                <strong style="color:var(--text-h);"><?php echo esc_html($comp['domain']); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html(number_format($comp['keyword_count'] ?? 0)); ?> keywords
                            </td>
                            <td>
                                <?php
                                $strategy = $comp['content_strategy'] ?? 'Onbekend';
                                echo esc_html($strategy);
                                ?>
                            </td>
                            <td>
                                <span class="fyn-badge fyn-badge--<?php echo ($comp['ai_content_percentage'] ?? 0) > 50 ? 'danger' : 'success'; ?>">
                                    <?php echo esc_html(($comp['ai_content_percentage'] ?? 0)); ?>%
                                </span>
                            </td>
                            <td style="color:var(--text-m);font-size:12px;">
                                <?php
                                $lastUpdated = $comp['last_updated'] ?? '';
                                echo $lastUpdated ? esc_html(human_time_diff(strtotime($lastUpdated))) . ' ' . esc_html__('ago', 'ai-seo-client') : esc_html__('Never', 'ai-seo-client');
                                ?>
                            </td>
                            <td>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;"
                                        onclick="sseoViewCompetitorDetails('<?php echo esc_js($comp['domain']); ?>')">
                                    <?php esc_html_e('Details', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;"
                                        onclick="sseoRefreshCompetitor('<?php echo esc_js($comp['domain']); ?>', '<?php echo esc_js($comp['sitemap_url'] ?? ''); ?>', <?php echo !empty($comp['full_site']) ? 'true' : 'false'; ?>)">
                                    <span class="dashicons dashicons-update" style="font-size:14px;width:14px;height:14px;"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Detailed Analysis Tabs -->
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Competitor Analysis', 'ai-seo-client'); ?></h3>
                </div>

                <div id="competitor-tabs">
                    <div class="fyn-tabs" style="margin-bottom:0;">
                        <button type="button" class="fyn-tab cr-tab-btn active" onclick="sseoSwitchTab(event, 'keyword-strategy')">
                            <?php esc_html_e('Keyword Strategy', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="fyn-tab cr-tab-btn" onclick="sseoSwitchTab(event, 'content-calendar')">
                            <?php esc_html_e('Content Calendar', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="fyn-tab cr-tab-btn" onclick="sseoSwitchTab(event, 'win-loss')">
                            <?php esc_html_e('Win/Loss Analysis', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="fyn-tab cr-tab-btn" onclick="sseoSwitchTab(event, 'backlink-strategy')">
                            <?php esc_html_e('Backlink Strategy', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="fyn-tab cr-tab-btn" onclick="sseoSwitchTab(event, 'ad-copy')">
                            <?php esc_html_e('Ad Copy', 'ai-seo-client'); ?>
                        </button>
                    </div>

                    <!-- Keyword Strategy Tab -->
                    <div id="keyword-strategy" class="cr-tab-content cr-active">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 8px;"><?php esc_html_e('Competitor Keyword Strategy', 'ai-seo-client'); ?></h4>
                        <div id="keyword-strategy-content">
                            <p style="color:var(--text-m);"><?php esc_html_e('Select a competitor to view their keyword strategy.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>

                    <!-- Content Calendar Tab -->
                    <div id="content-calendar" class="cr-tab-content">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 8px;"><?php esc_html_e('Content Publishing Calendar', 'ai-seo-client'); ?></h4>
                        <div id="content-calendar-content">
                            <p style="color:var(--text-m);"><?php esc_html_e('Select a competitor to view their content calendar.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>

                    <!-- Win/Loss Tab -->
                    <div id="win-loss" class="cr-tab-content">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 8px;"><?php esc_html_e('Win/Loss Analysis', 'ai-seo-client'); ?></h4>
                        <div id="win-loss-content">
                            <p style="color:var(--text-m);"><?php esc_html_e('Select a competitor to compare rankings.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>

                    <!-- Backlink Strategy Tab -->
                    <div id="backlink-strategy" class="cr-tab-content">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 8px;"><?php esc_html_e('Backlink Strategy', 'ai-seo-client'); ?></h4>
                        <div id="backlink-strategy-content">
                            <p style="color:var(--text-m);"><?php esc_html_e('Select a competitor to view their backlink strategy.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>

                    <!-- Ad Copy Tab -->
                    <div id="ad-copy" class="cr-tab-content">
                        <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 8px;"><?php esc_html_e('Ad Copy', 'ai-seo-client'); ?></h4>
                        <div id="ad-copy-content">
                            <p style="color:var(--text-m);"><?php esc_html_e('Select a competitor to view their ad copy.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        
        <script>
        function sseoAddCompetitorTracking() {
            const domain = jQuery('#competitor-domain').val().trim();
            if (!domain) {
                alert('<?php esc_html_e('Please enter a competitor domain', 'ai-seo-client'); ?>');
                return;
            }
            const sitemapUrl = jQuery('#competitor-sitemap').val().trim();
            const fullSite = jQuery('#competitor-fullsite').is(':checked') ? 1 : 0;

            jQuery.post(ajaxurl, {
                action: 'sseo_ai_analyze_competitor',
                domain: domain,
                sitemap_url: sitemapUrl,
                full_site: fullSite,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error analyzing competitor');
                }
            });
        }
        
        function sseoViewCompetitorDetails(domain) {
            // Load competitor details into tabs
            sseoLoadKeywordStrategy(domain);
            sseoLoadContentCalendar(domain);
            sseoLoadWinLoss(domain);
            sseoLoadBacklinkStrategy(domain);
            sseoLoadAdCopy(domain);
        }
        
        function sseoRefreshCompetitor(domain, sitemapUrl, fullSite) {
            if (!confirm('<?php esc_html_e('Refresh competitor data? This may take a few minutes.', 'ai-seo-client'); ?>')) {
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'sseo_ai_analyze_competitor',
                domain: domain,
                sitemap_url: sitemapUrl || '',
                full_site: fullSite ? 1 : 0,
                force_refresh: true,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error refreshing competitor');
                }
            });
        }
        
        function sseoSwitchTab(event, tabId) {
            event.preventDefault();

            jQuery('.cr-tab-content').removeClass('cr-active');
            jQuery('.cr-tab-btn').removeClass('active');

            jQuery('#' + tabId).addClass('cr-active');
            jQuery(event.target).closest('.cr-tab-btn').addBack().filter('.cr-tab-btn').addClass('active');
        }
        
        function sseoLoadKeywordStrategy(domain) {
            jQuery('#keyword-strategy-content').html('<p>Loading...</p>');
            
            jQuery.get(ajaxurl, {
                action: 'sseo_ai_get_keyword_strategy',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#keyword-strategy-content').html(response.data.html);
                }
            });
        }
        
        function sseoLoadContentCalendar(domain) {
            jQuery('#content-calendar-content').html('<p>Loading...</p>');
            
            jQuery.get(ajaxurl, {
                action: 'sseo_ai_get_content_calendar',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#content-calendar-content').html(response.data.html);
                }
            });
        }
        
        function sseoLoadWinLoss(domain) {
            jQuery('#win-loss-content').html('<p>Loading...</p>');
            
            jQuery.get(ajaxurl, {
                action: 'sseo_ai_get_win_loss',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#win-loss-content').html(response.data.html);
                }
            });
        }
        
        function sseoLoadBacklinkStrategy(domain) {
            jQuery('#backlink-strategy-content').html('<p>Loading...</p>');
            
            jQuery.get(ajaxurl, {
                action: 'sseo_ai_get_backlink_strategy',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#backlink-strategy-content').html(response.data.html);
                }
            });
        }
        
        function sseoLoadAdCopy(domain) {
            jQuery('#ad-copy-content').html('<p>Loading...</p>');
            
            jQuery.get(ajaxurl, {
                action: 'sseo_ai_get_ad_copy',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#ad-copy-content').html(response.data.html);
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Analyze competitor comprehensively
     */
    public function analyzeCompetitor(string $domain, string $sitemapUrl = '', bool $fullSite = false): array
    {
        $cacheKey = 'sseo_competitor_' . md5($domain);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $analysis = [
            'domain' => $domain,
            'sitemap_url' => $sitemapUrl,
            'full_site' => $fullSite,
            'keyword_count' => 0,
            'content_strategy' => 'Unknown',
            'ai_content_percentage' => 0,
            'last_updated' => current_time('mysql'),
        ];

        // 1. Keyword Strategy Detection
        $keywordStrategy = $this->detectKeywordStrategy($domain);
        $analysis['keyword_count'] = count($keywordStrategy['keywords'] ?? []);
        $analysis['keyword_strategy'] = $keywordStrategy;

        // 2. Content Calendar Analysis (with custom sitemap support)
        $contentCalendar = $this->analyzeContentCalendar($domain, $sitemapUrl, $fullSite);
        $analysis['content_calendar'] = $contentCalendar;
        $analysis['content_strategy'] = $contentCalendar['strategy'] ?? 'Unknown';

        // 3. AI Content Detection
        $aiDetection = $this->detectAIGeneratedContent($domain);
        $analysis['ai_content_percentage'] = $aiDetection['percentage'] ?? 0;
        $analysis['ai_detection'] = $aiDetection;

        // 4. Win/Loss Analysis
        $winLoss = $this->performWinLossAnalysis($domain);
        $analysis['win_loss'] = $winLoss;

        // 5. Backlink Strategy
        $backlinkStrategy = $this->analyzeBacklinkStrategy($domain);
        $analysis['backlink_strategy'] = $backlinkStrategy;

        // 6. Ad Copy Analysis
        $adCopy = $this->analyzeAdCopy($domain);
        $analysis['ad_copy'] = $adCopy;

        set_transient($cacheKey, $analysis, DAY_IN_SECONDS);

        // Store in tracked competitors
        $competitors = get_option('sseo_ai_tracked_competitors', []);
        $competitors[$domain] = $analysis;
        update_option('sseo_ai_tracked_competitors', $competitors);

        return $analysis;
    }
    
    /**
     * Detect competitor keyword strategy
     */
    private function detectKeywordStrategy(string $domain): array
    {
        // Use SERP API to get competitor's ranking keywords
        $serpData = $this->dashboardAPI->makeRequest('/serp/competitor-keywords', [
            'domain' => $domain,
            'limit' => 100,
        ]);
        
        if (is_wp_error($serpData)) {
            return ['keywords' => [], 'strategy' => 'Unknown'];
        }
        
        $keywords = $serpData['keywords'] ?? [];
        
        // Analyze keyword patterns using AI
        $keywordList = array_column($keywords, 'keyword');
        $keywordText = implode(', ', array_slice($keywordList, 0, 50));
        
        $prompt = "Analyze this competitor's keyword strategy based on their ranking keywords:

Keywords: {$keywordText}

Identify:
1. Primary keyword themes/topics
2. Content strategy (informational, commercial, transactional)
3. Long-tail vs short-tail focus
4. Keyword difficulty targeting (easy, medium, hard)
5. Seasonal patterns if any

Provide a concise analysis (3-4 sentences):";
        
        $aiAnalysis = $this->llm->generateText($prompt, ['max_tokens' => 200]);
        
        return [
            'keywords' => $keywords,
            'total_keywords' => count($keywords),
            'strategy_analysis' => is_wp_error($aiAnalysis) ? 'Analysis unavailable' : trim($aiAnalysis),
            'keyword_themes' => $this->extractKeywordThemes($keywords),
        ];
    }
    
    /**
     * Extract keyword themes
     */
    private function extractKeywordThemes(array $keywords): array
    {
        $themes = [];
        
        foreach ($keywords as $kw) {
            $keyword = strtolower($kw['keyword'] ?? '');
            $words = explode(' ', $keyword);
            
            foreach ($words as $word) {
                if (strlen($word) > 4) { // Skip short words
                    if (!isset($themes[$word])) {
                        $themes[$word] = 0;
                    }
                    $themes[$word]++;
                }
            }
        }
        
        arsort($themes);
        return array_slice($themes, 0, 10, true);
    }
    
    /**
     * Analyze content publishing calendar.
     *
     * @param string $domain      Competitor domain.
     * @param string $sitemapUrl  Custom sitemap URL (empty = auto-detect).
     * @param bool   $fullSite    Follow all sub-sitemaps in a sitemap index.
     */
    private function analyzeContentCalendar(string $domain, string $sitemapUrl = '', bool $fullSite = false): array
    {
        // Determine which sitemap URL(s) to fetch
        $sitemapUrls = [];
        if (!empty($sitemapUrl)) {
            $sitemapUrls[] = $sitemapUrl;
        } else {
            // Auto-detect: try common sitemap locations
            $sitemapUrls[] = "https://{$domain}/sitemap.xml";
            $sitemapUrls[] = "https://{$domain}/sitemap_index.xml";
            $sitemapUrls[] = "https://{$domain}/sitemap-index.xml";
        }

        $posts = [];
        $visitedUrls = []; // Prevent infinite loops / duplicate fetches
        $maxSubSitemaps = 15;  // Safety limit for full-site mode
        $maxUrls = 2000;       // Safety limit for total URLs parsed

        foreach ($sitemapUrls as $url) {
            if (isset($visitedUrls[$url]) || count($posts) >= $maxUrls) {
                continue;
            }
            $visitedUrls[$url] = true;

            // wp_safe_*: URLs come from the competitor's own sitemap — block private/loopback targets (SSRF).
            $response = wp_safe_remote_get($url, ['timeout' => 20]);
            if (is_wp_error($response)) {
                continue;
            }

            $xml = wp_remote_retrieve_body($response);
            $parsed = $this->parseSitemapDates($xml, $fullSite, $visitedUrls, $maxSubSitemaps, $maxUrls, 0);
            $posts = array_merge($posts, $parsed['posts']);
            $visitedUrls = $parsed['visited'];
        }

        // Analyze publishing frequency
        $frequency = $this->calculatePublishingFrequency($posts);

        // Detect content strategy using AI
        $recentTitles = array_slice(array_column($posts, 'title'), 0, 20);
        $titlesText = implode("\n", $recentTitles);

        $prompt = "Analyze this competitor's content strategy based on recent article titles:

{$titlesText}

Identify:
1. Content type focus (how-to, listicles, reviews, news, etc.)
2. Target audience
3. Content depth (beginner, intermediate, advanced)
4. Publishing strategy

Provide a brief analysis (2-3 sentences):";

        $strategyAnalysis = $this->llm->generateText($prompt, ['max_tokens' => 150]);

        return [
            'posts' => $posts,
            'total_posts' => count($posts),
            'frequency' => $frequency,
            'strategy' => is_wp_error($strategyAnalysis) ? 'Unknown' : trim($strategyAnalysis),
        ];
    }

    /**
     * Parse sitemap XML for post dates.
     *
     * Handles both regular sitemaps (<urlset> with <url> entries) and
     * sitemap indexes (<sitemapindex> with <sitemap> entries). When
     * $followIndex is true, sub-sitemaps are fetched recursively.
     *
     * @param string $xml             Raw XML content.
     * @param bool   $followIndex     Whether to follow sitemap index sub-sitemaps.
     * @param array  $visited         Already-visited URLs (by reference).
     * @param int    $maxSubSitemaps  Max sub-sitemaps to follow.
     * @param int    $maxUrls         Max total URLs to collect.
     * @param int    $depth           Current recursion depth.
     * @return array{posts: array, visited: array}
     */
    private function parseSitemapDates(string $xml, bool $followIndex = false, array $visited = [], int $maxSubSitemaps = 15, int $maxUrls = 2000, int $depth = 0): array
    {
        $posts = [];

        try {
            // Suppress libxml warnings for malformed XML
            $previous = libxml_use_internal_errors(true);
            $sitemap = simplexml_load_string($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if ($sitemap === false) {
                return ['posts' => [], 'visited' => $visited];
            }

            // Check if this is a sitemap index (<sitemapindex> with <sitemap> children)
            $isSitemapIndex = $sitemap->getName() === 'sitemapindex'
                || (isset($sitemap->sitemap) && count($sitemap->sitemap) > 0);

            if ($isSitemapIndex && $followIndex && $depth < 3) {
                $subSitemapCount = 0;
                foreach ($sitemap->sitemap as $sub) {
                    if ($subSitemapCount >= $maxSubSitemaps || count($posts) >= $maxUrls) {
                        break;
                    }
                    $subLoc = (string)($sub->loc ?? '');
                    if (empty($subLoc) || isset($visited[$subLoc])) {
                        continue;
                    }
                    $visited[$subLoc] = true;
                    $subSitemapCount++;

                    $subResponse = wp_safe_remote_get($subLoc, ['timeout' => 20]);
                    if (is_wp_error($subResponse)) {
                        continue;
                    }
                    $subXml = wp_remote_retrieve_body($subResponse);
                    $subParsed = $this->parseSitemapDates($subXml, true, $visited, $maxSubSitemaps - $subSitemapCount, $maxUrls - count($posts), $depth + 1);
                    $posts = array_merge($posts, $subParsed['posts']);
                    $visited = $subParsed['visited'];
                }
            } else {
                // Regular sitemap — extract <url> entries
                foreach ($sitemap->url as $url) {
                    if (count($posts) >= $maxUrls) {
                        break;
                    }
                    $loc = (string)$url->loc;
                    $lastmod = (string)$url->lastmod;

                    // Extract title from URL — keep natural slug casing (no forced Title Case)
                    $path = parse_url($loc, PHP_URL_PATH);
                    $slug = basename($path);
                    $title = str_replace(['-', '_'], ' ', $slug);
                    // Sentence case: capitalize only the first character
                    $title = ucfirst($title);

                    $posts[] = [
                        'url' => $loc,
                        'title' => $title,
                        'date' => $lastmod,
                        'timestamp' => strtotime($lastmod),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Sitemap parsing failed
        }

        // Sort by date descending
        usort($posts, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return ['posts' => $posts, 'visited' => $visited];
    }
    
    /**
     * Calculate publishing frequency
     */
    private function calculatePublishingFrequency(array $posts): string
    {
        if (count($posts) < 2) {
            return 'Unknown';
        }
        
        $recent = array_slice($posts, 0, 30);
        $timestamps = array_column($recent, 'timestamp');
        
        $intervals = [];
        for ($i = 0; $i < count($timestamps) - 1; $i++) {
            $intervals[] = $timestamps[$i] - $timestamps[$i + 1];
        }
        
        $intervalCount = count($intervals);
        $avgInterval = $intervalCount > 0 ? array_sum($intervals) / $intervalCount : 0;
        $daysInterval = $avgInterval / DAY_IN_SECONDS;
        
        if ($daysInterval < 1) {
            return 'Multiple times per day';
        } elseif ($daysInterval < 2) {
            return 'Daily';
        } elseif ($daysInterval < 4) {
            return '2-3 times per week';
        } elseif ($daysInterval < 8) {
            return 'Weekly';
        } elseif ($daysInterval < 15) {
            return 'Bi-weekly';
        } else {
            return 'Monthly or less';
        }
    }
    
    /**
     * Detect AI-generated content
     */
    private function detectAIGeneratedContent(string $domain): array
    {
        $posts = $this->getCompetitorPosts($domain, 10);
        $aiDetected = 0;
        $totalAnalyzed = 0;
        $details = [];
        
        foreach ($posts as $post) {
            $content = $this->scrapePostContent($post['url']);
            if (empty($content)) {
                continue;
            }
            
            $isAI = $this->isContentAIGenerated($content);
            $totalAnalyzed++;
            
            if ($isAI['is_ai']) {
                $aiDetected++;
            }
            
            $details[] = [
                'url' => $post['url'],
                'title' => $post['title'],
                'is_ai' => $isAI['is_ai'],
                'confidence' => $isAI['confidence'],
                'indicators' => $isAI['indicators'],
            ];
        }
        
        $percentage = $totalAnalyzed > 0 ? round(($aiDetected / $totalAnalyzed) * 100) : 0;
        
        return [
            'percentage' => $percentage,
            'ai_detected' => $aiDetected,
            'total_analyzed' => $totalAnalyzed,
            'details' => $details,
        ];
    }
    
    /**
     * Check if content is AI-generated
     */
    private function isContentAIGenerated(string $content): array
    {
        $excerpt = mb_substr(strip_tags($content), 0, 1000);
        
        $prompt = "Analyze if this content was likely generated by AI (ChatGPT, GPT-4, etc.):

{$excerpt}

Look for AI indicators:
- Repetitive phrasing patterns
- Overly formal or generic language
- Lack of personal anecdotes or unique insights
- Perfect grammar with no personality
- Generic transitional phrases

Respond in JSON format:
{
  \"is_ai\": true/false,
  \"confidence\": 0-100,
  \"indicators\": [\"list of detected AI patterns\"]
}";
        
        $response = $this->llm->generateText($prompt, ['max_tokens' => 200]);
        
        if (is_wp_error($response)) {
            return ['is_ai' => false, 'confidence' => 0, 'indicators' => []];
        }
        
        $json = preg_replace('/```json\s*|\s*```/', '', $response);
        $data = json_decode($json, true);
        
        if (!$data) {
            return ['is_ai' => false, 'confidence' => 0, 'indicators' => []];
        }
        
        return $data;
    }
    
    /**
     * Get competitor posts
     */
    private function getCompetitorPosts(string $domain, int $limit = 10): array
    {
        $sitemapUrl = "https://{$domain}/sitemap.xml";
        $response = wp_safe_remote_get($sitemapUrl, ['timeout' => 15]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $xml = wp_remote_retrieve_body($response);
        $posts = $this->parseSitemapDates($xml);
        
        return array_slice($posts, 0, $limit);
    }
    
    /**
     * Scrape post content
     */
    private function scrapePostContent(string $url): string
    {
        $response = wp_safe_remote_get($url, [
            'timeout' => 20,
            'user-agent' => 'Mozilla/5.0 (compatible; SSEOBot/1.0)',
        ]);
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Extract main content (simplified - would need better extraction)
        if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $matches)) {
            return $matches[1];
        } elseif (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * Perform win/loss analysis
     */
    private function performWinLossAnalysis(string $domain): array
    {
        $currentDomain = parse_url(get_site_url(), PHP_URL_HOST);
        
        // Get overlapping keywords
        $overlappingKeywords = $this->dashboardAPI->makeRequest('/serp/keyword-overlap', [
            'domain1' => $currentDomain,
            'domain2' => $domain,
        ]);
        
        if (is_wp_error($overlappingKeywords)) {
            return ['wins' => 0, 'losses' => 0, 'ties' => 0, 'keywords' => []];
        }
        
        $wins = 0;
        $losses = 0;
        $ties = 0;
        $analysis = [];
        
        foreach ($overlappingKeywords['keywords'] ?? [] as $kw) {
            $myRank = $kw['rank_domain1'] ?? 999;
            $theirRank = $kw['rank_domain2'] ?? 999;
            
            if ($myRank < $theirRank) {
                $wins++;
                $status = 'win';
            } elseif ($myRank > $theirRank) {
                $losses++;
                $status = 'loss';
            } else {
                $ties++;
                $status = 'tie';
            }
            
            // Analyze why we win or lose
            $reason = $this->analyzeWinLossReason($kw, $status);
            
            $analysis[] = [
                'keyword' => $kw['keyword'],
                'my_rank' => $myRank,
                'their_rank' => $theirRank,
                'status' => $status,
                'reason' => $reason,
            ];
        }
        
        return [
            'wins' => $wins,
            'losses' => $losses,
            'ties' => $ties,
            'total' => count($analysis),
            'win_rate' => count($analysis) > 0 ? round(($wins / count($analysis)) * 100) : 0,
            'keywords' => $analysis,
        ];
    }
    
    /**
     * Analyze win/loss reason
     */
    private function analyzeWinLossReason(array $keyword, string $status): string
    {
        // This would analyze various factors:
        // - Content quality/length
        // - Backlinks
        // - Domain authority
        // - On-page SEO
        // - User engagement metrics
        
        $reasons = [];
        
        if ($status === 'win') {
            $reasons[] = 'Better on-page optimization';
        } else {
            $reasons[] = 'Competitor has stronger backlink profile';
        }
        
        return implode(', ', $reasons);
    }
    
    /**
     * Analyze backlink strategy
     */
    private function analyzeBacklinkStrategy(string $domain): array
    {
        $backlinks = $this->getCompetitorBacklinks($domain);

        // Analyze patterns
        $linkTypes = $this->categorizeBacklinks($backlinks);
        $topSources = $this->getTopBacklinkSources($backlinks);
        $anchorStrategy = $this->analyzeAnchorTextStrategy($backlinks, $domain);
        $dofollow = count(array_filter($backlinks, fn($link) => $link['link_type'] === 'dofollow'));

        return [
            'total_backlinks' => count($backlinks),
            'dofollow_percentage' => $backlinks ? round($dofollow / count($backlinks) * 100, 1) : 0,
            'link_types' => $linkTypes,
            'top_sources' => $topSources,
            'anchor_strategy' => $anchorStrategy,
            'strategy_summary' => $this->summarizeBacklinkStrategy($backlinks, $linkTypes, $topSources),
        ];
    }

    /**
     * Get competitor backlinks (normalized items, see BacklinkAnalyzer::getBacklinkItems)
     */
    private function getCompetitorBacklinks(string $domain): array
    {
        if (!$this->backlinkAnalyzer) {
            return [];
        }

        return $this->backlinkAnalyzer->getBacklinkItems($domain);
    }

    /**
     * Categorize backlinks
     */
    private function categorizeBacklinks(array $backlinks): array
    {
        $categories = [
            'guest_posts' => 0,
            'directories' => 0,
            'forums' => 0,
            'social' => 0,
            'editorial' => 0,
            'other' => 0,
        ];

        if (!$this->backlinkAnalyzer) {
            return $categories;
        }

        foreach ($backlinks as $link) {
            $categories[$this->backlinkAnalyzer->classifyLink($link)]++;
        }

        return $categories;
    }

    /**
     * Get top backlink sources: [{domain, links, domain_rating}]
     */
    private function getTopBacklinkSources(array $backlinks): array
    {
        $sources = [];

        foreach ($backlinks as $link) {
            $domain = $link['source_domain'] ?? '';
            if ($domain === '') {
                continue;
            }
            if (!isset($sources[$domain])) {
                $sources[$domain] = ['domain' => $domain, 'links' => 0, 'domain_rating' => 0];
            }
            $sources[$domain]['links']++;
            $sources[$domain]['domain_rating'] = max($sources[$domain]['domain_rating'], (int) $link['domain_rating']);
        }

        usort($sources, fn($a, $b) => [$b['domain_rating'], $b['links']] <=> [$a['domain_rating'], $a['links']]);
        return array_slice($sources, 0, 10);
    }

    /**
     * Analyze anchor text strategy
     */
    private function analyzeAnchorTextStrategy(array $backlinks, string $domain = ''): array
    {
        $anchors = [];
        $total = 0;
        $branded = 0;
        $generic = 0;
        $keywordRich = 0;

        $brandLabel = strtolower(explode('.', $domain)[0] ?? '');
        $genericPattern = '/^(click here|read more|here|website|link|visit|more info|klik hier|lees meer|hier|bekijk|meer info|deze website)$/i';

        foreach ($backlinks as $link) {
            $anchor = $link['anchor_text'] ?? '';
            if ($anchor === '') continue;

            $total++;
            $lower = mb_strtolower($anchor);
            if (($brandLabel !== '' && str_contains($lower, $brandLabel)) || preg_match('#^(https?://|www\.)#', $lower)) {
                $branded++;
            } elseif (preg_match($genericPattern, trim($lower))) {
                $generic++;
            } else {
                $keywordRich++;
            }

            $anchors[$anchor] = ($anchors[$anchor] ?? 0) + 1;
        }

        arsort($anchors);

        return [
            'top_anchors' => array_slice($anchors, 0, 10, true),
            'branded_percentage' => $total ? round($branded / $total * 100, 1) : 0,
            // Non-branded, non-generic anchors — the competitor's keyword targeting
            'exact_match_percentage' => $total ? round($keywordRich / $total * 100, 1) : 0,
            'generic_percentage' => $total ? round($generic / $total * 100, 1) : 0,
        ];
    }

    /**
     * Summarize backlink strategy
     */
    private function summarizeBacklinkStrategy(array $backlinks, array $linkTypes, array $topSources): string
    {
        if (empty($backlinks)) {
            return __('No backlink data available for this competitor.', 'ai-seo-client');
        }

        $labels = $this->getLinkTypeLabels();
        $classified = $linkTypes;
        unset($classified['other']);
        arsort($classified);
        $mainType = (string) array_key_first($classified);
        $mainShare = round(($classified[$mainType] ?? 0) / count($backlinks) * 100);

        $dofollow = count(array_filter($backlinks, fn($link) => $link['link_type'] === 'dofollow'));
        $parts = [];

        if ($mainShare > 0) {
            $parts[] = sprintf(
                __('Most recognizable link type: %1$s (%2$d%% of analyzed links).', 'ai-seo-client'),
                $labels[$mainType] ?? $mainType,
                $mainShare
            );
        }
        $parts[] = sprintf(__('%d%% of the links are dofollow.', 'ai-seo-client'), round($dofollow / count($backlinks) * 100));

        $strong = array_filter($topSources, fn($source) => $source['domain_rating'] >= 50);
        if ($strong) {
            $parts[] = sprintf(
                __('Strongest sources: %s.', 'ai-seo-client'),
                implode(', ', array_map(fn($source) => $source['domain'] . ' (DR ' . $source['domain_rating'] . ')', array_slice($strong, 0, 3)))
            );
        }

        return implode(' ', $parts);
    }

    private function getLinkTypeLabels(): array
    {
        return [
            'guest_posts' => __('Guest posts', 'ai-seo-client'),
            'directories' => __('Directories', 'ai-seo-client'),
            'forums' => __('Forums', 'ai-seo-client'),
            'social' => __('Social', 'ai-seo-client'),
            'editorial' => __('Editorial', 'ai-seo-client'),
            'other' => __('Other', 'ai-seo-client'),
        ];
    }

    /**
     * AJAX: Backlink strategy tab (HTML fragment)
     */
    public function ajaxGetBacklinkStrategy(): void
    {
        check_ajax_referer('sseo_competitor', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $domain = sanitize_text_field(wp_unslash($_GET['domain'] ?? ''));
        if ($domain === '') {
            wp_send_json_error(['message' => 'Domain required']);
        }

        $tracked = get_option('sseo_ai_tracked_competitors', []);
        $strategy = $tracked[$domain]['backlink_strategy'] ?? null;
        if (empty($strategy['total_backlinks']) || !isset($strategy['dofollow_percentage'])) {
            $strategy = $this->analyzeBacklinkStrategy($domain);
            if (isset($tracked[$domain])) {
                $tracked[$domain]['backlink_strategy'] = $strategy;
                update_option('sseo_ai_tracked_competitors', $tracked);
            }
        }

        wp_send_json_success(['html' => $this->renderBacklinkStrategy($strategy)]);
    }

    private function renderBacklinkStrategy(array $strategy): string
    {
        if (empty($strategy['total_backlinks'])) {
            return '<p>' . esc_html__('No backlink data available for this competitor. Check your backlink provider under Integrations.', 'ai-seo-client') . '</p>';
        }

        $labels = $this->getLinkTypeLabels();
        ob_start();
        ?>
        <p><?php echo esc_html($strategy['strategy_summary']); ?></p>
        <p>
            <?php echo esc_html(sprintf(__('Analyzed links: %d', 'ai-seo-client'), $strategy['total_backlinks'])); ?> ·
            <?php echo esc_html(sprintf(__('Dofollow: %s%%', 'ai-seo-client'), $strategy['dofollow_percentage'])); ?> ·
            <?php echo esc_html(sprintf(__('Branded anchors: %s%%', 'ai-seo-client'), $strategy['anchor_strategy']['branded_percentage'] ?? 0)); ?> ·
            <?php echo esc_html(sprintf(__('Keyword anchors: %s%%', 'ai-seo-client'), $strategy['anchor_strategy']['exact_match_percentage'] ?? 0)); ?>
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
            <table class="fyn-table">
                <thead><tr><th><?php esc_html_e('Link Type', 'ai-seo-client'); ?></th><th><?php esc_html_e('Links', 'ai-seo-client'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($strategy['link_types'] as $type => $count): ?>
                    <tr><td><?php echo esc_html($labels[$type] ?? $type); ?></td><td><?php echo esc_html(number_format((int) $count)); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="fyn-table">
                <thead><tr><th><?php esc_html_e('Top Sources', 'ai-seo-client'); ?></th><th><?php esc_html_e('DR', 'ai-seo-client'); ?></th><th><?php esc_html_e('Links', 'ai-seo-client'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($strategy['top_sources'] as $source): ?>
                    <tr><td><?php echo esc_html($source['domain']); ?></td><td><?php echo esc_html($source['domain_rating']); ?></td><td><?php echo esc_html($source['links']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <table class="fyn-table">
                <thead><tr><th><?php esc_html_e('Anchor Text', 'ai-seo-client'); ?></th><th><?php esc_html_e('Count', 'ai-seo-client'); ?></th></tr></thead>
                <tbody>
                <?php foreach (($strategy['anchor_strategy']['top_anchors'] ?? []) as $anchor => $count): ?>
                    <tr><td><?php echo esc_html((string) $anchor); ?></td><td><?php echo esc_html($count); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return (string) ob_get_clean();
    }
    
    /**
     * Analyze ad copy
     */
    private function analyzeAdCopy(string $domain): array
    {
        // This would use Google Ads API or scraping tools
        // Placeholder implementation
        
        return [
            'ads_found' => 0,
            'ad_copies' => [],
            'messaging_themes' => [],
            'cta_patterns' => [],
        ];
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/competitor/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyzeCompetitor'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    /**
     * AJAX: Analyze competitor
     */
    public function ajaxAnalyzeCompetitor(): void
    {
        check_ajax_referer('sseo_competitor', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $domain = sanitize_text_field($_POST['domain'] ?? '');
        $sitemapUrl = esc_url_raw($_POST['sitemap_url'] ?? '');
        $fullSite = !empty($_POST['full_site']) ? true : false;
        $forceRefresh = !empty($_POST['force_refresh']);

        if (empty($domain)) {
            wp_send_json_error(['message' => 'Domain required']);
        }

        // Clear cache on force refresh
        if ($forceRefresh) {
            delete_transient('sseo_competitor_' . md5($domain));
        }

        $analysis = $this->analyzeCompetitor($domain, $sitemapUrl, $fullSite);

        wp_send_json_success(['analysis' => $analysis]);
    }
    
    /**
     * AJAX: Detect AI content
     */
    public function ajaxDetectAIContent(): void
    {
        check_ajax_referer('sseo_competitor', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL required']);
        }
        
        $content = $this->scrapePostContent($url);
        $result = $this->isContentAIGenerated($content);
        
        wp_send_json_success(['result' => $result]);
    }
}

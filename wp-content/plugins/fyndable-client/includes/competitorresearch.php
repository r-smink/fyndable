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
    
    public function __construct(Settings $settings, LLMClient $llm, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->dashboardAPI = $dashboardAPI;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_analyze_competitor', [$this, 'ajaxAnalyzeCompetitor']);
        add_action('wp_ajax_sseo_ai_detect_ai_content', [$this, 'ajaxDetectAIContent']);
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
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: 0; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .sseo-ai-dashboard-card .button-primary { background: linear-gradient(135deg, #FF4D00 0%, #ec4899 100%); border: none; }
            .sseo-ai-dashboard-card .nav-tab-wrapper { border-bottom: 1px solid #e2e8f0; }
            .sseo-ai-dashboard-card .nav-tab { background: #f1f5f9; border: 1px solid #e2e8f0; border-bottom: none; }
            .sseo-ai-dashboard-card .nav-tab-active { background: #fff; border-bottom: 1px solid #fff; }
            .sseo-ai-dashboard-card .tab-content { padding: 20px; background: #fff; border: 1px solid #e2e8f0; margin-top: -1px; }
            .sseo-ai-dashboard-card table.widefat { border: 1px solid #e2e8f0; }
            .sseo-ai-dashboard-card table.widefat thead th { background: #f8fafc; color: #334155; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('AI-Powered Competitor Research', 'ai-seo-client'); ?></h1>
            </div>

            <div class="sseo-ai-content">
            <!-- Add Competitor -->
            <div class="sseo-ai-dashboard-card">
                <h2><?php esc_html_e('Track Competitor', 'ai-seo-client'); ?></h2>
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div>
                        <label for="competitor-domain">
                            <strong><?php esc_html_e('Competitor Domain:', 'ai-seo-client'); ?></strong>
                        </label><br>
                        <input type="text" id="competitor-domain" class="regular-text" 
                               placeholder="competitor.com">
                    </div>
                    <button type="button" class="button button-primary" onclick="sseoAddCompetitorTracking()">
                        <?php esc_html_e('Add & Analyze', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Competitor Overview -->
            <?php if (!empty($competitors)): ?>
            <div class="sseo-ai-dashboard-card">
                <h2><?php esc_html_e('Tracked Competitors', 'ai-seo-client'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Competitor', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Keywords', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Content Strategy', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('AI Content %', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Last Updated', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitors as $comp): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($comp['domain']); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html(number_format($comp['keyword_count'] ?? 0)); ?> keywords
                            </td>
                            <td>
                                <?php 
                                $strategy = $comp['content_strategy'] ?? 'Unknown';
                                echo esc_html($strategy);
                                ?>
                            </td>
                            <td>
                                <span style="color: <?php echo ($comp['ai_content_percentage'] ?? 0) > 50 ? '#d63638' : '#00a32a'; ?>;">
                                    <?php echo esc_html(($comp['ai_content_percentage'] ?? 0)); ?>%
                                </span>
                            </td>
                            <td>
                                <?php 
                                $lastUpdated = $comp['last_updated'] ?? '';
                                echo $lastUpdated ? esc_html(human_time_diff(strtotime($lastUpdated))) . ' ago' : 'Never';
                                ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoViewCompetitorDetails('<?php echo esc_js($comp['domain']); ?>')">
                                    <?php esc_html_e('View Details', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="button button-small" 
                                        onclick="sseoRefreshCompetitor('<?php echo esc_js($comp['domain']); ?>')">
                                    <?php esc_html_e('Refresh', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Detailed Analysis Tabs -->
            <div class="sseo-ai-dashboard-card">
                <h2><?php esc_html_e('Competitor Analysis', 'ai-seo-client'); ?></h2>
                
                <div id="competitor-tabs" style="margin-top: 15px;">
                    <nav class="nav-tab-wrapper">
                        <a href="#keyword-strategy" class="nav-tab nav-tab-active" onclick="sseoSwitchTab(event, 'keyword-strategy')">
                            <?php esc_html_e('Keyword Strategy', 'ai-seo-client'); ?>
                        </a>
                        <a href="#content-calendar" class="nav-tab" onclick="sseoSwitchTab(event, 'content-calendar')">
                            <?php esc_html_e('Content Calendar', 'ai-seo-client'); ?>
                        </a>
                        <a href="#win-loss" class="nav-tab" onclick="sseoSwitchTab(event, 'win-loss')">
                            <?php esc_html_e('Win/Loss Analysis', 'ai-seo-client'); ?>
                        </a>
                        <a href="#backlink-strategy" class="nav-tab" onclick="sseoSwitchTab(event, 'backlink-strategy')">
                            <?php esc_html_e('Backlink Strategy', 'ai-seo-client'); ?>
                        </a>
                        <a href="#ad-copy" class="nav-tab" onclick="sseoSwitchTab(event, 'ad-copy')">
                            <?php esc_html_e('Ad Copy Analysis', 'ai-seo-client'); ?>
                        </a>
                    </nav>
                    
                    <!-- Keyword Strategy Tab -->
                    <div id="keyword-strategy" class="tab-content" style="display: block;">
                        <h3><?php esc_html_e('Competitor Keyword Strategy', 'ai-seo-client'); ?></h3>
                        <div id="keyword-strategy-content">
                            <p><?php esc_html_e('Select a competitor to view their keyword strategy.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Content Calendar Tab -->
                    <div id="content-calendar" class="tab-content" style="display: none;">
                        <h3><?php esc_html_e('Content Publishing Calendar', 'ai-seo-client'); ?></h3>
                        <div id="content-calendar-content">
                            <p><?php esc_html_e('Select a competitor to view their content calendar.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Win/Loss Tab -->
                    <div id="win-loss" class="tab-content" style="display: none;">
                        <h3><?php esc_html_e('Win/Loss Analysis', 'ai-seo-client'); ?></h3>
                        <div id="win-loss-content">
                            <p><?php esc_html_e('Select a competitor to compare rankings.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Backlink Strategy Tab -->
                    <div id="backlink-strategy" class="tab-content" style="display: none;">
                        <h3><?php esc_html_e('Backlink Strategy', 'ai-seo-client'); ?></h3>
                        <div id="backlink-strategy-content">
                            <p><?php esc_html_e('Select a competitor to view their backlink strategy.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Ad Copy Tab -->
                    <div id="ad-copy" class="tab-content" style="display: none;">
                        <h3><?php esc_html_e('Ad Copy Analysis', 'ai-seo-client'); ?></h3>
                        <div id="ad-copy-content">
                            <p><?php esc_html_e('Select a competitor to view their ad copy.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoAddCompetitorTracking() {
            const domain = jQuery('#competitor-domain').val().trim();
            if (!domain) {
                alert('<?php esc_html_e('Please enter a competitor domain', 'ai-seo-client'); ?>');
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_analyze_competitor',
                domain: domain,
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
        
        function sseoRefreshCompetitor(domain) {
            if (!confirm('<?php esc_html_e('Refresh competitor data? This may take a few minutes.', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_analyze_competitor',
                domain: domain,
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
            
            // Hide all tabs
            jQuery('.tab-content').hide();
            jQuery('.nav-tab').removeClass('nav-tab-active');
            
            // Show selected tab
            jQuery('#' + tabId).show();
            jQuery(event.target).addClass('nav-tab-active');
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
    public function analyzeCompetitor(string $domain): array
    {
        $cacheKey = 'sseo_competitor_' . md5($domain);
        $cached = get_transient($cacheKey);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $analysis = [
            'domain' => $domain,
            'keyword_count' => 0,
            'content_strategy' => 'Unknown',
            'ai_content_percentage' => 0,
            'last_updated' => current_time('mysql'),
        ];
        
        // 1. Keyword Strategy Detection
        $keywordStrategy = $this->detectKeywordStrategy($domain);
        $analysis['keyword_count'] = count($keywordStrategy['keywords'] ?? []);
        $analysis['keyword_strategy'] = $keywordStrategy;
        
        // 2. Content Calendar Analysis
        $contentCalendar = $this->analyzeContentCalendar($domain);
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
     * Analyze content publishing calendar
     */
    private function analyzeContentCalendar(string $domain): array
    {
        // Scrape competitor's sitemap or recent posts
        $sitemapUrl = "https://{$domain}/sitemap.xml";
        $response = wp_remote_get($sitemapUrl, ['timeout' => 15]);
        
        if (is_wp_error($response)) {
            return ['posts' => [], 'strategy' => 'Unknown', 'frequency' => 'Unknown'];
        }
        
        $xml = wp_remote_retrieve_body($response);
        $posts = $this->parseSitemapDates($xml);
        
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
     * Parse sitemap for post dates
     */
    private function parseSitemapDates(string $xml): array
    {
        $posts = [];
        
        try {
            $sitemap = new \SimpleXMLElement($xml);
            
            foreach ($sitemap->url as $url) {
                $loc = (string)$url->loc;
                $lastmod = (string)$url->lastmod;
                
                // Extract title from URL
                $path = parse_url($loc, PHP_URL_PATH);
                $slug = basename($path);
                $title = ucwords(str_replace(['-', '_'], ' ', $slug));
                
                $posts[] = [
                    'url' => $loc,
                    'title' => $title,
                    'date' => $lastmod,
                    'timestamp' => strtotime($lastmod),
                ];
            }
        } catch (\Exception $e) {
            // Sitemap parsing failed
        }
        
        // Sort by date descending
        usort($posts, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
        
        return $posts;
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
        $response = wp_remote_get($sitemapUrl, ['timeout' => 15]);
        
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
        $response = wp_remote_get($url, [
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
        // Get competitor backlinks from Ahrefs/Semrush
        $backlinks = $this->getCompetitorBacklinks($domain);
        
        // Analyze patterns
        $linkTypes = $this->categorizeBacklinks($backlinks);
        $topSources = $this->getTopBacklinkSources($backlinks);
        $anchorStrategy = $this->analyzeAnchorTextStrategy($backlinks);
        
        return [
            'total_backlinks' => count($backlinks),
            'link_types' => $linkTypes,
            'top_sources' => $topSources,
            'anchor_strategy' => $anchorStrategy,
            'strategy_summary' => $this->summarizeBacklinkStrategy($linkTypes, $topSources),
        ];
    }
    
    /**
     * Get competitor backlinks
     */
    private function getCompetitorBacklinks(string $domain): array
    {
        // This would use Ahrefs or Semrush API
        // Placeholder implementation
        return [];
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
        
        // Categorization logic would go here
        
        return $categories;
    }
    
    /**
     * Get top backlink sources
     */
    private function getTopBacklinkSources(array $backlinks): array
    {
        $sources = [];
        
        foreach ($backlinks as $link) {
            $domain = parse_url($link['source_url'] ?? '', PHP_URL_HOST);
            if (!isset($sources[$domain])) {
                $sources[$domain] = 0;
            }
            $sources[$domain]++;
        }
        
        arsort($sources);
        return array_slice($sources, 0, 10, true);
    }
    
    /**
     * Analyze anchor text strategy
     */
    private function analyzeAnchorTextStrategy(array $backlinks): array
    {
        $anchors = [];
        
        foreach ($backlinks as $link) {
            $anchor = $link['anchor_text'] ?? '';
            if (empty($anchor)) continue;
            
            if (!isset($anchors[$anchor])) {
                $anchors[$anchor] = 0;
            }
            $anchors[$anchor]++;
        }
        
        arsort($anchors);
        
        return [
            'top_anchors' => array_slice($anchors, 0, 10, true),
            'branded_percentage' => 0, // Would calculate
            'exact_match_percentage' => 0, // Would calculate
        ];
    }
    
    /**
     * Summarize backlink strategy
     */
    private function summarizeBacklinkStrategy(array $linkTypes, array $topSources): string
    {
        return 'Competitor focuses on guest posting and editorial links from high-authority domains.';
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
        
        if (empty($domain)) {
            wp_send_json_error(['message' => 'Domain required']);
        }
        
        $analysis = $this->analyzeCompetitor($domain);
        
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

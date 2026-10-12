<?php

namespace SSEOAIClient;

/**
 * Advanced Backlink Features
 * 
 * Enhanced backlink analysis with:
 * - Broken backlink prospecting
 * - Competitor backlink targets
 * - Advanced anchor text analysis
 * - Link opportunity scoring
 */
class AdvancedBacklinks
{
    private Settings $settings;
    private LLMClient $llm;
    
    public function __construct(Settings $settings, LLMClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_find_broken_backlinks', [$this, 'ajaxFindBrokenBacklinks']);
        add_action('wp_ajax_sseo_ai_analyze_competitor_backlinks', [$this, 'ajaxAnalyzeCompetitorBacklinks']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-backlinks',
            __('Advanced Backlink Analysis', 'ai-seo-client'),
            __('Advanced Analysis', 'ai-seo-client'),
            'manage_options',
            'ai-seo-advanced-backlinks',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render advanced backlink dashboard
     */
    public function renderDashboard(): void
    {
        $brokenBacklinks = $this->findBrokenBacklinks();
        $competitorTargets = $this->getCompetitorBacklinkTargets();
        $anchorAnalysis = $this->getAnchorTextAnalysis();
        $prefilledCompetitor = isset($_GET['competitor']) ? sanitize_text_field($_GET['competitor']) : '';

        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-header p { margin: 10px 0 0 0; opacity: 0.8; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .sseo-ai-dashboard-card h3 { color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 10px; }
            .sseo-ai-metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 15px; }
            .sseo-ai-metric { text-align: center; padding: 15px; border-radius: 8px; }
            .sseo-ai-metric.branded { background: #e0f2fe; color: #0369a1; }
            .sseo-ai-metric.exact { background: #fef3c7; color: #92400e; }
            .sseo-ai-metric.partial { background: #e0f7fa; color: #0e7490; }
            .sseo-ai-metric.generic { background: #fce7f3; color: #be185d; }
            .sseo-ai-metric-value { font-size: 28px; font-weight: 700; margin: 0; }
            .sseo-ai-metric-label { margin: 5px 0 0; font-size: 14px; opacity: 0.9; }
            .sseo-ai-actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
            .sseo-ai-recommendations { margin-top: 20px; padding: 16px; background: #f0f9ff; border-left: 4px solid #379fd3; border-radius: 8px; }
            .sseo-ai-recommendations h4 { margin-top: 0; color: #111827; }
            .sseo-ai-modal { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 99999; }
            .sseo-ai-modal-content { background: #fff; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,.1); }
            .sseo-ai-modal-content textarea { width: 100%; height: 300px; margin-bottom: 15px; }
            @media (max-width: 768px) {
                .sseo-ai-metric-grid { grid-template-columns: repeat(2, 1fr); }
                .sseo-ai-content { padding: 20px; }
            }
            /* Dark theme overrides */
            body.fyndable-dark .sseo-ai-metric-value { color: inherit; }
            body.fyndable-dark .sseo-ai-metric.branded { background: #0c4a6e; color: #e0f2fe; }
            body.fyndable-dark .sseo-ai-metric.exact { background: #78350f; color: #fef3c7; }
            body.fyndable-dark .sseo-ai-metric.partial { background: #164e63; color: #cffafe; }
            body.fyndable-dark .sseo-ai-metric.generic { background: #831843; color: #fce7f3; }
            body.fyndable-dark .sseo-ai-recommendations { background: #371447; border-left-color: #8f39ac; }
            body.fyndable-dark .sseo-ai-recommendations h4 { color: #fff; }
            body.fyndable-dark .sseo-ai-modal-content { background: #1f2937; color: #fff; }
            body.fyndable-dark .sseo-ai-modal-content textarea { background: #374151; color: #fff; border-color: #4b5563; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Advanced Backlink Analysis', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Broken links, competitor targets & anchor risk analysis', 'ai-seo-client'); ?></p>
            </div>
            <div class="sseo-ai-content">
            
                <!-- Broken Backlink Prospecting -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Broken Backlink Opportunities', 'ai-seo-client'); ?></h2>
                    <p><?php esc_html_e('Find broken backlinks pointing to your competitors that you can reclaim.', 'ai-seo-client'); ?></p>

                    <div style="margin-bottom: 15px;">
                        <label for="competitor-domain-broken">
                            <strong><?php esc_html_e('Competitor Domain:', 'ai-seo-client'); ?></strong>
                        </label><br>
                        <input type="text" id="competitor-domain-broken" class="regular-text"
                               placeholder="competitor.com"
                               value="<?php echo esc_attr($prefilledCompetitor); ?>">
                        <button type="button" class="button button-primary" onclick="sseoFindBrokenBacklinks()">
                            <?php esc_html_e('Find Broken Links', 'ai-seo-client'); ?>
                        </button>
                    </div>

                    <?php if (!empty($brokenBacklinks)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Source URL', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Broken Target', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('DR', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Anchor Text', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Opportunity Score', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brokenBacklinks as $link): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($link['source_url']); ?>" target="_blank">
                                        <?php echo esc_html($this->truncateUrl($link['source_url'])); ?>
                                    </a>
                                </td>
                                <td>
                                    <code><?php echo esc_html($this->truncateUrl($link['target_url'])); ?></code>
                                </td>
                                <td>
                                    <strong style="color: <?php echo esc_attr($this->getDRColor($link['domain_rating'])); ?>;">
                                        <?php echo esc_html($link['domain_rating']); ?>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($link['anchor_text']); ?></td>
                                <td>
                                    <span style="color: <?php echo esc_attr($this->getScoreColor($link['opportunity_score'])); ?>;">
                                        <?php echo esc_html($link['opportunity_score']); ?>/100
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small"
                                            onclick="sseoCreateOutreachEmail('<?php echo esc_js($link['source_url']); ?>')">
                                        <?php esc_html_e('Generate Outreach', 'ai-seo-client'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p><?php esc_html_e('No broken backlink opportunities found yet. Enter a competitor domain above.', 'ai-seo-client'); ?></p>
                    <?php endif; ?>
                </div>
            
                <!-- Competitor Backlink Targets -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Competitor Backlink Targets', 'ai-seo-client'); ?></h2>
                    <p><?php esc_html_e('High-value domains linking to your competitors but not to you.', 'ai-seo-client'); ?></p>

                    <?php if (!empty($competitorTargets)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Target Domain', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('DR', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Links to Competitors', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Link Type', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Priority', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($competitorTargets as $target): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url('https://' . $target['domain']); ?>" target="_blank">
                                        <?php echo esc_html($target['domain']); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong style="color: <?php echo esc_attr($this->getDRColor($target['domain_rating'])); ?>;">
                                        <?php echo esc_html($target['domain_rating']); ?>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($target['competitor_links']); ?></td>
                                <td><?php echo esc_html($target['link_type']); ?></td>
                                <td>
                                    <span style="color: <?php echo esc_attr($this->getPriorityColor($target['priority'])); ?>;">
                                        <?php echo esc_html(ucfirst($target['priority'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small button-primary"
                                            onclick="sseoCreateOutreachEmail('<?php echo esc_js('https://' . $target['domain']); ?>')">
                                        <?php esc_html_e('Start Outreach', 'ai-seo-client'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p><?php esc_html_e('Add competitor domains in the main Backlink Analysis page to see targets.', 'ai-seo-client'); ?></p>
                    <?php endif; ?>
                </div>
            
                <!-- Advanced Anchor Text Analysis -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Anchor Text Analysis', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($anchorAnalysis)): ?>
                <div style="margin-bottom: 20px;">
                        <h3><?php esc_html_e('Anchor Text Distribution', 'ai-seo-client'); ?></h3>
                    <div class="sseo-ai-metric-grid">
                        <div class="sseo-ai-metric branded">
                            <div class="sseo-ai-metric-value"><?php echo esc_html($anchorAnalysis['branded_percentage']); ?>%</div>
                            <div class="sseo-ai-metric-label"><?php esc_html_e('Branded', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="sseo-ai-metric exact">
                            <div class="sseo-ai-metric-value"><?php echo esc_html($anchorAnalysis['exact_match_percentage']); ?>%</div>
                            <div class="sseo-ai-metric-label"><?php esc_html_e('Exact Match', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="sseo-ai-metric partial">
                            <div class="sseo-ai-metric-value"><?php echo esc_html($anchorAnalysis['partial_match_percentage']); ?>%</div>
                            <div class="sseo-ai-metric-label"><?php esc_html_e('Partial Match', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="sseo-ai-metric generic">
                            <div class="sseo-ai-metric-value"><?php echo esc_html($anchorAnalysis['generic_percentage']); ?>%</div>
                            <div class="sseo-ai-metric-label"><?php esc_html_e('Generic', 'ai-seo-client'); ?></div>
                        </div>
                    </div>
                </div>

                <h3><?php esc_html_e('Top Anchor Texts', 'ai-seo-client'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Anchor Text', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Count', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Percentage', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Risk Level', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anchorAnalysis['top_anchors'] as $anchor): ?>
                        <tr>
                            <td><strong><?php echo esc_html($anchor['text']); ?></strong></td>
                            <td><?php echo esc_html($anchor['type']); ?></td>
                            <td><?php echo esc_html(number_format($anchor['count'])); ?></td>
                            <td><?php echo esc_html($anchor['percentage']); ?>%</td>
                            <td>
                                <span style="color: <?php echo esc_attr($this->getRiskColor($anchor['risk'])); ?>;">
                                    <?php echo esc_html(ucfirst($anchor['risk'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="sseo-ai-recommendations">
                    <h4><?php esc_html_e('AI Recommendations', 'ai-seo-client'); ?></h4>
                    <div id="anchor-recommendations">
                        <?php echo wp_kses_post($this->getAnchorRecommendations($anchorAnalysis)); ?>
                    </div>
                </div>
                <?php else: ?>
                <p><?php esc_html_e('No anchor text data available yet.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            </div>
        </div>

        <script>
        function sseoFindBrokenBacklinks() {
            const domain = jQuery('#competitor-domain-broken').val().trim();
            if (!domain) {
                alert('<?php esc_html_e('Please enter a competitor domain', 'ai-seo-client'); ?>');
                return;
            }

            jQuery.post(ajaxurl, {
                action: 'sseo_ai_find_broken_backlinks',
                domain: domain,
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error finding broken backlinks');
                }
            });
        }

        function sseoCreateOutreachEmail(sourceUrl) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_outreach',
                source_url: sourceUrl,
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    const email = response.data.email;
                    const modal = jQuery('<div class="sseo-ai-modal">' +
                        '<div class="sseo-ai-modal-content">' +
                        '<h2><?php esc_html_e('Outreach Email Template', 'ai-seo-client'); ?></h2>' +
                        '<textarea readonly>' + email + '</textarea>' +
                        '<button class="button button-primary" onclick="jQuery(this).closest(\'.sseo-ai-modal\').remove()"><?php esc_html_e('Close', 'ai-seo-client'); ?></button>' +
                        '</div>' +
                        '</div>');
                    jQuery('body').append(modal);
                }
            });
        }

        function sseoStartOutreach(domain) {
            sseoCreateOutreachEmail(domain);
        }
        </script>

        <style>
        .sseo-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
        }
        .sseo-modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
        }
        </style>
        <?php
    }
    
    /**
     * Find broken backlinks
     */
    public function findBrokenBacklinks(string $competitorDomain = ''): array
    {
        if (empty($competitorDomain)) {
            return get_option('sseo_ai_broken_backlinks', []);
        }
        
        // Get competitor's backlinks from Ahrefs/Semrush
        $backlinks = $this->getCompetitorBacklinks($competitorDomain);
        $brokenLinks = [];
        
        foreach ($backlinks as $link) {
            // Check if target URL is broken (404)
            $response = wp_remote_head($link['target_url'], ['timeout' => 5]);
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) === 404) {
                $opportunityScore = $this->calculateOpportunityScore($link);
                
                $brokenLinks[] = [
                    'source_url' => $link['source_url'],
                    'target_url' => $link['target_url'],
                    'domain_rating' => $link['domain_rating'] ?? 0,
                    'anchor_text' => $link['anchor_text'] ?? '',
                    'opportunity_score' => $opportunityScore,
                ];
            }
        }
        
        // Sort by opportunity score
        usort($brokenLinks, fn($a, $b) => $b['opportunity_score'] - $a['opportunity_score']);
        
        update_option('sseo_ai_broken_backlinks', $brokenLinks);
        
        return $brokenLinks;
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
     * Calculate opportunity score
     */
    private function calculateOpportunityScore(array $link): int
    {
        $score = 0;
        
        // Domain Rating (0-50 points)
        $dr = $link['domain_rating'] ?? 0;
        $score += min(50, $dr / 2);
        
        // Anchor text relevance (0-30 points)
        if (!empty($link['anchor_text']) && !in_array(strtolower($link['anchor_text']), ['click here', 'read more', 'here'])) {
            $score += 30;
        }
        
        // Link type (0-20 points)
        if (($link['link_type'] ?? '') === 'dofollow') {
            $score += 20;
        }
        
        return min(100, (int)$score);
    }
    
    /**
     * Get competitor backlink targets
     */
    public function getCompetitorBacklinkTargets(): array
    {
        $competitors = get_option('sseo_ai_competitor_domains', []);
        $targets = [];
        
        foreach ($competitors as $competitor) {
            $competitorBacklinks = $this->getCompetitorBacklinks($competitor);
            
            foreach ($competitorBacklinks as $link) {
                $domain = parse_url($link['source_url'], PHP_URL_HOST);
                
                if (!isset($targets[$domain])) {
                    $targets[$domain] = [
                        'domain' => $domain,
                        'domain_rating' => $link['domain_rating'] ?? 0,
                        'competitor_links' => 0,
                        'link_type' => $this->detectLinkType($link),
                        'priority' => 'medium',
                    ];
                }
                
                $targets[$domain]['competitor_links']++;
            }
        }
        
        // Calculate priority
        foreach ($targets as &$target) {
            if ($target['domain_rating'] >= 70 && $target['competitor_links'] >= 3) {
                $target['priority'] = 'high';
            } elseif ($target['domain_rating'] < 30 || $target['competitor_links'] < 2) {
                $target['priority'] = 'low';
            }
        }
        
        // Sort by priority and DR
        usort($targets, function($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $priorityDiff = $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
            
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            
            return $b['domain_rating'] - $a['domain_rating'];
        });
        
        return array_slice($targets, 0, 50);
    }
    
    /**
     * Detect link type
     */
    private function detectLinkType(array $link): string
    {
        // Analyze the link context to determine type
        $url = $link['source_url'] ?? '';
        
        if (stripos($url, '/blog/') !== false || stripos($url, '/article/') !== false) {
            return 'Editorial';
        } elseif (stripos($url, '/directory/') !== false) {
            return 'Directory';
        } elseif (stripos($url, '/forum/') !== false) {
            return 'Forum';
        } elseif (stripos($url, '/guest-post/') !== false) {
            return 'Guest Post';
        }
        
        return 'Other';
    }
    
    /**
     * Get anchor text analysis
     */
    public function getAnchorTextAnalysis(): array
    {
        $siteUrl = get_site_url();
        $domain = parse_url($siteUrl, PHP_URL_HOST);
        
        // Get all backlinks
        $backlinks = $this->getAllBacklinks($domain);
        
        if (empty($backlinks)) {
            return [];
        }
        
        $anchors = [];
        $totalAnchors = count($backlinks);
        
        $branded = 0;
        $exactMatch = 0;
        $partialMatch = 0;
        $generic = 0;
        
        $siteName = get_bloginfo('name');
        $targetKeywords = $this->getTargetKeywords();
        
        foreach ($backlinks as $link) {
            $anchor = strtolower($link['anchor_text'] ?? '');
            
            if (empty($anchor)) {
                continue;
            }
            
            // Categorize anchor
            if (stripos($anchor, strtolower($siteName)) !== false || stripos($anchor, $domain) !== false) {
                $type = 'Branded';
                $branded++;
            } elseif (in_array($anchor, array_map('strtolower', $targetKeywords))) {
                $type = 'Exact Match';
                $exactMatch++;
            } elseif ($this->containsKeyword($anchor, $targetKeywords)) {
                $type = 'Partial Match';
                $partialMatch++;
            } else {
                $type = 'Generic';
                $generic++;
            }
            
            if (!isset($anchors[$anchor])) {
                $anchors[$anchor] = [
                    'text' => $link['anchor_text'],
                    'type' => $type,
                    'count' => 0,
                ];
            }
            
            $anchors[$anchor]['count']++;
        }
        
        // Calculate percentages and risk
        foreach ($anchors as &$anchor) {
            $anchor['percentage'] = round(($anchor['count'] / $totalAnchors) * 100, 1);
            
            // Risk assessment
            if ($anchor['type'] === 'Exact Match' && $anchor['percentage'] > 30) {
                $anchor['risk'] = 'high';
            } elseif ($anchor['type'] === 'Exact Match' && $anchor['percentage'] > 15) {
                $anchor['risk'] = 'medium';
            } else {
                $anchor['risk'] = 'low';
            }
        }
        
        // Sort by count
        uasort($anchors, fn($a, $b) => $b['count'] - $a['count']);
        
        return [
            'branded_percentage' => round(($branded / $totalAnchors) * 100, 1),
            'exact_match_percentage' => round(($exactMatch / $totalAnchors) * 100, 1),
            'partial_match_percentage' => round(($partialMatch / $totalAnchors) * 100, 1),
            'generic_percentage' => round(($generic / $totalAnchors) * 100, 1),
            'top_anchors' => array_slice($anchors, 0, 20, true),
        ];
    }
    
    /**
     * Get all backlinks
     */
    private function getAllBacklinks(string $domain): array
    {
        // This would use Ahrefs or Semrush API
        // Placeholder implementation
        return [];
    }
    
    /**
     * Get target keywords
     */
    private function getTargetKeywords(): array
    {
        // Get main target keywords from settings or posts
        return ['seo', 'wordpress seo', 'seo plugin'];
    }
    
    /**
     * Check if anchor contains keyword
     */
    private function containsKeyword(string $anchor, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (stripos($anchor, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get anchor recommendations using AI
     */
    private function getAnchorRecommendations(array $analysis): string
    {
        $prompt = "Analyze this anchor text distribution and provide SEO recommendations:

Branded: {$analysis['branded_percentage']}%
Exact Match: {$analysis['exact_match_percentage']}%
Partial Match: {$analysis['partial_match_percentage']}%
Generic: {$analysis['generic_percentage']}%

Provide 3-5 specific recommendations to improve the anchor text profile and reduce risk of over-optimization penalties.";
        
        $recommendations = $this->llm->generateText($prompt, ['max_tokens' => 300]);
        
        if (is_wp_error($recommendations)) {
            return '<p>Unable to generate recommendations at this time.</p>';
        }
        
        return wpautop($recommendations);
    }
    
    /**
     * Helper methods
     */
    private function truncateUrl(string $url, int $length = 50): string
    {
        return strlen($url) > $length ? substr($url, 0, $length) . '...' : $url;
    }
    
    private function getDRColor(int $dr): string
    {
        if ($dr >= 70) return '#00a32a';
        if ($dr >= 40) return '#dba617';
        return '#d63638';
    }
    
    private function getScoreColor(int $score): string
    {
        if ($score >= 70) return '#00a32a';
        if ($score >= 40) return '#dba617';
        return '#d63638';
    }
    
    private function getPriorityColor(string $priority): string
    {
        switch ($priority) {
            case 'high': return '#d63638';
            case 'medium': return '#dba617';
            case 'low': return '#666';
            default: return '#666';
        }
    }
    
    private function getRiskColor(string $risk): string
    {
        switch ($risk) {
            case 'high': return '#d63638';
            case 'medium': return '#dba617';
            case 'low': return '#00a32a';
            default: return '#666';
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxFindBrokenBacklinks(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($domain)) {
            wp_send_json_error(['message' => 'Domain required']);
        }
        
        $brokenLinks = $this->findBrokenBacklinks($domain);
        
        wp_send_json_success(['broken_links' => $brokenLinks]);
    }
    
    public function ajaxAnalyzeCompetitorBacklinks(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $targets = $this->getCompetitorBacklinkTargets();
        
        wp_send_json_success(['targets' => $targets]);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/backlinks/broken', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetBrokenBacklinks'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/backlinks/competitor-targets', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCompetitorTargets'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    public function restGetBrokenBacklinks(): array
    {
        return ['broken_links' => $this->findBrokenBacklinks()];
    }
    
    public function restGetCompetitorTargets(): array
    {
        return ['targets' => $this->getCompetitorBacklinkTargets()];
    }
}

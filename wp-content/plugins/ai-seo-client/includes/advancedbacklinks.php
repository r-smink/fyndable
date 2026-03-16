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
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Advanced Backlink Analysis', 'ai-seo-client'); ?></h1>
            
            <!-- Broken Backlink Prospecting -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Broken Backlink Opportunities', 'ai-seo-client'); ?></h2>
                <p><?php esc_html_e('Find broken backlinks pointing to your competitors that you can reclaim.', 'ai-seo-client'); ?></p>
                
                <div style="margin-bottom: 15px;">
                    <label for="competitor-domain">
                        <strong><?php esc_html_e('Competitor Domain:', 'ai-seo-client'); ?></strong>
                    </label><br>
                    <input type="text" id="competitor-domain" class="regular-text" 
                           placeholder="competitor.com">
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
                                <strong style="color: <?php echo $this->getDRColor($link['domain_rating']); ?>;">
                                    <?php echo esc_html($link['domain_rating']); ?>
                                </strong>
                            </td>
                            <td><?php echo esc_html($link['anchor_text']); ?></td>
                            <td>
                                <span style="color: <?php echo $this->getScoreColor($link['opportunity_score']); ?>;">
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
            <div class="card" style="margin-bottom: 20px;">
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
                                <strong style="color: <?php echo $this->getDRColor($target['domain_rating']); ?>;">
                                    <?php echo esc_html($target['domain_rating']); ?>
                                </strong>
                            </td>
                            <td><?php echo esc_html($target['competitor_links']); ?></td>
                            <td><?php echo esc_html($target['link_type']); ?></td>
                            <td>
                                <span style="color: <?php echo $this->getPriorityColor($target['priority']); ?>;">
                                    <?php echo esc_html(ucfirst($target['priority'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small button-primary" 
                                        onclick="sseoStartOutreach('<?php echo esc_js($target['domain']); ?>')">
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
            <div class="card">
                <h2><?php esc_html_e('Anchor Text Analysis', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($anchorAnalysis)): ?>
                <div style="margin-bottom: 20px;">
                    <h3><?php esc_html_e('Anchor Text Distribution', 'ai-seo-client'); ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                        <div style="padding: 15px; background: #e7f3ff; border-radius: 4px;">
                            <div style="font-size: 24px; font-weight: bold; color: #2271b1;">
                                <?php echo esc_html($anchorAnalysis['branded_percentage']); ?>%
                            </div>
                            <div><?php esc_html_e('Branded', 'ai-seo-client'); ?></div>
                        </div>
                        <div style="padding: 15px; background: #fff3cd; border-radius: 4px;">
                            <div style="font-size: 24px; font-weight: bold; color: #856404;">
                                <?php echo esc_html($anchorAnalysis['exact_match_percentage']); ?>%
                            </div>
                            <div><?php esc_html_e('Exact Match', 'ai-seo-client'); ?></div>
                        </div>
                        <div style="padding: 15px; background: #d1ecf1; border-radius: 4px;">
                            <div style="font-size: 24px; font-weight: bold; color: #0c5460;">
                                <?php echo esc_html($anchorAnalysis['partial_match_percentage']); ?>%
                            </div>
                            <div><?php esc_html_e('Partial Match', 'ai-seo-client'); ?></div>
                        </div>
                        <div style="padding: 15px; background: #f8d7da; border-radius: 4px;">
                            <div style="font-size: 24px; font-weight: bold; color: #721c24;">
                                <?php echo esc_html($anchorAnalysis['generic_percentage']); ?>%
                            </div>
                            <div><?php esc_html_e('Generic', 'ai-seo-client'); ?></div>
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
                                <span style="color: <?php echo $this->getRiskColor($anchor['risk']); ?>;">
                                    <?php echo esc_html(ucfirst($anchor['risk'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                    <h4 style="margin-top: 0;"><?php esc_html_e('AI Recommendations', 'ai-seo-client'); ?></h4>
                    <div id="anchor-recommendations">
                        <?php echo wp_kses_post($this->getAnchorRecommendations($anchorAnalysis)); ?>
                    </div>
                </div>
                <?php else: ?>
                <p><?php esc_html_e('No anchor text data available yet.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoFindBrokenBacklinks() {
            const domain = jQuery('#competitor-domain').val().trim();
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
                    const modal = jQuery('<div class="sseo-modal">' +
                        '<div class="sseo-modal-content">' +
                        '<h2><?php esc_html_e('Outreach Email Template', 'ai-seo-client'); ?></h2>' +
                        '<textarea style="width: 100%; height: 300px;">' + email + '</textarea>' +
                        '<button class="button button-primary" onclick="jQuery(this).closest(\'.sseo-modal\').remove()">Close</button>' +
                        '</div>' +
                        '</div>');
                    jQuery('body').append(modal);
                }
            });
        }
        
        function sseoStartOutreach(domain) {
            window.location.href = '<?php echo admin_url('admin.php?page=ai-seo-outreach&domain='); ?>' + encodeURIComponent(domain);
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

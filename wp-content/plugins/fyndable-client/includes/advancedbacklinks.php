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
    private const BROKEN_OPTION = 'sseo_ai_broken_backlinks';

    private Settings $settings;
    private LLMClient $llm;
    /** Shared backlink data source (DataForSEO via Portal, provider fallbacks). */
    private ?BacklinkAnalyzer $analyzer;

    public function __construct(Settings $settings, LLMClient $llm, ?BacklinkAnalyzer $analyzer = null)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->analyzer = $analyzer;
    }

    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_find_broken_backlinks', [$this, 'ajaxFindBrokenBacklinks']);
        add_action('wp_ajax_sseo_ai_analyze_competitor_backlinks', [$this, 'ajaxAnalyzeCompetitorBacklinks']);
        add_action('wp_ajax_sseo_ai_generate_outreach', [$this, 'ajaxGenerateOutreach']);
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
            .sseo-ai-modal { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 99999; }
            .sseo-ai-modal-content { background: var(--card); border-radius: 14px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow: auto; box-shadow: var(--shadow-lg); }
            .sseo-ai-modal-content textarea { width: 100%; height: 300px; margin-bottom: 15px; background: var(--well); color: var(--text-b); border: 1px solid var(--card-border); border-radius: 9px; padding: 12px; font-size: 13px; }
        </style>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <!-- Page header -->
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Backlinks', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Advanced Backlink Analysis', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Broken links, competitor targets & anchor risk analysis', 'ai-seo-client'); ?></p>
                </div>
            </div>

            <!-- Broken Backlink Opportunities -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-editor-unlink"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Broken Backlink Opportunities', 'ai-seo-client'); ?></h3>
                </div>
                <p style="font-size:13px;color:var(--text-m);margin:0 0 16px;"><?php esc_html_e('Find broken backlinks pointing to your competitors that you can reclaim.', 'ai-seo-client'); ?></p>

                <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label for="competitor-domain-broken" style="font-size:12px;font-weight:600;color:var(--text-m);">
                            <?php esc_html_e('Competitor Domain:', 'ai-seo-client'); ?>
                        </label>
                        <input type="text" id="competitor-domain-broken" class="fyn-input"
                               placeholder="competitor.com" style="min-width:240px;"
                               value="<?php echo esc_attr($prefilledCompetitor); ?>">
                    </div>
                    <button type="button" class="fyn-btn fyn-btn-primary" onclick="sseoFindBrokenBacklinks()">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Find Broken Links', 'ai-seo-client'); ?>
                    </button>
                </div>

                <?php if (!empty($brokenBacklinks)): ?>
                <table class="fyn-table">
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
                                <a href="<?php echo esc_url($link['source_url']); ?>" target="_blank" style="color:var(--fyn-blue);">
                                    <?php echo esc_html($this->truncateUrl($link['source_url'])); ?>
                                </a>
                            </td>
                            <td>
                                <code style="font-size:12px;background:var(--well);padding:2px 6px;border-radius:4px;"><?php echo esc_html($this->truncateUrl($link['target_url'])); ?></code>
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
                                <button type="button" class="fyn-btn fyn-btn-outline" style="font-size:12px;padding:4px 10px;"
                                        onclick="sseoCreateOutreachEmail('<?php echo esc_js($link['source_url']); ?>', '<?php echo esc_js($link['target_url']); ?>')">
                                    <?php esc_html_e('Generate Outreach', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:var(--text-m);font-size:13px;"><?php esc_html_e('No broken backlink opportunities found yet. Enter a competitor domain above.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Competitor Backlink Targets -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-networking"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Competitor Backlink Targets', 'ai-seo-client'); ?></h3>
                </div>
                <p style="font-size:13px;color:var(--text-m);margin:0 0 16px;"><?php esc_html_e('High-value domains linking to your competitors but not to you.', 'ai-seo-client'); ?></p>

                <?php if (!empty($competitorTargets)): ?>
                <table class="fyn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Target Domain', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('DR', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Competitors Linked', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Link Type', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Priority', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitorTargets as $target): ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url('https://' . $target['domain']); ?>" target="_blank" style="color:var(--fyn-blue);">
                                    <?php echo esc_html($target['domain']); ?>
                                </a>
                            </td>
                            <td>
                                <strong style="color: <?php echo esc_attr($this->getDRColor($target['domain_rating'])); ?>;">
                                    <?php echo esc_html($target['domain_rating']); ?>
                                </strong>
                            </td>
                            <td title="<?php echo esc_attr(implode(', ', $target['competitors'] ?? [])); ?>"><?php echo esc_html($target['competitor_links']); ?></td>
                            <td><?php echo esc_html($target['link_type']); ?></td>
                            <td>
                                <span class="fyn-badge fyn-badge--<?php echo $target['priority'] === 'high' ? 'danger' : ($target['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                    <?php echo esc_html(ucfirst($target['priority'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="fyn-btn fyn-btn-primary" style="font-size:12px;padding:4px 10px;"
                                        onclick="sseoCreateOutreachEmail('<?php echo esc_js($target['sample_url'] ?? ('https://' . $target['domain'])); ?>', '')">
                                    <?php esc_html_e('Start Outreach', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:var(--text-m);font-size:13px;"><?php esc_html_e('Add competitor domains in the main Backlink Analysis page to see targets.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Advanced Anchor Text Analysis -->
            <div class="fyn-card">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-admin-links"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Anchor Text Analysis', 'ai-seo-client'); ?></h3>
                </div>

                <?php if (!empty($anchorAnalysis)): ?>
                <div style="margin-bottom:20px;">
                    <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 12px;"><?php esc_html_e('Anchor Text Distribution', 'ai-seo-client'); ?></h4>
                    <div class="fyn-stats-grid">
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-value" style="color:var(--fyn-blue);"><?php echo esc_html($anchorAnalysis['branded_percentage']); ?>%</div>
                            <div class="fyn-stat-label"><?php esc_html_e('Branded', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-value" style="color:var(--fyn-warning);"><?php echo esc_html($anchorAnalysis['exact_match_percentage']); ?>%</div>
                            <div class="fyn-stat-label"><?php esc_html_e('Exact Match', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-value" style="color:var(--fyn-info);"><?php echo esc_html($anchorAnalysis['partial_match_percentage']); ?>%</div>
                            <div class="fyn-stat-label"><?php esc_html_e('Partial Match', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="fyn-stat-tile">
                            <div class="fyn-stat-value" style="color:var(--fyn-purple);"><?php echo esc_html($anchorAnalysis['generic_percentage']); ?>%</div>
                            <div class="fyn-stat-label"><?php esc_html_e('Generic', 'ai-seo-client'); ?></div>
                        </div>
                    </div>
                </div>

                <h4 style="font-size:14px;font-weight:700;color:var(--text-h);margin:0 0 12px;"><?php esc_html_e('Top Anchor Texts', 'ai-seo-client'); ?></h4>
                <table class="fyn-table">
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
                                <span class="fyn-badge fyn-badge--<?php echo $anchor['risk'] === 'high' ? 'danger' : ($anchor['risk'] === 'medium' ? 'warning' : 'success'); ?>">
                                    <?php echo esc_html(ucfirst($anchor['risk'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="fyn-well" style="margin-top:20px;padding:16px;border-left:4px solid var(--fyn-blue);border-radius:9px;">
                    <h4 style="margin:0 0 8px;font-size:14px;font-weight:700;color:var(--text-h);"><?php esc_html_e('AI Recommendations', 'ai-seo-client'); ?></h4>
                    <div id="anchor-recommendations" style="font-size:13px;color:var(--text-b);">
                        <?php echo wp_kses_post($this->getAnchorRecommendations($anchorAnalysis)); ?>
                    </div>
                </div>
                <?php else: ?>
                <p style="color:var(--text-m);font-size:13px;"><?php esc_html_e('No anchor text data available yet.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
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

        function sseoCreateOutreachEmail(sourceUrl, brokenUrl) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_outreach',
                source_url: sourceUrl,
                broken_url: brokenUrl || '',
                nonce: '<?php echo wp_create_nonce('sseo_backlinks'); ?>'
            }, function(response) {
                if (response.success) {
                    const modal = jQuery('<div class="sseo-ai-modal">' +
                        '<div class="sseo-ai-modal-content">' +
                        '<h2 style="margin:0 0 16px;font-size:18px;font-weight:700;color:var(--text-h);"><?php echo esc_js(__('Outreach Email Template', 'ai-seo-client')); ?></h2>' +
                        '<textarea readonly></textarea>' +
                        '<button class="fyn-btn fyn-btn-primary" onclick="jQuery(this).closest(\'.sseo-ai-modal\').remove()"><?php echo esc_js(__('Close', 'ai-seo-client')); ?></button>' +
                        '</div>' +
                        '</div>');
                    // Set as value (not HTML) — the email is LLM output.
                    modal.find('textarea').val(response.data.email);
                    jQuery('body').append(modal);
                } else {
                    alert((response.data && response.data.message) || 'Error generating outreach email');
                }
            });
        }

        function sseoStartOutreach(domain) {
            sseoCreateOutreachEmail(domain);
        }
        </script>

        <?php
    }
    
    /**
     * Find broken backlinks pointing to a competitor (DataForSEO is_broken:
     * the linked page answers 4xx/5xx). Without a domain, returns the last
     * stored scan.
     */
    public function findBrokenBacklinks(string $competitorDomain = ''): array
    {
        if ($competitorDomain === '') {
            $stored = get_option(self::BROKEN_OPTION, []);
            return is_array($stored) ? $stored : [];
        }

        if (!$this->analyzer) {
            return [];
        }

        $competitorDomain = $this->analyzer->normalizeDomain($competitorDomain);
        if ($competitorDomain === '') {
            return [];
        }

        $links = $this->analyzer->getBacklinkItems($competitorDomain, [
            'filters'  => ['is_broken', '=', true],
            'order_by' => ['domain_from_rank,desc'],
            'limit'    => 200,
        ]);

        $brokenLinks = [];
        $seen = [];
        foreach ($links as $link) {
            if (!$link['is_broken'] || $link['source_url'] === '' || $link['target_url'] === '') {
                continue;
            }

            $key = $link['source_url'] . '|' . $link['target_url'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $brokenLinks[] = [
                'source_url' => $link['source_url'],
                'target_url' => $link['target_url'],
                'domain_rating' => $link['domain_rating'],
                'anchor_text' => $link['anchor_text'],
                'link_type' => $link['link_type'],
                'target_status' => $link['target_status'],
                'competitor' => $competitorDomain,
                'opportunity_score' => $this->calculateOpportunityScore($link),
            ];
        }

        usort($brokenLinks, fn($a, $b) => $b['opportunity_score'] <=> $a['opportunity_score']);
        $brokenLinks = array_slice($brokenLinks, 0, 100);

        update_option(self::BROKEN_OPTION, $brokenLinks, false);

        return $brokenLinks;
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
        if (!empty($link['anchor_text']) && !in_array(strtolower($link['anchor_text']), ['click here', 'read more', 'here', 'klik hier', 'lees meer', 'hier'], true)) {
            $score += 30;
        }

        // Link type (0-20 points)
        if (($link['link_type'] ?? '') === 'dofollow') {
            $score += 20;
        }

        // Spammy sources are poor outreach targets
        $score -= ($link['spam_score'] ?? 0) / 2;

        return max(0, min(100, (int) $score));
    }

    /**
     * Domains linking to competitors but not to us (link gap across all
     * configured competitors).
     */
    public function getCompetitorBacklinkTargets(): array
    {
        if (!$this->analyzer) {
            return [];
        }

        $targets = [];

        foreach ($this->analyzer->getCompetitorDomains() as $competitor) {
            foreach ($this->analyzer->getLinkGap($competitor, 200) as $link) {
                $domain = $link['source_domain'];

                if (!isset($targets[$domain])) {
                    $targets[$domain] = [
                        'domain' => $domain,
                        'domain_rating' => $link['domain_rating'],
                        'competitor_links' => 0,
                        'competitors' => [],
                        'sample_url' => $link['source_url'],
                        'link_type' => $this->detectLinkType($link),
                        'priority' => 'medium',
                    ];
                }

                // Gap lists are one-link-per-domain, so this counts competitors linked.
                $targets[$domain]['competitor_links']++;
                $targets[$domain]['competitors'][] = $competitor;
            }
        }

        // Calculate priority
        foreach ($targets as &$target) {
            if ($target['domain_rating'] >= 70 || ($target['domain_rating'] >= 40 && $target['competitor_links'] >= 2)) {
                $target['priority'] = 'high';
            } elseif ($target['domain_rating'] < 20) {
                $target['priority'] = 'low';
            }
        }
        unset($target);

        // Sort by priority, number of competitors linked and DR
        usort($targets, function($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            return [$priorityOrder[$b['priority']], $b['competitor_links'], $b['domain_rating']]
                <=> [$priorityOrder[$a['priority']], $a['competitor_links'], $a['domain_rating']];
        });

        return array_slice($targets, 0, 50);
    }

    /**
     * Detect link type
     */
    private function detectLinkType(array $link): string
    {
        $labels = [
            'guest_posts' => __('Guest Post', 'ai-seo-client'),
            'directories' => __('Directory', 'ai-seo-client'),
            'forums' => __('Forum', 'ai-seo-client'),
            'social' => __('Social', 'ai-seo-client'),
            'editorial' => __('Editorial', 'ai-seo-client'),
            'other' => __('Other', 'ai-seo-client'),
        ];

        $category = $this->analyzer ? $this->analyzer->classifyLink($link) : 'other';

        return $labels[$category] ?? $labels['other'];
    }

    /**
     * Get anchor text analysis for the own site
     */
    public function getAnchorTextAnalysis(): array
    {
        if (!$this->analyzer) {
            return [];
        }

        $domain = $this->analyzer->getOwnDomain();
        $backlinks = $this->analyzer->getBacklinkItems($domain);

        $brandTerms = $this->getBrandTerms($domain);
        $targetKeywords = $this->getTargetKeywords();

        $anchors = [];
        $totalAnchors = 0;
        $branded = 0;
        $exactMatch = 0;
        $partialMatch = 0;
        $generic = 0;

        foreach ($backlinks as $link) {
            $text = $link['anchor_text'] ?? '';
            $anchor = mb_strtolower($text);

            // Image links and empty anchors carry no anchor text signal
            if ($anchor === '') {
                continue;
            }
            $totalAnchors++;

            // Categorize anchor (naked URLs count as branded)
            if ($this->containsKeyword($anchor, $brandTerms) || preg_match('#^(https?://|www\.)#', $anchor)) {
                $type = 'Branded';
                $branded++;
            } elseif (in_array($anchor, $targetKeywords, true)) {
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
                    'text' => $text,
                    'type' => $type,
                    'count' => 0,
                ];
            }

            $anchors[$anchor]['count']++;
        }

        if ($totalAnchors === 0) {
            return [];
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
        unset($anchor);

        // Sort by count
        uasort($anchors, fn($a, $b) => $b['count'] - $a['count']);

        return [
            'total_anchors' => $totalAnchors,
            'branded_percentage' => round(($branded / $totalAnchors) * 100, 1),
            'exact_match_percentage' => round(($exactMatch / $totalAnchors) * 100, 1),
            'partial_match_percentage' => round(($partialMatch / $totalAnchors) * 100, 1),
            'generic_percentage' => round(($generic / $totalAnchors) * 100, 1),
            'top_anchors' => array_slice($anchors, 0, 20, true),
        ];
    }

    /**
     * Brand terms: site name, domain and its first label (e.g. "fyndable").
     */
    private function getBrandTerms(string $domain): array
    {
        $terms = [$domain];

        $siteName = mb_strtolower(trim(wp_strip_all_tags((string) get_bloginfo('name'))));
        if (mb_strlen($siteName) >= 3) {
            $terms[] = $siteName;
        }

        $label = explode('.', $domain)[0] ?? '';
        if (strlen($label) >= 3) {
            $terms[] = $label;
        }

        return array_values(array_unique($terms));
    }

    /**
     * Target keywords: active tracked keywords + focus keyphrases of published content.
     */
    private function getTargetKeywords(): array
    {
        global $wpdb;

        $keywords = [];

        $table = $wpdb->prefix . 'sseo_ai_tracked_keywords';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $keywords = array_merge($keywords, (array) $wpdb->get_col("SELECT keyword FROM {$table} WHERE active = 1 LIMIT 200"));
        }

        $keywords = array_merge($keywords, (array) $wpdb->get_col(
            "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sseo_ai_focus_keyphrase' AND pm.meta_value <> '' AND p.post_status = 'publish'
             LIMIT 200"
        ));

        $normalized = [];
        foreach ($keywords as $keyword) {
            $keyword = mb_strtolower(trim(wp_strip_all_tags((string) $keyword)));
            if (mb_strlen($keyword) >= 3) {
                $normalized[$keyword] = $keyword;
            }
        }

        return array_values($normalized);
    }

    /**
     * Check if anchor contains keyword
     */
    private function containsKeyword(string $anchor, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_stripos($anchor, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get anchor recommendations using AI (cached per distribution for a week)
     */
    private function getAnchorRecommendations(array $analysis): string
    {
        $distribution = [
            'branded' => $analysis['branded_percentage'],
            'exact' => $analysis['exact_match_percentage'],
            'partial' => $analysis['partial_match_percentage'],
            'generic' => $analysis['generic_percentage'],
        ];
        $locale = get_locale();
        $cacheKey = 'sseo_anchor_recs_' . md5(wp_json_encode($distribution) . $locale);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $prompt = "Analyze this anchor text distribution and provide SEO recommendations:

Branded: {$distribution['branded']}%
Exact Match: {$distribution['exact']}%
Partial Match: {$distribution['partial']}%
Generic: {$distribution['generic']}%

Provide 3-5 specific recommendations to improve the anchor text profile and reduce risk of over-optimization penalties.
Write in the language that matches the WordPress locale \"{$locale}\".";

        $recommendations = $this->llm->generateText($prompt, ['max_tokens' => 300]);

        if (is_wp_error($recommendations)) {
            return '<p>' . esc_html__('Unable to generate recommendations at this time.', 'ai-seo-client') . '</p>';
        }

        $html = wpautop($recommendations);
        set_transient($cacheKey, $html, WEEK_IN_SECONDS);

        return $html;
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
        
        $domain = sanitize_text_field(wp_unslash($_POST['domain'] ?? ''));
        $domain = $this->analyzer ? $this->analyzer->normalizeDomain($domain) : '';

        if (empty($domain)) {
            wp_send_json_error(['message' => __('Please enter a valid domain, e.g. competitor.com', 'ai-seo-client')]);
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
     * AJAX: Generate an outreach email for a link prospect.
     * POST: source_url (prospect page), broken_url? (broken competitor link on that page)
     */
    public function ajaxGenerateOutreach(): void
    {
        check_ajax_referer('sseo_backlinks', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $sourceUrl = esc_url_raw(wp_unslash($_POST['source_url'] ?? ''));
        $brokenUrl = esc_url_raw(wp_unslash($_POST['broken_url'] ?? ''));

        if ($sourceUrl === '') {
            wp_send_json_error(['message' => __('Source URL required', 'ai-seo-client')]);
        }

        $siteName = wp_strip_all_tags((string) get_bloginfo('name'));
        $siteUrl = home_url('/');
        $locale = get_locale();

        $context = $brokenUrl !== ''
            ? "The page {$sourceUrl} contains a broken link to {$brokenUrl}. Politely point this out and suggest a relevant page on our site as a replacement."
            : "We would like the page {$sourceUrl} to mention or link to our site as a useful resource for their readers.";

        $prompt = "Write a short, friendly and personal link-building outreach email (subject line + body, max 150 words).

Our site: {$siteName} ({$siteUrl})
{$context}

Rules:
- Do not invent facts, statistics or names; use placeholders like [Name], [Your name] and [URL of our page].
- No pushy sales language, no exaggerated flattery.
- Write in the language that matches the WordPress locale \"{$locale}\".
- Plain text only, no markdown.";

        $email = $this->llm->generateText($prompt, ['max_tokens' => 500]);

        if (is_wp_error($email)) {
            wp_send_json_error(['message' => $email->get_error_message()]);
        }

        wp_send_json_success(['email' => trim(wp_strip_all_tags((string) $email))]);
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

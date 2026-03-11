<?php

namespace SSEOAIClient;

/**
 * Smart Internal Linking
 * 
 * Enhanced internal linking with:
 * - Orphan page detection at scale
 * - Priority scoring algorithm
 * - Auto-suggested anchor text variants
 * - Link opportunity ranking
 */
class SmartInternalLinking
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
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_get_link_suggestions', [$this, 'ajaxGetLinkSuggestions']);
        add_action('wp_ajax_sseo_ai_detect_orphans', [$this, 'ajaxDetectOrphans']);
        
        // Scheduled orphan detection
        if (!wp_next_scheduled('sseo_ai_detect_orphans')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_detect_orphans');
        }
        add_action('sseo_ai_detect_orphans', [$this, 'detectOrphanPages']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Smart Internal Linking', 'ai-seo-client'),
            __('Internal Linking', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-internal-linking',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render internal linking dashboard
     */
    public function renderDashboard(): void
    {
        $orphanPages = $this->getOrphanPages();
        $linkOpportunities = $this->getLinkOpportunities();
        $linkStats = $this->getLinkingStats();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Smart Internal Linking', 'ai-seo-client'); ?></h1>
            
            <!-- Linking Statistics -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Internal Linking Overview', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo esc_html(number_format($linkStats['total_pages'])); ?>
                        </div>
                        <div><?php esc_html_e('Total Pages', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                            <?php echo esc_html(number_format($linkStats['orphan_pages'])); ?>
                        </div>
                        <div><?php esc_html_e('Orphan Pages', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #00a32a;">
                            <?php echo esc_html(number_format($linkStats['avg_internal_links'])); ?>
                        </div>
                        <div><?php esc_html_e('Avg Internal Links', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #856404;">
                            <?php echo esc_html(number_format($linkStats['opportunities'])); ?>
                        </div>
                        <div><?php esc_html_e('Link Opportunities', 'ai-seo-client'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Orphan Pages Detection -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Orphan Pages', 'ai-seo-client'); ?></h2>
                <p><?php esc_html_e('Pages with no internal links pointing to them.', 'ai-seo-client'); ?></p>
                
                <button type="button" class="button button-primary" onclick="sseoDetectOrphans()" style="margin-bottom: 15px;">
                    <?php esc_html_e('Scan for Orphan Pages', 'ai-seo-client'); ?>
                </button>
                
                <?php if (!empty($orphanPages)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Page', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Priority Score', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Suggested Sources', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanPages as $page): ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($page['id']); ?>">
                                    <?php echo esc_html($page['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($page['post_type']); ?></td>
                            <td>
                                <span style="color: <?php echo $this->getPriorityColor($page['priority_score']); ?>;">
                                    <?php echo esc_html($page['priority_score']); ?>/100
                                </span>
                            </td>
                            <td><?php echo esc_html($page['suggested_sources']); ?></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoFixOrphan(<?php echo $page['id']; ?>)">
                                    <?php esc_html_e('Auto-Fix', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="button button-small" 
                                        onclick="sseoViewSuggestions(<?php echo $page['id']; ?>)">
                                    <?php esc_html_e('View Suggestions', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #00a32a;">✓ <?php esc_html_e('No orphan pages detected!', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Link Opportunities -->
            <div class="card">
                <h2><?php esc_html_e('Top Link Opportunities', 'ai-seo-client'); ?></h2>
                <p><?php esc_html_e('AI-ranked opportunities to add internal links.', 'ai-seo-client'); ?></p>
                
                <?php if (!empty($linkOpportunities)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('From Page', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('To Page', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Suggested Anchor', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Relevance Score', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('SEO Impact', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkOpportunities as $opp): ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($opp['from_id']); ?>">
                                    <?php echo esc_html($this->truncate($opp['from_title'], 40)); ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo get_edit_post_link($opp['to_id']); ?>">
                                    <?php echo esc_html($this->truncate($opp['to_title'], 40)); ?>
                                </a>
                            </td>
                            <td>
                                <code><?php echo esc_html($opp['anchor_text']); ?></code>
                                <button type="button" class="button button-small" 
                                        onclick="sseoShowAnchorVariants(<?php echo $opp['from_id']; ?>, <?php echo $opp['to_id']; ?>)">
                                    <?php esc_html_e('Variants', 'ai-seo-client'); ?>
                                </button>
                            </td>
                            <td>
                                <span style="color: <?php echo $this->getScoreColor($opp['relevance_score']); ?>;">
                                    <?php echo esc_html($opp['relevance_score']); ?>%
                                </span>
                            </td>
                            <td>
                                <span style="color: <?php echo $this->getImpactColor($opp['seo_impact']); ?>;">
                                    <?php echo esc_html(ucfirst($opp['seo_impact'])); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small button-primary" 
                                        onclick="sseoAddLink(<?php echo $opp['from_id']; ?>, <?php echo $opp['to_id']; ?>, '<?php echo esc_js($opp['anchor_text']); ?>')">
                                    <?php esc_html_e('Add Link', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No link opportunities found. Try creating more content!', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoDetectOrphans() {
            const button = event.target;
            button.disabled = true;
            button.textContent = '<?php esc_html_e('Scanning...', 'ai-seo-client'); ?>';
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_detect_orphans',
                nonce: '<?php echo wp_create_nonce('sseo_linking'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error detecting orphans');
                    button.disabled = false;
                    button.textContent = '<?php esc_html_e('Scan for Orphan Pages', 'ai-seo-client'); ?>';
                }
            });
        }
        
        function sseoFixOrphan(pageId) {
            if (!confirm('<?php esc_html_e('Automatically add internal links to this orphan page?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_fix_orphan',
                page_id: pageId,
                nonce: '<?php echo wp_create_nonce('sseo_linking'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Links added successfully!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error fixing orphan');
                }
            });
        }
        
        function sseoViewSuggestions(pageId) {
            window.location.href = '<?php echo admin_url('post.php?action=edit&post='); ?>' + pageId + '#sseo-link-suggestions';
        }
        
        function sseoShowAnchorVariants(fromId, toId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_get_anchor_variants',
                from_id: fromId,
                to_id: toId,
                nonce: '<?php echo wp_create_nonce('sseo_linking'); ?>'
            }, function(response) {
                if (response.success) {
                    const variants = response.data.variants;
                    let html = '<div class="sseo-modal"><div class="sseo-modal-content">';
                    html += '<h2><?php esc_html_e('Anchor Text Variants', 'ai-seo-client'); ?></h2>';
                    html += '<ul style="list-style: none; padding: 0;">';
                    
                    variants.forEach(function(variant) {
                        html += '<li style="padding: 10px; margin: 5px 0; background: #f9f9f9; border-left: 3px solid #2271b1;">';
                        html += '<strong>' + variant.text + '</strong><br>';
                        html += '<small>' + variant.reason + '</small>';
                        html += '</li>';
                    });
                    
                    html += '</ul>';
                    html += '<button class="button" onclick="jQuery(this).closest(\'.sseo-modal\').remove()">Close</button>';
                    html += '</div></div>';
                    
                    jQuery('body').append(html);
                }
            });
        }
        
        function sseoAddLink(fromId, toId, anchorText) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_add_internal_link',
                from_id: fromId,
                to_id: toId,
                anchor_text: anchorText,
                nonce: '<?php echo wp_create_nonce('sseo_linking'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Link added successfully!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error adding link');
                }
            });
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
            max-height: 80vh;
            overflow-y: auto;
        }
        </style>
        <?php
    }
    
    /**
     * Add link suggestions meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-link-suggestions',
                __('Internal Link Suggestions', 'ai-seo-client'),
                [$this, 'renderLinkSuggestionsMetaBox'],
                $postType,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render link suggestions meta box
     */
    public function renderLinkSuggestionsMetaBox(\WP_Post $post): void
    {
        $suggestions = $this->getLinkSuggestionsForPost($post->ID);
        
        ?>
        <div class="sseo-link-suggestions">
            <?php if (!empty($suggestions)): ?>
            <p><strong><?php esc_html_e('Suggested Internal Links:', 'ai-seo-client'); ?></strong></p>
            <ul style="list-style: none; padding: 0;">
                <?php foreach (array_slice($suggestions, 0, 5) as $suggestion): ?>
                <li style="margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                    <div style="font-weight: bold; margin-bottom: 5px;">
                        <?php echo esc_html($this->truncate($suggestion['title'], 40)); ?>
                    </div>
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">
                        Anchor: <code><?php echo esc_html($suggestion['anchor']); ?></code>
                    </div>
                    <div style="font-size: 11px; color: #666;">
                        Relevance: <?php echo esc_html($suggestion['relevance']); ?>%
                    </div>
                    <button type="button" class="button button-small" style="margin-top: 5px;"
                            onclick="sseoInsertLink(<?php echo $suggestion['id']; ?>, '<?php echo esc_js($suggestion['anchor']); ?>')">
                        <?php esc_html_e('Insert Link', 'ai-seo-client'); ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p><?php esc_html_e('No link suggestions available.', 'ai-seo-client'); ?></p>
            <?php endif; ?>
            
            <button type="button" class="button" onclick="sseoRefreshSuggestions(<?php echo $post->ID; ?>)">
                <?php esc_html_e('Refresh Suggestions', 'ai-seo-client'); ?>
            </button>
        </div>
        
        <script>
        function sseoInsertLink(postId, anchorText) {
            const url = '<?php echo home_url('?p='); ?>' + postId;
            const link = '<a href="' + url + '">' + anchorText + '</a>';
            
            // Insert into editor
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                // Gutenberg
                const content = wp.data.select('core/editor').getEditedPostContent();
                wp.data.dispatch('core/editor').editPost({
                    content: content + ' ' + link
                });
            } else if (typeof tinymce !== 'undefined') {
                // Classic editor
                tinymce.activeEditor.insertContent(link);
            }
            
            alert('<?php esc_html_e('Link inserted!', 'ai-seo-client'); ?>');
        }
        
        function sseoRefreshSuggestions(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_get_link_suggestions',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_linking'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Detect orphan pages
     */
    public function detectOrphanPages(): array
    {
        global $wpdb;
        
        // Get all published posts and pages
        $allPosts = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
        ");
        
        $orphans = [];
        $siteUrl = home_url();
        
        foreach ($allPosts as $post) {
            $postUrl = get_permalink($post->ID);
            $hasInternalLinks = false;
            
            // Check if any other post links to this one
            foreach ($allPosts as $otherPost) {
                if ($otherPost->ID === $post->ID) {
                    continue;
                }
                
                if (stripos($otherPost->post_content, $postUrl) !== false ||
                    stripos($otherPost->post_content, '?p=' . $post->ID) !== false) {
                    $hasInternalLinks = true;
                    break;
                }
            }
            
            if (!$hasInternalLinks) {
                $priorityScore = $this->calculateOrphanPriority($post);
                $suggestedSources = $this->findSuggestedSources($post);
                
                $orphans[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'post_type' => $post->post_type,
                    'priority_score' => $priorityScore,
                    'suggested_sources' => count($suggestedSources) . ' pages',
                ];
            }
        }
        
        // Sort by priority score
        usort($orphans, fn($a, $b) => $b['priority_score'] - $a['priority_score']);
        
        update_option('sseo_ai_orphan_pages', $orphans);
        
        return $orphans;
    }
    
    /**
     * Calculate orphan priority score
     */
    private function calculateOrphanPriority(\WP_Post $post): int
    {
        $score = 0;
        
        // Recent posts are higher priority (0-30 points)
        $daysOld = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
        if ($daysOld < 30) {
            $score += 30;
        } elseif ($daysOld < 90) {
            $score += 20;
        } elseif ($daysOld < 180) {
            $score += 10;
        }
        
        // Content length (0-30 points)
        $wordCount = str_word_count(strip_tags($post->post_content));
        if ($wordCount > 1500) {
            $score += 30;
        } elseif ($wordCount > 800) {
            $score += 20;
        } elseif ($wordCount > 300) {
            $score += 10;
        }
        
        // Has featured image (0-20 points)
        if (has_post_thumbnail($post->ID)) {
            $score += 20;
        }
        
        // Traffic potential (0-20 points)
        $targetKeyword = get_post_meta($post->ID, '_sseo_ai_target_keyword', true);
        if (!empty($targetKeyword)) {
            $score += 20;
        }
        
        return min(100, $score);
    }
    
    /**
     * Find suggested source pages
     */
    private function findSuggestedSources(\WP_Post $orphanPost): array
    {
        global $wpdb;
        
        $sources = [];
        $orphanContent = strtolower(strip_tags($orphanPost->post_content));
        $orphanTitle = strtolower($orphanPost->post_title);
        
        // Extract keywords from orphan post
        $keywords = $this->extractKeywords($orphanContent . ' ' . $orphanTitle);
        
        // Find posts with similar content
        $allPosts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
            AND ID != %d
            LIMIT 100
        ", $orphanPost->ID));
        
        foreach ($allPosts as $post) {
            $relevanceScore = $this->calculateRelevance($post, $keywords);
            
            if ($relevanceScore >= 30) {
                $sources[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'relevance' => $relevanceScore,
                ];
            }
        }
        
        // Sort by relevance
        usort($sources, fn($a, $b) => $b['relevance'] - $a['relevance']);
        
        return array_slice($sources, 0, 10);
    }
    
    /**
     * Extract keywords from content
     */
    private function extractKeywords(string $content): array
    {
        $words = str_word_count($content, 1);
        $words = array_map('strtolower', $words);
        
        // Remove common stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $words = array_diff($words, $stopWords);
        
        // Count word frequency
        $wordCounts = array_count_values($words);
        arsort($wordCounts);
        
        return array_slice(array_keys($wordCounts), 0, 20);
    }
    
    /**
     * Calculate relevance between posts
     */
    private function calculateRelevance(\WP_Post $post, array $keywords): int
    {
        $content = strtolower(strip_tags($post->post_content . ' ' . $post->post_title));
        $matches = 0;
        
        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $matches++;
            }
        }
        
        return min(100, ($matches / count($keywords)) * 100);
    }
    
    /**
     * Get link opportunities
     */
    public function getLinkOpportunities(): array
    {
        global $wpdb;
        
        $opportunities = [];
        
        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
            ORDER BY post_date DESC
            LIMIT 50
        ");
        
        foreach ($posts as $fromPost) {
            foreach ($posts as $toPost) {
                if ($fromPost->ID === $toPost->ID) {
                    continue;
                }
                
                // Check if link already exists
                $toUrl = get_permalink($toPost->ID);
                if (stripos($fromPost->post_content, $toUrl) !== false) {
                    continue;
                }
                
                // Calculate relevance
                $fromKeywords = $this->extractKeywords($fromPost->post_content);
                $relevance = $this->calculateRelevance($toPost, $fromKeywords);
                
                if ($relevance >= 40) {
                    $anchorText = $this->generateAnchorText($fromPost, $toPost);
                    $seoImpact = $this->calculateSEOImpact($relevance);
                    
                    $opportunities[] = [
                        'from_id' => $fromPost->ID,
                        'from_title' => $fromPost->post_title,
                        'to_id' => $toPost->ID,
                        'to_title' => $toPost->post_title,
                        'anchor_text' => $anchorText,
                        'relevance_score' => $relevance,
                        'seo_impact' => $seoImpact,
                    ];
                }
            }
        }
        
        // Sort by relevance
        usort($opportunities, fn($a, $b) => $b['relevance_score'] - $a['relevance_score']);
        
        return array_slice($opportunities, 0, 20);
    }
    
    /**
     * Generate anchor text
     */
    private function generateAnchorText(\WP_Post $fromPost, \WP_Post $toPost): string
    {
        // Use AI to generate contextual anchor text
        $prompt = "Generate a natural anchor text for linking from this context:

From: {$fromPost->post_title}
To: {$toPost->post_title}

Provide only the anchor text (2-5 words), no explanation.";
        
        $anchor = $this->llm->generateText($prompt, ['max_tokens' => 20]);
        
        if (is_wp_error($anchor)) {
            return $toPost->post_title;
        }
        
        return trim($anchor);
    }
    
    /**
     * Calculate SEO impact
     */
    private function calculateSEOImpact(int $relevance): string
    {
        if ($relevance >= 80) return 'high';
        if ($relevance >= 60) return 'medium';
        return 'low';
    }
    
    /**
     * Get link suggestions for specific post
     */
    public function getLinkSuggestionsForPost(int $postId): array
    {
        global $wpdb;
        
        $post = get_post($postId);
        if (!$post) {
            return [];
        }
        
        $keywords = $this->extractKeywords($post->post_content . ' ' . $post->post_title);
        
        $relatedPosts = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
            AND ID != %d
            ORDER BY post_date DESC
            LIMIT 50
        ", $postId));
        
        $suggestions = [];
        
        foreach ($relatedPosts as $relatedPost) {
            $relevance = $this->calculateRelevance($relatedPost, $keywords);
            
            if ($relevance >= 40) {
                $anchor = $this->generateAnchorText($post, $relatedPost);
                
                $suggestions[] = [
                    'id' => $relatedPost->ID,
                    'title' => $relatedPost->post_title,
                    'anchor' => $anchor,
                    'relevance' => $relevance,
                ];
            }
        }
        
        usort($suggestions, fn($a, $b) => $b['relevance'] - $a['relevance']);
        
        return $suggestions;
    }
    
    /**
     * Get orphan pages
     */
    private function getOrphanPages(): array
    {
        return get_option('sseo_ai_orphan_pages', []);
    }
    
    /**
     * Get linking stats
     */
    private function getLinkingStats(): array
    {
        global $wpdb;
        
        $totalPages = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
        ");
        
        $orphanPages = count($this->getOrphanPages());
        $opportunities = count($this->getLinkOpportunities());
        
        // Calculate average internal links
        $posts = $wpdb->get_results("
            SELECT post_content
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
        ");
        
        $totalLinks = 0;
        $siteUrl = home_url();
        
        foreach ($posts as $post) {
            $linkCount = substr_count($post->post_content, '<a href="' . $siteUrl);
            $totalLinks += $linkCount;
        }
        
        $avgLinks = count($posts) > 0 ? round($totalLinks / count($posts), 1) : 0;
        
        return [
            'total_pages' => (int)$totalPages,
            'orphan_pages' => $orphanPages,
            'avg_internal_links' => $avgLinks,
            'opportunities' => $opportunities,
        ];
    }
    
    /**
     * Helper methods
     */
    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }
    
    private function getPriorityColor(int $score): string
    {
        if ($score >= 70) return '#d63638';
        if ($score >= 40) return '#dba617';
        return '#00a32a';
    }
    
    private function getScoreColor(int $score): string
    {
        if ($score >= 70) return '#00a32a';
        if ($score >= 40) return '#dba617';
        return '#d63638';
    }
    
    private function getImpactColor(string $impact): string
    {
        switch ($impact) {
            case 'high': return '#00a32a';
            case 'medium': return '#dba617';
            case 'low': return '#666';
            default: return '#666';
        }
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxGetLinkSuggestions(): void
    {
        check_ajax_referer('sseo_linking', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $suggestions = $this->getLinkSuggestionsForPost($postId);
        
        wp_send_json_success(['suggestions' => $suggestions]);
    }
    
    public function ajaxDetectOrphans(): void
    {
        check_ajax_referer('sseo_linking', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $orphans = $this->detectOrphanPages();
        
        wp_send_json_success(['orphans' => $orphans]);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/internal-links/suggestions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSuggestions'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/internal-links/orphans', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOrphans'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    public function restGetSuggestions(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $suggestions = $this->getLinkSuggestionsForPost($postId);
        
        return ['suggestions' => $suggestions];
    }
    
    public function restGetOrphans(): array
    {
        return ['orphans' => $this->getOrphanPages()];
    }
}

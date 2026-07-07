<?php

namespace SSEOAIClient;

class LinkAssistant
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_filter('the_content', [$this, 'autoInsertInternalLinks'], 20);
        add_action('save_post', [$this, 'updateLinkIndex'], 10, 2);
        add_action('admin_init', [$this, 'registerLinkGeniusSettings']);
        add_action('admin_post_sseo_ai_link_genius_add_rule', [$this, 'handleAddRule']);
        add_action('admin_post_sseo_ai_link_genius_delete_rule', [$this, 'handleDeleteRule']);
        add_action('admin_post_sseo_ai_link_genius_settings', [$this, 'handleSettings']);
    }

    public function registerLinkGeniusSettings(): void
    {
        register_setting('sseo_link_genius', 'sseo_link_genius_enabled', ['type' => 'boolean', 'default' => false]);
        register_setting('sseo_link_genius', 'sseo_link_genius_max_links', ['type' => 'integer', 'default' => 3]);
        register_setting('sseo_link_genius', 'sseo_link_genius_post_types', ['type' => 'array', 'default' => ['post', 'page']]);
        register_setting('sseo_link_genius', 'sseo_link_genius_open_new_tab', ['type' => 'boolean', 'default' => false]);
        register_setting('sseo_link_genius', 'sseo_link_genius_nofollow', ['type' => 'boolean', 'default' => false]);
        register_setting('sseo_link_genius', 'sseo_link_genius_rules', ['type' => 'array', 'default' => []]);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/suggest-links', [
            'methods' => 'POST',
            'callback' => [$this, 'restSuggestLinks'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/orphan-pages', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOrphanPages'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function suggestLinks(int $postId, string $content): array
    {
        $suggestions = [];
        $existingLinks = $this->getExistingLinks($content);

        // Get all posts except current
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'exclude' => [$postId],
            'no_found_rows' => true,
        ]);

        // Get current post keywords
        $focusKeyphrase = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        $secondary = get_post_meta($postId, '_sseo_ai_secondary_keyphrases', true);
        $currentKeywords = array_filter(array_merge(
            $focusKeyphrase ? [$focusKeyphrase] : [],
            $secondary ? explode(',', $secondary) : []
        ));

        foreach ($posts as $post) {
            $postKeywords = $this->extractKeywords($post);
            $relevance = $this->calculateRelevance($content, $post, $currentKeywords, $postKeywords);

            if ($relevance > 0.3) {
                $suggestions[] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'relevance' => round($relevance, 2),
                    'suggested_anchor' => $this->suggestAnchor($content, $post),
                    'already_linked' => in_array($post->ID, $existingLinks),
                ];
            }
        }

        // Sort by relevance
        usort($suggestions, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return array_slice($suggestions, 0, 10);
    }

    public function getOrphanPages(): array
    {
        global $wpdb;

        // Get all published posts
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
        ]);

        $orphans = [];

        foreach ($posts as $post) {
            // Count incoming internal links
            $incoming = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_content LIKE %s 
                AND post_status = 'publish' 
                AND ID != %d",
                '%' . $wpdb->esc_like(get_permalink($post->ID)) . '%',
                $post->ID
            ));

            if (!$incoming) {
                // Check if linked via slug
                $slug = $post->post_name;
                $incomingSlug = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} 
                    WHERE post_content LIKE %s 
                    AND post_status = 'publish' 
                    AND ID != %d",
                    '%href="/' . $wpdb->esc_like($slug) . '"%',
                    $post->ID
                ));

                if (!$incomingSlug) {
                    $orphans[] = [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'type' => $post->post_type,
                        'url' => get_permalink($post->ID),
                        'published' => $post->post_date,
                    ];
                }
            }
        }

        return $orphans;
    }

    public function autoInsertInternalLinks(string $content): string
    {
        if (!is_singular() || !in_the_loop()) {
            return $content;
        }

        $postId = get_the_ID();
        $maxLinks = (int) get_option('sseo_link_genius_max_links', 3);
        $enabled = get_option('sseo_link_genius_enabled', false);
        $openNewTab = get_option('sseo_link_genius_open_new_tab', false);
        $nofollow = get_option('sseo_link_genius_nofollow', false);
        $allowedTypes = get_option('sseo_link_genius_post_types', ['post', 'page']);

        if (!$enabled || !$postId) {
            return $content;
        }

        $currentType = get_post_type($postId);
        if (!in_array($currentType, $allowedTypes)) {
            return $content;
        }

        $linksAdded = 0;
        $linkAttrs = '';
        if ($openNewTab) {
            $linkAttrs .= ' target="_blank"';
        }
        if ($nofollow) {
            $linkAttrs .= ' rel="nofollow"';
        }

        // 1. Apply custom rules first
        $rules = get_option('sseo_link_genius_rules', []);
        if (!empty($rules)) {
            foreach ($rules as $rule) {
                if ($linksAdded >= $maxLinks) {
                    break;
                }
                if (empty($rule['keyword']) || empty($rule['url'])) {
                    continue;
                }
                $keyword = $rule['keyword'];
                $url = esc_url($rule['url']);
                $caseSensitive = !empty($rule['case_sensitive']);

                $pattern = '/\b' . preg_quote($keyword, '/') . '\b/' . ($caseSensitive ? '' : 'i');
                if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    $pos = $matches[0][1];
                    $before = substr($content, 0, $pos);
                    $after = substr($content, $pos + strlen($keyword));

                    if (!preg_match('/<a[^>]*>[^<]*$/', $before)) {
                        $content = $before . '<a href="' . $url . '"' . $linkAttrs . '>' . $keyword . '</a>' . $after;
                        $linksAdded++;
                    }
                }
            }
        }

        // 2. Then apply AI suggestions
        $suggestions = $this->suggestLinks($postId, $content);

        foreach ($suggestions as $suggestion) {
            if ($suggestion['already_linked'] || $linksAdded >= $maxLinks) {
                continue;
            }

            $anchor = $suggestion['suggested_anchor'];
            $url = esc_url($suggestion['url']);

            $pattern = '/\b' . preg_quote($anchor, '/') . '\b/i';
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $before = substr($content, 0, $pos);
                $after = substr($content, $pos + strlen($anchor));

                if (!preg_match('/<a[^>]*>[^<]*$/', $before)) {
                    $content = $before . '<a href="' . $url . '"' . $linkAttrs . '>' . $anchor . '</a>' . $after;
                    $linksAdded++;
                }
            }
        }

        return $content;
    }

    private function getExistingLinks(string $content): array
    {
        $linkedIds = [];

        if (preg_match_all('/href=[\'"](.*?)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $postId = url_to_postid($url);
                if ($postId) {
                    $linkedIds[] = $postId;
                }
            }
        }

        return array_unique($linkedIds);
    }

    private function extractKeywords(\WP_Post $post): array
    {
        $keywords = [];

        // From focus keyphrase
        $focus = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
        if ($focus) {
            $keywords[] = strtolower(trim($focus));
        }

        // From secondary keyphrases
        $secondary = get_post_meta($post->ID, '_sseo_ai_secondary_keyphrases', true);
        if ($secondary) {
            $keywords = array_merge($keywords, array_map('strtolower', array_map('trim', explode(',', $secondary))));
        }

        // From title
        $titleWords = array_filter(explode(' ', strtolower($post->post_title)));
        $keywords = array_merge($keywords, $titleWords);

        // From categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $cat) {
            $keywords[] = strtolower($cat->name);
        }

        // From tags
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $keywords[] = strtolower($tag->name);
            }
        }

        return array_unique(array_filter($keywords));
    }

    private function calculateRelevance(string $content, \WP_Post $targetPost, array $sourceKeywords, array $targetKeywords): float
    {
        $score = 0;
        $maxScore = 100;

        $contentLower = strtolower(strip_tags($content));
        $titleLower = strtolower($targetPost->post_title);

        // Direct title match in content
        if (stripos($contentLower, $titleLower) !== false) {
            $score += 30;
        }

        // Keyword overlap
        $commonKeywords = array_intersect($sourceKeywords, $targetKeywords);
        $score += count($commonKeywords) * 10;

        // Category match
        $sourceCats = wp_get_post_categories(0); // Current post cats
        $targetCats = wp_get_post_categories($targetPost->ID);
        $commonCats = array_intersect($sourceCats, $targetCats);
        $score += count($commonCats) * 5;

        // Content similarity (simple word overlap)
        $contentWords = array_count_values(str_word_count($contentLower, 1));
        $targetContent = strtolower(strip_tags($targetPost->post_content));
        $targetWords = array_count_values(str_word_count($targetContent, 1));

        $shared = 0;
        foreach ($contentWords as $word => $count) {
            if (isset($targetWords[$word]) && strlen($word) > 3) {
                $shared += min($count, $targetWords[$word]);
            }
        }

        $score += min(25, $shared);

        // Recency boost
        $daysOld = (time() - strtotime($targetPost->post_date)) / 86400;
        if ($daysOld < 30) {
            $score += 10;
        } elseif ($daysOld < 90) {
            $score += 5;
        }

        return min(1.0, $score / $maxScore);
    }

    private function suggestAnchor(string $content, \WP_Post $post): string
    {
        $title = $post->post_title;
        $focus = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);

        // Use focus keyphrase if it exists in content
        if ($focus && stripos($content, $focus) !== false) {
            return $focus;
        }

        // Use title words that appear in content
        $titleWords = explode(' ', $title);
        foreach ($titleWords as $word) {
            if (strlen($word) > 3 && stripos($content, $word) !== false) {
                return $word;
            }
        }

        return $title;
    }

    public function updateLinkIndex(int $postId, \WP_Post $post): void
    {
        if ($post->post_status !== 'publish') {
            return;
        }

        // Extract and store outgoing links
        $links = $this->extractLinks($post->post_content);
        update_post_meta($postId, '_sseo_ai_outgoing_links', $links);
    }

    private function extractLinks(string $content): array
    {
        $links = [];

        if (preg_match_all('/href=[\'"](.*?)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $postId = url_to_postid($url);
                if ($postId) {
                    $links[] = [
                        'post_id' => $postId,
                        'url' => $url,
                    ];
                }
            }
        }

        return $links;
    }

    public function restSuggestLinks(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $content = $request->get_param('content') ?: get_post_field('post_content', $postId);

        if (!$postId) {
            return new \WP_Error('no_post', __('Post ID required', 'ai-seo-client'));
        }

        return $this->suggestLinks($postId, $content);
    }

    public function restGetOrphanPages(): array
    {
        return $this->getOrphanPages();
    }

    public function getLinkStats(): array
    {
        global $wpdb;

        // Total internal links
        $postsWithLinks = $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_sseo_ai_outgoing_links' AND meta_value != 'a:0:{}'"
        );

        $totalPosts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page')");

        $orphans = count($this->getOrphanPages());

        return [
            'posts_with_links' => (int)$postsWithLinks,
            'total_posts' => (int)$totalPosts,
            'orphan_pages' => $orphans,
            'coverage_percent' => $totalPosts > 0 ? round(($postsWithLinks / $totalPosts) * 100, 1) : 0,
        ];
    }

    public function handleAddRule(): void
    {
        check_admin_referer('sseo_link_genius_add_rule');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $caseSensitive = isset($_POST['case_sensitive']);
        if ($keyword && $url) {
            $rules = get_option('sseo_link_genius_rules', []);
            $rules[] = ['keyword' => $keyword, 'url' => $url, 'case_sensitive' => $caseSensitive];
            update_option('sseo_link_genius_rules', $rules);
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-link-genius&rule_added=1'));
        exit;
    }

    public function handleDeleteRule(): void
    {
        check_admin_referer('sseo_link_genius_delete_rule_' . ($_POST['rule_index'] ?? -1));
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $index = (int) ($_POST['rule_index'] ?? -1);
        $rules = get_option('sseo_link_genius_rules', []);
        if (isset($rules[$index])) {
            array_splice($rules, $index, 1);
            update_option('sseo_link_genius_rules', $rules);
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-link-genius&rule_deleted=1'));
        exit;
    }

    public function handleSettings(): void
    {
        check_admin_referer('sseo_link_genius_settings');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        update_option('sseo_link_genius_enabled', isset($_POST['enabled']));
        update_option('sseo_link_genius_max_links', (int) ($_POST['max_links'] ?? 3));
        update_option('sseo_link_genius_open_new_tab', isset($_POST['open_new_tab']));
        update_option('sseo_link_genius_nofollow', isset($_POST['nofollow']));
        $postTypes = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : [];
        update_option('sseo_link_genius_post_types', $postTypes);
        wp_redirect(admin_url('admin.php?page=ai-seo-link-genius&settings_saved=1'));
        exit;
    }

    public function renderDashboard(): void
    {
        $enabled = get_option('sseo_link_genius_enabled', false);
        $maxLinks = (int) get_option('sseo_link_genius_max_links', 3);
        $openNewTab = get_option('sseo_link_genius_open_new_tab', false);
        $nofollow = get_option('sseo_link_genius_nofollow', false);
        $allowedTypes = get_option('sseo_link_genius_post_types', ['post', 'page']);
        $rules = get_option('sseo_link_genius_rules', []);
        $stats = $this->getLinkStats();
        $allPostTypes = get_post_types(['public' => true], 'objects');
        $internalTypes = ['attachment', 'aiseo_note', 'aiseo_prompt', 'aiseo_calendar'];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Link Genius', 'ai-seo-client'); ?></h1>
            <p><?php esc_html_e('Automatically add internal links to your content based on custom rules and AI-powered suggestions.', 'ai-seo-client'); ?></p>

            <?php if (isset($_GET['settings_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'ai-seo-client'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['rule_added'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Auto-link rule added.', 'ai-seo-client'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['rule_deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Rule deleted.', 'ai-seo-client'); ?></p></div>
            <?php endif; ?>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#2271b1;"><?php echo esc_html($stats['posts_with_links']); ?></div>
                    <div><?php esc_html_e('Posts with Links', 'ai-seo-client'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#00a32a;"><?php echo esc_html($stats['total_posts']); ?></div>
                    <div><?php esc_html_e('Total Posts', 'ai-seo-client'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#d63638;"><?php echo esc_html($stats['orphan_pages']); ?></div>
                    <div><?php esc_html_e('Orphan Pages', 'ai-seo-client'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#f0b400;"><?php echo esc_html($stats['coverage_percent']); ?>%</div>
                    <div><?php esc_html_e('Coverage', 'ai-seo-client'); ?></div>
                </div>
            </div>

            <!-- General Settings -->
            <h2><?php esc_html_e('General Settings', 'ai-seo-client'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sseo_link_genius_settings'); ?>
                <input type="hidden" name="action" value="sseo_link_genius_settings">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Auto-Linking', 'ai-seo-client'); ?></th>
                        <td><label><input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>> <?php esc_html_e('Automatically insert internal links on post content', 'ai-seo-client'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="max_links"><?php esc_html_e('Max Links Per Post', 'ai-seo-client'); ?></label></th>
                        <td><input type="number" name="max_links" id="max_links" value="<?php echo esc_attr($maxLinks); ?>" min="1" max="20" style="width:80px;"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post Types', 'ai-seo-client'); ?></th>
                        <td>
                            <?php foreach ($allPostTypes as $pt): ?>
                                <?php if (in_array($pt->name, $internalTypes)) continue; ?>
                                <label style="margin-right:15px;"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $allowedTypes)); ?>> <?php echo esc_html($pt->label); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Link Attributes', 'ai-seo-client'); ?></th>
                        <td>
                            <label style="margin-right:15px;"><input type="checkbox" name="open_new_tab" value="1" <?php checked($openNewTab); ?>> <?php esc_html_e('Open in new tab', 'ai-seo-client'); ?></label>
                            <label><input type="checkbox" name="nofollow" value="1" <?php checked($nofollow); ?>> <?php esc_html_e('Add nofollow', 'ai-seo-client'); ?></label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Settings', 'ai-seo-client')); ?>
            </form>

            <!-- Custom Auto-Link Rules -->
            <h2><?php esc_html_e('Custom Auto-Link Rules', 'ai-seo-client'); ?></h2>
            <p><?php esc_html_e('Define keywords that should automatically be linked to specific URLs. These rules are applied before AI suggestions.', 'ai-seo-client'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:20px;">
                <?php wp_nonce_field('sseo_link_genius_add_rule'); ?>
                <input type="hidden" name="action" value="sseo_link_genius_add_rule">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="keyword"><?php esc_html_e('Keyword', 'ai-seo-client'); ?></label></th>
                        <td><input type="text" name="keyword" id="keyword" class="regular-text" placeholder="<?php esc_attr_e('e.g. WordPress SEO', 'ai-seo-client'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="url"><?php esc_html_e('Target URL', 'ai-seo-client'); ?></label></th>
                        <td><input type="text" name="url" id="url" class="regular-text" placeholder="<?php esc_attr_e('https://example.com/wordpress-seo-guide', 'ai-seo-client'); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Case Sensitive', 'ai-seo-client'); ?></th>
                        <td><label><input type="checkbox" name="case_sensitive" value="1"> <?php esc_html_e('Match keyword case exactly', 'ai-seo-client'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(__('Add Rule', 'ai-seo-client'), 'secondary'); ?>
            </form>

            <?php if (!empty($rules)): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Target URL', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Case Sensitive', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rules as $index => $rule): ?>
                            <tr>
                                <td><strong><?php echo esc_html($rule['keyword']); ?></strong></td>
                                <td><code><?php echo esc_html($rule['url']); ?></code></td>
                                <td><?php echo !empty($rule['case_sensitive']) ? __('Yes', 'ai-seo-client') : __('No', 'ai-seo-client'); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Delete this rule?', 'ai-seo-client'); ?>')">
                                        <?php wp_nonce_field('sseo_link_genius_delete_rule_' . $index); ?>
                                        <input type="hidden" name="action" value="sseo_link_genius_delete_rule">
                                        <input type="hidden" name="rule_index" value="<?php echo esc_attr($index); ?>">
                                        <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('Delete', 'ai-seo-client'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php esc_html_e('No custom rules yet. Add one above to automatically link specific keywords.', 'ai-seo-client'); ?></p>
            <?php endif; ?>

            <!-- Orphan Pages -->
            <h2><?php esc_html_e('Orphan Pages', 'ai-seo-client'); ?></h2>
            <p><?php esc_html_e('Pages with no internal links pointing to them. Consider adding links from other posts.', 'ai-seo-client'); ?></p>
            <?php
            $orphans = $this->getOrphanPages();
            if (empty($orphans)): ?>
                <p><?php esc_html_e('No orphan pages found.', 'ai-seo-client'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Title', 'ai-seo-client'); ?></th><th><?php esc_html_e('Type', 'ai-seo-client'); ?></th><th><?php esc_html_e('URL', 'ai-seo-client'); ?></th><th><?php esc_html_e('Published', 'ai-seo-client'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($orphans, 0, 20) as $orphan): ?>
                            <tr>
                                <td><strong><?php echo esc_html($orphan['title']); ?></strong></td>
                                <td><?php echo esc_html($orphan['type']); ?></td>
                                <td><a href="<?php echo esc_url($orphan['url']); ?>" target="_blank"><?php echo esc_html($orphan['url']); ?></a></td>
                                <td><?php echo esc_html($orphan['published']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($orphans) > 20): ?>
                    <p><?php echo esc_html(sprintf(__('Showing 20 of %d orphan pages.', 'ai-seo-client'), count($orphans))); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

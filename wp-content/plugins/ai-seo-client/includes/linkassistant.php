<?php

namespace AISEOClient;

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
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoclient/v1', '/suggest-links', [
            'methods' => 'POST',
            'callback' => [$this, 'restSuggestLinks'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('aiseoclient/v1', '/orphan-pages', [
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
        $focusKeyphrase = get_post_meta($postId, '_aiseo_focus_keyphrase', true);
        $secondary = get_post_meta($postId, '_aiseo_secondary_keyphrases', true);
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
        $autoLink = $this->settings->get('auto_internal_links', 0);
        $maxLinks = $this->settings->get('max_auto_links', 3);

        if (!$autoLink || !$postId) {
            return $content;
        }

        $linksAdded = 0;
        $suggestions = $this->suggestLinks($postId, $content);

        foreach ($suggestions as $suggestion) {
            if ($suggestion['already_linked'] || $linksAdded >= $maxLinks) {
                continue;
            }

            $anchor = $suggestion['suggested_anchor'];
            $url = esc_url($suggestion['url']);

            // Find first occurrence and link it
            $pattern = '/\b' . preg_quote($anchor, '/') . '\b/i';
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                $before = substr($content, 0, $pos);
                $after = substr($content, $pos + strlen($anchor));

                // Check if already in a link
                if (!preg_match('/<a[^>]*>[^<]*$/', $before)) {
                    $content = $before . '<a href="' . $url . '">' . $anchor . '</a>' . $after;
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
        $focus = get_post_meta($post->ID, '_aiseo_focus_keyphrase', true);
        if ($focus) {
            $keywords[] = strtolower(trim($focus));
        }

        // From secondary keyphrases
        $secondary = get_post_meta($post->ID, '_aiseo_secondary_keyphrases', true);
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
        $focus = get_post_meta($post->ID, '_aiseo_focus_keyphrase', true);

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
        update_post_meta($postId, '_aiseo_outgoing_links', $links);
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
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_aiseo_outgoing_links' AND meta_value != 'a:0:{}'"
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
}

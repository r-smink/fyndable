<?php

namespace SSEOAIClient;

/**
 * Canonical URL Management
 * 
 * Outputs <link rel="canonical"> per page. Supports per-post overrides,
 * automatic canonical for archives, pagination, and taxonomy pages.
 * Comparable to Yoast Canonical, RankMath Canonical.
 */
class CanonicalUrl
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        // Remove WP default canonical (we handle it ourselves)
        remove_action('wp_head', 'rel_canonical');

        add_action('wp_head', [$this, 'outputCanonical'], 1);
        // Meta box moved to PostMetaBox tabbed container
        add_action('save_post', [$this, 'saveMeta'], 10, 2);
    }

    /**
     * Output canonical URL in <head>
     */
    public function outputCanonical(): void
    {
        if (is_admin()) {
            return;
        }

        $canonical = $this->getCanonicalUrl();

        if ($canonical) {
            printf('<link rel="canonical" href="%s" />' . "\n", esc_url($canonical));
        }
    }

    /**
     * Determine canonical URL for current page
     */
    public function getCanonicalUrl(): string
    {
        // Singular posts/pages
        if (is_singular()) {
            global $post;
            if (!$post) {
                return '';
            }

            // Check for per-post override
            $custom = get_post_meta($post->ID, '_sseo_ai_canonical_url', true);
            if ($custom) {
                return $custom;
            }

            // Default: permalink
            $url = get_permalink($post->ID);

            // Handle paginated posts (<!--nextpage-->)
            $page = get_query_var('page', 0);
            if ($page > 1) {
                $url = trailingslashit($url) . user_trailingslashit($page, 'single_paged');
            }

            return $url;
        }

        // Homepage
        if (is_front_page()) {
            $paged = get_query_var('paged', 0);
            if ($paged > 1) {
                return get_pagenum_link($paged);
            }
            return home_url('/');
        }

        // Blog page
        if (is_home()) {
            $pageId = get_option('page_for_posts');
            if ($pageId) {
                $custom = get_post_meta($pageId, '_sseo_ai_canonical_url', true);
                if ($custom) {
                    return $custom;
                }
                return get_permalink($pageId);
            }
            return home_url('/');
        }

        // Category / Tag / Taxonomy archives
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if (!$term) {
                return '';
            }

            $url = get_term_link($term);
            if (is_wp_error($url)) {
                return '';
            }

            // Paginated archives
            $paged = get_query_var('paged', 0);
            if ($paged > 1) {
                $url = trailingslashit($url) . user_trailingslashit('page/' . $paged, 'paged');
            }

            return $url;
        }

        // Author archives
        if (is_author()) {
            $author = get_queried_object();
            if (!$author) {
                return '';
            }
            $url = get_author_posts_url($author->ID);
            $paged = get_query_var('paged', 0);
            if ($paged > 1) {
                $url = trailingslashit($url) . user_trailingslashit('page/' . $paged, 'paged');
            }
            return $url;
        }

        // Date archives
        if (is_date()) {
            if (is_year()) {
                return get_year_link(get_query_var('year'));
            }
            if (is_month()) {
                return get_month_link(get_query_var('year'), get_query_var('monthnum'));
            }
            if (is_day()) {
                return get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
            }
        }

        // Search - no canonical (or self-referencing)
        if (is_search()) {
            return '';
        }

        // 404 - no canonical
        if (is_404()) {
            return '';
        }

        return '';
    }

    /**
     * Add meta box for custom canonical URL override
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true]);
        foreach ($postTypes as $postType) {
            add_meta_box(
                'aiseo_canonical',
                __('AI SEO: Canonical URL', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'low'
            );
        }
    }

    /**
     * Render canonical URL meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $canonical = get_post_meta($post->ID, '_sseo_ai_canonical_url', true);
        $default = get_permalink($post->ID);
        wp_nonce_field('aiseo_canonical_save', 'aiseo_canonical_nonce');
        ?>
        <div style="margin-bottom: 8px;">
            <label for="aiseo_canonical_url">
                <strong><?php esc_html_e('Custom Canonical URL', 'ai-seo-client'); ?></strong>
            </label>
        </div>
        <input type="url" id="aiseo_canonical_url" name="aiseo_canonical_url" 
               value="<?php echo esc_url($canonical); ?>" 
               placeholder="<?php echo esc_url($default); ?>"
               style="width: 100%;">
        <p class="description" style="margin-top: 6px;">
            <?php esc_html_e('Leave empty to use the default permalink. Set a custom URL to point search engines to a different page (e.g. for duplicate content).', 'ai-seo-client'); ?>
        </p>
        <?php if ($canonical): ?>
            <p style="margin-top: 4px;">
                <a href="#" id="aiseo-clear-canonical" style="color: #d63638; text-decoration: none;">
                    <?php esc_html_e('Clear custom canonical', 'ai-seo-client'); ?>
                </a>
            </p>
            <script>
            jQuery('#aiseo-clear-canonical').on('click', function(e) {
                e.preventDefault();
                jQuery('#aiseo_canonical_url').val('');
            });
            </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Save canonical URL meta
     */
    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_canonical_nonce']) || !wp_verify_nonce($_POST['aiseo_canonical_nonce'], 'aiseo_canonical_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $canonical = isset($_POST['aiseo_canonical_url']) ? esc_url_raw($_POST['aiseo_canonical_url']) : '';

        if ($canonical) {
            update_post_meta($postId, '_sseo_ai_canonical_url', $canonical);
        } else {
            delete_post_meta($postId, '_sseo_ai_canonical_url');
        }
    }
}

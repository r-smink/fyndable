<?php

namespace AISEOClient;

/**
 * Breadcrumbs Module
 * 
 * Outputs JSON-LD BreadcrumbList schema and provides a shortcode + helper
 * function for frontend breadcrumb navigation.
 * Comparable to Yoast Breadcrumbs, RankMath Breadcrumbs.
 */
class Breadcrumbs
{
    private Settings $settings;

    private const SEPARATOR_DEFAULT = '»';

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'outputJsonLd'], 3);
        add_shortcode('aiseo_breadcrumbs', [$this, 'shortcode']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('ai_seo_client_settings', 'aiseo_breadcrumbs_enabled', ['sanitize_callback' => 'absint']);
        register_setting('ai_seo_client_settings', 'aiseo_breadcrumbs_separator', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_seo_client_settings', 'aiseo_breadcrumbs_home_label', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_seo_client_settings', 'aiseo_breadcrumbs_show_home', ['sanitize_callback' => 'absint']);
    }

    /**
     * Build breadcrumb trail for current page
     *
     * @return array<int, array{name: string, url: string}>
     */
    public function buildTrail(): array
    {
        $trail = [];

        $showHome = (bool) get_option('aiseo_breadcrumbs_show_home', 1);
        $homeLabel = get_option('aiseo_breadcrumbs_home_label', '') ?: __('Home', 'ai-seo-client');

        if ($showHome) {
            $trail[] = ['name' => $homeLabel, 'url' => home_url('/')];
        }

        if (is_front_page()) {
            return $trail;
        }

        if (is_home()) {
            $pageId = get_option('page_for_posts');
            if ($pageId) {
                $trail[] = ['name' => get_the_title($pageId), 'url' => get_permalink($pageId)];
            } else {
                $trail[] = ['name' => __('Blog', 'ai-seo-client'), 'url' => ''];
            }
            return $trail;
        }

        if (is_singular()) {
            global $post;
            if (!$post) {
                return $trail;
            }

            // Hierarchical post types (pages) — add ancestors
            if (is_post_type_hierarchical($post->post_type) && $post->post_parent) {
                $ancestors = array_reverse(get_post_ancestors($post->ID));
                foreach ($ancestors as $ancestorId) {
                    $trail[] = ['name' => get_the_title($ancestorId), 'url' => get_permalink($ancestorId)];
                }
            }

            // For posts, add primary category
            if ($post->post_type === 'post') {
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    $primary = $this->getPrimaryCategory($post->ID, $categories);
                    // Add category ancestors
                    if ($primary->parent) {
                        $catAncestors = array_reverse(get_ancestors($primary->term_id, 'category'));
                        foreach ($catAncestors as $catId) {
                            $cat = get_category($catId);
                            if ($cat) {
                                $trail[] = ['name' => $cat->name, 'url' => get_category_link($catId)];
                            }
                        }
                    }
                    $trail[] = ['name' => $primary->name, 'url' => get_category_link($primary->term_id)];
                }
            }

            // Custom post types — add post type archive
            if (!in_array($post->post_type, ['post', 'page', 'attachment'])) {
                $ptObj = get_post_type_object($post->post_type);
                if ($ptObj && $ptObj->has_archive) {
                    $trail[] = ['name' => $ptObj->labels->name, 'url' => get_post_type_archive_link($post->post_type)];
                }
            }

            // Current page (no URL)
            $trail[] = ['name' => get_the_title($post->ID), 'url' => ''];

            return $trail;
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if (!$term) {
                return $trail;
            }

            // Add parent terms
            if ($term->parent) {
                $ancestors = array_reverse(get_ancestors($term->term_id, $term->taxonomy));
                foreach ($ancestors as $ancestorId) {
                    $ancestor = get_term($ancestorId, $term->taxonomy);
                    if ($ancestor && !is_wp_error($ancestor)) {
                        $trail[] = ['name' => $ancestor->name, 'url' => get_term_link($ancestor)];
                    }
                }
            }

            $trail[] = ['name' => $term->name, 'url' => ''];
            return $trail;
        }

        if (is_post_type_archive()) {
            $ptObj = get_queried_object();
            if ($ptObj) {
                $trail[] = ['name' => $ptObj->labels->name ?? '', 'url' => ''];
            }
            return $trail;
        }

        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $trail[] = ['name' => $author->display_name, 'url' => ''];
            }
            return $trail;
        }

        if (is_date()) {
            if (is_year()) {
                $trail[] = ['name' => get_the_date('Y'), 'url' => ''];
            } elseif (is_month()) {
                $trail[] = ['name' => get_the_date('Y'), 'url' => get_year_link(get_query_var('year'))];
                $trail[] = ['name' => get_the_date('F'), 'url' => ''];
            } elseif (is_day()) {
                $trail[] = ['name' => get_the_date('Y'), 'url' => get_year_link(get_query_var('year'))];
                $trail[] = ['name' => get_the_date('F'), 'url' => get_month_link(get_query_var('year'), get_query_var('monthnum'))];
                $trail[] = ['name' => get_the_date('j'), 'url' => ''];
            }
            return $trail;
        }

        if (is_search()) {
            $trail[] = ['name' => sprintf(__('Search: %s', 'ai-seo-client'), get_search_query()), 'url' => ''];
            return $trail;
        }

        if (is_404()) {
            $trail[] = ['name' => __('Page not found', 'ai-seo-client'), 'url' => ''];
            return $trail;
        }

        return $trail;
    }

    /**
     * Output JSON-LD BreadcrumbList schema
     */
    public function outputJsonLd(): void
    {
        if (is_admin() || is_front_page()) {
            return;
        }

        $trail = $this->buildTrail();
        if (count($trail) < 2) {
            return;
        }

        $items = [];
        foreach ($trail as $i => $crumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
            ];
            if (!empty($crumb['url'])) {
                $item['item'] = $crumb['url'];
            }
            $items[] = $item;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }

    /**
     * Shortcode: [aiseo_breadcrumbs]
     */
    public function shortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'separator' => get_option('aiseo_breadcrumbs_separator', self::SEPARATOR_DEFAULT),
            'class' => 'aiseo-breadcrumbs',
        ], $atts, 'aiseo_breadcrumbs');

        return $this->render($atts['separator'], $atts['class']);
    }

    /**
     * Render breadcrumb HTML
     */
    public function render(string $separator = '', string $cssClass = 'aiseo-breadcrumbs'): string
    {
        if (!$separator) {
            $separator = get_option('aiseo_breadcrumbs_separator', self::SEPARATOR_DEFAULT);
        }

        $trail = $this->buildTrail();
        if (empty($trail)) {
            return '';
        }

        $html = '<nav aria-label="' . esc_attr__('Breadcrumb', 'ai-seo-client') . '" class="' . esc_attr($cssClass) . '">';
        $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList" style="list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:4px;font-size:14px;">';

        $count = count($trail);
        foreach ($trail as $i => $crumb) {
            $isLast = ($i === $count - 1);
            $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" style="display:flex;align-items:center;">';

            if (!$isLast && !empty($crumb['url'])) {
                $html .= '<a itemprop="item" href="' . esc_url($crumb['url']) . '" style="text-decoration:none;color:#2271b1;">';
                $html .= '<span itemprop="name">' . esc_html($crumb['name']) . '</span>';
                $html .= '</a>';
            } else {
                $html .= '<span itemprop="name" style="color:#666;">' . esc_html($crumb['name']) . '</span>';
            }

            $html .= '<meta itemprop="position" content="' . ($i + 1) . '">';

            if (!$isLast) {
                $html .= '<span class="aiseo-breadcrumb-sep" style="margin:0 6px;color:#999;">' . esc_html($separator) . '</span>';
            }

            $html .= '</li>';
        }

        $html .= '</ol></nav>';
        return $html;
    }

    /**
     * Get primary category for a post (uses Yoast primary if set, otherwise first)
     */
    private function getPrimaryCategory(int $postId, array $categories): object
    {
        // Check if Yoast primary category is set
        $yoastPrimary = get_post_meta($postId, '_yoast_wpseo_primary_category', true);
        if ($yoastPrimary) {
            foreach ($categories as $cat) {
                if ((int) $cat->term_id === (int) $yoastPrimary) {
                    return $cat;
                }
            }
        }

        // Check if RankMath primary category is set
        $rmPrimary = get_post_meta($postId, 'rank_math_primary_category', true);
        if ($rmPrimary) {
            foreach ($categories as $cat) {
                if ((int) $cat->term_id === (int) $rmPrimary) {
                    return $cat;
                }
            }
        }

        // Fallback to first category
        return $categories[0];
    }
}

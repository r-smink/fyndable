<?php

namespace SSEOAIClient;

/**
 * Page Builder Helper
 *
 * Provides rendered content for SEO analysis across page builders
 * (Elementor, WPBakery, and generic the_content filters).
 */
class PageBuilderHelper
{
    /**
     * Builder/template post types that should never appear in SEO audits,
     * bulk scans, or reports. These are structural templates (headers, footers,
     * blocks, popups) — not standalone indexable pages.
     */
    private static array $builderTemplateTypes = [
        'elementor_library',      // Elementor templates (headers, footers, blocks, popups)
        'e-floating-buttons',     // Elementor floating buttons
        'e-landing-page',         // Elementor landing page templates
        'e-popup',                // Elementor popups (newer)
        'fl-theme-layout',        // Beaver Builder theme layouts
        'fl-builder-template',    // Beaver Builder templates
        'fusion_element',         // Avada Fusion Builder elements
        'ft_layout',              // Avada Fusion Builder layouts
        'tcb_symbol',             // Thrive Architect symbols
        'tcb_lightbox',           // Thrive Architect lightboxes
        'jet-menu',               // JetMenu items
        'jet-smart-filters',      // JetSmartFilters
        'wp_block',               // Gutenberg reusable blocks (template parts)
        'custom_css',             // Custom CSS
        'customize_changeset',    // Customizer changesets
        'oembed_cache',           // oEmbed cache
        'user_request',           // Privacy user requests
        'vc4t_template',          // VC templates
        'popup_maker',            // Popup Maker popups
        'amp_validated_url',      // AMP validated URLs
    ];

    /**
     * Get the post types that should be included in SEO audits, bulk scans,
     * and reports.
     *
     * Respects the existing `sseo_ai_enabled_post_types` option (set during
     * onboarding, default ['post', 'page']). Always excludes the
     * `attachment` type and the builder/template blacklist.
     *
     * @return array<int,string> List of post type slugs.
     */
    public static function getSeoPostTypes(): array
    {
        $enabled = get_option('sseo_ai_enabled_post_types', ['post', 'page']);

        if (empty($enabled) || !is_array($enabled)) {
            // Fallback: all public post types, minus excluded ones
            $types = get_post_types(['public' => true]);
        } else {
            $types = $enabled;
        }

        // Always exclude attachment
        $types = array_diff($types, ['attachment']);

        // Always exclude builder/template post types
        $types = array_diff($types, self::$builderTemplateTypes);

        // Re-index and return
        return array_values($types);
    }

    /**
     * Get fully rendered content for a post, including page builder output.
     *
     * @param \WP_Post $post
     * @return string Rendered HTML content
     */
    public static function getContent(\WP_Post $post): string
    {
        // Elementor: render from builder data when the post is built with Elementor.
        if (self::isElementor($post)) {
            $content = self::renderElementor($post);
            if (!empty($content)) {
                return $content;
            }
        }

        // WPBakery / generic shortcode-based builders.
        if (self::isWPBakery($post)) {
            $content = self::renderShortcodes($post);
            if (!empty($content)) {
                return $content;
            }
        }

        // Fallback to the_content filters (Beaver Builder, Divi, standard Gutenberg, etc.).
        $content = apply_filters('the_content', $post->post_content);
        if (!empty($content)) {
            return $content;
        }

        return $post->post_content;
    }

    /**
     * Check if the post is built with Elementor.
     */
    public static function isElementor(\WP_Post $post): bool
    {
        return get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder'
            || !empty(get_post_meta($post->ID, '_elementor_data', true));
    }

    /**
     * Render Elementor content for the post.
     */
    private static function renderElementor(\WP_Post $post): string
    {
        if (!class_exists('Elementor\Plugin')) {
            return '';
        }

        // Avoid affecting the current page template context.
        $elementor = \Elementor\Plugin::$instance;
        if (!method_exists($elementor->frontend, 'get_builder_content_for_display')) {
            return '';
        }

        return (string) $elementor->frontend->get_builder_content_for_display($post->ID, false);
    }

    /**
     * Check if the post uses WPBakery / shortcode builder.
     */
    public static function isWPBakery(\WP_Post $post): bool
    {
        if (empty($post->post_content)) {
            return false;
        }

        return strpos($post->post_content, '[vc_row') !== false
            || strpos($post->post_content, '[vc_column') !== false
            || strpos($post->post_content, '[vc_section') !== false;
    }

    /**
     * Render shortcode-based content (WPBakery, etc.).
     */
    private static function renderShortcodes(\WP_Post $post): string
    {
        if (empty($post->post_content)) {
            return '';
        }

        return do_shortcode($post->post_content);
    }

    /**
     * Fetch the public, fully rendered HTML for a post.
     *
     * This includes theme and page-builder templates (e.g. Elementor Theme
     * Builder single-post templates) that are not part of the post's own
     * builder content. Useful for detecting headings that live in templates.
     *
     * The result is cached for a few minutes because this method performs an
     * HTTP request.
     *
     * @param \WP_Post $post
     * @return string Rendered page HTML, or empty string on failure.
     */
    public static function getRenderedPageHtml(\WP_Post $post): string
    {
        if (!is_admin() && !wp_doing_ajax() && !wp_doing_rest()) {
            return '';
        }

        if (defined('SSEO_AI_DISABLE_FRONTEND_RENDER') && SSEO_AI_DISABLE_FRONTEND_RENDER) {
            return '';
        }

        if (!is_post_type_viewable($post->post_type) || post_password_required($post)) {
            return '';
        }

        $url = get_permalink($post->ID);
        if (!$url) {
            return '';
        }

        $cacheKey = 'sseo_ai_rendered_html_' . $post->ID . '_' . md5($post->post_modified . $url);
        $cached = get_transient($cacheKey);
        if (is_string($cached)) {
            return $cached;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => false,
            'headers' => [
                'Accept' => 'text/html',
                'User-Agent' => 'Fyndable SEO Analyzer/' . SSEO_AI_CLIENT_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 400 || empty($body)) {
            return '';
        }

        set_transient($cacheKey, $body, 5 * MINUTE_IN_SECONDS);
        return $body;
    }
}

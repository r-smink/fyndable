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
}

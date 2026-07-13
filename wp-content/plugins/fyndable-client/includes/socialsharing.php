<?php

namespace SSEOAIClient;

/**
 * Social Sharing Module
 *
 * Adds social sharing links for Facebook, Instagram, TikTok and other platforms,
 * plus Open Graph enhancements for organic sharing.
 */
class SocialSharing
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        // Meta box moved to PostMetaBox tabbed container
        add_action('wp_head', [$this, 'injectOpenGraphTags'], 1);
        add_filter('the_content', [$this, 'addShareButtons'], 999);
    }

    /**
     * Add social sharing meta box to posts and pages
     */
    public function addMetaBox(): void
    {
        $postTypes = ['post', 'page'];
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-social-sharing',
                __('Share on Social Media', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'default'
            );
        }
    }

    /**
     * Render sharing meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $url = get_permalink($post->ID);
        $title = rawurlencode(get_the_title($post->ID));
        $excerpt = rawurlencode(wp_strip_all_tags(get_the_excerpt($post)));

        $links = [
            'Facebook' => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($url),
            'X / Twitter' => "https://twitter.com/intent/tweet?url=" . urlencode($url) . "&text=" . $title,
            'LinkedIn' => "https://www.linkedin.com/sharing/share-offsite/?url=" . urlencode($url),
            'WhatsApp' => "https://api.whatsapp.com/send?text=" . $title . " " . urlencode($url),
            'Pinterest' => "https://pinterest.com/pin/create/button/?url=" . urlencode($url) . "&description=" . $title,
            'TikTok' => "https://www.tiktok.com/upload?referer=" . urlencode($url),
            'Reddit' => "https://reddit.com/submit?url=" . urlencode($url) . "&title=" . $title,
            'Telegram' => "https://t.me/share/url?url=" . urlencode($url) . "&text=" . $title,
        ];
        ?>
        <p class="description" style="margin-bottom: 10px;">
            <?php esc_html_e('Copy or open these links to share your content organically.', 'ai-seo-client'); ?>
        </p>
        <div style="display: flex; flex-direction: column; gap: 6px;">
            <?php foreach ($links as $name => $link): ?>
                <a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer"
                   style="display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: #f9f9f9; border-radius: 4px; text-decoration: none; color: #333; font-size: 13px; border: 1px solid #eee;">
                    <span style="font-weight: 600;"><?php echo esc_html($name); ?></span>
                    <span style="margin-left: auto; color: #666;">&#8599;</span>
                </a>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 10px; font-size: 12px; color: #666;">
            <?php esc_html_e('Note: Instagram does not support direct web sharing links. Use the mobile app to share.', 'ai-seo-client'); ?>
        </p>
        <?php
    }

    /**
     * Add share buttons to post content (optional, can be disabled)
     */
    public function addShareButtons(string $content): string
    {
        if (!is_singular(['post', 'page']) || is_admin()) {
            return $content;
        }

        $showButtons = get_option('sseo_ai_client_show_share_buttons', '1');
        if ($showButtons !== '1') {
            return $content;
        }

        $postId = get_the_ID();
        $url = get_permalink($postId);
        $title = rawurlencode(get_the_title($postId));

        $buttons = [
            'Facebook' => [
                'url' => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($url),
                'color' => '#1877f2',
            ],
            'X' => [
                'url' => "https://twitter.com/intent/tweet?url=" . urlencode($url) . "&text=" . $title,
                'color' => '#000000',
            ],
            'LinkedIn' => [
                'url' => "https://www.linkedin.com/sharing/share-offsite/?url=" . urlencode($url),
                'color' => '#0a66c2',
            ],
            'WhatsApp' => [
                'url' => "https://api.whatsapp.com/send?text=" . $title . " " . urlencode($url),
                'color' => '#25d366',
            ],
        ];

        $html = '<div class="sseo-share-bar" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">';
        $html .= '<p style="font-weight: 600; margin-bottom: 10px; font-size: 14px;">' . esc_html__('Share this article:', 'ai-seo-client') . '</p>';
        $html .= '<div style="display: flex; gap: 8px; flex-wrap: wrap;">';
        foreach ($buttons as $name => $btn) {
            $html .= '<a href="' . esc_url($btn['url']) . '" target="_blank" rel="noopener noreferrer" '
                   . 'style="display: inline-flex; align-items: center; padding: 8px 14px; background: ' . esc_attr($btn['color']) . '; color: #fff; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: 500;">'
                   . esc_html($name)
                   . '</a>';
        }
        $html .= '</div></div>';

        return $content . $html;
    }

    /**
     * Inject Open Graph tags for better social sharing
     */
    public function injectOpenGraphTags(): void
    {
        if (!is_singular()) {
            return;
        }

        $postId = get_queried_object_id();
        if (!$postId) return;

        $title = get_the_title($postId);
        $url = get_permalink($postId);
        $description = wp_strip_all_tags(get_the_excerpt($postId) ?: get_bloginfo('description'));
        $image = get_the_post_thumbnail_url($postId, 'large') ?: '';

        // Fallback to logo or default if no featured image
        if (!$image) {
            $customLogo = wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full');
            $image = $customLogo ?: '';
        }

        $siteName = get_bloginfo('name');
        $type = is_page() ? 'website' : 'article';

        echo "<!-- Fyndable Open Graph -->\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($siteName) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($type) . '" />' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            echo '<meta property="og:image:width" content="1200" />' . "\n";
            echo '<meta property="og:image:height" content="630" />' . "\n";
        }

        // Twitter Card
        echo '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
        echo "<!-- /Fyndable Open Graph -->\n";
    }
}

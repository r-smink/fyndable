<?php

namespace AISEOAssistant;

class SocialMedia
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'outputOpenGraph'], 1);
        add_action('wp_head', [$this, 'outputTwitterCards'], 1);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveSocialMeta'], 10, 2);
    }

    public function addMetaBoxes(): void
    {
        $screens = get_post_types(['public' => true]);
        foreach ($screens as $screen) {
            add_meta_box(
                'aiseo_social',
                __('Social Media Preview', 'ai-seo-assistant'),
                [$this, 'renderMetaBox'],
                $screen,
                'side',
                'default'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $ogTitle = get_post_meta($post->ID, '_aiseo_og_title', true);
        $ogDesc = get_post_meta($post->ID, '_aiseo_og_description', true);
        $ogImage = get_post_meta($post->ID, '_aiseo_og_image', true);
        $twitterTitle = get_post_meta($post->ID, '_aiseo_twitter_title', true);
        $twitterDesc = get_post_meta($post->ID, '_aiseo_twitter_description', true);
        $twitterImage = get_post_meta($post->ID, '_aiseo_twitter_image', true);
        
        wp_nonce_field('aiseo_social_save', 'aiseo_social_nonce');
        ?>
        <style>
            .aiseo-social-preview { border: 1px solid #ddd; padding: 15px; margin: 10px 0; background: #f9f9f9; }
            .aiseo-social-fb { border-left: 3px solid #1877f2; }
            .aiseo-social-x { border-left: 3px solid #000; }
            .aiseo-social-preview h4 { margin: 0 0 10px; font-size: 12px; text-transform: uppercase; color: #666; }
            .aiseo-fb-preview { background: white; border: 1px solid #e1e4e8; max-width: 500px; }
            .aiseo-fb-image { width: 100%; height: 262px; background: #f0f2f5; display: flex; align-items: center; justify-content: center; }
            .aiseo-fb-image img { max-width: 100%; max-height: 100%; object-fit: cover; }
            .aiseo-fb-content { padding: 12px; }
            .aiseo-fb-domain { color: #606770; font-size: 12px; text-transform: uppercase; }
            .aiseo-fb-title { color: #1d2129; font-size: 16px; font-weight: 600; line-height: 1.3; margin: 5px 0; }
            .aiseo-fb-desc { color: #606770; font-size: 14px; line-height: 1.3; }
        </style>

        <h4><?php esc_html_e('Open Graph (Facebook)', 'ai-seo-assistant'); ?></h4>
        <p>
            <label><?php esc_html_e('Title', 'ai-seo-assistant'); ?></label>
            <input type="text" name="aiseo_og_title" value="<?php echo esc_attr($ogTitle); ?>" class="wide-text" placeholder="<?php esc_attr_e('Leave empty to use SEO title', 'ai-seo-assistant'); ?>">
        </p>
        <p>
            <label><?php esc_html_e('Description', 'ai-seo-assistant'); ?></label>
            <textarea name="aiseo_og_description" rows="2" class="wide-text" placeholder="<?php esc_attr_e('Leave empty to use meta description', 'ai-seo-assistant'); ?>"><?php echo esc_textarea($ogDesc); ?></textarea>
        </p>
        <p>
            <label><?php esc_html_e('Image URL', 'ai-seo-assistant'); ?></label>
            <input type="text" name="aiseo_og_image" value="<?php echo esc_attr($ogImage); ?>" class="wide-text" placeholder="<?php esc_attr_e('1200x630px recommended', 'ai-seo-assistant'); ?>">
            <?php if ($ogImage) : ?>
            <br><img src="<?php echo esc_url($ogImage); ?>" style="max-width:100%; margin-top:5px;">
            <?php endif; ?>
        </p>

        <h4 style="margin-top:20px;"><?php esc_html_e('Twitter Cards', 'ai-seo-assistant'); ?></h4>
        <p>
            <label><?php esc_html_e('Title', 'ai-seo-assistant'); ?></label>
            <input type="text" name="aiseo_twitter_title" value="<?php echo esc_attr($twitterTitle); ?>" class="wide-text" placeholder="<?php esc_attr_e('Leave empty to use SEO title', 'ai-seo-assistant'); ?>">
        </p>
        <p>
            <label><?php esc_html_e('Description', 'ai-seo-assistant'); ?></label>
            <textarea name="aiseo_twitter_description" rows="2" class="wide-text" placeholder="<?php esc_attr_e('Leave empty to use meta description', 'ai-seo-assistant'); ?>"><?php echo esc_textarea($twitterDesc); ?></textarea>
        </p>
        <p>
            <label><?php esc_html_e('Image URL', 'ai-seo-assistant'); ?></label>
            <input type="text" name="aiseo_twitter_image" value="<?php echo esc_attr($twitterImage); ?>" class="wide-text" placeholder="<?php esc_attr_e('1200x600px recommended', 'ai-seo-assistant'); ?>">
        </p>

        <div class="aiseo-social-preview aiseo-social-fb">
            <h4><?php esc_html_e('Facebook Preview', 'ai-seo-assistant'); ?></h4>
            <div class="aiseo-fb-preview">
                <?php 
                $fbImage = $ogImage ?: $this->getDefaultImage($post);
                $fbTitle = $ogTitle ?: get_post_meta($post->ID, '_aiseo_title', true) ?: get_the_title($post);
                $fbDesc = $ogDesc ?: get_post_meta($post->ID, '_aiseo_description', true) ?: wp_trim_words(strip_tags($post->post_content), 20);
                ?>
                <div class="aiseo-fb-image">
                    <?php if ($fbImage) : ?>
                    <img src="<?php echo esc_url($fbImage); ?>">
                    <?php else : ?>
                    <span><?php esc_html_e('No image', 'ai-seo-assistant'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="aiseo-fb-content">
                    <div class="aiseo-fb-domain"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></div>
                    <div class="aiseo-fb-title"><?php echo esc_html($fbTitle); ?></div>
                    <div class="aiseo-fb-desc"><?php echo esc_html($fbDesc); ?></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function saveSocialMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_social_nonce']) || !wp_verify_nonce($_POST['aiseo_social_nonce'], 'aiseo_social_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $fields = [
            'aiseo_og_title',
            'aiseo_og_description',
            'aiseo_og_image',
            'aiseo_twitter_title',
            'aiseo_twitter_description',
            'aiseo_twitter_image',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $metaKey = '_' . $field;
                $value = $field === 'aiseo_og_description' || $field === 'aiseo_twitter_description' 
                    ? sanitize_textarea_field($_POST[$field]) 
                    : sanitize_text_field($_POST[$field]);
                update_post_meta($postId, $metaKey, $value);
            }
        }
    }

    public function outputOpenGraph(): void
    {
        if (!is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $ogData = $this->getOpenGraphData($post);

        foreach ($ogData as $key => $value) {
            if ($value) {
                printf("<meta property=\"%s\" content=\"%s\" />\n", esc_attr($key), esc_attr($value));
            }
        }

        // Additional tags
        echo '<meta property="og:locale" content="' . esc_attr(get_locale()) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";

        // Article specific
        if ($post->post_type === 'post') {
            $tags = get_the_tags($post->ID);
            if ($tags) {
                foreach ($tags as $tag) {
                    printf("<meta property=\"article:tag\" content=\"%s\" />\n", esc_attr($tag->name));
                }
            }
            $cats = get_the_category($post->ID);
            if ($cats) {
                printf("<meta property=\"article:section\" content=\"%s\" />\n", esc_attr($cats[0]->name));
            }
            printf("<meta property=\"article:published_time\" content=\"%s\" />\n", esc_attr(get_the_date('c', $post)));
            printf("<meta property=\"article:modified_time\" content=\"%s\" />\n", esc_attr(get_the_modified_date('c', $post)));
            printf("<meta property=\"article:author\" content=\"%s\" />\n", esc_attr(get_author_posts_url($post->post_author)));
        }
    }

    public function outputTwitterCards(): void
    {
        $cardType = $this->settings->get('twitter_card_type', 'summary_large_image');
        echo '<meta name="twitter:card" content="' . esc_attr($cardType) . '" />' . "\n";

        $site = $this->settings->get('twitter_site', '');
        if ($site) {
            echo '<meta name="twitter:site" content="' . esc_attr($site) . '" />' . "\n";
        }

        $creator = $this->settings->get('twitter_creator', '');
        if ($creator) {
            echo '<meta name="twitter:creator" content="' . esc_attr($creator) . '" />' . "\n";
        }

        if (!is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return;
        }

        $twitterData = $this->getTwitterData($post);

        foreach ($twitterData as $key => $value) {
            if ($value) {
                printf("<meta name=\"%s\" content=\"%s\" />\n", esc_attr($key), esc_attr($value));
            }
        }
    }

    private function getOpenGraphData(\WP_Post $post): array
    {
        $ogTitle = get_post_meta($post->ID, '_aiseo_og_title', true);
        $ogDesc = get_post_meta($post->ID, '_aiseo_og_description', true);
        $ogImage = get_post_meta($post->ID, '_aiseo_og_image', true);

        return [
            'og:type' => $post->post_type === 'post' ? 'article' : 'website',
            'og:title' => $ogTitle ?: get_post_meta($post->ID, '_aiseo_title', true) ?: get_the_title($post),
            'og:description' => $ogDesc ?: get_post_meta($post->ID, '_aiseo_description', true) ?: wp_trim_words(strip_tags($post->post_content), 25, ''),
            'og:url' => get_permalink($post),
            'og:image' => $ogImage ?: $this->getDefaultImage($post),
            'og:image:width' => $ogImage ? '1200' : '',
            'og:image:height' => $ogImage ? '630' : '',
        ];
    }

    private function getTwitterData(\WP_Post $post): array
    {
        $twitterTitle = get_post_meta($post->ID, '_aiseo_twitter_title', true);
        $twitterDesc = get_post_meta($post->ID, '_aiseo_twitter_description', true);
        $twitterImage = get_post_meta($post->ID, '_aiseo_twitter_image', true);

        return [
            'twitter:title' => $twitterTitle ?: get_post_meta($post->ID, '_aiseo_title', true) ?: get_the_title($post),
            'twitter:description' => $twitterDesc ?: get_post_meta($post->ID, '_aiseo_description', true) ?: wp_trim_words(strip_tags($post->post_content), 25, ''),
            'twitter:image' => $twitterImage ?: $this->getDefaultImage($post),
        ];
    }

    private function getDefaultImage(\WP_Post $post): string
    {
        // Featured image
        if (has_post_thumbnail($post->ID)) {
            $url = wp_get_attachment_image_url(get_post_thumbnail_id($post->ID), 'full');
            if ($url) {
                return $url;
            }
        }

        // First image in content
        if (preg_match('/<img[^>]+src=[\'"](.*?)[\'"]/i', $post->post_content, $match)) {
            return $match[1];
        }

        // Default site image
        $default = $this->settings->get('default_social_image', '');
        if ($default) {
            return $default;
        }

        // Site logo
        $logoId = get_theme_mod('custom_logo');
        if ($logoId) {
            $url = wp_get_attachment_image_url($logoId, 'full');
            if ($url) {
                return $url;
            }
        }

        return '';
    }

    public function generateSocialImages(int $postId): array
    {
        $post = get_post($postId);
        if (!$post) {
            return [];
        }

        $title = get_the_title($post);
        $image = $this->getDefaultImage($post);

        return [
            'facebook' => [
                'title' => $title,
                'image' => $image,
                'url' => get_permalink($post),
            ],
            'twitter' => [
                'title' => $title,
                'image' => $image,
                'url' => get_permalink($post),
            ],
        ];
    }
}

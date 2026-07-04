<?php

namespace SSEOAIClient;

/**
 * Open Graph & Social Meta Tags
 * 
 * Outputs og:title, og:description, og:image, twitter:card, etc.
 * Provides per-post overrides via meta box and global defaults via settings.
 * Comparable to Yoast Social, RankMath Social, AIOSEO Social.
 */
class OpenGraph
{
    private Settings $settings;
    private ?LlmClient $llm;

    public function __construct(Settings $settings, ?LlmClient $llm = null)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'outputMetaTags'], 2);
        // Meta box moved to PostMetaBox tabbed container
        add_action('save_post', [$this, 'saveMeta'], 10, 2);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerSettings(): void
    {
        $fields = [
            'og_default_image',
            'og_site_name',
            'twitter_site_handle',
            'twitter_card_type',
            'facebook_app_id',
            'og_fallback_description',
        ];
        foreach ($fields as $field) {
            register_setting('sseo_ai_client_settings', 'aiseo_' . $field, ['sanitize_callback' => 'sanitize_text_field']);
        }
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/og/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateOG'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);
    }

    /**
     * Output Open Graph + Twitter Card meta tags in <head>
     */
    public function outputMetaTags(): void
    {
        if (is_admin()) {
            return;
        }

        $data = $this->getMetaData();
        if (empty($data)) {
            return;
        }

        echo "\n<!-- AI SEO: Open Graph & Social Meta -->\n";

        // Open Graph
        $this->tag('og:type', $data['og_type']);
        $this->tag('og:title', $data['og_title']);
        $this->tag('og:description', $data['og_description']);
        $this->tag('og:url', $data['og_url']);
        $this->tag('og:image', $data['og_image']);
        $this->tag('og:image:width', $data['og_image_width']);
        $this->tag('og:image:height', $data['og_image_height']);
        $this->tag('og:image:alt', $data['og_image_alt']);
        $this->tag('og:site_name', $data['og_site_name']);
        $this->tag('og:locale', $data['og_locale']);

        // Facebook App ID
        if (!empty($data['fb_app_id'])) {
            $this->tag('fb:app_id', $data['fb_app_id']);
        }

        // Article-specific tags
        if ($data['og_type'] === 'article') {
            $this->tag('article:published_time', $data['article_published']);
            $this->tag('article:modified_time', $data['article_modified']);
            $this->tag('article:author', $data['article_author']);
            if (!empty($data['article_section'])) {
                $this->tag('article:section', $data['article_section']);
            }
            if (!empty($data['article_tags'])) {
                foreach ($data['article_tags'] as $atag) {
                    $this->tag('article:tag', $atag);
                }
            }
        }

        // Twitter Card
        echo "\n";
        $this->meta('twitter:card', $data['twitter_card']);
        $this->meta('twitter:title', $data['twitter_title']);
        $this->meta('twitter:description', $data['twitter_description']);
        $this->meta('twitter:image', $data['twitter_image']);
        if (!empty($data['twitter_site'])) {
            $this->meta('twitter:site', $data['twitter_site']);
        }
        if (!empty($data['twitter_creator'])) {
            $this->meta('twitter:creator', $data['twitter_creator']);
        }

        echo "<!-- / AI SEO: Open Graph & Social Meta -->\n\n";
    }

    /**
     * Get all meta data for current page
     */
    private function getMetaData(): array
    {
        $siteName = get_option('aiseo_og_site_name', get_bloginfo('name'));
        $defaultImage = get_option('aiseo_og_default_image', '');
        $twitterSite = get_option('aiseo_twitter_site_handle', '');
        $twitterCard = get_option('aiseo_twitter_card_type', 'summary_large_image');
        $fbAppId = get_option('aiseo_facebook_app_id', '');
        $locale = get_locale();

        if (is_singular()) {
            return $this->getSingularMeta($siteName, $defaultImage, $twitterSite, $twitterCard, $fbAppId, $locale);
        }

        if (is_front_page() || is_home()) {
            return $this->getHomeMeta($siteName, $defaultImage, $twitterSite, $twitterCard, $fbAppId, $locale);
        }

        if (is_category() || is_tag() || is_tax()) {
            return $this->getArchiveMeta($siteName, $defaultImage, $twitterSite, $twitterCard, $fbAppId, $locale);
        }

        if (is_author()) {
            return $this->getAuthorMeta($siteName, $defaultImage, $twitterSite, $twitterCard, $fbAppId, $locale);
        }

        return [];
    }

    /**
     * Get meta for singular posts/pages
     */
    private function getSingularMeta(string $siteName, string $defaultImage, string $twitterSite, string $twitterCard, string $fbAppId, string $locale): array
    {
        global $post;
        if (!$post) {
            return [];
        }

        $postId = $post->ID;

        // Per-post overrides (from meta box)
        $ogTitle = get_post_meta($postId, '_sseo_ai_og_title', true);
        $ogDesc = get_post_meta($postId, '_sseo_ai_og_description', true);
        $ogImage = get_post_meta($postId, '_sseo_ai_og_image', true);
        $twitterTitle = get_post_meta($postId, '_sseo_ai_twitter_title', true);
        $twitterDesc = get_post_meta($postId, '_sseo_ai_twitter_description', true);
        $twitterImage = get_post_meta($postId, '_sseo_ai_twitter_image', true);

        // Fallback to SEO meta
        $seoTitle = get_post_meta($postId, '_sseo_ai_title', true);
        $seoDesc = get_post_meta($postId, '_sseo_ai_description', true);

        // Final fallback to post data
        $title = $ogTitle ?: $seoTitle ?: get_the_title($postId);
        $description = $ogDesc ?: $seoDesc ?: $this->generateExcerpt($postId);

        // Image fallback chain: OG image → Featured image → Default image
        $image = $ogImage;
        $imageWidth = '';
        $imageHeight = '';
        $imageAlt = '';

        if (!$image) {
            $thumbId = get_post_thumbnail_id($postId);
            if ($thumbId) {
                $imgData = wp_get_attachment_image_src($thumbId, 'large');
                if ($imgData) {
                    $image = $imgData[0];
                    $imageWidth = $imgData[1];
                    $imageHeight = $imgData[2];
                }
                $imageAlt = get_post_meta($thumbId, '_wp_attachment_image_alt', true);
            }
        }
        if (!$image) {
            $image = $defaultImage;
        }

        // Categories and tags for article meta
        $categories = get_the_category($postId);
        $section = !empty($categories) ? $categories[0]->name : '';
        $tags = get_the_tags($postId);
        $tagNames = $tags ? wp_list_pluck($tags, 'name') : [];

        // Author
        $authorId = $post->post_author;
        $authorUrl = get_author_posts_url($authorId);

        return [
            'og_type' => is_page() ? 'website' : 'article',
            'og_title' => $this->truncate($title, 95),
            'og_description' => $this->truncate($description, 200),
            'og_url' => get_permalink($postId),
            'og_image' => $image,
            'og_image_width' => $imageWidth,
            'og_image_height' => $imageHeight,
            'og_image_alt' => $imageAlt ?: $title,
            'og_site_name' => $siteName,
            'og_locale' => $locale,
            'fb_app_id' => $fbAppId,
            'article_published' => get_the_date('c', $postId),
            'article_modified' => get_the_modified_date('c', $postId),
            'article_author' => $authorUrl,
            'article_section' => $section,
            'article_tags' => $tagNames,
            'twitter_card' => $twitterCard,
            'twitter_title' => $this->truncate($twitterTitle ?: $title, 70),
            'twitter_description' => $this->truncate($twitterDesc ?: $description, 200),
            'twitter_image' => $twitterImage ?: $image,
            'twitter_site' => $twitterSite,
            'twitter_creator' => get_the_author_meta('twitter', $authorId),
        ];
    }

    /**
     * Get meta for homepage
     */
    private function getHomeMeta(string $siteName, string $defaultImage, string $twitterSite, string $twitterCard, string $fbAppId, string $locale): array
    {
        $description = get_option('aiseo_og_fallback_description', get_bloginfo('description'));

        return [
            'og_type' => 'website',
            'og_title' => $siteName,
            'og_description' => $this->truncate($description, 200),
            'og_url' => home_url('/'),
            'og_image' => $defaultImage,
            'og_image_width' => '',
            'og_image_height' => '',
            'og_image_alt' => $siteName,
            'og_site_name' => $siteName,
            'og_locale' => $locale,
            'fb_app_id' => $fbAppId,
            'article_published' => '',
            'article_modified' => '',
            'article_author' => '',
            'article_section' => '',
            'article_tags' => [],
            'twitter_card' => $twitterCard,
            'twitter_title' => $siteName,
            'twitter_description' => $this->truncate($description, 200),
            'twitter_image' => $defaultImage,
            'twitter_site' => $twitterSite,
            'twitter_creator' => '',
        ];
    }

    /**
     * Get meta for category/tag/taxonomy archives
     */
    private function getArchiveMeta(string $siteName, string $defaultImage, string $twitterSite, string $twitterCard, string $fbAppId, string $locale): array
    {
        $term = get_queried_object();
        $title = $term->name ?? '';
        $description = term_description($term->term_id ?? 0) ?: $title;
        $description = wp_strip_all_tags($description);

        return [
            'og_type' => 'website',
            'og_title' => $title . ' - ' . $siteName,
            'og_description' => $this->truncate($description, 200),
            'og_url' => get_term_link($term),
            'og_image' => $defaultImage,
            'og_image_width' => '',
            'og_image_height' => '',
            'og_image_alt' => $title,
            'og_site_name' => $siteName,
            'og_locale' => $locale,
            'fb_app_id' => $fbAppId,
            'article_published' => '',
            'article_modified' => '',
            'article_author' => '',
            'article_section' => '',
            'article_tags' => [],
            'twitter_card' => $twitterCard,
            'twitter_title' => $title,
            'twitter_description' => $this->truncate($description, 200),
            'twitter_image' => $defaultImage,
            'twitter_site' => $twitterSite,
            'twitter_creator' => '',
        ];
    }

    /**
     * Get meta for author archives
     */
    private function getAuthorMeta(string $siteName, string $defaultImage, string $twitterSite, string $twitterCard, string $fbAppId, string $locale): array
    {
        $author = get_queried_object();
        $title = $author->display_name ?? '';
        $description = get_the_author_meta('description', $author->ID ?? 0) ?: sprintf(__('Posts by %s', 'ai-seo-client'), $title);
        $avatar = get_avatar_url($author->ID ?? 0, ['size' => 600]);

        return [
            'og_type' => 'profile',
            'og_title' => $title . ' - ' . $siteName,
            'og_description' => $this->truncate($description, 200),
            'og_url' => get_author_posts_url($author->ID ?? 0),
            'og_image' => $avatar ?: $defaultImage,
            'og_image_width' => '',
            'og_image_height' => '',
            'og_image_alt' => $title,
            'og_site_name' => $siteName,
            'og_locale' => $locale,
            'fb_app_id' => $fbAppId,
            'article_published' => '',
            'article_modified' => '',
            'article_author' => '',
            'article_section' => '',
            'article_tags' => [],
            'twitter_card' => $twitterCard,
            'twitter_title' => $title,
            'twitter_description' => $this->truncate($description, 200),
            'twitter_image' => $avatar ?: $defaultImage,
            'twitter_site' => $twitterSite,
            'twitter_creator' => get_the_author_meta('twitter', $author->ID ?? 0),
        ];
    }

    /**
     * Add meta boxes for per-post OG/Twitter overrides
     */
    public function addMetaBoxes(): void
    {
        // Only load on post edit screens
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }
        
        $postTypes = get_post_types(['public' => true]);
        foreach ($postTypes as $postType) {
            add_meta_box(
                'aiseo_opengraph',
                __('AI SEO: Social Media Preview', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render the Social Meta meta box in post editor
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $ogTitle = get_post_meta($post->ID, '_sseo_ai_og_title', true);
        $ogDesc = get_post_meta($post->ID, '_sseo_ai_og_description', true);
        $ogImage = get_post_meta($post->ID, '_sseo_ai_og_image', true);
        $twitterTitle = get_post_meta($post->ID, '_sseo_ai_twitter_title', true);
        $twitterDesc = get_post_meta($post->ID, '_sseo_ai_twitter_description', true);
        $twitterImage = get_post_meta($post->ID, '_sseo_ai_twitter_image', true);

        wp_nonce_field('aiseo_og_save', 'aiseo_og_nonce');
        ?>
        <style>
            .aiseo-og-tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 15px; }
            .aiseo-og-tab { padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 500; color: #666; }
            .aiseo-og-tab.active { color: #2271b1; border-bottom-color: #2271b1; }
            .aiseo-og-panel { display: none; }
            .aiseo-og-panel.active { display: block; }
            .aiseo-og-preview { background: #f0f2f5; border-radius: 8px; overflow: hidden; max-width: 500px; margin: 15px 0; font-family: -apple-system, system-ui, sans-serif; }
            .aiseo-og-preview-image { width: 100%; height: 260px; background: #e4e6eb; display: flex; align-items: center; justify-content: center; overflow: hidden; }
            .aiseo-og-preview-image img { width: 100%; height: 100%; object-fit: cover; }
            .aiseo-og-preview-body { padding: 12px 16px; }
            .aiseo-og-preview-domain { font-size: 12px; color: #65676b; text-transform: uppercase; }
            .aiseo-og-preview-title { font-size: 16px; font-weight: 600; color: #1c1e21; margin: 3px 0; line-height: 1.3; }
            .aiseo-og-preview-desc { font-size: 14px; color: #65676b; line-height: 1.3; }
            .aiseo-twitter-preview { background: #fff; border: 1px solid #e1e8ed; border-radius: 14px; overflow: hidden; max-width: 500px; margin: 15px 0; }
            .aiseo-twitter-preview-image { width: 100%; height: 250px; background: #e1e8ed; overflow: hidden; }
            .aiseo-twitter-preview-image img { width: 100%; height: 100%; object-fit: cover; }
            .aiseo-twitter-preview-body { padding: 12px 16px; }
            .aiseo-twitter-preview-title { font-size: 15px; font-weight: 700; color: #0f1419; }
            .aiseo-twitter-preview-desc { font-size: 15px; color: #536471; margin-top: 2px; }
            .aiseo-twitter-preview-domain { font-size: 15px; color: #536471; margin-top: 2px; }
            .aiseo-og-field { margin-bottom: 12px; }
            .aiseo-og-field label { display: block; font-weight: 600; margin-bottom: 4px; }
            .aiseo-og-field input, .aiseo-og-field textarea { width: 100%; }
            .aiseo-og-field .char-count { font-size: 12px; color: #999; text-align: right; }
            .aiseo-og-generate-btn { margin-top: 5px; }
        </style>

        <div class="aiseo-og-tabs">
            <div class="aiseo-og-tab active" data-tab="facebook">Facebook / Open Graph</div>
            <div class="aiseo-og-tab" data-tab="twitter">Twitter / X</div>
        </div>

        <!-- Facebook / Open Graph Panel -->
        <div class="aiseo-og-panel active" data-panel="facebook">
            <div class="aiseo-og-preview" id="aiseo-fb-preview">
                <div class="aiseo-og-preview-image">
                    <?php if ($ogImage || has_post_thumbnail($post->ID)): ?>
                        <img src="<?php echo esc_url($ogImage ?: get_the_post_thumbnail_url($post->ID, 'large')); ?>" alt="">
                    <?php else: ?>
                        <span style="color:#aaa;"><?php esc_html_e('No image set', 'ai-seo-client'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="aiseo-og-preview-body">
                    <div class="aiseo-og-preview-domain"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></div>
                    <div class="aiseo-og-preview-title" id="aiseo-fb-preview-title"><?php echo esc_html($ogTitle ?: get_the_title($post->ID)); ?></div>
                    <div class="aiseo-og-preview-desc" id="aiseo-fb-preview-desc"><?php echo esc_html($ogDesc ?: $this->generateExcerpt($post->ID)); ?></div>
                </div>
            </div>

            <div class="aiseo-og-field">
                <label for="aiseo_og_title"><?php esc_html_e('OG Title', 'ai-seo-client'); ?></label>
                <input type="text" id="aiseo_og_title" name="aiseo_og_title" 
                       value="<?php echo esc_attr($ogTitle); ?>" 
                       placeholder="<?php echo esc_attr(get_the_title($post->ID)); ?>" maxlength="95">
                <div class="char-count"><span id="aiseo-og-title-count"><?php echo strlen($ogTitle); ?></span>/95</div>
            </div>

            <div class="aiseo-og-field">
                <label for="aiseo_og_description"><?php esc_html_e('OG Description', 'ai-seo-client'); ?></label>
                <textarea id="aiseo_og_description" name="aiseo_og_description" rows="3" 
                          placeholder="<?php echo esc_attr($this->generateExcerpt($post->ID)); ?>" maxlength="200"><?php echo esc_textarea($ogDesc); ?></textarea>
                <div class="char-count"><span id="aiseo-og-desc-count"><?php echo strlen($ogDesc); ?></span>/200</div>
            </div>

            <div class="aiseo-og-field">
                <label for="aiseo_og_image"><?php esc_html_e('OG Image URL', 'ai-seo-client'); ?></label>
                <input type="url" id="aiseo_og_image" name="aiseo_og_image" 
                       value="<?php echo esc_url($ogImage); ?>" 
                       placeholder="<?php esc_attr_e('Leave empty to use featured image', 'ai-seo-client'); ?>">
                <button type="button" class="button aiseo-og-upload-btn" data-target="aiseo_og_image">
                    <?php esc_html_e('Upload Image', 'ai-seo-client'); ?>
                </button>
                <p class="description"><?php esc_html_e('Recommended: 1200×630px. Leave empty to use featured image.', 'ai-seo-client'); ?></p>
            </div>

            <button type="button" class="button aiseo-og-generate-btn" id="aiseo-generate-og" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('AI Generate OG Tags', 'ai-seo-client'); ?>
            </button>
        </div>

        <!-- Twitter Panel -->
        <div class="aiseo-og-panel" data-panel="twitter">
            <div class="aiseo-twitter-preview" id="aiseo-tw-preview">
                <div class="aiseo-twitter-preview-image">
                    <?php $twImg = $twitterImage ?: $ogImage ?: get_the_post_thumbnail_url($post->ID, 'large'); ?>
                    <?php if ($twImg): ?>
                        <img src="<?php echo esc_url($twImg); ?>" alt="">
                    <?php else: ?>
                        <span style="color:#aaa; padding: 20px;"><?php esc_html_e('No image set', 'ai-seo-client'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="aiseo-twitter-preview-body">
                    <div class="aiseo-twitter-preview-title" id="aiseo-tw-preview-title"><?php echo esc_html($twitterTitle ?: $ogTitle ?: get_the_title($post->ID)); ?></div>
                    <div class="aiseo-twitter-preview-desc" id="aiseo-tw-preview-desc"><?php echo esc_html($twitterDesc ?: $ogDesc ?: $this->generateExcerpt($post->ID)); ?></div>
                    <div class="aiseo-twitter-preview-domain"><?php echo esc_html(parse_url(home_url(), PHP_URL_HOST)); ?></div>
                </div>
            </div>

            <div class="aiseo-og-field">
                <label for="aiseo_twitter_title"><?php esc_html_e('Twitter Title', 'ai-seo-client'); ?></label>
                <input type="text" id="aiseo_twitter_title" name="aiseo_twitter_title" 
                       value="<?php echo esc_attr($twitterTitle); ?>" 
                       placeholder="<?php esc_attr_e('Falls back to OG Title', 'ai-seo-client'); ?>" maxlength="70">
                <div class="char-count"><span id="aiseo-tw-title-count"><?php echo strlen($twitterTitle); ?></span>/70</div>
            </div>

            <div class="aiseo-og-field">
                <label for="aiseo_twitter_description"><?php esc_html_e('Twitter Description', 'ai-seo-client'); ?></label>
                <textarea id="aiseo_twitter_description" name="aiseo_twitter_description" rows="3" 
                          placeholder="<?php esc_attr_e('Falls back to OG Description', 'ai-seo-client'); ?>" maxlength="200"><?php echo esc_textarea($twitterDesc); ?></textarea>
                <div class="char-count"><span id="aiseo-tw-desc-count"><?php echo strlen($twitterDesc); ?></span>/200</div>
            </div>

            <div class="aiseo-og-field">
                <label for="aiseo_twitter_image"><?php esc_html_e('Twitter Image URL', 'ai-seo-client'); ?></label>
                <input type="url" id="aiseo_twitter_image" name="aiseo_twitter_image" 
                       value="<?php echo esc_url($twitterImage); ?>" 
                       placeholder="<?php esc_attr_e('Falls back to OG Image', 'ai-seo-client'); ?>">
                <button type="button" class="button aiseo-og-upload-btn" data-target="aiseo_twitter_image">
                    <?php esc_html_e('Upload Image', 'ai-seo-client'); ?>
                </button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.aiseo-og-tab').on('click', function() {
                var tab = $(this).data('tab');
                $('.aiseo-og-tab').removeClass('active');
                $(this).addClass('active');
                $('.aiseo-og-panel').removeClass('active');
                $('.aiseo-og-panel[data-panel="' + tab + '"]').addClass('active');
            });

            // Live preview updates
            $('#aiseo_og_title').on('input', function() {
                var val = $(this).val() || $(this).attr('placeholder');
                $('#aiseo-fb-preview-title').text(val);
                $('#aiseo-og-title-count').text($(this).val().length);
            });
            $('#aiseo_og_description').on('input', function() {
                var val = $(this).val() || $(this).attr('placeholder');
                $('#aiseo-fb-preview-desc').text(val);
                $('#aiseo-og-desc-count').text($(this).val().length);
            });
            $('#aiseo_twitter_title').on('input', function() {
                var val = $(this).val() || $('#aiseo_og_title').val() || $('#aiseo_og_title').attr('placeholder');
                $('#aiseo-tw-preview-title').text(val);
                $('#aiseo-tw-title-count').text($(this).val().length);
            });
            $('#aiseo_twitter_description').on('input', function() {
                var val = $(this).val() || $('#aiseo_og_description').val() || $('#aiseo_og_description').attr('placeholder');
                $('#aiseo-tw-preview-desc').text(val);
                $('#aiseo-tw-desc-count').text($(this).val().length);
            });

            // Media uploader for images
            $('.aiseo-og-upload-btn').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                var frame = wp.media({ title: '<?php echo esc_js(__('Select Image', 'ai-seo-client')); ?>', multiple: false, library: { type: 'image' } });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#' + target).val(attachment.url);
                });
                frame.open();
            });

            // AI Generate OG tags
            $('#aiseo-generate-og').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/og/generate',
                    method: 'POST',
                    data: { post_id: postId }
                }).then(function(result) {
                    if (result.og_title) {
                        $('#aiseo_og_title').val(result.og_title).trigger('input');
                    }
                    if (result.og_description) {
                        $('#aiseo_og_description').val(result.og_description).trigger('input');
                    }
                    if (result.twitter_title) {
                        $('#aiseo_twitter_title').val(result.twitter_title).trigger('input');
                    }
                    if (result.twitter_description) {
                        $('#aiseo_twitter_description').val(result.twitter_description).trigger('input');
                    }
                    btn.prop('disabled', false).text('<?php echo esc_js(__('AI Generate OG Tags', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert('Error: ' + (err.message || 'Failed to generate'));
                    btn.prop('disabled', false).text('<?php echo esc_js(__('AI Generate OG Tags', 'ai-seo-client')); ?>');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Save OG meta on post save
     */
    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_og_nonce']) || !wp_verify_nonce($_POST['aiseo_og_nonce'], 'aiseo_og_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $fields = [
            '_sseo_ai_og_title' => 'aiseo_og_title',
            '_sseo_ai_og_description' => 'aiseo_og_description',
            '_sseo_ai_og_image' => 'aiseo_og_image',
            '_sseo_ai_twitter_title' => 'aiseo_twitter_title',
            '_sseo_ai_twitter_description' => 'aiseo_twitter_description',
            '_sseo_ai_twitter_image' => 'aiseo_twitter_image',
        ];

        foreach ($fields as $metaKey => $postKey) {
            if (isset($_POST[$postKey])) {
                $value = $postKey === 'aiseo_og_image' || $postKey === 'aiseo_twitter_image'
                    ? esc_url_raw($_POST[$postKey])
                    : sanitize_text_field($_POST[$postKey]);
                update_post_meta($postId, $metaKey, $value);
            }
        }
    }

    /**
     * REST: AI-generate OG tags for a post
     */
    public function restGenerateOG(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) $request->get_param('post_id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'));
        }

        if (!$this->llm) {
            return new \WP_Error('no_ai', __('AI features not available', 'ai-seo-client'));
        }

        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;
        $keyword = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);

        $prompt = "Generate optimized social media meta tags for this article.

Title: \"{$title}\"
" . ($keyword ? "Focus Keyword: \"{$keyword}\"\n" : "") . "
Content excerpt: " . substr($content, 0, 500) . "

Generate exactly this JSON format (no markdown, no code blocks):
{
    \"og_title\": \"Compelling Open Graph title (max 95 chars, include keyword)\",
    \"og_description\": \"Engaging description for Facebook sharing (max 200 chars)\",
    \"twitter_title\": \"Shorter punchy title for Twitter/X (max 70 chars)\",
    \"twitter_description\": \"Brief compelling description for Twitter (max 200 chars)\"
}

Return ONLY the JSON, nothing else.";

        $result = $this->llm->call($prompt, null, null, 500);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        // Clean potential markdown code blocks
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!$data) {
            return new \WP_Error('parse_error', __('Failed to parse AI response', 'ai-seo-client'));
        }

        return [
            'og_title' => sanitize_text_field($data['og_title'] ?? ''),
            'og_description' => sanitize_text_field($data['og_description'] ?? ''),
            'twitter_title' => sanitize_text_field($data['twitter_title'] ?? ''),
            'twitter_description' => sanitize_text_field($data['twitter_description'] ?? ''),
        ];
    }

    /**
     * Output a property meta tag (Open Graph)
     */
    private function tag(string $property, string $content): void
    {
        if (!empty($content)) {
            printf('<meta property="%s" content="%s" />' . "\n", esc_attr($property), esc_attr($content));
        }
    }

    /**
     * Output a name meta tag (Twitter)
     */
    private function meta(string $name, string $content): void
    {
        if (!empty($content)) {
            printf('<meta name="%s" content="%s" />' . "\n", esc_attr($name), esc_attr($content));
        }
    }

    /**
     * Generate excerpt from post content
     */
    private function generateExcerpt(int $postId): string
    {
        $post = get_post($postId);
        if (!$post) {
            return '';
        }

        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        $content = wp_strip_all_tags($post->post_content);
        return $this->truncate($content, 200);
    }

    /**
     * Truncate string to max length at word boundary
     */
    private function truncate(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $text = mb_substr($text, 0, $max);
        $lastSpace = mb_strrpos($text, ' ');
        if ($lastSpace !== false && $lastSpace > $max * 0.5) {
            $text = mb_substr($text, 0, $lastSpace);
        }
        return $text . '…';
    }
}

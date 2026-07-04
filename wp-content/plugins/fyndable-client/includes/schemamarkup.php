<?php

namespace SSEOAIClient;

class SchemaMarkup
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'outputSchema'], 1);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveSchemaMeta'], 10, 2);
    }

    public function addMetaBoxes(): void
    {
        $postTypes = get_post_types(['public' => true]);
        foreach ($postTypes as $postType) {
            add_meta_box(
                'aiseo_schema',
                __('AI SEO Schema Markup', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'high'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $schemaType = get_post_meta($post->ID, '_sseo_ai_schema_type', true) ?: 'Article';
        $customSchema = get_post_meta($post->ID, '_sseo_ai_custom_schema', true);
        wp_nonce_field('aiseo_schema_save', 'aiseo_schema_nonce');
        ?>
        <p>
            <label><strong><?php esc_html_e('Schema Type', 'ai-seo-client'); ?></strong></label><br>
            <select name="aiseo_schema_type" style="width:100%;">
                <option value="Article" <?php selected($schemaType, 'Article'); ?>><?php esc_html_e('Article', 'ai-seo-client'); ?></option>
                <option value="BlogPosting" <?php selected($schemaType, 'BlogPosting'); ?>><?php esc_html_e('Blog Posting', 'ai-seo-client'); ?></option>
                <option value="NewsArticle" <?php selected($schemaType, 'NewsArticle'); ?>><?php esc_html_e('News Article', 'ai-seo-client'); ?></option>
                <option value="Product" <?php selected($schemaType, 'Product'); ?>><?php esc_html_e('Product', 'ai-seo-client'); ?></option>
                <option value="LocalBusiness" <?php selected($schemaType, 'LocalBusiness'); ?>><?php esc_html_e('Local Business', 'ai-seo-client'); ?></option>
                <option value="Organization" <?php selected($schemaType, 'Organization'); ?>><?php esc_html_e('Organization', 'ai-seo-client'); ?></option>
                <option value="Person" <?php selected($schemaType, 'Person'); ?>><?php esc_html_e('Person', 'ai-seo-client'); ?></option>
                <option value="FAQPage" <?php selected($schemaType, 'FAQPage'); ?>><?php esc_html_e('FAQ Page', 'ai-seo-client'); ?></option>
                <option value="HowTo" <?php selected($schemaType, 'HowTo'); ?>><?php esc_html_e('How To', 'ai-seo-client'); ?></option>
                <option value="Recipe" <?php selected($schemaType, 'Recipe'); ?>><?php esc_html_e('Recipe', 'ai-seo-client'); ?></option>
                <option value="Event" <?php selected($schemaType, 'Event'); ?>><?php esc_html_e('Event', 'ai-seo-client'); ?></option>
                <option value="Course" <?php selected($schemaType, 'Course'); ?>><?php esc_html_e('Course', 'ai-seo-client'); ?></option>
                <option value="SoftwareApplication" <?php selected($schemaType, 'SoftwareApplication'); ?>><?php esc_html_e('Software Application', 'ai-seo-client'); ?></option>
                <option value="Review" <?php selected($schemaType, 'Review'); ?>><?php esc_html_e('Review', 'ai-seo-client'); ?></option>
                <option value="AggregateRating" <?php selected($schemaType, 'AggregateRating'); ?>><?php esc_html_e('Aggregate Rating', 'ai-seo-client'); ?></option>
                <option value="BreadcrumbList" <?php selected($schemaType, 'BreadcrumbList'); ?>><?php esc_html_e('Breadcrumbs (auto)', 'ai-seo-client'); ?></option>
                <option value="WebSite" <?php selected($schemaType, 'WebSite'); ?>><?php esc_html_e('WebSite (SearchAction)', 'ai-seo-client'); ?></option>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e('Custom JSON-LD (optional)', 'ai-seo-client'); ?></strong></label><br>
            <textarea name="aiseo_custom_schema" rows="5" style="width:100%; font-family:monospace;"><?php echo esc_textarea($customSchema); ?></textarea>
            <small><?php esc_html_e('Override with custom JSON-LD schema', 'ai-seo-client'); ?></small>
        </p>
        <?php
    }

    public function saveSchemaMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_schema_nonce']) || !wp_verify_nonce($_POST['aiseo_schema_nonce'], 'aiseo_schema_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if (isset($_POST['aiseo_schema_type'])) {
            update_post_meta($postId, '_sseo_ai_schema_type', sanitize_text_field($_POST['aiseo_schema_type']));
        }
        if (isset($_POST['aiseo_custom_schema'])) {
            $custom = sanitize_textarea_field($_POST['aiseo_custom_schema']);
            if ($this->isValidJson($custom)) {
                update_post_meta($postId, '_sseo_ai_custom_schema', $custom);
            }
        }
    }

    public function outputSchema(): void
    {
        $schemas = [];

        // WebSite schema (always)
        $schemas[] = $this->getWebSiteSchema();

        // Organization schema (always)
        $schemas[] = $this->getOrganizationSchema();

        // BreadcrumbList (always on non-homepage)
        if (!is_front_page()) {
            $schemas[] = $this->getBreadcrumbSchema();
        }

        // Page-specific schema
        if (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $custom = get_post_meta($post->ID, '_sseo_ai_custom_schema', true);
                if ($custom && $this->isValidJson($custom)) {
                    echo "<script type=\"application/ld+json\">{$custom}</script>\n";
                    return; // Custom overrides everything
                }

                $schemaType = get_post_meta($post->ID, '_sseo_ai_schema_type', true) ?: $this->detectSchemaType($post);
                $schema = $this->generateSchema($post, $schemaType);
                if ($schema) {
                    $schemas[] = $schema;
                }
            }
        }

        // Search results page
        if (is_search()) {
            $schemas[] = $this->getWebSiteSchema();
        }

        // Output all schemas
        foreach ($schemas as $schema) {
            if ($schema) {
                $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                echo "<script type=\"application/ld+json\">{$json}</script>\n";
            }
        }
    }

    private function detectSchemaType(\WP_Post $post): string
    {
        if ($post->post_type === 'product' || $post->post_type === 'wc_product') {
            return 'Product';
        }
        if ($post->post_type === 'page') {
            return 'WebPage';
        }
        if ($this->hasFAQ($post)) {
            return 'FAQPage';
        }
        if ($this->hasHowTo($post)) {
            return 'HowTo';
        }
        return 'Article';
    }

    private function hasFAQ(\WP_Post $post): bool
    {
        return has_block('faq', $post->post_content) || 
               stripos($post->post_content, '[faq') !== false ||
               preg_match('/<h[2-3][^>]*>.*?(faq|frequently asked|veelgestelde).*/i', $post->post_content);
    }

    private function hasHowTo(\WP_Post $post): bool
    {
        return has_block('how-to', $post->post_content) || 
               stripos($post->post_content, '[howto') !== false;
    }

    private function generateSchema(\WP_Post $post, string $type): ?array
    {
        $method = 'get' . $type . 'Schema';
        if (method_exists($this, $method)) {
            return $this->$method($post);
        }
        return $this->getArticleSchema($post);
    }

    private function getArticleSchema(\WP_Post $post): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $this->getHeadline($post),
            'description' => $this->getDescription($post),
            'url' => get_permalink($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified' => get_the_modified_date('c', $post),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
                'url' => get_author_posts_url($post->post_author),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->getSiteLogo(),
                ],
            ],
        ];

        // Featured image
        if (has_post_thumbnail($post->ID)) {
            $thumbId = get_post_thumbnail_id($post->ID);
            $imgUrl = wp_get_attachment_image_url($thumbId, 'full');
            if ($imgUrl) {
                $schema['image'] = [
                    '@type' => 'ImageObject',
                    'url' => $imgUrl,
                    'width' => wp_get_attachment_image_src($thumbId, 'full')[1] ?? null,
                    'height' => wp_get_attachment_image_src($thumbId, 'full')[2] ?? null,
                ];
            }
        }

        // Keywords/categories
        $categories = get_the_category($post->ID);
        if ($categories) {
            $schema['keywords'] = implode(', ', wp_list_pluck($categories, 'name'));
        }

        return $schema;
    }

    private function getBlogPostingSchema(\WP_Post $post): array
    {
        $schema = $this->getArticleSchema($post);
        $schema['@type'] = 'BlogPosting';
        return $schema;
    }

    private function getNewsArticleSchema(\WP_Post $post): array
    {
        $schema = $this->getArticleSchema($post);
        $schema['@type'] = 'NewsArticle';
        return $schema;
    }

    private function getProductSchema(\WP_Post $post): array
    {
        $price = get_post_meta($post->ID, '_price', true) ?: get_post_meta($post->ID, 'price', true);
        $currency = get_post_meta($post->ID, '_currency', true) ?: get_woocommerce_currency() ?: 'EUR';
        $sku = get_post_meta($post->ID, '_sku', true) ?: $post->ID;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => get_the_title($post),
            'description' => $this->getDescription($post),
            'sku' => $sku,
            'url' => get_permalink($post),
        ];

        if ($price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => $currency,
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink($post),
            ];
        }

        // Aggregate rating
        $rating = get_post_meta($post->ID, '_average_rating', true);
        $reviewCount = get_post_meta($post->ID, '_review_count', true);
        if ($rating && $reviewCount) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $reviewCount,
            ];
        }

        // Featured image
        if (has_post_thumbnail($post->ID)) {
            $schema['image'] = wp_get_attachment_image_url(get_post_thumbnail_id($post->ID), 'full');
        }

        return $schema;
    }

    private function getFAQPageSchema(\WP_Post $post): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => [],
        ];

        // Parse FAQ from content
        $faqs = $this->extractFAQs($post->post_content);
        foreach ($faqs as $faq) {
            $schema['mainEntity'][] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }

        return $schema;
    }

    private function getHowToSchema(\WP_Post $post): array
    {
        $steps = $this->extractHowToSteps($post->post_content);

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => get_the_title($post),
            'description' => $this->getDescription($post),
            'step' => [],
        ];

        foreach ($steps as $i => $step) {
            $schema['step'][] = [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => $step['title'],
                'text' => $step['content'],
            ];
        }

        return $schema;
    }

    private function getLocalBusinessSchema(\WP_Post $post): array
    {
        $options = $this->settings->all();
        return [
            '@context' => 'https://schema.org',
            '@type' => $options['local_business_type'] ?? 'LocalBusiness',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'telephone' => $options['business_phone'] ?? '',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $options['business_street'] ?? '',
                'addressLocality' => $options['business_city'] ?? '',
                'postalCode' => $options['business_postal'] ?? '',
                'addressCountry' => $options['business_country'] ?? 'NL',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => $options['business_lat'] ?? '',
                'longitude' => $options['business_lng'] ?? '',
            ],
            'openingHours' => $options['business_hours'] ?? 'Mo-Fr 09:00-17:00',
        ];
    }

    private function getOrganizationSchema(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'logo' => $this->getSiteLogo(),
        ];

        $socialLinks = $this->getSocialLinks();
        if ($socialLinks) {
            $schema['sameAs'] = $socialLinks;
        }

        return $schema;
    }

    private function getWebSiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'url' => home_url(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private function getBreadcrumbSchema(): array
    {
        $crumbs = $this->getBreadcrumbs();
        $itemList = [];

        foreach ($crumbs as $i => $crumb) {
            $itemList[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['title'],
                'item' => $crumb['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $itemList,
        ];
    }

    private function getBreadcrumbs(): array
    {
        $crumbs = [['title' => __('Home', 'ai-seo-client'), 'url' => home_url('/')]];

        if (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $categories = get_the_category($post->ID);
                if ($categories) {
                    $crumbs[] = [
                        'title' => $categories[0]->name,
                        'url' => get_category_link($categories[0]->term_id),
                    ];
                }
                $crumbs[] = [
                    'title' => get_the_title($post),
                    'url' => get_permalink($post),
                ];
            }
        } elseif (is_category()) {
            $crumbs[] = [
                'title' => single_cat_title('', false),
                'url' => get_category_link(get_queried_object_id()),
            ];
        } elseif (is_tag()) {
            $crumbs[] = [
                'title' => single_tag_title('', false),
                'url' => get_tag_link(get_queried_object_id()),
            ];
        } elseif (is_search()) {
            $crumbs[] = [
                'title' => sprintf(__('Search: %s', 'ai-seo-client'), get_search_query()),
                'url' => get_search_link(),
            ];
        }

        return $crumbs;
    }

    private function getHeadline(\WP_Post $post): string
    {
        $title = get_post_meta($post->ID, '_sseo_ai_title', true);
        return $title ?: get_the_title($post);
    }

    private function getDescription(\WP_Post $post): string
    {
        $desc = get_post_meta($post->ID, '_sseo_ai_description', true);
        if ($desc) {
            return $desc;
        }
        return wp_trim_words(strip_shortcodes($post->post_content), 30, '');
    }

    private function getSiteLogo(): string
    {
        $customLogoId = get_theme_mod('custom_logo');
        if ($customLogoId) {
            $url = wp_get_attachment_image_url($customLogoId, 'full');
            if ($url) {
                return $url;
            }
        }
        return get_site_icon_url(512) ?: '';
    }

    private function getSocialLinks(): array
    {
        $links = [];
        $socials = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube'];
        foreach ($socials as $social) {
            $url = $this->settings->get("social_{$social}", '');
            if ($url) {
                $links[] = $url;
            }
        }
        return $links;
    }

    private function extractFAQs(string $content): array
    {
        $faqs = [];

        // Look for heading + paragraph patterns
        if (preg_match_all('/<h[2-4][^>]*>([^<]+)<\/h[2-4]>\s*<p>([^<]+)<\/p>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $question = trim(strip_tags($match[1]));
                $answer = trim(strip_tags($match[2]));
                if (stripos($question, '?') !== false || stripos($question, 'wat') !== false || stripos($question, 'hoe') !== false) {
                    $faqs[] = ['question' => $question, 'answer' => $answer];
                }
            }
        }

        return $faqs;
    }

    private function extractHowToSteps(string $content): array
    {
        $steps = [];

        if (preg_match_all('/<h[2-3][^>]*>(?:stap|step)?\s*(\d+)[:\.]?\s*([^<]+)<\/h[2-3]>\s*<p>([^<]+)<\/p>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $steps[] = [
                    'number' => $match[1],
                    'title' => trim($match[2]),
                    'content' => trim(strip_tags($match[3])),
                ];
            }
        }

        return $steps ?: [['title' => get_the_title(), 'content' => wp_trim_words($content, 50)]];
    }

    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

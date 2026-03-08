<?php

namespace AISEOAssistant;

class WooCommerceSEO
{
    private Settings $settings;
    private SchemaMarkup $schema;

    public function __construct(Settings $settings, SchemaMarkup $schema)
    {
        $this->settings = $settings;
        $this->schema = $schema;
    }

    public function register(): void
    {
        if (!$this->isWooCommerceActive()) {
            return;
        }

        add_action('add_meta_boxes', [$this, 'addProductMetaBoxes']);
        add_action('save_post', [$this, 'saveProductMeta'], 10, 2);
        add_action('woocommerce_single_product_summary', [$this, 'outputProductSchema'], 5);
        add_filter('woocommerce_product_tabs', [$this, 'addSEOTabs']);
        add_action('woocommerce_archive_description', [$this, 'optimizeCategoryDescription'], 5);
        add_filter('wp_head', [$this, 'outputProductMeta'], 1);
    }

    private function isWooCommerceActive(): bool
    {
        return class_exists('WooCommerce') || in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
    }

    public function addProductMetaBoxes(): void
    {
        add_meta_box(
            'aiseo_wc_seo',
            __('AI SEO for Product', 'ai-seo-assistant'),
            [$this, 'renderProductMetaBox'],
            'product',
            'normal',
            'high'
        );
    }

    public function renderProductMetaBox(\WP_Post $post): void
    {
        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        $focusKeyphrase = get_post_meta($post->ID, '_aiseo_focus_keyphrase', true);
        $seoTitle = get_post_meta($post->ID, '_aiseo_title', true);
        $metaDesc = get_post_meta($post->ID, '_aiseo_description', true);
        $productSchemaType = get_post_meta($post->ID, '_aiseo_product_schema_type', true) ?: 'Product';
        $brand = get_post_meta($post->ID, '_aiseo_brand', true);
        $gtin = get_post_meta($post->ID, '_aiseo_gtin', true);
        $mpn = get_post_meta($post->ID, '_aiseo_mpn', true);
        ?>
        <div class="aiseo-wc-seo-box">
            <h4><?php esc_html_e('SEO Settings', 'ai-seo-assistant'); ?></h4>
            <p>
                <label><?php esc_html_e('Focus Keyphrase', 'ai-seo-assistant'); ?></label>
                <input type="text" name="aiseo_focus_keyphrase" value="<?php echo esc_attr($focusKeyphrase); ?>" class="large-text">
            </p>
            <p>
                <label><?php esc_html_e('SEO Title', 'ai-seo-assistant'); ?></label>
                <input type="text" name="aiseo_title" value="<?php echo esc_attr($seoTitle); ?>" class="large-text" placeholder="<?php echo esc_attr($product->get_name()); ?>">
                <small><?php printf(__('Recommended: 50-60 chars. Current: %d', 'ai-seo-assistant'), strlen($seoTitle)); ?></small>
            </p>
            <p>
                <label><?php esc_html_e('Meta Description', 'ai-seo-assistant'); ?></label>
                <textarea name="aiseo_description" rows="3" class="large-text"><?php echo esc_textarea($metaDesc); ?></textarea>
                <small><?php printf(__('Recommended: 150-160 chars. Current: %d', 'ai-seo-assistant'), strlen($metaDesc)); ?></small>
            </p>

            <h4><?php esc_html_e('Product Schema', 'ai-seo-assistant'); ?></h4>
            <p>
                <label><?php esc_html_e('Schema Type', 'ai-seo-assistant'); ?></label>
                <select name="aiseo_product_schema_type">
                    <option value="Product" <?php selected($productSchemaType, 'Product'); ?>><?php esc_html_e('Product', 'ai-seo-assistant'); ?></option>
                    <option value="IndividualProduct" <?php selected($productSchemaType, 'IndividualProduct'); ?>><?php esc_html_e('Individual Product', 'ai-seo-assistant'); ?></option>
                    <option value="ProductGroup" <?php selected($productSchemaType, 'ProductGroup'); ?>><?php esc_html_e('Product Group', 'ai-seo-assistant'); ?></option>
                    <option value="ProductCollection" <?php selected($productSchemaType, 'ProductCollection'); ?>><?php esc_html_e('Product Collection', 'ai-seo-assistant'); ?></option>
                </select>
            </p>
            <p>
                <label><?php esc_html_e('Brand', 'ai-seo-assistant'); ?></label>
                <input type="text" name="aiseo_brand" value="<?php echo esc_attr($brand); ?>" class="regular-text">
            </p>
            <p>
                <label><?php esc_html_e('GTIN (Global Trade Item Number)', 'ai-seo-assistant'); ?></label>
                <input type="text" name="aiseo_gtin" value="<?php echo esc_attr($gtin); ?>" class="regular-text" placeholder="e.g., 1234567890123">
            </p>
            <p>
                <label><?php esc_html_e('MPN (Manufacturer Part Number)', 'ai-seo-assistant'); ?></label>
                <input type="text" name="aiseo_mpn" value="<?php echo esc_attr($mpn); ?>" class="regular-text">
            </p>

            <h4><?php esc_html_e('Product SEO Analysis', 'ai-seo-assistant'); ?></h4>
            <?php $analysis = $this->analyzeProduct($product); ?>
            <ul style="list-style:none; padding:0;">
                <?php foreach ($analysis as $item) : ?>
                <li style="padding:5px; margin:3px 0; background:<?php echo $item['pass'] ? '#edfaef' : '#fcf0f1'; ?>;">
                    <?php echo $item['pass'] ? '✅' : '⚠️'; ?> <?php echo esc_html($item['message']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php wp_nonce_field('aiseo_wc_seo_save', 'aiseo_wc_seo_nonce'); ?>
        <?php
    }

    public function saveProductMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_wc_seo_nonce']) || !wp_verify_nonce($_POST['aiseo_wc_seo_nonce'], 'aiseo_wc_seo_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $fields = [
            'aiseo_focus_keyphrase',
            'aiseo_title',
            'aiseo_description',
            'aiseo_product_schema_type',
            'aiseo_brand',
            'aiseo_gtin',
            'aiseo_mpn',
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $metaKey = '_' . $field;
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($postId, $metaKey, $value);
            }
        }
    }

    public function outputProductSchema(): void
    {
        global $product;
        if (!$product) {
            return;
        }

        $schema = $this->generateProductSchema($product);
        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "<script type=\"application/ld+json\">{$json}</script>\n";
    }

    private function generateProductSchema(\WC_Product $product): array
    {
        $postId = $product->get_id();
        $schemaType = get_post_meta($postId, '_aiseo_product_schema_type', true) ?: 'Product';

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
            'name' => get_post_meta($postId, '_aiseo_title', true) ?: $product->get_name(),
            'description' => get_post_meta($postId, '_aiseo_description', true) ?: wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
            'sku' => $product->get_sku(),
            'url' => get_permalink($postId),
        ];

        // Image
        $imageId = $product->get_image_id();
        if ($imageId) {
            $imageUrl = wp_get_attachment_image_url($imageId, 'full');
            if ($imageUrl) {
                $schema['image'] = [
                    '@type' => 'ImageObject',
                    'url' => $imageUrl,
                ];
            }
        }

        // Brand
        $brand = get_post_meta($postId, '_aiseo_brand', true);
        if ($brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }

        // GTIN & MPN
        $gtin = get_post_meta($postId, '_aiseo_gtin', true);
        if ($gtin) {
            $schema['gtin'] = $gtin;
        }

        $mpn = get_post_meta($postId, '_aiseo_mpn', true);
        if ($mpn) {
            $schema['mpn'] = $mpn;
        }

        // Offers
        $price = $product->get_price();
        if ($price) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'url' => get_permalink($postId),
                'priceCurrency' => get_woocommerce_currency(),
                'price' => $price,
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'priceValidUntil' => date('Y-12-31'),
            ];

            if ($product->is_on_sale() && $product->get_regular_price()) {
                $schema['offers']['priceSpecification'] = [
                    '@type' => 'PriceSpecification',
                    'price' => $price,
                    'priceCurrency' => get_woocommerce_currency(),
                    'valueAddedTaxIncluded' => true,
                ];
            }
        }

        // Aggregate Rating
        $ratingCount = $product->get_review_count();
        if ($ratingCount > 0) {
            $average = $product->get_average_rating();
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $average,
                'reviewCount' => $ratingCount,
            ];
        }

        // Reviews
        $reviews = get_comments([
            'post_id' => $postId,
            'status' => 'approve',
            'type' => 'review',
            'number' => 5,
        ]);

        if ($reviews) {
            $schema['review'] = [];
            foreach ($reviews as $review) {
                $rating = get_comment_meta($review->comment_ID, 'rating', true);
                $schema['review'][] = [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $review->comment_author,
                    ],
                    'datePublished' => $review->comment_date,
                    'reviewBody' => $review->comment_content,
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $rating ?: 5,
                    ],
                ];
            }
        }

        // Additional product properties
        if ($product->get_weight()) {
            $schema['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $product->get_weight(),
                'unitCode' => get_option('woocommerce_weight_unit') === 'kg' ? 'KGM' : 'LBR',
            ];
        }

        return $schema;
    }

    private function analyzeProduct(\WC_Product $product): array
    {
        $analysis = [];
        $postId = $product->get_id();

        // Product name
        $name = $product->get_name();
        $analysis[] = [
            'message' => sprintf(__('Product name is %d characters', 'ai-seo-assistant'), strlen($name)),
            'pass' => strlen($name) >= 10 && strlen($name) <= 100,
        ];

        // Description
        $desc = $product->get_description();
        $analysis[] = [
            'message' => sprintf(__('Description is %d words', 'ai-seo-assistant'), str_word_count($desc)),
            'pass' => str_word_count($desc) >= 50,
        ];

        // Short description
        $shortDesc = $product->get_short_description();
        $analysis[] = [
            'message' => __('Has short description', 'ai-seo-assistant'),
            'pass' => !empty($shortDesc),
        ];

        // SKU
        $analysis[] = [
            'message' => __('Has SKU set', 'ai-seo-assistant'),
            'pass' => !empty($product->get_sku()),
        ];

        // Price
        $analysis[] = [
            'message' => __('Has price set', 'ai-seo-assistant'),
            'pass' => !empty($product->get_price()),
        ];

        // Images
        $galleryIds = $product->get_gallery_image_ids();
        $hasImage = $product->get_image_id();
        $analysis[] = [
            'message' => sprintf(__('Has %d gallery images', 'ai-seo-assistant'), count($galleryIds)),
            'pass' => $hasImage && count($galleryIds) >= 1,
        ];

        // Categories
        $categories = get_the_terms($postId, 'product_cat');
        $analysis[] = [
            'message' => sprintf(__('In %d categories', 'ai-seo-assistant'), is_array($categories) ? count($categories) : 0),
            'pass' => !empty($categories),
        ];

        // SEO Title
        $seoTitle = get_post_meta($postId, '_aiseo_title', true);
        $analysis[] = [
            'message' => $seoTitle ? __('Has custom SEO title', 'ai-seo-assistant') : __('Missing SEO title', 'ai-seo-assistant'),
            'pass' => !empty($seoTitle),
        ];

        // Meta Description
        $metaDesc = get_post_meta($postId, '_aiseo_description', true);
        $analysis[] = [
            'message' => $metaDesc ? __('Has meta description', 'ai-seo-assistant') : __('Missing meta description', 'ai-seo-assistant'),
            'pass' => !empty($metaDesc) && strlen($metaDesc) >= 100,
        ];

        return $analysis;
    }

    public function outputProductMeta(): void
    {
        if (!is_product()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $postId = $product->get_id();
        $title = get_post_meta($postId, '_aiseo_title', true);
        $description = get_post_meta($postId, '_aiseo_description', true);

        if ($title) {
            echo '<title>' . esc_html($title) . '</title>' . "\n";
        }

        if ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        // Canonical
        echo '<link rel="canonical" href="' . esc_url(get_permalink($postId)) . '">' . "\n";

        // Robots
        echo '<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">' . "\n";
    }

    public function addSEOTabs(array $tabs): array
    {
        $tabs['seo_specs'] = [
            'title' => __('Specifications', 'ai-seo-assistant'),
            'priority' => 25,
            'callback' => [$this, 'renderSpecsTab'],
        ];

        return $tabs;
    }

    public function renderSpecsTab(): void
    {
        global $product;
        if (!$product) {
            return;
        }

        $specs = [];

        if ($product->get_sku()) {
            $specs[] = ['label' => __('SKU', 'ai-seo-assistant'), 'value' => $product->get_sku()];
        }

        if ($product->get_weight()) {
            $specs[] = ['label' => __('Weight', 'ai-seo-assistant'), 'value' => $product->get_weight() . ' ' . get_option('woocommerce_weight_unit')];
        }

        $dimensions = $product->get_dimensions(false);
        if ($dimensions) {
            $specs[] = ['label' => __('Dimensions', 'ai-seo-assistant'), 'value' => $dimensions];
        }

        $brand = get_post_meta($product->get_id(), '_aiseo_brand', true);
        if ($brand) {
            $specs[] = ['label' => __('Brand', 'ai-seo-assistant'), 'value' => $brand];
        }

        $gtin = get_post_meta($product->get_id(), '_aiseo_gtin', true);
        if ($gtin) {
            $specs[] = ['label' => __('GTIN', 'ai-seo-assistant'), 'value' => $gtin];
        }

        $mpn = get_post_meta($product->get_id(), '_aiseo_mpn', true);
        if ($mpn) {
            $specs[] = ['label' => __('MPN', 'ai-seo-assistant'), 'value' => $mpn];
        }

        if ($specs) {
            echo '<table class="woocommerce-product-attributes shop_attributes">';
            foreach ($specs as $spec) {
                echo '<tr><th>' . esc_html($spec['label']) . '</th><td>' . esc_html($spec['value']) . '</td></tr>';
            }
            echo '</table>';
        }
    }

    public function optimizeCategoryDescription(): void
    {
        if (!is_product_category()) {
            return;
        }

        $term = get_queried_object();
        if (!$term) {
            return;
        }

        $description = term_description($term->term_id, 'product_cat');
        if (empty($description)) {
            // Generate default description based on products
            $products = wc_get_products([
                'limit' => 3,
                'category' => [$term->slug],
            ]);

            $productNames = array_map(fn($p) => $p->get_name(), $products);
            if ($productNames) {
                echo '<div class="term-description"><p>';
                printf(
                    esc_html__('Browse our selection of %s including %s.', 'ai-seo-assistant'),
                    esc_html($term->name),
                    esc_html(implode(', ', $productNames))
                );
                echo '</p></div>';
            }
        }
    }
}

<?php

namespace SSEOAIClient;

/**
 * WooCommerce SEO
 * 
 * Product-specific schema markup (Product, Offer, AggregateRating),
 * AI product description generator, and product-specific meta optimization.
 * Comparable to RankMath WooCommerce SEO, Yoast WooCommerce SEO.
 */
class WooCommerceSeo
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
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_action('wp_head', [$this, 'outputProductSchema'], 5);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post_product', [$this, 'saveMeta'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Add global price schema to shop/archive pages
        add_action('wp_head', [$this, 'outputCollectionSchema'], 6);

        // Remove default WC schema if we handle it
        add_filter('woocommerce_structured_data_product', '__return_empty_array', 99);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/woo/generate-description', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateDescription'],
            'permission_callback' => function () {
                return current_user_can('edit_products');
            },
            'args' => [
                'product_id' => ['type' => 'integer', 'required' => true],
                'type' => ['type' => 'string', 'default' => 'short'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/woo/generate-meta', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateMeta'],
            'permission_callback' => function () {
                return current_user_can('edit_products');
            },
            'args' => [
                'product_id' => ['type' => 'integer', 'required' => true],
            ],
        ]);
    }

    /**
     * Output Product JSON-LD schema on single product pages
     */
    public function outputProductSchema(): void
    {
        if (!is_singular('product')) {
            return;
        }

        global $post;
        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags($product->get_short_description() ?: $product->get_description()),
            'sku' => $product->get_sku(),
            'url' => get_permalink($post->ID),
        ];

        // Images
        $imageId = $product->get_image_id();
        if ($imageId) {
            $imgSrc = wp_get_attachment_image_src($imageId, 'full');
            if ($imgSrc) {
                $schema['image'] = $imgSrc[0];
            }
        }
        $galleryIds = $product->get_gallery_image_ids();
        if (!empty($galleryIds) && empty($schema['image'])) {
            $imgSrc = wp_get_attachment_image_src($galleryIds[0], 'full');
            if ($imgSrc) {
                $schema['image'] = $imgSrc[0];
            }
        }

        // Brand (from product attribute or custom meta)
        $brand = get_post_meta($post->ID, '_sseo_ai_product_brand', true);
        if (!$brand) {
            $brand = $product->get_attribute('brand') ?: $product->get_attribute('merk');
        }
        if ($brand) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        // GTIN / MPN
        $gtin = get_post_meta($post->ID, '_sseo_ai_product_gtin', true) ?: $product->get_attribute('gtin');
        $mpn = get_post_meta($post->ID, '_sseo_ai_product_mpn', true) ?: $product->get_attribute('mpn');
        if ($gtin) $schema['gtin13'] = $gtin;
        if ($mpn) $schema['mpn'] = $mpn;

        // Offers
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            $prices = array_map(fn($v) => (float) $v['display_price'], $variations);
            $schema['offers'] = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => get_woocommerce_currency(),
                'lowPrice' => min($prices) ?: $product->get_price(),
                'highPrice' => max($prices) ?: $product->get_price(),
                'offerCount' => count($variations),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => get_permalink($post->ID),
            ];
        } else {
            $schema['offers'] = [
                '@type' => 'Offer',
                'priceCurrency' => get_woocommerce_currency(),
                'price' => $product->get_price(),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => get_permalink($post->ID),
            ];

            // Sale price
            if ($product->is_on_sale()) {
                $schema['offers']['priceValidUntil'] = $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->format('Y-m-d') : '';
            }
        }

        // Reviews / Rating
        $reviewCount = $product->get_review_count();
        $avgRating = $product->get_average_rating();
        if ($reviewCount > 0 && $avgRating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $avgRating,
                'reviewCount' => $reviewCount,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // Category
        $terms = get_the_terms($post->ID, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $schema['category'] = $terms[0]->name;
        }

        // Weight / Dimensions
        if ($product->has_weight()) {
            $schema['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $product->get_weight(),
                'unitCode' => get_option('woocommerce_weight_unit', 'kg'),
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }

    /**
     * Output CollectionPage schema on shop/category archives
     */
    public function outputCollectionSchema(): void
    {
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        $title = '';
        $url = '';

        if (is_shop()) {
            $title = get_the_title(wc_get_page_id('shop'));
            $url = wc_get_page_permalink('shop');
        } elseif (is_product_category() || is_product_tag()) {
            $term = get_queried_object();
            $title = $term->name ?? '';
            $url = get_term_link($term);
        }

        if (!$title || is_wp_error($url)) return;

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'CollectionPage',
            'name' => $title,
            'url' => $url,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }

    /**
     * Product SEO meta box
     */
    public function addMetaBox(): void
    {
        add_meta_box(
            'aiseo_woo_seo',
            __('AI SEO: Product Optimization', 'ai-seo-client'),
            [$this, 'renderMetaBox'],
            'product',
            'normal',
            'default'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $brand = get_post_meta($post->ID, '_sseo_ai_product_brand', true);
        $gtin = get_post_meta($post->ID, '_sseo_ai_product_gtin', true);
        $mpn = get_post_meta($post->ID, '_sseo_ai_product_mpn', true);

        wp_nonce_field('aiseo_woo_save', 'aiseo_woo_nonce');
        ?>
        <style>
            .aiseo-woo-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
            .aiseo-woo-field label { display: block; font-weight: 600; margin-bottom: 4px; }
            .aiseo-woo-field input { width: 100%; }
            .aiseo-woo-actions { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        </style>

        <h4><?php esc_html_e('Product Schema Data', 'ai-seo-client'); ?></h4>
        <p class="description"><?php esc_html_e('Enhance your product schema markup for better rich snippet display in Google.', 'ai-seo-client'); ?></p>

        <div class="aiseo-woo-grid">
            <div class="aiseo-woo-field">
                <label for="aiseo_product_brand"><?php esc_html_e('Brand', 'ai-seo-client'); ?></label>
                <input type="text" id="aiseo_product_brand" name="aiseo_product_brand"
                       value="<?php echo esc_attr($brand); ?>" placeholder="<?php esc_attr_e('e.g. Nike, Samsung', 'ai-seo-client'); ?>">
            </div>
            <div class="aiseo-woo-field">
                <label for="aiseo_product_gtin"><?php esc_html_e('GTIN / EAN / UPC', 'ai-seo-client'); ?></label>
                <input type="text" id="aiseo_product_gtin" name="aiseo_product_gtin"
                       value="<?php echo esc_attr($gtin); ?>" placeholder="<?php esc_attr_e('e.g. 0123456789012', 'ai-seo-client'); ?>">
            </div>
            <div class="aiseo-woo-field">
                <label for="aiseo_product_mpn"><?php esc_html_e('MPN', 'ai-seo-client'); ?></label>
                <input type="text" id="aiseo_product_mpn" name="aiseo_product_mpn"
                       value="<?php echo esc_attr($mpn); ?>" placeholder="<?php esc_attr_e('Manufacturer Part Number', 'ai-seo-client'); ?>">
            </div>
        </div>

        <h4><?php esc_html_e('AI Product Content', 'ai-seo-client'); ?></h4>
        <div class="aiseo-woo-actions">
            <button type="button" class="button" id="aiseo-woo-gen-short" data-product="<?php echo $post->ID; ?>">
                <?php esc_html_e('AI Generate Short Description', 'ai-seo-client'); ?>
            </button>
            <button type="button" class="button" id="aiseo-woo-gen-long" data-product="<?php echo $post->ID; ?>">
                <?php esc_html_e('AI Generate Full Description', 'ai-seo-client'); ?>
            </button>
            <button type="button" class="button" id="aiseo-woo-gen-meta" data-product="<?php echo $post->ID; ?>">
                <?php esc_html_e('AI Generate SEO Meta', 'ai-seo-client'); ?>
            </button>
        </div>
        <div id="aiseo-woo-result" style="margin-top:15px;"></div>

        <script>
        jQuery(document).ready(function($) {
            function wooGenerate(productId, type, btn) {
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');
                var resultDiv = $('#aiseo-woo-result');

                wp.apiFetch({
                    path: 'sseo-ai/v1/woo/generate-description',
                    method: 'POST',
                    data: { product_id: productId, type: type }
                }).then(function(res) {
                    if (res.text) {
                        resultDiv.html(
                            '<div class="postbox" style="padding:15px;">' +
                            '<h4><?php echo esc_js(__('Generated Content', 'ai-seo-client')); ?></h4>' +
                            '<div style="white-space:pre-wrap;background:#f9f9f9;padding:10px;border:1px solid #ddd;border-radius:4px;">' +
                            $('<div>').text(res.text).html() +
                            '</div>' +
                            '<p style="margin-top:10px;color:#666;"><?php echo esc_js(__('Copy this content to the product description field.', 'ai-seo-client')); ?></p>' +
                            '</div>'
                        );
                    }
                    btn.prop('disabled', false).text(btn.data('original') || btn.text());
                }).catch(function(err) {
                    resultDiv.html('<p style="color:#d63638;">' + (err.message || 'Failed') + '</p>');
                    btn.prop('disabled', false).text(btn.data('original') || btn.text());
                });
            }

            $('#aiseo-woo-gen-short').each(function() { $(this).data('original', $(this).text()); }).on('click', function() {
                wooGenerate($(this).data('product'), 'short', $(this));
            });
            $('#aiseo-woo-gen-long').each(function() { $(this).data('original', $(this).text()); }).on('click', function() {
                wooGenerate($(this).data('product'), 'long', $(this));
            });

            // SEO Meta generation
            $('#aiseo-woo-gen-meta').each(function() { $(this).data('original', $(this).text()); }).on('click', function() {
                var btn = $(this);
                var productId = btn.data('product');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/woo/generate-meta',
                    method: 'POST',
                    data: { product_id: productId }
                }).then(function(res) {
                    var html = '<div class="postbox" style="padding:15px;">';
                    html += '<h4><?php echo esc_js(__('Generated SEO Meta', 'ai-seo-client')); ?></h4>';
                    if (res.seo_title) html += '<p><strong>SEO Title:</strong> ' + $('<span>').text(res.seo_title).html() + '</p>';
                    if (res.seo_description) html += '<p><strong>Meta Description:</strong> ' + $('<span>').text(res.seo_description).html() + '</p>';
                    if (res.focus_keyphrase) html += '<p><strong>Focus Keyphrase:</strong> ' + $('<span>').text(res.focus_keyphrase).html() + '</p>';
                    html += '<p style="color:#00a32a;"><?php echo esc_js(__('Meta saved to post!', 'ai-seo-client')); ?></p>';
                    html += '</div>';
                    $('#aiseo-woo-result').html(html);
                    btn.prop('disabled', false).text(btn.data('original'));
                }).catch(function(err) {
                    $('#aiseo-woo-result').html('<p style="color:#d63638;">' + (err.message || 'Failed') + '</p>');
                    btn.prop('disabled', false).text(btn.data('original'));
                });
            });
        });
        </script>
        <?php
    }

    public function saveMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_woo_nonce']) || !wp_verify_nonce($_POST['aiseo_woo_nonce'], 'aiseo_woo_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $postId)) return;

        $fields = ['_sseo_ai_product_brand' => 'aiseo_product_brand', '_sseo_ai_product_gtin' => 'aiseo_product_gtin', '_sseo_ai_product_mpn' => 'aiseo_product_mpn'];
        foreach ($fields as $metaKey => $postKey) {
            if (isset($_POST[$postKey])) {
                update_post_meta($postId, $metaKey, sanitize_text_field($_POST[$postKey]));
            }
        }
    }

    /**
     * REST: AI generate product description
     */
    public function restGenerateDescription(\WP_REST_Request $request): array|\WP_Error
    {
        $productId = (int) $request->get_param('product_id');
        $type = $request->get_param('type') ?: 'short';

        if (!$this->llm) {
            return new \WP_Error('no_ai', __('AI features not available', 'ai-seo-client'));
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return new \WP_Error('not_found', __('Product not found', 'ai-seo-client'));
        }

        $name = $product->get_name();
        $price = $product->get_price();
        $currency = get_woocommerce_currency();
        $categories = wc_get_product_category_list($productId, ', ');
        $categories = wp_strip_all_tags($categories);
        $attributes = [];
        foreach ($product->get_attributes() as $attr) {
            if (is_object($attr) && method_exists($attr, 'get_name')) {
                $attrName = wc_attribute_label($attr->get_name());
                $attrVals = $attr->is_taxonomy()
                    ? implode(', ', wc_get_product_terms($productId, $attr->get_name(), ['fields' => 'names']))
                    : $attr->get_options();
                if (is_array($attrVals)) $attrVals = implode(', ', $attrVals);
                $attributes[] = "{$attrName}: {$attrVals}";
            }
        }
        $attrText = !empty($attributes) ? implode("\n", $attributes) : 'None listed';

        $existingDesc = wp_strip_all_tags($product->get_description());
        $existingShort = wp_strip_all_tags($product->get_short_description());

        if ($type === 'short') {
            $prompt = "Write a compelling, SEO-optimized SHORT product description (2-3 sentences, max 150 words) for:

Product: {$name}
Price: {$currency} {$price}
Categories: {$categories}
Attributes: {$attrText}
" . ($existingDesc ? "Current long description: " . mb_substr($existingDesc, 0, 300) : "") . "

Focus on benefits, unique selling points, and include relevant keywords naturally. Write in a professional, persuasive tone.";
        } else {
            $prompt = "Write a detailed, SEO-optimized FULL product description (300-500 words) for:

Product: {$name}
Price: {$currency} {$price}
Categories: {$categories}
Attributes: {$attrText}
" . ($existingShort ? "Short description: {$existingShort}" : "") . "

Structure with:
- Opening hook (why this product)
- Key features and benefits (use bullet points or short paragraphs)
- Use cases / who it's for
- Technical specifications if relevant
- Closing CTA

Write in a professional, persuasive tone. Include relevant keywords naturally for SEO.";
        }

        $result = $this->llm->call($prompt, null, null, 800);
        if (is_wp_error($result)) {
            return $result;
        }

        return ['text' => $result['text'] ?? ''];
    }

    /**
     * REST: AI generate product SEO meta
     */
    public function restGenerateMeta(\WP_REST_Request $request): array|\WP_Error
    {
        $productId = (int) $request->get_param('product_id');

        if (!$this->llm) {
            return new \WP_Error('no_ai', __('AI features not available', 'ai-seo-client'));
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return new \WP_Error('not_found', __('Product not found', 'ai-seo-client'));
        }

        $name = $product->get_name();
        $price = $product->get_price();
        $currency = get_woocommerce_currency();
        $desc = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());

        $prompt = "Generate SEO metadata for this product:

Product: {$name}
Price: {$currency} {$price}
Description: " . mb_substr($desc, 0, 300) . "

Return JSON only (no markdown):
{
    \"seo_title\": \"Max 60 chars, include product name and keyword\",
    \"seo_description\": \"Max 155 chars, compelling with price and CTA\",
    \"focus_keyphrase\": \"2-4 word main keyword\"
}";

        $result = $this->llm->call($prompt, null, null, 300);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!$data) {
            return new \WP_Error('parse_error', __('Failed to parse AI response', 'ai-seo-client'));
        }

        // Save to post meta
        if (!empty($data['seo_title'])) {
            update_post_meta($productId, '_sseo_ai_title', sanitize_text_field($data['seo_title']));
        }
        if (!empty($data['seo_description'])) {
            update_post_meta($productId, '_sseo_ai_description', sanitize_text_field($data['seo_description']));
        }
        if (!empty($data['focus_keyphrase'])) {
            update_post_meta($productId, '_sseo_ai_focus_keyphrase', sanitize_text_field($data['focus_keyphrase']));
        }

        return [
            'seo_title' => $data['seo_title'] ?? '',
            'seo_description' => $data['seo_description'] ?? '',
            'focus_keyphrase' => $data['focus_keyphrase'] ?? '',
        ];
    }
}

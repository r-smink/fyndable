<?php

namespace SSEOAIClient;

/**
 * Multi-CMS Publishing
 *
 * Publishes AI-generated content to external CMS platforms:
 * - Webflow (CMS API)
 * - Shopify (Blog API)
 *
 * Allows users to generate content in WordPress and push to external platforms.
 */
class MultiCMSPublisher
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'addMenu']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Multi-CMS Publishing', 'ai-seo-client'),
            __('Multi-CMS Publish', 'ai-seo-client'),
            'manage_options',
            'ai-seo-multi-cms',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/multi-cms/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSettings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/multi-cms/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveSettings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/multi-cms/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'restPublish'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/multi-cms/collections', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCollections'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    private const SETTINGS_KEY = 'sseo_ai_multi_cms_settings';

    public function getSettings(): array
    {
        $defaults = [
            'webflow' => [
                'enabled' => false,
                'api_token' => '',
                'site_id' => '',
                'collection_id' => '',
            ],
            'shopify' => [
                'enabled' => false,
                'shop_domain' => '',
                'access_token' => '',
                'blog_id' => '',
            ],
        ];

        $saved = get_option(self::SETTINGS_KEY, []);
        if (!is_array($saved)) $saved = [];

        // Deep merge
        foreach ($defaults as $platform => $config) {
            if (!isset($saved[$platform])) $saved[$platform] = $config;
            else $saved[$platform] = array_merge($config, $saved[$platform]);
        }

        return $saved;
    }

    private function saveSettings(array $settings): array
    {
        $sanitized = [
            'webflow' => [
                'enabled' => (bool)($settings['webflow']['enabled'] ?? false),
                'api_token' => sanitize_text_field($settings['webflow']['api_token'] ?? ''),
                'site_id' => sanitize_text_field($settings['webflow']['site_id'] ?? ''),
                'collection_id' => sanitize_text_field($settings['webflow']['collection_id'] ?? ''),
            ],
            'shopify' => [
                'enabled' => (bool)($settings['shopify']['enabled'] ?? false),
                'shop_domain' => sanitize_text_field($settings['shopify']['shop_domain'] ?? ''),
                'access_token' => sanitize_text_field($settings['shopify']['access_token'] ?? ''),
                'blog_id' => sanitize_text_field($settings['shopify']['blog_id'] ?? ''),
            ],
        ];
        update_option(self::SETTINGS_KEY, $sanitized);
        return $sanitized;
    }

    /**
     * Publish a post to Webflow CMS.
     */
    public function publishToWebflow(int $postId, array $settings): array|\WP_Error
    {
        $post = get_post($postId);
        if (!$post) return new \WP_Error('no_post', __('Post not found', 'ai-seo-client'));

        $token = $settings['api_token'];
        $siteId = $settings['site_id'];
        $collectionId = $settings['collection_id'];

        if (empty($token) || empty($siteId) || empty($collectionId)) {
            return new \WP_Error('missing_config', __('Webflow API token, site ID, and collection ID required', 'ai-seo-client'));
        }

        $requestBody = [
            'fields' => [
                'name' => $post->post_title,
                'slug' => $post->post_name,
                '_archived' => false,
                '_draft' => false,
                'post-body' => $post->post_content,
                'post-summary' => get_post_meta($postId, '_sseo_ai_description', true) ?: wp_trim_words(wp_strip_all_tags($post->post_content), 30),
            ],
        ];

        $response = wp_remote_post("https://api.webflow.com/collections/{$collectionId}/items", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept-Version' => '1.0.0',
            ],
            'body' => json_encode($requestBody),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new \WP_Error('webflow_error', sprintf('Webflow API error: %s', $responseBody['message'] ?? 'Unknown'), ['status' => $code]);
        }

        update_post_meta($postId, '_sseo_ai_webflow_item_id', $responseBody['_id'] ?? '');

        return [
            'platform' => 'webflow',
            'item_id' => $responseBody['_id'] ?? '',
            'url' => $responseBody['url'] ?? '',
        ];
    }

    /**
     * Publish a post to Shopify blog.
     */
    public function publishToShopify(int $postId, array $settings): array|\WP_Error
    {
        $post = get_post($postId);
        if (!$post) return new \WP_Error('no_post', __('Post not found', 'ai-seo-client'));

        $domain = $settings['shop_domain'];
        $token = $settings['access_token'];
        $blogId = $settings['blog_id'];

        if (empty($domain) || empty($token)) {
            return new \WP_Error('missing_config', __('Shopify domain and access token required', 'ai-seo-client'));
        }
        if (empty($blogId)) {
            return new \WP_Error('missing_blog_id', __('Shopify Blog ID is required to publish articles', 'ai-seo-client'));
        }

        $endpoint = "https://{$domain}/admin/api/2024-01/blogs/{$blogId}/articles.json";

        $article = [
            'article' => [
                'title' => $post->post_title,
                'body_html' => $post->post_content,
                'published' => $post->post_status === 'publish',
                'metafields' => [
                    [
                        'namespace' => 'seo',
                        'key' => 'description',
                        'value' => get_post_meta($postId, '_sseo_ai_description', true) ?: wp_trim_words(wp_strip_all_tags($post->post_content), 30),
                        'type' => 'single_line_text_field',
                    ],
                ],
            ],
        ];

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($article),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            return new \WP_Error('shopify_error', sprintf('Shopify API error: %s', $body['errors'] ?? 'Unknown'), ['status' => $code]);
        }

        $articleId = $body['article']['id'] ?? '';
        update_post_meta($postId, '_sseo_ai_shopify_article_id', $articleId);

        return [
            'platform' => 'shopify',
            'article_id' => $articleId,
        ];
    }

    /**
     * Get Webflow collections for a site.
     */
    public function getWebflowCollections(string $token, string $siteId): array|\WP_Error
    {
        $response = wp_remote_get("https://api.webflow.com/sites/{$siteId}/collections", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept-Version' => '1.0.0',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['collections'] ?? [];
    }

    // REST handlers
    public function restGetSettings(): array
    {
        return $this->getSettings();
    }

    public function restSaveSettings(\WP_REST_Request $request): array
    {
        $settings = $request->get_params();
        return ['success' => true, 'settings' => $this->saveSettings($settings)];
    }

    public function restPublish(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int)($request->get_param('post_id') ?: 0);
        $platform = sanitize_text_field($request->get_param('platform') ?? 'webflow');
        if (!$postId) return new \WP_Error('missing', __('post_id required', 'ai-seo-client'), ['status' => 400]);

        $settings = $this->getSettings();

        if ($platform === 'webflow') {
            if (empty($settings['webflow']['enabled'])) return new \WP_Error('disabled', __('Webflow not enabled', 'ai-seo-client'), ['status' => 400]);
            $result = $this->publishToWebflow($postId, $settings['webflow']);
        } elseif ($platform === 'shopify') {
            if (empty($settings['shopify']['enabled'])) return new \WP_Error('disabled', __('Shopify not enabled', 'ai-seo-client'), ['status' => 400]);
            $result = $this->publishToShopify($postId, $settings['shopify']);
        } else {
            return new \WP_Error('unknown_platform', __('Unknown platform', 'ai-seo-client'), ['status' => 400]);
        }

        if (is_wp_error($result)) return $result;
        return ['success' => true, 'result' => $result];
    }

    public function restGetCollections(\WP_REST_Request $request): array|\WP_Error
    {
        $platform = sanitize_text_field($request->get_param('platform') ?? 'webflow');
        $settings = $this->getSettings();

        if ($platform === 'webflow') {
            $collections = $this->getWebflowCollections($settings['webflow']['api_token'], $settings['webflow']['site_id']);
            if (is_wp_error($collections)) return $collections;
            return ['collections' => $collections];
        }

        return ['collections' => []];
    }

    public function renderPage(): void
    {
        $settings = $this->getSettings();
        ?>
        <style>
            .mcms-wrap { max-width: 800px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .mcms-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 20px; }
            .mcms-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 20px 30px; border-radius: 12px 12px 0 0; margin: -30px -30px 20px -30px; }
            .mcms-header h1 { margin: 0; font-size: 22px; }
            .mcms-header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 13px; }
            .mcms-field { margin-bottom: 15px; }
            .mcms-field label { font-weight: 600; display: block; margin-bottom: 4px; }
            .mcms-field input { width: 100%; }
            .mcms-platform { border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
            .mcms-platform h3 { margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px; }
        </style>
        <div class="wrap mcms-wrap">
            <div class="mcms-card">
                <div class="mcms-header">
                    <h1>🌐 <?php esc_html_e('Multi-CMS Publishing', 'ai-seo-client'); ?></h1>
                    <p><?php esc_html_e('Publish AI-generated content to Webflow, Shopify, and other CMS platforms.', 'ai-seo-client'); ?></p>
                </div>

                <!-- Webflow -->
                <div class="mcms-platform">
                    <h3>🔵 Webflow CMS</h3>
                    <div class="mcms-field">
                        <label><input type="checkbox" id="mcms-wf-enabled" <?php checked($settings['webflow']['enabled']); ?>> <?php esc_html_e('Enable Webflow publishing', 'ai-seo-client'); ?></label>
                    </div>
                    <div class="mcms-field">
                        <label><?php esc_html_e('API Token', 'ai-seo-client'); ?></label>
                        <input type="password" id="mcms-wf-token" value="<?php echo esc_attr($settings['webflow']['api_token']); ?>" placeholder="Webflow API token">
                    </div>
                    <div class="mcms-field">
                        <label><?php esc_html_e('Site ID', 'ai-seo-client'); ?></label>
                        <input type="text" id="mcms-wf-site" value="<?php echo esc_attr($settings['webflow']['site_id']); ?>" placeholder="e.g. 62c1a3b5...">
                    </div>
                    <div class="mcms-field">
                        <label><?php esc_html_e('Collection ID', 'ai-seo-client'); ?></label>
                        <input type="text" id="mcms-wf-collection" value="<?php echo esc_attr($settings['webflow']['collection_id']); ?>" placeholder="e.g. 62c1a3b5...">
                    </div>
                </div>

                <!-- Shopify -->
                <div class="mcms-platform">
                    <h3>🟢 Shopify Blog</h3>
                    <div class="mcms-field">
                        <label><input type="checkbox" id="mcms-sh-enabled" <?php checked($settings['shopify']['enabled']); ?>> <?php esc_html_e('Enable Shopify publishing', 'ai-seo-client'); ?></label>
                    </div>
                    <div class="mcms-field">
                        <label><?php esc_html_e('Shop Domain', 'ai-seo-client'); ?></label>
                        <input type="text" id="mcms-sh-domain" value="<?php echo esc_attr($settings['shopify']['shop_domain']); ?>" placeholder="your-store.myshopify.com">
                    </div>
                    <div class="mcms-field">
                        <label><?php esc_html_e('Access Token', 'ai-seo-client'); ?></label>
                        <input type="password" id="mcms-sh-token" value="<?php echo esc_attr($settings['shopify']['access_token']); ?>" placeholder="Shopify Admin API token">
                    </div>
                    <div class="mcms-field">
                        <label><?php esc_html_e('Blog ID (optional)', 'ai-seo-client'); ?></label>
                        <input type="text" id="mcms-sh-blog" value="<?php echo esc_attr($settings['shopify']['blog_id']); ?>" placeholder="e.g. 123456789">
                    </div>
                </div>

                <button class="button button-primary" id="mcms-save"><?php esc_html_e('Save Settings', 'ai-seo-client'); ?></button>
                <div id="mcms-saved" style="display:none;color:#16a34a;margin-top:10px;">✅ <?php esc_html_e('Saved!', 'ai-seo-client'); ?></div>
            </div>

            <!-- Publish Panel -->
            <div class="mcms-card">
                <h3><?php esc_html_e('Publish a Post', 'ai-seo-client'); ?></h3>
                <p><?php esc_html_e('Select a post and platform to publish externally.', 'ai-seo-client'); ?></p>
                <div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
                    <div>
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Post', 'ai-seo-client'); ?></label>
                        <select id="mcms-post-select" style="min-width:300px;">
                            <option value=""><?php esc_html_e('— Select —', 'ai-seo-client'); ?></option>
                            <?php
                            $posts = get_posts(['post_type' => 'post', 'post_status' => ['publish', 'draft'], 'posts_per_page' => 100]);
                            foreach ($posts as $p) {
                                echo '<option value="' . $p->ID . '">' . esc_html($p->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Platform', 'ai-seo-client'); ?></label>
                        <select id="mcms-platform-select">
                            <option value="webflow">Webflow</option>
                            <option value="shopify">Shopify</option>
                        </select>
                    </div>
                    <button class="button button-primary" id="mcms-publish-btn"><?php esc_html_e('Publish', 'ai-seo-client'); ?></button>
                </div>
                <div id="mcms-publish-result" style="margin-top:15px;"></div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#mcms-save').on('click', function() {
                var data = {
                    webflow: {
                        enabled: $('#mcms-wf-enabled').is(':checked'),
                        api_token: $('#mcms-wf-token').val(),
                        site_id: $('#mcms-wf-site').val(),
                        collection_id: $('#mcms-wf-collection').val(),
                    },
                    shopify: {
                        enabled: $('#mcms-sh-enabled').is(':checked'),
                        shop_domain: $('#mcms-sh-domain').val(),
                        access_token: $('#mcms-sh-token').val(),
                        blog_id: $('#mcms-sh-blog').val(),
                    }
                };
                wp.apiFetch({
                    path: '/sseo-ai/v1/multi-cms/settings',
                    method: 'POST',
                    data: data
                }).then(function() {
                    $('#mcms-saved').show().delay(2000).fadeOut();
                });
            });

            $('#mcms-publish-btn').on('click', function() {
                var postId = $('#mcms-post-select').val();
                var platform = $('#mcms-platform-select').val();
                if (!postId) { alert('<?php echo esc_js(__("Select a post", "ai-seo-client")); ?>'); return; }
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__("Publishing...", "ai-seo-client")); ?>');
                wp.apiFetch({
                    path: '/sseo-ai/v1/multi-cms/publish',
                    method: 'POST',
                    data: { post_id: postId, platform: platform }
                }).then(function(res) {
                    $('#mcms-publish-result').html('<div style="background:#dcfce7;padding:15px;border-radius:8px;">✅ <?php echo esc_js(__("Published to", "ai-seo-client")); ?> ' + platform + '!<br><?php echo esc_js(__("Item ID:", "ai-seo-client")); ?> ' + (res.result.item_id || res.result.article_id || '—') + '</div>');
                }).catch(function(err) {
                    $('#mcms-publish-result').html('<div style="background:#fee2e2;padding:15px;border-radius:8px;color:#dc2626;">❌ ' + (err.message || 'Publish failed') + '</div>');
                }).finally(function() {
                    btn.prop('disabled', false).text('<?php echo esc_js(__("Publish", "ai-seo-client")); ?>');
                });
            });
        });
        </script>
        <?php
    }
}

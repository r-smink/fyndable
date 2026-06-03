<?php

namespace SSEOAIClient;

/**
 * AI Image Generator
 * 
 * Advanced AI image generation:
 * - Auto-generate featured images
 * - Social media images (OG, Twitter)
 * - Custom image generation from content
 * - Image optimization for SEO
 */
class AIImageGenerator
{
    private Settings $settings;
    private LLMClient $llm;
    
    public function __construct(Settings $settings, LLMClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_generate_featured_image', [$this, 'ajaxGenerateFeaturedImage']);
        add_action('wp_ajax_sseo_ai_generate_social_images', [$this, 'ajaxGenerateSocialImages']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('AI Image Generator', 'ai-seo-client'),
            __('AI Images', 'ai-seo-client'),
            'manage_options',
            'ai-seo-image-generator',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render AI image generator dashboard
     */
    public function renderDashboard(): void
    {
        $stats = $this->getImageStats();
        
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('AI Image Generator', 'ai-seo-client'); ?></h1>
            </div>
            
            <div class="sseo-ai-content">
                <!-- Image Statistics -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('AI Image Overview', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo esc_html($stats['total_generated']); ?>
                        </div>
                        <div><?php esc_html_e('Images Generated', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #00a32a;">
                            <?php echo esc_html($stats['featured_images']); ?>
                        </div>
                        <div><?php esc_html_e('Featured Images', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #856404;">
                            <?php echo esc_html($stats['social_images']); ?>
                        </div>
                        <div><?php esc_html_e('Social Images', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                            <?php echo esc_html($stats['missing_featured']); ?>
                        </div>
                        <div><?php esc_html_e('Posts Without Featured', 'ai-seo-client'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Image Generation -->
            <div class="sseo-ai-dashboard-card">
                <h2><?php esc_html_e('Bulk Image Generation', 'ai-seo-client'); ?></h2>
                
                <form method="post" id="bulk-image-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Image Type', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="generate_featured" value="1" checked>
                                    <?php esc_html_e('Featured Images', 'ai-seo-client'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="generate_og" value="1">
                                    <?php esc_html_e('Open Graph Images (1200x630)', 'ai-seo-client'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="generate_twitter" value="1">
                                    <?php esc_html_e('Twitter Card Images (1200x600)', 'ai-seo-client'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Target Posts', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="radio" name="target" value="missing" checked>
                                    <?php esc_html_e('Only posts without featured images', 'ai-seo-client'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="target" value="all">
                                    <?php esc_html_e('All published posts', 'ai-seo-client'); ?>
                                </label><br>
                                <label>
                                    <input type="radio" name="target" value="recent">
                                    <?php esc_html_e('Recent posts (last 30 days)', 'ai-seo-client'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Image Style', 'ai-seo-client'); ?></label>
                            </th>
                            <td>
                                <select name="image_style">
                                    <option value="photorealistic"><?php esc_html_e('Photorealistic', 'ai-seo-client'); ?></option>
                                    <option value="illustration"><?php esc_html_e('Illustration', 'ai-seo-client'); ?></option>
                                    <option value="abstract"><?php esc_html_e('Abstract', 'ai-seo-client'); ?></option>
                                    <option value="minimalist"><?php esc_html_e('Minimalist', 'ai-seo-client'); ?></option>
                                    <option value="3d-render"><?php esc_html_e('3D Render', 'ai-seo-client'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" class="button button-primary button-hero" onclick="sseoBulkGenerateImages()">
                            <?php esc_html_e('Start Bulk Generation', 'ai-seo-client'); ?>
                        </button>
                    </p>
                </form>
                
                <div id="bulk-progress" style="display: none; margin-top: 20px;">
                    <div style="background: #f0f6fc; padding: 15px; border-left: 4px solid #2271b1;">
                        <h4><?php esc_html_e('Generation Progress', 'ai-seo-client'); ?></h4>
                        <div class="progress-bar" style="background: #e0e0e0; height: 30px; border-radius: 4px; overflow: hidden;">
                            <div id="progress-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <p id="progress-text" style="margin-top: 10px;">0 / 0 images generated</p>
                    </div>
                </div>
            </div>
            </div>
        </div>
        
        <script>
        function sseoBulkGenerateImages() {
            if (!confirm('<?php esc_html_e('Start bulk image generation? This may take several minutes.', 'ai-seo-client'); ?>')) {
                return;
            }
            
            const formData = new FormData(document.getElementById('bulk-image-form'));
            
            jQuery('#bulk-progress').show();
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_bulk_generate_images',
                form_data: Object.fromEntries(formData),
                nonce: '<?php echo wp_create_nonce('sseo_images'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Bulk generation completed!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error generating images');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Add AI image generator meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-ai-images',
                __('AI Image Generator', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render AI image generator meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $hasFeatured = has_post_thumbnail($post->ID);
        $ogImage = get_post_meta($post->ID, '_sseo_ai_og_image', true);
        $twitterImage = get_post_meta($post->ID, '_sseo_ai_twitter_image', true);
        
        ?>
        <div class="sseo-ai-images-box">
            <!-- Featured Image -->
            <div style="margin-bottom: 15px;">
                <strong><?php esc_html_e('Featured Image:', 'ai-seo-client'); ?></strong>
                <?php if ($hasFeatured): ?>
                <div style="margin: 10px 0;">
                    <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
                </div>
                <button type="button" class="button" onclick="sseoRegenerateFeatured(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Regenerate', 'ai-seo-client'); ?>
                </button>
                <?php else: ?>
                <p style="color: #d63638;"><?php esc_html_e('No featured image', 'ai-seo-client'); ?></p>
                <button type="button" class="button button-primary" onclick="sseoGenerateFeatured(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('AI Generate', 'ai-seo-client'); ?>
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Social Images -->
            <div style="margin-bottom: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <strong><?php esc_html_e('Social Media Images:', 'ai-seo-client'); ?></strong>
                
                <div style="margin: 10px 0;">
                    <label>
                        <strong><?php esc_html_e('Open Graph (1200x630):', 'ai-seo-client'); ?></strong>
                    </label>
                    <?php if ($ogImage): ?>
                    <div style="margin: 5px 0;">
                        <img src="<?php echo esc_url($ogImage); ?>" style="max-width: 100%; height: auto;">
                    </div>
                    <?php else: ?>
                    <p style="color: #666; font-size: 12px;"><?php esc_html_e('Not generated', 'ai-seo-client'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="margin: 10px 0;">
                    <label>
                        <strong><?php esc_html_e('Twitter Card (1200x600):', 'ai-seo-client'); ?></strong>
                    </label>
                    <?php if ($twitterImage): ?>
                    <div style="margin: 5px 0;">
                        <img src="<?php echo esc_url($twitterImage); ?>" style="max-width: 100%; height: auto;">
                    </div>
                    <?php else: ?>
                    <p style="color: #666; font-size: 12px;"><?php esc_html_e('Not generated', 'ai-seo-client'); ?></p>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="button" onclick="sseoGenerateSocialImages(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Generate Social Images', 'ai-seo-client'); ?>
                </button>
            </div>
            
            <!-- Image Style -->
            <div style="padding-top: 15px; border-top: 1px solid #ddd;">
                <label>
                    <strong><?php esc_html_e('Image Style:', 'ai-seo-client'); ?></strong>
                </label>
                <select id="image-style-<?php echo $post->ID; ?>" style="width: 100%; margin-top: 5px;">
                    <option value="photorealistic"><?php esc_html_e('Photorealistic', 'ai-seo-client'); ?></option>
                    <option value="illustration"><?php esc_html_e('Illustration', 'ai-seo-client'); ?></option>
                    <option value="abstract"><?php esc_html_e('Abstract', 'ai-seo-client'); ?></option>
                    <option value="minimalist"><?php esc_html_e('Minimalist', 'ai-seo-client'); ?></option>
                    <option value="3d-render"><?php esc_html_e('3D Render', 'ai-seo-client'); ?></option>
                </select>
            </div>
        </div>
        
        <script>
        function sseoGenerateFeatured(postId) {
            const style = jQuery('#image-style-' + postId).val();
            
            if (!confirm('<?php esc_html_e('Generate AI featured image?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_featured_image',
                post_id: postId,
                style: style,
                nonce: '<?php echo wp_create_nonce('sseo_images'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Featured image generated!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error generating image');
                }
            });
        }
        
        function sseoRegenerateFeatured(postId) {
            sseoGenerateFeatured(postId);
        }
        
        function sseoGenerateSocialImages(postId) {
            const style = jQuery('#image-style-' + postId).val();
            
            if (!confirm('<?php esc_html_e('Generate social media images?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_social_images',
                post_id: postId,
                style: style,
                nonce: '<?php echo wp_create_nonce('sseo_images'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Social images generated!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error generating images');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Generate featured image
     */
    public function generateFeaturedImage(int $postId, string $style = 'photorealistic'): ?int
    {
        $post = get_post($postId);
        
        // Generate image prompt from content
        $prompt = $this->generateImagePrompt($post, $style);
        
        // Generate image using AI (DALL-E, Midjourney, Stable Diffusion, etc.)
        $imageUrl = $this->generateImageFromPrompt($prompt);
        
        if (!$imageUrl) {
            return null;
        }
        
        // Download and attach image
        $attachmentId = $this->downloadAndAttachImage($imageUrl, $post->ID, $post->post_title);
        
        if ($attachmentId) {
            set_post_thumbnail($postId, $attachmentId);
        }
        
        return $attachmentId;
    }
    
    /**
     * Generate social images
     */
    public function generateSocialImages(int $postId, string $style = 'photorealistic'): array
    {
        $post = get_post($postId);
        $images = [];
        
        // Generate Open Graph image (1200x630)
        $ogPrompt = $this->generateImagePrompt($post, $style, '1200x630');
        $ogUrl = $this->generateImageFromPrompt($ogPrompt);
        
        if ($ogUrl) {
            $ogAttachment = $this->downloadAndAttachImage($ogUrl, $postId, $post->post_title . ' - OG Image');
            if ($ogAttachment) {
                update_post_meta($postId, '_sseo_ai_og_image', wp_get_attachment_url($ogAttachment));
                $images['og'] = $ogAttachment;
            }
        }
        
        // Generate Twitter Card image (1200x600)
        $twitterPrompt = $this->generateImagePrompt($post, $style, '1200x600');
        $twitterUrl = $this->generateImageFromPrompt($twitterPrompt);
        
        if ($twitterUrl) {
            $twitterAttachment = $this->downloadAndAttachImage($twitterUrl, $postId, $post->post_title . ' - Twitter Card');
            if ($twitterAttachment) {
                update_post_meta($postId, '_sseo_ai_twitter_image', wp_get_attachment_url($twitterAttachment));
                $images['twitter'] = $twitterAttachment;
            }
        }
        
        return $images;
    }
    
    /**
     * Generate image prompt from post content
     */
    private function generateImagePrompt(\WP_Post $post, string $style, string $size = '1024x1024'): string
    {
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = substr($content, 0, 500);
        
        $aiPrompt = "Generate a detailed image prompt for creating a {$style} image about:

Title: {$post->post_title}
Content: {$excerpt}

Create a concise, descriptive prompt (max 100 words) that captures the essence of this content. Focus on visual elements, mood, and composition.";
        
        $imagePrompt = $this->llm->generateText($aiPrompt, ['max_tokens' => 150]);
        
        if (is_wp_error($imagePrompt)) {
            // Fallback to simple prompt
            return "{$post->post_title}, {$style} style, high quality, professional";
        }
        
        return trim($imagePrompt) . ", {$style} style, {$size}, high quality";
    }
    
    /**
     * Generate image from prompt using AI
     */
    private function generateImageFromPrompt(string $prompt): ?string
    {
        // Get image API credentials from SaaS dashboard (stored during license activation)
        $imageApi = get_option('sseo_ai_client_image_api', []);
        $provider = $imageApi['provider'] ?? '';
        $apiKey = $imageApi['key'] ?? '';
        $model = $imageApi['model'] ?? 'dall-e-3';
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: Retrieved credentials - Provider: ' . ($provider ?: 'empty') . ', Key exists: ' . (!empty($apiKey) ? 'yes' : 'no'));
        
        if (empty($provider) || empty($apiKey)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: No API provider or key configured. Please configure Image API in SaaS Dashboard Settings, then re-validate license.');
            return null;
        }
        
        switch ($provider) {
            case 'openai':
                return $this->generateWithOpenAI($prompt, $apiKey, $model);
            
            case 'stability':
                return $this->generateWithStabilityAI($prompt, $apiKey, $model);
            
            default:
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: Unknown provider - ' . $provider);
                return null;
        }
    }
    
    /**
     * Generate image with OpenAI DALL-E
     */
    private function generateWithOpenAI(string $prompt, string $apiKey, string $model): ?string
    {
        $quality = strpos($model, 'hd') !== false ? 'hd' : 'standard';
        
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => $quality,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: OpenAI API error - ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data'][0]['url'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: No image URL in OpenAI response - ' . print_r($body, true));
            return null;
        }
        
        return $body['data'][0]['url'];
    }
    
    /**
     * Generate image with Stability AI
     */
    private function generateWithStabilityAI(string $prompt, string $apiKey, string $model): ?string
    {
        $engine = 'stable-diffusion-xl-1024-v1-0';
        
        $response = wp_remote_post("https://api.stability.ai/v1/generation/{$engine}/text-to-image", [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'text_prompts' => [
                    ['text' => $prompt, 'weight' => 1],
                ],
                'cfg_scale' => 7,
                'height' => 1024,
                'width' => 1024,
                'samples' => 1,
                'steps' => 30,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: Stability AI API error - ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['artifacts'][0]['base64'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('SSEO AI Image: No image data in Stability AI response - ' . print_r($body, true));
            return null;
        }
        
        // Stability AI returns base64, we need to convert to temp file URL
        $base64 = $body['artifacts'][0]['base64'];
        $imageData = base64_decode($base64);
        
        // Save to temp file
        $tempFile = wp_tempnam('sseo-ai-image-');
        file_put_contents($tempFile, $imageData);
        
        return $tempFile; // Return temp file path instead of URL
    }
    
    /**
     * Download and attach image
     */
    private function downloadAndAttachImage(string $imageUrl, int $postId, string $title): ?int
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        $tmp = download_url($imageUrl);
        
        if (is_wp_error($tmp)) {
            return null;
        }
        
        $fileArray = [
            'name' => sanitize_file_name($title) . '.jpg',
            'tmp_name' => $tmp,
        ];
        
        $attachmentId = media_handle_sideload($fileArray, $postId);
        
        if (is_wp_error($attachmentId)) {
            @unlink($tmp);
            return null;
        }
        
        return $attachmentId;
    }
    
    /**
     * Get image stats
     */
    private function getImageStats(): array
    {
        global $wpdb;
        
        $totalGenerated = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sseo_ai_generated_image'
            AND meta_value = '1'
        ");
        
        $featuredImages = $wpdb->get_var("
            SELECT COUNT(DISTINCT meta_value)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_thumbnail_id'
            AND meta_value != ''
        ");
        
        $socialImages = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_sseo_ai_og_image', '_sseo_ai_twitter_image')
            AND meta_value != ''
        ");
        
        $totalPosts = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'
        ");
        
        $missingFeatured = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            WHERE p.post_status = 'publish'
            AND p.post_type = 'post'
            AND pm.meta_value IS NULL
        ");
        
        return [
            'total_generated' => (int)$totalGenerated,
            'featured_images' => (int)$featuredImages,
            'social_images' => (int)$socialImages,
            'missing_featured' => (int)$missingFeatured,
        ];
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxGenerateFeaturedImage(): void
    {
        check_ajax_referer('sseo_images', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        $style = sanitize_text_field($_POST['style'] ?? 'photorealistic');
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $attachmentId = $this->generateFeaturedImage($postId, $style);
        
        if (!$attachmentId) {
            wp_send_json_error(['message' => 'Failed to generate image']);
        }
        
        update_post_meta($postId, '_sseo_ai_generated_image', '1');
        
        wp_send_json_success(['attachment_id' => $attachmentId]);
    }
    
    public function ajaxGenerateSocialImages(): void
    {
        check_ajax_referer('sseo_images', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        $style = sanitize_text_field($_POST['style'] ?? 'photorealistic');
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $images = $this->generateSocialImages($postId, $style);
        
        if (empty($images)) {
            wp_send_json_error(['message' => 'Failed to generate social images']);
        }
        
        wp_send_json_success(['images' => $images]);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/images/generate/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateImage'],
            'permission_callback' => function() {
                return current_user_can('upload_files');
            },
        ]);
    }
    
    public function restGenerateImage(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $style = $request->get_param('style') ?? 'photorealistic';
        
        $attachmentId = $this->generateFeaturedImage($postId, $style);
        
        if (!$attachmentId) {
            return ['error' => 'Failed to generate image'];
        }
        
        return ['attachment_id' => $attachmentId];
    }
}

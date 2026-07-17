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
        // Meta box moved to PostMetaBox tabbed container
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
            
            <!-- Image Prompt Context -->
            <div style="padding-top: 15px; border-top: 1px solid #ddd;">
                <label>
                    <strong><?php esc_html_e('Image Context / Prompt:', 'ai-seo-client'); ?></strong>
                </label>
                <textarea id="image-context-<?php echo $post->ID; ?>" rows="3" style="width: 100%; margin-top: 5px;" placeholder="<?php esc_attr_e('Describe what the image should show. Leave empty to auto-generate from content.', 'ai-seo-client'); ?>"></textarea>
            </div>

            <!-- Word Count for Prompt Detail -->
            <div style="padding-top: 10px;">
                <label>
                    <strong><?php esc_html_e('Prompt Word Count:', 'ai-seo-client'); ?></strong>
                </label>
                <input type="number" id="image-word-count-<?php echo $post->ID; ?>" value="100" min="20" max="500" style="width: 100%; margin-top: 5px;">
                <p class="description" style="font-size: 11px; color: #666; margin: 3px 0 0;"><?php esc_html_e('Target length of the generated image prompt', 'ai-seo-client'); ?></p>
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
            const context = jQuery('#image-context-' + postId).val();
            const wordCount = jQuery('#image-word-count-' + postId).val();

            if (!confirm('<?php esc_html_e('Generate AI featured image?', 'ai-seo-client'); ?>')) {
                return;
            }

            if (typeof sseoShowLoader === 'function') sseoShowLoader();
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_featured_image',
                post_id: postId,
                style: style,
                context: context,
                word_count: wordCount,
                nonce: '<?php echo wp_create_nonce('sseo_images'); ?>'
            }, function(response) {
                if (typeof sseoHideLoader === 'function') sseoHideLoader();
                if (response.success) {
                    alert('<?php esc_html_e('Featured image generated!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error generating image');
                }
            }).fail(function() {
                if (typeof sseoHideLoader === 'function') sseoHideLoader();
                alert('<?php esc_html_e('Request failed. Please try again.', 'ai-seo-client'); ?>');
            });
        }

        function sseoRegenerateFeatured(postId) {
            sseoGenerateFeatured(postId);
        }

        function sseoGenerateSocialImages(postId) {
            const style = jQuery('#image-style-' + postId).val();
            const context = jQuery('#image-context-' + postId).val();
            const wordCount = jQuery('#image-word-count-' + postId).val();

            if (!confirm('<?php esc_html_e('Generate social media images?', 'ai-seo-client'); ?>')) {
                return;
            }

            if (typeof sseoShowLoader === 'function') sseoShowLoader();
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_social_images',
                post_id: postId,
                style: style,
                context: context,
                word_count: wordCount,
                nonce: '<?php echo wp_create_nonce('sseo_images'); ?>'
            }, function(response) {
                if (typeof sseoHideLoader === 'function') sseoHideLoader();
                if (response.success) {
                    alert('<?php esc_html_e('Social images generated!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error generating images');
                }
            }).fail(function() {
                if (typeof sseoHideLoader === 'function') sseoHideLoader();
                alert('<?php esc_html_e('Request failed. Please try again.', 'ai-seo-client'); ?>');
            });
        }
        </script>
        <?php
    }
    
    /**
     * Generate featured image
     */
    public function generateFeaturedImage(int $postId, string $style = 'photorealistic', string $context = '', int $wordCount = 100): ?int
    {
        $post = get_post($postId);
        
        // Generate image prompt from content
        $prompt = $this->generateImagePrompt($post, $style, '1024x1024', $context, $wordCount);
        
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
    public function generateSocialImages(int $postId, string $style = 'photorealistic', string $context = '', int $wordCount = 100): array
    {
        $post = get_post($postId);
        $images = [];
        
        // Generate Open Graph image (1200x630)
        $ogPrompt = $this->generateImagePrompt($post, $style, '1200x630', $context, $wordCount);
        $ogUrl = $this->generateImageFromPrompt($ogPrompt);
        
        if ($ogUrl) {
            $ogAttachment = $this->downloadAndAttachImage($ogUrl, $postId, $post->post_title . ' - OG Image');
            if ($ogAttachment) {
                update_post_meta($postId, '_sseo_ai_og_image', wp_get_attachment_url($ogAttachment));
                $images['og'] = $ogAttachment;
            }
        }
        
        // Generate Twitter Card image (1200x600)
        $twitterPrompt = $this->generateImagePrompt($post, $style, '1200x600', $context, $wordCount);
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
    private function generateImagePrompt(\WP_Post $post, string $style, string $size = '1024x1024', string $context = '', int $wordCount = 100): string
    {
        if (!empty($context)) {
            $aiPrompt = "Generate a detailed image prompt for creating a {$style} image.

User context: {$context}
Target prompt length: approximately {$wordCount} words.

Create a concise, descriptive prompt that captures the essence. Focus on visual elements, mood, and composition.";
        } else {
            $content = wp_strip_all_tags($post->post_content);
            $excerpt = substr($content, 0, 500);
            
            $aiPrompt = "Generate a detailed image prompt for creating a {$style} image about:

Title: {$post->post_title}
Content: {$excerpt}

Create a concise, descriptive prompt (max {$wordCount} words) that captures the essence of this content. Focus on visual elements, mood, and composition.";
        }
        
        $imagePrompt = $this->llm->generateText($aiPrompt, [
            'max_tokens' => max(150, (int)($wordCount * 2)),
            'track_extra' => [
                'endpoint' => 'image.prompt',
                'post_id' => $post->ID,
                'context' => substr($context, 0, 100) ?: 'auto',
            ],
        ]);
        
        if (is_wp_error($imagePrompt)) {
            // Fallback to simple prompt
            return (!empty($context) ? $context : $post->post_title) . ", {$style} style, {$size}, high quality";
        }
        
        return trim($imagePrompt) . ", {$style} style, {$size}, high quality";
    }
    
    /**
     * Generate image from prompt using AI
     */
    public function generateImageFromPrompt(string $prompt): ?string
    {
        // Get image API credentials from SaaS dashboard (stored during license activation)
        $imageApi = get_option('sseo_ai_client_image_api', []);
        $primaryProvider = $this->resolveImageProvider($imageApi['provider'] ?? '', $imageApi['key'] ?? '', $imageApi['model'] ?? 'dall-e-3');

        // Fallback order: OpenRouter -> OpenAI -> Stability AI. Primary provider is tried first.
        $fallbackOrder = ['openrouter', 'openai', 'stability'];
        $providers = [];
        if (!empty($primaryProvider)) {
            $providers[] = $primaryProvider;
        }
        foreach ($fallbackOrder as $provider) {
            if ($provider !== $primaryProvider && !in_array($provider, $providers, true)) {
                $providers[] = $provider;
            }
        }

        $lastError = null;
        foreach ($providers as $provider) {
            $apiKey = $this->getImageProviderKey($provider, $imageApi);
            $model = $this->mapModelForProvider($provider, $imageApi['model'] ?? 'dall-e-3');

            if (empty($apiKey)) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: No API key for provider ' . $provider);
                continue;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Fyndable Image: Trying provider ' . $provider . ' with model ' . $model);
            }

            switch ($provider) {
                case 'openai':
                    $result = $this->generateWithOpenAI($prompt, $apiKey, $model);
                    break;
                case 'openrouter':
                    $result = $this->generateWithOpenRouter($prompt, $apiKey, $model);
                    break;
                case 'stability':
                    $result = $this->generateWithStabilityAI($prompt, $apiKey, $model);
                    break;
                case 'openart':
                    $result = $this->generateWithOpenArt($prompt, $apiKey, $model);
                    break;
                default:
                    $result = null;
            }

            if (!empty($result)) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: Provider ' . $provider . ' succeeded');
                return $result;
            }

            $lastError = $provider;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Fyndable Image: All image providers failed. Last attempted: ' . ($lastError ?? 'none'));
        }

        return null;
    }

    /**
     * Pick the right API key for a provider from the stored image API config.
     */
    private function getImageProviderKey(string $provider, array $imageApi): ?string
    {
        $specific = $imageApi[$provider . '_key'] ?? '';
        if (!empty($specific)) {
            return $specific;
        }

        $generic = $imageApi['key'] ?? '';
        if ($this->keyMatchesProvider($provider, $generic, $imageApi['model'] ?? '')) {
            return $generic;
        }

        return null;
    }

    /**
     * Detect whether a given API key belongs to a specific image provider.
     */
    private function keyMatchesProvider(string $provider, string $apiKey, string $model): bool
    {
        if (empty($apiKey)) {
            return false;
        }

        switch ($provider) {
            case 'openrouter':
                return stripos($apiKey, 'sk-or-') === 0 || stripos($model, 'openai/') === 0;
            case 'openai':
                return (stripos($apiKey, 'sk-') === 0 && stripos($apiKey, 'sk-or-') !== 0) && stripos($model, 'openai/') !== 0;
            case 'stability':
            case 'openart':
                return !empty($apiKey);
            default:
                return false;
        }
    }

    /**
     * Map a generic / OpenRouter model ID to a provider-specific image model.
     */
    private function mapModelForProvider(string $provider, string $model): string
    {
        if ($provider === 'openrouter') {
            return $this->resolveOpenRouterImageModel($model)[0];
        }

        if ($provider === 'openai') {
            $model = trim($model);
            if (stripos($model, 'openai/') === 0) {
                $base = substr($model, 7);
                if (stripos($base, 'dall-e') === 0) {
                    return $base;
                }
                if (stripos($base, 'gpt-image') === 0) {
                    return 'dall-e-3';
                }
                return 'dall-e-3';
            }
            if (in_array(strtolower($model), ['dall-e-3', 'dall-e-2', 'dall-e-3-hd', 'gpt-image-1'], true)) {
                return $model;
            }
            return 'dall-e-3';
        }

        if ($provider === 'stability') {
            return 'stable-diffusion-xl';
        }

        return $model;
    }

    /**
     * Resolve the image API provider from key/model hints.
     *
     * Falls back to the general AI provider settings when the stored image
     * provider is empty or mismatched with the provided key.
     */
    private function resolveImageProvider(string $provider, string $apiKey, string $model): string
    {
        // Key-based hints
        if (stripos($apiKey, 'sk-or-') === 0) {
            return 'openrouter';
        }
        if (stripos($apiKey, 'sk-') === 0 && stripos($apiKey, 'sk-or-') !== 0) {
            return 'openai';
        }

        // Model-based hints
        if (stripos($model, 'openai/') === 0) {
            return 'openrouter';
        }
        if (stripos($model, 'flux') !== false) {
            return 'openart';
        }

        if (!empty($provider)) {
            return $provider;
        }

        // Fall back to the general AI provider configured for the client
        $modelRouting = get_option('sseo_ai_client_model_routing', []);
        $defaultModel = is_array($modelRouting) ? ($modelRouting['content_generation'] ?? $model) : $model;
        if (stripos($defaultModel, 'openai/') === 0) {
            return 'openrouter';
        }

        return 'openrouter';
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
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => $quality,
            ]),
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: OpenAI API error - ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['data'][0]['url'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: No image URL in OpenAI response - ' . print_r($body, true));
            return null;
        }
        
        return $body['data'][0]['url'];
    }
    
    /**
     * Generate image with OpenRouter (unified Image API)
     */
    private function generateWithOpenRouter(string $prompt, string $apiKey, string $model): ?string
    {
        [$model, $quality] = $this->resolveOpenRouterImageModel($model);

        $body = [
            'model'   => $model,
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => '1024x1024',
        ];

        if (!empty($quality) && $quality !== 'auto') {
            $body['quality'] = $quality;
        }

        $response = wp_remote_post('https://openrouter.ai/api/v1/images', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo('name'),
            ],
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: OpenRouter API error - ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: OpenRouter response - ' . print_r($body, true));

        $imageUrl = $body['data'][0]['url'] ?? '';
        $b64 = $body['data'][0]['b64_json'] ?? '';

        if (!empty($imageUrl)) {
            return $imageUrl;
        }

        if (!empty($b64)) {
            $imageData = base64_decode($b64);
            if ($imageData === false) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: Failed to decode OpenRouter base64 image');
                return null;
            }

            $mediaType = $body['data'][0]['media_type'] ?? 'image/png';
            $extension = 'png';
            if (stripos($mediaType, 'svg') !== false) {
                $extension = 'svg';
            } elseif (stripos($mediaType, 'jpeg') !== false || stripos($mediaType, 'jpg') !== false) {
                $extension = 'jpg';
            } elseif (stripos($mediaType, 'webp') !== false) {
                $extension = 'webp';
            }

            $tempFile = wp_tempnam('sseo-ai-image-' . time() . '.' . $extension);
            file_put_contents($tempFile, $imageData);
            return $tempFile;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: No image URL or data in OpenRouter response - ' . print_r($body, true));
        return null;
    }

    /**
     * Resolve a model string to a valid OpenRouter image model ID and quality.
     */
    private function resolveOpenRouterImageModel(string $model): array
    {
        $model = trim($model);
        $quality = 'auto';

        $validModels = [
            'openai/gpt-image-2',
            'openai/gpt-image-1',
            'openai/gpt-image-1-mini',
            'openai/gpt-5-image',
            'openai/gpt-5-image-mini',
            'openai/gpt-5.4-image-2',
            'google/gemini-3.1-flash-image',
            'google/gemini-3.1-flash-lite-image',
            'google/gemini-3.1-flash-image-preview',
            'google/gemini-3-pro-image',
            'google/gemini-3-pro-image-preview',
            'google/gemini-2.5-flash-image',
            'google/gemini-2.5-flash-image-preview',
            'bytedance-seed/seedream-4.5',
            'black-forest-labs/flux.2-pro',
            'black-forest-labs/flux.2-flex',
            'black-forest-labs/flux.2-max',
            'black-forest-labs/flux.2-klein-4b',
            'sourceful/riverflow-v2.5-pro',
            'sourceful/riverflow-v2.5-fast',
            'sourceful/riverflow-v2-pro',
            'sourceful/riverflow-v2-fast',
            'sourceful/riverflow-v2-max-preview',
            'sourceful/riverflow-v2-standard-preview',
            'sourceful/riverflow-v2-fast-preview',
            'microsoft/mai-image-2.5',
            'x-ai/grok-imagine-image-quality',
            'recraft/recraft-v4.1-pro',
            'recraft/recraft-v4.1',
            'recraft/recraft-v4.1-pro-vector',
            'recraft/recraft-v4.1-vector',
            'recraft/recraft-v4.1-utility-pro',
            'recraft/recraft-v4.1-utility',
            'recraft/recraft-v4-pro',
            'recraft/recraft-v4',
            'recraft/recraft-v3',
            'openrouter/auto',
        ];

        if (in_array(strtolower($model), array_map('strtolower', $validModels), true)) {
            return [$model, $quality];
        }

        if (stripos($model, 'dall-e-3') === 0 || stripos($model, 'openai/dall-e-3') !== false) {
            $quality = (stripos($model, 'hd') !== false) ? 'high' : 'auto';
            return ['openai/gpt-image-2', $quality];
        }

        if (stripos($model, 'flux') !== false) {
            if (stripos($model, 'schnell') !== false || stripos($model, 'fast') !== false || stripos($model, 'klein') !== false) {
                return ['black-forest-labs/flux.2-klein-4b', $quality];
            }
            return ['black-forest-labs/flux.2-pro', $quality];
        }

        if (stripos($model, 'stable') !== false || stripos($model, 'sdxl') !== false) {
            return ['black-forest-labs/flux.2-pro', $quality];
        }

        if (stripos($model, 'gemini') !== false) {
            return ['google/gemini-3.1-flash-image', $quality];
        }

        if (stripos($model, 'seedream') !== false) {
            return ['bytedance-seed/seedream-4.5', $quality];
        }

        return ['openai/gpt-image-2', $quality];
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
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: Stability AI API error - ' . $response->get_error_message());
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['artifacts'][0]['base64'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: No image data in Stability AI response - ' . print_r($body, true));
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
     * Generate image with OpenArt (Flux models)
     */
    private function generateWithOpenArt(string $prompt, string $apiKey, string $model): ?string
    {
        $model = $model ?: 'flux-1-schnell';

        $response = wp_remote_post('https://api.openart.ai/api/v1/generation', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'          => $model,
                'prompt'         => $prompt,
                'width'          => 1024,
                'height'         => 1024,
                'num_images'     => 1,
                'guidance_scale' => 7,
            ]),
            'timeout' => 120,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: OpenArt API error - ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        $imageUrl = $body['data'][0]['url'] ?? $body['images'][0]['url'] ?? $body['url'] ?? '';

        if (empty($imageUrl)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: No image URL in OpenArt response - ' . print_r($body, true));
            return null;
        }

        return $imageUrl;
    }

    /**
     * Download and attach image
     */
    private function downloadAndAttachImage(string $imageUrl, int $postId, string $title): ?int
    {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        
        // Some providers return a local temp file path (e.g. OpenRouter base64)
        if (file_exists($imageUrl) && is_readable($imageUrl)) {
            $tmp = $imageUrl;
        } else {
            $tmp = download_url($imageUrl);
            if (is_wp_error($tmp)) {
                if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: download_url error - ' . $tmp->get_error_message());
                return null;
            }
        }

        // Detect the real MIME type from the file contents. wp_tempnam() always
        // appends .tmp, so pathinfo() on the temp path cannot be trusted.
        $mime = function_exists('wp_get_image_mime') ? wp_get_image_mime($tmp) : false;
        if (!$mime) {
            $info = @getimagesize($tmp);
            $mime = $info['mime'] ?? false;
        }
        if (!$mime) {
            $firstBytes = @file_get_contents($tmp, false, null, 0, 200);
            if ($firstBytes !== false && (stripos($firstBytes, '<?xml') !== false || stripos($firstBytes, '<svg') !== false)) {
                $mime = 'image/svg+xml';
            }
        }

        $extensionMap = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
        ];

        $extension = !empty($mime) ? ($extensionMap[$mime] ?? '') : '';
        if (!$extension) {
            $extension = pathinfo($tmp, PATHINFO_EXTENSION);
            if (!$extension || $extension === 'tmp') {
                $extension = 'png';
            }
        }

        $safeTitle = sanitize_file_name($title) ?: 'image';
        $fileArray = [
            'name' => $safeTitle . '.' . $extension,
            'tmp_name' => $tmp,
            'type' => $mime ?: 'image/png',
            'error' => 0,
            'size' => filesize($tmp) ?: 0,
        ];
        
        $attachmentId = media_handle_sideload($fileArray, $postId);
        
        if (is_wp_error($attachmentId)) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Image: media_handle_sideload error - ' . $attachmentId->get_error_message());
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
        $context = sanitize_text_field($_POST['context'] ?? '');
        $wordCount = (int)($_POST['word_count'] ?? 100);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $attachmentId = $this->generateFeaturedImage($postId, $style, $context, $wordCount);
        
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
        $context = sanitize_text_field($_POST['context'] ?? '');
        $wordCount = (int)($_POST['word_count'] ?? 100);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $images = $this->generateSocialImages($postId, $style, $context, $wordCount);
        
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

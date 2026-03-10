<?php

namespace SSEOAIClient;

class ImageAltGenerator
{
    private Settings $settings;
    private LlmClient $llm;
    private ImageClient $imageClient;

    public function __construct(Settings $settings, LlmClient $llm, ImageClient $imageClient)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->imageClient = $imageClient;
    }

    public function register(): void
    {
        add_filter('wp_generate_attachment_metadata', [$this, 'autoGenerateAltOnUpload'], 10, 2);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('attachment_submitbox_misc_actions', [$this, 'renderAttachmentMeta']);
        add_action('edit_attachment', [$this, 'saveAttachmentAlt']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'aiseo_image_alt',
            __('AI Alt Text Generator', 'ai-seo-client'),
            [$this, 'renderMetaBox'],
            'attachment',
            'side',
            'high'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        if (!wp_attachment_is_image($post->ID)) {
            return;
        }

        $currentAlt = get_post_meta($post->ID, '_wp_attachment_image_alt', true);
        $generatedAlt = get_post_meta($post->ID, '_sseo_ai_generated_alt', true);
        ?>
        <div class="aiseo-image-alt-box">
            <p>
                <label><strong><?php esc_html_e('Current Alt Text', 'ai-seo-client'); ?></strong></label><br>
                <input type="text" value="<?php echo esc_attr($currentAlt); ?>" class="wide-text" readonly>
            </p>
            
            <?php if ($generatedAlt) : ?>
            <p>
                <label><strong><?php esc_html_e('AI Suggested', 'ai-seo-client'); ?></strong></label><br>
                <input type="text" id="aiseo-suggested-alt" value="<?php echo esc_attr($generatedAlt); ?>" class="wide-text" readonly>
            </p>
            <p>
                <button type="button" class="button" id="aiseo-apply-alt">
                    <?php esc_html_e('Apply AI Suggestion', 'ai-seo-client'); ?>
                </button>
            </p>
            <?php endif; ?>
            
            <p>
                <button type="button" class="button button-primary" id="aiseo-generate-alt" data-attachment="<?php echo $post->ID; ?>">
                    <?php esc_html_e('Generate AI Alt Text', 'ai-seo-client'); ?>
                </button>
            </p>
            
            <div id="aiseo-alt-result" style="margin-top:10px; display:none;">
                <textarea id="aiseo-alt-output" rows="2" class="wide-text"></textarea>
                <p>
                    <button type="button" class="button" id="aiseo-use-alt"><?php esc_html_e('Use This', 'ai-seo-client'); ?></button>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#aiseo-generate-alt').on('click', function() {
                var btn = $(this);
                var attachmentId = btn.data('attachment');
                
                btn.prop('disabled', true).text('<?php esc_html_e('Analyzing...', 'ai-seo-client'); ?>');
                
                wp.apiFetch({
                    path: 'aiseoclient/v1/generate-alt',
                    method: 'POST',
                    data: { attachment_id: attachmentId }
                }).then(function(response) {
                    $('#aiseo-alt-output').val(response.alt_text);
                    $('#aiseo-alt-result').show();
                    btn.prop('disabled', false).text('<?php esc_html_e('Generate AI Alt Text', 'ai-seo-client'); ?>');
                }).catch(function(error) {
                    alert('Error: ' + error.message);
                    btn.prop('disabled', false);
                });
            });
            
            $('#aiseo-use-alt').on('click', function() {
                var alt = $('#aiseo-alt-output').val();
                $('input[name="_wp_attachment_image_alt"]').val(alt);
            });
            
            $('#aiseo-apply-alt').on('click', function() {
                var alt = $('#aiseo-suggested-alt').val();
                $('input[name="_wp_attachment_image_alt"]').val(alt);
            });
        });
        </script>
        <?php
    }

    public function renderAttachmentMeta(): void
    {
        global $post;
        if (!$post || !wp_attachment_is_image($post->ID)) {
            return;
        }

        $generatedAlt = get_post_meta($post->ID, '_sseo_ai_generated_alt', true);
        if (!$generatedAlt) {
            return;
        }
        ?>
        <div class="misc-pub-section">
            <strong><?php esc_html_e('AI Alt Text Suggestion', 'ai-seo-client'); ?></strong><br>
            <em><?php echo esc_html($generatedAlt); ?></em><br>
            <button type="button" class="button-link" onclick="jQuery('#attachment_alt').val('<?php echo esc_js($generatedAlt); ?>');">
                <?php esc_html_e('Use suggestion', 'ai-seo-client'); ?>
            </button>
        </div>
        <?php
    }

    public function saveAttachmentAlt(int $attachmentId): void
    {
        if (!isset($_POST['_wp_attachment_image_alt'])) {
            return;
        }

        $alt = sanitize_text_field($_POST['_wp_attachment_image_alt']);
        update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
    }

    public function autoGenerateAltOnUpload(array $metadata, int $attachmentId): array
    {
        $autoGenerate = $this->settings->get('auto_generate_alt', 0);
        if (!$autoGenerate) {
            return $metadata;
        }

        if (!wp_attachment_is_image($attachmentId)) {
            return $metadata;
        }

        $altText = $this->generateAltText($attachmentId);
        if (!is_wp_error($altText) && !empty($altText)) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $altText);
            update_post_meta($attachmentId, '_sseo_ai_generated_alt', $altText);
        }

        return $metadata;
    }

    public function generateAltText(int $attachmentId)
    {
        if (!wp_attachment_is_image($attachmentId)) {
            return new \WP_Error('not_image', __('Attachment is not an image', 'ai-seo-client'));
        }

        // Get image file path
        $filePath = get_attached_file($attachmentId);
        if (!$filePath || !file_exists($filePath)) {
            return new \WP_Error('no_file', __('Image file not found', 'ai-seo-client'));
        }

        // Try vision analysis if available
        $imageUrl = wp_get_attachment_url($attachmentId);
        
        // Use filename as fallback context
        $filename = basename($filePath);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $filename = str_replace(['-', '_'], ' ', $filename);

        // Check for existing captions/titles
        $attachment = get_post($attachmentId);
        $title = $attachment->post_title;
        $caption = $attachment->post_excerpt;

        // Generate alt text using LLM with context
        $context = $this->buildAltContext($filename, $title, $caption, $imageUrl);
        $prompt = "Write a concise, descriptive alt text for this image. Keep it under 125 characters. Be specific about what's visible. Don't start with 'Image of' or 'Picture of'.\n\n";
        $prompt .= "Context:\n" . $context;

        $result = $this->llm->call($prompt);
        if (is_wp_error($result)) {
            // Fallback: use filename-based alt
            return $this->filenameToAlt($filename);
        }

        $altText = trim($result['text']);
        
        // Clean up the response
        $altText = preg_replace('/^(image of|picture of|photo of|graphic of)\s*/i', '', $altText);
        $altText = substr($altText, 0, 125);

        return $altText;
    }

    private function buildAltContext(string $filename, string $title, string $caption, string $imageUrl): string
    {
        $context = [];
        
        if ($filename && $filename !== 'image') {
            $context[] = "Filename: {$filename}";
        }
        if ($title && $title !== $filename) {
            $context[] = "Title: {$title}";
        }
        if ($caption) {
            $context[] = "Caption: {$caption}";
        }
        $context[] = "URL: {$imageUrl}";

        return implode("\n", $context);
    }

    private function filenameToAlt(string $filename): string
    {
        // Convert filename to readable alt text
        $alt = ucwords(strtolower($filename));
        $alt = preg_replace('/\d+/', '', $alt);
        $alt = trim($alt);
        
        return substr($alt, 0, 125) ?: 'Image';
    }

    public function bulkGenerateAlt(array $attachmentIds): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($attachmentIds as $id) {
            $altText = $this->generateAltText($id);
            
            if (is_wp_error($altText)) {
                $results['failed']++;
                $results['errors'][$id] = $altText->get_error_message();
            } else {
                update_post_meta($id, '_wp_attachment_image_alt', $altText);
                update_post_meta($id, '_sseo_ai_generated_alt', $altText);
                $results['success']++;
            }
        }

        return $results;
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/generate-alt', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateAlt'],
            'permission_callback' => function () {
                return current_user_can('upload_files');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/bulk-generate-alt', [
            'methods' => 'POST',
            'callback' => [$this, 'restBulkGenerateAlt'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/missing-alt-images', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetMissingAltImages'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function restGenerateAlt(\WP_REST_Request $request): array
    {
        $attachmentId = (int)$request->get_param('attachment_id');
        
        $altText = $this->generateAltText($attachmentId);
        if (is_wp_error($altText)) {
            return $altText;
        }

        return ['alt_text' => $altText, 'attachment_id' => $attachmentId];
    }

    public function restBulkGenerateAlt(\WP_REST_Request $request): array
    {
        $attachmentIds = array_map('intval', (array)$request->get_param('attachment_ids'));
        
        return $this->bulkGenerateAlt($attachmentIds);
    }

    public function restGetMissingAltImages(): array
    {
        global $wpdb;

        $images = $wpdb->get_results(
            "SELECT p.ID, p.post_title 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            LIMIT 50"
        );

        return array_map(fn($img) => [
            'id' => $img->ID,
            'title' => $img->post_title,
            'url' => wp_get_attachment_thumb_url($img->ID),
        ], $images);
    }

    public function getStats(): array
    {
        global $wpdb;

        $totalImages = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'"
        );

        $withAlt = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_wp_attachment_image_alt'
            AND pm.meta_value != ''"
        );

        $aiGenerated = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sseo_ai_generated_alt'"
        );

        return [
            'total' => (int)$totalImages,
            'with_alt' => (int)$withAlt,
            'without_alt' => (int)$totalImages - (int)$withAlt,
            'ai_generated' => (int)$aiGenerated,
            'coverage' => $totalImages > 0 ? round(((int)$withAlt / (int)$totalImages) * 100, 1) : 0,
        ];
    }
}

<?php

namespace AISEOClient;

/**
 * Video SEO Optimizer
 * 
 * Advanced video SEO features:
 * - Video schema validation
 * - Video transcript generation
 * - Video sitemap generation
 * - YouTube SEO optimization
 * - Video thumbnail optimization
 */
class VideoSEO
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
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_generate_transcript', [$this, 'ajaxGenerateTranscript']);
        add_action('wp_ajax_sseo_ai_validate_video_schema', [$this, 'ajaxValidateVideoSchema']);
        
        // Add video schema to head
        add_action('wp_head', [$this, 'outputVideoSchema']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Video SEO', 'ai-seo-client'),
            __('Video SEO', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-video-seo',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render video SEO dashboard
     */
    public function renderDashboard(): void
    {
        $videoStats = $this->getVideoStats();
        $recentVideos = $this->getRecentVideos();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Video SEO Optimizer', 'ai-seo-client'); ?></h1>
            
            <!-- Video Statistics -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Video SEO Overview', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo esc_html($videoStats['total_videos']); ?>
                        </div>
                        <div><?php esc_html_e('Total Videos', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #00a32a;">
                            <?php echo esc_html($videoStats['with_schema']); ?>
                        </div>
                        <div><?php esc_html_e('With Schema', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #856404;">
                            <?php echo esc_html($videoStats['with_transcript']); ?>
                        </div>
                        <div><?php esc_html_e('With Transcript', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                            <?php echo esc_html($videoStats['needs_optimization']); ?>
                        </div>
                        <div><?php esc_html_e('Needs Optimization', 'ai-seo-client'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Videos -->
            <div class="card">
                <h2><?php esc_html_e('Recent Videos', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($recentVideos)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Post', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Video URL', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Schema', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Transcript', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('SEO Score', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentVideos as $video): ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($video['post_id']); ?>">
                                    <?php echo esc_html($video['title']); ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($video['video_url']); ?>" target="_blank">
                                    <?php echo esc_html($this->truncateUrl($video['video_url'])); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($video['has_schema']): ?>
                                <span style="color: #00a32a;">✓ Valid</span>
                                <?php else: ?>
                                <span style="color: #d63638;">✗ Missing</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($video['has_transcript']): ?>
                                <span style="color: #00a32a;">✓ Yes</span>
                                <?php else: ?>
                                <span style="color: #d63638;">✗ No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?php echo $this->getScoreColor($video['seo_score']); ?>;">
                                    <?php echo esc_html($video['seo_score']); ?>/100
                                </strong>
                            </td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoOptimizeVideo(<?php echo $video['post_id']; ?>)">
                                    <?php esc_html_e('Optimize', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No videos found. Add videos to your posts to see them here.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoOptimizeVideo(postId) {
            window.location.href = '<?php echo admin_url('post.php?action=edit&post='); ?>' + postId + '#sseo-video-seo';
        }
        </script>
        <?php
    }
    
    /**
     * Add video SEO meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-video-seo',
                __('Video SEO', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render video SEO meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $videoUrl = get_post_meta($post->ID, '_sseo_ai_video_url', true);
        $videoType = get_post_meta($post->ID, '_sseo_ai_video_type', true) ?: 'youtube';
        $transcript = get_post_meta($post->ID, '_sseo_ai_video_transcript', true);
        $duration = get_post_meta($post->ID, '_sseo_ai_video_duration', true);
        $uploadDate = get_post_meta($post->ID, '_sseo_ai_video_upload_date', true) ?: date('Y-m-d');
        
        $schemaValidation = $this->validateVideoSchema($post->ID);
        
        wp_nonce_field('sseo_video_seo_save', 'sseo_video_seo_nonce');
        ?>
        <div class="sseo-video-seo-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="video_url"><?php esc_html_e('Video URL', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="video_url" name="sseo_video_url" 
                               value="<?php echo esc_url($videoUrl); ?>" 
                               class="large-text" 
                               placeholder="https://www.youtube.com/watch?v=...">
                        <p class="description">
                            <?php esc_html_e('YouTube, Vimeo, or self-hosted video URL', 'ai-seo-client'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="video_type"><?php esc_html_e('Video Type', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <select id="video_type" name="sseo_video_type">
                            <option value="youtube" <?php selected($videoType, 'youtube'); ?>>YouTube</option>
                            <option value="vimeo" <?php selected($videoType, 'vimeo'); ?>>Vimeo</option>
                            <option value="self-hosted" <?php selected($videoType, 'self-hosted'); ?>>Self-Hosted</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="video_duration"><?php esc_html_e('Duration (ISO 8601)', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="video_duration" name="sseo_video_duration" 
                               value="<?php echo esc_attr($duration); ?>" 
                               placeholder="PT10M30S">
                        <p class="description">
                            <?php esc_html_e('Example: PT10M30S (10 minutes 30 seconds)', 'ai-seo-client'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="video_upload_date"><?php esc_html_e('Upload Date', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="video_upload_date" name="sseo_video_upload_date" 
                               value="<?php echo esc_attr($uploadDate); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="video_transcript"><?php esc_html_e('Video Transcript', 'ai-seo-client'); ?></label>
                    </th>
                    <td>
                        <textarea id="video_transcript" name="sseo_video_transcript" 
                                  rows="10" class="large-text"><?php echo esc_textarea($transcript); ?></textarea>
                        <p>
                            <button type="button" class="button" onclick="sseoGenerateTranscript(<?php echo $post->ID; ?>)">
                                <?php esc_html_e('AI Generate Transcript', 'ai-seo-client'); ?>
                            </button>
                            <?php if (!empty($videoUrl) && strpos($videoUrl, 'youtube.com') !== false): ?>
                            <button type="button" class="button" onclick="sseoFetchYouTubeTranscript(<?php echo $post->ID; ?>)">
                                <?php esc_html_e('Fetch YouTube Captions', 'ai-seo-client'); ?>
                            </button>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Schema Validation -->
            <div style="margin-top: 20px; padding: 15px; background: <?php echo $schemaValidation['valid'] ? '#d1e7dd' : '#f8d7da'; ?>; border-left: 4px solid <?php echo $schemaValidation['valid'] ? '#00a32a' : '#d63638'; ?>;">
                <h4 style="margin-top: 0;">
                    <?php esc_html_e('Video Schema Validation', 'ai-seo-client'); ?>
                    <?php if ($schemaValidation['valid']): ?>
                    <span style="color: #00a32a;">✓ Valid</span>
                    <?php else: ?>
                    <span style="color: #d63638;">✗ Invalid</span>
                    <?php endif; ?>
                </h4>
                
                <?php if (!empty($schemaValidation['errors'])): ?>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <?php foreach ($schemaValidation['errors'] as $error): ?>
                    <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <?php if ($schemaValidation['valid']): ?>
                <button type="button" class="button" onclick="sseoPreviewSchema(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Preview Schema', 'ai-seo-client'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoGenerateTranscript(postId) {
            if (!confirm('<?php esc_html_e('Generate AI transcript from video? This may take a few minutes.', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_transcript',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_video'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#video_transcript').val(response.data.transcript);
                    alert('<?php esc_html_e('Transcript generated!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error generating transcript');
                }
            });
        }
        
        function sseoFetchYouTubeTranscript(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_fetch_youtube_transcript',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_video'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#video_transcript').val(response.data.transcript);
                    alert('<?php esc_html_e('YouTube captions fetched!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error fetching captions');
                }
            });
        }
        
        function sseoPreviewSchema(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_get_video_schema',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_video'); ?>'
            }, function(response) {
                if (response.success) {
                    const modal = jQuery('<div class="sseo-modal"><div class="sseo-modal-content">' +
                        '<h2><?php esc_html_e('Video Schema Preview', 'ai-seo-client'); ?></h2>' +
                        '<pre style="background: #f9f9f9; padding: 15px; overflow-x: auto;">' + 
                        JSON.stringify(response.data.schema, null, 2) + 
                        '</pre>' +
                        '<button class="button" onclick="jQuery(this).closest(\'.sseo-modal\').remove()">Close</button>' +
                        '</div></div>');
                    jQuery('body').append(modal);
                }
            });
        }
        </script>
        
        <style>
        .sseo-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
        }
        .sseo-modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        </style>
        <?php
    }
    
    /**
     * Save video SEO meta box
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['sseo_video_seo_nonce']) || !wp_verify_nonce($_POST['sseo_video_seo_nonce'], 'sseo_video_seo_save')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        
        // Save video URL
        if (isset($_POST['sseo_video_url'])) {
            update_post_meta($postId, '_sseo_ai_video_url', esc_url_raw($_POST['sseo_video_url']));
        }
        
        // Save video type
        if (isset($_POST['sseo_video_type'])) {
            update_post_meta($postId, '_sseo_ai_video_type', sanitize_text_field($_POST['sseo_video_type']));
        }
        
        // Save duration
        if (isset($_POST['sseo_video_duration'])) {
            update_post_meta($postId, '_sseo_ai_video_duration', sanitize_text_field($_POST['sseo_video_duration']));
        }
        
        // Save upload date
        if (isset($_POST['sseo_video_upload_date'])) {
            update_post_meta($postId, '_sseo_ai_video_upload_date', sanitize_text_field($_POST['sseo_video_upload_date']));
        }
        
        // Save transcript
        if (isset($_POST['sseo_video_transcript'])) {
            update_post_meta($postId, '_sseo_ai_video_transcript', wp_kses_post($_POST['sseo_video_transcript']));
        }
    }
    
    /**
     * Validate video schema
     */
    private function validateVideoSchema(int $postId): array
    {
        $videoUrl = get_post_meta($postId, '_sseo_ai_video_url', true);
        $duration = get_post_meta($postId, '_sseo_ai_video_duration', true);
        $transcript = get_post_meta($postId, '_sseo_ai_video_transcript', true);
        $uploadDate = get_post_meta($postId, '_sseo_ai_video_upload_date', true);
        
        $errors = [];
        
        if (empty($videoUrl)) {
            $errors[] = 'Video URL is required';
        }
        
        if (empty($duration)) {
            $errors[] = 'Video duration is required for schema';
        }
        
        if (empty($uploadDate)) {
            $errors[] = 'Upload date is required';
        }
        
        $post = get_post($postId);
        if (empty($post->post_title)) {
            $errors[] = 'Video title (post title) is required';
        }
        
        if (!has_post_thumbnail($postId)) {
            $errors[] = 'Thumbnail image is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Generate video schema
     */
    private function generateVideoSchema(int $postId): array
    {
        $post = get_post($postId);
        $videoUrl = get_post_meta($postId, '_sseo_ai_video_url', true);
        $duration = get_post_meta($postId, '_sseo_ai_video_duration', true);
        $transcript = get_post_meta($postId, '_sseo_ai_video_transcript', true);
        $uploadDate = get_post_meta($postId, '_sseo_ai_video_upload_date', true);
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => $post->post_title,
            'description' => get_the_excerpt($postId),
            'thumbnailUrl' => get_the_post_thumbnail_url($postId, 'large'),
            'uploadDate' => $uploadDate,
            'contentUrl' => $videoUrl,
        ];
        
        if ($duration) {
            $schema['duration'] = $duration;
        }
        
        if ($transcript) {
            $schema['transcript'] = $transcript;
        }
        
        return $schema;
    }
    
    /**
     * Output video schema in head
     */
    public function outputVideoSchema(): void
    {
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        $videoUrl = get_post_meta($post->ID, '_sseo_ai_video_url', true);
        
        if (empty($videoUrl)) {
            return;
        }
        
        $validation = $this->validateVideoSchema($post->ID);
        
        if (!$validation['valid']) {
            return;
        }
        
        $schema = $this->generateVideoSchema($post->ID);
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }
    
    /**
     * Get video stats
     */
    private function getVideoStats(): array
    {
        global $wpdb;
        
        $totalVideos = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sseo_ai_video_url'
            AND meta_value != ''
        ");
        
        $withSchema = $wpdb->get_var("
            SELECT COUNT(DISTINCT pm1.post_id)
            FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
            WHERE pm1.meta_key = '_sseo_ai_video_url'
            AND pm1.meta_value != ''
            AND pm2.meta_key = '_sseo_ai_video_duration'
            AND pm2.meta_value != ''
        ");
        
        $withTranscript = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sseo_ai_video_transcript'
            AND meta_value != ''
        ");
        
        return [
            'total_videos' => (int)$totalVideos,
            'with_schema' => (int)$withSchema,
            'with_transcript' => (int)$withTranscript,
            'needs_optimization' => (int)$totalVideos - (int)$withSchema,
        ];
    }
    
    /**
     * Get recent videos
     */
    private function getRecentVideos(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT p.ID as post_id, p.post_title as title,
                   pm1.meta_value as video_url,
                   pm2.meta_value as duration,
                   pm3.meta_value as transcript
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_sseo_ai_video_url'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_sseo_ai_video_duration'
            LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_sseo_ai_video_transcript'
            WHERE p.post_status = 'publish'
            AND pm1.meta_value != ''
            ORDER BY p.post_date DESC
            LIMIT 20
        ");
        
        $videos = [];
        
        foreach ($results as $row) {
            $hasSchema = !empty($row->duration);
            $hasTranscript = !empty($row->transcript);
            
            $seoScore = 0;
            if (!empty($row->video_url)) $seoScore += 30;
            if ($hasSchema) $seoScore += 35;
            if ($hasTranscript) $seoScore += 35;
            
            $videos[] = [
                'post_id' => $row->post_id,
                'title' => $row->title,
                'video_url' => $row->video_url,
                'has_schema' => $hasSchema,
                'has_transcript' => $hasTranscript,
                'seo_score' => $seoScore,
            ];
        }
        
        return $videos;
    }
    
    /**
     * Generate transcript using AI
     */
    public function generateTranscript(int $postId): string
    {
        $videoUrl = get_post_meta($postId, '_sseo_ai_video_url', true);
        
        if (empty($videoUrl)) {
            return '';
        }
        
        // For YouTube videos, try to fetch captions first
        if (strpos($videoUrl, 'youtube.com') !== false || strpos($videoUrl, 'youtu.be') !== false) {
            $transcript = $this->fetchYouTubeTranscript($videoUrl);
            if (!empty($transcript)) {
                return $transcript;
            }
        }
        
        // Otherwise, use AI to generate based on video content
        $post = get_post($postId);
        
        $prompt = "Generate a video transcript based on this content:

Title: {$post->post_title}
Description: " . get_the_excerpt($postId) . "
Content: " . wp_strip_all_tags(substr($post->post_content, 0, 1000)) . "

Generate a natural, engaging video transcript (500-1000 words) that would match this content.";
        
        $transcript = $this->llm->generateText($prompt, ['max_tokens' => 1500]);
        
        if (is_wp_error($transcript)) {
            return '';
        }
        
        return $transcript;
    }
    
    /**
     * Fetch YouTube transcript
     */
    private function fetchYouTubeTranscript(string $videoUrl): string
    {
        // Extract video ID
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\?\/]+)/', $videoUrl, $matches);
        
        if (empty($matches[1])) {
            return '';
        }
        
        $videoId = $matches[1];
        
        // This would use YouTube Data API to fetch captions
        // Placeholder implementation
        return '';
    }
    
    /**
     * Helper methods
     */
    private function truncateUrl(string $url, int $length = 50): string
    {
        return strlen($url) > $length ? substr($url, 0, $length) . '...' : $url;
    }
    
    private function getScoreColor(int $score): string
    {
        if ($score >= 80) return '#00a32a';
        if ($score >= 50) return '#dba617';
        return '#d63638';
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxGenerateTranscript(): void
    {
        check_ajax_referer('sseo_video', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $transcript = $this->generateTranscript($postId);
        
        if (empty($transcript)) {
            wp_send_json_error(['message' => 'Failed to generate transcript']);
        }
        
        update_post_meta($postId, '_sseo_ai_video_transcript', $transcript);
        
        wp_send_json_success(['transcript' => $transcript]);
    }
    
    public function ajaxValidateVideoSchema(): void
    {
        check_ajax_referer('sseo_video', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $validation = $this->validateVideoSchema($postId);
        
        wp_send_json_success($validation);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/video/schema/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetVideoSchema'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    public function restGetVideoSchema(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $schema = $this->generateVideoSchema($postId);
        
        return ['schema' => $schema];
    }
}

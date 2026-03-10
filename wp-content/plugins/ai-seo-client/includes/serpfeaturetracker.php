<?php

namespace AISEOClient;

/**
 * SERP Feature Tracker
 * 
 * Tracks and optimizes for Google SERP features:
 * - Featured Snippets
 * - People Also Ask (PAA)
 * - Knowledge Panel
 * - Image Pack
 * - Video Pack
 * - Rich Snippets
 * - SERP feature checklist per position
 */
class SerpFeatureTracker
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
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('SERP Features', 'ai-seo-client'),
            __('SERP Features', 'ai-seo-client'),
            'manage_options',
            'ai-seo-serp-features',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Add meta box for SERP feature optimization
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-serp-features',
                __('SERP Feature Optimization', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render SERP feature meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $featuredSnippet = get_post_meta($post->ID, '_sseo_ai_featured_snippet', true);
        $paaQuestions = get_post_meta($post->ID, '_sseo_ai_paa_questions', true) ?: [];
        $targetFeatures = get_post_meta($post->ID, '_sseo_ai_target_features', true) ?: [];
        $keyword = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
        
        wp_nonce_field('sseo_serp_save', 'sseo_serp_nonce');
        ?>
        <div class="sseo-serp-features-box">
            <style>
                .sseo-serp-features-box { padding: 10px 0; }
                .sseo-feature-section { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1; }
                .sseo-feature-section h4 { margin-top: 0; }
                .sseo-snippet-preview { padding: 15px; background: white; border: 1px solid #ddd; border-radius: 4px; margin-top: 10px; }
                .sseo-snippet-preview h3 { color: #1a0dab; font-size: 20px; margin: 0 0 5px; }
                .sseo-snippet-preview .url { color: #006621; font-size: 14px; }
                .sseo-snippet-preview .description { color: #545454; font-size: 14px; line-height: 1.4; margin-top: 5px; }
                .sseo-feature-checklist { list-style: none; padding: 0; }
                .sseo-feature-checklist li { padding: 8px; margin: 5px 0; background: white; border-left: 3px solid #ccc; }
                .sseo-feature-checklist li.achieved { border-left-color: #00a32a; }
                .sseo-paa-question { padding: 10px; margin: 5px 0; background: white; border: 1px solid #ddd; }
            </style>
            
            <!-- Target SERP Features -->
            <div class="sseo-feature-section">
                <h4><?php esc_html_e('Target SERP Features', 'ai-seo-client'); ?></h4>
                <p><?php esc_html_e('Select which SERP features you want to optimize for:', 'ai-seo-client'); ?></p>
                
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox" name="sseo_target_features[]" value="featured_snippet" 
                           <?php checked(in_array('featured_snippet', $targetFeatures)); ?>>
                    <?php esc_html_e('Featured Snippet', 'ai-seo-client'); ?>
                </label>
                
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox" name="sseo_target_features[]" value="paa" 
                           <?php checked(in_array('paa', $targetFeatures)); ?>>
                    <?php esc_html_e('People Also Ask (PAA)', 'ai-seo-client'); ?>
                </label>
                
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox" name="sseo_target_features[]" value="knowledge_panel" 
                           <?php checked(in_array('knowledge_panel', $targetFeatures)); ?>>
                    <?php esc_html_e('Knowledge Panel', 'ai-seo-client'); ?>
                </label>
                
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox" name="sseo_target_features[]" value="image_pack" 
                           <?php checked(in_array('image_pack', $targetFeatures)); ?>>
                    <?php esc_html_e('Image Pack', 'ai-seo-client'); ?>
                </label>
                
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox" name="sseo_target_features[]" value="video_pack" 
                           <?php checked(in_array('video_pack', $targetFeatures)); ?>>
                    <?php esc_html_e('Video Pack', 'ai-seo-client'); ?>
                </label>
            </div>
            
            <!-- Featured Snippet Optimizer -->
            <div class="sseo-feature-section">
                <h4><?php esc_html_e('Featured Snippet Optimization', 'ai-seo-client'); ?></h4>
                
                <p>
                    <label><strong><?php esc_html_e('Snippet Type:', 'ai-seo-client'); ?></strong></label><br>
                    <select name="sseo_snippet_type" style="width: 100%; max-width: 300px;">
                        <option value=""><?php esc_html_e('Auto-detect', 'ai-seo-client'); ?></option>
                        <option value="paragraph" <?php selected(get_post_meta($post->ID, '_sseo_ai_snippet_type', true), 'paragraph'); ?>>
                            <?php esc_html_e('Paragraph', 'ai-seo-client'); ?>
                        </option>
                        <option value="list" <?php selected(get_post_meta($post->ID, '_sseo_ai_snippet_type', true), 'list'); ?>>
                            <?php esc_html_e('List', 'ai-seo-client'); ?>
                        </option>
                        <option value="table" <?php selected(get_post_meta($post->ID, '_sseo_ai_snippet_type', true), 'table'); ?>>
                            <?php esc_html_e('Table', 'ai-seo-client'); ?>
                        </option>
                    </select>
                </p>
                
                <p>
                    <label><strong><?php esc_html_e('Featured Snippet Content:', 'ai-seo-client'); ?></strong></label><br>
                    <textarea name="sseo_featured_snippet" rows="4" style="width: 100%;" 
                              placeholder="<?php esc_attr_e('Enter the content optimized for featured snippet (40-60 words)', 'ai-seo-client'); ?>"><?php echo esc_textarea($featuredSnippet); ?></textarea>
                    <small><?php esc_html_e('This should be a concise, direct answer to the search query.', 'ai-seo-client'); ?></small>
                </p>
                
                <button type="button" class="button" onclick="sseoGenerateFeaturedSnippet(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('AI Generate Snippet', 'ai-seo-client'); ?>
                </button>
                
                <?php if ($featuredSnippet): ?>
                <div class="sseo-snippet-preview">
                    <h3><?php echo esc_html(get_the_title($post)); ?></h3>
                    <div class="url"><?php echo esc_html(get_permalink($post)); ?></div>
                    <div class="description"><?php echo esc_html($featuredSnippet); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- People Also Ask -->
            <div class="sseo-feature-section">
                <h4><?php esc_html_e('People Also Ask (PAA) Questions', 'ai-seo-client'); ?></h4>
                
                <p><?php esc_html_e('Add questions and answers that might appear in PAA:', 'ai-seo-client'); ?></p>
                
                <div id="sseo-paa-questions">
                    <?php 
                    if (!empty($paaQuestions)) {
                        foreach ($paaQuestions as $index => $qa) {
                            echo '<div class="sseo-paa-question">';
                            echo '<input type="text" name="sseo_paa_questions[' . $index . '][question]" value="' . esc_attr($qa['question']) . '" placeholder="' . esc_attr__('Question', 'ai-seo-client') . '" style="width: 100%; margin-bottom: 5px;">';
                            echo '<textarea name="sseo_paa_questions[' . $index . '][answer]" rows="2" style="width: 100%;" placeholder="' . esc_attr__('Answer', 'ai-seo-client') . '">' . esc_textarea($qa['answer']) . '</textarea>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
                
                <button type="button" class="button" onclick="sseoAddPAAQuestion()">
                    <?php esc_html_e('Add Question', 'ai-seo-client'); ?>
                </button>
                
                <button type="button" class="button" onclick="sseoGeneratePAAQuestions(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('AI Generate PAA Questions', 'ai-seo-client'); ?>
                </button>
            </div>
            
            <!-- SERP Feature Checklist -->
            <div class="sseo-feature-section">
                <h4><?php esc_html_e('SERP Feature Checklist', 'ai-seo-client'); ?></h4>
                
                <?php
                $checklist = $this->getSerpFeatureChecklist($post);
                ?>
                
                <ul class="sseo-feature-checklist">
                    <?php foreach ($checklist as $item): ?>
                    <li class="<?php echo $item['achieved'] ? 'achieved' : ''; ?>">
                        <span style="margin-right: 10px;">
                            <?php echo $item['achieved'] ? '✓' : '○'; ?>
                        </span>
                        <strong><?php echo esc_html($item['title']); ?></strong>
                        <p style="margin: 5px 0 0 25px; color: #666; font-size: 13px;">
                            <?php echo esc_html($item['description']); ?>
                        </p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <script>
        let paaQuestionIndex = <?php echo count($paaQuestions); ?>;
        
        function sseoAddPAAQuestion() {
            const container = document.getElementById('sseo-paa-questions');
            const div = document.createElement('div');
            div.className = 'sseo-paa-question';
            div.innerHTML = `
                <input type="text" name="sseo_paa_questions[${paaQuestionIndex}][question]" 
                       placeholder="<?php esc_attr_e('Question', 'ai-seo-client'); ?>" 
                       style="width: 100%; margin-bottom: 5px;">
                <textarea name="sseo_paa_questions[${paaQuestionIndex}][answer]" rows="2" 
                          style="width: 100%;" 
                          placeholder="<?php esc_attr_e('Answer', 'ai-seo-client'); ?>"></textarea>
            `;
            container.appendChild(div);
            paaQuestionIndex++;
        }
        
        function sseoGenerateFeaturedSnippet(postId) {
            if (!confirm('<?php esc_html_e('Generate featured snippet using AI?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_snippet',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_serp'); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('[name="sseo_featured_snippet"]').val(response.data.snippet);
                    alert('<?php esc_html_e('Featured snippet generated!', 'ai-seo-client'); ?>');
                } else {
                    alert(response.data.message || 'Error generating snippet');
                }
            });
        }
        
        function sseoGeneratePAAQuestions(postId) {
            if (!confirm('<?php esc_html_e('Generate PAA questions using AI?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_paa',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_serp'); ?>'
            }, function(response) {
                if (response.success) {
                    const container = document.getElementById('sseo-paa-questions');
                    container.innerHTML = '';
                    
                    response.data.questions.forEach((qa, index) => {
                        const div = document.createElement('div');
                        div.className = 'sseo-paa-question';
                        div.innerHTML = `
                            <input type="text" name="sseo_paa_questions[${index}][question]" 
                                   value="${qa.question}" style="width: 100%; margin-bottom: 5px;">
                            <textarea name="sseo_paa_questions[${index}][answer]" rows="2" 
                                      style="width: 100%;">${qa.answer}</textarea>
                        `;
                        container.appendChild(div);
                    });
                    
                    paaQuestionIndex = response.data.questions.length;
                    alert('<?php esc_html_e('PAA questions generated!', 'ai-seo-client'); ?>');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save SERP feature meta box
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['sseo_serp_nonce']) || !wp_verify_nonce($_POST['sseo_serp_nonce'], 'sseo_serp_save')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        
        // Save target features
        $targetFeatures = $_POST['sseo_target_features'] ?? [];
        update_post_meta($postId, '_sseo_ai_target_features', array_map('sanitize_text_field', $targetFeatures));
        
        // Save featured snippet
        if (isset($_POST['sseo_featured_snippet'])) {
            update_post_meta($postId, '_sseo_ai_featured_snippet', sanitize_textarea_field($_POST['sseo_featured_snippet']));
        }
        
        // Save snippet type
        if (isset($_POST['sseo_snippet_type'])) {
            update_post_meta($postId, '_sseo_ai_snippet_type', sanitize_text_field($_POST['sseo_snippet_type']));
        }
        
        // Save PAA questions
        if (isset($_POST['sseo_paa_questions'])) {
            $paaQuestions = [];
            foreach ($_POST['sseo_paa_questions'] as $qa) {
                if (!empty($qa['question']) && !empty($qa['answer'])) {
                    $paaQuestions[] = [
                        'question' => sanitize_text_field($qa['question']),
                        'answer' => sanitize_textarea_field($qa['answer']),
                    ];
                }
            }
            update_post_meta($postId, '_sseo_ai_paa_questions', $paaQuestions);
        }
    }
    
    /**
     * Get SERP feature checklist for a post
     */
    private function getSerpFeatureChecklist(\WP_Post $post): array
    {
        $content = $post->post_content;
        $wordCount = str_word_count(wp_strip_all_tags($content));
        $featuredSnippet = get_post_meta($post->ID, '_sseo_ai_featured_snippet', true);
        $paaQuestions = get_post_meta($post->ID, '_sseo_ai_paa_questions', true) ?: [];
        $hasImages = preg_match('/<img/', $content);
        $hasVideo = preg_match('/<iframe|<video/', $content);
        $hasTable = preg_match('/<table/', $content);
        $hasList = preg_match('/<ul|<ol/', $content);
        $hasSchema = get_post_meta($post->ID, '_sseo_ai_schema_type', true);
        
        return [
            [
                'title' => __('Featured Snippet Content', 'ai-seo-client'),
                'description' => __('Add a concise 40-60 word answer at the beginning of your content', 'ai-seo-client'),
                'achieved' => !empty($featuredSnippet),
            ],
            [
                'title' => __('Structured Data (Schema)', 'ai-seo-client'),
                'description' => __('Add appropriate schema markup for rich snippets', 'ai-seo-client'),
                'achieved' => !empty($hasSchema),
            ],
            [
                'title' => __('PAA Questions Covered', 'ai-seo-client'),
                'description' => __('Answer at least 3-5 related questions in your content', 'ai-seo-client'),
                'achieved' => count($paaQuestions) >= 3,
            ],
            [
                'title' => __('Lists or Tables', 'ai-seo-client'),
                'description' => __('Include formatted lists or tables for better snippet chances', 'ai-seo-client'),
                'achieved' => $hasList || $hasTable,
            ],
            [
                'title' => __('High-Quality Images', 'ai-seo-client'),
                'description' => __('Add relevant, optimized images with proper alt text', 'ai-seo-client'),
                'achieved' => $hasImages,
            ],
            [
                'title' => __('Video Content', 'ai-seo-client'),
                'description' => __('Embed relevant video content for video pack eligibility', 'ai-seo-client'),
                'achieved' => $hasVideo,
            ],
            [
                'title' => __('Comprehensive Content', 'ai-seo-client'),
                'description' => __('Content should be 1500+ words for knowledge panel consideration', 'ai-seo-client'),
                'achieved' => $wordCount >= 1500,
            ],
        ];
    }
    
    /**
     * Render SERP features dashboard
     */
    public function renderDashboard(): void
    {
        global $wpdb;
        
        // Get posts with SERP feature optimization
        $posts = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'meta_query' => [
                [
                    'key' => '_sseo_ai_target_features',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SERP Feature Tracking', 'ai-seo-client'); ?></h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('SERP Feature Overview', 'ai-seo-client'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Post', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Target Features', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Optimization Score', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $targetFeatures = get_post_meta($post->ID, '_sseo_ai_target_features', true) ?: [];
                            $checklist = $this->getSerpFeatureChecklist($post);
                            $achieved = count(array_filter($checklist, fn($item) => $item['achieved']));
                            $total = count($checklist);
                            $score = round(($achieved / $total) * 100);
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $featureLabels = [
                                    'featured_snippet' => __('Featured Snippet', 'ai-seo-client'),
                                    'paa' => __('PAA', 'ai-seo-client'),
                                    'knowledge_panel' => __('Knowledge Panel', 'ai-seo-client'),
                                    'image_pack' => __('Image Pack', 'ai-seo-client'),
                                    'video_pack' => __('Video Pack', 'ai-seo-client'),
                                ];
                                
                                foreach ($targetFeatures as $feature) {
                                    echo '<span class="badge" style="background: #2271b1; color: white; padding: 3px 8px; border-radius: 3px; margin-right: 5px; font-size: 11px;">';
                                    echo esc_html($featureLabels[$feature] ?? $feature);
                                    echo '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <strong style="color: <?php echo $score >= 70 ? '#00a32a' : ($score >= 40 ? '#dba617' : '#d63638'); ?>;">
                                    <?php echo $score; ?>%
                                </strong>
                                (<?php echo $achieved; ?>/<?php echo $total; ?>)
                            </td>
                            <td>
                                <?php if ($score >= 70): ?>
                                    <span style="color: #00a32a;">✓ <?php esc_html_e('Optimized', 'ai-seo-client'); ?></span>
                                <?php elseif ($score >= 40): ?>
                                    <span style="color: #dba617;">⚠ <?php esc_html_e('Needs Work', 'ai-seo-client'); ?></span>
                                <?php else: ?>
                                    <span style="color: #d63638;">✗ <?php esc_html_e('Not Optimized', 'ai-seo-client'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/serp/generate-snippet', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateSnippet'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/serp/generate-paa', [
            'methods' => 'POST',
            'callback' => [$this, 'restGeneratePAA'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    /**
     * REST: Generate featured snippet
     */
    public function restGenerateSnippet(\WP_REST_Request $request)
    {
        $postId = (int)$request->get_param('post_id');
        $post = get_post($postId);
        
        if (!$post) {
            return new \WP_Error('no_post', __('Post not found', 'ai-seo-client'));
        }
        
        $keyword = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = mb_substr($content, 0, 500);
        
        $prompt = "Generate a concise featured snippet (40-60 words) that directly answers the query: \"{$keyword}\"

Content context: {$excerpt}

Requirements:
- Direct, clear answer
- 40-60 words
- Start with the answer immediately
- Use simple language
- Include the keyword naturally

Featured snippet:";
        
        $response = $this->llm->generateText($prompt, ['max_tokens' => 150]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $snippet = trim($response);
        update_post_meta($postId, '_sseo_ai_featured_snippet', $snippet);
        
        return ['snippet' => $snippet];
    }
    
    /**
     * REST: Generate PAA questions
     */
    public function restGeneratePAA(\WP_REST_Request $request)
    {
        $postId = (int)$request->get_param('post_id');
        $post = get_post($postId);
        
        if (!$post) {
            return new \WP_Error('no_post', __('Post not found', 'ai-seo-client'));
        }
        
        $keyword = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = mb_substr($content, 0, 800);
        
        $prompt = "Generate 5 \"People Also Ask\" questions related to: \"{$keyword}\"

Content context: {$excerpt}

For each question, provide a concise answer (2-3 sentences) based on the content.

Format as JSON:
[
  {\"question\": \"...\", \"answer\": \"...\"},
  ...
]";
        
        $response = $this->llm->generateText($prompt, ['max_tokens' => 800]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Extract JSON
        $json = preg_replace('/```json\s*|\s*```/', '', $response);
        $questions = json_decode($json, true);
        
        if (!$questions) {
            return new \WP_Error('parse_error', __('Failed to parse AI response', 'ai-seo-client'));
        }
        
        update_post_meta($postId, '_sseo_ai_paa_questions', $questions);
        
        return ['questions' => $questions];
    }
}

<?php

namespace AISEOClient;

/**
 * FAQ Auto-Schema Generator
 * 
 * Automatically extracts and generates FAQ schema:
 * - FAQ content extraction from posts
 * - Auto-generate FAQ schema markup
 * - FAQ block detection
 * - AI-powered FAQ generation
 */
class FAQSchema
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
        add_action('wp_ajax_sseo_ai_extract_faqs', [$this, 'ajaxExtractFAQs']);
        add_action('wp_ajax_sseo_ai_generate_faqs', [$this, 'ajaxGenerateFAQs']);
        
        // Output FAQ schema
        add_action('wp_head', [$this, 'outputFAQSchema']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('FAQ Schema', 'ai-seo-client'),
            __('FAQ Schema', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-faq-schema',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render FAQ schema dashboard
     */
    public function renderDashboard(): void
    {
        $stats = $this->getFAQStats();
        $recentFAQs = $this->getRecentFAQPosts();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FAQ Schema Generator', 'ai-seo-client'); ?></h1>
            
            <!-- FAQ Statistics -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('FAQ Schema Overview', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #2271b1;">
                            <?php echo esc_html($stats['total_posts']); ?>
                        </div>
                        <div><?php esc_html_e('Posts with FAQs', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #d1e7dd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #00a32a;">
                            <?php echo esc_html($stats['total_faqs']); ?>
                        </div>
                        <div><?php esc_html_e('Total FAQ Items', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #856404;">
                            <?php echo esc_html($stats['avg_faqs']); ?>
                        </div>
                        <div><?php esc_html_e('Avg FAQs per Post', 'ai-seo-client'); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: #f8d7da; border-radius: 4px;">
                        <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                            <?php echo esc_html($stats['missing_schema']); ?>
                        </div>
                        <div><?php esc_html_e('Missing Schema', 'ai-seo-client'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Recent FAQ Posts -->
            <div class="card">
                <h2><?php esc_html_e('Recent Posts with FAQs', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($recentFAQs)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Post', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('FAQ Count', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Schema Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Last Updated', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentFAQs as $faq): ?>
                        <tr>
                            <td>
                                <a href="<?php echo get_edit_post_link($faq['post_id']); ?>">
                                    <?php echo esc_html($faq['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($faq['faq_count']); ?></td>
                            <td>
                                <?php if ($faq['has_schema']): ?>
                                <span style="color: #00a32a;">✓ Active</span>
                                <?php else: ?>
                                <span style="color: #d63638;">✗ Missing</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(human_time_diff(strtotime($faq['updated']))); ?> ago</td>
                            <td>
                                <a href="<?php echo get_edit_post_link($faq['post_id']); ?>#sseo-faq-schema" class="button button-small">
                                    <?php esc_html_e('Edit FAQs', 'ai-seo-client'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('No FAQ posts found. Add FAQs to your posts to see them here.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add FAQ schema meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-faq-schema',
                __('FAQ Schema', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }
    
    /**
     * Render FAQ schema meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $faqs = get_post_meta($post->ID, '_sseo_ai_faqs', true) ?: [];
        $autoExtract = get_post_meta($post->ID, '_sseo_ai_faq_auto_extract', true);
        
        wp_nonce_field('sseo_faq_schema_save', 'sseo_faq_schema_nonce');
        ?>
        <div class="sseo-faq-schema-box">
            <div style="margin-bottom: 20px;">
                <label>
                    <input type="checkbox" name="sseo_faq_auto_extract" value="1" <?php checked($autoExtract, '1'); ?>>
                    <?php esc_html_e('Auto-extract FAQs from content', 'ai-seo-client'); ?>
                </label>
                <p class="description">
                    <?php esc_html_e('Automatically detect and extract FAQ patterns from your content', 'ai-seo-client'); ?>
                </p>
            </div>
            
            <div style="margin-bottom: 15px;">
                <button type="button" class="button" onclick="sseoExtractFAQs(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Extract FAQs from Content', 'ai-seo-client'); ?>
                </button>
                <button type="button" class="button button-primary" onclick="sseoGenerateFAQs(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('AI Generate FAQs', 'ai-seo-client'); ?>
                </button>
            </div>
            
            <div id="faq-list">
                <?php if (!empty($faqs)): ?>
                    <?php foreach ($faqs as $index => $faq): ?>
                    <div class="faq-item" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;">
                        <div style="margin-bottom: 10px;">
                            <label><strong><?php esc_html_e('Question:', 'ai-seo-client'); ?></strong></label>
                            <input type="text" name="sseo_faqs[<?php echo $index; ?>][question]" 
                                   value="<?php echo esc_attr($faq['question']); ?>" 
                                   class="large-text" 
                                   placeholder="<?php esc_attr_e('Enter question', 'ai-seo-client'); ?>">
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Answer:', 'ai-seo-client'); ?></strong></label>
                            <textarea name="sseo_faqs[<?php echo $index; ?>][answer]" 
                                      rows="3" 
                                      class="large-text"
                                      placeholder="<?php esc_attr_e('Enter answer', 'ai-seo-client'); ?>"><?php echo esc_textarea($faq['answer']); ?></textarea>
                        </div>
                        <button type="button" class="button button-small" onclick="sseoRemoveFAQ(this)" style="margin-top: 10px;">
                            <?php esc_html_e('Remove', 'ai-seo-client'); ?>
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button" onclick="sseoAddFAQ()">
                <?php esc_html_e('Add FAQ Item', 'ai-seo-client'); ?>
            </button>
            
            <?php if (!empty($faqs)): ?>
            <div style="margin-top: 20px; padding: 15px; background: #d1e7dd; border-left: 4px solid #00a32a;">
                <strong><?php esc_html_e('Schema Preview:', 'ai-seo-client'); ?></strong>
                <p><?php echo esc_html(count($faqs)); ?> FAQ items will be added to schema markup</p>
                <button type="button" class="button button-small" onclick="sseoPreviewFAQSchema(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Preview Schema JSON', 'ai-seo-client'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <script>
        let faqIndex = <?php echo count($faqs); ?>;
        
        function sseoAddFAQ() {
            const container = document.getElementById('faq-list');
            const div = document.createElement('div');
            div.className = 'faq-item';
            div.style.cssText = 'margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-left: 3px solid #2271b1;';
            div.innerHTML = `
                <div style="margin-bottom: 10px;">
                    <label><strong><?php esc_html_e('Question:', 'ai-seo-client'); ?></strong></label>
                    <input type="text" name="sseo_faqs[${faqIndex}][question]" 
                           class="large-text" 
                           placeholder="<?php esc_attr_e('Enter question', 'ai-seo-client'); ?>">
                </div>
                <div>
                    <label><strong><?php esc_html_e('Answer:', 'ai-seo-client'); ?></strong></label>
                    <textarea name="sseo_faqs[${faqIndex}][answer]" 
                              rows="3" 
                              class="large-text"
                              placeholder="<?php esc_attr_e('Enter answer', 'ai-seo-client'); ?>"></textarea>
                </div>
                <button type="button" class="button button-small" onclick="sseoRemoveFAQ(this)" style="margin-top: 10px;">
                    <?php esc_html_e('Remove', 'ai-seo-client'); ?>
                </button>
            `;
            container.appendChild(div);
            faqIndex++;
        }
        
        function sseoRemoveFAQ(button) {
            button.closest('.faq-item').remove();
        }
        
        function sseoExtractFAQs(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_extract_faqs',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_faq'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error extracting FAQs');
                }
            });
        }
        
        function sseoGenerateFAQs(postId) {
            if (!confirm('<?php esc_html_e('Generate FAQs using AI? This will add new FAQ items.', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_faqs',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_faq'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error generating FAQs');
                }
            });
        }
        
        function sseoPreviewFAQSchema(postId) {
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_get_faq_schema',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_faq'); ?>'
            }, function(response) {
                if (response.success) {
                    const modal = jQuery('<div class="sseo-modal"><div class="sseo-modal-content">' +
                        '<h2><?php esc_html_e('FAQ Schema Preview', 'ai-seo-client'); ?></h2>' +
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
     * Save FAQ schema meta box
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['sseo_faq_schema_nonce']) || !wp_verify_nonce($_POST['sseo_faq_schema_nonce'], 'sseo_faq_schema_save')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $postId)) {
            return;
        }
        
        // Save auto-extract setting
        $autoExtract = isset($_POST['sseo_faq_auto_extract']) ? '1' : '0';
        update_post_meta($postId, '_sseo_ai_faq_auto_extract', $autoExtract);
        
        // Save FAQs
        if (isset($_POST['sseo_faqs'])) {
            $faqs = [];
            foreach ($_POST['sseo_faqs'] as $faq) {
                if (!empty($faq['question']) && !empty($faq['answer'])) {
                    $faqs[] = [
                        'question' => sanitize_text_field($faq['question']),
                        'answer' => wp_kses_post($faq['answer']),
                    ];
                }
            }
            update_post_meta($postId, '_sseo_ai_faqs', $faqs);
        }
    }
    
    /**
     * Extract FAQs from content
     */
    public function extractFAQs(int $postId): array
    {
        $post = get_post($postId);
        $content = $post->post_content;
        
        $faqs = [];
        
        // Method 1: Detect FAQ blocks (Gutenberg)
        if (has_blocks($content)) {
            $blocks = parse_blocks($content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/faq' || strpos($block['blockName'], 'faq') !== false) {
                    // Extract FAQ from block
                    $faqs = array_merge($faqs, $this->extractFAQsFromBlock($block));
                }
            }
        }
        
        // Method 2: Detect Q&A patterns in content
        $patterns = [
            '/(?:Q:|Question:)\s*(.+?)\s*(?:A:|Answer:)\s*(.+?)(?=Q:|Question:|$)/is',
            '/<h[2-6][^>]*>(.+?)<\/h[2-6]>\s*<p>(.+?)<\/p>/is',
        ];
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (count($match) >= 3) {
                    $question = strip_tags($match[1]);
                    $answer = strip_tags($match[2]);
                    
                    // Only add if it looks like a question
                    if (preg_match('/\?$/', $question) || 
                        preg_match('/^(what|how|why|when|where|who|which|can|is|are|do|does)/i', $question)) {
                        $faqs[] = [
                            'question' => trim($question),
                            'answer' => trim($answer),
                        ];
                    }
                }
            }
        }
        
        return array_slice($faqs, 0, 20); // Limit to 20 FAQs
    }
    
    /**
     * Extract FAQs from Gutenberg block
     */
    private function extractFAQsFromBlock(array $block): array
    {
        $faqs = [];
        
        // This would parse the block structure
        // Placeholder implementation
        
        return $faqs;
    }
    
    /**
     * Generate FAQs using AI
     */
    public function generateFAQs(int $postId): array
    {
        $post = get_post($postId);
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = substr($content, 0, 1000);
        
        $prompt = "Based on this content, generate 5-10 frequently asked questions and answers:

Title: {$post->post_title}
Content excerpt: {$excerpt}

Generate questions that readers would commonly ask about this topic. Format as JSON:
[
  {\"question\": \"...\", \"answer\": \"...\"},
  ...
]

Make answers concise (2-3 sentences) and informative.";
        
        $response = $this->llm->generateText($prompt, ['max_tokens' => 1000]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        // Parse JSON response
        $json = preg_replace('/```json\s*|\s*```/', '', $response);
        $faqs = json_decode($json, true);
        
        if (!is_array($faqs)) {
            return [];
        }
        
        return array_slice($faqs, 0, 10);
    }
    
    /**
     * Generate FAQ schema
     */
    private function generateFAQSchema(int $postId): array
    {
        $faqs = get_post_meta($postId, '_sseo_ai_faqs', true);
        
        if (empty($faqs)) {
            return [];
        }
        
        $mainEntity = [];
        
        foreach ($faqs as $faq) {
            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ];
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }
    
    /**
     * Output FAQ schema in head
     */
    public function outputFAQSchema(): void
    {
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        $faqs = get_post_meta($post->ID, '_sseo_ai_faqs', true);
        
        if (empty($faqs)) {
            return;
        }
        
        $schema = $this->generateFAQSchema($post->ID);
        
        if (empty($schema)) {
            return;
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }
    
    /**
     * Get FAQ stats
     */
    private function getFAQStats(): array
    {
        global $wpdb;
        
        $totalPosts = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sseo_ai_faqs'
            AND meta_value != ''
            AND meta_value != 'a:0:{}'
        ");
        
        $results = $wpdb->get_results("
            SELECT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sseo_ai_faqs'
            AND meta_value != ''
            AND meta_value != 'a:0:{}'
        ");
        
        $totalFAQs = 0;
        foreach ($results as $row) {
            $faqs = maybe_unserialize($row->meta_value);
            if (is_array($faqs)) {
                $totalFAQs += count($faqs);
            }
        }
        
        $avgFAQs = $totalPosts > 0 ? round($totalFAQs / $totalPosts, 1) : 0;
        
        return [
            'total_posts' => (int)$totalPosts,
            'total_faqs' => $totalFAQs,
            'avg_faqs' => $avgFAQs,
            'missing_schema' => 0, // Would calculate posts without schema
        ];
    }
    
    /**
     * Get recent FAQ posts
     */
    private function getRecentFAQPosts(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT p.ID as post_id, p.post_title as title, p.post_modified as updated,
                   pm.meta_value as faqs
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_sseo_ai_faqs'
            AND pm.meta_value != ''
            AND pm.meta_value != 'a:0:{}'
            AND p.post_status = 'publish'
            ORDER BY p.post_modified DESC
            LIMIT 20
        ");
        
        $posts = [];
        
        foreach ($results as $row) {
            $faqs = maybe_unserialize($row->faqs);
            $faqCount = is_array($faqs) ? count($faqs) : 0;
            
            $posts[] = [
                'post_id' => $row->post_id,
                'title' => $row->title,
                'faq_count' => $faqCount,
                'has_schema' => $faqCount > 0,
                'updated' => $row->updated,
            ];
        }
        
        return $posts;
    }
    
    /**
     * AJAX handlers
     */
    public function ajaxExtractFAQs(): void
    {
        check_ajax_referer('sseo_faq', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $faqs = $this->extractFAQs($postId);
        
        if (empty($faqs)) {
            wp_send_json_error(['message' => 'No FAQs found in content']);
        }
        
        // Merge with existing FAQs
        $existingFAQs = get_post_meta($postId, '_sseo_ai_faqs', true) ?: [];
        $allFAQs = array_merge($existingFAQs, $faqs);
        
        update_post_meta($postId, '_sseo_ai_faqs', $allFAQs);
        
        wp_send_json_success(['faqs' => $allFAQs]);
    }
    
    public function ajaxGenerateFAQs(): void
    {
        check_ajax_referer('sseo_faq', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $postId = (int)($_POST['post_id'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        $faqs = $this->generateFAQs($postId);
        
        if (empty($faqs)) {
            wp_send_json_error(['message' => 'Failed to generate FAQs']);
        }
        
        // Merge with existing FAQs
        $existingFAQs = get_post_meta($postId, '_sseo_ai_faqs', true) ?: [];
        $allFAQs = array_merge($existingFAQs, $faqs);
        
        update_post_meta($postId, '_sseo_ai_faqs', $allFAQs);
        
        wp_send_json_success(['faqs' => $allFAQs]);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/faq/schema/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetFAQSchema'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/faq/extract/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restExtractFAQs'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    public function restGetFAQSchema(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $schema = $this->generateFAQSchema($postId);
        
        return ['schema' => $schema];
    }
    
    public function restExtractFAQs(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $faqs = $this->extractFAQs($postId);
        
        return ['faqs' => $faqs];
    }
}

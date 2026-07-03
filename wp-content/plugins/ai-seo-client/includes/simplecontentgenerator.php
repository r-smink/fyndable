<?php

namespace SSEOAIClient;

/**
 * Simple Content Generator
 *
 * Adds a meta box to posts and pages for simple AI content generation
 * with context and word count controls.
 */
class SimpleContentGenerator
{
    private LLMClient $llm;

    public function __construct(LLMClient $llm)
    {
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('wp_ajax_sseo_ai_generate_simple_content', [$this, 'ajaxGenerateContent']);
    }

    public function addMetaBox(): void
    {
        $postTypes = ['post', 'page'];
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-simple-content-gen',
                __('AI Content Generator', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'high'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('sseo_simple_content_save', 'sseo_simple_content_nonce');
        $defaultWordCount = (int) get_option('sseo_ai_client_default_word_count', 500);
        ?>
        <div class="sseo-simple-content-box" style="padding: 10px;">
            <p class="description" style="margin-bottom: 10px;">
                <?php esc_html_e('Generate content for this post/page using AI. Enter context and desired word count below.', 'ai-seo-client'); ?>
            </p>

            <div style="margin-bottom: 12px;">
                <label><strong><?php esc_html_e('Topic / Focus Keyword:', 'ai-seo-client'); ?></strong></label>
                <input type="text" id="sseo-simple-topic" class="large-text" style="margin-top: 5px;"
                       value="<?php echo esc_attr(get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true)); ?>"
                       placeholder="<?php esc_attr_e('Enter the main topic or keyword...', 'ai-seo-client'); ?>">
            </div>

            <div style="margin-bottom: 12px;">
                <label><strong><?php esc_html_e('Context / Instructions for AI:', 'ai-seo-client'); ?></strong></label>
                <textarea id="sseo-simple-context" rows="4" class="large-text" style="margin-top: 5px;"
                          placeholder="<?php esc_attr_e('e.g. Write a friendly introduction about..., Include benefits section, Target audience is small business owners...', 'ai-seo-client'); ?>"></textarea>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <label><strong><?php esc_html_e('Word Count:', 'ai-seo-client'); ?></strong></label>
                    <input type="number" id="sseo-simple-wordcount" value="<?php echo esc_attr($defaultWordCount); ?>" min="100" max="3000" step="50" style="width: 100%; margin-top: 5px;">
                </div>
                <div style="flex: 1;">
                    <label><strong><?php esc_html_e('Tone:', 'ai-seo-client'); ?></strong></label>
                    <select id="sseo-simple-tone" style="width: 100%; margin-top: 5px;">
                        <option value="professional"><?php esc_html_e('Professional', 'ai-seo-client'); ?></option>
                        <option value="casual"><?php esc_html_e('Casual', 'ai-seo-client'); ?></option>
                        <option value="friendly"><?php esc_html_e('Friendly', 'ai-seo-client'); ?></option>
                        <option value="technical"><?php esc_html_e('Technical', 'ai-seo-client'); ?></option>
                        <option value="authoritative"><?php esc_html_e('Authoritative', 'ai-seo-client'); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label>
                    <input type="checkbox" id="sseo-simple-append" value="1">
                    <?php esc_html_e('Append to existing content instead of replacing', 'ai-seo-client'); ?>
                </label>
            </div>

            <p>
                <button type="button" class="button button-primary" id="sseo-simple-generate-btn" onclick="sseoSimpleGenerateContent(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Generate Content', 'ai-seo-client'); ?>
                </button>
                <span class="spinner" style="float: none; margin-left: 10px;"></span>
            </p>

            <div id="sseo-simple-preview" style="display:none; margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1; border-radius: 4px;">
                <h4 style="margin-top: 0;"><?php esc_html_e('Generated Preview', 'ai-seo-client'); ?></h4>
                <div id="sseo-simple-preview-content" style="max-height: 300px; overflow-y: auto; background: #fff; padding: 15px; border: 1px solid #ddd; line-height: 1.7;"></div>
                <p style="margin-top: 10px;">
                    <button type="button" class="button" onclick="sseoSimpleInsertContent(<?php echo $post->ID; ?>)">
                        <?php esc_html_e('Insert into Editor', 'ai-seo-client'); ?>
                    </button>
                    <button type="button" class="button" onclick="jQuery('#sseo-simple-preview').hide();">
                        <?php esc_html_e('Discard', 'ai-seo-client'); ?>
                    </button>
                </p>
            </div>
        </div>

        <script>
        var sseoGeneratedContent = '';

        function sseoSimpleGenerateContent(postId) {
            var btn = jQuery('#sseo-simple-generate-btn');
            var spinner = btn.next('.spinner');
            var topic = jQuery('#sseo-simple-topic').val().trim();
            var context = jQuery('#sseo-simple-context').val().trim();
            var wordCount = jQuery('#sseo-simple-wordcount').val();
            var tone = jQuery('#sseo-simple-tone').val();

            if (!topic) {
                alert('<?php echo esc_js(__('Please enter a topic or keyword.', 'ai-seo-client')); ?>');
                return;
            }

            btn.prop('disabled', true);
            spinner.addClass('is-active');
            jQuery('#sseo-simple-preview').hide();
            if (typeof sseoShowLoader === 'function') sseoShowLoader();

            jQuery.post(ajaxurl, {
                action: 'sseo_ai_generate_simple_content',
                post_id: postId,
                topic: topic,
                context: context,
                word_count: wordCount,
                tone: tone,
                nonce: '<?php echo wp_create_nonce('sseo_simple_content'); ?>'
            }, function(response) {
                spinner.removeClass('is-active');
                btn.prop('disabled', false);
                if (typeof sseoHideLoader === 'function') sseoHideLoader();

                if (response.success && response.data.content) {
                    sseoGeneratedContent = response.data.content;
                    jQuery('#sseo-simple-preview-content').html(response.data.content);
                    jQuery('#sseo-simple-preview').show();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Failed to generate content.', 'ai-seo-client')); ?>');
                }
            }).fail(function() {
                spinner.removeClass('is-active');
                btn.prop('disabled', false);
                if (typeof sseoHideLoader === 'function') sseoHideLoader();
                alert('<?php echo esc_js(__('Request failed. Please try again.', 'ai-seo-client')); ?>');
            });
        }

        function sseoSimpleInsertContent(postId) {
            if (!sseoGeneratedContent) return;

            var append = jQuery('#sseo-simple-append').is(':checked');

            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                // Gutenberg
                var current = wp.data.select('core/editor').getEditedPostContent();
                var newContent = append ? (current + '\n\n' + sseoGeneratedContent) : sseoGeneratedContent;
                wp.data.dispatch('core/editor').editPost({ content: newContent });
                alert('<?php echo esc_js(__('Content inserted into the block editor.', 'ai-seo-client')); ?>');
            } else {
                // Classic editor
                var editor = document.getElementById('content');
                if (editor) {
                    if (append) {
                        editor.value = editor.value + '\n\n' + sseoGeneratedContent;
                    } else {
                        editor.value = sseoGeneratedContent;
                    }
                    if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                        tinymce.get('content').setContent(editor.value);
                    }
                    alert('<?php echo esc_js(__('Content inserted into the editor.', 'ai-seo-client')); ?>');
                }
            }
            jQuery('#sseo-simple-preview').hide();
        }
        </script>
        <?php
    }

    public function ajaxGenerateContent(): void
    {
        check_ajax_referer('sseo_simple_content', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $postId = (int)($_POST['post_id'] ?? 0);
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $context = sanitize_textarea_field($_POST['context'] ?? '');
        $wordCount = (int)($_POST['word_count'] ?? 500);
        $tone = sanitize_text_field($_POST['tone'] ?? 'professional');

        if (!$postId || empty($topic)) {
            wp_send_json_error(['message' => 'Topic and post ID are required']);
        }

        $post = get_post($postId);
        if (!$post) {
            wp_send_json_error(['message' => 'Post not found']);
        }

        $contextPrompt = $context ? "Additional context / instructions: {$context}\n" : "";

        $prompt = "Write SEO-optimized content for a {$post->post_type} about: {$topic}

{$contextPrompt}Requirements:
- Write approximately {$wordCount} words
- Tone: {$tone}
- Use clear, scannable paragraphs (3-4 sentences each)
- Use HTML formatting (<p> tags for paragraphs, <h2> for sections, <ul>/<li> for lists)
- Include the topic naturally throughout the text
- Write an engaging introduction and a conclusion with a call-to-action
- Do NOT include a title / H1 (it will be added separately)

Write the content:";

        $result = $this->llm->generateText($prompt, [
            'max_tokens' => max(1000, (int)($wordCount * 2)),
            'track_extra' => [
                'endpoint' => 'simple_content.generate',
                'post_id' => $postId,
                'context' => 'topic:' . $topic . ' tone:' . $tone,
            ],
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $content = $this->cleanHtmlOutput($result);

        wp_send_json_success([
            'content' => $content,
            'word_count' => str_word_count(strip_tags($content)),
        ]);
    }

    private function cleanHtmlOutput(string $html): string
    {
        $html = preg_replace('/```html\s*/', '', $html);
        $html = preg_replace('/```\s*/', '', $html);
        $html = trim($html);

        if (strpos($html, '<') === false) {
            $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $html)));
            $html = '<p>' . implode("</p>\n<p>", $paragraphs) . '</p>';
        }

        return $html;
    }
}

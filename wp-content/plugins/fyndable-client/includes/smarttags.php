<?php

namespace SSEOAIClient;

class SmartTags
{
    private LlmClient $llm;

    public function __construct(LlmClient $llm)
    {
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('save_post', [$this, 'autoGenerateTags'], 10, 2);
        // Meta box moved to PostMetaBox tabbed container
    }

    public function addMetaBox(): void
    {
        // Only load on post edit screens
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }
        
        add_meta_box(
            'aiseo_smart_tags',
            __('AI Smart Tags', 'ai-seo-client'),
            [$this, 'renderMetaBox'],
            'post',
            'side',
            'default'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $suggested = get_post_meta($post->ID, '_sseo_ai_tag_suggestions', true);
        $currentTags = get_the_tags($post->ID);
        $currentTagNames = $currentTags ? wp_list_pluck($currentTags, 'name') : [];
        ?>
        <div class="aiseo-smart-tags-box">
            <p class="fyn-row-intro"><?php esc_html_e('AI-suggested tags based on your content:', 'ai-seo-client'); ?></p>

            <div class="fyn-btnbar">
                <button type="button" class="fyn-btn fyn-btn--primary" id="aiseo-generate-tags" data-post="<?php echo $post->ID; ?>">
                    <span class="dashicons dashicons-superhero-alt"></span>
                    <span class="fyn-btn-label"><?php esc_html_e('Generate Smart Tags', 'ai-seo-client'); ?></span>
                </button>
            </div>

            <div id="aiseo-tags-suggestions">
                <?php if ($suggested) : ?>
                <label class="fyn-lbl"><?php esc_html_e('Suggested:', 'ai-seo-client'); ?></label>
                <div class="fyn-chips aiseo-tags-cloud">
                    <?php foreach ($suggested as $tag) :
                        $isApplied = in_array($tag, $currentTagNames);
                    ?>
                    <button type="button"
                            class="fyn-chip aiseo-tag-suggestion<?php echo $isApplied ? ' fyn-chip--on applied' : ''; ?>"
                            data-tag="<?php echo esc_attr($tag); ?>">
                        <span class="fyn-chip-label"><?php echo esc_html($tag); ?></span>
                        <span class="dashicons <?php echo $isApplied ? 'dashicons-yes' : 'dashicons-plus-alt2'; ?>"></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php else : ?>
                <p class="fyn-empty"><?php esc_html_e('Click "Generate Smart Tags" to analyze your content and suggest relevant tags.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#aiseo-generate-tags').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post');

                btn.prop('disabled', true).find('.fyn-btn-label').text('<?php echo esc_js(__('Analyzing...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/generate-tags',
                    method: 'POST',
                    data: { post_id: postId }
                }).then(function(response) {
                    var html = '<label class="fyn-lbl"><?php echo esc_js(__('Suggested:', 'ai-seo-client')); ?></label>';
                    html += '<div class="fyn-chips aiseo-tags-cloud">';
                    response.tags.forEach(function(tag) {
                        html += '<button type="button" class="fyn-chip aiseo-tag-suggestion" data-tag="' + tag + '">';
                        html += '<span class="fyn-chip-label">' + tag + '</span>';
                        html += '<span class="dashicons dashicons-plus-alt2"></span></button>';
                    });
                    html += '</div>';
                    $('#aiseo-tags-suggestions').html(html);
                    btn.prop('disabled', false).find('.fyn-btn-label').text('<?php echo esc_js(__('Generate Smart Tags', 'ai-seo-client')); ?>');
                });
            });

            $(document).on('click', '.aiseo-tag-suggestion:not(.applied)', function() {
                var chip = $(this);
                var tag = chip.data('tag');
                var existingTags = $('#new-tag-post_tag').val();
                var newValue = existingTags ? existingTags + ', ' + tag : tag;
                $('#new-tag-post_tag').val(newValue);
                chip.addClass('applied fyn-chip--on')
                    .find('.dashicons').attr('class', 'dashicons dashicons-yes');
            });
        });
        </script>
        <?php
    }

    public function autoGenerateTags(int $postId, \WP_Post $post): void
    {
        $autoGenerate = get_option('aiseo_auto_tags', 0);
        if (!$autoGenerate) {
            return;
        }

        if ($post->post_status !== 'publish' && $post->post_status !== 'draft') {
            return;
        }

        // Don't override if tags already exist
        $existingTags = get_the_tags($postId);
        if ($existingTags && count($existingTags) >= 3) {
            return;
        }

        $suggested = $this->generateTags($post);
        
        if (!empty($suggested)) {
            update_post_meta($postId, '_sseo_ai_tag_suggestions', $suggested);

            // Auto-apply top tags if enabled
            $autoApply = get_option('aiseo_auto_apply_tags', 0);
            if ($autoApply && empty($existingTags)) {
                $topTags = array_slice($suggested, 0, 5);
                wp_set_post_tags($postId, $topTags);
            }
        }
    }

    public function generateTags(\WP_Post $post): array
    {
        $prompt = "Extract 5-10 relevant tags/keywords for this blog post. Tags should be:
- Specific to the content topic
- Common search terms people might use
- Single words or short phrases (1-3 words max)
- Mix of broad and specific terms

Format: Return as comma-separated list.

Title: {$post->post_title}

Content: " . wp_trim_words(strip_tags($post->post_content), 200);

        $result = $this->llm->call($prompt, null, null, null, [], 'content_analysis');
        
        if (is_wp_error($result)) {
            return $this->fallbackTagExtraction($post);
        }

        $tags = array_map('trim', explode(',', $result['text']));
        $tags = array_filter($tags, fn($t) => strlen($t) > 2 && strlen($t) < 50);
        
        return array_slice(array_unique($tags), 0, 10);
    }

    private function fallbackTagExtraction(\WP_Post $post): array
    {
        $content = strtolower(strip_tags($post->post_content . ' ' . $post->post_title));
        
        // Extract common words
        $words = str_word_count($content, 1);
        $freq = array_count_values($words);
        
        // Filter out common stop words
        $stopWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'its', 'may', 'new', 'now', 'old', 'see', 'two', 'who', 'boy', 'did', 'man', 'men', 'run', 'she', 'sun', 'way', 'what', 'with', 'this', 'that', 'have', 'from', 'they', 'been', 'were', 'said', 'each', 'which', 'their', 'time', 'will', 'about', 'when', 'word', 'said', 'each', 'make', 'water', 'than', 'them', 'into', 'look', 'more', 'find', 'long', 'down', 'most', 'over', 'know', 'take', 'than', 'only', 'other', 'after', 'first', 'never', 'these', 'think', 'where', 'being', 'every', 'great', 'might', 'shall', 'still', 'while', 'those', 'both', 'few', 'must', 'should', 'through', 'any', 'come', 'good', 'much', 'some', 'very', 'before', 'here', 'just', 'also', 'back', 'well', 'would', 'could'];
        
        foreach ($stopWords as $stop) {
            unset($freq[$stop]);
        }

        // Get top words
        arsort($freq);
        $tags = array_slice(array_keys($freq), 0, 10);
        
        // Add category names if available
        $categories = get_the_category($post->ID);
        foreach ($categories as $cat) {
            $tags[] = strtolower($cat->name);
        }

        return array_unique($tags);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/generate-tags', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateTags'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restGenerateTags(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('no_post', __('Post not found', 'ai-seo-client'));
        }

        $tags = $this->generateTags($post);
        update_post_meta($postId, '_sseo_ai_tag_suggestions', $tags);

        return ['tags' => $tags];
    }
}

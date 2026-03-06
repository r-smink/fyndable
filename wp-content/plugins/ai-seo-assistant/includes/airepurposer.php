<?php

namespace AISEOAssistant;

class AIRepurposer
{
    private Settings $settings;
    private LlmClient $llm;

    public function __construct(Settings $settings, LlmClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'aiseo_repurpose',
            __('AI Content Repurposing', 'ai-seo-assistant'),
            [$this, 'renderMetaBox'],
            ['post', 'page'],
            'side',
            'default'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        ?>
        <div class="aiseo-repurpose-box">
            <p><?php esc_html_e('Repurpose this content for different formats:', 'ai-seo-assistant'); ?></p>
            
            <button type="button" class="button aiseo-repurpose-btn" data-format="social-twitter" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('Twitter/X Post', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button aiseo-repurpose-btn" data-format="social-linkedin" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('LinkedIn Post', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button aiseo-repurpose-btn" data-format="social-facebook" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('Facebook Post', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button aiseo-repurpose-btn" data-format="newsletter" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('Newsletter Summary', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button aiseo-repurpose-btn" data-format="key-points" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('Key Points / TL;DR', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button aiseo-repurpose-btn" data-format="thread" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('Twitter Thread', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button aiseo-repurpose-btn" data-format="instagram" data-post="<?php echo $post->ID; ?>">
                <?php esc_html_e('Instagram Caption', 'ai-seo-assistant'); ?>
            </button>
            
            <div id="aiseo-repurpose-result" style="margin-top:15px; padding:10px; background:#f5f5f5; display:none;">
                <h4><?php esc_html_e('Generated Content', 'ai-seo-assistant'); ?></h4>
                <textarea id="aiseo-repurpose-output" rows="8" style="width:100%;" readonly></textarea>
                <button type="button" class="button" id="aiseo-copy-repurpose"><?php esc_html_e('Copy', 'ai-seo-assistant'); ?></button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.aiseo-repurpose-btn').on('click', function() {
                var format = $(this).data('format');
                var postId = $(this).data('post');
                var btn = $(this);
                
                btn.prop('disabled', true).text('<?php esc_html_e('Generating...', 'ai-seo-assistant'); ?>');
                
                wp.apiFetch({
                    path: 'aiseoassistant/v1/repurpose',
                    method: 'POST',
                    data: {
                        post_id: postId,
                        format: format
                    }
                }).then(function(response) {
                    $('#aiseo-repurpose-output').val(response.content);
                    $('#aiseo-repurpose-result').show();
                    btn.prop('disabled', false).text(btn.text().replace('<?php esc_html_e('Generating...', 'ai-seo-assistant'); ?>', ''));
                }).catch(function(error) {
                    alert('<?php esc_html_e('Error:', 'ai-seo-assistant'); ?> ' + error.message);
                    btn.prop('disabled', false);
                });
            });
            
            $('#aiseo-copy-repurpose').on('click', function() {
                $('#aiseo-repurpose-output').select();
                document.execCommand('copy');
                $(this).text('<?php esc_html_e('Copied!', 'ai-seo-assistant'); ?>').delay(2000).text('<?php esc_html_e('Copy', 'ai-seo-assistant'); ?>');
            });
        });
        </script>
        <?php
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoassistant/v1', '/repurpose', [
            'methods' => 'POST',
            'callback' => [$this, 'restRepurpose'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restRepurpose(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $format = sanitize_text_field($request->get_param('format'));

        $post = get_post($postId);
        if (!$post) {
            return new \WP_Error('no_post', __('Post not found', 'ai-seo-assistant'));
        }

        $content = $this->repurpose($post, $format);

        if (is_wp_error($content)) {
            return $content;
        }

        return ['content' => $content, 'format' => $format];
    }

    public function repurpose(\WP_Post $post, string $format)
    {
        $prompts = [
            'social-twitter' => "Convert this blog post into a catchy Twitter/X post. Maximum 280 characters including hashtags. Make it engaging and click-worthy:\n\n",
            'social-linkedin' => "Convert this blog post into a professional LinkedIn post. Use a hook, main insights, and a call-to-action. Keep it under 1300 characters:\n\n",
            'social-facebook' => "Convert this blog post into an engaging Facebook post with emojis and a friendly tone. Ask a question to encourage engagement:\n\n",
            'newsletter' => "Summarize this blog post as a newsletter snippet (3-4 paragraphs). Include a compelling subject line suggestion at the top:\n\n",
            'key-points' => "Extract 5-7 key points from this article as a TL;DR summary. Use bullet points:\n\n",
            'thread' => "Convert this blog post into a Twitter/X thread. Break it into 5-8 tweets, numbered. Each tweet max 280 chars:\n\n",
            'instagram' => "Create an Instagram caption from this blog post. Include relevant hashtags (10-15). Make it visually descriptive:\n\n",
        ];

        if (!isset($prompts[$format])) {
            return new \WP_Error('invalid_format', __('Invalid format specified', 'ai-seo-assistant'));
        }

        $content = strip_tags($post->post_content);
        $title = $post->post_title;
        $prompt = $prompts[$format] . "Title: {$title}\n\nContent:\n{$content}";

        $result = $this->llm->call($prompt);
        if (is_wp_error($result)) {
            return $result;
        }

        return $result['text'];
    }

    public function generateKeyPoints(string $content): array
    {
        $prompt = "Extract 5-7 key points from this article as bullet points. Be concise and focus on the most important takeaways:\n\n" . strip_tags($content);

        $result = $this->llm->call($prompt);
        if (is_wp_error($result)) {
            return [];
        }

        $lines = explode("\n", $result['text']);
        $points = [];
        foreach ($lines as $line) {
            $clean = preg_replace('/^[\s\-•\*\d\.]+/', '', $line);
            if (strlen($clean) > 10) {
                $points[] = trim($clean);
            }
        }

        return array_slice($points, 0, 7);
    }

    public function generateTLDR(string $content): string
    {
        $prompt = "Write a 2-3 sentence TL;DR (Too Long; Didn't Read) summary of this article:\n\n" . strip_tags($content);

        $result = $this->llm->call($prompt);
        if (is_wp_error($result)) {
            return '';
        }

        return $result['text'];
    }
}

<?php

namespace AISEOAssistant;

class BulkActions
{
    private LlmClient $llm;

    public function __construct(LlmClient $llm)
    {
        $this->llm = $llm;
    }

    /**
     * Find posts missing excerpt/meta.
     */
    public function findNeedingMeta(int $limit = 50): array
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_aiseo_description',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_aiseo_description',
                    'value' => '',
                    'compare' => '='
                ]
            ]
        ]);
        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'ID' => $p->ID,
                'title' => $p->post_title,
                'edit_link' => get_edit_post_link($p->ID, ''),
            ];
        }
        return $out;
    }

    public function generateMeta(int $postId): array|\WP_Error
    {
        $post = get_post($postId);
        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-assistant'));
        }
        $prompt = "Write a meta description (max 155 characters) and 5 FAQ questions with short answers for: {$post->post_title}. Content:\n" . wp_strip_all_tags($post->post_content);
        $res = $this->llm->call($prompt);
        if (is_wp_error($res)) {
            return $res;
        }
        $text = $res['text'];
        // First line meta, rest FAQs
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        $meta = array_shift($lines) ?: '';
        update_post_meta($postId, '_aiseo_description', wp_trim_words($meta, 30, ''));
        if (!$post->post_excerpt) {
            wp_update_post(['ID' => $postId, 'post_excerpt' => wp_trim_words($meta, 30, '')]);
        }
        update_post_meta($postId, '_aiseoassistant_faq', implode("\n", $lines));
        return ['meta' => $meta, 'faq' => $lines, 'provider' => $res['provider']];
    }
}

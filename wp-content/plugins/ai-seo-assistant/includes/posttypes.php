<?php

namespace AISEOAssistant;

class PostTypes
{
    public function register(): void
    {
        register_post_type('aiseo_note', [
            'label' => __('AI SEO Notes', 'ai-seo-assistant'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
        ]);

        register_post_type('aiseo_prompt', [
            'label' => __('AI SEO Prompts', 'ai-seo-assistant'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor'],
        ]);
    }

    public function getPrompts(): array
    {
        $posts = get_posts([
            'post_type' => 'aiseo_prompt',
            'post_status' => 'publish',
            'numberposts' => 50,
        ]);
        return array_map(function ($p) {
            return [
                'id' => $p->ID,
                'title' => $p->post_title,
                'body' => $p->post_content,
            ];
        }, $posts);
    }

    public function getNotes(): array
    {
        $posts = get_posts([
            'post_type' => 'aiseo_note',
            'post_status' => 'publish',
            'numberposts' => 50,
        ]);
        return array_map(function ($p) {
            return [
                'id' => $p->ID,
                'title' => $p->post_title,
                'body' => wp_strip_all_tags($p->post_content),
            ];
        }, $posts);
    }
}

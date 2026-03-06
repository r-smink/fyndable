<?php

namespace AISEOAssistant;

class CalendarWorkflow
{
    public function statuses(): array
    {
        return [
            'idea'    => __('Idea', 'ai-seo-assistant'),
            'brief'   => __('Briefed', 'ai-seo-assistant'),
            'draft'   => __('Drafting', 'ai-seo-assistant'),
            'review'  => __('Review', 'ai-seo-assistant'),
            'ready'   => __('Ready', 'ai-seo-assistant'),
            'live'    => __('Published', 'ai-seo-assistant'),
        ];
    }

    public function registerMeta(): void
    {
        register_post_meta('aiseo_calendar', 'status', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'default' => 'idea',
            'sanitize_callback' => function ($v) {
                $allowed = array_keys($this->statuses());
                return in_array($v, $allowed, true) ? $v : 'idea';
            },
        ]);
        register_post_meta('aiseo_calendar', 'due_date', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('aiseo_calendar', 'target_keyword', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_post_meta('aiseo_calendar', 'assignee', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }

    public function upcoming(int $limit = 20): array
    {
        $q = new \WP_Query([
            'post_type' => 'aiseo_calendar',
            'post_status' => 'publish',
            'meta_key' => 'due_date',
            'orderby' => 'meta_value',
            'order' => 'ASC',
            'posts_per_page' => $limit,
        ]);
        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'due' => get_post_meta($p->ID, 'due_date', true),
                'status' => get_post_meta($p->ID, 'status', true),
                'kw' => get_post_meta($p->ID, 'target_keyword', true),
                'assignee' => get_post_meta($p->ID, 'assignee', true),
                'edit' => get_edit_post_link($p->ID, ''),
            ];
        }
        wp_reset_postdata();
        return $items;
    }
}

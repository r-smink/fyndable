<?php

namespace AISEOAssistant;

class CalendarCpt
{
    public function register(): void
    {
        register_post_type('aiseo_calendar', [
            'label' => __('AI SEO Calendar', 'ai-seo-assistant'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'editor', 'custom-fields', 'author'],
        ]);
    }
}

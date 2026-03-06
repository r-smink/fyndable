<?php

namespace AISEOAssistant;

class EditorSidebar
{
    private string $pluginFile;
    private Settings $settings;

    public function __construct(string $pluginFile, Settings $settings)
    {
        $this->pluginFile = $pluginFile;
        $this->settings = $settings;
    }

    public function enqueue(): void
    {
        $handle = 'ai-seo-assistant-editor';
        $assetUrl = plugins_url('assets/editor-sidebar.js', $this->pluginFile);

        wp_enqueue_script(
            $handle,
            $assetUrl,
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/editor-sidebar.js'),
            true
        );

        wp_localize_script($handle, 'AISEOAssistantSettings', [
            'provider' => $this->settings->get('provider', 'api'),
            'tone'     => $this->settings->get('llm_tone', ''),
            'presets'  => array_values(array_filter(array_map('trim', explode("\n", (string)$this->settings->get('llm_presets', ''))))),
            'prompts'  => $this->getPrompts(),
            'notes'    => $this->getNotes(),
        ]);
    }

    private function getPrompts(): array
    {
        $posts = get_posts([
            'post_type' => 'aiseo_prompt',
            'post_status' => 'publish',
            'numberposts' => 50,
        ]);
        return array_map(fn($p) => ['id' => $p->ID, 'title' => $p->post_title, 'body' => $p->post_content], $posts);
    }

    private function getNotes(): array
    {
        $posts = get_posts([
            'post_type' => 'aiseo_note',
            'post_status' => 'publish',
            'numberposts' => 50,
        ]);
        return array_map(fn($p) => ['id' => $p->ID, 'title' => $p->post_title, 'body' => wp_strip_all_tags($p->post_content)], $posts);
    }
}

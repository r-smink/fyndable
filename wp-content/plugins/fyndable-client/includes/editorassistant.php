<?php

namespace SSEOAIClient;

/**
 * Editor Assistant
 *
 * Handles Gutenberg sidebar AI actions via REST API.
 */
class EditorAssistant
{
    private LlmClient $llm;

    public function __construct(LlmClient $llm)
    {
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_script(
            'aiseo-editor-sidebar',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/editor-sidebar.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'],
            SSEO_AI_CLIENT_VERSION,
            true
        );

        $presets = get_option('sseo_ai_prompt_presets', []);
        $tone = get_option('sseo_ai_brand_voice', '');
        $prompts = get_option('sseo_ai_custom_prompts', []);
        $notes = get_option('sseo_ai_knowledge_notes', []);

        wp_localize_script('aiseo-editor-sidebar', 'AISEOAssistantSettings', [
            'presets' => $presets,
            'tone'    => $tone,
            'prompts' => $prompts,
            'notes'   => $notes,
        ]);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/editor-action', [
            'methods' => 'POST',
            'callback' => [$this, 'restEditorAction'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/editor-image', [
            'methods' => 'POST',
            'callback' => [$this, 'restEditorImage'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    public function restEditorAction(\WP_REST_Request $request): array|\WP_Error
    {
        $action = sanitize_text_field($request->get_param('action') ?? 'outline');
        $topic = sanitize_text_field($request->get_param('topic') ?? '');
        $preset = sanitize_textarea_field($request->get_param('preset') ?? '');
        $tone = sanitize_text_field($request->get_param('tone') ?? 'professional');
        $content = wp_kses_post($request->get_param('content') ?? '');
        $selection = wp_kses_post($request->get_param('selection') ?? '');
        $extraContext = sanitize_textarea_field($request->get_param('extra_context') ?? '');

        $contextPart = $extraContext ? "\nExtra context / instructions from user: {$extraContext}\n" : '';

        switch ($action) {
            case 'outline':
                $prompt = "Create a detailed SEO content outline for: {$topic}\nTone: {$tone}\n{$contextPart}\nUse the following preset as guidance: {$preset}\n\nProvide H1, H2, H3 structure with bullet points. Return as plain text.";
                break;
            case 'faq':
                $prompt = "Create an FAQ section for: {$topic}\nTone: {$tone}\n{$contextPart}\nProvide 5-8 questions with concise answers. Use HTML: <h3>Question</h3><p>Answer</p>.";
                break;
            case 'links':
                $prompt = "Suggest 3-5 internal links for content about: {$topic}\n{$contextPart}\nFor each, provide anchor text and target URL description. Use HTML list format.";
                break;
            case 'rewrite':
                $prompt = "Rewrite the following text about '{$topic}':\n\n{$selection}\n\nTone: {$tone}\n{$contextPart}\nMake it SEO-friendly, clear, and engaging. Return only the rewritten text.";
                break;
            case 'improve':
                $prompt = "Improve the following paragraph about '{$topic}':\n\n{$selection}\n\nTone: {$tone}\n{$contextPart}\nFix grammar, improve clarity, and make it more engaging. Return only the improved text.";
                break;
            case 'expand':
                $prompt = "Expand the following paragraph about '{$topic}':\n\n{$selection}\n\nTone: {$tone}\n{$contextPart}\nMake it longer and more detailed (double the length). Keep the same meaning.";
                break;
            case 'cta':
                $prompt = "Write a compelling call-to-action for: {$topic}\nTone: {$tone}\n{$contextPart}\nIt should encourage the reader to take the next step. Keep it under 50 words.";
                break;
            default:
                $prompt = "Help with: {$topic}\nTone: {$tone}\n{$contextPart}\n{$preset}";
        }

        $result = $this->llm->generateText($prompt, [
            'max_tokens' => 2000,
            'use_case' => 'content_generation',
            'track_extra' => [
                'endpoint' => 'editor_action.' . $action,
                'context' => $topic,
            ],
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['text' => $result];
    }

    public function restEditorImage(\WP_REST_Request $request): array|\WP_Error
    {
        $prompt = sanitize_text_field($request->get_param('prompt') ?? '');
        if (!$prompt) {
            return new \WP_Error('no_prompt', __('Prompt is required', 'ai-seo-client'));
        }

        $imageApi = get_option('sseo_ai_client_image_api', []);
        $provider = $imageApi['provider'] ?? '';
        $apiKey = $imageApi['key'] ?? '';
        $model = $imageApi['model'] ?? 'dall-e-3';

        if (empty($provider) || empty($apiKey)) {
            return new \WP_Error('no_image_api', __('Image API not configured in dashboard', 'ai-seo-client'));
        }

        // Delegate to existing image generation logic via LLM or direct API
        // For now, return a placeholder or use a generic approach
        $generator = new AIImageGenerator(new Settings(), $this->llm);
        $url = $generator->generateImageFromPrompt($prompt);

        if (!$url) {
            return new \WP_Error('image_failed', __('Failed to generate image', 'ai-seo-client'));
        }

        return ['url' => $url];
    }
}

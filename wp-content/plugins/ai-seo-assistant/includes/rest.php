<?php

namespace AISEOAssistant;

class RestRoutes
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
        register_rest_route('aiseoassistant/v1', '/outline', [
            'methods'  => 'POST',
            'callback' => [$this, 'outline'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'topic' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'preset' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'tone' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ]);

        register_rest_route('aiseoassistant/v1', '/editor-action', [
            'methods'  => 'POST',
            'callback' => [$this, 'editorAction'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'action' => ['type' => 'string', 'required' => true],
                'topic'  => ['type' => 'string', 'required' => false],
                'preset' => ['type' => 'string', 'required' => false],
                'tone'   => ['type' => 'string', 'required' => false],
                'content'=> ['type' => 'string', 'required' => false],
                'selection' => ['type' => 'string', 'required' => false],
                'prompt_id' => ['type' => 'integer', 'required' => false],
                'notes' => ['type' => 'array', 'required' => false],
            ],
        ]);

        register_rest_route('aiseoassistant/v1', '/editor-image', [
            'methods'  => 'POST',
            'callback' => [$this, 'editorImage'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'prompt' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    public function editorImage(\WP_REST_Request $request)
    {
        $prompt = sanitize_text_field($request->get_param('prompt'));
        $client = new ImageClient($this->settings);
        $res = $client->generate($prompt);
        if (is_wp_error($res)) {
            return $res;
        }
        return $res;
    }

    public function outline(\WP_REST_Request $request)
    {
        $topic = sanitize_text_field($request->get_param('topic'));
        if (!$topic) {
            return new \WP_Error('invalid_topic', __('Topic is required', 'ai-seo-assistant'));
        }

        $prompt = "Schrijf een korte SEO-outline (koppen en bullets) voor het onderwerp: {$topic}. "
            . "Focus op intentie, entiteiten en FAQ. Max 8 bullets.";

        $extraTone = sanitize_text_field($request->get_param('tone') ?? '');
        $preset = sanitize_text_field($request->get_param('preset') ?? '');

        $finalPrompt = $preset ? $preset . "\n\nOnderwerp: {$topic}" : "Onderwerp: {$topic}";
        if ($extraTone) {
            $finalPrompt .= "\nToon: {$extraTone}";
        }
        $finalPrompt .= "\nSchrijf een outline met koppen/bullets en relevante FAQ.";

        $result = $this->llm->call($finalPrompt);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'outline' => $result['text'],
            'provider'=> $result['provider'],
        ];
    }

    public function editorAction(\WP_REST_Request $request)
    {
        $action = sanitize_text_field($request->get_param('action'));
        $topic = sanitize_text_field($request->get_param('topic'));
        $preset = sanitize_text_field($request->get_param('preset'));
        $tone = sanitize_text_field($request->get_param('tone'));
        $content = (string)$request->get_param('content');
        $selection = (string)$request->get_param('selection');
        $promptId = intval($request->get_param('prompt_id'));
        $noteIds = array_map('intval', (array)$request->get_param('notes'));

        $promptTemplate = $promptId ? get_post($promptId) : null;
        $notes = [];
        if (!empty($noteIds)) {
            $notePosts = get_posts(['post_type' => 'aiseo_note', 'post__in' => $noteIds, 'numberposts' => count($noteIds)]);
            foreach ($notePosts as $p) {
                $notes[] = wp_strip_all_tags($p->post_content);
            }
        }

        $prompt = $this->buildEditorPrompt($action, $topic, $preset, $tone, $content, $selection, $promptTemplate, $notes);
        if (is_wp_error($prompt)) {
            return $prompt;
        }

        $result = $this->llm->call($prompt);
        if (is_wp_error($result)) {
            return $result;
        }
        return [
            'text' => $result['text'],
            'provider' => $result['provider'],
        ];
    }

    private function buildEditorPrompt(string $action, string $topic, string $preset, string $tone, string $content, string $selection, ?\WP_Post $promptTemplate, array $notes)
    {
        $toneLine = $tone ? "Toon: {$tone}." : '';
        $kb = $notes ? "\nContext (notes):\n" . implode("\n---\n", $notes) : '';
        $template = $promptTemplate ? $promptTemplate->post_content : '';
        return match ($action) {
            'rewrite' => $selection ? "Herschrijf de volgende tekst SEO-proof. {$toneLine}\n\n{$selection}{$kb}" : new \WP_Error('missing_selection', __('Selectie ontbreekt voor rewrite', 'ai-seo-assistant')),
            'faq'     => "Maak een FAQ-sectie (5-8 vragen) voor het onderwerp '{$topic}'. {$toneLine}{$kb}",
            'links'   => "Stel 5 contextuele interne link-ankers voor voor het onderwerp '{$topic}'. {$toneLine}{$kb}\nGeef output als bulletlijst met anker - suggestie url slug.",
            'improve' => $selection ? "Verbeter stijl/leesbaarheid en SEO van deze alinea. {$toneLine}{$kb}\n\n{$selection}" : new \WP_Error('missing_selection', __('Selectie ontbreekt', 'ai-seo-assistant')),
            'expand'  => $selection ? "Breid onderstaande paragraaf uit met voorbeelden en subkoppen. {$toneLine}{$kb}\n\n{$selection}" : new \WP_Error('missing_selection', __('Selectie ontbreekt', 'ai-seo-assistant')),
            'cta'     => $selection ? "Voeg een duidelijke CTA toe aan deze paragraaf. {$toneLine}{$kb}\n\n{$selection}" : new \WP_Error('missing_selection', __('Selectie ontbreekt', 'ai-seo-assistant')),
            default   => ($template ?: $preset) . "\nOnderwerp: {$topic}\n{$toneLine}{$kb}",
        };
    }
}

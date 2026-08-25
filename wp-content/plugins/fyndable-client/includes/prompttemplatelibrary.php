<?php

namespace SSEOAIClient;

/**
 * Prompt Template Library
 *
 * Central store for reusable AI prompt templates. Business+ feature.
 * Provides default templates for common content-generation tasks and
 * allows users to create, edit, and delete custom templates.
 */
class PromptTemplateLibrary
{
    private const OPTION = 'sseo_ai_prompt_templates';
    private const DEFAULT_TEMPLATES = [
        [
            'id' => 'section_default',
            'name' => 'Default Section',
            'description' => 'Standard article section prompt.',
            'type' => 'section',
            'prompt' => "Write a section for an article about \"{keyword}\".\n\nSection heading: {heading}\n{context}\nRequirements:\n- Write approximately {word_count} words\n- Tone: {tone}\n- Use clear, scannable paragraphs (3-4 sentences each)\n- Include relevant examples or data where appropriate\n- Include the keyword \"{keyword}\" naturally 1-2 times\n- Do NOT include the heading itself\n- Use HTML formatting (paragraphs in <p> tags, lists in <ul>/<li>, bold with <strong>)\n- Do NOT wrap in unnecessary divs or extra HTML\n\nWrite the section content:",
        ],
        [
            'id' => 'title_default',
            'name' => 'Default Title',
            'description' => 'Generate an SEO-optimized blog post title.',
            'type' => 'title',
            'prompt' => "Generate 1 SEO-optimized blog post title for the keyword: \"{keyword}\"\n{serp_titles}\nRequirements:\n- Include the exact keyword or close variant\n- Maximum 60 characters\n- Compelling and click-worthy\n- Return ONLY the title, nothing else.",
        ],
        [
            'id' => 'intro_default',
            'name' => 'Default Introduction',
            'description' => 'Engaging article introduction.',
            'type' => 'intro',
            'prompt' => "Write an engaging introduction (150-200 words) for an article titled: \"{title}\"\nTarget keyword: \"{keyword}\"\nSearch intent: {intent}\nTone: {tone}\n\nRequirements:\n- Hook the reader immediately\n- Introduce the topic and its relevance\n- Naturally include the keyword\n- Briefly preview what the article will cover\n- Use HTML formatting (<p> tags)\n\nWrite the introduction:",
        ],
        [
            'id' => 'conclusion_default',
            'name' => 'Default Conclusion',
            'description' => 'Article conclusion.',
            'type' => 'conclusion',
            'prompt' => "Write a conclusion (100-150 words) for an article titled: \"{title}\"\nTarget keyword: \"{keyword}\"\nTone: {tone}\n\nRequirements:\n- Summarize the key points\n- Include the keyword naturally\n- End with a clear call-to-action or final thought\n- Use HTML formatting (<p> tags)\n\nWrite the conclusion:",
        ],
        [
            'id' => 'headings_default',
            'name' => 'Default Headings',
            'description' => 'Generate H2 headings for an article.',
            'type' => 'headings',
            'prompt' => "Generate 5-7 H2 headings for an article titled: \"{title}\"\nTarget keyword: \"{keyword}\"\n\nRequirements:\n- Cover the topic comprehensively\n- Include the keyword or synonym in 2-3 headings\n- Make headings descriptive and engaging\n- Return ONLY the headings, one per line, prefixed with 'H2: '",
        ],
        [
            'id' => 'meta_default',
            'name' => 'Default Meta Description',
            'description' => 'Generate a meta description.',
            'type' => 'meta',
            'prompt' => "Write a meta description (max 155 characters) for an article titled: \"{title}\"\nInclude the keyword: \"{keyword}\"\nMake it compelling with a call-to-action.\nReturn ONLY the meta description, nothing else.",
        ],
        [
            'id' => 'faq_default',
            'name' => 'Default FAQ',
            'description' => 'Generate FAQ answers.',
            'type' => 'faq',
            'prompt' => "Write short, clear answers (2-3 sentences each) for these FAQ questions about \"{keyword}\".\nTone: {tone}\n\nQuestions:\n{questions}\n\nFormat as HTML with each question as <h3> followed by answer in <p>.\n\nWrite the FAQ:",
        ],
    ];

    private Settings $settings;
    private LicenseValidator $licenseValidator;

    public function __construct(Settings $settings, LicenseValidator $licenseValidator)
    {
        $this->settings = $settings;
        $this->licenseValidator = $licenseValidator;
    }

    public function register(): void
    {
        if (!$this->licenseValidator->isBusinessPlus()) {
            return;
        }

        // Menu is registered by Client class to control placement
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Prompt Templates', 'ai-seo-client'),
            __('Prompt Templates', 'ai-seo-client'),
            'manage_options',
            'ai-seo-prompt-templates',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/prompt-templates', [
            'methods' => 'GET',
            'callback' => [$this, 'restListTemplates'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/prompt-templates', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveTemplate'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'id' => ['type' => 'string', 'required' => false],
                'name' => ['type' => 'string', 'required' => true],
                'type' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string', 'required' => false],
                'prompt' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/prompt-templates/(?P<id>[a-z0-9_\-]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteTemplate'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'id' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/prompt-templates/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'restApplyTemplate'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'id' => ['type' => 'string', 'required' => true],
                'vars' => ['type' => 'object', 'required' => true],
            ],
        ]);
    }

    /**
     * Get a single template by ID, merging defaults with user overrides.
     */
    public function getTemplate(string $id): ?array
    {
        $templates = $this->getTemplates();
        foreach ($templates as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }
        return null;
    }

    /**
     * Get all templates including defaults.
     */
    public function getTemplates(): array
    {
        $stored = get_option(self::OPTION, []);
        $defaults = self::DEFAULT_TEMPLATES;
        $merged = [];

        foreach ($defaults as $d) {
            $merged[$d['id']] = $d;
        }

        foreach ($stored as $t) {
            if (!empty($t['id'])) {
                $merged[$t['id']] = $t;
            }
        }

        return array_values($merged);
    }

    /**
     * Get templates filtered by type.
     */
    public function getTemplatesByType(string $type): array
    {
        return array_values(array_filter($this->getTemplates(), fn($t) => ($t['type'] ?? '') === $type));
    }

    /**
     * Apply a template by replacing placeholders with variables.
     */
    public function applyTemplate(string $id, array $vars): string|null
    {
        $template = $this->getTemplate($id);
        if (!$template) {
            return null;
        }

        $prompt = $template['prompt'];
        foreach ($vars as $key => $value) {
            $prompt = str_replace("{{$key}}", (string) $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Save (create or update) a user template.
     */
    public function saveTemplate(array $data): array
    {
        $templates = array_filter(get_option(self::OPTION, []), fn($t) => !empty($t['id']));

        $id = sanitize_text_field($data['id'] ?? '');
        if (!$id) {
            $id = 'sseo_' . wp_generate_password(8, false, false);
        }

        // Prevent overwriting defaults by reusing default IDs
        $defaultIds = array_column(self::DEFAULT_TEMPLATES, 'id');
        if (in_array($id, $defaultIds, true) && empty($data['is_default'])) {
            $id = 'sseo_' . wp_generate_password(8, false, false);
        }

        $templates[$id] = [
            'id' => $id,
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'type' => sanitize_text_field($data['type'] ?? 'section'),
            'prompt' => wp_kses_post($data['prompt'] ?? ''),
            'is_custom' => true,
        ];

        update_option(self::OPTION, array_values($templates));

        return $templates[$id];
    }

    /**
     * Delete a user template.
     */
    public function deleteTemplate(string $id): bool
    {
        $defaultIds = array_column(self::DEFAULT_TEMPLATES, 'id');
        if (in_array($id, $defaultIds, true)) {
            return false;
        }

        $templates = array_filter(get_option(self::OPTION, []), fn($t) => ($t['id'] ?? '') !== $id);
        update_option(self::OPTION, array_values($templates));
        return true;
    }

    public function restListTemplates(): array
    {
        return $this->getTemplates();
    }

    public function restSaveTemplate(\WP_REST_Request $request): array
    {
        return $this->saveTemplate($request->get_json_params() ?: []);
    }

    public function restDeleteTemplate(\WP_REST_Request $request): array|\WP_Error
    {
        $id = sanitize_text_field($request->get_param('id'));
        if (!$this->deleteTemplate($id)) {
            return new \WP_Error('delete_failed', __('Cannot delete default or missing template.', 'ai-seo-client'), ['status' => 400]);
        }
        return ['success' => true];
    }

    public function restApplyTemplate(\WP_REST_Request $request): array|\WP_Error
    {
        $id = sanitize_text_field($request->get_param('id'));
        $vars = $request->get_param('vars') ?: [];
        $prompt = $this->applyTemplate($id, $vars);

        if ($prompt === null) {
            return new \WP_Error('not_found', __('Template not found', 'ai-seo-client'), ['status' => 404]);
        }

        return ['id' => $id, 'prompt' => $prompt];
    }

    /**
     * Render the prompt templates admin page.
     */
    public function renderPage(): void
    {
        $types = ['section' => __('Section', 'ai-seo-client'), 'title' => __('Title', 'ai-seo-client'), 'intro' => __('Introduction', 'ai-seo-client'), 'conclusion' => __('Conclusion', 'ai-seo-client'), 'headings' => __('Headings', 'ai-seo-client'), 'meta' => __('Meta Description', 'ai-seo-client'), 'faq' => __('FAQ', 'ai-seo-client')];
        ?>
        <div class="wrap aiseo-modern">
            <h1><?php esc_html_e('Prompt Template Library', 'ai-seo-client'); ?></h1>
            <p class="description"><?php esc_html_e('Manage reusable AI prompt templates for content generation.', 'ai-seo-client'); ?></p>

            <div id="sseo-prompt-templates-app" style="margin-top:20px;">
                <p><?php esc_html_e('Loading templates...', 'ai-seo-client'); ?></p>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const types = <?php echo wp_json_encode($types); ?>;

                function loadTemplates() {
                    fetch('<?php echo esc_url(rest_url('sseo-ai/v1/prompt-templates')); ?>', {
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                    })
                    .then(r => r.json())
                    .then(templates => {
                        let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_js(__('Name', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Type', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Description', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Actions', 'ai-seo-client')); ?></th></tr></thead><tbody>';
                        templates.forEach(t => {
                            const isDefault = !t.is_custom;
                            html += '<tr>' +
                                '<td><strong>' + (t.name || t.id) + '</strong>' + (isDefault ? ' <span class="notice notice-success" style="padding:2px 6px;font-size:10px;"><?php echo esc_js(__('Default', 'ai-seo-client')); ?></span>' : '') + '</td>' +
                                '<td>' + (types[t.type] || t.type) + '</td>' +
                                '<td>' + (t.description || '') + '</td>' +
                                '<td>' +
                                    '<button type="button" class="button" onclick="editTemplate(\'' + t.id + '\')"><?php echo esc_js(__('Edit', 'ai-seo-client')); ?></button> ' +
                                    (isDefault ? '' : '<button type="button" class="button" onclick="deleteTemplate(\'' + t.id + '\')"><?php echo esc_js(__('Delete', 'ai-seo-client')); ?></button>') +
                                '</td>' +
                            '</tr>';
                        });
                        html += '</tbody></table>';
                        html += '<h2 style="margin-top:30px;"><?php echo esc_js(__('Add / Edit Template', 'ai-seo-client')); ?></h2>' +
                            '<form id="sseo-prompt-form" style="max-width:700px;">' +
                            '<input type="hidden" id="tpl-id" value="">' +
                            '<p><label><?php echo esc_js(__('Name', 'ai-seo-client')); ?></label><br><input type="text" id="tpl-name" class="regular-text" required></p>' +
                            '<p><label><?php echo esc_js(__('Type', 'ai-seo-client')); ?></label><br><select id="tpl-type">' +
                            Object.keys(types).map(k => '<option value="' + k + '">' + types[k] + '</option>').join('') +
                            '</select></p>' +
                            '<p><label><?php echo esc_js(__('Description', 'ai-seo-client')); ?></label><br><input type="text" id="tpl-desc" class="large-text"></p>' +
                            '<p><label><?php echo esc_js(__('Prompt', 'ai-seo-client')); ?></label><br><textarea id="tpl-prompt" rows="12" class="large-text" required></textarea></p>' +
                            '<p class="description"><?php echo esc_js(__('Use placeholders like {keyword}, {heading}, {tone}, {word_count}, {context}, {title}, {intent}, {questions}, {serp_titles}.', 'ai-seo-client')); ?></p>' +
                            '<p><button type="submit" class="button button-primary"><?php echo esc_js(__('Save Template', 'ai-seo-client')); ?></button></p>' +
                            '</form>';
                        document.getElementById('sseo-prompt-templates-app').innerHTML = html;

                        document.getElementById('sseo-prompt-form').addEventListener('submit', saveTemplate);
                    });
                }

                window.editTemplate = function(id) {
                    fetch('<?php echo esc_url(rest_url('sseo-ai/v1/prompt-templates')); ?>', {
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                    })
                    .then(r => r.json())
                    .then(templates => {
                        const t = templates.find(x => x.id === id);
                        if (t) {
                            document.getElementById('tpl-id').value = t.id;
                            document.getElementById('tpl-name').value = t.name;
                            document.getElementById('tpl-type').value = t.type;
                            document.getElementById('tpl-desc').value = t.description || '';
                            document.getElementById('tpl-prompt').value = t.prompt;
                        }
                    });
                };

                window.deleteTemplate = function(id) {
                    if (!confirm('<?php echo esc_js(__('Delete this template?', 'ai-seo-client')); ?>')) return;
                    fetch('<?php echo esc_url(rest_url('sseo-ai/v1/prompt-templates')); ?>' + '/' + id, {
                        method: 'DELETE',
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                    })
                    .then(r => r.json())
                    .then(() => loadTemplates());
                };

                function saveTemplate(e) {
                    e.preventDefault();
                    const data = {
                        id: document.getElementById('tpl-id').value,
                        name: document.getElementById('tpl-name').value,
                        type: document.getElementById('tpl-type').value,
                        description: document.getElementById('tpl-desc').value,
                        prompt: document.getElementById('tpl-prompt').value,
                    };
                    fetch('<?php echo esc_url(rest_url('sseo-ai/v1/prompt-templates')); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(r => r.json())
                    .then(() => {
                        document.getElementById('sseo-prompt-form').reset();
                        document.getElementById('tpl-id').value = '';
                        loadTemplates();
                    });
                }

                loadTemplates();
            });
            </script>
        </div>
        <?php
    }
}

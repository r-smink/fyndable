<?php

namespace SSEOAIClient;

/**
 * Programmatic SEO Templates
 *
 * Template-led page generation at scale. Users define a template with variables
 * and a dataset (CSV or manual), then generate hundreds of SEO-optimized pages
 * with unique content for each row.
 *
 * Comparable to Webflow CMS, Duda, programmatic SEO tools.
 */
class ProgrammaticSEO
{
    private LlmClient $llm;
    private Settings $settings;

    public function __construct(LlmClient $llm, Settings $settings)
    {
        $this->llm = $llm;
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'addMenu']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Programmatic SEO', 'ai-seo-client'),
            __('Programmatic SEO', 'ai-seo-client'),
            'manage_options',
            'ai-seo-programmatic',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/programmatic/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTemplates'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/programmatic/templates', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveTemplate'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/programmatic/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerate'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/programmatic/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'restPreview'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    private const TEMPLATES_KEY = 'sseo_ai_programmatic_templates';

    public function getTemplates(): array
    {
        $templates = get_option(self::TEMPLATES_KEY, []);
        return is_array($templates) ? $templates : [];
    }

    private function saveTemplates(array $templates): void
    {
        update_option(self::TEMPLATES_KEY, $templates);
    }

    /**
     * Generate content for a single dataset row using a template.
     */
    public function generatePage(array $template, array $row): array|\WP_Error
    {
        // Replace variables in title, meta, and content template
        $title = $this->replaceVars($template['title_template'] ?? '', $row);
        $metaDesc = $this->replaceVars($template['meta_template'] ?? '', $row);
        $keyword = $this->replaceVars($template['keyword_template'] ?? '', $row);
        $contentTemplate = $template['content_template'] ?? '';

        // If AI generation is enabled, use LLM to expand the template
        if (!empty($template['use_ai']) && $this->llm->isAvailable()) {
            $prompt = $this->buildAiPrompt($template, $row, $title, $keyword);
            $response = $this->llm->generateText($prompt, [
                'use_case' => 'content_generation',
                'max_tokens' => (int)($template['word_count'] ?? 1500) * 2,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $content = $response;
            // Try to extract JSON if the LLM returned structured data
            $data = json_decode(trim($response), true);
            if (is_array($data) && isset($data['content'])) {
                $content = $data['content'];
                if (!empty($data['meta_description'])) $metaDesc = $data['meta_description'];
            }
        } else {
            // Simple variable replacement without AI
            $content = $this->replaceVars($contentTemplate, $row);
        }

        return [
            'title' => $title,
            'content' => $content,
            'meta_description' => $metaDesc,
            'keyword' => $keyword,
        ];
    }

    private function replaceVars(string $template, array $row): string
    {
        $result = $template;
        foreach ($row as $key => $value) {
            if (is_array($value)) continue;
            $result = str_replace('{' . $key . '}', (string)$value, $result);
        }
        return $result;
    }

    private function buildAiPrompt(array $template, array $row, string $title, string $keyword): string
    {
        $varsStr = '';
        foreach ($row as $k => $v) {
            $varsStr .= "- {$k}: {$v}\n";
        }

        $wordCount = (int)($template['word_count'] ?? 1500);
        $tone = $template['tone'] ?? 'professional';
        $sections = $template['sections'] ?? '';
        $contentTemplate = $template['content_template'] ?? '';

        return <<<PROMPT
Generate a unique, SEO-optimized article for the following page:

Title: {$title}
Target Keyword: {$keyword}
Word Count: {$wordCount}
Tone: {$tone}

Page Data:
{$varsStr}

Content Structure Requirements:
{$sections}

Template Outline (use as a guide, but generate unique content):
{$contentTemplate}

Requirements:
1. Write unique, valuable content â€” do NOT just fill in blanks
2. Include the target keyword naturally (1-2% density)
3. Use proper H2/H3 headings
4. Include an introduction and conclusion
5. Add a FAQ section if relevant
6. Make the content specific to the page data above

Return a JSON response:
{
    "content": "Full HTML content",
    "meta_description": "150-160 char meta description",
    "tags": ["tag1", "tag2"]
}

Return ONLY the JSON.
PROMPT;
    }

    /**
     * Generate pages for an entire dataset and create WordPress posts.
     */
    public function generateBatch(array $template, array $dataset, bool $createDrafts = true): array
    {
        $results = [];
        foreach ($dataset as $row) {
            $page = $this->generatePage($template, $row);
            if (is_wp_error($page)) {
                $results[] = ['success' => false, 'error' => $page->get_error_message(), 'row' => $row];
                continue;
            }

            $postId = null;
            if ($createDrafts) {
                $postData = [
                    'post_title' => $page['title'],
                    'post_content' => $page['content'],
                    'post_type' => 'post',
                    'post_status' => 'draft',
                    'post_author' => get_current_user_id(),
                    'meta_input' => [
                        '_sseo_ai_title' => $page['title'],
                        '_sseo_ai_description' => $page['meta_description'],
                        '_sseo_ai_focus_keyphrase' => $page['keyword'],
                        '_sseo_ai_generated' => '1',
                        '_sseo_ai_programmatic' => '1',
                    ],
                ];
                $postId = wp_insert_post($postData);
                if (is_wp_error($postId)) {
                    $results[] = ['success' => false, 'error' => $postId->get_error_message(), 'row' => $row];
                    continue;
                }
            }

            $results[] = [
                'success' => true,
                'title' => $page['title'],
                'post_id' => $postId,
                'edit_url' => $postId ? get_edit_post_link($postId, '') : '',
            ];
        }
        return $results;
    }

    // REST: Get templates
    public function restGetTemplates(): array
    {
        return ['templates' => $this->getTemplates()];
    }

    // REST: Save template
    public function restSaveTemplate(\WP_REST_Request $request): array
    {
        $params = $request->get_params();
        $templates = $this->getTemplates();
        $id = sanitize_text_field($params['id'] ?? '');
        if (empty($id)) {
            $id = 'tpl_' . time();
        }

        $templates[$id] = [
            'id' => $id,
            'name' => sanitize_text_field($params['name'] ?? 'Untitled'),
            'title_template' => sanitize_text_field($params['title_template'] ?? ''),
            'meta_template' => sanitize_text_field($params['meta_template'] ?? ''),
            'keyword_template' => sanitize_text_field($params['keyword_template'] ?? ''),
            'content_template' => sanitize_textarea_field($params['content_template'] ?? ''),
            'sections' => sanitize_textarea_field($params['sections'] ?? ''),
            'word_count' => (int)($params['word_count'] ?? 1500),
            'tone' => sanitize_text_field($params['tone'] ?? 'professional'),
            'use_ai' => (bool)($params['use_ai'] ?? true),
        ];

        $this->saveTemplates($templates);
        return ['success' => true, 'id' => $id];
    }

    // REST: Preview generation for a single row
    public function restPreview(\WP_REST_Request $request): array|\WP_Error
    {
        $template = $request->get_param('template');
        $row = $request->get_param('row');
        if (empty($template) || empty($row)) {
            return new \WP_Error('missing', __('Template and row required', 'ai-seo-client'), ['status' => 400]);
        }
        return $this->generatePage($template, $row);
    }

    // REST: Generate batch
    public function restGenerate(\WP_REST_Request $request): array|\WP_Error
    {
        $templateId = sanitize_text_field($request->get_param('template_id'));
        $dataset = $request->get_param('dataset');
        $createDrafts = (bool)($request->get_param('create_drafts') ?? true);

        $templates = $this->getTemplates();
        if (!isset($templates[$templateId])) {
            return new \WP_Error('not_found', __('Template not found', 'ai-seo-client'), ['status' => 404]);
        }
        if (empty($dataset) || !is_array($dataset)) {
            return new \WP_Error('missing', __('Dataset required', 'ai-seo-client'), ['status' => 400]);
        }

        return ['results' => $this->generateBatch($templates[$templateId], $dataset, $createDrafts)];
    }

    public function renderPage(): void
    {
        $templates = $this->getTemplates();
        ?>
        <style>
            .pseo-wrap { max-width: 1000px; margin: 20px auto; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .pseo-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 20px; }
            .pseo-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 20px 30px; border-radius: 12px 12px 0 0; margin: -30px -30px 20px -30px; }
            .pseo-header h1 { margin: 0; font-size: 22px; }
            .pseo-header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 13px; }
            .pseo-field { margin-bottom: 15px; }
            .pseo-field label { font-weight: 600; display: block; margin-bottom: 4px; }
            .pseo-field input, .pseo-field textarea, .pseo-field select { width: 100%; }
            .pseo-var { background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 12px; }
            .pseo-tab { padding: 10px 20px; cursor: pointer; border: none; background: #f1f5f9; border-radius: 8px 8px 0 0; font-weight: 600; }
            .pseo-tab.active { background: #fff; border: 1px solid #e2e8f0; border-bottom: none; }
        </style>
        <div class="wrap pseo-wrap">
            <div class="pseo-card">
                <div class="pseo-header">
                    <h1>âš¡ <?php esc_html_e('Programmatic SEO', 'ai-seo-client'); ?></h1>
                    <p><?php esc_html_e('Generate hundreds of unique SEO-optimized pages from templates and datasets.', 'ai-seo-client'); ?></p>
                </div>

                <div style="display:flex;gap:10px;margin-bottom:20px;">
                    <button class="pseo-tab active" id="pseo-tab-template"><?php esc_html_e('Template', 'ai-seo-client'); ?></button>
                    <button class="pseo-tab" id="pseo-tab-dataset"><?php esc_html_e('Dataset', 'ai-seo-client'); ?></button>
                    <button class="pseo-tab" id="pseo-tab-generate"><?php esc_html_e('Generate', 'ai-seo-client'); ?></button>
                </div>

                <!-- Template Tab -->
                <div id="pseo-panel-template">
                    <div class="pseo-field">
                        <label><?php esc_html_e('Template Name', 'ai-seo-client'); ?></label>
                        <input type="text" id="pseo-tpl-name" placeholder="e.g. Service Location Pages" />
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div class="pseo-field">
                            <label><?php esc_html_e('Title Template', 'ai-seo-client'); ?> <span class="pseo-var">{location}</span> <span class="pseo-var">{service}</span></label>
                            <input type="text" id="pseo-tpl-title" placeholder="{service} in {location} | Expert Guide 2024" />
                        </div>
                        <div class="pseo-field">
                            <label><?php esc_html_e('Keyword Template', 'ai-seo-client'); ?></label>
                            <input type="text" id="pseo-tpl-keyword" placeholder="{service} {location}" />
                        </div>
                    </div>
                    <div class="pseo-field">
                        <label><?php esc_html_e('Meta Description Template', 'ai-seo-client'); ?></label>
                        <input type="text" id="pseo-tpl-meta" placeholder="Looking for {service} in {location}? Compare top providers..." />
                    </div>
                    <div class="pseo-field">
                        <label><?php esc_html_e('Content Sections (one per line)', 'ai-seo-client'); ?></label>
                        <textarea id="pseo-tpl-sections" rows="4" placeholder="Introduction to {service} in {location}&#10;Top {service} providers in {location}&#10;How to choose the right {service}&#10;Pricing guide for {service} in {location}&#10;FAQ about {service} in {location}"></textarea>
                    </div>
                    <div class="pseo-field">
                        <label><?php esc_html_e('Content Template (HTML outline with variables)', 'ai-seo-client'); ?></label>
                        <textarea id="pseo-tpl-content" rows="6" placeholder="<h2>Best {service} in {location}</h2>&#10;<p>{location} has many options for {service}...</p>"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;">
                        <div class="pseo-field">
                            <label><?php esc_html_e('Word Count', 'ai-seo-client'); ?></label>
                            <input type="number" id="pseo-tpl-wordcount" value="1500" />
                        </div>
                        <div class="pseo-field">
                            <label><?php esc_html_e('Tone', 'ai-seo-client'); ?></label>
                            <select id="pseo-tpl-tone">
                                <option value="professional">Professional</option>
                                <option value="casual">Casual</option>
                                <option value="friendly">Friendly</option>
                                <option value="authoritative">Authoritative</option>
                            </select>
                        </div>
                        <div class="pseo-field">
                            <label><?php esc_html_e('AI Generation', 'ai-seo-client'); ?></label>
                            <select id="pseo-tpl-useai">
                                <option value="1">Use AI (unique content)</option>
                                <option value="0">Variable replacement only</option>
                            </select>
                        </div>
                    </div>
                    <button class="button button-primary" id="pseo-save-tpl"><?php esc_html_e('Save Template', 'ai-seo-client'); ?></button>
                    <select id="pseo-load-tpl" style="margin-left:10px;">
                        <option value=""><?php esc_html_e('â€” Load Template â€”', 'ai-seo-client'); ?></option>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo esc_attr($tpl['id']); ?>"><?php echo esc_html($tpl['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dataset Tab -->
                <div id="pseo-panel-dataset" style="display:none;">
                    <div class="pseo-field">
                        <label><?php esc_html_e('Dataset (JSON array of objects)', 'ai-seo-client'); ?></label>
                        <textarea id="pseo-dataset" rows="10" style="font-family:monospace;font-size:12px;" placeholder='[{"location":"Amsterdam","service":"plumber"},{"location":"Rotterdam","service":"plumber"}]'></textarea>
                    </div>
                    <p class="description"><?php esc_html_e('Each object becomes a unique page. Keys must match variables in your template (e.g. {location}).', 'ai-seo-client'); ?></p>
                    <button class="button" id="pseo-preview-btn"><?php esc_html_e('Preview First Page', 'ai-seo-client'); ?></button>
                    <div id="pseo-preview-result" style="margin-top:15px;"></div>
                </div>

                <!-- Generate Tab -->
                <div id="pseo-panel-generate" style="display:none;">
                    <p><?php esc_html_e('Generate WordPress draft posts for all rows in your dataset using the selected template.', 'ai-seo-client'); ?></p>
                    <button class="button button-primary button-large" id="pseo-generate-btn"><?php esc_html_e('Generate All Pages', 'ai-seo-client'); ?></button>
                    <div id="pseo-generate-result" style="margin-top:20px;"></div>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var currentTemplate = null;

            // Tab switching
            $('.pseo-tab').on('click', function() {
                $('.pseo-tab').removeClass('active');
                $(this).addClass('active');
                var tab = $(this).attr('id').replace('pseo-tab-', '');
                $('[id^="pseo-panel-"]').hide();
                $('#pseo-panel-' + tab).show();
            });

            // Save template
            $('#pseo-save-tpl').on('click', function() {
                var data = {
                    name: $('#pseo-tpl-name').val(),
                    title_template: $('#pseo-tpl-title').val(),
                    keyword_template: $('#pseo-tpl-keyword').val(),
                    meta_template: $('#pseo-tpl-meta').val(),
                    sections: $('#pseo-tpl-sections').val(),
                    content_template: $('#pseo-tpl-content').val(),
                    word_count: $('#pseo-tpl-wordcount').val(),
                    tone: $('#pseo-tpl-tone').val(),
                    use_ai: $('#pseo-tpl-useai').val(),
                };
                wp.apiFetch({
                    path: '/sseo-ai/v1/programmatic/templates',
                    method: 'POST',
                    data: data
                }).then(function(res) {
                    currentTemplate = res.id;
                    alert('<?php echo esc_js(__("Template saved!", "ai-seo-client")); ?>');
                    location.reload();
                });
            });

            // Load template
            $('#pseo-load-tpl').on('change', function() {
                var id = $(this).val();
                if (!id) return;
                wp.apiFetch({ path: '/sseo-ai/v1/programmatic/templates' }).then(function(res) {
                    var tpl = res.templates[id];
                    if (!tpl) return;
                    currentTemplate = id;
                    $('#pseo-tpl-name').val(tpl.name);
                    $('#pseo-tpl-title').val(tpl.title_template);
                    $('#pseo-tpl-keyword').val(tpl.keyword_template);
                    $('#pseo-tpl-meta').val(tpl.meta_template);
                    $('#pseo-tpl-sections').val(tpl.sections);
                    $('#pseo-tpl-content').val(tpl.content_template);
                    $('#pseo-tpl-wordcount').val(tpl.word_count);
                    $('#pseo-tpl-tone').val(tpl.tone);
                    $('#pseo-tpl-useai').val(tpl.use_ai ? '1' : '0');
                });
            });

            // Preview
            $('#pseo-preview-btn').on('click', function() {
                var dataset = $('#pseo-dataset').val();
                if (!dataset) { alert('<?php echo esc_js(__("Add dataset first", "ai-seo-client")); ?>'); return; }
                var rows;
                try { rows = JSON.parse(dataset); } catch(e) { alert('Invalid JSON'); return; }
                if (!rows.length) { alert('Empty dataset'); return; }

                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__("Generating preview...", "ai-seo-client")); ?>');
                wp.apiFetch({
                    path: '/sseo-ai/v1/programmatic/preview',
                    method: 'POST',
                    data: { template: buildTemplateObj(), row: rows[0] }
                }).then(function(res) {
                    var html = '<div style="background:#f8fafc;border-radius:8px;padding:15px;">';
                    html += '<h4>' + res.title + '</h4>';
                    html += '<p style="font-size:13px;color:#64748b;"><strong>Keyword:</strong> ' + res.keyword + '</p>';
                    html += '<p style="font-size:13px;color:#64748b;"><strong>Meta:</strong> ' + res.meta_description + '</p>';
                    html += '<div style="max-height:400px;overflow-y:auto;border:1px solid #e2e8f0;padding:10px;margin-top:10px;">' + res.content + '</div>';
                    html += '</div>';
                    $('#pseo-preview-result').html(html);
                }).catch(function(err) {
                    alert(err.message || 'Preview failed');
                }).finally(function() {
                    btn.prop('disabled', false).text('<?php echo esc_js(__("Preview First Page", "ai-seo-client")); ?>');
                });
            });

            // Generate all
            $('#pseo-generate-btn').on('click', function() {
                var dataset = $('#pseo-dataset').val();
                if (!dataset) { alert('<?php echo esc_js(__("Add dataset in the Dataset tab first", "ai-seo-client")); ?>'); return; }
                if (!currentTemplate) { alert('<?php echo esc_js(__("Save or load a template first", "ai-seo-client")); ?>'); return; }
                var rows;
                try { rows = JSON.parse(dataset); } catch(e) { alert('Invalid JSON'); return; }

                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__("Generating...", "ai-seo-client")); ?> (' + rows.length + ' pages)');
                wp.apiFetch({
                    path: '/sseo-ai/v1/programmatic/generate',
                    method: 'POST',
                    data: { template_id: currentTemplate, dataset: rows, create_drafts: true }
                }).then(function(res) {
                    var success = 0, failed = 0;
                    var html = '<table class="wp-list-table widefat striped"><thead><tr><th>Title</th><th>Status</th><th>Link</th></tr></thead><tbody>';
                    res.results.forEach(function(r) {
                        if (r.success) {
                            success++;
                            html += '<tr><td>' + r.title + '</td><td style="color:#16a34a;">âœ“ Draft created</td><td>' + (r.edit_url ? '<a href="' + r.edit_url + '">Edit</a>' : '') + '</td></tr>';
                        } else {
                            failed++;
                            html += '<tr><td>â€”</td><td style="color:#dc2626;">âœ— ' + r.error + '</td><td></td></tr>';
                        }
                    });
                    html += '</tbody></table>';
                    html += '<p style="margin-top:10px;"><strong>' + success + '</strong> created, <strong>' + failed + '</strong> failed.</p>';
                    $('#pseo-generate-result').html(html);
                }).catch(function(err) {
                    alert(err.message || 'Generation failed');
                }).finally(function() {
                    btn.prop('disabled', false).text('<?php echo esc_js(__("Generate All Pages", "ai-seo-client")); ?>');
                });
            });

            function buildTemplateObj() {
                return {
                    title_template: $('#pseo-tpl-title').val(),
                    keyword_template: $('#pseo-tpl-keyword').val(),
                    meta_template: $('#pseo-tpl-meta').val(),
                    sections: $('#pseo-tpl-sections').val(),
                    content_template: $('#pseo-tpl-content').val(),
                    word_count: parseInt($('#pseo-tpl-wordcount').val(), 10) || 1500,
                    tone: $('#pseo-tpl-tone').val(),
                    use_ai: $('#pseo-tpl-useai').val() === '1'
                };
            }
        });
        </script>
        <?php
    }
}

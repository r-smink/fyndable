<?php

namespace SSEOAIClient;

/**
 * Brand Voice Engine
 *
 * Stores persistent brand voice settings (tone, style, terminology, forbidden words,
 * audience, examples) and injects them into all LLM content generation prompts.
 *
 * Comparable to Jasper Brand Voice, Copy.ai Brand Voice.
 */
class BrandVoice
{
    private const SETTINGS_KEY = 'sseo_ai_brand_voice';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'addMenu']);
        add_filter('sseo_ai_brand_voice_prompt', [$this, 'getVoicePrompt'], 10, 0);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Brand Voice', 'ai-seo-client'),
            __('Brand Voice', 'ai-seo-client'),
            'manage_options',
            'ai-seo-brand-voice',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/brand-voice', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSettings'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/brand-voice', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveSettings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/brand-voice/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyzeContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Get brand voice settings with defaults.
     */
    public function getSettings(): array
    {
        $defaults = [
            'enabled' => false,
            'brand_name' => '',
            'tone' => 'professional',
            'style' => 'informative',
            'audience' => '',
            'voice_description' => '',
            'preferred_terms' => '',
            'forbidden_terms' => '',
            'example_good' => '',
            'example_bad' => '',
            'language' => 'en',
        ];

        $saved = get_option(self::SETTINGS_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($defaults, $saved);
    }

    /**
     * Build the brand voice prompt snippet to inject into LLM prompts.
     */
    public function getVoicePrompt(): string
    {
        $settings = $this->getSettings();

        if (empty($settings['enabled'])) {
            return '';
        }

        $parts = [];

        if (!empty($settings['brand_name'])) {
            $parts[] = "Brand: {$settings['brand_name']}";
        }

        if (!empty($settings['tone'])) {
            $parts[] = "Tone: {$settings['tone']}";
        }

        if (!empty($settings['style'])) {
            $parts[] = "Writing style: {$settings['style']}";
        }

        if (!empty($settings['audience'])) {
            $parts[] = "Target audience: {$settings['audience']}";
        }

        if (!empty($settings['voice_description'])) {
            $parts[] = "Voice guidelines: {$settings['voice_description']}";
        }

        if (!empty($settings['preferred_terms'])) {
            $parts[] = "Use these preferred terms: {$settings['preferred_terms']}";
        }

        if (!empty($settings['forbidden_terms'])) {
            $parts[] = "Avoid these terms/words: {$settings['forbidden_terms']}";
        }

        if (!empty($settings['example_good'])) {
            $parts[] = "Example of good writing style:\n{$settings['example_good']}";
        }

        if (empty($parts)) {
            return '';
        }

        return "BRAND VOICE REQUIREMENTS (follow strictly):\n" . implode("\n", $parts) . "\n\n";
    }

    /**
     * Save brand voice settings.
     */
    private function saveSettings(array $settings): array
    {
        $sanitized = [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'brand_name' => sanitize_text_field($settings['brand_name'] ?? ''),
            'tone' => sanitize_text_field($settings['tone'] ?? 'professional'),
            'style' => sanitize_text_field($settings['style'] ?? 'informative'),
            'audience' => sanitize_text_field($settings['audience'] ?? ''),
            'voice_description' => sanitize_textarea_field($settings['voice_description'] ?? ''),
            'preferred_terms' => sanitize_textarea_field($settings['preferred_terms'] ?? ''),
            'forbidden_terms' => sanitize_textarea_field($settings['forbidden_terms'] ?? ''),
            'example_good' => sanitize_textarea_field($settings['example_good'] ?? ''),
            'example_bad' => sanitize_textarea_field($settings['example_bad'] ?? ''),
            'language' => sanitize_text_field($settings['language'] ?? 'en'),
        ];

        update_option(self::SETTINGS_KEY, $sanitized);
        return $sanitized;
    }

    /**
     * Analyze existing content to extract brand voice using AI.
     */
    public function analyzeContentFromPosts(array $postIds, LlmClient $llm): array|\WP_Error
    {
        $contents = [];
        foreach ($postIds as $pid) {
            $post = get_post((int) $pid);
            if ($post) {
                $contents[] = $post->post_content;
            }
        }

        if (empty($contents)) {
            return new \WP_Error('no_content', __('No content found to analyze', 'ai-seo-client'), ['status' => 400]);
        }

        $sampleText = implode("\n\n---\n\n", array_map('wp_strip_all_tags', $contents));
        $sampleText = substr($sampleText, 0, 8000);

        $prompt = "Analyze the following content samples and extract the brand voice profile. Return JSON with these fields:
{
    \"tone\": \"e.g. professional, casual, authoritative, friendly\",
    \"style\": \"e.g. informative, conversational, technical, storytelling\",
    \"audience\": \"e.g. small business owners, developers, marketers\",
    \"voice_description\": \"2-3 sentence description of the voice characteristics\",
    \"preferred_terms\": \"comma-separated list of terms frequently used\",
    \"forbidden_terms\": \"comma-separated list of terms to avoid (if any)\"
}

Content samples:
{$sampleText}

Return ONLY the JSON.";

        $response = $llm->generateText($prompt, ['use_case' => 'analysis']);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(trim($response), true);
        if (!is_array($data)) {
            return new \WP_Error('parse_error', __('Could not parse AI response', 'ai-seo-client'), ['status' => 500]);
        }

        return $data;
    }

    // REST: Get settings
    public function restGetSettings(): array
    {
        return $this->getSettings();
    }

    // REST: Save settings
    public function restSaveSettings(\WP_REST_Request $request): array
    {
        $settings = $request->get_params();
        $saved = $this->saveSettings($settings);
        return ['success' => true, 'settings' => $saved];
    }

    // REST: Analyze content
    public function restAnalyzeContent(\WP_REST_Request $request): array|\WP_Error
    {
        $postIds = $request->get_param('post_ids') ?? [];
        if (empty($postIds) || !is_array($postIds)) {
            return new \WP_Error('missing_posts', __('Post IDs required', 'ai-seo-client'), ['status' => 400]);
        }

        $llm = new LlmClient($this->settings, new HealthLogger(new AlertNotifier()), new DashboardAPI($this->settings));
        $result = $this->analyzeContentFromPosts($postIds, $llm);

        if (is_wp_error($result)) {
            return $result;
        }

        return ['success' => true, 'analysis' => $result];
    }

    /**
     * Render admin page
     */
    public function renderPage(): void
    {
        $settings = $this->getSettings();
        ?>
        <style>
            .bv-wrap { max-width: 800px; margin: 20px auto; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .bv-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 20px; }
            .bv-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 20px 30px; border-radius: 12px 12px 0 0; margin: -30px -30px 20px -30px; }
            .bv-header h1 { margin: 0; font-size: 22px; }
            .bv-header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 13px; }
            .bv-field { margin-bottom: 20px; }
            .bv-field label { font-weight: 600; display: block; margin-bottom: 6px; }
            .bv-field input, .bv-field textarea, .bv-field select { width: 100%; }
            .bv-toggle { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
            .bv-toggle input[type="checkbox"] { width: 20px; height: 20px; }
            .bv-preview { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; font-family: monospace; font-size: 12px; white-space: pre-wrap; }
            .bv-saved { display: none; padding: 10px 15px; background: #dcfce7; border-radius: 8px; color: #166534; margin-bottom: 15px; }
        </style>
        <div class="wrap bv-wrap">
            <div class="bv-card">
                <div class="bv-header">
                    <h1>ðŸŽ™ï¸ <?php esc_html_e('Brand Voice Engine', 'ai-seo-client'); ?></h1>
                    <p><?php esc_html_e('Define your brand voice once â€” all AI-generated content will follow it automatically.', 'ai-seo-client'); ?></p>
                </div>

                <div id="bv-saved-msg" class="bv-saved">âœ… <?php esc_html_e('Settings saved!', 'ai-seo-client'); ?></div>

                <div class="bv-toggle">
                    <input type="checkbox" id="bv-enabled" <?php checked($settings['enabled']); ?>>
                    <label for="bv-enabled" style="font-weight:600;"><?php esc_html_e('Enable Brand Voice injection', 'ai-seo-client'); ?></label>
                </div>

                <div class="bv-field">
                    <label><?php esc_html_e('Brand Name', 'ai-seo-client'); ?></label>
                    <input type="text" id="bv-brand-name" value="<?php echo esc_attr($settings['brand_name']); ?>" placeholder="e.g. Fyndable">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="bv-field">
                        <label><?php esc_html_e('Tone', 'ai-seo-client'); ?></label>
                        <select id="bv-tone">
                            <option value="professional" <?php selected($settings['tone'], 'professional'); ?>><?php esc_html_e('Professional', 'ai-seo-client'); ?></option>
                            <option value="casual" <?php selected($settings['tone'], 'casual'); ?>><?php esc_html_e('Casual', 'ai-seo-client'); ?></option>
                            <option value="authoritative" <?php selected($settings['tone'], 'authoritative'); ?>><?php esc_html_e('Authoritative', 'ai-seo-client'); ?></option>
                            <option value="friendly" <?php selected($settings['tone'], 'friendly'); ?>><?php esc_html_e('Friendly', 'ai-seo-client'); ?></option>
                            <option value="technical" <?php selected($settings['tone'], 'technical'); ?>><?php esc_html_e('Technical', 'ai-seo-client'); ?></option>
                            <option value="conversational" <?php selected($settings['tone'], 'conversational'); ?>><?php esc_html_e('Conversational', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="bv-field">
                        <label><?php esc_html_e('Writing Style', 'ai-seo-client'); ?></label>
                        <select id="bv-style">
                            <option value="informative" <?php selected($settings['style'], 'informative'); ?>><?php esc_html_e('Informative', 'ai-seo-client'); ?></option>
                            <option value="storytelling" <?php selected($settings['style'], 'storytelling'); ?>><?php esc_html_e('Storytelling', 'ai-seo-client'); ?></option>
                            <option value="data-driven" <?php selected($settings['style'], 'data-driven'); ?>><?php esc_html_e('Data-driven', 'ai-seo-client'); ?></option>
                            <option value="how-to" <?php selected($settings['style'], 'how-to'); ?>><?php esc_html_e('How-to / Tutorial', 'ai-seo-client'); ?></option>
                            <option value="opinion" <?php selected($settings['style'], 'opinion'); ?>><?php esc_html_e('Opinion / Thought Leadership', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                </div>

                <div class="bv-field">
                    <label><?php esc_html_e('Target Audience', 'ai-seo-client'); ?></label>
                    <input type="text" id="bv-audience" value="<?php echo esc_attr($settings['audience']); ?>" placeholder="e.g. Small business owners looking for SEO solutions">
                </div>

                <div class="bv-field">
                    <label><?php esc_html_e('Voice Description', 'ai-seo-client'); ?></label>
                    <textarea id="bv-voice-desc" rows="3" placeholder="e.g. We write with confidence but without jargon. We're approachable experts who make complex topics simple."><?php echo esc_textarea($settings['voice_description']); ?></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div class="bv-field">
                        <label><?php esc_html_e('Preferred Terms (comma-separated)', 'ai-seo-client'); ?></label>
                        <textarea id="bv-preferred" rows="3" placeholder="e.g. growth, ROI, data-driven, scalable"><?php echo esc_textarea($settings['preferred_terms']); ?></textarea>
                    </div>
                    <div class="bv-field">
                        <label><?php esc_html_e('Forbidden Terms (comma-separated)', 'ai-seo-client'); ?></label>
                        <textarea id="bv-forbidden" rows="3" placeholder="e.g. cheap, hack, gimmick"><?php echo esc_textarea($settings['forbidden_terms']); ?></textarea>
                    </div>
                </div>

                <div class="bv-field">
                    <label><?php esc_html_e('Example of Good Writing', 'ai-seo-client'); ?></label>
                    <textarea id="bv-example-good" rows="5" placeholder="Paste a paragraph that represents your ideal brand voice..."><?php echo esc_textarea($settings['example_good']); ?></textarea>
                </div>

                <button class="button button-primary" id="bv-save"><?php esc_html_e('Save Brand Voice', 'ai-seo-client'); ?></button>
                <button class="button" id="bv-preview-btn"><?php esc_html_e('Preview Prompt Snippet', 'ai-seo-client'); ?></button>

                <div id="bv-preview-wrap" style="display:none;margin-top:20px;">
                    <h3><?php esc_html_e('Prompt Snippet (injected into all AI content generation)', 'ai-seo-client'); ?></h3>
                    <div class="bv-preview" id="bv-preview"></div>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#bv-save').on('click', function() {
                var data = {
                    enabled: $('#bv-enabled').is(':checked'),
                    brand_name: $('#bv-brand-name').val(),
                    tone: $('#bv-tone').val(),
                    style: $('#bv-style').val(),
                    audience: $('#bv-audience').val(),
                    voice_description: $('#bv-voice-desc').val(),
                    preferred_terms: $('#bv-preferred').val(),
                    forbidden_terms: $('#bv-forbidden').val(),
                    example_good: $('#bv-example-good').val(),
                };
                wp.apiFetch({
                    path: '/sseo-ai/v1/brand-voice',
                    method: 'POST',
                    data: data
                }).then(function() {
                    $('#bv-saved-msg').show().delay(3000).fadeOut();
                }).catch(function(err) {
                    alert(err.message || 'Save failed');
                });
            });

            $('#bv-preview-btn').on('click', function() {
                wp.apiFetch({ path: '/sseo-ai/v1/brand-voice' }).then(function(s) {
                    var parts = [];
                    if (!s.enabled) {
                        $('#bv-preview').text('(Brand Voice is disabled â€” no prompt snippet injected)');
                        $('#bv-preview-wrap').show();
                        return;
                    }
                    var prompt = 'BRAND VOICE REQUIREMENTS (follow strictly):\n';
                    if (s.brand_name) prompt += 'Brand: ' + s.brand_name + '\n';
                    if (s.tone) prompt += 'Tone: ' + s.tone + '\n';
                    if (s.style) prompt += 'Writing style: ' + s.style + '\n';
                    if (s.audience) prompt += 'Target audience: ' + s.audience + '\n';
                    if (s.voice_description) prompt += 'Voice guidelines: ' + s.voice_description + '\n';
                    if (s.preferred_terms) prompt += 'Use these preferred terms: ' + s.preferred_terms + '\n';
                    if (s.forbidden_terms) prompt += 'Avoid these terms/words: ' + s.forbidden_terms + '\n';
                    if (s.example_good) prompt += 'Example of good writing style:\n' + s.example_good + '\n';
                    $('#bv-preview').text(prompt);
                    $('#bv-preview-wrap').show();
                });
            });
        });
        </script>
        <?php
    }
}

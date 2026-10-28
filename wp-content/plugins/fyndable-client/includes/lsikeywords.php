<?php

namespace SSEOAIClient;

class LSIKeywords
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
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        // Meta box moved to PostMetaBox tabbed container
        add_action('save_post', [$this, 'saveLSIMeta'], 10, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAssets']);
    }

    public function addMetaBoxes(): void
    {
        // Only load on post edit screens
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }
        
        $screens = get_post_types(['public' => true]);
        foreach ($screens as $screenType) {
            add_meta_box(
                'aiseo_lsi',
                __('LSI Keywords & Synonyms', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $screenType,
                'side',
                'default'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $focusKeyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
        $suggested = get_post_meta($post->ID, '_sseo_ai_lsi_suggestions', true);
        $used = get_post_meta($post->ID, '_sseo_ai_lsi_used', true) ?: [];

        wp_nonce_field('aiseo_lsi_save', 'aiseo_lsi_nonce');
        ?>
        <div class="aiseo-lsi-box">
            <div class="fyn-rows">
                <div class="fyn-row">
                    <span class="fyn-row-chip"><span class="dashicons dashicons-search"></span></span>
                    <div class="fyn-row-main">
                        <div class="fyn-row-label"><?php esc_html_e('Focus Keyphrase:', 'ai-seo-client'); ?></div>
                        <div class="fyn-row-detail">
                            <?php echo $focusKeyphrase
                                ? esc_html($focusKeyphrase)
                                : esc_html__('(Set in SEO tab)', 'ai-seo-client'); ?>
                        </div>
                    </div>
                </div>

                <?php if ($focusKeyphrase) :
                    $coverage = $this->calculateCoverage($post->ID, $focusKeyphrase, $used);
                    $coverageTone = $coverage >= 60 ? 'ok' : ($coverage >= 30 ? 'warn' : 'danger');
                ?>
                <div class="fyn-row">
                    <span class="fyn-row-chip"><span class="dashicons dashicons-chart-bar"></span></span>
                    <div class="fyn-row-main">
                        <div class="fyn-row-label"><?php esc_html_e('Coverage:', 'ai-seo-client'); ?></div>
                        <div class="fyn-row-detail" id="aiseo-lsi-coverage">
                            <?php printf(esc_html__('%.0f%% of LSI keywords used in content', 'ai-seo-client'), $coverage); ?>
                        </div>
                    </div>
                    <span class="fyn-badge fyn-badge--<?php echo esc_attr($coverageTone); ?>"><?php echo esc_html((string) round($coverage)); ?>%</span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($focusKeyphrase) : ?>
            <div class="fyn-btnbar">
                <button type="button" class="fyn-btn fyn-btn--primary" id="aiseo-generate-lsi" data-post="<?php echo $post->ID; ?>">
                    <span class="dashicons dashicons-superhero-alt"></span>
                    <span class="fyn-btn-label"><?php esc_html_e('Generate LSI Keywords', 'ai-seo-client'); ?></span>
                </button>
                <span class="spinner"></span>
            </div>

            <div id="aiseo-lsi-suggestions">
                <?php if ($suggested) : ?>
                <label class="fyn-lbl"><?php esc_html_e('Suggested Keywords:', 'ai-seo-client'); ?></label>
                <div class="fyn-chips aiseo-lsi-cloud">
                    <?php foreach ($suggested as $keyword) :
                        $isUsed = in_array($keyword, $used);
                    ?>
                    <button type="button"
                            class="fyn-chip aiseo-lsi-tag<?php echo $isUsed ? ' fyn-chip--on used' : ''; ?>"
                            data-keyword="<?php echo esc_attr($keyword); ?>">
                        <span class="fyn-chip-label"><?php echo esc_html($keyword); ?></span>
                        <span class="dashicons <?php echo $isUsed ? 'dashicons-yes' : 'dashicons-plus-alt2'; ?>"></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="aiseo_lsi_used" id="aiseo-lsi-used" value="<?php echo esc_attr(json_encode($used)); ?>">
                <?php else : ?>
                <p class="fyn-empty"><?php esc_html_e('Click "Generate LSI Keywords" to get suggestions based on your focus keyphrase.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            <?php else : ?>
            <div class="fyn-note fyn-note--warn">
                <p><?php esc_html_e('Please set a focus keyphrase in the SEO Score tab first to generate LSI keywords.', 'ai-seo-client'); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#aiseo-generate-lsi').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post');
                var spinner = btn.next('.spinner');

                spinner.addClass('is-active');
                btn.prop('disabled', true);

                wp.apiFetch({
                    path: 'sseo-ai/v1/lsi-suggest',
                    method: 'POST',
                    data: { post_id: postId }
                }).then(function(response) {
                    var html = '<label class="fyn-lbl"><?php echo esc_js(__('Suggested Keywords:', 'ai-seo-client')); ?></label>';
                    html += '<div class="fyn-chips aiseo-lsi-cloud">';
                    response.keywords.forEach(function(kw) {
                        html += '<button type="button" class="fyn-chip aiseo-lsi-tag" data-keyword="' + kw + '">';
                        html += '<span class="fyn-chip-label">' + kw + '</span>';
                        html += '<span class="dashicons dashicons-plus-alt2"></span></button>';
                    });
                    html += '</div>';
                    html += '<input type="hidden" name="aiseo_lsi_used" id="aiseo-lsi-used" value="[]">';
                    $('#aiseo-lsi-suggestions').html(html);
                    spinner.removeClass('is-active');
                    btn.prop('disabled', false);
                }).catch(function(error) {
                    alert('Error: ' + error.message);
                    spinner.removeClass('is-active');
                    btn.prop('disabled', false);
                });
            });

            $(document).on('click', '.aiseo-lsi-tag', function() {
                var tag = $(this);
                var keyword = tag.data('keyword');
                var used = JSON.parse($('#aiseo-lsi-used').val() || '[]');

                if (tag.hasClass('used')) {
                    used = used.filter(function(k) { return k !== keyword; });
                    tag.removeClass('used fyn-chip--on')
                       .find('.dashicons').attr('class', 'dashicons dashicons-plus-alt2');
                } else {
                    used.push(keyword);
                    tag.addClass('used fyn-chip--on')
                       .find('.dashicons').attr('class', 'dashicons dashicons-yes');
                }

                $('#aiseo-lsi-used').val(JSON.stringify(used));
            });
        });
        </script>
        <?php
    }

    public function saveLSIMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_lsi_nonce']) || !wp_verify_nonce($_POST['aiseo_lsi_nonce'], 'aiseo_lsi_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if (isset($_POST['aiseo_lsi_used'])) {
            $used = json_decode(sanitize_text_field($_POST['aiseo_lsi_used']), true);
            if (is_array($used)) {
                update_post_meta($postId, '_sseo_ai_lsi_used', $used);
            }
        }
    }

    public function suggestLSIKeywords(string $focusKeyphrase): array
    {
        $lang = $this->settings->contentLanguage();
        $langName = $this->settings->contentLanguageName();
        $cacheKey = 'aiseo_lsi_' . md5($focusKeyphrase . '_' . $lang);
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $prompt = "Generate 10-15 LSI (Latent Semantic Indexing) keywords and synonyms for SEO optimization related to: \"{$focusKeyphrase}\".

IMPORTANT: Generate ALL keywords in {$langName}. Do NOT translate the focus keyphrase \"{$focusKeyphrase}\" — keep it exactly as provided. Output the keywords in {$langName} only.

LSI keywords are semantically related terms that help search engines understand content context.

Requirements:
- Include synonyms and variations
- Include related concepts and subtopics
- Include long-tail variations
- Avoid exact duplicates
- Each keyword should be 1-4 words
- All keywords must be in {$langName}

Output as a comma-separated list only.";

        $result = $this->llm->call($prompt, null, null, null, [], 'keyword_research');

        if (is_wp_error($result)) {
            // Fallback: generate from common patterns
            return $this->fallbackLSI($focusKeyphrase, $lang);
        }

        $text = $result['text'];

        // Parse comma-separated list
        $keywords = array_map('trim', explode(',', $text));
        $keywords = array_filter($keywords, function($k) { return strlen($k) > 2; });
        $keywords = array_slice($keywords, 0, 15);

        // Store for this post
        set_transient($cacheKey, $keywords, DAY_IN_SECONDS);

        return $keywords;
    }

    private function fallbackLSI(string $focusKeyphrase, string $lang = 'nl'): array
    {
        $base = strtolower($focusKeyphrase);
        $words = explode(' ', $base);
        $variations = [];

        // Language-aware prefix/suffix patterns
        if ($lang === 'nl') {
            $patterns = [
                'beste ' . $base,
                'top ' . $base,
                $base . ' gids',
                $base . ' tips',
                'hoe ' . $base,
                'wat is ' . $base,
                $base . ' review',
                $base . ' vergelijken',
                'goedkope ' . $base,
                'betaalbare ' . $base,
                'professionele ' . $base,
            ];
            $suffixes = ['software', 'tools', 'diensten', 'bedrijf', 'expert', 'bureau', 'adviseur'];
        } else {
            $patterns = [
                'best ' . $base,
                'top ' . $base,
                $base . ' guide',
                $base . ' tutorial',
                $base . ' tips',
                'how to ' . $base,
                'what is ' . $base,
                $base . ' review',
                $base . ' vs',
                $base . ' comparison',
                'cheap ' . $base,
                'affordable ' . $base,
                'professional ' . $base,
            ];
            $suffixes = ['software', 'tools', 'services', 'company', 'expert', 'agency', 'consultant'];
        }

        // Add suffixes/prefixes
        foreach ($suffixes as $suffix) {
            if (strpos($base, $suffix) === false) {
                $variations[] = $base . ' ' . $suffix;
            }
        }

        // Single word variations
        if (count($words) > 1) {
            $variations[] = $words[0];
            $variations[] = $words[count($words) - 1];
        }

        return array_slice(array_unique(array_merge($patterns, $variations)), 0, 15);
    }

    public function checkUsageInContent(int $postId, array $keywords): array
    {
        $post = get_post($postId);
        if (!$post) {
            return [];
        }

        $content = strtolower(strip_tags($post->post_content . ' ' . $post->post_title));
        $usage = [];

        foreach ($keywords as $keyword) {
            $count = substr_count($content, strtolower($keyword));
            $usage[$keyword] = [
                'count' => $count,
                'used' => $count > 0,
            ];
        }

        return $usage;
    }

    public function calculateCoverage(int $postId, string $focusKeyphrase, array $usedKeywords): float
    {
        if (empty($focusKeyphrase)) {
            return 0;
        }

        $suggested = get_post_meta($postId, '_sseo_ai_lsi_suggestions', true);
        if (empty($suggested)) {
            return 0;
        }

        $count = count(array_intersect($usedKeywords, $suggested));
        $suggestedCount = count($suggested);
        return $suggestedCount > 0 ? round(($count / $suggestedCount) * 100, 1) : 0;
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/lsi-suggest', [
            'methods' => 'POST',
            'callback' => [$this, 'restSuggestLSI'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/lsi-check', [
            'methods' => 'POST',
            'callback' => [$this, 'restCheckUsage'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restSuggestLSI(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $focusKeyphrase = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);

        if (!$focusKeyphrase) {
            return new \WP_Error('no_keyphrase', __('No focus keyphrase set', 'ai-seo-client'));
        }

        $keywords = $this->suggestLSIKeywords($focusKeyphrase);
        update_post_meta($postId, '_sseo_ai_lsi_suggestions', $keywords);

        return ['keywords' => $keywords];
    }

    public function restCheckUsage(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $keywords = $request->get_param('keywords') ?: [];

        $usage = $this->checkUsageInContent($postId, $keywords);
        
        return [
            'usage' => $usage,
            'total_used' => count(array_filter($usage, fn($u) => $u['used'])),
        ];
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_script(
            'aiseo-lsi-editor',
            plugins_url('assets/lsi-editor.js', __DIR__ . '/ai-seo-assistant.php'),
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data'],
            '1.0',
            true
        );
    }
}

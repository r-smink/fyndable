<?php

namespace SSEOAIClient;

class TruSEOSCORE
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
        // Meta box moved to PostMetaBox tabbed container
        add_action('save_post', [$this, 'saveSeoMeta'], 10, 2);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function addMetaBoxes(): void
    {
        // Only load on actual post edit screens to improve performance
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }
        
        $screens = get_post_types(['public' => true]);
        foreach ($screens as $screenType) {
            add_meta_box(
                'aiseo_truseo',
                __('AI SEO Score & Optimization', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $screenType,
                'side',
                'high'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $focusKeyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
        $secondaryKeyphrases = get_post_meta($post->ID, '_sseo_ai_secondary_keyphrases', true);
        $title = get_post_meta($post->ID, '_sseo_ai_title', true) ?: $this->generateDefaultTitle($post);
        $description = get_post_meta($post->ID, '_sseo_ai_description', true) ?: $this->generateDefaultDescription($post);
        $score = $this->calculateScore($post, $focusKeyphrase);
        $analysis = $this->getAnalysis($post, $focusKeyphrase);
        
        wp_nonce_field('aiseo_truseo_save', 'aiseo_truseo_nonce');
        ?>
        <div class="aiseo-truseo-metabox">
            <style>
                .aiseo-truseo-metabox { padding: 15px; }
                .aiseo-score-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; color: white; margin-bottom: 15px; }
                .aiseo-score-good { background: #00a32a; }
                .aiseo-score-ok { background: #dba617; }
                .aiseo-score-bad { background: #d63638; }
                .aiseo-analysis-list { list-style: none; padding: 0; }
                .aiseo-analysis-list li { padding: 8px; margin: 4px 0; border-left: 3px solid #ddd; }
                .aiseo-analysis-list li.good { border-left-color: #00a32a; background: #edfaef; }
                .aiseo-analysis-list li.warning { border-left-color: #dba617; background: #fcf9e8; }
                .aiseo-analysis-list li.error { border-left-color: #d63638; background: #fcf0f1; }
                .aiseo-preview-box { border: 1px solid #ddd; padding: 15px; background: #f9f9f9; margin: 15px 0; }
                .aiseo-preview-title { color: #1a0dab; font-size: 18px; text-decoration: underline; cursor: pointer; }
                .aiseo-preview-url { color: #006621; font-size: 14px; }
                .aiseo-preview-desc { color: #4d5156; font-size: 14px; line-height: 1.4; }
            </style>

            <div style="display:flex; gap:20px; margin-bottom:20px;">
                <div class="aiseo-score-circle <?php echo $score >= 80 ? 'aiseo-score-good' : ($score >= 50 ? 'aiseo-score-ok' : 'aiseo-score-bad'); ?>">
                    <?php echo $score; ?>
                </div>
                <div>
                    <h3><?php esc_html_e('SEO Score', 'ai-seo-client'); ?></h3>
                    <p><?php echo $score >= 80 ? __('Great! Your content is well-optimized.', 'ai-seo-client') : ($score >= 50 ? __('Your content needs some improvements.', 'ai-seo-client') : __('Major improvements needed.', 'ai-seo-client')); ?></p>
                </div>
            </div>

            <h4><?php esc_html_e('Focus Keyphrase', 'ai-seo-client'); ?></h4>
            <p>
                <input type="text" name="aiseo_focus_keyphrase" value="<?php echo esc_attr($focusKeyphrase); ?>" class="large-text" placeholder="<?php esc_attr_e('Enter your main keyword...', 'ai-seo-client'); ?>">
            </p>

            <h4><?php esc_html_e('Secondary Keyphrases', 'ai-seo-client'); ?></h4>
            <p>
                <input type="text" name="aiseo_secondary_keyphrases" value="<?php echo esc_attr($secondaryKeyphrases); ?>" class="large-text" placeholder="<?php esc_attr_e('keyword 1, keyword 2, keyword 3', 'ai-seo-client'); ?>">
                <small><?php esc_html_e('Comma-separated list of related keywords', 'ai-seo-client'); ?></small>
            </p>

            <h4><?php esc_html_e('SEO Title', 'ai-seo-client'); ?></h4>
            <p>
                <input type="text" name="aiseo_title" value="<?php echo esc_attr($title); ?>" class="large-text" maxlength="60">
                <small><?php printf(__('Recommended: 50-60 characters. Current: %d', 'ai-seo-client'), strlen($title)); ?></small>
            </p>

            <h4><?php esc_html_e('Meta Description', 'ai-seo-client'); ?></h4>
            <p>
                <textarea name="aiseo_description" rows="3" class="large-text" maxlength="160"><?php echo esc_textarea($description); ?></textarea>
                <small><?php printf(__('Recommended: 150-160 characters. Current: %d', 'ai-seo-client'), strlen($description)); ?></small>
            </p>

            <h4><?php esc_html_e('Google SERP Preview', 'ai-seo-client'); ?></h4>
            <div class="aiseo-preview-box">
                <div class="aiseo-preview-title"><?php echo esc_html($title); ?></div>
                <div class="aiseo-preview-url"><?php echo esc_url($this->getCanonicalUrl($post)); ?></div>
                <div class="aiseo-preview-desc"><?php echo esc_html($description); ?></div>
            </div>

            <h4><?php esc_html_e('Analysis Results', 'ai-seo-client'); ?></h4>
            <ul class="aiseo-analysis-list">
                <?php foreach ($analysis as $item) : ?>
                <li class="<?php echo esc_attr($item['status']); ?>">
                    <strong><?php echo esc_html($item['label']); ?>:</strong> 
                    <?php echo esc_html($item['message']); ?>
                </li>
                <?php endforeach; ?>
            </ul>

            <p>
                <button type="button" class="button" id="aiseo-analyze-content">
                    <?php esc_html_e('Re-analyze Content', 'ai-seo-client'); ?>
                </button>
                <button type="button" class="button button-secondary" id="aiseo-suggest-improvements">
                    <?php esc_html_e('AI Suggest Improvements', 'ai-seo-client'); ?>
                </button>
            </p>

            <div id="aiseo-suggestions" style="margin-top:15px;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var postId = <?php echo $post->ID; ?>;

            // Re-analyze Content button
            $('#aiseo-analyze-content').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Analyzing...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/content-analyze/' + postId,
                    method: 'GET'
                }).then(function(data) {
                    // Reload page to show updated analysis
                    location.reload();
                }).catch(function(err) {
                    alert(err.message || '<?php echo esc_js(__('Analysis failed', 'ai-seo-client')); ?>');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Re-analyze Content', 'ai-seo-client')); ?>');
                });
            });

            // AI Suggest Improvements button
            $('#aiseo-suggest-improvements').on('click', function() {
                var btn = $(this);
                var focusKeyphrase = $('input[name="aiseo_focus_keyphrase"]').val();

                if (!focusKeyphrase) {
                    alert('<?php echo esc_js(__('Please enter a focus keyphrase first', 'ai-seo-client')); ?>');
                    return;
                }

                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating suggestions...', 'ai-seo-client')); ?>');
                $('#aiseo-suggestions').html('<p><?php echo esc_js(__('Loading AI suggestions...', 'ai-seo-client')); ?></p>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/content-suggest/' + postId,
                    method: 'POST',
                    data: { keyphrase: focusKeyphrase }
                }).then(function(data) {
                    if (data.suggestions && data.suggestions.length > 0) {
                        var html = '<div class="aiseo-suggestions-list" style="background:#f0f7ff;border-left:3px solid #2563eb;padding:10px 15px;margin:10px 0;border-radius:0 4px 4px 0;">';
                        html += '<h4 style="margin-top:0;color:#2563eb;"><?php echo esc_js(__('AI Improvement Suggestions', 'ai-seo-client')); ?></h4><ul style="margin:0;">';
                        data.suggestions.forEach(function(s) {
                            html += '<li style="margin:5px 0;">' + s + '</li>';
                        });
                        html += '</ul></div>';
                        $('#aiseo-suggestions').html(html);
                    } else {
                        $('#aiseo-suggestions').html('<p style="color:#00a32a;"><?php echo esc_js(__('No suggestions needed - your content looks great!', 'ai-seo-client')); ?></p>');
                    }
                    btn.prop('disabled', false).text('<?php echo esc_js(__('AI Suggest Improvements', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || '<?php echo esc_js(__('Failed to get suggestions', 'ai-seo-client')); ?>');
                    $('#aiseo-suggestions').html('');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('AI Suggest Improvements', 'ai-seo-client')); ?>');
                });
            });
        });
        </script>
        <?php
    }

    public function saveSeoMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_truseo_nonce']) || !wp_verify_nonce($_POST['aiseo_truseo_nonce'], 'aiseo_truseo_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if (isset($_POST['aiseo_focus_keyphrase'])) {
            update_post_meta($postId, '_sseo_ai_focus_keyphrase', sanitize_text_field($_POST['aiseo_focus_keyphrase']));
        }
        if (isset($_POST['aiseo_secondary_keyphrases'])) {
            update_post_meta($postId, '_sseo_ai_secondary_keyphrases', sanitize_text_field($_POST['aiseo_secondary_keyphrases']));
        }
        if (isset($_POST['aiseo_title'])) {
            update_post_meta($postId, '_sseo_ai_title', sanitize_text_field($_POST['aiseo_title']));
        }
        if (isset($_POST['aiseo_description'])) {
            update_post_meta($postId, '_sseo_ai_description', sanitize_text_field($_POST['aiseo_description']));
        }

        // Save score
        $score = $this->calculateScore($post, $_POST['aiseo_focus_keyphrase'] ?? '');
        update_post_meta($postId, '_sseo_ai_score', $score);
    }

    public function calculateScore(\WP_Post $post, string $focusKeyphrase): int
    {
        $score = 0;
        $maxScore = 100;

        // Rendered content supports Elementor, WPBakery and other page builders
        $content = PageBuilderHelper::getContent($post);
        $text = wp_strip_all_tags($content);
        $textLower = strtolower($text);

        // Content length (max 15 points)
        $wordCount = str_word_count($text);
        if ($wordCount >= 1000) $score += 15;
        elseif ($wordCount >= 500) $score += 10;
        elseif ($wordCount >= 300) $score += 5;

        // Focus keyphrase usage (max 20 points)
        if ($focusKeyphrase) {
            $keyphraseLower = strtolower($focusKeyphrase);

            // In title (5 points)
            if (stripos($post->post_title, $focusKeyphrase) !== false) {
                $score += 5;
            }

            // In first paragraph (5 points)
            $firstPara = substr($textLower, 0, 500);
            if (stripos($firstPara, $keyphraseLower) !== false) {
                $score += 5;
            }

            // Density (max 10 points)
            $count = substr_count($textLower, $keyphraseLower);
            $density = $count / max(1, $wordCount) * 100;
            if ($density >= 0.5 && $density <= 2.5) {
                $score += 10;
            } elseif ($density > 0) {
                $score += 5;
            }

            // In headings (5 points)
            if (preg_match('/<h[1-6][^>]*>.*?' . preg_quote($keyphraseLower, '/') . '.*?<\/h[1-6]>/i', strtolower($content))) {
                $score += 5;
            }
        }

        // SEO meta (max 20 points)
        $title = get_post_meta($post->ID, '_sseo_ai_title', true);
        $desc = get_post_meta($post->ID, '_sseo_ai_description', true);
        if ($title && strlen($title) >= 30 && strlen($title) <= 60) $score += 10;
        if ($desc && strlen($desc) >= 120 && strlen($desc) <= 160) $score += 10;

        // Internal links (max 10 points)
        $internalLinks = $this->countInternalLinks($content);
        if ($internalLinks >= 3) $score += 10;
        elseif ($internalLinks >= 1) $score += 5;

        // Outbound links (max 5 points)
        $outboundLinks = $this->countOutboundLinks($content);
        if ($outboundLinks >= 1 && $outboundLinks <= 5) $score += 5;

        // Images with alt (max 10 points)
        $images = $this->analyzeImages($content, $post->ID);
        $altRatio = $images['total'] > 0 ? $images['with_alt'] / $images['total'] : 0;
        $score += round($altRatio * 10);

        // Readability (max 10 points)
        $readability = $this->calculateReadability($content);
        if ($readability['flesch'] >= 60) $score += 10;
        elseif ($readability['flesch'] >= 40) $score += 5;

        // URL structure (max 5 points)
        if (strlen($post->post_name) <= 60 && strpos($post->post_name, '-') !== false) {
            $score += 5;
        }

        // H1 usage (max 5 points)
        $h1Count = preg_match_all('/<h1[^>]*>/i', $content);
        if ($h1Count === 1) $score += 5;

        return min($maxScore, max(0, $score));
    }

    public function getAnalysis(\WP_Post $post, string $focusKeyphrase): array
    {
        $analysis = [];

        // Rendered content supports Elementor, WPBakery and other page builders
        $htmlContent = PageBuilderHelper::getContent($post);
        $content = wp_strip_all_tags($htmlContent);
        $contentLower = strtolower($content);

        // Content length
        $wordCount = str_word_count($content);
        $analysis[] = [
            'label' => __('Content Length', 'ai-seo-client'),
            'status' => $wordCount >= 500 ? 'good' : ($wordCount >= 300 ? 'warning' : 'error'),
            'message' => sprintf(__('%d words. %s', 'ai-seo-client'), $wordCount,
                $wordCount >= 500 ? __('Good length.', 'ai-seo-client') : __('Try to add more content.', 'ai-seo-client')),
        ];

        // Keyphrase in title
        if ($focusKeyphrase) {
            $inTitle = stripos($post->post_title, $focusKeyphrase) !== false;
            $analysis[] = [
                'label' => __('Keyphrase in Title', 'ai-seo-client'),
                'status' => $inTitle ? 'good' : 'error',
                'message' => $inTitle ? __('Keyphrase found in title.', 'ai-seo-client') : __('Add keyphrase to title.', 'ai-seo-client'),
            ];

            // Keyphrase density
            $count = substr_count($contentLower, strtolower($focusKeyphrase));
            $density = $count / max(1, $wordCount) * 100;
            $analysis[] = [
                'label' => __('Keyphrase Density', 'ai-seo-client'),
                'status' => ($density >= 0.5 && $density <= 2.5) ? 'good' : 'warning',
                'message' => sprintf(__('%.1f%% (%d occurrences)', 'ai-seo-client'), $density, $count),
            ];

            // Keyphrase in intro
            $intro = substr($contentLower, 0, 300);
            $inIntro = stripos($intro, strtolower($focusKeyphrase)) !== false;
            $analysis[] = [
                'label' => __('Keyphrase in Intro', 'ai-seo-client'),
                'status' => $inIntro ? 'good' : 'warning',
                'message' => $inIntro ? __('Keyphrase appears in first paragraph.', 'ai-seo-client') : __('Add keyphrase to first paragraph.', 'ai-seo-client'),
            ];
        }

        // Internal links
        $internalCount = $this->countInternalLinks($htmlContent);
        $analysis[] = [
            'label' => __('Internal Links', 'ai-seo-client'),
            'status' => $internalCount >= 2 ? 'good' : 'warning',
            'message' => sprintf(__('%d internal links.', 'ai-seo-client'), $internalCount),
        ];

        // Images
        $images = $this->analyzeImages($htmlContent, $post->ID);
        $analysis[] = [
            'label' => __('Images with Alt Text', 'ai-seo-client'),
            'status' => ($images['total'] === 0 || $images['with_alt'] === $images['total']) ? 'good' : 'warning',
            'message' => sprintf(__('%d of %d images have alt text.', 'ai-seo-client'), $images['with_alt'], $images['total']),
        ];

        // H1 usage
        $h1Count = preg_match_all('/<h1[^>]*>/i', $htmlContent);
        $analysis[] = [
            'label' => __('H1 Heading', 'ai-seo-client'),
            'status' => $h1Count === 1 ? 'good' : 'error',
            'message' => $h1Count === 1 ? __('One H1 heading found.', 'ai-seo-client') : sprintf(__('%d H1 headings. Use exactly one.', 'ai-seo-client'), $h1Count),
        ];

        // Readability
        $readability = $this->calculateReadability($htmlContent);
        $analysis[] = [
            'label' => __('Readability', 'ai-seo-client'),
            'status' => $readability['flesch'] >= 50 ? 'good' : 'warning',
            'message' => sprintf(__('Flesch score: %d (%s)', 'ai-seo-client'), $readability['flesch'], $readability['grade']),
        ];

        return $analysis;
    }

    private function countInternalLinks(string $content): int
    {
        $homeUrl = home_url();
        $count = 0;
        if (preg_match_all('/href=[\'"](.*?)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (strpos($url, $homeUrl) === 0 || strpos($url, '/') === 0) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function countOutboundLinks(string $content): int
    {
        $homeUrl = home_url();
        $count = 0;
        if (preg_match_all('/href=[\'"](.*?)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (strpos($url, 'http') === 0 && strpos($url, $homeUrl) !== 0) {
                    $count++;
                }
            }
        }
        return $count;
    }

    private function analyzeImages(string $content, int $postId): array
    {
        $total = 0;
        $withAlt = 0;

        // Content images
        if (preg_match_all('/<img[^>]+>/i', $content, $matches)) {
            foreach ($matches[0] as $img) {
                $total++;
                if (preg_match('/alt=[\'"]([^\'"]*)[\'"]/i', $img, $altMatch)) {
                    if (!empty($altMatch[1])) {
                        $withAlt++;
                    }
                }
            }
        }

        // Featured image
        if (has_post_thumbnail($postId)) {
            $thumbId = get_post_thumbnail_id($postId);
            $alt = get_post_meta($thumbId, '_wp_attachment_image_alt', true);
            $total++;
            if ($alt) {
                $withAlt++;
            }
        }

        return ['total' => $total, 'with_alt' => $withAlt];
    }

    private function calculateReadability(string $content): array
    {
        $text = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($text);
        $syllables = $this->estimateSyllables($text);

        $sentenceCount = count(array_filter($sentences));
        $avgWordsPerSentence = $sentenceCount > 0 ? $words / $sentenceCount : 0;
        $avgSyllablesPerWord = $words > 0 ? $syllables / $words : 0;

        // Flesch Reading Ease
        $flesch = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord);
        $flesch = max(0, min(100, $flesch));

        // Grade level
        if ($flesch >= 90) $grade = '5th grade';
        elseif ($flesch >= 80) $grade = '6th grade';
        elseif ($flesch >= 70) $grade = '7th grade';
        elseif ($flesch >= 60) $grade = '8th-9th grade';
        elseif ($flesch >= 50) $grade = '10th-12th grade';
        elseif ($flesch >= 30) $grade = 'College';
        else $grade = 'Graduate';

        return [
            'flesch' => round($flesch),
            'grade' => $grade,
            'words_per_sentence' => round($avgWordsPerSentence, 1),
            'syllables_per_word' => round($avgSyllablesPerWord, 2),
        ];
    }

    private function estimateSyllables(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $syllableCount = 0;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if (empty($word)) continue;

            $syllables = 0;
            $word = preg_replace('/e$/', '', $word);
            $syllables += preg_match_all('/[aeiouy]+/', $word);
            if ($syllables === 0) $syllables = 1;

            $syllableCount += $syllables;
        }

        return $syllableCount;
    }

    private function generateDefaultTitle(\WP_Post $post): string
    {
        $title = $post->post_title;
        $sep = apply_filters('document_title_separator', '-');
        return "{$title} {$sep} " . get_bloginfo('name');
    }

    private function generateDefaultDescription(\WP_Post $post): string
    {
        $content = PageBuilderHelper::getContent($post);
        return wp_trim_words(wp_strip_all_tags($content), 25, '');
    }

    private function getCanonicalUrl(\WP_Post $post): string
    {
        return get_permalink($post);
    }

    public function enqueueAssets(): void
    {
        wp_enqueue_script(
            'aiseo-truseo-editor',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/truseo-editor.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-data', 'wp-api-fetch'],
            SSEO_AI_CLIENT_VERSION,
            true
        );

        wp_enqueue_script(
            'aiseo-seo-sidebar',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/seo-sidebar.js',
            ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'],
            SSEO_AI_CLIENT_VERSION,
            true
        );

        // Register meta fields for REST API
        $metaFields = ['_sseo_ai_focus_keyphrase', '_sseo_ai_title', '_sseo_ai_description', '_sseo_ai_score'];
        foreach ($metaFields as $key) {
            register_post_meta('', $key, [
                'show_in_rest' => true,
                'single' => true,
                'type' => $key === '_sseo_ai_score' ? 'integer' : 'string',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyze'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/content-analyze/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restAnalyzePost'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/content-suggest/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'restSuggestImprovements'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restAnalyze(\WP_REST_Request $request): array
    {
        $content = $request->get_param('content');
        $title = $request->get_param('title');
        $keyphrase = $request->get_param('keyphrase');

        $post = (object)[
            'post_title' => $title,
            'post_content' => $content,
            'post_name' => sanitize_title($title),
            'ID' => 0,
            'post_author' => get_current_user_id(),
        ];

        $score = $this->calculateScore($post, $keyphrase);
        $analysis = $this->getAnalysis($post, $keyphrase);

        return [
            'score' => $score,
            'analysis' => $analysis,
        ];
    }

    public function restAnalyzePost(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int)$request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }

        $keyphrase = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        $score = $this->calculateScore($post, $keyphrase);
        $analysis = $this->getAnalysis($post, $keyphrase);

        // Save the updated score
        update_post_meta($postId, '_sseo_ai_score', $score);

        return [
            'score' => $score,
            'analysis' => $analysis,
            'post_id' => $postId,
        ];
    }

    public function restSuggestImprovements(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int)$request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }

        $keyphrase = $request->get_param('keyphrase') ?: get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        
        if (empty($keyphrase)) {
            return new \WP_Error('no_keyphrase', 'No focus keyphrase provided', ['status' => 400]);
        }

        $suggestions = $this->generateAISuggestions($post, $keyphrase);

        return [
            'suggestions' => $suggestions,
            'post_id' => $postId,
        ];
    }

    private function generateAISuggestions(\WP_Post $post, string $keyphrase): array
    {
        // Use existing analysis to generate specific suggestions
        $analysis = $this->getAnalysis($post, $keyphrase);
        $suggestions = [];

        foreach ($analysis as $item) {
            if ($item['status'] === 'fail') {
                $suggestions[] = $item['message'];
            }
        }

        // Add AI-powered content improvement suggestions
        $content = PageBuilderHelper::getContent($post);
        $wordCount = str_word_count(wp_strip_all_tags($content));
        if ($wordCount < 500) {
            $suggestions[] = sprintf(
                __('Consider expanding your content to at least 500 words. Currently at %d words.', 'ai-seo-client'),
                $wordCount
            );
        }

        $internalLinks = $this->countInternalLinks($content);
        if ($internalLinks < 2) {
            $suggestions[] = __('Add more internal links to related content on your site to improve topical authority.', 'ai-seo-client');
        }

        $seoTitle = get_post_meta($post->ID, '_sseo_ai_title', true);
        if (empty($seoTitle)) {
            $suggestions[] = __('Generate an SEO title that includes your focus keyphrase near the beginning.', 'ai-seo-client');
        }

        $seoDesc = get_post_meta($post->ID, '_sseo_ai_description', true);
        if (empty($seoDesc)) {
            $suggestions[] = __('Add a compelling meta description (150-160 characters) to improve CTR from search results.', 'ai-seo-client');
        }

        return $suggestions;
    }

    public function getPostScore(int $postId): ?int
    {
        $score = get_post_meta($postId, '_sseo_ai_score', true);
        return $score !== '' ? (int)$score : null;
    }

    public function getPostsByScore(int $minScore, int $limit = 20): array
    {
        $args = [
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => [
                [
                    'key' => '_sseo_ai_score',
                    'value' => $minScore,
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ],
            ],
        ];

        return get_posts($args);
    }
}

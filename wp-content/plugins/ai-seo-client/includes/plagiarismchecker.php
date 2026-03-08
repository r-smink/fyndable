<?php

namespace AISEOClient;

/**
 * AI Plagiarism / Originality Checker
 * 
 * Uses AI to detect potentially duplicated or unoriginal content segments.
 * Checks content against known patterns, sentence structure analysis,
 * and AI-detection heuristics. Provides per-post originality scoring.
 * Comparable to Akii Originality, Copyscape-style checks.
 */
class PlagiarismChecker
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
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoclient/v1', '/originality/check', [
            'methods' => 'POST',
            'callback' => [$this, 'restCheckOriginality'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'post_id' => ['type' => 'integer'],
                'content' => ['type' => 'string'],
            ],
        ]);
    }

    /**
     * REST: Check content originality via AI analysis
     */
    public function restCheckOriginality(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) $request->get_param('post_id');
        $content = $request->get_param('content');

        if (!$content && $postId) {
            $post = get_post($postId);
            if ($post) {
                $content = $post->post_content;
            }
        }

        if (empty($content)) {
            return new \WP_Error('no_content', __('No content to check', 'ai-seo-client'));
        }

        $plainContent = wp_strip_all_tags($content);
        $wordCount = str_word_count($plainContent);

        if ($wordCount < 50) {
            return new \WP_Error('too_short', __('Content must be at least 50 words for originality analysis', 'ai-seo-client'));
        }

        // Basic heuristic checks first (no AI needed)
        $heuristics = $this->runHeuristicChecks($plainContent);

        // AI-powered originality analysis
        $aiResult = $this->runAiAnalysis($plainContent);

        if (is_wp_error($aiResult)) {
            // Still return heuristics even if AI fails
            return [
                'originality_score' => $heuristics['score'],
                'word_count' => $wordCount,
                'checks' => $heuristics['checks'],
                'ai_analysis' => null,
                'ai_error' => $aiResult->get_error_message(),
            ];
        }

        // Combine scores: 40% heuristic + 60% AI
        $combinedScore = (int) round(
            $heuristics['score'] * 0.4 +
            ($aiResult['originality_score'] ?? 70) * 0.6
        );
        $combinedScore = max(0, min(100, $combinedScore));

        // Save score to post meta
        if ($postId) {
            update_post_meta($postId, '_aiseo_originality_score', $combinedScore);
            update_post_meta($postId, '_aiseo_originality_checked', current_time('mysql'));
        }

        return [
            'originality_score' => $combinedScore,
            'word_count' => $wordCount,
            'checks' => array_merge($heuristics['checks'], $aiResult['checks'] ?? []),
            'ai_analysis' => $aiResult['summary'] ?? '',
            'flagged_segments' => $aiResult['flagged_segments'] ?? [],
        ];
    }

    /**
     * Run heuristic (non-AI) checks on content
     */
    private function runHeuristicChecks(string $content): array
    {
        $checks = [];
        $score = 100;

        // 1. Check for duplicate content within the same site
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter(array_map('trim', $sentences), fn($s) => str_word_count($s) >= 5);

        $duplicateOnSite = 0;
        $sampleSentences = array_slice($sentences, 0, 10);

        global $wpdb;
        foreach ($sampleSentences as $sentence) {
            $escaped = '%' . $wpdb->esc_like($sentence) . '%';
            $found = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                $escaped
            ));
            if ($found > 1) {
                $duplicateOnSite++;
            }
        }

        $dupRatio = count($sampleSentences) > 0 ? $duplicateOnSite / count($sampleSentences) : 0;
        if ($dupRatio > 0.5) {
            $score -= 30;
            $checks[] = [
                'type' => 'error',
                'label' => __('Internal Duplicate Content', 'ai-seo-client'),
                'message' => sprintf(__('%d of %d checked sentences appear in other posts on your site.', 'ai-seo-client'), $duplicateOnSite, count($sampleSentences)),
            ];
        } elseif ($dupRatio > 0.2) {
            $score -= 15;
            $checks[] = [
                'type' => 'warning',
                'label' => __('Partial Internal Overlap', 'ai-seo-client'),
                'message' => sprintf(__('%d of %d checked sentences found in other posts.', 'ai-seo-client'), $duplicateOnSite, count($sampleSentences)),
            ];
        } else {
            $checks[] = [
                'type' => 'good',
                'label' => __('Internal Uniqueness', 'ai-seo-client'),
                'message' => __('Content appears unique within your site.', 'ai-seo-client'),
            ];
        }

        // 2. Sentence variety / structure repetition
        $sentenceLengths = array_map('str_word_count', $sentences);
        $avgLen = count($sentenceLengths) > 0 ? array_sum($sentenceLengths) / count($sentenceLengths) : 0;
        $stdDev = 0;
        if (count($sentenceLengths) > 1) {
            $variance = array_sum(array_map(fn($l) => pow($l - $avgLen, 2), $sentenceLengths)) / count($sentenceLengths);
            $stdDev = sqrt($variance);
        }

        if ($stdDev < 3 && count($sentenceLengths) > 5) {
            $score -= 10;
            $checks[] = [
                'type' => 'warning',
                'label' => __('Sentence Variety', 'ai-seo-client'),
                'message' => __('Sentences have very similar length, which can indicate AI-generated or templated text. Add variety.', 'ai-seo-client'),
            ];
        } else {
            $checks[] = [
                'type' => 'good',
                'label' => __('Sentence Variety', 'ai-seo-client'),
                'message' => __('Good variety in sentence structure and length.', 'ai-seo-client'),
            ];
        }

        // 3. Overused phrases (common in AI text)
        $aiPhrases = [
            'in conclusion', 'it is important to note', 'it\'s worth noting',
            'in today\'s world', 'in the digital age', 'at the end of the day',
            'plays a crucial role', 'it goes without saying', 'needless to say',
            'comprehensive guide', 'deep dive', 'game changer', 'unlock the power',
            'elevate your', 'harness the power', 'delve into', 'tapestry',
        ];

        $contentLower = strtolower($content);
        $aiPhraseCount = 0;
        $foundPhrases = [];
        foreach ($aiPhrases as $phrase) {
            if (stripos($contentLower, $phrase) !== false) {
                $aiPhraseCount++;
                $foundPhrases[] = $phrase;
            }
        }

        if ($aiPhraseCount >= 4) {
            $score -= 15;
            $checks[] = [
                'type' => 'warning',
                'label' => __('AI Pattern Detection', 'ai-seo-client'),
                'message' => sprintf(
                    __('Found %d common AI-generated phrases: %s. Consider rewriting for a more natural tone.', 'ai-seo-client'),
                    $aiPhraseCount,
                    implode(', ', array_slice($foundPhrases, 0, 5))
                ),
            ];
        } elseif ($aiPhraseCount >= 2) {
            $score -= 5;
            $checks[] = [
                'type' => 'warning',
                'label' => __('AI Pattern Detection', 'ai-seo-client'),
                'message' => sprintf(__('Found %d common AI phrases. Minor concern.', 'ai-seo-client'), $aiPhraseCount),
            ];
        } else {
            $checks[] = [
                'type' => 'good',
                'label' => __('AI Pattern Detection', 'ai-seo-client'),
                'message' => __('No common AI-generated phrases detected.', 'ai-seo-client'),
            ];
        }

        // 4. Vocabulary richness (Type-Token Ratio)
        $words = str_word_count($contentLower, 1);
        $uniqueWords = count(array_unique($words));
        $totalWords = count($words);
        $ttr = $totalWords > 0 ? $uniqueWords / $totalWords : 0;

        if ($ttr < 0.3 && $totalWords > 200) {
            $score -= 10;
            $checks[] = [
                'type' => 'warning',
                'label' => __('Vocabulary Richness', 'ai-seo-client'),
                'message' => sprintf(__('Low vocabulary diversity (%.0f%%). Consider using more varied language.', 'ai-seo-client'), $ttr * 100),
            ];
        } else {
            $checks[] = [
                'type' => 'good',
                'label' => __('Vocabulary Richness', 'ai-seo-client'),
                'message' => sprintf(__('Good vocabulary diversity (%.0f%%).', 'ai-seo-client'), $ttr * 100),
            ];
        }

        return [
            'score' => max(0, min(100, $score)),
            'checks' => $checks,
        ];
    }

    /**
     * Run AI-powered originality analysis
     */
    private function runAiAnalysis(string $content): array|\WP_Error
    {
        $excerpt = mb_substr($content, 0, 2000);

        $prompt = "Analyze this text for originality and potential plagiarism/AI-generation patterns. 

Text to analyze:
\"{$excerpt}\"

Evaluate:
1. Does it read like original human-written content or AI-generated?
2. Are there sections that seem copied/templated?
3. Is the writing style consistent throughout?
4. Are there unique insights, personal voice, or original perspectives?

Return JSON only (no markdown):
{
    \"originality_score\": 0-100,
    \"summary\": \"1-2 sentence assessment\",
    \"flagged_segments\": [\"any suspicious phrases or sections\"],
    \"checks\": [
        {\"type\": \"good|warning|error\", \"label\": \"check name\", \"message\": \"explanation\"}
    ]
}";

        $result = $this->llm->call($prompt, null, null, 600);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!$data) {
            return new \WP_Error('parse_error', __('Failed to parse AI originality analysis', 'ai-seo-client'));
        }

        return [
            'originality_score' => (int) ($data['originality_score'] ?? 70),
            'summary' => sanitize_text_field($data['summary'] ?? ''),
            'flagged_segments' => array_map('sanitize_text_field', $data['flagged_segments'] ?? []),
            'checks' => array_map(function ($c) {
                return [
                    'type' => in_array($c['type'] ?? '', ['good', 'warning', 'error']) ? $c['type'] : 'warning',
                    'label' => sanitize_text_field($c['label'] ?? ''),
                    'message' => sanitize_text_field($c['message'] ?? ''),
                ];
            }, $data['checks'] ?? []),
        ];
    }

    /**
     * Meta box for originality check in post editor
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true]);
        foreach ($postTypes as $postType) {
            add_meta_box(
                'aiseo_originality',
                __('AI SEO: Originality Check', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'low'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $score = get_post_meta($post->ID, '_aiseo_originality_score', true);
        $checked = get_post_meta($post->ID, '_aiseo_originality_checked', true);
        ?>
        <div id="aiseo-originality-box">
            <?php if ($score !== ''): ?>
                <?php
                $color = $score >= 80 ? '#00a32a' : ($score >= 50 ? '#dba617' : '#d63638');
                $label = $score >= 80 ? __('Original', 'ai-seo-client') : ($score >= 50 ? __('Partially Original', 'ai-seo-client') : __('Low Originality', 'ai-seo-client'));
                ?>
                <div style="text-align:center; margin-bottom:10px;">
                    <div style="font-size:32px; font-weight:bold; color:<?php echo $color; ?>;"><?php echo (int) $score; ?>%</div>
                    <div style="color:<?php echo $color; ?>; font-weight:600;"><?php echo esc_html($label); ?></div>
                    <?php if ($checked): ?>
                        <div style="font-size:11px; color:#999; margin-top:4px;">
                            <?php printf(__('Last checked: %s', 'ai-seo-client'), esc_html($checked)); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p style="color:#999; text-align:center;"><?php esc_html_e('Not checked yet', 'ai-seo-client'); ?></p>
            <?php endif; ?>

            <button type="button" class="button" id="aiseo-check-originality" data-post="<?php echo $post->ID; ?>" style="width:100%;">
                <?php esc_html_e('Check Originality', 'ai-seo-client'); ?>
            </button>

            <div id="aiseo-originality-details" style="margin-top:10px;"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#aiseo-check-originality').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post');
                btn.prop('disabled', true).text('<?php echo esc_js(__('Analyzing...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'aiseoclient/v1/originality/check',
                    method: 'POST',
                    data: { post_id: postId }
                }).then(function(res) {
                    var color = res.originality_score >= 80 ? '#00a32a' : (res.originality_score >= 50 ? '#dba617' : '#d63638');
                    var html = '<div style="text-align:center;margin-bottom:10px;">' +
                        '<div style="font-size:32px;font-weight:bold;color:' + color + ';">' + res.originality_score + '%</div>' +
                        '</div>';

                    if (res.ai_analysis) {
                        html += '<p style="font-size:12px;color:#666;">' + $('<span>').text(res.ai_analysis).html() + '</p>';
                    }

                    if (res.checks && res.checks.length) {
                        res.checks.forEach(function(c) {
                            var cColor = c.type === 'good' ? '#00a32a' : (c.type === 'warning' ? '#dba617' : '#d63638');
                            var icon = c.type === 'good' ? '✓' : (c.type === 'warning' ? '▲' : '✕');
                            html += '<div style="font-size:12px;padding:4px 6px;margin:3px 0;border-left:2px solid ' + cColor + ';background:#f9f9f9;">' +
                                '<span style="color:' + cColor + ';">' + icon + '</span> ' +
                                '<strong>' + $('<span>').text(c.label).html() + '</strong><br>' +
                                '<span style="color:#666;">' + $('<span>').text(c.message).html() + '</span>' +
                                '</div>';
                        });
                    }

                    $('#aiseo-originality-box').html(html + '<button type="button" class="button" id="aiseo-check-originality" data-post="' + postId + '" style="width:100%;margin-top:10px;"><?php echo esc_js(__('Re-check', 'ai-seo-client')); ?></button>');
                    btn.prop('disabled', false);
                }).catch(function(err) {
                    alert(err.message || 'Check failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Check Originality', 'ai-seo-client')); ?>');
                });
            });
        });
        </script>
        <?php
    }
}

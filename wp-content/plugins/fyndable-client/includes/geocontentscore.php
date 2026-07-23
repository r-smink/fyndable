<?php

namespace SSEOAIClient;

/**
 * GEO Content Scoring (Generative Engine Optimization)
 *
 * Scores content for citability by AI search engines (ChatGPT, Perplexity, Gemini, Copilot).
 * Evaluates factors that make content more likely to be cited by LLMs:
 * - Direct, factual statements
 * - Clear structure with definitional content
 * - Statistical data and specific numbers
 * - Authoritative tone and E-E-A-T signals
 * - Concise, quotable passages
 * - FAQ format alignment
 * - Unique insights vs. generic content
 *
 * Comparable to Profound, Otterly.ai, AthenaHQ.
 */
class GeoContentScore
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
            __('GEO Score', 'ai-seo-client'),
            __('GEO Score', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-geo-score',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/geo/score', [
            'methods' => 'POST',
            'callback' => [$this, 'restScoreContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/geo/score-batch', [
            'methods' => 'POST',
            'callback' => [$this, 'restScoreBatch'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Score content for AI search citability.
     * Returns 0-100 score with breakdown by factor and suggestions.
     */
    public function scoreContent(string $content, string $keyword = '', ?int $postId = null): array
    {
        $plainContent = wp_strip_all_tags($content);
        $wordCount = str_word_count($plainContent);

        $factors = $this->heuristicScoring($content, $plainContent, $wordCount);

        $aiAnalysis = null;
        if ($this->llm->isAvailable() && $wordCount > 100) {
            $aiAnalysis = $this->aiCitabilityAnalysis($content, $keyword);
        }

        $totalScore = 0;
        $maxScore = 0;

        foreach ($factors as $factor) {
            $totalScore += $factor['score'] * $factor['weight'];
            $maxScore += 100 * $factor['weight'];
        }

        if ($aiAnalysis && !is_wp_error($aiAnalysis)) {
            $aiScore = $aiAnalysis['citability_score'] ?? 0;
            $factors['ai_citability'] = [
                'label' => __('AI Citability Assessment', 'ai-seo-client'),
                'score' => $aiScore,
                'weight' => 0.15,
                'detail' => $aiAnalysis['reasoning'] ?? '',
            ];
            $totalScore += $aiScore * 0.15;
            $maxScore += 100 * 0.15;
        }

        $finalScore = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;
        $suggestions = $this->generateSuggestions($factors, $aiAnalysis);

        $result = [
            'score' => $finalScore,
            'grade' => $this->scoreToGrade($finalScore),
            'factors' => $factors,
            'suggestions' => $suggestions,
            'word_count' => $wordCount,
            'keyword' => $keyword,
        ];

        if ($postId) {
            update_post_meta($postId, '_sseo_ai_geo_score', $finalScore);
            update_post_meta($postId, '_sseo_ai_geo_score_details', $factors);
        }

        return $result;
    }

    private function heuristicScoring(string $html, string $plain, int $wordCount): array
    {
        $statCount = preg_match_all('/\b\d+(?:\.\d+)?%?\b/', $plain);
        $statScore = min(100, $statCount * 10);

        $headingCount = preg_match_all('/<h[23]/i', $html);
        $listCount = preg_match_all('/<[uo]l/i', $html);
        $tableCount = preg_match_all('/<table/i', $html);
        $structureScore = min(100, ($headingCount * 10) + ($listCount * 15) + ($tableCount * 20));

        $defCount = preg_match_all('/\b(?:is|are|means|refers to|defined as|describes)\b/i', $plain);
        $defScore = min(100, $defCount * 8);

        $sentences = preg_split('/[.!?]+/', $plain);
        $quotable = 0;
        foreach ($sentences as $sentence) {
            $len = str_word_count(trim($sentence));
            if ($len >= 15 && $len <= 50) $quotable++;
        }
        $quotableScore = $quotable > 0 ? min(100, ($quotable / max(1, count($sentences))) * 200) : 0;

        $questionCount = preg_match_all('/\?/', $plain);
        $faqScore = min(100, $questionCount * 15);

        $depthScore = $wordCount < 300 ? 20 : ($wordCount < 800 ? 50 : ($wordCount < 1500 ? 80 : 100));

        $eeatScore = 0;
        if (preg_match('/<author|by\s+[A-Z]|written by/i', $html)) $eeatScore += 25;
        if (preg_match('/\b(?:20[12]\d|last updated|published)\b/i', $plain)) $eeatScore += 25;
        if (preg_match('/<a\s+href/i', $html)) $eeatScore += 25;
        if (preg_match('/\b(?:study|research|according to|source|data)\b/i', $plain)) $eeatScore += 25;

        $words = str_word_count($plain, 1);
        $uniqueWords = count(array_unique($words));
        $uniquenessScore = count($words) > 0 ? min(100, ($uniqueWords / count($words)) * 150) : 0;

        return [
            'factual_density' => [
                'label' => __('Factual Density (stats, numbers)', 'ai-seo-client'),
                'score' => $statScore, 'weight' => 0.15,
                'detail' => sprintf(__('%d numeric data points', 'ai-seo-client'), $statCount),
            ],
            'structure' => [
                'label' => __('Content Structure', 'ai-seo-client'),
                'score' => $structureScore, 'weight' => 0.15,
                'detail' => sprintf(__('%d headings, %d lists, %d tables', 'ai-seo-client'), $headingCount, $listCount, $tableCount),
            ],
            'definitional' => [
                'label' => __('Definitional Content', 'ai-seo-client'),
                'score' => $defScore, 'weight' => 0.10,
                'detail' => sprintf(__('%d definitional phrases', 'ai-seo-client'), $defCount),
            ],
            'quotable' => [
                'label' => __('Quotable Passages (15-50 words)', 'ai-seo-client'),
                'score' => $quotableScore, 'weight' => 0.15,
                'detail' => sprintf(__('%d quotable sentences', 'ai-seo-client'), $quotable),
            ],
            'faq_format' => [
                'label' => __('FAQ Format Alignment', 'ai-seo-client'),
                'score' => $faqScore, 'weight' => 0.10,
                'detail' => sprintf(__('%d questions found', 'ai-seo-client'), $questionCount),
            ],
            'depth' => [
                'label' => __('Content Depth', 'ai-seo-client'),
                'score' => $depthScore, 'weight' => 0.10,
                'detail' => sprintf(__('%d words', 'ai-seo-client'), $wordCount),
            ],
            'eeat' => [
                'label' => __('E-E-A-T Signals', 'ai-seo-client'),
                'score' => $eeatScore, 'weight' => 0.15,
                'detail' => __('Author, dates, sources, research', 'ai-seo-client'),
            ],
            'uniqueness' => [
                'label' => __('Vocabulary Uniqueness', 'ai-seo-client'),
                'score' => $uniquenessScore, 'weight' => 0.10,
                'detail' => sprintf(__('%d%% unique words', 'ai-seo-client'), round(($uniqueWords / max(1, count($words))) * 100)),
            ],
        ];
    }

    private function aiCitabilityAnalysis(string $content, string $keyword): array|\WP_Error
    {
        $plainContent = substr(wp_strip_all_tags($content), 0, 4000);
        $prompt = "Analyze this content for citability by AI search engines. How likely is an AI assistant to quote this when answering questions about \"{$keyword}\"?\n\nReturn JSON: {\"citability_score\": 0-100, \"reasoning\": \"explanation\", \"strengths\": [], \"weaknesses\": []}\n\nContent:\n{$plainContent}\n\nReturn ONLY the JSON.";

        $response = $this->llm->generateText($prompt, ['use_case' => 'analysis', 'max_tokens' => 1000]);
        if (is_wp_error($response)) return $response;

        $data = json_decode(trim($response), true);
        if (!is_array($data)) return new \WP_Error('parse_error', __('Could not parse AI response', 'ai-seo-client'));
        return $data;
    }

    private function generateSuggestions(array $factors, ?array $aiAnalysis): array
    {
        $suggestions = [];
        foreach ($factors as $key => $factor) {
            if ($factor['score'] < 50) {
                $suggestions[] = [
                    'factor' => $key,
                    'priority' => $factor['score'] < 30 ? 'high' : 'medium',
                    'message' => sprintf('%s: %d/100 â€” %s', $factor['label'], $factor['score'], $factor['detail'] ?? ''),
                ];
            }
        }
        if ($aiAnalysis && !is_wp_error($aiAnalysis)) {
            foreach ($aiAnalysis['weaknesses'] ?? [] as $w) {
                $suggestions[] = ['factor' => 'ai', 'priority' => 'high', 'message' => $w];
            }
        }
        usort($suggestions, fn($a, $b) => ($a['priority'] === 'high' ? 0 : 1) <=> ($b['priority'] === 'high' ? 0 : 1));
        return $suggestions;
    }

    private function scoreToGrade(int $score): string
    {
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    public function restScoreContent(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) ($request->get_param('post_id') ?: 0);
        $content = $request->get_param('content') ?? '';
        $keyword = sanitize_text_field($request->get_param('keyword') ?? '');

        if ($postId) {
            $post = get_post($postId);
            if (!$post) return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'), ['status' => 404]);
            $content = $post->post_content;
            if (empty($keyword)) $keyword = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        }
        if (empty($content)) return new \WP_Error('empty', __('Content required', 'ai-seo-client'), ['status' => 400]);
        return $this->scoreContent($content, $keyword, $postId ?: null);
    }

    public function restScoreBatch(\WP_REST_Request $request): array
    {
        $limit = (int) ($request->get_param('limit') ?: 20);
        $posts = get_posts([
            'post_type' => 'post', 'post_status' => 'publish',
            'posts_per_page' => $limit, 'meta_key' => '_sseo_ai_geo_score',
            'orderby' => 'meta_value_num', 'order' => 'ASC',
        ]);
        $results = [];
        foreach ($posts as $post) {
            $score = get_post_meta($post->ID, '_sseo_ai_geo_score', true);
            if ($score === '' || $score === false) {
                $r = $this->scoreContent($post->post_content, get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true), $post->ID);
                $score = $r['score'];
            }
            $results[] = [
                'post_id' => $post->ID, 'title' => $post->post_title,
                'score' => (int)$score, 'grade' => $this->scoreToGrade((int)$score),
                'edit_url' => get_edit_post_link($post->ID, ''),
            ];
        }
        usort($results, fn($a, $b) => $a['score'] <=> $b['score']);
        return ['posts' => $results, 'count' => count($results)];
    }

    public function renderPage(): void
    {
        ?>
        <style>
            .geo-wrap { max-width: 900px; margin: 20px auto; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .geo-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 20px; }
            .geo-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 20px 30px; border-radius: 12px 12px 0 0; margin: -30px -30px 20px -30px; }
            .geo-header h1 { margin: 0; font-size: 22px; }
            .geo-header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 13px; }
            .geo-score-big { font-size: 48px; font-weight: 800; text-align: center; }
            .geo-grade { font-size: 20px; font-weight: 700; text-align: center; }
            .geo-factor { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
            .geo-factor-bar { width: 200px; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
            .geo-factor-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
            .geo-suggestion { padding: 8px 12px; margin: 4px 0; border-radius: 6px; font-size: 13px; }
            .geo-suggestion.high { background: #fee2e2; color: #dc2626; }
            .geo-suggestion.medium { background: #fef3c7; color: #d97706; }
        </style>
        <div class="wrap geo-wrap">
            <div class="geo-card">
                <div class="geo-header">
                    <h1>ðŸ¤– <?php esc_html_e('GEO Content Score', 'ai-seo-client'); ?></h1>
                    <p><?php esc_html_e('Score your content for citability by AI search engines (ChatGPT, Perplexity, Gemini).', 'ai-seo-client'); ?></p>
                </div>
                <div style="display:flex;gap:20px;align-items:start;flex-wrap:wrap;">
                    <div style="flex:1;min-width:300px;">
                        <label style="font-weight:600;display:block;margin-bottom:6px;"><?php esc_html_e('Select Post to Score', 'ai-seo-client'); ?></label>
                        <select id="geo-post-select" style="width:100%;">
                            <option value=""><?php esc_html_e('â€” Choose a post â€”', 'ai-seo-client'); ?></option>
                            <?php
                            $posts = get_posts(['post_type' => 'post', 'post_status' => ['publish', 'draft'], 'posts_per_page' => 100]);
                            foreach ($posts as $p) {
                                echo '<option value="' . $p->ID . '">' . esc_html($p->post_title) . '</option>';
                            }
                            ?>
                        </select>
                        <button class="button button-primary" id="geo-score-btn" style="margin-top:10px;"><?php esc_html_e('Score Content', 'ai-seo-client'); ?></button>
                        <button class="button" id="geo-batch-btn" style="margin-top:10px;margin-left:8px;"><?php esc_html_e('Score All Posts', 'ai-seo-client'); ?></button>
                    </div>
                    <div id="geo-result" style="flex:2;min-width:300px;display:none;">
                        <div class="geo-score-big" id="geo-score-num">â€”</div>
                        <div class="geo-grade" id="geo-grade">â€”</div>
                        <div id="geo-factors" style="margin-top:20px;"></div>
                        <div id="geo-suggestions" style="margin-top:20px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#geo-score-btn').on('click', function() {
                var postId = $('#geo-post-select').val();
                if (!postId) { alert('<?php echo esc_js(__("Select a post first", "ai-seo-client")); ?>'); return; }
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__("Scoring...", "ai-seo-client")); ?>');
                wp.apiFetch({
                    path: '/sseo-ai/v1/geo/score',
                    method: 'POST',
                    data: { post_id: postId }
                }).then(function(r) {
                    renderResult(r);
                }).catch(function(err) {
                    alert(err.message || 'Error');
                }).finally(function() {
                    btn.prop('disabled', false).text('<?php echo esc_js(__("Score Content", "ai-seo-client")); ?>');
                });
            });

            $('#geo-batch-btn').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__("Scoring all...", "ai-seo-client")); ?>');
                wp.apiFetch({
                    path: '/sseo-ai/v1/geo/score-batch',
                    method: 'POST',
                    data: { limit: 50 }
                }).then(function(r) {
                    var html = '<h3><?php echo esc_js(__("All Posts by GEO Score", "ai-seo-client")); ?></h3><table class="wp-list-table widefat striped"><thead><tr><th>Title</th><th>Score</th><th>Grade</th><th></th></tr></thead><tbody>';
                    r.posts.forEach(function(p) {
                        var color = p.score >= 70 ? '#16a34a' : (p.score >= 50 ? '#d97706' : '#dc2626');
                        html += '<tr><td>' + p.title + '</td><td style="color:' + color + ';font-weight:700;">' + p.score + '</td><td>' + p.grade + '</td><td><a href="' + p.edit_url + '" class="button button-small">Edit</a></td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#geo-result').html(html).show();
                }).finally(function() {
                    btn.prop('disabled', false).text('<?php echo esc_js(__("Score All Posts", "ai-seo-client")); ?>');
                });
            });

            function renderResult(r) {
                var color = r.score >= 70 ? '#16a34a' : (r.score >= 50 ? '#d97706' : '#dc2626');
                $('#geo-score-num').text(r.score).css('color', color);
                $('#geo-grade').text('Grade: ' + r.grade).css('color', color);
                var fh = '';
                Object.entries(r.factors).forEach(function(e) {
                    var f = e[1];
                    var fc = f.score >= 70 ? '#16a34a' : (f.score >= 50 ? '#d97706' : '#dc2626');
                    fh += '<div class="geo-factor"><div><strong>' + f.label + '</strong><div style="font-size:11px;color:#64748b;">' + (f.detail || '') + '</div></div><div style="display:flex;align-items:center;gap:10px;"><div class="geo-factor-bar"><div class="geo-factor-fill" style="width:' + f.score + '%;background:' + fc + ';"></div></div><span style="font-weight:700;width:40px;text-align:right;">' + f.score + '</span></div></div>';
                });
                $('#geo-factors').html(fh);
                var sh = '<h4><?php echo esc_js(__("Suggestions", "ai-seo-client")); ?></h4>';
                r.suggestions.forEach(function(s) {
                    sh += '<div class="geo-suggestion ' + s.priority + '">' + s.message + '</div>';
                });
                $('#geo-suggestions').html(sh);
                $('#geo-result').show();
            }
        });
        </script>
        <?php
    }
}

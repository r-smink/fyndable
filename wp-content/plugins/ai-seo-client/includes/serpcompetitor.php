<?php

namespace SSEOAIClient;

/**
 * SERP Competitor Content Analysis
 * 
 * Analyzes top-ranking pages for a keyword and compares them
 * against your content. Shows topical gaps, content structure
 * differences, and competitive advantages — like MarketMuse Heatmap
 * and NeuronWriter competitor analysis.
 */
class SerpCompetitor
{
    private Settings $settings;
    private LlmClient $llm;
    private DashboardAPI $dashboardAPI;

    public function __construct(Settings $settings, LlmClient $llm, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->dashboardAPI = $dashboardAPI;
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
            __('SERP Analysis', 'ai-seo-client'),
            __('SERP Analysis', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-serp-analysis',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/serp/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyzeSerp'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'country' => ['type' => 'string', 'default' => 'us'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/serp/compare', [
            'methods' => 'POST',
            'callback' => [$this, 'restCompareContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/serp/gap-analysis', [
            'methods' => 'POST',
            'callback' => [$this, 'restGapAnalysis'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    /**
     * Full SERP competitor analysis for a keyword.
     * Analyzes the top results' content structure, topics covered, and patterns.
     */
    public function analyzeSerp(string $keyword, string $country = 'us'): array|\WP_Error
    {
        $cacheKey = 'aiseo_serp_analysis_' . md5($keyword . $country);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Get SERP results via proxy
        $serpResults = $this->fetchSerpResults($keyword, $country);

        // AI-powered deep analysis of competitor landscape
        $analysis = $this->aiAnalyzeCompetitors($keyword, $serpResults);
        if (is_wp_error($analysis)) {
            return $analysis;
        }

        $result = [
            'keyword' => $keyword,
            'analyzed_at' => current_time('mysql'),
            'serp_results' => $this->formatSerpResults($serpResults),
            'competitor_profiles' => $analysis['competitor_profiles'] ?? [],
            'common_topics' => $analysis['common_topics'] ?? [],
            'content_patterns' => $analysis['content_patterns'] ?? [],
            'avg_word_count' => (int) ($analysis['avg_word_count'] ?? 1500),
            'avg_headings' => (int) ($analysis['avg_headings'] ?? 8),
            'serp_features' => $analysis['serp_features'] ?? [],
            'topic_heatmap' => $analysis['topic_heatmap'] ?? [],
            'winning_patterns' => $analysis['winning_patterns'] ?? [],
            'content_gaps' => $analysis['content_gaps'] ?? [],
        ];

        set_transient($cacheKey, $result, 3 * DAY_IN_SECONDS);
        return $result;
    }

    private function fetchSerpResults(string $keyword, string $country): array
    {
        $response = $this->dashboardAPI->request('serp/search', [
            'keyword' => $keyword,
            'country' => $country,
            'num' => 20,
        ]);

        if (!is_wp_error($response) && !empty($response['results'])) {
            return $response['results'];
        }

        return [];
    }

    private function formatSerpResults(array $results): array
    {
        return array_map(function ($r, $i) {
            return [
                'position' => $r['position'] ?? ($i + 1),
                'title' => sanitize_text_field($r['title'] ?? ''),
                'url' => esc_url_raw($r['link'] ?? $r['url'] ?? ''),
                'domain' => parse_url($r['link'] ?? $r['url'] ?? '', PHP_URL_HOST) ?: '',
                'snippet' => sanitize_text_field($r['snippet'] ?? $r['description'] ?? ''),
            ];
        }, $results, array_keys($results));
    }

    /**
     * AI-powered competitor landscape analysis.
     */
    private function aiAnalyzeCompetitors(string $keyword, array $serpResults): array|\WP_Error
    {
        $serpContext = '';
        if (!empty($serpResults)) {
            $lines = [];
            foreach (array_slice($serpResults, 0, 15) as $i => $r) {
                $title = $r['title'] ?? '';
                $snippet = $r['snippet'] ?? $r['description'] ?? '';
                $url = $r['link'] ?? $r['url'] ?? '';
                $domain = parse_url($url, PHP_URL_HOST) ?: '';
                $lines[] = ($i + 1) . ". [{$domain}] {$title} — {$snippet}";
            }
            $serpContext = implode("\n", $lines);
        }

        $prompt = <<<PROMPT
You are a competitive SEO analyst (like MarketMuse). Analyze the SERP landscape for "{$keyword}".

Top-ranking pages:
{$serpContext}

Provide a comprehensive competitive analysis. Return JSON only (no markdown):
{{
    "competitor_profiles": [
        {{
            "position": 1,
            "domain": "example.com",
            "title": "Page Title",
            "content_type": "guide|listicle|comparison|tutorial|product|review",
            "estimated_word_count": 2000,
            "strengths": ["strength 1"],
            "weaknesses": ["weakness 1"],
            "unique_angle": "What makes this result unique"
        }}
    ],
    "common_topics": ["topic covered by most top pages"],
    "content_patterns": {{
        "dominant_format": "guide|listicle|etc",
        "avg_word_count": 1800,
        "avg_headings": 10,
        "uses_faq": true,
        "uses_tables": false,
        "uses_video": false,
        "uses_images": true
    }},
    "topic_heatmap": [
        {{"topic": "subtopic name", "coverage_pct": 80, "importance": "high|medium|low"}}
    ],
    "winning_patterns": ["Pattern that top 3 results share"],
    "content_gaps": ["Topic that no competitor covers well — opportunity for you"],
    "serp_features": ["featured_snippet", "people_also_ask", "video_carousel"],
    "avg_word_count": 1800,
    "avg_headings": 10
}}

Analyze at least 8 competitor profiles if data available.
Include 15-25 topics in the heatmap.
Identify 5-8 content gaps/opportunities.
Return ONLY valid JSON.
PROMPT;

        $result = $this->llm->call($prompt, null, null, 3000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);

        $data = json_decode(trim($text), true);
        if (!$data) {
            return new \WP_Error('parse_error', __('Failed to parse competitor analysis', 'ai-seo-client'));
        }

        return $data;
    }

    /**
     * Compare user content against competitor landscape.
     */
    public function compareContent(string $keyword, string $content): array|\WP_Error
    {
        $serpAnalysis = $this->analyzeSerp($keyword);
        if (is_wp_error($serpAnalysis)) {
            return $serpAnalysis;
        }

        $plainContent = wp_strip_all_tags($content);
        $contentLower = strtolower($plainContent);
        $wordCount = str_word_count($plainContent);
        $headingCount = preg_match_all('/<h[2-6][^>]*>/i', $content);

        // Check topic heatmap coverage
        $heatmap = $serpAnalysis['topic_heatmap'] ?? [];
        $topicCoverage = [];
        foreach ($heatmap as $topic) {
            $topicLower = strtolower($topic['topic']);
            $words = array_filter(explode(' ', $topicLower), fn($w) => strlen($w) > 2);
            $matchCount = 0;
            foreach ($words as $w) {
                if (stripos($contentLower, $w) !== false) $matchCount++;
            }
            $covered = count($words) > 0 ? ($matchCount / count($words)) >= 0.6 : false;
            $topicCoverage[] = [
                'topic' => $topic['topic'],
                'importance' => $topic['importance'] ?? 'medium',
                'competitor_coverage' => $topic['coverage_pct'] ?? 0,
                'your_coverage' => $covered,
            ];
        }

        $coveredCount = count(array_filter($topicCoverage, fn($t) => $t['your_coverage']));
        $totalTopics = count($topicCoverage);
        $coverageRatio = $totalTopics > 0 ? $coveredCount / $totalTopics : 0;

        // Content gap identification
        $gaps = $serpAnalysis['content_gaps'] ?? [];
        $addressedGaps = [];
        foreach ($gaps as $gap) {
            $gapWords = array_filter(explode(' ', strtolower($gap)), fn($w) => strlen($w) > 3);
            $matchCount = 0;
            foreach ($gapWords as $w) {
                if (stripos($contentLower, $w) !== false) $matchCount++;
            }
            $addressed = count($gapWords) > 0 ? ($matchCount / count($gapWords)) >= 0.5 : false;
            $addressedGaps[] = ['gap' => $gap, 'addressed' => $addressed];
        }

        // Competitive position estimate
        $avgWords = $serpAnalysis['avg_word_count'] ?? 1500;
        $avgHeadings = $serpAnalysis['avg_headings'] ?? 8;
        $wordScore = min(100, ($wordCount / max(1, $avgWords)) * 100);
        $headingScore = min(100, ($headingCount / max(1, $avgHeadings)) * 100);
        $topicScore = $coverageRatio * 100;

        $competitiveScore = (int) round($topicScore * 0.5 + $wordScore * 0.3 + $headingScore * 0.2);
        $competitiveScore = max(0, min(100, $competitiveScore));

        return [
            'competitive_score' => $competitiveScore,
            'your_stats' => [
                'word_count' => $wordCount,
                'heading_count' => $headingCount,
            ],
            'competitor_avg' => [
                'word_count' => $avgWords,
                'heading_count' => $avgHeadings,
            ],
            'topic_coverage' => $topicCoverage,
            'coverage_ratio' => round($coverageRatio * 100),
            'content_gaps' => $addressedGaps,
            'winning_patterns' => $serpAnalysis['winning_patterns'] ?? [],
            'content_patterns' => $serpAnalysis['content_patterns'] ?? [],
        ];
    }

    /**
     * Deep content gap analysis — what competitors cover that you don't.
     */
    public function gapAnalysis(string $keyword, string $content): array|\WP_Error
    {
        $excerpt = mb_substr(wp_strip_all_tags($content), 0, 2000);

        $prompt = <<<PROMPT
You are a competitive content analyst. Compare this content against what top-ranking pages typically cover for "{$keyword}".

User's content excerpt:
"{$excerpt}"

Identify:
1. Topics competitors cover that this content MISSES
2. Topics this content covers BETTER than most competitors
3. Unique angles this content could add to stand out
4. Structural improvements needed

Return JSON only (no markdown):
{{
    "missing_topics": [
        {{"topic": "topic name", "importance": "critical|important|nice_to_have", "reason": "Why this matters"}}
    ],
    "strengths": [
        {{"topic": "topic name", "reason": "Why this is strong"}}
    ],
    "unique_opportunities": [
        {{"idea": "Content idea", "reason": "Why this would differentiate"}}
    ],
    "structural_suggestions": [
        {{"suggestion": "Add comparison table", "impact": "high|medium|low"}}
    ],
    "recommended_additions_word_count": 500
}}

Be specific and actionable. Identify 5-10 missing topics, 3-5 strengths, 3-5 opportunities.
Return ONLY valid JSON.
PROMPT;

        $result = $this->llm->call($prompt, null, null, 2000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);

        $data = json_decode(trim($text), true);
        if (!$data) {
            return new \WP_Error('parse_error', __('Failed to parse gap analysis', 'ai-seo-client'));
        }

        return $data;
    }

    // REST handlers
    public function restAnalyzeSerp(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->analyzeSerp(
            sanitize_text_field($request->get_param('keyword')),
            sanitize_text_field($request->get_param('country') ?: 'us')
        );
    }

    public function restCompareContent(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->compareContent(
            sanitize_text_field($request->get_param('keyword')),
            $request->get_param('content')
        );
    }

    public function restGapAnalysis(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->gapAnalysis(
            sanitize_text_field($request->get_param('keyword')),
            $request->get_param('content')
        );
    }

    /**
     * Render SERP Analysis admin page.
     */
    public function renderPage(): void
    {
        ?>
        <div class="wrap aiseo-modern">
            <h1><?php esc_html_e('SERP Competitor Analysis', 'ai-seo-client'); ?></h1>
            <p class="description"><?php esc_html_e('Analyze the competitive landscape for any keyword. See what top-ranking pages cover, identify gaps, and find winning patterns.', 'ai-seo-client'); ?></p>

            <div style="max-width:1400px; margin-top:20px;">
                <div class="postbox" style="padding:20px;">
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="serp-keyword" class="regular-text" style="flex:1;" placeholder="<?php esc_attr_e('Enter target keyword...', 'ai-seo-client'); ?>">
                        <button type="button" class="button button-primary" id="serp-analyze-btn"><?php esc_html_e('Analyze SERP', 'ai-seo-client'); ?></button>
                    </div>
                </div>

                <div id="serp-results" style="display:none; margin-top:20px;">
                    <!-- Overview Cards -->
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:15px; margin-bottom:20px;">
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="serp-avg-words" style="font-size:28px;font-weight:bold;color:#2563eb;">—</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Avg Word Count', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="serp-avg-headings" style="font-size:28px;font-weight:bold;color:#059669;">—</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Avg Headings', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="serp-format" style="font-size:20px;font-weight:bold;color:#7c3aed;">—</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Dominant Format', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="serp-features" style="font-size:14px;font-weight:bold;color:#d97706;">—</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('SERP Features', 'ai-seo-client'); ?></div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <!-- Competitor Profiles -->
                        <div class="postbox" style="padding:15px;">
                            <h3 style="margin-top:0;"><?php esc_html_e('Competitor Profiles', 'ai-seo-client'); ?></h3>
                            <div id="serp-competitors" style="max-height:500px;overflow-y:auto;"></div>
                        </div>

                        <!-- Topic Heatmap -->
                        <div class="postbox" style="padding:15px;">
                            <h3 style="margin-top:0;"><?php esc_html_e('Topic Heatmap', 'ai-seo-client'); ?></h3>
                            <p style="font-size:12px;color:#666;"><?php esc_html_e('How thoroughly the top results cover each subtopic', 'ai-seo-client'); ?></p>
                            <div id="serp-heatmap" style="max-height:500px;overflow-y:auto;"></div>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:20px;">
                        <!-- Winning Patterns -->
                        <div class="postbox" style="padding:15px;">
                            <h3 style="margin-top:0;"><?php esc_html_e('Winning Patterns', 'ai-seo-client'); ?></h3>
                            <ul id="serp-patterns" style="list-style:none;padding:0;"></ul>
                        </div>

                        <!-- Content Gaps / Opportunities -->
                        <div class="postbox" style="padding:15px;">
                            <h3 style="margin-top:0;"><?php esc_html_e('Content Gaps & Opportunities', 'ai-seo-client'); ?></h3>
                            <ul id="serp-gaps" style="list-style:none;padding:0;"></ul>
                        </div>
                    </div>

                    <!-- Compare Your Content -->
                    <div class="postbox" style="padding:20px; margin-top:20px;">
                        <h3 style="margin-top:0;"><?php esc_html_e('Compare Your Content', 'ai-seo-client'); ?></h3>
                        <p class="description"><?php esc_html_e('Paste your content to see how it stacks up against the competition.', 'ai-seo-client'); ?></p>
                        <textarea id="serp-content" rows="8" class="large-text" placeholder="<?php esc_attr_e('Paste your content here...', 'ai-seo-client'); ?>"></textarea>
                        <div style="display:flex;gap:10px;margin-top:10px;">
                            <button type="button" class="button button-primary" id="serp-compare-btn"><?php esc_html_e('Compare', 'ai-seo-client'); ?></button>
                            <button type="button" class="button" id="serp-gap-btn"><?php esc_html_e('Deep Gap Analysis', 'ai-seo-client'); ?></button>
                        </div>
                        <div id="serp-compare-result" style="margin-top:15px;display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var serpData = null;

            $('#serp-analyze-btn').on('click', function() {
                var kw = $('#serp-keyword').val().trim();
                if (!kw) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Analyzing...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/serp/analyze',
                    method: 'POST',
                    data: { keyword: kw }
                }).then(function(data) {
                    serpData = data;
                    renderSerpAnalysis(data);
                    $('#serp-results').show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Analyze SERP', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Analyze SERP', 'ai-seo-client')); ?>');
                });
            });

            function renderSerpAnalysis(data) {
                var cp = data.content_patterns || {};
                $('#serp-avg-words').text(data.avg_word_count || cp.avg_word_count || '—');
                $('#serp-avg-headings').text(data.avg_headings || cp.avg_headings || '—');
                $('#serp-format').text(cp.dominant_format || '—');
                $('#serp-features').text((data.serp_features || []).slice(0, 3).join(', ') || '—');

                // Competitors
                var compHtml = '';
                (data.competitor_profiles || []).slice(0, 10).forEach(function(c) {
                    var typeColors = {guide:'#2563eb',listicle:'#059669',comparison:'#7c3aed',tutorial:'#d97706',product:'#dc2626',review:'#0891b2'};
                    compHtml += '<div style="padding:10px;margin-bottom:8px;background:#f9f9f9;border-radius:4px;border-left:3px solid ' + (typeColors[c.content_type]||'#999') + ';">' +
                        '<div style="display:flex;justify-content:space-between;"><strong>#' + c.position + ' ' + (c.domain || '') + '</strong>' +
                        '<span style="font-size:11px;padding:2px 6px;background:' + (typeColors[c.content_type]||'#e0e0e0') + ';color:#fff;border-radius:3px;">' + (c.content_type || '') + '</span></div>' +
                        '<div style="font-size:13px;color:#333;margin:4px 0;">' + (c.title || '') + '</div>' +
                        '<div style="font-size:11px;color:#666;">~' + (c.estimated_word_count || '?') + ' words</div>' +
                        (c.unique_angle ? '<div style="font-size:11px;color:#059669;margin-top:4px;">💡 ' + c.unique_angle + '</div>' : '') +
                        '</div>';
                });
                $('#serp-competitors').html(compHtml);

                // Heatmap
                var heatHtml = '';
                (data.topic_heatmap || []).forEach(function(t) {
                    var pct = t.coverage_pct || 0;
                    var impColors = {high:'#dc2626',medium:'#d97706',low:'#059669'};
                    heatHtml += '<div style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid #f0f0f0;">' +
                        '<span style="flex:1;font-size:12px;">' + t.topic + '</span>' +
                        '<div style="width:120px;background:#e0e0e0;border-radius:3px;height:8px;">' +
                        '<div style="width:' + pct + '%;background:' + (impColors[t.importance]||'#999') + ';height:8px;border-radius:3px;"></div></div>' +
                        '<span style="font-size:11px;width:35px;text-align:right;color:#666;">' + pct + '%</span>' +
                        '</div>';
                });
                $('#serp-heatmap').html(heatHtml);

                // Patterns
                var patHtml = '';
                (data.winning_patterns || []).forEach(function(p) {
                    patHtml += '<li style="padding:6px 10px;margin:3px 0;background:#ecfdf5;border-left:3px solid #059669;border-radius:0 4px 4px 0;font-size:13px;">✓ ' + p + '</li>';
                });
                $('#serp-patterns').html(patHtml);

                // Gaps
                var gapHtml = '';
                (data.content_gaps || []).forEach(function(g) {
                    gapHtml += '<li style="padding:6px 10px;margin:3px 0;background:#fef3c7;border-left:3px solid #d97706;border-radius:0 4px 4px 0;font-size:13px;">🎯 ' + g + '</li>';
                });
                $('#serp-gaps').html(gapHtml);
            }

            // Compare
            $('#serp-compare-btn').on('click', function() {
                var content = $('#serp-content').val().trim();
                var kw = $('#serp-keyword').val().trim();
                if (!content || !kw) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Comparing...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/serp/compare',
                    method: 'POST',
                    data: { keyword: kw, content: content }
                }).then(function(res) {
                    var sc = res.competitive_score;
                    var color = sc >= 80 ? '#00a32a' : (sc >= 60 ? '#059669' : (sc >= 40 ? '#dba617' : '#d63638'));
                    var html = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;">' +
                        '<div style="text-align:center;padding:15px;background:#f9f9f9;border-radius:8px;">' +
                        '<div style="font-size:36px;font-weight:bold;color:' + color + ';">' + sc + '</div>' +
                        '<div style="font-size:12px;color:#666;">Competitive Score</div></div>' +
                        '<div style="text-align:center;padding:15px;background:#f9f9f9;border-radius:8px;">' +
                        '<div style="font-size:18px;font-weight:bold;">' + res.your_stats.word_count + ' <span style="color:#999;font-weight:normal;">/ ' + res.competitor_avg.word_count + '</span></div>' +
                        '<div style="font-size:12px;color:#666;">Your Words / Avg</div></div>' +
                        '<div style="text-align:center;padding:15px;background:#f9f9f9;border-radius:8px;">' +
                        '<div style="font-size:18px;font-weight:bold;">' + res.coverage_ratio + '%</div>' +
                        '<div style="font-size:12px;color:#666;">Topic Coverage</div></div></div>';

                    // Topic coverage detail
                    html += '<h4 style="margin-top:15px;">Topic Coverage</h4>';
                    (res.topic_coverage || []).forEach(function(t) {
                        var covered = t.your_coverage;
                        html += '<div style="display:flex;align-items:center;gap:8px;padding:3px 0;font-size:12px;">' +
                            '<span style="color:' + (covered ? '#00a32a' : '#d63638') + ';width:16px;">' + (covered ? '✓' : '✕') + '</span>' +
                            '<span style="flex:1;">' + t.topic + '</span>' +
                            '<span style="color:#999;">' + t.competitor_coverage + '% competitors</span></div>';
                    });

                    $('#serp-compare-result').html(html).show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Compare', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Compare', 'ai-seo-client')); ?>');
                });
            });

            // Deep Gap
            $('#serp-gap-btn').on('click', function() {
                var content = $('#serp-content').val().trim();
                var kw = $('#serp-keyword').val().trim();
                if (!content || !kw) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Analyzing gaps...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/serp/gap-analysis',
                    method: 'POST',
                    data: { keyword: kw, content: content }
                }).then(function(res) {
                    var html = '<h4 style="margin-top:15px;">Missing Topics</h4>';
                    (res.missing_topics || []).forEach(function(t) {
                        var impColors = {critical:'#d63638',important:'#d97706',nice_to_have:'#059669'};
                        html += '<div style="padding:8px;margin:4px 0;border-left:3px solid ' + (impColors[t.importance]||'#999') + ';background:#f9f9f9;border-radius:0 4px 4px 0;">' +
                            '<strong>' + t.topic + '</strong> <span style="font-size:11px;color:' + (impColors[t.importance]||'#999') + ';">[' + t.importance + ']</span><br>' +
                            '<span style="font-size:12px;color:#666;">' + t.reason + '</span></div>';
                    });
                    html += '<h4 style="margin-top:15px;">Your Strengths</h4>';
                    (res.strengths || []).forEach(function(s) {
                        html += '<div style="padding:8px;margin:4px 0;border-left:3px solid #00a32a;background:#edfaef;border-radius:0 4px 4px 0;">' +
                            '<strong>' + s.topic + '</strong><br><span style="font-size:12px;color:#666;">' + s.reason + '</span></div>';
                    });
                    html += '<h4 style="margin-top:15px;">Unique Opportunities</h4>';
                    (res.unique_opportunities || []).forEach(function(o) {
                        html += '<div style="padding:8px;margin:4px 0;border-left:3px solid #7c3aed;background:#f5f3ff;border-radius:0 4px 4px 0;">' +
                            '<strong>💡 ' + o.idea + '</strong><br><span style="font-size:12px;color:#666;">' + o.reason + '</span></div>';
                    });

                    $('#serp-compare-result').html(html).show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Deep Gap Analysis', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Deep Gap Analysis', 'ai-seo-client')); ?>');
                });
            });
        });
        </script>
        <?php
    }
}

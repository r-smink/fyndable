<?php

namespace SSEOAIClient;

/**
 * Content Optimizer — NLP Content Score + Topic Model
 * 
 * The MarketMuse / NeuronWriter / SurferSEO killer feature.
 * Generates a semantic topic model for any keyword, then scores content
 * against that model in real-time. Shows which terms are covered,
 * missing, and overused — with a 0-100 Content Score.
 * 
 * Architecture:
 * - AI generates the topic model (related terms, entities, questions)
 *   based on SERP competitor analysis via DashboardAPI proxy
 * - Content is scored client-side against the cached topic model
 * - Results cached per keyword for 7 days
 */
class ContentOptimizer
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
        add_filter('cron_schedules', [$this, 'addCronSchedules']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        // Calibration cron: weekly correlation of term coverage vs rankings
        if (!wp_next_scheduled('sseo_ai_calibration_run')) {
            wp_schedule_event(strtotime('next sunday 2:00 am'), 'weekly', 'sseo_ai_calibration_run');
        }
        add_action('sseo_ai_calibration_run', [$this, 'runCalibration']);
    }

    public function addCronSchedules(array $schedules): array
    {
        $schedules['weekly'] = ['interval' => 7 * DAY_IN_SECONDS, 'display' => __('Once Weekly', 'ai-seo-client')];
        return $schedules;
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Content Optimizer', 'ai-seo-client'),
            __('Content Optimizer', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-optimizer',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/optimizer/topic-model', [
            'methods' => 'POST',
            'callback' => [$this, 'restGetTopicModel'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'country' => ['type' => 'string', 'default' => 'us'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/optimizer/score', [
            'methods' => 'POST',
            'callback' => [$this, 'restScoreContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
                'title' => ['type' => 'string', 'default' => ''],
                'post_id' => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/optimizer/calibration/(?P<id>\\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCalibration'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/optimizer/suggest-terms', [
            'methods' => 'POST',
            'callback' => [$this, 'restSuggestTermInsertions'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
                'missing_terms' => ['type' => 'array', 'default' => []],
            ],
        ]);
    }

    /**
     * Generate or retrieve cached topic model for a keyword.
     * This is the core NLP model — comparable to MarketMuse Topic Model.
     */
    public function getTopicModel(string $keyword, string $country = 'us'): array|\WP_Error
    {
        $cacheKey = 'aiseo_topic_model_' . md5($keyword . $country);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Step 1: Get SERP data via DashboardAPI proxy
        $serpData = $this->fetchSerpData($keyword, $country);

        // Step 2: Build topic model via AI analysis of SERP + keyword
        $model = $this->buildTopicModel($keyword, $serpData);
        if (is_wp_error($model)) {
            return $model;
        }

        // Step 3: Cache for 7 days
        set_transient($cacheKey, $model, 7 * DAY_IN_SECONDS);

        return $model;
    }

    /**
     * Fetch SERP data — titles, snippets, URLs of top results.
     */
    private function fetchSerpData(string $keyword, string $country): array
    {
        // Try via DashboardAPI SERP proxy
        $response = $this->dashboardAPI->request('serp/search', [
            'keyword' => $keyword,
            'country' => $country,
            'num' => 20,
        ]);

        if (!is_wp_error($response) && !empty($response['results'])) {
            return $response['results'];
        }

        // Fallback: return empty — AI will still generate a model from training data
        return [];
    }

    /**
     * Build the NLP topic model using AI.
     * This generates the term list, weights, entities, and questions
     * that top-ranking content uses — the core of content optimization.
     */
    private function buildTopicModel(string $keyword, array $serpResults): array|\WP_Error
    {
        $serpContext = '';
        if (!empty($serpResults)) {
            $serpLines = [];
            foreach (array_slice($serpResults, 0, 15) as $i => $r) {
                $title = $r['title'] ?? '';
                $snippet = $r['snippet'] ?? $r['description'] ?? '';
                $serpLines[] = ($i + 1) . ". {$title} — {$snippet}";
            }
            $serpContext = "Top-ranking pages in Google for \"{$keyword}\":\n" . implode("\n", $serpLines);
        }

        $prompt = <<<PROMPT
You are an NLP content optimization expert (like MarketMuse/SurferSEO). Analyze the keyword "{$keyword}" and generate a comprehensive topic model.

{$serpContext}

Generate a complete topic model for content optimization. Return JSON only (no markdown):
{
    "primary_terms": [
        {"term": "exact term", "weight": 1-10, "recommended_count": 2-8, "category": "core|supporting|entity|question"}
    ],
    "entities": ["named entity 1", "named entity 2"],
    "questions_to_answer": ["Question 1?", "Question 2?"],
    "content_structure": {
        "recommended_word_count": 1500,
        "recommended_headings": 8,
        "recommended_images": 3,
        "recommended_paragraphs": 15
    },
    "search_intent": "informational|transactional|commercial|navigational",
    "difficulty_estimate": 1-100,
    "serp_features": ["featured_snippet", "people_also_ask", "knowledge_panel"]
}

Requirements for primary_terms:
- Include 30-50 semantically related terms/phrases
- "core" terms: The main topic and close synonyms (weight 7-10)
- "supporting" terms: Related concepts searchers expect (weight 4-6)
- "entity" terms: Named entities, brands, tools, people (weight 3-5)
- "question" terms: Question phrases users search (weight 2-4)
- recommended_count = how many times each term should appear in optimal content
- Terms should be 1-4 words each
- Include both head terms and long-tail related phrases
- Think about what ALL top-ranking pages cover — the semantic footprint

Return ONLY valid JSON.
PROMPT;

        $result = $this->llm->call($prompt, null, null, 3000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!$data || !isset($data['primary_terms'])) {
            return new \WP_Error('parse_error', __('Failed to generate topic model', 'ai-seo-client'));
        }

        // Sanitize and structure
        $model = [
            'keyword' => $keyword,
            'generated_at' => current_time('mysql'),
            'primary_terms' => $this->sanitizeTerms($data['primary_terms'] ?? []),
            'entities' => array_map('sanitize_text_field', $data['entities'] ?? []),
            'questions' => array_map('sanitize_text_field', $data['questions_to_answer'] ?? []),
            'content_structure' => [
                'recommended_word_count' => (int) ($data['content_structure']['recommended_word_count'] ?? 1500),
                'recommended_headings' => (int) ($data['content_structure']['recommended_headings'] ?? 8),
                'recommended_images' => (int) ($data['content_structure']['recommended_images'] ?? 3),
                'recommended_paragraphs' => (int) ($data['content_structure']['recommended_paragraphs'] ?? 15),
            ],
            'search_intent' => sanitize_text_field($data['search_intent'] ?? 'informational'),
            'difficulty_estimate' => max(1, min(100, (int) ($data['difficulty_estimate'] ?? 50))),
            'serp_features' => array_map('sanitize_text_field', $data['serp_features'] ?? []),
            'serp_results' => array_slice(array_map(function ($r) {
                return [
                    'title' => sanitize_text_field($r['title'] ?? ''),
                    'url' => esc_url_raw($r['link'] ?? $r['url'] ?? ''),
                    'snippet' => sanitize_text_field($r['snippet'] ?? $r['description'] ?? ''),
                ];
            }, $serpResults), 0, 20),
        ];

        return $model;
    }

    private function sanitizeTerms(array $terms): array
    {
        $sanitized = [];
        foreach ($terms as $t) {
            if (empty($t['term'])) continue;
            $sanitized[] = [
                'term' => sanitize_text_field(strtolower($t['term'])),
                'weight' => max(1, min(10, (int) ($t['weight'] ?? 5))),
                'recommended_count' => max(1, min(20, (int) ($t['recommended_count'] ?? 3))),
                'category' => in_array($t['category'] ?? '', ['core', 'supporting', 'entity', 'question'])
                    ? $t['category'] : 'supporting',
            ];
        }

        // Sort by weight descending
        usort($sanitized, fn($a, $b) => $b['weight'] <=> $a['weight']);
        return $sanitized;
    }

    /**
     * Score content against a topic model — the real-time content score.
     * Returns 0-100 score + per-term coverage analysis.
     */
    public function scoreContent(string $keyword, string $content, string $title = '', ?int $postId = null): array|\WP_Error
    {
        $model = $this->getTopicModel($keyword);
        if (is_wp_error($model)) {
            return $model;
        }

        // Calibration: store model and term coverage per post when post_id is known
        if ($postId && get_post($postId)) {
            update_post_meta($postId, '_sseo_ai_topic_model', $model);
        }

        $plainContent = strtolower(wp_strip_all_tags($content));
        $plainTitle = strtolower($title);
        $fullText = $plainTitle . ' ' . $plainContent;

        $wordCount = str_word_count($plainContent);
        $terms = $model['primary_terms'] ?? [];
        $totalWeight = 0;
        $earnedWeight = 0;
        $termResults = [];

        foreach ($terms as $term) {
            $termLower = strtolower($term['term']);
            $count = substr_count($fullText, $termLower);
            $recommended = $term['recommended_count'];
            $weight = $term['weight'];
            $totalWeight += $weight;

            // Calculate term score
            if ($count === 0) {
                $termScore = 0;
                $status = 'missing';
            } elseif ($count >= $recommended * 3) {
                // Overused — penalize slightly
                $termScore = 0.6;
                $status = 'overused';
            } elseif ($count >= $recommended * 0.5 && $count <= $recommended * 2) {
                // Good range
                $termScore = 1.0;
                $status = 'good';
            } else {
                // Partial coverage
                $termScore = min(1, $count / max(1, $recommended));
                $status = $count < $recommended * 0.5 ? 'low' : 'good';
            }

            $earnedWeight += $termScore * $weight;

            $termResults[] = [
                'term' => $term['term'],
                'category' => $term['category'],
                'weight' => $weight,
                'count' => $count,
                'recommended' => $recommended,
                'status' => $status,
                'score' => round($termScore * 100),
            ];
        }

        // Content Score (weighted)
        $termScore = $totalWeight > 0 ? ($earnedWeight / $totalWeight) * 100 : 0;

        // Structure bonus/penalty
        $structureScore = $this->scoreStructure($content, $model['content_structure'] ?? []);

        // Weighted final: 70% term coverage + 30% structure
        $contentScore = (int) round($termScore * 0.7 + $structureScore * 0.3);
        $contentScore = max(0, min(100, $contentScore));

        // Grade
        $grade = match (true) {
            $contentScore >= 80 => 'A',
            $contentScore >= 60 => 'B',
            $contentScore >= 40 => 'C',
            $contentScore >= 20 => 'D',
            default => 'F',
        };

        // Competitor comparison
        $avgCompetitorScore = $model['difficulty_estimate'] ?? 50;

        // Summary stats
        $covered = count(array_filter($termResults, fn($t) => $t['status'] === 'good'));
        $missing = count(array_filter($termResults, fn($t) => $t['status'] === 'missing'));
        $overused = count(array_filter($termResults, fn($t) => $t['status'] === 'overused'));
        $low = count(array_filter($termResults, fn($t) => $t['status'] === 'low'));

        $result = [
            'content_score' => $contentScore,
            'grade' => $grade,
            'word_count' => $wordCount,
            'target_word_count' => $model['content_structure']['recommended_word_count'] ?? 1500,
            'search_intent' => $model['search_intent'] ?? 'informational',
            'summary' => [
                'total_terms' => count($termResults),
                'covered' => $covered,
                'missing' => $missing,
                'overused' => $overused,
                'low' => $low,
            ],
            'term_results' => $termResults,
            'structure_score' => $structureScore,
            'questions' => $model['questions'] ?? [],
            'entities' => $model['entities'] ?? [],
        ];

        if ($postId && get_post($postId)) {
            update_post_meta($postId, '_sseo_ai_term_coverage', $termResults);
            update_post_meta($postId, '_sseo_ai_content_score', $contentScore);

            if ($this->isCalibrationEnabled()) {
                $correlations = $this->getTermCorrelations($postId);
                $correlationMap = [];
                foreach ($correlations as $c) {
                    $correlationMap[$c['term']] = $c['correlation'];
                }
                foreach ($result['term_results'] as $idx => $tr) {
                    $result['term_results'][$idx]['correlation'] = $correlationMap[$tr['term']] ?? null;
                }
            }
        }

        return $result;
    }

    /**
     * Score content structure against recommendations.
     */
    private function scoreStructure(string $content, array $structure): int
    {
        $score = 0;
        $checks = 0;

        // Word count
        $wordCount = str_word_count(wp_strip_all_tags($content));
        $targetWords = $structure['recommended_word_count'] ?? 1500;
        $wordRatio = min(1, $wordCount / max(1, $targetWords));
        $score += $wordRatio * 25;
        $checks += 25;

        // Headings
        $headingCount = preg_match_all('/<h[2-6][^>]*>/i', $content);
        $targetHeadings = $structure['recommended_headings'] ?? 8;
        $headingRatio = min(1, $headingCount / max(1, $targetHeadings));
        $score += $headingRatio * 25;
        $checks += 25;

        // Images
        $imageCount = preg_match_all('/<img[^>]+>/i', $content);
        $targetImages = $structure['recommended_images'] ?? 3;
        $imageRatio = min(1, $imageCount / max(1, $targetImages));
        $score += $imageRatio * 25;
        $checks += 25;

        // Paragraphs
        $paraCount = preg_match_all('/<p[^>]*>/i', $content) + preg_match_all('/\n\n/', $content);
        $targetParas = $structure['recommended_paragraphs'] ?? 15;
        $paraRatio = min(1, $paraCount / max(1, $targetParas));
        $score += $paraRatio * 25;
        $checks += 25;

        return $checks > 0 ? (int) round(($score / $checks) * 100) : 0;
    }

    /**
     * AI suggest where to insert missing terms naturally.
     */
    public function suggestTermInsertions(string $keyword, string $content, array $missingTerms): array|\WP_Error
    {
        if (empty($missingTerms)) {
            return ['suggestions' => []];
        }

        $termList = implode(', ', array_slice($missingTerms, 0, 15));
        $excerpt = mb_substr(wp_strip_all_tags($content), 0, 1500);

        $prompt = "You are an SEO content optimization expert. The user is writing about \"{$keyword}\".

Their content is missing these important terms from the topic model: {$termList}

Content excerpt:
\"{$excerpt}\"

For each missing term, suggest a specific sentence or phrase that naturally incorporates it into the existing content. Return JSON only:
[
    {\"term\": \"missing term\", \"suggestion\": \"A natural sentence incorporating the term\", \"insert_after\": \"Which section/paragraph to add it near\"}
]";

        $result = $this->llm->call($prompt, null, null, 1500);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);

        $suggestions = json_decode(trim($text), true);
        if (!is_array($suggestions)) {
            return ['suggestions' => []];
        }

        return [
            'suggestions' => array_map(function ($s) {
                return [
                    'term' => sanitize_text_field($s['term'] ?? ''),
                    'suggestion' => sanitize_text_field($s['suggestion'] ?? ''),
                    'insert_after' => sanitize_text_field($s['insert_after'] ?? ''),
                ];
            }, $suggestions),
        ];
    }

    // REST handlers
    public function restGetTopicModel(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $country = sanitize_text_field($request->get_param('country') ?: 'us');
        return $this->getTopicModel($keyword, $country);
    }

    public function restScoreContent(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $content = $request->get_param('content');
        $title = sanitize_text_field($request->get_param('title') ?: '');
        $postId = (int) $request->get_param('post_id');
        return $this->scoreContent($keyword, $content, $title, $postId ?: null);
    }

    public function restSuggestTermInsertions(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $content = $request->get_param('content');
        $missingTerms = $request->get_param('missing_terms') ?: [];
        return $this->suggestTermInsertions($keyword, $content, $missingTerms);
    }

    /**
     * Get calibration data for a post: rank-correlation per term.
     */
    public function restGetCalibration(\WP_REST_Request $request): array
    {
        $postId = (int) $request->get_param('id');
        return [
            'post_id' => $postId,
            'term_correlations' => $this->getTermCorrelations($postId),
        ];
    }

    /**
     * Run calibration: correlate term coverage with rank history.
     */
    public function runCalibration(): void
    {
        global $wpdb;

        $rankTable = $wpdb->prefix . 'sseo_ai_tracked_keywords';
        $historyTable = $wpdb->prefix . 'sseo_ai_rank_history';

        $posts = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sseo_ai_term_coverage',
                    'compare' => 'EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);

        $termStats = [];

        foreach ($posts as $postId) {
            $coverage = get_post_meta($postId, '_sseo_ai_term_coverage', true);
            $keywordId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$rankTable} WHERE post_id = %d AND active = 1 LIMIT 1", $postId));
            if (!$keywordId || empty($coverage) || !is_array($coverage)) {
                continue;
            }

            $avgPosition = $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(position) FROM {$historyTable} WHERE keyword_id = %d",
                $keywordId
            ));
            $avgPosition = (float) $avgPosition;
            $isTop3 = $avgPosition > 0 && $avgPosition <= 3;

            foreach ($coverage as $term) {
                $name = $term['term'] ?? '';
                if (empty($name)) continue;

                if (!isset($termStats[$name])) {
                    $termStats[$name] = ['top3_good' => 0, 'top3_total' => 0, 'all_total' => 0, 'good_total' => 0];
                }

                $termStats[$name]['all_total']++;
                if ($term['status'] === 'good') {
                    $termStats[$name]['good_total']++;
                }
                if ($isTop3) {
                    $termStats[$name]['top3_total']++;
                    if ($term['status'] === 'good') {
                        $termStats[$name]['top3_good']++;
                    }
                }
            }
        }

        $correlations = [];
        foreach ($termStats as $term => $stats) {
            $globalRate = $stats['all_total'] > 0 ? $stats['good_total'] / $stats['all_total'] : 0;
            $top3Rate = $stats['top3_total'] > 0 ? $stats['top3_good'] / $stats['top3_total'] : 1;
            $correlations[$term] = [
                'lift' => round($top3Rate - $globalRate, 2),
                'top3_rate' => round($top3Rate, 2),
                'global_rate' => round($globalRate, 2),
                'sample_size' => $stats['all_total'],
            ];
        }

        update_option('sseo_ai_term_correlations', $correlations);
    }

    /**
     * Get stored term correlation data for a post.
     */
    private function getTermCorrelations(int $postId): array
    {
        $correlations = get_option('sseo_ai_term_correlations', []);
        $coverage = get_post_meta($postId, '_sseo_ai_term_coverage', true);
        if (empty($coverage) || !is_array($coverage)) {
            return [];
        }

        $result = [];
        foreach ($coverage as $term) {
            $name = $term['term'] ?? '';
            $result[] = [
                'term' => $name,
                'status' => $term['status'] ?? '',
                'correlation' => $correlations[$name] ?? null,
            ];
        }
        return $result;
    }

    /**
     * Check if calibration features are available (Business+ only).
     */
    private function isCalibrationEnabled(): bool
    {
        $validator = new LicenseValidator($this->settings);
        return $validator->isBusinessPlus();
    }

    /**
     * Render the Content Optimizer admin page (SurferSEO-style editor).
     */
    public function renderPage(): void
    {
        $postId = (int) ($_GET['post_id'] ?? 0);
        ?>
        <div class="wrap aiseo-modern">
            <h1><?php esc_html_e('Content Optimizer', 'ai-seo-client'); ?></h1>
            <p class="description"><?php esc_html_e('Real-time NLP content scoring — like MarketMuse & SurferSEO. Enter a keyword to generate a topic model, then optimize your content for maximum coverage.', 'ai-seo-client'); ?></p>

            <div style="display:grid; grid-template-columns:1fr 340px; gap:20px; max-width:1400px; margin-top:20px;">
                <!-- Left: Editor -->
                <div>
                    <div class="postbox" style="padding:20px;">
                        <input type="hidden" id="opt-post-id" value="<?php echo esc_attr($postId); ?>">
                        <div style="display:flex; gap:10px; margin-bottom:15px;">
                            <input type="text" id="opt-keyword" class="regular-text" style="flex:1;" placeholder="<?php esc_attr_e('Target keyword...', 'ai-seo-client'); ?>">
                            <button type="button" class="button button-primary" id="opt-analyze"><?php esc_html_e('Analyze', 'ai-seo-client'); ?></button>
                        </div>
                        <input type="text" id="opt-title" class="large-text" placeholder="<?php esc_attr_e('Content title...', 'ai-seo-client'); ?>" style="margin-bottom:10px;">
                        <textarea id="opt-content" rows="25" class="large-text" placeholder="<?php esc_attr_e('Paste or write your content here...', 'ai-seo-client'); ?>" style="font-size:15px;line-height:1.7;"></textarea>
                        <div style="display:flex; gap:10px; margin-top:10px;">
                            <button type="button" class="button" id="opt-score-btn" disabled><?php esc_html_e('Score Content', 'ai-seo-client'); ?></button>
                            <button type="button" class="button" id="opt-suggest-btn" disabled><?php esc_html_e('AI Suggest Missing Terms', 'ai-seo-client'); ?></button>
                            <span id="opt-word-count" style="margin-left:auto;color:#666;line-height:30px;">0 words</span>
                        </div>
                    </div>
                    <div id="opt-suggestions" style="display:none;" class="postbox" style="padding:15px; margin-top:15px;"></div>
                </div>

                <!-- Right: Score Panel -->
                <div>
                    <div class="postbox" style="padding:20px; text-align:center;" id="opt-score-panel">
                        <div id="opt-score-display" style="margin-bottom:15px;">
                            <div style="font-size:12px;color:#666;letter-spacing:1px;"><?php esc_html_e('Content Score', 'ai-seo-client'); ?></div>
                            <div id="opt-score-circle" style="width:120px;height:120px;margin:15px auto;position:relative;">
                                <svg viewBox="0 0 36 36" width="120" height="120" style="transform:rotate(-90deg);">
                                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e0e0e0" stroke-width="2.5"/>
                                    <circle id="opt-score-arc" cx="18" cy="18" r="15.9" fill="none" stroke="#ccc" stroke-width="2.5" stroke-dasharray="0, 100" stroke-linecap="round" style="transition:all 0.8s ease;"/>
                                </svg>
                                <div id="opt-score-num" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:32px;font-weight:bold;color:#999;">—</div>
                            </div>
                            <div id="opt-score-grade" style="font-size:14px;color:#666;"></div>
                        </div>
                        <div id="opt-summary" style="text-align:left;font-size:13px;display:none;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:15px;">
                                <div style="background:#edfaef;padding:8px;border-radius:4px;text-align:center;">
                                    <div id="opt-covered" style="font-size:20px;font-weight:bold;color:#00a32a;">0</div>
                                    <div style="font-size:11px;color:#666;"><?php esc_html_e('Covered', 'ai-seo-client'); ?></div>
                                </div>
                                <div style="background:#fcf0f1;padding:8px;border-radius:4px;text-align:center;">
                                    <div id="opt-missing" style="font-size:20px;font-weight:bold;color:#d63638;">0</div>
                                    <div style="font-size:11px;color:#666;"><?php esc_html_e('Missing', 'ai-seo-client'); ?></div>
                                </div>
                                <div style="background:#fcf9e8;padding:8px;border-radius:4px;text-align:center;">
                                    <div id="opt-low" style="font-size:20px;font-weight:bold;color:#dba617;">0</div>
                                    <div style="font-size:11px;color:#666;"><?php esc_html_e('Low', 'ai-seo-client'); ?></div>
                                </div>
                                <div style="background:#fff3cd;padding:8px;border-radius:4px;text-align:center;">
                                    <div id="opt-overused" style="font-size:20px;font-weight:bold;color:#b45309;">0</div>
                                    <div style="font-size:11px;color:#666;"><?php esc_html_e('Overused', 'ai-seo-client'); ?></div>
                                </div>
                            </div>
                            <div id="opt-intent" style="padding:6px 10px;background:#f0f0f1;border-radius:4px;margin-bottom:10px;font-size:12px;"></div>
                            <div id="opt-wordcount-target" style="padding:6px 10px;background:#f0f0f1;border-radius:4px;margin-bottom:10px;font-size:12px;"></div>
                        </div>
                    </div>

                    <!-- Term Heatmap -->
                    <div class="postbox" style="padding:15px;margin-top:15px;max-height:500px;overflow-y:auto;" id="opt-terms-panel">
                        <h3 style="margin-top:0;font-size:13px;"><?php esc_html_e('Topic Model Terms', 'ai-seo-client'); ?></h3>
                        <div id="opt-terms-list" style="font-size:12px;">
                            <p style="color:#999;"><?php esc_html_e('Enter a keyword and click Analyze to generate the topic model.', 'ai-seo-client'); ?></p>
                        </div>
                    </div>

                    <!-- Questions -->
                    <div class="postbox" style="padding:15px;margin-top:15px;" id="opt-questions-panel" style="display:none;">
                        <h3 style="margin-top:0;font-size:13px;"><?php esc_html_e('Questions to Answer', 'ai-seo-client'); ?></h3>
                        <ul id="opt-questions" style="list-style:none;padding:0;font-size:12px;"></ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var topicModel = null;
            var scoreData = null;

            // Word count
            $('#opt-content').on('input', function() {
                var wc = ($(this).val().match(/\S+/g) || []).length;
                $('#opt-word-count').text(wc + ' words');
            });

            // Analyze keyword — get topic model
            $('#opt-analyze').on('click', function() {
                var kw = $('#opt-keyword').val().trim();
                if (!kw) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Analyzing...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/optimizer/topic-model',
                    method: 'POST',
                    data: { keyword: kw }
                }).then(function(model) {
                    topicModel = model;
                    renderTopicModel(model);
                    $('#opt-score-btn, #opt-suggest-btn').prop('disabled', false);
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Analyze', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Analyze', 'ai-seo-client')); ?>');
                });
            });

            function renderTopicModel(model) {
                var terms = model.primary_terms || [];
                var html = '';
                terms.forEach(function(t) {
                    var catColors = {core:'#379fd3',supporting:'#059669',entity:'#7c3aed',question:'#d97706'};
                    var bgColors = {core:'#eff6ff',supporting:'#ecfdf5',entity:'#f5f3ff',question:'#fffbeb'};
                    html += '<div class="opt-term" data-term="' + t.term + '" style="display:flex;justify-content:space-between;align-items:center;padding:5px 8px;margin:2px 0;border-left:3px solid ' + (catColors[t.category]||'#999') + ';background:' + (bgColors[t.category]||'#f9f9f9') + ';border-radius:0 3px 3px 0;">' +
                        '<span>' + t.term + '</span>' +
                        '<span style="font-size:11px;color:#999;" class="opt-term-count">—/' + t.recommended_count + '</span>' +
                        '</div>';
                });
                $('#opt-terms-list').html(html || '<p style="color:#999;">No terms</p>');

                // Questions
                var qHtml = '';
                (model.questions || []).forEach(function(q) {
                    qHtml += '<li style="padding:4px 0;border-bottom:1px solid #f0f0f0;">❓ ' + q + '</li>';
                });
                $('#opt-questions').html(qHtml);
                $('#opt-questions-panel').show();
            }

            // Score content
            $('#opt-score-btn').on('click', function() {
                var content = $('#opt-content').val().trim();
                var title = $('#opt-title').val().trim();
                var kw = $('#opt-keyword').val().trim();
                var postId = parseInt($('#opt-post-id').val(), 10) || 0;
                if (!content || !kw) return;

                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Scoring...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/optimizer/score',
                    method: 'POST',
                    data: { keyword: kw, content: content, title: title, post_id: postId }
                }).then(function(result) {
                    scoreData = result;
                    renderScore(result);
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Score Content', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Score Content', 'ai-seo-client')); ?>');
                });
            });

            function renderScore(r) {
                var score = r.content_score;
                var color = score >= 80 ? '#00a32a' : (score >= 60 ? '#059669' : (score >= 40 ? '#dba617' : '#d63638'));
                $('#opt-score-arc').attr('stroke', color).attr('stroke-dasharray', score + ', 100');
                $('#opt-score-num').text(score).css('color', color);
                $('#opt-score-grade').html('Grade: <strong style="color:' + color + ';">' + r.grade + '</strong>');
                $('#opt-covered').text(r.summary.covered);
                $('#opt-missing').text(r.summary.missing);
                $('#opt-low').text(r.summary.low);
                $('#opt-overused').text(r.summary.overused);
                $('#opt-intent').html('<strong>Intent:</strong> ' + r.search_intent);
                $('#opt-wordcount-target').html('<strong>Words:</strong> ' + r.word_count + ' / ' + r.target_word_count);
                $('#opt-summary').show();

                // Update term counts
                (r.term_results || []).forEach(function(t) {
                    var el = $('.opt-term[data-term="' + t.term + '"]');
                    if (el.length) {
                        var statusColors = {good:'#00a32a',missing:'#d63638',low:'#dba617',overused:'#b45309'};
                        var corr = '';
                        if (t.correlation) {
                            var lift = parseFloat(t.correlation.lift);
                            if (lift > 0.2) {
                                corr = ' <span style="color:#00a32a;font-size:10px;" title="<?php echo esc_js(__('Higher coverage correlates with top rankings', 'ai-seo-client')); ?>">↑</span>';
                            } else if (lift < -0.2) {
                                corr = ' <span style="color:#d63638;font-size:10px;" title="<?php echo esc_js(__('Lower coverage correlates with top rankings', 'ai-seo-client')); ?>">↓</span>';
                            } else if (lift >= -0.2 && lift <= 0.2) {
                                corr = ' <span style="color:#999;font-size:10px;" title="<?php echo esc_js(__('No strong rank correlation yet', 'ai-seo-client')); ?>">•</span>';
                            }
                        }
                        el.find('.opt-term-count').html(t.count + '/' + t.recommended + corr).css('color', statusColors[t.status] || '#999');
                        el.css('border-left-color', statusColors[t.status] || el.css('border-left-color'));
                    }
                });
            }

            // AI Suggest
            $('#opt-suggest-btn').on('click', function() {
                if (!scoreData || !scoreData.term_results) return;
                var missing = scoreData.term_results.filter(function(t) { return t.status === 'missing'; }).map(function(t) { return t.term; });
                if (!missing.length) { alert('<?php echo esc_js(__('No missing terms!', 'ai-seo-client')); ?>'); return; }

                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/optimizer/suggest-terms',
                    method: 'POST',
                    data: { keyword: $('#opt-keyword').val(), content: $('#opt-content').val(), missing_terms: missing.slice(0, 10) }
                }).then(function(res) {
                    var html = '<div class="postbox" style="padding:15px;margin-top:15px;"><h3 style="margin-top:0;"><?php echo esc_js(__('AI Suggestions for Missing Terms', 'ai-seo-client')); ?></h3>';
                    (res.suggestions || []).forEach(function(s) {
                        html += '<div style="padding:8px;margin:5px 0;background:#f0f7ff;border-left:3px solid #379fd3;border-radius:0 4px 4px 0;">' +
                            '<strong style="color:#379fd3;">' + s.term + '</strong><br>' +
                            '<span style="color:#333;">' + s.suggestion + '</span>' +
                            (s.insert_after ? '<br><em style="color:#999;font-size:11px;">→ ' + s.insert_after + '</em>' : '') +
                            '</div>';
                    });
                    html += '</div>';
                    $('#opt-suggestions').html(html).show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('AI Suggest Missing Terms', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('AI Suggest Missing Terms', 'ai-seo-client')); ?>');
                });
            });
        });
        </script>
        <?php
    }
}

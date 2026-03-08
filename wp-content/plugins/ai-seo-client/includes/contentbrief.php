<?php

namespace AISEOClient;

/**
 * Content Brief Generator
 * 
 * Generates comprehensive SEO content briefs by analyzing top-ranking SERP results,
 * extracting common headings, word counts, entities, and questions — then using AI
 * to produce an actionable writing brief. This is the Surfer SEO killer feature.
 */
class ContentBrief
{
    private LlmClient $llm;
    private Settings $settings;
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
            __('Content Brief', 'ai-seo-client'),
            __('Content Brief', 'ai-seo-client'),
            'manage_options',
            'ai-seo-assistant-brief',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoclient/v1', '/content-brief', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateBrief'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'country' => ['type' => 'string', 'required' => false],
            ],
        ]);

        register_rest_route('aiseoclient/v1', '/content-brief/score', [
            'methods' => 'POST',
            'callback' => [$this, 'restScoreContent'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
                'brief_id' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    /**
     * Fetch SERP data via DashboardAPI proxy.
     */
    private function fetchSerpData(string $keyword, string $country = 'us'): array|\WP_Error
    {
        $cacheKey = 'aiseo_brief_serp_' . md5($keyword . $country);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->dashboardAPI->request('serp/search', [
            'keyword' => $keyword,
            'country' => $country,
            'num' => 10,
        ]);

        if (is_wp_error($response)) {
            // Fallback: use AI to simulate SERP data
            return $this->aiFallbackSerpData($keyword);
        }

        $results = $response['results'] ?? [];
        $topResults = array_slice($results, 0, 10);

        set_transient($cacheKey, $topResults, 3 * DAY_IN_SECONDS);
        return $topResults;
    }

    /**
     * AI fallback when SERP API is unavailable.
     */
    private function aiFallbackSerpData(string $keyword): array|\WP_Error
    {
        $prompt = "For the keyword \"{$keyword}\", generate a realistic list of 10 Google SERP results. Return JSON array with objects having: title, link, snippet, position. Return ONLY valid JSON.";

        $result = $this->llm->call($prompt, null, null, 2000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/', $text, $matches)) {
            $text = $matches[1];
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            return [];
        }

        return array_slice($parsed, 0, 10);
    }

    /**
     * Generate a full content brief for a keyword.
     */
    public function generateBrief(string $keyword): array|\WP_Error
    {
        // 1. Fetch SERP results via DashboardAPI proxy
        $topResults = $this->fetchSerpData($keyword);
        if (is_wp_error($topResults)) {
            return $topResults;
        }

        // 2. Analyze competing content
        $competitorAnalysis = $this->analyzeCompetitors($topResults);

        // 3. Use AI to generate the brief
        $brief = $this->generateAIBrief($keyword, $competitorAnalysis, $topResults);
        if (is_wp_error($brief)) {
            return $brief;
        }

        // 4. Calculate keyword difficulty estimate
        $difficulty = $this->estimateKeywordDifficulty($topResults);

        // 5. Combine everything
        $fullBrief = [
            'keyword' => $keyword,
            'generated_at' => current_time('mysql'),
            'serp_overview' => $this->buildSerpOverview($topResults),
            'difficulty' => $difficulty,
            'competitor_analysis' => $competitorAnalysis,
            'recommended_word_count' => $competitorAnalysis['avg_word_count'] ?? 1500,
            'recommended_headings' => $brief['headings'] ?? [],
            'recommended_questions' => $brief['questions'] ?? [],
            'recommended_entities' => $brief['entities'] ?? [],
            'recommended_lsi' => $brief['lsi_keywords'] ?? [],
            'content_outline' => $brief['outline'] ?? '',
            'search_intent' => $brief['intent'] ?? 'informational',
            'target_audience' => $brief['audience'] ?? '',
            'content_angle' => $brief['angle'] ?? '',
            'internal_link_opportunities' => $this->findInternalLinkOpportunities($keyword),
        ];

        // Cache the brief
        $briefId = 'aiseo_brief_' . md5($keyword . date('Y-m-d'));
        set_transient($briefId, $fullBrief, DAY_IN_SECONDS * 7);

        $fullBrief['brief_id'] = $briefId;
        return $fullBrief;
    }

    /**
     * Analyze competing pages from SERP results.
     */
    private function analyzeCompetitors(array $results): array
    {
        $wordCounts = [];
        $headings = [];
        $titles = [];
        $descriptions = [];
        $domains = [];

        foreach ($results as $result) {
            // Gather titles and descriptions from SERP
            $title = $result['title'] ?? '';
            $description = $result['snippet'] ?? $result['description'] ?? '';
            $url = $result['link'] ?? $result['url'] ?? '';
            $position = $result['position'] ?? 0;

            if ($title) $titles[] = $title;
            if ($description) $descriptions[] = $description;
            if ($url) {
                $parsed = parse_url($url);
                $domains[] = $parsed['host'] ?? '';
            }

            // Estimate word count from snippet length (rough heuristic)
            // Real implementation would scrape the page, but that requires additional HTTP calls
            $snippetWords = str_word_count($description);
            // Estimate full article length based on snippet density
            $estimatedWords = max(800, $snippetWords * 25);
            $wordCounts[] = $estimatedWords;

            // Extract heading patterns from titles
            if ($title) {
                $headings[] = $this->extractHeadingPattern($title);
            }
        }

        $avgWordCount = !empty($wordCounts) ? (int)round(array_sum($wordCounts) / count($wordCounts)) : 1500;
        // Round to nearest 100 for cleaner recommendation
        $avgWordCount = (int)(round($avgWordCount / 100) * 100);

        return [
            'total_results' => count($results),
            'avg_word_count' => max(1000, min(5000, $avgWordCount)),
            'common_title_patterns' => $this->findCommonPatterns($titles),
            'serp_titles' => array_slice($titles, 0, 10),
            'serp_descriptions' => array_slice($descriptions, 0, 10),
            'top_domains' => array_slice(array_unique($domains), 0, 10),
            'heading_patterns' => array_slice(array_unique($headings), 0, 10),
        ];
    }

    /**
     * Use AI to generate the actual content brief.
     */
    private function generateAIBrief(string $keyword, array $analysis, array $results): array|\WP_Error
    {
        $serpTitles = implode("\n", array_slice($analysis['serp_titles'] ?? [], 0, 8));
        $serpDescriptions = implode("\n", array_slice($analysis['serp_descriptions'] ?? [], 0, 5));

        $prompt = <<<PROMPT
You are an expert SEO content strategist. Generate a comprehensive content brief for the keyword: "{$keyword}"

Here are the top-ranking titles in Google:
{$serpTitles}

Here are some descriptions:
{$serpDescriptions}

Based on this competitive landscape, generate a JSON response with the following structure:
{
  "intent": "informational|transactional|navigational|commercial",
  "audience": "Description of target audience",
  "angle": "Unique angle or hook to differentiate from competitors",
  "headings": ["H2: ...", "H3: ...", ...],
  "questions": ["Question 1?", "Question 2?", ...],
  "entities": ["entity1", "entity2", ...],
  "lsi_keywords": ["related keyword 1", "related keyword 2", ...],
  "outline": "Full article outline with sections and bullet points"
}

Requirements:
- Include 6-10 H2 headings and relevant H3 subheadings
- Include 5-8 FAQ questions people might search for
- Include 10-15 related entities/topics to cover
- Include 10-15 LSI/semantic keywords
- The outline should be detailed with what to cover in each section
- Analyze search intent from the SERP data
- Suggest a unique content angle that beats existing content

Return ONLY valid JSON, no other text.
PROMPT;

        $result = $this->llm->call(
            $prompt,
            'You are an expert SEO content strategist who creates data-driven content briefs.',
            4000
        );

        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'];
        
        // Extract JSON from response (handle markdown code blocks)
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $text, $matches)) {
            $text = $matches[1];
        }
        
        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            // Fallback: try to extract structured data from plain text
            return $this->parsePlainTextBrief($text, $keyword);
        }

        return $parsed;
    }

    /**
     * Fallback parser for when AI doesn't return valid JSON.
     */
    private function parsePlainTextBrief(string $text, string $keyword): array
    {
        $headings = [];
        $questions = [];
        $entities = [];
        $lsiKeywords = [];

        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^H[23]:\s*(.+)/i', $line, $m)) {
                $headings[] = $m[0];
            } elseif (str_ends_with($line, '?')) {
                $questions[] = $line;
            }
        }

        return [
            'intent' => 'informational',
            'audience' => 'General audience searching for ' . $keyword,
            'angle' => 'Comprehensive guide covering all aspects',
            'headings' => $headings ?: ["H2: What is {$keyword}?", "H2: Benefits of {$keyword}", "H2: How to use {$keyword}", "H2: FAQ about {$keyword}"],
            'questions' => $questions ?: ["What is {$keyword}?", "How does {$keyword} work?", "Why is {$keyword} important?"],
            'entities' => $entities,
            'lsi_keywords' => $lsiKeywords,
            'outline' => $text,
        ];
    }

    /**
     * Estimate keyword difficulty from SERP results.
     */
    private function estimateKeywordDifficulty(array $results): array
    {
        $authoritySignals = 0;
        $total = count($results);
        $highAuthority = ['wikipedia.org', 'youtube.com', 'amazon.com', 'reddit.com', 'linkedin.com', 'facebook.com', 'twitter.com', 'forbes.com', 'nytimes.com', 'bbc.com', 'gov', '.edu'];

        foreach ($results as $result) {
            $url = $result['link'] ?? $result['url'] ?? '';
            $host = parse_url($url, PHP_URL_HOST) ?? '';
            foreach ($highAuthority as $domain) {
                if (stripos($host, $domain) !== false) {
                    $authoritySignals++;
                    break;
                }
            }
        }

        $difficultyScore = $total > 0 ? round(($authoritySignals / min($total, 10)) * 100) : 50;
        
        if ($difficultyScore >= 70) $level = 'hard';
        elseif ($difficultyScore >= 40) $level = 'medium';
        else $level = 'easy';

        return [
            'score' => $difficultyScore,
            'level' => $level,
            'high_authority_count' => $authoritySignals,
            'description' => match ($level) {
                'hard' => __('High competition. Many authoritative domains ranking. Requires strong content and backlinks.', 'ai-seo-client'),
                'medium' => __('Moderate competition. Mix of authoritative and niche sites. Winnable with quality content.', 'ai-seo-client'),
                'easy' => __('Low competition. Few major brands ranking. Good opportunity for quick wins.', 'ai-seo-client'),
            },
        ];
    }

    /**
     * Build a clean SERP overview.
     */
    private function buildSerpOverview(array $results): array
    {
        $overview = [];
        foreach (array_slice($results, 0, 10) as $i => $result) {
            $overview[] = [
                'position' => $result['position'] ?? ($i + 1),
                'title' => $result['title'] ?? '',
                'url' => $result['link'] ?? $result['url'] ?? '',
                'snippet' => $result['snippet'] ?? $result['description'] ?? '',
            ];
        }
        return $overview;
    }

    /**
     * Find internal pages that could link to content about this keyword.
     */
    private function findInternalLinkOpportunities(string $keyword): array
    {
        $posts = get_posts([
            's' => $keyword,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'no_found_rows' => true,
        ]);

        $opportunities = [];
        foreach ($posts as $post) {
            $opportunities[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'edit_url' => get_edit_post_link($post->ID, ''),
            ];
        }
        return $opportunities;
    }

    /**
     * Score existing content against a brief's recommendations.
     */
    public function scoreContent(string $keyword, string $content, ?string $briefId = null): array
    {
        $brief = null;
        if ($briefId) {
            $brief = get_transient($briefId);
        }

        $plainContent = strip_tags($content);
        $contentLower = strtolower($plainContent);
        $wordCount = str_word_count($plainContent);

        $scores = [];

        // Word count score
        $targetWords = $brief['recommended_word_count'] ?? 1500;
        $wordRatio = min(1, $wordCount / $targetWords);
        $scores['word_count'] = [
            'score' => round($wordRatio * 100),
            'current' => $wordCount,
            'target' => $targetWords,
            'status' => $wordRatio >= 0.9 ? 'good' : ($wordRatio >= 0.6 ? 'warning' : 'error'),
        ];

        // Keyword usage
        $keywordLower = strtolower($keyword);
        $keywordCount = substr_count($contentLower, $keywordLower);
        $keywordDensity = $wordCount > 0 ? ($keywordCount / $wordCount) * 100 : 0;
        $scores['keyword_usage'] = [
            'score' => ($keywordDensity >= 0.5 && $keywordDensity <= 2.5) ? 100 : max(0, 100 - abs($keywordDensity - 1.5) * 40),
            'count' => $keywordCount,
            'density' => round($keywordDensity, 2),
            'status' => ($keywordDensity >= 0.5 && $keywordDensity <= 2.5) ? 'good' : 'warning',
        ];

        // Heading coverage
        $recommendedHeadings = $brief['recommended_headings'] ?? [];
        $headingsCovered = 0;
        foreach ($recommendedHeadings as $heading) {
            $headingClean = strtolower(preg_replace('/^H[23]:\s*/i', '', $heading));
            // Check if any variant of the heading topic exists
            $words = array_filter(explode(' ', $headingClean), fn($w) => strlen($w) > 3);
            $found = 0;
            foreach ($words as $word) {
                if (stripos($contentLower, $word) !== false) $found++;
            }
            if (count($words) > 0 && ($found / count($words)) >= 0.5) {
                $headingsCovered++;
            }
        }
        $headingRatio = count($recommendedHeadings) > 0 ? $headingsCovered / count($recommendedHeadings) : 0;
        $scores['heading_coverage'] = [
            'score' => round($headingRatio * 100),
            'covered' => $headingsCovered,
            'total' => count($recommendedHeadings),
            'status' => $headingRatio >= 0.7 ? 'good' : ($headingRatio >= 0.4 ? 'warning' : 'error'),
        ];

        // Entity coverage
        $entities = $brief['recommended_entities'] ?? [];
        $entitiesCovered = 0;
        foreach ($entities as $entity) {
            if (stripos($contentLower, strtolower($entity)) !== false) {
                $entitiesCovered++;
            }
        }
        $entityRatio = count($entities) > 0 ? $entitiesCovered / count($entities) : 0;
        $scores['entity_coverage'] = [
            'score' => round($entityRatio * 100),
            'covered' => $entitiesCovered,
            'total' => count($entities),
            'status' => $entityRatio >= 0.6 ? 'good' : ($entityRatio >= 0.3 ? 'warning' : 'error'),
        ];

        // LSI keyword coverage
        $lsiKeywords = $brief['recommended_lsi'] ?? [];
        $lsiCovered = 0;
        foreach ($lsiKeywords as $lsi) {
            if (stripos($contentLower, strtolower($lsi)) !== false) {
                $lsiCovered++;
            }
        }
        $lsiRatio = count($lsiKeywords) > 0 ? $lsiCovered / count($lsiKeywords) : 0;
        $scores['lsi_coverage'] = [
            'score' => round($lsiRatio * 100),
            'covered' => $lsiCovered,
            'total' => count($lsiKeywords),
            'status' => $lsiRatio >= 0.5 ? 'good' : ($lsiRatio >= 0.3 ? 'warning' : 'error'),
        ];

        // Question coverage (FAQ)
        $questions = $brief['recommended_questions'] ?? [];
        $questionsCovered = 0;
        foreach ($questions as $q) {
            $qWords = array_filter(explode(' ', strtolower($q)), fn($w) => strlen($w) > 3);
            $found = 0;
            foreach ($qWords as $word) {
                if (stripos($contentLower, $word) !== false) $found++;
            }
            if (count($qWords) > 0 && ($found / count($qWords)) >= 0.6) {
                $questionsCovered++;
            }
        }
        $questionRatio = count($questions) > 0 ? $questionsCovered / count($questions) : 0;
        $scores['question_coverage'] = [
            'score' => round($questionRatio * 100),
            'covered' => $questionsCovered,
            'total' => count($questions),
            'status' => $questionRatio >= 0.5 ? 'good' : ($questionRatio >= 0.3 ? 'warning' : 'error'),
        ];

        // Overall score (weighted)
        $overall = round(
            $scores['word_count']['score'] * 0.15 +
            $scores['keyword_usage']['score'] * 0.20 +
            $scores['heading_coverage']['score'] * 0.20 +
            $scores['entity_coverage']['score'] * 0.15 +
            $scores['lsi_coverage']['score'] * 0.15 +
            $scores['question_coverage']['score'] * 0.15
        );

        return [
            'overall_score' => min(100, max(0, $overall)),
            'grade' => $overall >= 80 ? 'A' : ($overall >= 60 ? 'B' : ($overall >= 40 ? 'C' : 'D')),
            'scores' => $scores,
        ];
    }

    /**
     * Extract heading pattern from title.
     */
    private function extractHeadingPattern(string $title): string
    {
        // Remove numbers and common filler words to find the pattern
        $pattern = preg_replace('/\d+/', 'N', $title);
        return $pattern;
    }

    /**
     * Find common patterns in SERP titles.
     */
    private function findCommonPatterns(array $titles): array
    {
        $patterns = [];
        $keywords = [];

        foreach ($titles as $title) {
            $words = str_word_count(strtolower($title), 1);
            foreach ($words as $word) {
                if (strlen($word) > 3) {
                    $keywords[$word] = ($keywords[$word] ?? 0) + 1;
                }
            }
        }

        arsort($keywords);
        // Words appearing in 30%+ of titles
        $threshold = max(2, count($titles) * 0.3);
        foreach ($keywords as $word => $count) {
            if ($count >= $threshold) {
                $patterns[] = $word;
            }
        }

        return array_slice($patterns, 0, 15);
    }

    /**
     * REST: Generate content brief.
     */
    public function restGenerateBrief(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        if (!$keyword) {
            return new \WP_Error('no_keyword', __('Keyword is required', 'ai-seo-client'));
        }

        return $this->generateBrief($keyword);
    }

    /**
     * REST: Score content against brief.
     */
    public function restScoreContent(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $content = $request->get_param('content');
        $briefId = sanitize_text_field($request->get_param('brief_id') ?? '');

        if (!$keyword || !$content) {
            return new \WP_Error('missing_params', __('Keyword and content are required', 'ai-seo-client'));
        }

        return $this->scoreContent($keyword, $content, $briefId ?: null);
    }

    /**
     * Render the Content Brief admin page.
     */
    public function renderPage(): void
    {
        ?>
        <div class="wrap aiseo-modern">
            <h1><?php esc_html_e('Content Brief Generator', 'ai-seo-client'); ?></h1>
            <p class="description"><?php esc_html_e('Generate data-driven content briefs by analyzing top-ranking pages. Like Surfer SEO, but built into WordPress.', 'ai-seo-client'); ?></p>

            <div class="aiseo-brief-generator" style="max-width: 1200px;">
                <div class="postbox" style="padding: 20px;">
                    <h2><?php esc_html_e('Generate New Brief', 'ai-seo-client'); ?></h2>
                    <p>
                        <input type="text" id="aiseo-brief-keyword" class="regular-text" style="width: 400px;" placeholder="<?php esc_attr_e('Enter target keyword...', 'ai-seo-client'); ?>">
                        <button type="button" class="button button-primary" id="aiseo-generate-brief">
                            <?php esc_html_e('Generate Brief', 'ai-seo-client'); ?>
                        </button>
                        <span class="spinner" id="aiseo-brief-spinner" style="float:none;"></span>
                    </p>
                </div>

                <div id="aiseo-brief-result" style="display:none; margin-top: 20px;">
                    <!-- Brief Overview -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div class="postbox" style="padding: 15px; text-align: center;">
                            <div id="brief-difficulty" style="font-size: 28px; font-weight: bold;">-</div>
                            <div><?php esc_html_e('Difficulty', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding: 15px; text-align: center;">
                            <div id="brief-word-count" style="font-size: 28px; font-weight: bold;">-</div>
                            <div><?php esc_html_e('Target Words', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding: 15px; text-align: center;">
                            <div id="brief-intent" style="font-size: 28px; font-weight: bold;">-</div>
                            <div><?php esc_html_e('Search Intent', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding: 15px; text-align: center;">
                            <div id="brief-headings-count" style="font-size: 28px; font-weight: bold;">-</div>
                            <div><?php esc_html_e('Recommended Headings', 'ai-seo-client'); ?></div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <!-- Left Column -->
                        <div>
                            <div class="postbox" style="padding: 15px;">
                                <h3><?php esc_html_e('Content Outline', 'ai-seo-client'); ?></h3>
                                <div id="brief-outline" style="white-space: pre-wrap; font-family: monospace; font-size: 13px; max-height: 400px; overflow-y: auto;"></div>
                            </div>

                            <div class="postbox" style="padding: 15px; margin-top: 15px;">
                                <h3><?php esc_html_e('Recommended Headings', 'ai-seo-client'); ?></h3>
                                <ul id="brief-headings" style="list-style: none; padding: 0;"></ul>
                            </div>

                            <div class="postbox" style="padding: 15px; margin-top: 15px;">
                                <h3><?php esc_html_e('Questions to Answer (FAQ)', 'ai-seo-client'); ?></h3>
                                <ul id="brief-questions" style="list-style: disc; padding-left: 20px;"></ul>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div>
                            <div class="postbox" style="padding: 15px;">
                                <h3><?php esc_html_e('SERP Overview', 'ai-seo-client'); ?></h3>
                                <table class="wp-list-table widefat fixed striped" style="font-size: 12px;">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;">#</th>
                                            <th><?php esc_html_e('Title', 'ai-seo-client'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="brief-serp"></tbody>
                                </table>
                            </div>

                            <div class="postbox" style="padding: 15px; margin-top: 15px;">
                                <h3><?php esc_html_e('Entities & Topics to Cover', 'ai-seo-client'); ?></h3>
                                <div id="brief-entities" style="display: flex; flex-wrap: wrap; gap: 5px;"></div>
                            </div>

                            <div class="postbox" style="padding: 15px; margin-top: 15px;">
                                <h3><?php esc_html_e('LSI / Semantic Keywords', 'ai-seo-client'); ?></h3>
                                <div id="brief-lsi" style="display: flex; flex-wrap: wrap; gap: 5px;"></div>
                            </div>

                            <div class="postbox" style="padding: 15px; margin-top: 15px;">
                                <h3><?php esc_html_e('Internal Link Opportunities', 'ai-seo-client'); ?></h3>
                                <ul id="brief-internal-links" style="list-style: none; padding: 0;"></ul>
                            </div>
                        </div>
                    </div>

                    <div class="postbox" style="padding: 15px; margin-top: 20px;">
                        <h3><?php esc_html_e('Content Scoring', 'ai-seo-client'); ?></h3>
                        <p class="description"><?php esc_html_e('Paste your content below to score it against this brief\'s recommendations.', 'ai-seo-client'); ?></p>
                        <textarea id="aiseo-score-content" rows="8" class="large-text" placeholder="<?php esc_attr_e('Paste your article content here to get a score...', 'ai-seo-client'); ?>"></textarea>
                        <p>
                            <button type="button" class="button button-primary" id="aiseo-score-btn"><?php esc_html_e('Score Content', 'ai-seo-client'); ?></button>
                        </p>
                        <div id="aiseo-score-result" style="display:none; margin-top:15px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var currentBrief = null;

            $('#aiseo-generate-brief').on('click', function() {
                var keyword = $('#aiseo-brief-keyword').val().trim();
                if (!keyword) return alert('<?php echo esc_js(__('Enter a keyword', 'ai-seo-client')); ?>');

                var btn = $(this);
                var spinner = $('#aiseo-brief-spinner');
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                $('#aiseo-brief-result').hide();

                wp.apiFetch({
                    path: 'aiseoclient/v1/content-brief',
                    method: 'POST',
                    data: { keyword: keyword }
                }).then(function(brief) {
                    currentBrief = brief;
                    renderBrief(brief);
                    $('#aiseo-brief-result').show();
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }).catch(function(err) {
                    alert('Error: ' + (err.message || 'Unknown error'));
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                });
            });

            function renderBrief(brief) {
                var diff = brief.difficulty || {};
                var diffColor = diff.level === 'hard' ? '#dc3545' : (diff.level === 'medium' ? '#fd7e14' : '#28a745');
                $('#brief-difficulty').html('<span style="color:' + diffColor + '">' + (diff.score || 0) + '</span>');
                $('#brief-word-count').text(brief.recommended_word_count || '-');
                $('#brief-intent').text((brief.search_intent || '-').charAt(0).toUpperCase() + (brief.search_intent || '').slice(1));
                $('#brief-headings-count').text((brief.recommended_headings || []).length);
                $('#brief-outline').text(brief.content_outline || '');

                var headingsHtml = '';
                (brief.recommended_headings || []).forEach(function(h) {
                    var isH3 = h.toLowerCase().indexOf('h3') === 0;
                    headingsHtml += '<li style="padding:5px 8px; margin:3px 0; background:#f0f7ff; border-left:3px solid ' + (isH3 ? '#93c5fd' : '#2563eb') + '; ' + (isH3 ? 'margin-left:20px;' : 'font-weight:bold;') + '">' + h + '</li>';
                });
                $('#brief-headings').html(headingsHtml);

                var questionsHtml = '';
                (brief.recommended_questions || []).forEach(function(q) {
                    questionsHtml += '<li style="padding:3px 0;">' + q + '</li>';
                });
                $('#brief-questions').html(questionsHtml);

                var serpHtml = '';
                (brief.serp_overview || []).forEach(function(s) {
                    serpHtml += '<tr><td>' + s.position + '</td><td><a href="' + s.url + '" target="_blank">' + s.title + '</a></td></tr>';
                });
                $('#brief-serp').html(serpHtml);

                var entitiesHtml = '';
                (brief.recommended_entities || []).forEach(function(e) {
                    entitiesHtml += '<span style="display:inline-block;padding:4px 10px;background:#e0e7ff;border-radius:12px;font-size:12px;margin:2px;">' + e + '</span>';
                });
                $('#brief-entities').html(entitiesHtml);

                var lsiHtml = '';
                (brief.recommended_lsi || []).forEach(function(l) {
                    lsiHtml += '<span style="display:inline-block;padding:4px 10px;background:#dcfce7;border-radius:12px;font-size:12px;margin:2px;">' + l + '</span>';
                });
                $('#brief-lsi').html(lsiHtml);

                var linksHtml = '';
                (brief.internal_link_opportunities || []).forEach(function(lnk) {
                    linksHtml += '<li style="padding:4px 0;"><a href="' + lnk.edit_url + '">' + lnk.title + '</a></li>';
                });
                $('#brief-internal-links').html(linksHtml || '<li><?php echo esc_js(__('No matching internal pages found.', 'ai-seo-client')); ?></li>');
            }

            $('#aiseo-score-btn').on('click', function() {
                var content = $('#aiseo-score-content').val().trim();
                if (!content) return;
                var keyword = $('#aiseo-brief-keyword').val().trim();
                var briefId = currentBrief ? currentBrief.brief_id : '';

                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Scoring...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'aiseoclient/v1/content-brief/score',
                    method: 'POST',
                    data: { keyword: keyword, content: content, brief_id: briefId }
                }).then(function(result) {
                    renderScore(result);
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Score Content', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert('Error: ' + (err.message || 'Unknown'));
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Score Content', 'ai-seo-client')); ?>');
                });
            });

            function renderScore(result) {
                var scoreColor = result.overall_score >= 80 ? '#28a745' : (result.overall_score >= 60 ? '#fd7e14' : '#dc3545');
                var html = '<div style="display:flex;align-items:center;gap:20px;margin-bottom:15px;">';
                html += '<div style="width:80px;height:80px;border-radius:50%;background:' + scoreColor + ';color:white;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold;">' + result.overall_score + '</div>';
                html += '<div><strong style="font-size:20px;">Grade: ' + result.grade + '</strong></div>';
                html += '</div>';

                html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Metric</th><th>Score</th><th>Details</th></tr></thead><tbody>';
                var scores = result.scores || {};
                var labels = {
                    word_count: '<?php echo esc_js(__('Word Count', 'ai-seo-client')); ?>',
                    keyword_usage: '<?php echo esc_js(__('Keyword Usage', 'ai-seo-client')); ?>',
                    heading_coverage: '<?php echo esc_js(__('Heading Coverage', 'ai-seo-client')); ?>',
                    entity_coverage: '<?php echo esc_js(__('Entity Coverage', 'ai-seo-client')); ?>',
                    lsi_coverage: '<?php echo esc_js(__('LSI Keywords', 'ai-seo-client')); ?>',
                    question_coverage: '<?php echo esc_js(__('FAQ Coverage', 'ai-seo-client')); ?>'
                };
                for (var key in scores) {
                    var s = scores[key];
                    var statColor = s.status === 'good' ? '#28a745' : (s.status === 'warning' ? '#fd7e14' : '#dc3545');
                    var detail = '';
                    if (s.current !== undefined) detail = s.current + ' / ' + s.target;
                    else if (s.covered !== undefined) detail = s.covered + ' / ' + s.total;
                    else if (s.density !== undefined) detail = s.density + '% (' + s.count + 'x)';
                    html += '<tr><td>' + (labels[key] || key) + '</td><td><span style="color:' + statColor + ';font-weight:bold;">' + s.score + '%</span></td><td>' + detail + '</td></tr>';
                }
                html += '</tbody></table>';

                $('#aiseo-score-result').html(html).show();
            }
        });
        </script>
        <?php
    }
}

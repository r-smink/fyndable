<?php

namespace SSEOAIClient;

/**
 * Personalized Keyword Difficulty Score
 * 
 * Unlike generic KD scores, this analyzes difficulty relative to YOUR site:
 * - Your existing topical authority on related topics
 * - Your content inventory and internal linking strength
 * - Your domain age/strength signals
 * - Competitor landscape for the specific keyword
 * 
 * Similar to MarketMuse's Personalized Difficulty metric.
 */
class KeywordDifficulty
{
    private Settings $settings;
    private LlmClient $llm;
    private ?DashboardAPI $dashboardAPI = null;

    public function __construct(Settings $settings, LlmClient $llm, ?DashboardAPI $dashboardAPI = null)
    {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->dashboardAPI = $dashboardAPI;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/keyword-difficulty', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyzeDifficulty'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/keyword-difficulty/batch', [
            'methods' => 'POST',
            'callback' => [$this, 'restBatchAnalyze'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keywords' => ['type' => 'array', 'required' => true],
            ],
        ]);
    }

    /**
     * Analyze personalized keyword difficulty.
     */
    public function analyzeDifficulty(string $keyword): array
    {
        // 1. Check existing topical authority
        $authorityData = $this->assessTopicalAuthority($keyword);

        // 2. Content inventory strength
        $inventoryData = $this->assessContentInventory($keyword);

        // 3. Generic difficulty estimate via AI
        $genericDifficulty = $this->estimateGenericDifficulty($keyword);

        // 4. AI keyword search volume from DataForSEO (via Portal proxy)
        $aiKeywordData = $this->fetchAiKeywordData($keyword);

        // 5. Calculate personalized score
        $personalizedScore = $this->calculatePersonalizedScore(
            $genericDifficulty,
            $authorityData,
            $inventoryData
        );

        return [
            'keyword' => $keyword,
            'generic_difficulty' => $genericDifficulty,
            'personalized_difficulty' => $personalizedScore['score'],
            'personalized_level' => $personalizedScore['level'],
            'your_advantage' => $personalizedScore['advantage'],
            'topical_authority' => $authorityData,
            'content_inventory' => $inventoryData,
            'ai_keyword_data' => $aiKeywordData,
            'recommendation' => $personalizedScore['recommendation'],
            'estimated_effort' => $personalizedScore['effort'],
        ];
    }

    /**
     * Fetch AI keyword search volume data from DataForSEO via the Portal proxy.
     * Returns an empty array when the proxy is unavailable (graceful fallback).
     */
    private function fetchAiKeywordData(string $keyword): array
    {
        if (!$this->dashboardAPI) {
            return [];
        }

        $cacheKey = 'aiseo_kd_aikw_' . md5($keyword);
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = $this->dashboardAPI->request('ai/keyword-data', [
            'keywords' => [$keyword],
        ]);

        if (is_wp_error($response) || empty($response['data'])) {
            $empty = [];
            set_transient($cacheKey, $empty, HOUR_IN_SECONDS);
            return $empty;
        }

        $tasks = $response['data']['tasks'] ?? [];
        $result = $tasks[0]['result'] ?? [];
        $items = $result[0]['items'] ?? [];

        $payload = [];
        foreach ($items as $item) {
            if (($item['keyword'] ?? '') === $keyword) {
                $payload = [
                    'search_volume'   => (int) ($item['search_volume'] ?? 0),
                    'difficulty'      => (float) ($item['difficulty'] ?? 0),
                    'competition'     => (int) ($item['competition'] ?? 0),
                    'cpc'             => (float) ($item['cpc'] ?? 0),
                ];
                break;
            }
        }

        set_transient($cacheKey, $payload, 6 * HOUR_IN_SECONDS);
        return $payload;
    }

    /**
     * Assess your topical authority for a keyword area.
     */
    private function assessTopicalAuthority(string $keyword): array
    {
        $words = array_filter(explode(' ', strtolower($keyword)), fn($w) => strlen($w) > 2);
        $postTypes = get_post_types(['public' => true]);
        unset($postTypes['attachment']);

        $relatedPosts = 0;
        $totalWordCount = 0;
        $avgSeoScore = 0;
        $seoScores = [];

        $posts = get_posts([
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'fields' => 'ids',
        ]);

        foreach ($posts as $postId) {
            $title = strtolower(get_the_title($postId));
            $keyphrase = strtolower(get_post_meta($postId, '_sseo_ai_focus_keyphrase', true) ?: '');

            $isRelated = false;
            foreach ($words as $word) {
                if (stripos($title, $word) !== false || stripos($keyphrase, $word) !== false) {
                    $isRelated = true;
                    break;
                }
            }

            if ($isRelated) {
                $relatedPosts++;
                $post = get_post($postId);
                if ($post) {
                    $totalWordCount += str_word_count(wp_strip_all_tags($post->post_content));
                }
                $score = get_post_meta($postId, '_sseo_ai_score', true);
                if ($score !== '') {
                    $seoScores[] = (int) $score;
                }
            }
        }

        $totalPublished = count($posts);
        $authorityRatio = $totalPublished > 0 ? $relatedPosts / $totalPublished : 0;
        $avgScore = !empty($seoScores) ? (int) round(array_sum($seoScores) / count($seoScores)) : 0;

        // Authority level (0-100)
        $authorityScore = min(100, (int) round(
            min($relatedPosts, 20) * 3 + // Up to 60 points for related content volume
            min($totalWordCount / 1000, 20) * 1 + // Up to 20 points for word volume
            min($avgScore, 100) * 0.2 // Up to 20 points for quality
        ));

        return [
            'score' => $authorityScore,
            'related_posts' => $relatedPosts,
            'total_words' => $totalWordCount,
            'avg_seo_score' => $avgScore,
            'total_published' => $totalPublished,
            'level' => $authorityScore >= 60 ? 'strong' : ($authorityScore >= 30 ? 'moderate' : 'weak'),
        ];
    }

    /**
     * Assess content inventory strength for the keyword.
     */
    private function assessContentInventory(string $keyword): array
    {
        global $wpdb;

        // Check if we have a pillar page
        $keywordLower = strtolower($keyword);
        $hasPillar = false;
        $hasCluster = false;
        $internalLinks = 0;

        $relatedPosts = get_posts([
            's' => $keyword,
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 20,
        ]);

        foreach ($relatedPosts as $post) {
            $wordCount = str_word_count(wp_strip_all_tags($post->post_content));
            if ($wordCount >= 2500) {
                $hasPillar = true;
            }

            // Count internal links in content
            $linkCount = preg_match_all('/href=["\']' . preg_quote(home_url(), '/') . '/i', $post->post_content);
            $internalLinks += $linkCount;
        }

        $hasCluster = count($relatedPosts) >= 5;

        $inventoryScore = 0;
        if ($hasPillar) $inventoryScore += 30;
        if ($hasCluster) $inventoryScore += 30;
        $inventoryScore += min(40, $internalLinks * 5);

        return [
            'score' => min(100, $inventoryScore),
            'has_pillar_page' => $hasPillar,
            'has_content_cluster' => $hasCluster,
            'related_pages' => count($relatedPosts),
            'internal_links' => $internalLinks,
        ];
    }

    /**
     * Estimate generic keyword difficulty via AI.
     */
    private function estimateGenericDifficulty(string $keyword): int
    {
        $cacheKey = 'aiseo_kd_' . md5($keyword);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return (int) $cached;
        }

        $prompt = "Estimate the keyword difficulty (0-100) for SEO ranking for: \"{$keyword}\". Consider search volume, competition from authoritative domains, and content saturation. Return ONLY a number between 0 and 100.";

        $result = $this->llm->call($prompt, null, null, 50, [], 'keyword_research');
        if (is_wp_error($result)) {
            return 50;
        }

        $score = (int) trim(preg_replace('/[^0-9]/', '', $result['text'] ?? '50'));
        $score = max(0, min(100, $score));

        set_transient($cacheKey, $score, 7 * DAY_IN_SECONDS);
        return $score;
    }

    /**
     * Calculate personalized difficulty by adjusting generic KD with site signals.
     */
    private function calculatePersonalizedScore(int $genericKD, array $authority, array $inventory): array
    {
        // Strong authority lowers difficulty, weak authority raises it
        $authorityAdjustment = ($authority['score'] - 50) * 0.3; // -15 to +15
        $inventoryAdjustment = ($inventory['score'] - 50) * 0.2; // -10 to +10

        $personalizedKD = $genericKD - $authorityAdjustment - $inventoryAdjustment;
        $personalizedKD = max(1, min(100, (int) round($personalizedKD)));

        $advantage = $genericKD - $personalizedKD;

        if ($personalizedKD <= 30) {
            $level = 'easy';
            $effort = __('Low effort — publish quality content and you should rank within weeks.', 'ai-seo-client');
        } elseif ($personalizedKD <= 50) {
            $level = 'moderate';
            $effort = __('Medium effort — create comprehensive content with good internal linking. Expect results in 1-3 months.', 'ai-seo-client');
        } elseif ($personalizedKD <= 70) {
            $level = 'hard';
            $effort = __('Significant effort — need pillar content, cluster strategy, and backlinks. Expect 3-6 months.', 'ai-seo-client');
        } else {
            $level = 'very_hard';
            $effort = __('Very challenging — requires comprehensive authority building, link outreach, and 6+ months of consistent effort.', 'ai-seo-client');
        }

        // Recommendation
        if ($advantage > 15) {
            $recommendation = __('Strong advantage! Your existing topical authority gives you a significant edge. Prioritize this keyword.', 'ai-seo-client');
        } elseif ($advantage > 5) {
            $recommendation = __('Moderate advantage. Your existing content provides a foundation. Build on it with targeted content.', 'ai-seo-client');
        } elseif ($advantage > -5) {
            $recommendation = __('Neutral position. No significant advantage or disadvantage. Success depends on content quality.', 'ai-seo-client');
        } else {
            $recommendation = __('Disadvantage. You lack topical authority here. Consider building a content cluster first before targeting this keyword directly.', 'ai-seo-client');
        }

        return [
            'score' => $personalizedKD,
            'level' => $level,
            'advantage' => $advantage,
            'recommendation' => $recommendation,
            'effort' => $effort,
        ];
    }

    // REST handlers
    public function restAnalyzeDifficulty(\WP_REST_Request $request): array
    {
        return $this->analyzeDifficulty(sanitize_text_field($request->get_param('keyword')));
    }

    public function restBatchAnalyze(\WP_REST_Request $request): array
    {
        $keywords = $request->get_param('keywords');
        if (!is_array($keywords)) {
            return ['results' => []];
        }

        $results = [];
        foreach (array_slice($keywords, 0, 20) as $kw) {
            $results[] = $this->analyzeDifficulty(sanitize_text_field($kw));
        }

        return ['results' => $results];
    }
}

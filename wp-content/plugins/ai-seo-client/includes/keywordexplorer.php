<?php

namespace SSEOAIClient;

/**
 * Keyword Explorer
 * 
 * Expands seed keywords by analyzing SERP title n-grams and provides
 * lightweight Jaccard-similarity keyword clustering. Stores results
 * in wp_options for later retrieval.
 */
class KeywordExplorer
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;
    private LlmClient $llm;

    public function __construct(Settings $settings, DashboardAPI $dashboardAPI, LlmClient $llm)
    {
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/keywords/expand', [
            'methods' => 'POST',
            'callback' => [$this, 'restExpand'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/keywords/cluster', [
            'methods' => 'POST',
            'callback' => [$this, 'restCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keywords' => ['type' => 'array', 'required' => true],
            ],
        ]);
    }

    /**
     * Expand a seed keyword by fetching SERP titles and extracting n-grams.
     *
     * @return array{related: array<string,int>, serp: array}
     */
    public function expand(string $seed): array
    {
        $cacheKey = 'aiseo_kwexp_' . md5($seed);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $serpResults = $this->fetchSerpResults($seed);
        if (is_wp_error($serpResults) || empty($serpResults)) {
            return ['error' => 'Could not fetch SERP data', 'related' => [], 'serp' => []];
        }

        $ngrams = [];
        $related = [];
        foreach ($serpResults as $item) {
            $title = strtolower($item['title'] ?? '');
            $tokens = preg_split('/[^a-z0-9]+/i', $title, -1, PREG_SPLIT_NO_EMPTY);
            $tokens = array_values(array_filter($tokens, fn($t) => strlen($t) > 2));
            for ($i = 0; $i < count($tokens) - 1; $i++) {
                $ng = $tokens[$i] . ' ' . $tokens[$i + 1];
                $ngrams[$ng] = ($ngrams[$ng] ?? 0) + 1;
            }
            foreach ($tokens as $t) {
                if (strlen($t) >= 4) {
                    $related[$t] = ($related[$t] ?? 0) + 1;
                }
            }
        }
        arsort($ngrams);
        arsort($related);

        $payload = [
            'related' => array_slice($ngrams + $related, 0, 25, true),
            'serp'    => $serpResults,
        ];

        // Store in options for later use
        $this->saveCluster($seed, array_keys($payload['related']), 'expand');

        set_transient($cacheKey, $payload, 3 * DAY_IN_SECONDS);
        return $payload;
    }

    /**
     * Very light clustering of given keywords using Jaccard similarity.
     *
     * @param array<string> $keywords
     * @return array<int, array<string>>
     */
    public function cluster(array $keywords): array
    {
        $clusters = [];
        foreach ($keywords as $kw) {
            $tokens = $this->tokenize($kw);
            $placed = false;
            foreach ($clusters as &$cluster) {
                $rep = $this->tokenize($cluster[0]);
                $sim = $this->jaccard($tokens, $rep);
                if ($sim >= 0.25) {
                    $cluster[] = $kw;
                    $placed = true;
                    break;
                }
            }
            if (!$placed) {
                $clusters[] = [$kw];
            }
        }

        // Store clusters
        foreach ($clusters as $idx => $group) {
            $label = 'cluster-' . ($idx + 1);
            $this->saveCluster($label, $group, 'cluster');
        }

        return $clusters;
    }

    /**
     * Fetch SERP results via DashboardAPI with AI fallback.
     */
    private function fetchSerpResults(string $keyword): array|\WP_Error
    {
        $response = $this->dashboardAPI->request('serp/search', [
            'keyword' => $keyword,
            'num' => 20,
        ]);

        if (!is_wp_error($response) && !empty($response['results'])) {
            return array_slice($response['results'], 0, 20);
        }

        // AI fallback
        $prompt = "For the keyword \"{$keyword}\", generate a realistic list of 15 Google search result titles. Return JSON array with objects having: title, link, snippet, position. Return ONLY valid JSON.";
        $result = $this->llm->call($prompt, null, null, 2000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/', $text, $matches)) {
            $text = $matches[1];
        }

        $parsed = json_decode($text, true);
        return is_array($parsed) ? array_slice($parsed, 0, 15) : [];
    }

    /**
     * Save a keyword cluster/expansion to wp_options.
     */
    private function saveCluster(string $label, array $keywords, string $type): void
    {
        $clusters = get_option('aiseo_keyword_clusters', []);
        $clusters[$label] = [
            'keywords' => $keywords,
            'type' => $type,
            'created_at' => current_time('mysql'),
        ];
        // Keep only last 50 entries
        if (count($clusters) > 50) {
            $clusters = array_slice($clusters, -50, 50, true);
        }
        update_option('aiseo_keyword_clusters', $clusters);
    }

    private function tokenize(string $s): array
    {
        $parts = preg_split('/[^a-z0-9]+/i', strtolower($s), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($parts));
    }

    private function jaccard(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }
        $ia = array_intersect($a, $b);
        $ua = array_unique(array_merge($a, $b));
        return count($ia) / count($ua);
    }

    // REST handlers
    public function restExpand(\WP_REST_Request $request): array
    {
        return $this->expand(sanitize_text_field($request->get_param('keyword')));
    }

    public function restCluster(\WP_REST_Request $request): array
    {
        $keywords = array_map('sanitize_text_field', $request->get_param('keywords') ?: []);
        return ['clusters' => $this->cluster($keywords)];
    }
}

<?php

namespace SSEOAIClient;

/**
 * Keywords Management
 * 
 * Track, explore, and manage keywords for SEO.
 * Features keyword-based ideas, cluster keywords, and keyword explorer.
 * Comparable to WPSEO AI Keywords feature.
 */
class Keywords
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
        $this->maybeCreateTable();
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        // List all keywords
        register_rest_route('sseo-ai/v1', '/keywords', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetKeywords'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Add keyword
        register_rest_route('sseo-ai/v1', '/keywords/add', [
            'methods' => 'POST',
            'callback' => [$this, 'restAddKeyword'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'cluster_id' => ['type' => 'integer'],
                'search_volume' => ['type' => 'integer'],
                'difficulty' => ['type' => 'string'],
                'cpc' => ['type' => 'string'],
            ],
        ]);

        // Delete keyword
        register_rest_route('sseo-ai/v1', '/keywords/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteKeyword'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Update keyword
        register_rest_route('sseo-ai/v1', '/keywords/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'restUpdateKeyword'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Generate keywords from topic
        register_rest_route('sseo-ai/v1', '/keywords/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateKeywords'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'topic' => ['type' => 'string', 'required' => true],
                'count' => ['type' => 'integer', 'default' => 20],
                'language' => ['type' => 'string', 'default' => 'nl'],
            ],
        ]);

        // Generate keyword-based ideas
        register_rest_route('sseo-ai/v1', '/keywords/generate-ideas', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateKeywordIdeas'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'count' => ['type' => 'integer', 'default' => 5],
            ],
        ]);

        // Import keywords from CSV
        register_rest_route('sseo-ai/v1', '/keywords/import', [
            'methods' => 'POST',
            'callback' => [$this, 'restImportKeywords'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Export keywords
        register_rest_route('sseo-ai/v1', '/keywords/export', [
            'methods' => 'GET',
            'callback' => [$this, 'restExportKeywords'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Bulk delete keywords
        register_rest_route('sseo-ai/v1', '/keywords/bulk-delete', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restBulkDeleteKeywords'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Refresh keyword data
        register_rest_route('sseo-ai/v1', '/keywords/(?P<id>\d+)/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'restRefreshKeyword'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Get keyword ideas (cached)
        register_rest_route('sseo-ai/v1', '/keywords/(?P<id>\d+)/ideas', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetKeywordIdeas'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * REST: Get keywords with filtering
     */
    public function restGetKeywords(\WP_REST_Request $request): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';

        $search = sanitize_text_field($request->get_param('search') ?? '');
        $clusterId = (int) ($request->get_param('cluster_id') ?? 0);
        $difficulty = sanitize_text_field($request->get_param('difficulty') ?? '');
        $searchIntent = sanitize_text_field($request->get_param('search_intent') ?? '');
        $language = sanitize_text_field($request->get_param('language') ?? '');
        $status = sanitize_text_field($request->get_param('status') ?? '');
        $orderby = sanitize_text_field($request->get_param('orderby') ?? 'keyword');
        $order = sanitize_text_field($request->get_param('order') ?? 'ASC');
        $limit = (int) ($request->get_param('limit') ?? 50);
        $offset = (int) ($request->get_param('offset') ?? 0);

        $clusterTable = $wpdb->prefix . 'sseo_ai_clusters';
        $clusterTableExists = $wpdb->get_var("SHOW TABLES LIKE '{$clusterTable}'") === $clusterTable;

        if ($clusterTableExists) {
            $sql = "SELECT k.*, c.pillar_topic as cluster_name 
                    FROM {$table} k
                    LEFT JOIN {$clusterTable} c ON k.cluster_id = c.id
                    WHERE 1=1";
        } else {
            $sql = "SELECT k.*, NULL as cluster_name FROM {$table} k WHERE 1=1";
        }
        $params = [];

        if ($search) {
            $sql .= " AND (k.keyword LIKE %s OR k.search_intent LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if ($clusterId) {
            $sql .= " AND k.cluster_id = %d";
            $params[] = $clusterId;
        }

        if ($difficulty) {
            $sql .= " AND k.difficulty = %s";
            $params[] = $difficulty;
        }

        if ($searchIntent) {
            $sql .= " AND k.search_intent = %s";
            $params[] = $searchIntent;
        }

        if ($language) {
            $sql .= " AND k.language = %s";
            $params[] = $language;
        }

        if ($status) {
            $sql .= " AND k.status = %s";
            $params[] = $status;
        }

        // Safe orderby
        $allowedOrderby = ['keyword', 'search_volume', 'difficulty', 'cpc', 'rank', 'created_at'];
        if (!in_array($orderby, $allowedOrderby)) {
            $orderby = 'keyword';
        }
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY k.{$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $keywords = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$table} k WHERE 1=1";
        $countParams = [];

        if ($search) {
            $countSql .= " AND (k.keyword LIKE %s OR k.search_intent LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $countParams[] = $like;
            $countParams[] = $like;
        }
        if ($clusterId) {
            $countSql .= " AND k.cluster_id = %d";
            $countParams[] = $clusterId;
        }
        if ($difficulty) {
            $countSql .= " AND k.difficulty = %s";
            $countParams[] = $difficulty;
        }
        if ($searchIntent) {
            $countSql .= " AND k.search_intent = %s";
            $countParams[] = $searchIntent;
        }
        if ($language) {
            $countSql .= " AND k.language = %s";
            $countParams[] = $language;
        }
        if ($status) {
            $countSql .= " AND k.status = %s";
            $countParams[] = $status;
        }

        $total = $wpdb->get_var($wpdb->prepare($countSql, $countParams));

        // Get filter counts
        $filterCounts = $this->getFilterCounts();

        return [
            'keywords' => $keywords,
            'total' => (int) $total,
            'limit' => $limit,
            'offset' => $offset,
            'filter_counts' => $filterCounts,
        ];
    }

    /**
     * Get counts for filter pills
     */
    private function getFilterCounts(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';

        $tracked = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'active'");
        $ranked = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE rank IS NOT NULL AND rank > 0");
        $positionChanges = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE position_change != 0");
        $newRanked = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_new = 1");

        return [
            'all' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'tracked' => $tracked,
            'ranked' => $ranked,
            'position_changes' => $positionChanges,
            'new_ranked' => $newRanked,
        ];
    }

    /**
     * REST: Add a keyword
     */
    public function restAddKeyword(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $clusterId = (int) $request->get_param('cluster_id');
        $searchVolume = (int) $request->get_param('search_volume');
        $difficulty = sanitize_text_field($request->get_param('difficulty'));
        $cpc = sanitize_text_field($request->get_param('cpc'));

        if (empty($keyword)) {
            return new \WP_Error('missing_keyword', __('Keyword is required', 'ai-seo-client'), ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';

        // Check if keyword already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE keyword = %s",
            $keyword
        ));

        if ($exists) {
            return new \WP_Error('duplicate', __('Keyword already exists', 'ai-seo-client'), ['status' => 400]);
        }

        // Fetch keyword data from API if not provided
        if (!$searchVolume || !$difficulty) {
            $keywordData = $this->fetchKeywordData($keyword);
            $searchVolume = $keywordData['search_volume'] ?? rand(500, 10000);
            $difficulty = $keywordData['difficulty'] ?? 'LOW';
            $cpc = $keywordData['cpc'] ?? '€' . number_format(rand(100, 1000) / 100, 2);
        }

        $wpdb->insert($table, [
            'keyword' => $keyword,
            'cluster_id' => $clusterId ?: null,
            'search_volume' => $searchVolume,
            'difficulty' => $difficulty,
            'cpc' => $cpc,
            'search_intent' => 'informational',
            'language' => 'nl',
            'country' => 'Netherlands',
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return [
            'success' => true,
            'id' => $wpdb->insert_id,
            'message' => __('Keyword added successfully', 'ai-seo-client'),
        ];
    }

    /**
     * Fetch keyword data from external API
     */
    private function fetchKeywordData(string $keyword): array
    {
        // Try to get data from dashboard API
        try {
            $response = $this->dashboardAPI->request('/keywords/analyze', [
                'keyword' => $keyword,
                'language' => 'nl',
                'country' => 'NL',
            ]);

            if (!is_wp_error($response) && isset($response['data'])) {
                return [
                    'search_volume' => $response['data']['search_volume'] ?? null,
                    'difficulty' => $response['data']['difficulty'] ?? null,
                    'cpc' => $response['data']['cpc'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            // Fall through to default
        }

        // Generate mock data for demo/development
        $hash = crc32($keyword);
        
        $difficulties = ['LOW', 'MEDIUM', 'HIGH'];
        
        return [
            'search_volume' => abs($hash % 10000) + 100,
            'difficulty' => $difficulties[abs($hash) % 3],
            'cpc' => '€' . number_format((abs($hash % 1000) / 100), 2),
        ];
    }

    /**
     * REST: Delete keyword
     */
    public function restDeleteKeyword(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';
        $id = (int) $request->get_param('id');

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        if ($result === false) {
            return new \WP_Error('delete_failed', __('Failed to delete keyword', 'ai-seo-client'), ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => __('Keyword deleted', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Update keyword
     */
    public function restUpdateKeyword(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';
        $id = (int) $request->get_param('id');

        $data = [];
        if ($request->has_param('keyword')) {
            $keyword = sanitize_text_field($request->get_param('keyword'));
            if (empty($keyword)) {
                return new \WP_Error('missing_keyword', __('Keyword is required', 'ai-seo-client'), ['status' => 400]);
            }
            $data['keyword'] = $keyword;
        }
        if ($request->has_param('cluster_id')) {
            $data['cluster_id'] = (int) $request->get_param('cluster_id') ?: null;
        }
        if ($request->has_param('search_intent')) {
            $data['search_intent'] = sanitize_text_field($request->get_param('search_intent'));
        }
        if ($request->has_param('difficulty')) {
            $data['difficulty'] = sanitize_text_field($request->get_param('difficulty'));
        }
        if ($request->has_param('notes')) {
            $data['notes'] = sanitize_textarea_field($request->get_param('notes'));
        }

        if (empty($data)) {
            return new \WP_Error('no_data', __('No data to update', 'ai-seo-client'), ['status' => 400]);
        }

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update($table, $data, ['id' => $id]);

        if ($result === false) {
            return new \WP_Error('update_failed', __('Failed to update keyword', 'ai-seo-client'), ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => __('Keyword updated', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Generate keywords from topic
     */
    public function restGenerateKeywords(\WP_REST_Request $request): array|\WP_Error
    {
        $topic = sanitize_text_field($request->get_param('topic'));
        $count = (int) $request->get_param('count');
        $language = sanitize_text_field($request->get_param('language'));

        // Generate keywords using LLM
        $keywords = $this->generateKeywordsWithAI($topic, $count, $language);

        if (is_wp_error($keywords)) {
            return $keywords;
        }

        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';
        $saved = [];

        foreach ($keywords as $kw) {
            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE keyword = %s",
                $kw['keyword']
            ));

            if (!$exists) {
                $wpdb->insert($table, [
                    'keyword' => $kw['keyword'],
                    'search_volume' => $kw['search_volume'] ?? rand(500, 10000),
                    'difficulty' => $kw['difficulty'] ?? 'LOW',
                    'cpc' => $kw['cpc'] ?? '€' . number_format(rand(100, 1000) / 100, 2),
                    'search_intent' => $kw['search_intent'] ?? 'informational',
                    'language' => $language,
                    'country' => 'Netherlands',
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $saved[] = [
                    'id' => $wpdb->insert_id,
                    'keyword' => $kw['keyword'],
                ];
            }
        }

        return [
            'success' => true,
            'generated' => count($keywords),
            'saved' => count($saved),
            'keywords' => $saved,
        ];
    }

    /**
     * Generate keywords using AI
     */
    private function generateKeywordsWithAI(string $topic, int $count, string $language): array|\WP_Error
    {
        $langName = $this->getLanguageName($language);

        $prompt = <<<PROMPT
You are an SEO expert. Generate {$count} relevant, high-search-volume keywords for the topic: "{$topic}"

Requirements:
- Language: {$langName}
- Mix of short-tail and long-tail keywords
- Include commercial and informational intent keywords
- Keywords should be commonly searched

Return ONLY a JSON array in this format (no markdown):
[
    {
        "keyword": "exact search keyword phrase",
        "search_intent": "informational|commercial|transactional",
        "suggested_difficulty": "LOW|MEDIUM|HIGH"
    }
]

Generate exactly {$count} keywords.
PROMPT;

        $response = $this->llm->generateText($prompt, ['max_tokens' => 2000]);

        if (is_wp_error($response)) {
            return $response;
        }

        // Clean and parse JSON
        $response = preg_replace('/^```json\s*/i', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);

        $keywords = json_decode($response, true);

        if (!is_array($keywords)) {
            // Fallback
            return $this->generateFallbackKeywords($topic, $count);
        }

        // Add mock data for missing fields
        foreach ($keywords as &$kw) {
            $hash = crc32($kw['keyword']);
            $kw['search_volume'] = abs($hash % 10000) + 100;
            $kw['difficulty'] = $kw['suggested_difficulty'] ?? ['LOW', 'MEDIUM', 'HIGH'][abs($hash) % 3];
            $kw['cpc'] = '€' . number_format((abs($hash % 1000) / 100), 2);
        }

        return $keywords;
    }

    /**
     * Generate fallback keywords
     */
    private function generateFallbackKeywords(string $topic, int $count): array
    {
        $templates = [
            "wat is {topic}",
            "{topic} betekenis",
            "{topic} uitleg",
            "{topic} voordelen",
            "{topic} nadelen",
            "{topic} kosten",
            "{topic} prijs",
            "beste {topic}",
            "{topic} vergelijken",
            "{topic} ervaringen",
            "{topic} review",
            "{topic} kopen",
            "{topic} tips",
            "{topic} handleiding",
            "hoe werkt {topic}",
        ];

        $keywords = [];
        for ($i = 0; $i < min($count, count($templates)); $i++) {
            $keyword = str_replace('{topic}', $topic, $templates[$i]);
            $hash = crc32($keyword);
            
            $keywords[] = [
                'keyword' => $keyword,
                'search_volume' => abs($hash % 10000) + 100,
                'difficulty' => ['LOW', 'MEDIUM', 'HIGH'][abs($hash) % 3],
                'cpc' => '€' . number_format((abs($hash % 1000) / 100), 2),
                'search_intent' => 'informational',
            ];
        }

        return $keywords;
    }

    /**
     * REST: Generate ideas from keyword
     */
    public function restGenerateKeywordIdeas(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $count = (int) $request->get_param('count');

        global $wpdb;
        $ideasTable = $wpdb->prefix . 'sseo_ai_ideas';

        // Generate ideas
        $prompt = <<<PROMPT
Generate {$count} blog post ideas for the keyword: "{$keyword}"

Return JSON array:
[
    {
        "title": "SEO-optimized post title",
        "description": "Brief description",
        "angle": "content angle approach"
    }
]
PROMPT;

        $response = $this->llm->generateText($prompt, ['max_tokens' => 1500]);

        if (is_wp_error($response)) {
            return $response;
        }

        $ideas = json_decode(trim($response), true);
        if (!is_array($ideas)) {
            $ideas = [];
        }

        // Save to ideas table
        $saved = [];
        foreach ($ideas as $idea) {
            $wpdb->insert($ideasTable, [
                'title' => $idea['title'],
                'description' => $idea['description'] ?? '',
                'keywords' => $keyword,
                'search_intent' => 'informational',
                'source' => 'keyword_idea',
                'status' => 'active',
                'created_at' => current_time('mysql'),
            ]);
            $saved[] = [
                'id' => $wpdb->insert_id,
                'title' => $idea['title'],
            ];
        }

        return [
            'success' => true,
            'ideas_generated' => count($saved),
            'ideas' => $saved,
        ];
    }

    /**
     * REST: Import keywords from CSV
     */
    public function restImportKeywords(\WP_REST_Request $request): array|\WP_Error
    {
        $file = $request->get_file_params()['file'] ?? null;

        if (!$file || $file['error']) {
            return new \WP_Error('upload_error', __('File upload failed', 'ai-seo-client'), ['status' => 400]);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return new \WP_Error('read_error', __('Could not read file', 'ai-seo-client'), ['status' => 500]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';

        $imported = 0;
        $skipped = 0;

        // Skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            if (empty($data[0])) continue;

            $keyword = sanitize_text_field($data[0]);
            $searchVolume = (int) ($data[1] ?? 0);
            $difficulty = sanitize_text_field($data[2] ?? 'LOW');
            $cpc = sanitize_text_field($data[3] ?? '');

            // Check if exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE keyword = %s",
                $keyword
            ));

            if (!$exists) {
                $wpdb->insert($table, [
                    'keyword' => $keyword,
                    'search_volume' => $searchVolume,
                    'difficulty' => $difficulty,
                    'cpc' => $cpc,
                    'search_intent' => 'informational',
                    'language' => 'nl',
                    'country' => 'Netherlands',
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $imported++;
            } else {
                $skipped++;
            }
        }

        fclose($handle);

        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'message' => sprintf(__('Imported %d keywords, skipped %d duplicates', 'ai-seo-client'), $imported, $skipped),
        ];
    }

    /**
     * REST: Export keywords
     */
    public function restExportKeywords(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';

        $keywords = $wpdb->get_results("SELECT * FROM {$table} ORDER BY keyword", ARRAY_A);

        // Generate CSV
        $csv = "Keyword,Search Volume,Difficulty,CPC,Search Intent,Rank,Cluster,Language\n";

        foreach ($keywords as $kw) {
            $csv .= sprintf(
                '"%s",%d,%s,%s,%s,%s,"%s",%s' . "\n",
                $kw['keyword'],
                $kw['search_volume'],
                $kw['difficulty'],
                $kw['cpc'],
                $kw['search_intent'],
                $kw['rank'] ?: '-',
                $kw['cluster_id'] ? 'Yes' : '',
                $kw['language']
            );
        }

        return [
            'success' => true,
            'csv' => $csv,
            'count' => count($keywords),
        ];
    }

    /**
     * REST: Bulk delete keywords
     */
    public function restBulkDeleteKeywords(\WP_REST_Request $request): array|\WP_Error
    {
        $ids = array_map('intval', $request->get_param('ids') ?? []);

        if (empty($ids)) {
            return new \WP_Error('no_ids', __('No keywords selected', 'ai-seo-client'), ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids));

        return [
            'success' => true,
            'deleted' => count($ids),
            'message' => sprintf(__('%d keywords deleted', 'ai-seo-client'), count($ids)),
        ];
    }

    /**
     * REST: Refresh keyword data
     */
    public function restRefreshKeyword(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_keywords';
        $id = (int) $request->get_param('id');

        $keyword = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$keyword) {
            return new \WP_Error('not_found', __('Keyword not found', 'ai-seo-client'), ['status' => 404]);
        }

        // Fetch fresh data
        $data = $this->fetchKeywordData($keyword->keyword);

        $wpdb->update($table, [
            'search_volume' => $data['search_volume'] ?? $keyword->search_volume,
            'difficulty' => $data['difficulty'] ?? $keyword->difficulty,
            'cpc' => $data['cpc'] ?? $keyword->cpc,
            'updated_at' => current_time('mysql'),
        ], ['id' => $id]);

        return [
            'success' => true,
            'message' => __('Keyword data refreshed', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Get keyword ideas
     */
    public function restGetKeywordIdeas(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $keyword = $wpdb->get_var($wpdb->prepare(
            "SELECT keyword FROM {$wpdb->prefix}sseo_ai_keywords WHERE id = %d",
            $id
        ));

        if (!$keyword) {
            return new \WP_Error('not_found', __('Keyword not found', 'ai-seo-client'), ['status' => 404]);
        }

        // Get existing ideas for this keyword
        $ideas = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sseo_ai_ideas 
             WHERE keywords LIKE %s 
             ORDER BY created_at DESC 
             LIMIT 10",
            '%' . $wpdb->esc_like($keyword) . '%'
        ));

        return [
            'keyword' => $keyword,
            'ideas_count' => count($ideas),
            'ideas' => $ideas,
        ];
    }

    /**
     * Get language name
     */
    private function getLanguageName(string $code): string
    {
        $languages = [
            'nl' => 'Dutch',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
        ];

        return $languages[$code] ?? 'Dutch';
    }

    /**
     * Create database table
     */
    public static function createTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $table = $wpdb->prefix . 'sseo_ai_keywords';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            cluster_id bigint(20) unsigned,
            search_volume int(10) unsigned DEFAULT 0,
            difficulty varchar(20) DEFAULT 'LOW',
            cpc varchar(20) DEFAULT '',
            rank int(10) unsigned,
            search_intent varchar(50) DEFAULT 'informational',
            language varchar(10) DEFAULT 'nl',
            country varchar(50) DEFAULT 'Netherlands',
            status varchar(20) DEFAULT 'active',
            notes text,
            position_change int(5) DEFAULT 0,
            is_new tinyint(1) DEFAULT 0,
            last_checked datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword (keyword),
            KEY cluster_id (cluster_id),
            KEY difficulty (difficulty),
            KEY search_intent (search_intent),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";
        dbDelta($sql);

        $clusterTable = $wpdb->prefix . 'sseo_ai_clusters';
        $clusterSql = "CREATE TABLE {$clusterTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pillar_topic varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pillar_topic (pillar_topic)
        ) {$charset};";
        dbDelta($clusterSql);
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        $clusterTable = $wpdb->prefix . 'sseo_ai_clusters';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$clusterTable}'") !== $clusterTable) {
            self::createTable();
        }
    }

    /**
     * Render the Keywords page
     */
    public function renderPage(): void
    {
        global $wpdb;
        
        // Get clusters for dropdown
        $clusterTable = $wpdb->prefix . 'sseo_ai_clusters';
        $clusters = $wpdb->get_results("SELECT id, pillar_topic as name FROM {$clusterTable} ORDER BY pillar_topic LIMIT 50");

        // Fallback: clusters generated in Topic Clusters page are stored in aiseo_topic_clusters option
        if (empty($clusters)) {
            $topicClusters = get_option('aiseo_topic_clusters', []);
            if (is_array($topicClusters) && !empty($topicClusters)) {
                foreach ($topicClusters as $cluster) {
                    $clusters[] = (object) [
                        'id' => (int) ($cluster['id'] ?? 0),
                        'name' => sanitize_text_field($cluster['topic'] ?? ($cluster['pillar_page']['title'] ?? __('Unnamed cluster', 'ai-seo-client'))),
                    ];
                }
            }
        }

        // Get initial stats
        $keywordsTable = $wpdb->prefix . 'sseo_ai_keywords';
        $totalKeywords = $wpdb->get_var("SELECT COUNT(*) FROM {$keywordsTable}") ?: 0;
        $trackedCount = $wpdb->get_var("SELECT COUNT(*) FROM {$keywordsTable} WHERE status = 'active'") ?: 0;
        $rankedCount = $wpdb->get_var("SELECT COUNT(*) FROM {$keywordsTable} WHERE rank IS NOT NULL AND rank > 0") ?: 0;
        ?>
        <div class="wrap sseo-ai-modern" id="sseo-keywords-page">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Keywords', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <!-- Actions Bar -->
                    <div class="keywords-actions-bar">
                        <div class="keyword-input-group">
                            <input type="text" id="keyword-input" placeholder="<?php esc_attr_e('Enter a keyword', 'ai-seo-client'); ?>">
                            <button type="button" class="search-btn" id="btn-search-keyword">
                                <span class="dashicons dashicons-search"></span>
                            </button>
                        </div>
                        <button type="button" class="sseo-btn-primary" id="btn-generate-keyword-ideas">
                            <?php esc_html_e('Generate keyword-based ideas', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="sseo-btn-secondary" id="btn-clusters">
                            <?php esc_html_e('Clusters', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="sseo-btn-secondary" id="btn-keywords-explorer">
                            <?php esc_html_e('Keywords explorer', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="sseo-btn-secondary" id="btn-generate-keywords">
                            <span class="dashicons dashicons-plus"></span>
                            <?php esc_html_e('Generate keywords', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="sseo-btn-icon" id="btn-refresh-keywords" title="<?php esc_attr_e('Refresh', 'ai-seo-client'); ?>">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                        <button type="button" class="sseo-btn-icon danger" id="btn-delete-keywords" title="<?php esc_attr_e('Delete selected', 'ai-seo-client'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>

                    <!-- Filter Tabs -->
                    <div class="keywords-tabs">
                        <button type="button" class="tab-btn active" data-filter="all">
                            <?php esc_html_e('All', 'ai-seo-client'); ?> <span class="tab-count" id="count-all"><?php echo esc_html($totalKeywords); ?></span>
                        </button>
                        <button type="button" class="tab-btn" data-filter="tracked">
                            <?php esc_html_e('Tracked keywords', 'ai-seo-client'); ?> <span class="tab-count" id="count-tracked"><?php echo esc_html($trackedCount); ?></span>
                        </button>
                        <button type="button" class="tab-btn" data-filter="ranked">
                            <?php esc_html_e('All ranked keywords', 'ai-seo-client'); ?> <span class="tab-count" id="count-ranked"><?php echo esc_html($rankedCount); ?></span>
                        </button>
                        <button type="button" class="tab-btn" data-filter="position_changes">
                            <?php esc_html_e('Position changes', 'ai-seo-client'); ?> <span class="tab-count" id="count-changes">0</span>
                        </button>
                        <button type="button" class="tab-btn" data-filter="new_ranked">
                            <?php esc_html_e('New ranked keywords', 'ai-seo-client'); ?> <span class="tab-count" id="count-new">0</span>
                        </button>
                    </div>

                    <!-- Table Header -->
                    <div class="keywords-table-container">
                        <div class="keywords-table-header">
                            <label class="select-all">
                                <input type="checkbox" id="select-all-keywords">
                                <span><?php esc_html_e('Selected:', 'ai-seo-client'); ?> <span id="selected-count">0</span></span>
                            </label>
                        </div>

                        <div class="keywords-filters-bar">
                            <select id="filter-cluster" class="sseo-select">
                                <option value=""><?php esc_html_e('All clusters', 'ai-seo-client'); ?></option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?php echo esc_attr($cluster->id); ?>">
                                        <?php echo esc_html($cluster->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Keywords Table -->
                        <table class="keywords-table" id="keywords-table">
                            <thead>
                                <tr>
                                    <th class="col-checkbox"></th>
                                    <th class="col-actions"><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                                    <th class="col-keyword sortable" data-sort="keyword">
                                        <?php esc_html_e('Keywords', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span>
                                    </th>
                                    <th class="col-cluster"><?php esc_html_e('All clusters', 'ai-seo-client'); ?></th>
                                    <th class="col-volume sortable" data-sort="search_volume">
                                        <?php esc_html_e('Search volume', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span>
                                    </th>
                                    <th class="col-difficulty sortable" data-sort="difficulty">
                                        <?php esc_html_e('Difficulty', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span>
                                    </th>
                                    <th class="col-cpc sortable" data-sort="cpc">
                                        <?php esc_html_e('CPC', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span>
                                    </th>
                                    <th class="col-rank sortable" data-sort="rank">
                                        <?php esc_html_e('Rank', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span>
                                    </th>
                                    <th class="col-serp"><?php esc_html_e('SERP Competitors', 'ai-seo-client'); ?></th>
                                    <th class="col-intent"><?php esc_html_e('All Search Intent', 'ai-seo-client'); ?></th>
                                    <th class="col-created sortable" data-sort="created_at">
                                        <?php esc_html_e('Created', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span>
                                    </th>
                                    <th class="col-country"><?php esc_html_e('Country Language', 'ai-seo-client'); ?></th>
                                    <th class="col-link"><?php esc_html_e('Link', 'ai-seo-client'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="keywords-tbody">
                                <!-- Keywords loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="keywords-pagination" id="keywords-pagination">
                        <div class="pagination-info">
                            <?php esc_html_e('Showing', 'ai-seo-client'); ?> <span id="showing-start">0</span>-<span id="showing-end">0</span> <?php esc_html_e('of', 'ai-seo-client'); ?> <span id="showing-total">0</span>
                        </div>
                        <div class="pagination-buttons">
                            <button type="button" class="page-btn" data-page="prev">&laquo;</button>
                            <div id="pagination-numbers"></div>
                            <button type="button" class="page-btn" data-page="next">&raquo;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Keyword Modal -->
        <div class="sseo-modal" id="modal-add-keyword" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Add Keyword', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <div class="form-group">
                        <label><?php esc_html_e('Keyword', 'ai-seo-client'); ?> *</label>
                        <input type="text" id="add-keyword-input" class="sseo-input" placeholder="<?php esc_attr_e('Enter keyword to track...', 'ai-seo-client'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Cluster', 'ai-seo-client'); ?></label>
                        <select id="add-keyword-cluster" class="sseo-select">
                            <option value=""><?php esc_html_e('No cluster', 'ai-seo-client'); ?></option>
                            <?php foreach ($clusters as $cluster): ?>
                                <option value="<?php echo esc_attr($cluster->id); ?>">
                                    <?php echo esc_html($cluster->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-add-keyword">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Add Keyword', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Keyword Modal -->
        <div class="sseo-modal" id="modal-edit-keyword" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Edit Keyword', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <input type="hidden" id="edit-keyword-id">
                    <div class="form-group">
                        <label><?php esc_html_e('Keyword', 'ai-seo-client'); ?> *</label>
                        <input type="text" id="edit-keyword-input" class="sseo-input" placeholder="<?php esc_attr_e('Enter keyword...', 'ai-seo-client'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Cluster', 'ai-seo-client'); ?></label>
                        <select id="edit-keyword-cluster" class="sseo-select">
                            <option value=""><?php esc_html_e('No cluster', 'ai-seo-client'); ?></option>
                            <?php foreach ($clusters as $cluster): ?>
                                <option value="<?php echo esc_attr($cluster->id); ?>">
                                    <?php echo esc_html($cluster->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Search Intent', 'ai-seo-client'); ?></label>
                        <select id="edit-keyword-intent" class="sseo-select">
                            <option value="informational"><?php esc_html_e('Informational', 'ai-seo-client'); ?></option>
                            <option value="commercial"><?php esc_html_e('Commercial', 'ai-seo-client'); ?></option>
                            <option value="transactional"><?php esc_html_e('Transactional', 'ai-seo-client'); ?></option>
                            <option value="navigational"><?php esc_html_e('Navigational', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Difficulty', 'ai-seo-client'); ?></label>
                        <select id="edit-keyword-difficulty" class="sseo-select">
                            <option value="LOW"><?php esc_html_e('Low', 'ai-seo-client'); ?></option>
                            <option value="MEDIUM"><?php esc_html_e('Medium', 'ai-seo-client'); ?></option>
                            <option value="HIGH"><?php esc_html_e('High', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Notes', 'ai-seo-client'); ?></label>
                        <textarea id="edit-keyword-notes" class="sseo-input" rows="3"></textarea>
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-edit-keyword">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Save Changes', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Competitor Modal -->
        <div class="sseo-modal" id="modal-add-competitor" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Add Competitor', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <input type="hidden" id="add-competitor-keyword">
                    <p class="description" style="margin-bottom: 15px;">
                        <?php esc_html_e('Enter a competitor domain to analyze for this keyword.', 'ai-seo-client'); ?>
                    </p>
                    <div class="form-group">
                        <label><?php esc_html_e('Competitor Domain', 'ai-seo-client'); ?> *</label>
                        <input type="text" id="add-competitor-domain" class="sseo-input" placeholder="competitor.com">
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-add-competitor">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Add & Analyze', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Generate Keywords Modal -->
        <div class="sseo-modal" id="modal-generate-keywords" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Generate Keywords', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <div class="form-group">
                        <label><?php esc_html_e('Topic', 'ai-seo-client'); ?></label>
                        <input type="text" id="generate-topic-input" class="sseo-input" placeholder="<?php esc_attr_e('Enter a topic to generate keywords...', 'ai-seo-client'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Number of keywords', 'ai-seo-client'); ?></label>
                        <select id="generate-count" class="sseo-select">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="30">30</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Language', 'ai-seo-client'); ?></label>
                        <select id="generate-language" class="sseo-select">
                            <option value="nl" selected>Dutch</option>
                            <option value="en">English</option>
                            <option value="de">German</option>
                        </select>
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-generate-keywords">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Generate', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Clusters Modal -->
        <div class="sseo-modal" id="modal-clusters" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Keyword Clusters', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <?php if (empty($clusters)): ?>
                        <p><?php esc_html_e('No clusters found. Generate clusters from the Topic Clusters page.', 'ai-seo-client'); ?></p>
                    <?php else: ?>
                        <table class="keywords-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Cluster', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clusters as $cluster): ?>
                                    <tr>
                                        <td><?php echo esc_html($cluster->name); ?></td>
                                        <td>
                                            <button type="button" class="sseo-btn-secondary btn-filter-cluster" data-id="<?php echo esc_attr($cluster->id); ?>">
                                                <?php esc_html_e('Show keywords', 'ai-seo-client'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Close', 'ai-seo-client'); ?></button>
                </div>
            </div>
        </div>

        <!-- Keyword Explorer Modal -->
        <div class="sseo-modal modal-large" id="modal-explorer" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Keywords Explorer', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <div class="explorer-search">
                        <input type="text" id="explorer-input" class="sseo-input" placeholder="<?php esc_attr_e('Enter keyword to explore...', 'ai-seo-client'); ?>">
                        <button type="button" class="sseo-btn-primary" id="btn-explore">
                            <?php esc_html_e('Explore', 'ai-seo-client'); ?>
                        </button>
                    </div>
                    <div id="explorer-results" class="explorer-results">
                        <p class="explorer-hint"><?php esc_html_e('Enter a keyword to see related keywords, search volume, and difficulty.', 'ai-seo-client'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let currentFilter = 'all';
            let currentSort = 'keyword';
            let currentOrder = 'ASC';
            let selectedKeywords = [];

            // Load initial data
            loadKeywords();
            loadStats();

            // Tab switching
            $('.tab-btn').on('click', function() {
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                currentFilter = $(this).data('filter');
                currentPage = 1;
                loadKeywords();
            });

            // Sorting
            $('.sortable').on('click', function() {
                const sort = $(this).data('sort');
                if (currentSort === sort) {
                    currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    currentSort = sort;
                    currentOrder = 'ASC';
                }
                loadKeywords();
            });

            // Add keyword button
            $('#btn-search-keyword').on('click', function() {
                const keyword = $('#keyword-input').val().trim();
                if (keyword) {
                    $('#add-keyword-input').val(keyword);
                    $('#modal-add-keyword').show();
                }
            });

            // Generate keywords button
            $('#btn-generate-keywords').on('click', function() {
                $('#modal-generate-keywords').show();
            });

            // Explorer button
            $('#btn-keywords-explorer').on('click', function() {
                $('#modal-explorer').show();
            });

            $('#btn-explore').on('click', function() {
                const input = $('#explorer-input').val().trim();
                const results = $('#explorer-results');
                const btn = $(this);

                if (!input) {
                    alert('<?php echo esc_js(__('Please enter a keyword to explore', 'ai-seo-client')); ?>');
                    return;
                }

                btn.prop('disabled', true).text('<?php echo esc_js(__('Exploring...', 'ai-seo-client')); ?>');
                results.html('<p><?php echo esc_js(__('Loading related keywords...', 'ai-seo-client')); ?></p>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords/expand',
                    method: 'POST',
                    data: { keyword: input }
                }).then(function(response) {
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Explore', 'ai-seo-client')); ?>');

                    if (response.error) {
                        results.html('<p style="color:#d63638;">' + response.error + '</p>');
                        return;
                    }

                    const related = response.related || {};
                    const keys = Object.keys(related);
                    if (keys.length === 0) {
                        results.html('<p><?php echo esc_js(__('No related keywords found.', 'ai-seo-client')); ?></p>');
                        return;
                    }

                    let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php echo esc_js(__('Keyword', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Relevance Score', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Action', 'ai-seo-client')); ?></th></tr></thead><tbody>';
                    keys.forEach(function(kw) {
                        html += '<tr><td>' + escapeHtml(kw) + '</td><td>' + (related[kw] || 0) + '</td><td><button type="button" class="button button-small btn-add-explored-keyword" data-keyword="' + escapeHtml(kw) + '"><?php echo esc_js(__('Add keyword', 'ai-seo-client')); ?></button></td></tr>';
                    });
                    html += '</tbody></table>';
                    results.html(html);
                }).catch(function(error) {
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Explore', 'ai-seo-client')); ?>');
                    results.html('<p style="color:#d63638;">' + (error.message || '<?php echo esc_js(__('Failed to explore keyword.', 'ai-seo-client')); ?>') + '</p>');
                });
            });

            $(document).on('click', '.btn-add-explored-keyword', function() {
                const keyword = $(this).data('keyword');
                const btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Adding...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords/add',
                    method: 'POST',
                    data: { keyword: keyword }
                }).then(function() {
                    alert('<?php echo esc_js(__('Keyword added', 'ai-seo-client')); ?>: ' + keyword);
                    btn.text('<?php echo esc_js(__('Added', 'ai-seo-client')); ?>').addClass('button-primary');
                    loadKeywords();
                    loadStats();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to add keyword', 'ai-seo-client')); ?>');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Add keyword', 'ai-seo-client')); ?>');
                });
            });

            // Clusters button
            $('#btn-clusters').on('click', function() {
                $('#modal-clusters').show();
            });

            // Filter cluster from clusters modal
            $(document).on('click', '.btn-filter-cluster', function() {
                const clusterId = $(this).data('id');
                $('#filter-cluster').val(clusterId);
                currentPage = 1;
                $('#modal-clusters').hide();
                loadKeywords();
            });

            // Close modals
            $('.modal-close, .modal-cancel').on('click', function() {
                $(this).closest('.sseo-modal').hide();
            });

            // Confirm add keyword
            $('#btn-confirm-add-keyword').on('click', function() {
                const keyword = $('#add-keyword-input').val().trim();
                const clusterId = $('#add-keyword-cluster').val();

                if (!keyword) {
                    alert('<?php echo esc_js(__('Please enter a keyword', 'ai-seo-client')); ?>');
                    return;
                }

                const btn = $(this);
                btn.find('.spinner').show();
                btn.prop('disabled', true);

                const addData = { keyword };
                if (clusterId) addData.cluster_id = parseInt(clusterId);

                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords/add',
                    method: 'POST',
                    data: addData
                }).then(function(response) {
                    $('#modal-add-keyword').hide();
                    $('#add-keyword-input').val('');
                    loadKeywords();
                    loadStats();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to add keyword', 'ai-seo-client')); ?>');
                }).finally(function() {
                    btn.find('.spinner').hide();
                    btn.prop('disabled', false);
                });
            });

            // Confirm generate keywords
            $('#btn-confirm-generate-keywords').on('click', function() {
                const topic = $('#generate-topic-input').val().trim();
                const count = $('#generate-count').val();
                const language = $('#generate-language').val();

                if (!topic) {
                    alert('<?php echo esc_js(__('Please enter a topic', 'ai-seo-client')); ?>');
                    return;
                }

                const btn = $(this);
                btn.find('.spinner').show();
                btn.prop('disabled', true);

                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords/generate',
                    method: 'POST',
                    data: { topic, count, language }
                }).then(function(response) {
                    $('#modal-generate-keywords').hide();
                    $('#generate-topic-input').val('');
                    loadKeywords();
                    loadStats();
                    alert(response.saved + ' <?php echo esc_js(__('keywords generated', 'ai-seo-client')); ?>');
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to generate keywords', 'ai-seo-client')); ?>');
                }).finally(function() {
                    btn.find('.spinner').hide();
                    btn.prop('disabled', false);
                });
            });

            // Cluster filter
            $('#filter-cluster').on('change', function() {
                currentPage = 1;
                loadKeywords();
            });

            // Refresh
            $('#btn-refresh-keywords').on('click', function() {
                loadKeywords();
                loadStats();
            });

            // Select all
            $('#select-all-keywords').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.keyword-checkbox').prop('checked', isChecked);
                updateSelectedCount();
            });

            // Individual checkbox
            $(document).on('change', '.keyword-checkbox', function() {
                updateSelectedCount();
            });

            // Bulk delete
            $('#btn-delete-keywords').on('click', function() {
                if (selectedKeywords.length === 0) {
                    alert('<?php echo esc_js(__('Please select keywords first', 'ai-seo-client')); ?>');
                    return;
                }

                if (!confirm('<?php echo esc_js(__('Delete selected keywords?', 'ai-seo-client')); ?>')) return;

                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords/bulk-delete',
                    method: 'DELETE',
                    data: { ids: selectedKeywords }
                }).then(function() {
                    loadKeywords();
                    loadStats();
                    selectedKeywords = [];
                    $('#select-all-keywords').prop('checked', false);
                    updateSelectedCount();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to delete', 'ai-seo-client')); ?>');
                });
            });

            // Load keywords
            function loadKeywords() {
                const params = {
                    limit: 20,
                    offset: (currentPage - 1) * 20,
                    orderby: currentSort,
                    order: currentOrder,
                };

                if (currentFilter !== 'all') {
                    // Map filter to appropriate API parameter
                    if (currentFilter === 'tracked') params.status = 'active';
                }

                const clusterId = $('#filter-cluster').val();
                if (clusterId) params.cluster_id = clusterId;

                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords?' + $.param(params),
                    method: 'GET'
                }).then(function(response) {
                    renderKeywords(response.keywords);
                    updatePagination(response.total);
                    $('#showing-start').text(response.offset + 1);
                    $('#showing-end').text(Math.min(response.offset + response.keywords.length, response.total));
                    $('#showing-total').text(response.total);
                }).catch(function(error) {
                    console.error('Failed to load keywords:', error);
                });
            }

            // Load stats
            function loadStats() {
                wp.apiFetch({
                    path: 'sseo-ai/v1/keywords?limit=1',
                    method: 'GET'
                }).then(function(response) {
                    const counts = response.filter_counts || {};
                    $('#count-all').text(counts.all || 0);
                    $('#count-tracked').text(counts.tracked || 0);
                    $('#count-ranked').text(counts.ranked || 0);
                    $('#count-changes').text(counts.position_changes || 0);
                    $('#count-new').text(counts.new_ranked || 0);
                });
            }

            // Render keywords
            function renderKeywords(keywords) {
                const tbody = $('#keywords-tbody');
                
                if (!keywords || keywords.length === 0) {
                    tbody.html('<tr><td colspan="13" class="no-results"><?php echo esc_js(__('No keywords found', 'ai-seo-client')); ?></td></tr>');
                    return;
                }

                let html = '';
                keywords.forEach(function(kw) {
                    const difficulty = kw.difficulty || 'LOW';
                    const difficultyClass = 'difficulty-' + difficulty.toLowerCase();
                    const rankDisplay = kw.rank ? '#' + kw.rank : '-';
                    const ideasCount = kw.ideas_count || 0;
                    const postsCount = kw.posts_count || 0;
                    const searchIntent = kw.search_intent || 'informational';

                    html += `
                        <tr data-id="${kw.id}">
                            <td class="col-checkbox">
                                <input type="checkbox" class="keyword-checkbox" value="${kw.id}">
                            </td>
                            <td class="col-actions">
                                <button type="button" class="btn-action btn-edit" title="<?php echo esc_js(__('Edit', 'ai-seo-client')); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button type="button" class="btn-action btn-refresh" data-action="refresh" title="<?php echo esc_js(__('Refresh data', 'ai-seo-client')); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                                <button type="button" class="btn-action btn-add" data-action="add" title="<?php echo esc_js(__('Add to ideas', 'ai-seo-client')); ?>">
                                    <span class="dashicons dashicons-plus"></span>
                                </button>
                                <button type="button" class="btn-action btn-chart" data-action="chart" title="<?php echo esc_js(__('View chart', 'ai-seo-client')); ?>">
                                    <span class="dashicons dashicons-chart-line"></span>
                                </button>
                            </td>
                            <td class="col-keyword">
                                <strong>${escapeHtmlTemplate(kw.keyword)}</strong>
                            </td>
                            <td class="col-cluster">
                                ${kw.cluster_name ? `<span class="cluster-badge" data-id="${kw.cluster_id || ''}">${escapeHtmlTemplate(kw.cluster_name)}</span>` : `<button class="btn-set-cluster" data-id="${kw.id}">+ <?php echo esc_js(__('set cluster', 'ai-seo-client')); ?></button>`}
                            </td>
                            <td class="col-volume">${(kw.search_volume || 0).toLocaleString()}</td>
                            <td class="col-difficulty">
                                <span class="difficulty-badge ${difficultyClass}">${difficulty}</span>
                            </td>
                            <td class="col-cpc">${kw.cpc || '-'}</td>
                            <td class="col-rank">${rankDisplay}</td>
                            <td class="col-serp">
                                <button class="btn-show-competitors" data-keyword="${escapeTemplate(kw.keyword)}">
                                    + <?php echo esc_js(__('show competitors', 'ai-seo-client')); ?>
                                </button>
                            </td>
                            <td class="col-intent">
                                <span class="intent-badge ${searchIntent}">${searchIntent}</span>
                            </td>
                            <td class="col-created">${formatDate(kw.created_at)}</td>
                            <td class="col-country">${escapeHtmlTemplate(kw.country || 'Netherlands')} <span class="lang-flag">${getFlag(kw.language || 'nl')}</span></td>
                            <td class="col-link">
                                <button type="button" class="btn-action btn-delete" data-action="delete" title="<?php echo esc_js(__('Delete', 'ai-seo-client')); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.html(html);
                bindRowActions();
            }

            // Bind row actions
            function bindRowActions() {
                // Edit
                $('.btn-edit').on('click', function() {
                    const row = $(this).closest('tr');
                    const id = row.data('id');
                    const keyword = row.find('.col-keyword strong').text();
                    const clusterId = row.find('.col-cluster .cluster-badge').data('id') || '';
                    const intent = row.find('.col-intent .intent-badge').attr('class').replace('intent-badge ', '').trim() || 'informational';
                    const difficulty = (row.find('.difficulty-badge').text() || 'LOW').toUpperCase();

                    $('#edit-keyword-id').val(id);
                    $('#edit-keyword-input').val(keyword);
                    $('#edit-keyword-cluster').val(clusterId);
                    $('#edit-keyword-intent').val(intent);
                    $('#edit-keyword-difficulty').val(difficulty);
                    $('#edit-keyword-notes').val('');
                    $('#modal-edit-keyword').show();
                });

                // Save edit
                $('#btn-confirm-edit-keyword').on('click', function() {
                    const id = $('#edit-keyword-id').val();
                    const keyword = $('#edit-keyword-input').val().trim();
                    const clusterId = $('#edit-keyword-cluster').val();
                    const intent = $('#edit-keyword-intent').val();
                    const difficulty = $('#edit-keyword-difficulty').val();
                    const notes = $('#edit-keyword-notes').val();
                    const btn = $(this);

                    if (!keyword) {
                        alert('<?php echo esc_js(__('Please enter a keyword', 'ai-seo-client')); ?>');
                        return;
                    }

                    btn.find('.spinner').show();
                    btn.prop('disabled', true);

                    const data = {
                        keyword: keyword,
                        search_intent: intent,
                        difficulty: difficulty,
                    };
                    if (clusterId) data.cluster_id = parseInt(clusterId);
                    if (notes) data.notes = notes;

                    wp.apiFetch({
                        path: 'sseo-ai/v1/keywords/' + id,
                        method: 'PUT',
                        data: data
                    }).then(function() {
                        $('#modal-edit-keyword').hide();
                        loadKeywords();
                        loadStats();
                    }).catch(function(error) {
                        alert(error.message || '<?php echo esc_js(__('Failed to update keyword', 'ai-seo-client')); ?>');
                    }).finally(function() {
                        btn.find('.spinner').hide();
                        btn.prop('disabled', false);
                    });
                });

                // Delete
                $('.btn-delete[data-action="delete"]').on('click', function() {
                    const row = $(this).closest('tr');
                    const id = row.data('id');
                    
                    if (!confirm('<?php echo esc_js(__('Delete this keyword?', 'ai-seo-client')); ?>')) return;
                    
                    wp.apiFetch({
                        path: 'sseo-ai/v1/keywords/' + id,
                        method: 'DELETE'
                    }).then(function() {
                        row.remove();
                        loadStats();
                    }).catch(function(error) {
                        alert(error.message || '<?php echo esc_js(__('Failed to delete', 'ai-seo-client')); ?>');
                    });
                });

                // Refresh
                $('.btn-refresh').on('click', function() {
                    const id = $(this).closest('tr').data('id');
                    const btn = $(this);
                    btn.prop('disabled', true);
                    
                    wp.apiFetch({
                        path: 'sseo-ai/v1/keywords/' + id + '/refresh',
                        method: 'POST'
                    }).then(function() {
                        loadKeywords();
                    }).catch(function(error) {
                        alert(error.message || '<?php echo esc_js(__('Failed to refresh', 'ai-seo-client')); ?>');
                    }).finally(function() {
                        btn.prop('disabled', false);
                    });
                });

                // Add to ideas
                $('.btn-add').on('click', function() {
                    const row = $(this).closest('tr');
                    const id = row.data('id');
                    
                    wp.apiFetch({
                        path: 'sseo-ai/v1/keywords/' + id + '/ideas',
                        method: 'GET'
                    }).then(function(response) {
                        alert(response.ideas_count + ' <?php echo esc_js(__('ideas for this keyword', 'ai-seo-client')); ?>');
                    }).catch(function(error) {
                        alert(error.message || '<?php echo esc_js(__('Failed to get ideas', 'ai-seo-client')); ?>');
                    });
                });

                // Show competitors — open add competitor modal, then redirect inside Fyndable dashboard
                $('.btn-show-competitors').on('click', function() {
                    const keyword = $(this).data('keyword');
                    if (keyword) {
                        $('#add-competitor-keyword').val(keyword);
                        $('#add-competitor-domain').val('');
                        $('#modal-add-competitor').show();
                    }
                });

                $('#btn-confirm-add-competitor').on('click', function() {
                    const keyword = $('#add-competitor-keyword').val();
                    const domain = $('#add-competitor-domain').val().trim();
                    const btn = $(this);

                    if (!domain) {
                        alert('<?php echo esc_js(__('Please enter a competitor domain', 'ai-seo-client')); ?>');
                        return;
                    }

                    btn.find('.spinner').show();
                    btn.prop('disabled', true);

                    jQuery.post(ajaxurl, {
                        action: 'sseo_ai_analyze_competitor',
                        domain: domain,
                        nonce: '<?php echo wp_create_nonce('sseo_competitor'); ?>'
                    }, function(response) {
                        btn.find('.spinner').hide();
                        btn.prop('disabled', false);

                        if (response.success) {
                            $('#modal-add-competitor').hide();
                            window.location.href = '<?php echo esc_js(admin_url('admin.php?page=fyndable-dashboard&fyndable_page=ai-seo-competitor-research')); ?>&keyword=' + encodeURIComponent(keyword);
                        } else {
                            alert(response.data.message || '<?php echo esc_js(__('Error analyzing competitor', 'ai-seo-client')); ?>');
                        }
                    }).fail(function() {
                        btn.find('.spinner').hide();
                        btn.prop('disabled', false);
                        alert('<?php echo esc_js(__('Error analyzing competitor', 'ai-seo-client')); ?>');
                    });
                });
            }

            // Update pagination
            function updatePagination(total) {
                const totalPages = Math.ceil(total / 20);
                const container = $('#pagination-numbers');
                let html = '';

                for (let i = 1; i <= totalPages && i <= 10; i++) {
                    const active = i === currentPage ? 'active' : '';
                    html += `<button type="button" class="page-btn ${active}" data-page="${i}">${i}</button>`;
                }

                container.html(html);
            }

            // Pagination click
            $(document).on('click', '#pagination-numbers .page-btn', function() {
                currentPage = parseInt($(this).data('page'));
                loadKeywords();
            });

            // Update selected count
            function updateSelectedCount() {
                selectedKeywords = [];
                $('.keyword-checkbox:checked').each(function() {
                    selectedKeywords.push(parseInt($(this).val()));
                });
                $('#selected-count').text(selectedKeywords.length);
            }

            // Helpers
            function formatDate(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('<?php echo esc_js(str_replace('_', '-', get_locale())); ?>', {
                    month: 'short',
                    day: 'numeric'
                });
            }

            function getFlag(language) {
                const flags = {
                    'nl': '🇳🇱',
                    'en': '🇬🇧',
                    'de': '🇩🇪',
                    'fr': '🇫🇷',
                    'es': '🇪🇸',
                };
                return flags[language] || '🏳️';
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function escapeTemplate(text) {
                if (!text) return '';
                return String(text)
                    .replace(/\\/g, '\\\\')
                    .replace(/`/g, '\\`')
                    .replace(/\$\{/g, '\\${');
            }

            function escapeHtmlTemplate(text) {
                return escapeTemplate(escapeHtml(text));
            }
        });
        </script>
        <?php
    }
}

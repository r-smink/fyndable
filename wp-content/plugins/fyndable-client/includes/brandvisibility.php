<?php

namespace SSEOAIClient;

/**
 * Brand & AI Search Visibility Tracker
 *
 * Tracks how often and in what context a brand is mentioned
 * by AI-powered search engines and chatbots (ChatGPT, Perplexity, Gemini).
 */
class BrandVisibilityTracker
{
    private const TABLE_NAME = 'sseo_ai_brand_mentions';
    private const SETTINGS_KEY = 'sseo_ai_brand_visibility_settings';

    private LlmClient $llmClient;
    private Settings $settings;

    public function __construct(LlmClient $llmClient, Settings $settings)
    {
        $this->llmClient = $llmClient;
        $this->settings = $settings;
    }

    /**
     * Create database table on activation
     */
    public static function createTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            platform varchar(50) NOT NULL DEFAULT 'chatgpt',
            query_text text NOT NULL,
            brand_mentioned tinyint(1) NOT NULL DEFAULT 0,
            mention_position int(11) DEFAULT 0,
            mention_context text,
            mention_excerpt text,
            sentiment varchar(20) DEFAULT 'neutral',
            competitors_mentioned text,
            full_response longtext,
            model varchar(50) DEFAULT NULL,
            tokens_used int(11) DEFAULT 0,
            duration_ms int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'success',
            error_message text,
            PRIMARY KEY (id),
            KEY scan_date (scan_date),
            KEY platform (platform),
            KEY brand_mentioned (brand_mentioned)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get visibility settings
     */
    public function getSettings(): array
    {
        $defaults = [
            'brand_name' => '',
            'product_names' => '',
            'competitors' => '',
            'queries' => "What are the best {category} tools?\nWhich {category} would you recommend?\nWhat are the top {category} options?\nCan you compare the best {category}?\nWhat should I look for in a {category}?",
            'category' => '',
            'platforms' => ['chatgpt', 'perplexity', 'gemini'],
            'scan_frequency' => 'manual',
        ];

        $saved = get_option(self::SETTINGS_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return array_merge($defaults, $saved);
    }

    /**
     * Save visibility settings
     */
    public function saveSettings(array $data): void
    {
        $settings = [
            'brand_name' => sanitize_text_field($data['brand_name'] ?? ''),
            'product_names' => sanitize_textarea_field($data['product_names'] ?? ''),
            'competitors' => sanitize_textarea_field($data['competitors'] ?? ''),
            'queries' => sanitize_textarea_field($data['queries'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'platforms' => array_map('sanitize_text_field', $data['platforms'] ?? ['chatgpt']),
            'scan_frequency' => sanitize_text_field($data['scan_frequency'] ?? 'manual'),
        ];

        update_option(self::SETTINGS_KEY, $settings);
    }

    /**
     * Get platform personas for AI queries
     */
    private function getPlatformPersonas(): array
    {
        return [
            'chatgpt' => [
                'name' => 'ChatGPT',
                'system_prompt' => 'You are ChatGPT, a helpful AI assistant. A user is asking you for recommendations. Answer as you naturally would, providing a helpful and informative response. List specific brands and products when relevant. Be objective and comprehensive.',
                'model' => 'gpt-4',
            ],
            'perplexity' => [
                'name' => 'Perplexity',
                'system_prompt' => 'You are Perplexity AI, an AI search engine. A user is searching for recommendations. Provide a comprehensive answer based on available information. Cite specific brands and products. Be factual and cite sources when possible.',
                'model' => 'gpt-4',
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'system_prompt' => 'You are Google Gemini, an AI assistant integrated into Google Search. A user is asking for recommendations. Provide a helpful, balanced answer. Mention specific brands and products when relevant. Be concise but thorough.',
                'model' => 'gpt-4',
            ],
        ];
    }

    /**
     * Run a visibility scan across configured platforms
     */
    public function runScan(): array
    {
        $config = $this->getSettings();
        $brandName = $config['brand_name'];

        if (empty($brandName)) {
            return ['error' => 'No brand name configured'];
        }

        $queries = $this->parseQueries($config['queries'], $config['category']);
        $platforms = $config['platforms'];
        $personas = $this->getPlatformPersonas();

        $results = [];
        $competitors = array_filter(array_map('trim', explode("\n", $config['competitors'])));

        foreach ($platforms as $platformKey) {
            if (!isset($personas[$platformKey])) {
                continue;
            }

            $persona = $personas[$platformKey];

            foreach ($queries as $query) {
                $result = $this->queryPlatform($platformKey, $persona, $query, $brandName, $competitors);
                $results[] = $result;
            }
        }

        return [
            'success' => true,
            'scanned' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Parse query templates, replacing {category} with actual category
     */
    private function parseQueries(string $queriesText, string $category): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $queriesText)));
        $category = $category ?: 'product/service';

        return array_map(function ($line) use ($category) {
            return str_replace('{category}', $category, $line);
        }, $lines);
    }

    /**
     * Query a single AI platform with a single query
     */
    private function queryPlatform(string $platformKey, array $persona, string $query, string $brandName, array $competitors): array
    {
        $startTime = microtime(true);

        $response = $this->llmClient->call(
            $query,
            $persona['model'],
            null,
            2000,
            [
                'endpoint' => 'brand_visibility.scan',
                'context' => 'platform:' . $platformKey . ' query:' . substr($query, 0, 100),
            ]
        );

        $durationMs = (int)((microtime(true) - $startTime) * 1000);

        if (is_wp_error($response)) {
            $mention = $this->logMention([
                'platform' => $platformKey,
                'query_text' => $query,
                'brand_mentioned' => 0,
                'mention_position' => 0,
                'mention_context' => '',
                'mention_excerpt' => '',
                'sentiment' => 'neutral',
                'competitors_mentioned' => '',
                'full_response' => '',
                'model' => $persona['model'],
                'tokens_used' => 0,
                'duration_ms' => $durationMs,
                'status' => 'error',
                'error_message' => $response->get_error_message(),
            ]);

            return [
                'id' => $mention,
                'platform' => $platformKey,
                'query' => $query,
                'brand_mentioned' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $responseText = $response['text'] ?? '';
        $tokens = ($response['usage']['prompt_tokens'] ?? 0) + ($response['usage']['completion_tokens'] ?? 0);

        // Analyze the response for brand mentions
        $analysis = $this->analyzeResponse($responseText, $brandName, $competitors);

        $mention = $this->logMention([
            'platform' => $platformKey,
            'query_text' => $query,
            'brand_mentioned' => $analysis['brand_mentioned'] ? 1 : 0,
            'mention_position' => $analysis['mention_position'],
            'mention_context' => $analysis['mention_context'],
            'mention_excerpt' => $analysis['mention_excerpt'],
            'sentiment' => $analysis['sentiment'],
            'competitors_mentioned' => implode(', ', $analysis['competitors_found']),
            'full_response' => $responseText,
            'model' => $response['model'] ?? $persona['model'],
            'tokens_used' => $tokens,
            'duration_ms' => $durationMs,
            'status' => 'success',
            'error_message' => '',
        ]);

        return [
            'id' => $mention,
            'platform' => $platformKey,
            'query' => $query,
            'brand_mentioned' => $analysis['brand_mentioned'],
            'mention_position' => $analysis['mention_position'],
            'sentiment' => $analysis['sentiment'],
            'competitors' => $analysis['competitors_found'],
            'excerpt' => $analysis['mention_excerpt'],
        ];
    }

    /**
     * Analyze AI response for brand mentions, position, sentiment
     */
    private function analyzeResponse(string $response, string $brandName, array $competitors): array
    {
        $responseLower = mb_strtolower($response);
        $brandLower = mb_strtolower($brandName);

        // Check if brand is mentioned
        $brandMentioned = strpos($responseLower, $brandLower) !== false;

        // Find mention position (which brand/product is mentioned first, second, etc.)
        $mentionPosition = 0;
        $mentionContext = '';
        $mentionExcerpt = '';

        if ($brandMentioned) {
            // Find all brand mentions and extract context
            $allBrands = array_merge([$brandName], $competitors);
            $positions = [];

            foreach ($allBrands as $idx => $brand) {
                $brandLowerTmp = mb_strtolower($brand);
                $pos = strpos($responseLower, $brandLowerTmp);
                if ($pos !== false) {
                    $positions[] = ['brand' => $brand, 'position' => $pos, 'index' => $idx];
                }
            }

            // Sort by position in text
            usort($positions, function ($a, $b) {
                return $a['position'] - $b['position'];
            });

            // Find the rank of our brand
            foreach ($positions as $rank => $p) {
                if (mb_strtolower($p['brand']) === $brandLower) {
                    $mentionPosition = $rank + 1;
                    break;
                }
            }

            // Extract context around brand mention (100 chars before and after)
            $brandPos = strpos($responseLower, $brandLower);
            if ($brandPos !== false) {
                $start = max(0, $brandPos - 100);
                $length = min(strlen($response) - $start, strlen($brandName) + 200);
                $mentionExcerpt = '...' . substr($response, $start, $length) . '...';
                $mentionContext = trim(strip_tags($mentionExcerpt));
            }
        }

        // Detect competitors mentioned
        $competitorsFound = [];
        foreach ($competitors as $competitor) {
            $competitorLower = mb_strtolower($competitor);
            if (strpos($responseLower, $competitorLower) !== false) {
                $competitorsFound[] = $competitor;
            }
        }

        // Simple sentiment analysis based on context around brand mention
        $sentiment = 'neutral';
        if ($brandMentioned && !empty($mentionContext)) {
            $positiveWords = ['best', 'top', 'leading', 'excellent', 'great', 'recommend', 'outstanding', 'superior', 'popular', 'trusted', 'reliable', 'innovative'];
            $negativeWords = ['avoid', 'poor', 'bad', 'expensive', 'limited', 'lacking', 'issues', 'problems', 'disappointing', 'weak'];

            $contextLower = mb_strtolower($mentionContext);
            $positiveCount = 0;
            $negativeCount = 0;

            foreach ($positiveWords as $word) {
                if (strpos($contextLower, $word) !== false) {
                    $positiveCount++;
                }
            }
            foreach ($negativeWords as $word) {
                if (strpos($contextLower, $word) !== false) {
                    $negativeCount++;
                }
            }

            if ($positiveCount > $negativeCount) {
                $sentiment = 'positive';
            } elseif ($negativeCount > $positiveCount) {
                $sentiment = 'negative';
            }
        }

        return [
            'brand_mentioned' => $brandMentioned,
            'mention_position' => $mentionPosition,
            'mention_context' => $mentionContext,
            'mention_excerpt' => $mentionExcerpt,
            'sentiment' => $sentiment,
            'competitors_found' => $competitorsFound,
        ];
    }

    /**
     * Log a mention to the database
     */
    private function logMention(array $data): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $wpdb->insert($table, [
            'scan_date' => current_time('mysql'),
            'platform' => $data['platform'],
            'query_text' => $data['query_text'],
            'brand_mentioned' => $data['brand_mentioned'],
            'mention_position' => $data['mention_position'],
            'mention_context' => substr($data['mention_context'], 0, 65535),
            'mention_excerpt' => substr($data['mention_excerpt'], 0, 65535),
            'sentiment' => $data['sentiment'],
            'competitors_mentioned' => $data['competitors_mentioned'],
            'full_response' => substr($data['full_response'], 0, 65535),
            'model' => $data['model'],
            'tokens_used' => $data['tokens_used'],
            'duration_ms' => $data['duration_ms'],
            'status' => $data['status'],
            'error_message' => $data['error_message'],
        ], [
            '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s'
        ]);

        return (int)$wpdb->insert_id;
    }

    /**
     * Get mentions with filtering and pagination
     */
    public function getMentions(int $limit = 50, int $offset = 0, string $platform = '', string $filter = ''): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $where = ' WHERE 1=1';
        $params = [];

        if ($platform) {
            $where .= ' AND platform = %s';
            $params[] = $platform;
        }

        if ($filter === 'mentioned') {
            $where .= ' AND brand_mentioned = 1';
        } elseif ($filter === 'not_mentioned') {
            $where .= ' AND brand_mentioned = 0';
        } elseif ($filter === 'errors') {
            $where .= ' AND status = %s';
            $params[] = 'error';
        }

        $sql = "SELECT * FROM {$table}{$where} ORDER BY scan_date DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get total mention count
     */
    public function getTotalCount(string $platform = '', string $filter = ''): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $where = ' WHERE 1=1';
        $params = [];

        if ($platform) {
            $where .= ' AND platform = %s';
            $params[] = $platform;
        }

        if ($filter === 'mentioned') {
            $where .= ' AND brand_mentioned = 1';
        } elseif ($filter === 'not_mentioned') {
            $where .= ' AND brand_mentioned = 0';
        } elseif ($filter === 'errors') {
            $where .= ' AND status = %s';
            $params[] = 'error';
        }

        $sql = "SELECT COUNT(*) FROM {$table}{$where}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int)$wpdb->get_var($sql);
    }

    /**
     * Get visibility statistics
     */
    public function getStats(string $period = '30d'): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $days = $period === '7d' ? 7 : ($period === '90d' ? 90 : 30);
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $totalScans = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE scan_date >= %s", $since));
        $brandMentions = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE scan_date >= %s AND brand_mentioned = 1", $since));
        $errors = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE scan_date >= %s AND status = 'error'", $since));

        // Visibility score = (mentions / total successful scans) * 100
        $successfulScans = $totalScans - $errors;
        $visibilityScore = $successfulScans > 0 ? round(($brandMentions / $successfulScans) * 100, 1) : 0;

        // Average position when mentioned
        $avgPosition = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT AVG(mention_position) FROM {$table} WHERE scan_date >= %s AND brand_mentioned = 1 AND mention_position > 0",
            $since
        ));

        // Sentiment breakdown
        $positive = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE scan_date >= %s AND sentiment = 'positive'", $since));
        $neutral = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE scan_date >= %s AND sentiment = 'neutral' AND brand_mentioned = 1", $since));
        $negative = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE scan_date >= %s AND sentiment = 'negative'", $since));

        // Per-platform breakdown
        $platformStats = $wpdb->get_results($wpdb->prepare(
            "SELECT platform,
                COUNT(*) as total,
                SUM(CASE WHEN brand_mentioned = 1 THEN 1 ELSE 0 END) as mentions,
                AVG(CASE WHEN brand_mentioned = 1 AND mention_position > 0 THEN mention_position ELSE NULL END) as avg_position
            FROM {$table}
            WHERE scan_date >= %s
            GROUP BY platform",
            $since
        ), ARRAY_A);

        // Top competitors mentioned
        $competitorMentions = [];
        $allCompetitorRows = $wpdb->get_col($wpdb->prepare(
            "SELECT competitors_mentioned FROM {$table} WHERE scan_date >= %s AND competitors_mentioned != ''",
            $since
        ));

        foreach ($allCompetitorRows as $row) {
            $names = array_map('trim', explode(',', $row));
            foreach ($names as $name) {
                if (!empty($name)) {
                    $competitorMentions[$name] = ($competitorMentions[$name] ?? 0) + 1;
                }
            }
        }
        arsort($competitorMentions);
        $topCompetitors = array_slice($competitorMentions, 0, 10, true);

        // Trend data (last N days)
        $trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(scan_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN brand_mentioned = 1 THEN 1 ELSE 0 END) as mentions
            FROM {$table}
            WHERE scan_date >= %s
            GROUP BY DATE(scan_date)
            ORDER BY date ASC",
            $since
        ), ARRAY_A);

        return [
            'total_scans' => $totalScans,
            'brand_mentions' => $brandMentions,
            'errors' => $errors,
            'visibility_score' => $visibilityScore,
            'avg_position' => round($avgPosition, 1),
            'sentiment' => [
                'positive' => $positive,
                'neutral' => $neutral,
                'negative' => $negative,
            ],
            'platform_stats' => $platformStats ?: [],
            'top_competitors' => $topCompetitors,
            'trend' => $trend ?: [],
        ];
    }

    /**
     * Get trend data for charts
     */
    public function getTrend(int $days = 30): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(scan_date) as date,
                COUNT(*) as total,
                SUM(CASE WHEN brand_mentioned = 1 THEN 1 ELSE 0 END) as mentions,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
            FROM {$table}
            WHERE scan_date >= %s
            GROUP BY DATE(scan_date)
            ORDER BY date ASC",
            $since
        ), ARRAY_A);
    }

    /**
     * Get last scan date
     */
    public function getLastScanDate(): ?string
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $result = $wpdb->get_var("SELECT MAX(scan_date) FROM {$table}");
        return $result ?: null;
    }

    /**
     * Delete all mention data
     */
    public function clearAll(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("TRUNCATE TABLE {$table}");
    }

    /**
     * Register REST API endpoints
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', 'brand-visibility/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'restRunScan'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', 'brand-visibility/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStats'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', 'brand-visibility/mentions', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetMentions'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', 'brand-visibility/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveSettings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', 'brand-visibility/clear', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restClearAll'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function restRunScan(\WP_REST_Request $request): array|\WP_Error
    {
        $result = $this->runScan();
        if (isset($result['error'])) {
            return new \WP_Error('scan_error', $result['error'], ['status' => 400]);
        }
        return $result;
    }

    public function restGetStats(\WP_REST_Request $request): array
    {
        $period = sanitize_text_field($request->get_param('period') ?? '30d');
        return $this->getStats($period);
    }

    public function restGetMentions(\WP_REST_Request $request): array
    {
        $limit = (int)($request->get_param('limit') ?? 50);
        $offset = (int)($request->get_param('offset') ?? 0);
        $platform = sanitize_text_field($request->get_param('platform') ?? '');
        $filter = sanitize_text_field($request->get_param('filter') ?? '');

        $mentions = $this->getMentions($limit, $offset, $platform, $filter);
        $total = $this->getTotalCount($platform, $filter);

        return [
            'mentions' => $mentions,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function restSaveSettings(\WP_REST_Request $request): array
    {
        $this->saveSettings($request->get_params());
        return ['success' => true, 'settings' => $this->getSettings()];
    }

    public function restClearAll(): array
    {
        $this->clearAll();
        return ['success' => true, 'message' => 'All visibility data cleared'];
    }
}

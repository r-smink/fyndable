<?php

namespace SSEOAIClient;

/**
 * Keyword Rank Tracker
 * 
 * Tracks keyword positions daily via SERP API (proxied through SaaS Dashboard).
 * Stores historical data for trend charts. Admin page with keyword management.
 * Comparable to RankMath Rank Tracker, SurferSEO SERP Tracker.
 */
class RankTracker
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;
    private ?LocalSerp $localSerp = null;
    private string $tableName;
    private string $keywordsTable;

    public function __construct(Settings $settings, DashboardAPI $dashboardAPI)
    {
        global $wpdb;
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;
        $this->tableName = $wpdb->prefix . 'sseo_ai_rank_history';
        $this->keywordsTable = $wpdb->prefix . 'sseo_ai_tracked_keywords';
    }

    public function setLocalSerp(?LocalSerp $localSerp): void
    {
        $this->localSerp = $localSerp;
    }

    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Create/update tables on every load (ensures upgrades are applied)
        $this->createTables();

        // Daily cron for rank checking
        if (!wp_next_scheduled('sseo_ai_rank_check_cron')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_rank_check_cron');
        }
        add_action('sseo_ai_rank_check_cron', [$this, 'runDailyCheck']);
    }

    /**
     * Create database tables on activation
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE {$this->keywordsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            url varchar(500) DEFAULT '',
            post_id bigint(20) unsigned DEFAULT 0,
            search_engine varchar(20) DEFAULT 'google',
            country varchar(5) DEFAULT 'nl',
            language varchar(5) DEFAULT 'nl',
            current_position int DEFAULT 0,
            best_position int DEFAULT 0,
            previous_position int DEFAULT 0,
            last_checked datetime DEFAULT NULL,
            last_provider varchar(50) DEFAULT NULL,
            last_error text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            KEY keyword_idx (keyword),
            KEY post_id_idx (post_id)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned DEFAULT 0,
            position int DEFAULT 0,
            url varchar(500) DEFAULT '',
            checked_at date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword_id_idx (keyword_id),
            KEY post_id_idx (post_id),
            KEY checked_at_idx (checked_at),
            UNIQUE KEY keyword_date (keyword_id, checked_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        
        // Upgrade: Add post_id column if it doesn't exist (for existing installs)
        $this->maybeUpgradeTable();
    }
    
    /**
     * Upgrade existing tables
     */
    private function maybeUpgradeTable(): void
    {
        global $wpdb;
        
        // Check if post_id column exists
        $columnExists = $wpdb->get_results("SHOW COLUMNS FROM {$this->tableName} LIKE 'post_id'");
        if (empty($columnExists)) {
            $wpdb->query("ALTER TABLE {$this->tableName} ADD COLUMN post_id bigint(20) unsigned DEFAULT 0 AFTER keyword_id");
            $wpdb->query("ALTER TABLE {$this->tableName} ADD KEY post_id_idx (post_id)");
        }
        
        // Check if created_at column exists
        $createdAtExists = $wpdb->get_results("SHOW COLUMNS FROM {$this->tableName} LIKE 'created_at'");
        if (empty($createdAtExists)) {
            $wpdb->query("ALTER TABLE {$this->tableName} ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
        }
        
        // Check if last_provider and last_error columns exist
        $lastProviderExists = $wpdb->get_results("SHOW COLUMNS FROM {$this->keywordsTable} LIKE 'last_provider'");
        if (empty($lastProviderExists)) {
            $wpdb->query("ALTER TABLE {$this->keywordsTable} ADD COLUMN last_provider varchar(50) DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$this->keywordsTable} ADD COLUMN last_error text DEFAULT NULL");
        }
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Rank Tracker', 'ai-seo-client'),
            __('Rank Tracker', 'ai-seo-client'),
            'manage_options',
            'ai-seo-ranks',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/ranks/keywords', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetKeywords'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);

        register_rest_route('sseo-ai/v1', '/ranks/add', [
            'methods' => 'POST',
            'callback' => [$this, 'restAddKeyword'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'url' => ['type' => 'string'],
                'country' => ['type' => 'string'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/ranks/delete', [
            'methods' => 'POST',
            'callback' => [$this, 'restDeleteKeyword'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
            'args' => [
                'id' => ['type' => 'integer', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/ranks/history/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetHistory'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);

        register_rest_route('sseo-ai/v1', '/ranks/check-now', [
            'methods' => 'POST',
            'callback' => [$this, 'restCheckNow'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);

        register_rest_route('sseo-ai/v1', '/cannibalization/report', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCannibalizationReport'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);

        register_rest_route('sseo-ai/v1', '/cannibalization/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'restRunCannibalizationScan'],
            'permission_callback' => function () { return current_user_can('manage_options'); },
        ]);
    }

    /**
     * GET: cached cannibalization report (runs a scan when none exists).
     */
    public function restGetCannibalizationReport(\WP_REST_Request $request): array
    {
        $report = get_transient('sseo_ai_cannibal_report');
        if (!is_array($report)) {
            $report = $this->buildCannibalizationReport();
            set_transient('sseo_ai_cannibal_report', $report, 6 * HOUR_IN_SECONDS);
        }
        return $report;
    }

    /**
     * POST: force a fresh cannibalization scan.
     */
    public function restRunCannibalizationScan(\WP_REST_Request $request): array
    {
        $report = $this->buildCannibalizationReport();
        set_transient('sseo_ai_cannibal_report', $report, 6 * HOUR_IN_SECONDS);
        return $report;
    }

    /**
     * Build the site-wide cannibalization report.
     *
     * Two signals:
     *  1. Focus-keyphrase collisions — multiple posts/pages carrying the same
     *     _sseo_ai_focus_keyphrase, independent of tracked keywords.
     *  2. Tracked-keyword conflicts — an active tracked keyword matching more
     *     than one post (focus meta or title), or matching posts other than
     *     its intended target.
     */
    private function buildCannibalizationReport(): array
    {
        global $wpdb;

        // Fixed whitelist — safe to inline after escaping.
        $statusList = "'" . implode("','", array_map('esc_sql', ['publish', 'draft', 'future', 'pending'])) . "'";

        // 1. All posts with a focus keyphrase, grouped by normalized phrase.
        $focusRows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value AS phrase, p.post_title, p.post_status, p.post_type
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sseo_ai_focus_keyphrase'
               AND pm.meta_value != ''
               AND p.post_status IN ({$statusList})
               AND p.post_type IN ('post','page')",
            ARRAY_A
        ) ?: [];

        $focusGroups = [];
        foreach ($focusRows as $row) {
            $key = mb_strtolower(trim((string)$row['phrase']));
            if ($key === '') {
                continue;
            }
            $focusGroups[$key][] = $row;
        }

        $groups = [];

        foreach ($focusGroups as $phrase => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $groups[] = [
                'keyword'    => $rows[0]['phrase'],
                'severity'   => 'high',
                'reason'     => 'same_focus_keyphrase',
                'posts'      => array_map([$this, 'formatCannibalPost'], $rows),
                'tracked'    => false,
            ];
        }

        // 2. Tracked keywords: match posts via focus meta + title search.
        $tracked = $wpdb->get_results(
            "SELECT id, keyword, url, post_id FROM {$this->keywordsTable} WHERE active = 1",
            ARRAY_A
        ) ?: [];

        if ($tracked) {
            $titles = $wpdb->get_results(
                "SELECT ID, post_title, post_status, post_type FROM {$wpdb->posts}
                 WHERE post_status IN ({$statusList}) AND post_type IN ('post','page')
                 ORDER BY post_date DESC LIMIT 2000",
                ARRAY_A
            ) ?: [];

            // focus phrase → post rows lookup built above
            foreach ($tracked as $kw) {
                $norm = mb_strtolower(trim($kw['keyword']));
                $matches = [];

                foreach ($focusGroups[$norm] ?? [] as $row) {
                    $row['match_type'] = 'focus';
                    $matches[(int)$row['post_id']] = $row;
                }
                foreach ($titles as $post) {
                    if ($norm !== '' && mb_stripos($post['post_title'], $kw['keyword']) !== false) {
                        $post['post_id'] = $post['ID'];
                        $post['match_type'] = 'title';
                        $matches[(int)$post['ID']] = $post + ['phrase' => null];
                    }
                }

                $targetPostId = (int)($kw['post_id'] ?? 0);
                $posts = [];
                foreach ($matches as $postId => $row) {
                    $formatted = $this->formatCannibalPost($row);
                    $formatted['match_type'] = $row['match_type'] ?? 'focus';
                    $formatted['is_target'] = $targetPostId > 0 && $postId === $targetPostId;
                    $posts[] = $formatted;
                }

                if (count($posts) > 1) {
                    // High when the target collides or 2+ posts claim the phrase.
                    $focusCount = count($focusGroups[$norm] ?? []);
                    $severity = ($targetPostId === 0 || $focusCount > 1) ? 'high' : 'medium';
                    $groups[] = [
                        'keyword'  => $kw['keyword'],
                        'severity' => $severity,
                        'reason'   => 'tracked_keyword_multi_match',
                        'posts'    => array_values($posts),
                        'tracked'  => true,
                    ];
                } elseif (count($posts) === 0) {
                    $groups[] = [
                        'keyword'  => $kw['keyword'],
                        'severity' => 'info',
                        'reason'   => 'tracked_keyword_no_match',
                        'posts'    => [],
                        'tracked'  => true,
                    ];
                }
            }
        }

        // Sort: high → medium → info, then by post count desc.
        $order = ['high' => 0, 'medium' => 1, 'info' => 2];
        usort($groups, function ($a, $b) use ($order) {
            $sa = $order[$a['severity']] ?? 3;
            $sb = $order[$b['severity']] ?? 3;
            return $sa <=> $sb ?: count($b['posts']) <=> count($a['posts']);
        });

        $summary = ['high' => 0, 'medium' => 0, 'info' => 0];
        foreach ($groups as $g) {
            $summary[$g['severity']] = ($summary[$g['severity']] ?? 0) + 1;
        }

        return [
            'success'     => true,
            'scanned_at'  => current_time('mysql'),
            'summary'     => $summary,
            'groups'      => $groups,
        ];
    }

    private function formatCannibalPost(array $row): array
    {
        $id = (int)($row['post_id'] ?? $row['ID'] ?? 0);
        return [
            'id'         => $id,
            'title'      => $row['post_title'] ?? '',
            'status'     => $row['post_status'] ?? '',
            'type'       => $row['post_type'] ?? 'post',
            'edit_link'  => $id ? (string)get_edit_post_link($id, '') : '',
            'permalink'  => $id ? (string)get_permalink($id) : '',
            'is_target'  => false,
            'match_type' => $row['match_type'] ?? 'focus',
        ];
    }

    /**
     * Get all tracked keywords with current positions
     */
    public function restGetKeywords(\WP_REST_Request $request): array
    {
        global $wpdb;
        $keywords = $wpdb->get_results(
            "SELECT * FROM {$this->keywordsTable} WHERE active = 1 ORDER BY keyword ASC",
            ARRAY_A
        );

        return ['keywords' => $keywords ?: []];
    }

    /**
     * Add a keyword to track
     */
    public function restAddKeyword(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;

        $keyword = sanitize_text_field($request->get_param('keyword'));
        $url = esc_url_raw($request->get_param('url') ?? '');
        $country = sanitize_text_field($request->get_param('country') ?? 'nl');

        if (empty($keyword)) {
            return new \WP_Error('empty_keyword', __('Keyword is required', 'ai-seo-client'));
        }

        // Check duplicate
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->keywordsTable} WHERE keyword = %s AND active = 1",
            $keyword
        ));
        if ($exists) {
            return new \WP_Error('duplicate', __('Keyword is already being tracked', 'ai-seo-client'));
        }

        // Find matching post
        $postId = 0;
        if ($url) {
            $postId = url_to_postid($url);
        }

        $wpdb->insert($this->keywordsTable, [
            'keyword' => $keyword,
            'url' => $url ?: home_url('/'),
            'post_id' => $postId,
            'country' => $country,
            'language' => substr($country, 0, 2),
            'created_at' => current_time('mysql'),
        ]);

        return ['success' => true, 'id' => $wpdb->insert_id];
    }

    /**
     * Delete (deactivate) a tracked keyword
     */
    public function restDeleteKeyword(\WP_REST_Request $request): array
    {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $wpdb->update($this->keywordsTable, ['active' => 0], ['id' => $id]);

        return ['success' => true];
    }

    /**
     * Get rank history for a keyword (last 30 days)
     */
    public function restGetHistory(\WP_REST_Request $request): array
    {
        global $wpdb;
        $keywordId = (int) $request->get_param('id');

        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT position, url, checked_at FROM {$this->tableName} 
             WHERE keyword_id = %d 
             ORDER BY checked_at DESC 
             LIMIT 90",
            $keywordId
        ), ARRAY_A);

        return ['history' => array_reverse($history ?: [])];
    }

    /**
     * Manual check — check all keywords now
     */
    public function restCheckNow(\WP_REST_Request $request): array|\WP_Error
    {
        $result = $this->runDailyCheck();
        return ['success' => true, 'checked' => $result];
    }

    /**
     * Run daily rank check for all active keywords
     */
    public function runDailyCheck(): int
    {
        global $wpdb;

        $keywords = $wpdb->get_results(
            "SELECT * FROM {$this->keywordsTable} WHERE active = 1",
            ARRAY_A
        );

        if (empty($keywords)) {
            return 0;
        }

        $checked = 0;
        $today = current_time('Y-m-d');

        foreach ($keywords as $kw) {
            // Skip if already checked today
            $alreadyChecked = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->tableName} WHERE keyword_id = %d AND checked_at = %s",
                $kw['id'],
                $today
            ));
            if ($alreadyChecked) {
                continue;
            }

            $checkResult = $this->checkKeywordPosition($kw['keyword'], $kw['url'], $kw['country']);
            $now = current_time('mysql');

            if ($checkResult !== null) {
                $position = (int) ($checkResult['position'] ?? 0);

                // Insert history record
                $wpdb->replace($this->tableName, [
                    'keyword_id' => $kw['id'],
                    'post_id' => $kw['post_id'] ?? 0,
                    'position' => $position,
                    'url' => $kw['url'],
                    'checked_at' => $today,
                ]);

                // Update keyword record
                $updateData = [
                    'previous_position' => $kw['current_position'],
                    'current_position' => $position,
                    'last_checked' => $now,
                    'last_provider' => $checkResult['provider'] ?? null,
                    'last_error' => $checkResult['error'] ?? null,
                ];
                if ($position > 0 && ($kw['best_position'] == 0 || $position < $kw['best_position'])) {
                    $updateData['best_position'] = $position;
                }

                $wpdb->update($this->keywordsTable, $updateData, ['id' => $kw['id']]);
                $checked++;
            } else {
                $wpdb->update($this->keywordsTable, [
                    'last_error' => __('No response from dashboard', 'ai-seo-client'),
                    'last_checked' => $now,
                ], ['id' => $kw['id']]);
            }
        }

        return $checked;
    }

    /**
     * Check a single keyword position via SaaS Dashboard SERP proxy.
     * Returns null on connection failure, or an array with position, provider, checked_at, error.
     */
    private function checkKeywordPosition(string $keyword, string $targetUrl, string $country): ?array
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if (empty($licenseKey) || empty($tenantKey) || empty($dashboardUrl)) {
            return null;
        }

        $response = wp_remote_post(
            rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/serp/rank-check',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ],
                'body' => json_encode([
                    'keyword' => $keyword,
                    'target_url' => $targetUrl,
                    'country' => $country,
                ]),
                'timeout' => 60,
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            return [
                'position' => 0,
                'provider' => null,
                'checked_at' => current_time('mysql'),
                'error' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $now = current_time('mysql');

        if ($statusCode !== 200 || empty($body['success']) || !isset($body['position'])) {
            $message = $body['message'] ?? __('Invalid response from SERP service', 'ai-seo-client');
            return [
                'position' => 0,
                'provider' => $body['provider'] ?? null,
                'checked_at' => $now,
                'error' => $message,
            ];
        }

        return [
            'position' => (int) $body['position'],
            'provider' => $body['provider'] ?? null,
            'checked_at' => $body['checked_at'] ?? $now,
            'error' => null,
        ];
    }

    /**
     * Render the Rank Tracker admin page
     */
    public function renderPage(): void
    {
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: 0; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-header p { margin: 10px 0 0 0; opacity: 0.8; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Keyword Rank Tracker', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Track your keyword positions in Google search results. Positions are checked daily.', 'ai-seo-client'); ?></p>
            </div>

            <div class="sseo-ai-content">
                <div style="max-width:1100px;">

                <!-- Tabs -->
                <div style="margin-bottom:20px;">
                    <button type="button" class="button rank-tab active" data-tab="rank-tracker-panel" style="border-radius:6px 6px 0 0;border-bottom:none;"><?php esc_html_e('Rank Tracker', 'ai-seo-client'); ?></button>
                    <?php if ($this->localSerp): ?>
                        <button type="button" class="button rank-tab" data-tab="rank-local-panel" style="border-radius:6px 6px 0 0;border-bottom:none;"><?php esc_html_e('Local SERP', 'ai-seo-client'); ?></button>
                    <?php endif; ?>
                    <button type="button" class="button rank-tab" data-tab="rank-cannibal-panel" style="border-radius:6px 6px 0 0;border-bottom:none;"><?php esc_html_e('Cannibalization', 'ai-seo-client'); ?></button>
                </div>

                <div id="rank-tracker-panel" class="rank-panel" style="display:block;">
                <!-- Add Keyword Form -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Add Keyword to Track', 'ai-seo-client'); ?></h2>
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                        <div>
                            <label><strong><?php esc_html_e('Keyword', 'ai-seo-client'); ?></strong></label><br>
                            <input type="text" id="rank-keyword" placeholder="<?php esc_attr_e('e.g. best seo plugin', 'ai-seo-client'); ?>" style="width:250px;">
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Target URL', 'ai-seo-client'); ?></strong></label><br>
                            <input type="url" id="rank-url" placeholder="<?php echo esc_attr(home_url('/')); ?>" style="width:300px;">
                        </div>
                        <div>
                            <label><strong><?php esc_html_e('Country', 'ai-seo-client'); ?></strong></label><br>
                            <select id="rank-country">
                                <option value="nl">🇳🇱 NL</option>
                                <option value="be">🇧🇪 BE</option>
                                <option value="us">🇺🇸 US</option>
                                <option value="gb">🇬🇧 GB</option>
                                <option value="de">🇩🇪 DE</option>
                                <option value="fr">🇫🇷 FR</option>
                                <option value="es">🇪🇸 ES</option>
                            </select>
                        </div>
                        <button type="button" class="button button-primary" id="rank-add"><?php esc_html_e('Add Keyword', 'ai-seo-client'); ?></button>
                        <button type="button" class="button" id="rank-check-all"><?php esc_html_e('Check All Now', 'ai-seo-client'); ?></button>
                        <span class="spinner" id="rank-spinner" style="float:none;"></span>
                    </div>
                </div>

                <!-- Keywords Table -->
                <div class="sseo-ai-dashboard-card">
                    <table class="wp-list-table widefat fixed striped" id="rank-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                                <th style="width:80px;"><?php esc_html_e('Position', 'ai-seo-client'); ?></th>
                                <th style="width:80px;"><?php esc_html_e('Change', 'ai-seo-client'); ?></th>
                                <th style="width:80px;"><?php esc_html_e('Best', 'ai-seo-client'); ?></th>
                                <th style="width:60px;"><?php esc_html_e('Country', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Target URL', 'ai-seo-client'); ?></th>
                                <th style="width:140px;"><?php esc_html_e('Last Checked', 'ai-seo-client'); ?></th>
                                <th style="width:80px;"><?php esc_html_e('Provider', 'ai-seo-client'); ?></th>
                                <th style="width:130px;"><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="rank-table-body">
                            <tr><td colspan="9" style="text-align:center;color:#999;"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- History Chart (shown when clicking a keyword) -->
                <div id="rank-history-panel" class="sseo-ai-dashboard-card" style="display:none;">
                    <h2 id="rank-history-title"></h2>
                    <div id="rank-history-chart" style="height:250px; position:relative;"></div>
                </div>
                </div>

                <?php if ($this->localSerp): ?>
                    <div id="rank-local-panel" class="rank-panel" style="display:none;">
                        <?php $this->localSerp->renderPanel(); ?>
                    </div>
                <?php endif; ?>

                <div id="rank-cannibal-panel" class="rank-panel" style="display:none;">
                    <div class="sseo-ai-dashboard-card">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                            <h2 style="margin:0;"><?php esc_html_e('Cannibalization Dashboard', 'ai-seo-client'); ?></h2>
                            <div>
                                <span class="spinner" id="cannibal-spinner" style="float:none;"></span>
                                <button type="button" class="button button-primary" id="cannibal-rescan"><?php esc_html_e('Rescan', 'ai-seo-client'); ?></button>
                            </div>
                        </div>
                        <p style="color:#6b7280;">
                            <?php esc_html_e('Detects posts and pages competing for the same keyword — a shared focus keyphrase, or multiple titles matching a tracked keyword. Merge, differentiate or redirect to fix.', 'ai-seo-client'); ?>
                        </p>
                        <div id="cannibal-summary" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;"></div>
                        <div id="cannibal-scanned-at" style="font-size:12px;color:#9ca3af;margin-bottom:16px;"></div>
                        <div id="cannibal-groups"></div>
                    </div>
                </div>

            </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function loadKeywords() {
                wp.apiFetch({ path: 'sseo-ai/v1/ranks/keywords' }).then(function(res) {
                    var tbody = $('#rank-table-body');
                    tbody.empty();

                    var keywords = (res && res.keywords) ? res.keywords : [];

                    if (!keywords.length) {
                        tbody.append('<tr><td colspan="9" style="text-align:center;color:#999;"><?php echo esc_js(__('No keywords tracked yet. Add one above!', 'ai-seo-client')); ?></td></tr>');
                        return;
                    }

                    var now = new Date();
                    keywords.forEach(function(kw) {
                        var pos = kw.current_position || '—';
                        var prev = kw.previous_position || 0;
                        var curr = kw.current_position || 0;
                        var changeHtml = '—';

                        if (prev > 0 && curr > 0) {
                            var diff = prev - curr;
                            if (diff > 0) {
                                changeHtml = '<span style="color:#00a32a;font-weight:bold;">▲ ' + diff + '</span>';
                            } else if (diff < 0) {
                                changeHtml = '<span style="color:#d63638;font-weight:bold;">▼ ' + Math.abs(diff) + '</span>';
                            } else {
                                changeHtml = '<span style="color:#999;">= 0</span>';
                            }
                        }

                        var posColor = curr > 0 && curr <= 3 ? '#00a32a' : (curr <= 10 ? '#2271b1' : (curr <= 20 ? '#dba617' : '#d63638'));
                        if (curr === 0) posColor = '#999';

                        var lastCheckedText = kw.last_checked || '<?php echo esc_js(__('Never', 'ai-seo-client')); ?>';
                        var lastCheckedStyle = '';
                        if (kw.last_checked) {
                            var checkedDate = new Date(kw.last_checked.replace(' ', 'T'));
                            if (!isNaN(checkedDate) && (now - checkedDate) > (48 * 60 * 60 * 1000)) {
                                lastCheckedText += ' (stale)';
                                lastCheckedStyle = 'color:#d63638;';
                            }
                        }

                        var providerText = kw.last_provider ? kw.last_provider : '—';
                        var errorAttr = kw.last_error ? ' title="' + $('<span>').text(kw.last_error).html().replace(/"/g, '&quot;') + '"' : '';

                        tbody.append(
                            '<tr' + errorAttr + '>' +
                            '<td><strong>' + $('<span>').text(kw.keyword).html() + '</strong></td>' +
                            '<td><span style="font-size:18px;font-weight:bold;color:' + posColor + ';">' + (curr || '—') + '</span></td>' +
                            '<td>' + changeHtml + '</td>' +
                            '<td>' + (kw.best_position || '—') + '</td>' +
                            '<td>' + (kw.country || '').toUpperCase() + '</td>' +
                            '<td style="font-size:12px;">' + $('<span>').text(kw.url || '').html() + '</td>' +
                            '<td style="font-size:12px;' + lastCheckedStyle + '">' + lastCheckedText + '</td>' +
                            '<td style="font-size:12px;">' + $('<span>').text(providerText).html() + '</td>' +
                            '<td>' +
                                '<button class="button button-small rank-show-history" data-id="' + kw.id + '" data-keyword="' + $('<span>').text(kw.keyword).html() + '"><?php echo esc_js(__('History', 'ai-seo-client')); ?></button> ' +
                                '<button class="button button-small rank-delete" data-id="' + kw.id + '" style="color:#d63638;">✕</button>' +
                            '</td>' +
                            '</tr>'
                        );
                    });
                }).catch(function(error) {
                    var tbody = $('#rank-table-body');
                    tbody.empty();
                    tbody.append('<tr><td colspan="9" style="text-align:center;color:#d63638;"><?php echo esc_js(__('Failed to load keywords. Please refresh the page.', 'ai-seo-client')); ?></td></tr>');
                    console.error('Rank tracker loadKeywords error:', error);
                });
            }

            loadKeywords();

            // Add keyword
            $('#rank-add').on('click', function() {
                var keyword = $('#rank-keyword').val().trim();
                if (!keyword) return;

                var btn = $(this);
                btn.prop('disabled', true);

                wp.apiFetch({
                    path: 'sseo-ai/v1/ranks/add',
                    method: 'POST',
                    data: {
                        keyword: keyword,
                        url: $('#rank-url').val().trim() || '',
                        country: $('#rank-country').val()
                    }
                }).then(function() {
                    $('#rank-keyword').val('');
                    $('#rank-url').val('');
                    loadKeywords();
                    btn.prop('disabled', false);
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false);
                });
            });

            // Delete keyword
            $(document).on('click', '.rank-delete', function() {
                if (!confirm('<?php echo esc_js(__('Remove this keyword?', 'ai-seo-client')); ?>')) return;
                var id = $(this).data('id');
                wp.apiFetch({ path: 'sseo-ai/v1/ranks/delete', method: 'POST', data: { id: id } }).then(loadKeywords);
            });

            // Check all now
            $('#rank-check-all').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#rank-spinner').addClass('is-active');

                wp.apiFetch({ path: 'sseo-ai/v1/ranks/check-now', method: 'POST' }).then(function(res) {
                    loadKeywords();
                    btn.prop('disabled', false);
                    $('#rank-spinner').removeClass('is-active');
                    alert(res.checked + ' <?php echo esc_js(__('keywords checked', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false);
                    $('#rank-spinner').removeClass('is-active');
                });
            });

            // Show history
            $(document).on('click', '.rank-show-history', function() {
                var id = $(this).data('id');
                var keyword = $(this).data('keyword');
                $('#rank-history-title').text('<?php echo esc_js(__('Position History:', 'ai-seo-client')); ?> ' + keyword);

                wp.apiFetch({ path: 'sseo-ai/v1/ranks/history/' + id }).then(function(res) {
                    renderChart(res.history);
                    $('#rank-history-panel').show();
                });
            });

            // Simple text-based chart (no external library needed)
            function renderChart(history) {
                var container = $('#rank-history-chart');
                container.empty();

                if (!history.length) {
                    container.html('<p style="color:#999;text-align:center;"><?php echo esc_js(__('No history data yet', 'ai-seo-client')); ?></p>');
                    return;
                }

                var maxPos = Math.max.apply(null, history.map(function(h) { return h.position || 100; }));
                maxPos = Math.max(maxPos, 10);
                var chartHeight = 200;
                var barWidth = Math.max(6, Math.min(20, (container.width() - 40) / history.length - 2));

                var html = '<div style="display:flex;align-items:flex-end;height:' + chartHeight + 'px;gap:2px;padding:10px 0;border-bottom:1px solid #ddd;">';
                history.forEach(function(h) {
                    var pos = h.position || maxPos;
                    var height = Math.max(4, ((maxPos - pos + 1) / maxPos) * chartHeight);
                    var color = pos <= 3 ? '#00a32a' : (pos <= 10 ? '#2271b1' : (pos <= 20 ? '#dba617' : '#d63638'));
                    html += '<div title="' + h.checked_at + ': #' + pos + '" style="width:' + barWidth + 'px;height:' + height + 'px;background:' + color + ';border-radius:2px 2px 0 0;cursor:pointer;" data-pos="' + pos + '" data-date="' + h.checked_at + '"></div>';
                });
                html += '</div>';

                // Date labels
                html += '<div style="display:flex;gap:2px;font-size:10px;color:#999;margin-top:4px;">';
                var labelInterval = Math.max(1, Math.floor(history.length / 8));
                history.forEach(function(h, i) {
                    var label = (i % labelInterval === 0) ? h.checked_at.substr(5) : '';
                    html += '<div style="width:' + barWidth + 'px;text-align:center;white-space:nowrap;overflow:hidden;">' + label + '</div>';
                });
                html += '</div>';

                container.html(html);
            }

            // Tab switching
            $('.rank-tab').on('click', function() {
                var tab = $(this).data('tab');
                $('.rank-tab').removeClass('active').css({'background':'#f0f0f1','color':'#1d2327'});
                $(this).addClass('active').css({'background':'#fff','color':'#2271b1'});
                $('.rank-panel').hide();
                $('#' + tab).show();
            });
            $('.rank-tab.active').trigger('click');

            // Local SERP scan
            $('#local-scan').on('click', function() {
                var btn = $(this);
                var lat = $('#local-lat').val();
                var lng = $('#local-lng').val();

                if (!lat || !lng) {
                    alert('<?php echo esc_js(__('Set your business coordinates first in Settings → Local Business.', 'ai-seo-client')); ?>');
                    return;
                }

                btn.prop('disabled', true);
                $('#local-spinner').addClass('is-active');
                $('#local-results-table').hide();
                $('#local-result-summary').html('');

                wp.apiFetch({
                    path: 'sseo-ai/v1/local-serp/scan',
                    method: 'POST',
                    data: {
                        keyword: $('#local-keyword').val().trim(),
                        latitude: parseFloat(lat),
                        longitude: parseFloat(lng),
                        radius: parseInt($('#local-radius').val(), 10),
                        grid: parseInt($('#local-grid').val(), 10),
                        country: $('#rank-country').val() || 'nl',
                        language: $('#rank-country').val() || 'nl',
                        business_name: ''
                    }
                }).then(function(res) {
                    var tbody = $('#local-results-body');
                    tbody.empty();

                    var results = res.results || [];
                    var summary = '';
                    if (res.own_position) {
                        summary += '<?php echo esc_js(__('Your business position', 'ai-seo-client')); ?>: #' + res.own_position;
                    } else if (res.own_presence) {
                        summary += '<?php echo esc_js(__('Your business seen in', 'ai-seo-client')); ?> ' + res.own_presence + ' / ' + res.points_scanned + ' <?php echo esc_js(__('grid points', 'ai-seo-client')); ?>';
                    } else {
                        summary += '<?php echo esc_js(__('Not found in this scan.', 'ai-seo-client')); ?>';
                    }
                    if (res.provider) {
                        summary += ' <span style="color:#999;font-size:12px;">(<?php echo esc_js(__('provider', 'ai-seo-client')); ?>: ' + res.provider + ')</span>';
                    }
                    $('#local-result-summary').html(summary);

                    if (!results.length) {
                        tbody.append('<tr><td colspan="5" style="text-align:center;color:#999;"><?php echo esc_js(__('No local results found.', 'ai-seo-client')); ?></td></tr>');
                    }

                    results.forEach(function(item, index) {
                        var distance = '';
                        if (item.gps && typeof item.gps.lat !== 'undefined' && lat && lng) {
                            distance = calculateDistance(parseFloat(lat), parseFloat(lng), item.gps.lat, item.gps.lng).toFixed(2) + ' km';
                        }
                        var rating = (item.rating ? item.rating + ' (' + (item.reviews || 0) + ')' : '—');
                        var title = item.title ? $('<span>').text(item.title).html() : '—';
                        var address = item.address ? $('<span>').text(item.address).html() : '';
                        var row = '<tr>' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td><strong>' + title + '</strong></td>' +
                            '<td style="font-size:12px;">' + address + '</td>' +
                            '<td>' + rating + '</td>' +
                            '<td>' + distance + '</td>' +
                        '</tr>';
                        tbody.append(row);
                    });

                    $('#local-results-table').show();
                    btn.prop('disabled', false);
                    $('#local-spinner').removeClass('is-active');
                }).catch(function(err) {
                    alert(err.message || '<?php echo esc_js(__('Local scan failed.', 'ai-seo-client')); ?>');
                    btn.prop('disabled', false);
                    $('#local-spinner').removeClass('is-active');
                });
            });

            function calculateDistance(lat1, lng1, lat2, lng2) {
                var R = 6371;
                var dLat = (lat2 - lat1) * Math.PI / 180;
                var dLng = (lng2 - lng1) * Math.PI / 180;
                var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                        Math.sin(dLng/2) * Math.sin(dLng/2);
                var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }

            // ===== Cannibalization tab =====
            var cannibalLoaded = false;

            function escHtml(s) {
                return $('<span>').text(s == null ? '' : String(s)).html();
            }

            var severityMeta = {
                high:   { label: '<?php echo esc_js(__('High', 'ai-seo-client')); ?>',   color: '#b91c1c', bg: '#fee2e2' },
                medium: { label: '<?php echo esc_js(__('Medium', 'ai-seo-client')); ?>', color: '#92400e', bg: '#fef3c7' },
                info:   { label: '<?php echo esc_js(__('Info', 'ai-seo-client')); ?>',   color: '#1e40af', bg: '#dbeafe' }
            };

            var reasonLabels = {
                same_focus_keyphrase:        '<?php echo esc_js(__('Same focus keyphrase on multiple posts', 'ai-seo-client')); ?>',
                tracked_keyword_multi_match: '<?php echo esc_js(__('Multiple posts match this tracked keyword', 'ai-seo-client')); ?>',
                tracked_keyword_no_match:    '<?php echo esc_js(__('No post targets this tracked keyword', 'ai-seo-client')); ?>'
            };

            var adviceMap = {
                same_focus_keyphrase:        '<?php echo esc_js(__('Keep one primary post for this phrase: merge content or 301-redirect the others, or give each post a unique focus keyphrase.', 'ai-seo-client')); ?>',
                tracked_keyword_multi_match: '<?php echo esc_js(__('Decide which post should rank for this keyword. Strengthen internal links to it and differentiate the other posts\' titles and focus.', 'ai-seo-client')); ?>',
                tracked_keyword_no_match:    '<?php echo esc_js(__('Create or assign a post targeting this keyword — currently nothing is optimized for it.', 'ai-seo-client')); ?>'
            };

            function renderCannibalReport(report) {
                var summary = report.summary || {};
                var chips = '';
                ['high', 'medium', 'info'].forEach(function (sev) {
                    var m = severityMeta[sev];
                    chips += '<span style="background:' + m.bg + ';color:' + m.color + ';padding:4px 14px;border-radius:14px;font-weight:600;font-size:13px;">' +
                        (summary[sev] || 0) + '× ' + m.label + '</span>';
                });
                $('#cannibal-summary').html(chips);
                $('#cannibal-scanned-at').text('<?php echo esc_js(__('Last scan:', 'ai-seo-client')); ?> ' + (report.scanned_at || '—'));

                var groups = report.groups || [];
                var container = $('#cannibal-groups');
                container.empty();

                if (!groups.length) {
                    container.html('<p style="color:#059669;font-weight:600;"><?php echo esc_js(__('No cannibalization found — every keyword has a single clear target.', 'ai-seo-client')); ?></p>');
                    return;
                }

                groups.forEach(function (g) {
                    var m = severityMeta[g.severity] || severityMeta.info;
                    var html = '<div style="border:1px solid #e5e7eb;border-left:4px solid ' + m.color + ';border-radius:8px;padding:16px 20px;margin-bottom:14px;background:#fff;">' +
                        '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">' +
                            '<span style="background:' + m.bg + ';color:' + m.color + ';padding:2px 10px;border-radius:12px;font-size:12px;font-weight:700;">' + m.label + '</span>' +
                            '<strong style="font-size:15px;">"' + escHtml(g.keyword) + '"</strong>' +
                            '<span style="color:#6b7280;font-size:12px;">' + escHtml(reasonLabels[g.reason] || g.reason) + '</span>' +
                        '</div>';

                    if (g.posts && g.posts.length) {
                        html += '<table class="widefat" style="margin-top:10px;border:none;box-shadow:none;">' +
                            '<thead><tr>' +
                                '<th style="padding:6px 8px;"><?php echo esc_js(__('Post', 'ai-seo-client')); ?></th>' +
                                '<th style="width:70px;padding:6px 8px;"><?php echo esc_js(__('Status', 'ai-seo-client')); ?></th>' +
                                '<th style="width:90px;padding:6px 8px;"><?php echo esc_js(__('Match', 'ai-seo-client')); ?></th>' +
                                '<th style="width:110px;padding:6px 8px;"></th>' +
                            '</tr></thead><tbody>';
                        g.posts.forEach(function (p) {
                            html += '<tr>' +
                                '<td style="padding:6px 8px;">' +
                                    escHtml(p.title || ('#' + p.id)) +
                                    (p.is_target ? ' <span style="background:#d1fae5;color:#065f46;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;"><?php echo esc_js(__('target', 'ai-seo-client')); ?></span>' : '') +
                                '</td>' +
                                '<td style="padding:6px 8px;color:#6b7280;font-size:12px;">' + escHtml(p.status) + '</td>' +
                                '<td style="padding:6px 8px;color:#6b7280;font-size:12px;">' + escHtml(p.match_type || '—') + '</td>' +
                                '<td style="padding:6px 8px;">' +
                                    (p.edit_link ? '<a href="' + escHtml(p.edit_link) + '" class="button button-small"><?php echo esc_js(__('Edit', 'ai-seo-client')); ?></a> ' : '') +
                                    (p.permalink ? '<a href="' + escHtml(p.permalink) + '" target="_blank" class="button button-small"><?php echo esc_js(__('View', 'ai-seo-client')); ?></a>' : '') +
                                '</td>' +
                            '</tr>';
                        });
                        html += '</tbody></table>';
                    }

                    if (adviceMap[g.reason]) {
                        html += '<p style="margin:10px 0 0;color:#374151;font-size:13px;"><strong><?php echo esc_js(__('Advice:', 'ai-seo-client')); ?></strong> ' + escHtml(adviceMap[g.reason]) + '</p>';
                    }

                    html += '</div>';
                    container.append(html);
                });
            }

            function loadCannibalReport(force) {
                $('#cannibal-spinner').addClass('is-active');
                $('#cannibal-rescan').prop('disabled', true);
                wp.apiFetch({
                    path: force ? 'sseo-ai/v1/cannibalization/scan' : 'sseo-ai/v1/cannibalization/report',
                    method: force ? 'POST' : 'GET'
                }).then(function (res) {
                    renderCannibalReport(res);
                    $('#cannibal-spinner').removeClass('is-active');
                    $('#cannibal-rescan').prop('disabled', false);
                }).catch(function (err) {
                    $('#cannibal-groups').html('<p style="color:#b91c1c;">' + escHtml(err.message || '<?php echo esc_js(__('Scan failed.', 'ai-seo-client')); ?>') + '</p>');
                    $('#cannibal-spinner').removeClass('is-active');
                    $('#cannibal-rescan').prop('disabled', false);
                });
            }

            $('#cannibal-rescan').on('click', function () {
                loadCannibalReport(true);
            });

            // Lazy-load the report the first time the tab is opened.
            $('.rank-tab').on('click', function () {
                if ($(this).data('tab') === 'rank-cannibal-panel' && !cannibalLoaded) {
                    cannibalLoaded = true;
                    loadCannibalReport(false);
                }
            });
        });
        </script>
        <?php
    }
}

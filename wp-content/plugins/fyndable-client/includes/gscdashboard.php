<?php

namespace SSEOAIClient;

/**
 * Google Search Console Dashboard
 * 
 * Displays GSC performance data directly in the WordPress admin:
 * impressions, clicks, CTR, average position, top queries, top pages.
 * Uses the GscClient for API access.
 * 
 * Comparable to RankMath's Search Console integration.
 */
class GscDashboard
{
    private Settings $settings;
    private GscClient $gscClient;

    public function __construct(Settings $settings, GscClient $gscClient)
    {
        $this->settings = $settings;
        $this->gscClient = $gscClient;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        // Menu registration moved to Client class
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Search Console', 'ai-seo-client'),
            __('Search Console', 'ai-seo-client'),
            'manage_options',
            'ai-seo-gsc',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/gsc/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOverview'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 28],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/gsc/queries', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTopQueries'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 28],
                'limit' => ['type' => 'integer', 'default' => 50],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/gsc/pages', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTopPages'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 28],
                'limit' => ['type' => 'integer', 'default' => 50],
            ],
        ]);
    }

    /**
     * Get performance overview: totals + daily breakdown.
     */
    public function getOverview(int $days = 28): array
    {
        $cacheKey = 'aiseo_gsc_overview_' . $days;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $data = $this->gscClient->query([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['date'],
            'rowLimit' => $days + 1,
        ]);

        if (is_wp_error($data)) {
            return [
                'connected' => false,
                'error' => $data->get_error_message(),
            ];
        }

        $rows = $data['rows'] ?? [];
        $totalClicks = 0;
        $totalImpressions = 0;
        $totalCtr = 0;
        $totalPosition = 0;
        $daily = [];

        foreach ($rows as $row) {
            $date = $row['keys'][0] ?? '';
            $clicks = (int) ($row['clicks'] ?? 0);
            $impressions = (int) ($row['impressions'] ?? 0);
            $ctr = (float) ($row['ctr'] ?? 0);
            $position = (float) ($row['position'] ?? 0);

            $totalClicks += $clicks;
            $totalImpressions += $impressions;
            $totalCtr += $ctr;
            $totalPosition += $position;

            $daily[] = [
                'date' => $date,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => round($ctr * 100, 2),
                'position' => round($position, 1),
            ];
        }

        $rowCount = count($rows) ?: 1;
        $result = [
            'connected' => true,
            'period' => ['start' => $startDate, 'end' => $endDate, 'days' => $days],
            'totals' => [
                'clicks' => $totalClicks,
                'impressions' => $totalImpressions,
                'avg_ctr' => round(($totalCtr / $rowCount) * 100, 2),
                'avg_position' => round($totalPosition / $rowCount, 1),
            ],
            'daily' => $daily,
        ];

        set_transient($cacheKey, $result, 4 * HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * Get top queries by clicks.
     */
    public function getTopQueries(int $days = 28, int $limit = 50): array
    {
        $cacheKey = 'aiseo_gsc_queries_' . $days . '_' . $limit;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->gscClient->query([
            'startDate' => date('Y-m-d', strtotime("-{$days} days")),
            'endDate' => date('Y-m-d'),
            'dimensions' => ['query'],
            'rowLimit' => $limit,
        ]);

        if (is_wp_error($data)) {
            return ['queries' => [], 'error' => $data->get_error_message()];
        }

        $queries = [];
        foreach ($data['rows'] ?? [] as $row) {
            $queries[] = [
                'query' => $row['keys'][0] ?? '',
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr' => round(($row['ctr'] ?? 0) * 100, 2),
                'position' => round($row['position'] ?? 0, 1),
            ];
        }

        $result = ['queries' => $queries];
        set_transient($cacheKey, $result, 4 * HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * Get top pages by clicks.
     */
    public function getTopPages(int $days = 28, int $limit = 50): array
    {
        $cacheKey = 'aiseo_gsc_pages_' . $days . '_' . $limit;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $data = $this->gscClient->query([
            'startDate' => date('Y-m-d', strtotime("-{$days} days")),
            'endDate' => date('Y-m-d'),
            'dimensions' => ['page'],
            'rowLimit' => $limit,
        ]);

        if (is_wp_error($data)) {
            return ['pages' => [], 'error' => $data->get_error_message()];
        }

        $pages = [];
        foreach ($data['rows'] ?? [] as $row) {
            $url = $row['keys'][0] ?? '';
            $pages[] = [
                'url' => $url,
                'path' => parse_url($url, PHP_URL_PATH) ?: '/',
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr' => round(($row['ctr'] ?? 0) * 100, 2),
                'position' => round($row['position'] ?? 0, 1),
            ];
        }

        $result = ['pages' => $pages];
        set_transient($cacheKey, $result, 4 * HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * Render the GSC Dashboard admin page.
     */
    public function renderPage(): void
    {
        ?>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <!-- Page header -->
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Google Search Console', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('Google Search Console', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Performance data from Google Search Console. Connect via Settings to enable.', 'ai-seo-client'); ?></p>
                </div>
            </div>

            <!-- Controls card -->
            <div class="fyn-card" style="margin-bottom:20px;padding:16px 20px;">
                <div style="display:flex;gap:10px;align-items:center;">
                    <select id="gsc-period" class="fyn-input" style="max-width:180px;padding:9px 10px;">
                        <option value="7"><?php esc_html_e('Last 7 days', 'ai-seo-client'); ?></option>
                        <option value="28" selected><?php esc_html_e('Last 28 days', 'ai-seo-client'); ?></option>
                        <option value="90"><?php esc_html_e('Last 3 months', 'ai-seo-client'); ?></option>
                    </select>
                    <button type="button" class="fyn-btn fyn-btn-primary" id="gsc-refresh">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Load Data', 'ai-seo-client'); ?>
                    </button>
                    <span class="spinner" id="gsc-spinner" style="float:none;"></span>
                </div>
            </div>

            <div id="gsc-error" class="notice notice-error" style="display:none;"><p></p></div>

            <!-- Totals -->
            <div id="gsc-totals" class="fyn-stats-grid" style="margin-bottom:20px;">
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value" id="gsc-clicks">-</div>
                    <div class="fyn-stat-label"><?php esc_html_e('Clicks', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value" id="gsc-impressions">-</div>
                    <div class="fyn-stat-label"><?php esc_html_e('Impressions', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value" id="gsc-ctr">-</div>
                    <div class="fyn-stat-label"><?php esc_html_e('Avg CTR', 'ai-seo-client'); ?></div>
                </div>
                <div class="fyn-stat-tile">
                    <div class="fyn-stat-value" id="gsc-position">-</div>
                    <div class="fyn-stat-label"><?php esc_html_e('Avg Position', 'ai-seo-client'); ?></div>
                </div>
            </div>

            <!-- Queries & Pages -->
            <div class="fyn-grid-2">
                <div class="fyn-card">
                    <div class="fyn-card-header">
                        <span class="dashicons dashicons-search"></span>
                        <h3 class="fyn-card-title"><?php esc_html_e('Top Queries', 'ai-seo-client'); ?></h3>
                    </div>
                    <table class="fyn-table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Query', 'ai-seo-client'); ?></th>
                                <th style="width:60px"><?php esc_html_e('Clicks', 'ai-seo-client'); ?></th>
                                <th style="width:80px"><?php esc_html_e('Impr.', 'ai-seo-client'); ?></th>
                                <th style="width:55px"><?php esc_html_e('CTR', 'ai-seo-client'); ?></th>
                                <th style="width:45px"><?php esc_html_e('Pos', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="gsc-queries-body">
                            <tr><td colspan="5" style="color:var(--text-m);"><?php esc_html_e('Click "Load Data" to fetch.', 'ai-seo-client'); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="fyn-card">
                    <div class="fyn-card-header">
                        <span class="dashicons dashicons-admin-page"></span>
                        <h3 class="fyn-card-title"><?php esc_html_e('Top Pages', 'ai-seo-client'); ?></h3>
                    </div>
                    <table class="fyn-table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Page', 'ai-seo-client'); ?></th>
                                <th style="width:60px"><?php esc_html_e('Clicks', 'ai-seo-client'); ?></th>
                                <th style="width:80px"><?php esc_html_e('Impr.', 'ai-seo-client'); ?></th>
                                <th style="width:55px"><?php esc_html_e('CTR', 'ai-seo-client'); ?></th>
                                <th style="width:45px"><?php esc_html_e('Pos', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="gsc-pages-body">
                            <tr><td colspan="5" style="color:var(--text-m);"><?php esc_html_e('Click "Load Data" to fetch.', 'ai-seo-client'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function numFmt(n) {
                return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : n;
            }

            $('#gsc-refresh').on('click', function() {
                var days = parseInt($('#gsc-period').val());
                var btn = $(this);
                var spinner = $('#gsc-spinner');
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                $('#gsc-error').hide();

                // Fetch overview + queries + pages in parallel
                Promise.all([
                    wp.apiFetch({path: 'sseo-ai/v1/gsc/overview?days=' + days}),
                    wp.apiFetch({path: 'sseo-ai/v1/gsc/queries?days=' + days + '&limit=30'}),
                    wp.apiFetch({path: 'sseo-ai/v1/gsc/pages?days=' + days + '&limit=30'})
                ]).then(function(results) {
                    var overview = results[0];
                    var queries = results[1];
                    var pages = results[2];

                    if (!overview.connected) {
                        $('#gsc-error p').text(overview.error || '<?php echo esc_js(__('Not connected to Google Search Console.', 'ai-seo-client')); ?>');
                        $('#gsc-error').show();
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                        return;
                    }

                    var t = overview.totals;
                    $('#gsc-clicks').text(numFmt(t.clicks));
                    $('#gsc-impressions').text(numFmt(t.impressions));
                    $('#gsc-ctr').text(t.avg_ctr + '%');
                    $('#gsc-position').text(t.avg_position);

                    // Queries table
                    var qHtml = '';
                    (queries.queries || []).forEach(function(q) {
                        qHtml += '<tr><td>' + q.query + '</td><td>' + q.clicks + '</td><td>' + numFmt(q.impressions) + '</td><td>' + q.ctr + '%</td><td>' + q.position + '</td></tr>';
                    });
                    $('#gsc-queries-body').html(qHtml || '<tr><td colspan="5"><?php echo esc_js(__('No data available.', 'ai-seo-client')); ?></td></tr>');

                    // Pages table
                    var pHtml = '';
                    (pages.pages || []).forEach(function(p) {
                        pHtml += '<tr><td title="' + p.url + '">' + (p.path || '/') + '</td><td>' + p.clicks + '</td><td>' + numFmt(p.impressions) + '</td><td>' + p.ctr + '%</td><td>' + p.position + '</td></tr>';
                    });
                    $('#gsc-pages-body').html(pHtml || '<tr><td colspan="5"><?php echo esc_js(__('No data available.', 'ai-seo-client')); ?></td></tr>');

                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                }).catch(function(err) {
                    $('#gsc-error p').text(err.message || '<?php echo esc_js(__('Error loading data.', 'ai-seo-client')); ?>');
                    $('#gsc-error').show();
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                });
            });
        });
        </script>
        <?php
    }

    // REST handlers
    public function restGetOverview(\WP_REST_Request $request): array
    {
        return $this->getOverview((int) $request->get_param('days'));
    }

    public function restGetTopQueries(\WP_REST_Request $request): array
    {
        return $this->getTopQueries(
            (int) $request->get_param('days'),
            (int) $request->get_param('limit')
        );
    }

    public function restGetTopPages(\WP_REST_Request $request): array
    {
        return $this->getTopPages(
            (int) $request->get_param('days'),
            (int) $request->get_param('limit')
        );
    }
}

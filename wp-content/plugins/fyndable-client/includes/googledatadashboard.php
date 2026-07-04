<?php

namespace SSEOAIClient;

/**
 * Google Data Dashboard
 * 
 * Unified dashboard combining data from:
 * - Google Search Console (impressions, clicks, CTR, position)
 * - Google Analytics 4 (sessions, users, page views, conversions)
 * - Google Ads (clicks, impressions, cost, conversions)
 * 
 * All services use a single Google OAuth login.
 */
class GoogleDataDashboard
{
    private Settings $settings;
    private GscClient $gscClient;
    private GA4Client $ga4Client;
    private GoogleAdsClient $adsClient;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->gscClient = new GscClient($settings);
        $this->ga4Client = new GA4Client($settings);
        $this->adsClient = new GoogleAdsClient($settings);
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Google Data', 'ai-seo-client'),
            __('Google Data', 'ai-seo-client'),
            'manage_options',
            'ai-seo-google-data',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/google/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOverview'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ga4/daily', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetGA4Daily'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ga4/top-pages', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetGA4TopPages'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
                'limit' => ['type' => 'integer', 'default' => 10],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ga4/sources', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetGA4Sources'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ads/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAdsOverview'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ads/daily', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAdsDaily'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ads/keywords', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAdsKeywords'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30],
                'limit' => ['type' => 'integer', 'default' => 20],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/google/ga4/properties', [
            'methods' => 'GET',
            'callback' => [$this, 'restListGA4Properties'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/google/ads/customers', [
            'methods' => 'GET',
            'callback' => [$this, 'restListAdsCustomers'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * REST: Get unified overview (GSC + GA4 + Ads)
     */
    public function restGetOverview(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $cacheKey = "sseo_google_overview_{$days}";
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $overview = [
            'gsc' => null,
            'ga4' => null,
            'ads' => null,
            'errors' => [],
        ];

        // GSC overview
        if ($this->gscClient->isConnected()) {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            $gscResult = $this->gscClient->query([
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['date'],
                'rowLimit' => 1000,
            ]);
            if (is_wp_error($gscResult)) {
                $overview['errors'][] = 'GSC: ' . $gscResult->get_error_message();
            } else {
                $totalClicks = 0;
                $totalImpressions = 0;
                $totalCtr = 0;
                $totalPosition = 0;
                $rowCount = 0;
                if (!empty($gscResult['rows'])) {
                    foreach ($gscResult['rows'] as $row) {
                        $totalClicks += $row['clicks'] ?? 0;
                        $totalImpressions += $row['impressions'] ?? 0;
                        $totalCtr += $row['ctr'] ?? 0;
                        $totalPosition += $row['position'] ?? 0;
                        $rowCount++;
                    }
                }
                $overview['gsc'] = [
                    'clicks' => $totalClicks,
                    'impressions' => $totalImpressions,
                    'ctr' => $rowCount > 0 ? round(($totalCtr / $rowCount) * 100, 2) : 0,
                    'avg_position' => $rowCount > 0 ? round($totalPosition / $rowCount, 1) : 0,
                ];
            }
        }

        // GA4 overview
        if ($this->ga4Client->isConnected()) {
            $ga4Result = $this->ga4Client->getOverview($days);
            if (is_wp_error($ga4Result)) {
                $overview['errors'][] = 'GA4: ' . $ga4Result->get_error_message();
            } else {
                $overview['ga4'] = $ga4Result;
            }
        }

        // Google Ads overview
        if ($this->adsClient->isConnected()) {
            $adsResult = $this->adsClient->getCampaignOverview($days);
            if (is_wp_error($adsResult)) {
                $overview['errors'][] = 'Ads: ' . $adsResult->get_error_message();
            } else {
                $overview['ads'] = [
                    'total_clicks' => $adsResult['total_clicks'] ?? 0,
                    'total_impressions' => $adsResult['total_impressions'] ?? 0,
                    'total_cost' => $adsResult['total_cost'] ?? 0,
                    'total_conversions' => $adsResult['total_conversions'] ?? 0,
                    'avg_ctr' => $adsResult['avg_ctr'] ?? 0,
                    'cost_per_conversion' => $adsResult['cost_per_conversion'] ?? 0,
                ];
            }
        }

        set_transient($cacheKey, $overview, 30 * MINUTE_IN_SECONDS);
        return $overview;
    }

    public function restGetGA4Daily(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $result = $this->ga4Client->getDailyTraffic($days);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'daily' => []];
        }
        return $result;
    }

    public function restGetGA4TopPages(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $limit = (int)$request->get_param('limit');
        $result = $this->ga4Client->getTopPages($days, $limit);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'pages' => []];
        }
        return $result;
    }

    public function restGetGA4Sources(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $result = $this->ga4Client->getTrafficSources($days);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'sources' => []];
        }
        return $result;
    }

    public function restGetAdsOverview(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $result = $this->adsClient->getCampaignOverview($days);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message()];
        }
        return $result;
    }

    public function restGetAdsDaily(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $result = $this->adsClient->getDailyPerformance($days);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'daily' => []];
        }
        return $result;
    }

    public function restGetAdsKeywords(\WP_REST_Request $request): array
    {
        $days = (int)$request->get_param('days');
        $limit = (int)$request->get_param('limit');
        $result = $this->adsClient->getKeywordPerformance($days, $limit);
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'keywords' => []];
        }
        return $result;
    }

    public function restListGA4Properties(\WP_REST_Request $request): array
    {
        $result = $this->ga4Client->listProperties();
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'properties' => []];
        }
        return $result;
    }

    public function restListAdsCustomers(\WP_REST_Request $request): array
    {
        $result = $this->adsClient->listCustomers();
        if (is_wp_error($result)) {
            return ['error' => $result->get_error_message(), 'customers' => []];
        }
        return $result;
    }

    /**
     * Render the Google Data Dashboard page
     */
    public function renderPage(): void
    {
        $gscConnected = $this->gscClient->isConnected();
        $ga4Connected = $this->ga4Client->isConnected();
        $adsConnected = $this->adsClient->isConnected();
        $anyConnected = $gscConnected || $ga4Connected || $adsConnected;
        $ga4PropertyId = get_option('sseo_ai_ga4_property_id', '');
        $adsCustomerId = get_option('sseo_ai_google_ads_customer_id', '');

        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-header p { margin: 10px 0 0 0; opacity: 0.8; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .google-service-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; }
            .google-service-badge.connected { background: #d1fae5; color: #065f46; }
            .google-service-badge.disconnected { background: #fee2e2; color: #991b1b; }
            .google-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
            .google-stat-card { background: #f8fafc; border-radius: 8px; padding: 20px; text-align: center; border: 1px solid #e2e8f0; }
            .google-stat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
            .google-stat-label { font-size: 12px; color: #64748b; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
            .google-tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
            .google-tab { padding: 10px 20px; cursor: pointer; background: none; border: none; font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 14px; }
            .google-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
            .google-panel { display: none; }
            .google-panel.active { display: block; }
            .google-loading { text-align: center; padding: 40px; color: #64748b; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Google Data Dashboard', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Unified data from Google Search Console, Analytics 4, and Google Ads.', 'ai-seo-client'); ?></p>
            </div>

            <div class="sseo-ai-content">
                <div style="max-width: 1200px;">

                <!-- Connection Status -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Google Services Status', 'ai-seo-client'); ?></h2>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
                        <span class="google-service-badge <?php echo $gscConnected ? 'connected' : 'disconnected'; ?>">
                            <?php echo $gscConnected ? '✓' : '✗'; ?> Search Console
                        </span>
                        <span class="google-service-badge <?php echo $ga4Connected ? 'connected' : 'disconnected'; ?>">
                            <?php echo $ga4Connected ? '✓' : '✗'; ?> Analytics 4
                        </span>
                        <span class="google-service-badge <?php echo $adsConnected ? 'connected' : 'disconnected'; ?>">
                            <?php echo $adsConnected ? '✓' : '✗'; ?> Google Ads
                        </span>
                    </div>

                    <?php if (!$anyConnected): ?>
                        <p><?php esc_html_e('Connect your Google account via AI SEO → Integrations to see data here.', 'ai-seo-client'); ?></p>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" class="button button-primary">
                            <?php esc_html_e('Go to Integrations', 'ai-seo-client'); ?>
                        </a>
                    <?php else: ?>
                        <!-- GA4 Property ID config -->
                        <div style="margin-bottom: 15px; padding: 15px; background: #f0f6fc; border-radius: 8px;">
                            <label><strong><?php esc_html_e('GA4 Property ID', 'ai-seo-client'); ?></strong></label><br>
                            <input type="text" id="ga4-property-id" value="<?php echo esc_attr($ga4PropertyId); ?>" placeholder="123456789" style="width: 200px; margin-top: 5px;">
                            <button type="button" class="button button-small" id="ga4-list-properties" style="margin-left: 10px;">
                                <?php esc_html_e('List Available Properties', 'ai-seo-client'); ?>
                            </button>
                            <select id="ga4-property-select" style="display:none; margin-left: 10px; max-width: 300px;"></select>
                            <p class="description"><?php esc_html_e('Numeric property ID from GA4 (found in Admin → Property Settings).', 'ai-seo-client'); ?></p>
                        </div>

                        <!-- Google Ads config -->
                        <div style="padding: 15px; background: #fef3c7; border-radius: 8px;">
                            <label><strong><?php esc_html_e('Google Ads Customer ID', 'ai-seo-client'); ?></strong></label><br>
                            <input type="text" id="ads-customer-id" value="<?php echo esc_attr($adsCustomerId); ?>" placeholder="123-456-7890" style="width: 200px; margin-top: 5px;">
                            <button type="button" class="button button-small" id="ads-list-customers" style="margin-left: 10px;">
                                <?php esc_html_e('List Available Accounts', 'ai-seo-client'); ?>
                            </button>
                            <select id="ads-customer-select" style="display:none; margin-left: 10px; max-width: 200px;"></select>
                        </div>
                        <button type="button" class="button button-primary" id="google-save-config" style="margin-top: 15px;">
                            <?php esc_html_e('Save Configuration', 'ai-seo-client'); ?>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($anyConnected): ?>
                <!-- Tabs -->
                <div class="sseo-ai-dashboard-card">
                    <div class="google-tabs">
                        <button class="google-tab active" data-tab="overview"><?php esc_html_e('Overview', 'ai-seo-client'); ?></button>
                        <button class="google-tab" data-tab="ga4"><?php esc_html_e('Analytics 4', 'ai-seo-client'); ?></button>
                        <button class="google-tab" data-tab="ads"><?php esc_html_e('Google Ads', 'ai-seo-client'); ?></button>
                    </div>

                    <!-- Overview Panel -->
                    <div class="google-panel active" id="panel-overview">
                        <div class="google-stat-grid" id="overview-stats">
                            <div class="google-loading"><?php esc_html_e('Loading overview data...', 'ai-seo-client'); ?></div>
                        </div>
                    </div>

                    <!-- GA4 Panel -->
                    <div class="google-panel" id="panel-ga4">
                        <?php if ($ga4Connected): ?>
                            <h3><?php esc_html_e('Daily Traffic', 'ai-seo-client'); ?></h3>
                            <div id="ga4-daily-chart" class="google-loading"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></div>

                            <h3 style="margin-top: 30px;"><?php esc_html_e('Top Pages', 'ai-seo-client'); ?></h3>
                            <div id="ga4-top-pages" class="google-loading"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></div>

                            <h3 style="margin-top: 30px;"><?php esc_html_e('Traffic Sources', 'ai-seo-client'); ?></h3>
                            <div id="ga4-sources" class="google-loading"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></div>
                        <?php else: ?>
                            <p style="color: #64748b; text-align: center; padding: 40px;">
                                <?php esc_html_e('Google Analytics 4 is not configured. Set your GA4 Property ID above.', 'ai-seo-client'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Google Ads Panel -->
                    <div class="google-panel" id="panel-ads">
                        <?php if ($adsConnected): ?>
                            <h3><?php esc_html_e('Campaign Performance', 'ai-seo-client'); ?></h3>
                            <div id="ads-overview" class="google-loading"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></div>

                            <h3 style="margin-top: 30px;"><?php esc_html_e('Daily Performance', 'ai-seo-client'); ?></h3>
                            <div id="ads-daily" class="google-loading"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></div>

                            <h3 style="margin-top: 30px;"><?php esc_html_e('Keyword Performance', 'ai-seo-client'); ?></h3>
                            <div id="ads-keywords" class="google-loading"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></div>
                        <?php else: ?>
                            <p style="color: #64748b; text-align: center; padding: 40px;">
                                <?php esc_html_e('Google Ads is not configured. Set your Customer ID above.', 'ai-seo-client'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.google-tab').on('click', function() {
                var tab = $(this).data('tab');
                $('.google-tab').removeClass('active');
                $('.google-panel').removeClass('active');
                $(this).addClass('active');
                $('#panel-' + tab).addClass('active');

                if (tab === 'ga4' && !$('#ga4-daily-chart').data('loaded')) {
                    loadGA4Data();
                }
                if (tab === 'ads' && !$('#ads-overview').data('loaded')) {
                    loadAdsData();
                }
            });

            // Load overview
            if ($('#overview-stats').length) {
                loadOverview();
            }

            function loadOverview() {
                wp.apiFetch({ path: 'sseo-ai/v1/google/overview?days=30' }).then(function(data) {
                    var html = '';
                    var stats = [];

                    if (data.gsc) {
                        stats.push({ label: 'GSC Clicks', value: formatNum(data.gsc.clicks) });
                        stats.push({ label: 'GSC Impressions', value: formatNum(data.gsc.impressions) });
                        stats.push({ label: 'GSC CTR', value: data.gsc.ctr + '%' });
                        stats.push({ label: 'GSC Avg Position', value: data.gsc.avg_position });
                    }

                    if (data.ga4) {
                        stats.push({ label: 'GA4 Sessions', value: formatNum(data.ga4.sessions) });
                        stats.push({ label: 'GA4 Users', value: formatNum(data.ga4.users) });
                        stats.push({ label: 'GA4 Page Views', value: formatNum(data.ga4.page_views) });
                        stats.push({ label: 'GA4 Conversions', value: formatNum(data.ga4.conversions) });
                    }

                    if (data.ads) {
                        stats.push({ label: 'Ads Clicks', value: formatNum(data.ads.total_clicks) });
                        stats.push({ label: 'Ads Impressions', value: formatNum(data.ads.total_impressions) });
                        stats.push({ label: 'Ads Cost', value: '$' + data.ads.total_cost });
                        stats.push({ label: 'Ads Conversions', value: formatNum(data.ads.total_conversions) });
                    }

                    if (!stats.length) {
                        html = '<div style="grid-column: 1/-1; text-align: center; color: #64748b; padding: 40px;">No data available. Check your connections.</div>';
                    } else {
                        stats.forEach(function(s) {
                            html += '<div class="google-stat-card"><div class="google-stat-value">' + s.value + '</div><div class="google-stat-label">' + s.label + '</div></div>';
                        });
                    }

                    if (data.errors && data.errors.length) {
                        html += '<div style="grid-column: 1/-1; padding: 15px; background: #fee2e2; border-radius: 8px; margin-top: 10px;"><strong>Errors:</strong><ul style="margin: 5px 0;"><li>' + data.errors.join('</li><li>') + '</li></ul></div>';
                    }

                    $('#overview-stats').html(html);
                }).catch(function(err) {
                    $('#overview-stats').html('<div style="text-align:center;color:#d63638;">' + (err.message || 'Failed to load') + '</div>');
                });
            }

            function loadGA4Data() {
                // Daily traffic
                wp.apiFetch({ path: 'sseo-ai/v1/google/ga4/daily?days=30' }).then(function(data) {
                    $('#ga4-daily-chart').data('loaded', true);
                    if (data.error) {
                        $('#ga4-daily-chart').html('<p style="color:#d63638;">' + data.error + '</p>');
                        return;
                    }
                    if (!data.daily || !data.daily.length) {
                        $('#ga4-daily-chart').html('<p style="color:#64748b;">No data available</p>');
                        return;
                    }
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Sessions</th><th>Users</th><th>Page Views</th></tr></thead><tbody>';
                    data.daily.forEach(function(d) {
                        html += '<tr><td>' + d.date + '</td><td>' + formatNum(d.sessions) + '</td><td>' + formatNum(d.users) + '</td><td>' + formatNum(d.page_views) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#ga4-daily-chart').html(html);
                });

                // Top pages
                wp.apiFetch({ path: 'sseo-ai/v1/google/ga4/top-pages?days=30&limit=10' }).then(function(data) {
                    if (data.error) { $('#ga4-top-pages').html('<p style="color:#d63638;">' + data.error + '</p>'); return; }
                    if (!data.pages || !data.pages.length) { $('#ga4-top-pages').html('<p style="color:#64748b;">No data</p>'); return; }
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Page</th><th>Title</th><th>Views</th><th>Sessions</th><th>Avg Duration</th></tr></thead><tbody>';
                    data.pages.forEach(function(p) {
                        html += '<tr><td><code>' + $('<span>').text(p.path).html() + '</code></td><td>' + $('<span>').text(p.title).html() + '</td><td>' + formatNum(p.page_views) + '</td><td>' + formatNum(p.sessions) + '</td><td>' + p.avg_duration + 's</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#ga4-top-pages').html(html);
                });

                // Traffic sources
                wp.apiFetch({ path: 'sseo-ai/v1/google/ga4/sources?days=30' }).then(function(data) {
                    if (data.error) { $('#ga4-sources').html('<p style="color:#d63638;">' + data.error + '</p>'); return; }
                    if (!data.sources || !data.sources.length) { $('#ga4-sources').html('<p style="color:#64748b;">No data</p>'); return; }
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Channel</th><th>Sessions</th><th>Users</th></tr></thead><tbody>';
                    data.sources.forEach(function(s) {
                        html += '<tr><td><strong>' + $('<span>').text(s.channel).html() + '</strong></td><td>' + formatNum(s.sessions) + '</td><td>' + formatNum(s.users) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#ga4-sources').html(html);
                });
            }

            function loadAdsData() {
                // Campaign overview
                wp.apiFetch({ path: 'sseo-ai/v1/google/ads/overview?days=30' }).then(function(data) {
                    $('#ads-overview').data('loaded', true);
                    if (data.error) { $('#ads-overview').html('<p style="color:#d63638;">' + data.error + '</p>'); return; }
                    var html = '<div class="google-stat-grid" style="margin-bottom: 20px;">';
                    html += statCard('Total Clicks', formatNum(data.total_clicks));
                    html += statCard('Impressions', formatNum(data.total_impressions));
                    html += statCard('Total Cost', '$' + data.total_cost);
                    html += statCard('Conversions', formatNum(data.total_conversions));
                    html += statCard('Avg CTR', data.avg_ctr + '%');
                    html += statCard('Cost/Conv', '$' + data.cost_per_conversion);
                    html += '</div>';

                    if (data.campaigns && data.campaigns.length) {
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Campaign</th><th>Status</th><th>Clicks</th><th>Impr.</th><th>Cost</th><th>CTR</th><th>Conv.</th></tr></thead><tbody>';
                        data.campaigns.forEach(function(c) {
                            html += '<tr><td><strong>' + $('<span>').text(c.name).html() + '</strong></td><td>' + c.status + '</td><td>' + formatNum(c.clicks) + '</td><td>' + formatNum(c.impressions) + '</td><td>$' + c.cost + '</td><td>' + c.ctr + '%</td><td>' + c.conversions + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }
                    $('#ads-overview').html(html);
                });

                // Daily
                wp.apiFetch({ path: 'sseo-ai/v1/google/ads/daily?days=30' }).then(function(data) {
                    if (data.error) { $('#ads-daily').html('<p style="color:#d63638;">' + data.error + '</p>'); return; }
                    if (!data.daily || !data.daily.length) { $('#ads-daily').html('<p style="color:#64748b;">No data</p>'); return; }
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Date</th><th>Clicks</th><th>Impressions</th><th>Cost</th><th>Conversions</th></tr></thead><tbody>';
                    data.daily.forEach(function(d) {
                        html += '<tr><td>' + d.date + '</td><td>' + formatNum(d.clicks) + '</td><td>' + formatNum(d.impressions) + '</td><td>$' + d.cost + '</td><td>' + d.conversions + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#ads-daily').html(html);
                });

                // Keywords
                wp.apiFetch({ path: 'sseo-ai/v1/google/ads/keywords?days=30&limit=20' }).then(function(data) {
                    if (data.error) { $('#ads-keywords').html('<p style="color:#d63638;">' + data.error + '</p>'); return; }
                    if (!data.keywords || !data.keywords.length) { $('#ads-keywords').html('<p style="color:#64748b;">No data</p>'); return; }
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Keyword</th><th>Match Type</th><th>Clicks</th><th>Impr.</th><th>Cost</th><th>CTR</th><th>CPC</th></tr></thead><tbody>';
                    data.keywords.forEach(function(k) {
                        html += '<tr><td><strong>' + $('<span>').text(k.keyword).html() + '</strong></td><td>' + k.match_type + '</td><td>' + formatNum(k.clicks) + '</td><td>' + formatNum(k.impressions) + '</td><td>$' + k.cost + '</td><td>' + k.ctr + '%</td><td>$' + k.avg_cpc + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#ads-keywords').html(html);
                });
            }

            // Save config
            $('#google-save-config').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Saving...');

                jQuery.post(ajaxurl, {
                    action: 'sseo_ai_save_google_config',
                    ga4_property_id: $('#ga4-property-id').val(),
                    ads_customer_id: $('#ads-customer-id').val(),
                    nonce: '<?php echo wp_create_nonce('sseo_google_config'); ?>'
                }, function(response) {
                    btn.prop('disabled', false).text('Save Configuration');
                    if (response.success) {
                        alert('<?php esc_html_e('Configuration saved!', 'ai-seo-client'); ?>');
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Error saving');
                    }
                });
            });

            // List GA4 properties
            $('#ga4-list-properties').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Loading...');
                wp.apiFetch({ path: 'sseo-ai/v1/google/ga4/properties' }).then(function(data) {
                    btn.prop('disabled', false).text('<?php esc_html_e('List Available Properties', 'ai-seo-client'); ?>');
                    if (data.error) { alert(data.error); return; }
                    if (!data.properties || !data.properties.length) { alert('<?php esc_html_e('No properties found.', 'ai-seo-client'); ?>'); return; }
                    var select = $('#ga4-property-select');
                    select.empty().show();
                    data.properties.forEach(function(p) {
                        select.append('<option value="' + p.property_id + '">' + p.display_name + ' (' + p.property_id + ')</option>');
                    });
                    select.on('change', function() {
                        $('#ga4-property-id').val($(this).val());
                    });
                }).catch(function(err) {
                    btn.prop('disabled', false).text('<?php esc_html_e('List Available Properties', 'ai-seo-client'); ?>');
                    alert(err.message || 'Failed to list properties');
                });
            });

            // List Ads customers
            $('#ads-list-customers').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true).text('Loading...');
                wp.apiFetch({ path: 'sseo-ai/v1/google/ads/customers' }).then(function(data) {
                    btn.prop('disabled', false).text('<?php esc_html_e('List Available Accounts', 'ai-seo-client'); ?>');
                    if (data.error) { alert(data.error); return; }
                    if (!data.customers || !data.customers.length) { alert('<?php esc_html_e('No accounts found.', 'ai-seo-client'); ?>'); return; }
                    var select = $('#ads-customer-select');
                    select.empty().show();
                    data.customers.forEach(function(c) {
                        var formatted = c.substring(0, 3) + '-' + c.substring(3, 6) + '-' + c.substring(6);
                        select.append('<option value="' + formatted + '">' + formatted + '</option>');
                    });
                    select.on('change', function() {
                        $('#ads-customer-id').val($(this).val());
                    });
                }).catch(function(err) {
                    btn.prop('disabled', false).text('<?php esc_html_e('List Available Accounts', 'ai-seo-client'); ?>');
                    alert(err.message || 'Failed to list accounts');
                });
            });

            function formatNum(n) {
                if (!n) return '0';
                return n.toLocaleString();
            }

            function statCard(label, value) {
                return '<div class="google-stat-card"><div class="google-stat-value">' + value + '</div><div class="google-stat-label">' + label + '</div></div>';
            }
        });
        </script>
        <?php
    }
}

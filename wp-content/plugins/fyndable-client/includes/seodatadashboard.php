<?php

namespace SSEOAIClient;

/**
 * SEO Data Dashboard
 *
 * Combines SE Ranking and Ahrefs data into a unified dashboard.
 */
class SEODataDashboard
{
    private Settings $settings;
    private ?SERankingClient $seRanking = null;
    private ?AhrefsClient $ahrefs = null;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        $seKey = get_option('sseo_ai_seranking_api_key', '');
        if ($seKey) {
            $this->seRanking = new SERankingClient($seKey);
        }

        $ahKey = get_option('sseo_ai_ahrefs_api_key', '');
        if ($ahKey) {
            $this->ahrefs = new AhrefsClient($ahKey);
        }
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/seo-data/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOverview'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/seo-data/seranking', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSERanking'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/seo-data/ahrefs', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAhrefs'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function renderPage(): void
    {
        $seKey = get_option('sseo_ai_seranking_api_key', '');
        $ahKey = get_option('sseo_ai_ahrefs_api_key', '');
        $seConnected = !empty($seKey);
        $ahConnected = !empty($ahKey);
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .seo-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
            .seo-stat-card { background: #f8fafc; border-radius: 8px; padding: 20px; text-align: center; border: 1px solid #e2e8f0; }
            .seo-stat-value { font-size: 28px; font-weight: 700; color: #2563eb; }
            .seo-stat-label { font-size: 13px; color: #64748b; margin-top: 5px; }
            .seo-data-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
            .seo-data-tab { padding: 10px 20px; cursor: pointer; background: none; border: none; font-weight: 500; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; }
            .seo-data-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
            .seo-data-panel { display: none; }
            .seo-data-panel.active { display: block; }
            .connection-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .connection-badge.connected { background: #d1fae5; color: #065f46; }
            .connection-badge.disconnected { background: #fee2e2; color: #991b1b; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('SEO Data Dashboard', 'ai-seo-client'); ?></h1>
                <div style="margin-top: 10px; display: flex; gap: 10px;">
                    <span class="connection-badge <?php echo $seConnected ? 'connected' : 'disconnected'; ?>">
                        SE Ranking: <?php echo $seConnected ? 'Connected' : 'Not Connected'; ?>
                    </span>
                    <span class="connection-badge <?php echo $ahConnected ? 'connected' : 'disconnected'; ?>">
                        Ahrefs: <?php echo $ahConnected ? 'Connected' : 'Not Connected'; ?>
                    </span>
                </div>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <div class="seo-data-tabs">
                        <button type="button" class="seo-data-tab active" onclick="sseoSwitchTab(this, 'overview')"><?php esc_html_e('Overview', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'seranking')"><?php esc_html_e('SE Ranking', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'ahrefs')"><?php esc_html_e('Ahrefs', 'ai-seo-client'); ?></button>
                    </div>

                    <div id="panel-overview" class="seo-data-panel active">
                        <p><?php esc_html_e('Loading overview data...', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-seranking" class="seo-data-panel">
                        <p><?php esc_html_e('Loading SE Ranking data...', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-ahrefs" class="seo-data-panel">
                        <p><?php esc_html_e('Loading Ahrefs data...', 'ai-seo-client'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function sseoSwitchTab(tab, panelId) {
            document.querySelectorAll('.seo-data-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.seo-data-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('panel-' + panelId).classList.add('active');
        }

        function sseoRenderStats(containerId, data, mapping) {
            var container = document.getElementById(containerId);
            var html = '<div class="seo-stat-grid">';
            mapping.forEach(function(item) {
                var value = item.value || data[item.key] || '—';
                html += '<div class="seo-stat-card">';
                html += '<div class="seo-stat-value">' + value + '</div>';
                html += '<div class="seo-stat-label">' + item.label + '</div>';
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function sseoLoadOverview() {
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/overview' }).then(function(data) {
                var container = document.getElementById('panel-overview');
                if (data.error) {
                    container.innerHTML = '<p style="color:#d63638;">' + data.error + '</p>';
                    return;
                }
                var html = '';
                if (data.seranking) {
                    html += '<h3><?php echo esc_js(__('SE Ranking', 'ai-seo-client')); ?></h3>';
                    html += '<div class="seo-stat-grid">';
                    (data.seranking.stats || []).forEach(function(s) {
                        html += '<div class="seo-stat-card">';
                        html += '<div class="seo-stat-value">' + (s.value ?? '—') + '</div>';
                        html += '<div class="seo-stat-label">' + s.label + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }
                if (data.ahrefs) {
                    html += '<h3 style="margin-top:20px;"><?php echo esc_js(__('Ahrefs', 'ai-seo-client')); ?></h3>';
                    html += '<div class="seo-stat-grid">';
                    (data.ahrefs.stats || []).forEach(function(s) {
                        html += '<div class="seo-stat-card">';
                        html += '<div class="seo-stat-value">' + (s.value ?? '—') + '</div>';
                        html += '<div class="seo-stat-label">' + s.label + '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }
                container.innerHTML = html || '<p><?php echo esc_js(__('No data available. Configure API keys in Integrations.', 'ai-seo-client')); ?></p>';
            }).catch(function(err) {
                document.getElementById('panel-overview').innerHTML = '<p style="color:#d63638;">' + (err.message || 'Error') + '</p>';
            });
        }

        function sseoLoadSERanking() {
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/seranking' }).then(function(data) {
                var container = document.getElementById('panel-seranking');
                if (data.error) {
                    container.innerHTML = '<p style="color:#d63638;">' + data.error + '</p>';
                    return;
                }
                var html = '<h3><?php echo esc_js(__('Sites / Projects', 'ai-seo-client')); ?></h3>';
                if (data.sites && data.sites.length) {
                    html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Name</th><th>URL</th></tr></thead><tbody>';
                    data.sites.forEach(function(site) {
                        html += '<tr><td>' + (site.id ?? '—') + '</td><td>' + (site.name ?? '—') + '</td><td>' + (site.url ?? '—') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<p><?php echo esc_js(__('No sites found.', 'ai-seo-client')); ?></p>';
                }
                container.innerHTML = html;
            }).catch(function(err) {
                document.getElementById('panel-seranking').innerHTML = '<p style="color:#d63638;">' + (err.message || 'Error') + '</p>';
            });
        }

        function sseoLoadAhrefs() {
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/ahrefs' }).then(function(data) {
                var container = document.getElementById('panel-ahrefs');
                if (data.error) {
                    container.innerHTML = '<p style="color:#d63638;">' + data.error + '</p>';
                    return;
                }
                var html = '<h3><?php echo esc_js(__('Domain Overview', 'ai-seo-client')); ?></h3>';
                html += '<div class="seo-stat-grid">';
                (data.stats || []).forEach(function(s) {
                    html += '<div class="seo-stat-card">';
                    html += '<div class="seo-stat-value">' + (s.value ?? '—') + '</div>';
                    html += '<div class="seo-stat-label">' + s.label + '</div>';
                    html += '</div>';
                });
                html += '</div>';
                container.innerHTML = html;
            }).catch(function(err) {
                document.getElementById('panel-ahrefs').innerHTML = '<p style="color:#d63638;">' + (err.message || 'Error') + '</p>';
            });
        }

        // Load all panels on page load
        document.addEventListener('DOMContentLoaded', function() {
            sseoLoadOverview();
            sseoLoadSERanking();
            sseoLoadAhrefs();
        });
        </script>
        <?php
    }

    public function restGetOverview(): array
    {
        $result = [];
        $siteUrl = parse_url(home_url(), PHP_URL_HOST);

        if ($this->seRanking && $this->seRanking->isConfigured()) {
            $sites = $this->seRanking->getSites();
            if (!is_wp_error($sites) && is_array($sites)) {
                $result['seranking'] = [
                    'stats' => [
                        ['label' => __('Sites Tracked', 'ai-seo-client'), 'value' => count($sites)],
                    ],
                ];
            } else {
                $result['seranking'] = ['error' => is_wp_error($sites) ? $sites->get_error_message() : 'No data'];
            }
        }

        if ($this->ahrefs && $this->ahrefs->isConfigured()) {
            $dr = $this->ahrefs->getDomainRating($siteUrl);
            $traffic = $this->ahrefs->getOrganicTraffic($siteUrl);
            $bl = $this->ahrefs->getBacklinksSummary($siteUrl);

            $result['ahrefs'] = [
                'stats' => [
                    ['label' => __('Domain Rating', 'ai-seo-client'), 'value' => (!is_wp_error($dr) && isset($dr['domain_rating'])) ? $dr['domain_rating']['value'] : '—'],
                    ['label' => __('Backlinks', 'ai-seo-client'), 'value' => (!is_wp_error($bl) && isset($bl['metrics'])) ? number_format($bl['metrics']['live_refs'] ?? 0) : '—'],
                    ['label' => __('Referring Domains', 'ai-seo-client'), 'value' => (!is_wp_error($bl) && isset($bl['metrics'])) ? number_format($bl['metrics']['live_ref_domains'] ?? 0) : '—'],
                ],
            ];
        }

        if (empty($result)) {
            return ['error' => __('No integrations configured. Please add API keys in the Integrations page.', 'ai-seo-client')];
        }

        return $result;
    }

    public function restGetSERanking(): array
    {
        if (!$this->seRanking || !$this->seRanking->isConfigured()) {
            return ['error' => __('SE Ranking API key not configured.', 'ai-seo-client')];
        }

        $sites = $this->seRanking->getSites();
        if (is_wp_error($sites)) {
            return ['error' => $sites->get_error_message()];
        }

        return ['sites' => $sites];
    }

    public function restGetAhrefs(): array
    {
        if (!$this->ahrefs || !$this->ahrefs->isConfigured()) {
            return ['error' => __('Ahrefs API key not configured.', 'ai-seo-client')];
        }

        $siteUrl = parse_url(home_url(), PHP_URL_HOST);
        $dr = $this->ahrefs->getDomainRating($siteUrl);
        $traffic = $this->ahrefs->getOrganicTraffic($siteUrl);
        $bl = $this->ahrefs->getBacklinksSummary($siteUrl);

        $stats = [];
        if (!is_wp_error($dr) && isset($dr['domain_rating'])) {
            $stats[] = ['label' => __('Domain Rating', 'ai-seo-client'), 'value' => $dr['domain_rating']['value']];
        }
        if (!is_wp_error($traffic) && isset($traffic['metrics'])) {
            $stats[] = ['label' => __('Organic Traffic', 'ai-seo-client'), 'value' => number_format($traffic['metrics']['org_traffic'] ?? 0)];
        }
        if (!is_wp_error($bl) && isset($bl['metrics'])) {
            $stats[] = ['label' => __('Backlinks', 'ai-seo-client'), 'value' => number_format($bl['metrics']['live_refs'] ?? 0)];
            $stats[] = ['label' => __('Referring Domains', 'ai-seo-client'), 'value' => number_format($bl['metrics']['live_ref_domains'] ?? 0)];
        }

        return ['stats' => $stats];
    }
}

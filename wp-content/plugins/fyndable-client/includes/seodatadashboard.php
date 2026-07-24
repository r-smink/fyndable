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
    private ?SERankingDataClient $seData = null;
    private ?AhrefsClient $ahrefs = null;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        $seKey = get_option('sseo_ai_seranking_api_key', '');
        if ($seKey) {
            $this->seRanking = new SERankingClient($seKey);
            $this->seData = new SERankingDataClient($seKey);
        }

        $ahKey = get_option('sseo_ai_ahrefs_api_key', '');
        if ($ahKey) {
            $this->ahrefs = new AhrefsClient($ahKey);
        }
    }

    /**
     * Default SE Ranking regional database code based on site locale.
     */
    private function defaultSource(): string
    {
        $locale = strtolower((string) get_locale());
        $map = [
            'nl_nl' => 'nl', 'nl_be' => 'nl', 'de_de' => 'de', 'de_at' => 'de',
            'fr_fr' => 'fr', 'fr_be' => 'fr', 'es_es' => 'es', 'it_it' => 'it',
            'en_gb' => 'uk', 'en_au' => 'au', 'en_ca' => 'ca', 'pt_br' => 'br',
        ];
        return $map[$locale] ?? 'us';
    }

    /**
     * Resolve the target domain from a request, defaulting to the current site.
     */
    private function resolveDomain(\WP_REST_Request $request): string
    {
        $domain = sanitize_text_field((string) $request->get_param('domain'));
        if ($domain === '') {
            $domain = (string) parse_url(home_url(), PHP_URL_HOST);
        }
        // Strip scheme / path if a full URL was pasted.
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/.*$#', '', (string) $domain);
        return (string) $domain;
    }

    /**
     * Resolve the requested regional database code.
     */
    private function resolveSource(\WP_REST_Request $request): string
    {
        $source = sanitize_text_field((string) $request->get_param('source'));
        return $source !== '' ? $source : $this->defaultSource();
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

        // --- SE Ranking Data API (domain analysis, keyword research, SERP, competitors) ---
        register_rest_route('sseo-ai/v1', '/seo-data/domain', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetDomainAnalysis'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/seo-data/keywords', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetKeywordResearch'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/seo-data/competitors', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCompetitors'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/seo-data/serp', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSerp'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/seo-data/ai-search', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAiSearch'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    // ---------------------------------------------------------------------
    // SE Ranking Data API REST callbacks
    // ---------------------------------------------------------------------

    /**
     * Domain Analysis: overview + top keywords + top pages.
     */
    public function restGetDomainAnalysis(\WP_REST_Request $request): array
    {
        if (!$this->seData || !$this->seData->isConfigured()) {
            return ['error' => __('SE Ranking API key not configured.', 'ai-seo-client')];
        }

        $domain = $this->resolveDomain($request);
        $source = $this->resolveSource($request);

        $overview = $this->seData->getDomainOverview($domain, $source);
        $keywords = $this->seData->getDomainKeywords($domain, $source, ['limit' => 25]);
        $pages = $this->seData->getDomainPages($domain, $source, 'organic', 15);

        return [
            'domain' => $domain,
            'source' => $source,
            'overview' => is_wp_error($overview) ? ['error' => $overview->get_error_message()] : $overview,
            'keywords' => is_wp_error($keywords) ? ['error' => $keywords->get_error_message()] : $keywords,
            'pages' => is_wp_error($pages) ? ['error' => $pages->get_error_message()] : $pages,
        ];
    }

    /**
     * Keyword Research: metrics for the seed + similar / related / question keywords.
     */
    public function restGetKeywordResearch(\WP_REST_Request $request): array
    {
        if (!$this->seData || !$this->seData->isConfigured()) {
            return ['error' => __('SE Ranking API key not configured.', 'ai-seo-client')];
        }

        $keyword = sanitize_text_field((string) $request->get_param('keyword'));
        if ($keyword === '') {
            return ['error' => __('Please enter a keyword.', 'ai-seo-client')];
        }
        $source = $this->resolveSource($request);

        $metrics = $this->seData->getKeywordMetrics([$keyword], $source);
        $similar = $this->seData->getSimilarKeywords($keyword, $source, 25);
        $related = $this->seData->getRelatedKeywords($keyword, $source, 25);
        $questions = $this->seData->getQuestionKeywords($keyword, $source, 25);

        return [
            'keyword' => $keyword,
            'source' => $source,
            'metrics' => is_wp_error($metrics) ? ['error' => $metrics->get_error_message()] : $metrics,
            'similar' => is_wp_error($similar) ? ['error' => $similar->get_error_message()] : $similar,
            'related' => is_wp_error($related) ? ['error' => $related->get_error_message()] : $related,
            'questions' => is_wp_error($questions) ? ['error' => $questions->get_error_message()] : $questions,
        ];
    }

    /**
     * Competitors: competitor domains + optional keyword gap comparison.
     */
    public function restGetCompetitors(\WP_REST_Request $request): array
    {
        if (!$this->seData || !$this->seData->isConfigured()) {
            return ['error' => __('SE Ranking API key not configured.', 'ai-seo-client')];
        }

        $domain = $this->resolveDomain($request);
        $source = $this->resolveSource($request);
        $compareDomain = sanitize_text_field((string) $request->get_param('compare_domain'));

        $competitors = $this->seData->getDomainCompetitors($domain, $source, 'organic');

        $result = [
            'domain' => $domain,
            'source' => $source,
            'competitors' => is_wp_error($competitors) ? ['error' => $competitors->get_error_message()] : $competitors,
        ];

        if ($compareDomain !== '') {
            $gap = $this->seData->getKeywordComparison($domain, $compareDomain, $source, 'organic', 'missing', 50);
            $result['compare_domain'] = $compareDomain;
            $result['keyword_gap'] = is_wp_error($gap) ? ['error' => $gap->get_error_message()] : $gap;
        }

        return $result;
    }

    /**
     * SERP: task-based. Without a task_id it adds a task; with one it polls.
     */
    public function restGetSerp(\WP_REST_Request $request): array
    {
        if (!$this->seData || !$this->seData->isConfigured()) {
            return ['error' => __('SE Ranking API key not configured.', 'ai-seo-client')];
        }

        $source = $this->resolveSource($request);
        $taskId = sanitize_text_field((string) $request->get_param('task_id'));

        if ($taskId !== '') {
            $result = $this->seData->getSerpTask($taskId);
            if (is_wp_error($result)) {
                return ['error' => $result->get_error_message()];
            }
            return ['task' => $result];
        }

        $keyword = sanitize_text_field((string) $request->get_param('keyword'));
        if ($keyword === '') {
            return ['error' => __('Please enter a keyword.', 'ai-seo-client')];
        }

        $task = $this->seData->addSerpTask($keyword, $source);
        if (is_wp_error($task)) {
            return ['error' => $task->get_error_message()];
        }
        return ['task' => $task];
    }

    /**
     * AI Search: aggregated + per-engine overview, brand discovery, prompts.
     */
    public function restGetAiSearch(\WP_REST_Request $request): array
    {
        if (!$this->seData || !$this->seData->isConfigured()) {
            return ['error' => __('SE Ranking API key not configured.', 'ai-seo-client')];
        }

        $domain = $this->resolveDomain($request);
        $source = $this->resolveSource($request);
        $engine = sanitize_text_field((string) $request->get_param('engine'));
        if ($engine === '') {
            $engine = 'ai-overview';
        }

        $engines = ['ai-overview', 'chatgpt', 'perplexity', 'gemini', 'ai-mode'];

        $aggregated = $this->seData->getAiSearchOverviewAggregated($domain, $source);
        $brand = $this->seData->discoverBrand($domain, $source);
        $brandName = '';
        if (!is_wp_error($brand) && isset($brand['brand'])) {
            $brandName = (string) $brand['brand'];
        }

        $perEngine = [];
        foreach ($engines as $eng) {
            $perEngine[$eng] = $this->seData->getAiSearchOverviewByEngine($domain, $eng, $source, $brandName !== '' ? $brandName : null);
        }

        $prompts = $this->seData->getPromptsByTarget($domain, $source, $engine, 25);

        return [
            'domain' => $domain,
            'source' => $source,
            'brand' => $brandName,
            'aggregated' => is_wp_error($aggregated) ? ['error' => $aggregated->get_error_message()] : $aggregated,
            'per_engine' => array_map(function ($r) {
                return is_wp_error($r) ? ['error' => $r->get_error_message()] : $r;
            }, $perEngine),
            'prompts' => is_wp_error($prompts) ? ['error' => $prompts->get_error_message()] : $prompts,
        ];
    }

    public function renderPage(): void
    {
        $seKey = get_option('sseo_ai_seranking_api_key', '');
        $ahKey = get_option('sseo_ai_ahrefs_api_key', '');
        $seConnected = !empty($seKey);
        $ahConnected = !empty($ahKey);
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .seo-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
            .seo-stat-card { background: #f8fafc; border-radius: 8px; padding: 20px; text-align: center; border: 1px solid #e2e8f0; }
            .seo-stat-value { font-size: 28px; font-weight: 700; color: #379fd3; }
            .seo-stat-label { font-size: 13px; color: #64748b; margin-top: 5px; }
            .seo-data-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
            .seo-data-tab { padding: 10px 20px; cursor: pointer; background: none; border: none; font-weight: 500; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; }
            .seo-data-tab.active { color: #379fd3; border-bottom-color: #379fd3; }
            .seo-data-panel { display: none; }
            .seo-data-panel.active { display: block; }
            .connection-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
            .connection-badge.connected { background: #d1fae5; color: #065f46; }
            .connection-badge.disconnected { background: #fee2e2; color: #991b1b; }
            .seo-search-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 20px; }
            .seo-search-field { display: flex; flex-direction: column; gap: 4px; }
            .seo-search-field label { font-size: 12px; font-weight: 600; color: #475569; }
            .seo-search-field input, .seo-search-field select { padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; min-width: 220px; }
            .seo-search-bar button { padding: 9px 20px; background: #379fd3; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px; }
            .seo-search-bar button:hover { background: #2a7ba8; }
            .seo-data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .seo-data-table th { background: #f1f5f9; padding: 10px; text-align: left; font-size: 12px; color: #475569; }
            .seo-data-table td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
            .seo-data-table tr:hover { background: #f8fafc; }
            .seo-data-section { margin-top: 25px; }
            .seo-data-section h3 { color: #111827; font-size: 16px; margin-bottom: 8px; }
            .seo-diff-pos { color: #16a34a; font-weight: 600; }
            .seo-diff-neg { color: #dc2626; font-weight: 600; }
            .seo-kw-tag { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 4px; padding: 2px 6px; font-size: 11px; margin: 1px; }
            .seo-loading { color: #64748b; font-style: italic; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('SEO Data Dashboard', 'ai-seo-client'); ?></h1>
                <div style="margin-top: 10px; display: flex; gap: 10px;">
                    <?php if ($seConnected): ?>
                    <span class="connection-badge connected">SE Ranking: Connected</span>
                    <?php else: ?>
                    <a class="connection-badge disconnected" href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" style="text-decoration: none;">SE Ranking: Not Connected</a>
                    <?php endif; ?>
                    <?php if ($ahConnected): ?>
                    <span class="connection-badge connected">Ahrefs: Connected</span>
                    <?php else: ?>
                    <a class="connection-badge disconnected" href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" style="text-decoration: none;">Ahrefs: Not Connected</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sseo-ai-content">
                <?php DashboardSorter::begin('ai-seo-google-data'); ?>
                <?php $defaultSource = $this->defaultSource(); ?>
                <div class="sseo-ai-dashboard-card">
                    <div class="seo-search-bar">
                        <div class="seo-search-field">
                            <label for="seo-input-domain"><?php esc_html_e('Domain', 'ai-seo-client'); ?></label>
                            <input type="text" id="seo-input-domain" placeholder="<?php echo esc_attr(parse_url(home_url(), PHP_URL_HOST)); ?>">
                        </div>
                        <div class="seo-search-field">
                            <label for="seo-input-keyword"><?php esc_html_e('Keyword', 'ai-seo-client'); ?></label>
                            <input type="text" id="seo-input-keyword" placeholder="<?php esc_attr_e('e.g. seo software', 'ai-seo-client'); ?>">
                        </div>
                        <div class="seo-search-field">
                            <label for="seo-input-source"><?php esc_html_e('Database', 'ai-seo-client'); ?></label>
                            <select id="seo-input-source">
                                <?php
                                $databases = [
                                    'us' => 'United States', 'uk' => 'United Kingdom', 'nl' => 'Netherlands',
                                    'de' => 'Germany', 'fr' => 'France', 'be' => 'Belgium', 'es' => 'Spain',
                                    'it' => 'Italy', 'au' => 'Australia', 'ca' => 'Canada', 'br' => 'Brazil',
                                ];
                                foreach ($databases as $code => $name) {
                                    printf(
                                        '<option value="%s"%s>%s</option>',
                                        esc_attr($code),
                                        selected($code, $defaultSource, false),
                                        esc_html($name)
                                    );
                                }
                                ?>
                            </select>
                        </div>
                        <button type="button" onclick="sseoRunAnalysis()"><?php esc_html_e('Analyze', 'ai-seo-client'); ?></button>
                    </div>

                    <div class="seo-data-tabs">
                        <button type="button" class="seo-data-tab active" onclick="sseoSwitchTab(this, 'domain')"><?php esc_html_e('Domain Analysis', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'keywords')"><?php esc_html_e('Keyword Research', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'serp')"><?php esc_html_e('SERP', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'competitors')"><?php esc_html_e('Competitors', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'aisearch')"><?php esc_html_e('AI Search', 'ai-seo-client'); ?></button>
                        <button type="button" class="seo-data-tab" onclick="sseoSwitchTab(this, 'overview')"><?php esc_html_e('Projects / Ahrefs', 'ai-seo-client'); ?></button>
                    </div>

                    <div id="panel-domain" class="seo-data-panel active">
                        <p class="seo-loading"><?php esc_html_e('Enter a domain and click Analyze to load domain analysis.', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-keywords" class="seo-data-panel">
                        <p class="seo-loading"><?php esc_html_e('Enter a keyword and click Analyze to load keyword research.', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-serp" class="seo-data-panel">
                        <p class="seo-loading"><?php esc_html_e('Enter a keyword and click Analyze to fetch live SERP results.', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-competitors" class="seo-data-panel">
                        <p class="seo-loading"><?php esc_html_e('Enter a domain and click Analyze to load competitors.', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-aisearch" class="seo-data-panel">
                        <p class="seo-loading"><?php esc_html_e('Enter a domain and click Analyze to load AI search visibility.', 'ai-seo-client'); ?></p>
                    </div>
                    <div id="panel-overview" class="seo-data-panel">
                        <div id="panel-overview-summary"></div>
                        <div id="panel-seranking" style="margin-top:20px;"></div>
                        <div id="panel-ahrefs" style="margin-top:20px;"></div>
                    </div>
                </div>
                <?php DashboardSorter::end('ai-seo-google-data'); ?>
            </div>
        </div>

        <script>
        var SSEO_STR = {
            loading: '<?php echo esc_js(__('Loadingâ€¦', 'ai-seo-client')); ?>',
            noData: '<?php echo esc_js(__('No data available.', 'ai-seo-client')); ?>',
            needDomain: '<?php echo esc_js(__('Please enter a domain.', 'ai-seo-client')); ?>',
            needKeyword: '<?php echo esc_js(__('Please enter a keyword.', 'ai-seo-client')); ?>',
            serpWait: '<?php echo esc_js(__('Fetching live SERP results, this can take up to a minuteâ€¦', 'ai-seo-client')); ?>'
        };

        function sseoEsc(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, function(c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }
        function sseoNum(n) {
            if (n === null || n === undefined || n === '') return 'â€”';
            var v = Number(n);
            return isNaN(v) ? sseoEsc(n) : v.toLocaleString();
        }
        function sseoInputs() {
            return {
                domain: (document.getElementById('seo-input-domain').value || '').trim(),
                keyword: (document.getElementById('seo-input-keyword').value || '').trim(),
                source: document.getElementById('seo-input-source').value
            };
        }
        function sseoActiveTab() {
            var el = document.querySelector('.seo-data-tab.active');
            return el ? el.getAttribute('onclick').match(/'([^']+)'\)/)[1] : 'domain';
        }
        function sseoErr(msg) { return '<p style="color:#d63638;">' + sseoEsc(msg) + '</p>'; }
        function sseoLoad(id) { document.getElementById(id).innerHTML = '<p class="seo-loading">' + SSEO_STR.loading + '</p>'; }

        function sseoSwitchTab(tab, panelId) {
            document.querySelectorAll('.seo-data-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.seo-data-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('panel-' + panelId).classList.add('active');
            sseoRunAnalysis();
        }

        function sseoRunAnalysis() {
            var tab = sseoActiveTab();
            if (tab === 'domain') sseoLoadDomain();
            else if (tab === 'keywords') sseoLoadKeywords();
            else if (tab === 'serp') sseoLoadSerp();
            else if (tab === 'competitors') sseoLoadCompetitors();
            else if (tab === 'aisearch') sseoLoadAiSearch();
            else if (tab === 'overview') { sseoLoadOverview(); sseoLoadSERanking(); sseoLoadAhrefs(); }
        }

        function sseoKwTable(obj) {
            var rows = (obj && obj.keywords) ? obj.keywords : (Array.isArray(obj) ? obj : []);
            if (obj && obj.error) return sseoErr(obj.error);
            if (!rows.length) return '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
            var html = '<table class="seo-data-table"><thead><tr>' +
                '<th><?php echo esc_js(__('Keyword', 'ai-seo-client')); ?></th>' +
                '<th><?php echo esc_js(__('Volume', 'ai-seo-client')); ?></th>' +
                '<th><?php echo esc_js(__('CPC', 'ai-seo-client')); ?></th>' +
                '<th><?php echo esc_js(__('Difficulty', 'ai-seo-client')); ?></th>' +
                '<th><?php echo esc_js(__('Position', 'ai-seo-client')); ?></th>' +
                '</tr></thead><tbody>';
            rows.forEach(function(k) {
                html += '<tr><td>' + sseoEsc(k.keyword) + '</td>' +
                    '<td>' + sseoNum(k.volume) + '</td>' +
                    '<td>' + (k.cpc != null ? '$' + sseoEsc(k.cpc) : 'â€”') + '</td>' +
                    '<td>' + (k.difficulty != null ? sseoEsc(k.difficulty) : 'â€”') + '</td>' +
                    '<td>' + (k.position != null ? '#' + sseoEsc(k.position) : 'â€”') + '</td></tr>';
            });
            return html + '</tbody></table>';
        }

        function sseoLoadDomain() {
            var inp = sseoInputs();
            var c = document.getElementById('panel-domain');
            sseoLoad('panel-domain');
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/domain?domain=' + encodeURIComponent(inp.domain) + '&source=' + encodeURIComponent(inp.source) }).then(function(data) {
                if (data.error) { c.innerHTML = sseoErr(data.error); return; }
                var html = '<h3 style="color:#111827;">' + sseoEsc(data.domain) + ' <span style="color:#94a3b8;font-size:12px;">(' + sseoEsc(data.source) + ')</span></h3>';
                var org = (data.overview && data.overview.organic) ? data.overview.organic : null;
                if (data.overview && data.overview.error) {
                    html += sseoErr(data.overview.error);
                } else if (org) {
                    html += '<div class="seo-stat-grid">' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoNum(org.traffic_sum) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Est. Traffic', 'ai-seo-client')); ?></div></div>' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoNum(org.keywords_count) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Keywords', 'ai-seo-client')); ?></div></div>' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">$' + sseoNum(org.price_sum) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Traffic Value', 'ai-seo-client')); ?></div></div>' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoNum(org.top1_5) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Top 1-5', 'ai-seo-client')); ?></div></div>' +
                        '</div>';
                }
                html += '<div class="seo-data-section"><h3><?php echo esc_js(__('Top Keywords', 'ai-seo-client')); ?></h3>' + sseoKwTable(data.keywords) + '</div>';

                html += '<div class="seo-data-section"><h3><?php echo esc_js(__('Top Pages', 'ai-seo-client')); ?></h3>';
                var pages = Array.isArray(data.pages) ? data.pages : [];
                if (data.pages && data.pages.error) {
                    html += sseoErr(data.pages.error);
                } else if (pages.length) {
                    html += '<table class="seo-data-table"><thead><tr><th><?php echo esc_js(__('URL', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Keywords', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Traffic', 'ai-seo-client')); ?></th></tr></thead><tbody>';
                    pages.forEach(function(p) {
                        html += '<tr><td><a href="' + sseoEsc(p.url) + '" target="_blank" rel="noopener">' + sseoEsc(p.url) + '</a></td><td>' + sseoNum(p.keywords_count) + '</td><td>' + sseoNum(p.traffic_sum) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
                }
                html += '</div>';
                c.innerHTML = html;
            }).catch(function(err) { c.innerHTML = sseoErr(err.message || 'Error'); });
        }

        function sseoLoadKeywords() {
            var inp = sseoInputs();
            var c = document.getElementById('panel-keywords');
            if (!inp.keyword) { c.innerHTML = sseoErr(SSEO_STR.needKeyword); return; }
            sseoLoad('panel-keywords');
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/keywords?keyword=' + encodeURIComponent(inp.keyword) + '&source=' + encodeURIComponent(inp.source) }).then(function(data) {
                if (data.error) { c.innerHTML = sseoErr(data.error); return; }
                var html = '';
                var m = Array.isArray(data.metrics) ? data.metrics[0] : null;
                if (m) {
                    html += '<div class="seo-stat-grid">' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoNum(m.volume) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Search Volume', 'ai-seo-client')); ?></div></div>' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">$' + sseoEsc(m.cpc) + '</div><div class="seo-stat-label"><?php echo esc_js(__('CPC', 'ai-seo-client')); ?></div></div>' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoEsc(m.difficulty) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Difficulty', 'ai-seo-client')); ?></div></div>' +
                        '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoEsc(m.competition) + '</div><div class="seo-stat-label"><?php echo esc_js(__('Competition', 'ai-seo-client')); ?></div></div>' +
                        '</div>';
                }
                html += '<div class="seo-data-section"><h3><?php echo esc_js(__('Similar Keywords', 'ai-seo-client')); ?></h3>' + sseoKwTable(data.similar) + '</div>';
                html += '<div class="seo-data-section"><h3><?php echo esc_js(__('Related Keywords', 'ai-seo-client')); ?></h3>' + sseoKwTable(data.related) + '</div>';
                html += '<div class="seo-data-section"><h3><?php echo esc_js(__('Question Keywords', 'ai-seo-client')); ?></h3>' + sseoKwTable(data.questions) + '</div>';
                c.innerHTML = html;
            }).catch(function(err) { c.innerHTML = sseoErr(err.message || 'Error'); });
        }

        function sseoLoadCompetitors() {
            var inp = sseoInputs();
            var c = document.getElementById('panel-competitors');
            sseoLoad('panel-competitors');
            var compareDomain = (document.getElementById('seo-input-compare-domain') || {}).value || '';
            var path = '/sseo-ai/v1/seo-data/competitors?domain=' + encodeURIComponent(inp.domain) + '&source=' + encodeURIComponent(inp.source);
            if (compareDomain) path += '&compare_domain=' + encodeURIComponent(compareDomain.trim());
            wp.apiFetch({ path: path }).then(function(data) {
                if (data.error) { c.innerHTML = sseoErr(data.error); return; }
                var html = '<h3><?php echo esc_js(__('Organic Competitors for', 'ai-seo-client')); ?> ' + sseoEsc(data.domain) + '</h3>';

                // Compare domain input
                html += '<div class="seo-search-bar" style="margin-bottom:15px;"><div class="seo-search-field"><label for="seo-input-compare-domain"><?php echo esc_js(__('Compare with domain (optional)', 'ai-seo-client')); ?></label><input type="text" id="seo-input-compare-domain" placeholder="<?php echo esc_attr(__('e.g. competitor.com', 'ai-seo-client')); ?>" value="' + sseoEsc(compareDomain) + '"></div><button type="button" onclick="sseoLoadCompetitors()"><?php echo esc_js(__('Compare', 'ai-seo-client')); ?></button></div>';

                var comps = Array.isArray(data.competitors) ? data.competitors : [];
                if (data.competitors && data.competitors.error) {
                    html += sseoErr(data.competitors.error);
                } else if (!comps.length) {
                    html += '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
                } else {
                    html += '<table class="seo-data-table"><thead><tr>' +
                        '<th><?php echo esc_js(__('Competitor', 'ai-seo-client')); ?></th>' +
                        '<th><?php echo esc_js(__('Relevance', 'ai-seo-client')); ?></th>' +
                        '<th><?php echo esc_js(__('Common Keywords', 'ai-seo-client')); ?></th>' +
                        '<th><?php echo esc_js(__('Keyword Gap', 'ai-seo-client')); ?></th>' +
                        '<th><?php echo esc_js(__('Total Keywords', 'ai-seo-client')); ?></th>' +
                        '<th><?php echo esc_js(__('Est. Traffic', 'ai-seo-client')); ?></th>' +
                        '<th><?php echo esc_js(__('Traffic Value', 'ai-seo-client')); ?></th>' +
                        '</tr></thead><tbody>';
                    comps.forEach(function(comp) {
                        html += '<tr>' +
                            '<td><strong>' + sseoEsc(comp.domain) + '</strong></td>' +
                            '<td>' + sseoNum(comp.domain_relevance) + '%</td>' +
                            '<td>' + sseoNum(comp.common_keywords) + '</td>' +
                            '<td class="seo-diff-neg">' + sseoNum(comp.missing_keywords) + '</td>' +
                            '<td>' + sseoNum(comp.total_keywords) + '</td>' +
                            '<td>' + sseoNum(comp.traffic_sum) + '</td>' +
                            '<td>$' + sseoNum(comp.price_sum) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                // Keyword gap comparison
                if (data.keyword_gap) {
                    html += '<div class="seo-data-section"><h3><?php echo esc_js(__('Keyword Gap: keywords you rank for but', 'ai-seo-client')); ?> ' + sseoEsc(data.compare_domain) + ' <?php echo esc_js(__('does not', 'ai-seo-client')); ?></h3>';
                    if (data.keyword_gap.error) {
                        html += sseoErr(data.keyword_gap.error);
                    } else {
                        var gapRows = (data.keyword_gap.keywords) ? data.keyword_gap.keywords : (Array.isArray(data.keyword_gap) ? data.keyword_gap : []);
                        if (gapRows.length) {
                            html += '<table class="seo-data-table"><thead><tr><th><?php echo esc_js(__('Keyword', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Position', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Volume', 'ai-seo-client')); ?></th><th><?php echo esc_js(__('Traffic', 'ai-seo-client')); ?></th></tr></thead><tbody>';
                            gapRows.forEach(function(k) {
                                html += '<tr><td>' + sseoEsc(k.keyword) + '</td>' +
                                    '<td>#' + sseoEsc(k.position) + '</td>' +
                                    '<td>' + sseoNum(k.volume) + '</td>' +
                                    '<td>' + sseoNum(k.traffic) + '</td></tr>';
                            });
                            html += '</tbody></table>';
                        } else {
                            html += '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
                        }
                    }
                    html += '</div>';
                }

                c.innerHTML = html;
            }).catch(function(err) { c.innerHTML = sseoErr(err.message || 'Error'); });
        }

        function sseoAiSummaryCard(label, val, change) {
            var cls = '';
            var arrow = '';
            if (change !== null && change !== undefined && change !== '') {
                var n = parseFloat(change);
                if (!isNaN(n)) {
                    if (n > 0) { cls = 'seo-diff-pos'; arrow = ' (&#9650; ' + n + '%)'; }
                    else if (n < 0) { cls = 'seo-diff-neg'; arrow = ' (&#9660; ' + n + '%)'; }
                }
            }
            return '<div class="seo-stat-card"><div class="seo-stat-value ' + cls + '">' + sseoNum(val) + arrow + '</div><div class="seo-stat-label">' + sseoEsc(label) + '</div></div>';
        }

        function sseoLoadAiSearch() {
            var inp = sseoInputs();
            var c = document.getElementById('panel-aisearch');
            sseoLoad('panel-aisearch');
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/ai-search?domain=' + encodeURIComponent(inp.domain) + '&source=' + encodeURIComponent(inp.source) }).then(function(data) {
                if (data.error) { c.innerHTML = sseoErr(data.error); return; }
                var html = '<h3 style="color:#111827;">' + sseoEsc(data.domain) + ' <span style="color:#94a3b8;font-size:12px;">(' + sseoEsc(data.source) + ')</span></h3>';
                if (data.brand) {
                    html += '<p style="color:#64748b;font-size:13px;margin-bottom:15px;"><?php echo esc_js(__("Detected brand:", "ai-seo-client")); ?> <strong>' + sseoEsc(data.brand) + '</strong></p>';
                }

                // Aggregated overview
                if (data.aggregated && !data.aggregated.error) {
                    var s = data.aggregated.summary || {};
                    html += '<div class="seo-data-section"><h3><?php echo esc_js(__("Aggregated AI Search Visibility", "ai-seo-client")); ?></h3><div class="seo-stat-grid">';
                    if (s.brand_presence) html += sseoAiSummaryCard('<?php echo esc_js(__("Brand Presence", "ai-seo-client")); ?>', s.brand_presence.current, s.brand_presence.change_percent);
                    if (s.link_presence) html += sseoAiSummaryCard('<?php echo esc_js(__("Link Presence", "ai-seo-client")); ?>', s.link_presence.current, s.link_presence.change_percent);
                    if (s.ai_opportunity_traffic) html += sseoAiSummaryCard('<?php echo esc_js(__("AI Opportunity Traffic", "ai-seo-client")); ?>', s.ai_opportunity_traffic.current, s.ai_opportunity_traffic.change_percent);
                    if (s.average_position) html += sseoAiSummaryCard('<?php echo esc_js(__("Avg Position", "ai-seo-client")); ?>', s.average_position.current, s.average_position.change_percent);
                    html += '</div></div>';
                } else if (data.aggregated && data.aggregated.error) {
                    html += sseoErr(data.aggregated.error);
                }

                // Per-engine breakdown
                var engines = ['ai-overview', 'chatgpt', 'perplexity', 'gemini', 'ai-mode'];
                var engineLabels = { 'ai-overview': 'Google AI Overview', 'chatgpt': 'ChatGPT', 'perplexity': 'Perplexity', 'gemini': 'Gemini', 'ai-mode': 'Google AI Mode' };
                html += '<div class="seo-data-section"><h3><?php echo esc_js(__("Per-Engine Breakdown", "ai-seo-client")); ?></h3>';
                html += '<table class="seo-data-table"><thead><tr><th><?php echo esc_js(__("Engine", "ai-seo-client")); ?></th><th><?php echo esc_js(__("Brand Presence", "ai-seo-client")); ?></th><th><?php echo esc_js(__("Link Presence", "ai-seo-client")); ?></th><th><?php echo esc_js(__("AI Traffic", "ai-seo-client")); ?></th><th><?php echo esc_js(__("Avg Position", "ai-seo-client")); ?></th></tr></thead><tbody>';
                engines.forEach(function(eng) {
                    var ed = data.per_engine && data.per_engine[eng];
                    if (!ed || ed.error) {
                        html += '<tr><td>' + sseoEsc(engineLabels[eng] || eng) + '</td><td colspan="4" style="color:#94a3b8;">' + (ed && ed.error ? sseoEsc(ed.error) : SSEO_STR.noData) + '</td></tr>';
                        return;
                    }
                    var es = ed.summary || {};
                    html += '<tr><td><strong>' + sseoEsc(engineLabels[eng] || eng) + '</strong></td>' +
                        '<td>' + sseoNum(es.brand_presence ? es.brand_presence.current : null) + '</td>' +
                        '<td>' + sseoNum(es.link_presence ? es.link_presence.current : null) + '</td>' +
                        '<td>' + sseoNum(es.ai_opportunity_traffic ? es.ai_opportunity_traffic.current : null) + '</td>' +
                        '<td>' + sseoNum(es.average_position ? es.average_position.current : null) + '</td></tr>';
                });
                html += '</tbody></table></div>';

                // Prompts
                html += '<div class="seo-data-section"><h3><?php echo esc_js(__("Sample Prompts (AI Overview)", "ai-seo-client")); ?></h3>';
                var prompts = data.prompts;
                if (prompts && prompts.error) {
                    html += sseoErr(prompts.error);
                } else {
                    var pRows = (prompts && prompts.prompts) ? prompts.prompts : (Array.isArray(prompts) ? prompts : []);
                    if (pRows.length) {
                        html += '<table class="seo-data-table"><thead><tr><th><?php echo esc_js(__("Prompt", "ai-seo-client")); ?></th><th><?php echo esc_js(__("Position", "ai-seo-client")); ?></th></tr></thead><tbody>';
                        pRows.forEach(function(p) {
                            var promptText = p.prompt || p.query || p.text || '';
                            var pos = p.position || p.pos || '';
                            html += '<tr><td style="max-width:500px;">' + sseoEsc(promptText) + '</td><td>' + (pos ? '#' + sseoEsc(pos) : '&mdash;') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    } else {
                        html += '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
                    }
                }
                html += '</div>';

                c.innerHTML = html;
            }).catch(function(err) { c.innerHTML = sseoErr(err.message || 'Error'); });
        }

        var sseoSerpPolls = 0;
        function sseoLoadSerp() {
            var inp = sseoInputs();
            var c = document.getElementById('panel-serp');
            if (!inp.keyword) { c.innerHTML = sseoErr(SSEO_STR.needKeyword); return; }
            c.innerHTML = '<p class="seo-loading">' + SSEO_STR.serpWait + '</p>';
            sseoSerpPolls = 0;
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/serp?keyword=' + encodeURIComponent(inp.keyword) + '&source=' + encodeURIComponent(inp.source) }).then(function(data) {
                if (data.error) { c.innerHTML = sseoErr(data.error); return; }
                var taskId = data.task && (data.task.task_id || data.task.id);
                if (taskId) { sseoPollSerp(taskId); }
                else { sseoRenderSerp(data.task); }
            }).catch(function(err) { c.innerHTML = sseoErr(err.message || 'Error'); });
        }
        function sseoPollSerp(taskId) {
            var c = document.getElementById('panel-serp');
            if (sseoSerpPolls++ > 20) { c.innerHTML = sseoErr('<?php echo esc_js(__('SERP task timed out. Please try again.', 'ai-seo-client')); ?>'); return; }
            setTimeout(function() {
                wp.apiFetch({ path: '/sseo-ai/v1/seo-data/serp?task_id=' + encodeURIComponent(taskId) }).then(function(data) {
                    if (data.error) { c.innerHTML = sseoErr(data.error); return; }
                    var t = data.task || {};
                    var results = t.results || t.organic || (t.data && t.data.organic);
                    if (results && results.length) { sseoRenderSerp(t); }
                    else { sseoPollSerp(taskId); }
                }).catch(function(err) { c.innerHTML = sseoErr(err.message || 'Error'); });
            }, 3000);
        }
        function sseoRenderSerp(task) {
            var c = document.getElementById('panel-serp');
            var results = (task && (task.results || task.organic || (task.data && task.data.organic))) || [];
            if (!results.length) { c.innerHTML = '<p class="seo-loading">' + SSEO_STR.noData + '</p>'; return; }
            var html = '<h3><?php echo esc_js(__('SERP Results', 'ai-seo-client')); ?></h3><table class="seo-data-table"><thead><tr><th>#</th><th><?php echo esc_js(__('Title / URL', 'ai-seo-client')); ?></th></tr></thead><tbody>';
            results.forEach(function(r, i) {
                var pos = r.position || r.pos || (i + 1);
                var url = r.url || r.link || '';
                var title = r.title || url;
                html += '<tr><td>' + sseoEsc(pos) + '</td><td><strong>' + sseoEsc(title) + '</strong><br><a href="' + sseoEsc(url) + '" target="_blank" rel="noopener" style="color:#379fd3;font-size:12px;">' + sseoEsc(url) + '</a></td></tr>';
            });
            html += '</tbody></table>';
            c.innerHTML = html;
        }

        function sseoLoadOverview() {
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/overview' }).then(function(data) {
                var container = document.getElementById('panel-overview-summary');
                if (!container) return;
                if (data.error) { container.innerHTML = sseoErr(data.error); return; }
                var html = '';
                if (data.ahrefs) {
                    html += '<h3><?php echo esc_js(__('Ahrefs (current site)', 'ai-seo-client')); ?></h3><div class="seo-stat-grid">';
                    (data.ahrefs.stats || []).forEach(function(s) {
                        html += '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoEsc(s.value != null ? s.value : 'â€”') + '</div><div class="seo-stat-label">' + sseoEsc(s.label) + '</div></div>';
                    });
                    html += '</div>';
                }
                container.innerHTML = html || '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
            }).catch(function(err) {
                var container = document.getElementById('panel-overview-summary');
                if (container) container.innerHTML = sseoErr(err.message || 'Error');
            });
        }

        function sseoLoadSERanking() {
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/seranking' }).then(function(data) {
                var container = document.getElementById('panel-seranking');
                if (!container) return;
                if (data.error) { container.innerHTML = sseoErr(data.error); return; }
                var html = '<h3><?php echo esc_js(__('SE Ranking Projects', 'ai-seo-client')); ?></h3>';
                if (data.sites && data.sites.length) {
                    html += '<table class="seo-data-table"><thead><tr><th>ID</th><th><?php echo esc_js(__('Name', 'ai-seo-client')); ?></th><th>URL</th></tr></thead><tbody>';
                    data.sites.forEach(function(site) {
                        html += '<tr><td>' + sseoEsc(site.id) + '</td><td>' + sseoEsc(site.name) + '</td><td>' + sseoEsc(site.url) + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<p class="seo-loading">' + SSEO_STR.noData + '</p>';
                }
                container.innerHTML = html;
            }).catch(function(err) {
                var container = document.getElementById('panel-seranking');
                if (container) container.innerHTML = sseoErr(err.message || 'Error');
            });
        }

        function sseoLoadAhrefs() {
            wp.apiFetch({ path: '/sseo-ai/v1/seo-data/ahrefs' }).then(function(data) {
                var container = document.getElementById('panel-ahrefs');
                if (!container) return;
                if (data.error) { container.innerHTML = sseoErr(data.error); return; }
                var html = '<h3><?php echo esc_js(__('Ahrefs Domain Overview', 'ai-seo-client')); ?></h3><div class="seo-stat-grid">';
                (data.stats || []).forEach(function(s) {
                    html += '<div class="seo-stat-card"><div class="seo-stat-value">' + sseoEsc(s.value != null ? s.value : 'â€”') + '</div><div class="seo-stat-label">' + sseoEsc(s.label) + '</div></div>';
                });
                html += '</div>';
                container.innerHTML = html;
            }).catch(function(err) {
                var container = document.getElementById('panel-ahrefs');
                if (container) container.innerHTML = sseoErr(err.message || 'Error');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            sseoLoadDomain();
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
                    ['label' => __('Domain Rating', 'ai-seo-client'), 'value' => (!is_wp_error($dr) && isset($dr['domain_rating'])) ? $dr['domain_rating']['value'] : 'â€”'],
                    ['label' => __('Backlinks', 'ai-seo-client'), 'value' => (!is_wp_error($bl) && isset($bl['metrics'])) ? number_format($bl['metrics']['live_refs'] ?? 0) : 'â€”'],
                    ['label' => __('Referring Domains', 'ai-seo-client'), 'value' => (!is_wp_error($bl) && isset($bl['metrics'])) ? number_format($bl['metrics']['live_ref_domains'] ?? 0) : 'â€”'],
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

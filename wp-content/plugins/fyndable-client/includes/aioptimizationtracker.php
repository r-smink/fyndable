<?php

namespace SSEOAIClient;

/**
 * AI Optimization Tracker
 *
 * Tracks and optimizes for AI Search / LLM visibility using the DataForSEO
 * AI Optimization API (via the Portal proxy):
 *  - LLM Mentions: how often a domain/brand is mentioned in ChatGPT/Claude/Gemini/Perplexity
 *  - Top mentioned pages/domains/brands
 *  - Historical timeseries
 *  - AI Keyword Data: search volume for AI prompts
 *  - Live LLM Responses: what AIs say about a topic/brand
 *
 * Professional+ tier feature.
 */
class AiOptimizationTracker
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function __construct(Settings $settings, DashboardAPI $dashboardAPI)
    {
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/ai-optimization/mentions', [
            'methods'             => 'POST',
            'callback'            => [$this, 'restGetMentions'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args'                => [
                'action'  => ['type' => 'string', 'required' => false, 'default' => 'target_metrics'],
                'params'  => ['type' => 'object', 'required' => false],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/ai-optimization/keyword-data', [
            'methods'             => 'POST',
            'callback'            => [$this, 'restGetKeywordData'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args'                => [
                'keywords' => ['type' => 'array', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/ai-optimization/llm-response', [
            'methods'             => 'POST',
            'callback'            => [$this, 'restGetLlmResponse'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args'                => [
                'provider' => ['type' => 'string', 'required' => true],
                'prompt'   => ['type' => 'string', 'required' => true],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // REST handlers
    // -------------------------------------------------------------------------

    public function restGetMentions(\WP_REST_Request $request): \WP_REST_Response
    {
        $action = sanitize_text_field($request->get_param('action') ?: 'target_metrics');
        $params = $request->get_param('params') ?: [];
        if (!is_array($params)) {
            $params = [];
        }

        $result = $this->getMentions($action, $params);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function restGetKeywordData(\WP_REST_Request $request): \WP_REST_Response
    {
        $keywords = $request->get_param('keywords') ?: [];
        $keywords = array_filter(array_map('sanitize_text_field', $keywords));

        if (empty($keywords)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('keywords array is required', 'ai-seo-client'),
            ], 400);
        }

        $result = $this->getKeywordData($keywords);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function restGetLlmResponse(\WP_REST_Request $request): \WP_REST_Response
    {
        $provider = sanitize_text_field($request->get_param('provider'));
        $prompt   = sanitize_textarea_field($request->get_param('prompt'));

        if (empty($provider) || empty($prompt)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_params',
                'message' => __('provider and prompt are required', 'ai-seo-client'),
            ], 400);
        }

        $result = $this->getLlmResponse($provider, $prompt);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ], 502);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    // -------------------------------------------------------------------------
    // API methods (proxy through DashboardAPI)
    // -------------------------------------------------------------------------

    /**
     * Get LLM Mentions data for the given action.
     *
     * @param string $action One of: search_mentions, target_metrics, top_domains,
     *                       top_pages, top_brands, top_brand_categories, historical,
     *                       timeseries_delta, timeseries_new_and_lost
     * @param array  $params DataForSEO task parameters
     */
    public function getMentions(string $action, array $params = []): array|\WP_Error
    {
        $cacheKey = 'aiseo_aiopt_mentions_' . md5($action . serialize($params));
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = $this->dashboardAPI->request('ai/llm-mentions', [
            'action' => $action,
            'params' => $params,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = $response['data'] ?? [];
        set_transient($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    /**
     * Get AI keyword search volume data.
     *
     * @param array $keywords List of keywords to look up
     */
    public function getKeywordData(array $keywords): array|\WP_Error
    {
        $cacheKey = 'aiseo_aiopt_kwdata_' . md5(serialize($keywords));
        $cached = get_transient($cacheKey);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $response = $this->dashboardAPI->request('ai/keyword-data', [
            'keywords' => $keywords,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = $response['data'] ?? [];
        set_transient($cacheKey, $data, self::CACHE_TTL);
        return $data;
    }

    /**
     * Get a live LLM response from the specified provider.
     *
     * @param string $provider One of: chatgpt, claude, gemini, perplexity
     * @param string $prompt   The prompt to send
     */
    public function getLlmResponse(string $provider, string $prompt): array|\WP_Error
    {
        $response = $this->dashboardAPI->request('ai/llm-response', [
            'provider' => $provider,
            'prompt'   => $prompt,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response['data'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Admin dashboard rendering
    // -------------------------------------------------------------------------

    public function renderDashboard(): void
    {
        $siteUrl = get_site_url();
        $domain = parse_url($siteUrl, PHP_URL_HOST) ?: '';
        $brand = get_bloginfo('name');

        // Fetch target metrics for the current domain
        $targetMetrics = $this->getMentions('target_metrics', [
            'targets' => [$domain],
        ]);

        $hasError = is_wp_error($targetMetrics);
        $metrics = $hasError ? [] : $this->extractTargetMetrics($targetMetrics);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Optimization Tracker', 'ai-seo-client'); ?></h1>
            <p class="description">
                <?php esc_html_e('Track how often your domain and brand are mentioned in AI responses (ChatGPT, Claude, Gemini, Perplexity) and monitor your AI search visibility.', 'ai-seo-client'); ?>
            </p>

            <?php if ($hasError): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($targetMetrics->get_error_message()); ?></p>
                    <p><?php esc_html_e('Make sure the DataForSEO API key is configured in the Portal settings.', 'ai-seo-client'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Overview Cards -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px; margin-bottom: 30px;">
                <div class="card" style="padding: 20px; text-align: center;">
                    <h2 style="font-size: 36px; margin: 0; color: #2271b1;">
                        <?php echo esc_html(number_format($metrics['total_mentions'] ?? 0)); ?>
                    </h2>
                    <p style="color: #666; margin: 5px 0 0;">
                        <?php esc_html_e('Total AI Mentions', 'ai-seo-client'); ?>
                    </p>
                </div>
                <div class="card" style="padding: 20px; text-align: center;">
                    <h2 style="font-size: 36px; margin: 0; color: #2271b1;">
                        <?php echo esc_html(number_format($metrics['chatgpt_mentions'] ?? 0)); ?>
                    </h2>
                    <p style="color: #666; margin: 5px 0 0;">
                        <?php esc_html_e('ChatGPT Mentions', 'ai-seo-client'); ?>
                    </p>
                </div>
                <div class="card" style="padding: 20px; text-align: center;">
                    <h2 style="font-size: 36px; margin: 0; color: #2271b1;">
                        <?php echo esc_html(number_format($metrics['claude_mentions'] ?? 0)); ?>
                    </h2>
                    <p style="color: #666; margin: 5px 0 0;">
                        <?php esc_html_e('Claude Mentions', 'ai-seo-client'); ?>
                    </p>
                </div>
                <div class="card" style="padding: 20px; text-align: center;">
                    <h2 style="font-size: 36px; margin: 0; color: #2271b1;">
                        <?php echo esc_html(number_format($metrics['gemini_mentions'] ?? 0)); ?>
                    </h2>
                    <p style="color: #666; margin: 5px 0 0;">
                        <?php esc_html_e('Gemini Mentions', 'ai-seo-client'); ?>
                    </p>
                </div>
            </div>

            <!-- LLM Response Checker -->
            <div class="card" style="padding: 20px; margin-bottom: 20px;">
                <h2><?php esc_html_e('AI Response Checker', 'ai-seo-client'); ?></h2>
                <p class="description"><?php esc_html_e('See what AI models say about your brand or keyword.', 'ai-seo-client'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aiopt_provider"><?php esc_html_e('AI Provider', 'ai-seo-client'); ?></label></th>
                        <td>
                            <select id="aiopt_provider">
                                <option value="chatgpt">ChatGPT</option>
                                <option value="claude">Claude</option>
                                <option value="gemini">Gemini</option>
                                <option value="perplexity">Perplexity</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aiopt_prompt"><?php esc_html_e('Prompt', 'ai-seo-client'); ?></label></th>
                        <td>
                            <textarea id="aiopt_prompt" rows="3" class="large-text" placeholder="<?php esc_attr_e('e.g. What is the best SEO plugin for WordPress?', 'ai-seo-client'); ?>"></textarea>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" id="aiopt_check_btn">
                        <?php esc_html_e('Check AI Response', 'ai-seo-client'); ?>
                    </button>
                </p>
                <div id="aiopt_response_container" style="margin-top: 15px; display: none;">
                    <h3><?php esc_html_e('AI Response', 'ai-seo-client'); ?></h3>
                    <div id="aiopt_response_output" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>

            <!-- AI Keyword Data -->
            <div class="card" style="padding: 20px; margin-bottom: 20px;">
                <h2><?php esc_html_e('AI Keyword Search Volume', 'ai-seo-client'); ?></h2>
                <p class="description"><?php esc_html_e('Search volume data for how people prompt AI models.', 'ai-seo-client'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aiopt_keywords"><?php esc_html_e('Keywords (one per line)', 'ai-seo-client'); ?></label></th>
                        <td>
                            <textarea id="aiopt_keywords" rows="4" class="large-text" placeholder="<?php esc_attr_e('best seo plugin&#10;wordpress seo&#10;ai seo tools', 'ai-seo-client'); ?>"></textarea>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="button" class="button button-primary" id="aiopt_kw_btn">
                        <?php esc_html_e('Get Search Volume', 'ai-seo-client'); ?>
                    </button>
                </p>
                <div id="aiopt_kw_container" style="margin-top: 15px; display: none;">
                    <table class="widefat striped" id="aiopt_kw_table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Search Volume', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Difficulty', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('CPC', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <script>
            (function() {
                var restUrl = '<?php echo esc_url_raw(rest_url('sseo-ai/v1')); ?>';
                var nonce = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';

                function post(endpoint, body, cb) {
                    fetch(restUrl + endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce,
                        },
                        body: JSON.stringify(body),
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) { cb(null, data); })
                    .catch(function(err) { cb(err); });
                }

                document.getElementById('aiopt_check_btn').addEventListener('click', function() {
                    var provider = document.getElementById('aiopt_provider').value;
                    var prompt = document.getElementById('aiopt_prompt').value.trim();
                    if (!prompt) { return; }
                    var btn = this; btn.disabled = true;
                    var out = document.getElementById('aiopt_response_output');
                    out.textContent = 'Loading...';
                    document.getElementById('aiopt_response_container').style.display = 'block';
                    post('/ai-optimization/llm-response', { provider: provider, prompt: prompt }, function(err, data) {
                        btn.disabled = false;
                        if (err || !data.success) {
                            out.textContent = (data && data.message) || 'Error fetching AI response';
                            return;
                        }
                        var tasks = (data.data && data.data.tasks) || [];
                        var text = '';
                        if (tasks[0] && tasks[0].result && tasks[0].result[0]) {
                            var item = tasks[0].result[0];
                            text = item.content || item.text || item.response || JSON.stringify(item, null, 2);
                        } else {
                            text = JSON.stringify(data.data, null, 2);
                        }
                        out.textContent = text;
                    });
                });

                document.getElementById('aiopt_kw_btn').addEventListener('click', function() {
                    var raw = document.getElementById('aiopt_keywords').value.trim();
                    if (!raw) { return; }
                    var keywords = raw.split('\n').map(function(k) { return k.trim(); }).filter(Boolean);
                    var btn = this; btn.disabled = true;
                    var tbody = document.querySelector('#aiopt_kw_table tbody');
                    tbody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
                    document.getElementById('aiopt_kw_container').style.display = 'block';
                    post('/ai-optimization/keyword-data', { keywords: keywords }, function(err, data) {
                        btn.disabled = false;
                        if (err || !data.success) {
                            tbody.innerHTML = '<tr><td colspan="4">' + ((data && data.message) || 'Error') + '</td></tr>';
                            return;
                        }
                        var tasks = (data.data && data.data.tasks) || [];
                        var items = (tasks[0] && tasks[0].result && tasks[0].result[0] && tasks[0].result[0].items) || [];
                        if (!items.length) {
                            tbody.innerHTML = '<tr><td colspan="4">No data</td></tr>';
                            return;
                        }
                        tbody.innerHTML = items.map(function(item) {
                            return '<tr><td>' + escHtml(item.keyword || '') + '</td>' +
                                '<td>' + (item.search_volume || 0) + '</td>' +
                                '<td>' + (item.difficulty || 0) + '</td>' +
                                '<td>' + (item.cpc || 0) + '</td></tr>';
                        }).join('');
                    });
                });

                function escHtml(s) {
                    var d = document.createElement('div');
                    d.textContent = s;
                    return d.innerHTML;
                }
            })();
            </script>
        </div>
        <?php
    }

    /**
     * Extract normalized target metrics from the DataForSEO response.
     */
    private function extractTargetMetrics(array $data): array
    {
        $tasks = $data['tasks'] ?? [];
        $result = $tasks[0]['result'][0] ?? [];
        $metrics = $result['metrics'] ?? $result;

        return [
            'total_mentions'    => (int) ($metrics['total_mentions'] ?? $metrics['mentions'] ?? 0),
            'chatgpt_mentions'  => (int) ($metrics['chatgpt_mentions'] ?? 0),
            'claude_mentions'   => (int) ($metrics['claude_mentions'] ?? 0),
            'gemini_mentions'   => (int) ($metrics['gemini_mentions'] ?? 0),
            'perplexity_mentions' => (int) ($metrics['perplexity_mentions'] ?? 0),
        ];
    }
}

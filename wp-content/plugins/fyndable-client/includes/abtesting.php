<?php

namespace SSEOAIClient;

/**
 * A/B Testing Module
 *
 * Test page variants (title, content, meta) and track conversions.
 * Traffic split via session cookie. Goal types: page_view, click, form_submit, time_on_page.
 */
class ABTesting
{
    private Settings $settings;
    private string $testsTable;
    private string $variantsTable;
    private string $conversionsTable;

    public function __construct(Settings $settings)
    {
        global $wpdb;
        $this->settings = $settings;
        $this->testsTable = $wpdb->prefix . 'sseo_ai_ab_tests';
        $this->variantsTable = $wpdb->prefix . 'sseo_ai_ab_variants';
        $this->conversionsTable = $wpdb->prefix . 'sseo_ai_ab_conversions';
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_init', [$this, 'createTables']);
        add_action('wp', [$this, 'trackPageView']);
        add_action('wp_footer', [$this, 'injectConversionScript']);

        // Content variant injection
        add_filter('the_title', [$this, 'maybeOverrideTitle'], 999, 2);
        add_filter('the_content', [$this, 'maybeOverrideContent'], 999);
    }

    /**
     * Create database tables
     */
    public function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql1 = "CREATE TABLE {$this->testsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            test_name varchar(255) NOT NULL,
            goal_type varchar(50) DEFAULT 'page_view',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY status (status)
        ) {$charset};";

        $sql2 = "CREATE TABLE {$this->variantsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            test_id bigint(20) unsigned NOT NULL,
            name varchar(100) NOT NULL,
            title varchar(255) DEFAULT NULL,
            content longtext DEFAULT NULL,
            meta_description varchar(500) DEFAULT NULL,
            traffic_split int(3) DEFAULT 50,
            impressions bigint(20) unsigned DEFAULT 0,
            conversions bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY (id),
            KEY test_id (test_id)
        ) {$charset};";

        $sql3 = "CREATE TABLE {$this->conversionsTable} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            variant_id bigint(20) unsigned NOT NULL,
            test_id bigint(20) unsigned NOT NULL,
            session_id varchar(64) NOT NULL,
            conversion_type varchar(50) DEFAULT 'page_view',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY variant_id (variant_id),
            KEY session_id (session_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/ab-tests', [
            'methods' => 'GET',
            'callback' => [$this, 'restListTests'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/ab-tests', [
            'methods' => 'POST',
            'callback' => [$this, 'restCreateTest'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/ab-tests/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteTest'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/ab-tests/(?P<id>\d+)/end', [
            'methods' => 'POST',
            'callback' => [$this, 'restEndTest'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/ab-tests/(?P<id>\d+)/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStats'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        // Public conversion endpoint
        register_rest_route('sseo-ai/v1', '/ab-tests/conversion', [
            'methods' => 'POST',
            'callback' => [$this, 'restRecordConversion'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get session ID for variant assignment
     */
    private function getSessionId(): string
    {
        if (!isset($_COOKIE['sseo_ai_ab_session'])) {
            $sessionId = wp_generate_password(32, false);
            setcookie('sseo_ai_ab_session', $sessionId, time() + 86400 * 30, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['sseo_ai_ab_session'] = $sessionId;
        }
        return sanitize_text_field($_COOKIE['sseo_ai_ab_session']);
    }

    /**
     * Assign visitor to a variant based on traffic split
     */
    private function assignVariant(int $testId, array $variants): ?array
    {
        $sessionId = $this->getSessionId();
        $assignedKey = "sseo_ai_ab_v_{$testId}";

        // Check if already assigned
        if (isset($_COOKIE[$assignedKey])) {
            $variantId = (int) $_COOKIE[$assignedKey];
            foreach ($variants as $v) {
                if ((int) $v['id'] === $variantId) {
                    return $v;
                }
            }
        }

        // Random assignment based on traffic split.
        // random_int() is cryptographically secure and avoids mt_rand() predictability.
        $rand = random_int(1, 100);
        $cumulative = 0;
        foreach ($variants as $v) {
            $cumulative += (int) $v['traffic_split'];
            if ($rand <= $cumulative) {
                setcookie($assignedKey, $v['id'], time() + 86400 * 30, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                $_COOKIE[$assignedKey] = $v['id'];
                return $v;
            }
        }

        return $variants[0] ?? null;
    }

    /**
     * Track page view impression
     */
    public function trackPageView(): void
    {
        if (is_admin() || !is_singular('post')) {
            return;
        }

        $postId = get_the_ID();
        if (!$postId) return;

        $test = $this->getActiveTestForPost($postId);
        if (!$test) return;

        $variants = $this->getVariants($test['id']);
        $variant = $this->assignVariant($test['id'], $variants);

        if ($variant) {
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->variantsTable} SET impressions = impressions + 1 WHERE id = %d",
                $variant['id']
            ));
        }
    }

    /**
     * Maybe override post title
     */
    public function maybeOverrideTitle(string $title, int $postId): string
    {
        if (is_admin()) return $title;

        $test = $this->getActiveTestForPost($postId);
        if (!$test) return $title;

        $variants = $this->getVariants($test['id']);
        $variant = $this->assignVariant($test['id'], $variants);

        if ($variant && !empty($variant['title'])) {
            return $variant['title'];
        }

        return $title;
    }

    /**
     * Maybe override post content
     */
    public function maybeOverrideContent(string $content): string
    {
        if (is_admin()) return $content;

        $postId = get_the_ID();
        if (!$postId) return $content;

        $test = $this->getActiveTestForPost($postId);
        if (!$test) return $content;

        $variants = $this->getVariants($test['id']);
        $variant = $this->assignVariant($test['id'], $variants);

        if ($variant && !empty($variant['content'])) {
            return $variant['content'];
        }

        return $content;
    }

    /**
     * Get active test for a post
     */
    private function getActiveTestForPost(int $postId): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->testsTable} WHERE post_id = %d AND status = 'active' LIMIT 1",
            $postId
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Get variants for a test
     */
    private function getVariants(int $testId): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->variantsTable} WHERE test_id = %d ORDER BY id ASC",
            $testId
        ), ARRAY_A) ?: [];
    }

    /**
     * Inject conversion tracking script
     */
    public function injectConversionScript(): void
    {
        $postId = get_the_ID();
        if (!$postId) return;

        $test = $this->getActiveTestForPost($postId);
        if (!$test) return;

        $variants = $this->getVariants($test['id']);
        $variant = $this->assignVariant($test['id'], $variants);
        if (!$variant) return;

        $testId = (int) $test['id'];
        $variantId = (int) $variant['id'];
        $goalType = esc_js($test['goal_type']);
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <script>
        (function() {
            var testId = <?php echo $testId; ?>;
            var variantId = <?php echo $variantId; ?>;
            var goalType = '<?php echo $goalType; ?>';
            var recorded = false;

            function recordConversion(type) {
                if (recorded) return;
                recorded = true;
                var data = {
                    test_id: testId,
                    variant_id: variantId,
                    type: type || goalType
                };
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(
                        '<?php echo esc_url_raw(rest_url('sseo-ai/v1/ab-tests/conversion')); ?>',
                        JSON.stringify(data)
                    );
                } else {
                    fetch('<?php echo esc_url_raw(rest_url('sseo-ai/v1/ab-tests/conversion')); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo $nonce; ?>' },
                        body: JSON.stringify(data),
                        keepalive: true
                    });
                }
            }

            // Goal: page_view already tracked server-side
            if (goalType === 'click') {
                document.addEventListener('click', function(e) {
                    if (e.target.closest('a[href], button')) {
                        recordConversion('click');
                    }
                });
            }

            if (goalType === 'form_submit') {
                document.querySelectorAll('form').forEach(function(form) {
                    form.addEventListener('submit', function() {
                        recordConversion('form_submit');
                    });
                });
            }

            if (goalType === 'time_on_page') {
                setTimeout(function() {
                    recordConversion('time_on_page');
                }, 30000); // 30 seconds
            }

            window.addEventListener('beforeunload', function() {
                recordConversion(goalType);
            });
        })();
        </script>
        <?php
    }

    // ---- REST API Methods ----

    public function restListTests(): array
    {
        global $wpdb;
        $tests = $wpdb->get_results("SELECT t.*, p.post_title as post_title
            FROM {$this->testsTable} t
            LEFT JOIN {$wpdb->posts} p ON p.ID = t.post_id
            ORDER BY t.created_at DESC", ARRAY_A);

        foreach ($tests as &$test) {
            $test['variants'] = $this->getVariants($test['id']);
        }

        return ['tests' => $tests ?: []];
    }

    public function restCreateTest(\WP_REST_Request $request): array
    {
        global $wpdb;

        $postId = (int) $request->get_param('post_id');
        $testName = sanitize_text_field($request->get_param('test_name'));
        $goalType = sanitize_text_field($request->get_param('goal_type') ?: 'page_view');
        $variants = $request->get_param('variants') ?: [];

        if (empty($postId) || empty($testName) || count($variants) < 2) {
            return ['success' => false, 'message' => __('Post, test name, and at least 2 variants required.', 'ai-seo-client')];
        }

        $wpdb->insert($this->testsTable, [
            'post_id' => $postId,
            'test_name' => $testName,
            'goal_type' => $goalType,
            'status' => 'active',
        ]);
        $testId = $wpdb->insert_id;

        foreach ($variants as $v) {
            $wpdb->insert($this->variantsTable, [
                'test_id' => $testId,
                'name' => sanitize_text_field($v['name']),
                'title' => sanitize_text_field($v['title'] ?? null) ?: null,
                'content' => wp_kses_post($v['content'] ?? null) ?: null,
                'meta_description' => sanitize_textarea_field($v['meta_description'] ?? null) ?: null,
                'traffic_split' => (int) ($v['traffic_split'] ?? 50),
            ]);
        }

        return ['success' => true, 'id' => $testId];
    }

    public function restDeleteTest(\WP_REST_Request $request): array
    {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $wpdb->delete($this->variantsTable, ['test_id' => $id]);
        $wpdb->delete($this->conversionsTable, ['test_id' => $id]);
        $wpdb->delete($this->testsTable, ['id' => $id]);

        return ['success' => true];
    }

    public function restEndTest(\WP_REST_Request $request): array
    {
        global $wpdb;
        $id = (int) $request->get_param('id');

        $wpdb->update($this->testsTable, [
            'status' => 'ended',
            'ended_at' => current_time('mysql'),
        ], ['id' => $id]);

        return ['success' => true];
    }

    public function restGetStats(\WP_REST_Request $request): array
    {
        global $wpdb;
        $testId = (int) $request->get_param('id');

        $variants = $this->getVariants($testId);
        $totalImpressions = array_sum(array_column($variants, 'impressions'));
        $totalConversions = array_sum(array_column($variants, 'conversions'));

        foreach ($variants as &$v) {
            $v['conversion_rate'] = $v['impressions'] > 0
                ? round(($v['conversions'] / $v['impressions']) * 100, 2)
                : 0;
        }

        return [
            'variants' => $variants,
            'total_impressions' => (int) $totalImpressions,
            'total_conversions' => (int) $totalConversions,
            'overall_rate' => $totalImpressions > 0
                ? round(($totalConversions / $totalImpressions) * 100, 2)
                : 0,
        ];
    }

    public function restRecordConversion(\WP_REST_Request $request): array
    {
        global $wpdb;

        $testId = (int) $request->get_param('test_id');
        $variantId = (int) $request->get_param('variant_id');
        $type = sanitize_text_field($request->get_param('type') ?: 'page_view');
        $sessionId = $this->getSessionId();

        if (empty($testId) || empty($variantId)) {
            return ['success' => false];
        }

        // Rate limit: max 30 conversion requests per minute per IP to prevent
        // fake conversion flooding that would skew A/B test results.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rlKey = 'sseo_ab_rl_' . md5($ip);
        $count = (int) get_transient($rlKey);
        if ($count >= 30) {
            return ['success' => false, 'error' => 'rate_limited'];
        }
        $count === 0 ? set_transient($rlKey, 1, 60) : set_transient($rlKey, $count + 1, 60);

        // Validate that the variant belongs to an active test.
        $variant = $wpdb->get_row($wpdb->prepare(
            "SELECT v.id, t.status FROM {$this->variantsTable} v
             JOIN {$this->testsTable} t ON t.id = v.test_id
             WHERE v.id = %d AND v.test_id = %d AND t.status = 'active'
             LIMIT 1",
            $variantId, $testId
        ), ARRAY_A);

        if (!$variant) {
            return ['success' => false];
        }

        // Only count one conversion per session per type
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->conversionsTable}
             WHERE variant_id = %d AND session_id = %s AND conversion_type = %s
             LIMIT 1",
            $variantId, $sessionId, $type
        ));

        if (!$exists) {
            $wpdb->insert($this->conversionsTable, [
                'variant_id' => $variantId,
                'test_id' => $testId,
                'session_id' => $sessionId,
                'conversion_type' => $type,
            ]);

            // Update variant counter
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->variantsTable} SET conversions = conversions + 1 WHERE id = %d",
                $variantId
            ));
        }

        return ['success' => true];
    }

    /**
     * Render admin page
     */
    public function renderPage(): void
    {
        $posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1, 'post_status' => 'publish']);
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('A/B Testing', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Test page variants and track conversions.', 'ai-seo-client'); ?></p>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Create New Test', 'ai-seo-client'); ?></h2>
                    <form id="ab-test-form">
                        <table class="form-table">
                            <tr>
                                <th><label for="ab-post"><?php esc_html_e('Post', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <select id="ab-post" required style="min-width: 300px;">
                                        <option value=""><?php esc_html_e('Select a post...', 'ai-seo-client'); ?></option>
                                        <?php foreach ($posts as $p): ?>
                                            <option value="<?php echo esc_attr($p->ID); ?>"><?php echo esc_html($p->post_title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="ab-name"><?php esc_html_e('Test Name', 'ai-seo-client'); ?></label></th>
                                <td><input type="text" id="ab-name" required style="min-width: 300px;" placeholder="e.g. Headline Test March 2026"></td>
                            </tr>
                            <tr>
                                <th><label for="ab-goal"><?php esc_html_e('Goal', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <select id="ab-goal">
                                        <option value="page_view"><?php esc_html_e('Page View', 'ai-seo-client'); ?></option>
                                        <option value="click"><?php esc_html_e('Link/Button Click', 'ai-seo-client'); ?></option>
                                        <option value="form_submit"><?php esc_html_e('Form Submit', 'ai-seo-client'); ?></option>
                                        <option value="time_on_page"><?php esc_html_e('Time on Page (30s)', 'ai-seo-client'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <h3><?php esc_html_e('Variant A (Control)', 'ai-seo-client'); ?></h3>
                        <table class="form-table">
                            <tr><th>Title</th><td><input type="text" id="ab-v0-title" style="width:100%" placeholder="Leave empty to use original"></td></tr>
                            <tr><th>Meta Description</th><td><input type="text" id="ab-v0-meta" style="width:100%" placeholder="Leave empty to use original"></td></tr>
                            <tr><th>Content Override</th><td><textarea id="ab-v0-content" rows="4" style="width:100%" placeholder="Leave empty to use original content"></textarea></td></tr>
                            <tr><th>Traffic %</th><td><input type="number" id="ab-v0-split" value="50" min="1" max="99"></td></tr>
                        </table>

                        <h3><?php esc_html_e('Variant B', 'ai-seo-client'); ?></h3>
                        <table class="form-table">
                            <tr><th>Title</th><td><input type="text" id="ab-v1-title" style="width:100%" placeholder="e.g. New compelling headline"></td></tr>
                            <tr><th>Meta Description</th><td><input type="text" id="ab-v1-meta" style="width:100%" placeholder="e.g. New meta description"></td></tr>
                            <tr><th>Content Override</th><td><textarea id="ab-v1-content" rows="4" style="width:100%" placeholder="Override content for variant B"></textarea></td></tr>
                            <tr><th>Traffic %</th><td><input type="number" id="ab-v1-split" value="50" min="1" max="99"></td></tr>
                        </table>

                        <p><button type="submit" class="button button-primary"><?php esc_html_e('Create Test', 'ai-seo-client'); ?></button></p>
                    </form>
                </div>

                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Active & Past Tests', 'ai-seo-client'); ?></h2>
                    <div id="ab-tests-list">Loading...</div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function loadTests() {
                wp.apiFetch({ path: 'sseo-ai/v1/ab-tests' }).then(function(res) {
                    var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>' +
                        '<th>Post</th><th>Test</th><th>Goal</th><th>Status</th><th>Variants</th><th>Actions</th>' +
                        '</tr></thead><tbody>';
                    if (!res.tests.length) {
                        html += '<tr><td colspan="6" style="text-align:center;color:#999;">No tests yet.</td></tr>';
                    } else {
                        res.tests.forEach(function(t) {
                            var statusClass = t.status === 'active' ? 'background:#d1e7dd;color:#0f5132;' : 'background:#f8d7da;color:#842029;';
                            var variantsHtml = '';
                            if (t.variants) {
                                t.variants.forEach(function(v) {
                                    variantsHtml += '<div style="margin-bottom:4px;"><strong>' + $('<span>').text(v.name).html() + '</strong> (' + v.traffic_split + '%)</div>';
                                });
                            }
                            html += '<tr>' +
                                '<td>' + $('<span>').text(t.post_title).html() + '</td>' +
                                '<td>' + $('<span>').text(t.test_name).html() + '</td>' +
                                '<td>' + t.goal_type + '</td>' +
                                '<td><span style="padding:2px 8px;border-radius:4px;font-size:12px;font-weight:bold;' + statusClass + '">' + t.status + '</span></td>' +
                                '<td>' + variantsHtml + '</td>' +
                                '<td>' +
                                    '<button class="button ab-stats" data-id="' + t.id + '">Stats</button> ' +
                                    (t.status === 'active' ? '<button class="button ab-end" data-id="' + t.id + '" style="color:#00a32a;">End</button> ' : '') +
                                    '<button class="button ab-delete" data-id="' + t.id + '" style="color:#d63638;">Delete</button>' +
                                '</td>' +
                                '</tr>';
                        });
                    }
                    html += '</tbody></table>';
                    $('#ab-tests-list').html(html);
                });
            }

            $('#ab-test-form').on('submit', function(e) {
                e.preventDefault();
                var btn = $(this).find('button[type="submit"]');
                btn.prop('disabled', true).text('Creating...');

                var variants = [];
                for (var i = 0; i < 2; i++) {
                    var v = {
                        name: i === 0 ? 'Control' : 'Variant B',
                        title: $('#ab-v' + i + '-title').val() || null,
                        meta_description: $('#ab-v' + i + '-meta').val() || null,
                        content: $('#ab-v' + i + '-content').val() || null,
                        traffic_split: parseInt($('#ab-v' + i + '-split').val()) || 50,
                    };
                    if (v.title || v.meta_description || v.content) {
                        variants.push(v);
                    }
                }

                wp.apiFetch({
                    path: 'sseo-ai/v1/ab-tests',
                    method: 'POST',
                    data: {
                        post_id: parseInt($('#ab-post').val()),
                        test_name: $('#ab-name').val(),
                        goal_type: $('#ab-goal').val(),
                        variants: variants,
                    }
                }).then(function(res) {
                    btn.prop('disabled', false).text('Create Test');
                    if (res.success) {
                        $('#ab-test-form')[0].reset();
                        loadTests();
                    } else {
                        alert(res.message || 'Failed');
                    }
                }).catch(function(err) {
                    btn.prop('disabled', false).text('Create Test');
                    alert(err.message || 'Failed');
                });
            });

            $(document).on('click', '.ab-end', function() {
                if (!confirm('End this test?')) return;
                var id = $(this).data('id');
                wp.apiFetch({ path: 'sseo-ai/v1/ab-tests/' + id + '/end', method: 'POST' }).then(loadTests);
            });

            $(document).on('click', '.ab-delete', function() {
                if (!confirm('Delete this test and all data?')) return;
                var id = $(this).data('id');
                wp.apiFetch({ path: 'sseo-ai/v1/ab-tests/' + id, method: 'DELETE' }).then(loadTests);
            });

            $(document).on('click', '.ab-stats', function() {
                var id = $(this).data('id');
                wp.apiFetch({ path: 'sseo-ai/v1/ab-tests/' + id + '/stats' }).then(function(res) {
                    var html = '<h3>Results</h3>';
                    html += '<table class="wp-list-table widefat fixed striped"><thead><tr>' +
                        '<th>Variant</th><th>Impressions</th><th>Conversions</th><th>Conversion Rate</th>' +
                        '</tr></thead><tbody>';
                    res.variants.forEach(function(v) {
                        html += '<tr>' +
                            '<td>' + $('<span>').text(v.name).html() + '</td>' +
                            '<td>' + (v.impressions || 0) + '</td>' +
                            '<td>' + (v.conversions || 0) + '</td>' +
                            '<td><strong>' + (v.conversion_rate || 0) + '%</strong></td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';
                    html += '<p><strong>Total impressions:</strong> ' + res.total_impressions + ' | <strong>Total conversions:</strong> ' + res.total_conversions + ' | <strong>Overall rate:</strong> ' + res.overall_rate + '%</p>';
                    alert(html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim());
                });
            });

            loadTests();
        });
        </script>
        <?php
    }
}

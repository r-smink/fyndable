<?php

namespace SSEOAIClient;

/**
 * SERP Change Monitor with Auto-Update
 *
 * Monitors ranking changes and automatically triggers content updates
 * when positions drop significantly. Integrates with RankTracker and ContentDecay.
 *
 * Comparable to Semrush Position Tracking, Ahrefs Rank Tracker alerts.
 */
class SerpChangeMonitor
{
    private Settings $settings;
    private LlmClient $llm;

    public function __construct(Settings $settings, LlmClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'addMenu']);
        // Hook after rank check runs
        add_action('sseo_ai_rank_check_cron', [$this, 'checkForSignificantChanges'], 20);
        // Custom cron for auto-update processing
        if (!wp_next_scheduled('sseo_ai_serp_auto_update')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_serp_auto_update');
        }
        add_action('sseo_ai_serp_auto_update', [$this, 'processAutoUpdates']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('SERP Monitor', 'ai-seo-client'),
            __('SERP Monitor', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-serp-monitor',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/serp-monitor/changes', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetChanges'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/serp-monitor/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveSettings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/serp-monitor/auto-update', [
            'methods' => 'POST',
            'callback' => [$this, 'restTriggerAutoUpdate'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    private const SETTINGS_KEY = 'sseo_ai_serp_monitor_settings';
    private const CHANGES_KEY = 'sseo_ai_serp_changes';
    private const QUEUE_KEY = 'sseo_ai_serp_auto_update_queue';

    public function getSettings(): array
    {
        $defaults = [
            'enabled' => false,
            'drop_threshold' => 5, // positions
            'auto_update' => false,
            'auto_update_threshold' => 10, // only auto-update if dropped 10+ positions
            'notify_email' => true,
            'min_days_between_updates' => 14, // don't auto-update same post more than once per 14 days
        ];
        $saved = get_option(self::SETTINGS_KEY, []);
        if (!is_array($saved)) $saved = [];
        return array_merge($defaults, $saved);
    }

    private function saveSettings(array $settings): array
    {
        $sanitized = [
            'enabled' => (bool)($settings['enabled'] ?? false),
            'drop_threshold' => (int)($settings['drop_threshold'] ?? 5),
            'auto_update' => (bool)($settings['auto_update'] ?? false),
            'auto_update_threshold' => (int)($settings['auto_update_threshold'] ?? 10),
            'notify_email' => (bool)($settings['notify_email'] ?? true),
            'min_days_between_updates' => (int)($settings['min_days_between_updates'] ?? 14),
        ];
        update_option(self::SETTINGS_KEY, $sanitized);
        return $sanitized;
    }

    /**
     * Check for significant SERP changes after rank check.
     */
    public function checkForSignificantChanges(): void
    {
        $settings = $this->getSettings();
        if (empty($settings['enabled'])) return;

        global $wpdb;
        $keywordsTable = $wpdb->prefix . 'sseo_ai_keywords';

        $keywords = $wpdb->get_results(
            "SELECT * FROM {$keywordsTable} WHERE active = 1 AND previous_position > 0 AND current_position > 0",
            ARRAY_A
        );

        $changes = get_option(self::CHANGES_KEY, []);
        if (!is_array($changes)) $changes = [];

        $newChanges = [];
        $threshold = (int)$settings['drop_threshold'];

        foreach ($keywords as $kw) {
            $prev = (int)$kw['previous_position'];
            $curr = (int)$kw['current_position'];
            $drop = $curr - $prev; // positive = dropped down

            if ($drop >= $threshold) {
                $changeId = 'change_' . $kw['id'] . '_' . date('Y-m-d');
                if (isset($changes[$changeId])) continue; // already recorded

                $change = [
                    'id' => $changeId,
                    'keyword_id' => $kw['id'],
                    'keyword' => $kw['keyword'],
                    'post_id' => $kw['post_id'] ?? 0,
                    'previous_position' => $prev,
                    'current_position' => $curr,
                    'drop' => $drop,
                    'detected_at' => current_time('mysql'),
                    'status' => 'new',
                    'auto_updated' => false,
                ];

                $newChanges[$changeId] = $change;

                // Queue for auto-update if enabled and drop is severe enough
                if (!empty($settings['auto_update']) && $drop >= (int)$settings['auto_update_threshold'] && !empty($kw['post_id'])) {
                    $this->queueAutoUpdate($kw['post_id'], $kw['keyword'], $changeId);
                    $change['auto_updated'] = true;
                    $newChanges[$changeId] = $change;
                }
            }
        }

        if (!empty($newChanges)) {
            $changes = array_merge($changes, $newChanges);
            // Keep only last 100 changes
            if (count($changes) > 100) {
                $changes = array_slice($changes, -100, null, true);
            }
            update_option(self::CHANGES_KEY, $changes);

            // Send email notification
            if (!empty($settings['notify_email'])) {
                $this->sendNotificationEmail($newChanges);
            }
        }
    }

    /**
     * Queue a post for auto-update.
     */
    private function queueAutoUpdate(int $postId, string $keyword, string $changeId): void
    {
        $queue = get_option(self::QUEUE_KEY, []);
        if (!is_array($queue)) $queue = [];

        $settings = $this->getSettings();
        $minDays = (int)$settings['min_days_between_updates'];

        // Check if this post was recently auto-updated
        $lastUpdated = get_post_meta($postId, '_sseo_ai_last_auto_update', true);
        if ($lastUpdated && (time() - strtotime($lastUpdated)) < ($minDays * DAY_IN_SECONDS)) {
            return; // Too soon
        }

        $queue[] = [
            'post_id' => $postId,
            'keyword' => $keyword,
            'change_id' => $changeId,
            'queued_at' => current_time('mysql'),
        ];

        update_option(self::QUEUE_KEY, $queue);
    }

    /**
     * Process queued auto-updates via WP-Cron.
     */
    public function processAutoUpdates(): void
    {
        $queue = get_option(self::QUEUE_KEY, []);
        if (!is_array($queue) || empty($queue)) return;

        $settings = $this->getSettings();
        if (empty($settings['auto_update'])) return;

        $processed = 0;
        $maxPerRun = 3; // Limit per cron run
        $remaining = [];

        foreach ($queue as $item) {
            if ($processed >= $maxPerRun) {
                $remaining[] = $item;
                continue;
            }

            $postId = (int)$item['post_id'];
            $keyword = $item['keyword'];
            $post = get_post($postId);

            if (!$post) continue;

            // Generate updated content using AI
            $updatedContent = $this->generateContentUpdate($post, $keyword);

            if (!is_wp_error($updatedContent)) {
                wp_update_post([
                    'ID' => $postId,
                    'post_content' => $updatedContent['content'],
                ]);

                if (!empty($updatedContent['meta_description'])) {
                    update_post_meta($postId, '_sseo_ai_description', $updatedContent['meta_description']);
                }

                update_post_meta($postId, '_sseo_ai_last_auto_update', current_time('mysql'));
                update_post_meta($postId, '_sseo_ai_auto_update_count', (int)get_post_meta($postId, '_sseo_ai_auto_update_count', true) + 1);

                // Mark change as auto-updated
                $changes = get_option(self::CHANGES_KEY, []);
                if (isset($changes[$item['change_id']])) {
                    $changes[$item['change_id']]['status'] = 'auto_updated';
                    $changes[$item['change_id']]['auto_updated_at'] = current_time('mysql');
                    update_option(self::CHANGES_KEY, $changes);
                }

                $processed++;
            }
        }

        update_option(self::QUEUE_KEY, $remaining);
    }

    /**
     * Generate content update using AI.
     */
    private function generateContentUpdate(\WP_Post $post, string $keyword): array|\WP_Error
    {
        $content = $post->post_content;
        $plainContent = substr(wp_strip_all_tags($content), 0, 6000);

        $prompt = <<<PROMPT
This article is losing rankings for "{$keyword}". Refresh and improve the content to regain positions.

Current content:
---
{$plainContent}
---

Requirements:
1. Add fresh, up-to-date information
2. Improve content depth and comprehensiveness
3. Better satisfy search intent for "{$keyword}"
4. Add new relevant sections if needed
5. Improve the FAQ section
6. Keep the same HTML structure (headings, paragraphs)
7. Maintain or improve keyword density naturally

Return JSON:
{
    "content": "Full updated HTML content",
    "meta_description": "Updated meta description (150-160 chars)",
    "changes_summary": "Brief summary of what was changed"
}

Return ONLY the JSON.
PROMPT;

        $response = $this->llm->generateText($prompt, [
            'use_case' => 'content_generation',
            'max_tokens' => 4000,
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(trim($response), true);
        if (!is_array($data) || !isset($data['content'])) {
            return new \WP_Error('parse_error', __('Could not parse AI response', 'ai-seo-client'));
        }

        return $data;
    }

    /**
     * Send email notification for significant changes.
     */
    private function sendNotificationEmail(array $changes): void
    {
        $to = get_option('admin_email');
        $subject = sprintf(__('[%s] SERP Position Changes Detected', 'ai-seo-client'), get_bloginfo('name'));

        $body = __("The following significant ranking changes were detected:\n\n", 'ai-seo-client');
        foreach ($changes as $change) {
            $body .= sprintf(
                "- %s: dropped from #%d to #%d (%d positions)\n",
                $change['keyword'],
                $change['previous_position'],
                $change['current_position'],
                $change['drop']
            );
        }
        $body .= "\n" . sprintf(__("View details: %s", 'ai-seo-client'), admin_url('admin.php?page=ai-seo-serp-monitor'));

        wp_mail($to, $subject, $body);
    }

    // REST handlers
    public function restGetChanges(): array
    {
        $changes = get_option(self::CHANGES_KEY, []);
        $queue = get_option(self::QUEUE_KEY, []);
        return [
            'changes' => array_values(array_reverse($changes)),
            'queue_count' => is_array($queue) ? count($queue) : 0,
            'settings' => $this->getSettings(),
        ];
    }

    public function restSaveSettings(\WP_REST_Request $request): array
    {
        return ['success' => true, 'settings' => $this->saveSettings($request->get_params())];
    }

    public function restTriggerAutoUpdate(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int)($request->get_param('post_id') ?: 0);
        $keyword = sanitize_text_field($request->get_param('keyword') ?? '');
        if (!$postId) return new \WP_Error('missing', __('post_id required', 'ai-seo-client'), ['status' => 400]);

        $post = get_post($postId);
        if (!$post) return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'), ['status' => 404]);

        $result = $this->generateContentUpdate($post, $keyword);
        if (is_wp_error($result)) return $result;

        wp_update_post([
            'ID' => $postId,
            'post_content' => $result['content'],
        ]);

        if (!empty($result['meta_description'])) {
            update_post_meta($postId, '_sseo_ai_description', $result['meta_description']);
        }

        update_post_meta($postId, '_sseo_ai_last_auto_update', current_time('mysql'));

        return [
            'success' => true,
            'changes_summary' => $result['changes_summary'] ?? '',
            'edit_url' => get_edit_post_link($postId, ''),
        ];
    }

    public function renderPage(): void
    {
        ?>
        <style>
            .sm-wrap { max-width: 900px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; margin-bottom: 20px; }
            .sm-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 20px 30px; border-radius: 12px 12px 0 0; margin: -30px -30px 20px -30px; }
            .sm-header h1 { margin: 0; font-size: 22px; }
            .sm-header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 13px; }
            .sm-change { padding: 12px; margin: 8px 0; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
            .sm-change.new { background: #fee2e2; }
            .sm-change.auto_updated { background: #d1e7dd; }
            .sm-change.acknowledged { background: #f1f5f9; }
        </style>
        <div class="wrap sm-wrap">
            <div class="sm-card">
                <div class="sm-header">
                    <h1>📊 <?php esc_html_e('SERP Change Monitor', 'ai-seo-client'); ?></h1>
                    <p><?php esc_html_e('Monitor ranking changes and auto-update content when positions drop.', 'ai-seo-client'); ?></p>
                </div>

                <div id="sm-settings" style="margin-bottom:20px;padding:15px;background:#f8fafc;border-radius:8px;">
                    <h4><?php esc_html_e('Settings', 'ai-seo-client'); ?></h4>
                    <div style="display:flex;gap:20px;flex-wrap:wrap;">
                        <label><input type="checkbox" id="sm-enabled"> <?php esc_html_e('Enable monitoring', 'ai-seo-client'); ?></label>
                        <label><input type="checkbox" id="sm-auto-update"> <?php esc_html_e('Auto-update content on severe drops', 'ai-seo-client'); ?></label>
                        <label><input type="checkbox" id="sm-email"> <?php esc_html_e('Email notifications', 'ai-seo-client'); ?></label>
                    </div>
                    <div style="display:flex;gap:15px;margin-top:10px;flex-wrap:wrap;">
                        <label><?php esc_html_e('Drop threshold:', 'ai-seo-client'); ?> <input type="number" id="sm-threshold" value="5" min="1" style="width:60px;"> <?php esc_html_e('positions', 'ai-seo-client'); ?></label>
                        <label><?php esc_html_e('Auto-update threshold:', 'ai-seo-client'); ?> <input type="number" id="sm-auto-threshold" value="10" min="1" style="width:60px;"> <?php esc_html_e('positions', 'ai-seo-client'); ?></label>
                    </div>
                    <button class="button button-primary" id="sm-save-settings" style="margin-top:10px;"><?php esc_html_e('Save Settings', 'ai-seo-client'); ?></button>
                </div>

                <h3><?php esc_html_e('Recent Changes', 'ai-seo-client'); ?></h3>
                <div id="sm-changes"><p style="color:#666;"><?php esc_html_e('Loading...', 'ai-seo-client'); ?></p></div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            function loadSettings() {
                wp.apiFetch({ path: '/sseo-ai/v1/serp-monitor/changes' }).then(function(data) {
                    var s = data.settings;
                    $('#sm-enabled').prop('checked', s.enabled);
                    $('#sm-auto-update').prop('checked', s.auto_update);
                    $('#sm-email').prop('checked', s.notify_email);
                    $('#sm-threshold').val(s.drop_threshold);
                    $('#sm-auto-threshold').val(s.auto_update_threshold);

                    var html = '';
                    if (!data.changes.length) {
                        html = '<p style="color:#666;">🎉 <?php echo esc_js(__("No significant changes detected.", "ai-seo-client")); ?></p>';
                    } else {
                        data.changes.forEach(function(c) {
                            var statusLabel = c.status === 'auto_updated' ? '✅ <?php echo esc_js(__("Auto-updated", "ai-seo-client")); ?>' : (c.status === 'acknowledged' ? '👁️ Acknowledged' : '⚠️ New');
                            html += '<div class="sm-change ' + c.status + '">';
                            html += '<div><strong>' + c.keyword + '</strong><div style="font-size:12px;color:#64748b;">' + c.detected_at + '</div></div>';
                            html += '<div style="text-align:center;"><span style="font-size:18px;font-weight:700;color:#dc2626;">#' + c.current_position + '</span> <span style="color:#64748b;">was #' + c.previous_position + '</span></div>';
                            html += '<div>' + statusLabel;
                            if (c.post_id && c.status === 'new') {
                                html += '<br><button class="button button-small sm-update-btn" data-post-id="' + c.post_id + '" data-keyword="' + c.keyword + '" style="margin-top:5px;">' + '<?php echo esc_js(__("Auto-Update Now", "ai-seo-client")); ?>' + '</button>';
                            }
                            html += '</div>';
                            html += '</div>';
                        });
                    }
                    if (data.queue_count > 0) {
                        html += '<p style="margin-top:10px;color:#d97706;">' + data.queue_count + ' <?php echo esc_js(__("updates queued for processing", "ai-seo-client")); ?></p>';
                    }
                    $('#sm-changes').html(html);
                });
            }
            loadSettings();

            $('#sm-save-settings').on('click', function() {
                wp.apiFetch({
                    path: '/sseo-ai/v1/serp-monitor/settings',
                    method: 'POST',
                    data: {
                        enabled: $('#sm-enabled').is(':checked'),
                        auto_update: $('#sm-auto-update').is(':checked'),
                        notify_email: $('#sm-email').is(':checked'),
                        drop_threshold: parseInt($('#sm-threshold').val(), 10) || 5,
                        auto_update_threshold: parseInt($('#sm-auto-threshold').val(), 10) || 10,
                    }
                }).then(function() {
                    alert('<?php echo esc_js(__("Settings saved!", "ai-seo-client")); ?>');
                });
            });

            $(document).on('click', '.sm-update-btn', function() {
                var btn = $(this);
                var postId = btn.data('post-id');
                var keyword = btn.data('keyword');
                btn.prop('disabled', true).text('<?php echo esc_js(__("Updating...", "ai-seo-client")); ?>');
                wp.apiFetch({
                    path: '/sseo-ai/v1/serp-monitor/auto-update',
                    method: 'POST',
                    data: { post_id: postId, keyword: keyword }
                }).then(function(res) {
                    alert('<?php echo esc_js(__("Content updated! Summary:", "ai-seo-client")); ?> ' + res.changes_summary);
                    loadSettings();
                }).catch(function(err) {
                    alert(err.message || 'Update failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__("Auto-Update Now", "ai-seo-client")); ?>');
                });
            });
        });
        </script>
        <?php
    }
}

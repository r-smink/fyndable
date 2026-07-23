<?php

namespace SSEOAIClient;

/**
 * Bulk AI Actions
 * 
 * Provides bulk AI-powered SEO actions: generate meta titles, descriptions,
 * OG tags, and alt texts for multiple posts at once. Includes an admin page
 * with progress tracking and a post list column for quick status overview.
 * Comparable to WP SEO AI Bulk, Akii Bulk Optimizer.
 */
class BulkActions
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
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_filter('manage_posts_columns', [$this, 'addSeoColumn']);
        add_action('manage_posts_custom_column', [$this, 'renderSeoColumn'], 10, 2);
        add_filter('manage_pages_columns', [$this, 'addSeoColumn']);
        add_action('manage_pages_custom_column', [$this, 'renderSeoColumn'], 10, 2);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Bulk Optimizer', 'ai-seo-client'),
            __('Bulk Optimizer', 'ai-seo-client'),
            'manage_options',
            'ai-seo-bulk',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/bulk/scan', [
            'methods' => 'GET',
            'callback' => [$this, 'restScanPosts'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/bulk/generate-meta', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateMeta'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
                'fields' => ['type' => 'array', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/bulk/generate-batch', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateBatch'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'post_ids' => ['type' => 'array', 'required' => true],
                'fields' => ['type' => 'array', 'required' => true],
            ],
        ]);
    }

    /**
     * Scan all posts and return SEO status summary
     */
    public function restScanPosts(\WP_REST_Request $request): array
    {
        $postTypes = get_post_types(['public' => true]);
        unset($postTypes['attachment']);

        $posts = get_posts([
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $results = [];
        $summary = [
            'total' => count($posts),
            'missing_title' => 0,
            'missing_description' => 0,
            'missing_og' => 0,
            'missing_keyphrase' => 0,
            'short_content' => 0,
        ];

        foreach ($posts as $post) {
            $seoTitle = get_post_meta($post->ID, '_sseo_ai_title', true);
            $seoDesc = get_post_meta($post->ID, '_sseo_ai_description', true);
            $ogTitle = get_post_meta($post->ID, '_sseo_ai_og_title', true);
            $ogDesc = get_post_meta($post->ID, '_sseo_ai_og_description', true);
            $keyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
            $wordCount = str_word_count(wp_strip_all_tags($post->post_content));

            $issues = [];
            if (empty($seoTitle)) {
                $issues[] = 'missing_title';
                $summary['missing_title']++;
            }
            if (empty($seoDesc)) {
                $issues[] = 'missing_description';
                $summary['missing_description']++;
            }
            if (empty($ogTitle) && empty($ogDesc)) {
                $issues[] = 'missing_og';
                $summary['missing_og']++;
            }
            if (empty($keyphrase)) {
                $issues[] = 'missing_keyphrase';
                $summary['missing_keyphrase']++;
            }
            if ($wordCount < 300) {
                $issues[] = 'short_content';
                $summary['short_content']++;
            }

            $results[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'url' => get_permalink($post->ID),
                'edit_url' => get_edit_post_link($post->ID, ''),
                'word_count' => $wordCount,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDesc,
                'og_title' => $ogTitle,
                'og_description' => $ogDesc,
                'keyphrase' => $keyphrase,
                'issues' => $issues,
                'has_thumbnail' => has_post_thumbnail($post->ID),
            ];
        }

        return [
            'summary' => $summary,
            'posts' => $results,
        ];
    }

    /**
     * Generate SEO meta for a single post
     */
    public function restGenerateMeta(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) $request->get_param('post_id');
        $fields = $request->get_param('fields');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'));
        }

        return $this->generateMetaForPost($post, $fields);
    }

    /**
     * Generate SEO meta for a batch of posts (one at a time, returns results)
     */
    public function restGenerateBatch(\WP_REST_Request $request): array
    {
        $postIds = $request->get_param('post_ids');
        $fields = $request->get_param('fields');
        $results = [];

        // Process one post at a time to stay within rate limits
        $postId = (int) array_shift($postIds);
        $post = get_post($postId);

        if (!$post) {
            return [
                'processed' => $postId,
                'success' => false,
                'error' => __('Post not found', 'ai-seo-client'),
                'remaining' => $postIds,
            ];
        }

        $result = $this->generateMetaForPost($post, $fields);

        return [
            'processed' => $postId,
            'success' => !is_wp_error($result),
            'data' => is_wp_error($result) ? null : $result,
            'error' => is_wp_error($result) ? $result->get_error_message() : null,
            'remaining' => $postIds,
        ];
    }

    /**
     * Generate meta fields for a single post
     */
    private function generateMetaForPost(\WP_Post $post, array $fields): array|\WP_Error
    {
        $content = wp_strip_all_tags($post->post_content);
        $title = $post->post_title;
        $keyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
        $contentExcerpt = mb_substr($content, 0, 800);

        $fieldList = implode(', ', $fields);

        $prompt = "Generate optimized SEO metadata for this article.

Title: \"{$title}\"
" . ($keyphrase ? "Focus Keyword: \"{$keyphrase}\"\n" : "") . "
Content excerpt: {$contentExcerpt}

Generate ONLY the following fields: {$fieldList}

Rules:
- seo_title: Max 60 characters, include keyword, compelling for clicks
- seo_description: Max 155 characters, include keyword, clear call to action
- og_title: Max 95 characters, engaging for social sharing
- og_description: Max 200 characters, compelling for social clicks
- focus_keyphrase: 2-4 words, the main topic/keyword of the article

Return as JSON (no markdown, no code blocks):
{" . (in_array('seo_title', $fields) ? '"seo_title": "...",' : '')
   . (in_array('seo_description', $fields) ? '"seo_description": "...",' : '')
   . (in_array('og_title', $fields) ? '"og_title": "...",' : '')
   . (in_array('og_description', $fields) ? '"og_description": "...",' : '')
   . (in_array('focus_keyphrase', $fields) ? '"focus_keyphrase": "...",' : '') . "
}

Return ONLY the JSON.";

        $result = $this->llm->call($prompt, null, null, 500);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);
        if (!$data) {
            return new \WP_Error('parse_error', __('Failed to parse AI response', 'ai-seo-client'));
        }

        // Save to post meta
        $saved = [];
        if (!empty($data['seo_title']) && in_array('seo_title', $fields)) {
            update_post_meta($post->ID, '_sseo_ai_title', sanitize_text_field($data['seo_title']));
            $saved['seo_title'] = $data['seo_title'];
        }
        if (!empty($data['seo_description']) && in_array('seo_description', $fields)) {
            update_post_meta($post->ID, '_sseo_ai_description', sanitize_text_field($data['seo_description']));
            $saved['seo_description'] = $data['seo_description'];
        }
        if (!empty($data['og_title']) && in_array('og_title', $fields)) {
            update_post_meta($post->ID, '_sseo_ai_og_title', sanitize_text_field($data['og_title']));
            $saved['og_title'] = $data['og_title'];
        }
        if (!empty($data['og_description']) && in_array('og_description', $fields)) {
            update_post_meta($post->ID, '_sseo_ai_og_description', sanitize_text_field($data['og_description']));
            $saved['og_description'] = $data['og_description'];
        }
        if (!empty($data['focus_keyphrase']) && in_array('focus_keyphrase', $fields)) {
            update_post_meta($post->ID, '_sseo_ai_focus_keyphrase', sanitize_text_field($data['focus_keyphrase']));
            $saved['focus_keyphrase'] = $data['focus_keyphrase'];
        }

        return [
            'post_id' => $post->ID,
            'generated' => $saved,
        ];
    }

    /**
     * Add SEO status column to post list
     */
    public function addSeoColumn(array $columns): array
    {
        $columns['aiseo_status'] = __('SEO', 'ai-seo-client');
        return $columns;
    }

    /**
     * Render SEO status in post list column
     */
    public function renderSeoColumn(string $column, int $postId): void
    {
        if ($column !== 'aiseo_status') {
            return;
        }

        $seoTitle = get_post_meta($postId, '_sseo_ai_title', true);
        $seoDesc = get_post_meta($postId, '_sseo_ai_description', true);
        $keyphrase = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);

        $score = 0;
        if ($seoTitle) $score++;
        if ($seoDesc) $score++;
        if ($keyphrase) $score++;

        $colors = ['#d63638', '#dba617', '#dba617', '#00a32a'];
        $labels = [
            __('Missing', 'ai-seo-client'),
            __('Basic', 'ai-seo-client'),
            __('Good', 'ai-seo-client'),
            __('Complete', 'ai-seo-client'),
        ];

        printf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:%s;color:#fff;font-size:11px;font-weight:600;">%s</span>',
            esc_attr($colors[$score]),
            esc_html($labels[$score])
        );
    }

    /**
     * Render Bulk Optimizer admin page
     */
    public function renderPage(): void
    {
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('AI Bulk SEO Optimizer', 'ai-seo-client'); ?></h1>
            </div>

            <div class="sseo-ai-content">
                <!-- Summary Cards -->
                <div class="sseo-ai-dashboard-card" id="bulk-summary-card" style="display:none;">
                    <h2><?php esc_html_e('SEO Overview', 'ai-seo-client'); ?></h2>
                    <p style="color:#666; margin-top:0;"><?php esc_html_e('Scan your site for missing SEO data and bulk-generate meta titles, descriptions, and Open Graph tags using AI.', 'ai-seo-client'); ?></p>
                    <div id="bulk-summary">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:15px;">
                            <div style="padding:20px; text-align:center; background:#f0f6fc; border-radius:8px;">
                                <div style="font-size:36px; font-weight:bold; color:#2271b1; line-height:1.2;" id="bulk-total">0</div>
                                <div style="margin-top:10px; font-size:14px; color:#555;"><?php esc_html_e('Total Posts', 'ai-seo-client'); ?></div>
                            </div>
                            <div style="padding:20px; text-align:center; background:#f8d7da; border-radius:8px;">
                                <div style="font-size:36px; font-weight:bold; color:#d63638; line-height:1.2;" id="bulk-no-title">0</div>
                                <div style="margin-top:10px; font-size:14px; color:#555;"><?php esc_html_e('Missing Title', 'ai-seo-client'); ?></div>
                            </div>
                            <div style="padding:20px; text-align:center; background:#f8d7da; border-radius:8px;">
                                <div style="font-size:36px; font-weight:bold; color:#d63638; line-height:1.2;" id="bulk-no-desc">0</div>
                                <div style="margin-top:10px; font-size:14px; color:#555;"><?php esc_html_e('Missing Description', 'ai-seo-client'); ?></div>
                            </div>
                            <div style="padding:20px; text-align:center; background:#fff3cd; border-radius:8px;">
                                <div style="font-size:36px; font-weight:bold; color:#856404; line-height:1.2;" id="bulk-no-og">0</div>
                                <div style="margin-top:10px; font-size:14px; color:#555;"><?php esc_html_e('Missing OG Tags', 'ai-seo-client'); ?></div>
                            </div>
                            <div style="padding:20px; text-align:center; background:#fff3cd; border-radius:8px;">
                                <div style="font-size:36px; font-weight:bold; color:#856404; line-height:1.2;" id="bulk-no-kw">0</div>
                                <div style="margin-top:10px; font-size:14px; color:#555;"><?php esc_html_e('Missing Keyphrase', 'ai-seo-client'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Bulk Actions', 'ai-seo-client'); ?></h2>
                    <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                        <button type="button" class="button button-primary" id="bulk-scan">
                            <?php esc_html_e('Scan Site', 'ai-seo-client'); ?>
                        </button>

                        <select id="bulk-filter" style="display:none;">
                            <option value="all"><?php esc_html_e('Show All', 'ai-seo-client'); ?></option>
                            <option value="missing_title"><?php esc_html_e('Missing Title', 'ai-seo-client'); ?></option>
                            <option value="missing_description"><?php esc_html_e('Missing Description', 'ai-seo-client'); ?></option>
                            <option value="missing_og"><?php esc_html_e('Missing OG Tags', 'ai-seo-client'); ?></option>
                            <option value="missing_keyphrase"><?php esc_html_e('Missing Keyphrase', 'ai-seo-client'); ?></option>
                        </select>

                        <label style="display:none;" id="bulk-fields-label">
                            <strong><?php esc_html_e('Generate:', 'ai-seo-client'); ?></strong>
                        </label>
                        <label style="display:none;" class="bulk-field-cb"><input type="checkbox" value="seo_title" checked> <?php esc_html_e('SEO Title', 'ai-seo-client'); ?></label>
                        <label style="display:none;" class="bulk-field-cb"><input type="checkbox" value="seo_description" checked> <?php esc_html_e('Meta Description', 'ai-seo-client'); ?></label>
                        <label style="display:none;" class="bulk-field-cb"><input type="checkbox" value="og_title"> <?php esc_html_e('OG Title', 'ai-seo-client'); ?></label>
                        <label style="display:none;" class="bulk-field-cb"><input type="checkbox" value="og_description"> <?php esc_html_e('OG Description', 'ai-seo-client'); ?></label>
                        <label style="display:none;" class="bulk-field-cb"><input type="checkbox" value="focus_keyphrase"> <?php esc_html_e('Focus Keyphrase', 'ai-seo-client'); ?></label>

                        <button type="button" class="button button-primary" id="bulk-generate-selected" style="display:none;">
                            <?php esc_html_e('Generate for Selected', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="button" id="bulk-generate-all" style="display:none;">
                            <?php esc_html_e('Generate for All Missing', 'ai-seo-client'); ?>
                        </button>

                        <span class="spinner" id="bulk-spinner" style="float:none;"></span>
                    </div>

                    <!-- Progress bar -->
                    <div id="bulk-progress" style="display:none; margin-top:15px;">
                        <div style="background:#e0e0e0; border-radius:4px; height:24px; overflow:hidden;">
                            <div id="bulk-progress-bar" style="background:linear-gradient(90deg, #3b82f6, #FF4D00); height:100%; width:0%; transition:width 0.3s; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:600;"></div>
                        </div>
                        <p id="bulk-progress-text" style="color:#666; margin-top:5px;"></p>
                    </div>
                </div>

                <!-- Results Table -->
                <div class="sseo-ai-dashboard-card" id="bulk-results" style="display:none;">
                    <h2><?php esc_html_e('Posts Overview', 'ai-seo-client'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" id="bulk-select-all"></th>
                                <th><?php esc_html_e('Title', 'ai-seo-client'); ?></th>
                                <th style="width:80px;"><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                                <th style="width:70px;"><?php esc_html_e('Words', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('SEO Title', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Meta Description', 'ai-seo-client'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Issues', 'ai-seo-client'); ?></th>
                                <th style="width:100px;"><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="bulk-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var allPosts = [];

            function renderTable(posts) {
                var tbody = $('#bulk-table-body');
                tbody.empty();
                posts.forEach(function(p) {
                    var issueHtml = p.issues.map(function(i) {
                        var labels = {
                            missing_title: '<?php echo esc_js(__('No Title', 'ai-seo-client')); ?>',
                            missing_description: '<?php echo esc_js(__('No Desc', 'ai-seo-client')); ?>',
                            missing_og: '<?php echo esc_js(__('No OG', 'ai-seo-client')); ?>',
                            missing_keyphrase: '<?php echo esc_js(__('No KW', 'ai-seo-client')); ?>',
                            short_content: '<?php echo esc_js(__('Short', 'ai-seo-client')); ?>'
                        };
                        return '<span style="background:#fce4e4;color:#d63638;padding:1px 6px;border-radius:3px;font-size:11px;margin:1px;">' + (labels[i]||i) + '</span>';
                    }).join(' ');

                    tbody.append(
                        '<tr data-id="' + p.id + '">' +
                        '<td><input type="checkbox" class="bulk-cb" value="' + p.id + '"></td>' +
                        '<td><a href="' + p.edit_url + '" target="_blank">' + $('<span>').text(p.title).html() + '</a></td>' +
                        '<td>' + p.type + '</td>' +
                        '<td>' + p.word_count + '</td>' +
                        '<td class="seo-title-cell">' + (p.seo_title ? $('<span>').text(p.seo_title).html() : '<em style="color:#999;">Ã¢â‚¬â€</em>') + '</td>' +
                        '<td class="seo-desc-cell">' + (p.seo_description ? '<span style="font-size:12px;">' + $('<span>').text(p.seo_description).html() + '</span>' : '<em style="color:#999;">Ã¢â‚¬â€</em>') + '</td>' +
                        '<td>' + (issueHtml || '<span style="color:#00a32a;">Ã¢Å“â€œ</span>') + '</td>' +
                        '<td><button class="button button-small bulk-gen-single" data-id="' + p.id + '"><?php echo esc_js(__('Generate', 'ai-seo-client')); ?></button></td>' +
                        '</tr>'
                    );
                });
            }

            // Scan
            $('#bulk-scan').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#bulk-spinner').addClass('is-active');

                wp.apiFetch({ path: 'sseo-ai/v1/bulk/scan' }).then(function(res) {
                    allPosts = res.posts;
                    var s = res.summary;
                    $('#bulk-total').text(s.total);
                    $('#bulk-no-title').text(s.missing_title);
                    $('#bulk-no-desc').text(s.missing_description);
                    $('#bulk-no-og').text(s.missing_og);
                    $('#bulk-no-kw').text(s.missing_keyphrase);
                    $('#bulk-summary-card, #bulk-results, #bulk-filter, #bulk-fields-label, .bulk-field-cb, #bulk-generate-selected, #bulk-generate-all').show();
                    renderTable(allPosts);
                    btn.prop('disabled', false);
                    $('#bulk-spinner').removeClass('is-active');
                }).catch(function(err) {
                    alert(err.message || 'Scan failed');
                    btn.prop('disabled', false);
                    $('#bulk-spinner').removeClass('is-active');
                });
            });

            // Filter
            $('#bulk-filter').on('change', function() {
                var val = $(this).val();
                if (val === 'all') {
                    renderTable(allPosts);
                } else {
                    renderTable(allPosts.filter(function(p) { return p.issues.indexOf(val) !== -1; }));
                }
            });

            // Select all
            $('#bulk-select-all').on('change', function() {
                $('.bulk-cb').prop('checked', $(this).is(':checked'));
            });

            // Get selected fields
            function getFields() {
                var fields = [];
                $('.bulk-field-cb input:checked').each(function() { fields.push($(this).val()); });
                return fields;
            }

            // Generate single
            $(document).on('click', '.bulk-gen-single', function() {
                var btn = $(this);
                var postId = btn.data('id');
                var fields = getFields();
                if (!fields.length) return alert('<?php echo esc_js(__('Select at least one field to generate', 'ai-seo-client')); ?>');

                btn.prop('disabled', true).text('...');
                wp.apiFetch({
                    path: 'sseo-ai/v1/bulk/generate-meta',
                    method: 'POST',
                    data: { post_id: postId, fields: fields }
                }).then(function(res) {
                    var row = $('tr[data-id="' + postId + '"]');
                    if (res.generated.seo_title) row.find('.seo-title-cell').text(res.generated.seo_title);
                    if (res.generated.seo_description) row.find('.seo-desc-cell').html('<span style="font-size:12px;">' + $('<span>').text(res.generated.seo_description).html() + '</span>');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Generate', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Generate', 'ai-seo-client')); ?>');
                });
            });

            // Batch generate
            function processBatch(ids, fields, done, total) {
                if (!ids.length) {
                    $('#bulk-progress-text').text('<?php echo esc_js(__('Done!', 'ai-seo-client')); ?>');
                    $('#bulk-generate-selected, #bulk-generate-all').prop('disabled', false);
                    return;
                }

                var pct = Math.round((done / total) * 100);
                $('#bulk-progress-bar').css('width', pct + '%').text(pct + '%');
                $('#bulk-progress-text').text(done + ' / ' + total + ' <?php echo esc_js(__('processed', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/bulk/generate-batch',
                    method: 'POST',
                    data: { post_ids: ids, fields: fields }
                }).then(function(res) {
                    if (res.success && res.data) {
                        var row = $('tr[data-id="' + res.processed + '"]');
                        if (res.data.generated.seo_title) row.find('.seo-title-cell').text(res.data.generated.seo_title);
                        if (res.data.generated.seo_description) row.find('.seo-desc-cell').html('<span style="font-size:12px;">' + $('<span>').text(res.data.generated.seo_description).html() + '</span>');
                    }
                    processBatch(res.remaining, fields, done + 1, total);
                }).catch(function(err) {
                    // Continue on error
                    processBatch(ids.slice(1), fields, done + 1, total);
                });
            }

            // Generate selected
            $('#bulk-generate-selected').on('click', function() {
                var ids = [];
                $('.bulk-cb:checked').each(function() { ids.push(parseInt($(this).val())); });
                var fields = getFields();
                if (!ids.length) return alert('<?php echo esc_js(__('Select posts first', 'ai-seo-client')); ?>');
                if (!fields.length) return alert('<?php echo esc_js(__('Select fields to generate', 'ai-seo-client')); ?>');

                $(this).prop('disabled', true);
                $('#bulk-progress').show();
                processBatch(ids, fields, 0, ids.length);
            });

            // Generate all missing
            $('#bulk-generate-all').on('click', function() {
                var fields = getFields();
                if (!fields.length) return alert('<?php echo esc_js(__('Select fields to generate', 'ai-seo-client')); ?>');

                var ids = allPosts.filter(function(p) { return p.issues.length > 0; }).map(function(p) { return p.id; });
                if (!ids.length) return alert('<?php echo esc_js(__('No posts with issues found', 'ai-seo-client')); ?>');

                if (!confirm('<?php echo esc_js(sprintf(__('Generate for %s posts?', 'ai-seo-client'), "' + ids.length + '")); ?>')) return;

                $(this).prop('disabled', true);
                $('#bulk-progress').show();
                processBatch(ids, fields, 0, ids.length);
            });
        });
        </script>
        <?php
    }
}

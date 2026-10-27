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
        $postTypes = PageBuilderHelper::getSeoPostTypes();

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

        // Show the numeric SEO score (0-100) from _sseo_ai_score if available
        $seoScore = get_post_meta($postId, '_sseo_ai_score', true);

        if ($seoScore !== '' && $seoScore !== null && $seoScore !== false) {
            $score = (int) $seoScore;
            if ($score < 0) {
                $score = 0;
            } elseif ($score > 100) {
                $score = 100;
            }

            // Color code: red (0-40), orange (41-70), green (71-100)
            if ($score <= 40) {
                $color = '#d63638';
            } elseif ($score <= 70) {
                $color = '#dba617';
            } else {
                $color = '#00a32a';
            }

            printf(
                '<span style="display:inline-block;padding:2px 10px;border-radius:10px;background:%s;color:#fff;font-size:11px;font-weight:600;min-width:32px;text-align:center;">%d</span>',
                esc_attr($color),
                $score
            );
        } else {
            // No score calculated yet — show a neutral placeholder
            printf(
                '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:#cbd5e1;color:#fff;font-size:11px;font-weight:600;">%s</span>',
                esc_html__('—', 'ai-seo-client')
            );
        }
    }

    /**
     * Render Bulk Optimizer admin page
     */
    public function renderPage(): void
    {
        ?>
        <div class="wrap fyn-animate-in" style="max-width:1440px;margin:0 auto;padding:28px 32px 48px;">

            <!-- Page header -->
            <div class="fyn-page-header">
                <div class="fyn-page-header-left">
                    <div class="fyn-breadcrumb">Fyndable &rsaquo; <span class="fyn-crumb"><?php esc_html_e('Bulk Optimizer', 'ai-seo-client'); ?></span></div>
                    <h1><?php esc_html_e('AI Bulk SEO Optimizer', 'ai-seo-client'); ?></h1>
                    <p class="fyn-page-desc"><?php esc_html_e('Scan your site for missing SEO data and bulk-generate meta titles, descriptions and Open Graph tags with AI.', 'ai-seo-client'); ?></p>
                </div>
                <div class="fyn-page-header-actions">
                    <button type="button" class="fyn-btn fyn-btn-primary" id="bulk-scan">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Scan Site', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>

            <!-- Summary stat tiles -->
            <div id="bulk-summary-card" style="display:none;margin-bottom:20px;">
                <div class="fyn-stats-grid" id="bulk-summary">
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value" id="bulk-total">0</div>
                        <div class="fyn-stat-label"><?php esc_html_e('Total Posts', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value" style="color:var(--fyn-danger);" id="bulk-no-title">0</div>
                        <div class="fyn-stat-label"><?php esc_html_e('Missing Title', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value" style="color:var(--fyn-danger);" id="bulk-no-desc">0</div>
                        <div class="fyn-stat-label"><?php esc_html_e('Missing Description', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value" style="color:var(--fyn-warning);" id="bulk-no-og">0</div>
                        <div class="fyn-stat-label"><?php esc_html_e('Missing OG Tags', 'ai-seo-client'); ?></div>
                    </div>
                    <div class="fyn-stat-tile">
                        <div class="fyn-stat-value" style="color:var(--fyn-warning);" id="bulk-no-kw">0</div>
                        <div class="fyn-stat-label"><?php esc_html_e('Missing Keyphrase', 'ai-seo-client'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Controls card -->
            <div class="fyn-card" style="margin-bottom:20px;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Bulk Actions', 'ai-seo-client'); ?></h3>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <select id="bulk-filter" class="fyn-input" style="display:none;padding:8px 10px;max-width:220px;">
                        <option value="all"><?php esc_html_e('Show All', 'ai-seo-client'); ?></option>
                        <option value="missing_title"><?php esc_html_e('Missing Title', 'ai-seo-client'); ?></option>
                        <option value="missing_description"><?php esc_html_e('Missing Description', 'ai-seo-client'); ?></option>
                        <option value="missing_og"><?php esc_html_e('Missing OG Tags', 'ai-seo-client'); ?></option>
                        <option value="missing_keyphrase"><?php esc_html_e('Missing Keyphrase', 'ai-seo-client'); ?></option>
                    </select>

                    <label style="display:none;font-size:13px;font-weight:700;color:var(--text-h);" id="bulk-fields-label">
                        <?php esc_html_e('Generate:', 'ai-seo-client'); ?>
                    </label>
                    <label style="display:none;font-size:13px;color:var(--text-b);" class="bulk-field-cb"><input type="checkbox" value="seo_title" checked> <?php esc_html_e('SEO Title', 'ai-seo-client'); ?></label>
                    <label style="display:none;font-size:13px;color:var(--text-b);" class="bulk-field-cb"><input type="checkbox" value="seo_description" checked> <?php esc_html_e('Meta Description', 'ai-seo-client'); ?></label>
                    <label style="display:none;font-size:13px;color:var(--text-b);" class="bulk-field-cb"><input type="checkbox" value="og_title"> <?php esc_html_e('OG Title', 'ai-seo-client'); ?></label>
                    <label style="display:none;font-size:13px;color:var(--text-b);" class="bulk-field-cb"><input type="checkbox" value="og_description"> <?php esc_html_e('OG Description', 'ai-seo-client'); ?></label>
                    <label style="display:none;font-size:13px;color:var(--text-b);" class="bulk-field-cb"><input type="checkbox" value="focus_keyphrase"> <?php esc_html_e('Focus keyphrase', 'ai-seo-client'); ?></label>

                    <button type="button" class="fyn-btn fyn-btn-primary" id="bulk-generate-selected" style="display:none;">
                        <?php esc_html_e('Generate for Selected', 'ai-seo-client'); ?>
                    </button>
                    <button type="button" class="fyn-btn fyn-btn-outline" id="bulk-generate-all" style="display:none;">
                        <?php esc_html_e('Generate for Missing', 'ai-seo-client'); ?>
                    </button>

                    <span class="spinner" id="bulk-spinner" style="float:none;"></span>
                </div>

                <!-- Progress bar -->
                <div id="bulk-progress" style="display:none;margin-top:16px;">
                    <div class="fyn-progress" style="height:24px;">
                        <div class="fyn-progress-bar" id="bulk-progress-bar" style="width:0%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:600;transition:width .3s;"></div>
                    </div>
                    <p id="bulk-progress-text" style="color:var(--text-m);margin-top:6px;font-size:13px;"></p>
                </div>
            </div>

            <!-- Results Table -->
            <div class="fyn-card" id="bulk-results" style="display:none;">
                <div class="fyn-card-header">
                    <span class="dashicons dashicons-editor-table"></span>
                    <h3 class="fyn-card-title"><?php esc_html_e('Posts Overview', 'ai-seo-client'); ?></h3>
                </div>
                <table class="fyn-table">
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
                            missing_description: '<?php echo esc_js(__('No Desc.', 'ai-seo-client')); ?>',
                            missing_og: '<?php echo esc_js(__('No OG', 'ai-seo-client')); ?>',
                            missing_keyphrase: '<?php echo esc_js(__('No KW', 'ai-seo-client')); ?>',
                            short_content: '<?php echo esc_js(__('Short', 'ai-seo-client')); ?>'
                        };
                        return '<span class="fyn-badge fyn-badge--danger" style="font-size:10px;padding:2px 6px;">' + (labels[i]||i) + '</span>';
                    }).join(' ');

                    tbody.append(
                        '<tr data-id="' + p.id + '">' +
                        '<td><input type="checkbox" class="bulk-cb" value="' + p.id + '"></td>' +
                        '<td><a href="' + p.edit_url + '" target="_blank">' + $('<span>').text(p.title).html() + '</a></td>' +
                        '<td>' + p.type + '</td>' +
                        '<td>' + p.word_count + '</td>' +
                        '<td class="seo-title-cell">' + (p.seo_title ? $('<span>').text(p.seo_title).html() : '<em style="color:#999;">&mdash;</em>') + '</td>' +
                        '<td class="seo-desc-cell">' + (p.seo_description ? '<span style="font-size:12px;">' + $('<span>').text(p.seo_description).html() + '</span>' : '<em style="color:#999;">&mdash;</em>') + '</td>' +
                        '<td>' + (issueHtml || '<span style="color:var(--fyn-success);">&check;</span>') + '</td>' +
                        '<td><button class="fyn-btn fyn-btn-outline bulk-gen-single" style="padding:5px 10px;font-size:12px;" data-id="' + p.id + '"><?php echo esc_js(__('Generate', 'ai-seo-client')); ?></button></td>' +
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

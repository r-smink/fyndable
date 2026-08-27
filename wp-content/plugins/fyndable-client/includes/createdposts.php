<?php

namespace SSEOAIClient;

/**
 * SEO AI Created Posts
 * 
 * Overview of all AI-generated posts including scheduled, published, and drafts.
 * Shows post status, review results, mass edit status, and filtering options.
 * Comparable to WPSEO AI Created Posts feature.
 */
class CreatedPosts
{
    private Settings $settings;
    private GscClient $gscClient;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->gscClient = new GscClient($settings);
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        // Get all AI-created posts with filters
        register_rest_route('sseo-ai/v1', '/created-posts', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCreatedPosts'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Get post statistics
        register_rest_route('sseo-ai/v1', '/created-posts/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPostStats'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Bulk update post status
        register_rest_route('sseo-ai/v1', '/created-posts/bulk-status', [
            'methods' => 'POST',
            'callback' => [$this, 'restBulkUpdateStatus'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
            'args' => [
                'post_ids' => ['type' => 'array', 'required' => true],
                'status' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Bulk update review status
        register_rest_route('sseo-ai/v1', '/created-posts/bulk-review', [
            'methods' => 'POST',
            'callback' => [$this, 'restBulkUpdateReview'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
            'args' => [
                'post_ids' => ['type' => 'array', 'required' => true],
                'review_status' => ['type' => 'string', 'required' => true],
            ],
        ]);

        // Delete multiple posts
        register_rest_route('sseo-ai/v1', '/created-posts/bulk-delete', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restBulkDelete'],
            'permission_callback' => fn() => current_user_can('delete_posts'),
            'args' => [
                'post_ids' => ['type' => 'array', 'required' => true],
            ],
        ]);

        // Regenerate content for a post
        register_rest_route('sseo-ai/v1', '/created-posts/(?P<id>\d+)/regenerate', [
            'methods' => 'POST',
            'callback' => [$this, 'restRegenerateContent'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
        ]);

        // Get post detail
        register_rest_route('sseo-ai/v1', '/created-posts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPostDetail'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Update single post
        register_rest_route('sseo-ai/v1', '/created-posts/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'restUpdatePost'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Get GSC metrics for a single post
        register_rest_route('sseo-ai/v1', '/created-posts/(?P<id>\d+)/gsc-metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPostGscMetrics'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * REST: Get AI created posts with filtering
     */
    public function restGetCreatedPosts(\WP_REST_Request $request): array
    {
        global $wpdb;

        // Parse parameters
        $search = sanitize_text_field($request->get_param('search') ?? '');
        $postStatus = sanitize_text_field($request->get_param('post_status') ?? '');
        $editStatus = sanitize_text_field($request->get_param('edit_status') ?? '');
        $reviewStatus = sanitize_text_field($request->get_param('review_status') ?? '');
        $clusterId = (int) ($request->get_param('cluster_id') ?? 0);
        $language = sanitize_text_field($request->get_param('language') ?? '');
        $dateFrom = sanitize_text_field($request->get_param('date_from') ?? '');
        $dateTo = sanitize_text_field($request->get_param('date_to') ?? '');
        $publishFrom = sanitize_text_field($request->get_param('publish_from') ?? '');
        $publishTo = sanitize_text_field($request->get_param('publish_to') ?? '');
        $perPage = (int) ($request->get_param('per_page') ?? 10);
        $page = (int) ($request->get_param('page') ?? 1);
        $orderby = sanitize_text_field($request->get_param('orderby') ?? 'date');
        $order = sanitize_text_field($request->get_param('order') ?? 'DESC');

        // Build query
        $offset = ($page - 1) * $perPage;
        
        // Meta query for AI generated posts
        $metaQuery = [
            [
                'key' => '_sseo_ai_generated',
                'value' => '1',
                'compare' => '=',
            ],
        ];

        // Additional filters
        $taxQuery = [];
        
        // Post status filter
        $postStatuses = [];
        if ($postStatus) {
            $postStatuses = [$postStatus];
        } else {
            $postStatuses = ['publish', 'future', 'draft', 'private', 'pending'];
        }

        // Edit status filter
        if ($editStatus) {
            $metaQuery[] = [
                'key' => '_sseo_ai_edit_status',
                'value' => $editStatus,
                'compare' => '=',
            ];
        }

        // Review status filter
        if ($reviewStatus) {
            $metaQuery[] = [
                'key' => '_sseo_ai_review_status',
                'value' => $reviewStatus,
                'compare' => '=',
            ];
        }

        // Cluster filter
        if ($clusterId) {
            $metaQuery[] = [
                'key' => '_sseo_ai_cluster_id',
                'value' => $clusterId,
                'compare' => '=',
            ];
        }

        // Language filter
        if ($language && $language !== 'all') {
            $metaQuery[] = [
                'key' => '_sseo_ai_language',
                'value' => $language,
                'compare' => '=',
            ];
        }

        // Build date query
        $dateQuery = [];
        if ($dateFrom || $dateTo) {
            $dateQuery = [
                'after' => $dateFrom ?: '1970-01-01',
                'before' => $dateTo ? $dateTo . ' 23:59:59' : current_time('mysql'),
                'inclusive' => true,
            ];
        }

        $wpQueryArgs = [
            'post_type' => 'post',
            'post_status' => $postStatuses,
            'posts_per_page' => $perPage,
            'offset' => $offset,
            'orderby' => $orderby === 'date' ? 'date' : 'title',
            'order' => $order,
            'meta_query' => $metaQuery,
            'date_query' => $dateQuery,
        ];

        // Add search
        if ($search) {
            $wpQueryArgs['s'] = $search;
        }

        $query = new \WP_Query($wpQueryArgs);
        $posts = [];

        foreach ($query->posts as $post) {
            $posts[] = $this->formatPostData($post);
        }

        return [
            'posts' => $posts,
            'total' => $query->found_posts,
            'total_pages' => ceil($query->found_posts / $perPage),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Format post data for API response
     */
    private function formatPostData(\WP_Post $post): array
    {
        $postId = $post->ID;
        
        // Get meta data
        $editStatus = get_post_meta($postId, '_sseo_ai_edit_status', true) ?: 'pending';
        $reviewStatus = get_post_meta($postId, '_sseo_ai_review_status', true) ?: 'needs_review';
        $language = get_post_meta($postId, '_sseo_ai_language', true) ?: 'nl';
        $clusterId = (int) get_post_meta($postId, '_sseo_ai_cluster_id', true);
        $generatedDate = get_post_meta($postId, '_sseo_ai_generated_date', true);
        $wordCount = str_word_count(wp_strip_all_tags($post->post_content));

        // Get featured image
        $thumbnailId = get_post_thumbnail_id($postId);
        $thumbnailUrl = $thumbnailId ? wp_get_attachment_image_url($thumbnailId, 'thumbnail') : '';

        // Get cluster name
        $clusterName = '';
        if ($clusterId) {
            global $wpdb;
            $clusterTable = $wpdb->prefix . 'sseo_ai_clusters';
            $clusterName = $wpdb->get_var($wpdb->prepare(
                "SELECT pillar_topic FROM {$clusterTable} WHERE id = %d",
                $clusterId
            )) ?: '';
        }

        return [
            'id' => $postId,
            'title' => $post->post_title,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'status' => $post->post_status,
            'edit_status' => $editStatus,
            'review_status' => $reviewStatus,
            'language' => $language,
            'word_count' => $wordCount,
            'thumbnail' => $thumbnailUrl,
            'cluster_id' => $clusterId,
            'cluster_name' => $clusterName,
            'generated_date' => $generatedDate,
            'edit_url' => get_edit_post_link($postId, ''),
            'view_url' => get_permalink($postId),
            'post_type' => $post->post_type,
        ];
    }

    /**
     * REST: Get post statistics
     */
    public function restGetPostStats(): array
    {
        global $wpdb;

        // Count by edit status (join posts to exclude revisions)
        $editStats = $wpdb->get_results(
            "SELECT pm.meta_value as status, COUNT(*) as count 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sseo_ai_edit_status' 
             AND p.post_type = 'post'
             AND p.post_status IN ('publish', 'future', 'draft', 'private', 'pending')
             GROUP BY pm.meta_value",
            OBJECT_K
        );

        // Count by review status (join posts to exclude revisions)
        $reviewStats = $wpdb->get_results(
            "SELECT pm.meta_value as status, COUNT(*) as count 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sseo_ai_review_status' 
             AND p.post_type = 'post'
             AND p.post_status IN ('publish', 'future', 'draft', 'private', 'pending')
             GROUP BY pm.meta_value",
            OBJECT_K
        );

        // Count by post status
        $postStats = $wpdb->get_results(
            "SELECT p.post_status, COUNT(*) as count 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sseo_ai_generated' 
             AND pm.meta_value = '1'
             AND p.post_type = 'post'
             AND p.post_status IN ('publish', 'future', 'draft', 'private', 'pending')
             GROUP BY p.post_status",
            OBJECT_K
        );

        // Count by language (join posts to exclude revisions)
        $languageStats = $wpdb->get_results(
            "SELECT pm.meta_value as language, COUNT(*) as count 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sseo_ai_language' 
             AND p.post_type = 'post'
             AND p.post_status IN ('publish', 'future', 'draft', 'private', 'pending')
             GROUP BY pm.meta_value",
            OBJECT_K
        );

        return [
            'edit_status' => [
                'completed' => (int) (isset($editStats['completed']) ? $editStats['completed']->count : 0),
                'pending' => (int) (isset($editStats['pending']) ? $editStats['pending']->count : 0),
                'failed' => (int) (isset($editStats['failed']) ? $editStats['failed']->count : 0),
            ],
            'review_status' => [
                'yes' => (int) (isset($reviewStats['yes']) ? $reviewStats['yes']->count : 0),
                'no' => (int) (isset($reviewStats['no']) ? $reviewStats['no']->count : 0),
                'needs_attention' => (int) (isset($reviewStats['needs_attention']) ? $reviewStats['needs_attention']->count : 0),
                'reviewing' => (int) (isset($reviewStats['reviewing']) ? $reviewStats['reviewing']->count : 0),
                'failed' => (int) (isset($reviewStats['failed']) ? $reviewStats['failed']->count : 0),
            ],
            'post_status' => [
                'published' => (int) (isset($postStats['publish']) ? $postStats['publish']->count : 0),
                'drafts' => (int) (isset($postStats['draft']) ? $postStats['draft']->count : 0),
                'future' => (int) (isset($postStats['future']) ? $postStats['future']->count : 0),
                'private' => (int) (isset($postStats['private']) ? $postStats['private']->count : 0),
                'total' => is_array($postStats) ? array_sum(array_map(fn($s) => (int) $s->count, $postStats)) : 0,
            ],
            'languages' => $languageStats ?: [],
        ];
    }

    /**
     * REST: Get GSC metrics for a single post
     */
    public function restGetPostGscMetrics(\WP_REST_Request $request): \WP_REST_Response
    {
        $postId = (int) $request->get_param('id');

        if (!current_user_can('edit_post', $postId)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('You do not have permission to view this post.', 'ai-seo-client'),
            ], 403);
        }

        $post = get_post($postId);
        if (!$post) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Post not found.', 'ai-seo-client'),
            ], 404);
        }

        if (!$this->gscClient->isConnected()) {
            return new \WP_REST_Response([
                'success' => false,
                'connected' => false,
                'message' => __('Google Search Console is not connected.', 'ai-seo-client'),
            ], 200);
        }

        $postUrl = get_permalink($postId);
        if (!$postUrl || !str_starts_with($postUrl, 'http')) {
            return new \WP_REST_Response([
                'success' => false,
                'connected' => true,
                'message' => __('Unable to determine post URL.', 'ai-seo-client'),
            ], 200);
        }

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-28 days'));

        $data = $this->gscClient->query([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['page'],
            'dimensionFilterGroups' => [
                [
                    'groupType' => 'and',
                    'filters' => [
                        [
                            'dimension' => 'page',
                            'operator' => 'equals',
                            'expression' => $postUrl,
                        ],
                    ],
                ],
            ],
            'rowLimit' => 1,
        ]);

        if (is_wp_error($data)) {
            return new \WP_REST_Response([
                'success' => false,
                'connected' => true,
                'message' => $data->get_error_message(),
            ], 200);
        }

        $rows = $data['rows'] ?? [];
        if (empty($rows)) {
            return new \WP_REST_Response([
                'success' => true,
                'connected' => true,
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0.0,
                'position' => 0.0,
                'period' => ['start' => $startDate, 'end' => $endDate, 'days' => 28],
            ], 200);
        }

        $row = $rows[0];
        return new \WP_REST_Response([
            'success' => true,
            'connected' => true,
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => round((float) ($row['ctr'] ?? 0) * 100, 2),
            'position' => round((float) ($row['position'] ?? 0), 1),
            'period' => ['start' => $startDate, 'end' => $endDate, 'days' => 28],
        ], 200);
    }

    /**
     * REST: Bulk update post status
     */
    public function restBulkUpdateStatus(\WP_REST_Request $request): array|\WP_Error
    {
        $postIds = array_map('intval', $request->get_param('post_ids'));
        $status = sanitize_text_field($request->get_param('status'));

        $validStatuses = ['publish', 'draft', 'future', 'private', 'pending', 'trash'];
        if (!in_array($status, $validStatuses)) {
            return new \WP_Error('invalid_status', __('Invalid status', 'ai-seo-client'), ['status' => 400]);
        }

        $updated = 0;
        foreach ($postIds as $postId) {
            if (!current_user_can('edit_post', $postId)) {
                continue;
            }

            if ($status === 'trash') {
                wp_trash_post($postId);
            } else {
                wp_update_post(['ID' => $postId, 'post_status' => $status]);
            }
            
            // Update edit status meta
            update_post_meta($postId, '_sseo_ai_edit_status', $status === 'publish' ? 'completed' : 'pending');
            $updated++;
        }

        return [
            'success' => true,
            'updated' => $updated,
            'message' => sprintf(__('Updated %d posts', 'ai-seo-client'), $updated),
        ];
    }

    /**
     * REST: Bulk update review status
     */
    public function restBulkUpdateReview(\WP_REST_Request $request): array|\WP_Error
    {
        $postIds = array_map('intval', $request->get_param('post_ids'));
        $reviewStatus = sanitize_text_field($request->get_param('review_status'));

        $validStatuses = ['yes', 'no', 'needs_attention', 'reviewing', 'failed'];
        if (!in_array($reviewStatus, $validStatuses)) {
            return new \WP_Error('invalid_status', __('Invalid review status', 'ai-seo-client'), ['status' => 400]);
        }

        $updated = 0;
        foreach ($postIds as $postId) {
            if (!current_user_can('edit_post', $postId)) {
                continue;
            }
            update_post_meta($postId, '_sseo_ai_review_status', $reviewStatus);
            $updated++;
        }

        return [
            'success' => true,
            'updated' => $updated,
            'message' => sprintf(__('Updated review status for %d posts', 'ai-seo-client'), $updated),
        ];
    }

    /**
     * REST: Bulk delete posts
     */
    public function restBulkDelete(\WP_REST_Request $request): array|\WP_Error
    {
        $postIds = array_map('intval', $request->get_param('post_ids'));
        $force = (bool) $request->get_param('force');

        $deleted = 0;
        foreach ($postIds as $postId) {
            if (!current_user_can('delete_post', $postId)) {
                continue;
            }

            if ($force) {
                wp_delete_post($postId, true);
            } else {
                wp_trash_post($postId);
            }
            $deleted++;
        }

        return [
            'success' => true,
            'deleted' => $deleted,
            'message' => sprintf(__('Deleted %d posts', 'ai-seo-client'), $deleted),
        ];
    }

    /**
     * REST: Regenerate content for a post
     */
    public function restRegenerateContent(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) $request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'), ['status' => 404]);
        }

        // Get original parameters
        $title = $post->post_title;
        $keywords = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
        $wordCount = str_word_count(wp_strip_all_tags($post->post_content));

        // Mark as regenerating
        update_post_meta($postId, '_sseo_ai_edit_status', 'pending');
        update_post_meta($postId, '_sseo_ai_review_status', 'reviewing');

        // Note: Actual regeneration would be handled by ContentWriter
        // This endpoint just marks it for regeneration

        return [
            'success' => true,
            'post_id' => $postId,
            'message' => __('Post marked for regeneration', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Get post detail
     */
    public function restGetPostDetail(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) $request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'), ['status' => 404]);
        }

        return $this->formatPostData($post);
    }

    /**
     * REST: Update single post
     */
    public function restUpdatePost(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int) $request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'), ['status' => 404]);
        }

        $data = [];
        if ($request->has_param('edit_status')) {
            update_post_meta($postId, '_sseo_ai_edit_status', sanitize_text_field($request->get_param('edit_status')));
        }
        if ($request->has_param('review_status')) {
            update_post_meta($postId, '_sseo_ai_review_status', sanitize_text_field($request->get_param('review_status')));
        }
        if ($request->has_param('title')) {
            $data['post_title'] = sanitize_text_field($request->get_param('title'));
        }

        if (!empty($data)) {
            $data['ID'] = $postId;
            wp_update_post($data);
        }

        return [
            'success' => true,
            'message' => __('Post updated', 'ai-seo-client'),
        ];
    }

    /**
     * Render the Created Posts page
     */
    public function renderPage(): void
    {
        // Get clusters for filter
        global $wpdb;
        $clusterTable = $wpdb->prefix . 'sseo_ai_clusters';
        $clusters = $wpdb->get_results("SELECT id, pillar_topic as name FROM {$clusterTable} ORDER BY pillar_topic LIMIT 50");

        // Get post count range
        $wordCountRange = $wpdb->get_row(
            "SELECT 
                MIN(CAST(meta_value AS UNSIGNED)) as min_words,
                MAX(CAST(meta_value AS UNSIGNED)) as max_words
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_sseo_ai_word_count'"
        );
        $minWords = (int)($wordCountRange->min_words ?? 0);
        $maxWords = (int)($wordCountRange->max_words ?? 3000);
        ?>
        <div class="wrap sseo-ai-modern" id="sseo-created-posts-page">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('SEO AI created posts', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <!-- Top Filters -->
                    <div class="posts-filters-top">
                        <div class="filter-group search-group">
                            <label><?php esc_html_e('Search:', 'ai-seo-client'); ?></label>
                            <input type="text" id="filter-search" placeholder="<?php esc_attr_e('Post title keywords', 'ai-seo-client'); ?>">
                        </div>
                        <div class="filter-group cluster-group">
                            <label><?php esc_html_e('Select cluster', 'ai-seo-client'); ?></label>
                            <select id="filter-cluster">
                                <option value=""><?php esc_html_e('All clusters', 'ai-seo-client'); ?></option>
                                <?php foreach ($clusters as $cluster): ?>
                                    <option value="<?php echo esc_attr($cluster->id); ?>">
                                        <?php echo esc_html($cluster->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group wordcount-group">
                            <label><?php esc_html_e('Content words range:', 'ai-seo-client'); ?> <span id="wordcount-display"><?php echo $minWords; ?> - <?php echo $maxWords; ?></span></label>
                            <div class="wordcount-slider">
                                <input type="range" id="filter-wordcount-min" min="0" max="5000" value="<?php echo $minWords; ?>">
                                <input type="range" id="filter-wordcount-max" min="0" max="5000" value="<?php echo $maxWords; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Status Filters -->
                    <div class="posts-status-filters">
                        <div class="filter-row">
                            <span class="filter-label"><?php esc_html_e('Published / scheduled', 'ai-seo-client'); ?></span>
                            <div class="date-filters">
                                <input type="text" id="filter-publish-from" placeholder="<?php esc_attr_e('Published from', 'ai-seo-client'); ?>">
                                <button type="button" class="date-btn"><span class="dashicons dashicons-calendar-alt"></span></button>
                                <input type="text" id="filter-publish-to" placeholder="<?php esc_attr_e('Published to', 'ai-seo-client'); ?>">
                                <button type="button" class="date-btn"><span class="dashicons dashicons-calendar-alt"></span></button>
                                <input type="text" id="filter-created-from" placeholder="<?php esc_attr_e('Created from', 'ai-seo-client'); ?>">
                                <button type="button" class="date-btn"><span class="dashicons dashicons-calendar-alt"></span></button>
                                <input type="text" id="filter-created-to" placeholder="<?php esc_attr_e('Created to', 'ai-seo-client'); ?>">
                                <button type="button" class="date-btn"><span class="dashicons dashicons-calendar-alt"></span></button>
                                <button type="button" class="filter-search-btn" id="btn-apply-filters">
                                    <span class="dashicons dashicons-search"></span>
                                </button>
                                <button type="button" class="filter-clear-btn" id="btn-clear-filters">&times;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Mass Status Filters -->
                    <div class="posts-mass-filters">
                        <div class="mass-filter-row">
                            <span class="filter-label"><?php esc_html_e('Mass edit status:', 'ai-seo-client'); ?></span>
                            <div class="status-pills">
                                <button type="button" class="status-pill active" data-filter="edit_status" data-value="">
                                    <?php esc_html_e('All', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-all">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="edit_status" data-value="completed">
                                    <?php esc_html_e('Completed', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-completed">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="edit_status" data-value="pending">
                                    <?php esc_html_e('Pending', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-pending">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="edit_status" data-value="failed">
                                    <?php esc_html_e('Failed', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-failed">0</span>
                                </button>
                            </div>
                        </div>

                        <div class="mass-filter-row">
                            <span class="filter-label"><?php esc_html_e('Mass review status:', 'ai-seo-client'); ?></span>
                            <div class="status-pills">
                                <button type="button" class="status-pill" data-filter="review_status" data-value="yes">
                                    <?php esc_html_e('Yes', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-review-yes">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="review_status" data-value="no">
                                    <?php esc_html_e('No', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-review-no">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="review_status" data-value="needs_attention">
                                    <?php esc_html_e('Requires attention', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-review-attention">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="review_status" data-value="reviewing">
                                    <?php esc_html_e('Reviewing', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-reviewing">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="review_status" data-value="failed">
                                    <?php esc_html_e('Failed', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-review-failed">0</span>
                                </button>
                            </div>
                        </div>

                        <div class="mass-filter-row">
                            <span class="filter-label"><?php esc_html_e('Post language:', 'ai-seo-client'); ?></span>
                            <div class="status-pills">
                                <button type="button" class="status-pill" data-filter="language" data-value="all">
                                    <?php esc_html_e('All', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-lang-all">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="language" data-value="nl">
                                    <?php esc_html_e('NL', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-lang-nl">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="language" data-value="en">
                                    <?php esc_html_e('EN', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-lang-en">0</span>
                                </button>
                            </div>
                        </div>

                        <div class="mass-filter-row">
                            <span class="filter-label"><?php esc_html_e('Post status:', 'ai-seo-client'); ?></span>
                            <div class="status-pills">
                                <button type="button" class="status-pill" data-filter="post_status" data-value="publish">
                                    <?php esc_html_e('Published', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-published">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="post_status" data-value="draft">
                                    <?php esc_html_e('Drafts', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-drafts">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="post_status" data-value="future">
                                    <?php esc_html_e('Future', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-future">0</span>
                                </button>
                                <button type="button" class="status-pill" data-filter="post_status" data-value="private">
                                    <?php esc_html_e('Privé', 'ai-seo-client'); ?>
                                    <span class="pill-count" id="count-private">0</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Results Summary -->
                    <div class="posts-summary">
                        <label class="select-all">
                            <input type="checkbox" id="select-all-posts">
                        </label>
                        <span class="results-text"><?php esc_html_e('All posts:', 'ai-seo-client'); ?> <strong id="total-posts-count">0</strong></span>
                        <button type="button" class="toggle-filters" id="btn-toggle-filters">
                            <?php esc_html_e('All filters', 'ai-seo-client'); ?> 
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        
                        <div class="per-page-selector">
                            <label><?php esc_html_e('Posts per page', 'ai-seo-client'); ?></label>
                            <select id="posts-per-page">
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>

                    <!-- Posts Table -->
                    <div class="posts-table-wrapper">
                        <table class="posts-table" id="posts-table">
                            <thead>
                                <tr>
                                    <th class="col-checkbox"></th>
                                    <th class="col-id sortable" data-sort="id">ID <span class="sort-icon">&#8597;</span></th>
                                    <th class="col-date sortable" data-sort="date"><?php esc_html_e('Date', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span></th>
                                    <th class="col-image"><?php esc_html_e('Image', 'ai-seo-client'); ?></th>
                                    <th class="col-title sortable" data-sort="title"><?php esc_html_e('Title', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span></th>
                                    <th class="col-visibility"><?php esc_html_e('Post visibility', 'ai-seo-client'); ?></th>
                                    <th class="col-status"><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                                    <th class="col-edit-status"><?php esc_html_e('Mass Edit Status', 'ai-seo-client'); ?></th>
                                    <th class="col-review"><?php esc_html_e('Review Results', 'ai-seo-client'); ?></th>
                                    <th class="col-actions"></th>
                                </tr>
                            </thead>
                            <tbody id="posts-tbody">
                                <!-- Posts will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="posts-pagination" id="posts-pagination">
                        <button type="button" class="page-btn" data-page="prev">&laquo;</button>
                        <div id="pagination-numbers"></div>
                        <button type="button" class="page-btn" data-page="next">&raquo;</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Modal -->
        <div class="sseo-modal" id="modal-bulk-actions" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Bulk Actions', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <p><?php esc_html_e('Selected posts:', 'ai-seo-client'); ?> <strong id="bulk-selected-count">0</strong></p>
                    
                    <div class="form-group">
                        <label><?php esc_html_e('Change status to:', 'ai-seo-client'); ?></label>
                        <select id="bulk-new-status" class="sseo-select">
                            <option value=""><?php esc_html_e('-- No change --', 'ai-seo-client'); ?></option>
                            <option value="publish"><?php esc_html_e('Published', 'ai-seo-client'); ?></option>
                            <option value="draft"><?php esc_html_e('Draft', 'ai-seo-client'); ?></option>
                            <option value="future"><?php esc_html_e('Scheduled', 'ai-seo-client'); ?></option>
                            <option value="private"><?php esc_html_e('Private', 'ai-seo-client'); ?></option>
                            <option value="trash"><?php esc_html_e('Move to trash', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label><?php esc_html_e('Set review status to:', 'ai-seo-client'); ?></label>
                        <select id="bulk-review-status" class="sseo-select">
                            <option value=""><?php esc_html_e('-- No change --', 'ai-seo-client'); ?></option>
                            <option value="yes"><?php esc_html_e('Yes', 'ai-seo-client'); ?></option>
                            <option value="no"><?php esc_html_e('No', 'ai-seo-client'); ?></option>
                            <option value="needs_attention"><?php esc_html_e('Needs attention', 'ai-seo-client'); ?></option>
                            <option value="reviewing"><?php esc_html_e('Reviewing', 'ai-seo-client'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-bulk">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Apply Changes', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Post Detail Modal -->
        <div class="sseo-modal" id="modal-post-detail" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Post Details', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body" id="post-detail-body">
                    <p><?php esc_html_e('Loading...', 'ai-seo-client'); ?></p>
                </div>
                <div class="sseo-modal-footer">
                    <a href="#" class="sseo-btn-secondary" id="post-detail-view" target="_blank"><?php esc_html_e('View on site', 'ai-seo-client'); ?></a>
                    <a href="#" class="sseo-btn-primary" id="post-detail-edit" target="_blank"><?php esc_html_e('Edit Post', 'ai-seo-client'); ?></a>
                    <button type="button" class="sseo-btn-secondary modal-close"><?php esc_html_e('Close', 'ai-seo-client'); ?></button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let currentSort = 'date';
            let currentOrder = 'DESC';
            let selectedPosts = [];

            // Load initial data
            loadPosts();
            loadStats();

            // Filter pills
            $('.status-pill').on('click', function() {
                const filter = $(this).data('filter');
                const value = $(this).data('value');
                
                // Toggle active state
                if ($(this).hasClass('active')) {
                    $(this).removeClass('active');
                    $(this).siblings('[data-value=""]').addClass('active');
                } else {
                    $(this).siblings().removeClass('active');
                    $(this).addClass('active');
                }
                
                currentPage = 1;
                loadPosts();
            });

            // Sorting
            $('.sortable').on('click', function() {
                const sort = $(this).data('sort');
                if (currentSort === sort) {
                    currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    currentSort = sort;
                    currentOrder = 'DESC';
                }
                loadPosts();
            });

            // Search
            $('#btn-apply-filters').on('click', function() {
                currentPage = 1;
                loadPosts();
            });

            // Clear filters
            $('#btn-clear-filters').on('click', function() {
                $('#filter-search').val('');
                $('#filter-cluster').val('');
                $('#filter-publish-from').val('');
                $('#filter-publish-to').val('');
                $('#filter-created-from').val('');
                $('#filter-created-to').val('');
                $('.status-pill').removeClass('active');
                $('.status-pill[data-value=""]').addClass('active');
                currentPage = 1;
                loadPosts();
            });

            // Per page change
            $('#posts-per-page').on('change', function() {
                currentPage = 1;
                loadPosts();
            });

            // Select all
            $('#select-all-posts').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.post-checkbox').prop('checked', isChecked);
                updateSelectedPosts();
            });

            // Individual checkbox
            $(document).on('change', '.post-checkbox', function() {
                updateSelectedPosts();
            });

            // Bulk actions
            $('#btn-bulk-actions').on('click', function() {
                if (selectedPosts.length === 0) {
                    alert('<?php echo esc_js(__('Please select posts first', 'ai-seo-client')); ?>');
                    return;
                }
                $('#bulk-selected-count').text(selectedPosts.length);
                $('#modal-bulk-actions').show();
            });

            // Apply bulk changes
            $('#btn-confirm-bulk').on('click', function() {
                const newStatus = $('#bulk-new-status').val();
                const reviewStatus = $('#bulk-review-status').val();

                if (!newStatus && !reviewStatus) {
                    $('#modal-bulk-actions').hide();
                    return;
                }

                const btn = $(this);
                btn.find('.spinner').show();
                btn.prop('disabled', true);
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                const promises = [];

                if (newStatus) {
                    promises.push(wp.apiFetch({
                        path: 'sseo-ai/v1/created-posts/bulk-status',
                        method: 'POST',
                        data: { post_ids: selectedPosts, status: newStatus }
                    }));
                }

                if (reviewStatus) {
                    promises.push(wp.apiFetch({
                        path: 'sseo-ai/v1/created-posts/bulk-review',
                        method: 'POST',
                        data: { post_ids: selectedPosts, review_status: reviewStatus }
                    }));
                }

                Promise.all(promises).then(function() {
                    $('#modal-bulk-actions').hide();
                    loadPosts();
                    loadStats();
                    selectedPosts = [];
                    $('#select-all-posts').prop('checked', false);
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to apply changes', 'ai-seo-client')); ?>');
                }).finally(function() {
                    btn.find('.spinner').hide();
                    btn.prop('disabled', false);
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            });

            // Load posts
            function loadPosts() {
                const params = {
                    page: currentPage,
                    per_page: $('#posts-per-page').val(),
                    orderby: currentSort,
                    order: currentOrder,
                    search: $('#filter-search').val(),
                };
                const clusterId = $('#filter-cluster').val();
                if (clusterId) params.cluster_id = clusterId;

                // Add active filters
                $('.status-pill.active').each(function() {
                    const filter = $(this).data('filter');
                    const value = $(this).data('value');
                    if (value) {
                        params[filter] = value;
                    }
                });

                wp.apiFetch({
                    path: 'sseo-ai/v1/created-posts?' + $.param(params),
                    method: 'GET'
                }).then(function(response) {
                    console.log('Created posts response:', response);
                    renderPosts(response.posts);
                    renderPagination(response.total_pages, response.page);
                    $('#total-posts-count').text(response.total);
                }).catch(function(error) {
                    console.error('Failed to load posts:', error);
                });
            }

            // Load statistics
            function loadStats() {
                wp.apiFetch({
                    path: 'sseo-ai/v1/created-posts/stats',
                    method: 'GET'
                }).then(function(stats) {
                    if (!stats) return;
                    var es = stats.edit_status || {};
                    var rs = stats.review_status || {};
                    var ps = stats.post_status || {};
                    $('#count-completed').text(es.completed || 0);
                    $('#count-pending').text(es.pending || 0);
                    $('#count-failed').text(es.failed || 0);
                    $('#count-all').text((es.completed || 0) + (es.pending || 0) + (es.failed || 0));
                    $('#count-review-yes').text(rs.yes || 0);
                    $('#count-review-no').text(rs.no || 0);
                    $('#count-review-attention').text(rs.needs_attention || 0);
                    $('#count-reviewing').text(rs.reviewing || 0);
                    $('#count-review-failed').text(rs.failed || 0);
                    $('#count-published').text(ps.published || 0);
                    $('#count-drafts').text(ps.drafts || 0);
                    $('#count-future').text(ps.future || 0);
                    $('#count-private').text(ps.private || 0);
                    $('#count-lang-all').text(ps.total || 0);
                }).catch(function(error) {
                    console.error('Failed to load stats:', error);
                });
            }

            // Render posts table
            function renderPosts(posts) {
                const tbody = $('#posts-tbody');
                
                if (!posts || posts.length === 0) {
                    tbody.html('<tr><td colspan="10" class="no-results"><?php echo esc_js(__('No posts found', 'ai-seo-client')); ?></td></tr>');
                    return;
                }

                let html = '';
                posts.forEach(function(post) {
                    const statusLabel = getStatusLabel(post.status);
                    const editStatusClass = 'status-' + post.edit_status;
                    const reviewStatusClass = 'review-' + post.review_status;
                    
                    html += `
                        <tr data-id="${post.id}">
                            <td class="col-checkbox">
                                <input type="checkbox" class="post-checkbox" value="${post.id}">
                            </td>
                            <td class="col-id">${post.id}</td>
                            <td class="col-date">${formatDate(post.date)}</td>
                            <td class="col-image">
                                ${post.thumbnail ? `<img src="${post.thumbnail}" alt="" class="post-thumbnail">` : '<div class="no-image"><span class="dashicons dashicons-format-image"></span></div>'}
                            </td>
                            <td class="col-title">
                                <a href="${post.edit_url}" target="_blank">${escapeHtml(post.title)}</a>
                                ${post.cluster_name ? `<span class="cluster-tag">${escapeHtml(post.cluster_name)}</span>` : ''}
                            </td>
                            <td class="col-visibility">
                                <span class="visibility-badge ${post.status}">${statusLabel}</span>
                                ${post.status === 'future' ? `<small>${formatDate(post.date)}</small>` : ''}
                            </td>
                            <td class="col-status">
                                <span class="status-badge ${editStatusClass}">${post.edit_status}</span>
                            </td>
                            <td class="col-edit-status">
                                <span class="edit-status-badge ${post.edit_status}">${post.edit_status}</span>
                            </td>
                            <td class="col-review">
                                <span class="review-badge ${reviewStatusClass}">${post.review_status}</span>
                                ${post.edit_status === 'completed' ? `<small>${formatDateTime(post.date)}</small>` : ''}
                            </td>
                            <td class="col-actions">
                                <button type="button" class="btn-action btn-expand" title="<?php echo esc_js(__('View details', 'ai-seo-client')); ?>">
                                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.html(html);
            }

            // Render pagination
            function renderPagination(totalPages, current) {
                const container = $('#pagination-numbers');
                let html = '';

                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= current - 2 && i <= current + 2)) {
                        const active = i === current ? 'active' : '';
                        html += `<button type="button" class="page-btn ${active}" data-page="${i}">${i}</button>`;
                    } else if (i === current - 3 || i === current + 3) {
                        html += '<span class="page-ellipsis">...</span>';
                    }
                }

                container.html(html);
            }

            // Pagination click
            $(document).on('click', '#pagination-numbers .page-btn', function() {
                currentPage = parseInt($(this).data('page'));
                loadPosts();
            });

            // Update selected posts
            function updateSelectedPosts() {
                selectedPosts = [];
                $('.post-checkbox:checked').each(function() {
                    selectedPosts.push(parseInt($(this).val()));
                });
            }

            // View post details
            $(document).on('click', '.btn-expand', function() {
                const postId = $(this).closest('tr').data('id');
                const body = $('#post-detail-body');
                $('#modal-post-detail').show();
                body.html('<p>' + <?php echo wp_json_encode(__('Loading...', 'ai-seo-client')); ?> + '</p>');

                wp.apiFetch({
                    path: 'sseo-ai/v1/created-posts/' + postId,
                    method: 'GET'
                }).then(function(post) {
                    $('#post-detail-edit').attr('href', post.edit_url);
                    $('#post-detail-view').attr('href', post.view_url);

                    let html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Title', 'ai-seo-client')); ?> + '</strong><p>' + escapeHtml(post.title) + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Status', 'ai-seo-client')); ?> + '</strong><p>' + getStatusLabel(post.status) + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Edit Status', 'ai-seo-client')); ?> + '</strong><p>' + escapeHtml(post.edit_status) + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Review Status', 'ai-seo-client')); ?> + '</strong><p>' + escapeHtml(post.review_status) + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Language', 'ai-seo-client')); ?> + '</strong><p>' + escapeHtml(post.language) + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Cluster', 'ai-seo-client')); ?> + '</strong><p>' + escapeHtml(post.cluster_name || '-') + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Word Count', 'ai-seo-client')); ?> + '</strong><p>' + post.word_count + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Post Type', 'ai-seo-client')); ?> + '</strong><p>' + escapeHtml(post.post_type) + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Generated Date', 'ai-seo-client')); ?> + '</strong><p>' + (post.generated_date ? formatDateTime(post.generated_date) : '-') + '</p></div>';
                    html += '<div><strong>' + <?php echo wp_json_encode(__('Last Modified', 'ai-seo-client')); ?> + '</strong><p>' + formatDateTime(post.modified) + '</p></div>';
                    html += '</div>';
                    body.html(html);
                }).catch(function(err) {
                    body.html('<p>' + (err.message || <?php echo wp_json_encode(__('Failed to load details', 'ai-seo-client')); ?>) + '</p>');
                });
            });

            $('#modal-post-detail .modal-close, #modal-post-detail .sseo-modal-overlay').on('click', function() {
                $('#modal-post-detail').hide();
            });

            // Helpers
            function getStatusLabel(status) {
                const labels = {
                    'publish': '<?php echo esc_js(__('Published', 'ai-seo-client')); ?>',
                    'draft': '<?php echo esc_js(__('Draft', 'ai-seo-client')); ?>',
                    'future': '<?php echo esc_js(__('Scheduled', 'ai-seo-client')); ?>',
                    'private': '<?php echo esc_js(__('Private', 'ai-seo-client')); ?>',
                    'pending': '<?php echo esc_js(__('Pending', 'ai-seo-client')); ?>',
                };
                return labels[status] || status;
            }

            function formatDate(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('<?php echo esc_js(str_replace('_', '-', get_locale())); ?>', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            function formatDateTime(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('<?php echo esc_js(str_replace('_', '-', get_locale())); ?>', {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
        </script>
        <?php
    }
}

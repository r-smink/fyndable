<?php

namespace SSEOAIClient;

/**
 * Direct Index (Google Indexing API)
 *
 * Submits public post/page URLs directly to Google's Indexing API when they
 * are published or scheduled, and exposes a manual "Index Now" control.
 */
class DirectIndex
{
    private const API_URL = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
    public const QUOTA_DAILY = 10;
    private const LOG_LIMIT = 100;

    private const OPTION_ENABLED = 'sseo_direct_index_enabled';
    private const OPTION_POST_TYPES = 'sseo_direct_index_post_types';
    private const OPTION_LOG = 'sseo_direct_index_log';
    private const OPTION_QUOTA_USED = 'sseo_direct_index_quota_used';
    private const OPTION_QUOTA_DATE = 'sseo_direct_index_quota_date';

    private Settings $settings;
    private HealthLogger $health;
    private GscOAuth $oauth;
    private DashboardAPI $dashboardAPI;

    public function __construct(Settings $settings, HealthLogger $health)
    {
        $this->settings = $settings;
        $this->health = $health;
        $this->oauth = new GscOAuth($settings);
        $this->dashboardAPI = new DashboardAPI($settings);
    }

    public function register(): void
    {
        add_action('transition_post_status', [$this, 'onTransitionPostStatus'], 20, 3);
        add_action('delete_post', [$this, 'onDeletePost'], 20, 1);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_filter('post_row_actions', [$this, 'addRowAction'], 20, 2);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('admin_post_sseo_direct_index_now', [$this, 'handleAdminPost']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/direct-index/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'restSubmit'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'post_id' => ['type' => 'integer', 'required' => true],
                'type' => ['type' => 'string', 'default' => 'URL_UPDATED'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/direct-index/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * Auto-submit when a post becomes published or is trashed/deleted.
     */
    public function onTransitionPostStatus(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            $this->submitPost($post->ID, 'URL_UPDATED');
            return;
        }

        if ($newStatus === 'trash' && $oldStatus === 'publish') {
            $this->submitPost($post->ID, 'URL_DELETED');
        }
    }

    /**
     * Permanent deletion fallback.
     */
    public function onDeletePost(int $postId): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $url = get_permalink($postId);
        if ($url && !is_wp_error($url)) {
            $this->submitUrl($url, 'URL_DELETED');
        }
    }

    public function restSubmit(\WP_REST_Request $request): \WP_REST_Response|array
    {
        $postId = (int) $request->get_param('post_id');
        $type = sanitize_text_field($request->get_param('type'));

        if (!current_user_can('edit_post', $postId)) {
            return new \WP_REST_Response(['success' => false, 'error' => __('Permission denied.', 'ai-seo-client')], 403);
        }

        $result = $this->submitPost($postId, in_array($type, ['URL_UPDATED', 'URL_DELETED'], true) ? $type : 'URL_UPDATED');
        return new \WP_REST_Response($result, $result['success'] ? 200 : 400);
    }

    public function restStatus(\WP_REST_Request $request): array
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $scope = $tokens['scope'] ?? '';

        return [
            'connected' => $this->isConnected(),
            'has_indexing_scope' => $this->hasIndexingScope(),
            'enabled' => $this->isEnabled(),
            'quota_used_today' => $this->getQuotaUsedToday(),
            'quota_remaining_today' => max(0, self::QUOTA_DAILY - $this->getQuotaUsedToday()),
            'recent_log' => array_slice($this->getLog(), 0, 20),
        ];
    }

    /**
     * Add "Index Now" row action to post list.
     */
    public function addRowAction(array $actions, \WP_Post $post): array
    {
        if ($post->post_status !== 'publish' || !is_post_type_viewable($post->post_type) || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=sseo_direct_index_now&post_id=' . $post->ID),
            'sseo_direct_index_' . $post->ID
        );

        $actions['sseo_direct_index'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url($url),
            esc_attr__('Submit this URL to Google Indexing API', 'ai-seo-client'),
            esc_html__('Index Now', 'ai-seo-client')
        );

        return $actions;
    }

    /**
     * Render admin-post handler for manual submissions.
     */
    public function handleAdminPost(): void
    {
        if (!isset($_GET['post_id'])) {
            wp_die(__('Missing post ID.', 'ai-seo-client'));
        }

        $postId = (int) $_GET['post_id'];

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'sseo_direct_index_' . $postId)) {
            wp_die(__('Security check failed.', 'ai-seo-client'));
        }

        if (!current_user_can('edit_post', $postId)) {
            wp_die(__('Permission denied.', 'ai-seo-client'));
        }

        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') {
            $referer = wp_get_referer() ?: admin_url('edit.php');
            wp_safe_redirect(add_query_arg('sseo_direct_index_error', 'not_published', $referer));
            exit;
        }

        $result = $this->submitPost($postId, 'URL_UPDATED');

        $referer = wp_get_referer() ?: admin_url('edit.php');
        $redirect = add_query_arg('sseo_direct_index_result', $result['success'] ? '1' : '0', $referer);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Add a small meta box in the post editor.
     */
    public function addMetaBoxes(): void
    {
        $postTypes = $this->getEnabledPostTypes();

        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo_direct_index',
                __('Direct Index', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'low'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $status = $this->isConnected() && $this->hasIndexingScope()
            ? '<span style="color:#00a32a;">✓ ' . esc_html__('Connected', 'ai-seo-client') . '</span>'
            : '<span style="color:#d63638;">' . esc_html__('Not connected / missing indexing scope', 'ai-seo-client') . '</span>';

        $remaining = max(0, self::QUOTA_DAILY - $this->getQuotaUsedToday());

        echo '<p>' . wp_kses_post($status) . '</p>';
        echo '<p>' . esc_html(sprintf(__('Quota remaining today: %d / %d', 'ai-seo-client'), $remaining, self::QUOTA_DAILY)) . '</p>';

        if ($post->post_status !== 'publish') {
            echo '<p>' . esc_html__('Index Now is available after publishing.', 'ai-seo-client') . '</p>';
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=sseo_direct_index_now&post_id=' . $post->ID),
            'sseo_direct_index_' . $post->ID
        );

        echo '<p><a href="' . esc_url($url) . '" class="button">' . esc_html__('Index Now', 'ai-seo-client') . '</a></p>';
    }

    /**
     * Submit a post URL to the Google Indexing API.
     */
    public function submitPost(int $postId, string $type = 'URL_UPDATED'): array
    {
        if (!$this->isConnected() || !$this->hasIndexingScope()) {
            $this->logSubmission($postId, '', $type, false, 'not_connected', __('Google account not connected or missing indexing scope.', 'ai-seo-client'));
            return ['success' => false, 'code' => 'not_connected', 'message' => __('Google account not connected or missing indexing scope.', 'ai-seo-client')];
        }

        $post = get_post($postId);
        if (!$post || !$this->isPostTypeAllowed($post->post_type)) {
            return ['success' => false, 'code' => 'post_type_not_allowed', 'message' => __('Post type not allowed.', 'ai-seo-client')];
        }

        if ($type === 'URL_UPDATED' && $post->post_status !== 'publish') {
            return ['success' => false, 'code' => 'not_published', 'message' => __('Only published posts can be submitted for indexing.', 'ai-seo-client')];
        }

        $url = get_permalink($postId);
        if (!$url || is_wp_error($url)) {
            return ['success' => false, 'code' => 'no_url', 'message' => __('Could not determine post URL.', 'ai-seo-client')];
        }

        return $this->submitUrl($url, $type, $postId);
    }

    /**
     * Submit a URL directly to the Google Indexing API.
     */
    public function submitUrl(string $url, string $type = 'URL_UPDATED', int $postId = 0): array
    {
        $url = esc_url_raw($url);
        if (empty($url)) {
            return ['success' => false, 'code' => 'invalid_url', 'message' => __('Invalid URL.', 'ai-seo-client')];
        }

        if (!$this->hasQuota()) {
            $this->logSubmission($postId, $url, $type, false, 'quota_exceeded', __('Daily Direct Index quota reached.', 'ai-seo-client'));
            return ['success' => false, 'code' => 'quota_exceeded', 'message' => __('Daily Direct Index quota reached.', 'ai-seo-client')];
        }

        $accessToken = $this->oauth->getAccessToken();
        if (empty($accessToken)) {
            $this->logSubmission($postId, $url, $type, false, 'no_token', __('Unable to get Google access token.', 'ai-seo-client'));
            return ['success' => false, 'code' => 'no_token', 'message' => __('Unable to get Google access token.', 'ai-seo-client')];
        }

        $this->incrementQuota();

        $response = wp_remote_post(self::API_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'url' => $url,
                'type' => $type,
            ]),
        ]);

        $this->dashboardAPI->reportGoogleUsage('indexing', 1, 0);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            $this->logSubmission($postId, $url, $type, false, 'request_error', $message);
            $this->health->log('direct_index', 'google', 'error', $message);
            return ['success' => false, 'code' => 'request_error', 'message' => $message];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            $this->logSubmission($postId, $url, $type, true, (string) $code, __('Submitted successfully.', 'ai-seo-client'));
            return ['success' => true, 'code' => $code, 'message' => __('Submitted successfully.', 'ai-seo-client'), 'response' => $body];
        }

        $message = $this->extractErrorMessage($body) ?: sprintf(__('Google Indexing API returned HTTP %d.', 'ai-seo-client'), $code);
        $this->logSubmission($postId, $url, $type, false, (string) $code, $message);
        $this->health->log('direct_index', 'google', 'error', $message);

        return ['success' => false, 'code' => $code, 'message' => $message];
    }

    public function isEnabled(): bool
    {
        return (bool) get_option(self::OPTION_ENABLED, true);
    }

    public function isConnected(): bool
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        return !empty($tokens['access_token']);
    }

    public function hasIndexingScope(): bool
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $scope = $tokens['scope'] ?? '';
        return strpos($scope, 'https://www.googleapis.com/auth/indexing') !== false;
    }

    public function isPostTypeAllowed(string $postType): bool
    {
        if ($postType === 'attachment') {
            return false;
        }

        if (!is_post_type_viewable($postType)) {
            return false;
        }

        $enabled = get_option(self::OPTION_POST_TYPES, []);
        if (!is_array($enabled) || empty($enabled)) {
            return true;
        }

        return in_array($postType, $enabled, true);
    }

    public function getEnabledPostTypes(): array
    {
        $saved = get_option(self::OPTION_POST_TYPES, []);
        if (is_array($saved) && !empty($saved)) {
            return $saved;
        }

        $postTypes = get_post_types(['public' => true], 'names');
        return array_values(array_diff($postTypes, ['attachment']));
    }

    public function getQuotaUsedToday(): int
    {
        $today = current_time('Y-m-d');
        $storedDate = get_option(self::OPTION_QUOTA_DATE, '');

        if ($storedDate !== $today) {
            update_option(self::OPTION_QUOTA_DATE, $today, false);
            update_option(self::OPTION_QUOTA_USED, 0, false);
            return 0;
        }

        return (int) get_option(self::OPTION_QUOTA_USED, 0);
    }

    public function hasQuota(): bool
    {
        return $this->getQuotaUsedToday() < self::QUOTA_DAILY;
    }

    private function incrementQuota(): void
    {
        $used = $this->getQuotaUsedToday() + 1;
        update_option(self::OPTION_QUOTA_USED, $used, false);
    }

    public function getLog(): array
    {
        return get_option(self::OPTION_LOG, []);
    }

    private function logSubmission(int $postId, string $url, string $type, bool $success, string $code, string $message): void
    {
        $log = $this->getLog();

        array_unshift($log, [
            'post_id' => $postId,
            'url' => $url,
            'type' => $type,
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'time' => current_time('mysql'),
        ]);

        $log = array_slice($log, 0, self::LOG_LIMIT);
        update_option(self::OPTION_LOG, $log, false);
    }

    private function extractErrorMessage(string $body): string
    {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return '';
        }

        if (!empty($data['error']['message']) && is_string($data['error']['message'])) {
            return $data['error']['message'];
        }

        if (!empty($data['error_description']) && is_string($data['error_description'])) {
            return $data['error_description'];
        }

        if (!empty($data['error']) && is_string($data['error'])) {
            return $data['error'];
        }

        return '';
    }
}

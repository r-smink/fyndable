<?php

namespace SSEOAIClient;

/**
 * Post Auto-Cleaner
 *
 * Automatically trashes AI-generated posts that receive no traffic (views)
 * within a configurable period. Uses a lightweight internal view counter
 * (post meta `_sseo_ai_view_count`) incremented on frontend single-post
 * visits, with bot and logged-in user filtering.
 *
 * Settings (Settings page → Post Auto-Cleaner):
 *   - sseo_ai_client_autoclean_enabled   (bool,  default false)
 *   - sseo_ai_client_autoclean_days      (int,   default 60)
 *   - sseo_ai_client_autoclean_max_clicks(int,   default 0)
 *
 * Scheduled via wp_schedule_event('daily') — checks once per day.
 */
class PostAutoCleaner
{
    private Settings $settings;

    private const CRON_HOOK = 'sseo_ai_post_autocleaner_check';
    private const VIEW_META = '_sseo_ai_view_count';
    private const PUBLISHED_AT_META = '_sseo_ai_published_at';
    private const GENERATED_META = '_sseo_ai_generated';
    private const LAST_RUN_OPTION = 'sseo_ai_client_autoclean_last_run';
    private const LAST_RUN_COUNT_OPTION = 'sseo_ai_client_autoclean_last_count';

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        // Count frontend views on AI-generated single posts.
        add_action('template_redirect', [$this, 'countView']);

        // Record publication timestamp when an AI post is published.
        add_action('transition_post_status', [$this, 'recordPublishDate'], 10, 3);

        // Schedule daily cleanup cron.
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
        add_action(self::CRON_HOOK, [$this, 'runCleanup']);

        // REST endpoints.
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Increment the view counter for AI-generated posts on frontend visits.
     */
    public function countView(): void
    {
        if (!is_single() || is_admin() || is_user_logged_in()) {
            return;
        }

        $postId = get_the_ID();
        if (!$postId) {
            return;
        }

        // Only count AI-generated posts.
        if (get_post_meta($postId, self::GENERATED_META, true) !== '1') {
            return;
        }

        // Skip bots/crawlers.
        if ($this->isBot()) {
            return;
        }

        $current = (int) get_post_meta($postId, self::VIEW_META, true);
        update_post_meta($postId, self::VIEW_META, $current + 1);
    }

    /**
     * Detect common bots/crawlers via User-Agent.
     */
    private function isBot(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($ua)) {
            return true;
        }
        $bots = [
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'linkedinbot',
            'whatsapp', 'telegrambot', 'applebot', 'petalbot', 'semrush',
            'ahrefsbot', 'mj12bot', 'dotbot', 'bytespider', 'googleother',
            'crawler', 'spider', 'bot/', 'scraper', 'preview',
        ];
        $uaLower = strtolower($ua);
        foreach ($bots as $bot) {
            if (strpos($uaLower, $bot) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record the publication timestamp when a post transitions to 'publish'.
     */
    public function recordPublishDate(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($newStatus === 'publish' && $oldStatus !== 'publish') {
            if (get_post_meta($post->ID, self::GENERATED_META, true) === '1') {
                if (empty(get_post_meta($post->ID, self::PUBLISHED_AT_META, true))) {
                    update_post_meta($post->ID, self::PUBLISHED_AT_META, current_time('mysql'));
                }
            }
        }
    }

    /**
     * Daily cleanup: trash AI-posts that haven't received enough views
     * within the configured period.
     *
     * @return array{trashed: int[], skipped: int} Summary of actions.
     */
    public function runCleanup(): array
    {
        if (!$this->isEnabled()) {
            return ['trashed' => [], 'skipped' => 0];
        }

        $daysThreshold = $this->getDaysThreshold();
        $maxClicks = $this->getMaxClicks();
        $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$daysThreshold} days"));

        $candidates = $this->getCandidates($cutoffDate, $maxClicks);

        $trashed = [];
        foreach ($candidates as $postId) {
            if (wp_trash_post($postId)) {
                $trashed[] = (int) $postId;
            }
        }

        update_option(self::LAST_RUN_OPTION, current_time('mysql'));
        update_option(self::LAST_RUN_COUNT_OPTION, count($trashed));

        return ['trashed' => $trashed, 'skipped' => 0];
    }

    /**
     * Query AI-generated published posts that qualify for cleanup.
     *
     * @return int[] Post IDs.
     */
    private function getCandidates(string $cutoffDate, int $maxClicks): array
    {
        global $wpdb;

        $postsTable = $wpdb->prefix . 'posts';
        $pmTable = $wpdb->prefix . 'postmeta';

        // Join posts with _sseo_ai_generated = '1', filter by publish date
        // (using post_date as fallback when _sseo_ai_published_at is missing)
        // and view count <= maxClicks.
        $sql = $wpdb->prepare(
            "SELECT p.ID FROM {$postsTable} p
             INNER JOIN {$pmTable} pm_gen
                 ON pm_gen.post_id = p.ID
                 AND pm_gen.meta_key = %s
                 AND pm_gen.meta_value = '1'
             LEFT JOIN {$pmTable} pm_pub
                 ON pm_pub.post_id = p.ID
                 AND pm_pub.meta_key = %s
             LEFT JOIN {$pmTable} pm_views
                 ON pm_views.post_id = p.ID
                 AND pm_views.meta_key = %s
             WHERE p.post_type = 'post'
                 AND p.post_status = 'publish'
                 AND COALESCE(pm_pub.meta_value, p.post_date) <= %s
                 AND COALESCE(CAST(pm_views.meta_value AS UNSIGNED), 0) <= %d",
            self::GENERATED_META,
            self::PUBLISHED_AT_META,
            self::VIEW_META,
            $cutoffDate,
            $maxClicks
        );

        $results = $wpdb->get_col($sql);
        return array_map('intval', $results ?: []);
    }

    /**
     * Preview which posts would be trashed (without actually trashing).
     *
     * @return array{posts: array, total: int}
     */
    public function previewCleanup(): array
    {
        $daysThreshold = $this->getDaysThreshold();
        $maxClicks = $this->getMaxClicks();
        $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$daysThreshold} days"));

        $postIds = $this->getCandidates($cutoffDate, $maxClicks);

        $posts = [];
        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (!$post) {
                continue;
            }
            $posts[] = [
                'id'           => $postId,
                'title'        => $post->post_title,
                'post_date'    => $post->post_date,
                'published_at' => get_post_meta($postId, self::PUBLISHED_AT_META, true) ?: $post->post_date,
                'view_count'   => (int) get_post_meta($postId, self::VIEW_META, true),
                'edit_url'     => get_edit_post_link($postId, ''),
            ];
        }

        return ['posts' => $posts, 'total' => count($posts)];
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/autoclean/preview', [
            'methods'  => 'GET',
            'callback' => [$this, 'restPreview'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/autoclean/run', [
            'methods'  => 'POST',
            'callback' => [$this, 'restRun'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/autoclean/status', [
            'methods'  => 'GET',
            'callback' => [$this, 'restStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function restPreview(): \WP_REST_Response
    {
        $result = $this->previewCleanup();
        return new \WP_REST_Response([
            'success' => true,
            'enabled' => $this->isEnabled(),
            'days_threshold' => $this->getDaysThreshold(),
            'max_clicks' => $this->getMaxClicks(),
            'posts' => $result['posts'],
            'total' => $result['total'],
        ], 200);
    }

    public function restRun(): \WP_REST_Response
    {
        $result = $this->runCleanup();
        return new \WP_REST_Response([
            'success' => true,
            'trashed' => $result['trashed'],
            'count'   => count($result['trashed']),
        ], 200);
    }

    public function restStatus(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success'    => true,
            'enabled'    => $this->isEnabled(),
            'days_threshold' => $this->getDaysThreshold(),
            'max_clicks' => $this->getMaxClicks(),
            'last_run'   => get_option(self::LAST_RUN_OPTION, ''),
            'last_count' => (int) get_option(self::LAST_RUN_COUNT_OPTION, 0),
        ], 200);
    }

    private function isEnabled(): bool
    {
        return get_option('sseo_ai_client_autoclean_enabled', '0') === '1';
    }

    private function getDaysThreshold(): int
    {
        return max(1, (int) get_option('sseo_ai_client_autoclean_days', 60));
    }

    private function getMaxClicks(): int
    {
        return max(0, (int) get_option('sseo_ai_client_autoclean_max_clicks', 0));
    }
}

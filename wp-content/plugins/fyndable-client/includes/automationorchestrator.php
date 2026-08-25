<?php

namespace SSEOAIClient;

/**
 * Master Automation Orchestrator
 *
 * Provides a single toggle that automatically runs the full content pipeline:
 * keywords -> topic cluster -> content generation -> scheduled posts.
 * Respects a monthly post limit defined by the license tier.
 */
class AutomationOrchestrator
{
    private Settings $settings;
    private LicenseValidator $licenseValidator;
    private TopicCluster $topicCluster;
    private LlmClient $llm;

    private const SETTINGS_KEY = 'sseo_ai_automation_settings';
    private const LOG_KEY = 'sseo_ai_automation_log';
    private const CRON_HOOK = 'sseo_ai_automation_cron';

    public function __construct(
        Settings $settings,
        LicenseValidator $licenseValidator,
        TopicCluster $topicCluster,
        LlmClient $llm
    ) {
        $this->settings = $settings;
        $this->licenseValidator = $licenseValidator;
        $this->topicCluster = $topicCluster;
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_post_sseo_ai_automation_save', [$this, 'handleSaveSettings']);
        add_action('admin_post_sseo_ai_automation_run_now', [$this, 'handleRunNow']);
        add_action(self::CRON_HOOK, [$this, 'runAutomation']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_filter('cron_schedules', [$this, 'addCronIntervals']);

        // Schedule weekly automation if enabled and not already scheduled.
        $settings = $this->getSettings();
        if ($settings['enabled'] && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $settings['schedule'], self::CRON_HOOK);
        }

        // Ensure the cluster processing cron is always scheduled so queued items are handled.
        if (!wp_next_scheduled('sseo_ai_process_cluster_queue')) {
            wp_schedule_event(time(), 'sseo_ai_queue_interval', 'sseo_ai_process_cluster_queue');
        }
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'fyndable-dashboard',
            __('Automation', 'ai-seo-client'),
            __('Automation', 'ai-seo-client'),
            'manage_options',
            'ai-seo-automation',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/automation/run', [
            'methods' => 'POST',
            'callback' => [$this, 'restRun'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sseo-ai/v1', '/automation/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function addCronIntervals(array $schedules): array
    {
        $schedules['weekly'] = [
            'interval' => 7 * DAY_IN_SECONDS,
            'display' => __('Once Weekly', 'ai-seo-client'),
        ];
        return $schedules;
    }

    public function registerSettings(): void
    {
        register_setting('sseo_ai_automation', self::SETTINGS_KEY, [
            'type' => 'array',
            'default' => $this->defaultSettings(),
        ]);
    }

    private function defaultSettings(): array
    {
        return [
            'enabled' => false,
            'seed_keywords' => [],
            'language' => $this->defaultLanguage(),
            'schedule' => 'weekly',
            'lookahead_weeks' => 4,
            'gap_days' => 3,
            'posts_per_run' => null,
        ];
    }

    private function defaultLanguage(): string
    {
        $locale = get_locale();
        $map = [
            'nl_NL' => 'nl', 'nl_BE' => 'nl', 'de_DE' => 'de', 'de_AT' => 'de',
            'fr_FR' => 'fr', 'fr_BE' => 'fr', 'es_ES' => 'es', 'it_IT' => 'it',
            'pt_BR' => 'pt', 'pl_PL' => 'pl', 'en_GB' => 'en',
        ];
        return $map[$locale] ?? 'en';
    }

    private function getSettings(): array
    {
        $saved = get_option(self::SETTINGS_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_merge($this->defaultSettings(), $saved);
    }

    public function handleSaveSettings(): void
    {
        check_admin_referer('sseo_ai_automation_save');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }

        $settings = $this->getSettings();
        $settings['enabled'] = isset($_POST['enabled']);
        $settings['seed_keywords'] = $this->parseSeedKeywords(sanitize_textarea_field($_POST['seed_keywords'] ?? ''));
        $settings['language'] = sanitize_text_field($_POST['language'] ?? $this->defaultLanguage());
        $settings['lookahead_weeks'] = max(1, min(12, (int) ($_POST['lookahead_weeks'] ?? 4)));
        $settings['gap_days'] = max(1, (int) ($_POST['gap_days'] ?? 3));
        $settings['posts_per_run'] = ($_POST['posts_per_run'] ?? '') === '' ? null : max(1, (int) $_POST['posts_per_run']);

        update_option(self::SETTINGS_KEY, $settings);
        $this->manageCron($settings['enabled'], $settings['schedule']);

        wp_redirect(admin_url('admin.php?page=ai-seo-automation&saved=1'));
        exit;
    }

    private function parseSeedKeywords(string $input): array
    {
        if (empty($input)) {
            return [];
        }
        $keywords = array_map('trim', explode("\n", $input));
        return array_values(array_filter($keywords, fn($k) => !empty($k)));
    }

    public function handleRunNow(): void
    {
        check_admin_referer('sseo_ai_automation_run_now');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }

        $this->runAutomation();
        wp_redirect(admin_url('admin.php?page=ai-seo-automation&ran=1'));
        exit;
    }

    public function restRun(): array
    {
        if (!current_user_can('manage_options')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        return $this->runAutomation();
    }

    public function restStatus(): array
    {
        if (!current_user_can('manage_options')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }
        $settings = $this->getSettings();
        $limit = $this->licenseValidator->getMonthlyAutoPostLimit();
        $monthly = $this->getMonthlyCount();

        return [
            'enabled' => (bool) $settings['enabled'],
            'monthly_limit' => $limit,
            'posts_this_month' => $monthly['count'],
            'next_cron' => wp_next_scheduled(self::CRON_HOOK),
            'last_run' => get_option('sseo_ai_automation_last_run', ''),
        ];
    }

    public function runAutomation(): array
    {
        $settings = $this->getSettings();
        if (!$settings['enabled']) {
            return ['success' => false, 'message' => 'Automation is disabled'];
        }

        if (!$this->licenseValidator->isLicenseValid()) {
            return ['success' => false, 'message' => 'No valid license'];
        }

        $limit = $this->licenseValidator->getMonthlyAutoPostLimit();
        $monthly = $this->getMonthlyCount();
        if ($monthly['yearmonth'] !== date('Y-m')) {
            $monthly = ['yearmonth' => date('Y-m'), 'count' => 0];
            update_option('sseo_ai_automation_monthly_count', $monthly);
        }

        $available = $limit - $monthly['count'];
        if ($available <= 0) {
            $this->log("Monthly limit reached ({$monthly['count']}/{$limit})");
            update_option('sseo_ai_automation_last_run', current_time('mysql'));
            return ['success' => false, 'message' => 'Monthly post limit reached'];
        }

        $postsPerRun = $settings['posts_per_run'] ?? null;
        if ($postsPerRun !== null && $postsPerRun < $available) {
            $available = $postsPerRun;
        }

        $keywords = $this->resolveSeedKeywords($settings);
        if (empty($keywords)) {
            $this->log('No seed keywords or tracked keywords found.');
            update_option('sseo_ai_automation_last_run', current_time('mysql'));
            return ['success' => false, 'message' => 'No seed keywords available'];
        }

        $queued = 0;
        $language = $settings['language'];
        $lookaheadWeeks = (int) $settings['lookahead_weeks'];
        $gapDays = (int) $settings['gap_days'];
        $existingSlugs = $this->getExistingSlugs();
        $startDate = new \DateTime('tomorrow');
        $pages = [];

        foreach ($keywords as $keyword) {
            if ($queued >= $available) {
                break;
            }

            $cluster = $this->topicCluster->generateCluster($keyword, 'standard', $language);
            if (is_wp_error($cluster)) {
                $this->log('Cluster generation failed for "' . $keyword . '": ' . $cluster->get_error_message());
                continue;
            }

            $candidatePages = $this->extractPagesFromCluster($cluster, $keyword);
            foreach ($candidatePages as $page) {
                if ($queued >= $available) {
                    break 2;
                }
                if (isset($existingSlugs[$page['slug']])) {
                    continue;
                }

                $pages[] = $page;
                $queued++;
                if (count($pages) >= $available) {
                    break;
                }
            }
        }

        if (empty($pages)) {
            $this->log('No new pages to schedule after filtering existing content.');
            update_option('sseo_ai_automation_last_run', current_time('mysql'));
            return ['success' => false, 'message' => 'No new pages to schedule'];
        }

        $this->queuePages($pages, $startDate, $gapDays);

        $monthly['count'] += $queued;
        update_option('sseo_ai_automation_monthly_count', $monthly);
        update_option('sseo_ai_automation_last_run', current_time('mysql'));
        $this->log("Queued {$queued} posts for automation.");

        return ['success' => true, 'queued' => $queued, 'message' => "Queued {$queued} posts."];
    }

    private function resolveSeedKeywords(array $settings): array
    {
        $manual = array_filter((array) ($settings['seed_keywords'] ?? []));
        if (!empty($manual)) {
            return array_slice($manual, 0, 10);
        }

        $keywords = [];
        global $wpdb;

        $rankTable = $wpdb->prefix . 'sseo_ai_tracked_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$rankTable}'") === $rankTable) {
            $rows = $wpdb->get_results(
                "SELECT keyword FROM {$rankTable} WHERE active = 1 ORDER BY current_position DESC, best_position ASC LIMIT 10",
                ARRAY_A
            );
            foreach ($rows as $row) {
                if (!empty($row['keyword'])) {
                    $keywords[] = $row['keyword'];
                }
            }
        }

        $kwTable = $wpdb->prefix . 'sseo_ai_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$kwTable}'") === $kwTable) {
            $rows = $wpdb->get_results(
                "SELECT keyword FROM {$kwTable} WHERE status = 'active' ORDER BY search_volume DESC LIMIT 10",
                ARRAY_A
            );
            foreach ($rows as $row) {
                if (!empty($row['keyword'])) {
                    $keywords[] = $row['keyword'];
                }
            }
        }

        if (empty($keywords)) {
            $title = get_bloginfo('name');
            if (!empty($title)) {
                $keywords[] = $title;
            }
        }

        return array_slice(array_values(array_unique($keywords)), 0, 10);
    }

    private function getExistingSlugs(): array
    {
        global $wpdb;
        $slugs = [];
        $posts = $wpdb->get_results(
            "SELECT post_name FROM {$wpdb->posts} WHERE post_status IN ('publish','future','draft','pending','private') AND post_type != 'attachment' LIMIT 10000",
            ARRAY_A
        );
        foreach ($posts as $post) {
            $slugs[$post['post_name']] = true;
        }
        return $slugs;
    }

    private function extractPagesFromCluster(array $cluster, string $keyword): array
    {
        $pages = [];
        $pillar = $cluster['pillar_page'] ?? [];
        if (!empty($pillar['title'])) {
            $pages[] = $this->formatPage($pillar, $keyword, 'pillar');
        }

        $clusters = $cluster['clusters'] ?? [];
        foreach ($clusters as $clusterItem) {
            $hub = $clusterItem['hub_page'] ?? [];
            if (!empty($hub['title'])) {
                $pages[] = $this->formatPage($hub, $keyword, 'hub');
            }
            foreach ($clusterItem['supporting_pages'] ?? [] as $supporting) {
                if (!empty($supporting['title'])) {
                    $pages[] = $this->formatPage($supporting, $keyword, 'supporting');
                }
            }
        }

        return $pages;
    }

    private function formatPage(array $page, string $keyword, string $role): array
    {
        return [
            'title' => sanitize_text_field($page['title']),
            'keyword' => sanitize_text_field($page['target_keyword'] ?? $keyword),
            'slug' => sanitize_title($page['slug'] ?? $page['title']),
            'word_count' => (int) ($page['target_word_count'] ?? 1500),
            'content_type' => sanitize_text_field($page['content_type'] ?? 'article'),
            'cluster_role' => $role,
        ];
    }

    private function queuePages(array $pages, \DateTime $startDate, int $gapDays): void
    {
        $queues = get_option('sseo_ai_cluster_queues', []);
        if (!is_array($queues)) {
            $queues = [];
        }
        $queueId = count($queues) + 1;

        $queueItems = [];
        foreach ($pages as $index => $page) {
            $scheduleDate = clone $startDate;
            $scheduleDate->modify('+' . ($index * $gapDays) . ' days');

            $queueItems[] = [
                'title' => $page['title'],
                'keyword' => $page['keyword'],
                'word_count' => $page['word_count'],
                'content_type' => $page['content_type'],
                'cluster_role' => $page['cluster_role'],
                'schedule_date' => $scheduleDate->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'post_id' => null,
                'error' => null,
                'attempts' => 0,
            ];
        }

        $queue = [
            'id' => $queueId,
            'cluster_id' => 0,
            'cluster_map_id' => 0,
            'items' => $queueItems,
            'total' => count($queueItems),
            'completed' => 0,
            'failed' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'started_at' => null,
            'completed_at' => null,
        ];

        $queues[] = $queue;
        update_option('sseo_ai_cluster_queues', $queues);

        if (!wp_next_scheduled('sseo_ai_process_cluster_queue')) {
            wp_schedule_event(time() + 60, 'sseo_ai_queue_interval', 'sseo_ai_process_cluster_queue');
        }
    }

    private function manageCron(bool $enabled, string $schedule): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }

        if ($enabled) {
            $interval = in_array($schedule, ['daily', 'weekly'], true) ? $schedule : 'weekly';
            wp_schedule_event(time(), $interval, self::CRON_HOOK);
        }
    }

    private function getMonthlyCount(): array
    {
        $saved = get_option('sseo_ai_automation_monthly_count', []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_merge(['yearmonth' => date('Y-m'), 'count' => 0], $saved);
    }

    private function log(string $message): void
    {
        $logs = get_option(self::LOG_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }
        $logs[] = [
            'time' => current_time('mysql'),
            'message' => sanitize_text_field($message),
        ];
        $logs = array_slice($logs, -50);
        update_option(self::LOG_KEY, $logs);
    }

    public function renderPage(): void
    {
        $settings = $this->getSettings();
        $monthly = $this->getMonthlyCount();
        $limit = $this->licenseValidator->getMonthlyAutoPostLimit();
        $logs = get_option(self::LOG_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Content Automation', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Automatically generate topic clusters and schedule posts.', 'ai-seo-client'); ?></p>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card" style="max-width: 900px;">
                    <?php if (isset($_GET['saved'])): ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'ai-seo-client'); ?></p></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['ran'])): ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Automation run started. Check the log below.', 'ai-seo-client'); ?></p></div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sseo_ai_automation_save'); ?>
                        <input type="hidden" name="action" value="sseo_ai_automation_save">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="enabled"><?php esc_html_e('Enable automation', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <label><input type="checkbox" name="enabled" id="enabled" value="1" <?php checked($settings['enabled']); ?>> <?php esc_html_e('Run the automation weekly', 'ai-seo-client'); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="seed_keywords"><?php esc_html_e('Optional seed keywords', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <textarea name="seed_keywords" id="seed_keywords" rows="4" class="large-text" placeholder="one keyword per line"><?php echo esc_textarea(implode("\n", (array) $settings['seed_keywords'])); ?></textarea>
                                    <p class="description"><?php esc_html_e('Leave empty to use tracked keywords or site title.', 'ai-seo-client'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="language"><?php esc_html_e('Language', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <select name="language" id="language">
                                        <option value="nl" <?php selected($settings['language'], 'nl'); ?>>Nederlands</option>
                                        <option value="en" <?php selected($settings['language'], 'en'); ?>>English</option>
                                        <option value="de" <?php selected($settings['language'], 'de'); ?>>Deutsch</option>
                                        <option value="fr" <?php selected($settings['language'], 'fr'); ?>>Français</option>
                                        <option value="es" <?php selected($settings['language'], 'es'); ?>>Español</option>
                                        <option value="it" <?php selected($settings['language'], 'it'); ?>>Italiano</option>
                                        <option value="pt" <?php selected($settings['language'], 'pt'); ?>>Português</option>
                                        <option value="pl" <?php selected($settings['language'], 'pl'); ?>>Polski</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lookahead_weeks"><?php esc_html_e('Schedule ahead (weeks)', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="number" name="lookahead_weeks" id="lookahead_weeks" value="<?php echo esc_attr($settings['lookahead_weeks']); ?>" min="1" max="12" step="1">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="gap_days"><?php esc_html_e('Gap between posts (days)', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="number" name="gap_days" id="gap_days" value="<?php echo esc_attr($settings['gap_days']); ?>" min="1" step="1">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="posts_per_run"><?php esc_html_e('Max posts per run', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="number" name="posts_per_run" id="posts_per_run" value="<?php echo $settings['posts_per_run'] ? esc_attr($settings['posts_per_run']) : ''; ?>" min="1" step="1" placeholder="license limit">
                                    <p class="description"><?php esc_html_e('Leave empty to use the license monthly limit.', 'ai-seo-client'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save Settings', 'ai-seo-client')); ?>
                    </form>

                    <hr style="margin: 30px 0;">

                    <h2><?php esc_html_e('Status', 'ai-seo-client'); ?></h2>
                    <p>
                        <strong><?php esc_html_e('Posts this month:', 'ai-seo-client'); ?></strong> <?php echo esc_html($monthly['count']); ?> / <?php echo esc_html($limit === PHP_INT_MAX ? 'unlimited' : $limit); ?><br>
                        <strong><?php esc_html_e('Last run:', 'ai-seo-client'); ?></strong> <?php echo esc_html(get_option('sseo_ai_automation_last_run', 'never')); ?><br>
                        <strong><?php esc_html_e('Next scheduled run:', 'ai-seo-client'); ?></strong> <?php echo esc_html(wp_next_scheduled(self::CRON_HOOK) ? date('Y-m-d H:i:s', wp_next_scheduled(self::CRON_HOOK)) : 'not scheduled'); ?>
                    </p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 15px;">
                        <?php wp_nonce_field('sseo_ai_automation_run_now'); ?>
                        <input type="hidden" name="action" value="sseo_ai_automation_run_now">
                        <?php submit_button(__('Run Automation Now', 'ai-seo-client'), 'secondary'); ?>
                    </form>

                    <h2 style="margin-top: 30px;"><?php esc_html_e('Recent log', 'ai-seo-client'); ?></h2>
                    <ul style="font-size: 12px; color: #555;">
                        <?php foreach (array_reverse($logs) as $log): ?>
                            <li><?php echo esc_html($log['time'] ?? ''); ?> — <?php echo esc_html($log['message'] ?? ''); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
}

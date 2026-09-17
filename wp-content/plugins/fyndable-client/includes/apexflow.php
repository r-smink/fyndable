<?php

namespace SSEOAIClient;

/**
 * ApexFlow — fully automatic content autopilot.
 *
 * A single toggle that runs the complete content pipeline:
 * site scan -> niche profile -> regional SERP keyword research ->
 * content calendar (lookahead weeks) -> queued post generation -> weekly refill.
 *
 * Replaces the former AutomationOrchestrator. Reuses the shared
 * `sseo_ai_cluster_queues` option processed by TopicCluster::processQueueItems.
 *
 * Professional+ only (professional, business, agency, dev — no trial).
 */
class ApexFlow
{
    private Settings $settings;
    private LicenseValidator $licenseValidator;
    private DashboardAPI $dashboardAPI;
    private LlmClient $llm;
    private ?TopicCluster $topicCluster;
    private ?KeywordExplorer $keywordExplorer;
    private ?LocalSerp $localSerp;

    private const SETTINGS_KEY = 'sseo_ai_apexflow_settings';
    private const PROFILE_KEY = 'sseo_ai_apexflow_profile';
    private const POOL_KEY = 'sseo_ai_apexflow_keyword_pool';
    private const PLAN_KEY = 'sseo_ai_apexflow_plan';
    private const LOG_KEY = 'sseo_ai_apexflow_log';
    private const MONTHLY_KEY = 'sseo_ai_apexflow_monthly_count';
    private const LAST_RUN_KEY = 'sseo_ai_apexflow_last_run';
    private const CRON_HOOK = 'sseo_ai_apexflow_weekly';

    /** Tiers that may use ApexFlow (no trial — autopilot consumes real SERP/AI budget). */
    private const ALLOWED_TIERS = ['professional', 'business', 'agency', 'dev'];

    /** Re-scan the site profile when older than this. */
    private const PROFILE_MAX_AGE = 30 * DAY_IN_SECONDS;

    /** Refresh the keyword pool when older than this. */
    private const POOL_MAX_AGE = 7 * DAY_IN_SECONDS;

    /** API cost guardrails per run. */
    private const MAX_SEED_EXPANSIONS = 5;
    private const MAX_LOCAL_PACK_SCANS = 3;
    private const MAX_KEYWORD_DATA_BATCH = 25;
    private const POOL_SIZE = 50;

    public function __construct(
        Settings $settings,
        LicenseValidator $licenseValidator,
        DashboardAPI $dashboardAPI,
        LlmClient $llm,
        ?TopicCluster $topicCluster = null,
        ?KeywordExplorer $keywordExplorer = null,
        ?LocalSerp $localSerp = null
    ) {
        $this->settings = $settings;
        $this->licenseValidator = $licenseValidator;
        $this->dashboardAPI = $dashboardAPI;
        $this->llm = $llm;
        $this->topicCluster = $topicCluster;
        $this->keywordExplorer = $keywordExplorer;
        $this->localSerp = $localSerp;
    }

    public function register(): void
    {
        $this->migrateFromAutomation();

        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_post_sseo_ai_apexflow_save', [$this, 'handleSaveSettings']);
        add_action('admin_post_sseo_ai_apexflow_run_now', [$this, 'handleRunNow']);
        add_action('admin_post_sseo_ai_apexflow_rescan', [$this, 'handleRescan']);
        add_action('admin_post_sseo_ai_apexflow_preview', [$this, 'handlePreview']);
        add_action('admin_post_sseo_ai_apexflow_reject', [$this, 'handleRejectKeyword']);
        add_action(self::CRON_HOOK, [$this, 'runAutomation']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        $settings = $this->getSettings();
        if ($settings['enabled'] && $this->isAllowed() && !wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'weekly', self::CRON_HOOK);
        }

        // Ensure the shared cluster queue processor is scheduled so queued items are handled.
        if (!wp_next_scheduled('sseo_ai_process_cluster_queue')) {
            wp_schedule_event(time(), 'sseo_ai_queue_interval', 'sseo_ai_process_cluster_queue');
        }
    }

    /**
     * One-time migration from the legacy Automation settings, and cleanup of its cron.
     */
    private function migrateFromAutomation(): void
    {
        if (wp_next_scheduled('sseo_ai_automation_cron')) {
            wp_clear_scheduled_hook('sseo_ai_automation_cron');
        }

        if (get_option(self::SETTINGS_KEY) !== false) {
            return;
        }

        $legacy = get_option('sseo_ai_automation_settings');
        if (!is_array($legacy)) {
            return;
        }

        $settings = $this->defaultSettings();
        $settings['enabled'] = (bool) ($legacy['enabled'] ?? false);
        $settings['seed_keywords'] = (array) ($legacy['seed_keywords'] ?? []);
        if (!empty($legacy['language'])) {
            $settings['language'] = sanitize_text_field($legacy['language']);
        }
        if (!empty($legacy['lookahead_weeks'])) {
            $settings['lookahead_weeks'] = max(1, min(8, (int) $legacy['lookahead_weeks']));
        }
        update_option(self::SETTINGS_KEY, $settings);
    }

    public function registerSettings(): void
    {
        register_setting('sseo_ai_apexflow', self::SETTINGS_KEY, [
            'type' => 'array',
            'default' => $this->defaultSettings(),
        ]);
    }

    private function defaultSettings(): array
    {
        $options = $this->settings->all();
        $defaultCountry = strtolower((string) ($options['local_country'] ?? ''));
        if (strlen($defaultCountry) !== 2) {
            $defaultCountry = substr(strtolower((string) get_locale()), 3, 2) ?: 'nl';
        }

        return [
            'enabled' => false,
            'language' => $this->settings->contentLanguage(),
            'country' => $defaultCountry,
            'posts_per_week' => 2,
            'publish_mode' => 'schedule',
            'publish_days' => [2],
            'publish_time' => '09:00',
            'seed_keywords' => [],
            'excluded_topics' => [],
            'post_type' => 'post',
            'category_id' => 0,
            'author_id' => 0,
            'word_count' => 1500,
            'featured_image' => true,
            'notify_email' => true,
            'lookahead_weeks' => 4,
        ];
    }

    public function getSettings(): array
    {
        $saved = get_option(self::SETTINGS_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_merge($this->defaultSettings(), $saved);
    }

    public function isAllowed(): bool
    {
        return in_array($this->licenseValidator->getLicenseTier(), self::ALLOWED_TIERS, true);
    }

    // ------------------------------------------------------------------
    // Form handlers
    // ------------------------------------------------------------------

    public function handleSaveSettings(): void
    {
        check_admin_referer('sseo_ai_apexflow_save');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }

        $settings = $this->getSettings();
        $settings['enabled'] = isset($_POST['enabled']);
        $settings['language'] = sanitize_text_field($_POST['language'] ?? $settings['language']);
        $settings['country'] = strtolower(sanitize_text_field($_POST['country'] ?? $settings['country']));
        $settings['posts_per_week'] = max(1, min(7, (int) ($_POST['posts_per_week'] ?? 2)));
        $settings['publish_mode'] = in_array($_POST['publish_mode'] ?? '', ['schedule', 'draft'], true)
            ? $_POST['publish_mode'] : 'schedule';
        $days = array_map('intval', (array) ($_POST['publish_days'] ?? []));
        $settings['publish_days'] = array_values(array_filter($days, fn($d) => $d >= 1 && $d <= 7)) ?: [2];
        $settings['publish_time'] = preg_match('/^\d{2}:\d{2}$/', (string) ($_POST['publish_time'] ?? ''))
            ? $_POST['publish_time'] : '09:00';
        $settings['seed_keywords'] = $this->parseLines(sanitize_textarea_field($_POST['seed_keywords'] ?? ''));
        $settings['excluded_topics'] = $this->parseLines(sanitize_textarea_field($_POST['excluded_topics'] ?? ''));
        $settings['post_type'] = post_type_exists((string) ($_POST['post_type'] ?? 'post'))
            ? sanitize_text_field($_POST['post_type']) : 'post';
        $settings['category_id'] = max(0, (int) ($_POST['category_id'] ?? 0));
        $settings['author_id'] = max(0, (int) ($_POST['author_id'] ?? 0));
        $settings['word_count'] = max(300, min(5000, (int) ($_POST['word_count'] ?? 1500)));
        $settings['featured_image'] = isset($_POST['featured_image']);
        $settings['notify_email'] = isset($_POST['notify_email']);
        $settings['lookahead_weeks'] = max(1, min(8, (int) ($_POST['lookahead_weeks'] ?? 4)));

        update_option(self::SETTINGS_KEY, $settings);

        // Brand voice quick fields — written to the shared Brand Voice settings so
        // every AI feature (not just ApexFlow) follows the same voice.
        $bv = get_option('sseo_ai_brand_voice', []);
        if (!is_array($bv)) {
            $bv = [];
        }
        $bv['tone'] = sanitize_text_field($_POST['bv_tone'] ?? ($bv['tone'] ?? 'professional'));
        $bv['audience'] = sanitize_text_field($_POST['bv_audience'] ?? ($bv['audience'] ?? ''));
        $bv['voice_description'] = sanitize_textarea_field($_POST['bv_voice_description'] ?? ($bv['voice_description'] ?? ''));
        $bv['enabled'] = isset($_POST['bv_enabled']) || !empty($bv['voice_description']);
        update_option('sseo_ai_brand_voice', $bv);

        // Editable site profile fields (industry / audience / topics override).
        $profile = $this->getProfile();
        $profile['industry'] = sanitize_text_field($_POST['profile_industry'] ?? ($profile['industry'] ?? ''));
        $profile['audience'] = sanitize_text_field($_POST['profile_audience'] ?? ($profile['audience'] ?? ''));
        $profile['topics'] = $this->parseLines(sanitize_text_field($_POST['profile_topics'] ?? implode(', ', (array) ($profile['topics'] ?? []))), ',');
        update_option(self::PROFILE_KEY, $profile);

        $this->manageCron($settings['enabled']);

        wp_redirect(admin_url('admin.php?page=ai-seo-automation&saved=1'));
        exit;
    }

    public function handleRunNow(): void
    {
        check_admin_referer('sseo_ai_apexflow_run_now');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }
        $this->runAutomation(true);
        wp_redirect(admin_url('admin.php?page=ai-seo-automation&ran=1'));
        exit;
    }

    public function handleRescan(): void
    {
        check_admin_referer('sseo_ai_apexflow_rescan');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }
        delete_option(self::PROFILE_KEY);
        $this->scanSiteProfile();
        wp_redirect(admin_url('admin.php?page=ai-seo-automation&scanned=1'));
        exit;
    }

    public function handlePreview(): void
    {
        check_admin_referer('sseo_ai_apexflow_preview');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }
        $this->ensureProfile();
        $this->ensurePool();
        $plan = $this->buildPlan(0, false);
        set_transient('sseo_ai_apexflow_preview_' . get_current_user_id(), $plan, HOUR_IN_SECONDS);
        wp_redirect(admin_url('admin.php?page=ai-seo-automation&preview=1'));
        exit;
    }

    public function handleRejectKeyword(): void
    {
        check_admin_referer('sseo_ai_apexflow_reject');
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        if ($keyword !== '') {
            $this->rejectKeyword($keyword);
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-automation&rejected=1'));
        exit;
    }

    private function parseLines(string $input, string $separator = "\n"): array
    {
        if (empty($input)) {
            return [];
        }
        $items = array_map('trim', explode($separator, $input));
        return array_values(array_filter($items, fn($i) => $i !== ''));
    }

    private function manageCron(bool $enabled): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        if ($enabled) {
            wp_schedule_event(time(), 'weekly', self::CRON_HOOK);
        }
    }

    // ------------------------------------------------------------------
    // REST API
    // ------------------------------------------------------------------

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/apexflow/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restStatus'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('sseo-ai/v1', '/apexflow/run', [
            'methods' => 'POST',
            'callback' => [$this, 'restRun'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('sseo-ai/v1', '/apexflow/preview', [
            'methods' => 'POST',
            'callback' => [$this, 'restPreview'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('sseo-ai/v1', '/apexflow/rescan-profile', [
            'methods' => 'POST',
            'callback' => [$this, 'restRescan'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
        register_rest_route('sseo-ai/v1', '/apexflow/reject-keyword', [
            'methods' => 'POST',
            'callback' => [$this, 'restReject'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => ['keyword' => ['type' => 'string', 'required' => true]],
        ]);
    }

    public function restStatus(): array|\WP_Error
    {
        if (!$this->isAllowed()) {
            return new \WP_Error('tier_not_allowed', __('ApexFlow requires a Professional or higher license.', 'ai-seo-client'));
        }
        $settings = $this->getSettings();
        $monthly = $this->getMonthlyCount();
        return [
            'enabled' => (bool) $settings['enabled'],
            'monthly_limit' => $this->licenseValidator->getMonthlyAutoPostLimit(),
            'posts_this_month' => $monthly['count'],
            'pool_size' => count($this->getPool()['keywords'] ?? []),
            'planned' => count(array_filter($this->getPlan(), fn($p) => ($p['status'] ?? '') === 'planned')),
            'next_cron' => wp_next_scheduled(self::CRON_HOOK),
            'last_run' => get_option(self::LAST_RUN_KEY, ''),
            'profile' => $this->getProfile(),
        ];
    }

    public function restRun(): array|\WP_Error
    {
        if (!$this->isAllowed()) {
            return new \WP_Error('tier_not_allowed', __('ApexFlow requires a Professional or higher license.', 'ai-seo-client'));
        }
        return $this->runAutomation(true);
    }

    public function restPreview(): array|\WP_Error
    {
        if (!$this->isAllowed()) {
            return new \WP_Error('tier_not_allowed', __('ApexFlow requires a Professional or higher license.', 'ai-seo-client'));
        }
        $this->ensureProfile();
        $this->ensurePool();
        return ['success' => true, 'plan' => $this->buildPlan(0, false)];
    }

    public function restRescan(): array|\WP_Error
    {
        if (!$this->isAllowed()) {
            return new \WP_Error('tier_not_allowed', __('ApexFlow requires a Professional or higher license.', 'ai-seo-client'));
        }
        delete_option(self::PROFILE_KEY);
        return ['success' => true, 'profile' => $this->scanSiteProfile()];
    }

    public function restReject(\WP_REST_Request $request): array|\WP_Error
    {
        if (!$this->isAllowed()) {
            return new \WP_Error('tier_not_allowed', __('ApexFlow requires a Professional or higher license.', 'ai-seo-client'));
        }
        $this->rejectKeyword(sanitize_text_field($request->get_param('keyword')));
        return ['success' => true];
    }

    // ------------------------------------------------------------------
    // Main pipeline
    // ------------------------------------------------------------------

    /**
     * Run the full autopilot cycle. Called weekly by cron or manually.
     */
    public function runAutomation(bool $manual = false): array
    {
        $settings = $this->getSettings();
        if (!$settings['enabled'] && !$manual) {
            return ['success' => false, 'message' => 'ApexFlow is disabled'];
        }

        if (!$this->isAllowed()) {
            return ['success' => false, 'message' => 'ApexFlow requires a Professional or higher license'];
        }

        if (!$this->licenseValidator->isLicenseValid()) {
            return ['success' => false, 'message' => 'No valid license'];
        }

        $limit = $this->licenseValidator->getMonthlyAutoPostLimit();
        $monthly = $this->getMonthlyCount();
        if ($monthly['yearmonth'] !== date('Y-m')) {
            $monthly = ['yearmonth' => date('Y-m'), 'count' => 0];
            update_option(self::MONTHLY_KEY, $monthly);
        }

        $available = $limit - $monthly['count'];
        if ($available <= 0) {
            $this->log("Monthly limit reached ({$monthly['count']}/{$limit})");
            update_option(self::LAST_RUN_KEY, current_time('mysql'));
            return ['success' => false, 'message' => 'Monthly post limit reached'];
        }

        // 1. Site profile (niche detection) — scan once, refresh monthly.
        $profile = $this->ensureProfile();
        if (empty($profile['industry']) && empty($profile['topics'])) {
            $this->log('Site profile could not be determined. Set seed keywords manually.');
        }

        // 2. Keyword pool — refresh when stale.
        $this->ensurePool();

        // 3. Build the calendar for the lookahead window, then enqueue every
        // still-unqueued plan entry (including leftovers from a previous run).
        $this->buildPlan($available);
        $pending = array_values(array_filter(
            $this->getPlan(),
            fn($e) => ($e['status'] ?? '') === 'planned'
        ));
        if (empty($pending)) {
            $this->log('No new topics to schedule (pool empty or all slots filled).');
            update_option(self::LAST_RUN_KEY, current_time('mysql'));
            return ['success' => false, 'message' => 'No new topics to schedule'];
        }

        // Respect the monthly cap.
        if (count($pending) > $available) {
            $pending = array_slice($pending, 0, $available);
        }

        // 4. Enqueue into the shared generation queue.
        $queued = $this->enqueuePlan($pending);

        $monthly['count'] += $queued;
        update_option(self::MONTHLY_KEY, $monthly);
        update_option(self::LAST_RUN_KEY, current_time('mysql'));

        $this->log("ApexFlow queued {$queued} posts (profile: " . ($profile['industry'] ?? 'unknown') . ").");
        if ($settings['notify_email']) {
            $this->sendSummaryEmail($pending);
        }

        return ['success' => true, 'queued' => $queued, 'message' => "Queued {$queued} posts."];
    }

    // ------------------------------------------------------------------
    // Step 1: site profile
    // ------------------------------------------------------------------

    public function getProfile(): array
    {
        $profile = get_option(self::PROFILE_KEY, []);
        return is_array($profile) ? $profile : [];
    }

    private function ensureProfile(): array
    {
        $profile = $this->getProfile();
        $age = time() - strtotime($profile['scanned_at'] ?? '1970-01-01');
        if (empty($profile) || $age > self::PROFILE_MAX_AGE) {
            $profile = $this->scanSiteProfile();
        }
        return $profile;
    }

    /**
     * Analyze the website to detect the niche/industry, audience and seed topics.
     */
    public function scanSiteProfile(): array
    {
        $signals = $this->collectSiteSignals();
        $langName = $this->settings->contentLanguageName();

        $prompt = "Analyze this website and determine its niche. Return JSON only (no markdown) with:
{
    \"industry\": \"short industry label e.g. 'plumbing services', 'saas marketing'\",
    \"niche_description\": \"one sentence describing what the site/business does\",
    \"audience\": \"who the target audience is\",
    \"topics\": [\"5-10 core topic areas the site covers or should cover\"],
    \"suggested_seed_keywords\": [\"8-12 SEO seed keywords in {$langName} matching the niche\"]
}

Website signals:
{$signals}

Return ONLY the JSON.";

        $profile = [];
        $response = $this->llm->generateText($prompt, ['use_case' => 'analysis', 'max_tokens' => 1200]);
        if (!is_wp_error($response)) {
            $raw = trim($response);
            if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
                $raw = trim($m[1]);
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $profile = $decoded;
            }
        }

        if (empty($profile)) {
            $profile = $this->fallbackProfile();
        }

        $profile['industry'] = sanitize_text_field($profile['industry'] ?? '');
        $profile['niche_description'] = sanitize_text_field($profile['niche_description'] ?? '');
        $profile['audience'] = sanitize_text_field($profile['audience'] ?? '');
        $profile['topics'] = array_values(array_filter(array_map('sanitize_text_field', (array) ($profile['topics'] ?? []))));
        $profile['suggested_seed_keywords'] = array_values(array_filter(array_map('sanitize_text_field', (array) ($profile['suggested_seed_keywords'] ?? []))));
        $profile['scanned_at'] = current_time('mysql');

        update_option(self::PROFILE_KEY, $profile);
        $this->log('Site profile scanned: ' . ($profile['industry'] ?: 'unknown industry'));

        return $profile;
    }

    /**
     * Gather on-site signals for the niche scan: homepage text, recent titles,
     * business settings, industry option and WooCommerce categories.
     */
    private function collectSiteSignals(): string
    {
        $parts = [];
        $parts[] = 'Site title: ' . get_bloginfo('name');
        $tagline = get_bloginfo('description');
        if ($tagline) {
            $parts[] = 'Tagline: ' . $tagline;
        }

        // Homepage content — prefer the static front page, else fetch the URL.
        $homeText = '';
        $frontId = (int) get_option('page_on_front');
        if ($frontId) {
            $front = get_post($frontId);
            if ($front) {
                $homeText = wp_strip_all_tags($front->post_content);
            }
        }
        if (trim($homeText) === '') {
            $resp = wp_remote_get(home_url('/'), ['timeout' => 15, 'sslverify' => $this->settings->sslVerify()]);
            if (!is_wp_error($resp)) {
                $homeText = wp_strip_all_tags(wp_remote_retrieve_body($resp));
            }
        }
        $homeText = trim(preg_replace('/\s+/', ' ', $homeText));
        if ($homeText !== '') {
            $parts[] = 'Homepage content: ' . substr($homeText, 0, 4000);
        }

        $recent = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        if (!empty($recent)) {
            $titles = [];
            foreach ($recent as $p) {
                $line = $p->post_title;
                $excerpt = trim(wp_strip_all_tags($p->post_excerpt ?: $p->post_content));
                if ($excerpt !== '') {
                    $line .= ' — ' . substr($excerpt, 0, 140);
                }
                $titles[] = $line;
            }
            $parts[] = "Recent posts:\n- " . implode("\n- ", $titles);
        }

        $options = $this->settings->all();
        $industry = get_option('sseo_ai_industry', '');
        if ($industry) {
            $parts[] = 'Industry (user setting): ' . $industry;
        }
        $business = array_filter([
            $options['local_business_name'] ?? '',
            $options['local_business_type'] ?? '',
            $options['local_city'] ?? '',
            $options['local_country'] ?? '',
        ]);
        if (!empty($business)) {
            $parts[] = 'Local business: ' . implode(', ', $business);
        }

        if (class_exists('WooCommerce')) {
            $terms = get_terms(['taxonomy' => 'product_cat', 'number' => 15, 'hide_empty' => true]);
            if (!is_wp_error($terms) && !empty($terms)) {
                $parts[] = 'Shop categories: ' . implode(', ', wp_list_pluck($terms, 'name'));
            }
        }

        return implode("\n\n", $parts);
    }

    private function fallbackProfile(): array
    {
        return [
            'industry' => get_option('sseo_ai_industry', '') ?: get_bloginfo('name'),
            'niche_description' => get_bloginfo('description'),
            'audience' => '',
            'topics' => [],
            'suggested_seed_keywords' => array_filter([get_bloginfo('name')]),
        ];
    }

    // ------------------------------------------------------------------
    // Step 2: keyword pool
    // ------------------------------------------------------------------

    public function getPool(): array
    {
        $pool = get_option(self::POOL_KEY, []);
        return is_array($pool) ? $pool : [];
    }

    private function ensurePool(bool $force = false): array
    {
        $pool = $this->getPool();
        $age = time() - strtotime($pool['generated_at'] ?? '1970-01-01');
        if ($force || empty($pool['keywords']) || $age > self::POOL_MAX_AGE) {
            $pool = $this->refreshKeywordPool();
        }
        return $pool['keywords'] ?? [];
    }

    /**
     * Build the candidate keyword pool: seed expansion via SERP, volume and
     * difficulty via DataForSEO, optional local-pack intent detection.
     */
    public function refreshKeywordPool(): array
    {
        $settings = $this->getSettings();
        $seeds = $this->getSeeds();
        $covered = $this->getCoveredKeywords();
        $excluded = array_map('mb_strtolower', (array) $settings['excluded_topics']);

        $candidates = [];
        $expanded = 0;
        foreach ($seeds as $seed) {
            if ($expanded >= self::MAX_SEED_EXPANSIONS) {
                $candidates[] = $seed;
                continue;
            }
            $expanded++;
            $candidates[] = $seed;

            if ($this->keywordExplorer) {
                $expansion = $this->keywordExplorer->expand($seed, [
                    'country' => $settings['country'],
                    'language' => $settings['language'],
                ]);
                foreach (array_keys((array) ($expansion['related'] ?? [])) as $term) {
                    $candidates[] = $term;
                }
                foreach ((array) ($expansion['trends']['related_queries'] ?? []) as $rq) {
                    $q = is_array($rq) ? ($rq['query'] ?? '') : (string) $rq;
                    if ($q !== '') {
                        $candidates[] = $q;
                    }
                }
            }
        }

        $candidates = array_values(array_unique(array_filter(array_map('trim', $candidates))));

        // Batch search volume / difficulty via DataForSEO (one call).
        $keywordData = $this->fetchKeywordData(array_slice($candidates, 0, self::MAX_KEYWORD_DATA_BATCH));

        // Local intent detection for the strongest candidates.
        $localIntent = $this->detectLocalIntent($candidates, $settings);

        // Score, dedupe against covered/excluded keywords, keep the pool filled.
        $scored = [];
        foreach ($candidates as $kw) {
            $lower = mb_strtolower($kw);
            $isExcluded = false;
            foreach ($excluded as $ex) {
                if ($ex !== '' && ($lower === $ex || str_contains($lower, $ex))) {
                    $isExcluded = true;
                    break;
                }
            }
            if ($isExcluded || $this->isCovered($kw, $covered)) {
                continue;
            }

            $data = $keywordData[$lower] ?? [];
            $volume = (int) ($data['search_volume'] ?? 0);
            $difficulty = (float) ($data['difficulty'] ?? 50);
            $score = round(max(0.1, log10(max(10, $volume + 10)) * (1 - min($difficulty, 90) / 100)), 2);

            $scored[$lower] = [
                'keyword' => $kw,
                'volume' => $volume,
                'difficulty' => $difficulty,
                'score' => $score + (isset($localIntent[$lower]) ? 0.5 : 0),
                'local_intent' => isset($localIntent[$lower]),
                'status' => 'new',
            ];
        }

        // Add city-modified variants for local-intent keywords.
        $city = $this->settings->get('local_city', '');
        if ($city !== '') {
            foreach (array_keys($localIntent) as $lower) {
                if (isset($scored[$lower]) && !str_contains($lower, mb_strtolower($city))) {
                    $variant = $scored[$lower]['keyword'] . ' ' . $city;
                    $vKey = mb_strtolower($variant);
                    if (!isset($scored[$vKey]) && !$this->isCovered($variant, $covered)) {
                        $scored[$vKey] = [
                            'keyword' => $variant,
                            'volume' => 0,
                            'difficulty' => $scored[$lower]['difficulty'],
                            'score' => $scored[$lower]['score'] + 0.3,
                            'local_intent' => true,
                            'status' => 'new',
                        ];
                    }
                }
            }
        }

        // Preserve status of previously planned/used/rejected keywords.
        $existing = [];
        foreach ((array) ($this->getPool()['keywords'] ?? []) as $item) {
            $existing[mb_strtolower($item['keyword'] ?? '')] = $item['status'] ?? 'new';
        }
        foreach ($scored as $key => &$item) {
            if (isset($existing[$key]) && $existing[$key] !== 'new') {
                $item['status'] = $existing[$key];
            }
        }
        unset($item);

        uasort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $poolItems = array_slice(array_values($scored), 0, self::POOL_SIZE);

        $pool = [
            'generated_at' => current_time('mysql'),
            'country' => $settings['country'],
            'language' => $settings['language'],
            'keywords' => $poolItems,
        ];
        update_option(self::POOL_KEY, $pool);
        $this->log('Keyword pool refreshed: ' . count($poolItems) . ' candidates.');

        return $pool;
    }

    /**
     * Ordered seed keywords: manual settings > profile suggestions > tracked
     * keywords > GSC top queries > site title.
     */
    private function getSeeds(): array
    {
        $settings = $this->getSettings();
        $seeds = (array) $settings['seed_keywords'];

        $profile = $this->getProfile();
        foreach ((array) ($profile['suggested_seed_keywords'] ?? []) as $kw) {
            $seeds[] = $kw;
        }

        global $wpdb;
        $rankTable = $wpdb->prefix . 'sseo_ai_tracked_keywords';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$rankTable}'") === $rankTable) {
            $rows = $wpdb->get_col(
                "SELECT keyword FROM {$rankTable} WHERE active = 1 ORDER BY current_position DESC, best_position ASC LIMIT 10"
            );
            foreach ((array) $rows as $kw) {
                if (!empty($kw)) {
                    $seeds[] = $kw;
                }
            }
        }

        // GSC top queries as extra seeds when connected (best-effort).
        try {
            $gsc = new GscClient($this->settings);
            if ($gsc->isConnected()) {
                $data = $gsc->query([
                    'startDate' => date('Y-m-d', strtotime('-28 days')),
                    'endDate' => date('Y-m-d'),
                    'dimensions' => ['query'],
                    'rowLimit' => 10,
                ]);
                foreach ((array) ($data['rows'] ?? []) as $row) {
                    $q = $row['keys'][0] ?? '';
                    if ($q !== '') {
                        $seeds[] = $q;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal — GSC is an optional signal.
        }

        if (empty(array_filter($seeds))) {
            $seeds[] = get_bloginfo('name');
        }

        $seeds = array_values(array_unique(array_filter(array_map('trim', $seeds))));
        return array_slice($seeds, 0, 15);
    }

    /**
     * Fetch search volume/difficulty for a batch of keywords via the SaaS
     * DataForSEO proxy. Returns a map keyed by lowercase keyword.
     */
    private function fetchKeywordData(array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        $cacheKey = 'sseo_ai_apexflow_kwdata_' . md5(implode('|', $keywords));
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $settings = $this->getSettings();
        $params = ['keywords' => array_values($keywords)];
        $locationCode = $this->countryToLocationCode($settings['country']);
        if ($locationCode) {
            $params['location_code'] = $locationCode;
        }
        if (!empty($settings['language'])) {
            $params['language_code'] = $settings['language'];
        }

        $response = $this->dashboardAPI->request('ai/keyword-data', $params);
        $map = [];
        if (!is_wp_error($response) && !empty($response['data'])) {
            $items = $response['data']['tasks'][0]['result'][0]['items'] ?? [];
            foreach ((array) $items as $item) {
                $kw = mb_strtolower((string) ($item['keyword'] ?? ''));
                if ($kw === '') {
                    continue;
                }
                $map[$kw] = [
                    'search_volume' => (int) ($item['search_volume'] ?? 0),
                    'difficulty' => (float) ($item['difficulty'] ?? ($item['competition'] ?? 0) * 100),
                ];
            }
        }

        set_transient($cacheKey, $map, 6 * HOUR_IN_SECONDS);
        return $map;
    }

    /**
     * DataForSEO location codes for the supported countries (subset matching
     * ApiGateway::getLocationCode).
     */
    private function countryToLocationCode(string $country): int
    {
        $map = [
            'us' => 2840,
            'gb' => 2826,
            'uk' => 2826,
            'nl' => 2528,
            'de' => 2276,
            'fr' => 2250,
        ];
        return $map[strtolower($country)] ?? 0;
    }

    /**
     * When local business coordinates are configured, scan the Google local
     * pack for the strongest candidates to detect local intent.
     *
     * @return array<string,bool> Map of lowercase keyword => true when the SERP has a local pack.
     */
    private function detectLocalIntent(array $candidates, array $settings): array
    {
        if (!$this->localSerp) {
            return [];
        }
        $center = $this->localSerp->restGetCenter();
        $lat = $center['coordinates']['lat'] ?? '';
        $lng = $center['coordinates']['lng'] ?? '';
        if ($lat === '' || $lng === '') {
            return [];
        }

        $result = [];
        $scans = 0;
        foreach ($candidates as $kw) {
            if ($scans >= self::MAX_LOCAL_PACK_SCANS) {
                break;
            }
            $scans++;

            $response = $this->dashboardAPI->request('serp/local-pack', [
                'keyword' => $kw,
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
                'radius' => (int) ($center['radius'] ?? 10),
                'country' => $settings['country'],
                'language' => $settings['language'],
                'search_type' => 'maps',
                'target_business_name' => $center['business_name'] ?? '',
            ]);

            if (!is_wp_error($response) && !empty($response['results'])) {
                $result[mb_strtolower($kw)] = true;
            }
        }

        return $result;
    }

    /**
     * Keywords already targeted by existing posts, queued items or plan entries.
     */
    private function getCoveredKeywords(): array
    {
        global $wpdb;
        $covered = [];

        $keyphrases = $wpdb->get_col(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sseo_ai_focus_keyphrase' AND meta_value != '' LIMIT 2000"
        );
        foreach ((array) $keyphrases as $kw) {
            $covered[] = mb_strtolower(trim($kw));
        }

        foreach ((array) get_option('sseo_ai_cluster_queues', []) as $queue) {
            foreach ((array) ($queue['items'] ?? []) as $item) {
                if (in_array($item['status'] ?? '', ['pending', 'processing'], true) && !empty($item['keyword'])) {
                    $covered[] = mb_strtolower(trim($item['keyword']));
                }
            }
        }

        foreach ($this->getPlan() as $entry) {
            if (($entry['status'] ?? '') === 'planned' && !empty($entry['keyword'])) {
                $covered[] = mb_strtolower(trim($entry['keyword']));
            }
        }

        foreach ($this->getPool()['keywords'] ?? [] as $item) {
            if (in_array($item['status'] ?? '', ['used', 'rejected'], true)) {
                $covered[] = mb_strtolower(trim($item['keyword'] ?? ''));
            }
        }

        return array_values(array_filter($covered));
    }

    private function isCovered(string $keyword, array $covered): bool
    {
        $normalized = mb_strtolower(trim($keyword));
        foreach ($covered as $existing) {
            if ($existing === '' ) {
                continue;
            }
            if ($normalized === $existing) {
                return true;
            }
            similar_text($normalized, $existing, $percent);
            if ($percent >= 80) {
                return true;
            }
        }
        return false;
    }

    private function rejectKeyword(string $keyword): void
    {
        // Add to excluded topics so it never returns.
        $settings = $this->getSettings();
        $excluded = (array) $settings['excluded_topics'];
        if (!in_array($keyword, $excluded, true)) {
            $excluded[] = $keyword;
            $settings['excluded_topics'] = $excluded;
            update_option(self::SETTINGS_KEY, $settings);
        }

        // Mark in pool.
        $pool = get_option(self::POOL_KEY, []);
        foreach ((array) ($pool['keywords'] ?? []) as &$item) {
            if (mb_strtolower($item['keyword'] ?? '') === mb_strtolower($keyword)) {
                $item['status'] = 'rejected';
            }
        }
        unset($item);
        update_option(self::POOL_KEY, $pool);
        $this->log('Keyword rejected: ' . $keyword);
    }

    // ------------------------------------------------------------------
    // Step 3 + 4: calendar build & enqueue
    // ------------------------------------------------------------------

    public function getPlan(): array
    {
        $plan = get_option(self::PLAN_KEY, []);
        return is_array($plan) ? $plan : [];
    }

    /**
     * Build the content calendar: open slots in the lookahead window filled
     * with the highest-scoring unused pool keywords. Titles are generated in
     * one batched LLM call.
     *
     * @param int  $max     Cap on new entries (0 = fill the whole window).
     * @param bool $persist When false (preview/dry-run), entries are returned
     *                      but not stored, so they don't block future runs.
     */
    public function buildPlan(int $max = 0, bool $persist = true): array
    {
        $settings = $this->getSettings();
        $needed = (int) $settings['posts_per_week'] * (int) $settings['lookahead_weeks'];
        if ($max > 0) {
            $needed = min($needed, $max);
        }

        $slots = $this->openSlots($needed);
        if (empty($slots)) {
            return [];
        }

        $pool = $this->ensurePool();
        $covered = $this->getCoveredKeywords();
        $available = [];
        foreach ($pool as $item) {
            if (($item['status'] ?? 'new') !== 'new') {
                continue;
            }
            if ($this->isCovered($item['keyword'], $covered)) {
                continue;
            }
            $available[] = $item;
            if (count($available) >= count($slots)) {
                break;
            }
        }

        if (empty($available)) {
            return [];
        }

        $titles = $this->generateTitles(array_column($available, 'keyword'));

        $plan = $this->getPlan();
        $newEntries = [];
        foreach ($available as $i => $item) {
            $entry = [
                'keyword' => $item['keyword'],
                'title' => $titles[$i] ?? ucfirst($item['keyword']),
                'date' => $slots[$i],
                'score' => $item['score'] ?? 0,
                'volume' => $item['volume'] ?? 0,
                'local_intent' => (bool) ($item['local_intent'] ?? false),
                'status' => 'planned',
                'post_id' => null,
            ];
            $plan[] = $entry;
            $newEntries[] = $entry;
        }

        if ($persist) {
            // Keep the plan bounded — drop the oldest finished entries.
            $plan = array_slice($plan, -100);
            update_option(self::PLAN_KEY, $plan);
        }
        return $newEntries;
    }

    /**
     * Open publication slots: dates within the lookahead window matching the
     * configured publish days/time, capped at posts_per_week per week and
     * skipping dates that already have scheduled posts or planned entries.
     */
    private function openSlots(int $count): array
    {
        $settings = $this->getSettings();
        $days = array_map('intval', (array) $settings['publish_days']);
        sort($days);
        $perWeek = max(1, (int) $settings['posts_per_week']);
        $time = $settings['publish_time'] ?: '09:00';

        // Dates already taken by scheduled posts or existing plan entries.
        $taken = [];
        $future = get_posts([
            'post_type' => 'any',
            'post_status' => 'future',
            'posts_per_page' => 200,
            'fields' => 'ids',
        ]);
        foreach ($future as $pid) {
            $taken[date('Y-m-d', strtotime(get_post($pid)->post_date))] = true;
        }
        foreach ($this->getPlan() as $entry) {
            if (($entry['status'] ?? '') === 'planned' && !empty($entry['date'])) {
                $taken[date('Y-m-d', strtotime($entry['date']))] = true;
            }
        }

        $slots = [];
        $weeks = (int) $settings['lookahead_weeks'];
        $start = new \DateTime('tomorrow', wp_timezone());

        for ($w = 0; $w < $weeks; $w++) {
            $weekSlots = 0;
            // First pass: preferred publish days. Second pass: any other day,
            // used when posts_per_week exceeds the configured publish days.
            foreach ([true, false] as $preferredOnly) {
                for ($d = 0; $d < 7 && $weekSlots < $perWeek; $d++) {
                    $date = clone $start;
                    $date->modify("+{$w} week")->modify("+{$d} day");
                    if (in_array((int) $date->format('N'), $days, true) !== $preferredOnly) {
                        continue;
                    }
                    $key = $date->format('Y-m-d');
                    if (isset($taken[$key])) {
                        continue;
                    }
                    $slots[] = $key . ' ' . $time . ':00';
                    $taken[$key] = true;
                    $weekSlots++;
                    if (count($slots) >= $count) {
                        return $slots;
                    }
                }
            }
        }

        return $slots;
    }

    /**
     * Generate SEO titles for a batch of keywords in a single LLM call.
     *
     * @return array<int,string> Titles indexed to match $keywords.
     */
    private function generateTitles(array $keywords): array
    {
        $settings = $this->getSettings();
        $profile = $this->getProfile();
        $langName = $this->settings->contentLanguageName();
        if ($settings['language'] !== $this->settings->contentLanguage()) {
            $names = ['nl' => 'Dutch', 'en' => 'English', 'de' => 'German', 'fr' => 'French',
                'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'pl' => 'Polish'];
            $langName = $names[$settings['language']] ?? $langName;
        }

        $kwList = implode("\n", array_map(fn($k, $i) => ($i + 1) . ". {$k}", $keywords, array_keys($keywords)));
        $context = trim(($profile['niche_description'] ?? '') . ' ' . ($profile['industry'] ?? ''));

        $prompt = "Generate one compelling, SEO-optimized blog post title in {$langName} for each keyword below.
Website niche: {$context}
Return a JSON array of strings in the same order as the keywords (no markdown, only JSON).

Keywords:
{$kwList}";

        $titles = [];
        $response = $this->llm->generateText($prompt, ['use_case' => 'analysis', 'max_tokens' => 800]);
        if (!is_wp_error($response)) {
            $raw = trim($response);
            if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
                $raw = trim($m[1]);
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $titles = array_values(array_map('sanitize_text_field', $decoded));
            }
        }

        return $titles;
    }

    /**
     * Push planned entries into the shared cluster queue for background
     * generation by TopicCluster::processQueueItems().
     *
     * @return int Number of items queued.
     */
    private function enqueuePlan(array $entries): int
    {
        if (empty($entries)) {
            return 0;
        }

        $settings = $this->getSettings();
        $queues = get_option('sseo_ai_cluster_queues', []);
        if (!is_array($queues)) {
            $queues = [];
        }
        $queueId = count($queues) + 1;

        $queueItems = [];
        foreach ($entries as $entry) {
            $queueItems[] = [
                'title' => $entry['title'],
                'keyword' => $entry['keyword'],
                'word_count' => (int) $settings['word_count'],
                'content_type' => 'article',
                'cluster_role' => 'apexflow',
                'schedule_date' => $entry['date'],
                'status' => 'pending',
                'post_id' => null,
                'error' => null,
                'attempts' => 0,
                'post_type' => $settings['post_type'],
                'publish_mode' => $settings['publish_mode'],
                'category_id' => (int) $settings['category_id'],
                'author_id' => (int) $settings['author_id'],
                'featured_image' => (bool) $settings['featured_image'],
                'source' => 'apexflow',
            ];
        }

        $queues[] = [
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
        update_option('sseo_ai_cluster_queues', $queues);

        // Mark plan + pool entries as queued.
        $plan = $this->getPlan();
        foreach ($plan as &$entry) {
            foreach ($entries as $new) {
                if (($entry['keyword'] ?? '') === $new['keyword'] && ($entry['status'] ?? '') === 'planned') {
                    $entry['status'] = 'queued';
                }
            }
        }
        unset($entry);
        update_option(self::PLAN_KEY, $plan);

        $pool = get_option(self::POOL_KEY, []);
        foreach ((array) ($pool['keywords'] ?? []) as &$item) {
            foreach ($entries as $new) {
                if (mb_strtolower($item['keyword'] ?? '') === mb_strtolower($new['keyword'])) {
                    $item['status'] = 'planned';
                }
            }
        }
        unset($item);
        update_option(self::POOL_KEY, $pool);

        if (!wp_next_scheduled('sseo_ai_process_cluster_queue')) {
            wp_schedule_event(time() + 60, 'sseo_ai_queue_interval', 'sseo_ai_process_cluster_queue');
        }

        return count($queueItems);
    }

    // ------------------------------------------------------------------
    // Housekeeping
    // ------------------------------------------------------------------

    private function getMonthlyCount(): array
    {
        $saved = get_option(self::MONTHLY_KEY, []);
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

    private function sendSummaryEmail(array $entries): void
    {
        $email = get_option('admin_email');
        if (empty($email) || empty($entries)) {
            return;
        }

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $company = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';

        $lines = [];
        foreach ($entries as $e) {
            $lines[] = sprintf(
                '- %s — %s (%s)',
                date_i18n(get_option('date_format'), strtotime($e['date'])),
                $e['title'],
                $e['keyword']
            );
        }

        wp_mail(
            $email,
            sprintf(__('%s ApexFlow: %d posts scheduled', 'ai-seo-client'), $company, count($entries)),
            sprintf(
                __("ApexFlow scheduled %d new posts:\n\n%s\n\nManage: %s", 'ai-seo-client'),
                count($entries),
                implode("\n", $lines),
                admin_url('admin.php?page=ai-seo-automation')
            )
        );
    }

    // ------------------------------------------------------------------
    // Admin page
    // ------------------------------------------------------------------

    public function renderPage(): void
    {
        if (!$this->isAllowed()) {
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            esc_html_e('ApexFlow requires a Professional or higher license.', 'ai-seo-client');
            echo '</p></div></div>';
            return;
        }

        $settings = $this->getSettings();
        $profile = $this->getProfile();
        $pool = get_option(self::POOL_KEY, []);
        $poolItems = (array) ($pool['keywords'] ?? []);
        $plan = $this->getPlan();
        $monthly = $this->getMonthlyCount();
        $limit = $this->licenseValidator->getMonthlyAutoPostLimit();
        $weeklyCap = $limit === PHP_INT_MAX ? 7 : max(1, (int) floor($limit / 4.33));
        $logs = get_option(self::LOG_KEY, []);
        if (!is_array($logs)) {
            $logs = [];
        }
        $bv = get_option('sseo_ai_brand_voice', []);
        if (!is_array($bv)) {
            $bv = [];
        }
        $preview = get_transient('sseo_ai_apexflow_preview_' . get_current_user_id());
        $options = $this->settings->all();
        $hasLocal = !empty($options['local_latitude']) && !empty($options['local_longitude']);

        $queuedCount = 0;
        $queuePosts = [];
        foreach ((array) get_option('sseo_ai_cluster_queues', []) as $queue) {
            foreach ((array) ($queue['items'] ?? []) as $item) {
                if (($item['source'] ?? '') !== 'apexflow') {
                    continue;
                }
                if (in_array($item['status'] ?? '', ['pending', 'processing'], true)) {
                    $queuedCount++;
                }
                if (!empty($item['post_id'])) {
                    $queuePosts[mb_strtolower($item['keyword'] ?? '')] = [
                        'post_id' => (int) $item['post_id'],
                        'status' => $item['status'] ?? '',
                    ];
                }
            }
        }

        // Enrich plan entries with generated post links.
        foreach ($plan as &$entry) {
            $hit = $queuePosts[mb_strtolower($entry['keyword'] ?? '')] ?? null;
            if ($hit) {
                $entry['post_id'] = $hit['post_id'];
                $entry['status'] = 'done';
            }
        }
        unset($entry);
        ?>
        <style>
            .sseo-apexflow-page .sseo-ai-dashboard-card { border: 1px solid #e1e6ee; border-radius: 10px; box-shadow: 0 1px 3px rgba(15, 23, 42, .05); }
            .sseo-apexflow-page .form-table { border-collapse: separate; border-spacing: 0 12px; }
            .sseo-apexflow-page .form-table th { padding: 10px 20px 10px 0; color: #344054; font-size: 14px; font-weight: 600; vertical-align: top; }
            .sseo-apexflow-page .form-table td { padding: 4px 0; }
            .sseo-apexflow-page input[type="text"],
            .sseo-apexflow-page input[type="number"],
            .sseo-apexflow-page input[type="time"],
            .sseo-apexflow-page select,
            .sseo-apexflow-page textarea {
                min-height: 42px;
                padding: 9px 13px;
                border: 1px solid #d7dde7;
                border-radius: 6px;
                background: #fff;
                color: #344054;
                box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
                transition: border-color .15s, box-shadow .15s;
            }
            .sseo-apexflow-page textarea { min-height: 86px; }
            .sseo-apexflow-page input:focus,
            .sseo-apexflow-page select:focus,
            .sseo-apexflow-page textarea:focus {
                border-color: #379fd3;
                box-shadow: 0 0 0 3px rgba(55, 159, 211, .14);
                outline: 0;
            }
            .sseo-apexflow-page .button {
                min-height: 38px;
                padding: 4px 16px;
                border-color: #d7dde7;
                border-radius: 6px;
                color: #344054;
                font-weight: 600;
                box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
            }
            .sseo-apexflow-page .button-primary {
                border-color: #379fd3;
                background: #379fd3;
                color: #fff;
            }
            .sseo-apexflow-page .button-primary:hover,
            .sseo-apexflow-page .button-primary:focus { border-color: #278dc0; background: #278dc0; color: #fff; }
            .sseo-apexflow-toggle { display: inline-flex; align-items: center; gap: 12px; cursor: pointer; }
            .sseo-apexflow-toggle input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
            .sseo-apexflow-toggle-track {
                position: relative;
                width: 46px;
                height: 24px;
                flex: 0 0 46px;
                border-radius: 999px;
                background: #cbd5e1;
                transition: background .2s, box-shadow .2s;
            }
            .sseo-apexflow-toggle-track::after {
                content: "";
                position: absolute;
                top: 3px;
                left: 3px;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #fff;
                box-shadow: 0 1px 3px rgba(15, 23, 42, .3);
                transition: transform .2s;
            }
            .sseo-apexflow-toggle input:checked + .sseo-apexflow-toggle-track { background: #379fd3; }
            .sseo-apexflow-toggle input:checked + .sseo-apexflow-toggle-track::after { transform: translateX(22px); }
            .sseo-apexflow-toggle input:focus-visible + .sseo-apexflow-toggle-track { box-shadow: 0 0 0 3px rgba(55, 159, 211, .25); }
            .sseo-apexflow-toggle-text { color: #475467; font-weight: 500; }
            @media (max-width: 782px) {
                .sseo-apexflow-page .form-table th { padding-bottom: 2px; }
                .sseo-apexflow-page .regular-text,
                .sseo-apexflow-page .large-text { width: 100%; }
            }
        </style>
        <div class="wrap sseo-ai-modern sseo-apexflow-page">
            <div class="sseo-ai-header">
                <h1>&#9889; <?php esc_html_e('ApexFlow — Content Autopilot', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Scans your site, researches keywords in your region and automatically plans and writes posts — every week, hands-free.', 'ai-seo-client'); ?></p>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card" style="max-width: 1000px;">
                    <?php if (isset($_GET['saved'])): ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'ai-seo-client'); ?></p></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['ran'])): ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('ApexFlow run started. Check the log below.', 'ai-seo-client'); ?></p></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['scanned'])): ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Site profile re-scanned.', 'ai-seo-client'); ?></p></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['rejected'])): ?>
                        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Keyword rejected and added to excluded topics.', 'ai-seo-client'); ?></p></div>
                    <?php endif; ?>

                    <h2><?php esc_html_e('Status', 'ai-seo-client'); ?></h2>
                    <p>
                        <strong><?php esc_html_e('ApexFlow:', 'ai-seo-client'); ?></strong>
                        <?php echo $settings['enabled'] ? '&#9889; ' . esc_html__('Active', 'ai-seo-client') : esc_html__('Off', 'ai-seo-client'); ?><br>
                        <strong><?php esc_html_e('Posts this month:', 'ai-seo-client'); ?></strong> <?php echo esc_html($monthly['count']); ?> / <?php echo esc_html($limit === PHP_INT_MAX ? 'unlimited' : $limit); ?><br>
                        <strong><?php esc_html_e('In generation queue:', 'ai-seo-client'); ?></strong> <?php echo esc_html($queuedCount); ?><br>
                        <strong><?php esc_html_e('Keyword pool:', 'ai-seo-client'); ?></strong> <?php echo esc_html(count($poolItems)); ?> <?php esc_html_e('candidates', 'ai-seo-client'); ?><br>
                        <strong><?php esc_html_e('Last run:', 'ai-seo-client'); ?></strong> <?php echo esc_html(get_option(self::LAST_RUN_KEY, 'never')); ?><br>
                        <strong><?php esc_html_e('Next scheduled run:', 'ai-seo-client'); ?></strong> <?php echo esc_html(wp_next_scheduled(self::CRON_HOOK) ? date('Y-m-d H:i:s', wp_next_scheduled(self::CRON_HOOK)) : 'not scheduled'); ?>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Note: already scheduled posts will still publish when ApexFlow is turned off.', 'ai-seo-client'); ?>
                    </p>

                    <hr style="margin: 30px 0;">

                    <h2><?php esc_html_e('Detected site profile', 'ai-seo-client'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('ApexFlow scans your site to determine the niche. Adjust the detected values if needed.', 'ai-seo-client'); ?>
                    </p>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sseo_ai_apexflow_save'); ?>
                        <input type="hidden" name="action" value="sseo_ai_apexflow_save">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="enabled"><?php esc_html_e('Enable ApexFlow', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <label class="sseo-apexflow-toggle" for="enabled">
                                        <input type="checkbox" name="enabled" id="enabled" value="1" <?php checked($settings['enabled']); ?>>
                                        <span class="sseo-apexflow-toggle-track" aria-hidden="true"></span>
                                        <span class="sseo-apexflow-toggle-text"><?php esc_html_e('Automatically research, plan and write content every week', 'ai-seo-client'); ?></span>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="profile_industry"><?php esc_html_e('Industry / niche', 'ai-seo-client'); ?></label></th>
                                <td><input type="text" name="profile_industry" id="profile_industry" class="regular-text" value="<?php echo esc_attr($profile['industry'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="profile_audience"><?php esc_html_e('Target audience', 'ai-seo-client'); ?></label></th>
                                <td><input type="text" name="profile_audience" id="profile_audience" class="regular-text" value="<?php echo esc_attr($profile['audience'] ?? ''); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="profile_topics"><?php esc_html_e('Core topics (comma-separated)', 'ai-seo-client'); ?></label></th>
                                <td><input type="text" name="profile_topics" id="profile_topics" class="large-text" value="<?php echo esc_attr(implode(', ', (array) ($profile['topics'] ?? []))); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="seed_keywords"><?php esc_html_e('Own keywords (optional)', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <textarea name="seed_keywords" id="seed_keywords" rows="4" class="large-text" placeholder="one keyword per line"><?php echo esc_textarea(implode("\n", (array) $settings['seed_keywords'])); ?></textarea>
                                    <p class="description"><?php esc_html_e('Prioritized above automatically discovered keywords.', 'ai-seo-client'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="excluded_topics"><?php esc_html_e('Excluded topics/keywords', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <textarea name="excluded_topics" id="excluded_topics" rows="3" class="large-text" placeholder="one per line"><?php echo esc_textarea(implode("\n", (array) $settings['excluded_topics'])); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Language & region', 'ai-seo-client'); ?></th>
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
                                    <select name="country" id="country">
                                        <?php foreach (['nl' => 'Nederland', 'be' => 'België', 'de' => 'Duitsland', 'fr' => 'Frankrijk', 'gb' => 'United Kingdom', 'us' => 'United States', 'es' => 'Spanje', 'it' => 'Italië', 'ca' => 'Canada', 'au' => 'Australië'] as $code => $label): ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['country'], $code); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($hasLocal): ?>
                                        <p class="description">&#9989; <?php esc_html_e('Local business configured — local pack scans are included in keyword research.', 'ai-seo-client'); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="posts_per_week"><?php esc_html_e('Posts per week', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="number" name="posts_per_week" id="posts_per_week" value="<?php echo esc_attr($settings['posts_per_week']); ?>" min="1" max="7" step="1">
                                    <p class="description">
                                        <?php printf(esc_html__('Your license allows up to %s auto-posts per month (max %s/week).', 'ai-seo-client'), esc_html($limit === PHP_INT_MAX ? 'unlimited' : $limit), esc_html(min(7, $weeklyCap))); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Publish on', 'ai-seo-client'); ?></th>
                                <td>
                                    <?php
                                    $dayNames = [1 => __('Mon', 'ai-seo-client'), 2 => __('Tue', 'ai-seo-client'), 3 => __('Wed', 'ai-seo-client'), 4 => __('Thu', 'ai-seo-client'), 5 => __('Fri', 'ai-seo-client'), 6 => __('Sat', 'ai-seo-client'), 7 => __('Sun', 'ai-seo-client')];
                                    foreach ($dayNames as $num => $name):
                                    ?>
                                        <label style="margin-right:12px;"><input type="checkbox" name="publish_days[]" value="<?php echo esc_attr($num); ?>" <?php checked(in_array($num, (array) $settings['publish_days'], true)); ?>> <?php echo esc_html($name); ?></label>
                                    <?php endforeach; ?>
                                    <input type="time" name="publish_time" value="<?php echo esc_attr($settings['publish_time']); ?>" style="margin-left:15px;">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Publish mode', 'ai-seo-client'); ?></th>
                                <td>
                                    <label style="margin-right:15px;"><input type="radio" name="publish_mode" value="schedule" <?php checked($settings['publish_mode'], 'schedule'); ?>> <?php esc_html_e('Auto-publish on the planned date', 'ai-seo-client'); ?></label>
                                    <label><input type="radio" name="publish_mode" value="draft" <?php checked($settings['publish_mode'], 'draft'); ?>> <?php esc_html_e('Create drafts for review first', 'ai-seo-client'); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lookahead_weeks"><?php esc_html_e('Plan ahead (weeks)', 'ai-seo-client'); ?></label></th>
                                <td><input type="number" name="lookahead_weeks" id="lookahead_weeks" value="<?php echo esc_attr($settings['lookahead_weeks']); ?>" min="1" max="8" step="1"></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Post settings', 'ai-seo-client'); ?></th>
                                <td>
                                    <?php
                                    $postTypes = get_post_types(['public' => true], 'objects');
                                    unset($postTypes['attachment']);
                                    ?>
                                    <select name="post_type">
                                        <?php foreach ($postTypes as $pt): ?>
                                            <option value="<?php echo esc_attr($pt->name); ?>" <?php selected($settings['post_type'], $pt->name); ?>><?php echo esc_html($pt->labels->singular_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    wp_dropdown_categories([
                                        'name' => 'category_id',
                                        'show_option_none' => __('No category', 'ai-seo-client'),
                                        'option_none_value' => '0',
                                        'hide_empty' => false,
                                        'selected' => (int) $settings['category_id'],
                                    ]);
                                    wp_dropdown_users([
                                        'name' => 'author_id',
                                        'show_option_none' => __('Default author', 'ai-seo-client'),
                                        'option_none_value' => '0',
                                        'selected' => (int) $settings['author_id'],
                                    ]);
                                    ?>
                                    <input type="number" name="word_count" value="<?php echo esc_attr($settings['word_count']); ?>" min="300" max="5000" step="100" style="width:90px;" title="<?php esc_attr_e('Word count', 'ai-seo-client'); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Options', 'ai-seo-client'); ?></th>
                                <td>
                                    <label style="display:block;margin-bottom:6px;"><input type="checkbox" name="featured_image" value="1" <?php checked($settings['featured_image']); ?>> <?php esc_html_e('Generate AI featured images', 'ai-seo-client'); ?></label>
                                    <label style="display:block;"><input type="checkbox" name="notify_email" value="1" <?php checked($settings['notify_email']); ?>> <?php esc_html_e('Email me a weekly summary of scheduled posts', 'ai-seo-client'); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Tone of voice', 'ai-seo-client'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="bv_enabled" value="1" <?php checked(!empty($bv['enabled'])); ?>> <?php esc_html_e('Apply brand voice to generated content', 'ai-seo-client'); ?></label><br>
                                    <select name="bv_tone" style="margin-top:8px;">
                                        <?php foreach (['professional' => 'Professional', 'casual' => 'Casual', 'authoritative' => 'Authoritative', 'friendly' => 'Friendly', 'technical' => 'Technical', 'conversational' => 'Conversational'] as $tone => $label): ?>
                                            <option value="<?php echo esc_attr($tone); ?>" <?php selected($bv['tone'] ?? 'professional', $tone); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="bv_audience" class="regular-text" style="margin-left:8px;" placeholder="<?php esc_attr_e('Audience (optional)', 'ai-seo-client'); ?>" value="<?php echo esc_attr($bv['audience'] ?? ''); ?>"><br>
                                    <textarea name="bv_voice_description" rows="2" class="large-text" style="margin-top:8px;" placeholder="<?php esc_attr_e('Voice guidelines (optional)', 'ai-seo-client'); ?>"><?php echo esc_textarea($bv['voice_description'] ?? ''); ?></textarea>
                                    <p class="description"><a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-brand-voice')); ?>"><?php esc_html_e('Full Brand Voice settings', 'ai-seo-client'); ?> &rarr;</a></p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(__('Save Settings', 'ai-seo-client')); ?>
                    </form>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:15px;">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sseo_ai_apexflow_rescan'); ?>
                            <input type="hidden" name="action" value="sseo_ai_apexflow_rescan">
                            <?php submit_button(__('Rescan Site Profile', 'ai-seo-client'), 'secondary', 'submit', false); ?>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sseo_ai_apexflow_preview'); ?>
                            <input type="hidden" name="action" value="sseo_ai_apexflow_preview">
                            <?php submit_button(__('Preview Calendar', 'ai-seo-client'), 'secondary', 'submit', false); ?>
                        </form>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sseo_ai_apexflow_run_now'); ?>
                            <input type="hidden" name="action" value="sseo_ai_apexflow_run_now">
                            <?php submit_button(__('Run ApexFlow Now', 'ai-seo-client'), 'secondary', 'submit', false); ?>
                        </form>
                    </div>

                    <?php if (is_array($preview) && !empty($preview)): ?>
                        <hr style="margin: 30px 0;">
                        <h2><?php esc_html_e('Calendar preview (not scheduled yet)', 'ai-seo-client'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr>
                                <th><?php esc_html_e('Date', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Title', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Volume', 'ai-seo-client'); ?></th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($preview as $entry): ?>
                                    <tr>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($entry['date']))); ?></td>
                                        <td><?php echo esc_html($entry['title']); ?></td>
                                        <td><?php echo esc_html($entry['keyword']); ?><?php echo !empty($entry['local_intent']) ? ' &#128205;' : ''; ?></td>
                                        <td><?php echo esc_html($entry['volume'] ?: '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php
                    $upcoming = array_filter($plan, fn($e) => in_array($e['status'] ?? '', ['planned', 'queued', 'done'], true));
                    if (!empty($upcoming)):
                    ?>
                        <hr style="margin: 30px 0;">
                        <h2><?php esc_html_e('Planned posts', 'ai-seo-client'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr>
                                <th><?php esc_html_e('Date', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Title', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            </tr></thead>
                            <tbody>
                                <?php foreach (array_slice($upcoming, 0, 30) as $entry): ?>
                                    <tr>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($entry['date']))); ?></td>
                                        <td>
                                            <?php if (!empty($entry['post_id'])): ?>
                                                <a href="<?php echo esc_url(get_edit_post_link($entry['post_id'], '')); ?>"><?php echo esc_html($entry['title']); ?></a>
                                            <?php else: ?>
                                                <?php echo esc_html($entry['title']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($entry['keyword']); ?></td>
                                        <td><?php echo esc_html($entry['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($poolItems)): ?>
                        <hr style="margin: 30px 0;">
                        <h2><?php esc_html_e('Keyword pool', 'ai-seo-client'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead><tr>
                                <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Volume', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Difficulty', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Score', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                                <?php foreach (array_slice($poolItems, 0, 25) as $item): ?>
                                    <tr>
                                        <td><?php echo esc_html($item['keyword']); ?><?php echo !empty($item['local_intent']) ? ' &#128205;' : ''; ?></td>
                                        <td><?php echo esc_html($item['volume'] ?: '—'); ?></td>
                                        <td><?php echo esc_html(round($item['difficulty'] ?? 0)); ?></td>
                                        <td><?php echo esc_html($item['score'] ?? 0); ?></td>
                                        <td><?php echo esc_html($item['status'] ?? 'new'); ?></td>
                                        <td>
                                            <?php if (($item['status'] ?? 'new') === 'new'): ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                    <?php wp_nonce_field('sseo_ai_apexflow_reject'); ?>
                                                    <input type="hidden" name="action" value="sseo_ai_apexflow_reject">
                                                    <input type="hidden" name="keyword" value="<?php echo esc_attr($item['keyword']); ?>">
                                                    <button type="submit" class="button button-small"><?php esc_html_e('Reject', 'ai-seo-client'); ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <hr style="margin: 30px 0;">
                    <h2><?php esc_html_e('Activity log', 'ai-seo-client'); ?></h2>
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

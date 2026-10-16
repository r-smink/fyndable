<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Fyndable_Demo_Runtime
{
    private const STATE_META = '_fyndable_demo_state';
    private const LIVE_LIMIT = 2;
    private bool $liveRequestAllowed = false;

    public function register(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        add_filter('sseo_ai_license_valid', [$this, 'filterLicenseValid'], 999);
        add_filter('sseo_ai_license_tier', [$this, 'filterLicenseTier'], 999);
        add_filter('sseo_ai_skip_license_validation', [$this, 'skipLicenseValidation'], 999);
        add_filter('rest_pre_dispatch', [$this, 'interceptRestRequest'], 10, 3);
        add_filter('pre_http_request', [$this, 'blockPortalRequests'], 10, 3);

        // Option fixtures so server-rendered pages show demo data too.
        add_filter('pre_option_sseo_ai_tracked_competitors', [$this, 'fixtureCompetitors']);
        add_filter('pre_option_sseo_ai_apexflow_profile', [$this, 'fixtureApexflowProfile']);
        add_filter('pre_option_sseo_ai_apexflow_keyword_pool', [$this, 'fixtureApexflowPool']);
        add_filter('pre_option_sseo_ai_apexflow_plan', [$this, 'fixtureApexflowPlan']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 100);
        add_action('admin_menu', [$this, 'registerSettingsPage'], 50);
        add_action('admin_bar_menu', [$this, 'addAdminBarMenu'], 999);
        add_action('admin_notices', [$this, 'showNotice']);
        add_action('wp_ajax_fyndable_demo_reset', [$this, 'resetState']);
        add_action('wp_ajax_fyndable_demo_save_settings', [$this, 'saveSettings']);
    }

    private function isEnabled(): bool
    {
        return get_option('fyndable_demo_enabled', '1') === '1';
    }

    private function canUseDemo(): bool
    {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    public function filterLicenseValid(bool $valid): bool
    {
        return $this->canUseDemo() ? true : $valid;
    }

    public function filterLicenseTier(string $tier): string
    {
        return $this->canUseDemo() ? 'dev' : $tier;
    }

    public function skipLicenseValidation(bool $skip): bool
    {
        return $this->canUseDemo() ? true : $skip;
    }

    /**
     * Option fixtures: only return demo data when the real option is empty,
     * so sites that already configured these features keep their own values.
     * Returning an array short-circuits get_option(); returning false falls
     * through to the stored option.
     */
    public function fixtureCompetitors()
    {
        return $this->canUseDemo() ? Fyndable_Demo_Fixtures::competitors() : false;
    }

    public function fixtureApexflowProfile()
    {
        if (!$this->canUseDemo()) {
            return false;
        }
        $fixture = Fyndable_Demo_Fixtures::apexflow();
        return [
            'business' => $fixture['profile']['business'],
            'industry' => $fixture['profile']['industry'],
            'audience' => $fixture['profile']['audience'],
            'proposition' => $fixture['profile']['proposition'],
            'topics' => ['airco', 'warmtepomp', 'ventilatie', 'klimaatbeheersing'],
            'scanned_at' => gmdate('Y-m-d H:i:s', strtotime('-5 days')),
        ];
    }

    public function fixtureApexflowPool()
    {
        return $this->canUseDemo() ? Fyndable_Demo_Fixtures::apexflowPool() : false;
    }

    public function fixtureApexflowPlan()
    {
        return $this->canUseDemo() ? Fyndable_Demo_Fixtures::apexflowPlanOption() : false;
    }

    public function blockPortalRequests($preempt, array $args, string $url)
    {
        if (!$this->canUseDemo() || $this->liveRequestAllowed) {
            return $preempt;
        }

        $dashboardUrl = (string) get_option('sseo_ai_client_dashboard_url', SSEO_AI_DEFAULT_DASHBOARD_URL);
        $portalHost = wp_parse_url($dashboardUrl ?: SSEO_AI_DEFAULT_DASHBOARD_URL, PHP_URL_HOST);
        $requestHost = wp_parse_url($url, PHP_URL_HOST);
        if ($portalHost && $requestHost && strtolower($portalHost) === strtolower($requestHost)) {
            return new \WP_Error('fyndable_demo_blocked', __('External portal calls are disabled in the demo.', 'fyndable-demo'));
        }

        return $preempt;
    }

    public function showNotice(): void
    {
        if (!$this->canUseDemo() || isset($_GET['fyndable_shell'])) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'fyndable') === false && strpos($screen->id, 'ai-seo') === false)) {
            return;
        }

        echo '<div class="notice notice-warning fyndable-demo-notice"><p><strong>'
            . esc_html__('Fyndable salesdemo actief', 'fyndable-demo')
            . '</strong> — ' . esc_html__('Gegevens en schrijfacties zijn gesimuleerd, tenzij een actie uitdrukkelijk als live is gemarkeerd.', 'fyndable-demo')
            . '</p></div>';
    }

    public function addAdminBarMenu(\WP_Admin_Bar $bar): void
    {
        if (!$this->canUseDemo()) {
            return;
        }

        $startUrl = add_query_arg([
            'page' => 'fyndable-dashboard',
            'fyndable_page' => 'ai-seo-dashboard',
            'fyndable_demo_tour' => '1',
        ], admin_url('admin.php'));

        $bar->add_node([
            'id' => 'fyndable-demo',
            'title' => esc_html__('Demo', 'fyndable-demo'),
            'href' => $startUrl,
            'meta' => ['class' => 'fyndable-demo-admin-bar'],
        ]);
        $bar->add_node([
            'parent' => 'fyndable-demo',
            'id' => 'fyndable-demo-start',
            'title' => esc_html__('Start begeleide tour', 'fyndable-demo'),
            'href' => $startUrl,
        ]);
        $bar->add_node([
            'parent' => 'fyndable-demo',
            'id' => 'fyndable-demo-autoplay',
            'title' => esc_html__('Start autoplay', 'fyndable-demo'),
            'href' => add_query_arg('fyndable_demo_autoplay', '1', $startUrl),
        ]);
        $bar->add_node([
            'parent' => 'fyndable-demo',
            'id' => 'fyndable-demo-settings',
            'title' => esc_html__('Demo-instellingen', 'fyndable-demo'),
            'href' => admin_url('admin.php?page=fyndable-demo-settings'),
        ]);
    }

    public function registerSettingsPage(): void
    {
        add_submenu_page(
            'fyndable-dashboard',
            __('Demo-instellingen', 'fyndable-demo'),
            __('Demo-instellingen', 'fyndable-demo'),
            'manage_options',
            'fyndable-demo-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderSettingsPage(): void
    {
        $liveEnabled = get_option('fyndable_demo_live_enabled', '0') === '1';
        $state = $this->getState();
        ?>
        <div class="wrap sseo-ai-modern">
            <h1><?php esc_html_e('Fyndable salesdemo', 'fyndable-demo'); ?></h1>
            <div class="card" style="max-width:760px;padding:24px;">
                <h2><?php esc_html_e('Preflight', 'fyndable-demo'); ?></h2>
                <p><strong><?php esc_html_e('Clientplugin:', 'fyndable-demo'); ?></strong> <?php echo class_exists('SSEOAIClient\\Client') ? esc_html__('gereed', 'fyndable-demo') : esc_html__('ontbreekt', 'fyndable-demo'); ?></p>
                <p><strong><?php esc_html_e('Scenario:', 'fyndable-demo'); ?></strong> Van Dijk Klimaattechniek (NL)</p>
                <p><strong><?php esc_html_e('Live calls deze sessie:', 'fyndable-demo'); ?></strong> <?php echo esc_html((string) $state['live_calls']); ?> / <?php echo esc_html((string) self::LIVE_LIMIT); ?></p>
                <label style="display:block;margin:20px 0;">
                    <input type="checkbox" id="fyndable-demo-live" <?php checked($liveEnabled); ?>>
                    <?php esc_html_e('Sta bevestigde live generatie van contentbriefs toe', 'fyndable-demo'); ?>
                </label>
                <p class="description"><?php esc_html_e('Alle overige externe API-calls en niet-gesimuleerde schrijfacties blijven geblokkeerd.', 'fyndable-demo'); ?></p>
                <p>
                    <button type="button" class="button button-primary" id="fyndable-demo-save-settings"><?php esc_html_e('Instellingen opslaan', 'fyndable-demo'); ?></button>
                    <a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'fyndable-dashboard', 'fyndable_demo_tour' => '1'], admin_url('admin.php'))); ?>"><?php esc_html_e('Start tour', 'fyndable-demo'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    public function enqueueAssets(string $hook): void
    {
        if (!$this->canUseDemo() || (strpos($hook, 'fyndable') === false && strpos($hook, 'ai-seo') === false)) {
            return;
        }

        wp_enqueue_style(
            'fyndable-demo',
            FYNDABLE_DEMO_URL . 'assets/demo.css',
            [],
            FYNDABLE_DEMO_VERSION . '.' . filemtime(FYNDABLE_DEMO_DIR . 'assets/demo.css')
        );
        wp_enqueue_script(
            'fyndable-demo',
            FYNDABLE_DEMO_URL . 'assets/demo.js',
            [],
            FYNDABLE_DEMO_VERSION . '.' . filemtime(FYNDABLE_DEMO_DIR . 'assets/demo.js'),
            true
        );
        wp_localize_script('fyndable-demo', 'fyndableDemo', [
            'steps' => Fyndable_Demo_Fixtures::tour(),
            'nonce' => wp_create_nonce('fyndable_demo'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'audioBaseUrl' => FYNDABLE_DEMO_URL,
            'autoStart' => isset($_GET['fyndable_demo_tour']),
            'autoplay' => isset($_GET['fyndable_demo_autoplay']),
            'liveEnabled' => get_option('fyndable_demo_live_enabled', '0') === '1',
            'liveLimit' => self::LIVE_LIMIT,
            'labels' => [
                'demo' => __('Demodata', 'fyndable-demo'),
                'previous' => __('Vorige', 'fyndable-demo'),
                'next' => __('Volgende', 'fyndable-demo'),
                'finish' => __('Afronden', 'fyndable-demo'),
                'pause' => __('Pauzeren', 'fyndable-demo'),
                'play' => __('Afspelen', 'fyndable-demo'),
                'reset' => __('Demo resetten', 'fyndable-demo'),
                'whatTitle' => __('Wat doet het?', 'fyndable-demo'),
                'howTitle' => __('Hoe gebruik je het?', 'fyndable-demo'),
                'tipTitle' => __('Tip', 'fyndable-demo'),
                'mute' => __('Geluid uit', 'fyndable-demo'),
                'unmute' => __('Geluid aan', 'fyndable-demo'),
                'stepOf' => __('Stap', 'fyndable-demo'),
                'of' => __('van', 'fyndable-demo'),
                'jumpTo' => __('Ga naar onderdeel', 'fyndable-demo'),
                'voiceoverTitle' => __('Toelichting', 'fyndable-demo'),
                'switchToVoice' => __('Schakel naar voice-over', 'fyndable-demo'),
                'switchToText' => __('Schakel naar tekst', 'fyndable-demo'),
                'chooseModeTitle' => __('Hoe wil je de tour volgen?', 'fyndable-demo'),
                'chooseModeDesc' => __('Kies of je de toelichting wilt horen (voice-over) of lezen (tekst in de popup).', 'fyndable-demo'),
                'modeVoice' => __('Met voice-over', 'fyndable-demo'),
                'modeVoiceHint' => __('Audio-toelichting bij elke stap', 'fyndable-demo'),
                'modeText' => __('Met tekst', 'fyndable-demo'),
                'modeTextHint' => __('Toelichting als tekst in de popup', 'fyndable-demo'),
            ],
        ]);
    }

    public function interceptRestRequest($result, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        if ($result !== null || !$this->canUseDemo()) {
            return $result;
        }

        $route = '/' . ltrim($request->get_route(), '/');
        $method = strtoupper($request->get_method());

        if ($route === '/sseo-ai/v1/dashboard/overview' && $method === 'GET') {
            return $this->response(Fyndable_Demo_Fixtures::dashboard());
        }

        if ($route === '/sseo-ai/v1/keywords' && $method === 'GET') {
            $keywords = $this->getKeywords();
            return $this->response([
                'keywords' => $keywords,
                'total' => count($keywords),
                'limit' => 50,
                'offset' => 0,
                'filter_counts' => ['all' => count($keywords), 'tracked' => count($keywords), 'ranked' => count($keywords), 'position_changes' => count($keywords), 'new_ranked' => 0],
                'is_demo' => true,
            ]);
        }

        if ($route === '/sseo-ai/v1/keywords/add' && $method === 'POST') {
            $keyword = sanitize_text_field((string) $request->get_param('keyword'));
            if ($keyword === '') {
                return new \WP_Error('empty_keyword', __('Keyword is required', 'fyndable-demo'), ['status' => 400]);
            }
            $state = $this->getState();
            $id = 1000 + count($state['keywords']);
            $state['keywords'][] = ['id' => $id, 'keyword' => $keyword, 'search_volume' => 320, 'difficulty' => 'medium', 'search_intent' => 'commercial', 'rank' => 0, 'position_change' => 0, 'status' => 'active', 'language' => 'nl', 'cpc' => '1.80', 'cluster_id' => 1, 'cluster_name' => 'Demo', 'is_new' => 1, 'created_at' => current_time('mysql')];
            $this->saveState($state);
            return $this->response(['success' => true, 'id' => $id, 'is_demo' => true]);
        }

        if (preg_match('#^/sseo-ai/v1/keywords/(\d+)$#', $route, $matches) && in_array($method, ['DELETE', 'PUT'], true)) {
            $state = $this->getState();
            $id = (int) $matches[1];
            if ($method === 'DELETE') {
                $state['deleted_keywords'][] = $id;
            }
            $this->saveState($state);
            return $this->response(['success' => true, 'is_demo' => true]);
        }

        if ($route === '/sseo-ai/v1/clusters/list' && $method === 'GET') {
            return $this->response([Fyndable_Demo_Fixtures::topicCluster()]);
        }

        if ($route === '/sseo-ai/v1/clusters/1' && $method === 'GET') {
            return $this->response(Fyndable_Demo_Fixtures::topicCluster());
        }

        if ($route === '/sseo-ai/v1/clusters/generate-async' && $method === 'POST') {
            return $this->response(['queue_id' => 9001, 'status' => 'pending', 'is_demo' => true]);
        }

        if ($route === '/sseo-ai/v1/clusters/map-queue/9001' && $method === 'GET') {
            return $this->response(['queue_id' => 9001, 'status' => 'completed', 'result' => Fyndable_Demo_Fixtures::topicCluster(), 'is_demo' => true]);
        }

        if (preg_match('#^/sseo-ai/v1/clusters/(?:save|audit|queue|generate-content)$#', $route) && $method === 'POST') {
            return $this->response(['success' => true, 'simulated' => true, 'post_id' => 9001, 'edit_url' => '#', 'is_demo' => true]);
        }

        if (preg_match('#^/sseo-ai/v1/clusters/\d+$#', $route) && $method === 'DELETE') {
            return $this->response(['success' => true, 'simulated' => true, 'is_demo' => true]);
        }

        if ($route === '/sseo-ai/v1/ranks/keywords' && $method === 'GET') {
            return $this->response(['keywords' => Fyndable_Demo_Fixtures::ranks(), 'is_demo' => true]);
        }

        if (preg_match('#^/sseo-ai/v1/ranks/history/(\d+)$#', $route, $matches) && $method === 'GET') {
            return $this->response(['history' => Fyndable_Demo_Fixtures::rankHistory((int) $matches[1]), 'is_demo' => true]);
        }

        if ($route === '/sseo-ai/v1/ranks/check-now' && $method === 'POST') {
            return $this->response(['success' => true, 'checked' => count(Fyndable_Demo_Fixtures::ranks()), 'is_demo' => true]);
        }

        if ($route === '/sseo-ai/v1/content-brief' && $method === 'POST') {
            if ($this->allowLiveContentBrief($request)) {
                $this->liveRequestAllowed = true;
                return $result;
            }
            return $this->response(Fyndable_Demo_Fixtures::contentBrief(sanitize_text_field((string) $request->get_param('keyword'))));
        }

        if ($route === '/sseo-ai/v1/apexflow/status' && $method === 'GET') {
            return $this->response(Fyndable_Demo_Fixtures::apexflow());
        }

        if ($route === '/sseo-ai/v1/apexflow/preview' && $method === 'POST') {
            return $this->response(['success' => true, 'plan' => Fyndable_Demo_Fixtures::apexflowPlan(), 'is_demo' => true]);
        }

        if ($route === '/sseo-ai/v1/created-posts' && $method === 'GET') {
            $posts = Fyndable_Demo_Fixtures::createdPosts();
            return $this->response([
                'posts' => $posts,
                'total' => count($posts),
                'total_pages' => 1,
                'page' => 1,
                'per_page' => 10,
                'is_demo' => true,
            ]);
        }

        if ($route === '/sseo-ai/v1/created-posts/stats' && $method === 'GET') {
            return $this->response(Fyndable_Demo_Fixtures::createdPostStats());
        }

        if (str_starts_with($route, '/sseo-ai/v1/created-posts/') && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return $this->response(['success' => true, 'simulated' => true, 'is_demo' => true]);
        }

        if (str_starts_with($route, '/sseo-ai/v1/apexflow/') && $method === 'POST') {
            return $this->response(['success' => true, 'simulated' => true, 'is_demo' => true]);
        }

        if (str_starts_with($route, '/sseo-ai/v1/') && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return new \WP_Error(
                'fyndable_demo_action_blocked',
                __('This action is disabled in the sales demo to protect real site data.', 'fyndable-demo'),
                ['status' => 409, 'is_demo' => true]
            );
        }

        return $result;
    }

    private function allowLiveContentBrief(\WP_REST_Request $request): bool
    {
        if (get_option('fyndable_demo_live_enabled', '0') !== '1' || $request->get_header('X-Fyndable-Demo-Live') !== '1') {
            return false;
        }

        $state = $this->getState();
        if ((int) $state['live_calls'] >= self::LIVE_LIMIT) {
            return false;
        }

        $state['live_calls'] = (int) $state['live_calls'] + 1;
        $this->saveState($state);
        return true;
    }

    private function response(array $data): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data, 200);
        $response->header('X-Fyndable-Demo', 'fixture');
        return $response;
    }

    private function getKeywords(): array
    {
        $state = $this->getState();
        $keywords = array_merge(Fyndable_Demo_Fixtures::keywords(), $state['keywords']);
        return array_values(array_filter($keywords, static fn(array $row): bool => !in_array((int) $row['id'], $state['deleted_keywords'], true)));
    }

    private function getState(): array
    {
        $state = get_user_meta(get_current_user_id(), self::STATE_META, true);
        if (!is_array($state)) {
            $state = [];
        }
        return array_merge(['keywords' => [], 'deleted_keywords' => [], 'live_calls' => 0], $state);
    }

    private function saveState(array $state): void
    {
        update_user_meta(get_current_user_id(), self::STATE_META, $state);
    }

    public function resetState(): void
    {
        check_ajax_referer('fyndable_demo', 'nonce');
        if (!$this->canUseDemo()) {
            wp_send_json_error(['message' => __('Permission denied.', 'fyndable-demo')], 403);
        }
        delete_user_meta(get_current_user_id(), self::STATE_META);
        wp_send_json_success(['message' => __('Demo reset.', 'fyndable-demo')]);
    }

    public function saveSettings(): void
    {
        check_ajax_referer('fyndable_demo', 'nonce');
        if (!$this->canUseDemo()) {
            wp_send_json_error(['message' => __('Permission denied.', 'fyndable-demo')], 403);
        }
        update_option('fyndable_demo_live_enabled', isset($_POST['live_enabled']) && $_POST['live_enabled'] === '1' ? '1' : '0');
        wp_send_json_success();
    }
}

<?php

namespace AISEOAssistant;

class AdminPage
{
    private string $pluginFile;
    private Settings $settings;
    private SerpService $serp;
    private SnapshotRepository $snapshots;
    private LlmClient $llm;
    private HealthLogger $health;
    private AiOverviewRepository $aiOverviewRepo;
    private CompetitorGapService $competitorGap;
    private KeywordExplorer $keywordExplorer;
    private AuditService $audit;
    private BulkActions $bulk;
    private PageSpeedClient $pageSpeed;
    private PostTypes $postTypes;
    private ImageClient $imageClient;
    private GscClient $gsc;
    private TopicRepository $topics;
    private CalendarWorkflow $calendarWorkflow;
    private PsiLogger $psiLogger;
    private TechChecker $techChecker;
    private PsiAlerter $psiAlerter;
    
    // New modules
    private TruSEOSCORE $truseoScore;
    private RedirectionManager $redirectionManager;
    private NotFoundMonitor $notFoundMonitor;
    private LinkAssistant $linkAssistant;
    private ImageAltGenerator $imageAltGenerator;
    private LocalSEO $localSEO;
    private ImportExport $importExport;
    private RolePermissions $rolePermissions;
    private RobotsTxt $robotsTxt;
    
    // License management
    private LicenseManager $licenseManager;
    private LicenseAPI $licenseAPI;
    private FeatureGate $featureGate;
    
    // Content decay detection
    private ContentDecay $contentDecay;

    public function __construct(string $pluginFile, Settings $settings, SerpService $serp, SnapshotRepository $snapshots, LlmClient $llm, HealthLogger $health, AiOverviewRepository $aiOverviewRepo, CompetitorGapService $competitorGap, KeywordExplorer $keywordExplorer, AuditService $audit, BulkActions $bulk, PageSpeedClient $pageSpeed, PostTypes $postTypes, ImageClient $imageClient, GscClient $gsc, TopicRepository $topics, CalendarWorkflow $calendarWorkflow, PsiLogger $psiLogger, TechChecker $techChecker, PsiAlerter $psiAlerter, TruSEOSCORE $truseoScore, RedirectionManager $redirectionManager, NotFoundMonitor $notFoundMonitor, LinkAssistant $linkAssistant, ImageAltGenerator $imageAltGenerator, LocalSEO $localSEO, ImportExport $importExport, RolePermissions $rolePermissions, RobotsTxt $robotsTxt, LicenseManager $licenseManager, LicenseAPI $licenseAPI, FeatureGate $featureGate, ContentDecay $contentDecay)
    {
        $this->pluginFile = $pluginFile;
        $this->settings = $settings;
        $this->serp = $serp;
        $this->snapshots = $snapshots;
        $this->llm = $llm;
        $this->health = $health;
        $this->aiOverviewRepo = $aiOverviewRepo;
        $this->competitorGap = $competitorGap;
        $this->keywordExplorer = $keywordExplorer;
        $this->audit = $audit;
        $this->bulk = $bulk;
        $this->pageSpeed = $pageSpeed;
        $this->postTypes = $postTypes;
        $this->imageClient = $imageClient;
        $this->gsc = $gsc;
        $this->topics = $topics;
        $this->calendarWorkflow = $calendarWorkflow;
        $this->psiLogger = $psiLogger;
        $this->techChecker = $techChecker;
        $this->psiAlerter = $psiAlerter;
        $this->truseoScore = $truseoScore;
        $this->redirectionManager = $redirectionManager;
        $this->notFoundMonitor = $notFoundMonitor;
        $this->linkAssistant = $linkAssistant;
        $this->imageAltGenerator = $imageAltGenerator;
        $this->localSEO = $localSEO;
        $this->importExport = $importExport;
        $this->rolePermissions = $rolePermissions;
        $this->robotsTxt = $robotsTxt;
        $this->licenseManager = $licenseManager;
        $this->licenseAPI = $licenseAPI;
        $this->featureGate = $featureGate;
        $this->contentDecay = $contentDecay;
    }

    public function register(): void
    {
        add_menu_page(
            __('AI SEO Assistant', 'ai-seo-assistant'),
            __('AI SEO', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant',
            [$this, 'render'],
            'dashicons-search',
            58
        );
        
        // Register AJAX handlers for license
        add_action('wp_ajax_aiseo_activate_license', [$this, 'ajaxActivateLicense']);
        add_action('wp_ajax_aiseo_deactivate_license', [$this, 'ajaxDeactivateLicense']);
        add_action('wp_ajax_aiseo_start_trial', [$this, 'ajaxStartTrial']);
        add_action('wp_ajax_aiseo_check_license_status', [$this, 'ajaxCheckLicenseStatus']);
        add_submenu_page(
            'ai-seo-assistant',
            __('Dashboard', 'ai-seo-assistant'),
            __('Dashboard', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-dashboard',
            [$this, 'renderDashboard']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('AI Overview', 'ai-seo-assistant'),
            __('AI Overview', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-aio',
            [$this, 'renderAiOverview']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Competitor Gap', 'ai-seo-assistant'),
            __('Competitor Gap', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-competitor',
            [$this, 'renderCompetitor']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Topical Map', 'ai-seo-assistant'),
            __('Topical Map', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-topical',
            [$this, 'renderTopical']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Topic Log', 'ai-seo-assistant'),
            __('Topic Log', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-topics',
            [$this, 'renderTopics']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Audit', 'ai-seo-assistant'),
            __('Audit', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-audit',
            [$this, 'renderAudit']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Bulk Actions', 'ai-seo-assistant'),
            __('Bulk Actions', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-bulk',
            [$this, 'renderBulk']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Knowledge Base', 'ai-seo-assistant'),
            __('Knowledge Base', 'ai-seo-assistant'),
            'manage_options',
            'edit.php?post_type=aiseo_note'
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Prompt Library', 'ai-seo-assistant'),
            __('Prompt Library', 'ai-seo-assistant'),
            'manage_options',
            'edit.php?post_type=aiseo_prompt'
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Image Gen', 'ai-seo-assistant'),
            __('Image Gen', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-images',
            [$this, 'renderImages']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Calendar / GSC', 'ai-seo-assistant'),
            __('Calendar / GSC', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-calendar',
            [$this, 'renderCalendar']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Calendar Items', 'ai-seo-assistant'),
            __('Calendar Items', 'ai-seo-assistant'),
            'manage_options',
            'edit.php?post_type=aiseo_calendar'
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('SERP Snapshots', 'ai-seo-assistant'),
            __('SERP Snapshots', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-snapshots',
            [$this, 'renderSnapshots']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Redirects', 'ai-seo-assistant'),
            __('Redirects', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-redirects',
            [$this, 'renderRedirects']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('404 Monitor', 'ai-seo-assistant'),
            __('404 Monitor', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-404s',
            [$this, 'render404s']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Link Assistant', 'ai-seo-assistant'),
            __('Link Assistant', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-links',
            [$this, 'renderLinkAssistant']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Image Alt Text', 'ai-seo-assistant'),
            __('Image Alt Text', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-alt',
            [$this, 'renderImageAlt']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Import/Export', 'ai-seo-assistant'),
            __('Import/Export', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-import',
            [$this, 'renderImportExport']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('License', 'ai-seo-assistant'),
            __('License', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-license',
            [$this, 'renderLicense']
        );
        add_submenu_page(
            'ai-seo-assistant',
            __('Content Decay', 'ai-seo-assistant'),
            __('Content Decay', 'ai-seo-assistant'),
            'manage_options',
            'ai-seo-assistant-decay',
            [$this, 'renderDecayPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(Settings::OPTION_KEY, Settings::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => [],
        ]);
    }

    public function render(): void
    {
        if (isset($_POST['aiseoassistant_llm_test'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_llm_test')) {
            $hc = $this->llm->healthcheck('Short SEO outline for test keyword; give 3 bullets');
            if (is_wp_error($hc)) {
                add_settings_error('aiseoassistant_llmtest', 'error', $hc->get_error_message(), 'error');
            } else {
                add_settings_error('aiseoassistant_llmtest', 'success', sprintf(__('LLM OK via %s (%d words)', 'ai-seo-assistant'), $hc['provider'], $hc['words']), 'updated');
            }
        }

        if (isset($_GET['health_export']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'aiseoassistant_health_export')) {
            $rows = $this->health->all();
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename=\"aiseoassistant_health.csv\"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['type', 'provider', 'status', 'message', 'time']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['type'], $row['provider'], $row['status'], $row['message'], $row['time']]);
            }
            exit;
        }

        if (isset($_POST['aiseoassistant_health_clear'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_health_clear')) {
            $this->health->clear();
            add_settings_error('aiseoassistant_health', 'updated', __('Health log cleared', 'ai-seo-assistant'), 'updated');
        }

        $options = $this->settings->all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI SEO Assistant', 'ai-seo-assistant'); ?></h1>
            <?php
            settings_errors('aiseoassistant_llmtest');
            $logs = $this->health->latest(5);
            if ($logs) : ?>
                <div class="notice notice-info">
                    <p><strong><?php esc_html_e('Latest health checks', 'ai-seo-assistant'); ?></strong></p>
                    <ul>
                        <?php foreach ($logs as $row): ?>
                            <li><?php echo esc_html(sprintf('[%s] %s %s — %s', $row['type'], $row['provider'], $row['status'], $row['message'])); ?>
                                (<?php echo esc_html(get_date_from_gmt($row['time'], 'Y-m-d H:i')); ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php
                    $exportUrl = wp_nonce_url(add_query_arg(['health_export' => 1]), 'aiseoassistant_health_export');
                    ?>
                    <p>
                        <a class="button" href="<?php echo esc_url($exportUrl); ?>"><?php esc_html_e('Export health log (CSV)', 'ai-seo-assistant'); ?></a>
                    </p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php
                settings_fields(Settings::OPTION_KEY);
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Provider', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[provider]">
                                <option value="api" <?php selected($options['provider'] ?? '', 'api'); ?>>
                                    <?php esc_html_e('API (e.g., SerpAPI)', 'ai-seo-assistant'); ?>
                                </option>
                                <option value="dataforseo" <?php selected($options['provider'] ?? '', 'dataforseo'); ?>>
                                    <?php esc_html_e('DataForSEO API', 'ai-seo-assistant'); ?>
                                </option>
                                <option value="scrape" <?php selected($options['provider'] ?? '', 'scrape'); ?>>
                                    <?php esc_html_e('Self-scrape (cURL)', 'ai-seo-assistant'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Choose API for reliability; self-scrape for lower cost (respect robots.txt).', 'ai-seo-assistant'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('SerpApi Key', 'ai-seo-assistant'); ?></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($options['api_key'] ?? ''); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e('Required when provider is API.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('DataForSEO Login', 'ai-seo-assistant'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[dfs_login]" value="<?php echo esc_attr($options['dfs_login'] ?? ''); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e('Email/login for DataForSEO API.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('DataForSEO Password', 'ai-seo-assistant'); ?></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[dfs_password]" value="<?php echo esc_attr($options['dfs_password'] ?? ''); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php esc_html_e('Password/API key for DataForSEO API.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('LLM Provider', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[llm_provider]">
                                <option value="openai" <?php selected($options['llm_provider'] ?? 'openai', 'openai'); ?>>OpenAI (GPT-4.1)</option>
                                <option value="anthropic" <?php selected($options['llm_provider'] ?? '', 'anthropic'); ?>>Anthropic (Claude Opus 4.5)</option>
                                <option value="mistral" <?php selected($options['llm_provider'] ?? '', 'mistral'); ?>>Mistral (Large)</option>
                            </select>
                            <p class="description"><?php esc_html_e('Primary model; fallback order is OpenAI → Anthropic → Mistral.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('OpenAI API Key', 'ai-seo-assistant'); ?></th>
                        <td><input type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[openai_key]" value="<?php echo esc_attr($options['openai_key'] ?? ''); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Anthropic API Key', 'ai-seo-assistant'); ?></th>
                        <td><input type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[anthropic_key]" value="<?php echo esc_attr($options['anthropic_key'] ?? ''); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mistral API Key', 'ai-seo-assistant'); ?></th>
                        <td><input type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[mistral_key]" value="<?php echo esc_attr($options['mistral_key'] ?? ''); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Content Language', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[content_language]">
                                <?php
                                $languages = [
                                    'en' => 'English', 'nl' => 'Dutch', 'de' => 'German', 'fr' => 'French',
                                    'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'pl' => 'Polish',
                                    'sv' => 'Swedish', 'da' => 'Danish', 'no' => 'Norwegian', 'fi' => 'Finnish',
                                    'ja' => 'Japanese', 'ko' => 'Korean', 'zh' => 'Chinese', 'ar' => 'Arabic',
                                    'tr' => 'Turkish', 'ru' => 'Russian', 'cs' => 'Czech', 'ro' => 'Romanian',
                                ];
                                $currentLang = $options['content_language'] ?? 'en';
                                foreach ($languages as $code => $name) :
                                ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($currentLang, $code); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Language for AI-generated content and responses.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('OpenAI Model', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[openai_model]">
                                <option value="gpt-4.1" <?php selected($options['openai_model'] ?? 'gpt-4.1', 'gpt-4.1'); ?>>GPT-4.1</option>
                                <option value="gpt-4.1-mini" <?php selected($options['openai_model'] ?? '', 'gpt-4.1-mini'); ?>>GPT-4.1 Mini (faster/cheaper)</option>
                                <option value="gpt-4.1-nano" <?php selected($options['openai_model'] ?? '', 'gpt-4.1-nano'); ?>>GPT-4.1 Nano (fastest/cheapest)</option>
                                <option value="o3" <?php selected($options['openai_model'] ?? '', 'o3'); ?>>o3 (reasoning)</option>
                                <option value="o4-mini" <?php selected($options['openai_model'] ?? '', 'o4-mini'); ?>>o4-mini (reasoning, cheaper)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Anthropic Model', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[anthropic_model]">
                                <option value="claude-sonnet-4-20250514" <?php selected($options['anthropic_model'] ?? 'claude-sonnet-4-20250514', 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4</option>
                                <option value="claude-3-5-haiku-20241022" <?php selected($options['anthropic_model'] ?? '', 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (faster/cheaper)</option>
                                <option value="claude-opus-4-20250514" <?php selected($options['anthropic_model'] ?? '', 'claude-opus-4-20250514'); ?>>Claude Opus 4 (most capable)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Mistral Model', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[mistral_model]">
                                <option value="mistral-large-latest" <?php selected($options['mistral_model'] ?? 'mistral-large-latest', 'mistral-large-latest'); ?>>Mistral Large</option>
                                <option value="mistral-medium-latest" <?php selected($options['mistral_model'] ?? '', 'mistral-medium-latest'); ?>>Mistral Medium</option>
                                <option value="mistral-small-latest" <?php selected($options['mistral_model'] ?? '', 'mistral-small-latest'); ?>>Mistral Small (cheapest)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('LLM Temperature', 'ai-seo-assistant'); ?></th>
                        <td><input type="number" step="0.1" min="0" max="1" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[llm_temperature]" value="<?php echo esc_attr($options['llm_temperature'] ?? 0.4); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('LLM Max tokens', 'ai-seo-assistant'); ?></th>
                        <td><input type="number" min="64" max="4000" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[llm_max_tokens]" value="<?php echo esc_attr($options['llm_max_tokens'] ?? 600); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('API Rate Limit', 'ai-seo-assistant'); ?></th>
                        <td>
                            <input type="number" min="10" max="1000" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[llm_rate_limit]" value="<?php echo esc_attr($options['llm_rate_limit'] ?? 60); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum LLM API calls per hour (protects against runaway costs).', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Tone of voice (default)', 'ai-seo-assistant'); ?></th>
                        <td><input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[llm_tone]" value="<?php echo esc_attr($options['llm_tone'] ?? 'Professional, clear, to the point'); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Prompt presets (1 per line)', 'ai-seo-assistant'); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[llm_presets]" rows="6" cols="60" class="large-text code" placeholder="<?php esc_attr_e('Briefing template...\nFAQ focus...\nTopical map outline...', 'ai-seo-assistant'); ?>"><?php echo esc_textarea($options['llm_presets'] ?? "Blog: Write an SEO outline with H1/H2/H3, bullets and internal link suggestions.\nFAQ: Create a FAQ section with 8 questions and short answers, structured for schema.\nProduct page: Generate structure with USPs, specs, benefits, FAQ and CTA.\nTopical map: Provide H2/H3 cluster layout around the topic with internal link directions.\nLanding: Hero, proof, benefits, features, FAQ, CTA.\nCategory/Comparison: Overview, filters, top picks, FAQ, CTA."); ?></textarea>
                            <p class="description"><?php esc_html_e('Different prompt templates; shown in the editor sidebar.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Webhook alerts', 'ai-seo-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[webhook_enabled]" value="1" <?php checked(!empty($options['webhook_enabled'])); ?> />
                                <?php esc_html_e('Send alerts on healthcheck failures', 'ai-seo-assistant'); ?>
                            </label>
                            <p><input type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[webhook_url]" value="<?php echo esc_attr($options['webhook_url'] ?? ''); ?>" class="regular-text" placeholder="https://hooks.slack.com/services/..."></p>
                            <p class="description"><?php esc_html_e('Message format: emoji + TYPE • provider — message.', 'ai-seo-assistant'); ?>
                            <p>
                                <label><?php esc_html_e('Default competitor domains (comma sep)', 'ai-seo-assistant'); ?></label><br>
                                <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[competitor_domains]" value="<?php echo esc_attr($options['competitor_domains'] ?? ''); ?>" class="large-text" placeholder="ahrefs.com, moz.com">
                            </p>
                            <p>
                                <label><?php esc_html_e('PageSpeed API key (optional)', 'ai-seo-assistant'); ?></label><br>
                                <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[pagespeed_key]" value="<?php echo esc_attr($options['pagespeed_key'] ?? ''); ?>" class="regular-text">
                            </p>
                            <p>
                                <label><?php esc_html_e('Image provider', 'ai-seo-assistant'); ?></label><br>
                                <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[img_provider]">
                                    <option value="openai" <?php selected($options['img_provider'] ?? 'openai', 'openai'); ?>>OpenAI</option>
                                </select>
                            </p>
                            <p>
                                <label><?php esc_html_e('GSC proxy endpoint', 'ai-seo-assistant'); ?></label><br>
                                <input type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gsc_proxy]" value="<?php echo esc_attr($options['gsc_proxy'] ?? ''); ?>" class="regular-text" placeholder="https://your-proxy.example.com/gsc">
                            </p>
                            <p>
                                <label><?php esc_html_e('GSC OAuth (client id / secret)', 'ai-seo-assistant'); ?></label><br>
                                <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gsc_client_id]" value="<?php echo esc_attr($options['gsc_client_id'] ?? ''); ?>" class="regular-text" placeholder="client id"><br>
                                <input type="password" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[gsc_client_secret]" value="<?php echo esc_attr($options['gsc_client_secret'] ?? ''); ?>" class="regular-text" placeholder="client secret">
                                <p class="description"><?php esc_html_e('Redirect URI', 'ai-seo-assistant'); ?>: <?php echo esc_html(rest_url('aiseoassistant/v1/gsc-callback')); ?></p>
                            </p>
                            <p>
                                <label><?php esc_html_e('Performance alert threshold (avg best position)', 'ai-seo-assistant'); ?></label><br>
                                <input type="number" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[perf_threshold]" value="<?php echo esc_attr($options['perf_threshold'] ?? 10); ?>" class="small-text">
                            </p>
                            <p>
                                <label><?php esc_html_e('PSI strategy', 'ai-seo-assistant'); ?></label><br>
                                <select name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[psi_strategy]">
                                    <option value="mobile" <?php selected($options['psi_strategy'] ?? 'mobile', 'mobile'); ?>>mobile</option>
                                    <option value="desktop" <?php selected($options['psi_strategy'] ?? 'mobile', 'desktop'); ?>>desktop</option>
                                </select>
                            </p>
                            <p>
                                <label><?php esc_html_e('LCP limit (ms)', 'ai-seo-assistant'); ?></label><br>
                                <input type="number" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[lcp_limit]" value="<?php echo esc_attr($options['lcp_limit'] ?? 2500); ?>" class="small-text">
                            </p>
                            <p>
                                <label><?php esc_html_e('CLS limit', 'ai-seo-assistant'); ?></label><br>
                                <input type="number" step="0.01" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[cls_limit]" value="<?php echo esc_attr($options['cls_limit'] ?? 0.1); ?>" class="small-text">
                            </p>
                            <p>
                                <label><?php esc_html_e('INP limit (ms)', 'ai-seo-assistant'); ?></label><br>
                                <input type="number" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[inp_limit]" value="<?php echo esc_attr($options['inp_limit'] ?? 200); ?>" class="small-text">
                            </p>
                            <p><strong><?php esc_html_e('SaaS settings', 'ai-seo-assistant'); ?></strong></p>
                            <p>
                                <label><?php esc_html_e('Tenant key', 'ai-seo-assistant'); ?></label><br>
                                <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[tenant_key]" value="<?php echo esc_attr($options['tenant_key'] ?? ''); ?>" class="regular-text">
                            </p>
                            <p>
                                <label><?php esc_html_e('SaaS API base URL', 'ai-seo-assistant'); ?></label><br>
                                <input type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[saas_api_base]" value="<?php echo esc_attr($options['saas_api_base'] ?? ''); ?>" class="regular-text" placeholder="https://api.your-saas.com">
                            </p>
                            <p>
                                <label><?php esc_html_e('Webhook secret', 'ai-seo-assistant'); ?></label><br>
                                <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[webhook_secret]" value="<?php echo esc_attr($options['webhook_secret'] ?? ''); ?>" class="regular-text">
                            </p>
                            <p>
                                <label><?php esc_html_e('License Server URL', 'ai-seo-assistant'); ?></label><br>
                                <input type="url" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[license_server_url]" value="<?php echo esc_attr($options['license_server_url'] ?? ''); ?>" class="regular-text" placeholder="https://licenses.your-saas.com">
                                <p class="description"><?php esc_html_e('URL for license validation and activation (for SAAS deployments)', 'ai-seo-assistant'); ?></p>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Country (gl)', 'ai-seo-assistant'); ?></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[country]" value="<?php echo esc_attr($options['country'] ?? 'us'); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Two-letter country code used for queries.', 'ai-seo-assistant'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Fallback enabled', 'ai-seo-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[fallback_enabled]" value="1" <?php checked(!empty($options['fallback_enabled'])); ?> />
                                <?php esc_html_e('Try other providers if the selected one fails', 'ai-seo-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h3><?php esc_html_e('Sitemap Settings', 'ai-seo-assistant'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Video Sitemap', 'ai-seo-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enable_video_sitemap]" value="1" <?php checked(!empty($options['enable_video_sitemap'])); ?> />
                                <?php esc_html_e('Generate video sitemap for posts with embedded videos', 'ai-seo-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable News Sitemap', 'ai-seo-assistant'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[enable_news_sitemap]" value="1" <?php checked(!empty($options['enable_news_sitemap'])); ?> />
                                <?php esc_html_e('Generate Google News sitemap for recent articles', 'ai-seo-assistant'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php $this->localSEO->renderSettings(); ?>
                
                <?php $this->robotsTxt->renderSettings(); ?>
                
                <?php $this->rolePermissions->renderPermissionsSettings(); ?>
                
                <?php submit_button(); ?>
            </form>

            <form method="post" style="margin-top:16px;">
                <?php wp_nonce_field('aiseoassistant_llm_test'); ?>
                <input type="hidden" name="aiseoassistant_llm_test" value="1">
                <?php submit_button(__('Test LLM', 'ai-seo-assistant'), 'secondary', 'submit', false); ?>
            </form>
            <form method="post" style="margin-top:8px;">
                <?php wp_nonce_field('aiseoassistant_health_clear'); ?>
                <input type="hidden" name="aiseoassistant_health_clear" value="1">
                <?php submit_button(__('Clear health log', 'ai-seo-assistant'), 'delete', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public function renderSnapshots(): void
    {
        if (isset($_GET['export']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'aiseoassistant_export')) {
            $filterKeyword = isset($_GET['k']) ? sanitize_text_field($_GET['k']) : '';
            $rows = $this->snapshots->latest(200, $filterKeyword);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="aiseoassistant_snapshots.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['keyword', 'provider', 'created_at', 'position', 'title', 'link', 'snippet']);
            foreach ($rows as $row) {
                $organic = is_array($row['results']) ? $row['results'] : [];
                foreach ($organic as $item) {
                    fputcsv($out, [
                        $row['keyword'],
                        $row['provider'],
                        $row['created_at'],
                        $item['position'] ?? '',
                        $item['title'] ?? '',
                        $item['link'] ?? '',
                        $item['snippet'] ?? '',
                    ]);
                }
            }
            exit;
        }

        if (isset($_POST['aiseoassistant_manual_keyword'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_manual_fetch')) {
            $keyword = sanitize_text_field($_POST['aiseoassistant_manual_keyword']);
            $result = $this->serp->fetch($keyword);
            if (!is_wp_error($result)) {
                $this->snapshots->save($keyword, $result['provider'], $result['results']);
                add_settings_error('aiseoassistant_manual', 'success', sprintf(__('Snapshot saved via %s.', 'ai-seo-assistant'), $result['provider']), 'updated');
            } else {
                add_settings_error('aiseoassistant_manual', 'error', $result->get_error_message(), 'error');
            }
        }

        if (isset($_POST['aiseoassistant_healthcheck'], $_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_healthcheck')) {
            $provider = sanitize_text_field($_POST['provider'] ?? $this->settings->get('provider', 'api'));
            $hc = $this->serp->healthcheck($provider);
            if (is_wp_error($hc)) {
                add_settings_error('aiseoassistant_healthcheck', 'error', $hc->get_error_message(), 'error');
            } else {
                add_settings_error('aiseoassistant_healthcheck', 'success', sprintf(__('Healthcheck OK for %s (%d results)', 'ai-seo-assistant'), $provider, $hc['count']), 'updated');
            }
            // Log it
            if (!is_wp_error($hc)) {
                $this->health->log('serp', $provider, 'ok', sprintf('OK (%d resultaten)', $hc['count']));
            } else {
                $this->health->log('serp', $provider, 'error', $hc->get_error_message());
            }
        }

        $filterKeyword = isset($_GET['k']) ? sanitize_text_field($_GET['k']) : '';
        $rows = $this->snapshots->latest(50, $filterKeyword);
        settings_errors('aiseoassistant_manual');
        settings_errors('aiseoassistant_healthcheck');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('SERP Snapshots', 'ai-seo-assistant'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('aiseoassistant_manual_fetch'); ?>
                <label for="aiseoassistant_manual_keyword" class="screen-reader-text"><?php esc_html_e('Keyword', 'ai-seo-assistant'); ?></label>
                <input type="text" name="aiseoassistant_manual_keyword" id="aiseoassistant_manual_keyword" required placeholder="<?php esc_attr_e('e.g., best seo ai', 'ai-seo-assistant'); ?>" class="regular-text">
                <?php submit_button(__('Fetch now', 'ai-seo-assistant'), 'primary', 'submit', false); ?>
            </form>

            <form method="post" style="margin-top:12px;">
                <?php wp_nonce_field('aiseoassistant_healthcheck'); ?>
                <input type="hidden" name="aiseoassistant_healthcheck" value="1">
                <select name="provider">
                    <?php foreach (['api' => 'SerpApi', 'dataforseo' => 'DataForSEO', 'scrape' => 'Scrape'] as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $this->settings->get('provider', 'api')); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Run healthcheck', 'ai-seo-assistant'), 'secondary', 'submit', false); ?>
            </form>

            <form method="get" style="margin-top:12px;">
                <input type="hidden" name="page" value="ai-seo-assistant-snapshots">
                <label for="filter_k"><?php esc_html_e('Filter keyword', 'ai-seo-assistant'); ?></label>
                <input type="text" name="k" id="filter_k" value="<?php echo esc_attr($filterKeyword); ?>" placeholder="<?php esc_attr_e('keyword contains...', 'ai-seo-assistant'); ?>">
                <?php submit_button(__('Filter', 'ai-seo-assistant'), 'secondary', 'submit', false); ?>
                <?php
                $exportUrl = wp_nonce_url(add_query_arg(['page' => 'ai-seo-assistant-snapshots', 'export' => 1, 'k' => $filterKeyword], admin_url('admin.php')), 'aiseoassistant_export');
                ?>
                <a class="button" href="<?php echo esc_url($exportUrl); ?>"><?php esc_html_e('Export CSV', 'ai-seo-assistant'); ?></a>
            </form>

            <h2 class="mt-3"><?php esc_html_e('Latest snapshots', 'ai-seo-assistant'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Keyword', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Provider', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Created', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Top results', 'ai-seo-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="4"><?php esc_html_e('No data yet.', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['keyword']); ?></td>
                        <td><?php echo esc_html($row['provider']); ?></td>
                        <td><?php echo esc_html(get_date_from_gmt($row['created_at'], 'Y-m-d H:i')); ?></td>
                        <td>
                            <?php
                            $organic = is_array($row['results']) ? array_slice($row['results'], 0, 3) : [];
                            foreach ($organic as $item) {
                                $title = esc_html($item['title'] ?? '');
                                $link  = esc_url($item['link'] ?? '');
                                $pos   = isset($item['position']) ? intval($item['position']) : null;
                                $snippet = esc_html($item['snippet'] ?? '');
                                if ($title && $link) {
                                    echo '<div style="margin-bottom:6px;">';
                                    if ($pos) {
                                        echo '<strong>#' . $pos . '</strong> ';
                                    }
                                    echo '<a href="' . $link . '" target="_blank" rel="noopener noreferrer">' . $title . '</a>';
                                    if ($snippet) {
                                        echo '<div style="color:#555;">' . $snippet . '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function sanitize(array $input): array
    {
        return [
            'provider'     => in_array($input['provider'] ?? '', ['api', 'scrape', 'dataforseo'], true) ? $input['provider'] : 'api',
            'api_key'      => sanitize_text_field($input['api_key'] ?? ''),
            'dfs_login'    => sanitize_text_field($input['dfs_login'] ?? ''),
            'dfs_password' => sanitize_text_field($input['dfs_password'] ?? ''),
            'llm_provider' => in_array($input['llm_provider'] ?? '', ['openai', 'anthropic', 'mistral'], true) ? $input['llm_provider'] : 'openai',
            'openai_key'   => trim($input['openai_key'] ?? ''),
            'anthropic_key'=> trim($input['anthropic_key'] ?? ''),
            'mistral_key'  => trim($input['mistral_key'] ?? ''),
            'content_language' => sanitize_text_field($input['content_language'] ?? 'en'),
            'openai_model' => sanitize_text_field($input['openai_model'] ?? 'gpt-4.1'),
            'anthropic_model' => sanitize_text_field($input['anthropic_model'] ?? 'claude-sonnet-4-20250514'),
            'mistral_model' => sanitize_text_field($input['mistral_model'] ?? 'mistral-large-latest'),
            'llm_temperature' => (float)($input['llm_temperature'] ?? 0.4),
            'llm_max_tokens'  => max(64, (int)($input['llm_max_tokens'] ?? 600)),
            'llm_rate_limit'  => max(10, (int)($input['llm_rate_limit'] ?? 60)),
            'llm_tone'     => sanitize_text_field($input['llm_tone'] ?? ''),
            'llm_presets'  => trim($input['llm_presets'] ?? ''),
            'country'      => sanitize_text_field($input['country'] ?? 'us'),
            'fallback_enabled' => !empty($input['fallback_enabled']) ? 1 : 0,
            'webhook_enabled' => !empty($input['webhook_enabled']) ? 1 : 0,
            'webhook_url'     => esc_url_raw($input['webhook_url'] ?? ''),
            'competitor_domains' => sanitize_text_field($input['competitor_domains'] ?? ''),
            'pagespeed_key' => sanitize_text_field($input['pagespeed_key'] ?? ''),
            'img_provider' => in_array($input['img_provider'] ?? 'openai', ['openai'], true) ? $input['img_provider'] : 'openai',
            'gsc_proxy'   => esc_url_raw($input['gsc_proxy'] ?? ''),
            'gsc_client_id' => sanitize_text_field($input['gsc_client_id'] ?? ''),
            'gsc_client_secret' => sanitize_text_field($input['gsc_client_secret'] ?? ''),
            'lcp_limit' => (int)($input['lcp_limit'] ?? 2500),
            'cls_limit' => (float)($input['cls_limit'] ?? 0.1),
            'inp_limit' => (int)($input['inp_limit'] ?? 200),
            'psi_strategy' => in_array($input['psi_strategy'] ?? 'mobile', ['mobile','desktop'], true) ? $input['psi_strategy'] : 'mobile',
            'tenant_key' => sanitize_text_field($input['tenant_key'] ?? ''),
            'saas_api_base' => esc_url_raw($input['saas_api_base'] ?? ''),
            'webhook_secret' => sanitize_text_field($input['webhook_secret'] ?? ''),
            'license_server_url' => esc_url_raw($input['license_server_url'] ?? ''),
            'enable_video_sitemap' => !empty($input['enable_video_sitemap']) ? 1 : 0,
            'enable_news_sitemap' => !empty($input['enable_news_sitemap']) ? 1 : 0,
        ];
    }

    public function renderDashboard(): void
    {
        $health = $this->health->latest(10);
        $counts = $this->snapshots->countsByProvider();
        $keywords = $this->snapshots->latestKeywords(8);
        $metrics = $this->snapshots->metrics();
        $trend = $this->snapshots->dailyAvgBest(14);
        $alertThreshold = (float)$this->settings->get('perf_threshold', 10);
        
        // Get decay stats
        $decayStats = $this->contentDecay->getDecayStats();
        
        // Get LLM cost data
        $llmCosts = $this->llm->getMonthlyCosts();

        // Enqueue modern admin styles
        wp_enqueue_style(
            'aiseo-admin-modern',
            plugins_url('assets/admin-modern.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/admin-modern.css')
        );

        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
        wp_enqueue_script(
            'aiseoassistant-dashboard',
            plugins_url('assets/dashboard.js', $this->pluginFile),
            ['chart-js'],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/dashboard.js'),
            true
        );

        wp_localize_script('aiseoassistant-dashboard', 'AISEODashboardData', [
            'counts' => $counts,
            'health' => $health,
            'trends' => $trend,
        ]);
        ?>
        <div class="aiseo-wrapper">
            <div class="aiseo-header">
                <div>
                    <h1><?php esc_html_e('AI SEO Assistant', 'ai-seo-assistant'); ?></h1>
                    <p><?php esc_html_e('Your complete SEO command center', 'ai-seo-assistant'); ?></p>
                </div>
                <span class="version">v0.1.0</span>
            </div>

            <!-- Stats Cards -->
            <div class="aiseo-stats-grid">
                <div class="aiseo-stat-card success">
                    <div class="aiseo-stat-icon">📊</div>
                    <div class="aiseo-stat-value"><?php echo esc_html($metrics['tracked'] ?? 0); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Tracked Keywords', 'ai-seo-assistant'); ?></div>
                    <div class="aiseo-stat-change positive">
                        <span>↗</span> <?php esc_html_e('Active tracking', 'ai-seo-assistant'); ?>
                    </div>
                </div>
                <div class="aiseo-stat-card info">
                    <div class="aiseo-stat-icon">🎯</div>
                    <div class="aiseo-stat-value"><?php echo esc_html($metrics['avg_pos'] ?? '—'); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Avg Best Position', 'ai-seo-assistant'); ?></div>
                    <div class="aiseo-stat-change positive">
                        <span>↗</span> <?php esc_html_e('SERP performance', 'ai-seo-assistant'); ?>
                    </div>
                </div>
                <div class="aiseo-stat-card warning">
                    <div class="aiseo-stat-icon">🏆</div>
                    <div class="aiseo-stat-value"><?php echo esc_html($metrics['top3_pct'] ?? '—'); ?>%</div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Top 3 Rankings', 'ai-seo-assistant'); ?></div>
                    <div class="aiseo-stat-change positive">
                        <span>↗</span> <?php esc_html_e('Visibility', 'ai-seo-assistant'); ?>
                    </div>
                </div>
                <div class="aiseo-stat-card danger">
                    <div class="aiseo-stat-icon">⚠️</div>
                    <div class="aiseo-stat-value"><?php echo esc_html(($decayStats['critical'] ?? 0) + ($decayStats['high'] ?? 0)); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Content Decay Alerts', 'ai-seo-assistant'); ?></div>
                    <div class="aiseo-stat-change <?php echo (($decayStats['critical'] ?? 0) + ($decayStats['high'] ?? 0)) > 0 ? 'negative' : 'positive'; ?>">
                        <span><?php echo (($decayStats['critical'] ?? 0) + ($decayStats['high'] ?? 0)) > 0 ? '⚠️' : '✓'; ?></span>
                        <?php echo (($decayStats['critical'] ?? 0) + ($decayStats['high'] ?? 0)) > 0 ? esc_html__('Needs attention', 'ai-seo-assistant') : esc_html__('All good', 'ai-seo-assistant'); ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="aiseo-card">
                <div class="aiseo-card-header">
                    <h2><span class="dashicons dashicons-lightning"></span> <?php esc_html_e('Quick Actions', 'ai-seo-assistant'); ?></h2>
                </div>
                <div class="aiseo-card-body">
                    <div class="aiseo-quick-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-snapshots')); ?>" class="aiseo-quick-action">
                            <div class="icon">🔍</div>
                            <span><?php esc_html_e('SERP Snapshots', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-competitor')); ?>" class="aiseo-quick-action">
                            <div class="icon">🎯</div>
                            <span><?php esc_html_e('Competitor Gap', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-audit')); ?>" class="aiseo-quick-action">
                            <div class="icon">🔧</div>
                            <span><?php esc_html_e('SEO Audit', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-decay')); ?>" class="aiseo-quick-action">
                            <div class="icon">📉</div>
                            <span><?php esc_html_e('Content Decay', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-bulk')); ?>" class="aiseo-quick-action">
                            <div class="icon">⚡</div>
                            <span><?php esc_html_e('Bulk Actions', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-brief')); ?>" class="aiseo-quick-action">
                            <div class="icon">📋</div>
                            <span><?php esc_html_e('Content Brief', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-writer')); ?>" class="aiseo-quick-action">
                            <div class="icon">✍️</div>
                            <span><?php esc_html_e('Content Writer', 'ai-seo-assistant'); ?></span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-license')); ?>" class="aiseo-quick-action">
                            <div class="icon">🔐</div>
                            <span><?php esc_html_e('License', 'ai-seo-assistant'); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- API Cost Tracking -->
            <div class="aiseo-card">
                <div class="aiseo-card-header">
                    <h2><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('API Usage & Costs', 'ai-seo-assistant'); ?></h2>
                    <span style="font-size:12px;color:#666;"><?php echo esc_html($llmCosts['month'] ?? date('Y-m')); ?></span>
                </div>
                <div class="aiseo-card-body">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;">
                        <div style="text-align:center;padding:15px;background:#f0f7ff;border-radius:8px;">
                            <div style="font-size:28px;font-weight:bold;color:#2563eb;"><?php echo esc_html($llmCosts['calls'] ?? 0); ?></div>
                            <div style="font-size:13px;color:#666;"><?php esc_html_e('API Calls This Month', 'ai-seo-assistant'); ?></div>
                        </div>
                        <div style="text-align:center;padding:15px;background:#f0fdf4;border-radius:8px;">
                            <div style="font-size:28px;font-weight:bold;color:#16a34a;">$<?php echo esc_html(number_format($llmCosts['total'] ?? 0, 2)); ?></div>
                            <div style="font-size:13px;color:#666;"><?php esc_html_e('Estimated Cost This Month', 'ai-seo-assistant'); ?></div>
                        </div>
                        <div style="text-align:center;padding:15px;background:#fefce8;border-radius:8px;">
                            <?php
                            $rateLimit = (int)$this->settings->get('llm_rate_limit', 60);
                            $currentCalls = get_transient('aiseo_llm_calls') ?: 0;
                            $pct = $rateLimit > 0 ? round(($currentCalls / $rateLimit) * 100) : 0;
                            ?>
                            <div style="font-size:28px;font-weight:bold;color:#ca8a04;"><?php echo esc_html($currentCalls); ?>/<?php echo esc_html($rateLimit); ?></div>
                            <div style="font-size:13px;color:#666;"><?php esc_html_e('Hourly Rate Limit', 'ai-seo-assistant'); ?></div>
                            <div style="margin-top:5px;height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden;">
                                <div style="width:<?php echo min(100, $pct); ?>%;height:100%;background:<?php echo $pct > 80 ? '#dc2626' : ($pct > 50 ? '#f59e0b' : '#22c55e'); ?>;border-radius:2px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="aiseo-grid-3">
                <div class="aiseo-card">
                    <div class="aiseo-card-header">
                        <h2><?php esc_html_e('Snapshots per Provider', 'ai-seo-assistant'); ?></h2>
                    </div>
                    <div class="aiseo-card-body">
                        <canvas id="aiseo-counts" height="200"></canvas>
                    </div>
                </div>
                <div class="aiseo-card">
                    <div class="aiseo-card-header">
                        <h2><?php esc_html_e('Health Status', 'ai-seo-assistant'); ?></h2>
                    </div>
                    <div class="aiseo-card-body">
                        <canvas id="aiseo-health" height="200"></canvas>
                    </div>
                </div>
                <div class="aiseo-card">
                    <div class="aiseo-card-header">
                        <h2><?php esc_html_e('Avg Position (14 days)', 'ai-seo-assistant'); ?></h2>
                    </div>
                    <div class="aiseo-card-body">
                        <canvas id="aiseo-trend" height="200"></canvas>
                        <p class="description" style="margin-top:10px;">
                            <?php printf(esc_html__('Alert threshold: position #%s', 'ai-seo-assistant'), $alertThreshold); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Latest Data Row -->
            <div class="aiseo-grid-2">
                <div class="aiseo-card">
                    <div class="aiseo-card-header">
                        <h2><?php esc_html_e('Latest Healthchecks', 'ai-seo-assistant'); ?></h2>
                    </div>
                    <div class="aiseo-card-body">
                        <?php if (!empty($health)): ?>
                            <table class="aiseo-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Type', 'ai-seo-assistant'); ?></th>
                                        <th><?php esc_html_e('Provider', 'ai-seo-assistant'); ?></th>
                                        <th><?php esc_html_e('Status', 'ai-seo-assistant'); ?></th>
                                        <th><?php esc_html_e('Time', 'ai-seo-assistant'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($health, 0, 5) as $row): ?>
                                        <tr>
                                            <td><?php echo esc_html(strtoupper($row['type'])); ?></td>
                                            <td><?php echo esc_html($row['provider']); ?></td>
                                            <td>
                                                <span class="aiseo-status <?php echo $row['status'] === 'ok' ? 'success' : 'danger'; ?>">
                                                    <?php echo esc_html($row['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html(human_time_diff(strtotime($row['time']), current_time('timestamp')) . ' ago'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="aiseo-empty-state">
                                <div class="aiseo-empty-state-icon">🔍</div>
                                <h3><?php esc_html_e('No healthchecks yet', 'ai-seo-assistant'); ?></h3>
                                <p><?php esc_html_e('Healthchecks will appear here automatically.', 'ai-seo-assistant'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="aiseo-card">
                    <div class="aiseo-card-header">
                        <h2><?php esc_html_e('Latest Keywords', 'ai-seo-assistant'); ?></h2>
                    </div>
                    <div class="aiseo-card-body">
                        <?php if (!empty($keywords)): ?>
                            <ul style="list-style:none; margin:0; padding:0;">
                                <?php foreach (array_slice($keywords, 0, 8) as $kw): ?>
                                    <li style="padding:10px 0; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center;">
                                        <span><?php echo esc_html($kw); ?></span>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-snapshots&k=' . urlencode($kw))); ?>" class="button button-small">
                                            <?php esc_html_e('View', 'ai-seo-assistant'); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="aiseo-empty-state">
                                <div class="aiseo-empty-state-icon">🔑</div>
                                <h3><?php esc_html_e('No keywords yet', 'ai-seo-assistant'); ?></h3>
                                <p><?php esc_html_e('Start tracking keywords to see them here.', 'ai-seo-assistant'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderAiOverview(): void
    {
        $rows = $this->aiOverviewRepo->latest(50);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Overview Tracker', 'ai-seo-assistant'); ?></h1>
            <p><?php esc_html_e('Detects Google AI Overview presence via scrape provider (best-effort).', 'ai-seo-assistant'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Keyword', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Provider', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('AI Overview', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Citations', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Timestamp', 'ai-seo-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5"><?php esc_html_e('No data yet.', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['keyword']); ?></td>
                        <td><?php echo esc_html($row['provider']); ?></td>
                        <td><?php echo $row['ai_overview'] ? '✅' : '—'; ?></td>
                        <td>
                            <?php
                            $cits = json_decode($row['citations'] ?? '[]', true) ?: [];
                            foreach (array_slice($cits, 0, 3) as $c) {
                                echo '<div>'.esc_html($c).'</div>';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(get_date_from_gmt($row['created_at'], 'Y-m-d H:i')); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderCompetitor(): void
    {
        $options = $this->settings->all();
        $result = null;
        $error = null;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_competitor')) {
            $keyword = sanitize_text_field($_POST['kw'] ?? '');
            $competitorsRaw = sanitize_text_field($_POST['competitors'] ?? '');
            $competitors = array_filter(array_map('trim', explode(',', $competitorsRaw)));
            $own = parse_url(home_url(), PHP_URL_HOST) ?: '';
            $analysis = $this->competitorGap->analyze($keyword, $competitors, $own);
            if (isset($analysis['error'])) {
                $error = $analysis['error'];
            } else {
                $result = $analysis;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Competitor Gap', 'ai-seo-assistant'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('aiseoassistant_competitor'); ?>
                <p>
                    <label><?php esc_html_e('Keyword', 'ai-seo-assistant'); ?></label><br>
                    <input type="text" name="kw" class="regular-text" required placeholder="<?php esc_attr_e('bv. beste seo ai', 'ai-seo-assistant'); ?>">
                </p>
                <p>
                    <label><?php esc_html_e('Competitor domains (comma separated)', 'ai-seo-assistant'); ?></label><br>
                    <input type="text" name="competitors" class="large-text" placeholder="ahrefs.com, moz.com" value="<?php echo esc_attr($options['competitor_domains'] ?? ''); ?>">
                </p>
                <?php submit_button(__('Analyseer', 'ai-seo-assistant')); ?>
            </form>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($result): ?>
                <h2><?php esc_html_e('Resultaten', 'ai-seo-assistant'); ?> (<?php echo esc_html($result['provider']); ?>)</h2>
                <p><?php printf(__('Coverage: %s%% self; Competitor hits: %s', 'ai-seo-assistant'), $result['coverage'], $result['competitor_hits']); ?></p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>#</th><th><?php esc_html_e('Title', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Host', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Type', 'ai-seo-assistant'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['rows'] as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row['position']); ?></td>
                                <td><a href="<?php echo esc_url($row['link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($row['title']); ?></a></td>
                                <td><?php echo esc_html($row['host']); ?></td>
                                <td><?php echo esc_html($row['type']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderTopical(): void
    {
        $clustered = null;
        $expanded = null;
        $error = null;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_topical')) {
            $seed = sanitize_text_field($_POST['seed'] ?? '');
            $tracked = apply_filters('aiseoassistant_tracked_keywords', []);
            if (!empty($_POST['mode']) && $_POST['mode'] === 'cluster') {
                $clustered = $this->keywordExplorer->cluster($tracked);
            } else {
                $expanded = $this->keywordExplorer->expand($seed);
                if (isset($expanded['error'])) {
                    $error = $expanded['error'];
                    $expanded = null;
                }
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Topical Map & Keyword Explorer', 'ai-seo-assistant'); ?></h1>
            <form method="post" style="margin-bottom:16px;">
                <?php wp_nonce_field('aiseoassistant_topical'); ?>
                <p>
                    <label><?php esc_html_e('Seed keyword', 'ai-seo-assistant'); ?></label><br>
                    <input type="text" name="seed" class="regular-text" placeholder="<?php esc_attr_e('bv. ai seo plugin', 'ai-seo-assistant'); ?>">
                </p>
                <p>
                    <button class="button button-primary" name="mode" value="expand"><?php esc_html_e('Expand seed', 'ai-seo-assistant'); ?></button>
                    <button class="button" name="mode" value="cluster"><?php esc_html_e('Cluster tracked keywords', 'ai-seo-assistant'); ?></button>
                </p>
            </form>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($expanded): ?>
                <h2><?php esc_html_e('Suggested n-grams', 'ai-seo-assistant'); ?></h2>
                <ol>
                    <?php foreach ($expanded['related'] as $ng => $c): ?>
                        <li><?php echo esc_html($ng); ?> (<?php echo esc_html($c); ?>)</li>
                    <?php endforeach; ?>
                </ol>
                <h3><?php esc_html_e('Top SERP titles', 'ai-seo-assistant'); ?></h3>
                <ul>
                    <?php foreach ($expanded['serp'] as $item): ?>
                        <li><a href="<?php echo esc_url($item['link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($item['title']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($clustered): ?>
                <h2><?php esc_html_e('Clusters (tracked keywords)', 'ai-seo-assistant'); ?></h2>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <?php foreach ($clustered as $cluster): ?>
                        <div style="border:1px solid #ddd; padding:8px; border-radius:6px; min-width:180px;">
                            <?php foreach ($cluster as $kw): ?>
                                <div>• <?php echo esc_html($kw); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderAudit(): void
    {
        $issues = $this->audit->auditPosts(200);
        $keywords = apply_filters('aiseoassistant_tracked_keywords', []);
        $cannibals = $this->audit->cannibalization($keywords);
        $tech = [];
        $psi = null;
        $psiDesktop = null;
        $techExtra = [];
        if (!empty($_GET['tech_audit']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'aiseoassistant_techaudit')) {
            $urls = array_map('esc_url_raw', (array)($_GET['urls'] ?? [home_url()]));
            $tech = $this->audit->technicalAudit($urls);
            if (!empty($_GET['pagespeed'])) {
                $psi = $this->pageSpeed->fetch($urls[0], 'mobile');
                $psiDesktop = $this->pageSpeed->fetch($urls[0], 'desktop');
                if (!is_wp_error($psi)) {
                    $this->psiLogger->log($urls[0], $psi);
                    $this->psiAlerter->check($urls[0], $psi);
                }
                if (!is_wp_error($psiDesktop)) {
                    $this->psiLogger->log($urls[0], $psiDesktop);
                    $this->psiAlerter->check($urls[0], $psiDesktop);
                }
            }
            $techExtra = $this->techChecker->fullCheck($urls[0]);
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Technical/Content Audit', 'ai-seo-assistant'); ?></h1>
            <h2><?php esc_html_e('Thin / missing elements', 'ai-seo-assistant'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Words', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('H2?', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Internal links', 'ai-seo-assistant'); ?></th>
                        <th><?php esc_html_e('Excerpt/meta', 'ai-seo-assistant'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($issues)): ?>
                    <tr><td colspan="5"><?php esc_html_e('No obvious issues in last 200 posts.', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($issues as $row): ?>
                    <tr>
                        <td><a href="<?php echo esc_url($row['edit_link']); ?>"><?php echo esc_html($row['title']); ?></a></td>
                        <td><?php echo esc_html($row['word_count']); ?></td>
                        <td><?php echo $row['has_h2'] ? '✅' : '⚠️'; ?></td>
                        <td><?php echo esc_html($row['internal_links']); ?></td>
                        <td><?php echo $row['excerpt'] ? '✅' : '⚠️'; ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 style="margin-top:18px;"><?php esc_html_e('Cannibalization (tracked keywords)', 'ai-seo-assistant'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr><th><?php esc_html_e('Keyword', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Posts', 'ai-seo-assistant'); ?></th></tr>
                </thead>
                <tbody>
                    <?php if (empty($cannibals)): ?>
                        <tr><td colspan="2"><?php esc_html_e('No conflicts found.', 'ai-seo-assistant'); ?></td></tr>
                    <?php else: foreach ($cannibals as $kw => $posts): ?>
                        <tr>
                            <td><?php echo esc_html($kw); ?></td>
                            <td>
                                <?php foreach ($posts as $p): ?>
                                    <div><a href="<?php echo esc_url($p['edit_link']); ?>"><?php echo esc_html($p['title']); ?></a></div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderBulk(): void
    {
        $posts = $this->bulk->findNeedingMeta(50);
        $notice = null;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_bulkmeta')) {
            $postId = intval($_POST['post_id'] ?? 0);
            $res = $this->bulk->generateMeta($postId);
            if (is_wp_error($res)) {
                $notice = ['type' => 'error', 'msg' => $res->get_error_message()];
            } else {
                $notice = ['type' => 'updated', 'msg' => sprintf(__('Meta + FAQ generated (%s)', 'ai-seo-assistant'), $res['provider'])];
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Bulk Actions', 'ai-seo-assistant'); ?></h1>
            <p><?php esc_html_e('Posts without meta/FAQ — click to auto-generate meta + FAQ.', 'ai-seo-assistant'); ?></p>
            <?php if ($notice): ?>
                <div class="notice notice-<?php echo esc_attr($notice['type']); ?>"><p><?php echo esc_html($notice['msg']); ?></p></div>
            <?php endif; ?>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Post', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Action', 'ai-seo-assistant'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="2"><?php esc_html_e('No posts found without meta.', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($posts as $p): ?>
                    <tr>
                        <td><a href="<?php echo esc_url($p['edit_link']); ?>"><?php echo esc_html($p['title']); ?></a></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('aiseoassistant_bulkmeta'); ?>
                                <input type="hidden" name="post_id" value="<?php echo esc_attr($p['ID']); ?>">
                                <button class="button button-primary" type="submit"><?php esc_html_e('Generate meta + FAQ', 'ai-seo-assistant'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 style="margin-top:18px;"><?php esc_html_e('Technical (HEAD) audit', 'ai-seo-assistant'); ?></h2>
            <form method="get" style="margin-bottom:8px;">
                <input type="hidden" name="page" value="ai-seo-assistant-audit">
                <?php wp_nonce_field('aiseoassistant_techaudit'); ?>
                <input type="text" name="urls[]" class="large-text" placeholder="https://example.com/">
                <label><input type="checkbox" name="pagespeed" value="1"> <?php esc_html_e('Include PageSpeed (mobile)', 'ai-seo-assistant'); ?></label>
                <?php submit_button(__('Run technical audit', 'ai-seo-assistant'), 'secondary', 'submit', false); ?>
                <input type="hidden" name="tech_audit" value="1">
            </form>
            <table class="widefat striped">
                <thead><tr><th>URL</th><th>Code</th><th>Canonical</th><th>Hreflang</th><th>Issues</th></tr></thead>
                <tbody>
                <?php if (empty($tech)): ?>
                    <tr><td colspan="5"><?php esc_html_e('No technical audit run yet.', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($tech as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['url']); ?></td>
                        <td><?php echo esc_html($row['code']); ?></td>
                        <td><?php echo esc_html($row['canonical']); ?></td>
                        <td><?php echo esc_html($row['hreflang']); ?></td>
                        <td><?php echo esc_html(implode(', ', $row['issues'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <?php if ($psi && !is_wp_error($psi)): ?>
                <h3 style="margin-top:12px;"><?php esc_html_e('PageSpeed (mobile)', 'ai-seo-assistant'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Performance score', 'ai-seo-assistant'); ?>: <?php echo esc_html($psi['performance'] ?? ''); ?></li>
                    <li>LCP: <?php echo esc_html($psi['lcp'] ?? ''); ?> ms</li>
                    <li>INP: <?php echo esc_html($psi['inp'] ?? ''); ?> ms</li>
                    <li>CLS: <?php echo esc_html($psi['cls'] ?? ''); ?></li>
                    <li>TTFB: <?php echo esc_html($psi['ttfb'] ?? ''); ?> ms</li>
                </ul>
                <?php if ($psiDesktop && !is_wp_error($psiDesktop)): ?>
                    <h3 style="margin-top:12px;"><?php esc_html_e('PageSpeed (desktop)', 'ai-seo-assistant'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Performance score', 'ai-seo-assistant'); ?>: <?php echo esc_html($psiDesktop['performance'] ?? ''); ?></li>
                        <li>LCP: <?php echo esc_html($psiDesktop['lcp'] ?? ''); ?> ms</li>
                        <li>INP: <?php echo esc_html($psiDesktop['inp'] ?? ''); ?> ms</li>
                        <li>CLS: <?php echo esc_html($psiDesktop['cls'] ?? ''); ?></li>
                        <li>TTFB: <?php echo esc_html($psiDesktop['ttfb'] ?? ''); ?> ms</li>
                    </ul>
                <?php endif; ?>
            <?php elseif (is_wp_error($psi)): ?>
                <div class="notice notice-error"><p><?php echo esc_html($psi->get_error_message()); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($techExtra)): ?>
                <h3><?php esc_html_e('Hreflang map & Sitemaps', 'ai-seo-assistant'); ?></h3>
                <p><?php esc_html_e('Hreflang entries:', 'ai-seo-assistant'); ?></p>
                <pre><?php echo esc_html(print_r($techExtra['hreflang_map'] ?? [], true)); ?></pre>
                <p><?php esc_html_e('Sitemaps:', 'ai-seo-assistant'); ?></p>
                <pre><?php echo esc_html(print_r($techExtra['sitemaps'] ?? [], true)); ?></pre>
                <p><?php esc_html_e('Redirect chain:', 'ai-seo-assistant'); ?></p>
                <pre><?php echo esc_html(print_r($techExtra['redirects'] ?? [], true)); ?></pre>
                <?php if (!empty($techExtra['issues'])): ?>
                    <div class="notice notice-warning"><p><?php echo esc_html(implode('; ', $techExtra['issues'])); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderImages(): void
    {
        $output = null;
        $error = null;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_img')) {
            $prompt = sanitize_text_field($_POST['img_prompt'] ?? '');
            $res = $this->imageClient->generate($prompt);
            if (is_wp_error($res)) {
                $error = $res->get_error_message();
            } else {
                $output = $res;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Image Generation', 'ai-seo-assistant'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('aiseoassistant_img'); ?>
                <p>
                    <label><?php esc_html_e('Prompt', 'ai-seo-assistant'); ?></label><br>
                    <input type="text" name="img_prompt" class="large-text" placeholder="<?php esc_attr_e('e.g. Futuristic SEO robot on laptop', 'ai-seo-assistant'); ?>">
                </p>
                <?php submit_button(__('Generate', 'ai-seo-assistant')); ?>
            </form>
            <?php if ($error): ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <?php if ($output): ?>
                <h2><?php esc_html_e('Result', 'ai-seo-assistant'); ?></h2>
                <p><?php echo esc_html($output['provider']); ?></p>
                <img src="<?php echo esc_url($output['url']); ?>" style="max-width:400px; height:auto;" />
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderCalendar(): void
    {
        $rows = [];
        $err = null;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseoassistant_gsc')) {
            $site = esc_url_raw($_POST['gsc_site'] ?? home_url());
            $start = sanitize_text_field($_POST['gsc_start'] ?? date('Y-m-d', strtotime('-7 days')));
            $end = sanitize_text_field($_POST['gsc_end'] ?? date('Y-m-d'));
            $rows = $this->gsc->query($site, $start, $end, 20);
            if (is_wp_error($rows)) {
                $err = $rows->get_error_message();
                $rows = [];
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Content Calendar & GSC', 'ai-seo-assistant'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('aiseoassistant_gsc'); ?>
                <p><label><?php esc_html_e('Site URL', 'ai-seo-assistant'); ?></label><br>
                    <input type="url" name="gsc_site" value="<?php echo esc_attr(home_url()); ?>" class="regular-text"></p>
                <p>
                    <label><?php esc_html_e('Start date', 'ai-seo-assistant'); ?></label>
                    <input type="date" name="gsc_start" value="<?php echo esc_attr(date('Y-m-d', strtotime('-7 days'))); ?>">
                    <label><?php esc_html_e('End date', 'ai-seo-assistant'); ?></label>
                    <input type="date" name="gsc_end" value="<?php echo esc_attr(date('Y-m-d')); ?>">
                </p>
                <?php submit_button(__('Fetch GSC queries', 'ai-seo-assistant')); ?>
            </form>
            <?php if ($err): ?>
                <div class="notice notice-error"><p><?php echo esc_html($err); ?></p></div>
            <?php endif; ?>
            <h2><?php esc_html_e('Top queries', 'ai-seo-assistant'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>CTR</th><th>Position</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5"><?php esc_html_e('No data', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['keys'][0] ?? ''); ?></td>
                        <td><?php echo esc_html($r['clicks'] ?? ''); ?></td>
                        <td><?php echo esc_html($r['impressions'] ?? ''); ?></td>
                        <td><?php echo esc_html(isset($r['ctr']) ? round($r['ctr']*100,2).'%' : ''); ?></td>
                        <td><?php echo esc_html(isset($r['position']) ? round($r['position'],1) : ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2 style="margin-top:20px;"><?php esc_html_e('Calendar items (CPT aiseo_calendar)', 'ai-seo-assistant'); ?></h2>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e('Title', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Status', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Due', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Keyword', 'ai-seo-assistant'); ?></th><th><?php esc_html_e('Assignee', 'ai-seo-assistant'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($upcoming)): ?>
                    <tr><td colspan="5"><?php esc_html_e('No items', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($upcoming as $item): ?>
                    <tr>
                        <td><a href="<?php echo esc_url($item['edit']); ?>"><?php echo esc_html($item['title']); ?></a></td>
                        <td><?php echo esc_html($item['status']); ?></td>
                        <td><?php echo esc_html($item['due']); ?></td>
                        <td><?php echo esc_html($item['kw']); ?></td>
                        <td><?php echo esc_html($item['assignee']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderRedirects(): void
    {
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseo_redirect')) {
            if (isset($_POST['source'], $_POST['target'])) {
                $source = sanitize_text_field($_POST['source']);
                $target = sanitize_text_field($_POST['target']);
                $type = (int)($_POST['type'] ?? 301);
                $this->redirectionManager->add($source, $target, $type);
                add_settings_error('redirect', 'success', __('Redirect added', 'ai-seo-assistant'), 'updated');
            }
            if (isset($_POST['delete_id'])) {
                $this->redirectionManager->delete((int)$_POST['delete_id']);
                add_settings_error('redirect', 'success', __('Redirect deleted', 'ai-seo-assistant'), 'updated');
            }
        }

        $redirects = $this->redirectionManager->getAll();
        $stats = $this->redirectionManager->getStats();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Redirects Manager', 'ai-seo-assistant'); ?></h1>
            
            <div style="display:flex; gap:20px; margin-bottom:20px;">
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Total Redirects', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['total']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Active', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['active']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Total Hits', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['total_hits']); ?></span>
                </div>
            </div>

            <h3><?php esc_html_e('Add New Redirect', 'ai-seo-assistant'); ?></h3>
            <form method="post">
                <?php wp_nonce_field('aiseo_redirect'); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Source URL', 'ai-seo-assistant'); ?></th>
                        <td><input type="text" name="source" class="regular-text" placeholder="/old-page" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Target URL', 'ai-seo-assistant'); ?></th>
                        <td><input type="text" name="target" class="regular-text" placeholder="/new-page" required></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Redirect Type', 'ai-seo-assistant'); ?></th>
                        <td>
                            <select name="type">
                                <option value="301">301 (Permanent)</option>
                                <option value="302">302 (Temporary)</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Add Redirect', 'ai-seo-assistant')); ?>
            </form>

            <h3><?php esc_html_e('Existing Redirects', 'ai-seo-assistant'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr><th>Source</th><th>Target</th><th>Type</th><th>Hits</th><th>Last Accessed</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($redirects as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['source_url']); ?></td>
                        <td><?php echo esc_html($r['target_url']); ?></td>
                        <td><?php echo esc_html($r['redirect_type']); ?></td>
                        <td><?php echo esc_html($r['hits']); ?></td>
                        <td><?php echo $r['last_accessed'] ? esc_html(human_time_diff(strtotime($r['last_accessed']), current_time('timestamp'))) . ' ago' : '-'; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('aiseo_redirect'); ?>
                                <input type="hidden" name="delete_id" value="<?php echo esc_attr($r['id']); ?>">
                                <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Delete this redirect?', 'ai-seo-assistant'); ?>');"><?php esc_html_e('Delete', 'ai-seo-assistant'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render404s(): void
    {
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseo_404')) {
            if (isset($_POST['resolve_id'])) {
                $this->notFoundMonitor->resolve((int)$_POST['resolve_id']);
                add_settings_error('404', 'success', __('404 marked as resolved', 'ai-seo-assistant'), 'updated');
            }
            if (isset($_POST['clear_resolved'])) {
                $this->notFoundMonitor->clearResolved();
                add_settings_error('404', 'success', __('Resolved 404s cleared', 'ai-seo-assistant'), 'updated');
            }
        }

        $stats = $this->notFoundMonitor->getStats();
        $errors = $this->notFoundMonitor->getAll(['resolved' => 0]);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('404 Monitor', 'ai-seo-assistant'); ?></h1>
            
            <div style="display:flex; gap:20px; margin-bottom:20px;">
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Total 404s', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['total']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Unresolved', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px; color:red;"><?php echo esc_html($stats['unresolved']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Total Hits', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['total_hits']); ?></span>
                </div>
            </div>

            <p>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('aiseo_404'); ?>
                    <input type="hidden" name="clear_resolved" value="1">
                    <?php submit_button(__('Clear Resolved', 'ai-seo-assistant'), 'secondary', 'submit', false); ?>
                </form>
            </p>

            <h3><?php esc_html_e('Unresolved 404s', 'ai-seo-assistant'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr><th>URL</th><th>Hits</th><th>First Hit</th><th>Referrer</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($errors as $e): ?>
                    <tr>
                        <td><code><?php echo esc_html($e['url']); ?></code></td>
                        <td><?php echo esc_html($e['hit_count']); ?></td>
                        <td><?php echo esc_html($e['first_hit']); ?></td>
                        <td><?php echo $e['referrer'] ? esc_html($e['referrer']) : '-'; ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('aiseo_404'); ?>
                                <input type="hidden" name="resolve_id" value="<?php echo esc_attr($e['id']); ?>">
                                <button type="submit" class="button button-small"><?php esc_html_e('Resolve', 'ai-seo-assistant'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderLinkAssistant(): void
    {
        $stats = $this->linkAssistant->getLinkStats();
        $orphans = $this->linkAssistant->getOrphanPages();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Link Assistant', 'ai-seo-assistant'); ?></h1>
            
            <div style="display:flex; gap:20px; margin-bottom:20px;">
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Posts with Internal Links', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['posts_with_links']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Orphan Pages', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px; color:<?php echo $stats['orphan_pages'] > 0 ? 'orange' : 'green'; ?>;"><?php echo esc_html($stats['orphan_pages']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Link Coverage', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['coverage_percent']); ?>%</span>
                </div>
            </div>

            <h3><?php esc_html_e('Orphan Pages (no internal links pointing to them)', 'ai-seo-assistant'); ?></h3>
            <?php if ($orphans): ?>
            <table class="widefat striped">
                <thead>
                    <tr><th>Title</th><th>Type</th><th>Published</th><th>Action</th></tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($orphans, 0, 20) as $p): ?>
                    <tr>
                        <td><a href="<?php echo esc_url($p['url']); ?>" target="_blank"><?php echo esc_html($p['title']); ?></a></td>
                        <td><?php echo esc_html($p['type']); ?></td>
                        <td><?php echo esc_html(human_time_diff(strtotime($p['published']), current_time('timestamp'))); ?> ago</td>
                        <td><a href="<?php echo esc_url(get_edit_post_link($p['id'])); ?>" class="button button-small"><?php esc_html_e('Edit', 'ai-seo-assistant'); ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="notice notice-success"><?php esc_html_e('No orphan pages found! Great job with internal linking.', 'ai-seo-assistant'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function renderImageAlt(): void
    {
        $stats = $this->imageAltGenerator->getStats();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Image Alt Text Generator', 'ai-seo-assistant'); ?></h1>
            
            <div style="display:flex; gap:20px; margin-bottom:20px;">
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Total Images', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['total']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('With Alt Text', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['with_alt']); ?></span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('Coverage', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px; color:<?php echo $stats['coverage'] >= 90 ? 'green' : ($stats['coverage'] >= 70 ? 'orange' : 'red'); ?>;"><?php echo esc_html($stats['coverage']); ?>%</span>
                </div>
                <div class="card" style="padding:15px;">
                    <strong><?php esc_html_e('AI Generated', 'ai-seo-assistant'); ?></strong><br>
                    <span style="font-size:24px;"><?php echo esc_html($stats['ai_generated']); ?></span>
                </div>
            </div>

            <p>
                <a href="<?php echo esc_url(admin_url('upload.php?mode=list')); ?>" class="button button-primary">
                    <?php esc_html_e('Go to Media Library', 'ai-seo-assistant'); ?>
                </a>
            </p>

            <p class="description">
                <?php esc_html_e('The AI Alt Text Generator automatically suggests alt text for images. You can view and apply suggestions in the Media Library or when editing individual images.', 'ai-seo-assistant'); ?>
            </p>
        </div>
        <?php
    }

    public function renderImportExport(): void
    {
        $this->importExport->renderImportExport();
    }

    public function renderLicense(): void
    {
        // Enqueue license admin assets
        wp_enqueue_style(
            'aiseo-license-admin',
            plugins_url('assets/license-admin.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/license-admin.css')
        );
        
        wp_enqueue_script(
            'aiseo-license-admin',
            plugins_url('assets/license-admin.js', $this->pluginFile),
            ['jquery'],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/license-admin.js'),
            true
        );
        
        wp_localize_script('aiseo-license-admin', 'aiseoLicense', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiseo_license_ajax'),
            'strings' => [
                'activating' => __('Activating...', 'ai-seo-assistant'),
                'deactivating' => __('Deactivating...', 'ai-seo-assistant'),
                'startingTrial' => __('Starting trial...', 'ai-seo-assistant'),
                'error' => __('An error occurred. Please try again.', 'ai-seo-assistant'),
            ],
        ]);

        // Handle license actions (fallback for non-JS)
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'aiseo_license')) {
            if (isset($_POST['license_key'])) {
                $result = $this->licenseManager->activate(sanitize_text_field($_POST['license_key']));
                if (!is_wp_error($result)) {
                    add_settings_error('license', 'success', $result['message'], 'updated');
                } else {
                    add_settings_error('license', 'error', $result->get_error_message(), 'error');
                }
            }
            if (isset($_POST['deactivate_license'])) {
                $result = $this->licenseManager->deactivate();
                if (!is_wp_error($result)) {
                    add_settings_error('license', 'success', $result['message'], 'updated');
                } else {
                    add_settings_error('license', 'error', $result->get_error_message(), 'error');
                }
            }
            if (isset($_POST['start_trial'])) {
                $result = $this->licenseManager->startTrial();
                if ($result['success']) {
                    add_settings_error('license', 'success', $result['message'], 'updated');
                } else {
                    add_settings_error('license', 'error', $result['message'], 'error');
                }
            }
        }

        $license = $this->licenseManager->getLicense();
        $status = $this->licenseManager->getStatusDisplay();
        $tier = $this->licenseManager->getTier();
        $features = $this->licenseManager->getTierFeatures($tier);
        $limits = $this->featureGate->getUsageLimits();
        settings_errors('license');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('License Management', 'ai-seo-assistant'); ?></h1>
            
            <div class="notice <?php echo esc_attr($status['class']); ?>">
                <p><strong><?php echo esc_html($status['label']); ?>:</strong> <?php echo esc_html($status['message']); ?></p>
            </div>

            <?php if (empty($license['key']) && !$this->licenseManager->isTrialActive()): ?>
            <div class="card" style="padding:20px; margin:20px 0;">
                <h2><?php esc_html_e('Start Your 14-Day Free Trial', 'ai-seo-assistant'); ?></h2>
                <p><?php esc_html_e('Get full access to all premium features for 14 days. No credit card required.', 'ai-seo-assistant'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('aiseo_license'); ?>
                    <input type="hidden" name="start_trial" value="1">
                    <?php submit_button(__('Start Free Trial', 'ai-seo-assistant'), 'primary', 'submit', false); ?>
                </form>
            </div>
            <?php endif; ?>

            <?php if (empty($license['key'])): ?>
            <div class="card" style="padding:20px; margin:20px 0;">
                <h2><?php esc_html_e('Activate License', 'ai-seo-assistant'); ?></h2>
                <p><?php esc_html_e('Enter your license key to unlock premium features.', 'ai-seo-assistant'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('aiseo_license'); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('License Key', 'ai-seo-assistant'); ?></th>
                            <td>
                                <input type="text" name="license_key" class="regular-text" placeholder="AISEO-XXXX-XXXX-XXXX" required>
                                <p class="description">
                                    <?php esc_html_e('Format: AISEO-XXXX-XXXX-XXXX', 'ai-seo-assistant'); ?><br>
                                    <a href="#" target="_blank"><?php esc_html_e('Purchase a license', 'ai-seo-assistant'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Activate License', 'ai-seo-assistant'), 'primary'); ?>
                </form>
            </div>
            <?php else: ?>
            <div class="card" style="padding:20px; margin:20px 0;">
                <h2><?php esc_html_e('License Details', 'ai-seo-assistant'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('License Key', 'ai-seo-assistant'); ?></th>
                        <td><code><?php echo esc_html(substr($license['key'], 0, 8) . '****' . substr($license['key'], -4)); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Tier', 'ai-seo-assistant'); ?></th>
                        <td><?php echo esc_html(ucfirst($tier)); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Status', 'ai-seo-assistant'); ?></th>
                        <td><?php echo esc_html(ucfirst($license['status'])); ?></td>
                    </tr>
                    <?php if (!empty($license['expires'])): ?>
                    <tr>
                        <th><?php esc_html_e('Expires', 'ai-seo-assistant'); ?></th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($license['expires']))); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e('Sites', 'ai-seo-assistant'); ?></th>
                        <td><?php echo esc_html($license['site_count']); ?> / <?php echo esc_html($license['max_sites']); ?></td>
                    </tr>
                </table>
                <form method="post">
                    <?php wp_nonce_field('aiseo_license'); ?>
                    <input type="hidden" name="deactivate_license" value="1">
                    <?php submit_button(__('Deactivate License', 'ai-seo-assistant'), 'secondary', 'submit', false); ?>
                </form>
            </div>
            <?php endif; ?>

            <div class="card" style="padding:20px; margin:20px 0;">
                <h2><?php esc_html_e('Available Features', 'ai-seo-assistant'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr><th>Feature</th><th>Free</th><th>Trial</th><th>Starter</th><th>Pro</th><th>Business</th><th>Agency</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Basic SEO</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>XML Sitemap</td><td>-</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>Schema Markup</td><td>-</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>Redirects</td><td>-</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>404 Monitor</td><td>-</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>TruSEO Score</td><td>-</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>Extended Sitemaps</td><td>-</td><td>✓</td><td>-</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>LSI Keywords</td><td>-</td><td>✓</td><td>-</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>Smart Tags</td><td>-</td><td>✓</td><td>-</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>Link Assistant</td><td>-</td><td>✓</td><td>-</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>AI Alt Text</td><td>-</td><td>✓</td><td>-</td><td>✓</td><td>✓</td><td>✓</td></tr>
                        <tr><td>Local SEO</td><td>-</td><td>-</td><td>-</td><td>-</td><td>✓</td><td>✓</td></tr>
                        <tr><td>WooCommerce SEO</td><td>-</td><td>-</td><td>-</td><td>-</td><td>✓</td><td>✓</td></tr>
                        <tr><td>AI Features</td><td>-</td><td>✓</td><td>-</td><td>-</td><td>-</td><td>✓</td></tr>
                        <tr><td>White Label</td><td>-</td><td>-</td><td>-</td><td>-</td><td>-</td><td>✓</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function renderTopics(): void
    {
        $rows = $this->topics->latest(200);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Topic Log', 'ai-seo-assistant'); ?></h1>
            <table class="widefat striped">
                <thead><tr><th>Cluster</th><th>Keyword</th><th>Source</th><th>Time</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="4"><?php esc_html_e('No topics saved.', 'ai-seo-assistant'); ?></td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html($r['cluster']); ?></td>
                        <td><?php echo esc_html($r['keyword']); ?></td>
                        <td><?php echo esc_html($r['source']); ?></td>
                        <td><?php echo esc_html($r['created_at']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX handler for license activation
     */
    public function ajaxActivateLicense(): void
    {
        check_ajax_referer('aiseo_license_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-assistant')]);
        }
        
        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        
        if (empty($licenseKey)) {
            wp_send_json_error(['message' => __('Please enter a license key', 'ai-seo-assistant')]);
        }
        
        $result = $this->licenseManager->activate($licenseKey);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => $result['message'], 'license' => $result['license']]);
    }

    /**
     * AJAX handler for license deactivation
     */
    public function ajaxDeactivateLicense(): void
    {
        check_ajax_referer('aiseo_license_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-assistant')]);
        }
        
        $result = $this->licenseManager->deactivate();
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => $result['message']]);
    }

    /**
     * AJAX handler for starting trial
     */
    public function ajaxStartTrial(): void
    {
        check_ajax_referer('aiseo_license_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-assistant')]);
        }
        
        $result = $this->licenseManager->startTrial();
        
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }
        
        wp_send_json_success(['message' => $result['message'], 'expires' => $result['expires']]);
    }

    /**
     * AJAX handler for checking license status
     */
    public function ajaxCheckLicenseStatus(): void
    {
        check_ajax_referer('aiseo_license_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'ai-seo-assistant')]);
        }
        
        $validation = $this->licenseManager->validate();
        $license = $this->licenseManager->getLicense();
        
        wp_send_json_success([
            'valid' => !is_wp_error($validation) && !empty($validation['valid']),
            'status' => $license['status'],
            'tier' => $this->licenseManager->getTier(),
        ]);
    }

    /**
     * Render Content Decay page
     */
    public function renderDecayPage(): void
    {
        $this->contentDecay->renderDecayPage();
    }
}

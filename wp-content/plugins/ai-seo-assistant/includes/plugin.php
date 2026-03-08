<?php

namespace AISEOAssistant;

use AISEOAssistant\Providers\ApiSerpProvider;
use AISEOAssistant\Providers\ScrapeSerpProvider;

class Plugin
{
    private string $pluginFile;
    private Settings $settings;
    private SerpService $serpService;
    private AdminPage $adminPage;
    private SnapshotRepository $snapshots;
    private EditorSidebar $editorSidebar;
    private RestRoutes $restRoutes;
    private LlmClient $llmClient;
    private HealthLogger $healthLogger;
    private AlertNotifier $notifier;
    private AiOverviewRepository $aiOverviewRepo;
    private AiOverviewTracker $aiOverviewTracker;
    private CompetitorGapService $competitorGap;
    private KeywordExplorer $keywordExplorer;
    private AuditService $auditService;
    private BulkActions $bulkActions;
    private PageSpeedClient $pageSpeed;
    private PostTypes $postTypes;
    private ImageClient $imageClient;
    private GscClient $gscClient;
    private CalendarCpt $calendarCpt;
    private TopicRepository $topicRepo;
    private CalendarWorkflow $calendarWorkflow;
    private PsiLogger $psiLogger;
    private TechChecker $techChecker;
    private PsiAlerter $psiAlerter;
    private SaasClient $saasClient;
    private SaasWebhook $saasWebhook;

    // New feature modules
    private SitemapGenerator $sitemapGenerator;
    private SchemaMarkup $schemaMarkup;
    private RobotsTxt $robotsTxt;
    private RedirectionManager $redirectionManager;
    private NotFoundMonitor $notFoundMonitor;
    private TruSEOSCORE $truseoScore;
    private LinkAssistant $linkAssistant;
    private SocialMedia $socialMedia;
    private AIRepurposer $aiRepurposer;
    private ImageAltGenerator $imageAltGenerator;
    private LSIKeywords $lsiKeywords;
    private SmartTags $smartTags;
    private LocalSEO $localSEO;
    private WooCommerceSEO $woocommerceSEO;
    private ImportExport $importExport;
    private SEORevisions $seoRevisions;
    private RolePermissions $rolePermissions;
    private ExtendedSitemaps $extendedSitemaps;

    // License management
    private LicenseManager $licenseManager;
    private LicenseAPI $licenseAPI;
    private FeatureGate $featureGate;
    
    // Content decay detection
    private ContentDecay $contentDecay;

    // Content creation (killer features)
    private ContentBrief $contentBrief;
    private ContentWriter $contentWriter;

    // SaaS / Multi-tenancy
    private TenantRepository $tenants;
    private LicenseKeyGenerator $licenseGenerator;
    private LicenseAdmin $licenseAdmin;

    private const LLM_HEALTH_HOOK = 'aiseoassistant_llm_health';
    private const SERP_HEALTH_HOOK = 'aiseoassistant_serp_health';
    private const AI_OVERVIEW_HOOK = 'aiseoassistant_ai_overview';

    public function __construct(string $pluginFile)
    {
        $this->pluginFile = $pluginFile;
    }

    public function init(): void
    {
        $this->settings = new Settings();
        $this->snapshots = new SnapshotRepository();
        $this->snapshots->maybeCreateTable();
        $this->notifier = new AlertNotifier($this->settings);
        $this->healthLogger = new HealthLogger($this->notifier);
        $this->llmClient = new LlmClient($this->settings, $this->healthLogger, $this->tenants);
        $this->aiOverviewRepo = new AiOverviewRepository();
        $this->aiOverviewRepo->maybeCreateTable();
        $this->serpService = new SerpService($this->settings, [
            'api'     => new ApiSerpProvider($this->settings),
            'dataforseo' => new \AISEOAssistant\Providers\DataForSeoProvider($this->settings),
            'scrape'  => new ScrapeSerpProvider($this->settings),
        ], $this->snapshots, $this->healthLogger);
        $this->aiOverviewTracker = new AiOverviewTracker($this->settings, $this->aiOverviewRepo, $this->serpService);
        $this->competitorGap = new CompetitorGapService($this->serpService);
        $this->topicRepo = new TopicRepository();
        $this->topicRepo->maybeCreateTable();
        $this->keywordExplorer = new KeywordExplorer($this->serpService, $this->topicRepo);
        $this->auditService = new AuditService();
        $this->bulkActions = new BulkActions($this->llmClient);
        $this->pageSpeed = new PageSpeedClient($this->settings);
        $this->postTypes = new PostTypes();
        add_action('init', [$this->postTypes, 'register']);
        $this->imageClient = new ImageClient($this->settings);
        $this->gscClient = new GscClient($this->settings);
        $this->calendarCpt = new CalendarCpt();
        add_action('init', [$this->calendarCpt, 'register']);
        $this->calendarWorkflow = new CalendarWorkflow();
        add_action('init', [$this->calendarWorkflow, 'registerMeta']);
        $this->psiLogger = new PsiLogger();
        $this->psiAlerter = new PsiAlerter($this->healthLogger, $this->settings);
        $this->techChecker = new TechChecker();
        $this->saasClient = new SaasClient($this->settings);
        $this->saasWebhook = new SaasWebhook($this->settings, $this->snapshots, $this->healthLogger);

        // Initialize SaaS / Multi-tenancy system
        $this->tenants = new TenantRepository();
        $this->tenants->maybeCreateTables();
        $this->tenants->migrateExistingTables();
        
        // Initialize repositories with tenant support
        $this->snapshots = new SnapshotRepository($this->tenants);
        $this->snapshots->maybeCreateTable();
        
        $this->licenseGenerator = new LicenseKeyGenerator($this->tenants);
        $this->licenseAdmin = new LicenseAdmin($this->pluginFile, $this->licenseGenerator, $this->tenants);

        // Initialize License Management
        $this->licenseManager = new LicenseManager($this->settings->get('license_server_url', ''), 'ai-seo-assistant-pro');
        $this->licenseAPI = new LicenseAPI($this->licenseManager);
        $this->featureGate = new FeatureGate($this->licenseManager);

        // Initialize new feature modules
        $this->sitemapGenerator = new SitemapGenerator($this->pluginFile, $this->settings);
        $this->sitemapGenerator->register();
        
        $this->schemaMarkup = new SchemaMarkup($this->settings);
        $this->schemaMarkup->register();
        
        $this->robotsTxt = new RobotsTxt($this->settings);
        $this->robotsTxt->register();
        
        $this->redirectionManager = new RedirectionManager($this->settings);
        $this->redirectionManager->register();
        
        $this->notFoundMonitor = new NotFoundMonitor($this->settings);
        $this->notFoundMonitor->register();
        
        $this->truseoScore = new TruSEOSCORE($this->settings, $this->llmClient);
        $this->truseoScore->register();
        
        $this->linkAssistant = new LinkAssistant($this->settings);
        $this->linkAssistant->register();
        
        $this->socialMedia = new SocialMedia($this->settings);
        $this->socialMedia->register();
        
        $this->aiRepurposer = new AIRepurposer($this->settings, $this->llmClient);
        $this->aiRepurposer->register();
        
        $this->imageAltGenerator = new ImageAltGenerator($this->settings, $this->llmClient, $this->imageClient);
        $this->imageAltGenerator->register();
        
        $this->lsiKeywords = new LSIKeywords($this->settings, $this->llmClient);
        $this->lsiKeywords->register();
        
        $this->smartTags = new SmartTags($this->llmClient);
        $this->smartTags->register();
        
        $this->localSEO = new LocalSEO($this->settings);
        $this->localSEO->register();
        
        $this->woocommerceSEO = new WooCommerceSEO($this->settings, $this->schemaMarkup);
        $this->woocommerceSEO->register();
        
        $this->importExport = new ImportExport($this->settings);
        $this->importExport->register();
        
        $this->seoRevisions = new SEORevisions();
        $this->seoRevisions->register();
        $this->seoRevisions->registerRestRoutes();
        
        $this->rolePermissions = new RolePermissions();
        $this->rolePermissions->register();
        
        $this->extendedSitemaps = new ExtendedSitemaps($this->sitemapGenerator, $this->settings);
        $this->extendedSitemaps->register();

        // Initialize Content Decay Detection
        $this->contentDecay = new ContentDecay($this->snapshots, $this->gscClient, $this->settings);
        $this->contentDecay->maybeCreateTable();
        $this->contentDecay->register();

        // Initialize Content Brief & Writer (Surfer SEO + WP SEO AI killer features)
        $this->contentBrief = new ContentBrief($this->serpService, $this->llmClient, $this->settings);
        $this->contentBrief->register();
        
        $this->contentWriter = new ContentWriter($this->llmClient, $this->settings, $this->contentBrief);
        $this->contentWriter->register();

        $this->adminPage = new AdminPage($this->pluginFile, $this->settings, $this->serpService, $this->snapshots, $this->llmClient, $this->healthLogger, $this->aiOverviewRepo, $this->competitorGap, $this->keywordExplorer, $this->auditService, $this->bulkActions, $this->pageSpeed, $this->postTypes, $this->imageClient, $this->gscClient, $this->topicRepo, $this->calendarWorkflow, $this->psiLogger, $this->techChecker, $this->truseoScore, $this->redirectionManager, $this->notFoundMonitor, $this->linkAssistant, $this->imageAltGenerator, $this->localSEO, $this->importExport, $this->rolePermissions, $this->robotsTxt, $this->licenseManager, $this->licenseAPI, $this->featureGate, $this->contentDecay);
        $this->editorSidebar = new EditorSidebar($this->pluginFile, $this->settings);
        $this->restRoutes = new RestRoutes($this->settings, $this->llmClient);

        add_action('admin_menu', [$this->adminPage, 'register']);
        add_action('admin_menu', [$this->licenseAdmin, 'register']);
        add_action('admin_enqueue_scripts', [$this->licenseAdmin, 'enqueueAssets']);
        add_action('admin_init', [$this->adminPage, 'registerSettings']);
        add_action('admin_init', [$this, 'checkLicenseOnAdminInit']);
        add_action('enqueue_block_editor_assets', [$this->editorSidebar, 'enqueue']);
        add_action('rest_api_init', [$this->restRoutes, 'register']);
        add_action('rest_api_init', [$this->saasWebhook, 'register']);
        add_action('rest_api_init', [$this->licenseAPI, 'register']);
        // Note: REST routes for feature modules (aiRepurposer, imageAltGenerator,
        // lsiKeywords, smartTags, linkAssistant, localSEO, contentBrief, contentWriter)
        // are registered within their own register() methods called above.
        add_action(self::LLM_HEALTH_HOOK, [$this->llmClient, 'runHealthcheckJob']);
        add_action(self::SERP_HEALTH_HOOK, [$this->serpService, 'runHealthcheckJob']);
        add_action(self::AI_OVERVIEW_HOOK, [$this->aiOverviewTracker, 'scheduled']);
        
        // License validation cron
        add_action('aiseoassistant_license_check', [$this->licenseManager, 'validate']);
        
        // Content decay cron
        add_action('aiseoassistant_decay_check', [$this->contentDecay, 'runDecayCheck']);

        // Register cron hook.
        add_action(SerpService::CRON_HOOK, [$this->serpService, 'scheduledSnapshot']);

        register_activation_hook($this->pluginFile, [$this, 'activate']);
        register_deactivation_hook($this->pluginFile, [$this, 'deactivate']);
    }

    public function activate(): void
    {
        $this->snapshots->maybeCreateTable();
        $this->redirectionManager->maybeCreateTable();
        $this->notFoundMonitor->maybeCreateTable();
        $this->seoRevisions->maybeCreateTable();
        
        // Create tenant/license tables
        $this->tenants->maybeCreateTable();

        if (!wp_next_scheduled(SerpService::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'hourly', SerpService::CRON_HOOK);
        }
        if (!wp_next_scheduled(self::LLM_HEALTH_HOOK)) {
            wp_schedule_event(time() + 600, 'daily', self::LLM_HEALTH_HOOK);
        }
        if (!wp_next_scheduled(self::SERP_HEALTH_HOOK)) {
            wp_schedule_event(time() + 900, 'daily', self::SERP_HEALTH_HOOK);
        }
        if (!wp_next_scheduled(self::AI_OVERVIEW_HOOK)) {
            wp_schedule_event(time() + 1200, 'daily', self::AI_OVERVIEW_HOOK);
        }
        if (!wp_next_scheduled('aiseoassistant_license_check')) {
            wp_schedule_event(time() + 86400, 'daily', 'aiseoassistant_license_check');
        }
        if (!wp_next_scheduled('aiseoassistant_decay_check')) {
            wp_schedule_event(time() + 10800, 'daily', 'aiseoassistant_decay_check');
        }

        // Flush rewrite rules for sitemaps
        flush_rewrite_rules();
    }
    
    /**
     * Check license status on admin init
     */
    public function checkLicenseOnAdminInit(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        
        // Perform license check occasionally
        $lastCheck = get_transient('aiseo_license_admin_check');
        if ($lastCheck === false) {
            $this->licenseManager->validate();
            set_transient('aiseo_license_admin_check', time(), HOUR_IN_SECONDS * 6);
        }
    }

    public function deactivate(): void
    {
        $timestamp = wp_next_scheduled(SerpService::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, SerpService::CRON_HOOK);
        }
        $ts2 = wp_next_scheduled(self::LLM_HEALTH_HOOK);
        if ($ts2) {
            wp_unschedule_event($ts2, self::LLM_HEALTH_HOOK);
        }
        $ts3 = wp_next_scheduled(self::SERP_HEALTH_HOOK);
        if ($ts3) {
            wp_unschedule_event($ts3, self::SERP_HEALTH_HOOK);
        }
        $ts4 = wp_next_scheduled(self::AI_OVERVIEW_HOOK);
        if ($ts4) {
            wp_unschedule_event($ts4, self::AI_OVERVIEW_HOOK);
        }
        $ts5 = wp_next_scheduled('aiseoassistant_license_check');
        if ($ts5) {
            wp_unschedule_event($ts5, 'aiseoassistant_license_check');
        }
        $ts6 = wp_next_scheduled('aiseoassistant_decay_check');
        if ($ts6) {
            wp_unschedule_event($ts6, 'aiseoassistant_decay_check');
        }

        flush_rewrite_rules();
    }
}

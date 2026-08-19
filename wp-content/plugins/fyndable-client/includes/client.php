<?php

namespace SSEOAIClient;

/**
 * AI SEO Client Main Class
 * 
 * Handles license validation, communication with SaaS Dashboard,
 * and core SEO feature functionality.
 */
class Client
{
    public LicenseValidator $licenseValidator;
    private DashboardAPI $dashboardAPI;
    private Settings $settings;
    private ?LlmClient $llmClient = null;
    private ?HealthLogger $healthLogger = null;
    
    // SEO Feature instances
    private ?TruSEOScore $truSEO = null;
    private ?ContentWriter $contentWriter = null;
    private ?SchemaMarkup $schemaMarkup = null;
    private ?LinkAssistant $linkAssistant = null;
    private ?ImageAltGenerator $imageAltGenerator = null;
    private ?SitemapGenerator $sitemapGenerator = null;
    private ?SmartTags $smartTags = null;
    private ?ContentDecay $contentDecay = null;
    private ?AuditService $auditService = null;
    private ?LocalSEO $localSEO = null;
    private ?RedirectionManager $redirectManager = null;
    private ?NotFoundMonitor $notFoundMonitor = null;
    private ?RobotsTxt $robotsTxt = null;
    private ?LlmsTxt $llmsTxt = null;
    private ?SeoRevisions $seoRevisions = null;
    private ?OpenGraph $openGraph = null;
    private ?CanonicalUrl $canonicalUrl = null;
    private ?Breadcrumbs $breadcrumbs = null;
    private ?BulkActions $bulkActions = null;
    private ?SeoDashboard $seoDashboard = null;
    private ?RankTracker $rankTracker = null;
    private ?LocalSerp $localSerp = null;
    private ?Hreflang $hreflang = null;
    private ?SeoReportExport $seoReportExport = null;
    private ?WooCommerceSeo $wooSeo = null;
    private ?PlagiarismChecker $plagiarismChecker = null;
    private ?ContentOptimizer $contentOptimizer = null;
    private ?SerpCompetitor $serpCompetitor = null;
    private ?TopicCluster $topicCluster = null;
    private ?AutomationOrchestrator $automationOrchestrator = null;
    private ?KeywordDifficulty $keywordDifficulty = null;
    private ?LSIKeywords $lsiKeywords = null;
    private ?AIRepurposer $aiRepurposer = null;
    private ?RolePermissions $rolePermissions = null;
    private ?ExtendedSitemaps $extendedSitemaps = null;
    private ?PageSpeedClient $pageSpeedClient = null;
    private ?ContentBrief $contentBrief = null;
    private ?KeywordExplorer $keywordExplorer = null;
    private ?ContentRewriter $contentRewriter = null;
    private ?ReadabilityScore $readabilityScore = null;
    private ?IndexNow $indexNow = null;
    private ?DirectIndex $directIndex = null;
    private ?GscDashboard $gscDashboard = null;
    private ?BacklinkAnalyzer $backlinkAnalyzer = null;
    private ?SerpFeatureTracker $serpFeatureTracker = null;
    private ?AiOptimizationTracker $aiOptimizationTracker = null;
    private ?ExternalIntegrations $externalIntegrations = null;
    private ?CompetitorResearch $competitorResearch = null;
    private ?WhiteLabelManager $whiteLabelManager = null;
    private ?ContentPerformanceMonitor $contentPerformanceMonitor = null;
    private ?InternationalSEO $internationalSEO = null;
    private ?TechnicalSEOAuditor $technicalSEOAuditor = null;
    private ?ContentCalendar $contentCalendar = null;
    private ?PromptTemplateLibrary $promptTemplateLibrary = null;
    private ?AdvancedBacklinks $advancedBacklinks = null;
    private ?SmartInternalLinking $smartInternalLinking = null;
    private ?EEATValidator $eeatValidator = null;
    private ?FactChecker $factChecker = null;
    private ?VideoSEO $videoSEO = null;
    private ?FAQSchema $faqSchema = null;
    private ?AIImageGenerator $aiImageGenerator = null;
    private ?SimpleContentGenerator $simpleContentGenerator = null;
    private ?Ideas $ideas = null;
    private ?CreatedPosts $createdPosts = null;
    private ?Keywords $keywords = null;
    private ?ABTesting $abTesting = null;
    private ?SEODataDashboard $seoDataDashboard = null;
    private ?EditorAssistant $editorAssistant = null;
    private ?SocialSharing $socialSharing = null;
    private ?GoogleDataDashboard $googleDataDashboard = null;
    private ?PostMetaBox $postMetaBox = null;
    private ?DashboardShell $dashboardShell = null;
    private ?BrandVisibilityTracker $brandVisibility = null;
    private ?Supportickets $supportTickets = null;
    private ?PrivacyExport $privacyExport = null;
    private ?ReviewPrompt $reviewPrompt = null;
    private ?SeoImporter $seoImporter = null;
    private ?OnboardingWizard $onboardingWizard = null;
    private ?UpdateChecker $updateChecker = null;
    private ?DemoMode $demoMode = null;
    private ?AISeoAgent $aiAgent = null;
    private ?BrandVoice $brandVoice = null;
    private ?GeoContentScore $geoScore = null;
    private ?ProgrammaticSEO $programmaticSEO = null;
    private ?MultiCMSPublisher $multiCMS = null;
    private ?SerpChangeMonitor $serpMonitor = null;
    private ?SupportAssistant $supportAssistant = null;
    private ?PostAutoCleaner $postAutoCleaner = null;

    public function init(): void
    {
        $this->settings = new Settings();
        $this->licenseValidator = new LicenseValidator($this->settings);
        $this->dashboardAPI = new DashboardAPI($this->settings);
        $this->healthLogger = new HealthLogger(new AlertNotifier());
        $this->llmClient = new LlmClient($this->settings, $this->healthLogger, $this->dashboardAPI);
        $this->supportTickets = new Supportickets($this->settings, $this->dashboardAPI);

        // Support Assistant (sticky chatbot widget with KB + LLM fallback + ticket escalation)
        $this->supportAssistant = new SupportAssistant(
            $this->llmClient,
            $this->dashboardAPI,
            $this->licenseValidator
        );
        $this->supportAssistant->register();

        // GDPR privacy export/erasure — always registered regardless of license
        $this->privacyExport = new PrivacyExport();
        $this->privacyExport->register();

        // Review prompt notice (after 7 days)
        $this->reviewPrompt = new ReviewPrompt();
        $this->reviewPrompt->register();

        // SEO Importer (Yoast/RankMath/AIOSEO migration)
        $this->seoImporter = new SeoImporter();
        $this->seoImporter->register();

        // Onboarding wizard (first-run setup)
        $this->onboardingWizard = new OnboardingWizard();
        $this->onboardingWizard->register();

        // Auto-update checker (SaaS dashboard served)
        $this->updateChecker = new UpdateChecker($this->settings);
        $this->updateChecker->register();

        // Demo mode (sandbox with dummy data)
        $this->demoMode = new DemoMode();
        $this->demoMode->register();

        // Initialize license validation
        add_action('init', [$this, 'initializeLicense']);

        // Add admin menu for license activation
        add_action('admin_menu', [$this, 'registerAdminMenu'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Custom menu icon styling
        $printIconStyles = function (): void {
            echo '<style>
            #toplevel_page_fyndable-dashboard .wp-menu-image img,
            #toplevel_page_fyndable-network .wp-menu-image img {
                padding: 6px 0 0;
                opacity: 1;
                width: 22px;
            }
            </style>';
        };
        add_action('admin_head', $printIconStyles);
        add_action('network_admin_head', $printIconStyles);

        // Network admin menu (multisite)
        add_action('network_admin_menu', [$this, 'registerNetworkMenu']);

        // Handle license activation form
        add_action('admin_post_ai_seo_activate_license', [$this, 'handleLicenseActivation']);
        add_action('admin_post_ai_seo_deactivate_license', [$this, 'handleLicenseDeactivation']);
        add_action('admin_post_ai_seo_manual_validate', [$this, 'handleManualValidation']);
        
        // Handle settings save
        add_action('admin_post_ai_seo_save_settings', [$this, 'handleSettingsSave']);

        // Health check - validate license periodically
        if (!wp_next_scheduled('sseo_ai_client_license_check')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_client_license_check');
        }
        add_action('sseo_ai_client_license_check', [$this->licenseValidator, 'validateStoredLicense']);
        
        // Initialize SEO features (after init hook so license is validated)
        add_action('init', [$this, 'initializeFeatures'], 20);
    }

    public function activate(): void
    {
        // Set default options
        add_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        add_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        add_option('sseo_ai_client_dashboard_url', '');
        add_option('sseo_ai_client_license_status', 'inactive');
        add_option('sseo_ai_client_first_activation', time());
        
        // Create rank tracker tables
        $settings = new Settings();
        $dashAPI = new DashboardAPI($settings);
        $rt = new RankTracker($settings, $dashAPI);
        $rt->createTables();

        // Create ideas table
        Ideas::createTable();

        // Create keywords table
        Keywords::createTable();

        // Create A/B testing tables
        $abTesting = new ABTesting($settings);
        $abTesting->createTables();

        // Create LLM tracking table
        LLMTracker::createTable();

        // Create brand visibility table
        BrandVisibilityTracker::createTable();

        // Create onboarding status table
        OnboardingWizard::createTable();

        // Ensure sitemap rewrite rules are registered immediately
        flush_rewrite_rules();
    }

    /**
     * Initialize license on plugin load
     */
    public function initializeLicense(): void
    {
        // Check if license is valid on every page load (cached)
        $this->licenseValidator->validateStoredLicense();

        // Keep white-label in sync with the SaaS dashboard (1 minute cache)
        if (is_admin()) {
            $this->dashboardAPI->syncWhiteLabel();
        }
    }
    
    /**
     * Get the brand name to display in the plugin UI.
     *
     * Prefers the synced white-label company name. Falls back to a generic
     * brand for agency/whitelabel licenses, and Fyndable otherwise.
     */
    private function getBrandName(): string
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        if (!empty($whiteLabel['company_name'])) {
            return $whiteLabel['company_name'];
        }

        $tier = get_option('sseo_ai_client_license_tier', 'free');
        if ($tier === 'agency') {
            return 'Smart SEO';
        }

        return 'Fyndable';
    }

    /**
     * Initialize SEO features based on license tier
     */
    public function initializeFeatures(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            return;
        }
        
        $tier = $this->licenseValidator->getLicenseTier();
        
        // Core features - available to all tiers (free, starter, trial, professional, business, agency)
        $this->truSEO = new TruSEOScore($this->settings, $this->llmClient);
        $this->truSEO->register();
        
        $this->smartTags = new SmartTags($this->llmClient);
        $this->smartTags->register();
        
        $this->sitemapGenerator = new SitemapGenerator(SSEO_AI_CLIENT_PLUGIN_FILE, $this->settings);
        $this->sitemapGenerator->register();
        
        $this->robotsTxt = new RobotsTxt($this->settings);
        $this->robotsTxt->register();

        // llms.txt generator — serves /llms.txt for large language models
        $this->llmsTxt = new LlmsTxt($this->settings);
        $this->llmsTxt->register();
        
        $this->openGraph = new OpenGraph($this->settings, $this->llmClient);
        $this->openGraph->register();
        
        $this->canonicalUrl = new CanonicalUrl($this->settings);
        $this->canonicalUrl->register();
        
        $this->breadcrumbs = new Breadcrumbs($this->settings);
        $this->breadcrumbs->register();
        
        $this->seoDashboard = new SeoDashboard($this->settings);
        $this->seoDashboard->register();
        
        $this->hreflang = new Hreflang($this->settings);
        $this->hreflang->register();
        
        $this->rolePermissions = new RolePermissions();
        $this->rolePermissions->register();
        
        $this->lsiKeywords = new LSIKeywords($this->settings, $this->llmClient);
        $this->lsiKeywords->register();
        
        $this->extendedSitemaps = new ExtendedSitemaps($this->sitemapGenerator, $this->settings);
        $this->extendedSitemaps->register();
        
        $this->pageSpeedClient = new PageSpeedClient($this->settings);
        
        $this->readabilityScore = new ReadabilityScore($this->settings, $this->llmClient);
        $this->readabilityScore->register();
        
        $this->indexNow = new IndexNow($this->settings);
        $this->indexNow->register();
        
        // Direct Index (Google Indexing API) - available to all tiers
        $this->directIndex = new DirectIndex($this->settings, $this->healthLogger);
        $this->directIndex->register();
        
        // External Integrations - available to all tiers
        $this->externalIntegrations = new ExternalIntegrations($this->settings);
        $this->externalIntegrations->register();
        
        // Content Performance Monitor - available to all tiers
        $this->contentPerformanceMonitor = new ContentPerformanceMonitor($this->settings);
        $this->contentPerformanceMonitor->register();
        
        // Content Calendar - available to all tiers
        $this->contentCalendar = new ContentCalendar($this->settings, $this->llmClient, $this->licenseValidator);
        $this->contentCalendar->register();
        
        // Smart Internal Linking - available to all tiers
        $this->smartInternalLinking = new SmartInternalLinking($this->settings, $this->llmClient);
        $this->smartInternalLinking->register();
        
        // E-E-A-T Validator - available to all tiers
        $this->eeatValidator = new EEATValidator($this->settings, $this->llmClient);
        $this->eeatValidator->register();

        // Fact Checker - available to all tiers (LLM self-check with warnings)
        $this->factChecker = new FactChecker($this->llmClient);
        $this->factChecker->register();
        
        // Video SEO - available to all tiers
        $this->videoSEO = new VideoSEO($this->settings, $this->llmClient);
        $this->videoSEO->register();
        
        // FAQ Schema - available to all tiers
        $this->faqSchema = new FAQSchema($this->settings, $this->llmClient);
        $this->faqSchema->register();
        
        // AI Image Generator - available to all tiers
        $this->aiImageGenerator = new AIImageGenerator($this->settings, $this->llmClient);
        $this->aiImageGenerator->register();

        // Simple Content Generator - available to all tiers
        $this->simpleContentGenerator = new SimpleContentGenerator($this->llmClient);
        $this->simpleContentGenerator->register();

        // Editor Assistant (Gutenberg sidebar) - available to all tiers
        $this->editorAssistant = new EditorAssistant($this->llmClient);
        $this->editorAssistant->register();

        // Social Sharing - available to all tiers
        $this->socialSharing = new SocialSharing($this->settings);
        $this->socialSharing->register();

        // SEO Data Dashboard - available to all tiers
        $this->seoDataDashboard = new SEODataDashboard($this->settings);
        $this->seoDataDashboard->register();

        // Brand & AI Search Visibility - available to all tiers
        $this->brandVisibility = new BrandVisibilityTracker($this->llmClient, $this->settings);
        add_action('rest_api_init', [$this->brandVisibility, 'registerRestRoutes']);
        // Ensure the table exists for installations upgraded without reactivation
        if (get_option('sseo_ai_brand_visibility_db') !== '1') {
            BrandVisibilityTracker::createTable();
            update_option('sseo_ai_brand_visibility_db', '1');
        }

        // Ideas Management - available to all tiers
        $this->ideas = new Ideas($this->settings, $this->llmClient);
        $this->ideas->register();

        // Created Posts - available to all tiers
        $this->createdPosts = new CreatedPosts($this->settings);
        $this->createdPosts->register();

        // Post Auto-Cleaner - available to all tiers
        $this->postAutoCleaner = new PostAutoCleaner($this->settings);
        $this->postAutoCleaner->register();

        // Keywords Management - available to all tiers
        $this->keywords = new Keywords($this->settings, $this->llmClient, $this->dashboardAPI);
        $this->keywords->register();
        
        // Starter+ features
        if (in_array($tier, ['starter', 'professional', 'business', 'agency', 'trial', 'dev'])) {
            $this->linkAssistant = new LinkAssistant($this->settings);
            $this->linkAssistant->register();
            
            $this->redirectManager = new RedirectionManager($this->settings);
            $this->redirectManager->register();
            
            $this->imageAltGenerator = new ImageAltGenerator($this->settings, $this->llmClient, new ImageClient());
            $this->imageAltGenerator->register();
            
            $this->contentRewriter = new ContentRewriter($this->settings, $this->llmClient);
            $this->contentRewriter->register();
        }
        
        // Professional+ features
        if (in_array($tier, ['professional', 'business', 'agency', 'trial', 'dev'])) {
            $this->schemaMarkup = new SchemaMarkup($this->settings);
            $this->schemaMarkup->register();
            
            $this->localSEO = new LocalSEO($this->settings);
            $this->localSEO->register();

            $this->localSerp = new LocalSerp($this->settings, $this->dashboardAPI, $this->licenseValidator);
            $this->localSerp->register();

            $this->notFoundMonitor = new NotFoundMonitor($this->settings);
            $this->notFoundMonitor->register();

            $this->rankTracker = new RankTracker($this->settings, $this->dashboardAPI);
            $this->rankTracker->setLocalSerp($this->localSerp);
            $this->rankTracker->register();
            
            $this->seoReportExport = new SeoReportExport($this->settings);
            $this->seoReportExport->register();
            
            $this->wooSeo = new WooCommerceSeo($this->settings, $this->llmClient);
            $this->wooSeo->register();
            
            $this->contentOptimizer = new ContentOptimizer($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->contentOptimizer->register();
            
            $this->serpCompetitor = new SerpCompetitor($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->serpCompetitor->register();
            
            $this->keywordDifficulty = new KeywordDifficulty($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->keywordDifficulty->register();
            
            $this->contentBrief = new ContentBrief($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->contentBrief->register();

            // AI SEO Agent placeholder — instantiated after Business+ features so ContentWriter is available
            
            $this->keywordExplorer = new KeywordExplorer($this->settings, $this->dashboardAPI, $this->llmClient);
            $this->keywordExplorer->register();
            
            // Google Search Console OAuth & Dashboard
            $gscOAuth = new GscOAuth($this->settings);
            $gscOAuth->register();
            
            $gscClient = new GscClient($this->settings);
            $this->gscDashboard = new GscDashboard($this->settings, $gscClient);
            $this->gscDashboard->register();

            // Google Data Dashboard (GSC + GA4 + Google Ads unified)
            $this->googleDataDashboard = new GoogleDataDashboard($this->settings);
            $this->googleDataDashboard->register();
            
            // SERP Feature Tracker
            $this->serpFeatureTracker = new SerpFeatureTracker($this->settings, $this->llmClient);
            $this->serpFeatureTracker->register();

            // AI Optimization Tracker (DataForSEO AI Optimization via Portal proxy)
            $this->aiOptimizationTracker = new AiOptimizationTracker($this->settings, $this->dashboardAPI);
            $this->aiOptimizationTracker->register();
            
            // Backlink Analyzer
            $this->backlinkAnalyzer = new BacklinkAnalyzer($this->settings, $this->dashboardAPI);
            $this->backlinkAnalyzer->register();
            
            // Competitor Research
            $this->competitorResearch = new CompetitorResearch($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->competitorResearch->register();
            
            // International SEO
            $this->internationalSEO = new InternationalSEO($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->internationalSEO->register();
            
            // Technical SEO Auditor
            $this->technicalSEOAuditor = new TechnicalSEOAuditor($this->settings);
            $this->technicalSEOAuditor->register();
            
            // Advanced Backlinks
            $this->advancedBacklinks = new AdvancedBacklinks($this->settings, $this->llmClient);
            $this->advancedBacklinks->register();
            
            // A/B Testing
            $this->abTesting = new ABTesting($this->settings);
            $this->abTesting->register();
        }
        
        // Business+ features
        if (in_array($tier, ['business', 'agency', 'dev'])) {
            $this->promptTemplateLibrary = new PromptTemplateLibrary($this->settings, $this->licenseValidator);
            $this->promptTemplateLibrary->register();

            $this->contentWriter = new ContentWriter($this->llmClient, $this->settings, $this->contentBrief, $this->promptTemplateLibrary);
            $this->contentWriter->register();
            
            $this->aiRepurposer = new AIRepurposer($this->settings, $this->llmClient);
            $this->aiRepurposer->register();
            
            $this->bulkActions = new BulkActions($this->settings, $this->llmClient);
            $this->bulkActions->register();
            
            $snapshots = new SnapshotRepository();
            $gscClientBiz = new GscClient($this->settings);
            $this->contentDecay = new ContentDecay($snapshots, $gscClientBiz, $this->settings, $this->llmClient);
            $this->contentDecay->register();
            
            $this->auditService = new AuditService();
        }

        // AI SEO Agent — conversational interface (after all features are loaded)
        $this->aiAgent = new AISeoAgent(
            $this->llmClient,
            $this->settings,
            $this->topicCluster,
            $this->contentBrief,
            $this->contentWriter,
            $this->truSEO,
            $this->smartTags,
            $this->faqSchema
        );
        $this->aiAgent->register();

        // Brand Voice Engine — injects voice into all LLM prompts
        $this->brandVoice = new BrandVoice($this->settings);
        $this->brandVoice->register();

        // GEO Content Score — AI search citability scoring
        $this->geoScore = new GeoContentScore($this->llmClient, $this->settings);
        $this->geoScore->register();

        // Programmatic SEO — template-led page generation at scale
        $this->programmaticSEO = new ProgrammaticSEO($this->llmClient, $this->settings);
        $this->programmaticSEO->register();

        // Multi-CMS Publishing — Webflow/Shopify API integration
        $this->multiCMS = new MultiCMSPublisher($this->settings);
        $this->multiCMS->register();

        // SERP Change Monitor — auto-update content on ranking drops
        $this->serpMonitor = new SerpChangeMonitor($this->settings, $this->llmClient);
        $this->serpMonitor->register();

        // TopicCluster — instantiated once after all dependencies are available
        if (in_array($tier, ['professional', 'business', 'agency', 'trial', 'dev'])) {
            $this->topicCluster = new TopicCluster(
                $this->settings,
                $this->llmClient,
                $this->contentBrief,
                $this->contentOptimizer,
                $this->smartTags,
                $this->faqSchema,
                $this->openGraph,
                $this->truSEO,
                $this->geoScore
            );
            $this->topicCluster->register();
            add_action('sseo_ai_process_cluster_queue', [$this->topicCluster, 'processQueueItems']);
            add_filter('cron_schedules', function($schedules) {
                $schedules['sseo_ai_queue_interval'] = [
                    'interval' => 120,
                    'display' => __('Every 2 minutes (Fyndable Queue)', 'ai-seo-client'),
                ];
                return $schedules;
            });

            $this->automationOrchestrator = new AutomationOrchestrator(
                $this->settings,
                $this->licenseValidator,
                $this->topicCluster,
                $this->llmClient
            );
            $this->automationOrchestrator->register();
        }
        
        // Agency-only features (DEV includes these)
        if (in_array($tier, ['agency', 'dev'])) {
            $this->seoRevisions = new SeoRevisions();
            $this->seoRevisions->register();

            $this->plagiarismChecker = new PlagiarismChecker($this->settings, $this->llmClient);
            $this->plagiarismChecker->register();
        }

        // White-Label Branding (settings managed via SaaS dashboard / license)
        $this->whiteLabelManager = new WhiteLabelManager($this->settings);
        $this->whiteLabelManager->register();

        // Dashboard card drag-and-drop ordering
        DashboardSorter::register();

        // Unified Post Meta Box with tabs — replaces individual meta boxes
        $this->initPostMetaBox();
    }

    /**
     * Initialize the unified grouped accordion post meta box.
     * Collects all feature render methods into grouped accordion sections.
     */
    private function initPostMetaBox(): void
    {
        $this->postMetaBox = new PostMetaBox();

        // Define groups
        $this->postMetaBox->addGroup('content', __('Content & Keywords', 'ai-seo-client'), '&#9998;');
        $this->postMetaBox->addGroup('technical', __('Technical SEO', 'ai-seo-client'), '&#9881;');
        $this->postMetaBox->addGroup('social', __('Social & Schema', 'ai-seo-client'), '&#128241;');
        $this->postMetaBox->addGroup('ai', __('AI Tools', 'ai-seo-client'), '&#129302;');
        $this->postMetaBox->addGroup('advanced', __('Advanced', 'ai-seo-client'), '&#9881;');

        // Content & Keywords group
        $this->postMetaBox->addPanel('content', 'truSEO', __('SEO Score', 'ai-seo-client'), [$this->truSEO, 'renderMetaBox']);

        if ($this->lsiKeywords) {
            $this->postMetaBox->addPanel('content', 'lsi', __('Keywords', 'ai-seo-client'), [$this->lsiKeywords, 'renderMetaBox']);
        }

        if ($this->smartTags) {
            $this->postMetaBox->addPanel('content', 'smarttags', __('Smart Tags', 'ai-seo-client'), [$this->smartTags, 'renderMetaBox']);
        }

        if ($this->smartInternalLinking) {
            $this->postMetaBox->addPanel('content', 'internallinks', __('Internal Links', 'ai-seo-client'), [$this->smartInternalLinking, 'renderLinkSuggestionsMetaBox']);
        }

        if ($this->eeatValidator) {
            $this->postMetaBox->addPanel('content', 'eeat', __('E-E-A-T', 'ai-seo-client'), [$this->eeatValidator, 'renderMetaBox']);
        }

        if ($this->contentPerformanceMonitor) {
            $this->postMetaBox->addPanel('content', 'performance', __('Performance', 'ai-seo-client'), [$this->contentPerformanceMonitor, 'renderMetaBox']);
        }

        if ($this->contentCalendar && $this->licenseValidator->isBusinessPlus()) {
            $this->postMetaBox->addPanel('content', 'workflow', __('Workflow', 'ai-seo-client'), [$this->contentCalendar, 'renderWorkflowMetaBox']);
        }

        // Technical SEO group
        if ($this->canonicalUrl) {
            $this->postMetaBox->addPanel('technical', 'canonical', __('Canonical URL', 'ai-seo-client'), [$this->canonicalUrl, 'renderMetaBox']);
        }

        if ($this->hreflang) {
            $this->postMetaBox->addPanel('technical', 'hreflang', __('Hreflang', 'ai-seo-client'), [$this->hreflang, 'renderMetaBox']);
        }

        if ($this->internationalSEO) {
            $this->postMetaBox->addPanel('technical', 'international', __('International', 'ai-seo-client'), [$this->internationalSEO, 'renderMetaBox']);
        }

        if ($this->serpFeatureTracker) {
            $this->postMetaBox->addPanel('technical', 'serp', __('SERP Features', 'ai-seo-client'), [$this->serpFeatureTracker, 'renderMetaBox']);
        }

        if ($this->contentDecay) {
            $this->postMetaBox->addPanel('technical', 'decay', __('Position Trends', 'ai-seo-client'), [$this->contentDecay, 'renderDecayMetaBox']);
        }

        if ($this->seoRevisions) {
            $this->postMetaBox->addPanel('technical', 'revisions', __('SEO Revisions', 'ai-seo-client'), [$this->seoRevisions, 'renderMetaBox']);
        }

        // Social & Schema group
        if ($this->openGraph) {
            $this->postMetaBox->addPanel('social', 'opengraph', __('Social Preview', 'ai-seo-client'), [$this->openGraph, 'renderMetaBox']);
        }

        if ($this->socialSharing) {
            $this->postMetaBox->addPanel('social', 'social', __('Social Sharing', 'ai-seo-client'), [$this->socialSharing, 'renderMetaBox']);
        }

        if ($this->schemaMarkup) {
            $this->postMetaBox->addPanel('social', 'schema', __('Schema', 'ai-seo-client'), [$this->schemaMarkup, 'renderMetaBox']);
        }

        if ($this->faqSchema) {
            $this->postMetaBox->addPanel('social', 'faqschema', __('FAQ Schema', 'ai-seo-client'), [$this->faqSchema, 'renderMetaBox']);
        }

        if ($this->videoSEO) {
            $this->postMetaBox->addPanel('social', 'videoseo', __('Video SEO', 'ai-seo-client'), [$this->videoSEO, 'renderMetaBox']);
        }

        if ($this->localSEO) {
            $this->postMetaBox->addPanel('social', 'local', __('Local SEO', 'ai-seo-client'), [$this->localSEO, 'renderMetaBox']);
        }

        if ($this->wooSeo) {
            $this->postMetaBox->addPanel('social', 'woo', __('WooCommerce', 'ai-seo-client'), [$this->wooSeo, 'renderMetaBox']);
        }

        // AI Tools group
        if ($this->simpleContentGenerator) {
            $this->postMetaBox->addPanel('ai', 'contentgen', __('AI Content', 'ai-seo-client'), [$this->simpleContentGenerator, 'renderMetaBox']);
        }

        if ($this->aiImageGenerator) {
            $this->postMetaBox->addPanel('ai', 'imagegen', __('AI Image', 'ai-seo-client'), [$this->aiImageGenerator, 'renderMetaBox']);
        }

        if ($this->aiRepurposer) {
            $this->postMetaBox->addPanel('ai', 'repurpose', __('AI Repurpose', 'ai-seo-client'), [$this->aiRepurposer, 'renderMetaBox']);
        }

        if ($this->plagiarismChecker) {
            $this->postMetaBox->addPanel('ai', 'plagiarism', __('Originality', 'ai-seo-client'), [$this->plagiarismChecker, 'renderMetaBox']);
        }

        // Advanced group (attachment context for alt text)
        if ($this->imageAltGenerator) {
            $this->postMetaBox->addPanel('advanced', 'alttext', __('Alt Text', 'ai-seo-client'), [$this->imageAltGenerator, 'renderMetaBox'], 'attachment');
        }

        $this->postMetaBox->register();
    }

    /**
     * Register network admin menu (multisite)
     */
    public function registerNetworkMenu(): void
    {
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $menuName = $this->getBrandName();
        $iconUrl = dirname(plugin_dir_url(__FILE__)) . '/assets/FYN-ICON-WHITE.png';

        add_menu_page(
            $menuName,
            $menuName,
            'manage_network_options',
            'fyndable-network',
            [$this, 'renderNetworkPage'],
            $iconUrl,
            3
        );
    }

    /**
     * Render network admin page (multisite overview)
     */
    public function renderNetworkPage(): void
    {
        $sites = get_sites(['number' => 0]);
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $menuName = $this->getBrandName();

        echo '<div class="wrap"><h1>' . esc_html($menuName) . ' — Network Overview</h1>';
        echo '<p>Manage plugin settings across all sites in your network.</p>';

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        echo '<th>Site</th><th>Domain</th><th>License Status</th><th>Tier</th><th>Actions</th>';
        echo '</tr></thead><tbody>';

        foreach ($sites as $site) {
            switch_to_blog((int) $site->blog_id);
            $status = get_option('sseo_ai_client_license_status', 'inactive');
            $tier = get_option('sseo_ai_client_license_tier', 'free');
            $siteUrl = get_site_url();
            $siteName = get_bloginfo('name');
            $adminUrl = admin_url('admin.php?page=ai-seo-client');
            restore_current_blog();

            $statusColor = $status === 'active' ? 'green' : 'red';
            echo '<tr>';
            echo '<td><strong>' . esc_html($siteName) . '</strong></td>';
            echo '<td>' . esc_html($siteUrl) . '</td>';
            echo '<td><span style="color:' . esc_attr($statusColor) . ';font-weight:600;">' . esc_html(ucfirst($status)) . '</span></td>';
            echo '<td>' . esc_html(ucfirst($tier)) . '</td>';
            echo '<td><a href="' . esc_url($adminUrl) . '" class="button button-small">Manage</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        $isLicenseValid = $this->licenseValidator->isLicenseValid();
        $tier = $this->licenseValidator->getLicenseTier();
        
        // Get white-label company name if set
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $menuName = $this->getBrandName();
        $iconUrl = dirname(plugin_dir_url(__FILE__)) . '/assets/FYN-ICON-WHITE.png';

        // Initialize dashboard shell
        $this->dashboardShell = new DashboardShell($this);
        $this->dashboardShell->register();

        // Main menu — renders the full-page dashboard shell
        add_menu_page(
            $menuName,
            $menuName,
            'manage_options',
            'fyndable-dashboard',
            [$this, 'renderDashboardShell'],
            $iconUrl,
            30
        );

        // All tiers: Dashboard, Content Calendar, AI Tools, Link Manager, Integrations

        // 1. Dashboard / Statistics - all tiers (first, so the top-level menu lands here)
        add_submenu_page(
            'fyndable-dashboard',
            __('Dashboard', 'ai-seo-client'),
            __('📊 Dashboard', 'ai-seo-client'),
            'manage_options',
            'ai-seo-dashboard',
            [$this, 'renderDashboardPage']
        );

        // Connection page (separate slug so it can load in iframe)
        add_submenu_page(
            'fyndable-dashboard',
            __('Connection', 'ai-seo-client'),
            __('🔗 Connection', 'ai-seo-client'),
            'manage_options',
            'ai-seo-client',
            [$this, 'renderConnectionPage']
        );

        // Only show feature menus if license is valid
        if ($isLicenseValid) {

            // 2. Content Calendar - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Content Calendar', 'ai-seo-client'),
                __('📅 Content Calendar', 'ai-seo-client'),
                'manage_options',
                'ai-seo-content-calendar',
                [$this, 'renderContentCalendarPage']
            );

            // 3. AI Tools - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('AI Tools', 'ai-seo-client'),
                __('🤖 AI Tools', 'ai-seo-client'),
                'manage_options',
                'ai-seo-ai-tools',
                [$this, 'renderAIToolsPage']
            );

            // 4. Ideas - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Ideas', 'ai-seo-client'),
                __('💡 Ideas', 'ai-seo-client'),
                'manage_options',
                'ai-seo-ideas',
                [$this, 'renderIdeasPage']
            );

            // 5. SEO AI Created Posts - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Created Posts', 'ai-seo-client'),
                __('📝 Created Posts', 'ai-seo-client'),
                'manage_options',
                'ai-seo-created-posts',
                [$this, 'renderCreatedPostsPage']
            );

            // 6. Keywords - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Keywords', 'ai-seo-client'),
                __('🎯 Keywords', 'ai-seo-client'),
                'manage_options',
                'ai-seo-keywords',
                [$this, 'renderKeywordsPage']
            );

            // 7. Link Manager (Smart Internal Linking) - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Link Manager', 'ai-seo-client'),
                __('🔗 Link Manager', 'ai-seo-client'),
                'manage_options',
                'ai-seo-link-manager',
                [$this, 'renderLinkManagerPage']
            );

            // 7c. Competitor Research - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Competitor Research', 'ai-seo-client'),
                __('🔍 Competitor Research', 'ai-seo-client'),
                'manage_options',
                'ai-seo-competitor-research',
                [$this, 'renderCompetitorResearchPage']
            );

            // 7b. Link Genius (Auto Internal Linking) - starter+
            add_submenu_page(
                'fyndable-dashboard',
                __('Link Genius', 'ai-seo-client'),
                __('✨ Link Genius', 'ai-seo-client'),
                'manage_options',
                'ai-seo-link-genius',
                [$this, 'renderLinkGeniusPage']
            );

            // 8. Sitemaps - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Sitemaps', 'ai-seo-client'),
                __('🗺️ Sitemaps', 'ai-seo-client'),
                'manage_options',
                'ai-seo-sitemaps',
                [$this, 'renderSitemapsPage']
            );

            // 8c. Bulk Optimizer - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Bulk Optimizer', 'ai-seo-client'),
                __('✅ Bulk Optimizer', 'ai-seo-client'),
                'manage_options',
                'ai-seo-bulk',
                [$this, 'renderBulkOptimizerPage']
            );

            // 8b. Redirect Manager - starter+
            add_submenu_page(
                'fyndable-dashboard',
                __('Redirect Manager', 'ai-seo-client'),
                __('↩️ Redirect Manager', 'ai-seo-client'),
                'manage_options',
                'ai-seo-redirects',
                [$this, 'renderRedirectManagerPage']
            );

            // 9. Integrations - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('Integrations', 'ai-seo-client'),
                __('🔌 Integrations', 'ai-seo-client'),
                'manage_options',
                'ai-seo-integrations',
                [$this, 'renderIntegrationsPage']
            );

            // 9c. Support - all tiers (with unread badge)
            $unreadCount = 0;
            try {
                $unreadCount = $this->dashboardAPI->getUnreadSupportCount();
            } catch (\Exception $e) {
                $unreadCount = 0;
            }
            $supportTitle = __('💬 Support', 'ai-seo-client');
            if ($unreadCount > 0) {
                $supportTitle .= ' <span class="awaiting-mod count-' . $unreadCount . '"><span class="pending-count">' . $unreadCount . '</span></span>';
            }
            add_submenu_page(
                'fyndable-dashboard',
                __('Support', 'ai-seo-client'),
                $supportTitle,
                'manage_options',
                'ai-seo-support',
                [$this, 'renderSupportPage']
            );

            // 9b. SEO Data Dashboard (SE Ranking / Ahrefs) - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('SEO Data Dashboard', 'ai-seo-client'),
                __('📈 SEO Data', 'ai-seo-client'),
                'manage_options',
                'ai-seo-data-dashboard',
                [$this, 'renderSEODataDashboardPage']
            );

            // 9c. Brand & AI Search Visibility - all tiers
            add_submenu_page(
                'fyndable-dashboard',
                __('AI Search Visibility', 'ai-seo-client'),
                __('👁️ AI Search Visibility', 'ai-seo-client'),
                'manage_options',
                'ai-seo-llm-tracker',
                [$this, 'renderBrandVisibilityPage']
            );

            // Professional+ features: Topic Clusters, Site Audit, Rank Tracker
            $professionalTiers = ['professional', 'business', 'agency', 'trial', 'dev'];
            if (in_array($tier, $professionalTiers)) {
                // 10. Topic Clusters
                add_submenu_page(
                    'fyndable-dashboard',
                    __('Topic Clusters', 'ai-seo-client'),
                    __('🎯 Topic Clusters', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-topic-clusters',
                    [$this, 'renderTopicClusterPage']
                );

                // 10b. Automation
                add_submenu_page(
                    'fyndable-dashboard',
                    __('Automation', 'ai-seo-client'),
                    __('Automation', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-automation',
                    [$this, 'renderAutomationPage']
                );

                // 11. Site Audit
                add_submenu_page(
                    'fyndable-dashboard',
                    __('Site Audit', 'ai-seo-client'),
                    __('🔍 Site Audit', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-site-audit',
                    [$this, 'renderSiteAuditPage']
                );

                // 12. Rank Tracker
                add_submenu_page(
                    'fyndable-dashboard',
                    __('Rank Tracker', 'ai-seo-client'),
                    __('📈 Rank Tracker', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-rank-tracker',
                    [$this, 'renderRankTrackerPage']
                );

                // 13. Search Console (GSC) - Professional+
                add_submenu_page(
                    'fyndable-dashboard',
                    __('Search Console', 'ai-seo-client'),
                    __('📊 Search Console', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-gsc',
                    [$this, 'renderGscDashboardPage']
                );

                // 13b. Google Data Dashboard (GSC + GA4 + Ads) - Professional+
                add_submenu_page(
                    'fyndable-dashboard',
                    __('Google Data', 'ai-seo-client'),
                    __('📈 Google Data', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-google-data',
                    [$this, 'renderGoogleDataPage']
                );

                // 14. A/B Testing - Professional+
                add_submenu_page(
                    'fyndable-dashboard',
                    __('A/B Testing', 'ai-seo-client'),
                    __('🧪 A/B Testing', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-ab-testing',
                    [$this, 'renderABTestingPage']
                );

                // 15. AI Optimization Tracker - Professional+
                add_submenu_page(
                    'fyndable-dashboard',
                    __('AI Optimization', 'ai-seo-client'),
                    __('🤖 AI Optimization', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-ai-optimization',
                    [$this, 'renderAiOptimizationPage']
                );

                // 14b. Prompt Templates - Business+
                if ($this->promptTemplateLibrary) {
                    add_submenu_page(
                        'fyndable-dashboard',
                        __('Prompt Templates', 'ai-seo-client'),
                        __('📝 Prompt Templates', 'ai-seo-client'),
                        'manage_options',
                        'ai-seo-prompt-templates',
                        [$this, 'renderPromptTemplatesPage']
                    );
                }
            }
        }

        // Settings (always visible)
        add_submenu_page(
            'fyndable-dashboard',
            __('Settings', 'ai-seo-client'),
            __('⚙️ Settings', 'ai-seo-client'),
            'manage_options',
            'ai-seo-settings',
            [$this, 'renderSettingsPage']
        );

        // Import (always visible — useful even before license activation)
        add_submenu_page(
            'fyndable-dashboard',
            __('Import SEO Data', 'ai-seo-client'),
            __('📥 Import', 'ai-seo-client'),
            'manage_options',
            'ai-seo-import',
            [$this, 'renderImportPage']
        );

        // Remove all submenu items from WP admin menu (pages stay accessible via URL for iframe)
        add_action('admin_head', [$this, 'hideSubmenuItems']);
    }

    /**
     * Hide all dashboard submenu items from the WordPress admin menu via CSS.
     * Pages remain registered and accessible via direct URL (needed for iframe).
     */
    public function hideSubmenuItems(): void
    {
        echo '<style>
            #toplevel_page_fyndable-dashboard .wp-submenu,
            #toplevel_page_fyndable-dashboard .wp-submenu-wrap,
            #toplevel_page_fyndable-dashboard.wp-has-submenu .wp-submenu {
                display: none !important;
            }
            #toplevel_page_fyndable-dashboard .wp-menu-arrow {
                display: none !important;
            }
            #toplevel_page_fyndable-dashboard:hover .wp-submenu,
            #toplevel_page_fyndable-dashboard:hover .wp-submenu-wrap,
            #toplevel_page_fyndable-dashboard.wp-has-submenu:hover .wp-submenu {
                display: none !important;
            }
        </style>';
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'ai-seo') === false && strpos($hook, 'fyndable') === false) {
            return;
        }

        // WordPress media uploader (used by photo portfolio settings)
        wp_enqueue_media();

        wp_enqueue_style(
            'ai-seo-client-admin',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/client-admin.css',
            [],
            SSEO_AI_CLIENT_VERSION . '.' . filemtime(SSEO_AI_CLIENT_PLUGIN_DIR . 'assets/client-admin.css')
        );

        wp_enqueue_script(
            'ai-seo-client-admin',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/client-admin.js',
            ['jquery', 'wp-api-fetch'],
            SSEO_AI_CLIENT_VERSION,
            true
        );
        
        // Get white-label settings
        $whiteLabel = get_option('sseo_ai_white_label', []);

        wp_localize_script('ai-seo-client-admin', 'aiSeoClient', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sseo_ai_client_nonce'),
            'isLicensed' => $this->licenseValidator->isLicenseValid(),
            'whiteLabel' => $whiteLabel,
        ]);
        
        // Always add padding fix for #wpcontent
        wp_add_inline_style('ai-seo-client-admin', '
            #wpcontent { padding-left: 0 !important; }
        ');

        // Global AI generation loader overlay
        wp_add_inline_style('ai-seo-client-admin', '
            #sseo-ai-loader-overlay {
                display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0,0,0,0.6); z-index: 100001; justify-content: center; align-items: center;
                flex-direction: column; backdrop-filter: blur(4px);
            }
            #sseo-ai-loader-overlay.active { display: flex !important; }
            #sseo-ai-loader-overlay .sseo-loader-spinner {
                width: 60px; height: 60px; border: 5px solid rgba(255,255,255,0.3);
                border-top-color: #fff; border-radius: 50%; animation: sseo-spin 1s linear infinite;
            }
            #sseo-ai-loader-overlay .sseo-loader-text {
                color: #fff; margin-top: 20px; font-size: 16px; font-weight: 500;
                text-align: center; max-width: 300px; line-height: 1.5;
            }
            @keyframes sseo-spin { to { transform: rotate(360deg); } }
        ');

        wp_add_inline_script('ai-seo-client-admin', '
            (function($) {
                if (document.getElementById("sseo-ai-loader-overlay")) return;
                var overlay = document.createElement("div");
                overlay.id = "sseo-ai-loader-overlay";
                overlay.innerHTML = "<div class=\'sseo-loader-spinner\'></div><div class=\'sseo-loader-text\'>" + \'' . esc_js(__('AI is generating... Please wait.', 'ai-seo-client')) . '\' + "</div>";
                document.body.appendChild(overlay);

                var sseoShowLoader = function() { $("#sseo-ai-loader-overlay").addClass("active"); };
                var sseoHideLoader = function() { $("#sseo-ai-loader-overlay").removeClass("active"); };
                window.sseoShowLoader = sseoShowLoader;
                window.sseoHideLoader = sseoHideLoader;

                $(document).ajaxSend(function(e, xhr, settings) {
                    if (settings.data && (settings.data.indexOf("sseo_ai_") !== -1 || settings.data.indexOf("action=ai_seo") !== -1)) {
                        sseoShowLoader();
                    }
                });
                $(document).ajaxComplete(function() {
                    sseoHideLoader();
                });

                // Intercept wp.apiFetch for all AI calls
                if (typeof wp !== "undefined" && wp.apiFetch) {
                    var originalFetch = wp.apiFetch;
                    var sseoWrappedFetch = function(options) {
                        if (options && options.path && (options.path.indexOf("sseo-ai/v1") !== -1 || options.path.indexOf("/sseo-ai/v1") !== -1)) {
                            sseoShowLoader();
                            var result = originalFetch(options);
                            if (result && typeof result.then === "function") {
                                result.then(function() { sseoHideLoader(); }, function() { sseoHideLoader(); });
                            } else {
                                sseoHideLoader();
                            }
                            return result;
                        }
                        return originalFetch(options);
                    };
                    // Preserve all original properties (middleware, nonce, etc.)
                    for (var key in originalFetch) {
                        if (originalFetch.hasOwnProperty(key)) {
                            sseoWrappedFetch[key] = originalFetch[key];
                        }
                    }
                    wp.apiFetch = sseoWrappedFetch;
                }
            })(jQuery);
        ', 'after');

        // Apply white-label CSS variables only when a custom brand is configured
        if (!empty($whiteLabel['company_name']) && (!empty($whiteLabel['primary_color']) || !empty($whiteLabel['secondary_color']))) {
            $primaryColor = sanitize_hex_color($whiteLabel['primary_color'] ?: '#379fd3') ?: '#379fd3';
            $secondaryColor = sanitize_hex_color($whiteLabel['secondary_color'] ?: '#8f39ac') ?: '#8f39ac';
            $usePrimaryOnly = !empty($whiteLabel['use_primary_only']);
            $bgGradient = $usePrimaryOnly
                ? $primaryColor
                : "linear-gradient(135deg, {$primaryColor} 0%, {$secondaryColor} 100%)";

            // Convert hex to rgb for rgba() usage
            $rgb = sscanf($primaryColor, '#%02x%02x%02x');
            $rgbStr = implode(',', $rgb);

            wp_add_inline_style('ai-seo-client-admin', "
                :root {
                    --sseo-primary-color: {$primaryColor};
                    --sseo-secondary-color: {$secondaryColor};
                    --sseo-sa-primary: {$primaryColor};
                    --sseo-sa-secondary: {$secondaryColor};
                }
                /* Override all hardcoded Fyndable gradients on admin pages */
                .sseo-ai-header {
                    background: {$bgGradient} !important;
                    border-color: {$primaryColor} !important;
                }
                .sseo-ai-content {
                    background: {$bgGradient} !important;
                }
                .sseo-ai-header h1::before {
                    color: {$primaryColor};
                }
                /* Primary buttons */
                .button-primary.sseo-btn, .sseo-ai-btn-primary,
                .sseo-ai-connection-card .button-primary,
                .sseo-onboarding-actions .button-primary {
                    background-color: {$primaryColor} !important;
                    border-color: {$primaryColor} !important;
                }
                .button-primary.sseo-btn:hover, .sseo-ai-btn-primary:hover,
                .sseo-ai-connection-card .button-primary:hover,
                .sseo-onboarding-actions .button-primary:hover {
                    background-color: {$secondaryColor} !important;
                    border-color: {$secondaryColor} !important;
                }
                /* Accent colors — focus borders, hover states, active tabs */
                .form-field input:focus, .form-field select:focus, .form-field textarea:focus,
                .sseo-form-field select:focus, .sseo-reply-form textarea:focus,
                .connection-form input:focus, .sseo-onboarding-field input:focus,
                .sseo-onboarding-field select:focus, .sseo-onboarding-field textarea:focus {
                    border-color: {$primaryColor} !important;
                    box-shadow: 0 0 0 3px rgba({$rgbStr}, 0.1) !important;
                }
                .ai-tool-card:hover {
                    border-color: {$primaryColor} !important;
                    box-shadow: 0 10px 20px rgba({$rgbStr}, 0.15) !important;
                }
                .sseo-ticket-item:hover {
                    border-color: {$primaryColor} !important;
                    box-shadow: 0 4px 12px rgba({$rgbStr}, 0.1) !important;
                }
                .sseo-ticket-item a:hover, .sseo-card-controls button:hover,
                .sseo-reorder-toggle:hover {
                    color: {$primaryColor} !important;
                    border-color: {$primaryColor} !important;
                }
                .sseo-file-drop:hover {
                    border-color: {$primaryColor} !important;
                }
                /* Active states */
                .google-tab.active, .bv-period-tab.active {
                    color: {$primaryColor} !important;
                    border-bottom-color: {$primaryColor} !important;
                    background: {$primaryColor} !important;
                }
                .bv-pagination a:hover, .bv-pagination span.current,
                .bv-scan-btn, .bv-competitor-bar-fill {
                    background: {$primaryColor} !important;
                }
                /* Spinner */
                .sseo-ai-connection-loader .spinner,
                .sseo-onboarding-loader .spinner {
                    border-top-color: {$primaryColor} !important;
                }
                /* Highlight text gradient */
                .sseo-ai-connection-card .highlight {
                    background: {$bgGradient} !important;
                    -webkit-background-clip: text !important;
                    -webkit-text-fill-color: transparent !important;
                }
                /* Progress circle active state */
                .sseo-onboarding-progress-step.active .sseo-onboarding-progress-circle {
                    color: {$primaryColor} !important;
                }
                /* Checkbox/item hover */
                .sseo-onboarding-checkbox-item:hover {
                    border-color: {$primaryColor} !important;
                }
            ");
        }
    }

    /**
     * Render license activation page
     */
    public function renderLicensePage(): void
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $licenseStatus = get_option('sseo_ai_client_license_status', 'inactive');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = $this->settings->getDashboardUrl();

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = $this->getBrandName();
        $brandName = $companyName . ' ' . __('License Activation', 'ai-seo-client');
        ?>
        <div class="wrap ai-seo-client">
            <h1><?php echo esc_html($brandName); ?></h1>

            <?php
            // Show activation success message
            if (isset($_GET['activated']) && $_GET['activated'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php esc_html_e('License activated successfully!', 'ai-seo-client'); ?></strong></p>
                </div>
            <?php endif; ?>

            <?php
            // Show deactivation message
            if (isset($_GET['deactivated']) && $_GET['deactivated'] == '1'): ?>
                <div class="notice notice-info is-dismissible">
                    <p><?php esc_html_e('License deactivated.', 'ai-seo-client'); ?></p>
                </div>
            <?php endif; ?>

            <?php
            // Show error messages
            if (isset($_GET['error'])): 
                $error = sanitize_text_field($_GET['error']);
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong><?php esc_html_e('Activation Error:', 'ai-seo-client'); ?></strong></p>
                    <p><?php echo esc_html(urldecode($error)); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($licenseStatus === 'active'): ?>
                <div class="notice notice-success">
                    <p><strong><?php esc_html_e('License is active!', 'ai-seo-client'); ?></strong></p>
                    <p><?php 
                        printf(
                            esc_html__('Your license key: %s', 'ai-seo-client'),
                            esc_html(substr($licenseKey, 0, 15) . '...' . substr($licenseKey, -4))
                        ); 
                    ?></p>
                    <?php if ($tenantKey): ?>
                        <p><?php 
                            printf(
                                esc_html__('Tenant Key: %s', 'ai-seo-client'),
                                esc_html(substr($tenantKey, 0, 20) . '...')
                            ); 
                        ?></p>
                    <?php endif; ?>
                </div>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('deactivate_license'); ?>
                    <input type="hidden" name="action" value="ai_seo_deactivate_license">
                    <p><?php submit_button(__('Deactivate License', 'ai-seo-client'), 'secondary', 'submit', false); ?></p>
                </form>

            <?php else: ?>
                <?php if ($licenseStatus === 'expired'): ?>
                    <div class="notice notice-error">
                        <p><?php esc_html_e('Your license has expired. Please renew to continue using AI SEO features.', 'ai-seo-client'); ?></p>
                    </div>
                <?php elseif ($licenseStatus === 'revoked'): ?>
                    <div class="notice notice-error">
                        <p><?php esc_html_e('Your license has been revoked. Please contact support.', 'ai-seo-client'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <h2><?php esc_html_e('Activate Your License', 'ai-seo-client'); ?></h2>
                    <p><?php esc_html_e('Enter your license key to activate AI SEO features on this site.', 'ai-seo-client'); ?></p>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('activate_license'); ?>
                        <input type="hidden" name="action" value="ai_seo_activate_license">
                        <input type="hidden" name="dashboard_url" id="dashboard_url" value="<?php echo esc_attr($dashboardUrl); ?>">

                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="license_key"><?php esc_html_e('License Key', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="text" name="license_key" id="license_key"
                                           value="<?php echo esc_attr($licenseKey); ?>"
                                           class="regular-text"
                                           placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" required>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(__('Activate License', 'ai-seo-client'), 'primary', 'submit', false); ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the full-page dashboard shell
     */
    public function renderDashboardShell(): void
    {
        if ($this->dashboardShell) {
            $this->dashboardShell->render();
        }
    }

    /**
     * Render main page - handles both licensed and unlicensed states
     */
    public function renderMainPage(): void
    {
        if ($this->licenseValidator->isLicenseValid()) {
            $this->renderDashboardPage();
        } else {
            $this->renderLicensePage();
        }
    }

    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->seoDashboard) {
            $this->seoDashboard->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Content Calendar page - delegates to ContentCalendar class
     */
    public function renderContentCalendarPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->contentCalendar) {
            $this->contentCalendar->renderCalendar();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Topic Cluster page - delegates to TopicCluster class
     */
    public function renderTopicClusterPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->topicCluster) {
            $this->topicCluster->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Automation page - delegates to AutomationOrchestrator class
     */
    public function renderAutomationPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->automationOrchestrator) {
            $this->automationOrchestrator->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render AI Tools page
     */
    public function renderAIToolsPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        ?>
        <style>
            /* Critical layout CSS */
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
            .ai-tool-card { display: block; background: #fff; border: 2px solid #e5e7eb; border-radius: 6px; padding: 24px; transition: all .2s ease; }
            .ai-tool-card:hover { border-color: #379fd3; transform: translateY(-4px); box-shadow: 0 10px 20px rgba(55, 159, 211, 0.15); }
            .ai-tool-card h3 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 12px 0; }
            .ai-tool-card p { font-size: 14px; color: #4b5563; margin: 0; line-height: 1.6; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('AI Tools', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Available AI Tools', 'ai-seo-client'); ?></h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                        
                        <div class="ai-tool-card">
                            <h3>🤖 <?php esc_html_e('Content Writer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('AI-powered content generation for blog posts and articles', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>✍️ <?php esc_html_e('Bulk Optimizer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Bulk generate meta titles and descriptions', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>🎨 <?php esc_html_e('Image Generator', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Generate featured images and graphics', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>🖼️ <?php esc_html_e('Image Alt Generator', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Available in post editor sidebar', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>❓ <?php esc_html_e('FAQ Generator', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Generate FAQ schema from content', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>🎥 <?php esc_html_e('Video SEO', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Video transcript generation and optimization', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>🔄 <?php esc_html_e('Content Repurposer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Repurpose content for different formats', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <div class="ai-tool-card">
                            <h3>📊 <?php esc_html_e('Content Optimizer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('AI-powered content optimization suggestions', 'ai-seo-client'); ?></p>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Ideas page - delegates to Ideas class
     */
    public function renderIdeasPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->ideas) {
            $this->ideas->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Created Posts page - delegates to CreatedPosts class
     */
    public function renderCreatedPostsPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->createdPosts) {
            $this->createdPosts->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Keywords page - delegates to Keywords class
     */
    public function renderKeywordsPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->keywords) {
            $this->keywords->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Bulk Optimizer page - delegates to BulkActions class
     */
    public function renderBulkOptimizerPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->bulkActions) {
            $this->bulkActions->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Site Audit page - delegates to TechnicalSEOAuditor class
     */
    public function renderSiteAuditPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->technicalSEOAuditor) {
            $this->technicalSEOAuditor->renderDashboard();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Rank Tracker page - delegates to RankTracker class
     */
    public function renderRankTrackerPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->rankTracker) {
            $this->rankTracker->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Google Search Console Dashboard page
     */
    public function renderGscDashboardPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->gscDashboard) {
            $this->gscDashboard->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Google Data Dashboard page (GSC + GA4 + Google Ads)
     */
    public function renderGoogleDataPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->googleDataDashboard) {
            $this->googleDataDashboard->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render A/B Testing page - delegates to ABTesting class
     */
    public function renderABTestingPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->abTesting) {
            $this->abTesting->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render AI Optimization Tracker page - delegates to AiOptimizationTracker class
     */
    public function renderAiOptimizationPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->aiOptimizationTracker) {
            $this->aiOptimizationTracker->renderDashboard();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Prompt Templates page - delegates to PromptTemplateLibrary class
     */
    public function renderPromptTemplatesPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->promptTemplateLibrary) {
            $this->promptTemplateLibrary->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Link Manager page - delegates to SmartInternalLinking class
     */
    public function renderLinkManagerPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->smartInternalLinking) {
            $this->smartInternalLinking->renderDashboard();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Competitor Research page - delegates to CompetitorResearch class
     */
    public function renderCompetitorResearchPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->competitorResearch) {
            $this->competitorResearch->renderDashboard();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Redirect Manager page - delegates to RedirectionManager class
     */
    public function renderRedirectManagerPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->redirectManager) {
            $this->redirectManager->renderAdminPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Link Genius page - delegates to LinkAssistant class
     */
    public function renderLinkGeniusPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->linkAssistant) {
            $this->linkAssistant->renderDashboard();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Integrations page - delegates to ExternalIntegrations class
     */
    public function renderIntegrationsPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->externalIntegrations) {
            $this->externalIntegrations->renderSettings();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render SEO Data Dashboard page - SE Ranking & Ahrefs
     */
    public function renderSEODataDashboardPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }
        if ($this->seoDataDashboard) {
            $this->seoDataDashboard->renderPage();
        } else {
            $this->renderFeatureNotAvailable();
        }
    }

    /**
     * Render Sitemaps page - shows sitemap status and health
     */
    public function renderSitemapsPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }

        // Sitemap URLs for reference
        $sitemapUrl = $this->sitemapGenerator->getMainSitemapUrl();
        $sitemapIndexUrl = $this->sitemapGenerator->getSitemapIndexUrl();
        
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sitemap-status { display: flex; align-items: center; gap: 15px; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
            .sitemap-status.ok { background: #d1fae5; border-left: 4px solid #00a32a; }
            .sitemap-status.error { background: #fee2e2; border-left: 4px solid #d63638; }
            .sitemap-status.warning { background: #fff3cd; border-left: 4px solid #dba617; }
            .sitemap-url { font-family: monospace; background: #f3f4f6; padding: 10px 15px; border-radius: 6px; display: inline-block; margin: 5px 0; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('XML Sitemaps', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div style="max-width: 900px;">
                    
                    <!-- Main Sitemap Status -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Sitemap Status', 'ai-seo-client'); ?></h2>
                        <div id="sitemap-status-content">
                            <p><?php esc_html_e('Loading sitemap status...', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <p style="margin-top: 20px;">
                            <button type="button" id="run-sitemap-check" class="button button-primary">
                                <?php esc_html_e('Run Full Sitemap Health Check', 'ai-seo-client'); ?>
                            </button>
                            <span class="spinner" style="float: none; margin-left: 10px;"></span>
                        </p>
                        
                        <div id="sitemap-check-results" style="margin-top: 30px;"></div>
                        
                        <script>
                        jQuery(document).ready(function($) {
                            function loadSitemapStatus() {
                                var container = $('#sitemap-status-content');
                                container.html('<p><?php echo esc_js(__('Loading sitemap status...', 'ai-seo-client')); ?></p>');
                                
                                wp.apiFetch({
                                    path: '/sseo-ai/v1/sitemap/status',
                                    method: 'GET'
                                }).then(function(data) {
                                    var html = '';
                                    if (data.index_exists) {
                                        html += '<div class="sitemap-status ok">';
                                        html += '<span style="font-size: 24px;">✅</span>';
                                        html += '<div>';
                                        html += '<strong><?php echo esc_js(__('Sitemap Index Found', 'ai-seo-client')); ?></strong>';
                                        html += '<div class="sitemap-url"><a href="' + data.sitemap_index_url + '" target="_blank">' + data.sitemap_index_url + '</a></div>';
                                        html += '</div></div>';
                                    }
                                    if (data.sitemap_exists) {
                                        html += '<div class="sitemap-status ok">';
                                        html += '<span style="font-size: 24px;">✅</span>';
                                        html += '<div>';
                                        html += '<strong><?php echo esc_js(__('XML Sitemap Found', 'ai-seo-client')); ?></strong>';
                                        html += '<div class="sitemap-url"><a href="' + data.sitemap_url + '" target="_blank">' + data.sitemap_url + '</a></div>';
                                        html += '<p style="margin: 5px 0 0; font-size: 12px; color: #666;"><?php echo esc_js(__('Sitemap size:', 'ai-seo-client')); ?> ' + (data.sitemap_size || 0) + ' bytes</p>';
                                        html += '</div></div>';
                                    }
                                    if (!data.sitemap_exists && !data.index_exists) {
                                        html += '<div class="sitemap-status error">';
                                        html += '<span style="font-size: 24px;">❌</span>';
                                        html += '<div>';
                                        html += '<strong><?php echo esc_js(__('No Sitemap Found', 'ai-seo-client')); ?></strong>';
                                        html += '<p><?php echo esc_js(__('Neither sitemap.xml nor sitemap_index.xml could be found.', 'ai-seo-client')); ?></p>';
                                        html += '<p style="margin-top: 10px;"><a href="<?php echo esc_js(admin_url('admin.php?page=ai-seo-sitemap-settings')); ?>" class="button button-secondary"><?php echo esc_js(__('Open Sitemap Settings', 'ai-seo-client')); ?></a></p>';
                                        html += '</div></div>';
                                    }
                                    container.html(html);
                                }).catch(function(error) {
                                    container.html('<div class="sitemap-status error"><span style="font-size: 24px;">❌</span><div><strong><?php echo esc_js(__('Error loading sitemap status', 'ai-seo-client')); ?></strong><p>' + (error.message || '<?php echo esc_js(__('Unknown error', 'ai-seo-client')); ?>') + '</p></div></div>');
                                });
                            }
                            
                            loadSitemapStatus();
                            
                            $('#run-sitemap-check').on('click', function() {
                                var btn = $(this);
                                var spinner = btn.next('.spinner');
                                var results = $('#sitemap-check-results');
                                
                                btn.prop('disabled', true);
                                spinner.addClass('is-active');
                                results.html('<p><?php echo esc_js(__('Running sitemap health check...', 'ai-seo-client')); ?></p>');
                                
                                wp.apiFetch({
                                    path: '/sseo-ai/v1/technical/audit',
                                    method: 'POST'
                                }).then(function(response) {
                                    if (response.success && response.audit && response.audit.sitemap) {
                                        var sitemap = response.audit.sitemap;
                                        var html = '<div class="sseo-ai-dashboard-card" style="background: white; padding: 30px; border-radius: 8px;">';
                                        html += '<h3><?php echo esc_js(__('Sitemap Health Check Results', 'ai-seo-client')); ?></h3>';
                                        
                                        // Sitemap URL
                                        html += '<p><strong><?php echo esc_js(__('Sitemap URL:', 'ai-seo-client')); ?></strong> <a href="' + sitemap.url + '" target="_blank">' + sitemap.url + '</a></p>';
                                        
                                        // Stats
                                        html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0;">';
                                        html += '<div style="background: #f0f9ff; padding: 15px; border-radius: 6px; text-align: center;">';
                                        html += '<div style="font-size: 32px; font-weight: bold; color: #379fd3;">' + (sitemap.total_urls || 0) + '</div>';
                                        html += '<div style="color: #6b7280;"><?php echo esc_js(__('Total URLs', 'ai-seo-client')); ?></div>';
                                        html += '</div>';
                                        html += '<div style="background: #d1fae5; padding: 15px; border-radius: 6px; text-align: center;">';
                                        html += '<div style="font-size: 32px; font-weight: bold; color: #00a32a;">' + (sitemap.valid_urls || 0) + '</div>';
                                        html += '<div style="color: #6b7280;"><?php echo esc_js(__('Valid URLs', 'ai-seo-client')); ?></div>';
                                        html += '</div>';
                                        html += '<div style="background: #fee2e2; padding: 15px; border-radius: 6px; text-align: center;">';
                                        html += '<div style="font-size: 32px; font-weight: bold; color: #d63638;">' + (sitemap.invalid_urls || 0) + '</div>';
                                        html += '<div style="color: #6b7280;"><?php echo esc_js(__('Invalid URLs', 'ai-seo-client')); ?></div>';
                                        html += '</div>';
                                        html += '</div>';
                                        
                                        // Issues
                                        if (sitemap.issues && sitemap.issues.length > 0) {
                                            html += '<h4 style="margin-top: 20px;"><?php echo esc_js(__('Issues Found', 'ai-seo-client')); ?></h4>';
                                            html += '<ul style="list-style: none; padding: 0;">';
                                            sitemap.issues.forEach(function(issue) {
                                                html += '<li style="padding: 10px; margin: 5px 0; background: #fff3cd; border-left: 3px solid #dba617; border-radius: 4px;">';
                                                html += '<strong>' + issue.type + ':</strong> ' + issue.description;
                                                html += '</li>';
                                            });
                                            html += '</ul>';
                                        } else {
                                            html += '<div style="background: #d1fae5; padding: 15px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #00a32a;">';
                                            html += '<strong>✓</strong> <?php echo esc_js(__('No issues found! Your sitemap is healthy.', 'ai-seo-client')); ?>';
                                            html += '</div>';
                                        }
                                        
                                        html += '</div>';
                                        results.html(html);
                                    } else {
                                        results.html('<div style="background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #d63638;"><?php echo esc_js(__('Failed to run sitemap check. Please try again.', 'ai-seo-client')); ?></div>');
                                    }
                                    
                                    btn.prop('disabled', false);
                                    spinner.removeClass('is-active');
                                }).catch(function(error) {
                                    results.html('<div style="background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #d63638;"><strong><?php echo esc_js(__('Error:', 'ai-seo-client')); ?></strong> ' + (error.message || '<?php echo esc_js(__('Unknown error', 'ai-seo-client')); ?>') + '</div>';
                                    btn.prop('disabled', false);
                                    spinner.removeClass('is-active');
                                });
                            });
                        });
                        </script>
                    </div>
                    
                    <!-- Extended Sitemaps Info -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Extended Sitemaps', 'ai-seo-client'); ?></h2>
                        <p><?php esc_html_e('The plugin automatically generates the following sitemap types:', 'ai-seo-client'); ?></p>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><strong><?php esc_html_e('Sitemap Index:', 'ai-seo-client'); ?></strong> <code>sitemap_index.xml</code> <?php esc_html_e('(main entry point)', 'ai-seo-client'); ?></li>
                            <li><strong><?php esc_html_e('Main Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap.xml</code></li>
                            <li><strong><?php esc_html_e('RSS Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-rss.xml</code></li>
                            <li><strong><?php esc_html_e('Video Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-videos.xml</code></li>
                            <li><strong><?php esc_html_e('News Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-news.xml</code></li>
                            <li><strong><?php esc_html_e('Image Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-images.xml</code></li>
                            <li><strong><?php esc_html_e('Author Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-authors.xml</code></li>
                        </ul>
                    </div>

                    <!-- Sitemap Settings -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Sitemap Settings', 'ai-seo-client'); ?></h2>
                        <p><?php esc_html_e('Choose which post types and taxonomies to include in your sitemap.', 'ai-seo-client'); ?></p>
                        <?php $this->sitemapGenerator->renderSettings(); ?>
                    </div>
                    
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Helper: Render a feature page with cards
     */
    private function renderFeaturePage(string $title, string $heading, string $description, array $cards): void
    {
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
            .ai-tool-card { display: block; background: #fff; border: 2px solid #e5e7eb; border-radius: 6px; padding: 24px; transition: all .2s ease; }
            .ai-tool-card:hover { border-color: #379fd3; transform: translateY(-4px); box-shadow: 0 10px 20px rgba(55, 159, 211, 0.15); }
            .ai-tool-card h3 { font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 12px 0; }
            .ai-tool-card p { font-size: 14px; color: #4b5563; margin: 0; line-height: 1.6; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php echo esc_html($title); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php echo esc_html($heading); ?></h2>
                    <p style="margin-bottom: 30px; color: #646970;"><?php echo esc_html($description); ?></p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <?php foreach ($cards as $card): ?>
                        <div class="ai-tool-card">
                            <h3><?php echo esc_html($card['icon'] . ' ' . $card['title']); ?></h3>
                            <p><?php echo esc_html($card['desc']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get current rate limit status
     */
    private function getRateLimitStatus(): array
    {
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        if (empty($tenantKey)) {
            return ['calls' => 0, 'limit' => 0, 'remaining' => 0, 'reset_in' => 0, 'reset_in_minutes' => 0];
        }
        
        $key = 'ai_seo_llm_calls_' . $tenantKey;
        $calls = get_transient($key) ?: 0;
        $limit = (int)get_option('sseo_ai_client_rate_limit', 60);
        
        // Get transient expiration time
        $expires = get_option('_transient_timeout_' . $key);
        $resetIn = $expires ? max(0, $expires - time()) : HOUR_IN_SECONDS;
        
        return [
            'calls' => (int)$calls,
            'limit' => $limit,
            'remaining' => max(0, $limit - $calls),
            'reset_in' => $resetIn,
            'reset_in_minutes' => ceil($resetIn / 60),
        ];
    }

    /**
     * Render import page
     */
    public function renderImportPage(): void
    {
        $this->seoImporter->renderPage();
    }

    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        // Get current settings
        $promptSettings = get_option('sseo_ai_prompt_settings', '');
        $locations = get_option('sseo_ai_locations', '');
        $targetedAudience = get_option('sseo_ai_targeted_audience', '');
        $brandName = get_option('sseo_ai_brand_name', '');
        $brandVoice = get_option('sseo_ai_brand_voice', '');
        $sslVerify = $this->settings->sslVerify();
        $defaultWordCount = (int) get_option('sseo_ai_client_default_word_count', 500);
        $demoMode = $this->demoMode instanceof DemoMode ? $this->demoMode->isEnabled() : (get_option('sseo_ai_demo_mode', '0') === '1');
        $googlePlacesKey = get_option('sseo_ai_client_google_places_key', '');

        // Get rate limit status
        $rateLimitStatus = $this->getRateLimitStatus();
        
        // Check for success message
        $success = isset($_GET['settings-updated']) && $_GET['settings-updated'] === '1';
        
        ?>
        <style>
            /* Critical layout CSS */
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-settings-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); max-width: 1200px; margin: 0 auto; }
            .sseo-ai-notice { padding: 16px 20px; border-radius: 6px; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
            .sseo-ai-notice-success { background: #d1fae5; color: #10b981; border-left: 4px solid #10b981; }
            .settings-section { margin-bottom: 40px; padding-bottom: 40px; border-bottom: 2px solid #f3f4f6; }
            .settings-section h2 { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 8px 0; }
            .form-field { margin-bottom: 24px; }
            .form-field label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
            .form-field input, .form-field select, .form-field textarea { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px; }
            .form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color: #379fd3; outline: none; box-shadow: 0 0 0 3px rgba(55, 159, 211, 0.1); }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Settings', 'ai-seo-client'); ?></h1>
            </div>
            
            <div class="sseo-ai-content">
                <div class="sseo-ai-settings-card">
                    
                    <?php if ($success): ?>
                        <div class="sseo-ai-notice sseo-ai-notice-success">
                            <strong>✓</strong> <?php esc_html_e('Settings saved successfully!', 'ai-seo-client'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="settings-section" style="background:#f0f6ff;border-radius:8px;padding:20px;">
                        <h2><?php esc_html_e('Setup Wizard', 'ai-seo-client'); ?></h2>
                        <p class="description">
                            <?php esc_html_e('Re-run the Fyndable setup wizard to review or change your onboarding settings.', 'ai-seo-client'); ?>
                        </p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:15px;">
                            <input type="hidden" name="action" value="sseo_ai_onboarding_restart">
                            <?php wp_nonce_field('sseo_ai_onboarding_restart'); ?>
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Start Wizard', 'ai-seo-client'); ?>
                            </button>
                        </form>
                    </div>
                    
                    <?php if ($rateLimitStatus['limit'] > 0): ?>
                    <div class="settings-section">
                        <h2><?php esc_html_e('API Usage Status', 'ai-seo-client'); ?></h2>
                        <p class="description"><?php esc_html_e('Current AI API call usage and limits', 'ai-seo-client'); ?></p>
                        
                        <div style="background:#f9f9f9;padding:20px;border-radius:8px;margin:20px 0;">
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;text-align:center;">
                                <div>
                                    <div style="font-size:28px;font-weight:bold;color:#2271b1;"><?php echo esc_html($rateLimitStatus['calls']); ?></div>
                                    <div style="color:#666;font-size:13px;"><?php esc_html_e('Calls Made', 'ai-seo-client'); ?></div>
                                </div>
                                <div>
                                    <div style="font-size:28px;font-weight:bold;color:#00a32a;"><?php echo esc_html($rateLimitStatus['remaining']); ?></div>
                                    <div style="color:#666;font-size:13px;"><?php esc_html_e('Remaining', 'ai-seo-client'); ?></div>
                                </div>
                                <div>
                                    <div style="font-size:28px;font-weight:bold;color:#d63638;"><?php echo esc_html($rateLimitStatus['limit']); ?></div>
                                    <div style="color:#666;font-size:13px;"><?php esc_html_e('Hourly Limit', 'ai-seo-client'); ?></div>
                                </div>
                            </div>
                            <p style="text-align:center;margin:15px 0 0;color:#666;">
                                <?php printf(esc_html__('Limit resets in %d minutes', 'ai-seo-client'), $rateLimitStatus['reset_in_minutes']); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sseo-ai-settings-form">
                        <input type="hidden" name="action" value="ai_seo_save_settings">
                        <?php wp_nonce_field('save_settings'); ?>
                        
                        <div class="settings-section">
                            <h2><?php esc_html_e('Brand Information', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Configure your brand details for AI-generated content', 'ai-seo-client'); ?></p>
                            
                            <div class="form-field">
                                <label for="brand_name"><?php esc_html_e('Brand Name', 'ai-seo-client'); ?></label>
                                <input type="text" name="brand_name" id="brand_name" 
                                       value="<?php echo esc_attr($brandName); ?>" 
                                       placeholder="e.g., NextBuzz">
                                <p class="field-description"><?php esc_html_e('Your company or brand name', 'ai-seo-client'); ?></p>
                            </div>
                            
                            <div class="form-field">
                                <label for="brand_voice"><?php esc_html_e('Brand Voice & Tone', 'ai-seo-client'); ?></label>
                                <select name="brand_voice" id="brand_voice">
                                    <option value=""><?php esc_html_e('Select tone...', 'ai-seo-client'); ?></option>
                                    <option value="professional" <?php selected($brandVoice, 'professional'); ?>><?php esc_html_e('Professional', 'ai-seo-client'); ?></option>
                                    <option value="friendly" <?php selected($brandVoice, 'friendly'); ?>><?php esc_html_e('Friendly', 'ai-seo-client'); ?></option>
                                    <option value="casual" <?php selected($brandVoice, 'casual'); ?>><?php esc_html_e('Casual', 'ai-seo-client'); ?></option>
                                    <option value="authoritative" <?php selected($brandVoice, 'authoritative'); ?>><?php esc_html_e('Authoritative', 'ai-seo-client'); ?></option>
                                    <option value="conversational" <?php selected($brandVoice, 'conversational'); ?>><?php esc_html_e('Conversational', 'ai-seo-client'); ?></option>
                                    <option value="technical" <?php selected($brandVoice, 'technical'); ?>><?php esc_html_e('Technical', 'ai-seo-client'); ?></option>
                                </select>
                                <p class="field-description"><?php esc_html_e('The tone of voice for AI-generated content', 'ai-seo-client'); ?></p>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><?php esc_html_e('Target Audience', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Define who your content is for', 'ai-seo-client'); ?></p>
                            
                            <div class="form-field">
                                <label for="targeted_audience"><?php esc_html_e('Targeted Audience', 'ai-seo-client'); ?></label>
                                <textarea name="targeted_audience" id="targeted_audience" rows="4" 
                                          placeholder="e.g., Small business owners, marketing professionals, entrepreneurs aged 25-45"><?php echo esc_textarea($targetedAudience); ?></textarea>
                                <p class="field-description"><?php esc_html_e('Describe your target audience demographics, interests, and characteristics', 'ai-seo-client'); ?></p>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><?php esc_html_e('Location Settings', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Configure geographic targeting for local SEO', 'ai-seo-client'); ?></p>

                            <div class="form-field">
                                <label for="google_places_key"><?php esc_html_e('Google Places API Key (optional)', 'ai-seo-client'); ?></label>
                                <input type="text" name="google_places_key" id="google_places_key"
                                       value="<?php echo esc_attr($googlePlacesKey); ?>" class="regular-text"
                                       placeholder="AIza...">
                                <p class="field-description"><?php esc_html_e('Vul een Google Places API key in voor autocomplete-suggesties bij het typen van locaties. Zonder key werkt een eenvoudige fallback-lijst.', 'ai-seo-client'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="locations"><?php esc_html_e('Target Locations', 'ai-seo-client'); ?></label>
                                <div id="locations-tag-container" style="display:flex;flex-wrap:wrap;gap:6px;padding:8px;border:1px solid #ddd;border-radius:4px;min-height:42px;background:#fff;cursor:text;">
                                    <!-- Tag chips will be inserted here by JS -->
                                </div>
                                <input type="text" id="locations-input" class="regular-text"
                                       placeholder="<?php esc_attr_e('Typ een plaatsnaam en selecteer uit de suggesties...', 'ai-seo-client'); ?>"
                                       style="margin-top:6px;width:100%;"
                                       autocomplete="off">
                                <!-- Hidden textarea for backward-compatible comma-separated storage -->
                                <textarea name="locations" id="locations" rows="3" style="display:none;"><?php echo esc_textarea($locations); ?></textarea>
                                <p class="field-description"><?php esc_html_e('Steden, regio\'s of landen. Suggesties verschijnen automatisch tijdens het typen.', 'ai-seo-client'); ?></p>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h2><?php esc_html_e('Local Business', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Used for LocalBusiness schema and Local SERP radius scans', 'ai-seo-client'); ?></p>

                            <?php
                            $localOptions = $this->settings->all();
                            ?>

                            <div class="form-field">
                                <label for="local_business_name"><?php esc_html_e('Business Name', 'ai-seo-client'); ?></label>
                                <input type="text" name="local_business_name" id="local_business_name" value="<?php echo esc_attr($localOptions['local_business_name'] ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label for="local_business_type"><?php esc_html_e('Business Type', 'ai-seo-client'); ?></label>
                                <select name="local_business_type" id="local_business_type">
                                    <option value="LocalBusiness" <?php selected($localOptions['local_business_type'] ?? 'LocalBusiness', 'LocalBusiness'); ?>><?php esc_html_e('Local Business', 'ai-seo-client'); ?></option>
                                    <option value="Restaurant" <?php selected($localOptions['local_business_type'] ?? '', 'Restaurant'); ?>><?php esc_html_e('Restaurant', 'ai-seo-client'); ?></option>
                                    <option value="Store" <?php selected($localOptions['local_business_type'] ?? '', 'Store'); ?>><?php esc_html_e('Store', 'ai-seo-client'); ?></option>
                                    <option value="Dentist" <?php selected($localOptions['local_business_type'] ?? '', 'Dentist'); ?>><?php esc_html_e('Dentist', 'ai-seo-client'); ?></option>
                                    <option value="Doctor" <?php selected($localOptions['local_business_type'] ?? '', 'Doctor'); ?>><?php esc_html_e('Doctor', 'ai-seo-client'); ?></option>
                                    <option value="Plumber" <?php selected($localOptions['local_business_type'] ?? '', 'Plumber'); ?>><?php esc_html_e('Plumber', 'ai-seo-client'); ?></option>
                                    <option value="Electrician" <?php selected($localOptions['local_business_type'] ?? '', 'Electrician'); ?>><?php esc_html_e('Electrician', 'ai-seo-client'); ?></option>
                                    <option value="Lawyer" <?php selected($localOptions['local_business_type'] ?? '', 'Lawyer'); ?>><?php esc_html_e('Lawyer', 'ai-seo-client'); ?></option>
                                    <option value="RealEstateAgent" <?php selected($localOptions['local_business_type'] ?? '', 'RealEstateAgent'); ?>><?php esc_html_e('Real Estate Agent', 'ai-seo-client'); ?></option>
                                    <option value="HairSalon" <?php selected($localOptions['local_business_type'] ?? '', 'HairSalon'); ?>><?php esc_html_e('Hair Salon', 'ai-seo-client'); ?></option>
                                    <option value="AutoRepair" <?php selected($localOptions['local_business_type'] ?? '', 'AutoRepair'); ?>><?php esc_html_e('Auto Repair', 'ai-seo-client'); ?></option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="local_description"><?php esc_html_e('Description', 'ai-seo-client'); ?></label>
                                <textarea name="local_description" id="local_description" rows="3"><?php echo esc_textarea($localOptions['local_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-field">
                                <label for="local_street"><?php esc_html_e('Address', 'ai-seo-client'); ?></label>
                                <input type="text" name="local_street" id="local_street" value="<?php echo esc_attr($localOptions['local_street'] ?? ''); ?>" placeholder="Street">
                                <p style="margin-top:8px;display:flex;gap:8px;">
                                    <input type="text" name="local_city" value="<?php echo esc_attr($localOptions['local_city'] ?? ''); ?>" placeholder="City" style="flex:2;">
                                    <input type="text" name="local_state" value="<?php echo esc_attr($localOptions['local_state'] ?? ''); ?>" placeholder="State/Province" style="flex:1;">
                                    <input type="text" name="local_postal" value="<?php echo esc_attr($localOptions['local_postal'] ?? ''); ?>" placeholder="Postal code" style="flex:1;">
                                    <input type="text" name="local_country" value="<?php echo esc_attr($localOptions['local_country'] ?? 'NL'); ?>" placeholder="Country" style="flex:1;">
                                </p>
                            </div>

                            <div class="form-field">
                                <label for="local_latitude"><?php esc_html_e('Coordinates (Lat, Lng)', 'ai-seo-client'); ?></label>
                                <p style="display:flex;gap:8px;">
                                    <input type="text" name="local_latitude" id="local_latitude" value="<?php echo esc_attr($localOptions['local_latitude'] ?? ''); ?>" placeholder="Latitude" style="flex:1;">
                                    <input type="text" name="local_longitude" value="<?php echo esc_attr($localOptions['local_longitude'] ?? ''); ?>" placeholder="Longitude" style="flex:1;">
                                </p>
                                <p class="field-description"><?php esc_html_e('Required for Local SERP radius scans', 'ai-seo-client'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="local_search_radius"><?php esc_html_e('Default Local SERP Radius (km)', 'ai-seo-client'); ?></label>
                                <select name="local_search_radius" id="local_search_radius">
                                    <?php foreach ([1, 2, 5, 10, 15, 25, 50, 100] as $km): ?>
                                        <option value="<?php echo esc_attr($km); ?>" <?php selected((int) ($localOptions['local_search_radius'] ?? 10), $km); ?>><?php echo esc_html($km); ?> km</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="local_search_grid"><?php esc_html_e('Default Local SERP Grid', 'ai-seo-client'); ?></label>
                                <select name="local_search_grid" id="local_search_grid">
                                    <option value="1" <?php selected((int) ($localOptions['local_search_grid'] ?? 1), 1); ?>><?php esc_html_e('Single center (Professional)', 'ai-seo-client'); ?></option>
                                    <option value="3" <?php selected((int) ($localOptions['local_search_grid'] ?? 1), 3); ?>><?php esc_html_e('3x3 grid (Business)', 'ai-seo-client'); ?></option>
                                    <option value="5" <?php selected((int) ($localOptions['local_search_grid'] ?? 1), 5); ?>><?php esc_html_e('5x5 grid (Agency)', 'ai-seo-client'); ?></option>
                                    <option value="7" <?php selected((int) ($localOptions['local_search_grid'] ?? 1), 7); ?>><?php esc_html_e('7x7 grid (Agency)', 'ai-seo-client'); ?></option>
                                </select>
                            </div>

                            <div class="form-field">
                                <label for="local_phone"><?php esc_html_e('Contact', 'ai-seo-client'); ?></label>
                                <p style="display:flex;gap:8px;">
                                    <input type="text" name="local_phone" value="<?php echo esc_attr($localOptions['local_phone'] ?? ''); ?>" placeholder="Phone" style="flex:1;">
                                    <input type="email" name="local_email" value="<?php echo esc_attr($localOptions['local_email'] ?? ''); ?>" placeholder="Email" style="flex:1;">
                                </p>
                            </div>

                            <div class="form-field">
                                <label for="local_url"><?php esc_html_e('Business URL', 'ai-seo-client'); ?></label>
                                <input type="url" name="local_url" id="local_url" value="<?php echo esc_attr($localOptions['local_url'] ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label for="local_image"><?php esc_html_e('Business Image', 'ai-seo-client'); ?></label>
                                <input type="url" name="local_image" id="local_image" value="<?php echo esc_attr($localOptions['local_image'] ?? ''); ?>">
                            </div>

                            <div class="form-field">
                                <label for="local_opening_hours"><?php esc_html_e('Opening Hours', 'ai-seo-client'); ?></label>
                                <textarea name="local_opening_hours" id="local_opening_hours" rows="3" placeholder="Mo-Fr 09:00-17:00&#10;Sa 10:00-14:00"><?php echo esc_textarea($localOptions['local_opening_hours'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><?php esc_html_e('AI Prompt Settings', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Custom instructions for AI content generation', 'ai-seo-client'); ?></p>

                            <div class="form-field">
                                <label for="default_word_count"><?php esc_html_e('Default Word Count', 'ai-seo-client'); ?></label>
                                <input type="number" name="default_word_count" id="default_word_count" min="100" max="5000" step="50"
                                       value="<?php echo esc_attr($defaultWordCount); ?>">
                                <p class="field-description"><?php esc_html_e('Default number of words for AI-generated content (used when no specific count is set)', 'ai-seo-client'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="prompt_settings"><?php esc_html_e('Custom Prompt Instructions', 'ai-seo-client'); ?></label>
                                <textarea name="prompt_settings" id="prompt_settings" rows="6"
                                          placeholder="e.g., Always include actionable tips, use bullet points for readability, focus on practical examples..."><?php echo esc_textarea($promptSettings); ?></textarea>
                                <p class="field-description"><?php esc_html_e('Additional instructions that will be included in all AI prompts', 'ai-seo-client'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="default_include_faq">
                                    <input type="checkbox" name="default_include_faq" id="default_include_faq" value="1" <?php checked($this->settings->get('default_include_faq', true), true); ?>>
                                    <?php esc_html_e('Generate FAQ section by default', 'ai-seo-client'); ?>
                                </label>
                                <p class="field-description"><?php esc_html_e('When enabled, the Content Writer will include an FAQ section in generated articles by default. This can be overridden per article in the Content Writer.', 'ai-seo-client'); ?></p>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h2><?php esc_html_e('Post Auto-Cleaner', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Automatically trash AI-generated posts that receive no traffic within a configurable period. Uses an internal view counter (bots and logged-in users excluded).', 'ai-seo-client'); ?></p>

                            <?php
                            $autocleanEnabled = get_option('sseo_ai_client_autoclean_enabled', '0') === '1';
                            $autocleanDays = (int) get_option('sseo_ai_client_autoclean_days', 60);
                            $autocleanMaxClicks = (int) get_option('sseo_ai_client_autoclean_max_clicks', 0);
                            $autocleanLastRun = get_option('sseo_ai_client_autoclean_last_run', '');
                            $autocleanLastCount = (int) get_option('sseo_ai_client_autoclean_last_count', 0);
                            ?>

                            <div class="form-field">
                                <label for="autoclean_enabled">
                                    <input type="checkbox" name="autoclean_enabled" id="autoclean_enabled" value="1" <?php checked($autocleanEnabled); ?>>
                                    <?php esc_html_e('Enable auto-cleanup', 'ai-seo-client'); ?>
                                </label>
                                <p class="field-description"><?php esc_html_e('When enabled, a daily check trashes AI-generated posts that meet the criteria below.', 'ai-seo-client'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="autoclean_days"><?php esc_html_e('Days threshold', 'ai-seo-client'); ?></label>
                                <input type="number" name="autoclean_days" id="autoclean_days" min="1" max="3650" step="1"
                                       value="<?php echo esc_attr($autocleanDays); ?>">
                                <p class="field-description"><?php esc_html_e('Posts older than this many days are eligible for cleanup. Default: 60.', 'ai-seo-client'); ?></p>
                            </div>

                            <div class="form-field">
                                <label for="autoclean_max_clicks"><?php esc_html_e('Max views before cleanup', 'ai-seo-client'); ?></label>
                                <input type="number" name="autoclean_max_clicks" id="autoclean_max_clicks" min="0" max="1000000" step="1"
                                       value="<?php echo esc_attr($autocleanMaxClicks); ?>">
                                <p class="field-description"><?php esc_html_e('Posts with this many views or fewer are eligible. Set to 0 to only clean posts with zero views. Default: 0.', 'ai-seo-client'); ?></p>
                            </div>

                            <?php if (!empty($autocleanLastRun)): ?>
                            <p class="field-description" style="color:#666;">
                                <?php printf(
                                    /* translators: 1: date/time, 2: number of posts */
                                    esc_html__('Last run: %1$s — %2$s post(s) trashed.', 'ai-seo-client'),
                                    esc_html($autocleanLastRun),
                                    esc_html(number_format($autocleanLastCount))
                                ); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="settings-section">
                            <h2><?php esc_html_e('Photo Portfolio / Huisstijl Referenties', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Upload referentie-afbeeldingen (logo\'s, personen, omgeving, producten) die als stijl-referentie worden gebruikt bij het genereren van afbeeldingen. De AI beschrijft deze referenties en past de huisstijl toe in gegenereerde afbeeldingen.', 'ai-seo-client'); ?></p>

                            <?php
                            $photoPortfolio = get_option('sseo_ai_client_photo_portfolio', []);
                            if (!is_array($photoPortfolio)) { $photoPortfolio = []; }
                            ?>

                            <div class="form-field">
                                <label><?php esc_html_e('Referentie-afbeeldingen', 'ai-seo-client'); ?></label>
                                <div id="photo-portfolio-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:10px;">
                                    <?php foreach ($photoPortfolio as $idx => $ref): ?>
                                        <div class="photo-portfolio-item" data-idx="<?php echo esc_attr($idx); ?>" style="position:relative;border:1px solid #ddd;border-radius:8px;padding:8px;text-align:center;">
                                            <img src="<?php echo esc_url($ref['url'] ?? ''); ?>" style="width:100%;height:80px;object-fit:cover;border-radius:4px;margin-bottom:5px;">
                                            <select class="photo-ref-type" data-idx="<?php echo esc_attr($idx); ?>" style="width:100%;font-size:11px;margin-bottom:3px;">
                                                <option value="logo" <?php selected($ref['type'] ?? '', 'logo'); ?>><?php esc_html_e('Logo', 'ai-seo-client'); ?></option>
                                                <option value="person" <?php selected($ref['type'] ?? '', 'person'); ?>><?php esc_html_e('Persoon', 'ai-seo-client'); ?></option>
                                                <option value="environment" <?php selected($ref['type'] ?? '', 'environment'); ?>><?php esc_html_e('Omgeving/Pand', 'ai-seo-client'); ?></option>
                                                <option value="product" <?php selected($ref['type'] ?? '', 'product'); ?>><?php esc_html_e('Product', 'ai-seo-client'); ?></option>
                                                <option value="style" <?php selected($ref['type'] ?? '', 'style'); ?>><?php esc_html_e('Huisstijl/sfeer', 'ai-seo-client'); ?></option>
                                            </select>
                                            <button type="button" class="button button-small photo-ref-remove" data-idx="<?php echo esc_attr($idx); ?>" style="color:#dc2626;border-color:#fecaca;width:100%;">&times; <?php esc_html_e('Verwijder', 'ai-seo-client'); ?></button>
                                            <input type="hidden" name="photo_portfolio[<?php echo esc_attr($idx); ?>][attachment_id]" value="<?php echo esc_attr($ref['attachment_id'] ?? 0); ?>">
                                            <input type="hidden" name="photo_portfolio[<?php echo esc_attr($idx); ?>][url]" value="<?php echo esc_attr($ref['url'] ?? ''); ?>">
                                            <input type="hidden" name="photo_portfolio[<?php echo esc_attr($idx); ?>][type]" class="photo-ref-type-hidden" value="<?php echo esc_attr($ref['type'] ?? 'logo'); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button" id="photo-portfolio-add"><?php esc_html_e('+ Referentie toevoegen', 'ai-seo-client'); ?></button>
                                <p class="field-description"><?php esc_html_e('De AI gebruikt deze afbeeldingen om logo\'s, kleuren, sfeer en personen te herkennen en toe te passen in gegenereerde afbeeldingen.', 'ai-seo-client'); ?></p>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h2><?php esc_html_e('Advanced Settings', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Security and connectivity options', 'ai-seo-client'); ?></p>

                            <div class="form-field">
                                <label for="ssl_verify">
                                    <input type="checkbox" name="ssl_verify" id="ssl_verify" value="1" <?php checked($sslVerify, true); ?>>
                                    <?php esc_html_e('Verify SSL certificates for API calls', 'ai-seo-client'); ?>
                                </label>
                                <p class="field-description"><?php esc_html_e('Disable only for development environments with self-signed certificates. Disabling on production is a security risk.', 'ai-seo-client'); ?></p>
                            </div>
                        </div>

                        <div class="settings-section" style="border:2px solid #f59e0b;background:#fffbeb;border-radius:8px;">
                            <h2>
                                <?php esc_html_e('Demo Mode', 'ai-seo-client'); ?>
                                <?php if ($demoMode): ?>
                                    <span style="display:inline-block;background:#f59e0b;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;letter-spacing:0.5px;margin-left:8px;"><?php esc_html_e('Active', 'ai-seo-client'); ?></span>
                                <?php endif; ?>
                            </h2>
                            <p class="description"><?php esc_html_e('When enabled, the plugin uses fictitious data and bypasses license validation for demo purposes.', 'ai-seo-client'); ?></p>

                            <div class="form-field">
                                <label for="demo_mode">
                                    <input type="checkbox" name="demo_mode" id="demo_mode" value="1" <?php checked($demoMode, true); ?>>
                                    <?php esc_html_e('Enable demo mode', 'ai-seo-client'); ?>
                                </label>
                                <p class="field-description" style="color:#92400e;">
                                    <?php esc_html_e('All data shown while demo mode is active is fictitious and clearly labelled.', 'ai-seo-client'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="settings-actions">
                            <button type="submit" class="button button-primary button-large">
                                <?php esc_html_e('Save Settings', 'ai-seo-client'); ?>
                            </button>
                        </div>

                        <script>
                        (function() {
                            // Photo Portfolio media uploader
                            var addBtn = document.getElementById('photo-portfolio-add');
                            var grid = document.getElementById('photo-portfolio-grid');
                            if (!addBtn || !grid) return;
                            var idxCounter = grid.children.length;

                            // Update hidden type field when select changes
                            document.addEventListener('change', function(e) {
                                if (e.target && e.target.classList && e.target.classList.contains('photo-ref-type')) {
                                    var idx = e.target.getAttribute('data-idx');
                                    var hidden = document.querySelector('.photo-ref-type-hidden[data-idx="' + idx + '"]');
                                    if (hidden) hidden.value = e.target.value;
                                }
                            });

                            // Remove reference
                            document.addEventListener('click', function(e) {
                                if (e.target && e.target.classList && e.target.classList.contains('photo-ref-remove')) {
                                    e.target.closest('.photo-portfolio-item').remove();
                                }
                            });

                            addBtn.addEventListener('click', function() {
                                var frame = wp.media({
                                    title: '<?php echo esc_js(__('Selecteer referentie-afbeelding', 'ai-seo-client')); ?>',
                                    multiple: false,
                                    library: { type: 'image' }
                                });
                                frame.on('select', function() {
                                    var attachment = frame.state().get('selection').first().toJSON();
                                    var idx = idxCounter++;
                                    var item = document.createElement('div');
                                    item.className = 'photo-portfolio-item';
                                    item.setAttribute('data-idx', idx);
                                    item.style.cssText = 'position:relative;border:1px solid #ddd;border-radius:8px;padding:8px;text-align:center;';
                                    item.innerHTML =
                                        '<img src="' + attachment.url + '" style="width:100%;height:80px;object-fit:cover;border-radius:4px;margin-bottom:5px;">' +
                                        '<select class="photo-ref-type" data-idx="' + idx + '" style="width:100%;font-size:11px;margin-bottom:3px;">' +
                                        '<option value="logo">Logo</option>' +
                                        '<option value="person">Persoon</option>' +
                                        '<option value="environment">Omgeving/Pand</option>' +
                                        '<option value="product">Product</option>' +
                                        '<option value="style">Huisstijl/sfeer</option>' +
                                        '</select>' +
                                        '<button type="button" class="button button-small photo-ref-remove" data-idx="' + idx + '" style="color:#dc2626;border-color:#fecaca;width:100%;">&times; Verwijder</button>' +
                                        '<input type="hidden" name="photo_portfolio[' + idx + '][attachment_id]" value="' + attachment.id + '">' +
                                        '<input type="hidden" name="photo_portfolio[' + idx + '][url]" value="' + attachment.url + '">' +
                                        '<input type="hidden" name="photo_portfolio[' + idx + '][type]" class="photo-ref-type-hidden" value="logo">';
                                    grid.appendChild(item);
                                });
                                frame.open();
                            });
                        })();
                        </script>

                        <script>
                        (function() {
                            var container = document.getElementById('locations-tag-container');
                            var input = document.getElementById('locations-input');
                            var hidden = document.getElementById('locations');
                            if (!container || !input || !hidden) return;

                            var apiKey = '<?php echo esc_js($googlePlacesKey); ?>';
                            var existingLocations = (hidden.value || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                            var autocomplete = null;

                            // Render existing tags
                            function renderTags() {
                                container.innerHTML = '';
                                existingLocations.forEach(function(loc, idx) {
                                    var chip = document.createElement('span');
                                    chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#e8f4fa;color:#379fd3;border-radius:20px;font-size:13px;cursor:default;';
                                    chip.textContent = loc;
                                    var removeBtn = document.createElement('span');
                                    removeBtn.textContent = '\u00d7';
                                    removeBtn.style.cssText = 'cursor:pointer;font-weight:bold;margin-left:2px;';
                                    removeBtn.onclick = function() {
                                        existingLocations.splice(idx, 1);
                                        renderTags();
                                    };
                                    chip.appendChild(removeBtn);
                                    container.appendChild(chip);
                                });
                                syncHidden();
                            }

                            function syncHidden() {
                                hidden.value = existingLocations.join(', ');
                            }

                            function addLocation(loc) {
                                loc = loc.trim();
                                if (!loc) return;
                                if (existingLocations.indexOf(loc) !== -1) return;
                                existingLocations.push(loc);
                                renderTags();
                            }

                            // Set up fallback datalist (common European cities)
                            function setupFallback() {
                                var datalist = document.createElement('datalist');
                                datalist.id = 'locations-datalist';
                                var commonCities = ['Amsterdam','Rotterdam','Den Haag','Utrecht','Eindhoven','Tilburg','Groningen','Almere','Breda','Nijmegen','Enschede','Apeldoorn','Haarlem','Arnhem','Amersfoort','Zwolle','Zaanstad','Leeuwarden','Leiden','Maastricht','Dordrecht','Ede','Alkmaar','Emmen','Delft','Heerlen','Zoetermeer','Lelystad','Alphen aan den Rijn','Bergen op Zoom','Brussel','Antwerpen','Gent','Charleroi','Luik','Brugge','Leuven','Berlin','Hamburg','München','Köln','Frankfurt','Stuttgart','Düsseldorf','Paris','Lyon','Marseille','Toulouse','Nice','Lille','Bordeaux','London','Manchester','Birmingham','Leeds','Glasgow','Liverpool','Madrid','Barcelona','Valencia','Sevilla','Zaragoza','Rome','Milan','Naples','Turin','Florence','Lisbon','Porto','Warsaw','Kraków','Wrocław','Poznań','Gdańsk'];
                                commonCities.forEach(function(city) {
                                    var opt = document.createElement('option');
                                    opt.value = city;
                                    datalist.appendChild(opt);
                                });
                                input.setAttribute('list', 'locations-datalist');
                                document.body.appendChild(datalist);
                            }

                            // Initialize Google Places Autocomplete
                            function initPlacesAutocomplete() {
                                if (!apiKey || typeof google === 'undefined' || !google.maps || !google.maps.places) {
                                    setupFallback();
                                    return;
                                }
                                try {
                                    autocomplete = new google.maps.places.Autocomplete(input, {
                                        types: ['(cities)']
                                    });
                                    autocomplete.addListener('place_changed', function() {
                                        var place = autocomplete.getPlace();
                                        if (place && place.name) {
                                            addLocation(place.name);
                                            input.value = '';
                                        }
                                    });
                                } catch (e) {
                                    setupFallback();
                                }
                            }

                            // Allow Enter key to add location manually
                            input.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter' || e.key === ',') {
                                    e.preventDefault();
                                    if (input.value.trim()) {
                                        addLocation(input.value);
                                        input.value = '';
                                    }
                                }
                            });

                            // Allow blur to add
                            input.addEventListener('blur', function() {
                                if (input.value.trim()) {
                                    addLocation(input.value);
                                    input.value = '';
                                }
                            });

                            // Click on container focuses input
                            container.addEventListener('click', function() {
                                input.focus();
                            });

                            renderTags();

                            // If no API key, use fallback immediately
                            if (!apiKey) {
                                setupFallback();
                            } else {
                                // Expose callback for Google Maps script
                                window.initLocationsAutocomplete = initPlacesAutocomplete;
                                // If Google Maps already loaded (e.g. cached), init now
                                if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                                    initPlacesAutocomplete();
                                }
                                // Otherwise the callback=initLocationsAutocomplete in the script tag will fire
                            }
                        })();
                        </script>

                        <?php if (!empty($googlePlacesKey)): ?>
                        <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr($googlePlacesKey); ?>&libraries=places&callback=initLocationsAutocomplete" async defer></script>
                        <?php endif; ?>

                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle settings save
     */
    public function handleSettingsSave(): void
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'save_settings')) {
            wp_die(__('Security check failed', 'ai-seo-client'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-seo-client'));
        }

        // Sanitize and save settings
        update_option('sseo_ai_brand_name', sanitize_text_field($_POST['brand_name'] ?? ''));
        update_option('sseo_ai_brand_voice', sanitize_text_field($_POST['brand_voice'] ?? ''));
        update_option('sseo_ai_targeted_audience', sanitize_textarea_field($_POST['targeted_audience'] ?? ''));
        update_option('sseo_ai_locations', sanitize_textarea_field($_POST['locations'] ?? ''));
        update_option('sseo_ai_client_google_places_key', sanitize_text_field($_POST['google_places_key'] ?? ''));

        // Save photo portfolio references
        $photoPortfolio = [];
        if (!empty($_POST['photo_portfolio']) && is_array($_POST['photo_portfolio'])) {
            foreach ($_POST['photo_portfolio'] as $ref) {
                $url = esc_url_raw($ref['url'] ?? '');
                if (empty($url)) continue;
                $photoPortfolio[] = [
                    'attachment_id' => (int)($ref['attachment_id'] ?? 0),
                    'url' => $url,
                    'type' => sanitize_text_field($ref['type'] ?? 'logo'),
                ];
            }
        }
        update_option('sseo_ai_client_photo_portfolio', $photoPortfolio);

        update_option('sseo_ai_client_default_word_count', max(100, min(5000, (int) ($_POST['default_word_count'] ?? 500))));
        update_option('sseo_ai_prompt_settings', sanitize_textarea_field($_POST['prompt_settings'] ?? ''));
        $this->settings->set('default_include_faq', isset($_POST['default_include_faq']) && $_POST['default_include_faq'] === '1');
        update_option('sseo_ai_client_ssl_verify', isset($_POST['ssl_verify']) && $_POST['ssl_verify'] === '1' ? '1' : '0');
        update_option('sseo_ai_demo_mode', isset($_POST['demo_mode']) && $_POST['demo_mode'] === '1' ? '1' : '0');

        // Local business settings (used by Local SEO schema and Local SERP radius scans)
        update_option('sseo_ai_client_local_business_name', sanitize_text_field($_POST['local_business_name'] ?? ''));
        update_option('sseo_ai_client_local_business_type', sanitize_text_field($_POST['local_business_type'] ?? 'LocalBusiness'));
        update_option('sseo_ai_client_local_description', sanitize_textarea_field($_POST['local_description'] ?? ''));
        update_option('sseo_ai_client_local_street', sanitize_text_field($_POST['local_street'] ?? ''));
        update_option('sseo_ai_client_local_city', sanitize_text_field($_POST['local_city'] ?? ''));
        update_option('sseo_ai_client_local_state', sanitize_text_field($_POST['local_state'] ?? ''));
        update_option('sseo_ai_client_local_postal', sanitize_text_field($_POST['local_postal'] ?? ''));
        update_option('sseo_ai_client_local_country', sanitize_text_field($_POST['local_country'] ?? 'NL'));
        update_option('sseo_ai_client_local_phone', sanitize_text_field($_POST['local_phone'] ?? ''));
        update_option('sseo_ai_client_local_email', sanitize_email($_POST['local_email'] ?? ''));
        update_option('sseo_ai_client_local_url', esc_url_raw($_POST['local_url'] ?? ''));
        update_option('sseo_ai_client_local_latitude', sanitize_text_field($_POST['local_latitude'] ?? ''));
        update_option('sseo_ai_client_local_longitude', sanitize_text_field($_POST['local_longitude'] ?? ''));
        update_option('sseo_ai_client_local_image', esc_url_raw($_POST['local_image'] ?? ''));
        update_option('sseo_ai_client_local_price_range', sanitize_text_field($_POST['local_price_range'] ?? '$$'));
        update_option('sseo_ai_client_local_opening_hours', sanitize_textarea_field($_POST['local_opening_hours'] ?? ''));
        update_option('sseo_ai_client_local_search_radius', max(1, min(500, (int) ($_POST['local_search_radius'] ?? 10))));
        update_option('sseo_ai_client_local_search_grid', max(1, min(9, (int) ($_POST['local_search_grid'] ?? 3))));

        // Post Auto-Cleaner settings
        update_option('sseo_ai_client_autoclean_enabled', isset($_POST['autoclean_enabled']) && $_POST['autoclean_enabled'] === '1' ? '1' : '0');
        update_option('sseo_ai_client_autoclean_days', max(1, min(3650, (int) ($_POST['autoclean_days'] ?? 60))));
        update_option('sseo_ai_client_autoclean_max_clicks', max(0, (int) ($_POST['autoclean_max_clicks'] ?? 0)));

        // Redirect back with success message
        wp_redirect(admin_url('admin.php?page=ai-seo-settings&settings-updated=1'));
        exit;
    }

    /**
     * Handle manual license validation
     */
    public function handleManualValidation(): void
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Handler called');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: User ID = ' . get_current_user_id());
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Can manage_options = ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'manual_validate_license')) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Nonce verification failed');
            wp_die(__('Security check failed. Please refresh the page and try again.', 'ai-seo-client'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Nonce verified successfully');

        if (!current_user_can('manage_options') && !current_user_can('activate_plugins')) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: User lacks required capability');
            wp_die(__('You need administrator permissions to validate the license.', 'ai-seo-client'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Permission check passed');
        
        // Clear validation cache and force re-validation
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $cacheKey = 'ai_seo_license_check_' . md5($licenseKey);
        delete_transient($cacheKey);
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Running validation...');
        
        // Trigger validation
        $this->licenseValidator->validateStoredLicense();
        
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable Manual Validation: Validation complete, redirecting...');
        
        wp_redirect(admin_url('admin.php?page=ai-seo-client&validated=1'));
        exit;
    }
    
    /**
     * Render support tickets page
     */
    public function renderSupportPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }

        $this->supportTickets->renderPage();
    }

    /**
     * Render connection page (license details)
     */
    public function renderConnectionPage(): void
    {
        $licenseStatus = get_option('sseo_ai_client_license_status', 'inactive');
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $tier = get_option('sseo_ai_client_license_tier', 'free');
        $licenseType = get_option('sseo_ai_client_license_type', 'paid');
        $licenseEmail = get_option('sseo_ai_client_license_email', '');

        // Backfill email from the SaaS dashboard if not stored locally yet.
        if (empty($licenseEmail) && !empty($licenseKey) && !empty($tenantKey)) {
            delete_transient('ai_seo_license_check_' . md5($licenseKey));
            $this->licenseValidator->validateStoredLicense();
            $licenseEmail = get_option('sseo_ai_client_license_email', '');
        }

        if (empty($licenseEmail)) {
            $licenseEmail = __('Unknown', 'ai-seo-client');
        }

        $dashboardUrl = $this->settings->getDashboardUrl();

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = $this->getBrandName();
        $brandName = $companyName . ' Smart SEO';

        // White-label colors: use custom brand colors when configured, otherwise Fyndable defaults
        $hasCustomBrand = !empty($whiteLabel['company_name']);
        $primaryColor = $hasCustomBrand ? (sanitize_hex_color($whiteLabel['primary_color'] ?? '') ?: '#379fd3') : '#379fd3';
        $secondaryColor = $hasCustomBrand ? (sanitize_hex_color($whiteLabel['secondary_color'] ?? '') ?: '#8f39ac') : '#8f39ac';
        $usePrimaryOnly = $hasCustomBrand && !empty($whiteLabel['use_primary_only']);
        $bgGradient = $usePrimaryOnly
            ? $primaryColor
            : "linear-gradient(135deg, {$primaryColor} 0%, {$secondaryColor} 100%)";

        // Mask the keys for display
        $maskedLicenseKey = !empty($licenseKey) ? substr($licenseKey, 0, 12) . str_repeat('*', 20) . substr($licenseKey, -8) : '';
        $maskedTenantKey = !empty($tenantKey) ? substr($tenantKey, 0, 8) . str_repeat('*', 20) . substr($tenantKey, -8) : '';

        ?>
        <style>
            /* Critical layout CSS */
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: <?php echo esc_attr($bgGradient); ?>; color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: <?php echo esc_attr($bgGradient); ?>; min-height: calc(100vh - 150px); }
            .sseo-ai-connection-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 60px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); text-align: center; }
            .sseo-ai-connection-card h2 { font-size: 32px; font-weight: 700; color: #111827; margin: 0 0 20px 0; }
            .sseo-ai-connection-card .highlight { background: <?php echo esc_attr($bgGradient); ?>; -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            .connection-details { text-align: left; margin-top: 40px; padding-top: 30px; border-top: 2px solid #f3f4f6; }
            .detail-item { margin-bottom: 20px; }
            .detail-item label { display: block; font-size: 13px; font-weight: 600; color: #6b7280;  margin-bottom: 6px; }
            .detail-item .detail-value { font-size: 16px; color: #111827; font-family: 'Courier New', monospace; background: #f9fafb; padding: 12px 16px; border-radius: 6px; border: 1px solid #e5e7eb; }
            .connection-form { text-align: left; margin-top: 30px; }
            .connection-form .form-field { margin-bottom: 20px; }
            .connection-form label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
            .connection-form input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px; }
            .connection-form input:focus { border-color: <?php echo esc_attr($primaryColor); ?>; outline: none; box-shadow: 0 0 0 3px rgba(<?php echo esc_attr(implode(',', sscanf($primaryColor, '#%02x%02x%02x'))); ?>, 0.1); }
            .sseo-ai-connection-loader { display: none; position: fixed; inset: 0; background: rgba(255,255,255,0.9); z-index: 9999; align-items: center; justify-content: center; flex-direction: column; }
            .sseo-ai-connection-loader.active { display: flex; }
            .sseo-ai-connection-loader .spinner { width: 50px; height: 50px; border: 4px solid #e5e7eb; border-top-color: <?php echo esc_attr($primaryColor); ?>; border-radius: 50%; animation: sseo-conn-spin 1s linear infinite; }
            .sseo-ai-connection-loader p { margin-top: 20px; color: #374151; font-size: 16px; font-weight: 500; }
            @keyframes sseo-conn-spin { to { transform: rotate(360deg); } }
            /* White-label buttons inside the connection card */
            .sseo-ai-connection-card .button-primary { background: <?php echo esc_attr($primaryColor); ?> !important; border-color: <?php echo esc_attr($primaryColor); ?> !important; color: #fff !important; }
            .sseo-ai-connection-card .button-primary:hover { background: <?php echo esc_attr($secondaryColor); ?> !important; border-color: <?php echo esc_attr($secondaryColor); ?> !important; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Connection', 'ai-seo-client'); ?></h1>
            </div>
            
            <div class="sseo-ai-content">
                <?php if ($licenseStatus === 'active'): ?>
                    <div class="sseo-ai-connection-card">
                        <div class="connection-status">
                            <h2>
                                <?php esc_html_e('You are connected', 'ai-seo-client'); ?><br>
                                <?php esc_html_e('to the', 'ai-seo-client'); ?> <span class="highlight"><?php echo esc_html($brandName); ?></span>
                            </h2>
                        </div>
                        
                        <div class="connection-details">
                            <div class="detail-item">
                                <label><?php esc_html_e('Your connection e-mail:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html($licenseEmail); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <label><?php esc_html_e('Your connection API Key:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html($maskedTenantKey); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <label><?php esc_html_e('License Key:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html($maskedLicenseKey); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <label><?php esc_html_e('License Tier:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html(ucfirst($tier)); ?></div>
                            </div>
                            
                            <div class="detail-item">
                                <label><?php esc_html_e('License Type:', 'ai-seo-client'); ?></label>
                                <div class="detail-value">
                                    <?php echo esc_html(ucfirst($licenseType)); ?>
                                    <?php if ($licenseType === 'test'): ?>
                                        <span style="color:#00a32a;font-weight:600;"> (<?php esc_html_e('Unlimited API calls', 'ai-seo-client'); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <label><?php esc_html_e('Dashboard URL:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html($dashboardUrl); ?></div>
                            </div>
                        </div>
                        
                        <?php if (isset($_GET['validated'])): ?>
                            <div style="background:#d1fae5;color:#10b981;padding:12px 16px;border-radius:6px;margin-top:20px;border-left:4px solid #10b981;">
                                <strong>✓</strong> <?php esc_html_e('License validated successfully! Image API credentials refreshed.', 'ai-seo-client'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: 30px; display: flex; gap: 12px;">
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="flex: 1;">
                                <input type="hidden" name="action" value="ai_seo_manual_validate">
                                <?php wp_nonce_field('manual_validate_license'); ?>
                                <button type="submit" class="button button-primary" style="width:100%;">
                                    <?php esc_html_e('Validate License', 'ai-seo-client'); ?>
                                </button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="flex: 1;">
                                <input type="hidden" name="action" value="ai_seo_deactivate_license">
                                <?php wp_nonce_field('deactivate_license'); ?>
                                <button type="submit" class="button button-secondary" style="width:100%;">
                                    <?php esc_html_e('Disconnect', 'ai-seo-client'); ?>
                                </button>
                            </form>
                        </div>
                        <p style="margin-top: 12px; font-size: 13px; color: #6b7280; text-align: center;">
                            <?php esc_html_e('Click "Validate License" to refresh Image API credentials from dashboard', 'ai-seo-client'); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="sseo-ai-connection-card">
                        <div class="connection-status">
                            <h2><?php esc_html_e('Connect to', 'ai-seo-client'); ?> <?php echo esc_html($brandName); ?></h2>
                            <p><?php esc_html_e('Enter your license details to activate the plugin', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="connection-form">
                            <input type="hidden" name="action" value="ai_seo_activate_license">
                            <?php wp_nonce_field('activate_license'); ?>
                            <input type="hidden" name="dashboard_url" id="dashboard_url" value="<?php echo esc_attr($dashboardUrl); ?>">

                            <div class="form-field">
                                <label for="license_key"><?php esc_html_e('License Key', 'ai-seo-client'); ?></label>
                                <input type="text" name="license_key" id="license_key"
                                       placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" required>
                            </div>

                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Connect', 'ai-seo-client'); ?>
                            </button>
                        </form>
                    </div>

                    <div id="sseo-ai-connection-loader" class="sseo-ai-connection-loader">
                        <div class="spinner"></div>
                        <p><?php esc_html_e('Connecting your license, please wait...', 'ai-seo-client'); ?></p>
                    </div>

                    <script>
                        (function () {
                            var form = document.querySelector('.connection-form');
                            var loader = document.getElementById('sseo-ai-connection-loader');
                            if (!form || !loader) return;
                            form.addEventListener('submit', function () {
                                loader.classList.add('active');
                            });
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle license activation form submission
     */
    public function handleLicenseActivation(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: License activation handler called');
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Nonce present: ' . (isset($_POST['_wpnonce']) ? 'yes' : 'no'));
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'activate_license')) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: Nonce verification failed');
            wp_die(__('Security check failed. Please try again.', 'ai-seo-client'));
        }

        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable: User lacks manage_options capability');
            wp_die(__('Insufficient permissions. You must be an administrator to activate licenses.', 'ai-seo-client'));
        }

        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        $dashboardUrl = esc_url_raw($_POST['dashboard_url'] ?? '');

        // Fall back to the baked-in default dashboard URL when none is submitted
        // (the field is hidden in the activation UI).
        if (empty($dashboardUrl)) {
            $dashboardUrl = $this->settings->getDashboardUrl();
        }

        if (empty($licenseKey) || empty($dashboardUrl)) {
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . urlencode('Missing license key or dashboard URL')));
            exit;
        }

        // Store dashboard URL
        update_option('sseo_ai_client_dashboard_url', $dashboardUrl);

        // Attempt activation
        $result = $this->dashboardAPI->activateLicense($licenseKey, $dashboardUrl);

        if (is_wp_error($result)) {
            $errorMsg = $result->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Activation Failed: ' . $errorMsg);
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . urlencode($errorMsg)));
            exit;
        }

        if (empty($result['tenant_key'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) error_log('Fyndable License Activation: No tenant_key in response');
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . urlencode('Invalid response from dashboard - no tenant key')));
            exit;
        }

        // Store license and tenant info
        update_option(SSEO_AI_CLIENT_LICENSE_OPTION, $licenseKey);
        update_option(SSEO_AI_CLIENT_TENANT_OPTION, $result['tenant_key']);
        update_option('sseo_ai_client_license_status', 'active');
        update_option('sseo_ai_client_license_tier', $result['tier']);
        update_option('sseo_ai_client_license_type', $result['type'] ?? 'paid');
        update_option('sseo_ai_client_license_expires', $result['expires_at'] ?? '');
        update_option('sseo_ai_client_license_email', $result['email'] ?? '');
        update_option('sseo_ai_client_rate_limit', $result['rate_limit'] ?? 60);
        update_option('sseo_ai_client_api_limit', $result['api_calls_limit'] ?? 1000);
        
        // Store white-label settings from SaaS dashboard
        if (!empty($result['white_label'])) {
            update_option('sseo_ai_white_label', $result['white_label']);
        }
        
        // Store image API credentials from SaaS dashboard
        if (!empty($result['image_api'])) {
            update_option('sseo_ai_client_image_api', $result['image_api']);
        }

        // Store customer portal URL (used by upgrade buttons in the client UI)
        if (!empty($result['portal_url'])) {
            update_option('sseo_ai_client_portal_url', esc_url_raw($result['portal_url']));
        }

        // Set a transient to show success message on next page load
        set_transient('sseo_ai_activation_success', true, 30);
        
        // Redirect to onboarding wizard instead of dashboard
        wp_redirect(admin_url('admin.php?page=ai-seo-onboarding'));
        exit;
    }

    /**
     * Handle license deactivation
     */
    public function handleLicenseDeactivation(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'deactivate_license')) {
            wp_die(__('Security check failed', 'ai-seo-client'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-seo-client'));
        }

        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');

        if ($licenseKey && $tenantKey && $dashboardUrl) {
            // Notify dashboard of deactivation
            $this->dashboardAPI->deactivateLicense($licenseKey, $tenantKey, $dashboardUrl);
        }

        // Clear local license data
        update_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        update_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        update_option('sseo_ai_client_license_status', 'inactive');
        delete_option('sseo_ai_client_license_tier');
        delete_option('sseo_ai_client_license_type');
        delete_option('sseo_ai_client_license_expires');
        delete_option('sseo_ai_client_image_api');
        delete_option('sseo_ai_white_label');

        wp_redirect(admin_url('admin.php?page=ai-seo-client&deactivated=1'));
        exit;
    }

    /**
     * Render notice when license is required but not active
     */
    private function renderLicenseRequiredNotice(): void
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('License Required', 'ai-seo-client'); ?></h1>
            <div class="notice notice-error">
                <p><?php esc_html_e('This feature requires an active license. Please activate your license key to continue.', 'ai-seo-client'); ?></p>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-client')); ?>" class="button button-primary">
                    <?php esc_html_e('Go to License Activation', 'ai-seo-client'); ?></a></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render notice when feature is not available in current tier
     */
    private function renderFeatureNotAvailable(): void
    {
        $currentTier = $this->licenseValidator->getLicenseTier();
        $upgradeTiers = [
            'free' => 'Starter',
            'starter' => 'Professional',
            'professional' => 'Business',
            'business' => 'Agency',
        ];
        $nextTier = $upgradeTiers[$currentTier] ?? 'Professional';
        
        // Feature benefits by tier
        $tierBenefits = [
            'Starter' => [
                'Link Assistant - AI internal linking',
                'Redirect Manager',
                'Image Alt Generator',
                'Content Rewriter',
                '500 API calls/month',
            ],
            'Professional' => [
                'Rank Tracker - Daily SERP positions',
                'Schema Markup - 10+ structured data types',
                'Topic Clusters - AI content strategy',
                'Content Optimizer - NLP scoring',
                'Google Search Console integration',
                'SERP Competitor Analysis',
                '2,000 API calls/month',
            ],
            'Business' => [
                'AI Content Writer - Full article generation',
                'Content Repurposer',
                'Bulk AI Optimizer',
                'Content Decay Monitor',
                '10,000 API calls/month',
            ],
            'Agency' => [
                'SEO Revisions - Track all changes',
                'Plagiarism Checker',
                'White Label - Custom branding',
                'Unlimited API calls',
                'Priority support',
            ],
        ];
        $benefits = $tierBenefits[$nextTier] ?? $tierBenefits['Professional'];

        // Resolve the upgrade destination. The real upgrade flow lives in the
        // Customer Portal on the SaaS dashboard site (/portal/upgrade). Fall
        // back to the dashboard URL, then to the local Connection page.
        $portalUrl = get_option('sseo_ai_client_portal_url', '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        $upgradeUrl = !empty($portalUrl)
            ? $portalUrl
            : (!empty($dashboardUrl) ? $dashboardUrl : admin_url('admin.php?page=ai-seo-client'));
        ?>
        <style>
            .sseo-upgrade-wrap { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-upgrade-header { 
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); 
                color: #fff; 
                padding: 60px 40px; 
                margin: -10px -20px 0 -20px;
                text-align: center;
            }
            .sseo-upgrade-header h1 { 
                font-size: 42px; 
                font-weight: 800; 
                color: #fff; 
                margin: 0 0 20px 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            .sseo-upgrade-header p { 
                font-size: 20px; 
                opacity: 0.95;
                max-width: 600px;
                margin: 0 auto;
            }
            .sseo-upgrade-content { 
                padding: 40px; 
                background: linear-gradient(180deg, #f8fafc 0%, #e2e8f0 100%); 
                min-height: calc(100vh - 300px);
            }
            .sseo-upgrade-card { 
                background: #fff; 
                border-radius: 16px; 
                padding: 40px; 
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
                max-width: 800px;
                margin: 0 auto;
                text-align: center;
            }
            .sseo-upgrade-card h2 {
                font-size: 28px;
                color: #1e293b;
                margin: 0 0 30px 0;
            }
            .sseo-tier-badge {
                display: inline-block;
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                color: #fff;
                padding: 12px 30px;
                border-radius: 50px;
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 30px;
                box-shadow: 0 4px 6px rgba(55, 159, 211, 0.3);
            }
            .sseo-benefits-list {
                text-align: left;
                max-width: 500px;
                margin: 0 auto 40px;
                list-style: none;
                padding: 0;
            }
            .sseo-benefits-list li {
                padding: 15px 0;
                border-bottom: 1px solid #f1f5f9;
                font-size: 16px;
                color: #475569;
                display: flex;
                align-items: center;
            }
            .sseo-benefits-list li:last-child {
                border-bottom: none;
            }
            .sseo-benefits-list li:before {
                content: "✓";
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #fff;
                border-radius: 50%;
                margin-right: 15px;
                font-size: 14px;
                flex-shrink: 0;
            }
            .sseo-upgrade-cta {
                display: inline-block;
                background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
                color: #fff;
                padding: 18px 50px;
                border-radius: 50px;
                font-size: 18px;
                font-weight: 700;
                text-decoration: none;
                box-shadow: 0 10px 20px rgba(238, 90, 90, 0.3);
                transition: all 0.2s ease;
            }
            .sseo-upgrade-cta:hover {
                transform: translateY(-2px);
                box-shadow: 0 15px 30px rgba(238, 90, 90, 0.4);
                color: #fff;
            }
            .sseo-current-tier {
                margin-top: 30px;
                padding: 20px;
                background: #f8fafc;
                border-radius: 12px;
                font-size: 15px;
                color: #64748b;
            }
            .sseo-current-tier strong {
                color: #334155;
            }
        </style>
        <div class="wrap sseo-upgrade-wrap">
            <div class="sseo-upgrade-header">
                <h1>🚀 <?php esc_html_e('Unlock More SEO Power', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('This feature is available with a higher tier. Upgrade to unlock advanced capabilities and grow your traffic faster.', 'ai-seo-client'); ?></p>
            </div>
            <div class="sseo-upgrade-content">
                <div class="sseo-upgrade-card">
                    <div class="sseo-tier-badge"><?php echo esc_html($nextTier); ?> <?php esc_html_e('Plan', 'ai-seo-client'); ?></div>
                    <h2><?php esc_html_e('What you\'ll get with an upgrade:', 'ai-seo-client'); ?></h2>
                    <ul class="sseo-benefits-list">
                        <?php foreach ($benefits as $benefit): ?>
                        <li><?php echo esc_html($benefit); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?php echo esc_url($upgradeUrl); ?>" class="sseo-upgrade-cta" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Upgrade Now →', 'ai-seo-client'); ?>
                    </a>
                    <div class="sseo-current-tier">
                        <?php printf(esc_html__('Your current plan: %s', 'ai-seo-client'), '<strong>' . esc_html(ucfirst($currentTier)) . '</strong>'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Brand & AI Search Visibility page
     */
    public function renderBrandVisibilityPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }

        $bvConfig = $this->brandVisibility->getSettings();
        $period = sanitize_text_field($_GET['period'] ?? '30d');
        $stats = $this->brandVisibility->getStats($period);
        $recommendations = $this->brandVisibility->getRecommendations($stats, $bvConfig);
        $lastScan = $this->brandVisibility->getLastScanDate();

        $page = max(1, (int)($_GET['bv_page'] ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $platformFilter = sanitize_text_field($_GET['bv_platform'] ?? '');
        $filterType = sanitize_text_field($_GET['bv_filter'] ?? '');
        $mentions = $this->brandVisibility->getMentions($perPage, $offset, $platformFilter, $filterType);
        $total = $this->brandVisibility->getTotalCount($platformFilter, $filterType);
        $pages = (int)ceil($total / $perPage);

        $platformNames = [
            'chatgpt' => 'ChatGPT',
            'perplexity' => 'Perplexity',
            'gemini' => 'Google Gemini',
        ];
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-header p { color: rgba(255,255,255,0.7); margin: 8px 0 0; font-size: 14px; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sseo-ai-dashboard-card h2 { margin-top: 0; color: #111827; font-size: 20px; font-weight: 600; }
            .bv-stat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 15px; margin-bottom: 20px; }
            .bv-stat-card { background: #f8fafc; border-radius: 8px; padding: 18px; text-align: center; border: 1px solid #e2e8f0; }
            .bv-stat-value { font-size: 28px; font-weight: 700; color: #379fd3; }
            .bv-stat-value.score { color: #16a34a; }
            .bv-stat-value.positive { color: #16a34a; }
            .bv-stat-value.negative { color: #dc2626; }
            .bv-stat-label { font-size: 12px; color: #64748b; margin-top: 4px; }
            .bv-table { width: 100%; border-collapse: collapse; }
            .bv-table th { background: #f1f5f9; padding: 10px; text-align: left; font-size: 12px; color: #475569; }
            .bv-table td { padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
            .bv-table tr:hover { background: #f8fafc; }
            .bv-badge-yes { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
            .bv-badge-no { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
            .bv-badge-error { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
            .bv-sentiment-positive { color: #16a34a; font-weight: 600; }
            .bv-sentiment-neutral { color: #64748b; font-weight: 600; }
            .bv-sentiment-negative { color: #dc2626; font-weight: 600; }
            .bv-platform-tag { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; }
            .bv-platform-chatgpt { background: #e0e7ff; color: #3730a3; }
            .bv-platform-perplexity { background: #fce7f3; color: #9d174d; }
            .bv-platform-gemini { background: #dbeafe; color: #8f39ac; }
            .bv-excerpt { max-width: 350px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: help; }
            .bv-pagination { margin-top: 20px; }
            .bv-pagination a, .bv-pagination span { display: inline-block; padding: 6px 12px; margin-right: 4px; border-radius: 4px; font-size: 13px; }
            .bv-pagination a { background: #e2e8f0; color: #334155; text-decoration: none; }
            .bv-pagination a:hover { background: #379fd3; color: #fff; }
            .bv-pagination span.current { background: #379fd3; color: #fff; }
            .bv-settings-form table { width: 100%; }
            .bv-settings-form th { text-align: left; padding: 8px 10px; width: 200px; vertical-align: top; }
            .bv-settings-form td { padding: 8px 10px; }
            .bv-settings-form input[type=text], .bv-settings-form textarea { width: 100%; max-width: 500px; }
            .bv-settings-form textarea { height: 80px; }
            .bv-filter-bar { display: flex; gap: 10px; align-items: center; margin-bottom: 15px; flex-wrap: wrap; }
            .bv-filter-bar select { padding: 5px 10px; border-radius: 4px; border: 1px solid #cbd5e1; }
            .bv-period-tabs { display: flex; gap: 5px; margin-bottom: 15px; }
            .bv-period-tab { padding: 6px 16px; border-radius: 6px; font-size: 13px; text-decoration: none; background: #e2e8f0; color: #334155; }
            .bv-period-tab.active { background: #379fd3; color: #fff; }
            .bv-competitor-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
            .bv-competitor-name { min-width: 120px; font-size: 13px; font-weight: 500; }
            .bv-competitor-bar-bg { flex: 1; background: #e2e8f0; border-radius: 4px; height: 20px; overflow: hidden; }
            .bv-competitor-bar-fill { height: 100%; border-radius: 4px; background: #379fd3; transition: width 0.3s; }
            .bv-competitor-count { min-width: 30px; text-align: right; font-size: 13px; font-weight: 600; }
            .bv-scan-btn { background: #379fd3; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
            .bv-scan-btn:hover { background: #2a7ba8; }
            .bv-scan-btn:disabled { background: #94a3b8; cursor: not-allowed; }
            .bv-empty { text-align: center; padding: 40px; color: #94a3b8; }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Brand & AI Search Visibility', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Track how often and in what context your brand is mentioned by AI-powered search engines and chatbots.', 'ai-seo-client'); ?></p>
            </div>
            <div class="sseo-ai-content">

                <!-- Settings Card -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Configuration', 'ai-seo-client'); ?></h2>
                    <form class="bv-settings-form" id="bv-settings-form">
                        <table>
                            <tr>
                                <th><label for="bv-brand-name"><?php esc_html_e('Brand Name', 'ai-seo-client'); ?></label></th>
                                <td><input type="text" id="bv-brand-name" value="<?php echo esc_attr($bvConfig['brand_name']); ?>" placeholder="<?php esc_attr_e('e.g. Acme Corp', 'ai-seo-client'); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="bv-category"><?php esc_html_e('Category / Industry', 'ai-seo-client'); ?></label></th>
                                <td><input type="text" id="bv-category" value="<?php echo esc_attr($bvConfig['category']); ?>" placeholder="<?php esc_attr_e('e.g. SEO tools, CRM software', 'ai-seo-client'); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="bv-products"><?php esc_html_e('Product Names (one per line)', 'ai-seo-client'); ?></label></th>
                                <td><textarea id="bv-products" placeholder="Product A&#10;Product B"><?php echo esc_textarea($bvConfig['product_names']); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="bv-competitors"><?php esc_html_e('Competitors (one per line)', 'ai-seo-client'); ?></label></th>
                                <td><textarea id="bv-competitors" placeholder="Competitor A&#10;Competitor B"><?php echo esc_textarea($bvConfig['competitors']); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label for="bv-queries"><?php esc_html_e('Search Queries (use {category} placeholder)', 'ai-seo-client'); ?></label></th>
                                <td><textarea id="bv-queries" placeholder="What are the best {category}?&#10;Which {category} would you recommend?"><?php echo esc_textarea($bvConfig['queries']); ?></textarea></td>
                            </tr>
                            <tr>
                                <th><label><?php esc_html_e('AI Platforms to Track', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <?php foreach ($platformNames as $key => $label): ?>
                                    <label style="margin-right: 20px;">
                                        <input type="checkbox" class="bv-platform-checkbox" value="<?php echo esc_attr($key); ?>" <?php echo in_array($key, $bvConfig['platforms']) ? 'checked' : ''; ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        </table>
                        <p style="margin-top: 15px;">
                            <button type="button" class="button button-primary" id="bv-save-settings"><?php esc_html_e('Save Settings', 'ai-seo-client'); ?></button>
                            <button type="button" class="bv-scan-btn" id="bv-run-scan" style="margin-left: 10px;">
                                <?php esc_html_e('Run Scan Now', 'ai-seo-client'); ?>
                            </button>
                            <?php if ($lastScan): ?>
                            <span style="margin-left: 15px; font-size: 13px; color: #64748b;">
                                <?php esc_html_e('Last scan:', 'ai-seo-client'); ?> <?php echo esc_html($lastScan); ?>
                            </span>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>

                <!-- What does this tracker do and how to get found -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('What does AI Search Visibility do?', 'ai-seo-client'); ?></h2>
                    <p><?php esc_html_e('This feature scans the responses of AI-powered search engines and chatbots (ChatGPT, Perplexity, Gemini) for mentions of your brand and products. It tracks whether your brand is mentioned, how often, in what position, and with what sentiment, using your configured queries.', 'ai-seo-client'); ?></p>

                    <h3 style="margin-top: 25px;"><?php esc_html_e('How to get found in AI search / LLM answers', 'ai-seo-client'); ?></h3>
                    <?php if (empty($recommendations)): ?>
                        <p style="color: #666;"><?php esc_html_e('Save your brand settings above to receive personalized recommendations.', 'ai-seo-client'); ?></p>
                    <?php else: ?>
                        <ul style="list-style: disc; margin-left: 20px; padding-left: 0;">
                            <?php foreach ($recommendations as $recommendation): ?>
                                <li style="margin-bottom: 8px;"><?php echo esc_html($recommendation); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if ($stats['total_scans'] > 0): ?>
                <!-- Stats Card -->
                <div class="sseo-ai-dashboard-card">
                    <div class="bv-period-tabs">
                        <?php foreach (['7d' => '7 days', '30d' => '30 days', '90d' => '90 days'] as $pkey => $plabel): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-llm-tracker&period=' . $pkey)); ?>" class="bv-period-tab <?php echo $period === $pkey ? 'active' : ''; ?>"><?php echo esc_html($plabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <h2><?php esc_html_e('Visibility Overview', 'ai-seo-client'); ?></h2>
                    <div class="bv-stat-grid">
                        <div class="bv-stat-card">
                            <div class="bv-stat-value score"><?php echo esc_html($stats['visibility_score']); ?>%</div>
                            <div class="bv-stat-label"><?php esc_html_e('Visibility Score', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="bv-stat-card">
                            <div class="bv-stat-value"><?php echo esc_html($stats['brand_mentions']); ?></div>
                            <div class="bv-stat-label"><?php esc_html_e('Brand Mentions', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="bv-stat-card">
                            <div class="bv-stat-value"><?php echo esc_html($stats['total_scans']); ?></div>
                            <div class="bv-stat-label"><?php esc_html_e('Total Scans', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="bv-stat-card">
                            <div class="bv-stat-value"><?php echo $stats['avg_position'] > 0 ? esc_html('#' . $stats['avg_position']) : '&mdash;'; ?></div>
                            <div class="bv-stat-label"><?php esc_html_e('Avg Position', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="bv-stat-card">
                            <div class="bv-stat-value positive"><?php echo esc_html($stats['sentiment']['positive']); ?></div>
                            <div class="bv-stat-label"><?php esc_html_e('Positive', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="bv-stat-card">
                            <div class="bv-stat-value negative"><?php echo esc_html($stats['sentiment']['negative']); ?></div>
                            <div class="bv-stat-label"><?php esc_html_e('Negative', 'ai-seo-client'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Platform Breakdown -->
                <?php if (!empty($stats['platform_stats'])): ?>
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Platform Breakdown', 'ai-seo-client'); ?></h2>
                    <table class="bv-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Platform', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Total Scans', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Brand Mentions', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Visibility %', 'ai-seo-client'); ?></th>
                                <th><?php esc_html_e('Avg Position', 'ai-seo-client'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['platform_stats'] as $ps): ?>
                            <tr>
                                <td><span class="bv-platform-tag bv-platform-<?php echo esc_attr($ps['platform']); ?>"><?php echo esc_html($platformNames[$ps['platform']] ?? $ps['platform']); ?></span></td>
                                <td><?php echo esc_html($ps['total']); ?></td>
                                <td><?php echo esc_html($ps['mentions']); ?></td>
                                <td><?php echo $ps['total'] > 0 ? esc_html(round(($ps['mentions'] / $ps['total']) * 100, 1) . '%') : '&mdash;'; ?></td>
                                <td><?php echo $ps['avg_position'] ? esc_html('#' . round($ps['avg_position'], 1)) : '&mdash;'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Competitor Comparison -->
                <?php if (!empty($stats['top_competitors'])): ?>
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Competitor Mentions', 'ai-seo-client'); ?></h2>
                    <?php
                    $maxComp = max($stats['top_competitors']);
                    if ($maxComp <= 0) { $maxComp = 1; }
                    foreach ($stats['top_competitors'] as $compName => $compCount):
                        $widthPct = round(($compCount / $maxComp) * 100);
                    ?>
                    <div class="bv-competitor-bar">
                        <div class="bv-competitor-name"><?php echo esc_html($compName); ?></div>
                        <div class="bv-competitor-bar-bg">
                            <div class="bv-competitor-bar-fill" style="width: <?php echo esc_attr($widthPct); ?>%"></div>
                        </div>
                        <div class="bv-competitor-count"><?php echo esc_html($compCount); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Mentions Table -->
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Mention Details', 'ai-seo-client'); ?> (<?php echo number_format($total); ?>)</h2>
                    <?php if (empty($mentions)): ?>
                    <div class="bv-empty">
                        <p><?php esc_html_e('No scan data yet. Configure your brand settings above and run a scan to see results.', 'ai-seo-client'); ?></p>
                    </div>
                    <?php else: ?>
                    <div class="bv-filter-bar">
                        <form method="get" action="">
                            <input type="hidden" name="page" value="ai-seo-llm-tracker">
                            <select name="bv_platform">
                                <option value=""><?php esc_html_e('All Platforms', 'ai-seo-client'); ?></option>
                                <?php foreach ($platformNames as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php echo $platformFilter === $key ? 'selected' : ''; ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="bv_filter">
                                <option value=""><?php esc_html_e('All Results', 'ai-seo-client'); ?></option>
                                <option value="mentioned" <?php echo $filterType === 'mentioned' ? 'selected' : ''; ?>><?php esc_html_e('Brand Mentioned', 'ai-seo-client'); ?></option>
                                <option value="not_mentioned" <?php echo $filterType === 'not_mentioned' ? 'selected' : ''; ?>><?php esc_html_e('Not Mentioned', 'ai-seo-client'); ?></option>
                                <option value="errors" <?php echo $filterType === 'errors' ? 'selected' : ''; ?>><?php esc_html_e('Errors', 'ai-seo-client'); ?></option>
                            </select>
                            <button type="submit" class="button button-small"><?php esc_html_e('Filter', 'ai-seo-client'); ?></button>
                        </form>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="bv-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Platform', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Query', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Mentioned', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Position', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Sentiment', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Competitors', 'ai-seo-client'); ?></th>
                                    <th><?php esc_html_e('Excerpt', 'ai-seo-client'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mentions as $m): ?>
                                <tr>
                                    <td><?php echo esc_html($m['scan_date']); ?></td>
                                    <td><span class="bv-platform-tag bv-platform-<?php echo esc_attr($m['platform']); ?>"><?php echo esc_html($platformNames[$m['platform']] ?? $m['platform']); ?></span></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr($m['query_text']); ?>"><?php echo esc_html($m['query_text']); ?></td>
                                    <td>
                                        <?php if ($m['status'] === 'error'): ?>
                                        <span class="bv-badge-error"><?php esc_html_e('Error', 'ai-seo-client'); ?></span>
                                        <?php elseif ($m['brand_mentioned']): ?>
                                        <span class="bv-badge-yes"><?php esc_html_e('Yes', 'ai-seo-client'); ?></span>
                                        <?php else: ?>
                                        <span class="bv-badge-no"><?php esc_html_e('No', 'ai-seo-client'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $m['mention_position'] > 0 ? esc_html('#' . $m['mention_position']) : '&mdash;'; ?></td>
                                    <td class="bv-sentiment-<?php echo esc_attr($m['sentiment']); ?>"><?php echo esc_html(ucfirst($m['sentiment'])); ?></td>
                                    <td style="max-width: 150px; font-size: 12px;"><?php echo esc_html($m['competitors_mentioned'] ?: '—'); ?></td>
                                    <td class="bv-excerpt" title="<?php echo esc_attr($m['mention_excerpt']); ?>"><?php echo esc_html($m['mention_excerpt'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($pages > 1): ?>
                    <div class="bv-pagination">
                        <?php
                        $baseUrl = admin_url('admin.php?page=ai-seo-llm-tracker');
                        if ($period) { $baseUrl .= '&period=' . $period; }
                        if ($platformFilter) { $baseUrl .= '&bv_platform=' . $platformFilter; }
                        if ($filterType) { $baseUrl .= '&bv_filter=' . $filterType; }
                        for ($i = 1; $i <= $pages; $i++):
                        ?>
                            <?php if ($i === $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                            <a href="<?php echo esc_url($baseUrl . '&bv_page=' . $i); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <script>
        (function() {
            var btn = document.getElementById('bv-run-scan');
            var saveBtn = document.getElementById('bv-save-settings');
            if (!btn) { return; }

            btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.textContent = '<?php echo esc_js(__('Scanning...', 'ai-seo-client')); ?>';

                wp.apiFetch({
                    path: 'sseo-ai/v1/brand-visibility/scan',
                    method: 'POST'
                }).then(function(res) {
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('Run Scan Now', 'ai-seo-client')); ?>';
                    if (res.success) {
                        alert('<?php echo esc_js(__('Scan complete!', 'ai-seo-client')); ?> ' + res.scanned + ' <?php echo esc_js(__('queries processed.', 'ai-seo-client')); ?>');
                        location.reload();
                    } else {
                        alert('<?php echo esc_js(__('Scan completed with issues.', 'ai-seo-client')); ?>');
                    }
                }).catch(function(err) {
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('Run Scan Now', 'ai-seo-client')); ?>';
                    alert('<?php echo esc_js(__('Scan failed:', 'ai-seo-client')); ?> ' + (err.message || 'Unknown error'));
                });
            });

            if (saveBtn) {
                saveBtn.addEventListener('click', function() {
                    var platforms = [];
                    document.querySelectorAll('.bv-platform-checkbox:checked').forEach(function(cb) {
                        platforms.push(cb.value);
                    });

                    wp.apiFetch({
                        path: 'sseo-ai/v1/brand-visibility/settings',
                        method: 'POST',
                        data: {
                            brand_name: document.getElementById('bv-brand-name').value,
                            category: document.getElementById('bv-category').value,
                            product_names: document.getElementById('bv-products').value,
                            competitors: document.getElementById('bv-competitors').value,
                            queries: document.getElementById('bv-queries').value,
                            platforms: platforms
                        }
                    }).then(function() {
                        saveBtn.textContent = '<?php echo esc_js(__('Saved!', 'ai-seo-client')); ?>';
                        setTimeout(function() {
                            saveBtn.textContent = '<?php echo esc_js(__('Save Settings', 'ai-seo-client')); ?>';
                        }, 2000);
                    }).catch(function(err) {
                        alert('<?php echo esc_js(__('Failed to save:', 'ai-seo-client')); ?> ' + (err.message || 'Unknown error'));
                    });
                });
            }
        })();
        </script>
        <?php
    }
}

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
    private LicenseValidator $licenseValidator;
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
    private ?SeoRevisions $seoRevisions = null;
    private ?OpenGraph $openGraph = null;
    private ?CanonicalUrl $canonicalUrl = null;
    private ?Breadcrumbs $breadcrumbs = null;
    private ?BulkActions $bulkActions = null;
    private ?SeoDashboard $seoDashboard = null;
    private ?RankTracker $rankTracker = null;
    private ?Hreflang $hreflang = null;
    private ?SeoReportExport $seoReportExport = null;
    private ?WooCommerceSeo $wooSeo = null;
    private ?PlagiarismChecker $plagiarismChecker = null;
    private ?ContentOptimizer $contentOptimizer = null;
    private ?SerpCompetitor $serpCompetitor = null;
    private ?TopicCluster $topicCluster = null;
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
    private ?GscDashboard $gscDashboard = null;
    private ?BacklinkAnalyzer $backlinkAnalyzer = null;
    private ?SerpFeatureTracker $serpFeatureTracker = null;
    private ?ExternalIntegrations $externalIntegrations = null;
    private ?CompetitorResearch $competitorResearch = null;
    private ?WhiteLabelManager $whiteLabelManager = null;
    private ?ContentPerformanceMonitor $contentPerformanceMonitor = null;
    private ?InternationalSEO $internationalSEO = null;
    private ?TechnicalSEOAuditor $technicalSEOAuditor = null;
    private ?ContentCalendar $contentCalendar = null;
    private ?AdvancedBacklinks $advancedBacklinks = null;
    private ?SmartInternalLinking $smartInternalLinking = null;
    private ?EEATValidator $eeatValidator = null;
    private ?VideoSEO $videoSEO = null;
    private ?FAQSchema $faqSchema = null;
    private ?AIImageGenerator $aiImageGenerator = null;

    public function init(): void
    {
        $this->settings = new Settings();
        $this->licenseValidator = new LicenseValidator($this->settings);
        $this->dashboardAPI = new DashboardAPI($this->settings);
        $this->healthLogger = new HealthLogger();
        $this->llmClient = new LlmClient($this->settings, $this->healthLogger, $this->dashboardAPI);

        // Initialize license validation
        add_action('init', [$this, 'initializeLicense']);

        // Add admin menu for license activation - use priority 5 to register before other features
        add_action('admin_menu', [$this, 'registerAdminMenu'], 5);
        // Temporarily disable to allow all feature pages to be accessible
        // add_action('admin_menu', [$this, 'removeOldSubmenus'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Handle license activation form
        add_action('admin_post_ai_seo_activate_license', [$this, 'handleLicenseActivation']);
        add_action('admin_post_ai_seo_deactivate_license', [$this, 'handleLicenseDeactivation']);
        
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
        
        // Create rank tracker tables
        $settings = new Settings();
        $dashAPI = new DashboardAPI($settings);
        $rt = new RankTracker($settings, $dashAPI);
        $rt->createTables();
    }

    /**
     * Initialize license on plugin load
     */
    public function initializeLicense(): void
    {
        // Check if license is valid on every page load (cached)
        $this->licenseValidator->validateStoredLicense();
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
        
        // External Integrations - available to all tiers
        $this->externalIntegrations = new ExternalIntegrations($this->settings);
        $this->externalIntegrations->register();
        
        // Content Performance Monitor - available to all tiers
        $this->contentPerformanceMonitor = new ContentPerformanceMonitor($this->settings);
        $this->contentPerformanceMonitor->register();
        
        // Content Calendar - available to all tiers
        $this->contentCalendar = new ContentCalendar($this->settings, $this->llmClient);
        $this->contentCalendar->register();
        
        // Smart Internal Linking - available to all tiers
        $this->smartInternalLinking = new SmartInternalLinking($this->settings, $this->llmClient);
        $this->smartInternalLinking->register();
        
        // E-E-A-T Validator - available to all tiers
        $this->eeatValidator = new EEATValidator($this->settings, $this->llmClient);
        $this->eeatValidator->register();
        
        // Video SEO - available to all tiers
        $this->videoSEO = new VideoSEO($this->settings, $this->llmClient);
        $this->videoSEO->register();
        
        // FAQ Schema - available to all tiers
        $this->faqSchema = new FAQSchema($this->settings, $this->llmClient);
        $this->faqSchema->register();
        
        // AI Image Generator - available to all tiers
        $this->aiImageGenerator = new AIImageGenerator($this->settings, $this->llmClient);
        $this->aiImageGenerator->register();
        
        // Starter+ features
        if (in_array($tier, ['starter', 'professional', 'business', 'agency', 'trial'])) {
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
        if (in_array($tier, ['professional', 'business', 'agency', 'trial'])) {
            $this->schemaMarkup = new SchemaMarkup($this->settings);
            $this->schemaMarkup->register();
            
            $this->localSEO = new LocalSEO($this->settings);
            $this->localSEO->register();
            
            $this->notFoundMonitor = new NotFoundMonitor($this->settings);
            $this->notFoundMonitor->register();
            
            $this->rankTracker = new RankTracker($this->settings, $this->dashboardAPI);
            $this->rankTracker->register();
            
            $this->seoReportExport = new SeoReportExport($this->settings);
            $this->seoReportExport->register();
            
            $this->wooSeo = new WooCommerceSeo($this->settings, $this->llmClient);
            $this->wooSeo->register();
            
            $this->contentOptimizer = new ContentOptimizer($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->contentOptimizer->register();
            
            $this->serpCompetitor = new SerpCompetitor($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->serpCompetitor->register();
            
            $this->topicCluster = new TopicCluster($this->settings, $this->llmClient);
            $this->topicCluster->register();
            
            $this->keywordDifficulty = new KeywordDifficulty($this->settings, $this->llmClient);
            $this->keywordDifficulty->register();
            
            $this->contentBrief = new ContentBrief($this->settings, $this->llmClient, $this->dashboardAPI);
            $this->contentBrief->register();
            
            $this->keywordExplorer = new KeywordExplorer($this->settings, $this->dashboardAPI, $this->llmClient);
            $this->keywordExplorer->register();
            
            $gscClient = new GscClient($this->settings);
            $this->gscDashboard = new GscDashboard($this->settings, $gscClient);
            $this->gscDashboard->register();
            
            // SERP Feature Tracker
            $this->serpFeatureTracker = new SerpFeatureTracker($this->settings, $this->llmClient);
            $this->serpFeatureTracker->register();
            
            // Backlink Analyzer
            $this->backlinkAnalyzer = new BacklinkAnalyzer($this->settings);
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
        }
        
        // Business+ features
        if (in_array($tier, ['business', 'agency'])) {
            $this->contentWriter = new ContentWriter($this->llmClient, $this->settings);
            $this->contentWriter->register();
            
            $this->aiRepurposer = new AIRepurposer($this->settings, $this->llmClient);
            $this->aiRepurposer->register();
            
            $this->bulkActions = new BulkActions($this->settings, $this->llmClient);
            $this->bulkActions->register();
            
            $snapshots = new SnapshotRepository();
            $gscClientBiz = new GscClient($this->settings);
            $this->contentDecay = new ContentDecay($snapshots, $gscClientBiz, $this->settings);
            $this->contentDecay->register();
            
            $this->auditService = new AuditService();
        }
        
        // Agency only features
        if ($tier === 'agency') {
            $this->seoRevisions = new SeoRevisions();
            $this->seoRevisions->register();
            
            $this->plagiarismChecker = new PlagiarismChecker($this->settings, $this->llmClient);
            $this->plagiarismChecker->register();
            
            // White-Label Manager (Agency only)
            $this->whiteLabelManager = new WhiteLabelManager($this->settings);
            $this->whiteLabelManager->register();
        }
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        $isLicenseValid = $this->licenseValidator->isLicenseValid();
        
        // Main menu - use ai-seo-client as slug so all feature submenus work
        add_menu_page(
            __('SSEO AI', 'ai-seo-client'),
            __('SSEO AI', 'ai-seo-client'),
            'manage_options',
            'ai-seo-client',
            [$this, 'renderConnectionPage'],
            'dashicons-chart-line',
            30
        );

        // First submenu replaces the main menu link text
        add_submenu_page(
            'ai-seo-client',
            __('Connection', 'ai-seo-client'),
            __('Connection', 'ai-seo-client'),
            'manage_options',
            'ai-seo-client',
            [$this, 'renderConnectionPage']
        );
    }

    /**
     * Remove old submenu pages - DISABLED to allow all feature pages to work
     * Feature classes register their own menus under ai-seo-client parent
     */
    public function removeOldSubmenus(): void
    {
        // Disabled - let all feature classes register their menus
        return;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'ai-seo') === false) {
            return;
        }

        wp_enqueue_style(
            'ai-seo-client-admin',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/client-admin.css',
            [],
            SSEO_AI_CLIENT_VERSION
        );

        wp_enqueue_script(
            'ai-seo-client-admin',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/client-admin.js',
            ['jquery', 'wp-api-fetch'],
            SSEO_AI_CLIENT_VERSION,
            true
        );

        wp_localize_script('ai-seo-client-admin', 'aiSeoClient', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sseo_ai_client_nonce'),
            'isLicensed' => $this->licenseValidator->isLicenseValid(),
        ]);
    }

    /**
     * Render license activation page
     */
    public function renderLicensePage(): void
    {
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $licenseStatus = get_option('sseo_ai_client_license_status', 'inactive');
        $tenantKey = get_option(SSEO_AI_CLIENT_TENANT_OPTION, '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        ?>
        <div class="wrap ai-seo-client">
            <h1><?php esc_html_e('SSEO AI License Activation', 'ai-seo-client'); ?></h1>

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
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="dashboard_url"><?php esc_html_e('SaaS Dashboard URL', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="url" name="dashboard_url" id="dashboard_url" 
                                           value="<?php echo esc_attr($dashboardUrl); ?>" 
                                           class="regular-text" 
                                           placeholder="https://your-saas-domain.com" required>
                                    <p class="description"><?php esc_html_e('The URL where your AI SEO SaaS Dashboard is hosted', 'ai-seo-client'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="license_key"><?php esc_html_e('License Key', 'ai-seo-client'); ?></label></th>
                                <td>
                                    <input type="text" name="license_key" id="license_key" 
                                           value="<?php echo esc_attr($licenseKey); ?>" 
                                           class="regular-text" 
                                           placeholder="SSEO-AI-XXXX-XXXX-XXXX-XXXX" required>
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
        if ($this->seoDashboard) {
            $this->seoDashboard->renderPage();
        } else {
            // Fallback if seoDashboard not initialized
            ?>
            <div class="wrap sseo-ai-modern">
                <div class="sseo-ai-header">
                    <h1><?php esc_html_e('Statistics', 'ai-seo-client'); ?></h1>
                </div>
                <div class="sseo-ai-content">
                    <div class="sseo-ai-dashboard-card">
                        <p><?php esc_html_e('Dashboard loading...', 'ai-seo-client'); ?></p>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Render Content Calendar page
     */
    public function renderContentCalendarPage(): void
    {
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Content Calendar', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Plan Your Content Strategy', 'ai-seo-client'); ?></h2>
                    <p style="margin-bottom: 30px; color: #646970;"><?php esc_html_e('Organize and schedule your content with AI-powered suggestions', 'ai-seo-client'); ?></p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-content-calendar')); ?>" class="ai-tool-card">
                            <h3>📅 <?php esc_html_e('Editorial Calendar', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Visual calendar view of your content pipeline', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-content-calendar')); ?>" class="ai-tool-card">
                            <h3>🎯 <?php esc_html_e('Content Ideas', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('AI-generated topic suggestions based on trends', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-content-calendar')); ?>" class="ai-tool-card">
                            <h3>📊 <?php esc_html_e('Publishing Schedule', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Optimize posting times for maximum engagement', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-content-calendar')); ?>" class="ai-tool-card">
                            <h3>🔔 <?php esc_html_e('Deadline Reminders', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Never miss a publication deadline', 'ai-seo-client'); ?></p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Topic Cluster page
     */
    public function renderTopicClusterPage(): void
    {
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Topic Cluster', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Build Topic Authority', 'ai-seo-client'); ?></h2>
                    <p style="margin-bottom: 30px; color: #646970;"><?php esc_html_e('Create interconnected content clusters to dominate search rankings', 'ai-seo-client'); ?></p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-topic-clusters')); ?>" class="ai-tool-card">
                            <h3>🎯 <?php esc_html_e('Pillar Pages', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Create comprehensive pillar content for core topics', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-topic-clusters')); ?>" class="ai-tool-card">
                            <h3>🔗 <?php esc_html_e('Cluster Content', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Generate supporting articles linked to pillars', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-internal-linking')); ?>" class="ai-tool-card">
                            <h3>🕸️ <?php esc_html_e('Internal Linking', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('AI-suggested internal link opportunities', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-topic-clusters')); ?>" class="ai-tool-card">
                            <h3>📈 <?php esc_html_e('Cluster Performance', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Track rankings and traffic for each cluster', 'ai-seo-client'); ?></p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI Tools page
     */
    public function renderAIToolsPage(): void
    {
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('AI Tools', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Available AI Tools', 'ai-seo-client'); ?></h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-writer')); ?>" class="ai-tool-card">
                            <h3>🤖 <?php esc_html_e('Content Writer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('AI-powered content generation for blog posts and articles', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-bulk')); ?>" class="ai-tool-card">
                            <h3>✍️ <?php esc_html_e('Content Rewriter', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Rewrite and optimize existing content', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-image-generator')); ?>" class="ai-tool-card">
                            <h3>🎨 <?php esc_html_e('Image Generator', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Generate featured images and graphics', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-image-generator')); ?>" class="ai-tool-card">
                            <h3>🖼️ <?php esc_html_e('Image Alt Generator', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Auto-generate SEO-friendly alt text for images', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-faq-schema')); ?>" class="ai-tool-card">
                            <h3>❓ <?php esc_html_e('FAQ Generator', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Generate FAQ schema from content', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-video-seo')); ?>" class="ai-tool-card">
                            <h3>🎥 <?php esc_html_e('Video SEO', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Video transcript generation and optimization', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-brief')); ?>" class="ai-tool-card">
                            <h3>🔄 <?php esc_html_e('Content Repurposer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Repurpose content for different formats', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-optimizer')); ?>" class="ai-tool-card">
                            <h3>📊 <?php esc_html_e('Content Optimizer', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('AI-powered content optimization suggestions', 'ai-seo-client'); ?></p>
                        </a>
                        
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Site Audit page
     */
    public function renderSiteAuditPage(): void
    {
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Site Audit', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Comprehensive SEO Analysis', 'ai-seo-client'); ?></h2>
                    <p style="margin-bottom: 30px; color: #646970;"><?php esc_html_e('Identify and fix technical SEO issues across your entire site', 'ai-seo-client'); ?></p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-technical-audit')); ?>" class="ai-tool-card">
                            <h3>🔍 <?php esc_html_e('Technical SEO', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Crawl errors, broken links, redirect chains', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-performance')); ?>" class="ai-tool-card">
                            <h3>⚡ <?php esc_html_e('Page Speed', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Core Web Vitals and performance metrics', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-technical-audit')); ?>" class="ai-tool-card">
                            <h3>📱 <?php esc_html_e('Mobile Usability', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Mobile-friendly testing and optimization', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-technical-audit')); ?>" class="ai-tool-card">
                            <h3>🏗️ <?php esc_html_e('Site Structure', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('URL structure, sitemap, and navigation analysis', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-technical-audit')); ?>" class="ai-tool-card">
                            <h3>🔒 <?php esc_html_e('Security Check', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('HTTPS, mixed content, and security headers', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-assistant-schema')); ?>" class="ai-tool-card">
                            <h3>📋 <?php esc_html_e('Schema Markup', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Structured data validation and suggestions', 'ai-seo-client'); ?></p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Integrations page
     */
    public function renderIntegrationsPage(): void
    {
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Integrations', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <h2><?php esc_html_e('Connect Your Tools', 'ai-seo-client'); ?></h2>
                    <p style="margin-bottom: 30px; color: #646970;"><?php esc_html_e('Integrate with popular SEO and marketing platforms', 'ai-seo-client'); ?></p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" class="ai-tool-card">
                            <h3>📊 <?php esc_html_e('Google Analytics', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Track traffic and conversion data', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-gsc')); ?>" class="ai-tool-card">
                            <h3>🔎 <?php esc_html_e('Google Search Console', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Monitor search performance and indexing', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" class="ai-tool-card">
                            <h3>📈 <?php esc_html_e('SEMrush', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Keyword research and competitor analysis', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" class="ai-tool-card">
                            <h3>🔗 <?php esc_html_e('Ahrefs', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Backlink monitoring and site explorer', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" class="ai-tool-card">
                            <h3>📧 <?php esc_html_e('Email Marketing', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Mailchimp, ConvertKit, ActiveCampaign', 'ai-seo-client'); ?></p>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-integrations')); ?>" class="ai-tool-card">
                            <h3>💬 <?php esc_html_e('Social Media', 'ai-seo-client'); ?></h3>
                            <p><?php esc_html_e('Auto-share content to social platforms', 'ai-seo-client'); ?></p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
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
        
        // Check for success message
        $success = isset($_GET['settings-updated']) && $_GET['settings-updated'] === '1';
        
        ?>
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
                                <label for="locations"><?php esc_html_e('Target Locations', 'ai-seo-client'); ?></label>
                                <textarea name="locations" id="locations" rows="3" 
                                          placeholder="e.g., Amsterdam, Rotterdam, Utrecht"><?php echo esc_textarea($locations); ?></textarea>
                                <p class="field-description"><?php esc_html_e('Enter cities, regions, or countries (comma-separated)', 'ai-seo-client'); ?></p>
                            </div>
                        </div>
                        
                        <div class="settings-section">
                            <h2><?php esc_html_e('AI Prompt Settings', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Custom instructions for AI content generation', 'ai-seo-client'); ?></p>
                            
                            <div class="form-field">
                                <label for="prompt_settings"><?php esc_html_e('Custom Prompt Instructions', 'ai-seo-client'); ?></label>
                                <textarea name="prompt_settings" id="prompt_settings" rows="6" 
                                          placeholder="e.g., Always include actionable tips, use bullet points for readability, focus on practical examples..."><?php echo esc_textarea($promptSettings); ?></textarea>
                                <p class="field-description"><?php esc_html_e('Additional instructions that will be included in all AI prompts', 'ai-seo-client'); ?></p>
                            </div>
                        </div>
                        
                        <div class="settings-actions">
                            <button type="submit" class="button button-primary button-large">
                                <?php esc_html_e('Save Settings', 'ai-seo-client'); ?>
                            </button>
                        </div>
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
        update_option('sseo_ai_prompt_settings', sanitize_textarea_field($_POST['prompt_settings'] ?? ''));

        // Redirect back with success message
        wp_redirect(admin_url('admin.php?page=ai-seo-settings&settings-updated=1'));
        exit;
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
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        
        // Mask the keys for display
        $maskedLicenseKey = !empty($licenseKey) ? substr($licenseKey, 0, 12) . str_repeat('*', 20) . substr($licenseKey, -8) : '';
        $maskedTenantKey = !empty($tenantKey) ? substr($tenantKey, 0, 8) . str_repeat('*', 20) . substr($tenantKey, -8) : '';
        
        ?>
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
                                <?php esc_html_e('to the', 'ai-seo-client'); ?> <span class="highlight"><?php esc_html_e('SEO AI Service', 'ai-seo-client'); ?></span>
                            </h2>
                        </div>
                        
                        <div class="connection-details">
                            <div class="detail-item">
                                <label><?php esc_html_e('Your connection e-mail:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html(wp_get_current_user()->user_email); ?></div>
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
                                <label><?php esc_html_e('Dashboard URL:', 'ai-seo-client'); ?></label>
                                <div class="detail-value"><?php echo esc_html($dashboardUrl); ?></div>
                            </div>
                        </div>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 30px;">
                            <input type="hidden" name="action" value="ai_seo_deactivate_license">
                            <?php wp_nonce_field('deactivate_license'); ?>
                            <button type="submit" class="button button-secondary">
                                <?php esc_html_e('Disconnect', 'ai-seo-client'); ?>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="sseo-ai-connection-card">
                        <div class="connection-status">
                            <h2><?php esc_html_e('Connect to SEO AI Service', 'ai-seo-client'); ?></h2>
                            <p><?php esc_html_e('Enter your license details to activate the plugin', 'ai-seo-client'); ?></p>
                        </div>
                        
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="connection-form">
                            <input type="hidden" name="action" value="ai_seo_activate_license">
                            <?php wp_nonce_field('activate_license'); ?>
                            
                            <div class="form-field">
                                <label for="dashboard_url"><?php esc_html_e('Dashboard URL', 'ai-seo-client'); ?></label>
                                <input type="url" name="dashboard_url" id="dashboard_url" 
                                       placeholder="https://your-saas-domain.com" required>
                            </div>
                            
                            <div class="form-field">
                                <label for="license_key"><?php esc_html_e('License Key', 'ai-seo-client'); ?></label>
                                <input type="text" name="license_key" id="license_key" 
                                       placeholder="SSEO-AI-XXXX-XXXX-XXXX" required>
                            </div>
                            
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Connect', 'ai-seo-client'); ?>
                            </button>
                        </form>
                    </div>
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
        error_log('SSEO AI: License activation handler called');
        error_log('SSEO AI: User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        error_log('SSEO AI: Nonce present: ' . (isset($_POST['_wpnonce']) ? 'yes' : 'no'));
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'activate_license')) {
            error_log('SSEO AI: Nonce verification failed');
            wp_die(__('Security check failed. Please try again.', 'ai-seo-client'));
        }

        if (!current_user_can('manage_options')) {
            error_log('SSEO AI: User lacks manage_options capability');
            wp_die(__('Insufficient permissions. You must be an administrator to activate licenses.', 'ai-seo-client'));
        }

        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        $dashboardUrl = esc_url_raw($_POST['dashboard_url'] ?? '');

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
            error_log('SSEO AI License Activation Failed: ' . $errorMsg);
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . urlencode($errorMsg)));
            exit;
        }

        if (empty($result['tenant_key'])) {
            error_log('SSEO AI License Activation: No tenant_key in response');
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . urlencode('Invalid response from dashboard - no tenant key')));
            exit;
        }

        // Store license and tenant info
        update_option(SSEO_AI_CLIENT_LICENSE_OPTION, $licenseKey);
        update_option(SSEO_AI_CLIENT_TENANT_OPTION, $result['tenant_key']);
        update_option('sseo_ai_client_license_status', 'active');
        update_option('sseo_ai_client_license_tier', $result['tier']);
        update_option('sseo_ai_client_license_expires', $result['expires_at'] ?? '');
        update_option('sseo_ai_client_rate_limit', $result['rate_limit'] ?? 60);
        update_option('sseo_ai_client_api_limit', $result['api_calls_limit'] ?? 1000);

        // Set a transient to show success message on next page load
        set_transient('sseo_ai_activation_success', true, 30);
        
        // Redirect to dashboard instead of license page
        wp_redirect(admin_url('admin.php?page=ai-seo-dashboard'));
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
        delete_option('sseo_ai_client_license_expires');

        wp_redirect(admin_url('admin.php?page=ai-seo-client&deactivated=1'));
        exit;
    }
}

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

        // Add admin menu for license activation
        add_action('admin_menu', [$this, 'registerAdminMenu'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

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
            
            // Google Search Console OAuth & Dashboard
            $gscOAuth = new GscOAuth($this->settings);
            $gscOAuth->register();
            
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
        if (in_array($tier, ['business', 'agency', 'dev'])) {
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
        
        // Agency-only features (DEV includes these)
        if (in_array($tier, ['agency', 'dev'])) {
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
        $tier = $this->licenseValidator->getLicenseTier();
        
        // Get white-label company name if set
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $menuName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : __('SSEO AI', 'ai-seo-client');
        
        // Main menu
        add_menu_page(
            $menuName,
            $menuName,
            'manage_options',
            'ai-seo-client',
            [$this, 'renderConnectionPage'],
            'dashicons-chart-line',
            30
        );

        // Connection (first submenu replaces main menu text)
        add_submenu_page(
            'ai-seo-client',
            __('Connection', 'ai-seo-client'),
            __('🔗 Connection', 'ai-seo-client'),
            'manage_options',
            'ai-seo-client',
            [$this, 'renderConnectionPage']
        );
        
        // Only show feature menus if license is valid
        if ($isLicenseValid) {
            // All tiers: Dashboard, Content Calendar, AI Tools, Link Manager, Integrations
            
            // 1. Dashboard / Statistics - all tiers
            add_submenu_page(
                'ai-seo-client',
                __('Dashboard', 'ai-seo-client'),
                __('📊 Dashboard', 'ai-seo-client'),
                'manage_options',
                'ai-seo-dashboard',
                [$this, 'renderDashboardPage']
            );
            
            // 2. Content Calendar - all tiers
            add_submenu_page(
                'ai-seo-client',
                __('Content Calendar', 'ai-seo-client'),
                __('📅 Content Calendar', 'ai-seo-client'),
                'manage_options',
                'ai-seo-content-calendar',
                [$this, 'renderContentCalendarPage']
            );
            
            // 3. AI Tools - all tiers
            add_submenu_page(
                'ai-seo-client',
                __('AI Tools', 'ai-seo-client'),
                __('🤖 AI Tools', 'ai-seo-client'),
                'manage_options',
                'ai-seo-ai-tools',
                [$this, 'renderAIToolsPage']
            );
            
            // 4. Link Manager (Smart Internal Linking) - all tiers
            add_submenu_page(
                'ai-seo-client',
                __('Link Manager', 'ai-seo-client'),
                __('🔗 Link Manager', 'ai-seo-client'),
                'manage_options',
                'ai-seo-link-manager',
                [$this, 'renderLinkManagerPage']
            );
            
            // 5. Sitemaps - all tiers
            add_submenu_page(
                'ai-seo-client',
                __('Sitemaps', 'ai-seo-client'),
                __('🗺️ Sitemaps', 'ai-seo-client'),
                'manage_options',
                'ai-seo-sitemaps',
                [$this, 'renderSitemapsPage']
            );
            
            // 6. Integrations - all tiers
            add_submenu_page(
                'ai-seo-client',
                __('Integrations', 'ai-seo-client'),
                __('🔌 Integrations', 'ai-seo-client'),
                'manage_options',
                'ai-seo-integrations',
                [$this, 'renderIntegrationsPage']
            );
            
            // Professional+ features: Topic Clusters, Site Audit, Rank Tracker
            $professionalTiers = ['professional', 'business', 'agency', 'trial', 'dev'];
            if (in_array($tier, $professionalTiers)) {
                // 6. Topic Clusters
                add_submenu_page(
                    'ai-seo-client',
                    __('Topic Clusters', 'ai-seo-client'),
                    __('🎯 Topic Clusters', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-topic-clusters',
                    [$this, 'renderTopicClusterPage']
                );
                
                // 7. Site Audit
                add_submenu_page(
                    'ai-seo-client',
                    __('Site Audit', 'ai-seo-client'),
                    __('🔍 Site Audit', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-site-audit',
                    [$this, 'renderSiteAuditPage']
                );
                
                // 8. Rank Tracker
                add_submenu_page(
                    'ai-seo-client',
                    __('Rank Tracker', 'ai-seo-client'),
                    __('📈 Rank Tracker', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-rank-tracker',
                    [$this, 'renderRankTrackerPage']
                );
                
                // 9. Search Console (GSC) - Professional+
                add_submenu_page(
                    'ai-seo-client',
                    __('Search Console', 'ai-seo-client'),
                    __('📊 Search Console', 'ai-seo-client'),
                    'manage_options',
                    'ai-seo-gsc',
                    [$this, 'renderGscDashboardPage']
                );
            }
        }
        
        // Settings (always visible)
        add_submenu_page(
            'ai-seo-client',
            __('Settings', 'ai-seo-client'),
            __('⚙️ Settings', 'ai-seo-client'),
            'manage_options',
            'ai-seo-settings',
            [$this, 'renderSettingsPage']
        );
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

        // Apply white-label CSS variables
        if (!empty($whiteLabel['primary_color']) || !empty($whiteLabel['secondary_color'])) {
            $primaryColor = $whiteLabel['primary_color'] ?? '#2563eb';
            $secondaryColor = $whiteLabel['secondary_color'] ?? '#1e40af';
            wp_add_inline_style('ai-seo-client-admin', "
                :root {
                    --sseo-primary-color: {$primaryColor};
                    --sseo-secondary-color: {$secondaryColor};
                }
                .sseo-ai-header, .ai-tool-card:hover {
                    border-color: {$primaryColor} !important;
                }
                .button-primary.sseo-btn, .sseo-ai-btn-primary {
                    background-color: {$primaryColor} !important;
                    border-color: {$primaryColor} !important;
                }
                .sseo-ai-header h1::before {
                    color: {$primaryColor};
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
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
            .ai-tool-card { display: block; background: #fff; border: 2px solid #e5e7eb; border-radius: 6px; padding: 24px; transition: all .2s ease; }
            .ai-tool-card:hover { border-color: #FF4D00; transform: translateY(-4px); box-shadow: 0 10px 20px rgba(255, 77, 0, 0.15); }
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
     * Render Sitemaps page - shows sitemap status and health
     */
    public function renderSitemapsPage(): void
    {
        if (!$this->licenseValidator->isLicenseValid()) {
            $this->renderLicenseRequiredNotice();
            return;
        }

        // Check sitemap status
        $sitemapUrl = home_url('/sitemap.xml');
        $sitemapIndexUrl = home_url('/sitemap_index.xml');
        
        $response = wp_remote_get($sitemapUrl, ['timeout' => 10]);
        $sitemapExists = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        
        $indexResponse = wp_remote_get($sitemapIndexUrl, ['timeout' => 10]);
        $indexExists = !is_wp_error($indexResponse) && wp_remote_retrieve_response_code($indexResponse) === 200;
        
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); margin-bottom: 30px; }
            .sitemap-status { display: flex; align-items: center; gap: 15px; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
            .sitemap-status.ok { background: #d1fae5; border-left: 4px solid #00a32a; }
            .sitemap-status.error { background: #fee2e2; border-left: 4px solid #d63638; }
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
                        
                        <?php if ($sitemapExists || $indexExists): ?>
                            <?php if ($indexExists): ?>
                                <div class="sitemap-status ok">
                                    <span style="font-size: 24px;">✅</span>
                                    <div>
                                        <strong><?php esc_html_e('Sitemap Index Found', 'ai-seo-client'); ?></strong>
                                        <div class="sitemap-url">
                                            <a href="<?php echo esc_url($sitemapIndexUrl); ?>" target="_blank"><?php echo esc_html($sitemapIndexUrl); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($sitemapExists): ?>
                                <div class="sitemap-status ok">
                                    <span style="font-size: 24px;">✅</span>
                                    <div>
                                        <strong><?php esc_html_e('XML Sitemap Found', 'ai-seo-client'); ?></strong>
                                        <div class="sitemap-url">
                                            <a href="<?php echo esc_url($sitemapUrl); ?>" target="_blank"><?php echo esc_html($sitemapUrl); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <p style="margin-top: 20px;">
                                <button type="button" id="run-sitemap-check" class="button button-primary">
                                    <?php esc_html_e('Run Full Sitemap Health Check', 'ai-seo-client'); ?>
                                </button>
                                <span class="spinner" style="float: none; margin-left: 10px;"></span>
                            </p>
                            
                            <div id="sitemap-check-results" style="margin-top: 30px;"></div>
                            
                            <script>
                            jQuery(document).ready(function($) {
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
                                            html += '<div style="font-size: 32px; font-weight: bold; color: #2563eb;">' + (sitemap.total_urls || 0) + '</div>';
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
                                        results.html('<div style="background: #fee2e2; padding: 15px; border-radius: 6px; border-left: 4px solid #d63638;"><strong><?php echo esc_js(__('Error:', 'ai-seo-client')); ?></strong> ' + (error.message || '<?php echo esc_js(__('Unknown error', 'ai-seo-client')); ?>') + '</div>');
                                        btn.prop('disabled', false);
                                        spinner.removeClass('is-active');
                                    });
                                });
                            });
                            </script>
                        <?php else: ?>
                            <div class="sitemap-status error">
                                <span style="font-size: 24px;">❌</span>
                                <div>
                                    <strong><?php esc_html_e('No Sitemap Found', 'ai-seo-client'); ?></strong>
                                    <p><?php esc_html_e('Neither sitemap.xml nor sitemap_index.xml could be found.', 'ai-seo-client'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Extended Sitemaps Info -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Extended Sitemaps', 'ai-seo-client'); ?></h2>
                        <p><?php esc_html_e('The plugin automatically generates the following sitemap types:', 'ai-seo-client'); ?></p>
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li><strong><?php esc_html_e('Main Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap.xml</code></li>
                            <li><strong><?php esc_html_e('RSS Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-rss.xml</code></li>
                            <li><strong><?php esc_html_e('Video Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-videos.xml</code></li>
                            <li><strong><?php esc_html_e('News Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-news.xml</code></li>
                            <li><strong><?php esc_html_e('Image Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-images.xml</code></li>
                            <li><strong><?php esc_html_e('Author Sitemap:', 'ai-seo-client'); ?></strong> <code>sitemap-authors.xml</code></li>
                        </ul>
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
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
            .ai-tool-card { display: block; background: #fff; border: 2px solid #e5e7eb; border-radius: 6px; padding: 24px; transition: all .2s ease; }
            .ai-tool-card:hover { border-color: #FF4D00; transform: translateY(-4px); box-shadow: 0 10px 20px rgba(255, 77, 0, 0.15); }
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
        
        // Get rate limit status
        $rateLimitStatus = $this->getRateLimitStatus();
        
        // Check for success message
        $success = isset($_GET['settings-updated']) && $_GET['settings-updated'] === '1';
        
        ?>
        <style>
            /* Critical layout CSS */
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-settings-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 40px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); max-width: 900px; margin: 0 auto; }
            .sseo-ai-notice { padding: 16px 20px; border-radius: 6px; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
            .sseo-ai-notice-success { background: #d1fae5; color: #10b981; border-left: 4px solid #10b981; }
            .settings-section { margin-bottom: 40px; padding-bottom: 40px; border-bottom: 2px solid #f3f4f6; }
            .settings-section h2 { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 8px 0; }
            .form-field { margin-bottom: 24px; }
            .form-field label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
            .form-field input, .form-field select, .form-field textarea { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px; }
            .form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color: #FF4D00; outline: none; box-shadow: 0 0 0 3px rgba(255, 77, 0, 0.1); }
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
     * Handle manual license validation
     */
    public function handleManualValidation(): void
    {
        // Debug logging
        error_log('SSEO AI Manual Validation: Handler called');
        error_log('SSEO AI Manual Validation: POST data = ' . print_r($_POST, true));
        error_log('SSEO AI Manual Validation: User ID = ' . get_current_user_id());
        error_log('SSEO AI Manual Validation: Can manage_options = ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'manual_validate_license')) {
            error_log('SSEO AI Manual Validation: Nonce verification failed');
            wp_die(__('Security check failed. Please refresh the page and try again.', 'ai-seo-client'));
        }
        
        error_log('SSEO AI Manual Validation: Nonce verified successfully');

        if (!current_user_can('manage_options') && !current_user_can('activate_plugins')) {
            error_log('SSEO AI Manual Validation: User lacks required capability');
            wp_die(__('You need administrator permissions to validate the license.', 'ai-seo-client'));
        }
        
        error_log('SSEO AI Manual Validation: Permission check passed');
        
        // Clear validation cache and force re-validation
        $licenseKey = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        $cacheKey = 'ai_seo_license_check_' . md5($licenseKey);
        delete_transient($cacheKey);
        
        error_log('SSEO AI Manual Validation: Running validation...');
        
        // Trigger validation
        $this->licenseValidator->validateStoredLicense();
        
        error_log('SSEO AI Manual Validation: Validation complete, redirecting...');
        
        wp_redirect(admin_url('admin.php?page=ai-seo-client&validated=1'));
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
        $licenseType = get_option('sseo_ai_client_license_type', 'paid');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        
        // Mask the keys for display
        $maskedLicenseKey = !empty($licenseKey) ? substr($licenseKey, 0, 12) . str_repeat('*', 20) . substr($licenseKey, -8) : '';
        $maskedTenantKey = !empty($tenantKey) ? substr($tenantKey, 0, 8) . str_repeat('*', 20) . substr($tenantKey, -8) : '';
        
        ?>
        <style>
            /* Critical layout CSS */
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-connection-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 60px; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); text-align: center; }
            .sseo-ai-connection-card h2 { font-size: 32px; font-weight: 700; color: #111827; margin: 0 0 20px 0; }
            .sseo-ai-connection-card .highlight { background: linear-gradient(135deg, #3b82f6 0%, #FF4D00 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            .connection-details { text-align: left; margin-top: 40px; padding-top: 30px; border-top: 2px solid #f3f4f6; }
            .detail-item { margin-bottom: 20px; }
            .detail-item label { display: block; font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; margin-bottom: 6px; }
            .detail-item .detail-value { font-size: 16px; color: #111827; font-family: 'Courier New', monospace; background: #f9fafb; padding: 12px 16px; border-radius: 6px; border: 1px solid #e5e7eb; }
            .connection-form { text-align: left; margin-top: 30px; }
            .connection-form .form-field { margin-bottom: 20px; }
            .connection-form label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px; }
            .connection-form input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px; }
            .connection-form input:focus { border-color: #FF4D00; outline: none; box-shadow: 0 0 0 3px rgba(255, 77, 0, 0.1); }
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
        update_option('sseo_ai_client_license_type', $result['type'] ?? 'paid');
        update_option('sseo_ai_client_license_expires', $result['expires_at'] ?? '');
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
        delete_option('sseo_ai_client_license_type');
        delete_option('sseo_ai_client_license_expires');
        delete_option('sseo_ai_client_image_api');

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
        ?>
        <style>
            .sseo-upgrade-wrap { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-upgrade-header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); 
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
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 12px 30px;
                border-radius: 50px;
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 30px;
                box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);
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
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-client')); ?>" class="sseo-upgrade-cta">
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
}

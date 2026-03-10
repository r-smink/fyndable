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
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Handle license activation form
        add_action('admin_post_ai_seo_activate_license', [$this, 'handleLicenseActivation']);
        add_action('admin_post_ai_seo_deactivate_license', [$this, 'handleLicenseDeactivation']);

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
        
        $this->sitemapGenerator = new SitemapGenerator(AISEO_CLIENT_PLUGIN_FILE, $this->settings);
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
        add_menu_page(
            __('AI SEO Client', 'ai-seo-client'),
            __('AI SEO', 'ai-seo-client'),
            'manage_options',
            'ai-seo-client',
            [$this, 'renderLicensePage'],
            'dashicons-search',
            30
        );

        add_submenu_page(
            'ai-seo-client',
            __('License Activation', 'ai-seo-client'),
            __('License', 'ai-seo-client'),
            'manage_options',
            'ai-seo-client',
            [$this, 'renderLicensePage']
        );

        // Only show features menu if license is active
        if ($this->licenseValidator->isLicenseValid()) {
            $tier = $this->licenseValidator->getLicenseTier();
            
            add_submenu_page(
                'ai-seo-client',
                __('SEO Dashboard', 'ai-seo-client'),
                __('Dashboard', 'ai-seo-client'),
                'manage_options',
                'ai-seo-dashboard',
                [$this, 'renderDashboardPage']
            );
        }
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
            <h1><?php esc_html_e('AI SEO License Activation', 'ai-seo-client'); ?></h1>

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
                                           placeholder="AISEO-XXXX-XXXX-XXXX-XXXX" required>
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
     * Render dashboard page (only shown when licensed)
     */
    public function renderDashboardPage(): void
    {
        if ($this->seoDashboard) {
            $this->seoDashboard->renderPage();
        }
    }

    /**
     * Handle license activation form submission
     */
    public function handleLicenseActivation(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'activate_license')) {
            wp_die(__('Security check failed', 'ai-seo-client'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'ai-seo-client'));
        }

        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        $dashboardUrl = esc_url_raw($_POST['dashboard_url'] ?? '');

        if (empty($licenseKey) || empty($dashboardUrl)) {
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=missing_fields'));
            exit;
        }

        // Store dashboard URL
        update_option('sseo_ai_client_dashboard_url', $dashboardUrl);

        // Attempt activation
        $result = $this->dashboardAPI->activateLicense($licenseKey, $dashboardUrl);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . urlencode($result->get_error_message())));
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

        wp_redirect(admin_url('admin.php?page=ai-seo-client&activated=1'));
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

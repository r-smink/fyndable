<?php

namespace SSEOAISaaS;

/**
 * SaaS Dashboard Main Class
 * 
 * Manages all license and tenant functionality for the SaaS platform.
 * This plugin runs on the main SaaS portal/website.
 */
class Dashboard
{
    private string $pluginFile;
    private TenantRepository $tenants;
    private LicenseKeyGenerator $licenseGenerator;
    private LicenseAdmin $licenseAdmin;
    private LicenseAPI $licenseAPI;
    private SaaSSettings $saasSettings;
    private ApiGateway $apiGateway;
    private WhiteLabelAdmin $whiteLabelAdmin;
    private PaymentProcessor $paymentProcessor;
    private WebhookHandler $webhookHandler;
    private SupportTickets $supportTickets;
    private SupportAdmin $supportAdmin;
    private SaaSDashboardShell $dashboardShell;
    private EmailTemplateRepository $emailTemplateRepository;
    private EmailTemplateAdmin $emailTemplateAdmin;
    private EmailAutomation $emailAutomation;
    private UpdateServer $updateServer;
    private SignupCheckout $signupCheckout;
    private RevenueDashboard $revenueDashboard;
    private ProviderRouter $providerRouter;
    private AgencyRoleManager $agencyRoleManager;
    private AgencyPortal $agencyPortal;
    private FyndableLogin $fyndableLogin;
    private GeoScanRepository $geoScanRepository;
    private HtmlFetcher $htmlFetcher;
    private AiOverviewExtractor $aiOverviewExtractor;
    private GeoScanner $geoScanner;
    private GeoScanReport $geoScanReport;
    private GeoScanAdmin $geoScanAdmin;

    public function __construct()
    {
        $this->pluginFile = SSEO_AI_SAAS_PLUGIN_FILE;
    }

    public function init(): void
    {
        // Initialize core services
        $this->tenants = new TenantRepository();
        $this->tenants->maybeCreateTables();
        $this->tenants->migrateExistingTables();

        $this->licenseGenerator = new LicenseKeyGenerator($this->tenants);
        $this->licenseAdmin = new LicenseAdmin($this->pluginFile, $this->licenseGenerator, $this->tenants);
        $this->licenseAPI = new LicenseAPI($this->licenseGenerator, $this->tenants);
        $this->saasSettings = new SaaSSettings();
        add_action('phpmailer_init', [$this->saasSettings, 'configureMailer']);
        add_filter('wp_mail_from', [$this->saasSettings, 'getSmtpFromEmail']);
        add_filter('wp_mail_from_name', [$this->saasSettings, 'getSmtpFromName']);
        $this->providerRouter = new ProviderRouter($this->saasSettings);
        $this->apiGateway = new ApiGateway($this->tenants, $this->saasSettings, $this->providerRouter);
        $this->whiteLabelAdmin = new WhiteLabelAdmin($this->tenants);
        $this->paymentProcessor = new PaymentProcessor($this->tenants);
        $this->webhookHandler = new WebhookHandler($this->paymentProcessor, $this->tenants);
        $this->emailTemplateRepository = new EmailTemplateRepository();
        $this->emailTemplateRepository->maybeCreateTables();
        add_action('init', [$this->emailTemplateRepository, 'seedDefaults'], 20);

        $this->supportTickets = new SupportTickets($this->tenants, $this->emailTemplateRepository);
        $this->supportAdmin = new SupportAdmin($this->tenants, $this->supportTickets);
        $this->dashboardShell = new SaaSDashboardShell($this->pluginFile);
        $this->emailTemplateAdmin = new EmailTemplateAdmin($this->emailTemplateRepository, new EmailTemplateRenderer($this->emailTemplateRepository, $this->tenants));
        $this->emailAutomation = new EmailAutomation($this->tenants, $this->emailTemplateRepository);
        $this->updateServer = new UpdateServer($this->tenants);
        $this->signupCheckout = new SignupCheckout($this->tenants, $this->licenseGenerator, $this->paymentProcessor, $this->emailAutomation);
        $this->revenueDashboard = new RevenueDashboard($this->tenants);

        // Agency portal
        $this->agencyRoleManager = new AgencyRoleManager($this->tenants);
        $this->agencyPortal = new AgencyPortal(
            $this->pluginFile,
            $this->tenants,
            $this->licenseGenerator,
            $this->supportTickets,
            $this->agencyRoleManager
        );
        $this->fyndableLogin = new FyndableLogin($this->tenants, $this->agencyRoleManager);

        $this->geoScanRepository = new GeoScanRepository();
        $this->htmlFetcher = new HtmlFetcher($this->saasSettings);
        $this->aiOverviewExtractor = new AiOverviewExtractor($this->saasSettings);
        $this->geoScanner = new GeoScanner(
            $this->htmlFetcher,
            $this->aiOverviewExtractor,
            $this->providerRouter,
            $this->saasSettings,
            $this->geoScanRepository
        );
        $this->geoScanReport = new GeoScanReport($this->geoScanRepository);
        $this->geoScanAdmin = new GeoScanAdmin(
            $this->pluginFile,
            $this->geoScanner,
            $this->geoScanRepository,
            $this->geoScanReport
        );

        // Register dashboard shell (top-level menu)
        add_action('admin_menu', [$this, 'registerShellMenu']);
        add_action('admin_head', [$this->dashboardShell, 'hideWpChrome']);

        // Register admin menu (existing submenus under sseo-ai-licenses)
        add_action('admin_menu', [$this->licenseAdmin, 'register']);
        add_action('admin_menu', [$this->saasSettings, 'addSettingsMenu']);
        add_action('admin_menu', [$this->whiteLabelAdmin, 'addMenu']);
        add_action('admin_menu', [$this->supportAdmin, 'register']);
        add_action('admin_menu', [$this->emailTemplateAdmin, 'addMenu']);
        add_action('admin_menu', [$this->geoScanAdmin, 'register']);
        add_action('admin_enqueue_scripts', [$this->licenseAdmin, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this->whiteLabelAdmin, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this->geoScanAdmin, 'enqueueAssets']);

        // Register REST API for client plugin communication
        add_action('rest_api_init', [$this->licenseAPI, 'register']);
        add_action('rest_api_init', [$this->apiGateway, 'register']);
        add_action('rest_api_init', [$this->webhookHandler, 'register']);
        add_action('rest_api_init', [$this->supportTickets, 'registerRoutes']);
        add_action('rest_api_init', [$this->updateServer, 'register']);

        // Register self-serve signup (REST + shortcode)
        $this->signupCheckout->register();

        // Register revenue dashboard
        $this->revenueDashboard->register();
        
        // Register agency portal
        $this->agencyRoleManager->register();
        $this->agencyPortal->register();
        $this->fyndableLogin->register();
        
        // Register settings
        add_action('admin_init', [$this->saasSettings, 'registerSettings']);
        add_action('admin_init', [$this->whiteLabelAdmin, 'registerSettings']);
        add_action('admin_init', [$this->geoScanRepository, 'maybeCreateTables']);

        // Register email automation
        $this->emailAutomation->register();

        // Register update server settings
        add_action('admin_init', [$this->updateServer, 'registerSettings']);

        // Register activation hook for table creation
        register_activation_hook($this->pluginFile, [$this, 'activate']);
    }

    public function activate(): void
    {
        $this->tenants->maybeCreateTables();
        $this->tenants->migrateExistingTables();
        $this->geoScanRepository->maybeCreateTables();
        $this->emailTemplateRepository->maybeCreateTables();
        $this->emailTemplateRepository->seedDefaults();
    }

    /**
     * Register the SaaS dashboard shell as the main top-level menu.
     * The existing license menu becomes a hidden submenu (accessed via iframe).
     */
    public function registerShellMenu(): void
    {
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';

        $user = wp_get_current_user();
        $isAgency = $user && in_array('agency_partner', (array)$user->roles, true);
        if ($isAgency) {
            $wl = get_user_meta($user->ID, 'sseo_ai_agency_wl', true);
            if (is_array($wl) && !empty($wl['company_name'])) {
                $companyName = $wl['company_name'];
            }
        }

        $menuName = $companyName ? $companyName . ' SaaS' : 'Fyndable SaaS';
        $capability = $isAgency ? 'agency_view_dashboard' : 'manage_options';

        add_menu_page(
            $menuName,
            $menuName,
            $capability,
            'sseo-ai-shell',
            [$this->dashboardShell, 'render'],
            'dashicons-analytics',
            3
        );
    }
}

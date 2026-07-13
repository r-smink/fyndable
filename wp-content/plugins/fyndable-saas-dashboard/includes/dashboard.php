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
        $this->apiGateway = new ApiGateway($this->tenants, $this->saasSettings);
        $this->whiteLabelAdmin = new WhiteLabelAdmin($this->tenants);
        $this->paymentProcessor = new PaymentProcessor($this->tenants);
        $this->webhookHandler = new WebhookHandler($this->paymentProcessor, $this->tenants);
        $this->supportTickets = new SupportTickets($this->tenants);
        $this->supportAdmin = new SupportAdmin($this->tenants, $this->supportTickets);
        $this->dashboardShell = new SaaSDashboardShell($this->pluginFile);

        // Register dashboard shell (top-level menu)
        add_action('admin_menu', [$this, 'registerShellMenu']);
        add_action('admin_head', [$this->dashboardShell, 'hideWpChrome']);

        // Register admin menu (existing submenus under sseo-ai-licenses)
        add_action('admin_menu', [$this->licenseAdmin, 'register']);
        add_action('admin_menu', [$this->saasSettings, 'addSettingsMenu']);
        add_action('admin_menu', [$this->whiteLabelAdmin, 'addMenu']);
        add_action('admin_menu', [$this->supportAdmin, 'register']);
        add_action('admin_enqueue_scripts', [$this->licenseAdmin, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this->whiteLabelAdmin, 'enqueueAssets']);

        // Register REST API for client plugin communication
        add_action('rest_api_init', [$this->licenseAPI, 'register']);
        add_action('rest_api_init', [$this->apiGateway, 'register']);
        add_action('rest_api_init', [$this->webhookHandler, 'register']);
        add_action('rest_api_init', [$this->supportTickets, 'registerRoutes']);
        
        // Register settings
        add_action('admin_init', [$this->saasSettings, 'registerSettings']);
        add_action('admin_init', [$this->whiteLabelAdmin, 'registerSettings']);

        // Register activation hook for table creation
        register_activation_hook($this->pluginFile, [$this, 'activate']);
    }

    public function activate(): void
    {
        $this->tenants->maybeCreateTables();
        $this->tenants->migrateExistingTables();
    }

    /**
     * Register the SaaS dashboard shell as the main top-level menu.
     * The existing license menu becomes a hidden submenu (accessed via iframe).
     */
    public function registerShellMenu(): void
    {
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $menuName = $companyName ? $companyName . ' SaaS' : 'Fyndable SaaS';

        add_menu_page(
            $menuName,
            $menuName,
            'manage_options',
            'sseo-ai-shell',
            [$this->dashboardShell, 'render'],
            'dashicons-analytics',
            3
        );
    }
}

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

        // Register admin menu
        add_action('admin_menu', [$this->licenseAdmin, 'register']);
        add_action('admin_menu', [$this->saasSettings, 'addSettingsMenu']);
        add_action('admin_menu', [$this->whiteLabelAdmin, 'addMenu']);
        add_action('admin_enqueue_scripts', [$this->licenseAdmin, 'enqueueAssets']);
        add_action('admin_enqueue_scripts', [$this->whiteLabelAdmin, 'enqueueAssets']);

        // Register REST API for client plugin communication
        add_action('rest_api_init', [$this->licenseAPI, 'register']);
        add_action('rest_api_init', [$this->apiGateway, 'register']);
        
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
}

<?php

namespace SSEOAISaaS;

/**
 * SaaS Settings Manager
 * 
 * Manages API credentials and global settings for the SaaS Dashboard
 */
class SaaSSettings
{
    private const OPTION_PREFIX = 'ai_seo_saas_';
    
    /**
     * Register settings page
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addSettingsMenu']);
        add_action('admin_init', [$this, 'maybeMigrateClientVersions'], 5);
        add_action('admin_init', [$this, 'registerSettings']);

        add_action('phpmailer_init', [$this, 'configureMailer']);
        add_filter('wp_mail_from', [$this, 'getSmtpFromEmail']);
        add_filter('wp_mail_from_name', [$this, 'getSmtpFromName']);
    }
    
    /**
     * Add settings and checkout admin pages
     */
    public function addSettingsMenu(): void
    {
        add_action('admin_init', [$this, 'handleClientVersionsPost'], 20);

        // Checkout is accessed via the SaaS dashboard shell, not as a standalone WP menu item.
        add_submenu_page(
            'sseo-ai-licenses',
            __('Checkout', 'sseo-ai-saas'),
            __('Checkout', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-checkout',
            [$this, 'renderCheckoutPage']
        );

        add_submenu_page(
            'sseo-ai-licenses',
            __('SaaS Settings', 'sseo-ai-saas'),
            __('Settings', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-settings',
            [$this, 'renderSettingsPage']
        );
        
        add_submenu_page(
            'sseo-ai-licenses',
            __('Cost Dashboard', 'sseo-ai-saas'),
            __('Cost Dashboard', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-costs',
            [$this, 'renderCostDashboard']
        );

        add_submenu_page(
            'sseo-ai-licenses',
            __('AI Models', 'sseo-ai-saas'),
            __('AI Models', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-models',
            [$this, 'renderAiModelsPage']
        );

        add_submenu_page(
            'sseo-ai-licenses',
            __('Client Versions', 'sseo-ai-saas'),
            __('Client Versions', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-client-versions',
            [$this, 'renderClientVersionsPage']
        );

    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_openai_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_openai_model', ['default' => 'gpt-4']);
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_api_provider', ['default' => 'serpapi']);
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_dataforseo_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_serpapi_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_serp_seranking_api_key');

        // OpenRouter (multi-model gateway)
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_openrouter_api_key');
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_ai_provider', ['default' => 'openrouter']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_model_routing', ['default' => []]);

        // Image Generation API settings
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_image_api_provider', ['default' => '']);
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_image_api_key');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_image_api_model', ['default' => 'dall-e-3']);
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_openart_api_key');
        
        // Usage limits per tier
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_starter_api_calls', ['default' => 200]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_early_adopters_api_calls', ['default' => 200]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_professional_api_calls', ['default' => 1000]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_business_api_calls', ['default' => 5000]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_agency_api_calls', ['default' => 20000]);
        
        // Cost limits per tier (in USD)
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_starter_cost_limit', ['default' => 20]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_early_adopters_cost_limit', ['default' => 20]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_trial_cost_limit', ['default' => 50]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_professional_cost_limit', ['default' => 100]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_business_cost_limit', ['default' => 500]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_agency_cost_limit', ['default' => 2000]);

        // Monthly subscription price per tier (in EUR)
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_starter_price', ['default' => 29]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_early_adopters_price', ['default' => 14.5]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_trial_price', ['default' => 0]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_professional_price', ['default' => 79]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_business_price', ['default' => 199]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_agency_price', ['default' => 499]);

        // Monthly auto-scheduled post limits per tier
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_starter_auto_posts', ['default' => 15]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_early_adopters_auto_posts', ['default' => 15]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_trial_auto_posts', ['default' => 10]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_professional_auto_posts', ['default' => 35]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_business_auto_posts', ['default' => 150]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_agency_auto_posts', ['default' => 999999]);

        // Monthly GEO scan/audit limits per tier
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_starter_geo_scans', ['default' => 5]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_early_adopters_geo_scans', ['default' => 5]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_trial_geo_scans', ['default' => 5]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_professional_geo_scans', ['default' => 35]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_business_geo_scans', ['default' => 90]);
        register_setting('sseo_ai_saas_billing', 'ai_seo_saas_agency_geo_scans', ['default' => 999999]);
        
        // Billing settings - Payment providers
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_payment_provider', ['default' => 'stripe']);
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_currency', ['default' => 'EUR']);
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_self_serve_enabled', ['default' => false]);

        // Stripe settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_key');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_secret');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_stripe_webhook_secret');

        // Mollie settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_mollie_api_key');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_mollie_test_api_key');
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_mollie_mode', ['default' => 'live']);

        // Trial settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_trial_days', ['default' => 14]);
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_trial_enabled', ['default' => '1']);

        // Custom tier pricing (overrides defaults in PaymentProcessor)
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_pricing', ['default' => []]);

        // Google OAuth credentials (central, used by all client sites)
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_google_client_id');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_google_client_secret');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_google_ads_dev_token');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_support_email');

        // SMTP / Email settings
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_enabled', ['default' => false, 'sanitize_callback' => fn($v) => ($v === '1' || $v === true || $v === 1)]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_host', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_port', ['default' => 587, 'sanitize_callback' => 'absint']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_encryption', [
            'default' => 'tls',
            'sanitize_callback' => function ($value) {
                return in_array($value, ['', 'ssl', 'tls'], true) ? $value : 'tls';
            },
        ]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_auth', ['default' => true, 'sanitize_callback' => fn($v) => ($v === '1' || $v === true || $v === 1)]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_username', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_password', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_from_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_smtp_from_name', ['sanitize_callback' => 'sanitize_text_field']);

        // GEO Scan settings
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_firecrawl_api_key');
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_geo_model', ['default' => 'google/gemini-flash-1.5']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_geo_language', ['default' => 'nl']);

        // Customer portal settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_customer_portal_page', ['default' => 0, 'sanitize_callback' => 'absint']);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_vat_rate', ['default' => 21, 'sanitize_callback' => function($v) { return (float) $v; }]);

        // Model tier routing overrides
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_standard_routing', ['default' => []]);
        register_setting('ai_seo_saas_settings', 'sseo_ai_saas_premium_routing', ['default' => []]);

        // Early Adopters tier toggle
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_early_adopters_enabled', ['default' => false, 'sanitize_callback' => fn($v) => ($v === '1' || $v === true || $v === 1)]);

        // Invoice template (Bookkeeping tab 3)
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_logo_id', ['default' => 0, 'sanitize_callback' => 'absint']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_bg_id', ['default' => 0, 'sanitize_callback' => 'absint']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_bg_mode', ['default' => 'none']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_header_color', ['default' => '']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_accent_color', ['default' => '#379fd3']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_text_color', ['default' => '#111827']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_name');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_address');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_vat');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_kvk');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_iban');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_email');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_company_website');
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_prefix', ['default' => 'FYND-']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_footer_text', ['default' => 'Bedankt voor uw vertrouwen.']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_from', ['default' => 'Van']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_to', ['default' => 'Factuur aan']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_description', ['default' => 'Omschrijving']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_period', ['default' => 'Periode']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_amount', ['default' => 'Bedrag']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_subtotal', ['default' => 'Subtotaal']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_vat', ['default' => 'BTW']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_total', ['default' => 'Totaal']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_paid_on', ['default' => 'Betaald op']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_label_invoice', ['default' => 'Factuur']);
        register_setting('sseo_ai_saas_invoice_template', 'sseo_ai_saas_inv_cost_usd_eur_rate', ['default' => '0.92', 'sanitize_callback' => 'floatval']);
    }
    
    /**
     * Get OpenAI API key
     */
    public function getOpenAiApiKey(): string
    {
        return get_option('ai_seo_saas_openai_api_key', '');
    }
    
    /**
     * Get OpenAI model
     */
    public function getOpenAiModel(): string
    {
        return get_option('ai_seo_saas_openai_model', 'gpt-4');
    }
    
    /**
     * Get SERP API key
     */
    public function getSerpApiKey(): string
    {
        return get_option('ai_seo_saas_serp_api_key', '');
    }
    
    /**
     * Get SERP API provider
     */
    public function getSerpApiProvider(): string
    {
        return get_option('ai_seo_saas_serp_api_provider', 'serpapi');
    }
    
    /**
     * Get SERP API key for a specific provider.
     */
    public function getSerpApiKeyForProvider(string $provider): string
    {
        $specific = get_option('ai_seo_saas_serp_' . $provider . '_api_key', '');
        return !empty($specific) ? $specific : get_option('ai_seo_saas_serp_api_key', '');
    }
    
    /**
     * Get Image API provider
     *
     * Falls back to the default AI provider when no image provider is selected.
     */
    public function getImageApiProvider(): string
    {
        $provider = get_option('ai_seo_saas_image_api_provider', '');
        if (empty($provider)) {
            $provider = get_option('sseo_ai_saas_ai_provider', 'openrouter');
        }
        return $provider;
    }
    
    /**
     * Get Image API key
     */
    public function getImageApiKey(): string
    {
        return get_option('ai_seo_saas_image_api_key', '');
    }
    
    /**
     * Get Image API model
     */
    public function getImageApiModel(): string
    {
        return get_option('ai_seo_saas_image_api_model', 'dall-e-3');
    }

    /**
     * Get Google OAuth Client ID
     */
    public function getGoogleClientId(): string
    {
        return get_option('ai_seo_saas_google_client_id', '');
    }

    /**
     * Get Google OAuth Client Secret
     */
    public function getGoogleClientSecret(): string
    {
        return get_option('ai_seo_saas_google_client_secret', '');
    }

    /**
     * Get support email address
     */
    public function getSupportEmail(): string
    {
        return get_option('ai_seo_saas_support_email', get_option('admin_email'));
    }

    /**
     * Get Firecrawl API key (optional HTML extraction fallback)
     */
    public function getFirecrawlApiKey(): string
    {
        return get_option('sseo_ai_saas_firecrawl_api_key', '');
    }

    /**
     * Get GEO Scan model
     */
    public function getGeoModel(): string
    {
        return get_option('sseo_ai_saas_geo_model', 'google/gemini-flash-1.5');
    }

    /**
     * Get default GEO Scan language
     */
    public function getGeoLanguage(): string
    {
        return get_option('sseo_ai_saas_geo_language', 'nl');
    }

    /**
     * Get Google Ads Developer Token
     */
    public function getGoogleAdsDevToken(): string
    {
        return get_option('ai_seo_saas_google_ads_dev_token', '');
    }

    /**
     * Configure PHPMailer to use the configured SMTP server.
     */
    public function configureMailer($phpmailer): void
    {
        if (!get_option('sseo_ai_saas_smtp_enabled')) {
            return;
        }

        $host = get_option('sseo_ai_saas_smtp_host', '');
        if (empty($host)) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = (int) get_option('sseo_ai_saas_smtp_port', 587);
        $phpmailer->SMTPAuth = (bool) get_option('sseo_ai_saas_smtp_auth', true);

        if ($phpmailer->SMTPAuth) {
            $phpmailer->Username = get_option('sseo_ai_saas_smtp_username', '');
            $phpmailer->Password = get_option('sseo_ai_saas_smtp_password', '');
        }

        $encryption = get_option('sseo_ai_saas_smtp_encryption', 'tls');
        $phpmailer->SMTPSecure = in_array($encryption, ['ssl', 'tls'], true) ? $encryption : '';
    }

    /**
     * Get the configured SMTP from email, if any.
     */
    public function getSmtpFromEmail(string $email): string
    {
        $configured = get_option('sseo_ai_saas_smtp_from_email', '');
        return !empty($configured) ? $configured : $email;
    }

    /**
     * Get the configured SMTP from name, if any.
     */
    public function getSmtpFromName(string $name): string
    {
        $configured = get_option('sseo_ai_saas_smtp_from_name', '');
        return !empty($configured) ? $configured : $name;
    }
    
    /**
     * Get API call limit for tier
     */
    public function getApiLimitForTier(string $tier): int
    {
        $optionName = "ai_seo_saas_{$tier}_api_calls";
        return (int)get_option($optionName, $this->getDefaultApiLimit($tier));
    }
    
    /**
     * Get cost limit for tier (USD)
     */
    public function getCostLimitForTier(string $tier): float
    {
        $optionName = "ai_seo_saas_{$tier}_cost_limit";
        return (float)get_option($optionName, $this->getDefaultCostLimit($tier));
    }
    
    /**
     * Default API limits
     */
    private function getDefaultApiLimit(string $tier): int
    {
        $defaults = [
            'starter' => 200,
            'early_adopters' => 200,
            'trial' => 500,
            'professional' => 1000,
            'business' => 5000,
            'agency' => 20000,
        ];
        return $defaults[$tier] ?? 100;
    }
    
    /**
     * Default cost limits (USD)
     */
    private function getDefaultCostLimit(string $tier): float
    {
        $defaults = [
            'starter' => 20,
            'early_adopters' => 20,
            'trial' => 50,
            'professional' => 100,
            'business' => 500,
            'agency' => 2000,
        ];
        return $defaults[$tier] ?? 10;
    }

    /**
     * Get auto-scheduled posts limit for tier
     */
    public function getAutoPostLimitForTier(string $tier): int
    {
        $optionName = "ai_seo_saas_{$tier}_auto_posts";
        return (int)get_option($optionName, $this->getDefaultAutoPostLimit($tier));
    }

    /**
     * Default auto-scheduled post limits per tier
     */
    private function getDefaultAutoPostLimit(string $tier): int
    {
        $defaults = [
            'starter' => 15,
            'early_adopters' => 15,
            'trial' => 10,
            'professional' => 35,
            'business' => 150,
            'agency' => 999999,
        ];
        return $defaults[$tier] ?? 0;
    }

    /**
     * Get GEO scan/audit limit for tier
     */
    public function getGeoScanLimitForTier(string $tier): int
    {
        $optionName = "ai_seo_saas_{$tier}_geo_scans";
        return (int)get_option($optionName, $this->getDefaultGeoScanLimit($tier));
    }

    /**
     * Default GEO scan/audit limits per tier
     */
    private function getDefaultGeoScanLimit(string $tier): int
    {
        $defaults = [
            'starter' => 5,
            'early_adopters' => 5,
            'trial' => 5,
            'professional' => 35,
            'business' => 90,
            'agency' => 999999,
        ];
        return $defaults[$tier] ?? 0;
    }

    /**
     * Get monthly subscription price for tier (EUR)
     */
    public function getPriceForTier(string $tier): float
    {
        $optionName = "ai_seo_saas_{$tier}_price";
        return (float)get_option($optionName, $this->getDefaultPrice($tier));
    }

    /**
     * Default monthly prices (EUR)
     */
    private function getDefaultPrice(string $tier): float
    {
        $defaults = [
            'starter' => 29,
            'early_adopters' => 14.5,
            'trial' => 0,
            'professional' => 79,
            'business' => 199,
            'agency' => 499,
        ];
        return $defaults[$tier] ?? 0;
    }

    /**
     * Get all tier definitions with current settings.
     */
    public function getAllTiers(): array
    {
        $tiers = ['starter', 'early_adopters', 'trial', 'professional', 'business', 'agency'];
        $result = [];
        foreach ($tiers as $tier) {
            $result[$tier] = [
                'name' => ucfirst($tier),
                'price' => $this->getPriceForTier($tier),
                'api_calls' => $this->getApiLimitForTier($tier),
                'cost_limit' => $this->getCostLimitForTier($tier),
                'auto_posts' => $this->getAutoPostLimitForTier($tier),
                'geo_scans' => $this->getGeoScanLimitForTier($tier),
            ];
        }
        return $result;
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage(): void
    {
        $isOpenRouter = get_option('sseo_ai_saas_ai_provider', 'openrouter') === 'openrouter';
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('SSEO AI SaaS Settings', 'sseo-ai-saas'); ?></h1>

            <style>
                .sseo-ai-tab-panel { display: none; }
                .sseo-ai-tab-panel.active { display: block; }
                .nav-tab-active { background: #fff; border-bottom: 1px solid #fff; }
            </style>

            <h2 class="nav-tab-wrapper sseo-ai-settings-tabs">
                <a href="#tab-api-credentials" class="nav-tab nav-tab-active" data-tab="api-credentials"><?php esc_html_e('API Credentials', 'sseo-ai-saas'); ?></a>
                <a href="#tab-image-generation" class="nav-tab" data-tab="image-generation"><?php esc_html_e('Image Generation', 'sseo-ai-saas'); ?></a>
                <a href="#tab-integrations" class="nav-tab" data-tab="integrations"><?php esc_html_e('Integrations', 'sseo-ai-saas'); ?></a>
                <a href="#tab-email-smtp" class="nav-tab" data-tab="email-smtp"><?php esc_html_e('Email / SMTP', 'sseo-ai-saas'); ?></a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('ai_seo_saas_settings'); ?>

                <div class="sseo-ai-tab-panel active" id="tab-api-credentials">
                    <h2><?php esc_html_e('API Credentials', 'sseo-ai-saas'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="ai_provider"><?php esc_html_e('Default AI Provider', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_ai_provider" id="ai_provider">
                                    <option value="openrouter" <?php selected(get_option('sseo_ai_saas_ai_provider', 'openrouter'), 'openrouter'); ?>><?php esc_html_e('OpenRouter (recommended)', 'sseo-ai-saas'); ?></option>
                                    <option value="openai" <?php selected(get_option('sseo_ai_saas_ai_provider', 'openrouter'), 'openai'); ?>><?php esc_html_e('OpenAI (direct)', 'sseo-ai-saas'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('OpenRouter routes to many providers. OpenAI direct uses only OpenAI models.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="openrouter_api_key"><?php esc_html_e('OpenRouter API Key', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="sseo_ai_saas_openrouter_api_key" id="openrouter_api_key"
                                       value="<?php echo esc_attr(get_option('sseo_ai_saas_openrouter_api_key', '')); ?>" class="regular-text"
                                       placeholder="<?php esc_attr_e('sk-or-v1-...', 'sseo-ai-saas'); ?>">
                                <p class="description">
                                    <?php esc_html_e('Get your API key at', 'sseo-ai-saas'); ?>
                                    <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai/keys</a>
                                </p>
                            </td>
                        </tr>
                        <tr class="sseo-ai-openai-only"<?php if ($isOpenRouter) echo ' style="display:none;"'; ?>>
                            <th scope="row"><label for="openai_api_key"><?php esc_html_e('OpenAI API Key', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="ai_seo_saas_openai_api_key" id="openai_api_key"
                                       value="<?php echo esc_attr($this->getOpenAiApiKey()); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Your OpenAI API key for AI content generation. Keep this secret!', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr class="sseo-ai-openai-only"<?php if ($isOpenRouter) echo ' style="display:none;"'; ?>>
                            <th scope="row"><label for="openai_model"><?php esc_html_e('OpenAI Model', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="ai_seo_saas_openai_model" id="openai_model">
                                    <option value="gpt-4" <?php selected($this->getOpenAiModel(), 'gpt-4'); ?>>GPT-4 (Best quality, higher cost)</option>
                                    <option value="gpt-4-turbo" <?php selected($this->getOpenAiModel(), 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                                    <option value="gpt-3.5-turbo" <?php selected($this->getOpenAiModel(), 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Cheaper, faster)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="serp_api_key"><?php esc_html_e('SERP API Key', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="ai_seo_saas_serp_api_key" id="serp_api_key"
                                       value="<?php echo esc_attr($this->getSerpApiKey()); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('API key for SERP data (DataForSEO, SerpApi, etc.)', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="serp_api_provider"><?php esc_html_e('SERP Provider', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="ai_seo_saas_serp_api_provider" id="serp_api_provider">
                                    <option value="dataforseo" <?php selected($this->getSerpApiProvider(), 'dataforseo'); ?>>DataForSEO</option>
                                    <option value="serpapi" <?php selected($this->getSerpApiProvider(), 'serpapi'); ?>>SerpApi</option>
                                    <option value="seranking" <?php selected($this->getSerpApiProvider(), 'seranking'); ?>>SE Ranking</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_geo_firecrawl_key"><?php esc_html_e('Firecrawl API Key', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="sseo_ai_saas_firecrawl_api_key" id="sseo_geo_firecrawl_key"
                                       value="<?php echo esc_attr($this->getFirecrawlApiKey()); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e('Optional fallback for HTML extraction when Jina Reader fails.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_geo_model"><?php esc_html_e('GEO Scan Model', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_geo_model" id="sseo_geo_model">
                                    <?php foreach (\SSEOAISaaS\ProviderRouter::getAvailableModels() as $modelKey => $modelLabel): ?>
                                        <option value="<?php echo esc_attr($modelKey); ?>" <?php selected($this->getGeoModel(), $modelKey); ?>><?php echo esc_html($modelLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_geo_language"><?php esc_html_e('Default GEO Scan Language', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_geo_language" id="sseo_geo_language">
                                    <option value="nl" <?php selected($this->getGeoLanguage(), 'nl'); ?>><?php esc_html_e('Dutch', 'sseo-ai-saas'); ?></option>
                                    <option value="en" <?php selected($this->getGeoLanguage(), 'en'); ?>><?php esc_html_e('English', 'sseo-ai-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sseo-ai-tab-panel" id="tab-image-generation">
                    <h2><?php esc_html_e('AI Image Generation API', 'sseo-ai-saas'); ?></h2>
                    <p class="description">
                        <?php if ($isOpenRouter): ?>
                            <?php esc_html_e('Image generation is routed through OpenRouter using the selected model below.', 'sseo-ai-saas'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Choose the image generation service for all tenants.', 'sseo-ai-saas'); ?>
                        <?php endif; ?>
                    </p>
                    <table class="form-table">
                        <tr class="sseo-ai-image-provider-row"<?php if ($isOpenRouter) echo ' style="display:none;"'; ?>>
                            <th scope="row"><label for="image_api_provider"><?php esc_html_e('Image API Provider', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="ai_seo_saas_image_api_provider" id="image_api_provider">
                                    <option value=""><?php esc_html_e('-- Use default AI provider --', 'sseo-ai-saas'); ?></option>
                                    <option value="openai" <?php selected(get_option('ai_seo_saas_image_api_provider', ''), 'openai'); ?>><?php esc_html_e('OpenAI DALL-E 3', 'sseo-ai-saas'); ?></option>
                                    <option value="openrouter" <?php selected(get_option('ai_seo_saas_image_api_provider', ''), 'openrouter'); ?>><?php esc_html_e('OpenRouter (Multi-Provider Image API)', 'sseo-ai-saas'); ?></option>
                                    <option value="stability" <?php selected(get_option('ai_seo_saas_image_api_provider', ''), 'stability'); ?>><?php esc_html_e('Stability AI (Stable Diffusion)', 'sseo-ai-saas'); ?></option>
                                    <option value="openart" <?php selected(get_option('ai_seo_saas_image_api_provider', ''), 'openart'); ?>><?php esc_html_e('OpenArt (Flux Models)', 'sseo-ai-saas'); ?></option>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose AI image generation service for all tenants. Leave empty to use the default AI provider.', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr class="sseo-ai-image-key-row"<?php if ($isOpenRouter) echo ' style="display:none;"'; ?>>
                            <th scope="row"><label for="image_api_key"><?php esc_html_e('Image API Key', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="ai_seo_saas_image_api_key" id="image_api_key"
                                       value="<?php echo esc_attr($this->getImageApiKey()); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Get your key:', 'sseo-ai-saas'); ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a> |
                                    <a href="https://platform.stability.ai/account/keys" target="_blank">Stability AI</a> |
                                    <a href="https://openart.ai/api" target="_blank">OpenArt</a>
                                </p>
                            </td>
                        </tr>
                        <tr class="sseo-ai-image-key-row"<?php if ($isOpenRouter) echo ' style="display:none;"'; ?>>
                            <th scope="row"><label for="openart_api_key"><?php esc_html_e('OpenArt API Key (Flux)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="ai_seo_saas_openart_api_key" id="openart_api_key"
                                       value="<?php echo esc_attr(get_option('ai_seo_saas_openart_api_key', '')); ?>" class="regular-text"
                                       placeholder="<?php esc_attr_e('OpenArt API key for Flux models', 'sseo-ai-saas'); ?>">
                                <p class="description"><?php esc_html_e('Only required when OpenArt is selected as image provider.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="image_api_model"><?php esc_html_e('Default Model', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="ai_seo_saas_image_api_model" id="image_api_model">
                                    <optgroup label="<?php esc_attr_e('OpenAI DALL-E', 'sseo-ai-saas'); ?>">
                                        <option value="dall-e-3" <?php selected($this->getImageApiModel(), 'dall-e-3'); ?>><?php esc_html_e('DALL-E 3 Standard (1024x1024)', 'sseo-ai-saas'); ?></option>
                                        <option value="dall-e-3-hd" <?php selected($this->getImageApiModel(), 'dall-e-3-hd'); ?>><?php esc_html_e('DALL-E 3 HD (1024x1024)', 'sseo-ai-saas'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('OpenRouter Image API', 'sseo-ai-saas'); ?>">
                                        <option value="openai/gpt-image-2" <?php selected($this->getImageApiModel(), 'openai/gpt-image-2'); ?>><?php esc_html_e('OpenAI GPT Image 2 (OpenRouter)', 'sseo-ai-saas'); ?></option>
                                        <option value="openai/gpt-image-1" <?php selected($this->getImageApiModel(), 'openai/gpt-image-1'); ?>><?php esc_html_e('OpenAI GPT Image 1 (OpenRouter)', 'sseo-ai-saas'); ?></option>
                                        <option value="bytedance-seed/seedream-4.5" <?php selected($this->getImageApiModel(), 'bytedance-seed/seedream-4.5'); ?>><?php esc_html_e('Seedream 4.5 (OpenRouter)', 'sseo-ai-saas'); ?></option>
                                        <option value="google/gemini-3.1-flash-image" <?php selected($this->getImageApiModel(), 'google/gemini-3.1-flash-image'); ?>><?php esc_html_e('Gemini 3.1 Flash Image (OpenRouter)', 'sseo-ai-saas'); ?></option>
                                        <option value="black-forest-labs/flux.2-pro" <?php selected($this->getImageApiModel(), 'black-forest-labs/flux.2-pro'); ?>><?php esc_html_e('FLUX.2 Pro (OpenRouter)', 'sseo-ai-saas'); ?></option>
                                        <option value="black-forest-labs/flux.2-klein-4b" <?php selected($this->getImageApiModel(), 'black-forest-labs/flux.2-klein-4b'); ?>><?php esc_html_e('FLUX.2 Klein (OpenRouter, fast)', 'sseo-ai-saas'); ?></option>
                                        <option value="sourceful/riverflow-v2.5-pro" <?php selected($this->getImageApiModel(), 'sourceful/riverflow-v2.5-pro'); ?>><?php esc_html_e('Riverflow V2.5 Pro (OpenRouter)', 'sseo-ai-saas'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Stability AI', 'sseo-ai-saas'); ?>">
                                        <option value="stable-diffusion-xl" <?php selected($this->getImageApiModel(), 'stable-diffusion-xl'); ?>><?php esc_html_e('Stable Diffusion XL 1.0', 'sseo-ai-saas'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('OpenArt Flux', 'sseo-ai-saas'); ?>">
                                        <option value="flux-1-schnell" <?php selected($this->getImageApiModel(), 'flux-1-schnell'); ?>><?php esc_html_e('Flux 1 Schnell (Fast)', 'sseo-ai-saas'); ?></option>
                                        <option value="flux-1-dev" <?php selected($this->getImageApiModel(), 'flux-1-dev'); ?>><?php esc_html_e('Flux 1 Dev (Higher quality)', 'sseo-ai-saas'); ?></option>
                                        <option value="flux-1-pro" <?php selected($this->getImageApiModel(), 'flux-1-pro'); ?>><?php esc_html_e('Flux 1 Pro (Best quality)', 'sseo-ai-saas'); ?></option>
                                    </optgroup>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Choose the image model for the selected provider. When using OpenRouter, DALL-E 3 is automatically mapped to OpenAI GPT Image 2.', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sseo-ai-tab-panel" id="tab-integrations">
                    <h2><?php esc_html_e('Google OAuth Credentials', 'sseo-ai-saas'); ?></h2>
                    <p class="description"><?php esc_html_e('Central Google Cloud OAuth app used by all client sites. Customers never see these credentials — they just click "Connect with Google".', 'sseo-ai-saas'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="google_client_id"><?php esc_html_e('Google OAuth Client ID', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="ai_seo_saas_google_client_id" id="google_client_id"
                                       value="<?php echo esc_attr($this->getGoogleClientId()); ?>" class="regular-text"
                                       placeholder="xxxxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.apps.googleusercontent.com">
                                <p class="description">
                                    <?php esc_html_e('From Google Cloud Console → APIs & Services → Credentials', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="google_client_secret"><?php esc_html_e('Google OAuth Client Secret', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="ai_seo_saas_google_client_secret" id="google_client_secret"
                                       value="<?php echo esc_attr($this->getGoogleClientSecret()); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="google_ads_dev_token"><?php esc_html_e('Google Ads Developer Token', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="ai_seo_saas_google_ads_dev_token" id="google_ads_dev_token"
                                       value="<?php echo esc_attr($this->getGoogleAdsDevToken()); ?>" class="regular-text"
                                       placeholder="Developer token from Google Ads API Center">
                                <p class="description">
                                    <?php esc_html_e('Required for Google Ads API. Apply at Google Ads → Tools → API Center.', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h2><?php esc_html_e('Support Notifications', 'sseo-ai-saas'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="support_email"><?php esc_html_e('Support Email Address', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="email" name="ai_seo_saas_support_email" id="support_email"
                                       value="<?php echo esc_attr($this->getSupportEmail()); ?>" class="regular-text"
                                       placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                                <p class="description">
                                    <?php esc_html_e('Where new support ticket notifications are sent. Defaults to the WordPress admin email.', 'sseo-ai-saas'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sseo-ai-tab-panel" id="tab-email-smtp">
                    <h2><?php esc_html_e('SMTP / Outgoing Email', 'sseo-ai-saas'); ?></h2>
                    <p class="description"><?php esc_html_e('When enabled, all wp_mail() emails sent by the SaaS and client plugins are routed through the configured SMTP server.', 'sseo-ai-saas'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="smtp_enabled"><?php esc_html_e('Enable SMTP', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="checkbox" name="sseo_ai_saas_smtp_enabled" id="smtp_enabled" value="1" <?php checked(get_option('sseo_ai_saas_smtp_enabled', false)); ?>>
                                <p class="description"><?php esc_html_e('Override the default WordPress mailer with SMTP.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_host"><?php esc_html_e('SMTP Host', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="sseo_ai_saas_smtp_host" id="smtp_host" class="regular-text" value="<?php echo esc_attr(get_option('sseo_ai_saas_smtp_host', '')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_port"><?php esc_html_e('SMTP Port', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" name="sseo_ai_saas_smtp_port" id="smtp_port" class="small-text" value="<?php echo esc_attr(get_option('sseo_ai_saas_smtp_port', 587)); ?>">
                                <p class="description"><?php esc_html_e('Common ports: 25, 465 (SSL), 587 (TLS).', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_encryption"><?php esc_html_e('Encryption', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_smtp_encryption" id="smtp_encryption">
                                    <option value="tls" <?php selected(get_option('sseo_ai_saas_smtp_encryption', 'tls'), 'tls'); ?>>TLS</option>
                                    <option value="ssl" <?php selected(get_option('sseo_ai_saas_smtp_encryption', 'tls'), 'ssl'); ?>>SSL</option>
                                    <option value="" <?php selected(get_option('sseo_ai_saas_smtp_encryption', 'tls'), ''); ?>><?php esc_html_e('None', 'sseo-ai-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_auth"><?php esc_html_e('SMTP Authentication', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="checkbox" name="sseo_ai_saas_smtp_auth" id="smtp_auth" value="1" <?php checked(get_option('sseo_ai_saas_smtp_auth', true)); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_username"><?php esc_html_e('SMTP Username', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="sseo_ai_saas_smtp_username" id="smtp_username" class="regular-text" value="<?php echo esc_attr(get_option('sseo_ai_saas_smtp_username', '')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_password"><?php esc_html_e('SMTP Password', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="password" name="sseo_ai_saas_smtp_password" id="smtp_password" class="regular-text" value="<?php echo esc_attr(get_option('sseo_ai_saas_smtp_password', '')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_from_email"><?php esc_html_e('From Email Address', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="email" name="sseo_ai_saas_smtp_from_email" id="smtp_from_email" class="regular-text" value="<?php echo esc_attr(get_option('sseo_ai_saas_smtp_from_email', '')); ?>">
                                <p class="description"><?php esc_html_e('Used as the sender address for all emails. Leave empty to use WordPress default.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smtp_from_name"><?php esc_html_e('From Name', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="sseo_ai_saas_smtp_from_name" id="smtp_from_name" class="regular-text" value="<?php echo esc_attr(get_option('sseo_ai_saas_smtp_from_name', '')); ?>">
                                <p class="description"><?php esc_html_e('Used as the sender name for all emails. Leave empty to use WordPress default.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>


                <?php submit_button(__('Save Settings', 'sseo-ai-saas')); ?>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tabs = document.querySelectorAll('.sseo-ai-settings-tabs .nav-tab');
                var panels = document.querySelectorAll('.sseo-ai-tab-panel');
                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        var target = this.getAttribute('data-tab');
                        tabs.forEach(function(t) { t.classList.remove('nav-tab-active'); });
                        panels.forEach(function(p) { p.classList.remove('active'); });
                        this.classList.add('nav-tab-active');
                        var panel = document.getElementById('tab-' + target);
                        if (panel) { panel.classList.add('active'); }
                    });
                });

                function updateProviderFields() {
                    var provider = document.getElementById('ai_provider').value;
                    var openaiRows = document.querySelectorAll('.sseo-ai-openai-only');
                    var imageProviderRows = document.querySelectorAll('.sseo-ai-image-provider-row');
                    var imageKeyRows = document.querySelectorAll('.sseo-ai-image-key-row');
                    var display = provider === 'openrouter' ? 'none' : '';
                    openaiRows.forEach(function(row) { row.style.display = display; });
                    imageProviderRows.forEach(function(row) { row.style.display = display; });
                    imageKeyRows.forEach(function(row) { row.style.display = display; });
                }
                var aiProvider = document.getElementById('ai_provider');
                if (aiProvider) {
                    aiProvider.addEventListener('change', updateProviderFields);
                    updateProviderFields();
                }
            });
            </script>
        </div>
        <?php
    }

    public function renderCheckoutPage(): void
    {
        // Revenue stats (moved here from the old Billing page).
        $tenants = $this->getAllTenantsForStats();
        $totalRevenue = 0;
        $activeSubscriptions = 0;
        $paidTenants = array_filter($tenants, fn($t) => ($t['status'] ?? '') === 'active');
        foreach ($paidTenants as $tenant) {
            $activeSubscriptions++;
            switch ($tenant['tier'] ?? 'starter') {
                case 'agency':           $totalRevenue += 499; break;
                case 'business':         $totalRevenue += 199; break;
                case 'professional':     $totalRevenue += 79; break;
                case 'early_adopters':   $totalRevenue += 14.5; break;
                case 'starter':
                default:                 $totalRevenue += 29;
            }
        }

        $webhookUrls = [
            'stripe' => rest_url('ai-seo-saas/v1/webhooks/stripe'),
            'mollie' => rest_url('ai-seo-saas/v1/webhooks/mollie'),
        ];
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Checkout', 'sseo-ai-saas'); ?></h1>

            <!-- Stats Cards (moved from Billing page) -->
            <div class="sseo-ai-stats-grid">
                <div class="stat-card">
                    <div class="stat-value" style="color: #00a32a;">€<?php echo number_format($totalRevenue, 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Monthly Revenue (MRR)', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #379fd3;"><?php echo number_format($activeSubscriptions); ?></div>
                    <div class="stat-label"><?php esc_html_e('Active Subscriptions', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format(count($tenants)); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total Clients', 'sseo-ai-saas'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">€<?php echo number_format($totalRevenue * 12, 0); ?></div>
                    <div class="stat-label"><?php esc_html_e('Est. Annual Revenue', 'sseo-ai-saas'); ?></div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('sseo_ai_saas_billing'); ?>

                <h2><?php esc_html_e('Self-Serve Checkout & Payments', 'sseo-ai-saas'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Configure Stripe and Mollie. Self-serve checkout is disabled by default until you enable it below.', 'sseo-ai-saas'); ?>
                </p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="self_serve_enabled"><?php esc_html_e('Enable Self-Serve Checkout', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="checkbox" name="sseo_ai_saas_self_serve_enabled" id="self_serve_enabled" value="1" <?php checked(get_option('sseo_ai_saas_self_serve_enabled', false)); ?>>
                            <p class="description"><?php esc_html_e('Allow visitors to sign up and pay automatically. Leave disabled for manual license beta.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="payment_provider"><?php esc_html_e('Payment Provider', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <select name="sseo_ai_saas_payment_provider" id="payment_provider">
                                <option value="stripe" <?php selected(get_option('sseo_ai_saas_payment_provider', 'stripe'), 'stripe'); ?>><?php esc_html_e('Stripe', 'sseo-ai-saas'); ?></option>
                                <option value="mollie" <?php selected(get_option('sseo_ai_saas_payment_provider', 'stripe'), 'mollie'); ?>><?php esc_html_e('Mollie', 'sseo-ai-saas'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="payment_currency"><?php esc_html_e('Currency', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <select name="sseo_ai_saas_currency" id="payment_currency">
                                <option value="EUR" <?php selected(get_option('sseo_ai_saas_currency', 'EUR'), 'EUR'); ?>><?php esc_html_e('EUR', 'sseo-ai-saas'); ?></option>
                                <option value="USD" <?php selected(get_option('sseo_ai_saas_currency', 'EUR'), 'USD'); ?>><?php esc_html_e('USD', 'sseo-ai-saas'); ?></option>
                                <option value="GBP" <?php selected(get_option('sseo_ai_saas_currency', 'EUR'), 'GBP'); ?>><?php esc_html_e('GBP', 'sseo-ai-saas'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stripe_secret"><?php esc_html_e('Stripe Secret Key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="sseo_ai_saas_stripe_secret" id="stripe_secret" value="<?php echo esc_attr(get_option('sseo_ai_saas_stripe_secret', '')); ?>" class="regular-text" placeholder="sk_...">
                            <p class="description"><?php esc_html_e('Get from Stripe Dashboard → Developers → API keys.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="stripe_webhook_secret"><?php esc_html_e('Stripe Webhook Secret', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="sseo_ai_saas_stripe_webhook_secret" id="stripe_webhook_secret" value="<?php echo esc_attr(get_option('sseo_ai_saas_stripe_webhook_secret', '')); ?>" class="regular-text" placeholder="whsec_...">
                            <p class="description">
                                <?php esc_html_e('Webhook URL:', 'sseo-ai-saas'); ?> <code><?php echo esc_url(rest_url('ai-seo-saas/v1/webhooks/stripe')); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mollie_mode"><?php esc_html_e('Mollie Mode', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <select name="sseo_ai_saas_mollie_mode" id="mollie_mode">
                                <option value="live" <?php selected(get_option('sseo_ai_saas_mollie_mode', 'live'), 'live'); ?>><?php esc_html_e('Live', 'sseo-ai-saas'); ?></option>
                                <option value="test" <?php selected(get_option('sseo_ai_saas_mollie_mode', 'live'), 'test'); ?>><?php esc_html_e('Test', 'sseo-ai-saas'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mollie_api_key"><?php esc_html_e('Mollie Live API Key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="sseo_ai_saas_mollie_api_key" id="mollie_api_key" value="<?php echo esc_attr(get_option('sseo_ai_saas_mollie_api_key', '')); ?>" class="regular-text" placeholder="live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mollie_test_api_key"><?php esc_html_e('Mollie Test API Key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="sseo_ai_saas_mollie_test_api_key" id="mollie_test_api_key" value="<?php echo esc_attr(get_option('sseo_ai_saas_mollie_test_api_key', '')); ?>" class="regular-text" placeholder="test_...">
                            <p class="description">
                                <?php esc_html_e('Webhook URL:', 'sseo-ai-saas'); ?> <code id="mollie-webhook-url"><?php echo esc_url(rest_url('ai-seo-saas/v1/webhooks/mollie')); ?></code>
                                <br><button type="button" class="button button-secondary" id="mollie-test-webhook-btn" style="margin-top:6px;"><?php esc_html_e('Test webhook bereikbaarheid', 'sseo-ai-saas'); ?></button>
                                <span id="mollie-webhook-test-result" style="display:block;margin-top:6px;"></span>
                            </p>
                            <script>
                            (function(){
                                var btn = document.getElementById('mollie-test-webhook-btn');
                                if (!btn) return;
                                btn.addEventListener('click', function(){
                                    var resultEl = document.getElementById('mollie-webhook-test-result');
                                    var urlEl = document.getElementById('mollie-webhook-url');
                                    var url = urlEl ? urlEl.textContent : '';
                                    resultEl.textContent = 'Testen...';
                                    resultEl.style.color = '#6b7280';
                                    btn.disabled = true;
                                    fetch(url, {
                                        method: 'POST',
                                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                        body: 'id=test_webhook_check'
                                    })
                                    .then(function(r){ return r.text().then(function(t){ return {status: r.status, body: t}; }); })
                                    .then(function(res){
                                        if (res.status === 200 || res.status === 400) {
                                            resultEl.textContent = '✓ Webhook endpoint is bereikbaar (HTTP ' + res.status + ')';
                                            resultEl.style.color = '#10b981';
                                        } else {
                                            resultEl.textContent = '⚠ Webhook reageert met HTTP ' + res.status;
                                            resultEl.style.color = '#f59e0b';
                                        }
                                    })
                                    .catch(function(err){
                                        resultEl.textContent = '✗ Webhook niet bereikbaar: ' + err;
                                        resultEl.style.color = '#dc2626';
                                    })
                                    .finally(function(){ btn.disabled = false; });
                                });
                            })();
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trial_enabled"><?php esc_html_e('Enable Trial', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="checkbox" name="sseo_ai_saas_trial_enabled" id="trial_enabled" value="1" <?php checked(get_option('sseo_ai_saas_trial_enabled', '1')); ?>>
                            <p class="description"><?php esc_html_e('Offer a free trial period on self-serve signup.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="trial_days"><?php esc_html_e('Trial Days', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="number" name="sseo_ai_saas_trial_days" id="trial_days" value="<?php echo esc_attr(get_option('sseo_ai_saas_trial_days', 14)); ?>" style="width: 120px;">
                        </td>
                    </tr>
                </table>

                <!-- Customer Portal Page -->
                <h3><?php esc_html_e('Customer Portal Page', 'sseo-ai-saas'); ?></h3>
                <p class="description">
                    <?php
                    echo wp_kses_post(sprintf(
                        /* translators: %s: shortcode */
                        __('Create a WordPress page and add the shortcode %s to it, then select that page here. Paying customers are redirected to this page after login. If no page is selected, customers will be redirected to the homepage instead of getting a 404.', 'sseo-ai-saas'),
                        '<code>[fyndable_customer_portal]</code>'
                    ));
                    ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="customer_portal_page"><?php esc_html_e('Portal Page', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name' => 'sseo_ai_saas_customer_portal_page',
                                'id' => 'customer_portal_page',
                                'selected' => (int) get_option('sseo_ai_saas_customer_portal_page', 0),
                                'show_option_none' => __('— Select a page —', 'sseo-ai-saas'),
                                'option_none_value' => 0,
                            ]);
                            ?>
                            <p class="description"><?php esc_html_e('The page must contain the [fyndable_customer_portal] shortcode.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                </table>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="early_adopters_enabled"><?php esc_html_e('Enable Early Adopters', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="checkbox" name="sseo_ai_saas_early_adopters_enabled" id="early_adopters_enabled" value="1" <?php checked(get_option('sseo_ai_saas_early_adopters_enabled', false)); ?>>
                            <p class="description"><?php esc_html_e('Make the Early Adopters tier available for signup and license generation. It is a copy of Starter at half price.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e('Tier Pricing & Limits', 'sseo-ai-saas'); ?></h3>
                <p class="description"><?php esc_html_e('Set the monthly subscription price (EUR), API call limit and cost cap (USD) for each tier.', 'sseo-ai-saas'); ?></p>

                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 120px;"><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Price (EUR/mo)', 'sseo-ai-saas'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('API Calls/mo', 'sseo-ai-saas'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Cost Cap (USD)', 'sseo-ai-saas'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('Auto Posts/mo', 'sseo-ai-saas'); ?></th>
                            <th style="width: 150px;"><?php esc_html_e('GEO Scans/mo', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $tiers = [
                            'starter' => __('Starter', 'sseo-ai-saas'),
                            'early_adopters' => __('Early Adopters', 'sseo-ai-saas'),
                            'trial' => __('Trial', 'sseo-ai-saas'),
                            'professional' => __('Professional', 'sseo-ai-saas'),
                            'business' => __('Business', 'sseo-ai-saas'),
                            'agency' => __('Agency', 'sseo-ai-saas'),
                        ];
                        foreach ($tiers as $tier => $label):
                            $price = $this->getPriceForTier($tier);
                            $calls = $this->getApiLimitForTier($tier);
                            $cost = $this->getCostLimitForTier($tier);
                            $autoPosts = $this->getAutoPostLimitForTier($tier);
                            $geoScans = $this->getGeoScanLimitForTier($tier);
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                       name="ai_seo_saas_<?php echo esc_attr($tier); ?>_price"
                                       value="<?php echo esc_attr(number_format($price, 2, '.', '')); ?>"
                                       style="width: 120px;">
                            </td>
                            <td>
                                <input type="number" step="1" min="0"
                                       name="ai_seo_saas_<?php echo esc_attr($tier); ?>_api_calls"
                                       value="<?php echo esc_attr($calls); ?>"
                                       style="width: 120px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                       name="ai_seo_saas_<?php echo esc_attr($tier); ?>_cost_limit"
                                       value="<?php echo esc_attr(number_format($cost, 2, '.', '')); ?>"
                                       style="width: 120px;">
                            </td>
                            <td>
                                <input type="number" step="1" min="0"
                                       name="ai_seo_saas_<?php echo esc_attr($tier); ?>_auto_posts"
                                       value="<?php echo esc_attr($autoPosts); ?>"
                                       style="width: 120px;">
                            </td>
                            <td>
                                <input type="number" step="1" min="0"
                                       name="ai_seo_saas_<?php echo esc_attr($tier); ?>_geo_scans"
                                       value="<?php echo esc_attr($geoScans); ?>"
                                       style="width: 120px;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save Checkout Settings', 'sseo-ai-saas')); ?>
            </form>

            <!-- Webhook URLs & Setup Guide (moved from Billing page) -->
            <div class="sseo-ai-card" style="margin-top: 25px;">
                <h2><?php esc_html_e('Webhook URLs', 'sseo-ai-saas'); ?></h2>
                <p style="margin-bottom: 20px; color: #646970;"><?php esc_html_e('Add these URLs to your payment provider dashboards to receive payment notifications.', 'sseo-ai-saas'); ?></p>

                <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">🔗 <?php esc_html_e('Stripe Webhook URL', 'sseo-ai-saas'); ?></h4>
                    <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; word-break: break-all; font-size: 12px;">
                        <?php echo esc_url($webhookUrls['stripe']); ?>
                    </code>
                    <p style="margin: 10px 0 0; font-size: 12px; color: #646970;">
                        <?php esc_html_e('Add this in Stripe Dashboard → Developers → Webhooks', 'sseo-ai-saas'); ?><br>
                        <?php esc_html_e('Events to listen for:', 'sseo-ai-saas'); ?> <code>invoice.payment_succeeded</code>, <code>invoice.payment_failed</code>, <code>customer.subscription.deleted</code>
                    </p>
                </div>

                <div style="background: #f6f7f7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">🔗 <?php esc_html_e('Mollie Webhook URL', 'sseo-ai-saas'); ?></h4>
                    <code style="display: block; padding: 10px; background: #fff; border-radius: 4px; word-break: break-all; font-size: 12px;">
                        <?php echo esc_url($webhookUrls['mollie']); ?>
                    </code>
                    <p style="margin: 10px 0 0; font-size: 12px; color: #646970;">
                        <?php esc_html_e('Add this in Mollie Dashboard → Settings → Webhooks', 'sseo-ai-saas'); ?>
                    </p>
                </div>
            </div>

            <div class="sseo-ai-card" style="background: #f0f6fc; border-color: #2271b1; margin-top: 25px;">
                <h3>💡 <?php esc_html_e('Payment Integration Guide', 'sseo-ai-saas'); ?></h3>
                <div class="sseo-ai-grid-2" style="margin-top: 20px;">
                    <div>
                        <h4><?php esc_html_e('Stripe Setup', 'sseo-ai-saas'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Sign up at stripe.com', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Get API keys from Dashboard', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Add webhook URL above', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Create subscription products', 'sseo-ai-saas'); ?></li>
                        </ol>
                        <p><strong><?php esc_html_e('Best for:', 'sseo-ai-saas'); ?></strong> <?php esc_html_e('Global credit card payments', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h4><?php esc_html_e('Mollie Setup', 'sseo-ai-saas'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Sign up at mollie.com', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Complete business verification', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Get API key from Dashboard', 'sseo-ai-saas'); ?></li>
                            <li><?php esc_html_e('Add webhook URL above', 'sseo-ai-saas'); ?></li>
                        </ol>
                        <p><strong><?php esc_html_e('Best for:', 'sseo-ai-saas'); ?></strong> <?php esc_html_e('European payments (iDEAL, Bancontact)', 'sseo-ai-saas'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Helper for the Checkout stats cards. Uses TenantRepository when available,
     * falls back to a direct query if not injected.
     */
    private function getAllTenantsForStats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_tenants';
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status != 'deleted'", ARRAY_A);
        return $rows ?: [];
    }


    public function renderCostDashboard(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'cost';
        ?>
        <div class="wrap sseo-ai-license-admin sseo-ai-cost-wrap">
            <h1><?php esc_html_e('Cost Dashboard', 'sseo-ai-saas'); ?></h1>
            <?php $this->renderCostDashboardTabs($tab); ?>
            <div class="sseo-ai-cost-content">
                <?php
                if ($tab === 'google') {
                    $this->renderGoogleCostDashboard();
                } elseif ($tab === 'revenue') {
                    $this->renderRevenueDashboard();
                } else {
                    $this->renderCostDashboardContent();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the cost tab content (cards + tables).
     */
    private function renderCostDashboardContent(): void
    {
        global $wpdb;

        // Get current month's usage
        $currentMonth = date('Y-m');
        $tableUsage = $wpdb->prefix . 'sseo_ai_tenant_usage';

        $monthlyStats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(api_calls) as total_calls,
                SUM(api_cost) as total_cost,
                COUNT(DISTINCT tenant_id) as active_tenants
            FROM {$tableUsage}
            WHERE period = %s",
            $currentMonth
        ));

        // Get top cost tenants
        $topTenants = $wpdb->get_results($wpdb->prepare(
            "SELECT
                t.tenant_key,
                t.domain,
                t.tier,
                SUM(u.api_calls) as total_calls,
                SUM(u.api_cost) as total_cost
            FROM {$tableUsage} u
            JOIN {$wpdb->prefix}sseo_ai_tenants t ON u.tenant_id = t.id
            WHERE u.period = %s
            GROUP BY t.id
            ORDER BY total_cost DESC
            LIMIT 10",
            $currentMonth
        ));

        // Get tier distribution
        $tierDistribution = $wpdb->get_results($wpdb->prepare(
            "SELECT t.tier, COUNT(*) as count, COALESCE(SUM(u.api_cost), 0) as total_cost
            FROM {$wpdb->prefix}sseo_ai_tenants t
            LEFT JOIN {$tableUsage} u ON t.id = u.tenant_id AND u.period = %s
            WHERE t.status = 'active'
            GROUP BY t.tier",
            $currentMonth
        ));
        ?>
        <div class="card" style="margin-bottom: 20px;">
            <h2><?php echo esc_html(date('F Y')); ?> - <?php esc_html_e('Current Month', 'sseo-ai-saas'); ?></h2>
            <div style="display: flex; gap: 30px; margin-top: 15px;">
                <div>
                    <h3>$<?php echo number_format($monthlyStats->total_cost ?? 0, 2); ?></h3>
                    <p><?php esc_html_e('Total API Costs', 'sseo-ai-saas'); ?></p>
                </div>
                <div>
                    <h3><?php echo number_format($monthlyStats->total_calls ?? 0); ?></h3>
                    <p><?php esc_html_e('Total API Calls', 'sseo-ai-saas'); ?></p>
                </div>
                <div>
                    <h3><?php echo (int)($monthlyStats->active_tenants ?? 0); ?></h3>
                    <p><?php esc_html_e('Active Tenants', 'sseo-ai-saas'); ?></p>
                </div>
                <div>
                    <h3>$<?php
                        $avg = ($monthlyStats->active_tenants ?? 0) > 0
                            ? ((float)($monthlyStats->total_cost ?? 0) / (float)($monthlyStats->active_tenants ?? 0))
                            : 0;
                        echo number_format($avg, 2);
                    ?></h3>
                    <p><?php esc_html_e('Avg Cost per Tenant', 'sseo-ai-saas'); ?></p>
                </div>
            </div>
        </div>

        <div style="display: block; gap: 20px;">
            <div class="card" style="width: 100%; margin-bottom: 20px;">
                <h3><?php esc_html_e('Top 10 Customers by Cost', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Site', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('API Calls', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topTenants as $tenant): ?>
                        <tr>
                            <td><?php echo esc_html($tenant->domain); ?></td>
                            <td><span class="badge badge-<?php echo esc_attr($tenant->tier); ?>">
                                <?php echo esc_html(ucfirst($tenant->tier)); ?>
                            </span></td>
                            <td><?php echo number_format((int)($tenant->total_calls ?? 0)); ?></td>
                            <td>$<?php echo number_format((float)($tenant->total_cost ?? 0), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" style="width: 100%;">
                <h3><?php esc_html_e('Tier Distribution', 'sseo-ai-saas'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Tenants', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tierDistribution as $tier): ?>
                        <tr>
                            <td><?php echo esc_html(ucfirst($tier->tier)); ?></td>
                            <td><?php echo (int)$tier->count; ?></td>
                            <td>$<?php echo number_format($tier->total_cost ?? 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3><?php esc_html_e('API Cost Breakdown by Service', 'sseo-ai-saas'); ?></h3>
            <?php
            $serviceBreakdown = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    'api_calls' as metric,
                    SUM(api_calls) as total_calls,
                    SUM(api_cost) as total_cost
                FROM {$tableUsage}
                WHERE period = %s
                UNION ALL
                SELECT
                    'serp_requests' as metric,
                    SUM(serp_requests) as total_calls,
                    0 as total_cost
                FROM {$tableUsage}
                WHERE period = %s",
                $currentMonth,
                $currentMonth
            ));
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Service', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Calls', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('% of Total', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalCost = $monthlyStats->total_cost ?? 0;
                    foreach ($serviceBreakdown as $service):
                        $percent = $totalCost > 0 ? ($service->total_cost / $totalCost * 100) : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $service->metric))); ?></td>
                        <td><?php echo number_format((int)($service->total_calls ?? 0)); ?></td>
                        <td>$<?php echo number_format((float)($service->total_cost ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)$percent, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render cost dashboard tab navigation.
     */
    private function renderCostDashboardTabs(string $tab): void
    {
        ?>
        <h2 class="nav-tab-wrapper sseo-ai-settings-tabs">
            <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-costs&tab=cost')); ?>" class="nav-tab <?php echo $tab === 'cost' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Cost Dashboard', 'sseo-ai-saas'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-costs&tab=google')); ?>" class="nav-tab <?php echo $tab === 'google' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Google API Costs per Klant', 'sseo-ai-saas'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-costs&tab=revenue')); ?>" class="nav-tab <?php echo $tab === 'revenue' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Revenue', 'sseo-ai-saas'); ?></a>
        </h2>
        <?php
    }

    /**
     * Render revenue tab content.
     */
    public function renderRevenueDashboard(): void
    {
        $revenue = new RevenueDashboard(new TenantRepository());
        $revenue->renderContent();
    }

    /**
     * Render Google API Costs dashboard
     */
    public function renderGoogleCostDashboard(): void
    {
        $tenants = new TenantRepository();

        $selectedMonth = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : current_time('Y-m');

        $summary = $tenants->getGoogleApiUsageSummary($selectedMonth);
        $allUsage = $tenants->getAllGoogleApiUsage($selectedMonth);

        $serviceLabels = [
            'gsc' => 'Search Console',
            'ga4' => 'Analytics 4',
            'ads' => 'Google Ads',
            'oauth' => 'OAuth Exchange',
        ];

        $totalCost = 0;
        $totalCalls = 0;
        $activeTenants = [];
        foreach ($summary as $row) {
            $totalCost += (float)$row['total_cost'];
            $totalCalls += (int)$row['total_calls'];
            $activeTenants[] = $row['active_tenants'];
        }
        $uniqueTenants = count(array_unique($activeTenants));

        $perTenant = [];
        foreach ($allUsage as $row) {
            $key = $row['tenant_key'];
            if (!isset($perTenant[$key])) {
                $perTenant[$key] = [
                    'name' => $row['name'],
                    'domain' => $row['domain'],
                    'tier' => $row['tier'],
                    'status' => $row['status'],
                    'services' => [],
                    'total_cost' => 0,
                    'total_calls' => 0,
                ];
            }
            $perTenant[$key]['services'][$row['service']] = [
                'calls' => (int)$row['api_calls'],
                'cost' => (float)$row['api_cost'],
            ];
            $perTenant[$key]['total_cost'] += (float)$row['api_cost'];
            $perTenant[$key]['total_calls'] += (int)$row['api_calls'];
        }

        uasort($perTenant, fn($a, $b) => $b['total_cost'] <=> $a['total_cost']);

        $availableMonths = [];
        $currentMonth = current_time('Y-m');
        $base = strtotime(date('Y-m-01'));
        for ($i = 0; $i < 12; $i++) {
            $m = date('Y-m', strtotime("-{$i} months", $base));
            $availableMonths[] = $m;
            if ($m === $currentMonth) break;
        }
        ?>
        <form method="get" action="" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="sseo-ai-costs">
                <input type="hidden" name="tab" value="google">
                <?php if (isset($_GET['saas_shell'])): ?>
                    <input type="hidden" name="saas_shell" value="1">
                <?php endif; ?>
                <label for="month"><?php esc_html_e('Maand:', 'sseo-ai-saas'); ?></label>
                <select name="month" id="month" onchange="this.form.submit()">
                    <?php foreach ($availableMonths as $m): ?>
                        <option value="<?php echo esc_attr($m); ?>" <?php selected($selectedMonth, $m); ?>>
                            <?php echo esc_html(date_i18n('F Y', strtotime($m . '-01'))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <div class="card" style="margin-bottom: 20px;">
                <h2><?php echo esc_html(date_i18n('F Y', strtotime($selectedMonth . '-01'))); ?></h2>
                <div style="display: flex; gap: 30px; margin-top: 15px;">
                    <div>
                        <h3>$<?php echo number_format($totalCost, 4); ?></h3>
                        <p><?php esc_html_e('Totale Google API Kosten', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3><?php echo number_format($totalCalls); ?></h3>
                        <p><?php esc_html_e('Totale API Calls', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3><?php echo $uniqueTenants; ?></h3>
                        <p><?php esc_html_e('Actieve Klanten', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3>$<?php echo $uniqueTenants > 0 ? number_format($totalCost / $uniqueTenants, 4) : '0.0000'; ?></h3>
                        <p><?php esc_html_e('Gemiddeld per Klant', 'sseo-ai-saas'); ?></p>
                    </div>
                </div>
            </div>

            <div style="display: block; gap: 20px;">
                <div class="card" style="width: 100%; margin-bottom: 20px;">
                    <h3><?php esc_html_e('Kosten per Klant', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Klant', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Domein', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('GSC', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('GA4', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Ads', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('OAuth', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Totaal Calls', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Totaal Kosten', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($perTenant)): ?>
                                <tr>
                                    <td colspan="9"><?php esc_html_e('Geen Google API activiteit in deze maand.', 'sseo-ai-saas'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($perTenant as $key => $t): ?>
                                    <tr>
                                        <td><?php echo esc_html($t['name']); ?></td>
                                        <td><?php echo esc_html($t['domain'] ?? '-'); ?></td>
                                        <td><span class="badge badge-<?php echo esc_attr($t['tier']); ?>"><?php echo esc_html(ucfirst($t['tier'])); ?></span></td>
                                        <td>
                                            <?php if (isset($t['services']['gsc'])): ?>
                                                <?php echo number_format($t['services']['gsc']['calls']); ?> calls<br>
                                                <small>$<?php echo number_format($t['services']['gsc']['cost'], 4); ?></small>
                                            <?php else: ?>–<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($t['services']['ga4'])): ?>
                                                <?php echo number_format($t['services']['ga4']['calls']); ?> calls<br>
                                                <small>$<?php echo number_format($t['services']['ga4']['cost'], 4); ?></small>
                                            <?php else: ?>–<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($t['services']['ads'])): ?>
                                                <?php echo number_format($t['services']['ads']['calls']); ?> calls<br>
                                                <small>$<?php echo number_format($t['services']['ads']['cost'], 4); ?></small>
                                            <?php else: ?>–<?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($t['services']['oauth'])): ?>
                                                <?php echo number_format($t['services']['oauth']['calls']); ?> calls<br>
                                                <small>$<?php echo number_format($t['services']['oauth']['cost'], 4); ?></small>
                                            <?php else: ?>–<?php endif; ?>
                                        </td>
                                        <td><strong><?php echo number_format($t['total_calls']); ?></strong></td>
                                        <td><strong>$<?php echo number_format($t['total_cost'], 4); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($perTenant)): ?>
                            <tfoot>
                                <tr style="background: #f0f0f1; font-weight: bold;">
                                    <td colspan="3"><?php esc_html_e('Totaal', 'sseo-ai-saas'); ?></td>
                                    <td>
                                        <?php
                                        $gscCalls = $gscCost = 0;
                                        foreach ($perTenant as $t) {
                                            $gscCalls += $t['services']['gsc']['calls'] ?? 0;
                                            $gscCost += $t['services']['gsc']['cost'] ?? 0;
                                        }
                                        echo number_format($gscCalls) . ' calls<br><small>$' . number_format($gscCost, 4) . '</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $ga4Calls = $ga4Cost = 0;
                                        foreach ($perTenant as $t) {
                                            $ga4Calls += $t['services']['ga4']['calls'] ?? 0;
                                            $ga4Cost += $t['services']['ga4']['cost'] ?? 0;
                                        }
                                        echo number_format($ga4Calls) . ' calls<br><small>$' . number_format($ga4Cost, 4) . '</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $adsCalls = $adsCost = 0;
                                        foreach ($perTenant as $t) {
                                            $adsCalls += $t['services']['ads']['calls'] ?? 0;
                                            $adsCost += $t['services']['ads']['cost'] ?? 0;
                                        }
                                        echo number_format($adsCalls) . ' calls<br><small>$' . number_format($adsCost, 4) . '</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $oauthCalls = $oauthCost = 0;
                                        foreach ($perTenant as $t) {
                                            $oauthCalls += $t['services']['oauth']['calls'] ?? 0;
                                            $oauthCost += $t['services']['oauth']['cost'] ?? 0;
                                        }
                                        echo number_format($oauthCalls) . ' calls<br><small>$' . number_format($oauthCost, 4) . '</small>';
                                        ?>
                                    </td>
                                    <td><strong><?php echo number_format($totalCalls); ?></strong></td>
                                    <td><strong>$<?php echo number_format($totalCost, 4); ?></strong></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="card" style="width: 100%;">
                    <h3><?php esc_html_e('Kosten per Service', 'sseo-ai-saas'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Service', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Calls', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Klanten', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Kosten', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($summary)): ?>
                                <tr><td colspan="4"><?php esc_html_e('Geen data.', 'sseo-ai-saas'); ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($summary as $row): ?>
                                    <tr>
                                        <td><?php echo esc_html($serviceLabels[$row['service']] ?? $row['service']); ?></td>
                                        <td><?php echo number_format($row['total_calls']); ?></td>
                                        <td><?php echo (int)$row['active_tenants']; ?></td>
                                        <td>$<?php echo number_format($row['total_cost'], 4); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php
    }

    public function renderAiModelsPage(): void
    {
        $useCases = \SSEOAISaaS\ProviderRouter::getUseCases();
        $standardModels = array_intersect_key(
            \SSEOAISaaS\ProviderRouter::getAvailableModels(),
            array_flip(\SSEOAISaaS\ProviderRouter::getStandardModels())
        );
        $premiumModels = array_intersect_key(
            \SSEOAISaaS\ProviderRouter::getAvailableModels(),
            array_flip(\SSEOAISaaS\ProviderRouter::getPremiumModels())
        );
        $standardRouting = get_option('sseo_ai_saas_standard_routing', []);
        $premiumRouting = get_option('sseo_ai_saas_premium_routing', []);
        $standardDefaults = \SSEOAISaaS\ProviderRouter::getRoutingForModelTier('standard');
        $premiumDefaults = \SSEOAISaaS\ProviderRouter::getRoutingForModelTier('premium');
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('AI Models', 'sseo-ai-saas'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('ai_seo_saas_settings'); ?>
                <p class="description"><?php esc_html_e('Choose the default AI model for each function per tier. Standard tier uses standard models; Professional, Business and Agency use premium models by default. Agencies can override the model tier when generating sub-licenses.', 'sseo-ai-saas'); ?></p>

                <h2><?php esc_html_e('Standard Tier Models', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Used for Starter / standard subscriptions.', 'sseo-ai-saas'); ?></p>
                <table class="form-table">
                    <?php foreach ($useCases as $key => $label): ?>
                        <?php $current = $standardRouting[$key] ?? $standardDefaults[$key] ?? 'openai/gpt-4o-mini'; ?>
                        <tr>
                            <th scope="row"><label for="standard_routing_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_standard_routing[<?php echo esc_attr($key); ?>]" id="standard_routing_<?php echo esc_attr($key); ?>">
                                    <?php foreach ($standardModels as $modelKey => $modelLabel): ?>
                                        <option value="<?php echo esc_attr($modelKey); ?>" <?php selected($current, $modelKey); ?>><?php echo esc_html($modelLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e('Premium Tier Models', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Used for Professional, Business and Agency subscriptions.', 'sseo-ai-saas'); ?></p>
                <table class="form-table">
                    <?php foreach ($useCases as $key => $label): ?>
                        <?php $current = $premiumRouting[$key] ?? $premiumDefaults[$key] ?? 'openai/gpt-4o'; ?>
                        <tr>
                            <th scope="row"><label for="premium_routing_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_premium_routing[<?php echo esc_attr($key); ?>]" id="premium_routing_<?php echo esc_attr($key); ?>">
                                    <?php foreach ($premiumModels as $modelKey => $modelLabel): ?>
                                        <option value="<?php echo esc_attr($modelKey); ?>" <?php selected($current, $modelKey); ?>><?php echo esc_html($modelLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <?php submit_button(__('Save AI Models', 'sseo-ai-saas')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Process the Client Versions form (settings + zip upload).
     */
    public function handleClientVersionsPost(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] !== 'POST'
            || empty($_GET['page'])
            || $_GET['page'] !== 'sseo-ai-client-versions'
            || empty($_POST['sseo_ai_client_versions_nonce'])
        ) {
            return;
        }

        if (!check_admin_referer('sseo_ai_client_versions_save', 'sseo_ai_client_versions_nonce')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage client versions.', 'sseo-ai-saas'));
        }

        update_option('sseo_ai_saas_latest_version', sanitize_text_field($_POST['sseo_ai_saas_latest_version'] ?? ''));
        update_option('sseo_ai_saas_download_url', sanitize_text_field($_POST['sseo_ai_saas_download_url'] ?? ''));
        update_option('sseo_ai_saas_min_wp_version', sanitize_text_field($_POST['sseo_ai_saas_min_wp_version'] ?? '6.0'));
        update_option('sseo_ai_saas_update_changelog', wp_kses_post($_POST['sseo_ai_saas_update_changelog'] ?? ''));

        update_option('sseo_ai_saas_beta_enabled', !empty($_POST['sseo_ai_saas_beta_enabled']) ? '1' : '0');
        update_option('sseo_ai_saas_beta_version', sanitize_text_field($_POST['sseo_ai_saas_beta_version'] ?? ''));
        update_option('sseo_ai_saas_beta_download_url', sanitize_text_field($_POST['sseo_ai_saas_beta_download_url'] ?? ''));
        update_option('sseo_ai_saas_beta_changelog', wp_kses_post($_POST['sseo_ai_saas_beta_changelog'] ?? ''));

        if (!empty($_FILES['client_plugin_zip']['tmp_name']) && is_uploaded_file($_FILES['client_plugin_zip']['tmp_name'])) {
            $version = sanitize_text_field($_POST['upload_version'] ?? '');
            $result = $this->handleClientPluginZipUpload($_FILES['client_plugin_zip'], $version);
            if (is_wp_error($result)) {
                set_transient('sseo_ai_client_versions_notice', ['error', $result->get_error_message()], 60);
            } else {
                $filename = basename($result);
                $uploads = wp_upload_dir();
                $fileUrl = $uploads['baseurl'] . '/fyndable-versions/' . $filename;
                update_option('sseo_ai_saas_download_url', $fileUrl);
                if (!empty($version)) {
                    update_option('sseo_ai_saas_latest_version', $version);
                }
                set_transient('sseo_ai_client_versions_notice', ['success', sprintf(__('Uploaded client plugin zip: %s', 'sseo-ai-saas'), $filename)], 60);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sseo-ai-client-versions&saved=1'));
        exit;
    }

    /**
     * Store an uploaded client plugin zip.
     */
    private function handleClientPluginZipUpload(array $file, string $version): string|\WP_Error
    {
        $fileInfo = wp_check_filetype($file['name']);
        if (empty($fileInfo['ext']) || $fileInfo['ext'] !== 'zip') {
            return new \WP_Error('invalid_type', __('Please upload a .zip file.', 'sseo-ai-saas'));
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            error_log('SSEO AI SaaS: wp_upload_dir error: ' . $uploads['error']);
            return new \WP_Error('upload_dir_error', sprintf(__('WordPress uploads directory is unavailable: %s', 'sseo-ai-saas'), $uploads['error']));
        }

        $uploadDir = $uploads['basedir'] . '/fyndable-versions/';
        if (!wp_mkdir_p($uploadDir)) {
            error_log('SSEO AI SaaS: Could not create client versions directory: ' . $uploadDir);
            return new \WP_Error('mkdir_failed', sprintf(__('Could not create the client versions directory: %s', 'sseo-ai-saas'), $uploadDir));
        }

        if (!is_writable($uploadDir)) {
            error_log('SSEO AI SaaS: Client versions directory is not writable: ' . $uploadDir);
            return new \WP_Error('not_writable', sprintf(__('The client versions directory is not writable: %s', 'sseo-ai-saas'), $uploadDir));
        }

        $version = sanitize_file_name($version);
        if (empty($version)) {
            $version = 'latest';
        }
        $filename = 'fyndable-client_v' . $version . '.zip';
        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            error_log('SSEO AI SaaS: Could not move uploaded file to: ' . $destination);
            return new \WP_Error('move_failed', sprintf(__('Could not move uploaded file to %s', 'sseo-ai-saas'), $destination));
        }

        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($destination) === true) {
                $hasPhp = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (substr($zip->getNameIndex($i), -4) === '.php') {
                        $hasPhp = true;
                        break;
                    }
                }
                $zip->close();
                if (!$hasPhp) {
                    unlink($destination);
                    return new \WP_Error('invalid_zip', __('The uploaded zip does not appear to contain a WordPress plugin.', 'sseo-ai-saas'));
                }
            }
        }

        return $destination;
    }

    /**
     * Render the Client Versions admin page.
     */
    public function renderClientVersionsPage(): void
    {
        if (!empty($_GET['saved'])) {
            add_settings_error(
                'sseo_ai_client_versions',
                'client_versions_saved',
                __('Client version settings saved.', 'sseo-ai-saas'),
                'success'
            );
        }

        $notice = get_transient('sseo_ai_client_versions_notice');
        if (is_array($notice)) {
            delete_transient('sseo_ai_client_versions_notice');
            add_settings_error(
                'sseo_ai_client_versions',
                'client_versions_' . sanitize_key($notice[0]),
                $notice[1],
                $notice[0]
            );
        }

        $latestVersion = get_option('sseo_ai_saas_latest_version', SSEO_AI_SAAS_VERSION);
        $downloadUrl = get_option('sseo_ai_saas_download_url', '');
        $minWpVersion = get_option('sseo_ai_saas_min_wp_version', '6.0');
        $changelog = get_option('sseo_ai_saas_update_changelog', '');
        $betaEnabled = get_option('sseo_ai_saas_beta_enabled', '0') === '1';
        $betaVersion = get_option('sseo_ai_saas_beta_version', '');
        $betaDownloadUrl = get_option('sseo_ai_saas_beta_download_url', '');
        $betaChangelog = get_option('sseo_ai_saas_beta_changelog', '');

        $uploads = wp_upload_dir();
        $versionsDir = $uploads['basedir'] . '/fyndable-versions/';
        if (!is_dir($versionsDir)) {
            wp_mkdir_p($versionsDir);
        }
        $versionFiles = [];
        if (is_dir($versionsDir)) {
            $files = glob($versionsDir . '*.zip');
            if (is_array($files)) {
                rsort($files);
                $versionFiles = $files;
            }
        }
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Client Versions', 'sseo-ai-saas'); ?></h1>
            <?php settings_errors('sseo_ai_client_versions'); ?>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('sseo_ai_client_versions_save', 'sseo_ai_client_versions_nonce'); ?>

                <div class="sseo-ai-card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Stable Release', 'sseo-ai-saas'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_latest_version"><?php esc_html_e('Latest Version', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="sseo_ai_saas_latest_version" id="sseo_ai_saas_latest_version" value="<?php echo esc_attr($latestVersion); ?>" class="regular-text" placeholder="1.5.1">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_download_url"><?php esc_html_e('Download URL', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="url" name="sseo_ai_saas_download_url" id="sseo_ai_saas_download_url" value="<?php echo esc_attr($downloadUrl); ?>" class="regular-text" placeholder="https://.../fyndable-client_v1.5.1.zip">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_min_wp_version"><?php esc_html_e('Minimum WordPress Version', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="sseo_ai_saas_min_wp_version" id="sseo_ai_saas_min_wp_version" value="<?php echo esc_attr($minWpVersion); ?>" class="regular-text" placeholder="6.0">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_update_changelog"><?php esc_html_e('Changelog', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <textarea name="sseo_ai_saas_update_changelog" id="sseo_ai_saas_update_changelog" rows="5" cols="50" class="large-text"><?php echo esc_textarea($changelog); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sseo-ai-card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Beta Release', 'sseo-ai-saas'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_beta_enabled"><?php esc_html_e('Enable Beta Channel', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="checkbox" name="sseo_ai_saas_beta_enabled" id="sseo_ai_saas_beta_enabled" value="1" <?php checked($betaEnabled); ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_beta_version"><?php esc_html_e('Beta Version', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="sseo_ai_saas_beta_version" id="sseo_ai_saas_beta_version" value="<?php echo esc_attr($betaVersion); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_beta_download_url"><?php esc_html_e('Beta Download URL', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="url" name="sseo_ai_saas_beta_download_url" id="sseo_ai_saas_beta_download_url" value="<?php echo esc_attr($betaDownloadUrl); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_ai_saas_beta_changelog"><?php esc_html_e('Beta Changelog', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <textarea name="sseo_ai_saas_beta_changelog" id="sseo_ai_saas_beta_changelog" rows="5" cols="50" class="large-text"><?php echo esc_textarea($betaChangelog); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="sseo-ai-card" style="margin-bottom: 20px;">
                    <h2><?php esc_html_e('Upload Client Plugin', 'sseo-ai-saas'); ?></h2>
                    <p class="description"><?php esc_html_e('Upload a .zip to the versions folder. The version is used for the file name.', 'sseo-ai-saas'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="upload_version"><?php esc_html_e('Version for upload', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" name="upload_version" id="upload_version" class="regular-text" placeholder="1.5.1">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="client_plugin_zip"><?php esc_html_e('Plugin .zip', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="file" name="client_plugin_zip" id="client_plugin_zip" accept=".zip">
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Client Versions', 'sseo-ai-saas')); ?>
            </form>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Uploaded Versions', 'sseo-ai-saas'); ?></h2>
                <?php if (empty($versionFiles)): ?>
                    <p class="description"><?php esc_html_e('No client plugin zips found in the versions folder.', 'sseo-ai-saas'); ?></p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('File', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Size', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($versionFiles as $file): ?>
                                <tr>
                                    <td><code><?php echo esc_html(basename($file)); ?></code></td>
                                    <td><?php echo esc_html(size_format(filesize($file), 2)); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) filemtime($file))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Migrate client plugin zips from the plugin directory to wp-content/uploads.
     */
    public function maybeMigrateClientVersions(): void
    {
        if (get_option('sseo_ai_saas_client_versions_migrated')) {
            return;
        }

        $oldDir = SSEO_AI_SAAS_PLUGIN_DIR . 'versions/';
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) {
            error_log('SSEO AI SaaS: Migration wp_upload_dir error: ' . $uploads['error']);
            return;
        }
        if (!is_dir($oldDir)) {
            update_option('sseo_ai_saas_client_versions_migrated', true);
            return;
        }

        $newDir = $uploads['basedir'] . '/fyndable-versions/';
        if (!wp_mkdir_p($newDir)) {
            error_log('SSEO AI SaaS: Migration could not create client versions directory: ' . $newDir);
            return;
        }
        error_log('SSEO AI SaaS: Migrated client versions to: ' . $newDir);

        $files = glob($oldDir . '*.zip');
        $latestVersion = '';
        $latestFile = '';

        if (is_array($files)) {
            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $filename = basename($file);
                $newPath = $newDir . $filename;
                if (file_exists($newPath)) {
                    unlink($file);
                } elseif (copy($file, $newPath)) {
                    unlink($file);
                }

                if (preg_match('/fyndable-client_v([0-9.]+)\.zip$/', $filename, $matches)) {
                    $v = $matches[1];
                    if (empty($latestVersion) || version_compare($v, $latestVersion, '>')) {
                        $latestVersion = $v;
                        $latestFile = $filename;
                    }
                }
            }
        }

        $downloadUrl = get_option('sseo_ai_saas_download_url', '');
        $latestVersionOption = get_option('sseo_ai_saas_latest_version', '');
        $baseUrl = $uploads['baseurl'] . '/fyndable-versions/';

        if (!empty($latestFile)) {
            $newFileUrl = $baseUrl . $latestFile;
            if (empty($downloadUrl) || strpos($downloadUrl, '/fyndable-saas-dashboard/versions/') !== false) {
                update_option('sseo_ai_saas_download_url', $newFileUrl);
            }
            if (empty($latestVersionOption)) {
                update_option('sseo_ai_saas_latest_version', $latestVersion);
            }
        }

        update_option('sseo_ai_saas_client_versions_migrated', true);
    }
}

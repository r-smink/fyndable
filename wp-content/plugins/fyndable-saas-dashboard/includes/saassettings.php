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
        add_action('admin_init', [$this, 'registerSettings']);
    }
    
    /**
     * Add settings and checkout admin pages
     */
    public function addSettingsMenu(): void
    {
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
            __('Google API Costs', 'sseo-ai-saas'),
            __('Google API Costs', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-google-costs',
            [$this, 'renderGoogleCostDashboard']
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
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_free_api_calls', ['default' => 50]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_starter_api_calls', ['default' => 200]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_professional_api_calls', ['default' => 1000]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_business_api_calls', ['default' => 5000]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_agency_api_calls', ['default' => 20000]);
        
        // Cost limits per tier (in USD)
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_free_cost_limit', ['default' => 5]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_starter_cost_limit', ['default' => 20]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_trial_cost_limit', ['default' => 50]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_professional_cost_limit', ['default' => 100]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_business_cost_limit', ['default' => 500]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_agency_cost_limit', ['default' => 2000]);

        // Monthly subscription price per tier (in EUR)
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_free_price', ['default' => 0]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_starter_price', ['default' => 29]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_trial_price', ['default' => 0]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_professional_price', ['default' => 79]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_business_price', ['default' => 199]);
        register_setting('ai_seo_saas_limits', 'ai_seo_saas_agency_price', ['default' => 499]);
        
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

        // Trial settings
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_trial_days', ['default' => 14]);
        register_setting('sseo_ai_saas_billing', 'sseo_ai_saas_trial_enabled', ['default' => '1']);

        // Google OAuth credentials (central, used by all client sites)
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_google_client_id');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_google_client_secret');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_google_ads_dev_token');
        register_setting('ai_seo_saas_settings', 'ai_seo_saas_support_email');
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
     * Get Google Ads Developer Token
     */
    public function getGoogleAdsDevToken(): string
    {
        return get_option('ai_seo_saas_google_ads_dev_token', '');
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
            'free' => 50,
            'starter' => 200,
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
            'free' => 5,
            'starter' => 20,
            'trial' => 50,
            'professional' => 100,
            'business' => 500,
            'agency' => 2000,
        ];
        return $defaults[$tier] ?? 10;
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
            'free' => 0,
            'starter' => 29,
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
        $tiers = ['free', 'starter', 'trial', 'professional', 'business', 'agency'];
        $result = [];
        foreach ($tiers as $tier) {
            $result[$tier] = [
                'name' => ucfirst($tier),
                'price' => $this->getPriceForTier($tier),
                'api_calls' => $this->getApiLimitForTier($tier),
                'cost_limit' => $this->getCostLimitForTier($tier),
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
                <a href="#tab-ai-models" class="nav-tab nav-tab-active" data-tab="ai-models"><?php esc_html_e('AI Models', 'sseo-ai-saas'); ?></a>
                <a href="#tab-api-credentials" class="nav-tab" data-tab="api-credentials"><?php esc_html_e('API Credentials', 'sseo-ai-saas'); ?></a>
                <a href="#tab-image-generation" class="nav-tab" data-tab="image-generation"><?php esc_html_e('Image Generation', 'sseo-ai-saas'); ?></a>
                <a href="#tab-integrations" class="nav-tab" data-tab="integrations"><?php esc_html_e('Integrations', 'sseo-ai-saas'); ?></a>
                <a href="#tab-tier-pricing" class="nav-tab" data-tab="tier-pricing"><?php esc_html_e('Tier Pricing', 'sseo-ai-saas'); ?></a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('ai_seo_saas_settings'); ?>

                <div class="sseo-ai-tab-panel active" id="tab-ai-models">
                    <h2><?php esc_html_e('AI Models', 'sseo-ai-saas'); ?></h2>
                    <p class="description"><?php esc_html_e('Configure the default AI provider and per-function model routing.', 'sseo-ai-saas'); ?></p>
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
                    </table>

                    <h3><?php esc_html_e('AI Model Routing (Per Function)', 'sseo-ai-saas'); ?></h3>
                    <p class="description"><?php esc_html_e('Choose which AI model to use for each function. Models with a slash (e.g. openai/gpt-4o) route through OpenRouter.', 'sseo-ai-saas'); ?></p>
                    <table class="form-table">
                        <?php
                        $routing = get_option('sseo_ai_saas_model_routing', []);
                        if (!is_array($routing)) { $routing = []; }
                        $useCases = \SSEOAISaaS\ProviderRouter::getUseCases();
                        $models = \SSEOAISaaS\ProviderRouter::getAvailableModels();
                        $defaults = \SSEOAISaaS\ProviderRouter::getDefaultRouting();
                        foreach ($useCases as $key => $label):
                            $currentModel = $routing[$key] ?? $defaults[$key] ?? 'openai/gpt-4o';
                        ?>
                        <tr>
                            <th scope="row"><label for="routing_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <select name="sseo_ai_saas_model_routing[<?php echo esc_attr($key); ?>]" id="routing_<?php echo esc_attr($key); ?>">
                                    <?php foreach ($models as $modelKey => $modelLabel): ?>
                                        <option value="<?php echo esc_attr($modelKey); ?>" <?php selected($currentModel, $modelKey); ?>><?php echo esc_html($modelLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <div class="sseo-ai-tab-panel" id="tab-api-credentials">
                    <h2><?php esc_html_e('API Credentials', 'sseo-ai-saas'); ?></h2>
                    <table class="form-table">
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

                <?php submit_button(__('Save Settings', 'sseo-ai-saas')); ?>
            </form>

            <div class="sseo-ai-tab-panel" id="tab-tier-pricing">
                <form method="post" action="options.php">
                    <?php settings_fields('ai_seo_saas_limits'); ?>

                    <h2><?php esc_html_e('Tier Pricing & Limits', 'sseo-ai-saas'); ?></h2>
                    <p class="description"><?php esc_html_e('Set the monthly subscription price (EUR), API call limit and cost cap (USD) for each tier.', 'sseo-ai-saas'); ?></p>

                    <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="width: 120px;"><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                                <th style="width: 150px;"><?php esc_html_e('Price (EUR/mo)', 'sseo-ai-saas'); ?></th>
                                <th style="width: 150px;"><?php esc_html_e('API Calls/mo', 'sseo-ai-saas'); ?></th>
                                <th style="width: 150px;"><?php esc_html_e('Cost Cap (USD)', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tiers = [
                                'free' => __('Free', 'sseo-ai-saas'),
                                'starter' => __('Starter', 'sseo-ai-saas'),
                                'trial' => __('Trial', 'sseo-ai-saas'),
                                'professional' => __('Professional', 'sseo-ai-saas'),
                                'business' => __('Business', 'sseo-ai-saas'),
                                'agency' => __('Agency', 'sseo-ai-saas'),
                            ];
                            foreach ($tiers as $tier => $label):
                                $price = $this->getPriceForTier($tier);
                                $calls = $this->getApiLimitForTier($tier);
                                $cost = $this->getCostLimitForTier($tier);
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php submit_button(__('Save Tier Settings', 'sseo-ai-saas')); ?>
                </form>
            </div>

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
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Checkout', 'sseo-ai-saas'); ?></h1>

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
                        <th scope="row"><label for="mollie_api_key"><?php esc_html_e('Mollie API Key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="password" name="sseo_ai_saas_mollie_api_key" id="mollie_api_key" value="<?php echo esc_attr(get_option('sseo_ai_saas_mollie_api_key', '')); ?>" class="regular-text" placeholder="test_... / live_...">
                            <p class="description">
                                <?php esc_html_e('Webhook URL:', 'sseo-ai-saas'); ?> <code><?php echo esc_url(rest_url('ai-seo-saas/v1/webhooks/mollie')); ?></code>
                            </p>
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

                <?php submit_button(__('Save Checkout Settings', 'sseo-ai-saas')); ?>
            </form>
        </div>
        <?php
    }


    public function renderCostDashboard(): void
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
        $tierDistribution = $wpdb->get_results(
            "SELECT t.tier, COUNT(*) as count, COALESCE(SUM(u.api_cost), 0) as total_cost
            FROM {$wpdb->prefix}sseo_ai_tenants t
            LEFT JOIN {$tableUsage} u ON t.id = u.tenant_id AND u.period = '{$wpdb->_real_escape($currentMonth)}'
            WHERE t.status = 'active'
            GROUP BY t.tier"
        );
        ?>
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Cost Dashboard', 'sseo-ai-saas'); ?></h1>
            
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
                        <h3><?php echo $monthlyStats->active_tenants ?? 0; ?></h3>
                        <p><?php esc_html_e('Active Tenants', 'sseo-ai-saas'); ?></p>
                    </div>
                    <div>
                        <h3>$<?php 
                            $avg = ($monthlyStats->active_tenants ?? 0) > 0 
                                ? ($monthlyStats->total_cost / $monthlyStats->active_tenants) 
                                : 0;
                            echo number_format($avg, 2);
                        ?></h3>
                        <p><?php esc_html_e('Avg Cost per Tenant', 'sseo-ai-saas'); ?></p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
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
                                <td><?php echo number_format($tenant->total_calls); ?></td>
                                <td>$<?php echo number_format($tenant->total_cost, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card" style="width: 350px;">
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
                                <td><?php echo $tier->count; ?></td>
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
                            <td><?php echo number_format($service->total_calls); ?></td>
                            <td>$<?php echo number_format($service->total_cost, 2); ?></td>
                            <td><?php echo number_format($percent, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
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
        <div class="wrap sseo-ai-license-admin">
            <h1><?php esc_html_e('Google API Costs per Klant', 'sseo-ai-saas'); ?></h1>

            <form method="get" action="" style="margin-bottom: 15px;">
                <input type="hidden" name="page" value="sseo-ai-google-costs">
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

            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
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

                <div class="card" style="width: 320px;">
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
                                        <td><?php echo $row['active_tenants']; ?></td>
                                        <td>$<?php echo number_format($row['total_cost'], 4); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

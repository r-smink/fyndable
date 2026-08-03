<?php

namespace SSEOAIClient;

/**
 * Onboarding Wizard
 *
 * 7-step guided setup shown on first activation:
 * 1. License connect (or skip for free tier)
 * 2. Company branding (name, logo, description, industry)
 * 3. Tone of voice (brand voice, target audience, writing style)
 * 4. Basic SEO config (title templates, social profiles)
 * 5. Content type selection (which post types to enable SEO for)
 * 6. Google integrations (GSC, GA4, PageSpeed)
 * 7. Final setup & review
 *
 * After completion, redirects to the dashboard.
 */
class OnboardingWizard
{
    private const COMPLETED_OPTION = 'sseo_ai_onboarding_completed';
    private const STEP_OPTION      = 'sseo_ai_onboarding_step';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_post_sseo_ai_onboarding_save', [$this, 'handleSave']);
        add_action('admin_init', [$this, 'maybeRedirect']);
    }

    /**
     * Register the onboarding page (hidden from menu).
     */
    public function registerPage(): void
    {
        add_submenu_page(
            'fyndable-dashboard',
            __('Welcome to Fyndable', 'ai-seo-client'),
            '',
            'manage_options',
            'ai-seo-onboarding',
            [$this, 'renderPage']
        );
    }

    /**
     * Redirect to onboarding on first activation if not completed.
     */
    public function maybeRedirect(): void
    {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        if (get_option(self::COMPLETED_OPTION)) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && $screen->id === 'fyndable-dashboard_page_ai-seo-onboarding') {
            return;
        }

        if (isset($_GET['page']) && $_GET['page'] === 'ai-seo-onboarding') {
            return;
        }

        if (get_option('sseo_ai_client_license_status') === 'active') {
            update_option(self::COMPLETED_OPTION, '1');
            return;
        }

        $firstActivation = (int) get_option('sseo_ai_client_first_activation', 0);
        if (!$firstActivation) {
            return;
        }

        if (time() - $firstActivation < 5 * MINUTE_IN_SECONDS) {
            return;
        }

        wp_redirect(admin_url('admin.php?page=ai-seo-onboarding'));
        exit;
    }

    /**
     * Handle form submission from onboarding steps.
     */
    public function handleSave(): void
    {
        check_admin_referer('sseo_ai_onboarding');

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }

        $step = (int) ($_POST['step'] ?? 1);
        $nextStep = $step + 1;

        switch ($step) {
            case 1:
                $this->saveStep1();
                break;
            case 2:
                $this->saveStep2();
                break;
            case 3:
                $this->saveStep3();
                break;
            case 4:
                $this->saveStep4();
                break;
            case 5:
                $this->saveStep5();
                break;
            case 6:
                $this->saveStep6();
                break;
            case 7:
                $this->saveStep7();
                update_option(self::COMPLETED_OPTION, '1');
                wp_redirect(admin_url('admin.php?page=ai-seo-dashboard'));
                exit;
            default:
                break;
        }

        update_option(self::STEP_OPTION, (string) $nextStep);

        wp_redirect(admin_url('admin.php?page=ai-seo-onboarding&step=' . $nextStep));
        exit;
    }

    /**
     * Step 1: License connection (or skip).
     */
    private function saveStep1(): void
    {
        $action = sanitize_text_field($_POST['license_action'] ?? 'activate');
        $dashboardUrl = esc_url_raw($_POST['dashboard_url'] ?? '');
        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');
        $freeTierEnabled = (bool) get_option('sseo_ai_free_tier_enabled', false);

        if ($dashboardUrl) {
            update_option('sseo_ai_client_dashboard_url', $dashboardUrl);
        }

        if ($action === 'skip' && $freeTierEnabled) {
            update_option('sseo_ai_client_license_status', 'free');
            update_option('sseo_ai_client_license_tier', 'free');
            return;
        }

        if ($action === 'skip' && !$freeTierEnabled) {
            $this->redirectWithError(__('Free tier is not available. Please activate a license key.', 'ai-seo-client'));
        }

        if (empty($licenseKey) || empty($dashboardUrl)) {
            $this->redirectWithError(__('Please enter a valid license key and dashboard URL.', 'ai-seo-client'));
        }

        $api = new DashboardAPI(new Settings());
        $result = $api->activateLicense($licenseKey, $dashboardUrl);

        if (is_wp_error($result)) {
            $this->redirectWithError($result->get_error_message());
        }

        if (empty($result['tenant_key'])) {
            $this->redirectWithError(__('Invalid response from dashboard. No tenant key received.', 'ai-seo-client'));
        }

        update_option(SSEO_AI_CLIENT_LICENSE_OPTION, $licenseKey);
        update_option(SSEO_AI_CLIENT_TENANT_OPTION, $result['tenant_key']);
        update_option('sseo_ai_client_license_status', 'active');
        update_option('sseo_ai_client_license_tier', $result['tier'] ?? 'paid');
        update_option('sseo_ai_client_license_type', $result['type'] ?? 'paid');
        update_option('sseo_ai_client_license_expires', $result['expires_at'] ?? '');
        update_option('sseo_ai_client_rate_limit', $result['rate_limit'] ?? 60);
        update_option('sseo_ai_client_api_limit', $result['api_calls_limit'] ?? 1000);

        if (!empty($result['white_label']) && is_array($result['white_label'])) {
            update_option('sseo_ai_white_label', $result['white_label']);
            
            // Extract individual white-label settings
            if (isset($result['white_label']['fynable_login_enabled'])) {
                update_option('sseo_ai_fynable_login_enabled', $result['white_label']['fynable_login_enabled']);
            }
            if (isset($result['white_label']['company_name'])) {
                update_option('sseo_ai_wl_company_name', $result['white_label']['company_name']);
            }
            if (isset($result['white_label']['company_logo'])) {
                update_option('sseo_ai_wl_company_logo', $result['white_label']['company_logo']);
            }
            if (isset($result['white_label']['primary_color'])) {
                update_option('sseo_ai_wl_primary_color', $result['white_label']['primary_color']);
            }
            if (isset($result['white_label']['secondary_color'])) {
                update_option('sseo_ai_wl_secondary_color', $result['white_label']['secondary_color']);
            }
        }

        if (!empty($result['image_api'])) {
            update_option('sseo_ai_client_image_api', $result['image_api']);
        }

        if (!empty($result['model_routing'])) {
            update_option('sseo_ai_client_model_routing', $result['model_routing']);
        }
    }

    /**
     * Redirect back to onboarding step with an error message.
     */
    private function redirectWithError(string $message): void
    {
        wp_redirect(admin_url('admin.php?page=ai-seo-onboarding&step=1&onboarding_error=' . urlencode($message)));
        exit;
    }

    /**
     * Step 2: Company branding.
     */
    private function saveStep2(): void
    {
        // Company name (if not set by white-label)
        $companyName = sanitize_text_field($_POST['company_name'] ?? '');
        if ($companyName && !get_option('sseo_ai_wl_company_name')) {
            update_option('sseo_ai_wl_company_name', $companyName);
        }
        
        // Company logo upload
        if (isset($_FILES['company_logo']) && !empty($_FILES['company_logo']['tmp_name'])) {
            $logo = $this->handleLogoUpload($_FILES['company_logo']);
            if ($logo && !get_option('sseo_ai_wl_company_logo')) {
                update_option('sseo_ai_wl_company_logo', $logo);
            }
        }
        
        // Website description
        $description = sanitize_textarea_field($_POST['website_description'] ?? '');
        update_option('sseo_ai_website_description', $description);
        
        // Industry/category
        $industry = sanitize_text_field($_POST['industry'] ?? '');
        update_option('sseo_ai_industry', $industry);
    }

    /**
     * Handle logo upload.
     */
    private function handleLogoUpload(array $file): string|false
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (isset($upload['error'])) {
            return false;
        }
        
        return $upload['url'] ?? false;
    }

    /**
     * Step 3: Tone of voice.
     */
    private function saveStep3(): void
    {
        // Brand voice selection - save in BrandVoice format
        $brandVoice = sanitize_text_field($_POST['brand_voice'] ?? 'professional');
        $targetAudience = sanitize_textarea_field($_POST['target_audience'] ?? '');
        $writingStyle = sanitize_text_field($_POST['writing_style'] ?? 'conversational');
        $brandValues = sanitize_textarea_field($_POST['brand_values'] ?? '');
        
        // Save in BrandVoice class format for compatibility
        $brandVoiceSettings = [
            'enabled' => true,
            'brand_name' => get_option('sseo_ai_wl_company_name', ''),
            'tone' => $brandVoice,
            'style' => $writingStyle,
            'audience' => $targetAudience,
            'voice_description' => $brandValues,
            'preferred_terms' => '',
            'forbidden_terms' => '',
            'example_good' => '',
            'example_bad' => '',
            'language' => 'en',
        ];
        
        update_option('sseo_ai_brand_voice', $brandVoiceSettings);
        
        // Also save individual options for fallback/compatibility
        update_option('sseo_ai_target_audience', $targetAudience);
        update_option('sseo_ai_writing_style', $writingStyle);
        update_option('sseo_ai_brand_values', $brandValues);
    }

    /**
     * Step 4: Basic SEO configuration.
     */
    private function saveStep4(): void
    {
        $titleTemplate = sanitize_text_field($_POST['title_template'] ?? '%title% %separator% %sitename%');
        $descriptionTemplate = sanitize_text_field($_POST['description_template'] ?? '%excerpt%');
        $separator = sanitize_text_field($_POST['separator'] ?? 'â€“');
        $socialFacebook = esc_url_raw($_POST['social_facebook'] ?? '');
        $socialTwitter = esc_url_raw($_POST['social_twitter'] ?? '');
        $socialLinkedIn = esc_url_raw($_POST['social_linkedin'] ?? '');

        update_option('sseo_ai_title_template', $titleTemplate);
        update_option('sseo_ai_description_template', $descriptionTemplate);
        update_option('sseo_ai_separator', $separator);
        update_option('sseo_ai_social_facebook', $socialFacebook);
        update_option('sseo_ai_social_twitter', $socialTwitter);
        update_option('sseo_ai_social_linkedin', $socialLinkedIn);
    }

    /**
     * Step 5: Content type selection.
     */
    private function saveStep5(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        $enabled = [];

        foreach ($postTypes as $type) {
            if (isset($_POST['post_types'][$type])) {
                $enabled[] = $type;
            }
        }

        if (empty($enabled)) {
            $enabled = ['post', 'page'];
        }

        update_option('sseo_ai_enabled_post_types', $enabled);

        $enableSitemap = isset($_POST['enable_sitemap']) && $_POST['enable_sitemap'] === '1';
        $enableRobots = isset($_POST['enable_robots']) && $_POST['enable_robots'] === '1';
        $enableSchema = isset($_POST['enable_schema']) && $_POST['enable_schema'] === '1';

        update_option('sseo_ai_enable_sitemap', $enableSitemap ? '1' : '0');
        update_option('sseo_ai_enable_robots', $enableRobots ? '1' : '0');
        update_option('sseo_ai_enable_schema', $enableSchema ? '1' : '0');
    }

    /**
     * Step 6: Google integrations.
     */
    private function saveStep6(): void
    {
        // PageSpeed API key (existing)
        $pagespeedKey = sanitize_text_field($_POST['pagespeed_api_key'] ?? '');
        if ($pagespeedKey) {
            update_option('sseo_ai_pagespeed_api_key', $pagespeedKey);
        }
        
        // GSC connection status (if OAuth flow completed)
        $gscConnected = isset($_POST['gsc_connected']) && $_POST['gsc_connected'] === '1';
        update_option('sseo_ai_gsc_connected', $gscConnected ? '1' : '0');
        
        // GA4 connection status
        $ga4Connected = isset($_POST['ga4_connected']) && $_POST['ga4_connected'] === '1';
        update_option('sseo_ai_ga4_connected', $ga4Connected ? '1' : '0');
    }

    /**
     * Step 7: Final setup & review.
     */
    private function saveStep7(): void
    {
        // Just mark as complete - this is a review step
        update_option(self::COMPLETED_OPTION, '1');
        
        // Log onboarding completion for analytics
        update_option('sseo_ai_onboarding_completed_at', current_time('mysql'));
    }
    {
        $pagespeedKey = sanitize_text_field($_POST['pagespeed_api_key'] ?? '');
        if ($pagespeedKey) {
            update_option('sseo_ai_pagespeed_api_key', $pagespeedKey);
        }
    }

    /**
     * Render the onboarding wizard page.
     */
    public function renderPage(): void
    {
        $currentStep = isset($_GET['step']) ? max(1, (int) $_GET['step']) : (int) get_option(self::STEP_OPTION, 1);
        $currentStep = max(1, min(7, $currentStep));

        $freeTierEnabled = (bool) get_option('sseo_ai_free_tier_enabled', false);
        $onboardingError = isset($_GET['onboarding_error']) ? sanitize_text_field(urldecode($_GET['onboarding_error'])) : '';

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $brandName = $companyName . ' Smart SEO';

        $steps = [
            1 => __('Connect', 'ai-seo-client'),
            2 => __('Branding', 'ai-seo-client'),
            3 => __('Tone of Voice', 'ai-seo-client'),
            4 => __('SEO Setup', 'ai-seo-client'),
            5 => __('Content Types', 'ai-seo-client'),
            6 => __('Integrations', 'ai-seo-client'),
            7 => __('Review', 'ai-seo-client'),
        ];

        $primaryColor = '#379fd3';
        $secondaryColor = '#8f39ac';
        ?>
        <style>
            .sseo-onboarding { max-width: 700px; margin: 40px auto; font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-onboarding-header { text-align: center; margin-bottom: 40px; }
            .sseo-onboarding-header h1 { font-size: 32px; font-weight: 700; background: linear-gradient(135deg, <?php echo esc_attr($primaryColor); ?> 0%, <?php echo esc_attr($secondaryColor); ?> 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 0 0 8px 0; }
            .sseo-onboarding-header p { color: #6b7280; font-size: 16px; margin: 0; }
            .sseo-onboarding-progress { display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; }
            .sseo-onboarding-progress::before { content: ''; position: absolute; top: 20px; left: 0; right: 0; height: 2px; background: #e5e7eb; z-index: 0; }
            .sseo-onboarding-progress-step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 1; flex: 1; }
            .sseo-onboarding-progress-circle { width: 40px; height: 40px; border-radius: 50%; background: #fff; border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 16px; color: #9ca3af; margin-bottom: 8px; }
            .sseo-onboarding-progress-step.active .sseo-onboarding-progress-circle { border-color: <?php echo esc_attr($primaryColor); ?>; background: <?php echo esc_attr($primaryColor); ?>; color: #fff; }
            .sseo-onboarding-progress-step.completed .sseo-onboarding-progress-circle { border-color: #10b981; background: #10b981; color: #fff; }
            .sseo-onboarding-progress-label { font-size: 13px; color: #6b7280; }
            .sseo-onboarding-progress-step.active .sseo-onboarding-progress-label { color: <?php echo esc_attr($primaryColor); ?>; font-weight: 600; }
            .sseo-onboarding-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
            .sseo-onboarding-card h2 { font-size: 22px; font-weight: 700; margin: 0 0 8px 0; color: #111827; }
            .sseo-onboarding-card .description { color: #6b7280; margin-bottom: 30px; }
            .sseo-onboarding-field { margin-bottom: 20px; }
            .sseo-onboarding-field label { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px; }
            .sseo-onboarding-field input, .sseo-onboarding-field select { width: 100%; padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 15px; }
            .sseo-onboarding-field input:focus, .sseo-onboarding-field select:focus { border-color: <?php echo esc_attr($primaryColor); ?>; outline: none; }
            .sseo-onboarding-field .hint { font-size: 13px; color: #9ca3af; margin-top: 4px; }
            .sseo-onboarding-checkbox-group { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; margin-bottom: 24px; }
            .sseo-onboarding-checkbox-item { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 6px; cursor: pointer; }
            .sseo-onboarding-checkbox-item:hover { border-color: <?php echo esc_attr($primaryColor); ?>; }
            .sseo-onboarding-checkbox-item input { margin: 0; }
            .sseo-onboarding-actions { display: flex; justify-content: space-between; margin-top: 30px; }
            .sseo-onboarding-actions .button-primary { background: <?php echo esc_attr($primaryColor); ?>; border-color: <?php echo esc_attr($primaryColor); ?>; padding: 8px 24px; font-size: 15px; }
            .sseo-onboarding-actions .button-secondary { padding: 8px 24px; font-size: 15px; }
            .sseo-onboarding-skip { color: #9ca3af; text-decoration: none; font-size: 14px; line-height: 34px; }
        </style>

        <div class="sseo-onboarding">
            <div class="sseo-onboarding-header">
                <h1><?php echo esc_html(sprintf(__('Welcome to %s', 'ai-seo-client'), $brandName)); ?></h1>
                <p><?php esc_html_e('Let\'s get your site SEO-ready in 4 quick steps.', 'ai-seo-client'); ?></p>
            </div>

            <div class="sseo-onboarding-progress">
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <div class="sseo-onboarding-progress-step <?php echo $i < $currentStep ? 'completed' : ($i === $currentStep ? 'active' : ''); ?>">
                        <div class="sseo-onboarding-progress-circle">
                            <?php echo $i < $currentStep ? 'âœ“' : $i; ?>
                        </div>
                        <div class="sseo-onboarding-progress-label"><?php echo esc_html($steps[$i]); ?></div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="sseo-onboarding-card">
                <?php if ($onboardingError): ?>
                    <div class="notice notice-error" style="margin-bottom: 20px;">
                        <p><?php echo esc_html($onboardingError); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('sseo_ai_onboarding'); ?>
                    <input type="hidden" name="action" value="sseo_ai_onboarding_save">
                    <input type="hidden" name="step" value="<?php echo esc_attr($currentStep); ?>">

                    <?php switch ($currentStep):
                        case 1: ?>
                            <h2><?php esc_html_e('Connect Your License', 'ai-seo-client'); ?></h2>
                            <p class="description">
                                <?php echo $freeTierEnabled
                                    ? esc_html__('Enter your license key to unlock all features, or skip to use the free tier.', 'ai-seo-client')
                                    : esc_html__('Enter your license key to unlock all features.', 'ai-seo-client'); ?>
                            </p>

                            <div class="sseo-onboarding-field">
                                <label for="dashboard_url"><?php esc_html_e('SaaS Dashboard URL', 'ai-seo-client'); ?></label>
                                <input type="url" id="dashboard_url" name="dashboard_url" placeholder="https://dashboard.example.com" value="<?php echo esc_attr(get_option('sseo_ai_client_dashboard_url', '')); ?>">
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="license_key"><?php esc_html_e('License Key', 'ai-seo-client'); ?></label>
                                <input type="text" id="license_key" name="license_key" placeholder="SSEO-AI-XXXX-XXXX-XXXX" value="">
                                <?php if ($freeTierEnabled): ?>
                                    <div class="hint"><?php esc_html_e('Skip this step to use the free tier with limited features.', 'ai-seo-client'); ?></div>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="license_action" value="activate">

                            <div class="sseo-onboarding-actions">
                                <?php if ($freeTierEnabled): ?>
                                    <button type="submit" name="license_action" value="skip" class="button button-secondary" style="margin-right: auto;"><?php esc_html_e('Skip for now â†’', 'ai-seo-client'); ?></button>
                                <?php endif; ?>
                                <button type="submit" name="license_action" value="activate" class="button button-primary" style="margin-left: auto;"><?php esc_html_e('Continue', 'ai-seo-client'); ?> â†’</button>
                            </div>
                            <?php break;

                        case 2: ?>
                            <h2><?php esc_html_e('Company Branding', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Tell us about your company to personalize your SEO experience.', 'ai-seo-client'); ?></p>

                            <div class="sseo-onboarding-field">
                                <label for="company_name"><?php esc_html_e('Company Name', 'ai-seo-client'); ?></label>
                                <input type="text" id="company_name" name="company_name" placeholder="Your Company Name" value="<?php echo esc_attr(get_option('sseo_ai_wl_company_name', '')); ?>">
                                <div class="hint"><?php esc_html_e('Leave empty if already set by your license provider.', 'ai-seo-client'); ?></div>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="company_logo"><?php esc_html_e('Company Logo (optional)', 'ai-seo-client'); ?></label>
                                <input type="file" id="company_logo" name="company_logo" accept="image/*">
                                <div class="hint"><?php esc_html_e('Upload your company logo for branded reports and dashboards.', 'ai-seo-client'); ?></div>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="website_description"><?php esc_html_e('Website Description', 'ai-seo-client'); ?></label>
                                <textarea id="website_description" name="website_description" rows="3" placeholder="Brief description of your website or business..."><?php echo esc_textarea(get_option('sseo_ai_website_description', '')); ?></textarea>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="industry"><?php esc_html_e('Industry/Category', 'ai-seo-client'); ?></label>
                                <select id="industry" name="industry">
                                    <option value=""><?php esc_html_e('Select your industry...', 'ai-seo-client'); ?></option>
                                    <option value="technology" <?php selected(get_option('sseo_ai_industry'), 'technology'); ?>><?php esc_html_e('Technology', 'ai-seo-client'); ?></option>
                                    <option value="healthcare" <?php selected(get_option('sseo_ai_industry'), 'healthcare'); ?>><?php esc_html_e('Healthcare', 'ai-seo-client'); ?></option>
                                    <option value="finance" <?php selected(get_option('sseo_ai_industry'), 'finance'); ?>><?php esc_html_e('Finance', 'ai-seo-client'); ?></option>
                                    <option value="retail" <?php selected(get_option('sseo_ai_industry'), 'retail'); ?>><?php esc_html_e('Retail/E-commerce', 'ai-seo-client'); ?></option>
                                    <option value="education" <?php selected(get_option('sseo_ai_industry'), 'education'); ?>><?php esc_html_e('Education', 'ai-seo-client'); ?></option>
                                    <option value="travel" <?php selected(get_option('sseo_ai_industry'), 'travel'); ?>><?php esc_html_e('Travel/Hospitality', 'ai-seo-client'); ?></option>
                                    <option value="realestate" <?php selected(get_option('sseo_ai_industry'), 'realestate'); ?>><?php esc_html_e('Real Estate', 'ai-seo-client'); ?></option>
                                    <option value="other" <?php selected(get_option('sseo_ai_industry'), 'other'); ?>><?php esc_html_e('Other', 'ai-seo-client'); ?></option>
                                </select>
                            </div>

                            <div class="sseo-onboarding-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=1')); ?>" class="button button-secondary">â† <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> â†’</button>
                            </div>
                            <?php break;

                        case 3: ?>
                            <?php 
                            $brandVoiceSettings = get_option('sseo_ai_brand_voice', []);
                            $currentTone = is_array($brandVoiceSettings) ? ($brandVoiceSettings['tone'] ?? 'professional') : 'professional';
                            $currentStyle = is_array($brandVoiceSettings) ? ($brandVoiceSettings['style'] ?? 'conversational') : 'conversational';
                            $currentAudience = is_array($brandVoiceSettings) ? ($brandVoiceSettings['audience'] ?? '') : '';
                            $currentDescription = is_array($brandVoiceSettings) ? ($brandVoiceSettings['voice_description'] ?? '') : '';
                            ?>
                            <h2><?php esc_html_e('Tone of Voice', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Configure how AI-generated content should sound for your brand.', 'ai-seo-client'); ?></p>

                            <div class="sseo-onboarding-field">
                                <label for="brand_voice"><?php esc_html_e('Brand Voice', 'ai-seo-client'); ?></label>
                                <select id="brand_voice" name="brand_voice">
                                    <option value="professional" <?php selected($currentTone, 'professional'); ?>><?php esc_html_e('Professional', 'ai-seo-client'); ?></option>
                                    <option value="casual" <?php selected($currentTone, 'casual'); ?>><?php esc_html_e('Casual', 'ai-seo-client'); ?></option>
                                    <option value="friendly" <?php selected($currentTone, 'friendly'); ?>><?php esc_html_e('Friendly', 'ai-seo-client'); ?></option>
                                    <option value="authoritative" <?php selected($currentTone, 'authoritative'); ?>><?php esc_html_e('Authoritative', 'ai-seo-client'); ?></option>
                                    <option value="humorous" <?php selected($currentTone, 'humorous'); ?>><?php esc_html_e('Humorous', 'ai-seo-client'); ?></option>
                                    <option value="technical" <?php selected($currentTone, 'technical'); ?>><?php esc_html_e('Technical', 'ai-seo-client'); ?></option>
                                </select>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="target_audience"><?php esc_html_e('Target Audience', 'ai-seo-client'); ?></label>
                                <textarea id="target_audience" name="target_audience" rows="3" placeholder="Describe your target audience (e.g., small business owners, developers, parents...)"><?php echo esc_textarea($currentAudience); ?></textarea>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="writing_style"><?php esc_html_e('Writing Style', 'ai-seo-client'); ?></label>
                                <select id="writing_style" name="writing_style">
                                    <option value="conversational" <?php selected($currentStyle, 'conversational'); ?>><?php esc_html_e('Conversational', 'ai-seo-client'); ?></option>
                                    <option value="formal" <?php selected($currentStyle, 'formal'); ?>><?php esc_html_e('Formal', 'ai-seo-client'); ?></option>
                                    <option value="technical" <?php selected($currentStyle, 'technical'); ?>><?php esc_html_e('Technical', 'ai-seo-client'); ?></option>
                                    <option value="persuasive" <?php selected($currentStyle, 'persuasive'); ?>><?php esc_html_e('Persuasive', 'ai-seo-client'); ?></option>
                                    <option value="educational" <?php selected($currentStyle, 'educational'); ?>><?php esc_html_e('Educational', 'ai-seo-client'); ?></option>
                                </select>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="brand_values"><?php esc_html_e('Key Brand Values/Phrases (optional)', 'ai-seo-client'); ?></label>
                                <textarea id="brand_values" name="brand_values" rows="3" placeholder="Key phrases or values to include in AI content (e.g., innovation, customer-first, sustainability...)"><?php echo esc_textarea($currentDescription); ?></textarea>
                                <div class="hint"><?php esc_html_e('These will help AI content align with your brand messaging.', 'ai-seo-client'); ?></div>
                            </div>

                            <div class="sseo-onboarding-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=2')); ?>" class="button button-secondary">â† <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> â†’</button>
                            </div>
                            <?php break;

                        case 4: ?>
                            <h2><?php esc_html_e('Basic SEO Configuration', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Set up your default title and meta description templates.', 'ai-seo-client'); ?></p>

                            <div class="sseo-onboarding-field">
                                <label for="title_template"><?php esc_html_e('Title Template', 'ai-seo-client'); ?></label>
                                <input type="text" id="title_template" name="title_template" value="<?php echo esc_attr(get_option('sseo_ai_title_template', '%title% %separator% %sitename%')); ?>">
                                <div class="hint"><?php esc_html_e('Available variables: %title%, %separator%, %sitename%', 'ai-seo-client'); ?></div>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="separator"><?php esc_html_e('Title Separator', 'ai-seo-client'); ?></label>
                                <select id="separator" name="separator">
                                    <?php $seps = ['â€“', 'â€”', '|', 'Â·', 'â€¢', 'Â»', 'Â«', ':']; $current = get_option('sseo_ai_separator', 'â€“'); ?>
                                    <?php foreach ($seps as $sep): ?>
                                        <option value="<?php echo esc_attr($sep); ?>" <?php selected($current, $sep); ?>><?php echo esc_html($sep); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="social_facebook"><?php esc_html_e('Facebook URL (optional)', 'ai-seo-client'); ?></label>
                                <input type="url" id="social_facebook" name="social_facebook" placeholder="https://facebook.com/yourpage" value="<?php echo esc_attr(get_option('sseo_ai_social_facebook', '')); ?>">
                            </div>
                            <div class="sseo-onboarding-field">
                                <label for="social_twitter"><?php esc_html_e('Twitter/X URL (optional)', 'ai-seo-client'); ?></label>
                                <input type="url" id="social_twitter" name="social_twitter" placeholder="https://twitter.com/yourhandle" value="<?php echo esc_attr(get_option('sseo_ai_social_twitter', '')); ?>">
                            </div>

                            <div class="sseo-onboarding-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=3')); ?>" class="button button-secondary">â† <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> â†’</button>
                            </div>
                            <?php break;

                        case 5: ?>
                            <h2><?php esc_html_e('Content Types & Features', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Select which content types should have SEO features enabled.', 'ai-seo-client'); ?></p>

                            <div class="sseo-onboarding-checkbox-group">
                                <?php
                                $postTypes = get_post_types(['public' => true], 'objects');
                                $enabledTypes = get_option('sseo_ai_enabled_post_types', ['post', 'page']);
                                foreach ($postTypes as $type):
                                    $checked = in_array($type->name, $enabledTypes) || in_array($type->name, ['post', 'page']);
                                ?>
                                    <label class="sseo-onboarding-checkbox-item">
                                        <input type="checkbox" name="post_types[<?php echo esc_attr($type->name); ?>]" value="1" <?php checked($checked); ?>>
                                        <?php echo esc_html($type->labels->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div style="border-top: 1px solid #e5e7eb; padding-top: 24px; margin-bottom: 24px;">
                                <label class="sseo-onboarding-checkbox-item" style="margin-bottom: 12px;">
                                    <input type="checkbox" name="enable_sitemap" value="1" <?php checked(get_option('sseo_ai_enable_sitemap', '1'), '1'); ?>>
                                    <?php esc_html_e('Enable XML Sitemap', 'ai-seo-client'); ?>
                                </label>
                                <label class="sseo-onboarding-checkbox-item" style="margin-bottom: 12px;">
                                    <input type="checkbox" name="enable_robots" value="1" <?php checked(get_option('sseo_ai_enable_robots', '1'), '1'); ?>>
                                    <?php esc_html_e('Enable robots.txt editor', 'ai-seo-client'); ?>
                                </label>
                                <label class="sseo-onboarding-checkbox-item">
                                    <input type="checkbox" name="enable_schema" value="1" <?php checked(get_option('sseo_ai_enable_schema', '1'), '1'); ?>>
                                    <?php esc_html_e('Enable Schema Markup', 'ai-seo-client'); ?>
                                </label>
                            </div>

                            <div class="sseo-onboarding-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=4')); ?>" class="button button-secondary">â† <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> â†’</button>
                            </div>
                            <?php break;

                        case 6: ?>
                            <h2><?php esc_html_e('Google Integrations (Optional)', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('Connect Google services for enhanced SEO insights. You can always do this later.', 'ai-seo-client'); ?></p>

                            <div class="sseo-onboarding-field">
                                <label for="pagespeed_api_key"><?php esc_html_e('Google PageSpeed API Key (optional)', 'ai-seo-client'); ?></label>
                                <input type="text" id="pagespeed_api_key" name="pagespeed_api_key" placeholder="AIza..." value="<?php echo esc_attr(get_option('sseo_ai_pagespeed_api_key', '')); ?>">
                                <div class="hint">
                                    <?php echo wp_kses_post(sprintf(__('Get a free API key from <a href="%s" target="_blank">Google Cloud Console</a>. Free tier: 25 requests/day.', 'ai-seo-client'), 'https://console.cloud.google.com/apis/library/pagespeedapi.googleapis.com')); ?>
                                </div>
                            </div>

                            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                                <p style="margin: 0; color: #0369a1; font-size: 14px;">
                                    <strong><?php esc_html_e('Google Search Console', 'ai-seo-client'); ?></strong><br>
                                    <?php esc_html_e('You can connect GSC later from the Integrations page after setup.', 'ai-seo-client'); ?>
                                </p>
                            </div>

                            <div class="sseo-onboarding-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=5')); ?>" class="button button-secondary">â† <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> â†’</button>
                            </div>
                            <?php break;

                        case 7: ?>
                            <h2><?php esc_html_e('Setup Complete!', 'ai-seo-client'); ?></h2>
                            <p class="description"><?php esc_html_e('You're all set. Here's a summary of your configuration:', 'ai-seo-client'); ?></p>

                            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                                <h3 style="margin-top: 0; font-size: 16px; color: #111827;"><?php esc_html_e('Configuration Summary', 'ai-seo-client'); ?></h3>
                                <ul style="margin: 0; padding-left: 20px; color: #374151; font-size: 14px;">
                                    <li><?php esc_html_e('License: Connected', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Company Branding: Configured', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Tone of Voice: Set', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('SEO Settings: Configured', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Content Types: Selected', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Integrations: Optional (can be done later)', 'ai-seo-client'); ?></li>
                                </ul>
                            </div>

                            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 20px; margin-bottom: 24px;">
                                <h3 style="margin-top: 0; font-size: 16px; color: #065f46;"><?php esc_html_e('Quick Start Tips', 'ai-seo-client'); ?></h3>
                                <ul style="margin: 0; padding-left: 20px; color: #047857; font-size: 14px;">
                                    <li><?php esc_html_e('Check your SEO Dashboard for site health overview', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Use the Content Writer to generate SEO-optimized articles', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Connect Google Search Console for search performance data', 'ai-seo-client'); ?></li>
                                    <li><?php esc_html_e('Review your SEO settings in Settings > Fyndable', 'ai-seo-client'); ?></li>
                                </ul>
                            </div>

                            <div class="sseo-onboarding-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=6')); ?>" class="button button-secondary">â† <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Go to Dashboard', 'ai-seo-client'); ?> âœ“</button>
                            </div>
                            <?php break;
                    endswitch; ?>
                </form>
            </div>
        </div>
        <?php
    }
}

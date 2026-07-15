<?php

namespace SSEOAIClient;

/**
 * Onboarding Wizard
 *
 * 4-step guided setup shown on first activation:
 * 1. License connect (or skip for free tier)
 * 2. Basic SEO config (title templates, social profiles)
 * 3. Content type selection (which post types to enable SEO for)
 * 4. Optional Google integrations (GSC, GA4, PageSpeed)
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

        if (!empty($result['white_label'])) {
            update_option('sseo_ai_white_label', $result['white_label']);
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
     * Step 2: Basic SEO configuration.
     */
    private function saveStep2(): void
    {
        $titleTemplate = sanitize_text_field($_POST['title_template'] ?? '%title% %separator% %sitename%');
        $descriptionTemplate = sanitize_text_field($_POST['description_template'] ?? '%excerpt%');
        $separator = sanitize_text_field($_POST['separator'] ?? '–');
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
     * Step 3: Content type selection.
     */
    private function saveStep3(): void
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
     * Step 4: Optional Google integrations (skip for now, just mark complete).
     */
    private function saveStep4(): void
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
        $currentStep = max(1, min(4, $currentStep));

        $freeTierEnabled = (bool) get_option('sseo_ai_free_tier_enabled', false);
        $onboardingError = isset($_GET['onboarding_error']) ? sanitize_text_field(urldecode($_GET['onboarding_error'])) : '';

        $whiteLabel = get_option('sseo_ai_white_label', []);
        $companyName = !empty($whiteLabel['company_name']) ? $whiteLabel['company_name'] : 'Fyndable';
        $brandName = $companyName . ' Smart SEO';

        $steps = [
            1 => __('Connect', 'ai-seo-client'),
            2 => __('SEO Setup', 'ai-seo-client'),
            3 => __('Content Types', 'ai-seo-client'),
            4 => __('Integrations', 'ai-seo-client'),
        ];

        $primaryColor = '#3b82f6';
        $secondaryColor = '#ec4899';
        ?>
        <style>
            .sseo-onboarding { max-width: 700px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
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
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div class="sseo-onboarding-progress-step <?php echo $i < $currentStep ? 'completed' : ($i === $currentStep ? 'active' : ''); ?>">
                        <div class="sseo-onboarding-progress-circle">
                            <?php echo $i < $currentStep ? '✓' : $i; ?>
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

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
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
                                    <button type="submit" name="license_action" value="skip" class="button button-secondary" style="margin-right: auto;"><?php esc_html_e('Skip for now →', 'ai-seo-client'); ?></button>
                                <?php endif; ?>
                                <button type="submit" name="license_action" value="activate" class="button button-primary" style="margin-left: auto;"><?php esc_html_e('Continue', 'ai-seo-client'); ?> →</button>
                            </div>
                            <?php break;

                        case 2: ?>
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
                                    <?php $seps = ['–', '—', '|', '·', '•', '»', '«', ':']; $current = get_option('sseo_ai_separator', '–'); ?>
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
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=1')); ?>" class="button button-secondary">← <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> →</button>
                            </div>
                            <?php break;

                        case 3: ?>
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
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=2')); ?>" class="button button-secondary">← <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Continue', 'ai-seo-client'); ?> →</button>
                            </div>
                            <?php break;

                        case 4: ?>
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
                                <a href="<?php echo esc_url(admin_url('admin.php?page=ai-seo-onboarding&step=3')); ?>" class="button button-secondary">← <?php esc_html_e('Back', 'ai-seo-client'); ?></a>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Finish Setup', 'ai-seo-client'); ?> ✓</button>
                            </div>
                            <?php break;
                    endswitch; ?>
                </form>
            </div>
        </div>
        <?php
    }
}

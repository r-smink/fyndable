<?php

namespace SSEOAIClient;

/**
 * White-Label Branding Manager
 *
 * Applies white-label branding (colors, logo, login screen) based on
 * settings received from the SaaS dashboard via license activation.
 * No local settings page — branding is managed centrally.
 */
class WhiteLabelManager
{
    private Settings $settings;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }
    
    public function register(): void
    {
        // Apply white-label branding (settings come from SaaS dashboard)
        add_filter('admin_footer_text', [$this, 'customAdminFooter']);
        add_action('admin_head', [$this, 'customAdminStyles']);
        add_action('login_head', [$this, 'customLoginStyles']);
        add_filter('login_headerurl', [$this, 'customLoginUrl']);
        add_filter('login_headertext', [$this, 'customLoginText']);
        add_filter('login_title', [$this, 'customLoginTitle'], 10, 2);
        add_filter('login_body_class', [$this, 'customLoginBodyClass']);

        // Client portal
        add_action('init', [$this, 'registerClientPortalEndpoint']);
        add_filter('template_include', [$this, 'clientPortalTemplate']);
    }
    
    /**
     * Apply custom admin footer
     */
    public function customAdminFooter(string $text): string
    {
        $footerText = get_option('sseo_ai_wl_footer_text', '');
        
        if (!empty($footerText)) {
            return $footerText;
        }
        
        return $text;
    }
    
    /**
     * Apply custom admin styles
     */
    public function customAdminStyles(): void
    {
        $primaryColor = get_option('sseo_ai_wl_primary_color', '');
        $secondaryColor = get_option('sseo_ai_wl_secondary_color', '');
        
        if (empty($primaryColor)) {
            return;
        }
        
        ?>
        <style>
        .button-primary {
            background-color: <?php echo esc_attr($primaryColor); ?> !important;
            border-color: <?php echo esc_attr($secondaryColor); ?> !important;
        }
        .button-primary:hover {
            background-color: <?php echo esc_attr($secondaryColor); ?> !important;
        }
        </style>
        <?php
    }
    
    /**
     * Apply custom login styles
     */
    public function customLoginStyles(): void
    {
        if ($this->isFynableLoginEnabled()) {
            $this->renderFynableLoginStyles();
            return;
        }

        $companyLogo = get_option('sseo_ai_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_wl_primary_color', '');

        if (empty($companyLogo) && empty($primaryColor)) {
            return;
        }

        ?>
        <style>
        <?php if ($companyLogo): ?>
        #login h1 a {
            background-image: url('<?php echo esc_url($companyLogo); ?>');
            background-size: contain;
            width: 100%;
        }
        <?php endif; ?>

        <?php if ($primaryColor): ?>
        .wp-core-ui .button-primary {
            background-color: <?php echo esc_attr($primaryColor); ?>;
        }
        <?php endif; ?>
        </style>
        <?php
    }

    /**
     * Render a fully Fynable-branded WordPress login page
     */
    private function renderFynableLoginStyles(): void
    {
        $companyName = get_option('sseo_ai_wl_company_name', 'Fyndable');
        $companyLogo = get_option('sseo_ai_wl_company_logo', '');
        $primaryColor = get_option('sseo_ai_wl_primary_color', '#2271b1');
        $secondaryColor = get_option('sseo_ai_wl_secondary_color', '#135e96');
        $title = get_option('sseo_ai_fynable_login_title', $companyName . ' Smart SEO');
        $bgColor = get_option('sseo_ai_fynable_login_bg_color', '#f0f4f8');
        $bgImage = get_option('sseo_ai_fynable_login_bg_image', '');

        $logoCss = $companyLogo ? 'background-image: url(' . esc_url($companyLogo) . ') !important;' : '';
        $bgImageCss = $bgImage ? 'background-image: url(' . esc_url($bgImage) . ');' : '';

        ?>
        <style>
        body.sseo-fynable-login {
            background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
            <?php echo $bgImage ? 'background-size: cover; background-position: center;' : ''; ?>
            <?php echo $bgImageCss; ?>
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        body.sseo-fynable-login #login {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        body.sseo-fynable-login #login h1 a {
            <?php echo $logoCss; ?>
            background-size: contain;
            width: 100%;
            height: 64px;
            margin-bottom: 20px;
        }
        body.sseo-fynable-login #login h1 a::after {
            content: '<?php echo esc_js($title); ?>';
            display: block;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            text-indent: 0;
            margin-top: 70px;
        }
        body.sseo-fynable-login .message,
        body.sseo-fynable-login #login_error {
            border-radius: 8px;
            border-left-color: <?php echo esc_attr($primaryColor); ?>;
        }
        body.sseo-fynable-login #loginform,
        body.sseo-fynable-login #lostpasswordform,
        body.sseo-fynable-login #registerform {
            border: none;
            box-shadow: none;
            padding: 0;
        }
        body.sseo-fynable-login #loginform .input,
        body.sseo-fynable-login #lostpasswordform .input,
        body.sseo-fynable-login #registerform .input {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
        }
        body.sseo-fynable-login #loginform .input:focus,
        body.sseo-fynable-login #lostpasswordform .input:focus,
        body.sseo-fynable-login #registerform .input:focus {
            border-color: <?php echo esc_attr($primaryColor); ?>;
            box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        }
        body.sseo-fynable-login .wp-core-ui .button-primary {
            background-color: <?php echo esc_attr($primaryColor); ?>;
            border-color: <?php echo esc_attr($primaryColor); ?>;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            width: 100%;
            transition: background-color 0.2s;
        }
        body.sseo-fynable-login .wp-core-ui .button-primary:hover {
            background-color: <?php echo esc_attr($secondaryColor); ?>;
            border-color: <?php echo esc_attr($secondaryColor); ?>;
        }
        body.sseo-fynable-login #nav,
        body.sseo-fynable-login #backtoblog {
            text-align: center;
            padding: 0;
            margin-top: 16px;
        }
        body.sseo-fynable-login #nav a,
        body.sseo-fynable-login #backtoblog a {
            color: <?php echo esc_attr($primaryColor); ?>;
            text-decoration: none;
        }
        body.sseo-fynable-login #nav a:hover,
        body.sseo-fynable-login #backtoblog a:hover {
            text-decoration: underline;
        }
        </style>
        <?php
    }

    /**
     * Custom login URL
     */
    public function customLoginUrl(string $url): string
    {
        return $this->isFynableLoginEnabled() ? home_url('/') : $url;
    }

    /**
     * Custom login header text
     */
    public function customLoginText(string $text): string
    {
        if (!$this->isFynableLoginEnabled()) {
            return $text;
        }

        $companyName = get_option('sseo_ai_wl_company_name', 'Fyndable');
        return $companyName ?: 'Fyndable';
    }

    /**
     * Custom login title
     */
    public function customLoginTitle(string $title, string $sep): string
    {
        if (!$this->isFynableLoginEnabled()) {
            return $title;
        }

        $companyName = get_option('sseo_ai_wl_company_name', 'Fyndable');
        $loginTitle = get_option('sseo_ai_fynable_login_title', $companyName . ' Smart SEO');
        return $loginTitle . ' ' . $sep . ' ' . get_bloginfo('name');
    }

    /**
     * Add custom body class to login page
     */
    public function customLoginBodyClass(array $classes): array
    {
        if ($this->isFynableLoginEnabled()) {
            $classes[] = 'sseo-fynable-login';
        }
        return $classes;
    }

    /**
     * Check if Fynable login is enabled
     */
    private function isFynableLoginEnabled(): bool
    {
        return (bool) get_option('sseo_ai_fynable_login_enabled', false);
    }

    /**
     * Check if free tier is enabled
     */
    private function isFreeTierEnabled(): bool
    {
        return (bool) get_option('sseo_ai_free_tier_enabled', false);
    }
    
    /**
     * Register client portal endpoint
     */
    public function registerClientPortalEndpoint(): void
    {
        $portalEnabled = get_option('sseo_ai_wl_portal_enabled', false);
        
        if (!$portalEnabled) {
            return;
        }
        
        $slug = get_option('sseo_ai_wl_portal_slug', 'seo-portal');
        add_rewrite_rule("^{$slug}/?", 'index.php?sseo_client_portal=1', 'top');
        add_rewrite_tag('%sseo_client_portal%', '([^&]+)');
    }
    
    /**
     * Client portal template
     */
    public function clientPortalTemplate($template)
    {
        if (get_query_var('sseo_client_portal')) {
            return SSEO_AI_CLIENT_PLUGIN_DIR . 'templates/client-portal.php';
        }
        
        return $template;
    }
    
}

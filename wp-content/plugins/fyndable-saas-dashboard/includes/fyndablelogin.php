<?php

namespace SSEOAISaaS;

/**
 * Fyndable Login
 *
 * Styles the WordPress login page with the Fyndable brand gradient
 * and redirects users to the SaaS dashboard shell after login.
 * Also provides secure auto-login links for agency-to-tenant access.
 */
class FyndableLogin
{
    private TenantRepository $tenants;
    private AgencyRoleManager $agencyRoleManager;

    public function __construct(TenantRepository $tenants, AgencyRoleManager $agencyRoleManager)
    {
        $this->tenants = $tenants;
        $this->agencyRoleManager = $agencyRoleManager;
    }

    public function register(): void
    {
        add_action('login_head', [$this, 'renderLoginStyle']);
        add_filter('login_redirect', [$this, 'filterLoginRedirect'], 10, 3);
        add_filter('login_message', [$this, 'filterLoginMessage']);
        add_filter('login_headerurl', [$this, 'getLoginHeaderUrl']);
        add_filter('login_headertext', [$this, 'getLoginHeaderText']);
        add_shortcode('fyndable_login', [$this, 'renderLoginShortcode']);
        add_action('init', [$this, 'handleAutoLogin']);
        add_action('init', [$this, 'addSetPasswordRewrite']);
        add_action('init', [$this, 'maybeFlushRewrites']);
    }

    /**
     * Add a clean /set-password rewrite rule that maps to the WordPress
     * password reset handler (wp-login.php?action=rp). Query params such as
     * key, login and redirect_to are passed through automatically.
     */
    public function addSetPasswordRewrite(): void
    {
        add_rewrite_rule('^set-password/?$', 'wp-login.php?action=rp', 'top');
    }

    /**
     * Flush rewrite rules once after the /set-password rule is introduced,
     * so existing installs pick it up without a manual Permalinks save.
     */
    public function maybeFlushRewrites(): void
    {
        if (!get_option('sseo_ai_set_password_rewrite_flushed')) {
            flush_rewrite_rules();
            update_option('sseo_ai_set_password_rewrite_flushed', true);
        }
    }

    /**
     * Inject Fyndable brand styling into the WordPress login page.
     */
    public function renderLoginStyle(): void
    {
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $brandName = $companyName ?: 'Fyndable';
        ?>
        <style>
            body.login {
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%) !important;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #login {
                width: 420px;
                max-width: 90vw;
                padding: 0;
                margin: 0 auto;
            }
            #loginform, #registerform, #lostpasswordform {
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                padding: 40px;
                border: none;
            }
            .login h1 {
                margin-bottom: 24px;
            }
            .login h1 a {
                background-image: none !important;
                width: auto !important;
                height: auto !important;
                text-indent: 0 !important;
                font-size: 28px;
                font-weight: 700;
                color: #fff;
                text-decoration: none;
                display: block;
                text-align: center;
                padding: 16px 0;
                letter-spacing: -0.5px;
            }
            .login h1 a span {
                font-weight: 400;
                opacity: 0.85;
            }
            .login form .input, .login input[type="text"], .login input[type="email"], .login input[type="password"] {
                border-radius: 8px;
                border: 2px solid #e5e7eb;
                padding: 12px 14px;
                font-size: 15px;
                transition: border-color 0.15s;
            }
            .login form .input:focus, .login input[type="text"]:focus, .login input[type="email"]:focus, .login input[type="password"]:focus {
                border-color: #379fd3;
                box-shadow: 0 0 0 3px rgba(55,159,211,0.1);
            }
            .login form .forgetmenot {
                float: none;
                margin-bottom: 16px;
            }
            .login .button-primary {
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                border: none;
                border-radius: 8px;
                padding: 12px;
                font-size: 15px;
                font-weight: 600;
                height: auto;
                width: 100%;
                text-shadow: none;
                box-shadow: 0 4px 12px rgba(55,159,211,0.3);
                transition: transform 0.15s, box-shadow 0.15s;
            }
            .login .button-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(55,159,211,0.4);
            }
            .login .button-secondary {
                border-radius: 8px;
            }
            #login form p {
                margin-bottom: 16px;
            }
            #login .forgetmenot label {
                font-size: 14px;
                color: #6b7280;
            }
            #backtoblog, .login #nav {
                text-align: center;
                padding: 12px 0;
            }
            #backtoblog a, .login #nav a {
                color: rgba(255,255,255,0.9) !important;
                text-decoration: none;
                font-size: 13px;
            }
            #backtoblog a:hover, .login #nav a:hover {
                color: #fff !important;
                text-decoration: underline;
            }
            .login .message, .login .success, .login #login_error {
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .language-switcher {
                display: none;
            }
            /* Password visibility toggle (eye icon) */
            .sseo-pwd-wrap { position: relative; }
            .sseo-pwd-toggle {
                position: absolute;
                top: 50%;
                right: 12px;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 0;
                margin: 0;
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6b7280;
                line-height: 1;
            }
            .sseo-pwd-toggle:hover { color: #379fd3; }
            .sseo-pwd-toggle svg { width: 20px; height: 20px; display: block; }
            .login form .input.sseo-has-toggle,
            .login input[type="password"].sseo-has-toggle,
            .fyndable-login-field input.sseo-has-toggle {
                padding-right: 44px;
            }
        </style>
        <script>
        (function () {
            function makeToggle(input) {
                if (!input || input.dataset.sseoToggle === '1') return;
                input.dataset.sseoToggle = '1';
                var wrap = document.createElement('div');
                wrap.className = 'sseo-pwd-wrap';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
                input.classList.add('sseo-has-toggle');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'sseo-pwd-toggle';
                btn.setAttribute('aria-label', 'Toon of verberg wachtwoord');
                btn.tabIndex = -1;
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
                btn.addEventListener('click', function () {
                    var isPwd = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPwd ? 'text' : 'password');
                    btn.innerHTML = isPwd
                        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
                    input.focus();
                });
                wrap.appendChild(btn);
            }
            function init() {
                var wp = document.getElementById('user_pass');
                if (wp) makeToggle(wp);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else { init(); }
        })();
        </script>
        <?php
    }

    /**
     * Redirect users to the SaaS dashboard shell after login.
     * Agency users go to the agency portal, admins go to the shell.
     */
    public function filterLoginRedirect($redirect_to, $requested_redirect_to, $user)
    {
        if (!is_wp_error($user) && $user instanceof \WP_User) {
            if (in_array('fyndable_customer', (array)$user->roles, true)) {
                $portalPageId = (int) get_option('sseo_ai_saas_customer_portal_page', 0);
                if ($portalPageId > 0) {
                    $url = get_permalink($portalPageId);
                    if ($url) {
                        return $url;
                    }
                }
                // No portal page configured — redirect to /dashboard.
                return home_url('/dashboard/');
            }
            if (in_array('agency_partner', (array)$user->roles, true)) {
                return admin_url('admin.php?page=sseo-ai-shell');
            }
            if (in_array('administrator', (array)$user->roles, true)) {
                return admin_url('admin.php?page=sseo-ai-shell');
            }
        }
        return $requested_redirect_to ?: admin_url('admin.php?page=sseo-ai-shell');
    }

    /**
     * Show a welcome message on the login form.
     */
    public function filterLoginMessage($message)
    {
        if (empty($message)) {
            $enabled = get_option('sseo_ai_saas_wl_enabled', false);
            $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
            $brandName = $companyName ?: 'Fyndable';
            return '<p style="text-align:center;color:#6b7280;font-size:14px;margin-bottom:20px;">'
                . esc_html(sprintf(__('Welcome to %s — sign in to your dashboard', 'sseo-ai-saas'), $brandName))
                . '</p>';
        }
        return $message;
    }

    public function getLoginHeaderUrl(): string
    {
        return home_url('/');
    }

    public function getLoginHeaderText(): string
    {
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $brandName = $companyName ?: 'Fyndable';
        return $brandName . ' <span>Smart SEO</span>';
    }

    /**
     * Generate a secure auto-login link for a tenant.
     * Only callable by agency partners or admins.
     */
    public function generateTenantLoginLink(int $tenantId): string|\WP_Error
    {
        $user = wp_get_current_user();
        if (!$user || !$user->exists()) {
            return new \WP_Error('not_logged_in', __('You must be logged in.', 'sseo-ai-saas'));
        }

        $isAgency = $this->agencyRoleManager->isAgencyUser($user);
        $isAdmin = in_array('administrator', (array)$user->roles, true);

        if (!$isAgency && !$isAdmin) {
            return new \WP_Error('no_permission', __('You do not have permission to generate login links.', 'sseo-ai-saas'));
        }

        if ($isAgency) {
            $agencyTenantId = $this->agencyRoleManager->getAgencyTenantId($user->ID);
            $tenant = $this->tenants->getTenantById($tenantId);
            if (!$tenant || (int)$tenant['parent_tenant_id'] !== $agencyTenantId) {
                return new \WP_Error('not_your_tenant', __('This tenant does not belong to your agency.', 'sseo-ai-saas'));
            }
        }

        $token = wp_generate_password(32, false);
        $expiry = time() + 300;

        set_transient(
            'fyndable_autologin_' . $token,
            [
                'tenant_id' => $tenantId,
                'generated_by' => $user->ID,
            ],
            300
        );

        return add_query_arg(
            ['fyndable_autologin' => $token],
            home_url('/wp-login.php')
        );
    }

    /**
     * Handle auto-login via token.
     */
    public function handleAutoLogin(): void
    {
        if (!isset($_GET['fyndable_autologin'])) {
            return;
        }

        $token = sanitize_text_field($_GET['fyndable_autologin']);
        $data = get_transient('fyndable_autologin_' . $token);

        if (!$data || !is_array($data)) {
            wp_die(__('Invalid or expired login link.', 'sseo-ai-saas'));
        }

        delete_transient('fyndable_autologin_' . $token);

        $tenantId = (int)$data['tenant_id'];
        $tenant = $this->tenants->getTenantById($tenantId);

        if (!$tenant) {
            wp_die(__('Tenant not found.', 'sseo-ai-saas'));
        }

        $email = $tenant['email'] ?? '';
        if (empty($email)) {
            wp_die(__('Tenant has no email address for login.', 'sseo-ai-saas'));
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            $password = wp_generate_password(20, true);
            $userId = wp_create_user($email, $password, $email);
            if (is_wp_error($userId)) {
                wp_die(__('Failed to create user account.', 'sseo-ai-saas'));
            }
            $user = get_user_by('ID', $userId);
            $user->set_role('subscriber');
        }

        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);

        // Redirect based on role: customers go to /dashboard, others to the admin shell.
        if (in_array('fyndable_customer', (array)$user->roles, true)) {
            $portalPageId = (int) get_option('sseo_ai_saas_customer_portal_page', 0);
            if ($portalPageId > 0) {
                $redirect = get_permalink($portalPageId) ?: home_url('/dashboard/');
            } else {
                $redirect = home_url('/dashboard/');
            }
        } else {
            $redirect = admin_url('admin.php?page=sseo-ai-shell');
        }
        wp_redirect($redirect);
        exit;
    }

    /**
     * Shortcode: [fyndable_login redirect="..."]
     *
     * Renders a branded login form that submits to WordPress login.
     */
    public function renderLoginShortcode(array $atts = []): string
    {
        $atts = shortcode_atts([
            'redirect' => admin_url('admin.php?page=sseo-ai-shell'),
        ], $atts, 'fyndable_login');

        $redirect = esc_url_raw($atts['redirect']);

        if (is_user_logged_in()) {
            $currentUser = wp_get_current_user();
            if (in_array('fyndable_customer', (array)$currentUser->roles, true)) {
                $portalPageId = (int) get_option('sseo_ai_saas_customer_portal_page', 0);
                if ($portalPageId > 0) {
                    $dashboardUrl = get_permalink($portalPageId) ?: home_url('/dashboard/');
                } else {
                    $dashboardUrl = home_url('/dashboard/');
                }
            } else {
                $dashboardUrl = admin_url('admin.php?page=sseo-ai-shell');
            }
            return '<div class="fyndable-login-message" style="max-width:420px;margin:0 auto;padding:24px;background:#fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);text-align:center;">' .
                sprintf(
                    __('You are already logged in. <a href="%s" style="color:#379fd3;font-weight:600;">Go to dashboard</a>', 'sseo-ai-saas'),
                    esc_url($dashboardUrl)
                ) .
                '</div>';
        }

        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $brandName = $companyName ?: 'Fyndable';
        $action = esc_url(wp_login_url($redirect));

        ob_start();
        ?>
        <style>
            @font-face {
                font-family: 'Outfit';
                src: url('<?php echo SSEO_AI_SAAS_PLUGIN_URL . 'assets/fonts/outfit/Outfit-Variable.ttf'; ?>') format('truetype');
                font-weight: 100 900;
                font-style: normal;
                font-display: swap;
            }
            .fyndable-login-wrap {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                padding: 40px 20px;
                box-sizing: border-box;
                width: 100%;
                margin: 0;
                color: #fff;
                font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }
            .fyndable-login-wrap h2 {
                margin: 0 0 8px 0;
                font-size: 28px;
                font-weight: 700;
                color: #fff;
                text-align: center;
            }
            .fyndable-login-wrap h2 span {
                font-weight: 400;
                opacity: 0.85;
            }
            .fyndable-login-wrap .subtitle {
                color: rgba(255,255,255,0.85);
                margin: 0 0 30px 0;
                font-size: 14px;
                text-align: center;
            }
            .fyndable-login-wrap form {
                width: 420px;
                max-width: 90vw;
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                padding: 40px;
                box-sizing: border-box;
                margin: 0 auto;
            }
            .fyndable-login-field {
                margin-bottom: 20px;
            }
            .fyndable-login-field label {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                margin-bottom: 6px;
            }
            .fyndable-login-field input {
                width: 100%;
                padding: 12px 14px;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                font-size: 15px;
                box-sizing: border-box;
                transition: border-color 0.15s;
            }
            .fyndable-login-field input:focus {
                border-color: #379fd3;
                box-shadow: 0 0 0 3px rgba(55,159,211,0.1);
                outline: none;
            }
            .fyndable-login-remember {
                margin-bottom: 24px;
                font-size: 14px;
                color: #6b7280;
            }
            .fyndable-login-remember input {
                margin-right: 6px;
            }
            .fyndable-login-submit {
                width: 100%;
                padding: 14px;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                color: #fff;
                background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%);
                cursor: pointer;
                transition: transform 0.15s, box-shadow 0.15s;
            }
            .fyndable-login-submit:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(55,159,211,0.4);
            }
            @media screen and (max-width: 480px) {
                .fyndable-login-wrap {
                    padding: 24px 16px;
                }
                .fyndable-login-wrap form {
                    padding: 24px;
                }
                .fyndable-login-wrap h2 {
                    font-size: 20px;
                }
                .fyndable-login-field input {
                    font-size: 16px;
                }
            }
        </style>
        <div class='fyndable-login-wrap'>
            <h2><?php echo esc_html($brandName); ?> <span><?php echo esc_html(__('Smart SEO', 'sseo-ai-saas')); ?></span></h2>
            <p class='subtitle'><?php echo esc_html(sprintf(__('Welcome to %s — sign in to your dashboard', 'sseo-ai-saas'), $brandName)); ?></p>
            <form action='<?php echo $action; ?>' method='post'>
                <p class='fyndable-login-field'>
                    <label for='fyndable_log'><?php echo esc_html(__('Email or username', 'sseo-ai-saas')); ?></label>
                    <input type='text' name='log' id='fyndable_log' required>
                </p>
                <p class='fyndable-login-field'>
                    <label for='fyndable_pwd'><?php echo esc_html(__('Password', 'sseo-ai-saas')); ?></label>
                    <input type='password' name='pwd' id='fyndable_pwd' required>
                </p>
                <p class='fyndable-login-remember'>
                    <label><input type='checkbox' name='rememberme' value='forever'> <?php echo esc_html(__('Remember me', 'sseo-ai-saas')); ?></label>
                </p>
                <input type='hidden' name='redirect_to' value='<?php echo esc_url($redirect); ?>'>
                <input type='hidden' name='testcookie' value='1'>
                <p><button type='submit' class='fyndable-login-submit'><?php echo esc_html(__('Sign in', 'sseo-ai-saas')); ?></button></p>
            </form>
        </div>
        <style>
            .sseo-pwd-wrap { position: relative; }
            .sseo-pwd-toggle {
                position: absolute;
                top: 50%;
                right: 12px;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 0;
                margin: 0;
                width: 28px;
                height: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6b7280;
                line-height: 1;
            }
            .sseo-pwd-toggle:hover { color: #379fd3; }
            .sseo-pwd-toggle svg { width: 20px; height: 20px; display: block; }
            .fyndable-login-field input.sseo-has-toggle { padding-right: 44px; }
        </style>
        <script>
        (function () {
            function makeToggle(input) {
                if (!input || input.dataset.sseoToggle === '1') return;
                input.dataset.sseoToggle = '1';
                var wrap = document.createElement('div');
                wrap.className = 'sseo-pwd-wrap';
                input.parentNode.insertBefore(wrap, input);
                wrap.appendChild(input);
                input.classList.add('sseo-has-toggle');
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'sseo-pwd-toggle';
                btn.setAttribute('aria-label', 'Toon of verberg wachtwoord');
                btn.tabIndex = -1;
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
                btn.addEventListener('click', function () {
                    var isPwd = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPwd ? 'text' : 'password');
                    btn.innerHTML = isPwd
                        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
                    input.focus();
                });
                wrap.appendChild(btn);
            }
            function init() {
                var el = document.getElementById('fyndable_pwd');
                if (el) makeToggle(el);
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else { init(); }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

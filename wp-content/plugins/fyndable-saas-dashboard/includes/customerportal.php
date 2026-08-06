<?php

namespace SSEOAISaaS;

/**
 * Customer Portal
 *
 * Front-end portal for paying customers to manage their subscription,
 * view usage, download the plugin, view invoices, and manage their account.
 *
 * Exposed via shortcode [fyndable_customer_portal] and REST API endpoints.
 */
class CustomerPortal
{
    private TenantRepository $tenants;
    private PaymentProcessor $paymentProcessor;
    private CustomerRoleManager $roleManager;
    private InvoiceManager $invoiceManager;
    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(
        TenantRepository $tenants,
        PaymentProcessor $paymentProcessor,
        CustomerRoleManager $roleManager,
        InvoiceManager $invoiceManager
    ) {
        $this->tenants = $tenants;
        $this->paymentProcessor = $paymentProcessor;
        $this->roleManager = $roleManager;
        $this->invoiceManager = $invoiceManager;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_shortcode('fyndable_customer_portal', [$this, 'renderPortalShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
    }

    /**
     * Register REST API routes for the portal.
     */
    public function registerRestRoutes(): void
    {
        // Get subscription details
        register_rest_route($this->namespace, '/portal/subscription', [
            'methods' => 'GET',
            'callback' => [$this, 'getSubscription'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get usage stats
        register_rest_route($this->namespace, '/portal/usage', [
            'methods' => 'GET',
            'callback' => [$this, 'getUsage'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get invoices
        register_rest_route($this->namespace, '/portal/invoices', [
            'methods' => 'GET',
            'callback' => [$this, 'getInvoices'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Download/view a specific invoice
        register_rest_route($this->namespace, '/portal/invoice/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'viewInvoice'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Print/download a specific invoice as a standalone HTML document (auto-print).
        register_rest_route($this->namespace, '/portal/invoice/(?P<id>\d+)/print', [
            'methods' => 'GET',
            'callback' => [$this, 'printInvoice'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Cancel subscription
        register_rest_route($this->namespace, '/portal/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'cancelSubscription'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Download plugin
        register_rest_route($this->namespace, '/portal/download', [
            'methods' => 'GET',
            'callback' => [$this, 'downloadPlugin'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get license details
        register_rest_route($this->namespace, '/portal/license', [
            'methods' => 'GET',
            'callback' => [$this, 'getLicense'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Update account info
        register_rest_route($this->namespace, '/portal/account', [
            'methods' => 'POST',
            'callback' => [$this, 'updateAccount'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Set language preference
        register_rest_route($this->namespace, '/portal/language', [
            'methods' => 'POST',
            'callback' => [$this, 'setLanguage'],
            'permission_callback' => [$this, 'checkPermission'],
            'args' => [
                'lang' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
    }

    /**
     * Check if the current user has portal access.
     */
    public function checkPermission(\WP_REST_Request $request): bool
    {
        return is_user_logged_in() && $this->roleManager->isCustomerUser();
    }

    /**
     * Get subscription details for the current customer.
     */
    public function getSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $tenantKey = $tenant['tenant_key'];
        $interval = $this->tenants->getTenantSetting($tenantKey, 'subscription_interval', 'month');
        $mollieSubId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');
        $stripeSubId = $this->tenants->getTenantSetting($tenantKey, 'stripe_subscription_id', '');

        $subscriptionDetails = null;
        if (!empty($mollieSubId)) {
            $sub = $this->paymentProcessor->fetchMollieSubscription($tenantKey);
            if (!is_wp_error($sub)) {
                $subscriptionDetails = [
                    'provider' => 'mollie',
                    'status' => $sub['status'] ?? 'unknown',
                    'amount' => $sub['amount']['value'] ?? '',
                    'currency' => $sub['amount']['currency'] ?? 'EUR',
                    'interval' => $sub['interval'] ?? $interval,
                    'next_payment_date' => $sub['nextPaymentDate'] ?? null,
                    'start_date' => $sub['startDate'] ?? null,
                    'description' => $sub['description'] ?? '',
                ];
            }
        } elseif (!empty($stripeSubId)) {
            $subscriptionDetails = [
                'provider' => 'stripe',
                'status' => $tenant['payment_status'] ?? 'unknown',
                'interval' => $interval,
            ];
        }

        $pricing = $this->paymentProcessor->getTierPricing($tenant['tier'], $interval);

        return new \WP_REST_Response([
            'success' => true,
            'subscription' => [
                'tier' => $tenant['tier'],
                'status' => $tenant['status'],
                'payment_status' => $tenant['payment_status'] ?? '',
                'interval' => $interval,
                'license_key' => $tenant['license_key'],
                'domain' => $tenant['domain'],
                'expires_at' => $tenant['expires_at'],
                'created_at' => $tenant['created_at'],
                'max_sites' => $tenant['max_sites'],
                'rate_limit' => $tenant['rate_limit'],
                'api_calls_limit' => $tenant['api_calls_limit'],
                'monthly_amount' => is_wp_error($pricing) ? 0 : $pricing['amount'],
                'currency' => get_option('sseo_ai_saas_currency', 'EUR'),
            ],
            'provider_details' => $subscriptionDetails,
        ], 200);
    }

    /**
     * Get usage stats for the current customer.
     */
    public function getUsage(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $currentPeriod = date('Y-m');
        $usage = $this->tenants->getTenantUsage($tenant['tenant_key'], $currentPeriod);

        $apiCalls = (int)($usage['api_calls'] ?? 0);
        $apiLimit = (int)$tenant['api_calls_limit'];
        $usagePct = $apiLimit > 0 ? round(($apiCalls / $apiLimit) * 100) : 0;

        return new \WP_REST_Response([
            'success' => true,
            'usage' => [
                'period' => $currentPeriod,
                'api_calls' => $apiCalls,
                'api_calls_limit' => $apiLimit,
                'api_calls_pct' => $usagePct,
                'api_cost' => (float)($usage['api_cost'] ?? 0),
                'serp_requests' => (int)($usage['serp_requests'] ?? 0),
                'content_generated' => (int)($usage['content_generated'] ?? 0),
                'keywords_tracked' => (int)($usage['keywords_tracked'] ?? 0),
            ],
        ], 200);
    }

    /**
     * Get invoices for the current customer.
     */
    public function getInvoices(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $invoices = $this->invoiceManager->getInvoices($tenant['tenant_key']);

        $formatted = array_map(function ($inv) {
            return [
                'id' => (int)$inv['id'],
                'invoice_number' => $inv['invoice_number'],
                'amount' => (float)$inv['amount'],
                'currency' => $inv['currency'],
                'status' => $inv['status'],
                'description' => $inv['description'],
                'billing_interval' => $inv['billing_interval'],
                'created_at' => $inv['created_at'],
                'paid_at' => $inv['paid_at'],
            ];
        }, $invoices);

        return new \WP_REST_Response([
            'success' => true,
            'invoices' => $formatted,
        ], 200);
    }

    /**
     * View/download a specific invoice as HTML.
     */
    public function viewInvoice(\WP_REST_Request $request): \WP_REST_Response
    {
        $invoiceId = (int) $request->get_param('id');
        $tenant = $this->roleManager->getCustomerTenant();

        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $invoice = $this->invoiceManager->getInvoice($invoiceId, $tenant['tenant_key']);
        if (!$invoice) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'invoice' => $invoice,
            'html' => $this->invoiceManager->renderInvoiceHtml($invoice),
        ], 200);
    }

    /**
     * Print/download a specific invoice as a standalone HTML document.
     * Opens with auto-print so the browser's "Save as PDF" can be used.
     */
    public function printInvoice(\WP_REST_Request $request): \WP_REST_Response
    {
        $invoiceId = (int) $request->get_param('id');
        $tenant = $this->roleManager->getCustomerTenant();

        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $invoice = $this->invoiceManager->getInvoice($invoiceId, $tenant['tenant_key']);
        if (!$invoice) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $html = $this->invoiceManager->renderInvoicePrintHtml($invoice);

        return new \WP_REST_Response([
            'success' => true,
            'html' => $html,
        ], 200);
    }

    /**
     * Cancel the customer's subscription.
     */
    public function cancelSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $result = $this->paymentProcessor->cancelSubscription($tenant['tenant_key']);
        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 500);
        }

        do_action('sseo_ai_subscription_cancelled', $tenant['tenant_key'], $tenant);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Your subscription has been cancelled. You will retain access until the end of your current billing period.', 'sseo-ai-saas'),
        ], 200);
    }

    /**
     * Download the latest client plugin version.
     */
    public function downloadPlugin(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Check if tenant is active
        if ($tenant['status'] !== 'active') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Your subscription is not active. Please renew to download the plugin.',
            ], 403);
        }

        $downloadUrl = get_option('sseo_ai_saas_download_url', '');
        $latestVersion = get_option('sseo_ai_saas_latest_version', '');

        if (empty($downloadUrl)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Plugin download is not available yet. Please contact support.',
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'download_url' => $downloadUrl,
            'version' => $latestVersion,
            'license_key' => $tenant['license_key'],
            'dashboard_url' => home_url(),
            'instructions' => '1. Download the zip file. 2. In your WordPress admin, go to Plugins > Add New > Upload Plugin. 3. Upload the zip and activate. 4. Enter your license key in the plugin settings.',
        ], 200);
    }

    /**
     * Get license details for the current customer.
     */
    public function getLicense(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        // Don't show license tab for agency tier
        if (($tenant['tier'] ?? '') === 'agency') {
            return new \WP_REST_Response(['success' => false, 'message' => 'License tab not available for agency accounts'], 403);
        }

        return new \WP_REST_Response([
            'success' => true,
            'license_key' => $tenant['license_key'] ?? '',
            'tier' => $tenant['tier'] ?? 'starter',
            'status' => $tenant['status'] ?? 'active',
            'expires_at' => $tenant['expires_at'] ?? null,
            'max_sites' => (int)($tenant['max_sites'] ?? 1),
            'rate_limit' => (int)($tenant['rate_limit'] ?? 60),
            'api_calls_limit' => (int)($tenant['api_calls_limit'] ?? 1000),
        ], 200);
    }

    /**
     * Update customer account info (name, domain).
     */
    public function updateAccount(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $name = sanitize_text_field($request->get_param('name') ?? '');
        $domain = esc_url_raw($request->get_param('domain') ?? '');

        $update = [];
        if (!empty($name)) {
            $update['name'] = $name;
        }
        if (!empty($domain)) {
            $update['domain'] = $domain;
        }

        if (empty($update)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'No fields to update'], 400);
        }

        $result = $this->tenants->updateTenant($tenant['tenant_key'], $update);
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Account updated successfully.',
        ], 200);
    }

    /**
     * Set the customer's language preference (EN/NL).
     */
    public function setLanguage(\WP_REST_Request $request): \WP_REST_Response
    {
        $lang = sanitize_key($request->get_param('lang') ?? '');
        if (!in_array($lang, ['en', 'nl'], true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid language.'], 400);
        }

        $userId = get_current_user_id();
        update_user_meta($userId, 'fyndable_lang', $lang);

        return new \WP_REST_Response(['success' => true, 'lang' => $lang], 200);
    }

    /**
     * Maybe enqueue portal assets.
     */
    public function maybeEnqueueAssets(): void
    {
        // Assets are enqueued when shortcode is rendered
    }

    /**
     * Render the customer portal shortcode.
     */
    public function renderPortalShortcode(): string
    {
        $jsVersion = filemtime(SSEO_AI_SAAS_PLUGIN_DIR . 'assets/customerportal.js') ?: SSEO_AI_SAAS_VERSION;
        $i18nVersion = filemtime(SSEO_AI_SAAS_PLUGIN_DIR . 'assets/i18n.js') ?: SSEO_AI_SAAS_VERSION;
        wp_enqueue_style('fyndable-customer-portal', SSEO_AI_SAAS_PLUGIN_URL . 'assets/customerportal.css', [], $jsVersion);
        wp_enqueue_script('fyndable-i18n', SSEO_AI_SAAS_PLUGIN_URL . 'assets/i18n.js', [], $i18nVersion, true);
        wp_enqueue_script('fyndable-customer-portal', SSEO_AI_SAAS_PLUGIN_URL . 'assets/customerportal.js', ['jquery', 'fyndable-i18n'], $jsVersion, true);

        // Determine user language preference.
        $userLang = '';
        if (is_user_logged_in()) {
            $userLang = get_user_meta(get_current_user_id(), 'fyndable_lang', true);
            if ($userLang !== 'nl' && $userLang !== 'en') {
                $userLang = '';
            }
        }

        wp_localize_script('fyndable-i18n', 'FyndableI18nConfig', [
            'userLang' => $userLang,
        ]);

        wp_localize_script('fyndable-customer-portal', 'FyndablePortal', [
            'restUrl' => esc_url_raw(rest_url('ai-seo-saas/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'loginUrl' => esc_url(wp_login_url($this->roleManager->getPortalUrl())),
            'lang' => $userLang,
            // i18n kept for backward-compat — JS now uses FyndableI18n.t() instead.
            'i18n' => [
                'loading' => __('Loading...', 'sseo-ai-saas'),
                'error' => __('Something went wrong. Please try again.', 'sseo-ai-saas'),
                'confirmCancel' => __('Are you sure you want to cancel your subscription? You will retain access until the end of your current billing period.', 'sseo-ai-saas'),
                'cancelled' => __('Subscription cancelled successfully.', 'sseo-ai-saas'),
                'copied' => __('Copied to clipboard!', 'sseo-ai-saas'),
            ],
        ]);

        if (!is_user_logged_in()) {
            return $this->renderLoginPrompt();
        }

        if (!$this->roleManager->isCustomerUser()) {
            $user = wp_get_current_user();
            if (in_array('administrator', (array)$user->roles, true)) {
                return '<div class="fyndable-portal-notice" data-i18n="admin_notice">You are logged in as an administrator. <a href="' . esc_url(admin_url('admin.php?page=sseo-ai-shell')) . '" data-i18n-link="go_to_dashboard">Go to SaaS Dashboard</a></div>';
            }
            if (in_array('agency_partner', (array)$user->roles, true)) {
                return '<div class="fyndable-portal-notice" data-i18n="agency_notice">You are logged in as an agency partner. <a href="' . esc_url(admin_url('admin.php?page=sseo-ai-shell')) . '" data-i18n-link="go_to_agency">Go to Agency Portal</a></div>';
            }
            return '<div class="fyndable-portal-notice" data-i18n="no_access">You do not have access to the customer portal.</div>';
        }

        $tenant = $this->roleManager->getCustomerTenant();
        if (!$tenant) {
            return '<div class="fyndable-portal-notice" data-i18n="no_subscription">No subscription found for your account. Please contact support.</div>';
        }

        ob_start();
        ?>
        <div id="fyndable-customer-portal" class="fyndable-portal">
            <!-- Header -->
            <div class="fyndable-portal-header">
                <div class="fyndable-portal-brand">
                    <span class="fyndable-portal-logo">Fyndable</span>
                    <span class="fyndable-portal-tagline">Smart SEO</span>
                </div>
                <div class="fyndable-portal-user">
                    <div class="fyndable-portal-lang-toggle">
                        <button type="button" data-lang="en">EN</button>
                        <span class="fyndable-portal-lang-sep">|</span>
                        <button type="button" data-lang="nl">NL</button>
                    </div>
                    <span class="fyndable-portal-user-name"><?php echo esc_html($tenant['name']); ?></span>
                    <a href="<?php echo esc_url(wp_logout_url($this->roleManager->getPortalUrl())); ?>" class="fyndable-portal-logout" data-i18n="sign_out">Sign out</a>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="fyndable-portal-tabs">
                <button class="fyndable-portal-tab active" data-tab="subscription" data-i18n="tab_subscription">Subscription</button>
                <button class="fyndable-portal-tab" data-tab="license" data-i18n="tab_license">License</button>
                <button class="fyndable-portal-tab" data-tab="usage" data-i18n="tab_usage">Usage</button>
                <button class="fyndable-portal-tab" data-tab="download" data-i18n="tab_download">Plugin</button>
                <button class="fyndable-portal-tab" data-tab="invoices" data-i18n="tab_invoices">Invoices</button>
                <button class="fyndable-portal-tab" data-tab="account" data-i18n="tab_account">Account</button>
            </div>

            <!-- Tab: Subscription -->
            <div class="fyndable-portal-panel active" id="panel-subscription">
                <div class="fyndable-portal-loading" data-i18n="loading_subscription">Loading subscription...</div>
            </div>

            <!-- Tab: License -->
            <div class="fyndable-portal-panel" id="panel-license">
                <div class="fyndable-portal-loading" data-i18n="loading_license">Loading license...</div>
            </div>

            <!-- Tab: Usage -->
            <div class="fyndable-portal-panel" id="panel-usage">
                <div class="fyndable-portal-loading" data-i18n="loading_usage">Loading usage...</div>
            </div>

            <!-- Tab: Download -->
            <div class="fyndable-portal-panel" id="panel-download">
                <div class="fyndable-portal-loading" data-i18n="loading">Loading...</div>
            </div>

            <!-- Tab: Invoices -->
            <div class="fyndable-portal-panel" id="panel-invoices">
                <div class="fyndable-portal-loading" data-i18n="loading_invoices">Loading invoices...</div>
            </div>

            <!-- Tab: Account -->
            <div class="fyndable-portal-panel" id="panel-account">
                <div class="fyndable-portal-loading" data-i18n="loading_account">Loading account...</div>
            </div>

            <!-- Invoice Modal -->
            <div class="fyndable-portal-modal" id="invoice-modal" style="display:none;">
                <div class="fyndable-portal-modal-overlay"></div>
                <div class="fyndable-portal-modal-content">
                    <button class="fyndable-portal-modal-close" onclick="document.getElementById('invoice-modal').style.display='none';">&times;</button>
                    <div id="invoice-modal-body"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a login prompt for non-logged-in users.
     */
    private function renderLoginPrompt(): string
    {
        $loginUrl = esc_url(wp_login_url($this->roleManager->getPortalUrl()));
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = $enabled ? get_option('sseo_ai_saas_wl_company_name', '') : '';
        $brandName = $companyName ?: 'Fyndable';

        ob_start();
        ?>
        <div class="fyndable-portal-login-prompt">
            <div class="fyndable-portal-lang-toggle" style="position:absolute;top:16px;right:16px;">
                <button type="button" data-lang="en">EN</button>
                <span class="fyndable-portal-lang-sep">|</span>
                <button type="button" data-lang="nl">NL</button>
            </div>
            <div class="fyndable-portal-login-card">
                <h2 data-i18n="welcome_to" data-i18n-arg="<?php echo esc_attr($brandName); ?>">Welcome to <?php echo esc_html($brandName); ?></h2>
                <p data-i18n="sign_in_subtitle">Sign in to manage your subscription, view invoices, and download the plugin.</p>
                <a href="<?php echo $loginUrl; ?>" class="fyndable-portal-login-btn" data-i18n="sign_in">
                    Sign in
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

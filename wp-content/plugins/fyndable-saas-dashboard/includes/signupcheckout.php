<?php

namespace SSEOAISaaS;

/**
 * Self-Serve Signup & Checkout
 *
 * Public-facing signup flow that allows new customers to:
 * 1. Choose a plan (free, starter, professional, business, agency)
 * 2. Enter their details (name, email, site URL)
 * 3. Complete payment (via Stripe/Mollie) for paid plans
 * 4. Receive their license key automatically
 *
 * Exposed via REST API and a shortcode [fyndable_signup] for embedding.
 */
class SignupCheckout
{
    private TenantRepository $tenants;
    private LicenseKeyGenerator $licenseGenerator;
    private PaymentProcessor $paymentProcessor;
    private EmailAutomation $emailAutomation;
    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(
        TenantRepository $tenants,
        LicenseKeyGenerator $licenseGenerator,
        PaymentProcessor $paymentProcessor,
        EmailAutomation $emailAutomation
    ) {
        $this->tenants = $tenants;
        $this->licenseGenerator = $licenseGenerator;
        $this->paymentProcessor = $paymentProcessor;
        $this->emailAutomation = $emailAutomation;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_shortcode('fyndable_signup', [$this, 'renderSignupShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueueAssets']);
        add_action('template_redirect', [$this, 'handlePaymentReturn']);
    }

    /**
     * Register REST routes.
     */
    public function registerRestRoutes(): void
    {
        // Get available plans
        register_rest_route($this->namespace, '/signup/plans', [
            'methods'  => 'GET',
            'callback' => [$this, 'restGetPlans'],
            'permission_callback' => '__return_true',
        ]);

        // Create account + start checkout
        register_rest_route($this->namespace, '/signup/register', [
            'methods'  => 'POST',
            'callback' => [$this, 'restRegister'],
            'permission_callback' => '__return_true',
            'args' => [
                'name' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'email' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'street' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'postal_code' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'city' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'country' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'tier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'interval' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'payment_method' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        // Verify payment and activate license
        register_rest_route($this->namespace, '/signup/activate', [
            'methods'  => 'POST',
            'callback' => [$this, 'restActivate'],
            'permission_callback' => '__return_true',
            'args' => [
                'tenant_key' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'payment_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    /**
     * Get available plans with pricing.
     */
    public function restGetPlans(): \WP_REST_Response
    {
        nocache_headers();
        $provider = get_option('sseo_ai_saas_payment_provider', 'stripe');
        $paymentMethods = [];
        if ($provider === 'mollie') {
            $paymentMethods = [
                'ideal' => 'iDEAL',
                'creditcard' => 'Credit Card',
            ];
        }
        return new \WP_REST_Response([
            'success' => true,
            'plans' => $this->getPlans(),
            'currency' => get_option('sseo_ai_saas_currency', 'EUR'),
            'provider' => $provider,
            'payment_methods' => $paymentMethods,
            'trial_enabled' => !empty(get_option('sseo_ai_saas_trial_enabled', '1')),
        ], 200);
    }

    /**
     * Get plan definitions.
     */
    public function getPlans(): array
    {
        $currency = get_option('sseo_ai_saas_currency', 'EUR');
        $symbol = $this->getCurrencySymbol($currency);

        $tiers = [
            'starter' => [
                'name' => 'Starter',
                'features' => [
                    '1 site',
                    '15 auto-posts/month',
                    '5 GEO audits/month',
                    '500 AI calls/month',
                    'All SEO features',
                    'Schema markup',
                    'Social previews',
                    'Email support',
                ],
                'popular' => false,
                'cta' => 'Choose Starter',
            ],
            'professional' => [
                'name' => 'Professional',
                'features' => [
                    '1 site',
                    '35 auto-posts/month',
                    '35 GEO audits/month',
                    '2,000 AI calls/month',
                    'Everything in Starter',
                    'Rank tracking',
                    'Content briefs',
                    'Competitor analysis',
                    'Priority support',
                ],
                'popular' => true,
                'cta' => 'Choose Professional',
            ],
            'business' => [
                'name' => 'Business',
                'features' => [
                    '3 sites',
                    '150 auto-posts/month',
                    '90 GEO audits/month',
                    '5,000 AI calls/month',
                    'Everything in Professional',
                    'White-label reports',
                    'API access',
                    'Dedicated support',
                ],
                'popular' => false,
                'cta' => 'Choose Business',
            ],
            'agency' => [
                'name' => 'Agency',
                'features' => [
                    '5+ sites',
                    'Unlimited auto-posts',
                    'Unlimited GEO audits',
                    '20,000 AI calls/month',
                    'Everything in Business',
                    'Full white-label',
                    'Multi-tenant management',
                    'Custom integrations',
                    'SLA support',
                ],
                'popular' => false,
                'cta' => 'Contact Us',
                'self_serve' => false,
            ],
        ];

        if (get_option('sseo_ai_saas_early_adopters_enabled', false)) {
            $tiers['early_adopters'] = [
                'name' => 'Early Adopters',
                'features' => [
                    '1 site',
                    '15 auto-posts/month',
                    '5 GEO audits/month',
                    '500 AI calls/month',
                    'All SEO features',
                    'Schema markup',
                    'Social previews',
                    'Email support',
                ],
                'popular' => false,
                'cta' => 'Choose Early Adopters',
            ];
        }

        $plans = [];
        foreach ($tiers as $key => $tier) {
            $monthly = $this->paymentProcessor->getTierPricing($key, 'month');
            $yearly = $this->paymentProcessor->getTierPricing($key, 'year');
            $monthlyAmount = is_wp_error($monthly) ? 0.0 : $monthly['amount'];
            $yearlyAmount = is_wp_error($yearly) ? 0.0 : $yearly['amount'];

            $savingsLabel = '';
            if ($monthlyAmount > 0 && $yearlyAmount > 0 && $yearlyAmount < $monthlyAmount * 12) {
                $savingsLabel = __('2 maanden gratis', 'sseo-ai-saas');
            }

            $plans[$key] = [
                'name' => $tier['name'],
                'popular' => $tier['popular'],
                'cta' => $tier['cta'],
                'features' => $tier['features'],
                'intervals' => [
                    'month' => [
                        'price' => $monthlyAmount,
                        'price_display' => $symbol . number_format($monthlyAmount, 0),
                        'period' => '/month',
                    ],
                    'year' => [
                        'price' => $yearlyAmount,
                        'price_display' => $symbol . number_format($yearlyAmount, 0),
                        'period' => '/year',
                        'savings_label' => $savingsLabel,
                    ],
                ],
            ];
        }

        return $plans;
    }

    /**
     * Register a new tenant and start checkout.
     */
    public function restRegister(\WP_REST_Request $request): \WP_REST_Response
    {
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $street = $request->get_param('street');
        $postalCode = $request->get_param('postal_code');
        $city = $request->get_param('city');
        $country = $request->get_param('country');
        $tier = $request->get_param('tier');
        $interval = $request->get_param('interval') ?: 'month';
        $interval = in_array($interval, ['month', 'year'], true) ? $interval : 'month';
        $paymentMethod = $request->get_param('payment_method') ?: null;

        $plans = $this->getPlans();
        if (!isset($plans[$tier])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid plan selected.',
            ], 400);
        }

        // Agency tier is not available via self-serve checkout — contact us manually
        if ($tier === 'agency') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'The Agency plan is not available via self-serve checkout. Please contact us at ' . get_option('admin_email') . ' to set up an agency account.',
            ], 403);
        }

        // Self-serve checkout is disabled by default for the beta
        if (!get_option('sseo_ai_saas_self_serve_enabled', false)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Self-serve checkout is currently disabled. Please contact us for a license key.',
            ], 400);
        }

        // Check if email already exists; allow retrying a failed/cancelled pending payment
        $existingTenants = $this->tenants->getAllTenants(500);
        foreach ($existingTenants as $existing) {
            if (($existing['email'] ?? '') === $email) {
                if (($existing['status'] ?? '') === 'pending_payment') {
                    $this->cleanupPendingSignup($existing);
                } else {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'An account with this email already exists. Please log in or use a different email.',
                    ], 409);
                }
            }
        }

        // Generate license key
        $licenseResult = $this->licenseGenerator->generateLicense([
            'tier' => $tier,
            'name' => $name,
        ]);

        if (is_wp_error($licenseResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to generate license key.',
            ], 500);
        }

        $licenseKey = $licenseResult['license_key'] ?? '';

        // Set tier limits
        $limits = $this->getTierLimits($tier);

        // Create tenant
        $tenantData = [
            'name' => $name,
            'email' => $email,
            'tier' => $tier,
            'license_key' => $licenseKey,
            'status' => 'pending_payment',
            'max_sites' => $limits['max_sites'],
            'rate_limit' => $limits['rate_limit'],
            'api_calls_limit' => $limits['api_calls_limit'],
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS),
        ];

        $tenantResult = $this->tenants->createTenant($tenantData);

        if (is_wp_error($tenantResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $tenantResult->get_error_message(),
            ], 500);
        }

        $tenantKey = $tenantResult['tenant_key'];
        $this->tenants->setTenantSetting($tenantKey, 'subscription_interval', $interval);

        // Store address in tenant settings
        if (!empty($street)) {
            $this->tenants->setTenantSetting($tenantKey, 'address_street', $street);
        }
        if (!empty($postalCode)) {
            $this->tenants->setTenantSetting($tenantKey, 'address_postal_code', $postalCode);
        }
        if (!empty($city)) {
            $this->tenants->setTenantSetting($tenantKey, 'address_city', $city);
        }
        if (!empty($country)) {
            $this->tenants->setTenantSetting($tenantKey, 'address_country', $country);
        }

        // For paid tiers, create checkout session
        $checkoutResult = $this->paymentProcessor->createSubscription($tenantKey, $tier, $interval, $paymentMethod);

        if (is_wp_error($checkoutResult)) {
            $this->cleanupPendingSignup([
                'tenant_key' => $tenantKey,
                'id' => $tenantResult['id'] ?? 0,
                'license_key' => $licenseKey,
                'status' => 'pending_payment',
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'message' => $checkoutResult->get_error_message(),
            ], 500);
        }

        return new \WP_REST_Response([
            'success' => true,
            'tenant_key' => $tenantKey,
            'license_key' => $licenseKey,
            'requires_payment' => true,
            'checkout_url' => $checkoutResult['checkout_url'] ?? '',
            'payment_data' => $checkoutResult,
        ], 200);
    }

    /**
     * Activate license after payment completion.
     */
    public function restActivate(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantKey = $request->get_param('tenant_key');
        $paymentId = $request->get_param('payment_id');

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Tenant not found.',
            ], 404);
        }

        // SECURITY: This is a public endpoint. It must NEVER activate a paid plan
        // without a verified payment. Activation is driven by the signature-verified
        // payment webhooks. Here we only (a) return the license if the webhook has
        // already activated the tenant, or (b) re-verify the payment inline as a
        // fallback for race conditions before activating.
        $isActive = ($tenant['status'] ?? '') === 'active'
            && ($tenant['payment_status'] ?? '') === 'active';

        // Free tier is always active without payment.
        if (!$isActive) {
            $verified = $this->verifyPaymentInline($tenant, $paymentId);
            if (!$verified) {
                // Do not activate — payment not confirmed yet.
                return new \WP_REST_Response([
                    'success' => false,
                    'pending' => true,
                    'message' => 'Payment not yet confirmed. This can take a few moments; please retry shortly.',
                ], 202);
            }

            // Payment verified inline — safe to activate.
            $interval = $this->tenants->getTenantSetting($tenantKey, 'subscription_interval', 'month');
            $period = $interval === 'year' ? '+1 year' : '+1 month';
            $this->tenants->updateTenant($tenantKey, [
                'status' => 'active',
                'payment_status' => 'active',
                'last_payment_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime($period)),
            ]);

            $this->emailAutomation->sendWelcomeEmail($tenantKey, [
                'email' => $tenant['email'],
                'tier' => $tenant['tier'],
                'license_key' => $tenant['license_key'],
            ]);

            $plans = $this->getPlans();
            $plan = $plans[$tenant['tier']] ?? null;
            $currency = get_option('sseo_ai_saas_currency', 'EUR');
            $symbol = $this->getCurrencySymbol($currency);
            $amount = $plan ? ($symbol . $plan['price']) : '';

            do_action('sseo_ai_payment_success', $tenantKey, $amount, [
                'tier' => $tenant['tier'],
                'date' => current_time('mysql'),
                'payment_id' => $paymentId,
            ]);
        }

        return new \WP_REST_Response([
            'success' => true,
            'license_key' => $tenant['license_key'],
            'redirect_url' => $this->getSuccessUrl($tenant['license_key']),
        ], 200);
    }

    /**
     * Re-verify a payment directly with the provider before activating.
     * Currently supports Mollie (its API is safe to query by payment id).
     * Stripe activation is handled exclusively by the signature-verified webhook.
     */
    private function verifyPaymentInline(array $tenant, ?string $paymentId): bool
    {
        if (empty($paymentId)) {
            return false;
        }

        // Mollie payment ids are prefixed with "tr_".
        if (strpos($paymentId, 'tr_') === 0) {
            $payment = $this->paymentProcessor->fetchMolliePayment($paymentId);
            if (is_wp_error($payment) || !is_array($payment)) {
                return false;
            }

            $status = $payment['status'] ?? '';
            $metaTenant = $payment['metadata']['tenant_key'] ?? '';

            return $status === 'paid' && $metaTenant === ($tenant['tenant_key'] ?? '');
        }

        // Unknown/unsupported payment reference — refuse to activate.
        return false;
    }

    /**
     * Handle Mollie/Stripe return URLs after a payment attempt.
     * Cleans up pending signups on failed/cancelled payments and,
     * on successful Mollie payments, creates the customer account
     * and redirects them to set their password.
     */
    public function handlePaymentReturn(): void
    {
        if (is_admin() || wp_doing_ajax() || empty($_GET['fyndable_payment']) || empty($_GET['tenant_key'])) {
            return;
        }

        $state = sanitize_text_field($_GET['fyndable_payment']);
        $tenantKey = sanitize_text_field($_GET['tenant_key']);
        $provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : '';
        $paymentId = isset($_GET['paymentId'])
            ? sanitize_text_field($_GET['paymentId'])
            : (isset($_GET['payment_id'])
                ? sanitize_text_field($_GET['payment_id'])
                : (isset($_GET['id']) ? sanitize_text_field($_GET['id']) : ''));

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            wp_redirect(home_url('/pricing/'));
            exit;
        }

        // Fallback: als Mollie het payment ID niet in de redirect URL heeft meegegeven,
        // haal het op uit de tenant settings (opgeslagen tijdens checkout creatie).
        if (empty($paymentId) && $provider === 'mollie' && $state === 'success') {
            $paymentId = $this->tenants->getTenantSetting($tenantKey, 'mollie_payment_id', '');
        }

        error_log(sprintf(
            'SSEO AI SaaS: handlePaymentReturn — state=%s provider=%s tenant=%s paymentId=%s',
            $state, $provider, $tenantKey, $paymentId ?: '(empty)'
        ));

        $pricingUrl = home_url('/pricing/');

        if (in_array($state, ['cancel', 'failed', 'expired'], true)) {
            $this->cleanupPendingSignup($tenant);
            wp_redirect(add_query_arg('fyndable_checkout', 'cancelled', $pricingUrl));
            exit;
        }

        if ($state !== 'success' || $provider !== 'mollie' || empty($paymentId)) {
            return;
        }

        $payment = $this->paymentProcessor->fetchMolliePayment($paymentId);
        if (is_wp_error($payment) || !is_array($payment) || ($payment['status'] ?? '') !== 'paid') {
            $this->cleanupPendingSignup($tenant);
            wp_redirect(add_query_arg('fyndable_checkout', 'failed', $pricingUrl));
            exit;
        }

        $metaTenant = $payment['metadata']['tenant_key'] ?? '';
        if ($metaTenant !== $tenantKey) {
            $this->cleanupPendingSignup($tenant);
            wp_redirect(add_query_arg('fyndable_checkout', 'failed', $pricingUrl));
            exit;
        }

        // Activate the tenant if the webhook has not already done so.
        if (($tenant['status'] ?? '') !== 'active' || ($tenant['payment_status'] ?? '') !== 'active') {
            $interval = $this->tenants->getTenantSetting($tenantKey, 'subscription_interval', 'month');
            $period = $interval === 'year' ? '+1 year' : '+1 month';
            $this->tenants->updateTenant($tenantKey, [
                'status' => 'active',
                'payment_status' => 'active',
                'last_payment_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime($period)),
            ]);

            do_action('sseo_ai_payment_success', $tenantKey, $this->getCurrencySymbol(strtoupper($payment['amount']['currency'] ?? 'EUR')) . ($payment['amount']['value'] ?? '0'), [
                'tier' => $tenant['tier'],
                'date' => current_time('mysql'),
                'payment_id' => $paymentId,
            ]);
        }

        // Fallback: ensure a Mollie subscription exists for recurring payments.
        // The webhook normally creates this, but if the webhook was delayed or
        // failed, we create it here from the first payment so recurring SEPA
        // incasso charges will still be scheduled.
        $existingSubId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');
        if (empty($existingSubId) && ($payment['sequenceType'] ?? '') === 'first') {
            $subscription = $this->paymentProcessor->createMollieSubscription($tenantKey, $payment);
            if (is_wp_error($subscription)) {
                error_log('SSEO AI SaaS: Fallback Mollie subscription creation failed for tenant ' . $tenantKey . ': ' . $subscription->get_error_message());
            } else {
                error_log('SSEO AI SaaS: Fallback Mollie subscription created for tenant ' . $tenantKey . ': ' . ($subscription['id'] ?? ''));
            }
        }

        $roleManager = new CustomerRoleManager($this->tenants);
        $user = get_user_by('email', $tenant['email']);
        if (!$user || !$roleManager->isCustomerUser($user)) {
            $userId = $roleManager->createCustomerUser(
                $tenant['email'],
                $tenant['name'] ?? '',
                (int) $tenant['id'],
                $tenant['tier'] ?? 'starter'
            );
            if (is_wp_error($userId)) {
                wp_redirect(add_query_arg('fyndable_checkout', 'failed', $pricingUrl));
                exit;
            }
            $user = get_user_by('ID', $userId);
        }

        if (!$user) {
            wp_redirect(add_query_arg('fyndable_checkout', 'failed', $pricingUrl));
            exit;
        }

        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            wp_redirect(wp_login_url($roleManager->getPortalUrl()));
            exit;
        }

        $resetUrl = network_site_url(
            "wp-login.php?action=rp&key=" . rawurlencode($key)
            . "&login=" . rawurlencode($user->user_login)
            . "&redirect_to=" . rawurlencode($roleManager->getPortalUrl())
        );

        wp_redirect($resetUrl);
        exit;
    }

    /**
     * Remove a pending tenant/license that was created for a failed/cancelled payment.
     */
    private function cleanupPendingSignup(array $tenant): void
    {
        global $wpdb;

        $tenantKey = $tenant['tenant_key'] ?? '';
        $licenseKey = $tenant['license_key'] ?? '';
        $tenantId = (int) ($tenant['id'] ?? 0);

        if (empty($tenantKey)) {
            return;
        }

        // Never clean up an already active paid customer
        if (($tenant['status'] ?? '') === 'active' && ($tenant['payment_status'] ?? '') === 'active') {
            return;
        }

        if ($licenseKey) {
            $this->licenseGenerator->revokeLicense($licenseKey, 'Payment failed or cancelled during checkout');
            $wpdb->delete($wpdb->prefix . 'sseo_ai_license_keys', ['license_key' => $licenseKey]);
        }

        if ($tenantId) {
            $wpdb->delete($wpdb->prefix . 'sseo_ai_tenant_settings', ['tenant_id' => $tenantId]);
        }

        $wpdb->delete($wpdb->prefix . 'sseo_ai_tenants', ['tenant_key' => $tenantKey]);
    }

    /**
     * Get tier limits.
     */
    private function getTierLimits(string $tier): array
    {
        $limits = [
            'starter' => ['max_sites' => 1, 'rate_limit' => 60, 'api_calls_limit' => 500, 'geo_scan_limit' => 5],
            'professional' => ['max_sites' => 1, 'rate_limit' => 120, 'api_calls_limit' => 2000, 'geo_scan_limit' => 35],
            'business' => ['max_sites' => 3, 'rate_limit' => 300, 'api_calls_limit' => 5000, 'geo_scan_limit' => 90],
            'agency' => ['max_sites' => 5, 'rate_limit' => 600, 'api_calls_limit' => 20000, 'geo_scan_limit' => 999999],
        ];

        $limits['early_adopters'] = $limits['starter'];

        return $limits[$tier] ?? $limits['starter'];
    }

    /**
     * Get success URL with license key.
     */
    private function getSuccessUrl(string $licenseKey): string
    {
        return add_query_arg([
            'license_key' => $licenseKey,
            'signup' => 'complete',
        ], home_url('/thank-you/'));
    }

    /**
     * Get currency symbol.
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
        ];
        return $symbols[$currency] ?? '€';
    }

    /**
     * Maybe enqueue signup assets on pages with the shortcode.
     */
    public function maybeEnqueueAssets(): void
    {
        // Assets are enqueued when shortcode is rendered
    }

    /**
     * Render the signup shortcode.
     */
    public function renderSignupShortcode(): string
    {
        $jsVersion = filemtime(SSEO_AI_SAAS_PLUGIN_DIR . 'assets/signup.js') ?: SSEO_AI_SAAS_VERSION;
        $i18nVersion = filemtime(SSEO_AI_SAAS_PLUGIN_DIR . 'assets/i18n.js') ?: SSEO_AI_SAAS_VERSION;
        wp_enqueue_style('fyndable-signup', SSEO_AI_SAAS_PLUGIN_URL . 'assets/signup.css', [], $jsVersion);
        wp_enqueue_script('fyndable-i18n', SSEO_AI_SAAS_PLUGIN_URL . 'assets/i18n.js', [], $i18nVersion, true);
        wp_enqueue_script('fyndable-signup', SSEO_AI_SAAS_PLUGIN_URL . 'assets/signup.js', ['wp-api-fetch', 'fyndable-i18n'], $jsVersion, true);

        // Determine user language for logged-in users
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

        wp_localize_script('fyndable-signup', 'FyndableSignup', [
            'restUrl' => esc_url_raw(rest_url('ai-seo-saas/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        ob_start();
        ?>
        <div id="fyndable-signup-app">
            <div class="fyndable-signup-loading">Loading plans...</div>
        </div>
        <?php
        return ob_get_clean();
    }
}

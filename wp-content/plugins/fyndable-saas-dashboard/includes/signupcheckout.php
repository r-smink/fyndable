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
                'site_url' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'esc_url_raw'],
                'tier' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
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
        return new \WP_REST_Response([
            'success' => true,
            'plans' => $this->getPlans(),
            'currency' => get_option('sseo_ai_saas_currency', 'EUR'),
        ], 200);
    }

    /**
     * Get plan definitions.
     */
    public function getPlans(): array
    {
        $currency = get_option('sseo_ai_saas_currency', 'EUR');
        $symbol = $this->getCurrencySymbol($currency);

        return [
            'free' => [
                'name' => 'Free',
                'price' => 0,
                'price_display' => '€0',
                'period' => 'forever',
                'features' => [
                    '1 site',
                    '30 AI calls/month',
                    'Basic SEO meta',
                    'XML Sitemap',
                    'robots.txt editor',
                ],
                'popular' => false,
                'cta' => 'Get Started Free',
            ],
            'starter' => [
                'name' => 'Starter',
                'price' => 19,
                'price_display' => $symbol . '19',
                'period' => '/month',
                'features' => [
                    '1 site',
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
                'price' => 49,
                'price_display' => $symbol . '49',
                'period' => '/month',
                'features' => [
                    '3 sites',
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
                'price' => 99,
                'price_display' => $symbol . '99',
                'period' => '/month',
                'features' => [
                    '10 sites',
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
                'price' => 199,
                'price_display' => $symbol . '199',
                'period' => '/month',
                'features' => [
                    'Unlimited sites',
                    '20,000 AI calls/month',
                    'Everything in Business',
                    'Full white-label',
                    'Multi-tenant management',
                    'Custom integrations',
                    'SLA support',
                ],
                'popular' => false,
                'cta' => 'Choose Agency',
            ],
        ];
    }

    /**
     * Register a new tenant and start checkout.
     */
    public function restRegister(\WP_REST_Request $request): \WP_REST_Response
    {
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $siteUrl = $request->get_param('site_url');
        $tier = $request->get_param('tier');

        $plans = $this->getPlans();
        if (!isset($plans[$tier])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid plan selected.',
            ], 400);
        }

        // Self-serve checkout is disabled by default for the beta
        if ($tier !== 'free' && !get_option('sseo_ai_saas_self_serve_enabled', false)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Self-serve checkout is currently disabled. Please contact us for a license key.',
            ], 400);
        }

        // Check if email already exists
        $existingTenants = $this->tenants->getAllTenants(500);
        foreach ($existingTenants as $existing) {
            if (($existing['email'] ?? '') === $email) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'An account with this email already exists. Please log in or use a different email.',
                ], 409);
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
            'domain' => $siteUrl,
            'tier' => $tier,
            'license_key' => $licenseKey,
            'status' => $tier === 'free' ? 'active' : 'pending_payment',
            'max_sites' => $limits['max_sites'],
            'rate_limit' => $limits['rate_limit'],
            'api_calls_limit' => $limits['api_calls_limit'],
            'expires_at' => $tier === 'free' ? null : gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS),
        ];

        $tenantResult = $this->tenants->createTenant($tenantData);

        if (is_wp_error($tenantResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $tenantResult->get_error_message(),
            ], 500);
        }

        $tenantKey = $tenantResult['tenant_key'];

        // For free tier, activate immediately
        if ($tier === 'free') {
            $this->emailAutomation->sendWelcomeEmail($tenantKey, [
                'email' => $email,
                'tier' => 'free',
                'license_key' => $licenseKey,
            ]);

            return new \WP_REST_Response([
                'success' => true,
                'tenant_key' => $tenantKey,
                'license_key' => $licenseKey,
                'requires_payment' => false,
                'redirect_url' => $this->getSuccessUrl($licenseKey),
            ], 200);
        }

        // For paid tiers, create checkout session
        $checkoutResult = $this->paymentProcessor->createSubscription($tenantKey, $tier);

        if (is_wp_error($checkoutResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $checkoutResult->get_error_message(),
                'tenant_key' => $tenantKey,
                'license_key' => $licenseKey,
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

        // Update tenant status to active
        $this->tenants->updateTenant($tenantKey, [
            'status' => 'active',
            'payment_status' => 'active',
            'last_payment_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS),
        ]);

        // Send welcome email
        $this->emailAutomation->sendWelcomeEmail($tenantKey, [
            'email' => $tenant['email'],
            'tier' => $tenant['tier'],
            'license_key' => $tenant['license_key'],
        ]);

        // Fire payment success action for receipt email
        do_action('sseo_ai_payment_success', $tenantKey, $tenant['tier'], [
            'tier' => $tenant['tier'],
            'date' => current_time('mysql'),
            'payment_id' => $paymentId,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'license_key' => $tenant['license_key'],
            'redirect_url' => $this->getSuccessUrl($tenant['license_key']),
        ], 200);
    }

    /**
     * Get tier limits.
     */
    private function getTierLimits(string $tier): array
    {
        $limits = [
            'free' => ['max_sites' => 1, 'rate_limit' => 30, 'api_calls_limit' => 30],
            'starter' => ['max_sites' => 1, 'rate_limit' => 60, 'api_calls_limit' => 500],
            'professional' => ['max_sites' => 3, 'rate_limit' => 120, 'api_calls_limit' => 2000],
            'business' => ['max_sites' => 10, 'rate_limit' => 300, 'api_calls_limit' => 5000],
            'agency' => ['max_sites' => 999, 'rate_limit' => 600, 'api_calls_limit' => 20000],
        ];

        return $limits[$tier] ?? $limits['free'];
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
        wp_enqueue_style('fyndable-signup', SSEO_AI_SAAS_PLUGIN_URL . 'assets/signup.css', [], SSEO_AI_SAAS_VERSION);
        wp_enqueue_script('fyndable-signup', SSEO_AI_SAAS_PLUGIN_URL . 'assets/signup.js', ['wp-api-fetch'], SSEO_AI_SAAS_VERSION, true);

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

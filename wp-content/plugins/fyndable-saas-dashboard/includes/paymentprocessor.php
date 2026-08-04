<?php

namespace SSEOAISaaS;

/**
 * Payment Processor
 * 
 * Handles payment processing via Stripe and Mollie
 * - Stripe: Credit cards, subscriptions (global)
 * - Mollie: iDEAL, Bancontact, SEPA (Europe)
 */
class PaymentProcessor
{
    private string $provider;
    private string $stripeSecretKey;
    private string $mollieApiKey;
    private string $mollieMode;
    private string $mollieLiveKey;
    private string $mollieTestKey;
    private string $currency;
    private TenantRepository $tenants;

    private const STRIPE_API = 'https://api.stripe.com/v1';
    private const MOLLIE_API = 'https://api.mollie.com/v2';

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
        $this->provider = get_option('sseo_ai_saas_payment_provider', 'stripe');
        $this->stripeSecretKey = get_option('sseo_ai_saas_stripe_secret', '');
        $this->mollieMode = get_option('sseo_ai_saas_mollie_mode', 'live');
        $this->mollieLiveKey = get_option('sseo_ai_saas_mollie_api_key', '');
        $this->mollieTestKey = get_option('sseo_ai_saas_mollie_test_api_key', '');
        $this->mollieApiKey = ($this->mollieMode === 'test' && !empty($this->mollieTestKey)) ? $this->mollieTestKey : $this->mollieLiveKey;
        $this->currency = get_option('sseo_ai_saas_currency', 'EUR');
    }

    /**
     * Get available payment providers
     */
    public static function getProviders(): array
    {
        return [
            'stripe' => [
                'name' => 'Stripe',
                'description' => 'Credit cards, subscriptions (Global)',
                'methods' => ['card', 'sepa_debit'],
                'supports_subscriptions' => true,
            ],
            'mollie' => [
                'name' => 'Mollie',
                'description' => 'iDEAL, Bancontact, SEPA (Europe)',
                'methods' => ['ideal', 'bancontact', 'sofort', 'sepa_direct_debit'],
                'supports_subscriptions' => true,
            ],
        ];
    }

    /**
     * Create a subscription checkout for a tenant
     */
    public function createSubscription(string $tenantKey, string $tier, string $interval = 'month'): array|\WP_Error
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_Error('tenant_not_found', __('Tenant not found', 'sseo-ai-saas'));
        }

        $pricing = $this->getTierPricing($tier, $interval);
        if (is_wp_error($pricing)) {
            return $pricing;
        }

        switch ($this->provider) {
            case 'stripe':
                return $this->createStripeSubscription($tenant, $tier, $pricing);
            case 'mollie':
                return $this->createMollieCheckout($tenant, $tier, $pricing);
            default:
                return new \WP_Error('invalid_provider', __('Invalid payment provider', 'sseo-ai-saas'));
        }
    }

    /**
     * Get pricing for a tier and billing interval
     */
    public function getTierPricing(string $tier, string $interval = 'month'): array|\WP_Error
    {
        $defaultMonthly = [
            'trial' => 0.00,
            'starter' => 29.00,
            'early_adopters' => 14.50,
            'professional' => 79.00,
            'business' => 199.00,
            'agency' => 499.00,
        ];

        // Custom monthly pricing override
        $customPricing = get_option('sseo_ai_saas_pricing', []);
        $monthlyAmount = null;
        if (is_array($customPricing) && !empty($customPricing[$tier])) {
            $custom = $customPricing[$tier];
            if (is_array($custom)) {
                if (isset($custom['monthly_amount'])) {
                    $monthlyAmount = (float) $custom['monthly_amount'];
                } elseif (isset($custom['amount'])) {
                    $monthlyAmount = (float) $custom['amount'];
                }
            }
        }

        if ($monthlyAmount === null) {
            $monthlyAmount = $defaultMonthly[$tier] ?? null;
        }
        if ($monthlyAmount === null) {
            return new \WP_Error('invalid_tier', __('Invalid subscription tier', 'sseo-ai-saas'));
        }

        // Yearly amount: use explicit yearly_amount from custom pricing, otherwise monthly * 12
        $customYearly = null;
        if (is_array($customPricing[$tier] ?? null) && isset($customPricing[$tier]['yearly_amount'])) {
            $customYearly = (float) $customPricing[$tier]['yearly_amount'];
        }

        if ($interval === 'year') {
            $amount = $customYearly ?? (float) round($monthlyAmount * 12, 0);
            $stripeInterval = 'year';
            $mollieInterval = '12 months';
        } else {
            $amount = $monthlyAmount;
            $stripeInterval = 'month';
            $mollieInterval = '1 month';
        }

        return [
            'amount' => $amount,
            'interval' => $stripeInterval,
            'mollie_interval' => $mollieInterval,
        ];
    }

    /**
     * Create Stripe subscription checkout session
     */
    private function createStripeSubscription(array $tenant, string $tier, array $pricing): array|\WP_Error
    {
        if (empty($this->stripeSecretKey)) {
            return new \WP_Error('stripe_not_configured', __('Stripe secret key is not configured', 'sseo-ai-saas'));
        }

        $priceResult = $this->createStripePrice($tier, $pricing);
        if (is_wp_error($priceResult)) {
            return $priceResult;
        }

        $successUrl = $this->getReturnUrl($tenant['tenant_key'], 'stripe');
        $cancelUrl = $this->getCancelUrl($tenant['tenant_key']);

        $session = $this->stripeRequest('checkout/sessions', [
            'mode' => 'subscription',
            'client_reference_id' => $tenant['tenant_key'],
            'customer_email' => $tenant['email'],
            'line_items' => [
                [
                    'price' => $priceResult['id'],
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'tenant_key' => $tenant['tenant_key'],
                'tier' => $tier,
            ],
        ]);

        if (is_wp_error($session)) {
            return $session;
        }

        $this->tenants->setTenantSetting($tenant['tenant_key'], 'stripe_session_id', $session['id']);

        return [
            'provider' => 'stripe',
            'tenant_key' => $tenant['tenant_key'],
            'tier' => $tier,
            'amount' => $pricing['amount'],
            'currency' => $this->currency,
            'status' => 'pending_setup',
            'message' => __('Redirect to Stripe to complete payment setup.', 'sseo-ai-saas'),
            'checkout_url' => $session['url'] ?? '',
        ];
    }

    /**
     * Create a Stripe price for a tier
     */
    private function createStripePrice(string $tier, array $pricing): array|\WP_Error
    {
        $product = $this->stripeRequest('products', [
            'name' => 'Fyndable SmartSEO - ' . ucfirst($tier),
            'metadata' => ['tier' => $tier],
        ]);

        if (is_wp_error($product)) {
            return $product;
        }

        $currency = strtolower($this->currency);
        $amountCents = (int) round($pricing['amount'] * 100);

        $price = $this->stripeRequest('prices', [
            'unit_amount' => $amountCents,
            'currency' => $currency,
            'recurring' => ['interval' => $pricing['interval']],
            'product' => $product['id'],
            'metadata' => ['tier' => $tier],
        ]);

        if (is_wp_error($price)) {
            return $price;
        }

        return $price;
    }

    /**
     * Create Mollie checkout for the first recurring payment
     */
    private function createMollieCheckout(array $tenant, string $tier, array $pricing): array|\WP_Error
    {
        if (empty($this->mollieApiKey)) {
            return new \WP_Error('mollie_not_configured', __('Mollie API key is not configured', 'sseo-ai-saas'));
        }

        $expectedPrefix = $this->mollieMode === 'test' ? 'test_' : 'live_';
        if (strpos($this->mollieApiKey, $expectedPrefix) !== 0) {
            return new \WP_Error(
                'mollie_key_mismatch',
                sprintf(__('Mollie is set to %s mode but the API key does not start with "%s".', 'sseo-ai-saas'), $this->mollieMode, $expectedPrefix)
            );
        }

        $customer = $this->createMollieCustomer($tenant);
        if (is_wp_error($customer)) {
            return $customer;
        }

        $customerId = $customer['id'];
        $this->tenants->setTenantSetting($tenant['tenant_key'], 'mollie_customer_id', $customerId);

        $payment = $this->mollieRequest('payments', [
            'amount' => $this->mollieAmount($pricing['amount']),
            'customerId' => $customerId,
            'sequenceType' => 'first',
            'description' => sprintf(__('Fyndable SmartSEO %s subscription', 'sseo-ai-saas'), ucfirst($tier)),
            'redirectUrl' => $this->getReturnUrl($tenant['tenant_key'], 'mollie'),
            'webhookUrl' => $this->getWebhookUrl('mollie'),
            'metadata' => [
                'tenant_key' => $tenant['tenant_key'],
                'tier' => $tier,
                'interval' => $pricing['interval'] ?? 'month',
                'mollie_interval' => $pricing['mollie_interval'] ?? '1 month',
            ],
        ]);

        if (is_wp_error($payment)) {
            return $payment;
        }

        $this->tenants->setTenantSetting($tenant['tenant_key'], 'mollie_payment_id', $payment['id']);

        return [
            'provider' => 'mollie',
            'tenant_key' => $tenant['tenant_key'],
            'tier' => $tier,
            'amount' => $pricing['amount'],
            'currency' => $this->currency,
            'status' => 'pending_payment',
            'message' => __('Redirect to Mollie to complete the first payment.', 'sseo-ai-saas'),
            'checkout_url' => $payment['_links']['checkout']['href'] ?? '',
        ];
    }

    /**
     * Create a Mollie customer
     */
    private function createMollieCustomer(array $tenant): array|\WP_Error
    {
        $existing = $this->tenants->getTenantSetting($tenant['tenant_key'], 'mollie_customer_id', '');
        if (!empty($existing)) {
            $customer = $this->mollieRequest('customers/' . $existing, [], 'GET');
            if (!is_wp_error($customer)) {
                return $customer;
            }
        }

        return $this->mollieRequest('customers', [
            'email' => $tenant['email'],
            'name' => $tenant['name'],
            'metadata' => [
                'tenant_key' => $tenant['tenant_key'],
            ],
        ]);
    }

    /**
     * Fetch a Mollie payment by ID
     */
    public function fetchMolliePayment(string $paymentId): array|\WP_Error
    {
        if (empty($this->mollieApiKey)) {
            return new \WP_Error('mollie_not_configured', __('Mollie API key is not configured', 'sseo-ai-saas'));
        }

        return $this->mollieRequest('payments/' . $paymentId, [], 'GET');
    }

    /**
     * Create a Mollie subscription after a successful first payment
     */
    public function createMollieSubscription(string $tenantKey, array $payment): array|\WP_Error
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_Error('tenant_not_found', __('Tenant not found', 'sseo-ai-saas'));
        }

        $customerId = $payment['customerId'] ?? '';
        $mandateId = $payment['mandateId'] ?? '';
        $tier = $payment['metadata']['tier'] ?? $tenant['tier'];
        $interval = $payment['metadata']['interval'] ?? 'month';
        $mollieInterval = $payment['metadata']['mollie_interval'] ?? '1 month';

        if (empty($customerId)) {
            return new \WP_Error('mollie_no_customer', __('Mollie payment does not have a customer ID', 'sseo-ai-saas'));
        }

        // If no mandateId in payment response, fetch mandates for the customer
        if (empty($mandateId)) {
            $mandateId = $this->fetchValidMandateId($customerId);
            if (empty($mandateId)) {
                return new \WP_Error('mollie_no_mandate', __('No valid mandate found for Mollie customer. The payment method may not support recurring payments.', 'sseo-ai-saas'));
            }
        }

        $pricing = $this->getTierPricing($tier, $interval);
        if (is_wp_error($pricing)) {
            return $pricing;
        }

        $period = $interval === 'year' ? 'P1Y' : 'P1M';
        $startDate = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->add(new \DateInterval($period))
            ->format('Y-m-d');

        $subscription = $this->mollieRequest('customers/' . $customerId . '/subscriptions', [
            'amount' => $this->mollieAmount($pricing['amount']),
            'interval' => $mollieInterval,
            'startDate' => $startDate,
            'description' => sprintf(__('Fyndable SmartSEO %s subscription', 'sseo-ai-saas'), ucfirst($tier)),
            'mandateId' => $mandateId,
            'webhookUrl' => $this->getWebhookUrl('mollie'),
            'metadata' => [
                'tenant_key' => $tenantKey,
                'tier' => $tier,
                'interval' => $interval,
            ],
        ]);

        if (is_wp_error($subscription)) {
            return $subscription;
        }

        $this->tenants->setTenantSetting($tenantKey, 'mollie_subscription_id', $subscription['id']);
        $this->tenants->setTenantSetting($tenantKey, 'mollie_mandate_id', $mandateId);
        $this->tenants->setTenantSetting($tenantKey, 'subscription_interval', $interval);
        $this->tenants->setTenantSetting($tenantKey, 'mollie_interval', $mollieInterval);

        $expiresPeriod = $interval === 'year' ? '+1 year' : '+1 month';
        $this->tenants->updateTenant($tenantKey, [
            'status' => 'active',
            'payment_status' => 'active',
            'last_payment_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime($expiresPeriod)),
        ]);

        return $subscription;
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $tenantKey): array|\WP_Error
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_Error('tenant_not_found', __('Tenant not found', 'sseo-ai-saas'));
        }

        $stripeSubId = $this->tenants->getTenantSetting($tenantKey, 'stripe_subscription_id', '');
        $mollieSubId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');

        if (!empty($stripeSubId)) {
            $this->stripeRequest('subscriptions/' . $stripeSubId, ['cancel_at_period_end' => true], 'POST');
        } elseif (!empty($mollieSubId)) {
            $mollieCustomerId = $this->tenants->getTenantSetting($tenantKey, 'mollie_customer_id', '');
            if (!empty($mollieCustomerId)) {
                $this->mollieRequest('customers/' . $mollieCustomerId . '/subscriptions/' . $mollieSubId, [], 'DELETE');
            }
        }

        $this->tenants->updateTenant($tenantKey, [
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
        ]);

        return [
            'success' => true,
            'message' => __('Subscription cancelled successfully', 'sseo-ai-saas'),
        ];
    }

    /**
     * Upgrade/downgrade subscription tier
     */
    public function changeTier(string $tenantKey, string $newTier): array|\WP_Error
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_Error('tenant_not_found', __('Tenant not found', 'sseo-ai-saas'));
        }

        $interval = $this->tenants->getTenantSetting($tenantKey, 'subscription_interval', 'month');
        $pricing = $this->getTierPricing($newTier, $interval);
        if (is_wp_error($pricing)) {
            return $pricing;
        }

        $stripeSubId = $this->tenants->getTenantSetting($tenantKey, 'stripe_subscription_id', '');
        if (!empty($stripeSubId)) {
            $items = $this->stripeRequest('subscriptions/' . $stripeSubId, [], 'GET');
            if (!is_wp_error($items) && !empty($items['items']['data'][0]['id'])) {
                $priceResult = $this->createStripePrice($newTier, $pricing);
                if (!is_wp_error($priceResult)) {
                    $this->stripeRequest('subscriptions/' . $stripeSubId, [
                        'items' => [
                            [
                                'id' => $items['items']['data'][0]['id'],
                                'price' => $priceResult['id'],
                            ],
                        ],
                        'proration_behavior' => 'create_prorations',
                    ], 'POST');
                }
            }
        }

        $mollieSubId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');
        if (!empty($mollieSubId)) {
            $mollieCustomerId = $this->tenants->getTenantSetting($tenantKey, 'mollie_customer_id', '');
            if (!empty($mollieCustomerId)) {
                $this->mollieRequest('customers/' . $mollieCustomerId . '/subscriptions/' . $mollieSubId, [
                    'amount' => $this->mollieAmount($pricing['amount']),
                ], 'PATCH');
            }
        }

        $this->tenants->updateTenant($tenantKey, ['tier' => $newTier]);

        return [
            'success' => true,
            'message' => sprintf(__('Subscription upgraded to %s', 'sseo-ai-saas'), ucfirst($newTier)),
            'old_tier' => $tenant['tier'],
            'new_tier' => $newTier,
        ];
    }

    /**
     * Generic Stripe API request
     */
    private function stripeRequest(string $endpoint, array $params, string $method = 'POST'): array|\WP_Error
    {
        $url = self::STRIPE_API . '/' . $endpoint;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->stripeSecretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 60,
        ];

        if ($method === 'GET') {
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
            $response = wp_remote_get($url, $args);
        } else {
            $args['body'] = http_build_query($params);
            $args['method'] = $method;
            $response = wp_remote_request($url, $args);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = [];
        }

        if ($statusCode >= 400 || !empty($body['error'])) {
            $message = $body['error']['message'] ?? __('Stripe API request failed', 'sseo-ai-saas');
            return new \WP_Error('stripe_request_failed', $message, ['status' => $statusCode]);
        }

        return $body;
    }

    /**
     * Generic Mollie API request
     */
    private function mollieRequest(string $endpoint, array $params, string $method = 'POST'): array|\WP_Error
    {
        $url = self::MOLLIE_API . '/' . ltrim($endpoint, '/');
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->mollieApiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
        ];

        if ($method === 'GET') {
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
            $response = wp_remote_get($url, $args);
        } else {
            $args['body'] = json_encode($params);
            $args['method'] = $method;
            $response = wp_remote_request($url, $args);
        }

        if (is_wp_error($response)) {
            error_log('Mollie API connection error: ' . $response->get_error_message());
            return new \WP_Error(
                'mollie_request_failed',
                __('Mollie API connection failed: ', 'sseo-ai-saas') . $response->get_error_message()
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            $body = [];
        }

        if ($statusCode >= 400 || (!empty($body['status']) && is_numeric($body['status']) && $body['status'] >= 400) || !empty($body['detail'])) {
            $message = $body['detail'] ?? ($body['title'] ?? null);
            if (empty($message)) {
                error_log(sprintf('Mollie API error: HTTP %d %s', $statusCode, $rawBody ?: '(empty body)'));
                $message = sprintf(__('Mollie API request failed (HTTP %d): %s', 'sseo-ai-saas'), $statusCode, $rawBody ?: '(empty response)');
            }
            return new \WP_Error('mollie_request_failed', $message, ['status' => $statusCode]);
        }

        return $body;
    }

    /**
     * Format amount for Mollie (currency + value)
     */
    private function mollieAmount(float $amount): array
    {
        $decimals = in_array(strtoupper($this->currency), ['JPY', 'KRW', 'CLP', 'ISK'], true) ? 0 : 2;
        $value = number_format($amount, $decimals, '.', '');

        return [
            'currency' => strtoupper($this->currency),
            'value' => $value,
        ];
    }

    /**
     * Return URL after payment
     */
    private function getReturnUrl(string $tenantKey, string $provider): string
    {
        return add_query_arg([
            'fyndable_payment' => 'success',
            'provider' => $provider,
            'tenant_key' => $tenantKey,
        ], home_url('/thank-you/'));
    }

    /**
     * Cancel URL
     */
    private function getCancelUrl(string $tenantKey): string
    {
        return add_query_arg([
            'fyndable_payment' => 'cancel',
            'tenant_key' => $tenantKey,
        ], home_url('/pricing/'));
    }

    /**
     * Webhook URL for a provider
     */
    public function getWebhookUrl(string $provider): string
    {
        return rest_url('ai-seo-saas/v1/webhooks/' . $provider);
    }

    /**
     * Find tenant by customer ID
     */
    private function findTenantByCustomerId(string $customerId, string $provider): ?string
    {
        return $this->findTenantByProviderId("{$provider}_customer_id", $customerId);
    }

    /**
     * Find tenant by subscription ID
     */
    private function findTenantBySubscriptionId(string $subscriptionId, string $provider): ?string
    {
        return $this->findTenantByProviderId("{$provider}_subscription_id", $subscriptionId);
    }

    /**
     * Find tenant by payment/session ID
     */
    public function findTenantByPaymentId(string $paymentId, string $key): ?string
    {
        return $this->findTenantByProviderId($key, $paymentId);
    }

    /**
     * Find tenant by provider setting
     */
    private function findTenantByProviderId(string $key, string $value): ?string
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . 'sseo_ai_tenant_settings';
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';

        $tenantId = $wpdb->get_var($wpdb->prepare(
            "SELECT tenant_id FROM $settingsTable 
             WHERE setting_key = %s AND setting_value = %s",
            $key,
            $value
        ));

        if ($tenantId) {
            $tenantKey = $wpdb->get_var($wpdb->prepare(
                "SELECT tenant_key FROM $tenantsTable WHERE id = %d",
                $tenantId
            ));
            return $tenantKey ?: null;
        }

        return null;
    }

    /**
     * Fetch a valid mandate ID for a Mollie customer.
     * Returns the first valid mandate, or empty string if none found.
     */
    public function fetchValidMandateId(string $customerId): string
    {
        $mandates = $this->mollieRequest('customers/' . $customerId . '/mandates', [], 'GET');
        if (is_wp_error($mandates) || empty($mandates['_embedded']['mandates'])) {
            return '';
        }

        foreach ($mandates['_embedded']['mandates'] as $mandate) {
            if (($mandate['status'] ?? '') === 'valid') {
                return $mandate['id'] ?? '';
            }
        }

        return '';
    }

    /**
     * Fetch a Mollie subscription by customer and subscription ID.
     */
    public function fetchMollieSubscription(string $tenantKey): array|\WP_Error
    {
        $customerId = $this->tenants->getTenantSetting($tenantKey, 'mollie_customer_id', '');
        $subscriptionId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');

        if (empty($customerId) || empty($subscriptionId)) {
            return new \WP_Error('not_configured', __('Mollie subscription not configured for this tenant', 'sseo-ai-saas'));
        }

        return $this->mollieRequest('customers/' . $customerId . '/subscriptions/' . $subscriptionId, [], 'GET');
    }

    /**
     * Fetch Mollie payments for a customer (for invoice/billing history).
     */
    public function fetchMollieCustomerPayments(string $tenantKey, int $limit = 50): array|\WP_Error
    {
        $customerId = $this->tenants->getTenantSetting($tenantKey, 'mollie_customer_id', '');

        if (empty($customerId)) {
            return new \WP_Error('not_configured', __('Mollie customer not configured for this tenant', 'sseo-ai-saas'));
        }

        return $this->mollieRequest('customers/' . $customerId . '/payments', [
            'limit' => $limit,
            'sort' => 'desc',
        ], 'GET');
    }

    /**
     * Cancel a Mollie subscription.
     */
    public function cancelMollieSubscription(string $tenantKey): array|\WP_Error
    {
        $customerId = $this->tenants->getTenantSetting($tenantKey, 'mollie_customer_id', '');
        $subscriptionId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');

        if (empty($customerId) || empty($subscriptionId)) {
            return new \WP_Error('not_configured', __('Mollie subscription not configured for this tenant', 'sseo-ai-saas'));
        }

        return $this->mollieRequest('customers/' . $customerId . '/subscriptions/' . $subscriptionId, [], 'DELETE');
    }

    /**
     * Send payment failure notification
     */
    private function notifyPaymentFailure(string $tenantKey, array $event, string $provider): void
    {
        $adminEmail = get_option('admin_email');
        $tenant = $this->tenants->getTenant($tenantKey);

        if ($tenant) {
            $subject = sprintf(__('Payment Failed: %s', 'sseo-ai-saas'), $tenant['name']);
            $message = sprintf(
                __("Payment failed for tenant: %s\nProvider: %s\nTenant Email: %s\n\nPlease check the payment dashboard.", 'sseo-ai-saas'),
                $tenant['name'],
                ucfirst($provider),
                $tenant['email']
            );

            wp_mail($adminEmail, $subject, $message);
        }
    }
}

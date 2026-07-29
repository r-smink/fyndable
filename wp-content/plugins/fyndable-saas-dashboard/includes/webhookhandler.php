<?php

namespace SSEOAISaaS;

/**
 * Webhook Handler
 * 
 * Handles incoming webhooks from payment providers
 * - Stripe webhooks
 * - Mollie webhooks
 */
class WebhookHandler
{
    private PaymentProcessor $paymentProcessor;
    private TenantRepository $tenants;
    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(PaymentProcessor $paymentProcessor, TenantRepository $tenants)
    {
        $this->paymentProcessor = $paymentProcessor;
        $this->tenants = $tenants;
    }

    /**
     * Register REST routes for webhooks
     */
    public function register(): void
    {
        // Stripe webhook endpoint
        register_rest_route($this->namespace, '/webhooks/stripe', [
            'methods' => 'POST',
            'callback' => [$this, 'handleStripeWebhook'],
            'permission_callback' => '__return_true', // Webhooks are authenticated via signature
        ]);

        // Mollie webhook endpoint
        register_rest_route($this->namespace, '/webhooks/mollie', [
            'methods' => 'POST',
            'callback' => [$this, 'handleMollieWebhook'],
            'permission_callback' => '__return_true', // Webhooks use id parameter
        ]);

        // Test webhook endpoint (for development)
        register_rest_route($this->namespace, '/webhooks/test', [
            'methods' => 'POST',
            'callback' => [$this, 'handleTestWebhook'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // Get webhook URLs (for admin)
        register_rest_route($this->namespace, '/webhooks/urls', [
            'methods' => 'GET',
            'callback' => [$this, 'getWebhookUrls'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_body();
        $sigHeader = $request->get_header('stripe-signature');
        $webhookSecret = get_option('sseo_ai_saas_stripe_webhook_secret', '');

        // SECURITY: Verify the webhook signature before trusting any data.
        // Fail closed — if no secret is configured, the webhook cannot be trusted.
        if (empty($webhookSecret)) {
            error_log('SSEO AI SaaS: Stripe webhook rejected — webhook secret not configured');
            return new \WP_REST_Response(['error' => 'Webhook secret not configured'], 400);
        }

        if (!$this->verifyStripeSignature($payload, $sigHeader, $webhookSecret)) {
            error_log('SSEO AI SaaS: Stripe webhook signature verification failed');
            return new \WP_REST_Response(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            error_log('SSEO AI SaaS: Invalid Stripe webhook payload');
            return new \WP_REST_Response(['error' => 'Invalid payload'], 400);
        }

        // Idempotency: skip events we have already processed (Stripe retries deliveries).
        $eventId = $event['id'] ?? '';
        if ($eventId !== '' && $this->isEventProcessed($eventId)) {
            return new \WP_REST_Response(['received' => true, 'processed' => false, 'reason' => 'Duplicate event'], 200);
        }

        // Process the event
        $result = $this->processStripeEvent($event);

        if ($eventId !== '') {
            $this->markEventProcessed($eventId);
        }

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Process Stripe event
     */
    private function processStripeEvent(array $event): array
    {
        $type = $event['type'];
        $data = $event['data']['object'] ?? [];

        switch ($type) {
            case 'checkout.session.completed':
                return $this->handleStripeCheckoutCompleted($data);

            case 'invoice.payment_succeeded':
                return $this->handleStripePaymentSuccess($data);

            case 'invoice.payment_failed':
                return $this->handleStripePaymentFailed($data);

            case 'customer.subscription.created':
                return $this->handleStripeSubscriptionCreated($data);

            case 'customer.subscription.updated':
                return $this->handleStripeSubscriptionUpdated($data);

            case 'customer.subscription.deleted':
            case 'customer.subscription.canceled':
                return $this->handleStripeSubscriptionCanceled($data);

            case 'payment_intent.succeeded':
                return $this->handleStripePaymentIntentSucceeded($data);

            case 'payment_intent.payment_failed':
                return $this->handleStripePaymentIntentFailed($data);

            case 'customer.created':
                return $this->handleStripeCustomerCreated($data);

            default:
                error_log('SSEO AI SaaS: Unhandled Stripe event: ' . $type);
                return ['received' => true, 'processed' => false, 'reason' => 'Unhandled event type'];
        }
    }

    /**
     * Handle Stripe checkout session completed
     */
    private function handleStripeCheckoutCompleted(array $session): array
    {
        $tenantKey = $session['client_reference_id'] ?? '';
        $customerId = $session['customer'] ?? '';
        $subscriptionId = $session['subscription'] ?? '';

        if (empty($tenantKey)) {
            return ['received' => true, 'processed' => false, 'reason' => 'No client_reference_id'];
        }

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            error_log("SSEO AI SaaS: No tenant found for checkout session: {$tenantKey}");
            return ['received' => true, 'processed' => false, 'reason' => 'Tenant not found'];
        }

        if ($customerId) {
            $this->tenants->setTenantSetting($tenantKey, 'stripe_customer_id', $customerId);
        }
        if ($subscriptionId) {
            $this->tenants->setTenantSetting($tenantKey, 'stripe_subscription_id', $subscriptionId);
        }

        $this->tenants->updateTenant($tenantKey, [
            'status' => 'active',
            'payment_status' => 'active',
            'last_payment_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        do_action('sseo_ai_payment_success', $tenantKey, $this->formatAmount(($session['amount_total'] ?? 0) / 100, $session['currency'] ?? 'eur'), [
            'tier' => $tenant['tier'],
            'date' => current_time('mysql'),
            'payment_id' => $session['payment_intent'] ?? $session['id'] ?? '',
        ]);

        return [
            'received' => true,
            'processed' => true,
            'tenant_key' => $tenantKey,
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
        ];
    }

    /**
     * Handle Stripe payment success
     */
    private function handleStripePaymentSuccess(array $invoice): array
    {
        $customerId = $invoice['customer'] ?? '';
        $subscriptionId = $invoice['subscription'] ?? '';
        $amount = $invoice['amount_paid'] ?? 0;

        error_log("SSEO AI SaaS: Payment succeeded for customer: {$customerId}");

        // Find tenant by customer ID
        $tenantKey = $this->findTenantByStripeCustomerId($customerId);

        if (!$tenantKey) {
            error_log("SSEO AI SaaS: No tenant found for customer: {$customerId}");
            return ['received' => true, 'processed' => false, 'reason' => 'Tenant not found'];
        }

        // Update tenant
        $this->tenants->updateTenant($tenantKey, [
            'status' => 'active',
            'payment_status' => 'active',
            'last_payment_at' => current_time('mysql'),
            'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+1 month')),
        ]);

        // Store subscription ID if provided
        if ($subscriptionId) {
            $this->tenants->setTenantSetting($tenantKey, 'stripe_subscription_id', $subscriptionId);
        }

        // Create invoice record
        $this->createInvoiceRecord($tenantKey, 'stripe', $amount / 100, $invoice['currency'] ?? 'eur', $invoice['id']);

        // Send notification
        do_action('sseo_ai_payment_success', $tenantKey, $this->formatAmount(($invoice['amount_paid'] ?? 0) / 100, $invoice['currency'] ?? 'eur'), [
            'tier' => $tenant['tier'],
            'date' => current_time('mysql'),
            'payment_id' => $invoice['id'] ?? '',
        ]);

        return [
            'received' => true,
            'processed' => true,
            'tenant_key' => $tenantKey,
            'amount' => $amount / 100,
        ];
    }

    /**
     * Handle Stripe payment failure
     */
    private function handleStripePaymentFailed(array $invoice): array
    {
        $customerId = $invoice['customer'] ?? '';
        $nextPaymentAttempt = $invoice['next_payment_attempt'] ?? null;

        $tenantKey = $this->findTenantByStripeCustomerId($customerId);

        if ($tenantKey) {
            $this->tenants->updateTenant($tenantKey, [
                'payment_status' => 'failed',
            ]);

            // Notify admin
            $this->notifyPaymentFailure($tenantKey, $invoice, 'stripe');

            do_action('sseo_ai_payment_failed', $tenantKey, [
                'amount' => $this->formatAmount(($invoice['amount_due'] ?? 0) / 100, $invoice['currency'] ?? 'eur'),
            ]);
        }

        return ['received' => true, 'processed' => true, 'tenant_key' => $tenantKey];
    }

    /**
     * Handle Stripe subscription created
     */
    private function handleStripeSubscriptionCreated(array $subscription): array
    {
        $customerId = $subscription['customer'] ?? '';
        $subscriptionId = $subscription['id'] ?? '';

        $tenantKey = $this->findTenantByStripeCustomerId($customerId);

        if ($tenantKey) {
            $this->tenants->setTenantSetting($tenantKey, 'stripe_subscription_id', $subscriptionId);
            $this->tenants->setTenantSetting($tenantKey, 'stripe_customer_id', $customerId);

            do_action('sseo_ai_saas_subscription_created', $tenantKey, $subscription, 'stripe');
        }

        return ['received' => true, 'processed' => true, 'tenant_key' => $tenantKey];
    }

    /**
     * Handle Stripe subscription updated
     */
    private function handleStripeSubscriptionUpdated(array $subscription): array
    {
        $subscriptionId = $subscription['id'] ?? '';
        $status = $subscription['status'] ?? '';

        $tenantKey = $this->findTenantByStripeSubscriptionId($subscriptionId);

        if ($tenantKey) {
            // Update status based on subscription state
            $tenantStatus = 'active';
            $paymentStatus = 'active';
            if ($status === 'past_due') {
                $tenantStatus = 'active';
                $paymentStatus = 'past_due';
            } elseif ($status === 'unpaid') {
                $tenantStatus = 'suspended';
                $paymentStatus = 'unpaid';
            } elseif ($status === 'canceled') {
                $tenantStatus = 'cancelled';
                $paymentStatus = 'cancelled';
            }

            $this->tenants->updateTenant($tenantKey, [
                'status' => $tenantStatus,
                'payment_status' => $paymentStatus,
            ]);

            do_action('sseo_ai_saas_subscription_updated', $tenantKey, $subscription, 'stripe');
        }

        return ['received' => true, 'processed' => true, 'tenant_key' => $tenantKey];
    }

    /**
     * Handle Stripe subscription canceled
     */
    private function handleStripeSubscriptionCanceled(array $subscription): array
    {
        $subscriptionId = $subscription['id'] ?? '';

        $tenantKey = $this->findTenantByStripeSubscriptionId($subscriptionId);

        if ($tenantKey) {
            $this->tenants->updateTenant($tenantKey, [
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
            ]);

            do_action('sseo_ai_saas_subscription_cancelled', $tenantKey, $subscription, 'stripe');
        }

        return ['received' => true, 'processed' => true, 'tenant_key' => $tenantKey];
    }

    /**
     * Handle Stripe payment intent succeeded
     */
    private function handleStripePaymentIntentSucceeded(array $paymentIntent): array
    {
        // Handle one-time payments or setup intents
        $customerId = $paymentIntent['customer'] ?? '';
        
        error_log("SSEO AI SaaS: Payment intent succeeded for customer: {$customerId}");

        return ['received' => true, 'processed' => false, 'reason' => 'One-time payment not processed'];
    }

    /**
     * Handle Stripe payment intent failed
     */
    private function handleStripePaymentIntentFailed(array $paymentIntent): array
    {
        $customerId = $paymentIntent['customer'] ?? '';
        $error = $paymentIntent['last_payment_error']['message'] ?? 'Unknown error';

        error_log("SSEO AI SaaS: Payment intent failed for customer: {$customerId}, error: {$error}");

        return ['received' => true, 'processed' => false];
    }

    /**
     * Handle Stripe customer created
     */
    private function handleStripeCustomerCreated(array $customer): array
    {
        $customerId = $customer['id'] ?? '';
        $email = $customer['email'] ?? '';

        // Try to find tenant by email
        $tenants = $this->tenants->getTenants(['search' => $email], 10, 0);
        
        if (!empty($tenants)) {
            $tenant = $tenants[0];
            $this->tenants->setTenantSetting($tenant['tenant_key'], 'stripe_customer_id', $customerId);
        }

        return ['received' => true, 'processed' => true];
    }

    /**
     * Handle Mollie webhook
     */
    public function handleMollieWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $paymentId = $request->get_param('id');

        if (empty($paymentId)) {
            error_log('SSEO AI SaaS: Mollie webhook received without payment ID');
            return new \WP_REST_Response(['error' => 'No payment ID'], 400);
        }

        error_log("SSEO AI SaaS: Mollie webhook received for payment: {$paymentId}");

        $result = $this->processMolliePayment($paymentId);

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Process Mollie payment
     */
    private function processMolliePayment(string $paymentId): array
    {
        $payment = $this->paymentProcessor->fetchMolliePayment($paymentId);

        if (is_wp_error($payment)) {
            error_log('SSEO AI SaaS: Mollie payment fetch failed: ' . $payment->get_error_message());
            return ['received' => true, 'processed' => false, 'reason' => $payment->get_error_message()];
        }

        $status = $payment['status'] ?? '';
        $tenantKey = $payment['metadata']['tenant_key'] ?? '';
        $subscriptionId = $payment['subscriptionId'] ?? '';
        $customerId = $payment['customerId'] ?? '';

        if (empty($tenantKey) && !empty($subscriptionId)) {
            $tenantKey = $this->findTenantByProviderId('mollie_subscription_id', $subscriptionId);
        }
        if (empty($tenantKey) && !empty($customerId)) {
            $tenantKey = $this->findTenantByProviderId('mollie_customer_id', $customerId);
        }
        if (empty($tenantKey)) {
            $tenantKey = $this->findTenantByMolliePaymentId($paymentId);
        }

        if (!$tenantKey) {
            error_log("SSEO AI SaaS: No tenant found for Mollie payment: {$paymentId}");
            return ['received' => true, 'processed' => false, 'reason' => 'Tenant not found'];
        }

        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return ['received' => true, 'processed' => false, 'reason' => 'Tenant not found'];
        }

        if ($status !== 'paid') {
            $this->tenants->updateTenant($tenantKey, [
                'payment_status' => $status,
            ]);
            return ['received' => true, 'processed' => true, 'tenant_key' => $tenantKey, 'status' => $status];
        }

        $sequenceType = $payment['sequenceType'] ?? '';
        $subscriptionId = $payment['subscriptionId'] ?? '';

        if ($sequenceType === 'first') {
            $subscription = $this->paymentProcessor->createMollieSubscription($tenantKey, $payment);
            if (is_wp_error($subscription)) {
                error_log('SSEO AI SaaS: Mollie subscription creation failed: ' . $subscription->get_error_message());
                return ['received' => true, 'processed' => false, 'reason' => $subscription->get_error_message()];
            }
        } else {
            if (!empty($subscriptionId)) {
                $this->tenants->setTenantSetting($tenantKey, 'mollie_subscription_id', $subscriptionId);
            }

            $interval = $this->tenants->getTenantSetting($tenantKey, 'subscription_interval', 'month');
            $period = $interval === 'year' ? '+1 year' : '+1 month';
            $this->tenants->updateTenant($tenantKey, [
                'status' => 'active',
                'payment_status' => 'active',
                'last_payment_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime($period)),
            ]);
        }

        // Create invoice record for this Mollie payment
        $paymentAmount = (float)($payment['amount']['value'] ?? 0);
        $paymentCurrency = $payment['amount']['currency'] ?? 'eur';
        $this->createInvoiceRecord($tenantKey, 'mollie', $paymentAmount, $paymentCurrency, $payment['id'] ?? '');

        do_action('sseo_ai_payment_success', $tenantKey, $this->formatAmount($paymentAmount, $paymentCurrency), [
            'tier' => $tenant['tier'],
            'date' => current_time('mysql'),
            'payment_id' => $payment['id'] ?? '',
        ]);

        return [
            'received' => true,
            'processed' => true,
            'tenant_key' => $tenantKey,
            'payment_id' => $paymentId,
        ];
    }

    /**
     * Handle test webhook (for development)
     */
    public function handleTestWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params();

        error_log('SSEO AI SaaS: Test webhook received');
        error_log(print_r($body, true));

        return new \WP_REST_Response([
            'received' => true,
            'body' => $body,
            'message' => 'Test webhook received successfully',
        ], 200);
    }

    /**
     * Get webhook URLs for admin
     */
    public function getWebhookUrls(): \WP_REST_Response
    {
        $urls = [
            'stripe' => rest_url($this->namespace . '/webhooks/stripe'),
            'mollie' => rest_url($this->namespace . '/webhooks/mollie'),
            'test' => rest_url($this->namespace . '/webhooks/test'),
        ];

        return new \WP_REST_Response([
            'success' => true,
            'urls' => $urls,
            'instructions' => [
                'stripe' => 'Add this URL to your Stripe Dashboard → Developers → Webhooks',
                'mollie' => 'Add this URL to your Mollie Dashboard → Settings → Webhooks',
            ],
        ], 200);
    }

    /**
     * Find tenant by Stripe customer ID
     */
    private function findTenantByStripeCustomerId(string $customerId): ?string
    {
        return $this->findTenantByProviderId('stripe_customer_id', $customerId);
    }

    /**
     * Find tenant by Stripe subscription ID
     */
    private function findTenantByStripeSubscriptionId(string $subscriptionId): ?string
    {
        return $this->findTenantByProviderId('stripe_subscription_id', $subscriptionId);
    }

    /**
     * Find tenant by Mollie payment ID
     */
    private function findTenantByMolliePaymentId(string $paymentId): ?string
    {
        return $this->findTenantByProviderId('mollie_payment_id', $paymentId);
    }

    /**
     * Verify a Stripe webhook signature using HMAC-SHA256.
     * Implements the same scheme as the Stripe SDK without requiring it.
     * Includes replay protection via the signed timestamp.
     */
    private function verifyStripeSignature(string $payload, ?string $sigHeader, string $secret): bool
    {
        if (empty($secret) || empty($sigHeader)) {
            return false;
        }

        // Header format: t=timestamp,v1=signature[,v1=signature...]
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $sigHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$k, $v] = $kv;
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || !ctype_digit((string) $timestamp) || empty($signatures)) {
            return false;
        }

        // Replay protection: reject signatures older than 5 minutes.
        $tolerance = 300;
        if (abs(time() - (int) $timestamp) > $tolerance) {
            error_log('SSEO AI SaaS: Stripe webhook timestamp outside tolerance');
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Idempotency: has this webhook event already been processed?
     */
    private function isEventProcessed(string $eventId): bool
    {
        return get_transient('sseo_ai_saas_evt_' . md5($eventId)) !== false;
    }

    /**
     * Idempotency: mark a webhook event as processed (retained 24h).
     */
    private function markEventProcessed(string $eventId): void
    {
        set_transient('sseo_ai_saas_evt_' . md5($eventId), 1, DAY_IN_SECONDS);
    }

    /**
     * Find tenant by provider ID
     */
    private function findTenantByProviderId(string $key, string $value): ?string
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . 'sseo_ai_tenant_settings';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT tenant_id FROM $settingsTable 
             WHERE setting_key = %s AND setting_value = %s",
            $key,
            $value
        ));

        if ($result) {
            $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';
            $tenantKey = $wpdb->get_var($wpdb->prepare(
                "SELECT tenant_key FROM $tenantsTable WHERE id = %d",
                $result
            ));
            return $tenantKey;
        }

        return null;
    }

    /**
     * Create invoice record
     */
    private function createInvoiceRecord(string $tenantKey, string $provider, float $amount, string $currency, string $externalId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_invoices';

        // Create invoices table if it doesn't exist
        $this->maybeCreateInvoicesTable();

        // Generate invoice number
        $year = date('Y');
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE invoice_number LIKE %s",
            "FYND-$year-%"
        ));
        $invoiceNumber = sprintf('FYND-%s-%04d', $year, $count + 1);

        $tenant = $this->tenants->getTenant($tenantKey);
        $tier = $tenant['tier'] ?? '';
        $interval = $this->tenants->getTenantSetting($tenantKey, 'subscription_interval', 'month');

        $wpdb->insert($table, [
            'tenant_key' => $tenantKey,
            'provider' => $provider,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'external_id' => $externalId,
            'status' => 'paid',
            'invoice_number' => $invoiceNumber,
            'tier' => $tier,
            'billing_interval' => $interval,
            'description' => sprintf('Fyndable SmartSEO %s - %s subscription', ucfirst($tier), $interval),
            'created_at' => current_time('mysql'),
            'paid_at' => current_time('mysql'),
        ]);

        do_action('sseo_ai_invoice_created', $tenantKey, $wpdb->insert_id, $invoiceNumber);
    }

    /**
     * Create invoices table if it doesn't exist
     */
    private function maybeCreateInvoicesTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_invoices';

        // Check if table exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));

        if ($exists) {
            return;
        }

        // Create table
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_key varchar(64) NOT NULL,
            provider varchar(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'EUR',
            external_id varchar(255) DEFAULT NULL,
            status enum('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
            invoice_number varchar(50) DEFAULT NULL,
            tier varchar(20) DEFAULT NULL,
            billing_interval varchar(10) DEFAULT NULL,
            description varchar(255) DEFAULT NULL,
            mollie_subscription_id varchar(255) DEFAULT NULL,
            pdf_path varchar(500) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY tenant_key (tenant_key),
            KEY provider (provider),
            KEY status (status),
            KEY created_at (created_at),
            KEY invoice_number (invoice_number)
        ) $charsetCollate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Send payment failure notification
     */
    private function notifyPaymentFailure(string $tenantKey, array $data, string $provider): void
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return;
        }

        $adminEmail = get_option('admin_email');
        $subject = sprintf(__('[%s] Payment Failed: %s', 'sseo-ai-saas'), get_bloginfo('name'), $tenant['name']);

        $message = sprintf(
            __("Hello,

A payment has failed for the following tenant:

Tenant: %s
Email: %s
Provider: %s
Time: %s

Please check the payment dashboard for more details.

---
This is an automated message from %s", 'sseo-ai-saas'),
            $tenant['name'],
            $tenant['email'],
            ucfirst($provider),
            current_time('mysql'),
            get_bloginfo('name')
        );

        wp_mail($adminEmail, $subject, $message);
    }

    /**
     * Format an amount with a currency symbol.
     */
    private function formatAmount(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
        ];
        $symbol = $symbols[$currency] ?? ($currency . ' ');

        return $symbol . number_format_i18n($amount, 2);
    }
}

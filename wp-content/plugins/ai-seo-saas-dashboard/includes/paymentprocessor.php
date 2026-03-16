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
    private ?string $stripeSecretKey = null;
    private ?string $mollieApiKey = null;
    private string $currency;
    private TenantRepository $tenants;
    
    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
        $this->provider = get_option('sseo_ai_saas_payment_provider', 'stripe');
        $this->stripeSecretKey = get_option('sseo_ai_saas_stripe_secret', '');
        $this->mollieApiKey = get_option('sseo_ai_saas_mollie_api_key', '');
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
     * Create a subscription for a tenant
     */
    public function createSubscription(string $tenantKey, string $tier): array|\WP_Error
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_Error('tenant_not_found', __('Tenant not found', 'sseo-ai-saas'));
        }
        
        // Get tier pricing
        $pricing = $this->getTierPricing($tier);
        if (is_wp_error($pricing)) {
            return $pricing;
        }
        
        switch ($this->provider) {
            case 'stripe':
                return $this->createStripeSubscription($tenant, $tier, $pricing);
            case 'mollie':
                return $this->createMollieSubscription($tenant, $tier, $pricing);
            default:
                return new \WP_Error('invalid_provider', __('Invalid payment provider', 'sseo-ai-saas'));
        }
    }
    
    /**
     * Get pricing for a tier
     */
    private function getTierPricing(string $tier): array|\WP_Error
    {
        $pricing = [
            'basic' => ['amount' => 19.00, 'interval' => 'month'],
            'professional' => ['amount' => 49.00, 'interval' => 'month'],
            'agency' => ['amount' => 99.00, 'interval' => 'month'],
        ];
        
        if (!isset($pricing[$tier])) {
            return new \WP_Error('invalid_tier', __('Invalid subscription tier', 'sseo-ai-saas'));
        }
        
        return $pricing[$tier];
    }
    
    /**
     * Create Stripe subscription
     */
    private function createStripeSubscription(array $tenant, string $tier, array $pricing): array|\WP_Error
    {
        if (empty($this->stripeSecretKey)) {
            return new \WP_Error('stripe_not_configured', __('Stripe not configured', 'sseo-ai-saas'));
        }
        
        // This would integrate with Stripe PHP SDK
        // For now, return a placeholder that indicates the integration point
        return [
            'provider' => 'stripe',
            'tenant_key' => $tenant['tenant_key'],
            'tier' => $tier,
            'amount' => $pricing['amount'],
            'currency' => $this->currency,
            'status' => 'pending_setup',
            'message' => __('Stripe subscription created. Customer needs to complete payment setup.', 'sseo-ai-saas'),
            'checkout_url' => $this->generateStripeCheckoutUrl($tenant, $tier, $pricing),
        ];
    }
    
    /**
     * Create Mollie subscription
     */
    private function createMollieSubscription(array $tenant, string $tier, array $pricing): array|\WP_Error
    {
        if (empty($this->mollieApiKey)) {
            return new \WP_Error('mollie_not_configured', __('Mollie not configured', 'sseo-ai-saas'));
        }
        
        // This would integrate with Mollie PHP SDK
        // For now, return a placeholder
        return [
            'provider' => 'mollie',
            'tenant_key' => $tenant['tenant_key'],
            'tier' => $tier,
            'amount' => $pricing['amount'],
            'currency' => $this->currency,
            'status' => 'pending_payment',
            'message' => __('Mollie payment initiated. Customer needs to complete payment.', 'sseo-ai-saas'),
            'checkout_url' => $this->generateMollieCheckoutUrl($tenant, $tier, $pricing),
        ];
    }
    
    /**
     * Generate Stripe Checkout URL
     */
    private function generateStripeCheckoutUrl(array $tenant, string $tier, array $pricing): string
    {
        // This would generate a Stripe Checkout Session URL
        // Placeholder for actual implementation
        return add_query_arg([
            'stripe_checkout' => '1',
            'tenant' => $tenant['tenant_key'],
            'tier' => $tier,
        ], home_url('/stripe-checkout/'));
    }
    
    /**
     * Generate Mollie Checkout URL
     */
    private function generateMollieCheckoutUrl(array $tenant, string $tier, array $pricing): string
    {
        // This would create a Mollie payment and return the checkout URL
        // Placeholder for actual implementation
        return add_query_arg([
            'mollie_checkout' => '1',
            'tenant' => $tenant['tenant_key'],
            'tier' => $tier,
        ], home_url('/mollie-checkout/'));
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(): \WP_REST_Response
    {
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = get_option('sseo_ai_saas_stripe_webhook_secret', '');
        
        // Verify webhook signature (when properly configured)
        // This is a placeholder - real implementation needs Stripe PHP SDK
        
        $event = json_decode($payload, true);
        
        if (!$event || !isset($event['type'])) {
            return new \WP_REST_Response(['error' => 'Invalid payload'], 400);
        }
        
        switch ($event['type']) {
            case 'invoice.payment_succeeded':
                $this->handlePaymentSuccess($event, 'stripe');
                break;
                
            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event, 'stripe');
                break;
                
            case 'customer.subscription.deleted':
                $this->handleSubscriptionCanceled($event, 'stripe');
                break;
                
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event, 'stripe');
                break;
        }
        
        return new \WP_REST_Response(['received' => true], 200);
    }
    
    /**
     * Handle Mollie webhook
     */
    public function handleMollieWebhook(): \WP_REST_Response
    {
        $paymentId = $_POST['id'] ?? '';
        
        if (empty($paymentId)) {
            return new \WP_REST_Response(['error' => 'No payment ID'], 400);
        }
        
        // Fetch payment from Mollie API (placeholder)
        // Real implementation needs Mollie PHP SDK
        
        // Update tenant status based on payment result
        $this->updateTenantPaymentStatus($paymentId, 'completed', 'mollie');
        
        return new \WP_REST_Response(['received' => true], 200);
    }
    
    /**
     * Handle successful payment
     */
    private function handlePaymentSuccess(array $event, string $provider): void
    {
        // Extract tenant information from event
        $customerId = $event['data']['object']['customer'] ?? '';
        $subscriptionId = $event['data']['object']['subscription'] ?? '';
        
        // Find tenant by customer ID
        $tenantKey = $this->findTenantByCustomerId($customerId, $provider);
        
        if ($tenantKey) {
            // Update tenant status
            $this->tenants->updateTenant($tenantKey, [
                'status' => 'active',
                'payment_status' => 'paid',
                'last_payment_at' => current_time('mysql'),
            ]);
            
            // Store subscription ID
            $this->tenants->setTenantSetting($tenantKey, "{$provider}_subscription_id", $subscriptionId);
            
            do_action('sseo_ai_saas_payment_success', $tenantKey, $event, $provider);
        }
    }
    
    /**
     * Handle failed payment
     */
    private function handlePaymentFailed(array $event, string $provider): void
    {
        $customerId = $event['data']['object']['customer'] ?? '';
        $tenantKey = $this->findTenantByCustomerId($customerId, $provider);
        
        if ($tenantKey) {
            $this->tenants->updateTenant($tenantKey, [
                'payment_status' => 'failed',
            ]);
            
            // Send notification to admin
            $this->notifyPaymentFailure($tenantKey, $event, $provider);
            
            do_action('sseo_ai_saas_payment_failed', $tenantKey, $event, $provider);
        }
    }
    
    /**
     * Handle subscription cancellation
     */
    private function handleSubscriptionCanceled(array $event, string $provider): void
    {
        $subscriptionId = $event['data']['object']['id'] ?? '';
        $tenantKey = $this->findTenantBySubscriptionId($subscriptionId, $provider);
        
        if ($tenantKey) {
            $this->tenants->updateTenant($tenantKey, [
                'status' => 'cancelled',
                'payment_status' => 'cancelled',
            ]);
            
            do_action('sseo_ai_saas_subscription_cancelled', $tenantKey, $event, $provider);
        }
    }
    
    /**
     * Handle subscription update
     */
    private function handleSubscriptionUpdated(array $event, string $provider): void
    {
        $subscriptionId = $event['data']['object']['id'] ?? '';
        $tenantKey = $this->findTenantBySubscriptionId($subscriptionId, $provider);
        
        if ($tenantKey) {
            // Update subscription details
            $status = $event['data']['object']['status'] ?? '';
            
            if ($status === 'past_due') {
                $this->tenants->updateTenant($tenantKey, [
                    'payment_status' => 'past_due',
                ]);
            }
            
            do_action('sseo_ai_saas_subscription_updated', $tenantKey, $event, $provider);
        }
    }
    
    /**
     * Find tenant by customer ID
     */
    private function findTenantByCustomerId(string $customerId, string $provider): ?string
    {
        // This would search tenants by payment provider customer ID
        // Implementation depends on how customer IDs are stored
        global $wpdb;
        $settingsTable = $wpdb->prefix . 'sseo_ai_tenant_settings';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT tenant_id FROM $settingsTable 
             WHERE setting_key = %s AND setting_value = %s",
            "{$provider}_customer_id",
            $customerId
        ));
        
        if ($result) {
            $tenant = $this->tenants->getTenant((string)$result);
            return $tenant['tenant_key'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Find tenant by subscription ID
     */
    private function findTenantBySubscriptionId(string $subscriptionId, string $provider): ?string
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . 'sseo_ai_tenant_settings';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT tenant_id FROM $settingsTable 
             WHERE setting_key = %s AND setting_value = %s",
            "{$provider}_subscription_id",
            $subscriptionId
        ));
        
        if ($result) {
            $tenant = $this->tenants->getTenant((string)$result);
            return $tenant['tenant_key'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Update tenant payment status
     */
    private function updateTenantPaymentStatus(string $paymentId, string $status, string $provider): void
    {
        // Implementation for updating payment status
        // This would find the tenant by payment ID and update their status
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
    
    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $tenantKey): array|\WP_Error
    {
        $tenant = $this->tenants->getTenant($tenantKey);
        if (!$tenant) {
            return new \WP_Error('tenant_not_found', __('Tenant not found', 'sseo-ai-saas'));
        }
        
        // Get subscription IDs
        $stripeSubId = $this->tenants->getTenantSetting($tenantKey, 'stripe_subscription_id', '');
        $mollieSubId = $this->tenants->getTenantSetting($tenantKey, 'mollie_subscription_id', '');
        
        // Cancel via appropriate provider
        if (!empty($stripeSubId)) {
            // Cancel Stripe subscription
            // Implementation with Stripe SDK
        } elseif (!empty($mollieSubId)) {
            // Cancel Mollie subscription
            // Implementation with Mollie SDK
        }
        
        // Update tenant status
        $this->tenants->updateTenant($tenantKey, [
            'status' => 'cancelled',
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
        
        $currentTier = $tenant['tier'] ?? 'basic';
        
        // Get pricing for new tier
        $pricing = $this->getTierPricing($newTier);
        if (is_wp_error($pricing)) {
            return $pricing;
        }
        
        // Update subscription with provider
        // Implementation depends on provider SDK
        
        // Update tenant tier
        $this->tenants->updateTenant($tenantKey, [
            'tier' => $newTier,
        ]);
        
        return [
            'success' => true,
            'message' => sprintf(__('Subscription upgraded to %s', 'sseo-ai-saas'), ucfirst($newTier)),
            'old_tier' => $currentTier,
            'new_tier' => $newTier,
        ];
    }
}

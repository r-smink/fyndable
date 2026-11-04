<?php

namespace SSEOAISaaS;

/**
 * Shopify App Pricing sync endpoint
 *
 * The Shopify app (shopify.fyndable.ai) reads the merchant's subscription
 * from the Shopify Partner API and pushes the result here. This endpoint
 * creates or updates the shop's tenant + license (platform = shopify) so all
 * feature gating keeps running through the normal tenant/status flow.
 *
 * Server-to-server only. Authentication: HMAC-SHA256 over
 * "{timestamp}.{raw body}" with the shared secret, sent as
 * X-Fyndable-Timestamp + X-Fyndable-Signature (hex). Requests older than
 * 5 minutes are rejected (replay protection).
 *
 * Secret: constant FYNDABLE_SHOPIFY_SYNC_SECRET in wp-config.php, or the
 * option sseo_ai_saas_shopify_sync_secret. Never logged.
 *
 * POST /ai-seo-saas/v1/shopify/subscription
 * Body: {
 *   shop_domain: "store.myshopify.com",
 *   shop_name, shop_email,
 *   state: "active" | "trial" | "none" | "uninstalled",
 *   tier: "starter" | "professional" | "business" | "trial" (state active/trial),
 *   plan_handle, trial_ends_at (in-app trial end for state trial, sets the
 *   tenant expires_at), cycle_ends_at, cancel_at_end_of_cycle
 * }
 * Returns: { success, tenant_key, license_key, tier, status }
 */
class ShopifyBillingApi
{
    private const MAX_CLOCK_SKEW = 300;

    private const SETTING_SHOP = 'shopify_shop_domain';

    private const SETTING_BILLING = 'shopify_billing';

    private const PAID_TIERS = ['starter', 'professional', 'business'];

    private const STATES = ['active', 'trial', 'none', 'uninstalled'];

    private string $namespace = 'ai-seo-saas/v1';

    public function __construct(
        private LicenseKeyGenerator $licenseGenerator,
        private TenantRepository $tenants
    ) {
    }

    public function register(): void
    {
        register_rest_route($this->namespace, '/shopify/subscription', [
            'methods' => 'POST',
            'callback' => [$this, 'syncSubscription'],
            // Authenticated by the HMAC signature inside the callback.
            'permission_callback' => '__return_true',
        ]);
    }

    public function syncSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $auth = $this->verifySignature($request);
        if ($auth !== true) {
            return $auth;
        }

        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return $this->error('invalid_body', 'Request body must be JSON.', 400);
        }

        $shopDomain = self::normalizeShopDomain((string)($data['shop_domain'] ?? ''));
        if ($shopDomain === null) {
            return $this->error('invalid_shop', 'Invalid shop_domain.', 400);
        }

        $state = (string)($data['state'] ?? '');
        if (!in_array($state, self::STATES, true)) {
            return $this->error('invalid_state', 'Invalid state.', 400);
        }

        $tier = (string)($data['tier'] ?? '');
        if ($state === 'active' && !in_array($tier, self::PAID_TIERS, true)) {
            return $this->error('invalid_tier', 'Active subscriptions need a paid tier.', 400);
        }
        if ($state === 'trial') {
            $tier = 'trial';
        }

        // Serialize per shop so two concurrent syncs never create two tenants.
        global $wpdb;
        $lockName = 'sseo_shopify_' . md5($shopDomain);
        $locked = (int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lockName));
        if ($locked !== 1) {
            return $this->error('busy', 'Another sync for this shop is running.', 409);
        }

        try {
            return $this->applySync($shopDomain, $state, $tier, $data);
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
        }
    }

    private function applySync(string $shopDomain, string $state, string $tier, array $data): \WP_REST_Response
    {
        $tenant = $this->findTenantByShop($shopDomain);
        $billing = [
            'state' => $state,
            'plan_handle' => sanitize_key((string)($data['plan_handle'] ?? '')),
            'trial_ends_at' => $this->sanitizeDate($data['trial_ends_at'] ?? null),
            'cycle_ends_at' => $this->sanitizeDate($data['cycle_ends_at'] ?? null),
            'cancel_at_end_of_cycle' => !empty($data['cancel_at_end_of_cycle']),
            'synced_at' => current_time('mysql'),
        ];

        // Nothing to suspend for a shop that never had a tenant.
        if ($tenant === null && in_array($state, ['none', 'uninstalled'], true)) {
            return new \WP_REST_Response([
                'success' => true,
                'tenant_key' => null,
                'license_key' => null,
                'tier' => null,
                'status' => 'none',
            ], 200);
        }

        if ($tenant === null) {
            $tenant = $this->createShopTenant($shopDomain, $tier, $data);
            if ($tenant instanceof \WP_Error) {
                error_log('SSEO AI Shopify billing: tenant creation failed - ' . $tenant->get_error_code());
                return $this->error($tenant->get_error_code(), $tenant->get_error_message(), 500);
            }
        }

        $update = [
            'platform' => 'shopify',
            'last_active' => current_time('mysql'),
        ];

        if (in_array($state, ['active', 'trial'], true)) {
            $update['status'] = 'active';
            $update['tier'] = $tier;
            $update['payment_status'] = $state === 'active' ? 'shopify' : 'trial';
            // Paid: Shopify bills the subscription, the tenant never expires.
            // In-app trial: expire at the trial end, so the tenant stops even
            // when the app cannot send the final sync.
            $update['expires_at'] = $state === 'trial' ? $billing['trial_ends_at'] : null;
        } else {
            // Cancelled, frozen, trial used up or uninstalled: keep the data,
            // block usage until a new plan is chosen.
            $update['status'] = 'suspended';
        }

        $email = sanitize_email((string)($data['shop_email'] ?? ''));
        if ($email !== '' && is_email($email)) {
            $update['email'] = $email;
        }

        $this->tenants->updateTenant($tenant['tenant_key'], $update);
        if (array_key_exists('expires_at', $update) && $update['expires_at'] === null) {
            $this->clearTenantExpiry($tenant['tenant_key']);
        }
        $this->tenants->setTenantSetting($tenant['tenant_key'], self::SETTING_BILLING, $billing);

        return new \WP_REST_Response([
            'success' => true,
            'tenant_key' => $tenant['tenant_key'],
            'license_key' => $tenant['license_key'],
            'tier' => $update['tier'] ?? $tenant['tier'],
            'status' => $update['status'],
        ], 200);
    }

    /**
     * Generate a license and activate it for the shop (creates the tenant).
     *
     * @return array{tenant_key: string, license_key: string, tier: string}|\WP_Error
     */
    private function createShopTenant(string $shopDomain, string $tier, array $data): array|\WP_Error
    {
        $email = sanitize_email((string)($data['shop_email'] ?? ''));

        $license = $this->licenseGenerator->generateLicense([
            'tier' => $tier,
            'type' => $tier === 'trial' ? 'trial' : 'paid',
            'assigned_to' => is_email($email) ? $email : $shopDomain,
            'notes' => 'Shopify App Pricing: ' . $shopDomain,
        ]);
        if (is_wp_error($license)) {
            return $license;
        }

        $licenseKey = (string)$license['license_key'];
        $activated = $this->licenseGenerator->activateLicense($licenseKey, [
            'site_url' => 'https://' . $shopDomain,
            'site_name' => sanitize_text_field((string)($data['shop_name'] ?? '')) ?: $shopDomain,
            'ip_address' => 'shopify-app',
            'platform' => 'shopify',
        ]);
        if (is_wp_error($activated)) {
            return $activated;
        }

        $tenantKey = (string)$activated['tenant_key'];
        $this->tenants->setTenantSetting($tenantKey, self::SETTING_SHOP, $shopDomain);

        return [
            'tenant_key' => $tenantKey,
            'license_key' => $licenseKey,
            'tier' => $tier,
        ];
    }

    /**
     * Tenant created by this endpoint for the shop (tenant_settings mapping).
     * Manually activated agency licenses have no mapping and are never touched.
     *
     * @return array{tenant_key: string, license_key: string, tier: string}|null
     */
    private function findTenantByShop(string $shopDomain): ?array
    {
        global $wpdb;
        $tenantsTable = $wpdb->prefix . 'sseo_ai_tenants';
        $settingsTable = $wpdb->prefix . 'sseo_ai_tenant_settings';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.tenant_key, t.license_key, t.tier
             FROM $tenantsTable t
             INNER JOIN $settingsTable s ON s.tenant_id = t.id
             WHERE s.setting_key = %s AND s.setting_value = %s
             ORDER BY t.id DESC
             LIMIT 1",
            self::SETTING_SHOP,
            $shopDomain
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * updateTenant() skips null values, so clear expires_at explicitly.
     */
    private function clearTenantExpiry(string $tenantKey): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}sseo_ai_tenants SET expires_at = NULL WHERE tenant_key = %s",
            $tenantKey
        ));
    }

    /**
     * @return true|\WP_REST_Response
     */
    private function verifySignature(\WP_REST_Request $request): bool|\WP_REST_Response
    {
        $secret = self::getSecret();
        if ($secret === '') {
            return $this->error('not_configured', 'Shopify sync is not configured.', 503);
        }

        $timestamp = (string)$request->get_header('x_fyndable_timestamp');
        $signature = strtolower((string)$request->get_header('x_fyndable_signature'));

        if ($timestamp === '' || !ctype_digit($timestamp) || $signature === '') {
            return $this->error('invalid_signature', 'Missing signature.', 401);
        }

        if (abs(time() - (int)$timestamp) > self::MAX_CLOCK_SKEW) {
            return $this->error('expired_signature', 'Signature timestamp out of range.', 401);
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $request->get_body(), $secret);
        if (!hash_equals($expected, $signature)) {
            return $this->error('invalid_signature', 'Invalid signature.', 401);
        }

        return true;
    }

    public static function getSecret(): string
    {
        if (defined('FYNDABLE_SHOPIFY_SYNC_SECRET') && is_string(FYNDABLE_SHOPIFY_SYNC_SECRET)) {
            return FYNDABLE_SHOPIFY_SYNC_SECRET;
        }

        return (string)get_option('sseo_ai_saas_shopify_sync_secret', '');
    }

    /**
     * Accept only "{handle}.myshopify.com" (lowercase).
     */
    public static function normalizeShopDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim((string)$domain, '/');

        return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $domain) ? $domain : null;
    }

    private function sanitizeDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : gmdate('Y-m-d H:i:s', $ts);
    }

    private function error(string $code, string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => $code,
            'message' => $message,
        ], $status);
    }
}

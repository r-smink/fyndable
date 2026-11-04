<?php

use App\Models\Shop;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest configuration
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application with a fresh in-memory SQLite
| database (see phpunit.xml). Existing PHPUnit test classes keep working
| alongside Pest test files.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Shared helpers
|--------------------------------------------------------------------------
*/

const TEST_API_KEY = 'test-api-key';
const TEST_API_SECRET = 'test-api-secret-0123456789abcdef0123456789abcdef';
const TEST_SHOP = 'test-shop.myshopify.com';
const TEST_SYNC_SECRET = 'sync-secret-for-tests';

/**
 * Configure the app for billing tests (no real credentials).
 */
function configureShopifyTestApp(bool $billing = true): void
{
    config([
        'shopify.api_key' => TEST_API_KEY,
        'shopify.api_secret' => TEST_API_SECRET,
        'shopify.saas_dashboard_url' => 'https://portal.test',
        'shopify.billing_enabled' => $billing,
        'shopify.billing.partner_id' => '5185442',
        'shopify.billing.partner_api_token' => 'prtapi_test',
        'shopify.billing.app_id' => '423617626113',
        'shopify.billing.app_handle' => 'fyndable-smart-seo',
        'shopify.billing.sync_secret' => TEST_SYNC_SECRET,
        'shopify.billing.trial_products' => 5,
        'shopify.billing.check_ttl' => 600,
    ]);
}

/**
 * Installed shop with a usable token (no token exchange needed).
 */
function makeTestShop(array $attributes = []): Shop
{
    return Shop::create(array_merge([
        'shop_domain' => TEST_SHOP,
        'access_token' => 'shpat_test',
        'token_expires_at' => now()->addHour(),
        'is_installed' => true,
        'is_uninstalled' => false,
        'shopify_shop_gid' => 'gid://shopify/Shop/777',
        'shop_name' => 'Test Shop',
        'shop_email' => 'owner@example.com',
    ], $attributes));
}

/**
 * App Bridge session token (HS256 with the app secret) for TEST_SHOP.
 */
function sessionToken(string $dest = TEST_SHOP): string
{
    $now = time();

    return JWT::encode([
        'iss' => "https://{$dest}/admin",
        'dest' => "https://{$dest}",
        'aud' => TEST_API_KEY,
        'sub' => '123',
        'exp' => $now + 300,
        'nbf' => $now - 10,
        'iat' => $now,
        'jti' => 'jti',
        'sid' => 'sid',
    ], TEST_API_SECRET, 'HS256');
}

/**
 * Partner API response for an active subscription (or null for none).
 */
function partnerSubscription(?string $handle, array $extra = []): array
{
    if ($handle === null) {
        return ['data' => ['activeSubscription' => null]];
    }

    return ['data' => ['activeSubscription' => array_merge([
        'billingPeriod' => 'EVERY_30_DAYS',
        'cancelAtEndOfCycle' => false,
        'trialEndsAt' => null,
        'currentBillingCycle' => ['startTime' => '2026-11-01T00:00:00Z', 'endTime' => '2026-12-01T00:00:00Z'],
        'items' => [['handle' => $handle, 'description' => ucfirst($handle)]],
    ], $extra)]];
}

/**
 * SaaS sync response used by Http::fake.
 */
function saasSyncResponse(string $tier = 'trial', string $status = 'active'): array
{
    return [
        'success' => true,
        'tenant_key' => 'tn_abc123',
        'license_key' => 'FYND-AAAA-BBBB-CCCC',
        'tier' => $tier,
        'status' => $status,
    ];
}

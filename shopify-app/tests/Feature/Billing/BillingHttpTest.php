<?php

use App\Jobs\SyncShopBillingJob;
use App\Models\Shop;
use App\Services\SaasProxyClient;
use App\Services\ShopifyBillingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function authHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

/**
 * Fake SaaS: tenant/status reports the tier as active (or suspended).
 */
function fakeSaas(string $tier, string $status = 'active', ?array $partner = null): void
{
    Http::fake([
        'partners.shopify.com/*' => Http::response($partner ?? partnerSubscription(null)),
        'portal.test/wp-json/ai-seo-saas/v1/shopify/subscription' => Http::response(saasSyncResponse($tier, $status)),
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => $status, 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/ai/*' => Http::response(['success' => true, 'text' => '{}']),
        'test-shop.myshopify.com/*' => Http::response(['data' => []]),
    ]);
}

// --- Billing endpoints ---------------------------------------------------

it('requires a session token for the billing status', function () {
    makeTestShop();

    $this->getJson('/api/billing/status')->assertStatus(401);
});

it('returns the billing status for an authenticated shop', function () {
    makeTestShop();
    fakeSaas('trial');

    $this->getJson('/api/billing/status', authHeaders())
        ->assertOk()
        ->assertJson(['enabled' => true, 'state' => 'trial', 'required' => false]);
});

it('confirms the plan from the redirect only via the Partner API', function () {
    makeTestShop(['billing_state' => 'trial', 'billing_checked_at' => now()]);
    fakeSaas('professional', 'active', partnerSubscription('professional'));

    $this->postJson('/api/billing/confirm', ['plan_handle' => 'professional'], authHeaders())
        ->assertOk()
        ->assertJson(['state' => 'active', 'plan' => 'professional', 'plan_confirmed' => true]);
});

it('does not trust a forged plan_handle without a subscription', function () {
    makeTestShop(['billing_state' => 'trial', 'billing_checked_at' => now()]);
    fakeSaas('trial', 'active', partnerSubscription(null));

    $this->postJson('/api/billing/confirm', ['plan_handle' => 'business'], authHeaders())
        ->assertOk()
        ->assertJson(['state' => 'trial', 'plan_confirmed' => false]);

    expect(Shop::first()->license_tier)->not->toBe('business');
});

it('includes billing in the dashboard overview', function () {
    makeTestShop();
    fakeSaas('trial');

    $this->getJson('/api/dashboard/overview', authHeaders())
        ->assertOk()
        ->assertJsonPath('billing.state', 'trial')
        ->assertJsonPath('billing.trial.max', 5);
});

// --- Trial product guard -------------------------------------------------

it('lets a trial shop optimize up to five products and blocks the sixth with 402', function () {
    $shop = makeTestShop([
        'billing_state' => 'trial',
        'billing_checked_at' => now(),
        'license_key' => 'FYND-AAAA-BBBB-CCCC',
        'tenant_key' => 'tn_abc123',
        'license_source' => 'app_pricing',
        'license_tier' => 'trial',
    ]);
    fakeSaas('trial');
    foreach (range(1, 5) as $id) {
        DB::table('trial_products')->insert(['shop_id' => $shop->id, 'product_id' => (string) $id]);
    }

    $this->postJson('/api/products/6/generate-meta', [], authHeaders())
        ->assertStatus(402)
        ->assertJson([
            'error' => 'trial_exhausted',
            'trial_limit' => 5,
            'pricing_url' => 'https://admin.shopify.com/store/test-shop/charges/fyndable-smart-seo/pricing_plans',
        ]);

    expect($shop->refresh()->billing_state)->toBe('none');
});

it('recognises product gids on the generic content routes', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'billing_checked_at' => now(), 'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'trial']);
    fakeSaas('trial');
    foreach (range(1, 5) as $id) {
        DB::table('trial_products')->insert(['shop_id' => $shop->id, 'product_id' => (string) $id]);
    }

    $this->postJson('/api/content/product/'.rawurlencode('gid://shopify/Product/99').'/generate-meta', [], authHeaders())
        ->assertStatus(402);
});

it('does not count collections or pages towards the trial', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'billing_checked_at' => now(), 'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'trial']);
    fakeSaas('trial');

    $this->postJson('/api/content/collection/55/generate-meta', [], authHeaders())->assertStatus(200);

    expect(DB::table('trial_products')->count())->toBe(0);
});

it('does not apply the trial guard to paying shops', function () {
    $shop = makeTestShop(['billing_state' => 'active', 'billing_checked_at' => now(), 'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'starter']);
    fakeSaas('starter');

    $this->postJson('/api/products/123/generate-meta', [], authHeaders())->assertStatus(200);
    expect(DB::table('trial_products')->count())->toBe(0);
});

// --- Uninstall webhook ---------------------------------------------------

it('queues the SaaS uninstall sync from a verified app/uninstalled webhook', function () {
    Queue::fake();
    $shop = makeTestShop(['license_key' => 'K', 'tenant_key' => 'T', 'license_source' => 'app_pricing']);
    $body = json_encode(['id' => 1, 'domain' => TEST_SHOP]);
    $hmac = base64_encode(hash_hmac('sha256', $body, TEST_API_SECRET, true));

    $this->call('POST', '/webhooks', [], [], [], [
        'HTTP_X_SHOPIFY_TOPIC' => 'app/uninstalled',
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => TEST_SHOP,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();

    expect($shop->refresh()->is_uninstalled)->toBeTrue();
    Queue::assertPushed(SyncShopBillingJob::class, fn ($job) => $job->shopId === $shop->id && $job->uninstalled);
});

it('rejects webhooks with an invalid HMAC', function () {
    Queue::fake();
    makeTestShop();

    $this->call('POST', '/webhooks', [], [], [], [
        'HTTP_X_SHOPIFY_TOPIC' => 'app/uninstalled',
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => TEST_SHOP,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode('forged'),
        'CONTENT_TYPE' => 'application/json',
    ], '{}')->assertStatus(401);

    Queue::assertNothingPushed();
});

it('runs the uninstall sync job against the SaaS', function () {
    $shop = makeTestShop(['license_key' => 'K', 'tenant_key' => 'T', 'license_source' => 'app_pricing']);
    fakeSaas('starter', 'suspended');

    (new SyncShopBillingJob($shop->id, true))->handle(app(ShopifyBillingService::class));

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/shopify/subscription') && $r['state'] === 'uninstalled');
});

// --- No manual license keys (App Store requirement 1.2.1) ----------------

it('has no manual license activation in the Shopify app', function () {
    makeTestShop();

    $this->postJson('/api/license/activate', ['license_key' => 'FYND-AGENCY'], authHeaders())->assertNotFound();
    $this->postJson('/api/license/deactivate', [], authHeaders())->assertNotFound();
});

// --- Signed SaaS sync ----------------------------------------------------

it('signs the SaaS sync with HMAC over timestamp and body', function () {
    Http::fake(['portal.test/*' => Http::response(saasSyncResponse())]);

    app(SaasProxyClient::class)->syncShopifySubscription(['shop_domain' => TEST_SHOP, 'state' => 'trial']);

    Http::assertSent(function (Request $r) {
        $ts = $r->header('X-Fyndable-Timestamp')[0];
        $expected = hash_hmac('sha256', $ts.'.'.$r->body(), TEST_SYNC_SECRET);

        return $r->url() === 'https://portal.test/wp-json/ai-seo-saas/v1/shopify/subscription'
            && hash_equals($expected, $r->header('X-Fyndable-Signature')[0])
            && abs(time() - (int) $ts) < 5;
    });
});

it('refuses to sync without a shared secret', function () {
    config(['shopify.billing.sync_secret' => '']);
    Http::fake();

    expect(app(SaasProxyClient::class)->syncShopifySubscription(['state' => 'trial']))
        ->toBe(['error' => 'sync_not_configured']);
    Http::assertNothingSent();
});

it('produces signature headers that change with the body', function () {
    $a = SaasProxyClient::signatureHeaders('{"a":1}', '1700000000', 'k');
    $b = SaasProxyClient::signatureHeaders('{"a":2}', '1700000000', 'k');

    expect($a['X-Fyndable-Signature'])->not->toBe($b['X-Fyndable-Signature'])
        ->and($a['X-Fyndable-Timestamp'])->toBe('1700000000');
});

it('renders the plan page with the billing i18n strings in the admin language', function () {
    $this->get('/dashboard/plan?shop='.TEST_SHOP.'&locale=nl-NL')
        ->assertOk()
        ->assertSee('id="billing-card"', false)
        ->assertSee('Kies een abonnement')
        ->assertDontSee('Choose a plan');

    $this->get('/dashboard/plan?shop='.TEST_SHOP.'&locale=en-US')
        ->assertSee('Choose a plan');
});

it('returns 402 trial_expired for product AI after the 7-day trial', function () {
    $shop = makeTestShop([
        'billing_state' => 'trial', 'billing_checked_at' => now(), 'license_key' => 'K', 'tenant_key' => 'T',
        'license_tier' => 'trial', 'trial_started_at' => now()->subDays(8),
    ]);
    fakeSaas('trial');

    $this->postJson('/api/products/1/generate-meta', [], authHeaders())
        ->assertStatus(402)
        ->assertJson(['error' => 'trial_expired', 'trial_days' => 7]);
});

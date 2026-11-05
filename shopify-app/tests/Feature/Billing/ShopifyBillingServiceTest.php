<?php

use App\Services\ShopifyBillingService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureShopifyTestApp();
});

function billing(): ShopifyBillingService
{
    return app(ShopifyBillingService::class);
}

function fakeBilling(array $partner, ?array $sync = null): void
{
    Http::fake([
        'partners.shopify.com/*' => Http::response($partner),
        'portal.test/wp-json/ai-seo-saas/v1/shopify/subscription' => Http::response($sync ?? saasSyncResponse()),
    ]);
}

function sentSync(): array
{
    $requests = Http::recorded(fn (Request $r) => str_contains($r->url(), '/shopify/subscription'));

    return $requests->map(fn ($pair) => json_decode($pair[0]->body(), true))->values()->all();
}

it('starts a new shop without subscription in the in-app trial and creates the tenant', function () {
    $shop = makeTestShop();
    fakeBilling(partnerSubscription(null), saasSyncResponse('trial'));

    $status = billing()->refresh($shop);

    expect($status['state'])->toBe('trial')
        ->and($status['required'])->toBeFalse()
        ->and($status['trial'])->toMatchArray(['used' => 0, 'max' => 5, 'ended' => false, 'days' => 7]);

    $shop->refresh();
    expect($shop->billing_state)->toBe('trial')
        ->and($shop->license_source)->toBe('app_pricing')
        ->and($shop->license_key)->toBe('FYND-AAAA-BBBB-CCCC')
        ->and($shop->tenant_key)->toBe('tn_abc123')
        ->and($shop->license_tier)->toBe('trial');

    expect(sentSync())->toHaveCount(1)
        ->and(sentSync()[0])->toMatchArray(['shop_domain' => TEST_SHOP, 'state' => 'trial', 'tier' => 'trial']);
});

it('queries the Partner API with app and shop gids and the access token', function () {
    $shop = makeTestShop();
    fakeBilling(partnerSubscription(null));

    billing()->refresh($shop);

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'partners.shopify.com')) {
            return false;
        }

        return $request->url() === 'https://partners.shopify.com/5185442/api/2026-10/graphql.json'
            && $request->header('X-Shopify-Access-Token')[0] === 'prtapi_test'
            && $request['variables'] === ['appId' => 'gid://shopify/App/423617626113', 'shopId' => 'gid://shopify/Shop/777'];
    });
});

it('maps an active plan handle to its tier and ends the trial', function (string $handle) {
    $shop = makeTestShop();
    fakeBilling(partnerSubscription($handle), saasSyncResponse($handle));

    $status = billing()->refresh($shop);

    expect($status['state'])->toBe('active')
        ->and($status['plan'])->toBe($handle)
        ->and($status['tier'])->toBe($handle)
        ->and($status['cycle_ends_at'])->not->toBeNull();

    expect($shop->refresh()->trial_ended_at)->not->toBeNull();
    expect(sentSync()[0])->toMatchArray(['state' => 'active', 'tier' => $handle, 'plan_handle' => $handle]);
})->with(['starter', 'professional', 'business']);

it('falls back to starter for an unknown plan handle instead of locking out a paying shop', function () {
    $shop = makeTestShop();
    fakeBilling(partnerSubscription('legacy-gold'), saasSyncResponse('starter'));

    $status = billing()->refresh($shop);

    expect($status['state'])->toBe('active')->and($shop->refresh()->license_tier)->toBe('starter');
});

it('marks a cancelled or frozen subscription as none and requires a plan', function () {
    $shop = makeTestShop([
        'billing_state' => 'active',
        'billing_plan' => 'professional',
        'license_tier' => 'professional',
        'license_key' => 'FYND-AAAA-BBBB-CCCC',
        'tenant_key' => 'tn_abc123',
        'license_source' => 'app_pricing',
        'trial_ended_at' => now()->subMonth(),
    ]);
    fakeBilling(partnerSubscription(null), saasSyncResponse('professional', 'suspended'));

    $status = billing()->refresh($shop, true);

    expect($status['state'])->toBe('none')
        ->and($status['required'])->toBeTrue()
        ->and($status['pricing_url'])->toBe('https://admin.shopify.com/store/test-shop/charges/fyndable-smart-seo/pricing_plans');
    expect(sentSync()[0])->toMatchArray(['state' => 'none', 'tier' => null]);
    // The suspended tenant's old tier must not be written back as active tier.
    expect($shop->refresh()->license_tier)->toBe('free');
});

it('reuses a fresh result without calling the Partner API again', function () {
    $shop = makeTestShop();
    fakeBilling(partnerSubscription('starter'), saasSyncResponse('starter'));

    billing()->refresh($shop);
    billing()->refresh($shop->refresh());

    expect(Http::recorded(fn (Request $r) => str_contains($r->url(), 'partners.shopify.com')))->toHaveCount(1);
});

it('does not resync the SaaS when nothing changed', function () {
    $shop = makeTestShop();
    fakeBilling(partnerSubscription('starter'), saasSyncResponse('starter'));

    billing()->refresh($shop, true);
    billing()->refresh($shop->refresh(), true);

    expect(sentSync())->toHaveCount(1);
});

it('keeps the last known state when the Partner API fails', function (int $status) {
    $shop = makeTestShop([
        'billing_state' => 'active',
        'billing_plan' => 'business',
        'license_tier' => 'business',
        'license_key' => 'FYND-AAAA-BBBB-CCCC',
        'tenant_key' => 'tn_abc123',
        'license_source' => 'app_pricing',
    ]);
    Http::fake(['partners.shopify.com/*' => Http::response(['errors' => 'x'], $status)]);

    $result = billing()->refresh($shop, true);

    expect($result['state'])->toBe('active')->and($result['plan'])->toBe('business');
    expect(sentSync())->toBeEmpty();
})->with([429, 500]);

it('reports unknown (not required) when the API fails for a shop whose trial already ended', function () {
    $shop = makeTestShop(['trial_ended_at' => now()->subDay()]);
    Http::fake(['partners.shopify.com/*' => Http::response([], 503)]);

    $result = billing()->refresh($shop, true);

    expect($result['state'])->toBe('unknown')->and($result['required'])->toBeFalse();
});

it('does nothing when billing is disabled', function () {
    configureShopifyTestApp(false);
    $shop = makeTestShop();
    Http::fake();

    expect(billing()->refresh($shop)['state'])->toBe('disabled');
    Http::assertNothingSent();
});

it('counts distinct trial products and ends the trial on the first product beyond the limit', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'license_key' => 'K', 'tenant_key' => 'T', 'license_source' => 'app_pricing']);
    fakeBilling(partnerSubscription(null), saasSyncResponse('trial', 'suspended'));

    foreach (['1', '2', '3', '4', '5'] as $id) {
        expect(billing()->claimTrialProduct($shop, $id))->toBeTrue();
    }
    // Already counted products stay usable after the counter is full.
    expect(billing()->claimTrialProduct($shop, '3'))->toBeTrue();
    expect($shop->refresh()->billing_state)->toBe('trial');

    expect(billing()->claimTrialProduct($shop, '6'))->toBeFalse();

    $shop->refresh();
    expect($shop->billing_state)->toBe('none')
        ->and($shop->trial_ended_at)->not->toBeNull()
        ->and(billing()->trialProductsUsed($shop))->toBe(5);
    expect(sentSync())->toHaveCount(1)->and(sentSync()[0]['state'])->toBe('none');
});

it('does not restart the trial after a cancelled subscription', function () {
    $shop = makeTestShop(['trial_ended_at' => now()->subMonths(2), 'billing_state' => 'active', 'license_tier' => 'starter']);
    fakeBilling(partnerSubscription(null), saasSyncResponse('starter', 'suspended'));

    expect(billing()->refresh($shop, true)['state'])->toBe('none');
});

it('sends the uninstall state to the SaaS and resets the local check', function () {
    $shop = makeTestShop(['billing_state' => 'active', 'license_key' => 'K', 'tenant_key' => 'T', 'license_source' => 'app_pricing', 'billing_checked_at' => now()]);
    fakeBilling(partnerSubscription(null), saasSyncResponse('starter', 'suspended'));

    billing()->handleUninstall($shop);

    expect(sentSync()[0]['state'])->toBe('uninstalled');
    expect($shop->refresh()->billing_state)->toBeNull()->and($shop->billing_checked_at)->toBeNull();
});

// --- 7-day trial window --------------------------------------------------

it('starts the 7-day trial window on the first trial check and sends its end to the SaaS', function () {
    $this->travelTo(now()->startOfDay());
    $shop = makeTestShop();
    fakeBilling(partnerSubscription(null), saasSyncResponse('trial'));

    $status = billing()->refresh($shop);

    expect($shop->refresh()->trial_started_at->equalTo(now()))->toBeTrue()
        ->and($status['trial']['days'])->toBe(7)
        ->and($status['trial']['ends_at'])->toBe(now()->addDays(7)->toIso8601String());
    expect(sentSync()[0]['trial_ends_at'])->toBe(now()->addDays(7)->toIso8601String());
});

it('ends the trial after 7 days even when the cached check is still fresh', function () {
    $shop = makeTestShop([
        'billing_state' => 'trial',
        'license_tier' => 'trial',
        'license_key' => 'K',
        'tenant_key' => 'T',
        'trial_started_at' => now()->subDays(7)->subMinute(),
        'billing_checked_at' => now(),
    ]);
    fakeBilling(partnerSubscription(null), saasSyncResponse('trial', 'suspended'));

    $status = billing()->refresh($shop);

    expect($status['state'])->toBe('none')->and($status['required'])->toBeTrue();
    expect($shop->refresh()->trial_ended_at)->not->toBeNull();
    expect(sentSync()[0]['state'])->toBe('none');
});

it('keeps the trial on day 6', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'trial_started_at' => now()->subDays(6), 'billing_checked_at' => now()]);
    Http::fake();

    expect(billing()->refresh($shop)['state'])->toBe('trial');
    Http::assertNothingSent();
});

it('switches an expired trial to the paid plan the merchant chose meanwhile', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'license_tier' => 'trial', 'trial_started_at' => now()->subDays(9), 'billing_checked_at' => now()]);
    fakeBilling(partnerSubscription('business'), saasSyncResponse('business'));

    expect(billing()->refresh($shop)['state'])->toBe('active');
});

it('ends an expired trial when the Partner API is unavailable', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'license_key' => 'K', 'tenant_key' => 'T', 'trial_started_at' => now()->subDays(8)]);
    Http::fake([
        'partners.shopify.com/*' => Http::response([], 503),
        'portal.test/*' => Http::response(saasSyncResponse('trial', 'suspended')),
    ]);

    expect(billing()->refresh($shop, true)['state'])->toBe('none');
});

it('refuses new trial products after the trial window', function () {
    $shop = makeTestShop(['billing_state' => 'trial', 'license_key' => 'K', 'tenant_key' => 'T', 'trial_started_at' => now()->subDays(8)]);
    fakeBilling(partnerSubscription(null), saasSyncResponse('trial', 'suspended'));

    expect(billing()->claimTrialProduct($shop, '1'))->toBeFalse()
        ->and($shop->refresh()->billing_state)->toBe('none');
});

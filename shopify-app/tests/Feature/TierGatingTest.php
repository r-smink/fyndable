<?php

use App\Jobs\CheckRankingsJob;
use App\Jobs\SyncShopBillingJob;
use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\UsageLimiter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp(false);
});

function licensedShop(string $tier, string $status = 'active'): Shop
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => $status, 'tier' => $tier]),
    ]);

    return makeTestShop(['license_key' => 'FYND-K', 'tenant_key' => 'tn_k', 'license_tier' => $tier]);
}

it('maps every App Pricing plan handle to a tier known by config/tiers.php', function () {
    $levels = config('tiers.levels');

    foreach (config('shopify.billing.plans') as $handle => $tier) {
        expect($levels)->toHaveKey($tier);
    }
    expect(array_values(config('shopify.billing.plans')))->toBe(['starter', 'professional', 'business']);
});

it('orders the three Shopify plans starter < professional < business', function () {
    $levels = config('tiers.levels');

    expect($levels['starter'])->toBeLessThan($levels['professional'])
        ->and($levels['professional'])->toBeLessThan($levels['business']);
});

it('unlocks features per plan', function (string $tier, string $feature, bool $allowed) {
    $shop = licensedShop($tier);

    expect(app(LicenseService::class)->canUse($shop, $feature))->toBe($allowed);
})->with([
    ['starter', 'content_meta', true],
    ['starter', 'geo_scan', false],
    ['professional', 'geo_scan', true],
    ['professional', 'bulk_optimizer', false],
    ['business', 'bulk_optimizer', true],
    ['trial', 'rank_tracker', true],
]);

it('treats a suspended tenant as free', function () {
    $shop = licensedShop('business', 'suspended');

    expect(app(LicenseService::class)->effectiveTier($shop))->toBe('free')
        ->and(app(LicenseService::class)->canUse($shop, 'content_meta'))->toBeFalse();
});

it('blocks a route below the required tier with 403 tier_too_low', function () {
    licensedShop('starter');

    $this->getJson('/api/rank-tracker/stats', ['Authorization' => 'Bearer '.sessionToken()])
        ->assertStatus(403)
        ->assertJson(['error' => 'tier_too_low', 'required_tier' => 'professional', 'current_tier' => 'starter']);
});

it('returns license_inactive for shops without a valid license', function () {
    makeTestShop();

    $this->getJson('/api/rank-tracker/stats', ['Authorization' => 'Bearer '.sessionToken()])
        ->assertStatus(403)
        ->assertJson(['error' => 'license_inactive']);
});

it('enforces monthly usage limits per tier', function () {
    $shop = licensedShop('professional');
    $limiter = app(UsageLimiter::class);

    expect($limiter->limit($shop, 'geo_scans'))->toBe(10);
    foreach (range(1, 10) as $i) {
        $limiter->increment($shop, 'geo_scans');
    }

    expect($limiter->guard($shop, 'geo_scans'))->toMatchArray(['error' => 'limit_reached', 'used' => 10, 'limit' => 10]);
});

it('schedules the hourly billing sync for paying App Pricing shops and expired trials', function () {
    configureShopifyTestApp(true);
    Queue::fake();
    $paying = makeTestShop(['billing_state' => 'active', 'license_source' => 'app_pricing']);
    makeTestShop(['shop_domain' => 'trial-shop.myshopify.com', 'billing_state' => 'trial', 'trial_started_at' => now()->subDays(2)]);
    $expiredTrial = makeTestShop(['shop_domain' => 'old-trial.myshopify.com', 'billing_state' => 'trial', 'trial_started_at' => now()->subDays(8)]);
    makeTestShop(['shop_domain' => 'agency.myshopify.com', 'billing_state' => 'active', 'license_source' => 'manual']);

    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'shopify-billing-sync');
    expect($event)->not->toBeNull();
    $event->run(app());

    Queue::assertPushed(SyncShopBillingJob::class, 2);
    Queue::assertPushed(SyncShopBillingJob::class, fn ($job) => $job->shopId === $expiredTrial->id);
    Queue::assertPushed(SyncShopBillingJob::class, fn ($job) => $job->shopId === $paying->id && ! $job->uninstalled);
});

it('skips the queued rank check when the plan no longer includes the rank tracker', function () {
    $shop = licensedShop('starter');
    $shop->trackedKeywords()->create(['keyword' => 'seo app', 'country' => 'us', 'language' => 'en']);

    (new CheckRankingsJob($shop->id))->handle();

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/serp/'));
});

it('only maps dashboard tabs to known feature keys', function () {
    foreach (config('tiers.tabs') as $tab => $feature) {
        expect(config('tiers.features'))->toHaveKey($feature);
    }
});

it('renders the tab map from config into the dashboard', function () {
    $this->get('/dashboard?shop='.TEST_SHOP)
        ->assertOk()
        ->assertSee('"geoscan":"geo_scan"', false);
});

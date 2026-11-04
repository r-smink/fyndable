<?php

use App\Jobs\SyncSearchConsoleJob;
use App\Models\GoogleConnection;
use App\Models\ResourceHandle;
use App\Models\Shop;
use App\Models\TrackedKeyword;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function gscHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function gscShop(string $tier = 'professional'): Shop
{
    return makeTestShop([
        'scope' => config('shopify.scopes'),
        'license_key' => 'FYND-KEY', 'tenant_key' => 'tn_1', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
}

function gscConnection(Shop $shop, array $attributes = []): GoogleConnection
{
    return GoogleConnection::create(array_merge([
        'shop_id' => $shop->id,
        'access_token' => 'ya29.access',
        'refresh_token' => '1//refresh',
        'token_expires_at' => now()->addHour(),
        'status' => 'connected',
        'site_url' => 'sc-domain:lamps.example',
    ], $attributes));
}

function gscRow(array $keys, int $clicks, int $impressions, float $position): array
{
    return ['keys' => $keys, 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $impressions ? $clicks / $impressions : 0, 'position' => $position];
}

/**
 * Fake SaaS (tenant, Google proxy) + Google Search Console API + Shopify shop details.
 */
function fakeGsc(string $tier = 'professional', array $saasGoogle = []): void
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/google/oauth-session' => Http::response($saasGoogle['session'] ?? ['success' => true, 'url' => 'https://portal.test/wp-json/ai-seo-saas/v1/google/oauth-start?session=abc']),
        'portal.test/wp-json/ai-seo-saas/v1/google/exchange' => Http::response($saasGoogle['exchange'] ?? ['success' => true, 'tokens' => ['access_token' => 'ya29.new', 'refresh_token' => '1//new-refresh', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly']]),
        'portal.test/wp-json/ai-seo-saas/v1/google/refresh' => Http::response($saasGoogle['refresh'] ?? ['success' => true, 'tokens' => ['access_token' => 'ya29.refreshed', 'expires_in' => 3599]], $saasGoogle['refresh_status'] ?? 200),
        'portal.test/wp-json/ai-seo-saas/v1/google/report-usage' => Http::response(['success' => true]),
        'www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [
            ['siteUrl' => 'https://other.example/', 'permissionLevel' => 'siteOwner'],
            ['siteUrl' => 'sc-domain:lamps.example', 'permissionLevel' => 'siteFullUser'],
            ['siteUrl' => 'https://unverified.example/', 'permissionLevel' => 'siteUnverifiedUser'],
        ]]),
        'www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => function (Request $request) {
            $dimensions = $request['dimensions'] ?? [];
            if ($dimensions === []) {
                return Http::response(['rows' => [['clicks' => 120, 'impressions' => 4000, 'ctr' => 0.03, 'position' => 14.24]]]);
            }
            if ($dimensions === ['page']) {
                return Http::response(['rows' => [
                    gscRow(['https://lamps.example/products/brass-lamp'], 80, 1500, 6.2),
                    gscRow(['https://lamps.example/nl/collections/lighting/products/brass-lamp'], 5, 200, 9.0),
                ]]);
            }

            return Http::response(['rows' => [
                gscRow(['brass lamp', 'https://lamps.example/products/brass-lamp'], 30, 900, 12.4),
                gscRow(['Brass Lamp', 'https://lamps.example/collections/lighting'], 2, 100, 18.0),
                gscRow(['lamp', 'https://lamps.example/'], 1, 5, 15.0),
                gscRow(['buy lamp', 'https://lamps.example/'], 40, 600, 3.0),
            ]]);
        },
        'test-shop.myshopify.com/admin/api/*' => Http::response(['data' => ['shop' => ['name' => 'Lamp Shop', 'primaryDomain' => ['url' => 'https://www.lamps.example']]]]),
    ]);
}

// --- Connecting ------------------------------------------------------------------

it('creates a one-time Google session through the SaaS without exposing the license key', function () {
    gscShop();
    fakeGsc();

    $response = $this->postJson('/api/gsc/session', [], gscHeaders())->assertJson(['success' => true]);

    expect($response->json('url'))->toContain('session=abc')
        ->and($response->getContent())->not->toContain('FYND-KEY');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'google/oauth-session') && $r['license_key'] === 'FYND-KEY' && $r['tenant_key'] === 'tn_1');
});

it('exchanges the code server-side, stores the tokens encrypted and suggests the store property', function () {
    $shop = gscShop();
    fakeGsc();

    $response = $this->postJson('/api/gsc/connect', ['code' => '4/0AbCd'], gscHeaders())
        ->assertJson(['success' => true, 'connected' => true, 'suggested' => 'sc-domain:lamps.example'])
        ->assertJsonCount(2, 'sites');

    expect($response->getContent())->not->toContain('ya29')->not->toContain('refresh');
    $raw = DB::table('google_connections')->where('shop_id', $shop->id)->first();
    expect($raw->access_token)->not->toContain('ya29')
        ->and($raw->refresh_token)->not->toContain('1//new-refresh')
        ->and(GoogleConnection::first()->refresh_token)->toBe('1//new-refresh');
});

it('needs a refresh token on the first connection', function () {
    gscShop();
    fakeGsc('professional', ['exchange' => ['success' => true, 'tokens' => ['access_token' => 'ya29.x', 'expires_in' => 3599]]]);

    $this->postJson('/api/gsc/connect', ['code' => 'abc'], gscHeaders())->assertJson(['error' => 'connect_failed', 'message' => 'no_refresh_token']);
    expect(GoogleConnection::count())->toBe(0);
});

it('only accepts a property of the connected account and starts the import', function () {
    Queue::fake();
    $shop = gscShop();
    gscConnection($shop, ['site_url' => null]);
    fakeGsc();

    $this->postJson('/api/gsc/site', ['site_url' => 'https://unverified.example/'], gscHeaders())->assertJson(['error' => 'invalid_site']);
    $this->postJson('/api/gsc/site', ['site_url' => 'sc-domain:lamps.example'], gscHeaders())->assertJson(['success' => true]);

    expect(GoogleConnection::first()->site_url)->toBe('sc-domain:lamps.example');
    Queue::assertPushed(SyncSearchConsoleJob::class);
});

// --- Sync + tokens ---------------------------------------------------------------------

it('imports the 28-day totals, pages and queries', function () {
    $shop = gscShop();
    gscConnection($shop);
    fakeGsc();

    SyncSearchConsoleJob::dispatchSync($shop->id);

    $connection = GoogleConnection::first();
    expect($connection->totals)->toBe(['clicks' => 120, 'impressions' => 4000, 'ctr' => 0.03, 'position' => 14.2])
        ->and($connection->synced_at)->not->toBeNull()
        ->and(DB::table('gsc_page_stats')->pluck('path')->all())->toBe(['/products/brass-lamp', '/products/brass-lamp'])
        ->and(DB::table('gsc_query_stats')->count())->toBe(4);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchAnalytics/query') && str_contains($r->url(), 'sc-domain%3Alamps.example') && $r->hasHeader('Authorization', 'Bearer ya29.access'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'google/report-usage') && $r['service'] === 'gsc');
});

it('refreshes an expired access token through the SaaS', function () {
    $shop = gscShop();
    gscConnection($shop, ['token_expires_at' => now()->subMinute()]);
    fakeGsc();

    SyncSearchConsoleJob::dispatchSync($shop->id);

    expect(GoogleConnection::first()->access_token)->toBe('ya29.refreshed');
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'google/refresh') && $r['refresh_token'] === '1//refresh');
});

it('asks to reconnect when Google revoked access', function () {
    $shop = gscShop();
    gscConnection($shop, ['token_expires_at' => now()->subMinute()]);
    fakeGsc('professional', ['refresh' => ['success' => false, 'error' => 'refresh_failed', 'details' => ['error' => 'invalid_grant']], 'refresh_status' => 400]);

    SyncSearchConsoleJob::dispatchSync($shop->id);

    expect(GoogleConnection::first())->status->toBe('needs_reconnect')->access_token->toBeNull();
    $this->getJson('/api/gsc', gscHeaders())->assertJson(['connected' => true, 'status' => 'needs_reconnect']);
});

// --- Insights ---------------------------------------------------------------------------

it('lists almost-page-1 queries with an edit link for known products', function () {
    $shop = gscShop();
    gscConnection($shop);
    fakeGsc();
    SyncSearchConsoleJob::dispatchSync($shop->id);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'product', 'resource_id' => '42', 'handle' => 'brass-lamp']);

    $rows = $this->getJson('/api/gsc/opportunities', gscHeaders())->assertOk()->json('opportunities');

    expect(array_column($rows, 'query'))->toBe(['brass lamp', 'Brass Lamp'])
        ->and($rows[0]['resource'])->toBe(['type' => 'product', 'id' => '42'])
        ->and($rows[1]['resource'])->toBeNull();
});

it('adds the Search Console position to tracked keywords', function () {
    $shop = gscShop();
    gscConnection($shop);
    fakeGsc();
    SyncSearchConsoleJob::dispatchSync($shop->id);
    TrackedKeyword::create(['shop_id' => $shop->id, 'keyword' => 'brass lamp', 'country' => 'us', 'language' => 'en']);
    TrackedKeyword::create(['shop_id' => $shop->id, 'keyword' => 'unknown', 'country' => 'us', 'language' => 'en']);

    $keywords = collect($this->getJson('/api/rank-tracker/keywords', gscHeaders())->json('keywords'))->keyBy('keyword');

    // Impression-weighted: (12.4 * 900 + 18 * 100) / 1000 = 12.96
    expect($keywords['brass lamp']['gsc'])->toEqual(['clicks' => 32, 'impressions' => 1000, 'position' => 13.0])
        ->and($keywords['unknown']['gsc'])->toBeNull();
});

it('shows Search Console numbers for the open item in the content optimizer', function () {
    $shop = gscShop();
    gscConnection($shop);
    DB::table('gsc_page_stats')->insert(['shop_id' => $shop->id, 'page' => 'https://lamps.example/products/brass-lamp', 'path' => '/products/brass-lamp', 'clicks' => 80, 'impressions' => 1500, 'ctr' => 0.053, 'position' => 6.2]);
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'test-shop.myshopify.com/*' => Http::response(['data' => ['node' => ['__typename' => 'Product', 'id' => 'gid://shopify/Product/42', 'title' => 'Brass lamp', 'handle' => 'brass-lamp',
            'onlineStoreUrl' => 'https://lamps.example/products/brass-lamp', 'descriptionHtml' => '', 'seo' => ['title' => null, 'description' => null], 'images' => ['edges' => []], 'metafields' => ['edges' => []]]]]),
    ]);

    $gsc = $this->getJson('/api/content/product/42', gscHeaders())->assertOk()->json('item.gsc');

    expect($gsc)->toMatchArray(['clicks' => 80, 'impressions' => 1500]);
});

// --- Plan, disconnect, privacy ------------------------------------------------------------

it('keeps Search Console on Professional', function () {
    gscShop('starter');
    fakeGsc('starter');

    $this->getJson('/api/gsc', gscHeaders())->assertStatus(403)->assertJsonPath('required_tier', 'professional');
});

it('removes tokens and data on disconnect, uninstall and shop/redact', function (string $how) {
    Queue::fake();
    $shop = gscShop();
    gscConnection($shop);
    fakeGsc();
    DB::table('gsc_page_stats')->insert(['shop_id' => $shop->id, 'page' => 'https://x/', 'path' => '/', 'clicks' => 1, 'impressions' => 1, 'ctr' => 1, 'position' => 1]);

    match ($how) {
        'disconnect' => $this->postJson('/api/gsc/disconnect', [], gscHeaders())->assertJson(['success' => true]),
        default => sendWebhook($how)->assertOk(),
    };

    expect(GoogleConnection::count())->toBe(0);
    if ($how !== 'app/uninstalled') {
        expect(DB::table('gsc_page_stats')->count())->toBe(0);
    }
})->with(['disconnect', 'app/uninstalled', 'shop/redact']);

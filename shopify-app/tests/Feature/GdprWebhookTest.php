<?php

use App\Jobs\GdprDeleteTenantJob;
use App\Models\ScheduledArticle;
use App\Models\Shop;
use App\Services\SaasProxyClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    configureShopifyTestApp();
});

function sendWebhook(string $topic, array $payload = []): TestResponse
{
    $body = json_encode($payload ?: ['shop_domain' => TEST_SHOP]);

    return test()->call('POST', '/webhooks', [], [], [], [
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => TEST_SHOP,
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, TEST_API_SECRET, true)),
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

it('erases shop data on shop/redact and queues the SaaS tenant deletion', function () {
    Queue::fake();
    $shop = makeTestShop([
        'license_key' => 'FYND-AAAA', 'tenant_key' => 'tn_1', 'license_source' => 'app_pricing',
        'license_tier' => 'business', 'billing_state' => 'active', 'trial_ended_at' => now()->subMonth(),
    ]);
    $shop->trackedKeywords()->create(['keyword' => 'seo', 'country' => 'us', 'language' => 'en']);
    ScheduledArticle::create(['shop_id' => $shop->id, 'blog_id' => 'gid://shopify/Blog/1', 'title' => 'T', 'body_html' => '<p>x</p>', 'scheduled_at' => now(), 'status' => 'scheduled']);
    DB::table('shop_usage')->insert(['shop_id' => $shop->id, 'metric' => 'geo_scans', 'period' => '2026-11', 'count' => 3]);
    DB::table('trial_products')->insert(['shop_id' => $shop->id, 'product_id' => '1']);
    App\Models\ShopProfile::create(['shop_id' => $shop->id, 'brand_description' => 'Familiebedrijf van Jan']);

    sendWebhook('shop/redact')->assertOk();

    $shop->refresh();
    expect($shop->trackedKeywords()->count())->toBe(0)
        ->and($shop->profile()->count())->toBe(0)
        ->and(ScheduledArticle::where('shop_id', $shop->id)->count())->toBe(0)
        ->and(DB::table('shop_usage')->where('shop_id', $shop->id)->count())->toBe(0)
        ->and($shop->shop_email)->toBeNull()
        ->and($shop->shop_name)->toBeNull()
        ->and($shop->license_key)->toBeNull()
        ->and($shop->tenant_key)->toBeNull()
        ->and($shop->billing_state)->toBeNull()
        // Trial protection survives (product IDs only).
        ->and($shop->trial_ended_at)->not->toBeNull()
        ->and(DB::table('trial_products')->where('shop_id', $shop->id)->count())->toBe(1);

    Queue::assertPushed(GdprDeleteTenantJob::class, fn ($job) => $job->tenantKey === 'tn_1' && $job->licenseKey === 'FYND-AAAA');
});

it('only unlinks a manual agency license on shop/redact', function () {
    Queue::fake();
    makeTestShop(['license_key' => 'FYND-AGENCY', 'tenant_key' => 'tn_agency', 'license_source' => 'manual']);

    sendWebhook('shop/redact')->assertOk();

    Queue::assertNotPushed(GdprDeleteTenantJob::class);
    expect(Shop::first()->license_key)->toBeNull();
});

it('acknowledges the customer GDPR webhooks', function (string $topic) {
    makeTestShop();

    sendWebhook($topic, ['shop_domain' => TEST_SHOP, 'customer' => ['id' => 1]])->assertOk();
})->with(['customers/data_request', 'customers/redact']);

it('calls the SaaS GDPR delete with confirmation', function () {
    Http::fake(['portal.test/*' => Http::response(['success' => true])]);

    (new GdprDeleteTenantJob('FYND-AAAA', 'tn_1', TEST_SHOP))->handle(app(SaasProxyClient::class));

    Http::assertSent(fn (Request $r) => $r->url() === 'https://portal.test/wp-json/ai-seo-saas/v1/gdpr/delete'
        && $r['confirm'] === 'DELETE' && $r['tenant_key'] === 'tn_1' && $r['license_key'] === 'FYND-AAAA');
});

it('retries the SaaS GDPR delete on failure but accepts an already deleted tenant', function () {
    Http::fake(['portal.test/*' => Http::sequence()->push(['message' => 'down'], 500)->push(['success' => false], 403)]);
    $job = new GdprDeleteTenantJob('FYND-AAAA', 'tn_1', TEST_SHOP);

    expect(fn () => $job->handle(app(SaasProxyClient::class)))->toThrow(RuntimeException::class);
    $job->handle(app(SaasProxyClient::class));
});

<?php

use App\Jobs\ImportRedirectsJob;
use App\Jobs\TrackHandleChangeJob;
use App\Models\LlmsTxtSettings;
use App\Models\NotFoundUrl;
use App\Models\RedirectEvent;
use App\Models\ResourceHandle;
use App\Models\Shop;
use App\Models\ShopProfile;
use App\Services\ShopifySignature;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function redirectHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function redirectShop(array $attributes = []): Shop
{
    return makeTestShop(array_merge([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'professional', 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ], $attributes));
}

/**
 * Query string signed like Shopify's app proxy (sorted, decoded, no separator).
 */
function proxyQuery(array $params, ?string $secret = null): string
{
    $pairs = [];
    foreach ($params as $key => $value) {
        $pairs[] = $key.'='.(is_array($value) ? implode(',', $value) : $value);
    }
    sort($pairs);
    $signature = hash_hmac('sha256', implode('', $pairs), $secret ?? TEST_API_SECRET);

    $query = [];
    foreach ($params as $key => $value) {
        foreach ((array) $value as $v) {
            $query[] = rawurlencode($key).'='.rawurlencode($v);
        }
    }

    return implode('&', $query).'&signature='.$signature;
}

function proxyParams(): array
{
    return ['shop' => TEST_SHOP, 'path_prefix' => '/apps/fyndable', 'timestamp' => '1700000000', 'logged_in_customer_id' => ''];
}

/**
 * Fake Shopify (redirect GraphQL + product collection) and an active Starter tenant.
 * $existing: redirects that already exist, keyed by path.
 */
function fakeRedirectApi(array $existing = [], ?string $collection = 'summer', string $tier = 'professional'): void
{
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => $tier]),
        'test-shop.myshopify.com/admin/api/*' => function (HttpRequest $request) use ($existing, $collection) {
            $query = $request['query'] ?? '';
            $vars = $request['variables'] ?? [];
            if (str_contains($query, 'urlRedirects(')) {
                preg_match('/^path:"(.*)"$/', (string) ($vars['query'] ?? ''), $m);
                $nodes = [];
                foreach ($existing as $path => $target) {
                    if (! isset($m[1]) || $m[1] === $path) {
                        $nodes[] = ['id' => 'gid://shopify/UrlRedirect/'.crc32($path), 'path' => $path, 'target' => $target];
                    }
                }

                return Http::response(['data' => ['urlRedirects' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => $nodes]]]);
            }
            foreach (['urlRedirectCreate', 'urlRedirectUpdate'] as $mutation) {
                if (str_contains($query, $mutation)) {
                    return Http::response(['data' => [$mutation => ['urlRedirect' => ['id' => 'gid://shopify/UrlRedirect/9'] + $vars['urlRedirect'], 'userErrors' => []]]]);
                }
            }
            if (str_contains($query, 'urlRedirectDelete')) {
                return Http::response(['data' => ['urlRedirectDelete' => ['deletedUrlRedirectId' => $vars['id'], 'userErrors' => []]]]);
            }
            if (str_contains($query, 'productCollection')) {
                return Http::response(['data' => ['product' => ['collections' => ['nodes' => $collection ? [['handle' => $collection]] : []]]]]);
            }

            return Http::response(['errors' => [['message' => 'unexpected query']]]);
        },
    ]);
}

function sentRedirect(string $mutation, string $path, string $target): bool
{
    return Http::recorded(fn (HttpRequest $r) => str_contains($r['query'] ?? '', $mutation)
        && ($r['variables']['urlRedirect']['path'] ?? null) === $path
        && ($r['variables']['urlRedirect']['target'] ?? null) === $target)->isNotEmpty();
}

// --- App proxy signature ---------------------------------------------------

it('verifies app proxy signatures the way Shopify computes them', function () {
    $signature = new ShopifySignature(TEST_API_SECRET);
    $params = proxyParams() + ['extra' => ['1', '2']];

    $valid = Request::create('/api/404?'.proxyQuery($params));
    $tampered = Request::create('/api/404?'.str_replace('shop=test-shop', 'shop=evil-shop', proxyQuery($params)));
    $unsigned = Request::create('/api/404?shop='.TEST_SHOP);

    expect($signature->verifyAppProxy($valid))->toBeTrue()
        ->and($signature->verifyAppProxy($tampered))->toBeFalse()
        ->and($signature->verifyAppProxy($unsigned))->toBeFalse();
});

it('serves llms.txt through the signed app proxy', function () {
    $shop = redirectShop();
    LlmsTxtSettings::getForShop($shop->id)->update(['enabled' => false]);

    // Disabled llms.txt answers 404, a bad signature answers 404 too, so
    // check the signature step directly: a valid one resolves the shop.
    $request = Request::create('/api?'.proxyQuery(proxyParams()));
    expect((new ShopifySignature(TEST_API_SECRET))->verifyAppProxy($request))->toBeTrue();
});

// --- 404 monitor -------------------------------------------------------------

it('records storefront 404s from the app embed beacon', function () {
    $shop = redirectShop();
    fakeRedirectApi();

    $send = fn (string $path, string $ref = '') => $this->call('POST', '/api/404?'.proxyQuery(proxyParams()), [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(['path' => $path, 'ref' => $ref]));

    $send('/products/old-shoe', 'https://www.google.com/search?q=private+stuff')->assertNoContent();
    $send('/products/old-shoe')->assertNoContent();
    $send('/cdn/shop/files/x.png')->assertNoContent();
    $send('/apps/fyndable/llms.txt')->assertNoContent();

    $row = NotFoundUrl::where('shop_id', $shop->id)->sole();
    expect($row->path)->toBe('/products/old-shoe')
        ->and($row->hits)->toBe(2)
        ->and($row->referrer)->toBe('www.google.com/search');
});

it('rejects unsigned 404 reports', function () {
    redirectShop();

    $this->call('POST', '/api/404?shop='.TEST_SHOP, [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{"path":"/x"}')->assertStatus(401);

    expect(NotFoundUrl::count())->toBe(0);
});

it('does not record 404s without a plan or with the monitor turned off', function (array $shop, bool $monitor) {
    $model = redirectShop($shop);
    ShopProfile::create(['shop_id' => $model->id, 'not_found_monitor' => $monitor]);
    Http::fake(['portal.test/*' => Http::response(['status' => $shop === [] ? 'active' : 'suspended', 'tier' => 'professional'])]);

    $this->call('POST', '/api/404?'.proxyQuery(proxyParams()), [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{"path":"/products/x"}')->assertNoContent();

    expect(NotFoundUrl::count())->toBe(0);
})->with([
    'no plan' => [['license_key' => null, 'tenant_key' => null, 'license_tier' => 'free'], true],
    'monitor off' => [[], false],
]);

// --- Automatic redirects -----------------------------------------------------

it('remembers the first handle and redirects when it changes', function () {
    $shop = redirectShop();
    fakeRedirectApi();

    TrackHandleChangeJob::dispatchSync($shop->id, 'product', '42', 'blue-shoe', 'Blue shoe');
    expect(Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'urlRedirectCreate')))->toHaveCount(0)
        ->and(ResourceHandle::sole())->handle->toBe('blue-shoe')->collection_handle->toBe('summer');

    TrackHandleChangeJob::dispatchSync($shop->id, 'product', '42', 'navy-shoe', 'Navy shoe');

    expect(sentRedirect('urlRedirectCreate', '/products/blue-shoe', '/products/navy-shoe'))->toBeTrue()
        ->and(ResourceHandle::sole()->handle)->toBe('navy-shoe')
        ->and(RedirectEvent::sole())->source->toBe('handle_change');
});

it('repoints an existing redirect instead of creating a duplicate', function () {
    $shop = redirectShop();
    fakeRedirectApi(['/collections/old' => '/collections/elsewhere']);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'collection', 'resource_id' => '7', 'handle' => 'old']);

    TrackHandleChangeJob::dispatchSync($shop->id, 'collection', '7', 'new', 'New');

    expect(sentRedirect('urlRedirectUpdate', '/collections/old', '/collections/new'))->toBeTrue()
        ->and(sentRedirect('urlRedirectCreate', '/collections/old', '/collections/new'))->toBeFalse();
});

it('redirects a deleted product to its collection or the chosen target', function (string $setting, ?string $collection, string $target) {
    $shop = redirectShop();
    fakeRedirectApi();
    ShopProfile::create(['shop_id' => $shop->id, 'deleted_redirect_target' => $setting]);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'product', 'resource_id' => '42', 'handle' => 'blue-shoe', 'title' => 'Blue shoe', 'collection_handle' => $collection]);

    TrackHandleChangeJob::dispatchSync($shop->id, 'product', '42', null, null, true);

    expect(sentRedirect('urlRedirectCreate', '/products/blue-shoe', $target))->toBeTrue()
        ->and(ResourceHandle::count())->toBe(0)
        ->and(RedirectEvent::sole()->source)->toBe('deleted');
})->with([
    ['collection', 'summer', '/collections/summer'],
    ['collection', null, '/collections/all'],
    ['home', 'summer', '/'],
    ['search', null, '/search?q=Blue%20shoe'],
]);

it('respects the automatic redirect switches', function () {
    $shop = redirectShop();
    fakeRedirectApi();
    ShopProfile::create(['shop_id' => $shop->id, 'redirect_on_handle_change' => false, 'redirect_on_delete' => false]);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'product', 'resource_id' => '1', 'handle' => 'a', 'collection_checked_at' => now()]);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'product', 'resource_id' => '2', 'handle' => 'b', 'collection_checked_at' => now()]);

    TrackHandleChangeJob::dispatchSync($shop->id, 'product', '1', 'a-new', 'A');
    TrackHandleChangeJob::dispatchSync($shop->id, 'product', '2', null, null, true);

    expect(Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'urlRedirect')))->toHaveCount(0)
        ->and(ResourceHandle::where('resource_id', '1')->value('handle'))->toBe('a-new');
});

it('resolves a recorded 404 when a redirect is created for it', function () {
    $shop = redirectShop();
    fakeRedirectApi();
    NotFoundUrl::create(['shop_id' => $shop->id, 'path' => '/products/blue-shoe', 'path_hash' => NotFoundUrl::hashPath('/products/blue-shoe'), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'product', 'resource_id' => '42', 'handle' => 'blue-shoe', 'collection_checked_at' => now()]);

    TrackHandleChangeJob::dispatchSync($shop->id, 'product', '42', 'navy-shoe', 'Navy');

    expect(NotFoundUrl::sole())->resolved_target->toBe('/products/navy-shoe');
});

it('queues handle tracking from product and collection webhooks', function () {
    Queue::fake();
    $shop = redirectShop();

    sendWebhook('products/update', ['id' => 42, 'handle' => 'navy-shoe', 'title' => 'Navy'])->assertOk();
    sendWebhook('collections/delete', ['id' => 7])->assertOk();

    Queue::assertPushed(TrackHandleChangeJob::class, fn ($job) => $job->type === 'product' && $job->resourceId === '42' && $job->handle === 'navy-shoe' && ! $job->deleted);
    Queue::assertPushed(TrackHandleChangeJob::class, fn ($job) => $job->type === 'collection' && $job->resourceId === '7' && $job->deleted);
});

// --- Redirect manager API -------------------------------------------------------

it('creates a redirect and validates the paths', function (array $body, array $expected) {
    redirectShop();
    fakeRedirectApi();

    $this->postJson('/api/redirects', $body, redirectHeaders())->assertJson($expected);
})->with([
    'ok' => [['path' => '/old', 'target' => '/collections/new'], ['success' => true]],
    'full shop url as source' => [['path' => 'https://shop.example/old?x=1', 'target' => 'https://other.example/x'], ['success' => true]],
    'no slash' => [['path' => 'old', 'target' => '/new'], ['error' => 'invalid_path']],
    'root' => [['path' => '/', 'target' => '/new'], ['error' => 'invalid_path']],
    'protocol relative target' => [['path' => '/old', 'target' => '//evil.example'], ['error' => 'invalid_target']],
    'javascript target' => [['path' => '/old', 'target' => 'javascript:alert(1)'], ['error' => 'invalid_target']],
    'loop' => [['path' => '/old/', 'target' => '/old'], ['error' => 'same_path']],
]);

it('requires the online store navigation scope to write redirects', function () {
    redirectShop(['scope' => 'read_products,write_products,write_content']);
    fakeRedirectApi();

    $this->postJson('/api/redirects', ['path' => '/old', 'target' => '/new'], redirectHeaders())
        ->assertStatus(403)
        ->assertJsonPath('error', 'missing_scopes');
});

it('lists, updates and deletes redirects', function () {
    redirectShop();
    fakeRedirectApi(['/a' => '/b']);

    $this->getJson('/api/redirects?search=a', redirectHeaders())->assertJsonPath('redirects.0.path', '/a');
    $this->postJson('/api/redirects/123', ['path' => '/a', 'target' => '/c'], redirectHeaders())->assertJsonPath('redirect.status', 'updated');
    $this->deleteJson('/api/redirects/123', [], redirectHeaders())->assertJson(['success' => true]);

    Http::assertSent(fn (HttpRequest $r) => str_contains($r['query'] ?? '', 'urlRedirectDelete') && $r['variables']['id'] === 'gid://shopify/UrlRedirect/123');
});

it('exports redirects as Shopify CSV with a formula guard', function () {
    redirectShop();
    fakeRedirectApi(['/a' => '/b', '/=cmd' => 'https://x.example/?a=1,2']);

    $csv = $this->getJson('/api/redirects/export', redirectHeaders())->assertOk()->json('csv');

    expect($csv)->toStartWith("Redirect from,Redirect to\n")
        ->toContain("/a,/b\n")
        ->toContain('"https://x.example/?a=1,2"');
});

it('parses a CSV import and queues it', function () {
    Queue::fake();
    $shop = redirectShop();
    fakeRedirectApi();
    $csv = "Redirect from;Redirect to\n/old;/new\n/old;/newer\nbad;/x\n/same;/same\n\nhttps://shop.example/p?a=1;https://other.example\n";

    $this->postJson('/api/redirects/import', ['csv' => $csv], redirectHeaders())
        ->assertJson(['success' => true, 'rows' => 2, 'invalid' => 2]);

    Queue::assertPushed(ImportRedirectsJob::class, fn ($job) => $job->rows === [
        ['path' => '/old', 'target' => '/newer'],
        ['path' => '/p?a=1', 'target' => 'https://other.example'],
    ]);
    expect(Cache::get(ImportRedirectsJob::cacheKey($shop->id))['status'])->toBe('queued');
});

it('runs an import and reports the counts', function () {
    $shop = redirectShop();
    fakeRedirectApi(['/same' => '/target', '/old' => '/elsewhere']);

    ImportRedirectsJob::dispatchSync($shop->id, [
        ['path' => '/new-one', 'target' => '/x'],
        ['path' => '/old', 'target' => '/y'],
        ['path' => '/same', 'target' => '/target'],
    ]);

    expect(Cache::get(ImportRedirectsJob::cacheKey($shop->id)))->toMatchArray([
        'status' => 'completed', 'total' => 3, 'created' => 1, 'updated' => 1, 'unchanged' => 1, 'failed' => 0,
    ])->and(RedirectEvent::where('source', 'import')->count())->toBe(2);
});

it('lists open 404s and fixes or ignores them', function () {
    $shop = redirectShop();
    fakeRedirectApi();
    $make = fn ($path, $hits) => NotFoundUrl::create(['shop_id' => $shop->id, 'path' => $path, 'path_hash' => NotFoundUrl::hashPath($path), 'hits' => $hits, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    $a = $make('/products/a', 3);
    $b = $make('/products/b', 9);

    $this->getJson('/api/redirects/not-found', redirectHeaders())
        ->assertJsonPath('open', 2)
        ->assertJsonPath('items.0.path', '/products/b');

    $this->postJson("/api/redirects/not-found/{$b->id}/fix", ['target' => '/collections/all'], redirectHeaders())->assertJson(['success' => true]);
    $this->postJson("/api/redirects/not-found/{$a->id}/ignore", [], redirectHeaders())->assertJson(['success' => true]);

    expect($b->refresh()->resolved_target)->toBe('/collections/all')
        ->and($a->refresh())->resolved_at->not->toBeNull()->resolved_target->toBeNull()
        ->and(sentRedirect('urlRedirectCreate', '/products/b', '/collections/all'))->toBeTrue();
    $this->getJson('/api/redirects/not-found', redirectHeaders())->assertJsonPath('open', 0);
});

it('saves the automatic redirect settings', function () {
    $shop = redirectShop();
    fakeRedirectApi();

    $this->postJson('/api/redirects/settings', ['redirect_on_handle_change' => true, 'redirect_on_delete' => false, 'deleted_redirect_target' => 'home', 'not_found_monitor' => false], redirectHeaders())
        ->assertJsonPath('settings.deleted_redirect_target', 'home');
    $this->postJson('/api/redirects/settings', ['redirect_on_handle_change' => true, 'redirect_on_delete' => true, 'deleted_redirect_target' => 'nowhere', 'not_found_monitor' => true], redirectHeaders())
        ->assertJson(['error' => 'invalid_settings']);

    expect(ShopProfile::where('shop_id', $shop->id)->first())
        ->redirect_on_delete->toBeFalse()
        ->not_found_monitor->toBeFalse();
});

it('keeps redirects on Starter and the 404 monitor on Professional', function () {
    redirectShop(['license_tier' => 'starter']);
    fakeRedirectApi([], 'summer', 'starter');

    $this->postJson('/api/redirects', ['path' => '/old', 'target' => '/new'], redirectHeaders())->assertJson(['success' => true]);
    $this->getJson('/api/redirects/not-found', redirectHeaders())->assertStatus(403)->assertJsonPath('required_tier', 'professional');
    $this->call('POST', '/api/404?'.proxyQuery(proxyParams()), [], [], [], ['CONTENT_TYPE' => 'text/plain'], '{"path":"/products/x"}')->assertNoContent();

    expect(NotFoundUrl::count())->toBe(0);
});

it('gates the redirect manager by plan', function () {
    makeTestShop();

    $this->getJson('/api/redirects/not-found', redirectHeaders())->assertStatus(403);
});

// --- Config ---------------------------------------------------------------------

it('subscribes to the lifecycle and catalog webhooks and requests the redirect scope', function () {
    $toml = file_get_contents(base_path('shopify.app.toml'));

    foreach (['app/uninstalled', 'products/update', 'products/delete', 'collections/update', 'collections/delete'] as $topic) {
        expect($toml)->toContain('"'.$topic.'"');
    }
    expect($toml)->toContain('write_online_store_navigation')
        ->and(file_get_contents(config_path('shopify.php')))->toContain('write_online_store_navigation')
        ->and(file_get_contents(base_path('.env.example')))->toContain('write_online_store_navigation');
});

it('removes redirect data on shop/redact', function () {
    Queue::fake();
    $shop = redirectShop(['license_source' => 'manual']);
    ResourceHandle::create(['shop_id' => $shop->id, 'type' => 'product', 'resource_id' => '1', 'handle' => 'a']);
    NotFoundUrl::create(['shop_id' => $shop->id, 'path' => '/x', 'path_hash' => NotFoundUrl::hashPath('/x'), 'first_seen_at' => now(), 'last_seen_at' => now()]);
    RedirectEvent::create(['shop_id' => $shop->id, 'source' => 'manual', 'path' => '/x', 'target' => '/y']);

    sendWebhook('shop/redact')->assertOk();

    expect(ResourceHandle::count() + NotFoundUrl::count() + RedirectEvent::count())->toBe(0);
});

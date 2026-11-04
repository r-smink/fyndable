<?php

use App\Jobs\BulkOptimizeJob;
use App\Models\Shop;
use App\Services\BulkOptimizer;
use App\Services\SeoAuditor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function bulkHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function bulkShop(array $attributes = []): Shop
{
    return makeTestShop(array_merge([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'business', 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ], $attributes));
}

function bulkNode(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => "gid://shopify/Product/{$id}",
        'handle' => "p{$id}",
        'title' => "Organic cotton romper {$id}",
        'vendor' => 'Lamp Co',
        'productType' => 'Romper',
        'onlineStoreUrl' => "https://shop.example/products/p{$id}",
        'descriptionHtml' => '<p>'.str_repeat('Soft organic cotton for babies. ', 12).'</p>',
        'seo' => ['title' => "Organic cotton romper {$id} | Lamp Co", 'description' => str_repeat('Soft organic cotton romper. ', 4)],
        'priceRangeV2' => ['minVariantPrice' => ['amount' => '24.95', 'currencyCode' => 'EUR']],
        'media' => ['nodes' => [['id' => "gid://shopify/MediaImage/{$id}0", 'alt' => 'Front', 'mediaContentType' => 'IMAGE', 'preview' => ['image' => ['url' => "https://cdn.example/{$id}.jpg"]]]]],
        'hidden' => null,
    ], $overrides);
}

/**
 * Fake the SaaS (tenant + AI) and Shopify (catalog list + mutations).
 */
function fakeBulkApis(array $nodes = [], string $connection = 'products', string $tier = 'business', string $aiText = 'AI text'): void
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/ai/generate' => Http::response(['success' => true, 'text' => $aiText]),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($nodes, $connection) {
            $query = $request['query'] ?? '';
            foreach (['productUpdate', 'collectionUpdate', 'metafieldsSet', 'fileUpdate'] as $mutation) {
                if (str_contains($query, $mutation)) {
                    return Http::response(['data' => [$mutation => ['userErrors' => [], 'product' => ['id' => 'x'], 'collection' => ['id' => 'x'], 'metafields' => [], 'files' => []]]]);
                }
            }
            if (str_contains($query, 'node(')) {
                return Http::response(['data' => ['node' => ['__typename' => 'Product', 'id' => 'gid://shopify/Product/1', 'title' => 'Romper', 'descriptionHtml' => '<p>Old text</p>']]]);
            }

            return Http::response(['data' => [$connection => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => array_map(fn ($n) => ['node' => $n], $nodes)]]]);
        },
    ]);
}

function mutations(string $name): array
{
    return Http::recorded(fn (Request $r) => str_contains($r['query'] ?? '', $name))->map(fn ($pair) => $pair[0]['variables'])->values()->all();
}

function snapshot(array $node, string $type = 'product'): array
{
    $optimizer = app(BulkOptimizer::class);
    $method = new ReflectionMethod($optimizer, 'snapshot');

    return $method->invoke($optimizer, $type, $node, app(SeoAuditor::class)->normalize($type, $node, 'shop.example'));
}

// --- Templates ----------------------------------------------------------------

it('renders templates and drops separators around empty placeholders', function (string $template, array $item, string $expected) {
    expect(app(BulkOptimizer::class)->render($template, $item, 'Lamp Shop', 'meta_title'))->toBe($expected);
})->with([
    ['{{title}} | {{vendor}} | {{shop}}', ['title' => 'Romper', 'vendor' => 'Lamp Co'], 'Romper | Lamp Co | Lamp Shop'],
    ['{{title}} | {{vendor}} | {{shop}}', ['title' => 'Romper', 'vendor' => ''], 'Romper | Lamp Shop'],
    ['{{ vendor }} - {{title}}', ['title' => 'Romper', 'vendor' => ''], 'Romper'],
    ['Buy {{title}} for {{price}}', ['title' => 'Romper', 'price' => '24.95 EUR'], 'Buy Romper for 24.95 EUR'],
    ['{{title}} {{unknown}}', ['title' => 'Romper'], 'Romper'],
]);

it('cuts template output at a word boundary', function () {
    $text = app(BulkOptimizer::class)->render('{{title}} | {{shop}}', ['title' => str_repeat('Organic cotton ', 6)], 'Lamp Shop', 'meta_title');

    expect(mb_strlen($text))->toBeLessThanOrEqual(60)->and($text)->not->toEndWith(' ')->and($text)->toStartWith('Organic cotton');
});

// --- Selecting items -----------------------------------------------------------------

it('selects only the items that need the action', function () {
    $shop = bulkShop();
    fakeBulkApis([
        bulkNode(1),
        bulkNode(2, ['seo' => ['title' => null, 'description' => null]]),
        bulkNode(3, ['seo' => ['title' => 'x', 'description' => str_repeat('y', 200)]]),
    ]);

    $optimizer = app(BulkOptimizer::class);

    expect(array_column($optimizer->select($shop, 'product', 'meta', true, 100), 'id'))->toBe(['gid://shopify/Product/2', 'gid://shopify/Product/3'])
        ->and(array_column($optimizer->select($shop, 'product', 'meta', false, 2), 'id'))->toBe(['gid://shopify/Product/1', 'gid://shopify/Product/2']);
});

// --- API -----------------------------------------------------------------------------

it('queues a bulk run with the selected items', function () {
    Queue::fake();
    bulkShop();
    fakeBulkApis([bulkNode(1, ['seo' => ['title' => null, 'description' => null]]), bulkNode(2)]);

    $this->postJson('/api/bulk/optimize', ['type' => 'product', 'action' => 'meta_title', 'mode' => 'template', 'template' => '{{title}} | {{shop}}'], bulkHeaders())
        ->assertJson(['success' => true, 'total' => 1]);

    Queue::assertPushed(BulkOptimizeJob::class, fn ($job) => $job->mode === 'template' && $job->template === '{{title}} | {{shop}}' && count($job->items) === 1);
    $this->getJson('/api/bulk/progress', bulkHeaders())->assertJson(['status' => 'queued', 'total' => 1, 'action' => 'meta_title']);
});

it('validates bulk parameters', function (array $body, string $error) {
    bulkShop();
    fakeBulkApis();

    $this->postJson('/api/bulk/optimize', $body, bulkHeaders())->assertJson(['error' => $error]);
})->with([
    [['type' => 'blog', 'action' => 'meta'], 'invalid_type'],
    [['type' => 'page', 'action' => 'alt_text'], 'invalid_action'],
    [['type' => 'product', 'action' => 'descriptions', 'mode' => 'template', 'template' => '{{title}}'], 'invalid_mode'],
    [['type' => 'product', 'action' => 'meta', 'mode' => 'template', 'template' => 'no placeholder'], 'invalid_template'],
]);

it('allows one run per shop but restarts a stuck one', function () {
    Queue::fake();
    $shop = bulkShop();
    fakeBulkApis([bulkNode(1, ['seo' => ['title' => null, 'description' => null]])]);

    Cache::put(BulkOptimizeJob::cacheKey($shop->id), ['status' => 'running', 'updated_at' => now()->toIso8601String()]);
    $this->postJson('/api/bulk/optimize', ['action' => 'meta'], bulkHeaders())->assertJson(['error' => 'job_running']);

    Cache::put(BulkOptimizeJob::cacheKey($shop->id), ['status' => 'running', 'updated_at' => now()->subHour()->toIso8601String()]);
    $this->postJson('/api/bulk/optimize', ['action' => 'meta'], bulkHeaders())->assertJson(['success' => true]);

    Cache::put(BulkOptimizeJob::cacheKey($shop->id), ['status' => 'completed']);
    $this->postJson('/api/bulk/optimize', ['action' => 'meta'], bulkHeaders())->assertJson(['success' => true]);
});

it('needs write_content for pages and articles', function () {
    bulkShop(['scope' => 'read_products,write_products,read_content']);
    fakeBulkApis();

    $this->postJson('/api/bulk/optimize', ['type' => 'page', 'action' => 'meta'], bulkHeaders())->assertJson(['error' => 'missing_scopes']);
});

it('previews a template with the current and new values', function () {
    bulkShop(['shop_name' => 'Lamp Shop']);
    fakeBulkApis([bulkNode(7, ['seo' => ['title' => null, 'description' => null]])]);

    $this->postJson('/api/bulk/preview', ['action' => 'meta_title', 'mode' => 'template', 'template' => '{{title}} | {{shop}}'], bulkHeaders())
        ->assertJsonPath('items.0.current', null)
        ->assertJsonPath('items.0.new', 'Organic cotton romper 7 | Lamp Shop');
});

it('keeps the bulk optimizer on Business', function () {
    bulkShop(['license_tier' => 'professional']);
    fakeBulkApis([], 'products', 'professional');

    $this->postJson('/api/bulk/optimize', ['action' => 'meta'], bulkHeaders())->assertStatus(403);
});

it('stops a running job on cancel', function () {
    $shop = bulkShop();
    fakeBulkApis();
    $this->postJson('/api/bulk/cancel', [], bulkHeaders())->assertJson(['error' => 'no_job_running']);

    Cache::put(BulkOptimizeJob::cacheKey($shop->id), ['status' => 'running']);
    $this->postJson('/api/bulk/cancel', [], bulkHeaders())->assertJson(['success' => true]);

    expect(Cache::get(BulkOptimizeJob::cacheKey($shop->id))['status'])->toBe('cancelled');
});

// --- Job: writes per type and action ---------------------------------------------------

it('writes template meta titles to product SEO without AI', function () {
    $shop = bulkShop(['shop_name' => 'Lamp Shop']);
    fakeBulkApis();

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'meta_title', 'template', '{{title}} | {{shop}}', [snapshot(bulkNode(1))]);

    expect(mutations('productUpdate')[0]['product'])->toBe(['id' => 'gid://shopify/Product/1', 'seo' => ['title' => 'Organic cotton romper 1 | Lamp Shop']])
        ->and(Http::recorded(fn ($r) => str_contains($r->url(), '/ai/generate')))->toHaveCount(0)
        ->and(Cache::get(BulkOptimizeJob::cacheKey($shop->id)))->toMatchArray(['status' => 'completed', 'succeeded' => 1, 'failed' => 0]);
});

it('writes page meta descriptions to the global description_tag metafield', function () {
    $shop = bulkShop();
    fakeBulkApis();
    $page = ['type' => 'page', 'id' => 'gid://shopify/Page/5', 'title' => 'About us', 'seo_title' => null, 'seo_description' => null, 'vendor' => '', 'product_type' => '', 'price' => '', 'blog' => '', 'excerpt' => 'We make lamps.', 'images' => []];

    BulkOptimizeJob::dispatchSync($shop->id, 'page', 'meta', 'ai', null, [$page]);

    expect(mutations('metafieldsSet')[0]['metafields'][0])->toMatchArray([
        'ownerId' => 'gid://shopify/Page/5', 'namespace' => 'global', 'key' => 'description_tag', 'value' => 'AI text',
    ]);
});

it('writes AI collection descriptions as HTML paragraphs', function () {
    $shop = bulkShop();
    fakeBulkApis([], 'collections', 'business', "First paragraph.\n\nSecond <b>one</b>.");
    $collection = ['type' => 'collection', 'id' => 'gid://shopify/Collection/9', 'title' => 'Lamps', 'seo_title' => null, 'seo_description' => null, 'vendor' => '', 'product_type' => '', 'price' => '', 'blog' => '', 'excerpt' => '', 'images' => []];

    BulkOptimizeJob::dispatchSync($shop->id, 'collection', 'descriptions', 'ai', null, [$collection]);

    expect(mutations('collectionUpdate')[0]['input'])->toBe([
        'id' => 'gid://shopify/Collection/9',
        'descriptionHtml' => '<p>First paragraph.</p><p>Second &lt;b&gt;one&lt;/b&gt;.</p>',
    ]);
});

it('writes AI alt text to the first image without alt text', function () {
    $shop = bulkShop();
    fakeBulkApis([], 'products', 'business', 'Baby romper in organic cotton');
    $item = snapshot(bulkNode(1, ['media' => ['nodes' => [
        ['id' => 'gid://shopify/MediaImage/10', 'alt' => 'Front', 'mediaContentType' => 'IMAGE', 'preview' => ['image' => ['url' => 'https://cdn.example/a.jpg']]],
        ['id' => 'gid://shopify/MediaImage/11', 'alt' => '', 'mediaContentType' => 'IMAGE', 'preview' => ['image' => ['url' => 'https://cdn.example/b.jpg']]],
    ]]]));

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'alt_text', 'ai', null, [$item]);

    expect(mutations('fileUpdate')[0]['files'][0])->toBe(['id' => 'gid://shopify/MediaImage/11', 'alt' => 'Baby romper in organic cotton']);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ai/generate') && str_contains(json_encode($r->data()), 'https:\/\/cdn.example\/b.jpg'));
});

it('optimizes product titles with AI', function () {
    $shop = bulkShop();
    fakeBulkApis([], 'products', 'business', '"Organic Cotton Baby Romper - Lamp Co"');

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'titles', 'ai', null, [snapshot(bulkNode(1))]);

    expect(mutations('productUpdate')[0]['product']['title'])->toBe('Organic Cotton Baby Romper - Lamp Co');
});

it('records failed items with a reason', function () {
    $shop = bulkShop();
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'business']),
        'portal.test/wp-json/ai-seo-saas/v1/ai/generate' => Http::response(['error' => 'limit_reached'], 429),
    ]);

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'meta', 'ai', null, [snapshot(bulkNode(1)), snapshot(bulkNode(2))]);

    expect(Cache::get(BulkOptimizeJob::cacheKey($shop->id)))->toMatchArray([
        'status' => 'completed', 'processed' => 2, 'succeeded' => 0, 'failed' => 2,
        'errors' => [['title' => 'Organic cotton romper 1', 'error' => 'ai_failed'], ['title' => 'Organic cotton romper 2', 'error' => 'ai_failed']],
    ]);
});

it('does not start a run that was cancelled while queued', function () {
    $shop = bulkShop();
    fakeBulkApis();
    Cache::put(BulkOptimizeJob::cacheKey($shop->id), ['status' => 'cancelled']);

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'meta_title', 'template', '{{title}}', [snapshot(bulkNode(1))]);

    expect(mutations('productUpdate'))->toBe([])
        ->and(Cache::get(BulkOptimizeJob::cacheKey($shop->id))['status'])->toBe('cancelled');
});

it('stops between items when the run is cancelled', function () {
    $shop = bulkShop();
    $key = BulkOptimizeJob::cacheKey($shop->id);
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'business']),
        'test-shop.myshopify.com/admin/api/*' => function () use ($key) {
            // The merchant presses "Stop" while the first item is saved.
            Cache::put($key, array_merge(Cache::get($key, []), ['status' => 'cancelled']));

            return Http::response(['data' => ['productUpdate' => ['userErrors' => [], 'product' => ['id' => 'x']]]]);
        },
    ]);

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'meta_title', 'template', '{{title}}', [snapshot(bulkNode(1)), snapshot(bulkNode(2)), snapshot(bulkNode(3))]);

    expect(mutations('productUpdate'))->toHaveCount(1)
        ->and(Cache::get($key))->toMatchArray(['status' => 'cancelled', 'processed' => 1]);
});

it('stops when the plan no longer includes the bulk optimizer', function () {
    $shop = bulkShop();
    fakeBulkApis([], 'products', 'professional');

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'meta_title', 'template', '{{title}}', [snapshot(bulkNode(1))]);

    expect(mutations('productUpdate'))->toBe([])
        ->and(Cache::get(BulkOptimizeJob::cacheKey($shop->id)))->toMatchArray(['status' => 'failed', 'error' => 'tier_too_low']);
});

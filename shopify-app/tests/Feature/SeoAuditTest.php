<?php

use App\Jobs\SeoAuditJob;
use App\Models\SeoAudit;
use App\Models\SeoAuditItem;
use App\Models\Shop;
use App\Services\SeoAuditor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function auditHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function auditShop(string $tier = 'professional'): Shop
{
    return makeTestShop([
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
}

function auditWords(int $n): string
{
    return '<p>'.implode(' ', array_fill(0, $n, 'word')).'</p>';
}

/** A product that passes every item check. */
function auditItem(array $overrides = []): array
{
    return array_merge([
        'type' => 'product',
        'id' => 'gid://shopify/Product/1',
        'title' => 'Organic cotton baby romper',
        'handle' => 'romper',
        'blog_handle' => null,
        'url' => 'https://shop.example/products/romper',
        'seo_title' => 'Organic cotton baby romper | Test Shop',
        'seo_description' => str_repeat('Soft organic cotton romper for babies. ', 3),
        'body_html' => auditWords(80),
        'images' => ['Romper front'],
        'products_count' => null,
        'hidden' => false,
        'published' => true,
    ], $overrides);
}

function issueCodes(array $result): array
{
    return array_column($result['issues'], 'code');
}

// --- SeoAuditor: item checks ---------------------------------------------

it('gives a complete product 100 points', function () {
    $result = app(SeoAuditor::class)->scoreItem(auditItem());

    expect($result['score'])->toBe(100)->and($result['issues'])->toBe([]);
});

it('flags meta, content, alt text and heading problems', function (array $overrides, string $code, int $score) {
    $result = app(SeoAuditor::class)->scoreItem(auditItem($overrides));

    expect(issueCodes($result))->toContain($code)->and($result['score'])->toBe($score);
})->with([
    'missing description' => [['seo_description' => null], 'meta_desc_missing', 85],
    'long description' => [['seo_description' => str_repeat('x', 161)], 'meta_desc_long', 95],
    'short description' => [['seo_description' => 'Too short.'], 'meta_desc_short', 95],
    'no custom title' => [['seo_title' => null], 'meta_title_missing', 92],
    'long title' => [['seo_title' => str_repeat('t', 71)], 'meta_title_long', 94],
    'thin product' => [['body_html' => auditWords(20)], 'thin_content', 85],
    'missing alt' => [['images' => ['ok', null, null]], 'missing_alt', 90],
    'extra h1' => [['body_html' => '<h1>Title</h1>'.auditWords(80)], 'multiple_h1', 95],
    'hidden' => [['hidden' => true], 'hidden_from_search', 60],
    'not published' => [['published' => false], 'not_published', 70],
]);

it('reports the number of images without alt text', function () {
    $result = app(SeoAuditor::class)->scoreItem(auditItem(['images' => [null, 'a', null]]));

    expect($result['issues'][0])->toBe(['code' => 'missing_alt', 'severity' => 'warning', 'detail' => 2]);
});

it('uses a word minimum per content type', function () {
    $auditor = app(SeoAuditor::class);

    expect(issueCodes($auditor->scoreItem(auditItem(['type' => 'article', 'body_html' => auditWords(200)]))))->toContain('thin_content')
        ->and(issueCodes($auditor->scoreItem(auditItem(['type' => 'collection', 'products_count' => 3, 'body_html' => auditWords(40)]))))->not->toContain('thin_content');
});

it('flags empty collections only when the count is known', function () {
    $auditor = app(SeoAuditor::class);

    expect(issueCodes($auditor->scoreItem(auditItem(['type' => 'collection', 'products_count' => 0]))))->toContain('empty_collection')
        ->and(issueCodes($auditor->scoreItem(auditItem(['type' => 'collection', 'products_count' => null]))))->not->toContain('empty_collection');
});

it('never scores below zero', function () {
    $result = app(SeoAuditor::class)->scoreItem(auditItem([
        'hidden' => true, 'published' => false, 'seo_title' => null, 'seo_description' => null,
        'body_html' => '<h1>x</h1>', 'images' => [null],
    ]));

    expect($result['score'])->toBe(0);
});

// --- SeoAuditor: store-wide checks ----------------------------------------

it('finds duplicate titles and descriptions across content types', function () {
    $result = app(SeoAuditor::class)->audit([
        auditItem(['id' => 'a', 'handle' => 'a', 'seo_title' => 'Same title here for test', 'seo_description' => str_repeat('Same description. ', 5)]),
        auditItem(['id' => 'b', 'handle' => 'b', 'type' => 'page', 'body_html' => auditWords(120), 'seo_title' => 'same title  here for TEST', 'seo_description' => str_repeat('Same description. ', 5)]),
        auditItem(['id' => 'c', 'handle' => 'c']),
    ], ['shop.example']);

    expect(issueCodes($result['items'][0]))->toContain('duplicate_title', 'duplicate_description')
        ->and(issueCodes($result['items'][1]))->toContain('duplicate_title')
        ->and(issueCodes($result['items'][2]))->toBe([])
        ->and($result['summary'])->toBe(['duplicate_title' => 2, 'duplicate_description' => 2])
        ->and($result['score'])->toBe(87);
});

it('detects broken internal links and ignores external and valid ones', function () {
    $body = auditWords(80)
        .'<a href="/products/romper">ok</a>'
        .'<a href="https://shop.example/collections/summer/products/romper">scoped ok</a>'
        .'<a href="/nl/pages/about">locale ok</a>'
        .'<a href="/collections/all">all ok</a>'
        .'<a href="https://other.example/products/gone">external</a>'
        .'<a href="/products/gone">broken</a>'
        .'<a href="https://test-shop.myshopify.com/blogs/news/old-post?x=1">broken article</a>'
        .'<a href="/cart">not content</a>';

    $result = app(SeoAuditor::class)->audit([
        auditItem(['body_html' => $body]),
        auditItem(['id' => 'p', 'type' => 'page', 'handle' => 'about', 'body_html' => auditWords(120), 'seo_title' => 'About our organic shop', 'seo_description' => str_repeat('About us text. ', 6)]),
    ], ['shop.example', TEST_SHOP]);

    $issue = collect($result['items'][0]['issues'])->firstWhere('code', 'broken_links');
    expect($issue['detail'])->toBe(['/products/gone', '/blogs/news/old-post']);
});

it('normalizes Shopify nodes per content type', function () {
    $auditor = app(SeoAuditor::class);

    $product = $auditor->normalize('product', [
        'id' => 'gid://shopify/Product/1', 'handle' => 'romper', 'title' => 'Romper', 'onlineStoreUrl' => null,
        'descriptionHtml' => '<p>x</p>', 'seo' => ['title' => ' ', 'description' => 'Desc'],
        'media' => ['nodes' => [['alt' => '', 'mediaContentType' => 'IMAGE'], ['alt' => 'Video', 'mediaContentType' => 'VIDEO'], ['alt' => 'Front', 'mediaContentType' => 'IMAGE']]],
        'hidden' => ['value' => '1'],
    ], 'shop.example');
    $article = $auditor->normalize('article', [
        'id' => 'gid://shopify/Article/2', 'handle' => 'post', 'title' => 'Post', 'body' => '<p>y</p>', 'blog' => ['handle' => 'journal'],
        'titleTag' => ['value' => 'Custom'], 'descriptionTag' => null, 'image' => ['altText' => null],
    ], 'shop.example');

    expect($product)->toMatchArray([
        'seo_title' => null, 'seo_description' => 'Desc', 'images' => [null, 'Front'],
        'hidden' => true, 'published' => false, 'url' => 'https://shop.example/products/romper',
    ])->and($article)->toMatchArray([
        'seo_title' => 'Custom', 'seo_description' => null, 'images' => [null],
        'url' => 'https://shop.example/blogs/journal/post', 'published' => true,
    ]);
});

it('uses the Online Store publication, not onlineStoreUrl, for published products', function () {
    // onlineStoreUrl is null while the storefront is password protected.
    $auditor = app(SeoAuditor::class);
    $node = ['id' => 'gid://shopify/Product/1', 'handle' => 'romper', 'title' => 'Romper', 'onlineStoreUrl' => null, 'descriptionHtml' => '', 'seo' => []];

    expect($auditor->normalize('product', $node + ['publishedOnline' => true], 'shop.example')['published'])->toBeTrue()
        ->and($auditor->normalize('product', $node + ['publishedOnline' => false], 'shop.example')['published'])->toBeFalse();
});

// --- SeoAuditJob ------------------------------------------------------------

function fakeShopifyCatalog(): void
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) {
            $query = $request['query'] ?? '';
            $page = fn (array $nodes) => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => array_map(fn ($n) => ['node' => $n], $nodes)];

            if (str_contains($query, 'products(')) {
                expect($query)->toContain('status:active');

                return Http::response(['data' => ['products' => $page([
                    ['id' => 'gid://shopify/Product/1', 'handle' => 'romper', 'title' => 'Organic cotton baby romper', 'onlineStoreUrl' => 'https://shop.example/products/romper',
                        'descriptionHtml' => auditWords(80).'<a href="/products/gone">x</a>', 'seo' => ['title' => 'Organic cotton baby romper | Shop', 'description' => str_repeat('Soft organic cotton romper. ', 4)],
                        'media' => ['nodes' => [['alt' => 'Front', 'mediaContentType' => 'IMAGE']]], 'hidden' => null],
                    ['id' => 'gid://shopify/Product/2', 'handle' => 'hat', 'title' => 'Hat', 'onlineStoreUrl' => 'https://shop.example/products/hat',
                        'descriptionHtml' => '', 'seo' => ['title' => null, 'description' => null], 'media' => ['nodes' => [['alt' => null, 'mediaContentType' => 'IMAGE']]], 'hidden' => null],
                ])]]);
            }
            if (str_contains($query, 'collections(')) {
                return Http::response(['data' => ['collections' => $page([
                    ['id' => 'gid://shopify/Collection/5', 'handle' => 'empty', 'title' => 'Empty collection for test', 'descriptionHtml' => '', 'seo' => ['title' => null, 'description' => null], 'productsCount' => ['count' => 0], 'image' => null, 'hidden' => null],
                ])]]);
            }

            return Http::response(['data' => [str_contains($query, 'pages(') ? 'pages' : 'articles' => $page([])]]);
        },
    ]);
}

it('runs the audit, stores the items worst first and scores the store', function () {
    $shop = auditShop();
    fakeShopifyCatalog();
    $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'queued']);

    SeoAuditJob::dispatchSync($audit->id);

    $audit->refresh();
    expect($audit->status)->toBe('completed')
        ->and($audit->progress)->toBe(100)
        ->and($audit->items_count)->toBe(3)
        ->and($audit->summary)->toHaveKeys(['broken_links', 'empty_collection', 'meta_desc_missing', 'missing_alt'])
        ->and(SeoAuditItem::where('seo_audit_id', $audit->id)->orderBy('score')->first()->resource_id)->toBe('gid://shopify/Collection/5');

    $romper = SeoAuditItem::where('resource_id', 'gid://shopify/Product/1')->first();
    expect($romper->score)->toBe(85)
        ->and($romper->issues[0])->toMatchArray(['code' => 'broken_links', 'detail' => ['/products/gone']]);
});

it('fails the audit when Shopify cannot be read', function () {
    $shop = auditShop();
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'test-shop.myshopify.com/*' => Http::response('down', 503),
    ]);
    $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'queued']);

    SeoAuditJob::dispatchSync($audit->id);

    expect($audit->refresh())->status->toBe('failed')->error->toBe('http_503')
        ->and(SeoAuditItem::count())->toBe(0);
});

it('does not run the audit when the plan no longer allows it', function () {
    $shop = auditShop();
    Http::fake(['portal.test/*' => Http::response(['status' => 'suspended', 'tier' => 'professional'])]);
    $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'queued']);

    SeoAuditJob::dispatchSync($audit->id);

    expect($audit->refresh())->status->toBe('failed')->error->toBe('tier_too_low');
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'myshopify.com'));
});

it('keeps only the last audits per shop', function () {
    $shop = auditShop();
    fakeShopifyCatalog();
    for ($i = 0; $i < SeoAuditJob::KEEP + 2; $i++) {
        SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed']);
    }
    $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'queued']);

    SeoAuditJob::dispatchSync($audit->id);

    expect(SeoAudit::where('shop_id', $shop->id)->count())->toBe(SeoAuditJob::KEEP)
        ->and(SeoAudit::where('shop_id', $shop->id)->max('id'))->toBe($audit->id);
});

// --- API --------------------------------------------------------------------

it('queues one audit at a time', function () {
    Queue::fake();
    auditShop();
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);

    $this->postJson('/api/audit/start', [], auditHeaders())->assertJsonPath('audit.status', 'queued');
    $this->postJson('/api/audit/start', [], auditHeaders())->assertJsonPath('already_running', true);

    Queue::assertPushed(SeoAuditJob::class, 1);
});

it('restarts an audit that is stuck', function () {
    Queue::fake();
    $shop = auditShop();
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);
    $stuck = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'running']);
    $stuck->forceFill(['created_at' => now()->subHour()])->save();

    $this->postJson('/api/audit/start', [], auditHeaders())->assertJsonMissingPath('already_running');

    expect($stuck->refresh()->status)->toBe('failed');
    Queue::assertPushed(SeoAuditJob::class);
});

it('returns the latest result with issue groups ordered by severity', function () {
    $shop = auditShop();
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);
    SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed', 'score' => 72, 'items_count' => 10, 'finished_at' => now(),
        'summary' => ['meta_title_long' => 9, 'meta_desc_missing' => 4, 'empty_collection' => 1]]);
    SeoAudit::create(['shop_id' => $shop->id, 'status' => 'running', 'progress' => 30]);

    $this->getJson('/api/audit/latest', auditHeaders())
        ->assertJsonPath('current.status', 'running')
        ->assertJsonPath('result.score', 72)
        ->assertJsonPath('result.severity', ['critical' => 1, 'warning' => 4, 'info' => 9])
        ->assertJsonPath('bulk_fixes.meta_desc_missing', 'meta')
        ->assertJson(fn ($json) => $json->where('result.summary', fn ($summary) => array_keys($summary->all()) === ['empty_collection', 'meta_desc_missing', 'meta_title_long'])->etc());
});

it('lists audit items filtered by issue and type', function () {
    $shop = auditShop();
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);
    $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed', 'score' => 70]);
    $add = fn ($type, $id, $score, $codes) => SeoAuditItem::create(['seo_audit_id' => $audit->id, 'shop_id' => $shop->id, 'type' => $type,
        'resource_id' => "gid://shopify/{$id}", 'title' => $id, 'url' => null, 'score' => $score,
        'issues' => array_map(fn ($c) => ['code' => $c, 'severity' => 'warning'], $codes)]);
    $add('product', 'Product/1', 60, ['meta_desc_missing']);
    $add('product', 'Product/2', 90, ['meta_title_long']);
    $add('page', 'Page/3', 40, ['meta_desc_missing']);

    $this->getJson('/api/audit/items?issue=meta_desc_missing', auditHeaders())
        ->assertJsonPath('total', 2)
        ->assertJsonPath('items.0.resource_id', 'gid://shopify/Page/3');

    $this->getJson('/api/audit/items?issue=meta_desc_missing&type=product', auditHeaders())
        ->assertJsonPath('total', 1)
        ->assertJsonPath('items.0.resource_id', 'gid://shopify/Product/1');
});

it('gates the store-wide audit to Professional', function () {
    auditShop('starter');
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'starter'])]);

    $this->postJson('/api/audit/start', [], auditHeaders())
        ->assertStatus(403)
        ->assertJsonPath('required_tier', 'professional');
});

it('keeps the live item score in the content editor on Starter', function () {
    auditShop('starter');
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'starter']),
        'test-shop.myshopify.com/*' => Http::response(['data' => ['node' => ['__typename' => 'Product', 'id' => 'gid://shopify/Product/1', 'title' => 'Lamp', 'handle' => 'lamp',
            'descriptionHtml' => '', 'seo' => ['title' => null, 'description' => null], 'images' => ['edges' => []], 'metafields' => ['edges' => []]]]]),
    ]);

    $this->getJson('/api/content/product/1', auditHeaders())->assertOk()->assertJsonPath('item.seo.score', 58);
});

// --- Integration with other pages -------------------------------------------

it('adds the live SEO score to the content editor and audit scores to the list', function () {
    $shop = auditShop();
    $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed', 'score' => 70]);
    SeoAuditItem::create(['seo_audit_id' => $audit->id, 'shop_id' => $shop->id, 'type' => 'product', 'resource_id' => 'gid://shopify/Product/1',
        'title' => 'Lamp', 'score' => 64, 'issues' => []]);
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'test-shop.myshopify.com/*' => function (Request $request) {
            return str_contains($request['query'] ?? '', 'node(')
                ? Http::response(['data' => ['node' => ['__typename' => 'Product', 'id' => 'gid://shopify/Product/1', 'title' => 'Lamp', 'handle' => 'lamp',
                    'descriptionHtml' => '<p>Short</p>', 'seo' => ['title' => null, 'description' => null], 'images' => ['edges' => []], 'metafields' => ['edges' => []]]]])
                : Http::response(['data' => ['products' => ['edges' => [['node' => ['id' => 'gid://shopify/Product/1', 'title' => 'Lamp', 'handle' => 'lamp', 'productType' => 'Lighting', 'status' => 'DRAFT']]]]]]);
        },
    ]);

    $this->getJson('/api/content/product', auditHeaders())
        ->assertJsonPath('items.0.score', 64)
        ->assertJsonPath('items.0.subtitle', 'Lighting')
        ->assertJsonPath('items.0.status', 'DRAFT');

    $detail = $this->getJson('/api/content/product/1', auditHeaders())->assertOk()->json('item.seo');
    expect($detail['score'])->toBe(58)
        ->and(array_column($detail['issues'], 'code'))->toBe(['meta_title_missing', 'meta_title_short', 'meta_desc_missing', 'thin_content']);
});

it('shows the store SEO score on the home overview', function () {
    $shop = auditShop();
    SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed', 'score' => 81]);
    SeoAudit::create(['shop_id' => $shop->id, 'status' => 'failed']);
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'partners.shopify.com/*' => Http::response(partnerSubscription('starter')),
        'test-shop.myshopify.com/*' => Http::response(['data' => ['productsCount' => ['count' => 2]]]),
    ]);

    $this->getJson('/api/dashboard/overview', auditHeaders())->assertJsonPath('seo_score', 81);
});

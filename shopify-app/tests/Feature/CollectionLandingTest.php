<?php

use App\Models\ActivityEvent;
use App\Models\Shop;
use App\Services\CollectionLanding;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureShopifyTestApp();
});

function clHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function clShop(string $tier = 'professional'): Shop
{
    return makeTestShop([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
}

function fakeCollectionApis(array $aiTexts = [], string $tier = 'professional', ?array $landing = null): void
{
    $sequence = Http::sequence();
    foreach ($aiTexts as $text) {
        $sequence->push(['success' => true, 'text' => $text]);
    }

    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/ai/generate' => $sequence,
        'portal.test/wp-json/ai-seo-saas/v1/ai/keyword-data' => Http::response(['success' => true, 'data' => [['items' => [
            ['keyword' => 'brass lamps', 'search_volume' => 2400],
            ['keyword' => 'vintage desk lamp', 'search_volume' => 880],
        ]]]]),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($landing) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'metafieldsSet')) {
                return Http::response(['data' => ['metafieldsSet' => ['metafields' => [], 'userErrors' => []]]]);
            }
            if (str_contains($query, 'landingCollections')) {
                return Http::response(['data' => ['collections' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => [
                    ['node' => ['id' => 'gid://shopify/Collection/5', 'title' => 'Lamps', 'handle' => 'lamps', 'productsCount' => ['count' => 12], 'landing' => ['value' => '{}', 'updatedAt' => '2026-11-01T10:00:00Z']]],
                    ['node' => ['id' => 'gid://shopify/Collection/6', 'title' => 'Shades', 'handle' => 'shades', 'productsCount' => ['count' => 3], 'landing' => null]],
                ]]]]);
            }

            return Http::response(['data' => ['collection' => [
                'id' => 'gid://shopify/Collection/5', 'title' => 'Lamps', 'handle' => 'lamps', 'descriptionHtml' => '<p>Our lamps.</p>',
                'products' => ['nodes' => [['title' => 'Brass desk lamp', 'productType' => 'Lamp', 'vendor' => 'Lumen']]],
                'landing' => $landing ? ['value' => json_encode($landing)] : null,
            ]]]);
        },
    ]);
}

// --- Sanitizing -------------------------------------------------------------------------

it('keeps simple HTML and strips scripts, attributes and unsafe links', function () {
    $html = app(CollectionLanding::class)->sanitize(
        '<p class="x" onclick="alert(1)">Hi <strong>there</strong></p><script>alert(1)</script>'
        .'<a href="https://lamps.example/a" target="_blank">ok</a><a href="javascript:alert(1)">bad</a><a href="//evil.example">bad</a>'
        .'<img src=x onerror=alert(1)><h1>Big</h1><ul><li style="color:red">One</li></ul>'
    );

    expect($html)->toBe('<p>Hi <strong>there</strong></p><a href="https://lamps.example/a">ok</a><a>bad</a><a>bad</a>Big<ul><li>One</li></ul>');
});

// --- Keywords + content -------------------------------------------------------------------

it('suggests keywords for a collection, highest search volume first', function () {
    clShop();
    fakeCollectionApis(['{"keywords": ["Vintage desk lamp", "brass lamps", "brass lamps", "lamp shades"]}']);

    $keywords = $this->postJson('/api/collection-pages/5/keywords', [], clHeaders())->assertOk()->json('keywords');

    expect($keywords)->toBe([
        ['keyword' => 'brass lamps', 'volume' => 2400],
        ['keyword' => 'vintage desk lamp', 'volume' => 880],
        ['keyword' => 'lamp shades', 'volume' => null],
    ]);
});

it('writes an intro and FAQ for the chosen keywords', function () {
    clShop();
    fakeCollectionApis(['```json
{"intro_html": "<p>Find <strong>brass lamps</strong> for every desk.</p><script>x</script>", "faq": [{"question": "Are brass lamps dimmable?", "answer": "Most of them are."}, {"question": "", "answer": "skip"}]}
```']);

    $landing = $this->postJson('/api/collection-pages/5/generate', ['keywords' => ['brass lamps', 'vintage desk lamp']], clHeaders())->assertOk()->json('landing');

    expect($landing)->toBe([
        'intro_html' => '<p>Find <strong>brass lamps</strong> for every desk.</p>',
        'faq' => [['question' => 'Are brass lamps dimmable?', 'answer' => 'Most of them are.']],
        'keywords' => ['brass lamps', 'vintage desk lamp'],
    ]);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ai/generate') && str_contains(json_encode($r->data()), 'Primary keyword: brass lamps') && str_contains(json_encode($r->data()), 'Brass desk lamp (Lamp)'));
});

it('saves the landing content and the FAQ schema to the collection', function () {
    $shop = clShop();
    fakeCollectionApis();

    $this->postJson('/api/collection-pages/5/save', [
        'intro_html' => '<p>Brass lamps.</p>',
        'faq' => [['question' => 'Dimmable?', 'answer' => 'Yes.']],
        'keywords' => ['brass lamps'],
    ], clHeaders())->assertJson(['success' => true]);

    $metafields = Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'metafieldsSet'))->first()[0]['variables']['metafields'];
    expect($metafields[0])->toMatchArray(['ownerId' => 'gid://shopify/Collection/5', 'namespace' => 'fyndable', 'key' => 'collection_landing', 'type' => 'json'])
        ->and(json_decode($metafields[0]['value'], true)['intro_html'])->toBe('<p>Brass lamps.</p>')
        ->and($metafields[1]['key'])->toBe('faq_schema')
        ->and(json_decode($metafields[1]['value'], true))->toMatchArray(['@type' => 'FAQPage', 'mainEntity' => [['@type' => 'Question', 'name' => 'Dimmable?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes.']]]])
        ->and(ActivityEvent::where('shop_id', $shop->id)->where('kind', 'collection_page')->count())->toBe(1);

    $this->postJson('/api/collection-pages/5/save', ['intro_html' => '<p> </p>', 'faq' => []], clHeaders())->assertJson(['error' => 'nothing_to_save']);
});

it('lists collections with their landing status and loads saved content', function () {
    clShop();
    fakeCollectionApis([], 'professional', ['intro_html' => '<p>Saved</p>', 'faq' => [['question' => 'Q', 'answer' => 'A']], 'keywords' => ['lamps']]);

    $this->getJson('/api/collection-pages', clHeaders())
        ->assertJsonPath('collections.0', ['id' => '5', 'title' => 'Lamps', 'handle' => 'lamps', 'products' => 12, 'has_landing' => true, 'updated_at' => '2026-11-01T10:00:00Z'])
        ->assertJsonPath('collections.1.has_landing', false);

    $this->getJson('/api/collection-pages/5', clHeaders())->assertJsonPath('landing.intro_html', '<p>Saved</p>')->assertJsonPath('landing.faq.0.question', 'Q');
});

it('keeps collection pages on Professional', function () {
    clShop('starter');
    fakeCollectionApis([], 'starter');

    $this->getJson('/api/collection-pages', clHeaders())->assertStatus(403)->assertJsonPath('required_tier', 'professional');
});

it('ships theme app blocks for the collection template', function (string $file, string $needle) {
    $liquid = file_get_contents(base_path("extensions/fyndable-seo-schema/blocks/{$file}"));

    expect($liquid)->toContain('collection.metafields.fyndable.collection_landing.value')
        ->toContain('"enabled_on": { "templates": ["collection"] }')
        ->toContain($needle)
        ->not->toContain('<script');
})->with([
    ['fyndable-collection-intro.liquid', 'fyndable_landing.intro_html'],
    ['fyndable-collection-faq.liquid', '{{ fyndable_item.answer | escape }}'],
]);

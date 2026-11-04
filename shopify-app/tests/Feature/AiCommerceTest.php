<?php

use App\Jobs\BuildProductFeedJob;
use App\Jobs\BulkOptimizeJob;
use App\Models\ActivityEvent;
use App\Models\AiReadinessItem;
use App\Models\ProductFeed;
use App\Models\Shop;
use App\Services\AiCommerce;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    configureShopifyTestApp();
    Storage::fake('local');
});

function arHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function arShop(string $tier = 'professional'): Shop
{
    return makeTestShop([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
}

/** A product that passes every AI-ready check. */
function arProduct(array $overrides = []): array
{
    return array_merge([
        'id' => 'gid://shopify/Product/10',
        'handle' => 'brass-lamp',
        'title' => 'Brass desk lamp',
        'vendor' => 'Lumen & Co',
        'productType' => 'Lamp',
        'status' => 'ACTIVE',
        'onlineStoreUrl' => 'https://lamps.example/products/brass-lamp',
        'descriptionHtml' => '<p>'.str_repeat('Solid brass desk lamp with a warm light. ', 12).'</p>',
        'totalVariants' => 2,
        'category' => ['fullName' => 'Home & Garden > Lighting > Lamps'],
        'seo' => ['description' => 'A solid brass desk lamp.'],
        'media' => ['nodes' => [['image' => ['url' => 'https://cdn.example/1.jpg']], ['image' => ['url' => 'https://cdn.example/2.jpg']], ['image' => ['url' => 'https://cdn.example/3.jpg']]]],
        'aiAttributes' => ['value' => json_encode(['material' => 'Brass', 'color' => 'Gold', 'features' => ['Dimmable', 'USB port'], 'use_cases' => ['Reading'], 'gender' => 'unisex', 'age_group' => 'adult'])],
        'rating' => ['value' => json_encode(['value' => '4.4', 'scale_min' => '1.0', 'scale_max' => '5.0'])],
        'ratingCount' => ['value' => '12'],
    ], $overrides);
}

function arVariant(int $id, array $overrides = []): array
{
    return array_merge([
        'id' => "gid://shopify/ProductVariant/{$id}",
        'sku' => "BL-{$id}",
        'barcode' => '4006381333931',
        'price' => '79.00',
        'compareAtPrice' => null,
        'availableForSale' => true,
        'title' => $id === 1 ? 'Small' : 'Large',
        'selectedOptions' => [['name' => 'Size', 'value' => $id === 1 ? 'Small' : 'Large']],
        'image' => null,
    ], $overrides);
}

function fakeFeedShopify(array $variants, string $tier = 'professional', ?string $aiText = null): void
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/ai/generate' => Http::response(['success' => true, 'text' => $aiText ?? '{}']),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($variants) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'productVariants')) {
                return Http::response(['data' => ['productVariants' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => array_map(fn ($v) => ['node' => $v], $variants)]]]);
            }
            if (str_contains($query, 'primaryDomain')) {
                return Http::response(['data' => ['shop' => ['name' => 'Lamp Shop', 'primaryDomain' => ['url' => 'https://lamps.example'], 'currencyCode' => 'EUR']]]);
            }
            if (str_contains($query, 'metafieldsSet')) {
                return Http::response(['data' => ['metafieldsSet' => ['metafields' => [], 'userErrors' => []]]]);
            }
            if (str_contains($query, 'node(')) {
                return Http::response(['data' => ['node' => ['__typename' => 'Product', 'id' => 'gid://shopify/Product/10', 'title' => 'Brass desk lamp', 'vendor' => 'Lumen & Co', 'descriptionHtml' => '<p>Brass lamp, dimmable.</p>']]]);
            }

            return Http::response(['errors' => [['message' => 'unexpected']]]);
        },
    ]);
}

// --- Pure logic ----------------------------------------------------------------------

it('validates GTIN check digits', function (string $gtin, bool $valid) {
    expect(app(AiCommerce::class)->validGtin($gtin))->toBe($valid);
})->with([
    ['4006381333931', true],     // EAN-13
    ['036000291452', true],      // UPC-A
    ['96385074', true],          // EAN-8
    ['4006381333932', false],    // wrong check digit
    ['12345', false],
    ['BL-1', false],
]);

it('gives a complete product 100 and deducts per missing item', function () {
    $ai = app(AiCommerce::class);

    expect($ai->score(arProduct(), [arVariant(1), arVariant(2)]))->toBe(['score' => 100, 'issues' => []]);

    $result = $ai->score(arProduct([
        'vendor' => '', 'category' => null, 'aiAttributes' => null, 'rating' => null,
        'media' => ['nodes' => [['image' => ['url' => 'https://cdn.example/1.jpg']]]],
        'descriptionHtml' => '<p>'.str_repeat('word ', 50).'</p>',
    ]), [arVariant(1), arVariant(2, ['barcode' => ''])]);

    expect(array_column($result['issues'], 'code'))->toBe(['missing_gtin', 'short_description', 'missing_attributes', 'missing_brand', 'missing_category', 'few_images', 'no_reviews'])
        // 100 - 8 (gtin 1/2) - 8 (short) - 15 - 10 - 10 - 5 (1 image) - 5
        ->and($result['score'])->toBe(39);
});

it('skips draft and unpublished products', function () {
    $draft = arVariant(3) + ['product' => arProduct(['id' => 'gid://shopify/Product/11', 'status' => 'DRAFT'])];
    $hidden = arVariant(4) + ['product' => arProduct(['id' => 'gid://shopify/Product/12', 'onlineStoreUrl' => null])];
    $live = arVariant(1) + ['product' => arProduct()];

    expect(array_keys(app(AiCommerce::class)->group([$draft, $hidden, $live])))->toBe(['gid://shopify/Product/10']);
});

it('builds OpenAI feed rows per variant following the spec', function () {
    $row = app(AiCommerce::class)->openAiRow(arProduct(), arVariant(2, ['price' => '59.00', 'compareAtPrice' => '79.00']), ['name' => 'Lamp Shop', 'url' => 'https://lamps.example', 'currency' => 'EUR']);

    expect($row)->toMatchArray([
        'item_id' => '2',
        'title' => 'Brass desk lamp - Large',
        'url' => 'https://lamps.example/products/brass-lamp?variant=2',
        'brand' => 'Lumen & Co',
        'seller_name' => 'Lamp Shop',
        'image_url' => 'https://cdn.example/1.jpg',
        'availability' => 'in_stock',
        'price' => '79.00 EUR',
        'sale_price' => '59.00 EUR',
        'group_id' => '10',
        'listing_has_variations' => true,
        'variant_dict' => ['size' => 'Large'],
        'gtin' => '4006381333931',
        'product_category' => 'Home & Garden > Lighting > Lamps',
        'material' => 'Brass',
        'color' => 'Gold',
        'size' => 'Large',
        'gender' => 'unisex',
        'age_group' => 'adult',
        'star_rating' => '4.40',
        'review_count' => 12,
        'is_eligible_checkout' => false,
    ])->and($row['additional_image_urls'])->toBe(['https://cdn.example/2.jpg', 'https://cdn.example/3.jpg']);
});

it('marks Google items without a GTIN as identifier_exists no', function () {
    $item = app(AiCommerce::class)->googleItem(arProduct(), arVariant(1, ['barcode' => 'n/a', 'availableForSale' => false]), ['name' => 'Lamp Shop', 'url' => 'https://lamps.example', 'currency' => 'EUR']);

    expect($item)->toMatchArray(['id' => '1', 'availability' => 'out_of_stock', 'identifier_exists' => 'no', 'item_group_id' => '10', 'price' => '79.00 EUR'])
        ->and($item)->not->toHaveKey('gtin')
        ->and($item['product_highlight'])->toBe(['Dimmable', 'USB port']);
});

// --- Building + serving the feeds ------------------------------------------------------

it('builds both feeds and the AI-ready scores', function () {
    $shop = arShop();
    $feed = ProductFeed::forShop($shop);
    $feed->update(['enabled' => true]);
    fakeFeedShopify([
        arVariant(1) + ['product' => arProduct()],
        arVariant(2) + ['product' => arProduct()],
        arVariant(5, ['barcode' => '']) + ['product' => arProduct(['id' => 'gid://shopify/Product/20', 'title' => 'Plain lamp', 'totalVariants' => 1, 'aiAttributes' => null, 'vendor' => ''])],
    ]);

    BuildProductFeedJob::dispatchSync($shop->id);

    $feed->refresh();
    expect($feed)->status->toBe('ready')->products_count->toBe(2)->items_count->toBe(3)
        ->and($feed->summary)->toHaveKeys(['missing_gtin', 'missing_attributes', 'missing_brand'])
        ->and(AiReadinessItem::where('shop_id', $shop->id)->orderBy('score')->first()->product_id)->toBe('20');

    $xml = simplexml_load_string(Storage::disk('local')->get($feed->path('google')));
    $items = $xml->channel->item;
    expect(count($items))->toBe(3)
        ->and((string) $items[0]->children('g', true)->price)->toBe('79.00 EUR');

    $rows = array_map(fn ($l) => json_decode($l, true), array_filter(explode("\n", Storage::disk('local')->get($feed->path('openai')))));
    expect($rows)->toHaveCount(3)->and($rows[2])->toMatchArray(['item_id' => '5', 'brand' => 'Lamp Shop', 'url' => 'https://lamps.example/products/brass-lamp']);
});

it('serves the feeds only under the secret token while enabled', function () {
    $shop = arShop();
    $feed = ProductFeed::forShop($shop);
    $feed->update(['enabled' => true, 'built_at' => now()]);
    Storage::disk('local')->put($feed->path('google'), '<rss/>');
    Storage::disk('local')->put($feed->path('openai'), "{}\n");
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);

    $this->get("/feeds/{$feed->token}/google.xml")->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
    $this->get("/feeds/{$feed->token}/openai.jsonl")->assertOk();
    $this->get('/feeds/'.str_repeat('a', 48).'/google.xml')->assertNotFound();

    $feed->update(['enabled' => false]);
    $this->get("/feeds/{$feed->token}/google.xml")->assertNotFound();
});

it('rotates the feed token so old URLs stop working', function () {
    $shop = arShop();
    $feed = ProductFeed::forShop($shop);
    $feed->update(['enabled' => true, 'built_at' => now()]);
    Storage::disk('local')->put($feed->path('google'), '<rss/>');
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);
    $old = $feed->token;

    $new = $this->postJson('/api/ai-ready/rotate-token', [], arHeaders())->json('feed.urls.google');

    expect($new)->not->toContain($old);
    $this->get("/feeds/{$old}/google.xml")->assertNotFound();
});

it('starts the first build when the feed is turned on and limits rebuilds', function () {
    Queue::fake();
    arShop();
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'professional'])]);

    $this->postJson('/api/ai-ready/feed', ['enabled' => true], arHeaders())->assertJsonPath('feed.enabled', true)->assertJsonPath('feed.status', 'building');
    Queue::assertPushed(BuildProductFeedJob::class, 1);

    $this->postJson('/api/ai-ready/rebuild', [], arHeaders())->assertJson(['success' => true]);
    $this->postJson('/api/ai-ready/rebuild', [], arHeaders())->assertJson(['error' => 'too_soon']);
});

// --- AI attributes ------------------------------------------------------------------------

it('generates shopping attributes with AI and saves the edited version', function () {
    arShop();
    fakeFeedShopify([], 'professional', "```json\n{\"material\": \"Brass\", \"features\": [\"Dimmable\", \"\"], \"use_cases\": [\"Reading\"], \"gender\": \"robot\", \"age_group\": \"adult\"}\n```");

    $this->postJson('/api/ai-ready/10/attributes', [], arHeaders())
        ->assertJsonPath('attributes.material', 'Brass')
        ->assertJsonPath('attributes.features', ['Dimmable'])
        ->assertJsonPath('attributes.gender', '');

    $this->postJson('/api/ai-ready/10/attributes/save', ['attributes' => ['material' => 'Solid brass', 'features' => ['Dimmable', 'USB port'], 'gender' => 'unisex']], arHeaders())
        ->assertJson(['success' => true]);

    $sent = Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'metafieldsSet'))->first()[0];
    $metafield = $sent['variables']['metafields'][0];
    expect($metafield)->toMatchArray(['ownerId' => 'gid://shopify/Product/10', 'namespace' => 'fyndable', 'key' => 'ai_attributes', 'type' => 'json'])
        ->and(json_decode($metafield['value'], true))->toMatchArray(['material' => 'Solid brass', 'features' => ['Dimmable', 'USB port'], 'gender' => 'unisex']);
});

it('fills AI attributes in bulk', function () {
    $shop = arShop('business');
    fakeFeedShopify([], 'business', '{"material": "Brass", "features": ["Dimmable"], "use_cases": ["Reading"]}');
    $item = ['type' => 'product', 'id' => 'gid://shopify/Product/10', 'title' => 'Brass desk lamp', 'seo_title' => null, 'seo_description' => null, 'vendor' => '', 'product_type' => '', 'price' => '', 'blog' => '', 'excerpt' => '', 'images' => []];

    BulkOptimizeJob::dispatchSync($shop->id, 'product', 'ai_attributes', 'ai', null, [$item]);

    expect(Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'metafieldsSet')))->toHaveCount(1)
        ->and(ActivityEvent::where('kind', 'ai_attributes')->count())->toBe(1);
});

// --- Plan + privacy --------------------------------------------------------------------------

it('keeps AI shopping on Professional', function () {
    arShop('starter');
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'starter'])]);

    $this->getJson('/api/ai-ready', arHeaders())->assertStatus(403)->assertJsonPath('required_tier', 'professional');
});

it('stops serving feeds on uninstall and removes them on shop/redact', function () {
    Queue::fake();
    $shop = arShop();
    $feed = ProductFeed::forShop($shop);
    $feed->update(['enabled' => true]);
    Storage::disk('local')->put($feed->path('google'), '<rss/>');
    AiReadinessItem::create(['shop_id' => $shop->id, 'product_id' => '1', 'title' => 'x', 'score' => 50, 'issues' => []]);

    sendWebhook('app/uninstalled')->assertOk();
    expect($feed->refresh()->enabled)->toBeFalse();

    sendWebhook('shop/redact')->assertOk();
    expect(ProductFeed::count() + AiReadinessItem::count())->toBe(0)
        ->and(Storage::disk('local')->exists($feed->path('google')))->toBeFalse();
});

<?php

use App\Jobs\TranslateJob;
use App\Models\ActivityEvent;
use App\Models\Shop;
use App\Models\ShopProfile;
use App\Services\BrandVoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function trHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function trShop(string $tier = 'professional', array $attributes = []): Shop
{
    return makeTestShop(array_merge([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ], $attributes));
}

function trNode(int $id, array $translations = []): array
{
    return [
        'resourceId' => "gid://shopify/Product/{$id}",
        'translatableContent' => [
            ['key' => 'title', 'value' => "Brass lamp {$id}", 'digest' => "d-title-{$id}", 'locale' => 'en'],
            ['key' => 'handle', 'value' => "brass-lamp-{$id}", 'digest' => "d-handle-{$id}", 'locale' => 'en'],
            ['key' => 'body_html', 'value' => '<p>A <strong>solid brass</strong> desk lamp.</p>', 'digest' => "d-body-{$id}", 'locale' => 'en'],
            ['key' => 'meta_title', 'value' => 'Brass desk lamp | Lamp Shop', 'digest' => "d-mt-{$id}", 'locale' => 'en'],
            ['key' => 'meta_description', 'value' => '', 'digest' => "d-md-{$id}", 'locale' => 'en'],
        ],
        'translations' => $translations,
    ];
}

function fakeTranslationApis(array $nodes, string $tier = 'professional', ?string $aiText = null): void
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/ai/generate' => Http::response(['success' => true, 'text' => $aiText ?? '{"title": "Messing bureaulamp", "body_html": "<p>Een <strong>massief messing</strong> bureaulamp.</p>", "meta_title": "Messing bureaulamp kopen | Lamp Shop | Gratis verzending in heel Nederland"}']),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($nodes) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'shopLocales')) {
                return Http::response(['data' => ['shopLocales' => [
                    ['locale' => 'en', 'name' => 'English', 'primary' => true, 'published' => true],
                    ['locale' => 'nl', 'name' => 'Dutch', 'primary' => false, 'published' => true],
                    ['locale' => 'de', 'name' => 'German', 'primary' => false, 'published' => false],
                ]]]);
            }
            if (str_contains($query, 'translationsRegister')) {
                return Http::response(['data' => ['translationsRegister' => ['translations' => [], 'userErrors' => []]]]);
            }
            if (str_contains($query, 'translatableResources(')) {
                return Http::response(['data' => ['translatableResources' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'edges' => array_map(fn ($n) => ['node' => $n], $nodes)]]]);
            }
            if (str_contains($query, 'translatableResource(')) {
                return Http::response(['data' => ['translatableResource' => $nodes[0] ?? null]]);
            }

            return Http::response(['errors' => [['message' => 'unexpected']]]);
        },
    ]);
}

function registered(): array
{
    return Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'translationsRegister'))->map(fn ($p) => $p[0]['variables'])->values()->all();
}

// --- Languages + gaps ---------------------------------------------------------------------

it('lists the primary language and the languages to translate into', function () {
    trShop();
    fakeTranslationApis([]);

    $this->getJson('/api/translations', trHeaders())
        ->assertJsonPath('primary.locale', 'en')
        ->assertJsonPath('targets.0', ['locale' => 'nl', 'name' => 'Dutch', 'published' => true])
        ->assertJsonPath('targets.1.published', false);
});

it('finds missing and outdated translations and ignores empty and untranslated keys', function () {
    trShop();
    fakeTranslationApis([
        trNode(1),
        trNode(2, [['key' => 'title', 'value' => 'Messing lamp', 'outdated' => true], ['key' => 'body_html', 'value' => '<p>x</p>', 'outdated' => false], ['key' => 'meta_title', 'value' => 'x', 'outdated' => false]]),
        trNode(3, [['key' => 'title', 'value' => 'a', 'outdated' => false], ['key' => 'body_html', 'value' => 'b', 'outdated' => false], ['key' => 'meta_title', 'value' => 'c', 'outdated' => false]]),
    ]);

    $response = $this->getJson('/api/translations/gaps?type=product&locale=nl', trHeaders())->assertOk();

    expect($response->json('total'))->toBe(2)
        ->and($response->json('fields'))->toBe(4)
        ->and($response->json('items.0'))->toMatchArray(['resource_id' => 'gid://shopify/Product/1', 'title' => 'Brass lamp 1', 'missing' => ['title', 'body_html', 'meta_title'], 'outdated' => []])
        ->and($response->json('items.1'))->toMatchArray(['missing' => [], 'outdated' => ['title']]);
});

it('only translates into languages of the store', function () {
    trShop();
    fakeTranslationApis([]);

    $this->getJson('/api/translations/gaps?type=product&locale=fr', trHeaders())->assertJson(['error' => 'invalid_locale']);
    $this->getJson('/api/translations/gaps?type=product&locale=en', trHeaders())->assertJson(['error' => 'invalid_locale']);
    $this->getJson('/api/translations/gaps?type=variant&locale=nl', trHeaders())->assertJson(['error' => 'invalid_type']);
});

// --- Translating ----------------------------------------------------------------------------

it('translates the missing fields in the brand voice without forcing the store language', function () {
    $shop = trShop();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'en', 'target_country' => 'US', 'brand_tone' => 'luxury']);
    fakeTranslationApis([trNode(1)]);

    $entries = $this->postJson('/api/translations/preview', ['type' => 'product', 'resource_id' => 'gid://shopify/Product/1', 'locale' => 'nl'], trHeaders())
        ->assertOk()->json('entries');

    expect(array_column($entries, 'key'))->toBe(['title', 'body_html', 'meta_title'])
        ->and($entries[1])->toMatchArray(['value' => '<p>Een <strong>massief messing</strong> bureaulamp.</p>', 'digest' => 'd-body-1'])
        ->and(mb_strlen($entries[2]['value']))->toBeLessThanOrEqual(60);

    Http::assertSent(function (Request $r) {
        if (! str_contains($r->url(), '/ai/generate')) {
            return false;
        }
        $payload = json_encode($r->data());

        return str_contains($payload, 'into Dutch (locale nl)') && str_contains($payload, 'premium and refined')
            && ! str_contains($payload, 'Write all text in English') && ! str_contains($payload, 'country US')
            && ! str_contains($payload, 'brass-lamp-1');
    });
});

it('registers the edited translation with the source digests', function () {
    trShop();
    fakeTranslationApis([]);

    $this->postJson('/api/translations/save', ['type' => 'product', 'resource_id' => 'gid://shopify/Product/1', 'locale' => 'nl', 'entries' => [
        ['key' => 'title', 'value' => 'Messing bureaulamp', 'digest' => 'd-title-1'],
        ['key' => 'meta_title', 'value' => '', 'digest' => 'd-mt-1'],
    ]], trHeaders())->assertJson(['success' => true]);

    expect(registered())->toBe([[
        'resourceId' => 'gid://shopify/Product/1',
        'translations' => [['locale' => 'nl', 'key' => 'title', 'value' => 'Messing bureaulamp', 'translatableContentDigest' => 'd-title-1']],
    ]]);
    expect(ActivityEvent::where('kind', 'translation')->count())->toBe(1);
});

it('needs the write_translations scope to save', function () {
    trShop('professional', ['scope' => 'read_products,write_products,read_translations,read_locales']);
    fakeTranslationApis([]);

    $this->postJson('/api/translations/save', ['resource_id' => 'gid://shopify/Product/1', 'locale' => 'nl', 'entries' => []], trHeaders())
        ->assertStatus(403)->assertJsonPath('error', 'missing_scopes');
});

// --- Background run ---------------------------------------------------------------------------

it('queues a run for the gaps and translates them in the background', function () {
    Queue::fake();
    $shop = trShop();
    fakeTranslationApis([trNode(1), trNode(2)]);

    $this->postJson('/api/translations/run', ['type' => 'product', 'locale' => 'nl', 'limit' => 1], trHeaders())->assertJson(['success' => true, 'total' => 1]);
    $this->postJson('/api/translations/run', ['type' => 'product', 'locale' => 'nl'], trHeaders())->assertJson(['error' => 'job_running']);

    Queue::assertPushed(TranslateJob::class, fn ($job) => $job->locale === 'nl' && $job->languageName === 'Dutch' && count($job->resources) === 1);
});

it('runs the translation job and reports progress', function () {
    $shop = trShop();
    fakeTranslationApis([trNode(1)]);
    Cache::put(TranslateJob::cacheKey($shop->id), ['status' => 'queued', 'total' => 2]);

    TranslateJob::dispatchSync($shop->id, 'product', 'nl', 'Dutch', [
        ['resource_id' => 'gid://shopify/Product/1', 'title' => 'Brass lamp 1'],
        ['resource_id' => 'gid://shopify/Product/1', 'title' => 'Brass lamp again'],
    ]);

    expect(Cache::get(TranslateJob::cacheKey($shop->id)))->toMatchArray(['status' => 'completed', 'processed' => 2, 'succeeded' => 2, 'fields' => 6])
        ->and(registered())->toHaveCount(2)
        ->and(ActivityEvent::where('kind', 'translation')->sum('count'))->toBe(6);
});

it('keeps translations on Professional', function () {
    trShop('starter');
    fakeTranslationApis([], 'starter');

    $this->getJson('/api/translations', trHeaders())->assertStatus(403)->assertJsonPath('required_tier', 'professional');
});

it('can leave the store language and country out of the brand voice', function () {
    $shop = trShop();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'nl', 'target_country' => 'BE', 'brand_tone' => 'friendly']);

    $voice = app(BrandVoice::class);

    expect($voice->instructions($shop->fresh(), true, false))->not->toContain('Dutch')->not->toContain('BE')->toContain('Brand voice')
        ->and($voice->instructions($shop->fresh()))->toContain('Write all text in Dutch.');
});

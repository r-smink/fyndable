<?php

use App\Models\ShopProfile;
use App\Services\BrandVoice;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureShopifyTestApp();
});

function onbHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

/**
 * Admin GraphQL fake: locale defaults + product search.
 */
function fakeShopifyForOnboarding(array $products = [], ?string $primaryLocale = 'nl-NL', ?string $country = 'NL'): void
{
    Http::fake([
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($products, $primaryLocale, $country) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'billingAddress')) {
                return Http::response(['data' => ['shop' => ['billingAddress' => ['countryCodeV2' => $country]]]]);
            }
            if (str_contains($query, 'shopLocales')) {
                return $primaryLocale === null
                    ? Http::response(['errors' => [['message' => 'Access denied']]])
                    : Http::response(['data' => ['shopLocales' => [['locale' => 'en', 'primary' => false], ['locale' => $primaryLocale, 'primary' => true]]]]);
            }

            return Http::response(['data' => ['products' => ['edges' => array_map(fn ($p) => ['node' => $p], $products)]]]);
        },
        'partners.shopify.com/*' => Http::response(partnerSubscription(null)),
        'portal.test/*' => Http::response(saasSyncResponse('trial')),
    ]);
}

function product(int $id, bool $withSeo): array
{
    return [
        'id' => "gid://shopify/Product/{$id}",
        'title' => "Product {$id}",
        'featuredImage' => null,
        'seo' => $withSeo ? ['title' => 'T', 'description' => 'D'] : ['title' => null, 'description' => null],
    ];
}

// --- Wizard endpoints ----------------------------------------------------

it('prefills language and country from Shopify and suggests products without SEO first', function () {
    makeTestShop();
    $products = [product(1, true), product(2, false), product(3, true), product(4, false), product(5, true), product(6, true), product(7, false)];
    fakeShopifyForOnboarding($products);

    $response = $this->getJson('/api/onboarding', onbHeaders())->assertOk();

    $response->assertJson([
        'done' => false,
        'suggestions' => ['content_language' => 'nl', 'target_country' => 'NL'],
        'trial' => ['max' => 5],
    ]);
    $ids = array_column($response->json('products'), 'id');
    expect($ids)->toHaveCount(5)
        ->and(array_slice($ids, 0, 3))->toBe(['gid://shopify/Product/2', 'gid://shopify/Product/4', 'gid://shopify/Product/7']);
});

it('falls back to the admin locale when read_locales is not granted', function () {
    makeTestShop();
    fakeShopifyForOnboarding([], null, null);

    $this->getJson('/api/onboarding?locale=de-DE', onbHeaders())
        ->assertJson(['suggestions' => ['content_language' => 'de', 'target_country' => null]]);
});

it('saves the store profile', function () {
    $shop = makeTestShop();

    $this->postJson('/api/onboarding/profile', [
        'content_language' => 'nl',
        'target_country' => 'be',
        'brand_tone' => 'friendly',
        'brand_audience' => 'Jonge ouders',
        'brand_description' => 'Duurzame babykleding',
    ], onbHeaders())->assertOk()->assertJson(['success' => true]);

    expect($shop->profile()->first())
        ->content_language->toBe('nl')
        ->target_country->toBe('BE')
        ->brand_tone->toBe('friendly');
});

it('rejects unknown languages and tones', function (array $payload) {
    makeTestShop();

    $this->postJson('/api/onboarding/profile', $payload, onbHeaders())
        ->assertJson(['error' => 'invalid_profile']);
})->with([
    [['content_language' => 'xx']],
    [['content_language' => 'nl', 'brand_tone' => 'angry']],
    [['content_language' => 'nl', 'target_country' => 'NLD']],
]);

it('marks the wizard as completed or skipped', function (string $action, string $column) {
    $shop = makeTestShop();

    $this->postJson("/api/onboarding/{$action}", [], onbHeaders())->assertJson(['success' => true]);

    expect($shop->profile()->first()->{$column})->not->toBeNull()
        ->and($shop->profile()->first()->onboardingDone())->toBeTrue();
})->with([['complete', 'onboarding_completed_at'], ['skip', 'onboarding_skipped_at']]);

it('reports onboarding status in the dashboard overview', function () {
    $shop = makeTestShop();
    fakeShopifyForOnboarding();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'nl']);

    $this->getJson('/api/dashboard/overview', onbHeaders())
        ->assertJsonPath('onboarding.done', false)
        ->assertJsonPath('onboarding.language', 'nl');
});

it('requires a session token for onboarding', function () {
    makeTestShop();

    $this->getJson('/api/onboarding')->assertStatus(401);
});

// --- Brand voice ---------------------------------------------------------

it('adds language, market and brand voice to the system prompt', function () {
    $shop = makeTestShop();
    ShopProfile::create([
        'shop_id' => $shop->id,
        'content_language' => 'nl',
        'target_country' => 'BE',
        'brand_tone' => 'luxury',
        'brand_audience' => "Design\nlovers",
        'brand_description' => 'Handgemaakte lampen',
    ]);

    $messages = app(BrandVoice::class)->apply($shop->fresh(), [
        ['role' => 'system', 'content' => 'You are an SEO expert.'],
        ['role' => 'user', 'content' => 'Write.'],
    ]);

    expect($messages[0]['content'])
        ->toContain('You are an SEO expert.')
        ->toContain('Write all text in Dutch.')
        ->toContain('country BE')
        ->toContain('premium and refined')
        ->toContain('Target audience: Design lovers.')
        ->toContain('About the brand: Handgemaakte lampen');
    expect($messages[1]['content'])->toBe('Write.');
});

it('only adds language and market when the voice is off (alt text)', function () {
    $shop = makeTestShop();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'de', 'brand_tone' => 'playful']);

    $content = app(BrandVoice::class)->apply($shop->fresh(), [['role' => 'system', 'content' => 'x']], false)[0]['content'];

    expect($content)->toContain('German')->not->toContain('playful');
});

it('leaves prompts untouched without a profile', function () {
    $shop = makeTestShop();
    $messages = [['role' => 'user', 'content' => 'Hi']];

    expect(app(BrandVoice::class)->apply($shop, $messages))->toBe($messages);
});

it('prepends a system message when there is none', function () {
    $shop = makeTestShop();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'fr']);

    $messages = app(BrandVoice::class)->apply($shop->fresh(), [['role' => 'user', 'content' => 'Hi']]);

    expect($messages[0]['role'])->toBe('system')->and($messages[0]['content'])->toContain('French');
});

it('sends the store language with product meta generation', function () {
    $shop = makeTestShop([
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'starter', 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'nl']);
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'starter']),
        'portal.test/wp-json/ai-seo-saas/v1/ai/generate' => Http::response(['success' => true, 'text' => '{"title":"Titel","description":"Omschrijving"}']),
        'test-shop.myshopify.com/*' => Http::response(['data' => ['product' => ['id' => 'gid://shopify/Product/1', 'title' => 'Lamp', 'description' => '', 'variants' => ['edges' => []]]]]),
    ]);

    $this->postJson('/api/products/1/generate-meta', [], onbHeaders())->assertJson(['success' => true, 'title' => 'Titel']);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/ai/generate')
        && str_contains(json_encode($r['messages'] ?? $r->data()), 'Write all text in Dutch.'));
});

it('renders the onboarding wizard as a Polaris modal on home and settings', function (string $url) {
    $this->get($url.'?shop='.TEST_SHOP.'&locale=nl-NL')
        ->assertOk()
        ->assertSee('<s-modal id="onb-modal"', false)
        ->assertSee('js/onboarding.js', false)
        ->assertSee('Optimaliseer je eerste producten');
})->with(['/dashboard', '/dashboard/settings']);

it('only opens the wizard from the setup card, never on page load', function () {
    // Built for Shopify 4.3.6: the card is hidden until the overview says
    // onboarding is open, and only its button calls FynOnboarding.open().
    $this->get('/dashboard?shop='.TEST_SHOP)
        ->assertSee('<s-section id="onb-card" hidden>', false);

    expect(file_get_contents(public_path('js/pages/home.js')))->not->toContain('FynOnboarding.open(');
});

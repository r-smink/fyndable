<?php

use App\Http\Controllers\DashboardController;
use App\Models\ShopProfile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureShopifyTestApp();
});

function pageHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

// --- Page shell (Polaris web components + App Bridge navigation) ---------

it('renders every app page with App Bridge navigation and Polaris', function (string $page) {
    $url = $page === 'home' ? '/dashboard' : "/dashboard/{$page}";

    $response = $this->get($url.'?shop='.TEST_SHOP.'&host=abc123&locale=nl-NL')->assertOk();

    $response->assertSee('https://cdn.shopify.com/shopifycloud/polaris-2.js', false)
        ->assertSee('<s-app-nav>', false)
        ->assertSee('<s-page', false)
        ->assertSee('href="/dashboard/settings?shop='.TEST_SHOP.'&amp;host=abc123&amp;locale=nl-NL"', false)
        ->assertSee('js/pages/'.$page.'.js', false)
        ->assertDontSee('fyn-dark', false);

    expect(file_exists(public_path("js/pages/{$page}.js")))->toBeTrue();
})->with(DashboardController::PAGES);

it('returns 404 for unknown pages', function () {
    $this->get('/dashboard/nope?shop='.TEST_SHOP)->assertNotFound();
});

it('rejects pages without a valid shop', function () {
    $this->get('/dashboard/settings?shop=evil.com')->assertStatus(400);
});

it('follows the Shopify admin language', function () {
    $this->get('/dashboard/settings?shop='.TEST_SHOP.'&locale=nl-NL')
        ->assertSee('<html lang="nl">', false)
        ->assertSee('Taal van je content')
        ->assertSee('"settings_saved":"Instellingen opgeslagen"', false);

    $this->get('/dashboard/settings?shop='.TEST_SHOP.'&locale=en-US')
        ->assertSee('<html lang="en">', false)
        ->assertSee('Content language');
});

it('does not pass unsafe query values on to the navigation', function () {
    $this->get('/dashboard?shop='.TEST_SHOP.'&host=%22%3E%3Cscript%3E&locale=xx%22')
        ->assertOk()
        ->assertDontSee('<script>"', false)
        ->assertSee('href="/dashboard/settings?shop='.TEST_SHOP.'"', false);
});

it('renders the tier map for plan gating', function () {
    $this->get('/dashboard/geoscan?shop='.TEST_SHOP)
        ->assertSee('"feature":"geo_scan"', false)
        ->assertSee('"geoscan":"geo_scan"', false);
});

it('maps every gated tier tab to an existing page', function () {
    expect(array_diff(array_keys(config('tiers.tabs')), DashboardController::PAGES))->toBe([]);
});

it('has the same translation keys in English and Dutch', function () {
    $files = array_map('basename', glob(lang_path('en/*.php')));
    expect($files)->toContain('app.php');

    foreach ($files as $file) {
        expect(file_exists(lang_path("nl/{$file}")))->toBeTrue("lang/nl/{$file} is missing");
        $en = array_keys(Arr::dot(require lang_path("en/{$file}")));
        $nl = array_keys(Arr::dot(require lang_path("nl/{$file}")));

        expect(array_values(array_diff($en, $nl)))->toBe([], "keys missing in lang/nl/{$file}")
            ->and(array_values(array_diff($nl, $en)))->toBe([], "keys missing in lang/en/{$file}");
    }
});

it('uses no em or en dashes in merchant-facing text', function () {
    $files = array_merge(glob(lang_path('*/*.php')), glob(resource_path('views/dashboard/pages/*.blade.php')), glob(public_path('js/pages/*.js')), [public_path('js/fyndable.js'), public_path('js/onboarding.js')]);

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain("\u{2014}")->not->toContain("\u{2013}");
    }
});

// --- Context + settings API ------------------------------------------------

it('returns the page context with plan gating and billing', function () {
    makeTestShop(['license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'starter', 'license_source' => 'app_pricing', 'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now()]);
    Http::fake(['portal.test/*' => Http::response(['status' => 'active', 'tier' => 'starter'])]);

    $this->getJson('/api/dashboard/context', pageHeaders())
        ->assertOk()
        ->assertJsonPath('plan.tier', 'starter')
        ->assertJsonPath('plan.features.geo_scan', false)
        ->assertJsonPath('plan.features.llmstxt', true)
        ->assertJsonPath('onboarding.done', false);
});

it('loads the settings profile with the Shopify suggestion while nothing is saved', function () {
    makeTestShop();
    Http::fake([
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) {
            return str_contains($request['query'] ?? '', 'billingAddress')
                ? Http::response(['data' => ['shop' => ['billingAddress' => ['countryCodeV2' => 'BE']]]])
                : Http::response(['data' => ['shopLocales' => [['locale' => 'nl', 'primary' => true]]]]);
        },
    ]);

    $this->getJson('/api/settings/profile', pageHeaders())
        ->assertOk()
        ->assertJsonPath('suggestions.content_language', 'nl')
        ->assertJsonPath('suggestions.target_country', 'BE')
        ->assertJsonPath('languages.de', 'German');
});

it('skips the Shopify lookup once a language is saved', function () {
    $shop = makeTestShop();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'fr', 'brand_tone' => 'playful']);
    Http::fake();

    $this->getJson('/api/settings/profile', pageHeaders())
        ->assertJsonPath('profile.content_language', 'fr')
        ->assertJsonPath('profile.brand_tone', 'playful')
        ->assertJsonPath('suggestions', null);

    Http::assertNothingSent();
});

it('saves the content language from the settings page', function () {
    $shop = makeTestShop();
    ShopProfile::create(['shop_id' => $shop->id, 'content_language' => 'en', 'onboarding_completed_at' => now()]);

    $this->postJson('/api/settings/profile', ['content_language' => 'de', 'target_country' => 'at', 'brand_tone' => null], pageHeaders())
        ->assertJson(['success' => true]);

    expect($shop->profile()->first())
        ->content_language->toBe('de')
        ->target_country->toBe('AT')
        ->onboarding_completed_at->not->toBeNull();
});

it('rejects an unknown content language on the settings page', function () {
    makeTestShop();

    $this->postJson('/api/settings/profile', ['content_language' => 'tlh'], pageHeaders())
        ->assertJson(['error' => 'invalid_profile']);
});

it('requires a session token for settings and context', function (string $url) {
    makeTestShop();

    $this->getJson($url)->assertStatus(401);
})->with(['/api/settings/profile', '/api/dashboard/context']);

// --- Menu groups -------------------------------------------------------------------------

it('puts every page except home in exactly one menu group', function () {
    $grouped = array_merge(...array_values(DashboardController::GROUPS));

    expect($grouped)->toHaveCount(count(array_unique($grouped)))
        ->and(array_values(array_diff(DashboardController::PAGES, $grouped)))->toBe(['home'])
        ->and(array_values(array_diff($grouped, DashboardController::PAGES)))->toBe([]);
});

it('shows home and the groups in the Shopify menu', function () {
    $html = $this->get('/dashboard?shop='.TEST_SHOP.'&locale=nl-NL')->assertOk()->getContent();

    preg_match('#<s-app-nav>(.*?)</s-app-nav>#s', $html, $nav);
    preg_match_all('#<s-link [^>]*>([^<]+)</s-link>#', $nav[1], $links);

    expect(array_map('trim', $links[1]))->toBe(['Home', 'SEO', 'Content', 'Zoekwoorden', 'AI-zichtbaarheid', 'Rapporten', 'Instellingen'])
        ->and($html)->not->toContain('id="fyn-subnav"');
});

it('shows the sub navigation of the group with the current page marked', function () {
    $html = $this->get('/dashboard/backlinks?shop='.TEST_SHOP)->assertOk()->getContent();

    preg_match('#<s-stack id="fyn-subnav".*?</s-stack>#s', $html, $subnav);
    preg_match_all('#<s-button href="/dashboard/([a-z]+)[^"]*" variant="(primary|secondary)" data-feature="([a-z_]*)"#', $subnav[0], $buttons, PREG_SET_ORDER);

    expect(array_column($buttons, 1))->toBe(DashboardController::GROUPS['keywords'])
        ->and(collect($buttons)->firstWhere(1, 'backlinks')[2])->toBe('primary')
        ->and(collect($buttons)->firstWhere(1, 'gsc')[2])->toBe('secondary')
        ->and(collect($buttons)->firstWhere(1, 'backlinks')[3])->toBe('backlinks')
        ->and($subnav[0])->toContain('aria-current="page"');
});

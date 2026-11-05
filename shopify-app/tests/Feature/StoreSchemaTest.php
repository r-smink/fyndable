<?php

use App\Jobs\SyncStoreSchemaJob;
use App\Models\Shop;
use App\Models\StoreSchemaSetting;
use App\Services\StoreSchemaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    configureShopifyTestApp();
});

function schemaHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function schemaShop(string $tier = 'starter'): Shop
{
    return makeTestShop([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
}

/**
 * Fake the SaaS tenant + Shopify (shop details, app installation,
 * metafieldsSet and theme files).
 */
function fakeSchemaShopify(string $tier = 'starter', array $themeFiles = []): void
{
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => $tier]),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($themeFiles) {
            $query = $request['query'] ?? '';
            if (str_contains($query, 'primaryDomain')) {
                return Http::response(['data' => ['shop' => ['id' => 'gid://shopify/Shop/777', 'name' => 'Lamp Shop', 'primaryDomain' => ['url' => 'https://lamps.example'], 'currencyCode' => 'EUR']]]);
            }
            if (str_contains($query, 'currentAppInstallation')) {
                return Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/55']]]);
            }
            if (str_contains($query, 'metafieldsSet')) {
                return Http::response(['data' => ['metafieldsSet' => ['metafields' => [['id' => '1']], 'userErrors' => []]]]);
            }
            if (str_contains($query, 'themeFiles')) {
                return Http::response(['data' => ['themes' => ['nodes' => [[
                    'name' => 'Dawn',
                    'files' => [
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'nodes' => array_map(fn ($name, $content) => ['filename' => $name, 'body' => ['content' => $content]], array_keys($themeFiles), $themeFiles),
                    ],
                ]]]]]);
            }

            return Http::response(['errors' => [['message' => 'unexpected']]]);
        },
    ]);
}

function publishedConfig(): array
{
    $sent = Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'metafieldsSet'))->first();
    expect($sent)->not->toBeNull();

    return json_decode($sent[0]['variables']['metafields'][0]['value'], true);
}

function schemaSettings(array $overrides = []): array
{
    return array_replace_recursive(app(StoreSchemaService::class)->defaults(), [
        'organization' => ['name' => 'Lamp Co', 'logo' => 'https://cdn.example/logo.png', 'same_as' => ['https://instagram.com/lampco']],
        'local_business' => ['street' => 'Dorpsstraat 1', 'city' => 'Utrecht', 'postal_code' => '3511 AA', 'country' => 'NL', 'opening_hours' => ['Mo-Fr 09:00-17:30']],
    ], $overrides);
}

// --- Building the config -----------------------------------------------------

it('builds Organization and LocalBusiness for the home page', function () {
    $service = app(StoreSchemaService::class);
    $settings = schemaSettings(['flags' => ['local_business' => true]]);

    $config = $service->buildConfig($settings, array_fill_keys(StoreSchemaService::FLAGS, true), ['name' => 'Lamp Shop', 'domain' => 'https://lamps.example']);

    expect($config)->toMatchArray(['website' => true, 'breadcrumbs' => true, 'product' => true, 'article' => true, 'organization_id' => 'https://lamps.example/#organization'])
        ->and($config['home'][0])->toMatchArray(['@type' => 'Organization', 'name' => 'Lamp Co', 'url' => 'https://lamps.example/', 'sameAs' => ['https://instagram.com/lampco']])
        ->and($config['home'][0])->not->toHaveKey('email')
        ->and($config['home'][1])->toMatchArray([
            '@type' => 'Store',
            'openingHours' => ['Mo-Fr 09:00-17:30'],
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Dorpsstraat 1', 'addressLocality' => 'Utrecht', 'postalCode' => '3511 AA', 'addressCountry' => 'NL'],
            'parentOrganization' => ['@id' => 'https://lamps.example/#organization'],
        ]);
});

it('falls back to the store name and skips LocalBusiness without an address', function () {
    $settings = schemaSettings(['organization' => ['name' => ''], 'flags' => ['local_business' => true], 'local_business' => ['street' => '']]);

    $config = app(StoreSchemaService::class)->buildConfig($settings, array_fill_keys(StoreSchemaService::FLAGS, true), ['name' => 'Lamp Shop', 'domain' => 'https://lamps.example']);

    expect($config['home'])->toHaveCount(1)->and($config['home'][0]['name'])->toBe('Lamp Shop');
});

it('publishes switched off schema when the plan does not include it', function () {
    $settings = schemaSettings(['flags' => ['local_business' => true]]);
    $allowed = array_fill_keys(StoreSchemaService::FLAGS, false);

    $config = app(StoreSchemaService::class)->buildConfig($settings, $allowed, ['name' => 'Lamp Shop', 'domain' => 'https://lamps.example']);

    expect($config)->toMatchArray(['website' => false, 'breadcrumbs' => false, 'product' => false, 'article' => false, 'home' => [], 'organization_id' => null]);
});

// --- Plan gating + publish ---------------------------------------------------

it('saves the settings and publishes them to the app installation metafield', function () {
    $shop = schemaShop('professional');
    fakeSchemaShopify('professional');

    $this->postJson('/api/schema', schemaSettings(['flags' => ['local_business' => true]]), schemaHeaders())->assertJson(['success' => true]);

    $sent = Http::recorded(fn ($r) => str_contains($r['query'] ?? '', 'metafieldsSet'))->first()[0];
    expect($sent['variables']['metafields'][0])->toMatchArray(['ownerId' => 'gid://shopify/AppInstallation/55', 'namespace' => 'fyndable', 'key' => 'schema_config', 'type' => 'json'])
        ->and(array_column(publishedConfig()['home'], '@type'))->toBe(['Organization', 'Store'])
        ->and(StoreSchemaSetting::where('shop_id', $shop->id)->first()->published_at)->not->toBeNull();
});

it('keeps LocalBusiness out on Starter (Professional feature)', function () {
    schemaShop('starter');
    fakeSchemaShopify('starter');

    $this->getJson('/api/schema', schemaHeaders())
        ->assertJsonPath('allowed.product', true)
        ->assertJsonPath('allowed.local_business', false);
    $this->postJson('/api/schema', schemaSettings(['flags' => ['local_business' => true]]), schemaHeaders())->assertJson(['success' => true]);

    expect(array_column(publishedConfig()['home'], '@type'))->toBe(['Organization'])
        ->and(publishedConfig()['product'])->toBeTrue();
});

it('rejects invalid schema settings', function (array $overrides, string $field) {
    schemaShop();
    fakeSchemaShopify();

    $this->postJson('/api/schema', schemaSettings($overrides), schemaHeaders())
        ->assertJson(['error' => 'invalid_settings'])
        ->assertJsonFragment([$field]);
})->with([
    [['organization' => ['logo' => 'http://insecure.example/logo.png']], 'organization.logo'],
    [['organization' => ['same_as' => ['javascript:alert(1)']]], 'organization.same_as.0'],
    [['local_business' => ['type' => 'Casino']], 'local_business.type'],
    [['local_business' => ['opening_hours' => ['maandag 9 tot 5']]], 'local_business.opening_hours.0'],
    [['local_business' => ['latitude' => 120]], 'local_business.latitude'],
]);

it('reports a publish error without losing the saved settings', function () {
    $shop = schemaShop();
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'starter']),
        'test-shop.myshopify.com/*' => Http::response(['data' => ['currentAppInstallation' => null, 'shop' => []]]),
    ]);

    $this->postJson('/api/schema', schemaSettings(), schemaHeaders())->assertJson(['error' => 'publish_failed', 'message' => 'no_app_installation']);

    expect(StoreSchemaSetting::where('shop_id', $shop->id)->first())
        ->settings->organization->name->toBe('Lamp Co')
        ->publish_error->toBe('no_app_installation');
});

it('re-publishes daily so a cancelled plan switches the schema off', function () {
    $shop = schemaShop();
    StoreSchemaSetting::create(['shop_id' => $shop->id, 'settings' => schemaSettings()]);
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'suspended', 'tier' => 'starter']),
        'test-shop.myshopify.com/admin/api/*' => fn (Request $r) => str_contains($r['query'] ?? '', 'currentAppInstallation')
            ? Http::response(['data' => ['currentAppInstallation' => ['id' => 'gid://shopify/AppInstallation/55']]])
            : (str_contains($r['query'] ?? '', 'metafieldsSet')
                ? Http::response(['data' => ['metafieldsSet' => ['metafields' => [], 'userErrors' => []]]])
                : Http::response(['data' => ['shop' => ['name' => 'Lamp Shop', 'primaryDomain' => ['url' => 'https://lamps.example']]]])),
    ]);

    SyncStoreSchemaJob::dispatchSync($shop->id);

    expect(publishedConfig())->toMatchArray(['product' => false, 'website' => false, 'home' => []]);
});

it('gates the schema settings by plan', function () {
    makeTestShop();

    $this->getJson('/api/schema', schemaHeaders())->assertStatus(403);
});

it('renders the store-wide schema from the app-data metafield in the app embed', function () {
    $liquid = file_get_contents(base_path('extensions/fyndable-seo-schema/blocks/fyndable-seo-schema.liquid'));

    expect($liquid)
        ->toContain('app.metafields.fyndable.schema_config.value')
        ->toContain('"@type": "BreadcrumbList"')
        ->toContain('"@type": "Product"')
        ->toContain('"@type": "SearchAction"')
        ->toContain('fyndable_schema == nil')
        ->not->toContain('<script src');
});

it('removes schema settings on shop/redact', function () {
    Queue::fake();
    $shop = schemaShop();
    StoreSchemaSetting::create(['shop_id' => $shop->id, 'settings' => schemaSettings()]);

    sendWebhook('shop/redact')->assertOk();

    expect(StoreSchemaSetting::count())->toBe(0);
});

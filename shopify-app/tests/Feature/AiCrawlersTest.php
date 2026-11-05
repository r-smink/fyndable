<?php

use App\Models\Shop;
use App\Services\AiCrawlers;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureShopifyTestApp();
});

function crawlerHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function crawlerShop(string $tier = 'starter'): Shop
{
    return makeTestShop([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
}

const SHOPIFY_DEFAULT_ROBOTS = "# we use Shopify as our ecommerce platform\nUser-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nSitemap: https://lamps.example/sitemap.xml\n\nUser-agent: adsbot-google\nDisallow: /checkouts/\n";

function fakeCrawlerShopify(string $robots = SHOPIFY_DEFAULT_ROBOTS, string $tier = 'starter'): void
{
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => $tier]),
        'lamps.example/robots.txt' => Http::response($robots),
        'test-shop.myshopify.com/admin/api/*' => Http::response(['data' => ['shop' => ['name' => 'Lamp Shop', 'primaryDomain' => ['url' => 'https://lamps.example']]]]),
    ]);
}

it('works out per bot whether robots.txt blocks it', function (string $robots, array $expected) {
    $crawlers = app(AiCrawlers::class);
    $groups = $crawlers->parse($robots);

    foreach ($expected as $bot => $blocked) {
        expect($crawlers->isBlocked($groups, $bot))->toBe($blocked, $bot);
    }
})->with([
    'Shopify default' => [SHOPIFY_DEFAULT_ROBOTS, ['GPTBot' => false, 'OAI-SearchBot' => false]],
    'own group' => [SHOPIFY_DEFAULT_ROBOTS."\nUser-agent: GPTBot\nDisallow: /\n", ['GPTBot' => true, 'gptbot' => true, 'OAI-SearchBot' => false]],
    'shared group' => ["User-agent: *\nDisallow: /cart\n\nUser-agent: GPTBot\nUser-agent: CCBot # comment\nDisallow: /\n", ['GPTBot' => true, 'CCBot' => true, 'ClaudeBot' => false]],
    'everything blocked' => ["User-agent: *\nDisallow: /\n\nUser-agent: OAI-SearchBot\nAllow: /\n", ['GPTBot' => true, 'OAI-SearchBot' => false]],
    'partial disallow only' => ["User-agent: ClaudeBot\nDisallow: /checkout\n", ['ClaudeBot' => false]],
]);

it('lists the AI bots with company and purpose', function () {
    crawlerShop();
    fakeCrawlerShopify();

    $this->getJson('/api/ai-crawlers', crawlerHeaders())
        ->assertJsonPath('bots.GPTBot', ['company' => 'OpenAI', 'purpose' => 'training'])
        ->assertJsonPath('bots.OAI-SearchBot.purpose', 'search');
});

it('reports the live robots.txt per bot', function () {
    crawlerShop();
    fakeCrawlerShopify(SHOPIFY_DEFAULT_ROBOTS."\nUser-agent: GPTBot\nDisallow: /\n");

    $this->postJson('/api/ai-crawlers/check', [], crawlerHeaders())
        ->assertJsonPath('live.url', 'https://lamps.example/robots.txt')
        ->assertJsonPath('live.bots.GPTBot', 'block')
        ->assertJsonPath('live.bots.OAI-SearchBot', 'allow');

    Http::assertNotSent(fn (Request $r) => str_contains($r['query'] ?? '', 'themeFiles'));
});

it('reports when the live robots.txt cannot be read', function () {
    crawlerShop();
    fakeCrawlerShopify('<html>password</html>');

    $this->postJson('/api/ai-crawlers/check', [], crawlerHeaders())->assertJsonPath('live.error', 'no_robots_txt');
});

it('does not ask merchants to edit theme code (App Store 5.1.1)', function () {
    foreach (array_merge(glob(lang_path('*/*.php')), glob(resource_path('views/dashboard/pages/*.blade.php')), glob(public_path('js/pages/*.js'))) as $file) {
        expect(file_get_contents($file))->not->toContain('robots.txt.liquid', basename($file))
            ->not->toMatch('/Edit code|Code bewerken/', basename($file));
    }
    $this->postJson('/api/ai-crawlers', [], crawlerHeaders())->assertStatus(405);
});

it('needs a paid plan for AI crawler status', function () {
    makeTestShop();

    $this->getJson('/api/ai-crawlers', crawlerHeaders())->assertStatus(403);
});

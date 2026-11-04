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

function fakeCrawlerShopify(?string $themeFile, string $robots = SHOPIFY_DEFAULT_ROBOTS, string $tier = 'starter'): void
{
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => $tier]),
        'lamps.example/robots.txt' => Http::response($robots),
        'test-shop.myshopify.com/admin/api/*' => function (Request $request) use ($themeFile) {
            if (str_contains($request['query'] ?? '', 'themeFiles')) {
                return Http::response(['data' => ['themes' => ['nodes' => [[
                    'name' => 'Dawn',
                    'files' => ['pageInfo' => ['hasNextPage' => false, 'endCursor' => null], 'nodes' => $themeFile === null ? [] : [['filename' => 'templates/robots.txt.liquid', 'body' => ['content' => $themeFile]]]],
                ]]]]]);
            }

            return Http::response(['data' => ['shop' => ['name' => 'Lamp Shop', 'primaryDomain' => ['url' => 'https://lamps.example']]]]);
        },
    ]);
}

// --- Generating the robots.txt code --------------------------------------------------

it('allows every AI crawler by default and adds no rules for allowed bots', function () {
    $shop = crawlerShop();
    $crawlers = app(AiCrawlers::class);

    $settings = $crawlers->settings($shop);
    $block = $crawlers->block($settings);

    expect(array_unique(array_values($settings)))->toBe(['allow'])
        ->and($block)->toStartWith(AiCrawlers::BEGIN)->toEndWith(AiCrawlers::END."\n")
        ->and($block)->not->toContain('User-agent');
});

it('blocks only the chosen bots and keeps Shopify defaults in the full template', function () {
    $shop = crawlerShop();
    $crawlers = app(AiCrawlers::class);

    $settings = $crawlers->save($shop, ['GPTBot' => 'block', 'CCBot' => 'block', 'OAI-SearchBot' => 'allow', 'EvilBot' => 'block', 'ClaudeBot' => 'maybe']);
    $template = $crawlers->fullTemplate($settings);

    expect($settings)->toMatchArray(['GPTBot' => 'block', 'CCBot' => 'block', 'ClaudeBot' => 'allow'])
        ->and($settings)->not->toHaveKey('EvilBot')
        ->and($template)->toContain('{% for group in robots.default_groups %}')
        ->toContain("User-agent: GPTBot\nDisallow: /")
        ->toContain("User-agent: CCBot\nDisallow: /")
        ->not->toContain('OAI-SearchBot')
        ->not->toContain('Allow: /');
});

// --- Reading robots.txt ------------------------------------------------------------------

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

// --- API ----------------------------------------------------------------------------------

it('saves the choices and returns the code for the theme', function () {
    crawlerShop();
    fakeCrawlerShopify(null);

    $this->getJson('/api/ai-crawlers', crawlerHeaders())
        ->assertJsonPath('settings.GPTBot', 'allow')
        ->assertJsonPath('bots.GPTBot', ['company' => 'OpenAI', 'purpose' => 'training'])
        ->assertJsonPath('bots.OAI-SearchBot.purpose', 'search');

    $response = $this->postJson('/api/ai-crawlers', ['bots' => ['GPTBot' => 'block']], crawlerHeaders())->assertJsonPath('settings.GPTBot', 'block');
    expect($response->json('block'))->toContain("User-agent: GPTBot\nDisallow: /");
});

it('reports the theme file and the live robots.txt', function (?string $themeFile, array $expectTheme) {
    $shop = crawlerShop();
    $crawlers = app(AiCrawlers::class);
    $settings = $crawlers->save($shop, ['GPTBot' => 'block']);
    $themeFile = $themeFile === 'current' ? $crawlers->fullTemplate($settings) : $themeFile;
    fakeCrawlerShopify($themeFile, SHOPIFY_DEFAULT_ROBOTS."\nUser-agent: GPTBot\nDisallow: /\n");

    $response = $this->postJson('/api/ai-crawlers/check', [], crawlerHeaders())->assertOk();

    expect($response->json('theme'))->toMatchArray($expectTheme)
        ->and($response->json('live.bots.GPTBot'))->toBe('block')
        ->and($response->json('live.bots.OAI-SearchBot'))->toBe('allow')
        ->and($response->json('live.url'))->toBe('https://lamps.example/robots.txt');
})->with([
    'no template' => [null, ['exists' => false, 'has_block' => false, 'in_sync' => false, 'theme' => 'Dawn']],
    'template without block' => ['{{ robots }}', ['exists' => true, 'has_block' => false]],
    'outdated block' => [AiCrawlers::BEGIN."\n".AiCrawlers::END, ['has_block' => true, 'in_sync' => false]],
    'current block' => ['current', ['has_block' => true, 'in_sync' => true]],
]);

it('reports when the live robots.txt cannot be read', function () {
    crawlerShop();
    Http::fake([
        'portal.test/*' => Http::response(['status' => 'active', 'tier' => 'starter']),
        'lamps.example/robots.txt' => Http::response('<html>password</html>', 200),
        'test-shop.myshopify.com/admin/api/*' => Http::response(['data' => ['shop' => ['primaryDomain' => ['url' => 'https://lamps.example']], 'themes' => ['nodes' => []]]]),
    ]);

    $this->postJson('/api/ai-crawlers/check', [], crawlerHeaders())->assertJsonPath('live.error', 'no_robots_txt');
});

it('needs a paid plan for AI crawler management', function () {
    makeTestShop();

    $this->getJson('/api/ai-crawlers', crawlerHeaders())->assertStatus(403);
});

<?php

use Illuminate\Support\Facades\Http;

/*
 * The insights and AI tracker endpoints return the fields the dashboard
 * shows instead of the raw DataForSEO envelope the SaaS passes through.
 */

beforeEach(function () {
    configureShopifyTestApp();
    makeTestShop([
        'shop_name' => 'Acme Outdoor',
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => 'professional', 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
    ]);
});

function insightsHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function llmEnvelope(string $text): array
{
    return ['success' => true, 'provider' => 'chatgpt', 'data' => ['tasks' => [['result' => [[
        'model_name' => 'gpt-test',
        'items' => [['type' => 'message', 'sections' => [['type' => 'text', 'text' => $text, 'annotations' => [['title' => 'Acme', 'url' => 'https://acme.example/']]]]]],
    ]]]]]];
}

it('returns a readable LLM answer instead of the raw envelope', function () {
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'portal.test/wp-json/ai-seo-saas/v1/ai/llm-response' => Http::response(llmEnvelope('Try **Acme Outdoor** for tents.')),
    ]);

    $this->postJson('/api/insights/llm-check', ['prompt' => 'best tent shop?', 'provider' => 'chatgpt'], insightsHeaders())
        ->assertOk()
        ->assertJsonPath('answer', 'Try **Acme Outdoor** for tents.')
        ->assertJsonPath('brand_mentioned', true)
        ->assertJsonPath('sources.0.url', 'https://acme.example/')
        ->assertJsonMissingPath('data');
});

it('summarizes the multi-provider check per provider', function () {
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'portal.test/wp-json/ai-seo-saas/v1/ai/llm-response' => Http::sequence()
            ->push(llmEnvelope('Acme Outdoor is great.'))
            ->push(['success' => false, 'error' => 'dataforseo_error', 'message' => 'Provider down'], 502)
            ->push(llmEnvelope('Globex sells tents.'))
            ->push(llmEnvelope('No idea.')),
    ]);

    $response = $this->postJson('/api/ai-tracker/check', ['prompt' => 'best tent shop?'], insightsHeaders())->assertOk();

    expect($response->json('results.chatgpt.brand_mentioned'))->toBeTrue()
        ->and($response->json('results.claude.error'))->toBe('Provider down')
        ->and($response->json('results.gemini.brand_mentioned'))->toBeFalse()
        ->and($response->json('results.gemini'))->not->toHaveKey('data');
});

it('returns backlink summary numbers', function () {
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'portal.test/wp-json/ai-seo-saas/v1/backlinks/summary' => Http::response(['success' => true, 'data' => ['tasks' => [['result' => [[
            'target' => 'acme.example', 'rank' => 37, 'backlinks' => 900, 'referring_domains' => 51,
        ]]]]]]),
    ]);

    $this->getJson('/api/insights/backlinks?target=acme.example', insightsHeaders())
        ->assertOk()
        ->assertJsonPath('live', false)
        ->assertJsonPath('summary.rank', 37)
        ->assertJsonPath('summary.referring_domains', 51);
});

it('passes SaaS errors through unchanged', function () {
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => 'professional']),
        'portal.test/wp-json/ai-seo-saas/v1/ai/llm-response' => Http::response(['success' => false, 'error' => 'dataforseo_error', 'message' => 'Provider down'], 502),
    ]);

    $response = $this->postJson('/api/insights/llm-check', ['prompt' => 'q'], insightsHeaders())->assertOk();
    expect($response->json('error'))->not->toBeNull()->and($response->json())->not->toHaveKey('answer');
});

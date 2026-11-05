<?php

use App\Jobs\SendSeoReportJob;
use App\Mail\WeeklyReportMail;
use App\Models\ActivityEvent;
use App\Models\GoogleConnection;
use App\Models\RedirectEvent;
use App\Models\SeoAudit;
use App\Models\SeoReport;
use App\Models\Shop;
use App\Models\ShopProfile;
use App\Models\TrackedKeyword;
use App\Services\ReportBuilder;
use App\Services\ReportMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    configureShopifyTestApp();
});

function reportHeaders(): array
{
    return ['Authorization' => 'Bearer '.sessionToken()];
}

function reportShop(string $tier = 'professional', array $attributes = []): Shop
{
    return makeTestShop(array_merge([
        'scope' => config('shopify.scopes'),
        'license_key' => 'K', 'tenant_key' => 'T', 'license_tier' => $tier, 'license_source' => 'app_pricing',
        'billing_state' => 'active', 'billing_checked_at' => now(), 'trial_ended_at' => now(),
        'shop_name' => 'Lamp Shop',
    ], $attributes));
}

function fakeReportSaas(string $tier = 'professional', array $geo = []): void
{
    Http::fake([
        'portal.test/wp-json/ai-seo-saas/v1/tenant/status' => Http::response(['status' => 'active', 'tier' => $tier]),
        'portal.test/wp-json/ai-seo-saas/v1/geo-scan/recent*' => Http::response(['scans' => $geo]),
        'portal.test/*' => Http::response(['success' => true]),
        'test-shop.myshopify.com/*' => Http::response(['data' => ['productUpdate' => ['userErrors' => [], 'product' => ['id' => 'gid://shopify/Product/1']]]]),
    ]);
}

// --- Activity log -------------------------------------------------------------------

it('logs successful saves for the report and ignores failures', function () {
    $shop = reportShop();
    fakeReportSaas();

    $this->postJson('/api/products/1/save-meta', ['title' => 'Lamp | Lamp Shop', 'description' => 'A brass lamp.'], reportHeaders())->assertJson(['success' => true]);
    $this->postJson('/api/products/1/save-meta', [], reportHeaders());

    expect(ActivityEvent::where('shop_id', $shop->id)->pluck('kind')->all())->toBe(['meta']);
});

// --- Report figures ------------------------------------------------------------------

it('builds the weekly figures from audit, rankings, Search Console, GEO and activity', function () {
    $shop = reportShop();
    fakeReportSaas('professional', [['status' => 'completed', 'score' => 71.6, 'created_at' => '2026-11-01']]);

    SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed', 'score' => 60, 'finished_at' => now()->subDays(20)]);
    SeoAudit::create(['shop_id' => $shop->id, 'status' => 'completed', 'score' => 74, 'finished_at' => now()->subDay(), 'summary' => ['broken_links' => 2, 'meta_desc_missing' => 5]]);

    $up = TrackedKeyword::create(['shop_id' => $shop->id, 'keyword' => 'brass lamp', 'country' => 'us', 'language' => 'en', 'last_position' => 4]);
    $down = TrackedKeyword::create(['shop_id' => $shop->id, 'keyword' => 'desk lamp', 'country' => 'us', 'language' => 'en', 'last_position' => 30]);
    $new = TrackedKeyword::create(['shop_id' => $shop->id, 'keyword' => 'lamp shop', 'country' => 'us', 'language' => 'en', 'last_position' => 9]);
    DB::table('rank_history')->insert([
        ['tracked_keyword_id' => $up->id, 'position' => 12, 'checked_at' => now()->subDays(9), 'search_engine' => 'google'],
        ['tracked_keyword_id' => $down->id, 'position' => 22, 'checked_at' => now()->subDays(9), 'search_engine' => 'google'],
    ]);

    GoogleConnection::create(['shop_id' => $shop->id, 'refresh_token' => 'r', 'status' => 'connected', 'site_url' => 'sc-domain:x',
        'totals' => ['clicks' => 150, 'impressions' => 5000, 'ctr' => 0.03, 'position' => 12.5], 'period_start' => now()->subDays(30), 'period_end' => now()->subDays(3)]);
    DB::table('gsc_query_stats')->insert(['shop_id' => $shop->id, 'query' => 'brass lamp', 'page' => 'https://x/products/a', 'path' => '/products/a', 'clicks' => 3, 'impressions' => 400, 'ctr' => 0.01, 'position' => 11.4]);

    ActivityEvent::record($shop->id, 'meta', 3);
    ActivityEvent::record($shop->id, 'alt_text');
    RedirectEvent::create(['shop_id' => $shop->id, 'source' => 'deleted', 'path' => '/a', 'target' => '/b']);
    ActivityEvent::create(['shop_id' => $shop->id, 'kind' => 'meta', 'count' => 9])->forceFill(['created_at' => now()->subDays(20)])->save();

    $report = app(ReportBuilder::class)->create($shop, now());
    $previous = $report->metrics;

    expect($previous['seo'])->toMatchArray(['score' => 74, 'previous' => 60, 'critical' => 2])
        ->and($previous['rankings'])->toMatchArray(['tracked' => 3, 'top10' => 2, 'top3' => 0, 'improved' => 2, 'declined' => 1])
        ->and($previous['rankings']['movers'][0])->toBe(['keyword' => 'lamp shop', 'from' => null, 'to' => 9, 'change' => 92])
        ->and($previous['search_console'])->toMatchArray(['clicks' => 150, 'previous_clicks' => null])
        ->and($previous['search_console']['opportunities'][0]['query'])->toBe('brass lamp')
        ->and($previous['geo'])->toMatchArray(['score' => 72])
        ->and($previous['activity'])->toBe(['counts' => ['meta' => 3, 'alt_text' => 1, 'redirect' => 1], 'total' => 5]);

    // Next week compares Search Console clicks with this report.
    expect(app(ReportBuilder::class)->create($shop, now()->addDays(6))->metrics['search_console']['previous_clicks'])->toBe(150);
});

it('leaves out sections the plan does not include', function () {
    $shop = reportShop('starter');
    fakeReportSaas('starter');
    TrackedKeyword::create(['shop_id' => $shop->id, 'keyword' => 'x', 'country' => 'us', 'language' => 'en', 'last_position' => 3]);

    $metrics = app(ReportBuilder::class)->create($shop, now())->metrics;

    expect($metrics['rankings'])->toBeNull()->and($metrics['geo'])->toBeNull();
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'geo-scan'));
});

// --- Sending -----------------------------------------------------------------------------

it('emails the weekly report in the chosen language with signed links', function () {
    Mail::fake();
    $shop = reportShop();
    fakeReportSaas();
    ShopProfile::create(['shop_id' => $shop->id, 'report_enabled' => true, 'report_email' => 'owner@lamps.example', 'report_language' => 'nl']);
    ActivityEvent::record($shop->id, 'meta', 4);

    SendSeoReportJob::dispatchSync($shop->id);

    Mail::assertSent(WeeklyReportMail::class, function (WeeklyReportMail $mail) {
        $html = $mail->render();

        return $mail->hasTo('owner@lamps.example')
            && $mail->locale === 'nl'
            && str_contains($html, 'Wekelijks SEO-rapport')
            && str_contains($html, '4 verbeteringen in totaal')
            && str_contains($html, '/report/') && str_contains($html, 'signature=')
            && str_contains($html, '/report/unsubscribe/')
            && str_contains($html, 'admin.shopify.com/store/test-shop/apps/fyndable-smart-seo');
    });
    expect(SeoReport::sole())->sent_to->toBe('owner@lamps.example')->sent_at->not->toBeNull();
});

it('only sends scheduled reports when the email is turned on', function () {
    Mail::fake();
    $shop = reportShop();
    fakeReportSaas();
    ShopProfile::create(['shop_id' => $shop->id, 'report_enabled' => false, 'report_email' => 'owner@lamps.example']);

    SendSeoReportJob::dispatchSync($shop->id);
    Mail::assertNothingSent();

    SendSeoReportJob::dispatchSync($shop->id, true);
    Mail::assertSent(WeeklyReportMail::class);
});

it('does not send reports below Professional', function () {
    Mail::fake();
    $shop = reportShop('starter');
    fakeReportSaas('starter');
    ShopProfile::create(['shop_id' => $shop->id, 'report_enabled' => true, 'report_email' => 'owner@lamps.example']);

    SendSeoReportJob::dispatchSync($shop->id);

    Mail::assertNothingSent();
    $this->getJson('/api/reports', reportHeaders())->assertStatus(403)->assertJsonPath('required_tier', 'professional');
});

// --- Signed pages ------------------------------------------------------------------------

it('serves the printable report only with a valid signature', function () {
    $shop = reportShop();
    fakeReportSaas();
    $report = app(ReportBuilder::class)->create($shop, now());
    $other = app(ReportBuilder::class)->create($shop, now());

    $url = URL::temporarySignedRoute('report.show', now()->addHour(), ['report' => $report->id], false);
    $this->get($url)->assertOk()->assertSee('Weekly SEO report')->assertSee('window.print()', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get(str_replace('report/'.$report->id, 'report/'.$other->id, $url))->assertForbidden();
    $this->get('/report/'.$report->id)->assertForbidden();
    $this->get(URL::temporarySignedRoute('report.show', now()->subMinute(), ['report' => $report->id], false))->assertForbidden();
});

it('turns the email off from the unsubscribe link', function () {
    $shop = reportShop();
    ShopProfile::create(['shop_id' => $shop->id, 'report_enabled' => true, 'report_email' => 'owner@lamps.example', 'report_language' => 'nl']);

    $this->get(URL::signedRoute('report.unsubscribe', ['shop' => $shop->id], null, false))->assertOk()->assertSee('Wekelijks rapport uitgezet');

    expect(ShopProfile::where('shop_id', $shop->id)->value('report_enabled'))->toBeFalsy();
});

// --- API --------------------------------------------------------------------------------

it('saves the report settings and defaults the email to the store owner', function () {
    reportShop();
    fakeReportSaas();

    $this->getJson('/api/reports', reportHeaders())->assertJsonPath('settings', ['enabled' => false, 'email' => 'owner@example.com', 'language' => 'en']);
    $this->postJson('/api/reports/settings', ['enabled' => true, 'email' => 'not-an-email', 'language' => 'nl'], reportHeaders())->assertJson(['error' => 'invalid_settings']);
    $this->postJson('/api/reports/settings', ['enabled' => true, 'email' => 'seo@lamps.example', 'language' => 'nl'], reportHeaders())
        ->assertJsonPath('settings', ['enabled' => true, 'email' => 'seo@lamps.example', 'language' => 'nl']);
});

it('creates a report on request at most every 10 minutes and lists it with a signed link', function () {
    Queue::fake();
    $shop = reportShop();
    fakeReportSaas();

    $this->postJson('/api/reports/send', [], reportHeaders())->assertJson(['success' => true]);
    $this->postJson('/api/reports/send', [], reportHeaders())->assertJson(['error' => 'too_soon']);
    Queue::assertPushed(SendSeoReportJob::class, fn ($job) => $job->manual);

    app(ReportBuilder::class)->create($shop, now());
    expect($this->getJson('/api/reports', reportHeaders())->json('reports.0.url'))->toContain('signature=');
});

it('removes reports and the activity log on shop/redact', function () {
    Queue::fake();
    $shop = reportShop();
    fakeReportSaas();
    app(ReportBuilder::class)->create($shop, now());
    ActivityEvent::record($shop->id, 'meta');

    sendWebhook('shop/redact')->assertOk();

    expect(SeoReport::count() + ActivityEvent::count())->toBe(0);
});

it('keeps signed report links valid after a change of the app domain', function () {
    $shop = reportShop();
    fakeReportSaas();
    $report = app(ReportBuilder::class)->create($shop, now());

    config(['app.url' => 'https://old-domain.example']);
    $url = app(ReportMailer::class)->reportUrl($report, now()->addDay());
    expect($url)->toStartWith('https://old-domain.example/report/');

    // Same path + query on another host (redirected domain) still validates.
    $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->assertOk();
});

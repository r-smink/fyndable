<?php

use App\Jobs\CheckRankingsJob;
use App\Jobs\SyncSearchConsoleJob;
use App\Jobs\SyncShopBillingJob;
use App\Jobs\SyncStoreSchemaJob;
use App\Models\GoogleConnection;
use App\Models\Shop;
use App\Models\StoreSchemaSetting;
use App\Services\LlmsTxtGenerator;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Jobs
|--------------------------------------------------------------------------
*/

// Daily rank check for all shops with active licenses
Schedule::call(function () {
    $shops = Shop::where('is_installed', true)
        ->where('is_uninstalled', false)
        ->whereNotNull('license_key')
        ->whereNotNull('tenant_key')
        ->get();

    foreach ($shops as $shop) {
        CheckRankingsJob::dispatch($shop->id);
    }
})->dailyAt('06:00')->name('daily-rank-check')->withoutOverlapping();

// Publish scheduled blog articles every 15 minutes
Schedule::command('app:publish-scheduled-articles')
    ->everyFifteenMinutes()
    ->name('publish-scheduled-articles')
    ->withoutOverlapping();

// Daily re-publish of the store-wide schema config (1.4), so plan changes
// also switch off structured data that is no longer included.
Schedule::call(function () {
    StoreSchemaSetting::whereHas('shop', fn ($q) => $q->where('is_installed', true)->where('is_uninstalled', false))
        ->pluck('shop_id')
        ->each(fn (int $shopId, int $i) => SyncStoreSchemaJob::dispatch($shopId)->delay(now()->addSeconds(intdiv($i, 3))));
})->dailyAt('04:00')->name('store-schema-sync')->withoutOverlapping();

// Daily Search Console snapshot (1.5) for connected shops with a property.
Schedule::call(function () {
    GoogleConnection::where('status', 'connected')->whereNotNull('site_url')
        ->whereHas('shop', fn ($q) => $q->where('is_installed', true)->where('is_uninstalled', false))
        ->pluck('shop_id')
        ->each(fn (int $shopId, int $i) => SyncSearchConsoleJob::dispatch($shopId)->delay(now()->addSeconds($i * 2)));
})->dailyAt('05:00')->name('search-console-sync')->withoutOverlapping();

// Daily llms.txt cache refresh (prevents stale cache from building up)
Schedule::call(function () {
    $shops = Shop::where('is_installed', true)
        ->where('is_uninstalled', false)
        ->get();

    foreach ($shops as $shop) {
        $generator = app(LlmsTxtGenerator::class);
        $generator->invalidate($shop);
    }
})->dailyAt('03:00')->name('daily-llmstxt-refresh');

// Hourly Shopify App Pricing sync: App Pricing sends no webhooks for
// cancellations or freezes, so re-read every paying shop from the
// Partner API. Trial shops whose trial window has passed are checked too,
// so their SaaS tenant is suspended even when the merchant never returns.
// Spread at 3 shops per second (Partner API limit is 4/s, shared with
// app-load checks).
Schedule::call(function () {
    if (! config('shopify.billing_enabled')) {
        return;
    }

    $shopIds = Shop::where('is_installed', true)
        ->where('is_uninstalled', false)
        ->where(fn ($q) => $q->whereNull('license_source')->orWhere('license_source', 'app_pricing'))
        ->where(fn ($q) => $q->where('billing_state', 'active')
            ->orWhere(fn ($t) => $t->where('billing_state', 'trial')
                ->where('trial_started_at', '<=', now()->subDays((int) config('shopify.billing.trial_days', 7)))))
        ->pluck('id');

    foreach ($shopIds->values() as $i => $shopId) {
        SyncShopBillingJob::dispatch($shopId)->delay(now()->addSeconds(intdiv($i, 3)));
    }
})->hourly()->name('shopify-billing-sync')->withoutOverlapping();

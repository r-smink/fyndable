<?php

use App\Jobs\CheckRankingsJob;
use App\Jobs\SyncShopBillingJob;
use App\Models\Shop;
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
// Partner API. Spread at 3 shops per second (Partner API limit is 4/s,
// shared with app-load checks).
Schedule::call(function () {
    if (! config('shopify.billing_enabled')) {
        return;
    }

    $shopIds = Shop::where('is_installed', true)
        ->where('is_uninstalled', false)
        ->where(fn ($q) => $q->whereNull('license_source')->orWhere('license_source', 'app_pricing'))
        ->where('billing_state', 'active')
        ->pluck('id');

    foreach ($shopIds->values() as $i => $shopId) {
        SyncShopBillingJob::dispatch($shopId)->delay(now()->addSeconds(intdiv($i, 3)));
    }
})->hourly()->name('shopify-billing-sync')->withoutOverlapping();

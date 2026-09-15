<?php

use App\Jobs\CheckRankingsJob;
use App\Models\Shop;
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

// Daily llms.txt cache refresh (prevents stale cache from building up)
Schedule::call(function () {
    $shops = Shop::where('is_installed', true)
        ->where('is_uninstalled', false)
        ->get();

    foreach ($shops as $shop) {
        $generator = app(\App\Services\LlmsTxtGenerator::class);
        $generator->invalidate($shop);
    }
})->dailyAt('03:00')->name('daily-llmstxt-refresh');

<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SearchConsoleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncSearchConsoleJob (1.5): refreshes the 28-day Search Console
 * snapshot of one shop (daily scheduler + after choosing a property).
 */
class SyncSearchConsoleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 600;

    public int $timeout = 300;

    public function __construct(public int $shopId) {}

    public function handle(SearchConsoleService $gsc, LicenseService $license): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->is_installed || ! $license->canUse($shop, 'search_console')) {
            return;
        }

        $gsc->sync($shop);
    }
}

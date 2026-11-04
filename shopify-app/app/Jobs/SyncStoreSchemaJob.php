<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\StoreSchemaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncStoreSchemaJob (1.4)
 *
 * Re-publishes the app embed config daily, so a plan change (downgrade,
 * cancellation) also switches structured data that is no longer included
 * off in the storefront.
 */
class SyncStoreSchemaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 300;

    public function __construct(public int $shopId) {}

    public function handle(StoreSchemaService $schema): void
    {
        $shop = Shop::find($this->shopId);
        if ($shop && $shop->is_installed && $shop->hasAccessToken()) {
            $schema->publish($shop);
        }
    }
}

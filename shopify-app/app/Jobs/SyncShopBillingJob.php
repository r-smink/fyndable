<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\ShopifyBillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SyncShopBillingJob
 *
 * Re-reads one shop's Shopify App Pricing subscription from the Partner API
 * and syncs changes to the SaaS. Shopify App Pricing sends no webhooks for
 * cancellations or freezes, so the hourly scheduler (routes/console.php)
 * dispatches this for every installed shop on App Pricing. Also used for the
 * uninstall sync, so the webhook can answer within Shopify's 5 seconds.
 */
class SyncShopBillingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $shopId,
        public bool $uninstalled = false
    ) {}

    public function handle(ShopifyBillingService $billing): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop) {
            return;
        }

        if ($this->uninstalled) {
            $billing->handleUninstall($shop);

            return;
        }

        if (! $shop->is_installed || $shop->is_uninstalled) {
            return;
        }

        $billing->refresh($shop, true);
    }
}

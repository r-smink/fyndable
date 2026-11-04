<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\RedirectManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * TrackHandleChangeJob (1.2)
 *
 * Runs outside the webhook request (Shopify expects an answer within 5
 * seconds): remembers product/collection handles and creates a redirect
 * when a handle changes or the item is deleted.
 */
class TrackHandleChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $shopId,
        public string $type,
        public string $resourceId,
        public ?string $handle = null,
        public ?string $title = null,
        public bool $deleted = false
    ) {}

    public function handle(RedirectManager $manager, LicenseService $license): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->hasAccessToken()) {
            return;
        }

        // Queued work bypasses the shopify.tier route middleware.
        if (! $license->canUse($shop, 'redirects')) {
            return;
        }

        $this->deleted
            ? $manager->resourceDeleted($shop, $this->type, $this->resourceId)
            : $manager->resourceUpdated($shop, $this->type, $this->resourceId, (string) $this->handle, $this->title);
    }
}

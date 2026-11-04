<?php

namespace App\Jobs;

use App\Models\ProductFeed;
use App\Models\Shop;
use App\Services\FeedBuilder;
use App\Services\LicenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * BuildProductFeedJob (2.1): rebuilds the product feeds and AI-ready
 * scores of one shop (daily scheduler, manual rebuild, enabling the feed).
 */
class BuildProductFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $shopId) {}

    public function handle(FeedBuilder $builder, LicenseService $license): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->is_installed || ! $shop->hasAccessToken()) {
            return;
        }
        $feed = ProductFeed::forShop($shop);
        if (! $license->canUse($shop, 'ai_readiness')) {
            $feed->update(['status' => 'failed', 'error' => 'tier_too_low']);

            return;
        }

        $builder->build($shop, $feed);
    }

    public function failed(Throwable $e): void
    {
        ProductFeed::where('shop_id', $this->shopId)->update(['status' => 'failed', 'error' => 'job_failed']);
    }
}

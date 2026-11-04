<?php

namespace App\Jobs;

use App\Models\ActivityEvent;
use App\Models\Shop;
use App\Services\BulkOptimizer;
use App\Services\LicenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BulkOptimizeJob (1.3)
 *
 * Runs one bulk optimization in the background: products, collections,
 * pages or articles; AI or template mode (BulkOptimizer). Progress lives in
 * the cache under bulk:{shop_id} so the dashboard can poll it, and the
 * cancel endpoint can stop the run between items. One run per shop.
 */
class BulkOptimizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /** Bulk action => activity kind for the reports (1.7). */
    private const ACTIVITY = ['meta_title' => 'meta', 'meta' => 'meta', 'titles' => 'title', 'alt_text' => 'alt_text', 'descriptions' => 'description'];

    /** Failed items listed in the progress (title + reason). */
    private const MAX_ERRORS = 25;

    /**
     * @param  array<int,array>  $items  BulkOptimizer::select() snapshots
     */
    public function __construct(
        public int $shopId,
        public string $type,
        public string $action,
        public string $mode,
        public ?string $template,
        public array $items
    ) {}

    public static function cacheKey(int $shopId): string
    {
        return "bulk:{$shopId}";
    }

    public function handle(BulkOptimizer $optimizer, LicenseService $license): void
    {
        $key = self::cacheKey($this->shopId);
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->hasAccessToken()) {
            $this->finish('failed', ['error' => 'shop_not_ready']);

            return;
        }

        // Queued work bypasses the shopify.tier route middleware: re-check the
        // tier, the plan may have been downgraded or cancelled since dispatch.
        if (! $license->canUse($shop, 'bulk_optimizer')) {
            $this->finish('failed', ['error' => 'tier_too_low']);

            return;
        }

        if ((Cache::get($key)['status'] ?? null) === 'cancelled') {
            return;
        }

        $progress = array_merge(Cache::get($key, []), ['status' => 'running', 'processed' => 0, 'succeeded' => 0, 'failed' => 0, 'errors' => []]);
        Cache::put($key, $progress, now()->addDay());

        $shopName = (string) ($shop->shop_name ?: str_replace('.myshopify.com', '', $shop->shop_domain));
        $pause = $this->mode === 'ai' ? 300_000 : 150_000;

        foreach ($this->items as $item) {
            if ((Cache::get($key)['status'] ?? null) === 'cancelled') {
                $this->finish('cancelled', $progress);

                return;
            }

            try {
                $error = $optimizer->process($shop, $item, $this->action, $this->mode, $this->template, $shopName);
            } catch (Throwable $e) {
                Log::error('BulkOptimizeJob: item failed', ['shop_id' => $this->shopId, 'item' => $item['id'] ?? null, 'error' => $e->getMessage()]);
                $error = 'exception';
            }

            $progress['processed']++;
            if ($error === null) {
                $progress['succeeded']++;
                ActivityEvent::record($this->shopId, self::ACTIVITY[$this->action] ?? $this->action);
            } else {
                $progress['failed']++;
                if (count($progress['errors']) < self::MAX_ERRORS) {
                    $progress['errors'][] = ['title' => mb_substr((string) ($item['title'] ?? ''), 0, 120), 'error' => mb_substr($error, 0, 200)];
                }
            }
            $progress['updated_at'] = now()->toIso8601String();

            // A cancel that arrived while this item was processed must not be
            // overwritten by the progress write.
            if ((Cache::get($key)['status'] ?? null) === 'cancelled') {
                $this->finish('cancelled', $progress);

                return;
            }
            Cache::put($key, $progress, now()->addDay());

            // Stay well under Shopify's GraphQL rate limit.
            usleep(app()->runningUnitTests() ? 0 : $pause);
        }

        $this->finish('completed', $progress);
    }

    public function failed(Throwable $e): void
    {
        $this->finish('failed', ['error' => 'job_failed']);
        Log::error('BulkOptimizeJob failed', ['shop_id' => $this->shopId, 'error' => $e->getMessage()]);
    }

    private function finish(string $status, array $progress): void
    {
        $key = self::cacheKey($this->shopId);
        Cache::put($key, array_merge(Cache::get($key, []), $progress, [
            'status' => $status,
            'finished_at' => now()->toIso8601String(),
        ]), now()->addDay());

        Log::info('BulkOptimizeJob: '.$status, ['shop_id' => $this->shopId, 'action' => $this->action, 'type' => $this->type, 'mode' => $this->mode]);
    }
}

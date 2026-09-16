<?php

namespace App\Jobs;

use App\Models\RankHistory;
use App\Models\Shop;
use App\Models\TrackedKeyword;
use App\Services\SaasProxyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CheckRankingsJob
 *
 * Checks Google SERP rankings for all tracked keywords of a shop.
 * Uses the SaaS dashboard SERP proxy (/serp/rank-check).
 *
 * Scheduled daily via the console kernel.
 */
class CheckRankingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        private int $shopId
    ) {}

    public function handle(): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->hasLicense()) {
            return;
        }

        $saas = app(SaasProxyClient::class);
        $keywords = TrackedKeyword::where('shop_id', $shop->id)->get();

        if ($keywords->isEmpty()) {
            return;
        }

        Log::info('CheckRankingsJob: starting', [
            'shop_id' => $shop->id,
            'keyword_count' => $keywords->count(),
        ]);

        foreach ($keywords as $tracked) {
            try {
                $targetUrl = $tracked->url ?: "https://{$shop->shop_domain}";

                $result = $saas->serpRankCheck(
                    $shop->license_key,
                    $shop->tenant_key,
                    $tracked->keyword,
                    $targetUrl,
                    $tracked->country,
                    $tracked->language
                );

                if (isset($result['error'])) {
                    Log::warning('CheckRankingsJob: rank check failed', [
                        'keyword' => $tracked->keyword,
                        'target_url' => $targetUrl,
                        'error' => $result['error'],
                        'status' => $result['status'] ?? null,
                        'message' => $result['message'] ?? null,
                    ]);

                    continue;
                }

                $position = $result['position'] ?? null;
                $resultUrl = $result['result_url'] ?? null;
                $serpFeatures = $result['serp_features'] ?? null;

                if ($position !== null) {
                    // Record history
                    RankHistory::create([
                        'tracked_keyword_id' => $tracked->id,
                        'position' => $position,
                        'search_engine' => 'google',
                        'result_url' => $resultUrl,
                        'serp_features' => $serpFeatures,
                        'checked_at' => now(),
                    ]);

                    // Update tracked keyword
                    $tracked->last_position = $position;
                    $tracked->last_checked_at = now();
                    if ($tracked->best_position === null || $position < $tracked->best_position) {
                        $tracked->best_position = $position;
                    }
                    $tracked->save();
                }

                // Rate limit between keywords
                usleep(1000000); // 1 second
            } catch (\Exception $e) {
                Log::error('CheckRankingsJob: exception', [
                    'keyword' => $tracked->keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CheckRankingsJob: completed', ['shop_id' => $shop->id]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\RedirectManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * ImportRedirectsJob (1.2): creates or repoints redirects from a CSV
 * import. Progress lives in the cache (redirect_import:{shop_id}).
 */
class ImportRedirectsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array<int, array{path:string, target:string}>  $rows
     */
    public function __construct(public int $shopId, public array $rows) {}

    public static function cacheKey(int $shopId): string
    {
        return "redirect_import:{$shopId}";
    }

    public function handle(RedirectManager $manager): void
    {
        $shop = Shop::find($this->shopId);
        $key = self::cacheKey($this->shopId);
        if (! $shop) {
            return;
        }

        $progress = ['status' => 'running', 'total' => count($this->rows), 'processed' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'errors' => []];
        foreach ($this->rows as $row) {
            $result = $manager->redirect($shop, $row['path'], $row['target'], 'import');
            $bucket = match ($result['status']) {
                'created' => 'created',
                'updated' => 'updated',
                'exists' => 'unchanged',
                default => 'failed',
            };
            $progress[$bucket]++;
            if ($bucket === 'failed' && count($progress['errors']) < 20) {
                $progress['errors'][] = ['path' => $row['path'], 'message' => $result['message'] ?? 'failed'];
            }
            $progress['processed']++;
            if ($progress['processed'] % 10 === 0) {
                Cache::put($key, $progress, now()->addDay());
            }
        }

        $progress['status'] = 'completed';
        Cache::put($key, $progress, now()->addDay());
    }

    public function failed(\Throwable $e): void
    {
        $progress = Cache::get(self::cacheKey($this->shopId), []);
        Cache::put(self::cacheKey($this->shopId), array_merge($progress, ['status' => 'failed']), now()->addDay());
    }
}

<?php

namespace App\Jobs;

use App\Models\ActivityEvent;
use App\Models\Shop;
use App\Services\AiTranslator;
use App\Services\LicenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * TranslateJob (2.3): translates the missing/outdated fields of a list of
 * resources into one language and registers them. Progress in the cache
 * (translate:{shop_id}), stoppable between resources, one run per shop.
 */
class TranslateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    /**
     * @param  array<int, array{resource_id:string, title:string}>  $resources
     */
    public function __construct(
        public int $shopId,
        public string $type,
        public string $locale,
        public string $languageName,
        public array $resources
    ) {}

    public static function cacheKey(int $shopId): string
    {
        return "translate:{$shopId}";
    }

    public function handle(AiTranslator $translator, LicenseService $license): void
    {
        $key = self::cacheKey($this->shopId);
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->hasAccessToken() || ! $license->canUse($shop, 'translations')) {
            $this->finish('failed', ['error' => $shop ? 'tier_too_low' : 'shop_not_ready']);

            return;
        }
        if ((Cache::get($key)['status'] ?? null) === 'cancelled') {
            return;
        }

        $progress = array_merge(Cache::get($key, []), ['status' => 'running', 'processed' => 0, 'succeeded' => 0, 'failed' => 0, 'fields' => 0, 'errors' => []]);
        Cache::put($key, $progress, now()->addDay());

        foreach ($this->resources as $resource) {
            if ((Cache::get($key)['status'] ?? null) === 'cancelled') {
                $this->finish('cancelled', $progress);

                return;
            }

            try {
                $result = $translator->translate($shop, $this->type, $resource['resource_id'], $this->locale, $this->languageName);
                $error = $result['error'] ?? ($result['entries'] === [] ? null : $translator->register($shop, $resource['resource_id'], $this->locale, $result['entries']));
            } catch (Throwable $e) {
                $error = 'exception';
            }

            $progress['processed']++;
            if ($error === null) {
                $progress['succeeded']++;
                $progress['fields'] += count($result['entries'] ?? []);
                ActivityEvent::record($this->shopId, 'translation', count($result['entries'] ?? []));
            } else {
                $progress['failed']++;
                if (count($progress['errors']) < 25) {
                    $progress['errors'][] = ['title' => mb_substr((string) $resource['title'], 0, 120), 'error' => mb_substr((string) $error, 0, 200)];
                }
            }

            if ((Cache::get($key)['status'] ?? null) === 'cancelled') {
                $this->finish('cancelled', $progress);

                return;
            }
            Cache::put($key, $progress, now()->addDay());
        }

        $this->finish('completed', $progress);
    }

    public function failed(Throwable $e): void
    {
        $this->finish('failed', ['error' => 'job_failed']);
    }

    private function finish(string $status, array $progress): void
    {
        $key = self::cacheKey($this->shopId);
        Cache::put($key, array_merge(Cache::get($key, []), $progress, ['status' => $status, 'finished_at' => now()->toIso8601String()]), now()->addDay());
    }
}

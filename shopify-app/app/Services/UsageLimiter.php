<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * UsageLimiter
 *
 * Monthly Shopify-only usage caps (GEO scans, blog articles) from
 * config/tiers.php `limits`, counted per shop per calendar month in the
 * shop_usage table.
 */
class UsageLimiter
{
    public function __construct(private LicenseService $license) {}

    /**
     * Monthly limit for a metric (null = unlimited).
     */
    public function limit(Shop $shop, string $metric): ?int
    {
        $limits = config("tiers.limits.{$metric}", []);
        $tier = $this->license->effectiveTier($shop);

        return array_key_exists($tier, $limits) ? $limits[$tier] : 0;
    }

    public function used(Shop $shop, string $metric): int
    {
        return (int) DB::table('shop_usage')
            ->where('shop_id', $shop->id)
            ->where('metric', $metric)
            ->where('period', $this->period())
            ->value('count');
    }

    /**
     * Error payload when the monthly limit is reached, null when allowed.
     */
    public function guard(Shop $shop, string $metric): ?array
    {
        $limit = $this->limit($shop, $metric);
        if ($limit === null) {
            return null;
        }

        $used = $this->used($shop, $metric);
        if ($used < $limit) {
            return null;
        }

        return [
            'error' => 'limit_reached',
            'metric' => $metric,
            'used' => $used,
            'limit' => $limit,
            'message' => "Monthly limit reached ({$used}/{$limit}). Upgrade your plan or wait until next month.",
        ];
    }

    public function increment(Shop $shop, string $metric): void
    {
        $key = ['shop_id' => $shop->id, 'metric' => $metric, 'period' => $this->period()];

        DB::table('shop_usage')->insertOrIgnore($key + ['count' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_usage')->where($key)->update([
            'count' => DB::raw('count + 1'),
            'updated_at' => now(),
        ]);
    }

    /**
     * Usage + limit per metric for the dashboard.
     *
     * @return array<string, array{used: int, limit: ?int}>
     */
    public function summary(Shop $shop): array
    {
        $summary = [];
        foreach (array_keys(config('tiers.limits', [])) as $metric) {
            $summary[$metric] = ['used' => $this->used($shop, $metric), 'limit' => $this->limit($shop, $metric)];
        }

        return $summary;
    }

    private function period(): string
    {
        return now()->format('Y-m');
    }
}

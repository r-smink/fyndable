<?php

namespace App\Jobs;

use App\Models\SeoAudit;
use App\Models\SeoAuditItem;
use App\Services\LicenseService;
use App\Services\SeoAuditor;
use App\Services\ShopifyContentFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SeoAuditJob
 *
 * Store-wide SEO audit (1.1): fetches active products, collections and
 * published pages and articles, scores them with SeoAuditor and stores the
 * result per item. Progress is written to the seo_audits row so the
 * dashboard can poll it.
 */
class SeoAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Audits kept per shop (older ones are deleted). */
    public const KEEP = 5;

    public const TYPES = ['product', 'collection', 'page', 'article'];

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $auditId) {}

    public function handle(ShopifyContentFetcher $fetcher, SeoAuditor $auditor, LicenseService $license): void
    {
        $audit = SeoAudit::find($this->auditId);
        if (! $audit || ! $audit->isActive()) {
            return;
        }

        $shop = $audit->shop;
        if (! $shop || ! $shop->hasAccessToken()) {
            $this->markFailed($audit, 'shop_not_ready');

            return;
        }

        // Queued work bypasses the shopify.tier route middleware.
        if (! $license->canUse($shop, 'seo_audit')) {
            $this->markFailed($audit, 'tier_too_low');

            return;
        }

        $audit->update(['status' => 'running', 'started_at' => now(), 'progress' => 5]);

        $limit = (int) config('shopify.audit.max_items_per_type', 1000);
        $nodes = [];
        foreach (self::TYPES as $i => $type) {
            $result = $fetcher->getAuditItems($shop, $type, $limit);
            if (isset($result['error'])) {
                $this->markFailed($audit, (string) $result['error']);

                return;
            }
            $nodes[$type] = $result;
            $audit->update(['progress' => 10 + ($i + 1) * 20]);
        }

        // Internal hosts: the myshopify domain plus the storefront domain(s).
        $hosts = [$shop->shop_domain];
        foreach ($nodes['product'] as $node) {
            $host = parse_url((string) ($node['onlineStoreUrl'] ?? ''), PHP_URL_HOST);
            if ($host && ! in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }
        $storefront = $hosts[1] ?? $shop->shop_domain;

        $items = [];
        foreach ($nodes as $type => $list) {
            foreach ($list as $node) {
                $items[] = $auditor->normalize($type, $node, $storefront);
            }
        }

        $result = $auditor->audit($items, $hosts);

        DB::transaction(function () use ($audit, $result, $shop) {
            foreach (array_chunk($result['items'], 500) as $chunk) {
                SeoAuditItem::insert(array_map(fn (array $item) => [
                    'seo_audit_id' => $audit->id,
                    'shop_id' => $shop->id,
                    'type' => $item['type'],
                    'resource_id' => mb_substr($item['resource_id'], 0, 128),
                    'title' => mb_substr($item['title'] !== '' ? $item['title'] : '-', 0, 255),
                    'url' => mb_substr($item['url'], 0, 512),
                    'score' => $item['score'],
                    'issues' => json_encode($item['issues']),
                ], $chunk));
            }

            $audit->update([
                'status' => 'completed',
                'progress' => 100,
                'score' => $result['score'],
                'items_count' => count($result['items']),
                'summary' => $result['summary'],
                'finished_at' => now(),
            ]);
        });

        $this->prune($audit->shop_id);
    }

    public function failed(Throwable $e): void
    {
        $audit = SeoAudit::find($this->auditId);
        if ($audit && $audit->isActive()) {
            $this->markFailed($audit, 'job_failed');
        }
        Log::error('SeoAuditJob failed', ['audit_id' => $this->auditId, 'error' => $e->getMessage()]);
    }

    private function markFailed(SeoAudit $audit, string $error): void
    {
        $audit->update(['status' => 'failed', 'error' => mb_substr($error, 0, 255), 'finished_at' => now()]);
    }

    private function prune(int $shopId): void
    {
        $old = SeoAudit::where('shop_id', $shopId)->orderByDesc('id')->skip(self::KEEP)->take(100)->pluck('id');
        if ($old->isNotEmpty()) {
            SeoAuditItem::whereIn('seo_audit_id', $old)->delete();
            SeoAudit::whereIn('id', $old)->delete();
        }
    }
}

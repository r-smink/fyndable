<?php

namespace App\Http\Controllers;

use App\Jobs\SeoAuditJob;
use App\Models\SeoAudit;
use App\Models\SeoAuditItem;
use App\Models\Shop;
use App\Services\SeoAuditor;
use Illuminate\Http\Request;

/**
 * SeoAuditController (1.1)
 *
 * POST /api/audit/start   - queue a store-wide audit (one at a time)
 * GET  /api/audit/latest  - newest audit (status/progress) + last completed result
 * GET  /api/audit/items   - items of the last completed audit, worst first
 *                           (?issue=meta_desc_missing&type=product&page=1)
 */
class SeoAuditController extends Controller
{
    private const PER_PAGE = 25;

    /** A queued/running audit older than this is considered stuck. */
    private const STALE_MINUTES = 30;

    public function start(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $active = SeoAudit::where('shop_id', $shop->id)->whereIn('status', ['queued', 'running'])->latest('id')->first();
        if ($active && $active->created_at->gt(now()->subMinutes(self::STALE_MINUTES))) {
            return ['success' => true, 'audit' => $this->auditPayload($active), 'already_running' => true];
        }
        $active?->update(['status' => 'failed', 'error' => 'stale', 'finished_at' => now()]);

        $audit = SeoAudit::create(['shop_id' => $shop->id, 'status' => 'queued']);
        SeoAuditJob::dispatch($audit->id);

        return ['success' => true, 'audit' => $this->auditPayload($audit->fresh())];
    }

    public function latest(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $newest = SeoAudit::where('shop_id', $shop->id)->latest('id')->first();
        $completed = SeoAudit::latestCompleted($shop->id);

        return [
            'success' => true,
            'current' => $newest ? $this->auditPayload($newest) : null,
            'result' => $completed ? $this->resultPayload($completed) : null,
            'issues' => array_map(fn ($meta) => $meta['severity'], SeoAuditor::ISSUES),
            'bulk_fixes' => SeoAuditor::BULK_FIXES,
        ];
    }

    public function items(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $audit = SeoAudit::latestCompleted($shop->id);
        if (! $audit) {
            return ['success' => true, 'items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        }

        $issue = (string) $request->query('issue', '');
        $type = (string) $request->query('type', '');
        $page = max(1, (int) $request->query('page', 1));

        $query = SeoAuditItem::where('seo_audit_id', $audit->id)->orderBy('score')->orderBy('id');
        if (in_array($type, SeoAuditJob::TYPES, true)) {
            $query->where('type', $type);
        }
        if (isset(SeoAuditor::ISSUES[$issue])) {
            // Issue codes are unique quoted strings in the JSON column (MySQL
            // normalizes JSON spacing, so match the quoted code only).
            $query->where('issues', 'like', '%"'.$issue.'"%');
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, self::PER_PAGE)->get()->map(fn (SeoAuditItem $item) => [
            'type' => $item->type,
            'resource_id' => $item->resource_id,
            'title' => $item->title,
            'url' => $item->url,
            'score' => $item->score,
            'issues' => $item->issues,
        ])->all();

        return [
            'success' => true,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / self::PER_PAGE),
        ];
    }

    private function auditPayload(SeoAudit $audit): array
    {
        return [
            'id' => $audit->id,
            'status' => $audit->status,
            'progress' => $audit->progress,
            'error' => $audit->error,
            'created_at' => $audit->created_at?->toIso8601String(),
        ];
    }

    private function resultPayload(SeoAudit $audit): array
    {
        $summary = $audit->summary ?? [];
        $severity = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($summary as $code => $count) {
            $level = SeoAuditor::ISSUES[$code]['severity'] ?? 'info';
            $severity[$level] += $count;
        }
        // Most severe issue groups first, then by number of items.
        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        uksort($summary, fn ($a, $b) => [$rank[SeoAuditor::ISSUES[$a]['severity'] ?? 'info'], -$summary[$a]]
            <=> [$rank[SeoAuditor::ISSUES[$b]['severity'] ?? 'info'], -$summary[$b]]);

        return [
            'id' => $audit->id,
            'score' => $audit->score,
            'items_count' => $audit->items_count,
            'summary' => $summary,
            'severity' => $severity,
            'finished_at' => $audit->finished_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\GoogleConnection;
use App\Models\RedirectEvent;
use App\Models\SeoAudit;
use App\Models\SeoReport;
use App\Models\Shop;
use App\Models\TrackedKeyword;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * ReportBuilder (1.7)
 *
 * Weekly report figures from data the app already has: SEO audit score,
 * rank tracker movement, Search Console totals and opportunities, the GEO
 * scan score and what Fyndable changed in the store (activity_events,
 * redirect_events). No AI calls; one optional SaaS call (GEO scans).
 */
class ReportBuilder
{
    public const DAYS = 7;

    public function __construct(
        private LicenseService $license,
        private SaasProxyClient $saas
    ) {}

    /**
     * Build and store a report for the 7 days up to $end.
     */
    public function create(Shop $shop, CarbonInterface $end): SeoReport
    {
        // Exactly 7 days back, so consecutive weekly reports join up.
        $end = $end->copy();
        $start = $end->copy()->subDays(self::DAYS);
        $previous = SeoReport::where('shop_id', $shop->id)->latest('id')->first();

        return SeoReport::create([
            'shop_id' => $shop->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'metrics' => $this->metrics($shop, $start, $end, $previous?->metrics),
        ]);
    }

    public function metrics(Shop $shop, CarbonInterface $start, CarbonInterface $end, ?array $previous = null): array
    {
        return [
            'seo' => $this->seo($shop, $start),
            'rankings' => $this->license->canUse($shop, 'rank_tracker') ? $this->rankings($shop, $start) : null,
            'search_console' => $this->searchConsole($shop, $previous),
            'geo' => $this->license->canUse($shop, 'geo_scan') ? $this->geo($shop) : null,
            'activity' => $this->activity($shop, $start, $end),
        ];
    }

    private function seo(Shop $shop, CarbonInterface $start): ?array
    {
        $latest = SeoAudit::latestCompleted($shop->id);
        if (! $latest) {
            return null;
        }
        $before = SeoAudit::where('shop_id', $shop->id)->where('status', 'completed')
            ->where('id', '<', $latest->id)->where('finished_at', '<', $start)->latest('id')->first();

        return [
            'score' => $latest->score,
            'previous' => $before?->score,
            'critical' => array_sum(array_intersect_key($latest->summary ?? [], array_filter(SeoAuditor::ISSUES, fn ($i) => $i['severity'] === 'critical'))),
            'checked_at' => $latest->finished_at?->toIso8601String(),
        ];
    }

    /**
     * Position now vs. the last check before the week started.
     */
    private function rankings(Shop $shop, CarbonInterface $start): array
    {
        $keywords = TrackedKeyword::where('shop_id', $shop->id)->get();
        $ranked = fn ($p) => $p !== null && $p > 0 && $p <= 100;
        $movers = [];
        $improved = 0;
        $declined = 0;

        foreach ($keywords as $keyword) {
            $before = DB::table('rank_history')->where('tracked_keyword_id', $keyword->id)
                ->where('checked_at', '<', $start)->orderByDesc('checked_at')->value('position');
            $now = $keyword->last_position;
            if (! $ranked($now) && ! $ranked($before)) {
                continue;
            }
            $from = $ranked($before) ? (int) $before : 101;
            $to = $ranked($now) ? (int) $now : 101;
            if ($to < $from) {
                $improved++;
            } elseif ($to > $from) {
                $declined++;
            }
            if ($to !== $from) {
                $movers[] = ['keyword' => $keyword->keyword, 'from' => $ranked($before) ? (int) $before : null, 'to' => $ranked($now) ? (int) $now : null, 'change' => $from - $to];
            }
        }
        usort($movers, fn ($a, $b) => abs($b['change']) <=> abs($a['change']));

        return [
            'tracked' => $keywords->count(),
            'top3' => $keywords->filter(fn ($k) => $ranked($k->last_position) && $k->last_position <= 3)->count(),
            'top10' => $keywords->filter(fn ($k) => $ranked($k->last_position) && $k->last_position <= 10)->count(),
            'improved' => $improved,
            'declined' => $declined,
            'movers' => array_slice($movers, 0, 5),
        ];
    }

    private function searchConsole(Shop $shop, ?array $previous): ?array
    {
        $connection = GoogleConnection::where('shop_id', $shop->id)->first();
        if (! $connection || ! $connection->totals || ! $this->license->canUse($shop, 'search_console')) {
            return null;
        }

        $opportunities = DB::table('gsc_query_stats')->where('shop_id', $shop->id)
            ->whereBetween('position', [8, 20])->where('impressions', '>=', 10)
            ->orderByDesc('impressions')->limit(3)->get(['query', 'position', 'impressions'])
            ->map(fn ($r) => ['query' => $r->query, 'position' => round((float) $r->position, 1), 'impressions' => (int) $r->impressions])->all();

        return [
            'clicks' => (int) $connection->totals['clicks'],
            'impressions' => (int) $connection->totals['impressions'],
            'position' => (float) $connection->totals['position'],
            'previous_clicks' => $previous['search_console']['clicks'] ?? null,
            'period_start' => $connection->period_start?->toDateString(),
            'period_end' => $connection->period_end?->toDateString(),
            'opportunities' => $opportunities,
        ];
    }

    private function geo(Shop $shop): ?array
    {
        $result = $this->saas->geoScanRecent((string) $shop->license_key, (string) $shop->tenant_key, 5);
        $scans = $result['scans'] ?? $result['data'] ?? [];
        foreach (is_array($scans) ? $scans : [] as $scan) {
            $score = $scan['score'] ?? $scan['result']['score'] ?? null;
            if (($scan['status'] ?? '') === 'completed' && is_numeric($score)) {
                return ['score' => (int) round((float) $score), 'date' => $scan['completed_at'] ?? $scan['created_at'] ?? null];
            }
        }

        return null;
    }

    private function activity(Shop $shop, CarbonInterface $start, CarbonInterface $end): array
    {
        $counts = ActivityEvent::where('shop_id', $shop->id)->whereBetween('created_at', [$start, $end])
            ->selectRaw('kind, SUM(count) as total')->groupBy('kind')->pluck('total', 'kind')
            ->map(fn ($v) => (int) $v)->all();

        $redirects = RedirectEvent::where('shop_id', $shop->id)->whereBetween('created_at', [$start, $end])->count();
        if ($redirects > 0) {
            $counts['redirect'] = $redirects;
        }
        arsort($counts);

        return ['counts' => $counts, 'total' => array_sum($counts)];
    }
}

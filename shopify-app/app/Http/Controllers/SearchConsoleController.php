<?php

namespace App\Http\Controllers;

use App\Jobs\SyncSearchConsoleJob;
use App\Models\Shop;
use App\Services\SearchConsoleService;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SearchConsoleController (1.5)
 *
 * GET  /api/gsc                - connection status, property, 28-day totals
 * POST /api/gsc/session        - one-time Google popup URL (SaaS OAuth proxy)
 * POST /api/gsc/connect        - {code} from the popup: exchange + store tokens
 * GET  /api/gsc/sites          - properties of the connected Google account
 * POST /api/gsc/site           - {site_url} choose the property, start a sync
 * POST /api/gsc/sync           - refresh now (max every 10 minutes)
 * POST /api/gsc/disconnect     - remove tokens and data
 * GET  /api/gsc/pages          - top pages (clicks)
 * GET  /api/gsc/opportunities  - "almost page 1": queries at position 8-20
 */
class SearchConsoleController extends Controller
{
    public function __construct(
        private SearchConsoleService $gsc,
        private ShopifyContentFetcher $fetcher
    ) {}

    public function show(Request $request): array
    {
        $connection = $this->gsc->connection($this->shop($request));

        return [
            'success' => true,
            'connected' => $connection !== null,
            'status' => $connection?->status,
            'site_url' => $connection?->site_url,
            'totals' => $connection?->totals,
            'period_start' => $connection?->period_start?->toDateString(),
            'period_end' => $connection?->period_end?->toDateString(),
            'synced_at' => $connection?->synced_at?->toIso8601String(),
            'sync_error' => $connection?->sync_error,
        ];
    }

    public function session(Request $request): array
    {
        return $this->gsc->session($this->shop($request)) + ['success' => true];
    }

    public function connect(Request $request): array
    {
        $shop = $this->shop($request);
        $code = (string) $request->input('code', '');
        if ($code === '' || strlen($code) > 512) {
            return ['error' => 'invalid_code'];
        }

        $error = $this->gsc->connect($shop, $code);
        if ($error !== null) {
            return ['error' => 'connect_failed', 'message' => $error];
        }

        return $this->sites($request) + ['connected' => true];
    }

    public function sites(Request $request): array
    {
        $shop = $this->shop($request);
        $connection = $this->gsc->connection($shop);
        if (! $connection) {
            return ['error' => 'not_connected'];
        }

        $sites = $this->gsc->sites($shop, $connection);
        if (isset($sites['error'])) {
            return $sites;
        }

        $details = $this->fetcher->getShopDetails($shop);
        $hosts = [parse_url((string) ($details['domain'] ?? ''), PHP_URL_HOST), $shop->shop_domain];

        return ['success' => true, 'sites' => $sites, 'suggested' => $this->gsc->suggestSite($sites, $hosts), 'site_url' => $connection->site_url];
    }

    public function selectSite(Request $request): array
    {
        $shop = $this->shop($request);
        $connection = $this->gsc->connection($shop);
        if (! $connection) {
            return ['error' => 'not_connected'];
        }

        $site = (string) $request->input('site_url', '');
        $sites = $this->gsc->sites($shop, $connection);
        if (isset($sites['error'])) {
            return $sites;
        }
        if (! in_array($site, array_column($sites, 'site_url'), true)) {
            return ['error' => 'invalid_site'];
        }

        if ($connection->site_url !== $site) {
            DB::table('gsc_page_stats')->where('shop_id', $shop->id)->delete();
            DB::table('gsc_query_stats')->where('shop_id', $shop->id)->delete();
            $connection->update(['site_url' => $site, 'totals' => null, 'synced_at' => null, 'sync_error' => null]);
        }
        SyncSearchConsoleJob::dispatch($shop->id);

        return ['success' => true, 'site_url' => $site];
    }

    public function sync(Request $request): array
    {
        $shop = $this->shop($request);
        $key = 'gsc_sync:'.$shop->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return ['error' => 'sync_too_soon', 'retry_after' => RateLimiter::availableIn($key)];
        }
        RateLimiter::hit($key, 600);
        SyncSearchConsoleJob::dispatch($shop->id);

        return ['success' => true];
    }

    public function disconnect(Request $request): array
    {
        $this->gsc->disconnect($this->shop($request));

        return ['success' => true];
    }

    public function pages(Request $request): array
    {
        $shop = $this->shop($request);
        $rows = DB::table('gsc_page_stats')->where('shop_id', $shop->id)->orderByDesc('clicks')->orderByDesc('impressions')->limit(50)->get();

        return ['success' => true, 'pages' => $rows->map(fn ($r) => $this->row($shop, $r))->all()];
    }

    public function opportunities(Request $request): array
    {
        $shop = $this->shop($request);
        $rows = DB::table('gsc_query_stats')->where('shop_id', $shop->id)
            ->whereBetween('position', [8, 20])->where('impressions', '>=', 10)
            ->orderByDesc('impressions')->limit(50)->get();

        return ['success' => true, 'opportunities' => $rows->map(fn ($r) => ['query' => $r->query] + $this->row($shop, $r))->all()];
    }

    private function row(Shop $shop, object $r): array
    {
        return [
            'page' => $r->page,
            'path' => $r->path,
            'clicks' => (int) $r->clicks,
            'impressions' => (int) $r->impressions,
            'ctr' => round((float) $r->ctr, 4),
            'position' => round((float) $r->position, 1),
            'resource' => $this->gsc->resourceForPath($shop, $r->path),
        ];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

<?php

namespace App\Services;

use App\Models\GoogleConnection;
use App\Models\ResourceHandle;
use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SearchConsoleService (1.5)
 *
 * Google Search Console for the Shopify app, reusing the SaaS Google OAuth
 * proxy (the client secret stays on the SaaS):
 *
 * - session(): one-time popup URL (SaaS /google/oauth-session); the popup
 *   posts only the authorization code back to the app.
 * - connect(): exchanges the code via the SaaS and stores the tokens
 *   encrypted; refresh via the SaaS when the access token expires.
 * - sync(): 28-day snapshot (totals, pages, query + page) into
 *   gsc_page_stats / gsc_query_stats, used for top pages, "almost page 1"
 *   opportunities, the Rank tracker and the Content optimizer.
 */
class SearchConsoleService
{
    private const API = 'https://www.googleapis.com/webmasters/v3';

    /** Search Console data lags; the window ends this many days ago. */
    private const LAG_DAYS = 3;

    public const PERIOD_DAYS = 28;

    private const ROW_LIMIT = 5000;

    public function __construct(private SaasProxyClient $saas) {}

    public function connection(Shop $shop): ?GoogleConnection
    {
        return GoogleConnection::where('shop_id', $shop->id)->first();
    }

    /**
     * @return array{url:string}|array{error:string, message?:string}
     */
    public function session(Shop $shop): array
    {
        $result = $this->saas->googleOAuthSession((string) $shop->license_key, (string) $shop->tenant_key);
        if (empty($result['success']) || empty($result['url'])) {
            return ['error' => (string) ($result['error'] ?? 'session_failed'), 'message' => $result['message'] ?? null];
        }

        return ['url' => (string) $result['url']];
    }

    /**
     * Exchange the popup's authorization code and store the tokens.
     */
    public function connect(Shop $shop, string $code): ?string
    {
        $result = $this->saas->googleExchange((string) $shop->license_key, (string) $shop->tenant_key, $code);
        $tokens = $result['tokens'] ?? null;
        if (empty($result['success']) || ! is_array($tokens) || empty($tokens['access_token'])) {
            return (string) ($result['message'] ?? $result['error'] ?? 'exchange_failed');
        }

        $existing = $this->connection($shop);
        $refresh = $tokens['refresh_token'] ?? $existing?->refresh_token;
        if (! $refresh) {
            // Google only returns a refresh token on first consent.
            return 'no_refresh_token';
        }

        GoogleConnection::updateOrCreate(['shop_id' => $shop->id], [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $refresh,
            'token_expires_at' => now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600)) - 60),
            'scopes' => mb_substr((string) ($tokens['scope'] ?? ''), 0, 500),
            'status' => 'connected',
            'sync_error' => null,
        ]);

        return null;
    }

    public function disconnect(Shop $shop): void
    {
        DB::table('gsc_page_stats')->where('shop_id', $shop->id)->delete();
        DB::table('gsc_query_stats')->where('shop_id', $shop->id)->delete();
        GoogleConnection::where('shop_id', $shop->id)->delete();
    }

    /**
     * Valid access token, refreshed through the SaaS when needed. A revoked
     * grant marks the connection as needs_reconnect.
     */
    public function accessToken(Shop $shop, GoogleConnection $connection): ?string
    {
        if ($connection->access_token && $connection->token_expires_at && $connection->token_expires_at->isFuture()) {
            return $connection->access_token;
        }

        $result = $this->saas->googleRefresh((string) $shop->license_key, (string) $shop->tenant_key, (string) $connection->refresh_token);
        $tokens = $result['tokens'] ?? null;
        if (empty($result['success']) || ! is_array($tokens) || empty($tokens['access_token'])) {
            $revoked = str_contains(json_encode($result) ?: '', 'invalid_grant');
            $connection->update($revoked
                ? ['status' => 'needs_reconnect', 'access_token' => null, 'sync_error' => 'google_access_revoked']
                : ['sync_error' => 'token_refresh_failed']);

            return null;
        }

        $connection->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600)) - 60),
            'refresh_token' => $tokens['refresh_token'] ?? $connection->refresh_token,
        ]);

        return $tokens['access_token'];
    }

    /**
     * Search Console properties the Google account can read.
     *
     * @return array<int, array{site_url:string, permission:string}>|array{error:string}
     */
    public function sites(Shop $shop, GoogleConnection $connection): array
    {
        $response = $this->get($shop, $connection, '/sites');
        if (isset($response['error'])) {
            return $response;
        }

        $sites = [];
        foreach ($response['siteEntry'] ?? [] as $entry) {
            if (($entry['permissionLevel'] ?? '') !== 'siteUnverifiedUser') {
                $sites[] = ['site_url' => (string) $entry['siteUrl'], 'permission' => (string) ($entry['permissionLevel'] ?? '')];
            }
        }

        return $sites;
    }

    /**
     * The property that matches the store: a domain property first, then
     * the https URL property of a storefront host.
     */
    public function suggestSite(array $sites, array $hosts): ?string
    {
        $hosts = array_map(fn ($h) => preg_replace('/^www\./', '', strtolower($h)), array_filter($hosts));
        foreach (['sc-domain:', 'https://'] as $prefix) {
            foreach ($sites as $site) {
                $url = strtolower($site['site_url']);
                if (! str_starts_with($url, $prefix)) {
                    continue;
                }
                $host = $prefix === 'sc-domain:' ? substr($url, 10) : preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
                if (in_array($host, $hosts, true)) {
                    return $site['site_url'];
                }
            }
        }

        return null;
    }

    /**
     * Refresh the 28-day snapshot for the selected property.
     */
    public function sync(Shop $shop): ?string
    {
        $connection = $this->connection($shop);
        if (! $connection || ! $connection->site_url || $connection->status !== 'connected') {
            return 'not_connected';
        }

        $end = now()->subDays(self::LAG_DAYS)->toDateString();
        $start = now()->subDays(self::LAG_DAYS + self::PERIOD_DAYS - 1)->toDateString();
        $base = ['startDate' => $start, 'endDate' => $end, 'dataState' => 'final'];

        $totals = $this->query($shop, $connection, $base);
        $pages = $this->query($shop, $connection, $base + ['dimensions' => ['page'], 'rowLimit' => self::ROW_LIMIT]);
        $queries = $this->query($shop, $connection, $base + ['dimensions' => ['query', 'page'], 'rowLimit' => self::ROW_LIMIT]);
        foreach ([$totals, $pages, $queries] as $result) {
            if (isset($result['error'])) {
                $connection->update(['sync_error' => mb_substr((string) $result['error'], 0, 255)]);

                return (string) $result['error'];
            }
        }

        $row = $totals[0] ?? [];
        DB::transaction(function () use ($shop, $pages, $queries) {
            DB::table('gsc_page_stats')->where('shop_id', $shop->id)->delete();
            DB::table('gsc_query_stats')->where('shop_id', $shop->id)->delete();
            foreach (array_chunk($pages, 500) as $chunk) {
                DB::table('gsc_page_stats')->insert(array_map(fn ($r) => $this->statRow($shop, $r['keys'][0], $r), $chunk));
            }
            foreach (array_chunk($queries, 500) as $chunk) {
                DB::table('gsc_query_stats')->insert(array_map(fn ($r) => ['query' => mb_substr((string) $r['keys'][0], 0, 255)] + $this->statRow($shop, $r['keys'][1], $r), $chunk));
            }
        });

        $connection->update([
            'totals' => [
                'clicks' => (int) ($row['clicks'] ?? 0),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'ctr' => round((float) ($row['ctr'] ?? 0), 4),
                'position' => round((float) ($row['position'] ?? 0), 1),
            ],
            'period_start' => $start,
            'period_end' => $end,
            'synced_at' => now(),
            'sync_error' => null,
        ]);

        // Cost tracking on the SaaS (same as the WordPress client).
        $this->saas->googleReportUsage((string) $shop->license_key, (string) $shop->tenant_key, 'gsc', 3);

        return null;
    }

    /**
     * Search Console numbers for one storefront path (Content optimizer).
     */
    public function pageStats(Shop $shop, string $path): ?array
    {
        $row = DB::table('gsc_page_stats')->where('shop_id', $shop->id)->where('path', $this->normalizePath($path))->first();

        return $row ? ['clicks' => $row->clicks, 'impressions' => $row->impressions, 'ctr' => round($row->ctr, 4), 'position' => round($row->position, 1)] : null;
    }

    /**
     * Product/collection behind a storefront path (for edit links), from the
     * handles the redirect manager keeps (1.2).
     *
     * @return array{type:string, id:string}|null
     */
    public function resourceForPath(Shop $shop, string $path): ?array
    {
        if (! preg_match('#^/(products|collections)/([^/]+)$#', $this->normalizePath($path), $m)) {
            return null;
        }
        $type = $m[1] === 'products' ? 'product' : 'collection';
        $id = ResourceHandle::where(['shop_id' => $shop->id, 'type' => $type, 'handle' => $m[2]])->value('resource_id');

        return $id ? ['type' => $type, 'id' => (string) $id] : null;
    }

    public function normalizePath(string $url): string
    {
        $path = strtolower(rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? '/')));
        // Locale prefixes and collection-scoped product URLs map to the item.
        $path = preg_replace('#^/[a-z]{2}(-[a-z]{2})?(?=/(products|collections|pages|blogs)/)#', '', $path);
        $path = preg_replace('#^/collections/[^/]+(?=/products/)#', '', $path);

        return mb_substr(rtrim($path, '/') ?: '/', 0, 512);
    }

    private function statRow(Shop $shop, string $page, array $row): array
    {
        return [
            'shop_id' => $shop->id,
            'page' => mb_substr($page, 0, 1024),
            'path' => $this->normalizePath($page),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ];
    }

    /**
     * @return array<int,array>|array{error:string}
     */
    private function query(Shop $shop, GoogleConnection $connection, array $body): array
    {
        $response = $this->call($shop, $connection, 'post', '/sites/'.rawurlencode((string) $connection->site_url).'/searchAnalytics/query', $body);

        return isset($response['error']) ? $response : ($response['rows'] ?? []);
    }

    private function get(Shop $shop, GoogleConnection $connection, string $path): array
    {
        return $this->call($shop, $connection, 'get', $path);
    }

    private function call(Shop $shop, GoogleConnection $connection, string $method, string $path, array $body = []): array
    {
        $token = $this->accessToken($shop, $connection);
        if ($token === null) {
            return ['error' => $connection->status === 'needs_reconnect' ? 'google_access_revoked' : 'token_refresh_failed'];
        }

        try {
            $request = Http::timeout(30)->withToken($token)->acceptJson();
            $response = $method === 'post' ? $request->post(self::API.$path, $body) : $request->get(self::API.$path);
        } catch (ConnectionException) {
            return ['error' => 'google_unreachable'];
        }

        if ($response->status() === 403) {
            return ['error' => 'no_access_to_property'];
        }
        if ($response->failed()) {
            Log::warning('SearchConsoleService: Google API error', ['shop' => $shop->shop_domain, 'status' => $response->status()]);

            return ['error' => 'google_http_'.$response->status()];
        }

        return $response->json() ?? [];
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\ImportRedirectsJob;
use App\Models\NotFoundUrl;
use App\Models\RedirectEvent;
use App\Models\Shop;
use App\Services\RedirectManager;
use App\Services\ShopifyUrlRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * RedirectController (1.2 redirect manager + 404 monitor)
 *
 * GET    /api/redirects                  - Shopify redirects (?search=&after=)
 * POST   /api/redirects                  - create {path, target}
 * POST   /api/redirects/{id}             - update {path, target}
 * DELETE /api/redirects/{id}             - delete
 * GET    /api/redirects/export           - all redirects as CSV text
 * POST   /api/redirects/import           - {csv} queue an import
 * GET    /api/redirects/import/status    - import progress
 * GET    /api/redirects/not-found        - open 404s, most hits first
 * POST   /api/redirects/not-found/{id}/fix    - {target} redirect a 404
 * POST   /api/redirects/not-found/{id}/ignore - hide a 404
 * GET    /api/redirects/settings         - automatic redirect settings + log
 * POST   /api/redirects/settings
 */
class RedirectController extends Controller
{
    public const MAX_IMPORT_ROWS = 5000;

    private const MAX_EXPORT = 20000;

    public function __construct(
        private ShopifyUrlRedirect $redirects,
        private RedirectManager $manager
    ) {}

    public function index(Request $request): array
    {
        $shop = $this->shop($request);
        $search = trim((string) $request->query('search', ''));
        $after = $request->query('after');

        $result = $this->redirects->list($shop, $search !== '' ? $search : null, is_string($after) && $after !== '' ? $after : null, 50);

        return isset($result['error']) ? $result : ['success' => true] + $result;
    }

    public function store(Request $request): array
    {
        $shop = $this->shop($request);
        [$path, $target, $error] = $this->validatedPair($request, $shop);
        if ($error) {
            return $error;
        }

        $result = $this->manager->redirect($shop, $path, $target, 'manual');

        return $result['status'] === 'error'
            ? ['error' => 'redirect_failed', 'message' => $result['message'] ?? '']
            : ['success' => true, 'redirect' => $result];
    }

    public function update(Request $request, string $id): array
    {
        $shop = $this->shop($request);
        [$path, $target, $error] = $this->validatedPair($request, $shop);
        if ($error) {
            return $error;
        }

        $result = $this->redirects->update($shop, ShopifyUrlRedirect::gid($id), $path, $target);

        return $result['status'] === 'error'
            ? ['error' => 'redirect_failed', 'message' => $result['message'] ?? '']
            : ['success' => true, 'redirect' => $result];
    }

    public function destroy(Request $request, string $id): array
    {
        $error = $this->redirects->delete($this->shop($request), ShopifyUrlRedirect::gid($id));

        return $error === null ? ['success' => true] : ['error' => 'delete_failed', 'message' => $error];
    }

    public function export(Request $request): array
    {
        $shop = $this->shop($request);
        $lines = ['Redirect from,Redirect to'];
        $after = null;
        $count = 0;
        do {
            $page = $this->redirects->list($shop, null, $after, 250);
            if (isset($page['error'])) {
                return $page;
            }
            foreach ($page['redirects'] as $redirect) {
                $lines[] = $this->csvCell($redirect['path']).','.$this->csvCell($redirect['target']);
                $count++;
            }
            $after = $page['next'];
        } while ($after !== null && $count < self::MAX_EXPORT);

        return ['success' => true, 'count' => $count, 'csv' => implode("\n", $lines)."\n"];
    }

    public function import(Request $request): array
    {
        $shop = $this->shop($request);
        $csv = (string) $request->input('csv', '');
        if (trim($csv) === '' || strlen($csv) > 2_000_000) {
            return ['error' => 'invalid_csv'];
        }

        $status = Cache::get(ImportRedirectsJob::cacheKey($shop->id));
        if (($status['status'] ?? null) === 'running' || ($status['status'] ?? null) === 'queued') {
            return ['error' => 'import_running'];
        }

        [$rows, $invalid] = $this->parseCsv($csv, $shop);
        if ($rows === []) {
            return ['error' => 'no_valid_rows', 'invalid' => $invalid];
        }
        if (count($rows) > self::MAX_IMPORT_ROWS) {
            return ['error' => 'too_many_rows', 'max' => self::MAX_IMPORT_ROWS];
        }

        Cache::put(ImportRedirectsJob::cacheKey($shop->id), ['status' => 'queued', 'total' => count($rows), 'processed' => 0], now()->addDay());
        ImportRedirectsJob::dispatch($shop->id, $rows);

        return ['success' => true, 'rows' => count($rows), 'invalid' => $invalid];
    }

    public function importStatus(Request $request): array
    {
        return ['success' => true, 'import' => Cache::get(ImportRedirectsJob::cacheKey($this->shop($request)->id))];
    }

    public function notFound(Request $request): array
    {
        $shop = $this->shop($request);
        $items = NotFoundUrl::where('shop_id', $shop->id)->whereNull('resolved_at')
            ->orderByDesc('hits')->orderByDesc('last_seen_at')->limit(100)->get()
            ->map(fn (NotFoundUrl $row) => [
                'id' => $row->id,
                'path' => $row->path,
                'hits' => $row->hits,
                'referrer' => $row->referrer,
                'last_seen_at' => $row->last_seen_at?->toIso8601String(),
            ])->all();

        return [
            'success' => true,
            'items' => $items,
            'open' => NotFoundUrl::where('shop_id', $shop->id)->whereNull('resolved_at')->count(),
        ];
    }

    public function fixNotFound(Request $request, int $id): array
    {
        $shop = $this->shop($request);
        $row = NotFoundUrl::where('shop_id', $shop->id)->find($id);
        if (! $row) {
            return ['error' => 'not_found'];
        }

        $target = $this->normalizeTarget((string) $request->input('target', ''), $shop);
        if ($target === null) {
            return ['error' => 'invalid_target'];
        }

        $result = $this->manager->redirect($shop, $row->path, $target, 'not_found');

        return $result['status'] === 'error'
            ? ['error' => 'redirect_failed', 'message' => $result['message'] ?? '']
            : ['success' => true, 'redirect' => $result];
    }

    public function ignoreNotFound(Request $request, int $id): array
    {
        $updated = NotFoundUrl::where('shop_id', $this->shop($request)->id)->where('id', $id)
            ->update(['resolved_at' => now(), 'resolved_target' => null]);

        return $updated ? ['success' => true] : ['error' => 'not_found'];
    }

    public function settings(Request $request): array
    {
        $shop = $this->shop($request);

        return [
            'success' => true,
            'settings' => $this->manager->settings($shop),
            'events' => RedirectEvent::where('shop_id', $shop->id)->latest('id')->limit(25)->get(['source', 'path', 'target', 'created_at'])
                ->map(fn (RedirectEvent $e) => ['source' => $e->source, 'path' => $e->path, 'target' => $e->target, 'created_at' => $e->created_at?->toIso8601String()])->all(),
        ];
    }

    public function saveSettings(Request $request): array
    {
        $validator = validator($request->all(), [
            'redirect_on_handle_change' => ['required', 'boolean'],
            'redirect_on_delete' => ['required', 'boolean'],
            'deleted_redirect_target' => ['required', Rule::in(RedirectManager::DELETE_TARGETS)],
            'not_found_monitor' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            return ['error' => 'invalid_settings', 'fields' => $validator->errors()->keys()];
        }

        return ['success' => true, 'settings' => $this->manager->saveSettings($this->shop($request), $validator->validated())];
    }

    /**
     * @return array{0:?string, 1:?string, 2:?array}
     */
    private function validatedPair(Request $request, Shop $shop): array
    {
        $path = $this->normalizePath((string) $request->input('path', ''), $shop);
        $target = $this->normalizeTarget((string) $request->input('target', ''), $shop);
        if ($path === null) {
            return [null, null, ['error' => 'invalid_path']];
        }
        if ($target === null) {
            return [null, null, ['error' => 'invalid_target']];
        }
        if (strcasecmp(rtrim($path, '/'), rtrim($target, '/')) === 0) {
            return [null, null, ['error' => 'same_path']];
        }

        return [$path, $target, null];
    }

    /**
     * Redirect source: a path on the store ("/old" or a full URL on the shop).
     */
    private function normalizePath(string $value, Shop $shop): ?string
    {
        $value = trim($value);
        if (preg_match('#^https?://#i', $value)) {
            $parts = parse_url($value);
            $value = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }
        if ($value === '' || $value[0] !== '/' || $value === '/' || mb_strlen($value) > 500 || preg_match('/\s/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * Redirect target: a store path or an absolute http(s) URL.
     */
    private function normalizeTarget(string $value, Shop $shop): ?string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > 500 || preg_match('/\s/', $value)) {
            return null;
        }
        if ($value[0] === '/') {
            return str_starts_with($value, '//') ? null : $value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) ? $value : null;
    }

    /**
     * Shopify's redirect CSV ("Redirect from,Redirect to"), comma or
     * semicolon separated; the header row is optional.
     *
     * @return array{0: array<int, array{path:string, target:string}>, 1: int}
     */
    private function parseCsv(string $csv, Shop $shop): array
    {
        $rows = [];
        $seen = [];
        $invalid = 0;
        $lines = preg_split('/\r\n|\r|\n/', $csv);
        $delimiter = substr_count($lines[0] ?? '', ';') > substr_count($lines[0] ?? '', ',') ? ';' : ',';

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            if ($i === 0 && preg_match('/redirect|from|path|van/i', $cells[0] ?? '') && ! str_starts_with(trim($cells[0] ?? ''), '/')) {
                continue;
            }
            $path = $this->normalizePath((string) ($cells[0] ?? ''), $shop);
            $target = $this->normalizeTarget((string) ($cells[1] ?? ''), $shop);
            if ($path === null || $target === null || strcasecmp(rtrim($path, '/'), rtrim($target, '/')) === 0) {
                $invalid++;

                continue;
            }
            $key = mb_strtolower($path);
            if (isset($seen[$key])) {
                $rows[$seen[$key]]['target'] = $target;

                continue;
            }
            $seen[$key] = count($rows);
            $rows[] = ['path' => $path, 'target' => $target];
        }

        return [$rows, $invalid];
    }

    /**
     * CSV cell, quoted when needed, with a formula-injection guard.
     */
    private function csvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'".$value;
        }

        return preg_match('/[",\n\r]/', $value) ? '"'.str_replace('"', '""', $value).'"' : $value;
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

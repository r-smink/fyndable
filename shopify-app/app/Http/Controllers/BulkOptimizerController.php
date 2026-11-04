<?php

namespace App\Http\Controllers;

use App\Jobs\BulkOptimizeJob;
use App\Models\Shop;
use App\Services\BulkOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * BulkOptimizerController (1.3)
 *
 * POST /api/bulk/optimize  - start a run {type, action, mode, template, only_needed, limit}
 * POST /api/bulk/preview   - first items with their current and new value (template mode)
 * GET  /api/bulk/progress  - progress of the current/last run
 * POST /api/bulk/cancel    - stop the run after the current item
 *
 * Types: product, collection, page, article. Actions per type in
 * BulkOptimizer::ACTIONS; template mode only for meta_title and meta.
 */
class BulkOptimizerController extends Controller
{
    public const MAX_ITEMS = 500;

    private const PREVIEW_ITEMS = 5;

    /** A run without progress for this long is considered stuck. */
    private const STALE_MINUTES = 30;

    public function __construct(private BulkOptimizer $optimizer) {}

    public function optimize(Request $request): array
    {
        $shop = $this->shop($request);
        [$params, $error] = $this->params($request);
        if ($error) {
            return $error;
        }

        // The route checks write_products; pages and articles need write_content.
        $scope = in_array($params['type'], ['page', 'article'], true) ? 'write_content' : 'write_products';
        if (in_array($scope, $shop->missingScopes(), true)) {
            return [
                'error' => 'missing_scopes',
                'missing_scopes' => $shop->missingScopes(),
                'reauth_url' => '/install?shop='.urlencode($shop->shop_domain),
            ];
        }

        $key = BulkOptimizeJob::cacheKey($shop->id);
        $current = Cache::get($key);
        if ($current && in_array($current['status'] ?? null, ['queued', 'running'], true) && ! $this->stale($current)) {
            return ['error' => 'job_running'];
        }

        $items = $this->optimizer->select($shop, $params['type'], $params['action'], $params['only_needed'], $params['limit']);
        if (isset($items['error'])) {
            return ['error' => 'fetch_failed', 'message' => $items['error']];
        }
        if ($items === []) {
            return ['error' => 'no_items'];
        }

        Cache::put($key, [
            'type' => $params['type'],
            'action' => $params['action'],
            'mode' => $params['mode'],
            'total' => count($items),
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'errors' => [],
            'status' => 'queued',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], now()->addDay());

        BulkOptimizeJob::dispatch($shop->id, $params['type'], $params['action'], $params['mode'], $params['template'], $items);

        return ['success' => true, 'total' => count($items)];
    }

    public function preview(Request $request): array
    {
        $shop = $this->shop($request);
        [$params, $error] = $this->params($request);
        if ($error) {
            return $error;
        }
        if ($params['mode'] !== 'template') {
            return ['error' => 'preview_template_only'];
        }

        $items = $this->optimizer->select($shop, $params['type'], $params['action'], $params['only_needed'], self::PREVIEW_ITEMS);
        if (isset($items['error'])) {
            return ['error' => 'fetch_failed', 'message' => $items['error']];
        }

        $shopName = (string) ($shop->shop_name ?: str_replace('.myshopify.com', '', $shop->shop_domain));
        $field = $params['action'] === 'meta_title' ? 'seo_title' : 'seo_description';

        return [
            'success' => true,
            'items' => array_map(fn (array $item) => [
                'title' => $item['title'],
                'current' => $item[$field],
                'new' => $this->optimizer->render((string) $params['template'], $item, $shopName, $params['action']),
            ], $items),
        ];
    }

    public function progress(Request $request): array
    {
        $progress = Cache::get(BulkOptimizeJob::cacheKey($this->shop($request)->id));

        return $progress ?: ['status' => 'idle'];
    }

    public function cancel(Request $request): array
    {
        $key = BulkOptimizeJob::cacheKey($this->shop($request)->id);
        $progress = Cache::get($key);
        if (! $progress || ! in_array($progress['status'] ?? null, ['queued', 'running'], true)) {
            return ['error' => 'no_job_running'];
        }

        $progress['status'] = 'cancelled';
        Cache::put($key, $progress, now()->addDay());

        return ['success' => true];
    }

    /**
     * @return array{0: ?array, 1: ?array}
     */
    private function params(Request $request): array
    {
        $type = (string) $request->input('type', 'product');
        $action = (string) $request->input('action', 'meta');
        $mode = (string) $request->input('mode', 'ai');
        $template = trim((string) $request->input('template', ''));

        if (! in_array($type, BulkOptimizer::TYPES, true)) {
            return [null, ['error' => 'invalid_type', 'valid_types' => BulkOptimizer::TYPES]];
        }
        if (! BulkOptimizer::validAction($type, $action)) {
            return [null, ['error' => 'invalid_action', 'valid_actions' => BulkOptimizer::ACTIONS[$type]]];
        }
        if (! in_array($mode, ['ai', 'template'], true) || ($mode === 'template' && ! in_array($action, BulkOptimizer::TEMPLATE_ACTIONS, true))) {
            return [null, ['error' => 'invalid_mode']];
        }
        if ($mode === 'template' && ($template === '' || mb_strlen($template) > 500 || ! str_contains($template, '{{'))) {
            return [null, ['error' => 'invalid_template']];
        }

        return [[
            'type' => $type,
            'action' => $action,
            'mode' => $mode,
            'template' => $mode === 'template' ? $template : null,
            'only_needed' => $request->boolean('only_needed', true) && $action !== 'titles',
            'limit' => max(1, min((int) $request->input('limit', 100), self::MAX_ITEMS)),
        ], null];
    }

    private function stale(array $progress): bool
    {
        $updated = strtotime((string) ($progress['updated_at'] ?? $progress['started_at'] ?? ''));

        return $updated !== false && $updated < now()->subMinutes(self::STALE_MINUTES)->getTimestamp();
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

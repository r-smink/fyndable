<?php

namespace App\Http\Controllers;

use App\Jobs\BulkOptimizeJob;
use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * BulkOptimizerController
 *
 * Bulk AI-powered SEO optimization for Shopify products:
 *  - Optimize product titles
 *  - Generate meta descriptions
 *  - Generate image alt text
 *  - Rewrite product descriptions
 *
 * Uses queue-based processing (Laravel Jobs) for large catalogs.
 * Progress is tracked via cache and polled by the frontend.
 */
class BulkOptimizerController extends Controller
{
    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private LicenseService $license
    ) {}

    /**
     * Start a bulk optimization job.
     *
     * POST /api/bulk/optimize
     * Body: { action: "titles"|"meta"|"alt_text"|"descriptions", limit: 100 }
     */
    public function optimize(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (!$this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $action = $request->input('action', 'meta');
        $limit = min((int) $request->input('limit', 100), 500);
        $validActions = ['titles', 'meta', 'alt_text', 'descriptions'];

        if (!in_array($action, $validActions, true)) {
            return ['error' => 'invalid_action', 'valid_actions' => $validActions];
        }

        // Check if a job is already running
        $jobKey = "bulk:{$shop->id}:{$action}";
        if (Cache::has($jobKey)) {
            return ['error' => 'job_running', 'message' => 'A bulk optimization job is already running for this action.'];
        }

        // Fetch products to optimize
        $products = $this->fetcher->getProducts($shop, $limit);
        if (empty($products)) {
            return ['error' => 'no_products'];
        }

        $productIds = array_map(fn($p) => $p['id'], $products);

        // Initialize progress tracking
        Cache::put($jobKey, [
            'action' => $action,
            'total' => count($productIds),
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'started_at' => now()->toIso8601String(),
            'status' => 'running',
        ], 3600);

        // Dispatch the job
        BulkOptimizeJob::dispatch($shop->id, $action, $productIds);

        return [
            'success' => true,
            'job' => $action,
            'total' => count($productIds),
            'message' => 'Bulk optimization started. Check progress with GET /api/bulk/progress.',
        ];
    }

    /**
     * Get the progress of a bulk optimization job.
     *
     * GET /api/bulk/progress?action=meta
     */
    public function progress(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $action = $request->input('action', 'meta');
        $jobKey = "bulk:{$shop->id}:{$action}";

        $progress = Cache::get($jobKey);
        if (!$progress) {
            return ['status' => 'idle', 'message' => 'No bulk job running.'];
        }

        return $progress;
    }

    /**
     * Cancel a running bulk optimization job (by marking it as cancelled).
     *
     * POST /api/bulk/cancel
     * Body: { action: "meta" }
     */
    public function cancel(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $action = $request->input('action', 'meta');
        $jobKey = "bulk:{$shop->id}:{$action}";

        $progress = Cache::get($jobKey);
        if (!$progress) {
            return ['error' => 'no_job_running'];
        }

        $progress['status'] = 'cancelled';
        Cache::put($jobKey, $progress, 300);

        return ['success' => true, 'message' => 'Job cancellation requested.'];
    }
}

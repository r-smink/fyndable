<?php

namespace App\Http\Controllers;

use App\Models\LlmsTxtSettings;
use App\Models\Shop;
use App\Services\LlmsTxtGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * LlmsTxtController
 *
 * Serves /llms.txt and /llms-full.txt for Shopify shops.
 *
 * Shopify App Proxy routes these requests from the shop's domain:
 *   https://store.myshopify.com/apps/fyndable/llms.txt
 *   https://store.myshopify.com/apps/fyndable/llms-full.txt
 *
 * The shop is identified by the `shop` query parameter (added by Shopify App Proxy).
 */
class LlmsTxtController extends Controller
{
    public function __construct(
        private LlmsTxtGenerator $generator
    ) {}

    /**
     * Serve /llms.txt (markdown summary).
     */
    public function summary(Request $request): Response
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response('Not found', 404);
        }

        $settings = LlmsTxtSettings::getForShop($shop->id);
        if (! $settings->enabled) {
            return response('Not found', 404);
        }

        $content = $this->generator->getSummary($shop, $settings);

        return $this->textResponse($content);
    }

    /**
     * Serve /llms-full.txt (full content).
     */
    public function full(Request $request): Response
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return response('Not found', 404);
        }

        $settings = LlmsTxtSettings::getForShop($shop->id);
        if (! $settings->enabled || ! $settings->full_enabled) {
            return response('Not found', 404);
        }

        $content = $this->generator->getFull($shop, $settings);

        return $this->textResponse($content);
    }

    /**
     * Get llms.txt status (for the dashboard UI).
     */
    public function status(Request $request): array
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return ['error' => 'shop_not_found'];
        }

        try {
            $settings = LlmsTxtSettings::getForShop($shop->id);
            $summarySize = strlen($this->generator->getSummary($shop, $settings));
            $fullSize = $settings->full_enabled ? strlen($this->generator->getFull($shop, $settings)) : 0;

            return [
                'enabled' => $settings->enabled,
                'full_enabled' => $settings->full_enabled,
                'include_products' => $settings->include_products,
                'include_collections' => $settings->include_collections,
                'include_pages' => $settings->include_pages,
                'include_blogs' => $settings->include_blogs,
                'max_products' => $settings->max_products,
                'max_pages' => $settings->max_pages,
                'max_articles' => $settings->max_articles,
                'max_collections' => $settings->max_collections,
                'full_max_chars' => $settings->full_max_chars,
                'include_excerpt' => $settings->include_excerpt,
                'description' => $settings->description,
                'custom_sections' => $settings->custom_sections,
                'summary_size' => $summarySize,
                'full_size' => $fullSize,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LlmsTxtController::status failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'status_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Update llms.txt settings (from the dashboard UI).
     */
    public function updateSettings(Request $request): array
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return ['error' => 'shop_not_found'];
        }

        $settings = LlmsTxtSettings::getForShop($shop->id);
        $settings->fill($request->only([
            'enabled',
            'full_enabled',
            'include_products',
            'include_collections',
            'include_pages',
            'include_blogs',
            'max_products',
            'max_pages',
            'max_articles',
            'max_collections',
            'full_max_chars',
            'include_excerpt',
            'description',
            'custom_sections',
        ]));
        $settings->save();

        // Invalidate cache so changes take effect immediately
        $this->generator->invalidate($shop);

        return ['success' => true, 'settings' => $settings->fresh()->toArray()];
    }

    /**
     * Regenerate the llms.txt cache (manual refresh from dashboard).
     */
    public function regenerate(Request $request): array
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return ['error' => 'shop_not_found'];
        }

        try {
            $this->generator->invalidate($shop);
            $settings = LlmsTxtSettings::getForShop($shop->id);

            $summarySize = strlen($this->generator->getSummary($shop, $settings));
            $fullSize = $settings->full_enabled ? strlen($this->generator->getFull($shop, $settings)) : 0;

            return [
                'success' => true,
                'summary_size' => $summarySize,
                'full_size' => $fullSize,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LlmsTxtController::regenerate failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'regenerate_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Preview the llms.txt content (for the dashboard UI).
     */
    public function preview(Request $request): array
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return ['error' => 'shop_not_found'];
        }

        try {
            $settings = LlmsTxtSettings::getForShop($shop->id);
            $this->generator->invalidate($shop);
            $summary = $this->generator->getSummary($shop, $settings);

            // Return first 5000 chars for preview
            $preview = mb_strlen($summary) > 5000
                ? mb_substr($summary, 0, 5000)."\n\n... (truncated for preview)"
                : $summary;

            return [
                'content' => $preview,
                'full_size' => mb_strlen($summary),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('LlmsTxtController::preview failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'preview_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Resolve the shop from the request (via `shop` query param or session).
     */
    private function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->query('shop', '');
        if (! empty($shopDomain)) {
            $shopDomain = strtolower(trim($shopDomain));

            return Shop::findByDomain($shopDomain);
        }

        // Fallback: try session-based shop (for authenticated dashboard requests)
        $sessionShop = $request->attributes->get('shop');
        if ($sessionShop instanceof Shop) {
            return $sessionShop;
        }

        return null;
    }

    /**
     * Build a text/plain response with ETag and cache headers.
     */
    private function textResponse(string $content): Response
    {
        $etag = '"'.md5($content).'"';

        $inm = request()->header('If-None-Match', '');
        if ($inm && trim($inm, '"') === trim($etag, '"')) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=21600',
            'ETag' => $etag,
        ]);
    }
}

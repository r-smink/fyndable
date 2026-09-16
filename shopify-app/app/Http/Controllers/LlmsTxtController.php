<?php

namespace App\Http\Controllers;

use App\Models\LlmsTxtSettings;
use App\Models\Shop;
use App\Services\LlmsTxtGenerator;
use App\Services\ShopifyUrlRedirect;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * LlmsTxtController
 *
 * Serves /llms.txt and /llms-full.txt for Shopify shops.
 *
 * Shopify App Proxy routes these requests from the shop's domain:
 *   https://store.myshopify.com/apps/fyndable/llms.txt
 *   https://store.myshopify.com/apps/fyndable/llms.txt?full=1
 *
 * The shop is identified by the `shop` query parameter (added by Shopify App Proxy).
 * Use `?full=1` to get the full content.
 */
class LlmsTxtController extends Controller
{
    public function __construct(
        private LlmsTxtGenerator $generator
    ) {}

    /**
     * App Proxy catch-all: serves /llms.txt or /llms-full.txt content
     * based on the `full` query parameter.
     *
     * This method is mapped to the root of the API routes (e.g. GET /api)
     * so Shopify App Proxy requests to `https://shopify.fyndable.ai/api`
     * can be handled. Use `?full=1` to get the full version.
     */
    public function proxy(Request $request): Response
    {
        $request->query->set('shop', $request->query('shop', ''));

        if ($request->boolean('full')) {
            return $this->full($request);
        }

        return $this->summary($request);
    }

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
     * Create or update Shopify URL redirects so /llms.txt and /llms-full.txt
     * on the root domain redirect to the App Proxy paths.
     */
    public function setupRedirects(Request $request, ShopifyUrlRedirect $redirects): array
    {
        $shop = $this->resolveShop($request);
        if (! $shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $shop->hasAccessToken()) {
            return ['error' => 'no_access_token'];
        }

        $results = $redirects->setup($shop);

        return ['success' => true, 'redirects' => $results];
    }

    /**
     * Resolve the shop from the request (via `shop` query param or session).
     * If a Shopify App Proxy `signature` is provided, it is verified first.
     */
    private function resolveShop(Request $request): ?Shop
    {
        $shopDomain = $request->query('shop', '');

        // Verify App Proxy signature if present
        if ($request->has('signature')) {
            if (! $this->verifyAppProxySignature($request)) {
                Log::warning('llms.txt App Proxy signature invalid', ['shop' => $shopDomain]);
                return null;
            }
        }

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
     * Verify a Shopify App Proxy signature.
     *
     * Shopify sorts all query params (except `signature`) alphabetically, builds
     * `key=value` pairs joined with `&` (without URL decoding the raw values),
     * and compares the HMAC-SHA256 (hex) with the app secret.
     */
    private function verifyAppProxySignature(Request $request): bool
    {
        $queryString = $request->server->get('QUERY_STRING', '');
        if ($queryString === '') {
            return false;
        }

        $signature = '';
        $pairs = [];

        foreach (explode('&', $queryString) as $part) {
            $pos = strpos($part, '=');
            $key = $pos === false ? $part : substr($part, 0, $pos);
            $value = $pos === false ? '' : substr($part, $pos + 1);

            if ($key === 'signature') {
                $signature = $value;
                continue;
            }

            $pairs[$key] = $key . '=' . $value;
        }

        ksort($pairs);
        $message = implode('&', $pairs);

        $calculated = hash_hmac('sha256', $message, config('shopify.api_secret'));

        return $signature !== '' && hash_equals($signature, $calculated);
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

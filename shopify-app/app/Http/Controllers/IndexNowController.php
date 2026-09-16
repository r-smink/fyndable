<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopifyContentFetcher;
use App\Services\ShopifyUrlRedirect;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNowController
 *
 * IndexNow (Bing/Yandex instant indexing) for the storefront:
 *
 *  - Deterministic key per shop (HMAC of the shop domain — no storage needed).
 *  - The key file is served through the App Proxy at /apps/fyndable/indexnow-key.txt.
 *  - A Shopify URL redirect /{key}.txt → that proxy URL makes the key file
 *    reachable on the shop's own domain, as IndexNow requires.
 *  - Submissions go to https://api.indexnow.org/indexnow.
 */
class IndexNowController extends Controller
{
    public function __construct(
        private ShopifyUrlRedirect $redirects,
        private ShopifyContentFetcher $fetcher
    ) {}

    /**
     * IndexNow status: key, key file URL, redirect target.
     *
     * GET /api/indexnow/status
     */
    public function status(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $host = $this->shopHost($shop);

        return [
            'success' => true,
            'key' => $this->key($shop),
            'key_location' => "https://{$host}/".$this->key($shop).'.txt',
            'proxy_path' => '/apps/fyndable/indexnow-key.txt',
            'redirect_path' => '/'.$this->key($shop).'.txt',
        ];
    }

    /**
     * Create the storefront redirect so the IndexNow key file resolves.
     *
     * POST /api/indexnow/setup
     */
    public function setup(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (in_array('write_content', $shop->missingScopes(), true)) {
            return [
                'error' => 'missing_scopes',
                'missing_scopes' => $shop->missingScopes(),
                'reauth_url' => '/install?shop='.urlencode($shop->shop_domain),
            ];
        }

        $result = $this->redirects->ensure(
            $shop,
            '/'.$this->key($shop).'.txt',
            '/apps/fyndable/indexnow-key.txt'
        );

        return ['success' => true, 'redirect' => $result];
    }

    /**
     * Submit URLs to IndexNow.
     *
     * POST /api/indexnow/submit
     * Body: { urls: [...] } — absolute URLs on the shop's domain.
     */
    public function submit(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $urls = array_values(array_filter(array_map(
            fn ($u) => trim((string) $u),
            (array) $request->input('urls', [])
        ), fn ($u) => str_starts_with($u, 'https://')));

        if (empty($urls)) {
            return ['error' => 'urls_required'];
        }

        $host = $this->shopHost($shop);
        $key = $this->key($shop);

        try {
            $response = Http::timeout(30)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => "https://{$host}/{$key}.txt",
                'urlList' => $urls,
            ]);
        } catch (\Throwable $e) {
            Log::error('IndexNow submit failed', ['error' => $e->getMessage()]);

            return ['error' => 'submit_failed', 'message' => $e->getMessage()];
        }

        // IndexNow returns 200 (accepted) or 202 (received).
        if ($response->status() >= 400) {
            return [
                'error' => 'indexnow_error',
                'status' => $response->status(),
                'message' => mb_substr($response->body(), 0, 300),
            ];
        }

        return [
            'success' => true,
            'submitted' => count($urls),
            'key_location' => "https://{$host}/{$key}.txt",
        ];
    }

    /**
     * Serve the IndexNow key file through the App Proxy.
     *
     * GET /api/indexnow-key.txt (public — Shopify app proxy adds ?shop=...)
     */
    public function keyFile(Request $request): Response
    {
        $shopDomain = (string) $request->query('shop', '');
        $shop = $shopDomain !== '' ? Shop::findByDomain($shopDomain) : null;

        if (! $shop) {
            return response('not found', 404);
        }

        return response($this->key($shop), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Deterministic 32-char IndexNow key per shop (no storage needed).
     */
    private function key(Shop $shop): string
    {
        return substr(hash_hmac('sha256', 'indexnow:'.$shop->shop_domain, (string) config('shopify.api_secret')), 0, 32);
    }

    /**
     * The storefront host (primary domain preferred over myshopify domain).
     */
    private function shopHost(Shop $shop): string
    {
        $details = $this->fetcher->getShopDetails($shop);
        $primary = $details['domain'] ?? '';
        $host = parse_url($primary, PHP_URL_HOST);

        return $host ?: $shop->shop_domain;
    }
}

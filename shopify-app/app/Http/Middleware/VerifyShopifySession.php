<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;

/**
 * VerifyShopifySession
 *
 * Verifies the Shopify session for authenticated API requests.
 * Extracts the shop domain from the query parameter or JWT session token
 * and attaches the Shop model to the request attributes.
 */
class VerifyShopifySession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $shopDomain = $request->query('shop', '');

        // Also check the X-Shop-Domain header (for App Bridge requests)
        if (empty($shopDomain)) {
            $shopDomain = $request->header('X-Shop-Domain', '');
        }

        // Try to extract from the session token (Authorization header)
        if (empty($shopDomain)) {
            $shopDomain = $this->extractShopFromToken($request);
        } else {
            // If a token is also present, validate that it matches the claimed shop
            $this->validateTokenForShop($request, $shopDomain);
        }

        $shopDomain = strtolower(trim($shopDomain));
        if (empty($shopDomain)) {
            return response()->json(['error' => 'shop_required'], 400);
        }

        $shop = Shop::findByDomain($shopDomain);
        if (!$shop) {
            return response()->json(['error' => 'shop_not_found'], 404);
        }

        if (!$shop->is_installed || $shop->is_uninstalled) {
            return response()->json(['error' => 'shop_not_installed'], 403);
        }

        $request->attributes->set('shop', $shop);

        return $next($request);
    }

    /**
     * Try to extract the shop domain from a Shopify session JWT.
     * The JWT's `dest` claim contains the shop domain (e.g. https://store.myshopify.com).
     */
    private function extractShopFromToken(Request $request): string
    {
        $auth = $request->header('Authorization', '');
        if (!str_starts_with($auth, 'Bearer ')) {
            return '';
        }

        $token = substr($auth, 7);

        $signature = new \App\Services\ShopifySignature(config('shopify.api_secret'));
        $payload = $signature->verifySessionToken($token);

        if (!$payload || empty($payload['dest'])) {
            return '';
        }

        $dest = $payload['dest'];
        $dest = preg_replace('#^https?://#', '', $dest);
        $dest = preg_replace('#/.*$#', '', $dest);

        return $dest;
    }

    /**
     * If the request also carries an App Bridge JWT, verify it for the given shop.
     */
    private function validateTokenForShop(Request $request, string $shopDomain): void
    {
        $auth = $request->header('Authorization', '');
        if (!str_starts_with($auth, 'Bearer ')) {
            return;
        }

        $token = substr($auth, 7);

        $signature = new \App\Services\ShopifySignature(config('shopify.api_secret'));
        $payload = $signature->verifySessionToken($token, $shopDomain);

        if (!$payload) {
            abort(401, 'Invalid session token.');
        }
    }
}

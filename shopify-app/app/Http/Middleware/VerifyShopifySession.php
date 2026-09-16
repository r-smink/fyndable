<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\ShopifySignature;
use App\Services\ShopifyTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * VerifyShopifySession
 *
 * Verifies the Shopify session for authenticated API requests.
 * Requires an `Authorization: Bearer <token>` App Bridge session JWT,
 * derives the shop from the verified `dest` claim, and attaches the
 * Shop model to the request attributes.
 */
class VerifyShopifySession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $auth = $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return $this->unauthorized();
        }

        $token = substr($auth, 7);

        $signature = new ShopifySignature(config('shopify.api_secret'));
        $payload = $signature->verifySessionToken($token);

        if (! $payload) {
            return $this->unauthorized();
        }

        // The shop is derived exclusively from the verified `dest` claim
        $shopDomain = ShopifySignature::normalizeShopDomain((string) ($payload['dest'] ?? ''));
        if ($shopDomain === null) {
            return $this->unauthorized();
        }

        // If the request also claims a shop via query or header, it must match the token
        $claimed = $request->query('shop') ?: $request->header('X-Shop-Domain', '');
        if (! empty($claimed)) {
            $claimed = ShopifySignature::normalizeShopDomain((string) $claimed);
            if ($claimed === null || $claimed !== $shopDomain) {
                return $this->unauthorized();
            }
        }

        $shop = Shop::findByDomain($shopDomain);
        if (! $shop) {
            return response()->json(['error' => 'shop_not_found'], 404);
        }

        if (! $shop->is_installed || $shop->is_uninstalled) {
            return response()->json(['error' => 'shop_not_installed'], 403);
        }

        // Shopify rejects non-expiring offline tokens. While a merchant
        // session is active, swap the verified ID token for a fresh expiring
        // access token whenever the stored one is missing or expired.
        if (! $shop->hasUsableToken()) {
            app(ShopifyTokenService::class)->exchange($shop, $token);
        }

        $request->attributes->set('shop', $shop);

        return $next($request);
    }

    /**
     * 401 response that tells App Bridge to fetch a fresh session token.
     */
    private function unauthorized(): JsonResponse
    {
        return response()
            ->json(['error' => 'invalid_session'], 401)
            ->header('X-Shopify-Retry-Invalid-Session-Request', '1');
    }
}

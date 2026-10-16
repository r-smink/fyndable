<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\ShopifySignature;
use App\Services\ShopifyTokenService;
use App\Services\ShopifyWebhookRegistrar;
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

        // Managed install flow: there is no OAuth callback. The first
        // verified session after the merchant installs (or reinstalls)
        // provisions the shop record via token exchange.
        $isNewInstall = $shop === null;
        if ($isNewInstall) {
            $shop = new Shop([
                'shop_domain' => $shopDomain,
                'is_installed' => true,
                'is_uninstalled' => false,
            ]);
        }

        if (! $shop->is_installed && ! $isNewInstall) {
            return response()->json(['error' => 'shop_not_installed'], 403);
        }

        // A valid session token on an uninstalled shop means the merchant
        // reinstalled — clear the flag once the exchange succeeds.
        $needsToken = ! $shop->hasUsableToken() || $shop->is_uninstalled;
        if ($needsToken) {
            if (! app(ShopifyTokenService::class)->exchange($shop, $token)) {
                return response()->json(['error' => 'token_exchange_failed'], 403);
            }
            if ($shop->is_uninstalled) {
                $shop->is_uninstalled = false;
                $shop->save();
            }
        }

        if ($isNewInstall && $shop->exists) {
            app(ShopifyWebhookRegistrar::class)->register($shop);
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

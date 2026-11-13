<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\ShopifySignature;
use App\Services\ShopifyTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Shopify only issues session tokens for an app that is installed. A verified token
        // for a shop we marked uninstalled (app/uninstalled webhook) therefore means the
        // merchant reinstalled: mint a new token and mark the shop installed again.
        // (Before, the shop was rejected with "shop_not_installed" before this point, so
        // every reinstall got stuck: App Store review 1.2.2 / 1.2.3 / 2.1.1.)
        $isReinstall = ! $isNewInstall && (! $shop->is_installed || $shop->is_uninstalled);
        $needsToken = $isNewInstall || $isReinstall || ! $shop->hasUsableToken();
        if ($needsToken) {
            if (! app(ShopifyTokenService::class)->exchange($shop, $token)) {
                return response()->json(['error' => 'token_exchange_failed'], 403);
            }
            if ($isReinstall) {
                $shop->is_installed = true;
                $shop->is_uninstalled = false;
                $shop->save();
                Log::info('Shop reinstalled', ['shop' => $shop->shop_domain]);
            }
        }

        // Webhooks are app-specific subscriptions in shopify.app.toml; nothing
        // to register per shop (the REST registration was removed, 2.2.4).

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

<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;

/**
 * RequireShopifyScope
 *
 * Route middleware that checks whether the shop's stored access token was
 * granted a specific Shopify access scope. Runs after shopify.session, which
 * attaches the Shop model to the request attributes.
 *
 * Usage: ->middleware('shopify.scope:write_products')
 *
 * Returns 403 missing_scopes with a reauth_url when the scope is absent, so the
 * embedded app can send the merchant through OAuth again to upgrade the token.
 */
class RequireShopifyScope
{
    public function handle(Request $request, Closure $next, string $scope): mixed
    {
        $shop = $request->attributes->get('shop');

        if ($shop instanceof Shop && in_array($scope, $shop->missingScopes(), true)) {
            return response()->json([
                'error' => 'missing_scopes',
                'missing_scopes' => $shop->missingScopes(),
                'reauth_url' => '/install?shop='.urlencode($shop->shop_domain),
                'message' => "Missing access scope: {$scope}. Re-authorize the app to grant it.",
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\ShopifySignature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AuthController
 *
 * The app uses Shopify's managed install flow: Shopify shows the grant
 * screen, and the first verified session token provisions the shop and its
 * access token via token exchange (VerifyShopifySession). There is no OAuth
 * code exchange.
 *
 *  1. /install       - send the merchant to the grant screen (install or
 *                      re-approve added scopes)
 *  2. /auth/callback - kept because shopify.app.toml declares it as redirect
 *                      URL; verifies the HMAC and forwards to the dashboard
 */
class AuthController extends Controller
{
    /**
     * Step 1: Redirect the merchant to Shopify's install/grant page.
     *
     * This URL presents the grant screen and is also where merchants
     * re-approve when the app adds access scopes.
     */
    public function install(Request $request): RedirectResponse
    {
        $shopDomain = ShopifySignature::normalizeShopDomain($request->query('shop', ''));
        if ($shopDomain === null) {
            abort(400, 'Invalid or missing shop parameter.');
        }

        $grantUrl = "https://{$shopDomain}/admin/oauth/install?".http_build_query([
            'client_id' => config('shopify.api_key'),
        ]);

        return redirect($grantUrl);
    }

    /**
     * Step 2: Shopify redirect after install. Verify the signature and open
     * the embedded dashboard, where the session token flow takes over.
     *
     * The former OAuth code exchange here always failed (install() no longer
     * issues a state nonce under the managed install flow) and was removed.
     */
    public function callback(Request $request): RedirectResponse
    {
        $shopDomain = ShopifySignature::normalizeShopDomain((string) $request->query('shop', ''));
        if ($shopDomain === null) {
            abort(400, 'Invalid or missing shop parameter.');
        }

        $signature = new ShopifySignature(config('shopify.api_secret'));
        if (! $signature->verifyOAuth($request)) {
            Log::warning('HMAC verification failed', ['shop' => $shopDomain]);
            abort(403, 'HMAC verification failed.');
        }

        return redirect()->route('dashboard', array_filter([
            'shop' => $shopDomain,
            'host' => (string) $request->query('host', ''),
        ]));
    }
}

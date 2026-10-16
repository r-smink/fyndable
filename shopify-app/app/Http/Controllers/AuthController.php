<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopifyContentFetcher;
use App\Services\ShopifySignature;
use App\Services\ShopifyWebhookRegistrar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AuthController
 *
 * Handles Shopify OAuth install flow:
 *  1. /install — redirect to Shopify's OAuth authorize URL
 *  2. /auth/callback — exchange code for access token, register webhooks
 *
 * Validates the HMAC signature on the callback to prevent tampering.
 */
class AuthController extends Controller
{
    /**
     * Step 1: Redirect the merchant to Shopify's install/grant page.
     *
     * The app uses the managed install flow (token exchange) — there is no
     * OAuth authorize step. This URL presents the grant screen and is also
     * where merchants re-approve when the app adds access scopes.
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
     * Step 2: Handle the OAuth callback — exchange code for access token.
     */
    public function callback(Request $request): RedirectResponse
    {
        $shopDomain = ShopifySignature::normalizeShopDomain($request->query('shop', ''));
        $code = $request->query('code', '');
        $hmac = $request->query('hmac', '');

        if ($shopDomain === null || empty($code)) {
            abort(400, 'Missing required OAuth parameters.');
        }

        // Verify the OAuth state nonce issued in install() to prevent CSRF
        $expectedState = $request->session()->pull('shopify_oauth_state');
        $state = (string) $request->query('state', '');
        if (empty($expectedState) || empty($state) || ! hash_equals($expectedState, $state)) {
            abort(403, 'OAuth state mismatch.');
        }

        // Verify HMAC
        $this->verifyHmac($request);

        // Exchange code for access token
        $tokenData = $this->exchangeCodeForToken($shopDomain, $code);
        $accessToken = $tokenData['access_token'] ?? null;
        if ($accessToken === null) {
            abort(400, 'Failed to obtain access token.');
        }

        // Save shop — scope comes from the token-exchange response (the
        // callback query string does not include it), then is corrected to the
        // actually granted scopes via updateAccessScopes() below.
        $shop = Shop::firstOrCreate(['shop_domain' => $shopDomain]);
        $shop->access_token = $accessToken;
        $shop->scope = $tokenData['scope'] ?? '';

        // The code grant may return an expiring offline token (with a refresh
        // token); store those when present. A null token_expires_at marks a
        // legacy non-expiring token — the next session request exchanges it.
        $expiresIn = (int) ($tokenData['expires_in'] ?? 0);
        $shop->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;
        $shop->refresh_token = $tokenData['refresh_token'] ?? null;

        $shop->is_installed = true;
        $shop->is_uninstalled = false;
        $shop->save();

        // Fetch shop details (name, currency, country)
        $this->updateShopDetails($shop);

        // Store the granted scopes (authoritative, covers granted != requested)
        $this->updateAccessScopes($shop);

        // Register webhooks
        app(ShopifyWebhookRegistrar::class)->register($shop);

        Log::info('Shopify app installed', ['shop' => $shopDomain]);

        // Redirect to the embedded app dashboard (App Bridge loads the session token)
        return redirect()->route('dashboard', [
            'shop' => $shopDomain,
            'host' => $request->query('host', ''),
        ]);
    }

    /**
     * Exchange the OAuth code for a permanent access token.
     *
     * @return array{access_token: ?string, scope: string, expires_in: mixed, refresh_token: ?string}|null
     */
    private function exchangeCodeForToken(string $shopDomain, string $code): ?array
    {
        $endpoint = "https://{$shopDomain}/admin/oauth/access_token";
        $apiKey = config('shopify.api_key');
        $apiSecret = config('shopify.api_secret');

        try {
            $response = Http::timeout(30)
                ->post($endpoint, [
                    'client_id' => $apiKey,
                    'client_secret' => $apiSecret,
                    'code' => $code,
                ]);
        } catch (ConnectionException $e) {
            Log::error('OAuth token exchange failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::error('OAuth token exchange HTTP error', ['status' => $response->status()]);

            return null;
        }

        return [
            'access_token' => $response->json('access_token'),
            'scope' => (string) $response->json('scope', ''),
            'expires_in' => $response->json('expires_in'),
            'refresh_token' => $response->json('refresh_token'),
        ];
    }

    /**
     * Fetch the actually granted access scopes via GraphQL and store them.
     */
    private function updateAccessScopes(Shop $shop): void
    {
        try {
            $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/graphql.json';
            $response = Http::timeout(15)
                ->withHeaders(['X-Shopify-Access-Token' => $shop->access_token])
                ->post($endpoint, [
                    'query' => '{ currentAppInstallation { accessScopes { handle } } }',
                ]);

            $handles = collect($response->json('data.currentAppInstallation.accessScopes') ?? [])
                ->pluck('handle')
                ->filter();

            if ($handles->isNotEmpty()) {
                $shop->scope = $handles->implode(',');
                $shop->save();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch access scopes', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify the HMAC signature on the callback request.
     */
    private function verifyHmac(Request $request): void
    {
        $signature = new ShopifySignature(config('shopify.api_secret'));

        if (! $signature->verifyOAuth($request)) {
            Log::warning('HMAC verification failed', ['shop' => $request->query('shop', '')]);
            abort(403, 'HMAC verification failed.');
        }
    }

    /**
     * Fetch and store shop details (name, currency, country).
     */
    private function updateShopDetails(Shop $shop): void
    {
        try {
            $fetcher = app(ShopifyContentFetcher::class);
            $details = $fetcher->getShopDetails($shop);

            if (! empty($details)) {
                $shop->shop_name = $details['name'] ?? $shop->shop_name;
                $shop->currency = $details['currency'] ?? $shop->currency;
                $shop->country_code = $details['country'] ?? $shop->country_code;
                $shop->save();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch shop details', ['error' => $e->getMessage()]);
        }
    }
}

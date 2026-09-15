<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopifyContentFetcher;
use App\Services\ShopifySignature;
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
     * Step 1: Redirect the merchant to Shopify's OAuth authorize page.
     */
    public function install(Request $request): RedirectResponse
    {
        $shopDomain = ShopifySignature::normalizeShopDomain($request->query('shop', ''));
        if ($shopDomain === null) {
            abort(400, 'Invalid or missing shop parameter.');
        }

        $apiKey = config('shopify.api_key');
        $scopes = config('shopify.scopes');
        $redirectUri = url(config('shopify.redirect_uri'));

        // Create or update shop record
        $shop = Shop::firstOrCreate(
            ['shop_domain' => $shopDomain],
            ['is_installed' => false]
        );

        $state = bin2hex(random_bytes(32));
        $request->session()->put('shopify_oauth_state', $state);

        $authorizeUrl = "https://{$shopDomain}/admin/oauth/authorize?".http_build_query([
            'client_id' => $apiKey,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return redirect($authorizeUrl);
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
        $accessToken = $this->exchangeCodeForToken($shopDomain, $code);
        if ($accessToken === null) {
            abort(400, 'Failed to obtain access token.');
        }

        // Save shop
        $shop = Shop::firstOrCreate(['shop_domain' => $shopDomain]);
        $shop->access_token = $accessToken;
        $shop->scope = $request->query('scope', '');
        $shop->is_installed = true;
        $shop->is_uninstalled = false;
        $shop->save();

        // Fetch shop details (name, currency, country)
        $this->updateShopDetails($shop);

        // Register webhooks
        $this->registerWebhooks($shop);

        Log::info('Shopify app installed', ['shop' => $shopDomain]);

        // Redirect to the embedded app dashboard (App Bridge loads the session token)
        return redirect()->route('dashboard', [
            'shop' => $shopDomain,
            'host' => $request->query('host', ''),
        ]);
    }

    /**
     * Exchange the OAuth code for a permanent access token.
     */
    private function exchangeCodeForToken(string $shopDomain, string $code): ?string
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

        return $response->json('access_token');
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

    /**
     * Register Shopify webhooks for content changes.
     */
    private function registerWebhooks(Shop $shop): void
    {
        $webhookUrl = url(config('shopify.webhook_uri'));
        $topics = [
            'products/create',
            'products/update',
            'products/delete',
            'collections/create',
            'collections/update',
            'collections/delete',
            'pages/create',
            'pages/update',
            'pages/delete',
            'articles/create',
            'articles/update',
            'articles/delete',
            'blogs/create',
            'blogs/update',
            'blogs/delete',
            'app/uninstalled',
        ];

        $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/webhooks.json';

        foreach ($topics as $topic) {
            try {
                Http::timeout(15)
                    ->withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($endpoint, [
                        'webhook' => [
                            'topic' => $topic,
                            'address' => $webhookUrl,
                            'format' => 'json',
                        ],
                    ]);
            } catch (\Exception $e) {
                Log::warning('Webhook registration failed', ['topic' => $topic, 'error' => $e->getMessage()]);
            }
        }
    }
}

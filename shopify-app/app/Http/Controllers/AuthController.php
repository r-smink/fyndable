<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\Utils;

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
        $shopDomain = $request->query('shop', '');
        if (empty($shopDomain)) {
            abort(400, 'Missing shop parameter.');
        }

        $shopDomain = $this->normalizeDomain($shopDomain);
        $apiKey = config('shopify.api_key');
        $scopes = config('shopify.scopes');
        $redirectUri = url(config('shopify.redirect_uri'));

        // Create or update shop record
        $shop = Shop::firstOrCreate(
            ['shop_domain' => $shopDomain],
            ['is_installed' => false]
        );

        $authorizeUrl = "https://{$shopDomain}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $apiKey,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => csrf_token(),
        ]);

        return redirect($authorizeUrl);
    }

    /**
     * Step 2: Handle the OAuth callback — exchange code for access token.
     */
    public function callback(Request $request): RedirectResponse
    {
        $shopDomain = $this->normalizeDomain($request->query('shop', ''));
        $code = $request->query('code', '');
        $hmac = $request->query('hmac', '');

        if (empty($shopDomain) || empty($code)) {
            abort(400, 'Missing required OAuth parameters.');
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

        // Redirect to the embedded app dashboard
        $appUrl = config('shopify.is_embedded')
            ? "https://{$shopDomain}/apps/" . config('shopify.api_key')
            : route('dashboard', ['shop' => $shopDomain]);

        return redirect($appUrl);
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
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->post($endpoint, [
                    'client_id' => $apiKey,
                    'client_secret' => $apiSecret,
                    'code' => $code,
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
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
        $signature = new \App\Services\ShopifySignature(config('shopify.api_secret'));

        if (!$signature->verifyOAuth($request)) {
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
            $fetcher = app(\App\Services\ShopifyContentFetcher::class);
            $details = $fetcher->getShopDetails($shop);

            if (!empty($details)) {
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

        $endpoint = "https://{$shop->shop_domain}/admin/api/" . config('shopify.api_version') . "/webhooks.json";

        foreach ($topics as $topic) {
            try {
                \Illuminate\Support\Facades\Http::timeout(15)
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

    /**
     * Normalize a Shopify domain (lowercase, strip protocol/path).
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);

        // Ensure it ends with .myshopify.com if not a custom domain
        if (!str_contains($domain, '.') ) {
            $domain .= '.myshopify.com';
        }

        return $domain;
    }
}

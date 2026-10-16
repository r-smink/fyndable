<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ShopifyTokenService
 *
 * Shopify no longer accepts non-expiring offline access tokens for the Admin
 * API. Expiring tokens (expires_in = 1 hour) are obtained two ways:
 *
 *  - Token exchange: an App Bridge session ID token is swapped for an access
 *    token during an active merchant session (see VerifyShopifySession).
 *  - Refresh grant: the stored refresh_token (valid ~90 days, rotated on every
 *    use) mints a new access token server-side — used by jobs and webhooks
 *    where no session exists.
 */
class ShopifyTokenService
{
    /**
     * Resolve a usable Admin API access token for the shop.
     *
     * Returns the stored token while valid; otherwise attempts a refresh-token
     * grant (the path available to background jobs). Returns null when no
     * usable credential exists — a merchant session is then required to
     * re-mint via exchange().
     */
    public function tokenFor(Shop $shop): ?string
    {
        if ($shop->hasUsableToken()) {
            return $shop->access_token;
        }

        if ($this->refresh($shop) && $shop->hasUsableToken()) {
            return $shop->access_token;
        }

        return null;
    }

    /**
     * Exchange an App Bridge session ID token for an expiring offline token.
     */
    public function exchange(Shop $shop, string $idToken): bool
    {
        try {
            $response = Http::timeout(30)->asForm()->post($this->tokenEndpoint($shop), [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                'subject_token' => $idToken,
                'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
                'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
                'expiring' => '1',
            ]);
        } catch (ConnectionException $e) {
            Log::error('ShopifyTokenService: exchange connection failed', [
                'shop' => $shop->shop_domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->failed()) {
            Log::error('ShopifyTokenService: exchange failed', [
                'shop' => $shop->shop_domain,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        }

        return $this->storeTokenResponse($shop, $response->json() ?? []);
    }

    /**
     * Refresh an expiring offline token via the stored refresh token.
     * Rotates both tokens; a 401 clears them so the next session re-mints.
     */
    public function refresh(Shop $shop): bool
    {
        if (empty($shop->refresh_token)) {
            return false;
        }

        try {
            $response = Http::timeout(30)->asForm()->post($this->tokenEndpoint($shop), [
                'client_id' => config('shopify.api_key'),
                'client_secret' => config('shopify.api_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $shop->refresh_token,
            ]);
        } catch (ConnectionException $e) {
            Log::error('ShopifyTokenService: refresh connection failed', [
                'shop' => $shop->shop_domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($response->status() === 401) {
            Log::warning('ShopifyTokenService: refresh token rejected — clearing stored tokens', [
                'shop' => $shop->shop_domain,
            ]);
            $shop->access_token = null;
            $shop->refresh_token = null;
            $shop->token_expires_at = null;
            $shop->save();

            return false;
        }

        if ($response->failed()) {
            Log::error('ShopifyTokenService: refresh failed', [
                'shop' => $shop->shop_domain,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return false;
        }

        return $this->storeTokenResponse($shop, $response->json() ?? []);
    }

    private function storeTokenResponse(Shop $shop, array $data): bool
    {
        $accessToken = $data['access_token'] ?? null;
        if (empty($accessToken)) {
            return false;
        }

        $shop->access_token = $accessToken;

        // Refresh tokens rotate — always store the newest one when present.
        if (! empty($data['refresh_token'])) {
            $shop->refresh_token = $data['refresh_token'];
        }

        // A missing/zero expires_in means the token does not expire; keep
        // token_expires_at null so hasUsableToken() still treats it as legacy
        // (Shopify rejects non-expiring tokens anyway).
        $expiresIn = (int) ($data['expires_in'] ?? 0);
        $shop->token_expires_at = $expiresIn > 0 ? now()->addSeconds($expiresIn) : null;

        if (! empty($data['scope'])) {
            $shop->scope = $data['scope'];
        }

        $shop->save();

        return true;
    }

    private function tokenEndpoint(Shop $shop): string
    {
        return "https://{$shop->shop_domain}/admin/oauth/access_token";
    }
}

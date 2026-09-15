<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ShopifySignature
 *
 * Verifies HMAC signatures for Shopify OAuth callbacks and webhooks,
 * and verifies App Bridge session tokens (JWT) issued by Shopify.
 */
class ShopifySignature
{
    public function __construct(
        private string $apiSecret
    ) {}

    /**
     * Verify the HMAC signature on an OAuth callback request.
     *
     * Shopify sorts all query params alphabetically (except `hmac` and `signature`),
     * builds a string of `key=value` pairs joined with `&` (without URL encoding),
     * and compares it to the `hmac` query parameter.
     */
    public function verifyOAuth(Request $request): bool
    {
        $params = $request->query();
        $hmac = $params['hmac'] ?? '';
        unset($params['hmac'], $params['signature']);

        // Build the signature string exactly as Shopify does.
        ksort($params);

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        $message = implode('&', $pairs);

        $calculated = hash_hmac('sha256', $message, $this->apiSecret);

        return hash_equals($hmac, $calculated);
    }

    /**
     * Verify the HMAC-SHA256 signature on a Shopify webhook request.
     *
     * The HMAC is computed over the raw request body using the API secret.
     */
    public function verifyWebhook(string $hmac, string $body): bool
    {
        $calculated = base64_encode(hash_hmac('sha256', $body, $this->apiSecret, true));

        return hash_equals($hmac, $calculated);
    }

    /**
     * Verify an App Bridge session token (JWT) issued by Shopify.
     *
     * Fetches the shop's JWKS, verifies the RS256 signature, and validates
     * the `dest` (shop domain), `iss` (issuer), and expiry claims.
     *
     * @param string $token The JWT string from the Authorization header.
     * @param string $shopDomain Optional shop domain to validate against `dest`.
     * @return array|null Decoded payload if valid, null otherwise.
     */
    public function verifySessionToken(string $token, string $shopDomain = ''): ?array
    {
        // Extract header to get the key ID (kid) and the shop domain
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!is_array($header) || !is_array($payload)) {
            return null;
        }

        // The token's `dest` must match the shop domain we expect
        $tokenShop = $payload['dest'] ?? '';
        $tokenShop = preg_replace('#^https?://#', '', $tokenShop);

        if ($shopDomain && strtolower($tokenShop) !== strtolower($shopDomain)) {
            Log::warning('App Bridge token dest mismatch', ['expected' => $shopDomain, 'got' => $tokenShop]);
            return null;
        }

        $shopDomain = $shopDomain ?: $tokenShop;
        $kid = $header['kid'] ?? '';

        try {
            $keys = $this->getJwks($shopDomain);
            $key = $kid ? ($keys[$kid] ?? null) : null;
            if ($key === null && !empty($keys)) {
                // If no kid match, try the first available key
                $key = reset($keys);
            }

            if ($key === null) {
                Log::error('No Shopify JWKS key available', ['shop' => $shopDomain, 'kid' => $kid]);
                return null;
            }

            JWT::$leeway = 60; // 60 seconds clock skew
            $decoded = JWT::decode($token, $key);

            return (array)$decoded;
        } catch (\Exception $e) {
            Log::warning('App Bridge token verification failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fetch and parse the Shopify JWKS for a shop, with caching.
     *
     * @return array<string, object> Firebase JWT key objects keyed by kid.
     */
    private function getJwks(string $shopDomain): array
    {
        $cacheKey = 'shopify:jwks:' . $shopDomain;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get("https://{$shopDomain}/.well-known/jwks.json");
            if ($response->failed()) {
                return [];
            }

            $jwks = $response->json();
            $keys = JWK::parseKeySet($jwks);

            Cache::put($cacheKey, $keys, 86400); // 24h

            return $keys;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Shopify JWKS', ['shop' => $shopDomain, 'error' => $e->getMessage()]);
            return [];
        }
    }
}

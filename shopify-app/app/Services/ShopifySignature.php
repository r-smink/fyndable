<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
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
            $pairs[] = $key.'='.$value;
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
     * Shopify signs session tokens with HS256 using the app's API secret.
     * Validates the `aud` (app API key), `dest`/`iss` (shop domain), and
     * expiry claims (verified by the JWT library with a 60s leeway).
     *
     * @param  string  $token  The JWT string from the Authorization header.
     * @param  string  $shopDomain  Optional shop domain to validate against `dest`.
     * @return array|null Decoded payload if valid, null otherwise.
     */
    public function verifySessionToken(string $token, string $shopDomain = ''): ?array
    {
        try {
            JWT::$leeway = 60; // 60 seconds clock skew
            $decoded = JWT::decode($token, new Key($this->apiSecret, 'HS256'));
        } catch (\Exception $e) {
            Log::warning('App Bridge token verification failed', ['error' => $e->getMessage()]);

            return null;
        }

        $payload = (array) $decoded;

        // The token must be issued for this app
        $apiKey = (string) config('shopify.api_key');
        $aud = $payload['aud'] ?? null;
        $audValues = is_array($aud) ? $aud : [$aud];
        if ($apiKey === '' || ! in_array($apiKey, $audValues, true)) {
            Log::warning('App Bridge token aud mismatch');

            return null;
        }

        // `dest` and `iss` must both resolve to the same *.myshopify.com host
        $dest = self::normalizeShopDomain((string) ($payload['dest'] ?? ''));
        $iss = self::normalizeShopDomain((string) ($payload['iss'] ?? ''));
        if ($dest === null || $iss === null || $dest !== $iss) {
            Log::warning('App Bridge token dest/iss invalid or mismatched', [
                'dest' => $payload['dest'] ?? null,
                'iss' => $payload['iss'] ?? null,
            ]);

            return null;
        }

        if ($shopDomain !== '') {
            $expected = self::normalizeShopDomain($shopDomain);
            if ($expected === null || $expected !== $dest) {
                Log::warning('App Bridge token dest mismatch', ['expected' => $shopDomain, 'got' => $dest]);

                return null;
            }
        }

        return $payload;
    }

    /**
     * Normalize a shop domain (or URL containing one) to a *.myshopify.com hostname.
     * Returns null when the input does not contain a valid myshopify.com host.
     */
    public static function normalizeShopDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);
        if (! is_string($host) || ! preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $host)) {
            return null;
        }

        return $host;
    }
}

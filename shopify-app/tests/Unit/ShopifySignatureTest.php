<?php

namespace Tests\Unit;

use App\Services\ShopifySignature;
use Firebase\JWT\JWT;
use Tests\TestCase;

class ShopifySignatureTest extends TestCase
{
    private const API_KEY = 'test-api-key';

    private const API_SECRET = 'test-api-secret-0123456789abcdef0123456789abcdef';

    private const SHOP = 'test-shop.myshopify.com';

    private function signature(): ShopifySignature
    {
        config(['shopify.api_key' => self::API_KEY]);

        return new ShopifySignature(self::API_SECRET);
    }

    private function makeToken(array $overrides = [], string $secret = self::API_SECRET): string
    {
        $now = time();
        $claims = array_merge([
            'iss' => 'https://'.self::SHOP.'/admin',
            'dest' => 'https://'.self::SHOP,
            'aud' => self::API_KEY,
            'sub' => '123',
            'exp' => $now + 300,
            'nbf' => $now - 10,
            'iat' => $now,
            'jti' => 'test-jti',
            'sid' => 'test-sid',
        ], $overrides);

        return JWT::encode($claims, $secret, 'HS256');
    }

    public function test_valid_hs256_token_is_accepted(): void
    {
        $payload = $this->signature()->verifySessionToken($this->makeToken());

        $this->assertIsArray($payload);
        $this->assertSame('https://'.self::SHOP, $payload['dest']);
    }

    public function test_wrong_audience_fails(): void
    {
        $payload = $this->signature()->verifySessionToken($this->makeToken(['aud' => 'other-app']));

        $this->assertNull($payload);
    }

    public function test_mismatched_shop_domain_fails(): void
    {
        $payload = $this->signature()->verifySessionToken(
            $this->makeToken(),
            'other-shop.myshopify.com'
        );

        $this->assertNull($payload);
    }

    public function test_expired_token_fails(): void
    {
        $now = time();
        $payload = $this->signature()->verifySessionToken($this->makeToken([
            'iat' => $now - 600,
            'nbf' => $now - 600,
            'exp' => $now - 120, // beyond the 60s leeway
        ]));

        $this->assertNull($payload);
    }

    public function test_wrong_secret_fails(): void
    {
        $payload = $this->signature()->verifySessionToken(
            $this->makeToken([], 'wrong-secret-0123456789abcdef0123456789abcdef')
        );

        $this->assertNull($payload);
    }

    public function test_normalize_shop_domain(): void
    {
        $this->assertSame(self::SHOP, ShopifySignature::normalizeShopDomain('Test-Shop.myshopify.com'));
        $this->assertSame(self::SHOP, ShopifySignature::normalizeShopDomain('https://test-shop.myshopify.com/admin'));
        $this->assertNull(ShopifySignature::normalizeShopDomain('example.com'));
        $this->assertNull(ShopifySignature::normalizeShopDomain('myshopify.com'));
        $this->assertNull(ShopifySignature::normalizeShopDomain(''));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Shop;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyShopifySessionTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test-api-key';

    private const API_SECRET = 'test-api-secret-0123456789abcdef0123456789abcdef';

    private const SHOP = 'test-shop.myshopify.com';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shopify.api_key' => self::API_KEY,
            'shopify.api_secret' => self::API_SECRET,
        ]);

        Shop::create([
            'shop_domain' => self::SHOP,
            'access_token' => 'shpat_test',
            'is_installed' => true,
            'is_uninstalled' => false,
        ]);
    }

    private function token(string $dest = self::SHOP): string
    {
        $now = time();

        return JWT::encode([
            'iss' => "https://{$dest}/admin",
            'dest' => "https://{$dest}",
            'aud' => self::API_KEY,
            'sub' => '123',
            'exp' => $now + 300,
            'nbf' => $now - 10,
            'iat' => $now,
            'jti' => 'jti',
            'sid' => 'sid',
        ], self::API_SECRET, 'HS256');
    }

    public function test_request_without_bearer_token_returns_401(): void
    {
        $response = $this->getJson('/api/license/status');

        $response->assertStatus(401);
        $response->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
    }

    public function test_valid_bearer_token_reaches_route(): void
    {
        $response = $this->getJson('/api/license/status', [
            'Authorization' => 'Bearer '.$this->token(),
        ]);

        // Shop has no license, so the controller returns has_license=false
        // without any external SaaS call.
        $response->assertOk();
        $response->assertJson(['has_license' => false]);
    }

    public function test_valid_token_with_mismatched_shop_query_returns_401(): void
    {
        $response = $this->getJson('/api/license/status?shop=other-shop.myshopify.com', [
            'Authorization' => 'Bearer '.$this->token(),
        ]);

        $response->assertStatus(401);
        $response->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
    }

    public function test_valid_token_with_matching_shop_query_is_accepted(): void
    {
        $response = $this->getJson('/api/license/status?shop='.self::SHOP, [
            'Authorization' => 'Bearer '.$this->token(),
        ]);

        $response->assertOk();
    }
}

<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use App\Services\ShopifyAdminWriter;
use App\Services\ShopifyContentFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeShop(array $overrides = []): Shop
    {
        return Shop::create(array_merge([
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'token_expires_at' => now()->addHour(),
            'license_key' => 'FYND-TEST',
            'tenant_key' => 'tenant-test',
            'scope' => 'read_products,write_products,read_content,write_content,read_themes,read_metaobjects,write_metaobjects,write_app_proxy',
            'is_installed' => true,
            'is_uninstalled' => false,
        ], $overrides));
    }

    private function makeController(): ProductController
    {
        $license = Mockery::mock(LicenseService::class);
        $license->shouldReceive('isActive')->andReturn(true);

        return new ProductController(
            app(ShopifyContentFetcher::class),
            app(SaasProxyClient::class),
            $license,
            app(ShopifyAdminWriter::class)
        );
    }

    private function productResponse(): array
    {
        return [
            'data' => [
                'product' => [
                    'id' => 'gid://shopify/Product/123',
                    'handle' => 'test-product',
                    'title' => 'Test Product',
                    'description' => 'A test product',
                    'descriptionHtml' => '<p>A test product</p>',
                    'productType' => 'Shoes',
                    'vendor' => 'TestBrand',
                    'tags' => [],
                    'onlineStoreUrl' => 'https://test-shop.myshopify.com/products/test-product',
                    'featuredImage' => ['url' => 'https://cdn.example.com/img.png', 'altText' => null],
                    'images' => ['edges' => []],
                    'variants' => ['edges' => [['node' => ['sku' => 'T1', 'price' => '19.99', 'compareAtPrice' => null, 'availableForSale' => true]]]],
                ],
            ],
        ];
    }

    public function test_save_meta_sends_product_update_with_seo(): void
    {
        $shop = $this->makeShop();

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productUpdate' => [
                        'product' => ['id' => 'gid://shopify/Product/123'],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $request = Request::create('/api/products/123/save-meta', 'POST', [
            'title' => 'SEO Title',
        ]);
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->saveMeta($request, '123');

        $this->assertSame(['success' => true], $result);

        Http::assertSent(function ($request) use ($shop) {
            return str_contains($request->url(), "https://{$shop->shop_domain}/admin/api/")
                && str_contains($request['query'], 'productUpdate')
                && $request['variables']['product']['id'] === 'gid://shopify/Product/123'
                && $request['variables']['product']['seo']['title'] === 'SEO Title';
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request['query'] ?? '', 'metafieldsSet');
        });
    }

    public function test_generate_schema_returns_preview_without_saving(): void
    {
        $shop = $this->makeShop();

        Http::fake([
            '*' => Http::response($this->productResponse(), 200),
        ]);

        $request = Request::create('/api/products/123/generate-schema', 'POST');
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->generateSchema($request, '123');

        $this->assertTrue($result['success']);
        $this->assertSame('Product', $result['schema']['@type']);
        $this->assertSame('Test Product', $result['schema']['name']);

        // Preview only — nothing may be written to Shopify
        Http::assertNotSent(function ($request) {
            return str_contains($request['query'] ?? '', 'metafieldsSet');
        });
    }

    public function test_save_schema_writes_json_metafield(): void
    {
        $shop = $this->makeShop();

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'metafieldsSet' => [
                        'metafields' => [['id' => 'gid://shopify/Metafield/1']],
                        'userErrors' => [],
                    ],
                ],
            ], 200),
        ]);

        $schema = ['@context' => 'https://schema.org/', '@type' => 'Product', 'name' => 'Custom'];
        $request = Request::create('/api/products/123/save-schema', 'POST', ['schema' => $schema]);
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->saveSchema($request, '123');

        $this->assertSame(['success' => true], $result);

        Http::assertSent(function ($request) {
            $metafield = $request['variables']['metafields'][0] ?? [];

            return str_contains($request['query'], 'metafieldsSet')
                && $metafield['namespace'] === 'fyndable'
                && $metafield['key'] === 'product_schema'
                && $metafield['type'] === 'json'
                && $metafield['ownerId'] === 'gid://shopify/Product/123';
        });
    }

    public function test_save_schema_rejects_invalid_schema(): void
    {
        $shop = $this->makeShop();
        Http::fake();

        $request = Request::create('/api/products/123/save-schema', 'POST', [
            'schema' => ['name' => 'No type'],
        ]);
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->saveSchema($request, '123');

        $this->assertSame('invalid_schema', $result['error']);
        Http::assertNothingSent();
    }

    public function test_save_meta_surfaces_shopify_user_errors(): void
    {
        $shop = $this->makeShop();

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productUpdate' => [
                        'product' => null,
                        'userErrors' => [['field' => ['seo', 'title'], 'message' => 'is too long']],
                    ],
                ],
            ], 200),
        ]);

        $request = Request::create('/api/products/123/save-meta', 'POST', ['title' => 'X']);
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->saveMeta($request, '123');

        $this->assertSame('save_failed', $result['error']);
        $this->assertStringContainsString('is too long', $result['message']);
    }

    public function test_missing_scopes_detects_legacy_token(): void
    {
        $shop = $this->makeShop(['scope' => 'read_products,read_content']);

        $missing = $shop->missingScopes();

        $this->assertContains('write_products', $missing);
        $this->assertContains('write_content', $missing);
        $this->assertNotContains('read_products', $missing);
    }
}

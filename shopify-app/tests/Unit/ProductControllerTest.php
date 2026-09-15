<?php

namespace Tests\Unit;

use App\Http\Controllers\ProductController;
use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use App\Services\ShopifyContentFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_meta_sends_owner_id_in_metafields(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'license_key' => 'FYND-TEST',
            'tenant_key' => 'tenant-test',
            'is_installed' => true,
            'is_uninstalled' => false,
        ]);

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

        $license = Mockery::mock(LicenseService::class);
        $license->shouldReceive('isActive')->andReturn(true);

        $controller = new ProductController(
            app(ShopifyContentFetcher::class),
            app(SaasProxyClient::class),
            $license
        );

        $request = Request::create('/api/products/123/save-meta', 'POST', [
            'title' => 'SEO Title',
        ]);
        $request->attributes->set('shop', $shop);

        $result = $controller->saveMeta($request, '123');

        $this->assertSame(['success' => true], $result);

        Http::assertSent(function ($request) use ($shop) {
            return str_contains($request->url(), "https://{$shop->shop_domain}/admin/api/")
                && $request['variables']['metafields'][0]['ownerId'] === 'gid://shopify/Product/123'
                && $request['variables']['metafields'][0]['namespace'] === 'seo'
                && $request['variables']['metafields'][0]['key'] === 'title';
        });
    }
}

<?php

namespace Tests\Unit;

use App\Http\Controllers\ContentController;
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

class ContentControllerTest extends TestCase
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

    private function makeController(): ContentController
    {
        $license = Mockery::mock(LicenseService::class);
        $license->shouldReceive('isActive')->andReturn(true);

        return new ContentController(
            app(ShopifyContentFetcher::class),
            app(SaasProxyClient::class),
            $license,
            app(ShopifyAdminWriter::class)
        );
    }

    public function test_index_lists_pages(): void
    {
        $shop = $this->makeShop();

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'pages' => [
                        'edges' => [
                            ['node' => ['id' => 'gid://shopify/Page/10', 'title' => 'About', 'handle' => 'about']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $request = Request::create('/api/content/page', 'GET');
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->index($request, 'page');

        $this->assertTrue($result['success']);
        $this->assertSame('About', $result['items'][0]['title']);
        $this->assertSame('gid://shopify/Page/10', $result['items'][0]['id']);
    }

    public function test_save_meta_writes_global_metafields_for_page(): void
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

        $request = Request::create('/api/content/page/10/save-meta', 'POST', [
            'title' => 'SEO title',
            'description' => 'SEO description',
        ]);
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->saveMeta($request, 'page', '10');

        $this->assertSame(['success' => true], $result);

        Http::assertSent(function ($request) {
            $metafields = $request['variables']['metafields'] ?? [];

            return str_contains($request['query'], 'metafieldsSet')
                && count($metafields) === 2
                && $metafields[0]['namespace'] === 'global'
                && $metafields[0]['key'] === 'title_tag'
                && $metafields[0]['ownerId'] === 'gid://shopify/Page/10'
                && $metafields[1]['key'] === 'description_tag';
        });
    }

    public function test_save_meta_blocked_when_write_content_scope_missing(): void
    {
        $shop = $this->makeShop(['scope' => 'read_products,write_products,read_content']);
        Http::fake();

        $request = Request::create('/api/content/page/10/save-meta', 'POST', ['title' => 'x']);
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->saveMeta($request, 'page', '10');

        $this->assertSame('missing_scopes', $result['error']);
        $this->assertContains('write_content', $result['missing_scopes']);
        $this->assertStringContainsString('/install?shop=', $result['reauth_url']);
        Http::assertNothingSent();
    }

    public function test_generate_schema_builds_collection_page_schema(): void
    {
        $shop = $this->makeShop();

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'node' => [
                        '__typename' => 'Collection',
                        'id' => 'gid://shopify/Collection/5',
                        'title' => 'Summer Sale',
                        'handle' => 'summer-sale',
                        'description' => 'Summer products',
                        'descriptionHtml' => '<p>Summer products</p>',
                        'onlineStoreUrl' => 'https://test-shop.myshopify.com/collections/summer-sale',
                        'seo' => ['title' => null, 'description' => null],
                        'image' => null,
                        'metafields' => ['edges' => []],
                    ],
                ],
            ], 200),
        ]);

        $request = Request::create('/api/content/collection/5/generate-schema', 'POST');
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->generateSchema($request, 'collection', '5');

        $this->assertTrue($result['success']);
        $this->assertSame('CollectionPage', $result['schema']['@type']);
        $this->assertSame('Summer Sale', $result['schema']['name']);

        Http::assertNotSent(function ($request) {
            return str_contains($request['query'] ?? '', 'metafieldsSet');
        });
    }

    public function test_invalid_type_is_rejected(): void
    {
        $shop = $this->makeShop();
        Http::fake();

        $request = Request::create('/api/content/order', 'GET');
        $request->attributes->set('shop', $shop);

        $result = $this->makeController()->index($request, 'order');

        $this->assertSame('invalid_type', $result['error']);
        Http::assertNothingSent();
    }
}

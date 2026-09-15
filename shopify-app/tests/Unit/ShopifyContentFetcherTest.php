<?php

namespace Tests\Unit;

use App\Models\LlmsTxtSettings;
use App\Models\Shop;
use App\Services\LlmsTxtGenerator;
use App\Services\ShopifyContentFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopifyContentFetcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_product_count_returns_api_count(): void
    {
        config(['shopify.api_version' => '2026-07']);

        $shop = Shop::create([
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'is_installed' => true,
            'is_uninstalled' => false,
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'productsCount' => ['count' => 42, 'precision' => 'EXACT'],
                ],
            ], 200),
        ]);

        $count = app(ShopifyContentFetcher::class)->getProductCount($shop);

        $this->assertSame(42, $count);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/admin/api/2026-07/graphql.json')
                && str_contains($request['query'], 'productsCount');
        });
    }

    public function test_default_api_version_is_2026_07(): void
    {
        $this->assertSame('2026-07', config('shopify.api_version'));
    }

    public function test_get_collections_preserves_count_object(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'is_installed' => true,
            'is_uninstalled' => false,
        ]);

        Http::fake([
            '*' => Http::response([
                'data' => [
                    'collections' => [
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'edges' => [[
                            'node' => [
                                'id' => 'gid://shopify/Collection/5',
                                'handle' => 'summer',
                                'title' => 'Summer',
                                'description' => 'Summer collection',
                                'descriptionHtml' => 'Summer collection',
                                'onlineStoreUrl' => 'https://test-shop.myshopify.com/collections/summer',
                                'productsCount' => ['count' => 7, 'precision' => 'EXACT'],
                                'image' => null,
                            ],
                        ]],
                    ],
                ],
            ], 200),
        ]);

        $collections = app(ShopifyContentFetcher::class)->getCollections($shop);

        $this->assertCount(1, $collections);
        $this->assertSame(7, $collections[0]['productsCount']['count']);
    }

    public function test_llms_full_renders_collection_product_count(): void
    {
        $shop = Shop::create([
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'is_installed' => true,
            'is_uninstalled' => false,
        ]);

        $settings = new LlmsTxtSettings([
            'include_products' => false,
            'include_collections' => true,
            'include_pages' => false,
            'include_blogs' => false,
            'max_collections' => 50,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request['query'], 'collections(')) {
                return Http::response([
                    'data' => [
                        'collections' => [
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                            'edges' => [[
                                'node' => [
                                    'id' => 'gid://shopify/Collection/5',
                                    'handle' => 'summer',
                                    'title' => 'Summer',
                                    'description' => 'Summer collection',
                                    'onlineStoreUrl' => 'https://test-shop.myshopify.com/collections/summer',
                                    'productsCount' => ['count' => 7, 'precision' => 'EXACT'],
                                ],
                            ]],
                        ],
                    ],
                ], 200);
            }

            // shop details query
            return Http::response([
                'data' => ['shop' => [
                    'name' => 'Test Shop',
                    'primaryDomain' => ['url' => 'https://test-shop.myshopify.com'],
                    'myshopifyDomain' => 'test-shop.myshopify.com',
                    'currencyCode' => 'USD',
                    'billingAddress' => ['country' => 'US'],
                ]],
            ], 200);
        });

        $full = app(LlmsTxtGenerator::class)->generateFull($shop, $settings);

        $this->assertStringContainsString('Products in collection: 7', $full);
    }
}

<?php

namespace Tests\Unit;

use App\Jobs\BulkOptimizeJob;
use App\Models\Shop;
use App\Services\SaasProxyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class BulkOptimizeJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeShop(): Shop
    {
        return Shop::create([
            'shop_domain' => 'test-shop.myshopify.com',
            'access_token' => 'shpat_test',
            'token_expires_at' => now()->addHour(),
            'license_key' => 'FYND-TEST',
            'tenant_key' => 'tenant-test',
            'is_installed' => true,
            'is_uninstalled' => false,
        ]);
    }

    public function test_cancelled_job_retains_cancelled_status(): void
    {
        $shop = $this->makeShop();

        $jobKey = "bulk:{$shop->id}:meta";
        Cache::put($jobKey, [
            'action' => 'meta',
            'total' => 2,
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'status' => 'cancelled',
        ], 300);

        $job = new BulkOptimizeJob($shop->id, 'meta', ['gid://shopify/Product/1']);
        $job->handle();

        $status = Cache::get($jobKey);
        $this->assertSame('cancelled', $status['status']);
        $this->assertArrayNotHasKey('completed_at', $status);
    }

    public function test_mid_run_cancellation_is_not_overwritten_with_completed(): void
    {
        $shop = $this->makeShop();

        $jobKey = "bulk:{$shop->id}:meta";
        $running = [
            'action' => 'meta',
            'total' => 1,
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'status' => 'running',
        ];
        $cancelled = array_merge($running, ['status' => 'cancelled']);

        // First get() is the initial progress read (running); the in-loop get()
        // sees the cancellation; the post-loop get() reads the cancelled entry
        // the job must preserve.
        Cache::shouldReceive('get')
            ->with($jobKey, [])
            ->andReturn($running, $cancelled, $cancelled);

        $written = null;
        Cache::shouldReceive('put')
            ->with($jobKey, Mockery::capture($written), 300)
            ->andReturnTrue();

        (new BulkOptimizeJob($shop->id, 'meta', ['gid://shopify/Product/1']))->handle();

        $this->assertIsArray($written);
        $this->assertSame('cancelled', $written['status']);
        $this->assertArrayNotHasKey('completed_at', $written);
    }

    public function test_titles_action_sends_product_update_with_title(): void
    {
        $shop = $this->makeShop();
        $jobKey = "bulk:{$shop->id}:titles";
        Cache::put($jobKey, ['status' => 'running', 'total' => 1], 3600);

        $saas = Mockery::mock(SaasProxyClient::class);
        $saas->shouldReceive('aiGenerate')->andReturn(['text' => 'Better SEO Title']);
        $this->app->instance(SaasProxyClient::class, $saas);

        Http::fake(function ($request) {
            if (str_contains($request['query'], 'productUpdate')) {
                return Http::response([
                    'data' => ['productUpdate' => ['product' => ['id' => 'gid://shopify/Product/1'], 'userErrors' => []]],
                ], 200);
            }

            return Http::response([
                'data' => ['product' => [
                    'id' => 'gid://shopify/Product/1',
                    'title' => 'Old Title',
                    'description' => 'Desc',
                    'productType' => 'Shoes',
                    'vendor' => 'Acme',
                    'tags' => [],
                    'media' => ['nodes' => []],
                ]],
            ], 200);
        });

        (new BulkOptimizeJob($shop->id, 'titles', ['gid://shopify/Product/1']))->handle();

        Http::assertSent(function ($request) {
            return str_contains($request['query'], 'productUpdate')
                && $request['variables']['product']['id'] === 'gid://shopify/Product/1'
                && $request['variables']['product']['title'] === 'Better SEO Title';
        });
    }

    public function test_meta_action_sends_product_update_with_seo_description(): void
    {
        $shop = $this->makeShop();
        $jobKey = "bulk:{$shop->id}:meta";
        Cache::put($jobKey, ['status' => 'running', 'total' => 1], 3600);

        $saas = Mockery::mock(SaasProxyClient::class);
        $saas->shouldReceive('aiGenerate')->andReturn(['text' => 'New meta description']);
        $this->app->instance(SaasProxyClient::class, $saas);

        Http::fake(function ($request) {
            if (str_contains($request['query'], 'productUpdate')) {
                return Http::response([
                    'data' => ['productUpdate' => ['product' => ['id' => 'gid://shopify/Product/1'], 'userErrors' => []]],
                ], 200);
            }

            return Http::response([
                'data' => ['product' => [
                    'id' => 'gid://shopify/Product/1',
                    'title' => 'Title',
                    'description' => 'Desc',
                    'productType' => '',
                    'vendor' => '',
                    'tags' => [],
                    'media' => ['nodes' => []],
                ]],
            ], 200);
        });

        (new BulkOptimizeJob($shop->id, 'meta', ['gid://shopify/Product/1']))->handle();

        Http::assertSent(function ($request) {
            return str_contains($request['query'], 'productUpdate')
                && $request['variables']['product']['id'] === 'gid://shopify/Product/1'
                && $request['variables']['product']['seo']['description'] === 'New meta description';
        });
    }

    public function test_alt_text_action_sends_product_update_media(): void
    {
        $shop = $this->makeShop();
        $jobKey = "bulk:{$shop->id}:alt_text";
        Cache::put($jobKey, ['status' => 'running', 'total' => 1], 3600);

        $saas = Mockery::mock(SaasProxyClient::class);
        $saas->shouldReceive('aiGenerate')->andReturn(['text' => 'A descriptive alt text']);
        $this->app->instance(SaasProxyClient::class, $saas);

        Http::fake(function ($request) {
            if (str_contains($request['query'], 'productUpdateMedia')) {
                return Http::response([
                    'data' => ['productUpdateMedia' => [
                        'media' => [['id' => 'gid://shopify/MediaImage/9', 'alt' => 'A descriptive alt text']],
                        'mediaUserErrors' => [],
                    ]],
                ], 200);
            }

            return Http::response([
                'data' => ['product' => [
                    'id' => 'gid://shopify/Product/1',
                    'title' => 'Title',
                    'description' => 'Desc',
                    'productType' => '',
                    'vendor' => '',
                    'tags' => [],
                    'media' => ['nodes' => [
                        [
                            'id' => 'gid://shopify/MediaImage/9',
                            'alt' => null,
                            'mediaContentType' => 'IMAGE',
                            'image' => ['url' => 'https://cdn.example.com/img.jpg'],
                        ],
                    ]],
                ]],
            ], 200);
        });

        (new BulkOptimizeJob($shop->id, 'alt_text', ['gid://shopify/Product/1']))->handle();

        Http::assertSent(function ($request) {
            return str_contains($request['query'], 'productUpdateMedia')
                && $request['variables']['productId'] === 'gid://shopify/Product/1'
                && $request['variables']['media'][0]['id'] === 'gid://shopify/MediaImage/9'
                && $request['variables']['media'][0]['alt'] === 'A descriptive alt text';
        });
    }
}

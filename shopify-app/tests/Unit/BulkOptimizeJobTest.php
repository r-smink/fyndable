<?php

namespace Tests\Unit;

use App\Jobs\BulkOptimizeJob;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
}

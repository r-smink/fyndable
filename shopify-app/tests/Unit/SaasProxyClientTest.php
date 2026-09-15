<?php

namespace Tests\Unit;

use App\Services\SaasProxyClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaasProxyClientTest extends TestCase
{
    public function test_serp_rank_check_sends_target_url(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $client = new SaasProxyClient;
        $client->serpRankCheck('LIC', 'TENANT', 'blue sneakers', 'https://store.example.com/products/x');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/serp/rank-check')
                && $request['target_url'] === 'https://store.example.com/products/x'
                && ! isset($request['url'])
                && $request['keyword'] === 'blue sneakers';
        });
    }
}

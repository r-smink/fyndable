<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manage Shopify URL redirects via the REST Admin API.
 *
 * The REST endpoint for redirects is covered by the `write_content` scope,
 * which is already requested by the Fyndable app. This is a workaround for
 * the fact that Shopify App Proxy cannot serve content at the root domain.
 *
 *   /llms.txt     -> /apps/fyndable/llms.txt
 *   /llms-full.txt -> /apps/fyndable/llms.txt?full=1
 */
class ShopifyUrlRedirect
{
    public function setup(Shop $shop, string $subpath = 'fyndable', string $prefix = 'apps'): array
    {
        $apiVersion = config('shopify.api_version');
        $base = "https://{$shop->shop_domain}/admin/api/{$apiVersion}";

        $redirects = [
            ['path' => '/llms.txt', 'target' => "/{$prefix}/{$subpath}/llms.txt"],
            ['path' => '/llms-full.txt', 'target' => "/{$prefix}/{$subpath}/llms.txt?full=1"],
        ];

        $results = [];

        foreach ($redirects as $redirect) {
            $results[] = $this->ensureRedirect($shop, $base, $redirect['path'], $redirect['target']);
        }

        return $results;
    }

    /**
     * Ensure a single redirect exists (create or update).
     */
    public function ensure(Shop $shop, string $path, string $target): array
    {
        $base = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version');

        return $this->ensureRedirect($shop, $base, $path, $target);
    }

    private function ensureRedirect(Shop $shop, string $base, string $path, string $target): array
    {
        $existing = $this->findRedirect($shop, $base, $path);

        if ($existing && $existing['target'] === $target) {
            return ['path' => $path, 'status' => 'exists', 'id' => $existing['id'], 'target' => $target];
        }

        if ($existing) {
            return $this->updateRedirect($shop, $base, $existing['id'], $path, $target);
        }

        return $this->createRedirect($shop, $base, $path, $target);
    }

    private function findRedirect(Shop $shop, string $base, string $path): ?array
    {
        $accessToken = app(ShopifyTokenService::class)->tokenFor($shop);
        if ($accessToken === null) {
            return null;
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
            ->get("{$base}/redirects.json", ['path' => $path, 'limit' => 1]);

        if ($response->failed()) {
            Log::error('ShopifyUrlRedirect: find failed', [
                'shop' => $shop->shop_domain,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $redirects = $data['redirects'] ?? [];

        return $redirects[0] ?? null;
    }

    private function createRedirect(Shop $shop, string $base, string $path, string $target): array
    {
        $accessToken = app(ShopifyTokenService::class)->tokenFor($shop);
        if ($accessToken === null) {
            return ['path' => $path, 'status' => 'error', 'message' => 'no usable access token'];
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
            ->post("{$base}/redirects.json", [
                'redirect' => ['path' => $path, 'target' => $target],
            ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];
            Log::error('ShopifyUrlRedirect: create failed', [
                'shop' => $shop->shop_domain,
                'path' => $path,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'path' => $path,
                'status' => 'error',
                'message' => $body['errors'] ?? $response->body(),
            ];
        }

        $data = $response->json();
        $redirect = $data['redirect'] ?? null;

        return [
            'path' => $path,
            'status' => 'created',
            'id' => $redirect['id'] ?? null,
            'target' => $target,
        ];
    }

    private function updateRedirect(Shop $shop, string $base, int $id, string $path, string $target): array
    {
        $accessToken = app(ShopifyTokenService::class)->tokenFor($shop);
        if ($accessToken === null) {
            return ['path' => $path, 'status' => 'error', 'message' => 'no usable access token'];
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
            ->put("{$base}/redirects/{$id}.json", [
                'redirect' => ['id' => $id, 'path' => $path, 'target' => $target],
            ]);

        if ($response->failed()) {
            $body = $response->json() ?? [];
            Log::error('ShopifyUrlRedirect: update failed', [
                'shop' => $shop->shop_domain,
                'id' => $id,
                'path' => $path,
                'status' => $response->status(),
                'body' => $body,
            ]);

            return [
                'path' => $path,
                'status' => 'error',
                'message' => $body['errors'] ?? $response->body(),
            ];
        }

        return [
            'path' => $path,
            'status' => 'updated',
            'id' => $id,
            'target' => $target,
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\SaasProxyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkOptimizeJob
 *
 * Processes bulk SEO optimization for Shopify products in the background.
 * Handles four actions: titles, meta, alt_text, descriptions.
 *
 * Progress is tracked in the cache so the frontend can poll it.
 */
class BulkOptimizeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes

    public int $tries = 1;

    public function __construct(
        private int $shopId,
        private string $action,
        private array $productIds
    ) {}

    public function handle(): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->hasAccessToken() || ! $shop->hasLicense()) {
            Log::warning('BulkOptimizeJob: shop not ready', ['shop_id' => $this->shopId]);

            return;
        }

        $saas = app(SaasProxyClient::class);
        $jobKey = "bulk:{$this->shopId}:{$this->action}";
        $progress = Cache::get($jobKey, []);

        if (($progress['status'] ?? '') === 'cancelled') {
            Log::info('BulkOptimizeJob: cancelled before start', ['shop_id' => $this->shopId]);

            return;
        }

        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $cancelled = false;

        foreach ($this->productIds as $productGid) {
            // Check for cancellation
            $current = Cache::get($jobKey, []);
            if (($current['status'] ?? '') === 'cancelled') {
                Log::info('BulkOptimizeJob: cancelled mid-run', ['shop_id' => $this->shopId, 'processed' => $processed]);
                $cancelled = true;
                break;
            }

            try {
                $result = match ($this->action) {
                    'titles' => $this->optimizeTitle($shop, $saas, $productGid),
                    'meta' => $this->generateMeta($shop, $saas, $productGid),
                    'alt_text' => $this->generateAltText($shop, $saas, $productGid),
                    'descriptions' => $this->optimizeDescription($shop, $saas, $productGid),
                    default => false,
                };

                $result ? $succeeded++ : $failed++;
            } catch (\Exception $e) {
                Log::error('BulkOptimizeJob: product failed', [
                    'product' => $productGid,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            $processed++;

            // Update progress
            Cache::put($jobKey, array_merge($progress, [
                'processed' => $processed,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'status' => 'running',
            ]), 3600);

            // Rate limit: pause between products to respect API limits
            usleep(500000); // 0.5 seconds
        }

        if ($cancelled) {
            // Preserve the cancelled status written by the cancel endpoint
            $current = Cache::get($jobKey, []);
            Cache::put($jobKey, array_merge($current, [
                'processed' => $processed,
                'succeeded' => $succeeded,
                'failed' => $failed,
                'status' => 'cancelled',
            ]), 300);

            Log::info('BulkOptimizeJob: cancelled', [
                'shop_id' => $this->shopId,
                'action' => $this->action,
                'processed' => $processed,
                'succeeded' => $succeeded,
                'failed' => $failed,
            ]);

            return;
        }

        // Mark as complete
        Cache::put($jobKey, array_merge($progress, [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
        ]), 3600);

        Log::info('BulkOptimizeJob: completed', [
            'shop_id' => $this->shopId,
            'action' => $this->action,
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ]);
    }

    /**
     * Optimize a product title via AI and save to metafields.
     */
    private function optimizeTitle(Shop $shop, SaasProxyClient $saas, string $productGid): bool
    {
        $product = $this->getProductMinimal($shop, $productGid);
        if (! $product) {
            return false;
        }

        $vendor = $product['vendor'] ?? '';
        $productType = $product['productType'] ?? '';
        $prompt = "Improve this product title for SEO while keeping the brand name. Keep it under 60 characters.\n\n"
            ."Current title: {$product['title']}\n"
            ."Vendor: {$vendor}\n"
            ."Type: {$productType}\n\n"
            .'Return only the improved title, nothing else.';

        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert specializing in e-commerce product titles.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $saas->aiGenerate($shop->license_key, $shop->tenant_key, $messages, 'openai/gpt-4o-mini', 100, 0.5, 'bulk_title');
        if (isset($result['error']) || empty($result['text'])) {
            return false;
        }

        $title = trim($result['text']);

        return $this->saveMetafield($shop, 'product', $productGid, 'seo', 'title', $title, 'single_line_text_field');
    }

    /**
     * Generate a meta description for a product.
     */
    private function generateMeta(Shop $shop, SaasProxyClient $saas, string $productGid): bool
    {
        $product = $this->getProductMinimal($shop, $productGid);
        if (! $product) {
            return false;
        }

        $prompt = "Write an SEO meta description (max 155 characters) for this product.\n\n"
            ."Title: {$product['title']}\n"
            .'Description: '.strip_tags($product['description'] ?? '')."\n\n"
            .'Return only the meta description, nothing else.';

        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert. Write concise, compelling meta descriptions.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $saas->aiGenerate($shop->license_key, $shop->tenant_key, $messages, 'openai/gpt-4o-mini', 200, 0.5, 'bulk_meta');
        if (isset($result['error']) || empty($result['text'])) {
            return false;
        }

        $description = trim($result['text']);

        return $this->saveMetafield($shop, 'product', $productGid, 'seo', 'description', $description, 'multi_line_text_field');
    }

    /**
     * Generate alt text for the first product image.
     */
    private function generateAltText(Shop $shop, SaasProxyClient $saas, string $productGid): bool
    {
        $product = $this->getProductMinimal($shop, $productGid);
        if (! $product || empty($product['featuredImage']['url'])) {
            return false;
        }

        $imageUrl = $product['featuredImage']['url'];
        $productName = $product['title'];

        $messages = [
            ['role' => 'system', 'content' => 'You are an expert at writing SEO alt text for product images. Max 125 characters.'],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => "Write alt text for this image of: {$productName}"],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ]],
        ];

        $result = $saas->aiGenerate($shop->license_key, $shop->tenant_key, $messages, 'openai/gpt-4o-mini', 100, 0.3, 'bulk_alt_text');
        if (isset($result['error']) || empty($result['text'])) {
            return false;
        }

        $altText = trim($result['text']);

        return $this->saveMetafield($shop, 'product', $productGid, 'fyndable', 'image_alt_text', $altText, 'single_line_text_field');
    }

    /**
     * Optimize a product description via AI.
     */
    private function optimizeDescription(Shop $shop, SaasProxyClient $saas, string $productGid): bool
    {
        $product = $this->getProductMinimal($shop, $productGid);
        if (! $product) {
            return false;
        }

        $vendor = $product['vendor'] ?? '';
        $productType = $product['productType'] ?? '';
        $description = strip_tags($product['description'] ?? '');
        $prompt = "Rewrite this product description to be more SEO-optimized. Keep it under 500 words. Do not use HTML.\n\n"
            ."Title: {$product['title']}\n"
            ."Current description: {$description}\n"
            ."Vendor: {$vendor}\n"
            ."Type: {$productType}\n\n"
            .'Return only the improved description.';

        $messages = [
            ['role' => 'system', 'content' => 'You are an expert e-commerce copywriter.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $saas->aiGenerate($shop->license_key, $shop->tenant_key, $messages, 'openai/gpt-4o-mini', 1000, 0.7, 'bulk_description');
        if (isset($result['error']) || empty($result['text'])) {
            return false;
        }

        $description = trim($result['text']);

        return $this->saveMetafield($shop, 'product', $productGid, 'fyndable', 'optimized_description', $description, 'multi_line_text_field');
    }

    /**
     * Fetch minimal product data for AI processing.
     */
    private function getProductMinimal(Shop $shop, string $productGid): ?array
    {
        $query = <<<'GRAPHQL'
        query getProduct($id: ID!) {
          product(id: $id) {
            id
            title
            description
            productType
            vendor
            tags
            featuredImage { url altText }
          }
        }
        GRAPHQL;

        $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/graphql.json';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'query' => $query,
                    'variables' => ['id' => $productGid],
                ]);
        } catch (ConnectionException $e) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        return $response->json('data.product');
    }

    /**
     * Save a metafield to a Shopify resource.
     */
    private function saveMetafield(Shop $shop, string $ownerType, string $ownerGid, string $namespace, string $key, string $value, string $type): bool
    {
        $mutation = <<<'GRAPHQL'
        mutation setMetafield($metafields: [MetafieldsSetInput!]!) {
          metafieldsSet(metafields: $metafields) {
            metafields { id }
            userErrors { field message }
          }
        }
        GRAPHQL;

        $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/graphql.json';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'query' => $mutation,
                    'variables' => [
                        'metafields' => [
                            [
                                'ownerId' => $ownerGid,
                                'namespace' => $namespace,
                                'key' => $key,
                                'value' => $value,
                                'type' => $type,
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            return false;
        }

        if ($response->failed()) {
            return false;
        }

        $errors = $response->json('data.metafieldsSet.userErrors', []);

        return empty($errors);
    }
}

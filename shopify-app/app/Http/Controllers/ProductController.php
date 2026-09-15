<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProductController
 *
 * Handles AI-powered product SEO:
 *  - Generate product descriptions (short/long)
 *  - Generate SEO meta title + description
 *  - Generate image alt text (vision model)
 *  - Inject Product JSON-LD schema via Shopify metafields
 */
class ProductController extends Controller
{
    private const METAFIELD_NAMESPACE = 'fyndable';

    private const METAFIELD_NAMESPACE_SEO = 'seo';

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private SaasProxyClient $saas,
        private LicenseService $license
    ) {}

    /**
     * Generate an AI product description.
     *
     * POST /api/products/{productId}/generate-description
     * Body: { type: "short"|"long" }
     */
    public function generateDescription(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $type = $request->input('type', 'long');
        $product = $this->getProduct($shop, $productId);
        if (isset($product['error'])) {
            return $product;
        }

        $prompt = $this->buildDescriptionPrompt($product, $type);
        $messages = [
            ['role' => 'system', 'content' => 'You are an expert e-commerce copywriter who writes SEO-optimized product descriptions.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $this->saas->aiGenerate(
            $shop->license_key,
            $shop->tenant_key,
            $messages,
            'openai/gpt-4o-mini',
            $type === 'short' ? 300 : 1000,
            0.7,
            'product_description'
        );

        if (isset($result['error'])) {
            return $result;
        }

        return [
            'success' => true,
            'description' => $result['text'],
            'type' => $type,
        ];
    }

    /**
     * Generate SEO meta title + description for a product.
     *
     * POST /api/products/{productId}/generate-meta
     */
    public function generateMeta(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $product = $this->getProduct($shop, $productId);
        if (isset($product['error'])) {
            return $product;
        }

        $prompt = $this->buildMetaPrompt($product);
        $messages = [
            ['role' => 'system', 'content' => 'You are an SEO expert. Generate a concise meta title (max 60 chars) and meta description (max 155 chars) for a product. Respond in JSON: {"title": "...", "description": "..."}'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $result = $this->saas->aiGenerate(
            $shop->license_key,
            $shop->tenant_key,
            $messages,
            'openai/gpt-4o-mini',
            300,
            0.5,
            'product_meta'
        );

        if (isset($result['error'])) {
            return $result;
        }

        // Try to parse JSON response
        $text = $result['text'];
        $parsed = $this->parseJsonResponse($text);

        return [
            'success' => true,
            'title' => $parsed['title'] ?? '',
            'description' => $parsed['description'] ?? '',
            'raw' => $text,
        ];
    }

    /**
     * Generate alt text for a product image (vision model).
     *
     * POST /api/products/{productId}/generate-alt-text
     * Body: { image_url: "..." }
     */
    public function generateAltText(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $imageUrl = $request->input('image_url', '');
        if (empty($imageUrl)) {
            return ['error' => 'image_url_required'];
        }

        $product = $this->getProduct($shop, $productId);
        if (isset($product['error'])) {
            return $product;
        }

        $productName = $product['title'] ?? 'this product';
        $prompt = "Write a concise, descriptive alt text (max 125 chars) for this product image of: {$productName}. Focus on what the image shows for SEO and accessibility.";

        $messages = [
            ['role' => 'system', 'content' => 'You are an expert at analyzing images and writing concise, descriptive alt text for SEO and accessibility.'],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
            ]],
        ];

        $result = $this->saas->aiGenerate(
            $shop->license_key,
            $shop->tenant_key,
            $messages,
            'openai/gpt-4o-mini',
            100,
            0.3,
            'image_alt_text'
        );

        if (isset($result['error'])) {
            return $result;
        }

        return [
            'success' => true,
            'alt_text' => trim($result['text']),
            'image_url' => $imageUrl,
        ];
    }

    /**
     * Save SEO meta to Shopify product metafields.
     *
     * POST /api/products/{productId}/save-meta
     * Body: { title: "...", description: "..." }
     */
    public function saveMeta(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $title = $request->input('title', '');
        $description = $request->input('description', '');

        if (empty($title) && empty($description)) {
            return ['error' => 'nothing_to_save'];
        }

        $metafields = [];
        if (! empty($title)) {
            $metafields[] = $this->metafieldInput('seo', 'title', $title, 'single_line_text_field');
        }
        if (! empty($description)) {
            $metafields[] = $this->metafieldInput('seo', 'description', $description, 'multi_line_text_field');
        }

        $result = $this->createMetafields($shop, 'product', $productId, $metafields);

        return $result
            ? ['success' => true]
            : ['error' => 'save_failed'];
    }

    /**
     * Generate and save Product JSON-LD schema to metafields.
     *
     * POST /api/products/{productId}/generate-schema
     */
    public function generateSchema(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $product = $this->getProduct($shop, $productId);
        if (isset($product['error'])) {
            return $product;
        }

        $schema = $this->buildProductSchema($product, $shop);

        $metafield = $this->metafieldInput(
            self::METAFIELD_NAMESPACE,
            'product_schema',
            json_encode($schema, JSON_UNESCAPED_SLASHES),
            'json'
        );

        $result = $this->createMetafields($shop, 'product', $productId, [$metafield]);

        return $result
            ? ['success' => true, 'schema' => $schema]
            : ['error' => 'schema_save_failed'];
    }

    /**
     * Build a Product JSON-LD schema from Shopify product data.
     */
    private function buildProductSchema(array $product, Shop $shop): array
    {
        $siteDomain = rtrim("https://{$shop->shop_domain}", '/');
        $url = $product['onlineStoreUrl'] ?: "{$siteDomain}/products/{$product['handle']}";
        $description = strip_tags($product['description'] ?? '');

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product['title'],
            'description' => $description,
            'url' => $url,
        ];

        // Image
        $featuredImage = $product['featuredImage'] ?? null;
        if ($featuredImage && ! empty($featuredImage['url'])) {
            $schema['image'] = $featuredImage['url'];
        }

        // Brand (vendor)
        if (! empty($product['vendor'])) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product['vendor']];
        }

        // Category
        if (! empty($product['productType'])) {
            $schema['category'] = $product['productType'];
        }

        // Offers from variants
        $variants = $product['variants']['edges'] ?? [];
        if (! empty($variants)) {
            $prices = [];
            $available = true;
            foreach ($variants as $edge) {
                $v = $edge['node'];
                if (isset($v['price'])) {
                    $prices[] = (float) $v['price'];
                }
                if (! $v['availableForSale']) {
                    $available = false;
                }
            }

            if (count($prices) > 1) {
                $schema['offers'] = [
                    '@type' => 'AggregateOffer',
                    'priceCurrency' => $shop->currency ?: 'USD',
                    'lowPrice' => min($prices),
                    'highPrice' => max($prices),
                    'offerCount' => count($variants),
                    'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $url,
                ];
            } else {
                $schema['offers'] = [
                    '@type' => 'Offer',
                    'priceCurrency' => $shop->currency ?: 'USD',
                    'price' => $prices[0] ?? 0,
                    'availability' => $available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $url,
                ];
            }
        }

        return $schema;
    }

    /**
     * Build the AI prompt for product description generation.
     */
    private function buildDescriptionPrompt(array $product, string $type): string
    {
        $title = $product['title'] ?? '';
        $vendor = $product['vendor'] ?? '';
        $productType = $product['productType'] ?? '';
        $tags = $product['tags'] ?? [];
        $tags = is_array($tags) ? implode(', ', $tags) : $tags;
        $existingDesc = strip_tags($product['description'] ?? '');
        $variants = $product['variants']['edges'] ?? [];
        $price = $variants[0]['node']['price'] ?? '';

        $length = $type === 'short' ? '1-2 sentences (max 100 words)' : '3-5 paragraphs (max 500 words)';

        return "Write an SEO-optimized product description for the following product. Length: {$length}.\n\n"
            ."Product: {$title}\n"
            ."Vendor: {$vendor}\n"
            ."Type: {$productType}\n"
            ."Tags: {$tags}\n"
            ."Price: {$price}\n"
            ."Existing description: {$existingDesc}\n\n"
            .'Focus on benefits, features, and include relevant keywords naturally. Do not use HTML tags.';
    }

    /**
     * Build the AI prompt for meta title + description generation.
     */
    private function buildMetaPrompt(array $product): string
    {
        $title = $product['title'] ?? '';
        $vendor = $product['vendor'] ?? '';
        $productType = $product['productType'] ?? '';
        $existingDesc = strip_tags($product['description'] ?? '');

        return "Generate SEO meta title and description for this product.\n\n"
            ."Product: {$title}\n"
            ."Vendor: {$vendor}\n"
            ."Type: {$productType}\n"
            ."Description: {$existingDesc}\n\n"
            ."Meta title: max 60 characters. Meta description: max 155 characters.\n"
            .'Respond as JSON: {"title": "...", "description": "..."}';
    }

    /**
     * Fetch a single product by ID from Shopify.
     */
    private function getProduct(Shop $shop, string $productId): array
    {
        $query = <<<'GRAPHQL'
        query getProduct($id: ID!) {
          product(id: $id) {
            id
            handle
            title
            description
            descriptionHtml
            productType
            vendor
            tags
            onlineStoreUrl
            featuredImage { url altText }
            images(first: 10) { edges { node { url altText } } }
            variants(first: 20) {
              edges {
                node {
                  sku
                  price
                  compareAtPrice
                  availableForSale
                }
              }
            }
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
                    'variables' => ['id' => "gid://shopify/Product/{$productId}"],
                ]);
        } catch (ConnectionException $e) {
            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'http_'.$response->status()];
        }

        $body = $response->json();
        $product = $body['data']['product'] ?? null;
        if (! $product) {
            return ['error' => 'product_not_found'];
        }

        return $product;
    }

    /**
     * Create metafields on a Shopify resource via the Admin API.
     */
    private function createMetafields(Shop $shop, string $ownerType, string $ownerId, array $metafields): bool
    {
        $mutation = <<<'GRAPHQL'
        mutation createMetafields($metafields: [MetafieldsSetInput!]!) {
          metafieldsSet(metafields: $metafields) {
            metafields { id namespace key value }
            userErrors { field message }
          }
        }
        GRAPHQL;

        // Shopify GIDs use the case-sensitive resource type (e.g. "Product")
        $gid = "gid://shopify/Product/{$ownerId}";

        $metafields = array_map(
            fn (array $metafield) => ['ownerId' => $gid] + $metafield,
            $metafields
        );

        $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/graphql.json';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'query' => $mutation,
                    'variables' => ['metafields' => $metafields],
                ]);
        } catch (ConnectionException $e) {
            Log::error('Metafield creation failed', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            Log::error('Metafield HTTP error', ['status' => $response->status()]);

            return false;
        }

        $body = $response->json();
        $errors = $body['data']['metafieldsSet']['userErrors'] ?? [];
        if (! empty($errors)) {
            Log::error('Metafield user errors', ['errors' => $errors]);

            return false;
        }

        return true;
    }

    /**
     * Build a metafield input array for the GraphQL mutation.
     */
    private function metafieldInput(string $namespace, string $key, string $value, string $type): array
    {
        return [
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
            'type' => $type,
        ];
    }

    /**
     * Try to parse a JSON response from the LLM (handles markdown code fences).
     */
    private function parseJsonResponse(string $text): array
    {
        // Strip markdown code fences
        $text = preg_replace('/^```(?:json)?\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);

        return is_array($parsed) ? $parsed : [];
    }
}

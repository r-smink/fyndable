<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use App\Services\ShopifyAdminWriter;
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
 *  - Preview and save Product JSON-LD schema via Shopify metafields
 */
class ProductController extends Controller
{
    private const METAFIELD_NAMESPACE = 'fyndable';

    private const METAFIELD_NAMESPACE_SEO = 'seo';

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private SaasProxyClient $saas,
        private LicenseService $license,
        private ShopifyAdminWriter $writer
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

        try {
            $type = $request->input('type', 'long');
            $product = $this->getProduct($shop, $productId);
            if (isset($product['error'])) {
                return $product;
            }

            $prompt = $this->buildDescriptionPrompt($product, $type, $request->input('context', '') ?? '');
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
        } catch (\Throwable $e) {
            Log::error('generateDescription failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => 'generate_failed', 'message' => $e->getMessage()];
        }
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

        try {
            $product = $this->getProduct($shop, $productId);
            if (isset($product['error'])) {
                return $product;
            }

            $prompt = $this->buildMetaPrompt($product, $request->input('context', '') ?? '');
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

            // Parse JSON response
            $text = $result['text'] ?? '';
            $parsed = $this->parseJsonResponse($text);
            if (empty($parsed)) {
                return ['error' => 'ai_parse_failed', 'raw' => $text];
            }

            return [
                'success' => true,
                'title' => $parsed['title'] ?? '',
                'description' => $parsed['description'] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('generateMeta failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => 'generate_failed', 'message' => $e->getMessage()];
        }
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
     * Save SEO meta to the product's native Shopify SEO fields.
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

        $seo = [];
        if (! empty($title)) {
            $seo['title'] = $title;
        }
        if (! empty($description)) {
            $seo['description'] = $description;
        }

        $error = $this->writer->updateProduct($shop, $productId, ['seo' => $seo]);

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * Save a product description to Shopify.
     *
     * POST /api/products/{productId}/save-description
     * Body: { description: "...", format: "text"|"html" }
     */
    public function saveDescription(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $description = $request->input('description', '');
        if (empty($description)) {
            return ['error' => 'nothing_to_save'];
        }

        $descriptionHtml = $request->input('format') === 'html'
            ? $description
            : nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $error = $this->writer->updateProduct($shop, $productId, ['descriptionHtml' => $descriptionHtml]);

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * Generate a Product JSON-LD schema preview (does not save).
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

        return [
            'success' => true,
            'schema' => $this->buildProductSchema($product, $shop),
        ];
    }

    /**
     * Save a (possibly user-edited) Product JSON-LD schema to metafields.
     *
     * POST /api/products/{productId}/save-schema
     * Body: { schema: { ... } }
     */
    public function saveSchema(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $schema = $request->input('schema');
        if (! is_array($schema) || empty($schema)) {
            return ['error' => 'schema_required', 'message' => 'Provide a schema JSON object.'];
        }

        if (empty($schema['@context']) || empty($schema['@type'])) {
            return ['error' => 'invalid_schema', 'message' => 'Schema must include @context and @type.'];
        }

        $metafield = $this->writer->metafieldInput(
            self::METAFIELD_NAMESPACE,
            'product_schema',
            json_encode($schema, JSON_UNESCAPED_SLASHES),
            'json'
        );

        $error = $this->writer->setMetafields($shop, 'product', $productId, [$metafield]);

        return $error
            ? ['error' => 'schema_save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * Push a generated product description to Shopify.
     *
     * POST /api/products/{productId}/push-description
     * Body: { description: "...", overwrite: true }
     */
    public function pushDescription(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $description = trim($request->input('description', ''));
        if (empty($description)) {
            return ['error' => 'description_required'];
        }

        $overwrite = (bool) $request->input('overwrite', false);
        if (! $overwrite) {
            return ['error' => 'overwrite_required', 'message' => 'Set overwrite: true to confirm overwriting the product description.'];
        }

        $error = $this->writer->updateProduct($shop, $productId, [
            'descriptionHtml' => $description,
        ]);

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true, 'saved' => true];
    }

    /**
     * Push a generated product title to Shopify.
     *
     * POST /api/products/{productId}/push-title
     * Body: { title: "...", overwrite: true }
     */
    public function pushTitle(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $title = trim($request->input('title', ''));
        if (empty($title)) {
            return ['error' => 'title_required'];
        }

        $overwrite = (bool) $request->input('overwrite', false);
        if (! $overwrite) {
            return ['error' => 'overwrite_required', 'message' => 'Set overwrite: true to confirm overwriting the product title.'];
        }

        $error = $this->writer->updateProduct($shop, $productId, [
            'title' => $title,
        ]);

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true, 'saved' => true];
    }

    /**
     * Push generated image alt text to Shopify.
     *
     * POST /api/products/{productId}/push-alt-text
     * Body: { image_id: "gid://shopify/MediaImage/123", alt_text: "..." }
     */
    public function pushAltText(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $imageId = trim($request->input('image_id', ''));
        $altText = trim($request->input('alt_text', ''));

        if (empty($imageId) || empty($altText)) {
            return ['error' => 'image_id_and_alt_text_required'];
        }

        $error = $this->writer->updateMediaAlt($shop, $imageId, $altText);

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true, 'saved' => true];
    }

    /**
     * Push all generated SEO content to Shopify at once.
     *
     * POST /api/products/{productId}/push-all
     * Body: { title, description, description_format, meta_title, meta_description, alt_text, image_id, schema, overwrite }
     */
    public function pushAll(Request $request, string $productId): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        $overwrite = (bool) $request->input('overwrite', false);
        if (! $overwrite) {
            return ['error' => 'overwrite_required', 'message' => 'Set overwrite: true to confirm overwriting product content.'];
        }

        $results = [];

        $title = trim($request->input('title', ''));
        if (! empty($title)) {
            $error = $this->writer->updateProduct($shop, $productId, ['title' => $title]);
            $results['title'] = $error ?: true;
        }

        $description = trim($request->input('description', ''));
        if (! empty($description)) {
            $descriptionHtml = $request->input('description_format') === 'html'
                ? $description
                : nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $error = $this->writer->updateProduct($shop, $productId, ['descriptionHtml' => $descriptionHtml]);
            $results['description'] = $error ?: true;
        }

        $metaTitle = trim($request->input('meta_title', ''));
        $metaDescription = trim($request->input('meta_description', ''));
        if (! empty($metaTitle) || ! empty($metaDescription)) {
            $seo = [];
            if (! empty($metaTitle)) {
                $seo['title'] = $metaTitle;
            }
            if (! empty($metaDescription)) {
                $seo['description'] = $metaDescription;
            }
            $error = $this->writer->updateProduct($shop, $productId, ['seo' => $seo]);
            $results['meta'] = $error ?: true;
        }

        $imageId = trim($request->input('image_id', ''));
        $altText = trim($request->input('alt_text', ''));
        if (! empty($imageId) && ! empty($altText)) {
            $error = $this->writer->updateMediaAlt($shop, $imageId, $altText);
            $results['alt_text'] = $error ?: true;
        }

        $schema = $request->input('schema', null);
        if (is_array($schema) && ! empty($schema)) {
            $metafield = $this->writer->metafieldInput(
                self::METAFIELD_NAMESPACE,
                'product_schema',
                json_encode($schema, JSON_UNESCAPED_SLASHES),
                'json'
            );
            $error = $this->writer->setMetafields($shop, 'product', $productId, [$metafield]);
            $results['schema'] = $error ?: true;
        }

        $failed = array_filter($results, fn ($result) => $result !== true);

        return empty($failed)
            ? ['success' => true, 'saved' => $results]
            : ['error' => 'partial_save', 'saved' => $results];
    }

    /**
     * Build a Product JSON-LD schema from Shopify product data.
     */
    private function buildProductSchema(array $product, Shop $shop): array
    {
        $siteDomain = rtrim("https://{$shop->shop_domain}", '/');
        $url = ($product['onlineStoreUrl'] ?? null) ?: "{$siteDomain}/products/".($product['handle'] ?? '');
        $description = strip_tags($product['description'] ?? '');

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product['title'] ?? '',
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
                if (! ($v['availableForSale'] ?? false)) {
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
    private function buildDescriptionPrompt(array $product, string $type, string $context = ''): string
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

        $prompt = "Write an SEO-optimized product description for the following product. Length: {$length}.\n\n"
            ."Product: {$title}\n"
            ."Vendor: {$vendor}\n"
            ."Type: {$productType}\n"
            ."Tags: {$tags}\n"
            ."Price: {$price}\n"
            ."Existing description: {$existingDesc}\n";

        if (! empty($context)) {
            $prompt .= "Additional context: {$context}\n";
        }

        $prompt .= "\nFocus on benefits, features, and include relevant keywords naturally. Do not use HTML tags.";

        return $prompt;
    }

    /**
     * Build the AI prompt for meta title + description generation.
     */
    private function buildMetaPrompt(array $product, string $context = ''): string
    {
        $title = $product['title'] ?? '';
        $vendor = $product['vendor'] ?? '';
        $productType = $product['productType'] ?? '';
        $existingDesc = strip_tags($product['description'] ?? '');

        $prompt = "Generate SEO meta title and description for this product.\n\n"
            ."Product: {$title}\n"
            ."Vendor: {$vendor}\n"
            ."Type: {$productType}\n"
            ."Description: {$existingDesc}\n";

        if (! empty($context)) {
            $prompt .= "Additional context: {$context}\n";
        }

        $prompt .= "\nMeta title: max 60 characters. Meta description: max 155 characters.\n"
            .'Respond as JSON: {"title": "...", "description": "..."}';

        return $prompt;
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
            images(first: 10) { edges { node { id url altText } } }
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
                    'variables' => ['id' => str_starts_with($productId, 'gid://') ? $productId : "gid://shopify/Product/{$productId}"],
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

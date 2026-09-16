<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ShopifyAdminWriter
 *
 * Write operations against the Shopify Admin GraphQL API: product/collection/
 * page/article updates, media alt text and metafields.
 *
 * All public methods return null on success or a human-readable error string
 * on failure, so controllers can surface the real Shopify userErrors in the
 * app UI instead of a generic "save_failed".
 */
class ShopifyAdminWriter
{
    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('shopify.api_version', '2026-07');
    }

    /**
     * Update a product via the productUpdate mutation.
     *
     * @param  array  $fields  ProductUpdateInput fields (merged with `id`).
     */
    public function updateProduct(Shop $shop, string $productId, array $fields): ?string
    {
        $mutation = <<<'GRAPHQL'
        mutation updateProduct($product: ProductUpdateInput!) {
          productUpdate(product: $product) {
            product { id }
            userErrors { field message }
          }
        }
        GRAPHQL;

        return $this->runMutation(
            $shop,
            $mutation,
            ['product' => ['id' => $this->gid('Product', $productId)] + $fields],
            'productUpdate'
        );
    }

    /**
     * Update a collection via the collectionUpdate mutation.
     */
    public function updateCollection(Shop $shop, string $collectionId, array $fields): ?string
    {
        $mutation = <<<'GRAPHQL'
        mutation updateCollection($input: CollectionInput!) {
          collectionUpdate(input: $input) {
            collection { id }
            userErrors { field message }
          }
        }
        GRAPHQL;

        return $this->runMutation(
            $shop,
            $mutation,
            ['input' => ['id' => $this->gid('Collection', $collectionId)] + $fields],
            'collectionUpdate'
        );
    }

    /**
     * Update a page via the pageUpdate mutation.
     */
    public function updatePage(Shop $shop, string $pageId, array $fields): ?string
    {
        $mutation = <<<'GRAPHQL'
        mutation updatePage($id: ID!, $page: PageUpdateInput!) {
          pageUpdate(id: $id, page: $page) {
            page { id }
            userErrors { field message }
          }
        }
        GRAPHQL;

        return $this->runMutation(
            $shop,
            $mutation,
            ['id' => $this->gid('Page', $pageId), 'page' => $fields],
            'pageUpdate'
        );
    }

    /**
     * Update a blog article via the articleUpdate mutation.
     */
    public function updateArticle(Shop $shop, string $articleId, array $fields): ?string
    {
        $mutation = <<<'GRAPHQL'
        mutation updateArticle($id: ID!, $article: ArticleUpdateInput!) {
          articleUpdate(id: $id, article: $article) {
            article { id }
            userErrors { field message }
          }
        }
        GRAPHQL;

        return $this->runMutation(
            $shop,
            $mutation,
            ['id' => $this->gid('Article', $articleId), 'article' => $fields],
            'articleUpdate'
        );
    }

    /**
     * Update the alt text of an existing media file via the fileUpdate mutation.
     *
     * Accepts a MediaImage GID or numeric file ID.
     */
    public function updateMediaAlt(Shop $shop, string $mediaId, string $altText): ?string
    {
        $mutation = <<<'GRAPHQL'
        mutation fileUpdate($files: [FileUpdateInput!]!) {
          fileUpdate(files: $files) {
            files { id alt }
            userErrors { field message }
          }
        }
        GRAPHQL;

        return $this->runMutation(
            $shop,
            $mutation,
            ['files' => [['id' => $this->gid('MediaImage', $mediaId), 'alt' => $altText]]],
            'fileUpdate'
        );
    }

    /**
     * Create or update metafields on a resource via metafieldsSet.
     *
     * @param  string  $ownerType  Lowercase resource type: product|collection|page|article
     * @param  array<int, array>  $metafields  Metafield inputs from metafieldInput().
     */
    public function setMetafields(Shop $shop, string $ownerType, string $ownerId, array $metafields): ?string
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
        $gid = $this->gid(ucfirst($ownerType), $ownerId);

        $metafields = array_map(
            fn (array $metafield) => ['ownerId' => $gid] + $metafield,
            $metafields
        );

        return $this->runMutation($shop, $mutation, ['metafields' => $metafields], 'metafieldsSet');
    }

    /**
     * Build a metafield input array for the metafieldsSet mutation.
     */
    public function metafieldInput(string $namespace, string $key, string $value, string $type): array
    {
        return [
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
            'type' => $type,
        ];
    }

    /**
     * Build a full Shopify GID from a resource type and an ID/GID.
     */
    public function gid(string $resourceType, string $id): string
    {
        return str_starts_with($id, 'gid://') ? $id : "gid://shopify/{$resourceType}/{$id}";
    }

    /**
     * Run a mutation and normalize failures to a single error string.
     *
     * @return string|null Null on success, error message on failure.
     */
    private function runMutation(Shop $shop, string $mutation, array $variables, string $resultKey): ?string
    {
        $body = $this->graphql($shop, $mutation, $variables);

        if (isset($body['error'])) {
            $error = $body['error'];
            if (! empty($body['details'])) {
                $error .= ': '.json_encode($body['details']);
            }
            Log::error("ShopifyAdminWriter: {$resultKey} failed", ['error' => $error]);

            return $error;
        }

        $result = $body['data'][$resultKey] ?? null;
        if ($result === null) {
            Log::error("ShopifyAdminWriter: {$resultKey} missing from response");

            return 'unexpected_response';
        }

        $userErrors = $result['userErrors'] ?? $result['mediaUserErrors'] ?? [];
        if (! empty($userErrors)) {
            $error = collect($userErrors)
                ->map(fn (array $e) => trim(implode('.', (array) ($e['field'] ?? [])).' '.$e['message']))
                ->implode('; ');
            Log::error("ShopifyAdminWriter: {$resultKey} userErrors", ['errors' => $userErrors]);

            return $error;
        }

        return null;
    }

    /**
     * Execute a GraphQL request against the Shopify Admin API.
     *
     * @return array GraphQL response body or ['error' => ..., 'details' => ...]
     */
    private function graphql(Shop $shop, string $query, array $variables = []): array
    {
        if (! $shop->hasAccessToken()) {
            return ['error' => 'no_access_token'];
        }

        $endpoint = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/graphql.json";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $shop->access_token,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'query' => $query,
                    // Shopify expects variables as a JSON object ({}, not []).
                    'variables' => empty($variables) ? new \stdClass : $variables,
                ]);
        } catch (ConnectionException $e) {
            return ['error' => 'connection_failed', 'details' => $e->getMessage()];
        }

        if ($response->failed()) {
            return ['error' => 'http_'.$response->status(), 'details' => substr($response->body(), 0, 300)];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return ['error' => 'invalid_response'];
        }

        if (! empty($body['errors'])) {
            return ['error' => 'graphql_errors', 'details' => $body['errors']];
        }

        return $body;
    }
}

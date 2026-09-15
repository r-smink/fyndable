<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ShopifyContentFetcher
 *
 * Fetches products, pages, blog articles, and collections from Shopify via the
 * Admin GraphQL API. Used by the LlmsTxtGenerator and ProductSchemaService.
 *
 * Handles Shopify's GraphQL cost-based rate limiting by paginating with cursors.
 */
class ShopifyContentFetcher
{
    private string $apiVersion;

    public function __construct()
    {
        $this->apiVersion = config('shopify.api_version', '2026-07');
    }

    /**
     * Fetch products from the shop.
     *
     * @param  Shop  $shop
     * @param  int  $limit  Max number of products to fetch.
     * @return array<int, array>
     */
    public function getProducts(Shop $shop, int $limit = 100): array
    {
        $query = <<<'GRAPHQL'
        query getProducts($first: Int!, $after: String) {
          products(first: $first, after: $after) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                handle
                title
                description
                descriptionHtml
                productType
                vendor
                tags
                status
                onlineStoreUrl
                featuredImage { url altText }
                images(first: 5) { edges { node { url altText } } }
                variants(first: 10) {
                  edges {
                    node {
                      sku
                      price
                      compareAtPrice
                      availableForSale
                    }
                  }
                }
                metafields(first: 10, namespace: "seo") {
                  edges { node { key value } }
                }
              }
            }
          }
        }
        GRAPHQL;

        return $this->paginate($shop, $query, 'products', $limit);
    }

    /**
     * Fetch collections from the shop.
     *
     * @param  Shop  $shop
     * @param  int  $limit
     * @return array<int, array>
     */
    public function getCollections(Shop $shop, int $limit = 100): array
    {
        $query = <<<'GRAPHQL'
        query getCollections($first: Int!, $after: String) {
          collections(first: $first, after: $after) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                handle
                title
                description
                descriptionHtml
                onlineStoreUrl
                productsCount { count precision }
                image { url altText }
              }
            }
          }
        }
        GRAPHQL;

        return $this->paginate($shop, $query, 'collections', $limit);
    }

    /**
     * Fetch pages from the shop.
     *
     * @param  Shop  $shop
     * @param  int  $limit
     * @return array<int, array>
     */
    public function getPages(Shop $shop, int $limit = 50): array
    {
        $query = <<<'GRAPHQL'
        query getPages($first: Int!, $after: String) {
          pages(first: $first, after: $after) {
            pageInfo { hasNextPage endCursor }
            edges {
              node {
                id
                handle
                title
                body
                bodySummary
                url
                status
              }
            }
          }
        }
        GRAPHQL;

        return $this->paginate($shop, $query, 'pages', $limit);
    }

    /**
     * Fetch blog articles from all blogs.
     *
     * @param  Shop  $shop
     * @param  int  $limit  Max total articles across all blogs.
     * @return array<int, array>
     */
    public function getArticles(Shop $shop, int $limit = 250): array
    {
        // First get all blogs
        $blogsQuery = <<<'GRAPHQL'
        query getBlogs($first: Int!) {
          blogs(first: $first) {
            edges {
              node { id handle title }
            }
          }
        }
        GRAPHQL;

        $blogsResponse = $this->graphql($shop, $blogsQuery, ['first' => 50]);
        if (isset($blogsResponse['error'])) {
            return [];
        }

        $blogs = $blogsResponse['data']['blogs']['edges'] ?? [];
        $allArticles = [];

        foreach ($blogs as $blogEdge) {
            $blog = $blogEdge['node'];
            $blogHandle = $blog['handle'];

            $articlesQuery = <<<'GRAPHQL'
            query getArticles($blogId: ID!, $first: Int!, $after: String) {
              blog(id: $blogId) {
                articles(first: $first, after: $after) {
                  pageInfo { hasNextPage endCursor }
                  edges {
                    node {
                      id
                      handle
                      title
                      content
                      contentHtml
                      excerpt
                      url
                      publishedAt
                      tags
                      image { url altText }
                    }
                  }
                }
              }
            }
            GRAPHQL;

            $articles = $this->paginateBlog($shop, $articlesQuery, $blog['id'], $blogHandle, $limit - count($allArticles));
            foreach ($articles as $article) {
                $allArticles[] = $article;
                if (count($allArticles) >= $limit) {
                    break 2;
                }
            }
        }

        return $allArticles;
    }

    /**
     * Get the shop's total product count.
     *
     * @param  Shop  $shop
     * @return int
     */
    public function getProductCount(Shop $shop): int
    {
        $query = <<<'GRAPHQL'
        query getProductCount {
          productsCount(limit: null) { count precision }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query);

        return (int) ($response['data']['productsCount']['count'] ?? 0);
    }

    /**
     * Get shop details (name, currency, country, domain).
     */
    public function getShopDetails(Shop $shop): array
    {
        $query = <<<'GRAPHQL'
        query {
          shop {
            name
            primaryDomain { url }
            myshopifyDomain
            currencyCode
            billingAddress { country }
          }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query);
        if (isset($response['error'])) {
            return [];
        }

        $shopData = $response['data']['shop'] ?? [];

        return [
            'name' => $shopData['name'] ?? '',
            'domain' => $shopData['primaryDomain']['url'] ?? "https://{$shop->shop_domain}",
            'currency' => $shopData['currencyCode'] ?? 'USD',
            'country' => $shopData['billingAddress']['country'] ?? '',
        ];
    }

    /**
     * Execute a GraphQL query with pagination for a top-level connection.
     *
     * @param  Shop  $shop
     * @param  string  $query  GraphQL query with $first and $after variables.
     * @param  string  $connectionKey  The connection field name (e.g. "products").
     * @param  int  $limit  Max items to fetch.
     * @return array<int, array>
     */
    private function paginate(Shop $shop, string $query, string $connectionKey, int $limit): array
    {
        $items = [];
        $cursor = null;
        $pageSize = min($limit, 250);

        do {
            $variables = ['first' => $pageSize, 'after' => $cursor];
            $response = $this->graphql($shop, $query, $variables);

            if (isset($response['error'])) {
                Log::error('ShopifyContentFetcher: GraphQL error', [
                    'connection' => $connectionKey,
                    'error' => $response['error'],
                ]);
                break;
            }

            $connection = $response['data'][$connectionKey] ?? null;
            if (! $connection) {
                break;
            }

            foreach ($connection['edges'] ?? [] as $edge) {
                $items[] = $edge['node'] ?? [];
                if (count($items) >= $limit) {
                    break 2;
                }
            }

            $pageInfo = $connection['pageInfo'] ?? [];
            $cursor = $pageInfo['endCursor'] ?? null;
        } while (! empty($cursor) && ($pageInfo['hasNextPage'] ?? false) && count($items) < $limit);

        return $items;
    }

    /**
     * Paginate blog articles (slightly different structure).
     */
    private function paginateBlog(Shop $shop, string $query, string $blogId, string $blogHandle, int $limit): array
    {
        $items = [];
        $cursor = null;
        $pageSize = min($limit, 250);

        do {
            $variables = ['blogId' => $blogId, 'first' => $pageSize, 'after' => $cursor];
            $response = $this->graphql($shop, $query, $variables);

            if (isset($response['error'])) {
                break;
            }

            $connection = $response['data']['blog']['articles'] ?? null;
            if (! $connection) {
                break;
            }

            foreach ($connection['edges'] ?? [] as $edge) {
                $node = $edge['node'] ?? [];
                $node['_blog_handle'] = $blogHandle;
                $items[] = $node;
                if (count($items) >= $limit) {
                    break 2;
                }
            }

            $pageInfo = $connection['pageInfo'] ?? [];
            $cursor = $pageInfo['endCursor'] ?? null;
        } while (! empty($cursor) && ($pageInfo['hasNextPage'] ?? false) && count($items) < $limit);

        return $items;
    }

    /**
     * Execute a GraphQL query against the Shopify Admin API.
     *
     * @return array GraphQL response data or ['error' => ...]
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
                    'variables' => $variables,
                ]);
        } catch (ConnectionException $e) {
            Log::error('ShopifyContentFetcher: HTTP error', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'http_'.$response->status()];
        }

        $body = $response->json();
        if (isset($body['errors'])) {
            Log::error('ShopifyContentFetcher: GraphQL errors', ['errors' => $body['errors']]);

            return ['error' => 'graphql_errors', 'details' => $body['errors']];
        }

        return $body;
    }
}

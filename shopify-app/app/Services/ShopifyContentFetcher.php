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
                metafields(first: 10) {
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
     * Search a single content type by title.
     *
     * Uses Shopify's search `query` argument; an empty search returns the first
     * items. Supported types: product, collection, page, article.
     *
     * @return array<int, array>
     */
    public function search(Shop $shop, string $type, string $search = '', int $limit = 50): array
    {
        $limit = max(1, min($limit, 250));

        $fields = match ($type) {
            'product' => 'id title handle status productType onlineStoreUrl featuredImage { url altText }',
            'collection' => 'id title handle onlineStoreUrl image { url altText }',
            'page' => 'id title handle',
            'article' => 'id title handle publishedAt image { url altText } blog { handle title }',
            default => null,
        };

        if ($fields === null) {
            return [];
        }

        $connection = $type === 'article' ? 'articles' : "{$type}s";

        $query = <<<GRAPHQL
        query searchContent(\$first: Int!, \$query: String) {
          {$connection}(first: \$first, query: \$query) {
            edges { node { {$fields} } }
          }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query, [
            'first' => $limit,
            'query' => $search !== '' ? $search : null,
        ]);

        if (isset($response['error'])) {
            Log::error('ShopifyContentFetcher: search failed', [
                'type' => $type,
                'error' => $response['error'],
            ]);

            return [];
        }

        $items = [];
        foreach ($response['data'][$connection]['edges'] ?? [] as $edge) {
            $items[] = $edge['node'] ?? [];
        }

        return $items;
    }

    /**
     * Fetch a single resource by GID via the generic node() query.
     *
     * Returns the raw node array plus a `__typename` discriminator, or
     * ['error' => ...] on failure.
     */
    public function getNode(Shop $shop, string $gid): array
    {
        $query = <<<'GRAPHQL'
        query getNode($id: ID!) {
          node(id: $id) {
            __typename
            id
            ... on Product {
              title
              handle
              description
              descriptionHtml
              vendor
              productType
              tags
              status
              onlineStoreUrl
              seo { title description }
              featuredImage { url altText }
              images(first: 10) { edges { node { id url altText } } }
              variants(first: 20) { edges { node { sku price compareAtPrice availableForSale } } }
              metafields(first: 25) { edges { node { namespace key value } } }
            }
            ... on Collection {
              title
              handle
              description
              descriptionHtml
              onlineStoreUrl
              seo { title description }
              image { url altText }
              metafields(first: 25) { edges { node { namespace key value } } }
            }
            ... on Page {
              title
              handle
              body
              bodySummary
              metafields(first: 25) { edges { node { namespace key value } } }
            }
            ... on Article {
              title
              handle
              content
              contentHtml
              excerpt
              publishedAt
              tags
              image { url altText }
              blog { handle title }
              metafields(first: 25) { edges { node { namespace key value } } }
            }
          }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query, ['id' => $gid]);
        if (isset($response['error'])) {
            return $response;
        }

        $node = $response['data']['node'] ?? null;

        return is_array($node) ? $node : ['error' => 'not_found'];
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
          productsCount { count precision }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query);

        if (isset($response['error'])) {
            return 0;
        }

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
            shopAddress { country }
          }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query);
        if (isset($response['error'])) {
            // Return sensible defaults so callers can safely use array access
            return [
                'name' => '',
                'domain' => "https://{$shop->shop_domain}",
                'currency' => 'USD',
                'country' => '',
            ];
        }

        $shopData = $response['data']['shop'] ?? [];

        return [
            'name' => $shopData['name'] ?? '',
            'domain' => $shopData['primaryDomain']['url'] ?? "https://{$shop->shop_domain}",
            'currency' => $shopData['currencyCode'] ?? 'USD',
            'country' => $shopData['shopAddress']['country'] ?? '',
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
        $accessToken = app(ShopifyTokenService::class)->tokenFor($shop);
        if ($accessToken === null) {
            return ['error' => 'no_access_token'];
        }

        $endpoint = "https://{$shop->shop_domain}/admin/api/{$this->apiVersion}/graphql.json";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'query' => $query,
                    // Shopify expects variables as a JSON object ({}, not []).
                    // Empty PHP array [] encodes to [] in JSON, which Shopify
                    // rejects with "Invalid variables parameter."
                    'variables' => empty($variables) ? new \stdClass : $variables,
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

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
    /**
     * List the shop's blogs (for the article writer).
     */
    public function getBlogs(Shop $shop): array
    {
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

        return array_map(
            fn (array $edge) => $edge['node'],
            $blogsResponse['data']['blogs']['edges'] ?? []
        );
    }

    public function getArticles(Shop $shop, int $limit = 250): array
    {
        // First get all blogs
        $blogs = array_map(
            fn (array $blog) => ['node' => $blog],
            $this->getBlogs($shop)
        );
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
                      body
                      summary
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
     * Items for the SEO audit (SeoAuditor::normalize) and the bulk optimizer,
     * one content type at a time. Lean fields and small pages keep each query under Shopify's
     * 1000-point cost limit. Draft/archived products and unpublished pages
     * and articles are skipped: they are not indexed.
     *
     * @return array<int, array>|array{error: string}
     */
    public function getAuditItems(Shop $shop, string $type, int $limit = 1000): array
    {
        $hidden = 'hidden: metafield(namespace: "seo", key: "hidden") { value }';
        $tags = 'titleTag: metafield(namespace: "global", key: "title_tag") { value } '
            .'descriptionTag: metafield(namespace: "global", key: "description_tag") { value }';

        [$connection, $filter, $fields, $pageSize] = match ($type) {
            'product' => ['products', 'status:active', "id handle title vendor productType onlineStoreUrl descriptionHtml seo { title description } priceRangeV2 { minVariantPrice { amount currencyCode } } media(first: 10) { nodes { id alt mediaContentType preview { image { url } } } } aiAttributes: metafield(namespace: \"fyndable\", key: \"ai_attributes\") { value } {$hidden}", 40],
            'collection' => ['collections', null, "id handle title descriptionHtml seo { title description } productsCount { count } image { altText } {$hidden}", 100],
            'page' => ['pages', 'published_status:published', "id handle title body {$tags} {$hidden}", 50],
            'article' => ['articles', 'published_status:published', "id handle title body blog { handle title } image { altText } {$tags} {$hidden}", 50],
            default => [null, null, null, 0],
        };
        if ($connection === null) {
            return ['error' => 'invalid_type'];
        }

        $queryArg = $filter !== null ? ', query: "'.$filter.'"' : '';
        $query = <<<GRAPHQL
        query auditItems(\$first: Int!, \$after: String) {
          {$connection}(first: \$first, after: \$after{$queryArg}) {
            pageInfo { hasNextPage endCursor }
            edges { node { {$fields} } }
          }
        }
        GRAPHQL;

        $error = null;
        $items = $this->paginate($shop, $query, $connection, $limit, $pageSize, $error);

        // A partial catalog would give a misleading score: report the error.
        if ($error !== null) {
            return ['error' => $error];
        }

        return $type === 'product' ? $this->withOnlineStoreFlag($shop, $items) : $items;
    }

    /**
     * GIDs of active products published to the Online Store sales channel.
     *
     * Product.onlineStoreUrl is null while the storefront is password
     * protected (development stores, stores before launch), even for
     * published products, so it cannot tell whether a product is published.
     * The published_status search filter can.
     *
     * @return array<string, true>|array{error: string}
     */
    public function getOnlineStoreProductIds(Shop $shop, int $limit = 20000): array
    {
        $query = <<<'GRAPHQL'
        query onlineStoreProducts($first: Int!, $after: String) {
          products(first: $first, after: $after, query: "status:active published_status:online_store_channel") {
            pageInfo { hasNextPage endCursor }
            edges { node { id } }
          }
        }
        GRAPHQL;

        $error = null;
        $nodes = $this->paginate($shop, $query, 'products', $limit, 250, $error);
        if ($error !== null) {
            return ['error' => $error];
        }

        $ids = [];
        foreach ($nodes as $node) {
            if (! empty($node['id'])) {
                $ids[$node['id']] = true;
            }
        }

        return $ids;
    }

    /**
     * Mark each product with publishedOnline (Online Store sales channel).
     *
     * @param  array<int, array>  $products  product nodes with id
     * @return array<int, array>|array{error: string}
     */
    private function withOnlineStoreFlag(Shop $shop, array $products): array
    {
        if ($products === []) {
            return $products;
        }
        $ids = $this->getOnlineStoreProductIds($shop);
        if (isset($ids['error'])) {
            return ['error' => (string) $ids['error']];
        }
        foreach ($products as &$product) {
            $product['publishedOnline'] = isset($ids[$product['id'] ?? '']);
        }
        unset($product);

        return $products;
    }

    /**
     * Handle of the product's first collection (redirect target when the
     * product is deleted, 1.2). null = in no collection, false = request failed.
     */
    public function getProductCollectionHandle(Shop $shop, string $productGid): string|null|false
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        query productCollection($id: ID!) {
          product(id: $id) { collections(first: 1) { nodes { handle } } }
        }
        GRAPHQL, ['id' => $productGid]);

        if (isset($response['error'])) {
            return false;
        }

        return $response['data']['product']['collections']['nodes'][0]['handle'] ?? null;
    }

    /**
     * All variants with their product data for the product feed and the
     * AI-ready score (2.1). Variant pages of 50 keep the query cost low;
     * the product fields repeat per variant. Draft and archived products are
     * filtered by the caller (status).
     *
     * @return array<int, array>|array{error: string}
     */
    public function getFeedVariants(Shop $shop, int $max = 20000): array
    {
        $query = <<<'GRAPHQL'
        query feedVariants($first: Int!, $after: String) {
          productVariants(first: $first, after: $after) {
            pageInfo { hasNextPage endCursor }
            edges { node {
              id sku barcode price compareAtPrice availableForSale title
              selectedOptions { name value }
              image { url }
              product {
                id handle title vendor productType status onlineStoreUrl descriptionHtml totalVariants
                category { fullName }
                seo { description }
                media(first: 6) { nodes { ... on MediaImage { image { url } alt } } }
                aiAttributes: metafield(namespace: "fyndable", key: "ai_attributes") { value }
                rating: metafield(namespace: "reviews", key: "rating") { value }
                ratingCount: metafield(namespace: "reviews", key: "rating_count") { value }
              }
            } }
          }
        }
        GRAPHQL;

        $error = null;
        $items = $this->paginate($shop, $query, 'productVariants', $max, 50, $error);
        if ($error !== null) {
            return ['error' => $error];
        }
        if ($items === []) {
            return $items;
        }

        $ids = $this->getOnlineStoreProductIds($shop);
        if (isset($ids['error'])) {
            return ['error' => (string) $ids['error']];
        }
        foreach ($items as &$variant) {
            if (isset($variant['product'])) {
                $variant['product']['publishedOnline'] = isset($ids[$variant['product']['id'] ?? '']);
            }
        }
        unset($variant);

        return $items;
    }

    /**
     * Shop languages (2.3): [{locale, name, primary, published}].
     *
     * @return array<int, array>|array{error: string}
     */
    public function getShopLocales(Shop $shop): array
    {
        $response = $this->graphql($shop, '{ shopLocales { locale name primary published } }');

        return isset($response['error']) ? ['error' => (string) $response['error']] : ($response['data']['shopLocales'] ?? []);
    }

    /**
     * Translatable resources of one type with their source content (digest)
     * and the translations for one locale (2.3).
     *
     * @return array<int, array>|array{error: string}
     */
    public function getTranslatableResources(Shop $shop, string $resourceType, string $locale, int $max = 250): array
    {
        $query = <<<'GRAPHQL'
        query translatable($first: Int!, $after: String, $type: TranslatableResourceType!, $locale: String!) {
          translatableResources(first: $first, after: $after, resourceType: $type) {
            pageInfo { hasNextPage endCursor }
            edges { node {
              resourceId
              translatableContent { key value digest locale }
              translations(locale: $locale) { key value outdated }
            } }
          }
        }
        GRAPHQL;

        $items = [];
        $cursor = null;
        do {
            $response = $this->graphql($shop, $query, ['first' => 25, 'after' => $cursor, 'type' => $resourceType, 'locale' => $locale]);
            if (isset($response['error'])) {
                return ['error' => (string) $response['error']];
            }
            $connection = $response['data']['translatableResources'] ?? [];
            foreach ($connection['edges'] ?? [] as $edge) {
                $items[] = $edge['node'];
            }
            $cursor = ($connection['pageInfo']['hasNextPage'] ?? false) ? ($connection['pageInfo']['endCursor'] ?? null) : null;
        } while ($cursor !== null && count($items) < $max);

        return array_slice($items, 0, $max);
    }

    /**
     * @return array|array{error: string}
     */
    public function getTranslatableResource(Shop $shop, string $resourceId, string $locale): array
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        query translatableOne($id: ID!, $locale: String!) {
          translatableResource(resourceId: $id) {
            resourceId
            translatableContent { key value digest locale }
            translations(locale: $locale) { key value outdated }
          }
        }
        GRAPHQL, ['id' => $resourceId, 'locale' => $locale]);

        if (isset($response['error'])) {
            return ['error' => (string) $response['error']];
        }

        return $response['data']['translatableResource'] ?? ['error' => 'not_found'];
    }

    /**
     * Collections with their landing content status (2.5).
     *
     * @return array<int, array>|array{error: string}
     */
    public function getCollectionsForLanding(Shop $shop, int $max = 250): array
    {
        $query = <<<'GRAPHQL'
        query landingCollections($first: Int!, $after: String) {
          collections(first: $first, after: $after, sortKey: TITLE) {
            pageInfo { hasNextPage endCursor }
            edges { node {
              id title handle
              productsCount { count }
              landing: metafield(namespace: "fyndable", key: "collection_landing") { value updatedAt }
            } }
          }
        }
        GRAPHQL;

        $error = null;
        $items = $this->paginate($shop, $query, 'collections', $max, 100, $error);

        return $error !== null ? ['error' => $error] : $items;
    }

    /**
     * One collection with sample products and its landing content (2.5).
     *
     * @return array|array{error: string}
     */
    public function getCollectionForLanding(Shop $shop, string $collectionGid): array
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        query landingCollection($id: ID!) {
          collection(id: $id) {
            id title handle descriptionHtml
            products(first: 20) { nodes { title productType vendor } }
            landing: metafield(namespace: "fyndable", key: "collection_landing") { value }
          }
        }
        GRAPHQL, ['id' => $collectionGid]);

        if (isset($response['error'])) {
            return ['error' => (string) $response['error']];
        }

        return $response['data']['collection'] ?? ['error' => 'not_found'];
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
            'product' => 'id title handle status productType onlineStoreUrl featuredImage { url altText } seo { title description }',
            'collection' => 'id title handle image { url altText }',
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
              seo { title description }
              image { url altText }
              productsCount { count }
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
              body
              summary
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
     * Suggested store profile for onboarding: primary store language and
     * billing country (ISO codes). Null when not available.
     *
     * @return array{language: ?string, country: ?string}
     */
    public function getLocaleDefaults(Shop $shop): array
    {
        $defaults = ['language' => null, 'country' => null];

        $response = $this->graphql($shop, '{ shop { billingAddress { countryCodeV2 } } }');
        if (! isset($response['error'])) {
            $code = $response['data']['shop']['billingAddress']['countryCodeV2'] ?? null;
            $defaults['country'] = is_string($code) && strlen($code) === 2 ? strtoupper($code) : null;
        }

        // shopLocales needs read_locales; without it the language stays null.
        $response = $this->graphql($shop, '{ shopLocales { locale primary } }');
        if (! isset($response['error'])) {
            foreach ($response['data']['shopLocales'] ?? [] as $locale) {
                if (! empty($locale['primary']) && is_string($locale['locale'] ?? null)) {
                    $defaults['language'] = strtolower(substr($locale['locale'], 0, 2));
                    break;
                }
            }
        }

        return $defaults;
    }

    /**
     * Get shop details (name, currency, country, domain).
     */
    public function getShopDetails(Shop $shop): array
    {
        $query = <<<'GRAPHQL'
        query {
          shop {
            id
            name
            email
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
                'gid' => '',
                'email' => '',
            ];
        }

        $shopData = $response['data']['shop'] ?? [];

        return [
            'name' => $shopData['name'] ?? '',
            'domain' => $shopData['primaryDomain']['url'] ?? "https://{$shop->shop_domain}",
            'currency' => $shopData['currencyCode'] ?? 'USD',
            'country' => $shopData['shopAddress']['country'] ?? '',
            'gid' => $shopData['id'] ?? '',
            'email' => $shopData['email'] ?? '',
        ];
    }

    /**
     * Fetch all markets with regions, web presences and locale data.
     *
     * Requires read_markets + read_locales scopes.
     *
     * @return array<int, array>|array{error: string}
     */
    public function getMarkets(Shop $shop): array
    {
        $query = <<<'GRAPHQL'
        query getMarkets {
          markets(first: 50) {
            edges {
              node {
                id
                name
                enabled
                primary
                regions(first: 50) {
                  edges {
                    node {
                      ... on Country {
                        code
                        name
                        currency { isoCode }
                      }
                    }
                  }
                }
                webPresences(first: 10) {
                  edges {
                    node {
                      rootUrls { locale url }
                      defaultLocale
                      alternateLocales
                    }
                  }
                }
              }
            }
          }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query);
        if (isset($response['error'])) {
            return $response;
        }

        $markets = [];
        foreach ($response['data']['markets']['edges'] ?? [] as $edge) {
            $node = $edge['node'] ?? [];

            // Flatten regions
            $regions = array_map(
                fn ($re) => $re['node'] ?? [],
                $node['regions']['edges'] ?? []
            );

            // Flatten web presences
            $webPresences = array_map(
                fn ($wpe) => $wpe['node'] ?? [],
                $node['webPresences']['edges'] ?? []
            );

            $markets[] = [
                'id' => $node['id'] ?? '',
                'name' => $node['name'] ?? '',
                'enabled' => $node['enabled'] ?? false,
                'primary' => $node['primary'] ?? false,
                'regions' => $regions,
                'webPresences' => $webPresences,
            ];
        }

        return $markets;
    }

    /**
     * Fetch translations for a resource in a given locale.
     *
     * Requires read_translations scope.
     *
     * @param  string  $resourceGid  e.g. "gid://shopify/Product/123"
     * @param  string  $locale  e.g. "nl", "de", "fr"
     * @return array<int, array{key: string, value: string}>|array{error: string}
     */
    public function getTranslations(Shop $shop, string $resourceGid, string $locale): array
    {
        $query = <<<'GRAPHQL'
        query getTranslations($resourceId: ID!, $locale: String!) {
          translatableResource(resourceId: $resourceId) {
            translations(locale: $locale) {
              key
              value
              locale
            }
          }
        }
        GRAPHQL;

        $response = $this->graphql($shop, $query, [
            'resourceId' => $resourceGid,
            'locale' => $locale,
        ]);

        if (isset($response['error'])) {
            return $response;
        }

        return $response['data']['translatableResource']['translations'] ?? [];
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
    private function paginate(Shop $shop, string $query, string $connectionKey, int $limit, int $maxPageSize = 250, ?string &$error = null): array
    {
        $items = [];
        $cursor = null;
        $pageSize = min($limit, $maxPageSize);

        do {
            $variables = ['first' => $pageSize, 'after' => $cursor];
            $response = $this->graphql($shop, $query, $variables);

            if (isset($response['error'])) {
                Log::error('ShopifyContentFetcher: GraphQL error', [
                    'connection' => $connectionKey,
                    'error' => $response['error'],
                ]);
                $error = (string) $response['error'];
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

            return [
                'error' => 'graphql_errors',
                'message' => $body['errors'][0]['message'] ?? 'Shopify GraphQL query failed',
                'details' => $body['errors'],
            ];
        }

        return $body;
    }
}

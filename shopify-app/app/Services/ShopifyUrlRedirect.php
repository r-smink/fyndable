<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shopify URL redirects via the GraphQL Admin API (scope
 * write_online_store_navigation).
 *
 * Used by the redirect manager and 404 monitor (1.2), automatic redirects on
 * handle changes and deletions (TrackHandleChangeJob), and the root-domain
 * redirects for llms.txt and the IndexNow key file:
 *
 *   /llms.txt      -> /apps/fyndable/llms.txt
 *   /llms-full.txt -> /apps/fyndable/llms.txt?full=1
 */
class ShopifyUrlRedirect
{
    public function setup(Shop $shop, string $subpath = 'fyndable', string $prefix = 'apps'): array
    {
        return [
            $this->ensure($shop, '/llms.txt', "/{$prefix}/{$subpath}/llms.txt"),
            $this->ensure($shop, '/llms-full.txt', "/{$prefix}/{$subpath}/llms.txt?full=1"),
        ];
    }

    /**
     * Create the redirect, or point an existing redirect for the path to the
     * new target.
     *
     * @return array{path:string, status:string, id?:string, target?:string, message?:string}
     */
    public function ensure(Shop $shop, string $path, string $target): array
    {
        $existing = $this->find($shop, $path);
        if (isset($existing['error'])) {
            return ['path' => $path, 'status' => 'error', 'message' => $existing['error']];
        }

        if ($existing !== null && $existing['target'] === $target) {
            return ['path' => $path, 'status' => 'exists', 'id' => $existing['id'], 'target' => $target];
        }

        return $existing !== null
            ? $this->update($shop, $existing['id'], $path, $target)
            : $this->create($shop, $path, $target);
    }

    /**
     * Redirect for an exact path, null when there is none.
     *
     * @return array{id:string, path:string, target:string}|array{error:string}|null
     */
    public function find(Shop $shop, string $path): ?array
    {
        $result = $this->list($shop, 'path:'.$this->quote($path), null, 10);
        if (isset($result['error'])) {
            return $result;
        }
        foreach ($result['redirects'] as $redirect) {
            if (strcasecmp($redirect['path'], $path) === 0) {
                return $redirect;
            }
        }

        return null;
    }

    /**
     * One page of redirects. $query uses Shopify search syntax
     * (path:/x, target:/y or free text).
     *
     * @return array{redirects:array<int,array{id:string,path:string,target:string}>, next:?string}|array{error:string}
     */
    public function list(Shop $shop, ?string $query = null, ?string $after = null, int $first = 50): array
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        query redirects($first: Int!, $after: String, $query: String) {
          urlRedirects(first: $first, after: $after, query: $query, sortKey: PATH) {
            pageInfo { hasNextPage endCursor }
            nodes { id path target }
          }
        }
        GRAPHQL, ['first' => max(1, min($first, 250)), 'after' => $after, 'query' => $query !== '' ? $query : null]);

        if (isset($response['error'])) {
            return $response;
        }

        $connection = $response['data']['urlRedirects'] ?? [];

        return [
            'redirects' => array_map(fn (array $node) => [
                'id' => (string) $node['id'],
                'path' => (string) $node['path'],
                'target' => (string) $node['target'],
            ], $connection['nodes'] ?? []),
            'next' => ($connection['pageInfo']['hasNextPage'] ?? false) ? ($connection['pageInfo']['endCursor'] ?? null) : null,
        ];
    }

    public function create(Shop $shop, string $path, string $target): array
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        mutation create($urlRedirect: UrlRedirectInput!) {
          urlRedirectCreate(urlRedirect: $urlRedirect) {
            urlRedirect { id path target }
            userErrors { field message }
          }
        }
        GRAPHQL, ['urlRedirect' => ['path' => $path, 'target' => $target]]);

        return $this->result($shop, $response, 'urlRedirectCreate', $path, $target, 'created');
    }

    public function update(Shop $shop, string $id, string $path, string $target): array
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        mutation update($id: ID!, $urlRedirect: UrlRedirectInput!) {
          urlRedirectUpdate(id: $id, urlRedirect: $urlRedirect) {
            urlRedirect { id path target }
            userErrors { field message }
          }
        }
        GRAPHQL, ['id' => $id, 'urlRedirect' => ['path' => $path, 'target' => $target]]);

        return $this->result($shop, $response, 'urlRedirectUpdate', $path, $target, 'updated');
    }

    /**
     * @return string|null error message, null on success
     */
    public function delete(Shop $shop, string $id): ?string
    {
        $response = $this->graphql($shop, <<<'GRAPHQL'
        mutation delete($id: ID!) {
          urlRedirectDelete(id: $id) {
            deletedUrlRedirectId
            userErrors { field message }
          }
        }
        GRAPHQL, ['id' => $id]);

        if (isset($response['error'])) {
            return $response['error'];
        }
        $errors = $response['data']['urlRedirectDelete']['userErrors'] ?? [];

        return $errors === [] ? null : (string) ($errors[0]['message'] ?? 'delete_failed');
    }

    public static function gid(string $id): string
    {
        return str_starts_with($id, 'gid://') ? $id : 'gid://shopify/UrlRedirect/'.$id;
    }

    private function result(Shop $shop, array $response, string $key, string $path, string $target, string $status): array
    {
        if (isset($response['error'])) {
            return ['path' => $path, 'status' => 'error', 'message' => $response['error']];
        }

        $payload = $response['data'][$key] ?? [];
        $errors = $payload['userErrors'] ?? [];
        if ($errors !== [] || empty($payload['urlRedirect'])) {
            $message = (string) ($errors[0]['message'] ?? 'redirect_failed');
            Log::warning('ShopifyUrlRedirect: '.$key.' failed', ['shop' => $shop->shop_domain, 'path' => $path, 'message' => $message]);

            return ['path' => $path, 'status' => 'error', 'message' => $message];
        }

        return ['path' => $path, 'status' => $status, 'id' => (string) $payload['urlRedirect']['id'], 'target' => $target];
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function graphql(Shop $shop, string $query, array $variables): array
    {
        $token = app(ShopifyTokenService::class)->tokenFor($shop);
        if ($token === null) {
            return ['error' => 'no_access_token'];
        }

        $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/graphql.json';

        try {
            $response = Http::timeout(20)
                ->withHeaders(['X-Shopify-Access-Token' => $token, 'Content-Type' => 'application/json'])
                ->post($endpoint, ['query' => $query, 'variables' => $variables]);
        } catch (ConnectionException) {
            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'http_'.$response->status()];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return ['error' => 'invalid_response'];
        }
        if (! empty($body['errors'])) {
            Log::error('ShopifyUrlRedirect: GraphQL errors', ['shop' => $shop->shop_domain, 'errors' => $body['errors']]);

            return ['error' => (string) ($body['errors'][0]['message'] ?? 'graphql_errors')];
        }

        return $body;
    }
}

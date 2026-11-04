<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\CollectionLanding;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;

/**
 * CollectionLandingController (2.5 collection pages as SEO landing pages)
 *
 * GET  /api/collection-pages                 - collections + whether they have landing content
 * GET  /api/collection-pages/{id}            - saved intro + FAQ
 * POST /api/collection-pages/{id}/keywords   - keyword ideas with search volume
 * POST /api/collection-pages/{id}/generate   - {keywords[]} AI intro + FAQ (not saved)
 * POST /api/collection-pages/{id}/save       - {intro_html, faq[], keywords[]}
 */
class CollectionLandingController extends Controller
{
    public function __construct(
        private CollectionLanding $landing,
        private ShopifyContentFetcher $fetcher
    ) {}

    public function index(Request $request): array
    {
        $collections = $this->fetcher->getCollectionsForLanding($this->shop($request));
        if (isset($collections['error'])) {
            return $collections;
        }

        return ['success' => true, 'collections' => array_map(fn ($c) => [
            'id' => (string) preg_replace('#^.*/#', '', $c['id']),
            'title' => (string) $c['title'],
            'handle' => (string) $c['handle'],
            'products' => (int) ($c['productsCount']['count'] ?? 0),
            'has_landing' => ! empty($c['landing']['value']),
            'updated_at' => $c['landing']['updatedAt'] ?? null,
        ], $collections)];
    }

    public function show(Request $request, string $id): array
    {
        $result = $this->landing->current($this->shop($request), $this->gid($id));

        return isset($result['error']) ? $result : ['success' => true] + $result;
    }

    public function keywords(Request $request, string $id): array
    {
        $result = $this->landing->keywords($this->shop($request), $this->gid($id));

        return isset($result['error']) ? $result : ['success' => true] + $result;
    }

    public function generate(Request $request, string $id): array
    {
        $keywords = $request->input('keywords', []);
        if (! is_array($keywords)) {
            return ['error' => 'invalid_keywords'];
        }
        $result = $this->landing->draft($this->shop($request), $this->gid($id), $keywords);

        return isset($result['error']) ? $result : ['success' => true, 'landing' => $result];
    }

    public function save(Request $request, string $id): array
    {
        $data = $request->only(['intro_html', 'faq', 'keywords']);
        if (trim(strip_tags((string) ($data['intro_html'] ?? ''))) === '' && empty($data['faq'])) {
            return ['error' => 'nothing_to_save'];
        }
        $error = $this->landing->save($this->shop($request), $this->gid($id), $data);

        return $error === null ? ['success' => true, 'landing' => $this->landing->clean($data)] : ['error' => 'save_failed', 'message' => $error];
    }

    private function gid(string $id): string
    {
        return 'gid://shopify/Collection/'.$id;
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use App\Services\ShopifyAdminWriter;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ContentController
 *
 * Generic content SEO endpoints for collections, pages and blog articles —
 * and product passthrough to ProductController so the UI has one uniform
 * /api/content/{type}/{id}/* surface.
 *
 *  - List/search content items
 *  - Show current values (meta, body, schema, images)
 *  - AI-generate meta/description (via the SaaS proxy)
 *  - Deterministic JSON-LD schema preview + save to fyndable.* metafields
 */
class ContentController extends Controller
{
    private const TYPES = ['product', 'collection', 'page', 'article'];

    private const SCHEMA_KEYS = [
        'product' => 'product_schema',
        'collection' => 'collection_schema',
        'page' => 'page_schema',
        'article' => 'article_schema',
    ];

    private const TYPE_LABELS = [
        'collection' => 'collection page',
        'page' => 'content page',
        'article' => 'blog article',
    ];

    private const WRITE_SCOPES = [
        'product' => 'write_products',
        'collection' => 'write_content',
        'page' => 'write_content',
        'article' => 'write_content',
    ];

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private SaasProxyClient $saas,
        private LicenseService $license,
        private ShopifyAdminWriter $writer
    ) {}

    /**
     * List/search items of a content type.
     *
     * GET /api/content/{type}?search=&limit=
     */
    public function index(Request $request, string $type): array
    {
        $error = $this->validateType($type) ?? $this->guardShop($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $search = trim((string) $request->query('search', ''));
        $limit = max(1, min((int) $request->query('limit', 50), 250));

        $items = array_map(
            fn (array $item) => $this->normalizeListItem($type, $item),
            $this->fetcher->search($shop, $type, $search, $limit)
        );

        return ['success' => true, 'type' => $type, 'items' => $items];
    }

    /**
     * Fetch a single item with current SEO values for the editor.
     *
     * GET /api/content/{type}/{id}
     */
    public function show(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardShop($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $node = $this->fetcher->getNode($shop, $this->writer->gid(ucfirst($type), $id));

        if (isset($node['error'])) {
            return $node;
        }

        return ['success' => true, 'item' => $this->normalizeDetail($type, $node, $shop)];
    }

    /**
     * POST /api/content/{type}/{id}/generate-meta
     */
    public function generateMeta(Request $request, string $type, string $id): array
    {
        if ($type === 'product') {
            return app(ProductController::class)->generateMeta($request, $id);
        }

        $error = $this->validateType($type) ?? $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        try {
            $shop = $request->attributes->get('shop');
            $node = $this->fetcher->getNode($shop, $this->writer->gid(ucfirst($type), $id));
            if (isset($node['error'])) {
                return $node;
            }

            $label = self::TYPE_LABELS[$type];
            $body = mb_substr(strip_tags($this->extractBody($node)), 0, 2000);
            $context = trim((string) $request->input('context', ''));

            $prompt = "Generate SEO meta title and description for this {$label}.\n\n"
                ."Title: {$node['title']}\n"
                ."Content: {$body}\n";

            if ($context !== '') {
                $prompt .= "Additional context: {$context}\n";
            }

            $prompt .= "\nMeta title: max 60 characters. Meta description: max 155 characters.\n"
                .'Respond as JSON: {"title": "...", "description": "..."}';

            $result = $this->saas->aiGenerate(
                $shop->license_key,
                $shop->tenant_key,
                [
                    ['role' => 'system', 'content' => 'You are an SEO expert. Respond only in JSON: {"title": "...", "description": "..."}'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'openai/gpt-4o-mini',
                300,
                0.5,
                'content_meta'
            );

            if (isset($result['error'])) {
                return $result;
            }

            $parsed = $this->parseJsonResponse($result['text'] ?? '');
            if (empty($parsed)) {
                return ['error' => 'ai_parse_failed', 'raw' => $result['text'] ?? ''];
            }

            return [
                'success' => true,
                'title' => $parsed['title'] ?? '',
                'description' => $parsed['description'] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('ContentController::generateMeta failed', [
                'type' => $type, 'id' => $id, 'error' => $e->getMessage(),
            ]);

            return ['error' => 'generate_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Save SEO meta via the global.title_tag / global.description_tag metafields
     * (the mechanism Shopify uses for non-product resources).
     *
     * POST /api/content/{type}/{id}/save-meta
     */
    public function saveMeta(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardWrite($request, $type);
        if ($error) {
            return $error;
        }

        if ($type === 'product') {
            return app(ProductController::class)->saveMeta($request, $id);
        }

        $shop = $request->attributes->get('shop');
        $title = trim((string) $request->input('title', ''));
        $description = trim((string) $request->input('description', ''));

        if ($title === '' && $description === '') {
            return ['error' => 'nothing_to_save'];
        }

        $metafields = [];
        if ($title !== '') {
            $metafields[] = $this->writer->metafieldInput('global', 'title_tag', $title, 'single_line_text_field');
        }
        if ($description !== '') {
            $metafields[] = $this->writer->metafieldInput('global', 'description_tag', $description, 'multi_line_text_field');
        }

        $error = $this->writer->setMetafields($shop, $type, $id, $metafields);

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * POST /api/content/{type}/{id}/generate-description
     * Body: { type: "short"|"long", context }
     */
    public function generateDescription(Request $request, string $type, string $id): array
    {
        if ($type === 'product') {
            return app(ProductController::class)->generateDescription($request, $id);
        }

        $error = $this->validateType($type) ?? $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        try {
            $shop = $request->attributes->get('shop');
            $node = $this->fetcher->getNode($shop, $this->writer->gid(ucfirst($type), $id));
            if (isset($node['error'])) {
                return $node;
            }

            $label = self::TYPE_LABELS[$type];
            $variant = $request->input('type', 'long');
            $length = $variant === 'short' ? '1-2 sentences (max 100 words)' : '2-4 paragraphs (max 400 words)';
            $body = mb_substr(strip_tags($this->extractBody($node)), 0, 2000);
            $context = trim((string) $request->input('context', ''));

            $prompt = "Write an SEO-optimized {$label} text. Length: {$length}.\n\n"
                ."Title: {$node['title']}\n"
                ."Current content: {$body}\n";

            if ($context !== '') {
                $prompt .= "Additional context: {$context}\n";
            }

            $prompt .= "\nFocus on relevant keywords naturally. Do not use HTML tags.";

            $result = $this->saas->aiGenerate(
                $shop->license_key,
                $shop->tenant_key,
                [
                    ['role' => 'system', 'content' => 'You are an expert copywriter who writes SEO-optimized website content.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'openai/gpt-4o-mini',
                $variant === 'short' ? 300 : 1000,
                0.7,
                'content_description'
            );

            if (isset($result['error'])) {
                return $result;
            }

            return ['success' => true, 'description' => $result['text'], 'type' => $variant];
        } catch (\Throwable $e) {
            Log::error('ContentController::generateDescription failed', [
                'type' => $type, 'id' => $id, 'error' => $e->getMessage(),
            ]);

            return ['error' => 'generate_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Save the body/description for a collection, page or article.
     *
     * POST /api/content/{type}/{id}/save-description
     * Body: { description: "...", format: "text"|"html" }
     */
    public function saveDescription(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardWrite($request, $type);
        if ($error) {
            return $error;
        }

        if ($type === 'product') {
            return app(ProductController::class)->saveDescription($request, $id);
        }

        $shop = $request->attributes->get('shop');
        $description = $request->input('description', '');
        if (empty($description)) {
            return ['error' => 'nothing_to_save'];
        }

        $html = $request->input('format') === 'html'
            ? $description
            : nl2br(htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $error = match ($type) {
            'collection' => $this->writer->updateCollection($shop, $id, ['descriptionHtml' => $html]),
            'page' => $this->writer->updatePage($shop, $id, ['body' => $html]),
            'article' => $this->writer->updateArticle($shop, $id, ['body' => $html]),
            default => 'unsupported_type',
        };

        return $error
            ? ['error' => 'save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * Generate a deterministic JSON-LD schema preview (does not save).
     *
     * POST /api/content/{type}/{id}/generate-schema
     */
    public function generateSchema(Request $request, string $type, string $id): array
    {
        if ($type === 'product') {
            return app(ProductController::class)->generateSchema($request, $id);
        }

        $error = $this->validateType($type) ?? $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $node = $this->fetcher->getNode($shop, $this->writer->gid(ucfirst($type), $id));
        if (isset($node['error'])) {
            return $node;
        }

        return [
            'success' => true,
            'schema' => $this->buildSchema($type, $node, $shop),
        ];
    }

    /**
     * Save a (possibly user-edited) JSON-LD schema to the fyndable.* metafield.
     *
     * POST /api/content/{type}/{id}/save-schema
     * Body: { schema: { ... } }
     */
    public function saveSchema(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardWrite($request, $type);
        if ($error) {
            return $error;
        }

        if ($type === 'product') {
            return app(ProductController::class)->saveSchema($request, $id);
        }

        $schema = $request->input('schema');
        if (! is_array($schema) || empty($schema)) {
            return ['error' => 'schema_required', 'message' => 'Provide a schema JSON object.'];
        }

        if (empty($schema['@context']) || empty($schema['@type'])) {
            return ['error' => 'invalid_schema', 'message' => 'Schema must include @context and @type.'];
        }

        $shop = $request->attributes->get('shop');
        $metafield = $this->writer->metafieldInput(
            'fyndable',
            self::SCHEMA_KEYS[$type],
            json_encode($schema, JSON_UNESCAPED_SLASHES),
            'json'
        );

        $error = $this->writer->setMetafields($shop, $type, $id, [$metafield]);

        return $error
            ? ['error' => 'schema_save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * Generate an FAQ (Q&A pairs) for a resource via AI — returns both the
     * editable pairs and a ready-made FAQPage JSON-LD schema.
     *
     * POST /api/content/{type}/{id}/generate-faq
     * Body: { count?: int, context?: string }
     */
    public function generateFaq(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        try {
            $shop = $request->attributes->get('shop');
            $node = $this->fetcher->getNode($shop, $this->writer->gid(ucfirst($type), $id));
            if (isset($node['error'])) {
                return $node;
            }

            $label = self::TYPE_LABELS[$type] ?? 'product';
            $count = max(2, min((int) $request->input('count', 5), 10));
            $body = mb_substr(strip_tags($this->extractBody($node)), 0, 2000);
            $context = trim((string) $request->input('context', ''));

            $prompt = "Generate {$count} frequently asked questions with short, factual answers for this {$label}.\n\n"
                ."Title: {$node['title']}\n"
                ."Content: {$body}\n";

            if ($context !== '') {
                $prompt .= "Additional context: {$context}\n";
            }

            $prompt .= "\nRespond as JSON array only: [{\"question\": \"...\", \"answer\": \"...\"}]";

            $result = $this->saas->aiGenerate(
                $shop->license_key,
                $shop->tenant_key,
                [
                    ['role' => 'system', 'content' => 'You are an SEO expert. Respond only in JSON: [{"question": "...", "answer": "..."}]'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'openai/gpt-4o-mini',
                800,
                0.6,
                'faq_generation'
            );

            if (isset($result['error'])) {
                return $result;
            }

            $pairs = $this->parseJsonResponse($result['text'] ?? '');
            $pairs = array_values(array_filter(array_map(
                fn ($p) => [
                    'question' => trim((string) ($p['question'] ?? '')),
                    'answer' => trim((string) ($p['answer'] ?? '')),
                ],
                is_array($pairs) ? $pairs : []
            ), fn ($p) => $p['question'] !== '' && $p['answer'] !== ''));

            if (empty($pairs)) {
                return ['error' => 'ai_parse_failed', 'raw' => $result['text'] ?? ''];
            }

            return [
                'success' => true,
                'faq' => $pairs,
                'schema' => $this->buildFaqSchema($pairs),
            ];
        } catch (\Throwable $e) {
            Log::error('ContentController::generateFaq failed', [
                'type' => $type, 'id' => $id, 'error' => $e->getMessage(),
            ]);

            return ['error' => 'generate_failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Save FAQPage JSON-LD to the fyndable.faq_schema metafield.
     *
     * POST /api/content/{type}/{id}/save-faq
     * Body: { schema: {...} } — the FAQPage schema (possibly user-edited).
     */
    public function saveFaq(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardWrite($request, $type);
        if ($error) {
            return $error;
        }

        $schema = $request->input('schema');
        if (! is_array($schema) || ($schema['@type'] ?? '') !== 'FAQPage') {
            return ['error' => 'invalid_schema', 'message' => 'Provide a FAQPage schema object.'];
        }

        $shop = $request->attributes->get('shop');
        $metafield = $this->writer->metafieldInput(
            'fyndable',
            'faq_schema',
            json_encode($schema, JSON_UNESCAPED_SLASHES),
            'json'
        );

        $error = $this->writer->setMetafields($shop, $type, $id, [$metafield]);

        return $error
            ? ['error' => 'faq_save_failed', 'message' => $error]
            : ['success' => true];
    }

    /**
     * Suggest internal links: find other site resources whose title appears in
     * this item's body text but is not yet linked.
     *
     * POST /api/content/{type}/{id}/link-suggestions
     */
    public function linkSuggestions(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardShop($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $gid = $this->writer->gid(ucfirst($type), $id);
        $node = $this->fetcher->getNode($shop, $gid);
        if (isset($node['error'])) {
            return $node;
        }

        $bodyHtml = $this->extractBody($node);
        $bodyText = mb_strtolower(strip_tags($bodyHtml));
        $alreadyLinked = [];
        if (preg_match_all('/href="([^"]+)"/i', $bodyHtml, $m)) {
            $alreadyLinked = array_map('mb_strtolower', $m[1]);
        }

        $domain = rtrim("https://{$shop->shop_domain}", '/');
        $suggestions = [];

        foreach (['product', 'collection', 'page', 'article'] as $candidateType) {
            foreach ($this->fetcher->search($shop, $candidateType, '', 50) as $item) {
                $itemGid = $item['id'] ?? '';
                $title = trim((string) ($item['title'] ?? ''));
                // Skip self, too-short titles, and already-linked targets.
                if ($itemGid === $gid || mb_strlen($title) < 4) {
                    continue;
                }

                $url = $this->resourceUrl($candidateType, $item, $domain);
                if (in_array(mb_strtolower($url), $alreadyLinked, true)) {
                    continue;
                }

                // Anchor = full title when it literally occurs in the text.
                if (str_contains($bodyText, mb_strtolower($title))) {
                    $suggestions[] = [
                        'anchor' => $title,
                        'url' => $url,
                        'title' => $title,
                        'target_type' => $candidateType,
                    ];
                }
            }
        }

        return ['success' => true, 'suggestions' => $suggestions];
    }

    /**
     * Insert an internal link into the item's body HTML — wraps the first
     * plain-text occurrence of the anchor in an <a> tag — and saves it.
     *
     * POST /api/content/{type}/{id}/apply-link
     * Body: { anchor, url }
     */
    public function applyLink(Request $request, string $type, string $id): array
    {
        $error = $this->validateType($type) ?? $this->guardWrite($request, $type);
        if ($error) {
            return $error;
        }

        $anchor = trim((string) $request->input('anchor', ''));
        $url = trim((string) $request->input('url', ''));
        if ($anchor === '' || $url === '') {
            return ['error' => 'anchor_and_url_required'];
        }

        $shop = $request->attributes->get('shop');
        $node = $this->fetcher->getNode($shop, $this->writer->gid(ucfirst($type), $id));
        if (isset($node['error'])) {
            return $node;
        }

        $bodyHtml = $this->extractBody($node);
        $quoted = preg_quote($anchor, '/');

        // Only link text nodes — skip anchors inside existing tags/attributes.
        $newHtml = preg_replace(
            '/(?<![">])('.str_replace(' ', '\s+', $quoted).')(?![^<]*>)/i',
            '<a href="'.htmlspecialchars($url, ENT_QUOTES).'">$1</a>',
            $bodyHtml,
            1,
            $replaced
        );

        if (! $replaced || $newHtml === $bodyHtml) {
            return ['error' => 'anchor_not_found', 'message' => 'Anchor text not found in the body content.'];
        }

        $saveError = match ($type) {
            'product' => $this->writer->updateProduct($shop, $id, ['descriptionHtml' => $newHtml]),
            'collection' => $this->writer->updateCollection($shop, $id, ['descriptionHtml' => $newHtml]),
            'page' => $this->writer->updatePage($shop, $id, ['body' => $newHtml]),
            'article' => $this->writer->updateArticle($shop, $id, ['body' => $newHtml]),
            default => 'unsupported_type',
        };

        return $saveError
            ? ['error' => 'save_failed', 'message' => $saveError]
            : ['success' => true];
    }

    /**
     * Generate a product image via AI and attach it as product media.
     *
     * POST /api/content/product/{id}/generate-image
     * Body: { prompt?: string, alt?: string }
     */
    public function generateImage(Request $request, string $type, string $id): array
    {
        if ($type !== 'product') {
            return ['error' => 'unsupported_type', 'message' => 'AI images are only supported for products.'];
        }

        $error = $this->guardWrite($request, 'product');
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $node = $this->fetcher->getNode($shop, $this->writer->gid('Product', $id));
        if (isset($node['error'])) {
            return $node;
        }

        $prompt = trim((string) $request->input('prompt', ''));
        if ($prompt === '') {
            $prompt = "Professional product photo of {$node['title']}, clean studio background, high quality";
        }

        $result = $this->saas->aiImage($shop->license_key, $shop->tenant_key, $prompt);
        if (isset($result['error'])) {
            return ['error' => 'image_failed', 'message' => $result['message'] ?? $result['error']];
        }

        $imageUrl = $result['url'] ?? null;
        if (! $imageUrl) {
            return ['error' => 'image_failed', 'message' => 'No image URL returned.'];
        }

        $alt = trim((string) $request->input('alt', '')) ?: ($node['title'] ?? '');

        $error = $this->writer->addProductMedia($shop, $id, $imageUrl, $alt);

        return $error
            ? ['error' => 'media_failed', 'message' => $error]
            : ['success' => true, 'image_url' => $imageUrl, 'revised_prompt' => $result['revised_prompt'] ?? null];
    }

    /**
     * Build a FAQPage JSON-LD schema from Q&A pairs.
     */
    private function buildFaqSchema(array $pairs): array
    {
        return [
            '@context' => 'https://schema.org/',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($p) => [
                '@type' => 'Question',
                'name' => $p['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $p['answer'],
                ],
            ], $pairs),
        ];
    }

    /**
     * Build a JSON-LD schema for a collection, page or article.
     */
    private function buildSchema(string $type, array $node, Shop $shop): array
    {
        $domain = rtrim("https://{$shop->shop_domain}", '/');
        $description = mb_substr(strip_tags($this->extractBody($node)), 0, 300);

        $schema = [
            '@context' => 'https://schema.org/',
            '@type' => match ($type) {
                'collection' => 'CollectionPage',
                'page' => 'WebPage',
                'article' => 'BlogPosting',
            },
            'name' => $node['title'] ?? '',
            'description' => $description,
            'url' => $this->resourceUrl($type, $node, $domain),
        ];

        if ($type === 'article') {
            $schema['headline'] = $node['title'] ?? '';
            if (! empty($node['publishedAt'])) {
                $schema['datePublished'] = $node['publishedAt'];
            }
        }

        $image = $node['image']['url'] ?? $node['featuredImage']['url'] ?? null;
        if ($image) {
            $schema['image'] = $image;
        }

        return $schema;
    }

    /**
     * Build a storefront URL for a resource from its handle.
     */
    private function resourceUrl(string $type, array $node, string $domain): string
    {
        if (! empty($node['onlineStoreUrl'])) {
            return $node['onlineStoreUrl'];
        }

        $handle = $node['handle'] ?? '';

        return match ($type) {
            'product' => "{$domain}/products/{$handle}",
            'collection' => "{$domain}/collections/{$handle}",
            'page' => "{$domain}/pages/{$handle}",
            'article' => "{$domain}/blogs/".($node['blog']['handle'] ?? 'news')."/articles/{$handle}",
            default => $domain,
        };
    }

    /**
     * Extract the main body HTML for a node regardless of type.
     */
    private function extractBody(array $node): string
    {
        return $node['descriptionHtml']
            ?? $node['description']
            ?? $node['body']
            ?? $node['contentHtml']
            ?? $node['content']
            ?? '';
    }

    /**
     * Normalize a list/search result for the picker UI.
     */
    private function normalizeListItem(string $type, array $item): array
    {
        return [
            'id' => $item['id'] ?? '',
            'title' => $item['title'] ?? '(untitled)',
            'handle' => $item['handle'] ?? '',
            'subtitle' => match ($type) {
                'product' => trim(($item['productType'] ?? '').' '.($item['status'] ?? '')),
                'article' => $item['blog']['title'] ?? '',
                default => '',
            },
            'image' => $item['featuredImage']['url'] ?? $item['image']['url'] ?? null,
        ];
    }

    /**
     * Normalize a node() result into the editor shape.
     */
    private function normalizeDetail(string $type, array $node, Shop $shop): array
    {
        $metafields = [];
        foreach ($node['metafields']['edges'] ?? [] as $edge) {
            $metafield = $edge['node'] ?? [];
            $metafields[($metafield['namespace'] ?? '').'.'.($metafield['key'] ?? '')] = $metafield['value'] ?? '';
        }

        $domain = rtrim("https://{$shop->shop_domain}", '/');

        $schema = null;
        $rawSchema = $metafields['fyndable.'.self::SCHEMA_KEYS[$type]] ?? '';
        if ($rawSchema !== '') {
            $decoded = json_decode($rawSchema, true);
            $schema = is_array($decoded) ? $decoded : null;
        }

        $images = [];
        if ($type === 'product') {
            foreach ($node['images']['edges'] ?? [] as $edge) {
                $image = $edge['node'] ?? [];
                $images[] = [
                    'id' => $image['id'] ?? '',
                    'url' => $image['url'] ?? '',
                    'alt' => $image['altText'] ?? '',
                ];
            }
        }

        return [
            'id' => $node['id'] ?? '',
            'type' => $type,
            'title' => $node['title'] ?? '',
            'handle' => $node['handle'] ?? '',
            'url' => $this->resourceUrl($type, $node, $domain),
            'body' => $this->extractBody($node),
            'seo_title' => $node['seo']['title'] ?? $metafields['global.title_tag'] ?? '',
            'seo_description' => $node['seo']['description'] ?? $metafields['global.description_tag'] ?? '',
            'schema' => $schema,
            'images' => $images,
            'status' => $node['status'] ?? null,
        ];
    }

    private function validateType(string $type): ?array
    {
        return in_array($type, self::TYPES, true)
            ? null
            : ['error' => 'invalid_type', 'valid_types' => self::TYPES];
    }

    private function guardShop(Request $request): ?array
    {
        return $request->attributes->get('shop') instanceof Shop
            ? null
            : ['error' => 'shop_not_found'];
    }

    private function guardLicensed(Request $request): ?array
    {
        $error = $this->guardShop($request);
        if ($error) {
            return $error;
        }

        return $this->license->isActive($request->attributes->get('shop'))
            ? null
            : ['error' => 'license_inactive'];
    }

    /**
     * Licensed check + the Shopify write scope required for this content type.
     */
    private function guardWrite(Request $request, string $type): ?array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $scope = self::WRITE_SCOPES[$type] ?? null;

        if ($scope !== null && in_array($scope, $shop->missingScopes(), true)) {
            return [
                'error' => 'missing_scopes',
                'missing_scopes' => $shop->missingScopes(),
                'reauth_url' => '/install?shop='.urlencode($shop->shop_domain),
                'message' => "Missing access scope: {$scope}. Re-authorize the app to grant it.",
            ];
        }

        return null;
    }

    /**
     * Try to parse a JSON response from the LLM (handles markdown code fences).
     */
    private function parseJsonResponse(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);

        return is_array($parsed) ? $parsed : [];
    }
}

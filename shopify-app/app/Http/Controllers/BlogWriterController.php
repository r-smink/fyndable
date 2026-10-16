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
 * BlogWriterController
 *
 * AI blog article writer: generates a draft (title + HTML body) via the SaaS
 * AI proxy, then creates the article on a chosen Shopify blog.
 */
class BlogWriterController extends Controller
{
    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private ShopifyAdminWriter $writer,
        private SaasProxyClient $saas,
        private LicenseService $license
    ) {}

    /**
     * List the shop's blogs.
     *
     * GET /api/blogs
     */
    public function blogs(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        return ['success' => true, 'blogs' => $this->fetcher->getBlogs($shop)];
    }

    /**
     * Supported output languages for generated content.
     */
    private const LANGUAGES = [
        'en' => 'English',
        'nl' => 'Dutch',
        'de' => 'German',
        'fr' => 'French',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'da' => 'Danish',
        'sv' => 'Swedish',
        'no' => 'Norwegian',
        'fi' => 'Finnish',
        'pl' => 'Polish',
    ];

    /**
     * Generate an article draft.
     *
     * POST /api/articles/generate
     * Body: { topic, keywords?, tone?, word_count?, language? }
     */
    public function generate(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $topic = trim((string) $request->input('topic', ''));
        if ($topic === '') {
            return ['error' => 'topic_required'];
        }

        $keywords = trim((string) $request->input('keywords', ''));
        $tone = trim((string) $request->input('tone', 'informative'));
        $wordCount = max(200, min((int) $request->input('word_count', 800), 2500));
        $language = self::LANGUAGES[strtolower((string) $request->input('language', 'en'))] ?? 'English';

        $details = $this->fetcher->getShopDetails($shop);
        $shopName = $details['name'] ?? $shop->shop_domain;

        $prompt = "Write an SEO-optimized blog article for the Shopify store \"{$shopName}\".\n\n"
            ."Topic: {$topic}\n"
            ."Language: {$language} (write the entire article, including the title, in {$language})\n"
            ."Tone: {$tone}\n"
            ."Length: about {$wordCount} words.\n";

        if ($keywords !== '') {
            $prompt .= "Target keywords (use naturally): {$keywords}\n";
        }

        $prompt .= "\nRespond as JSON: {\"title\": \"...\", \"body_html\": \"...\"}\n"
            .'body_html uses simple HTML (<h2>, <p>, <ul>, <li>, <strong>). No <html>/<body> tags.';

        try {
            $result = $this->saas->aiGenerate(
                $shop->license_key,
                $shop->tenant_key,
                [
                    ['role' => 'system', 'content' => 'You are an expert SEO content writer. Respond only in JSON: {"title": "...", "body_html": "..."}'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'openai/gpt-4o-mini',
                4000,
                0.7,
                'blog_article'
            );
        } catch (\Throwable $e) {
            Log::error('BlogWriterController::generate failed', ['error' => $e->getMessage()]);

            return ['error' => 'generate_failed', 'message' => $e->getMessage()];
        }

        if (isset($result['error'])) {
            return $result;
        }

        $parsed = $this->parseJsonResponse($result['text'] ?? '');
        if (empty($parsed['title']) || empty($parsed['body_html'])) {
            return ['error' => 'ai_parse_failed', 'raw' => $result['text'] ?? ''];
        }

        return [
            'success' => true,
            'title' => $parsed['title'],
            'body_html' => $parsed['body_html'],
        ];
    }

    /**
     * Create an article on a blog (draft by default).
     *
     * POST /api/blogs/{blogId}/articles
     * Body: { title, body_html, tags?, publish?: bool, author? }
     */
    public function create(Request $request, string $blogId): array
    {
        $error = $this->guardWrite($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $title = trim((string) $request->input('title', ''));
        $bodyHtml = (string) $request->input('body_html', '');

        if ($title === '' || $bodyHtml === '') {
            return ['error' => 'title_and_body_required'];
        }

        // author is a required AuthorInput in recent API versions — fall back
        // to the shop name when the merchant leaves the field empty.
        $authorName = trim((string) $request->input('author', ''));
        if ($authorName === '') {
            $details = $this->fetcher->getShopDetails($shop);
            $authorName = trim((string) ($details['name'] ?? '')) ?: $shop->shop_domain;
        }

        $fields = [
            'title' => $title,
            'body' => $bodyHtml,
            'isPublished' => $request->boolean('publish'),
            'author' => ['name' => $authorName],
        ];

        $tags = array_values(array_filter(array_map(
            fn ($t) => trim((string) $t),
            (array) $request->input('tags', [])
        )));
        if (! empty($tags)) {
            $fields['tags'] = $tags;
        }

        $error = $this->writer->createArticle($shop, $blogId, $fields);

        return $error
            ? ['error' => 'create_failed', 'message' => $error]
            : ['success' => true];
    }

    private function guardLicensed(Request $request): ?array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        return $this->license->isActive($shop)
            ? null
            : ['error' => 'license_inactive'];
    }

    private function guardWrite(Request $request): ?array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        if (in_array('write_content', $shop->missingScopes(), true)) {
            return [
                'error' => 'missing_scopes',
                'missing_scopes' => $shop->missingScopes(),
                'reauth_url' => '/install?shop='.urlencode($shop->shop_domain),
                'message' => 'Missing access scope: write_content. Re-authorize the app to grant it.',
            ];
        }

        return null;
    }

    private function parseJsonResponse(string $text): array
    {
        $text = preg_replace('/^```(?:json)?\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $parsed = json_decode(trim($text), true);

        return is_array($parsed) ? $parsed : [];
    }
}

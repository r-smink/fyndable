<?php

namespace App\Services;

use App\Models\LlmsTxtSettings;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;

/**
 * LlmsTxtGenerator
 *
 * Generates /llms.txt (markdown summary with links) and /llms-full.txt (full
 * content) for a Shopify shop, following the https://llmstxt.org spec.
 *
 * Content sources (all configurable):
 *  - Products (title, URL, description, price, specs)
 *  - Collections (title, URL, description, product count)
 *  - Pages (title, URL, body text)
 *  - Blog articles (title, URL, body text)
 *
 * Caching: generated content is cached per shop (TTL 6h, invalidated by webhooks).
 */
class LlmsTxtGenerator
{
    private const CACHE_KEY_SUMMARY = 'llmstxt:summary:';

    private const CACHE_KEY_FULL = 'llmstxt:full:';

    private const CACHE_TTL = 21600; // 6 hours

    public function __construct(
        private ShopifyContentFetcher $fetcher
    ) {}

    /**
     * Get the cached (or freshly generated) /llms.txt content.
     */
    public function getSummary(Shop $shop, LlmsTxtSettings $settings): string
    {
        $key = self::CACHE_KEY_SUMMARY.$shop->id;
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $content = $this->generateSummary($shop, $settings);
        Cache::put($key, $content, self::CACHE_TTL);

        return $content;
    }

    /**
     * Get the cached (or freshly generated) /llms-full.txt content.
     */
    public function getFull(Shop $shop, LlmsTxtSettings $settings): string
    {
        $key = self::CACHE_KEY_FULL.$shop->id;
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $content = $this->generateFull($shop, $settings);
        Cache::put($key, $content, self::CACHE_TTL);

        return $content;
    }

    /**
     * Invalidate the cache for a shop (called by webhooks on content changes).
     */
    public function invalidate(Shop $shop): void
    {
        Cache::forget(self::CACHE_KEY_SUMMARY.$shop->id);
        Cache::forget(self::CACHE_KEY_FULL.$shop->id);
    }

    /**
     * Generate the /llms.txt markdown summary.
     */
    public function generateSummary(Shop $shop, LlmsTxtSettings $settings): string
    {
        $shopDetails = $this->fetcher->getShopDetails($shop);
        $siteName = $shopDetails['name'] ?: $shop->shop_name ?: $shop->shop_domain;
        $siteDomain = rtrim($shopDetails['domain'] ?: "https://{$shop->shop_domain}", '/');
        $description = $settings->description ?: __('SEO-optimized Shopify store');

        $lines = [];
        $lines[] = "# {$siteName}";
        $lines[] = '';
        $lines[] = "> {$description}";
        $lines[] = '';

        // Products
        if ($settings->include_products) {
            $products = $this->fetcher->getProducts($shop, $settings->max_products);
            if (! empty($products)) {
                $lines[] = '## Products';
                $lines[] = '';
                foreach ($products as $product) {
                    $url = $product['onlineStoreUrl'] ?: "{$siteDomain}/products/{$product['handle']}";
                    $title = trim($product['title']);
                    $entry = "- [{$title}]({$url})";
                    if ($settings->include_excerpt) {
                        $excerpt = $this->makeExcerpt($product['description'] ?? '');
                        if ($excerpt) {
                            $entry .= ": {$excerpt}";
                        }
                    }
                    $lines[] = $entry;
                }
                $lines[] = '';
            }
        }

        // Collections
        if ($settings->include_collections) {
            $collections = $this->fetcher->getCollections($shop, $settings->max_collections);
            if (! empty($collections)) {
                $lines[] = '## Collections';
                $lines[] = '';
                foreach ($collections as $collection) {
                    $url = $collection['onlineStoreUrl'] ?: "{$siteDomain}/collections/{$collection['handle']}";
                    $title = trim($collection['title']);
                    $entry = "- [{$title}]({$url})";
                    if ($settings->include_excerpt) {
                        $excerpt = $this->makeExcerpt($collection['description'] ?? '');
                        if ($excerpt) {
                            $entry .= ": {$excerpt}";
                        }
                    }
                    $lines[] = $entry;
                }
                $lines[] = '';
            }
        }

        // Pages
        if ($settings->include_pages) {
            $pages = $this->fetcher->getPages($shop, $settings->max_pages);
            if (! empty($pages)) {
                $lines[] = '## Pages';
                $lines[] = '';
                foreach ($pages as $page) {
                    $url = $page['url'] ?: "{$siteDomain}/pages/{$page['handle']}";
                    $title = trim($page['title']);
                    $entry = "- [{$title}]({$url})";
                    if ($settings->include_excerpt) {
                        $excerpt = $this->makeExcerpt($page['bodySummary'] ?? $page['body'] ?? '');
                        if ($excerpt) {
                            $entry .= ": {$excerpt}";
                        }
                    }
                    $lines[] = $entry;
                }
                $lines[] = '';
            }
        }

        // Blog articles
        if ($settings->include_blogs) {
            $articles = $this->fetcher->getArticles($shop, $settings->max_articles);
            if (! empty($articles)) {
                $lines[] = '## Blog';
                $lines[] = '';
                foreach ($articles as $article) {
                    $blogHandle = $article['_blog_handle'] ?? 'news';
                    $url = $article['url'] ?: "{$siteDomain}/blogs/{$blogHandle}/{$article['handle']}";
                    $title = trim($article['title']);
                    $entry = "- [{$title}]({$url})";
                    if ($settings->include_excerpt) {
                        $excerpt = $this->makeExcerpt($article['excerpt'] ?? $article['content'] ?? '');
                        if ($excerpt) {
                            $entry .= ": {$excerpt}";
                        }
                    }
                    $lines[] = $entry;
                }
                $lines[] = '';
            }
        }

        // Custom sections (raw markdown)
        $custom = trim($settings->custom_sections ?? '');
        if ($custom) {
            $lines[] = $custom;
            $lines[] = '';
        }

        // Link to full version
        if ($settings->full_enabled) {
            $fullUrl = "{$siteDomain}/apps/fyndable/llms-full.txt";
            $lines[] = '## Full content';
            $lines[] = '';
            $lines[] = "- [Full site content]({$fullUrl}): Complete text of all products, collections, pages, and blog articles";
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Generate the /llms-full.txt full-content version.
     */
    public function generateFull(Shop $shop, LlmsTxtSettings $settings): string
    {
        $shopDetails = $this->fetcher->getShopDetails($shop);
        $siteName = $shopDetails['name'] ?: $shop->shop_name ?: $shop->shop_domain;
        $siteDomain = rtrim($shopDetails['domain'] ?: "https://{$shop->shop_domain}", '/');
        $description = $settings->description ?: __('SEO-optimized Shopify store');
        $maxChars = $settings->full_max_chars ?: 50000;

        $lines = [];
        $lines[] = "# {$siteName}";
        $lines[] = '';
        $lines[] = "> {$description}";
        $lines[] = '';

        // Products
        if ($settings->include_products) {
            $products = $this->fetcher->getProducts($shop, $settings->max_products);
            if (! empty($products)) {
                $lines[] = '## Products';
                $lines[] = '';
                foreach ($products as $product) {
                    $lines = array_merge($lines, $this->formatProductFull($product, $siteDomain, $maxChars));
                    $lines[] = '';
                }
            }
        }

        // Collections
        if ($settings->include_collections) {
            $collections = $this->fetcher->getCollections($shop, $settings->max_collections);
            if (! empty($collections)) {
                $lines[] = '## Collections';
                $lines[] = '';
                foreach ($collections as $collection) {
                    $lines = array_merge($lines, $this->formatCollectionFull($collection, $siteDomain, $maxChars));
                    $lines[] = '';
                }
            }
        }

        // Pages
        if ($settings->include_pages) {
            $pages = $this->fetcher->getPages($shop, $settings->max_pages);
            if (! empty($pages)) {
                $lines[] = '## Pages';
                $lines[] = '';
                foreach ($pages as $page) {
                    $lines = array_merge($lines, $this->formatPageFull($page, $siteDomain, $maxChars));
                    $lines[] = '';
                }
            }
        }

        // Blog articles
        if ($settings->include_blogs) {
            $articles = $this->fetcher->getArticles($shop, $settings->max_articles);
            if (! empty($articles)) {
                $lines[] = '## Blog';
                $lines[] = '';
                foreach ($articles as $article) {
                    $lines = array_merge($lines, $this->formatArticleFull($article, $siteDomain, $maxChars));
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format a product as a full-content markdown block.
     */
    private function formatProductFull(array $product, string $siteDomain, int $maxChars): array
    {
        $url = $product['onlineStoreUrl'] ?: "{$siteDomain}/products/{$product['handle']}";
        $title = trim($product['title']);
        $body = $this->stripHtml($product['description'] ?? '');

        // Add product specs
        $specs = [];
        if (! empty($product['vendor'])) {
            $specs[] = "Vendor: {$product['vendor']}";
        }
        if (! empty($product['productType'])) {
            $specs[] = "Type: {$product['productType']}";
        }
        if (! empty($product['tags'])) {
            $tags = is_array($product['tags']) ? implode(', ', $product['tags']) : $product['tags'];
            $specs[] = "Tags: {$tags}";
        }

        // Price from first variant
        $variants = $product['variants']['edges'] ?? [];
        if (! empty($variants)) {
            $firstVariant = $variants[0]['node'] ?? [];
            if (! empty($firstVariant['price'])) {
                $specs[] = "Price: {$firstVariant['price']}";
            }
            if (! empty($firstVariant['compareAtPrice'])) {
                $specs[] = "Compare at: {$firstVariant['compareAtPrice']}";
            }
        }

        $fullBody = $body;
        if (! empty($specs)) {
            $fullBody .= "\n\n".implode("\n", $specs);
        }

        $fullBody = trim(preg_replace('/\s+/', ' ', $fullBody));
        if (mb_strlen($fullBody) > $maxChars) {
            $fullBody = mb_substr($fullBody, 0, $maxChars - 3).'...';
        }

        return [
            "### {$title}",
            "URL: {$url}",
            '',
            $fullBody,
        ];
    }

    /**
     * Format a collection as a full-content markdown block.
     */
    private function formatCollectionFull(array $collection, string $siteDomain, int $maxChars): array
    {
        $url = $collection['onlineStoreUrl'] ?: "{$siteDomain}/collections/{$collection['handle']}";
        $title = trim($collection['title']);
        $body = $this->stripHtml($collection['description'] ?? '');
        $productCount = (int) ($collection['productsCount']['count'] ?? 0);

        $fullBody = $body;
        if ($productCount > 0) {
            $fullBody .= "\n\nProducts in collection: {$productCount}";
        }

        $fullBody = trim(preg_replace('/\s+/', ' ', $fullBody));
        if (mb_strlen($fullBody) > $maxChars) {
            $fullBody = mb_substr($fullBody, 0, $maxChars - 3).'...';
        }

        return [
            "### {$title}",
            "URL: {$url}",
            '',
            $fullBody,
        ];
    }

    /**
     * Format a page as a full-content markdown block.
     */
    private function formatPageFull(array $page, string $siteDomain, int $maxChars): array
    {
        $url = $page['url'] ?: "{$siteDomain}/pages/{$page['handle']}";
        $title = trim($page['title']);
        $body = $this->stripHtml($page['body'] ?? '');

        $body = trim(preg_replace('/\s+/', ' ', $body));
        if (mb_strlen($body) > $maxChars) {
            $body = mb_substr($body, 0, $maxChars - 3).'...';
        }

        return [
            "### {$title}",
            "URL: {$url}",
            '',
            $body,
        ];
    }

    /**
     * Format a blog article as a full-content markdown block.
     */
    private function formatArticleFull(array $article, string $siteDomain, int $maxChars): array
    {
        $blogHandle = $article['_blog_handle'] ?? 'news';
        $url = $article['url'] ?: "{$siteDomain}/blogs/{$blogHandle}/{$article['handle']}";
        $title = trim($article['title']);
        $body = $this->stripHtml($article['content'] ?? '');

        $body = trim(preg_replace('/\s+/', ' ', $body));
        if (mb_strlen($body) > $maxChars) {
            $body = mb_substr($body, 0, $maxChars - 3).'...';
        }

        return [
            "### {$title}",
            "URL: {$url}",
            '',
            $body,
        ];
    }

    /**
     * Create a short excerpt (max 200 chars) from a text.
     */
    private function makeExcerpt(string $text): string
    {
        $text = $this->stripHtml($text);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) > 200) {
            return mb_substr($text, 0, 197).'...';
        }

        return $text;
    }

    /**
     * Strip HTML tags and decode entities.
     */
    private function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }
}

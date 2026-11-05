<?php

namespace App\Services;

/**
 * SeoAuditor
 *
 * Scores products, collections, pages and blog articles (0-100) and runs the
 * store-wide checks (duplicate titles/descriptions, broken internal links).
 * Pure logic: SeoAuditJob fetches the items, ContentController scores a
 * single item for the editor. The item score is available on every plan
 * (WordPress: TruSEO score, base); the store-wide audit is Professional
 * (WordPress: Technical SEO Auditor).
 *
 * Item shape (see normalize()):
 *   type, id, title, handle, url, seo_title, seo_description, body_html,
 *   images (list of alt texts, null = missing), products_count, hidden,
 *   published
 */
class SeoAuditor
{
    /** Points deducted per issue. Store score = average item score. */
    public const ISSUES = [
        'hidden_from_search' => ['severity' => 'critical', 'penalty' => 40],
        'not_published' => ['severity' => 'critical', 'penalty' => 30],
        'empty_collection' => ['severity' => 'critical', 'penalty' => 25],
        'broken_links' => ['severity' => 'critical', 'penalty' => 15],
        'meta_desc_missing' => ['severity' => 'warning', 'penalty' => 15],
        'thin_content' => ['severity' => 'warning', 'penalty' => 15],
        'duplicate_title' => ['severity' => 'warning', 'penalty' => 10],
        'duplicate_description' => ['severity' => 'warning', 'penalty' => 10],
        'missing_alt' => ['severity' => 'warning', 'penalty' => 10],
        'meta_title_missing' => ['severity' => 'info', 'penalty' => 8],
        'meta_title_long' => ['severity' => 'info', 'penalty' => 6],
        'meta_title_short' => ['severity' => 'info', 'penalty' => 4],
        'meta_desc_long' => ['severity' => 'info', 'penalty' => 5],
        'meta_desc_short' => ['severity' => 'info', 'penalty' => 5],
        'multiple_h1' => ['severity' => 'info', 'penalty' => 5],
    ];

    /** Bulk optimizer action that fixes an issue (BulkOptimizer::ACTIONS). */
    public const BULK_FIXES = [
        'meta_title_missing' => 'meta_title',
        'meta_title_long' => 'meta_title',
        'meta_title_short' => 'meta_title',
        'meta_desc_missing' => 'meta',
        'meta_desc_short' => 'meta',
        'meta_desc_long' => 'meta',
        'missing_alt' => 'alt_text',
        'thin_content' => 'descriptions',
    ];

    public const TITLE_MIN = 20;

    public const TITLE_MAX = 70;

    public const DESC_MIN = 70;

    public const DESC_MAX = 160;

    /** Minimum words of body text before content counts as thin. */
    public const MIN_WORDS = ['product' => 50, 'collection' => 30, 'page' => 100, 'article' => 300];

    /**
     * Normalize an Admin GraphQL node (ShopifyContentFetcher::getAuditItems)
     * into the item shape. $host is the storefront host for URLs.
     */
    public function normalize(string $type, array $node, string $host): array
    {
        $images = [];
        if ($type === 'product') {
            foreach ($node['media']['nodes'] ?? [] as $media) {
                if (($media['mediaContentType'] ?? 'IMAGE') === 'IMAGE') {
                    $images[] = $this->clean($media['alt'] ?? null);
                }
            }
        } elseif (! empty($node['image'])) {
            $images[] = $this->clean($node['image']['altText'] ?? null);
        }

        $handle = (string) ($node['handle'] ?? '');
        $url = match ($type) {
            'product' => $node['onlineStoreUrl'] ?? "https://{$host}/products/{$handle}",
            'collection' => "https://{$host}/collections/{$handle}",
            'page' => "https://{$host}/pages/{$handle}",
            'article' => "https://{$host}/blogs/".($node['blog']['handle'] ?? 'news')."/{$handle}",
            default => '',
        };

        return [
            'type' => $type,
            'id' => (string) ($node['id'] ?? ''),
            'title' => (string) ($node['title'] ?? ''),
            'handle' => $handle,
            'blog_handle' => $node['blog']['handle'] ?? null,
            'url' => $url,
            'seo_title' => $this->clean($node['seo']['title'] ?? $node['titleTag']['value'] ?? null),
            'seo_description' => $this->clean($node['seo']['description'] ?? $node['descriptionTag']['value'] ?? null),
            'body_html' => (string) ($node['descriptionHtml'] ?? $node['body'] ?? ''),
            'images' => $images,
            'products_count' => $type === 'collection' ? (int) ($node['productsCount']['count'] ?? 0) : null,
            'hidden' => ($node['hidden']['value'] ?? null) === '1',
            // Products are fetched with status:active. publishedOnline comes
            // from the published_status filter; onlineStoreUrl alone is null
            // on password protected storefronts, so it is only a fallback.
            'published' => $type !== 'product' || ($node['publishedOnline'] ?? ! empty($node['onlineStoreUrl'])),
        ];
    }

    /**
     * Item checks that need no other items.
     *
     * @return array{score:int, issues:array<int,array{code:string,severity:string,detail?:mixed}>}
     */
    public function scoreItem(array $item): array
    {
        $issues = [];
        $type = $item['type'];

        if ($item['hidden'] ?? false) {
            $issues[] = $this->issue('hidden_from_search');
        }
        if (! ($item['published'] ?? true)) {
            $issues[] = $this->issue('not_published');
        }
        if ($type === 'collection' && ($item['products_count'] ?? null) === 0) {
            $issues[] = $this->issue('empty_collection');
        }

        $seoTitle = $item['seo_title'] ?? null;
        $title = $seoTitle ?? ($item['title'] ?? '');
        if ($seoTitle === null) {
            $issues[] = $this->issue('meta_title_missing');
        }
        $titleLength = mb_strlen($title);
        if ($titleLength > self::TITLE_MAX) {
            $issues[] = $this->issue('meta_title_long', $titleLength);
        } elseif ($titleLength > 0 && $titleLength < self::TITLE_MIN) {
            $issues[] = $this->issue('meta_title_short', $titleLength);
        }

        $description = $item['seo_description'] ?? null;
        if ($description === null) {
            $issues[] = $this->issue('meta_desc_missing');
        } elseif (mb_strlen($description) > self::DESC_MAX) {
            $issues[] = $this->issue('meta_desc_long', mb_strlen($description));
        } elseif (mb_strlen($description) < self::DESC_MIN) {
            $issues[] = $this->issue('meta_desc_short', mb_strlen($description));
        }

        $body = (string) ($item['body_html'] ?? '');
        $words = $this->wordCount($body);
        if ($words < (self::MIN_WORDS[$type] ?? 50)) {
            $issues[] = $this->issue('thin_content', $words);
        }
        // The theme renders the title as H1; another H1 in the body competes with it.
        if (preg_match('/<h1[\s>]/i', $body)) {
            $issues[] = $this->issue('multiple_h1');
        }

        $missingAlt = count(array_filter($item['images'] ?? [], fn ($alt) => $alt === null));
        if ($missingAlt > 0) {
            $issues[] = $this->issue('missing_alt', $missingAlt);
        }

        return ['score' => $this->score($issues), 'issues' => $issues];
    }

    /**
     * Full audit over all items: item checks plus duplicates and broken
     * internal links. $hosts are the storefront hosts that count as internal.
     *
     * @return array{score:?int, items:array, summary:array<string,int>}
     */
    public function audit(array $items, array $hosts): array
    {
        $titles = [];
        $descriptions = [];
        $known = [];
        foreach ($items as $item) {
            $titleKey = $this->key($item['seo_title'] ?? $item['title']);
            if ($titleKey !== '') {
                $titles[$titleKey][] = $item['id'];
            }
            $descKey = $this->key($item['seo_description'] ?? '');
            if ($descKey !== '') {
                $descriptions[$descKey][] = $item['id'];
            }
            $known[$this->path($item)] = true;
        }

        $results = [];
        $summary = [];
        foreach ($items as $item) {
            $result = $this->scoreItem($item);

            $titleKey = $this->key($item['seo_title'] ?? $item['title']);
            if ($titleKey !== '' && count($titles[$titleKey]) > 1) {
                $result['issues'][] = $this->issue('duplicate_title', count($titles[$titleKey]));
            }
            $descKey = $this->key($item['seo_description'] ?? '');
            if ($descKey !== '' && count($descriptions[$descKey]) > 1) {
                $result['issues'][] = $this->issue('duplicate_description', count($descriptions[$descKey]));
            }

            $broken = $this->brokenLinks((string) ($item['body_html'] ?? ''), $known, $hosts);
            if ($broken !== []) {
                $result['issues'][] = $this->issue('broken_links', array_slice($broken, 0, 10));
            }

            $result['score'] = $this->score($result['issues']);
            foreach ($result['issues'] as $issue) {
                $summary[$issue['code']] = ($summary[$issue['code']] ?? 0) + 1;
            }

            $results[] = [
                'type' => $item['type'],
                'resource_id' => $item['id'],
                'title' => $item['title'],
                'url' => $item['url'],
                'score' => $result['score'],
                'issues' => $result['issues'],
            ];
        }

        $score = $results === [] ? null : (int) round(array_sum(array_column($results, 'score')) / count($results));

        return ['score' => $score, 'items' => $results, 'summary' => $summary];
    }

    /**
     * Internal storefront links in the body that point to a product,
     * collection, page or article that does not exist (or is not active).
     *
     * @return array<int,string> broken paths
     */
    public function brokenLinks(string $html, array $known, array $hosts): array
    {
        if ($html === '' || ! preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }

        $hosts = array_map('strtolower', $hosts);
        $broken = [];
        foreach ($matches[1] as $href) {
            $parts = parse_url(html_entity_decode($href));
            if ($parts === false) {
                continue;
            }
            $host = strtolower($parts['host'] ?? '');
            if ($host !== '' && ! in_array($host, $hosts, true)) {
                continue;
            }
            $path = rtrim(strtolower(rawurldecode($parts['path'] ?? '')), '/');
            // Locale prefixes (/nl/products/x) and collection-scoped product URLs.
            $path = preg_replace('#^/[a-z]{2}(-[a-z]{2})?(?=/(products|collections|pages|blogs)/)#', '', $path);
            $path = preg_replace('#^/collections/[^/]+(?=/products/)#', '', $path);
            if (! preg_match('#^/(products|collections|pages)/[^/]+$|^/blogs/[^/]+/[^/]+$#', $path)) {
                continue;
            }
            if ($path === '/collections/all' || isset($known[$path])) {
                continue;
            }
            $broken[$path] = true;
        }

        return array_keys($broken);
    }

    private function path(array $item): string
    {
        $handle = strtolower($item['handle'] ?? '');

        return match ($item['type']) {
            'product' => "/products/{$handle}",
            'collection' => "/collections/{$handle}",
            'page' => "/pages/{$handle}",
            'article' => '/blogs/'.strtolower($item['blog_handle'] ?? 'news')."/{$handle}",
            default => '',
        };
    }

    private function issue(string $code, mixed $detail = null): array
    {
        $issue = ['code' => $code, 'severity' => self::ISSUES[$code]['severity']];
        if ($detail !== null) {
            $issue['detail'] = $detail;
        }

        return $issue;
    }

    private function score(array $issues): int
    {
        $penalty = array_sum(array_map(fn ($issue) => self::ISSUES[$issue['code']]['penalty'], $issues));

        return max(0, 100 - $penalty);
    }

    private function wordCount(string $html): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)))));

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }

    private function key(?string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $text)));
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

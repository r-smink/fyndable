<?php

namespace App\Services;

use App\Models\Shop;

/**
 * CollectionLanding (2.5)
 *
 * Turns collection pages into SEO landing pages: keyword suggestions with
 * search volume, an AI intro (above the product grid) and an FAQ (below
 * it) in the brand voice. Stored as the JSON metafield
 * fyndable.collection_landing and rendered by the theme app blocks
 * "Fyndable collection intro" and "Fyndable collection FAQ"; the FAQ is
 * also saved as FAQPage schema (fyndable.faq_schema, output by the app embed).
 */
class CollectionLanding
{
    /** Tags kept in the intro HTML; all attributes are dropped except a[href]. */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><ul><ol><li><h2><h3><a>';

    public const MAX_FAQ = 8;

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private ShopifyAdminWriter $writer,
        private SaasProxyClient $saas,
        private BrandVoice $voice
    ) {}

    /**
     * Keyword ideas for the collection with their monthly search volume,
     * highest volume first.
     *
     * @return array{keywords: array<int, array{keyword:string, volume:?int}>}|array{error:string}
     */
    public function keywords(Shop $shop, string $collectionGid): array
    {
        $collection = $this->fetcher->getCollectionForLanding($shop, $collectionGid);
        if (isset($collection['error'])) {
            return $collection;
        }

        $text = $this->generate($shop, 'You are an e-commerce SEO specialist. Respond only with JSON.',
            "Suggest 8 search queries shoppers use to find a collection like this. Mix short head terms and specific long-tail queries, in the language of the store.\n\n"
            .$this->context($collection)."\n"
            .'Respond as JSON: {"keywords": ["...", "..."]}', 300, 'collection_keywords');
        $ideas = $text === null ? null : ($this->json($text)['keywords'] ?? null);
        if (! is_array($ideas)) {
            return ['error' => 'ai_failed'];
        }
        $ideas = array_values(array_unique(array_filter(array_map(fn ($k) => mb_strtolower(trim((string) $k)), $ideas))));
        $ideas = array_slice($ideas, 0, 10);

        $volumes = $this->volumes($shop, $ideas);
        $keywords = array_map(fn ($k) => ['keyword' => $k, 'volume' => $volumes[$k] ?? null], $ideas);
        usort($keywords, fn ($a, $b) => ($b['volume'] ?? -1) <=> ($a['volume'] ?? -1));

        return ['keywords' => $keywords];
    }

    /**
     * AI intro + FAQ for the chosen keywords (not saved).
     *
     * @return array{intro_html:string, faq:array<int,array{question:string,answer:string}>}|array{error:string}
     */
    public function draft(Shop $shop, string $collectionGid, array $keywords): array
    {
        $collection = $this->fetcher->getCollectionForLanding($shop, $collectionGid);
        if (isset($collection['error'])) {
            return $collection;
        }
        $keywords = array_slice(array_values(array_filter(array_map(fn ($k) => trim((string) $k), $keywords))), 0, 5);
        $primary = $keywords[0] ?? $collection['title'];

        $text = $this->generate($shop, 'You are an e-commerce copywriter and SEO specialist. Respond only with JSON.',
            "Write landing page content for this collection page.\n\n"
            .$this->context($collection)."\n"
            .'Primary keyword: '.$primary."\n"
            .'Secondary keywords: '.implode(', ', array_slice($keywords, 1))."\n\n"
            ."1. intro_html: 80 to 150 words shown above the products. Helpful, specific to these products, uses the primary keyword naturally once or twice. HTML with <p> and at most one <ul>. No headings, no links, no prices or claims you cannot verify.\n"
            ."2. faq: 4 to 6 questions shoppers ask before buying from this collection, each answered in 1 to 3 sentences, using the secondary keywords where natural.\n"
            .'Respond as JSON: {"intro_html": "...", "faq": [{"question": "...", "answer": "..."}]}', 1500, 'collection_landing');
        $data = $text === null ? null : $this->json($text);
        if (! is_array($data) || empty($data['intro_html'])) {
            return ['error' => 'ai_failed'];
        }

        return $this->clean($data + ['keywords' => $keywords]);
    }

    /**
     * Current landing content of a collection.
     */
    public function current(Shop $shop, string $collectionGid): array
    {
        $collection = $this->fetcher->getCollectionForLanding($shop, $collectionGid);
        if (isset($collection['error'])) {
            return $collection;
        }
        $saved = json_decode((string) ($collection['landing']['value'] ?? ''), true);

        return [
            'title' => (string) $collection['title'],
            'handle' => (string) $collection['handle'],
            'landing' => is_array($saved) ? $this->clean($saved) : null,
        ];
    }

    public function save(Shop $shop, string $collectionGid, array $data): ?string
    {
        $landing = $this->clean($data);
        $metafields = [
            $this->writer->metafieldInput('fyndable', 'collection_landing', json_encode($landing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'json'),
        ];
        if ($landing['faq'] !== []) {
            $metafields[] = $this->writer->metafieldInput('fyndable', 'faq_schema', json_encode($this->faqSchema($landing['faq']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'json');
        }

        return $this->writer->setMetafields($shop, 'Collection', $collectionGid, $metafields);
    }

    /**
     * @return array{intro_html:string, faq:array, keywords:array}
     */
    public function clean(array $data): array
    {
        $faq = [];
        foreach (is_array($data['faq'] ?? null) ? $data['faq'] : [] as $item) {
            $question = trim(strip_tags((string) ($item['question'] ?? '')));
            $answer = trim(strip_tags((string) ($item['answer'] ?? '')));
            if ($question !== '' && $answer !== '') {
                $faq[] = ['question' => mb_substr($question, 0, 255), 'answer' => mb_substr($answer, 0, 1000)];
            }
        }

        return [
            'intro_html' => $this->sanitize((string) ($data['intro_html'] ?? '')),
            'faq' => array_slice($faq, 0, self::MAX_FAQ),
            'keywords' => array_slice(array_values(array_filter(array_map(fn ($k) => mb_substr(trim((string) $k), 0, 100), (array) ($data['keywords'] ?? [])))), 0, 5),
        ];
    }

    /**
     * Allow-listed HTML: no attributes except safe a[href], no scripts or styles.
     */
    public function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object)[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, self::ALLOWED_TAGS);
        $html = preg_replace_callback('#<(a)\b([^>]*)>#i', function ($m) {
            if (preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $m[2], $href) && preg_match('#^(https?://|/)#i', $href[1]) && ! str_starts_with($href[1], '//')) {
                return '<a href="'.htmlspecialchars($href[1], ENT_QUOTES).'">';
            }

            return '<a>';
        }, $html);
        $html = preg_replace('#<(?!a\b)([a-z0-9]+)\b[^>]*>#i', '<$1>', $html);

        return mb_substr(trim($html), 0, 5000);
    }

    private function faqSchema(array $faq): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $faq),
        ];
    }

    private function context(array $collection): string
    {
        $products = array_map(fn ($p) => trim(($p['title'] ?? '').(! empty($p['productType']) ? ' ('.$p['productType'].')' : '')), $collection['products']['nodes'] ?? []);
        $description = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($collection['descriptionHtml'] ?? '')))), 0, 1000);

        return 'Collection: '.$collection['title']."\n"
            .($description !== '' ? "Current description: {$description}\n" : '')
            .'Products: '.implode('; ', array_slice($products, 0, 20));
    }

    /**
     * Monthly search volume per keyword (SaaS keyword data, failure tolerant).
     *
     * @return array<string,int>
     */
    private function volumes(Shop $shop, array $keywords): array
    {
        if ($keywords === []) {
            return [];
        }
        $language = app(BrandVoice::class)->languageCode($shop);
        $result = $this->saas->keywordData((string) $shop->license_key, (string) $shop->tenant_key, $keywords, null, $language);
        if (isset($result['error'])) {
            return [];
        }

        $volumes = [];
        $walk = function ($node) use (&$walk, &$volumes) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['keyword']) && is_string($node['keyword'])) {
                $volume = $node['search_volume'] ?? $node['ai_search_volume'] ?? $node['volume'] ?? null;
                if (is_numeric($volume)) {
                    $volumes[mb_strtolower($node['keyword'])] = (int) $volume;
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($result['data'] ?? $result);

        return $volumes;
    }

    private function generate(Shop $shop, string $system, string $prompt, int $maxTokens, string $useCase): ?string
    {
        $result = $this->saas->aiGenerate((string) $shop->license_key, (string) $shop->tenant_key, $this->voice->apply($shop, [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt],
        ]), 'openai/gpt-4o-mini', $maxTokens, 0.6, $useCase);

        return isset($result['error']) || empty($result['text']) ? null : (string) $result['text'];
    }

    private function json(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $data = json_decode($text, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        return is_array($data) ? $data : null;
    }
}

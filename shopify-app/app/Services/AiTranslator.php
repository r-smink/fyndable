<?php

namespace App\Services;

use App\Models\Shop;

/**
 * AiTranslator (2.3)
 *
 * Fills translation gaps for Shopify Markets with AI: titles, descriptions,
 * SEO titles/descriptions and image alt text per language, written as
 * search-optimized copy for that language in the brand voice (not word for
 * word), and registered with translationsRegister.
 */
class AiTranslator
{
    /** type => [Shopify TranslatableResourceType, keys Fyndable translates] */
    public const TYPES = [
        'product' => ['PRODUCT', ['title', 'body_html', 'meta_title', 'meta_description']],
        'collection' => ['COLLECTION', ['title', 'body_html', 'meta_title', 'meta_description']],
        'page' => ['ONLINE_STORE_PAGE', ['title', 'body_html', 'meta_title', 'meta_description']],
        'article' => ['ONLINE_STORE_ARTICLE', ['title', 'body_html', 'summary_html', 'meta_title', 'meta_description']],
        'image' => ['MEDIA_IMAGE', ['alt']],
    ];

    private const MAX_LENGTH = ['meta_title' => 60, 'meta_description' => 155, 'alt' => 125, 'title' => 255];

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private ShopifyAdminWriter $writer,
        private SaasProxyClient $saas,
        private BrandVoice $voice
    ) {}

    /**
     * Primary language and the other languages of the store.
     *
     * @return array{primary: ?array, targets: array<int, array>}|array{error: string}
     */
    public function locales(Shop $shop): array
    {
        $locales = $this->fetcher->getShopLocales($shop);
        if (isset($locales['error'])) {
            return $locales;
        }

        $primary = null;
        $targets = [];
        foreach ($locales as $locale) {
            $entry = ['locale' => (string) $locale['locale'], 'name' => (string) ($locale['name'] ?? $locale['locale']), 'published' => (bool) ($locale['published'] ?? false)];
            if (! empty($locale['primary'])) {
                $primary = $entry;
            } else {
                $targets[] = $entry;
            }
        }

        return ['primary' => $primary, 'targets' => $targets];
    }

    /**
     * Resources with missing or outdated translations for one locale.
     *
     * @return array<int, array{resource_id:string, title:string, missing:array, outdated:array}>|array{error:string}
     */
    public function gaps(Shop $shop, string $type, string $locale, int $max = 250): array
    {
        [$resourceType, $keys] = self::TYPES[$type];
        $nodes = $this->fetcher->getTranslatableResources($shop, $resourceType, $locale, $max);
        if (isset($nodes['error'])) {
            return $nodes;
        }

        $gaps = [];
        foreach ($nodes as $node) {
            $status = $this->status($node, $keys);
            if ($status['missing'] !== [] || $status['outdated'] !== []) {
                $gaps[] = ['resource_id' => (string) $node['resourceId'], 'title' => $status['title']] + $status;
            }
        }

        return $gaps;
    }

    /**
     * AI translation of one resource (only missing or outdated keys, unless
     * $all). Returns entries with the source text, the translation and the
     * digest needed to register it.
     *
     * @return array{entries: array<int, array{key:string, source:string, value:string, digest:string}>}|array{error:string}
     */
    public function translate(Shop $shop, string $type, string $resourceId, string $locale, string $languageName, bool $all = false): array
    {
        $node = $this->fetcher->getTranslatableResource($shop, $resourceId, $locale);
        if (isset($node['error'])) {
            return $node;
        }

        $status = $this->status($node, self::TYPES[$type][1]);
        $keys = $all ? $status['translatable'] : array_merge($status['missing'], $status['outdated']);
        if ($keys === []) {
            return ['entries' => []];
        }

        $source = [];
        $digests = [];
        foreach ($node['translatableContent'] as $content) {
            if (in_array($content['key'], $keys, true)) {
                $source[$content['key']] = (string) $content['value'];
                $digests[$content['key']] = (string) $content['digest'];
            }
        }

        $prompt = "Translate this online store content into {$languageName} (locale {$locale}) for shoppers in that market.\n"
            ."- Write natural, fluent copy that is optimized for search in {$languageName}: use the words people in that market search for, not a word-for-word translation.\n"
            ."- Keep all HTML tags and attributes exactly as they are; only translate the visible text.\n"
            ."- Keep brand names, product names, model numbers and units unless the market uses a different unit.\n"
            ."- meta_title: max 60 characters. meta_description: max 155 characters. alt: max 125 characters.\n"
            ."Respond only with a JSON object with exactly the same keys.\n\n"
            .json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $result = $this->saas->aiGenerate(
            (string) $shop->license_key,
            (string) $shop->tenant_key,
            $this->voice->apply($shop, [
                ['role' => 'system', 'content' => 'You are a professional e-commerce translator and SEO copywriter. Respond only with JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ], true, false),
            'openai/gpt-4o-mini',
            3000,
            0.3,
            'translation'
        );
        if (isset($result['error']) || empty($result['text'])) {
            return ['error' => (string) ($result['error'] ?? 'ai_failed')];
        }

        $translated = $this->parse((string) $result['text']);
        if ($translated === null) {
            return ['error' => 'ai_parse_failed'];
        }

        $entries = [];
        foreach ($source as $key => $text) {
            $value = trim((string) ($translated[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $entries[] = ['key' => $key, 'source' => $text, 'value' => $this->cut($key, $value), 'digest' => $digests[$key]];
        }

        return ['entries' => $entries];
    }

    /**
     * @param  array<int, array{key:string, value:string, digest:string}>  $entries
     */
    public function register(Shop $shop, string $resourceId, string $locale, array $entries): ?string
    {
        $translations = [];
        foreach ($entries as $entry) {
            $value = trim((string) ($entry['value'] ?? ''));
            if ($value === '' || empty($entry['key']) || empty($entry['digest'])) {
                continue;
            }
            $translations[] = [
                'locale' => $locale,
                'key' => (string) $entry['key'],
                'value' => $this->cut((string) $entry['key'], $value),
                'translatableContentDigest' => (string) $entry['digest'],
            ];
        }

        return $translations === [] ? 'nothing_to_save' : $this->writer->registerTranslations($shop, $resourceId, $translations);
    }

    /**
     * @return array{title:string, translatable:array, missing:array, outdated:array}
     */
    private function status(array $node, array $keys): array
    {
        $content = [];
        foreach ($node['translatableContent'] ?? [] as $item) {
            if (in_array($item['key'], $keys, true) && trim(strip_tags((string) $item['value'])) !== '') {
                $content[$item['key']] = (string) $item['value'];
            }
        }
        $translations = [];
        foreach ($node['translations'] ?? [] as $item) {
            if (trim((string) ($item['value'] ?? '')) !== '') {
                $translations[$item['key']] = (bool) ($item['outdated'] ?? false);
            }
        }

        $missing = [];
        $outdated = [];
        foreach (array_keys($content) as $key) {
            if (! array_key_exists($key, $translations)) {
                $missing[] = $key;
            } elseif ($translations[$key]) {
                $outdated[] = $key;
            }
        }

        $title = $content['title'] ?? $content['alt'] ?? (string) $node['resourceId'];

        return ['title' => mb_substr(strip_tags($title), 0, 120), 'translatable' => array_keys($content), 'missing' => $missing, 'outdated' => $outdated];
    }

    private function parse(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $data = json_decode($text, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        return is_array($data) ? $data : null;
    }

    private function cut(string $key, string $value): string
    {
        $max = self::MAX_LENGTH[$key] ?? null;
        if ($max === null || mb_strlen($value) <= $max) {
            return $value;
        }
        $cut = mb_substr($value, 0, $max + 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : mb_substr($value, 0, $max), ' ,.;:-|');
    }
}

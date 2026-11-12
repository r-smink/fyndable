<?php

namespace App\Services;

use App\Models\Shop;

/**
 * BulkOptimizer (1.3)
 *
 * Bulk SEO for products, collections, pages and blog articles:
 *
 * - select(): the items to process, optionally only those with the matching
 *   SEO audit issue (missing or badly sized meta, missing alt text, thin
 *   content), so good content is not overwritten.
 * - Template mode (meta title / meta description) renders placeholders
 *   like {{title}} | {{shop}} without AI calls, for large catalogs.
 * - AI mode generates per item through the SaaS (BrandVoice applied).
 * - process(): generate and save one item (used by BulkOptimizeJob).
 */
class BulkOptimizer
{
    public const TYPES = ['product', 'collection', 'page', 'article'];

    /** Actions per content type. */
    public const ACTIONS = [
        'product' => ['meta_title', 'meta', 'titles', 'alt_text', 'descriptions', 'ai_attributes'],
        'collection' => ['meta_title', 'meta', 'descriptions'],
        'page' => ['meta_title', 'meta'],
        'article' => ['meta_title', 'meta'],
    ];

    /** Actions that can run from a template instead of AI. */
    public const TEMPLATE_ACTIONS = ['meta_title', 'meta'];

    public const PLACEHOLDERS = ['title', 'shop', 'vendor', 'type', 'price', 'blog', 'excerpt'];

    /** SeoAuditor issues an action fixes ("only items that need it"). */
    private const ISSUES = [
        'meta_title' => ['meta_title_missing', 'meta_title_long', 'meta_title_short'],
        'meta' => ['meta_desc_missing', 'meta_desc_long', 'meta_desc_short'],
        'alt_text' => ['missing_alt'],
        'descriptions' => ['thin_content'],
    ];

    private const MAX = ['meta_title' => 60, 'meta' => 155];

    private const LABELS = ['product' => 'product', 'collection' => 'product collection', 'page' => 'page', 'article' => 'blog article'];

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private ShopifyAdminWriter $writer,
        private SaasProxyClient $saas,
        private SeoAuditor $auditor,
        private BrandVoice $voice
    ) {}

    public static function validAction(string $type, string $action): bool
    {
        return in_array($action, self::ACTIONS[$type] ?? [], true);
    }

    /**
     * Items to process, as compact snapshots (stored in the queued job).
     *
     * @return array<int,array>|array{error:string}
     */
    public function select(Shop $shop, string $type, string $action, bool $onlyNeeded, int $limit): array
    {
        $nodes = $this->fetcher->getAuditItems($shop, $type, $onlyNeeded ? 2500 : $limit);
        if (isset($nodes['error'])) {
            return $nodes;
        }

        $items = [];
        foreach ($nodes as $node) {
            $normalized = $this->auditor->normalize($type, $node, $shop->shop_domain);
            if ($onlyNeeded && $action === 'ai_attributes' && ! empty($node['aiAttributes']['value'])) {
                continue;
            }
            if ($onlyNeeded && isset(self::ISSUES[$action])) {
                $codes = array_column($this->auditor->scoreItem($normalized)['issues'], 'code');
                if (array_intersect($codes, self::ISSUES[$action]) === []) {
                    continue;
                }
            }
            $items[] = $this->snapshot($type, $node, $normalized);
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * Render a template for one item: placeholders replaced, separators
     * around empty values removed, cut at a word boundary.
     */
    public function render(string $template, array $item, string $shopName, string $action): string
    {
        $values = [
            'title' => $item['title'] ?? '',
            'shop' => $shopName,
            'vendor' => $item['vendor'] ?? '',
            'type' => $item['product_type'] ?? '',
            'price' => $item['price'] ?? '',
            'blog' => $item['blog'] ?? '',
            'excerpt' => $item['excerpt'] ?? '',
        ];
        $text = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', fn ($m) => $values[strtolower($m[1])] ?? '', $template);

        // "Title |  | Shop" -> "Title | Shop", no leading/trailing separators.
        $sep = '[|\x{00B7}:,\-\/]';
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = preg_replace('/(\s*'.$sep.')(\s*'.$sep.')+/u', '$1', $text);
        $text = trim(preg_replace('/^\s*'.$sep.'\s*|\s*'.$sep.'\s*$/u', '', $text));

        return $this->cut($text, self::MAX[$action] ?? 160);
    }

    /**
     * Generate and save one item.
     *
     * @return string|null error, null on success
     */
    public function process(Shop $shop, array $item, string $action, string $mode, ?string $template, string $shopName): ?string
    {
        $type = $item['type'];
        if ($mode === 'template') {
            $value = $this->render((string) $template, $item, $shopName, $action);

            return $value === '' ? 'empty_result' : $this->saveMeta($shop, $item, $action, $value);
        }

        return match ($action) {
            'meta_title', 'meta' => $this->aiMeta($shop, $item, $action),
            'titles' => $this->aiProductTitle($shop, $item),
            'alt_text' => $this->aiAltText($shop, $item),
            'descriptions' => $this->aiDescription($shop, $item),
            'ai_attributes' => $this->aiAttributes($shop, $item),
            default => 'invalid_action',
        };
    }

    private function aiMeta(Shop $shop, array $item, string $action): ?string
    {
        $label = self::LABELS[$item['type']];
        $what = $action === 'meta_title'
            ? 'an SEO meta title (max 60 characters)'
            : 'an SEO meta description (max 155 characters)';
        $prompt = "Write {$what} for this {$label}.\n\n"
            ."Title: {$item['title']}\n"
            .($item['vendor'] ? "Brand: {$item['vendor']}\n" : '')
            .'Content: '.($item['excerpt'] ?? '')."\n\n"
            .'Return only the text, without quotes.';

        $text = $this->generate($shop, 'You are an SEO expert. Write concise, compelling meta tags.', $prompt, 200, 'bulk_'.$action);
        if ($text === null) {
            return 'ai_failed';
        }

        return $this->saveMeta($shop, $item, $action, $this->cut(trim($text, " \t\n\r\"'"), self::MAX[$action]));
    }

    private function aiProductTitle(Shop $shop, array $item): ?string
    {
        $prompt = "Optimize this product title for SEO. Keep it under 70 characters, keep the brand and key attributes.\n\n"
            ."Current title: {$item['title']}\n"
            .($item['vendor'] ? "Brand: {$item['vendor']}\n" : '')
            .($item['product_type'] ? "Type: {$item['product_type']}\n" : '')
            .'Return only the new title.';

        $text = $this->generate($shop, 'You are an e-commerce SEO expert.', $prompt, 100, 'bulk_titles');
        if ($text === null) {
            return 'ai_failed';
        }

        return $this->writer->updateProduct($shop, $item['id'], ['title' => $this->cut(trim($text, " \t\n\r\"'"), 255)]);
    }

    private function aiAltText(Shop $shop, array $item): ?string
    {
        // First image without alt text (or the first image when all have one).
        $images = $item['images'] ?? [];
        $target = collect($images)->first(fn ($image) => $image['alt'] === null) ?? ($images[0] ?? null);
        if (! $target || empty($target['url'])) {
            return 'no_image';
        }

        $messages = [
            ['role' => 'system', 'content' => 'You are an expert at writing SEO alt text for product images. Max 125 characters.'],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => "Write alt text for this image of: {$item['title']}"],
                ['type' => 'image_url', 'image_url' => ['url' => $target['url']]],
            ]],
        ];
        $result = $this->saas->aiGenerate($shop->license_key, $shop->tenant_key, $this->voice->apply($shop, $messages, false), 'openai/gpt-4o-mini', 100, 0.3, 'bulk_alt_text');
        if (isset($result['error']) || empty($result['text'])) {
            return 'ai_failed';
        }

        return $this->writer->updateMediaAlt($shop, $target['id'], $this->cut(trim($result['text'], " \t\n\r\"'"), 125));
    }

    /**
     * Shopping attributes for AI feeds (2.1).
     */
    private function aiAttributes(Shop $shop, array $item): ?string
    {
        $attributes = app(AiAttributes::class);
        $result = $attributes->generate($shop, $item['id']);

        return isset($result['error']) ? (string) $result['error'] : $attributes->save($shop, $item['id'], $result['attributes']);
    }

    private function aiDescription(Shop $shop, array $item): ?string
    {
        $node = $this->fetcher->getNode($shop, $item['id']);
        if (isset($node['error'])) {
            return (string) $node['error'];
        }
        $current = mb_substr(trim(strip_tags((string) ($node['descriptionHtml'] ?? $node['description'] ?? ''))), 0, 3000);
        $label = self::LABELS[$item['type']];
        $prompt = "Rewrite this {$label} description to be more SEO-optimized and helpful. 150 to 400 words. Plain text paragraphs, no HTML.\n\n"
            ."Title: {$item['title']}\n"
            .($item['vendor'] ? "Brand: {$item['vendor']}\n" : '')
            .($item['product_type'] ? "Type: {$item['product_type']}\n" : '')
            ."Current description: {$current}\n\n"
            .'Return only the description.';

        $text = $this->generate($shop, 'You are an expert e-commerce copywriter.', $prompt, 1000, 'bulk_description', 0.7);
        if ($text === null) {
            return 'ai_failed';
        }

        // One paragraph per line (the prompt asks for plain paragraphs); Markdown the model adds anyway becomes HTML.
        $html = app(DescriptionFormatter::class)->toHtml(preg_replace('/\n+/', "\n\n", trim($text)));

        return $item['type'] === 'collection'
            ? $this->writer->updateCollection($shop, $item['id'], ['descriptionHtml' => $html])
            : $this->writer->updateProduct($shop, $item['id'], ['descriptionHtml' => $html]);
    }

    /**
     * Save a meta title or description the same way the Content optimizer
     * does: SEO fields for products and collections, global.title_tag /
     * global.description_tag metafields for pages and articles.
     */
    private function saveMeta(Shop $shop, array $item, string $action, string $value): ?string
    {
        $field = $action === 'meta_title' ? 'title' : 'description';

        return match ($item['type']) {
            'product' => $this->writer->updateProduct($shop, $item['id'], ['seo' => [$field => $value]]),
            'collection' => $this->writer->updateCollection($shop, $item['id'], ['seo' => [$field => $value]]),
            default => $this->writer->setMetafields($shop, ucfirst($item['type']), $item['id'], [
                $field === 'title'
                    ? $this->writer->metafieldInput('global', 'title_tag', $value, 'single_line_text_field')
                    : $this->writer->metafieldInput('global', 'description_tag', $value, 'multi_line_text_field'),
            ]),
        };
    }

    private function generate(Shop $shop, string $system, string $prompt, int $maxTokens, string $useCase, float $temperature = 0.5): ?string
    {
        $messages = [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]];
        $result = $this->saas->aiGenerate($shop->license_key, $shop->tenant_key, $this->voice->apply($shop, $messages), 'openai/gpt-4o-mini', $maxTokens, $temperature, $useCase);

        return isset($result['error']) || empty($result['text']) ? null : (string) $result['text'];
    }

    private function snapshot(string $type, array $node, array $normalized): array
    {
        $price = $node['priceRangeV2']['minVariantPrice'] ?? null;
        $images = [];
        foreach ($node['media']['nodes'] ?? [] as $media) {
            if (($media['mediaContentType'] ?? '') === 'IMAGE') {
                $alt = trim((string) ($media['alt'] ?? ''));
                $images[] = ['id' => (string) ($media['id'] ?? ''), 'url' => $media['preview']['image']['url'] ?? null, 'alt' => $alt === '' ? null : $alt];
            }
        }
        $excerpt = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $normalized['body_html'])))));

        return [
            'type' => $type,
            'id' => $normalized['id'],
            'title' => $normalized['title'],
            'seo_title' => $normalized['seo_title'],
            'seo_description' => $normalized['seo_description'],
            'vendor' => (string) ($node['vendor'] ?? ''),
            'product_type' => (string) ($node['productType'] ?? ''),
            'price' => $price ? $this->formatPrice((string) $price['amount'], (string) $price['currencyCode']) : '',
            'blog' => (string) ($node['blog']['title'] ?? ''),
            'excerpt' => $this->cut($excerpt, 300),
            'images' => array_slice($images, 0, 10),
        ];
    }

    private function formatPrice(string $amount, string $currency): string
    {
        $value = (float) $amount;
        $formatted = floor($value) == $value ? number_format($value, 0, '.', '') : number_format($value, 2, '.', '');

        return trim($formatted.' '.$currency);
    }

    private function cut(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max + 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $max), ' ,.;:-|');
    }
}

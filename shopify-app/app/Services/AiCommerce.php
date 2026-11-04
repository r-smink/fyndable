<?php

namespace App\Services;

/**
 * AiCommerce (2.1)
 *
 * Pure logic for AI-shopping readiness:
 * - group(): variant nodes (ShopifyContentFetcher::getFeedVariants) into
 *   products, skipping draft/archived and unpublished products.
 * - score(): the AI-ready score per product (what AI shopping engines need:
 *   brand, GTIN, category, rich description, images, attributes, reviews).
 * - openAiRow() / googleItem(): one feed row per variant, following the
 *   OpenAI product feed spec and Google Merchant Center attributes.
 */
class AiCommerce
{
    /** Points per check (total 100). */
    public const CHECKS = [
        'missing_gtin' => 15,
        'short_description' => 15,
        'missing_attributes' => 15,
        'missing_brand' => 10,
        'missing_category' => 10,
        'few_images' => 15,
        'missing_type' => 5,
        'missing_options' => 5,
        'no_reviews' => 5,
        'missing_meta' => 5,
    ];

    public const ATTRIBUTE_KEYS = ['material', 'color', 'features', 'use_cases', 'target_audience', 'care_instructions', 'gender', 'age_group'];

    private const GENDERS = ['male', 'female', 'unisex'];

    private const AGE_GROUPS = ['newborn', 'infant', 'toddler', 'kids', 'adult'];

    /**
     * @return array<string, array{product: array, variants: array<int, array>}> keyed by product GID
     */
    public function group(array $variants): array
    {
        $products = [];
        foreach ($variants as $variant) {
            $product = $variant['product'] ?? null;
            if (! $product || ($product['status'] ?? '') !== 'ACTIVE' || empty($product['onlineStoreUrl'])) {
                continue;
            }
            $id = $product['id'];
            $products[$id] ??= ['product' => $product, 'variants' => []];
            unset($variant['product']);
            $products[$id]['variants'][] = $variant;
        }

        return $products;
    }

    /**
     * @return array{score:int, issues:array<int,array{code:string, detail?:mixed}>}
     */
    public function score(array $product, array $variants): array
    {
        $issues = [];
        $deduct = 0;
        $add = function (string $code, float $share = 1.0, mixed $detail = null) use (&$issues, &$deduct) {
            $deduct += (int) round(self::CHECKS[$code] * $share);
            $issues[] = $detail === null ? ['code' => $code] : ['code' => $code, 'detail' => $detail];
        };

        $missingGtin = count(array_filter($variants, fn ($v) => ! $this->validGtin((string) ($v['barcode'] ?? ''))));
        if ($missingGtin > 0) {
            $add('missing_gtin', $missingGtin / max(1, count($variants)), $missingGtin);
        }

        $words = $this->wordCount((string) ($product['descriptionHtml'] ?? ''));
        if ($words < 30) {
            $add('short_description', 1.0, $words);
        } elseif ($words < 80) {
            $add('short_description', 0.5, $words);
        }

        $attributes = $this->attributes($product);
        $filled = count(array_filter(['material', 'features', 'use_cases'], fn ($k) => ! empty($attributes[$k])));
        if ($filled < 3) {
            $add('missing_attributes', (3 - $filled) / 3);
        }

        if (trim((string) ($product['vendor'] ?? '')) === '') {
            $add('missing_brand');
        }
        if (empty($product['category']['fullName'])) {
            $add('missing_category');
        }

        $images = count($this->images($product));
        if ($images === 0) {
            $add('few_images', 1.0, 0);
        } elseif ($images < 3) {
            $add('few_images', 1 / 3, $images);
        }

        if (trim((string) ($product['productType'] ?? '')) === '') {
            $add('missing_type');
        }
        if (count($variants) > 1 && $this->variantOptions($variants[0]) === []) {
            $add('missing_options');
        }
        if ($this->rating($product) === null) {
            $add('no_reviews');
        }
        if (trim((string) ($product['seo']['description'] ?? '')) === '') {
            $add('missing_meta');
        }

        return ['score' => max(0, 100 - $deduct), 'issues' => $issues];
    }

    /**
     * One OpenAI product feed row (JSONL) per variant.
     */
    public function openAiRow(array $product, array $variant, array $shop): array
    {
        $common = $this->common($product, $variant, $shop);
        $row = [
            'item_id' => $common['id'],
            'title' => $common['title'],
            'description' => $common['description'],
            'url' => $common['url'],
            'brand' => $common['brand'],
            'seller_name' => $shop['name'],
            'seller_url' => $shop['url'],
            'image_url' => $common['image'],
            'additional_image_urls' => $common['additional_images'],
            'availability' => $common['in_stock'] ? 'in_stock' : 'out_of_stock',
            'price' => $common['price'],
            'sale_price' => $common['sale_price'],
            'condition' => 'new',
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'group_id' => $common['group_id'],
            'listing_has_variations' => $common['has_variants'],
            'variant_dict' => $common['has_variants'] ? ($common['options'] ?: null) : null,
            'gtin' => $common['gtin'],
            'product_category' => $common['category'],
            'material' => $common['material'],
            'color' => $common['color'],
            'size' => $common['size'],
            'gender' => $common['gender'],
            'age_group' => $common['age_group'],
            'star_rating' => $common['rating'] !== null ? number_format($common['rating']['value'], 2, '.', '') : null,
            'review_count' => $common['rating']['count'] ?? null,
        ];

        return array_filter($row, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * One Google Merchant Center item (g: attributes) per variant.
     */
    public function googleItem(array $product, array $variant, array $shop): array
    {
        $common = $this->common($product, $variant, $shop);
        $item = [
            'id' => $common['id'],
            'title' => mb_substr($common['title'], 0, 150),
            'description' => $common['description'],
            'link' => $common['url'],
            'image_link' => $common['image'],
            'additional_image_link' => $common['additional_images'],
            'availability' => $common['in_stock'] ? 'in_stock' : 'out_of_stock',
            'price' => $common['price'],
            'sale_price' => $common['sale_price'],
            'brand' => $common['brand'],
            'gtin' => $common['gtin'],
            'identifier_exists' => $common['gtin'] === null ? 'no' : null,
            'condition' => 'new',
            'item_group_id' => $common['has_variants'] ? $common['group_id'] : null,
            'product_type' => $common['category'] ?: ($product['productType'] ?? null),
            'color' => $common['color'],
            'size' => $common['size'],
            'material' => $common['material'],
            'gender' => $common['gender'],
            'age_group' => $common['age_group'],
            'product_highlight' => array_slice($common['features'], 0, 10),
        ];

        return array_filter($item, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    public function validGtin(string $value): bool
    {
        $digits = preg_replace('/\s+/', '', $value);
        if (! preg_match('/^\d{8}$|^\d{12,14}$/', $digits)) {
            return false;
        }
        $sum = 0;
        $body = substr($digits, 0, -1);
        $len = strlen($body);
        for ($i = 0; $i < $len; $i++) {
            // Weights 3,1,3,1... from the right of the body.
            $sum += (int) $body[$len - 1 - $i] * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === (int) substr($digits, -1);
    }

    /**
     * AI attributes saved by Fyndable (metafield fyndable.ai_attributes).
     */
    public function attributes(array $product): array
    {
        $raw = $product['aiAttributes']['value'] ?? null;
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? array_intersect_key($data, array_flip(self::ATTRIBUTE_KEYS)) : [];
    }

    private function common(array $product, array $variant, array $shop): array
    {
        $productId = $this->numericId($product['id']);
        $variantId = $this->numericId($variant['id']);
        $options = $this->variantOptions($variant);
        $attributes = $this->attributes($product);
        $images = $this->images($product);
        $image = $variant['image']['url'] ?? ($images[0] ?? null);
        $hasVariants = (int) ($product['totalVariants'] ?? 1) > 1;

        $price = (float) ($variant['price'] ?? 0);
        $compare = (float) ($variant['compareAtPrice'] ?? 0);
        $money = fn (float $amount) => number_format($amount, 2, '.', '').' '.$shop['currency'];

        $variantTitle = trim((string) ($variant['title'] ?? ''));
        $title = (string) $product['title'].($hasVariants && $variantTitle !== '' && $variantTitle !== 'Default Title' ? ' - '.$variantTitle : '');
        $barcode = preg_replace('/\s+/', '', (string) ($variant['barcode'] ?? ''));

        return [
            'id' => $variantId,
            'group_id' => $productId,
            'has_variants' => $hasVariants,
            'title' => mb_substr($title, 0, 150),
            'description' => mb_substr($this->plainText((string) ($product['descriptionHtml'] ?? '')) ?: (string) $product['title'], 0, 5000),
            'url' => $product['onlineStoreUrl'].($hasVariants ? '?variant='.$variantId : ''),
            'brand' => trim((string) ($product['vendor'] ?? '')) ?: $shop['name'],
            'image' => $image,
            'additional_images' => array_values(array_filter(array_slice($images, 0, 10), fn ($url) => $url !== $image)),
            'in_stock' => (bool) ($variant['availableForSale'] ?? false),
            'price' => $money($compare > $price ? $compare : $price),
            'sale_price' => $compare > $price && $price > 0 ? $money($price) : null,
            'gtin' => $this->validGtin($barcode) ? $barcode : null,
            'category' => $product['category']['fullName'] ?? null,
            'options' => $options,
            'color' => $this->option($options, ['color', 'colour', 'kleur', 'farbe', 'couleur']) ?? $this->text($attributes['color'] ?? null),
            'size' => $this->option($options, ['size', 'maat', 'grootte', 'größe', 'taille']),
            'material' => $this->text($attributes['material'] ?? null),
            'gender' => in_array($attributes['gender'] ?? null, self::GENDERS, true) ? $attributes['gender'] : null,
            'age_group' => in_array($attributes['age_group'] ?? null, self::AGE_GROUPS, true) ? $attributes['age_group'] : null,
            'features' => array_values(array_filter(array_map(fn ($f) => $this->text($f), (array) ($attributes['features'] ?? [])))),
            'rating' => $this->rating($product),
        ];
    }

    /** Variant options without Shopify's placeholder "Title". */
    private function variantOptions(array $variant): array
    {
        $options = [];
        foreach ($variant['selectedOptions'] ?? [] as $option) {
            if (($option['name'] ?? '') !== 'Title') {
                $options[mb_strtolower((string) $option['name'])] = (string) $option['value'];
            }
        }

        return $options;
    }

    private function option(array $options, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($options[$name]) && $options[$name] !== '') {
                return $options[$name];
            }
        }

        return null;
    }

    /** @return array<int,string> */
    private function images(array $product): array
    {
        return array_values(array_filter(array_map(fn ($m) => $m['image']['url'] ?? null, $product['media']['nodes'] ?? [])));
    }

    /** @return array{value: float, count: int}|null */
    private function rating(array $product): ?array
    {
        $rating = json_decode((string) ($product['rating']['value'] ?? ''), true);
        $count = (int) ($product['ratingCount']['value'] ?? 0);
        if (! is_array($rating) || ! isset($rating['value']) || $count < 1) {
            return null;
        }
        $max = (float) ($rating['scale_max'] ?? 5) ?: 5.0;

        return ['value' => round((float) $rating['value'] / $max * 5, 2), 'count' => $count];
    }

    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function wordCount(string $html): int
    {
        $text = $this->plainText($html);

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    }

    private function text(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : mb_substr($value, 0, 150);
    }

    private function numericId(string $gid): string
    {
        return (string) preg_replace('#^.*/#', '', $gid);
    }
}

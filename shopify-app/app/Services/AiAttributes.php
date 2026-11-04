<?php

namespace App\Services;

use App\Models\Shop;

/**
 * AiAttributes (2.1): structured product attributes for AI shopping
 * (material, color, key features, use cases, audience, care, gender, age
 * group), generated with AI from the product data and saved as the JSON
 * metafield fyndable.ai_attributes. Used by the product feeds, the Product
 * schema in the app embed and the AI-ready score.
 */
class AiAttributes
{
    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private ShopifyAdminWriter $writer,
        private SaasProxyClient $saas,
        private BrandVoice $voice
    ) {}

    /**
     * @return array{attributes: array}|array{error: string}
     */
    public function generate(Shop $shop, string $productGid): array
    {
        $node = $this->fetcher->getNode($shop, $productGid);
        if (isset($node['error'])) {
            return ['error' => (string) $node['error']];
        }

        $description = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($node['descriptionHtml'] ?? $node['description'] ?? '')))), 0, 3000);
        $prompt = "Extract structured shopping attributes for this product. Only use facts from the data; leave a field empty when unknown, never invent specifications.\n\n"
            .'Title: '.($node['title'] ?? '')."\n"
            .(! empty($node['vendor']) ? "Brand: {$node['vendor']}\n" : '')
            .(! empty($node['productType']) ? "Type: {$node['productType']}\n" : '')
            ."Description: {$description}\n\n"
            .'Respond as JSON: {"material": "", "color": "", "features": ["3 to 6 short key features"], "use_cases": ["2 to 5 situations or needs this product is for"], '
            .'"target_audience": "", "care_instructions": "", "gender": "male|female|unisex or empty", "age_group": "newborn|infant|toddler|kids|adult or empty"}';

        $result = $this->saas->aiGenerate(
            (string) $shop->license_key,
            (string) $shop->tenant_key,
            $this->voice->apply($shop, [
                ['role' => 'system', 'content' => 'You are a product data specialist for e-commerce feeds. Respond only with JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ]),
            'openai/gpt-4o-mini',
            600,
            0.2,
            'ai_attributes'
        );
        if (isset($result['error']) || empty($result['text'])) {
            return ['error' => (string) ($result['error'] ?? 'ai_failed')];
        }

        $data = $this->parse((string) $result['text']);

        return $data === null ? ['error' => 'ai_parse_failed'] : ['attributes' => $data];
    }

    /**
     * @return string|null error, null on success
     */
    public function save(Shop $shop, string $productGid, array $attributes): ?string
    {
        $clean = $this->normalize($attributes);

        return $this->writer->setMetafields($shop, 'Product', $productGid, [
            $this->writer->metafieldInput('fyndable', 'ai_attributes', json_encode($clean, JSON_UNESCAPED_UNICODE), 'json'),
        ]);
    }

    public function normalize(array $data): array
    {
        $text = fn ($v, $max = 150) => is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : '';
        $list = fn ($v, $n) => array_values(array_slice(array_filter(array_map(fn ($x) => $text($x, 200), is_array($v) ? $v : [])), 0, $n));
        $gender = strtolower($text($data['gender'] ?? ''));
        $age = strtolower($text($data['age_group'] ?? ''));

        return [
            'material' => $text($data['material'] ?? ''),
            'color' => $text($data['color'] ?? ''),
            'features' => $list($data['features'] ?? [], 6),
            'use_cases' => $list($data['use_cases'] ?? [], 5),
            'target_audience' => $text($data['target_audience'] ?? '', 255),
            'care_instructions' => $text($data['care_instructions'] ?? '', 500),
            'gender' => in_array($gender, ['male', 'female', 'unisex'], true) ? $gender : '',
            'age_group' => in_array($age, ['newborn', 'infant', 'toddler', 'kids', 'adult'], true) ? $age : '',
        ];
    }

    private function parse(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $data = json_decode($text, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
        }

        return is_array($data) ? $this->normalize($data) : null;
    }
}

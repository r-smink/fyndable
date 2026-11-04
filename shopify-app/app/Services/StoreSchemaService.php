<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\StoreSchemaSetting;

/**
 * StoreSchemaService (1.4)
 *
 * Store-wide structured data through the "Fyndable SEO schema" app embed:
 *
 * - The app embed renders Product, BlogPosting, BreadcrumbList and WebSite
 *   (+ SearchAction) straight from Liquid, so they are always current and
 *   cost no API calls.
 * - Organization and LocalBusiness come from the merchant's settings; the
 *   app builds them here and publishes them, with the on/off flags, as the
 *   app-data metafield fyndable.schema_config (AppInstallation owner).
 * - Plan gating happens when publishing: features outside the plan are
 *   published as off (re-published daily by SyncStoreSchemaJob).
 * - The theme check scans the main theme for its own JSON-LD so the
 *   merchant can avoid duplicate schema (read_themes).
 */
class StoreSchemaService
{
    public const FLAGS = ['organization', 'website', 'breadcrumbs', 'product', 'article', 'local_business'];

    public const BUSINESS_TYPES = ['Store', 'ClothingStore', 'ElectronicsStore', 'FurnitureStore', 'GardenStore', 'HomeGoodsStore', 'JewelryStore', 'BookStore', 'SportingGoodsStore', 'ToyStore', 'PetStore', 'HealthAndBeautyBusiness', 'FoodEstablishment', 'LocalBusiness'];

    /** Theme files that can contain hard-coded structured data. */
    public const THEME_PATTERNS = ['layout/*.liquid', 'sections/*.liquid', 'snippets/*.liquid', 'templates/*.liquid', 'blocks/*.liquid'];

    /** schema.org type -> setting that would duplicate it. */
    private const TYPE_FLAGS = [
        'Product' => 'product', 'ProductGroup' => 'product',
        'Organization' => 'organization', 'Corporation' => 'organization', 'OnlineStore' => 'organization',
        'WebSite' => 'website',
        'BreadcrumbList' => 'breadcrumbs',
        'Article' => 'article', 'BlogPosting' => 'article', 'NewsArticle' => 'article',
        'LocalBusiness' => 'local_business', 'Store' => 'local_business',
    ];

    public function __construct(
        private ShopifyAdminWriter $writer,
        private ShopifyContentFetcher $fetcher,
        private LicenseService $license
    ) {}

    public function defaults(): array
    {
        return [
            'flags' => ['organization' => true, 'website' => true, 'breadcrumbs' => true, 'product' => true, 'article' => true, 'local_business' => false],
            'organization' => ['name' => '', 'logo' => '', 'description' => '', 'email' => '', 'phone' => '', 'same_as' => []],
            'local_business' => [
                'type' => 'Store', 'street' => '', 'city' => '', 'postal_code' => '', 'region' => '', 'country' => '',
                'phone' => '', 'latitude' => null, 'longitude' => null, 'opening_hours' => [], 'price_range' => '',
            ],
        ];
    }

    public function record(Shop $shop): ?StoreSchemaSetting
    {
        return StoreSchemaSetting::where('shop_id', $shop->id)->first();
    }

    public function settings(Shop $shop): array
    {
        $defaults = $this->defaults();
        $saved = $this->record($shop)?->settings ?? [];

        return [
            'flags' => array_merge($defaults['flags'], array_intersect_key($saved['flags'] ?? [], $defaults['flags'])),
            'organization' => array_merge($defaults['organization'], array_intersect_key($saved['organization'] ?? [], $defaults['organization'])),
            'local_business' => array_merge($defaults['local_business'], array_intersect_key($saved['local_business'] ?? [], $defaults['local_business'])),
        ];
    }

    public function save(Shop $shop, array $settings): StoreSchemaSetting
    {
        $record = StoreSchemaSetting::firstOrNew(['shop_id' => $shop->id]);
        $record->settings = $settings;
        $record->save();

        return $record;
    }

    /**
     * Which flags the plan allows (Schema = starter, LocalBusiness = professional).
     */
    public function allowed(Shop $shop): array
    {
        $schema = $this->license->canUse($shop, 'schema');
        $local = $schema && $this->license->canUse($shop, 'local_business_schema');

        return array_combine(self::FLAGS, array_map(fn ($flag) => $flag === 'local_business' ? $local : $schema, self::FLAGS));
    }

    /**
     * The JSON published for the app embed.
     *
     * @param  array{name?:string, domain?:string}  $details  Shopify shop details
     */
    public function buildConfig(array $settings, array $allowed, array $details): array
    {
        $on = fn (string $flag) => (bool) ($settings['flags'][$flag] ?? false) && ($allowed[$flag] ?? false);
        $url = rtrim((string) ($details['domain'] ?? ''), '/');
        $org = $settings['organization'];
        $name = trim((string) $org['name']) !== '' ? trim((string) $org['name']) : (string) ($details['name'] ?? '');

        $home = [];
        if ($on('organization') && $name !== '') {
            $home[] = $this->clean([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => $url.'/#organization',
                'name' => $name,
                'url' => $url.'/',
                'logo' => $org['logo'] ?: null,
                'description' => $org['description'] ?: null,
                'email' => $org['email'] ?: null,
                'telephone' => $org['phone'] ?: null,
                'sameAs' => array_values(array_filter($org['same_as'] ?? [])) ?: null,
            ]);
        }

        $local = $settings['local_business'];
        if ($on('local_business') && trim((string) $local['street']) !== '' && trim((string) $local['city']) !== '') {
            $home[] = $this->clean([
                '@context' => 'https://schema.org',
                '@type' => in_array($local['type'], self::BUSINESS_TYPES, true) ? $local['type'] : 'Store',
                '@id' => $url.'/#localbusiness',
                'name' => $name,
                'url' => $url.'/',
                'image' => $org['logo'] ?: null,
                'telephone' => ($local['phone'] ?: $org['phone']) ?: null,
                'priceRange' => $local['price_range'] ?: null,
                'address' => $this->clean([
                    '@type' => 'PostalAddress',
                    'streetAddress' => $local['street'],
                    'addressLocality' => $local['city'],
                    'postalCode' => $local['postal_code'] ?: null,
                    'addressRegion' => $local['region'] ?: null,
                    'addressCountry' => $local['country'] ?: null,
                ]),
                'geo' => is_numeric($local['latitude']) && is_numeric($local['longitude'])
                    ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $local['latitude'], 'longitude' => (float) $local['longitude']]
                    : null,
                'openingHours' => array_values(array_filter($local['opening_hours'] ?? [])) ?: null,
                'parentOrganization' => $on('organization') ? ['@id' => $url.'/#organization'] : null,
            ]);
        }

        return [
            'version' => 1,
            'website' => $on('website'),
            'breadcrumbs' => $on('breadcrumbs'),
            'product' => $on('product'),
            'article' => $on('article'),
            'organization_id' => $on('organization') && $name !== '' ? $url.'/#organization' : null,
            'home' => $home,
        ];
    }

    /**
     * Build and write fyndable.schema_config for the app embed.
     *
     * @return string|null error, null on success
     */
    public function publish(Shop $shop): ?string
    {
        $record = StoreSchemaSetting::firstOrCreate(['shop_id' => $shop->id], ['settings' => $this->defaults()]);
        $config = $this->buildConfig($this->settings($shop), $this->allowed($shop), $this->fetcher->getShopDetails($shop));

        $installation = $this->writer->currentAppInstallationId($shop);
        $error = $installation === null
            ? 'no_app_installation'
            : $this->writer->setMetafields($shop, 'AppInstallation', $installation, [
                $this->writer->metafieldInput('fyndable', 'schema_config', json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'json'),
            ]);

        $record->update($error === null
            ? ['published_at' => now(), 'publish_error' => null]
            : ['publish_error' => mb_substr($error, 0, 255)]);

        return $error;
    }

    /**
     * Find structured data the theme itself outputs and which Fyndable
     * settings would duplicate it.
     *
     * @return array{theme:string, findings:array, duplicates:array<int,string>, checked_files:int}|array{error:string}
     */
    public function themeCheck(Shop $shop): array
    {
        $result = $this->fetcher->getMainThemeFiles($shop, self::THEME_PATTERNS);
        if (isset($result['error'])) {
            return $result;
        }

        $findings = [];
        foreach ($result['files'] as $file) {
            $types = $this->schemaTypes($file['filename'], $file['content']);
            if ($types !== []) {
                $findings[] = ['file' => $file['filename'], 'types' => $types];
            }
        }

        $flags = [];
        foreach ($findings as $finding) {
            foreach ($finding['types'] as $type) {
                if (isset(self::TYPE_FLAGS[$type])) {
                    $flags[self::TYPE_FLAGS[$type]] = true;
                }
            }
        }

        $check = [
            'theme' => $result['theme'],
            'findings' => $findings,
            'duplicates' => array_keys($flags),
            'checked_files' => count($result['files']),
        ];

        StoreSchemaSetting::updateOrCreate(['shop_id' => $shop->id], ['theme_check' => $check, 'theme_checked_at' => now()] + ($this->record($shop) ? [] : ['settings' => $this->defaults()]));

        return $check;
    }

    /**
     * schema.org types a theme file outputs: JSON-LD script blocks and
     * Shopify's `structured_data` Liquid filter (Product or Article).
     *
     * @return array<int,string>
     */
    public function schemaTypes(string $filename, string $content): array
    {
        $types = [];

        if (preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $content, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/["\']@type["\']\s*:\s*["\']([A-Za-z]+)["\']/', $block, $m)) {
                    array_push($types, ...$m[1]);
                }
            }
        }

        if (preg_match_all('/(\w+)\s*\|\s*structured_data/', $content, $m)) {
            foreach ($m[1] as $object) {
                $types[] = str_contains($object, 'article') ? 'Article' : 'Product';
            }
        }

        // Nested helper types are not a duplicate risk on their own.
        $ignore = ['ListItem', 'Offer', 'AggregateOffer', 'Brand', 'PostalAddress', 'ImageObject', 'Person', 'SearchAction', 'EntryPoint', 'Rating', 'AggregateRating', 'Review', 'Thing', 'GeoCoordinates', 'ContactPoint', 'WebPage', 'PropertyValue', 'MonetaryAmount', 'OfferShippingDetails', 'QuantitativeValue'];

        return array_values(array_unique(array_diff($types, $ignore)));
    }

    private function clean(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}

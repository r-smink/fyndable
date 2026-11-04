<?php

namespace App\Services;

use App\Models\Shop;

/**
 * MarketsAuditService
 *
 * Audits a Shopify shop's multi-market setup for SEO issues:
 * hreflang consistency, locale coverage, translation gaps, etc.
 */
class MarketsAuditService
{
    public function __construct(private ShopifyContentFetcher $fetcher) {}

    /**
     * Run a full markets SEO audit.
     *
     * @return array{score: int, checks: array, recommendations: array}
     */
    public function audit(Shop $shop): array
    {
        $markets = $this->fetcher->getMarkets($shop);
        if (isset($markets['error'])) {
            return $markets;
        }

        if (empty($markets)) {
            return [
                'score' => 100,
                'markets_count' => 0,
                'checks' => [],
                'recommendations' => ['No markets configured — single-market store.'],
            ];
        }

        $checks = [];
        $issues = [];
        $totalChecks = 0;
        $passed = 0;

        // Check 1: Primary market exists
        $totalChecks++;
        $hasPrimary = false;
        foreach ($markets as $market) {
            if ($market['primary'] ?? false) {
                $hasPrimary = true;
                break;
            }
        }
        $checks[] = ['name' => 'primary_market_exists', 'passed' => $hasPrimary, 'detail' => $hasPrimary ? 'Primary market is configured' : 'No primary market found'];
        if ($hasPrimary) {
            $passed++;
        } else {
            $issues[] = 'Configure a primary market for your store.';
        }

        // Check 2: All enabled markets have web presences
        foreach ($markets as $market) {
            if (! ($market['enabled'] ?? false)) {
                continue;
            }

            $totalChecks++;
            $webPresences = $market['webPresences'] ?? [];
            $hasPresence = ! empty($webPresences);
            $checks[] = [
                'name' => 'web_presence',
                'market' => $market['name'] ?? $market['id'],
                'passed' => $hasPresence,
                'detail' => $hasPresence
                    ? 'Web presence configured with '.count($webPresences).' domain(s)'
                    : 'No web presence — this market has no storefront URL',
            ];
            if ($hasPresence) {
                $passed++;
            } else {
                $issues[] = "Market \"{$market['name']}\" has no web presence configured.";
            }
        }

        // Check 3: Hreflang coverage (alternate locales)
        $allLocales = [];
        foreach ($markets as $market) {
            foreach ($market['webPresences'] ?? [] as $wp) {
                $defaultLocale = $wp['defaultLocale'] ?? null;
                $alternates = $wp['alternateLocales'] ?? [];
                if ($defaultLocale) {
                    $allLocales[] = $defaultLocale;
                }
                foreach ($alternates as $locale) {
                    $allLocales[] = $locale;
                }
            }
        }
        $uniqueLocales = array_unique($allLocales);

        $totalChecks++;
        $multiLang = count($uniqueLocales) > 1;
        $checks[] = [
            'name' => 'hreflang_locales',
            'passed' => true, // Informational
            'detail' => $multiLang
                ? count($uniqueLocales).' locales detected: '.implode(', ', $uniqueLocales)
                : 'Single locale store — hreflang not needed',
            'locales' => $uniqueLocales,
        ];
        $passed++;

        // Check 4: Each market has at least one region
        foreach ($markets as $market) {
            if (! ($market['enabled'] ?? false)) {
                continue;
            }

            $totalChecks++;
            $regions = $market['regions'] ?? [];
            $hasRegions = ! empty($regions);
            $checks[] = [
                'name' => 'market_regions',
                'market' => $market['name'] ?? $market['id'],
                'passed' => $hasRegions,
                'detail' => $hasRegions
                    ? count($regions).' region(s): '.implode(', ', array_map(fn ($r) => $r['name'] ?? $r['code'] ?? '', $regions))
                    : 'No regions assigned',
            ];
            if ($hasRegions) {
                $passed++;
            } else {
                $issues[] = "Market \"{$market['name']}\" has no regions assigned.";
            }
        }

        // Check 5: Translation coverage (sample 5 products)
        if ($multiLang) {
            $products = $this->fetcher->getProducts($shop, 5);
            foreach ($uniqueLocales as $locale) {
                $totalChecks++;
                $translated = 0;
                foreach ($products as $product) {
                    $gid = $product['id'] ?? '';
                    if ($gid === '') {
                        continue;
                    }
                    $translations = $this->fetcher->getTranslations($shop, $gid, $locale);
                    if (! empty($translations) && ! isset($translations['error'])) {
                        $translated++;
                    }
                }

                $ratio = count($products) > 0 ? round(($translated / count($products)) * 100) : 0;
                $ok = $ratio >= 80;
                $checks[] = [
                    'name' => 'translation_coverage',
                    'locale' => $locale,
                    'passed' => $ok,
                    'detail' => "{$translated}/".count($products)." sample products translated ({$ratio}%)",
                ];
                if ($ok) {
                    $passed++;
                } else {
                    $issues[] = "Low translation coverage for locale \"{$locale}\": only {$ratio}% of sampled products are translated.";
                }
            }
        }

        $score = $totalChecks > 0 ? (int) round(($passed / $totalChecks) * 100) : 100;

        return [
            'score' => $score,
            'markets_count' => count($markets),
            'locales' => $uniqueLocales,
            'checks' => $checks,
            'recommendations' => $issues,
        ];
    }

    /**
     * Get translation status per market for content.
     */
    public function contentStatus(Shop $shop, string $marketId): array
    {
        $markets = $this->fetcher->getMarkets($shop);
        if (isset($markets['error'])) {
            return $markets;
        }

        $targetMarket = null;
        foreach ($markets as $market) {
            $mId = $market['id'] ?? '';
            $numericId = preg_replace('/^gid:\/\/shopify\/Market\//', '', $mId);
            if ($mId === $marketId || $numericId === $marketId) {
                $targetMarket = $market;
                break;
            }
        }

        if (! $targetMarket) {
            return ['error' => 'market_not_found'];
        }

        $locales = [];
        foreach ($targetMarket['webPresences'] ?? [] as $wp) {
            if (! empty($wp['defaultLocale'])) {
                $locales[] = $wp['defaultLocale'];
            }
            foreach ($wp['alternateLocales'] ?? [] as $locale) {
                $locales[] = $locale;
            }
        }
        $locales = array_unique($locales);

        // Sample content
        $products = $this->fetcher->getProducts($shop, 10);
        $pages = $this->fetcher->getPages($shop, 5);

        $status = [];
        foreach ($locales as $locale) {
            $translatedProducts = 0;
            foreach ($products as $product) {
                $gid = $product['id'] ?? '';
                if ($gid === '') {
                    continue;
                }
                $translations = $this->fetcher->getTranslations($shop, $gid, $locale);
                if (! empty($translations) && ! isset($translations['error'])) {
                    $translatedProducts++;
                }
            }

            $translatedPages = 0;
            foreach ($pages as $page) {
                $gid = $page['id'] ?? '';
                if ($gid === '') {
                    continue;
                }
                $translations = $this->fetcher->getTranslations($shop, $gid, $locale);
                if (! empty($translations) && ! isset($translations['error'])) {
                    $translatedPages++;
                }
            }

            $status[$locale] = [
                'products' => ['translated' => $translatedProducts, 'total' => count($products)],
                'pages' => ['translated' => $translatedPages, 'total' => count($pages)],
            ];
        }

        return [
            'market' => $targetMarket['name'] ?? $marketId,
            'locales' => $locales,
            'content_status' => $status,
        ];
    }
}

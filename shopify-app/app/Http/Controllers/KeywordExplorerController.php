<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use Illuminate\Http\Request;

/**
 * KeywordExplorerController
 *
 * Keyword research: expand a seed keyword into related keywords with search
 * volume, CPC and difficulty data. Supports clustering and trend analysis.
 */
class KeywordExplorerController extends Controller
{
    public function __construct(
        private SaasProxyClient $saas,
        private LicenseService $license
    ) {}

    /**
     * Expand a seed keyword into related keywords.
     *
     * POST /api/keywords/explore
     * Body: { seed: "seo tool", country?: "us", language?: "en" }
     */
    public function explore(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $seed = trim((string) $request->input('seed', ''));

        if ($seed === '') {
            return ['error' => 'seed_required'];
        }

        $country = trim((string) $request->input('country', 'us')) ?: 'us';
        $language = trim((string) $request->input('language', 'en')) ?: 'en';

        return $this->saas->keywordExpand(
            $shop->license_key,
            $shop->tenant_key,
            $seed,
            $country,
            $language
        );
    }

    /**
     * Bulk keyword volume lookup.
     *
     * POST /api/keywords/volume
     * Body: { keywords: [...], location_code?, language_code? }
     */
    public function volume(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $keywords = array_values(array_filter(array_map(
            fn ($k) => trim((string) $k),
            (array) $request->input('keywords', [])
        )));

        if (empty($keywords)) {
            return ['error' => 'keywords_required'];
        }

        return $this->saas->keywordData(
            $shop->license_key,
            $shop->tenant_key,
            $keywords,
            $request->input('location_code') ? (int) $request->input('location_code') : null,
            $request->input('language_code') ? (string) $request->input('language_code') : null
        );
    }

    /**
     * Cluster keywords by SERP similarity.
     *
     * POST /api/keywords/cluster
     * Body: { keywords: [...] }
     */
    public function cluster(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $keywords = array_values(array_filter(array_map(
            fn ($k) => trim((string) $k),
            (array) $request->input('keywords', [])
        )));

        if (count($keywords) < 2) {
            return ['error' => 'min_2_keywords_required'];
        }

        return $this->saas->keywordCluster($shop->license_key, $shop->tenant_key, $keywords);
    }

    /**
     * Google Trends data for keywords.
     *
     * POST /api/keywords/trends
     * Body: { keywords: [...] }
     */
    public function trends(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $keywords = array_values(array_filter(array_map(
            fn ($k) => trim((string) $k),
            (array) $request->input('keywords', [])
        )));

        if (empty($keywords)) {
            return ['error' => 'keywords_required'];
        }

        return $this->saas->googleTrends($shop->license_key, $shop->tenant_key, $keywords);
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
}

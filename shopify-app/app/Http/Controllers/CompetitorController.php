<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\CompetitorService;
use App\Services\LicenseService;
use Illuminate\Http\Request;

/**
 * CompetitorController
 *
 * SERP competitor analysis: analyse who ranks for a keyword, enriched with
 * backlink data and keyword metrics.
 */
class CompetitorController extends Controller
{
    public function __construct(
        private CompetitorService $competitor,
        private LicenseService $license
    ) {}

    /**
     * Analyse SERP competitors for a keyword.
     *
     * POST /api/competitor/analyze
     * Body: { keyword, target_url?, country?, language? }
     */
    public function analyze(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $keyword = trim((string) $request->input('keyword', ''));

        if ($keyword === '') {
            return ['error' => 'keyword_required'];
        }

        $targetUrl = trim((string) $request->input('target_url', '')) ?: $shop->shop_domain;
        $country = trim((string) $request->input('country', 'us')) ?: 'us';
        $language = trim((string) $request->input('language', 'en')) ?: 'en';

        return $this->competitor->analyze(
            $shop->license_key,
            $shop->tenant_key,
            $keyword,
            $targetUrl,
            $country,
            $language
        );
    }

    /**
     * Compare two domains on backlink metrics.
     *
     * POST /api/competitor/compare
     * Body: { domain_a, domain_b }
     */
    public function compare(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $domainA = trim((string) $request->input('domain_a', ''));
        $domainB = trim((string) $request->input('domain_b', ''));

        if ($domainA === '' || $domainB === '') {
            return ['error' => 'both_domains_required'];
        }

        return $this->competitor->compare(
            $shop->license_key,
            $shop->tenant_key,
            $domainA,
            $domainB
        );
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

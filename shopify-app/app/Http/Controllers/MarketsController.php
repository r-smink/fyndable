<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\MarketsAuditService;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;

/**
 * MarketsController
 *
 * Shopify Markets SEO Check — audits multi-market setup for hreflang
 * consistency, locale coverage, translation gaps, and SEO best practices.
 */
class MarketsController extends Controller
{
    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private MarketsAuditService $audit,
        private LicenseService $license
    ) {}

    /**
     * List markets with locales, regions and web presences.
     *
     * GET /api/markets/overview
     */
    public function overview(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $markets = $this->fetcher->getMarkets($shop);

        if (isset($markets['error'])) {
            return $markets;
        }

        return ['success' => true, 'markets' => $markets];
    }

    /**
     * Full SEO audit of multi-market setup.
     *
     * GET /api/markets/audit
     */
    public function audit(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');

        return $this->audit->audit($shop);
    }

    /**
     * Translation status per market.
     *
     * GET /api/markets/{marketId}/content-status
     */
    public function contentStatus(Request $request, string $marketId): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');

        return $this->audit->contentStatus($shop, $marketId);
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

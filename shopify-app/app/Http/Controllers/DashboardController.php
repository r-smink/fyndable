<?php

namespace App\Http\Controllers;

use App\Models\LlmsTxtSettings;
use App\Models\Shop;
use App\Models\TrackedKeyword;
use App\Services\LicenseService;
use App\Services\ShopifyContentFetcher;
use App\Services\ShopifySignature;
use Illuminate\Http\Request;

/**
 * DashboardController
 *
 * Serves the embedded Shopify App Bridge dashboard.
 * Provides overview data and renders the main app UI.
 */
class DashboardController extends Controller
{
    public function __construct(
        private LicenseService $license,
        private ShopifyContentFetcher $fetcher
    ) {}

    /**
     * Render the embedded app dashboard (App Bridge + Polaris).
     *
     * This is the initial HTML shell — it cannot carry a bearer token, so it
     * only validates the `shop` query param and renders the view. All data is
     * loaded via /api/* endpoints protected by the session-token middleware.
     */
    public function index(Request $request)
    {
        $shopDomain = ShopifySignature::normalizeShopDomain((string) $request->query('shop', ''));
        if ($shopDomain === null) {
            abort(400, 'Invalid or missing shop parameter.');
        }

        return view('dashboard.index', [
            'apiKey' => config('shopify.api_key'),
            'shopDomain' => $shopDomain,
        ]);
    }

    /**
     * Get overview stats for the dashboard.
     *
     * GET /api/dashboard/overview
     */
    public function overview(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $licenseStatus = $this->license->validate($shop);

        // Get product count
        $productCount = $this->fetcher->getProductCount($shop);

        // Get tracked keyword stats
        $keywordCount = TrackedKeyword::where('shop_id', $shop->id)->count();
        $top10 = TrackedKeyword::where('shop_id', $shop->id)
            ->whereNotNull('last_position')
            ->where('last_position', '<=', 10)
            ->count();

        // llms.txt status
        $llmsSettings = LlmsTxtSettings::getForShop($shop->id);

        return [
            'shop' => [
                'domain' => $shop->shop_domain,
                'name' => $shop->shop_name,
                'currency' => $shop->currency,
            ],
            'license' => $licenseStatus,
            'product_count' => $productCount,
            'tracked_keywords' => $keywordCount,
            'top_10_keywords' => $top10,
            'llms_txt_enabled' => $llmsSettings->enabled,
            'llms_txt_full_enabled' => $llmsSettings->full_enabled,
        ];
    }
}

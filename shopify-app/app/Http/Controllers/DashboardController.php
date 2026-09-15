<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\ShopifyContentFetcher;
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
     */
    public function index(Request $request)
    {
        $shop = $request->attributes->get('shop');
        if (!$shop instanceof Shop) {
            return redirect()->route('install.form');
        }

        $licenseStatus = $this->license->validate($shop);
        $shopDetails = $this->fetcher->getShopDetails($shop);

        return view('dashboard.index', [
            'shop' => $shop,
            'license' => $licenseStatus,
            'shopDetails' => $shopDetails,
            'apiKey' => config('shopify.api_key'),
            'shopDomain' => $shop->shop_domain,
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
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $licenseStatus = $this->license->validate($shop);

        // Get product count
        $products = $this->fetcher->getProducts($shop, 1);
        $productCount = count($products);

        // Get tracked keyword stats
        $keywordCount = \App\Models\TrackedKeyword::where('shop_id', $shop->id)->count();
        $top10 = \App\Models\TrackedKeyword::where('shop_id', $shop->id)
            ->whereNotNull('last_position')
            ->where('last_position', '<=', 10)
            ->count();

        // llms.txt status
        $llmsSettings = \App\Models\LlmsTxtSettings::getForShop($shop->id);

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

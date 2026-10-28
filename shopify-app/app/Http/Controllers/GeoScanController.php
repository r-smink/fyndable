<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use Illuminate\Http\Request;

/**
 * GeoScanController
 *
 * AI Visibility (GEO) scanning — checks how well a shop's content is cited
 * by AI models (ChatGPT, Gemini, Claude, Perplexity). All heavy lifting
 * happens on the SaaS dashboard; this controller is a thin proxy.
 */
class GeoScanController extends Controller
{
    public function __construct(
        private SaasProxyClient $saas,
        private LicenseService $license
    ) {}

    /**
     * Start a new GEO scan.
     *
     * POST /api/geo-scan/start
     * Body: { keywords: "keyword1, keyword2" }
     */
    public function start(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $keywords = trim((string) $request->input('keywords', ''));

        if ($keywords === '') {
            return ['error' => 'keywords_required'];
        }

        $url = "https://{$shop->shop_domain}";

        return $this->saas->geoScan($shop->license_key, $shop->tenant_key, $url, $keywords);
    }

    /**
     * Poll scan status and results.
     *
     * GET /api/geo-scan/{id}/status
     */
    public function status(Request $request, int $id): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');

        return $this->saas->geoScanStatus($shop->license_key, $shop->tenant_key, $id);
    }

    /**
     * List recent scans.
     *
     * GET /api/geo-scan/recent
     */
    public function recent(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $limit = max(1, min((int) $request->query('limit', 10), 50));

        return $this->saas->geoScanRecent($shop->license_key, $shop->tenant_key, $limit);
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

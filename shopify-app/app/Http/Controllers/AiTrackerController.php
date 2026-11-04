<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\AiTrackerService;
use App\Services\LicenseService;
use Illuminate\Http\Request;

/**
 * AiTrackerController
 *
 * AI Optimization Tracker — tracks AI search engine visibility over time
 * (mentions, trends, brand citations across ChatGPT/Claude/Gemini/Perplexity).
 */
class AiTrackerController extends Controller
{
    public function __construct(
        private AiTrackerService $tracker,
        private LicenseService $license
    ) {}

    /**
     * Overview: total mentions, top pages, top brands.
     *
     * GET /api/ai-tracker/overview
     */
    public function overview(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $target = trim((string) $request->query('target', '')) ?: $shop->shop_domain;

        return $this->tracker->overview($shop->license_key, $shop->tenant_key, $target);
    }

    /**
     * Timeseries: AI visibility trends over time.
     *
     * GET /api/ai-tracker/timeseries
     */
    public function timeseries(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $target = trim((string) $request->query('target', '')) ?: $shop->shop_domain;

        return $this->tracker->timeseries($shop->license_key, $shop->tenant_key, $target);
    }

    /**
     * Live brand check across all 4 AI providers.
     *
     * POST /api/ai-tracker/check
     * Body: { prompt }
     */
    public function check(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $prompt = trim((string) $request->input('prompt', ''));

        if ($prompt === '') {
            return ['error' => 'prompt_required'];
        }

        return $this->tracker->multiProviderCheck($shop->license_key, $shop->tenant_key, $prompt);
    }

    /**
     * AI-generated recommendations based on mention data.
     *
     * GET /api/ai-tracker/recommendations
     */
    public function recommendations(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $target = trim((string) $request->query('target', '')) ?: $shop->shop_domain;

        return $this->tracker->recommendations($shop->license_key, $shop->tenant_key, $target);
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

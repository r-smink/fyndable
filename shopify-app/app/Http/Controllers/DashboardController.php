<?php

namespace App\Http\Controllers;

use App\Models\LlmsTxtSettings;
use App\Models\SeoAudit;
use App\Models\Shop;
use App\Models\TrackedKeyword;
use App\Services\LicenseService;
use App\Services\ShopifyBillingService;
use App\Services\ShopifyContentFetcher;
use App\Services\ShopifySignature;
use App\Services\UsageLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        private ShopifyContentFetcher $fetcher,
        private UsageLimiter $usage,
        private ShopifyBillingService $billing
    ) {}

    /**
     * App pages, in s-app-nav order. Each page is its own view
     * (resources/views/dashboard/pages/{page}.blade.php) with its own
     * cacheable script (public/js/pages/{page}.js).
     */
    public const PAGES = [
        'home', 'seoaudit', 'redirects', 'schema', 'content', 'bulk', 'blogwriter', 'calendar',
        'gsc', 'ranktracker', 'kwexplorer', 'competitor', 'backlinks', 'markets',
        'aiready', 'aivisibility', 'geoscan', 'aitracker', 'llmstxt', 'tools',
        'reports', 'settings', 'plan',
    ];

    /** Admin UI languages (merchant-facing text: lang/{en,nl}). */
    public const UI_LANGUAGES = ['en', 'nl'];

    /**
     * Render an embedded app page (App Bridge + Polaris web components).
     *
     * This is the HTML shell: it cannot carry a bearer token, so it only
     * validates the `shop` query param and renders the view. All data is
     * loaded via /api/* endpoints protected by the session-token middleware.
     * The UI language follows the Shopify admin (?locale=nl-NL), which the
     * navigation links pass on to every page.
     */
    public function index(Request $request, string $page = 'home')
    {
        $shopDomain = ShopifySignature::normalizeShopDomain((string) $request->query('shop', ''));
        if ($shopDomain === null) {
            abort(400, 'Invalid or missing shop parameter.');
        }

        $locale = (string) $request->query('locale', '');
        $lang = strtolower(substr($locale, 0, 2));
        if (! in_array($lang, self::UI_LANGUAGES, true)) {
            $lang = $request->getPreferredLanguage(self::UI_LANGUAGES) ?? 'en';
        }
        app()->setLocale($lang);

        $host = (string) $request->query('host', '');
        $navQuery = http_build_query(array_filter([
            'shop' => $shopDomain,
            'host' => preg_match('/^[A-Za-z0-9_\-=]+$/', $host) ? $host : null,
            'locale' => preg_match('/^[A-Za-z]{2}(-[A-Za-z0-9]{2,4})?$/', $locale) ? $locale : null,
            'embedded' => $request->query('embedded') === '1' ? '1' : null,
        ]));

        return response(view('dashboard.pages.'.$page, [
            'apiKey' => config('shopify.api_key'),
            'shopDomain' => $shopDomain,
            'page' => $page,
            'pages' => self::PAGES,
            'navQuery' => $navQuery,
            'lang' => $lang,
            'feature' => config('tiers.tabs.'.$page),
        ]), 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
        ]);
    }

    /**
     * Lightweight page context: billing state, plan gating and onboarding.
     * Every page loads this (the home page uses the full overview instead).
     *
     * GET /api/dashboard/context
     */
    public function context(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $profile = $shop->profile()->first();

        return [
            'billing' => $this->billing->refresh($shop),
            'onboarding' => [
                'done' => (bool) $profile?->onboardingDone(),
                'language' => $profile?->content_language,
            ],
            'plan' => $this->planPayload($shop),
        ];
    }

    private function planPayload(Shop $shop): array
    {
        return [
            'tier' => $this->license->effectiveTier($shop),
            'features' => $this->license->featureAccess($shop),
            'required' => config('tiers.features', []),
            'usage' => $this->usage->summary($shop),
        ];
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

        try {
            // App Pricing sends no webhooks: check the subscription on every
            // app load (cached, see ShopifyBillingService::refresh).
            $billing = $this->billing->refresh($shop);
            $profile = $shop->profile()->first();

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
                'billing' => $billing,
                'onboarding' => [
                    'done' => (bool) $profile?->onboardingDone(),
                    'language' => $profile?->content_language,
                ],
                'product_count' => $productCount,
                'tracked_keywords' => $keywordCount,
                'top_10_keywords' => $top10,
                'seo_score' => SeoAudit::latestCompleted($shop->id)?->score,
                'llms_txt_enabled' => $llmsSettings->enabled,
                'llms_txt_full_enabled' => $llmsSettings->full_enabled,
                'missing_scopes' => $shop->missingScopes(),
                'reauth_url' => '/install?shop='.urlencode($shop->shop_domain),
                'plan' => $this->planPayload($shop),
            ];
        } catch (\Throwable $e) {
            Log::error('DashboardController::overview failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => 'overview_failed', 'message' => $e->getMessage()];
        }
    }
}

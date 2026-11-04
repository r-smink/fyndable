<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopProfile;
use App\Services\BrandVoice;
use App\Services\ShopifyBillingService;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * OnboardingController
 *
 * Zero-config onboarding wizard for the embedded app. The trial tenant is
 * created automatically on the first app load (ShopifyBillingService); the
 * wizard then collects the store profile and suggests the first products to
 * optimize:
 *
 *  1. content language + target country (prefilled from Shopify)
 *  2. brand voice (tone, audience, short brand description)
 *  3. optimize the first products (the dashboard calls the existing product
 *     generate/save endpoints, which also count towards the trial)
 *
 * GET  /api/onboarding          - status, profile, suggestions, products
 * POST /api/onboarding/profile  - save language, country and brand voice
 * POST /api/onboarding/complete - mark the wizard as finished
 * POST /api/onboarding/skip     - hide the wizard without finishing
 */
class OnboardingController extends Controller
{
    /** Products suggested in the "optimize your first products" step. */
    private const SUGGESTED_PRODUCTS = 5;

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private ShopifyBillingService $billing
    ) {}

    public function show(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $profile = $this->profile($shop);

        return [
            'success' => true,
            'done' => $profile->onboardingDone(),
            'completed' => $profile->onboarding_completed_at !== null,
            'profile' => $this->profilePayload($profile),
            'suggestions' => $this->suggestions($shop, $request),
            'languages' => BrandVoice::LANGUAGES,
            'tones' => array_keys(BrandVoice::TONES),
            'products' => $this->suggestedProducts($shop),
            'trial' => $this->billing->status($shop)['trial'],
        ];
    }

    public function saveProfile(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $validator = validator($request->all(), [
            'content_language' => ['required', Rule::in(array_keys(BrandVoice::LANGUAGES))],
            'target_country' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'brand_tone' => ['nullable', Rule::in(array_keys(BrandVoice::TONES))],
            'brand_audience' => ['nullable', 'string', 'max:255'],
            'brand_description' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return ['error' => 'invalid_profile', 'fields' => $validator->errors()->keys()];
        }

        $data = $validator->validated();
        $profile = $this->profile($shop);
        $profile->fill([
            'content_language' => $data['content_language'],
            'target_country' => ! empty($data['target_country']) ? strtoupper($data['target_country']) : null,
            'brand_tone' => $data['brand_tone'] ?? null,
            'brand_audience' => isset($data['brand_audience']) ? trim($data['brand_audience']) : null,
            'brand_description' => isset($data['brand_description']) ? trim($data['brand_description']) : null,
        ]);
        $profile->save();

        return ['success' => true, 'profile' => $this->profilePayload($profile)];
    }

    public function complete(Request $request): array
    {
        return $this->mark($request, 'onboarding_completed_at');
    }

    public function skip(Request $request): array
    {
        return $this->mark($request, 'onboarding_skipped_at');
    }

    private function mark(Request $request, string $column): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $profile = $this->profile($shop);
        $profile->{$column} = now();
        $profile->save();

        return ['success' => true];
    }

    private function profile(Shop $shop): ShopProfile
    {
        return ShopProfile::firstOrCreate(['shop_id' => $shop->id]);
    }

    private function profilePayload(ShopProfile $profile): array
    {
        return $profile->only([
            'content_language',
            'target_country',
            'brand_tone',
            'brand_audience',
            'brand_description',
        ]);
    }

    /**
     * Prefill: Shopify primary locale and billing country, falling back to
     * the admin language the app was opened in (?locale=nl-NL).
     */
    private function suggestions(Shop $shop, Request $request): array
    {
        $defaults = $this->fetcher->getLocaleDefaults($shop);

        $language = $defaults['language'];
        if ($language === null || ! isset(BrandVoice::LANGUAGES[$language])) {
            $adminLocale = strtolower(substr((string) $request->query('locale', ''), 0, 2));
            $language = isset(BrandVoice::LANGUAGES[$adminLocale]) ? $adminLocale : 'en';
        }

        return ['content_language' => $language, 'target_country' => $defaults['country']];
    }

    /**
     * Products without a custom SEO title/description first.
     */
    private function suggestedProducts(Shop $shop): array
    {
        $items = $this->fetcher->search($shop, 'product', '', 50);
        if (isset($items['error'])) {
            return [];
        }

        $products = array_map(fn (array $item) => [
            'id' => $item['id'] ?? '',
            'title' => $item['title'] ?? '',
            'image' => $item['featuredImage']['url'] ?? null,
            'seo_missing' => empty($item['seo']['title'] ?? null) || empty($item['seo']['description'] ?? null),
        ], array_filter($items, 'is_array'));

        usort($products, fn ($a, $b) => (int) $b['seo_missing'] <=> (int) $a['seo_missing']);

        return array_slice($products, 0, self::SUGGESTED_PRODUCTS);
    }
}

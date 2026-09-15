<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * LicenseService
 *
 * Handles Fyndable license activation and validation for Shopify shops.
 * Delegates all API calls to SaasProxyClient (which talks to portal.fyndable.ai).
 */
class LicenseService
{
    private SaasProxyClient $proxy;

    public function __construct(SaasProxyClient $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Activate a Fyndable license for a shop.
     *
     * @param  Shop  $shop
     * @param  string  $licenseKey
     * @return array{success: bool, message?: string}
     */
    public function activate(Shop $shop, string $licenseKey): array
    {
        $result = $this->proxy->activateLicense($licenseKey, $shop->shop_domain, $shop->shop_name ?? '');

        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']];
        }

        $shop->license_key = $licenseKey;
        $shop->tenant_key = $result['tenant_key'] ?? '';
        $shop->license_tier = $result['tier'] ?? 'free';
        $shop->license_validated_at = now();
        $shop->save();

        // Invalidate cached validation
        Cache::forget($this->cacheKey($shop->id));

        return ['success' => true, 'tier' => $shop->license_tier];
    }

    /**
     * Validate the stored license (cached for 1 hour).
     *
     * @param  Shop  $shop
     * @return array{valid: bool, tier?: string}
     */
    public function validate(Shop $shop): array
    {
        if (! $shop->hasLicense()) {
            return ['valid' => false];
        }

        $cacheKey = $this->cacheKey($shop->id);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->proxy->validateLicense($shop->license_key, $shop->tenant_key);

        if (isset($result['error'])) {
            // Network error — keep current status, don't invalidate
            Log::warning('LicenseService: validation network error', ['error' => $result['error']]);

            return ['valid' => true, 'tier' => $shop->license_tier, 'cached' => false];
        }

        $valid = $result['valid'] ?? false;
        $tier = $result['tier'] ?? $shop->license_tier;

        if ($valid) {
            $shop->license_tier = $tier;
            $shop->license_validated_at = now();
            $shop->save();
        }

        $data = ['valid' => $valid, 'tier' => $tier];
        Cache::put($cacheKey, $data, now()->addHour());

        return $data;
    }

    /**
     * Check if a shop has an active license (uses cache).
     */
    public function isActive(Shop $shop): bool
    {
        return $this->validate($shop)['valid'] ?? false;
    }

    /**
     * Get the license tier for a shop.
     */
    public function getTier(Shop $shop): string
    {
        $result = $this->validate($shop);

        return $result['tier'] ?? 'free';
    }

    /**
     * Check if the shop meets a minimum tier.
     *
     * @param  Shop  $shop
     * @param  array  $allowedTiers  e.g. ['professional', 'business', 'agency']
     */
    public function hasTier(Shop $shop, array $allowedTiers): bool
    {
        $tier = $this->getTier($shop);

        return in_array($tier, $allowedTiers, true);
    }

    /**
     * Deactivate the license (clears local state; SaaS dashboard keeps the activation).
     */
    public function deactivate(Shop $shop): void
    {
        $shop->license_key = null;
        $shop->tenant_key = null;
        $shop->license_tier = 'free';
        $shop->license_validated_at = null;
        $shop->save();

        Cache::forget($this->cacheKey($shop->id));
    }

    private function cacheKey(int $shopId): string
    {
        return "shop:{$shopId}:license_status";
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use Illuminate\Http\Request;

/**
 * LicenseController
 *
 * License status of the shop. The license comes from the Shopify App Pricing
 * subscription (ShopifyBillingService + SaaS sync); there is no manual key
 * entry in the Shopify app (App Store requirement 1.2.1).
 */
class LicenseController extends Controller
{
    public function __construct(
        private LicenseService $license
    ) {}

    /**
     * Get the current license status for the shop.
     *
     * GET /api/license/status
     */
    public function status(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $validation = $this->license->validate($shop);

        return [
            'has_license' => $shop->hasLicense(),
            'source' => $shop->license_source,
            'license_key' => $shop->license_key ? (substr($shop->license_key, 0, 8).'...'.substr($shop->license_key, -4)) : null,
            'tier' => $validation['tier'] ?? 'free',
            'valid' => $validation['valid'] ?? false,
            'validated_at' => $shop->license_validated_at?->toIso8601String(),
        ];
    }
}

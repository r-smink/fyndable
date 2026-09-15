<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use Illuminate\Http\Request;

/**
 * LicenseController
 *
 * Handles Fyndable license activation and deactivation from the Shopify app UI.
 * The shop owner enters their Fyndable license key, which is validated against
 * the SaaS dashboard (portal.fyndable.ai).
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
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $validation = $this->license->validate($shop);

        return [
            'has_license' => $shop->hasLicense(),
            'license_key' => $shop->license_key ? (substr($shop->license_key, 0, 8) . '...' . substr($shop->license_key, -4)) : null,
            'tier' => $validation['tier'] ?? 'free',
            'valid' => $validation['valid'] ?? false,
            'validated_at' => $shop->license_validated_at?->toIso8601String(),
        ];
    }

    /**
     * Activate a Fyndable license for the shop.
     *
     * POST /api/license/activate
     * Body: { license_key: "FYND-XXXX-XXXX-XXXX" }
     */
    public function activate(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $licenseKey = strtoupper(trim($request->input('license_key', '')));
        if (empty($licenseKey)) {
            return ['error' => 'license_key_required'];
        }

        $result = $this->license->activate($shop, $licenseKey);

        return $result;
    }

    /**
     * Deactivate the license for the shop.
     *
     * POST /api/license/deactivate
     */
    public function deactivate(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (!$shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $this->license->deactivate($shop);

        return ['success' => true];
    }
}

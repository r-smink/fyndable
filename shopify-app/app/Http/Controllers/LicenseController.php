<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
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
        private LicenseService $license,
        private SaasProxyClient $proxy
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

    /**
     * Activate a Fyndable license for the shop.
     *
     * POST /api/license/activate
     * Body: { license_key: "FYND-XXXX-XXXX-XXXX" }
     */
    public function activate(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $licenseKey = strtoupper(trim($request->input('license_key', '')));
        if (empty($licenseKey)) {
            return ['error' => 'license_key_required'];
        }

        // Regular plans are billed through Shopify App Pricing. A manually
        // entered key is only accepted for agency/enterprise licenses, checked
        // before activation so other keys are never bound to this shop.
        $allowed = config('shopify.billing.manual_license_tiers', ['agency', 'dev']);
        $check = $this->proxy->checkLicense($licenseKey, $shop->shop_domain);
        if (! ($check['valid'] ?? false)) {
            return ['success' => false, 'error' => $check['error'] ?? 'invalid_key'];
        }
        if (! in_array($check['tier'] ?? '', $allowed, true)) {
            return [
                'success' => false,
                'error' => 'license_tier_not_allowed',
                'message' => 'Only agency and enterprise license keys can be entered here. Choose a plan via Shopify instead.',
            ];
        }

        $result = $this->license->activate($shop, $licenseKey);
        if ($result['success'] ?? false) {
            $shop->license_source = 'manual';
            $shop->save();
        }

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
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        // App Pricing licenses follow the Shopify subscription; the merchant
        // changes or cancels the plan in Shopify, not here.
        if ($shop->license_source === 'app_pricing') {
            return ['success' => false, 'error' => 'managed_by_shopify'];
        }

        $this->license->deactivate($shop);
        $shop->license_source = null;
        $shop->billing_state = null;
        $shop->billing_checked_at = null;
        $shop->save();

        return ['success' => true];
    }
}

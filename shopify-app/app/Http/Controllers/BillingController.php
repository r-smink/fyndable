<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopifyBillingService;
use Illuminate\Http\Request;

/**
 * BillingController
 *
 * Shopify App Pricing status for the embedded dashboard.
 *
 * GET  /api/billing/status  - current state (refreshed when stale)
 * POST /api/billing/confirm - after the plan selection redirect; the
 *                             plan_handle query/body value is only a hint,
 *                             the subscription is always re-read from the
 *                             Partner API.
 */
class BillingController extends Controller
{
    public function __construct(private ShopifyBillingService $billing) {}

    public function status(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        return $this->billing->refresh($shop);
    }

    public function confirm(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $status = $this->billing->refresh($shop, true);

        $hint = (string) $request->input('plan_handle', '');
        $status['plan_confirmed'] = $hint !== '' && $status['state'] === 'active' && $status['plan'] === $hint;

        return $status;
    }
}

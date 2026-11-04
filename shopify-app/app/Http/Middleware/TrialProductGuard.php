<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\ShopifyBillingService;
use Closure;
use Illuminate\Http\Request;

/**
 * TrialProductGuard
 *
 * In-app trial cap: during the trial a shop may run AI optimizations on a
 * limited number of distinct products (config shopify.billing.trial_products).
 * The trial is valid for config shopify.billing.trial_days days. Already
 * counted products stay usable; the first product beyond the limit ends the
 * trial (402 trial_exhausted), as does any product after the trial window
 * (402 trial_expired). Both include the plan selection URL.
 *
 * Usage: ->middleware('shopify.trial') on product AI routes. The product id
 * comes from the {productId} route parameter, or {id} when {type} = product.
 * Runs after shopify.session; a no-op outside the trial.
 */
class TrialProductGuard
{
    public function __construct(private ShopifyBillingService $billing) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop || ! $this->billing->enabled() || $shop->billing_state !== 'trial') {
            return $next($request);
        }

        $productId = $this->productId($request);
        if ($productId === null) {
            return $next($request);
        }

        if (! $this->billing->claimTrialProduct($shop, $productId)) {
            $expired = $this->billing->trialExpired($shop);

            return response()->json([
                'error' => $expired ? 'trial_expired' : 'trial_exhausted',
                'trial_limit' => $this->billing->trialLimit(),
                'trial_days' => $this->billing->trialDays(),
                'pricing_url' => $this->billing->pricingPageUrl($shop),
                'message' => $expired
                    ? 'Your '.$this->billing->trialDays().'-day free trial has ended. Choose a plan to continue.'
                    : 'Your free trial covers '.$this->billing->trialLimit().' products. Choose a plan to continue.',
            ], 402);
        }

        return $next($request);
    }

    /**
     * Numeric Shopify product id from the route (accepts gid or numeric).
     */
    private function productId(Request $request): ?string
    {
        $route = $request->route();
        $raw = $route?->parameter('productId');

        if ($raw === null && $route?->parameter('type') === 'product') {
            $raw = $route->parameter('id');
        }

        if ($raw === null) {
            return null;
        }

        return preg_match('/(\d+)$/', urldecode((string) $raw), $m) ? $m[1] : null;
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;

/**
 * RequireTier
 *
 * Route middleware that blocks a feature for shops below the feature's
 * minimum tier (config/tiers.php). Runs after shopify.session, which
 * attaches the Shop model to the request attributes.
 *
 * Usage: ->middleware('shopify.tier:rank_tracker')
 *
 * Returns 403 tier_too_low with the required and current tier, so the
 * embedded app can show an upgrade notice.
 */
class RequireTier
{
    public function __construct(private LicenseService $license) {}

    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $shop = $request->attributes->get('shop');

        if ($shop instanceof Shop && ! $this->license->canUse($shop, $feature)) {
            $required = $this->license->requiredTier($feature);
            $current = $this->license->effectiveTier($shop);

            return response()->json([
                'error' => $current === 'free' ? 'license_inactive' : 'tier_too_low',
                'feature' => $feature,
                'required_tier' => $required,
                'current_tier' => $current,
                'message' => $current === 'free'
                    ? 'This feature requires an active Fyndable license.'
                    : 'This feature requires the '.ucfirst((string) $required).' plan or higher.',
            ], 403);
        }

        return $next($request);
    }
}

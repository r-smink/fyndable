<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\RedirectManager;
use App\Services\ShopifySignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

/**
 * NotFoundController (1.2 404 monitor)
 *
 * POST /apps/fyndable/404 (app proxy -> /api/404). The theme app embed
 * "Fyndable 404 monitor" sends a beacon from the theme's 404 template with
 * {"path": "...", "ref": "..."}. Shopify signs app proxy requests, so only
 * real storefront requests are accepted. Always answers 204 (beacons do not
 * read the response).
 */
class NotFoundController extends Controller
{
    /** Reports per shop per minute (bots hitting random URLs). */
    private const PER_MINUTE = 120;

    public function report(Request $request, RedirectManager $manager, LicenseService $license): Response
    {
        $signature = new ShopifySignature((string) config('shopify.api_secret'));
        if (! $signature->verifyAppProxy($request)) {
            return response('', 401);
        }

        $shop = Shop::findByDomain((string) $request->query('shop', ''));
        if (! $shop || ! $shop->is_installed) {
            return response('', 204);
        }

        $key = 'not_found:'.$shop->id;
        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            return response('', 204);
        }
        RateLimiter::hit($key, 60);

        if (! $license->canUse($shop, 'not_found_monitor')) {
            return response('', 204);
        }

        // sendBeacon posts text/plain, so decode the raw body.
        $body = json_decode(mb_substr((string) $request->getContent(), 0, 4096), true);
        if (is_array($body) && is_string($body['path'] ?? null)) {
            $manager->recordNotFound($shop, $body['path'], is_string($body['ref'] ?? null) ? $body['ref'] : null);
        }

        return response('', 204);
    }
}

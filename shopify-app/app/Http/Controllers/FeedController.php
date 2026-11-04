<?php

namespace App\Http\Controllers;

use App\Models\ProductFeed;
use App\Services\LicenseService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * FeedController (2.1): public product feeds for Google Merchant Center and
 * OpenAI, under a secret per-shop token (/feeds/{token}/google.xml,
 * /feeds/{token}/openai.jsonl). Only product data that is public on the
 * storefront is in the feed.
 */
class FeedController extends Controller
{
    public function show(string $token, string $file, LicenseService $license): Response
    {
        $format = array_search($file, ProductFeed::FORMATS, true);
        $feed = $format !== false && strlen($token) >= 32 ? ProductFeed::where('token', $token)->first() : null;

        if (! $feed || ! $feed->enabled || ! $feed->shop || ! $feed->shop->is_installed || ! $license->canUse($feed->shop, 'ai_readiness')) {
            abort(404);
        }

        $disk = Storage::disk('local');
        $path = $feed->path($format);
        if (! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => $format === 'google' ? 'application/xml; charset=utf-8' : 'application/jsonl; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'X-Robots-Tag' => 'noindex',
            'Last-Modified' => $feed->built_at?->toRfc7231String(),
        ]);
    }
}

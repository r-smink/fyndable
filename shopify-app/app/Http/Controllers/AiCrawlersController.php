<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\AiCrawlers;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;

/**
 * AiCrawlersController (2.2)
 *
 * GET  /api/ai-crawlers        - AI bots with company and purpose
 * POST /api/ai-crawlers/check  - live robots.txt status per bot
 */
class AiCrawlersController extends Controller
{
    public function __construct(
        private AiCrawlers $crawlers,
        private ShopifyContentFetcher $fetcher
    ) {}

    public function show(Request $request): array
    {
        return [
            'success' => true,
            'bots' => array_map(fn ($meta) => ['company' => $meta[0], 'purpose' => $meta[1]], AiCrawlers::BOTS),
        ];
    }

    public function check(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }
        $details = $this->fetcher->getShopDetails($shop);

        return [
            'success' => true,
            'live' => $this->crawlers->liveStatus((string) ($details['domain'] ?? 'https://'.$shop->shop_domain)),
        ];
    }
}

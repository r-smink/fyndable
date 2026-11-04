<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\AiCrawlers;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;

/**
 * AiCrawlersController (2.2)
 *
 * GET  /api/ai-crawlers        - bots, choices, the Fyndable block and full template
 * POST /api/ai-crawlers        - {bots: {UserAgent: allow|block}}
 * POST /api/ai-crawlers/check  - theme file status + live robots.txt per bot
 */
class AiCrawlersController extends Controller
{
    public function __construct(
        private AiCrawlers $crawlers,
        private ShopifyContentFetcher $fetcher
    ) {}

    public function show(Request $request): array
    {
        return $this->payload($this->crawlers->settings($this->shop($request)));
    }

    public function save(Request $request): array
    {
        $bots = $request->input('bots');
        if (! is_array($bots)) {
            return ['error' => 'invalid_settings'];
        }

        return $this->payload($this->crawlers->save($this->shop($request), $bots));
    }

    public function check(Request $request): array
    {
        $shop = $this->shop($request);
        $settings = $this->crawlers->settings($shop);
        $details = $this->fetcher->getShopDetails($shop);

        return [
            'success' => true,
            'theme' => $this->crawlers->themeStatus($shop, $settings, $this->fetcher),
            'live' => $this->crawlers->liveStatus((string) ($details['domain'] ?? 'https://'.$shop->shop_domain)),
        ];
    }

    private function payload(array $settings): array
    {
        return [
            'success' => true,
            'bots' => array_map(fn ($meta) => ['company' => $meta[0], 'purpose' => $meta[1]], AiCrawlers::BOTS),
            'settings' => $settings,
            'block' => $this->crawlers->block($settings),
            'template' => $this->crawlers->fullTemplate($settings),
        ];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

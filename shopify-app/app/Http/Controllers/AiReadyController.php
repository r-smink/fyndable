<?php

namespace App\Http\Controllers;

use App\Jobs\BuildProductFeedJob;
use App\Models\AiReadinessItem;
use App\Models\ProductFeed;
use App\Models\Shop;
use App\Services\AiAttributes;
use App\Services\AiCommerce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * AiReadyController (2.1 AI-commerce readiness)
 *
 * GET  /api/ai-ready                          - feed status, URLs, average score, issue summary
 * GET  /api/ai-ready/products                 - products worst first (?issue=&page=)
 * POST /api/ai-ready/feed                     - {enabled} turn the public feed on/off
 * POST /api/ai-ready/rebuild                  - rebuild feed + scores (max every 10 min)
 * POST /api/ai-ready/rotate-token             - new secret feed URLs
 * POST /api/ai-ready/{productId}/attributes   - generate AI attributes (preview)
 * POST /api/ai-ready/{productId}/attributes/save - save the (edited) attributes
 */
class AiReadyController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private AiAttributes $attributes) {}

    public function show(Request $request): array
    {
        $feed = ProductFeed::forShop($this->shop($request));

        return [
            'success' => true,
            'feed' => [
                'enabled' => $feed->enabled,
                'status' => $feed->status,
                'error' => $feed->error,
                'built_at' => $feed->built_at?->toIso8601String(),
                'products' => $feed->products_count,
                'items' => $feed->items_count,
                'urls' => $feed->enabled ? ['google' => $feed->url('google'), 'openai' => $feed->url('openai')] : null,
            ],
            'average_score' => $feed->average_score,
            'summary' => $feed->summary ?? [],
            'checks' => AiCommerce::CHECKS,
        ];
    }

    public function products(Request $request): array
    {
        $shop = $this->shop($request);
        $issue = (string) $request->query('issue', '');
        $page = max(1, (int) $request->query('page', 1));

        $query = AiReadinessItem::where('shop_id', $shop->id)->orderBy('score')->orderBy('id');
        if (isset(AiCommerce::CHECKS[$issue])) {
            $query->where('issues', 'like', '%"'.$issue.'"%');
        }
        $total = (clone $query)->count();

        return [
            'success' => true,
            'products' => $query->forPage($page, self::PER_PAGE)->get(['product_id', 'title', 'score', 'issues'])->toArray(),
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / self::PER_PAGE),
        ];
    }

    public function feed(Request $request): array
    {
        $shop = $this->shop($request);
        $feed = ProductFeed::forShop($shop);
        $feed->update(['enabled' => $request->boolean('enabled')]);
        if ($feed->enabled && ! $feed->built_at && $feed->status !== 'building') {
            $feed->update(['status' => 'building']);
            BuildProductFeedJob::dispatch($shop->id);
        }

        return $this->show($request);
    }

    public function rebuild(Request $request): array
    {
        $shop = $this->shop($request);
        $key = 'feed_rebuild:'.$shop->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return ['error' => 'too_soon', 'retry_after' => RateLimiter::availableIn($key)];
        }
        RateLimiter::hit($key, 600);
        ProductFeed::forShop($shop)->update(['status' => 'building', 'error' => null]);
        BuildProductFeedJob::dispatch($shop->id);

        return ['success' => true];
    }

    public function rotateToken(Request $request): array
    {
        ProductFeed::forShop($this->shop($request))->update(['token' => Str::random(48)]);

        return $this->show($request);
    }

    public function generateAttributes(Request $request, string $productId): array
    {
        $result = $this->attributes->generate($this->shop($request), 'gid://shopify/Product/'.$productId);

        return isset($result['error']) ? $result : ['success' => true] + $result;
    }

    public function saveAttributes(Request $request, string $productId): array
    {
        $data = $request->input('attributes');
        if (! is_array($data)) {
            return ['error' => 'invalid_attributes'];
        }

        $error = $this->attributes->save($this->shop($request), 'gid://shopify/Product/'.$productId, $data);

        return $error === null ? ['success' => true, 'attributes' => $this->attributes->normalize($data)] : ['error' => 'save_failed', 'message' => $error];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

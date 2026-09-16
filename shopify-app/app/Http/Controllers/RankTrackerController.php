<?php

namespace App\Http\Controllers;

use App\Jobs\CheckRankingsJob;
use App\Models\RankHistory;
use App\Models\Shop;
use App\Models\TrackedKeyword;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use Illuminate\Http\Request;

/**
 * RankTrackerController
 *
 * Tracks keyword rankings in Google SERP for the shop's product/collection URLs.
 * Uses the SaaS dashboard SERP proxy (/serp/rank-check) — same endpoint as the
 * WordPress RankTracker.
 */
class RankTrackerController extends Controller
{
    public function __construct(
        private SaasProxyClient $saas,
        private LicenseService $license
    ) {}

    /**
     * List tracked keywords for the shop.
     *
     * GET /api/rank-tracker/keywords
     */
    public function listKeywords(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $keywords = TrackedKeyword::where('shop_id', $shop->id)
            ->orderByDesc('updated_at')
            ->get();

        return ['keywords' => $keywords->toArray()];
    }

    /**
     * Add a keyword to track.
     *
     * POST /api/rank-tracker/keywords
     * Body: { keyword, url, country, language }
     */
    public function addKeyword(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->hasTier($shop, ['professional', 'business', 'agency', 'trial', 'dev'])) {
            return ['error' => 'tier_too_low', 'message' => 'Rank tracking requires Professional tier or higher.'];
        }

        $keyword = trim($request->input('keyword', ''));
        $url = trim($request->input('url', ''));
        $country = $request->input('country', 'us');
        $language = $request->input('language', 'en');

        if (empty($keyword)) {
            return ['error' => 'keyword_required'];
        }

        $existing = TrackedKeyword::where('shop_id', $shop->id)
            ->where('keyword', $keyword)
            ->where('url', $url)
            ->first();

        if ($existing) {
            return ['error' => 'already_exists', 'keyword' => $existing->toArray()];
        }

        $tracked = TrackedKeyword::create([
            'shop_id' => $shop->id,
            'keyword' => $keyword,
            'url' => $url,
            'country' => $country,
            'language' => $language,
        ]);

        return ['success' => true, 'keyword' => $tracked->toArray()];
    }

    /**
     * Delete a tracked keyword.
     *
     * DELETE /api/rank-tracker/keywords/{id}
     */
    public function deleteKeyword(Request $request, int $id): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $keyword = TrackedKeyword::where('shop_id', $shop->id)->where('id', $id)->first();
        if (! $keyword) {
            return ['error' => 'not_found'];
        }

        $keyword->delete();

        return ['success' => true];
    }

    /**
     * Check rankings for all tracked keywords (triggers async job).
     *
     * POST /api/rank-tracker/check
     */
    public function checkRankings(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        if (! $this->license->isActive($shop)) {
            return ['error' => 'license_inactive'];
        }

        // Run synchronously so no queue worker is needed
        CheckRankingsJob::dispatchSync($shop->id);

        return ['success' => true, 'message' => 'Rank check completed.'];
    }

    /**
     * Get ranking statistics and history for a keyword.
     *
     * GET /api/rank-tracker/keywords/{id}/history
     */
    public function keywordHistory(Request $request, int $id): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $keyword = TrackedKeyword::where('shop_id', $shop->id)->where('id', $id)->first();
        if (! $keyword) {
            return ['error' => 'not_found'];
        }

        $history = RankHistory::where('tracked_keyword_id', $keyword->id)
            ->orderByDesc('checked_at')
            ->limit(90)
            ->get();

        return [
            'keyword' => $keyword->toArray(),
            'history' => $history->toArray(),
        ];
    }

    /**
     * Get overall ranking stats for the dashboard.
     *
     * GET /api/rank-tracker/stats
     */
    public function stats(Request $request): array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        $keywords = TrackedKeyword::where('shop_id', $shop->id)->get();
        $total = $keywords->count();
        $top3 = $keywords->filter(fn ($k) => $k->last_position !== null && $k->last_position <= 3)->count();
        $top10 = $keywords->filter(fn ($k) => $k->last_position !== null && $k->last_position <= 10)->count();
        $top100 = $keywords->filter(fn ($k) => $k->last_position !== null && $k->last_position <= 100)->count();
        $notRanked = $keywords->filter(fn ($k) => $k->last_position === null)->count();

        $improved = 0;
        $declined = 0;
        foreach ($keywords as $k) {
            $last = RankHistory::where('tracked_keyword_id', $k->id)
                ->orderByDesc('checked_at')
                ->limit(2)
                ->get();
            if ($last->count() >= 2) {
                $current = $last->first()->position;
                $previous = $last->last()->position;
                if ($current < $previous) {
                    $improved++;
                } elseif ($current > $previous) {
                    $declined++;
                }
            }
        }

        return [
            'total_keywords' => $total,
            'top_3' => $top3,
            'top_10' => $top10,
            'top_100' => $top100,
            'not_ranked' => $notRanked,
            'improved' => $improved,
            'declined' => $declined,
        ];
    }
}

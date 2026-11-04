<?php

namespace App\Http\Controllers;

use App\Models\ScheduledArticle;
use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\ShopifyContentFetcher;
use Illuminate\Http\Request;

/**
 * ContentCalendarController
 *
 * Content calendar for scheduling and managing blog articles. Stores scheduled
 * articles in the local database and publishes them to Shopify when due (via
 * the app:publish-scheduled-articles artisan command).
 */
class ContentCalendarController extends Controller
{
    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private LicenseService $license
    ) {}

    /**
     * List scheduled articles for the current shop.
     *
     * GET /api/calendar/articles?month=2026-10&status=scheduled
     */
    public function index(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $query = ScheduledArticle::where('shop_id', $shop->id);

        // Optional status filter
        $status = $request->query('status');
        if ($status && in_array($status, ['scheduled', 'published', 'failed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        // Optional month filter (YYYY-MM)
        $month = $request->query('month');
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $query->whereRaw('DATE_FORMAT(scheduled_at, "%Y-%m") = ?', [$month]);
        }

        $articles = $query->orderBy('scheduled_at')->limit(100)->get();

        return ['articles' => $articles->toArray()];
    }

    /**
     * Schedule a new article for future publishing.
     *
     * POST /api/calendar/schedule
     * Body: { blog_id, title, body_html, tags?, scheduled_at, author? }
     */
    public function schedule(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');

        $blogId = trim((string) $request->input('blog_id', ''));
        $title = trim((string) $request->input('title', ''));
        $bodyHtml = trim((string) $request->input('body_html', ''));
        $scheduledAt = $request->input('scheduled_at');

        if ($blogId === '' || $title === '' || $bodyHtml === '' || ! $scheduledAt) {
            return ['error' => 'blog_id, title, body_html, and scheduled_at are required'];
        }

        $article = ScheduledArticle::create([
            'shop_id' => $shop->id,
            'blog_id' => $blogId,
            'title' => $title,
            'body_html' => $bodyHtml,
            'tags' => trim((string) $request->input('tags', '')) ?: null,
            'scheduled_at' => $scheduledAt,
            'status' => 'scheduled',
            'meta' => array_filter([
                'author' => trim((string) $request->input('author', '')) ?: null,
                'language' => trim((string) $request->input('language', '')) ?: null,
            ]),
        ]);

        return ['success' => true, 'article' => $article->toArray()];
    }

    /**
     * Update a scheduled (not yet published) article.
     *
     * PUT /api/calendar/{id}
     */
    public function update(Request $request, int $id): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $article = ScheduledArticle::where('shop_id', $shop->id)->find($id);

        if (! $article) {
            return ['error' => 'article_not_found'];
        }

        if ($article->status !== 'scheduled') {
            return ['error' => 'only_scheduled_articles_can_be_updated'];
        }

        $fields = array_filter([
            'title' => trim((string) $request->input('title', '')) ?: null,
            'body_html' => trim((string) $request->input('body_html', '')) ?: null,
            'tags' => $request->has('tags') ? (trim((string) $request->input('tags', '')) ?: null) : null,
            'scheduled_at' => $request->input('scheduled_at'),
            'blog_id' => trim((string) $request->input('blog_id', '')) ?: null,
        ], fn ($v) => $v !== null);

        if (! empty($fields)) {
            $article->update($fields);
        }

        return ['success' => true, 'article' => $article->fresh()->toArray()];
    }

    /**
     * Cancel a scheduled article.
     *
     * DELETE /api/calendar/{id}
     */
    public function destroy(Request $request, int $id): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $article = ScheduledArticle::where('shop_id', $shop->id)->find($id);

        if (! $article) {
            return ['error' => 'article_not_found'];
        }

        if ($article->status === 'published') {
            return ['error' => 'published_articles_cannot_be_deleted'];
        }

        $article->update(['status' => 'cancelled']);

        return ['success' => true];
    }

    /**
     * List published Shopify articles with publishedAt dates.
     *
     * GET /api/calendar/published
     */
    public function published(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');

        $articles = $this->fetcher->getArticles($shop);

        return ['articles' => $articles];
    }

    private function guardLicensed(Request $request): ?array
    {
        $shop = $request->attributes->get('shop');
        if (! $shop instanceof Shop) {
            return ['error' => 'shop_not_found'];
        }

        return $this->license->isActive($shop)
            ? null
            : ['error' => 'license_inactive'];
    }
}

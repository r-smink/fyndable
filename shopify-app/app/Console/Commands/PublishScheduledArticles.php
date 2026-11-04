<?php

namespace App\Console\Commands;

use App\Models\ScheduledArticle;
use App\Services\ShopifyAdminWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledArticles extends Command
{
    protected $signature = 'app:publish-scheduled-articles';

    protected $description = 'Publish scheduled blog articles that are due';

    public function handle(ShopifyAdminWriter $writer): int
    {
        $articles = ScheduledArticle::with('shop')
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->limit(50)
            ->get();

        if ($articles->isEmpty()) {
            $this->info('No scheduled articles due.');

            return self::SUCCESS;
        }

        $published = 0;
        $failed = 0;

        foreach ($articles as $article) {
            $shop = $article->shop;

            if (! $shop || ! $shop->access_token) {
                $article->update(['status' => 'failed', 'error' => 'shop_not_accessible']);
                $failed++;

                continue;
            }

            $meta = $article->meta ?? [];
            $fields = [
                'title' => $article->title,
                'body' => $article->body_html,
                'tags' => $article->tags ? array_map('trim', explode(',', $article->tags)) : [],
                'isPublished' => true,
                'author' => $meta['author'] ?? $shop->shop_name ?? '',
            ];

            $error = $writer->createArticle($shop, $article->blog_id, $fields);

            if ($error) {
                $article->update(['status' => 'failed', 'error' => $error]);
                Log::warning('PublishScheduledArticles: failed', [
                    'article_id' => $article->id,
                    'error' => $error,
                ]);
                $failed++;
            } else {
                $article->update(['status' => 'published']);
                $published++;
            }
        }

        $this->info("Published: {$published}, Failed: {$failed}");

        return self::SUCCESS;
    }
}

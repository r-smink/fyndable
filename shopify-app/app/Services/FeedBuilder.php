<?php

namespace App\Services;

use App\Models\AiReadinessItem;
use App\Models\ProductFeed;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use XMLWriter;

/**
 * FeedBuilder (2.1): reads all variants, scores every product (AI-ready
 * score) and writes the Google Merchant Center XML and the OpenAI JSONL
 * feed to storage. Served by FeedController under a secret token.
 */
class FeedBuilder
{
    public const MAX_VARIANTS = 20000;

    public function __construct(
        private ShopifyContentFetcher $fetcher,
        private AiCommerce $ai
    ) {}

    public function build(Shop $shop, ProductFeed $feed): ?string
    {
        $feed->update(['status' => 'building', 'error' => null]);

        $variants = $this->fetcher->getFeedVariants($shop, self::MAX_VARIANTS);
        if (isset($variants['error'])) {
            $feed->update(['status' => 'failed', 'error' => mb_substr((string) $variants['error'], 0, 255)]);

            return (string) $variants['error'];
        }

        $details = $this->fetcher->getShopDetails($shop);
        $shopInfo = [
            'name' => (string) ($details['name'] ?: ($shop->shop_name ?: $shop->shop_domain)),
            'url' => rtrim((string) $details['domain'], '/'),
            'currency' => (string) ($details['currency'] ?: 'USD'),
        ];

        $products = $this->ai->group($variants);
        $scores = [];
        $summary = [];
        $openAi = [];
        $google = new XMLWriter;
        $google->openMemory();
        $google->setIndent(true);
        $google->startDocument('1.0', 'UTF-8');
        $google->startElement('rss');
        $google->writeAttribute('version', '2.0');
        $google->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $google->startElement('channel');
        $google->writeElement('title', $shopInfo['name']);
        $google->writeElement('link', $shopInfo['url']);
        $google->writeElement('description', 'Product feed by Fyndable Smart SEO');

        $items = 0;
        foreach ($products as $gid => ['product' => $product, 'variants' => $productVariants]) {
            $result = $this->ai->score($product, $productVariants);
            $scores[] = [
                'shop_id' => $shop->id,
                'product_id' => (string) preg_replace('#^.*/#', '', $gid),
                'title' => mb_substr((string) $product['title'] ?: '-', 0, 255),
                'score' => $result['score'],
                'issues' => json_encode($result['issues']),
            ];
            foreach ($result['issues'] as $issue) {
                $summary[$issue['code']] = ($summary[$issue['code']] ?? 0) + 1;
            }

            foreach ($productVariants as $variant) {
                $openAi[] = json_encode($this->ai->openAiRow($product, $variant, $shopInfo), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $this->writeGoogleItem($google, $this->ai->googleItem($product, $variant, $shopInfo));
                $items++;
            }
        }

        $google->endElement();
        $google->endElement();
        $google->endDocument();

        $disk = Storage::disk('local');
        $disk->put($feed->path('google'), $google->outputMemory());
        $disk->put($feed->path('openai'), implode("\n", $openAi).($openAi ? "\n" : ''));

        DB::transaction(function () use ($shop, $scores) {
            AiReadinessItem::where('shop_id', $shop->id)->delete();
            foreach (array_chunk($scores, 500) as $chunk) {
                AiReadinessItem::insert($chunk);
            }
        });

        arsort($summary);
        $feed->update([
            'status' => 'ready',
            'products_count' => count($products),
            'items_count' => $items,
            'average_score' => $scores ? (int) round(array_sum(array_column($scores, 'score')) / count($scores)) : null,
            'summary' => $summary,
            'built_at' => now(),
        ]);

        return null;
    }

    public function delete(Shop $shop): void
    {
        Storage::disk('local')->deleteDirectory('feeds/'.$shop->id);
        AiReadinessItem::where('shop_id', $shop->id)->delete();
        ProductFeed::where('shop_id', $shop->id)->delete();
    }

    private function writeGoogleItem(XMLWriter $xml, array $item): void
    {
        $xml->startElement('item');
        foreach ($item as $name => $value) {
            foreach ((array) $value as $single) {
                $xml->writeElement('g:'.$name, (string) $single);
            }
        }
        $xml->endElement();
    }
}

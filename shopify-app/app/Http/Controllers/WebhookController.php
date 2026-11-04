<?php

namespace App\Http\Controllers;

use App\Jobs\GdprDeleteTenantJob;
use App\Jobs\SyncShopBillingJob;
use App\Jobs\TrackHandleChangeJob;
use App\Models\ActivityEvent;
use App\Models\GoogleConnection;
use App\Models\NotFoundUrl;
use App\Models\ProductFeed;
use App\Models\RankHistory;
use App\Models\RedirectEvent;
use App\Models\ResourceHandle;
use App\Models\ScheduledArticle;
use App\Models\SeoAudit;
use App\Models\SeoAuditItem;
use App\Models\Shop;
use App\Models\StoreSchemaSetting;
use App\Services\FeedBuilder;
use App\Services\LlmsTxtGenerator;
use App\Services\ShopifySignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WebhookController
 *
 * Handles Shopify webhooks for content changes (products, pages, blogs,
 * collections) and app lifecycle events (app/uninstalled).
 *
 * Content webhooks invalidate the llms.txt cache for the affected shop.
 * The app/uninstalled webhook marks the shop as uninstalled.
 */
class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Verify webhook HMAC
        if (! $this->verifyWebhook($request)) {
            Log::warning('Webhook HMAC verification failed');

            return response('Unauthorized', 401);
        }

        $topic = $request->header('X-Shopify-Topic', '');
        $shopDomain = $request->header('X-Shopify-Shop-Domain', '');
        $payload = $request->json()->all();

        $shop = Shop::findByDomain($shopDomain);
        if (! $shop) {
            Log::warning('Webhook for unknown shop', ['shop' => $shopDomain]);

            return response('OK', 200);
        }

        Log::info('Webhook received', ['topic' => $topic, 'shop' => $shopDomain]);

        switch ($topic) {
            case 'app/uninstalled':
                $this->handleUninstall($shop);
                break;

                // GDPR mandatory webhooks — required for App Store listing.
            case 'customers/data_request':
                // The app stores no customer PII — acknowledge only.
                Log::info('GDPR customers/data_request acknowledged', ['shop' => $shopDomain]);
                break;

            case 'customers/redact':
                // The app stores no customer PII — acknowledge only.
                Log::info('GDPR customers/redact acknowledged', ['shop' => $shopDomain]);
                break;

            case 'shop/redact':
                $this->handleShopRedact($shop);
                break;

            case 'products/create':
            case 'products/update':
            case 'products/delete':
            case 'collections/create':
            case 'collections/update':
            case 'collections/delete':
                $this->trackHandle($shop, $topic, $payload);
                $this->handleContentChange($shop);
                break;

            case 'pages/create':
            case 'pages/update':
            case 'pages/delete':
            case 'articles/create':
            case 'articles/update':
            case 'articles/delete':
            case 'blogs/create':
            case 'blogs/update':
            case 'blogs/delete':
                $this->handleContentChange($shop);
                break;

            default:
                Log::info('Unhandled webhook topic', ['topic' => $topic]);
        }

        return response('OK', 200);
    }

    /**
     * Invalidate the llms.txt cache when content changes.
     */
    private function handleContentChange(Shop $shop): void
    {
        try {
            $generator = app(LlmsTxtGenerator::class);
            $generator->invalidate($shop);
            Log::info('llms.txt cache invalidated', ['shop' => $shop->shop_domain]);
        } catch (\Exception $e) {
            Log::error('Failed to invalidate llms.txt cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remember product/collection handles and redirect changed or deleted
     * URLs (1.2). products/delete only sends the id, so the job looks up the
     * last known handle.
     */
    private function trackHandle(Shop $shop, string $topic, array $payload): void
    {
        $id = (string) ($payload['id'] ?? '');
        if (! ctype_digit($id)) {
            return;
        }

        [$resource, $action] = explode('/', $topic, 2);
        $type = $resource === 'collections' ? 'collection' : 'product';

        $action === 'delete'
            ? TrackHandleChangeJob::dispatch($shop->id, $type, $id, null, null, true)
            : TrackHandleChangeJob::dispatch($shop->id, $type, $id, (string) ($payload['handle'] ?? ''), $payload['title'] ?? null);
    }

    /**
     * Mark the shop as uninstalled.
     */
    private function handleUninstall(Shop $shop): void
    {
        $shop->is_installed = false;
        $shop->is_uninstalled = true;
        $shop->access_token = null;
        $shop->save();

        // Google tokens are not needed after uninstall (Search Console, 1.5).
        GoogleConnection::where('shop_id', $shop->id)->delete();
        // Stop serving the public product feeds (2.1).
        ProductFeed::where('shop_id', $shop->id)->update(['enabled' => false]);

        // Shopify cancels the App Pricing subscription on uninstall; suspend
        // the SaaS tenant outside the webhook request (5 second budget).
        SyncShopBillingJob::dispatch($shop->id, true);

        Log::info('Shop uninstalled', ['shop' => $shop->shop_domain]);
    }

    /**
     * shop/redact — erase shop data after uninstall grace period.
     *
     * Removes tracked keywords, rank history, llms.txt settings, the store
     * profile (onboarding), scheduled articles, SEO audits, redirect data (handles, 404s, log), schema settings, the Google connection with Search Console data, reports, the activity log, product feeds and usage counters, and deletes the App Pricing tenant on the
     * SaaS (queued). The shop row is kept without credentials, license or
     * contact data. trial_products and trial_ended_at stay (product IDs only)
     * so a reinstall cannot restart the free trial.
     */
    private function handleShopRedact(Shop $shop): void
    {
        RankHistory::whereIn(
            'tracked_keyword_id',
            $shop->trackedKeywords()->pluck('id')
        )->delete();
        $shop->trackedKeywords()->delete();
        $shop->llmsTxtSettings()->delete();
        $shop->profile()->delete();
        ScheduledArticle::where('shop_id', $shop->id)->delete();
        SeoAuditItem::where('shop_id', $shop->id)->delete();
        SeoAudit::where('shop_id', $shop->id)->delete();
        ResourceHandle::where('shop_id', $shop->id)->delete();
        NotFoundUrl::where('shop_id', $shop->id)->delete();
        RedirectEvent::where('shop_id', $shop->id)->delete();
        StoreSchemaSetting::where('shop_id', $shop->id)->delete();
        GoogleConnection::where('shop_id', $shop->id)->delete();
        ActivityEvent::where('shop_id', $shop->id)->delete();
        app(FeedBuilder::class)->delete($shop);
        DB::table('seo_reports')->where('shop_id', $shop->id)->delete();
        DB::table('gsc_page_stats')->where('shop_id', $shop->id)->delete();
        DB::table('gsc_query_stats')->where('shop_id', $shop->id)->delete();
        DB::table('shop_usage')->where('shop_id', $shop->id)->delete();

        // The App Pricing tenant belongs to this shop: delete it on the SaaS.
        // A manual agency license belongs to the agency account: only unlink.
        if ($shop->license_source === 'app_pricing' && $shop->hasLicense()) {
            GdprDeleteTenantJob::dispatch($shop->license_key, $shop->tenant_key, $shop->shop_domain);
        }

        $shop->access_token = null;
        $shop->refresh_token = null;
        $shop->token_expires_at = null;
        $shop->shop_email = null;
        $shop->shop_name = null;
        $shop->license_key = null;
        $shop->tenant_key = null;
        $shop->license_tier = 'free';
        $shop->license_source = null;
        $shop->license_validated_at = null;
        $shop->billing_state = null;
        $shop->billing_plan = null;
        $shop->billing_checked_at = null;
        $shop->save();

        Log::info('GDPR shop/redact processed', ['shop' => $shop->shop_domain]);
    }

    /**
     * Verify the Shopify webhook HMAC signature.
     */
    private function verifyWebhook(Request $request): bool
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256', '');
        $body = $request->getContent();

        $signature = new ShopifySignature(config('shopify.api_secret'));

        return $signature->verifyWebhook($hmac, $body);
    }
}

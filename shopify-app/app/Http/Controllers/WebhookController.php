<?php

namespace App\Http\Controllers;

use App\Jobs\GdprDeleteTenantJob;
use App\Jobs\SyncShopBillingJob;
use App\Models\RankHistory;
use App\Models\ScheduledArticle;
use App\Models\Shop;
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
     * Mark the shop as uninstalled.
     */
    private function handleUninstall(Shop $shop): void
    {
        $shop->is_installed = false;
        $shop->is_uninstalled = true;
        $shop->access_token = null;
        $shop->save();

        // Shopify cancels the App Pricing subscription on uninstall; suspend
        // the SaaS tenant outside the webhook request (5 second budget).
        SyncShopBillingJob::dispatch($shop->id, true);

        Log::info('Shop uninstalled', ['shop' => $shop->shop_domain]);
    }

    /**
     * shop/redact — erase shop data after uninstall grace period.
     *
     * Removes tracked keywords, rank history, llms.txt settings, the store
     * profile (onboarding), scheduled articles and usage counters, and deletes the App Pricing tenant on the
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

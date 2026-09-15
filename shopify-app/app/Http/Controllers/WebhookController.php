<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LlmsTxtGenerator;
use App\Services\ShopifySignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        Log::info('Shop uninstalled', ['shop' => $shop->shop_domain]);
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

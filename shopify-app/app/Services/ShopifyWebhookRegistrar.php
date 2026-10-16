<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ShopifyWebhookRegistrar
 *
 * Registers the shop-specific (content-change) webhook subscriptions via the
 * REST Admin API. GDPR-mandatory topics are declared app-side in
 * shopify.app.toml and are NOT registered here.
 *
 * Called on install/reinstall — both from the OAuth callback (when it still
 * runs) and from the first token exchange under the managed install flow.
 */
class ShopifyWebhookRegistrar
{
    /**
     * Content-change topics the app subscribes to per shop.
     *
     * @var array<int, string>
     */
    private const TOPICS = [
        'products/create',
        'products/update',
        'products/delete',
        'collections/create',
        'collections/update',
        'collections/delete',
        'pages/create',
        'pages/update',
        'pages/delete',
        'articles/create',
        'articles/update',
        'articles/delete',
        'blogs/create',
        'blogs/update',
        'blogs/delete',
        'app/uninstalled',
    ];

    public function register(Shop $shop): void
    {
        $webhookUrl = url(config('shopify.webhook_uri'));
        $endpoint = "https://{$shop->shop_domain}/admin/api/".config('shopify.api_version').'/webhooks.json';

        foreach (self::TOPICS as $topic) {
            try {
                Http::timeout(15)
                    ->withHeaders([
                        'X-Shopify-Access-Token' => $shop->access_token,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($endpoint, [
                        'webhook' => [
                            'topic' => $topic,
                            'address' => $webhookUrl,
                            'format' => 'json',
                        ],
                    ]);
            } catch (\Exception $e) {
                Log::warning('Webhook registration failed', ['topic' => $topic, 'error' => $e->getMessage()]);
            }
        }
    }
}

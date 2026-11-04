<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PartnerApiClient
 *
 * Thin client for the Shopify Partner API, used to read Shopify App Pricing
 * subscriptions. App Pricing sends no webhooks for subscription changes, so
 * activeSubscription() is the source of truth.
 *
 * Endpoint: https://partners.shopify.com/{partner_id}/api/{version}/graphql.json
 * Auth: X-Shopify-Access-Token (Partner API client, permission "Manage apps").
 * Rate limit: 4 requests/second per client; 429 is reported as an error so
 * callers keep the last known state.
 */
class PartnerApiClient
{
    private const ACTIVE_SUBSCRIPTION_QUERY = <<<'GRAPHQL'
    query ActiveSubscription($appId: ID!, $shopId: ID!) {
      activeSubscription(appId: $appId, shopId: $shopId) {
        billingPeriod
        cancelAtEndOfCycle
        trialEndsAt
        currentBillingCycle { startTime endTime }
        items { handle description }
      }
    }
    GRAPHQL;

    public function isConfigured(): bool
    {
        return config('shopify.billing.partner_id') !== ''
            && config('shopify.billing.partner_api_token') !== ''
            && config('shopify.billing.app_id') !== '';
    }

    /**
     * Read the shop's active App Pricing subscription.
     *
     * @param  string  $shopGid  gid://shopify/Shop/{id}
     *                           The subscription is null when the shop has no active subscription
     *                           (never subscribed, cancelled or frozen).
     * @return array{ok: true, subscription: ?array}|array{ok: false, error: string}
     */
    public function activeSubscription(string $shopGid): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'partner_api_not_configured'];
        }

        $appId = (string) config('shopify.billing.app_id');
        $variables = [
            'appId' => str_starts_with($appId, 'gid://') ? $appId : "gid://shopify/App/{$appId}",
            'shopId' => $shopGid,
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Shopify-Access-Token' => (string) config('shopify.billing.partner_api_token'),
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint(), [
                    'query' => self::ACTIVE_SUBSCRIPTION_QUERY,
                    'variables' => $variables,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('PartnerApiClient: connection failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'connection_failed'];
        }

        if ($response->status() === 429) {
            Log::warning('PartnerApiClient: rate limited');

            return ['ok' => false, 'error' => 'rate_limited'];
        }

        if ($response->failed()) {
            Log::warning('PartnerApiClient: HTTP error', ['status' => $response->status()]);

            return ['ok' => false, 'error' => 'http_'.$response->status()];
        }

        $body = $response->json();
        if (! is_array($body) || ! empty($body['errors'])) {
            Log::warning('PartnerApiClient: GraphQL error', [
                'errors' => mb_substr(json_encode($body['errors'] ?? 'invalid_response'), 0, 500),
            ]);

            return ['ok' => false, 'error' => 'graphql_error'];
        }

        if (! array_key_exists('activeSubscription', $body['data'] ?? [])) {
            return ['ok' => false, 'error' => 'invalid_response'];
        }

        $subscription = $body['data']['activeSubscription'];

        return ['ok' => true, 'subscription' => is_array($subscription) ? $subscription : null];
    }

    private function endpoint(): string
    {
        $partnerId = rawurlencode((string) config('shopify.billing.partner_id'));
        $version = rawurlencode((string) config('shopify.billing.partner_api_version', '2026-10'));

        return "https://partners.shopify.com/{$partnerId}/api/{$version}/graphql.json";
    }
}

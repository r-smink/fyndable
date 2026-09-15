<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SaasProxyClient
 *
 * Communicates with the Fyndable SaaS Dashboard (portal.fyndable.ai) for:
 *  - License activation & validation (/license/activate, /tenant/status)
 *  - AI content generation (/ai/generate)
 *  - SERP queries (/serp/query, /serp/rank-check, /serp/local-pack, /serp/local-grid)
 *
 * This is the Shopify-app equivalent of the WordPress DashboardAPI + LlmClient.
 * The same SaaS endpoints are reused; only the client wrapper differs.
 */
class SaasProxyClient
{
    private string $dashboardUrl;

    private string $namespace;

    public function __construct()
    {
        $this->dashboardUrl = rtrim(config('shopify.saas_dashboard_url'), '/');
        $this->namespace = config('shopify.saas_api_namespace', 'ai-seo-saas/v1');
    }

    /**
     * Activate a Fyndable license for a Shopify shop.
     *
     * @param  string  $licenseKey  The Fyndable license key (uppercase).
     * @param  string  $shopDomain  The Shopify shop domain (e.g. store.myshopify.com).
     * @param  string  $shopName  Optional shop display name.
     * @return array{success: bool, tenant_key?: string, tier?: string, ...}|array{error: string}
     */
    public function activateLicense(string $licenseKey, string $shopDomain, string $shopName = ''): array
    {
        $licenseKey = strtoupper(trim($licenseKey));
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/license/activate";

        try {
            $response = Http::timeout(60)
                ->post($endpoint, [
                    'license_key' => $licenseKey,
                    'site_url' => "https://{$shopDomain}",
                    'site_name' => $shopName ?: $shopDomain,
                    'platform' => 'shopify',
                ]);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: license activation failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'activation_failed', 'status' => $response->status()];
        }

        $body = $response->json();
        if (! is_array($body) || ! ($body['success'] ?? false)) {
            return ['error' => $body['message'] ?? 'activation_failed'];
        }

        return $body;
    }

    /**
     * Validate a tenant's license status (cached 1h by caller).
     *
     * @param  string  $licenseKey
     * @param  string  $tenantKey
     * @return array{valid: bool, tier?: string, ...}|array{error: string}
     */
    public function validateLicense(string $licenseKey, string $tenantKey): array
    {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/tenant/status";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ])
                ->post($endpoint, [
                    'license_key' => $licenseKey,
                    'tenant_key' => $tenantKey,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('SaasProxyClient: license validation failed (network)', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'validation_failed', 'status' => $response->status()];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return ['error' => 'invalid_response'];
        }

        return [
            'valid' => ($body['status'] ?? '') === 'active',
            'tier' => $body['tier'] ?? 'free',
            'data' => $body,
        ];
    }

    /**
     * Generate AI content via the SaaS dashboard proxy.
     *
     * @param  string  $licenseKey
     * @param  string  $tenantKey
     * @param  array  $messages  OpenAI-style messages array.
     * @param  string  $model  Model identifier (e.g. "openai/gpt-4o-mini").
     * @param  int  $maxTokens
     * @param  float  $temperature
     * @param  string  $useCase  Tracking label (e.g. "product_description").
     * @return array{text: string, model: string, usage: array}|array{error: string}
     */
    public function aiGenerate(
        string $licenseKey,
        string $tenantKey,
        array $messages,
        string $model = 'openai/gpt-4o-mini',
        int $maxTokens = 2000,
        float $temperature = 0.7,
        string $useCase = 'content_generation'
    ): array {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/ai/generate";

        try {
            $response = Http::timeout(300)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'messages' => $messages,
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'use_case' => $useCase,
                ]);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: AI generate failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            $body = $response->json();

            return ['error' => $body['message'] ?? 'ai_failed', 'status' => $response->status()];
        }

        $body = $response->json();

        return [
            'text' => $body['text'] ?? $body['content'] ?? '',
            'model' => $body['model'] ?? $model,
            'usage' => $body['usage'] ?? [],
        ];
    }

    /**
     * Check a keyword ranking via the SaaS dashboard SERP proxy.
     *
     * @param  string  $licenseKey
     * @param  string  $tenantKey
     * @param  string  $keyword
     * @param  string  $url  The URL to find in SERP results.
     * @param  string  $country
     * @param  string  $language
     * @return array{position?: int, results?: array, ...}|array{error: string}
     */
    public function serpRankCheck(
        string $licenseKey,
        string $tenantKey,
        string $keyword,
        string $url,
        string $country = 'us',
        string $language = 'en'
    ): array {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/serp/rank-check";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'keyword' => $keyword,
                    'target_url' => $url,
                    'country' => $country,
                    'language' => $language,
                ]);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: SERP rank-check failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'serp_failed', 'status' => $response->status()];
        }

        return $response->json();
    }

    /**
     * Run a SERP query (for SERP feature tracking / competitor analysis).
     *
     * @param  string  $licenseKey
     * @param  string  $tenantKey
     * @param  string  $keyword
     * @param  string  $country
     * @param  string  $language
     * @return array|array{error: string}
     */
    public function serpQuery(
        string $licenseKey,
        string $tenantKey,
        string $keyword,
        string $country = 'us',
        string $language = 'en'
    ): array {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/serp/query";

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'keyword' => $keyword,
                    'country' => $country,
                    'language' => $language,
                ]);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: SERP query failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'serp_failed', 'status' => $response->status()];
        }

        return $response->json();
    }

    /**
     * Run a local pack / geo-grid scan.
     *
     * @param  string  $licenseKey
     * @param  string  $tenantKey
     * @param  array  $params  {keyword, latitude, longitude, radius, grid_size, ...}
     * @param  bool  $grid  Whether to use the grid endpoint (vs local-pack).
     * @return array|array{error: string}
     */
    public function localSerpScan(string $licenseKey, string $tenantKey, array $params, bool $grid = false): array
    {
        $route = $grid ? 'serp/local-grid' : 'serp/local-pack';
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/{$route}";

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $params);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: local SERP failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'local_serp_failed', 'status' => $response->status()];
        }

        return $response->json();
    }
}

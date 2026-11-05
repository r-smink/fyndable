<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SaasProxyClient
 *
 * Communicates with the Fyndable SaaS Dashboard (portal.fyndable.ai) for:
 *  - License validation (/tenant/status) and the signed App Pricing sync
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
     * Push a shop's Shopify App Pricing state to the SaaS dashboard, which
     * creates or updates the shop's tenant + license (platform=shopify).
     *
     * Signed with HMAC-SHA256 over "{timestamp}.{body}" using the shared
     * secret (X-Fyndable-Timestamp / X-Fyndable-Signature).
     *
     * @param  array  $payload  shop_domain, shop_name, shop_email, state, tier, plan_handle, trial_ends_at, cycle_ends_at, cancel_at_end_of_cycle
     * @return array{success: true, tenant_key: ?string, license_key: ?string, tier: ?string, status: string}|array{error: string}
     */
    public function syncShopifySubscription(array $payload): array
    {
        $secret = (string) config('shopify.billing.sync_secret');
        if ($secret === '') {
            return ['error' => 'sync_not_configured'];
        }

        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/shopify/subscription";
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();

        try {
            $response = Http::timeout(30)
                ->withHeaders(self::signatureHeaders($body, $timestamp, $secret))
                ->withBody($body, 'application/json')
                ->post($endpoint);
        } catch (ConnectionException $e) {
            Log::warning('SaasProxyClient: subscription sync failed (network)', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        $json = $response->json();
        if ($response->failed() || ! is_array($json) || ! ($json['success'] ?? false)) {
            Log::warning('SaasProxyClient: subscription sync rejected', [
                'status' => $response->status(),
                'error' => is_array($json) ? ($json['error'] ?? null) : null,
            ]);

            return ['error' => is_array($json) ? ($json['error'] ?? 'sync_failed') : 'sync_failed'];
        }

        return $json;
    }

    /**
     * GDPR: delete/anonymize the tenant on the SaaS dashboard (shop/redact).
     *
     * @return array{success?: bool, error?: string}
     */
    public function gdprDelete(string $licenseKey, string $tenantKey): array
    {
        return $this->post('gdpr/delete', $licenseKey, $tenantKey, [
            'license_key' => $licenseKey,
            'tenant_key' => $tenantKey,
            'confirm' => 'DELETE',
        ], 30);
    }

    /**
     * Signature headers for server-to-server calls to the SaaS dashboard.
     *
     * @return array{X-Fyndable-Timestamp: string, X-Fyndable-Signature: string}
     */
    public static function signatureHeaders(string $body, string $timestamp, string $secret): array
    {
        return [
            'X-Fyndable-Timestamp' => $timestamp,
            'X-Fyndable-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
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
            $body = $response->json() ?? [];
            Log::error('SaasProxyClient: SERP rank-check failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return [
                'error' => 'serp_failed',
                'status' => $response->status(),
                'message' => $body['message'] ?? $body['error'] ?? null,
            ];
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

    /**
     * Backlinks summary for a domain/URL (DataForSEO via SaaS).
     *
     * @param  string  $target  Domain or URL (e.g. "example.com").
     */
    public function backlinksSummary(string $licenseKey, string $tenantKey, string $target): array
    {
        return $this->post('backlinks/summary', $licenseKey, $tenantKey, ['target' => $target]);
    }

    /**
     * Live backlink list for a domain/URL.
     */
    public function backlinksLive(string $licenseKey, string $tenantKey, string $target, int $limit = 50): array
    {
        return $this->post('backlinks/live', $licenseKey, $tenantKey, [
            'target' => $target,
            'limit' => $limit,
        ]);
    }

    /**
     * AI Mentions (LLM visibility) — DataForSEO AI Optimization API.
     *
     * @param  string  $action  e.g. search_mentions, target_metrics, top_domains, top_pages
     * @param  array  $params  Action-specific params (target, domain, etc.)
     */
    public function llmMentions(string $licenseKey, string $tenantKey, string $action, array $params = []): array
    {
        return $this->post('ai/llm-mentions', $licenseKey, $tenantKey, [
            'action' => $action,
            'params' => $params,
        ]);
    }

    /**
     * Live LLM response (ChatGPT/Claude/Gemini/Perplexity) — DataForSEO.
     *
     * @param  string  $provider  chatgpt|claude|gemini|perplexity
     */
    public function llmResponse(string $licenseKey, string $tenantKey, string $provider, string $prompt, array $extra = []): array
    {
        return $this->post('ai/llm-response', $licenseKey, $tenantKey, [
            'provider' => $provider,
            'prompt' => $prompt,
        ] + $extra);
    }

    /**
     * AI Keyword Data (search volume etc.) — DataForSEO.
     *
     * @param  array<int, string>  $keywords
     */
    public function keywordData(string $licenseKey, string $tenantKey, array $keywords, ?int $locationCode = null, ?string $languageCode = null): array
    {
        $body = ['keywords' => array_values($keywords)];
        if ($locationCode !== null) {
            $body['location_code'] = $locationCode;
        }
        if ($languageCode !== null) {
            $body['language_code'] = $languageCode;
        }

        return $this->post('ai/keyword-data', $licenseKey, $tenantKey, $body);
    }

    /**
     * Generate an image via the SaaS /ai/image endpoint (OpenAI images API).
     *
     * @return array{success?: bool, url?: string, ...}|array{error: string}
     */
    public function aiImage(string $licenseKey, string $tenantKey, string $prompt, string $size = '1024x1024'): array
    {
        return $this->post('ai/image', $licenseKey, $tenantKey, [
            'prompt' => $prompt,
            'size' => $size,
        ], 180);
    }

    // -----------------------------------------------------------------------
    //  GEO Scan (AI Visibility)
    // -----------------------------------------------------------------------

    /**
     * Start an async GEO scan via the SaaS dashboard.
     *
     * @param  string  $url  The shop's URL to scan.
     * @param  string  $keywords  Comma-separated keywords (1-2).
     * @return array{success?: bool, scan_id?: int}|array{error: string}
     */
    public function geoScan(string $licenseKey, string $tenantKey, string $url, string $keywords): array
    {
        return $this->post('geo-scan/start', $licenseKey, $tenantKey, [
            'url' => $url,
            'keywords' => $keywords,
        ], 90);
    }

    /**
     * Poll GEO scan status / results.
     */
    public function geoScanStatus(string $licenseKey, string $tenantKey, int $scanId): array
    {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/geo-scan/{$scanId}/status";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ])
                ->get($endpoint);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: GEO scan status failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'geo_scan_status_failed', 'status' => $response->status()];
        }

        return $response->json() ?? [];
    }

    /**
     * Get recent GEO scans for a tenant.
     */
    public function geoScanRecent(string $licenseKey, string $tenantKey, int $limit = 10): array
    {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/geo-scan/recent";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                ])
                ->get($endpoint, ['limit' => $limit]);
        } catch (ConnectionException $e) {
            Log::error('SaasProxyClient: GEO scan recent failed', ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            return ['error' => 'geo_scan_recent_failed', 'status' => $response->status()];
        }

        return $response->json() ?? [];
    }

    // -----------------------------------------------------------------------
    //  Keyword Explorer
    // -----------------------------------------------------------------------

    /**
     * Expand a seed keyword into related keywords via the SaaS keyword expand endpoint.
     *
     * @return array{keywords?: array, ...}|array{error: string}
     */
    public function keywordExpand(string $licenseKey, string $tenantKey, string $seed, string $country = 'us', string $language = 'en'): array
    {
        return $this->post('keywords/expand', $licenseKey, $tenantKey, [
            'seed' => $seed,
            'country' => $country,
            'language' => $language,
        ], 90);
    }

    /**
     * Cluster a list of keywords by SERP similarity.
     *
     * @param  array<int, string>  $keywords
     */
    public function keywordCluster(string $licenseKey, string $tenantKey, array $keywords): array
    {
        return $this->post('keywords/cluster', $licenseKey, $tenantKey, [
            'keywords' => array_values($keywords),
        ], 90);
    }

    /**
     * Google Trends data for keywords.
     *
     * @param  array<int, string>  $keywords
     */
    public function googleTrends(string $licenseKey, string $tenantKey, array $keywords): array
    {
        return $this->post('keywords/google-trends', $licenseKey, $tenantKey, [
            'keywords' => array_values($keywords),
        ]);
    }

    /**
     * Shared POST helper for SaaS endpoints.
     */
    /**
     * Google OAuth for Search Console (1.5). The SaaS keeps the Google client
     * secret; these calls never log tokens.
     */
    public function googleOAuthSession(string $licenseKey, string $tenantKey): array
    {
        return $this->post('google/oauth-session', $licenseKey, $tenantKey, ['license_key' => $licenseKey, 'tenant_key' => $tenantKey], 20);
    }

    public function googleExchange(string $licenseKey, string $tenantKey, string $code): array
    {
        return $this->post('google/exchange', $licenseKey, $tenantKey, ['license_key' => $licenseKey, 'tenant_key' => $tenantKey, 'code' => $code], 20);
    }

    public function googleRefresh(string $licenseKey, string $tenantKey, string $refreshToken): array
    {
        return $this->post('google/refresh', $licenseKey, $tenantKey, ['license_key' => $licenseKey, 'tenant_key' => $tenantKey, 'refresh_token' => $refreshToken], 20);
    }

    public function googleReportUsage(string $licenseKey, string $tenantKey, string $service, int $calls): array
    {
        return $this->post('google/report-usage', $licenseKey, $tenantKey, ['license_key' => $licenseKey, 'tenant_key' => $tenantKey, 'service' => $service, 'calls' => $calls], 10);
    }

    private function post(string $route, string $licenseKey, string $tenantKey, array $body, int $timeout = 60): array
    {
        $endpoint = "{$this->dashboardUrl}/wp-json/{$this->namespace}/{$route}";

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-License-Key' => $licenseKey,
                    'X-Tenant-Key' => $tenantKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $body);
        } catch (ConnectionException $e) {
            Log::error("SaasProxyClient: {$route} failed", ['error' => $e->getMessage()]);

            return ['error' => 'connection_failed'];
        }

        if ($response->failed()) {
            $json = $response->json() ?? [];
            Log::error("SaasProxyClient: {$route} failed", [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return [
                'error' => 'saas_request_failed',
                'status' => $response->status(),
                'message' => $json['message'] ?? $json['error'] ?? null,
                'details' => $json['details'] ?? null,
            ];
        }

        return $response->json() ?? [];
    }
}

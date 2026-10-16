<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\SaasProxyClient;
use Illuminate\Http\Request;

/**
 * InsightsController
 *
 * Backlink analysis, LLM visibility checks and keyword data — all proxied
 * through the Fyndable SaaS dashboard (DataForSEO under the hood).
 */
class InsightsController extends Controller
{
    public function __construct(
        private SaasProxyClient $saas,
        private LicenseService $license
    ) {}

    /**
     * Backlink summary for a domain or URL.
     *
     * GET /api/insights/backlinks?target=example.com&live=1&limit=50
     */
    public function backlinks(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $target = trim((string) $request->query('target', '')) ?: $shop->shop_domain;
        $live = $request->boolean('live');
        $limit = max(1, min((int) $request->query('limit', 50), 100));

        $result = $live
            ? $this->saas->backlinksLive($shop->license_key, $shop->tenant_key, $target, $limit)
            : $this->saas->backlinksSummary($shop->license_key, $shop->tenant_key, $target);

        return $result + ['target' => $target];
    }

    /**
     * AI Mentions — LLM visibility data for a brand/domain.
     *
     * POST /api/insights/llm-mentions
     * Body: { action: "search_mentions"|"target_metrics"|..., params: {...} }
     */
    public function llmMentions(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $action = trim((string) $request->input('action', 'search_mentions'));
        $params = $request->input('params', []);
        if (! is_array($params)) {
            $params = [];
        }

        return $this->saas->llmMentions($shop->license_key, $shop->tenant_key, $action, $params);
    }

    /**
     * Ask an LLM a question live and inspect the answer for brand visibility.
     *
     * POST /api/insights/llm-check
     * Body: { prompt, provider?: chatgpt|claude|gemini|perplexity }
     */
    public function llmCheck(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $prompt = trim((string) $request->input('prompt', ''));
        $provider = trim((string) $request->input('provider', 'chatgpt'));

        if ($prompt === '') {
            return ['error' => 'prompt_required'];
        }

        if (! in_array($provider, ['chatgpt', 'claude', 'gemini', 'perplexity'], true)) {
            $provider = 'chatgpt';
        }

        return $this->saas->llmResponse($shop->license_key, $shop->tenant_key, $provider, $prompt);
    }

    /**
     * Keyword search volume data (DataForSEO AI keyword data).
     *
     * POST /api/insights/keyword-data
     * Body: { keywords: [...], location_code?, language_code? }
     */
    public function keywordData(Request $request): array
    {
        $error = $this->guardLicensed($request);
        if ($error) {
            return $error;
        }

        $shop = $request->attributes->get('shop');
        $keywords = array_values(array_filter(array_map(
            fn ($k) => trim((string) $k),
            (array) $request->input('keywords', [])
        )));

        if (empty($keywords)) {
            return ['error' => 'keywords_required'];
        }

        return $this->saas->keywordData(
            $shop->license_key,
            $shop->tenant_key,
            $keywords,
            $request->input('location_code') ? (int) $request->input('location_code') : null,
            $request->input('language_code') ? (string) $request->input('language_code') : null
        );
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

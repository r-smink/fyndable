<?php

namespace App\Services;

/**
 * AiTrackerService
 *
 * Aggregates LLM visibility data into an AI Optimization Tracker overview:
 * total mentions, timeseries trends, multi-provider brand checks, and
 * AI-generated recommendations. DataForSEO responses are reduced to the
 * fields the dashboard shows (DataForSeoFormat).
 */
class AiTrackerService
{
    public function __construct(private SaasProxyClient $saas) {}

    /**
     * Build an overview combining target metrics, top pages and top brands.
     */
    public function overview(string $licenseKey, string $tenantKey, string $target): array
    {
        $metrics = $this->saas->llmMentions($licenseKey, $tenantKey, 'target_metrics', [
            'target' => $target,
        ]);

        $topPages = $this->saas->llmMentions($licenseKey, $tenantKey, 'top_pages', [
            'target' => $target,
        ]);

        $topBrands = $this->saas->llmMentions($licenseKey, $tenantKey, 'top_brands', [
            'target' => $target,
        ]);

        return [
            'target' => $target,
            'metrics' => DataForSeoFormat::failed($metrics) ? null : DataForSeoFormat::targetMetrics($metrics),
            'top_pages' => DataForSeoFormat::failed($topPages) ? [] : DataForSeoFormat::topItems($topPages),
            'top_brands' => DataForSeoFormat::failed($topBrands) ? [] : DataForSeoFormat::topItems($topBrands),
        ];
    }

    /**
     * Change in mentions and AI search volume per period.
     */
    public function timeseries(string $licenseKey, string $tenantKey, string $target): array
    {
        $delta = $this->saas->llmMentions($licenseKey, $tenantKey, 'timeseries_delta', [
            'target' => $target,
        ]);

        if (DataForSeoFormat::failed($delta)) {
            return $delta + ['target' => $target];
        }

        return [
            'target' => $target,
            'points' => DataForSeoFormat::timeseriesDelta($delta),
        ];
    }

    /**
     * Check brand visibility across all 4 LLM providers.
     *
     * @param  array<int, string>  $brandTerms  store name/domain terms for the "mentioned" flag
     */
    public function multiProviderCheck(string $licenseKey, string $tenantKey, string $prompt, array $brandTerms = []): array
    {
        $providers = ['chatgpt', 'claude', 'gemini', 'perplexity'];
        $results = [];

        foreach ($providers as $provider) {
            $response = $this->saas->llmResponse($licenseKey, $tenantKey, $provider, $prompt);
            $results[$provider] = DataForSeoFormat::failed($response)
                ? ['error' => $response['message'] ?? $response['error'] ?? 'request_failed']
                : DataForSeoFormat::llmAnswer($response, $brandTerms);
        }

        return ['prompt' => $prompt, 'results' => $results];
    }

    /**
     * Generate AI-powered recommendations based on mention data.
     */
    public function recommendations(string $licenseKey, string $tenantKey, string $target): array
    {
        $metrics = $this->saas->llmMentions($licenseKey, $tenantKey, 'target_metrics', [
            'target' => $target,
        ]);

        if (DataForSeoFormat::failed($metrics)) {
            return ['error' => 'no_data_available'];
        }

        // Only the aggregated numbers, not the raw DataForSEO envelope.
        $metricsJson = json_encode(DataForSeoFormat::targetMetrics($metrics), JSON_UNESCAPED_UNICODE);

        $result = $this->saas->aiGenerate($licenseKey, $tenantKey, [
            [
                'role' => 'system',
                'content' => 'You are an AI search visibility expert. Analyse the LLM mention metrics and provide 3-5 actionable recommendations to improve the site\'s AI visibility. Be specific and concise. Respond in JSON: {"recommendations": [{"title": "...", "description": "...", "priority": "high|medium|low"}]}',
            ],
            [
                'role' => 'user',
                'content' => "AI visibility metrics for {$target}:\n{$metricsJson}",
            ],
        ], 'openai/gpt-4o-mini', 1000, 0.5, 'ai_tracker_recommendations');

        if (isset($result['error'])) {
            return $result;
        }

        $text = $result['text'] ?? '';
        // Models sometimes wrap the JSON in a ```json fence.
        $decoded = json_decode(preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', $text), true);

        return $decoded ?: ['recommendations' => [], 'raw' => $text];
    }
}

<?php

namespace App\Services;

/**
 * AiTrackerService
 *
 * Aggregates LLM visibility data into an AI Optimization Tracker overview:
 * total mentions, timeseries trends, multi-provider brand checks, and
 * AI-generated recommendations.
 */
class AiTrackerService
{
    public function __construct(private SaasProxyClient $saas) {}

    /**
     * Build an overview combining target metrics and top pages.
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
            'metrics' => isset($metrics['error']) ? null : $metrics,
            'top_pages' => isset($topPages['error']) ? null : $topPages,
            'top_brands' => isset($topBrands['error']) ? null : $topBrands,
        ];
    }

    /**
     * Get timeseries data for AI visibility trends.
     */
    public function timeseries(string $licenseKey, string $tenantKey, string $target): array
    {
        $delta = $this->saas->llmMentions($licenseKey, $tenantKey, 'timeseries_delta', [
            'target' => $target,
        ]);

        $newLost = $this->saas->llmMentions($licenseKey, $tenantKey, 'timeseries_new_and_lost', [
            'target' => $target,
        ]);

        return [
            'target' => $target,
            'delta' => isset($delta['error']) ? null : $delta,
            'new_and_lost' => isset($newLost['error']) ? null : $newLost,
        ];
    }

    /**
     * Check brand visibility across all 4 LLM providers simultaneously.
     */
    public function multiProviderCheck(string $licenseKey, string $tenantKey, string $prompt): array
    {
        $providers = ['chatgpt', 'claude', 'gemini', 'perplexity'];
        $results = [];

        foreach ($providers as $provider) {
            $response = $this->saas->llmResponse($licenseKey, $tenantKey, $provider, $prompt);
            $results[$provider] = isset($response['error'])
                ? ['error' => $response['error']]
                : $response;
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

        if (isset($metrics['error'])) {
            return ['error' => 'no_data_available'];
        }

        $metricsJson = json_encode($metrics, JSON_UNESCAPED_UNICODE);

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
        $decoded = json_decode($text, true);

        return $decoded ?: ['recommendations' => [], 'raw' => $text];
    }
}

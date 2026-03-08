<?php

namespace AISEOAssistant;

use AISEOAssistant\Providers\SerpProviderInterface;
use WP_Error;

class SerpService
{
    public const CRON_HOOK = 'aiseoassistant_serp_snapshot';

    private Settings $settings;
    private SnapshotRepository $snapshots;
    private HealthLogger $health;

    /** @var array<string, SerpProviderInterface> */
    private array $providers;

    public function __construct(Settings $settings, array $providers, SnapshotRepository $snapshots, HealthLogger $health)
    {
        $this->settings = $settings;
        $this->providers = $providers;
        $this->snapshots = $snapshots;
        $this->health = $health;
    }

    /**
     * Fetch SERP data for a keyword using the active provider (+ optional fallback).
     * @return array{provider:string,results:array}|WP_Error
     */
    public function fetch(string $keyword, bool $preferScrape = false)
    {
        $primary = $this->settings->get('provider', 'api');
        $order = $this->buildProviderOrder($primary, (bool)$this->settings->get('fallback_enabled', false), $preferScrape);
        $lastError = null;

        foreach ($order as $key) {
            $provider = $this->providers[$key] ?? null;
            if (!$provider) {
                continue;
            }
            $result = $provider->search($keyword, [
                'country' => $this->settings->get('country', 'us'),
            ]);
            if (is_wp_error($result)) {
                $lastError = $result;
                continue;
            }
            if (is_array($result) && count($result) > 0) {
                // Normalize structure for scrape provider (which wraps results)
                if (isset($result['results'])) {
                    return [
                        'provider' => $key,
                        'results'  => $result['results'],
                        'ai_overview' => $result['ai_overview'] ?? false,
                        'ai_citations' => $result['ai_citations'] ?? [],
                    ];
                }
                return [
                    'provider' => $key,
                    'results'  => $result,
                ];
            }
        }

        return $lastError ?: new WP_Error('no_results', __('No results from any provider', 'ai-seo-assistant'));
    }

    /**
     * Example cron callback for periodic tracking.
     */
    public function scheduledSnapshot(): void
    {
        $keywords = apply_filters('aiseoassistant_tracked_keywords', []);
        if (empty($keywords)) {
            return;
        }

        foreach ($keywords as $keyword) {
            $result = $this->fetch($keyword);
            if (!is_wp_error($result)) {
                $this->snapshots->save($keyword, $result['provider'], $result['results']);
                $this->maybeAlertPerformance($keyword, $result['results']);
            }
            do_action('aiseoassistant_snapshot_saved', $keyword, $result);
        }
    }

    /**
     * Simple healthcheck runner.
     */
    public function healthcheck(string $providerKey): array|WP_Error
    {
        $provider = $this->providers[$providerKey] ?? null;
        if (!$provider) {
            return new WP_Error('invalid_provider', __('Unknown provider', 'ai-seo-assistant'));
        }
        $res = $provider->search('test keyword', [
            'country' => $this->settings->get('country', 'us'),
        ]);
        if (is_wp_error($res)) {
            return $res;
        }
        return [
            'ok' => !empty($res),
            'count' => is_array($res) ? count($res) : 0,
        ];
    }

    /**
     * Cron healthcheck for all providers.
     */
    public function runHealthcheckJob(): void
    {
        foreach (array_keys($this->providers) as $provider) {
            $hc = $this->healthcheck($provider);
            if (is_wp_error($hc)) {
                $this->health->log('serp', $provider, 'error', $hc->get_error_message());
            } else {
                $this->health->log('serp', $provider, 'ok', sprintf('OK (%d resultaten)', $hc['count']));
            }
        }
    }

    /**
     * Build ordered provider list, deduped.
     */
    private function buildProviderOrder(string $primary, bool $fallbackEnabled, bool $preferScrape = false): array
    {
        $priority = ['api', 'dataforseo', 'scrape'];
        if (!$fallbackEnabled) {
            return $preferScrape ? ['scrape', $primary] : [$primary];
        }
        // Start with primary, then priority excluding primary.
        $ordered = array_merge([$primary], array_diff($priority, [$primary]));
        if ($preferScrape && !in_array('scrape', $ordered, true)) {
            array_unshift($ordered, 'scrape');
        }
        // Remove providers that are not registered.
        return array_values(array_filter($ordered, fn($key) => isset($this->providers[$key])));
    }

    private function maybeAlertPerformance(string $keyword, array $results): void
    {
        $threshold = $this->settings->perfThreshold();
        $best = null;
        foreach ($results as $item) {
            if (isset($item['position'])) {
                $best = $best === null ? $item['position'] : min($best, $item['position']);
            }
        }
        if ($best !== null && $best > $threshold) {
            $this->health->log('serp', 'perf', 'warn', sprintf('Keyword "%s" avg best #%d over threshold #%d', $keyword, $best, $threshold));
        }
    }
}

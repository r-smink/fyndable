<?php

namespace SSEOAISaaS;

/**
 * AI Overview Extractor
 *
 * Fetches Google SERP data via SerpApi and extracts AI Overview presence,
 * cited sources and the organic top 10 for citation comparison.
 */
class AiOverviewExtractor
{
    private SaaSSettings $settings;

    public function __construct(SaaSSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get AI Overview data for a single keyword.
     *
     * @return array|\WP_Error Normalized result with has_ai_overview, ai_text, ai_sources, organic_top.
     */
    public function getForKeyword(string $keyword, string $language = 'nl'): array|\WP_Error
    {
        $apiKey = $this->settings->getSerpApiKeyForProvider('serpapi');
        if (empty($apiKey)) {
            return new \WP_Error('serpapi_not_configured', __('SerpApi key is not configured', 'sseo-ai-saas'));
        }

        $locale = $this->getLocale($language);

        $url = add_query_arg([
            'q'             => $keyword,
            'location'      => $locale['location'],
            'google_domain' => $locale['domain'],
            'gl'            => $locale['gl'],
            'hl'            => $locale['hl'],
            'api_key'       => $apiKey,
            'output'        => 'json',
        ], 'https://serpapi.com/search');

        $response = wp_remote_get($url, ['timeout' => 60]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 || !empty($body['error'])) {
            $message = is_string($body['error'] ?? '') ? $body['error'] : __('Unknown SerpApi error', 'sseo-ai-saas');
            return new \WP_Error('serpapi_error', $message);
        }

        return $this->parse($body, $keyword);
    }

    private function parse(array $body, string $keyword): array
    {
        $aiOverview = $body['ai_overview'] ?? [];
        $hasAiOverview = !empty($aiOverview);

        $text = '';
        $sources = [];

        if ($hasAiOverview) {
            if (is_string($aiOverview)) {
                $text = $aiOverview;
            } elseif (is_array($aiOverview)) {
                $text = $this->extractText($aiOverview);
                $sources = $this->extractSources($aiOverview);
            }
        }

        $organic = [];
        foreach ($body['organic_results'] ?? [] as $item) {
            $organic[] = [
                'title'   => $item['title'] ?? '',
                'url'     => $item['link'] ?? $item['url'] ?? '',
                'snippet' => $item['snippet'] ?? '',
            ];
        }

        return [
            'keyword'         => $keyword,
            'has_ai_overview' => $hasAiOverview,
            'ai_text'         => $text,
            'ai_sources'      => $sources,
            'organic_top'     => array_slice($organic, 0, 10),
        ];
    }

    private function extractText(array $overview): string
    {
        if (isset($overview['text']) && is_string($overview['text'])) {
            return $overview['text'];
        }

        if (isset($overview['snippet']) && is_string($overview['snippet'])) {
            return $overview['snippet'];
        }

        $parts = [];

        if (isset($overview['title']) && is_string($overview['title'])) {
            $parts[] = $overview['title'];
        }

        if (isset($overview['bullets']) && is_array($overview['bullets'])) {
            foreach ($overview['bullets'] as $bullet) {
                if (is_string($bullet)) {
                    $parts[] = $bullet;
                } elseif (is_array($bullet) && isset($bullet['text']) && is_string($bullet['text'])) {
                    $parts[] = $bullet['text'];
                }
            }
        }

        return implode("\n", array_filter($parts));
    }

    private function extractSources(array $overview): array
    {
        $sources = [];
        $candidates = [
            $overview['inline_links'] ?? [],
            $overview['sources'] ?? [],
            $overview['inline_citations'] ?? [],
            $overview['citations'] ?? [],
        ];

        foreach ($candidates as $links) {
            if (!is_array($links)) {
                continue;
            }

            foreach ($links as $link) {
                if (is_string($link)) {
                    $sources[] = ['url' => $link, 'title' => ''];
                    continue;
                }

                if (!is_array($link)) {
                    continue;
                }

                $url = $link['url'] ?? $link['link'] ?? $link['source'] ?? '';
                $title = $link['title'] ?? $link['name'] ?? $link['text'] ?? '';

                if (!empty($url)) {
                    $sources[] = ['url' => $url, 'title' => $title];
                }
            }
        }

        return $sources;
    }

    private function getLocale(string $language): array
    {
        $hl = in_array($language, ['nl', 'en'], true) ? $language : 'nl';

        return [
            'location' => 'Netherlands',
            'domain'   => 'google.nl',
            'gl'       => 'nl',
            'hl'       => $hl,
        ];
    }
}

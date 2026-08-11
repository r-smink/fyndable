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
    private ?DataForSeoClient $dataForSeoClient = null;

    public function __construct(SaaSSettings $settings, ?DataForSeoClient $dataForSeoClient = null)
    {
        $this->settings = $settings;
        $this->dataForSeoClient = $dataForSeoClient;
    }

    /**
     * Get AI Overview data for a single keyword.
     *
     * When the DataForSEO AI Overview toggle is enabled and the client is
     * configured, DataForSEO is tried first. SerpApi remains as fallback.
     *
     * @return array|\WP_Error Normalized result with has_ai_overview, ai_text, ai_sources, organic_top.
     */
    public function getForKeyword(string $keyword, string $language = 'nl'): array|\WP_Error
    {
        // Try DataForSEO first when enabled
        if ($this->settings->isDataForSeoAiOverviewEnabled() && $this->dataForSeoClient && $this->dataForSeoClient->isConfigured()) {
            $dfsResult = $this->getForKeywordViaDataForSeo($keyword, $language);
            if (!is_wp_error($dfsResult)) {
                $dfsResult['provider'] = 'dataforseo';
                return $dfsResult;
            }
            // Fall through to SerpApi on error
        }

        return $this->getForKeywordViaSerpApi($keyword, $language);
    }

    /**
     * Get AI Overview data via SerpApi (original implementation).
     */
    public function getForKeywordViaSerpApi(string $keyword, string $language = 'nl'): array|\WP_Error
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

        $result = $this->parse($body, $keyword);
        $result['provider'] = 'serpapi';
        return $result;
    }

    /**
     * Get AI Overview-equivalent data via DataForSEO AI Optimization API.
     *
     * Uses LLM Mentions search_mentions to find AI Overview content and
     * cited sources for the given keyword.
     */
    public function getForKeywordViaDataForSeo(string $keyword, string $language = 'nl'): array|\WP_Error
    {
        if (!$this->dataForSeoClient || !$this->dataForSeoClient->isConfigured()) {
            return new \WP_Error('dataforseo_not_configured', __('DataForSEO API is not configured', 'sseo-ai-saas'));
        }

        $locale = $this->getDataForSeoLocale($language);

        $params = [
            'keyword'       => $keyword,
            'location_code' => $locale['location_code'],
            'language_code' => $locale['language_code'],
        ];

        $result = $this->dataForSeoClient->aiMentionsSearchMentions($params);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->parseDataForSeo($result, $keyword);
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

    /**
     * Get DataForSEO location/language codes for the AI Optimization API.
     */
    private function getDataForSeoLocale(string $language): array
    {
        $lang = in_array($language, ['nl', 'en'], true) ? $language : 'nl';

        $map = [
            'nl' => ['location_code' => 2528, 'language_code' => 'nl'], // Netherlands
            'en' => ['location_code' => 2840, 'language_code' => 'en'], // United States
        ];

        return $map[$lang] ?? $map['nl'];
    }

    /**
     * Parse DataForSEO AI Mentions search_mentions response into the same
     * normalized shape as the SerpApi parse() method.
     */
    private function parseDataForSeo(array $response, string $keyword): array
    {
        $tasks = $response['tasks'] ?? [];
        $result = $tasks[0]['result'] ?? [];

        // DataForSEO may return a list of mention items
        $items = $result[0]['items'] ?? $result['items'] ?? [];

        $hasAiOverview = !empty($items);
        $text = '';
        $sources = [];
        $organic = [];

        foreach ($items as $item) {
            // Collect AI-generated text snippets
            $snippet = $item['text'] ?? $item['snippet'] ?? $item['content'] ?? '';
            if (!empty($snippet) && is_string($snippet)) {
                $text .= ($text ? "\n" : '') . $snippet;
            }

            // Collect cited sources
            $url = $item['url'] ?? $item['link'] ?? $item['source'] ?? '';
            $title = $item['title'] ?? $item['name'] ?? '';
            if (!empty($url) && is_string($url)) {
                $sources[] = ['url' => $url, 'title' => is_string($title) ? $title : ''];
            }

            // Some items may be organic results referenced by the AI
            $type = $item['type'] ?? '';
            if ($type === 'organic' || !empty($item['rank_position'])) {
                $organic[] = [
                    'title'   => is_string($title) ? $title : '',
                    'url'     => is_string($url) ? $url : '',
                    'snippet' => is_string($snippet) ? $snippet : '',
                ];
            }
        }

        return [
            'keyword'         => $keyword,
            'has_ai_overview' => $hasAiOverview,
            'ai_text'         => $text,
            'ai_sources'      => $sources,
            'organic_top'     => array_slice($organic, 0, 10),
        ];
    }
}

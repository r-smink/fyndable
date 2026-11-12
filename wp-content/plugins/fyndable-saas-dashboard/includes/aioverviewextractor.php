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

        $lastError = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_remote_get($url, ['timeout' => 30]);

            if (is_wp_error($response)) {
                return $response;
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($statusCode === 200 && empty($body['error'])) {
                // Google often loads the AI Overview separately: SerpApi then only returns a page_token,
                // and the content needs a second request (engine=google_ai_overview).
                $overview = $body['ai_overview'] ?? null;
                if (is_array($overview) && !empty($overview['page_token']) && empty($overview['text_blocks'])) {
                    $full = $this->fetchSerpApiAiOverview((string)$overview['page_token'], $apiKey);
                    if ($full !== null) {
                        $body['ai_overview'] = $full;
                    }
                }
                $result = $this->parse($body, $keyword);
                $result['provider'] = 'serpapi';
                return $result;
            }

            $message = is_string($body['error'] ?? '') ? $body['error'] : __('Unknown SerpApi error', 'sseo-ai-saas');
            $lastError = new \WP_Error('serpapi_error', $message);

            // Retry once on rate limit / too many requests.
            if ($statusCode === 429 || stripos($message, 'rate') !== false || stripos($message, 'limit') !== false) {
                if ($attempt < 2) {
                    sleep(2);
                    continue;
                }
            }

            break;
        }

        return $lastError ?: new \WP_Error('serpapi_error', __('Unknown SerpApi error', 'sseo-ai-saas'));
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

        // The real Google result page incl. the AI Overview. (This used LLM Mentions search_mentions
        // before, which is a database of LLM answers and does not contain Google's AI Overview.)
        $items = $this->dataForSeoClient->serpOrganicLiveAdvanced(
            $keyword,
            (int)$locale['location_code'],
            (string)$locale['language_code'],
            '',
            ['load_async_ai_overview' => true, 'depth' => 10]
        );

        if (is_wp_error($items)) {
            return $items;
        }

        return $this->parseDataForSeoSerp((array)$items, $keyword);
    }

    /**
     * DataForSEO SERP items → normalized shape. The 'ai_overview' item has `markdown` (full text),
     * nested `items` and `references` ({source, domain, url, title, text}).
     */
    private function parseDataForSeoSerp(array $items, string $keyword): array
    {
        $hasAiOverview = false;
        $textParts = [];
        $sources = [];
        $organic = [];

        $collectReferences = function (array $node) use (&$collectReferences, &$sources, &$textParts): void {
            foreach ((array)($node['references'] ?? []) as $reference) {
                if (is_array($reference) && !empty($reference['url'])) {
                    $sources[] = ['url' => (string)$reference['url'], 'title' => (string)($reference['title'] ?? $reference['source'] ?? '')];
                }
            }
            foreach ((array)($node['items'] ?? []) as $child) {
                if (!is_array($child)) {
                    continue;
                }
                foreach (['title', 'text'] as $field) {
                    if (!empty($child[$field]) && is_string($child[$field])) {
                        $textParts[] = $child[$field];
                    }
                }
                $collectReferences($child);
            }
        };

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string)($item['type'] ?? '');
            if ($type === 'ai_overview') {
                $hasAiOverview = true;
                if (!empty($item['markdown']) && is_string($item['markdown'])) {
                    $textParts[] = $item['markdown'];
                }
                $collectReferences($item);
            } elseif ($type === 'organic') {
                $organic[] = [
                    'title'   => (string)($item['title'] ?? ''),
                    'url'     => (string)($item['url'] ?? ''),
                    'snippet' => (string)($item['description'] ?? ''),
                ];
            }
        }

        $unique = [];
        foreach ($sources as $source) {
            $unique[$source['url']] = $source;
        }

        return [
            'keyword'         => $keyword,
            'has_ai_overview' => $hasAiOverview,
            'ai_text'         => implode("\n", array_unique(array_filter($textParts))),
            'ai_sources'      => array_values($unique),
            'organic_top'     => array_slice($organic, 0, 10),
        ];
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

    /** Full AI Overview for a SerpApi page_token, or null when it cannot be loaded. */
    private function fetchSerpApiAiOverview(string $pageToken, string $apiKey): ?array
    {
        $url = add_query_arg([
            'engine'     => 'google_ai_overview',
            'page_token' => $pageToken,
            'api_key'    => $apiKey,
            'output'     => 'json',
        ], 'https://serpapi.com/search');
        $response = wp_remote_get($url, ['timeout' => 30]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body['ai_overview'] ?? null) ? $body['ai_overview'] : null;
    }

    /**
     * Text of SerpApi's text_blocks (paragraphs, lists incl. nested lists, expandable blocks, tables).
     */
    private function textFromBlocks(array $blocks): array
    {
        $parts = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            foreach (['title', 'snippet'] as $field) {
                if (!empty($block[$field]) && is_string($block[$field])) {
                    $parts[] = $block[$field];
                }
            }
            if (!empty($block['list']) && is_array($block['list'])) {
                $parts = array_merge($parts, $this->textFromBlocks($block['list']));
            }
            if (!empty($block['text_blocks']) && is_array($block['text_blocks'])) {
                $parts = array_merge($parts, $this->textFromBlocks($block['text_blocks']));
            }
            if (!empty($block['table']) && is_array($block['table'])) {
                foreach ($block['table'] as $row) {
                    if (is_array($row)) {
                        $parts[] = implode(' | ', array_filter(array_map(static fn($cell) => is_scalar($cell) ? (string)$cell : '', $row)));
                    }
                }
            }
        }
        return $parts;
    }

    private function extractText(array $overview): string
    {
        if (!empty($overview['text_blocks']) && is_array($overview['text_blocks'])) {
            return implode("\n", array_filter($this->textFromBlocks($overview['text_blocks'])));
        }

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
            $overview['references'] ?? [], // SerpApi: {title, link, snippet, source, index}
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
}

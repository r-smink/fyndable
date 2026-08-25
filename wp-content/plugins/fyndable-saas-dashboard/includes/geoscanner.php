<?php

namespace SSEOAISaaS;

/**
 * GEO Scanner
 *
 * Orchestrates the full GEO Readiness scan:
 * - fetch readable page text
 * - get AI Overviews per keyword
 * - run OpenRouter analysis
 * - build and store the report
 */
class GeoScanner
{
    private HtmlFetcher $htmlFetcher;
    private AiOverviewExtractor $aiExtractor;
    private ProviderRouter $providerRouter;
    private SaaSSettings $settings;
    private GeoScanRepository $repository;

    public function __construct(
        HtmlFetcher $htmlFetcher,
        AiOverviewExtractor $aiExtractor,
        ProviderRouter $providerRouter,
        SaaSSettings $settings,
        GeoScanRepository $repository
    ) {
        $this->htmlFetcher = $htmlFetcher;
        $this->aiExtractor = $aiExtractor;
        $this->providerRouter = $providerRouter;
        $this->settings = $settings;
        $this->repository = $repository;
    }

    /**
     * Run a GEO scan for a URL and a set of keywords.
     *
     * @return array|\WP_Error ['scan_id' => int, 'report' => array]
     */
    public function scan(string $url, array $keywords, string $language = 'nl'): array|\WP_Error
    {
        $url = esc_url_raw($url);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', __('Invalid URL provided', 'sseo-ai-saas'));
        }

        $keywords = array_values(array_filter(array_map('trim', $keywords)));

        if (count($keywords) < 1 || count($keywords) > 10) {
            return new \WP_Error('invalid_keywords', __('Provide between 1 and 10 keywords', 'sseo-ai-saas'));
        }

        $language = in_array($language, ['nl', 'en'], true) ? $language : 'nl';

        $htmlResult = $this->htmlFetcher->fetch($url);
        if (is_wp_error($htmlResult)) {
            return $htmlResult;
        }

        $pageText = $htmlResult['text'] ?? '';

        $keywordResults = [];
        foreach ($keywords as $keyword) {
            $res = $this->aiExtractor->getForKeyword($keyword, $language);
            if (is_wp_error($res)) {
                return $res;
            }
            $keywordResults[] = $res;
        }

        $llmResult = $this->analyzeWithLlm($pageText, $keywords, $keywordResults);
        if (is_wp_error($llmResult)) {
            return $llmResult;
        }

        $targetHost = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $report = $this->buildReport($url, $keywords, $language, $htmlResult, $keywordResults, $llmResult, $targetHost);

        $scanId = $this->repository->insert($url, $keywords, $language, $report);

        return [
            'scan_id' => $scanId,
            'report'  => $report,
        ];
    }

    private function analyzeWithLlm(string $pageText, array $keywords, array $keywordResults): array|\WP_Error
    {
        $truncated = mb_substr($pageText, 0, 12000);

        $context = [];
        foreach ($keywordResults as $kr) {
            $context[] = '- ' . $kr['keyword'] . ': AI Overview ' . ($kr['has_ai_overview'] ? 'aanwezig' : 'afwezig');
        }

        $lang = 'nl';
        $prompt = "Analyseer onderstaande webpagina op GEO-readiness (Generative Engine Optimization). 
Beoordeel of de pagina geschikt is om door AI-zoekmachines als bron te worden geciteerd.
Schrijf de output in het Nederlands, in geldig JSON, zonder markdown code blocks, zonder extra tekst buiten de JSON.

Vereiste JSON-structuur:
{
  \"score\": 0-100,
  \"breakdown\": {
    \"direct_answer\": 0-100,
    \"structure\": 0-100,
    \"schema_markup\": 0-100,
    \"entities\": 0-100,
    \"citation_worthiness\": 0-100,
    \"readability\": 0-100,
    \"eeat\": 0-100,
    \"content_freshness\": 0-100,
    \"mobile_friendly\": 0-100,
    \"internal_linking\": 0-100,
    \"page_metadata\": 0-100,
    \"entity_coverage\": 0-100,
    \"competitive_gap\": 0-100
  },
  \"findings\": [\"...\", \"...\"],
  \"recommendations\": [\"...\", \"...\"],
  \"strengths\": [\"...\", \"...\"],
  \"weaknesses\": [\"...\", \"...\"],
  \"priority_ranked_recommendations\": [\"...\", \"...\"]
}

Richtlijnen:
- Genereer minimaal 8 findings met korte, concrete observaties.
- Genereer minimaal 8 recommendations die direct actiegericht en geprioriteerd zijn.
- Geef ook 3-5 strengths en 3-5 weaknesses.
- priority_ranked_recommendations bevat de top 5 recommendations, van hoogste naar laagste prioriteit.
- Zorg dat de tekst geschikt is om aan een prospect te presenteren: professioneel, duidelijk en commercieel vriendelijk.

Paginatekst ( eerste 12000 tekens ):
" . $truncated . "

Belangrijkste zoekwoorden: " . implode(', ', $keywords) . "
AI Overview context:
" . implode("\n", $context);

        $messages = [
            ['role' => 'system', 'content' => 'Je bent een ervaren SEO/GEO-analist. Antwoord altijd in geldig JSON in het Nederlands.'],
            ['role' => 'user', 'content' => $prompt],
        ];

        $model = $this->settings->getGeoModel();
        $result = $this->providerRouter->routeRequest($messages, $model ?: null, 'geo_readiness', 2500, 0.2);

        if (is_wp_error($result)) {
            return $result;
        }

        $content = $result['content'] ?? '';
        $parsed = $this->extractJson($content);

        if (empty($parsed)) {
            return new \WP_Error('llm_json_invalid', __('The AI model did not return a valid JSON response', 'sseo-ai-saas'));
        }

        $parsed['usage'] = $result['usage'] ?? [];

        return $parsed;
    }

    private function extractJson(string $content): ?array
    {
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && isset($decoded['score'])) {
                return $decoded;
            }
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['score'])) {
            return $decoded;
        }

        return null;
    }

    private function buildReport(
        string $url,
        array $keywords,
        string $language,
        array $htmlResult,
        array $keywordResults,
        array $llmResult,
        string $targetHost
    ): array {
        $keywordsAnalysis = [];

        foreach ($keywordResults as $kr) {
            $cited = false;
            $sourceHosts = [];
            foreach ($kr['ai_sources'] as $source) {
                $host = strtolower(parse_url($source['url'], PHP_URL_HOST) ?: '');
                if ($host) {
                    $sourceHosts[] = $host;
                }
                if ($host && $host === $targetHost) {
                    $cited = true;
                }
            }

            $competitors = [];
            foreach ($kr['organic_top'] as $item) {
                $host = strtolower(parse_url($item['url'], PHP_URL_HOST) ?: '');
                if ($host && $host !== $targetHost && !in_array($host, array_column($competitors, 'host'), true)) {
                    $competitors[] = [
                        'host'  => $host,
                        'title' => $item['title'],
                        'url'   => $item['url'],
                    ];
                }
            }

            $keywordsAnalysis[] = [
                'keyword'             => $kr['keyword'],
                'has_ai_overview'     => $kr['has_ai_overview'],
                'ai_text'             => mb_substr($kr['ai_text'], 0, 500),
                'ai_sources_count'    => count($kr['ai_sources']),
                'target_cited'        => $cited,
                'competitor_citations'=> array_slice($competitors, 0, 5),
            ];
        }

        return [
            'url'                           => $url,
            'keywords'                      => $keywords,
            'language'                      => $language,
            'scanned_at'                    => current_time('mysql'),
            'score'                         => (int)($llmResult['score'] ?? 0),
            'breakdown'                     => $llmResult['breakdown'] ?? [],
            'findings'                      => $llmResult['findings'] ?? [],
            'recommendations'               => $llmResult['recommendations'] ?? [],
            'priority_ranked_recommendations'=> $llmResult['priority_ranked_recommendations'] ?? [],
            'strengths'                     => $llmResult['strengths'] ?? [],
            'weaknesses'                    => $llmResult['weaknesses'] ?? [],
            'readability'                   => (int)($llmResult['breakdown']['readability'] ?? 0),
            'eeat'                          => (int)($llmResult['breakdown']['eeat'] ?? 0),
            'content_freshness'             => (int)($llmResult['breakdown']['content_freshness'] ?? 0),
            'mobile_friendly'               => (int)($llmResult['breakdown']['mobile_friendly'] ?? 0),
            'internal_linking'              => (int)($llmResult['breakdown']['internal_linking'] ?? 0),
            'page_metadata'                 => (int)($llmResult['breakdown']['page_metadata'] ?? 0),
            'entity_coverage'               => (int)($llmResult['breakdown']['entity_coverage'] ?? 0),
            'competitive_gap'               => (int)($llmResult['breakdown']['competitive_gap'] ?? 0),
            'keywords_analysis'             => $keywordsAnalysis,
            'page_text_preview'             => mb_substr($htmlResult['text'] ?? '', 0, 500),
            'html_source'                   => $htmlResult['source'] ?? 'unknown',
            'usage'                         => $llmResult['usage'] ?? [],
        ];
    }
}

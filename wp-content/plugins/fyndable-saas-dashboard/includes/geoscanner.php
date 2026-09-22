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
 *
 * Supports progress callbacks for async/queued processing.
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
     * @param string    $language   'nl', 'en' or 'auto' (detected from keywords).
     * @param callable|null $onProgress fn(int $percent, string $label) — for async progress tracking.
     * @param int|null  $scanId     When set, the report is written to this existing
     *                              (queued) row instead of inserting a new one.
     * @return array|\WP_Error ['scan_id' => int, 'report' => array]
     */
    public function scan(string $url, array $keywords, string $language = 'nl', ?callable $onProgress = null, ?int $scanId = null): array|\WP_Error
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        $progress = function (int $percent, string $label) use ($onProgress, $scanId): void {
            if ($onProgress) {
                $onProgress($percent, $label);
            } elseif ($scanId) {
                $this->repository->updateProgress($scanId, $percent, $label);
            }
        };

        $url = esc_url_raw($url);

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', __('Invalid URL provided', 'sseo-ai-saas'));
        }

        $keywords = array_values(array_filter(array_map('trim', $keywords)));

        if (count($keywords) < 1 || count($keywords) > 10) {
            return new \WP_Error('invalid_keywords', __('Provide between 1 and 10 keywords', 'sseo-ai-saas'));
        }

        if ($language === 'auto') {
            $language = $this->detectLanguageFromKeywords($keywords);
        }
        $language = in_array($language, ['nl', 'en'], true) ? $language : 'nl';

        $progress(5, __('Pagina ophalen…', 'sseo-ai-saas'));
        $htmlResult = $this->htmlFetcher->fetch($url);
        if (is_wp_error($htmlResult)) {
            return $htmlResult;
        }

        $pageText = $htmlResult['text'] ?? '';

        $keywordResults = [];
        $failedKeywords = [];
        $keywordCount = count($keywords);
        foreach ($keywords as $index => $keyword) {
            $progress(
                10 + (int) round(($index / $keywordCount) * 60),
                sprintf(__('AI Overview controleren: %s', 'sseo-ai-saas'), $keyword)
            );

            $res = $this->aiExtractor->getForKeyword($keyword, $language);
            if (is_wp_error($res)) {
                $failedKeywords[] = [
                    'keyword' => $keyword,
                    'error'   => $res->get_error_message(),
                ];
                continue;
            }
            $keywordResults[] = $res;

            // Small delay to reduce the chance of SerpApi rate limits when
            // multiple keywords are scanned in quick succession.
            if ($index < $keywordCount - 1) {
                usleep(500000);
            }
        }

        if (empty($keywordResults)) {
            return new \WP_Error('all_keywords_failed', __('All keyword lookups failed. Please check your SERP provider settings and try again.', 'sseo-ai-saas'));
        }

        $progress(75, __('AI-analyse genereren…', 'sseo-ai-saas'));
        $llmResult = $this->analyzeWithLlm($pageText, $keywords, $keywordResults, $language);
        if (is_wp_error($llmResult)) {
            return $llmResult;
        }

        $progress(90, __('Rapport samenstellen…', 'sseo-ai-saas'));
        $targetHost = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $report = $this->buildReport($url, $keywords, $language, $htmlResult, $keywordResults, $failedKeywords, $llmResult, $targetHost);

        if ($scanId) {
            $this->repository->markCompleted($scanId, $report);
        } else {
            $scanId = $this->repository->insert($url, $keywords, $language, $report);
        }

        return [
            'scan_id' => $scanId,
            'report'  => $report,
        ];
    }

    /**
     * Detect keyword language ('nl' or 'en') with a stopword/pattern heuristic.
     * Ties and empty scores fall back to Dutch (primary market).
     */
    public function detectLanguageFromKeywords(array $keywords): string
    {
        $text = mb_strtolower(implode(' ', $keywords));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $nlStopwords = [
            'de', 'het', 'een', 'en', 'van', 'voor', 'met', 'aan', 'op', 'in', 'te',
            'bij', 'uit', 'naar', 'over', 'onder', 'door', 'als', 'of', 'maar',
            'beste', 'goedkoop', 'goedkope', 'kopen', 'prijs', 'prijzen', 'kosten',
            'offerte', 'diensten', 'dienst', 'bedrijf', 'winkel', 'webshop', 'maken',
            'laten', 'werken', 'informatie', 'vacature', 'vergelijken', 'huren',
        ];
        $enStopwords = [
            'the', 'a', 'an', 'and', 'of', 'for', 'with', 'to', 'at', 'on', 'in',
            'by', 'from', 'about', 'or', 'but', 'as', 'is', 'are',
            'best', 'cheap', 'cheapest', 'buy', 'price', 'prices', 'cost', 'costs',
            'quote', 'services', 'service', 'company', 'shop', 'store', 'how',
            'what', 'where', 'near', 'me', 'hire', 'top', 'review', 'reviews',
            'guide', 'vs', 'versus', 'compare', 'free', 'online',
        ];

        $nlScore = 0;
        $enScore = 0;
        foreach ($words as $word) {
            if (in_array($word, $nlStopwords, true)) {
                $nlScore += 2;
            }
            if (in_array($word, $enStopwords, true)) {
                $enScore += 2;
            }
        }

        // Dutch-specific orthographic patterns (ij, sch, doubled vowels, common suffixes).
        if (preg_match('/\b\w*(ij|schr|sch\w+|aa|oo|ee|uu|ing|heid|lijk|tje)\w*\b/u', $text)) {
            $nlScore += 1;
        }
        // English-specific patterns.
        if (preg_match('/\b\w*(tion|ness|ment|ing\b|ly\b)/u', $text)) {
            $enScore += 1;
        }

        return $enScore > $nlScore ? 'en' : 'nl';
    }

    private function analyzeWithLlm(string $pageText, array $keywords, array $keywordResults, string $language = 'nl'): array|\WP_Error
    {
        $truncated = mb_substr($pageText, 0, 12000);

        $context = [];
        foreach ($keywordResults as $kr) {
            if ($language === 'en') {
                $context[] = '- ' . $kr['keyword'] . ': AI Overview ' . ($kr['has_ai_overview'] ? 'present' : 'absent');
            } else {
                $context[] = '- ' . $kr['keyword'] . ': AI Overview ' . ($kr['has_ai_overview'] ? 'aanwezig' : 'afwezig');
            }
        }

        if ($language === 'en') {
            $systemPrompt = 'You are an experienced SEO/GEO analyst. Always answer in valid JSON in English.';
            $prompt = "Analyse the webpage below for GEO readiness (Generative Engine Optimization).
Assess whether the page is suitable to be cited as a source by AI search engines.
Write the output in English, in valid JSON, without markdown code blocks, without any text outside the JSON.

Required JSON structure:
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

Guidelines:
- Generate at least 8 findings with short, concrete observations.
- Generate at least 8 recommendations that are immediately actionable and prioritized.
- Also give 3-5 strengths and 3-5 weaknesses.
- priority_ranked_recommendations contains the top 5 recommendations, from highest to lowest priority.
- Make sure the text is suitable to present to a prospect: professional, clear and commercially friendly.

Page text (first 12000 characters):
" . $truncated . "

Main keywords: " . implode(', ', $keywords) . "
AI Overview context:
" . implode("\n", $context);
        } else {
            $systemPrompt = 'Je bent een ervaren SEO/GEO-analist. Antwoord altijd in geldig JSON in het Nederlands.';
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
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
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
        array $failedKeywords,
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
            $targetPosition = null;
            foreach ($kr['organic_top'] as $idx => $item) {
                $host = strtolower(parse_url($item['url'], PHP_URL_HOST) ?: '');
                if ($host && $host === $targetHost && $targetPosition === null) {
                    $targetPosition = $idx + 1;
                }
                if ($host && $host !== $targetHost && !in_array($host, array_column($competitors, 'host'), true)) {
                    $competitors[] = [
                        'host'  => $host,
                        'title' => $item['title'],
                        'url'   => $item['url'],
                    ];
                }
            }

            $keywordsAnalysis[] = [
                'keyword'                => $kr['keyword'],
                'has_ai_overview'        => $kr['has_ai_overview'],
                'ai_text'                => mb_substr($kr['ai_text'], 0, 500),
                'ai_sources_count'       => count($kr['ai_sources']),
                'target_cited'           => $cited,
                'target_organic_position'=> $targetPosition,
                'competitor_citations'   => array_slice($competitors, 0, 5),
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
            'failed_keywords'               => $failedKeywords,
            'page_text_preview'             => mb_substr($htmlResult['text'] ?? '', 0, 500),
            'html_source'                   => $htmlResult['source'] ?? 'unknown',
            'usage'                         => $llmResult['usage'] ?? [],
        ];
    }
}

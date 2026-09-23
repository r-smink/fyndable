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
     * @param string    $source     'admin' or 'website' — website scans can use a
     *                              dedicated model (sseo_ai_saas_geo_website_model).
     * @return array|\WP_Error ['scan_id' => int, 'report' => array]
     */
    public function scan(string $url, array $keywords, string $language = 'nl', ?callable $onProgress = null, ?int $scanId = null, string $source = 'admin'): array|\WP_Error
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

        $progress(10, __('Pagina-inhoud verwerkt', 'sseo-ai-saas'));

        $keywordResults = [];
        $failedKeywords = [];
        $keywordCount = count($keywords);
        foreach ($keywords as $index => $keyword) {
            $progress(
                10 + (int) round(($index / $keywordCount) * 60),
                sprintf(__('AI Overview controleren: %s (%d/%d)', 'sseo-ai-saas'), $keyword, $index + 1, $keywordCount)
            );

            $res = $this->aiExtractor->getForKeyword($keyword, $language);
            if (is_wp_error($res)) {
                $failedKeywords[] = [
                    'keyword' => $keyword,
                    'error'   => $res->get_error_message(),
                ];
            } else {
                $keywordResults[] = $res;
            }

            $progress(
                10 + (int) round((($index + 1) / $keywordCount) * 60),
                sprintf(__('Keyword %d van %d gecontroleerd', 'sseo-ai-saas'), $index + 1, $keywordCount)
            );

            // Small delay to reduce the chance of SerpApi rate limits when
            // multiple keywords are scanned in quick succession.
            if ($index < $keywordCount - 1) {
                usleep(500000);
            }
        }

        if (empty($keywordResults)) {
            return new \WP_Error('all_keywords_failed', __('All keyword lookups failed. Please check your SERP provider settings and try again.', 'sseo-ai-saas'));
        }

        $multiModelEnabled = $source === 'website'
            ? ($this->settings->isGeoMultiModelEnabled() && $this->settings->isGeoWebsiteMultiModelEnabled())
            : $this->settings->isGeoMultiModelEnabled();
        $multiModels = $multiModelEnabled ? $this->settings->getGeoMultiModels() : [];

        if ($multiModelEnabled && count($multiModels) >= 2) {
            $progress(75, __('AI-analyse genereren (multi-model)…', 'sseo-ai-saas'));
            $llmResult = $this->analyzeWithMultipleModels($pageText, $keywords, $keywordResults, $language, $source, $multiModels, $progress);
        } else {
            $progress(75, __('AI-analyse genereren…', 'sseo-ai-saas'));
            $llmResult = $this->analyzeWithLlm($pageText, $keywords, $keywordResults, $language, $source);
        }
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

    /**
     * Run a single-model LLM analysis.
     *
     * @param string|null $modelOverride Explicit model to use (bypasses source-based resolution).
     */
    private function analyzeWithLlm(string $pageText, array $keywords, array $keywordResults, string $language = 'nl', string $source = 'admin', ?string $modelOverride = null): array|\WP_Error
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

        $model = $modelOverride
            ?? ($source === 'website' ? $this->settings->getWebsiteGeoModel() : $this->settings->getGeoModel());

        $modelExtra = $this->getModelSpecificPromptAddition($model, $language);

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
" . $modelExtra . "
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
" . $modelExtra . "
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

        $maxTokens = 6000;

        $result = $this->providerRouter->routeRequest($messages, $model ?: null, 'geo_readiness', $maxTokens, 0.2);

        if (is_wp_error($result)) {
            return $result;
        }

        $content = $result['content'] ?? '';
        $parsed = $this->extractJson($content);

        if (empty($parsed)) {
            $messages[] = ['role' => 'assistant', 'content' => $content];
            $messages[] = ['role' => 'user', 'content' => $language === 'en'
                ? 'Your previous response was not valid JSON. Return ONLY the raw JSON object — no markdown, no commentary.'
                : 'Je vorige antwoord was geen geldige JSON. Geef ALLEEN het kale JSON-object terug — geen markdown, geen toelichting.'];

            $result = $this->providerRouter->routeRequest($messages, $model ?: null, 'geo_readiness', $maxTokens, 0.2);
            if (is_wp_error($result)) {
                return $result;
            }
            $content = $result['content'] ?? '';
            $parsed = $this->extractJson($content);
        }

        if (empty($parsed)) {
            return new \WP_Error(
                'llm_json_invalid',
                __('The AI model did not return a valid JSON response', 'sseo-ai-saas'),
                ['model' => $model, 'response_preview' => mb_substr($content, 0, 300)]
            );
        }

        $parsed['usage'] = $result['usage'] ?? [];
        $parsed['model_used'] = $result['model'] ?? $model;

        return $parsed;
    }

    /**
     * Run LLM analysis across multiple models sequentially and merge the results.
     *
     * @param callable $progress Progress callback fn(int $percent, string $label).
     */
    private function analyzeWithMultipleModels(
        string $pageText,
        array $keywords,
        array $keywordResults,
        string $language,
        string $source,
        array $models,
        callable $progress
    ): array|\WP_Error {
        $modelResults = [];
        $modelCount = count($models);

        foreach ($models as $index => $model) {
            $modelLabel = $this->getModelDisplayName($model);
            $pct = 75 + (int) round(($index / $modelCount) * 15);
            $progress($pct, sprintf(
                __('AI-analyse: %s (%d/%d)…', 'sseo-ai-saas'),
                $modelLabel,
                $index + 1,
                $modelCount
            ));

            $result = $this->analyzeWithLlm($pageText, $keywords, $keywordResults, $language, $source, $model);

            if (is_wp_error($result)) {
                // Log failure but continue with remaining models (graceful degradation).
                error_log(sprintf('[GeoScanner] Multi-model: %s failed — %s', $model, $result->get_error_message()));
                continue;
            }

            $result['model_id'] = $model;
            $modelResults[] = $result;
        }

        if (empty($modelResults)) {
            return new \WP_Error(
                'all_models_failed',
                __('All AI models failed during multi-model analysis.', 'sseo-ai-saas')
            );
        }

        // Single model succeeded — return as-is (no merge needed).
        if (count($modelResults) === 1) {
            $r = $modelResults[0];
            $r['models_used'] = [[
                'model' => $r['model_id'],
                'score' => (int)($r['score'] ?? 0),
            ]];
            return $r;
        }

        return $this->mergeMultiModelResults($modelResults);
    }

    /**
     * Merge results from multiple model analyses into a single report.
     *
     * - Scores/breakdown: averaged across models.
     * - Text arrays (findings, recommendations, etc.): merged and deduplicated.
     * - Usage: summed.
     * - models_used: per-model score for transparency.
     */
    private function mergeMultiModelResults(array $modelResults): array
    {
        $modelCount = count($modelResults);
        $breakdownKeys = [
            'direct_answer', 'structure', 'schema_markup', 'entities',
            'citation_worthiness', 'readability', 'eeat', 'content_freshness',
            'mobile_friendly', 'internal_linking', 'page_metadata',
            'entity_coverage', 'competitive_gap',
        ];

        // Average score and breakdown.
        $totalScore = 0;
        $breakdownSums = array_fill_keys($breakdownKeys, 0);
        $modelsUsed = [];

        foreach ($modelResults as $r) {
            $score = (int)($r['score'] ?? 0);
            $totalScore += $score;

            foreach ($breakdownKeys as $key) {
                $breakdownSums[$key] += (int)($r['breakdown'][$key] ?? 0);
            }

            $modelsUsed[] = [
                'model' => $r['model_id'] ?? ($r['model_used'] ?? 'unknown'),
                'score' => $score,
            ];
        }

        $avgScore = (int) round($totalScore / $modelCount);
        $avgBreakdown = [];
        foreach ($breakdownKeys as $key) {
            $avgBreakdown[$key] = (int) round($breakdownSums[$key] / $modelCount);
        }

        // Merge and deduplicate text arrays.
        $findings = $this->mergeTextArrays($modelResults, 'findings');
        $recommendations = $this->mergeTextArrays($modelResults, 'recommendations');
        $strengths = $this->mergeTextArrays($modelResults, 'strengths');
        $weaknesses = $this->mergeTextArrays($modelResults, 'weaknesses');
        $priorityRecs = $this->mergeTextArrays($modelResults, 'priority_ranked_recommendations');

        // Sum usage.
        $totalUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'cost' => 0.0];
        foreach ($modelResults as $r) {
            $usage = $r['usage'] ?? [];
            $totalUsage['prompt_tokens'] += (int)($usage['prompt_tokens'] ?? 0);
            $totalUsage['completion_tokens'] += (int)($usage['completion_tokens'] ?? 0);
            $totalUsage['total_tokens'] += (int)($usage['total_tokens'] ?? 0);
            $totalUsage['cost'] += (float)($usage['cost'] ?? 0);
        }

        return [
            'score'                          => $avgScore,
            'breakdown'                      => $avgBreakdown,
            'findings'                       => $findings,
            'recommendations'                => $recommendations,
            'strengths'                      => $strengths,
            'weaknesses'                     => $weaknesses,
            'priority_ranked_recommendations'=> array_slice($priorityRecs, 0, 5),
            'usage'                          => $totalUsage,
            'models_used'                    => $modelsUsed,
            'multi_model'                    => true,
        ];
    }

    /**
     * Merge a named text-array field from multiple model results, deduplicated.
     *
     * Uses a simple similarity check to avoid near-duplicate entries from
     * different models saying roughly the same thing.
     */
    private function mergeTextArrays(array $modelResults, string $field): array
    {
        $all = [];
        foreach ($modelResults as $r) {
            foreach ((array)($r[$field] ?? []) as $item) {
                $item = trim((string)$item);
                if ($item !== '' && !$this->isDuplicate($item, $all)) {
                    $all[] = $item;
                }
            }
        }
        return $all;
    }

    /**
     * Check if a text is a near-duplicate of any existing entry (>= 60% word overlap).
     */
    private function isDuplicate(string $text, array $existing): bool
    {
        $words = array_unique(preg_split('/\s+/', mb_strtolower($text)) ?: []);
        $wordCount = count($words);
        if ($wordCount === 0) {
            return false;
        }

        foreach ($existing as $entry) {
            $entryWords = array_unique(preg_split('/\s+/', mb_strtolower($entry)) ?: []);
            $overlap = count(array_intersect($words, $entryWords));
            $maxLen = max($wordCount, count($entryWords));
            if ($maxLen > 0 && ($overlap / $maxLen) >= 0.6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a model-specific prompt addition that emphasizes the evaluation
     * criteria most relevant to each AI platform.
     */
    private function getModelSpecificPromptAddition(string $model, string $language): string
    {
        $family = $this->detectModelFamily($model);

        if ($language === 'en') {
            return match ($family) {
                'openai' => "\nAdditional focus: Evaluate specifically for ChatGPT Search visibility. ChatGPT Search retrieves live results via Bing, so assess whether the page would appear both in the base model's training knowledge and in live search results. Pay extra attention to Bing-indexed content signals, conversational answer suitability, and whether the content directly answers common user questions.\n",
                'google' => "\nAdditional focus: Evaluate specifically from Google Gemini's perspective. Gemini draws ~99.5% of its sources from the organic top 10 and relies heavily on Google's Knowledge Graph. Pay extra attention to structured data (Schema.org), Google Business Profile integration, Knowledge Graph entity alignment, and traditional organic SEO strength. Local business information accuracy is especially important.\n",
                'anthropic' => "\nAdditional focus: Evaluate specifically from Claude's perspective. Claude is conservative with name-dropping — a brand must be explicitly relevant to be mentioned. Pay extra attention to entity clarity, unambiguous structured content, explicit authority signals (author bios, citations, credentials), and whether the brand's relevance is self-evident from the content rather than implied.\n",
                default => '',
            };
        }

        return match ($family) {
            'openai' => "\nExtra focus: Evalueer specifiek voor zichtbaarheid in ChatGPT Search. ChatGPT Search haalt live resultaten op via Bing, dus beoordeel of de pagina zowel in de basiskennis van het model als in live zoekresultaten zou verschijnen. Let extra op Bing-geïndexeerde content-signalen, geschiktheid voor conversationele antwoorden, en of de content veelgestelde vragen direct beantwoordt.\n",
            'google' => "\nExtra focus: Evalueer specifiek vanuit Google Gemini's perspectief. Gemini haalt ~99,5% van zijn bronnen uit de organische top-10 en leunt zwaar op Google's Knowledge Graph. Let extra op gestructureerde data (Schema.org), Google Bedrijfsprofiel-integratie, Knowledge Graph entity-afstemming en traditionele organische SEO-sterkte. Nauwkeurigheid van lokale bedrijfsinformatie is bijzonder belangrijk.\n",
            'anthropic' => "\nExtra focus: Evalueer specifiek vanuit Claude's perspectief. Claude is conservatief met het noemen van merknamen — een merk moet expliciet relevant zijn om vermeld te worden. Let extra op entity clarity, ondubbelzinnige gestructureerde content, expliciete autoriteitssignalen (auteursbio's, citaties, referenties) en of de relevantie van het merk vanzelfsprekend is vanuit de content in plaats van impliciet.\n",
            default => '',
        };
    }

    /**
     * Detect the model family from a model ID (e.g. 'openai/gpt-5' → 'openai').
     */
    private function detectModelFamily(string $model): string
    {
        $model = mb_strtolower($model);

        if (str_starts_with($model, 'openai/') || str_starts_with($model, 'gpt')) {
            return 'openai';
        }
        if (str_starts_with($model, 'google/') || str_starts_with($model, 'gemini')) {
            return 'google';
        }
        if (str_starts_with($model, 'anthropic/') || str_starts_with($model, 'claude')) {
            return 'anthropic';
        }

        return 'other';
    }

    /**
     * Get a short display name for a model (strips provider prefix).
     */
    private function getModelDisplayName(string $model): string
    {
        $available = ProviderRouter::getAvailableModels();
        if (isset($available[$model])) {
            return $available[$model];
        }
        // Fallback: strip "provider/" prefix.
        return str_contains($model, '/') ? substr($model, strpos($model, '/') + 1) : $model;
    }

    private function extractJson(string $content): ?array
    {
        // Strip markdown code fences (```json ... ```) and stray whitespace.
        $clean = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $clean, $fence)) {
            $clean = trim($fence[1]);
        }

        $decoded = json_decode($clean, true);
        if (is_array($decoded) && isset($decoded['score'])) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && isset($decoded['score'])) {
                return $decoded;
            }
        }

        // Truncated output (hit max_tokens): try to repair by closing open
        // strings/brackets and slicing back to the last complete element.
        $repaired = $this->repairTruncatedJson($clean);
        if ($repaired !== null) {
            $decoded = json_decode($repaired, true);
            if (is_array($decoded) && isset($decoded['score'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Attempt to repair a JSON document truncated mid-stream: cut back to the
     * last "safe" boundary — a comma or closed bracket outside strings, which
     * guarantees everything before it was complete — then close the open
     * structures.
     */
    private function repairTruncatedJson(string $content): ?string
    {
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $json = substr($content, $start);
        $len = strlen($json);
        $inString = false;
        $escaped = false;
        $cutPoint = -1; // byte offset just after the last safe boundary

        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
            } elseif ($ch === ',') {
                $cutPoint = $i + 1;
            } elseif ($ch === '}' || $ch === ']') {
                $cutPoint = $i + 1;
            }
        }

        if ($cutPoint <= 0) {
            return null;
        }

        $cut = substr($json, 0, $cutPoint);
        $cut = rtrim($cut);
        $cut = rtrim($cut, ',');

        // Rebuild the open bracket/string stack for the trimmed fragment.
        $stack = [];
        $inString = false;
        $escaped = false;
        $len = strlen($cut);
        for ($i = 0; $i < $len; $i++) {
            $ch = $cut[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inString = true;
            } elseif ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
            } elseif ($ch === '}' || $ch === ']') {
                array_pop($stack);
            }
        }

        if (empty($stack)) {
            return null; // nothing was actually truncated
        }

        if ($inString) {
            $cut .= '"';
        }
        foreach (array_reverse($stack) as $open) {
            $cut .= ($open === '{') ? '}' : ']';
        }

        return $cut;
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
            'multi_model'                   => !empty($llmResult['multi_model']),
            'models_used'                   => $llmResult['models_used'] ?? [],
        ];
    }
}

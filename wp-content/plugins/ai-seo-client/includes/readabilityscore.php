<?php

namespace AISEOClient;

/**
 * Readability Score
 * 
 * Calculates Flesch Reading Ease, Flesch-Kincaid Grade Level, and
 * a custom readability analysis with actionable suggestions.
 * Supports English AND Dutch (Flesch-Douma for NL).
 * 
 * Comparable to Yoast's readability analysis but with multi-language
 * support and AI-powered improvement suggestions.
 */
class ReadabilityScore
{
    private Settings $settings;
    private LlmClient $llm;

    public function __construct(Settings $settings, LlmClient $llm)
    {
        $this->settings = $settings;
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoclient/v1', '/readability/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'restAnalyze'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
                'language' => ['type' => 'string', 'default' => 'en'],
            ],
        ]);

        register_rest_route('aiseoclient/v1', '/readability/suggest', [
            'methods' => 'POST',
            'callback' => [$this, 'restSuggest'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
                'language' => ['type' => 'string', 'default' => 'en'],
            ],
        ]);
    }

    /**
     * Full readability analysis of content.
     */
    public function analyze(string $content, string $language = 'en'): array
    {
        $plainText = wp_strip_all_tags($content);
        $plainText = html_entity_decode($plainText, ENT_QUOTES, 'UTF-8');
        $plainText = preg_replace('/\s+/', ' ', trim($plainText));

        if (strlen($plainText) < 20) {
            return ['error' => 'Content too short for analysis'];
        }

        $sentences = $this->countSentences($plainText);
        $words = $this->countWords($plainText);
        $syllables = $this->countSyllables($plainText, $language);
        $chars = mb_strlen(preg_replace('/\s/', '', $plainText));
        $paragraphs = $this->countParagraphs($content);

        // Flesch Reading Ease (or Flesch-Douma for Dutch)
        $fleschScore = $this->calculateFlesch($words, $sentences, $syllables, $language);

        // Flesch-Kincaid Grade Level
        $gradeLevel = $this->calculateGradeLevel($words, $sentences, $syllables);

        // Sentence length analysis
        $sentenceAnalysis = $this->analyzeSentenceLengths($plainText);

        // Passive voice detection (heuristic)
        $passiveCount = $this->detectPassiveVoice($plainText, $language);

        // Transition word usage
        $transitionCount = $this->countTransitionWords($plainText, $language);
        $transitionRatio = $sentences > 0 ? round(($transitionCount / $sentences) * 100) : 0;

        // Paragraph length analysis
        $paragraphAnalysis = $this->analyzeParagraphs($content);

        // Build issues list
        $issues = [];
        $score = 100;

        // Flesch score assessment
        if ($fleschScore < 30) {
            $issues[] = ['type' => 'error', 'label' => __('Very difficult to read', 'ai-seo-client'), 'message' => sprintf(__('Flesch score: %d. Aim for 60+ for general audiences.', 'ai-seo-client'), $fleschScore)];
            $score -= 25;
        } elseif ($fleschScore < 50) {
            $issues[] = ['type' => 'warning', 'label' => __('Difficult to read', 'ai-seo-client'), 'message' => sprintf(__('Flesch score: %d. Try shorter sentences and simpler words.', 'ai-seo-client'), $fleschScore)];
            $score -= 15;
        } elseif ($fleschScore < 60) {
            $issues[] = ['type' => 'warning', 'label' => __('Fairly difficult', 'ai-seo-client'), 'message' => sprintf(__('Flesch score: %d. Close to ideal, simplify a few sections.', 'ai-seo-client'), $fleschScore)];
            $score -= 8;
        } else {
            $issues[] = ['type' => 'good', 'label' => __('Good readability', 'ai-seo-client'), 'message' => sprintf(__('Flesch score: %d. Easy to read for most audiences.', 'ai-seo-client'), $fleschScore)];
        }

        // Sentence length
        $avgSentenceLength = $sentences > 0 ? round($words / $sentences, 1) : 0;
        if ($avgSentenceLength > 25) {
            $issues[] = ['type' => 'error', 'label' => __('Sentences too long', 'ai-seo-client'), 'message' => sprintf(__('Average: %.1f words/sentence. Aim for under 20.', 'ai-seo-client'), $avgSentenceLength)];
            $score -= 15;
        } elseif ($avgSentenceLength > 20) {
            $issues[] = ['type' => 'warning', 'label' => __('Sentences slightly long', 'ai-seo-client'), 'message' => sprintf(__('Average: %.1f words/sentence. Try to shorten some.', 'ai-seo-client'), $avgSentenceLength)];
            $score -= 8;
        } else {
            $issues[] = ['type' => 'good', 'label' => __('Good sentence length', 'ai-seo-client'), 'message' => sprintf(__('Average: %.1f words/sentence.', 'ai-seo-client'), $avgSentenceLength)];
        }

        // Long sentences percentage
        $longPct = $sentenceAnalysis['long_percentage'] ?? 0;
        if ($longPct > 40) {
            $issues[] = ['type' => 'error', 'label' => __('Too many long sentences', 'ai-seo-client'), 'message' => sprintf(__('%d%% of sentences are over 20 words. Aim for under 25%%.', 'ai-seo-client'), $longPct)];
            $score -= 10;
        } elseif ($longPct > 25) {
            $issues[] = ['type' => 'warning', 'label' => __('Some long sentences', 'ai-seo-client'), 'message' => sprintf(__('%d%% of sentences are over 20 words.', 'ai-seo-client'), $longPct)];
            $score -= 5;
        }

        // Passive voice
        $passivePct = $sentences > 0 ? round(($passiveCount / $sentences) * 100) : 0;
        if ($passivePct > 15) {
            $issues[] = ['type' => 'warning', 'label' => __('Passive voice overuse', 'ai-seo-client'), 'message' => sprintf(__('~%d%% passive voice detected. Aim for under 10%%.', 'ai-seo-client'), $passivePct)];
            $score -= 8;
        } else {
            $issues[] = ['type' => 'good', 'label' => __('Active voice', 'ai-seo-client'), 'message' => sprintf(__('~%d%% passive voice. Good.', 'ai-seo-client'), $passivePct)];
        }

        // Transition words
        if ($transitionRatio < 20) {
            $issues[] = ['type' => 'warning', 'label' => __('Few transition words', 'ai-seo-client'), 'message' => sprintf(__('%d%% of sentences use transitions. Aim for 30%%+.', 'ai-seo-client'), $transitionRatio)];
            $score -= 8;
        } else {
            $issues[] = ['type' => 'good', 'label' => __('Good use of transitions', 'ai-seo-client'), 'message' => sprintf(__('%d%% of sentences use transition words.', 'ai-seo-client'), $transitionRatio)];
        }

        // Paragraph length
        if ($paragraphAnalysis['long_paragraphs'] > 0) {
            $issues[] = ['type' => 'warning', 'label' => __('Long paragraphs', 'ai-seo-client'), 'message' => sprintf(__('%d paragraph(s) exceed 150 words. Break them up.', 'ai-seo-client'), $paragraphAnalysis['long_paragraphs'])];
            $score -= 5;
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'grade' => $this->scoreToGrade($score),
            'flesch_score' => $fleschScore,
            'flesch_label' => $this->fleschLabel($fleschScore),
            'grade_level' => $gradeLevel,
            'language' => $language,
            'stats' => [
                'words' => $words,
                'sentences' => $sentences,
                'paragraphs' => $paragraphs,
                'syllables' => $syllables,
                'characters' => $chars,
                'avg_sentence_length' => $avgSentenceLength,
                'avg_syllables_per_word' => $words > 0 ? round($syllables / $words, 2) : 0,
                'passive_voice_pct' => $passivePct,
                'transition_word_pct' => $transitionRatio,
                'long_sentences_pct' => $longPct,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Calculate Flesch Reading Ease.
     * English: 206.835 - 1.015*(words/sentences) - 84.6*(syllables/words)
     * Dutch (Flesch-Douma): 206.835 - 0.93*(words/sentences) - 77.0*(syllables/words)
     */
    private function calculateFlesch(int $words, int $sentences, int $syllables, string $language): int
    {
        if ($words === 0 || $sentences === 0) {
            return 0;
        }

        $asl = $words / $sentences; // average sentence length
        $asw = $syllables / $words; // average syllables per word

        if ($language === 'nl') {
            // Flesch-Douma formula for Dutch
            $score = 206.835 - (0.93 * $asl) - (77.0 * $asw);
        } else {
            // Standard Flesch Reading Ease
            $score = 206.835 - (1.015 * $asl) - (84.6 * $asw);
        }

        return max(0, min(100, (int) round($score)));
    }

    /**
     * Calculate Flesch-Kincaid Grade Level.
     */
    private function calculateGradeLevel(int $words, int $sentences, int $syllables): float
    {
        if ($words === 0 || $sentences === 0) {
            return 0;
        }

        $grade = (0.39 * ($words / $sentences)) + (11.8 * ($syllables / $words)) - 15.59;
        return max(0, round($grade, 1));
    }

    /**
     * Count sentences in text.
     */
    private function countSentences(string $text): int
    {
        // Split on sentence-ending punctuation
        $sentences = preg_split('/[.!?]+\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return max(1, count($sentences));
    }

    /**
     * Count words in text.
     */
    private function countWords(string $text): int
    {
        return str_word_count($text);
    }

    /**
     * Count syllables (heuristic for EN/NL).
     */
    private function countSyllables(string $text, string $language = 'en'): int
    {
        $words = preg_split('/\s+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $total = 0;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z\x{00C0}-\x{024F}]/u', '', $word);
            if (strlen($word) <= 2) {
                $total += 1;
                continue;
            }

            // Count vowel groups
            if ($language === 'nl') {
                // Dutch vowels including digraphs
                $count = preg_match_all('/[aeiouyèéêëïîôöüû]+/i', $word);
            } else {
                $count = preg_match_all('/[aeiouy]+/i', $word);
            }

            // Adjust for silent e at end (English)
            if ($language === 'en' && preg_match('/[^l]e$/', $word)) {
                $count = max(1, $count - 1);
            }

            $total += max(1, $count);
        }

        return $total;
    }

    /**
     * Count paragraphs in HTML content.
     */
    private function countParagraphs(string $html): int
    {
        $count = preg_match_all('/<p[\s>]/i', $html);
        if ($count === 0) {
            // No <p> tags, count double newlines
            $count = count(preg_split('/\n\s*\n/', wp_strip_all_tags($html), -1, PREG_SPLIT_NO_EMPTY));
        }
        return max(1, $count);
    }

    /**
     * Analyze sentence length distribution.
     */
    private function analyzeSentenceLengths(string $text): array
    {
        $sentences = preg_split('/[.!?]+\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $longCount = 0;
        $veryLongCount = 0;

        foreach ($sentences as $sentence) {
            $wc = str_word_count($sentence);
            if ($wc > 30) {
                $veryLongCount++;
            } elseif ($wc > 20) {
                $longCount++;
            }
        }

        $total = count($sentences);
        return [
            'total' => $total,
            'long' => $longCount,
            'very_long' => $veryLongCount,
            'long_percentage' => $total > 0 ? (int) round((($longCount + $veryLongCount) / $total) * 100) : 0,
        ];
    }

    /**
     * Analyze paragraph lengths.
     */
    private function analyzeParagraphs(string $html): array
    {
        $blocks = preg_split('/<\/p>|<br\s*\/?>\s*<br\s*\/?>/i', $html);
        $longParagraphs = 0;

        foreach ($blocks as $block) {
            $text = wp_strip_all_tags($block);
            if (str_word_count($text) > 150) {
                $longParagraphs++;
            }
        }

        return [
            'total' => count($blocks),
            'long_paragraphs' => $longParagraphs,
        ];
    }

    /**
     * Heuristic passive voice detection.
     */
    private function detectPassiveVoice(string $text, string $language = 'en'): int
    {
        if ($language === 'nl') {
            // Dutch passive: "wordt/werden/is/zijn/was/waren" + past participle (ge...d/ge...t/ge...en)
            return (int) preg_match_all('/\b(wordt|worden|werd|werden|is|zijn|was|waren)\s+\w*ge\w+(d|t|en)\b/i', $text);
        }

        // English passive: form of "to be" + past participle
        $beVerbs = 'is|are|was|were|been|being|be';
        return (int) preg_match_all('/\b(' . $beVerbs . ')\s+(\w+ed|written|done|made|taken|given|shown|known|found|said|told)\b/i', $text);
    }

    /**
     * Count transition words in text.
     */
    private function countTransitionWords(string $text, string $language = 'en'): int
    {
        if ($language === 'nl') {
            $transitions = [
                'daarom', 'bovendien', 'echter', 'hoewel', 'desalniettemin', 'vervolgens',
                'daarnaast', 'ten eerste', 'ten tweede', 'ten slotte', 'bijvoorbeeld',
                'namelijk', 'kortom', 'samengevat', 'dus', 'ook', 'maar', 'toch',
                'immers', 'want', 'omdat', 'aangezien', 'terwijl', 'zodra',
                'daarentegen', 'integendeel', 'evenwel', 'niettemin', 'aldus',
                'met andere woorden', 'dat wil zeggen', 'al met al', 'over het algemeen',
            ];
        } else {
            $transitions = [
                'however', 'therefore', 'furthermore', 'moreover', 'additionally',
                'consequently', 'nevertheless', 'meanwhile', 'subsequently', 'accordingly',
                'for example', 'for instance', 'in addition', 'as a result', 'on the other hand',
                'in contrast', 'similarly', 'likewise', 'in conclusion', 'in summary',
                'first', 'second', 'third', 'finally', 'also', 'although', 'because',
                'since', 'while', 'whereas', 'despite', 'instead', 'thus', 'hence',
            ];
        }

        $count = 0;
        $textLower = strtolower($text);
        foreach ($transitions as $tw) {
            $count += substr_count($textLower, $tw);
        }

        return $count;
    }

    /**
     * Convert Flesch score to human label.
     */
    private function fleschLabel(int $score): string
    {
        if ($score >= 90) return __('Very easy', 'ai-seo-client');
        if ($score >= 80) return __('Easy', 'ai-seo-client');
        if ($score >= 70) return __('Fairly easy', 'ai-seo-client');
        if ($score >= 60) return __('Standard', 'ai-seo-client');
        if ($score >= 50) return __('Fairly difficult', 'ai-seo-client');
        if ($score >= 30) return __('Difficult', 'ai-seo-client');
        return __('Very difficult', 'ai-seo-client');
    }

    private function scoreToGrade(int $score): string
    {
        if ($score >= 80) return 'A';
        if ($score >= 60) return 'B';
        if ($score >= 40) return 'C';
        return 'D';
    }

    /**
     * AI-powered readability improvement suggestions.
     */
    public function suggest(string $content, string $language = 'en'): array|\WP_Error
    {
        $analysis = $this->analyze($content, $language);
        $langLabel = $language === 'nl' ? 'Dutch' : 'English';

        $issuesSummary = '';
        foreach ($analysis['issues'] ?? [] as $issue) {
            if ($issue['type'] !== 'good') {
                $issuesSummary .= "- {$issue['label']}: {$issue['message']}\n";
            }
        }

        if (empty($issuesSummary)) {
            return ['suggestions' => [], 'message' => __('Content readability is already good!', 'ai-seo-client')];
        }

        $prompt = <<<PROMPT
Analyze this {$langLabel} content for readability issues and provide specific, actionable suggestions.

Current issues:
{$issuesSummary}

Content:
---
{$content}
---

Return JSON array of suggestions, each with: "issue", "location" (quote the problematic text, max 50 chars), "suggestion" (how to fix it). Max 8 suggestions. Return ONLY valid JSON array.
PROMPT;

        $result = $this->llm->call($prompt, null, null, 2000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        if (preg_match('/```(?:json)?\s*(\[[\s\S]*?\])\s*```/', $text, $matches)) {
            $text = $matches[1];
        }

        $suggestions = json_decode($text, true);
        if (!is_array($suggestions)) {
            $suggestions = [];
        }

        return [
            'analysis' => $analysis,
            'suggestions' => array_slice($suggestions, 0, 8),
        ];
    }

    // REST handlers
    public function restAnalyze(\WP_REST_Request $request): array
    {
        return $this->analyze(
            $request->get_param('content'),
            sanitize_text_field($request->get_param('language'))
        );
    }

    public function restSuggest(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->suggest(
            $request->get_param('content'),
            sanitize_text_field($request->get_param('language'))
        );
    }
}

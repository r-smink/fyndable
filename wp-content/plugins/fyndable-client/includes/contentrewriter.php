<?php

namespace SSEOAIClient;

/**
 * AI Content Rewriter / Optimizer
 * 
 * Rewrites existing content for better SEO performance. Supports multiple modes:
 * - Improve readability and flow
 * - Optimize for a target keyword
 * - Expand thin content sections
 * - Rewrite for different tone/audience
 * - Paraphrase to improve originality
 * 
 * Comparable to WPSEO AI rewriter and Jasper's content optimizer.
 */
class ContentRewriter
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
        register_rest_route('sseo-ai/v1', '/rewrite', [
            'methods' => 'POST',
            'callback' => [$this, 'restRewrite'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
                'mode' => ['type' => 'string', 'default' => 'improve'],
                'keyword' => ['type' => 'string', 'default' => ''],
                'tone' => ['type' => 'string', 'default' => ''],
                'instructions' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/rewrite/section', [
            'methods' => 'POST',
            'callback' => [$this, 'restRewriteSection'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
                'context' => ['type' => 'string', 'default' => ''],
                'mode' => ['type' => 'string', 'default' => 'improve'],
                'keyword' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/humanize', [
            'methods' => 'POST',
            'callback' => [$this, 'restHumanize'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
                'keyword' => ['type' => 'string', 'default' => ''],
                'intensity' => ['type' => 'string', 'default' => 'balanced'],
            ],
        ]);
    }

    /**
     * Rewrite content based on the specified mode.
     */
    public function rewrite(string $content, string $mode = 'improve', array $options = []): array|\WP_Error
    {
        $keyword = $options['keyword'] ?? '';
        $tone = $options['tone'] ?? $this->settings->get('llm_tone', 'professional');
        $instructions = $options['instructions'] ?? '';

        $prompt = $this->buildPrompt($content, $mode, $keyword, $tone, $instructions);

        $result = $this->llm->call($prompt, 'You are an expert SEO content editor. Preserve HTML structure. Return only the rewritten content.', null, 4000);
        if (is_wp_error($result)) {
            return $result;
        }

        $rewritten = $result['text'] ?? '';

        // Strip markdown code fences if present
        $rewritten = preg_replace('/^```(?:html)?\s*\n?/i', '', $rewritten);
        $rewritten = preg_replace('/\n?```\s*$/', '', $rewritten);

        $originalWords = str_word_count(wp_strip_all_tags($content));
        $newWords = str_word_count(wp_strip_all_tags($rewritten));

        return [
            'original' => $content,
            'rewritten' => trim($rewritten),
            'mode' => $mode,
            'original_word_count' => $originalWords,
            'new_word_count' => $newWords,
            'word_change' => $newWords - $originalWords,
        ];
    }

    /**
     * Rewrite a single section/paragraph with article context.
     */
    public function rewriteSection(string $section, string $context = '', string $mode = 'improve', string $keyword = ''): array|\WP_Error
    {
        $contextNote = $context ? "\n\nFor context, this section is part of a larger article about: {$context}" : '';
        $keywordNote = $keyword ? "\nTarget keyword: \"{$keyword}\" — weave it naturally if missing." : '';

        $modeInstructions = match ($mode) {
            'expand' => 'Expand this section with more detail, examples, and depth. At least double the length.',
            'simplify' => 'Simplify this section for easier reading. Use shorter sentences and simpler vocabulary.',
            'persuasive' => 'Rewrite to be more persuasive and compelling. Use power words and strong CTAs.',
            'paraphrase' => 'Completely paraphrase this section while keeping the same meaning. Change sentence structure and vocabulary.',
            default => 'Improve readability, flow, and engagement while maintaining the core message.',
        };

        $prompt = <<<PROMPT
{$modeInstructions}{$contextNote}{$keywordNote}

Section to rewrite:
---
{$section}
---

Return ONLY the rewritten section. Preserve any HTML formatting.
PROMPT;

        $result = $this->llm->call($prompt, null, null, 2000);
        if (is_wp_error($result)) {
            return $result;
        }

        $rewritten = trim($result['text'] ?? '');
        $rewritten = preg_replace('/^```(?:html)?\s*\n?/i', '', $rewritten);
        $rewritten = preg_replace('/\n?```\s*$/', '', $rewritten);

        return [
            'original' => $section,
            'rewritten' => trim($rewritten),
            'mode' => $mode,
        ];
    }

    /**
     * Build the rewrite prompt based on mode.
     */
    private function buildPrompt(string $content, string $mode, string $keyword, string $tone, string $instructions): string
    {
        $modeBlock = match ($mode) {
            'seo_optimize' => "Optimize this content for SEO. Target keyword: \"{$keyword}\". Ensure the keyword appears naturally in the first paragraph, at least one H2, and throughout the text at 1-2% density. Add relevant semantic keywords. Improve heading structure.",
            'readability' => "Rewrite for maximum readability. Use shorter sentences (max 20 words). Break up long paragraphs. Use transition words. Aim for a Flesch reading score of 60+. Keep the same information but make it easier to read.",
            'expand' => "Expand this content significantly. Add more depth, examples, statistics, and explanations. Target at least 50% more words. Add new relevant subsections with H2/H3 headings where appropriate.",
            'condense' => "Condense this content to its essential points. Remove fluff, redundancy, and filler. Keep all key information but reduce word count by 30-40%.",
            'paraphrase' => "Completely paraphrase this content for originality. Change sentence structures, use different vocabulary, reorganize paragraphs. The meaning must stay identical but the text must be entirely new.",
            'tone_shift' => "Rewrite this content in a {$tone} tone. Adjust vocabulary, sentence structure, and style to match the requested tone while keeping all information intact.",
            'humanize' => "Rewrite this content to sound more human and natural. Apply these techniques:\n- Vary sentence length significantly (mix short punchy sentences with longer flowing ones)\n- Add personal perspectives and conversational asides\n- Use contractions naturally (don't, can't, it's)\n- Include rhetorical questions where appropriate\n- Replace overly formal language with everyday expressions\n- Add transitional phrases that feel organic, not formulaic\n- Break the fourth wall occasionally (address the reader directly)\n- Replace generic statements with specific, vivid examples\n- Add mild imperfections that feel human (starting sentences with 'And' or 'But')\n- Remove AI-typical phrases like 'In conclusion', 'It's worth noting', 'Furthermore', 'Moreover'\n- Keep all factual information and SEO value intact",
            default => "Improve this content's overall quality. Fix grammar, improve flow, strengthen weak sentences, and make it more engaging. Keep the same structure and information.",
        };

        $keywordNote = $keyword ? "\nTarget keyword: \"{$keyword}\"" : '';
        $toneNote = $tone ? "\nDesired tone: {$tone}" : '';
        $customNote = $instructions ? "\nAdditional instructions: {$instructions}" : '';

        return <<<PROMPT
{$modeBlock}{$keywordNote}{$toneNote}{$customNote}

Content to rewrite:
---
{$content}
---

Requirements:
- Preserve ALL HTML tags and structure (headings, lists, links, images)
- Do not remove any sections or headings
- Do not add markdown formatting — keep HTML
- Return ONLY the rewritten content, no explanations
PROMPT;
    }

    // REST handlers
    public function restRewrite(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->rewrite(
            $request->get_param('content'),
            sanitize_text_field($request->get_param('mode')),
            [
                'keyword' => sanitize_text_field($request->get_param('keyword')),
                'tone' => sanitize_text_field($request->get_param('tone')),
                'instructions' => sanitize_text_field($request->get_param('instructions')),
            ]
        );
    }

    /**
     * Humanize AI-generated content to bypass AI detection.
     * @param string $content HTML content to humanize
     * @param array $options {keyword, intensity}
     * @return array|\WP_Error
     */
    public function humanize(string $content, array $options = []): array|\WP_Error
    {
        $keyword = $options['keyword'] ?? '';
        $intensity = $options['intensity'] ?? 'balanced'; // subtle, balanced, aggressive

        $intensityGuide = match ($intensity) {
            'subtle' => "Make light touches — vary a few sentences, add a couple of contractions, soften formal phrases.",
            'aggressive' => "Heavily rework the text — change most sentence structures, add personal anecdotes, use colloquial language, break conventions.",
            default => "Moderately rework the text for a natural, human feel without losing professionalism.",
        };

        $keywordNote = $keyword ? "\nPreserve SEO keyword: \"{$keyword}\" with natural 1-2% density." : '';

        $prompt = <<<PROMPT
You are a humanizing editor. Rewrite the following content so it reads like it was written by a real person, not an AI.

{$intensityGuide}{$keywordNote}

Techniques to apply:
- Vary sentence rhythm and length dramatically
- Use conversational connectors ("Here's the thing...", "Now...", "But wait...")
- Replace AI clichés with fresh alternatives
- Add micro-stories or relatable examples
- Use second-person address ("you", "your")
- Include occasional humor or personality
- Make paragraphs uneven in length
- Avoid bullet-point-heavy sections — convert some to prose

Content to humanize:
---
{$content}
---

Return ONLY the humanized content. Preserve HTML structure. Keep all factual information.
PROMPT;

        $result = $this->llm->call($prompt, 'You are a humanizing editor. Return only the rewritten content.', null, 4000);
        if (is_wp_error($result)) {
            return $result;
        }

        $humanized = trim($result['text'] ?? '');
        $humanized = preg_replace('/^```(?:html)?\s*\n?/i', '', $humanized);
        $humanized = preg_replace('/\n?```\s*$/', '', $humanized);

        $originalWords = str_word_count(wp_strip_all_tags($content));
        $newWords = str_word_count(wp_strip_all_tags($humanized));

        return [
            'original' => $content,
            'humanized' => $humanized,
            'intensity' => $intensity,
            'original_word_count' => $originalWords,
            'new_word_count' => $newWords,
            'word_change' => $newWords - $originalWords,
        ];
    }

    public function restHumanize(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->humanize(
            $request->get_param('content'),
            [
                'keyword' => sanitize_text_field($request->get_param('keyword')),
                'intensity' => sanitize_text_field($request->get_param('intensity') ?? 'balanced'),
            ]
        );
    }

    public function restRewriteSection(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->rewriteSection(
            $request->get_param('content'),
            sanitize_text_field($request->get_param('context')),
            sanitize_text_field($request->get_param('mode')),
            sanitize_text_field($request->get_param('keyword'))
        );
    }
}

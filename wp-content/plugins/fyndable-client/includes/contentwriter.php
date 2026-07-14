<?php

namespace SSEOAIClient;

/**
 * AI Content Writer
 * 
 * Generates full SEO-optimized articles using AI, with configurable structure,
 * tone, word count targets, and automatic internal linking. Integrates with
 * Content Brief for data-driven content creation. This is the WP SEO AI killer feature.
 */
class ContentWriter
{
    private LlmClient $llm;
    private Settings $settings;
    private ?ContentBrief $contentBrief;

    public function __construct(LlmClient $llm, Settings $settings, ?ContentBrief $contentBrief = null)
    {
        $this->llm = $llm;
        $this->settings = $settings;
        $this->contentBrief = $contentBrief;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        // Menu registration moved to Client class
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Content Writer', 'ai-seo-client'),
            __('Content Writer', 'ai-seo-client'),
            'manage_options',
            'ai-seo-assistant-writer',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/write-article', [
            'methods' => 'POST',
            'callback' => [$this, 'restWriteArticle'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'title' => ['type' => 'string', 'required' => false],
                'tone' => ['type' => 'string', 'required' => false],
                'word_count' => ['type' => 'integer', 'required' => false],
                'outline' => ['type' => 'string', 'required' => false],
                'brief_id' => ['type' => 'string', 'required' => false],
                'create_draft' => ['type' => 'boolean', 'required' => false],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/write-section', [
            'methods' => 'POST',
            'callback' => [$this, 'restWriteSection'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'heading' => ['type' => 'string', 'required' => true],
                'context' => ['type' => 'string', 'required' => false],
                'tone' => ['type' => 'string', 'required' => false],
                'word_count' => ['type' => 'integer', 'required' => false],
            ],
        ]);
    }

    /**
     * Generate a full article for a keyword.
     */
    public function writeArticle(string $keyword, array $options = []): array|\WP_Error
    {
        $title = $options['title'] ?? '';
        $tone = $options['tone'] ?? $this->settings->get('llm_tone', 'professional');
        $targetWords = $options['word_count'] ?? 1500;
        $outline = $options['outline'] ?? '';
        $briefId = $options['brief_id'] ?? '';

        // Get brief data if available
        $brief = null;
        if ($briefId) {
            $brief = get_transient($briefId);
        }

        // If we have a content brief module and no outline, generate one
        if (!$outline && $this->contentBrief && !$brief) {
            $briefResult = $this->contentBrief->generateBrief($keyword);
            if (!is_wp_error($briefResult)) {
                $brief = $briefResult;
                $outline = $brief['content_outline'] ?? '';
            }
        }

        // Generate title if not provided
        if (!$title) {
            $title = $this->generateTitle($keyword, $brief);
            if (is_wp_error($title)) {
                $title = ucfirst($keyword);
            }
        }

        // Build the article in sections for higher quality
        $sections = $this->buildSections($keyword, $title, $outline, $brief, $tone, $targetWords);
        if (is_wp_error($sections)) {
            return $sections;
        }

        // Generate intro
        $intro = $this->generateIntro($keyword, $title, $tone, $brief);
        if (is_wp_error($intro)) {
            return $intro;
        }

        // Generate conclusion
        $conclusion = $this->generateConclusion($keyword, $title, $tone);
        if (is_wp_error($conclusion)) {
            return $conclusion;
        }

        // Generate FAQ section if brief has questions
        $faq = '';
        $questions = $brief['recommended_questions'] ?? [];
        if (!empty($questions)) {
            $faq = $this->generateFAQ($keyword, $questions, $tone);
        }

        // Assemble the full article
        $article = $intro . "\n\n" . implode("\n\n", $sections);
        if ($faq) {
            $article .= "\n\n<h2>" . __('Frequently Asked Questions', 'ai-seo-client') . "</h2>\n\n" . $faq;
        }
        $article .= "\n\n<h2>" . __('Conclusion', 'ai-seo-client') . "</h2>\n\n" . $conclusion;

        // Generate meta description
        $metaDesc = $this->generateMetaDescription($keyword, $title);

        $result = [
            'title' => $title,
            'content' => $article,
            'meta_description' => is_wp_error($metaDesc) ? '' : $metaDesc,
            'word_count' => str_word_count(strip_tags($article)),
            'keyword' => $keyword,
        ];

        // Create WordPress draft if requested
        if (!empty($options['create_draft'])) {
            $postId = $this->createDraft($result);
            if (!is_wp_error($postId)) {
                $result['post_id'] = $postId;
                $result['edit_url'] = get_edit_post_link($postId, '');
            }
        }

        return $result;
    }

    /**
     * Generate a single section of content.
     */
    public function writeSection(string $keyword, string $heading, array $options = []): string|\WP_Error
    {
        $tone = $options['tone'] ?? 'professional';
        $targetWords = $options['word_count'] ?? 300;
        $context = $options['context'] ?? '';

        $prompt = "Write a section for an article about \"{$keyword}\".

Section heading: {$heading}
" . ($context ? "Context from previous sections: {$context}\n" : "") . "
Requirements:
- Write approximately {$targetWords} words
- Tone: {$tone}
- Use clear, scannable paragraphs (3-4 sentences each)
- Include relevant examples or data where appropriate
- Include the keyword \"{$keyword}\" naturally 1-2 times
- Do NOT include the heading itself — I will add it separately
- Use HTML formatting (paragraphs in <p> tags, lists in <ul>/<li>, bold with <strong>)
- Do NOT wrap in unnecessary divs or extra HTML

Write the section content:";

        $result = $this->llm->call($prompt, null, max(800, $targetWords * 2), null, [], 'content_generation');
        if (is_wp_error($result)) {
            return $result;
        }

        return $this->cleanHtmlOutput($result['text']);
    }

    /**
     * Generate an SEO-optimized title.
     */
    private function generateTitle(string $keyword, ?array $brief = null): string|\WP_Error
    {
        $serpTitles = '';
        if ($brief && !empty($brief['competitor_analysis']['serp_titles'])) {
            $serpTitles = "\nTop-ranking titles for reference:\n" . implode("\n", array_slice($brief['competitor_analysis']['serp_titles'], 0, 5));
        }

        $prompt = "Generate 1 SEO-optimized blog post title for the keyword: \"{$keyword}\".
{$serpTitles}
Requirements:
- Include the exact keyword or close variant
- 50-60 characters ideal
- Compelling and click-worthy
- Use power words or numbers if appropriate

Return ONLY the title, nothing else.";

        $result = $this->llm->call($prompt, null, 100, null, [], 'meta_optimization');
        if (is_wp_error($result)) {
            return $result;
        }

        return trim(str_replace(['"', "'"], '', $result['text']));
    }

    /**
     * Generate article introduction.
     */
    private function generateIntro(string $keyword, string $title, string $tone, ?array $brief = null): string|\WP_Error
    {
        $intent = $brief['search_intent'] ?? 'informational';

        $prompt = "Write an engaging introduction (150-200 words) for an article titled: \"{$title}\"
Target keyword: \"{$keyword}\"
Search intent: {$intent}
Tone: {$tone}

Requirements:
- Hook the reader in the first sentence
- Include the keyword in the first 100 words
- Address the reader's search intent
- Set expectations for what the article covers
- Use HTML <p> tags for paragraphs
- Do NOT include the title

Write the introduction:";

        $result = $this->llm->call($prompt, null, 500, null, [], 'content_generation');
        if (is_wp_error($result)) {
            return $result;
        }

        return $this->cleanHtmlOutput($result['text']);
    }

    /**
     * Generate conclusion.
     */
    private function generateConclusion(string $keyword, string $title, string $tone): string|\WP_Error
    {
        $prompt = "Write a conclusion (100-150 words) for an article titled: \"{$title}\"
Target keyword: \"{$keyword}\"
Tone: {$tone}

Requirements:
- Summarize the key takeaways
- Include a clear call-to-action
- Include the keyword once naturally
- Use HTML <p> tags
- Do NOT include a heading

Write the conclusion:";

        $result = $this->llm->call($prompt, null, 400);
        if (is_wp_error($result)) {
            return $result;
        }

        return $this->cleanHtmlOutput($result['text']);
    }

    /**
     * Generate FAQ section.
     */
    private function generateFAQ(string $keyword, array $questions, string $tone): string
    {
        $qList = implode("\n", array_slice($questions, 0, 5));

        $prompt = "Write short, clear answers (2-3 sentences each) for these FAQ questions about \"{$keyword}\".
Tone: {$tone}

Questions:
{$qList}

Format each as:
<h3>Question?</h3>
<p>Answer.</p>

Write the FAQ:";

        $result = $this->llm->call($prompt, null, 1500);
        if (is_wp_error($result)) {
            return '';
        }

        return $this->cleanHtmlOutput($result['text']);
    }

    /**
     * Generate meta description.
     */
    private function generateMetaDescription(string $keyword, string $title): string|\WP_Error
    {
        $prompt = "Write a meta description (max 155 characters) for an article titled: \"{$title}\"
Include the keyword: \"{$keyword}\"
Make it compelling with a call-to-action.
Return ONLY the meta description, nothing else.";

        $result = $this->llm->call($prompt, null, 100);
        if (is_wp_error($result)) {
            return $result;
        }

        return substr(trim($result['text']), 0, 160);
    }

    /**
     * Build article sections from outline or brief.
     */
    private function buildSections(string $keyword, string $title, string $outline, ?array $brief, string $tone, int $targetWords): array|\WP_Error
    {
        // Extract headings from brief or outline
        $headings = [];
        
        if ($brief && !empty($brief['recommended_headings'])) {
            $headings = $brief['recommended_headings'];
        } elseif ($outline) {
            // Parse headings from outline text
            $lines = explode("\n", $outline);
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^#{1,3}\s+(.+)/', $line, $m)) {
                    $headings[] = $m[1];
                } elseif (preg_match('/^H[23]:\s*(.+)/i', $line, $m)) {
                    $headings[] = $m[0];
                } elseif (preg_match('/^\d+\.\s+(.+)/', $line, $m) && strlen($m[1]) < 80) {
                    $headings[] = $m[1];
                }
            }
        }

        // Fallback: generate headings with AI
        if (empty($headings)) {
            $headings = $this->generateHeadings($keyword, $title);
            if (is_wp_error($headings)) {
                return $headings;
            }
        }

        // Calculate words per section
        $introConclWords = 350; // ~200 intro + ~150 conclusion
        $remainingWords = max(600, $targetWords - $introConclWords);
        $wordsPerSection = max(200, (int)round($remainingWords / count($headings)));

        $sections = [];
        $previousContext = '';

        foreach ($headings as $heading) {
            // Clean heading prefix
            $cleanHeading = preg_replace('/^H[23]:\s*/i', '', $heading);
            $isH3 = stripos($heading, 'H3') === 0;
            $tag = $isH3 ? 'h3' : 'h2';

            $sectionContent = $this->writeSection($keyword, $cleanHeading, [
                'tone' => $tone,
                'word_count' => $wordsPerSection,
                'context' => $previousContext,
            ]);

            if (is_wp_error($sectionContent)) {
                continue; // Skip failed sections rather than failing entirely
            }

            $sections[] = "<{$tag}>" . esc_html($cleanHeading) . "</{$tag}>\n\n" . $sectionContent;
            $previousContext = "Previous section was about: " . $cleanHeading;
        }

        if (empty($sections)) {
            return new \WP_Error('no_sections', __('Failed to generate any content sections', 'ai-seo-client'));
        }

        return $sections;
    }

    /**
     * Generate heading structure with AI.
     */
    private function generateHeadings(string $keyword, string $title): array|\WP_Error
    {
        $prompt = "Generate 5-7 H2 headings for an article titled: \"{$title}\"
Target keyword: \"{$keyword}\"

Requirements:
- Cover the topic comprehensively
- Include the keyword or synonym in 2-3 headings
- Make headings descriptive and engaging
- Return ONLY the headings, one per line, prefixed with 'H2: '";

        $result = $this->llm->call($prompt, null, 500);
        if (is_wp_error($result)) {
            return $result;
        }

        $lines = array_filter(array_map('trim', explode("\n", $result['text'])));
        $headings = [];
        foreach ($lines as $line) {
            $clean = preg_replace('/^[\-\*\d\.]+\s*/', '', $line);
            if (strlen($clean) > 5 && strlen($clean) < 100) {
                if (stripos($clean, 'H2:') !== 0 && stripos($clean, 'H3:') !== 0) {
                    $clean = 'H2: ' . $clean;
                }
                $headings[] = $clean;
            }
        }

        return $headings ?: ["H2: What is {$keyword}?", "H2: Benefits of {$keyword}", "H2: How to Get Started"];
    }

    /**
     * Create a WordPress draft from generated content.
     */
    private function createDraft(array $articleData): int|\WP_Error
    {
        $postId = wp_insert_post([
            'post_title' => $articleData['title'],
            'post_content' => $articleData['content'],
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'post_excerpt' => $articleData['meta_description'] ?? '',
        ]);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Set SEO meta
        update_post_meta($postId, '_sseo_ai_focus_keyphrase', $articleData['keyword']);
        update_post_meta($postId, '_sseo_ai_title', $articleData['title']);
        update_post_meta($postId, '_sseo_ai_description', $articleData['meta_description'] ?? '');
        update_post_meta($postId, '_sseo_ai_generated', true);
        update_post_meta($postId, '_sseo_ai_generated_at', current_time('mysql'));

        return $postId;
    }

    /**
     * Clean HTML output from LLM.
     */
    private function cleanHtmlOutput(string $html): string
    {
        // Remove markdown code blocks
        $html = preg_replace('/```html\s*/', '', $html);
        $html = preg_replace('/```\s*/', '', $html);
        
        // Ensure paragraphs are wrapped
        $html = trim($html);
        if (strpos($html, '<') === false) {
            // Plain text — wrap in paragraphs
            $paragraphs = array_filter(array_map('trim', preg_split('/\n{2,}/', $html)));
            $html = '<p>' . implode("</p>\n<p>", $paragraphs) . '</p>';
        }

        return $html;
    }

    /**
     * REST: Write full article.
     */
    public function restWriteArticle(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        if (!$keyword) {
            return new \WP_Error('no_keyword', __('Keyword is required', 'ai-seo-client'));
        }

        return $this->writeArticle($keyword, [
            'title' => sanitize_text_field($request->get_param('title') ?? ''),
            'tone' => sanitize_text_field($request->get_param('tone') ?? ''),
            'word_count' => (int)($request->get_param('word_count') ?: 1500),
            'outline' => $request->get_param('outline') ?? '',
            'brief_id' => sanitize_text_field($request->get_param('brief_id') ?? ''),
            'create_draft' => (bool)$request->get_param('create_draft'),
        ]);
    }

    /**
     * REST: Write a single section.
     */
    public function restWriteSection(\WP_REST_Request $request): array|\WP_Error
    {
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $heading = sanitize_text_field($request->get_param('heading'));

        if (!$keyword || !$heading) {
            return new \WP_Error('missing_params', __('Keyword and heading are required', 'ai-seo-client'));
        }

        $content = $this->writeSection($keyword, $heading, [
            'tone' => sanitize_text_field($request->get_param('tone') ?? ''),
            'word_count' => (int)($request->get_param('word_count') ?: 300),
            'context' => $request->get_param('context') ?? '',
        ]);

        if (is_wp_error($content)) {
            return $content;
        }

        return ['content' => $content, 'heading' => $heading];
    }

    /**
     * Render the Content Writer admin page.
     */
    public function renderPage(): void
    {
        $tones = ['professional', 'casual', 'academic', 'conversational', 'authoritative', 'friendly', 'technical'];
        $defaultWordCount = (int) $this->settings->get('default_word_count', 1500);
        ?>
        <div class="wrap aiseo-modern">
            <h1><?php esc_html_e('AI Content Writer', 'ai-seo-client'); ?></h1>
            <p class="description"><?php esc_html_e('Generate full SEO-optimized articles with AI. Optionally uses Content Brief data for data-driven writing.', 'ai-seo-client'); ?></p>

            <div style="max-width: 900px;">
                <div class="postbox" style="padding: 20px;">
                    <h2><?php esc_html_e('Article Settings', 'ai-seo-client'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="writer-keyword"><?php esc_html_e('Target Keyword', 'ai-seo-client'); ?></label></th>
                            <td><input type="text" id="writer-keyword" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('Enter your main keyword...', 'ai-seo-client'); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="writer-title"><?php esc_html_e('Title (optional)', 'ai-seo-client'); ?></label></th>
                            <td><input type="text" id="writer-title" class="regular-text" style="width:100%;" placeholder="<?php esc_attr_e('Leave empty for AI-generated title', 'ai-seo-client'); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="writer-tone"><?php esc_html_e('Tone', 'ai-seo-client'); ?></label></th>
                            <td>
                                <select id="writer-tone">
                                    <?php foreach ($tones as $t): ?>
                                    <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html(ucfirst($t)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="writer-words"><?php esc_html_e('Target Word Count', 'ai-seo-client'); ?></label></th>
                            <td>
                                <input type="range" id="writer-words" min="500" max="5000" step="100" value="<?php echo esc_attr($defaultWordCount); ?>" style="width:300px;">
                                <span id="writer-words-display"><?php echo esc_html($defaultWordCount); ?></span> <?php esc_html_e('words', 'ai-seo-client'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="writer-outline"><?php esc_html_e('Custom Outline (optional)', 'ai-seo-client'); ?></label></th>
                            <td>
                                <textarea id="writer-outline" rows="5" class="large-text" placeholder="<?php esc_attr_e("H2: First section\nH2: Second section\nH3: Subsection\n...", 'ai-seo-client'); ?>"></textarea>
                                <p class="description"><?php esc_html_e('Leave empty to auto-generate from keyword analysis.', 'ai-seo-client'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e('Options', 'ai-seo-client'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="writer-create-draft" checked> 
                                    <?php esc_html_e('Create WordPress draft automatically', 'ai-seo-client'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" class="button button-primary button-hero" id="writer-generate">
                            <?php esc_html_e('Generate Article', 'ai-seo-client'); ?>
                        </button>
                        <span class="spinner" id="writer-spinner" style="float:none;"></span>
                    </p>
                    <p id="writer-status" style="display:none; color: #666;"></p>
                </div>

                <div id="writer-result" style="display:none; margin-top: 20px;">
                    <div class="postbox" style="padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h2 id="writer-result-title" style="margin:0;"></h2>
                            <div>
                                <span id="writer-result-words" style="background: #e0e7ff; padding: 4px 12px; border-radius: 12px; font-size: 13px;"></span>
                                <a id="writer-edit-link" href="#" class="button" target="_blank" style="margin-left:10px;"><?php esc_html_e('Edit Draft', 'ai-seo-client'); ?></a>
                            </div>
                        </div>
                        <div id="writer-result-content" style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; padding: 20px; background: #fff; line-height: 1.7;"></div>
                        <p style="margin-top: 10px;">
                            <strong><?php esc_html_e('Meta Description:', 'ai-seo-client'); ?></strong>
                            <span id="writer-result-meta" style="color: #666;"></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#writer-words').on('input', function() {
                $('#writer-words-display').text($(this).val());
            });

            $('#writer-generate').on('click', function() {
                var keyword = $('#writer-keyword').val().trim();
                if (!keyword) return alert('<?php echo esc_js(__('Enter a keyword', 'ai-seo-client')); ?>');

                var btn = $(this);
                var spinner = $('#writer-spinner');
                var status = $('#writer-status');
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                status.show().text('<?php echo esc_js(__('Analyzing keyword and generating article... This may take 1-2 minutes.', 'ai-seo-client')); ?>');
                $('#writer-result').hide();
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                wp.apiFetch({
                    path: 'sseo-ai/v1/write-article',
                    method: 'POST',
                    data: {
                        keyword: keyword,
                        title: $('#writer-title').val().trim(),
                        tone: $('#writer-tone').val(),
                        word_count: parseInt($('#writer-words').val()),
                        outline: $('#writer-outline').val().trim(),
                        create_draft: $('#writer-create-draft').is(':checked')
                    }
                }).then(function(result) {
                    $('#writer-result-title').text(result.title);
                    $('#writer-result-words').text(result.word_count + ' words');
                    $('#writer-result-content').html(result.content);
                    $('#writer-result-meta').text(result.meta_description);
                    if (result.edit_url) {
                        $('#writer-edit-link').attr('href', result.edit_url).show();
                    } else {
                        $('#writer-edit-link').hide();
                    }
                    $('#writer-result').show();
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                    status.text('<?php echo esc_js(__('Article generated successfully!', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert('Error: ' + (err.message || 'Unknown error'));
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                    status.hide();
                }).finally(function() {
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            });
        });
        </script>
        <?php
    }
}

<?php

namespace SSEOAIClient;

/**
 * Fact Checker
 *
 * Runs an LLM self-check on generated content to identify potentially
 * inaccurate, misleading, or unverifiable claims. Produces warnings that
 * are stored as post meta and surfaced in the content-generation UI.
 *
 * This is an AI self-assessment, NOT external fact verification. Results
 * are clearly labelled as such.
 *
 * Available to all tiers — uses the cheapest available model path.
 */
class FactChecker
{
    private LLMClient $llm;

    private const META_KEY = '_sseo_ai_fact_check';

    public function __construct(LLMClient $llm)
    {
        $this->llm = $llm;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_sseo_ai_get_fact_check', [$this, 'ajaxGetFactCheck']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/fact-check/(?P<post_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetFactCheck'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/fact-check', [
            'methods' => 'POST',
            'callback' => [$this, 'restRunFactCheck'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Run a fact check on a post's content.
     *
     * @param int $postId
     * @return array{verdict: string, confidence: int, claims: array, warnings: array, checked_at: string, is_ai_assessment: bool}|\WP_Error
     */
    public function checkPost(int $postId): array|\WP_Error
    {
        $post = get_post($postId);
        if (!$post) {
            return new \WP_Error('not_found', __('Post not found', 'ai-seo-client'), ['status' => 404]);
        }

        $title = $post->post_title;
        $content = wp_strip_all_tags($post->post_content);
        $excerpt = substr($content, 0, 4000);

        $prompt = $this->buildPrompt($title, $excerpt);

        // Use the cheapest model path — analysis use case maps to a smaller model
        $result = $this->llm->call($prompt, null, 'fact_checker', 1000, [], 'analysis');
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $data = $this->parseJsonResponse($text);

        if (is_wp_error($data)) {
            // Retry with stricter prompt
            $retryPrompt = $prompt . "\n\nIMPORTANT: Return ONLY valid JSON. No markdown, no code fences.";
            $retryResult = $this->llm->call($retryPrompt, null, 'fact_checker', 1000, [], 'analysis');
            if (!is_wp_error($retryResult)) {
                $data = $this->parseJsonResponse($retryResult['text'] ?? '');
            }
        }

        if (is_wp_error($data)) {
            return $data;
        }

        $report = [
            'verdict' => $data['verdict'] ?? 'uncertain',
            'confidence' => (int)($data['confidence'] ?? 50),
            'claims' => $data['claims'] ?? [],
            'warnings' => $data['warnings'] ?? [],
            'checked_at' => current_time('mysql'),
            'is_ai_assessment' => true,
        ];

        // Store as post meta
        update_post_meta($postId, self::META_KEY, $report);

        return $report;
    }

    /**
     * Run a fact check on raw content (not yet saved as a post).
     *
     * @param string $title
     * @param string $content
     * @return array|\WP_Error
     */
    public function checkContent(string $title, string $content): array|\WP_Error
    {
        $excerpt = substr(wp_strip_all_tags($content), 0, 4000);
        $prompt = $this->buildPrompt($title, $excerpt);

        $result = $this->llm->call($prompt, null, 'fact_checker', 1000, [], 'analysis');
        if (is_wp_error($result)) {
            return $result;
        }

        $data = $this->parseJsonResponse($result['text'] ?? '');
        if (is_wp_error($data)) {
            return $data;
        }

        return [
            'verdict' => $data['verdict'] ?? 'uncertain',
            'confidence' => (int)($data['confidence'] ?? 50),
            'claims' => $data['claims'] ?? [],
            'warnings' => $data['warnings'] ?? [],
            'checked_at' => current_time('mysql'),
            'is_ai_assessment' => true,
        ];
    }

    /**
     * Get stored fact check result for a post.
     */
    public function getPostFactCheck(int $postId): ?array
    {
        $meta = get_post_meta($postId, self::META_KEY, true);
        return is_array($meta) ? $meta : null;
    }

    /**
     * Build the fact-check prompt.
     */
    private function buildPrompt(string $title, string $content): string
    {
        return "You are a fact-checking assistant. Review the following content for factual accuracy.
Identify any claims that are potentially false, misleading, outdated, or unverifiable.

Content title: {$title}

Content:
{$content}

Return ONLY a JSON object with this structure:
{
    \"verdict\": \"supported\" | \"uncertain\" | \"questionable\",
    \"confidence\": 0-100,
    \"claims\": [
        {
            \"claim\": \"the specific claim made in the content\",
            \"assessment\": \"supported\" | \"uncertain\" | \"questionable\",
            \"explanation\": \"brief explanation of why this claim is assessed this way\"
        }
    ],
    \"warnings\": [
        \"specific warning about a potentially inaccurate or unverifiable statement\"
    ]
}

Guidelines:
- \"supported\" means the claims appear factually reasonable and well-established.
- \"uncertain\" means some claims cannot be verified without external sources.
- \"questionable\" means one or more claims appear potentially false or misleading.
- Focus on factual claims (statistics, dates, definitions, cause-effect statements).
- Do not flag opinions or subjective statements as factual errors.
- Be conservative — only flag claims you have reasonable doubt about.
- This is a self-assessment by an AI, not external fact verification.

Return ONLY the JSON.";
    }

    /**
     * Parse JSON from the LLM response.
     */
    private function parseJsonResponse(string $text): array|\WP_Error
    {
        $text = trim($text);
        // Strip markdown code fences
        $text = preg_replace('/^```(?:json)?\s*\n?/i', '', $text);
        $text = preg_replace('/\n?```\s*$/', '', $text);

        // Extract JSON object
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $data = json_decode(trim($text), true);
        if (!is_array($data)) {
            return new \WP_Error('parse_error', __('Could not parse fact-check response', 'ai-seo-client'));
        }

        return $data;
    }

    // REST: Get stored fact check
    public function restGetFactCheck(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int)$request->get_param('post_id');
        $report = $this->getPostFactCheck($postId);
        if ($report === null) {
            return new \WP_Error('not_checked', __('No fact check available for this post', 'ai-seo-client'), ['status' => 404]);
        }
        return $report;
    }

    // REST: Run fact check
    public function restRunFactCheck(\WP_REST_Request $request): array|\WP_Error
    {
        $postId = (int)$request->get_param('post_id');
        if (empty($postId)) {
            return new \WP_Error('missing_post_id', __('Post ID required', 'ai-seo-client'), ['status' => 400]);
        }
        return $this->checkPost($postId);
    }

    // AJAX: Get fact check
    public function ajaxGetFactCheck(): void
    {
        check_ajax_referer('sseo_ai_admin', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $postId = (int)($_POST['post_id'] ?? 0);
        $report = $this->getPostFactCheck($postId);
        wp_send_json_success(['report' => $report]);
    }

    /**
     * Render a fact-check warning badge for the post meta box.
     */
    public function renderFactCheckBadge(int $postId): string
    {
        $report = $this->getPostFactCheck($postId);
        if ($report === null) {
            return '';
        }

        $verdict = $report['verdict'] ?? 'uncertain';
        $warnings = $report['warnings'] ?? [];
        $verdictColors = [
            'supported' => '#16a34a',
            'uncertain' => '#d97706',
            'questionable' => '#dc2626',
        ];
        $verdictLabels = [
            'supported' => __('Fact check: supported', 'ai-seo-client'),
            'uncertain' => __('Fact check: uncertain', 'ai-seo-client'),
            'questionable' => __('Fact check: questionable', 'ai-seo-client'),
        ];
        $color = $verdictColors[$verdict] ?? '#6b7280';
        $label = $verdictLabels[$verdict] ?? __('Fact check: unknown', 'ai-seo-client');
        $warningCount = count($warnings);

        $html = '<div class="sseo-fact-check-badge" style="padding:10px 14px;border-radius:8px;margin:10px 0;background:'
            . $color . '15;border:1px solid ' . $color . '40;">';
        $html .= '<div style="display:flex;align-items:center;gap:8px;">';
        $html .= '<span style="color:' . $color . ';font-weight:600;">&#9888; ' . esc_html($label) . '</span>';
        if ($warningCount > 0) {
            $html .= '<span style="background:' . $color . ';color:#fff;font-size:11px;padding:2px 8px;border-radius:12px;">'
                . $warningCount . '</span>';
        }
        $html .= '</div>';

        if (!empty($warnings)) {
            $html .= '<ul style="margin:8px 0 0 0;padding-left:20px;color:#475569;font-size:13px;">';
            foreach ($warnings as $warning) {
                $html .= '<li>' . esc_html($warning) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '<p style="margin:8px 0 0 0;font-size:11px;color:#6b7280;">'
            . esc_html__('This is an AI self-assessment, not external fact verification.', 'ai-seo-client')
            . '</p>';
        $html .= '</div>';

        return $html;
    }
}

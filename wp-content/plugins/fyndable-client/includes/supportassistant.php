<?php

namespace SSEOAIClient;

/**
 * Support Assistant — sticky chatbot widget
 *
 * Renders a floating chatbot (sticky bottom-right) in the Fyndable dashboard
 * shell and on post/page edit screens. Answers questions from the bundled
 * knowledge base (GEBRUIKERSHANDLEIDING.md → JSON) with a hybrid approach:
 * local keyword search first, LLM fallback when confidence is low.
 * When no satisfactory answer is found, the user can create a support ticket
 * directly from the chat — submitted via the existing DashboardAPI.
 */
class SupportAssistant
{
    private LlmClient $llmClient;
    private DashboardAPI $dashboardAPI;
    private LicenseValidator $licenseValidator;

    private const REST_NAMESPACE = 'sseo-ai/v1';
    private const KB_OPTION_KEY = 'sseo_ai_chatbot_config';
    private const CONFIDENCE_THRESHOLD = 0.3;

    /** @var array|null Cached knowledge base entries */
    private static ?array $kbCache = null;

    public function __construct(
        LlmClient $llmClient,
        DashboardAPI $dashboardAPI,
        LicenseValidator $licenseValidator
    ) {
        $this->llmClient = $llmClient;
        $this->dashboardAPI = $dashboardAPI;
        $this->licenseValidator = $licenseValidator;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }

    /**
     * Register REST API routes for the chatbot.
     */
    public function registerRestRoutes(): void
    {
        register_rest_route(self::REST_NAMESPACE, '/support-assistant/search', [
            'methods' => 'POST',
            'callback' => [$this, 'restSearch'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'question' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/support-assistant/ask', [
            'methods' => 'POST',
            'callback' => [$this, 'restAsk'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'question' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'history' => ['required' => false, 'type' => 'array'],
            ],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/support-assistant/ticket', [
            'methods' => 'POST',
            'callback' => [$this, 'restCreateTicket'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'subject' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'message' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            ],
        ]);
    }

    /**
     * Enqueue widget assets on admin pages where the chatbot should appear.
     */
    public function enqueueAssets(string $hook): void
    {
        if ($this->isDisabled()) {
            return;
        }

        $shouldEnqueue = $this->shouldEnqueueOnHook($hook);
        if (!$shouldEnqueue) {
            return;
        }

        $this->doEnqueue();
    }

    /**
     * Enqueue on Gutenberg editor screens.
     */
    public function enqueueBlockEditorAssets(): void
    {
        if ($this->isDisabled()) {
            return;
        }

        $this->doEnqueue();
    }

    /**
     * Check whether the chatbot has been disabled by the site admin
     * via the Support page toggle.
     */
    private function isDisabled(): bool
    {
        return (bool) get_option('sseo_ai_chatbot_disabled', 0);
    }

    /**
     * Determine whether the chatbot should be enqueued for a given admin hook.
     */
    private function shouldEnqueueOnHook(string $hook): bool
    {
        // Dashboard shell (top-level Fyndable page)
        if ($hook === 'toplevel_page_fyndable-dashboard') {
            return true;
        }

        // Classic editor post/page edit screens
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            return true;
        }

        // Any Fyndable admin page (iframe content inside the shell)
        if (strpos($hook, 'ai-seo') !== false || strpos($hook, 'fyndable') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Actually enqueue + localize the widget assets.
     */
    private function doEnqueue(): void
    {
        wp_enqueue_style(
            'sseo-ai-support-assistant',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/support-assistant.css',
            [],
            SSEO_AI_CLIENT_VERSION . '.' . filemtime(SSEO_AI_CLIENT_PLUGIN_DIR . 'assets/support-assistant.css')
        );

        wp_enqueue_script(
            'sseo-ai-support-assistant',
            SSEO_AI_CLIENT_PLUGIN_URL . 'assets/support-assistant.js',
            [],
            SSEO_AI_CLIENT_VERSION . '.' . filemtime(SSEO_AI_CLIENT_PLUGIN_DIR . 'assets/support-assistant.js'),
            true
        );

        $config = $this->getChatbotConfig();
        $whiteLabel = get_option('sseo_ai_white_label', []);
        $primaryColor = !empty($whiteLabel['primary_color'])
            ? (sanitize_hex_color($whiteLabel['primary_color']) ?: '#379fd3')
            : '#379fd3';
        $secondaryColor = !empty($whiteLabel['secondary_color'])
            ? (sanitize_hex_color($whiteLabel['secondary_color']) ?: '#8f39ac')
            : '#8f39ac';

        $suggestedQuestions = $this->getSuggestedQuestions();

        wp_localize_script('sseo-ai-support-assistant', 'SupportAssistantSettings', [
            'restUrl' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'licenseValid' => $this->licenseValidator->isLicenseValid(),
            'chatbot' => [
                'name' => $config['name'] ?? __('Fyndable Assistant', 'ai-seo-client'),
                'avatarUrl' => $config['avatar_url'] ?? '',
            ],
            'colors' => [
                'primary' => $primaryColor,
                'secondary' => $secondaryColor,
            ],
            'suggestedQuestions' => $suggestedQuestions,
            'i18n' => [
                'title' => $config['name'] ?? __('Fyndable Assistant', 'ai-seo-client'),
                'placeholder' => __('Stel je vraag...', 'ai-seo-client'),
                'send' => __('Verstuur', 'ai-seo-client'),
                'sourceManual' => __('Uit handleiding', 'ai-seo-client'),
                'sourceAi' => __('AI-assistent', 'ai-seo-client'),
                'createTicket' => __('Maak support ticket', 'ai-seo-client'),
                'createTicketDirect' => __('Maak direct een support ticket', 'ai-seo-client'),
                'ticketSubject' => __('Onderwerp', 'ai-seo-client'),
                'ticketMessage' => __('Bericht', 'ai-seo-client'),
                'ticketSubmit' => __('Verstuur ticket', 'ai-seo-client'),
                'ticketSuccess' => __('Ticket aangemaakt! Ticket #%d', 'ai-seo-client'),
                'ticketError' => __('Kon ticket niet aanmaken. Probeer het later opnieuw.', 'ai-seo-client'),
                'loading' => __('Bezig met denken...', 'ai-seo-client'),
                'greeting' => __('Hoi! Ik kan je helpen met vragen over Fyndable. Waar kan ik je mee helpen?', 'ai-seo-client'),
                'noAnswer' => __('Sorry, daar weet ik geen antwoord op. Wil je een support ticket aanmaken?', 'ai-seo-client'),
                'viewTicket' => __('Bekijk ticket', 'ai-seo-client'),
                'supportPageUrl' => esc_url(admin_url('admin.php?page=ai-seo-support')),
            ],
        ]);
    }

    /**
     * Get the chatbot config (synced from SaaS dashboard via tenant/status).
     */
    private function getChatbotConfig(): array
    {
        $defaults = [
            'name' => __('Fyndable Assistant', 'ai-seo-client'),
            'avatar_url' => '',
            'knowledge' => '',
        ];

        $stored = get_option(self::KB_OPTION_KEY, []);
        if (!is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    /**
     * Get suggested questions from the KB (top entries by category).
     */
    private function getSuggestedQuestions(): array
    {
        $kb = $this->loadKnowledgeBase();
        $suggestions = [];
        $seenCategories = [];

        foreach ($kb as $entry) {
            $cat = $entry['category'] ?? 'Overig';
            if (isset($seenCategories[$cat]) && $seenCategories[$cat] >= 2) {
                continue;
            }
            $suggestions[] = $entry['title'];
            $seenCategories[$cat] = ($seenCategories[$cat] ?? 0) + 1;
            if (count($suggestions) >= 6) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * Load the knowledge base JSON (with static caching).
     */
    private function loadKnowledgeBase(): array
    {
        if (self::$kbCache !== null) {
            return self::$kbCache;
        }

        $path = SSEO_AI_CLIENT_PLUGIN_DIR . 'assets/support-knowledge-base.json';
        if (!file_exists($path)) {
            self::$kbCache = [];
            return self::$kbCache;
        }

        $contents = file_get_contents($path);
        $data = json_decode($contents, true);

        if (!is_array($data) || empty($data['entries'])) {
            self::$kbCache = [];
            return self::$kbCache;
        }

        self::$kbCache = $data['entries'];
        return self::$kbCache;
    }

    /**
     * REST: local keyword search only.
     */
    public function restSearch(\WP_REST_Request $request): \WP_REST_Response
    {
        $question = strtolower(trim($request->get_param('question')));

        if ($question === '') {
            return new \WP_REST_Response([
                'success' => true,
                'answer' => '',
                'source' => 'manual',
                'confidence' => 0,
                'section_id' => null,
            ], 200);
        }

        $result = $this->searchKnowledgeBase($question);

        return new \WP_REST_Response([
            'success' => true,
            'answer' => $result['answer'],
            'source' => 'manual',
            'confidence' => $result['confidence'],
            'section_id' => $result['section_id'],
        ], 200);
    }

    /**
     * REST: hybrid ask (local search → LLM fallback → ticket suggestion).
     */
    public function restAsk(\WP_REST_Request $request): \WP_REST_Response
    {
        $question = trim($request->get_param('question'));
        $history = $request->get_param('history') ?? [];

        if ($question === '') {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Question is required.', 'ai-seo-client'),
            ], 400);
        }

        // Step 1: local keyword search
        $local = $this->searchKnowledgeBase(strtolower($question));

        if ($local['confidence'] >= self::CONFIDENCE_THRESHOLD) {
            return new \WP_REST_Response([
                'success' => true,
                'answer' => $local['answer'],
                'source' => 'manual',
                'confidence' => $local['confidence'],
                'ticket_suggested' => false,
            ], 200);
        }

        // Step 2: LLM fallback (if license valid + AI available)
        if ($this->licenseValidator->isLicenseValid() && $this->llmClient->isAvailable()) {
            $aiAnswer = $this->askLlm($question, $history, $local);

            if (!is_wp_error($aiAnswer) && !empty($aiAnswer['text'])) {
                return new \WP_REST_Response([
                    'success' => true,
                    'answer' => $aiAnswer['text'],
                    'source' => 'ai',
                    'confidence' => 1,
                    'ticket_suggested' => false,
                ], 200);
            }
        }

        // Step 3: no answer → suggest ticket
        $fallbackAnswer = $local['answer'] !== ''
            ? $local['answer']
            : __('Sorry, daar weet ik geen antwoord op. Wil je een support ticket aanmaken?', 'ai-seo-client');

        return new \WP_REST_Response([
            'success' => true,
            'answer' => $fallbackAnswer,
            'source' => $local['confidence'] > 0 ? 'manual' : 'none',
            'confidence' => $local['confidence'],
            'ticket_suggested' => true,
        ], 200);
    }

    /**
     * REST: create a support ticket from the chat.
     */
    public function restCreateTicket(\WP_REST_Request $request): \WP_REST_Response
    {
        $subject = trim($request->get_param('subject'));
        $message = trim($request->get_param('message'));

        if (empty($subject) || empty($message)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Subject and message are required.', 'ai-seo-client'),
            ], 400);
        }

        $result = $this->dashboardAPI->createSupportTicket($subject, $message, 'middle', []);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 500);
        }

        $ticketId = $result['ticket_id'] ?? 0;

        return new \WP_REST_Response([
            'success' => true,
            'ticket_id' => $ticketId,
            'support_url' => esc_url(admin_url('admin.php?page=ai-seo-support&view=' . $ticketId)),
        ], 201);
    }

    /**
     * Search the knowledge base by keyword matching.
     * Returns the best matching entry with a confidence score.
     */
    private function searchKnowledgeBase(string $question): array
    {
        $kb = $this->loadKnowledgeBase();
        if (empty($kb)) {
            return ['answer' => '', 'confidence' => 0, 'section_id' => null];
        }

        // Merge in custom knowledge from chatbot config (admin-uploaded .md/.json)
        $config = $this->getChatbotConfig();
        $customEntries = $this->parseCustomKnowledge($config['knowledge'] ?? '');
        $allEntries = array_merge($kb, $customEntries);

        $questionWords = $this->tokenize($question);
        if (empty($questionWords)) {
            return ['answer' => '', 'confidence' => 0, 'section_id' => null];
        }

        $bestScore = 0;
        $bestEntry = null;

        foreach ($allEntries as $entry) {
            $score = $this->scoreEntry($entry, $question, $questionWords);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestEntry = $entry;
            }
        }

        if ($bestEntry === null || $bestScore === 0) {
            return ['answer' => '', 'confidence' => 0, 'section_id' => null];
        }

        // Normalize confidence: a score of 2+ keyword matches = high confidence
        $confidence = min(1, $bestScore / 2);

        return [
            'answer' => $bestEntry['answer'] ?? '',
            'confidence' => round($confidence, 2),
            'section_id' => $bestEntry['id'] ?? null,
        ];
    }

    /**
     * Score an entry against the question.
     */
    private function scoreEntry(array $entry, string $question, array $questionWords): float
    {
        $score = 0;
        $keywords = $entry['keywords'] ?? [];
        $title = strtolower($entry['title'] ?? '');

        foreach ($keywords as $keyword) {
            $keywordLower = strtolower($keyword);

            // Exact keyword in question
            if (strpos($question, $keywordLower) !== false) {
                $score += 1.0;
            }

            // Keyword word overlap with question words
            $keywordWords = $this->tokenize($keywordLower);
            $overlap = count(array_intersect($keywordWords, $questionWords));
            if ($overlap > 0) {
                $score += $overlap * 0.5;
            }
        }

        // Title word overlap
        $titleWords = $this->tokenize($title);
        $titleOverlap = count(array_intersect($titleWords, $questionWords));
        if ($titleOverlap > 0) {
            $score += $titleOverlap * 0.3;
        }

        return $score;
    }

    /**
     * Tokenize a string into lowercase words.
     */
    private function tokenize(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Filter out very short tokens and common Dutch/English stop words
        $stopWords = [
            'de', 'het', 'een', 'en', 'van', 'in', 'is', 'te', 'dat', 'die', 'op', 'voor', 'met', 'niet',
            'the', 'a', 'an', 'and', 'of', 'in', 'is', 'to', 'that', 'on', 'for', 'with', 'not', 'how',
            'wat', 'hoe', 'waar', 'wie', 'waarom', 'kan', 'ik', 'mijn', 'we', 'onze', 'je', 'jouw',
            'my', 'our', 'your', 'i', 'me', 'we',
        ];

        return array_values(array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopWords, true)));
    }

    /**
     * Parse custom knowledge text (.md or .json) into KB entries.
     */
    private function parseCustomKnowledge(string $knowledge): array
    {
        if (empty(trim($knowledge))) {
            return [];
        }

        $trimmed = trim($knowledge);

        // Try JSON first
        $json = json_decode($trimmed, true);
        if (is_array($json)) {
            // Could be a full KB object with "entries" or a flat array
            $entries = $json['entries'] ?? $json;
            if (is_array($entries)) {
                $parsed = [];
                foreach ($entries as $entry) {
                    if (!is_array($entry) || empty($entry['answer'])) {
                        continue;
                    }
                    $parsed[] = [
                        'id' => 'custom-' . ($entry['id'] ?? sanitize_title($entry['title'] ?? uniqid())),
                        'title' => $entry['title'] ?? '',
                        'category' => $entry['category'] ?? 'Custom',
                        'keywords' => $entry['keywords'] ?? [],
                        'answer' => $entry['answer'] ?? '',
                    ];
                }
                return $parsed;
            }
        }

        // Treat as markdown: split on ## headers, each section becomes an entry
        $sections = preg_split('/^##\s+/m', $trimmed);
        $entries = [];
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) {
                continue;
            }
            $lines = explode("\n", $section, 2);
            $title = trim($lines[0]);
            $body = trim($lines[1] ?? '');
            if (empty($title) || empty($body)) {
                continue;
            }
            // Auto-generate keywords from title words
            $keywords = $this->tokenize($title);
            $entries[] = [
                'id' => 'custom-' . sanitize_title($title),
                'title' => $title,
                'category' => 'Custom',
                'keywords' => $keywords,
                'answer' => $body,
            ];
        }

        return $entries;
    }

    /**
     * Ask the LLM with KB context as system prompt.
     */
    private function askLlm(string $question, array $history, array $localResult): array|\WP_Error
    {
        $config = $this->getChatbotConfig();
        $kb = $this->loadKnowledgeBase();
        $customEntries = $this->parseCustomKnowledge($config['knowledge'] ?? '');
        $allEntries = array_merge($kb, $customEntries);

        // Build context from top 5 most relevant entries
        $questionLower = strtolower($question);
        $questionWords = $this->tokenize($questionLower);
        $scored = [];

        foreach ($allEntries as $entry) {
            $score = $this->scoreEntry($entry, $questionLower, $questionWords);
            if ($score > 0) {
                $scored[] = ['score' => $score, 'entry' => $entry];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $topEntries = array_slice($scored, 0, 5);

        $contextParts = [];
        foreach ($topEntries as $item) {
            $entry = $item['entry'];
            $contextParts[] = "### " . ($entry['title'] ?? 'Onbekend') . "\n" . ($entry['answer'] ?? '');
        }

        $context = implode("\n\n", $contextParts);
        $botName = $config['name'] ?? 'Fyndable Assistant';

        $systemPrompt = "Je bent {$botName}, een behulpzame support-assistent voor de Fyndable SEO-plugin. " .
            "Beantwoord vragen van gebruikers in het Nederlands op basis van de onderstaande handleiding-kennis. " .
            "Wees beknopt, vriendelijk en praktisch. Als je het antwoord niet zeker weet, zeg dat dan eerlijk.\n\n" .
            "Handleiding-kennis:\n" . $context;

        // Build conversation with history
        $prompt = $question;
        if (!empty($history) && is_array($history)) {
            $historyText = '';
            $recentHistory = array_slice($history, -4); // last 4 messages
            foreach ($recentHistory as $msg) {
                $role = $msg['role'] ?? '';
                $content = $msg['content'] ?? '';
                if ($role === 'user') {
                    $historyText .= "Gebruiker: {$content}\n";
                } elseif ($role === 'assistant') {
                    $historyText .= "Assistent: {$content}\n";
                }
            }
            if ($historyText) {
                $prompt = "Eerder gesprek:\n" . $historyText . "\nNieuwe vraag: {$question}";
            }
        }

        return $this->llmClient->call(
            $prompt,
            null, // let model routing decide
            $systemPrompt,
            800,
            ['context' => 'support_assistant'],
            'support_assistant'
        );
    }
}

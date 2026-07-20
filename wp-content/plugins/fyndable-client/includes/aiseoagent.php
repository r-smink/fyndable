<?php

namespace SSEOAIClient;

/**
 * AI SEO Agent
 *
 * A conversational interface that orchestrates the full SEO workflow:
 * research → cluster → brief → write → score → publish.
 *
 * The agent chains steps together, checking in with the user between major actions.
 * Supports auto-publish per content type once trust is established.
 *
 * Comparable to Frase Agent, Junia AI Agent.
 */
class AISeoAgent
{
    private LlmClient $llm;
    private Settings $settings;
    private ?TopicCluster $topicCluster = null;
    private ?ContentBrief $contentBrief = null;
    private ?ContentWriter $contentWriter = null;
    private ?TruSEOScore $truSEO = null;
    private ?SmartTags $smartTags = null;
    private ?FAQSchema $faqSchema = null;

    private const CONVERSATIONS_KEY = 'sseo_ai_agent_conversations';

    public function __construct(
        LlmClient $llm,
        Settings $settings,
        ?TopicCluster $topicCluster = null,
        ?ContentBrief $contentBrief = null,
        ?ContentWriter $contentWriter = null,
        ?TruSEOScore $truSEO = null,
        ?SmartTags $smartTags = null,
        ?FAQSchema $faqSchema = null
    ) {
        $this->llm = $llm;
        $this->settings = $settings;
        $this->topicCluster = $topicCluster;
        $this->contentBrief = $contentBrief;
        $this->contentWriter = $contentWriter;
        $this->truSEO = $truSEO;
        $this->smartTags = $smartTags;
        $this->faqSchema = $faqSchema;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'addMenu']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('AI SEO Agent', 'ai-seo-client'),
            __('AI Agent', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-agent',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/agent/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'restChat'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/agent/conversations', [
            'methods' => 'GET',
            'callback' => [$this, 'restListConversations'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/agent/conversations/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetConversation'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/agent/conversations/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteConversation'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/agent/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSettings'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/agent/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveSettings'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * Main chat endpoint — processes user message and executes actions.
     */
    public function restChat(\WP_REST_Request $request): array|\WP_Error
    {
        $message = sanitize_textarea_field($request->get_param('message') ?? '');
        $conversationId = (int) ($request->get_param('conversation_id') ?: 0);
        $approvedAction = sanitize_text_field($request->get_param('approved_action') ?? '');

        if (empty($message) && empty($approvedAction)) {
            return new \WP_Error('empty', __('Message or approved action required', 'ai-seo-client'), ['status' => 400]);
        }

        // Load or create conversation
        $conversations = get_option(self::CONVERSATIONS_KEY, []);
        $conversation = null;

        if ($conversationId > 0) {
            foreach ($conversations as $conv) {
                if (($conv['id'] ?? 0) === $conversationId) {
                    $conversation = $conv;
                    break;
                }
            }
        }

        if (!$conversation) {
            $conversationId = count($conversations) + 1;
            $conversation = [
                'id' => $conversationId,
                'title' => substr($message, 0, 50),
                'messages' => [],
                'context' => [],
                'created_at' => current_time('mysql'),
            ];
        }

        // Add user message
        if (!empty($message)) {
            $conversation['messages'][] = [
                'role' => 'user',
                'content' => $message,
                'timestamp' => current_time('mysql'),
            ];
        }

        // Process the message / approved action
        $result = $this->processMessage($message, $conversation, $approvedAction);

        // Add agent response
        $conversation['messages'][] = [
            'role' => 'assistant',
            'content' => $result['message'],
            'actions' => $result['actions'] ?? [],
            'data' => $result['data'] ?? null,
            'timestamp' => current_time('mysql'),
        ];

        // Update context
        if (isset($result['context_update'])) {
            $conversation['context'] = array_merge($conversation['context'] ?? [], $result['context_update']);
        }

        // Save conversation
        $found = false;
        foreach ($conversations as &$conv) {
            if (($conv['id'] ?? 0) === $conversationId) {
                $conv = $conversation;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $conversations[] = $conversation;
        }
        update_option(self::CONVERSATIONS_KEY, $conversations);

        return [
            'conversation_id' => $conversationId,
            'message' => $result['message'],
            'actions' => $result['actions'] ?? [],
            'data' => $result['data'] ?? null,
        ];
    }

    /**
     * Process user message and determine action.
     */
    private function processMessage(string $message, array $conversation, string $approvedAction = ''): array
    {
        $context = $conversation['context'] ?? [];
        $messageLower = strtolower($message);

        // Handle approved actions
        if (!empty($approvedAction)) {
            return $this->executeApprovedAction($approvedAction, $context);
        }

        // Detect intent from message
        $intent = $this->detectIntent($messageLower);

        switch ($intent) {
            case 'generate_cluster':
                return $this->handleGenerateCluster($message, $context);

            case 'write_article':
                return $this->handleWriteArticle($message, $context);

            case 'research_keywords':
                return $this->handleResearchKeywords($message, $context);

            case 'audit_site':
                return $this->handleAuditSite($message, $context);

            case 'status_check':
                return $this->handleStatusCheck($context);

            case 'help':
            default:
                return $this->handleHelp($message, $context);
        }
    }

    /**
     * Detect user intent from message text.
     */
    private function detectIntent(string $message): string
    {
        if (preg_match('/\b(cluster|topical authority|pillar|content map)\b/', $message)) {
            return 'generate_cluster';
        }
        if (preg_match('/\b(write|article|blog|content|draft|generate post)\b/', $message)) {
            return 'write_article';
        }
        if (preg_match('/\b(keyword|research|find keywords|seo research)\b/', $message)) {
            return 'research_keywords';
        }
        if (preg_match('/\b(audit|scan|check site|site health|seo score)\b/', $message)) {
            return 'audit_site';
        }
        if (preg_match('/\b(status|progress|queue|how.*doing|where.*stand)\b/', $message)) {
            return 'status_check';
        }
        return 'help';
    }

    /**
     * Handle cluster generation request.
     */
    private function handleGenerateCluster(string $message, array $context): array
    {
        // Extract topic from message
        $topic = $this->extractTopic($message);
        if (!$topic) {
            return [
                'message' => __('I can create a topic cluster map for you. What topic should I build the cluster around? For example: "Create a cluster about WordPress SEO".', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        if (!$this->topicCluster) {
            return [
                'message' => __('Topic Clusters feature is not available on your current plan. Upgrade to Professional or higher to use this feature.', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        // Generate cluster
        $result = $this->topicCluster->generateCluster($topic, 'standard', 'en');
        if (is_wp_error($result)) {
            return [
                'message' => sprintf(__('Failed to generate cluster: %s', 'ai-seo-client'), $result->get_error_message()),
                'actions' => [],
            ];
        }

        $pageCount = $result['total_pages'] ?? 0;
        $clusterCount = count($result['clusters'] ?? []);
        $pillarTitle = $result['pillar_page']['title'] ?? '';

        $message = sprintf(
            __("I've created a topic cluster map for \"%s\". Here's the overview:\n\n• Pillar page: %s\n• %d cluster hubs with supporting pages\n• %d total pages to create\n• Estimated timeline: %d months\n• Authority potential: %d/100\n\nWhat would you like to do next?", 'ai-seo-client'),
            $topic,
            $pillarTitle,
            $clusterCount,
            $pageCount,
            $result['estimated_months'] ?? 0,
            $result['topical_authority_score_potential'] ?? 0
        );

        return [
            'message' => $message,
            'actions' => [
                [
                    'id' => 'generate_all_content',
                    'label' => __('Generate all content in background', 'ai-seo-client'),
                    'description' => __('Queue all cluster pages for background generation with scheduling.', 'ai-seo-client'),
                    'requires_approval' => true,
                ],
                [
                    'id' => 'view_cluster',
                    'label' => __('View full cluster map', 'ai-seo-client'),
                    'description' => __('Open the Topic Clusters page to see the full map.', 'ai-seo-client'),
                    'url' => admin_url('admin.php?page=ai-seo-topic-clusters'),
                    'requires_approval' => false,
                ],
            ],
            'data' => [
                'cluster' => $result,
                'topic' => $topic,
            ],
            'context_update' => [
                'last_cluster' => $result,
                'last_topic' => $topic,
            ],
        ];
    }

    /**
     * Handle article writing request.
     */
    private function handleWriteArticle(string $message, array $context): array
    {
        $keyword = $this->extractKeyword($message);

        if (!$keyword && isset($context['last_topic'])) {
            $keyword = $context['last_topic'];
        }

        if (!$keyword) {
            return [
                'message' => __('I can write an SEO-optimized article for you. What keyword should I target? For example: "Write an article about email marketing tips".', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        if (!$this->contentWriter) {
            return [
                'message' => __('AI Content Writer is not available on your current plan. Upgrade to Business or higher to use this feature.', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        return [
            'message' => sprintf(__('I\'ll write an article targeting "%s". This will use a Content Brief for SERP-driven writing. Shall I proceed?', 'ai-seo-client'), $keyword),
            'actions' => [
                [
                    'id' => 'write_article_approved',
                    'label' => __('Yes, write the article', 'ai-seo-client'),
                    'requires_approval' => true,
                    'params' => ['keyword' => $keyword],
                ],
            ],
            'context_update' => [
                'pending_keyword' => $keyword,
            ],
        ];
    }

    /**
     * Handle keyword research request.
     */
    private function handleResearchKeywords(string $message, array $context): array
    {
        $topic = $this->extractTopic($message) ?: ($context['last_topic'] ?? '');

        if (!$topic) {
            return [
                'message' => __('I can research keywords for you. What topic or industry should I research? For example: "Research keywords for a fitness blog".', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        $prompt = "Generate 20 SEO keyword ideas for the topic: \"{$topic}\". For each keyword, provide:
- keyword
- estimated monthly search volume (low/medium/high)
- keyword difficulty (0-100)
- search intent (informational/commercial/transactional)

Return as JSON array.";

        $response = $this->llm->generateText($prompt, ['use_case' => 'keyword_research']);

        if (is_wp_error($response)) {
            return [
                'message' => sprintf(__('Keyword research failed: %s', 'ai-seo-client'), $response->get_error_message()),
                'actions' => [],
            ];
        }

        $keywords = json_decode(trim($response), true);
        if (!is_array($keywords)) {
            $keywords = [];
        }

        $message = sprintf(__("Here are keyword ideas for \"%s\":\n\n", 'ai-seo-client'), $topic);
        $count = 0;
        foreach ($keywords as $kw) {
            if (!is_array($kw)) continue;
            $count++;
            $message .= sprintf(
                "%d. %s (Volume: %s, Difficulty: %s, Intent: %s)\n",
                $count,
                $kw['keyword'] ?? 'Unknown',
                $kw['estimated_monthly_search_volume'] ?? $kw['volume'] ?? '—',
                $kw['keyword_difficulty'] ?? $kw['difficulty'] ?? '—',
                $kw['search_intent'] ?? $kw['intent'] ?? '—'
            );
            if ($count >= 15) break;
        }

        if ($count === 0) {
            $message = sprintf(__('I researched "%s" but couldn\'t parse the results. Please try the Keywords module directly.', 'ai-seo-client'), $topic);
        }

        return [
            'message' => $message,
            'actions' => [
                [
                    'id' => 'create_cluster_from_keywords',
                    'label' => __('Create a topic cluster from these keywords', 'ai-seo-client'),
                    'requires_approval' => true,
                    'params' => ['topic' => $topic],
                ],
            ],
            'context_update' => [
                'last_topic' => $topic,
                'last_keywords' => $keywords,
            ],
        ];
    }

    /**
     * Handle site audit request.
     */
    private function handleAuditSite(string $message, array $context): array
    {
        return [
            'message' => __('I can run a site audit for you. This will check your site for missing meta tags, thin content, missing alt texts, and internal linking issues. Shall I proceed?', 'ai-seo-client'),
            'actions' => [
                [
                    'id' => 'run_audit',
                    'label' => __('Yes, run the audit', 'ai-seo-client'),
                    'url' => admin_url('admin.php?page=ai-seo-dashboard'),
                    'requires_approval' => false,
                ],
            ],
        ];
    }

    /**
     * Handle status check.
     */
    private function handleStatusCheck(array $context): array
    {
        $queues = get_option('sseo_ai_cluster_queues', []);
        $activeQueues = array_filter($queues, fn($q) => in_array($q['status'] ?? '', ['pending', 'processing']));

        $message = '';

        if (empty($activeQueues)) {
            $message = __("No active background tasks. Here's what I can help with:\n• Generate a topic cluster\n• Write an article\n• Research keywords\n• Run a site audit", 'ai-seo-client');
        } else {
            $message = __("Active background tasks:\n\n", 'ai-seo-client');
            foreach ($activeQueues as $queue) {
                $completed = $queue['completed'] ?? 0;
                $failed = $queue['failed'] ?? 0;
                $total = $queue['total'] ?? 0;
                $message .= sprintf(
                    "• Queue #%d: %d/%d completed, %d failed (%s)\n",
                    $queue['id'] ?? 0,
                    $completed,
                    $total,
                    $failed,
                    $queue['status'] ?? 'pending'
                );
            }
        }

        if (isset($context['last_cluster'])) {
            $message .= sprintf(
                "\n\nLast cluster: \"%s\" with %d pages. You can generate content or view the full map.",
                $context['last_topic'] ?? '',
                $context['last_cluster']['total_pages'] ?? 0
            );
        }

        return [
            'message' => $message,
            'actions' => [],
        ];
    }

    /**
     * Handle help/default.
     */
    private function handleHelp(string $message, array $context): array
    {
        $help = __("I'm your AI SEO Agent. I can help you with:\n\n");
        $help .= __("• **Generate topic clusters** — \"Create a cluster about WordPress SEO\"\n");
        $help .= __("• **Write articles** — \"Write an article about email marketing\"\n");
        $help .= __("• **Research keywords** — \"Find keywords for a fitness blog\"\n");
        $help .= __("• **Audit your site** — \"Run a site audit\"\n");
        $help .= __("• **Check status** — \"What's the status of my queue?\"\n\n");
        $help .= __("Just tell me what you need in plain language!");

        return [
            'message' => $help,
            'actions' => [],
        ];
    }

    /**
     * Execute an approved action.
     */
    private function executeApprovedAction(string $actionId, array $context): array
    {
        // Parse action ID — may contain params like "write_article_approved:keyword=email marketing"
        $parts = explode(':', $actionId, 2);
        $action = $parts[0];
        $params = [];
        if (isset($parts[1])) {
            parse_str($parts[1], $params);
        }

        switch ($action) {
            case 'write_article_approved':
                $keyword = $params['keyword'] ?? ($context['pending_keyword'] ?? '');
                return $this->executeWriteArticle($keyword);

            case 'generate_all_content':
                $cluster = $context['last_cluster'] ?? null;
                $topic = $context['last_topic'] ?? '';
                return $this->executeGenerateAllContent($cluster, $topic);

            case 'create_cluster_from_keywords':
                $topic = $params['topic'] ?? ($context['last_topic'] ?? '');
                return $this->handleGenerateCluster('cluster about ' . $topic, $context);

            default:
                return [
                    'message' => sprintf(__('Unknown action: %s', 'ai-seo-client'), $actionId),
                    'actions' => [],
                ];
        }
    }

    /**
     * Execute article writing.
     */
    private function executeWriteArticle(string $keyword): array
    {
        if (!$this->contentWriter || empty($keyword)) {
            return [
                'message' => __('Cannot write article — Content Writer not available or no keyword specified.', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        $result = $this->contentWriter->writeArticle($keyword, [
            'create_draft' => true,
            'word_count' => 1500,
        ]);

        if (is_wp_error($result)) {
            return [
                'message' => sprintf(__('Article generation failed: %s', 'ai-seo-client'), $result->get_error_message()),
                'actions' => [],
            ];
        }

        $message = sprintf(
            __("✅ Article written and saved as draft!\n\n• Title: %s\n• Word count: %d\n• Keyword: %s\n\nYou can edit it now or publish it directly.", 'ai-seo-client'),
            $result['title'] ?? 'Untitled',
            $result['word_count'] ?? 0,
            $keyword
        );

        $editUrl = isset($result['edit_url']) ? $result['edit_url'] : (isset($result['post_id']) ? get_edit_post_link($result['post_id'], '') : '');

        return [
            'message' => $message,
            'actions' => [
                [
                    'id' => 'edit_article',
                    'label' => __('Edit draft', 'ai-seo-client'),
                    'url' => $editUrl,
                    'requires_approval' => false,
                ],
                [
                    'id' => 'write_another',
                    'label' => __('Write another article', 'ai-seo-client'),
                    'requires_approval' => false,
                ],
            ],
            'data' => $result,
            'context_update' => [
                'pending_keyword' => null,
            ],
        ];
    }

    /**
     * Execute bulk content generation for a cluster.
     */
    private function executeGenerateAllContent(?array $cluster, string $topic): array
    {
        if (!$cluster || !$this->topicCluster) {
            return [
                'message' => __('No cluster found to generate content for. Please create a cluster first.', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        // Build pages array from cluster
        $pages = [];
        if (isset($cluster['pillar_page'])) {
            $pages[] = [
                'title' => $cluster['pillar_page']['title'],
                'keyword' => $cluster['pillar_page']['target_keyword'],
                'word_count' => $cluster['pillar_page']['target_word_count'] ?? 3000,
                'content_type' => 'pillar',
            ];
        }
        foreach ($cluster['clusters'] ?? [] as $cl) {
            if (isset($cl['hub_page'])) {
                $pages[] = [
                    'title' => $cl['hub_page']['title'],
                    'keyword' => $cl['hub_page']['target_keyword'],
                    'word_count' => $cl['hub_page']['target_word_count'] ?? 1500,
                    'content_type' => 'hub',
                ];
            }
            foreach ($cl['supporting_pages'] ?? [] as $sp) {
                $pages[] = [
                    'title' => $sp['title'],
                    'keyword' => $sp['target_keyword'],
                    'word_count' => $sp['target_word_count'] ?? 800,
                    'content_type' => 'supporting',
                ];
            }
        }

        if (empty($pages)) {
            return [
                'message' => __('No pages found in the cluster to generate.', 'ai-seo-client'),
                'actions' => [],
            ];
        }

        // Submit to queue
        $startDate = date('Y-m-d 09:00:00', strtotime('+1 day'));
        $gapDays = max(1, min(7, intval(30 / count($pages))));

        // Simulate the REST request
        $request = new \WP_REST_Request('POST');
        $request->set_param('pages', $pages);
        $request->set_param('cluster_id', $cluster['id'] ?? 0);
        $request->set_param('cluster_map_id', $cluster['id'] ?? 0);
        $request->set_param('start_date', $startDate);
        $request->set_param('gap_days', $gapDays);

        $result = $this->topicCluster->restQueueBulkGeneration($request);

        if (is_wp_error($result)) {
            return [
                'message' => sprintf(__('Failed to queue content: %s', 'ai-seo-client'), $result->get_error_message()),
                'actions' => [],
            ];
        }

        $message = sprintf(
            __("✅ Queued %d pages for background generation!\n\n• Start date: %s\n• Gap between posts: %d days\n• Queue ID: #%d\n\nContent will be generated via WP-Cron. You can close this page — I'll check the status when you return.", 'ai-seo-client'),
            $result['total_items'] ?? count($pages),
            $startDate,
            $gapDays,
            $result['queue_id'] ?? 0
        );

        return [
            'message' => $message,
            'actions' => [
                [
                    'id' => 'check_status',
                    'label' => __('Check queue status', 'ai-seo-client'),
                    'requires_approval' => false,
                ],
                [
                    'id' => 'view_scheduled',
                    'label' => __('View scheduled posts', 'ai-seo-client'),
                    'url' => admin_url('edit.php?post_status=future&post_type=post'),
                    'requires_approval' => false,
                ],
            ],
            'data' => $result,
            'context_update' => [
                'last_queue_id' => $result['queue_id'] ?? 0,
            ],
        ];
    }

    /**
     * Extract topic from user message.
     */
    private function extractTopic(string $message): string
    {
        // Try patterns like "cluster about X", "topic X", "for X"
        if (preg_match('/(?:about|for|on|around)\s+["\']?([^"\']+)["\']?/i', $message, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/(?:cluster|topic)\s+["\']?([^"\']+)["\']?/i', $message, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Extract keyword from user message.
     */
    private function extractKeyword(string $message): string
    {
        if (preg_match('/(?:about|for|on|targeting)\s+["\']?([^"\']+)["\']?/i', $message, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    // REST: List conversations
    public function restListConversations(): array
    {
        $conversations = get_option(self::CONVERSATIONS_KEY, []);
        $list = [];
        foreach ($conversations as $conv) {
            $list[] = [
                'id' => $conv['id'] ?? 0,
                'title' => $conv['title'] ?? 'Untitled',
                'created_at' => $conv['created_at'] ?? '',
                'message_count' => count($conv['messages'] ?? []),
            ];
        }
        return $list;
    }

    // REST: Get conversation
    public function restGetConversation(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $conversations = get_option(self::CONVERSATIONS_KEY, []);
        foreach ($conversations as $conv) {
            if (($conv['id'] ?? 0) === $id) {
                return $conv;
            }
        }
        return new \WP_Error('not_found', __('Conversation not found', 'ai-seo-client'), ['status' => 404]);
    }

    // REST: Delete conversation
    public function restDeleteConversation(\WP_REST_Request $request): array
    {
        $id = (int) $request->get_param('id');
        $conversations = get_option(self::CONVERSATIONS_KEY, []);
        $conversations = array_values(array_filter($conversations, fn($c) => ($c['id'] ?? 0) !== $id));
        update_option(self::CONVERSATIONS_KEY, $conversations);
        return ['success' => true];
    }

    // REST: Get agent settings
    public function restGetSettings(): array
    {
        return get_option('sseo_ai_agent_settings', [
            'auto_publish' => false,
            'auto_publish_threshold' => 75,
            'default_word_count' => 1500,
            'default_language' => 'en',
        ]);
    }

    // REST: Save agent settings
    public function restSaveSettings(\WP_REST_Request $request): array
    {
        $settings = [
            'auto_publish' => (bool) $request->get_param('auto_publish'),
            'auto_publish_threshold' => (int) $request->get_param('auto_publish_threshold'),
            'default_word_count' => (int) $request->get_param('default_word_count'),
            'default_language' => sanitize_text_field($request->get_param('default_language') ?? 'en'),
        ];
        update_option('sseo_ai_agent_settings', $settings);
        return ['success' => true];
    }

    /**
     * Render admin page
     */
    public function renderPage(): void
    {
        ?>
        <style>
            .agent-wrap { max-width: 900px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .agent-chat { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
            .agent-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 20px 30px; }
            .agent-header h1 { margin: 0; font-size: 22px; }
            .agent-header p { margin: 5px 0 0 0; opacity: 0.7; font-size: 13px; }
            .agent-messages { max-height: 500px; overflow-y: auto; padding: 20px 30px; }
            .agent-msg { margin-bottom: 20px; }
            .agent-msg-user { text-align: right; }
            .agent-msg-user .agent-bubble { background: #2563eb; color: #fff; margin-left: 60px; }
            .agent-msg-assistant .agent-bubble { background: #f1f5f9; color: #1e293b; margin-right: 60px; }
            .agent-bubble { display: inline-block; padding: 12px 18px; border-radius: 12px; font-size: 14px; line-height: 1.6; text-align: left; white-space: pre-wrap; max-width: 100%; }
            .agent-actions { margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; }
            .agent-action-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid #2563eb; background: #fff; color: #2563eb; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s; }
            .agent-action-btn:hover { background: #2563eb; color: #fff; }
            .agent-action-btn.approve { border-color: #16a34a; color: #16a34a; }
            .agent-action-btn.approve:hover { background: #16a34a; color: #fff; }
            .agent-input-area { border-top: 1px solid #e2e8f0; padding: 20px 30px; display: flex; gap: 10px; }
            .agent-input { flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; resize: none; min-height: 44px; max-height: 120px; }
            .agent-send { padding: 12px 24px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; }
            .agent-send:hover { background: #1d4ed8; }
            .agent-send:disabled { opacity: 0.5; cursor: not-allowed; }
            .agent-typing { display: inline-block; padding: 12px 18px; background: #f1f5f9; border-radius: 12px; font-size: 14px; color: #64748b; }
            .agent-sidebar { margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; }
            .agent-sidebar button { font-size: 12px; }
        </style>
        <div class="wrap agent-wrap">
            <div class="agent-chat">
                <div class="agent-header">
                    <h1>🤖 <?php esc_html_e('AI SEO Agent', 'ai-seo-client'); ?></h1>
                    <p><?php esc_html_e('Your conversational SEO assistant — research, plan, write, and publish in one chat.', 'ai-seo-client'); ?></p>
                </div>
                <div class="agent-messages" id="agent-messages">
                    <div class="agent-msg agent-msg-assistant">
                        <div class="agent-bubble"><?php esc_html_e("Hi! I'm your AI SEO Agent. I can help you with:\n• Generate topic clusters\n• Write SEO articles\n• Research keywords\n• Run site audits\n\nWhat would you like to do?", 'ai-seo-client'); ?></div>
                    </div>
                </div>
                <div class="agent-input-area">
                    <textarea class="agent-input" id="agent-input" placeholder="<?php esc_attr_e('Type your request... (e.g. "Create a cluster about WordPress SEO")', 'ai-seo-client'); ?>" rows="1"></textarea>
                    <button class="agent-send" id="agent-send"><?php esc_html_e('Send', 'ai-seo-client'); ?></button>
                </div>
            </div>
            <div class="agent-sidebar">
                <button class="button button-small" id="agent-new"><?php esc_html_e('New Conversation', 'ai-seo-client'); ?></button>
                <button class="button button-small" id="agent-history"><?php esc_html_e('History', 'ai-seo-client'); ?></button>
                <button class="button button-small" id="agent-settings"><?php esc_html_e('Settings', 'ai-seo-client'); ?></button>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var conversationId = 0;

            function addMessage(role, content, actions) {
                var cls = role === 'user' ? 'agent-msg-user' : 'agent-msg-assistant';
                var html = '<div class="agent-msg ' + cls + '"><div class="agent-bubble">' + escapeHtml(content) + '</div>';
                if (actions && actions.length) {
                    html += '<div class="agent-actions">';
                    actions.forEach(function(a) {
                        if (a.url) {
                            html += '<a href="' + a.url + '" class="agent-action-btn" target="_blank">' + escapeHtml(a.label) + '</a>';
                        } else {
                            var approveClass = a.requires_approval ? ' approve' : '';
                            html += '<button class="agent-action-btn' + approveClass + '" data-action="' + escapeHtml(a.id || '') + '" data-params="' + escapeHtml(JSON.stringify(a.params || {})) + '">' + escapeHtml(a.label) + '</button>';
                        }
                    });
                    html += '</div>';
                }
                html += '</div>';
                $('#agent-messages').append(html);
                $('#agent-messages').scrollTop($('#agent-messages')[0].scrollHeight);
            }

            function escapeHtml(text) {
                if (!text) return '';
                return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            function sendMessage(message, approvedAction) {
                if (!message && !approvedAction) return;
                if (message) addMessage('user', message);

                $('#agent-send').prop('disabled', true);
                $('#agent-messages').append('<div class="agent-msg agent-msg-assistant"><div class="agent-typing">🤔 ' + '<?php echo esc_js(__("Thinking...", "ai-seo-client")); ?>' + '</div></div>');
                $('#agent-messages').scrollTop($('#agent-messages')[0].scrollHeight);

                var data = {};
                if (message) data.message = message;
                if (approvedAction) data.approved_action = approvedAction;
                if (conversationId) data.conversation_id = conversationId;

                wp.apiFetch({
                    path: '/sseo-ai/v1/agent/chat',
                    method: 'POST',
                    data: data
                }).then(function(res) {
                    $('.agent-typing').parent().remove();
                    conversationId = res.conversation_id;
                    addMessage('assistant', res.message, res.actions);
                }).catch(function(err) {
                    $('.agent-typing').parent().remove();
                    addMessage('assistant', '❌ ' + (err.message || '<?php echo esc_js(__("Error occurred", "ai-seo-client")); ?>'));
                }).finally(function() {
                    $('#agent-send').prop('disabled', false);
                });
            }

            $('#agent-send').on('click', function() {
                var msg = $('#agent-input').val().trim();
                if (!msg) return;
                $('#agent-input').val('');
                sendMessage(msg);
            });

            $('#agent-input').on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $('#agent-send').click();
                }
            });

            $(document).on('click', '.agent-action-btn', function() {
                var actionId = $(this).data('action');
                var params = $(this).data('params');
                if (typeof params === 'string') params = JSON.parse(params || '{}');

                // Build action string with params
                var actionStr = actionId;
                if (params && Object.keys(params).length) {
                    actionStr += ':' + $.param(params);
                }

                addMessage('user', $(this).text());
                sendMessage('', actionStr);
            });

            $('#agent-new').on('click', function() {
                conversationId = 0;
                $('#agent-messages').html('<div class="agent-msg agent-msg-assistant"><div class="agent-bubble"><?php echo esc_js(__("New conversation started. What would you like to do?", "ai-seo-client")); ?></div></div>');
            });
        });
        </script>
        <?php
    }
}

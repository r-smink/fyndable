<?php

namespace SSEOAIClient;

/**
 * Ideas Management
 * 
 * Generate content ideas with AI or add them manually.
 * Ideas can be converted to drafts, outlines, or scheduled posts.
 * Comparable to WPSEO AI Ideas feature.
 */
class Ideas
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
        self::createTable();
    }

    public function registerRestRoutes(): void
    {
        // Generate ideas based on topic/keyword
        register_rest_route('sseo-ai/v1', '/ideas/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateIdeas'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'topic' => ['type' => 'string', 'required' => true],
                'count' => ['type' => 'integer', 'default' => 10],
                'language' => ['type' => 'string', 'default' => 'nl'],
            ],
        ]);

        // Add a manual idea
        register_rest_route('sseo-ai/v1', '/ideas/add', [
            'methods' => 'POST',
            'callback' => [$this, 'restAddIdea'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'title' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string', 'default' => ''],
                'keywords' => ['type' => 'string', 'default' => ''],
            ],
        ]);

        // List all ideas
        register_rest_route('sseo-ai/v1', '/ideas/list', [
            'methods' => 'GET',
            'callback' => [$this, 'restListIdeas'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Get single idea
        register_rest_route('sseo-ai/v1', '/ideas/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetIdea'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Update idea
        register_rest_route('sseo-ai/v1', '/ideas/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'restUpdateIdea'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Delete idea
        register_rest_route('sseo-ai/v1', '/ideas/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteIdea'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Convert idea to draft post
        register_rest_route('sseo-ai/v1', '/ideas/(?P<id>\d+)/convert', [
            'methods' => 'POST',
            'callback' => [$this, 'restConvertIdea'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
            'args' => [
                'word_count' => ['type' => 'integer', 'default' => 1500],
            ],
        ]);

        // Convert idea to scheduled post
        register_rest_route('sseo-ai/v1', '/ideas/(?P<id>\d+)/schedule', [
            'methods' => 'POST',
            'callback' => [$this, 'restScheduleIdea'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
            'args' => [
                'publish_date' => ['type' => 'string', 'required' => true],
                'word_count' => ['type' => 'integer', 'default' => 1500],
            ],
        ]);

        // Get scheduled posts
        register_rest_route('sseo-ai/v1', '/ideas/scheduled', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetScheduledPosts'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Delete all ideas
        register_rest_route('sseo-ai/v1', '/ideas/delete-all', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteAllIdeas'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * REST: Generate ideas using AI
     */
    public function restGenerateIdeas(\WP_REST_Request $request): array|\WP_Error
    {
        $topic = sanitize_text_field($request->get_param('topic'));
        $count = (int) $request->get_param('count');
        $language = sanitize_text_field($request->get_param('language'));

        $ideas = $this->generateIdeasWithAI($topic, $count, $language);
        
        if (is_wp_error($ideas)) {
            return $ideas;
        }

        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $created = [];

        foreach ($ideas as $idea) {
            $wpdb->insert($table, [
                'title' => $idea['title'],
                'description' => $idea['description'] ?? '',
                'keywords' => $idea['keywords'] ?? '',
                'search_intent' => $idea['search_intent'] ?? 'informational',
                'source' => 'ai_generated',
                'created_at' => current_time('mysql'),
                'status' => 'active',
            ]);
            $created[] = [
                'id' => $wpdb->insert_id,
                'title' => $idea['title'],
                'description' => $idea['description'] ?? '',
                'keywords' => $idea['keywords'] ?? '',
            ];
        }

        return [
            'success' => true,
            'count' => count($created),
            'ideas' => $created,
        ];
    }

    /**
     * Generate ideas using LLM
     */
    private function generateIdeasWithAI(string $topic, int $count, string $language): array|\WP_Error
    {
        $langName = $this->getLanguageName($language);
        
        $prompt = <<<PROMPT
You are an expert content strategist. Generate {$count} blog post ideas for the topic: "{$topic}"

Requirements:
- Language: {$langName}
- Each idea should have SEO value
- Mix of informational, commercial, and transactional intent
- Include target keywords naturally
- Consider search volume potential

Return ONLY a JSON array in this exact format (no markdown, no code blocks):
[
    {
        "title": " catchy, SEO-optimized title",
        "description": "brief 1-2 sentence description",
        "keywords": "primary keyword, secondary keyword",
        "search_intent": "informational|commercial|transactional"
    }
]

Generate {$count} ideas now.
PROMPT;

        $response = $this->llm->generateText($prompt, ['max_tokens' => 3000, 'use_case' => 'content_generation']);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Clean response and parse JSON
        $response = preg_replace('/^```json\s*/i', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        $response = trim($response);
        
        $ideas = json_decode($response, true);
        
        if (!is_array($ideas)) {
            // Fallback: generate basic ideas
            return $this->generateFallbackIdeas($topic, $count);
        }

        return $ideas;
    }

    /**
     * Fallback idea generation
     */
    private function generateFallbackIdeas(string $topic, int $count): array
    {
        $templates = [
            "De complete gids voor {$topic} in 2024",
            "Hoe {$topic} te gebruiken voor betere resultaten",
            "5 mythes over {$topic} ontkracht",
            "{$topic}: alles wat je moet weten",
            "De voordelen van {$topic} voor je bedrijf",
            "{$topic} vs alternatieven: een vergelijking",
            "Hoe start je met {$topic}? Stap-voor-stap gids",
            "De toekomst van {$topic}: trends en voorspellingen",
            "Veelgemaakte fouten met {$topic} en hoe ze te vermijden",
            "{$topic} voor beginners: de ultieme handleiding",
            "Waarom {$topic} essentieel is voor groei",
            "De kosten van {$topic}: wat kun je verwachten?",
            "{$topic} case studies: succesverhalen",
            "Hoe kies je de beste {$topic} oplossing?",
            "{$topic} checklist: ben jij voorbereid?",
        ];

        $ideas = [];
        for ($i = 0; $i < min($count, count($templates)); $i++) {
            $ideas[] = [
                'title' => str_replace('{$topic}', $topic, $templates[$i]),
                'description' => "Een uitgebreid artikel over {$topic} dat waarde biedt aan je doelgroep.",
                'keywords' => $topic,
                'search_intent' => 'informational',
            ];
        }

        return $ideas;
    }

    /**
     * REST: Add manual idea
     */
    public function restAddIdea(\WP_REST_Request $request): array|\WP_Error
    {
        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $keywords = sanitize_text_field($request->get_param('keywords'));

        if (empty($title)) {
            return new \WP_Error('missing_title', __('Title is required', 'ai-seo-client'), ['status' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';

        $wpdb->insert($table, [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'search_intent' => 'informational',
            'source' => 'manual',
            'created_at' => current_time('mysql'),
            'status' => 'active',
        ]);

        return [
            'success' => true,
            'id' => $wpdb->insert_id,
            'title' => $title,
            'message' => __('Idea added successfully', 'ai-seo-client'),
        ];
    }

    /**
     * REST: List ideas with filtering
     */
    public function restListIdeas(\WP_REST_Request $request): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';

        $search = sanitize_text_field($request->get_param('search') ?? '');
        $status = sanitize_text_field($request->get_param('status') ?? 'active');
        $source = sanitize_text_field($request->get_param('source') ?? '');
        $dateFrom = sanitize_text_field($request->get_param('date_from') ?? '');
        $dateTo = sanitize_text_field($request->get_param('date_to') ?? '');
        $limit = (int) ($request->get_param('limit') ?? 50);
        $offset = (int) ($request->get_param('offset') ?? 0);

        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }
        if ($source) {
            $sql .= " AND source = %s";
            $params[] = $source;
        }
        if ($search) {
            $sql .= " AND (title LIKE %s OR keywords LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($dateFrom) {
            $sql .= " AND created_at >= %s";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $sql .= " AND created_at <= %s";
            $params[] = $dateTo . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $ideas = $wpdb->get_results($wpdb->prepare($sql, $params));

        // Get total count
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
        $countParams = [];
        
        if ($status) {
            $countSql .= " AND status = %s";
            $countParams[] = $status;
        }
        if ($source) {
            $countSql .= " AND source = %s";
            $countParams[] = $source;
        }
        if ($search) {
            $countSql .= " AND (title LIKE %s OR keywords LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $countParams[] = $like;
            $countParams[] = $like;
        }

        if (!empty($countParams)) {
            $total = $wpdb->get_var($wpdb->prepare($countSql, $countParams));
        } else {
            $total = $wpdb->get_var($countSql);
        }

        return [
            'ideas' => $ideas ?: [],
            'total' => (int) $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * REST: Get single idea
     */
    public function restGetIdea(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $id = (int) $request->get_param('id');

        $idea = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$idea) {
            return new \WP_Error('not_found', __('Idea not found', 'ai-seo-client'), ['status' => 404]);
        }

        return (array) $idea;
    }

    /**
     * REST: Update idea
     */
    public function restUpdateIdea(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $id = (int) $request->get_param('id');

        $data = [];
        if ($request->has_param('title')) {
            $data['title'] = sanitize_text_field($request->get_param('title'));
        }
        if ($request->has_param('description')) {
            $data['description'] = sanitize_textarea_field($request->get_param('description'));
        }
        if ($request->has_param('keywords')) {
            $data['keywords'] = sanitize_text_field($request->get_param('keywords'));
        }
        if ($request->has_param('status')) {
            $data['status'] = sanitize_text_field($request->get_param('status'));
        }

        if (empty($data)) {
            return new \WP_Error('no_data', __('No data to update', 'ai-seo-client'), ['status' => 400]);
        }

        $result = $wpdb->update($table, $data, ['id' => $id]);

        if ($result === false) {
            return new \WP_Error('update_failed', __('Failed to update idea', 'ai-seo-client'), ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => __('Idea updated successfully', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Delete idea
     */
    public function restDeleteIdea(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $id = (int) $request->get_param('id');

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        if ($result === false) {
            return new \WP_Error('delete_failed', __('Failed to delete idea', 'ai-seo-client'), ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => __('Idea deleted successfully', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Convert idea to draft post
     */
    public function restConvertIdea(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $id = (int) $request->get_param('id');
        $wordCount = (int) $request->get_param('word_count');

        $idea = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$idea) {
            return new \WP_Error('not_found', __('Idea not found', 'ai-seo-client'), ['status' => 404]);
        }

        // Generate content
        $content = $this->generateContentForIdea($idea->title, $idea->keywords ?? '', $wordCount);
        
        if (is_wp_error($content)) {
            return $content;
        }

        // Create post
        $postId = wp_insert_post([
            'post_title' => $idea->title,
            'post_content' => $content['content'],
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'meta_input' => [
                '_sseo_ai_title' => $idea->title,
                '_sseo_ai_description' => $content['meta_description'] ?? '',
                '_sseo_ai_focus_keyphrase' => $idea->keywords ?? '',
                '_sseo_ai_generated' => '1',
                '_sseo_ai_generated_date' => current_time('mysql'),
                '_sseo_ai_source_idea_id' => $id,
            ],
        ]);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Update idea status
        $wpdb->update($table, 
            ['status' => 'converted', 'converted_post_id' => $postId, 'converted_at' => current_time('mysql')],
            ['id' => $id]
        );

        return [
            'success' => true,
            'post_id' => $postId,
            'edit_url' => get_edit_post_link($postId, ''),
            'view_url' => get_permalink($postId),
            'title' => $idea->title,
            'word_count' => $content['word_count'] ?? 0,
        ];
    }

    /**
     * REST: Schedule idea as post
     */
    public function restScheduleIdea(\WP_REST_Request $request): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $id = (int) $request->get_param('id');
        $publishDate = sanitize_text_field($request->get_param('publish_date'));
        $wordCount = (int) $request->get_param('word_count');

        $idea = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ));

        if (!$idea) {
            return new \WP_Error('not_found', __('Idea not found', 'ai-seo-client'), ['status' => 404]);
        }

        // Generate content
        $content = $this->generateContentForIdea($idea->title, $idea->keywords ?? '', $wordCount);
        
        if (is_wp_error($content)) {
            return $content;
        }

        // Create scheduled post
        $postId = wp_insert_post([
            'post_title' => $idea->title,
            'post_content' => $content['content'],
            'post_status' => 'future',
            'post_type' => 'post',
            'post_author' => get_current_user_id(),
            'post_date' => $publishDate,
            'post_date_gmt' => get_gmt_from_date($publishDate),
            'meta_input' => [
                '_sseo_ai_title' => $idea->title,
                '_sseo_ai_description' => $content['meta_description'] ?? '',
                '_sseo_ai_focus_keyphrase' => $idea->keywords ?? '',
                '_sseo_ai_generated' => '1',
                '_sseo_ai_generated_date' => current_time('mysql'),
                '_sseo_ai_source_idea_id' => $id,
                '_sseo_ai_scheduled' => '1',
            ],
        ]);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Update idea status
        $wpdb->update($table, 
            ['status' => 'scheduled', 'converted_post_id' => $postId, 'scheduled_at' => current_time('mysql')],
            ['id' => $id]
        );

        return [
            'success' => true,
            'post_id' => $postId,
            'edit_url' => get_edit_post_link($postId, ''),
            'view_url' => get_permalink($postId),
            'title' => $idea->title,
            'scheduled_date' => $publishDate,
            'word_count' => $content['word_count'] ?? 0,
        ];
    }

    /**
     * REST: Get scheduled posts from ideas
     */
    public function restGetScheduledPosts(\WP_REST_Request $request): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';

        $posts = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'scheduled' ORDER BY scheduled_at DESC"
        );

        return [
            'scheduled' => $posts,
            'count' => count($posts),
        ];
    }

    /**
     * REST: Delete all ideas
     */
    public function restDeleteAllIdeas(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';

        $wpdb->query("DELETE FROM {$table}");

        return [
            'success' => true,
            'message' => __('All ideas deleted', 'ai-seo-client'),
        ];
    }

    /**
     * Generate content for an idea
     */
    private function generateContentForIdea(string $title, string $keywords, int $wordCount): array|\WP_Error
    {
        $prompt = <<<PROMPT
Write a comprehensive, SEO-optimized blog post.

Title: {$title}
Keywords: {$keywords}
Target word count: {$wordCount} words

Requirements:
1. Start with an engaging introduction
2. Use proper H2 and H3 headings
3. Include actionable advice and examples
4. End with a conclusion and FAQ section
5. Write in Dutch
6. Make it engaging and valuable for readers

Return JSON in this format (no markdown):
{
    "content": "HTML content with headings and paragraphs",
    "meta_description": "SEO meta description (150-160 characters)",
    "word_count": 1234
}
PROMPT;

        $response = $this->llm->generateText($prompt, ['max_tokens' => min(4000, $wordCount * 2), 'use_case' => 'content_generation']);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(trim($response), true);
        
        if (!$data || !isset($data['content'])) {
            return [
                'content' => $response,
                'meta_description' => substr(strip_tags($response), 0, 160),
                'word_count' => str_word_count(strip_tags($response)),
            ];
        }

        return $data;
    }

    /**
     * Get language name from code
     */
    private function getLanguageName(string $code): string
    {
        $languages = [
            'nl' => 'Dutch',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
        ];

        return $languages[$code] ?? 'Dutch';
    }

    /**
     * Create database table on activation
     */
    public static function createTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            keywords varchar(255),
            search_intent varchar(50) DEFAULT 'informational',
            source varchar(50) DEFAULT 'manual',
            status varchar(50) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            converted_post_id bigint(20) unsigned,
            converted_at datetime,
            scheduled_at datetime,
            PRIMARY KEY (id),
            KEY status (status),
            KEY source (source),
            KEY created_at (created_at)
        ) {$charset};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Render the Ideas page
     */
    public function renderPage(): void
    {
        // Get credits info
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_ideas';
        $totalIdeas = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'active'") ?: 0;
        $scheduledCount = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'scheduled'") ?: 0;

        // Use configurable or dynamically computed credits
        $credits = $this->getCredits($table);

        // Resolve the upgrade destination (Customer Portal on the SaaS
        // dashboard site), with dashboard URL / Connection page fallbacks.
        $portalUrl = get_option('sseo_ai_client_portal_url', '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        $upgradeUrl = !empty($portalUrl)
            ? $portalUrl
            : (!empty($dashboardUrl) ? $dashboardUrl : admin_url('admin.php?page=ai-seo-client'));
        ?>

        <div class="wrap sseo-ai-modern" id="sseo-ideas-page">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Ideas', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">
                    <!-- Actions Bar -->
                    <div class="ideas-actions-bar">
                        <button type="button" class="sseo-btn-primary" id="btn-generate-ideas">
                            <?php esc_html_e('Generate ideas', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="sseo-btn-secondary" id="btn-add-idea">
                            <?php esc_html_e('+ Add an idea', 'ai-seo-client'); ?>
                        </button>
                        
                        <div class="credits-display">
                            <span class="credits-label"><?php esc_html_e('Credits used / available:', 'ai-seo-client'); ?></span>
                            <div class="credits-grid">
                                <div class="credit-item">
                                    <span class="credit-value"><?php echo esc_html($this->renderCreditValue($credits['posts'])); ?></span>
                                    <span class="credit-label"><?php esc_html_e('Posts', 'ai-seo-client'); ?></span>
                                </div>
                                <div class="credit-item">
                                    <span class="credit-value"><?php echo esc_html($this->renderCreditValue($credits['ideas'])); ?></span>
                                    <span class="credit-label"><?php esc_html_e('Ideas', 'ai-seo-client'); ?></span>
                                </div>
                                <div class="credit-item">
                                    <span class="credit-value"><?php echo esc_html($this->renderCreditValue($credits['outlines'])); ?></span>
                                    <span class="credit-label"><?php esc_html_e('Outlines', 'ai-seo-client'); ?></span>
                                </div>
                                <div class="credit-item">
                                    <span class="credit-value"><?php echo esc_html($this->renderCreditValue($credits['keywords'])); ?></span>
                                    <span class="credit-label"><?php esc_html_e('Keywords', 'ai-seo-client'); ?></span>
                                </div>
                                <div class="credit-item">
                                    <span class="credit-value"><?php echo esc_html($this->renderCreditValue($credits['images'])); ?></span>
                                    <span class="credit-label"><?php esc_html_e('Images', 'ai-seo-client'); ?></span>
                                </div>
                            </div>
                            <a href="<?php echo esc_url($upgradeUrl); ?>" class="upgrade-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Upgrade My Plan', 'ai-seo-client'); ?></a>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="ideas-tabs">
                        <label class="tab-item">
                            <input type="checkbox" id="select-all-ideas">
                        </label>
                        <button type="button" class="tab-btn active" data-tab="all">
                            <?php esc_html_e('All ideas', 'ai-seo-client'); ?>
                        </button>
                        <button type="button" class="tab-btn" data-tab="scheduled">
                            <?php esc_html_e('Scheduled posts', 'ai-seo-client'); ?>
                            <?php if ($scheduledCount > 0): ?>
                                <span class="tab-badge"><?php echo esc_html($scheduledCount); ?></span>
                            <?php endif; ?>
                        </button>
                        <button type="button" class="tab-btn danger" id="btn-delete-all">
                            <?php esc_html_e('Delete all ideas', 'ai-seo-client'); ?>
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="ideas-filters">
                        <div class="filter-group">
                            <input type="text" id="filter-title" placeholder="<?php esc_attr_e('Enter a title', 'ai-seo-client'); ?>">
                            <button type="button" class="filter-btn" id="btn-search-title">
                                <span class="dashicons dashicons-search"></span>
                            </button>
                        </div>
                        <div class="filter-group date-group">
                            <input type="text" id="filter-date-from" placeholder="<?php esc_attr_e('Created from', 'ai-seo-client'); ?>">
                            <button type="button" class="filter-btn">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </button>
                            <input type="text" id="filter-date-to" placeholder="<?php esc_attr_e('Created to', 'ai-seo-client'); ?>">
                            <button type="button" class="filter-btn">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </button>
                        </div>
                        <button type="button" class="filter-clear" id="btn-clear-filters">&times;</button>
                    </div>

                    <!-- Table Header -->
                    <div class="ideas-table-header">
                        <span class="sortable" data-sort="id">ID <span class="sort-icon">&#8597;</span></span>
                        <span class="sortable" data-sort="date"><?php esc_html_e('Date', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span></span>
                        <span class="sortable" data-sort="name"><?php esc_html_e('Name', 'ai-seo-client'); ?> <span class="sort-icon">&#8597;</span></span>
                    </div>

                    <!-- Ideas List -->
                    <div id="ideas-list-container">
                        <div class="ideas-empty">
                            <p><?php esc_html_e('No ideas yet. Generate ideas or add your own!', 'ai-seo-client'); ?></p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="ideas-pagination" id="ideas-pagination" style="display: none;">
                        <button type="button" class="page-btn" data-page="prev">&laquo;</button>
                        <div id="page-numbers"></div>
                        <button type="button" class="page-btn" data-page="next">&raquo;</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generate Ideas Modal -->
        <div class="sseo-modal" id="modal-generate-ideas" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Generate Ideas', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <div class="form-group">
                        <label><?php esc_html_e('Topic or keyword', 'ai-seo-client'); ?></label>
                        <input type="text" id="generate-topic" class="sseo-input" placeholder="<?php esc_attr_e('Enter a topic to generate ideas for...', 'ai-seo-client'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Number of ideas', 'ai-seo-client'); ?></label>
                        <select id="generate-count" class="sseo-select">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                            <option value="20">20</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Language', 'ai-seo-client'); ?></label>
                        <select id="generate-language" class="sseo-select">
                            <option value="nl" selected>Dutch</option>
                            <option value="en">English</option>
                            <option value="de">German</option>
                            <option value="fr">French</option>
                        </select>
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-generate">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Generate', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Add Idea Modal -->
        <div class="sseo-modal" id="modal-add-idea" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Add Idea', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <div class="form-group">
                        <label><?php esc_html_e('Title', 'ai-seo-client'); ?> *</label>
                        <input type="text" id="add-title" class="sseo-input" placeholder="<?php esc_attr_e('Enter post title...', 'ai-seo-client'); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Description', 'ai-seo-client'); ?></label>
                        <textarea id="add-description" class="sseo-textarea" rows="3" placeholder="<?php esc_attr_e('Brief description of the idea...', 'ai-seo-client'); ?>"></textarea>
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Keywords', 'ai-seo-client'); ?></label>
                        <input type="text" id="add-keywords" class="sseo-input" placeholder="<?php esc_attr_e('keyword1, keyword2, keyword3...', 'ai-seo-client'); ?>">
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-add">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Add Idea', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Schedule Modal -->
        <div class="sseo-modal" id="modal-schedule" style="display: none;">
            <div class="sseo-modal-overlay"></div>
            <div class="sseo-modal-content">
                <div class="sseo-modal-header">
                    <h3><?php esc_html_e('Schedule Post', 'ai-seo-client'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="sseo-modal-body">
                    <input type="hidden" id="schedule-idea-id">
                    <div class="form-group">
                        <label><?php esc_html_e('Publish date', 'ai-seo-client'); ?></label>
                        <input type="datetime-local" id="schedule-date" class="sseo-input">
                    </div>
                    <div class="form-group">
                        <label><?php esc_html_e('Word count', 'ai-seo-client'); ?></label>
                        <select id="schedule-word-count" class="sseo-select">
                            <option value="800">800 words</option>
                            <option value="1200" selected>1,200 words</option>
                            <option value="1500">1,500 words</option>
                            <option value="2000">2,000 words</option>
                            <option value="2500">2,500 words</option>
                        </select>
                    </div>
                </div>
                <div class="sseo-modal-footer">
                    <button type="button" class="sseo-btn-secondary modal-cancel"><?php esc_html_e('Cancel', 'ai-seo-client'); ?></button>
                    <button type="button" class="sseo-btn-primary" id="btn-confirm-schedule">
                        <span class="spinner" style="display: none;"></span>
                        <?php esc_html_e('Schedule', 'ai-seo-client'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let totalPages = 1;
            let currentTab = 'all';

            // Load ideas on page load
            loadIdeas();

            // Tab switching
            $('.tab-btn').on('click', function() {
                if ($(this).hasClass('danger')) return;
                $('.tab-btn').removeClass('active');
                $(this).addClass('active');
                currentTab = $(this).data('tab');
                currentPage = 1;
                loadIdeas();
            });

            // Generate ideas modal
            $('#btn-generate-ideas').on('click', function() {
                $('#modal-generate-ideas').show();
            });

            // Add idea modal
            $('#btn-add-idea').on('click', function() {
                $('#modal-add-idea').show();
            });

            // Close modals
            $('.modal-close, .modal-cancel').on('click', function() {
                $(this).closest('.sseo-modal').hide();
            });

            // Generate ideas
            $('#btn-confirm-generate').on('click', function() {
                const topic = $('#generate-topic').val().trim();
                const count = $('#generate-count').val();
                const language = $('#generate-language').val();

                if (!topic) {
                    alert('<?php echo esc_js(__('Please enter a topic', 'ai-seo-client')); ?>');
                    return;
                }

                const btn = $(this);
                btn.find('.spinner').show();
                btn.prop('disabled', true);
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                wp.apiFetch({
                    path: 'sseo-ai/v1/ideas/generate',
                    method: 'POST',
                    data: { topic, count: parseInt(count), language }
                }).then(function(response) {
                    $('#modal-generate-ideas').hide();
                    $('#generate-topic').val('');
                    loadIdeas();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to generate ideas', 'ai-seo-client')); ?>');
                }).finally(function() {
                    btn.find('.spinner').hide();
                    btn.prop('disabled', false);
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            });

            // Add idea
            $('#btn-confirm-add').on('click', function() {
                const title = $('#add-title').val().trim();
                const description = $('#add-description').val().trim();
                const keywords = $('#add-keywords').val().trim();

                if (!title) {
                    alert('<?php echo esc_js(__('Please enter a title', 'ai-seo-client')); ?>');
                    return;
                }

                const btn = $(this);
                btn.find('.spinner').show();
                btn.prop('disabled', true);

                wp.apiFetch({
                    path: 'sseo-ai/v1/ideas/add',
                    method: 'POST',
                    data: { title, description, keywords }
                }).then(function(response) {
                    $('#modal-add-idea').hide();
                    $('#add-title').val('');
                    $('#add-description').val('');
                    $('#add-keywords').val('');
                    loadIdeas();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to add idea', 'ai-seo-client')); ?>');
                }).finally(function() {
                    btn.find('.spinner').hide();
                    btn.prop('disabled', false);
                });
            });

            // Load ideas function
            function loadIdeas() {
                const status = currentTab === 'all' ? 'active' : currentTab;
                
                wp.apiFetch({
                    path: 'sseo-ai/v1/ideas/list?status=' + status + '&limit=20&offset=' + ((currentPage - 1) * 20),
                    method: 'GET'
                }).then(function(response) {
                    renderIdeas(response.ideas, response.total);
                    updatePagination(response.total, 20);
                }).catch(function(error) {
                    console.error('Failed to load ideas:', error);
                });
            }

            // Render ideas
            function renderIdeas(ideas, total) {
                const container = $('#ideas-list-container');
                
                if (!ideas || ideas.length === 0) {
                    container.html('<div class="ideas-empty"><p><?php echo esc_js(__('No ideas found', 'ai-seo-client')); ?></p></div>');
                    return;
                }

                let html = '<div class="ideas-list">';
                ideas.forEach(function(idea) {
                    html += renderIdeaRow(idea);
                });
                html += '</div>';
                
                container.html(html);
                bindIdeaActions();
            }

            // Render single idea row
            function renderIdeaRow(idea) {
                return `
                    <div class="idea-row" data-id="${idea.id}">
                        <div class="idea-col idea-check">
                            <input type="checkbox" class="idea-select">
                        </div>
                        <div class="idea-col idea-id">#${idea.id}</div>
                        <div class="idea-col idea-date">${formatDate(idea.created_at)}</div>
                        <div class="idea-col idea-info">
                            <div class="idea-title">${escapeHtml(idea.title)}</div>
                            ${idea.keywords ? `<div class="idea-keywords">${escapeHtml(idea.keywords)}</div>` : ''}
                            ${idea.description ? `<div class="idea-desc">${escapeHtml(idea.description)}</div>` : ''}
                        </div>
                        <div class="idea-col idea-actions">
                            <button type="button" class="btn-action btn-draft" data-action="draft" title="<?php echo esc_js(__('Create draft', 'ai-seo-client')); ?>">
                                <span class="dashicons dashicons-media-document"></span>
                            </button>
                            <button type="button" class="btn-action btn-schedule" data-action="schedule" title="<?php echo esc_js(__('Schedule', 'ai-seo-client')); ?>">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </button>
                            <button type="button" class="btn-action btn-outline" data-action="outline" title="<?php echo esc_js(__('Create outline', 'ai-seo-client')); ?>">
                                <span class="dashicons dashicons-editor-ul"></span>
                            </button>
                            <button type="button" class="btn-action btn-delete" data-action="delete" title="<?php echo esc_js(__('Delete', 'ai-seo-client')); ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                `;
            }

            // Bind idea actions
            function bindIdeaActions() {
                // Delete
                $('.btn-delete').on('click', function() {
                    const row = $(this).closest('.idea-row');
                    const id = row.data('id');
                    
                    if (!confirm('<?php echo esc_js(__('Delete this idea?', 'ai-seo-client')); ?>')) return;
                    
                    wp.apiFetch({
                        path: 'sseo-ai/v1/ideas/' + id,
                        method: 'DELETE'
                    }).then(function() {
                        row.remove();
                    }).catch(function(error) {
                        alert(error.message || '<?php echo esc_js(__('Failed to delete', 'ai-seo-client')); ?>');
                    });
                });

                // Draft
                $('.btn-draft').on('click', function() {
                    const row = $(this).closest('.idea-row');
                    const id = row.data('id');
                    const btn = $(this);

                    btn.prop('disabled', true);
                    if (typeof sseoShowLoader === 'function') sseoShowLoader();

                    wp.apiFetch({
                        path: 'sseo-ai/v1/ideas/' + id + '/convert',
                        method: 'POST',
                        data: { word_count: 1200 }
                    }).then(function(response) {
                        if (response.edit_url) {
                            window.open(response.edit_url, '_blank');
                        }
                        row.find('.idea-title').append(' <span class="badge-converted"><?php echo esc_js(__('Converted', 'ai-seo-client')); ?></span>');
                    }).catch(function(error) {
                        alert(error.message || '<?php echo esc_js(__('Failed to create draft', 'ai-seo-client')); ?>');
                    }).finally(function() {
                        btn.prop('disabled', false);
                        if (typeof sseoHideLoader === 'function') sseoHideLoader();
                    });
                });

                // Schedule
                $('.btn-schedule').on('click', function() {
                    const row = $(this).closest('.idea-row');
                    const id = row.data('id');
                    $('#schedule-idea-id').val(id);
                    $('#modal-schedule').show();
                });
            }

            // Schedule confirm
            $('#btn-confirm-schedule').on('click', function() {
                const id = $('#schedule-idea-id').val();
                const publishDate = $('#schedule-date').val();
                const wordCount = $('#schedule-word-count').val();

                if (!publishDate) {
                    alert('<?php echo esc_js(__('Please select a date', 'ai-seo-client')); ?>');
                    return;
                }

                const btn = $(this);
                btn.find('.spinner').show();
                btn.prop('disabled', true);
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                wp.apiFetch({
                    path: 'sseo-ai/v1/ideas/' + id + '/schedule',
                    method: 'POST',
                    data: { publish_date: publishDate, word_count: wordCount }
                }).then(function(response) {
                    $('#modal-schedule').hide();
                    loadIdeas();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to schedule', 'ai-seo-client')); ?>');
                }).finally(function() {
                    btn.find('.spinner').hide();
                    btn.prop('disabled', false);
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            });

            // Delete all
            $('#btn-delete-all').on('click', function() {
                if (!confirm('<?php echo esc_js(__('Delete all ideas? This cannot be undone.', 'ai-seo-client')); ?>')) return;
                
                wp.apiFetch({
                    path: 'sseo-ai/v1/ideas/delete-all',
                    method: 'DELETE'
                }).then(function() {
                    loadIdeas();
                }).catch(function(error) {
                    alert(error.message || '<?php echo esc_js(__('Failed to delete', 'ai-seo-client')); ?>');
                });
            });

            // Helper functions
            function formatDate(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleDateString('<?php echo esc_js(str_replace('_', '-', get_locale())); ?>', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function updatePagination(total, limit) {
                totalPages = Math.ceil(total / limit);
                const pagination = $('#ideas-pagination');
                
                if (totalPages <= 1) {
                    pagination.hide();
                    return;
                }

                let html = '';
                for (let i = 1; i <= totalPages; i++) {
                    const active = i === currentPage ? 'active' : '';
                    html += `<button type="button" class="page-btn ${active}" data-page="${i}">${i}</button>`;
                }
                
                $('#page-numbers').html(html);
                pagination.show();

                // Page click handlers
                $('#page-numbers .page-btn').on('click', function() {
                    currentPage = parseInt($(this).data('page'));
                    loadIdeas();
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Render a credit value as "used" or "used / limit".
     * Unlimited tiers (PHP_INT_MAX) show "∞" for the limit.
     */
    private function renderCreditValue(array $credit): string
    {
        $used = (int) ($credit['used'] ?? 0);
        $limit = $credit['limit'] ?? null;

        if ($limit === null) {
            return (string) $used;
        }

        if ($limit === PHP_INT_MAX) {
            return $used . ' / ∞';
        }

        return $used . ' / ' . (int) $limit;
    }

    /**
     * Get credit counts for the Ideas page.
     *
     * Returns per-item arrays with 'used' and 'limit' (limit may be null when
     * no tier quota applies). Resolution order:
     * 1. sseo_ai_client_credits option (e.g. set by dashboard / admin code)
     * 2. apply_filters('sseo_ai_ideas_credits', ...)
     * 3. Dynamically computed from local data + tier limits
     */
    private function getCredits(string $table): array
    {
        $optionCredits = get_option('sseo_ai_client_credits');
        if (is_array($optionCredits) && !empty($optionCredits)) {
            return $optionCredits;
        }

        global $wpdb;

        $aiPostsCount = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_sseo_ai_generated' AND meta_value = '1'"
        ) ?: 0;

        $scheduledCount = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'scheduled'"
        ) ?: 0;

        $keywordCount = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT keywords) FROM {$table} WHERE keywords != ''"
        ) ?: 0;

        $aiImages = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_sseo_ai_og_image', '_sseo_ai_twitter_image')"
        ) ?: 0;

        // Tier-based monthly limits (stored at license activation/validation).
        // null = no tier quota for this item.
        $postLimit = $this->getTierLimit('sseo_ai_client_monthly_auto_posts', self::TIER_AUTO_POST_LIMITS);
        $apiLimit  = $this->getTierLimit('sseo_ai_client_api_limit', self::TIER_API_LIMITS);

        $credits = [
            'posts'    => ['used' => $aiPostsCount, 'limit' => $postLimit],
            'ideas'    => ['used' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'active'") ?: 0, 'limit' => null],
            'outlines' => ['used' => $scheduledCount, 'limit' => null],
            'keywords' => ['used' => $keywordCount, 'limit' => $apiLimit],
            'images'   => ['used' => $aiImages, 'limit' => null],
        ];

        // Allow SaaS dashboard / custom code to override credit display values
        return apply_filters('sseo_ai_ideas_credits', $credits);
    }

    /**
     * Default monthly auto-scheduled post limits per tier.
     * Mirrors LicenseValidator::TIER_AUTO_POST_LIMITS to avoid coupling.
     */
    private const TIER_AUTO_POST_LIMITS = [
        'free'         => 0,
        'starter'      => 15,
        'trial'        => 10,
        'professional' => 35,
        'business'     => 150,
        'agency'       => PHP_INT_MAX,
        'dev'          => PHP_INT_MAX,
    ];

    /**
     * Default API call limits per tier (per month).
     */
    private const TIER_API_LIMITS = [
        'free'         => 500,
        'starter'      => 1000,
        'trial'        => 5000,
        'professional' => 10000,
        'business'     => 50000,
        'agency'       => 200000,
        'dev'          => PHP_INT_MAX,
    ];

    /**
     * Resolve a tier limit: option value if set, otherwise tier default.
     * Returns null when the tier is unknown (no quota to show).
     */
    private function getTierLimit(string $optionName, array $tierDefaults): ?int
    {
        $tier = get_option('sseo_ai_client_license_tier', 'free');
        if ($tier === 'dev') {
            return PHP_INT_MAX;
        }
        $default = $tierDefaults[$tier] ?? null;
        if ($default === null) {
            return null;
        }
        return (int) get_option($optionName, $default);
    }
}

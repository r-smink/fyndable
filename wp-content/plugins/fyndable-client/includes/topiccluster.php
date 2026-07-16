<?php

namespace SSEOAIClient;

/**
 * Topic Cluster / Topical Authority Map
 * 
 * Generates pillar-cluster content maps for building topical authority.
 * Identifies hub pages, supporting content, and internal linking strategy.
 * Comparable to MarketMuse Cluster Analysis, NeuronWriter topical maps.
 * 
 * Also audits existing site content to map current topical coverage
 * and identify content gaps in your cluster strategy.
 */
class TopicCluster
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
        // Menu registration moved to Client class
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Topic Clusters', 'ai-seo-client'),
            __('Topic Clusters', 'ai-seo-client'),
            'edit_posts',
            'ai-seo-topic-clusters',
            [$this, 'renderPage']
        );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/clusters/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/audit', [
            'methods' => 'POST',
            'callback' => [$this, 'restAuditExistingContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/save', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/list', [
            'methods' => 'GET',
            'callback' => [$this, 'restListClusters'],
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Content generation endpoint for cluster items
        register_rest_route('sseo-ai/v1', '/clusters/generate-content', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateClusterContent'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
            'args' => [
                'title' => ['type' => 'string', 'required' => true],
                'keyword' => ['type' => 'string', 'required' => true],
                'word_count' => ['type' => 'integer', 'required' => false, 'default' => 1500],
                'content_type' => ['type' => 'string', 'required' => false, 'default' => 'article'],
                'cluster_context' => ['type' => 'string', 'required' => false],
            ],
        ]);
    }

    /**
     * Generate content for a cluster page and create WordPress draft
     */
    public function restGenerateClusterContent(\WP_REST_Request $request): array|\WP_Error
    {
        $title = sanitize_text_field($request->get_param('title'));
        $keyword = sanitize_text_field($request->get_param('keyword'));
        $wordCount = (int) ($request->get_param('word_count') ?: 1500);
        $contentType = sanitize_text_field($request->get_param('content_type') ?: 'article');
        $clusterContext = sanitize_textarea_field($request->get_param('cluster_context') ?: '');
        $scheduleDate = sanitize_text_field($request->get_param('schedule_date') ?: '');

        if (empty($title) || empty($keyword)) {
            return new \WP_Error('missing_params', __('Title and keyword are required', 'ai-seo-client'), ['status' => 400]);
        }

        // Generate content using LLM
        $content = $this->generateClusterPageContent($title, $keyword, $wordCount, $contentType, $clusterContext);
        
        if (is_wp_error($content)) {
            return $content;
        }

        // Determine post status and date based on scheduling
        $postData = [
            'post_title'   => $title,
            'post_content' => $content['content'],
            'post_type'    => 'post',
            'post_author'  => get_current_user_id(),
            'meta_input'   => [
                '_sseo_ai_title' => $title,
                '_sseo_ai_description' => $content['meta_description'] ?? '',
                '_sseo_ai_focus_keyphrase' => $keyword,
                '_sseo_ai_generated' => '1',
                '_sseo_ai_generated_date' => current_time('mysql'),
            ],
        ];

        if (!empty($scheduleDate) && strtotime(get_gmt_from_date($scheduleDate)) > time()) {
            $postData['post_status'] = 'future';
            $postData['post_date']   = $scheduleDate;
        } else {
            $postData['post_status'] = 'draft';
        }

        $postId = wp_insert_post($postData);

        if (is_wp_error($postId)) {
            return $postId;
        }

        // Add tags if available
        if (!empty($content['tags'])) {
            wp_set_post_tags($postId, $content['tags']);
        }

        // Generate a featured image automatically when image API credentials are configured
        $imageAttachmentId = null;
        $imageApi = get_option('sseo_ai_client_image_api', []);
        if (current_user_can('upload_files') && !empty($imageApi['provider']) && !empty($imageApi['key'])) {
            $generator = new AIImageGenerator($this->settings, $this->llm);
            $imageAttachmentId = $generator->generateFeaturedImage($postId, 'photorealistic', $title, 100);
        }

        $result = [
            'success' => true,
            'post_id' => $postId,
            'edit_url' => get_edit_post_link($postId, ''),
            'view_url' => get_permalink($postId),
            'title' => $title,
            'word_count' => $content['word_count'] ?? 0,
        ];

        if ($imageAttachmentId) {
            $result['image_attachment_id'] = $imageAttachmentId;
        }

        return $result;
    }

    /**
     * Generate content for a cluster page
     */
    private function generateClusterPageContent(string $title, string $keyword, int $wordCount, string $contentType, string $clusterContext): array|\WP_Error
    {
        $prompt = <<<PROMPT
You are an expert SEO content writer. Create a comprehensive, SEO-optimized {$contentType} for the topic: "{$title}"

Target Keyword: {$keyword}
Target Word Count: {$wordCount} words

{$clusterContext}

Requirements:
1. Write in a professional, engaging tone
2. Include the target keyword naturally throughout the content (1-2% density)
3. Structure with proper H2 and H3 headings
4. Include an engaging introduction and conclusion
5. Add internal linking opportunities (suggest where to link to related content)
6. Include a FAQ section at the end
7. Write detailed, valuable content that satisfies search intent

Return a JSON response in this exact format:
{
    "content": "Full HTML content with proper headings, paragraphs, lists",
    "meta_description": "SEO meta description (150-160 characters)",
    "tags": ["tag1", "tag2", "tag3"],
    "word_count": 1234
}

IMPORTANT: Return ONLY the JSON, no markdown formatting, no code blocks.
PROMPT;

        $response = $this->llm->generateText($prompt, ['max_tokens' => min(4000, $wordCount * 2), 'use_case' => 'content_generation']);
        
        if (is_wp_error($response)) {
            return $response;
        }

        // Parse JSON response
        $data = json_decode(trim($response), true);
        if (!$data || !isset($data['content'])) {
            // Try to extract content from non-JSON response
            return [
                'content' => $response,
                'meta_description' => substr(strip_tags($response), 0, 160),
                'tags' => [$keyword],
                'word_count' => str_word_count(strip_tags($response)),
            ];
        }

        return $data;
    }

    /**
     * Generate a complete topic cluster / topical authority map.
     */
    public function generateCluster(string $topic, string $depth = 'standard', string $language = 'en'): array|\WP_Error
    {
        $subtopicCount = $depth === 'deep' ? '20-30' : '10-15';
        $supportingCount = $depth === 'deep' ? '3-5' : '2-3';
        
        // Language mapping for prompt
        $languageNames = [
            'en' => 'English',
            'nl' => 'Dutch',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'pl' => 'Polish',
        ];
        $langName = $languageNames[$language] ?? 'English';

        $prompt = <<<PROMPT
You are a topical authority expert (like MarketMuse). Generate a complete topic cluster map for building topical authority around: "{$topic}"

IMPORTANT: ALL content must be in {$langName} language. Use the exact topic "{$topic}" as provided - do NOT translate it to English. Return all titles, descriptions, keywords, and strategy in {$langName}.

Create a pillar-cluster content architecture. Return JSON only (no markdown):
{{
    "pillar_page": {{
        "title": "Comprehensive pillar page title",
        "slug": "url-slug",
        "description": "What this pillar page covers",
        "target_keyword": "primary keyword",
        "target_word_count": 3000,
        "search_intent": "informational"
    }},
    "clusters": [
        {{
            "name": "Subtopic cluster name",
            "description": "What this cluster covers",
            "hub_page": {{
                "title": "Hub article title",
                "slug": "url-slug",
                "target_keyword": "cluster keyword",
                "target_word_count": 2000,
                "search_intent": "informational|transactional|commercial",
                "priority": "high|medium|low"
            }},
            "supporting_pages": [
                {{
                    "title": "Supporting article title",
                    "slug": "url-slug",
                    "target_keyword": "long-tail keyword",
                    "target_word_count": 1200,
                    "search_intent": "informational|transactional|commercial",
                    "content_type": "guide|how-to|comparison|listicle|case-study|FAQ",
                    "priority": "high|medium|low"
                }}
            ]
        }}
    ],
    "internal_linking_strategy": [
        "Linking rule 1: Pillar links to all hub pages",
        "Linking rule 2: Hub pages link to their supporting pages and back to pillar"
    ],
    "content_calendar": [
        {{"week": 1, "action": "Write pillar page", "pages": ["pillar slug"]}},
        {{"week": 2, "action": "Write first hub pages", "pages": ["slug1", "slug2"]}}
    ],
    "total_pages": 25,
    "estimated_months": 3,
    "topical_authority_score_potential": 85
}}

Requirements:
- Generate {$subtopicCount} subtopic clusters
- Each cluster should have 1 hub page and {$supportingCount} supporting pages
- Cover the topic comprehensively — leave no major subtopic unaddressed
- Include a mix of search intents (informational, transactional, commercial)
- Include a mix of content types
- Prioritize by search volume potential and strategic importance
- Internal linking strategy should create clear topical silos
- Content calendar should be realistic (2-4 pages per week)

Return ONLY valid JSON.
PROMPT;

        $result = $this->llm->call($prompt, null, 'seo_expert', 4000, [], 'keyword_research');
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/^```(?:json)?\s*\n?/i', '', trim($text));
        $text = preg_replace('/\n?```\s*$/', '', $text);

        // Try to extract JSON if there's extra text around it
        $jsonStart = strpos($text, '{');
        $jsonEnd = strrpos($text, '}');
        if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
            $text = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        }

        $data = json_decode(trim($text), true);
        if (!$data || !isset($data['pillar_page'])) {
            return new \WP_Error('parse_error', __('Failed to generate topic cluster. LLM response was not valid JSON.', 'ai-seo-client') . ' Response: ' . substr($result['text'] ?? '', 0, 500));
        }

        $data['topic'] = $topic;
        $data['generated_at'] = current_time('mysql');
        $data['depth'] = $depth;

        return $data;
    }

    /**
     * Audit existing site content against a topic cluster.
     * Identifies what content already exists and what gaps remain.
     */
    public function auditExistingContent(string $topic): array
    {
        // Search site for related content
        $postTypes = get_post_types(['public' => true]);
        unset($postTypes['attachment']);

        $words = array_filter(explode(' ', $topic), fn($w) => strlen($w) > 2);

        $existingContent = [];
        $allPosts = get_posts([
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($allPosts as $post) {
            $titleLower = strtolower($post->post_title);
            $contentLower = strtolower(wp_strip_all_tags($post->post_content));
            $fullText = $titleLower . ' ' . $contentLower;

            // Check relevance using keyword matching
            $matchScore = 0;
            foreach ($words as $word) {
                $wordLower = strtolower($word);
                if (stripos($titleLower, $wordLower) !== false) $matchScore += 3;
                $matchScore += substr_count($contentLower, $wordLower);
            }

            // Also check focus keyphrase
            $keyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
            if ($keyphrase && stripos(strtolower($keyphrase), strtolower($topic)) !== false) {
                $matchScore += 10;
            }

            if ($matchScore >= 3) {
                $seoScore = get_post_meta($post->ID, '_sseo_ai_score', true);
                $wordCount = str_word_count(wp_strip_all_tags($post->post_content));

                $existingContent[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'edit_url' => get_edit_post_link($post->ID, ''),
                    'type' => $post->post_type,
                    'date' => $post->post_date,
                    'word_count' => $wordCount,
                    'seo_score' => $seoScore !== '' ? (int) $seoScore : null,
                    'focus_keyphrase' => $keyphrase ?: null,
                    'relevance_score' => $matchScore,
                ];
            }
        }

        // Sort by relevance
        usort($existingContent, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        // Calculate coverage stats
        $totalWords = array_sum(array_column($existingContent, 'word_count'));
        $avgScore = 0;
        $scoredPosts = array_filter($existingContent, fn($p) => $p['seo_score'] !== null);
        if (!empty($scoredPosts)) {
            $avgScore = (int) round(array_sum(array_column($scoredPosts, 'seo_score')) / count($scoredPosts));
        }

        return [
            'topic' => $topic,
            'existing_content' => array_slice($existingContent, 0, 50),
            'stats' => [
                'total_pages' => count($existingContent),
                'total_words' => $totalWords,
                'avg_seo_score' => $avgScore,
                'has_pillar' => count($existingContent) > 0 && $existingContent[0]['word_count'] >= 2000,
            ],
        ];
    }

    // Persistence — save/load clusters in options
    public function restSaveCluster(\WP_REST_Request $request): array
    {
        $cluster = $request->get_param('cluster');
        $clusters = get_option('aiseo_topic_clusters', []);

        $cluster['saved_at'] = current_time('mysql');
        $cluster['id'] = count($clusters) + 1;
        $clusters[] = $cluster;

        update_option('aiseo_topic_clusters', $clusters);
        return ['success' => true, 'id' => $cluster['id']];
    }

    public function restListClusters(\WP_REST_Request $request): array
    {
        return get_option('aiseo_topic_clusters', []);
    }

    public function restDeleteCluster(\WP_REST_Request $request): array
    {
        $id = (int) $request->get_param('id');
        $clusters = get_option('aiseo_topic_clusters', []);
        $clusters = array_values(array_filter($clusters, fn($c) => ($c['id'] ?? 0) !== $id));
        update_option('aiseo_topic_clusters', $clusters);
        return ['success' => true];
    }

    public function restGetCluster(\WP_REST_Request $request): array|\WP_Error
    {
        $id = (int) $request->get_param('id');
        $clusters = get_option('aiseo_topic_clusters', []);
        
        foreach ($clusters as $cluster) {
            if (($cluster['id'] ?? 0) === $id) {
                return $cluster;
            }
        }
        
        return new \WP_Error('not_found', 'Cluster not found', ['status' => 404]);
    }

    // REST handlers
    public function restGenerateCluster(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->generateCluster(
            sanitize_text_field($request->get_param('topic')),
            sanitize_text_field($request->get_param('depth') ?: 'standard'),
            sanitize_text_field($request->get_param('language') ?: 'en')
        );
    }

    public function restAuditExistingContent(\WP_REST_Request $request): array
    {
        return $this->auditExistingContent(sanitize_text_field($request->get_param('topic')));
    }

    /**
     * Render admin page
     */
    public function renderPage(): void
    {
        ?>
        <style>
            .wrap.sseo-ai-modern { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .sseo-ai-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color: #fff; padding: 30px 40px; margin: -10px -20px 0 -20px; }
            .sseo-ai-header h1 { font-size: 28px; font-weight: 700; color: #fff; margin: 0; }
            .sseo-ai-header p { margin: 10px 0 0 0; opacity: 0.8; }
            .sseo-ai-content { padding: 40px; background: linear-gradient(135deg, #3b82f6 0%, #ec4899 50%, #FF4D00 100%); min-height: calc(100vh - 150px); }
            .sseo-ai-dashboard-card { background: rgba(255, 255, 255, 0.95); border-radius: 12px; padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Topic Clusters & Topical Authority', 'ai-seo-client'); ?></h1>
                <p><?php esc_html_e('Build topical authority with pillar-cluster content maps. Plan comprehensive content strategies that establish you as the go-to resource on any topic.', 'ai-seo-client'); ?></p>
            </div>
            
            <div class="sseo-ai-content">
                <div style="max-width:1400px;">
                    <!-- Generator -->
                    <div class="postbox" style="padding:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Generate Topic Cluster', 'ai-seo-client'); ?></h2>
                    <div style="display:flex;gap:10px;align-items:end;">
                        <div style="flex:1;">
                            <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Core Topic', 'ai-seo-client'); ?></label>
                            <input type="text" id="tc-topic" class="large-text" placeholder="<?php esc_attr_e('e.g. WordPress SEO, email marketing, project management', 'ai-seo-client'); ?>">
                        </div>
                        <div>
                            <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Language', 'ai-seo-client'); ?></label>
                            <select id="tc-language" style="width:140px;">
                                <option value="nl">Nederlands</option>
                                <option value="en" selected>English</option>
                                <option value="de">Deutsch</option>
                                <option value="fr">Français</option>
                                <option value="es">Español</option>
                                <option value="it">Italiano</option>
                                <option value="pt">Português</option>
                                <option value="pl">Polski</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e('Depth', 'ai-seo-client'); ?></label>
                            <select id="tc-depth">
                                <option value="standard"><?php esc_html_e('Standard (10-15 clusters)', 'ai-seo-client'); ?></option>
                                <option value="deep"><?php esc_html_e('Deep (20-30 clusters)', 'ai-seo-client'); ?></option>
                            </select>
                        </div>
                        <button type="button" class="button button-primary" id="tc-generate" style="height:30px;"><?php esc_html_e('Generate Map', 'ai-seo-client'); ?></button>
                        <button type="button" class="button" id="tc-audit" style="height:30px;"><?php esc_html_e('Audit Existing Content', 'ai-seo-client'); ?></button>
                    </div>
                </div>

                <!-- Audit Results -->
                <div id="tc-audit-result" style="display:none;margin-top:20px;"></div>

                <!-- Cluster Map -->
                <div id="tc-result" style="display:none;margin-top:20px;">
                    <!-- Overview -->
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;">
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="tc-total-pages" style="font-size:28px;font-weight:bold;color:#2563eb;">0</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Total Pages', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="tc-clusters" style="font-size:28px;font-weight:bold;color:#059669;">0</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Clusters', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="tc-months" style="font-size:28px;font-weight:bold;color:#7c3aed;">0</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Est. Months', 'ai-seo-client'); ?></div>
                        </div>
                        <div class="postbox" style="padding:15px;text-align:center;">
                            <div id="tc-authority" style="font-size:28px;font-weight:bold;color:#d97706;">0</div>
                            <div style="font-size:12px;color:#666;"><?php esc_html_e('Authority Potential', 'ai-seo-client'); ?></div>
                        </div>
                    </div>

                    <!-- Pillar Page -->
                    <div id="tc-pillar" class="postbox" style="padding:20px;border-left:4px solid #2563eb;"></div>

                    <!-- Cluster Grid -->
                    <div id="tc-cluster-grid" style="margin-top:20px;"></div>

                    <!-- Linking Strategy -->
                    <div class="postbox" style="padding:15px;margin-top:20px;">
                        <h3 style="margin-top:0;"><?php esc_html_e('Internal Linking Strategy', 'ai-seo-client'); ?></h3>
                        <ul id="tc-linking" style="list-style:none;padding:0;"></ul>
                    </div>

                    <!-- Content Calendar -->
                    <div class="postbox" style="padding:15px;margin-top:20px;">
                        <h3 style="margin-top:0;"><?php esc_html_e('Content Calendar', 'ai-seo-client'); ?></h3>
                        <div id="tc-calendar" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px;"></div>
                    </div>

                    <!-- Review & Bulk Generate Section -->
                    <div class="postbox" style="padding:20px;margin-top:20px;background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:2px solid #16a34a;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                            <h3 style="margin:0;color:#166534;">📋 <?php esc_html_e('Review & Generate Content', 'ai-seo-client'); ?></h3>
                            <div>
                                <button type="button" class="button" id="tc-toggle-review" style="margin-right:10px;">
                                    <?php esc_html_e('Review All Pages', 'ai-seo-client'); ?>
                                </button>
                                <button type="button" class="button button-primary" id="tc-save"><?php esc_html_e('Save This Cluster Map', 'ai-seo-client'); ?></button>
                            </div>
                        </div>
                        <p style="margin:0;color:#166534;font-size:13px;">
                            <?php esc_html_e('Review all pages, select which to generate, and create content drafts in bulk.', 'ai-seo-client'); ?>
                        </p>
                    </div>

                    <!-- Pages Review Table (Hidden by default) -->
                    <div id="tc-review-section" style="display:none;margin-top:20px;">
                        <div class="postbox" style="padding:20px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
                                <h3 style="margin:0;">📄 <?php esc_html_e('All Pages in Cluster', 'ai-seo-client'); ?></h3>
                                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <label style="font-size:12px;white-space:nowrap;"><?php esc_html_e('Start date', 'ai-seo-client'); ?></label>
                                        <input type="date" id="tc-schedule-start" class="small-text" style="font-size:12px;">
                                        <label style="font-size:12px;white-space:nowrap;"><?php esc_html_e('Gap (days)', 'ai-seo-client'); ?></label>
                                        <input type="number" id="tc-schedule-gap" value="3" min="1" class="small-text" style="width:60px;font-size:12px;">
                                    </div>
                                    <button type="button" class="button" id="tc-select-all"><?php esc_html_e('Select All', 'ai-seo-client'); ?></button>
                                    <button type="button" class="button" id="tc-deselect-all"><?php esc_html_e('Deselect All', 'ai-seo-client'); ?></button>
                                    <button type="button" class="button button-primary" id="tc-bulk-generate" style="background:#16a34a;border-color:#16a34a;">
                                        <?php esc_html_e('Generate Selected', 'ai-seo-client'); ?> (<span id="tc-selected-count">0</span>)
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Stats -->
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;">
                                <div style="padding:15px;background:#f8fafc;border-radius:8px;text-align:center;">
                                    <div id="tc-review-total" style="font-size:24px;font-weight:bold;color:#2563eb;">0</div>
                                    <div style="font-size:12px;color:#64748b;"><?php esc_html_e('Total Pages', 'ai-seo-client'); ?></div>
                                </div>
                                <div style="padding:15px;background:#f8fafc;border-radius:8px;text-align:center;">
                                    <div id="tc-review-selected" style="font-size:24px;font-weight:bold;color:#16a34a;">0</div>
                                    <div style="font-size:12px;color:#64748b;"><?php esc_html_e('Selected', 'ai-seo-client'); ?></div>
                                </div>
                                <div style="padding:15px;background:#f8fafc;border-radius:8px;text-align:center;">
                                    <div id="tc-review-words" style="font-size:24px;font-weight:bold;color:#7c3aed;">0</div>
                                    <div style="font-size:12px;color:#64748b;"><?php esc_html_e('Total Words', 'ai-seo-client'); ?></div>
                                </div>
                                <div style="padding:15px;background:#f8fafc;border-radius:8px;text-align:center;">
                                    <div id="tc-review-est-cost" style="font-size:24px;font-weight:bold;color:#d97706;">$0</div>
                                    <div style="font-size:12px;color:#64748b;"><?php esc_html_e('Est. API Cost', 'ai-seo-client'); ?></div>
                                </div>
                            </div>

                            <!-- Pages Table -->
                            <div style="max-height:600px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;">
                                <table class="wp-list-table widefat striped" style="margin:0;border:none;" id="tc-pages-table">
                                    <thead style="position:sticky;top:0;background:#f8fafc;z-index:10;">
                                        <tr>
                                            <th style="width:40px;text-align:center;"><input type="checkbox" id="tc-select-all-checkbox"></th>
                                            <th><?php esc_html_e('Page Title', 'ai-seo-client'); ?></th>
                                            <th style="width:150px;"><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                                            <th style="width:180px;"><?php esc_html_e('Target Keyword', 'ai-seo-client'); ?></th>
                                            <th style="width:100px;"><?php esc_html_e('Words', 'ai-seo-client'); ?></th>
                                            <th style="width:100px;"><?php esc_html_e('Priority', 'ai-seo-client'); ?></th>
                                            <th style="width:120px;"><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="tc-pages-tbody">
                                        <!-- Populated by JS -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Bulk Progress -->
                            <div id="tc-bulk-progress" style="display:none;margin-top:20px;padding:20px;background:#f0fdf4;border-radius:8px;">
                                <h4 style="margin:0 0 15px 0;color:#166534;">⏳ <?php esc_html_e('Generating Content...', 'ai-seo-client'); ?></h4>
                                <div style="background:#e2e8f0;border-radius:10px;height:24px;overflow:hidden;margin-bottom:10px;">
                                    <div id="tc-progress-bar" style="background:linear-gradient(90deg,#16a34a,#22c55e);height:100%;width:0%;transition:width 0.3s ease;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:600;">0%</div>
                                </div>
                                <div id="tc-progress-text" style="font-size:13px;color:#64748b;"><?php esc_html_e('Preparing...', 'ai-seo-client'); ?></div>
                                <div id="tc-progress-log" style="margin-top:15px;max-height:200px;overflow-y:auto;font-family:monospace;font-size:11px;background:#fff;padding:10px;border-radius:4px;border:1px solid #e2e8f0;"></div>
                            </div>

                            <!-- Results -->
                            <div id="tc-bulk-results" style="display:none;margin-top:20px;"></div>
                        </div>
                    </div>
                </div>

                    <!-- Saved Clusters -->
                    <div class="postbox" style="padding:15px;margin-top:30px;">
                        <h3 style="margin-top:0;"><?php esc_html_e('Saved Cluster Maps', 'ai-seo-client'); ?></h3>
                        <div id="tc-saved-list"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var currentCluster = null;

            // Load saved clusters
            function loadSaved() {
                wp.apiFetch({ path: '/sseo-ai/v1/clusters/list' }).then(function(list) {
                    if (!list || !list.length) {
                        $('#tc-saved-list').html('<p style="color:#999;"><?php echo esc_js(__('No saved clusters yet.', 'ai-seo-client')); ?></p>');
                        return;
                    }
                    var html = '<table class="wp-list-table widefat striped" style="font-size:13px;"><thead><tr><th>Topic</th><th>Pages</th><th>Clusters</th><th>Date</th><th style="width:120px;"></th></tr></thead><tbody>';
                    list.forEach(function(c) {
                        html += '<tr><td><strong>' + (c.topic || '—') + '</strong></td>' +
                            '<td>' + (c.total_pages || '—') + '</td>' +
                            '<td>' + ((c.clusters||[]).length) + '</td>' +
                            '<td>' + (c.saved_at || c.generated_at || '—') + '</td>' +
                            '<td>' +
                            '<button class="button button-small tc-view-saved" data-id="' + c.id + '" style="margin-right:5px;">View</button>' +
                            '<button class="button button-small tc-delete-saved" data-id="' + c.id + '">Delete</button>' +
                            '</td></tr>';
                    });
                    html += '</tbody></table>';
                    $('#tc-saved-list').html(html);
                });
            }
            loadSaved();

            $(document).on('click', '.tc-delete-saved', function() {
                var id = $(this).data('id');
                if (!confirm('Delete?')) return;
                wp.apiFetch({ path: '/sseo-ai/v1/clusters/' + id, method: 'DELETE' }).then(loadSaved);
            });

            // View saved cluster
            $(document).on('click', '.tc-view-saved', function() {
                var id = $(this).data('id');
                wp.apiFetch({ path: '/sseo-ai/v1/clusters/' + id }).then(function(data) {
                    currentCluster = data;
                    $('#tc-topic').val(data.topic || '');
                    renderCluster(data);
                    $('#tc-result').show();
                    // Scroll to result
                    $('html, body').animate({
                        scrollTop: $('#tc-result').offset().top - 100
                    }, 500);
                }).catch(function(err) {
                    alert(err.message || 'Failed to load cluster');
                });
            });

            // Generate
            $('#tc-generate').on('click', function() {
                var topic = $('#tc-topic').val().trim();
                if (!topic) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                wp.apiFetch({
                    path: '/sseo-ai/v1/clusters/generate',
                    method: 'POST',
                    data: { topic: topic, depth: $('#tc-depth').val(), language: $('#tc-language').val() }
                }).then(function(data) {
                    currentCluster = data;
                    renderCluster(data);
                    $('#tc-result').show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Generate Map', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Generate Map', 'ai-seo-client')); ?>');
                }).finally(function() {
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            });

            // Audit
            $('#tc-audit').on('click', function() {
                var topic = $('#tc-topic').val().trim();
                if (!topic) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Auditing...', 'ai-seo-client')); ?>');
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                wp.apiFetch({
                    path: '/sseo-ai/v1/clusters/audit',
                    method: 'POST',
                    data: { topic: topic }
                }).then(function(data) {
                    var s = data.stats;
                    var html = '<div class="postbox" style="padding:20px;">' +
                        '<h3 style="margin-top:0;">Existing Content for "' + data.topic + '"</h3>' +
                        '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:15px;">' +
                        '<div style="text-align:center;padding:10px;background:#f0f7ff;border-radius:6px;"><div style="font-size:24px;font-weight:bold;">' + s.total_pages + '</div><div style="font-size:11px;color:#666;">Pages Found</div></div>' +
                        '<div style="text-align:center;padding:10px;background:#ecfdf5;border-radius:6px;"><div style="font-size:24px;font-weight:bold;">' + s.total_words.toLocaleString() + '</div><div style="font-size:11px;color:#666;">Total Words</div></div>' +
                        '<div style="text-align:center;padding:10px;background:#fef3c7;border-radius:6px;"><div style="font-size:24px;font-weight:bold;">' + s.avg_seo_score + '</div><div style="font-size:11px;color:#666;">Avg SEO Score</div></div>' +
                        '<div style="text-align:center;padding:10px;background:' + (s.has_pillar ? '#ecfdf5' : '#fcf0f1') + ';border-radius:6px;"><div style="font-size:24px;font-weight:bold;">' + (s.has_pillar ? '✓' : '✕') + '</div><div style="font-size:11px;color:#666;">Has Pillar Page</div></div>' +
                        '</div>';

                    if (data.existing_content.length) {
                        html += '<table class="wp-list-table widefat striped" style="font-size:12px;"><thead><tr><th>Title</th><th>Words</th><th>Score</th><th>Keyphrase</th></tr></thead><tbody>';
                        data.existing_content.forEach(function(p) {
                            html += '<tr><td><a href="' + (p.edit_url||'#') + '">' + p.title + '</a></td>' +
                                '<td>' + p.word_count + '</td>' +
                                '<td>' + (p.seo_score !== null ? p.seo_score : '—') + '</td>' +
                                '<td>' + (p.focus_keyphrase || '—') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    } else {
                        html += '<p style="color:#999;">No existing content found for this topic.</p>';
                    }
                    html += '</div>';
                    $('#tc-audit-result').html(html).show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Audit Existing Content', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Audit Existing Content', 'ai-seo-client')); ?>');
                }).finally(function() {
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            });

            // Generate content for cluster item
            function generateClusterContent(title, keyword, wordCount, contentType, buttonElement, scheduleDate) {
                var context = currentCluster ? 'Part of "' + (currentCluster.topic || '') + '" topic cluster. Related pages: ' +
                    ((currentCluster.clusters||[]).map(function(c) {
                        return (c.hub_page?.title||'') + ', ' + (c.supporting_pages||[]).map(function(s){return s.title;}).join(', ');
                    }).join(', ').substring(0, 500)) : '';

                var btn = $(buttonElement);
                var originalText = btn.text();
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                var payload = {
                    title: title,
                    keyword: keyword,
                    word_count: wordCount || 1500,
                    content_type: contentType || 'article',
                    cluster_context: context
                };
                if (scheduleDate) {
                    payload.schedule_date = scheduleDate;
                }

                wp.apiFetch({
                    path: '/sseo-ai/v1/clusters/generate-content',
                    method: 'POST',
                    data: payload
                }).then(function(res) {
                    var statusLabel = scheduleDate ? '✓ <?php echo esc_js(__('Scheduled', 'ai-seo-client')); ?>' : '✓ <?php echo esc_js(__('Created Draft', 'ai-seo-client')); ?>';
                    btn.prop('disabled', false).html(statusLabel);
                    btn.after(' <a href="' + res.edit_url + '" class="button button-small" style="margin-left:5px;"><?php echo esc_js(__('Edit', 'ai-seo-client')); ?></a>');
                    setTimeout(function() {
                        btn.text(originalText).prop('disabled', false);
                    }, 3000);
                }).catch(function(err) {
                    alert('<?php echo esc_js(__('Error:', 'ai-seo-client')); ?> ' + (err.message || '<?php echo esc_js(__('Failed to generate content', 'ai-seo-client')); ?>'));
                    btn.prop('disabled', false).text(originalText);
                }).finally(function() {
                    if (typeof sseoHideLoader === 'function') sseoHideLoader();
                });
            }

            function renderCluster(data) {
                currentCluster = data;
                $('#tc-total-pages').text(data.total_pages || 0);
                $('#tc-clusters').text((data.clusters||[]).length);
                $('#tc-months').text(data.estimated_months || 0);
                $('#tc-authority').text(data.topical_authority_score_potential || 0);

                // Pillar with generate button
                var p = data.pillar_page || {};
                $('#tc-pillar').html(
                    '<div style="display:flex;justify-content:space-between;align-items:flex-start;">' +
                    '<div>' +
                    '<h2 style="margin-top:0;color:#2563eb;">🏛️ ' + (p.title || 'Pillar Page') + '</h2>' +
                    '<p>' + (p.description || '') + '</p>' +
                    '<div style="display:flex;gap:15px;font-size:13px;color:#666;">' +
                    '<span>🎯 ' + (p.target_keyword || '') + '</span>' +
                    '<span>📝 ' + (p.target_word_count || 3000) + ' words</span>' +
                    '<span>🔍 ' + (p.search_intent || '') + '</span></div>' +
                    '</div>' +
                    '<button type="button" class="button button-primary tc-generate-content" ' +
                    'data-title="' + escapeHtml(p.title || '') + '" ' +
                    'data-keyword="' + escapeHtml(p.target_keyword || '') + '" ' +
                    'data-words="' + (p.target_word_count || 3000) + '" ' +
                    'data-type="pillar"><?php echo esc_js(__('Generate Content', 'ai-seo-client')); ?></button>' +
                    '</div>'
                );

                // Clusters with generate buttons
                var gridHtml = '';
                var priorityColors = {high:'#dc2626',medium:'#d97706',low:'#059669'};
                (data.clusters || []).forEach(function(cl, idx) {
                    gridHtml += '<div class="postbox" style="padding:15px;margin-bottom:15px;">' +
                        '<h3 style="margin-top:0;">📂 ' + cl.name + '</h3>' +
                        '<p style="font-size:12px;color:#666;">' + (cl.description || '') + '</p>';

                    // Hub with generate button
                    var h = cl.hub_page || {};
                    gridHtml += '<div style="padding:10px;background:#eff6ff;border-radius:6px;border-left:3px solid #2563eb;margin:10px 0;display:flex;justify-content:space-between;align-items:center;">' +
                        '<div>' +
                        '<strong>Hub: ' + (h.title || '') + '</strong>' +
                        '<div style="font-size:11px;color:#666;margin-top:4px;">' +
                        '🎯 ' + (h.target_keyword || '') + ' · ' + (h.target_word_count || 0) + ' words · ' +
                        '<span style="color:' + (priorityColors[h.priority]||'#999') + ';">' + (h.priority || '') + '</span></div>' +
                        '</div>' +
                        '<button type="button" class="button tc-generate-content" ' +
                        'data-title="' + escapeHtml(h.title || '') + '" ' +
                        'data-keyword="' + escapeHtml(h.target_keyword || '') + '" ' +
                        'data-words="' + (h.target_word_count || 1500) + '" ' +
                        'data-type="hub"><?php echo esc_js(__('Generate', 'ai-seo-client')); ?></button>' +
                        '</div>';

                    // Supporting pages with generate buttons
                    (cl.supporting_pages || []).forEach(function(sp) {
                        gridHtml += '<div style="padding:8px 10px;margin:4px 0 4px 20px;background:#f9f9f9;border-left:2px solid #93c5fd;border-radius:0 4px 4px 0;font-size:13px;display:flex;justify-content:space-between;align-items:center;">' +
                            '<div>' + sp.title +
                            '<div style="font-size:11px;color:#666;">' +
                            '🎯 ' + (sp.target_keyword || '') + ' · ' + (sp.target_word_count || 0) + 'w · ' +
                            '<span style="background:#e0e7ff;padding:1px 4px;border-radius:2px;font-size:10px;">' + (sp.content_type || '') + '</span>' +
                            '</div></div>' +
                            '<button type="button" class="button button-small tc-generate-content" ' +
                            'data-title="' + escapeHtml(sp.title || '') + '" ' +
                            'data-keyword="' + escapeHtml(sp.target_keyword || '') + '" ' +
                            'data-words="' + (sp.target_word_count || 800) + '" ' +
                            'data-type="supporting"><?php echo esc_js(__('Generate', 'ai-seo-client')); ?></button>' +
                            '</div>';
                    });
                    gridHtml += '</div>';
                });
                $('#tc-cluster-grid').html(gridHtml);

                // Bind generate buttons
                $(document).off('click', '.tc-generate-content').on('click', '.tc-generate-content', function() {
                    var btn = $(this);
                    generateClusterContent(
                        btn.data('title'),
                        btn.data('keyword'),
                        btn.data('words'),
                        btn.data('type'),
                        this
                    );
                });

                // Linking
                var linkHtml = '';
                (data.internal_linking_strategy || []).forEach(function(rule) {
                    linkHtml += '<li style="padding:6px 10px;margin:3px 0;background:#ecfdf5;border-left:3px solid #059669;border-radius:0 4px 4px 0;font-size:13px;">🔗 ' + rule + '</li>';
                });
                $('#tc-linking').html(linkHtml);

                // Calendar
                var calHtml = '';
                (data.content_calendar || []).forEach(function(w) {
                    calHtml += '<div style="padding:12px;background:#f9f9f9;border-radius:6px;border-top:3px solid #2563eb;">' +
                        '<strong>Week ' + w.week + '</strong><br>' +
                        '<span style="font-size:12px;color:#666;">' + w.action + '</span><br>' +
                        '<span style="font-size:11px;color:#2563eb;">' + (w.pages || []).join(', ') + '</span></div>';
                });
                $('#tc-calendar').html(calHtml);
                
                // Populate review table
                populateReviewTable(data);

                // Set default schedule start date to tomorrow if empty
                if (!$('#tc-schedule-start').val()) {
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    var yyyy = tomorrow.getFullYear();
                    var mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
                    var dd = String(tomorrow.getDate()).padStart(2, '0');
                    $('#tc-schedule-start').val(yyyy + '-' + mm + '-' + dd);
                }

                // Suggest gap based on content calendar (fallback to 3 days)
                var totalPages = data.total_pages || (window.tcAllPages || []).length;
                var months = data.estimated_months || 1;
                if (totalPages && months) {
                    var suggestedGap = Math.max(1, Math.round((months * 30) / totalPages));
                    $('#tc-schedule-gap').val(suggestedGap);
                }
            }

            // Review Table Population
            function populateReviewTable(data) {
                var allPages = [];
                var idCounter = 0;
                
                // Add pillar page
                if (data.pillar_page) {
                    allPages.push({
                        id: 'pillar-' + idCounter++,
                        title: data.pillar_page.title,
                        type: 'Pillar Page',
                        typeClass: 'pillarp',
                        keyword: data.pillar_page.target_keyword,
                        words: data.pillar_page.target_word_count || 3000,
                        priority: 'high',
                        content_type: 'pillar'
                    });
                }
                
                // Add cluster pages
                (data.clusters || []).forEach(function(cluster) {
                    // Hub page
                    if (cluster.hub_page) {
                        allPages.push({
                            id: 'hub-' + idCounter++,
                            title: cluster.hub_page.title,
                            type: 'Hub: ' + cluster.name,
                            typeClass: 'hub',
                            keyword: cluster.hub_page.target_keyword,
                            words: cluster.hub_page.target_word_count || 1500,
                            priority: cluster.hub_page.priority || 'medium',
                            content_type: 'hub'
                        });
                    }
                    
                    // Supporting pages
                    (cluster.supporting_pages || []).forEach(function(page) {
                        allPages.push({
                            id: 'support-' + idCounter++,
                            title: page.title,
                            type: 'Supporting',
                            typeClass: 'supporting',
                            keyword: page.target_keyword,
                            words: page.target_word_count || 800,
                            priority: page.priority || 'low',
                            content_type: 'supporting'
                        });
                    });
                });
                
                // Store for later use
                window.tcAllPages = allPages;
                
                // Render table
                renderReviewTable(allPages);
                updateReviewStats();
            }
            
            function renderReviewTable(pages) {
                var html = '';
                var priorityColors = {high:'#dc2626',medium:'#d97706',low:'#059669'};
                var priorityLabels = {high:'<?php echo esc_js(__('High', 'ai-seo-client')); ?>',medium:'<?php echo esc_js(__('Medium', 'ai-seo-client')); ?>',low:'<?php echo esc_js(__('Low', 'ai-seo-client')); ?>'};
                
                pages.forEach(function(page) {
                    var typeBadge = '';
                    if (page.typeClass === 'pillarp') typeBadge = '<span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">PILLAR</span>';
                    else if (page.typeClass === 'hub') typeBadge = '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">HUB</span>';
                    else typeBadge = '<span style="background:#f3f4f6;color:#4b5563;padding:2px 8px;border-radius:4px;font-size:11px;">Supporting</span>';
                    
                    html += '<tr data-page-id="' + page.id + '" class="tc-page-row">' +
                        '<td style="text-align:center;"><input type="checkbox" class="tc-page-checkbox" data-page-id="' + page.id + '"></td>' +
                        '<td><strong>' + escapeHtml(page.title) + '</strong></td>' +
                        '<td>' + typeBadge + '</td>' +
                        '<td><code style="background:#f3f4f6;padding:2px 6px;border-radius:3px;font-size:11px;">' + escapeHtml(page.keyword || '-') + '</code></td>' +
                        '<td style="text-align:center;">' + page.words.toLocaleString() + '</td>' +
                        '<td style="text-align:center;"><span style="color:' + priorityColors[page.priority] + ';font-weight:600;font-size:12px;">' + priorityLabels[page.priority] + '</span></td>' +
                        '<td style="text-align:center;" class="tc-status-cell"><span class="tc-status-pending" style="background:#f3f4f6;color:#6b7280;padding:3px 10px;border-radius:12px;font-size:11px;"><?php echo esc_js(__('Pending', 'ai-seo-client')); ?></span></td>' +
                        '</tr>';
                });
                
                $('#tc-pages-tbody').html(html);
                $('#tc-review-total').text(pages.length);
            }
            
            function updateReviewStats() {
                var selected = $('.tc-page-checkbox:checked').length;
                $('#tc-review-selected').text(selected);
                $('#tc-selected-count').text(selected);
                
                var totalWords = 0;
                $('.tc-page-checkbox:checked').each(function() {
                    var pageId = $(this).data('page-id');
                    var page = window.tcAllPages.find(function(p) { return p.id === pageId; });
                    if (page) totalWords += page.words;
                });
                $('#tc-review-words').text(totalWords.toLocaleString());
                
                // Estimate cost: ~$0.03 per 1000 words (OpenAI GPT-4)
                var estCost = (totalWords / 1000) * 0.03;
                $('#tc-review-est-cost').text('$' + estCost.toFixed(2));
            }

            // Toggle Review Section
            $('#tc-toggle-review').on('click', function() {
                $('#tc-review-section').slideToggle();
            });
            
            // Select All Checkbox
            $('#tc-select-all-checkbox').on('change', function() {
                $('.tc-page-checkbox').prop('checked', $(this).is(':checked'));
                updateReviewStats();
            });
            
            // Individual checkbox change
            $(document).on('change', '.tc-page-checkbox', function() {
                updateReviewStats();
            });
            
            // Select All Button
            $('#tc-select-all').on('click', function() {
                $('.tc-page-checkbox').prop('checked', true);
                $('#tc-select-all-checkbox').prop('checked', true);
                updateReviewStats();
            });
            
            // Deselect All Button
            $('#tc-deselect-all').on('click', function() {
                $('.tc-page-checkbox').prop('checked', false);
                $('#tc-select-all-checkbox').prop('checked', false);
                updateReviewStats();
            });
            
            // Bulk Generate
            $('#tc-bulk-generate').on('click', function() {
                var selectedIds = [];
                $('.tc-page-checkbox:checked').each(function() {
                    selectedIds.push($(this).data('page-id'));
                });

                if (selectedIds.length === 0) {
                    alert('<?php echo esc_js(__('Please select at least one page to generate.', 'ai-seo-client')); ?>');
                    return;
                }

                // Scheduling settings
                var startDateInput = $('#tc-schedule-start').val();
                var gapDays = parseInt($('#tc-schedule-gap').val(), 10) || 3;
                if (!startDateInput) {
                    // Default to tomorrow
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    var yyyy = tomorrow.getFullYear();
                    var mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
                    var dd = String(tomorrow.getDate()).padStart(2, '0');
                    startDateInput = yyyy + '-' + mm + '-' + dd;
                }
                var startDate = new Date(startDateInput + 'T09:00:00');
                var scheduledDates = {};

                function pad(n) { return n < 10 ? '0' + n : n; }
                function formatDate(d) {
                    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                }

                if (!confirm('<?php echo esc_js(__('Generate content for', 'ai-seo-client')); ?> ' + selectedIds.length + ' <?php echo esc_js(__('pages and schedule them? This may take several minutes.', 'ai-seo-client')); ?>')) {
                    return;
                }

                // Show progress
                $('#tc-bulk-progress').show();
                $('#tc-bulk-results').hide().html('');
                $('#tc-bulk-generate').prop('disabled', true);
                if (typeof sseoShowLoader === 'function') sseoShowLoader();

                var completed = 0;
                var failed = 0;
                var total = selectedIds.length;
                var results = [];

                function updateProgress() {
                    var percent = Math.round((completed + failed) / total * 100);
                    $('#tc-progress-bar').css('width', percent + '%').text(percent + '%');
                    $('#tc-progress-text').text(completed + ' <?php echo esc_js(__('completed', 'ai-seo-client')); ?>, ' + failed + ' <?php echo esc_js(__('failed', 'ai-seo-client')); ?>, ' + (total - completed - failed) + ' <?php echo esc_js(__('remaining', 'ai-seo-client')); ?>');
                }

                function log(message) {
                    var time = new Date().toLocaleTimeString();
                    $('#tc-progress-log').prepend('[' + time + '] ' + message + '\n');
                }

                function processNext(index) {
                    if (index >= selectedIds.length) {
                        // All done
                        $('#tc-bulk-generate').prop('disabled', false);
                        $('#tc-progress-text').text('<?php echo esc_js(__('Complete!', 'ai-seo-client')); ?> ' + completed + ' <?php echo esc_js(__('pages generated', 'ai-seo-client')); ?>');
                        if (typeof sseoHideLoader === 'function') sseoHideLoader();
                        
                        // Show results
                        var resultsHtml = '<div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:8px;padding:20px;">' +
                            '<h4 style="margin:0 0 15px 0;color:#166534;">✅ <?php echo esc_js(__('Content Generation Complete!', 'ai-seo-client')); ?></h4>' +
                            '<p><?php echo esc_js(__('Successfully generated and scheduled', 'ai-seo-client')); ?> ' + completed + ' <?php echo esc_js(__('posts. They will auto-publish on their scheduled dates.', 'ai-seo-client')); ?></p>' +
                            '<a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=post')); ?>" class="button button-primary" style="background:#16a34a;border-color:#16a34a;"><?php echo esc_js(__('View Scheduled Posts', 'ai-seo-client')); ?></a>' +
                            '</div>';
                        $('#tc-bulk-results').html(resultsHtml).show();
                        return;
                    }
                    
                    var pageId = selectedIds[index];
                    var page = window.tcAllPages.find(function(p) { return p.id === pageId; });
                    if (!page) {
                        failed++;
                        updateProgress();
                        processNext(index + 1);
                        return;
                    }

                    // Compute scheduled date for this page
                    var scheduledDate = new Date(startDate);
                    scheduledDate.setDate(startDate.getDate() + (index * gapDays));
                    var scheduledDateString = formatDate(scheduledDate);
                    scheduledDates[pageId] = scheduledDateString;
                    
                    // Update status to generating
                    var $row = $('tr[data-page-id="' + pageId + '"]');
                    $row.find('.tc-status-cell').html('<span style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-size:11px;">⏳ <?php echo esc_js(__('Generating...', 'ai-seo-client')); ?></span>');
                    
                    log('<?php echo esc_js(__('Generating', 'ai-seo-client')); ?>: ' + page.title + ' → ' + scheduledDateString);
                    
                    // Get cluster context
                    var context = currentCluster ? 'Part of "' + (currentCluster.topic || '') + '" topic cluster.' : '';
                    
                    wp.apiFetch({
                        path: '/sseo-ai/v1/clusters/generate-content',
                        method: 'POST',
                        data: {
                            title: page.title,
                            keyword: page.keyword,
                            word_count: page.words,
                            content_type: page.content_type,
                            cluster_context: context,
                            schedule_date: scheduledDateString
                        }
                    }).then(function(res) {
                        completed++;
                        updateProgress();
                        log('✅ <?php echo esc_js(__('Created', 'ai-seo-client')); ?>: ' + page.title + ' (' + scheduledDateString + ')');
                        
                        // Update row status
                        $row.find('.tc-status-cell').html('<a href="' + res.edit_url + '" style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:11px;text-decoration:none;">✓ <?php echo esc_js(__('Scheduled', 'ai-seo-client')); ?></a>');
                        $row.find('.tc-page-checkbox').prop('checked', false);
                        
                        // Continue to next
                        setTimeout(function() { processNext(index + 1); }, 500);
                    }).catch(function(err) {
                        failed++;
                        updateProgress();
                        log('❌ <?php echo esc_js(__('Failed', 'ai-seo-client')); ?>: ' + page.title + ' - ' + (err.message || '<?php echo esc_js(__('Error', 'ai-seo-client')); ?>'));
                        
                        // Update row status
                        $row.find('.tc-status-cell').html('<span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:12px;font-size:11px;">❌ <?php echo esc_js(__('Failed', 'ai-seo-client')); ?></span>');
                        
                        // Continue to next
                        setTimeout(function() { processNext(index + 1); }, 500);
                    });
                }
                
                // Start processing
                processNext(0);
            });

            // Escape HTML helper
            function escapeHtml(text) {
                if (!text) return '';
                return text.replace(/["']/g, function(m) {
                    return m === '"' ? '&quot;' : '&#039;';
                });
            }

            // Save
            $('#tc-save').on('click', function() {
                if (!currentCluster) return;
                wp.apiFetch({
                    path: '/sseo-ai/v1/clusters/save',
                    method: 'POST',
                    data: { cluster: currentCluster }
                }).then(function() {
                    alert('<?php echo esc_js(__('Cluster saved!', 'ai-seo-client')); ?>');
                    loadSaved();
                });
            });
        });
        </script>
        <?php
    }
}

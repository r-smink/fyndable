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
    private ?ContentBrief $contentBrief = null;
    private ?ContentOptimizer $contentOptimizer = null;
    private ?SmartTags $smartTags = null;
    private ?FAQSchema $faqSchema = null;
    private ?OpenGraph $openGraph = null;
    private ?TruSEOScore $truSEO = null;
    private ?GeoContentScore $geoScore = null;

    public function __construct(
        Settings $settings,
        LlmClient $llm,
        ?ContentBrief $contentBrief = null,
        ?ContentOptimizer $contentOptimizer = null,
        ?SmartTags $smartTags = null,
        ?FAQSchema $faqSchema = null,
        ?OpenGraph $openGraph = null,
        ?TruSEOScore $truSEO = null,
        ?GeoContentScore $geoScore = null
    ) {
        $this->settings = $settings;
        $this->llm = $llm;
        $this->contentBrief = $contentBrief;
        $this->contentOptimizer = $contentOptimizer;
        $this->smartTags = $smartTags;
        $this->faqSchema = $faqSchema;
        $this->openGraph = $openGraph;
        $this->truSEO = $truSEO;
        $this->geoScore = $geoScore;
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
                'cluster_id' => ['type' => 'integer', 'required' => false],
                'cluster_role' => ['type' => 'string', 'required' => false],
                'cluster_map_id' => ['type' => 'integer', 'required' => false],
                'skip_cannibalism_check' => ['type' => 'boolean', 'required' => false, 'default' => false],
            ],
        ]);

        // Queue endpoints for background generation
        register_rest_route('sseo-ai/v1', '/clusters/queue', [
            'methods' => 'POST',
            'callback' => [$this, 'restQueueBulkGeneration'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/queue/(?P<queue_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetQueueStatus'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/queue/(?P<queue_id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'restCancelQueue'],
            'permission_callback' => fn() => current_user_can('publish_posts'),
        ]);

        // Internal linking endpoint — re-link cluster posts after new content is added
        register_rest_route('sseo-ai/v1', '/clusters/(?P<cluster_id>\d+)/interlink', [
            'methods' => 'POST',
            'callback' => [$this, 'restInterlinkCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
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
        $clusterId = (int) ($request->get_param('cluster_id') ?: 0);
        $clusterRole = sanitize_text_field($request->get_param('cluster_role') ?: '');
        $clusterMapId = (int) ($request->get_param('cluster_map_id') ?: 0);
        $skipCannibalismCheck = (bool) ($request->get_param('skip_cannibalism_check') ?: false);

        if (empty($title) || empty($keyword)) {
            return new \WP_Error('missing_params', __('Title and keyword are required', 'ai-seo-client'), ['status' => 400]);
        }

        // 1.4 — Anti-cannibalisatie check
        if (!$skipCannibalismCheck) {
            $cannibalism = $this->checkCannibalism($keyword);
            if ($cannibalism !== null) {
                return new \WP_Error(
                    'cannibalism_detected',
                    sprintf(
                        __('A post already targets the keyword "%s": "%s" (ID: %d). Consider updating that post instead of creating a new one, or set skip_cannibalism_check to true.', 'ai-seo-client'),
                        $keyword,
                        $cannibalism['title'],
                        $cannibalism['post_id']
                    ),
                    ['status' => 409, 'existing_post' => $cannibalism]
                );
            }
        }

        // 1.3 — Use Content Brief for SERP-driven content generation
        $briefData = null;
        if ($this->contentBrief) {
            $briefData = $this->contentBrief->generateBrief($keyword);
            // Don't fail if brief generation fails — fall back to simple prompt
        }

        // Generate content using LLM (with brief data if available)
        $content = $this->generateClusterPageContent($title, $keyword, $wordCount, $contentType, $clusterContext, $briefData);
        
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

        // 1.1 — Track cluster relationships in post meta
        if ($clusterId > 0) {
            update_post_meta($postId, '_sseo_ai_cluster_id', $clusterId);
            update_post_meta($postId, '_sseo_ai_cluster_role', $clusterRole);
            if ($clusterMapId > 0) {
                update_post_meta($postId, '_sseo_ai_cluster_map_id', $clusterMapId);
            }
        }

        // Add tags if available
        if (!empty($content['tags'])) {
            wp_set_post_tags($postId, $content['tags']);
        }

        // 1.1 — Inject internal links to other cluster posts that already exist
        if ($clusterId > 0) {
            $this->injectInternalLinks($postId, $clusterId, $keyword);
        }

        // Generate a featured image automatically when image API credentials are configured
        $imageAttachmentId = null;
        $imageApi = get_option('sseo_ai_client_image_api', []);
        if (current_user_can('upload_files') && !empty($imageApi['provider']) && !empty($imageApi['key'])) {
            $generator = new AIImageGenerator($this->settings, $this->llm);
            $imageAttachmentId = $generator->generateFeaturedImage($postId, 'photorealistic', $title, 100);
        }

        // 1.5 — Post-generation quality pipeline
        $qualityScores = $this->runPostGenerationPipeline($postId, $keyword, $content['content']);

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

        if ($qualityScores) {
            $result['quality_scores'] = $qualityScores;
        }

        if ($briefData && !is_wp_error($briefData)) {
            $result['brief_used'] = true;
        }

        return $result;
    }

    /**
     * Generate content for a cluster page
     * @param array|null $briefData Content Brief data from ContentBrief::generateBrief()
     */
    private function generateClusterPageContent(string $title, string $keyword, int $wordCount, string $contentType, string $clusterContext, ?array $briefData = null): array|\WP_Error
    {
        // Build SERP-informed section from brief data
        $briefSection = '';
        if ($briefData && !is_wp_error($briefData)) {
            $recommendedWords = $briefData['recommended_word_count'] ?? $wordCount;
            $headings = $briefData['recommended_headings'] ?? [];
            $questions = $briefData['recommended_questions'] ?? [];
            $entities = $briefData['recommended_entities'] ?? [];
            $lsi = $briefData['recommended_lsi'] ?? [];
            $intent = $briefData['search_intent'] ?? '';
            $angle = $briefData['content_angle'] ?? '';

            $headingsStr = !empty($headings) ? implode("\n- ", array_slice($headings, 0, 10)) : '';
            $questionsStr = !empty($questions) ? implode("\n- ", array_slice($questions, 0, 5)) : '';
            $entitiesStr = !empty($entities) ? implode(', ', array_slice($entities, 0, 15)) : '';
            $lsiStr = !empty($lsi) ? implode(', ', array_slice($lsi, 0, 15)) : '';

            // 3.1 — Research-backed citations from SERP sources
            $sources = $briefData['sources'] ?? [];
            $sourcesStr = '';
            if (!empty($sources)) {
                $sourceLines = [];
                foreach (array_slice($sources, 0, 5) as $src) {
                    $sourceLines[] = "- {$src['title']} ({$src['domain']}) — {$src['url']}";
                }
                $sourcesStr = implode("\n", $sourceLines);
            }

            $briefSection = "\n\nSERP Analysis (from top-ranking pages):";
            if ($intent) $briefSection .= "\nSearch Intent: {$intent}";
            if ($angle) $briefSection .= "\nUnique Angle: {$angle}";
            if ($recommendedWords) $briefSection .= "\nRecommended word count based on competitors: {$recommendedWords}";
            if ($headingsStr) $briefSection .= "\nRecommended headings:\n- {$headingsStr}";
            if ($questionsStr) $briefSection .= "\nQuestions to answer:\n- {$questionsStr}";
            if ($entitiesStr) $briefSection .= "\nEntities to include: {$entitiesStr}";
            if ($lsiStr) $briefSection .= "\nLSI/related terms to include: {$lsiStr}";
            if ($sourcesStr) $briefSection .= "\n\nAuthoritative sources to cite (reference these naturally in the content):\n{$sourcesStr}";
        }

        $prompt = <<<PROMPT
You are an expert SEO content writer. Create a comprehensive, SEO-optimized {$contentType} for the topic: "{$title}"

Target Keyword: {$keyword}
Target Word Count: {$wordCount} words

{$clusterContext}{$briefSection}

Requirements:
1. Write in a professional, engaging tone
2. Include the target keyword naturally throughout the content (1-2% density)
3. Structure with proper H2 and H3 headings
4. Include an engaging introduction and conclusion
5. Add internal linking opportunities (suggest where to link to related content)
6. Include a FAQ section at the end
7. Write detailed, valuable content that satisfies search intent
8. If SERP analysis is provided above, follow the recommended structure, entities, and questions closely
9. If authoritative sources are listed above, cite them naturally in the content (e.g. "According to [Domain]..." or link to the source)

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
     * 1.4 — Check for keyword cannibalism: is there already a post targeting this keyword?
     * Returns existing post data if found, null otherwise.
     */
    private function checkCannibalism(string $keyword): ?array
    {
        $normalizedKeyword = strtolower(trim($keyword));

        // Check by exact focus keyphrase match
        $existingPosts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'future', 'pending'],
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_sseo_ai_focus_keyphrase',
                    'value' => $normalizedKeyword,
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if (!empty($existingPosts)) {
            $post = $existingPosts[0];
            $existingKeyword = strtolower(trim(get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true)));
            // Check similarity
            similar_text($existingKeyword, $normalizedKeyword, $percent);
            if ($percent >= 80) {
                return [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'edit_url' => get_edit_post_link($post->ID, ''),
                    'focus_keyphrase' => get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true),
                    'similarity' => round($percent, 1),
                ];
            }
        }

        return null;
    }

    /**
     * 1.1 — Get all posts belonging to a cluster
     */
    private function getClusterPosts(int $clusterId, int $excludePostId = 0): array
    {
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft', 'future', 'pending'],
            'posts_per_page' => 100,
            'meta_query' => [
                [
                    'key' => '_sseo_ai_cluster_id',
                    'value' => $clusterId,
                    'compare' => '=',
                ],
            ],
        ]);

        $result = [];
        foreach ($posts as $post) {
            if ($post->ID === $excludePostId) continue;
            $result[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'keyword' => get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true),
                'role' => get_post_meta($post->ID, '_sseo_ai_cluster_role', true),
            ];
        }
        return $result;
    }

    /**
     * 1.1 — Inject internal links into a post's content, linking to other cluster posts.
     * Scans for mentions of other cluster posts' keywords/titles and converts them to links.
     */
    private function injectInternalLinks(int $postId, int $clusterId, string $currentKeyword): void
    {
        $post = get_post($postId);
        if (!$post) return;

        $content = $post->post_content;
        $clusterPosts = $this->getClusterPosts($clusterId, $postId);
        if (empty($clusterPosts)) return;

        $changed = false;
        foreach ($clusterPosts as $clusterPost) {
            // Use the cluster post's keyword or title as anchor text
            $anchorCandidates = array_filter([
                $clusterPost['keyword'] ?? '',
                $clusterPost['title'] ?? '',
            ]);

            foreach ($anchorCandidates as $anchor) {
                if (empty($anchor) || strlen($anchor) < 4) continue;

                // Check if this anchor text appears in content and isn't already a link
                $pattern = '/\b(' . preg_quote($anchor, '/') . ')\b(?![^<]*>|[^<>]*<\/a>)/i';
                if (preg_match($pattern, $content)) {
                    // Only add first occurrence to avoid over-linking
                    $replacement = '<a href="' . esc_url($clusterPost['url']) . '">$1</a>';
                    $newContent = preg_replace($pattern, $replacement, $content, 1);
                    if ($newContent && $newContent !== $content) {
                        $content = $newContent;
                        $changed = true;
                    }
                    break; // Move to next cluster post
                }
            }
        }

        if ($changed) {
            // Update post content without triggering a full re-save cycle
            wp_update_post([
                'ID' => $postId,
                'post_content' => $content,
            ], false);
        }
    }

    /**
     * 1.5 — Post-generation quality pipeline
     * Runs TruSEO scoring, Smart Tags, FAQ Schema extraction, and OG meta generation.
     */
    private function runPostGenerationPipeline(int $postId, string $keyword, string $content): array
    {
        $scores = [];
        $post = get_post($postId);
        if (!$post) return $scores;

        // TruSEO score calculation
        if ($this->truSEO) {
            try {
                $score = $this->truSEO->calculateScore($post, $keyword);
                update_post_meta($postId, '_sseo_ai_score', $score);
                $scores['tru_seo'] = $score;
            } catch (\Throwable $e) {
                // Non-fatal: continue pipeline
            }
        }

        // Smart Tags auto-generation
        if ($this->smartTags) {
            try {
                $tags = $this->smartTags->generateTags($post);
                if (!empty($tags)) {
                    // Merge with any existing tags
                    $existingTags = wp_get_post_tags($postId, ['fields' => 'names']);
                    $allTags = array_unique(array_merge($existingTags, array_slice($tags, 0, 8)));
                    wp_set_post_tags($postId, $allTags);
                    $scores['tags_generated'] = count($tags);
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        // FAQ Schema extraction
        if ($this->faqSchema) {
            try {
                $faqs = $this->faqSchema->extractFAQs($postId);
                if (!empty($faqs)) {
                    update_post_meta($postId, '_sseo_ai_auto_faqs', $faqs);
                    $scores['faqs_extracted'] = count($faqs);
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        // Content Optimizer score (if available)
        if ($this->contentOptimizer) {
            try {
                $optimizerResult = $this->contentOptimizer->scoreContent($keyword, $content, $post->post_title, $postId);
                if (!is_wp_error($optimizerResult) && isset($optimizerResult['score'])) {
                    update_post_meta($postId, '_sseo_ai_optimizer_score', $optimizerResult['score']);
                    $scores['content_optimizer'] = $optimizerResult['score'];
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        // GEO content score (AI search citability)
        if ($this->geoScore) {
            try {
                $geoResult = $this->geoScore->scoreContent($content, $keyword, $postId);
                if (isset($geoResult['score'])) {
                    $scores['geo_score'] = $geoResult['score'];
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }
        }

        return $scores;
    }

    /**
     * REST: Queue bulk generation for background processing (1.2)
     * Creates a queue entry and lets WP-Cron process items.
     */
    public function restQueueBulkGeneration(\WP_REST_Request $request): array|\WP_Error
    {
        $pages = $request->get_param('pages');
        $clusterId = (int) ($request->get_param('cluster_id') ?: 0);
        $clusterMapId = (int) ($request->get_param('cluster_map_id') ?: 0);
        $startDate = sanitize_text_field($request->get_param('start_date') ?: '');
        $gapDays = (int) ($request->get_param('gap_days') ?: 3);

        if (empty($pages) || !is_array($pages)) {
            return new \WP_Error('missing_pages', __('Pages array is required', 'ai-seo-client'), ['status' => 400]);
        }

        // Create queue entry
        $queues = get_option('sseo_ai_cluster_queues', []);
        $queueId = count($queues) + 1;

        $queueItems = [];
        $scheduleDate = $startDate ? new \DateTime($startDate) : new \DateTime('+1 day');

        foreach ($pages as $index => $page) {
            $itemScheduleDate = clone $scheduleDate;
            $itemScheduleDate->modify('+' . ($index * $gapDays) . ' days');

            $queueItems[] = [
                'title' => sanitize_text_field($page['title'] ?? ''),
                'keyword' => sanitize_text_field($page['keyword'] ?? ''),
                'word_count' => (int) ($page['word_count'] ?? 1500),
                'content_type' => sanitize_text_field($page['content_type'] ?? 'article'),
                'cluster_role' => sanitize_text_field($page['content_type'] ?? ''),
                'schedule_date' => $itemScheduleDate->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'post_id' => null,
                'error' => null,
                'attempts' => 0,
            ];
        }

        $queue = [
            'id' => $queueId,
            'cluster_id' => $clusterId,
            'cluster_map_id' => $clusterMapId,
            'items' => $queueItems,
            'total' => count($queueItems),
            'completed' => 0,
            'failed' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'started_at' => null,
            'completed_at' => null,
        ];

        $queues[] = $queue;
        update_option('sseo_ai_cluster_queues', $queues);

        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('sseo_ai_process_cluster_queue')) {
            wp_schedule_event(time() + 60, 'sseo_ai_queue_interval', 'sseo_ai_process_cluster_queue');
        }

        return [
            'success' => true,
            'queue_id' => $queueId,
            'total_items' => count($queueItems),
            'message' => __('Queue created. Content will be generated in the background.', 'ai-seo-client'),
        ];
    }

    /**
     * REST: Get queue status (1.2)
     */
    public function restGetQueueStatus(\WP_REST_Request $request): array|\WP_Error
    {
        $queueId = (int) $request->get_param('queue_id');
        $queues = get_option('sseo_ai_cluster_queues', []);

        foreach ($queues as $queue) {
            if (($queue['id'] ?? 0) === $queueId) {
                $completed = 0;
                $failed = 0;
                $pending = 0;
                $items = [];

                foreach ($queue['items'] as $item) {
                    $status = $item['status'] ?? 'pending';
                    if ($status === 'completed') $completed++;
                    elseif ($status === 'failed') $failed++;
                    else $pending++;

                    $items[] = [
                        'title' => $item['title'],
                        'keyword' => $item['keyword'],
                        'status' => $status,
                        'post_id' => $item['post_id'],
                        'edit_url' => $item['post_id'] ? get_edit_post_link($item['post_id'], '') : null,
                        'error' => $item['error'],
                    ];
                }

                return [
                    'queue_id' => $queueId,
                    'status' => $queue['status'],
                    'total' => $queue['total'],
                    'completed' => $completed,
                    'failed' => $failed,
                    'pending' => $pending,
                    'items' => $items,
                    'created_at' => $queue['created_at'],
                    'completed_at' => $queue['completed_at'],
                ];
            }
        }

        return new \WP_Error('not_found', __('Queue not found', 'ai-seo-client'), ['status' => 404]);
    }

    /**
     * REST: Cancel a pending queue (1.2)
     */
    public function restCancelQueue(\WP_REST_Request $request): array|\WP_Error
    {
        $queueId = (int) $request->get_param('queue_id');
        $queues = get_option('sseo_ai_cluster_queues', []);

        foreach ($queues as &$queue) {
            if (($queue['id'] ?? 0) === $queueId) {
                $queue['status'] = 'cancelled';
                foreach ($queue['items'] as &$item) {
                    if ($item['status'] === 'pending') {
                        $item['status'] = 'cancelled';
                    }
                }
                update_option('sseo_ai_cluster_queues', $queues);
                return ['success' => true, 'message' => __('Queue cancelled', 'ai-seo-client')];
            }
        }

        return new \WP_Error('not_found', __('Queue not found', 'ai-seo-client'), ['status' => 404]);
    }

    /**
     * REST: Re-run internal linking for all posts in a cluster (1.1)
     */
    public function restInterlinkCluster(\WP_REST_Request $request): array|\WP_Error
    {
        $clusterId = (int) $request->get_param('cluster_id');
        if ($clusterId <= 0) {
            return new \WP_Error('missing_id', __('Cluster ID is required', 'ai-seo-client'), ['status' => 400]);
        }

        $clusterPosts = $this->getClusterPosts($clusterId);
        $linked = 0;

        foreach ($clusterPosts as $clusterPost) {
            $this->injectInternalLinks($clusterPost['id'], $clusterId, $clusterPost['keyword'] ?? '');
            $linked++;
        }

        return [
            'success' => true,
            'cluster_id' => $clusterId,
            'posts_processed' => $linked,
            'message' => sprintf(__('Internal links updated for %d posts', 'ai-seo-client'), $linked),
        ];
    }

    /**
     * 1.2 — Process queue items via WP-Cron callback.
     * Processes a limited number of items per run to avoid timeouts.
     */
    public function processQueueItems(): void
    {
        $queues = get_option('sseo_ai_cluster_queues', []);
        if (empty($queues)) return;

        $maxPerRun = (int) get_option('sseo_ai_queue_batch_size', 2);
        $processed = 0;
        $allDone = true;

        foreach ($queues as &$queue) {
            if ($queue['status'] !== 'pending' && $queue['status'] !== 'processing') continue;
            $queue['status'] = 'processing';
            if (!$queue['started_at']) {
                $queue['started_at'] = current_time('mysql');
            }

            foreach ($queue['items'] as &$item) {
                if ($item['status'] !== 'pending') continue;
                if ($processed >= $maxPerRun) {
                    $allDone = false;
                    break;
                }

                $item['status'] = 'processing';
                $item['attempts'] = ($item['attempts'] ?? 0) + 1;

                try {
                    // Generate content for this item
                    $content = $this->generateClusterPageContent(
                        $item['title'],
                        $item['keyword'],
                        $item['word_count'],
                        $item['content_type'],
                        ''
                    );

                    if (is_wp_error($content)) {
                        throw new \Exception($content->get_error_message());
                    }

                    // Determine post status
                    $postData = [
                        'post_title' => $item['title'],
                        'post_content' => $content['content'],
                        'post_type' => 'post',
                        'post_author' => get_current_user_id() ?: 1,
                        'meta_input' => [
                            '_sseo_ai_title' => $item['title'],
                            '_sseo_ai_description' => $content['meta_description'] ?? '',
                            '_sseo_ai_focus_keyphrase' => $item['keyword'],
                            '_sseo_ai_generated' => '1',
                            '_sseo_ai_generated_date' => current_time('mysql'),
                            '_sseo_ai_cluster_id' => $queue['cluster_id'] ?? 0,
                            '_sseo_ai_cluster_role' => $item['cluster_role'] ?? '',
                        ],
                    ];

                    $scheduleDate = $item['schedule_date'] ?? '';
                    if (!empty($scheduleDate) && strtotime(get_gmt_from_date($scheduleDate)) > time()) {
                        $postData['post_status'] = 'future';
                        $postData['post_date'] = $scheduleDate;
                    } else {
                        $postData['post_status'] = 'draft';
                    }

                    $postId = wp_insert_post($postData);

                    if (is_wp_error($postId)) {
                        throw new \Exception($postId->get_error_message());
                    }

                    // Add tags
                    if (!empty($content['tags'])) {
                        wp_set_post_tags($postId, $content['tags']);
                    }

                    // Inject internal links
                    $clusterId = $queue['cluster_id'] ?? 0;
                    if ($clusterId > 0) {
                        $this->injectInternalLinks($postId, $clusterId, $item['keyword']);
                    }

                    // Quality pipeline
                    $this->runPostGenerationPipeline($postId, $item['keyword'], $content['content']);

                    // Featured image
                    $imageApi = get_option('sseo_ai_client_image_api', []);
                    if (!empty($imageApi['provider']) && !empty($imageApi['key'])) {
                        $generator = new AIImageGenerator($this->settings, $this->llm);
                        $generator->generateFeaturedImage($postId, 'photorealistic', $item['title'], 100);
                    }

                    $item['status'] = 'completed';
                    $item['post_id'] = $postId;
                    $queue['completed'] = ($queue['completed'] ?? 0) + 1;
                } catch (\Throwable $e) {
                    $item['status'] = 'failed';
                    $item['error'] = $e->getMessage();
                    $queue['failed'] = ($queue['failed'] ?? 0) + 1;

                    // Retry once more on next run if attempts < 2
                    if ($item['attempts'] < 2) {
                        $item['status'] = 'pending';
                        $queue['failed'] = max(0, ($queue['failed'] ?? 0) - 1);
                    }
                }

                $processed++;
            }

            // Check if queue is complete
            $pendingItems = array_filter($queue['items'], fn($i) => $i['status'] === 'pending' || $i['status'] === 'processing');
            if (empty($pendingItems)) {
                $queue['status'] = 'completed';
                $queue['completed_at'] = current_time('mysql');
            } else {
                $allDone = false;
            }
        }

        update_option('sseo_ai_cluster_queues', $queues);

        // Clear cron if all queues are done
        if ($allDone) {
            $timestamp = wp_next_scheduled('sseo_ai_process_cluster_queue');
            if ($timestamp) {
                wp_clear_scheduled_hook('sseo_ai_process_cluster_queue');
            }
        }
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
                                <span style="margin-left:10px;display:inline-flex;align-items:center;gap:5px;">
                                    <label style="font-size:12px;color:#166534;"><?php esc_html_e('Start:', 'ai-seo-client'); ?></label>
                                    <input type="date" id="tc-schedule-start" style="width:140px;" />
                                    <label style="font-size:12px;color:#166534;margin-left:5px;"><?php esc_html_e('Gap (days):', 'ai-seo-client'); ?></label>
                                    <input type="number" id="tc-schedule-gap" value="3" min="1" max="14" style="width:60px;" />
                                </span>
                                <button type="button" class="button" id="tc-sync-calendar" style="margin-left:5px;"><?php esc_html_e('Sync to Content Calendar', 'ai-seo-client'); ?></button>
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
                                <h4 style="margin:0 0 15px 0;color:#166534;">⏳ <?php esc_html_e('Generating Content in Background...', 'ai-seo-client'); ?></h4>
                                <p style="margin:0 0 10px 0;font-size:12px;color:#166534;"><?php esc_html_e('Content is generated via WP-Cron. You can close this page — check back later or view your drafts/scheduled posts.', 'ai-seo-client'); ?></p>
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
                    cluster_context: context,
                    cluster_id: currentCluster ? (currentCluster.id || 0) : 0,
                    cluster_role: contentType || ''
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
                    if (res.quality_scores) {
                        var scoreText = Object.entries(res.quality_scores).map(function(e) { return e[0] + ': ' + e[1]; }).join(', ');
                        btn.after('<span style="margin-left:8px;font-size:11px;color:#64748b;">' + escapeHtml(scoreText) + '</span>');
                    }
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
            
            // Bulk Generate — Queue-based background processing
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
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    var yyyy = tomorrow.getFullYear();
                    var mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
                    var dd = String(tomorrow.getDate()).padStart(2, '0');
                    startDateInput = yyyy + '-' + mm + '-' + dd;
                }

                if (!confirm('<?php echo esc_js(__('Generate content for', 'ai-seo-client')); ?> ' + selectedIds.length + ' <?php echo esc_js(__('pages in the background? You can close this page — content will be generated and scheduled automatically.', 'ai-seo-client')); ?>')) {
                    return;
                }

                // Build pages array for queue
                var pages = [];
                selectedIds.forEach(function(pageId) {
                    var page = window.tcAllPages.find(function(p) { return p.id === pageId; });
                    if (page) {
                        pages.push({
                            title: page.title,
                            keyword: page.keyword,
                            word_count: page.words,
                            content_type: page.content_type
                        });
                    }
                });

                // Show progress
                $('#tc-bulk-progress').show();
                $('#tc-bulk-results').hide().html('');
                $('#tc-bulk-generate').prop('disabled', true);
                $('#tc-progress-bar').css('width', '0%').text('0%');
                $('#tc-progress-text').text('<?php echo esc_js(__('Submitting to background queue...', 'ai-seo-client')); ?>');
                $('#tc-progress-log').html('');

                function log(message) {
                    var time = new Date().toLocaleTimeString();
                    $('#tc-progress-log').prepend('[' + time + '] ' + message + '\n');
                }

                // Submit to queue endpoint
                wp.apiFetch({
                    path: '/sseo-ai/v1/clusters/queue',
                    method: 'POST',
                    data: {
                        pages: pages,
                        cluster_id: currentCluster ? (currentCluster.id || 0) : 0,
                        cluster_map_id: currentCluster ? (currentCluster.id || 0) : 0,
                        start_date: startDateInput + ' 09:00:00',
                        gap_days: gapDays
                    }
                }).then(function(res) {
                    log('✅ <?php echo esc_js(__('Queue created with', 'ai-seo-client')); ?> ' + res.total_items + ' <?php echo esc_js(__('items', 'ai-seo-client')); ?>');
                    $('#tc-progress-text').text('<?php echo esc_js(__('Queue active — generating in background...', 'ai-seo-client')); ?>');

                    // Mark all selected rows as queued
                    selectedIds.forEach(function(pageId) {
                        var $row = $('tr[data-page-id="' + pageId + '"]');
                        $row.find('.tc-status-cell').html('<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:11px;">⏳ <?php echo esc_js(__('Queued', 'ai-seo-client')); ?></span>');
                    });

                    // Poll for status
                    var queueId = res.queue_id;
                    var pollInterval = setInterval(function() {
                        wp.apiFetch({
                            path: '/sseo-ai/v1/clusters/queue/' + queueId
                        }).then(function(status) {
                            var percent = Math.round((status.completed + status.failed) / status.total * 100);
                            $('#tc-progress-bar').css('width', percent + '%').text(percent + '%');
                            $('#tc-progress-text').text(
                                status.completed + ' <?php echo esc_js(__('completed', 'ai-seo-client')); ?>, ' +
                                status.failed + ' <?php echo esc_js(__('failed', 'ai-seo-client')); ?>, ' +
                                status.pending + ' <?php echo esc_js(__('remaining', 'ai-seo-client')); ?>'
                            );

                            // Update row statuses
                            status.items.forEach(function(item, idx) {
                                var pageId = selectedIds[idx];
                                if (!pageId) return;
                                var $row = $('tr[data-page-id="' + pageId + '"]');

                                if (item.status === 'completed') {
                                    $row.find('.tc-status-cell').html('<a href="' + (item.edit_url || '#') + '" style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:11px;text-decoration:none;">✓ <?php echo esc_js(__('Done', 'ai-seo-client')); ?></a>');
                                } else if (item.status === 'failed') {
                                    $row.find('.tc-status-cell').html('<span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:12px;font-size:11px;">❌ <?php echo esc_js(__('Failed', 'ai-seo-client')); ?></span>');
                                } else if (item.status === 'processing') {
                                    $row.find('.tc-status-cell').html('<span style="background:#dbeafe;color:#1e40af;padding:3px 10px;border-radius:12px;font-size:11px;">⏳ <?php echo esc_js(__('Generating...', 'ai-seo-client')); ?></span>');
                                }
                            });

                            // Check if done
                            if (status.status === 'completed' || status.status === 'cancelled') {
                                clearInterval(pollInterval);
                                $('#tc-bulk-generate').prop('disabled', false);

                                var resultsHtml = '<div style="background:#f0fdf4;border:2px solid #16a34a;border-radius:8px;padding:20px;">' +
                                    '<h4 style="margin:0 0 15px 0;color:#166534;">✅ <?php echo esc_js(__('Content Generation Complete!', 'ai-seo-client')); ?></h4>' +
                                    '<p><?php echo esc_js(__('Generated', 'ai-seo-client')); ?> ' + status.completed + ' <?php echo esc_js(__('posts', 'ai-seo-client')); ?>' +
                                    (status.failed > 0 ? ', ' + status.failed + ' <?php echo esc_js(__('failed', 'ai-seo-client')); ?>' : '') + '.</p>' +
                                    '<a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=post')); ?>" class="button button-primary" style="background:#16a34a;border-color:#16a34a;margin-right:10px;"><?php echo esc_js(__('View Scheduled Posts', 'ai-seo-client')); ?></a>' +
                                    '<a href="<?php echo esc_url(admin_url('edit.php?post_status=draft&post_type=post')); ?>" class="button"><?php echo esc_js(__('View Drafts', 'ai-seo-client')); ?></a>' +
                                    '</div>';
                                $('#tc-bulk-results').html(resultsHtml).show();
                                log('✅ <?php echo esc_js(__('Queue completed', 'ai-seo-client')); ?>: ' + status.completed + ' <?php echo esc_js(__('done', 'ai-seo-client')); ?>, ' + status.failed + ' <?php echo esc_js(__('failed', 'ai-seo-client')); ?>');
                            }
                        }).catch(function(err) {
                            log('❌ <?php echo esc_js(__('Polling error', 'ai-seo-client')); ?>: ' + (err.message || 'unknown'));
                        });
                    }, 5000); // Poll every 5 seconds
                }).catch(function(err) {
                    alert('<?php echo esc_js(__('Error creating queue', 'ai-seo-client')); ?>: ' + (err.message || 'unknown'));
                    $('#tc-bulk-generate').prop('disabled', false);
                    $('#tc-bulk-progress').hide();
                });
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

            // Sync to Content Calendar
            $('#tc-sync-calendar').on('click', function() {
                if (!currentCluster) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'ai-seo-client')); ?>');
                var startDate = $('#tc-schedule-start').val() || '';
                var gapDays = parseInt($('#tc-schedule-gap').val(), 10) || 3;
                wp.apiFetch({
                    path: '/sseo-ai/v1/calendar/sync-cluster',
                    method: 'POST',
                    data: {
                        cluster: currentCluster,
                        start_date: startDate + ' 09:00:00',
                        gap_days: gapDays
                    }
                }).then(function(res) {
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Sync to Content Calendar', 'ai-seo-client')); ?>');
                    var msg = '<?php echo esc_js(__("Synced", "ai-seo-client")); ?> ' + res.synced + '/' + res.total + ' <?php echo esc_js(__("pages to the content calendar as scheduled drafts.", "ai-seo-client")); ?>';
                    if (res.errors.length) {
                        msg += '\n<?php echo esc_js(__("Errors:", "ai-seo-client")); ?>\n' + res.errors.join('\n');
                    }
                    alert(msg);
                }).catch(function(err) {
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Sync to Content Calendar', 'ai-seo-client')); ?>');
                    alert(err.message || 'Sync failed');
                });
            });
        });
        </script>
        <?php
    }
}

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
            'args' => [
                'topic' => ['type' => 'string', 'required' => true],
                'depth' => ['type' => 'string', 'default' => 'standard'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/audit', [
            'methods' => 'POST',
            'callback' => [$this, 'restAuditExistingContent'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'topic' => ['type' => 'string', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/save', [
            'methods' => 'POST',
            'callback' => [$this, 'restSaveCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
            'args' => [
                'cluster' => ['type' => 'object', 'required' => true],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/list', [
            'methods' => 'GET',
            'callback' => [$this, 'restListClusters'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/clusters/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restDeleteCluster'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    /**
     * Generate a complete topic cluster / topical authority map.
     */
    public function generateCluster(string $topic, string $depth = 'standard'): array|\WP_Error
    {
        $subtopicCount = $depth === 'deep' ? '20-30' : '10-15';
        $supportingCount = $depth === 'deep' ? '3-5' : '2-3';

        $prompt = <<<PROMPT
You are a topical authority expert (like MarketMuse). Generate a complete topic cluster map for building topical authority around: "{$topic}"

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

        $result = $this->llm->call($prompt, null, null, 4000);
        if (is_wp_error($result)) {
            return $result;
        }

        $text = $result['text'] ?? '';
        $text = preg_replace('/```json?\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);

        $data = json_decode(trim($text), true);
        if (!$data || !isset($data['pillar_page'])) {
            return new \WP_Error('parse_error', __('Failed to generate topic cluster', 'ai-seo-client'));
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

    // REST handlers
    public function restGenerateCluster(\WP_REST_Request $request): array|\WP_Error
    {
        return $this->generateCluster(
            sanitize_text_field($request->get_param('topic')),
            sanitize_text_field($request->get_param('depth') ?: 'standard')
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

                    <div style="margin-top:15px;">
                        <button type="button" class="button button-primary" id="tc-save"><?php esc_html_e('Save This Cluster Map', 'ai-seo-client'); ?></button>
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
                    var html = '<table class="wp-list-table widefat striped" style="font-size:13px;"><thead><tr><th>Topic</th><th>Pages</th><th>Clusters</th><th>Date</th><th></th></tr></thead><tbody>';
                    list.forEach(function(c) {
                        html += '<tr><td><strong>' + (c.topic || '—') + '</strong></td>' +
                            '<td>' + (c.total_pages || '—') + '</td>' +
                            '<td>' + ((c.clusters||[]).length) + '</td>' +
                            '<td>' + (c.saved_at || c.generated_at || '—') + '</td>' +
                            '<td><button class="button button-small tc-delete-saved" data-id="' + c.id + '">Delete</button></td></tr>';
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

            // Generate
            $('#tc-generate').on('click', function() {
                var topic = $('#tc-topic').val().trim();
                if (!topic) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ai-seo-client')); ?>');

                wp.apiFetch({
                    path: '/sseo-ai/v1/clusters/generate',
                    method: 'POST',
                    data: { topic: topic, depth: $('#tc-depth').val() }
                }).then(function(data) {
                    currentCluster = data;
                    renderCluster(data);
                    $('#tc-result').show();
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Generate Map', 'ai-seo-client')); ?>');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false).text('<?php echo esc_js(__('Generate Map', 'ai-seo-client')); ?>');
                });
            });

            // Audit
            $('#tc-audit').on('click', function() {
                var topic = $('#tc-topic').val().trim();
                if (!topic) return;
                var btn = $(this);
                btn.prop('disabled', true).text('<?php echo esc_js(__('Auditing...', 'ai-seo-client')); ?>');

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
                });
            });

            function renderCluster(data) {
                $('#tc-total-pages').text(data.total_pages || 0);
                $('#tc-clusters').text((data.clusters||[]).length);
                $('#tc-months').text(data.estimated_months || 0);
                $('#tc-authority').text(data.topical_authority_score_potential || 0);

                // Pillar
                var p = data.pillar_page || {};
                $('#tc-pillar').html(
                    '<h2 style="margin-top:0;color:#2563eb;">🏛️ ' + (p.title || 'Pillar Page') + '</h2>' +
                    '<p>' + (p.description || '') + '</p>' +
                    '<div style="display:flex;gap:15px;font-size:13px;color:#666;">' +
                    '<span>🎯 ' + (p.target_keyword || '') + '</span>' +
                    '<span>📝 ' + (p.target_word_count || 3000) + ' words</span>' +
                    '<span>🔍 ' + (p.search_intent || '') + '</span></div>'
                );

                // Clusters
                var gridHtml = '';
                var priorityColors = {high:'#dc2626',medium:'#d97706',low:'#059669'};
                (data.clusters || []).forEach(function(cl, idx) {
                    gridHtml += '<div class="postbox" style="padding:15px;margin-bottom:15px;">' +
                        '<h3 style="margin-top:0;">📂 ' + cl.name + '</h3>' +
                        '<p style="font-size:12px;color:#666;">' + (cl.description || '') + '</p>';

                    // Hub
                    var h = cl.hub_page || {};
                    gridHtml += '<div style="padding:10px;background:#eff6ff;border-radius:6px;border-left:3px solid #2563eb;margin:10px 0;">' +
                        '<strong>Hub: ' + (h.title || '') + '</strong>' +
                        '<div style="font-size:11px;color:#666;margin-top:4px;">' +
                        '🎯 ' + (h.target_keyword || '') + ' · ' + (h.target_word_count || 0) + ' words · ' +
                        '<span style="color:' + (priorityColors[h.priority]||'#999') + ';">' + (h.priority || '') + '</span></div></div>';

                    // Supporting
                    (cl.supporting_pages || []).forEach(function(sp) {
                        gridHtml += '<div style="padding:8px 10px;margin:4px 0 4px 20px;background:#f9f9f9;border-left:2px solid #93c5fd;border-radius:0 4px 4px 0;font-size:13px;">' +
                            sp.title +
                            '<div style="font-size:11px;color:#666;">' +
                            '🎯 ' + (sp.target_keyword || '') + ' · ' + (sp.target_word_count || 0) + 'w · ' +
                            '<span style="background:#e0e7ff;padding:1px 4px;border-radius:2px;font-size:10px;">' + (sp.content_type || '') + '</span>' +
                            '</div></div>';
                    });
                    gridHtml += '</div>';
                });
                $('#tc-cluster-grid').html(gridHtml);

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

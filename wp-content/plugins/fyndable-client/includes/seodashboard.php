<?php

namespace SSEOAIClient;

/**
 * SEO Dashboard
 * 
 * Site-wide SEO overview with health score, quick wins, and issue breakdown.
 * Comparable to RankMath Dashboard, AIOSEO Dashboard, Yoast SEO overview.
 */
class SeoDashboard
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/dashboard/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOverview'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Calculate full site SEO overview
     */
    public function getOverview(): array
    {
        // Check cache first (5 minute transient)
        $cacheKey = 'sseo_dashboard_overview_' . get_current_blog_id();
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $postTypes = get_post_types(['public' => true]);
        unset($postTypes['attachment']);

        // Use WP_Query for better performance with meta queries
        $query = new \WP_Query([
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => 100, // Reduced from 500 for performance
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids', // Only get IDs for performance
            'no_found_rows' => true, // Skip pagination calculation
            'update_post_meta_cache' => true, // Prime meta cache
            'update_post_term_cache' => false, // Don't need terms
        ]);

        $postIds = $query->posts;
        $total = count($postIds);

        if ($total === 0) {
            return [
                'score' => 0,
                'total_posts' => 0,
                'issues' => [],
                'quick_wins' => [],
                'breakdown' => [],
            ];
        }

        // Batch fetch all post meta in one query (much faster!)
        update_meta_cache('post', $postIds);

        // Counters
        $withTitle = 0;
        $withDesc = 0;
        $withKeyphrase = 0;
        $withOg = 0;
        $withAlt = 0;
        $withInternalLinks = 0;
        $thinContent = 0;

        $issuesList = [];
        $quickWins = [];
        $affected = [
            'missing_title' => [],
            'missing_description' => [],
            'missing_keyphrase' => [],
            'missing_og' => [],
            'missing_links' => [],
            'thin_content' => [],
        ];

        // Get thumbnail IDs in one query
        $thumbnailMap = [];
        if (!empty($postIds)) {
            global $wpdb;
            $postIdsStr = implode(',', array_map('intval', $postIds));
            $thumbnails = $wpdb->get_results(
                "SELECT post_id, meta_value as thumb_id FROM {$wpdb->postmeta} 
                 WHERE post_id IN ({$postIdsStr}) AND meta_key = '_thumbnail_id'",
                OBJECT_K
            );
            foreach ($thumbnails as $pid => $row) {
                $thumbnailMap[$pid] = $row->thumb_id;
            }
        }

        // Get all attachment alt texts in one query
        $altTexts = [];
        if (!empty($thumbnailMap)) {
            global $wpdb;
            $thumbIds = array_map('intval', array_unique($thumbnailMap));
            $thumbIdsStr = implode(',', $thumbIds);
            $alts = $wpdb->get_results(
                "SELECT post_id, meta_value as alt FROM {$wpdb->postmeta} 
                 WHERE post_id IN ({$thumbIdsStr}) AND meta_key = '_wp_attachment_image_alt' AND meta_value != ''",
                OBJECT_K
            );
            foreach ($alts as $tid => $row) {
                $altTexts[$tid] = true;
            }
        }

        foreach ($postIds as $postId) {
            // Get post object (lightweight since we used 'fields' => 'ids')
            $post = get_post($postId);
            if (!$post) continue;

            $content = $post->post_content;
            $wordCount = str_word_count(wp_strip_all_tags($content));

            // Now meta is cached, these are fast
            $seoTitle = get_post_meta($postId, '_sseo_ai_title', true);
            $seoDesc = get_post_meta($postId, '_sseo_ai_description', true);
            $keyphrase = get_post_meta($postId, '_sseo_ai_focus_keyphrase', true);
            $ogTitle = get_post_meta($postId, '_sseo_ai_og_title', true);

            if ($seoTitle) {
                $withTitle++;
            } else {
                $affected['missing_title'][] = ['id' => $postId, 'title' => $post->post_title, 'url' => admin_url('post.php?post=' . $postId . '&action=edit')];
            }
            if ($seoDesc) {
                $withDesc++;
            } else {
                $affected['missing_description'][] = ['id' => $postId, 'title' => $post->post_title, 'url' => admin_url('post.php?post=' . $postId . '&action=edit')];
            }
            if ($keyphrase) {
                $withKeyphrase++;
            } else {
                $affected['missing_keyphrase'][] = ['id' => $postId, 'title' => $post->post_title, 'url' => admin_url('post.php?post=' . $postId . '&action=edit')];
            }
            if ($ogTitle) {
                $withOg++;
            } else {
                $affected['missing_og'][] = ['id' => $postId, 'title' => $post->post_title, 'url' => admin_url('post.php?post=' . $postId . '&action=edit')];
            }
            if ($wordCount < 300) {
                $thinContent++;
                $affected['thin_content'][] = ['id' => $postId, 'title' => $post->post_title, 'url' => admin_url('post.php?post=' . $postId . '&action=edit')];
            }

            // Check internal links (cached in content)
            $hasInternalLink = false;
            if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $content, $matches)) {
                $home = parse_url(home_url(), PHP_URL_HOST);
                foreach ($matches[1] as $href) {
                    $hrefHost = parse_url($href, PHP_URL_HOST);
                    if ($hrefHost === $home || strpos($href, '/') === 0) {
                        $hasInternalLink = true;
                        break;
                    }
                }
            }
            if ($hasInternalLink) {
                $withInternalLinks++;
            } else {
                $affected['missing_links'][] = ['id' => $postId, 'title' => $post->post_title, 'url' => admin_url('post.php?post=' . $postId . '&action=edit')];
            }

            // Check featured image alt (from pre-fetched data)
            if (isset($thumbnailMap[$postId]) && isset($altTexts[$thumbnailMap[$postId]])) {
                $withAlt++;
            }

            // Collect specific issues for quick wins (limit to 20 max)
            if ($wordCount >= 300 && count($quickWins) < 20 && (!$seoTitle || !$seoDesc)) {
                $reasonParts = [];
                if (!$seoTitle) $reasonParts[] = __('missing title', 'ai-seo-client');
                if (!$seoDesc) $reasonParts[] = __('missing description', 'ai-seo-client');
                $quickWins[] = [
                    'post_id' => $postId,
                    'title' => $post->post_title,
                    'edit_url' => admin_url('post.php?post=' . $postId . '&action=edit'),
                    'reason' => sprintf(
                        __('Good content but %s — easy to fix with AI', 'ai-seo-client'),
                        implode(' ' . __('and', 'ai-seo-client') . ' ', $reasonParts)
                    ),
                ];
            }
        }

        // Calculate overall score (weighted)
        $scores = [
            'titles' => $total > 0 ? ($withTitle / $total) * 100 : 0,
            'descriptions' => $total > 0 ? ($withDesc / $total) * 100 : 0,
            'keyphrases' => $total > 0 ? ($withKeyphrase / $total) * 100 : 0,
            'og_tags' => $total > 0 ? ($withOg / $total) * 100 : 0,
            'internal_links' => $total > 0 ? ($withInternalLinks / $total) * 100 : 0,
            'content_length' => $total > 0 ? (($total - $thinContent) / $total) * 100 : 0,
        ];

        // Weighted average: meta = most important
        $overallScore = (int) round(
            $scores['titles'] * 0.25 +
            $scores['descriptions'] * 0.25 +
            $scores['keyphrases'] * 0.15 +
            $scores['og_tags'] * 0.10 +
            $scores['internal_links'] * 0.15 +
            $scores['content_length'] * 0.10
        );

        // Build issues list
        $issues = [];
        $missingTitles = $total - $withTitle;
        $missingDescs = $total - $withDesc;
        $missingKw = $total - $withKeyphrase;
        $missingOg = $total - $withOg;
        $missingLinks = $total - $withInternalLinks;

        if ($missingTitles > 0) {
            $issues[] = [
                'type' => 'critical',
                'label' => sprintf(__('%d posts missing SEO title', 'ai-seo-client'), $missingTitles),
                'count' => $missingTitles,
                'action' => 'bulk_title',
                'key' => 'missing_title',
                'affected' => array_slice($affected['missing_title'], 0, 50),
            ];
        }
        if ($missingDescs > 0) {
            $issues[] = [
                'type' => 'critical',
                'label' => sprintf(__('%d posts missing meta description', 'ai-seo-client'), $missingDescs),
                'count' => $missingDescs,
                'action' => 'bulk_desc',
                'key' => 'missing_description',
                'affected' => array_slice($affected['missing_description'], 0, 50),
            ];
        }
        if ($missingKw > 0) {
            $issues[] = [
                'type' => 'warning',
                'label' => sprintf(__('%d posts missing focus keyphrase', 'ai-seo-client'), $missingKw),
                'count' => $missingKw,
                'action' => 'bulk_keyphrase',
                'key' => 'missing_keyphrase',
                'affected' => array_slice($affected['missing_keyphrase'], 0, 50),
            ];
        }
        if ($missingOg > 0) {
            $issues[] = [
                'type' => 'warning',
                'label' => sprintf(__('%d posts missing Open Graph tags', 'ai-seo-client'), $missingOg),
                'count' => $missingOg,
                'action' => 'bulk_og',
                'key' => 'missing_og',
                'affected' => array_slice($affected['missing_og'], 0, 50),
            ];
        }
        if ($missingLinks > 0) {
            $issues[] = [
                'type' => 'info',
                'label' => sprintf(__('%d posts have no internal links', 'ai-seo-client'), $missingLinks),
                'count' => $missingLinks,
                'action' => 'link_assistant',
                'key' => 'missing_links',
                'affected' => array_slice($affected['missing_links'], 0, 50),
            ];
        }
        if ($thinContent > 0) {
            $issues[] = [
                'type' => 'info',
                'label' => sprintf(__('%d posts have thin content (<300 words)', 'ai-seo-client'), $thinContent),
                'count' => $thinContent,
                'action' => 'content_writer',
                'key' => 'thin_content',
                'affected' => array_slice($affected['thin_content'], 0, 50),
            ];
        }

        // Breakdown for chart
        $breakdown = [
            ['label' => __('SEO Titles', 'ai-seo-client'), 'done' => $withTitle, 'total' => $total, 'pct' => round($scores['titles'])],
            ['label' => __('Meta Descriptions', 'ai-seo-client'), 'done' => $withDesc, 'total' => $total, 'pct' => round($scores['descriptions'])],
            ['label' => __('Focus Keyphrases', 'ai-seo-client'), 'done' => $withKeyphrase, 'total' => $total, 'pct' => round($scores['keyphrases'])],
            ['label' => __('Open Graph Tags', 'ai-seo-client'), 'done' => $withOg, 'total' => $total, 'pct' => round($scores['og_tags'])],
            ['label' => __('Internal Links', 'ai-seo-client'), 'done' => $withInternalLinks, 'total' => $total, 'pct' => round($scores['internal_links'])],
            ['label' => __('Content Length', 'ai-seo-client'), 'done' => $total - $thinContent, 'total' => $total, 'pct' => round($scores['content_length'])],
        ];

        $result = [
            'score' => $overallScore,
            'total_posts' => $total,
            'issues' => $issues,
            'quick_wins' => array_slice($quickWins, 0, 10),
            'breakdown' => $breakdown,
        ];

        // Cache for 5 minutes (300 seconds)
        set_transient($cacheKey, $result, 300);

        return $result;
    }

    /**
     * REST endpoint
     */
    public function restGetOverview(\WP_REST_Request $request): array
    {
        return $this->getOverview();
    }

    /**
     * Render the SEO Dashboard page
     */
    public function renderPage(): void
    {
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Statistics', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card">

            <div style="max-width:1100px;">
                <p>
                    <button type="button" class="button button-primary" id="seo-dash-scan">
                        <?php esc_html_e('Analyze Site', 'ai-seo-client'); ?>
                    </button>
                    <span class="spinner" id="seo-dash-spinner" style="float:none;"></span>
                </p>

                <!-- Score + Summary Row -->
                <div id="seo-dash-main" style="display:none;">
                    <div style="display:grid; grid-template-columns:280px 1fr; gap:20px; margin-bottom:20px;">
                        <!-- Score Circle -->
                        <div class="postbox" style="padding:30px; text-align:center; margin:0;">
                            <div id="seo-score-ring" style="position:relative;width:160px;height:160px;margin:0 auto 15px;">
                                <svg viewBox="0 0 36 36" style="width:100%;height:100%;transform:rotate(-90deg);">
                                    <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e0e0e0" stroke-width="3"></circle>
                                    <circle id="seo-score-circle" cx="18" cy="18" r="15.9" fill="none" stroke="#2271b1" stroke-width="3" stroke-dasharray="0, 100" stroke-linecap="round" style="transition:stroke-dasharray 1s ease;"></circle>
                                </svg>
                                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:36px;font-weight:bold;" id="seo-score-num">—</div>
                            </div>
                            <div style="font-size:16px;font-weight:600;" id="seo-score-label"><?php esc_html_e('SEO Score', 'ai-seo-client'); ?></div>
                            <div style="color:#666;font-size:13px;" id="seo-score-posts"></div>
                        </div>

                        <!-- Breakdown Bars -->
                        <div class="postbox" style="padding:20px; margin:0;">
                            <h2 style="margin-top:0;"><?php esc_html_e('SEO Breakdown', 'ai-seo-client'); ?></h2>
                            <div id="seo-breakdown"></div>
                        </div>
                    </div>

                    <!-- Issues + Quick Wins -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="postbox" style="padding:20px; margin:0;">
                            <h2 style="margin-top:0;"><?php esc_html_e('Issues Found', 'ai-seo-client'); ?></h2>
                            <div id="seo-issues"></div>
                        </div>
                        <div class="postbox" style="padding:20px; margin:0;">
                            <h2 style="margin-top:0;"><?php esc_html_e('Quick Wins', 'ai-seo-client'); ?></h2>
                            <p style="color:#666;font-size:13px;"><?php esc_html_e('Posts with good content but missing SEO meta — easy to fix!', 'ai-seo-client'); ?></p>
                            <div id="seo-quickwins"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Issue detail modal -->
        <div id="seo-issue-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:100000;justify-content:center;align-items:center;">
            <div style="background:#fff;border-radius:8px;width:90%;max-width:700px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 40px rgba(0,0,0,0.2);">
                <div style="padding:18px 20px;border-bottom:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="margin:0;font-size:16px;" id="seo-issue-modal-title"></h3>
                    <button type="button" id="seo-issue-modal-close" style="background:none;border:none;font-size:22px;cursor:pointer;line-height:1;">&times;</button>
                </div>
                <div style="padding:20px;overflow-y:auto;flex:1;" id="seo-issue-modal-body"></div>
                <div style="padding:15px 20px;border-top:1px solid #e0e0e0;text-align:right;color:#666;font-size:12px;" id="seo-issue-modal-footer"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function getScoreColor(score) {
                if (score >= 80) return '#00a32a';
                if (score >= 50) return '#dba617';
                return '#d63638';
            }

            function getScoreLabel(score) {
                if (score >= 80) return '<?php echo esc_js(__('Great', 'ai-seo-client')); ?>';
                if (score >= 50) return '<?php echo esc_js(__('Needs Work', 'ai-seo-client')); ?>';
                return '<?php echo esc_js(__('Poor', 'ai-seo-client')); ?>';
            }

            function renderDashboard(data) {
                // Score
                var color = getScoreColor(data.score);
                $('#seo-score-num').text(data.score).css('color', color);
                $('#seo-score-circle').attr('stroke', color).attr('stroke-dasharray', data.score + ', 100');
                $('#seo-score-label').text(getScoreLabel(data.score));
                $('#seo-score-posts').text(data.total_posts + ' <?php echo esc_js(__('posts analyzed', 'ai-seo-client')); ?>');

                // Breakdown bars
                var bHtml = '';
                data.breakdown.forEach(function(b) {
                    var bColor = getScoreColor(b.pct);
                    bHtml += '<div style="margin-bottom:12px;">' +
                        '<div style="display:flex;justify-content:space-between;margin-bottom:3px;">' +
                        '<span style="font-weight:500;">' + b.label + '</span>' +
                        '<span style="color:#666;">' + b.done + '/' + b.total + ' (' + b.pct + '%)</span>' +
                        '</div>' +
                        '<div style="background:#e0e0e0;border-radius:4px;height:8px;overflow:hidden;">' +
                        '<div style="background:' + bColor + ';height:100%;width:' + b.pct + '%;transition:width 0.5s;border-radius:4px;"></div>' +
                        '</div></div>';
                });
                $('#seo-breakdown').html(bHtml);

                // Issues
                var iHtml = '';
                if (data.issues.length === 0) {
                    iHtml = '<p style="color:#00a32a;font-weight:600;"><?php echo esc_js(__('No issues found!', 'ai-seo-client')); ?></p>';
                } else {
                    data.issues.forEach(function(issue, index) {
                        var iColor = issue.type === 'critical' ? '#d63638' : (issue.type === 'warning' ? '#dba617' : '#2271b1');
                        var icon = issue.type === 'critical' ? '●' : (issue.type === 'warning' ? '▲' : 'ℹ');
                        iHtml += '<div class="seo-issue-row" data-index="' + index + '" style="padding:8px 12px;margin-bottom:6px;border-left:3px solid ' + iColor + ';background:#f9f9f9;border-radius:0 4px 4px 0;display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:background 0.15s;">' +
                            '<span><span style="color:' + iColor + ';">' + icon + '</span> ' + issue.label + '</span>' +
                            '<span style="font-size:12px;color:#666;"><?php echo esc_js(__('View', 'ai-seo-client')); ?> &rarr;</span>' +
                            '</div>';
                    });
                }
                $('#seo-issues').html(iHtml);

                // Quick wins
                var qHtml = '';
                if (data.quick_wins.length === 0) {
                    qHtml = '<p style="color:#666;"><?php echo esc_js(__('No quick wins available.', 'ai-seo-client')); ?></p>';
                } else {
                    data.quick_wins.forEach(function(qw) {
                        qHtml += '<div style="padding:6px 0;border-bottom:1px solid #eee;">' +
                            '<a href="' + qw.edit_url + '" target="_blank" style="text-decoration:none;">' + $('<span>').text(qw.title).html() + '</a>' +
                            '<div style="font-size:12px;color:#999;">' + qw.reason + '</div>' +
                            '</div>';
                    });
                }
                $('#seo-quickwins').html(qHtml);

                $('#seo-dash-main').show();
            }

            function openIssueModal(issue) {
                var title = issue.label || '<?php echo esc_js(__('Issue Details', 'ai-seo-client')); ?>';
                $('#seo-issue-modal-title').text(title);
                var body = '<p><?php echo esc_js(__('Showing up to 50 affected posts/pages:', 'ai-seo-client')); ?></p><ul style="margin:0;padding-left:18px;">';
                if (issue.affected && issue.affected.length) {
                    issue.affected.forEach(function(item) {
                        body += '<li style="margin-bottom:8px;"><a href="' + item.url + '" target="_blank">' + $('<span>').text(item.title).html() + '</a></li>';
                    });
                } else {
                    body += '<li><?php echo esc_js(__('No specific items recorded.', 'ai-seo-client')); ?></li>';
                }
                body += '</ul>';
                $('#seo-issue-modal-body').html(body);
                $('#seo-issue-modal-footer').text((issue.count || issue.affected.length || 0) + ' <?php echo esc_js(__('total affected', 'ai-seo-client')); ?>');
                $('#seo-issue-modal').css('display', 'flex');
            }

            $('#seo-issues').on('click', '.seo-issue-row', function() {
                var index = $(this).data('index');
                var data = window.sseoDashLastData;
                if (data && data.issues && data.issues[index]) {
                    openIssueModal(data.issues[index]);
                }
            });

            $('#seo-issue-modal-close, #seo-issue-modal').on('click', function(e) {
                if (e.target.id === 'seo-issue-modal' || e.target.id === 'seo-issue-modal-close') {
                    $('#seo-issue-modal').hide();
                }
            });

            // Load cached analysis on page load
            var cached = localStorage.getItem('sseo_dashboard_overview');
            if (cached) {
                try {
                    var cachedData = JSON.parse(cached);
                    window.sseoDashLastData = cachedData;
                    renderDashboard(cachedData);
                } catch (e) {}
            }

            $('#seo-dash-scan').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                $('#seo-dash-spinner').addClass('is-active');

                wp.apiFetch({ path: 'sseo-ai/v1/dashboard/overview' }).then(function(data) {
                    window.sseoDashLastData = data;
                    localStorage.setItem('sseo_dashboard_overview', JSON.stringify(data));
                    renderDashboard(data);
                    btn.prop('disabled', false);
                    $('#seo-dash-spinner').removeClass('is-active');
                }).catch(function(err) {
                    alert(err.message || 'Failed');
                    btn.prop('disabled', false);
                    $('#seo-dash-spinner').removeClass('is-active');
                });
            });
        });
        </script>
                </div>
            </div>
        </div>
        <?php
    }
}

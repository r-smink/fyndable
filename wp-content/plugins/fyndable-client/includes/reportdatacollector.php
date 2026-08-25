<?php

namespace SSEOAIClient;

/**
 * ReportDataCollector
 *
 * Gathers data from existing SEO subsystems for the weekly/daily/monthly
 * email report. Keeps the actual HTML rendering in ExternalIntegrations.
 */
class ReportDataCollector
{
    private SeoReportExport $seoReport;

    public function __construct(SeoReportExport $seoReport)
    {
        $this->seoReport = $seoReport;
    }

    /**
     * Collect all report metrics.
     *
     * @return array{
     *     summary: array,
     *     winners: array,
     *     action_items: array,
     *     technical: array,
     *     rank_data: array,
     *     period: string,
     * }
     */
    public function collect(string $period): array
    {
        global $wpdb;

        $seoData = $this->seoReport->getReportData();

        $summary = [
            'site_name' => get_bloginfo('name'),
            'site_url'  => home_url('/'),
            'period'    => $period,
            'date'      => current_time('Y-m-d H:i'),
            'total_posts' => (int) ($seoData['total_posts'] ?? 0),
            'avg_score'   => (int) ($seoData['avg_score'] ?? 0),
            'missing_seo_title' => 0,
            'missing_meta_desc' => 0,
            'missing_keyphrase' => 0,
            'thin_content'      => 0,
            'posts_with_issues' => 0,
        ];

        $postsWithIssues = [];
        $winners = [];

        foreach ($seoData['rows'] ?? [] as $row) {
            $issues = [];

            if (empty($row['seo_title'])) {
                $summary['missing_seo_title']++;
                $issues[] = __('Missing SEO title', 'ai-seo-client');
            }
            if (empty($row['seo_description'])) {
                $summary['missing_meta_desc']++;
                $issues[] = __('Missing meta description', 'ai-seo-client');
            }
            if (empty($row['focus_keyphrase'])) {
                $summary['missing_keyphrase']++;
                $issues[] = __('Missing focus keyphrase', 'ai-seo-client');
            }
            if ((int) ($row['word_count'] ?? 0) < 300) {
                $summary['thin_content']++;
                $issues[] = __('Thin content', 'ai-seo-client');
            }

            if (count($issues) > 0 || $row['issue_count'] > 0) {
                $summary['posts_with_issues']++;
                $postsWithIssues[] = [
                    'title'    => $row['title'],
                    'url'      => $row['url'],
                    'score'    => $row['score'],
                    'issues'   => $issues,
                    'word_count' => $row['word_count'],
                ];
            }

            // Winner candidates: posts with a score >= 80.
            if ((int) ($row['score'] ?? 0) >= 80) {
                $winners[] = [
                    'title' => $row['title'],
                    'url'   => $row['url'],
                    'score' => (int) $row['score'],
                ];
            }
        }

        // Best scores first.
        usort($winners, fn($a, $b) => $b['score'] <=> $a['score']);
        $winners = array_slice($winners, 0, 5);

        // Rank tracker data.
        $keywordsTable = $wpdb->prefix . 'sseo_ai_tracked_keywords';
        $historyTable  = $wpdb->prefix . 'sseo_ai_rank_history';

        $rankData = [
            'total_keywords' => 0,
            'top_keywords'   => [],
            'risers'         => [],
            'fallers'        => [],
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '{$keywordsTable}'") === $keywordsTable) {
            $keywords = $wpdb->get_results(
                "SELECT * FROM {$keywordsTable} WHERE active = 1 ORDER BY keyword ASC",
                ARRAY_A
            );

            if ($keywords) {
                $rankData['total_keywords'] = count($keywords);

                foreach ($keywords as $keyword) {
                    $current = (int) ($keyword['current_position'] ?? 0);
                    $previous = (int) ($keyword['previous_position'] ?? 0);
                    $change = $current && $previous ? $previous - $current : 0;

                    $row = [
                        'keyword'   => $keyword['keyword'],
                        'url'       => $keyword['url'],
                        'current'   => $current,
                        'previous'  => $previous,
                        'change'    => $change,
                    ];

                    if ($current > 0 && $current <= 10) {
                        $rankData['top_keywords'][] = $row;
                    }

                    if ($change > 0) {
                        $rankData['risers'][] = $row;
                    } elseif ($change < 0) {
                        $rankData['fallers'][] = $row;
                    }
                }

                usort($rankData['top_keywords'], fn($a, $b) => $a['current'] <=> $b['current']);
                $rankData['top_keywords'] = array_slice($rankData['top_keywords'], 0, 5);

                usort($rankData['risers'], fn($a, $b) => $b['change'] <=> $a['change']);
                $rankData['risers'] = array_slice($rankData['risers'], 0, 5);

                usort($rankData['fallers'], fn($a, $b) => $a['change'] <=> $b['change']);
                $rankData['fallers'] = array_slice($rankData['fallers'], 0, 5);
            }
        }

        // Technical audit.
        $lastAudit = get_option('sseo_ai_last_technical_audit', []);
        $lastAuditDate = get_option('sseo_ai_last_audit_date', '');

        $technical = [
            'last_audit_date' => $lastAuditDate,
            'has_data'        => !empty($lastAudit),
            'scores'          => [
                'crawlability' => (int) ($lastAudit['crawlability_score'] ?? 0),
                'performance'  => (int) ($lastAudit['performance_score'] ?? 0),
                'structure'    => (int) ($lastAudit['structure_score'] ?? 0),
                'sitemap'      => (int) ($lastAudit['sitemap_score'] ?? 0),
            ],
            'critical_issues'   => [],
        ];

        foreach (['crawlability', 'crawl_budget', 'url_structure', 'sitemap', 'robots_txt', 'performance'] as $section) {
            $sectionData = $lastAudit[$section] ?? [];
            if (!is_array($sectionData)) {
                continue;
            }
            foreach ($sectionData as $check) {
                if (!is_array($check)) {
                    continue;
                }
                if (($check['status'] ?? '') === 'fail') {
                    $technical['critical_issues'][] = [
                        'name'    => $check['name'] ?? '',
                        'details' => $check['details'] ?? '',
                        'fix_url' => $check['fix_url'] ?? '',
                    ];
                }
            }
        }

        // Content decay active alerts.
        $decayTable = $wpdb->prefix . 'sseo_ai_content_decay';
        $decayItems = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$decayTable}'") === $decayTable) {
            $decay = $wpdb->get_results(
                "SELECT post_id, keyword, current_position, baseline_position, position_change, severity, detected_at
                 FROM {$decayTable}
                 WHERE status = 'active'
                 ORDER BY severity DESC, detected_at DESC
                 LIMIT 5",
                ARRAY_A
            );

            if ($decay) {
                foreach ($decay as $item) {
                    $post = get_post((int) $item['post_id']);
                    $decayItems[] = [
                        'title'    => $post ? $post->post_title : __('Unknown post', 'ai-seo-client'),
                        'keyword'  => $item['keyword'],
                        'current'  => (float) ($item['current_position'] ?? 0),
                        'baseline' => (float) ($item['baseline_position'] ?? 0),
                        'change'   => (int) ($item['position_change'] ?? 0),
                        'severity' => $item['severity'] ?? 'low',
                    ];
                }
            }
        }

        // Action items summary.
        $actionItems = [
            'posts_with_issues' => array_slice($postsWithIssues, 0, 10),
            'decay'             => $decayItems,
        ];

        return [
            'summary'     => $summary,
            'winners'     => $winners,
            'action_items' => $actionItems,
            'technical'   => $technical,
            'rank_data'   => $rankData,
            'period'      => $period,
        ];
    }
}

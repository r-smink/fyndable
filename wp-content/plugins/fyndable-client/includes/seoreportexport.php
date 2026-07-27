<?php

namespace SSEOAIClient;

/**
 * SEO Report Export (CSV / HTML-PDF)
 * 
 * Exports site-wide SEO audit data as CSV or printable HTML report.
 * Includes SEO scores, missing meta, issues, and recommendations.
 * Comparable to RankMath SEO Report, AIOSEO Report.
 */
class SeoReportExport
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('admin_post_sseo_ai_export_csv', [$this, 'handleCsvExport']);
        add_action('admin_post_sseo_ai_export_pdf', [$this, 'handlePdfExport']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('SEO Report', 'ai-seo-client'),
            __('SEO Report', 'ai-seo-client'),
            'manage_options',
            'ai-seo-report',
            [$this, 'renderPage']
        );
    }

    /**
     * Gather all SEO data for the report
     */
    public function getReportData(): array
    {
        $postTypes = get_post_types(['public' => true]);
        unset($postTypes['attachment']);

        $posts = get_posts([
            'post_type' => array_values($postTypes),
            'post_status' => 'publish',
            'posts_per_page' => 500,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $rows = [];
        $totalScore = 0;
        $scored = 0;

        foreach ($posts as $post) {
            $seoTitle = get_post_meta($post->ID, '_sseo_ai_title', true);
            $seoDesc = get_post_meta($post->ID, '_sseo_ai_description', true);
            $keyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
            $ogTitle = get_post_meta($post->ID, '_sseo_ai_og_title', true);
            $score = get_post_meta($post->ID, '_sseo_ai_score', true);
            $wordCount = str_word_count(wp_strip_all_tags($post->post_content));

            $issues = [];
            if (empty($seoTitle)) $issues[] = __('Missing SEO title', 'ai-seo-client');
            if (empty($seoDesc)) $issues[] = __('Missing meta description', 'ai-seo-client');
            if (empty($keyphrase)) $issues[] = __('Missing focus keyphrase', 'ai-seo-client');
            if (empty($ogTitle)) $issues[] = __('Missing OG tags', 'ai-seo-client');
            if ($wordCount < 300) $issues[] = __('Thin content', 'ai-seo-client');

            if ($score !== '') {
                $totalScore += (int) $score;
                $scored++;
            }

            $rows[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'url' => get_permalink($post->ID),
                'word_count' => $wordCount,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDesc,
                'focus_keyphrase' => $keyphrase,
                'og_title' => $ogTitle,
                'score' => $score !== '' ? (int) $score : '',
                'issues' => implode('; ', $issues),
                'issue_count' => count($issues),
            ];
        }

        $avgScore = $scored > 0 ? round($totalScore / $scored) : 0;

        return [
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'date' => current_time('Y-m-d H:i'),
            'total_posts' => count($rows),
            'avg_score' => $avgScore,
            'rows' => $rows,
        ];
    }

    /**
     * Handle CSV export
     */
    public function handleCsvExport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }
        check_admin_referer('aiseo_export_csv');

        $data = $this->gatherReportData();

        $filename = sanitize_file_name('seo-report-' . date('Y-m-d') . '.csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Header row
        fputcsv($output, [
            'ID',
            'Title',
            'Type',
            'URL',
            'Word Count',
            'SEO Score',
            'SEO Title',
            'Meta Description',
            'Focus Keyphrase',
            'OG Title',
            'Issues',
        ]);

        foreach ($data['rows'] as $row) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['type'],
                $row['url'],
                $row['word_count'],
                $row['score'],
                $row['seo_title'],
                $row['seo_description'],
                $row['focus_keyphrase'],
                $row['og_title'],
                $row['issues'],
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Handle PDF-style HTML export (printable)
     */
    public function handlePdfExport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'ai-seo-client'));
        }
        check_admin_referer('aiseo_export_pdf');

        $data = $this->getReportData();

        $postsWithIssues = array_filter($data['rows'], fn($r) => $r['issue_count'] > 0);
        $postsOk = $data['total_posts'] - count($postsWithIssues);

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($data['site_name']); ?> â€” SEO Report</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Outfit, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1d2327; padding: 40px; max-width: 1000px; margin: 0 auto; font-size: 14px; }
                h1 { font-size: 28px; margin-bottom: 5px; }
                h2 { font-size: 20px; margin: 30px 0 15px; border-bottom: 2px solid #2271b1; padding-bottom: 5px; }
                .meta { color: #666; margin-bottom: 30px; }
                .summary { display: flex; gap: 20px; margin: 20px 0 30px; }
                .summary-card { flex: 1; background: #f0f0f1; border-radius: 8px; padding: 20px; text-align: center; }
                .summary-card .num { font-size: 32px; font-weight: bold; }
                .summary-card .label { color: #666; font-size: 13px; margin-top: 4px; }
                .score-good { color: #00a32a; }
                .score-ok { color: #dba617; }
                .score-bad { color: #d63638; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 13px; }
                th { background: #f0f0f1; font-weight: 600; }
                tr:nth-child(even) { background: #f9f9f9; }
                .issue-badge { background: #fcf0f1; color: #d63638; padding: 2px 6px; border-radius: 3px; font-size: 11px; display: inline-block; margin: 1px; }
                .ok-badge { background: #edfaef; color: #00a32a; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
                .print-btn { background: #2271b1; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; cursor: pointer; font-size: 14px; margin-bottom: 20px; }
                .print-btn:hover { background: #135e96; }
                @media print { .print-btn { display: none; } body { padding: 20px; } }
            </style>
        </head>
        <body>
            <button class="print-btn" onclick="window.print()"><?php esc_html_e('Print / Save as PDF', 'ai-seo-client'); ?></button>

            <h1><?php echo esc_html($data['site_name']); ?></h1>
            <p class="meta">
                <?php esc_html_e('SEO Audit Report', 'ai-seo-client'); ?> â€” <?php echo esc_html($data['date']); ?><br>
                <?php echo esc_url($data['site_url']); ?>
            </p>

            <div class="summary">
                <div class="summary-card">
                    <div class="num <?php echo $data['avg_score'] >= 80 ? 'score-good' : ($data['avg_score'] >= 50 ? 'score-ok' : 'score-bad'); ?>">
                        <?php echo $data['avg_score']; ?>
                    </div>
                    <div class="label"><?php esc_html_e('Average SEO Score', 'ai-seo-client'); ?></div>
                </div>
                <div class="summary-card">
                    <div class="num"><?php echo $data['total_posts']; ?></div>
                    <div class="label"><?php esc_html_e('Total Pages', 'ai-seo-client'); ?></div>
                </div>
                <div class="summary-card">
                    <div class="num score-good"><?php echo $postsOk; ?></div>
                    <div class="label"><?php esc_html_e('No Issues', 'ai-seo-client'); ?></div>
                </div>
                <div class="summary-card">
                    <div class="num score-bad"><?php echo count($postsWithIssues); ?></div>
                    <div class="label"><?php esc_html_e('Pages with Issues', 'ai-seo-client'); ?></div>
                </div>
            </div>

            <h2><?php esc_html_e('All Pages', 'ai-seo-client'); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Title', 'ai-seo-client'); ?></th>
                        <th style="width:60px;"><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                        <th style="width:60px;"><?php esc_html_e('Words', 'ai-seo-client'); ?></th>
                        <th style="width:60px;"><?php esc_html_e('Score', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Issues', 'ai-seo-client'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row['title']); ?></td>
                        <td><?php echo esc_html($row['type']); ?></td>
                        <td><?php echo $row['word_count']; ?></td>
                        <td>
                            <?php if ($row['score'] !== ''): ?>
                                <strong class="<?php echo $row['score'] >= 80 ? 'score-good' : ($row['score'] >= 50 ? 'score-ok' : 'score-bad'); ?>">
                                    <?php echo $row['score']; ?>
                                </strong>
                            <?php else: ?>
                                <span style="color:#999;">â€”</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['issues']): ?>
                                <?php foreach (explode('; ', $row['issues']) as $issue): ?>
                                    <span class="issue-badge"><?php echo esc_html($issue); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="ok-badge">âœ“ OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top: 30px; color: #999; font-size: 12px;">
                <?php printf(
                    esc_html__('Generated by AI SEO Client v%s on %s', 'ai-seo-client'),
                    AISEO_CLIENT_VERSION,
                    $data['date']
                ); ?>
            </p>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Render the Report export admin page
     */
    public function renderPage(): void
    {
        ?>
        <div class="wrap aiseo-modern">
            <h1><?php esc_html_e('SEO Report Export', 'ai-seo-client'); ?></h1>
            <p class="description"><?php esc_html_e('Export a full SEO audit report for your site. Use CSV for spreadsheet analysis or PDF for client presentations.', 'ai-seo-client'); ?></p>

            <div style="max-width:600px; margin-top:20px;">
                <div class="postbox" style="padding:25px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Export Options', 'ai-seo-client'); ?></h2>
                    <p style="color:#666;"><?php esc_html_e('The report includes all published pages with their SEO score, meta data, and identified issues.', 'ai-seo-client'); ?></p>

                    <div style="display:flex; gap:15px; margin-top:20px;">
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('aiseo_export_csv'); ?>
                            <input type="hidden" name="action" value="aiseo_export_csv">
                            <button type="submit" class="button button-primary" style="padding:8px 24px; height:auto;">
                                <span style="font-size:18px; vertical-align:middle;">ðŸ“Š</span>
                                <?php esc_html_e('Download CSV', 'ai-seo-client'); ?>
                            </button>
                            <p class="description" style="margin-top:5px;"><?php esc_html_e('Open in Excel, Google Sheets, etc.', 'ai-seo-client'); ?></p>
                        </form>

                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" target="_blank">
                            <?php wp_nonce_field('aiseo_export_pdf'); ?>
                            <input type="hidden" name="action" value="aiseo_export_pdf">
                            <button type="submit" class="button" style="padding:8px 24px; height:auto;">
                                <span style="font-size:18px; vertical-align:middle;">ðŸ“„</span>
                                <?php esc_html_e('Open PDF Report', 'ai-seo-client'); ?>
                            </button>
                            <p class="description" style="margin-top:5px;"><?php esc_html_e('Print or Save as PDF from browser.', 'ai-seo-client'); ?></p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

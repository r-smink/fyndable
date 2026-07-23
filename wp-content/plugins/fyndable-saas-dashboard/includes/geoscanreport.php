<?php

namespace SSEOAISaaS;

/**
 * GEO Scan Report Renderer
 *
 * Renders a printable HTML report for a stored scan result.
 */
class GeoScanReport
{
    private GeoScanRepository $repository;

    public function __construct(GeoScanRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Render the report for a given scan id.
     */
    public function render(int $scanId): void
    {
        $scan = $this->repository->getById($scanId);

        if (!$scan) {
            echo '<div class="wrap"><p>' . esc_html__('Scan not found.', 'sseo-ai-saas') . '</p></div>';
            return;
        }

        $data = $scan['result'] ?? [];
        $score = (int)($data['score'] ?? 0);
        $breakdown = $data['breakdown'] ?? [];
        $keywords = $data['keywords_analysis'] ?? [];
        ?>
        <style>
            .sseo-geo-report {
                font-family: Outfit, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                max-width: 900px;
                margin: 20px auto;
                padding: 32px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            }
            .sseo-geo-report h1 {
                margin-top: 0;
                font-size: 28px;
            }
            .sseo-geo-report .report-meta {
                color: #555;
                margin-bottom: 24px;
            }
            .sseo-geo-score {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 140px;
                height: 140px;
                border-radius: 50%;
                background: conic-gradient(#2563eb calc(var(--score) * 1%), #e5e7eb 0);
                font-size: 36px;
                font-weight: 700;
                color: #111827;
                margin-bottom: 24px;
            }
            .sseo-geo-breakdown {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
                gap: 16px;
                margin-bottom: 32px;
            }
            .sseo-geo-metric {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 16px;
                text-align: center;
            }
            .sseo-geo-metric .value {
                font-size: 24px;
                font-weight: 700;
                color: #111827;
            }
            .sseo-geo-metric .label {
                font-size: 13px;
                color: #6b7280;
                margin-top: 4px;
            }
            .sseo-geo-report h2 {
                border-bottom: 2px solid #e5e7eb;
                padding-bottom: 8px;
                margin-top: 32px;
            }
            .sseo-geo-report ul {
                padding-left: 20px;
            }
            .sseo-geo-report li {
                margin-bottom: 8px;
            }
            .sseo-geo-report table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }
            .sseo-geo-report th,
            .sseo-geo-report td {
                border: 1px solid #e5e7eb;
                padding: 10px 12px;
                text-align: left;
                vertical-align: top;
            }
            .sseo-geo-report th {
                background: #f3f4f6;
            }
            .sseo-geo-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
            }
            .sseo-geo-status.yes {
                background: #d1fae5;
                color: #065f46;
            }
            .sseo-geo-status.no {
                background: #fee2e2;
                color: #991b1b;
            }
            .sseo-geo-actions {
                margin-bottom: 24px;
            }
            @media print {
                #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenu,
                #wpfooter, .sseo-geo-actions button {
                    display: none !important;
                }
                #wpcontent, #wpbody, #wpbody-content {
                    margin-left: 0 !important;
                }
                .sseo-geo-report {
                    box-shadow: none;
                    max-width: none;
                }
            }
        </style>

        <div class="wrap sseo-geo-report">
            <div class="sseo-geo-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-geo-scan')); ?>" class="button"><?php esc_html_e('Back to scans', 'sseo-ai-saas'); ?></a>
                <button type="button" class="button button-primary" onclick="window.print();"><?php esc_html_e('Print / Save as PDF', 'sseo-ai-saas'); ?></button>
            </div>

            <h1><?php esc_html_e('GEO Readiness Scan Report', 'sseo-ai-saas'); ?></h1>
            <p class="report-meta">
                <strong><?php esc_html_e('URL:', 'sseo-ai-saas'); ?></strong> <?php echo esc_url($data['url'] ?? ''); ?><br>
                <strong><?php esc_html_e('Date:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($data['scanned_at'] ?? ''); ?><br>
                <strong><?php esc_html_e('Language:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html(strtoupper($data['language'] ?? 'nl')); ?>
            </p>

            <div class="sseo-geo-score" style="--score: <?php echo esc_attr($score); ?>">
                <?php echo esc_html($score); ?>
            </div>

            <h2><?php esc_html_e('Score Breakdown', 'sseo-ai-saas'); ?></h2>
            <div class="sseo-geo-breakdown">
                <?php
                $metricLabels = [
                    'direct_answer'       => __('Direct Answer', 'sseo-ai-saas'),
                    'structure'           => __('Structure', 'sseo-ai-saas'),
                    'schema_markup'       => __('Schema Markup', 'sseo-ai-saas'),
                    'entities'            => __('Entities', 'sseo-ai-saas'),
                    'citation_worthiness' => __('Citation Worthiness', 'sseo-ai-saas'),
                ];
                foreach ($metricLabels as $key => $label) :
                    $value = (int)($breakdown[$key] ?? 0);
                ?>
                <div class="sseo-geo-metric">
                    <div class="value"><?php echo esc_html($value); ?></div>
                    <div class="label"><?php echo esc_html($label); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <h2><?php esc_html_e('Findings', 'sseo-ai-saas'); ?></h2>
            <ul>
                <?php foreach ((array)($data['findings'] ?? []) as $finding) : ?>
                    <li><?php echo esc_html($finding); ?></li>
                <?php endforeach; ?>
            </ul>

            <h2><?php esc_html_e('Recommendations', 'sseo-ai-saas'); ?></h2>
            <ul>
                <?php foreach ((array)($data['recommendations'] ?? []) as $rec) : ?>
                    <li><?php echo esc_html($rec); ?></li>
                <?php endforeach; ?>
            </ul>

            <h2><?php esc_html_e('Keyword Analysis', 'sseo-ai-saas'); ?></h2>
            <table>
                <thead>
                    <tr>
                        <th><?php esc_html_e('Keyword', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('AI Overview', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Target Cited', 'sseo-ai-saas'); ?></th>
                        <th><?php esc_html_e('Competitor Citations', 'sseo-ai-saas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keywords as $kw) : ?>
                        <tr>
                            <td><?php echo esc_html($kw['keyword']); ?></td>
                            <td>
                                <?php if ($kw['has_ai_overview']) : ?>
                                    <span class="sseo-geo-status yes"><?php esc_html_e('Yes', 'sseo-ai-saas'); ?></span>
                                <?php else : ?>
                                    <span class="sseo-geo-status no"><?php esc_html_e('No', 'sseo-ai-saas'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($kw['target_cited']) : ?>
                                    <span class="sseo-geo-status yes"><?php esc_html_e('Yes', 'sseo-ai-saas'); ?></span>
                                <?php else : ?>
                                    <span class="sseo-geo-status no"><?php esc_html_e('No', 'sseo-ai-saas'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $competitors = (array)($kw['competitor_citations'] ?? []);
                                if ($competitors) :
                                    echo '<ul style="margin:0;padding-left:16px;">';
                                    foreach (array_slice($competitors, 0, 3) as $c) {
                                        echo '<li>' . esc_html($c['host'] ?? '') . '</li>';
                                    }
                                    echo '</ul>';
                                else :
                                    esc_html_e('No competitors found', 'sseo-ai-saas');
                                endif;
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

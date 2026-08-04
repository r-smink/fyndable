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
            .sseo-geo-report-print-header { display: none; }
            @media print {
                body { background: #fff !important; }
                #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenu, #wpfooter, .sseo-geo-actions { display: none !important; }
                #wpcontent, #wpbody, #wpbody-content { margin-left: 0 !important; padding-left: 0 !important; }
                .sseo-geo-report { box-shadow: none; max-width: none; margin: 0; border-radius: 0; }
                .sseo-geo-report-print-header { display: block; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #379fd3; }
                .sseo-geo-report-print-header img { max-width: 180px; height: auto; }
            }
        </style>

        <div class="wrap sseo-geo-report">
            <div class="sseo-geo-report-print-header">
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/logo_long_black.png'); ?>" alt="Fyndable">
            </div>

            <div class="sseo-geo-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-geo-scan')); ?>" class="button button-secondary"><?php esc_html_e('Back to scans', 'sseo-ai-saas'); ?></a>
                <button type="button" class="button button-primary" onclick="window.print();"><?php esc_html_e('Print / Save as PDF', 'sseo-ai-saas'); ?></button>
                <span class="sseo-geo-print-hint"><?php esc_html_e('Kies in het printvenster "Opslaan als PDF".', 'sseo-ai-saas'); ?></span>
            </div>

            <div class="sseo-geo-report-card sseo-geo-report-hero">
                <h1><?php esc_html_e('GEO Readiness Scan Report', 'sseo-ai-saas'); ?></h1>
                <p class="report-meta">
                    <strong><?php esc_html_e('URL:', 'sseo-ai-saas'); ?></strong> <?php echo esc_url($data['url'] ?? ''); ?><br>
                    <strong><?php esc_html_e('Date:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($data['scanned_at'] ?? ''); ?><br>
                    <strong><?php esc_html_e('Language:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html(strtoupper($data['language'] ?? 'nl')); ?>
                </p>
            </div>

            <div class="sseo-geo-report-grid sseo-geo-report-grid-2">
                <div class="sseo-geo-report-card sseo-geo-score-card">
                    <h2><?php esc_html_e('Totaalscore', 'sseo-ai-saas'); ?></h2>
                    <div class="sseo-geo-score" style="--score: <?php echo esc_attr($score); ?>" data-score="<?php echo esc_attr($score); ?>">
                        <span class="score-value"><?php echo esc_html($score); ?></span>
                        <span class="score-label">/100</span>
                    </div>
                    <p class="sseo-geo-score-caption"><?php esc_html_e('Hoe geschikt is deze pagina om als bron te worden geciteerd door AI-zoekmachines?', 'sseo-ai-saas'); ?></p>
                </div>

                <div class="sseo-geo-report-card sseo-geo-summary-card">
                    <h2><?php esc_html_e('Quickscan', 'sseo-ai-saas'); ?></h2>
                    <?php
                    $strengths = (array)($data['strengths'] ?? []);
                    $weaknesses = (array)($data['weaknesses'] ?? []);
                    ?>
                    <?php if ($strengths) : ?>
                        <h3><?php esc_html_e('Sterke punten', 'sseo-ai-saas'); ?></h3>
                        <ul class="sseo-geo-list sseo-geo-list--positive">
                            <?php foreach (array_slice($strengths, 0, 5) as $s) : ?>
                                <li><?php echo esc_html($s); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($weaknesses) : ?>
                        <h3><?php esc_html_e('Aandachtspunten', 'sseo-ai-saas'); ?></h3>
                        <ul class="sseo-geo-list sseo-geo-list--negative">
                            <?php foreach (array_slice($weaknesses, 0, 5) as $w) : ?>
                                <li><?php echo esc_html($w); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sseo-geo-report-card">
                <h2><?php esc_html_e('Score Breakdown', 'sseo-ai-saas'); ?></h2>
                <div class="sseo-geo-breakdown">
                    <?php
                    $metricLabels = [
                        'direct_answer'       => __('Direct Answer', 'sseo-ai-saas'),
                        'structure'           => __('Structure', 'sseo-ai-saas'),
                        'schema_markup'       => __('Schema Markup', 'sseo-ai-saas'),
                        'entities'            => __('Entities', 'sseo-ai-saas'),
                        'citation_worthiness' => __('Citation Worthiness', 'sseo-ai-saas'),
                        'readability'         => __('Readability', 'sseo-ai-saas'),
                        'eeat'                => __('E-E-A-T', 'sseo-ai-saas'),
                        'content_freshness'   => __('Content Freshness', 'sseo-ai-saas'),
                        'mobile_friendly'     => __('Mobile Friendly', 'sseo-ai-saas'),
                        'internal_linking'    => __('Internal Linking', 'sseo-ai-saas'),
                        'page_metadata'       => __('Page Metadata', 'sseo-ai-saas'),
                        'entity_coverage'     => __('Entity Coverage', 'sseo-ai-saas'),
                        'competitive_gap'     => __('Competitive Gap', 'sseo-ai-saas'),
                    ];
                    foreach ($metricLabels as $key => $label) :
                        $value = (int)($breakdown[$key] ?? 0);
                    ?>
                    <div class="sseo-geo-metric" data-value="<?php echo esc_attr($value); ?>">
                        <div class="value"><?php echo esc_html($value); ?></div>
                        <div class="label"><?php echo esc_html($label); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sseo-geo-report-grid sseo-geo-report-grid-2">
                <div class="sseo-geo-report-card">
                    <h2><?php esc_html_e('Findings', 'sseo-ai-saas'); ?></h2>
                    <ul class="sseo-geo-list sseo-geo-list--findings">
                        <?php foreach ((array)($data['findings'] ?? []) as $finding) : ?>
                            <li><?php echo esc_html($finding); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="sseo-geo-report-card">
                    <h2><?php esc_html_e('Recommendations', 'sseo-ai-saas'); ?></h2>
                    <ul class="sseo-geo-list sseo-geo-list--recommendations">
                        <?php foreach ((array)($data['recommendations'] ?? []) as $rec) : ?>
                            <li><?php echo esc_html($rec); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php $priority = (array)($data['priority_ranked_recommendations'] ?? []); ?>
            <?php if ($priority) : ?>
            <div class="sseo-geo-report-card sseo-geo-report-card--priority">
                <h2><?php esc_html_e('Prioriteitacties', 'sseo-ai-saas'); ?></h2>
                <ol class="sseo-geo-priority-list">
                    <?php foreach ($priority as $i => $p) : ?>
                        <li>
                            <span class="sseo-geo-priority-rank"><?php echo esc_html($i + 1); ?></span>
                            <?php echo esc_html($p); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

            <div class="sseo-geo-report-card">
                <h2><?php esc_html_e('Keyword Analysis', 'sseo-ai-saas'); ?></h2>
                <div class="sseo-geo-keywords-table-wrap">
                    <table class="sseo-geo-keywords-table">
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
                                            echo '<ul class="sseo-geo-competitor-list">';
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
            </div>
        </div>
        <?php
    }
}

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
            echo '<div class="wrap"><p>' . esc_html__('Scan niet gevonden.', 'sseo-ai-saas') . '</p></div>';
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
            .sseo-geo-keywords-legend { color: #6b7280; font-size: 14px; line-height: 1.5; margin: -8px 0 18px 0; }
        </style>

        <div class="wrap sseo-geo-report">
            <div class="sseo-geo-report-print-header">
                <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/logo_long_black.png'); ?>" alt="Fyndable">
            </div>

            <div class="sseo-geo-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=sseo-ai-geo-scan')); ?>" class="button button-secondary"><?php esc_html_e('Terug naar scans', 'sseo-ai-saas'); ?></a>
                <button type="button" class="button button-primary" onclick="window.print();"><?php esc_html_e('Printen / Opslaan als PDF', 'sseo-ai-saas'); ?></button>
                <span class="sseo-geo-print-hint"><?php esc_html_e('Kies in het printvenster "Opslaan als PDF".', 'sseo-ai-saas'); ?></span>
            </div>

            <div class="sseo-geo-report-card sseo-geo-report-hero">
                <h1><?php esc_html_e('GEO-scanrapport', 'sseo-ai-saas'); ?></h1>
                <p class="report-meta">
                    <strong><?php esc_html_e('Webadres:', 'sseo-ai-saas'); ?></strong> <?php echo esc_url($data['url'] ?? ''); ?><br>
                    <strong><?php esc_html_e('Datum:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html($data['scanned_at'] ?? ''); ?><br>
                    <strong><?php esc_html_e('Taal:', 'sseo-ai-saas'); ?></strong> <?php echo esc_html(strtoupper($data['language'] ?? 'nl')); ?>
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
                <h2><?php esc_html_e('Scoreverdeling', 'sseo-ai-saas'); ?></h2>
                <div class="sseo-geo-breakdown">
                    <?php
                    $metricLabels = [
                        'direct_answer'       => __('Direct antwoord', 'sseo-ai-saas'),
                        'structure'           => __('Structuur', 'sseo-ai-saas'),
                        'schema_markup'       => __('Schema markup', 'sseo-ai-saas'),
                        'entities'            => __('Entiteiten', 'sseo-ai-saas'),
                        'citation_worthiness' => __('Citeerwaardigheid', 'sseo-ai-saas'),
                        'readability'         => __('Leesbaarheid', 'sseo-ai-saas'),
                        'eeat'                => __('E-E-A-T', 'sseo-ai-saas'),
                        'content_freshness'   => __('Actualiteit', 'sseo-ai-saas'),
                        'mobile_friendly'     => __('Mobiel vriendelijk', 'sseo-ai-saas'),
                        'internal_linking'    => __('Interne links', 'sseo-ai-saas'),
                        'page_metadata'       => __('Metadata', 'sseo-ai-saas'),
                        'entity_coverage'     => __('Entiteitsdekking', 'sseo-ai-saas'),
                        'competitive_gap'     => __('Concurrentieverschil', 'sseo-ai-saas'),
                    ];
                    $metricTooltips = [
                        'direct_answer'       => __("Geeft je pagina meteen een duidelijk antwoord op de vraag van de bezoeker? AI-zoekmachines geven de voorkeur aan pagina's die de vraag direct beantwoorden.", 'sseo-ai-saas'),
                        'structure'           => __("Is de tekst overzichtelijk opgebouwd met duidelijke koppen, paragrafen en lijsten?", 'sseo-ai-saas'),
                        'schema_markup'       => __("Gebruik je gestructureerde gegevens (schema) waardoor zoekmachines de inhoud beter begrijpen?", 'sseo-ai-saas'),
                        'entities'            => __("Noemt je tekst belangrijke personen, plaatsen, merken of begrippen die relevant zijn voor het onderwerp?", 'sseo-ai-saas'),
                        'citation_worthiness' => __("Is je pagina een betrouwbare bron die andere websites of AI-systemen zouden willen citeren?", 'sseo-ai-saas'),
                        'readability'         => __("Is de tekst makkelijk te lezen en te begrijpen voor een breed publiek?", 'sseo-ai-saas'),
                        'eeat'                => __("Laat je pagina zien dat je expertise, autoriteit en betrouwbaarheid hebt over dit onderwerp. Zoekmachines vertrouwen content van betrouwbare bronnen meer.", 'sseo-ai-saas'),
                        'content_freshness'   => __("Is de informatie recent en up-to-date? Verse content scoort vaak beter.", 'sseo-ai-saas'),
                        'mobile_friendly'     => __("Is de pagina goed te gebruiken op een mobiele telefoon?", 'sseo-ai-saas'),
                        'internal_linking'    => __("Linkt de pagina naar andere relevante pagina's binnen je eigen website?", 'sseo-ai-saas'),
                        'page_metadata'       => __("Zijn titel, omschrijving en andere meta-informatie aanwezig en relevant?", 'sseo-ai-saas'),
                        'entity_coverage'     => __("Hoe goed dekt je tekst de belangrijkste entiteiten en onderwerpen rond het zoekwoord af?", 'sseo-ai-saas'),
                        'competitive_gap'     => __("In welke opzichten komt jouw inhoud tekort ten opzichte van concurrenten die wel geciteerd worden?", 'sseo-ai-saas'),
                    ];
                    $importantMetrics = [
                        'direct_answer',
                        'eeat',
                        'schema_markup',
                        'entities',
                        'citation_worthiness',
                        'content_freshness',
                        'structure',
                        'readability',
                    ];
                    foreach ($importantMetrics as $key) :
                        $value = (int)($breakdown[$key] ?? 0);
                        $label = $metricLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                        $tooltip = $metricTooltips[$key] ?? '';
                    ?>
                    <div class="sseo-geo-metric" data-value="<?php echo esc_attr($value); ?>">
                        <div class="value"><?php echo esc_html($value); ?></div>
                        <div class="label"><?php echo esc_html($label); ?></div>
                        <div class="sseo-geo-metric-tooltip"><?php echo esc_html($tooltip); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sseo-geo-report-grid sseo-geo-report-grid-2">
                <div class="sseo-geo-report-card">
                    <h2><?php esc_html_e('Bevindingen', 'sseo-ai-saas'); ?></h2>
                    <ul class="sseo-geo-list sseo-geo-list--findings">
                        <?php foreach ((array)($data['findings'] ?? []) as $finding) : ?>
                            <li><?php echo esc_html($finding); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="sseo-geo-report-card">
                    <h2><?php esc_html_e('Aanbevelingen', 'sseo-ai-saas'); ?></h2>
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
                <h2><?php esc_html_e('Prioriteitsacties', 'sseo-ai-saas'); ?></h2>
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
                <h2><?php esc_html_e('Zoekwoordanalyse', 'sseo-ai-saas'); ?></h2>
                <p class="sseo-geo-keywords-legend"><?php esc_html_e('Deze tabel laat per zoekwoord zien of er een AI-overzicht beschikbaar is, of jouw bedrijf daarin wordt vermeld en welke concurrenten wél worden genoemd. Let op: Ja bij AI-overzicht betekent dat er een AI-samenvatting bestaat; Ja bij Jouw bedrijf vermeld betekent dat jouw website daadwerkelijk in die samenvatting staat.', 'sseo-ai-saas'); ?></p>
                <div class="sseo-geo-keywords-table-wrap">
                    <table class="sseo-geo-keywords-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Zoekwoord', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('AI-overzicht', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Jouw bedrijf vermeld', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Concurrenten vermeld', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($keywords as $kw) : ?>
                                <tr>
                                    <td><?php echo esc_html($kw['keyword']); ?></td>
                                    <td>
                                        <?php if ($kw['has_ai_overview']) : ?>
                                            <span class="sseo-geo-status yes"><?php esc_html_e('Ja', 'sseo-ai-saas'); ?></span>
                                        <?php else : ?>
                                            <span class="sseo-geo-status no"><?php esc_html_e('Nee', 'sseo-ai-saas'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kw['target_cited']) : ?>
                                            <span class="sseo-geo-status yes"><?php esc_html_e('Ja', 'sseo-ai-saas'); ?></span>
                                        <?php else : ?>
                                            <span class="sseo-geo-status no"><?php esc_html_e('Nee', 'sseo-ai-saas'); ?></span>
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
                                            esc_html_e('Geen concurrenten gevonden', 'sseo-ai-saas');
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

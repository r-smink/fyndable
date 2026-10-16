<?php

namespace SSEOAISaaS;

use Dompdf\Dompdf;
use Dompdf\Options;

class GeoScanPdf
{
    private GeoScanRepository $repository;

    public function __construct(GeoScanRepository $repository)
    {
        $this->repository = $repository;
    }

    public function render(int $scanId, string $cover = 'light'): array|\WP_Error
    {
        $scan = $this->repository->getById($scanId);
        if (!$scan || ($scan['status'] ?? '') !== 'completed' || empty($scan['result'])) {
            return new \WP_Error('geo_pdf_scan_unavailable', __('Het voltooide scanrapport is niet gevonden.', 'sseo-ai-saas'));
        }
        if (!class_exists(Dompdf::class)) {
            return new \WP_Error('geo_pdf_dependency_missing', __('De PDF-renderer is niet beschikbaar.', 'sseo-ai-saas'));
        }

        $cover = $cover === 'dark' ? 'dark' : 'light';
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', SSEO_AI_SAAS_PLUGIN_DIR);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->buildHtml($scan, $cover), 'UTF-8');
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pageLabel = (($scan['result']['language'] ?? 'nl') === 'en') ? 'Page' : 'Pagina';
        $dompdf->getCanvas()->page_script(static function ($pageNumber, $pageCount, $canvas) use ($font, $pageLabel): void {
            if ($pageNumber > 1) {
                $text = $pageLabel . ' ' . ($pageNumber - 1) . ' / ' . ($pageCount - 1);
                $width = $canvas->get_text_width($text, $font, 7);
                $canvas->text(548 - $width, 810, $text, $font, 7, [0.48, 0.51, 0.57]);
            }
        });

        $data = $scan['result'];
        $host = $this->host((string)($data['url'] ?? $scan['url'] ?? 'website'));
        $date = !empty($data['scanned_at']) ? strtotime($data['scanned_at']) : time();
        $filename = sanitize_file_name(sprintf('fyndable-geo-scan-%s-%s-%s.pdf', $host, wp_date('Y-m-d', $date), $cover));

        return ['bytes' => $dompdf->output(), 'filename' => $filename];
    }

    private function buildHtml(array $scan, string $cover): string
    {
        $data = (array)$scan['result'];
        $language = ($data['language'] ?? 'nl') === 'en' ? 'en' : 'nl';
        $t = $this->labels($language);
        $score = max(0, min(100, (int)($data['score'] ?? 0)));
        $url = (string)($data['url'] ?? $scan['url'] ?? '');
        $host = $this->host($url);
        $scanDate = !empty($data['scanned_at']) ? strtotime($data['scanned_at']) : time();
        $date = wp_date($language === 'en' ? 'F j, Y' : 'j F Y', $scanDate);
        $headline = trim((string)($data['management_headline'] ?? '')) ?: $this->scoreHeadline($score, $language);
        $summary = trim((string)($data['executive_summary'] ?? '')) ?: $this->fallbackSummary($score, $language);
        $breakdown = (array)($data['breakdown'] ?? []);
        $platforms = (array)($data['platform_visibility'] ?? []);
        $benchmark = (array)($data['competitive_benchmark'] ?? []);
        $actions = $this->actions($data, $language);
        $logo = $this->logoDataUri($cover === 'dark');
        $headerLogo = $this->logoDataUri(false);
        $fontRegular = $this->fontDataUri(false);
        $fontBold = $this->fontDataUri(true);
        $scoreRing = $this->scoreRingDataUri($score, $cover === 'dark');
        $coverClass = $cover === 'dark' ? 'cover dark' : 'cover light';
        $scoreLabel = $score >= 70 ? $t['good'] : ($score >= 40 ? $t['needs_work'] : $t['weak']);
        $visiblePlatforms = count(array_filter($platforms, static fn(array $platform): bool => in_array($platform['status'] ?? '', ['visible', 'sometimes'], true)));
        $platformCount = count(array_filter($platforms, static fn(array $platform): bool => ($platform['status'] ?? '') !== 'unavailable'));
        $metricLabels = $this->metricLabels($language);
        $summaryMetrics = array_intersect_key($metricLabels, array_flip(['structure', 'schema_markup', 'direct_answer', 'citation_worthiness']));
        $headerHtml = '<div class="report-header">'
            . ($headerLogo !== '' ? '<img src="' . $headerLogo . '" alt="Fyndable Smart SEO">' : '')
            . '<span>' . $this->e($t['report'] . ' · ' . $host) . '</span></div>';
        $footerHtml = '<div class="report-footer">Fyndable GEO Scan · ' . $this->e($host) . '</div>';

        ob_start();
        ?>
<!doctype html>
<html lang="<?php echo $this->e($language); ?>">
<head>
<meta charset="utf-8">
<style>
@page { margin: 12mm 14mm 12mm; }
@font-face { font-family: "Outfit"; src: url("<?php echo $fontRegular; ?>") format("truetype"); font-weight: normal; }
@font-face { font-family: "Outfit"; src: url("<?php echo $fontBold; ?>") format("truetype"); font-weight: bold; }
* { box-sizing: border-box; }
body { margin: 0; color: #343434; font-family: "Outfit", "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.45; }
a { color: inherit; text-decoration: none; }
.cover { height: 250mm; padding: 0; position: relative; overflow: hidden; border-top: 1.4mm solid #8340b9; }
.cover.light { background: #fff; color: #343434; }
.cover.dark { background: #16172b; color: #fff; }
.cover:after { content: ""; position: absolute; right: -38mm; bottom: -50mm; width: 115mm; height: 115mm; border-radius: 58mm; background: #efe4fa; }
.cover.dark:after { background: #292446; }
.cover .eyebrow, .section-kicker { color: #a23bbc; font-size: 8pt; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
.cover.dark .eyebrow { color: #c57ce1; }
.logo { width: 60mm; height: auto; }
.cover-logo { position: absolute; top: 14mm; left: 0; }
.cover-copy { position: absolute; left: 0; top: 116mm; width: 145mm; }
.cover h1 { font-size: 31pt; line-height: .98; margin: 6mm 0 5mm; font-weight: bold; letter-spacing: -.8px; }
.cover h1 .gradient-word { display: block; color: #7546c9; font-size: 30pt; }
.cover .subtitle { width: 145mm; font-size: 11pt; color: #4e596c; line-height: 1.6; }
.cover.dark .subtitle { color: #d0d2de; }
.cover-bottom-card { position: absolute; left: 0; right: 0; bottom: 7mm; height: 57mm; border-top: 1.2mm solid #8842bd; border-radius: 4mm; background: #f7f7f8; z-index: 1; }
.cover.dark .cover-bottom-card { background: #22243a; }
.cover-meta { position: absolute; left: 8mm; bottom: 15mm; width: 72mm; z-index: 2; }
.meta-label { color: #748096; font-size: 6.8pt; font-weight: bold; letter-spacing: .65px; text-transform: uppercase; }
.cover.dark .meta-label { color: #aeb3c9; }
.meta-value { font-size: 13pt; font-weight: bold; margin: 1mm 0 5mm; }
.cover-score { position: absolute; right: 8mm; bottom: 11mm; width: 50mm; text-align: center; z-index: 2; }
.cover-score-ring { width: 42mm; height: 42mm; margin: 0 auto 1mm; }
.score-status { display: inline-block; padding: 1.5mm 3mm; border: .3mm solid #f0c76b; border-radius: 8mm; background: #fff5dc; color: #b56208; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
.cover.dark .score-status { background: #322849; border-color: #8252b7; color: #ead7fa; }
.report-page { height: 258mm; position: relative; page-break-before: always; overflow: hidden; }
.appendix-page { page-break-before: always; }
.report-header { height: 13mm; border-bottom: .55mm solid #8842bd; margin-bottom: 8mm; }
.report-header img { width: 45mm; height: auto; }
.report-header span { float: right; padding-top: 2mm; color: #8992a4; font-size: 7pt; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
.report-footer { position: absolute; bottom: 0; left: 0; right: 0; border-top: .3mm solid #dfe3ea; color: #8d95a5; font-size: 7pt; padding-top: 2.5mm; }
h2 { font-size: 22pt; line-height: 1.12; margin: 3mm 0 4mm; font-weight: bold; letter-spacing: -.4px; }
h3 { font-size: 14pt; margin: 0 0 3mm; font-weight: bold; }
p { margin: 0 0 4mm; }
.lead { font-size: 10.5pt; color: #515a6c; line-height: 1.55; }
.stats { width: 100%; border-collapse: separate; border-spacing: 3mm; margin: 7mm -3mm 6mm; }
.stats td { width: 33.33%; padding: 5mm; border-top: 1.2mm solid #8c42bd; background: #f7f7f8; }
.stats strong { display: block; color: #9b39b5; font-size: 24pt; line-height: 1.1; }
.stats span { color: #596274; font-size: 8pt; }
.metric-table, .benchmark-table { width: 100%; border-collapse: collapse; }
.metric-table td { padding: 2.4mm 0; vertical-align: middle; }
.metric-icon { width: 13mm; height: 10mm; color: #fff; background: #8547bf; text-align: center; border-radius: 3mm; }
.metric-name { width: 35%; padding-left: 4mm !important; font-weight: bold; }
.bar { width: 100%; height: 2.2mm; background: #e3e6eb; border-radius: 2mm; }
.bar-fill { height: 2.2mm; background: #8742bd; border-radius: 2mm; }
.metric-score { width: 24%; text-align: right; color: #9299a8; font-size: 8pt; }
.metric-score strong { color: #343434; font-size: 11pt; }
.platform-grid { width: 100%; border-collapse: separate; border-spacing: 4mm; margin: 3mm -4mm 6mm; table-layout: fixed; }
.platform-grid td { width: 50%; vertical-align: top; border: .5mm solid #e0e3e8; padding: 5mm; border-radius: 3mm; }
.platform-name { font-size: 12pt; font-weight: bold; }
.badge { float: right; border-radius: 10mm; padding: 1.2mm 3mm; font-size: 6.5pt; font-weight: bold; text-transform: uppercase; }
.badge.visible { background: #ccf5e2; color: #087b55; }
.badge.sometimes { background: #fff0bf; color: #aa6200; }
.badge.not_mentioned { background: #ffdfe1; color: #bd202b; }
.badge.unavailable { background: #eceef3; color: #687083; }
.platform-copy { clear: both; padding-top: 4mm; color: #626a7b; font-size: 8.5pt; }
.benchmark-table { padding: 4mm 6mm; background: #f8f8f9; }
.benchmark-table td { padding: 2.5mm 0; }
.benchmark-label { width: 30%; font-weight: bold; }
.benchmark-bar { width: 100%; background: #e2e5ea; height: 4mm; border-radius: 3mm; }
.benchmark-fill { height: 4mm; background: #8742bd; border-radius: 3mm; }
.benchmark-value { width: 10%; text-align: right; font-weight: bold; }
.note { padding: 3mm 6mm; background: #f8f8f9; color: #687083; font-size: 8pt; }
.action { width: 100%; page-break-inside: avoid; border-top: .3mm solid #e1e4ec; border-collapse: collapse; }
.action td { padding: 4mm 0; vertical-align: top; }
.action-priority { width: 25mm; }
.priority-chip { display: inline-block; min-width: 18mm; padding: 1.2mm 2.5mm; border-radius: 8mm; text-align: center; font-size: 7pt; font-style: normal; font-weight: bold; text-transform: uppercase; }
.priority-chip.high { color: #b71925; background: #ffdfe1; }
.priority-chip.medium { color: #a85d00; background: #fff0bf; }
.priority-chip.low { color: #087b55; background: #ccf5e2; }
.action-title { font-weight: bold; font-size: 10.5pt; line-height: 1.3; }
.action-description { margin: 1mm 0 2mm; color: #657083; font-size: 8.5pt; }
.action-meta span, .action-meta em { display: inline-block; margin-right: 2mm; padding: 1mm 2.5mm; border-radius: 7mm; background: #f3e8f8; color: #a13ab8; font-size: 7pt; font-style: normal; }
.action-meta em { background: #f0f2f5; color: #657083; }
.steps { width: 100%; border-collapse: separate; border-spacing: 0 4mm; margin-top: 4mm; }
.steps td { vertical-align: middle; padding: 5mm; background: #f8f8f9; }
.step-number { width: 15mm; color: #fff; background: #8644bd !important; text-align: center; font-size: 16pt; font-weight: bold; }
.cta { margin-top: 7mm; padding: 8mm 9mm; color: #fff; background: #191b31; border-top: 1.2mm solid #8742bd; }
.cta-table { width: 100%; }
.cta-table td { width: 55%; vertical-align: middle; }
.cta h3 { color: #fff; font-size: 18pt; line-height: 1.2; }
.cta-contact { width: 45% !important; text-align: center; color: #d7dae4; font-size: 8pt; }
.cta-contact strong { display: block; margin-bottom: 3mm; padding: 3mm; border-radius: 2mm; background: #8b43bd; color: #fff; font-size: 10pt; }
.appendix-block { margin: 6mm 0; }
.appendix-block h3 { padding-bottom: 2mm; border-bottom: .3mm solid #e1e4ec; }
ul { margin: 0; padding-left: 5mm; }
li { margin-bottom: 2mm; }
.keyword-table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
.keyword-table th { background: #171a32; color: #fff; text-align: left; padding: 2.5mm; }
.keyword-table td { padding: 2.5mm; border-bottom: .3mm solid #e7e9ef; vertical-align: top; }
.model-chips span { display: inline-block; margin: 0 2mm 2mm 0; padding: 1.5mm 2.5mm; background: #f0ebf8; border-radius: 8mm; font-size: 7pt; }
</style>
</head>
<body>
<section class="<?php echo $coverClass; ?>">
    <?php if ($logo !== '') : ?><img class="logo cover-logo" src="<?php echo $logo; ?>" alt="Fyndable Smart SEO"><?php endif; ?>
    <div class="cover-copy">
        <div class="eyebrow">Generative Engine Optimization</div>
        <h1>GEO Scan <span class="gradient-word"><?php echo $language === 'en' ? 'Report' : 'Rapport'; ?></span></h1>
        <div class="subtitle"><?php echo $this->e($t['subtitle']); ?></div>
    </div>
    <div class="cover-bottom-card"></div>
    <div class="cover-meta"><div class="meta-label"><?php echo $this->e($t['website']); ?></div><div class="meta-value"><?php echo $this->e($host); ?></div><div class="meta-label"><?php echo $this->e($t['scan_date']); ?></div><div class="meta-value"><?php echo $this->e($date); ?></div></div>
    <div class="cover-score"><img class="cover-score-ring" src="<?php echo $scoreRing; ?>" alt="<?php echo $score; ?>/100"><div class="score-status"><?php echo $this->e($t['geo_score'] . ' · ' . $scoreLabel); ?></div></div>
</section>
<section class="report-page">
    <?php echo $headerHtml; ?>
    <div class="section-kicker"><?php echo $this->e($t['management']); ?></div>
    <h2><?php echo $this->e($headline); ?></h2>
    <p class="lead"><?php echo $this->e($summary); ?></p>
    <table class="stats"><tr>
        <td><strong><?php echo $score; ?></strong><span><?php echo $this->e($t['total_score']); ?></span></td>
        <td><strong><?php echo $visiblePlatforms; ?>/<?php echo max(1, $platformCount); ?></strong><span><?php echo $this->e($t['platforms_visible']); ?></span></td>
        <td><strong><?php echo count($actions); ?></strong><span><?php echo $this->e($t['actions_found']); ?></span></td>
    </tr></table>
    <h3><?php echo $this->e($t['category_scores']); ?></h3>
    <table class="metric-table"><?php foreach ($summaryMetrics as $key => $label) : $value = max(0, min(100, (int)($breakdown[$key] ?? 0))); ?><tr><td class="metric-icon">●</td><td class="metric-name"><?php echo $this->e($label); ?></td><td><div class="bar"><div class="bar-fill" style="width:<?php echo $value; ?>%"></div></div></td><td class="metric-score"><?php echo $this->e($this->scoreStatus($value, $language)); ?> <strong><?php echo $value; ?></strong>/100</td></tr><?php endforeach; ?></table>
    <?php echo $footerHtml; ?>
</section>
<section class="report-page">
    <?php echo $headerHtml; ?>
    <div class="section-kicker"><?php echo $this->e($t['platform_visibility']); ?></div>
    <h2><?php echo $this->e($t['where_visible']); ?></h2>
    <p class="lead"><?php echo $this->e($t['platform_intro']); ?></p>
    <table class="platform-grid">
        <?php foreach (array_chunk($this->platformLabels($language), 2, true) as $row) : ?><tr>
        <?php foreach ($row as $key => $label) : $platform = (array)($platforms[$key] ?? []); $status = (string)($platform['status'] ?? 'unavailable'); ?>
        <td><span class="platform-name"><?php echo $this->e($label); ?></span><span class="badge <?php echo $this->e($status); ?>"><?php echo $this->e($t['status_' . $status]); ?></span><div class="platform-copy"><?php echo $this->e($this->platformCopy($platform, $language)); ?></div></td>
        <?php endforeach; ?></tr><?php endforeach; ?>
    </table>
    <h3><?php echo $this->e($t['benchmark']); ?></h3>
    <table class="benchmark-table">
        <?php foreach ([['own_visibility', $t['your_site']], ['average_competitor', $t['average_competitor']], ['best_competitor', $t['best_competitor']]] as [$key, $label]) : $value = isset($benchmark[$key]) ? (int)$benchmark[$key] : 0; ?>
        <tr><td class="benchmark-label"><?php echo $this->e($label); ?></td><td><div class="benchmark-bar"><div class="benchmark-fill" style="width:<?php echo max(0, min(100, $value)); ?>%"></div></div></td><td class="benchmark-value"><?php echo $value; ?></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="note"><?php echo $this->e(sprintf($t['snapshot_note'], (int)($benchmark['tests'] ?? 0))); ?></div>
    <?php echo $footerHtml; ?>
</section>
<section class="report-page">
    <?php echo $headerHtml; ?>
    <div class="section-kicker"><?php echo $this->e($t['actions']); ?></div>
    <h2><?php echo $this->e(sprintf($t['actions_heading'], count($actions))); ?></h2>
    <p class="lead"><?php echo $this->e($t['actions_intro']); ?></p>
    <?php foreach ($actions as $action) : ?><table class="action"><tr><td class="action-priority"><span class="priority-chip <?php echo $this->e($action['priority']); ?>"><?php echo $this->e($t['priority_' . $action['priority']]); ?></span></td><td><div class="action-title"><?php echo $this->e($action['title']); ?></div><?php if ($action['description'] !== '') : ?><div class="action-description"><?php echo $this->e($action['description']); ?></div><?php endif; ?><div class="action-meta"><span><?php echo $this->e($action['category']); ?></span> <em><?php echo $this->e($t['effort'] . ': ' . $t['effort_' . $action['effort']]); ?></em></div></td></tr></table><?php endforeach; ?>
    <?php echo $footerHtml; ?>
</section>
<section class="report-page">
    <?php echo $headerHtml; ?>
    <div class="section-kicker"><?php echo $this->e($t['next_steps']); ?></div>
    <h2><?php echo $this->e($t['from_report']); ?></h2>
    <p class="lead"><?php echo $this->e($t['steps_intro']); ?></p>
    <table class="steps"><?php foreach ($t['steps'] as $index => $step) : ?><tr><td class="step-number"><?php echo $index + 1; ?></td><td><strong><?php echo $this->e($step[0]); ?></strong><br><?php echo $this->e($step[1]); ?></td></tr><?php endforeach; ?></table>
    <div class="cta"><table class="cta-table"><tr><td><h3><?php echo $this->e($t['cta_title']); ?></h3><?php echo $this->e($t['cta_copy']); ?></td><td class="cta-contact"><strong><?php echo $this->e($t['contact']); ?></strong><br>fyndable.ai · hallo@fyndable.ai</td></tr></table></div>
    <?php echo $footerHtml; ?>
</section>
<section class="appendix-page">
    <?php echo $headerHtml; ?>
    <div class="section-kicker"><?php echo $this->e($t['appendix']); ?></div>
    <h2><?php echo $this->e($t['findings_recommendations']); ?></h2>
    <?php if (!empty($data['multi_model']) && !empty($data['models_used'])) : ?><div class="model-chips"><?php foreach ((array)$data['models_used'] as $model) : ?><span><?php echo $this->e((string)($model['model'] ?? '')); ?> · <?php echo (int)($model['score'] ?? 0); ?>/100</span><?php endforeach; ?></div><?php endif; ?>
    <div class="appendix-block"><h3><?php echo $this->e($t['strengths']); ?></h3><ul><?php foreach ((array)($data['strengths'] ?? []) as $item) : ?><li><?php echo $this->e((string)$item); ?></li><?php endforeach; ?></ul></div>
    <div class="appendix-block"><h3><?php echo $this->e($t['weaknesses']); ?></h3><ul><?php foreach ((array)($data['weaknesses'] ?? []) as $item) : ?><li><?php echo $this->e((string)$item); ?></li><?php endforeach; ?></ul></div>
    <div class="appendix-block"><h3><?php echo $this->e($t['findings']); ?></h3><ul><?php foreach ((array)($data['findings'] ?? []) as $item) : ?><li><?php echo $this->e((string)$item); ?></li><?php endforeach; ?></ul></div>
    <div class="appendix-block"><h3><?php echo $this->e($t['recommendations']); ?></h3><ul><?php foreach ((array)($data['recommendations'] ?? []) as $item) : ?><li><?php echo $this->e((string)$item); ?></li><?php endforeach; ?></ul></div>
    <div class="appendix-block"><h3><?php echo $this->e($t['all_category_scores']); ?></h3><table class="metric-table"><?php foreach ($metricLabels as $key => $label) : $value = max(0, min(100, (int)($breakdown[$key] ?? 0))); ?><tr><td class="metric-name"><?php echo $this->e($label); ?></td><td><div class="bar"><div class="bar-fill" style="width:<?php echo $value; ?>%"></div></div></td><td class="metric-score"><?php echo $value; ?>/100</td></tr><?php endforeach; ?></table></div>
</section>
<section class="appendix-page">
    <?php echo $headerHtml; ?>
    <div class="section-kicker"><?php echo $this->e($t['keyword_analysis']); ?></div>
    <h2><?php echo $this->e($t['tested_keywords']); ?></h2>
    <table class="keyword-table"><thead><tr><th><?php echo $this->e($t['keyword']); ?></th><th><?php echo $this->e($t['ai_overview']); ?></th><th><?php echo $this->e($t['your_site_cited']); ?></th><th><?php echo $this->e($t['competitors']); ?></th></tr></thead><tbody><?php foreach ((array)($data['keywords_analysis'] ?? []) as $keyword) : ?><tr><td><?php echo $this->e((string)($keyword['keyword'] ?? '')); ?></td><td><?php echo !empty($keyword['has_ai_overview']) ? $this->e($t['yes']) : $this->e($t['no']); ?></td><td><?php echo !empty($keyword['target_cited']) ? $this->e($t['yes']) : $this->e($t['no']); ?></td><td><?php echo $this->e($this->competitorHosts((array)($keyword['competitor_citations'] ?? []))); ?></td></tr><?php endforeach; ?></tbody></table>
</section>
</body>
</html>
        <?php
        return (string)ob_get_clean();
    }

    private function actions(array $data, string $language): array
    {
        $actions = [];
        foreach ((array)($data['structured_actions'] ?? []) as $item) {
            if (!is_array($item) || trim((string)($item['title'] ?? '')) === '') {
                continue;
            }
            $actions[] = [
                'title' => (string)$item['title'],
                'description' => (string)($item['description'] ?? ''),
                'priority' => in_array($item['priority'] ?? '', ['high', 'medium', 'low'], true) ? $item['priority'] : 'medium',
                'category' => (string)($item['category'] ?? ($language === 'en' ? 'GEO readiness' : 'GEO-readiness')),
                'effort' => in_array($item['effort'] ?? '', ['low', 'medium', 'high'], true) ? $item['effort'] : 'medium',
            ];
        }
        if (!$actions) {
            foreach ((array)($data['priority_ranked_recommendations'] ?? $data['recommendations'] ?? []) as $index => $item) {
                $actions[] = [
                    'title' => (string)$item,
                    'description' => '',
                    'priority' => $index < 2 ? 'high' : ($index < 4 ? 'medium' : 'low'),
                    'category' => $language === 'en' ? 'GEO readiness' : 'GEO-readiness',
                    'effort' => 'medium',
                ];
            }
        }
        return array_slice($actions, 0, 8);
    }

    private function labels(string $language): array
    {
        if ($language === 'en') {
            return [
                'title'=>'GEO Scan Report','subtitle'=>'How visible is your website in answers from ChatGPT, Gemini, Perplexity and Google AI Overviews.','website'=>'Scanned website','scan_date'=>'Scan date','geo_score'=>'GEO score','report'=>'GEO scan report','management'=>'Management overview','total_score'=>'Total GEO score out of 100','platforms_visible'=>'AI platforms with visibility','actions_found'=>'Concrete actions identified','category_scores'=>'Score by category','platform_visibility'=>'Visibility by AI platform','where_visible'=>'Where are you used as a source?','platform_intro'=>'A snapshot based on live prompts in the most important AI tools.','benchmark'=>'Benchmark against competitors','your_site'=>'Your site','average_competitor'=>'Average competitor','best_competitor'=>'Best competitor','snapshot_note'=>'This benchmark is a snapshot based on %d successful live tests.','actions'=>'Concrete actions','actions_heading'=>'%d improvements ranked by priority','actions_intro'=>'Start at the top: these actions have the greatest expected impact on your GEO visibility.','next_steps'=>'Next steps','from_report'=>'From report to AI visibility','steps_intro'=>'Three practical steps to improve your score over the coming weeks.','cta_title'=>'Ready to become visible in AI?','cta_copy'=>'Contact Fyndable and we will review the opportunities with you.','contact'=>'Contact us','appendix'=>'Appendix','findings_recommendations'=>'Complete scan details','all_category_scores'=>'All category scores','strengths'=>'Strengths','weaknesses'=>'Areas for attention','findings'=>'Findings','recommendations'=>'Recommendations','keyword_analysis'=>'Keyword analysis','tested_keywords'=>'Results by tested keyword','keyword'=>'Keyword','ai_overview'=>'AI Overview','your_site_cited'=>'Your site cited','competitors'=>'Competitors cited','yes'=>'Yes','no'=>'No','good'=>'Good','needs_work'=>'Needs work','weak'=>'Weak','status_visible'=>'Visible','status_sometimes'=>'Sometimes mentioned','status_not_mentioned'=>'Not mentioned','status_unavailable'=>'Insufficient data','priority_high'=>'High','priority_medium'=>'Medium','priority_low'=>'Low','effort'=>'Effort','effort_low'=>'low','effort_medium'=>'medium','effort_high'=>'high','steps'=>[['Implement the two highest-priority actions','Start with the improvements expected to have the greatest impact.'],['Use Fyndable Smart SEO','Optimize content, schema and AI visibility from one workflow.'],['Repeat the scan in 30 days','Measure progress with the same prompts and platforms.']],
            ];
        }
        return [
            'title'=>'GEO Scan Rapport','subtitle'=>'Hoe zichtbaar is je website in de antwoorden van ChatGPT, Gemini, Perplexity en Google AI Overviews.','website'=>'Gescande website','scan_date'=>'Scandatum','geo_score'=>'GEO-score','report'=>'GEO scan rapport','management'=>'Managementoverzicht','total_score'=>'Totale GEO-score van 100','platforms_visible'=>'AI-platforms waarin je zichtbaar bent','actions_found'=>'Concrete actiepunten geïdentificeerd','category_scores'=>'Score per categorie','platform_visibility'=>'Zichtbaarheid per AI-platform','where_visible'=>'Waar word je wel en niet als bron gebruikt?','platform_intro'=>'Een momentopname op basis van live testprompts in de belangrijkste AI-tools.','benchmark'=>'Benchmark t.o.v. concurrentie','your_site'=>'Jouw site','average_competitor'=>'Gemiddelde concurrent','best_competitor'=>'Beste concurrent','snapshot_note'=>'Deze benchmark is een momentopname op basis van %d geslaagde live tests.','actions'=>'Concrete actiepunten','actions_heading'=>'%d ingrepen, op prioriteit gerangschikt','actions_intro'=>'Begin bovenaan: dit zijn de acties met de grootste verwachte impact op je GEO-zichtbaarheid.','next_steps'=>'Volgende stappen','from_report'=>'Van rapport naar zichtbaarheid in AI','steps_intro'=>'Drie praktische stappen om je score de komende weken omhoog te brengen.','cta_title'=>'Klaar om zichtbaar te worden in AI?','cta_copy'=>'Neem contact op met Fyndable en we lopen de kansen samen door.','contact'=>'Neem contact op','appendix'=>'Bijlage','findings_recommendations'=>'Volledige scandetails','all_category_scores'=>'Alle categoriescores','strengths'=>'Sterke punten','weaknesses'=>'Aandachtspunten','findings'=>'Bevindingen','recommendations'=>'Aanbevelingen','keyword_analysis'=>'Zoekwoordanalyse','tested_keywords'=>'Resultaten per getest zoekwoord','keyword'=>'Zoekwoord','ai_overview'=>'AI-overzicht','your_site_cited'=>'Jouw site geciteerd','competitors'=>'Concurrenten geciteerd','yes'=>'Ja','no'=>'Nee','good'=>'Goed','needs_work'=>'Kan beter','weak'=>'Zwak','status_visible'=>'Zichtbaar','status_sometimes'=>'Soms genoemd','status_not_mentioned'=>'Niet genoemd','status_unavailable'=>'Onvoldoende data','priority_high'=>'Hoog','priority_medium'=>'Midden','priority_low'=>'Laag','effort'=>'Inspanning','effort_low'=>'laag','effort_medium'=>'midden','effort_high'=>'hoog','steps'=>[['Pak de twee hoge-prioriteitsacties op','Begin met de verbeteringen waarvan de grootste impact wordt verwacht.'],['Gebruik Fyndable Smart SEO','Optimaliseer content, schema en AI-zichtbaarheid vanuit één workflow.'],['Herhaal de scan over 30 dagen','Meet de vooruitgang met dezelfde prompts en platformen.']],
        ];
    }

    private function metricLabels(string $language): array
    {
        return $language === 'en'
            ? ['direct_answer'=>'Direct answer','structure'=>'Content structure','schema_markup'=>'Schema markup','entities'=>'Entities','citation_worthiness'=>'Citation worthiness','readability'=>'Readability','eeat'=>'E-E-A-T','content_freshness'=>'Content freshness','mobile_friendly'=>'Mobile friendly','internal_linking'=>'Internal linking','page_metadata'=>'Page metadata','entity_coverage'=>'Entity coverage','competitive_gap'=>'Competitive gap']
            : ['direct_answer'=>'Direct antwoord','structure'=>'Contentstructuur','schema_markup'=>'Schema markup','entities'=>'Entiteiten','citation_worthiness'=>'Citeerwaardigheid','readability'=>'Leesbaarheid','eeat'=>'E-E-A-T','content_freshness'=>'Actualiteit','mobile_friendly'=>'Mobiel vriendelijk','internal_linking'=>'Interne links','page_metadata'=>'Metadata','entity_coverage'=>'Entiteitsdekking','competitive_gap'=>'Concurrentieverschil'];
    }

    private function platformLabels(string $language): array
    {
        return ['chatgpt'=>'ChatGPT','perplexity'=>'Perplexity','gemini'=>'Gemini','google_ai_overviews'=>'Google AI Overviews'];
    }

    private function platformCopy(array $platform, string $language): string
    {
        $tests = (int)($platform['tests'] ?? 0);
        $mentions = (int)($platform['mentions'] ?? 0);
        if ($tests === 0) {
            return $language === 'en' ? 'No reliable live test result was available.' : 'Er was geen betrouwbaar live testresultaat beschikbaar.';
        }
        return $language === 'en'
            ? sprintf('Mentioned or cited in %d of %d tested prompts (%d%%).', $mentions, $tests, (int)($platform['visibility'] ?? 0))
            : sprintf('Genoemd of geciteerd in %d van %d geteste prompts (%d%%).', $mentions, $tests, (int)($platform['visibility'] ?? 0));
    }

    private function scoreStatus(int $score, string $language): string
    {
        if ($language === 'en') {
            return $score >= 70 ? 'Good' : ($score >= 40 ? 'Needs work' : 'Weak');
        }
        return $score >= 70 ? 'Goed' : ($score >= 40 ? 'Kan beter' : 'Zwak');
    }

    private function scoreHeadline(int $score, string $language): string
    {
        if ($language === 'en') {
            return $score >= 70 ? 'A strong foundation for AI visibility' : ($score >= 40 ? 'Partly visible, with clear opportunities to improve' : 'Substantial work is needed to become visible in AI');
        }
        return $score >= 70 ? 'Een sterke basis voor zichtbaarheid in AI' : ($score >= 40 ? 'Deels vindbaar, met duidelijke winst te halen' : 'Er is veel winst te behalen in AI-zichtbaarheid');
    }

    private function fallbackSummary(int $score, string $language): string
    {
        return $language === 'en'
            ? sprintf('Your website scores %d out of 100 for GEO readiness. The report below shows where AI systems can already understand and cite your content and which improvements offer the greatest opportunity.', $score)
            : sprintf('Je website scoort %d van de 100 op GEO-readiness. Dit rapport laat zien waar AI-systemen je content al kunnen begrijpen en citeren en welke verbeteringen de grootste kans bieden.', $score);
    }

    private function logoDataUri(bool $white): string
    {
        $file = SSEO_AI_SAAS_PLUGIN_DIR . 'assets/' . ($white ? 'logo-white.png' : 'logo_long_black.png');
        if (!is_readable($file)) {
            return '';
        }
        return 'data:image/png;base64,' . base64_encode((string)file_get_contents($file));
    }

    private function fontDataUri(bool $bold): string
    {
        $file = SSEO_AI_SAAS_PLUGIN_DIR . 'assets/fonts/outfit/' . ($bold ? 'Outfit-Bold.ttf' : 'Outfit-Regular.ttf');
        if (!is_readable($file)) {
            return '';
        }
        return 'data:font/truetype;base64,' . base64_encode((string)file_get_contents($file));
    }

    private function scoreRingDataUri(int $score, bool $dark): string
    {
        $score = max(0, min(100, $score));
        $circumference = 263.89;
        $dash = round($circumference * ($score / 100), 2);
        $track = $dark ? '#34364d' : '#e5e8ee';
        $text = $dark ? '#ffffff' : '#343434';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#a43bb8"/><stop offset="1" stop-color="#32a6db"/></linearGradient></defs>'
            . '<circle cx="80" cy="80" r="42" fill="none" stroke="' . $track . '" stroke-width="10"/>'
            . '<circle cx="80" cy="80" r="42" fill="none" stroke="#8742bd" stroke-width="10" stroke-linecap="round" stroke-dasharray="' . $dash . ' ' . ($circumference - $dash) . '" transform="rotate(-90 80 80)"/>'
            . '<text x="80" y="79" text-anchor="middle" font-family="DejaVu Sans" font-weight="700" font-size="32" fill="' . $text . '">' . $score . '</text>'
            . '<text x="80" y="99" text-anchor="middle" font-family="DejaVu Sans" font-size="12" fill="#8992a4">/ 100</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function host(string $url): string
    {
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: $url);
        return preg_replace('/^www\./i', '', strtolower($host)) ?: $host;
    }

    private function competitorHosts(array $competitors): string
    {
        $hosts = [];
        foreach ($competitors as $competitor) {
            $host = $this->host((string)($competitor['host'] ?? ''));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }
        return implode(', ', array_values(array_unique($hosts)));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

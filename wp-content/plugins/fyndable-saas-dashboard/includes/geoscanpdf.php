<?php

namespace SSEOAISaaS;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class GeoScanPdf
{
    private GeoScanRepository $repository;
    /** Tijdens render(): de mPDF-instantie, gebruikt om tekstbreedtes van pills te meten. */
    private ?Mpdf $mpdf = null;

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
        if (!class_exists(Mpdf::class)) {
            return new \WP_Error('geo_pdf_dependency_missing', __('De PDF-renderer is niet beschikbaar.', 'sseo-ai-saas'));
        }

        $cover = $cover === 'dark' ? 'dark' : 'light';
        $data = $scan['result'];
        $host = $this->host((string)($data['url'] ?? $scan['url'] ?? 'website'));

        try {
            // Alleen DejaVu Sans (vendor/mpdf/mpdf/ttfonts, uitgedund door tools/trim-mpdf-fonts.php) en Outfit (assets).
            $fontdata = (new FontVariables())->getDefaults()['fontdata'];
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'tempDir' => $this->tempDir(),
                'fontDir' => array_merge((new ConfigVariables())->getDefaults()['fontDir'], [SSEO_AI_SAAS_PLUGIN_DIR . 'assets/fonts/outfit']),
                'fontdata' => [
                    'outfit' => ['R' => 'Outfit-Regular.ttf', 'B' => 'Outfit-Bold.ttf'],
                    'dejavusans' => $fontdata['dejavusans'],
                ],
                'default_font' => 'outfit',
                // Tekens die Outfit niet heeft (bijv. ● of pijlen) vallen terug op DejaVu Sans.
                'useSubstitutions' => true,
                'backupSubsFont' => ['dejavusans'],
                'backupSIPFont' => 'dejavusans',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
            ]);
            $mpdf->SetTitle('GEO Scan · ' . $host);
            $mpdf->SetAuthor('Fyndable Smart SEO');
            $mpdf->SetCreator('Fyndable Smart SEO');
            $this->mpdf = $mpdf;
            $html = $this->buildHtml($scan, $cover);
            $this->mpdf = null;
            $mpdf->WriteHTML($html);
            $bytes = $mpdf->Output('', Destination::STRING_RETURN);
        } catch (\Throwable $e) {
            $this->mpdf = null;
            error_log('[Fyndable] GEO scan PDF failed: ' . $e->getMessage());
            return new \WP_Error('geo_pdf_render_failed', __('De PDF kon niet worden gemaakt.', 'sseo-ai-saas'));
        }

        $date = !empty($data['scanned_at']) ? strtotime($data['scanned_at']) : time();
        $filename = sanitize_file_name(sprintf('fyndable-geo-scan-%s-%s-%s.pdf', $host, wp_date('Y-m-d', $date), $cover));

        return ['bytes' => $bytes, 'filename' => $filename];
    }

    private function tempDir(): string
    {
        $dir = trailingslashit(get_temp_dir()) . 'fyndable-mpdf';
        wp_mkdir_p($dir);
        return $dir;
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
        $dark = $cover === 'dark';
        $logo = $this->logoDataUri($dark);
        $headerLogo = $this->logoDataUri(false);
        $coverBackground = $this->coverBackgroundDataUri($dark);
        $scoreRing = $this->scoreRingDataUri($score, $dark);
        // Covertinten: donker (primair ontwerp) of licht. mPDF kent geen samengestelde selectors, dus per variant inline in de CSS.
        $cv = $dark
            ? ['text' => '#ffffff', 'eyebrow' => '#c57ce1', 'word' => '#C77DFF', 'subtitle' => '#d3cfdc', 'label' => '#9b97ab', 'card' => '#24223f', 'card_border' => '#3b3957', 'pill' => '#322e50', 'pill_border' => '#8252b7', 'pill_text' => '#ffffff', 'fine' => '#7f7b92']
            : ['text' => '#1a1430', 'eyebrow' => '#8F39AC', 'word' => '#7546c9', 'subtitle' => '#515a6c', 'label' => '#746e82', 'card' => '#f7f5fb', 'card_border' => '#ece9f2', 'pill' => '#f3e8f8', 'pill_border' => '#c9a5e0', 'pill_text' => '#8F39AC', 'fine' => '#a39dae'];
        $scoreLabel = $score >= 70 ? $t['good'] : ($score >= 40 ? $t['needs_work'] : $t['weak']);
        $pageLabel = $language === 'en' ? 'Page' : 'Pagina';
        $confidential = $language === 'en'
            ? 'Confidential · Prepared by Fyndable Smart SEO · fyndable.ai'
            : 'Vertrouwelijk · Opgesteld door Fyndable Smart SEO · fyndable.ai';
        $visiblePlatforms = count(array_filter($platforms, static fn(array $platform): bool => in_array($platform['status'] ?? '', ['visible', 'sometimes'], true)));
        $platformCount = count(array_filter($platforms, static fn(array $platform): bool => !in_array($platform['status'] ?? '', ['unavailable', 'not_tested'], true)));
        $metricLabels = $this->metricLabels($language);
        $summaryMetrics = array_intersect_key($metricLabels, array_flip(['structure', 'schema_markup', 'direct_answer', 'citation_worthiness']));
        $metricIcons = ['structure' => 'editor-ul', 'schema_markup' => 'editor-code', 'direct_answer' => 'format-chat', 'citation_worthiness' => 'admin-links'];
        // "Belangrijkste bevinding": er is geen apart resultaatveld, dus de eerste bevinding uit de scan.
        $keyFinding = '';
        foreach ((array)($data['findings'] ?? []) as $finding) {
            if (trim((string)$finding) !== '') {
                $keyFinding = trim((string)$finding);
                break;
            }
        }
        $keyFindingLabel = $language === 'en' ? 'Key finding' : 'Belangrijkste bevinding';
        // Statuskleuren [achtergrond, tekst] en platform-monogrammen uit de referentie.
        $statusColors = ['visible' => ['#ccf5e2', '#087b55'], 'sometimes' => ['#fff0bf', '#aa6200'], 'not_mentioned' => ['#ffdfe1', '#bd202b'], 'unavailable' => ['#eceef3', '#687083'], 'not_tested' => ['#eceef3', '#687083']];
        $platformChips = ['chatgpt' => ['C', '#10a37f'], 'perplexity' => ['P', '#20b8cd'], 'gemini' => ['G', '#4a9eff'], 'google_ai_overviews' => ['AI', '#ea4335']];
        $priorityColors = ['high' => ['#ffdfe1', '#b71925'], 'medium' => ['#fff0bf', '#a85d00'], 'low' => ['#ccf5e2', '#087b55']];
        $yesPill = $this->pillHtml($t['yes'], '#ccf5e2', '#087b55', 8.25, 5, 9);
        $noPill = $this->pillHtml($t['no'], '#ffdfe1', '#bd202b', 8.25, 5, 9);

        ob_start();
        ?>
<!doctype html>
<html lang="<?php echo $this->e($language); ?>">
<head>
<meta charset="utf-8">
<style>
/* mPDF: geen flex/grid; opmaak met tabellen. 1px uit de referentie (794px breed) = 0.2646mm = 0.75pt. */
@page { margin: 0; }
@page cover { margin: 0; margin-header: 0; margin-footer: 0; }
@page inner { margin-top: 31mm; margin-bottom: 21mm; margin-left: 14mm; margin-right: 14mm; margin-header: 12mm; margin-footer: 8mm; header: html_innerHeader; footer: html_innerFooter; }
@page appendix { margin-top: 31mm; margin-bottom: 21mm; margin-left: 14mm; margin-right: 14mm; margin-header: 12mm; margin-footer: 8mm; header: html_appendixHeader; footer: html_innerFooter; }
body { color: #413b52; font-family: outfit; font-size: 10pt; line-height: 1.45; }
a { color: inherit; text-decoration: none; }

/* Header en footer van de binnenpagina's */
/* De lijn zit op een omhullende div: tabelranden per cel laten in mPDF een gat tussen de cellen. */
.hdr-wrap { padding-bottom: 3.7mm; border-bottom: 0.53mm solid #ece9f2; }
.hdr { width: 100%; border-collapse: collapse; }
.hdr td { padding: 0; vertical-align: middle; }
.hdr-label { text-align: right; color: #a39dae; font-size: 7.9pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; }
.ftr-wrap { padding-top: 2.6mm; border-top: 0.26mm solid #ece9f2; }
.ftr { width: 100%; border-collapse: collapse; }
.ftr td { padding: 0; color: #a39dae; font-size: 7.9pt; }

/* Cover: losse, absoluut gepositioneerde blokken op een pagina zonder marge */
.cv-bg { position: absolute; left: 0; top: 0; width: 210mm; height: 297mm; }
.cv-logo { position: absolute; left: 15mm; top: 17mm; }
.cv-copy { position: absolute; left: 15mm; top: 133mm; width: 150mm; color: <?php echo $cv['text']; ?>; }
.cv-eyebrow { color: <?php echo $cv['eyebrow']; ?>; font-size: 9.75pt; font-weight: bold; letter-spacing: 1.4pt; text-transform: uppercase; margin-bottom: 4.8mm; }
.cv-title { font-size: 45pt; font-weight: bold; line-height: 1; letter-spacing: -1.3pt; color: <?php echo $cv['text']; ?>; }
.cv-word { color: <?php echo $cv['word']; ?>; }
.cv-subtitle { margin-top: 6.3mm; width: 114mm; font-size: 12pt; line-height: 1.6; color: <?php echo $cv['subtitle']; ?>; }
.cv-card { position: absolute; left: 15mm; top: 213mm; width: 180mm; height: 59mm; background: <?php echo $cv['card']; ?>; border: 0.26mm solid <?php echo $cv['card_border']; ?>; border-top: 0.8mm solid #8842bd; border-radius: 4.2mm; }
/* Inhoud van de kaart als losse blokken: mPDF negeert blokopmaak (radius, breedte) binnen tabelcellen. */
.cv-meta { position: absolute; left: 22.4mm; top: 241mm; width: 100mm; color: <?php echo $cv['text']; ?>; }
.cv-score { position: absolute; left: 132mm; top: 220mm; width: 56mm; text-align: center; }
.cv-label { color: <?php echo $cv['label']; ?>; font-size: 8.25pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; }
.cv-value { font-size: 14.25pt; font-weight: bold; margin-top: 0.8mm; padding-bottom: 4.2mm; }
.cv-value-last { padding-bottom: 0; }
.cv-pill { margin: 2.6mm auto 0; width: 50mm; padding: 1.3mm 3.7mm; border: 0.26mm solid <?php echo $cv['pill_border']; ?>; border-radius: 5mm; background: <?php echo $cv['pill']; ?>; color: <?php echo $cv['pill_text']; ?>; font-size: 8.25pt; font-weight: bold; letter-spacing: 0.2pt; text-transform: uppercase; text-align: center; }
.cv-fine { position: absolute; left: 0; top: 277mm; width: 210mm; text-align: center; font-size: 8.25pt; color: <?php echo $cv['fine']; ?>; }

/* Binnenpagina's: gedeelde typografie (contentbreedte 182mm) */
.kick { color: #8F39AC; font-size: 8.6pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; }
.kick-icon { width: 4mm; height: 4mm; vertical-align: middle; }
.h2 { margin: 1.85mm 0 2.6mm; font-size: 18.75pt; font-weight: bold; line-height: 1.1; letter-spacing: -0.4pt; color: #1a1430; }
.h3 { margin: 0 0 3.7mm; font-size: 12pt; font-weight: bold; color: #1a1430; }
.lead { margin: 0 0 1mm; font-size: 10.5pt; line-height: 1.6; color: #515a6c; }
.clear { clear: both; height: 0; font-size: 1pt; line-height: 0; }

/* 2. Managementoverzicht: drie tegels als floats (afgeronde hoeken werken niet in tabelcellen) */
.tiles { margin: 5.8mm 0 6.3mm; }
/* mPDF negeert margin-right en height op floats: tussenruimte als margin-left op tegel 2 en 3, en de vaste
   hoogte zit op het label (ruimte voor twee regels), zodat de tegels even hoog zijn. */
.tile { float: left; width: 47.6mm; padding: 4.8mm 5.3mm; background: #f7f5fb; border-top: 1.06mm solid #8c42bd; border-radius: 3.2mm; }
.tile-next { margin-left: 3.7mm; }
.tile-value { font-size: 22.5pt; font-weight: bold; line-height: 1.05; color: #9b39b5; }
.tile-label { margin-top: 1mm; height: 8.6mm; font-size: 9pt; line-height: 1.35; color: #596274; }
.metrics { width: 100%; border-collapse: collapse; }
.metrics td { padding: 1.85mm 0; vertical-align: middle; }
.metric-chip { width: 12.2mm; }
.metric-label { width: 56mm; padding: 1.85mm 2.6mm 1.85mm 1mm !important; font-size: 10.1pt; font-weight: bold; color: #1a1430; }
.metric-bar { width: 76.8mm; }
.metric-value { width: 37mm; text-align: right; font-size: 9pt; color: #9299a8; }
.metric-value b { font-size: 10.5pt; color: #1a1430; }
.callout { margin-top: 6.3mm; padding: 5.3mm 5.8mm; background: linear-gradient(315deg, #f3eaf7, #eaf3f9); border: 0.26mm solid #ece9f2; border-radius: 3.7mm; }
.callout-title { margin-bottom: 2.1mm; font-size: 10.5pt; font-weight: bold; color: #1a1430; }
.callout-icon { width: 4.2mm; height: 4.2mm; vertical-align: middle; }
.callout-text { font-size: 9.75pt; line-height: 1.6; color: #413b52; }

/* 3. Zichtbaarheid per AI-platform: kaarten 89.15mm breed (182 − 3.7 tussenruimte) / 2, inhoud 79mm */
.platforms { margin: 5.3mm 0 6.3mm; }
.platform-row-next { margin-top: 3.7mm; }
.platform { float: left; width: 79mm; padding: 4.2mm 4.8mm; border: 0.26mm solid #e0e3e8; border-radius: 3.2mm; }
.platform-next { margin-left: 3.7mm; }
.platform-head { width: 100%; border-collapse: collapse; margin-bottom: 2.6mm; }
.platform-head td { padding: 0; vertical-align: middle; }
.platform-chip { width: 10.3mm; }
.platform-name { font-size: 10.5pt; font-weight: bold; color: #1a1430; }
.platform-badge { text-align: right; }
.platform-copy { margin-top: 2.1mm; height: 8mm; font-size: 9pt; line-height: 1.45; color: #626a7b; }
.benchmark { padding: 4.8mm 5.8mm; background: #f8f8f9; border-radius: 3.2mm; }
.benchmark-rows { width: 100%; border-collapse: collapse; }
.benchmark-rows td { padding: 1.85mm 0; vertical-align: middle; }
.benchmark-label { width: 58mm; font-size: 9.75pt; font-weight: bold; color: #1a1430; }
.benchmark-bar { width: 100.2mm; }
.benchmark-value { width: 12.2mm; text-align: right; font-size: 10.5pt; font-weight: bold; color: #1a1430; }
.note { margin-top: 3.7mm; padding: 3.2mm 4.8mm; background: #f8f8f9; border-radius: 2.6mm; font-size: 9pt; color: #687083; }

/* 4. Concrete actiepunten */
.actions { width: 100%; margin-top: 4.8mm; border-collapse: collapse; }
.actions td { padding: 4mm 0; vertical-align: top; border-top: 0.26mm solid #e1e4ec; }
.action-priority { width: 19.5mm; }
.action-title { font-size: 10.9pt; font-weight: bold; line-height: 1.3; color: #1a1430; }
.action-description { margin: 0.8mm 0 1.85mm; font-size: 9.4pt; line-height: 1.5; color: #657083; }

/* 5. Volgende stappen + CTA */
.steps { margin-top: 5.3mm; }
.step { margin-bottom: 3.2mm; padding: 4.8mm 5.8mm; background: #f8f8f9; border-radius: 3.2mm; }
.step-table { width: 100%; border-collapse: collapse; }
.step-table td { padding: 0; vertical-align: middle; }
.step-number { width: 17mm; }
.step-title { font-size: 11.25pt; font-weight: bold; color: #1a1430; }
.step-text { margin-top: 0.5mm; font-size: 9.75pt; line-height: 1.5; color: #657083; }
.cta { margin-top: 3.7mm; padding: 7.9mm 9mm; background: linear-gradient(315deg, #2A1533, #132A38); border-top: 1.06mm solid #8742bd; border-radius: 4.2mm; }
.cta-table { width: 100%; border-collapse: collapse; }
.cta-table td { padding: 0; vertical-align: middle; }
.cta-copy { padding-right: 5.8mm !important; }
.cta-title { margin-bottom: 2.1mm; font-size: 15.75pt; font-weight: bold; line-height: 1.2; color: #ffffff; }
.cta-text { font-size: 10.1pt; line-height: 1.55; color: #d4d0dc; }
.cta-contact { width: 55mm; text-align: center; }
.cta-address { margin-top: 2.6mm; font-size: 9pt; color: #d7dae4; }

/* 6. Bijlage: lijsten 2×2 als floats van 88.35mm (182 − 5.3) / 2 */
.models { margin: 2.6mm 0 4.8mm; line-height: 2; }
.models-intro { font-size: 8.25pt; font-weight: bold; color: #746e82; }
.lists-next { margin-top: 4.8mm; }
.list { float: left; width: 88.35mm; }
.list-next { margin-left: 5.3mm; }
.list-title { margin-bottom: 2.1mm; padding-bottom: 1.3mm; border-bottom: 0.26mm solid #ece9f2; font-size: 10.5pt; font-weight: bold; }
ul { margin: 0; padding-left: 4.8mm; }
li { margin-bottom: 1.6mm; font-size: 9.4pt; line-height: 1.45; color: #413b52; }
.h3-small { margin: 5.3mm 0 2.6mm; font-size: 10.5pt; }
.all-metrics { width: 100%; border-collapse: collapse; }
.all-metrics td { padding: 1.05mm 0; vertical-align: middle; }
.all-metric-label { width: 58.2mm; padding-right: 2.6mm !important; font-size: 9pt; font-weight: bold; color: #1a1430; }
.all-metric-bar { width: 108.5mm; }
.all-metric-value { width: 15.3mm; text-align: right; font-size: 9pt; color: #746e82; }

/* 7. Bijlage: zoekwoordanalyse */
.keywords { width: 100%; margin-top: 4.8mm; border-collapse: collapse; }
.keywords th { padding: 2.4mm 3.2mm; background: #171a32; color: #ffffff; font-size: 8.25pt; font-weight: bold; text-align: left; }
.keywords td { padding: 2.4mm 3.2mm; border-bottom: 0.26mm solid #e7e9ef; vertical-align: middle; }
.keywords .kw-keyword { width: 60mm; }
td.kw-keyword { font-size: 9pt; font-weight: bold; color: #1a1430; }
.keywords .kw-flag { width: 30mm; text-align: center; }
td.kw-competitors { font-size: 8.6pt; color: #657083; }
.kw-note { margin-top: 5.3mm; font-size: 8.25pt; line-height: 1.5; color: #a39dae; }
</style>
</head>
<body>
<htmlpageheader name="innerHeader"><div class="hdr-wrap"><table class="hdr"><tr><td><?php if ($headerLogo !== '') : ?><img src="<?php echo $headerLogo; ?>" style="height: 7.9mm;" alt="Fyndable Smart SEO"><?php endif; ?></td><td class="hdr-label"><?php echo $this->e($t['report'] . ' · ' . $host); ?></td></tr></table></div></htmlpageheader>
<htmlpageheader name="appendixHeader"><div class="hdr-wrap"><table class="hdr"><tr><td><?php if ($headerLogo !== '') : ?><img src="<?php echo $headerLogo; ?>" style="height: 7.9mm;" alt="Fyndable Smart SEO"><?php endif; ?></td><td class="hdr-label"><?php echo $this->e($t['appendix'] . ' · ' . $host); ?></td></tr></table></div></htmlpageheader>
<htmlpagefooter name="innerFooter"><div class="ftr-wrap"><table class="ftr"><tr><td>Fyndable GEO Scan · <?php echo $this->e($host); ?></td><td style="text-align: right;"><?php echo $pageLabel; ?> {PAGENO} / {nbpg}</td></tr></table></div></htmlpagefooter>

<!-- 1. Cover: volle pagina zonder marge, zonder header/footer. Het lege blok in de normale flow is nodig:
     zonder flow-inhoud negeert mPDF de <pagebreak> hieronder en komt pagina 2 onder de cover terecht. -->
<div style="page: cover; height: 1mm;">&nbsp;</div>
<div class="cv-bg"><img src="<?php echo $coverBackground; ?>" style="width: 210mm; height: 297mm;" alt=""></div>
<?php if ($logo !== '') : ?><div class="cv-logo"><img src="<?php echo $logo; ?>" style="height: 13.8mm;" alt="Fyndable Smart SEO"></div><?php endif; ?>
<div class="cv-copy">
    <div class="cv-eyebrow">Generative Engine Optimization</div>
    <div class="cv-title">GEO Scan<br><span class="cv-word"><?php echo $language === 'en' ? 'Report' : 'Rapport'; ?></span></div>
    <div class="cv-subtitle"><?php echo $this->e($t['subtitle']); ?></div>
</div>
<div class="cv-card"></div>
<div class="cv-meta">
    <div class="cv-label"><?php echo $this->e($t['website']); ?></div>
    <div class="cv-value"><?php echo $this->e($host); ?></div>
    <div class="cv-label"><?php echo $this->e($t['scan_date']); ?></div>
    <div class="cv-value cv-value-last"><?php echo $this->e($date); ?></div>
</div>
<div class="cv-score">
    <img src="<?php echo $scoreRing; ?>" style="width: 35mm; height: 35mm;" alt="<?php echo $score; ?>/100">
    <div class="cv-pill"><?php echo $this->e($t['geo_score'] . ' · ' . $scoreLabel); ?></div>
</div>
<div class="cv-fine"><?php echo $this->e($confidential); ?></div>

<pagebreak page-selector="inner" resetpagenum="1" />
<!-- 2. Managementoverzicht -->
<div class="kick"><img src="<?php echo $this->iconDataUri('chart-bar'); ?>" class="kick-icon" alt=""> <?php echo $this->e($t['management']); ?></div>
<div class="h2"><?php echo $this->e($headline); ?></div>
<div class="lead"><?php echo $this->e($summary); ?></div>
<div class="tiles">
    <?php foreach ([[$score, $t['total_score']], [$visiblePlatforms . '/' . max(1, $platformCount), $t['platforms_visible']], [count($actions), $t['actions_found']]] as $index => [$value, $label]) : ?>
    <div class="tile<?php echo $index > 0 ? ' tile-next' : ''; ?>"><div class="tile-value"><?php echo $this->e((string)$value); ?></div><div class="tile-label"><?php echo $this->e($label); ?></div></div>
    <?php endforeach; ?>
    <div class="clear"></div>
</div>
<div class="h3"><?php echo $this->e($t['category_scores']); ?></div>
<table class="metrics">
    <?php foreach ($summaryMetrics as $key => $label) : $value = max(0, min(100, (int)($breakdown[$key] ?? 0))); ?>
    <tr>
        <td class="metric-chip"><img src="<?php echo $this->iconDataUri($metricIcons[$key] ?? 'chart-bar', '#ffffff', true); ?>" style="width: 9mm; height: 7.9mm;" alt=""></td>
        <td class="metric-label"><?php echo $this->e($label); ?></td>
        <td class="metric-bar"><img src="<?php echo $this->barDataUri($value, 74, 2.4); ?>" style="width: 74mm; height: 2.4mm;" alt=""></td>
        <td class="metric-value"><?php echo $this->e($this->scoreStatus($value, $language)); ?> <b><?php echo $value; ?></b>/100</td>
    </tr>
    <?php endforeach; ?>
</table>
<?php if ($keyFinding !== '') : ?>
<div class="callout">
    <div class="callout-title"><img src="<?php echo $this->iconDataUri('lightbulb'); ?>" class="callout-icon" alt=""> <?php echo $this->e($keyFindingLabel); ?></div>
    <div class="callout-text"><?php echo $this->e($keyFinding); ?></div>
</div>
<?php endif; ?>
<pagebreak page-selector="inner" />
<!-- 3. Zichtbaarheid per AI-platform: 2×2 kaarten als floats -->
<div class="kick"><img src="<?php echo $this->iconDataUri('visibility'); ?>" class="kick-icon" alt=""> <?php echo $this->e($t['platform_visibility']); ?></div>
<div class="h2"><?php echo $this->e($t['where_visible']); ?></div>
<div class="lead"><?php echo $this->e($t['platform_intro']); ?></div>
<div class="platforms">
    <?php foreach (array_chunk($this->platformLabels($language), 2, true) as $rowIndex => $row) : ?>
    <div class="platform-row<?php echo $rowIndex > 0 ? ' platform-row-next' : ''; ?>">
        <?php $col = 0; foreach ($row as $key => $label) : $platform = (array)($platforms[$key] ?? []); $status = (string)($platform['status'] ?? 'unavailable'); $status = isset($statusColors[$status]) ? $status : 'unavailable'; [$monogram, $chipColor] = $platformChips[$key] ?? [mb_substr($label, 0, 1), '#8F39AC']; ?>
        <div class="platform<?php echo $col++ > 0 ? ' platform-next' : ''; ?>">
            <table class="platform-head"><tr>
                <td class="platform-chip"><img src="<?php echo $this->monogramDataUri($monogram, $chipColor); ?>" style="width: 7.9mm; height: 7.9mm;" alt=""></td>
                <td class="platform-name"><?php echo $this->e($label); ?></td>
                <td class="platform-badge"><?php echo $this->pillHtml($t['status_' . $status], $statusColors[$status][0], $statusColors[$status][1]); ?></td>
            </tr></table>
            <img src="<?php echo $this->barDataUri($status === 'unavailable' ? 0 : (int)($platform['visibility'] ?? 0), 79, 2.1, 'grad', '#edeaf2'); ?>" style="width: 79mm; height: 2.1mm;" alt="">
            <div class="platform-copy"><?php echo $this->e($this->platformCopy($platform, $language)); ?></div>
        </div>
        <?php endforeach; ?>
        <div class="clear"></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="h3"><?php echo $this->e($t['benchmark']); ?></div>
<div class="benchmark">
    <table class="benchmark-rows">
        <?php foreach ([['own_visibility', $t['your_site'], 'grad'], ['average_competitor', $t['average_competitor'], '#9299a8'], ['best_competitor', $t['best_competitor'], '#596274']] as [$key, $label, $fill]) : $value = isset($benchmark[$key]) ? (int)$benchmark[$key] : 0; ?>
        <tr>
            <td class="benchmark-label"><?php echo $this->e($label); ?></td>
            <td class="benchmark-bar"><img src="<?php echo $this->barDataUri($value, 98, 2.9, $fill, '#e2e5ea'); ?>" style="width: 98mm; height: 2.9mm;" alt=""></td>
            <td class="benchmark-value"><?php echo $value; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<div class="note"><?php echo $this->e(sprintf($t['snapshot_note'], (int)($benchmark['tests'] ?? 0))); ?></div>
<pagebreak page-selector="inner" />
<!-- 4. Concrete actiepunten: één tabel, mPDF splitst geen rijen, dus lange lijsten lopen per actie door naar de volgende pagina -->
<div class="kick"><img src="<?php echo $this->iconDataUri('awards'); ?>" class="kick-icon" alt=""> <?php echo $this->e($t['actions']); ?></div>
<div class="h2"><?php echo $this->e(sprintf($t['actions_heading'], count($actions))); ?></div>
<div class="lead"><?php echo $this->e($t['actions_intro']); ?></div>
<table class="actions">
    <?php foreach ($actions as $action) : [$priorityBg, $priorityFg] = $priorityColors[$action['priority']]; ?>
    <tr>
        <td class="action-priority"><?php echo $this->pillHtml(mb_strtoupper($t['priority_' . $action['priority']]), $priorityBg, $priorityFg, 7.9, 5, 15.3); ?></td>
        <td class="action-body">
            <div class="action-title"><?php echo $this->e($action['title']); ?></div>
            <?php if ($action['description'] !== '') : ?><div class="action-description"><?php echo $this->e($action['description']); ?></div><?php endif; ?>
            <div class="action-tags"><?php echo $this->pillHtml($action['category'], '#f3e8f8', '#a13ab8', 7.9, 4.7); ?>&nbsp;&nbsp;<?php echo $this->pillHtml($t['effort'] . ': ' . $t['effort_' . $action['effort']], '#f0f2f5', '#657083', 7.9, 4.7); ?></div>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<pagebreak page-selector="inner" />
<!-- 5. Volgende stappen + CTA -->
<div class="kick"><img src="<?php echo $this->iconDataUri('arrow-right-alt'); ?>" class="kick-icon" alt=""> <?php echo $this->e($t['next_steps']); ?></div>
<div class="h2"><?php echo $this->e($t['from_report']); ?></div>
<div class="lead"><?php echo $this->e($t['steps_intro']); ?></div>
<div class="steps">
    <?php foreach ($t['steps'] as $index => $step) : ?>
    <div class="step"><table class="step-table"><tr>
        <td class="step-number"><img src="<?php echo $this->stepNumberDataUri($index + 1); ?>" style="width: 12.2mm; height: 12.2mm;" alt="<?php echo $index + 1; ?>"></td>
        <td><div class="step-title"><?php echo $this->e($step[0]); ?></div><div class="step-text"><?php echo $this->e($step[1]); ?></div></td>
    </tr></table></div>
    <?php endforeach; ?>
</div>
<div class="cta"><table class="cta-table"><tr>
    <td class="cta-copy"><div class="cta-title"><?php echo $this->e($t['cta_title']); ?></div><div class="cta-text"><?php echo $this->e($t['cta_copy']); ?></div></td>
    <td class="cta-contact"><?php echo $this->pillHtml($t['contact'], '#8b43bd', '#ffffff', 11.25, 11.6, 0, 3.2); ?><div class="cta-address">fyndable.ai · hallo@fyndable.ai</div></td>
</tr></table></div>

<pagebreak page-selector="appendix" />
<!-- 6. Bijlage: volledige scandetails -->
<div class="kick"><img src="<?php echo $this->iconDataUri('media-document'); ?>" class="kick-icon" alt=""> <?php echo $this->e($t['appendix']); ?></div>
<div class="h2"><?php echo $this->e($t['findings_recommendations']); ?></div>
<?php if (!empty($data['multi_model']) && !empty($data['models_used'])) : $modelsUsed = array_values(array_filter((array)$data['models_used'], 'is_array')); ?>
<div class="models"><span class="models-intro"><?php echo $this->e(sprintf($language === 'en' ? 'Analysed by %d models · score per model:' : 'Geanalyseerd door %d modellen · score per model:', count($modelsUsed))); ?></span>
    <?php foreach ($modelsUsed as $model) : ?> <?php echo $this->pillHtml(preg_replace('~^[^/]+/~', '', (string)($model['model'] ?? '')) . ' · ' . (int)($model['score'] ?? 0) . '/100', '#f0ebf8', '#1a1430', 8.25, 5.3); ?><?php endforeach; ?>
</div>
<?php endif; ?>
<?php foreach ([[['strengths', '#047857'], ['weaknesses', '#b91c1c']], [['findings', '#1a1430'], ['recommendations', '#1a1430']]] as $rowIndex => $row) : ?>
<div class="lists<?php echo $rowIndex > 0 ? ' lists-next' : ''; ?>">
    <?php foreach ($row as $col => [$field, $color]) : $items = array_values(array_filter(array_map(static fn($item): string => trim((string)$item), (array)($data[$field] ?? [])), 'strlen')); ?>
    <div class="list<?php echo $col > 0 ? ' list-next' : ''; ?>">
        <div class="list-title" style="color: <?php echo $color; ?>;"><?php echo $this->e($t[$field]); ?></div>
        <ul><?php foreach ($items ?: ['—'] as $item) : ?><li><?php echo $this->e($item); ?></li><?php endforeach; ?></ul>
    </div>
    <?php endforeach; ?>
    <div class="clear"></div>
</div>
<?php endforeach; ?>
<div class="h3 h3-small"><?php echo $this->e($t['all_category_scores']); ?></div>
<table class="all-metrics">
    <?php foreach ($metricLabels as $key => $label) : $value = max(0, min(100, (int)($breakdown[$key] ?? 0))); ?>
    <tr>
        <td class="all-metric-label"><?php echo $this->e($label); ?></td>
        <td class="all-metric-bar"><img src="<?php echo $this->barDataUri($value, 106, 1.85); ?>" style="width: 106mm; height: 1.85mm;" alt=""></td>
        <td class="all-metric-value"><?php echo $value; ?>/100</td>
    </tr>
    <?php endforeach; ?>
</table>

<pagebreak page-selector="appendix" />
<!-- 7. Bijlage: zoekwoordanalyse (thead herhaalt op een volgende pagina) -->
<div class="kick"><img src="<?php echo $this->iconDataUri('search'); ?>" class="kick-icon" alt=""> <?php echo $this->e($t['keyword_analysis']); ?></div>
<div class="h2"><?php echo $this->e($t['tested_keywords']); ?></div>
<div class="lead"><?php echo $this->e($language === 'en'
    ? 'Per keyword: whether an AI overview exists, whether your site is cited in it, and which competitors are mentioned.'
    : 'Per zoekwoord: of er een AI-overzicht bestaat, of jouw site daarin wordt geciteerd, en welke concurrenten wél worden genoemd.'); ?></div>
<table class="keywords">
    <thead><tr>
        <th class="kw-keyword"><?php echo $this->e($t['keyword']); ?></th>
        <th class="kw-flag"><?php echo $this->e($t['ai_overview']); ?></th>
        <th class="kw-flag"><?php echo $this->e($t['your_site_cited']); ?></th>
        <th class="kw-competitors"><?php echo $this->e($t['competitors']); ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ((array)($data['keywords_analysis'] ?? []) as $keyword) : $keyword = (array)$keyword; $competitorList = $this->competitorHosts((array)($keyword['competitor_citations'] ?? [])); ?>
    <tr>
        <td class="kw-keyword"><?php echo $this->e((string)($keyword['keyword'] ?? '')); ?></td>
        <td class="kw-flag"><?php echo !empty($keyword['has_ai_overview']) ? $yesPill : $noPill; ?></td>
        <td class="kw-flag"><?php echo !empty($keyword['target_cited']) ? $yesPill : $noPill; ?></td>
        <td class="kw-competitors"><?php echo $this->e($competitorList !== '' ? $competitorList : '—'); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div class="kw-note"><?php echo $this->e($language === 'en'
    ? 'Note: "Yes" under AI Overview means an AI summary exists; "Yes" under Your site cited means your website actually appears in that summary.'
    : 'Let op: "Ja" bij AI-overzicht betekent dat er een AI-samenvatting bestaat; "Ja" bij Jouw site geciteerd betekent dat je website daadwerkelijk in die samenvatting staat.'); ?></div>
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
                'title'=>'GEO Scan Report','subtitle'=>'How visible is your website in answers from ChatGPT, Gemini, Perplexity and Google AI Overviews.','website'=>'Scanned website','scan_date'=>'Scan date','geo_score'=>'GEO score','report'=>'GEO scan report','management'=>'Management overview','total_score'=>'Total GEO score out of 100','platforms_visible'=>'AI platforms with visibility','actions_found'=>'Concrete actions identified','category_scores'=>'Score by category','platform_visibility'=>'Visibility by AI platform','where_visible'=>'Where are you used as a source?','platform_intro'=>'A snapshot based on live prompts in the most important AI tools.','benchmark'=>'Benchmark against competitors','your_site'=>'Your site','average_competitor'=>'Average competitor','best_competitor'=>'Best competitor','snapshot_note'=>'This benchmark is a snapshot based on %d successful live tests.','actions'=>'Concrete actions','actions_heading'=>'%d improvements ranked by priority','actions_intro'=>'Start at the top: these actions have the greatest expected impact on your GEO visibility.','next_steps'=>'Next steps','from_report'=>'From report to AI visibility','steps_intro'=>'Three practical steps to improve your score over the coming weeks.','cta_title'=>'Ready to become visible in AI?','cta_copy'=>'Contact Fyndable and we will review the opportunities with you.','contact'=>'Contact us','appendix'=>'Appendix','findings_recommendations'=>'Complete scan details','all_category_scores'=>'All category scores','strengths'=>'Strengths','weaknesses'=>'Areas for attention','findings'=>'Findings','recommendations'=>'Recommendations','keyword_analysis'=>'Keyword analysis','tested_keywords'=>'Results by tested keyword','keyword'=>'Keyword','ai_overview'=>'AI Overview','your_site_cited'=>'Your site cited','competitors'=>'Competitors cited','yes'=>'Yes','no'=>'No','good'=>'Good','needs_work'=>'Needs work','weak'=>'Weak','status_visible'=>'Visible','status_sometimes'=>'Sometimes mentioned','status_not_mentioned'=>'Not mentioned','status_unavailable'=>'Insufficient data','status_not_tested'=>'Not tested','priority_high'=>'High','priority_medium'=>'Medium','priority_low'=>'Low','effort'=>'Effort','effort_low'=>'low','effort_medium'=>'medium','effort_high'=>'high','steps'=>[['Implement the two highest-priority actions','Start with the improvements expected to have the greatest impact.'],['Use Fyndable Smart SEO','Optimize content, schema and AI visibility from one workflow.'],['Repeat the scan in 30 days','Measure progress with the same prompts and platforms.']],
            ];
        }
        return [
            'title'=>'GEO Scan Rapport','subtitle'=>'Hoe zichtbaar is je website in de antwoorden van ChatGPT, Gemini, Perplexity en Google AI Overviews.','website'=>'Gescande website','scan_date'=>'Scandatum','geo_score'=>'GEO-score','report'=>'GEO scan rapport','management'=>'Managementoverzicht','total_score'=>'Totale GEO-score van 100','platforms_visible'=>'AI-platforms waarin je zichtbaar bent','actions_found'=>'Concrete actiepunten geïdentificeerd','category_scores'=>'Score per categorie','platform_visibility'=>'Zichtbaarheid per AI-platform','where_visible'=>'Waar word je wel en niet als bron gebruikt?','platform_intro'=>'Een momentopname op basis van live testprompts in de belangrijkste AI-tools.','benchmark'=>'Benchmark t.o.v. concurrentie','your_site'=>'Jouw site','average_competitor'=>'Gemiddelde concurrent','best_competitor'=>'Beste concurrent','snapshot_note'=>'Deze benchmark is een momentopname op basis van %d geslaagde live tests.','actions'=>'Concrete actiepunten','actions_heading'=>'%d ingrepen, op prioriteit gerangschikt','actions_intro'=>'Begin bovenaan: dit zijn de acties met de grootste verwachte impact op je GEO-zichtbaarheid.','next_steps'=>'Volgende stappen','from_report'=>'Van rapport naar zichtbaarheid in AI','steps_intro'=>'Drie praktische stappen om je score de komende weken omhoog te brengen.','cta_title'=>'Klaar om zichtbaar te worden in AI?','cta_copy'=>'Neem contact op met Fyndable en we lopen de kansen samen door.','contact'=>'Neem contact op','appendix'=>'Bijlage','findings_recommendations'=>'Volledige scandetails','all_category_scores'=>'Alle categoriescores','strengths'=>'Sterke punten','weaknesses'=>'Aandachtspunten','findings'=>'Bevindingen','recommendations'=>'Aanbevelingen','keyword_analysis'=>'Zoekwoordanalyse','tested_keywords'=>'Resultaten per getest zoekwoord','keyword'=>'Zoekwoord','ai_overview'=>'AI-overzicht','your_site_cited'=>'Jouw site geciteerd','competitors'=>'Concurrenten geciteerd','yes'=>'Ja','no'=>'Nee','good'=>'Goed','needs_work'=>'Kan beter','weak'=>'Zwak','status_visible'=>'Zichtbaar','status_sometimes'=>'Soms genoemd','status_not_mentioned'=>'Niet genoemd','status_unavailable'=>'Onvoldoende data','status_not_tested'=>'Niet getest','priority_high'=>'Hoog','priority_medium'=>'Midden','priority_low'=>'Laag','effort'=>'Inspanning','effort_low'=>'laag','effort_medium'=>'midden','effort_high'=>'hoog','steps'=>[['Pak de twee hoge-prioriteitsacties op','Begin met de verbeteringen waarvan de grootste impact wordt verwacht.'],['Gebruik Fyndable Smart SEO','Optimaliseer content, schema en AI-zichtbaarheid vanuit één workflow.'],['Herhaal de scan over 30 dagen','Meet de vooruitgang met dezelfde prompts en platformen.']],
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
        if (($platform['status'] ?? '') === 'not_tested') {
            return $language === 'en' ? 'Not included in this scan.' : 'Niet meegenomen in deze scan.';
        }
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

    /**
     * Achtergrond van de cover als één SVG (A4 in referentie-px, 794×1123): verloop, twee zachte gloeden,
     * een raster van 44px dat naar de randen vervaagt (de CSS-mask uit de referentie, per lijnstuk benaderd)
     * en de paarse rand bovenaan.
     */
    private function coverBackgroundDataUri(bool $dark): string
    {
        $w = 794;
        $h = 1123;
        $c = $dark
            ? ['a' => '#2A1533', 'b' => '#1d1b3a', 'c' => '#132A38', 'glow1' => '#8F39AC', 'glow1_op' => .55, 'glow2' => '#379FD3', 'glow2_op' => .4, 'grid' => '#ffffff', 'grid_op' => .078]
            : ['a' => '#ffffff', 'b' => '#fcfbfe', 'c' => '#f5fafd', 'glow1' => '#8F39AC', 'glow1_op' => .16, 'glow2' => '#379FD3', 'glow2_op' => .14, 'grid' => '#8F39AC', 'grid_op' => .07];

        // Raster: elk lijnstuk krijgt de dekking van de elliptische mask (85% × 70%, middelpunt 50% / 32%).
        $cx = $w * .5;
        $cy = $h * .32;
        $rx = $w * .85;
        $ry = $h * .7;
        $levels = [];
        $alpha = static function (float $x, float $y) use ($cx, $cy, $rx, $ry): int {
            $d = sqrt((($x - $cx) / $rx) ** 2 + (($y - $cy) / $ry) ** 2);
            return (int)round(max(0, 1 - $d) * 8); // 0..8
        };
        for ($x = 0; $x <= $w; $x += 44) {
            for ($y = 0; $y < $h; $y += 44) {
                $level = $alpha($x, $y + 22);
                if ($level > 0) {
                    $levels[$level][] = 'M' . $x . ' ' . $y . 'V' . min($h, $y + 44);
                }
            }
        }
        for ($y = 0; $y <= $h; $y += 44) {
            for ($x = 0; $x < $w; $x += 44) {
                $level = $alpha($x + 22, $y);
                if ($level > 0) {
                    $levels[$level][] = 'M' . $x . ' ' . $y . 'H' . min($w, $x + 44);
                }
            }
        }
        $grid = '';
        foreach ($levels as $level => $segments) {
            $grid .= '<path d="' . implode('', $segments) . '" stroke="' . $c['grid'] . '" stroke-width="1" stroke-opacity="' . round($c['grid_op'] * $level / 8, 3) . '" fill="none"/>';
        }

        $glow = static fn(string $id, string $color, float $opacity): string => '<radialGradient id="' . $id . '" cx="50%" cy="50%" r="50%">'
            . '<stop offset="0" stop-color="' . $color . '" stop-opacity="' . $opacity . '"/>'
            . '<stop offset="0.65" stop-color="' . $color . '" stop-opacity="0"/></radialGradient>';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
            . '<defs><linearGradient id="bg" x1="0" y1="0" x2="0.62" y2="1">'
            . '<stop offset="0" stop-color="' . $c['a'] . '"/><stop offset="0.55" stop-color="' . $c['b'] . '"/><stop offset="1" stop-color="' . $c['c'] . '"/></linearGradient>'
            . $glow('g1', $c['glow1'], $c['glow1_op']) . $glow('g2', $c['glow2'], $c['glow2_op']) . '</defs>'
            . '<rect width="' . $w . '" height="' . $h . '" fill="url(#bg)"/>'
            . '<circle cx="' . ($w + 150 - 280) . '" cy="' . (-180 + 280) . '" r="280" fill="url(#g1)"/>'
            . '<circle cx="' . (-170 + 280) . '" cy="' . ($h + 230 - 280) . '" r="280" fill="url(#g2)"/>'
            . $grid
            . '<rect width="' . $w . '" height="5" fill="#8340b9"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Afgeronde voortgangsbalk als SVG. mPDF negeert blokopmaak binnen tabelcellen, dus een balk met
     * div's werkt daar niet; een afbeelding wel. $fill is 'grad' (merkverloop) of een hex-kleur.
     */
    private function barDataUri(int $percent, float $widthMm, float $heightMm, string $fill = 'grad', string $track = '#e3e6eb'): string
    {
        $w = round($widthMm * 10, 1);
        $h = round($heightMm * 10, 1);
        $r = $h / 2;
        $percent = max(0, min(100, $percent));
        $fillWidth = $percent > 0 ? max($h, round($w * $percent / 100, 1)) : 0;
        $paint = $fill === 'grad' ? 'url(#bg)' : $fill;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
            . '<defs><linearGradient id="bg" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="' . $w . '" y2="0"><stop offset="0" stop-color="#8F39AC"/><stop offset="1" stop-color="#379FD3"/></linearGradient></defs>'
            . '<rect width="' . $w . '" height="' . $h . '" rx="' . $r . '" ry="' . $r . '" fill="' . $track . '"/>'
            . ($fillWidth > 0 ? '<rect width="' . $fillWidth . '" height="' . $h . '" rx="' . $r . '" ry="' . $r . '" fill="' . $paint . '"/>' : '')
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Kleine lijniconen (20×20) als vervanging voor de Dashicons uit de referentie. Met $chip komt het
     * icoon wit op een afgerond merkverloop-vlakje (34×30), zoals bij de categoriescores.
     */
    private function iconDataUri(string $name, string $color = '#8F39AC', bool $chip = false): string
    {
        $s = 'fill="none" stroke="' . ($chip ? '#ffffff' : $color) . '" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"';
        $f = 'fill="' . ($chip ? '#ffffff' : $color) . '"';
        $glyphs = [
            'chart-bar' => '<path ' . $s . ' d="M4 16V9M10 16V4M16 16v-5M2.5 17.5h15"/>',
            'editor-ul' => '<circle cx="4" cy="5" r="1.4" ' . $f . '/><circle cx="4" cy="10" r="1.4" ' . $f . '/><circle cx="4" cy="15" r="1.4" ' . $f . '/><path ' . $s . ' d="M8 5h9M8 10h9M8 15h9"/>',
            'editor-code' => '<path ' . $s . ' d="M6.5 5.5 2 10l4.5 4.5M13.5 5.5 18 10l-4.5 4.5M11.5 3.5l-3 13"/>',
            'format-chat' => '<path ' . $s . ' d="M3 4.5h14v8.5H9l-4 3.5V13H3z"/>',
            'admin-links' => '<path ' . $s . ' d="M8.5 11.5a3.2 3.2 0 0 0 4.6 0l2.6-2.6a3.2 3.2 0 0 0-4.6-4.6l-1.1 1.1M11.5 8.5a3.2 3.2 0 0 0-4.6 0l-2.6 2.6a3.2 3.2 0 0 0 4.6 4.6l1.1-1.1"/>',
            'lightbulb' => '<path ' . $s . ' d="M7 13.5c0-1.6-2.5-3-2.5-6a5.5 5.5 0 0 1 11 0c0 3-2.5 4.4-2.5 6zM7.5 16.5h5M8.5 18.5h3"/>',
            'visibility' => '<path ' . $s . ' d="M1.5 10S4.5 4.5 10 4.5 18.5 10 18.5 10 15.5 15.5 10 15.5 1.5 10 1.5 10z"/><circle cx="10" cy="10" r="2.6" ' . $f . '/>',
            'awards' => '<circle cx="10" cy="7.5" r="5" ' . $s . '/><path ' . $s . ' d="M6.8 11.4 5.5 18l4.5-2.3 4.5 2.3-1.3-6.6"/>',
            'arrow-right-alt' => '<path ' . $s . ' d="M3 10h13M11 5l5 5-5 5"/>',
            'media-document' => '<path ' . $s . ' d="M5 2.5h7l4 4v11H5zM12 2.5v4h4M7.5 10.5h6M7.5 13.5h6"/>',
            'search' => '<circle cx="8.5" cy="8.5" r="5.5" ' . $s . '/><path ' . $s . ' d="M12.5 12.5l5 5"/>',
        ];
        $glyph = $glyphs[$name] ?? $glyphs['chart-bar'];
        if (!$chip) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">' . $glyph . '</svg>';
        } else {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="30" viewBox="0 0 34 30">'
                . '<defs><linearGradient id="ig" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="34" y2="30"><stop offset="0" stop-color="#8F39AC"/><stop offset="1" stop-color="#379FD3"/></linearGradient></defs>'
                . '<rect width="34" height="30" rx="8" ry="8" fill="url(#ig)"/>'
                . '<g transform="translate(9.5 7.5) scale(0.75)">' . $glyph . '</g></svg>';
        }
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Afgeronde label-pill (badge, chip, tag) als SVG. mPDF tekent geen border-radius op inline-elementen en
     * negeert blokopmaak in tabelcellen; als afbeelding past de pill overal. De breedte volgt de tekst.
     *
     * @return array{src: string, w: float, h: float} breedte/hoogte in mm
     */
    private function pill(string $text, string $background, string $color, float $sizePt = 7.9, float $heightMm = 4.9, float $minWidthMm = 0, ?float $radiusMm = null): array
    {
        $paddingMm = min($heightMm * 0.55, 5.8);
        $widthMm = round(max($minWidthMm, $this->textWidthMm($text, $sizePt) + 2 * $paddingMm), 2);
        $w = round($widthMm * 10, 1);
        $h = round($heightMm * 10, 1);
        $r = $radiusMm === null ? $h / 2 : round($radiusMm * 10, 1);
        $fontSize = round($sizePt * 0.3528 * 10, 2); // pt → mm → SVG-eenheden (0.1mm)
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">'
            . '<rect width="' . $w . '" height="' . $h . '" rx="' . $r . '" ry="' . $r . '" fill="' . $background . '"/>'
            . '<text x="' . ($w / 2) . '" y="' . round($h / 2 + $fontSize * 0.35, 2) . '" text-anchor="middle" font-family="outfit" font-weight="bold" font-size="' . $fontSize . '" fill="' . $color . '">'
            . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text></svg>';
        return ['src' => 'data:image/svg+xml;base64,' . base64_encode($svg), 'w' => $widthMm, 'h' => $heightMm];
    }

    private function pillHtml(string $text, string $background, string $color, float $sizePt = 7.9, float $heightMm = 4.9, float $minWidthMm = 0, ?float $radiusMm = null): string
    {
        $pill = $this->pill($text, $background, $color, $sizePt, $heightMm, $minWidthMm, $radiusMm);
        return '<img src="' . $pill['src'] . '" style="width: ' . $pill['w'] . 'mm; height: ' . $pill['h'] . 'mm; vertical-align: middle;" alt="' . $this->e($text) . '">';
    }

    private function textWidthMm(string $text, float $sizePt): float
    {
        if ($this->mpdf !== null) {
            $this->mpdf->SetFont('outfit', 'B', $sizePt);
            return (float)$this->mpdf->GetStringWidth($text);
        }
        return mb_strlen($text) * $sizePt * 0.2; // grove schatting buiten render()
    }

    /** Stapnummer (46×46) wit op een afgerond merkverloop-vlak, voor de volgende stappen. */
    private function stepNumberDataUri(int $number): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="46" height="46" viewBox="0 0 46 46">'
            . '<defs><linearGradient id="sg" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="46" y2="46"><stop offset="0" stop-color="#8F39AC"/><stop offset="1" stop-color="#379FD3"/></linearGradient></defs>'
            . '<rect width="46" height="46" rx="12" ry="12" fill="url(#sg)"/>'
            . '<text x="23" y="30" text-anchor="middle" font-family="outfit" font-weight="bold" font-size="20" fill="#ffffff">' . $number . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Gekleurd monogram-vlakje (30×30) met een of twee letters, voor de platformkaarten. */
    private function monogramDataUri(string $letters, string $color): string
    {
        $size = mb_strlen($letters) > 1 ? 11 : 13;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30">'
            . '<rect width="30" height="30" rx="8" ry="8" fill="' . $color . '"/>'
            . '<text x="15" y="' . (15 + $size * 0.35) . '" text-anchor="middle" font-family="outfit" font-weight="bold" font-size="' . $size . '" fill="#ffffff">' . htmlspecialchars($letters, ENT_XML1, 'UTF-8') . '</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function scoreRingDataUri(int $score, bool $dark): string
    {
        $score = max(0, min(100, $score));
        $track = $dark ? '#3a3853' : '#e7e3ee';
        $text = $dark ? '#ffffff' : '#1a1430';
        $sub = $dark ? '#9b97ab' : '#746e82';
        [$from, $to] = $dark ? ['#C77DFF', '#5BC5F2'] : ['#8F39AC', '#379FD3'];
        // mPDF's SVG-renderer kent geen verloop op stroke: de boog is daarom een gevulde vorm
        // (buitenboog, ronde kop, binnenboog, ronde staart) met het verloop als fill.
        $arc = '';
        if ($score > 0) {
            $sweep = min(359.9, 360 * $score / 100);
            $point = static function (float $r, float $deg): string {
                $rad = deg2rad($deg);
                return round(60 + $r * sin($rad), 3) . ' ' . round(60 - $r * cos($rad), 3);
            };
            $large = $sweep > 180 ? 1 : 0;
            $arc = '<path fill="url(#cg)" d="M' . $point(57.5, 0)
                . ' A57.5 57.5 0 ' . $large . ' 1 ' . $point(57.5, $sweep)
                . ' A5.5 5.5 0 0 1 ' . $point(46.5, $sweep)
                . ' A46.5 46.5 0 ' . $large . ' 0 ' . $point(46.5, 0)
                . ' A5.5 5.5 0 0 1 ' . $point(57.5, 0) . ' Z"/>';
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
            . '<defs><linearGradient id="cg" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="120" y2="120"><stop offset="0" stop-color="' . $from . '"/><stop offset="1" stop-color="' . $to . '"/></linearGradient></defs>'
            . '<circle cx="60" cy="60" r="52" fill="none" stroke="' . $track . '" stroke-width="11"/>'
            . $arc
            . '<text x="60" y="64" text-anchor="middle" font-family="outfit" font-weight="bold" font-size="34" fill="' . $text . '">' . $score . '</text>'
            . '<text x="60" y="80" text-anchor="middle" font-family="outfit" font-size="10" fill="' . $sub . '">/ 100</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function host(string $url): string
    {
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: $url);
        return preg_replace('/^www\./i', '', strtolower($host)) ?: $host;
    }

    private function competitorHosts(array $competitors): string
    {
        return implode(', ', GeoScanReport::displayCompetitorHosts($competitors));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

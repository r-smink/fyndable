<?php
/**
 * Houdt alleen DejaVu Sans over in mPDF's ttfonts-map (±87 MB → ±3 MB).
 * Outfit staat in assets/fonts/outfit/ en wordt via fontDir/fontdata in GeoScanPdf geladen.
 * Draait automatisch na `composer install` / `composer update` (zie composer.json "scripts").
 */
$dir = dirname(__DIR__) . '/vendor/mpdf/mpdf/ttfonts';
if (!is_dir($dir)) {
    exit(0);
}
$keep = ['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf', 'DejaVuSans-Oblique.ttf', 'DejaVuSans-BoldOblique.ttf', 'DejaVuinfo.txt'];
$removed = 0;
foreach (scandir($dir) as $file) {
    $path = $dir . '/' . $file;
    if (is_file($path) && !in_array($file, $keep, true)) {
        unlink($path);
        $removed++;
    }
}
echo "mPDF fonts trimmed: {$removed} files removed\n";

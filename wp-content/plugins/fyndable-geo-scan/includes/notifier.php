<?php

namespace FyndableGeoScan;

/**
 * Admin notification: e-mail to the site team as soon as a visitor submits the scan form.
 * Sent with this site's own wp_mail (so via whatever SMTP fyndable.ai has configured).
 * The portal sends a separate notification when the scan has finished (GeoScanQueue).
 */
class Notifier
{
    public static function enabled(): bool
    {
        return (bool)get_option(FYNDABLE_GEOSCAN_NOTIFY_ENABLED_OPTION, true);
    }

    /** @return string[] valid recipient addresses; falls back to the site admin e-mail */
    public static function recipients(): array
    {
        $raw = (string)get_option(FYNDABLE_GEOSCAN_NOTIFY_EMAIL_OPTION, '');
        $emails = array_values(array_filter(array_map('trim', explode(',', $raw)), 'is_email'));
        return $emails ?: array_values(array_filter([(string)get_option('admin_email')], 'is_email'));
    }

    /** Comma-separated list → only valid addresses, for the settings field. */
    public static function sanitizeRecipients($value): string
    {
        $emails = array_filter(array_map('sanitize_email', array_map('trim', explode(',', (string)$value))), 'is_email');
        return implode(', ', array_unique($emails));
    }

    /**
     * @param array{url: string, keywords: string[], email: string, name: string, company: string, scan_id: int} $lead
     */
    public static function newSubmission(array $lead): void
    {
        if (!self::enabled()) {
            return;
        }
        $to = self::recipients();
        if (!$to) {
            return;
        }

        $host = (string)(wp_parse_url($lead['url'], PHP_URL_HOST) ?: $lead['url']);
        $subject = sprintf(__('[GEO Scan] Nieuwe aanvraag: %s', 'fyndable-geo-scan'), preg_replace('/^www\./i', '', $host));
        $reportUrl = Settings::portalUrl() . '/wp-admin/admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . (int)$lead['scan_id'];

        $lines = [
            __('Er is zojuist een GEO-scan aangevraagd via het formulier op de website.', 'fyndable-geo-scan'),
            '',
            __('Website', 'fyndable-geo-scan') . ':  ' . $lead['url'],
            __('Zoekwoorden', 'fyndable-geo-scan') . ':  ' . implode(', ', $lead['keywords']),
            '',
            __('Naam', 'fyndable-geo-scan') . ':  ' . ($lead['name'] !== '' ? $lead['name'] : '-'),
            __('Bedrijf', 'fyndable-geo-scan') . ':  ' . ($lead['company'] !== '' ? $lead['company'] : '-'),
            __('E-mail', 'fyndable-geo-scan') . ':  ' . $lead['email'],
            __('Toestemming', 'fyndable-geo-scan') . ':  ' . __('ja', 'fyndable-geo-scan') . ' (' . wp_date('Y-m-d H:i') . ')',
            '',
            __('Het rapport staat in het portaal zodra de scan klaar is (meestal binnen enkele minuten):', 'fyndable-geo-scan'),
            $reportUrl,
            '',
            __('Beantwoord deze e-mail om direct contact op te nemen met de aanvrager.', 'fyndable-geo-scan'),
        ];

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $replyName = trim(str_replace(["\r", "\n", '"', '<', '>'], '', $lead['name']));
        $headers[] = 'Reply-To: ' . ($replyName !== '' ? '"' . $replyName . '" ' : '') . '<' . $lead['email'] . '>';

        // A mail failure must never break the visitor's scan request.
        try {
            wp_mail($to, $subject, implode("\n", $lines), $headers);
        } catch (\Throwable $e) {
            error_log('[Fyndable GEO Scan] Admin notification failed: ' . $e->getMessage());
        }
    }
}

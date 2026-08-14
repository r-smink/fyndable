<?php

namespace SSEOAISaaS;

/**
 * Invoice Manager
 *
 * Handles invoice generation, HTML/PDF rendering, and management.
 * Works with the sseo_ai_invoices table created by WebhookHandler.
 *
 * Rendering is driven by the configurable invoice template stored in
 * the sseo_ai_saas_invoice_template option group (managed on the
 * Bookkeeping admin page, tab 3).
 */
class InvoiceManager
{
    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    public function register(): void
    {
        // Hook into invoice creation to generate PDF
        add_action('sseo_ai_invoice_created', [$this, 'onInvoiceCreated'], 10, 3);
    }

    /**
     * Called when a new invoice record is created.
     * Generates an HTML invoice that can be downloaded.
     */
    public function onInvoiceCreated(string $tenantKey, int $invoiceId, string $invoiceNumber): void
    {
        // For now, we don't generate a PDF file automatically.
        // The portal renders invoices as HTML which can be printed to PDF.
        // This hook is here for future PDF generation integration.
    }

    /**
     * Get invoices for a tenant.
     */
    public function getInvoices(string $tenantKey, int $limit = 50, int $offset = 0): array
    {
        return $this->tenants->getInvoicesByTenantKey($tenantKey, $limit, $offset);
    }

    /**
     * Get a single invoice by ID, verifying tenant ownership.
     */
    public function getInvoice(int $invoiceId, ?string $tenantKey = null): ?array
    {
        $invoice = $this->tenants->getInvoiceById($invoiceId);
        if (!$invoice) {
            return null;
        }

        if ($tenantKey !== null && $invoice['tenant_key'] !== $tenantKey) {
            return null;
        }

        return $invoice;
    }

    /**
     * Build the placeholder values used by the invoice template.
     *
     * @param array $invoice   Row from sseo_ai_invoices.
     * @param array|null $tenant Optional tenant row; loaded if not provided.
     */
    private function buildPlaceholders(array $invoice, ?array $tenant = null): array
    {
        if ($tenant === null) {
            $tenant = $this->tenants->getTenant($invoice['tenant_key']) ?: [];
        }

        // Company identity — fall back to white-label options then to defaults.
        $wlEnabled = (bool) get_option('sseo_ai_saas_wl_enabled', false);
        $companyName = get_option('sseo_ai_saas_inv_company_name', '');
        if ($companyName === '') {
            $companyName = $wlEnabled ? (get_option('sseo_ai_saas_wl_company_name', '') ?: 'Fyndable') : 'Fyndable';
        }
        $companyAddress = get_option('sseo_ai_saas_inv_company_address', '');
        if ($companyAddress === '') {
            $companyAddress = get_option('sseo_ai_saas_wl_company_address', '');
        }
        $companyEmail = get_option('sseo_ai_saas_inv_company_email', '');
        if ($companyEmail === '') {
            $companyEmail = get_option('admin_email', '');
        }

        $amount = (float) $invoice['amount'];
        $currency = $invoice['currency'] ?? 'EUR';
        $vatRate = (float) get_option('sseo_ai_saas_vat_rate', 21);
        $vatAmount = $amount * ($vatRate / (100 + $vatRate));
        $subtotal = $amount - $vatAmount;

        $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        $symbol = $symbols[strtoupper($currency)] ?? ($currency . ' ');

        $invoiceDate = !empty($invoice['created_at']) ? date_i18n(get_option('date_format'), strtotime($invoice['created_at'])) : '';
        $paidDate = !empty($invoice['paid_at']) ? date_i18n(get_option('date_format'), strtotime($invoice['paid_at'])) : '';

        return [
            'company_name'      => $companyName,
            'company_address'   => $companyAddress,
            'company_vat'       => get_option('sseo_ai_saas_inv_company_vat', ''),
            'company_kvk'       => get_option('sseo_ai_saas_inv_company_kvk', ''),
            'company_iban'      => get_option('sseo_ai_saas_inv_company_iban', ''),
            'company_email'     => $companyEmail,
            'company_website'   => get_option('sseo_ai_saas_inv_company_website', ''),
            'invoice_number'    => $invoice['invoice_number'] ?? '',
            'invoice_date'      => $invoiceDate,
            'paid_date'         => $paidDate,
            'customer_name'     => $tenant['name'] ?? '',
            'customer_email'    => $tenant['email'] ?? '',
            'customer_domain'   => $tenant['domain'] ?? '',
            'tier'              => ucfirst($invoice['tier'] ?? ''),
            'billing_interval'  => ucfirst($invoice['billing_interval'] ?? 'month'),
            'description'       => $invoice['description'] ?? 'Fyndable SmartSEO Subscription',
            'subtotal'          => $symbol . number_format($subtotal, 2),
            'vat_rate'          => $vatRate,
            'vat_amount'        => $symbol . number_format($vatAmount, 2),
            'total'             => $symbol . number_format($amount, 2),
            'currency_symbol'   => $symbol,
            'amount_raw'        => $amount,
            'subtotal_raw'      => $subtotal,
            'vat_amount_raw'    => $vatAmount,
            'status'            => $invoice['status'] ?? 'paid',
            'footer_text'       => get_option('sseo_ai_saas_inv_footer_text', 'Bedankt voor uw vertrouwen.'),
            // Labels
            'label_invoice'     => get_option('sseo_ai_saas_inv_label_invoice', 'Factuur'),
            'label_from'        => get_option('sseo_ai_saas_inv_label_from', 'Van'),
            'label_to'          => get_option('sseo_ai_saas_inv_label_to', 'Factuur aan'),
            'label_description' => get_option('sseo_ai_saas_inv_label_description', 'Omschrijving'),
            'label_period'      => get_option('sseo_ai_saas_inv_label_period', 'Periode'),
            'label_amount'      => get_option('sseo_ai_saas_inv_label_amount', 'Bedrag'),
            'label_subtotal'    => get_option('sseo_ai_saas_inv_label_subtotal', 'Subtotaal'),
            'label_vat'         => get_option('sseo_ai_saas_inv_label_vat', 'BTW'),
            'label_total'       => get_option('sseo_ai_saas_inv_label_total', 'Totaal'),
            'label_paid_on'     => get_option('sseo_ai_saas_inv_label_paid_on', 'Betaald op'),
        ];
    }

    /**
     * Resolve an attachment ID to a URL (or empty string).
     */
    private function attachmentUrl(int $attachmentId): string
    {
        if ($attachmentId <= 0) {
            return '';
        }
        $url = wp_get_attachment_url($attachmentId);
        return $url ?: '';
    }

    /**
     * Replace {{placeholder}} tokens in a string with values from the map.
     */
    private function fillPlaceholders(string $template, array $values): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($values) {
            $key = $m[1];
            return isset($values[$key]) ? (string) $values[$key] : '';
        }, $template);
    }

    /**
     * Render an invoice as HTML (for display in modal or print-to-PDF).
     */
    public function renderInvoiceHtml(array $invoice, ?array $tenant = null): string
    {
        $p = $this->buildPlaceholders($invoice, $tenant);

        $logoUrl = $this->attachmentUrl((int) get_option('sseo_ai_saas_inv_logo_id', 0));
        $bgUrl = $this->attachmentUrl((int) get_option('sseo_ai_saas_inv_bg_id', 0));
        $bgMode = get_option('sseo_ai_saas_inv_bg_mode', 'none');
        $headerColor = get_option('sseo_ai_saas_inv_header_color', '');
        $accentColor = get_option('sseo_ai_saas_inv_accent_color', '#379fd3');
        $textColor = get_option('sseo_ai_saas_inv_text_color', '#111827');

        // Default header gradient uses the accent color when no explicit header color set.
        $headerStyle = $headerColor !== ''
            ? 'background:' . esc_attr($headerColor) . ';'
            : 'background:linear-gradient(135deg, ' . esc_attr($accentColor) . ' 0%, #8f39ac 100%);';

        $bodyBgStyle = '';
        if ($bgMode === 'cover' && $bgUrl !== '') {
            $bodyBgStyle = 'background:url(' . esc_url($bgUrl) . ') center/cover no-repeat fixed;';
        } elseif ($bgMode === 'repeat' && $bgUrl !== '') {
            $bodyBgStyle = 'background:url(' . esc_url($bgUrl) . ') repeat;';
        }

        $logoHtml = $logoUrl !== ''
            ? '<img src="' . esc_url($logoUrl) . '" alt="' . esc_attr($p['company_name']) . '" style="max-height:48px;max-width:220px;display:block;">'
            : '<h1>' . esc_html($p['company_name']) . '</h1>';

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="nl">
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html($p['label_invoice']); ?> <?php echo esc_html($p['invoice_number']); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: <?php echo esc_attr($textColor); ?>; background: #f9fafb; padding: 40px 20px; <?php echo $bodyBgStyle; ?> }
                .invoice { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; }
                .invoice-header { <?php echo $headerStyle; ?> color: #fff; padding: 32px 40px; display: flex; justify-content: space-between; align-items: center; }
                .invoice-header h1 { font-size: 28px; font-weight: 700; }
                .invoice-header .invoice-meta { text-align: right; font-size: 14px; opacity: 0.95; }
                .invoice-body { padding: 40px; }
                .invoice-section h3 { font-size: 12px;  letter-spacing: 0.5px; color: #6b7280; margin-bottom: 8px; }
                .invoice-from, .invoice-to { display: flex; gap: 60px; margin-bottom: 32px; }
                .invoice-from > div, .invoice-to > div { flex: 1; }
                .invoice-from p, .invoice-to p { font-size: 14px; line-height: 1.6; color: #374151; }
                .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
                .invoice-table th { text-align: left; font-size: 12px;  letter-spacing: 0.5px; color: #6b7280; padding: 12px 16px; border-bottom: 2px solid #e5e7eb; }
                .invoice-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
                .invoice-table .amount { text-align: right; font-variant-numeric: tabular-nums; }
                .invoice-totals { margin-left: auto; width: 280px; }
                .invoice-totals .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
                .invoice-totals .row.total { border-top: 2px solid #e5e7eb; margin-top: 8px; padding-top: 16px; font-weight: 700; font-size: 18px; }
                .invoice-footer { padding: 24px 40px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;  letter-spacing: 0.5px; }
                .status-paid { background: #d1fae5; color: #065f46; }
                .status-pending { background: #fef3c7; color: #92400e; }
                .status-failed { background: #fee2e2; color: #991b1b; }
                .status-refunded { background: #e0e7ff; color: #3730a3; }
                .company-meta { font-size: 12px; color: #6b7280; margin-top: 6px; line-height: 1.5; }
                @media print {
                    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                    body { background: #fff; padding: 0; }
                    .invoice { box-shadow: none; }
                    .no-print { display: none; }
                    .invoice-header { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                    .status-paid, .status-pending, .status-failed, .status-refunded { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                }
            </style>
        </head>
        <body>
            <div class="invoice">
                <div class="invoice-header">
                    <div class="invoice-brand"><?php echo $logoHtml; ?></div>
                    <div class="invoice-meta">
                        <div><strong><?php echo esc_html($p['label_invoice']); ?></strong> <?php echo esc_html($p['invoice_number']); ?></div>
                        <div><?php echo esc_html($p['invoice_date']); ?></div>
                        <div style="margin-top:8px;">
                            <span class="status-badge status-<?php echo esc_attr($p['status']); ?>">
                                <?php echo esc_html(ucfirst($p['status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="invoice-body">
                    <div class="invoice-from">
                        <div class="invoice-section">
                            <h3><?php echo esc_html($p['label_from']); ?></h3>
                            <p><strong><?php echo esc_html($p['company_name']); ?></strong><br>
                            <?php if ($p['company_address'] !== ''): ?><?php echo esc_html($p['company_address']); ?><br><?php endif; ?>
                            <?php if ($p['company_email'] !== ''): ?><?php echo esc_html($p['company_email']); ?><br><?php endif; ?>
                            <?php if ($p['company_website'] !== ''): ?><?php echo esc_html($p['company_website']); ?><?php endif; ?>
                            </p>
                            <?php if ($p['company_vat'] !== '' || $p['company_kvk'] !== '' || $p['company_iban'] !== ''): ?>
                            <p class="company-meta">
                                <?php if ($p['company_vat'] !== ''): ?>BTW: <?php echo esc_html($p['company_vat']); ?><br><?php endif; ?>
                                <?php if ($p['company_kvk'] !== ''): ?>KvK: <?php echo esc_html($p['company_kvk']); ?><br><?php endif; ?>
                                <?php if ($p['company_iban'] !== ''): ?>IBAN: <?php echo esc_html($p['company_iban']); ?><?php endif; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="invoice-section">
                            <h3><?php echo esc_html($p['label_to']); ?></h3>
                            <p><strong><?php echo esc_html($p['customer_name']); ?></strong><br>
                            <?php echo esc_html($p['customer_email']); ?><br>
                            <?php echo esc_html($p['customer_domain']); ?></p>
                        </div>
                    </div>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html($p['label_description']); ?></th>
                                <th><?php echo esc_html($p['label_period']); ?></th>
                                <th class="amount"><?php echo esc_html($p['label_amount']); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo esc_html($p['description']); ?></td>
                                <td><?php echo esc_html($p['billing_interval']); ?></td>
                                <td class="amount"><?php echo esc_html($p['total']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="invoice-totals">
                        <div class="row">
                            <span><?php echo esc_html($p['label_subtotal']); ?></span>
                            <span><?php echo esc_html($p['subtotal']); ?></span>
                        </div>
                        <div class="row">
                            <span><?php echo esc_html($p['label_vat']); ?> (<?php echo esc_html($p['vat_rate']); ?>%)</span>
                            <span><?php echo esc_html($p['vat_amount']); ?></span>
                        </div>
                        <div class="row total">
                            <span><?php echo esc_html($p['label_total']); ?></span>
                            <span><?php echo esc_html($p['total']); ?></span>
                        </div>
                    </div>
                    <?php if ($p['paid_date'] !== ''): ?>
                    <p style="margin-top:24px;font-size:14px;color:#065f46;">
                        <?php echo esc_html($p['label_paid_on']); ?> <?php echo esc_html($p['paid_date']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="invoice-footer">
                    <?php echo esc_html($p['company_name']); ?> — <?php echo esc_html($p['footer_text']); ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render an invoice as a full standalone HTML document that auto-prints.
     * Used by the customer portal "Download PDF" button (browser print-to-PDF).
     */
    public function renderInvoicePrintHtml(array $invoice, ?array $tenant = null): string
    {
        $html = $this->renderInvoiceHtml($invoice, $tenant);
        // Inject auto-print script right before </body>.
        $printScript = "<script>window.onload=function(){setTimeout(function(){window.print();},250);};</script>";
        return str_replace('</body>', $printScript . '</body>', $html);
    }

    /**
     * Render a preview invoice using dummy data (for the template editor).
     */
    public function renderPreviewHtml(): string
    {
        $dummy = [
            'tenant_key'       => 'preview',
            'invoice_number'   => get_option('sseo_ai_saas_inv_prefix', 'FYND-') . date('Y') . '-0001',
            'amount'           => SaaSSettings::tierPrice('professional'),
            'currency'         => get_option('sseo_ai_saas_currency', 'EUR'),
            'status'           => 'paid',
            'tier'             => 'professional',
            'billing_interval' => 'month',
            'description'      => 'Fyndable SmartSEO Professional - month subscription',
            'created_at'       => current_time('mysql'),
            'paid_at'          => current_time('mysql'),
        ];
        $dummyTenant = [
            'name'   => 'Voorbeeld Klant B.V.',
            'email'  => 'info@voorbeeldklant.nl',
            'domain' => 'voorbeeldklant.nl',
        ];
        return $this->renderInvoiceHtml($dummy, $dummyTenant);
    }
}

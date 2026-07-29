<?php

namespace SSEOAISaaS;

/**
 * Invoice Manager
 *
 * Handles invoice generation, PDF rendering, and management.
 * Works with the sseo_ai_invoices table created by WebhookHandler.
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
     * Render an invoice as HTML (for display or print-to-PDF).
     */
    public function renderInvoiceHtml(array $invoice): string
    {
        $tenant = $this->tenants->getTenant($invoice['tenant_key']);
        $companyName = 'Fyndable';
        $enabled = get_option('sseo_ai_saas_wl_enabled', false);
        if ($enabled) {
            $companyName = get_option('sseo_ai_saas_wl_company_name', '') ?: $companyName;
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

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="nl">
        <head>
            <meta charset="utf-8">
            <title>Factuur <?php echo esc_html($invoice['invoice_number']); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #111827; background: #f9fafb; padding: 40px 20px; }
                .invoice { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; }
                .invoice-header { background: linear-gradient(135deg, #379fd3 0%, #8f39ac 100%); color: #fff; padding: 32px 40px; display: flex; justify-content: space-between; align-items: center; }
                .invoice-header h1 { font-size: 28px; font-weight: 700; }
                .invoice-header .invoice-meta { text-align: right; font-size: 14px; opacity: 0.95; }
                .invoice-body { padding: 40px; }
                .invoice-section { margin-bottom: 32px; }
                .invoice-section h3 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 8px; }
                .invoice-from, .invoice-to { display: flex; gap: 60px; margin-bottom: 32px; }
                .invoice-from > div, .invoice-to > div { flex: 1; }
                .invoice-from p, .invoice-to p { font-size: 14px; line-height: 1.6; color: #374151; }
                .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
                .invoice-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; padding: 12px 16px; border-bottom: 2px solid #e5e7eb; }
                .invoice-table td { padding: 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
                .invoice-table .amount { text-align: right; font-variant-numeric: tabular-nums; }
                .invoice-totals { margin-left: auto; width: 280px; }
                .invoice-totals .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
                .invoice-totals .row.total { border-top: 2px solid #e5e7eb; margin-top: 8px; padding-top: 16px; font-weight: 700; font-size: 18px; }
                .invoice-footer { padding: 24px 40px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #9ca3af; text-align: center; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
                .status-paid { background: #d1fae5; color: #065f46; }
                .status-pending { background: #fef3c7; color: #92400e; }
                .status-failed { background: #fee2e2; color: #991b1b; }
                @media print { body { background: #fff; padding: 0; } .invoice { box-shadow: none; } }
            </style>
        </head>
        <body>
            <div class="invoice">
                <div class="invoice-header">
                    <h1><?php echo esc_html($companyName); ?></h1>
                    <div class="invoice-meta">
                        <div><strong>Factuur</strong> <?php echo esc_html($invoice['invoice_number']); ?></div>
                        <div><?php echo esc_html($invoiceDate); ?></div>
                        <div style="margin-top:8px;">
                            <span class="status-badge status-<?php echo esc_attr($invoice['status']); ?>">
                                <?php echo esc_html(ucfirst($invoice['status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="invoice-body">
                    <div class="invoice-from">
                        <div>
                            <h3>Van</h3>
                            <p><strong><?php echo esc_html($companyName); ?></strong><br>
                            <?php echo esc_html(get_option('sseo_ai_saas_wl_company_address', '')); ?><br>
                            <?php echo esc_html(get_option('admin_email', '')); ?></p>
                        </div>
                        <div>
                            <h3>Factuur aan</h3>
                            <p><strong><?php echo esc_html($tenant['name'] ?? ''); ?></strong><br>
                            <?php echo esc_html($tenant['email'] ?? ''); ?><br>
                            <?php echo esc_html($tenant['domain'] ?? ''); ?></p>
                        </div>
                    </div>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Omschrijving</th>
                                <th>Periode</th>
                                <th class="amount">Bedrag</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo esc_html($invoice['description'] ?? 'Fyndable SmartSEO Subscription'); ?></td>
                                <td><?php echo esc_html(ucfirst($invoice['billing_interval'] ?? 'month')); ?></td>
                                <td class="amount"><?php echo esc_html($symbol . number_format($amount, 2)); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="invoice-totals">
                        <div class="row">
                            <span>Subtotaal</span>
                            <span><?php echo esc_html($symbol . number_format($subtotal, 2)); ?></span>
                        </div>
                        <div class="row">
                            <span>BTW (<?php echo esc_html($vatRate); ?>%)</span>
                            <span><?php echo esc_html($symbol . number_format($vatAmount, 2)); ?></span>
                        </div>
                        <div class="row total">
                            <span>Totaal</span>
                            <span><?php echo esc_html($symbol . number_format($amount, 2)); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($paidDate)): ?>
                    <p style="margin-top:24px;font-size:14px;color:#065f46;">
                        Betaald op <?php echo esc_html($paidDate); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="invoice-footer">
                    <?php echo esc_html($companyName); ?> — Bedankt voor uw vertrouwen.
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

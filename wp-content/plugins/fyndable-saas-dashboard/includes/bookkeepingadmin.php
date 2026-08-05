<?php

namespace SSEOAISaaS;

/**
 * Bookkeeping Admin
 *
 * Admin page with three tabs:
 *  1. Invoices   — list of all invoices with filters, per-row PDF (print) and CSV batch export.
 *  2. Profit     — revenue minus AI/GEO costs per tenant and totals, for a selected period.
 *  3. Template   — configurable invoice template (logo, background, colors, labels, company details).
 *
 * The invoice template is consumed by InvoiceManager::renderInvoiceHtml for both the
 * customer portal and the admin view/export.
 */
class BookkeepingAdmin
{
    private TenantRepository $tenants;
    private InvoiceManager $invoices;
    private string $pluginFile;

    public function __construct(string $pluginFile, TenantRepository $tenants, InvoiceManager $invoices)
    {
        $this->pluginFile = $pluginFile;
        $this->tenants = $tenants;
        $this->invoices = $invoices;
    }

    public function register(): void
    {
        // Menu is registered via WhiteLabelAdmin::addMenu.
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_sseo_ai_bookkeeping_export_csv', [$this, 'handleExportCsv']);
        add_action('admin_post_sseo_ai_bookkeeping_view_invoice', [$this, 'handleViewInvoice']);
        add_action('wp_ajax_bookkeeping_preview_invoice', [$this, 'handlePreviewInvoice']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('Bookkeeping', 'sseo-ai-saas'),
            __('Bookkeeping', 'sseo-ai-saas'),
            'manage_options',
            'sseo-ai-bookkeeping',
            [$this, 'renderPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'sseo-ai-billing') === false) {
            return;
        }
        wp_enqueue_style(
            'sseo-ai-bookkeeping',
            plugins_url('assets/bookkeeping-admin.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/bookkeeping-admin.css')
        );
        wp_enqueue_script(
            'sseo-ai-bookkeeping',
            plugins_url('assets/bookkeeping-admin.js', $this->pluginFile),
            ['jquery'],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/bookkeeping-admin.js'),
            true
        );
        wp_enqueue_media();
    }

    /**
     * Render the page with tab navigation.
     */
    public function renderPage(): void
    {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'invoices';
        $tabs = ['invoices' => __('Invoices', 'sseo-ai-saas'), 'profit' => __('Profit', 'sseo-ai-saas'), 'template' => __('Invoice Template', 'sseo-ai-saas')];
        $base = admin_url('admin.php?page=sseo-ai-billing');
        ?>
        <div class="wrap sseo-ai-bookkeeping-wrap">
            <h1><?php esc_html_e('Bookkeeping', 'sseo-ai-saas'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?php echo esc_url(add_query_arg('tab', $key, $base)); ?>"
                       class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php
            switch ($tab) {
                case 'profit':
                    $this->renderProfitTab();
                    break;
                case 'template':
                    $this->renderTemplateTab();
                    break;
                case 'invoices':
                default:
                    $this->renderInvoicesTab();
                    break;
            }
            ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab 1: Invoices
    // -------------------------------------------------------------------------

    private function renderInvoicesTab(): void
    {
        $filters = [
            'status'   => isset($_GET['f_status']) ? sanitize_key($_GET['f_status']) : '',
            'provider' => isset($_GET['f_provider']) ? sanitize_key($_GET['f_provider']) : '',
            'period'   => isset($_GET['f_period']) ? sanitize_text_field($_GET['f_period']) : '',
            'tenant'   => isset($_GET['f_tenant']) ? sanitize_text_field($_GET['f_tenant']) : '',
        ];

        $perPage = 50;
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $invoices = $this->tenants->getAllInvoices($filters, $perPage, $offset);
        $total = $this->tenants->countAllInvoices($filters);
        $pages = max(1, (int) ceil($total / $perPage));

        $csvUrl = wp_nonce_url(
            add_query_arg(array_filter([
                'action'    => 'sseo_ai_bookkeeping_export_csv',
                'f_status'   => $filters['status'],
                'f_provider' => $filters['provider'],
                'f_period'   => $filters['period'],
                'f_tenant'   => $filters['tenant'],
            ]), admin_url('admin-post.php')),
            'bookkeeping_export_csv'
        );
        ?>
        <form method="get" class="sseo-ai-bk-filters">
            <input type="hidden" name="page" value="sseo-ai-bookkeeping">
            <input type="hidden" name="tab" value="invoices">
            <select name="f_status">
                <option value=""><?php esc_html_e('All statuses', 'sseo-ai-saas'); ?></option>
                <?php foreach (['paid', 'pending', 'failed', 'refunded'] as $s): ?>
                    <option value="<?php echo esc_attr($s); ?>" <?php selected($filters['status'], $s); ?>><?php echo esc_html(ucfirst($s)); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="f_provider">
                <option value=""><?php esc_html_e('All providers', 'sseo-ai-saas'); ?></option>
                <option value="stripe" <?php selected($filters['provider'], 'stripe'); ?>>Stripe</option>
                <option value="mollie" <?php selected($filters['provider'], 'mollie'); ?>>Mollie</option>
            </select>
            <input type="month" name="f_period" value="<?php echo esc_attr($filters['period']); ?>" placeholder="YYYY-MM">
            <input type="text" name="f_tenant" value="<?php echo esc_attr($filters['tenant']); ?>" placeholder="<?php esc_attr_e('Search tenant...', 'sseo-ai-saas'); ?>">
            <button class="button" type="submit"><?php esc_html_e('Filter', 'sseo-ai-saas'); ?></button>
            <a href="<?php echo esc_url($csvUrl); ?>" class="button button-primary"><?php esc_html_e('Export CSV', 'sseo-ai-saas'); ?></a>
        </form>

        <table class="wp-list-table widefat fixed striped sseo-ai-bk-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Invoice #', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Customer', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Period', 'sseo-ai-saas'); ?></th>
                    <th class="num"><?php esc_html_e('Amount', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Provider', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="9"><?php esc_html_e('No invoices found.', 'sseo-ai-saas'); ?></td></tr>
                <?php else: foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo esc_html($inv['invoice_number']); ?></strong></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($inv['created_at']))); ?></td>
                        <td>
                            <?php echo esc_html($inv['tenant_name'] ?: $inv['tenant_key']); ?>
                            <?php if (!empty($inv['tenant_email'])): ?>
                                <div class="sseo-ai-bk-sub"><?php echo esc_html($inv['tenant_email']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(ucfirst($inv['tier'] ?? '')); ?></td>
                        <td><?php echo esc_html(ucfirst($inv['billing_interval'] ?? '')); ?></td>
                        <td class="num"><?php echo esc_html(($inv['currency'] ?? 'EUR') . ' ' . number_format((float) $inv['amount'], 2)); ?></td>
                        <td><span class="sseo-ai-bk-status sseo-ai-bk-status-<?php echo esc_attr($inv['status']); ?>"><?php echo esc_html(ucfirst($inv['status'])); ?></span></td>
                        <td><?php echo esc_html(ucfirst($inv['provider'] ?? '')); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=sseo_ai_bookkeeping_view_invoice&id=' . (int) $inv['id']), 'bookkeeping_view_invoice_' . (int) $inv['id'])); ?>"
                               target="_blank" class="button button-small"><?php esc_html_e('View / PDF', 'sseo-ai-saas'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <?php if ($pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('paged', '%#%'),
                        'format'    => '',
                        'current'   => $page,
                        'total'     => $pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                    ]);
                    ?>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Stream a CSV of all invoices matching the current filters.
     */
    public function handleExportCsv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export invoices.', 'sseo-ai-saas'));
        }
        check_admin_referer('bookkeeping_export_csv');

        $filters = [
            'status'   => isset($_GET['f_status']) ? sanitize_key($_GET['f_status']) : '',
            'provider' => isset($_GET['f_provider']) ? sanitize_key($_GET['f_provider']) : '',
            'period'   => isset($_GET['f_period']) ? sanitize_text_field($_GET['f_period']) : '',
            'tenant'   => isset($_GET['f_tenant']) ? sanitize_text_field($_GET['f_tenant']) : '',
        ];

        $invoices = $this->tenants->getAllInvoices($filters, 100000, 0);
        $vatRate = (float) get_option('sseo_ai_saas_vat_rate', 21);

        $rows = [];
        $header = [
            'invoice_number', 'created_at', 'paid_at', 'tenant_key', 'tenant_name', 'tenant_email',
            'tier', 'billing_interval', 'description', 'provider', 'external_id', 'status',
            'currency', 'subtotal', 'vat_rate', 'vat_amount', 'total',
        ];
        $rows[] = $header;

        foreach ($invoices as $inv) {
            $amount = (float) $inv['amount'];
            $vatAmount = $amount * ($vatRate / (100 + $vatRate));
            $subtotal = $amount - $vatAmount;
            $rows[] = [
                $inv['invoice_number'],
                $inv['created_at'],
                $inv['paid_at'] ?? '',
                $inv['tenant_key'],
                $inv['tenant_name'] ?? '',
                $inv['tenant_email'] ?? '',
                $inv['tier'] ?? '',
                $inv['billing_interval'] ?? '',
                $inv['description'] ?? '',
                $inv['provider'] ?? '',
                $inv['external_id'] ?? '',
                $inv['status'] ?? '',
                $inv['currency'] ?? 'EUR',
                number_format($subtotal, 2, '.', ''),
                $vatRate,
                number_format($vatAmount, 2, '.', ''),
                number_format($amount, 2, '.', ''),
            ];
        }

        $fh = fopen('php://output', 'w');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=invoices-' . date('Y-m-d') . '.csv');
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
        exit;
    }

    /**
     * Render a single invoice as a full HTML document for admin view / print-to-PDF.
     */
    public function handleViewInvoice(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to view invoices.', 'sseo-ai-saas'));
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('bookkeeping_view_invoice_' . $id);

        $invoice = $this->tenants->getInvoiceWithTenant($id);
        if (!$invoice) {
            wp_die(__('Invoice not found.', 'sseo-ai-saas'));
        }

        $tenant = [
            'name'   => $invoice['tenant_name'] ?? '',
            'email'  => $invoice['tenant_email'] ?? '',
            'domain' => $invoice['tenant_domain'] ?? '',
        ];
        echo $this->invoices->renderInvoicePrintHtml($invoice, $tenant);
        exit;
    }

    // -------------------------------------------------------------------------
    // Tab 2: Profit
    // -------------------------------------------------------------------------

    private function renderProfitTab(): void
    {
        $today = current_time('Y-m-d');
        $defaultFrom = gmdate('Y-m-01', strtotime($today));
        $defaultTo = $today;

        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : $defaultFrom;
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : $defaultTo;
        $rate = (float) get_option('sseo_ai_saas_inv_cost_usd_eur_rate', 0.92);

        $revenueRows = $this->tenants->getRevenueByTenant($from, $to);
        $costs = $this->tenants->getCostByTenant($from, $to);
        $geoTotal = $this->tenants->getTotalGeoScanCost($from, $to);

        // Build a unified per-tenant table keyed by tenant_key.
        $byKey = [];
        foreach ($revenueRows as $r) {
            $byKey[$r['tenant_key']] = [
                'name'    => $r['tenant_name'] ?? '',
                'email'   => $r['tenant_email'] ?? '',
                'tier'    => $r['tier'] ?? '',
                'revenue' => (float) $r['revenue'],
                'cost'    => 0.0,
            ];
        }

        // Attach costs by looking up tenant_id -> tenant_key.
        foreach ($costs as $tenantId => $costUsd) {
            $tenant = $this->tenants->getTenantById($tenantId);
            if (!$tenant) {
                continue;
            }
            $key = $tenant['tenant_key'];
            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'name'    => $tenant['name'] ?? '',
                    'email'   => $tenant['email'] ?? '',
                    'tier'    => $tenant['tier'] ?? '',
                    'revenue' => 0.0,
                    'cost'    => 0.0,
                ];
            }
            // Convert USD cost to EUR using configured rate.
            $byKey[$key]['cost'] += (float) $costUsd * $rate;
        }

        // Sort by profit descending.
        uasort($byKey, function ($a, $b) {
            $pa = $a['revenue'] - $a['cost'];
            $pb = $b['revenue'] - $b['cost'];
            return $pb <=> $pa;
        });

        $totalRevenue = 0.0;
        $totalCost = 0.0;
        foreach ($byKey as $row) {
            $totalRevenue += $row['revenue'];
            $totalCost += $row['cost'];
        }
        $totalProfit = $totalRevenue - $totalCost;
        $totalMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue * 100) : 0;
        $geoTotalEur = $geoTotal * $rate;
        ?>
        <form method="get" class="sseo-ai-bk-filters">
            <input type="hidden" name="page" value="sseo-ai-bookkeeping">
            <input type="hidden" name="tab" value="profit">
            <label><?php esc_html_e('From:', 'sseo-ai-saas'); ?> <input type="date" name="from" value="<?php echo esc_attr($from); ?>"></label>
            <label><?php esc_html_e('To:', 'sseo-ai-saas'); ?> <input type="date" name="to" value="<?php echo esc_attr($to); ?>"></label>
            <button class="button" type="submit"><?php esc_html_e('Update', 'sseo-ai-saas'); ?></button>
        </form>

        <div class="sseo-ai-bk-stats">
            <div class="sseo-ai-bk-stat">
                <div class="label"><?php esc_html_e('Total Revenue', 'sseo-ai-saas'); ?></div>
                <div class="value"><?php echo esc_html('&euro; ' . number_format($totalRevenue, 2)); ?></div>
            </div>
            <div class="sseo-ai-bk-stat">
                <div class="label"><?php esc_html_e('Total Cost (AI)', 'sseo-ai-saas'); ?></div>
                <div class="value"><?php echo esc_html('&euro; ' . number_format($totalCost, 2)); ?></div>
            </div>
            <div class="sseo-ai-bk-stat">
                <div class="label"><?php esc_html_e('Total Profit', 'sseo-ai-saas'); ?></div>
                <div class="value <?php echo $totalProfit >= 0 ? 'pos' : 'neg'; ?>"><?php echo esc_html('&euro; ' . number_format($totalProfit, 2)); ?></div>
            </div>
            <div class="sseo-ai-bk-stat">
                <div class="label"><?php esc_html_e('Margin', 'sseo-ai-saas'); ?></div>
                <div class="value"><?php echo esc_html(number_format($totalMargin, 1)); ?>%</div>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped sseo-ai-bk-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Customer', 'sseo-ai-saas'); ?></th>
                    <th><?php esc_html_e('Tier', 'sseo-ai-saas'); ?></th>
                    <th class="num"><?php esc_html_e('Revenue', 'sseo-ai-saas'); ?></th>
                    <th class="num"><?php esc_html_e('Cost', 'sseo-ai-saas'); ?></th>
                    <th class="num"><?php esc_html_e('Profit', 'sseo-ai-saas'); ?></th>
                    <th class="num"><?php esc_html_e('Margin', 'sseo-ai-saas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($byKey)): ?>
                    <tr><td colspan="6"><?php esc_html_e('No data for the selected period.', 'sseo-ai-saas'); ?></td></tr>
                <?php else: foreach ($byKey as $row):
                    $profit = $row['revenue'] - $row['cost'];
                    $margin = $row['revenue'] > 0 ? ($profit / $row['revenue'] * 100) : 0;
                ?>
                    <tr>
                        <td>
                            <?php echo esc_html($row['name'] ?: __('(unknown)', 'sseo-ai-saas')); ?>
                            <?php if (!empty($row['email'])): ?>
                                <div class="sseo-ai-bk-sub"><?php echo esc_html($row['email']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(ucfirst($row['tier'])); ?></td>
                        <td class="num"><?php echo esc_html('&euro; ' . number_format($row['revenue'], 2)); ?></td>
                        <td class="num"><?php echo esc_html('&euro; ' . number_format($row['cost'], 2)); ?></td>
                        <td class="num <?php echo $profit >= 0 ? 'pos' : 'neg'; ?>"><?php echo esc_html('&euro; ' . number_format($profit, 2)); ?></td>
                        <td class="num"><?php echo esc_html(number_format($margin, 1)); ?>%</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><strong><?php esc_html_e('Total', 'sseo-ai-saas'); ?></strong></td>
                    <td class="num"><strong><?php echo esc_html('&euro; ' . number_format($totalRevenue, 2)); ?></strong></td>
                    <td class="num"><strong><?php echo esc_html('&euro; ' . number_format($totalCost, 2)); ?></strong></td>
                    <td class="num <?php echo $totalProfit >= 0 ? 'pos' : 'neg'; ?>"><strong><?php echo esc_html('&euro; ' . number_format($totalProfit, 2)); ?></strong></td>
                    <td class="num"><strong><?php echo esc_html(number_format($totalMargin, 1)); ?>%</strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($geoTotalEur > 0): ?>
            <p class="description" style="margin-top:12px;">
                <?php
                printf(
                    /* translators: 1: amount in EUR */
                    esc_html__('Note: GEO scan costs of %1$s in this period are not yet allocated per tenant (per-tenant GEO cost tracking is a follow-up).', 'sseo-ai-saas'),
                    '&euro; ' . esc_html(number_format($geoTotalEur, 2))
                );
                ?>
            </p>
        <?php endif;
    }

    // -------------------------------------------------------------------------
    // Tab 3: Invoice Template
    // -------------------------------------------------------------------------

    private function renderTemplateTab(): void
    {
        $logoId = (int) get_option('sseo_ai_saas_inv_logo_id', 0);
        $bgId = (int) get_option('sseo_ai_saas_inv_bg_id', 0);
        $logoUrl = $logoId ? wp_get_attachment_url($logoId) : '';
        $bgUrl = $bgId ? wp_get_attachment_url($bgId) : '';
        ?>
        <div class="sseo-ai-bk-template-wrap">
            <div class="sseo-ai-bk-template-form">
                <form method="post" action="options.php">
                    <?php settings_fields('sseo_ai_saas_invoice_template'); ?>

                    <h3><?php esc_html_e('Branding', 'sseo-ai-saas'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Logo', 'sseo-ai-saas'); ?></th>
                            <td>
                                <div class="sseo-ai-bk-media">
                                    <input type="hidden" id="inv_logo_id" name="sseo_ai_saas_inv_logo_id" value="<?php echo esc_attr($logoId); ?>">
                                    <div id="inv_logo_preview" class="sseo-ai-bk-media-preview">
                                        <?php if ($logoUrl): ?>
                                            <img src="<?php echo esc_url($logoUrl); ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button sseo-ai-bk-media-upload" data-target="inv_logo_id" data-preview="inv_logo_preview"><?php esc_html_e('Choose logo', 'sseo-ai-saas'); ?></button>
                                    <button type="button" class="button sseo-ai-bk-media-remove" data-target="inv_logo_id" data-preview="inv_logo_preview"><?php esc_html_e('Remove', 'sseo-ai-saas'); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Background image', 'sseo-ai-saas'); ?></th>
                            <td>
                                <div class="sseo-ai-bk-media">
                                    <input type="hidden" id="inv_bg_id" name="sseo_ai_saas_inv_bg_id" value="<?php echo esc_attr($bgId); ?>">
                                    <div id="inv_bg_preview" class="sseo-ai-bk-media-preview">
                                        <?php if ($bgUrl): ?>
                                            <img src="<?php echo esc_url($bgUrl); ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button sseo-ai-bk-media-upload" data-target="inv_bg_id" data-preview="inv_bg_preview"><?php esc_html_e('Choose background', 'sseo-ai-saas'); ?></button>
                                    <button type="button" class="button sseo-ai-bk-media-remove" data-target="inv_bg_id" data-preview="inv_bg_preview"><?php esc_html_e('Remove', 'sseo-ai-saas'); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="inv_bg_mode"><?php esc_html_e('Background mode', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <select id="inv_bg_mode" name="sseo_ai_saas_inv_bg_mode">
                                    <option value="none" <?php selected(get_option('sseo_ai_saas_inv_bg_mode', 'none'), 'none'); ?>><?php esc_html_e('None', 'sseo-ai-saas'); ?></option>
                                    <option value="cover" <?php selected(get_option('sseo_ai_saas_inv_bg_mode', 'none'), 'cover'); ?>><?php esc_html_e('Cover', 'sseo-ai-saas'); ?></option>
                                    <option value="repeat" <?php selected(get_option('sseo_ai_saas_inv_bg_mode', 'none'), 'repeat'); ?>><?php esc_html_e('Repeat', 'sseo-ai-saas'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="inv_header_color"><?php esc_html_e('Header color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" id="inv_header_color" name="sseo_ai_saas_inv_header_color" value="<?php echo esc_attr(get_option('sseo_ai_saas_inv_header_color', '')); ?>" class="regular-text" placeholder="<?php esc_attr_e('empty = default gradient', 'sseo-ai-saas'); ?>">
                                <p class="description"><?php esc_html_e('Solid color (e.g. #1a2b3c). Leave empty for the default accent gradient.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="inv_accent_color"><?php esc_html_e('Accent color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" id="inv_accent_color" name="sseo_ai_saas_inv_accent_color" value="<?php echo esc_attr(get_option('sseo_ai_saas_inv_accent_color', '#379fd3')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="inv_text_color"><?php esc_html_e('Text color', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="text" id="inv_text_color" name="sseo_ai_saas_inv_text_color" value="<?php echo esc_attr(get_option('sseo_ai_saas_inv_text_color', '#111827')); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Company details', 'sseo-ai-saas'); ?></h3>
                    <table class="form-table">
                        <?php
                        $fields = [
                            'company_name'    => __('Company name', 'sseo-ai-saas'),
                            'company_address' => __('Address', 'sseo-ai-saas'),
                            'company_vat'     => __('VAT number', 'sseo-ai-saas'),
                            'company_kvk'     => __('Chamber of Commerce (KvK)', 'sseo-ai-saas'),
                            'company_iban'    => __('IBAN', 'sseo-ai-saas'),
                            'company_email'   => __('Email', 'sseo-ai-saas'),
                            'company_website' => __('Website', 'sseo-ai-saas'),
                        ];
                        foreach ($fields as $key => $label):
                            $opt = 'sseo_ai_saas_inv_' . $key;
                        ?>
                            <tr>
                                <th scope="row"><label for="inv_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                                <td><input type="text" id="inv_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($opt); ?>" value="<?php echo esc_attr(get_option($opt, '')); ?>" class="regular-text"></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th scope="row"><label for="inv_prefix"><?php esc_html_e('Invoice number prefix', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" id="inv_prefix" name="sseo_ai_saas_inv_prefix" value="<?php echo esc_attr(get_option('sseo_ai_saas_inv_prefix', 'FYND-')); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="inv_footer_text"><?php esc_html_e('Footer text', 'sseo-ai-saas'); ?></label></th>
                            <td><input type="text" id="inv_footer_text" name="sseo_ai_saas_inv_footer_text" value="<?php echo esc_attr(get_option('sseo_ai_saas_inv_footer_text', 'Bedankt voor uw vertrouwen.')); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Labels', 'sseo-ai-saas'); ?></h3>
                    <table class="form-table">
                        <?php
                        $labels = [
                            'label_invoice'     => __('"Invoice" label', 'sseo-ai-saas'),
                            'label_from'        => __('"From" label', 'sseo-ai-saas'),
                            'label_to'          => __('"Billed to" label', 'sseo-ai-saas'),
                            'label_description' => __('"Description" label', 'sseo-ai-saas'),
                            'label_period'      => __('"Period" label', 'sseo-ai-saas'),
                            'label_amount'      => __('"Amount" label', 'sseo-ai-saas'),
                            'label_subtotal'    => __('"Subtotal" label', 'sseo-ai-saas'),
                            'label_vat'         => __('"VAT" label', 'sseo-ai-saas'),
                            'label_total'       => __('"Total" label', 'sseo-ai-saas'),
                            'label_paid_on'     => __('"Paid on" label', 'sseo-ai-saas'),
                        ];
                        foreach ($labels as $key => $label):
                            $opt = 'sseo_ai_saas_inv_' . $key;
                            $default = ucfirst(str_replace(['label_', '_'], ['', ' '], $key));
                        ?>
                            <tr>
                                <th scope="row"><label for="inv_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                                <td><input type="text" id="inv_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($opt); ?>" value="<?php echo esc_attr(get_option($opt, $default)); ?>" class="regular-text"></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <h3><?php esc_html_e('Profit calculation', 'sseo-ai-saas'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="inv_cost_usd_eur_rate"><?php esc_html_e('USD → EUR rate', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <input type="number" step="0.0001" min="0" id="inv_cost_usd_eur_rate" name="sseo_ai_saas_inv_cost_usd_eur_rate" value="<?php echo esc_attr(get_option('sseo_ai_saas_inv_cost_usd_eur_rate', 0.92)); ?>" style="width:120px;">
                                <p class="description"><?php esc_html_e('Used on the Profit tab to convert USD API costs to EUR.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Save template', 'sseo-ai-saas')); ?>
                </form>
            </div>
            <div class="sseo-ai-bk-template-preview">
                <h3><?php esc_html_e('Live preview', 'sseo-ai-saas'); ?></h3>
                <iframe id="sseo-ai-bk-preview-frame" src="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'bookkeeping_preview_invoice', admin_url('admin-ajax.php')), 'bookkeeping_preview_invoice', 'nonce')); ?>" width="100%" height="900"></iframe>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX endpoint serving a preview invoice using the current template settings.
     */
    public function handlePreviewInvoice(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to preview invoices.', 'sseo-ai-saas'));
        }
        check_ajax_referer('bookkeeping_preview_invoice', 'nonce');
        echo $this->invoices->renderPreviewHtml();
        wp_die();
    }
}

<?php

namespace SSEOAISaaS;

/**
 * GEO Scan Admin
 *
 * Admin page for running and viewing GEO Readiness scans.
 * Scans are queued and processed in the background (GeoScanQueue) — the
 * AJAX submit returns instantly and the UI polls scan status for progress.
 */
class GeoScanAdmin
{
    private GeoScanner $geoScanner;
    private GeoScanRepository $repository;
    private GeoScanReport $report;
    private GeoScanPdf $pdf;
    private GeoScanQueue $queue;
    private SaaSSettings $settings;
    private string $pluginFile;

    public function __construct(
        string $pluginFile,
        GeoScanner $geoScanner,
        GeoScanRepository $repository,
        GeoScanReport $report,
        GeoScanPdf $pdf,
        GeoScanQueue $queue,
        SaaSSettings $settings
    ) {
        $this->pluginFile = $pluginFile;
        $this->geoScanner = $geoScanner;
        $this->repository = $repository;
        $this->report = $report;
        $this->pdf = $pdf;
        $this->queue = $queue;
        $this->settings = $settings;

        add_action('wp_ajax_sseo_geo_scan_run', [$this, 'ajaxRun']);
        add_action('wp_ajax_sseo_geo_scan_status', [$this, 'ajaxStatus']);
        add_action('admin_post_sseo_geo_scan_regen_key', [$this, 'handleRegenKey']);
        add_action('admin_post_sseo_geo_scan_save_website', [$this, 'handleSaveWebsiteSettings']);
        add_action('admin_post_sseo_geo_scan_save_multi_model', [$this, 'handleSaveMultiModel']);
        add_action('admin_post_sseo_geo_scan_download_pdf', [$this, 'handleDownloadPdf']);
        add_action('admin_post_sseo_geo_scan_delete', [$this, 'handleDelete']);
    }

    /**
     * Register menu, assets and AJAX handler.
     */
    public function register(): void
    {
        $this->registerMenu();
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'sseo-ai-licenses',
            __('GEO Scan', 'sseo-ai-saas'),
            __('GEO Scan', 'sseo-ai-saas'),
            'agency_geo_scan',
            'sseo-ai-geo-scan',
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'sseo-ai-geo-scan') === false) {
            return;
        }

        wp_enqueue_style(
            'sseo-geo-scan-admin',
            plugins_url('assets/geo-scan-admin.css', $this->pluginFile),
            [],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/geo-scan-admin.css')
        );

        wp_enqueue_script(
            'sseo-geo-scan-admin',
            plugins_url('assets/geo-scan-admin.js', $this->pluginFile),
            ['jquery'],
            filemtime(plugin_dir_path($this->pluginFile) . 'assets/geo-scan-admin.js'),
            true
        );

        wp_localize_script('sseo-geo-scan-admin', 'sseoGeoScan', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('sseo_geo_scan'),
            'strings' => [
                'error'     => __('Scan failed. Please try again.', 'sseo-ai-saas'),
                'queued'    => __('In wachtrij…', 'sseo-ai-saas'),
                'completed' => __('Voltooid — rapport openen…', 'sseo-ai-saas'),
            ],
        ]);
    }

    /**
     * Render the main admin page or a report view.
     */
    public function render(): void
    {
        $this->repository->deleteExpired();

        if (isset($_GET['view']) && $_GET['view'] === 'report' && !empty($_GET['scan_id'])) {
            $scanId = (int)$_GET['scan_id'];
            if (!$this->canAccessScan($this->repository->getStatus($scanId))) {
                echo '<div class="wrap"><p>' . esc_html__('Scan niet gevonden.', 'sseo-ai-saas') . '</p></div>';
                return;
            }
            $this->report->render($scanId);
            return;
        }

        // Agency partners only see the scans they started themselves; website
        // leads and the provider settings below are provider-only.
        $isProvider = $this->isProvider();
        $viewMode = $this->viewMode();
        $websiteKey = $this->settings->getWebsiteScanKey();
        $publicEndpoint = rest_url('ai-seo-saas/v1/public/geo-scan');
        $saasShell = isset($_GET['saas_shell']) ? '&saas_shell=1' : '';
        ?>
        <div class="wrap sseo-ai-license-admin sseo-geo-admin">
            <?php ShellUi::pageHeader(
            $isProvider ? __('Provider', 'sseo-ai-saas') : __('Agency', 'sseo-ai-saas'),
            __('GEO Scans', 'sseo-ai-saas'),
            __('GEO Readiness Scan', 'sseo-ai-saas'),
            __('How visible a site is to AI assistants, per keyword and per model.', 'sseo-ai-saas')
        ); ?>

            <?php if (!empty($_GET['key_regenerated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Nieuwe website-scan key gegenereerd. Vergeet niet de key op fyndable.ai bij te werken.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($_GET['website_settings_saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Website-scan instellingen opgeslagen.', 'sseo-ai-saas'); ?></p></div>
            <?php endif; ?>

            <form id="sseo-geo-scan-form" class="sseo-geo-form sseo-geo-form-card">
                <?php wp_nonce_field('sseo_geo_scan', 'sseo_geo_scan_nonce'); ?>
                <h2><?php esc_html_e('Start een nieuwe scan', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Voer een prospect-URL en 1 tot 10 zoekwoorden in om een GEO Readiness rapport te genereren.', 'sseo-ai-saas'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sseo_geo_url"><?php esc_html_e('Prospect URL', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="url" name="url" id="sseo_geo_url" class="regular-text" placeholder="https://example.com" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sseo_geo_brand"><?php esc_html_e('Merknaam (optioneel)', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="text" name="brand" id="sseo_geo_brand" class="regular-text" maxlength="100" placeholder="<?php esc_attr_e('Bijv. NorthGear', 'sseo-ai-saas'); ?>">
                            <p class="description"><?php esc_html_e('Een AI-antwoord dat deze naam noemt telt als vermelding, ook zonder link. De domeinnaam (zonder extensie) wordt altijd al herkend; vul dit in als de merknaam daarvan afwijkt.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sseo_geo_keywords"><?php esc_html_e('Keywords', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <textarea name="keywords" id="sseo_geo_keywords" rows="5" cols="50" placeholder="<?php esc_attr_e('One keyword per line', 'sseo-ai-saas'); ?>" required></textarea>
                            <p class="description"><?php esc_html_e('Enter 1 to 10 keywords, one per line.', 'sseo-ai-saas'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sseo_geo_language"><?php esc_html_e('Language', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <select name="language" id="sseo_geo_language">
                                <option value="auto"><?php esc_html_e('Auto-detect (uit keywords)', 'sseo-ai-saas'); ?></option>
                                <option value="nl"><?php esc_html_e('Dutch', 'sseo-ai-saas'); ?></option>
                                <option value="en"><?php esc_html_e('English', 'sseo-ai-saas'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-hero" id="sseo-geo-scan-submit">
                        <?php esc_html_e('Start Scan', 'sseo-ai-saas'); ?>
                    </button>
                </p>
                <div id="sseo-geo-progress" class="sseo-geo-progress" style="display:none;">
                    <div class="sseo-geo-progress-track">
                        <div class="sseo-geo-progress-fill" style="width:0%;"></div>
                    </div>
                    <div class="sseo-geo-progress-meta">
                        <span class="sseo-geo-progress-pct">0%</span>
                        <span class="sseo-geo-progress-label"></span>
                    </div>
                </div>
            </form>

            <div id="sseo-geo-scan-error" class="sseo-geo-error" style="display:none;"></div>

            <?php if (!empty($_GET['deleted'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(_n('%d scan verwijderd.', '%d scans verwijderd.', (int)$_GET['deleted'], 'sseo-ai-saas'), (int)$_GET['deleted'])); ?></p></div>
            <?php endif; ?>

            <?php $this->renderScanSection('admin', $isProvider ? null : get_current_user_id(), $viewMode, $saasShell); ?>

            <?php if ($isProvider) : ?>
            <?php $this->renderScanSection('website', null, $viewMode, $saasShell); ?>

            <?php
            // Multi-model settings
            $multiEnabled = $this->settings->isGeoMultiModelEnabled();
            $multiModels = $this->settings->getGeoMultiModels();
            $websiteMultiEnabled = $this->settings->isGeoWebsiteMultiModelEnabled();
            $multiRouter = new \SSEOAISaaS\ProviderRouter($this->settings);
            $allModels = $multiRouter->getMergedAvailableModels();
            ?>
            <div class="sseo-ai-card sseo-geo-integration-card">
                <h2><?php esc_html_e('Multi-model GEO Scan', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Laat meerdere AI-modellen dezelfde pagina beoordelen voor een breder perspectief. De scores worden gemiddeld en bevindingen samengevoegd. Elk model evalueert vanuit zijn eigen sterke punten (bijv. ChatGPT → Bing/conversationeel, Gemini → Knowledge Graph, Claude → entity clarity).', 'sseo-ai-saas'); ?></p>

                <?php if (!empty($_GET['multi_model_saved'])) : ?>
                    <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Multi-model instellingen opgeslagen.', 'sseo-ai-saas'); ?></p></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sseo_geo_scan_multi_model', 'sseo_geo_multi_nonce'); ?>
                    <input type="hidden" name="action" value="sseo_geo_scan_save_multi_model">
                    <input type="hidden" name="saas_shell" value="<?php echo isset($_GET['saas_shell']) ? '1' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Multi-model (admin-scans)', 'sseo-ai-saas'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="multi_enabled" value="1" <?php checked($multiEnabled); ?>>
                                    <?php esc_html_e('Schakel multi-model analyse in voor admin-scans', 'sseo-ai-saas'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Wanneer ingeschakeld worden scans die vanuit deze pagina worden gestart door alle geselecteerde modellen geanalyseerd.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Multi-model (website-scans)', 'sseo-ai-saas'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="website_multi_enabled" value="1" <?php checked($websiteMultiEnabled); ?>>
                                    <?php esc_html_e('Ook voor website-scans (fyndable.ai)', 'sseo-ai-saas'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Pas op: dit verhoogt de kosten per website-scan met het aantal geselecteerde modellen.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sseo_geo_multi_add"><?php esc_html_e('Modellen', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <div class="sseo-geo-pill-select" id="sseo_geo_pill_select">
                                    <div class="sseo-geo-pill-container" id="sseo_geo_pill_container"></div>
                                    <select id="sseo_geo_multi_add" class="sseo-geo-pill-dropdown">
                                        <option value=""><?php esc_html_e('+ Model toevoegen…', 'sseo-ai-saas'); ?></option>
                                        <?php foreach ($allModels as $modelKey => $modelLabel) : ?>
                                            <option value="<?php echo esc_attr($modelKey); ?>"><?php echo esc_html($modelLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php // Hidden inputs for form submission are managed by JS ?>
                                    <div id="sseo_geo_multi_hidden_inputs">
                                        <?php foreach ($multiModels as $modelId) : ?>
                                            <input type="hidden" name="multi_models[]" value="<?php echo esc_attr($modelId); ?>">
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <p class="description sseo-geo-pill-count">
                                    <?php
                                    $selectedCount = count($multiModels);
                                    printf(
                                        esc_html__('Momenteel %d model(len) geselecteerd. Kosten per scan: ~%dx de normale prijs.', 'sseo-ai-saas'),
                                        $selectedCount,
                                        max(1, $selectedCount)
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Multi-model instellingen opslaan', 'sseo-ai-saas'); ?></button>
                </form>
            </div>

            <div class="sseo-ai-card sseo-geo-integration-card">
                <h2><?php esc_html_e('Website Scan integratie (fyndable.ai)', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Deze key gebruikt de fyndable-geo-scan plugin op de website om scans aan te vragen. De key wordt als X-Fyndable-Scan-Key header verstuurd.', 'sseo-ai-saas'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Endpoint', 'sseo-ai-saas'); ?></th>
                        <td><code><?php echo esc_html($publicEndpoint); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Status endpoint', 'sseo-ai-saas'); ?></th>
                        <td><code><?php echo esc_html(rest_url('ai-seo-saas/v1/public/geo-scan/{id}/status')); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sseo_geo_website_key"><?php esc_html_e('API key', 'sseo-ai-saas'); ?></label></th>
                        <td>
                            <input type="text" id="sseo_geo_website_key" class="regular-text code" value="<?php echo esc_attr($websiteKey); ?>" readonly onclick="this.select();">
                            <?php if (empty($websiteKey)) : ?>
                                <p class="description"><?php esc_html_e('Nog geen key — genereer er een om de website-scan te activeren.', 'sseo-ai-saas'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sseo_geo_scan_website_settings', 'sseo_geo_website_nonce'); ?>
                    <input type="hidden" name="action" value="sseo_geo_scan_save_website">
                    <input type="hidden" name="saas_shell" value="<?php echo isset($_GET['saas_shell']) ? '1' : ''; ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="sseo_geo_website_model"><?php esc_html_e('Model (website-scan)', 'sseo-ai-saas'); ?></label></th>
                            <td>
                                <?php $websiteModelOverride = $this->settings->getWebsiteGeoModelOverride(); ?>
                                <select name="website_model" id="sseo_geo_website_model">
                                    <option value="" <?php selected($websiteModelOverride, ''); ?>><?php esc_html_e('— Zelfde als GEO Scan Model —', 'sseo-ai-saas'); ?></option>
                                    <?php
                                    $websiteRouter = new \SSEOAISaaS\ProviderRouter($this->settings);
                                    foreach ($websiteRouter->getMergedAvailableModels() as $modelKey => $modelLabel):
                                    ?>
                                        <option value="<?php echo esc_attr($modelKey); ?>" <?php selected($websiteModelOverride, $modelKey); ?>><?php echo esc_html($modelLabel); ?></option>
                                    <?php endforeach; ?>
                                    <?php if (!empty($websiteModelOverride) && !isset(\SSEOAISaaS\ProviderRouter::getAvailableModels()[$websiteModelOverride])): ?>
                                        <?php // Keep a saved model selectable when the live model list is unavailable. ?>
                                        <option value="<?php echo esc_attr($websiteModelOverride); ?>" selected><?php echo esc_html($websiteModelOverride); ?> (<?php esc_html_e('saved', 'sseo-ai-saas'); ?>)</option>
                                    <?php endif; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Model voor scans die via de fyndable-geo-scan plugin op fyndable.ai worden gestart. Laat leeg om het standaard GEO Scan Model te gebruiken.', 'sseo-ai-saas'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Model opslaan', 'sseo-ai-saas'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sseo_geo_scan_regen_key', 'sseo_geo_regen_nonce'); ?>
                    <input type="hidden" name="action" value="sseo_geo_scan_regen_key">
                    <input type="hidden" name="saas_shell" value="<?php echo isset($_GET['saas_shell']) ? '1' : ''; ?>">
                    <button type="submit" class="button button-secondary" <?php echo !empty($websiteKey) ? 'onclick="return confirm(\'' . esc_js(__('Bestaande key wordt ongeldig — de key op fyndable.ai moet daarna worden bijgewerkt. Doorgaan?', 'sseo-ai-saas')) . '\');"' : ''; ?>>
                        <?php echo empty($websiteKey) ? esc_html__('Genereer key', 'sseo-ai-saas') : esc_html__('Genereer nieuwe key', 'sseo-ai-saas'); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
            <?php $this->renderPdfDialog(); ?>
        </div>
        <?php
    }

    private const PER_PAGE = ['list' => 15, 'cards' => 12];
    private const VIEW_META = 'sseo_geo_scan_view';

    /** Lijst of kaarten: ?geo_view= wint en wordt per gebruiker onthouden. */
    private function viewMode(): string
    {
        $requested = isset($_GET['geo_view']) ? sanitize_key($_GET['geo_view']) : '';
        if (in_array($requested, ['list', 'cards'], true)) {
            update_user_meta(get_current_user_id(), self::VIEW_META, $requested);
            return $requested;
        }
        $stored = (string)get_user_meta(get_current_user_id(), self::VIEW_META, true);
        return $stored === 'cards' ? 'cards' : 'list';
    }

    /** Paginaparameter per sectie, zodat beide overzichten los van elkaar bladeren. */
    private function pageParam(string $source): string
    {
        return $source === 'website' ? 'web_page' : 'admin_page';
    }

    /**
     * URL van de overzichtspagina met de huidige weergave en paginering; $overrides vervangt losse parameters.
     */
    private function overviewUrl(array $overrides = []): string
    {
        $args = ['page' => 'sseo-ai-geo-scan'];
        foreach (['geo_view', 'admin_page', 'web_page', 'saas_shell'] as $key) {
            if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
                $args[$key] = sanitize_key((string)$_REQUEST[$key]);
            }
        }
        return add_query_arg(array_merge($args, $overrides), admin_url('admin.php'));
    }

    /**
     * Eén overzicht (eigen/admin-scans of website-leads) als lijst of kaarten, met paginering,
     * bulk- en losse verwijderknoppen.
     */
    private function renderScanSection(string $source, ?int $userId, string $viewMode, string $saasShell): void
    {
        $isWebsite = $source === 'website';
        $perPage = self::PER_PAGE[$viewMode];
        $total = $this->repository->count($source, $userId);
        $pages = max(1, (int)ceil($total / $perPage));
        $pageParam = $this->pageParam($source);
        $current = min($pages, max(1, (int)($_GET[$pageParam] ?? 1)));
        $scans = $total > 0 ? $this->repository->getPage($source, $userId, $perPage, $current) : [];
        $formId = 'sseo-geo-delete-' . $source;
        $confirmOne = __('Deze scan definitief verwijderen? Dit kan niet ongedaan worden gemaakt.', 'sseo-ai-saas');
        $confirmBulk = __('De geselecteerde scans definitief verwijderen? Dit kan niet ongedaan worden gemaakt.', 'sseo-ai-saas');
        ?>
        <div class="sseo-ai-card sseo-geo-section">
            <div class="sseo-geo-section-head">
                <div>
                    <h2><?php echo $isWebsite ? esc_html__('Website Scans', 'sseo-ai-saas') : esc_html__('Recent Scans', 'sseo-ai-saas'); ?></h2>
                    <?php if ($isWebsite) : ?>
                        <p class="description"><?php esc_html_e('Scans aangevraagd via het publieke formulier op fyndable.ai. Bevat contactgegevens voor follow-up.', 'sseo-ai-saas'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="sseo-geo-view-toggle" role="group" aria-label="<?php esc_attr_e('Weergave', 'sseo-ai-saas'); ?>">
                    <a href="<?php echo esc_url($this->overviewUrl(['geo_view' => 'list'])); ?>" class="button<?php echo $viewMode === 'list' ? ' is-active' : ''; ?>" aria-pressed="<?php echo $viewMode === 'list' ? 'true' : 'false'; ?>"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Lijst', 'sseo-ai-saas'); ?></a>
                    <a href="<?php echo esc_url($this->overviewUrl(['geo_view' => 'cards'])); ?>" class="button<?php echo $viewMode === 'cards' ? ' is-active' : ''; ?>" aria-pressed="<?php echo $viewMode === 'cards' ? 'true' : 'false'; ?>"><span class="dashicons dashicons-grid-view"></span> <?php esc_html_e('Kaarten', 'sseo-ai-saas'); ?></a>
                </div>
            </div>

            <?php if ($total === 0) : ?>
                <p><?php echo $isWebsite ? esc_html__('Nog geen website-scans.', 'sseo-ai-saas') : esc_html__('No scans yet.', 'sseo-ai-saas'); ?></p>
            <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="<?php echo esc_attr($formId); ?>" class="sseo-geo-delete-form">
                <input type="hidden" name="action" value="sseo_geo_scan_delete">
                <?php wp_nonce_field('sseo_geo_scan_delete', 'sseo_geo_delete_nonce'); ?>
                <?php foreach (['geo_view' => $viewMode, 'admin_page' => (int)($_GET['admin_page'] ?? 1), 'web_page' => (int)($_GET['web_page'] ?? 1)] as $key => $value) : ?>
                    <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string)$value); ?>">
                <?php endforeach; ?>
                <?php if ($saasShell !== '') : ?><input type="hidden" name="saas_shell" value="1"><?php endif; ?>

                <div class="sseo-geo-bulk">
                    <label class="sseo-geo-select-all"><input type="checkbox" data-geo-select-all="<?php echo esc_attr($formId); ?>"> <?php esc_html_e('Alles op deze pagina', 'sseo-ai-saas'); ?></label>
                    <button type="submit" class="button" name="bulk_delete" value="1" data-confirm="<?php echo esc_attr($confirmBulk); ?>" data-empty="<?php esc_attr_e('Selecteer eerst een of meer scans.', 'sseo-ai-saas'); ?>"><?php esc_html_e('Geselecteerde verwijderen', 'sseo-ai-saas'); ?></button>
                    <span class="sseo-geo-count"><?php echo esc_html(sprintf(_n('%d scan', '%d scans', $total, 'sseo-ai-saas'), $total)); ?></span>
                </div>

                <?php if ($viewMode === 'list') : ?>
                <table class="wp-list-table widefat fixed striped sseo-geo-scans-table">
                    <thead>
                        <tr>
                            <td class="check-column"><span class="screen-reader-text"><?php esc_html_e('Selecteren', 'sseo-ai-saas'); ?></span></td>
                            <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                            <?php if ($isWebsite) : ?><th><?php esc_html_e('E-mail', 'sseo-ai-saas'); ?></th><?php endif; ?>
                            <th><?php esc_html_e('URL', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Keywords', 'sseo-ai-saas'); ?></th>
                            <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                            <th class="sseo-geo-col-score"><?php esc_html_e('Score', 'sseo-ai-saas'); ?></th>
                            <?php if ($isWebsite) : ?><th class="sseo-geo-col-consent"><?php esc_html_e('Consent', 'sseo-ai-saas'); ?></th><?php endif; ?>
                            <th class="sseo-geo-col-actions"><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scans as $scan) : $status = (string)($scan['status'] ?? 'completed'); ?>
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="scan_ids[]" value="<?php echo (int)$scan['id']; ?>"></th>
                            <td><?php echo esc_html((string)$scan['created_at']); ?></td>
                            <?php if ($isWebsite) : ?><td><?php $this->renderLead($scan); ?></td><?php endif; ?>
                            <td class="sseo-geo-url"><?php echo esc_html((string)$scan['url']); ?></td>
                            <td><?php echo esc_html((string)$scan['keywords']); ?></td>
                            <td><?php $this->renderStatusBadge($status, (int)($scan['progress'] ?? 0), (string)($scan['progress_label'] ?? ''), (string)($scan['error'] ?? '')); ?></td>
                            <td><?php echo $status === 'completed' ? esc_html((int)($scan['score'] ?? 0) . '/100') : '—'; ?></td>
                            <?php if ($isWebsite) : ?><td><?php $this->renderConsent($scan, false); ?></td><?php endif; ?>
                            <td class="sseo-geo-actions"><?php $this->renderScanActions($scan, $saasShell, $confirmOne); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="sseo-geo-cards">
                    <?php foreach ($scans as $scan) : $status = (string)($scan['status'] ?? 'completed'); $host = (string)(wp_parse_url((string)$scan['url'], PHP_URL_HOST) ?: $scan['url']); ?>
                    <div class="sseo-geo-scan-card">
                        <div class="sseo-geo-scan-card-top">
                            <label class="sseo-geo-card-check"><input type="checkbox" name="scan_ids[]" value="<?php echo (int)$scan['id']; ?>"><span class="screen-reader-text"><?php esc_html_e('Selecteren', 'sseo-ai-saas'); ?></span></label>
                            <div class="sseo-geo-scan-card-title">
                                <strong title="<?php echo esc_attr((string)$scan['url']); ?>"><?php echo esc_html(preg_replace('/^www\./i', '', $host)); ?></strong>
                                <span><?php echo esc_html((string)$scan['created_at']); ?></span>
                            </div>
                            <div class="sseo-geo-scan-card-score<?php echo $status === 'completed' ? '' : ' is-empty'; ?>"><?php echo $status === 'completed' ? (int)($scan['score'] ?? 0) : '—'; ?></div>
                        </div>
                        <div class="sseo-geo-scan-card-body">
                            <?php $this->renderStatusBadge($status, (int)($scan['progress'] ?? 0), (string)($scan['progress_label'] ?? ''), (string)($scan['error'] ?? '')); ?>
                            <?php if ($isWebsite) : ?><?php $this->renderConsent($scan); ?><?php endif; ?>
                            <p class="sseo-geo-scan-card-keywords"><?php echo esc_html((string)$scan['keywords']); ?></p>
                            <?php if ($isWebsite) : ?><div class="sseo-geo-scan-card-lead"><?php $this->renderLead($scan); ?></div><?php endif; ?>
                        </div>
                        <div class="sseo-geo-scan-card-actions"><?php $this->renderScanActions($scan, $saasShell, $confirmOne); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </form>

            <?php if ($pages > 1) : ?>
            <div class="tablenav bottom sseo-geo-pagination"><div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html(sprintf(__('Pagina %1$d van %2$d', 'sseo-ai-saas'), $current, $pages)); ?></span>
                <span class="pagination-links"><?php
                    echo paginate_links([
                        'base'      => str_replace('999999999', '%#%', $this->overviewUrl([$pageParam => 999999999])),
                        'format'    => '',
                        'current'   => $current,
                        'total'     => $pages,
                        'prev_text' => '&lsaquo;',
                        'next_text' => '&rsaquo;',
                        'mid_size'  => 1,
                    ]); // phpcs:ignore WordPress.Security.EscapeOutput -- paginate_links escapes its own output
                ?></span>
            </div></div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderLead(array $scan): void
    {
        $email = (string)($scan['email'] ?? '');
        if ($email !== '') {
            echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        }
        $name = trim((string)($scan['meta']['name'] ?? ''));
        $company = trim((string)($scan['meta']['company'] ?? ''));
        if ($name !== '' || $company !== '') {
            echo '<br><span class="description">' . esc_html(trim($name . ($company !== '' ? ' · ' . $company : ''))) . '</span>';
        }
    }

    /** Consent-badge; in de lijst staat het label al in de kolomkop, in een kaart niet. */
    private function renderConsent(array $scan, bool $withLabel = true): void
    {
        if (!empty($scan['consent'])) {
            $text = $withLabel ? __('Consent: ja', 'sseo-ai-saas') : __('Ja', 'sseo-ai-saas');
            echo '<span class="sseo-geo-status yes" title="' . esc_attr((string)($scan['consent_at'] ?? '')) . '">' . esc_html($text) . '</span>';
        } else {
            $text = $withLabel ? __('Consent: nee', 'sseo-ai-saas') : __('Nee', 'sseo-ai-saas');
            echo '<span class="sseo-geo-status no">' . esc_html($text) . '</span>';
        }
    }

    private function renderScanActions(array $scan, string $saasShell, string $confirmOne): void
    {
        $scanId = (int)$scan['id'];
        if (($scan['status'] ?? '') === 'completed') {
            $viewUrl = admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . $scanId . $saasShell);
            echo '<a href="' . esc_url($viewUrl) . '" class="button button-small">' . esc_html__('View Report', 'sseo-ai-saas') . '</a> ';
            echo '<button type="button" class="button button-small sseo-geo-pdf-open" data-pdf-url="' . esc_url($this->getPdfUrl($scanId)) . '">' . esc_html__('Download PDF', 'sseo-ai-saas') . '</button> ';
        }
        echo '<button type="submit" class="button button-small button-link-delete sseo-geo-delete" name="delete_one" value="' . $scanId . '" onclick="return confirm(' . esc_attr(wp_json_encode($confirmOne)) . ');">' . esc_html__('Verwijderen', 'sseo-ai-saas') . '</button>';
    }

    /**
     * admin-post: definitief verwijderen van één scan (delete_one) of de aangevinkte scans (scan_ids[]).
     * Alleen scans die de gebruiker mag openen (canAccessScan) worden verwijderd.
     */
    public function handleDelete(): void
    {
        if (!current_user_can('agency_geo_scan') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen toestemming om scans te verwijderen.', 'sseo-ai-saas'), 403);
        }
        check_admin_referer('sseo_geo_scan_delete', 'sseo_geo_delete_nonce');

        $ids = !empty($_POST['delete_one'])
            ? [(int)$_POST['delete_one']]
            : array_map('intval', (array)($_POST['scan_ids'] ?? []));
        $allowed = array_values(array_filter($ids, fn(int $id): bool => $id > 0 && $this->canAccessScan($this->repository->getStatus($id))));
        $deleted = $this->repository->deleteByIds($allowed);

        wp_safe_redirect($this->overviewUrl(['deleted' => $deleted]));
        exit;
    }

    private function isProvider(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Providers may open every scan; agency partners only the admin scans
     * they started themselves.
     */
    private function canAccessScan(?array $scan): bool
    {
        if (!$scan) {
            return false;
        }
        if ($this->isProvider()) {
            return true;
        }

        return ($scan['source'] ?? '') === 'admin'
            && (int)($scan['user_id'] ?? 0) > 0
            && (int)$scan['user_id'] === get_current_user_id();
    }

    private function getPdfUrl(int $scanId): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=sseo_geo_scan_download_pdf&scan_id=' . $scanId),
            'sseo_geo_scan_pdf_' . $scanId
        );
    }

    private function renderPdfDialog(): void
    {
        ?>
        <div class="sseo-geo-pdf-dialog" id="sseo-geo-pdf-dialog" role="dialog" aria-modal="true" aria-labelledby="sseo-geo-pdf-title" hidden>
            <div class="sseo-geo-pdf-backdrop" data-pdf-close></div>
            <div class="sseo-geo-pdf-panel">
                <button type="button" class="sseo-geo-pdf-close" data-pdf-close aria-label="<?php esc_attr_e('Sluiten', 'sseo-ai-saas'); ?>">&times;</button>
                <h2 id="sseo-geo-pdf-title"><?php esc_html_e('Kies een cover', 'sseo-ai-saas'); ?></h2>
                <p><?php esc_html_e('Selecteer de cover voor het prospectrapport.', 'sseo-ai-saas'); ?></p>
                <div class="sseo-geo-cover-options">
                    <label class="sseo-geo-cover-option is-selected">
                        <input type="radio" name="sseo_geo_cover" value="light" checked>
                        <span class="sseo-geo-cover-preview light"><strong>GEO</strong><small><?php esc_html_e('SCAN RAPPORT', 'sseo-ai-saas'); ?></small></span>
                        <span><?php esc_html_e('Lichte cover', 'sseo-ai-saas'); ?></span>
                    </label>
                    <label class="sseo-geo-cover-option">
                        <input type="radio" name="sseo_geo_cover" value="dark">
                        <span class="sseo-geo-cover-preview dark"><strong>GEO</strong><small><?php esc_html_e('SCAN RAPPORT', 'sseo-ai-saas'); ?></small></span>
                        <span><?php esc_html_e('Donkere cover', 'sseo-ai-saas'); ?></span>
                    </label>
                </div>
                <div class="sseo-geo-pdf-dialog-actions">
                    <button type="button" class="button" data-pdf-close><?php esc_html_e('Annuleren', 'sseo-ai-saas'); ?></button>
                    <button type="button" class="button button-primary" id="sseo-geo-pdf-download"><?php esc_html_e('Download PDF', 'sseo-ai-saas'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Status badge for queued/running/failed/completed scans.
     */
    private function renderStatusBadge(string $status, int $progress, string $label, string $error): void
    {
        switch ($status) {
            case 'queued':
                echo '<span class="sseo-geo-status queued">' . esc_html__('In wachtrij', 'sseo-ai-saas') . '</span>';
                break;
            case 'running':
                echo '<span class="sseo-geo-status running">' . esc_html($progress) . '% — ' . esc_html($label ?: __('Bezig…', 'sseo-ai-saas')) . '</span>';
                break;
            case 'failed':
                echo '<span class="sseo-geo-status no" title="' . esc_attr($error) . '">' . esc_html__('Mislukt', 'sseo-ai-saas') . '</span>';
                break;
            default:
                echo '<span class="sseo-geo-status yes">' . esc_html__('Voltooid', 'sseo-ai-saas') . '</span>';
        }
    }

    /**
     * AJAX: queue a new scan — returns instantly with a scan_id.
     */
    public function ajaxRun(): void
    {
        check_ajax_referer('sseo_geo_scan', 'nonce');

        if (!current_user_can('agency_geo_scan') && !current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to run scans.', 'sseo-ai-saas'));
        }

        $url = sanitize_url($_POST['url'] ?? '');
        $rawKeywords = isset($_POST['keywords']) ? sanitize_textarea_field(wp_unslash($_POST['keywords'])) : '';
        $language = sanitize_text_field($_POST['language'] ?? 'auto');

        $keywords = array_values(array_filter(array_map('trim', explode("\n", $rawKeywords))));

        if (empty($url) || empty($keywords)) {
            wp_send_json_error(__('URL en keywords zijn verplicht.', 'sseo-ai-saas'));
        }
        if (count($keywords) > 10) {
            wp_send_json_error(__('Maximaal 10 keywords.', 'sseo-ai-saas'));
        }

        $scanId = $this->repository->insertQueued($url, $keywords, $language, [
            'source'  => 'admin',
            'user_id' => get_current_user_id(),
            'brand'   => sanitize_text_field(wp_unslash($_POST['brand'] ?? '')),
        ]);
        $this->queue->enqueue($scanId);

        wp_send_json_success([
            'scan_id' => $scanId,
            'status'  => 'queued',
        ]);
    }

    /**
     * AJAX: poll scan status/progress (called every ~2.5s by the admin JS).
     */
    public function ajaxStatus(): void
    {
        check_ajax_referer('sseo_geo_scan', 'nonce');

        if (!current_user_can('agency_geo_scan') && !current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to view scans.', 'sseo-ai-saas'));
        }

        $scanId = (int)($_POST['scan_id'] ?? 0);
        $scan = $this->repository->getStatus($scanId);

        if (!$this->canAccessScan($scan)) {
            wp_send_json_error(__('Scan niet gevonden.', 'sseo-ai-saas'));
        }

        $response = [
            'status'         => $scan['status'],
            'progress'       => (int)$scan['progress'],
            'progress_label' => $scan['progress_label'],
        ];

        if ($scan['status'] === 'completed') {
            $saasShell = !empty($_POST['saas_shell']) ? '&saas_shell=1' : '';
            $response['redirect'] = admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . $scanId . $saasShell);
        }

        if ($scan['status'] === 'failed') {
            $response['error'] = $scan['error'] ?: __('Scan mislukt.', 'sseo-ai-saas');
        }

        wp_send_json_success($response);
    }

    public function handleDownloadPdf(): void
    {
        if (!current_user_can('agency_geo_scan') && !current_user_can('manage_options')) {
            wp_die(__('Je hebt geen toestemming om dit rapport te downloaden.', 'sseo-ai-saas'), 403);
        }

        $scanId = (int)($_GET['scan_id'] ?? 0);
        check_admin_referer('sseo_geo_scan_pdf_' . $scanId);
        if (!$this->canAccessScan($this->repository->getStatus($scanId))) {
            wp_die(esc_html__('Het voltooide scanrapport is niet gevonden.', 'sseo-ai-saas'), esc_html__('PDF genereren mislukt', 'sseo-ai-saas'), ['response' => 404]);
        }
        $cover = sanitize_key($_GET['cover'] ?? 'light');
        $pdf = $this->pdf->render($scanId, $cover);
        if (is_wp_error($pdf)) {
            wp_die(esc_html($pdf->get_error_message()), esc_html__('PDF genereren mislukt', 'sseo-ai-saas'), ['response' => 404]);
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $pdf['filename'] . '"');
        header('Content-Length: ' . strlen($pdf['bytes']));
        echo $pdf['bytes'];
        exit;
    }

    /**
     * admin-post: save the dedicated website-scan model override.
     */
    public function handleSaveWebsiteSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission.', 'sseo-ai-saas'), 403);
        }
        check_admin_referer('sseo_geo_scan_website_settings', 'sseo_geo_website_nonce');

        $model = sanitize_text_field(wp_unslash($_POST['website_model'] ?? ''));
        update_option('sseo_ai_saas_geo_website_model', $model);

        $redirect = admin_url('admin.php?page=sseo-ai-geo-scan&website_settings_saved=1');
        if (!empty($_POST['saas_shell'])) {
            $redirect .= '&saas_shell=1';
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * admin-post: regenerate the shared website-scan key.
     */
    public function handleRegenKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission.', 'sseo-ai-saas'), 403);
        }
        check_admin_referer('sseo_geo_scan_regen_key', 'sseo_geo_regen_nonce');

        $this->settings->regenerateWebsiteScanKey();

        $redirect = admin_url('admin.php?page=sseo-ai-geo-scan&key_regenerated=1');
        if (!empty($_POST['saas_shell'])) {
            $redirect .= '&saas_shell=1';
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * admin-post: save multi-model GEO scan settings.
     */
    public function handleSaveMultiModel(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission.', 'sseo-ai-saas'), 403);
        }
        check_admin_referer('sseo_geo_scan_multi_model', 'sseo_geo_multi_nonce');

        $multiEnabled = !empty($_POST['multi_enabled']);
        $websiteMultiEnabled = !empty($_POST['website_multi_enabled']);
        $models = isset($_POST['multi_models']) && is_array($_POST['multi_models'])
            ? array_map('sanitize_text_field', $_POST['multi_models'])
            : [];

        update_option('sseo_ai_saas_geo_multi_enabled', $multiEnabled);
        update_option('sseo_ai_saas_geo_website_multi_enabled', $websiteMultiEnabled);
        update_option('sseo_ai_saas_geo_multi_models', $models);

        $redirect = admin_url('admin.php?page=sseo-ai-geo-scan&multi_model_saved=1');
        if (!empty($_POST['saas_shell'])) {
            $redirect .= '&saas_shell=1';
        }
        wp_safe_redirect($redirect);
        exit;
    }
}

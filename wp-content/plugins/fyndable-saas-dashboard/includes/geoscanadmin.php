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
    private GeoScanQueue $queue;
    private SaaSSettings $settings;
    private string $pluginFile;

    public function __construct(
        string $pluginFile,
        GeoScanner $geoScanner,
        GeoScanRepository $repository,
        GeoScanReport $report,
        GeoScanQueue $queue,
        SaaSSettings $settings
    ) {
        $this->pluginFile = $pluginFile;
        $this->geoScanner = $geoScanner;
        $this->repository = $repository;
        $this->report = $report;
        $this->queue = $queue;
        $this->settings = $settings;

        add_action('wp_ajax_sseo_geo_scan_run', [$this, 'ajaxRun']);
        add_action('wp_ajax_sseo_geo_scan_status', [$this, 'ajaxStatus']);
        add_action('admin_post_sseo_geo_scan_regen_key', [$this, 'handleRegenKey']);
        add_action('admin_post_sseo_geo_scan_save_website', [$this, 'handleSaveWebsiteSettings']);
        add_action('admin_post_sseo_geo_scan_save_multi_model', [$this, 'handleSaveMultiModel']);
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
            $this->report->render((int)$_GET['scan_id']);
            return;
        }

        $recentScans = $this->repository->getRecent(20, 'admin');
        $websiteScans = $this->repository->getRecent(50, 'website');
        $websiteKey = $this->settings->getWebsiteScanKey();
        $publicEndpoint = rest_url('ai-seo-saas/v1/public/geo-scan');
        $saasShell = isset($_GET['saas_shell']) ? '&saas_shell=1' : '';
        ?>
        <div class="wrap sseo-ai-license-admin sseo-geo-admin">
            <h1><?php esc_html_e('GEO Readiness Scan', 'sseo-ai-saas'); ?></h1>

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

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Recent Scans', 'sseo-ai-saas'); ?></h2>
                <?php if (empty($recentScans)) : ?>
                    <p><?php esc_html_e('No scans yet.', 'sseo-ai-saas'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped sseo-geo-scans-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('URL', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Keywords', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Score', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentScans as $scan) :
                                $score = (int)($scan['score'] ?? 0);
                                $status = $scan['status'] ?? 'completed';
                                $viewUrl = admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . (int)$scan['id'] . $saasShell);
                            ?>
                            <tr>
                                <td><?php echo esc_html($scan['created_at']); ?></td>
                                <td><?php echo esc_url($scan['url']); ?></td>
                                <td><?php echo esc_html($scan['keywords']); ?></td>
                                <td><?php $this->renderStatusBadge($status, (int)($scan['progress'] ?? 0), $scan['progress_label'] ?? '', $scan['error'] ?? ''); ?></td>
                                <td><?php echo $status === 'completed' ? esc_html($score . '/100') : '—'; ?></td>
                                <td>
                                    <?php if ($status === 'completed') : ?>
                                        <a href="<?php echo esc_url($viewUrl); ?>" class="button button-small"><?php esc_html_e('View Report', 'sseo-ai-saas'); ?></a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="sseo-ai-card">
                <h2><?php esc_html_e('Website Scans', 'sseo-ai-saas'); ?></h2>
                <p class="description"><?php esc_html_e('Scans aangevraagd via het publieke formulier op fyndable.ai. Bevat contactgegevens voor follow-up.', 'sseo-ai-saas'); ?></p>
                <?php if (empty($websiteScans)) : ?>
                    <p><?php esc_html_e('Nog geen website-scans.', 'sseo-ai-saas'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped sseo-geo-scans-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('E-mail', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('URL', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Keywords', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Status', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Score', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Consent', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($websiteScans as $scan) :
                                $score = (int)($scan['score'] ?? 0);
                                $status = $scan['status'] ?? 'completed';
                                $viewUrl = admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . (int)$scan['id'] . $saasShell);
                            ?>
                            <tr>
                                <td><?php echo esc_html($scan['created_at']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($scan['email']); ?>"><?php echo esc_html($scan['email']); ?></a>
                                    <?php
                                    $leadName = trim((string)($scan['meta']['name'] ?? ''));
                                    $leadCompany = trim((string)($scan['meta']['company'] ?? ''));
                                    if ($leadName !== '' || $leadCompany !== '') :
                                    ?>
                                    <br><span class="description"><?php echo esc_html(trim($leadName . ($leadCompany !== '' ? ' · ' . $leadCompany : ''))); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_url($scan['url']); ?></td>
                                <td><?php echo esc_html($scan['keywords']); ?></td>
                                <td><?php $this->renderStatusBadge($status, (int)($scan['progress'] ?? 0), $scan['progress_label'] ?? '', $scan['error'] ?? ''); ?></td>
                                <td><?php echo $status === 'completed' ? esc_html($score . '/100') : '—'; ?></td>
                                <td>
                                    <?php if (!empty($scan['consent'])) : ?>
                                        <span class="sseo-geo-status yes" title="<?php echo esc_attr($scan['consent_at'] ?? ''); ?>"><?php esc_html_e('Ja', 'sseo-ai-saas'); ?></span>
                                    <?php else : ?>
                                        <span class="sseo-geo-status no"><?php esc_html_e('Nee', 'sseo-ai-saas'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status === 'completed') : ?>
                                        <a href="<?php echo esc_url($viewUrl); ?>" class="button button-small"><?php esc_html_e('View Report', 'sseo-ai-saas'); ?></a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

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

        $scanId = $this->repository->insertQueued($url, $keywords, $language, ['source' => 'admin']);
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

        if (!$scan) {
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

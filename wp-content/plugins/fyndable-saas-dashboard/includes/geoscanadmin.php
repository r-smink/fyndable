<?php

namespace SSEOAISaaS;

/**
 * GEO Scan Admin
 *
 * Admin page for running and viewing GEO Readiness scans.
 */
class GeoScanAdmin
{
    private GeoScanner $geoScanner;
    private GeoScanRepository $repository;
    private GeoScanReport $report;
    private string $pluginFile;

    public function __construct(
        string $pluginFile,
        GeoScanner $geoScanner,
        GeoScanRepository $repository,
        GeoScanReport $report
    ) {
        $this->pluginFile = $pluginFile;
        $this->geoScanner = $geoScanner;
        $this->repository = $repository;
        $this->report = $report;

        add_action('wp_ajax_sseo_geo_scan_run', [$this, 'ajaxRun']);
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
            'manage_options',
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
                'error' => __('Scan failed. Please try again.', 'sseo-ai-saas'),
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

        $recentScans = $this->repository->getRecent(20);
        ?>
        <div class="wrap sseo-ai-license-admin sseo-geo-admin">
            <h1><?php esc_html_e('GEO Readiness Scan', 'sseo-ai-saas'); ?></h1>

            <form id="sseo-geo-scan-form" class="sseo-geo-form">
                <?php wp_nonce_field('sseo_geo_scan', 'sseo_geo_scan_nonce'); ?>
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
                                <option value="nl"><?php esc_html_e('Dutch', 'sseo-ai-saas'); ?></option>
                                <option value="en"><?php esc_html_e('English', 'sseo-ai-saas'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary" id="sseo-geo-scan-submit">
                        <?php esc_html_e('Start Scan', 'sseo-ai-saas'); ?>
                    </button>
                    <span class="sseo-geo-spinner" style="display:none;"><?php esc_html_e('Scanning, please wait...', 'sseo-ai-saas'); ?></span>
                </p>
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
                                <th><?php esc_html_e('Score', 'sseo-ai-saas'); ?></th>
                                <th><?php esc_html_e('Actions', 'sseo-ai-saas'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentScans as $scan) :
                                $result = $scan['result'] ?? [];
                                $score = (int)($result['score'] ?? 0);
                                $viewUrl = admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . (int)$scan['id']);
                            ?>
                            <tr>
                                <td><?php echo esc_html($scan['created_at']); ?></td>
                                <td><?php echo esc_url($scan['url']); ?></td>
                                <td><?php echo esc_html($scan['keywords']); ?></td>
                                <td><?php echo esc_html($score); ?>/100</td>
                                <td>
                                    <a href="<?php echo esc_url($viewUrl); ?>" class="button button-small"><?php esc_html_e('View Report', 'sseo-ai-saas'); ?></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle the AJAX scan request.
     */
    public function ajaxRun(): void
    {
        check_ajax_referer('sseo_geo_scan', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to run scans.', 'sseo-ai-saas'));
        }

        $url = sanitize_url($_POST['url'] ?? '');
        $rawKeywords = isset($_POST['keywords']) ? sanitize_textarea_field(wp_unslash($_POST['keywords'])) : '';
        $language = sanitize_text_field($_POST['language'] ?? 'nl');

        $keywords = array_values(array_filter(array_map('trim', explode("\n", $rawKeywords))));

        $result = $this->geoScanner->scan($url, $keywords, $language);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'scan_id'  => $result['scan_id'],
            'redirect' => admin_url('admin.php?page=sseo-ai-geo-scan&view=report&scan_id=' . (int)$result['scan_id']),
        ]);
    }
}

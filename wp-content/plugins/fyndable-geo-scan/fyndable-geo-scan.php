<?php
/**
 * Plugin Name: Fyndable GEO Scan
 * Description: Free GEO Readiness scan widget for fyndable.ai — runs scans via the Fyndable SaaS portal and collects leads (email + consent).
 * Version: 0.1.3
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Fyndable
 * Text Domain: fyndable-geo-scan
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FYNDABLE_GEOSCAN_VERSION', '0.1.3');
define('FYNDABLE_GEOSCAN_PLUGIN_FILE', __FILE__);
define('FYNDABLE_GEOSCAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FYNDABLE_GEOSCAN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Portal connection options
define('FYNDABLE_GEOSCAN_PORTAL_URL_OPTION', 'fyndable_geoscan_portal_url');
define('FYNDABLE_GEOSCAN_API_KEY_OPTION', 'fyndable_geoscan_api_key');

require_once FYNDABLE_GEOSCAN_PLUGIN_DIR . 'includes/settings.php';
require_once FYNDABLE_GEOSCAN_PLUGIN_DIR . 'includes/widget.php';

add_action('plugins_loaded', function (): void {
    $settings = new \FyndableGeoScan\Settings();
    $settings->register();

    $widget = new \FyndableGeoScan\Widget();
    $widget->register();
});

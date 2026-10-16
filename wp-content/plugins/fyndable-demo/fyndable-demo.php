<?php
/**
 * Plugin Name: Fyndable Demo
 * Description: Safe, guided sales demo companion for the Fyndable client plugin.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Fyndable
 * Text Domain: fyndable-demo
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FYNDABLE_DEMO_VERSION', '0.1.0');
define('FYNDABLE_DEMO_FILE', __FILE__);
define('FYNDABLE_DEMO_DIR', plugin_dir_path(__FILE__));
define('FYNDABLE_DEMO_URL', plugin_dir_url(__FILE__));

require_once FYNDABLE_DEMO_DIR . 'includes/class-fyndable-demo-fixtures.php';
require_once FYNDABLE_DEMO_DIR . 'includes/class-fyndable-demo-runtime.php';

add_action('plugins_loaded', static function (): void {
    if (!class_exists('SSEOAIClient\\Client')) {
        add_action('admin_notices', static function (): void {
            if (current_user_can('activate_plugins')) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Fyndable Demo requires the Fyndable client plugin.', 'fyndable-demo') . '</p></div>';
            }
        });
        return;
    }

    (new Fyndable_Demo_Runtime())->register();
});

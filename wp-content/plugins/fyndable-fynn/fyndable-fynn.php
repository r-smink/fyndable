<?php
/**
 * Plugin Name: Fynn — AI Chat Assistant
 * Plugin URI:  https://fyndable.com
 * Description: Geanimeerde AI-chatwidget voor de frontend, aangestuurd door OpenRouter.
 * Version:     0.1.0
 * Author:      Fyndable
 * Text Domain: fyndable-fynn
 * Domain Path: /languages
 * License:     GPL-2.0+
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FYNN_PLUGIN_FILE', __FILE__);
define('FYNN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FYNN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FYNN_VERSION', '0.1.0');

require_once FYNN_PLUGIN_DIR . 'includes/plugin.php';
require_once FYNN_PLUGIN_DIR . 'includes/settings.php';
require_once FYNN_PLUGIN_DIR . 'includes/openrouter-client.php';
require_once FYNN_PLUGIN_DIR . 'includes/assistant.php';
require_once FYNN_PLUGIN_DIR . 'includes/frontend.php';

add_action('plugins_loaded', static function () {
    \Fynn\Plugin::instance();
}, 11);

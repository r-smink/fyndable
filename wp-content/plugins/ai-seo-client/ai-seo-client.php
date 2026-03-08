<?php
/**
 * Plugin Name: AI SEO Client
 * Description: Client plugin for AI SEO SaaS - validates license and provides core SEO features
 * Version: 1.0.0
 * Author: Your Company
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-seo-client
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AISEO_CLIENT_VERSION', '1.0.0');
define('AISEO_CLIENT_PLUGIN_FILE', __FILE__);
define('AISEO_CLIENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AISEO_CLIENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AISEO_CLIENT_LICENSE_OPTION', 'ai_seo_client_license');
define('AISEO_CLIENT_TENANT_OPTION', 'ai_seo_client_tenant');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AISEOClient\\';
    $baseDir = AISEO_CLIENT_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', strtolower($relativeClass)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once AISEO_CLIENT_PLUGIN_DIR . 'includes/client.php';
    $client = new \AISEOClient\Client();
    $client->activate();
});

// Initialize plugin
add_action('plugins_loaded', function () {
    $client = new \AISEOClient\Client();
    $client->init();
});

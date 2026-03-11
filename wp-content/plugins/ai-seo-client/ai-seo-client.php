<?php
/**
 * Plugin Name: SSEO AI
 * Description: Advanced AI-powered SEO plugin with comprehensive optimization features
 * Version: 0.5-beta
 * Author: Rick Smink
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sseo-ai-client
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSEO_AI_CLIENT_VERSION', '0.5.1-beta');
define('SSEO_AI_CLIENT_PLUGIN_FILE', __FILE__);
define('SSEO_AI_CLIENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SSEO_AI_CLIENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSEO_AI_CLIENT_LICENSE_OPTION', 'sseo_ai_client_license');
define('SSEO_AI_CLIENT_TENANT_OPTION', 'sseo_ai_client_tenant');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SSEOAIClient\\';
    $baseDir = SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/';

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
    require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/client.php';
    $client = new \SSEOAIClient\Client();
    $client->activate();
});

// Initialize plugin
add_action('plugins_loaded', function () {
    $client = new \SSEOAIClient\Client();
    $client->init();
});

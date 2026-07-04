<?php
/**
 * Plugin Name: Fynable
 * Description: Advanced AI-powered SEO plugin by Fynable with comprehensive optimization features
 * Version: 1.4.0
 * Author: Fynable
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-seo-client
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSEO_AI_CLIENT_VERSION', '1.4.0');
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
        require_once $file;
    }
});

// Explicitly require core files to ensure they're loaded
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/settings.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/licensevalidator.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/dashboardapi.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/healthlogger.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/llmclient.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/client.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/postmetabox.php';

// Activation hook
register_activation_hook(__FILE__, function () {
    $client = new \SSEOAIClient\Client();
    $client->activate();
});

// Load text domain for translations
add_action('init', function () {
    // Generate MO files from PO files if needed
    require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/translationhelper.php';
    
    $moFile = SSEO_AI_CLIENT_PLUGIN_DIR . 'languages/ai-seo-client-nl_NL.mo';
    $poFile = SSEO_AI_CLIENT_PLUGIN_DIR . 'languages/ai-seo-client-nl_NL.po';
    
    // Generate MO file if it doesn't exist or PO file is newer
    if (!file_exists($moFile) || (file_exists($poFile) && filemtime($poFile) > filemtime($moFile))) {
        \SSEOAIClient\TranslationHelper::generateMoFile($poFile, $moFile);
    }
    
    load_plugin_textdomain('ai-seo-client', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Initialize plugin
add_action('plugins_loaded', function () {
    $client = new \SSEOAIClient\Client();
    $client->init();
});

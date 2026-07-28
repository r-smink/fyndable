<?php
/**
 * Plugin Name: Fyndable SmartSEO Dashboard
 * Description: Multi-tenant license and tenant management dashboard for Fyndable SmartSEO
 * Version: 1.4.3
 * Author: Fyndable
 * Text Domain: sseo-ai-saas
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSEO_AI_SAAS_VERSION', '1.4.3');
define('SSEO_AI_SAAS_PLUGIN_FILE', __FILE__);
define('SSEO_AI_SAAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SSEO_AI_SAAS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SSEOAISaaS\\';
    $baseDir = SSEO_AI_SAAS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', strtolower(str_replace('_', '', $relativeClass))) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function () {
    require_once SSEO_AI_SAAS_PLUGIN_DIR . 'includes/tenantrepository.php';
    require_once SSEO_AI_SAAS_PLUGIN_DIR . 'includes/licensekeygenerator.php';
    $tenants = new \SSEOAISaaS\TenantRepository();
    $tenants->maybeCreateTables();
    $tenants->migrateExistingTables();
    
    // Flush rewrite rules to register REST API routes
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// Load translations
add_action('init', function () {
    load_plugin_textdomain('sseo-ai-saas', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 5);

// Initialize
add_action('plugins_loaded', function () {
    $plugin = new \SSEOAISaaS\Dashboard();
    $plugin->init();
});

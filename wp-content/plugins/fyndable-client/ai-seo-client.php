<?php
/**
 * Plugin Name: Fyndable
 * Description: Advanced AI-powered SEO plugin by Fyndable with comprehensive optimization features
 * Version: 1.9.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Fyndable
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-seo-client
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SSEO_AI_CLIENT_VERSION', '1.8.0');
define('SSEO_AI_CLIENT_PLUGIN_FILE', __FILE__);
define('SSEO_AI_CLIENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SSEO_AI_CLIENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SSEO_AI_CLIENT_LICENSE_OPTION', 'sseo_ai_client_license');
define('SSEO_AI_CLIENT_TENANT_OPTION', 'sseo_ai_client_tenant');

// Default SaaS dashboard URL. Baked in so customers only need to enter their
// license key. Override in wp-config.php with `define('SSEO_AI_DEFAULT_DASHBOARD_URL', 'https://...');`
// for migrations, white-label partners, or local testing.
if (!defined('SSEO_AI_DEFAULT_DASHBOARD_URL')) {
    define('SSEO_AI_DEFAULT_DASHBOARD_URL', 'https://portal.fyndable.ai');
}

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

// Load translations before any included files call __()/_e() so
// WordPress 6.7+ does not trigger _load_textdomain_just_in_time too early.
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/translationhelper.php';
$earlyMoFile = SSEO_AI_CLIENT_PLUGIN_DIR . 'languages/ai-seo-client-nl_NL.mo';
$earlyPoFile = SSEO_AI_CLIENT_PLUGIN_DIR . 'languages/ai-seo-client-nl_NL.po';
if (!file_exists($earlyMoFile) || (file_exists($earlyPoFile) && filemtime($earlyPoFile) > filemtime($earlyMoFile))) {
    \SSEOAIClient\TranslationHelper::generateMoFile($earlyPoFile, $earlyMoFile);
}
load_plugin_textdomain('ai-seo-client', false, dirname(plugin_basename(__FILE__)) . '/languages');

// Explicitly require core files to ensure they're loaded
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/settings.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/licensevalidator.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/dashboardapi.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/healthlogger.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/llmclient.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/client.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/postmetabox.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/fyndabledashboard.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/serankingclient.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/serankingdataclient.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/ahrefsclient.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/seodatadashboard.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/privacyexport.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/reviewprompt.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/seoimporter.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/onboardingwizard.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/updatechecker.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/demomode.php';
require_once SSEO_AI_CLIENT_PLUGIN_DIR . 'includes/mobileapp.php';

// Activation hook
register_activation_hook(__FILE__, function () {
    if (is_multisite()) {
        // Network activation — run on all sites
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog((int) $site->blog_id);
            $client = new \SSEOAIClient\Client();
            $client->activate();
            restore_current_blog();
        }
    } else {
        $client = new \SSEOAIClient\Client();
        $client->activate();
    }
});

// Deactivation hook — clean up cron on multisite
register_deactivation_hook(__FILE__, function () {
    if (is_multisite()) {
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog((int) $site->blog_id);
            wp_clear_scheduled_hook('sseo_ai_client_license_check');
            wp_clear_scheduled_hook('sseo_ai_rank_check_cron');
            restore_current_blog();
        }
    } else {
        wp_clear_scheduled_hook('sseo_ai_client_license_check');
        wp_clear_scheduled_hook('sseo_ai_rank_check_cron');
    }
});

// Initialize plugin
add_action('plugins_loaded', function () {
    $client = new \SSEOAIClient\Client();
    $client->init();
});

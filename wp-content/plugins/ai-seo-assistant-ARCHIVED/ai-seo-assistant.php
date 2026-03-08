<?php
/**
 * Plugin Name: AI SEO Assistant
 * Description: AI-assisted keyword discovery, SERP snapshots, and content prompts with pluggable data providers (self-scrape or API).
 * Version: 0.1.0
 * Author: Your Team
 * License: GPL-2.0+
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-autoloader.php';

// Boot the plugin.
add_action('plugins_loaded', static function () {
    $plugin = new \AISEOAssistant\Plugin(__FILE__);
    $plugin->init();
});

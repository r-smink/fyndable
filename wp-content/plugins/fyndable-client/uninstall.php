<?php
/**
 * Fyndable SEO Plugin — Uninstall
 *
 * Removes all plugin data when uninstalled via WordPress admin.
 * This includes: options, custom tables, cron jobs, post meta, transients.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// WordPress only loads uninstall.php on deletion, not the main plugin file,
// so the constants used below may not be defined yet. Define fallbacks here.
if (!defined('SSEO_AI_CLIENT_LICENSE_OPTION')) {
    define('SSEO_AI_CLIENT_LICENSE_OPTION', 'sseo_ai_client_license');
}
if (!defined('SSEO_AI_CLIENT_TENANT_OPTION')) {
    define('SSEO_AI_CLIENT_TENANT_OPTION', 'sseo_ai_client_tenant');
}

// ── 1. Delete all options ───────────────────────────────────────────

$optionPatterns = [
    'sseo_ai_client_%',
    'sseo_ai_white_label',
    'sseo_ai_wl_%',
    'ai_seo_%',
    'sseo_ai_saas_%',
];

foreach ($optionPatterns as $pattern) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $pattern
    ));
}

// Delete specific known options that may not match patterns above
$specificOptions = [
    SSEO_AI_CLIENT_LICENSE_OPTION ?? 'sseo_ai_client_license',
    SSEO_AI_CLIENT_TENANT_OPTION ?? 'sseo_ai_client_tenant',
    'sseo_ai_client_license_status',
    'sseo_ai_client_license_tier',
    'sseo_ai_client_license_type',
    'sseo_ai_client_license_expires',
    'sseo_ai_client_rate_limit',
    'sseo_ai_client_api_limit',
    'sseo_ai_client_image_api',
    'sseo_ai_client_enabled_features',
    'sseo_ai_client_dashboard_url',
    'sseo_ai_client_settings',
    'sseo_ai_client_temperature',
    'sseo_ai_client_ssl_verify',
    'sseo_ai_white_label',
    'sseo_ai_saas_wl_enabled',
];

foreach ($specificOptions as $option) {
    delete_option($option);
}

// ── 2. Delete all transients ────────────────────────────────────────

$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sseo_ai_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sseo_ai_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_seo_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_seo_%'");

// ── 3. Drop custom database tables ──────────────────────────────────

$tables = [
    $wpdb->prefix . 'sseo_ai_keywords',
    $wpdb->prefix . 'sseo_ai_clusters',
    $wpdb->prefix . 'sseo_ai_ideas',
    $wpdb->prefix . 'sseo_ai_redirects',
    $wpdb->prefix . 'sseo_ai_revisions',
    $wpdb->prefix . 'sseo_ai_rank_history',
    $wpdb->prefix . 'sseo_ai_tracked_keywords',
    $wpdb->prefix . 'sseo_ai_404_logs',
    $wpdb->prefix . 'aiseoclient_snapshots',
    $wpdb->prefix . 'sseo_ai_llm_logs',
    $wpdb->prefix . 'sseo_ai_content_decay',
    $wpdb->prefix . 'sseo_ai_content_trends',
    $wpdb->prefix . 'sseo_ai_brand_visibility',
    $wpdb->prefix . 'sseo_ai_ab_tests',
    $wpdb->prefix . 'sseo_ai_ab_variants',
    $wpdb->prefix . 'sseo_ai_ab_conversions',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// ── 4. Clear scheduled cron jobs ────────────────────────────────────

$cronHooks = [
    'sseo_ai_client_license_check',
    'sseo_ai_rank_check_cron',
    'sseo_ai_daily_report',
    'sseo_ai_weekly_report',
    'sseo_ai_monthly_report',
    'sseo_ai_technical_audit',
    'sseo_ai_detect_orphans',
    'sseo_ai_check_performance',
    'aiseoclient_decay_check',
    'aiseoclient_generate_sitemap',
];

foreach ($cronHooks as $hook) {
    wp_clear_scheduled_hook($hook);
}

// ── 5. Delete post meta ─────────────────────────────────────────────

$metaKeys = [
    '_sseo_ai_focus_keyphrase',
    '_sseo_ai_secondary_keyphrases',
    '_sseo_ai_title',
    '_sseo_ai_description',
    '_sseo_ai_score',
    '_sseo_ai_og_title',
    '_sseo_ai_og_description',
    '_sseo_ai_og_image',
    '_sseo_ai_twitter_title',
    '_sseo_ai_twitter_description',
    '_sseo_ai_twitter_image',
    '_sseo_ai_canonical_url',
    '_sseo_ai_schema_type',
    '_sseo_ai_schema_data',
    '_sseo_ai_faq_schema',
    '_sseo_ai_video_schema',
    '_sseo_ai_local_seo',
    '_sseo_ai_robots_meta',
    '_sseo_ai_content_score',
    '_sseo_ai_topic_model',
    '_sseo_ai_content_brief',
    '_sseo_ai_cluster_id',
    '_sseo_ai_eeat_score',
    '_sseo_ai_readability_score',
    '_sseo_ai_lsi_keywords',
    '_sseo_ai_redirect',
    '_sseo_ai_alt_text',
    '_sseo_ai_generated_content',
    '_sseo_ai_content_decay_score',
    '_sseo_ai_ab_test_id',
    '_sseo_ai_brand_visibility',
    '_aiseo_focus_keyphrase',
    '_aiseo_title',
    '_aiseo_description',
    '_aiseo_score',
];

foreach ($metaKeys as $metaKey) {
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $metaKey
    ));
}

// ── 6. Clear any cached data ────────────────────────────────────────

// wp_cache_flush_group() is only available since WP 6.4; fall back to a full
// object-cache flush to avoid undefined-function fatals on older installs.
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('sseo_ai');
    wp_cache_flush_group('ai_seo');
} else {
    wp_cache_flush();
}

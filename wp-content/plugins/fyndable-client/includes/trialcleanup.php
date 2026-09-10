<?php
namespace SSEOAIClient;

class TrialCleanup
{
    public function run(): void
    {
        global $wpdb;

        // Remove AI-generated posts.
        $generated = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sseo_ai_generated' AND meta_value = '1'"
        );
        foreach ($generated as $postId) {
            wp_delete_post((int) $postId, true);
        }

        // Remove Fyndable postmeta for all posts.
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sseo_ai_%' OR meta_key LIKE '_aiseo_%'");

        // Truncate custom AI tables so reactivation does not need schema recreation.
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
            $wpdb->query("TRUNCATE TABLE {$table}");
        }

        // Clear transients.
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sseo_ai_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sseo_ai_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ai_seo_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ai_seo_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aiseoclient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aiseoclient_%'");

        // Clear plugin options except connection/license keys needed for reactivation.
        $keep = [
            'sseo_ai_client_dashboard_url',
            'sseo_ai_client_license',
            'sseo_ai_client_license_key',
            'sseo_ai_client_tenant',
            'sseo_ai_client_tenant_key',
        ];
        $options = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sseo_ai_client_%' OR option_name LIKE 'ai_seo_%' OR option_name LIKE 'sseo_ai_saas_%'"
        );
        foreach ($options as $optionName) {
            if (!in_array($optionName, $keep, true)) {
                delete_option($optionName);
            }
        }

        // Ensure the license is marked invalid and a trial-expired flag is set.
        update_option('sseo_ai_client_license_status', 'invalid');
        update_option('sseo_ai_client_trial_expired', '1');

        // Clear object cache.
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sseo_ai');
            wp_cache_flush_group('ai_seo');
        } else {
            wp_cache_flush();
        }
    }
}

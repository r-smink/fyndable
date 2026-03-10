<?php

namespace SSEOAIClient;

/**
 * Settings Manager
 * 
 * Handles plugin settings and configuration
 */
class Settings
{
    private const OPTION_GROUP = 'sseo_ai_client_settings';

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
        $value = get_option("sseo_ai_client_{$key}", $default);
        return $value;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, $value): bool
    {
        return update_option("sseo_ai_client_{$key}", $value);
    }

    /**
     * Delete a setting
     */
    public function delete(string $key): bool
    {
        return delete_option("sseo_ai_client_{$key}");
    }

    /**
     * Get dashboard URL
     */
    public function getDashboardUrl(): string
    {
        return get_option('sseo_ai_client_dashboard_url', '');
    }

    /**
     * Get license key (masked for display)
     */
    public function getMaskedLicense(): string
    {
        $key = get_option(SSEO_AI_CLIENT_LICENSE_OPTION, '');
        if (empty($key)) {
            return '';
        }
        return substr($key, 0, 8) . '...' . substr($key, -4);
    }

    /**
     * Get all settings as array
     */
    public function getAll(): array
    {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'sseo_ai_client_%'",
            ARRAY_A
        );

        $settings = [];
        foreach ($results as $row) {
            $key = str_replace('sseo_ai_client_', '', $row['option_name']);
            $settings[$key] = maybe_unserialize($row['option_value']);
        }

        return $settings;
    }

    /**
     * Clear all settings
     */
    public function clearAll(): void
    {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sseo_ai_client_%'");
    }
}

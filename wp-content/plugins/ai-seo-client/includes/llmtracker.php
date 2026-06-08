<?php

namespace SSEOAIClient;

/**
 * LLM Tracker
 *
 * Logs every AI/LLM call for monitoring, debugging and usage tracking.
 */
class LLMTracker
{
    private const TABLE_NAME = 'sseo_ai_llm_log';

    public static function createTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            prompt longtext NOT NULL,
            response longtext,
            model varchar(50) DEFAULT NULL,
            tokens_input int(11) DEFAULT 0,
            tokens_output int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0.000000,
            endpoint varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'success',
            error_message text,
            duration_ms int(11) DEFAULT 0,
            user_id bigint(20) DEFAULT 0,
            post_id bigint(20) DEFAULT 0,
            context varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY model (model),
            KEY status (status),
            KEY post_id (post_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log an LLM call
     */
    public static function log(array $data): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $wpdb->insert($table, [
            'timestamp'     => current_time('mysql'),
            'prompt'        => substr($data['prompt'] ?? '', 0, 65535),
            'response'      => substr($data['response'] ?? '', 0, 65535),
            'model'         => sanitize_text_field($data['model'] ?? ''),
            'tokens_input'  => (int)($data['tokens_input'] ?? 0),
            'tokens_output' => (int)($data['tokens_output'] ?? 0),
            'cost'          => (float)($data['cost'] ?? 0),
            'endpoint'      => sanitize_text_field($data['endpoint'] ?? ''),
            'status'        => sanitize_text_field($data['status'] ?? 'success'),
            'error_message' => sanitize_text_field($data['error_message'] ?? ''),
            'duration_ms'   => (int)($data['duration_ms'] ?? 0),
            'user_id'       => get_current_user_id(),
            'post_id'       => (int)($data['post_id'] ?? 0),
            'context'       => sanitize_text_field($data['context'] ?? ''),
        ], [
            '%s','%s','%s','%s','%d','%d','%f','%s','%s','%s','%d','%d','%d','%s'
        ]);

        return (int)$wpdb->insert_id;
    }

    /**
     * Get recent logs
     */
    public static function getLogs(int $limit = 100, int $offset = 0, string $status = ''): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $where = '';
        if ($status) {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT {$offset}, {$limit}",
            ARRAY_A
        );
    }

    /**
     * Get stats
     */
    public static function getStats(string $period = '24h'): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $since = date('Y-m-d H:i:s', strtotime('-1 ' . ($period === '24h' ? 'day' : ($period === '7d' ? 'week' : 'month'))));

        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE timestamp >= %s", $since));
        $success = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE timestamp >= %s AND status = 'success'", $since));
        $failed = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE timestamp >= %s AND status = 'error'", $since));
        $totalCost = (float)$wpdb->get_var($wpdb->prepare("SELECT SUM(cost) FROM {$table} WHERE timestamp >= %s AND status = 'success'", $since));
        $avgDuration = (int)$wpdb->get_var($wpdb->prepare("SELECT AVG(duration_ms) FROM {$table} WHERE timestamp >= %s", $since));

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'total_cost' => round($totalCost, 4),
            'avg_duration_ms' => $avgDuration,
        ];
    }

    /**
     * Get total count for pagination
     */
    public static function getTotalCount(): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Clear old logs (keep last 90 days)
     */
    public static function prune(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("DELETE FROM {$table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    }
}

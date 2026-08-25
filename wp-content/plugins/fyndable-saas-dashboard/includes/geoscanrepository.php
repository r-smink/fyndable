<?php

namespace SSEOAISaaS;

/**
 * GEO Scan Repository
 *
 * Stores GEO Readiness scan results for prospect URLs with a 7-day retention.
 */
class GeoScanRepository
{
    private const TABLE = 'sseo_ai_geo_scans';
    private const RETENTION_DAYS = 7;

    /**
     * Create the scans table and clean up old records.
     */
    public function maybeCreateTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            keywords text DEFAULT NULL,
            language varchar(10) NOT NULL DEFAULT 'nl',
            status varchar(20) NOT NULL DEFAULT 'completed',
            score tinyint(3) unsigned DEFAULT NULL,
            result longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) $charsetCollate;";

        $wpdb->query($sql);
    }

    /**
     * Insert a completed scan and return the generated id.
     */
    public function insert(string $url, array $keywords, string $language, array $result): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $wpdb->insert(
            $table,
            [
                'url'        => $url,
                'keywords'   => implode(', ', $keywords),
                'language'   => $language,
                'status'     => 'completed',
                'score'      => isset($result['score']) ? (int)$result['score'] : null,
                'result'     => wp_json_encode($result),
                'created_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . self::RETENTION_DAYS . ' days')),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Get a single scan by id.
     */
    public function getById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['result'] = !empty($row['result']) ? json_decode($row['result'], true) : [];

        return $row;
    }

    /**
     * Get recent scans ordered by created_at desc.
     */
    public function getRecent(int $limit = 20): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['result'] = !empty($row['result']) ? json_decode($row['result'], true) : [];
        }

        return $rows ?: [];
    }

    /**
     * Delete scans older than the retention period.
     */
    public function deleteExpired(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %s", current_time('mysql'))
        );
    }
}

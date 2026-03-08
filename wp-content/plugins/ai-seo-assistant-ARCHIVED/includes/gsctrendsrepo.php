<?php

namespace AISEOAssistant;

class GscTrendsRepo
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aiseoassistant_gsc_trends';
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            query varchar(255) NOT NULL,
            clicks int unsigned DEFAULT 0,
            impressions int unsigned DEFAULT 0,
            ctr float DEFAULT 0,
            position float DEFAULT 0,
            date date NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY query (query),
            KEY date (date)
        ) $charset;";
        dbDelta($sql);
    }

    public function insertRows(array $rows, string $date): void
    {
        global $wpdb;
        foreach ($rows as $r) {
            $wpdb->insert($this->table, [
                'query' => $r['keys'][0] ?? '',
                'clicks' => $r['clicks'] ?? 0,
                'impressions' => $r['impressions'] ?? 0,
                'ctr' => $r['ctr'] ?? 0,
                'position' => $r['position'] ?? 0,
                'date' => $date,
            ], ['%s','%d','%d','%f','%f','%s']);
        }
    }

    public function trend(string $query, int $days = 30): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT date, position, ctr FROM {$this->table} WHERE query=%s ORDER BY date DESC LIMIT %d", $query, $days), ARRAY_A) ?: [];
    }
}

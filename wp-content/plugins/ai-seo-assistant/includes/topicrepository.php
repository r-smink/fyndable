<?php

namespace AISEOAssistant;

class TopicRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aiseoassistant_topics';
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cluster varchar(255) NOT NULL,
            keyword varchar(255) NOT NULL,
            source varchar(50) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cluster (cluster),
            KEY keyword (keyword)
        ) $charset;";
        dbDelta($sql);
    }

    public function saveCluster(string $cluster, array $keywords, string $source): void
    {
        global $wpdb;
        foreach ($keywords as $kw) {
            $wpdb->insert($this->table, [
                'cluster' => $cluster,
                'keyword' => $kw,
                'source' => $source,
                'created_at' => current_time('mysql', true),
            ], ['%s','%s','%s','%s']);
        }
    }

    public function latest(int $limit = 100): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit), ARRAY_A) ?: [];
    }
}

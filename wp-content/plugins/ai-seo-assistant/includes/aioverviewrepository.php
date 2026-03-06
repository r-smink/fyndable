<?php

namespace AISEOAssistant;

class AiOverviewRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aiseoassistant_ai_overview';
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            provider varchar(50) NOT NULL,
            ai_overview tinyint(1) NOT NULL DEFAULT 0,
            citations longtext NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY keyword (keyword),
            KEY created_at (created_at)
        ) $charsetCollate;";
        dbDelta($sql);
    }

    public function save(string $keyword, string $provider, bool $flag, array $citations = []): void
    {
        global $wpdb;
        $wpdb->insert(
            $this->table,
            [
                'keyword'    => $keyword,
                'provider'   => $provider,
                'ai_overview'=> $flag ? 1 : 0,
                'citations'  => wp_json_encode($citations),
                'created_at' => current_time('mysql', true),
            ],
            ['%s','%s','%d','%s','%s']
        );
    }

    public function latest(int $limit = 50): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit),
            ARRAY_A
        ) ?: [];
    }
}

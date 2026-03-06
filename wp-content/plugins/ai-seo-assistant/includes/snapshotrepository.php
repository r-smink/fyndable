<?php

namespace AISEOAssistant;

class SnapshotRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aiseoassistant_snapshots';
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
            results longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY keyword (keyword),
            KEY created_at (created_at)
        ) $charsetCollate;";

        dbDelta($sql);
    }

    public function save(string $keyword, string $provider, array $results): void
    {
        global $wpdb;
        $wpdb->insert(
            $this->table,
            [
                'keyword'   => $keyword,
                'provider'  => $provider,
                'results'   => wp_json_encode($results),
                'created_at'=> current_time('mysql', true),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function latest(int $limit = 50, string $keywordLike = ''): array
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if ($keywordLike !== '') {
            $sql .= " WHERE keyword LIKE %s";
            $params[] = '%' . $wpdb->esc_like($keywordLike) . '%';
        }
        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);
        return array_map(static function ($row) {
            $row['results'] = json_decode($row['results'], true) ?? [];
            return $row;
        }, $rows ?: []);
    }

    /**
     * Count snapshots by provider.
     * @return array<string,int>
     */
    public function countsByProvider(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT provider, COUNT(*) as c FROM {$this->table} GROUP BY provider", ARRAY_A);
        $out = [];
        foreach ($rows ?: [] as $row) {
            $out[$row['provider']] = (int)$row['c'];
        }
        return $out;
    }

    /**
     * Recent keywords list.
     * @return array<int,string>
     */
    public function latestKeywords(int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_col($wpdb->prepare("SELECT keyword FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit));
    }

    /**
     * Basic metrics for dashboard.
     */
    public function metrics(int $limit = 200): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT results FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit),
            ARRAY_A
        );

        $total = 0;
        $posSum = 0;
        $top3 = 0;

        foreach ($rows ?: [] as $row) {
            $results = json_decode($row['results'], true) ?: [];
            if (!is_array($results) || empty($results)) {
                continue;
            }
            $firstPos = $results[0]['position'] ?? null;
            $bestPos = $firstPos;
            foreach ($results as $item) {
                if (isset($item['position'])) {
                    $bestPos = min($bestPos ?? $item['position'], $item['position']);
                }
            }
            if ($bestPos !== null) {
                $total++;
                $posSum += (int)$bestPos;
                if ($bestPos <= 3) {
                    $top3++;
                }
            }
        }

        return [
            'tracked' => $total,
            'avg_pos' => $total ? round($posSum / $total, 2) : null,
            'top3_pct' => $total ? round(($top3 / $total) * 100, 1) : null,
        ];
    }

    /**
     * Average best position per day (last N days).
     * @return array<int,array{date:string,avg:float}>
     */
    public function dailyAvgBest(int $days = 14): array
    {
        global $wpdb;
        $sql = $wpdb->prepare("
            SELECT DATE(created_at) as d, results
            FROM {$this->table}
            WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
        ", $days);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $bucket = [];
        foreach ($rows ?: [] as $row) {
            $res = json_decode($row['results'], true) ?: [];
            $best = null;
            foreach ($res as $item) {
                if (isset($item['position'])) {
                    $best = $best === null ? $item['position'] : min($best, $item['position']);
                }
            }
            if ($best !== null) {
                $bucket[$row['d']][] = $best;
            }
        }
        $out = [];
        foreach ($bucket as $date => $arr) {
            $out[] = ['date' => $date, 'avg' => round(array_sum($arr)/count($arr), 2)];
        }
        usort($out, fn($a,$b)=>strcmp($a['date'],$b['date']));
        return $out;
    }
}

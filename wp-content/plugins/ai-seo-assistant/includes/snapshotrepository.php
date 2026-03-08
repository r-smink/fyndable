<?php

namespace AISEOAssistant;

class SnapshotRepository
{
    private string $table;
    private ?TenantRepository $tenants = null;

    public function __construct(?TenantRepository $tenants = null)
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'aiseoassistant_snapshots';
        $this->tenants = $tenants;
    }
    
    /**
     * Get current tenant key if available
     */
    private function getTenantKey(): ?string
    {
        return $this->tenants?->getCurrentTenant();
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id varchar(64) DEFAULT NULL,
            keyword varchar(255) NOT NULL,
            provider varchar(50) NOT NULL,
            results longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY tenant_id (tenant_id),
            KEY keyword (keyword),
            KEY created_at (created_at)
        ) $charsetCollate;";

        dbDelta($sql);
    }

    public function save(string $keyword, string $provider, array $results): void
    {
        global $wpdb;
        $data = [
            'keyword'   => $keyword,
            'provider'  => $provider,
            'results'   => wp_json_encode($results),
            'created_at'=> current_time('mysql', true),
        ];
        
        // Add tenant isolation if available
        $tenantKey = $this->getTenantKey();
        if ($tenantKey) {
            $data['tenant_id'] = $tenantKey;
        }
        
        $wpdb->insert($this->table, $data);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function latest(int $limit = 50, string $keywordLike = ''): array
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Add tenant isolation
        $tenantKey = $this->getTenantKey();
        if ($tenantKey) {
            $sql .= " AND tenant_id = %s";
            $params[] = $tenantKey;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        
        if ($keywordLike !== '') {
            $sql .= " AND keyword LIKE %s";
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
        $sql = "SELECT provider, COUNT(*) as c FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Add tenant isolation
        $tenantKey = $this->getTenantKey();
        if ($tenantKey) {
            $sql .= " AND tenant_id = %s";
            $params[] = $tenantKey;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        
        $sql .= " GROUP BY provider";
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
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
        $sql = "SELECT keyword FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Add tenant isolation
        $tenantKey = $this->getTenantKey();
        if ($tenantKey) {
            $sql .= " AND tenant_id = %s";
            $params[] = $tenantKey;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        return $wpdb->get_col($wpdb->prepare($sql, $params));
    }

    /**
     * Basic metrics for dashboard.
     */
    public function metrics(int $limit = 200): array
    {
        global $wpdb;
        $sql = "SELECT results FROM {$this->table} WHERE 1=1";
        $params = [];
        
        // Add tenant isolation
        $tenantKey = $this->getTenantKey();
        if ($tenantKey) {
            $sql .= " AND tenant_id = %s";
            $params[] = $tenantKey;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

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
        $sql = "SELECT DATE(created_at) as d, results FROM {$this->table} 
                WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)";
        $params = [$days];
        
        // Add tenant isolation
        $tenantKey = $this->getTenantKey();
        if ($tenantKey) {
            $sql .= " AND tenant_id = %s";
            $params[] = $tenantKey;
        } else {
            $sql .= " AND tenant_id IS NULL";
        }
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
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

<?php

namespace SSEOAISaaS;

/**
 * GEO Scan Repository
 *
 * Stores GEO Readiness scan results. Scans run asynchronously:
 * queued → running → completed|failed, with progress tracked per scan.
 * Admin scans expire after 7 days, website (lead) scans after 90 days.
 */
class GeoScanRepository
{
    private const TABLE = 'sseo_ai_geo_scans';
    private const RETENTION_DAYS = 7;
    private const WEBSITE_RETENTION_DAYS = 90;

    /**
     * Create the scans table, migrate existing installs and clean up old records.
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
            progress tinyint(3) unsigned NOT NULL DEFAULT 0,
            progress_label varchar(255) NOT NULL DEFAULT '',
            error varchar(500) DEFAULT NULL,
            source varchar(20) NOT NULL DEFAULT 'admin',
            email varchar(255) DEFAULT NULL,
            consent tinyint(1) NOT NULL DEFAULT 0,
            consent_at datetime DEFAULT NULL,
            meta text DEFAULT NULL,
            score tinyint(3) unsigned DEFAULT NULL,
            result longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_at (created_at),
            KEY expires_at (expires_at),
            KEY status (status),
            KEY source (source),
            KEY email (email)
        ) $charsetCollate;";

        $wpdb->query($sql);

        $this->migrateExistingTable();
    }

    /**
     * Add async/lead columns to installs that predate them.
     */
    private function migrateExistingTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$exists) {
            return;
        }

        $hasColumn = function (string $column) use ($wpdb, $table): bool {
            $found = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s",
                $table,
                $column
            ));
            return !empty($found);
        };

        if (!$hasColumn('progress')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN progress tinyint(3) unsigned NOT NULL DEFAULT 0 AFTER status");
        }
        if (!$hasColumn('progress_label')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN progress_label varchar(255) NOT NULL DEFAULT '' AFTER progress");
        }
        if (!$hasColumn('error')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN error varchar(500) DEFAULT NULL AFTER progress_label");
        }
        if (!$hasColumn('source')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN source varchar(20) NOT NULL DEFAULT 'admin' AFTER error");
            $wpdb->query("ALTER TABLE $table ADD KEY source (source)");
        }
        if (!$hasColumn('email')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN email varchar(255) DEFAULT NULL AFTER source");
            $wpdb->query("ALTER TABLE $table ADD KEY email (email)");
        }
        if (!$hasColumn('consent')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN consent tinyint(1) NOT NULL DEFAULT 0 AFTER email");
        }
        if (!$hasColumn('consent_at')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN consent_at datetime DEFAULT NULL AFTER consent");
        }
        if (!$hasColumn('meta')) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN meta text DEFAULT NULL AFTER consent_at");
        }
    }

    /**
     * Create a queued scan row and return its id.
     *
     * @param array $meta Optional: source ('admin'|'website'), email, consent,
     *                    ip, user_agent.
     */
    public function insertQueued(string $url, array $keywords, string $language, array $meta = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $source = ($meta['source'] ?? 'admin') === 'website' ? 'website' : 'admin';
        $retentionDays = $source === 'website' ? self::WEBSITE_RETENTION_DAYS : self::RETENTION_DAYS;
        $consent = !empty($meta['consent']);

        $wpdb->insert(
            $table,
            [
                'url'            => $url,
                'keywords'       => implode(', ', $keywords),
                'language'       => $language,
                'status'         => 'queued',
                'progress'       => 0,
                'progress_label' => __('In wachtrij', 'sseo-ai-saas'),
                'source'         => $source,
                'email'          => !empty($meta['email']) ? sanitize_email($meta['email']) : null,
                'consent'        => $consent ? 1 : 0,
                'consent_at'     => $consent ? current_time('mysql') : null,
                'meta'           => wp_json_encode([
                    'ip'         => $meta['ip'] ?? '',
                    'user_agent' => $meta['user_agent'] ?? '',
                    'name'       => sanitize_text_field($meta['name'] ?? ''),
                    'company'    => sanitize_text_field($meta['company'] ?? ''),
                    'keywords'   => $keywords,
                ]),
                'created_at'     => current_time('mysql'),
                'expires_at'     => gmdate('Y-m-d H:i:s', strtotime('+' . $retentionDays . ' days')),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        return (int)$wpdb->insert_id;
    }

    /**
     * Insert a completed scan and return the generated id (legacy/synchronous path).
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
                'progress'   => 100,
                'score'      => isset($result['score']) ? (int)$result['score'] : null,
                'result'     => wp_json_encode($result),
                'created_at' => current_time('mysql'),
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+' . self::RETENTION_DAYS . ' days')),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        return (int)$wpdb->insert_id;
    }

    public function markRunning(int $id): void
    {
        $this->updateFields($id, [
            'status'         => 'running',
            'progress'       => 1,
            'progress_label' => __('Scan wordt gestart…', 'sseo-ai-saas'),
        ]);
    }

    public function updateProgress(int $id, int $progress, string $label): void
    {
        $this->updateFields($id, [
            'progress'       => max(0, min(100, $progress)),
            'progress_label' => $label,
        ]);
    }

    public function markCompleted(int $id, array $result): void
    {
        $this->updateFields($id, [
            'status'         => 'completed',
            'progress'       => 100,
            'progress_label' => __('Voltooid', 'sseo-ai-saas'),
            'score'          => isset($result['score']) ? (int)$result['score'] : null,
            'result'         => wp_json_encode($result),
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->updateFields($id, [
            'status'         => 'failed',
            'error'          => mb_substr($error, 0, 500),
            'progress_label' => __('Mislukt', 'sseo-ai-saas'),
        ]);
    }

    /**
     * Put a queued/stale scan back on the queue.
     */
    public function requeue(int $id): void
    {
        $this->updateFields($id, [
            'status'         => 'queued',
            'progress'       => 0,
            'progress_label' => __('In wachtrij', 'sseo-ai-saas'),
            'error'          => null,
        ]);
    }

    private function updateFields(int $id, array $fields): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $formats = [];
        foreach ($fields as $value) {
            $formats[] = is_int($value) ? '%d' : '%s';
        }

        $wpdb->update($table, $fields, ['id' => $id], $formats, ['%d']);
    }

    /**
     * Lightweight status row for polling (no decoded result).
     */
    public function getStatus(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, url, keywords, language, status, progress, progress_label, error, source, email, consent, score, created_at
                 FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Oldest queued scan (for the cron sweep).
     */
    public function getNextQueued(): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $row = $wpdb->get_row(
            "SELECT id FROM {$table} WHERE status = 'queued' ORDER BY created_at ASC LIMIT 1",
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Ids of scans stuck in 'queued' longer than $seconds (cron probably missed them).
     */
    public function getStaleQueued(int $seconds = 120): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // created_at is stored in WP local time (current_time('mysql')) — the
        // comparison must use the same clock or the sweep fires hours late.
        return $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE status = 'queued' AND created_at < %s",
            wp_date('Y-m-d H:i:s', time() - $seconds)
        )) ?: [];
    }

    /**
     * Ids of scans stuck in 'running' longer than $seconds (sweep marks them failed).
     */
    public function getStaleRunning(int $seconds = 900): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE status = 'running' AND created_at < %s",
            wp_date('Y-m-d H:i:s', time() - $seconds)
        )) ?: [];
    }

    /**
     * Whether a non-expired website scan exists for this email address.
     */
    public function emailHasActiveScan(string $email): bool
    {
        return $this->findActiveWebsiteScanId($email) !== null;
    }

    /**
     * Id of the non-expired website scan for this email address, if any.
     */
    public function findActiveWebsiteScanId(string $email): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // Failed scans never block a resubmit — the visitor can just try again.
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE source = 'website' AND email = %s AND expires_at > %s
             AND status != 'failed'
             ORDER BY id DESC
             LIMIT 1",
            $email,
            gmdate('Y-m-d H:i:s')
        ));

        return $id ? (int)$id : null;
    }

    /**
     * Get a single scan by id (decoded result for the report renderer).
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
        $row['meta'] = !empty($row['meta']) ? json_decode($row['meta'], true) : [];

        return $row;
    }

    /**
     * Get recent scans ordered by created_at desc, filtered by source.
     */
    public function getRecent(int $limit = 20, string $source = 'admin'): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE source = %s ORDER BY created_at DESC LIMIT %d",
                $source,
                $limit
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['result'] = !empty($row['result']) ? json_decode($row['result'], true) : [];
            $row['meta'] = !empty($row['meta']) ? json_decode($row['meta'], true) : [];
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
            $wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %s", gmdate('Y-m-d H:i:s'))
        );
    }
}

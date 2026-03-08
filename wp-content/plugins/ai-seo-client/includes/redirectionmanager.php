<?php

namespace AISEOClient;

class RedirectionManager
{
    private Settings $settings;
    private string $tableName;

    public function __construct(Settings $settings)
    {
        global $wpdb;
        $this->settings = $settings;
        $this->tableName = $wpdb->prefix . 'aiseo_redirects';
    }

    public function register(): void
    {
        add_action('init', [$this, 'checkRedirect'], 1);
        add_action('admin_init', [$this, 'maybeCreateTable']);
        add_action('template_redirect', [$this, 'handle404Redirect'], 1);
        add_filter('wp_insert_post_data', [$this, 'trackSlugChange'], 10, 2);
        add_action('post_updated', [$this, 'autoCreateRedirectOnSlugChange'], 10, 3);
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(500) NOT NULL,
            target_url varchar(500) NOT NULL,
            redirect_type int(3) NOT NULL DEFAULT 301,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            last_accessed datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            enabled tinyint(1) NOT NULL DEFAULT 1,
            regex tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY source_url (source_url(255)),
            KEY enabled (enabled)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function checkRedirect(): void
    {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $requestUri = $this->getRequestUri();
        $redirect = $this->findRedirect($requestUri);

        if ($redirect) {
            $this->incrementHit($redirect['id']);
            wp_redirect($redirect['target_url'], $redirect['redirect_type']);
            exit;
        }
    }

    public function handle404Redirect(): void
    {
        if (!is_404()) {
            return;
        }

        // Try to find similar URL
        $requestUri = $this->getRequestUri();
        $suggestion = $this->findSimilarUrl($requestUri);

        if ($suggestion) {
            wp_redirect($suggestion, 301);
            exit;
        }
    }

    private function getRequestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = strtok($uri, '?');
        return trim($uri, '/');
    }

    public function findRedirect(string $source): ?array
    {
        global $wpdb;

        // Exact match
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tableName} WHERE source_url = %s AND enabled = 1", $source),
            ARRAY_A
        );
        if ($row) {
            return $row;
        }

        // With leading slash
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tableName} WHERE source_url = %s AND enabled = 1", '/' . $source),
            ARRAY_A
        );
        if ($row) {
            return $row;
        }

        // Regex patterns
        $patterns = $wpdb->get_results("SELECT * FROM {$this->tableName} WHERE regex = 1 AND enabled = 1", ARRAY_A);
        foreach ($patterns as $pattern) {
            if (@preg_match('#' . $pattern['source_url'] . '#', $source)) {
                return $pattern;
            }
        }

        return null;
    }

    public function findSimilarUrl(string $requestUri): ?string
    {
        global $wpdb;

        // Check posts with similar slugs
        $slug = basename($requestUri);
        $like = '%' . $wpdb->esc_like($slug) . '%';

        $postId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE %s AND post_status = 'publish' LIMIT 1",
                $like
            )
        );

        if ($postId) {
            return get_permalink($postId);
        }

        return null;
    }

    public function add(string $source, string $target, int $type = 301, bool $regex = false): int
    {
        global $wpdb;

        $source = trim($source, '/');
        $target = trim($target, '/');

        // Check if exists
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$this->tableName} WHERE source_url = %s", $source)
        );

        if ($exists) {
            $wpdb->update(
                $this->tableName,
                [
                    'target_url' => $target,
                    'redirect_type' => $type,
                    'regex' => $regex ? 1 : 0,
                ],
                ['id' => $exists]
            );
            return $exists;
        }

        $wpdb->insert(
            $this->tableName,
            [
                'source_url' => $source,
                'target_url' => $target,
                'redirect_type' => $type,
                'regex' => $regex ? 1 : 0,
            ]
        );

        return $wpdb->insert_id;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete($this->tableName, ['id' => $id], ['%d']) !== false;
    }

    public function update(int $id, array $data): bool
    {
        global $wpdb;
        return $wpdb->update($this->tableName, $data, ['id' => $id]) !== false;
    }

    public function getAll(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (isset($filters['enabled'])) {
            $where[] = 'enabled = %d';
            $params[] = $filters['enabled'] ? 1 : 0;
        }

        if (!empty($filters['search'])) {
            $where[] = '(source_url LIKE %s OR target_url LIKE %s)';
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function getStats(): array
    {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName}");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName} WHERE enabled = 1");
        $totalHits = $wpdb->get_var("SELECT SUM(hits) FROM {$this->tableName}") ?: 0;

        return [
            'total' => (int)$total,
            'active' => (int)$active,
            'total_hits' => (int)$totalHits,
        ];
    }

    private function incrementHit(int $id): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName} SET hits = hits + 1, last_accessed = NOW() WHERE id = %d",
            $id
        ));
    }

    public function trackSlugChange(array $data, array $postarr): array
    {
        if (empty($postarr['ID'])) {
            return $data;
        }

        $oldPost = get_post($postarr['ID']);
        if (!$oldPost || $oldPost->post_name === $data['post_name']) {
            return $data;
        }

        // Store for later use in post_updated
        update_post_meta($postarr['ID'], '_aiseo_old_slug', $oldPost->post_name);

        return $data;
    }

    public function autoCreateRedirectOnSlugChange(int $postId, \WP_Post $postAfter, \WP_Post $postBefore): void
    {
        if ($postAfter->post_name === $postBefore->post_name) {
            return;
        }

        // Only for published posts
        if ($postAfter->post_status !== 'publish') {
            return;
        }

        $oldUrl = get_permalink($postBefore->ID);
        $newUrl = get_permalink($postAfter->ID);

        if ($oldUrl && $newUrl && $oldUrl !== $newUrl) {
            $this->add($oldUrl, $newUrl, 301);
        }
    }

    public function export(): string
    {
        $redirects = $this->getAll();
        $csv = "source_url,target_url,redirect_type,hits,created_at,enabled\n";

        foreach ($redirects as $r) {
            $csv .= sprintf(
                "\"%s\",\"%s\",%d,%d,\"%s\",%d\n",
                $r['source_url'],
                $r['target_url'],
                $r['redirect_type'],
                $r['hits'],
                $r['created_at'],
                $r['enabled']
            );
        }

        return $csv;
    }

    public function import(string $csvContent): array
    {
        $lines = explode("\n", trim($csvContent));
        $imported = 0;
        $errors = [];

        foreach ($lines as $i => $line) {
            if ($i === 0 || empty($line)) {
                continue;
            }

            $parts = str_getcsv($line);
            if (count($parts) < 3) {
                $errors[] = "Line {$i}: Invalid format";
                continue;
            }

            $source = trim($parts[0], '/');
            $target = trim($parts[1], '/');
            $type = (int)$parts[2] ?: 301;

            $this->add($source, $target, $type);
            $imported++;
        }

        return ['imported' => $imported, 'errors' => $errors];
    }
}

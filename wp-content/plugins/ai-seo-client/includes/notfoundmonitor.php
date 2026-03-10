<?php

namespace SSEOAIClient;

class NotFoundMonitor
{
    private Settings $settings;
    private string $tableName;

    public function __construct(Settings $settings)
    {
        global $wpdb;
        $this->settings = $settings;
        $this->tableName = $wpdb->prefix . 'sseo_ai_404s';
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'log404'], 100);
        add_action('admin_init', [$this, 'maybeCreateTable']);
        add_action('wp', [$this, 'checkBrokenLinksInContent'], 100);
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            referrer varchar(500) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            hit_count bigint(20) unsigned NOT NULL DEFAULT 1,
            first_hit datetime DEFAULT CURRENT_TIMESTAMP,
            last_hit datetime DEFAULT CURRENT_TIMESTAMP,
            resolved tinyint(1) NOT NULL DEFAULT 0,
            redirect_id bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY (id),
            KEY url (url(255)),
            KEY resolved (resolved),
            KEY hit_count (hit_count)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function log404(): void
    {
        if (!is_404()) {
            return;
        }

        // Don't log common 404s
        $url = $_SERVER['REQUEST_URI'] ?? '';
        if ($this->isCommon404($url)) {
            return;
        }

        global $wpdb;

        $url = esc_url_raw($url);
        $referrer = esc_url_raw(wp_get_referer() ?: ($_SERVER['HTTP_REFERER'] ?? ''));
        $ua = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = $this->getClientIp();

        // Check if already logged
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->tableName} WHERE url = %s AND resolved = 0",
            $url
        ));

        if ($exists) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->tableName} SET hit_count = hit_count + 1, last_hit = NOW() WHERE id = %d",
                $exists
            ));
        } else {
            $wpdb->insert($this->tableName, [
                'url' => $url,
                'referrer' => $referrer,
                'user_agent' => $ua,
                'ip_address' => $ip,
            ]);
        }
    }

    public function checkBrokenLinksInContent(): void
    {
        if (!is_singular()) {
            return;
        }

        // Only run periodically
        $lastCheck = get_post_meta(get_the_ID(), '_sseo_ai_link_check', true);
        if ($lastCheck && strtotime($lastCheck) > strtotime('-1 day')) {
            return;
        }

        global $post;
        $broken = $this->scanForBrokenLinks($post->post_content);

        if ($broken) {
            update_post_meta($post->ID, '_sseo_ai_broken_links', $broken);
        }

        update_post_meta($post->ID, '_sseo_ai_link_check', current_time('mysql'));
    }

    public function scanForBrokenLinks(string $content): array
    {
        $broken = [];

        // Find all links
        preg_match_all('/href=[\'"](.*?)[\'"]/i', $content, $matches);

        foreach ($matches[1] as $url) {
            // Skip external links in this basic check
            if (strpos($url, home_url()) !== 0 && strpos($url, '/') !== 0) {
                continue;
            }

            // Convert to path
            $path = str_replace(home_url(), '', $url);
            $path = trim($path, '/');

            // Check if page exists
            $exists = url_to_postid($url);
            if (!$exists && $path) {
                // Try to find the slug
                $parts = explode('/', $path);
                $slug = end($parts);

                $found = get_posts([
                    'name' => $slug,
                    'post_type' => 'any',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                ]);

                if (!$found) {
                    $broken[] = [
                        'url' => $url,
                        'status' => '404',
                        'context' => $this->getLinkContext($content, $url),
                    ];
                }
            }
        }

        return $broken;
    }

    private function getLinkContext(string $content, string $url): string
    {
        $pos = strpos($content, $url);
        if ($pos === false) {
            return '';
        }

        $start = max(0, $pos - 50);
        $length = min(100, strlen($content) - $start);
        return substr($content, $start, $length);
    }

    private function isCommon404(string $url): bool
    {
        $patterns = [
            '/wp-includes/',
            '/wp-admin/',
            '/wp-content/',
            '.php',
            '/xmlrpc.php',
            '/robots.txt',
            '/favicon.ico',
            'wp-login',
            '/admin/',
        ];

        foreach ($patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public function getAll(array $filters = []): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (isset($filters['resolved'])) {
            $where[] = 'resolved = %d';
            $params[] = $filters['resolved'] ? 1 : 0;
        }

        if (!empty($filters['url'])) {
            $where[] = 'url LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['url']) . '%';
        }

        $sql = "SELECT * FROM {$this->tableName} WHERE " . implode(' AND ', $where) . " ORDER BY hit_count DESC, last_hit DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function getStats(): array
    {
        global $wpdb;

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName}") ?: 0;
        $unresolved = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName} WHERE resolved = 0") ?: 0;
        $totalHits = $wpdb->get_var("SELECT SUM(hit_count) FROM {$this->tableName}") ?: 0;
        $mostHit = $wpdb->get_row("SELECT url, hit_count FROM {$this->tableName} ORDER BY hit_count DESC LIMIT 1", ARRAY_A);

        return [
            'total' => (int)$total,
            'unresolved' => (int)$unresolved,
            'resolved' => $total - $unresolved,
            'total_hits' => (int)$totalHits,
            'most_hit' => $mostHit,
        ];
    }

    public function resolve(int $id, ?int $redirectId = null): bool
    {
        global $wpdb;

        $data = ['resolved' => 1];
        if ($redirectId) {
            $data['redirect_id'] = $redirectId;
        }

        return $wpdb->update($this->tableName, $data, ['id' => $id]) !== false;
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return $wpdb->delete($this->tableName, ['id' => $id]) !== false;
    }

    public function clearResolved(): int
    {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$this->tableName} WHERE resolved = 1");
    }

    public function getTop404s(int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->tableName} WHERE resolved = 0 ORDER BY hit_count DESC LIMIT %d", $limit),
            ARRAY_A
        ) ?: [];
    }

    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }
}

<?php

namespace SSEOAIClient;

class RedirectionManager
{
    private Settings $settings;
    private string $tableName;

    public function __construct(Settings $settings)
    {
        global $wpdb;
        $this->settings = $settings;
        $this->tableName = $wpdb->prefix . 'sseo_ai_redirects';
    }

    public function register(): void
    {
        add_action('init', [$this, 'checkRedirect'], 1);
        add_action('admin_init', [$this, 'maybeCreateTable']);
        add_action('template_redirect', [$this, 'handle404Redirect'], 1);
        add_filter('wp_insert_post_data', [$this, 'trackSlugChange'], 10, 2);
        add_action('post_updated', [$this, 'autoCreateRedirectOnSlugChange'], 10, 3);
        add_action('admin_post_sseo_ai_redirect_add', [$this, 'handleAddRedirect']);
        add_action('admin_post_sseo_ai_redirect_delete', [$this, 'handleDeleteRedirect']);
        add_action('admin_post_sseo_ai_redirect_toggle', [$this, 'handleToggleRedirect']);
        add_action('admin_post_sseo_ai_redirect_import', [$this, 'handleImportRedirects']);
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->tableName} (
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
        update_post_meta($postarr['ID'], '_sseo_ai_old_slug', $oldPost->post_name);

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

    public function handleAddRedirect(): void
    {
        check_admin_referer('sseo_ai_redirect_add');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $source = esc_url_raw($_POST['source_url'] ?? '');
        $target = esc_url_raw($_POST['target_url'] ?? '');
        $type = (int) ($_POST['redirect_type'] ?? 301);
        $regex = isset($_POST['regex']);
        if ($source && $target) {
            $this->add($source, $target, $type, $regex);
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-redirects&added=1'));
        exit;
    }

    public function handleDeleteRedirect(): void
    {
        check_admin_referer('sseo_ai_redirect_delete_' . ($_POST['id'] ?? 0));
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $this->delete($id);
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-redirects&deleted=1'));
        exit;
    }

    public function handleToggleRedirect(): void
    {
        check_admin_referer('sseo_ai_redirect_toggle_' . ($_POST['id'] ?? 0));
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $enabled = (int) ($_POST['enabled'] ?? 0);
        if ($id) {
            $this->update($id, ['enabled' => $enabled ? 0 : 1]);
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-redirects'));
        exit;
    }

    public function handleImportRedirects(): void
    {
        check_admin_referer('sseo_ai_redirect_import');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);
            $result = $this->import($content);
            wp_redirect(admin_url('admin.php?page=ai-seo-redirects&imported=' . $result['imported']));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=ai-seo-redirects&error=1'));
        exit;
    }

    public function renderAdminPage(): void
    {
        $stats = $this->getStats();
        $search = sanitize_text_field($_GET['s'] ?? '');
        $redirects = $this->getAll($search ? ['search' => $search] : []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Redirect Manager', 'ai-seo-client'); ?></h1>

            <?php if (isset($_GET['added'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Redirect added.', 'ai-seo-client'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Redirect deleted.', 'ai-seo-client'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['imported'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('%d redirects imported.', 'ai-seo-client'), (int)$_GET['imported'])); ?></p></div>
            <?php endif; ?>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin:20px 0;">
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#2271b1;"><?php echo esc_html($stats['total']); ?></div>
                    <div><?php esc_html_e('Total Redirects', 'ai-seo-client'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#00a32a;"><?php echo esc_html($stats['active']); ?></div>
                    <div><?php esc_html_e('Active', 'ai-seo-client'); ?></div>
                </div>
                <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;border-radius:4px;text-align:center;">
                    <div style="font-size:28px;font-weight:bold;color:#d63638;"><?php echo esc_html($stats['total_hits']); ?></div>
                    <div><?php esc_html_e('Total Hits', 'ai-seo-client'); ?></div>
                </div>
            </div>

            <!-- Add new redirect -->
            <h2><?php esc_html_e('Add New Redirect', 'ai-seo-client'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sseo_ai_redirect_add'); ?>
                <input type="hidden" name="action" value="sseo_ai_redirect_add">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="source_url"><?php esc_html_e('Source URL', 'ai-seo-client'); ?></label></th>
                        <td><input type="text" name="source_url" id="source_url" class="regular-text" placeholder="/old-page or /category/old-post"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="target_url"><?php esc_html_e('Target URL', 'ai-seo-client'); ?></label></th>
                        <td><input type="text" name="target_url" id="target_url" class="regular-text" placeholder="/new-page or https://example.com/page"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="redirect_type"><?php esc_html_e('Redirect Type', 'ai-seo-client'); ?></label></th>
                        <td>
                            <select name="redirect_type" id="redirect_type">
                                <option value="301">301 - Permanent</option>
                                <option value="302">302 - Temporary</option>
                                <option value="307">307 - Temporary (HTTP 1.1)</option>
                                <option value="410">410 - Gone</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Regex', 'ai-seo-client'); ?></th>
                        <td><label><input type="checkbox" name="regex" value="1"> <?php esc_html_e('Use regular expression for source URL', 'ai-seo-client'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(__('Add Redirect', 'ai-seo-client')); ?>
            </form>

            <!-- Import / Export -->
            <h2><?php esc_html_e('Import / Export', 'ai-seo-client'); ?></h2>
            <div style="display:flex;gap:20px;align-items:start;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="flex:1;">
                    <?php wp_nonce_field('sseo_ai_redirect_import'); ?>
                    <input type="hidden" name="action" value="sseo_ai_redirect_import">
                    <input type="file" name="csv_file" accept=".csv">
                    <?php submit_button(__('Import CSV', 'ai-seo-client'), 'secondary', 'submit', false); ?>
                </form>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=sseo_ai_redirect_export')); ?>" class="button button-secondary"><?php esc_html_e('Export CSV', 'ai-seo-client'); ?></a>
            </div>

            <!-- Redirects list -->
            <h2><?php esc_html_e('Existing Redirects', 'ai-seo-client'); ?></h2>
            <form method="get" style="margin-bottom:15px;">
                <input type="hidden" name="page" value="ai-seo-redirects">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search redirects...', 'ai-seo-client'); ?>">
                <button type="submit" class="button"><?php esc_html_e('Search', 'ai-seo-client'); ?></button>
            </form>

            <?php if (empty($redirects)): ?>
                <p><?php esc_html_e('No redirects found.', 'ai-seo-client'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Source URL', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Target URL', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Type', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Hits', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Last Accessed', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($redirects as $r): ?>
                            <tr>
                                <td><code><?php echo esc_html($r['source_url']); ?></code><?php if ($r['regex']) echo ' <span class="dashicons dashicons-search" title="Regex"></span>'; ?></td>
                                <td><code><?php echo esc_html($r['target_url']); ?></code></td>
                                <td><?php echo esc_html($r['redirect_type']); ?></td>
                                <td><?php echo esc_html(number_format($r['hits'])); ?></td>
                                <td><?php echo $r['last_accessed'] ? esc_html($r['last_accessed']) : '&mdash;'; ?></td>
                                <td>
                                    <?php if ($r['enabled']): ?>
                                        <span style="color:#00a32a;"><?php esc_html_e('Active', 'ai-seo-client'); ?></span>
                                    <?php else: ?>
                                        <span style="color:#999;"><?php esc_html_e('Disabled', 'ai-seo-client'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                        <?php wp_nonce_field('sseo_ai_redirect_toggle_' . $r['id']); ?>
                                        <input type="hidden" name="action" value="sseo_ai_redirect_toggle">
                                        <input type="hidden" name="id" value="<?php echo esc_attr($r['id']); ?>">
                                        <input type="hidden" name="enabled" value="<?php echo esc_attr($r['enabled']); ?>">
                                        <button type="submit" class="button button-small"><?php echo $r['enabled'] ? __('Disable', 'ai-seo-client') : __('Enable', 'ai-seo-client'); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Delete this redirect?', 'ai-seo-client'); ?>')">
                                        <?php wp_nonce_field('sseo_ai_redirect_delete_' . $r['id']); ?>
                                        <input type="hidden" name="action" value="sseo_ai_redirect_delete">
                                        <input type="hidden" name="id" value="<?php echo esc_attr($r['id']); ?>">
                                        <button type="submit" class="button button-small button-link-delete"><?php esc_html_e('Delete', 'ai-seo-client'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}

<?php

namespace AISEOClient;

class SEORevisions
{
    private string $tableName;

    public function __construct()
    {
        global $wpdb;
        $this->tableName = $wpdb->prefix . 'aiseo_revisions';
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'maybeCreateTable']);
        add_action('save_post', [$this, 'trackRevision'], 10, 2);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
    }

    public function maybeCreateTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            revision_data longtext NOT NULL,
            revision_type varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function trackRevision(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!in_array($post->post_type, ['post', 'page', 'product'])) {
            return;
        }

        // Check if SEO data changed
        $oldTitle = get_post_meta($postId, '_aiseo_title', true);
        $oldDesc = get_post_meta($postId, '_aiseo_description', true);
        $oldFocus = get_post_meta($postId, '_aiseo_focus_keyphrase', true);
        $oldScore = get_post_meta($postId, '_aiseo_score', true);

        $data = [
            'title' => $oldTitle,
            'description' => $oldDesc,
            'focus_keyphrase' => $oldFocus,
            'score' => $oldScore,
            'post_title' => $post->post_title,
            'post_content_hash' => md5($post->post_content),
        ];

        // Check if this is different from last revision
        $last = $this->getLastRevision($postId);
        if ($last && $last['revision_data'] === json_encode($data)) {
            return;
        }

        global $wpdb;
        $wpdb->insert($this->tableName, [
            'post_id' => $postId,
            'user_id' => get_current_user_id(),
            'revision_data' => wp_json_encode($data),
            'revision_type' => 'seo_update',
        ]);

        // Keep only last 50 revisions per post
        $this->cleanupOldRevisions($postId, 50);
    }

    public function getRevisions(int $postId, int $limit = 20): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableName} WHERE post_id = %d ORDER BY created_at DESC LIMIT %d",
                $postId,
                $limit
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['data'] = json_decode($row['revision_data'], true);
            $row['user'] = get_userdata($row['user_id']);
        }

        return $rows;
    }

    public function getLastRevision(int $postId): ?array
    {
        $revisions = $this->getRevisions($postId, 1);
        return $revisions[0] ?? null;
    }

    public function restoreRevision(int $revisionId): bool
    {
        global $wpdb;
        $revision = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $revisionId),
            ARRAY_A
        );

        if (!$revision) {
            return false;
        }

        $data = json_decode($revision['revision_data'], true);
        $postId = $revision['post_id'];

        if (!empty($data['title'])) {
            update_post_meta($postId, '_aiseo_title', $data['title']);
        }
        if (!empty($data['description'])) {
            update_post_meta($postId, '_aiseo_description', $data['description']);
        }
        if (!empty($data['focus_keyphrase'])) {
            update_post_meta($postId, '_aiseo_focus_keyphrase', $data['focus_keyphrase']);
        }

        return true;
    }

    private function cleanupOldRevisions(int $postId, int $keep): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->tableName} 
            WHERE post_id = %d 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM {$this->tableName} 
                    WHERE post_id = %d 
                    ORDER BY created_at DESC 
                    LIMIT %d
                ) as keep_ids
            )",
            $postId,
            $postId,
            $keep
        ));
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'aiseo_revisions',
            __('SEO Revisions', 'ai-seo-client'),
            [$this, 'renderMetaBox'],
            ['post', 'page', 'product'],
            'normal',
            'low'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $revisions = $this->getRevisions($post->ID, 10);
        ?>
        <div class="aiseo-revisions-box">
            <?php if ($revisions) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('User', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('SEO Score', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Title', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revisions as $rev) : 
                        $data = $rev['data'];
                    ?>
                    <tr>
                        <td><?php echo esc_html(human_time_diff(strtotime($rev['created_at']), current_time('timestamp'))); ?> <?php esc_html_e('ago', 'ai-seo-client'); ?></td>
                        <td><?php echo $rev['user'] ? esc_html($rev['user']->display_name) : 'Unknown'; ?></td>
                        <td><?php echo isset($data['score']) ? esc_html($data['score']) : '-'; ?></td>
                        <td><?php echo isset($data['title']) ? esc_html(wp_trim_words($data['title'], 8)) : '-'; ?></td>
                        <td>
                            <button type="button" class="button button-small aiseo-restore-rev" data-rev="<?php echo $rev['id']; ?>">
                                <?php esc_html_e('Restore', 'ai-seo-client'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p><em><?php esc_html_e('No SEO revisions yet.', 'ai-seo-client'); ?></em></p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.aiseo-restore-rev').on('click', function() {
                var revId = $(this).data('rev');
                if (!confirm('<?php esc_html_e('Restore this SEO revision? Current SEO data will be overwritten.', 'ai-seo-client'); ?>')) {
                    return;
                }
                
                wp.apiFetch({
                    path: 'aiseoclient/v1/restore-revision',
                    method: 'POST',
                    data: { revision_id: revId }
                }).then(function() {
                    alert('<?php esc_html_e('Revision restored. Reload the page to see changes.', 'ai-seo-client'); ?>');
                });
            });
        });
        </script>
        <?php
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoclient/v1', '/restore-revision', [
            'methods' => 'POST',
            'callback' => [$this, 'restRestoreRevision'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restRestoreRevision(\WP_REST_Request $request): array
    {
        $revisionId = (int)$request->get_param('revision_id');
        
        if ($this->restoreRevision($revisionId)) {
            return ['success' => true];
        }

        return new \WP_Error('restore_failed', __('Failed to restore revision', 'ai-seo-client'));
    }
}

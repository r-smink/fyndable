<?php

namespace AISEOAssistant;

class ImportExport
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'handleExport']);
        add_action('admin_init', [$this, 'handleImport']);
    }

    public function handleExport(): void
    {
        if (!isset($_GET['aiseo_export']) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'aiseo_export')) {
            return;
        }

        $type = sanitize_text_field($_GET['aiseo_export']);
        
        switch ($type) {
            case 'settings':
                $this->exportSettings();
                break;
            case 'seo_meta':
                $this->exportSEOMeta();
                break;
            case 'redirects':
                $this->exportRedirects();
                break;
            case 'full':
                $this->exportFull();
                break;
        }
    }

    public function handleImport(): void
    {
        if (!isset($_POST['aiseo_import_submit'], $_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'aiseo_import')) {
            return;
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            add_settings_error('aiseo_import', 'error', __('No file uploaded', 'ai-seo-assistant'), 'error');
            return;
        }

        $file = $_FILES['import_file']['tmp_name'];
        $type = sanitize_text_field($_POST['import_type'] ?? 'settings');

        $content = file_get_contents($file);
        if (!$content) {
            add_settings_error('aiseo_import', 'error', __('Could not read file', 'ai-seo-assistant'), 'error');
            return;
        }

        $data = json_decode($content, true);
        if (!$data) {
            add_settings_error('aiseo_import', 'error', __('Invalid JSON file', 'ai-seo-assistant'), 'error');
            return;
        }

        switch ($type) {
            case 'settings':
                $result = $this->importSettings($data);
                break;
            case 'seo_meta':
                $result = $this->importSEOMeta($data);
                break;
            case 'redirects':
                $result = $this->importRedirects($data);
                break;
            case 'full':
                $result = $this->importFull($data);
                break;
            default:
                $result = ['success' => false, 'message' => __('Unknown import type', 'ai-seo-assistant')];
        }

        if ($result['success']) {
            add_settings_error('aiseo_import', 'success', $result['message'], 'updated');
        } else {
            add_settings_error('aiseo_import', 'error', $result['message'], 'error');
        }
    }

    private function exportSettings(): void
    {
        $settings = $this->settings->all();
        
        $this->sendJson($settings, 'aiseo-settings-' . date('Y-m-d') . '.json');
    }

    private function exportSEOMeta(): void
    {
        global $wpdb;

        $posts = $wpdb->get_results(
            "SELECT p.ID, p.post_type, pm.meta_key, pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND pm.meta_key LIKE '_aiseo_%'"
        );

        $data = [];
        foreach ($posts as $row) {
            if (!isset($data[$row->ID])) {
                $data[$row->ID] = [
                    'id' => $row->ID,
                    'type' => $row->post_type,
                    'meta' => [],
                ];
            }
            $data[$row->ID]['meta'][$row->meta_key] = maybe_unserialize($row->meta_value);
        }

        $this->sendJson(array_values($data), 'aiseo-seo-meta-' . date('Y-m-d') . '.json');
    }

    private function exportRedirects(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiseo_redirects';

        $redirects = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

        $this->sendJson($redirects ?: [], 'aiseo-redirects-' . date('Y-m-d') . '.json');
    }

    private function exportFull(): void
    {
        global $wpdb;

        $export = [
            'version' => '1.0',
            'date' => current_time('mysql'),
            'settings' => $this->settings->all(),
            'seo_meta' => [],
            'redirects' => [],
            '404s' => [],
        ];

        // SEO Meta
        $posts = $wpdb->get_results(
            "SELECT p.ID, p.post_type, pm.meta_key, pm.meta_value 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND pm.meta_key LIKE '_aiseo_%'"
        );

        foreach ($posts as $row) {
            if (!isset($export['seo_meta'][$row->ID])) {
                $export['seo_meta'][$row->ID] = [
                    'id' => $row->ID,
                    'type' => $row->post_type,
                    'meta' => [],
                ];
            }
            $export['seo_meta'][$row->ID]['meta'][$row->meta_key] = maybe_unserialize($row->meta_value);
        }

        // Redirects
        $redirectTable = $wpdb->prefix . 'aiseo_redirects';
        $export['redirects'] = $wpdb->get_results("SELECT * FROM {$redirectTable}", ARRAY_A) ?: [];

        // 404s
        $notFoundTable = $wpdb->prefix . 'aiseo_404s';
        $export['404s'] = $wpdb->get_results("SELECT * FROM {$notFoundTable} WHERE resolved = 0", ARRAY_A) ?: [];

        $this->sendJson($export, 'aiseo-full-backup-' . date('Y-m-d') . '.json');
    }

    private function importSettings(array $data): array
    {
        if (!is_array($data)) {
            return ['success' => false, 'message' => __('Invalid settings data', 'ai-seo-assistant')];
        }

        update_option(Settings::OPTION_KEY, $data);

        return ['success' => true, 'message' => __('Settings imported successfully', 'ai-seo-assistant')];
    }

    private function importSEOMeta(array $data): array
    {
        if (!is_array($data)) {
            return ['success' => false, 'message' => __('Invalid SEO meta data', 'ai-seo-assistant')];
        }

        $imported = 0;
        $errors = 0;

        foreach ($data as $item) {
            if (empty($item['id']) || empty($item['meta'])) {
                continue;
            }

            $postId = (int)$item['id'];
            
            // Verify post exists
            if (!get_post($postId)) {
                $errors++;
                continue;
            }

            foreach ($item['meta'] as $key => $value) {
                update_post_meta($postId, $key, $value);
            }
            $imported++;
        }

        return [
            'success' => true,
            'message' => sprintf(__('Imported SEO meta for %d posts (%d errors)', 'ai-seo-assistant'), $imported, $errors),
        ];
    }

    private function importRedirects(array $data): array
    {
        if (!is_array($data)) {
            return ['success' => false, 'message' => __('Invalid redirects data', 'ai-seo-assistant')];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aiseo_redirects';

        $imported = 0;

        foreach ($data as $redirect) {
            if (empty($redirect['source_url']) || empty($redirect['target_url'])) {
                continue;
            }

            $wpdb->replace($table, [
                'source_url' => $redirect['source_url'],
                'target_url' => $redirect['target_url'],
                'redirect_type' => $redirect['redirect_type'] ?? 301,
                'hits' => $redirect['hits'] ?? 0,
                'created_at' => $redirect['created_at'] ?? current_time('mysql'),
                'enabled' => $redirect['enabled'] ?? 1,
                'regex' => $redirect['regex'] ?? 0,
            ]);

            $imported++;
        }

        return [
            'success' => true,
            'message' => sprintf(__('Imported %d redirects', 'ai-seo-assistant'), $imported),
        ];
    }

    private function importFull(array $data): array
    {
        if (!isset($data['version'])) {
            return ['success' => false, 'message' => __('Invalid full backup file', 'ai-seo-assistant')];
        }

        $results = [];

        if (!empty($data['settings'])) {
            $results[] = $this->importSettings($data['settings']);
        }

        if (!empty($data['seo_meta'])) {
            $results[] = $this->importSEOMeta($data['seo_meta']);
        }

        if (!empty($data['redirects'])) {
            $results[] = $this->importRedirects($data['redirects']);
        }

        $messages = array_filter(array_column($results, 'message'));

        return [
            'success' => true,
            'message' => implode('; ', $messages) ?: __('Full import completed', 'ai-seo-assistant'),
        ];
    }

    private function sendJson(array $data, string $filename): void
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        
        echo wp_json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    public function renderImportExport(): void
    {
        ?>
        <h3><?php esc_html_e('Import / Export', 'ai-seo-assistant'); ?></h3>

        <h4><?php esc_html_e('Export', 'ai-seo-assistant'); ?></h4>
        <p><?php esc_html_e('Download your AI SEO Assistant data:', 'ai-seo-assistant'); ?></p>
        <p>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('aiseo_export', 'settings'), 'aiseo_export')); ?>" class="button">
                <?php esc_html_e('Export Settings', 'ai-seo-assistant'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('aiseo_export', 'seo_meta'), 'aiseo_export')); ?>" class="button">
                <?php esc_html_e('Export SEO Meta', 'ai-seo-assistant'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('aiseo_export', 'redirects'), 'aiseo_export')); ?>" class="button">
                <?php esc_html_e('Export Redirects', 'ai-seo-assistant'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('aiseo_export', 'full'), 'aiseo_export')); ?>" class="button button-primary">
                <?php esc_html_e('Full Backup', 'ai-seo-assistant'); ?>
            </a>
        </p>

        <h4><?php esc_html_e('Import', 'ai-seo-assistant'); ?></h4>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aiseo_import'); ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Import File', 'ai-seo-assistant'); ?></th>
                    <td>
                        <input type="file" name="import_file" accept=".json">
                        <p class="description"><?php esc_html_e('Upload a JSON file exported from AI SEO Assistant', 'ai-seo-assistant'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Import Type', 'ai-seo-assistant'); ?></th>
                    <td>
                        <select name="import_type">
                            <option value="settings"><?php esc_html_e('Settings Only', 'ai-seo-assistant'); ?></option>
                            <option value="seo_meta"><?php esc_html_e('SEO Meta Only', 'ai-seo-assistant'); ?></option>
                            <option value="redirects"><?php esc_html_e('Redirects Only', 'ai-seo-assistant'); ?></option>
                            <option value="full"><?php esc_html_e('Full Backup', 'ai-seo-assistant'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="aiseo_import_submit" class="button button-primary">
                    <?php esc_html_e('Import', 'ai-seo-assistant'); ?>
                </button>
            </p>
        </form>

        <h4><?php esc_html_e('Import from Other SEO Plugins', 'ai-seo-assistant'); ?></h4>
        <p><?php esc_html_e('Import data from popular SEO plugins:', 'ai-seo-assistant'); ?></p>
        <p>
            <button type="button" class="button" id="import-yoast">
                <?php esc_html_e('Import from Yoast SEO', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button" id="import-rankmath">
                <?php esc_html_e('Import from Rank Math', 'ai-seo-assistant'); ?>
            </button>
            <button type="button" class="button" id="import-aio">
                <?php esc_html_e('Import from All in One SEO', 'ai-seo-assistant'); ?>
            </button>
        </p>

        <script>
        jQuery(document).ready(function($) {
            $('#import-yoast, #import-rankmath, #import-aio').on('click', function() {
                var plugin = $(this).attr('id').replace('import-', '');
                if (!confirm('<?php esc_html_e('This will import SEO data from '); ?>' + plugin + '. <?php esc_html_e('Continue?'); ?>')) {
                    return;
                }
                
                wp.apiFetch({
                    path: 'aiseoassistant/v1/import-from-plugin',
                    method: 'POST',
                    data: { plugin: plugin }
                }).then(function(response) {
                    alert(response.message);
                }).catch(function(error) {
                    alert('Error: ' + error.message);
                });
            });
        });
        </script>
        <?php
    }

    public function importFromYoast(): array
    {
        global $wpdb;

        $imported = 0;
        $skipped = 0;

        // Check if Yoast data exists
        $yoastPosts = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = '_yoast_wpseo_title' OR meta_key = '_yoast_wpseo_metadesc'"
        );

        if (!$yoastPosts) {
            return ['success' => false, 'message' => __('No Yoast SEO data found', 'ai-seo-assistant')];
        }

        // Group by post
        $byPost = [];
        foreach ($yoastPosts as $row) {
            if (!isset($byPost[$row->post_id])) {
                $byPost[$row->post_id] = [];
            }
            $byPost[$row->post_id][$row->meta_key] = $row->meta_value;
        }

        foreach ($byPost as $postId => $meta) {
            if (!empty($meta['_yoast_wpseo_title'])) {
                update_post_meta($postId, '_aiseo_title', $meta['_yoast_wpseo_title']);
            }
            if (!empty($meta['_yoast_wpseo_metadesc'])) {
                update_post_meta($postId, '_aiseo_description', $meta['_yoast_wpseo_metadesc']);
            }
            if (!empty($meta['_yoast_wpseo_focuskw'])) {
                update_post_meta($postId, '_aiseo_focus_keyphrase', $meta['_yoast_wpseo_focuskw']);
            }
            $imported++;
        }

        return [
            'success' => true,
            'message' => sprintf(__('Imported Yoast SEO data for %d posts', 'ai-seo-assistant'), $imported),
        ];
    }

    public function importFromRankMath(): array
    {
        global $wpdb;

        $rankmathPosts = $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE 'rank_math%'"
        );

        if (!$rankmathPosts) {
            return ['success' => false, 'message' => __('No Rank Math data found', 'ai-seo-assistant')];
        }

        $imported = 0;

        foreach ($rankmathPosts as $row) {
            switch ($row->meta_key) {
                case 'rank_math_title':
                    update_post_meta($row->post_id, '_aiseo_title', $row->meta_value);
                    break;
                case 'rank_math_description':
                    update_post_meta($row->post_id, '_aiseo_description', $row->meta_value);
                    break;
                case 'rank_math_focus_keyword':
                    update_post_meta($row->post_id, '_aiseo_focus_keyphrase', $row->meta_value);
                    break;
            }
            $imported++;
        }

        return [
            'success' => true,
            'message' => sprintf(__('Imported Rank Math data for %d posts', 'ai-seo-assistant'), count(array_unique(wp_list_pluck($rankmathPosts, 'post_id')))),
        ];
    }

    public function importFromAIOSEO(): array
    {
        global $wpdb;

        $aioseoPosts = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
            WHERE meta_key = '_aioseo_title' OR meta_key = '_aioseo_description'"
        );

        if (!$aioseoPosts) {
            return ['success' => false, 'message' => __('No All in One SEO data found', 'ai-seo-assistant')];
        }

        $imported = 0;

        foreach ($aioseoPosts as $row) {
            $meta = maybe_unserialize($row->meta_value);
            if (is_array($meta)) {
                if (!empty($meta['title'])) {
                    update_post_meta($row->post_id, '_aiseo_title', $meta['title']);
                }
                if (!empty($meta['description'])) {
                    update_post_meta($row->post_id, '_aiseo_description', $meta['description']);
                }
                $imported++;
            }
        }

        return [
            'success' => true,
            'message' => sprintf(__('Imported All in One SEO data for %d posts', 'ai-seo-assistant'), $imported),
        ];
    }
}

<?php

namespace SSEOAIClient;

/**
 * SEO Importer
 *
 * Imports SEO metadata from Yoast SEO, RankMath, and AIOSEO into
 * Fyndable's post meta format. Provides a REST endpoint for
 * batch processing and an admin page under Settings.
 */
class SeoImporter
{
    private string $pageSlug = 'ai-seo-import';

    /**
     * Meta key mappings from competitor plugins to Fyndable.
     */
    private array $metaMap = [
        'yoast' => [
            '_yoast_wpseo_focuskw'         => '_sseo_ai_focus_keyphrase',
            '_yoast_wpseo_title'           => '_sseo_ai_title',
            '_yoast_wpseo_metadesc'        => '_sseo_ai_description',
            '_yoast_wpseo_canonical'       => '_sseo_ai_canonical_url',
            '_yoast_wpseo_opengraph-title' => '_sseo_ai_og_title',
            '_yoast_wpseo_opengraph-description' => '_sseo_ai_og_description',
            '_yoast_wpseo_opengraph-image' => '_sseo_ai_og_image',
            '_yoast_wpseo_twitter-title'   => '_sseo_ai_twitter_title',
            '_yoast_wpseo_twitter-description' => '_sseo_ai_twitter_description',
            '_yoast_wpseo_twitter-image'   => '_sseo_ai_twitter_image',
            '_yoast_wpseo_schema_page_type' => '_sseo_ai_schema_type',
        ],
        'rankmath' => [
            'rank_math_focus_keyword'      => '_sseo_ai_focus_keyphrase',
            'rank_math_title'              => '_sseo_ai_title',
            'rank_math_description'        => '_sseo_ai_description',
            'rank_math_canonical_url'      => '_sseo_ai_canonical_url',
            'rank_math_facebook_title'     => '_sseo_ai_og_title',
            'rank_math_facebook_description' => '_sseo_ai_og_description',
            'rank_math_facebook_image'     => '_sseo_ai_og_image',
            'rank_math_twitter_title'      => '_sseo_ai_twitter_title',
            'rank_math_twitter_description' => '_sseo_ai_twitter_description',
            'rank_math_twitter_image'      => '_sseo_ai_twitter_image',
        ],
        'aioseo' => [
            '_aioseo_title'                => '_sseo_ai_title',
            '_aioseo_description'          => '_sseo_ai_description',
            '_aioseo_focus_keyword'        => '_sseo_ai_focus_keyphrase',
            '_aioseo_canonical_url'        => '_sseo_ai_canonical_url',
            '_aioseo_og_title'             => '_sseo_ai_og_title',
            '_aioseo_og_description'       => '_sseo_ai_og_description',
            '_aioseo_og_image'             => '_sseo_ai_og_image',
            '_aioseo_twitter_title'        => '_sseo_ai_twitter_title',
            '_aioseo_twitter_description'  => '_sseo_ai_twitter_description',
            '_aioseo_twitter_image'        => '_sseo_ai_twitter_image',
        ],
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register REST routes for import operations.
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/import/detect', [
            'methods'  => 'GET',
            'callback' => [$this, 'restDetect'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/import/start', [
            'methods'  => 'POST',
            'callback' => [$this, 'restStartImport'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('sseo-ai/v1', '/import/status', [
            'methods'  => 'GET',
            'callback' => [$this, 'restImportStatus'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Detect which SEO plugins are installed and have data.
     */
    public function restDetect(): \WP_REST_Response
    {
        $detected = $this->detectPlugins();

        return new \WP_REST_Response([
            'success' => true,
            'plugins' => $detected,
        ], 200);
    }

    /**
     * Detect installed SEO plugins and their post counts.
     */
    public function detectPlugins(): array
    {
        global $wpdb;

        $plugins = [];
        $postTypes = get_post_types(['public' => true], 'names');
        $postTypeList = "'" . implode("','", array_map('esc_sql', $postTypes)) . "'";

        foreach ($this->metaMap as $plugin => $mapping) {
            $sourceKeys = array_keys($mapping);
            $keyList = "'" . implode("','", array_map('esc_sql', $sourceKeys)) . "'";

            $count = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ({$keyList})"
            );

            $isInstalled = false;
            $pluginName = '';

            if ($plugin === 'yoast') {
                $isInstalled = is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');
                $pluginName = 'Yoast SEO';
            } elseif ($plugin === 'rankmath') {
                $isInstalled = is_plugin_active('seo-by-rank-math/rank-math.php');
                $pluginName = 'Rank Math SEO';
            } elseif ($plugin === 'aioseo') {
                $isInstalled = is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') || is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack_pro.php');
                $pluginName = 'AIOSEO';
            }

            $plugins[$plugin] = [
                'name'        => $pluginName,
                'installed'   => $isInstalled,
                'post_count'  => $count,
                'has_data'    => $count > 0,
            ];
        }

        return $plugins;
    }

    /**
     * Start import process (batch).
     */
    public function restStartImport(\WP_REST_Request $request): \WP_REST_Response
    {
        $plugin = sanitize_text_field($request->get_param('plugin'));
        $overwrite = (bool) $request->get_param('overwrite');
        $batchSize = 50;
        $offset = (int) $request->get_param('offset');

        if (!isset($this->metaMap[$plugin])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Unknown plugin source.', 'ai-seo-client'),
            ], 400);
        }

        $result = $this->importBatch($plugin, $overwrite, $batchSize, $offset);

        return new \WP_REST_Response([
            'success'  => true,
            'imported' => $result['imported'],
            'skipped'  => $result['skipped'],
            'total'    => $result['total'],
            'offset'   => $result['next_offset'],
            'done'     => $result['done'],
        ], 200);
    }

    /**
     * Import a batch of posts.
     */
    public function importBatch(string $plugin, bool $overwrite, int $limit, int $offset): array
    {
        global $wpdb;

        $mapping = $this->metaMap[$plugin];
        $sourceKeys = array_keys($mapping);
        $keyList = "'" . implode("','", array_map('esc_sql', $sourceKeys)) . "'";

        $postIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$keyList}) ORDER BY post_id ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));

        $totalCount = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ({$keyList})"
        );

        $imported = 0;
        $skipped = 0;

        foreach ($postIds as $postId) {
            $hasExisting = false;
            if (!$overwrite) {
                $existingTitle = get_post_meta($postId, '_sseo_ai_title', true);
                if (!empty($existingTitle)) {
                    $hasExisting = true;
                }
            }

            if ($hasExisting) {
                $skipped++;
                continue;
            }

            foreach ($mapping as $sourceKey => $destKey) {
                $value = get_post_meta($postId, $sourceKey, true);
                if (!empty($value)) {
                    update_post_meta($postId, $destKey, $value);
                }
            }

            $imported++;
        }

        $nextOffset = $offset + $limit;
        $done = $nextOffset >= $totalCount;

        return [
            'imported'    => $imported,
            'skipped'     => $skipped,
            'total'       => $totalCount,
            'next_offset' => $done ? 0 : $nextOffset,
            'done'        => $done,
        ];
    }

    /**
     * Get import status (for UI progress display).
     */
    public function restImportStatus(): \WP_REST_Response
    {
        $detected = $this->detectPlugins();

        return new \WP_REST_Response([
            'success' => true,
            'plugins' => $detected,
        ], 200);
    }

    /**
     * Render the import admin page.
     */
    public function renderPage(): void
    {
        $detected = $this->detectPlugins();
        ?>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Import SEO Data', 'ai-seo-client'); ?></h1>
            </div>
            <div class="sseo-ai-content">
                <div class="sseo-ai-dashboard-card" style="max-width: 800px; margin: 0 auto;">
                    <h2><?php esc_html_e('Migrate from another SEO plugin', 'ai-seo-client'); ?></h2>
                    <p style="color: #6b7280; margin-bottom: 30px;">
                        <?php esc_html_e('Import SEO titles, descriptions, focus keywords, canonical URLs, and social meta from Yoast, Rank Math, or AIOSEO into Fyndable.', 'ai-seo-client'); ?>
                    </p>

                    <?php foreach ($detected as $pluginKey => $info): ?>
                        <div class="import-plugin-card" data-plugin="<?php echo esc_attr($pluginKey); ?>" style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div>
                                    <strong style="font-size: 16px;"><?php echo esc_html($info['name']); ?></strong>
                                    <?php if ($info['installed']): ?>
                                        <span style="color: #10b981; margin-left: 8px;">● Installed</span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af; margin-left: 8px;">○ Not active</span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #6b7280; font-size: 14px;">
                                    <?php echo esc_html(sprintf(_n('%s post with data', '%s posts with data', $info['post_count'], 'ai-seo-client'), number_format_i18n($info['post_count']))); ?>
                                </div>
                            </div>

                            <?php if ($info['has_data']): ?>
                                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                                    <input type="checkbox" class="import-overwrite" data-plugin="<?php echo esc_attr($pluginKey); ?>">
                                    <span><?php esc_html_e('Overwrite existing Fyndable SEO data', 'ai-seo-client'); ?></span>
                                </label>
                                <button class="button button-primary sseo-import-btn" data-plugin="<?php echo esc_attr($pluginKey); ?>">
                                    <?php esc_html_e('Import', 'ai-seo-client'); ?>
                                </button>
                                <div class="import-progress" data-plugin="<?php echo esc_attr($pluginKey); ?>" style="display: none; margin-top: 16px;">
                                    <div style="background: #f3f4f6; border-radius: 4px; height: 8px; overflow: hidden;">
                                        <div class="import-progress-bar" style="background: #3b82f6; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                    </div>
                                    <p class="import-progress-text" style="font-size: 13px; color: #6b7280; margin-top: 8px;"></p>
                                </div>
                            <?php else: ?>
                                <p style="color: #9ca3af; margin: 0;"><?php esc_html_e('No SEO data found from this plugin.', 'ai-seo-client'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty(array_filter($detected, fn($p) => $p['has_data']))): ?>
                        <div style="text-align: center; padding: 40px; color: #9ca3af;">
                            <p><?php esc_html_e('No compatible SEO plugins detected. Install Yoast, Rank Math, or AIOSEO first, then return here to import.', 'ai-seo-client'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (function() {
            document.querySelectorAll('.sseo-import-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var pluginKey = btn.dataset.plugin;
                    var overwrite = document.querySelector('.import-overwrite[data-plugin="' + pluginKey + '"]').checked;
                    var progressDiv = document.querySelector('.import-progress[data-plugin="' + pluginKey + '"]');
                    var progressBar = progressDiv.querySelector('.import-progress-bar');
                    var progressText = progressDiv.querySelector('.import-progress-text');

                    btn.disabled = true;
                    progressDiv.style.display = 'block';

                    function runBatch(offset) {
                        fetch('/wp-json/sseo-ai/v1/import/start', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': window.wpApiSettings?.nonce || ''
                            },
                            body: JSON.stringify({
                                plugin: pluginKey,
                                overwrite: overwrite,
                                offset: offset
                            })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data.success) {
                                progressText.textContent = 'Error: ' + (data.message || 'Import failed');
                                btn.disabled = false;
                                return;
                            }

                            var pct = data.total > 0 ? Math.round(((data.offset || data.total) / data.total) * 100) : 100;
                            progressBar.style.width = pct + '%';
                            progressText.textContent = 'Imported ' + (data.imported || 0) + ' posts, skipped ' + (data.skipped || 0) + ' — ' + pct + '%';

                            if (!data.done) {
                                runBatch(data.offset);
                            } else {
                                progressText.textContent += ' — Done!';
                                btn.textContent = 'Import Complete';
                                btn.disabled = false;
                            }
                        })
                        .catch(function(err) {
                            progressText.textContent = 'Error: ' + err.message;
                            btn.disabled = false;
                        });
                    }

                    runBatch(0);
                });
            });
        })();
        </script>
        <?php
    }
}

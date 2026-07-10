<?php

namespace SSEOAIClient;

class SitemapGenerator
{
    private string $pluginDir;
    private Settings $settings;

    public function __construct(string $pluginFile, Settings $settings)
    {
        $this->pluginDir = plugin_dir_path($pluginFile);
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules'], 10, 0);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'maybeServeSitemap']);
        add_action('save_post', [$this, 'onPostSave'], 10, 3);
        add_action('delete_post', [$this, 'onPostDelete']);
        add_action('aiseoclient_generate_sitemap', [$this, 'generateAll']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        $this->disableWordPressSitemap();
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/sitemap/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
    }

    public function restGetStatus(): array
    {
        $sitemapUrl = home_url('/sitemap.xml');
        $sitemapIndexUrl = home_url('/sitemap_index.xml');

        $response = wp_remote_get($sitemapUrl, ['timeout' => 5]);
        $sitemapExists = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        $sitemapSize = !is_wp_error($response) ? strlen(wp_remote_retrieve_body($response) ?: '') : 0;

        $indexResponse = wp_remote_get($sitemapIndexUrl, ['timeout' => 5]);
        $indexExists = !is_wp_error($indexResponse) && wp_remote_retrieve_response_code($indexResponse) === 200;

        return [
            'sitemap_url' => $sitemapUrl,
            'sitemap_index_url' => $sitemapIndexUrl,
            'sitemap_exists' => $sitemapExists,
            'index_exists' => $indexExists,
            'sitemap_size' => $sitemapSize,
            'rewrite_rules_flushed' => (bool) get_option('sseo_rewrite_rules_flushed'),
        ];
    }

    public function registerSettings(): void
    {
        register_setting('sseo_sitemap_settings', 'sseo_sitemap_post_types', [
            'type' => 'array',
            'default' => null,
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_values(array_filter(array_map('sanitize_text_field', $value)));
            },
        ]);
        register_setting('sseo_sitemap_settings', 'sseo_sitemap_taxonomies', [
            'type' => 'array',
            'default' => null,
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_values(array_filter(array_map('sanitize_text_field', $value)));
            },
        ]);
        register_setting('sseo_sitemap_settings', 'sseo_sitemap_exclude_ids', ['type' => 'string', 'default' => '']);
        register_setting('sseo_sitemap_settings', 'sseo_sitemap_ping_engines', ['type' => 'boolean', 'default' => true]);
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?aiseo_sitemap=main', 'top');
        add_rewrite_rule('^sitemap_index\.xml$', 'index.php?aiseo_sitemap=main', 'top');
        add_rewrite_rule('^wp-sitemap\.xml$', 'index.php?aiseo_sitemap=main', 'top');
        add_rewrite_rule('^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?aiseo_sitemap=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-tax-([a-z0-9_-]+)\.xml$', 'index.php?aiseo_sitemap=tax-$matches[1]', 'top');
    }

    public function disableWordPressSitemap(): void
    {
        add_filter('wp_sitemaps_enabled', '__return_false');
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = 'aiseo_sitemap';
        return $vars;
    }

    public function maybeServeSitemap(): void
    {
        $type = get_query_var('aiseo_sitemap');
        if (!$type) {
            $type = $this->detectSitemapRequest();
            if (!$type) {
                return;
            }
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        if ($type === 'main') {
            echo $this->generateIndex();
        } elseif (str_starts_with($type, 'tax-')) {
            $taxonomy = substr($type, 4);
            echo $this->generateTaxonomySitemap($taxonomy);
        } else {
            echo $this->generateType($type);
        }
        exit;
    }

    /**
     * Fallback detection for sitemap URLs when rewrite rules are not flushed.
     */
    private function detectSitemapRequest(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '';
        $path = trim($path, '/');

        if ($path === 'sitemap.xml' || $path === 'sitemap_index.xml' || $path === 'wp-sitemap.xml') {
            return 'main';
        }
        if (preg_match('/^sitemap-tax-([a-z0-9_-]+)\.xml$/', $path, $matches)) {
            return 'tax-' . $matches[1];
        }
        if (preg_match('/^sitemap-([a-z0-9_-]+)\.xml$/', $path, $matches)) {
            return $matches[1];
        }
        return '';
    }

    public function generateIndex(): string
    {
        $sitemaps = $this->getSitemapList();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xslUrl = SSEO_AI_CLIENT_PLUGIN_URL . 'assets/sitemap.xsl';
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($xslUrl) . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($sitemaps as $map) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . esc_url($map['url']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . esc_html($map['lastmod']) . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';
        return $xml;
    }

    public function generateType(string $type): string
    {
        $parts = explode('-', $type);
        $postType = $parts[0] ?? 'post';
        $page = isset($parts[1]) ? (int)$parts[1] : 1;

        return $this->generatePostTypeSitemap($postType, $page);
    }

    public function generatePostTypeSitemap(string $postType, int $page = 1): string
    {
        $perPage = 50000;
        $offset = ($page - 1) * $perPage;

        $excluded = $this->getExcludedIds();
        $args = [
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $perPage,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ];
        if (!empty($excluded)) {
            $args['post__not_in'] = $excluded;
        }
        $posts = get_posts($args);

        $xslUrl = SSEO_AI_CLIENT_PLUGIN_URL . 'assets/sitemap.xsl';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($xslUrl) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($posts as $post) {
            $xml .= $this->getUrlElement($post);
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function getUrlElement(\WP_Post $post): string
    {
        $url = get_permalink($post);
        $modified = get_the_modified_date('c', $post);
        $priority = $this->calculatePriority($post);
        $changefreq = $this->calculateChangefreq($post);

        $xml = '  <url>' . "\n";
        $xml .= '    <loc>' . esc_url($url) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . esc_html($modified) . '</lastmod>' . "\n";
        $xml .= '    <changefreq>' . esc_html($changefreq) . '</changefreq>' . "\n";
        $xml .= '    <priority>' . esc_html($priority) . '</priority>' . "\n";

        // Featured image
        if (has_post_thumbnail($post->ID)) {
            $thumbId = get_post_thumbnail_id($post->ID);
            $imageUrl = wp_get_attachment_image_url($thumbId, 'full');
            if ($imageUrl) {
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>' . esc_url($imageUrl) . '</image:loc>' . "\n";
                $xml .= '      <image:title>' . esc_html(get_the_title($thumbId)) . '</image:title>' . "\n";
                $xml .= '    </image:image>' . "\n";
            }
        }

        // Hreflang (if WPML/Polylang present)
        $translations = $this->getTranslations($post->ID);
        foreach ($translations as $lang => $href) {
            $xml .= '    <xhtml:link rel="alternate" hreflang="' . esc_attr($lang) . '" href="' . esc_url($href) . '" />' . "\n";
        }

        $xml .= '  </url>' . "\n";
        return $xml;
    }

    private function calculatePriority(\WP_Post $post): string
    {
        if ($post->post_type === 'page' && $post->ID == get_option('page_on_front')) {
            return '1.0';
        }
        if ($post->post_type === 'page') {
            return '0.8';
        }

        $daysSinceModified = (time() - strtotime($post->post_modified)) / 86400;
        if ($daysSinceModified < 7) {
            return '0.8';
        }
        if ($daysSinceModified < 30) {
            return '0.6';
        }
        return '0.4';
    }

    private function calculateChangefreq(\WP_Post $post): string
    {
        $daysSinceModified = (time() - strtotime($post->post_modified)) / 86400;
        if ($daysSinceModified < 1) {
            return 'hourly';
        }
        if ($daysSinceModified < 7) {
            return 'daily';
        }
        if ($daysSinceModified < 30) {
            return 'weekly';
        }
        if ($daysSinceModified < 365) {
            return 'monthly';
        }
        return 'yearly';
    }

    private function getSitemapList(): array
    {
        $sitemaps = [];
        $postTypes = $this->getEnabledPostTypes();

        foreach ($postTypes as $postType) {
            $counts = wp_count_posts($postType);
            $published = (int)($counts->publish ?? 0);
            if ($published === 0) {
                continue;
            }
            $sitemaps[] = [
                'url' => home_url("/sitemap-{$postType}.xml"),
                'lastmod' => current_time('c'),
            ];
        }

        // Taxonomy sitemaps
        $taxonomies = $this->getEnabledTaxonomies();
        foreach ($taxonomies as $taxonomy) {
            $count = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
            if ($count && !is_wp_error($count) && (int)$count > 0) {
                $sitemaps[] = [
                    'url' => home_url("/sitemap-tax-{$taxonomy}.xml"),
                    'lastmod' => current_time('c'),
                ];
            }
        }

        return $sitemaps;
    }

    private function getEnabledPostTypes(): array
    {
        $saved = get_option('sseo_sitemap_post_types', null);
        if (is_array($saved)) {
            return $saved;
        }
        // Default: all public post types except internal ones
        $postTypes = get_post_types(['public' => true], 'names');
        return array_values(array_diff($postTypes, ['attachment', 'aiseo_note', 'aiseo_prompt', 'aiseo_calendar']));
    }

    private function getEnabledTaxonomies(): array
    {
        $saved = get_option('sseo_sitemap_taxonomies', null);
        if (is_array($saved)) {
            return $saved;
        }
        // Default: category and post_tag
        return ['category', 'post_tag'];
    }

    private function getExcludedIds(): array
    {
        $raw = get_option('sseo_sitemap_exclude_ids', '');
        if (!$raw) {
            return [];
        }
        return array_filter(array_map('intval', explode(',', $raw)));
    }

    public function generateTaxonomySitemap(string $taxonomy): string
    {
        if (!in_array($taxonomy, $this->getEnabledTaxonomies())) {
            return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        }

        $xslUrl = SSEO_AI_CLIENT_PLUGIN_URL . 'assets/sitemap.xsl';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($xslUrl) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => 1000,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            $xml .= '</urlset>';
            return $xml;
        }

        foreach ($terms as $term) {
            $url = get_term_link($term);
            if (is_wp_error($url)) {
                continue;
            }
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url($url) . '</loc>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.4</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function getTranslations(int $postId): array
    {
        $translations = [];

        // WPML
        if (function_exists('icl_get_languages')) {
            $langs = icl_get_languages('skip_missing=0');
            foreach ($langs as $lang) {
                $transId = icl_object_id($postId, get_post_type($postId), false, $lang['language_code']);
                if ($transId) {
                    $translations[$lang['language_code']] = get_permalink($transId);
                }
            }
        }

        // Polylang
        if (function_exists('pll_get_post_translations')) {
            $trans = pll_get_post_translations($postId);
            foreach ($trans as $lang => $transId) {
                if ($transId !== $postId) {
                    $translations[$lang] = get_permalink($transId);
                }
            }
        }

        return $translations;
    }

    public function onPostSave(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        wp_schedule_single_event(time(), 'aiseoclient_generate_sitemap');
    }

    public function onPostDelete(int $postId): void
    {
        wp_schedule_single_event(time(), 'aiseoclient_generate_sitemap');
    }

    public function generateAll(): void
    {
        // Sitemaps are generated dynamically, but we can ping search engines here
        $this->pingSearchEngines();
    }

    private function pingSearchEngines(): void
    {
        if (!get_option('sseo_sitemap_ping_engines', true)) {
            return;
        }
        $sitemapUrl = urlencode(home_url('/sitemap.xml'));
        wp_remote_get("https://www.google.com/ping?sitemap={$sitemapUrl}", ['timeout' => 5]);
        wp_remote_get("https://www.bing.com/ping?sitemap={$sitemapUrl}", ['timeout' => 5]);
    }

    public function renderSettings(): void
    {
        $rawPostTypes = get_option('sseo_sitemap_post_types', null);
        $enabledPostTypes = is_array($rawPostTypes) ? $rawPostTypes : [];
        $postTypeDefaultsActive = ($rawPostTypes === null);

        $rawTaxonomies = get_option('sseo_sitemap_taxonomies', null);
        $enabledTaxonomies = is_array($rawTaxonomies) ? $rawTaxonomies : [];
        $taxonomyDefaultsActive = ($rawTaxonomies === null);

        $excludeIds = get_option('sseo_sitemap_exclude_ids', '');
        $pingEngines = get_option('sseo_sitemap_ping_engines', true);

        $allPostTypes = get_post_types(['public' => true], 'objects');
        $internalTypes = ['attachment', 'aiseo_note', 'aiseo_prompt', 'aiseo_calendar'];
        $allTaxonomies = get_taxonomies(['public' => true], 'objects');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Sitemap Settings', 'ai-seo-client'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('sseo_sitemap_settings'); ?>

                <h2><?php esc_html_e('Post Types in Sitemap', 'ai-seo-client'); ?></h2>
                <p><?php esc_html_e('Select which post types to include in the sitemap.', 'ai-seo-client'); ?></p>
                <input type="hidden" name="sseo_sitemap_post_types[]" value="">
                <table class="form-table">
                    <?php foreach ($allPostTypes as $pt): ?>
                        <?php if (in_array($pt->name, $internalTypes)) continue; ?>
                        <tr>
                            <th scope="row"><label for="pt-<?php echo esc_attr($pt->name); ?>"><?php echo esc_html($pt->label); ?></label></th>
                            <td>
                                <input type="checkbox" name="sseo_sitemap_post_types[]" id="pt-<?php echo esc_attr($pt->name); ?>" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $enabledPostTypes) || ($postTypeDefaultsActive && !in_array($pt->name, ['attachment', 'aiseo_note', 'aiseo_prompt', 'aiseo_calendar']))); ?>>
                                <code><?php echo esc_html($pt->name); ?></code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e('Taxonomies in Sitemap', 'ai-seo-client'); ?></h2>
                <p><?php esc_html_e('Select which taxonomies to include as separate sitemaps.', 'ai-seo-client'); ?></p>
                <input type="hidden" name="sseo_sitemap_taxonomies[]" value="">
                <table class="form-table">
                    <?php foreach ($allTaxonomies as $tax): ?>
                        <tr>
                            <th scope="row"><label for="tax-<?php echo esc_attr($tax->name); ?>"><?php echo esc_html($tax->label); ?></label></th>
                            <td>
                                <input type="checkbox" name="sseo_sitemap_taxonomies[]" id="tax-<?php echo esc_attr($tax->name); ?>" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, $enabledTaxonomies) || ($taxonomyDefaultsActive && in_array($tax->name, ['category', 'post_tag']))); ?>>
                                <code><?php echo esc_html($tax->name); ?></code>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <h2><?php esc_html_e('Exclude Posts/Pages', 'ai-seo-client'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sseo_sitemap_exclude_ids"><?php esc_html_e('Excluded IDs', 'ai-seo-client'); ?></label></th>
                        <td>
                            <input type="text" name="sseo_sitemap_exclude_ids" id="sseo_sitemap_exclude_ids" value="<?php echo esc_attr($excludeIds); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Comma-separated list of post/page IDs to exclude from the sitemap.', 'ai-seo-client'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sseo_sitemap_ping_engines"><?php esc_html_e('Ping Search Engines', 'ai-seo-client'); ?></label></th>
                        <td>
                            <input type="checkbox" name="sseo_sitemap_ping_engines" id="sseo_sitemap_ping_engines" value="1" <?php checked($pingEngines, true); ?>>
                            <p class="description"><?php esc_html_e('Automatically ping Google and Bing when sitemap is updated.', 'ai-seo-client'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Sitemap URLs', 'ai-seo-client'); ?></h2>
            <p><strong><?php esc_html_e('Main sitemap index:', 'ai-seo-client'); ?></strong> <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank"><?php echo esc_url(home_url('/sitemap.xml')); ?></a></p>
        </div>
        <?php
    }
}

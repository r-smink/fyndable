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
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?aiseo_sitemap=main', 'top');
        add_rewrite_rule('^sitemap-([a-z0-9-]+)\.xml$', 'index.php?aiseo_sitemap=$matches[1]', 'top');
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
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        if ($type === 'main') {
            echo $this->generateIndex();
        } else {
            echo $this->generateType($type);
        }
        exit;
    }

    public function generateIndex(): string
    {
        $sitemaps = $this->getSitemapList();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . plugins_url('assets/sitemap.xsl', $this->pluginDir . 'ai-seo-assistant.php') . '"?>' . "\n";
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
        $perPage = 1000;
        $offset = ($page - 1) * $perPage;

        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => 'publish',
            'posts_per_page' => $perPage,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . plugins_url('assets/sitemap.xsl', $this->pluginDir . 'ai-seo-assistant.php') . '"?>' . "\n";
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
        $postTypes = $this->getPublicPostTypes();

        foreach ($postTypes as $postType) {
            $counts = wp_count_posts($postType);
            $published = (int)($counts->publish ?? 0);
            $pages = (int)ceil($published / 1000);

            if ($pages === 1) {
                $sitemaps[] = [
                    'url' => home_url("/sitemap-{$postType}.xml"),
                    'lastmod' => current_time('c'),
                ];
            } else {
                for ($i = 1; $i <= $pages; $i++) {
                    $sitemaps[] = [
                        'url' => home_url("/sitemap-{$postType}-{$i}.xml"),
                        'lastmod' => current_time('c'),
                    ];
                }
            }
        }

        // Category sitemap
        $sitemaps[] = [
            'url' => home_url('/sitemap-categories.xml'),
            'lastmod' => current_time('c'),
        ];

        return $sitemaps;
    }

    private function getPublicPostTypes(): array
    {
        $postTypes = get_post_types(['public' => true], 'names');
        return array_diff($postTypes, ['attachment', 'aiseo_note', 'aiseo_prompt', 'aiseo_calendar']);
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
        $sitemapUrl = urlencode(home_url('/sitemap.xml'));
        wp_remote_get("https://www.google.com/ping?sitemap={$sitemapUrl}", ['timeout' => 5]);
        wp_remote_get("https://www.bing.com/ping?sitemap={$sitemapUrl}", ['timeout' => 5]);
    }
}

<?php

namespace SSEOAIClient;

class ExtendedSitemaps
{
    private SitemapGenerator $baseSitemap;
    private Settings $settings;

    public function __construct(SitemapGenerator $baseSitemap, Settings $settings)
    {
        $this->baseSitemap = $baseSitemap;
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules'], 20);
        add_action('template_redirect', [$this, 'maybeServeExtendedSitemap'], 5);
        add_action('save_post', [$this, 'onPostSave'], 10, 3);
        add_action('created_term', [$this, 'onTermChange'], 10, 3);
        add_action('edited_term', [$this, 'onTermChange'], 10, 3);
    }

    public function addRewriteRules(): void
    {
        // RSS Sitemap
        add_rewrite_rule('^sitemap-rss\.xml$', 'index.php?aiseo_sitemap_ext=rss', 'top');
        // Video Sitemap
        add_rewrite_rule('^sitemap-videos\.xml$', 'index.php?aiseo_sitemap_ext=videos', 'top');
        // News Sitemap
        add_rewrite_rule('^sitemap-news\.xml$', 'index.php?aiseo_sitemap_ext=news', 'top');
        // Image Sitemap
        add_rewrite_rule('^sitemap-images\.xml$', 'index.php?aiseo_sitemap_ext=images', 'top');
        // Author Sitemap
        add_rewrite_rule('^sitemap-authors\.xml$', 'index.php?aiseo_sitemap_ext=authors', 'top');

        add_filter('query_vars', [$this, 'addQueryVars']);
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = 'aiseo_sitemap_ext';
        return $vars;
    }

    public function maybeServeExtendedSitemap(): void
    {
        $type = get_query_var('aiseo_sitemap_ext');
        if (!$type) {
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        $method = 'generate' . ucfirst($type) . 'Sitemap';
        if (method_exists($this, $method)) {
            echo $this->$method();
        } else {
            status_header(404);
            echo '<?xml version="1.0"?><error>Not found</error>';
        }
        exit;
    }

    public function generateRssSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . esc_html(get_bloginfo('name')) . ' - Latest Posts</title>' . "\n";
        $xml .= '  <link>' . esc_url(home_url()) . '</link>' . "\n";
        $xml .= '  <description>' . esc_html(get_bloginfo('description')) . '</description>' . "\n";
        $xml .= '  <lastBuildDate>' . esc_html(gmdate('r')) . '</lastBuildDate>' . "\n";

        $posts = get_posts([
            'posts_per_page' => 50,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        foreach ($posts as $post) {
            $xml .= '  <item>' . "\n";
            $xml .= '    <title>' . esc_html($post->post_title) . '</title>' . "\n";
            $xml .= '    <link>' . esc_url(get_permalink($post)) . '</link>' . "\n";
            $xml .= '    <pubDate>' . esc_html(gmdate('r', strtotime($post->post_date_gmt))) . '</pubDate>' . "\n";
            $xml .= '    <guid>' . esc_url(get_permalink($post)) . '</guid>' . "\n";
            $xml .= '    <description><![CDATA[' . esc_html(wp_trim_words(strip_tags($post->post_content), 55)) . ']]></description>' . "\n";
            $xml .= '  </item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
    }

    public function generateVideosSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . plugins_url('assets/sitemap.xsl', __DIR__ . '/ai-seo-assistant.php') . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        // Find posts with video embeds
        $posts = get_posts([
            'posts_per_page' => 100,
            'post_status' => 'publish',
            's' => 'youtube|vimeo|video|embed',
        ]);

        foreach ($posts as $post) {
            $videos = $this->extractVideos($post->post_content);
            if (empty($videos)) {
                continue;
            }

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url(get_permalink($post)) . '</loc>' . "\n";

            foreach ($videos as $video) {
                $xml .= '    <video:video>' . "\n";
                $xml .= '      <video:thumbnail_loc>' . esc_url($video['thumbnail']) . '</video:thumbnail_loc>' . "\n";
                $xml .= '      <video:title>' . esc_html($video['title']) . '</video:title>' . "\n";
                $xml .= '      <video:description>' . esc_html(substr($video['description'], 0, 2048)) . '</video:description>' . "\n";
                $xml .= '      <video:content_loc>' . esc_url($video['url']) . '</video:content_loc>' . "\n";
                $xml .= '      <video:publication_date>' . esc_html(get_the_date('c', $post)) . '</video:publication_date>' . "\n";
                $xml .= '      <video:family_friendly>yes</video:family_friendly>' . "\n";
                $xml .= '    </video:video>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public function generateNewsSitemap(): string
    {
        $newsPostType = $this->settings->get('news_post_type', 'post');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . plugins_url('assets/sitemap.xsl', __DIR__ . '/ai-seo-assistant.php') . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";

        $posts = get_posts([
            'post_type' => $newsPostType,
            'posts_per_page' => 1000,
            'post_status' => 'publish',
            'date_query' => [
                'after' => '2 days ago',
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($posts as $post) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url(get_permalink($post)) . '</loc>' . "\n";
            $xml .= '    <news:news>' . "\n";
            $xml .= '      <news:publication>' . "\n";
            $xml .= '        <news:name>' . esc_html(get_bloginfo('name')) . '</news:name>' . "\n";
            $xml .= '        <news:language>' . esc_html(substr(get_locale(), 0, 2)) . '</news:language>' . "\n";
            $xml .= '      </news:publication>' . "\n";
            $xml .= '      <news:publication_date>' . esc_html(get_the_date('c', $post)) . '</news:publication_date>' . "\n";
            $xml .= '      <news:title>' . esc_html($post->post_title) . '</news:title>' . "\n";
            
            // Keywords from categories
            $cats = get_the_category($post->ID);
            if ($cats) {
                $keywords = implode(',', wp_list_pluck($cats, 'name'));
                $xml .= '      <news:keywords>' . esc_html($keywords) . '</news:keywords>' . "\n";
            }

            $xml .= '    </news:news>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public function generateImagesSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . plugins_url('assets/sitemap.xsl', __DIR__ . '/ai-seo-assistant.php') . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        $posts = get_posts([
            'posts_per_page' => 500,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        foreach ($posts as $post) {
            $images = $this->getPostImages($post);
            if (empty($images)) {
                continue;
            }

            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url(get_permalink($post)) . '</loc>' . "\n";

            foreach ($images as $image) {
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>' . esc_url($image['url']) . '</image:loc>' . "\n";
                if ($image['title']) {
                    $xml .= '      <image:title>' . esc_html($image['title']) . '</image:title>' . "\n";
                }
                if ($image['caption']) {
                    $xml .= '      <image:caption>' . esc_html($image['caption']) . '</image:caption>' . "\n";
                }
                $xml .= '    </image:image>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public function generateAuthorsSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . plugins_url('assets/sitemap.xsl', __DIR__ . '/ai-seo-assistant.php') . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $authors = get_users([
            'who' => 'authors',
            'has_published_posts' => true,
        ]);

        foreach ($authors as $author) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url(get_author_posts_url($author->ID)) . '</loc>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.3</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function extractVideos(string $content): array
    {
        $videos = [];

        // YouTube embeds
        if (preg_match_all('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/i', $content, $matches)) {
            foreach ($matches[1] as $videoId) {
                $videos[] = [
                    'url' => "https://www.youtube.com/watch?v={$videoId}",
                    'thumbnail' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                    'title' => 'YouTube Video',
                    'description' => 'Embedded YouTube video',
                ];
            }
        }

        // Vimeo embeds
        if (preg_match_all('/vimeo\.com\/video\/(\d+)/i', $content, $matches)) {
            foreach ($matches[1] as $videoId) {
                $videos[] = [
                    'url' => "https://vimeo.com/{$videoId}",
                    'thumbnail' => '',
                    'title' => 'Vimeo Video',
                    'description' => 'Embedded Vimeo video',
                ];
            }
        }

        // Video shortcode
        if (preg_match_all('/\[video[^\]]+src=[\'"]([^\'"]+)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $src) {
                $videos[] = [
                    'url' => $src,
                    'thumbnail' => '',
                    'title' => 'Video',
                    'description' => 'Embedded video',
                ];
            }
        }

        return $videos;
    }

    private function getPostImages(\WP_Post $post): array
    {
        $images = [];

        // Featured image
        if (has_post_thumbnail($post->ID)) {
            $thumbId = get_post_thumbnail_id($post->ID);
            $image = $this->getImageData($thumbId);
            if ($image) {
                $images[] = $image;
            }
        }

        // Content images
        if (preg_match_all('/<img[^>]+src=[\'"](.*?)[\'"][^>]*>/i', $post->post_content, $matches)) {
            foreach ($matches[1] as $src) {
                // Skip external URLs
                if (strpos($src, home_url()) !== 0 && strpos($src, '/') !== 0) {
                    continue;
                }

                // Try to get attachment ID
                $attachmentId = attachment_url_to_postid($src);
                if ($attachmentId) {
                    $image = $this->getImageData($attachmentId);
                    if ($image) {
                        $images[] = $image;
                    }
                } else {
                    $images[] = [
                        'url' => $src,
                        'title' => '',
                        'caption' => '',
                    ];
                }
            }
        }

        // Gallery images
        if (preg_match_all('/\[gallery[^\]]+ids=[\'"]([^\'"]+)[\'"]/i', $post->post_content, $matches)) {
            foreach ($matches[1] as $ids) {
                $galleryIds = array_map('intval', explode(',', $ids));
                foreach ($galleryIds as $gid) {
                    $image = $this->getImageData($gid);
                    if ($image) {
                        $images[] = $image;
                    }
                }
            }
        }

        return array_slice($images, 0, 20); // Limit per URL
    }

    private function getImageData(int $attachmentId): ?array
    {
        $url = wp_get_attachment_image_url($attachmentId, 'full');
        if (!$url) {
            return null;
        }

        return [
            'url' => $url,
            'title' => get_the_title($attachmentId),
            'caption' => wp_get_attachment_caption($attachmentId),
        ];
    }

    public function onPostSave(int $postId, \WP_Post $post, bool $update): void
    {
        if ($update && $post->post_status === 'publish') {
            // Ping search engines for news sitemap if applicable
            if ($this->isNewsPost($post)) {
                $this->pingNewsSitemap();
            }
        }
    }

    public function onTermChange(int $termId, int $ttId, string $taxonomy): void
    {
        // Trigger sitemap update for category changes
        if ($taxonomy === 'category') {
            do_action('aiseoclient_generate_sitemap');
        }
    }

    private function isNewsPost(\WP_Post $post): bool
    {
        $newsTypes = $this->settings->get('news_post_types', ['post']);
        return in_array($post->post_type, $newsTypes) &&
               strtotime($post->post_date) > strtotime('-48 hours');
    }

    private function pingNewsSitemap(): void
    {
        $sitemapUrl = urlencode(home_url('/sitemap-news.xml'));
        wp_remote_get("https://www.google.com/ping?sitemap={$sitemapUrl}", ['timeout' => 5]);
    }

    public function getSitemapUrls(): array
    {
        $urls = [
            'rss' => home_url('/sitemap-rss.xml'),
            'images' => home_url('/sitemap-images.xml'),
            'authors' => home_url('/sitemap-authors.xml'),
        ];

        if ($this->settings->get('enable_video_sitemap', 0)) {
            $urls['videos'] = home_url('/sitemap-videos.xml');
        }

        if ($this->settings->get('enable_news_sitemap', 0)) {
            $urls['news'] = home_url('/sitemap-news.xml');
        }

        return $urls;
    }
}

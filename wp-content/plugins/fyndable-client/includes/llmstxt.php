<?php

namespace SSEOAIClient;

/**
 * llms.txt Generator
 *
 * Generates and serves an /llms.txt file (per https://llmstxt.org) that gives
 * large language models a concise, markdown-formatted overview of the site's
 * published content. Served via a rewrite rule + parse_request interception,
 * the same pattern used by SitemapGenerator.
 *
 * Settings (stored via Settings::OPTION_KEY with llmstxt_* keys):
 *  - llmstxt_enabled        (bool,   default true)
 *  - llmstxt_post_types     (array,  default ['post'])
 *  - llmstxt_max_items      (int,    default 100)
 *  - llmstxt_description    (string, default bloginfo description)
 *  - llmstxt_custom_sections(string, markdown — optional extra sections appended)
 *  - llmstxt_include_excerpt(bool,   default true)
 *  - llmstxt_full_enabled   (bool,   default true)  — serve /llms-full.txt
 *  - llmstxt_selected_pages (array,  default [])    — page IDs to include (empty = all published pages)
 *  - llmstxt_full_max_chars (int,    default 50000) — max chars per item in the full version
 */
class LlmsTxt
{
    private Settings $settings;
    private const CACHE_KEY = 'sseo_ai_llmstxt_cache';
    private const CACHE_KEY_FULL = 'sseo_ai_llmstxt_full_cache';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules'], 10, 0);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('parse_request', [$this, 'interceptRequest'], 1);
        add_action('template_redirect', [$this, 'maybeServe']);
        add_action('save_post', [$this, 'invalidateCache'], 10, 2);
        add_action('delete_post', [$this, 'invalidateCache']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'addSettingsSection']);
        $this->maybeFlushRewriteRules();
    }

    // -------------------------------------------------------------------------
    // Rewrite rules & request interception
    // -------------------------------------------------------------------------

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^llms\.txt$', 'index.php?aiseo_llmstxt=1', 'top');
        add_rewrite_rule('^llms-full\.txt$', 'index.php?aiseo_llmstxt=full', 'top');
    }

    public function addQueryVars(array $vars): array
    {
        $vars[] = 'aiseo_llmstxt';
        return $vars;
    }

    /**
     * Intercept /llms.txt and /llms-full.txt requests at parse_request (before redirect_canonical).
     */
    public function interceptRequest(\WP $wp): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '';
        $path = trim($path, '/');

        if ($path === 'llms.txt') {
            $wp->query_vars['aiseo_llmstxt'] = '1';
            $this->maybeServe();
        } elseif ($path === 'llms-full.txt') {
            $wp->query_vars['aiseo_llmstxt'] = 'full';
            $this->maybeServe();
        }
    }

    public function maybeServe(): void
    {
        $serve = get_query_var('aiseo_llmstxt');
        if (!$serve) {
            return;
        }

        $isFull = ($serve === 'full');

        if (!$this->isEnabled()) {
            status_header(404);
            exit;
        }

        if ($isFull && !$this->isFullEnabled()) {
            status_header(404);
            exit;
        }

        $content = $isFull ? $this->getFullContent() : $this->getContent();
        $etag = md5($content);

        // ETag support
        $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($inm && trim($inm, '"') === $etag) {
            status_header(304);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=' . self::CACHE_TTL);
        header('ETag: "' . $etag . '"');
        echo $content;
        exit;
    }

    public function maybeFlushRewriteRules(): void
    {
        if (get_option('sseo_llmstxt_flushed') && get_option('sseo_llmstxt_full_flushed')) {
            return;
        }
        flush_rewrite_rules(false);
        update_option('sseo_llmstxt_flushed', 1);
        update_option('sseo_llmstxt_full_flushed', 1);
    }

    // -------------------------------------------------------------------------
    // Content generation
    // -------------------------------------------------------------------------

    public function getContent(): string
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $content = $this->generateContent();
        set_transient(self::CACHE_KEY, $content, self::CACHE_TTL);
        return $content;
    }

    public function generateContent(): string
    {
        $siteName = get_bloginfo('name');
        $description = $this->settings->get('llmstxt_description', '');
        if (empty($description)) {
            $description = get_bloginfo('description');
        }
        $postTypes = $this->settings->get('llmstxt_post_types', ['post']);
        if (!is_array($postTypes) || empty($postTypes)) {
            $postTypes = ['post'];
        }
        $maxItems = (int) $this->settings->get('llmstxt_max_items', 100);
        if ($maxItems < 1) {
            $maxItems = 100;
        }
        $includeExcerpt = (bool) $this->settings->get('llmstxt_include_excerpt', true);
        $customSections = $this->settings->get('llmstxt_custom_sections', '');
        $customSections = is_string($customSections) ? trim($customSections) : '';

        $lines = [];

        // H1 — site title
        $lines[] = '# ' . $siteName;
        $lines[] = '';

        // Blockquote — site description
        if ($description) {
            $lines[] = '> ' . $description;
            $lines[] = '';
        }

        // Posts grouped by post type
        $totalAdded = 0;
        foreach ($postTypes as $postType) {
            if ($totalAdded >= $maxItems) {
                break;
            }

            $postTypeObj = get_post_type_object($postType);
            if (!$postTypeObj) {
                continue;
            }

            $remaining = $maxItems - $totalAdded;
            $posts = get_posts([
                'post_type' => $postType,
                'post_status' => 'publish',
                'numberposts' => $remaining,
                'orderby' => 'date',
                'order' => 'DESC',
                'suppress_filters' => false,
            ]);

            if (empty($posts)) {
                continue;
            }

            $lines[] = '## ' . $postTypeObj->labels->name;
            $lines[] = '';

            foreach ($posts as $post) {
                $url = get_permalink($post);
                $title = trim($post->post_title);
                $entry = '- [' . $title . '](' . $url . ')';
                if ($includeExcerpt) {
                    $excerpt = $this->getPostSummary($post);
                    if ($excerpt) {
                        $entry .= ': ' . $excerpt;
                    }
                }
                $lines[] = $entry;
                $totalAdded++;
                if ($totalAdded >= $maxItems) {
                    break;
                }
            }
            $lines[] = '';
        }

        // Pages (selected or all) as links
        $pages = $this->getSelectedPages();
        if (!empty($pages) && $totalAdded < $maxItems) {
            $pageLabel = __('Pages', 'ai-seo-client');
            $lines[] = '## ' . $pageLabel;
            $lines[] = '';

            foreach ($pages as $page) {
                if ($totalAdded >= $maxItems) {
                    break;
                }
                $url = get_permalink($page);
                $title = trim($page->post_title);
                $entry = '- [' . $title . '](' . $url . ')';
                if ($includeExcerpt) {
                    $excerpt = $this->getPostSummary($page);
                    if ($excerpt) {
                        $entry .= ': ' . $excerpt;
                    }
                }
                $lines[] = $entry;
                $totalAdded++;
            }
            $lines[] = '';
        }

        // Optional custom sections (raw markdown appended as-is)
        if ($customSections) {
            $lines[] = trim($customSections);
            $lines[] = '';
        }

        // Link to the full-content version so AI crawlers can discover it
        if ($this->isFullEnabled()) {
            $fullUrl = home_url('/llms-full.txt');
            $lines[] = '## Full content';
            $lines[] = '';
            $lines[] = '- [Full site content](' . $fullUrl . '): Complete text of all posts and selected pages';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Get the cached (or freshly generated) full-content version.
     */
    public function getFullContent(): string
    {
        $cached = get_transient(self::CACHE_KEY_FULL);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $content = $this->generateFullContent();
        set_transient(self::CACHE_KEY_FULL, $content, self::CACHE_TTL);
        return $content;
    }

    /**
     * Generate the /llms-full.txt content — full body text of selected posts and pages.
     */
    public function generateFullContent(): string
    {
        $siteName = get_bloginfo('name');
        $description = $this->settings->get('llmstxt_description', '');
        if (empty($description)) {
            $description = get_bloginfo('description');
        }
        $postTypes = $this->settings->get('llmstxt_post_types', ['post']);
        if (!is_array($postTypes) || empty($postTypes)) {
            $postTypes = ['post'];
        }
        $maxItems = (int) $this->settings->get('llmstxt_max_items', 100);
        if ($maxItems < 1) {
            $maxItems = 100;
        }
        $maxChars = (int) $this->settings->get('llmstxt_full_max_chars', 50000);
        if ($maxChars < 100) {
            $maxChars = 50000;
        }

        $lines = [];

        // H1 — site title
        $lines[] = '# ' . $siteName;
        $lines[] = '';

        // Blockquote — site description
        if ($description) {
            $lines[] = '> ' . $description;
            $lines[] = '';
        }

        $totalAdded = 0;

        // Full content of posts (grouped by post type)
        foreach ($postTypes as $postType) {
            if ($totalAdded >= $maxItems) {
                break;
            }

            $postTypeObj = get_post_type_object($postType);
            if (!$postTypeObj) {
                continue;
            }

            $remaining = $maxItems - $totalAdded;
            $posts = get_posts([
                'post_type' => $postType,
                'post_status' => 'publish',
                'numberposts' => $remaining,
                'orderby' => 'date',
                'order' => 'DESC',
                'suppress_filters' => false,
            ]);

            if (empty($posts)) {
                continue;
            }

            $lines[] = '## ' . $postTypeObj->labels->name;
            $lines[] = '';

            foreach ($posts as $post) {
                $lines = array_merge($lines, $this->formatFullItem($post, $maxChars));
                $lines[] = '';
                $totalAdded++;
                if ($totalAdded >= $maxItems) {
                    break;
                }
            }
        }

        // Full content of selected pages
        $pages = $this->getSelectedPages();
        if (!empty($pages) && $totalAdded < $maxItems) {
            $pageLabel = __('Pages', 'ai-seo-client');
            $lines[] = '## ' . $pageLabel;
            $lines[] = '';

            foreach ($pages as $page) {
                if ($totalAdded >= $maxItems) {
                    break;
                }
                $lines = array_merge($lines, $this->formatFullItem($page, $maxChars));
                $lines[] = '';
                $totalAdded++;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single post/page as a full-content markdown block.
     *
     * @return string[]
     */
    private function formatFullItem(\WP_Post $post, int $maxChars): array
    {
        $url = get_permalink($post);
        $title = trim($post->post_title);
        $body = wp_strip_all_tags($post->post_content);
        $body = trim(preg_replace('/\s+/', ' ', $body));
        if (mb_strlen($body) > $maxChars) {
            $body = mb_substr($body, 0, $maxChars - 3) . '...';
        }

        $out = [];
        $out[] = '### ' . $title;
        $out[] = 'URL: ' . $url;
        $out[] = '';
        $out[] = $body;
        return $out;
    }

    /**
     * Get the selected pages as WP_Post objects.
     * When no selection is stored, all published pages are returned.
     *
     * @return \WP_Post[]
     */
    private function getSelectedPages(): array
    {
        $selectedIds = $this->settings->get('llmstxt_selected_pages', []);
        $selectedIds = is_array($selectedIds) ? array_filter(array_map('intval', $selectedIds)) : [];

        $queryArgs = [
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => false,
        ];

        if (!empty($selectedIds)) {
            $queryArgs['post__in'] = $selectedIds;
            $queryArgs['orderby'] = 'post__in';
        }

        return get_posts($queryArgs);
    }

    /**
     * Get a clean text summary for a post (excerpt or first paragraph).
     */
    private function getPostSummary(\WP_Post $post): string
    {
        $excerpt = get_the_excerpt($post);
        if ($excerpt) {
            $excerpt = wp_strip_all_tags($excerpt);
            $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt));
            if (mb_strlen($excerpt) > 200) {
                $excerpt = mb_substr($excerpt, 0, 197) . '...';
            }
            return $excerpt;
        }

        // Fallback: first paragraph of content
        $content = wp_strip_all_tags($post->post_content);
        $content = trim(preg_replace('/\s+/', ' ', $content));
        if (mb_strlen($content) > 200) {
            $content = mb_substr($content, 0, 197) . '...';
        }
        return $content;
    }

    // -------------------------------------------------------------------------
    // Cache invalidation
    // -------------------------------------------------------------------------

    public function invalidateCache(int $postId, ?\WP_Post $post = null): void
    {
        delete_transient(self::CACHE_KEY);
        delete_transient(self::CACHE_KEY_FULL);
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get('llmstxt_enabled', true);
    }

    public function isFullEnabled(): bool
    {
        return (bool) $this->settings->get('llmstxt_full_enabled', true);
    }

    public function registerSettings(): void
    {
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_enabled', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => fn($v) => (bool) $v,
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_post_types', [
            'type' => 'array',
            'default' => ['post'],
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return ['post'];
                }
                $valid = array_keys(get_post_types(['public' => true], 'names'));
                return array_values(array_filter(array_map('sanitize_text_field', $value), function ($t) use ($valid) {
                    return in_array($t, $valid, true);
                }));
            },
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_max_items', [
            'type' => 'integer',
            'default' => 100,
            'sanitize_callback' => fn($v) => max(1, min(1000, (int) $v)),
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_description', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_include_excerpt', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => fn($v) => (bool) $v,
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_custom_sections', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_full_enabled', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => fn($v) => (bool) $v,
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_selected_pages', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_values(array_filter(array_map('absint', $value)));
            },
        ]);
        register_setting(Settings::OPTION_KEY, 'sseo_ai_client_llmstxt_full_max_chars', [
            'type' => 'integer',
            'default' => 50000,
            'sanitize_callback' => fn($v) => max(100, min(500000, (int) $v)),
        ]);
    }

    public function addSettingsSection(): void
    {
        // Hooked into the existing Settings page via a custom action so the
        // settings UI stays in one place. The Settings page can render this
        // section by calling do_action('sseo_ai_render_llmstxt_settings').
        add_action('sseo_ai_render_llmstxt_settings', [$this, 'renderSettingsSection']);
    }

    public function renderSettingsSection(): void
    {
        $enabled = $this->isEnabled();
        $postTypes = $this->settings->get('llmstxt_post_types', ['post']);
        if (!is_array($postTypes)) {
            $postTypes = ['post'];
        }
        $maxItems = (int) $this->settings->get('llmstxt_max_items', 100);
        $description = $this->settings->get('llmstxt_description', '');
        $includeExcerpt = (bool) $this->settings->get('llmstxt_include_excerpt', true);
        $customSections = $this->settings->get('llmstxt_custom_sections', '');
        $llmsUrl = home_url('/llms.txt');
        $fullEnabled = $this->isFullEnabled();
        $fullMaxChars = (int) $this->settings->get('llmstxt_full_max_chars', 50000);
        $selectedPages = $this->settings->get('llmstxt_selected_pages', []);
        $selectedPages = is_array($selectedPages) ? array_map('intval', $selectedPages) : [];
        $fullUrl = home_url('/llms-full.txt');

        $publicPostTypes = get_post_types(['public' => true], 'objects');

        // All published pages for the selection list (capped for performance)
        $allPages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);
        ?>
        <h3><?php esc_html_e('llms.txt Generator', 'ai-seo-client'); ?></h3>
        <p class="description" style="margin-bottom: 15px;">
            <?php
            printf(
                /* translators: 1: llms.txt URL, 2: llms-full.txt URL */
                esc_html__('Serves a markdown overview at %1$s and a full-content version at %2$s for large language models (ChatGPT, Claude, Gemini, Perplexity).', 'ai-seo-client'),
                '<code>' . esc_html($llmsUrl) . '</code>',
                '<code>' . esc_html($fullUrl) . '</code>'
            );
            ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Enable llms.txt', 'ai-seo-client'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sseo_ai_client_llmstxt_enabled" value="1" <?php checked($enabled); ?>>
                        <?php esc_html_e('Serve /llms.txt', 'ai-seo-client'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Post types', 'ai-seo-client'); ?></th>
                <td>
                    <?php foreach ($publicPostTypes as $pt): ?>
                        <label style="display: inline-block; margin-right: 15px;">
                            <input type="checkbox" name="sseo_ai_client_llmstxt_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $postTypes, true)); ?>>
                            <?php echo esc_html($pt->labels->name); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Max items', 'ai-seo-client'); ?></th>
                <td>
                    <input type="number" name="sseo_ai_client_llmstxt_max_items" value="<?php echo esc_attr($maxItems); ?>" min="1" max="1000" class="small-text">
                    <p class="description"><?php esc_html_e('Maximum number of posts to include.', 'ai-seo-client'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Site description', 'ai-seo-client'); ?></th>
                <td>
                    <input type="text" name="sseo_ai_client_llmstxt_description" value="<?php echo esc_attr($description); ?>" class="regular-text" placeholder="<?php esc_attr_e('Defaults to the site tagline', 'ai-seo-client'); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Include excerpts', 'ai-seo-client'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sseo_ai_client_llmstxt_include_excerpt" value="1" <?php checked($includeExcerpt); ?>>
                        <?php esc_html_e('Append a short summary after each post link', 'ai-seo-client'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Custom sections', 'ai-seo-client'); ?></th>
                <td>
                    <textarea name="sseo_ai_client_llmstxt_custom_sections" rows="5" class="large-text code" placeholder="<?php esc_attr_e('## Additional resources&#10;- [About](https://example.com/about): ...', 'ai-seo-client'); ?>"><?php echo esc_textarea($customSections); ?></textarea>
                    <p class="description"><?php esc_html_e('Optional markdown appended after the post listings.', 'ai-seo-client'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Full-content version', 'ai-seo-client'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sseo_ai_client_llmstxt_full_enabled" value="1" <?php checked($fullEnabled); ?>>
                        <?php
                        printf(
                            /* translators: %s: llms-full.txt URL */
                            esc_html__('Serve %s with the full body text of posts and selected pages', 'ai-seo-client'),
                            '<code>/llms-full.txt</code>'
                        );
                        ?>
                    </label>
                    <p class="description" style="margin-top:8px;">
                        <label>
                            <?php esc_html_e('Max characters per item:', 'ai-seo-client'); ?>
                            <input type="number" name="sseo_ai_client_llmstxt_full_max_chars" value="<?php echo esc_attr($fullMaxChars); ?>" min="100" max="500000" step="500" class="small-text">
                        </label>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Pages to include', 'ai-seo-client'); ?></th>
                <td>
                    <p class="description" style="margin-top:0;margin-bottom:8px;">
                        <?php esc_html_e('Select which pages appear in both /llms.txt (as links) and /llms-full.txt (full content). Leave all unchecked to include every published page.', 'ai-seo-client'); ?>
                    </p>
                    <div style="max-height:240px;overflow:auto;border:1px solid #ddd;border-radius:4px;padding:10px;background:#fff;">
                        <?php if (empty($allPages)): ?>
                            <p class="description"><?php esc_html_e('No published pages found.', 'ai-seo-client'); ?></p>
                        <?php else: ?>
                            <?php foreach ($allPages as $pg): ?>
                                <label style="display:block;padding:3px 0;">
                                    <input type="checkbox" name="sseo_ai_client_llmstxt_selected_pages[]" value="<?php echo esc_attr($pg->ID); ?>" <?php checked(in_array((int) $pg->ID, $selectedPages, true)); ?>>
                                    <?php echo esc_html($pg->post_title); ?>
                                    <span style="color:#999;font-size:12px;">(<?php echo esc_html(get_permalink($pg)); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (count($allPages) >= 200): ?>
                        <p class="description" style="margin-top:6px;color:#d63638;">
                            <?php esc_html_e('Only the first 200 pages (by title) are shown. Use a lower selection or reduce page count to ensure all desired pages are listed.', 'ai-seo-client'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    // -------------------------------------------------------------------------
    // REST
    // -------------------------------------------------------------------------

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/llmstxt/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        register_rest_route('sseo-ai/v1', '/llmstxt/regenerate', [
            'methods' => 'POST',
            'callback' => [$this, 'restRegenerate'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    public function restGetStatus(): array
    {
        $url = home_url('/llms.txt');
        $response = wp_remote_get($url, ['timeout' => 5]);
        $exists = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        $size = !is_wp_error($response) ? strlen(wp_remote_retrieve_body($response) ?: '') : 0;

        $fullUrl = home_url('/llms-full.txt');
        $fullResponse = wp_remote_get($fullUrl, ['timeout' => 5]);
        $fullExists = !is_wp_error($fullResponse) && wp_remote_retrieve_response_code($fullResponse) === 200;
        $fullSize = !is_wp_error($fullResponse) ? strlen(wp_remote_retrieve_body($fullResponse) ?: '') : 0;

        $selectedPages = $this->settings->get('llmstxt_selected_pages', []);
        $selectedPages = is_array($selectedPages) ? count($selectedPages) : 0;

        return [
            'enabled' => $this->isEnabled(),
            'url' => $url,
            'exists' => $exists,
            'size' => $size,
            'post_types' => $this->settings->get('llmstxt_post_types', ['post']),
            'max_items' => (int) $this->settings->get('llmstxt_max_items', 100),
            'full_enabled' => $this->isFullEnabled(),
            'full_url' => $fullUrl,
            'full_exists' => $fullExists,
            'full_size' => $fullSize,
            'selected_pages_count' => $selectedPages,
        ];
    }

    public function restRegenerate(): array
    {
        $this->invalidateCache(0);
        $content = $this->getContent();
        $fullContent = $this->isFullEnabled() ? $this->getFullContent() : '';

        return [
            'success' => true,
            'size' => strlen($content),
            'url' => home_url('/llms.txt'),
            'full_size' => strlen($fullContent),
            'full_url' => home_url('/llms-full.txt'),
        ];
    }
}

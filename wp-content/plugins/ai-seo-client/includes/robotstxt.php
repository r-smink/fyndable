<?php

namespace AISEOClient;

class RobotsTxt
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('robots_txt', [$this, 'modifyRobotsTxt'], 100, 2);
        add_action('wp_loaded', [$this, 'maybeFlushRewriteRules']);
    }

    public function registerSettings(): void
    {
        register_setting(Settings::OPTION_KEY, Settings::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function sanitize(array $input): array
    {
        // Robots.txt content
        if (isset($input['robots_txt_content'])) {
            $input['robots_txt_content'] = sanitize_textarea_field($input['robots_txt_content']);
        }

        // Rules
        if (isset($input['robots_rules']) && is_array($input['robots_rules'])) {
            $clean = [];
            foreach ($input['robots_rules'] as $rule) {
                $clean[] = [
                    'user_agent' => sanitize_text_field($rule['user_agent'] ?? '*'),
                    'allow' => array_filter(array_map('sanitize_text_field', (array)($rule['allow'] ?? []))),
                    'disallow' => array_filter(array_map('sanitize_text_field', (array)($rule['disallow'] ?? []))),
                    'crawl_delay' => (int)($rule['crawl_delay'] ?? 0),
                    'sitemap' => esc_url_raw($rule['sitemap'] ?? ''),
                ];
            }
            $input['robots_rules'] = $clean;
        }

        return $input;
    }

    public function modifyRobotsTxt(string $output, bool $public): string
    {
        $custom = $this->settings->get('robots_txt_content', '');
        if ($custom) {
            return $custom;
        }

        // Build from rules
        $rules = $this->settings->get('robots_rules', []);
        if (empty($rules)) {
            // Default rules
            $rules = [
                [
                    'user_agent' => '*',
                    'allow' => [],
                    'disallow' => ['/wp-admin/', '/wp-includes/', '/wp-content/plugins/', '/search', '/author', '/trackback'],
                    'crawl_delay' => 0,
                ],
            ];
        }

        $lines = [];
        foreach ($rules as $rule) {
            $lines[] = 'User-agent: ' . ($rule['user_agent'] ?: '*');

            foreach ($rule['allow'] as $allow) {
                $lines[] = 'Allow: ' . $allow;
            }

            foreach ($rule['disallow'] as $disallow) {
                $lines[] = 'Disallow: ' . $disallow;
            }

            if (!empty($rule['crawl_delay'])) {
                $lines[] = 'Crawl-delay: ' . $rule['crawl_delay'];
            }

            $lines[] = '';
        }

        // Sitemap reference
        $sitemapUrl = home_url('/sitemap.xml');
        $lines[] = 'Sitemap: ' . $sitemapUrl;

        // Additional sitemaps from settings
        foreach ($rules as $rule) {
            if (!empty($rule['sitemap'])) {
                $lines[] = 'Sitemap: ' . $rule['sitemap'];
            }
        }

        return implode("\n", $lines) . "\n";
    }

    public function maybeFlushRewriteRules(): void
    {
        if (get_option('aiseo_flush_robots')) {
            flush_rewrite_rules();
            delete_option('aiseo_flush_robots');
        }
    }

    public function getDefaultRules(): array
    {
        return [
            [
                'user_agent' => '*',
                'allow' => ['/wp-admin/admin-ajax.php'],
                'disallow' => [
                    '/wp-admin/',
                    '/wp-includes/',
                    '/wp-content/plugins/',
                    '/wp-content/cache/',
                    '/wp-content/uploads/wpo/',
                    '/search',
                    '/author/',
                    '/trackback/',
                    '/feed/',
                    '/comments/feed/',
                    '/cgi-bin/',
                    '/wp-login.php',
                    '/wp-register.php',
                ],
                'crawl_delay' => 0,
                'sitemap' => '',
            ],
            [
                'user_agent' => 'Googlebot',
                'allow' => [],
                'disallow' => [],
                'crawl_delay' => 0,
                'sitemap' => '',
            ],
            [
                'user_agent' => 'Googlebot-Image',
                'allow' => ['/wp-content/uploads/'],
                'disallow' => [],
                'crawl_delay' => 0,
                'sitemap' => '',
            ],
            [
                'user_agent' => 'bingbot',
                'allow' => [],
                'disallow' => [],
                'crawl_delay' => 0,
                'sitemap' => '',
            ],
        ];
    }

    public function renderSettings(): void
    {
        $rules = $this->settings->get('robots_rules', $this->getDefaultRules());
        $custom = $this->settings->get('robots_txt_content', '');
        ?>
        <h3><?php esc_html_e('Robots.txt Editor', 'ai-seo-client'); ?></h3>
        <p><?php esc_html_e('Configure how search engines crawl your site.', 'ai-seo-client'); ?></p>

        <h4><?php esc_html_e('Custom robots.txt', 'ai-seo-client'); ?></h4>
        <p>
            <textarea name="aiseoassistant[robots_txt_content]" rows="10" style="width:100%; font-family:monospace;"><?php echo esc_textarea($custom); ?></textarea>
            <small><?php esc_html_e('Leave empty to use generated rules below', 'ai-seo-client'); ?></small>
        </p>

        <h4><?php esc_html_e('Rules Generator', 'ai-seo-client'); ?></h4>
        <div id="robots-rules">
            <?php foreach ($rules as $i => $rule) : ?>
            <div class="robot-rule" style="border:1px solid #ccc; padding:10px; margin:10px 0;">
                <p>
                    <label><?php esc_html_e('User-agent', 'ai-seo-client'); ?></label>
                    <input type="text" name="aiseoassistant[robots_rules][<?php echo $i; ?>][user_agent]" value="<?php echo esc_attr($rule['user_agent']); ?>" class="regular-text">
                </p>
                <p>
                    <label><?php esc_html_e('Allow paths (one per line)', 'ai-seo-client'); ?></label>
                    <textarea name="aiseoassistant[robots_rules][<?php echo $i; ?>][allow]" rows="3" style="width:100%;"><?php echo esc_textarea(implode("\n", (array)$rule['allow'])); ?></textarea>
                </p>
                <p>
                    <label><?php esc_html_e('Disallow paths (one per line)', 'ai-seo-client'); ?></label>
                    <textarea name="aiseoassistant[robots_rules][<?php echo $i; ?>][disallow]" rows="5" style="width:100%;"><?php echo esc_textarea(implode("\n", (array)$rule['disallow'])); ?></textarea>
                </p>
                <p>
                    <label><?php esc_html_e('Crawl-delay (seconds)', 'ai-seo-client'); ?></label>
                    <input type="number" name="aiseoassistant[robots_rules][<?php echo $i; ?>][crawl_delay]" value="<?php echo esc_attr($rule['crawl_delay']); ?>" min="0" max="60">
                </p>
            </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button" id="add-robot-rule"><?php esc_html_e('+ Add Rule', 'ai-seo-client'); ?></button>
        </p>

        <h4><?php esc_html_e('Preview', 'ai-seo-client'); ?></h4>
        <pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd;"><?php echo esc_html($this->modifyRobotsTxt('', true)); ?></pre>
        <?php
    }
}

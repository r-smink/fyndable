<?php

namespace SSEOAIClient;

/**
 * Demo Mode
 *
 * When enabled, the plugin operates without a real license key
 * and serves dummy data for all SEO features. Useful for:
 * - Sales demos
 * - Testing environments
 * - Sandbox/trial evaluation
 *
 * Toggle via: update_option('sseo_ai_demo_mode', '1')
 */
class DemoMode
{
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = get_option('sseo_ai_demo_mode', '0') === '1';
    }

    public function register(): void
    {
        // Register the admin bar badge globally so it can reflect the toggle state
        add_action('admin_bar_menu', [$this, 'addAdminBarBadge'], 1000);

        if (!$this->enabled) {
            return;
        }

        // Override license validation to always pass
        add_filter('sseo_ai_license_valid', '__return_true');
        add_filter('sseo_ai_license_tier', function () {
            return 'professional';
        });

        // Show demo banner in admin
        add_action('admin_notices', [$this, 'showDemoBanner']);

        // Provide dummy SEO data for posts
        add_filter('sseo_ai_truseo_score', [$this, 'dummyScore'], 10, 2);
        add_filter('sseo_ai_analysis_results', [$this, 'dummyAnalysis'], 10, 2);

        // Provide dummy rank tracking data
        add_filter('sseo_ai_rank_data', [$this, 'dummyRankData'], 10, 2);

        // Provide dummy dashboard stats
        add_filter('sseo_ai_dashboard_stats', [$this, 'dummyDashboardStats']);
    }

    /**
     * Get the HTML for a visual demo badge.
     */
    public function getBadgeHtml(string $label = 'DEMO'): string
    {
        return sprintf(
            '<span class="sseo-demo-badge" style="display:inline-block;background:#f59e0b;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px;letter-spacing:0.5px;margin-left:6px;">%s</span>',
            esc_html($label)
        );
    }

    /**
     * Add a DEMO badge to the WordPress admin bar so every page is visually labelled.
     */
    public function addAdminBarBadge(\WP_Admin_Bar $wpAdminBar): void
    {
        if (!$this->enabled) {
            return;
        }

        $wpAdminBar->add_node([
            'id'    => 'sseo-demo-mode-badge',
            'title' => '🎭 ' . esc_html__('Demo Mode', 'ai-seo-client'),
            'href'  => admin_url('admin.php?page=ai-seo-settings'),
            'meta'  => [
                'title' => esc_attr__('Demo mode is active — all data is fictitious', 'ai-seo-client'),
                'class' => 'sseo-demo-admin-bar-badge',
            ],
        ]);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function showDemoBanner(): void
    {
        echo '<div class="notice notice-warning" style="background:#fef3c7;border-left:4px solid #f59e0b;">';
        echo '<p><strong>🎭 Demo Mode Active</strong> — All data shown is fictitious. ';
        echo '<a href="' . esc_url(admin_url('admin.php?page=ai-seo-settings')) . '">Disable demo mode</a></p>';
        echo '</div>';
    }

    public function dummyScore($score, $postId): int
    {
        // Deterministic pseudo-random score based on post ID
        return 65 + ($postId % 30);
    }

    public function dummyAnalysis($analysis, $postId): array
    {
        return [
            [
                'label' => 'Focus Keyphrase',
                'status' => 'good',
                'message' => 'Keyphrase found in title and first paragraph.',
            ],
            [
                'label' => 'Meta Description',
                'status' => 'good',
                'message' => 'Meta description length is optimal (145 characters).',
            ],
            [
                'label' => 'Title Length',
                'status' => 'warning',
                'message' => 'Title is 62 characters. Recommended: 30-60.',
            ],
            [
                'label' => 'Internal Links',
                'status' => 'warning',
                'message' => 'Only 2 internal links found. Add more for better SEO.',
            ],
            [
                'label' => 'Image Alt Text',
                'status' => 'good',
                'message' => 'All images have alt text.',
            ],
            [
                'label' => 'Readability',
                'status' => 'good',
                'message' => 'Flesch reading ease: 62.4 (Standard).',
            ],
        ];
    }

    public function dummyRankData($data, $keyword): array
    {
        $hash = crc32($keyword);
        return [
            'keyword' => $keyword,
            'position' => 3 + ($hash % 20),
            'previous_position' => 5 + ($hash % 15),
            'change' => -2,
            'search_volume' => 1000 + ($hash % 50000),
            'difficulty' => 20 + ($hash % 60),
            'url' => home_url('/sample-page/'),
            'last_checked' => current_time('mysql'),
            'is_demo' => true,
            'demo_label' => esc_html__('Demo Data', 'ai-seo-client'),
        ];
    }

    public function dummyDashboardStats(): array
    {
        return [
            'total_keywords' => 127,
            'ranking_keywords' => 89,
            'top_3' => 12,
            'top_10' => 34,
            'top_100' => 89,
            'average_position' => 23.4,
            'traffic_estimate' => 4520,
            'indexed_pages' => 156,
            'issues_found' => 7,
            'issues_fixed' => 23,
            'content_score_avg' => 78,
            'ai_credits_used' => 340,
            'ai_credits_limit' => 2000,
            'is_demo' => true,
            'demo_label' => esc_html__('Demo Data', 'ai-seo-client'),
        ];
    }

    /**
     * Get dummy keywords for the keyword tracker.
     */
    public function getDummyKeywords(): array
    {
        return [
            ['keyword' => 'seo plugin wordpress', 'position' => 4, 'volume' => 12000, 'difficulty' => 45],
            ['keyword' => 'ai seo optimization', 'position' => 7, 'volume' => 8500, 'difficulty' => 52],
            ['keyword' => 'content analysis tool', 'position' => 12, 'volume' => 5400, 'difficulty' => 38],
            ['keyword' => 'schema markup generator', 'position' => 3, 'volume' => 3200, 'difficulty' => 29],
            ['keyword' => 'meta description writer', 'position' => 18, 'volume' => 2100, 'difficulty' => 41],
            ['keyword' => 'wordpress seo score', 'position' => 2, 'volume' => 6800, 'difficulty' => 35],
            ['keyword' => 'serp tracking tool', 'position' => 9, 'volume' => 4200, 'difficulty' => 48],
            ['keyword' => 'internal linking plugin', 'position' => 15, 'volume' => 1800, 'difficulty' => 33],
        ];
    }
}

<?php

namespace SSEOAIClient;

/**
 * Content Decay Detection
 * 
 * Detects when content loses rankings/traffic and alerts users.
 * Provides historical trend graphs and auto-suggestions for content refresh.
 */
class ContentDecay
{
    private const DECAY_TABLE = 'sseo_ai_content_decay';
    private const TREND_TABLE = 'sseo_ai_position_trends';
    private const ALERT_OPTION = 'sseo_ai_decay_alerts';
    
    private SnapshotRepository $snapshots;
    private GscClient $gscClient;
    private Settings $settings;
    
    public function __construct(SnapshotRepository $snapshots, GscClient $gscClient, Settings $settings)
    {
        $this->snapshots = $snapshots;
        $this->gscClient = $gscClient;
        $this->settings = $settings;
    }
    
    /**
     * Register hooks
     */
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('aiseoclient_decay_check', [$this, 'runDecayCheck']);
        add_action('admin_notices', [$this, 'showDecayAlerts']);
        add_action('add_meta_boxes', [$this, 'addDecayMetaBox']);
        
        // Cron voor dagelijkse decay check
        if (!wp_next_scheduled('aiseoclient_decay_check')) {
            wp_schedule_event(time(), 'daily', 'aiseoclient_decay_check');
        }
        
        // Admin menu moved to Client class
        
        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }
    
    /**
     * Create database tables
     */
    public function maybeCreateTable(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . self::DECAY_TABLE;
        $trend_table = $wpdb->prefix . self::TREND_TABLE;
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            keyword varchar(255) NOT NULL,
            baseline_position decimal(5,2) NOT NULL,
            current_position decimal(5,2) NOT NULL,
            position_change int NOT NULL,
            decay_percentage decimal(5,2) NOT NULL,
            traffic_change int NOT NULL DEFAULT 0,
            decay_type enum('ranking', 'traffic', 'both') NOT NULL DEFAULT 'ranking',
            severity enum('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
            detected_at datetime NOT NULL,
            last_checked datetime NOT NULL,
            status enum('active', 'acknowledged', 'fixed', 'ignored') NOT NULL DEFAULT 'active',
            suggestions text,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY keyword (keyword),
            KEY status (status),
            KEY severity (severity),
            KEY detected_at (detected_at)
        ) $charset_collate;";
        
        $sql2 = "CREATE TABLE IF NOT EXISTS $trend_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            keyword varchar(255) NOT NULL,
            position decimal(5,2) NOT NULL,
            date date NOT NULL,
            source enum('serp', 'gsc', 'manual') NOT NULL DEFAULT 'serp',
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY keyword_date (keyword, date),
            KEY date (date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
    }
    
    /**
     * Run daily decay check
     */
    public function runDecayCheck(): void
    {
        $posts = $this->getTrackedPosts();
        
        foreach ($posts as $post) {
            $this->analyzePostDecay($post);
        }
        
        // Cleanup oude data
        $this->cleanupOldData();
    }
    
    /**
     * Analyze decay for a single post
     */
    private function analyzePostDecay(\WP_Post $post): void
    {
        $keywords = $this->getPostKeywords($post);
        
        foreach ($keywords as $keyword) {
            $trend = $this->getPositionTrend($post->ID, $keyword);
            
            if (empty($trend) || count($trend) < 7) {
                continue; // Niet genoeg data
            }
            
            $baseline = $this->calculateBaseline($trend);
            $current = $trend[0]['position']; // Meest recent
            
            $change = $baseline - $current; // Positief = slechter (hoger nummer)
            $decayPercentage = $baseline > 0 ? ($change / $baseline) * 100 : 0;
            
            // Alleen opslaan als er significante decay is
            if ($change >= 3) { // Minstens 3 posities gezakt
                $severity = $this->calculateSeverity($change, $decayPercentage);
                $trafficChange = $this->estimateTrafficChange($baseline, $current);
                $decayType = $this->determineDecayType($change, $trafficChange);
                
                $this->saveDecayAlert([
                    'post_id' => $post->ID,
                    'keyword' => $keyword,
                    'baseline_position' => $baseline,
                    'current_position' => $current,
                    'position_change' => $change,
                    'decay_percentage' => $decayPercentage,
                    'traffic_change' => $trafficChange,
                    'decay_type' => $decayType,
                    'severity' => $severity,
                    'detected_at' => current_time('mysql'),
                    'last_checked' => current_time('mysql'),
                    'suggestions' => wp_json_encode($this->generateSuggestions($post, $keyword, $change)),
                ]);
            }
            
            // Altijd trend data opslaan
            $this->saveTrendData($post->ID, $keyword, $current);
        }
    }
    
    /**
     * Get tracked posts (posts with focus keywords or in snapshot)
     */
    private function getTrackedPosts(): array
    {
        global $wpdb;
        
        // Posts with focus keyphrase meta
        $postIds = $wpdb->get_col("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_sseo_ai_focus_keyphrase'
            AND meta_value != ''
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_status = 'publish'
            )
        ");
        
        if (empty($postIds)) {
            return [];
        }
        
        return get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'include' => $postIds,
            'posts_per_page' => -1,
        ]);
    }
    
    /**
     * Get keywords for a post
     */
    private function getPostKeywords(\WP_Post $post): array
    {
        $keywords = [];
        
        // Focus keyphrase from meta
        $focusKeyphrase = get_post_meta($post->ID, '_sseo_ai_focus_keyphrase', true);
        if ($focusKeyphrase) {
            $keywords[] = $focusKeyphrase;
        }
        
        // Secondary keyphrases
        $secondary = get_post_meta($post->ID, '_sseo_ai_secondary_keyphrases', true);
        if ($secondary) {
            $keywords = array_merge($keywords, array_map('trim', explode(',', $secondary)));
        }
        
        return array_unique(array_filter($keywords));
    }
    
    /**
     * Get position trend for keyword
     */
    private function getPositionTrend(int $postId, string $keyword): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TREND_TABLE;
        
        // Eerst uit trend tabel
        $trends = $wpdb->get_results($wpdb->prepare("
            SELECT position, date 
            FROM $table 
            WHERE post_id = %d AND keyword = %s 
            ORDER BY date DESC 
            LIMIT 30
        ", $postId, $keyword), ARRAY_A);
        
        if (count($trends) >= 7) {
            return $trends;
        }
        
        // Supplement from snapshots — parse position from JSON results
        $snapshots = $wpdb->get_results($wpdb->prepare("
            SELECT results, DATE(created_at) as date
            FROM {$wpdb->prefix}aiseoclient_snapshots
            WHERE keyword = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY created_at DESC
        ", $keyword), ARRAY_A);
        
        $parsed = [];
        foreach ($snapshots as $snap) {
            $results = json_decode($snap['results'], true) ?: [];
            $bestPos = null;
            foreach ($results as $item) {
                if (isset($item['position'])) {
                    $bestPos = $bestPos === null ? $item['position'] : min($bestPos, $item['position']);
                }
            }
            if ($bestPos !== null) {
                $parsed[] = ['position' => (float)$bestPos, 'date' => $snap['date']];
            }
        }
        
        return array_merge($trends, $parsed);
    }
    
    /**
     * Calculate baseline position (average of older positions)
     */
    private function calculateBaseline(array $trend): float
    {
        // Use the oldest 50% as baseline
        $count = count($trend);
        $baselineCount = max(3, (int)($count * 0.5));
        $baselinePositions = array_slice($trend, -$baselineCount, $baselineCount);
        
        $sum = array_sum(array_column($baselinePositions, 'position'));
        $baselineCount = count($baselinePositions);
        return $baselineCount > 0 ? round($sum / $baselineCount, 2) : 0;
    }
    
    /**
     * Calculate severity level
     */
    private function calculateSeverity(int $positionChange, float $decayPercentage): string
    {
        if ($positionChange >= 10 || $decayPercentage >= 50) {
            return 'critical';
        }
        if ($positionChange >= 7 || $decayPercentage >= 35) {
            return 'high';
        }
        if ($positionChange >= 5 || $decayPercentage >= 20) {
            return 'medium';
        }
        return 'low';
    }
    
    /**
     * Estimate traffic change based on position
     */
    private function estimateTrafficChange(float $baseline, float $current): int
    {
        // Vereenvoudigde CTR model
        $ctrRates = [
            1 => 0.30, 2 => 0.15, 3 => 0.10, 4 => 0.08,
            5 => 0.06, 6 => 0.05, 7 => 0.04, 8 => 0.035,
            9 => 0.03, 10 => 0.025, 11 => 0.02, 15 => 0.015,
            20 => 0.01, 30 => 0.005, 40 => 0.003, 50 => 0.001,
        ];
        
        $baselineCtr = $this->getCtrForPosition($baseline, $ctrRates);
        $currentCtr = $this->getCtrForPosition($current, $ctrRates);
        
        // Geschatte traffic change percentage
        if ($baselineCtr <= 0) return 0;
        
        return (int)((($currentCtr - $baselineCtr) / $baselineCtr) * 100);
    }
    
    /**
     * Get CTR for position (interpolated)
     */
    private function getCtrForPosition(float $position, array $rates): float
    {
        $intPos = (int)ceil($position);
        
        if (isset($rates[$intPos])) {
            return $rates[$intPos];
        }
        
        // Interpolate
        $positions = array_keys($rates);
        sort($positions);
        
        foreach ($positions as $i => $pos) {
            if ($pos >= $intPos && isset($positions[$i - 1])) {
                $prevPos = $positions[$i - 1];
                $prevCtr = $rates[$prevPos];
                $currCtr = $rates[$pos];
                
                $diff = $pos - $prevPos;
                $step = ($currCtr - $prevCtr) / $diff;
                
                return max(0, $prevCtr + ($step * ($intPos - $prevPos)));
            }
        }
        
        return 0.0005; // Sub 50
    }
    
    /**
     * Determine decay type
     */
    private function determineDecayType(int $positionChange, int $trafficChange): string
    {
        if ($trafficChange <= -20 && $positionChange >= 3) {
            return 'both';
        }
        if ($trafficChange <= -20) {
            return 'traffic';
        }
        return 'ranking';
    }
    
    /**
     * Save decay alert
     */
    private function saveDecayAlert(array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::DECAY_TABLE;
        
        // Check if there's already an active alert for this combination
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $table 
            WHERE post_id = %d AND keyword = %s AND status = 'active'
        ", $data['post_id'], $data['keyword']));
        
        if ($existing) {
            // Update existing alert
            $wpdb->update(
                $table,
                [
                    'current_position' => $data['current_position'],
                    'position_change' => $data['position_change'],
                    'decay_percentage' => $data['decay_percentage'],
                    'traffic_change' => $data['traffic_change'],
                    'last_checked' => $data['last_checked'],
                    'severity' => $data['severity'],
                ],
                ['id' => $existing],
                ['%f', '%d', '%f', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            // Nieuwe alert
            $wpdb->insert($table, $data);
        }
    }
    
    /**
     * Save trend data
     */
    private function saveTrendData(int $postId, string $keyword, float $position): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TREND_TABLE;
        $today = current_time('Y-m-d');
        
        // Check of er al een entry is voor vandaag
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM $table 
            WHERE post_id = %d AND keyword = %s AND date = %s
        ", $postId, $keyword, $today));
        
        if (!$existing) {
            $wpdb->insert($table, [
                'post_id' => $postId,
                'keyword' => $keyword,
                'position' => $position,
                'date' => $today,
                'source' => 'serp',
            ]);
        }
    }
    
    /**
     * Generate suggestions for fixing decay
     */
    private function generateSuggestions(\WP_Post $post, string $keyword, int $positionChange): array
    {
        $suggestions = [];
        
        if ($positionChange >= 5) {
            $suggestions[] = [
                'type' => 'content_refresh',
                'title' => __('Update content with fresh information', 'ai-seo-client'),
                'description' => __('Your content may be outdated. Consider adding new insights, statistics, or recent developments.', 'ai-seo-client'),
                'action' => 'edit',
                'link' => get_edit_post_link($post->ID),
            ];
        }
        
        if ($positionChange >= 3) {
            $suggestions[] = [
                'type' => 'title_meta',
                'title' => __('Optimize title and meta description', 'ai-seo-client'),
                'description' => __('Review your title tag and meta description for better CTR.', 'ai-seo-client'),
                'action' => 'edit',
                'link' => get_edit_post_link($post->ID),
            ];
        }
        
        // Check internal links
        $internalLinks = $this->countInternalLinks($post);
        if ($internalLinks < 3) {
            $suggestions[] = [
                'type' => 'internal_links',
                'title' => __('Add more internal links', 'ai-seo-client'),
                'description' => sprintf(__('This post only has %d internal links. Add more to improve authority flow.', 'ai-seo-client'), $internalLinks),
                'action' => 'link_assistant',
                'link' => admin_url('admin.php?page=ai-seo-assistant-link-assistant'),
            ];
        }
        
        $suggestions[] = [
            'type' => 'competitor_analysis',
            'title' => __('Analyze top competitors', 'ai-seo-client'),
            'description' => __('See what content is now ranking higher and identify gaps.', 'ai-seo-client'),
            'action' => 'competitor_gap',
            'link' => admin_url('admin.php?page=ai-seo-assistant-competitor'),
        ];
        
        return $suggestions;
    }
    
    /**
     * Count internal links in post
     */
    private function countInternalLinks(\WP_Post $post): int
    {
        $content = $post->post_content;
        $siteUrl = parse_url(home_url(), PHP_URL_HOST);
        
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        $internalCount = 0;
        foreach ($matches[1] as $url) {
            if (strpos($url, $siteUrl) !== false || strpos($url, '/') === 0) {
                $internalCount++;
            }
        }
        
        return $internalCount;
    }
    
    /**
     * Show decay alerts in admin
     */
    public function showDecayAlerts(): void
    {
        // Alleen tonen op dashboard en AI SEO pagina's
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['dashboard', 'toplevel_page_ai-seo-assistant', 'ai-seo-assistant_page_ai-seo-assistant-decay'])) {
            return;
        }
        
        $criticalAlerts = $this->getAlertsBySeverity('critical', 5);
        $highAlerts = $this->getAlertsBySeverity('high', 3);
        
        if (!empty($criticalAlerts)) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html__('Content Decay Alert:', 'ai-seo-client') . '</strong></p>';
            echo '<ul>';
            foreach ($criticalAlerts as $alert) {
                $post = get_post($alert['post_id']);
                if ($post) {
                    echo '<li>';
                    echo sprintf(
                        esc_html__('"%1$s" dropped %2$d positions for "%3$s"', 'ai-seo-client'),
                        esc_html($post->post_title),
                        $alert['position_change'],
                        esc_html($alert['keyword'])
                    );
                    echo ' <a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html__('Fix now', 'ai-seo-client') . '</a>';
                    echo '</li>';
                }
            }
            echo '</ul>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=ai-seo-assistant-decay')) . '" class="button">' . esc_html__('View All Decay Alerts', 'ai-seo-client') . '</a></p>';
            echo '</div>';
        }
        
        if (!empty($highAlerts)) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__('Content Decay Warning:', 'ai-seo-client') . '</strong> ';
            echo sprintf(esc_html_n('%d post is losing rankings', '%d posts are losing rankings', count($highAlerts), 'ai-seo-client'), count($highAlerts));
            echo '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Get alerts by severity
     */
    private function getAlertsBySeverity(string $severity, int $limit = 10): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::DECAY_TABLE;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE severity = %s AND status = 'active' 
            ORDER BY position_change DESC 
            LIMIT %d
        ", $severity, $limit), ARRAY_A);
    }
    
    /**
     * Add decay meta box to posts
     */
    public function addDecayMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true]);
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'aiseo_content_decay',
                __('SEO Position Trends', 'ai-seo-client'),
                [$this, 'renderDecayMetaBox'],
                $postType,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render decay meta box
     */
    public function renderDecayMetaBox(\WP_Post $post): void
    {
        $alerts = $this->getActiveAlertsForPost($post->ID);
        $trends = $this->getTrendChartData($post->ID);
        
        echo '<div class="aiseo-decay-metabox">';
        
        if (!empty($alerts)) {
            echo '<div class="notice notice-warning inline" style="margin: 0 0 10px;">';
            echo '<p><strong>' . esc_html__('⚠️ Content Decay Detected', 'ai-seo-client') . '</strong></p>';
            foreach ($alerts as $alert) {
                echo '<p>';
                echo sprintf(
                    esc_html__('"%s" dropped %d positions', 'ai-seo-client'),
                    esc_html($alert['keyword']),
                    $alert['position_change']
                );
                echo '</p>';
            }
            echo '</div>';
        }
        
        if (!empty($trends)) {
            echo '<div class="aiseo-trend-chart" style="height: 100px; margin: 10px 0;">';
            echo '<canvas id="aiseo-trend-chart-' . esc_attr($post->ID) . '"></canvas>';
            echo '</div>';
            echo '<p class="description">' . esc_html__('Position trend (last 30 days)', 'ai-seo-client') . '</p>';
            
            // Inline chart script
            echo '<script>';
            echo '(function(){';
            echo 'var ctx = document.getElementById("aiseo-trend-chart-' . $post->ID . '").getContext("2d");';
            echo 'new Chart(ctx, {';
            echo 'type: "line",';
            echo 'data: {';
            echo 'labels: ' . wp_json_encode($trends['dates']) . ',';
            echo 'datasets: [{';
            echo 'label: "Position",';
            echo 'data: ' . wp_json_encode($trends['positions']) . ',';
            echo 'borderColor: "#667eea",';
            echo 'tension: 0.4';
            echo '}]';
            echo '},';
            echo 'options: {';
            echo 'responsive: true,';
            echo 'maintainAspectRatio: false,';
            echo 'scales: { y: { reverse: true, min: 1, max: 50 } }';
            echo '}';
            echo '});';
            echo '})();';
            echo '</script>';
        } else {
            echo '<p class="description">' . esc_html__('No trend data available yet. Track keywords to see position trends.', 'ai-seo-client') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Get active alerts for post
     */
    private function getActiveAlertsForPost(int $postId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::DECAY_TABLE;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table 
            WHERE post_id = %d AND status = 'active'
            ORDER BY severity DESC, position_change DESC
        ", $postId), ARRAY_A);
    }
    
    /**
     * Get trend chart data for post
     */
    private function getTrendChartData(int $postId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TREND_TABLE;
        
        $data = $wpdb->get_results($wpdb->prepare("
            SELECT keyword, AVG(position) as avg_position, date
            FROM $table 
            WHERE post_id = %d 
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY date
            ORDER BY date ASC
        ", $postId), ARRAY_A);
        
        if (empty($data)) {
            return null;
        }
        
        return [
            'dates' => array_column($data, 'date'),
            'positions' => array_map('floatval', array_column($data, 'avg_position')),
        ];
    }
    
    /**
     * Add decay menu item
     */
    public function addDecayMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Content Decay', 'ai-seo-client'),
            __('Content Decay', 'ai-seo-client'),
            'manage_options',
            'ai-seo-assistant-decay',
            [$this, 'renderDecayPage']
        );
    }
    
    /**
     * Render decay page
     */
    public function renderDecayPage(): void
    {
        $alerts = $this->getAllAlerts();
        $stats = $this->getDecayStats();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Content Decay Detection', 'ai-seo-client'); ?></h1>
            
            <div class="aiseo-decay-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #dc3545;"><?php echo esc_html($stats['critical']); ?></div>
                    <div><?php esc_html_e('Critical', 'ai-seo-client'); ?></div>
                </div>
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #fd7e14;"><?php echo esc_html($stats['high']); ?></div>
                    <div><?php esc_html_e('High', 'ai-seo-client'); ?></div>
                </div>
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #ffc107;"><?php echo esc_html($stats['medium']); ?></div>
                    <div><?php esc_html_e('Medium', 'ai-seo-client'); ?></div>
                </div>
                <div class="postbox" style="padding: 20px; text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; color: #28a745;"><?php echo esc_html($stats['total_traffic_loss']); ?>%</div>
                    <div><?php esc_html_e('Est. Traffic Loss', 'ai-seo-client'); ?></div>
                </div>
            </div>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="filter-severity">
                        <option value=""><?php esc_html_e('All Severities', 'ai-seo-client'); ?></option>
                        <option value="critical"><?php esc_html_e('Critical', 'ai-seo-client'); ?></option>
                        <option value="high"><?php esc_html_e('High', 'ai-seo-client'); ?></option>
                        <option value="medium"><?php esc_html_e('Medium', 'ai-seo-client'); ?></option>
                        <option value="low"><?php esc_html_e('Low', 'ai-seo-client'); ?></option>
                    </select>
                    <select id="filter-status">
                        <option value="active"><?php esc_html_e('Active', 'ai-seo-client'); ?></option>
                        <option value="acknowledged"><?php esc_html_e('Acknowledged', 'ai-seo-client'); ?></option>
                        <option value="fixed"><?php esc_html_e('Fixed', 'ai-seo-client'); ?></option>
                    </select>
                    <button type="button" class="button" onclick="filterDecayAlerts()"><?php esc_html_e('Filter', 'ai-seo-client'); ?></button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Post', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Keyword', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Position Change', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Traffic Impact', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Severity', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Detected', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert): 
                        $post = get_post($alert['post_id']);
                        if (!$post) continue;
                    ?>
                    <tr data-severity="<?php echo esc_attr($alert['severity']); ?>" data-status="<?php echo esc_attr($alert['status']); ?>">
                        <td>
                            <strong><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
                            <br><small><?php echo esc_html(get_the_category_list(', ', '', $post->ID)); ?></small>
                        </td>
                        <td><code><?php echo esc_html($alert['keyword']); ?></code></td>
                        <td>
                            <span style="color: #dc3545; font-weight: bold;">-<?php echo esc_html($alert['position_change']); ?></span>
                            <br><small><?php echo esc_html($alert['baseline_position']); ?> → <?php echo esc_html($alert['current_position']); ?></small>
                        </td>
                        <td>
                            <span style="color: <?php echo $alert['traffic_change'] < -30 ? '#dc3545' : '#fd7e14'; ?>;">
                                <?php echo esc_html($alert['traffic_change']); ?>%
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="
                                display: inline-block;
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 12px;
                                font-weight: bold;
                                text-transform: uppercase;
                                <?php echo $this->getSeverityStyle($alert['severity']); ?>
                            ">
                                <?php echo esc_html($alert['severity']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(human_time_diff(strtotime($alert['detected_at']), current_time('timestamp')) . ' ago'); ?></td>
                        <td>
                            <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" class="button button-small"><?php esc_html_e('Edit Post', 'ai-seo-client'); ?></a>
                            <button type="button" class="button button-small" onclick="showSuggestions(<?php echo esc_js($alert['id']); ?>)"><?php esc_html_e('Suggestions', 'ai-seo-client'); ?></button>
                            <button type="button" class="button button-small" onclick="acknowledgeAlert(<?php echo esc_js($alert['id']); ?>)"><?php esc_html_e('Ack', 'ai-seo-client'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($alerts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <p style="font-size: 16px; color: #666;">🎉 <?php esc_html_e('No content decay detected! Your content is performing well.', 'ai-seo-client'); ?></p>
                            <p class="description"><?php esc_html_e('We\'ll check daily and alert you when content starts losing rankings.', 'ai-seo-client'); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        function filterDecayAlerts() {
            var severity = document.getElementById('filter-severity').value;
            var status = document.getElementById('filter-status').value;
            
            document.querySelectorAll('tbody tr').forEach(function(row) {
                var show = true;
                if (severity && row.dataset.severity !== severity) show = false;
                if (status && row.dataset.status !== status) show = false;
                row.style.display = show ? '' : 'none';
            });
        }
        
        function acknowledgeAlert(id) {
            if (confirm('<?php esc_html_e('Acknowledge this alert? It will be moved to acknowledged status.', 'ai-seo-client'); ?>')) {
                // AJAX call to acknowledge
                jQuery.post(ajaxurl, {
                    action: 'aiseo_acknowledge_decay',
                    id: id,
                    nonce: '<?php echo wp_create_nonce('aiseo_decay'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            }
        }
        
        function showSuggestions(id) {
            // Open modal with suggestions
            // Implementation depends on your modal system
            alert('<?php esc_html_e('Suggestions would appear in a modal here', 'ai-seo-client'); ?>');
        }
        </script>
        <?php
    }
    
    /**
     * Get severity badge style
     */
    private function getSeverityStyle(string $severity): string
    {
        $styles = [
            'critical' => 'background: #dc3545; color: #fff;',
            'high' => 'background: #fd7e14; color: #fff;',
            'medium' => 'background: #ffc107; color: #000;',
            'low' => 'background: #6c757d; color: #fff;',
        ];
        
        return $styles[$severity] ?? $styles['low'];
    }
    
    /**
     * Get all decay alerts
     */
    private function getAllAlerts(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::DECAY_TABLE;
        
        return $wpdb->get_results("
            SELECT * FROM $table 
            WHERE status = 'active'
            ORDER BY 
                FIELD(severity, 'critical', 'high', 'medium', 'low'),
                position_change DESC
        ", ARRAY_A);
    }
    
    /**
     * Get decay statistics
     */
    private function getDecayStats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::DECAY_TABLE;
        
        $stats = [
            'critical' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE severity = 'critical' AND status = 'active'"),
            'high' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE severity = 'high' AND status = 'active'"),
            'medium' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE severity = 'medium' AND status = 'active'"),
            'low' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE severity = 'low' AND status = 'active'"),
            'total_traffic_loss' => $wpdb->get_var("SELECT AVG(traffic_change) FROM $table WHERE status = 'active'"),
        ];
        
        $stats['total_traffic_loss'] = round(abs($stats['total_traffic_loss']), 1);
        
        return $stats;
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/decay', [
            'methods' => 'GET',
            'callback' => [$this, 'getDecayData'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/decay/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'updateDecayStatus'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    /**
     * REST: Get decay data
     */
    public function getDecayData(): \WP_REST_Response
    {
        $alerts = $this->getAllAlerts();
        $stats = $this->getDecayStats();
        
        return new \WP_REST_Response([
            'alerts' => $alerts,
            'stats' => $stats,
        ], 200);
    }
    
    /**
     * REST: Update decay status
     */
    public function updateDecayStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request['id'];
        $status = $request->get_param('status');
        
        global $wpdb;
        $table = $wpdb->prefix . self::DECAY_TABLE;
        
        $wpdb->update(
            $table,
            ['status' => sanitize_text_field($status)],
            ['id' => intval($id)],
            ['%s'],
            ['%d']
        );
        
        return new \WP_REST_Response(['success' => true], 200);
    }
    
    /**
     * Cleanup old data (older than 90 days)
     */
    private function cleanupOldData(): void
    {
        global $wpdb;
        
        // Verwijder oude trend data
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}" . self::TREND_TABLE . "
            WHERE date < DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        
        // Verwijder oude fixed/ignored alerts
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}" . self::DECAY_TABLE . "
            WHERE status IN ('fixed', 'ignored')
            AND last_checked < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
}

<?php

namespace SSEOAIClient;

/**
 * Content Performance Monitor
 * 
 * Tracks content performance with:
 * - Google Analytics 4 (GA4) integration
 * - Traffic attribution per optimization
 * - Lead/conversion tracking
 * - Content ROI calculator
 * - Performance baseline vs optimization metrics
 * - A/B testing framework for titles/meta
 * - Time-to-rank tracking
 */
class ContentPerformanceMonitor
{
    private Settings $settings;
    private ?GA4Client $ga4Client = null;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->ga4Client = new GA4Client($settings);
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        
        // Track performance changes
        add_action('sseo_ai_seo_optimized', [$this, 'recordOptimization'], 10, 2);
        add_action('wp_ajax_sseo_ai_track_conversion', [$this, 'ajaxTrackConversion']);
        
        // Scheduled performance checks
        if (!wp_next_scheduled('sseo_ai_check_performance')) {
            wp_schedule_event(time(), 'daily', 'sseo_ai_check_performance');
        }
        add_action('sseo_ai_check_performance', [$this, 'checkPerformance']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Performance Monitor', 'ai-seo-client'),
            __('Performance', 'ai-seo-client'),
            'manage_options',
            'ai-seo-performance',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        // GA4 Integration
        register_setting('sseo_ai_performance', 'sseo_ai_ga4_measurement_id');
        register_setting('sseo_ai_performance', 'sseo_ai_ga4_api_secret');
        register_setting('sseo_ai_performance', 'sseo_ai_ga4_property_id');
        
        // Conversion Tracking
        register_setting('sseo_ai_performance', 'sseo_ai_conversion_goals', ['default' => []]);
        register_setting('sseo_ai_performance', 'sseo_ai_track_form_submissions', ['default' => true]);
        register_setting('sseo_ai_performance', 'sseo_ai_track_button_clicks', ['default' => true]);
        
        // A/B Testing
        register_setting('sseo_ai_performance', 'sseo_ai_ab_testing_enabled', ['default' => false]);
        register_setting('sseo_ai_performance', 'sseo_ai_ab_test_duration', ['default' => 14]);
    }
    
    /**
     * Render performance dashboard
     */
    public function renderDashboard(): void
    {
        $ga4MeasurementId = get_option('sseo_ai_ga4_measurement_id', '');
        $ga4Connected = !empty($ga4MeasurementId);
        
        // Get performance data
        $performanceData = $this->getPerformanceOverview();
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Content Performance Monitor', 'ai-seo-client'); ?></h1>
            
            <!-- GA4 Connection Status -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Google Analytics 4 Integration', 'ai-seo-client'); ?></h2>
                
                <?php if ($ga4Connected): ?>
                <div class="notice notice-success inline">
                    <p><strong><?php esc_html_e('✓ Connected to GA4', 'ai-seo-client'); ?></strong></p>
                    <p><?php esc_html_e('Measurement ID:', 'ai-seo-client'); ?> <?php echo esc_html($ga4MeasurementId); ?></p>
                </div>
                <?php else: ?>
                <div class="notice notice-warning inline">
                    <p><strong><?php esc_html_e('GA4 Not Connected', 'ai-seo-client'); ?></strong></p>
                    <p><?php esc_html_e('Connect Google Analytics 4 to track content performance.', 'ai-seo-client'); ?></p>
                </div>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=ai-seo-performance&tab=settings'); ?>" class="button">
                    <?php esc_html_e('Configure GA4', 'ai-seo-client'); ?>
                </a>
            </div>
            
            <!-- Performance Overview -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Performance Overview (Last 30 Days)', 'ai-seo-client'); ?></h2>
                
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 15px;">
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html(number_format($performanceData['total_traffic'] ?? 0)); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Total Traffic', 'ai-seo-client'); ?>
                        </p>
                        <small style="color: <?php echo ($performanceData['traffic_change'] ?? 0) >= 0 ? '#00a32a' : '#d63638'; ?>;">
                            <?php 
                            $change = $performanceData['traffic_change'] ?? 0;
                            echo ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
                            ?>
                        </small>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html(number_format($performanceData['conversions'] ?? 0)); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Conversions', 'ai-seo-client'); ?>
                        </p>
                        <small style="color: <?php echo ($performanceData['conversion_change'] ?? 0) >= 0 ? '#00a32a' : '#d63638'; ?>;">
                            <?php 
                            $change = $performanceData['conversion_change'] ?? 0;
                            echo ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
                            ?>
                        </small>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            $<?php echo esc_html(number_format($performanceData['estimated_roi'] ?? 0)); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Estimated ROI', 'ai-seo-client'); ?>
                        </p>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                        <h3 style="font-size: 36px; margin: 0; color: #2271b1;">
                            <?php echo esc_html($performanceData['avg_time_to_rank'] ?? 'N/A'); ?>
                        </h3>
                        <p style="margin: 5px 0 0; color: #666;">
                            <?php esc_html_e('Avg Time-to-Rank', 'ai-seo-client'); ?>
                        </p>
                        <small><?php esc_html_e('days', 'ai-seo-client'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Top Performing Content -->
            <div class="card" style="margin-bottom: 20px;">
                <h2><?php esc_html_e('Top Performing Content', 'ai-seo-client'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Content', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Traffic', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Conversions', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('ROI', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Optimization Impact', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $topContent = $this->getTopPerformingContent(10);
                        if (empty($topContent)): 
                        ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No performance data available yet. Connect GA4 to start tracking.', 'ai-seo-client'); ?></td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($topContent as $content): ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="<?php echo get_edit_post_link($content['post_id']); ?>">
                                        <?php echo esc_html($content['title']); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html(number_format($content['traffic'])); ?></td>
                            <td><?php echo esc_html(number_format($content['conversions'])); ?></td>
                            <td>$<?php echo esc_html(number_format($content['roi'], 2)); ?></td>
                            <td>
                                <?php if ($content['optimization_impact'] > 0): ?>
                                <span style="color: #00a32a;">
                                    +<?php echo esc_html(number_format($content['optimization_impact'], 1)); ?>%
                                </span>
                                <?php else: ?>
                                <span style="color: #666;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoViewPerformanceDetails(<?php echo $content['post_id']; ?>)">
                                    <?php esc_html_e('View Details', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Active A/B Tests -->
            <div class="card">
                <h2><?php esc_html_e('Active A/B Tests', 'ai-seo-client'); ?></h2>
                
                <?php $activeTests = $this->getActiveABTests(); ?>
                
                <?php if (empty($activeTests)): ?>
                <p><?php esc_html_e('No active A/B tests. Create a test to optimize your titles and meta descriptions.', 'ai-seo-client'); ?></p>
                <button type="button" class="button button-primary" onclick="sseoCreateABTest()">
                    <?php esc_html_e('Create A/B Test', 'ai-seo-client'); ?>
                </button>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Test Name', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Post', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Variant A CTR', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Variant B CTR', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Winner', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeTests as $test): ?>
                        <tr>
                            <td><strong><?php echo esc_html($test['name']); ?></strong></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($test['post_id']); ?>">
                                    <?php echo esc_html(get_the_title($test['post_id'])); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(number_format($test['variant_a_ctr'], 2)); ?>%</td>
                            <td><?php echo esc_html(number_format($test['variant_b_ctr'], 2)); ?>%</td>
                            <td>
                                <?php 
                                if ($test['winner']) {
                                    echo '<strong style="color: #00a32a;">' . esc_html($test['winner']) . '</strong>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(ucfirst($test['status'])); ?></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="sseoStopABTest(<?php echo $test['id']; ?>)">
                                    <?php esc_html_e('Stop Test', 'ai-seo-client'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        function sseoViewPerformanceDetails(postId) {
            window.location.href = '<?php echo admin_url('post.php?action=edit&post='); ?>' + postId + '#performance-details';
        }
        
        function sseoCreateABTest() {
            // Open A/B test creation modal
            alert('<?php esc_html_e('A/B test creation coming soon', 'ai-seo-client'); ?>');
        }
        
        function sseoStopABTest(testId) {
            if (!confirm('<?php esc_html_e('Stop this A/B test and apply the winning variant?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_stop_ab_test',
                test_id: testId,
                nonce: '<?php echo wp_create_nonce('sseo_performance'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error stopping test');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Add performance meta box
     */
    public function addMetaBox(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'sseo-performance',
                __('Performance Metrics', 'ai-seo-client'),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render performance meta box
     */
    public function renderMetaBox(\WP_Post $post): void
    {
        $metrics = $this->getPostPerformance($post->ID);
        $baseline = get_post_meta($post->ID, '_sseo_ai_performance_baseline', true);
        $optimizationDate = get_post_meta($post->ID, '_sseo_ai_last_optimization', true);
        
        ?>
        <div class="sseo-performance-box">
            <style>
                .sseo-metric { margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #2271b1; }
                .sseo-metric-value { font-size: 24px; font-weight: bold; color: #2271b1; }
                .sseo-metric-label { font-size: 12px; color: #666; text-transform: uppercase; }
                .sseo-metric-change { font-size: 14px; margin-top: 5px; }
            </style>
            
            <div class="sseo-metric">
                <div class="sseo-metric-label"><?php esc_html_e('Traffic (30 days)', 'ai-seo-client'); ?></div>
                <div class="sseo-metric-value"><?php echo esc_html(number_format($metrics['traffic'] ?? 0)); ?></div>
                <?php if (isset($metrics['traffic_change'])): ?>
                <div class="sseo-metric-change" style="color: <?php echo $metrics['traffic_change'] >= 0 ? '#00a32a' : '#d63638'; ?>;">
                    <?php echo ($metrics['traffic_change'] >= 0 ? '↑' : '↓'); ?>
                    <?php echo esc_html(abs($metrics['traffic_change'])); ?>%
                </div>
                <?php endif; ?>
            </div>
            
            <div class="sseo-metric">
                <div class="sseo-metric-label"><?php esc_html_e('Conversions', 'ai-seo-client'); ?></div>
                <div class="sseo-metric-value"><?php echo esc_html(number_format($metrics['conversions'] ?? 0)); ?></div>
            </div>
            
            <div class="sseo-metric">
                <div class="sseo-metric-label"><?php esc_html_e('Avg. Position', 'ai-seo-client'); ?></div>
                <div class="sseo-metric-value"><?php echo esc_html(number_format($metrics['avg_position'] ?? 0, 1)); ?></div>
            </div>
            
            <?php if ($optimizationDate): ?>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p style="margin: 0; font-size: 12px; color: #666;">
                    <?php esc_html_e('Last optimized:', 'ai-seo-client'); ?>
                    <?php echo esc_html(human_time_diff(strtotime($optimizationDate))); ?> ago
                </p>
                
                <?php if ($baseline): ?>
                <p style="margin: 5px 0 0; font-size: 12px;">
                    <strong><?php esc_html_e('Optimization Impact:', 'ai-seo-client'); ?></strong>
                    <?php 
                    $impact = $this->calculateOptimizationImpact($post->ID, $baseline, $metrics);
                    $color = $impact >= 0 ? '#00a32a' : '#d63638';
                    ?>
                    <span style="color: <?php echo $color; ?>;">
                        <?php echo ($impact >= 0 ? '+' : '') . number_format($impact, 1); ?>%
                    </span>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 15px;">
                <button type="button" class="button button-small button-primary" style="width: 100%;" 
                        onclick="sseoSetPerformanceBaseline(<?php echo $post->ID; ?>)">
                    <?php esc_html_e('Set Current as Baseline', 'ai-seo-client'); ?>
                </button>
            </div>
        </div>
        
        <script>
        function sseoSetPerformanceBaseline(postId) {
            if (!confirm('<?php esc_html_e('Set current metrics as baseline for future comparisons?', 'ai-seo-client'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_set_baseline',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('sseo_performance'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Baseline set successfully!', 'ai-seo-client'); ?>');
                    location.reload();
                } else {
                    alert(response.data.message || 'Error setting baseline');
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save performance meta box
     */
    public function saveMetaBox(int $postId, \WP_Post $post): void
    {
        // Performance data is tracked automatically via GA4
        // This is just a placeholder for future custom tracking
    }
    
    /**
     * Get performance overview
     */
    private function getPerformanceOverview(): array
    {
        $ga4Data = $this->getGA4Data('overview', 30);
        
        return [
            'total_traffic' => $ga4Data['total_traffic'] ?? 0,
            'traffic_change' => $ga4Data['traffic_change'] ?? 0,
            'conversions' => $ga4Data['conversions'] ?? 0,
            'conversion_change' => $ga4Data['conversion_change'] ?? 0,
            'estimated_roi' => $this->calculateEstimatedROI($ga4Data),
            'avg_time_to_rank' => $this->getAverageTimeToRank(),
        ];
    }
    
    /**
     * Get GA4 data via real GA4Client
     */
    private function getGA4Data(string $type, int $days = 30): array
    {
        if (!$this->ga4Client || !$this->ga4Client->isConnected()) {
            return [];
        }

        $overview = $this->ga4Client->getOverview($days);
        if (is_wp_error($overview)) {
            return [];
        }

        return [
            'total_traffic' => $overview['sessions'] ?? 0,
            'traffic_change' => 0,
            'conversions' => $overview['conversions'] ?? 0,
            'conversion_change' => 0,
        ];
    }
    
    /**
     * Calculate estimated ROI
     */
    private function calculateEstimatedROI(array $ga4Data): float
    {
        $conversions = $ga4Data['conversions'] ?? 0;
        $avgConversionValue = get_option('sseo_ai_avg_conversion_value', 100);
        
        return $conversions * $avgConversionValue;
    }
    
    /**
     * Get average time to rank
     */
    private function getAverageTimeToRank(): string
    {
        global $wpdb;
        
        // Check if rank_history table exists with post_id column
        $tableName = $wpdb->prefix . 'sseo_ai_rank_history';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") === $tableName;
        if (!$tableExists) {
            return 'N/A';
        }
        
        $columnExists = $wpdb->get_results("SHOW COLUMNS FROM {$tableName} LIKE 'post_id'");
        if (empty($columnExists)) {
            return 'N/A';
        }
        
        $results = $wpdb->get_results("
            SELECT p.ID as post_id, p.post_date, 
                   (SELECT MIN(created_at) FROM {$wpdb->prefix}sseo_ai_rank_history 
                    WHERE post_id = p.ID AND position <= 10) as first_rank_date
            FROM {$wpdb->posts} p
            WHERE post_status = 'publish'
            AND post_type = 'post'
            AND post_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
            HAVING first_rank_date IS NOT NULL
        ");
        
        if (empty($results)) {
            return 'N/A';
        }
        
        $totalDays = 0;
        foreach ($results as $row) {
            $publishDate = strtotime($row->post_date);
            $rankDate = strtotime($row->first_rank_date);
            $totalDays += ($rankDate - $publishDate) / DAY_IN_SECONDS;
        }
        
        $resultCount = count($results);
        $avgDays = $resultCount > 0 ? round($totalDays / $resultCount) : 0;
        return (string)$avgDays;
    }
    
    /**
     * Get top performing content via GA4 API
     */
    private function getTopPerformingContent(int $limit = 10): array
    {
        if (!$this->ga4Client || !$this->ga4Client->isConnected()) {
            return [];
        }

        $result = $this->ga4Client->getTopPages(30, $limit);
        if (is_wp_error($result)) {
            return [];
        }

        return $result['pages'] ?? [];
    }
    
    /**
     * Get post performance
     */
    private function getPostPerformance(int $postId): array
    {
        $url = get_permalink($postId);
        
        // Query GA4 for this specific URL
        $ga4Data = $this->getGA4DataForUrl($url, 30);
        
        return [
            'traffic' => $ga4Data['pageviews'] ?? 0,
            'traffic_change' => $ga4Data['pageviews_change'] ?? 0,
            'conversions' => $ga4Data['conversions'] ?? 0,
            'avg_position' => $this->getAvgPosition($postId),
        ];
    }
    
    /**
     * Get GA4 data for specific URL via GA4 API
     */
    private function getGA4DataForUrl(string $url, int $days): array
    {
        if (!$this->ga4Client || !$this->ga4Client->isConnected()) {
            return [
                'pageviews' => 0,
                'pageviews_change' => 0,
                'conversions' => 0,
            ];
        }

        $path = wp_parse_url($url, PHP_URL_PATH) ?: '/';
        $result = $this->ga4Client->getPageData($path, $days);
        if (is_wp_error($result)) {
            return [
                'pageviews' => 0,
                'pageviews_change' => 0,
                'conversions' => 0,
            ];
        }

        return [
            'pageviews' => $result['page_views'] ?? 0,
            'pageviews_change' => 0,
            'conversions' => $result['conversions'] ?? 0,
        ];
    }
    
    /**
     * Get average position for post
     */
    private function getAvgPosition(int $postId): float
    {
        global $wpdb;
        
        // Check if rank_history table exists with post_id column
        $tableName = $wpdb->prefix . 'sseo_ai_rank_history';
        $tableExists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") === $tableName;
        if (!$tableExists) {
            return 0;
        }
        
        $columnExists = $wpdb->get_results("SHOW COLUMNS FROM {$tableName} LIKE 'post_id'");
        if (empty($columnExists)) {
            return 0;
        }
        
        $avgPos = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(position)
            FROM {$wpdb->prefix}sseo_ai_rank_history
            WHERE post_id = %d
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $postId));
        
        return $avgPos ? (float)$avgPos : 0;
    }
    
    /**
     * Calculate optimization impact
     */
    private function calculateOptimizationImpact(int $postId, array $baseline, array $current): float
    {
        $baselineTraffic = $baseline['traffic'] ?? 0;
        $currentTraffic = $current['traffic'] ?? 0;
        
        if ($baselineTraffic == 0) {
            return 0;
        }
        
        return (($currentTraffic - $baselineTraffic) / $baselineTraffic) * 100;
    }
    
    /**
     * Record optimization event
     */
    public function recordOptimization(int $postId, array $changes): void
    {
        // Get current performance as baseline
        $currentMetrics = $this->getPostPerformance($postId);
        
        update_post_meta($postId, '_sseo_ai_performance_baseline', $currentMetrics);
        update_post_meta($postId, '_sseo_ai_last_optimization', current_time('mysql'));
        update_post_meta($postId, '_sseo_ai_optimization_changes', $changes);
    }
    
    /**
     * Get active A/B tests
     */
    private function getActiveABTests(): array
    {
        return get_option('sseo_ai_active_ab_tests', []);
    }
    
    /**
     * Check performance (scheduled task)
     */
    public function checkPerformance(): void
    {
        // Check all posts with optimizations
        $posts = get_posts([
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sseo_ai_last_optimization',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);
        
        foreach ($posts as $post) {
            $this->updatePostPerformance($post->ID);
        }
    }
    
    /**
     * Update post performance data
     */
    private function updatePostPerformance(int $postId): void
    {
        $metrics = $this->getPostPerformance($postId);
        update_post_meta($postId, '_sseo_ai_current_performance', $metrics);
        update_post_meta($postId, '_sseo_ai_performance_updated', current_time('mysql'));
    }
    
    /**
     * Track conversion (AJAX)
     */
    public function ajaxTrackConversion(): void
    {
        check_ajax_referer('sseo_performance', 'nonce');
        
        $postId = (int)($_POST['post_id'] ?? 0);
        $conversionType = sanitize_text_field($_POST['conversion_type'] ?? 'generic');
        $value = (float)($_POST['value'] ?? 0);
        
        if (!$postId) {
            wp_send_json_error(['message' => 'Post ID required']);
        }
        
        // Record conversion
        $conversions = get_post_meta($postId, '_sseo_ai_conversions', true) ?: [];
        $conversions[] = [
            'type' => $conversionType,
            'value' => $value,
            'timestamp' => current_time('mysql'),
        ];
        update_post_meta($postId, '_sseo_ai_conversions', $conversions);
        
        wp_send_json_success(['message' => 'Conversion tracked']);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/performance/post/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPostPerformance'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/performance/baseline', [
            'methods' => 'POST',
            'callback' => [$this, 'restSetBaseline'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
        
        register_rest_route('sseo-ai/v1', '/performance/ab-test', [
            'methods' => 'POST',
            'callback' => [$this, 'restCreateABTest'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }
    
    /**
     * REST: Get post performance
     */
    public function restGetPostPerformance(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('id');
        $metrics = $this->getPostPerformance($postId);
        
        return ['metrics' => $metrics];
    }
    
    /**
     * REST: Set performance baseline
     */
    public function restSetBaseline(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $metrics = $this->getPostPerformance($postId);
        
        update_post_meta($postId, '_sseo_ai_performance_baseline', $metrics);
        update_post_meta($postId, '_sseo_ai_baseline_set_at', current_time('mysql'));
        
        return ['success' => true, 'baseline' => $metrics];
    }
    
    /**
     * REST: Create A/B test
     */
    public function restCreateABTest(\WP_REST_Request $request): array
    {
        $postId = (int)$request->get_param('post_id');
        $variantA = $request->get_param('variant_a');
        $variantB = $request->get_param('variant_b');
        $duration = (int)$request->get_param('duration') ?: 14;
        
        $test = [
            'id' => uniqid('test_'),
            'post_id' => $postId,
            'name' => 'Title/Meta Test',
            'variant_a' => $variantA,
            'variant_b' => $variantB,
            'variant_a_ctr' => 0,
            'variant_b_ctr' => 0,
            'status' => 'active',
            'started_at' => current_time('mysql'),
            'ends_at' => date('Y-m-d H:i:s', strtotime("+{$duration} days")),
            'winner' => null,
        ];
        
        $activeTests = get_option('sseo_ai_active_ab_tests', []);
        $activeTests[] = $test;
        update_option('sseo_ai_active_ab_tests', $activeTests);
        
        return ['success' => true, 'test' => $test];
    }
}

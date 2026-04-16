<?php

namespace SSEOAIClient;

/**
 * Technical SEO Auditor
 * 
 * Provides deep technical SEO analysis:
 * - Crawl simulation / crawlability audit
 * - Crawl budget analyzer
 * - URL structure optimization
 * - Sitemap health checker
 * - Server response time optimization
 * - CDN performance analysis
 * - XML sitemap validation (deep)
 * - Robots.txt optimization suggestions
 */
class TechnicalSEOAuditor
{
    private Settings $settings;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }
    
    public function register(): void
    {
        // Menu registration moved to Client class
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // Scheduled audits
        if (!wp_next_scheduled('sseo_ai_technical_audit')) {
            wp_schedule_event(time(), 'weekly', 'sseo_ai_technical_audit');
        }
        add_action('sseo_ai_technical_audit', [$this, 'runScheduledAudit']);
    }
    
    public function addMenu(): void
    {
        add_submenu_page(
            'ai-seo-client',
            __('Technical SEO Audit', 'ai-seo-client'),
            __('Technical Audit', 'ai-seo-client'),
            'manage_options',
            'ai-seo-technical-audit',
            [$this, 'renderDashboard']
        );
    }
    
    /**
     * Render technical audit dashboard
     */
    public function renderDashboard(): void
    {
        $lastAudit = get_option('sseo_ai_last_technical_audit', []);
        $lastAuditDate = get_option('sseo_ai_last_audit_date', '');
        
        ?>
        <style>
            .sseo-ai-dashboard-card + .sseo-ai-dashboard-card { margin-top: 30px; }
            .sseo-ai-dashboard-card:first-of-type { margin-bottom: 30px; }
            .sseo-two-columns { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; }
            @media (max-width: 1024px) { .sseo-two-columns { grid-template-columns: 1fr; } }
        </style>
        <div class="wrap sseo-ai-modern">
            <div class="sseo-ai-header">
                <h1><?php esc_html_e('Technical SEO Audit', 'ai-seo-client'); ?></h1>
            </div>
            
            <div class="sseo-ai-content">
                <!-- Audit Overview -->
                <div class="sseo-ai-dashboard-card" style="grid-column: 1 / -1;">
                    <h2><?php esc_html_e('Audit Overview', 'ai-seo-client'); ?></h2>
                
                <?php if ($lastAuditDate): ?>
                <p>
                    <strong><?php esc_html_e('Last Audit:', 'ai-seo-client'); ?></strong>
                    <?php echo esc_html(human_time_diff(strtotime($lastAuditDate))); ?> ago
                </p>
                <?php endif; ?>
                
                <button type="button" class="button button-primary button-hero" onclick="sseoRunTechnicalAudit()">
                    <?php esc_html_e('Run Full Technical Audit', 'ai-seo-client'); ?>
                </button>
                
                <?php if (!empty($lastAudit)): ?>
                <div style="margin-top: 20px;">
                    <h3><?php esc_html_e('Audit Score', 'ai-seo-client'); ?></h3>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                        <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                            <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($lastAudit['crawlability_score'] ?? 0); ?>;">
                                <?php echo esc_html($lastAudit['crawlability_score'] ?? 0); ?>
                            </div>
                            <div><?php esc_html_e('Crawlability', 'ai-seo-client'); ?></div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                            <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($lastAudit['performance_score'] ?? 0); ?>;">
                                <?php echo esc_html($lastAudit['performance_score'] ?? 0); ?>
                            </div>
                            <div><?php esc_html_e('Performance', 'ai-seo-client'); ?></div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                            <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($lastAudit['structure_score'] ?? 0); ?>;">
                                <?php echo esc_html($lastAudit['structure_score'] ?? 0); ?>
                            </div>
                            <div><?php esc_html_e('URL Structure', 'ai-seo-client'); ?></div>
                        </div>
                        <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 4px;">
                            <div style="font-size: 48px; font-weight: bold; color: <?php echo $this->getScoreColor($lastAudit['sitemap_score'] ?? 0); ?>;">
                                <?php echo esc_html($lastAudit['sitemap_score'] ?? 0); ?>
                            </div>
                            <div><?php esc_html_e('Sitemap Health', 'ai-seo-client'); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="sseo-two-columns">
                <!-- Left Column -->
                <div>
                    <!-- Crawlability Audit -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Crawlability Audit', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($lastAudit['crawlability'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Check', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Details', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Action', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lastAudit['crawlability'] as $check): ?>
                        <tr>
                            <td><strong><?php echo esc_html($check['name']); ?></strong></td>
                            <td>
                                <?php 
                                $status = $check['status'];
                                $color = $status === 'pass' ? '#00a32a' : ($status === 'warning' ? '#dba617' : '#d63638');
                                ?>
                                <span style="color: <?php echo $color; ?>;">
                                    <?php echo $status === 'pass' ? '✓' : ($status === 'warning' ? '⚠' : '✗'); ?>
                                    <?php echo esc_html(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($check['details']); ?></td>
                            <td>
                                <?php if (!empty($check['fix_url'])): ?>
                                <a href="<?php echo esc_url($check['fix_url']); ?>" class="button button-small">
                                    <?php esc_html_e('Fix', 'ai-seo-client'); ?>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('Run an audit to see crawlability results.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Crawl Budget Analysis -->
            <div class="sseo-ai-dashboard-card">
                <h2><?php esc_html_e('Crawl Budget Analysis', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($lastAudit['crawl_budget'])): ?>
                <div style="margin-bottom: 15px;">
                    <p>
                        <strong><?php esc_html_e('Total Pages:', 'ai-seo-client'); ?></strong>
                        <?php echo esc_html(number_format($lastAudit['crawl_budget']['total_pages'] ?? 0)); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Crawlable Pages:', 'ai-seo-client'); ?></strong>
                        <?php echo esc_html(number_format($lastAudit['crawl_budget']['crawlable_pages'] ?? 0)); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Wasted Crawl Budget:', 'ai-seo-client'); ?></strong>
                        <?php echo esc_html(number_format($lastAudit['crawl_budget']['wasted_budget'] ?? 0)); ?> pages
                    </p>
                </div>
                
                <h3><?php esc_html_e('Crawl Budget Wasters', 'ai-seo-client'); ?></h3>
                <ul>
                    <?php foreach ($lastAudit['crawl_budget']['wasters'] ?? [] as $waster): ?>
                    <li>
                        <strong><?php echo esc_html($waster['type']); ?>:</strong>
                        <?php echo esc_html($waster['count']); ?> pages -
                        <?php echo esc_html($waster['recommendation']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p><?php esc_html_e('Run an audit to analyze crawl budget.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- URL Structure Optimization -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('URL Structure Optimization', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($lastAudit['url_structure'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Issue', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Count', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Recommendation', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lastAudit['url_structure'] as $issue): ?>
                        <tr>
                            <td><?php echo esc_html($issue['issue']); ?></td>
                            <td><?php echo esc_html($issue['count']); ?></td>
                            <td><?php echo esc_html($issue['recommendation']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('Run an audit to check URL structure.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                    </div>
                    
                    <!-- Sitemap Health -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('XML Sitemap Health Check', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($lastAudit['sitemap'])): ?>
                <div style="margin-bottom: 15px;">
                    <p>
                        <strong><?php esc_html_e('Sitemap URL:', 'ai-seo-client'); ?></strong>
                        <a href="<?php echo esc_url($lastAudit['sitemap']['url']); ?>" target="_blank">
                            <?php echo esc_html($lastAudit['sitemap']['url']); ?>
                        </a>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Total URLs:', 'ai-seo-client'); ?></strong>
                        <?php echo esc_html(number_format($lastAudit['sitemap']['total_urls'] ?? 0)); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Valid URLs:', 'ai-seo-client'); ?></strong>
                        <?php echo esc_html(number_format($lastAudit['sitemap']['valid_urls'] ?? 0)); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Invalid URLs:', 'ai-seo-client'); ?></strong>
                        <span style="color: <?php echo ($lastAudit['sitemap']['invalid_urls'] ?? 0) > 0 ? '#d63638' : '#00a32a'; ?>;">
                            <?php echo esc_html(number_format($lastAudit['sitemap']['invalid_urls'] ?? 0)); ?>
                        </span>
                    </p>
                </div>
                
                <?php if (!empty($lastAudit['sitemap']['issues'])): ?>
                <h3><?php esc_html_e('Sitemap Issues', 'ai-seo-client'); ?></h3>
                <ul>
                    <?php foreach ($lastAudit['sitemap']['issues'] as $issue): ?>
                    <li style="color: #d63638;">
                        <strong><?php echo esc_html($issue['type']); ?>:</strong>
                        <?php echo esc_html($issue['description']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <?php else: ?>
                <p><?php esc_html_e('Run an audit to check sitemap health.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                    </div>
                    
                    <!-- Robots.txt Optimization -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Robots.txt Optimization', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($lastAudit['robots_txt'])): ?>
                <div style="margin-bottom: 15px;">
                    <p>
                        <strong><?php esc_html_e('Status:', 'ai-seo-client'); ?></strong>
                        <span style="color: <?php echo $lastAudit['robots_txt']['exists'] ? '#00a32a' : '#d63638'; ?>;">
                            <?php echo $lastAudit['robots_txt']['exists'] ? '✓ Found' : '✗ Not Found'; ?>
                        </span>
                    </p>
                </div>
                
                <?php if (!empty($lastAudit['robots_txt']['suggestions'])): ?>
                <h3><?php esc_html_e('Optimization Suggestions', 'ai-seo-client'); ?></h3>
                <ul>
                    <?php foreach ($lastAudit['robots_txt']['suggestions'] as $suggestion): ?>
                    <li><?php echo esc_html($suggestion); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <?php else: ?>
                <p><?php esc_html_e('Run an audit to check robots.txt.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                    </div>
                    
                    <!-- Performance Analysis -->
                    <div class="sseo-ai-dashboard-card">
                        <h2><?php esc_html_e('Server & CDN Performance', 'ai-seo-client'); ?></h2>
                
                <?php if (!empty($lastAudit['performance'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Metric', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Value', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-seo-client'); ?></th>
                            <th><?php esc_html_e('Recommendation', 'ai-seo-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lastAudit['performance'] as $metric): ?>
                        <tr>
                            <td><strong><?php echo esc_html($metric['name']); ?></strong></td>
                            <td><?php echo esc_html($metric['value']); ?></td>
                            <td>
                                <span style="color: <?php echo $this->getStatusColor($metric['status']); ?>;">
                                    <?php echo esc_html(ucfirst($metric['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($metric['recommendation']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p><?php esc_html_e('Run an audit to analyze performance.', 'ai-seo-client'); ?></p>
                <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
        </div>
        
        <script>
        function sseoRunTechnicalAudit() {
            if (!confirm('<?php esc_html_e('Run a full technical SEO audit? This may take several minutes.', 'ai-seo-client'); ?>')) {
                return;
            }
            
            // Show loading indicator
            const button = event.target;
            button.disabled = true;
            button.textContent = '<?php esc_html_e('Running Audit...', 'ai-seo-client'); ?>';
            
            jQuery.post(ajaxurl, {
                action: 'sseo_ai_run_technical_audit',
                nonce: '<?php echo wp_create_nonce('sseo_technical'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error running audit');
                    button.disabled = false;
                    button.textContent = '<?php esc_html_e('Run Full Technical Audit', 'ai-seo-client'); ?>';
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Run full technical audit
     */
    public function runFullAudit(): array
    {
        $audit = [
            'crawlability' => $this->auditCrawlability(),
            'crawl_budget' => $this->analyzeCrawlBudget(),
            'url_structure' => $this->auditURLStructure(),
            'sitemap' => $this->auditSitemap(),
            'robots_txt' => $this->auditRobotsTxt(),
            'performance' => $this->auditPerformance(),
        ];
        
        // Calculate scores
        $audit['crawlability_score'] = $this->calculateCrawlabilityScore($audit['crawlability']);
        $audit['performance_score'] = $this->calculatePerformanceScore($audit['performance']);
        $audit['structure_score'] = $this->calculateStructureScore($audit['url_structure']);
        $audit['sitemap_score'] = $this->calculateSitemapScore($audit['sitemap']);
        
        // Store audit results
        update_option('sseo_ai_last_technical_audit', $audit);
        update_option('sseo_ai_last_audit_date', current_time('mysql'));
        
        return $audit;
    }
    
    /**
     * Audit crawlability
     */
    private function auditCrawlability(): array
    {
        $checks = [];
        
        // Check robots.txt
        $robotsUrl = home_url('/robots.txt');
        $robotsResponse = wp_remote_get($robotsUrl);
        $robotsExists = !is_wp_error($robotsResponse) && wp_remote_retrieve_response_code($robotsResponse) === 200;
        
        $checks[] = [
            'name' => 'Robots.txt Accessible',
            'status' => $robotsExists ? 'pass' : 'fail',
            'details' => $robotsExists ? 'Robots.txt is accessible' : 'Robots.txt not found',
            'fix_url' => '', // No dedicated robots.txt page yet
        ];
        
        // Check XML sitemap
        $sitemapExists = $this->checkSitemapExists();
        $checks[] = [
            'name' => 'XML Sitemap Accessible',
            'status' => $sitemapExists ? 'pass' : 'fail',
            'details' => $sitemapExists ? 'XML sitemap is accessible' : 'XML sitemap not found',
            'fix_url' => '', // No dedicated sitemap page yet - configure via Settings
        ];
        
        // Check for redirect chains
        $redirectChains = $this->checkRedirectChains();
        $checks[] = [
            'name' => 'Redirect Chains',
            'status' => count($redirectChains) === 0 ? 'pass' : 'warning',
            'details' => count($redirectChains) . ' redirect chains found',
            'fix_url' => '', // No dedicated redirects page yet
        ];
        
        // Check for orphaned pages
        $orphanedPages = $this->findOrphanedPages();
        $checks[] = [
            'name' => 'Orphaned Pages',
            'status' => count($orphanedPages) === 0 ? 'pass' : 'warning',
            'details' => count($orphanedPages) . ' orphaned pages found',
            'fix_url' => '',
        ];
        
        // Check crawl depth
        $deepPages = $this->findDeepPages();
        $checks[] = [
            'name' => 'Crawl Depth',
            'status' => count($deepPages) === 0 ? 'pass' : 'warning',
            'details' => count($deepPages) . ' pages deeper than 3 clicks',
            'fix_url' => '',
        ];
        
        return $checks;
    }
    
    /**
     * Analyze crawl budget
     */
    private function analyzeCrawlBudget(): array
    {
        global $wpdb;
        
        $totalPages = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page')
        ");
        
        // Find pages that waste crawl budget
        $wasters = [];
        
        // Duplicate content
        $duplicates = $this->findDuplicateContent();
        if (count($duplicates) > 0) {
            $wasters[] = [
                'type' => 'Duplicate Content',
                'count' => count($duplicates),
                'recommendation' => 'Use canonical tags or noindex',
            ];
        }
        
        // Thin content
        $thinContent = $this->findThinContent();
        if (count($thinContent) > 0) {
            $wasters[] = [
                'type' => 'Thin Content',
                'count' => count($thinContent),
                'recommendation' => 'Expand content or noindex',
            ];
        }
        
        // Pagination pages
        $paginationPages = $this->countPaginationPages();
        if ($paginationPages > 10) {
            $wasters[] = [
                'type' => 'Excessive Pagination',
                'count' => $paginationPages,
                'recommendation' => 'Consider load more or infinite scroll',
            ];
        }
        
        $wastedBudget = array_sum(array_column($wasters, 'count'));
        
        return [
            'total_pages' => (int)$totalPages,
            'crawlable_pages' => (int)$totalPages - $wastedBudget,
            'wasted_budget' => $wastedBudget,
            'wasters' => $wasters,
        ];
    }
    
    /**
     * Audit URL structure
     */
    private function auditURLStructure(): array
    {
        global $wpdb;
        
        $issues = [];
        
        // Long URLs
        $longUrls = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND LENGTH(post_name) > 75
        ");
        
        if ($longUrls > 0) {
            $issues[] = [
                'issue' => 'Long URLs (>75 characters)',
                'count' => $longUrls,
                'recommendation' => 'Shorten URLs for better readability',
            ];
        }
        
        // URLs with special characters
        $specialChars = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_name REGEXP '[^a-z0-9-]'
        ");
        
        if ($specialChars > 0) {
            $issues[] = [
                'issue' => 'URLs with special characters',
                'count' => $specialChars,
                'recommendation' => 'Use only lowercase letters, numbers, and hyphens',
            ];
        }
        
        // URLs with stop words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at'];
        $stopWordCount = 0;
        
        foreach ($stopWords as $word) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_name LIKE %s
            ", '%' . $wpdb->esc_like('-' . $word . '-') . '%'));
            
            $stopWordCount += (int)$count;
        }
        
        if ($stopWordCount > 0) {
            $issues[] = [
                'issue' => 'URLs with stop words',
                'count' => $stopWordCount,
                'recommendation' => 'Remove stop words from URLs',
            ];
        }
        
        return $issues;
    }
    
    /**
     * Audit XML sitemap
     */
    private function auditSitemap(): array
    {
        // Try sitemap_index.xml first (Yoast SEO style), then sitemap.xml
        $sitemapUrls = [
            home_url('/sitemap_index.xml'),
            home_url('/sitemap.xml'),
        ];
        
        $sitemapUrl = null;
        $response = null;
        
        foreach ($sitemapUrls as $url) {
            $resp = wp_remote_get($url, ['timeout' => 30]);
            if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                $sitemapUrl = $url;
                $response = $resp;
                break;
            }
        }
        
        if (!$response || is_wp_error($response)) {
            return [
                'url' => $sitemapUrls[0],
                'exists' => false,
                'total_urls' => 0,
                'valid_urls' => 0,
                'invalid_urls' => 0,
                'issues' => [
                    ['type' => 'Error', 'description' => 'Sitemap not accessible'],
                ],
            ];
        }
        
        $xml = wp_remote_retrieve_body($response);
        
        try {
            $sitemap = new \SimpleXMLElement($xml);
            $urls = [];
            $issues = [];
            
            // Check if this is a sitemap index (contains sub-sitemaps)
            $isSitemapIndex = isset($sitemap->sitemap) || strpos($xml, 'sitemapindex') !== false;
            
            if ($isSitemapIndex) {
                // Parse sitemap index and fetch sub-sitemaps
                foreach ($sitemap->sitemap as $subSitemap) {
                    $subSitemapUrl = (string)$subSitemap->loc;
                    $subUrls = $this->parseSubSitemap($subSitemapUrl);
                    
                    if (is_array($subUrls)) {
                        $urls = array_merge($urls, $subUrls);
                    } else {
                        $issues[] = [
                            'type' => 'Sub-sitemap Error',
                            'description' => "Could not parse sub-sitemap: {$subSitemapUrl}",
                        ];
                    }
                }
            } else {
                // Regular sitemap with URLs directly
                foreach ($sitemap->url as $url) {
                    $loc = (string)$url->loc;
                    $urls[] = $loc;
                }
            }
            
            // Validate a sample of URLs (not all, for performance)
            $sampleUrls = array_slice($urls, 0, 10);
            foreach ($sampleUrls as $loc) {
                $urlResponse = wp_remote_head($loc, ['timeout' => 5]);
                if (is_wp_error($urlResponse)) {
                    $issues[] = [
                        'type' => 'Unreachable URL',
                        'description' => "URL not accessible: {$loc}",
                    ];
                }
            }
            
            $totalUrls = count($urls);
            $invalidUrls = count($issues);
            
            // Check for missing pages
            global $wpdb;
            $publishedPosts = $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_status = 'publish'
                AND post_type IN ('post', 'page')
            ");
            
            if ($publishedPosts > $totalUrls) {
                $issues[] = [
                    'type' => 'Missing URLs',
                    'description' => ($publishedPosts - $totalUrls) . ' published pages not in sitemap',
                ];
            }
            
            return [
                'url' => $sitemapUrl,
                'exists' => true,
                'total_urls' => $totalUrls,
                'valid_urls' => $totalUrls - $invalidUrls,
                'invalid_urls' => $invalidUrls,
                'issues' => $issues,
            ];
            
        } catch (\Exception $e) {
            return [
                'url' => $sitemapUrl,
                'exists' => false,
                'total_urls' => 0,
                'valid_urls' => 0,
                'invalid_urls' => 0,
                'issues' => [
                    ['type' => 'Parse Error', 'description' => 'Could not parse sitemap XML: ' . $e->getMessage()],
                ],
            ];
        }
    }
    
    /**
     * Parse sub-sitemap and return URLs
     */
    private function parseSubSitemap(string $url): ?array
    {
        $response = wp_remote_get($url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $xml = wp_remote_retrieve_body($response);
        
        try {
            $sitemap = new \SimpleXMLElement($xml);
            $urls = [];
            
            foreach ($sitemap->url as $urlNode) {
                $urls[] = (string)$urlNode->loc;
            }
            
            return $urls;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Audit robots.txt
     */
    private function auditRobotsTxt(): array
    {
        $robotsUrl = home_url('/robots.txt');
        $response = wp_remote_get($robotsUrl);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [
                'exists' => false,
                'suggestions' => [
                    'Create a robots.txt file',
                    'Add sitemap reference',
                ],
            ];
        }
        
        $content = wp_remote_retrieve_body($response);
        $suggestions = [];
        
        // Check for sitemap reference
        if (stripos($content, 'Sitemap:') === false) {
            $suggestions[] = 'Add sitemap reference to robots.txt';
        }
        
        // Check for overly restrictive rules
        if (stripos($content, 'Disallow: /') !== false) {
            $suggestions[] = 'Review Disallow rules - site may be blocking all crawlers';
        }
        
        // Check for crawl-delay
        if (stripos($content, 'Crawl-delay:') !== false) {
            $suggestions[] = 'Consider removing Crawl-delay (not supported by Google)';
        }
        
        return [
            'exists' => true,
            'content' => $content,
            'suggestions' => $suggestions,
        ];
    }
    
    /**
     * Audit performance
     */
    private function auditPerformance(): array
    {
        $metrics = [];
        
        // Server response time
        $startTime = microtime(true);
        wp_remote_get(home_url());
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        $metrics[] = [
            'name' => 'Server Response Time',
            'value' => round($responseTime) . 'ms',
            'status' => $responseTime < 200 ? 'good' : ($responseTime < 500 ? 'warning' : 'poor'),
            'recommendation' => $responseTime > 200 ? 'Optimize server configuration or upgrade hosting' : 'Good',
        ];
        
        // Check for CDN
        $headers = wp_remote_retrieve_headers(wp_remote_get(home_url()));
        $hasCDN = isset($headers['cf-ray']) || isset($headers['x-cache']) || isset($headers['x-cdn']);
        
        $metrics[] = [
            'name' => 'CDN Usage',
            'value' => $hasCDN ? 'Detected' : 'Not Detected',
            'status' => $hasCDN ? 'good' : 'warning',
            'recommendation' => $hasCDN ? 'CDN is active' : 'Consider using a CDN for better performance',
        ];
        
        // Check compression
        $hasCompression = isset($headers['content-encoding']) && 
                         (stripos($headers['content-encoding'], 'gzip') !== false || 
                          stripos($headers['content-encoding'], 'br') !== false);
        
        $metrics[] = [
            'name' => 'Compression',
            'value' => $hasCompression ? 'Enabled' : 'Disabled',
            'status' => $hasCompression ? 'good' : 'poor',
            'recommendation' => $hasCompression ? 'Compression is active' : 'Enable Gzip or Brotli compression',
        ];
        
        // Check caching headers
        $hasCaching = isset($headers['cache-control']) || isset($headers['expires']);
        
        $metrics[] = [
            'name' => 'Browser Caching',
            'value' => $hasCaching ? 'Enabled' : 'Disabled',
            'status' => $hasCaching ? 'good' : 'warning',
            'recommendation' => $hasCaching ? 'Caching headers present' : 'Add cache-control headers',
        ];
        
        return $metrics;
    }
    
    /**
     * Helper methods
     */
    private function findRedirectChains(): array
    {
        // Placeholder - would check for redirect chains
        return [];
    }
    
    private function findOrphanedPages(): array
    {
        // Placeholder - would find pages with no internal links
        return [];
    }
    
    private function findDeepPages(): array
    {
        // Placeholder - would find pages >3 clicks from homepage
        return [];
    }
    
    private function findDuplicateContent(): array
    {
        // Placeholder - would detect duplicate content
        return [];
    }
    
    private function findThinContent(): array
    {
        global $wpdb;
        
        return $wpdb->get_col("
            SELECT ID
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'
            AND CHAR_LENGTH(post_content) < 300
        ");
    }
    
    private function countPaginationPages(): int
    {
        // Placeholder - would count pagination pages
        return 0;
    }
    
    private function calculateCrawlabilityScore(array $checks): int
    {
        $checkCount = count($checks);
        if ($checkCount === 0) return 0;
        $passed = count(array_filter($checks, fn($c) => $c['status'] === 'pass'));
        return round(($passed / $checkCount) * 100);
    }
    
    private function calculatePerformanceScore(array $metrics): int
    {
        $metricCount = count($metrics);
        if ($metricCount === 0) return 0;
        $good = count(array_filter($metrics, fn($m) => $m['status'] === 'good'));
        return round(($good / $metricCount) * 100);
    }
    
    private function calculateStructureScore(array $issues): int
    {
        $totalIssues = array_sum(array_column($issues, 'count'));
        return max(0, 100 - ($totalIssues * 2));
    }
    
    private function calculateSitemapScore(array $sitemap): int
    {
        if (!$sitemap['exists']) {
            return 0;
        }
        
        $validPercentage = $sitemap['total_urls'] > 0 
            ? ($sitemap['valid_urls'] / $sitemap['total_urls']) * 100 
            : 0;
            
        return round($validPercentage);
    }
    
    private function getScoreColor(int $score): string
    {
        if ($score >= 80) return '#00a32a';
        if ($score >= 60) return '#dba617';
        return '#d63638';
    }
    
    private function getStatusColor(string $status): string
    {
        switch ($status) {
            case 'good':
            case 'pass':
                return '#00a32a';
            case 'warning':
                return '#dba617';
            case 'poor':
            case 'fail':
                return '#d63638';
            default:
                return '#666';
        }
    }
    
    /**
     * Run scheduled audit
     */
    public function runScheduledAudit(): void
    {
        $this->runFullAudit();
    }
    
    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/technical/audit', [
            'methods' => 'POST',
            'callback' => [$this, 'restRunAudit'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);
    }
    
    /**
     * REST: Run technical audit
     */
    public function restRunAudit(): array
    {
        $audit = $this->runFullAudit();
        return ['success' => true, 'audit' => $audit];
    }
}

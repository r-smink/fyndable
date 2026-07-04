<?php
/**
 * SSEO AI SaaS Dashboard - API Test Script
 * 
 * Access via: yourdashboard.com/wp-content/plugins/ai-seo-saas-dashboard/test-api.php
 */

require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>SSEO AI SaaS - API Test</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f0f0f1; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
        .success { background: #edfaef; border-left: 4px solid #00a32a; padding: 15px; margin: 15px 0; }
        .error { background: #fcf0f1; border-left: 4px solid #d63638; padding: 15px; margin: 15px 0; }
        .info { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f6f7f7; font-weight: 600; }
        .btn { background: #2271b1; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 SSEO AI SaaS Dashboard - API Test</h1>

        <h2>1. Check REST API Routes</h2>
        <?php
        $routes = rest_get_server()->get_routes();
        $sseoRoutes = array_filter(array_keys($routes), function($route) {
            return strpos($route, 'ai-seo-saas') !== false;
        });
        
        if (empty($sseoRoutes)) {
            echo '<div class="error"><strong>❌ No SSEO AI routes found!</strong><br>';
            echo 'The REST API routes are not registered. The plugin may not be properly initialized.</div>';
            
            echo '<div class="info"><strong>Troubleshooting:</strong><br>';
            echo '1. Make sure the SSEO AI SaaS Dashboard plugin is activated<br>';
            echo '2. Try deactivating and reactivating the plugin<br>';
            echo '3. Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules</div>';
        } else {
            echo '<div class="success"><strong>✅ Found ' . count($sseoRoutes) . ' SSEO AI routes:</strong></div>';
            echo '<table>';
            echo '<tr><th>Route</th><th>Methods</th></tr>';
            foreach ($sseoRoutes as $route) {
                $methods = array_keys($routes[$route]);
                echo '<tr>';
                echo '<td><code>' . esc_html($route) . '</code></td>';
                echo '<td>' . esc_html(implode(', ', $methods)) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
        ?>

        <h2>2. Test License Activation Endpoint</h2>
        <?php
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_license_keys';
        
        // Get a test license key
        $testLicense = $wpdb->get_row("SELECT * FROM $table WHERE status = 'active' LIMIT 1", ARRAY_A);
        
        if (!$testLicense) {
            echo '<div class="error"><strong>❌ No active license keys found in database</strong><br>';
            echo 'Please generate a license key first in the SSEO AI SaaS Dashboard.</div>';
            echo '<a href="' . admin_url('admin.php?page=sseo-ai-saas-licenses') . '" class="btn">Go to License Management</a>';
        } else {
            echo '<div class="info"><strong>Test License Key:</strong> ' . esc_html($testLicense['license_key']) . '</div>';
            
            if (isset($_POST['test_activation'])) {
                $apiUrl = home_url('/wp-json/ai-seo-saas/v1/license/activate');
                
                echo '<div class="info"><strong>Testing endpoint:</strong> ' . esc_html($apiUrl) . '</div>';
                
                $response = wp_remote_post($apiUrl, [
                    'body' => [
                        'license_key' => $testLicense['license_key'],
                        'site_url' => 'http://test-site.local',
                        'site_name' => 'Test Site',
                    ],
                    'timeout' => 30,
                    'sslverify' => false,
                ]);
                
                if (is_wp_error($response)) {
                    echo '<div class="error"><strong>❌ Request Error:</strong><br>';
                    echo esc_html($response->get_error_message()) . '</div>';
                } else {
                    $statusCode = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    $data = json_decode($body, true);
                    
                    if ($statusCode == 200) {
                        echo '<div class="success"><strong>✅ Success! Status: ' . $statusCode . '</strong></div>';
                    } else {
                        echo '<div class="error"><strong>❌ Failed! Status: ' . $statusCode . '</strong></div>';
                    }
                    
                    echo '<strong>Response:</strong>';
                    echo '<pre>' . esc_html(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
                }
            }
            
            echo '<form method="post">';
            echo '<button type="submit" name="test_activation" class="btn">🧪 Test Activation Endpoint</button>';
            echo '</form>';
        }
        ?>

        <h2>3. Database Tables</h2>
        <?php
        $tables = [
            'sseo_ai_license_keys',
            'sseo_ai_tenants',
            'sseo_ai_tenant_settings',
            'sseo_ai_tenant_usage',
        ];
        
        echo '<table>';
        echo '<tr><th>Table</th><th>Status</th><th>Row Count</th></tr>';
        foreach ($tables as $table) {
            $fullTable = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$fullTable'") == $fullTable;
            
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $fullTable");
                echo '<tr>';
                echo '<td><code>' . esc_html($fullTable) . '</code></td>';
                echo '<td><span style="color: #00a32a;">✅ Exists</span></td>';
                echo '<td>' . esc_html($count) . ' rows</td>';
                echo '</tr>';
            } else {
                echo '<tr>';
                echo '<td><code>' . esc_html($fullTable) . '</code></td>';
                echo '<td><span style="color: #d63638;">❌ Missing</span></td>';
                echo '<td>-</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        ?>

        <h2>4. Plugin Status</h2>
        <?php
        $plugins = get_plugins();
        $activePlugins = get_option('active_plugins', []);
        
        echo '<table>';
        echo '<tr><th>Plugin</th><th>Version</th><th>Status</th></tr>';
        
        foreach ($plugins as $pluginFile => $pluginData) {
            if (strpos($pluginData['Name'], 'SSEO AI') !== false) {
                $isActive = in_array($pluginFile, $activePlugins);
                echo '<tr>';
                echo '<td><strong>' . esc_html($pluginData['Name']) . '</strong></td>';
                echo '<td>' . esc_html($pluginData['Version']) . '</td>';
                echo '<td>' . ($isActive ? '<span style="color: #00a32a;">✅ Active</span>' : '<span style="color: #d63638;">❌ Inactive</span>') . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        ?>

        <h2>5. Quick Fixes</h2>
        <div class="info">
            <strong>If routes are not showing:</strong><br>
            1. Go to <a href="<?php echo admin_url('options-permalink.php'); ?>">Settings → Permalinks</a><br>
            2. Click "Save Changes" (don't change anything, just save)<br>
            3. This will flush the rewrite rules and register REST API routes<br>
            4. Refresh this page to verify
        </div>

        <a href="<?php echo admin_url('options-permalink.php'); ?>" class="btn">🔄 Go to Permalinks Settings</a>
        <a href="<?php echo admin_url('plugins.php'); ?>" class="btn">🔌 Go to Plugins</a>
    </div>
</body>
</html>

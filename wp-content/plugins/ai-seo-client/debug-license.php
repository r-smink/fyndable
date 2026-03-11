<?php
/**
 * SSEO AI License Debug Script
 * 
 * Place this file in wp-content/plugins/ai-seo-client/
 * Access via: yoursite.com/wp-content/plugins/ai-seo-client/debug-license.php
 * 
 * This will show you exactly what's happening with license activation
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>SSEO AI License Debug</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 20px; background: #f0f0f1; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        h1 { color: #1d2327; border-bottom: 2px solid #2271b1; padding-bottom: 10px; }
        h2 { color: #2271b1; margin-top: 30px; }
        .info-box { background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0; }
        .error-box { background: #fcf0f1; border-left: 4px solid #d63638; padding: 15px; margin: 15px 0; }
        .success-box { background: #edfaef; border-left: 4px solid #00a32a; padding: 15px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f6f7f7; font-weight: 600; }
        code { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-family: Consolas, Monaco, monospace; }
        .test-btn { background: #2271b1; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-btn:hover { background: #135e96; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 SSEO AI License Debug Information</h1>
        
        <h2>Current License Status</h2>
        <?php
        $licenseKey = get_option('sseo_ai_client_license', '');
        $tenantKey = get_option('sseo_ai_client_tenant', '');
        $dashboardUrl = get_option('sseo_ai_client_dashboard_url', '');
        $licenseStatus = get_option('sseo_ai_client_license_status', 'inactive');
        $licenseTier = get_option('sseo_ai_client_license_tier', 'free');
        ?>
        
        <table>
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>License Key</td>
                <td><?php echo $licenseKey ? esc_html(substr($licenseKey, 0, 20) . '...') : '<em>Not set</em>'; ?></td>
            </tr>
            <tr>
                <td>Tenant Key</td>
                <td><?php echo $tenantKey ? esc_html(substr($tenantKey, 0, 30) . '...') : '<em>Not set</em>'; ?></td>
            </tr>
            <tr>
                <td>Dashboard URL</td>
                <td><?php echo $dashboardUrl ? esc_html($dashboardUrl) : '<em>Not set</em>'; ?></td>
            </tr>
            <tr>
                <td>License Status</td>
                <td><strong><?php echo esc_html($licenseStatus); ?></strong></td>
            </tr>
            <tr>
                <td>License Tier</td>
                <td><strong><?php echo esc_html($licenseTier); ?></strong></td>
            </tr>
        </table>

        <h2>API Endpoint Test</h2>
        <?php
        if ($dashboardUrl) {
            $apiUrl = rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/activate';
            echo '<div class="info-box">';
            echo '<strong>Testing API endpoint:</strong><br>';
            echo '<code>' . esc_html($apiUrl) . '</code>';
            echo '</div>';
            
            // Test if endpoint is reachable
            $testResponse = wp_remote_get($apiUrl, ['timeout' => 10, 'sslverify' => false]);
            
            if (is_wp_error($testResponse)) {
                echo '<div class="error-box">';
                echo '<strong>❌ Connection Error:</strong><br>';
                echo esc_html($testResponse->get_error_message());
                echo '</div>';
            } else {
                $statusCode = wp_remote_retrieve_response_code($testResponse);
                echo '<div class="' . ($statusCode == 404 || $statusCode == 405 ? 'success-box' : 'error-box') . '">';
                echo '<strong>Response Status:</strong> ' . esc_html($statusCode) . '<br>';
                echo '<em>Note: 404 or 405 is expected for GET request (endpoint requires POST)</em>';
                echo '</div>';
            }
        } else {
            echo '<div class="error-box">Dashboard URL not configured</div>';
        }
        ?>

        <h2>Test License Activation</h2>
        <?php
        if (isset($_POST['test_activation']) && $licenseKey && $dashboardUrl) {
            echo '<div class="info-box"><strong>Testing activation...</strong></div>';
            
            $siteUrl = get_site_url();
            $siteName = get_bloginfo('name');
            
            $response = wp_remote_post(
                rtrim($dashboardUrl, '/') . '/wp-json/ai-seo-saas/v1/license/activate',
                [
                    'body' => [
                        'license_key' => $licenseKey,
                        'site_url' => $siteUrl,
                        'site_name' => $siteName,
                    ],
                    'timeout' => 30,
                    'sslverify' => false,
                ]
            );
            
            if (is_wp_error($response)) {
                echo '<div class="error-box">';
                echo '<strong>❌ Connection Error:</strong><br>';
                echo esc_html($response->get_error_message());
                echo '</div>';
            } else {
                $statusCode = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                
                echo '<div class="' . ($statusCode == 200 ? 'success-box' : 'error-box') . '">';
                echo '<strong>Response Status:</strong> ' . esc_html($statusCode) . '<br><br>';
                echo '<strong>Response Body:</strong><br>';
                echo '<pre>' . esc_html(json_encode($data, JSON_PRETTY_PRINT)) . '</pre>';
                echo '</div>';
            }
        }
        ?>
        
        <form method="post">
            <button type="submit" name="test_activation" class="test-btn">
                🧪 Test Activation Now
            </button>
        </form>

        <h2>Recent Error Log (Last 50 SSEO AI entries)</h2>
        <?php
        $logFile = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $sseoLines = array_filter($lines, function($line) {
                return stripos($line, 'SSEO AI') !== false;
            });
            $recentLines = array_slice($sseoLines, -50);
            
            if (empty($recentLines)) {
                echo '<div class="info-box">No SSEO AI log entries found. Make sure WP_DEBUG_LOG is enabled.</div>';
            } else {
                echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;">';
                foreach ($recentLines as $line) {
                    echo esc_html($line);
                }
                echo '</pre>';
            }
        } else {
            echo '<div class="error-box">Debug log file not found. Enable WP_DEBUG_LOG in wp-config.php</div>';
        }
        ?>

        <h2>WordPress Debug Settings</h2>
        <div class="info-box">
            <strong>WP_DEBUG:</strong> <?php echo defined('WP_DEBUG') && WP_DEBUG ? '✅ Enabled' : '❌ Disabled'; ?><br>
            <strong>WP_DEBUG_LOG:</strong> <?php echo defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? '✅ Enabled' : '❌ Disabled'; ?><br>
            <strong>WP_DEBUG_DISPLAY:</strong> <?php echo defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY ? '✅ Enabled' : '❌ Disabled'; ?>
        </div>

        <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <strong>Next Steps:</strong><br>
            1. Check the error log above for specific error messages<br>
            2. Make sure you have generated a license key in the SaaS Dashboard<br>
            3. Verify the Dashboard URL is correct and accessible<br>
            4. Test the activation using the button above
        </p>
    </div>
</body>
</html>

# SSEO AI - Setup & Installation Guide

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [SaaS Dashboard Setup](#saas-dashboard-setup)
3. [Client Plugin Setup](#client-plugin-setup)
4. [Configuration](#configuration)
5. [White-Label Setup](#white-label-setup)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

## Prerequisites

### System Requirements

**SaaS Dashboard Server:**
- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- SSL certificate (HTTPS required)
- Minimum 512MB PHP memory limit
- cURL enabled

**Customer Sites (Client Plugin):**
- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- 256MB PHP memory limit minimum

### API Keys Required

Before starting, obtain these API keys:

1. **OpenAI API Key** (required)
   - Sign up at https://platform.openai.com
   - Generate API key from dashboard
   - Recommended: Set spending limits

2. **Anthropic API Key** (optional)
   - Sign up at https://console.anthropic.com
   - For Claude AI models

3. **SERP API Key** (optional but recommended)
   - Options: SerpAPI, DataForSEO, or similar
   - For keyword research and SERP analysis

## SaaS Dashboard Setup

### Step 1: Install WordPress

```bash
# Standard WordPress installation
# Ensure clean install on dedicated domain
# Example: https://saas.yourdomain.com
```

### Step 2: Upload SaaS Dashboard Plugin

```bash
# Via FTP/SFTP
cd /path/to/wordpress/wp-content/plugins/
unzip ai-seo-saas-dashboard.zip

# Or via WordPress Admin
# Plugins → Add New → Upload Plugin
```

### Step 3: Activate Plugin

```bash
# Via WP-CLI
wp plugin activate ai-seo-saas-dashboard

# Or via WordPress Admin
# Plugins → Installed Plugins → Activate "SSEO AI SaaS Dashboard"
```

### Step 4: Configure API Keys

Navigate to: **SSEO AI SaaS → Settings**

```
OpenAI API Key: sk-...
Anthropic API Key: sk-ant-... (optional)
SERP API Provider: SerpAPI
SERP API Key: your-key
```

### Step 5: Verify Database Tables

The plugin automatically creates these tables on activation:

```sql
wp_sseo_ai_tenants
wp_sseo_ai_tenant_settings
wp_sseo_ai_tenant_usage
wp_sseo_ai_license_keys
```

Verify in phpMyAdmin or via WP-CLI:

```bash
wp db query "SHOW TABLES LIKE 'wp_sseo_ai_%'"
```

### Step 6: Test REST API

```bash
# Test license validation endpoint
curl -X POST https://saas.yourdomain.com/wp-json/ai-seo-saas/v1/license/validate \
  -H "Content-Type: application/json" \
  -d '{"license_key":"test","site_url":"https://example.com"}'

# Should return JSON response (even if license invalid)
```

## Client Plugin Setup

### Step 1: Upload Client Plugin

On customer WordPress site:

```bash
# Via FTP/SFTP
cd /path/to/wordpress/wp-content/plugins/
unzip ai-seo-client.zip

# Or via WordPress Admin
# Plugins → Add New → Upload Plugin
```

### Step 2: Activate Plugin

```bash
# Via WP-CLI
wp plugin activate ai-seo-client

# Or via WordPress Admin
# Plugins → Activate "SSEO AI"
```

### Step 3: Generate License Key

On SaaS Dashboard:

1. Go to **SSEO AI SaaS → Licenses**
2. Click **Generate New License**
3. Fill in details:
   - **License Type:** Paid
   - **Tier:** Professional (or desired tier)
   - **Max Sites:** 1
   - **Expires:** 365 days (or custom)
   - **Assigned To:** customer@email.com
4. Click **Generate License**
5. Copy the generated license key: `SSEO-XXXX-XXXX-XXXX-XXXX`

### Step 4: Activate License on Client Site

On customer WordPress site:

1. Go to **SSEO AI → Connection**
2. Enter:
   - **Dashboard URL:** `https://saas.yourdomain.com`
   - **License Key:** `SSEO-XXXX-XXXX-XXXX-XXXX`
3. Click **Activate License**
4. Success message should appear
5. Menu should show available features based on tier

### Step 5: Verify Activation

Check that:
- ✅ License status shows "Active"
- ✅ Tenant key is displayed
- ✅ Tier is correct
- ✅ Expiration date is shown
- ✅ Feature menu items appear

## Configuration

### SaaS Dashboard Settings

#### Global Settings

Navigate to: **SSEO AI SaaS → Settings**

```
AI Provider Settings:
├─ Default Model: gpt-4-turbo-preview
├─ Fallback Model: gpt-3.5-turbo
├─ Max Tokens: 4000
└─ Temperature: 0.7

Rate Limiting:
├─ Free Tier: 100 calls/month, 5/minute
├─ Starter: 1000 calls/month, 10/minute
├─ Professional: 5000 calls/month, 20/minute
└─ Agency: Unlimited, 50/minute

SERP Settings:
├─ Cache Duration: 24 hours
├─ Results Per Query: 10
└─ Default Location: United States
```

#### Pricing Tiers

Configure tier limits in: **SSEO AI SaaS → Settings → Tiers**

```php
// Example tier configuration
$tiers = [
    'free' => [
        'price' => 0,
        'api_calls' => 100,
        'features' => ['basic_seo', 'sitemap']
    ],
    'starter' => [
        'price' => 19,
        'api_calls' => 1000,
        'features' => ['basic_seo', 'sitemap', 'link_assistant', 'redirects']
    ],
    'professional' => [
        'price' => 49,
        'api_calls' => 5000,
        'features' => ['all_basic', 'schema', 'rank_tracker', 'content_optimizer']
    ],
    'agency' => [
        'price' => 99,
        'api_calls' => -1, // unlimited
        'features' => ['all_features']
    ]
];
```

### Client Plugin Settings

#### Feature Configuration

Some features can be configured per-site:

Navigate to: **SSEO AI → Settings**

```
Content Optimization:
├─ Auto-optimize on publish: Yes/No
├─ Target keyword required: Yes/No
└─ Min content length: 300 words

Rank Tracking:
├─ Check frequency: Daily/Weekly
├─ Track top: 10/20/50 positions
└─ Location: Custom

Schema Markup:
├─ Auto-generate: Yes/No
├─ Default type: Article
└─ Organization info: [Configure]
```

## White-Label Setup

### Configure Branding on SaaS Dashboard

Navigate to: **SSEO AI SaaS → White-Label**

```
Company Information:
├─ Company Name: Your SEO Agency
├─ Company Logo: https://youragency.com/logo.png
├─ Primary Color: #2563eb
├─ Secondary Color: #1e40af
├─ Support Email: support@youragency.com
└─ Support URL: https://youragency.com/support
```

### Apply to Specific Tenant (Optional)

For tenant-specific branding:

```php
// Via code or custom admin interface
$tenants->setTenantSetting($tenantKey, 'white_label_brand', [
    'company_name' => 'Client Specific Name',
    'company_logo' => 'https://client.com/logo.png',
    'primary_color' => '#ff6b6b',
    'secondary_color' => '#ee5a52'
]);
```

### Sync to Client

White-label settings automatically sync when:
1. License is activated
2. License is reactivated
3. Tenant settings are updated (requires reactivation)

To force sync on client:
1. Deactivate license
2. Reactivate license
3. Refresh page (Ctrl+F5)

## Testing

### Test License Activation

```bash
# On client site, via WP-CLI
wp eval "
\$api = new SSEOAIClient\DashboardAPI(new SSEOAIClient\Settings());
\$result = \$api->activateLicense(
    'SSEO-XXXX-XXXX-XXXX-XXXX',
    'https://saas.yourdomain.com'
);
print_r(\$result);
"
```

### Test AI Content Generation

1. Create/edit a post
2. Open SSEO AI sidebar
3. Enter target keyword
4. Click "Generate Content Ideas"
5. Verify AI response appears

### Test SERP Analysis

1. Go to **SSEO AI → SERP Analysis**
2. Enter keyword: "best coffee makers"
3. Click "Analyze"
4. Verify SERP data loads
5. Check competitor analysis

### Test White-Label

1. Check admin menu shows custom company name
2. Verify colors are applied to buttons/headers
3. Check support email in footer (if configured)

### Test Rate Limiting

```bash
# Make rapid API calls to test rate limiting
for i in {1..20}; do
  curl -X POST https://saas.yourdomain.com/wp-json/ai-seo-saas/v1/ai/chat \
    -H "X-Tenant-Key: tenant_abc123" \
    -H "X-License-Key: SSEO-XXXX-XXXX-XXXX-XXXX" \
    -H "Content-Type: application/json" \
    -d '{"prompt":"Test","model":"gpt-3.5-turbo"}' &
done

# Should return 429 error after limit reached
```

## Troubleshooting

### Issue: "No route found" Error

**Solution:**
```bash
# Flush permalinks on SaaS Dashboard
wp rewrite flush

# Or via WordPress Admin
Settings → Permalinks → Save Changes
```

### Issue: License Activation Fails

**Checklist:**
- [ ] Dashboard URL is correct (include https://)
- [ ] License key is valid and active
- [ ] SaaS Dashboard is accessible
- [ ] REST API is enabled
- [ ] No firewall blocking requests

**Debug:**
```bash
# Test connectivity
curl -I https://saas.yourdomain.com/wp-json/

# Should return 200 OK with REST API headers
```

### Issue: White-Label Not Applying

**Solution:**
1. Deactivate license on client
2. Reactivate license
3. Hard refresh browser (Ctrl+Shift+R)
4. Check `wp_options` for `sseo_ai_white_label`

```bash
wp option get sseo_ai_white_label --format=json
```

### Issue: Division by Zero Errors

**Solution:**
Update to version 1.0.4 or higher. All division by zero errors were fixed.

```bash
# Check version
wp plugin list | grep ai-seo-client

# Update if needed
wp plugin update ai-seo-client
```

### Issue: Permission Denied on Feature Pages

**Solution:**
Update to version 1.0.7 or higher. All permission capabilities were fixed to `manage_options`.

### Issue: Database Errors

**Solution:**
```bash
# Recreate tables
wp eval "
\$repo = new SSEOAISaaS\TenantRepository();
\$repo->maybeCreateTables();
"

# Or deactivate and reactivate plugin
wp plugin deactivate ai-seo-saas-dashboard
wp plugin activate ai-seo-saas-dashboard
```

### Issue: API Calls Not Working

**Debug Steps:**

1. **Check API keys:**
```bash
wp option get sseo_ai_saas_openai_key
```

2. **Check tenant status:**
```bash
wp eval "
\$repo = new SSEOAISaaS\TenantRepository();
\$tenant = \$repo->getTenant('tenant_abc123');
print_r(\$tenant);
"
```

3. **Check error logs:**
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

4. **Test OpenAI directly:**
```bash
curl https://api.openai.com/v1/chat/completions \
  -H "Authorization: Bearer sk-..." \
  -H "Content-Type: application/json" \
  -d '{"model":"gpt-3.5-turbo","messages":[{"role":"user","content":"Test"}]}'
```

### Issue: High API Costs

**Solutions:**

1. **Enable caching:**
```php
// In SaaS settings
define('SSEO_CACHE_AI_RESPONSES', true);
define('SSEO_CACHE_DURATION', 3600); // 1 hour
```

2. **Use cheaper models:**
```php
// Default to GPT-3.5 instead of GPT-4
update_option('sseo_ai_default_model', 'gpt-3.5-turbo');
```

3. **Implement stricter rate limits:**
```php
// Reduce API calls per tier
$limits['professional']['api_calls'] = 2000; // instead of 5000
```

4. **Monitor usage:**
```bash
# Check usage per tenant
wp eval "
\$repo = new SSEOAISaaS\TenantRepository();
\$usage = \$wpdb->get_results('SELECT * FROM wp_sseo_ai_tenant_usage ORDER BY api_cost DESC LIMIT 10');
print_r(\$usage);
"
```

## Security Best Practices

### 1. Secure API Keys

```php
// Store in wp-config.php, not database
define('SSEO_OPENAI_API_KEY', 'sk-...');
define('SSEO_ANTHROPIC_API_KEY', 'sk-ant-...');

// Never commit to version control
// Add to .gitignore:
# wp-config.php
# .env
```

### 2. Enable HTTPS

```bash
# Force HTTPS in wp-config.php
define('FORCE_SSL_ADMIN', true);

# Redirect HTTP to HTTPS in .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 3. Limit Login Attempts

Install security plugin or add to functions.php:

```php
// Limit login attempts
add_filter('authenticate', function($user, $username, $password) {
    $attempts = get_transient('login_attempts_' . $username);
    if ($attempts && $attempts > 5) {
        return new WP_Error('too_many_attempts', 'Too many login attempts. Try again in 15 minutes.');
    }
    return $user;
}, 30, 3);
```

### 4. Regular Backups

```bash
# Backup database daily
wp db export backup-$(date +%Y%m%d).sql

# Backup files weekly
tar -czf backup-files-$(date +%Y%m%d).tar.gz wp-content/
```

### 5. Update Regularly

```bash
# Update WordPress core
wp core update

# Update plugins
wp plugin update --all

# Update themes
wp theme update --all
```

## Performance Optimization

### 1. Enable Object Caching

```bash
# Install Redis or Memcached
# Add to wp-config.php
define('WP_CACHE', true);
```

### 2. Use CDN

Configure CDN for:
- SaaS Dashboard static assets
- White-label logos/images
- API responses (if applicable)

### 3. Database Optimization

```bash
# Optimize tables monthly
wp db optimize

# Clean up old data
wp eval "
\$wpdb->query('DELETE FROM wp_sseo_ai_tenant_usage WHERE period < DATE_SUB(NOW(), INTERVAL 12 MONTH)');
"
```

### 4. Monitor Performance

```bash
# Install Query Monitor plugin
wp plugin install query-monitor --activate

# Check slow queries
# Admin → Query Monitor → Database Queries
```

## Maintenance

### Daily Tasks
- [ ] Monitor error logs
- [ ] Check API usage/costs
- [ ] Verify license activations

### Weekly Tasks
- [ ] Review tenant usage reports
- [ ] Check for plugin updates
- [ ] Backup database

### Monthly Tasks
- [ ] Analyze revenue vs costs
- [ ] Optimize database
- [ ] Review and adjust rate limits
- [ ] Clean up expired licenses

## Support Resources

- **Documentation:** See ARCHITECTURE.md
- **GitHub Issues:** Report bugs and feature requests
- **Email Support:** Configure in white-label settings
- **Community Forum:** (if available)

## Next Steps

After setup is complete:

1. ✅ Create test license and activate on demo site
2. ✅ Configure white-label branding
3. ✅ Set up monitoring and alerts
4. ✅ Create customer documentation
5. ✅ Set up billing integration (Stripe, etc.)
6. ✅ Launch marketing site
7. ✅ Onboard first customers

## Appendix: WP-CLI Commands

```bash
# List all tenants
wp eval "print_r((new SSEOAISaaS\TenantRepository())->getTenants());"

# Generate license
wp eval "
\$gen = new SSEOAISaaS\LicenseKeyGenerator(new SSEOAISaaS\TenantRepository());
\$license = \$gen->generateLicense('professional', 365, 1);
echo \$license['license_key'];
"

# Check tenant usage
wp eval "
global \$wpdb;
\$usage = \$wpdb->get_results('SELECT * FROM wp_sseo_ai_tenant_usage WHERE tenant_id = 1');
print_r(\$usage);
"

# Flush all caches
wp cache flush
wp rewrite flush
wp transient delete --all
```

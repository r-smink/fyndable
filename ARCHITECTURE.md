# SSEO AI - System Architecture

## Overview

SSEO AI is a **multi-tenant SaaS platform** for WordPress SEO optimization, consisting of two main plugins:

1. **SaaS Dashboard Plugin** - Runs on the central SaaS platform (your server)
2. **Client Plugin** - Installed on customer WordPress sites

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    SaaS Dashboard Server                     │
│  ┌────────────────────────────────────────────────────────┐ │
│  │         ai-seo-saas-dashboard Plugin                   │ │
│  │                                                         │ │
│  │  ├─ License Management (LicenseAdmin)                  │ │
│  │  ├─ Tenant Repository (Multi-tenant DB)               │ │
│  │  ├─ License API (REST endpoints)                      │ │
│  │  ├─ API Gateway (Proxied AI calls)                    │ │
│  │  ├─ White-Label Admin (Branding settings)             │ │
│  │  └─ SaaS Settings (Global config)                     │ │
│  │                                                         │ │
│  │  REST API Endpoints:                                   │ │
│  │  • /ai-seo-saas/v1/license/validate                   │ │
│  │  • /ai-seo-saas/v1/license/activate                   │ │
│  │  • /ai-seo-saas/v1/tenant/status                      │ │
│  │  • /ai-seo-saas/v1/ai/chat (Proxied to OpenAI)        │ │
│  │  • /ai-seo-saas/v1/serp/analyze (SERP data)           │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                            ↕ HTTPS REST API
┌─────────────────────────────────────────────────────────────┐
│                  Customer WordPress Sites                    │
│  ┌────────────────────────────────────────────────────────┐ │
│  │            ai-seo-client Plugin                        │ │
│  │                                                         │ │
│  │  ├─ License Validator (Checks with SaaS)              │ │
│  │  ├─ Dashboard API Client (Calls SaaS endpoints)       │ │
│  │  ├─ LLM Client (Proxied AI via SaaS)                  │ │
│  │  ├─ 40+ SEO Features (Content, Technical, etc.)       │ │
│  │  └─ Settings & UI (WordPress admin pages)             │ │
│  │                                                         │ │
│  │  Calls SaaS API for:                                   │ │
│  │  • License validation                                  │ │
│  │  • AI content generation                              │ │
│  │  • SERP analysis                                       │ │
│  │  • Keyword research                                    │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### 1. SaaS Dashboard Plugin (`ai-seo-saas-dashboard`)

**Purpose:** Central management platform for licenses, tenants, and API proxying.

**Key Classes:**

#### `Dashboard` (Main orchestrator)
- Initializes all services
- Registers admin menus and REST routes
- Coordinates between components

#### `TenantRepository`
- Multi-tenant database management
- Tables: `sseo_ai_tenants`, `sseo_ai_tenant_settings`, `sseo_ai_license_keys`
- Tenant isolation and data management

#### `LicenseKeyGenerator`
- Generates unique license keys
- Validates license status
- Activates licenses and creates tenants

#### `LicenseAPI`
- REST endpoints for license operations
- Returns white-label settings on activation
- Validates tenant access

#### `ApiGateway`
- Proxies AI requests to OpenAI/Anthropic/Mistral
- Rate limiting per tenant
- Cost tracking and usage monitoring
- SERP data aggregation

#### `WhiteLabelAdmin`
- White-label branding settings (logo, colors, company name)
- Client portal management
- Team management
- Billing & invoicing dashboard

**Database Schema:**

```sql
-- Tenants table
sseo_ai_tenants (
    id, tenant_key, name, domain, email,
    status, tier, license_key, max_sites,
    rate_limit, api_calls_limit,
    created_at, expires_at, metadata
)

-- License keys table
sseo_ai_license_keys (
    id, license_key, license_type, tier,
    status, max_sites, rate_limit, api_calls_limit,
    expires_days, assigned_to, created_at
)

-- Tenant settings (white-label, etc.)
sseo_ai_tenant_settings (
    id, tenant_id, setting_key, setting_value
)

-- Usage tracking for billing
sseo_ai_tenant_usage (
    id, tenant_id, period, api_calls,
    api_cost, serp_requests, content_generated
)
```

### 2. Client Plugin (`ai-seo-client`)

**Purpose:** WordPress plugin installed on customer sites with 40+ SEO features.

**Key Classes:**

#### `Client` (Main plugin class)
- Registers all features based on license tier
- Admin menu management
- Dashboard rendering
- License activation handling

#### `LicenseValidator`
- Validates license with SaaS Dashboard
- Caches validation results
- Checks tier permissions

#### `DashboardAPI`
- HTTP client for SaaS API calls
- Handles authentication (license key + tenant key)
- Error handling and retries

#### `LLMClient`
- Proxies AI requests through SaaS Dashboard
- Supports multiple providers (OpenAI, Anthropic, Mistral)
- Automatic fallback handling

#### Feature Classes (40+ modules)
Each feature is a separate class that registers itself:
- Admin menus
- REST API endpoints
- Meta boxes
- Gutenberg blocks
- AJAX handlers

**Feature Tiers:**

```
Free Tier:
- Basic SEO meta fields
- XML sitemap
- Robots.txt editor

Starter Tier ($19/mo):
+ Link Assistant
+ Redirect Manager
+ Image Alt Generator
+ Content Rewriter

Professional Tier ($49/mo):
+ Schema Markup
+ Local SEO
+ Rank Tracker
+ Content Optimizer
+ SERP Competitor
+ Topic Clusters
+ Keyword Explorer
+ GSC Dashboard

Business Tier ($79/mo):
+ AI Content Writer
+ Content Repurposer
+ Bulk Optimizer
+ Content Decay Monitor

Agency Tier ($99/mo):
+ SEO Revisions
+ AI Plagiarism Checker
+ White-label options
+ Multi-site management
```

## Communication Flow

### License Activation Flow

```
1. User enters license key in Client Plugin
   ↓
2. Client calls: POST /ai-seo-saas/v1/license/activate
   Body: { license_key, site_url, site_name }
   ↓
3. SaaS Dashboard validates license
   ↓
4. SaaS creates/updates tenant record
   ↓
5. SaaS returns:
   {
     tenant_key,
     tier,
     expires_at,
     rate_limit,
     api_calls_limit,
     white_label: {
       company_name,
       company_logo,
       primary_color,
       secondary_color,
       support_email
     }
   }
   ↓
6. Client stores tenant_key and white_label settings
   ↓
7. Client applies white-label branding to UI
```

### AI Content Generation Flow

```
1. User requests AI content in Client Plugin
   ↓
2. Client calls: POST /ai-seo-saas/v1/ai/chat
   Headers: X-Tenant-Key, X-License-Key
   Body: { prompt, model, max_tokens }
   ↓
3. SaaS validates tenant and checks rate limits
   ↓
4. SaaS proxies request to OpenAI/Anthropic
   ↓
5. SaaS tracks usage and costs
   ↓
6. SaaS returns AI response to Client
   ↓
7. Client displays content to user
```

### SERP Analysis Flow

```
1. User requests SERP analysis for keyword
   ↓
2. Client calls: POST /ai-seo-saas/v1/serp/analyze
   Body: { keyword, location, device }
   ↓
3. SaaS fetches SERP data (cached or fresh)
   ↓
4. SaaS analyzes top 10 results:
   - Title patterns
   - Meta descriptions
   - Content length
   - Headings structure
   - Keywords used
   ↓
5. SaaS returns analysis to Client
   ↓
6. Client displays recommendations
```

## Security & Authentication

### API Authentication

All Client → SaaS API calls include:

```http
POST /ai-seo-saas/v1/ai/chat
X-Tenant-Key: tenant_abc123
X-License-Key: SSEO-XXXX-XXXX-XXXX-XXXX
Content-Type: application/json
```

### Validation Process

1. **License Key Validation**
   - Check if license exists in database
   - Verify status is 'active'
   - Check expiration date

2. **Tenant Key Validation**
   - Verify tenant exists
   - Match tenant to license key
   - Check tenant status

3. **Rate Limiting**
   - Track API calls per tenant per period
   - Enforce tier-based limits
   - Return 429 if limit exceeded

4. **Domain Verification**
   - Store activated domain in tenant record
   - Optionally verify requests come from registered domain

## White-Label System

### Configuration Flow

```
1. SaaS Admin sets white-label settings:
   - Company name: "Your SEO Agency"
   - Logo URL: https://example.com/logo.png
   - Primary color: #2563eb
   - Support email: support@example.com
   ↓
2. Settings stored in:
   - Global: wp_options (sseo_ai_saas_wl_*)
   - Per-tenant: sseo_ai_tenant_settings
   ↓
3. On license activation, white-label data sent to Client
   ↓
4. Client stores in: wp_options (sseo_ai_white_label)
   ↓
5. Client applies branding:
   - Admin menu name
   - CSS color variables
   - Support links
   - Footer text
```

### CSS Variables Applied

```css
:root {
    --sseo-primary-color: #2563eb;
    --sseo-secondary-color: #1e40af;
}

.sseo-ai-header,
.ai-tool-card:hover {
    border-color: var(--sseo-primary-color);
}

.button-primary.sseo-btn {
    background-color: var(--sseo-primary-color);
}
```

## Data Flow Examples

### Example 1: Content Optimization

```
User clicks "Optimize Content" on post
  ↓
Client: Extracts post content and target keyword
  ↓
Client → SaaS: POST /ai-seo-saas/v1/ai/chat
  {
    prompt: "Optimize this content for keyword 'SEO tips'...",
    model: "gpt-4",
    max_tokens: 2000
  }
  ↓
SaaS: Validates tenant, checks API limit (e.g., 1000/month)
  ↓
SaaS → OpenAI: Proxied request
  ↓
OpenAI → SaaS: AI response
  ↓
SaaS: Logs usage (api_calls++, api_cost += $0.06)
  ↓
SaaS → Client: Returns optimized content
  ↓
Client: Displays suggestions in editor sidebar
```

### Example 2: Keyword Research

```
User searches for "best coffee makers"
  ↓
Client → SaaS: POST /ai-seo-saas/v1/keywords/expand
  { keyword: "best coffee makers" }
  ↓
SaaS: Checks cache for this keyword
  ↓
SaaS: If not cached, fetches SERP data
  ↓
SaaS: Extracts related keywords from:
  - SERP titles
  - People Also Ask
  - Related searches
  ↓
SaaS: Clusters keywords by similarity
  ↓
SaaS → Client: Returns keyword list with metrics
  {
    keywords: [
      { keyword: "best drip coffee makers", volume: 5400, difficulty: 45 },
      { keyword: "top rated coffee machines", volume: 3200, difficulty: 52 }
    ]
  }
  ↓
Client: Displays in keyword explorer UI
```

## Error Handling

### License Validation Errors

```php
// Client side
$result = $this->dashboardAPI->activateLicense($licenseKey, $dashboardUrl);

if (is_wp_error($result)) {
    // Possible errors:
    // - 'invalid_license': License not found
    // - 'expired_license': License expired
    // - 'max_sites_reached': Too many activations
    // - 'connection_error': Can't reach SaaS
    
    wp_redirect(admin_url('admin.php?page=ai-seo-client&error=' . $result->get_error_message()));
}
```

### API Call Errors

```php
// SaaS side
if ($tenant['api_calls_this_month'] >= $tenant['api_calls_limit']) {
    return new \WP_REST_Response([
        'success' => false,
        'error' => 'rate_limit_exceeded',
        'message' => 'Monthly API limit reached. Upgrade your plan.'
    ], 429);
}
```

## Deployment Architecture

### Recommended Setup

```
Production:
├─ SaaS Dashboard Server
│  ├─ WordPress installation
│  ├─ ai-seo-saas-dashboard plugin
│  ├─ SSL certificate (required)
│  ├─ CDN (optional, for performance)
│  └─ Database (MySQL 5.7+)
│
└─ Customer Sites (distributed)
   ├─ WordPress installations
   ├─ ai-seo-client plugin
   └─ License key configured
```

### Environment Variables

**SaaS Dashboard:**
```php
// wp-config.php or plugin settings
define('SSEO_OPENAI_API_KEY', 'sk-...');
define('SSEO_ANTHROPIC_API_KEY', 'sk-ant-...');
define('SSEO_SERP_API_KEY', 'your-serp-api-key');
```

**Client Plugin:**
```php
// Stored in wp_options after activation
sseo_ai_client_license: 'SSEO-XXXX-XXXX-XXXX-XXXX'
sseo_ai_client_tenant: 'tenant_abc123'
sseo_ai_client_dashboard_url: 'https://saas.example.com'
```

## Performance Considerations

### Caching Strategy

1. **License Validation Cache**
   - Cache validation results for 1 hour
   - Revalidate on plugin settings page

2. **SERP Data Cache**
   - Cache SERP results for 24 hours
   - Shared across all tenants for same keyword

3. **AI Response Cache**
   - Optional: Cache common prompts
   - Reduces API costs

### Rate Limiting

```php
// Per-tenant limits (examples)
$limits = [
    'free' => ['api_calls' => 100, 'rate_per_minute' => 5],
    'starter' => ['api_calls' => 1000, 'rate_per_minute' => 10],
    'professional' => ['api_calls' => 5000, 'rate_per_minute' => 20],
    'agency' => ['api_calls' => -1, 'rate_per_minute' => 50], // unlimited
];
```

## Monitoring & Analytics

### Metrics to Track

**SaaS Dashboard:**
- Active licenses count
- API calls per tenant
- API costs per tenant
- Error rates
- Response times

**Client Plugin:**
- Feature usage statistics
- Content generated count
- Keywords tracked
- Performance improvements

### Logging

```php
// SaaS logs
error_log('SSEO AI Dashboard: License activation - Tenant: ' . $tenantKey);
error_log('SSEO AI Dashboard: API call - Cost: $' . $cost);

// Client logs
error_log('SSEO AI: License activated successfully');
error_log('SSEO AI: AI content generated - ' . $wordCount . ' words');
```

## Troubleshooting

### Common Issues

1. **"No route found" error**
   - Flush rewrite rules: Settings → Permalinks → Save
   - Check REST API is enabled

2. **"Invalid license" error**
   - Verify license key is correct
   - Check license status in SaaS Dashboard
   - Ensure dashboard URL is correct

3. **White-label not applying**
   - Deactivate and reactivate license
   - Check white-label settings in SaaS Dashboard
   - Clear browser cache

4. **Division by zero errors**
   - All fixed in v1.0.4+
   - Update to latest version

## Version History

- **v1.0.8** - UI modernization, permission fixes, database error fixes
- **v1.0.7** - Dashboard card link fixes
- **v1.0.6** - White-label sync implementation
- **v1.0.5** - SaaS features removed from client
- **v1.0.4** - Division by zero error fixes
- **v1.0.3** - REST API namespace fixes
- **v1.0.2** - Menu registration fixes
- **v1.0.1** - Initial release

## Future Enhancements

- [ ] Webhook notifications for license events
- [ ] Advanced analytics dashboard
- [ ] Multi-language support
- [ ] Custom AI model training per tenant
- [ ] Advanced white-label customization
- [ ] Client portal for end-users
- [ ] Automated reporting
- [ ] Integration marketplace

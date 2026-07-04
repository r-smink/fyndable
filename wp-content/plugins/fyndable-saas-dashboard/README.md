# SSEO AI SaaS - Plugin Split

This is a refactored version of the AI SEO Assistant plugin, split into two separate plugins for SaaS deployment:

## Plugin Structure

### 1. ai-seo-saas-dashboard (SaaS Dashboard)
**Location:** `/wp-content/plugins/ai-seo-saas-dashboard/`

**Purpose:** Runs on the main SaaS portal. Manages tenants, licenses, and provides admin interface.

**Key Components:**
- `TenantRepository` - Multi-tenant data management
- `LicenseKeyGenerator` - Self-hosted license generation
- `LicenseAdmin` - Admin UI for license/tenant management  
- `LicenseAPI` - REST API endpoints for client communication

**REST API Endpoints:**
- `POST /wp-json/ai-seo-saas/v1/license/validate` - Validate license key
- `POST /wp-json/ai-seo-saas/v1/license/activate` - Activate license (creates tenant)
- `POST /wp-json/ai-seo-saas/v1/license/deactivate` - Deactivate license
- `POST /wp-json/ai-seo-saas/v1/tenant/status` - Check tenant status/limits
- `POST /wp-json/ai-seo-saas/v1/usage/report` - Report usage metrics

**Database Tables:**
- `{prefix}sseo_ai_tenants` - Tenant accounts
- `{prefix}sseo_ai_tenant_settings` - Tenant-specific settings
- `{prefix}sseo_ai_tenant_usage` - Usage tracking for billing
- `{prefix}sseo_ai_license_keys` - License key storage

### 2. ai-seo-client (Client Plugin)
**Location:** `/wp-content/plugins/ai-seo-client/`

**Purpose:** Installed on customer WordPress sites. Handles license validation and provides core SEO features.

**Key Components:**
- `LicenseValidator` - Validates licenses locally and with dashboard
- `DashboardAPI` - Communicates with SaaS Dashboard REST API
- `Settings` - Configuration management

**Features Available by Tier:**
- Free/Starter: Content analysis, meta optimization
- Professional: + SERP tracking, keyword research
- Business: + Content decay alerts, AI generation
- Agency: + Multi-site management, white label reports

## Installation

### SaaS Dashboard Setup

1. Install `ai-seo-saas-dashboard` on your main WordPress site (the SaaS portal)
2. Activate the plugin - this creates the necessary database tables
3. Navigate to **Licenses** menu in wp-admin to:
   - Generate license keys (test, free, paid, lifetime)
   - View all licenses and their status
   - Manage tenants
   - View usage reports

### Client Plugin Setup

1. Install `ai-seo-client` on a customer WordPress site
2. Activate the plugin
3. Go to **AI SEO > License** in wp-admin
4. Enter:
   - SaaS Dashboard URL (e.g., `https://your-saas-domain.com`)
   - License key (format: `SSEO-AI-XXXX-XXXX-XXXX`)
5. Click **Activate License**

## License Key Format

License keys follow the format: `SSEO-AI-XXXX-XXXX-XXXX`

Example: `SSEO-AI-A1B2C3D4-E5F6G7H8-I9J0K1L2`

## License Types

- **Test** - Internal testing, unlimited usage
- **Free** - Complimentary licenses for marketing/promotions
- **Trial** - Time-limited (e.g., 14 days), auto-expires
- **Paid** - Standard subscription license
- **Lifetime** - Never expires, one-time payment

## Tenant Isolation

The system implements multi-tenancy through:
1. `tenant_id` column added to all data tables
2. Tenant context set via `TenantRepository::setCurrentTenant()`
3. All queries filtered by current tenant
4. Usage tracked per tenant for billing

## API Communication Flow

1. Client plugin sends license key to Dashboard REST API
2. Dashboard validates and creates/returns tenant
3. Client stores tenant key locally
4. Client includes tenant key in all subsequent API calls
5. Dashboard tracks usage and enforces limits per tenant

## Development

### Adding New Features

**Dashboard Plugin:**
- Add admin pages in `LicenseAdmin.php`
- Add REST endpoints in `LicenseAPI.php`
- Modify tenant logic in `TenantRepository.php`

**Client Plugin:**
- Add feature checks using `LicenseValidator::hasFeature()`
- Report usage via `DashboardAPI::reportUsage()`
- Add admin UI in `Client.php`

### Shared Configuration

Both plugins share `includes/shared-config.php` for:
- API version constants
- Database table names
- License format patterns
- Status enums

## Security

- REST API endpoints verify license key + tenant key pairs
- All inputs sanitized with WordPress sanitization functions
- Database queries use prepared statements
- Rate limiting enforced per tenant

## Troubleshooting

**License activation fails:**
- Check Dashboard URL is correct (include https://)
- Verify license key format
- Check that Dashboard plugin is active
- Review REST API is accessible (no 404)

**Client can't connect:**
- Verify SSL certificate on Dashboard site
- Check firewall isn't blocking API requests
- Ensure permalinks are enabled on Dashboard site
- Review Dashboard site error logs

**Usage not tracking:**
- Verify tenant key is stored on client site
- Check `sseo_ai_client_tenant` option exists
- Ensure cron is running for scheduled checks

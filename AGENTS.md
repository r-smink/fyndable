# AGENTS.md — Fyndable local SERP / geo-grid feature

This file captures project-specific information discovered during implementation.

## New SaaS endpoints

`fyndable-saas-dashboard/includes/apigateway.php`

- `POST /ai-seo-saas/v1/serp/local-pack`
  - Body: `{ keyword, latitude, longitude, radius, language, country, search_type: "maps"|"local_finder", target_business_name }`
  - Returns: `{ success, results[], center, own_position, result_count, provider, usage }`
- `POST /ai-seo-saas/v1/serp/local-grid`
  - Body: `{ keyword, latitude, longitude, radius, grid_size (3|5|7|9), language, country, search_type, target_business_name }`
  - Returns: `{ success, results[], center, grid_size, points_scanned, own_presence, own_best_position, provider, usage }`

Primary provider is DataForSEO (`/serp/google/maps/live/advanced` and `/serp/google/local_finder/live/advanced`); SerpAPI (`engine=google_maps`) is used as fallback.

## New client endpoints

`fyndable-client/includes/localserp.php`

- `GET /sseo-ai/v1/local-serp/center` — returns configured business address + coordinates + default radius/grid.
- `POST /sseo-ai/v1/local-serp/scan` — runs local pack / grid via the SaaS dashboard.
  - Body: `{ keyword, latitude, longitude, radius, grid, country, language, business_name }`
  - Requires Professional+ tier.

`fyndable-client/includes/ranktracker.php` has a new **Local SERP** tab that calls the above endpoint.

## Business settings

Local business settings (name, address, coordinates, radius, grid) are now saved via **Settings → Local Business** in `client.php`:

- `sseo_ai_client_local_business_name`
- `sseo_ai_client_local_street` / `local_city` / `local_state` / `local_postal` / `local_country`
- `sseo_ai_client_local_latitude` / `local_longitude`
- `sseo_ai_client_local_search_radius` (default 10 km)
- `sseo_ai_client_local_search_grid` (1 = single, 3/5/7 = grid, tier-limited)

## Tier limits for geo-grid

| Tier     | Max grid | Notes                  |
|----------|----------|------------------------|
| Starter  | n/a      | No local SERP scans.   |
| Professional | 3    | Single + 3x3 grid.     |
| Business | 5        | Up to 5x5 grid.        |
| Agency   | 7        | Up to 7x7 grid.        |

## Admin REST API (for the internal Android management app)

`fyndable-saas-dashboard/includes/adminapi.php` — class `SSEOAISaaS\AdminApi`, registered in `Dashboard::init()` on `rest_api_init`. All routes live under `ai-seo-saas/v1/admin/*` and require an authenticated WordPress user with `manage_options` (use **Application Passwords** / HTTP Basic Auth — built into WP since 5.6).

### License keys
- `GET  /admin/ping` — auth check, returns the authenticated user.
- `GET  /admin/licenses/stats` — license dashboard stats.
- `GET  /admin/licenses` — list (filters: status, type, tier, search, limit, offset).
- `POST /admin/licenses` — generate one or batch (count, type, tier, max_sites, rate_limit, api_calls_limit, expires_days, assigned_to, notes, key_prefix).
- `GET  /admin/licenses/{key}` — single license + associated tenant.
- `POST /admin/licenses/{key}` — update (assigned_to, notes, max_sites, rate_limit, api_calls_limit, license_type).
- `POST /admin/licenses/{key}/revoke` — revoke (body: reason).

### Tenants / usage reports
- `GET  /admin/tenants` — list (filters: status, tier, search, limit, offset).
- `GET  /admin/tenants/{tenant_key}` — tenant detail + usage + limits + onboarding.
- `GET  /admin/tenants/{tenant_key}/usage/history` — monthly usage history (months).
- `GET  /admin/usage` — per-active-tenant usage overview (the "Usage Reports" page data).
- `GET  /admin/revenue/stats` — MRR/ARR/tier breakdown (RevenueDashboard::getStats).

### Support tickets (admin side)
- `GET  /admin/support/tickets` — list all (filters: status, priority, search).
- `GET  /admin/support/tickets/{id}` — ticket detail incl. replies.
- `POST /admin/support/tickets/{id}` — update status/priority.
- `POST /admin/support/tickets/{id}/reply` — staff reply (body: message, screenshots[]).

### GEO Readiness scan
- `POST /admin/geo-scan` — run scan (body: url, keywords[], language). Can take 30-90s.
- `GET  /admin/geo-scan/recent` — recent scans (limit).
- `GET  /admin/geo-scan/{id}` — single scan report.

### AI models
- `GET  /admin/ai-models` — use cases, current standard/premium routing, model lists.
- `POST /admin/ai-models` — save routing (body: standard_routing{}, premium_routing{}).
- `POST /admin/ai-models/refresh` — force-refresh live model list from OpenRouter.

### Setup for the Android app
1. On portal.fyndable.ai, create a dedicated WP user with `manage_options` (e.g. `app-admin`).
2. Generate an Application Password under **Users → Profile → Application Passwords**.
3. The Android app authenticates with HTTP Basic Auth: `Authorization: Basic base64(username:app-password)`.
4. Verify Application Passwords are enabled (default since WP 5.6; can be disabled via the `wp_is_application_passwords_available` filter).

## Verification notes

- PHP CLI **is** available in this environment (`php -l` works).
- Key changed files:
  - `wp-content/plugins/fyndable-saas-dashboard/includes/dataforseoclient.php`
  - `wp-content/plugins/fyndable-saas-dashboard/includes/apigateway.php`
  - `wp-content/plugins/fyndable-saas-dashboard/includes/adminapi.php` (NEW — admin REST API for Android app)
  - `wp-content/plugins/fyndable-saas-dashboard/includes/dashboard.php` (wires AdminApi)
  - `wp-content/plugins/fyndable-client/includes/client.php`
  - `wp-content/plugins/fyndable-client/includes/localseo.php`
  - `wp-content/plugins/fyndable-client/includes/ranktracker.php`
  - `wp-content/plugins/fyndable-client/includes/localserp.php`
  - `wp-content/plugins/fyndable-client/includes/dashboardapi.php`
  - `wp-content/plugins/fyndable-client/includes/llmstxt.php`

## llms.txt + llms-full.txt generator

`fyndable-client/includes/llmstxt.php` serves two endpoints:

- `/llms.txt` — markdown summary with links to posts and pages (+ optional excerpts).
- `/llms-full.txt` — full body text of posts and selected pages (per https://llmstxt.org).

Settings (saved via **Settings → llms.txt Generator** in `client.php`, handled in `handleSettingsSave`):

- `sseo_ai_client_llmstxt_enabled` (bool, default true)
- `sseo_ai_client_llmstxt_post_types` (array, default `['post']`)
- `sseo_ai_client_llmstxt_max_items` (int 1-1000, default 100)
- `sseo_ai_client_llmstxt_description` (string)
- `sseo_ai_client_llmstxt_include_excerpt` (bool, default true)
- `sseo_ai_client_llmstxt_custom_sections` (markdown textarea)
- `sseo_ai_client_llmstxt_full_enabled` (bool, default true) — serve `/llms-full.txt`
- `sseo_ai_client_llmstxt_selected_pages` (array of page IDs; empty = all published pages)
- `sseo_ai_client_llmstxt_full_max_chars` (int 100-500000, default 50000) — max chars per item in full version

Caches: transient `sseo_ai_llmstxt_cache` (summary) and `sseo_ai_llmstxt_full_cache` (full), TTL 6h, invalidated on `save_post`/`delete_post` and on settings save.

REST: `GET /sseo-ai/v1/llmstxt/status` and `POST /sseo-ai/v1/llmstxt/regenerate` now include `full_enabled`, `full_url`, `full_exists`, `full_size`, `selected_pages_count`.

Note: the settings UI is rendered via `do_action('sseo_ai_render_llmstxt_settings')` in `renderSettingsPage()` (placed between "Social & Sharing" and "Advanced Settings").

## Shopify App (Laravel 11)

A separate Laravel 11 application lives in `shopify-app/` and provides the Fyndable SEO app for the Shopify platform. It reuses the SaaS dashboard (portal.fyndable.ai) for licensing, AI, and SERP — it does NOT have its own provider keys.

### Stack
- Laravel 11.56.1, PHP 8.2
- `shopify/shopify-api` v6.1.1 (abandoned — revisit before production)
- SQLite for local dev (switch to MySQL for production)
- Queue jobs for bulk optimization and rank checks

### Key files
- `shopify-app/config/shopify.php` — Shopify + SaaS config
- `shopify-app/app/Services/SaasProxyClient.php` — talks to portal.fyndable.ai (license, AI, SERP)
- `shopify-app/app/Services/LicenseService.php` — license activation/validation (cached 1h)
- `shopify-app/app/Services/ShopifyContentFetcher.php` — Shopify Admin GraphQL (products, collections, pages, blogs)
- `shopify-app/app/Services/LlmsTxtGenerator.php` — generates /llms.txt and /llms-full.txt (cached 6h)
- `shopify-app/app/Http/Controllers/AuthController.php` — Shopify OAuth install + callback
- `shopify-app/app/Http/Controllers/WebhookController.php` — content change + app/uninstalled webhooks
- `shopify-app/app/Http/Controllers/LlmsTxtController.php` — serves llms.txt files + settings API
- `shopify-app/app/Http/Controllers/ProductController.php` — AI descriptions, meta, alt text, JSON-LD schema
- `shopify-app/app/Http/Controllers/BulkOptimizerController.php` — bulk SEO optimization
- `shopify-app/app/Http/Controllers/RankTrackerController.php` — keyword rank tracking
- `shopify-app/app/Http/Controllers/DashboardController.php` — embedded app dashboard
- `shopify-app/app/Http/Controllers/LicenseController.php` — license activation UI
- `shopify-app/app/Http/Middleware/VerifyShopifySession.php` — resolves shop from query/JWT
- `shopify-app/app/Jobs/BulkOptimizeJob.php` — background bulk optimization
- `shopify-app/app/Jobs/CheckRankingsJob.php` — daily rank checks (scheduled 06:00 UTC)
- `shopify-app/resources/views/dashboard/index.blade.php` — App Bridge + Polaris dashboard

### Database tables (migrations in `database/migrations/`)
- `shops` — shop_domain, access_token, scope, license_key, tenant_key, license_tier, is_installed, is_uninstalled
- `tracked_keywords` — shop_id, keyword, url, country, language, last_position, best_position
- `rank_history` — tracked_keyword_id, position, search_engine, result_url, serp_features, checked_at
- `llmstxt_settings` — shop_id, enabled, full_enabled, include_products/collections/pages/blogs, max_*, description, custom_sections

### Routes
- `GET /install` — Shopify OAuth start
- `GET /auth/callback` — OAuth callback (HMAC verified)
- `POST /webhooks` — Shopify webhooks (HMAC verified)
- `GET /dashboard` — embedded app UI
- `GET /api/llms.txt` / `/api/llms-full.txt` — public llms serving (shop via query param)
- `GET /api/dashboard/overview` — dashboard stats
- `GET|POST /api/license/*` — license status/activate/deactivate
- `GET|POST /api/llmstxt/*` — llms.txt settings/status/regenerate/preview
- `POST /api/products/{id}/*` — AI description/meta/alt-text/schema generation
- `POST /api/bulk/*` — bulk optimizer (start/progress/cancel)
- `GET|POST|DELETE /api/rank-tracker/*` — keyword CRUD + rank check

### Shopify App Proxy for llms.txt
Shopify App Proxy serves llms.txt at `/apps/fyndable/llms.txt` (not root `/llms.txt`). The Laravel app exposes `/api/llms.txt?shop=...` which the App Proxy forwards to. A theme app extension or redirect snippet is needed for root-level serving — documented for merchants in the dashboard.

### SaaS dashboard changes (Phase 7)
- `tenantrepository.php` — added `platform` column (wordpress|shopify|webflow) to tenants table + migration in `migrateExistingTables()`
- `licenseapi.php` — `/license/activate` now accepts `platform` parameter
- `licensekeygenerator.php` — `activateLicense()` passes platform to createTenant/updateTenant; `getLicenses()`/`countLicenses()` join with tenants to expose `platform`; added `getLicensePlatformStats()`
- `adminapi.php` — `/admin/tenants` now supports `platform` filter
- `tenantrepository.php` — `getTenants()` supports platform filter; `countTenants()` supports platform + search filters; `updateTenant()` allows platform field
- `licenseadmin.php` — Tenants and All Licenses admin pages show `Platform` column; both pages have a Platform filter; dashboard shows `Licenses by Platform` stats; licenses export includes `Platform`

### Environment variables (shopify-app/.env)
- `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET` — from Shopify Partner Dashboard
- `SHOPIFY_SCOPES` — read_products,write_products,read_content,write_content,read_themes,read_metaobjects,write_metaobjects
- `SHOPIFY_API_VERSION` — 2025-01
- `SAAS_DASHBOARD_URL` — https://portal.fyndable.ai
- `SAAS_API_NAMESPACE` — ai-seo-saas/v1

### Known issues / TODO before production
- `shopify/shopify-api` v6.1.1 is abandoned — consider `shopify/shopify-app-php`
- Composer security advisories were disabled during install — run `composer audit` and fix advisories
- No test suite yet — needs OAuth, webhook, GraphQL, llms, and SaaS proxy tests
- Queue worker must be running for bulk optimization and rank checks (`php artisan queue:work`)
- Scheduler must be running for daily rank checks (`php artisan schedule:run` via cron)
- App Proxy path is `/apps/fyndable/llms.txt` — configure App Proxy + theme app extension in Shopify Partner Dashboard
- SaaS platform filter shows `unknown` for licenses that have not been activated yet

### Security hardening completed
- `ShopifySignature` service verifies OAuth callback HMAC without URL-encoding values (per Shopify spec)
- `ShopifySignature` verifies webhook HMAC over raw request body
- `VerifyShopifySession` verifies App Bridge session tokens via JWKS from `https://{shop}/.well-known/jwks.json` using `firebase/php-jwt` v7


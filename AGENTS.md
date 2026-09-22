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

## Google OAuth proxy (GSC / GA4 / Ads)

The client plugin never sees the OAuth `client_secret` — it lives on the SaaS dashboard. All Google token operations are proxied via `licenseapi.php`:

- `POST /ai-seo-saas/v1/google/oauth-config` — returns `client_id` + scopes for the GIS popup.
- `POST /ai-seo-saas/v1/google/exchange` — exchanges auth code for tokens (`redirect_uri: postmessage`, GIS code-client flow).
- `POST /ai-seo-saas/v1/google/refresh` — **NEW**: refreshes the access token. Client-side `GscOAuth::refresh()` calls this; a direct call to `oauth2.googleapis.com/token` fails with `invalid_client` because Google requires `client_secret` on refresh for Web-app clients.
- `POST /ai-seo-saas/v1/google/ads-dev-token` — Google Ads developer token.
- `GET  /ai-seo-saas/v1/google/oauth-start` — HTML page that runs the GIS popup and postMessages tokens back to the client.

Client side: `gscoauth.php` stores tokens in `aiseoclient_gsc_tokens`; `getAccessToken()` auto-refreshes when expired. `restStoreTokens()`/`exchangeCode()` preserve an existing `refresh_token` when Google doesn't return a new one.

Note: if the Google OAuth app is in "Testing" publishing status, refresh tokens expire after **7 days** — publish/verify the app for persistent connections.

## GEO Readiness scan — async + public website scan (2026-09-22)

Scans run **asynchronously** (fixed gateway 504s): `GeoScanRepository::insertQueued()` → `GeoScanQueue::enqueue()` → WP-Cron `sseo_geo_scan_run_job` → `GeoScanner::scan(..., onProgress, scanId)` writes progress to the row → frontends poll status. `spawn_cron()` kicks processing immediately; a 5-min sweep (`sseo_geo_scan_sweep`) requeues stale jobs and fails stuck 'running' scans. Table `sseo_ai_geo_scans` gained: `progress`, `progress_label`, `error`, `source` ('admin'|'website'), `email`, `consent`, `consent_at`, `meta` (incl. original keywords array — commas safe). Retention: admin 7d, website 90d.

`GeoScanner::scan(url, keywords, language='auto')` — 'auto' triggers `detectLanguageFromKeywords()` (NL/EN stopword heuristic); LLM prompt is language-aware (NL/EN variants).

### Public endpoints (shared-key auth, `X-Fyndable-Scan-Key` header)

`publicapi.php` — class `PublicApi`, key managed via GEO Scan admin page (integratiekaart, `SaaSSettings::getWebsiteScanKey()` / `regenerateWebsiteScanKey()`, option `sseo_ai_saas_website_scan_key`):

- `POST /ai-seo-saas/v1/public/geo-scan` — body `{url, keywords[1-3], email, consent:true}`; dedupe per email (active scans only), IP rate limit 10/h → `201 {scan_id}`
- `GET /ai-seo-saas/v1/public/geo-scan/{id}/status` — `{status, progress, progress_label}` + `teaser` (score, top-3 strengths/weaknesses/findings, keyword flags) when completed — full report stays internal
- `POST /admin/geo-scan` (AdminApi) is now async too: returns `202 {scan_id, status:'queued'}` — poll `GET /admin/geo-scan/{id}`

### Website plugin `fyndable-geo-scan/` (for fyndable.ai)

Standalone plugin: Settings → GEO Scan (portal URL + API key), shortcode `[fyndable_geo_scan]` (URL + 3 keywords + email + consent + honeypot), REST proxy `fyndable/v1/geo-scan` + `…/status`, JS progress bar → teaser result card. wp_mail notification to support email on completed website scans (follow-up).

Note: WP-Cron needs traffic or a real system cron hitting `wp-cron.php` on the portal for reliable processing.

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
- `DELETE /admin/tenants/{tenant_key}` — permanently delete a tenant (body: license_action = keep|free|revoke|delete). Cleans up settings/usage/tickets/feedback; keeps invoices. Blocked when sub-tenants or generated sub-licenses exist. Same logic as the Delete form on the WP-admin Tenants page (`TenantRepository::deleteTenant()`).
- `GET  /admin/tenants/{tenant_key}/usage/history` — monthly usage history (months).
- `GET  /admin/usage` — per-active-tenant usage overview (the "Usage Reports" page data).
- `GET  /admin/revenue/stats` — MRR/ARR/tier breakdown (RevenueDashboard::getStats).

Note: tenant_key URL params must allow underscores (`tn_<hex>`) — the route regex is `[A-Za-z0-9_]+`.

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

## ApexFlow — content autopilot (2026-09-17)

`fyndable-client/includes/apexflow.php` — class `ApexFlow`, replaces `AutomationOrchestrator` (file deleted). Menu slug stays `ai-seo-automation`, label is now "⚡ ApexFlow". **Paid Professional+ only**: `ALLOWED_TIERS = [professional, business, agency, dev]` — deliberately excludes `trial`, unlike the other Pro+ gates.

Pipeline (weekly cron `sseo_ai_apexflow_weekly` + "Run ApexFlow Now"):
1. `scanSiteProfile()` — homepage text + recent posts + `sseo_ai_industry` + Local Business + WooCommerce cats → LLM → `sseo_ai_apexflow_profile` (refreshed when >30d old).
2. `refreshKeywordPool()` — seeds (manual > profile > tracked keywords > GSC top queries > site title) → `KeywordExplorer::expand()` SERP n-grams + `ai/keyword-data` volume/difficulty + `serp/local-pack` local-intent detection (when coords configured). Stored in `sseo_ai_apexflow_keyword_pool` (max 50, refreshed when >7d). Caps per run: 5 seed expansions, 3 local-pack scans, 25 keyword-data items.
3. `buildPlan()` — open slots in lookahead window (publish_days/time, skips dates with existing `future` posts) → batched LLM titles → `sseo_ai_apexflow_plan` (statuses: planned|queued). Preview = `buildPlan(0, false)` — dry-run, not persisted.
4. `enqueuePlan()` — appends items to the shared `sseo_ai_cluster_queues` (source=`apexflow`), generated in background by `TopicCluster::processQueueItems()`.

New queue-item fields honored by `processQueueItems()`: `post_type`, `publish_mode` (`schedule`→future post, `draft`→draft + `_sseo_ai_planned_date` meta), `category_id`, `author_id`, `featured_image` (opt-out), `source` (→ `_sseo_ai_source` meta).

Settings option `sseo_ai_apexflow_settings`: enabled, language, country, posts_per_week (1-7, capped by `getMonthlyAutoPostLimit()`), publish_mode, publish_days[], publish_time, seed_keywords[], excluded_topics[], post_type, category_id, author_id, word_count, featured_image, notify_email, lookahead_weeks (1-8). Legacy `sseo_ai_automation_settings` migrated on first load; `sseo_ai_automation_cron` cleared.

REST (`manage_options`): `GET /sseo-ai/v1/apexflow/status`, `POST /apexflow/run`, `POST /apexflow/preview`, `POST /apexflow/rescan-profile`, `POST /apexflow/reject-keyword`.

Other touched code:
- `keywordexplorer.php` — `expand($seed, $opts)` accepts `country`/`language`, forwarded to `serp/search` (cache key includes them).
- `apigateway.php` (SaaS) — `handleSerpRequest` now accepts `country` (mapped via `countryToLocation`) and `language` (→ DataForSEO `language_code`, SerpApi `hl`). Previously clients sent `country` and it was silently ignored — fixes latent bug for SerpCompetitor/KeywordExplorer too.
- `ai-seo-client.php` — deactivation clears `sseo_ai_apexflow_weekly` + legacy `sseo_ai_automation_cron`.
- Brand Voice quick fields on the ApexFlow page write to shared `sseo_ai_brand_voice` (tone, audience, voice_description, enabled).

Note: WP-Cron only runs on site traffic — production sites should point a real cron at `wp-cron.php` for reliable weekly runs and queue processing.

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
- `GET /api/dashboard/overview` — dashboard stats (incl. `missing_scopes` + `reauth_url`)
- `GET|POST /api/license/*` — license status/activate/deactivate
- `GET|POST /api/llmstxt/*` — llms.txt settings/status/regenerate/preview
- `GET /api/content/{type}` — list/search products|collections|pages|articles (search, limit)
- `GET /api/content/{type}/{id}` — item detail for the editor (meta, body, schema, images)
- `POST /api/content/{type}/{id}/generate-*` — AI meta/description + deterministic schema preview
- `POST /api/content/{type}/{id}/save-*` — save meta/description/schema (per-type write-scope check)
- `POST /api/products/{id}/*` — product-only extras: alt-text, push-all (write_products scope middleware)
- `POST /api/bulk/*` — bulk optimizer (start/progress/cancel)
- `GET|POST|DELETE /api/rank-tracker/*` — keyword CRUD + rank check

### Content SEO editor (2026-09-16)
- `ContentController` — generic `/api/content/{type}/{id}/*` for collection/page/article; delegates product calls to `ProductController`.
- `ShopifyAdminWriter` — all Admin GraphQL mutations; returns `?string` error so real `userErrors` reach the UI. Alt text via `fileUpdate` (not productUpdate — that mutation can't edit existing media).
- `ShopifyContentFetcher::search()` / `getNode()` — per-type search (`query:` arg) + `node(id:)` detail fetch.
- `Shop::missingScopes()` + `shopify.scope` middleware + `missing_scopes` in overview — detects stale tokens (legacy install flow keeps install-time scopes) and points to `/install?shop=...` for re-auth.
- Schemas: `generate-schema` = preview only; `save-schema` writes the (user-editable) JSON to `fyndable.{type}_schema` metafield (json type). Non-product meta saved via `global.title_tag`/`global.description_tag` metafields.
- Theme extension block renders schema metafields for product/collection/page/article.

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

### Queue worker setup (production VPS)
Bulk optimization (`BulkOptimizeJob`) and rank checks (`CheckRankingsJob`) run as queued jobs. Without a running queue worker, these jobs are enqueued but never execute — the API returns success but nothing happens.

**Option A — Supervisor (recommended for production):**
```ini
[program:shopify-queue]
command=php /path/to/shopify-app/artisan queue:work --tries=1 --timeout=1800
directory=/path/to/shopify-app
autostart=true
autorestart=true
user=www-data
```
Install Supervisor (`apt install supervisor`), save to `/etc/supervisor/conf.d/shopify-queue.conf`, then:
```bash
supervisorctl reread && supervisorctl update && supervisorctl start shopify-queue
```

**Option B — Quick fix (small catalogs only):**
Set `QUEUE_CONNECTION=sync` in `.env` so jobs run synchronously within the HTTP request. This makes bulk optimize slow for large catalogs but works without a worker.

**Scheduler (daily rank checks):**
Add to crontab:
```
* * * * * cd /path/to/shopify-app && php artisan schedule:run >> /dev/null 2>&1
```

### Bug fixes (2026-09-15)
- **App Bridge v4 restored** — commit `d87c24a` replaced App Bridge v4 with v2 and removed `createApp()`, breaking all authenticated API calls. Restored v4 CDN with `authenticatedFetch` so the session ID token is sent as `Authorization: Bearer` header.
- **LlmsTxtGenerator error handling** — `generateSummary()` and `generateFull()` accessed `$shopDetails['name']` and `$shopDetails['domain']` with `?:` (ternary) which throws `ErrorException` on missing keys. Fixed to use `??` (null coalescing). Also fixed all `$product['onlineStoreUrl']`, `$collection['title']`, etc. accesses.
- **ShopifyContentFetcher error handling** — `getShopDetails()` returned `[]` on error, causing downstream 500s. Now returns sensible defaults. `getProductCount()` now checks for errors before accessing `$response['data']`.
- **Deprecated GraphQL argument removed** — `metafields(first: 10, namespace: "seo")` in the products query had a deprecated `namespace` argument. Removed to avoid GraphQL errors on newer API versions.

### Security hardening completed
- `ShopifySignature` service verifies OAuth callback HMAC without URL-encoding values (per Shopify spec)
- `ShopifySignature` verifies webhook HMAC over raw request body
- `VerifyShopifySession` verifies App Bridge session tokens via JWKS from `https://{shop}/.well-known/jwks.json` using `firebase/php-jwt` v7


### Bug fixes / changes (2026-09-17)
- **articleCreate author required** — API version 2026-07 makes `ArticleCreateInput.author` (AuthorInput: `name` or `userId`) required; `BlogWriterController::create()` now always sends `author.name`, using an optional `author` request field with the shop name as fallback. The Blog Writer UI has an optional "Author" field.
- **Blog Writer language selector** — `POST /api/articles/generate` accepts `language` (whitelist in `BlogWriterController::LANGUAGES`, default `en`); the prompt instructs the model to write title + body in that language. UI dropdown in the Blog Writer tab. Other generators (product/collection meta/descriptions) have no selector — they inherit language from existing content/context.
- **Agency tier checkout** — `SignupCheckout::getPlans()` now includes `self_serve` + `contact_url` (mailto to `ai_seo_saas_support_email`) per plan. `signup.js` renders `plan.cta` ("Contact Us") for non-self-serve plans and redirects to `contact_url` instead of opening the trial signup form (previously all plans showed "Start 14-dagen trial" and then hit the backend 403). New i18n key `start_trial` in `assets/i18n.js`.

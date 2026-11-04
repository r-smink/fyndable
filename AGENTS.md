# AGENTS.md — Fyndable local SERP / geo-grid feature

This file captures project-specific information discovered during implementation.

> **Documentation note (2026-10-16):** all user/architecture/setup docs were merged into `FYNDABLE.md` (repo root, Dutch). The old `ARCHITECTURE.md`, `SETUP.md`, `AUDIT-REPORT.md`, `GEBRUIKERSHANDLEIDING.md`, `analyse.md`, `report-claude.md`, `fyndable-faq-homepage-pricing.md` and `tools/UPDATES-SERVER.md` were removed; `README.md` is now just a pointer to `FYNDABLE.md`. This file remains the running technical log.

## Security fixes (2026-10-16 audit round)

- `whitelabelpackagebuilder.php` — `sanitizeCompanyName()` whitelist before injecting agency `company_name` into PHP source (was: raw `str_replace` of a `sanitize_text_field` value → PHP code injection into distributed zips). `extractClientZip()` now rejects `..`/absolute/drive-letter entry paths (zip-slip).
- `licenseapi.php` — `startTrial()` no longer returns the existing `license_key` in its 409 `trial_exists` response (email-only credential leak). `getClientIp()` now uses `REMOTE_ADDR` only (XFF spoofing bypassed all license rate limits). Activation log uses an md5 fingerprint instead of the first 15 key chars.
- `signupcheckout.php` — `restRegister` got an IP rate limit (10/h). `handlePaymentReturn` verifies the stored `mollie_payment_id` with Mollie before `cleanupPendingSignup` on cancel/failed/expired; `cleanupPendingSignup` re-reads the tenant row and refuses to delete tenants with `paid`/`trial`/`active` payment_status.
- `bookkeepingadmin.php` — CSV export cells pass through `csvSafe()` (formula-injection guard for `=+-@` and tab/CR prefixes).
- `licensevalidator.php` — cURL fallback no longer follows redirects (credentials could leak to a redirect target).
- `seoreportexport.php` — fixed two fatals: `gatherReportData()` → `getReportData()`, `AISEO_CLIENT_VERSION` → `SSEO_AI_CLIENT_VERSION`.
- `redirectionmanager.php` — registered the missing `admin_post_sseo_ai_redirect_export` handler (nonce + `manage_options`) for the previously dead Export CSV link.

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

`GeoScanner::scan(url, keywords, language='auto', onProgress, scanId, source)` — 'auto' triggers `detectLanguageFromKeywords()` (NL/EN stopword heuristic); LLM prompt is language-aware (NL/EN variants). `source` ('admin'|'website') selects the model: admin scans use `sseo_ai_saas_geo_model` (Settings → "GEO Scan Model"), website scans use `sseo_ai_saas_geo_website_model` (GEO Scan admin → integratiekaart → "Model (website-scan)"; empty = fall back to the admin model). Saved via `admin_post_sseo_geo_scan_save_website`.

### Multi-model GEO scan (2026-09-23)

When enabled, the GEO scan runs the same page through multiple AI models sequentially, each with a model-specific prompt addition. Results are merged into a single report.

**Settings** (managed via GEO Scan admin → "Multi-model GEO Scan" card, saved via `admin_post_sseo_geo_scan_save_multi_model`):
- `sseo_ai_saas_geo_multi_enabled` (bool) — multi-model for admin scans
- `sseo_ai_saas_geo_multi_models` (array of model IDs) — which models to use
- `sseo_ai_saas_geo_website_multi_enabled` (bool) — also for website scans (requires admin multi-model to be on)

**Architecture:**
- `GeoScanner::analyzeWithMultipleModels()` — loops sequentially over selected models, calls `analyzeWithLlm()` per model with a `$modelOverride`
- `GeoScanner::getModelSpecificPromptAddition(model, language)` — per model-family (openai/google/anthropic) appends a focused evaluation section: ChatGPT → Bing/conversational, Gemini → Knowledge Graph/organic top-10, Claude → entity clarity/conservative name-dropping
- `GeoScanner::mergeMultiModelResults()` — averages scores/breakdown, merges+deduplicates findings/recommendations/strengths/weaknesses (60% word-overlap threshold), sums usage/cost
- `GeoScanner::detectModelFamily(model)` — maps model ID to family (openai/google/anthropic/other)
- Report JSON gains `multi_model` (bool) and `models_used` (array of `{model, score}`)
- `GeoScanReport` renders a "Geanalyseerd door X modellen" badge with per-model score chips when `multi_model` is true

**Graceful degradation:** if a model fails, it's skipped and logged (`error_log`). If all models fail → `WP_Error('all_models_failed')`. If only 1 succeeds → returned as single-model result.

**Backward compatible:** toggle off = exact same behavior as before. No database schema changes (report JSON is `longtext`).

### Public endpoints (shared-key auth, `X-Fyndable-Scan-Key` header)

`publicapi.php` — class `PublicApi`, key managed via GEO Scan admin page (integratiekaart, `SaaSSettings::getWebsiteScanKey()` / `regenerateWebsiteScanKey()`, option `sseo_ai_saas_website_scan_key`):

- `POST /ai-seo-saas/v1/public/geo-scan` — body `{url, keywords[1-3], email, consent:true}`; one active scan per email: an existing non-expired non-failed website scan returns `409 already_scanned` (no id/token — security-patch-1 N7); IP rate limit 10/h → `201 {scan_id}` for new scans
- `GET /ai-seo-saas/v1/public/geo-scan/{id}/status` — `{status, progress, progress_label}` + `teaser` (score, top-3 strengths/weaknesses/findings, keyword flags) when completed — full report stays internal
- `POST /admin/geo-scan` (AdminApi) is now async too: returns `202 {scan_id, status:'queued'}` — poll `GET /admin/geo-scan/{id}`

### Website plugin `fyndable-geo-scan/` (for fyndable.ai)

Standalone plugin: Settings → GEO Scan (portal URL + API key), shortcode `[fyndable_geo_scan]` (URL + max 2 keywords + email + consent + honeypot), REST proxy `fyndable/v1/geo-scan` + `…/status`, JS progress bar → teaser result card. wp_mail notification to support email on completed website scans (follow-up). Progress bar is smoothed client-side (creeps ≤12% ahead of server value, cap 96% until completed); poll timeout is 10 min (`MAX_POLL_MS` in geo-scan.js).

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

- PHP CLI is **not** on PATH in this workspace (`php` not found); `perl` is available. `tools/po2mo.pl` regenerates `.mo` files — equivalent to `languages/generate-mo.php`.
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
2. `refreshKeywordPool()` — seeds (manual > profile > tracked keywords > GSC top queries > site title) → `KeywordExplorer::expand()` SERP n-grams (Unicode tokenization, segment-bounded bigrams) + LLM relevance filter (`selectRelevantCandidates`, language-aware) + `ai/keyword-data` volume/difficulty + `serp/local-pack` local-intent detection (when coords configured). Quality gates: `isUsableKeyword()` requires ≥2 meaningful words + rejects foreign-stopword markers (seeds exempt: manual seeds bypass entirely, other seeds min 1 word); candidates not recognized by `ai/keyword-data` are dropped (seeds exempt; gate skipped when the API call fails — `fetchKeywordData` returns `?array`, null on error). Stored in `sseo_ai_apexflow_keyword_pool` (max 20, refreshed when >7d or when a stored pool exceeds POOL_SIZE). Caps per run: 5 seed expansions, 3 local-pack scans, 25 keyword-data items.
3. `buildPlan()` — open slots in lookahead window (publish_days/time, skips dates with existing `future` posts) → batched LLM titles → `sseo_ai_apexflow_plan` (statuses: planned|queued). Preview = `buildPlan(0, false)` — dry-run, not persisted.
4. `enqueuePlan()` — appends items to the shared `sseo_ai_cluster_queues` (source=`apexflow`), generated in background by `TopicCluster::processQueueItems()`.

New queue-item fields honored by `processQueueItems()`: `post_type`, `publish_mode` (`schedule`→future post, `draft`→draft + `_sseo_ai_planned_date` meta), `category_id`, `author_id`, `featured_image` (opt-out), `source` (→ `_sseo_ai_source` meta).

Settings option `sseo_ai_apexflow_settings`: enabled, language, country, posts_per_week (1-7, capped by `getMonthlyAutoPostLimit()`), publish_mode, publish_days[], publish_time, seed_keywords[], excluded_topics[], post_type, category_id, author_id, word_count, featured_image, notify_email, lookahead_weeks (1-8). Legacy `sseo_ai_automation_settings` migrated on first load; `sseo_ai_automation_cron` cleared.

REST (`manage_options`): `GET /sseo-ai/v1/apexflow/status`, `POST /apexflow/run`, `POST /apexflow/preview`, `POST /apexflow/rescan-profile`, `POST /apexflow/reject-keyword`, `POST /apexflow/clear-plan`, `POST /apexflow/clear-pool`, `POST /apexflow/refresh-pool`.

Admin reset buttons (admin-post handlers + REST parity): "Clear Planned Posts" → `clearPlanAndQueue()` deletes `sseo_ai_apexflow_plan`, sets pending `source=apexflow` items in `sseo_ai_cluster_queues` to `cancelled` (items already `processing` finish; generated posts untouched), and frees pool keywords `planned`→`new`. "Clear Keyword Pool" deletes `sseo_ai_apexflow_keyword_pool` (lazy rebuild on next run/preview); "Refresh Pool Now" calls `refreshKeywordPool()` synchronously (costs SERP/AI credits). Activity log keeps the last 50 entries (`LOG_KEY`) and renders in a `.sseo-apexflow-log` scroll container.

Other touched code:
- `keywordexplorer.php` — `expand($seed, $opts)` accepts `country`/`language`, forwarded to `serp/search` (cache key includes them).
- `apigateway.php` (SaaS) — `handleSerpRequest` now accepts `country` (mapped via `countryToLocation`) and `language` (→ DataForSEO `language_code`, SerpApi `hl`). Previously clients sent `country` and it was silently ignored — fixes latent bug for SerpCompetitor/KeywordExplorer too.
- `ai-seo-client.php` — deactivation clears `sseo_ai_apexflow_weekly` + legacy `sseo_ai_automation_cron`.
- Brand Voice quick fields on the ApexFlow page write to shared `sseo_ai_brand_voice` (tone, audience, voice_description, enabled).

Note: WP-Cron only runs on site traffic — production sites should point a real cron at `wp-cron.php` for reliable weekly runs and queue processing.

## Global AI loader — animated Fyndable logo (2026-09-28)

`fyndable-client/assets/fyndable-loader.js` — self-contained vanilla-JS loader ported from `preloader.html` (bundled React artifact; source of truth for the animation). The Fyndable logo ring is "drawn" via an SVG `<mask>` wedge whose `d` is updated per frame by a shared `requestAnimationFrame` loop: 1.2s eased draw → 450ms hold → 0.5s erase → 250ms gap → loop. Dots under the label cycle `.`/`..`/`...` every 450ms.

- **Overlay**: `#sseo-ai-loader-overlay` (dark `rgba(0,0,0,.6)` + blur backdrop) created lazily; label from `fyndableLoaderConfig.text` (`'AI is generating'` + animated dots). Public API unchanged: `window.sseoShowLoader()` / `sseoHideLoader()` — now backed by a pending counter + 300ms show delay (no flash on fast calls). Each show-source increments; matching completions decrement.
- **Triggers**: jQuery `ajaxSend` (data contains `sseo_ai_`/`action=ai_seo`, matched again on `ajaxComplete`/`ajaxError` via `xhr.__fynLoader`), `wp.apiFetch` wrapper (`sseo-ai/v1` paths), `window.fetch` wrapper (`/sseo-ai/v1/` URLs). `support-assistant` paths are excluded — the chat widget has its own typing UI.
- **Spinner upgrade**: a `MutationObserver` replaces every `.spinner`/`.fyndable-spinner` inside `fyndableLoaderConfig.scopeSelector` with an inline logo SVG (unique gradient/mask ids per instance, whitelabel `colors` when configured). Elements keep their own size; `.fyn-upgraded` CSS reset kills the WP border/background spinner. Animation runs only while an instance is visible (computed style + `.hidden` ancestor check).
- **Enqueue points**: `client.php::enqueueAssets()` (all `ai-seo`/`fyndable` pages, scope = whole document) and `postmetabox.php` (post.php/post-new.php/attachment.php + `enqueue_block_editor_assets` for iframed editors, scope = `#fyndable_seo_meta, #fyndable_seo_meta_attachment` so WP core spinners stay untouched).
- `mobile-app-template.html` embeds a compact copy of the same engine (`.spinner` → logo) for the Android app.
- `.mo` regenerated via `tools/po2mo.pl` (new msgid "AI is generating" → "AI is aan het genereren").

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

## Feature inventory + security audit (2026-10-09)

### fyndable-client (~115 modules, `includes/`)
- **Core SEO**: TruSEO score, post metabox, readability, schema markup (FAQ etc.), breadcrumbs, canonicals, Open Graph, robots.txt, sitemaps (incl. extended/video), hreflang/international, redirection manager, 404 monitor, technical SEO auditor, robots/indexing (IndexNow, directindex), WooCommerce SEO, pagebuilder helper, smart tags, smart internal linking (linkassistant).
- **Content**: content writer/rewriter/optimizer, editor assistant, content brief, content calendar, ideas, topic clusters, programmatic SEO, A/B testing, AI repurposer, simple content generator, bulk actions, content decay + performance monitor, fact checker, plagiarism checker, E-E-A-T validator, brand voice, GEO content score, LSI keywords, prompt template library, image alt + AI image generator, AiseoAgent.
- **Keywords/rank**: keywords DB, rank tracker, keyword explorer + difficulty, competitor research, SERP competitor, SERP change monitor, SERP feature tracker, local SERP/geo-grid, local SEO.
- **AI/GEO**: GEO readiness scans (via SaaS), llms.txt + llms-full.txt generator, LLM tracker, brand visibility tracker, AI optimization tracker.
- **Data/integrations**: GSC/GA4/Google Ads dashboards (OAuth proxy via SaaS), PageSpeed, Ahrefs + SE Ranking + DataForSEO backlink clients, backlink analyzer, external integrations, multi-CMS publisher, mobile app template.
- **Automation**: ApexFlow content autopilot, post auto-cleaner, alert notifier, health logger, review prompt, onboarding wizard, demo mode, trial cleanup, role permissions, white-label manager, privacy export (GDPR), support tickets + assistant, SEO importer, SEO revisions + snapshots, report data collector + SEO report export.
- **Special**: auditservice incl. cannibalization check; topiccluster also blocks cannibal posts.

### fyndable-saas-dashboard (~45 modules)
- Licensing (generator, API, feature manager), tenants, subscriptions (Stripe + Mollie, signature-verified webhooks), invoicing, revenue dashboard, bookkeeping admin.
- API gateway: LLM proxy (OpenRouter/OpenAI adapters), SERP (DataForSEO + SerpAPI), keyword data, image gen (OpenArt), GEO scanner (multi-model, async queue + sweeper).
- Public surface: license API (rate-limited), public geo-scan (shared key + IP rate limit), signup/checkout (self-serve gated), webhooks, update server (license+tenant verified).
- Admin REST API (manage_options, for Android app), agency portal (white-label zip builder, custom role/caps), customer portal, email automation (templates + block renderer), feedback, support tickets, GEO scan admin.

### fyndable-geo-scan
- Standalone website plugin: shortcode form → SaaS public API, teaser results, wp_mail follow-up.

### Security audit findings (2026-10-09)
Good: hash_equals on shared key, Stripe signature verify fail-closed, Mollie fetch-back verify, orderby whitelists, SSRF private-range blocking in HtmlFetcher, rate limits on license/public endpoints, no hardcoded secrets, capability checks on all admin REST routes.
Watchlist — resolved 2026-10-09:
- license_key comparisons → `hash_equals` (licenseapi, apigateway, updateserver, feedback, supporttickets)
- Google refresh_token encrypted at rest in `aiseoclient_gsc_tokens` (`enc1:` = AES-256-GCM, key derived from wp_salt; legacy plaintext decrypts transparently)
- agency logo upload → `wp_check_filetype_and_ext` (real mime check)
- A/B conversion endpoint already had rate-limit (30/min/IP) + per-session dedup
- website GEO scans get unguessable `public_token` in meta; `GET /public/geo-scan/{id}/status` requires `?token=` when a token is stored (scans predating this still work). geo-scan plugin passes `scan_token` through, localStorage resume carries it.
- client zip uploads get random filename suffix (`fyndable-client_v{ver}_{rand10}.zip`)
- HtmlFetcher `isUrlSafe` now blocks literal-IP hosts incl. bracketed IPv6 (note: no server-side fetch happens — Jina/Firecrawl fetch remotely)
- geo-scan print/PDF: light print theme, report header (brand + URL + date), footer, `position:static` for multi-page flow, print-only chrome via `.fgs-print-head/.fgs-print-foot`

## Cannibalization dashboard (2026-10-09)

New third tab **"Cannibalization"** on the Rank Tracker page (`ai-seo-ranks`), implemented inside `ranktracker.php`:

- `GET /sseo-ai/v1/cannibalization/report` — cached report (transient `sseo_ai_cannibal_report`, 6h, auto-scans when empty)
- `POST /sseo-ai/v1/cannibalization/scan` — force rescan
- `RankTracker::buildCannibalizationReport()` detects:
  - `same_focus_keyphrase` (high) — ≥2 posts/pages sharing `_sseo_ai_focus_keyphrase`, site-wide
  - `tracked_keyword_multi_match` (high/medium) — active tracked keyword matching >1 post via focus meta or title; marks `is_target` vs `sseo_ai_tracked_keywords.post_id`
  - `tracked_keyword_no_match` (info) — tracked keyword with no targeting post
- Report shape: `{success, scanned_at, summary{high,medium,info}, groups[]}`; each group has `keyword, severity, reason, posts[] {id,title,status,type,edit_link,permalink,is_target,match_type}, tracked`
- UI: lazy-loads on first tab open, severity chips + per-group post tables with Edit/View links and advice per reason. JS lives in the existing renderPage() script block (jQuery + wp.apiFetch).

Removed dead code: `auditservice.php` (AuditService) was instantiated in client.php but never called — deleted file + references (also from README feature table). Cannibalization now lives in RankTracker.

Note: `php` CLI **is** available in this workspace (`php -l` works) — earlier note about PHP missing was wrong.

## GEO prospect PDF + AI-platform benchmark (2026-10-12)

The SaaS GEO scan now tests up to the first 3 keywords via DataForSEO LLM Responses for ChatGPT, Gemini and Perplexity. Google AI Overview continues to use all successful keyword lookups. `GeoScanner` stores normalized `platform_visibility` and `competitive_benchmark` fields in the existing result JSON; failed platform calls degrade to `unavailable` rather than a false negative. The live benchmark compares own-domain and cited competitor visibility within the successful test set.

`GeoScanPdf` (`includes/geoscanpdf.php`) renders the completed scan on demand with Dompdf 3.1.6, bundled under the plugin-local `vendor/` directory. The protected `admin-post.php?action=sseo_geo_scan_download_pdf` action requires `agency_geo_scan`/`manage_options` plus a scan-specific nonce. The report/download pages open a cover-selection dialog with `cover=light|dark`; both versions contain the same prospect-safe scan data and exclude lead details, consent, API errors, usage and cost.

New scans may take longer because of up to 9 additional live platform requests. `GeoScanQueue` lock/stale-running limits are 20 minutes. Verification commands: `php -l` on changed PHP files, `node --check assets/geo-scan-admin.js`, and plugin-local Composer validation through the available Composer PHAR or a system Composer installation.

## Custom plans (per-klant agency-tier pricing, 2026-10-16)

`fyndable-saas-dashboard/includes/customplan.php` — class `CustomPlan`: per-tenant pricing config in `tenant_settings.custom_plan` (`price_per_license`, `discounts[{min,pct}]`, `max_licenses`, `initial_licenses`, `name`). Monthly amount = `paid_licenses × price × (1 − hoogste toepasselijke korting)`; the linked agency account's `max_sub_licenses` doubles as the paid-license count. Consent log in `tenant_settings.custom_plan_consent_log` (max 200 entries, incl. user/IP/bedragen/nextPaymentDate).

- Admin UI: License Admin → Agency Accounts → "Create Custom Plan" + per-plan Edit / Payment Link / Sync Subscription / Consent log (`licenseadmin.php`, `admin_post_sseo_ai_custom_plan_*`, `manage_options` + nonces).
- Mollie: `PaymentProcessor::createCustomPlanCheckout()` (sequenceType=first, metadata `type=custom_plan`, `license_count`) → mandate; `createMollieSubscription()` computes the plan amount **before** `getTierPricing` (custom plans don't depend on agency tier pricing) and sets `max_sub_licenses`. `setMollieSubscriptionAmount()` is the absolute PATCH variant of the existing additive `updateMollieSubscriptionAmount()`.
- Portal: Agency Portal → "Extra Licenses" (`sseo-ai-agency-add-licenses`) branches to the custom-plan page: activation card when `payment_status != 'active'`, otherwise price table + add-licenses form with **verplichte consent-checkbox** + live JS herberekening (nieuw maandbedrag + korting). `handleAddLicenses` → `handleCustomPlanAddLicenses`: consent → PATCH absoluut bedrag → quota omhoog → consent-log.
- Webhook (`webhookhandler.php`): `type=custom_plan` loopt via de generieke `sequenceType=first` → `createMollieSubscription`; bij niet-`paid` status wordt `cleanupPendingSignup` **overgeslagen** voor custom-plan tenants (anders verdwijnen tenant + plan bij een mislukte eerste betaling).
- Vertalingen: `generate_nl_translations.ps1` scant nu ook `esc_html_e`/`esc_attr_e`/`_e` (1176 extra strings) en merge't bestaande `.po`-vertalingen als fallback; `$finalTranslations` is een case-sensitive `Dictionary` ('Licenses' vs 'licenses' zijn aparte msgids). Let op: msgids met `%1$d`-placeholders MOETEN single-quoted in de dictionary (`$d` interpoleert anders als variabele).
- Verificatie: `C:\Devtools\php\php.exe -l` (PHP binary staat niet op PATH maar bestaat wel).

## Backlinks — echte data voor Advanced Backlinks / Competitor Research (2026-10-23)

- **DataForSEO-pad gefixt** (SaaS `dataforseoclient.php` + client `dataforseobacklinksclient.php`): target zat in het URL-pad (`/backlinks/{domain}/backlinks/live`) → nu `/v3/backlinks/backlinks/live` en `/v3/backlinks/summary/live` met `target` in de body, `rank_scale: one_hundred` (DR 0-100). Summary-DR komt uit `rank` (was `domain_rank`). De backlinkcalls gaven daarvoor geen data. **Portal moet gedeployed worden** voordat de proxy werkt.
- `apigateway.php` `handleBacklinksLive` laat nu ook `mode` (as_is|one_per_domain|one_per_anchor), `backlinks_status_type` en `order_by[]` (array) door; limit/offset worden begrensd.
- `BacklinkAnalyzer` is de gedeelde databron: `getBacklinkItems($domain, $query)` levert genormaliseerde links (source_url/domain, target_url, anchor_text, domain_rating, link_type, item_type, attributes, page_title, spam_score, is_broken, target_status, first_seen), met DataForSEO-filtering wanneer `$query` gezet is (cache 24u, negatief 15 min). Daarnaast `getLinkGap()` (one_per_domain vs. eigen domein, spam ≥50 eruit), `classifyLink()`, `getCompetitorDomains()`, `normalizeDomain()`. Ahrefs/SE Ranking-profielen lopen via dezelfde `normalizeLink()`/`buildProfile()`.
- Concurrenten: `sseo_ai_add_competitor` / `sseo_ai_remove_competitor` (max 5, option `sseo_ai_competitor_domains` = `[{domain, added_at}]`; legacy strings worden ook gelezen). Voorheen bestond geen handler, dus de option bleef altijd leeg.
- Toxic: score = `backlink_spam_score` + 50 voor spam-ankers (casino/porn/viagra…) + 15 bij DR<5; toxic vanaf 50. Output bevat nu `toxicity` + `reason` (de UI verwachtte die al).
- `POST /sseo-ai/v1/backlinks/analyze` (`restAnalyzeBacklinks`) bestaat nu; `GET /backlinks/opportunities` geeft de link gap (optioneel `competitor`, `keyword`).
- `AdvancedBacklinks` (krijgt `BacklinkAnalyzer` geïnjecteerd): broken links via DataForSEO-filter `is_broken = true` (geen HEAD-requests meer), competitor targets = link gap over alle concurrenten, ankeranalyse op eigen links met merktermen (sitenaam/domein) + doelkeywords uit actieve getrackte keywords + `_sseo_ai_focus_keyphrase` (was hardcoded), AI-aanbevelingen gecachet (1 week). Nieuwe handler `sseo_ai_generate_outreach` (LLM; e-mail via `.val()` in de textarea gezet).
- `CompetitorResearch` (krijgt `BacklinkAnalyzer`): echte categorisering, topbronnen met DR, ankerverdeling, samenvatting op basis van data; nieuwe handler `sseo_ai_get_backlink_strategy` (HTML-fragment). Andere tab-handlers (`sseo_ai_get_keyword_strategy`, `_content_calendar`, `_win_loss`, `_ad_copy`) bestaan nog steeds niet.
- Nog open: `sseo_ai_disavow_link` heeft geen handler; geen historie of tracking over tijd (punt 2/3 van het backlinkplan).

## Security patch 1 (2026-10-23)

Volledig verslag: `security-patch-1.md` (repo root). Kort:
- `/license/trial` en `/signup/activate` geven geen `license_key`/`tenant_key` meer in de response; de key gaat alleen via de welkomstmail. Trial-respons is identiek voor nieuwe/bestaande e-mails. Duplicaatchecks via `TenantRepository::findTenantsByEmail()` (geen `getAllTenants(500)`-loop meer).
- `getImageApiData()` stuurt `sseo_ai_saas_openrouter_image_api_key` (Settings → Image, aparte gelimiteerde key) mee als die gezet is, anders nog de centrale OpenRouter-key — **centrale key roteren + image-proxy bouwen staat open**.
- Client-activatie volgt alleen redirects binnen de dashboard-host (`DashboardAPI::isSameDashboardHost()`); cURL-fallback volgt geen redirects.
- Custom plans: `GET_LOCK` rond add-licenses, qty 1-100 server-side, `createMollieSubscription()` idempotent per first payment (`mollie_subscription_source_payment`), dashboard-return maakt geen tweede abonnement.
- `HtmlFetcher::isUrlSafe()`: alleen http(s), volledige IPv6-check + AAAA. Competitor-sitemap-fetches via `wp_safe_remote_get`. SerpAPI/Places-query-args via `rawurlencode_deep()`. Support-screenshots alleen afbeeldingen. OAuth-popup: geen `'*'`-fallback meer.

## Audit-ronde 2026-10-29

- **Image-API key** (`licenseapi.php`): `getTenantStatus` stuurt `image_api` alleen nog mee bij een actieve, niet-verlopen tenant met een niet-ingetrokken licentie (anders `[]`). `activateLicense` vereist alleen een geldige `license_key` (geen tenant_key) — comments gecorrigeerd. Client wist een eerder opgeslagen key niet bij een lege waarde; rotatie + image-proxy staan nog open.
- **SSRF competitor research** (`fyndable-client/includes/competitorresearch.php`): alle fetches via `safeRemoteGet()` → `isPublicUrl()` (http(s), alle A/AAAA-records publiek; blokkeert ook 169.254/16, IPv6-lokaal, 100.64/10 die core's `wp_http_validate_url` doorlaat). Redirects handmatig gevolgd (max 3), elke hop gecontroleerd. Capability blijft `edit_posts`. DNS-rebinding (TOCTOU) niet afgedekt.
- **Schema-migraties version-gated** (`dashboard.php`): `Dashboard::upgradeSchema()` draait alleen bij activatie of wanneer option `sseo_ai_saas_db_version` ≠ `SSEO_AI_SAAS_VERSION` (update-server-updates vuren geen activation hook). Onvoorwaardelijke `maybeCreateTables`/`migrateExistingTables` uit `init()` en de `admin_init`-hooks van GeoScanRepository/Feedback verwijderd. **Nieuwe migratie toevoegen = pluginversie bumpen.**
- **Tier-limieten één bron**: maandelijkse API-limieten komen uit de billing-opties (`SaaSSettings::getApiLimitForTier`, `ai_seo_saas_{tier}_api_calls`); `LicenseKeyGenerator::getDefaultApiLimit()` delegeert daarheen (`TIER_API_LIMITS` verwijderd). Rate limits: alleen `LicenseKeyGenerator::TIER_RATE_LIMITS`. `SignupCheckout::getTierLimits()` gebruikt beide; alleen `max_sites` is checkout-specifiek (ongebruikte `geo_scan_limit` verwijderd). Bestaande tenants houden hun opgeslagen limieten.
- **SMTP** verplaatst naar `SmtpConfigurator` (zelfde hooks/opties), geregistreerd in `Dashboard::init()`.

## Brand Style References — type, edit policy, image mode (2026-10-29)

- Settings → Brand Style References (`client.php`): per afbeelding `type` (logo|person|environment|product|style) én `policy` (free|minimal|locked = Vrij/Beperkt/Beschermd, zelfde instructies als FynnAI `EDIT_POLICIES`) in option `sseo_ai_client_photo_portfolio`. Bugfix: type werd nooit opgeslagen (JS zocht hidden field op een ontbrekend `data-idx`) — selects posten nu zelf; nieuwe items via `<template id="photo-portfolio-template">`.
- Option `sseo_ai_client_brand_ref_mode`: `describe` (default — vision-beschrijving per afbeelding in de prompt; locked/minimal krijgen een gedetailleerde beschrijving) of `images` (geen vision-calls; afbeeldingen gaan als `input_references` mee naar OpenRouter `/api/v1/images`, base64 data-URL uit de 'large'-size, ≤4 MB, anders publieke URL; SVG overgeslagen; max 6, locked→minimal→free). Per afbeelding een regel "Reference image N (type) <policy>" achter de prompt. Faalt het model met referenties → één retry zonder. Andere providers (OpenAI/Stability/OpenArt) krijgen geen afbeeldingen.
- Alleen `generateFeaturedImage`/`generateSocialImages` geven referenties mee (`generateImageFromPrompt($prompt, true)`); editor assistant en topiccluster niet.

## Shopify tier-gating (2026-11-04)

Indeling vastgelegd in `shopify-tier-indeling.xlsx` (repo root). Eén bron: `shopify-app/config/tiers.php`.

- `features` → minimale tier per feature; route-middleware `shopify.tier:{feature}` (`RequireTier`) op alle routes in `routes/api.php` behalve dashboard/license/content-list/show. 403 `tier_too_low` (of `license_inactive` bij geen geldige licentie) met `required_tier`/`current_tier`. Trial = Professional-niveau. Oude hardcoded `hasTier()` in RankTrackerController verwijderd.
- Starter: llms.txt, IndexNow, meta/beschrijving/alt-tekst/schema. Professional: FAQ, linksuggesties, AI-afbeelding, varianten, rank tracker, keyword explorer, competitor, GEO, AI tracker, insights, markets. Business: blog writer, content calendar, bulk optimizer.
- `limits` → maandlimieten alleen voor Shopify, lokaal geteld in tabel `shop_usage` (`UsageLimiter`): GEO-scans Pro 10 / Business 30 / Agency onbeperkt; blogartikelen (generaties) Business 50 / Agency onbeperkt. Fout `limit_reached`.
- API-calls/maand: SaaS `SaaSSettings::getEffectiveApiLimit($tenant)` — tenants met `platform=shopify` gebruiken `ai_seo_saas_shopify_{tier}_api_calls` (default Starter 500 / Pro+trial 2.000 / Business 5.000 / Agency 20.000; kolom "Shopify API Calls/mo" op Tier Pricing & Limits) i.p.v. de opgeslagen `api_calls_limit`. WordPress ongewijzigd.
- `/dashboard/overview` geeft `plan {tier, features, required, usage}`; de blade zet een slotje op tabs + upgrade-kaart (`TAB_FEATURES` wordt gerenderd uit `config/tiers.php` `tabs`).
- Queue-jobs (`CheckRankingsJob`, `BulkOptimizeJob`) gaan niet door de middleware en checken de tier daarom zelf.

## Shopify App Pricing (0.1, 2026-11-04)

Billing loopt via **Shopify App Pricing** (voorheen Managed Pricing). Geen Billing API (`appSubscriptionCreate` wordt geweigerd) en geen `app_subscriptions/update`-webhook (App Pricing stuurt sinds 28-04-2026 geen abonnementswebhooks meer).

- Plannen in het Partner Dashboard: `starter` (60 USD), `professional` (199 USD), `business` (299 USD). Mapping plan_handle -> tier in `config/shopify.php` `billing.plans`; tiers zelf in `config/tiers.php`. Agency/enterprise blijft buiten de App Store (handmatige sleutel, alleen tier `agency`/`dev`, gecheckt via `/license/validate` voor activatie).
- `PartnerApiClient`: `activeSubscription(appId, shopId)` op `https://partners.shopify.com/{partner_id}/api/2026-10/graphql.json` (header `X-Shopify-Access-Token`, Partner API client met "Manage apps", 4 req/s). Geen statusveld: `null` = geen actief abonnement (nooit, opgezegd of bevroren). Plan = `items[].handle`.
- `ShopifyBillingService::refresh()`: bij elke app-load (overview + `GET /api/billing/status`, cache `check_ttl` 600s), na de plankeuze-redirect (`POST /api/billing/confirm`, `plan_handle` is alleen een hint) en elk uur via `SyncShopBillingJob` (scheduler `shopify-billing-sync`, alleen `billing_state=active`, 3 shops/s). Partner API-fout = laatst bekende status houden.
- Staten (`shops.billing_state`): `trial` (in-app trial), `active`, `none` (plan vereist, dashboard stuurt 1x per sessie naar `https://admin.shopify.com/store/{handle}/charges/fyndable-smart-seo/pricing_plans`). Elke wijziging gaat signed naar de SaaS (`POST /ai-seo-saas/v1/shopify/subscription`, `ShopifyBillingApi`), die de tenant + licentie maakt/bijwerkt (`platform=shopify`, mapping in `tenant_settings.shopify_shop_domain`) of bij none/uninstall op `suspended` zet. Gating blijft via `tenant/status` -> `LicenseService` -> `RequireTier`.
- In-app trial (geen Shopify-trialdagen): `billing.trial_days` (7) dagen vanaf `trial_started_at` of AI-optimalisatie van max. `billing.trial_products` (5) verschillende producten, wat het eerst komt. Na 7 dagen: 402 `trial_expired`, uur-sync pakt verlopen trials op, SaaS-tenant krijgt de einddatum als `expires_at`. Producten geteld in `trial_products` via middleware `shopify.trial` (`TrialProductGuard`) op alle product-AI-routes. Product 6 -> 402 `trial_exhausted` + `trial_ended_at`, state `none`. Een betaald plan zet `trial_ended_at` ook, dus geen nieuwe trial na opzeggen. Trial-tier = `trial` (Professional-niveau in `config/tiers.php`).
- Signing: HMAC-SHA256 over `"{timestamp}.{body}"`, headers `X-Fyndable-Timestamp`/`X-Fyndable-Signature`, 5 min venster. Secret: app `.env` `FYNDABLE_SHOPIFY_SYNC_SECRET` = SaaS `wp-config.php` `FYNDABLE_SHOPIFY_SYNC_SECRET` (of optie `sseo_ai_saas_shopify_sync_secret`).
- `.env` (productie): `SHOPIFY_BILLING_ENABLED=true`, `SHOPIFY_PARTNER_ID=5185442`, `SHOPIFY_APP_ID=423617626113`, `SHOPIFY_APP_HANDLE=fyndable-smart-seo`, `SHOPIFY_PARTNER_API_TOKEN`, `FYNDABLE_SHOPIFY_SYNC_SECRET`. Queue worker + scheduler zijn nodig voor de uur-sync en de uninstall-sync.
- Tests: Pest (`vendor/bin/pest`, PHP-ini via `PHP_INI_SCAN_DIR=C:/Devtools/composer/conf.d`). `composer.json` pint `platform.php` op 8.2.29.
- Geen Shopify-SDK: `shopify/shopify-app-php` is verwijderd (werd nooit gebruikt); `firebase/php-jwt ^7.0` is een directe dependency (sessietokens). `/auth/callback` verifieert alleen de HMAC en stuurt door naar `/dashboard` (managed install, geen code-exchange).
- GDPR `shop/redact`: wist keywords, rank history, llms-instellingen, `scheduled_articles`, `shop_usage`, naam/e-mail/licentie/billing en queuet `GdprDeleteTenantJob` (SaaS `/gdpr/delete`, alleen voor App Pricing-tenants; agency-licenties worden alleen ontkoppeld). `trial_products`/`trial_ended_at` blijven (trial-misbruik). SaaS `/gdpr/delete` verwijdert nu ook `tenant_settings` en gebruikt `tenant_id` voor usage/tickets.
- Queue-jobs (`BulkOptimizeJob`, `CheckRankingsJob`) checken de tier zelf via `LicenseService::canUse`. Tab -> feature-map voor het dashboard staat in `config/tiers.php` `tabs`.

## Shopify onboarding + merkstem (0.2, 2026-11-05)

- Trial-tenant ontstaat bij de eerste app-load (`ShopifyBillingService::refresh`, zie App Pricing). De homepage toont een setup-kaart zolang `shop_profiles.onboarding_completed_at`/`onboarding_skipped_at` leeg is en er geen plan vereist is; de wizard opent alleen na een klik (Built for Shopify 4.3.6: geen modals die vanzelf openen; 4.2.2: onboarding-UI verdwijnt na afronden). Heropenen via de Plan-tab.
- `OnboardingController`: `GET /api/onboarding` (profiel, suggesties uit `shopLocales` primary + `billingAddress.countryCodeV2`, fallback `?locale=`; 5 productvoorstellen, zonder SEO eerst), `POST /api/onboarding/profile|complete|skip`. Stap 3 roept de gewone `/products/{id}/generate-meta` + `save-meta` aan, dus tier-gating en trial-limiet gelden ongewijzigd.
- `BrandVoice::apply($shop, $messages, $voice = true)` voegt taal, land, toon, doelgroep en merkomschrijving toe aan de system prompt. Gebruikt in ProductController, ContentController en BulkOptimizeJob (alt-tekst: alleen taal/land). Blog Writer: standaardtaal uit het profiel, talenlijst = `BrandVoice::LANGUAGES`. Nieuwe AI-tekstgenerator = altijd via `BrandVoice::apply`.
- `shop/redact` verwijdert ook `shop_profiles`.

## Shopify embedded UI op Polaris (0.5, 2026-11-04)

- Geen single-page blade meer. `GET /dashboard/{page?}` (`DashboardController::PAGES`, volgorde = `s-app-nav`) rendert `resources/views/dashboard/pages/{page}.blade.php` op `layouts/app.blade.php` (App Bridge + `https://cdn.shopify.com/shopifycloud/polaris-2.js`). Navigatielinks geven `shop`, `host`, `locale`, `embedded` door (gevalideerd).
- JS: `public/js/fyndable.js` (`Fyn.api/post`, `Fyn.toast` (App Bridge), `Fyn.confirm` (s-modal, alleen vanuit een klik), `Fyn.errorBanner`, `Fyn.stats/row/table/badge/json`, `Fyn.val/setVal/checked`, `Fyn.busy`), pagina-scripts `public/js/pages/{page}.js` registreren met `Fyn.page(async ctx => ...)`. Die draait na `GET /api/dashboard/context` (billing, plan, onboarding) en niet als de pagina-feature (`config/tiers.php` `tabs`) buiten het plan valt. Home gebruikt `/dashboard/overview` (`Fyn.customContext = true`). Gedeelde extra scripts via `@php($scripts = ['onboarding'])` in de pagina-view.
- i18n: `lang/{en,nl}/app.php` (nav, gedeeld, home, instellingen, plan, onboarding) + `lang/{en,nl}/{page}.php`; de `js`-groep gaat als `window.FYN.i18n` naar de browser. Sleutels moeten in beide talen bestaan en er mogen geen em-/en-dashes in staan (`tests/Feature/DashboardPagesTest.php`). UI-taal = Shopify-admintaal (`?locale=`), AI-contenttaal = `shop_profiles.content_language` (pagina Instellingen, `GET|POST /api/settings/profile`).
- Geen eigen kleuren/dark mode/dashicons meer (`public/vendor/dashicons` verwijderd). `public/css/fyndable.css` bevat alleen `[hidden]`-fix voor custom elements en code/preview-blokken.
- Nieuwe pagina = view + script + `lang/en|nl/{page}.php` + entry in `DashboardController::PAGES` (+ `config/tiers.php` `tabs` als hij gegated is).

## Shopify SEO-audit (1.1, 2026-11-04)

- `App\Services\SeoAuditor`: pure scoring. `normalize($type, $node, $host)` (nodes uit `ShopifyContentFetcher::getAuditItems`), `scoreItem()` (itemchecks), `audit($items, $hosts)` (plus dubbele titels/beschrijvingen en gebroken interne links). Checks, ernst en strafpunten in `SeoAuditor::ISSUES`; bulk-acties per issue in `BULK_FIXES`.
- `getAuditItems()` gebruikt slanke queries met kleine pagina's (producten 40, cost < 1000) en geeft `['error' => ...]` terug als een pagina faalt (geen misleidende score op een halve catalogus). Producten met `status:active`, pagina's/artikelen `published_status:published`.
- `SeoAuditJob` (queue, 1 poging, 15 min) schrijft voortgang naar `seo_audits` en items naar `seo_audit_items`; houdt `KEEP` (5) audits per shop. Checkt zelf de tier (`seo_audit`, Starter).
- API: `POST /api/audit/start` (een tegelijk, na 30 min als vastgelopen beschouwd), `GET /api/audit/latest`, `GET /api/audit/items?issue=&type=&page=`. `ContentController::show` geeft `item.seo` (live itemscore), `index` geeft `score` uit de laatste audit; overview geeft `seo_score`.
- UI: pagina `seoaudit`; deep links `/dashboard/bulk?action=meta` en `/dashboard/content?type=product&id=123`. Issue-labels staan gedeeld in `lang/*/app.php` (`issue_*`, `severity_*`).
- GDPR `shop/redact` verwijdert ook `seo_audits`/`seo_audit_items`.

## Shopify redirects + 404-monitor (1.2, 2026-11-04)

- `ShopifyUrlRedirect` is nu GraphQL (`urlRedirects`, `urlRedirectCreate/Update/Delete`), scope `write_online_store_navigation` (ook voor de llms.txt- en IndexNow-redirects; route-middleware `shopify.scope:write_online_store_navigation`). `ensure()` maakt aan of zet een bestaande redirect om.
- `RedirectManager`: instellingen (kolommen op `shop_profiles`: `redirect_on_handle_change`, `redirect_on_delete`, `deleted_redirect_target` collection|all|home|search, `not_found_monitor`), `resourceUpdated()/resourceDeleted()` (handles in `resource_handles`, eerste collectie van een product max. 24 uur gecachet), `redirect()` (logt in `redirect_events` en lost een bijbehorende 404 op), `recordNotFound()` (max. 2000 open 404's per shop, referrer zonder query).
- Webhooks `products/*` en `collections/*` -> `TrackHandleChangeJob` (queue, checkt tier `redirects`). `shopify.app.toml` registreert nu `app/uninstalled`, `products/*`, `collections/*` (voorheen alleen GDPR-topics).
- 404-beacon: theme app embed `extensions/fyndable-seo-schema/blocks/fyndable-404-monitor.liquid` -> `POST /apps/fyndable/404` -> `NotFoundController` (app proxy-handtekening via `ShopifySignature::verifyAppProxy`, Shopify-spec: gedecodeerd, gesorteerd, zonder scheidingsteken; 120/min per shop).
- API onder `/api/redirects` (tier `redirects`, schrijfacties ook scope): lijst/zoeken, aanmaken, bewerken, verwijderen, `export` (CSV met formula-guard), `import` (`ImportRedirectsJob`, voortgang in cache `redirect_import:{shop_id}`), `not-found` (+ `fix`/`ignore`), `settings`.
- Tests: `tests/Pest.php` zet `shopify.scopes` vast (los van de lokale `.env`) en bevat nu `sendWebhook()`.

### Tier-indeling nieuwe Shopify-functies (2026-11-04)

Nieuwe functies volgen `shopify-tier-indeling.xlsx` (rijen 28-31; WordPress-tier uit `LicenseFeatureManager` als basis): `seo_audit` professional, itemscore iedereen (niet gegated), `redirects` starter, `not_found_monitor` professional (404-routes en de beacon checken deze feature apart). Nieuwe Shopify-functie = eerst een rij in de spreadsheet, dan `config/tiers.php`.

## Shopify winkelbreed schema (1.4, 2026-11-04)

- App embed `extensions/fyndable-seo-schema/blocks/fyndable-seo-schema.liquid`: handmatig itemschema (metafields `fyndable.*_schema`, `fyndable.faq_schema`) gaat voor; daarna, als `app.metafields.fyndable.schema_config.value` bestaat: `home`-blokken (Organization/LocalBusiness, door de app gebouwd) + WebSite/SearchAction op `request.page_type == 'index'`, BreadcrumbList, automatisch Product en BlogPosting uit Liquid. Alle strings via `| json`, geen JavaScript.
- `StoreSchemaService`: instellingen in `store_schema_settings` (flags, organization, local_business), `buildConfig()` (gating: `schema` starter, `local_business_schema` professional), `publish()` (metafieldsSet op `currentAppInstallation`, type json), `themeCheck()` (`ShopifyContentFetcher::getMainThemeFiles`, patterns `layout|sections|snippets|templates|blocks/*.liquid`).
- API: `GET|POST /api/schema`, `POST /api/schema/theme-check` (scope `read_themes`). Dagelijks `SyncStoreSchemaJob` via scheduler `store-schema-sync`.
- JS: `Fyn.embedStatus(handle)` (App Bridge `shopify.app.extensions()`, null als onbekend) en `Fyn.embedEditorUrl(handle)` (deeplink `shopify://admin/themes/current/editor?context=apps&activateAppId={apiKey}/{handle}`); `window.FYN.apiKey` is de publieke client-id.

## Shopify bulk optimizer (1.3, 2026-11-04)

- `BulkOptimizer`: `ACTIONS` per type (product: meta_title, meta, titles, alt_text, descriptions; collection: meta_title, meta, descriptions; page/article: meta_title, meta), `TEMPLATE_ACTIONS` (meta_title, meta), `select()` (via `getAuditItems`, met "alleen nodig" op basis van `SeoAuditor`-issues), `render()` (placeholders, scheidingstekens opruimen, woordgrens), `process()` (AI of sjabloon, opslaan per type).
- `BulkOptimizeJob(shopId, type, action, mode, template, items)` draait in de queue; voortgang in cache `bulk:{shop_id}` (status queued|running|completed|cancelled|failed, errors[]). Na elk item wordt eerst gecontroleerd op "cancelled" voordat de voortgang wordt weggeschreven.
- API: `POST /api/bulk/optimize|preview|cancel`, `GET /api/bulk/progress` (tier `bulk_optimizer`; pagina's/artikelen checken `write_content` in de controller). Deep link `/dashboard/bulk?type=product&action=meta`.
- JS `t()` laat onbekende `{naam}`-placeholders staan (nodig voor `{{title}}` in helpteksten).

## Shopify Search Console (1.5, 2026-11-04)

- SaaS `licenseapi.php`: nieuw `POST /google/oauth-session` (license+tenant, alleen `platform=shopify`) -> eenmalige `oauth-start?session=...` (transient `sseo_google_oauth_{session}`, 10 min, single use). In sessiemodus postMessaget de popup `{type: 'fyndable_google_code', code}` naar de Shopify-app-origin (`FYNDABLE_SHOPIFY_APP_ORIGIN` / optie `sseo_ai_saas_shopify_app_origin`, default `https://shopify.fyndable.ai`) en wisselt niet zelf in. WordPress (license_key/tenant_key in de URL, tokens via postMessage) is ongewijzigd.
- App: `SearchConsoleService` (session, connect via SaaS `/google/exchange`, `accessToken()` met refresh via SaaS, `sites()`, `suggestSite()`, `sync()` -> `gsc_page_stats`/`gsc_query_stats` (laatste 28 dagen tot 3 dagen geleden), `pageStats()`, `resourceForPath()`). Tokens in `google_connections` met `encrypted` casts, nooit in API-responses. `SaasProxyClient::post` geeft bij fouten nu ook `details` terug.
- API `/api/gsc/*` (tier `search_console`, professional). JS opent de popup leeg binnen de klik (popup-blockers) en controleert `event.origin` tegen de origin van de sessie-URL.
- Koppelingen: `RankTrackerController::listKeywords` voegt `gsc {clicks, impressions, position}` toe; `ContentController::show` voegt `item.gsc` toe. Uninstall verwijdert de Google-koppeling, `shop/redact` ook de statistieken.

## Shopify rapportage (1.7, 2026-11-04)

- Activiteitenlog: middleware `shopify.activity` (`RecordActivity`) op de hele `shopify.session`-API-groep; mapt de route-URI naar een soort (meta, description, schema, alt_text, product, internal_link, image, article) en logt alleen geslaagde POSTs (`success: true`). `BulkOptimizeJob` logt per geslaagd item. Nieuwe opslaan-route = patroon toevoegen in `RecordActivity::ROUTES`.
- `ReportBuilder::create($shop, $end)` maakt een `seo_reports`-snapshot van de 7 dagen tot `$end` (seo, rankings, search_console met vorige klikken, geo, activity). `ReportMailer::createAndSend()` mailt `WeeklyReportMail` (view `reports/email`, gedeelde partial `reports/_content` met inline styles) in `report_language` (anders contenttaal, anders en). Instellingen op `shop_profiles` (`report_enabled`, `report_email`, `report_language`).
- Signed web-routes: `report.show` (`/report/{report}`, printbaar, noindex) en `report.unsubscribe` (`/report/unsubscribe/{shop}`). Scheduler `weekly-seo-report` (maandag 07:00), job `SendSeoReportJob($shopId, $manual)`.
- API `/api/reports`, `/api/reports/settings`, `/api/reports/send` (tier `reports`, professional). `shop/redact` verwijdert rapporten en het activiteitenlog.

## Shopify AI-shopping (2.1, 2026-11-04)

- `AiCommerce` (puur): `group()` (varianten -> producten, alleen ACTIVE + online), `score()` (`CHECKS`, totaal 100), `openAiRow()` / `googleItem()` (OpenAI Product Feed Spec / Merchant Center), `validGtin()` (GTIN-8/12/13/14 controlecijfer), `attributes()` (metafield `fyndable.ai_attributes`).
- `ShopifyContentFetcher::getFeedVariants()` (productVariants, 50 per pagina, productvelden per variant). `FeedBuilder::build()` schrijft `storage/app/private/feeds/{shop_id}/google.xml|openai.jsonl`, vult `ai_readiness_items` en `product_feeds` (status, tellingen, gemiddelde, issue-samenvatting).
- `AiAttributes`: `generate()` (AI, JSON, normaliseert enums), `save()` (metafieldsSet json). Bulkactie `ai_attributes` in `BulkOptimizer` ("alleen nodig" = metafield leeg).
- API `/api/ai-ready/*` (tier `ai_readiness`; attributen `ai_attributes`, met `shopify.trial`). Publiek: `GET /feeds/{token}/{google.xml|openai.jsonl}` (`FeedController`, 404 als uit, geen plan of niet geinstalleerd). Scheduler `product-feed-build` 02:00.

## Shopify AI-crawlers (2.2, 2026-11-04)

- `AiCrawlers`: `BOTS` (user agent => [bedrijf, search|user|training]), keuzes in `shop_profiles.ai_crawlers` (standaard allow), `block()` (alleen `Disallow: /` voor geblokkeerde bots, tussen `# Fyndable AI crawlers - begin/end`), `fullTemplate()` (Shopify `robots.default_groups`-loop + blok), `themeStatus()` (`templates/robots.txt.liquid` via `getMainThemeFiles`), `liveStatus()` + `parse()`/`isBlocked()` (eigen groep, anders `*`; `Allow: /` wint).
- API `GET|POST /api/ai-crawlers`, `POST /api/ai-crawlers/check` (tier `ai_crawlers`, starter; check vraagt `read_themes`). Geen `write_themes`: de merchant plakt de code zelf.

## Shopify AI-vertalingen (2.3, 2026-11-04)

- `AiTranslator`: `TYPES` (type => [TranslatableResourceType, keys]), `locales()`, `gaps()` (ontbrekend/verouderd per resource), `translate()` (AI, JSON met dezelfde keys, lengtelimieten), `register()` (`ShopifyAdminWriter::registerTranslations`, met `translatableContentDigest`). Fetcher: `getShopLocales()`, `getTranslatableResources()` (25 per pagina), `getTranslatableResource()`.
- `BrandVoice::apply($shop, $messages, $voice, $market)`: `$market = false` laat contenttaal en land weg (nodig bij vertalen naar een andere taal).
- API `/api/translations/*` (tier `translations`, professional; save/run vragen `write_translations`). `TranslateJob` (cache `translate:{shop_id}`), activiteitensoort `translation`. Gaten worden 5 minuten gecachet (`translation_gaps:{shop}:{type}:{locale}`).

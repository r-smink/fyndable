# Changelog

## v1.7.1 (2026-08-19)

### Fixed / Improved
- **Upgrade button** — "Upgrade Now →" button on locked-feature pages now links to the Customer Portal on the SaaS dashboard (with dashboard-URL fallback) instead of the local Connection page. Opens in a new tab.
- **Portal URL storage** — Client stores `portal_url` from `tenant/status` and `license/activate` responses in `sseo_ai_client_portal_url` option.
- **Ideas page upgrade link** — "Upgrade My Plan" link on the Ideas page now points to the Customer Portal instead of `#`.
- **Plugin metadata cleanup** — Removed `FEATURES.md` and `REVIEW-EN-FEATURES.md` from the plugin package; only `README.md` and `CHANGELOG.md` are shipped.

## v1.5.1 (2026-07-15)

### New Features
- **Fynable Login Screen** — Fully branded WordPress `wp-login.php` with separate toggle (Settings → White-Label & Login). Works for all users/tiers.
- **Free Tier Toggle** — Onboarding free-tier skip can be toggled off; default is disabled for beta, code remains intact.

### Fixed / Improved
- **SaaS Dashboard Branding** — Topbar now shows `Fyndable Smart SEO` with a smaller `SaaS` suffix.
- **SaaS Login Header** — Login screen now shows `Fyndable Smart SEO` instead of `Fyndable SaaS`.

## v0.5-beta (2026-03-08)

### New Features
- **AI Content Rewriter** — Rewrite existing content in multiple modes: SEO optimize, improve readability, expand, condense, paraphrase, tone shift. Section-level and full-article rewriting. REST API endpoints.
- **Readability Score** — Flesch Reading Ease + Flesch-Kincaid Grade Level analysis. Dutch support via Flesch-Douma formula. Passive voice detection, transition word analysis, sentence/paragraph length checks. AI-powered improvement suggestions.
- **IndexNow Integration** — Instant search engine notification on publish/update/delete. Auto-generated API key with verification file. Submits to Bing and IndexNow API. Submission log tracking.
- **GSC Dashboard** — Google Search Console performance data directly in WordPress admin. Clicks, impressions, CTR, average position. Top queries and top pages tables. Period selection (7/28/90 days).
- **ImageClient** — Image processing utility for AI vision: base64 encoding, resize, EXIF metadata extraction.

### Fixed / Improved
- **ContentBrief** — Replaced `SerpService` dependency with `DashboardAPI` proxy. Added AI fallback for SERP data. Now fully functional without archived plugin.
- **KeywordExplorer** — Replaced `SerpService` + `TopicRepository` dependencies. Uses `DashboardAPI` for SERP data, stores clusters in `wp_options`. Added REST API endpoints and caching.
- **TechChecker** — Removed non-existent sub-checker class dependencies (`SchemaValidator`, `HreflangChecker`, `RobotsChecker`, `RedirectChecker`). All checks now self-contained: JSON-LD validation, hreflang analysis, robots.txt parsing, redirect chain detection, meta robots/title/canonical/description checks.

### Module Registration
- Registered 12 previously unregistered modules in `client.php`:
  - **Core tier**: LSIKeywords, RolePermissions, ExtendedSitemaps, PageSpeedClient, ReadabilityScore, IndexNow
  - **Starter+**: ImageAltGenerator, ContentRewriter
  - **Professional+**: ContentBrief, KeywordExplorer, GscDashboard
  - **Business+**: AIRepurposer

### UI
- Complete admin CSS overhaul (`client-admin.css`): CSS custom properties design system, modern cards/buttons/inputs/tables, stat cards, badges, score circles, SERP preview, analysis lists, loading spinner, responsive breakpoints.

### Documentation
- Created comprehensive `README.md` with full feature overview (50+ modules), license tiers, REST API endpoints, admin pages, competitive matrix vs RankMath/Yoast/AIOSEO/WPSEO AI/MarketMuse/NeuronWriter/SurferSEO.

---

## v0.4-alpha (previous)
- Initial MarketMuse/NeuronWriter competitive features: ContentOptimizer, SerpCompetitor, TopicCluster, KeywordDifficulty
- Core SEO features: TruSEO, SmartTags, Sitemaps, Breadcrumbs, OpenGraph, Canonical, Schema, Local SEO, Rank Tracker, etc.

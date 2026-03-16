# AI SEO Client — WordPress Plugin

> All-in-one AI-powered SEO plugin that combines the best of RankMath/Yoast/AIOSEO **and** MarketMuse/NeuronWriter/SurferSEO — directly inside WordPress.

## Installation

1. Upload `ai-seo-client` to `/wp-content/plugins/`
2. Activate via **Plugins → Activate**
3. Go to **AI SEO → License** and enter your SaaS Dashboard URL + license key
4. Features unlock based on your license tier

---

## Feature Overview (50+ modules)

### Core Features (All Tiers)

| Module | File | Description |
|--------|------|-------------|
| **TruSEO Score** | `truseoscore.php` | Real-time on-page SEO analysis with 0-100 score. Checks focus keyphrase usage, title length, meta description, readability, heading structure, image alt tags, internal/external links. Google SERP preview. AI-powered improvement suggestions. |
| **Smart Tags** | `smarttags.php` | AI-generated meta tags using dynamic variables like `%title%`, `%sitename%`, `%separator%`. Auto-generates SEO titles and descriptions from content patterns. |
| **XML Sitemap** | `sitemapgenerator.php` | Auto-generated XML sitemap with post types, taxonomies, priority/frequency settings. Ping search engines on publish. Exclusion rules per post/page. |
| **Extended Sitemaps** | `extendedsitemaps.php` | Video sitemap (YouTube/Vimeo), News sitemap (Google News), Image sitemap, RSS sitemap, Author sitemap. Auto-ping on publish. |
| **Robots.txt Editor** | `robotstxt.php` | Visual robots.txt editor with AI suggestions. Block/allow rules, sitemap references, preview. |
| **Open Graph / Social Meta** | `opengraph.php` | Facebook OG tags, Twitter Cards, per-post social image/title/description overrides. AI-generated social snippets. Preview for Facebook & Twitter. |
| **Canonical URLs** | `canonicalurl.php` | Automatic canonical URL management with per-post override. Prevents duplicate content issues. Cross-domain canonical support. |
| **Breadcrumbs** | `breadcrumbs.php` | SEO breadcrumbs with JSON-LD schema markup. `[aiseo_breadcrumbs]` shortcode. Customizable separator, home label, post type archives. |
| **SEO Dashboard** | `seodashboard.php` | Site-wide SEO health score with breakdown: content quality, technical SEO, meta optimization, link health. Quick wins list, issues tracker, improvement trends. |
| **Multi-language SEO (Hreflang)** | `hreflang.php` | Automatic hreflang tags with WPML, Polylang, TranslatePress auto-detection. Manual per-post language mappings. x-default support. |
| **LSI Keywords** | `lsikeywords.php` | AI-generated LSI (Latent Semantic Indexing) keywords per post. Visual keyword cloud with used/unused tracking. Coverage percentage calculation. |
| **Role-Based Permissions** | `rolepermissions.php` | Granular capabilities for Admins, Editors, Authors, Contributors. Controls access to settings, dashboard, AI features, bulk actions, SERP data. |
| **PageSpeed Client** | `pagespeedclient.php` | Google PageSpeed Insights API integration. Core Web Vitals: LCP, INP, CLS, TTFB, FCP. Mobile/Desktop strategy selection. |
| **Readability Score** | `readabilityscore.php` | Flesch Reading Ease + Flesch-Kincaid Grade Level. Dutch support via Flesch-Douma formula. Passive voice detection, transition words, sentence/paragraph length analysis. AI-powered improvement suggestions. |
| **IndexNow** | `indexnow.php` | Instant search engine notification on publish/update/delete. Auto-generated API key. Submits to Bing + IndexNow API. Submission log. |

### Starter+ Features

| Module | File | Description |
|--------|------|-------------|
| **Internal Link Assistant** | `linkassistant.php` | AI-powered internal linking suggestions. Scans content for link opportunities, suggests anchor text, tracks orphan pages with no internal links. |
| **Redirection Manager** | `redirectionmanager.php` | Create 301/302/307 redirects. Import from CSV. Auto-redirect on slug change. Regex support. Hit counter and last-accessed tracking. |
| **AI Image Alt Generator** | `imagealtgenerator.php` | Generates descriptive, SEO-optimized alt text for images using AI vision. Bulk process media library. Customizable alt text patterns. |
| **AI Content Rewriter** | `contentrewriter.php` | Rewrite content in multiple modes: SEO optimize, readability, expand, condense, paraphrase, tone shift. Section-level and full-article rewriting. Preserves HTML structure. |

### Professional+ Features

| Module | File | Description |
|--------|------|-------------|
| **Schema Markup** | `schemamarkup.php` | JSON-LD structured data: Article, FAQ, HowTo, Product, Review, Recipe, Event, Local Business, Breadcrumb, Video. Auto-detect and manual override. Validation testing. |
| **Local SEO** | `localseo.php` | Local business schema, NAP consistency checker, Google Business Profile integration, service area pages, opening hours, multi-location support. |
| **404 Monitor** | `notfoundmonitor.php` | Real-time 404 error tracking with referrer, user agent, hit count. One-click redirect creation. Auto-cleanup of old entries. |
| **Keyword Rank Tracker** | `ranktracker.php` | Daily SERP position tracking via API. Historical trend charts. Position change alerts. Track unlimited keywords. Country/language targeting. |
| **SEO Report Export** | `seoreportexport.php` | Export site-wide SEO audits as CSV or printable PDF/HTML. Covers all posts: SEO score, meta data, issues, word count, focus keyphrase. |
| **WooCommerce SEO** | `woocommerceseo.php` | Product schema (price, availability, reviews), AI product description generator, product-specific meta optimization, category SEO settings. |
| **Content Optimizer** | `contentoptimizer.php` | **MarketMuse/SurferSEO killer.** NLP topic model with 30-50 weighted terms per keyword. Real-time 0-100 content score. Term heatmap (covered/missing/low/overused). Structure scoring (word count, headings, images, paragraphs). AI suggestion engine for missing terms. SurferSEO-style editor page. |
| **SERP Competitor Analysis** | `serpcompetitor.php` | **NeuronWriter competitor analysis.** Analyzes top-20 SERP results: competitor profiles, content type, word counts, strengths/weaknesses. Topic heatmap with coverage percentages. Winning patterns identification. Content gap finder. Compare your content vs competitors with competitive score. Deep AI gap analysis. |
| **Topic Cluster Map** | `topiccluster.php` | **MarketMuse cluster analysis.** AI-generated pillar-cluster content architecture. Hub pages + supporting pages per subtopic. Internal linking strategy. Content calendar with weekly planning. Existing content audit against cluster map. Save/load multiple clusters. Topical authority score potential. |
| **Personalized Keyword Difficulty** | `keyworddifficulty.php` | **MarketMuse personalized difficulty.** Unlike generic KD, analyzes difficulty relative to YOUR site: existing topical authority, content inventory, pillar page presence, internal linking strength. Batch analysis for up to 20 keywords. Recommendations based on your competitive position. |
| **Content Brief Generator** | `contentbrief.php` | SEO content brief using SERP analysis + AI. Competitor headings, questions, entities, LSI keywords, outlines, difficulty estimation, content scoring against brief. |
| **Keyword Explorer** | `keywordexplorer.php` | Keyword expansion via SERP title n-gram extraction. Jaccard similarity clustering. Stores expansions and clusters in wp_options. REST API for expand + cluster. |
| **GSC Dashboard** | `gscdashboard.php` | Google Search Console performance data in WordPress admin. Clicks, impressions, CTR, average position. Top queries and top pages tables. Period selection (7/28/90 days). |

### Business+ Features

| Module | File | Description |
|--------|------|-------------|
| **AI Content Writer** | `contentwriter.php` | Full AI article generation with configurable tone, word count, outline. Section-by-section writing for quality. Auto-generates intro, body, FAQ, conclusion. Creates WordPress draft with SEO meta. Integrates with Content Brief data. |
| **AI Content Repurposer** | `airepurposer.php` | Transform existing content into new formats: blog → social posts, article → email newsletter, long-form → summary, text → FAQ, content → video script. |
| **Bulk AI Optimizer** | `bulkactions.php` | Bulk generate meta titles, descriptions, OG tags for hundreds of posts. SEO status column in post list. Scan for missing meta data. Progress tracking with batch processing. |
| **Content Decay Monitor** | `contentdecay.php` | Detects declining content via Google Search Console data. Tracks impression/click trends. Alerts when pages lose rankings. Suggests refresh strategies. |
| **Audit Service** | `auditservice.php` | Comprehensive content audit with quality scoring, thin content detection, duplicate content finder, and optimization recommendations. |

### Agency Features

| Module | File | Description |
|--------|------|-------------|
| **SEO Revisions** | `seorevisions.php` | Track all SEO meta changes over time. Compare revisions, restore previous versions. Audit trail for multi-user environments. |
| **AI Plagiarism Checker** | `plagiarismchecker.php` | AI-powered originality analysis. Heuristic checks (sentence patterns, vocabulary diversity, perplexity) combined with LLM deep analysis. Originality score 0-100. Flags AI-generated content. |

### Infrastructure / Support Modules

| Module | File | Description |
|--------|------|-------------|
| **LLM Client** | `llmclient.php` | Proxied AI calls through SaaS Dashboard. Supports OpenAI, Anthropic, Mistral. Rate limiting, cost tracking, fallback handling. |
| **Dashboard API** | `dashboardapi.php` | Communication layer between client plugin and SaaS Dashboard. License validation, SERP proxy, usage tracking. |
| **Settings** | `settings.php` | Centralized settings management with get/set/defaults. |
| **License Validator** | `licensevalidator.php` | License key validation, tier detection, expiration checks, periodic re-validation via cron. |
| **Health Logger** | `healthlogger.php` | Internal health monitoring for SERP providers, API calls, performance metrics. |
| **Snapshot Repository** | `snapshotrepository.php` | SERP snapshot storage and retrieval for historical data. |
| **GSC Client** | `gscclient.php` | Google Search Console API integration for impression/click data. |
| **GSC OAuth** | `gscoauth.php` | OAuth2 flow for Google Search Console authorization. |
| **Image Client** | `imageclient.php` | Image processing utility: base64 encoding for AI vision, resize, EXIF metadata extraction, accessibility check. |
| **Tech Checker** | `techchecker.php` | Technical SEO validator: JSON-LD schema, hreflang, robots.txt, meta robots, title/canonical/description checks, redirect chain analysis. |

---

## License Tiers

| Tier | Includes |
|------|----------|
| **Free/Starter** | Core SEO (TruSEO, Sitemaps, OG, Canonical, Breadcrumbs, Dashboard, Hreflang, LSI, Permissions, PageSpeed, Readability Score, IndexNow) |
| **Starter+** | + Link Assistant, Redirections, AI Image Alt, AI Content Rewriter |
| **Professional** | + Schema, Local SEO, 404 Monitor, Rank Tracker, Reports, WooCommerce SEO, Content Optimizer, SERP Analysis, Topic Clusters, Keyword Difficulty, Content Brief, Keyword Explorer, GSC Dashboard |
| **Business** | + AI Writer, AI Repurposer, Bulk Optimizer, Content Decay, Audit Service |
| **Agency** | + SEO Revisions, Plagiarism Checker |

---

## REST API Endpoints

All endpoints use namespace `aiseoclient/v1`.

### On-Page SEO
- `POST /truseo/analyze` — Analyze content SEO
- `POST /truseo/suggest` — AI improvement suggestions
- `POST /lsi-suggest` — Generate LSI keywords
- `POST /lsi-check` — Check LSI usage in content

### Content Optimization (MarketMuse/SurferSEO)
- `POST /optimizer/topic-model` — Generate NLP topic model
- `POST /optimizer/score` — Score content against topic model
- `POST /optimizer/suggest-terms` — AI suggest missing term insertions

### SERP & Competitor Analysis
- `POST /serp/analyze` — Full SERP competitor analysis
- `POST /serp/compare` — Compare your content vs competitors
- `POST /serp/gap-analysis` — Deep content gap analysis
- `POST /keyword-difficulty` — Personalized keyword difficulty
- `POST /keyword-difficulty/batch` — Batch KD analysis

### Topic Clusters
- `POST /clusters/generate` — Generate pillar-cluster map
- `POST /clusters/audit` — Audit existing content coverage
- `POST /clusters/save` — Save cluster map
- `GET /clusters/list` — List saved clusters
- `DELETE /clusters/{id}` — Delete cluster

### Content Generation & Rewriting
- `POST /write-article` — Generate full AI article
- `POST /write-section` — Generate single section
- `POST /content-brief` — Generate content brief
- `POST /content-brief/score` — Score content against brief
- `POST /rewrite` — AI content rewrite (multiple modes)
- `POST /rewrite/section` — Rewrite single section

### Readability
- `POST /readability/analyze` — Full readability analysis (Flesch, grade level, issues)
- `POST /readability/suggest` — AI readability improvement suggestions

### Keyword Research
- `POST /keywords/expand` — Expand seed keyword via SERP n-grams
- `POST /keywords/cluster` — Cluster keywords by Jaccard similarity

### IndexNow
- `POST /indexnow/submit` — Submit URLs for instant indexing
- `GET /indexnow/status` — View submission log and API key info

### Google Search Console
- `GET /gsc/overview` — GSC performance overview (clicks, impressions, CTR, position)
- `GET /gsc/queries` — Top queries by clicks
- `GET /gsc/pages` — Top pages by clicks

### Bulk & Management
- `GET /bulk/scan` — Scan posts for SEO issues
- `POST /bulk/generate-meta` — Generate meta for single post
- `POST /bulk/generate-batch` — Batch generate meta
- `POST /originality/check` — AI plagiarism check

### Rank Tracking
- `GET /ranks/keywords` — Get tracked keywords
- `POST /ranks/add` — Add keyword to track
- `POST /ranks/delete` — Remove tracked keyword
- `GET /ranks/history/{id}` — Get position history
- `POST /ranks/check-now` — Force rank check

---

## Admin Pages

| Menu Item | Slug | Module |
|-----------|------|--------|
| AI SEO → License | `ai-seo-client` | License activation/management |
| AI SEO → Dashboard | `ai-seo-dashboard` | Site-wide SEO health overview |
| AI SEO → Content Optimizer | `ai-seo-optimizer` | NLP content scoring editor |
| AI SEO → SERP Analysis | `ai-seo-serp-analysis` | Competitor analysis + heatmap |
| AI SEO → Topic Clusters | `ai-seo-topic-clusters` | Topical authority mapping |
| AI SEO → Rank Tracker | `ai-seo-ranks` | Keyword position tracking |
| AI SEO → Content Writer | `ai-seo-assistant-writer` | AI article generation |
| AI SEO → Content Brief | `ai-seo-assistant-brief` | SEO brief generator |
| AI SEO → Search Console | `ai-seo-gsc` | GSC performance dashboard |
| AI SEO → Bulk Optimizer | `ai-seo-bulk` | Bulk meta generation |
| AI SEO → SEO Reports | `ai-seo-reports` | CSV/PDF export |

---

## Competitive Advantage

| Feature | RankMath | Yoast | AIOSEO | WPSEO AI | MarketMuse | NeuronWriter | SurferSEO | **SSEO AI Client** |
|---------|---------|-------|--------|----------|-----------|-------------|-----------|-------------------|
| On-page SEO Score | ✅ | ✅ | ✅ | ✅ | — | — | — | ✅ |
| NLP Content Score | — | — | — | Partial | ✅ | ✅ | ✅ | ✅ |
| Topic Model | — | — | — | — | ✅ | ✅ | ✅ | ✅ |
| SERP Competitor Analysis | — | — | — | — | ✅ | ✅ | ✅ | ✅ |
| Topic Cluster Maps | — | — | — | — | ✅ | ✅ | — | ✅ |
| Personalized KD | — | — | — | — | ✅ | — | — | ✅ |
| AI Content Writing | — | Partial | Partial | ✅ | — | ✅ | — | ✅ |
| AI Content Repurposing | — | — | — | ✅ | — | — | — | ✅ |
| Schema Markup | ✅ | ✅ | ✅ | — | — | — | — | ✅ |
| WooCommerce SEO | ✅ | ✅ | ✅ | — | — | — | — | ✅ |
| Rank Tracking | ✅ | — | — | — | — | — | ✅ | ✅ |
| Redirections | ✅ | — | ✅ | — | — | — | — | ✅ |
| 404 Monitor | ✅ | — | — | — | — | — | — | ✅ |
| Plagiarism Check | — | — | — | — | — | — | — | ✅ |
| SEO Revisions | — | — | — | — | — | — | — | ✅ |
| Content Decay Alert | — | — | — | — | ✅ | — | — | ✅ |
| Local SEO | ✅ | ✅ | ✅ | — | — | — | — | ✅ |
| Bulk Optimizer | — | — | — | ✅ | — | — | — | ✅ |
| Readability Score (NL) | — | ✅ | — | — | — | — | — | ✅ |
| AI Content Rewriter | — | — | — | ✅ | — | — | — | ✅ |
| IndexNow Integration | ✅ | — | — | — | — | — | — | ✅ |
| GSC Dashboard | ✅ | — | — | — | — | — | — | ✅ |
| Hreflang / Multilang | ✅ | ✅ | — | — | — | — | — | ✅ |
| Video/News Sitemaps | ✅ | ✅ | ✅ | — | — | — | — | ✅ |
| SaaS License Gating | — | — | — | — | — | — | — | ✅ |

---

## Technical Notes

- **Namespace:** `AISEOClient`
- **Autoloader:** `strtolower(className)` → `includes/{filename}.php`
- **REST namespace:** `aiseoclient/v1`
- **Meta prefix:** `_aiseo_`
- **Option prefix:** `ai_seo_client_`
- **Cron hooks:** `ai_seo_client_license_check`, `aiseo_rank_check_cron`
- **Dependencies:** `wp-api-fetch` for admin JS

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Active SaaS Dashboard connection for AI features
- Google PageSpeed API key (optional, for PageSpeed module)

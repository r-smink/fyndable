# AI SEO Client — WordPress Plugin

> All-in-one AI-powered SEO plugin that combines the best of RankMath/Yoast/AIOSEO **and** MarketMuse/NeuronWriter/SurferSEO/Frase — directly inside WordPress. **60+ modules. Version 1.2.0.**

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
| **Topic Cluster Map** | `topiccluster.php` | **MarketMuse cluster analysis.** AI-generated pillar-cluster content architecture. Hub pages + supporting pages per subtopic. **One-click content generation** - generate AI content for any pillar/hub/supporting page directly from the cluster map. Internal linking strategy. Content calendar with weekly planning. Existing content audit against cluster map. Save/load multiple clusters. Topical authority score potential. |
| **Personalized Keyword Difficulty** | `keyworddifficulty.php` | **MarketMuse personalized difficulty.** Unlike generic KD, analyzes difficulty relative to YOUR site: existing topical authority, content inventory, pillar page presence, internal linking strength. Batch analysis for up to 20 keywords. Recommendations based on your competitive position. |
| **Content Brief Generator** | `contentbrief.php` | SEO content brief using SERP analysis + AI. Competitor headings, questions, entities, LSI keywords, outlines, difficulty estimation, content scoring against brief. |
| **Keyword Explorer** | `keywordexplorer.php` | Keyword expansion via SERP title n-gram extraction. Jaccard similarity clustering. Stores expansions and clusters in wp_options. REST API for expand + cluster. |
| **GSC Dashboard** | `gscdashboard.php` | Google Search Console performance data in WordPress admin. Clicks, impressions, CTR, average position. Top queries and top pages tables. Period selection (7/28/90 days). |
| **FAQ Schema Generator** | `faqschema.php` | AI-generated FAQ structured data from content. Auto-extracts Q&A pairs. JSON-LD output. Per-post FAQ editor with AI suggestions. |
| **Video SEO** | `videoseo.php` | VideoObject schema markup, AI-generated video transcripts, video sitemap integration, thumbnail optimization, video rich snippet support. |
| **AI Image Generator** | `aiimagegenerator.php` | DALL-E / Midjourney proxy via SaaS Dashboard. Generate featured images from prompts. Auto-alt text generation. Save to media library. |
| **E-E-A-T Validator** | `eeatvalidator.php` | AI-powered Experience, Expertise, Authoritativeness, Trustworthiness analysis. Checks author bios, citations, outbound links, factual claims. Improvement suggestions. |
| **Content Performance Monitor** | `contentperformancemonitor.php` | Track content metrics over time: word count, readability, SEO score trends. Identifies underperforming pages. Benchmark against competitors. |
| **Backlink Analyzer** | `backlinkanalyzer.php` | Backlink profile analysis: total links, referring domains, anchor text distribution, toxic link detection. Integration with external backlink APIs. |
| **SERP Feature Tracker** | `serpfeaturetracker.php` | Track featured snippets, People Also Ask, knowledge panels, image packs, video carousels. Alert when you win/lose SERP features. |
| **International SEO** | `internationalseo.php` | Advanced hreflang management, geo-targeting settings, multilingual sitemaps, currency/price localization for WooCommerce. |
| **Technical SEO Auditor** | `technicalseoauditor.php` | Comprehensive technical audit: crawlability, indexability, Core Web Vitals, mobile-friendliness, structured data validation, broken links, redirect chains. |
| **Competitor Research** | `competitorresearch.php` | Deep competitor domain analysis: traffic estimates, top keywords, content gaps, backlink comparison. AI-powered strategic recommendations. |
| **A/B Testing** | `abtesting.php` | **Differentiator.** Test title/content/meta variants per post. Cookie-based traffic split. 4 goal types: page_view, click, form_submit, time_on_page. Real-time stats dashboard. Auto-winner detection. |

### Business+ Features

| Module | File | Description |
|--------|------|-------------|
| **AI Content Writer** | `contentwriter.php` | Full AI article generation with configurable tone, word count, outline. Section-by-section writing for quality. Auto-generates intro, body, FAQ, conclusion. Creates WordPress draft with SEO meta. Integrates with Content Brief data. |
| **AI Content Repurposer** | `airepurposer.php` | Transform existing content into new formats: blog → social posts, article → email newsletter, long-form → summary, text → FAQ, content → video script. |
| **Bulk AI Optimizer** | `bulkactions.php` | Bulk generate meta titles, descriptions, OG tags for hundreds of posts. SEO status column in post list. Scan for missing meta data. Progress tracking with batch processing. |
| **Content Decay Monitor** | `contentdecay.php` | Detects declining content via Google Search Console data. Tracks impression/click trends. Alerts when pages lose rankings. Suggests refresh strategies. |
| **Advanced Backlinks** | `advancedbacklinks.php` | Deep backlink monitoring: new/lost links, anchor text changes, domain authority trends. Automated outreach email templates. Competitor backlink gap analysis. |
| **Content Performance Monitor** | `contentperformancemonitor.php` | Long-term content metrics tracking. Benchmark against historical performance. Automated underperformance alerts. |
| **Audit Service** | `auditservice.php` | Comprehensive content audit with quality scoring, thin content detection, duplicate content finder, and optimization recommendations. |

### Agency Features

| Module | File | Description |
|--------|------|-------------|
| **SEO Revisions** | `seorevisions.php` | Track all SEO meta changes over time. Compare revisions, restore previous versions. Audit trail for multi-user environments. |
| **AI Plagiarism Checker** | `plagiarismchecker.php` | AI-powered originality analysis. Heuristic checks (sentence patterns, vocabulary diversity, perplexity) combined with LLM deep analysis. Originality score 0-100. Flags AI-generated content. |
| **White Label** | `whitelabel.php` | Rebrand the plugin with your agency logo, colors, and name. Client-facing reports without SSEO AI branding. Custom domain for SaaS dashboard. |

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

| Tier | Includes | API Limit | For |
|------|----------|-----------|-----|
| **Free** | Core SEO features only | 100/month | Basic users |
| **Starter** | Core + Link Assistant, Redirect Manager, Image Alt Generator, Content Rewriter | 500/month | Small sites |
| **Professional** | Starter + Schema, Rank Tracker, Topic Clusters, Content Optimizer, GSC Dashboard, SERP Analysis | 2,000/month | SEO professionals |
| **Business** | Professional + AI Content Writer, Bulk Optimizer, Content Decay Monitor, Repurposer | 10,000/month | Content teams |
| **Agency** | Business + SEO Revisions, Plagiarism Checker, White Label | Unlimited | Marketing agencies |
| **Trial** | All Professional features for 14 days | 2,000/month | Evaluation |
| **DEV** | **ALL 50+ features + unlimited API** | **Unlimited** | **Internal development/testing only** |

### Feature Availability Quick Reference

| Feature Category | Free | Starter | Pro | Business | Agency | **DEV** |
|-----------------|:----:|:-------:|:---:|:--------:|:------:|:------:|
| **Core SEO** (TruSEO, Sitemaps, OG, Keywords, Ideas, etc.) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Content Tools** (Rewriter, Alt Generator, Link Assistant) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Advanced SEO** (Rank Tracker, GSC, Clusters, A/B Testing, Schema, etc.) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| **AI Generation** (Writer, Repurposer, Bulk, Image Gen) | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| **Agency Tools** (Revisions, Plagiarism, White Label) | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |

> ⚠️ **DEV tier** is for internal development/testing only. Never distribute to clients. Set via SaaS dashboard or database: `sseo_ai_client_license_tier = 'dev'`

---

## REST API Endpoints

All endpoints use namespace `sseo-ai/v1`.

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
- `GET /clusters/{id}` — Get single cluster by ID
- `DELETE /clusters/{id}` — Delete cluster
- `POST /clusters/generate-content` — **NEW** Generate AI content for cluster page and create WordPress draft

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

### A/B Testing
- `GET /ab-tests` — List all tests
- `POST /ab-tests` — Create new test with variants
- `DELETE /ab-tests/{id}` — Delete test and all data
- `POST /ab-tests/{id}/end` — End an active test
- `GET /ab-tests/{id}/stats` — Get test statistics and conversion rates
- `POST /ab-tests/conversion` — Record a conversion (public endpoint)

---

## Admin Pages

| Menu Item | Slug | Module | Min Tier | Icon |
|-----------|------|--------|----------|------|
| 🔗 Connection | `ai-seo-client` | License activation | Free | 🔗 |
| 📊 Dashboard | `ai-seo-dashboard` | SEO health overview | Free | 📊 |
| 📅 Content Calendar | `ai-seo-content-calendar` | Editorial calendar | Free | 📅 |
| 🤖 AI Tools | `ai-seo-ai-tools` | AI tools overview | Free | 🤖 |
| � Ideas | `ai-seo-ideas` | AI content ideas | Free | 💡 |
| 📝 Created Posts | `ai-seo-created-posts` | Generated content overview | Free | 📝 |
| 🎯 Keywords | `ai-seo-keywords` | Keyword database & clusters | Free | 🎯 |
| �� Link Manager | `ai-seo-link-manager` | Smart Internal Linking | Free | 🔗 |
| 🗺️ Sitemaps | `ai-seo-sitemaps` | XML sitemap management | Free | 🗺️ |
| 🔌 Integrations | `ai-seo-integrations` | External services | Free | 🔌 |
| 🎯 Topic Clusters | `ai-seo-topic-clusters` | Topical authority mapping | **Professional** | 🎯 |
| 🔍 Site Audit | `ai-seo-site-audit` | Technical SEO audit | **Professional** | 🔍 |
| 📈 Rank Tracker | `ai-seo-rank-tracker` | SERP position tracking | **Professional** | 📈 |
| 📊 Search Console | `ai-seo-gsc` | GSC integration | **Professional** | 📊 |
| 🧪 A/B Testing | `ai-seo-ab-testing` | Content split testing | **Professional** | 🧪 |
| ⚙️ Settings | `ai-seo-settings` | Plugin configuration | Free | ⚙️ |

### Tier-Restricted Menu Behavior

When a user tries to access a feature not included in their tier:
1. They see a **beautiful gradient upgrade page** instead of an error
2. The page shows what features they'll get by upgrading
3. A clear CTA button redirects to the license/upgrade page
4. Their current tier is displayed at the bottom

---

## Competitive Advantage

| Feature | RankMath | Yoast | AIOSEO | WPSEO AI | MarketMuse | NeuronWriter | SurferSEO | Frase | **SSEO AI Client** |
|---------|---------|-------|--------|----------|-----------|-------------|-----------|-------|-------------------|
| **On-page SEO Score** | ✅ | ✅ | ✅ | ✅ | — | — | — | — | ✅ |
| **NLP Content Score** | — | — | — | Partial | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Topic Model / Content Briefs** | — | — | — | — | ✅ | ✅ | ✅ | ✅ | ✅ |
| **SERP Competitor Analysis** | — | — | — | — | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Topic Cluster Maps** | — | — | — | — | ✅ | ✅ | — | — | ✅ |
| **Personalized Keyword Difficulty** | — | — | — | — | ✅ | — | — | — | ✅ |
| **AI Content Writing** | — | Partial | Partial | ✅ | — | ✅ | — | ✅ | ✅ |
| **AI Content Repurposing** | — | — | — | ✅ | — | — | — | — | ✅ |
| **AI Image Generator** | — | — | — | — | — | — | — | — | ✅ |
| **FAQ Schema Generator** | — | — | — | — | — | — | — | — | ✅ |
| **Video SEO / Transcripts** | — | — | — | — | — | — | — | — | ✅ |
| **E-E-A-T Validator** | — | — | — | — | — | — | — | — | ✅ |
| **Content Performance Monitor** | — | — | — | — | — | — | — | — | ✅ |
| **Schema Markup (JSON-LD)** | ✅ | ✅ | ✅ | — | — | — | — | — | ✅ |
| **WooCommerce SEO** | ✅ | ✅ | ✅ | — | — | — | — | — | ✅ |
| **Rank Tracking** | ✅ | — | — | — | — | — | ✅ | — | ✅ |
| **Redirections (301/302)** | ✅ | — | ✅ | — | — | — | — | — | ✅ |
| **404 Monitor** | ✅ | — | — | — | — | — | — | — | ✅ |
| **Smart Internal Linking** | ✅ | — | — | — | — | — | — | — | ✅ |
| **Backlink Analyzer** | ✅ | — | — | — | — | — | — | — | ✅ |
| **Plagiarism Check** | — | — | — | — | — | — | — | — | ✅ |
| **SEO Revisions** | — | — | — | — | — | — | — | — | ✅ |
| **Content Decay Alert** | — | — | — | — | ✅ | — | — | — | ✅ |
| **Local SEO** | ✅ | ✅ | ✅ | — | — | — | — | — | ✅ |
| **Bulk Optimizer** | — | — | — | ✅ | — | — | — | — | ✅ |
| **Readability Score (NL)** | — | ✅ | — | — | — | — | — | — | ✅ |
| **AI Content Rewriter** | — | — | — | ✅ | — | — | — | — | ✅ |
| **IndexNow Integration** | ✅ | — | — | — | — | — | — | — | ✅ |
| **GSC Dashboard** | ✅ | — | — | — | — | — | — | — | ✅ |
| **SERP Feature Tracker** | — | — | — | — | — | — | — | — | ✅ |
| **Technical SEO Auditor** | — | — | — | — | — | — | — | — | ✅ |
| **Competitor Research** | — | — | — | — | — | — | — | — | ✅ |
| **A/B Testing (Split Testing)** | — | — | — | — | — | — | — | — | ✅ |
| **Hreflang / Multilang** | ✅ | ✅ | — | — | — | — | — | — | ✅ |
| **Video/News Sitemaps** | ✅ | ✅ | ✅ | — | — | — | — | — | ✅ |
| **SaaS License Gating** | — | — | — | — | — | — | — | — | ✅ |

---

## Technical Notes

- **Namespace:** `SSEOAIClient`
- **Autoloader:** PSR-4 style → `includes/{filename}.php`
- **REST namespace:** `sseo-ai/v1`
- **Option prefix:** `sseo_ai_client_`
- **Cron hooks:** `sseo_ai_rank_check_cron`
- **Dependencies:** `wp-api-fetch`, `jquery` for admin JS
- **Current Version:** 1.2.0

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Active SaaS Dashboard connection for AI features
- Google PageSpeed API key (optional, for PageSpeed module)

# SSEO AI Client - Feature Tier Mapping

> Complete overview of all 50+ features and their availability per license tier.

---

## License Tiers

| Tier | Description | Price Indication |
|------|-------------|------------------|
| **Free** | Basic SEO features only | Free |
| **Starter** | Core SEO + essential tools | €9/month |
| **Professional** | Advanced SEO + AI tools | €29/month |
| **Business** | Full AI content suite | €79/month |
| **Agency** | Multi-site + white-label | €199/month |
| **Trial** | Full features for 14 days | Free |
| **DEV** | **Development/Testing** - All features, unlimited API | Internal Use Only |

### DEV Tier Details

The **DEV tier** is designed for:
- Plugin development and testing
- Demo environments
- Internal QA testing
- Pre-release validation

**Characteristics:**
- ✅ **ALL 50+ features** enabled (same as Agency)
- ✅ **Unlimited API calls** (no rate limiting)
- ✅ **No expiration** (when paired with `lifetime` license type)
- ✅ **No upgrade prompts** (all menus visible)
- 🔒 **Internal use only** - not for client distribution

**How to activate:**
Set license tier to `dev` in the SaaS dashboard or via database:
```sql
UPDATE wp_options SET option_value = 'dev' WHERE option_name = 'sseo_ai_client_license_tier';
```

---

## Feature Matrix

### Core SEO (All Tiers)

| Feature | File | Free | Starter | Pro | Business | Agency | **DEV** | Description |
|---------|------|:----:|:-------:|:---:|:--------:|:------:|:------:|-------------|
| TruSEO Score | `truseoscore.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Real-time on-page SEO analysis 0-100 score |
| Smart Tags | `smarttags.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | AI-generated meta tags with dynamic variables |
| XML Sitemap | `sitemapgenerator.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Auto-generated XML sitemap with pings |
| Extended Sitemaps | `extendedsitemaps.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Video, News, Image, RSS, Author sitemaps |
| Robots.txt Editor | `robotstxt.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Visual robots.txt with AI suggestions |
| Open Graph | `opengraph.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Facebook OG + Twitter Cards |
| Canonical URLs | `canonicalurl.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Automatic canonical management |
| Breadcrumbs | `breadcrumbs.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | SEO breadcrumbs with JSON-LD schema |
| SEO Dashboard | `seodashboard.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Site-wide SEO health score |
| Hreflang | `hreflang.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Multi-language SEO with WPML/Polylang support |
| Role Permissions | `rolepermissions.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Granular user capabilities |
| LSI Keywords | `lsikeywords.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | AI-generated semantic keywords |
| PageSpeed | `pagespeedclient.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Google PageSpeed Insights integration |
| Readability | `readabilityscore.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Flesch-Kincaid + Dutch support |
| IndexNow | `indexnow.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Instant search engine notifications |
| External Integrations | `externalintegrations.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Slack, Zapier, webhooks, Notion |
| Content Performance | `contentperformancemonitor.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Content analytics & tracking |
| Content Calendar | `contentcalendar.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Editorial calendar |
| Smart Internal Linking | `smartinternallinking.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Auto internal link suggestions |
| E-E-A-T Validator | `eeatvalidator.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Experience/Expertise/Authority/Trust checks |
| Video SEO | `videoseo.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | Video schema + transcript generation |
| FAQ Schema | `faqschema.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | FAQ structured data |
| AI Image Generator | `aiimagegenerator.php` | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | AI featured & social images |

### Starter+ Features

| Feature | File | Starter | Pro | Business | Agency | **DEV** | Description |
|---------|------|:-------:|:---:|:--------:|:------:|:------:|-------------|
| Link Assistant | `linkassistant.php` | ✅ | ✅ | ✅ | ✅ | ✅ | AI internal linking suggestions |
| Redirect Manager | `redirectionmanager.php` | ✅ | ✅ | ✅ | ✅ | ✅ | 301/302 redirects with import |
| Image Alt Generator | `imagealtgenerator.php` | ✅ | ✅ | ✅ | ✅ | ✅ | AI alt text for images |
| Content Rewriter | `contentrewriter.php` | ✅ | ✅ | ✅ | ✅ | ✅ | AI content rewriting modes |

### Professional+ Features

| Feature | File | Pro | Business | Agency | **DEV** | Description |
|---------|------|:---:|:--------:|:------:|:------:|-------------|
| Schema Markup | `schemamarkup.php` | ✅ | ✅ | ✅ | ✅ | JSON-LD structured data (10+ types) |
| Local SEO | `localseo.php` | ✅ | ✅ | ✅ | ✅ | Google Business Profile integration |
| 404 Monitor | `notfoundmonitor.php` | ✅ | ✅ | ✅ | ✅ | Real-time 404 tracking |
| Rank Tracker | `ranktracker.php` | ✅ | ✅ | ✅ | ✅ | Daily SERP position tracking |
| SEO Report Export | `seoreportexport.php` | ✅ | ✅ | ✅ | ✅ | CSV/PDF export |
| WooCommerce SEO | `woocommerceseo.php` | ✅ | ✅ | ✅ | ✅ | Product schema + AI descriptions |
| Content Optimizer | `contentoptimizer.php` | ✅ | ✅ | ✅ | ✅ | MarketMuse/SurferSEO killer - NLP scoring |
| SERP Competitor | `serpcompetitor.php` | ✅ | ✅ | ✅ | ✅ | NeuronWriter competitor analysis |
| Topic Clusters | `topiccluster.php` | ✅ | ✅ | ✅ | ✅ | MarketMuse cluster analysis + AI content gen |
| Keyword Difficulty | `keyworddifficulty.php` | ✅ | ✅ | ✅ | ✅ | Personalized KD (MarketMuse style) |
| Content Brief | `contentbrief.php` | ✅ | ✅ | ✅ | ✅ | SERP-based content briefs |
| Keyword Explorer | `keywordexplorer.php` | ✅ | ✅ | ✅ | ✅ | Keyword expansion + clustering |
| GSC Dashboard | `gscdashboard.php` | ✅ | ✅ | ✅ | ✅ | Google Search Console integration |
| SERP Feature Tracker | `serpfeaturetracker.php` | ✅ | ✅ | ✅ | ✅ | Track featured snippets, etc. |
| Backlink Analyzer | `backlinkanalyzer.php` | ✅ | ✅ | ✅ | ✅ | Backlink analysis & monitoring |
| Competitor Research | `competitorresearch.php` | ✅ | ✅ | ✅ | ✅ | Deep competitor analysis |
| International SEO | `internationalseo.php` | ✅ | ✅ | ✅ | ✅ | Hreflang + geo-targeting |
| Technical SEO Auditor | `technicalseoauditor.php` | ✅ | ✅ | ✅ | ✅ | Full technical SEO audit |
| Advanced Backlinks | `advancedbacklinks.php` | ✅ | ✅ | ✅ | ✅ | Backlink gap analysis |

### Business+ Features

| Feature | File | Business | Agency | **DEV** | Description |
|---------|------|:--------:|:------:|:------:|-------------|
| AI Content Writer | `contentwriter.php` | ✅ | ✅ | ✅ | Full AI article generation |
| AI Content Repurposer | `airepurposer.php` | ✅ | ✅ | ✅ | Transform content to new formats |
| Bulk AI Optimizer | `bulkactions.php` | ✅ | ✅ | ✅ | Bulk meta generation |
| Content Decay Monitor | `contentdecay.php` | ✅ | ✅ | ✅ | Detect declining content |
| Audit Service | `auditservice.php` | ✅ | ✅ | ✅ | Comprehensive content audits |

### Agency-Only Features

| Feature | File | Agency | **DEV** | Description |
|---------|------|:------:|:------:|-------------|
| SEO Revisions | `seorevisions.php` | ✅ | ✅ | Track all SEO meta changes |
| Plagiarism Checker | `plagiarismchecker.php` | ✅ | ✅ | AI-powered originality check |
| White Label Manager | `whitelabelmanager.php` | ✅ | ✅ | Custom branding |

---

## Menu Items by Tier

| Menu | Slug | Min Tier |
|------|------|----------|
| Connection | `ai-seo-client` | Free |
| Dashboard | `ai-seo-dashboard` | Free |
| Content Calendar | `ai-seo-content-calendar` | Free |
| AI Tools | `ai-seo-ai-tools` | Free |
| Link Manager | `ai-seo-link-manager` | Free |
| Sitemaps | `ai-seo-sitemaps` | Free |
| Integrations | `ai-seo-integrations` | Free |
| Topic Clusters | `ai-seo-topic-clusters` | Professional |
| Site Audit | `ai-seo-site-audit` | Professional |
| Rank Tracker | `ai-seo-rank-tracker` | Professional |
| Search Console | `ai-seo-gsc` | Professional |
| Settings | `ai-seo-settings` | Free |

**Note:** DEV tier users see ALL menus (no restrictions).

---

## License Types

| Type | Description | Behavior |
|------|-------------|----------|
| `test` | Development testing | No validation, unlimited features |
| `trial` | 14-day trial | Full features, expires after 14 days |
| `paid` | Standard subscription | Monthly/yearly billing, auto-renew |
| `lifetime` | One-time purchase | Never expires, one-time fee |

---

## API Limits by Tier

| Tier | API Calls/Month | Rate Limit (req/min) | Concurrent Jobs |
|------|-----------------|:--------------------:|-----------------|
| Free | 100 | 10 | 1 |
| Starter | 500 | 30 | 2 |
| Professional | 2,000 | 60 | 5 |
| Business | 10,000 | 120 | 10 |
| Agency | Unlimited | 300 | 25 |
| **DEV** | **Unlimited** | **Unlimited** | **Unlimited** |

---

## SaaS Portal: Advanced Feature Toggles

The SaaS Portal now supports **per-license feature overrides**, allowing administrators to enable/disable specific features for individual licenses beyond their default tier assignments.

### How It Works

1. **Tier Defaults**: Each license tier has a default set of features (as documented above)
2. **License Overrides**: Administrators can override these defaults per license
3. **Client Sync**: Feature lists are synchronized to client plugins during license validation
4. **Runtime Check**: Client plugins check features against the SaaS-provided list

### Managing Features (SaaS Portal)

1. Go to **Licenses → All Licenses**
2. Find the license you want to customize
3. Click **"Manage Features"** button
4. Toggle features on/off as needed
5. Click **"Save Feature Overrides"**

### Feature Override Rules

| Scenario | Behavior |
|----------|----------|
| Feature enabled in tier, no override | ✅ Feature available (default tier behavior) |
| Feature enabled in tier, overridden to OFF | ❌ Feature disabled (override takes precedence) |
| Feature NOT in tier, overridden to ON | ✅ Feature enabled (grant extra feature) |
| Feature NOT in tier, no override | ❌ Feature not available (default tier behavior) |

### REST API Endpoints (Admin Only)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/sseo-ai/v1/license/features?license_key={key}` | GET | Get feature toggle data for a license |
| `/wp-json/sseo-ai/v1/license/features` | POST | Update feature overrides |
| `/wp-json/sseo-ai/v1/features/all` | GET | Get all available features |

### Client Plugin Integration

The client plugin automatically:
1. Receives the enabled features list during license validation
2. Stores it in the `sseo_ai_client_enabled_features` option
3. Uses this list for all `hasFeature()` checks
4. Falls back to tier-based features if no override list exists

### Use Cases

- **Grant specific Professional features** to a Starter license customer
- **Temporarily disable expensive features** (e.g., AI Writer) for a high-usage customer
- **Create custom feature bundles** beyond standard tiers
- **Enable beta features** for specific customers only
- **Troubleshoot by disabling specific features**

---

## Notes

- **Trial tier** = Professional features for 14 days
- **Free tier** currently not fully implemented - redirects to license activation
- **DEV tier** = All features + unlimited API (internal use only)
- **PageSpeed Client** requires Google API key (free tier has 25 queries/day limit)
- **GSC Dashboard** requires OAuth2 setup in Integrations
- **AI features** use OpenAI API through SaaS proxy
- **DEV tier** should NEVER be distributed to clients - for internal development only
- **Feature toggles** require SaaS Portal plugin version 1.2.0+

---

*Last updated: April 17, 2026*  
*Maintained by: Cascade AI*

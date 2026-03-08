# AI SEO Assistant Pro

A comprehensive WordPress SEO plugin with AI-powered content generation, SERP tracking, technical SEO audits, and content decay detection. Targets PHP 8.1+ and WordPress 6.4+.

## 🚀 Key Features

### SERP & Keyword Intelligence
- **Multiple SERP Providers**: SerpApi, DataForSEO, or self-scrape (cURL-based)
- **Automatic Snapshots**: Hourly cron tracking of keywords with CSV export
- **Competitor Gap Analysis**: Compare your rankings vs competitors
- **Topical Map & Keyword Clustering**: Discover content opportunities
- **AI Overview Tracking**: Monitor Google AI Overview presence in SERPs

### AI Content Generation
- **Gutenberg Sidebar**: AI-powered content outline generator
- **Editor Actions**: Rewrite, Improve, Expand, CTA, FAQ generation
- **AI Image Generation**: Generate featured images with OpenAI
- **Smart Meta + FAQ**: Auto-generate SEO meta descriptions and FAQ sections
- **Bulk Actions**: Generate meta/FAQ for multiple posts at once
- **Prompt Library**: Custom prompts with tone and preset selection

### Technical SEO
- **Technical Audit**: HEAD checks, canonical, hreflang, JSON-LD validation
- **PageSpeed Insights**: Mobile & desktop performance monitoring
- **Sitemap Generator**: XML sitemaps with video and news extensions
- **Schema Markup**: JSON-LD structured data support
- **Robots.txt Editor**: Manage crawler access rules
- **Redirect Manager**: 301/302 redirects with hit tracking
- **404 Monitor**: Track and resolve broken links

### Content Optimization
- **TruSEO Score**: Real-time content quality scoring
- **Link Assistant**: Internal linking suggestions + orphan page detection
- **Content Decay Detection**: ⭐ Automatic alerts for declining rankings
- **Cannibalization Detection**: Find keyword conflicts across posts
- **Thin Content Audit**: Identify posts lacking SEO elements

### License Management (Pro)
- **Tier-based Licensing**: Free, Trial, Starter, Professional, Business, Agency tiers
- **Feature Gating**: Control feature access by license tier
- **Trial Mode**: 14-day trial with automatic expiration
- **Self-hosted License Server**: Full control over licensing
- **Remote Activation**: License key activation/deactivation

### Modern Admin UI
- **Redesigned Dashboard**: Gradient header, stat cards, quick actions
- **Visual Charts**: Chart.js powered analytics with trend tracking
- **Status Indicators**: Color-coded badges for health/status
- **Responsive Design**: Mobile-friendly admin interface
- **Empty States**: User-friendly empty data messaging

## 📦 Installation

1. Copy the `ai-seo-assistant` folder into `wp-content/plugins/`
2. Activate the plugin in WP Admin
3. Start your **14-day free trial** (no credit card required)
4. Upgrade to paid tier after trial or when ready

## ⚙️ Configuration

### SERP Providers

**SerpApi** (Recommended)
```
Engine: Google
Endpoint: https://serpapi.com/search
Requires: API key
```

**DataForSEO**
```
Endpoint: https://api.dataforseo.com/v3/serp/google/organic/live/regular
Requires: Login + Password (Basic Auth)
```

**Self-scrape** (Free option)
```
Lightweight DOM parsing
Respects robots.txt
No API costs
```

### LLM Providers (with auto-fallback)
1. **OpenAI** - GPT-4.1 (default)
2. **Anthropic** - Claude Opus 4.5
3. **Mistral** - Mistral Large

Configure API keys in AI SEO settings. Fallback order: selected → OpenAI → Anthropic → Mistral.

### Google Search Console (GSC)
- Configure OAuth credentials (client ID + secret)
- Set redirect URI: `{site}/wp-json/aiseoassistant/v1/gsc-callback`
- Optional: Use proxy endpoint for multi-site setups

## 🎯 Usage Examples

### Track Keywords
```php
add_filter('aiseoassistant_tracked_keywords', function () {
    return ['ai seo plugin', 'best seo ai', 'content optimization'];
});
```

### Listen for Snapshots
```php
add_action('aiseoassistant_snapshot_saved', function ($keyword, $results) {
    // Process snapshot data
    error_log("Snapshot saved: {$keyword}");
}, 10, 2);
```

### Check License Tier
```php
$plugin = \AISEOAssistant\Plugin::instance();
if ($plugin->featureGate->canUseFeature('content_decay')) {
    // Feature available
}
```

## 📊 Content Decay Detection

Automatically monitors your content performance and alerts you to declining rankings:

- **Severity Levels**: Low, Medium, High, Critical
- **Auto-suggestions**: Content refresh, title optimization, internal links
- **Trend Charts**: 30-day ranking visualization in post editor
- **Daily Cron**: Automatic decay analysis
- **Admin Alerts**: Notice banners for critical issues

Decay triggers:
- **High**: Position drop >5 spots OR <80% average traffic
- **Critical**: Position drop >10 spots OR <50% average traffic

## 🔐 License Tiers

**Note**: Prices exclude API costs. You bring your own SerpApi/DataForSEO/OpenAI/Anthropic API keys.

| Tier | Price | Features | Max Keywords |
|------|-------|----------|--------------|
| Trial | €0 | Full access for 14 days | 25 keywords |
| Starter | €99/mo | Decay detection, link assistant, bulk actions | 50 keywords |
| Professional | €249/mo | GSC integration, extended sitemaps, team features | 200 keywords |
| Business | €499/mo | White-label, priority support, unlimited bulk | 500 keywords |
| Enterprise | €999/mo | Multi-site, API access, custom integrations | Unlimited |

### 💰 Pricing Strategy & ROI

**Market Comparison**:
- WP SEO AI: €2,500 onboarding + €5,000/yr = €7,500 eerste jaar
- **Our Enterprise**: €999/yr = **86% cheaper** met vergelijkbare features

**Value-Based Pricing** (wat levert het op):
| Bedrijfsgrootte | SEO Budget | Plugin ROI | Aanbevolen Tier |
|-----------------|------------|------------|-----------------|
| Startup (1-5 medewerkers) | €500-1,500/maand | 3-5x | Starter €99/mo |
| MKB (10-50 medewerkers) | €2,000-5,000/maand | 5-8x | Professional €249/mo |
| Enterprise (100+) | €10,000+/maand | 8-15x | Business/Enterprise |

**Typische ROI berekening**:
- 1 positie omhoog in Google = +30% traffic gemiddeld
- 50 keywords verbeteren met 3 posities = aanzienlijke traffic boost
- Conversie van 2% → 3% op 10K bezoekers = 50 extra leads/maand
- **Waarde**: €2,000-10,000+/maand voor €99-999 plugin kosten

## 🏗️ Architecture

### Cron Hooks
- `aiseoassistant_serp_snapshot` - Hourly keyword snapshots
- `aiseoassistant_llm_health` - Daily LLM health check
- `aiseoassistant_serp_health` - Daily SERP provider check
- `aiseoassistant_ai_overview` - Daily AI Overview detection
- `aiseoassistant_license_check` - Daily license validation
- `aiseoassistant_decay_check` - Daily content decay analysis

### Database Tables
- `wp_aiseoassistant_snapshots` - SERP snapshots
- `wp_aiseoassistant_health` - Health check logs
- `wp_aiseoassistant_ai_overviews` - AI Overview tracking
- `wp_aiseoassistant_psi` - PageSpeed Insights data
- `wp_aiseoassistant_redirects` - Redirect rules
- `wp_aiseoassistant_404s` - 404 error log
- `wp_aiseoassistant_seo_revisions` - SEO metadata history
- `aiseo_content_decay` - Decay alerts
- `aiseo_position_trends` - Historical position data

### REST API Endpoints
- `POST /wp-json/aiseoassistant/v1/outline` - Generate content outline
- `POST /wp-json/aiseoassistant/v1/editor-action` - Editor AI actions
- `POST /wp-json/aiseoassistant/v1/editor-image` - Generate images
- `GET/POST/DELETE` - License management endpoints
- `GET` - Content decay data endpoints

## 🔧 Developer Hooks

### Filters
- `aiseoassistant_tracked_keywords` - Modify tracked keywords
- `aiseoassistant_editor_presets` - Modify prompt presets
- `aiseoassistant_tier_features` - Modify tier capabilities

### Actions
- `aiseoassistant_snapshot_saved` - Triggered after snapshot save
- `aiseoassistant_decay_detected` - Triggered on new decay alert
- `aiseoassistant_license_activated` - Triggered on license activation
- `aiseoassistant_license_deactivated` - Triggered on license deactivation

## 📋 Changelog

### v0.2.0
- ✅ Content Decay Detection with trend charts and auto-suggestions
- ✅ License Management system with 6 tiers
- ✅ Modern admin UI redesign with stat cards and charts
- ✅ TruSEO Score integration
- ✅ Extended sitemaps (video + news)
- ✅ SEO Revisions tracking

### v0.1.0
- Initial release with SERP tracking, AI content generation, technical audits

## 📝 License

GPL-2.0+ - See LICENSE file for details.

Pro features require active license. Self-hosted license server option available.

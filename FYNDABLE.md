# Fyndable Smart SEO — Complete documentatie

> **Eén gezaghebbend document voor het hele Fyndable-platform: architectuur, installatie, gebruikershandleiding, feature-catalogus, API-referentie, security en operations.**
>
> Dit bestand vervangt de eerdere losse documenten (README, SETUP, ARCHITECTURE, GEBRUIKERSHANDLEIDING, AUDIT-REPORT, analyse, report-claude, FAQ en tools/UPDATES-SERVER). Plugin-specifieke details staan in `wp-content/plugins/fyndable-client/README.md` en `CHANGELOG.md`. De interactieve documentatiesites staan in `docs/` en `how-to/`.
>
> Laatste bijwerking: 2026-10-16 — inclusief bevindingen uit de security-audit van deze datum.

---

## Inhoudsopgave

1. [Wat is Fyndable](#1-wat-is-fyndable)
2. [Systeemarchitectuur](#2-systeemarchitectuur)
3. [Installatie & configuratie](#3-installatie--configuratie)
4. [Licenties, tiers & prijzen](#4-licenties-tiers--prijzen)
5. [Feature-catalogus](#5-feature-catalogus)
6. [Gebruikershandleiding](#6-gebruikershandleiding)
7. [SaaS-beheer & portals](#7-saas-beheer--portals)
8. [API-referentie](#8-api-referentie)
9. [Security & privacy](#9-security--privacy)
10. [Updates & deployment](#10-updates--deployment)
11. [Monitoring & performance](#11-monitoring--performance)
12. [Troubleshooting & FAQ](#12-troubleshooting--faq)
13. [Roadmap & bekende beperkingen](#13-roadmap--bekende-beperkingen)
14. [Bijlage: repository-layout](#14-bijlage-repository-layout)

---

## 1. Wat is Fyndable

Fyndable Smart SEO is een **multi-tenant SaaS SEO-platform** voor WordPress. Het brengt de complete SEO-werkstroom — onderzoeken, schrijven, optimaliseren, technisch op orde brengen, monitoren en rapporteren — in één omgeving binnen WordPress, en breidt dat uit met **GEO/AI-zoekmachines** (zichtbaarheid in ChatGPT, Gemini, Claude en Perplexity).

Het platform vervangt daarmee een stack van losse tools: RankMath/Yoast (on-page), SurferSEO/MarketMuse (content-optimalisatie), Jasper/Copy.ai (AI-schrijven), plus een eigen laag voor AI-zoekmachine-zichtbaarheid die geen van die tools heeft.

### Kerncomponenten

| Component | Locatie | Rol |
|-----------|---------|-----|
| **Client-plugin** | `wp-content/plugins/fyndable-client` | ~115 modules op de WordPress-site van de klant: SEO-analyse, AI-content, rank tracking, GEO, integraties |
| **SaaS Dashboard** | `wp-content/plugins/fyndable-saas-dashboard` | Centraal platform: licenties, tenants, billing (Stripe/Mollie), API-gateway, white-label, portals |
| **GEO-scan plugin** | `fyndable-geo-scan/` | Losse plugin voor marketing-site: publieke GEO-scan via shortcode |
| **Shopify app** | `shopify-app/` | Laravel 11 app voor Shopify-merchants (zelfde SaaS-backend) |
| **Update-server** | `updates.fyndable.ai` + `tools/` | Distribueert client-plugin-updates naar klantsites |

### Het kernverhaal

De gebruiker voert zijn onderwerp, markt en doelgroep in; de AI analyseert waar daadwerkelijk op gezocht wordt (keywords, vragen, zoekintentie, concurrentie) en vertaalt dat naar concrete acties: betere titels en meta's, ontbrekende termen, complete nieuwe artikelen, interne links, structured data en technische fixes. Daarna bewaakt het platform de resultaten met rank tracking, content-decay-detectie en dashboards. Voor bureaus is alles white-label inzetbaar.

**Belangrijk voor gebruikers:** ze hoeven zelf géén OpenAI- of SERP-API-accounts te regelen. Alle AI- en SERP-verkeer loopt via het SaaS-platform; één licentiesleutel ontsluit alles.

---

## 2. Systeemarchitectuur

```
┌──────────────────────────────────────────────────────────┐
│            SaaS Dashboard (portal.fyndable.ai)            │
│  ┌────────────────────────────────────────────────────┐  │
│  │ Licenties & Tenants    │ Stripe/Mollie billing     │  │
│  │ API-gateway            │ AI-proxy (OpenRouter/OAI) │  │
│  │ SERP-proxy             │ (DataForSEO + SerpAPI)    │  │
│  │ GEO-scanner            │ (async queue, multi-model)│  │
│  │ Google OAuth proxy     │ (GSC/GA4/Ads)             │  │
│  │ Update-server          │ White-label builder       │  │
│  │ Agency + Customer      │ portals                   │  │
│  └────────────────────────────────────────────────────┘  │
└────────────────────────────┬─────────────────────────────┘
                             │ HTTPS REST API
        ┌────────────────────┼────────────────────┐
        ▼                    ▼                    ▼
┌───────────────┐   ┌────────────────┐   ┌──────────────────┐
│ Klant-WP sites│   │ Shopify app    │   │ Marketing-site   │
│ (fyndable-    │   │ (Laravel,      │   │ (fyndable-geo-   │
│  client)      │   │  shopify-app/) │   │  scan plugin)    │
│ ~115 modules  │   │                │   │ Publieke scan    │
└───────────────┘   └────────────────┘   └──────────────────┘
```

### Communicatiemodel

1. **Licentieactivatie** — client stuurt `license_key` + `site_url` naar `/license/activate`; het dashboard maakt/verrijkt een tenant en retourneert `tenant_key`, tier, limits, white-label data en image-API-config.
2. **Feature calls** — elke SaaS-call bevat `license_key` + `tenant_key`; het dashboard valideert het paar (`hash_equals`), controleert tier en rate limits, proxyt naar de provider en logt usage/kosten.
3. **Google OAuth** — de client ziet nooit de OAuth `client_secret`; token-exchange en -refresh lopen via proxy-endpoints (`/google/exchange`, `/google/refresh`).
4. **Updates** — client checkt via `/updates/check` bij het dashboard; het dashboard haalt metadata op van `updates.fyndable.ai`.
5. **Publieke GEO-scans** — marketing-site plugin gebruikt een gedeelde `X-Fyndable-Scan-Key` met IP-rate-limit en idempotente scan-creatie.

### Databaseschema (SaaS)

```sql
sseo_ai_tenants          — tenant_key, name, email, domain, tier, status,
                           payment_status, license_key, limits, expires_at,
                           platform (wordpress|shopify|webflow)
sseo_ai_tenant_settings  — per-tenant key/value settings
sseo_ai_tenant_usage     — api_calls, api_cost, serp_requests per periode
sseo_ai_license_keys     — license_key, type, tier, status, limits
sseo_ai_geo_scans        — url, keywords, status, progress, report, source,
                           email/consent, meta (incl. public_token)
```

Client-side komen daar eigen tabellen bij voor keywords, rank history, A/B-tests, redirects, 404-log, LLM-tracker, snapshots en SEO-revisions.

### Async GEO-scan architectuur

Scans draaien asynchroon (fixed gateway-timeouts): `GeoScanRepository::insertQueued()` → `GeoScanQueue` → WP-Cron `sseo_geo_scan_run_job` → `GeoScanner::scan()` schrijft voortgang naar de rij → frontends pollen status. Een 5-min sweep requeue't vastgelopen jobs. Retentie: admin-scans 7 dagen, website-scans 90 dagen.

**Multi-model-scan:** indien ingeschakeld draait dezelfde pagina sequentieel door meerdere LLM's met model-specifieke prompts (ChatGPT → Bing/conversational, Gemini → Knowledge Graph, Claude → entity clarity); resultaten worden gemerged (gemiddelde scores, gededupliceerde bevindingen). Graceful degradation: falende modellen worden overgeslagen; alle modellen falen → error.

---

## 3. Installatie & configuratie

### 3.1 Vereisten

| | SaaS-dashboard | Client-site |
|---|---|---|
| WordPress | 6.0+ | 6.0+ |
| PHP | 8.1+ | 8.1+ |
| MySQL | 5.7+ | 5.6+ |
| HTTPS | verplicht | aanbevolen |
| PHP memory | 512MB+ | 256MB+ |
| Cron | echte cron naar `wp-cron.php` (scans/queue) | idem voor ApexFlow/ranks |

### 3.2 SaaS-dashboard installeren

1. Installeer WordPress op een eigen (sub)domein, bijv. `portal.fyndable.ai`.
2. Upload `wp-content/plugins/fyndable-saas-dashboard` → **Plugins → Activate**. De tabellen worden automatisch aangemaakt.
3. Ga naar **SaaS → Settings** en configureer:
   - **AI-providers**: OpenRouter API-key (centrale proxy), eventueel OpenAI direct, OpenArt (image-gen).
   - **SERP-providers**: DataForSEO credentials (primair) en/of SerpAPI key (fallback).
   - **Billing**: Stripe secret + `sseo_ai_saas_stripe_webhook_secret` (verplicht, anders worden webhooks geweigerd — fail-closed) en/of Mollie API-key.
   - **Google OAuth**: `client_id`/`client_secret` voor de GSC/GA4/Ads-proxy; Google Ads developer-token optioneel.
   - **Tier-pricing**: `ai_seo_saas_{tier}_price` per tier (Settings → Tier Pricing & Limits).
   - **Self-serve checkout**: `sseo_ai_saas_self_serve_enabled` (default uit), `sseo_ai_saas_trial_enabled`, `sseo_ai_saas_trial_days` (default 14).
   - **GEO-scan**: admin-model (`sseo_ai_saas_geo_model`), website-scan-model, multi-model toggles, en `X-Fyndable-Scan-Key` voor de publieke scan-API (GEO Scan admin → integratiekaart).
   - **Update-server**: `sseo_ai_saas_update_server_url` → `https://updates.fyndable.ai` (zie §10).
4. Stel de klant-portal-pagina in (`sseo_ai_saas_customer_portal_page`) voor `/dashboard/` en gebruik desgewenst de `[fyndable_login]`-shortcode voor een gebrande login.
5. Controleer dat WP-Cron wordt getriggerd (echte cron naar `wp-cron.php` aanbevolen; anders lopen GEO-queue en sweeps alleen op bezoekersverkeer).

### 3.3 Client-plugin installeren

1. Upload `fyndable-client` naar `/wp-content/plugins/` op de klantsite en activeer.
2. Ga naar **Fyndable → Connection**, vul Dashboard URL (`https://portal.fyndable.ai`) en license key in → **Activate License**.
3. De client slaat `tenant_key`, tier, white-label data en `portal_url` op; features verschijnen per tier.
4. Doorloop de **Onboarding Wizard**: basis-SEO, post types, optionele Google-koppeling (GSC/GA4/Ads via de OAuth-proxy).
5. Optioneel: **Settings → Local Business** (adres + coördinaten) voor Local SERP en lokale-intentie-detectie in ApexFlow.

### 3.4 GEO-scan-plugin op de marketing-site

1. Installeer `fyndable-geo-scan/` op de publieke site.
2. Settings → GEO Scan: portal-URL + API-key (= `sseo_ai_saas_website_scan_key` van het dashboard).
3. Plaats de shortcode `[fyndable_geo_scan]` op een landingspagina. Het formulier (URL + max 2 keywords + e-mail + consent + honeypot) proxyt naar `POST /ai-seo-saas/v1/public/geo-scan`.

### 3.5 Agency/white-label flow

- Agency-accounts krijgen de rol `agency_partner` en een eigen **Agency Portal** in wp-admin (sub-tenants, eigen licenties, white-label settings, download van een gerebrandde client-plugin-zip via de White-Label Package Builder).
- Customers krijgen de rol `fyndable_customer` en een frontend portal op `/dashboard/` (of de geconfigureerde portal-pagina).
- White-label synchroniseert naar clients bij licentieactivatie; per-tenant overrides via tenant-settings (`white_label_brand`, `enable_whitelabel`).

---

## 4. Licenties, tiers & prijzen

### Licentie-types

| Type | Betekenis |
|------|-----------|
| `test` | Intern testen, onbeperkt |
| `free` | Gratis licenties (marketing/promotie) |
| `trial` | Tijdgebonden (default 14 dagen), auto-expire |
| `paid` | Standaard abonnement |
| `lifetime` | Verloopt nooit |

Licentiekey-formaat: `SSEO-…` (gegenereerd door `LicenseKeyGenerator`, entropie ~96 bits — zie backlog §13).

### Tiers en limits (server-side afgedwongen)

Defaults uit `SignupCheckout::getTierLimits()` en `SaaSSettings::tierPrice()` — alle bedragen configureerbaar via **Tier Pricing & Limits**:

| Tier | Default prijs/mnd | Sites | Rate limit (req/min) | AI-calls/mnd | GEO-audits/mnd |
|------|-------------------|-------|----------------------|--------------|----------------|
| Early Adopters | €14,50 | 1 | — | 500 | 5 |
| Starter | €29 | 1 | 60 | 500 | 5 |
| Professional | €79 | 1 | 120 | 2.000 | 35 |
| Business | €199 | 3 | 300 | 5.000 | 90 |
| Agency | €499 | 5+ | 600 | 20.000 | onbeperkt |
| Trial | €0 (14 dagen) | 1 | 200 | 5.000 | — |
| DEV | intern | ∞ | ∞ | ∞ | ∞ |

> **Let op:** oudere documenten noemden afwijkende prijzen (9/29/79/199 resp. 99/199/299/499). De bovenstaande defaults uit de code zijn leidend; de werkelijke prijs op een installatie is wat in **Tier Pricing & Limits** staat ingesteld. Agency is niet self-serve: de checkout toont "Contact Us".

### Feature-gating per tier (client)

| Categorie | Free | Starter | Professional | Business | Agency |
|-----------|:----:|:-------:|:------------:|:--------:|:------:|
| Core SEO (TruSEO, sitemaps, OG, keywords, ideas) | ✓ | ✓ | ✓ | ✓ | ✓ |
| Content tools (Rewriter, Alt-gen, Link Assistant) | — | ✓ | ✓ | ✓ | ✓ |
| Advanced SEO (Rank Tracker, GSC, Clusters, A/B, Schema, Local SERP) | — | — | ✓ | ✓ | ✓ |
| AI-generatie (Writer, Repurposer, Bulk, Image-gen) | — | — | — | ✓ | ✓ |
| Agency tools (Revisions, Plagiarism, White-label) | — | — | — | — | ✓ |

- **ApexFlow** (content-autopilot) vereist betaalde Professional+ — `trial` wordt hier bewust uitgesloten.
- **DEV-tier** is alleen voor interne development/testing — nooit naar klanten distribueren.
- **Demo Mode** (`sseo_ai_demo_mode`) bypassed licentiechecks en serveert fictieve data; alleen inschakelen op demo-omgevingen.

### Local SERP grid-limits

| Tier | Max grid |
|------|----------|
| Starter | geen local SERP |
| Professional | 3×3 |
| Business | 5×5 |
| Agency | 7×7 |

---

## 5. Feature-catalogus

Compleet overzicht van alle geïmplementeerde modules. Tier-markering: geen markering = alle tiers.

### 5.1 Core SEO & techniek

| Module | Bestand | Functie |
|--------|---------|---------|
| TruSEO Score | `truseoscore.php` | Realtime 0-100 on-page score: keyphrase, title, meta, koppen, alt-tags, links; SERP-preview en AI-suggesties |
| Post metabox | `postmetabox.php` | Gecentraliseerde SEO-metabox in de editor |
| Readability | `readabilityscore.php` | Flesch Reading Ease, Flesch-Kincaid, NL Flesch-Douma; passief, transition words, zinslengte |
| Schema Markup | `schemamarkup.php`, `faqschema.php` | JSON-LD: Article, FAQ, HowTo, Product, Review, Recipe, Event, LocalBusiness, Breadcrumb, Video; AI-gegenereerde FAQ-extractie |
| Breadcrumbs | `breadcrumbs.php` | Schema-breadcrumbs + `[aiseo_breadcrumbs]` shortcode |
| Canonical URLs | `canonicalurl.php` | Automatische canonicals + per-post override, cross-domain |
| Open Graph / Social | `opengraph.php`, `socialsharing.php` | OG/Twitter-cards, share-buttons, per-post overrides |
| Robots.txt | `robotstxt.php` | Visuele editor met suggesties |
| Sitemaps | `sitemapgenerator.php`, `extendedsitemaps.php` | XML-sitemap + video/news/image/RSS/author-sitemaps, ping bij publish |
| Hreflang / internationaal | `hreflang.php`, `internationalseo.php` | WPML/Polylang/TranslatePress-detectie, geo-targeting, meertalige sitemaps |
| Technical SEO Auditor | `technicalseoauditor.php` | Site-audit op 100+ criteria (crawlability, CWV, structured data, broken links) — Professional+ |
| 404 Monitor | `notfoundmonitor.php` | 404-tracking met referrer/UA, one-click redirect aanmaken |
| Redirect Manager | `redirectionmanager.php` | 301/302/307, regex-support, CSV import/export, hit-counters, auto-redirect bij slug-wijziging — Starter+ |
| IndexNow / DirectIndex | `indexnow.php`, `directindex.php` | Instant indexatie-notificaties naar Bing/IndexNow |
| WooCommerce SEO | `woocommerceseo.php` | Product-schema, AI productbeschrijvingen, categorie-SEO |
| Tech Checker | `techchecker.php` | Validatie JSON-LD, hreflang, robots, meta robots, redirect-chains |

### 5.2 Content & AI

| Module | Functie |
|--------|---------|
| Content Optimizer | NLP topic-model (30-50 gewogen termen), 0-100 score, term-heatmap, structuurscoring — Professional+ |
| Content Brief | SERP-gedreven brief: koppen, PAA-vragen, entiteiten, woordenaantal — Professional+ |
| AI Content Writer | Volledige artikelen: type, tone-of-voice, woordenaantal, sectie-voor-sectie, auto-interne links — Business+ |
| Content Rewriter | Herschrijven: SEO, leesbaarheid, expand, condense, parafraseer, toon-shift — Starter+ |
| Simple Content Generator | Snelle alinea's/CTA's in de editor |
| AI Repurposer | Blog → LinkedIn/nieuwsbrief/Instagram/samenvatting — Business+ |
| E-E-A-T Validator | Experience/Expertise/Authoritativeness/Trust-analyse, auteursprofielen |
| Fact Checker | Feitelijke claims controleren via LLM |
| Plagiarism Checker | Originaliteitsscore + AI-detectie — Agency |
| Brand Voice | Merkstem-profiel; quick fields op de ApexFlow-pagina |
| Prompt Template Library | Herbruikbare prompt-templates |
| AiseoAgent | Conversational SEO-assistent |
| Content Calendar | Redactionele kalender, workflow-statussen, toewijzing |
| Ideas | AI-contentideeën → draft/planning |
| Created Posts | Overzicht AI-posts: statussen, bulk publish/draft |
| Content Decay Monitor | Detecteert dalende pagina's via GSC; verversingsadvies — Business+ |
| Content Performance Monitor | Metrics over tijd; onderpresteerders |
| Post Auto-Cleaner | Ruimt AI-posts zonder traffic automatisch op (instelbare dagen/views) |
| Programmatic SEO | Template-gedreven massa-pagina's met variabelen |
| A/B Testing | Title/meta/content-varianten, cookie-split, 4 goal-types, live stats — Professional+ |
| Bulk Optimizer | Bulk-scan + AI-generatie ontbrekende titles/meta/OG — Business+ |

### 5.3 Keywords, rankings & concurrentie

| Module | Functie |
|--------|---------|
| Keywords DB | Keyword-bibliotheek, clusters, CSV import/export |
| Keyword Explorer | SERP n-gram-expansie + Jaccard-clustering — Professional+ |
| Keyword Difficulty | Gepersonaliseerde KD t.o.v. eigen site-authority; batch tot 20 — Professional+ |
| Rank Tracker | Dagelijkse posities, trends, alerts, land/taal — Professional+ |
| Cannibalization | Tab in Rank Tracker: posts die met elkaar concurreren (focus-keyphrase, tracked-keyword multi-match) |
| Local SERP | Local pack + geo-grid scans rond bedrijfsadres — Professional+ (grid-grootte per tier) |
| SERP Competitor | Top-20-analyse, topic-heatmap, content-gaps — Professional+ |
| SERP Feature Tracker | Featured snippets, PAA, knowledge panels — wint/verliest-alerts |
| SERP Change Monitor | Volgt SERP-veranderingen |
| Competitor Research | Domeinanalyse: keywords, content, backlinks, ads |
| Backlink Analyzer / Advanced Backlinks | Profiel, toxic links, anchor-distributie, opportunities (Ahrefs/SE Ranking/DataForSEO) |

### 5.4 GEO / AI-zoekmachines

| Module | Functie |
|--------|---------|
| GEO Content Score | Scoort content op AI-citeerbaarheid |
| GEO Readiness Scan | Volledige site-scan via SaaS (async, multi-model optioneel) |
| llms.txt / llms-full.txt | Genereert `/llms.txt` (markdown-index) en `/llms-full.txt` (volledige tekst) per llmstxt.org |
| LLM Tracker | Logt AI-crawler/bezoeken |
| Brand Visibility Tracker | Merkvermeldingen in AI-antwoorden |
| AI Optimization Tracker | Voortgang AI-zichtbaarheid |

### 5.5 Data, integraties & media

| Module | Functie |
|--------|---------|
| GSC Dashboard | Clicks/impressions/CTR/positie, top queries & pages (7/28/90d) — Professional+ |
| GA4 + Google Ads | Dashboards via dezelfde OAuth-proxy |
| Google OAuth (`gscoauth.php`) | OAuth2-flow, AES-256-GCM-encrypted refresh tokens, auto-refresh via SaaS-proxy |
| PageSpeed | Core Web Vitals (LCP/INP/CLS/TTFB/FCP), mobiel/desktop |
| Image Alt Generator | AI alt-teksten, bulk media-library — Starter+ |
| AI Image Generator | Featured/OG-afbeeldingen via OpenArt/OpenRouter — vereist image-API |
| Multi-CMS Publisher | Publiceert naar Webflow/Shopify vanuit WP (credentials in options) |
| External Integrations | Diverse externe diensten |
| SEO Importer | Import uit Yoast/RankMath e.a. |
| SEO Revisions + Snapshots | Wijzigingshistorie en terugzetten — Agency |

### 5.6 Platform & beheer (client)

| Module | Functie |
|--------|---------|
| Dashboard Shell | Full-page Fyndable-omgeving met sidebar |
| Role Permissions | Granulaire capabilities per WP-rol |
| White-label Manager | Kleuren/logo/branding op de client |
| Privacy Export | GDPR data-export/-verwijdering |
| Support Tickets | Tickets vanuit de client naar het SaaS-platform, met screenshots |
| Onboarding Wizard | Geleide eerste setup |
| Demo Mode | Fictieve data voor demo's (expliciet inschakelen) |
| Trial Cleanup | Opruimen verlopen trial-data |
| Mobile/PWA | `mobileapp.php` serveert manifest/service-worker/app-template |
| Update Checker | Updates via SaaS `/updates/check` |
| SEO Report Export | Site-brede audit als CSV of printbaar PDF-rapport |

### 5.7 SaaS-dashboard modules

| Module | Functie |
|--------|---------|
| TenantRepository | Multi-tenant data, migraties, sub-tenants |
| LicenseKeyGenerator | Keys genereren/activeren/intrekken, per-feature licensing |
| LicenseAPI | REST: validate/activate/deactivate/status/trial/upgrade + Google-proxy + support/feedback |
| ApiGateway | LLM-proxy (OpenRouter/OpenAI), SERP (DataForSEO/SerpAPI), keyword-data, local pack/grid, Google Places/Geocoding proxy |
| GeoScanner/Queue/Report | Async GEO-scans, multi-model merge, rapport-rendering |
| PublicApi | Publieke website-scan met shared key + IP-rate-limit + idempotentie |
| PaymentProcessor | Stripe + Mollie, prijzen, checkout, subscriptions |
| WebhookHandler | Stripe (HMAC + replay/idempotentie), Mollie (fetch-back-verify) |
| SignupCheckout | Self-serve registratie + trial signup (rate-limited) + payment-returns |
| UpdateServer | Update-metadata + downloads (license+tenant geverifieerd) |
| WhiteLabelPackageBuilder | Gerebrandde client-zip downloaden |
| AgencyPortal | Agency-beheer: sub-tenants, eigen licenties, white-label |
| CustomerPortal / RoleManager | Klant-portal, auto-login links (single-use, 5 min) |
| AdminApi | REST-API voor de Android-management-app (Application Passwords) |
| BookkeepingAdmin / RevenueDashboard | Facturen, CSV-export, MRR/ARR |
| EmailAutomation + TemplateRenderer | Transactionele mails met template-system |
| SupportTickets/SupportAdmin | Ticket-systeem met staff-replies en screenshots |
| Feedback | Klant-feedback verzameling |
| FyndableLogin | Gebrande wp-login, `/set-password`-route, auto-login |

---

## 6. Gebruikershandleiding

Condensatie van de volledige handleiding; de HTML-versie in `docs/` en `how-to/` bevat dezelfde inhoud in presentatieformaat.

### 6.1 Snelstart

1. Activeer licentie via **Fyndable → Connection**.
2. Doorloop de **Onboarding Wizard** (basis-SEO, post types, Google-koppeling).
3. Gebruik de **Dashboard Shell** — linker sidebar bevat alle tools; ×-icoon rechtsboven keert terug naar standaard WP-admin.
4. Controleer je tier via **Connection** — grijze menu's horen bij hogere tiers.

### 6.2 Dagelijkse workflow per doel

**Post schrijven/optimaliseren**
- Focus-keyphrase invullen in de metabox → TruSEO Score + Readability volgen.
- **Content Optimizer** (Pro+): analyseer top-10 → werk missing terms bij → re-analyze.
- **Smart Tags** voor title/meta; **Social Preview** voor OG.
- **LSI Keywords** voor semantische dekking; **E-E-A-T** voor autoriteit.

**Content produceren**
- **Ideas** → idee genereren → Convert to Draft.
- **Content Brief** → **AI Content Writer** → altijd redigeren vóór publicatie.
- **Bulk Optimizer** (Business+) voor site-brede meta-generatie; verwerkt één post per call — bij onderbreking: herladen en hervatten.

**Strategie**
- **Topic Clusters**: pillar + 3-5 clusters, pillar eerst publiceren.
- **Keyword Difficulty** (gepersonaliseerd) voorkomt miskleunen op te zware keywords.
- **ApexFlow** (betaald Pro+): zet seed-keywords, publish-dagen en modus — weekelijkse autopilot via queue; gebruik Preview eerst.

**Techniek**
- **Site Audit** → rode items eerst; **Sitemaps → Redirect Manager** voor 404-fixes.
- **Link Manager** → Orphan Pages oplossen; **Link Genius** voor automatische interne links.

**Meten**
- **Rank Tracker** (+ Cannibalization-tab), **GSC/GA4-dashboards**, **Content Decay** voor dalers.
- **SEO Report Export** voor CSV/PDF-rapportage aan klanten.

**GEO/AI-zichtbaarheid**
- **GEO-scan** via SaaS; **llms.txt** aan in Settings → llms.txt Generator; **LLM Tracker** en **Brand Visibility** voor resultaatmeting.

### 6.3 Veelvoorkomende gebruikersproblemen

- **"Invalid license"** — key zonder spaties, dashboard-URL met `https://`, licentie actief in het dashboard.
- **Lege sidebar** — licentie inactief of tier te laag.
- **Wizard start niet** — handmatig via `admin.php?page=ai-seo-onboarding`.
- **Audit/scan loopt vast** — WP-Cron afhankelijk van traffic; echte cron instellen.
- **AI-output generiek/hallucinerend** — meer context in prompt; feiten altijd verifiëren.
- **Google-koppeling verloopt** — OAuth-app in "Testing"-status laat refresh tokens na 7 dagen verlopen; publiceer/verifieer de Google-app voor permanente koppeling.

---

## 7. SaaS-beheer & portals

### Admin (manage_options)

- **Licenties**: genereren (single/batch, type/tier/limits/expiry/prefix), revoken, exporteren, per-feature beheer via LicenseFeatureManager.
- **Tenants**: detail, usage-history, sub-tenants, platform-filter (wordpress/shopify/webflow), delete met license-actie (keep/free/revoke/delete).
- **Boekhouding**: factuurlijst met filters, CSV-export (formule-injectie-safe sinds 2026-10-16), factuur-view/print, winst-tab, factuur-template.
- **GEO Scan admin**: handmatige scans (async), recente scans, website-scan-key beheer, multi-model settings.
- **AI Models**: standard/premium routing per use-case, live model-list refresh via OpenRouter.
- **Client Versions**: download-URL, min WP-versie, changelog, zip-upload (random suffix), beta-kanaal.
- **Support**: alle tickets, staff-replies met screenshots, status/priority.
- **White-Label**: globale branding + per-tenant overrides; agency-portal beheert eigen branding.

### Agency Portal (`agency_partner`-rol)

- Eigen sub-tenants en licenties, white-label settings (naam/logo/kleuren), download van de **gerebrandde client-plugin-zip**.
- Tenant-login-links: single-use, 5 minuten geldig, alleen voor eigen sub-tenants (agency↔tenant-binding gecontroleerd).

### Customer Portal (`fyndable_customer`-rol)

- Frontend-portal op `/dashboard/`: abonnement, upgrade-flow, facturen, support.
- Login via standaard wp-login (gebrand) of auto-login link vanuit agency/admin.

### Signup & checkout (publiek)

- `POST /register` — self-serve (option-flag `sseo_ai_saas_self_serve_enabled`); trial-flow via Mollie-mandaat of direct; Agency-tier alleen via contact.
- Betaal-returns via `?fyndable_payment=…&tenant_key=…` — verifieert bij Mollie vóór cleanup sinds 2026-10-16.
- Rate limits: registratie 10/uur/IP; trial 3/uur/IP; license-validate 10/min; activate 5/min.

---

## 8. API-referentie

### 8.1 SaaS REST — `ai-seo-saas/v1`

**Licentie (publiek, rate-limited)**
| Endpoint | Auth | Functie |
|----------|------|---------|
| `POST /license/validate` | license_key | Valideer key (geen secrets in response) |
| `POST /license/activate` | license_key | Activeer → tenant_key, tier, white-label, image_api |
| `POST /license/deactivate` | license_key+tenant_key | Deactiveer site |
| `POST /license/trial` | — | Start trial (3/uur/IP) |
| `POST /tenant/status` | license_key+tenant_key | Status, limits, usage, model-routing |

**Google OAuth-proxy**
| Endpoint | Functie |
|----------|---------|
| `POST /google/oauth-config` | client_id + scopes voor GIS-popup |
| `POST /google/exchange` | code → tokens (redirect_uri `postmessage`) |
| `POST /google/refresh` | access-token verversen (client_secret blijft server-side) |
| `POST /google/ads-dev-token` | Google Ads developer-token |
| `GET /google/oauth-start` | HTML-popup, postMessage beperkt tot tenant-origin |

**SERP & data (license+tenant)**
| Endpoint | Functie |
|----------|---------|
| `POST /serp/search` | SERP-resultaten (country/language gemapt) |
| `POST /serp/local-pack` | Google Maps local pack: keyword, lat/lng, radius, hl/gl |
| `POST /serp/local-grid` | Geo-grid scan: grid_size 3/5/7/9 |
| `POST /ai/chat` | LLM-proxy met model-routing per tier |
| `POST /ai/keyword-data` | Volume/difficulty |
| `POST /places/autocomplete`, `/places/geocode` | Google Places proxy |

**Publieke GEO-scan (X-Fyndable-Scan-Key)**
| Endpoint | Functie |
|----------|---------|
| `POST /public/geo-scan` | `{url, keywords[1-3], email, consent}` → `201 {scan_id}`; idempotent per e-mail; 10/uur/IP |
| `GET /public/geo-scan/{id}/status` | `{status, progress, teaser}`; `?token=` vereist bij nieuwe scans |

**Webhooks & signup (publiek)**
- `POST /webhooks/stripe` — HMAC-SHA256-signatuur + timestamp-tolerantie + event-dedup (fail-closed).
- `POST /webhooks/mollie` — betaling opgehaald bij Mollie, payload niet vertrouwd.
- `POST /register` — self-serve signup/checkout (rate-limited 10/uur/IP).
- `POST /signup/activate` — activeert nooit blind; alleen na webhook-activatie of Mollie-herverificatie.

**Updates**
- `POST /updates/check` — license+tenant geverifieerd; retourneert `has_update`, `download_url`, `changelog`, `min_wp_version`.

**Admin-API (`/admin/*`, Application Passwords + `manage_options`)** — voor de Android-management-app:
- `GET /admin/ping`, `GET /admin/licenses[...]`, `POST /admin/licenses`, `GET|POST /admin/licenses/{key}`, `POST /admin/licenses/{key}/revoke`
- `GET /admin/tenants`, `GET|DELETE /admin/tenants/{tenant_key}`, `GET /admin/tenants/{key}/usage/history`
- `GET /admin/usage`, `GET /admin/revenue/stats`
- `GET|POST /admin/support/tickets[/{id}[/reply]]`
- `POST /admin/geo-scan`, `GET /admin/geo-scan/recent`, `GET /admin/geo-scan/{id}`
- `GET|POST /admin/ai-models`, `POST /admin/ai-models/refresh`

### 8.2 Client REST — `sseo-ai/v1`

Alle admin-endpoints vereisen `manage_options`, `edit_posts` of `publish_posts`; de volledige lijst staat in `wp-content/plugins/fyndable-client/README.md`. Belangrijkste publieke/uitzonderlijke routes:

| Endpoint | Permissie | Opmerking |
|----------|-----------|-----------|
| `GET /gsc-callback` | publiek | OAuth-callback; `state` gevalideerd via `hash_equals` + transient |
| `POST /google/store-tokens` | manage_options | Tokens opgeslagen (refresh_token AES-256-GCM) |
| `POST /ab-tests/conversion` | publiek | Conversie-tracking; 30/min IP-limit, sessie-dedup, variant-validatie |
| `GET|POST /llmstxt/*` | manage_options | Status/regenerate voor llms.txt |
| `GET|POST /local-serp/*` | manage_options + tier Pro+ | Local pack/grid scans |
| `GET|POST /apexflow/*` | manage_options + betaald Pro+ | Status/run/preview/pool/plan |
| `GET /cannibalization/report`, `POST /cannibalization/scan` | manage_options | Cannibalization-dashboard |

---

## 9. Security & privacy

### 9.1 Architectuur-maatregelen

- **Licentie↔tenant-binding**: alle gevoelige endpoints verifiëren het `license_key`+`tenant_key`-paar met `hash_equals`.
- **Webhooks**: Stripe HMAC-SHA256 + 5-min timestamp-tolerantie + 24u event-dedup; Mollie fetch-back-verificatie.
- **Rate limiting**: IP-gebaseerd via transients op alle publieke endpoints (license, trial, register, public scan, A/B-conversie).
- **SSRF**: `HtmlFetcher::isUrlSafe()` blokkeert loopback/private/link-local/ULA en letterlijke IP-hosts (incl. IPv6); externe fetch loopt via Jina/Firecrawl. Alle uitgaande `wp_remote_*` calls gaan naar vaste provider-hosts met `sslverify` (default true) en timeouts.
- **Secrets**: Google refresh-tokens AES-256-GCM (`enc1:`-prefix, sleutel uit `wp_salt`); centrale image-API-keys verlaten de server alleen via license+tenant-geverifieerde endpoints. Geen secrets in logs (alleen md5-vingerafdruk van keys).
- **Uploads**: `wp_check_filetype_and_ext`, `is_uploaded_file`, MIME-whitelists, grootte-limits, random bestandsnamen (client-versie-zips).
- **ZIP-handling**: white-label builder valideert entry-paden (zip-slip-guard) en sanitiseert bedrijfsnamen vóór injectie in PHP-broncode.
- **CSRF**: alle admin_post- en AJAX-handlers hebben nonce + capability; geen `nopriv`-handlers.
- **Output**: `esc_html`/`esc_attr`/`esc_url`/`wp_kses_post` consistent; `postMessage` beperkt tot tenant-origin.
- **PDF**: dompdf `isRemoteEnabled=false`.
- **CSV**: exports neutraliseren formule-injectie (`=`,`+`,`-`,`@`-prefix guard).
- **Auto-login**: single-use 32-char transient-tokens, 5 min, agency↔tenant-binding.

### 9.2 Audit-historie (WP-1 t/m WP-6, eerder voltooid)

Eerdere volledige audits fixten o.a.: Stripe-webhook signature-verificatie (was uitgecommentarieerd → kritiek), Stripe idempotentie, publieke tenant-activatie zonder betaling (kritiek), `postMessage('*')` (high), ontbrekende `permission_callback` op `/clusters/list` (high), CSS-color-injectie via white-label optie, license-key-logging, multipart CRLF-injectie, missende capability-checks in onboarding/E-E-A-T, en 3× unescaped DB-COUNT output.

### 9.3 Audit 2026-10-16 — nieuwe bevindingen & fixes

Volledige verificatie-pass over beide plugins (REST-routes, AJAX/admin_post, SQL, XSS, SSRF, file-ops, secrets, sessies, rate limiting). Resultaat: de codebase was in goede staat; 10 issues gevonden en direct gefixt:

| # | Ernst | Bestand | Bevinding → fix |
|---|-------|---------|-----------------|
| 1 | **Hoog** | `whitelabelpackagebuilder.php` | `company_name` (agency-input, alleen `sanitize_text_field`) werd via `str_replace` in PHP-broncode geïnjecteerd → mogelijke code-injectie in gedistribueerde white-label zips. Fix: `sanitizeCompanyName()` whitelist (letters/cijfers/spaties/`&.,()-`, max 60 tekens) + hard-fail bij lege naam. |
| 2 | **Hoog** | `whitelabelpackagebuilder.php` | `extractClientZip()` extraheerde zonder padvalidatie → zip-slip bij gecompromitteerde update-server-zip. Fix: reject entries met `..`, absolute paden of drive-letters vóór extractie. |
| 3 | **Medium** | `licenseapi.php` | `startTrial()` retourneerde bij 409 `trial_exists` de bestaande `license_key` — iedereen met het e-mailadres kon de key ophalen. Fix: key verwijderd uit response; verwijzing naar e-mail. |
| 4 | **Medium** | `licenseapi.php` | `getClientIp()` vertrouwde `X-Forwarded-For`/`X-Real-IP` ongefilterd → alle IP-rate-limits omzeilbaar. Fix: alleen `REMOTE_ADDR`; reverse-proxy moet `REMOTE_ADDR` overschrijven. |
| 5 | **Medium** | `signupcheckout.php` | `restRegister` had géén rate limit → onbeperkt tenants/licenses/checkout-sessies. Fix: 10/uur per IP (REMOTE_ADDR). |
| 6 | **Medium** | `signupcheckout.php` | `handlePaymentReturn` met `?fyndable_payment=cancel` wiste pending tenant+license zonder betalingsverificatie (race met webhook). Fix: opgeslagen `mollie_payment_id` wordt bij Mollie geverifieerd vóór cleanup; `cleanupPendingSignup` herleest de rij en weigert ook `paid`/`trial`-statussen te wissen. |
| 7 | **Medium** | `bookkeepingadmin.php` | CSV-formule-injectie via tenant-velden (`=`, `+`, `-`, `@`). Fix: `csvSafe()`-guard op alle tekstcellen. |
| 8 | **Laag** | `licensevalidator.php` | cURL-fallback volgde redirects → credentials zouden bij redirect naar kwaadaardige host lekken. Fix: `CURLOPT_FOLLOWLOCATION=false` (WP-HTTP-pad had al `redirection=0`). |
| 9 | **Bug** | `seoreportexport.php` | `handleCsvExport` riep niet-bestaande `gatherReportData()` aan; `handlePdfExport` gebruikte undefined `AISEO_CLIENT_VERSION` → beide exports fataal. Fix: `getReportData()` + `SSEO_AI_CLIENT_VERSION`. |
| 10 | **Bug** | `redirectionmanager.php` | "Export CSV"-link zonder geregistreerde handler. Fix: `admin_post_sseo_ai_redirect_export` handler met nonce + `manage_options`, link met `wp_nonce_url`. |

### 9.4 Verifieerde non-issues

- Mollie-webhook zonder signatuur is correct (fetch-back-verificatie).
- `/local-schema` en `/ab-tests/conversion` publiek is by-design met eigen mitigaties.
- Demo Mode bypass is bewust en opt-in.
- Geen `eval`/`exec`/`unserialize`, geen `sslverify=false`, geen user-gestuurde includes, geen `nopriv`-handlers, geen raw `$_GET/$_POST`-echo's, geen directe user-input in SQL.

### 9.5 Privacy

- `privacyexport.php` (client) en de GDPR delete/export-routes in `licenseapi.php` ondersteunen data-export en -verwijdering per tenant.
- Website-scans bewaren `consent` + `consent_at`; retentie 90 dagen voor publieke scans, 7 dagen voor admin-scans.

---

## 10. Updates & deployment

### Update-keten

1. **Build**: `tools/build-fyndable-client.ps1` — versie bumpen in `ai-seo-client.php` (regels 5 en 19), script maakt de zip + `latest.json`.
2. **Deploy**: `tools/deploy-updates.ps1 -Version X.Y.Z -User <ssh> -Key <key> -Archive` → SCP naar `/var/www/updates.fyndable.ai/public_html/fyndable-client/`.
3. **Valideer**: `tools/validate-release.ps1`, of handmatig:
   ```bash
   curl -s https://updates.fyndable.ai/fyndable-client/latest.json
   curl -sI .../latest.json   # verwacht: Content-Type: application/json, no-cache
   ```
4. **SaaS koppelen**: `admin.php?page=sseo-ai-client-versions` → Update server URL = `https://updates.fyndable.ai`. `UpdateServer` fetched `<url>/fyndable-client/latest.json` (6u cache: `sseo_ai_update_meta_*` transients legen na geforceerde uitrol).
5. **Client**: `UpdateChecker` polled via `/updates/check` (6u transient) → `pre_set_site_transient_update_plugins`; "Check for Updates"-link in plugin-rij forceert een verse check.

### Update-server (Nginx, aanbevolen)

- Vhost-config: `tools/updates.fyndable.ai/nginx/updates.fyndable.ai.conf`.
- Eisen: `autoindex off`, `default_type application/json` op `.json` (kritiek), `no-cache` + `Access-Control-Allow-Origin: *` op JSON, `max-age=86400` op `.zip`, dotfiles deny.
- HTTPS via certbot (`--nginx -d updates.fyndable.ai --redirect`).
- Apache-variant (legacy): zie de appendix in de oude `tools/UPDATES-SERVER.md` (nu samengevat in `docs/`-site); kort: `Options -Indexes`, `AddType application/json .json`, `Header set Cache-Control "no-cache"` op JSON.
- **Beta-kanaal**: `<url>/fyndable-client/beta/latest-beta.json` — handmatig aanmaken.
- **Valkuil**: het build-script schrijft `changelog=""` — `latest.json` handmatig aanvullen of script aanpassen.

### White-label zip-distributie

De White-Label Package Builder bouwt een gerebrandde zip van de client-plugin; bron-resolution: lokaal geïnstalleerd → update-server (6u extract-cache) → packaged zips in `uploads/fyndable-versions` of `versions/`. Bedrijfsnamen worden sinds 2026-10-16 streng gesanitiseerd vóór ze in broncode worden geïnjecteerd.

### Shopify-app deployment (los project)

`shopify-app/` (Laravel 11): queue-worker via Supervisor (`php artisan queue:work`) + scheduler-cron voor bulk-optimize en rank checks. App Proxy pad `/apps/fyndable/llms.txt`. Let op: `shopify/shopify-api` v6.1.1 is abandoned — vervang voor productie.

---

## 11. Monitoring & performance

- **SaaS-metrics**: actieve licenties, API-calls/kosten per tenant, revenue per tier (MRR/ARR), usage-rapporten, error rates.
- **Client-metrics**: gegenereerde content, tracked keywords, rankings, health-logs (`healthlogger.php`), alerts (`alertnotifier.php`).
- **Benchmarks** (indicatief): license-validatie <100ms, AI-content 2-5s, SERP-analyse 1-3s gecachet / 5-10s vers, GEO-scan 30-90s (async).
- **Caching**: license-validatie 1u, SERP-data 24u, GEO-statussen via transients, update-metadata 6u, llms.txt 6h (invalideert op save/delete_post).
- **Aanbevelingen**: object-cache (Redis/Memcached), echte cron naar `wp-cron.php` op portal én clients (GEO-queue, ApexFlow, rank checks zijn WP-Cron-afhankelijk), goedkopere modellen via AI-model-routing (`standard` vs `premium`).

---

## 12. Troubleshooting & FAQ

### Veelvoorkomende issues

| Probleem | Oplossing |
|----------|-----------|
| "No route found" | Permalinks flushen (`wp rewrite flush` of Settings → Permalinks → Save) |
| Licentie-activatie faalt | URL met `https://`, key correct, REST bereikbaar (`curl -I <url>/wp-json/`), licentie actief in dashboard |
| White-label werkt niet | Licentie de-/reactiveren + hard refresh; check `wp option get sseo_ai_white_label` |
| GEO-scan blijft "queued" | WP-Cron draait alleen op traffic → echte cron naar `wp-cron.php`; sweep ruimt na 5 min vastgelopen jobs |
| Stripe-webhooks geweigerd | `sseo_ai_saas_stripe_webhook_secret` instellen (fail-closed by design) |
| Google-koppeling verloopt wekelijks | Google OAuth-app in "Testing"-status → publiceer/verifieer de app |
| Updates niet zichtbaar | `sseo_ai_update_meta_*` transients legen; `latest.json` cache-headers checken |
| Fatale fout CSV/PDF-export | Gefixt in 2026-10-16-release (seoreportexport) |
| Hoge AI-kosten | Caching, goedkopere model-routing, strakkere tier-limits, usage per tenant monitoren |

### FAQ (uit marketing-doc, geconsolideerd)

- **Moet ik zelf API-keys regelen?** Nee — alle AI/SERP-verkeer loopt geproxied via het platform.
- **Naast Yoast/RankMath?** Kan, maar kies één plugin leidend voor sitemaps/schema/redirects.
- **Gratis proberen?** 14 dagen, volledige Professional-features.
- **Opzegbaar?** Maandelijks, automatisch verwerkt.
- **Betaalmethoden?** iDEAL/Bancontact/SEPA (Mollie) en creditcard (Stripe).
- **Meerdere klantsites?** Agency-tier: multi-site, extra licenties, per-licentie feature-toggles, volledig white-label.
- **Limiet bereikt?** Per tier AI-/SERP-limits; upgraden bij structureel overschrijden.
- **Is mijn data veilig?** Encryptie in transit (HTTPS verplicht), refresh-tokens encrypted at rest, tenant-isolatie, GDPR export/delete.

---

## 13. Roadmap & bekende beperkingen

### Bekende beperkingen / backlog

- **Image-API-proxy**: centrale OpenArt/OpenRouter-keys gaan nog mee naar clients via `activateLicense`/`getTenantStatus`. Lange-termijn: proxy-architectuur zodat keys de server nooit verlaten.
- **Licentiekey-entropie**: ~96 bits — acceptabel, 128+ aanbevolen.
- **E-mail-enumeratie**: signup geeft 409 bij bestaand e-mailadres (gebruikelijk, maar noemenswaardig).
- **`gscoauth.php` fallback**: bij ontbrekend OpenSSL worden tokens plaintext opgeslagen i.p.v. de koppeling te verbreken — bewuste trade-off; OpenSSL is vrijwel altijd beschikbaar.
- **WP-Cron-afhankelijkheid**: zonder echte cron lopen GEO-queue, sweeps, ApexFlow en rank checks alleen op bezoekersverkeer.
- **`rand()`-fallback**: `keywords.php` genereert willekeurige CPC/difficulty als de API faalt (kwaliteits-issue, geen security).
- **Diverse `prepare()`-loze cleanup-queries**: geen user-input, dus geen SQLi — best-practice-backlog.
- **`client.php` quality**: ~30 identieke `render*Page()`-methoden, ~200 regels inline CSS/JS — refactor-backlog.
- **Shopify-app**: `shopify/shopify-api` v6.1.1 abandoned; geen test-suite; `composer audit` draaien vóór productie.

### Geplande features (uit eerdere roadmap, deels al gerealiseerd)

- [x] Client-portal voor eindgebruikers
- [x] GEO/AI-zichtbaarheid (GEO-scan, llms.txt, LLM Tracker, Brand Visibility)
- [x] Content-autopilot (ApexFlow)
- [x] Android-management-app (admin REST-API)
- [ ] Webhook-notificaties voor license-events
- [ ] Multi-language UI (i18n verbreden)
- [ ] Custom AI-model-training per tenant
- [ ] Integratie-marketplace (Zapier/Make)
- [ ] Advanced white-label CSS-editor

---

## 14. Bijlage: repository-layout

```
C:\WorkSpace\Fyndable\
├── FYNDABLE.md                    ← dit document (single source of truth)
├── README.md                      ← pointer naar dit document
├── AGENTS.md                      ← technische werkinstructies/notities
├── wp-content\plugins\
│   ├── fyndable-client\           ← client-plugin (~115 modules)
│   │   ├── ai-seo-client.php      ← bootstrap + autoloader
│   │   ├── includes\              ← alle feature-classes
│   │   ├── README.md              ← feature-tabel + REST-lijst
│   │   └── CHANGELOG.md           ← release-notes
│   ├── fyndable-saas-dashboard\   ← SaaS-platform (~45 modules)
│   └── fyndable-geo-scan\         ← publieke scan-plugin (marketing-site)
├── tools\                         ← build/deploy/validate scripts + nginx-conf
├── versions\                      ← packaged release-zips
├── docs\, how-to\                 ← HTML-documentatiesites
├── shopify-app\                   ← Laravel 11 Shopify-app
├── android-app\, fyndable-admin-app\
└── logo\, overig\                 ← assets & diversen
```

### Versies

- Client-plugin: zie `SSEO_AI_CLIENT_VERSION` in `ai-seo-client.php` (momenteel 2.4.0) en `wp-content/plugins/fyndable-client/CHANGELOG.md`.
- SaaS-dashboard: `SSEO_AI_SAAS_VERSION` in de main plugin file.

### Conventies

- REST-namespaces: client `sseo-ai/v1`, SaaS `ai-seo-saas/v1`.
- Options-prefixen: `sseo_ai_client_*` (client), `sseo_ai_saas_*` / `ai_seo_saas_*` (SaaS), `sseo_ai_*` (gedeeld).
- Tabelnamen via `$wpdb->prefix . 'sseo_ai_*'`.
- Nonce-acties per handler (`check_admin_referer`/`check_ajax_referer`); capabilities `manage_options`/`edit_posts`/`publish_posts`.

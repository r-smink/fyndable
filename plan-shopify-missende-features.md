# Plan — Missende features Fyndable Shopify-app

*Opgesteld 2026-11-04 op basis van de huidige code in `shopify-app/` (routes, controllers, services, theme-extensie, dashboard-tabs).*

## Voortgang

Een feature wordt pas afgevinkt als de code gecommit is, de testsuite groen is en `composer audit` schoon is.

**Fase 0**
- [ ] 0.1 Shopify Billing (Managed Pricing / Billing API)
- [ ] 0.2 Zero-config onboarding + automatische trial
- [ ] 0.3 Consistente tier-gating
- [ ] 0.4 Library-migratie + testsuite
- [ ] 0.5 Built for Shopify-eisen

**Fase 1**
- [ ] 1.1 SEO-score per item + winkelbrede audit
- [ ] 1.2 404-monitor + redirectmanager
- [ ] 1.3 Bulk optimizer uitbreiden
- [ ] 1.4 Winkelbreed schema via app embed
- [ ] 1.5 Google Search Console-koppeling
- [ ] 1.6 Beeld-SEO
- [ ] 1.7 Rapportage

**Fase 2**
- [ ] 2.1 AI-commerce readiness / productfeed
- [ ] 2.2 AI-crawlerbeheer via `robots.txt.liquid`
- [ ] 2.3 AI-vertalingen voor Markets
- [ ] 2.4 ApexFlow voor Shopify
- [ ] 2.5 Collectiepagina's als SEO-landingspagina's
- [ ] 2.6 Reviews naar content + schema
- [ ] 2.7 Merkstem + brand style references
- [ ] 2.8 AI-referralverkeer en omzet
- [ ] 2.9 Agency multi-store-overzicht

### Afwijkingen tussen plan en code (gecontroleerd 2026-11-04)

- **Library (0.4):** `shopify/shopify-api` v6 is al vervangen. `composer.json` heeft Laravel `~12.66` en `shopify/shopify-app-php ~1.0.2`, maar die library wordt nergens in `app/` gebruikt; alle Admin-calls lopen via `Http::` (GraphQL + enkele REST-calls). AGENTS.md noemt nog Laravel 11 en v6.1.1.
- **Tests (0.4):** er zijn wel tests: 33 PHPUnit-tests (o.a. `ShopifySignatureTest`, `VerifyShopifySessionTest`, `BulkOptimizeJobTest`, `ContentControllerTest`), allemaal groen. Pest is niet geinstalleerd.
- **Tier-gating (0.3):** `hasTier()` wordt op 1 plek aangeroepen (`RankTrackerController`), niet 2. `BulkOptimizeJob` en `CheckRankingsJob` checken alleen `hasLicense()`.
- **composer audit:** 6 advisories in 3 packages (`laravel/framework` 3x, `league/commonmark` 2x, `symfony/mime` 1x).
- **Scopes:** `config/shopify.php` vereist `read_markets,read_locales,read_translations`, maar `shopify.app.toml` declareert die niet. Bij de managed install krijgt een winkel ze dus nooit, en `missingScopes()` blijft altijd een re-auth melden. App Store-risico.
- **OAuth-callback:** `install()` zet geen `shopify_oauth_state` meer (managed install), dus `/auth/callback` eindigt altijd in 403. Dode code.
- **Billing:** `config('shopify.billing_enabled')` bestaat maar wordt nergens gebruikt. Geen `app_subscriptions/update`-webhook.
- **GDPR:** `shop/redact` verwijdert `scheduled_articles` niet en roept de SaaS-endpoint `/gdpr/delete` niet aan.
- **i18n:** de dashboard-UI is volledig Engelstalig, zonder vertaallaag.

## Wat er al staat (uitgangspunt)

Content SEO-editor (meta, beschrijving, JSON-LD, FAQ, interne links, AI-productafbeelding) voor producten/collecties/pagina's/artikelen, varianten-beschrijvingen, review-schema, bulk optimizer (alleen producten), Blog Writer + contentkalender met ingeplande publicatie, llms.txt/llms-full.txt (App Proxy + redirects), IndexNow, rank tracker (dagelijks), keyword explorer (volume/cluster/trends), concurrentieanalyse, backlinks, LLM-mentions + AI-tracker, GEO-scan, Markets/hreflang-audit, licentie via portal, GDPR-webhooks, theme app block voor schema.

De basis is breed. De grootste gaten zitten in **(1) wat de App Store vereist**, **(2) de standaard SEO-hygiëne die merchants van elke SEO-app verwachten**, en **(3) AI-commerce, waarmee Fyndable zich echt kan onderscheiden**.

---

## Fase 0 — Blokkerend voor App Store-lancering (eerst doen)

| # | Feature | Waarom | Aanpak |
|---|---------|--------|--------|
| 0.1 | **Shopify Billing (Managed Pricing of Billing API)** | Apps in de App Store moeten via Shopify afrekenen. Een licentiesleutel van portal.fyndable.ai wordt bij review afgekeurd. | `appSubscriptionCreate` (of Managed Pricing in de Partner Dashboard) → bij `app_subscriptions/update`-webhook automatisch een tenant + licentie aanmaken via SaaS (`platform=shopify`). Handmatige licentie-invoer alleen nog voor agency/enterprise. |
| 0.2 | **Zero-config onboarding + automatische trial** | Nu moet de merchant eerst een licentiesleutel plakken. Dat kost conversie. | Na OAuth-install direct een trial-tenant aanmaken, plus een wizard (winkeltaal, land, merkstem, eerste 5 producten optimaliseren). |
| 0.3 | **Consistente tier-gating** | `hasTier()` wordt maar op 2 plekken aangeroepen. Dure features (GEO-scan, AI-tracker, bulk, keyword explorer) staan open voor elke tier. | Centrale `RequireTier`-middleware op routegroepen + quota per tier vanuit de SaaS (`getApiLimitForTier`). |
| 0.4 | **Library-migratie + testsuite** | `shopify/shopify-api` v6 is abandoned (bekend punt). Er is geen enkele test. | Migreren naar `shopify/shopify-app-php` of een eigen dunne GraphQL-client; Pest-tests voor OAuth-HMAC, webhooks, sessietokens, SaasProxyClient. `composer audit` schoon. |
| 0.5 | **Built for Shopify-eisen** | Badge = ranking in de App Store. | Polaris-web-components/App Bridge-nav, sneller laden (de dashboard-blade is 159 KB in één bestand → opsplitsen), geen theme-impact buiten de app block. |

---

## Fase 1 — Kern-SEO die merchants verwachten (pariteit met Plug in SEO, StoreSEO, Smart SEO, Booster)

| # | Feature | Waarom belangrijk |
|---|---------|-------------------|
| 1.1 | **SEO-score per item + winkelbrede audit** | Er bestaat nu geen score en geen health check (alleen de Markets-audit). Elke concurrent opent met "jouw winkel scoort 62/100". Checks: ontbrekende of te lange meta, dubbele titels/beschrijvingen, thin content, ontbrekende alt-teksten, H1-problemen, lege collecties, niet-geïndexeerde producten, gebroken interne links. Inclusief "fix alles"-knoppen die doorlinken naar de bulk optimizer. |
| 1.2 | **404-monitor + redirectmanager** | Shopify-winkels verliezen veel linkwaarde door verwijderde producten en gewijzigde handles. `ShopifyUrlRedirect` bestaat al, maar wordt alleen voor llms/IndexNow gebruikt. Bouwen: automatische redirect bij een handle-wijziging (`products/update`-webhook, oude handle vergelijken), bij het verwijderen een redirect naar de collectie, CSV-import/-export en bulk-redirects. |
| 1.3 | **Bulk optimizer uitbreiden** | `BulkOptimizeJob` doet nu alleen producten. Uitbreiden naar collecties, pagina's en artikelen, plus **template-regels zonder AI-kosten** (`{{title}} \| {{vendor}} \| {{shop}}`) voor grote catalogi. |
| 1.4 | **Winkelbreed schema via app embed** | Nu alleen schema per item, en pas na handmatig "save". Toevoegen: Organization/WebSite + SearchAction, BreadcrumbList, automatisch Product-schema (offers/varianten/GTIN/beschikbaarheid) zonder handmatige actie, LocalBusiness voor fysieke winkels. Detectie en uitschakeling van dubbel theme-schema (de meest gehoorde klacht bij Shopify-SEO). |
| 1.5 | **Google Search Console-koppeling** | Al gebouwd in WP (OAuth-proxy op de SaaS). Hergebruiken: echte klikken/impressies per product, "bijna pagina 1"-kansen, en de rank tracker aanvullen met GSC-posities. |
| 1.6 | **Beeld-SEO** | Bestandsnamen hernoemen (SEO-vriendelijk), compressie/WebP-check, ontbrekende alt-teksten in bulk. TinyIMG verdient hier zijn geld mee. Minimaal de alt-teksten en bestandsnamen. |
| 1.7 | **Rapportage** | Wekelijkse e-mail plus PDF-rapport (rankings, AI-zichtbaarheid, wat Fyndable heeft geoptimaliseerd). Belangrijk voor retentie: de merchant moet de waarde zien. |

---

## Fase 2 — Onderscheidend (hier wint Fyndable)

| # | Feature | Waarom onderscheidend |
|---|---------|----------------------|
| 2.1 | **AI-commerce readiness / productfeed voor AI-shopping** | ChatGPT Shopping, Perplexity Shopping en Google AI Mode halen productdata uit feeds en gestructureerde data. Bouwen: per product een "AI-ready score" (GTIN, merk, materiaal, maten, use-cases, compleetheid van attributen), automatisch ontbrekende attributen/metafields aanvullen met AI, en een feed-export (Google Merchant Center + OpenAI-productfeedformaat). Geen enkele Shopify-SEO-app doet dit nu goed. |
| 2.2 | **AI-crawlerbeheer via `robots.txt.liquid`** | Shopify staat theme-aanpassing van robots.txt toe. Per AI-bot (GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, Google-Extended) instellen: toestaan of blokkeren, met uitleg over de gevolgen voor de zichtbaarheid. Sluit aan op llms.txt. |
| 2.3 | **AI-vertalingen voor Markets** | De Markets-audit vindt nu wel vertaalgaten, maar lost ze niet op. Met `translationsRegister` meta, beschrijvingen en alt-teksten per locale invullen in de merkstem. Grote meerwaarde voor EU-merchants (NL/DE/FR/BE), en Shopify Translate & Adapt doet geen SEO-optimalisatie. |
| 2.4 | **ApexFlow voor Shopify (content-autopilot)** | Kalender en Blog Writer bestaan al, maar zonder autopilot. ApexFlow-pipeline porten: winkelprofiel → keywordpool → planning → automatisch publiceren, met interne links naar producten/collecties. Kernfeature in WP, dus ook voor Shopify een sterk verkoopargument. |
| 2.5 | **Collectiepagina's als SEO-landingspagina's** | AI-gegenereerde intro- en FAQ-blokken boven/onder collecties (theme block), gebaseerd op keyworddata. Collecties ranken in e-commerce het best maar zijn meestal leeg. |
| 2.6 | **Reviews → content + schema** | Judge.me/Yotpo/Shopify Product Reviews uitlezen (metafields) → aggregateRating-schema, FAQ uit klantvragen, en "wat klanten zeggen"-samenvattingen (AI-citeerbaar). |
| 2.7 | **Merkstem + brand style references** | Al gebouwd in WP. Porten zodat alle AI-teksten en productafbeeldingen consistent in de merkstijl zijn. |
| 2.8 | **AI-referralverkeer en omzet** | Via Shopify-orders/sessies (of een web pixel) meten hoeveel verkeer en **omzet** uit chatgpt.com, perplexity.ai en Gemini komt. "Fyndable leverde € X op via AI-zoekmachines" is het sterkste retentie- en upsell-argument. |
| 2.9 | **Agency multi-store-overzicht** | Agency-tier: één overzicht over alle gekoppelde winkels (scores, rankings, AI-zichtbaarheid), white-label rapporten. Sluit aan op de bestaande agency portal in de SaaS. |

---

## Voorgestelde volgorde

1. **Sprint 1–2:** 0.1 Billing, 0.2 onboarding/trial, 0.3 tier-gating
2. **Sprint 3:** 0.4 tests + library, 0.5 opsplitsen dashboard / Built for Shopify
3. **Sprint 4–5:** 1.1 SEO-score + audit, 1.2 redirects/404, 1.4 winkelbreed schema
4. **Sprint 6:** 1.3 bulk-uitbreiding + templates, 1.5 GSC, 1.7 rapportage
5. **Sprint 7–9:** 2.1 AI-commerce readiness, 2.2 AI-crawlers, 2.3 AI-vertalingen
6. **Daarna:** 2.4 ApexFlow, 2.8 AI-omzetmeting, 2.5/2.6/2.7/2.9, 1.6 beeld-SEO

**Positionering:** "De enige Shopify SEO-app die je producten vindbaar maakt in ChatGPT, Perplexity en Google AI." Fase 2.1, 2.2 en 2.8 maken die claim waar. Fase 0 en 1 zijn nodig om überhaupt mee te kunnen doen.

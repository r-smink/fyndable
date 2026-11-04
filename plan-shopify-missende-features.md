# Plan - Missende features Fyndable Shopify-app

*Opgesteld 2026-11-04 op basis van de huidige code in `shopify-app/` (routes, controllers, services, theme-extensie, dashboard-tabs).*

## Voortgang

Afvinken als de feature gecommit is, de testsuite groen is en `composer audit` schoon is.

- [x] 0.1 Shopify billing via App Pricing (commits `d824c7c`, `7ca10a2`, `6b02973`, `d472cc8`; zie "Stand 0.1" hieronder)
- [x] 0.2 Zero-config onboarding + automatische trial (commit `2f25da4`; trial-tenant bij eerste app-load via 0.1, wizard met taal/land/merkstem + eerste 5 producten, merkstem in alle AI-teksten)
- [x] 0.3 Consistente tier-gating (gebouwd in de IDE, commit `259f252`; tests toegevoegd in `tests/Feature/TierGatingTest.php`, commit `6b02973`)
- [x] 0.4 Library-migratie + testsuite (commits `d824c7c`, `bb7b304`: Pest, eigen dunne GraphQL/Partner-clients via `Http::`, `shopify-app-php` verwijderd, 103 tests, audit schoon)
- [ ] 0.5 Built for Shopify-eisen
- [ ] 1.1 SEO-score + audit
- [ ] 1.2 404-monitor + redirectmanager
- [ ] 1.3 Bulk optimizer uitbreiden
- [ ] 1.4 Winkelbreed schema via app embed
- [ ] 1.5 Google Search Console-koppeling
- [ ] 1.6 Beeld-SEO
- [ ] 1.7 Rapportage
- [ ] 2.1 t/m 2.9 (fase 2)

### Afwijkingen en vervolgpunten (gecontroleerd 2026-11-04)

- **0.3 tier-gating:** `config/tiers.php` (levels free/starter/professional/business/agency, trial = Professional), `RequireTier`-middleware (`shopify.tier:{feature}`) op alle feature-routes, `UsageLimiter` + tabel `shop_usage`. Consistent met de drie plannen, met tests. Queue-jobs checken de tier zelf en de tabblokkades komen uit `config/tiers.php` `tabs` (`1da5e88`).
- **0.4 (klaar):** geen Shopify-SDK meer; Admin GraphQL en Partner API lopen via eigen dunne clients op `Http::`. `shopify-app-php` (ongebruikt) is verwijderd, `firebase/php-jwt ^7.0` staat nu direct in `composer.json`. Laravel 12.71, `platform.php` 8.2.29, Pest met 103 tests (OAuth-HMAC, webhooks, sessietokens, SaasProxyClient, billing, tier-gating, GDPR), `composer audit` schoon.
- **Scopes:** opgelost in `d472cc8` (`read_markets,read_locales,read_translations` in `shopify.app.toml`). Werkt pas na `shopify app deploy`.
- **OAuth-callback (opgelost, `bb7b304`):** de dode code-exchange is weg; `/auth/callback` controleert de HMAC en stuurt door naar het dashboard (route blijft omdat de TOML hem als redirect-URL declareert).
- **GDPR (opgelost, `22ea92c`):** `shop/redact` verwijdert nu ook `scheduled_articles` en `shop_usage`, wist naam/e-mail/licentie en laat de SaaS de App Pricing-tenant verwijderen (`GdprDeleteTenantJob`). Daarbij drie bestaande fouten in de SaaS-route `/gdpr/delete` gerepareerd (ongeldige enum-status `deleted`, usage en tickets werden op een niet-bestaande kolom `tenant_key` verwijderd, `tenant_settings` bleven staan).
- **0.2 (2026-11-05):** de product-AI-prompts gaven geen taal mee, dus Nederlandse winkels kregen Engelse teksten. Opgelost met `BrandVoice` (taal, land, toon, doelgroep, merkomschrijving uit `shop_profiles`). AiTracker (zichtbaarheidscheck) gebruikt bewust geen merkstem. Open: de wizard optimaliseert alleen SEO-titel + metabeschrijving, geen productbeschrijving (sneller, en bespaart trial-tegoed).
- **i18n:** de dashboard-UI is Engelstalig. Sinds 0.1 is er een kleine NL/EN-laag (`I18N`/`t()`/`data-i18n` in de blade, taal uit `?locale=`) voor de plan- en trialteksten; de rest vertalen hoort bij 0.5.
- **App Pricing (docs gecontroleerd):** klopt met het plan. Aanvullend: Partner API-endpoint `https://partners.shopify.com/{partner_id}/api/2026-10/graphql.json`, header `X-Shopify-Access-Token`, client met permissie *Manage apps*, limiet 4 req/s. `activeSubscription` geeft `null` zonder actief abonnement en heeft geen statusveld; plan zit in `items[].handle`, verder `trialEndsAt`, `cancelAtEndOfCycle`, `currentBillingCycle`, `pendingUpdate`. Redirect geeft `plan_handle` (+ `shop` bij externe URL), `charge_id` niet meer. Geen eenmalige aankopen, dus credit-packs kunnen niet via App Pricing.

### Stand 0.1 (2026-11-04)

Gebouwd: App Pricing-status via de Partner API (app-load, redirect-bevestiging, uur-sync), signed sync naar de SaaS (`ShopifyBillingApi`), in-app trial van 5 producten, agency-only handmatige sleutels, Plan-tab + banner NL/EN. Details in AGENTS.md ("Shopify App Pricing").

Nog te doen buiten de code (voor livegang):
- [ ] Plannen `starter`/`professional`/`business` (60/199/299 USD, geen Shopify-trialdagen) aanmaken in het Partner Dashboard, met precies deze handles.
- [ ] Partner API client aanmaken (Dev Dashboard, permissie Manage apps) en het token in `.env` zetten (`SHOPIFY_PARTNER_API_TOKEN`).
- [ ] Een gedeelde secret genereren en die als `FYNDABLE_SHOPIFY_SYNC_SECRET` in zowel de app-`.env` als de SaaS-`wp-config.php` zetten. Daarna de portal deployen.
- [ ] `SHOPIFY_BILLING_ENABLED=true` in productie, en de queue worker + scheduler moeten draaien.
- [ ] De welcome link/app-URL in het Partner Dashboard laten wijzen naar `/dashboard` (de app leest `plan_handle` daar).
- [ ] `shopify app deploy` voor de scope-wijziging.
- [ ] Test met een development store in dezelfde Partner-organisatie (betaalde plannen zijn daar gratis).

Open productvragen / vervolgpunten:
- Trial (besloten 2026-11-04): 7 dagen of 5 producten, wat het eerst komt (`d6f015f`). Tijdens de trial geldt Professional-niveau (`config/tiers.php`); de SaaS-tenant krijgt de trial-einddatum als `expires_at`.
- Na de plankeuze stuurt Shopify de merchant naar de app (welcome link), waar het plan direct wordt bevestigd. Een plan dat buiten die redirect om ontstaat (bv. door Shopify-support) komt binnen bij de volgende app-load.
- De SaaS-kant heeft geen geautomatiseerde tests (alleen `php -l`).
- GDPR `shop/redact`: `trial_products` en `trial_ended_at` blijven bewust staan (alleen product-ID's, voorkomt trial-misbruik na herinstallatie).
- 0.3: queue-jobs checken de tier nu zelf (`1da5e88`); `TAB_FEATURES` komt uit `config/tiers.php` `tabs`.

## Wat er al staat (uitgangspunt)

Content SEO-editor (meta, beschrijving, JSON-LD, FAQ, interne links, AI-productafbeelding) voor producten/collecties/pagina's/artikelen, varianten-beschrijvingen, review-schema, bulk optimizer (alleen producten), Blog Writer + contentkalender met ingeplande publicatie, llms.txt/llms-full.txt (App Proxy + redirects), IndexNow, rank tracker (dagelijks), keyword explorer (volume/cluster/trends), concurrentieanalyse, backlinks, LLM-mentions + AI-tracker, GEO-scan, Markets/hreflang-audit, licentie via portal, GDPR-webhooks, theme app block voor schema.

De basis is breed. De grootste gaten zitten in **(1) wat de App Store vereist**, **(2) de standaard SEO-hygiëne die merchants van elke SEO-app verwachten**, en **(3) AI-commerce, waarmee Fyndable zich echt kan onderscheiden**.

---

## Fase 0 - Blokkerend voor App Store-lancering (eerst doen)

| # | Feature | Waarom | Aanpak |
|---|---------|--------|--------|
| 0.1 | **Shopify billing via Shopify App Pricing (voorheen Managed Pricing)** | Apps in de App Store moeten via Shopify afrekenen. Een licentiesleutel van portal.fyndable.ai wordt bij review afgekeurd. De Billing API is inmiddels legacy; Shopify App Pricing is de standaard. | Plannen (Starter/Professional/Business, trial) instellen in het Partner Dashboard. **Let op: App Pricing stuurt na 28 april 2026 geen webhooks meer voor abonnementswijzigingen** (dus geen `app_subscriptions/update`). Status uitlezen via de Partner API (`activeSubscription(appId, shopId)`), plus redirect-parameters `plan_handle` en `shop` na plankeuze. Annuleringen en freezes komen buiten een redirect binnen, dus controleer de status bij elke app-load en met een periodieke sync-job. Bij een actief abonnement automatisch een tenant + licentie aanmaken via SaaS (`platform=shopify`). Handmatige licentie-invoer alleen nog voor agency/enterprise. |
| 0.2 | **Zero-config onboarding + automatische trial** | Nu moet de merchant eerst een licentiesleutel plakken. Dat kost conversie. | Na OAuth-install direct een trial-tenant aanmaken, plus een wizard (winkeltaal, land, merkstem, eerste 5 producten optimaliseren). |
| 0.3 | **Consistente tier-gating (wordt apart in de IDE gebouwd, hier alleen controleren en integreren)** | `hasTier()` wordt maar op 2 plekken aangeroepen. Dure features (GEO-scan, AI-tracker, bulk, keyword explorer) staan open voor elke tier. | Centrale `RequireTier`-middleware op routegroepen + quota per tier vanuit de SaaS (`getApiLimitForTier`). |
| 0.4 | **Library-migratie + testsuite** | `shopify/shopify-api` v6 is abandoned (bekend punt). Er is geen enkele test. | Migreren naar `shopify/shopify-app-php` of een eigen dunne GraphQL-client; Pest-tests voor OAuth-HMAC, webhooks, sessietokens, SaasProxyClient. `composer audit` schoon. |
| 0.5 | **Built for Shopify-eisen** | Badge = ranking in de App Store. | Polaris-web-components/App Bridge-nav, sneller laden (de dashboard-blade is 159 KB in één bestand → opsplitsen), geen theme-impact buiten de app block. |


### Besluit vooraf bij 0.1: App Pricing of Billing API (manual pricing)

- **Standaard: Shopify App Pricing.** Aanbevolen route, minder code, Shopify host de plankeuze-pagina en regelt proration. Beperkingen: prijzen alleen in USD, maximaal 8 publieke plannen, geen eenmalige aankopen, geen usage caps.
- **Billing API (manual pricing)** alleen kiezen als we losse credit-packs (eenmalige aankopen, bv. extra AI-credits), usage caps of volledige controle over kortingen nodig hebben. App Pricing en Billing API kunnen niet gemixt worden: bij App Pricing worden alle Billing API-charges geweigerd, ook eenmalige.
- **Besloten prijzen (USD):** Starter 60 dollar per maand, Professional 199 dollar per maand, Business 299 dollar per maand. Dit past bij App Pricing (prijzen in USD, Shopify rekent om naar de valuta van de merchant) en de featureset wijkt toch af van de WordPress-versie, dus een aparte prijsstelling is logisch. Het eurobedrag voor de merchant schommelt licht mee met de wisselkoers. Drie publieke plannen, ruim binnen de limiet van acht. Agency/enterprise blijft buiten de App Store om (private plannen of handmatige licentie).
- **Tier-gating (0.3) wordt apart opgepakt:** de featureverdeling per tier (Starter, Professional, Business) wordt in een aparte sessie in de IDE gebouwd. Claude Code bouwt die niet opnieuw, maar leest de resulterende tierdefinities uit de code en koppelt de Shopify-plannen daaraan.
- **Te verifieren in de Shopify-docs voor de start:** kortingen en extra trials per merchant via het Dev Dashboard (forumberichten melden dat dit ontbrak), en hoe Agency/enterprise-prijzen als private plannen werken.
- Bronnen: shopify.dev/docs/apps/launch/billing/managed-pricing en de aankondiging "Shopify App Pricing: the next generation of app billing" op community.shopify.dev.

---

## Fase 1 - Kern-SEO die merchants verwachten (pariteit met Plug in SEO, StoreSEO, Smart SEO, Booster)

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

## Fase 2 - Onderscheidend (hier wint Fyndable)

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

1. **Sprint 1-2:** 0.1 Billing, 0.2 onboarding/trial, 0.3 tier-gating
2. **Sprint 3:** 0.4 tests + library, 0.5 opsplitsen dashboard / Built for Shopify
3. **Sprint 4-5:** 1.1 SEO-score + audit, 1.2 redirects/404, 1.4 winkelbreed schema
4. **Sprint 6:** 1.3 bulk-uitbreiding + templates, 1.5 GSC, 1.7 rapportage
5. **Sprint 7-9:** 2.1 AI-commerce readiness, 2.2 AI-crawlers, 2.3 AI-vertalingen
6. **Daarna:** 2.4 ApexFlow, 2.8 AI-omzetmeting, 2.5/2.6/2.7/2.9, 1.6 beeld-SEO

**Positionering:** "De enige Shopify SEO-app die je producten vindbaar maakt in ChatGPT, Perplexity en Google AI." Fase 2.1, 2.2 en 2.8 maken die claim waar. Fase 0 en 1 zijn nodig om überhaupt mee te kunnen doen.

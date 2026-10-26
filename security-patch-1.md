# Security patch 1 — audit 2026-10-23

**Scope:** de 11 open bevindingen uit `code-scan-findings-r-smink-fyndable-20261023-110447.csv`, plus een nieuwe review van alles wat sinds de vorige ronde (16-10) is veranderd: custom plans (`41134ec`), het demo-plugin en demo mode, de ongecommitte backlink-wijzigingen, en een sweep over de publieke REST-, AJAX- en admin-post-endpoints van alle plugins.

**Methode:** de code is handmatig gelezen. Drie parallelle read-only reviews zijn uitgevoerd en elke bevinding is daarna in de code geverifieerd. De fixes zijn getest met `php -l` op alle gewijzigde bestanden en met een los testscript voor de SSRF- en redirect-checks (26/26 geslaagd). Niets is gecommit en niets is getest op een live omgeving.

Legenda: ✅ opgelost in deze patch · 🟡 gedeeltelijk / mitigatie · ⏳ open (actie nodig) · ✔️ geverifieerd, geen actie nodig

---

## 1. Samenvatting

| # | Ernst | Bevinding | Status |
|---|---|---|---|
| N1 | **Kritiek** | `/license/trial` gaf license_key + tenant_key direct terug → anoniem een volledige credential-set (en via `/tenant/status` de image-API-key) | ✅ |
| N2 | **Hoog** | Centrale OpenRouter-key (= LLM-key) gaat naar elke klant via `image_api` (CSV #14, ernst verhoogd) | 🟡 |
| N3 | Medium | `/signup/activate` gaf license_key terug op basis van alleen tenant_key; license_key stond in de `/thank-you/`-URL | ✅ (deels ⏳) |
| N4 | Medium | Client volgde redirects bij licentie-activatie → license key naar willekeurige host | ✅ |
| N5 | Medium | Custom plan: race condition bij licenties toevoegen → onbetaalde licenties | ✅ |
| N6 | Medium | Custom plan: `?plan_payment=done` / herhaalde webhook maakt een tweede Mollie-abonnement aan | ✅ |
| N7 | Medium | Publieke GEO-scan: duplicaat-respons gaf scan_id + token van iemand anders bij diens e-mailadres | ✅ |
| N8 | Medium | Demo-plugin: fixture-data wordt in echte options weggeschreven | ⏳ |
| N9 | Laag-medium | SSRF via concurrent-sitemaps (Competitor Research) | ✅ |
| N10 | Laag | Query-parameter-injectie in SerpAPI/Google Places-URL's | ✅ |
| N11 | Laag | Support-screenshot-upload accepteerde elk bestandstype | ✅ |
| N12 | Laag | Custom plan: geen server-side limiet op het aantal extra licenties | ✅ |
| N13 | Laag | GEO-scan-IP-limiet deelt één bucket voor alle websitebezoekers (DoS) | ⏳ |
| N14 | Laag | Demo: live-call-limiet te omzeilen via reset; demo staat standaard aan | ⏳ |
| N15 | Info | `ajaxPlacesAutocomplete` / `ajaxGeocodeAddress` zonder nonce (alleen credits) | ⏳ |
| CSV | — | 11 open CSV-punten | zie §3 |

---

## 2. Nieuwe bevindingen

### N1 — Kritiek: trial-endpoint gaf volledige credentials (✅)
`fyndable-saas-dashboard/includes/licenseapi.php` · `startTrial()`

- **Probleem:** `POST /ai-seo-saas/v1/license/trial` is publiek en vraagt geen e-mailverificatie. Toch gaf het direct `license_key` + `tenant_key` terug. Met die twee waarden levert `POST /tenant/status` het `image_api`-blok, met de centrale OpenRouter-key (zie N2).
  - **Aanval:** twee requests met een verzonnen e-mailadres, en de aanvaller heeft de operator-key.
  - Daarnaast verraadden de 409-responses (`trial_exists` / `account_exists`) of een e-mailadres al klant is (CSV #24).
  - En de duplicaatcheck (`getAllTenants(500)`) miste duplicaten boven de 500 tenants.
- **Fix:**
  - De response bevat geen keys meer. De license key gaat alleen via de welkomstmail (`sseo_ai_license_activated` → template `welcome` bevat `{{license_key}}`), zodat de e-mail als verificatie dient.
  - Nieuwe en bestaande adressen krijgen een identieke 200-response (`email_sent: true`).
  - De duplicaatcheck loopt via de nieuwe methode `TenantRepository::findTenantsByEmail()` (directe WHERE, geen limiet) en `is_email()`-validatie.
- **Impact:** in de repo roept niets `/license/trial` aan. Mocht een externe site (bijv. fyndable.ai) dit wel gebruiken, dan moet die tonen "check je mail" in plaats van de key.

### N2 — Hoog: centrale OpenRouter-key verlaat de server (🟡)
`licenseapi.php` · `getImageApiData()` (gebruikt door `activateLicense` en `getTenantStatus`)

- **Probleem:** client-sites genereren afbeeldingen zelf en krijgen daarvoor een key mee.
  - Bij provider `openrouter` was dat `sseo_ai_saas_openrouter_api_key`, dezelfde key als voor álle LLM-calls van het platform.
  - Is er geen image-provider gekozen, dan valt `getImageApiProvider()` terug op `sseo_ai_saas_ai_provider` (default `openrouter`). Standaard krijgt dus elke klant, inclusief trial, de hoofd-key.
  - Wie die key uit `wp_options` (`sseo_ai_client_image_api`) haalt, heeft onbeperkt LLM-toegang op kosten van de operator.
- **Mitigatie (deze patch):** nieuwe instelling **Settings → Image → "OpenRouter Image Key (client-side)"** (`sseo_ai_saas_openrouter_image_api_key`). Als die is ingevuld, gaat deze key naar clients in plaats van de centrale key. Zonder invulling blijft het oude gedrag (achterwaarts compatibel).
- **Actie nodig (⏳):**
  1. **Direct:** maak in OpenRouter een aparte key met een credit-limiet en vul die in.
  2. **Daarna roteren:** roteer de centrale OpenRouter-key, want die is al bij alle bestaande klanten opgeslagen.
  3. **Structureel:** laat image-generatie via de bestaande proxy `/ai-seo-saas/v1/ai/image` lopen (die ondersteunt nu alleen de OpenAI-adapter). Breid hem uit met OpenRouter/OpenArt, laat `AIImageGenerator::generateImageFromPrompt()` die proxy aanroepen, en stop met het meesturen van keys.

### N3 — Medium: `/signup/activate` → license key op basis van tenant_key (✅ / ⏳)
`signupcheckout.php` · `restActivate()`, `getSuccessUrl()`

- **Probleem:** voor actieve tenants gaf het publieke endpoint `license_key` terug als je alleen `tenant_key` meegaf. De `tenant_key` staat in de Mollie-return-URL (`?fyndable_payment=…&tenant_key=…`) en lekt dus via browsergeschiedenis, Referer, analytics en het PSP-dashboard. `getSuccessUrl()` zette bovendien de `license_key` zelf in `/thank-you/?license_key=`.
- **Fix:** de response bevat geen `license_key` meer (de key gaat per mail via `sseo_ai_payment_success`), en `/thank-you/` krijgt alleen `?signup=complete`. Geen bekende aanroeper in de repo.
- **Open (⏳):** de `tenant_key` staat nog in de return-URL (`paymentprocessor.php` `getReturnUrl()`), en `handlePaymentReturn()` heeft hem nodig. Structureel: vervang hem door een opaque, eenmalige referentie (transient → tenant_key).

### N4 — Medium: client volgde redirects met de license key (✅)
`fyndable-client/includes/dashboardapi.php` · `activateLicense()`, `curlPost()`

- **Probleem:** bij een 301/302/307/308 POST'te de activatiecode de license key naar elke `Location`. Daarnaast stond `CURLOPT_FOLLOWLOCATION => true` in de cURL-fallback, wat bij 307/308 de body opnieuw POST. Op 16-10 is hetzelfde patroon al in `licensevalidator.php` gerepareerd.
- **Fix:**
  - Redirects worden alleen gevolgd binnen de dashboard-host. `isSameDashboardHost()` staat relatieve URL's, http↔https en `www.` toe. Relatieve redirects worden correct absoluut gemaakt.
  - cURL volgt geen redirects meer en is beperkt tot http/https.
  - De debuglog bevat de `tenant_key` niet meer (CSV #23).

### N5 — Medium: race condition bij custom-plan-licenties (✅)
`agencyportal.php` · `handleCustomPlanAddLicenses()`

- **Probleem:** de flow was lezen → absolute Mollie-PATCH → absoluut quotum wegschrijven, zonder lock. Twee gelijktijdige requests (qty 10 en qty 1) kunnen eindigen met Mollie-bedrag N+1 en een quotum van N+10.
- **Fix:** MySQL `GET_LOCK` per tenant rond de hele lees/PATCH/schrijf-reeks. De telling wordt binnen de lock gelezen en de lock vrijgegeven via een shutdown-functie (en anders bij het sluiten van de verbinding). Een parallelle request krijgt een nette foutmelding.

### N6 — Medium: dubbel Mollie-abonnement / gratis maand (✅)
`agencyportal.php` · `renderDashboard()`; `paymentprocessor.php` · `createMollieSubscription()`

- **Probleem:**
  - **Gratis maand:** het dashboard riep `createMollieSubscriptionFromLastPayment()` aan bij `?plan_payment=done` zonder te kijken of er al een abonnement was. Na een mislukte maandincasso leverde dat een tweede abonnement op en een maand gratis (`expires_at` +1 maand).
  - **Dubbel factureren:** een opnieuw afgeleverde first-payment-webhook (Mollie roept opnieuw aan bij refund of chargeback) maakte ook een duplicaat aan.
- **Fix:**
  - Het dashboard heeft nu dezelfde guard (`!$hasSubscription`) als de add-licenses-pagina.
  - `createMollieSubscription()` is idempotent per eerste betaling: het payment-ID wordt opgeslagen als `mollie_subscription_source_payment`. Wordt hetzelfde payment opnieuw verwerkt terwijl het abonnement `active`/`pending` is, dan komt het bestaande abonnement terug.
  - Een *andere* eerste betaling (bijv. conversie naar een custom plan) maakt nog wel een nieuw abonnement.

### N7 — Medium: GEO-scan-resultaat van een ander opvragen (✅)
`publicapi.php` · `POST /public/geo-scan`

- **Probleem:** wie een scan aanvroeg met het e-mailadres van iemand met een lopende scan, kreeg diens `scan_id` + `scan_token` terug en kon zo de teaser (score, sterke en zwakke punten) uitlezen.
- **Fix:** bij een duplicaat volgt `409 already_scanned` zonder ID of token. De website-widget (`fyndable-geo-scan/includes/widget.php:274`) verwerkte die 409 al met de melding "Voor dit e-mailadres is al een scan aangevraagd." De oorspronkelijke bezoeker hervat de scan via het token in de eigen browser (localStorage).

### N8 — Medium: demo-fixtures overschrijven echte data (⏳)
`fyndable-demo/includes/class-fyndable-demo-runtime.php:26-29, 69-98`

- **Probleem:** de `pre_option_*`-filters geven fixture-data voor elke admin, ook als de echte option gevuld is (het commentaar zegt "alleen als leeg", maar de code doet dat niet). Alleen REST wordt onderschept. Admin-post- en AJAX-handlers (ApexFlow reject/run, competitor analyse) lezen de fixtures en schrijven ze met `update_option` weg.
- **Gevolg:** als het demo-plugin op een echte klantsite actief is, wordt de echte keyword-pool vervangen door demo-data.
- **Niet gefixt:** dit vraagt een productkeuze over hoe de demo zich hoort te gedragen.
- **Aanbeveling:** alleen fixtures teruggeven als de echte option leeg is, of de betreffende `admin_post_*`/`wp_ajax_*`-acties blokkeren terwijl de demo actief is. Installeer het demo-plugin nooit op klant- of productiesites.

### N9 — Laag-medium: SSRF via concurrent-sitemaps (✅)
`fyndable-client/includes/competitorresearch.php` (4 fetches)

- **Probleem:** sub-sitemaps en post-URL's uit de XML van de concurrent werden met `wp_remote_get` opgehaald, inclusief redirects en zonder IP-check. De body ging daarna naar de LLM.
- **Fix:** alle vier de fetches gebruiken nu `wp_safe_remote_get`, dat privé- en loopback-adressen blokkeert, ook na redirects.

### N10 — Laag: parameter-injectie in provider-URL's (✅)
`apigateway.php` (SerpAPI maps/search, Places autocomplete, geocode) en `backlinkanalyzer.php`

- **Probleem:** `add_query_arg()` encodeert waarden niet. Een keyword met `&engine=…` of `#` kon dus de SerpAPI- of Places-call met de platform-key aanpassen.
- **Fix:** waarden gaan door `rawurlencode_deep()`.
- **Client:** de backlink-analyzer weigert nu ongeldige domeinen in plaats van terug te vallen op de ruwe invoer.

### N11 — Laag: support-upload (✅)
`supporttickets.php` · screenshot-upload is beperkt tot jpg, png, gif en webp via `mimes`.

### N12 — Laag: geen server-side limiet op extra licenties (✅)
`agencyportal.php` · `handleAddLicenses()` accepteert server-side alleen 1-100 (HTML `max=100` was alleen client-side).

### N13 — Laag: GEO-scan-IP-limiet is gedeeld (⏳)
`publicapi.php:265`

- **Probleem:** de limiet is 10 per uur per `REMOTE_ADDR`. Alle websitebezoekers komen echter binnen via het IP van de fyndable.ai-server, dus vier aanvallers met elk 3 scans per uur sluiten iedereen buiten.
- **Aanbeveling:** stuur het bezoekers-IP mee in een header die alleen vertrouwd wordt naast een geldige `X-Fyndable-Scan-Key`, en limiteer daarop. Of ontzie key-geauthenticeerde proxy-calls (de widget limiteert zelf al per bezoeker).

### N14 — Laag: demo-plugin (⏳)
- **Live-call-limiet:** de teller zit in dezelfde user-meta die `fyndable_demo_reset` wist, dus na elke reset zijn er weer 2 echte AI-calls. Dit kost geld, maar alleen admins kunnen het.
- **Standaard aan:** `fyndable_demo_enabled` staat standaard op `'1'` en heeft geen uit-knop.
- **Ongebruikte filters:** de license- en tier-filters in demo mode worden nergens aangeroepen. Een license-bypass is dus niet mogelijk, maar de code is dood.

### N15 — Info: Places-AJAX zonder nonce (⏳)
`client.php` `ajaxPlacesAutocomplete` / `ajaxGeocodeAddress` hebben wel een capability-check maar geen nonce. Ze zijn read-only, dus CSRF kost hooguit credits. Voeg bij gelegenheid `check_ajax_referer` toe.

---

## 3. Status van de open punten uit de scan-CSV

| CSV | Bevinding | Oordeel |
|---|---|---|
| #02 | `validateLicense` lekte API-keys | ✔️ **Geverifieerd opgelost.** `validateLicense` geeft geen `image_api` meer terug. `getImageApiData()` wordt alleen vanuit `activateLicense` en `getTenantStatus` aangeroepen. *Maar:* via `/license/trial` was het paar license+tenant anoniem te krijgen (N1, nu opgelost), en de key zelf is het centrale probleem (N2). |
| #07 | Rate limiting license-endpoints | ✔️ **Geverifieerd.** Validate 10/min, activate 5/min, trial 3/uur per IP. `getClientIp()` gebruikt alleen `REMOTE_ADDR`, dus geen XFF-spoofing. Let op achter een reverse proxy: zorg dat de proxy `REMOTE_ADDR` overschrijft. |
| #08 | SSRF in GEO-scan (`htmlfetcher.php`) | ✅ **Aangescherpt.** Alleen http/https. IPv6 wordt nu volledig gecontroleerd (`::`, `::1`, `fe80::/10`, `fc00::/7`, IPv4-mapped `::ffff:x.x.x.x`), en AAAA-records worden ook opgelost via `dns_get_record`. Restrisico is klein: de fetch gebeurt extern (Jina/Firecrawl). |
| #09 | OAuth postMessage met `'*'` | ✅ **Opgelost.** De `'*'`-fallback is weg: zonder geregistreerd domein stopt de flow met een duidelijke melding. Activatie zet het domein altijd (`licensekeygenerator.php:210/255`). Ook gaat `setStatus()` nu via `textContent` in plaats van `innerHTML`. |
| #14 | Image-API-keys verlaten de server | 🟡 **Ernst verhoogd naar hoog,** zie N2. Er is een mitigatie toegevoegd; de proxy-architectuur is nog nodig. |
| #15 | `handlePaymentReturn()` status-check | ✔️ **Geverifieerd.** Actieve tenants met payment `active`/`paid` worden direct doorgestuurd (`signupcheckout.php:668-674`). Bij annuleren wordt eerst de betaling bij Mollie gecontroleerd. |
| #16 | Entropie license key (96 bit) | ⏳ **Bewust niet aangepast.** Het formaat `PREFIX-AI-8hex-8hex-8hex` wordt hard gevalideerd in de client (`fyndable-client/includes/shared-config.php:29`, `assets/client-admin.js:40`). Een extra groep zou alle geïnstalleerde clients breken. 96 bit is met de rate limits ruim voldoende. **Migratiepad:** eerst de client beide formaten laten accepteren, uitrollen, en pas daarna de generator aanpassen. |
| #17 | `$wpdb->query()` zonder `prepare()` | ✔️ **Geen risico.** Alle genoemde queries (contentdecay, ideas, settings, llmtracker, brandvisibilitytracker, notfoundmonitor, ranktracker) gebruiken alleen tabelnamen en constanten. De sweep bevestigt dat nergens request-input ongeprepared in SQL komt. |
| #19 | Debug-scripts `debug-license.php` / `test-api.php` | ⏳ **Niet in de huidige plugins, wel in oude zips.** Ze staan in `versions/ai-seo-client_v1.3.zip`, `versions/fyndable-client_v1.4.zip`, `versions/fyndable-saas-dash_v1.3.zip` en `versions/fyndable-saas-dashboard_v1.4.zip`. Er zitten geen hardcoded secrets in, maar ze tonen key-prefixen en gebruiken `sslverify => false`. **Actie:** verwijder deze oude zips uit de repo (of zorg dat `versions/` nooit mee gedeployed wordt), en installeer ze nooit opnieuw. |
| #23 | Debug-logging in `dashboardapi.php` | ✅ **Opgelost.** Er worden geen response bodies of key-prefixen meer gelogd (alles achter `WP_DEBUG`). De laatste `tenant_key` in de log (direct activation) is in deze patch verwijderd. |
| #24 | E-mail-enumeratie + `getAllTenants(500)` | ✅ / ⏳ **Deels opgelost.** `startTrial` geeft geen onderscheid meer (N1). De duplicaatcheck in `startTrial` en `restRegister` gebruikt `findTenantsByEmail()` zonder 500-limiet. **Open:** `restRegister` geeft nog "account bestaat al" (409). Dat is gebruikelijk bij signup-UX en beperkt tot 10 per uur per IP. Wie het volledig wil dichten, moet de signup naar een e-mail-bevestigingsflow omzetten. |

De 25 punten uit de CSV met status *dismissed* (fix al eerder geverifieerd) zijn niet opnieuw beoordeeld.

---

## 4. Wat is gecontroleerd en in orde bevonden

- **Custom plans:**
  - Alle `admin_post_sseo_ai_custom_plan_*`-handlers checken nonce + `manage_options`.
  - Het portal haalt de tenant uit `getAgencyContext()`, nooit uit de request, dus geen IDOR.
  - Prijzen en aantallen worden server-side berekend.
  - De Mollie-PATCH gebeurt vóór de quotum-verhoging.
  - Webhook-metadata komt van ons eigen, opgehaalde payment.
  - Alle output is ge-escaped.
- **Demo:**
  - De demo mode is niet door niet-admins aan te zetten (geen query-param of cookie).
  - Er is geen license-bypass.
  - Alle `innerHTML` in `demo.js` gaat via `escapeHtml`, en er zijn geen postMessage-listeners.
  - `fyndable-demo.zip` bevat niets gevoeligs.
- **Publieke routes:**
  - Er bestaan geen `wp_ajax_nopriv_*` / `admin_post_nopriv_*`-handlers.
  - Alle `tenant/*`, `usage/report`, `google/*`, `gdpr/*` en `updates/*`-routes vergelijken het license+tenant-paar met `hash_equals`.
  - Webhooks zijn signature-verified (Stripe) of worden via fetch-back gecontroleerd (Mollie).
- **Backlink-wijzigingen** (ongecommit):
  - **XSS:** externe data (anchors, URL's, titels) wordt overal ge-escaped en de outreach-mail gaat via `.val()`.
  - **Rechten:** de AJAX-handlers checken nonce + `manage_options`.
  - **Gateway-parameters:** de `handleBacklinksLive`-params zijn begrensd of gewhitelist.
- **SQL / uploads:** geen request-input ongeprepared in SQL. De zip-slip- en logo-mime-checks uit de vorige ronde zitten er nog.

---

## 5. Gewijzigde bestanden (deze patch)

`fyndable-saas-dashboard/includes/`

| Bestand | Wijziging |
|---|---|
| `licenseapi.php` | N1, N2, #09 |
| `tenantrepository.php` | `findTenantsByEmail()` |
| `signupcheckout.php` | N3, #24 |
| `saassettings.php` | N2-instelling |
| `htmlfetcher.php` | #08 |
| `agencyportal.php` | N5, N6, N12 |
| `paymentprocessor.php` | N6 |
| `publicapi.php` | N7 |
| `apigateway.php` | N10 |
| `supporttickets.php` | N11 |

`fyndable-client/includes/`

| Bestand | Wijziging |
|---|---|
| `dashboardapi.php` | N4, #23 |
| `competitorresearch.php` | N9 |
| `backlinkanalyzer.php` | N10 |

## 6. Acties voor jou

1. **OpenRouter:** maak een aparte, gelimiteerde image-key, vul die in, en **roteer daarna de centrale OpenRouter-key** (N2).
2. **Deploy:** zet de portal en de client-plugin live, want beide bevatten fixes.
3. **Externe `/license/trial`-aanroeper:** heeft fyndable.ai of een andere externe site een formulier dat `/license/trial` aanroept? Laat dat dan "check je mail" tonen in plaats van de key (N1).
4. **Oude zips:** verwijder de vier oude zips met debug-scripts uit `versions/` (CSV #19).
5. **Demo-plugin:** alleen op demo-omgevingen gebruiken (N8). Plan de fix voor fixture-writes.
6. **Backlog:**
   - image-proxy (N2)
   - opaque return-referentie (N3)
   - GEO-scan-rate-limit per bezoeker (N13)
   - migratie van de license-key-entropie (CSV #16)
   - nonce op Places-AJAX (N15)

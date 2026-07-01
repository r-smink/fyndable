# Fyndable Smart SEO — Volledig Functioneel & Commercieel Productrapport

**Datum:** 10 juni 2026
**Status:** Stap 1 (inventarisatie) + Stap 2 (uitwerking per hoofdfunctionaliteit)
**Methode:** Volledige systematische analyse van de daadwerkelijke codebase (client-plugin `ai-seo-client` + SaaS-platform `ai-seo-saas-dashboard`). Alles in dit rapport is geverifieerd tegen de broncode; er staat niets in dat niet daadwerkelijk in de tool zit.

---

## Leeswijzer

- **Deel 0** geeft de productarchitectuur en het waardeverhaal in vogelvlucht.
- **Deel 1 (Stap 1)** is de volledige inventarisatie: alle functionaliteiten, gegroepeerd in 16 hoofdmodules.
- **Deel 2 (Stap 2)** werkt elke hoofdmodule uit in de afgesproken 10-puntsstructuur.
- **Deel 3 (Stap 3)** gaat een niveau dieper: alle onderliggende subfunctionaliteiten per hoofdmodule, in de 6-puntsstructuur (naam, functie, gebruikershandeling, verwerking, resultaat, commerciële relevantie).
- **Deel 4** bevat transparantienotities: zaken die commercieel relevant zijn om te weten vóórdat je claims naar buiten brengt (rijpheid, afhankelijkheden, fallbacks).
- **Deel 5 (Stap 4)** beschrijft de end-to-end gebruikersflow: van eerste contact tot tastbaar SEO-resultaat, inclusief de belangrijkste aha-momenten.

---

# DEEL 0 — PRODUCTARCHITECTUUR & KERNVERHAAL

## Wat is Fyndable Smart SEO technisch?

Fyndable Smart SEO is een **multi-tenant SaaS SEO-platform** dat bestaat uit twee componenten:

1. **De client-plugin (`ai-seo-client`)** — geïnstalleerd op de WordPress-site van de klant. Bevat 70+ functionele modules: van real-time SEO-scoring in de editor tot AI-artikelgeneratie, rank tracking, technische audits en rapportage-export.
2. **Het SaaS-dashboard (`ai-seo-saas-dashboard`)** — draait centraal op het Fyndable-platform. Beheert licenties, tenants (klanten), abonnementen/betalingen (Stripe & Mollie), white-label branding én fungeert als **API-gateway**: alle AI-aanvragen van klanten worden via dit platform geproxied naar OpenAI, inclusief kosten- en verbruiksregistratie per klant.

```
Klant-WordPress (client-plugin)          Fyndable SaaS-platform
┌──────────────────────────────┐        ┌──────────────────────────────┐
│ 70+ SEO-modules              │  REST  │ Licentiebeheer & tenants     │
│ Editor-integratie & scoring  │ ◄────► │ API-gateway (AI & SERP proxy)│
│ Eigen database-tabellen      │  API   │ Verbruiks- & kostentracking  │
│ (keywords, ranks, tests...)  │        │ Billing (Stripe/Mollie)      │
└──────────────────────────────┘        │ White-label & feature toggles│
                                        └──────────────────────────────┘
```

## Het kernverhaal in één alinea

Fyndable Smart SEO brengt de complete SEO-werkstroom — onderzoeken, schrijven, optimaliseren, technisch op orde brengen, monitoren en rapporteren — in één gebruiksvriendelijk portaal binnen WordPress. De gebruiker voert zijn onderwerp, markt en doelgroep in; de AI analyseert waar daadwerkelijk op gezocht wordt (keywords, vragen, zoekintentie, concurrentie in de zoekresultaten) en vertaalt dat naar concrete, uitvoerbare optimalisaties: betere titels en meta-omschrijvingen, ontbrekende termen in content, complete nieuwe artikelen, interne links, structured data en technische fixes. Daarna bewaakt het platform de resultaten met dagelijkse rank tracking, content-decay-detectie en dashboards. Voor bureaus is het volledig white-label inzetbaar.

## De drie commerciële pijlers

| Pijler | Inhoud | Bewijslast in product |
|---|---|---|
| **1. Van data naar inzicht** | AI-gedreven keyword-, SERP- en concurrentieanalyse | Keyword Explorer, Content Brief, SERP Competitor, Keyword Difficulty, Competitor Research |
| **2. Van inzicht naar actie** | Concrete optimalisaties en contentproductie | Content Optimizer, AI Content Writer, Smart Tags, Bulk Optimizer, Schema Markup, Interne linking |
| **3. Van actie naar bewijs** | Monitoring, rapportage en ROI-aantoonbaarheid | Rank Tracker, GSC Dashboard, Content Decay, A/B Testing, Rapport-export, alerts |

---

# DEEL 1 (STAP 1) — INVENTARISATIE VAN ALLE HOOFDFUNCTIONALITEITEN

Alle functionaliteit is gegroepeerd in **16 hoofdmodules**. Per module staan de daadwerkelijk aanwezige onderdelen (met technische component tussen haakjes).

### MODULE 1 — Onboarding, Licentie & Connectiviteit
Verbinding tussen klantsite en SaaS-platform; toegangspoort tot alle features.
- Licentie-activatie & periodieke hervalidatie (`licensevalidator.php`)
- API-communicatie met het SaaS-dashboard, incl. fallbacks (`dashboardapi.php`)
- Centrale AI-proxy-client met tier-afhankelijke modellen en rate limiting (`llmclient.php`)
- Centrale instellingen: licentie, dashboard-URL, AI-temperature, SSL (`settings.php`)
- Feature-gating per licentietier (Free → Starter → Professional → Business → Agency) (`client.php`, FEATURES.md)
- Health-logging & e-mailalerts bij API-fouten (`healthlogger.php`, `alertnotifier.php`)

### MODULE 2 — On-page SEO-analyse & Contentscore
Real-time analyse en scoring tijdens het schrijven.
- TruSEO Score: 0–100 SEO-score met checklist in de editor (`truseoscore.php`)
- Focus keyphrase + secundaire keyphrases, SEO-titel & meta-omschrijving met SERP-preview (`truseoscore.php`)
- Readability Score: Flesch (EN) + Flesch-Douma (NL), passieve zinnen, signaalwoorden (`readabilityscore.php`)
- E-E-A-T Validator: Experience/Expertise/Authoritativeness/Trust-scores per post en sitebreed, auteursprofielen met credentials (`eeatvalidator.php`)
- LSI Keywords: AI-gegenereerde semantische termen + dekkingspercentage (`lsikeywords.php`)

### MODULE 3 — Content Optimizer & Content Brief (SERP-gedreven optimalisatie)
Datagedreven contentoptimalisatie op basis van wat er werkelijk rankt.
- NLP-topicmodel uit SERP-analyse: 30–50 gewogen termen (core/supporting/entity/questions) (`contentoptimizer.php`)
- Real-time contentscore (0–100, grade A–F) met term-heatmap (missing/low/good/overused)
- AI-suggesties voor natuurlijke inpassing van ontbrekende termen
- Content Brief Generator: SERP-top-10-analyse → aanbevolen woordaantal, headings, FAQ-vragen, entiteiten, zoekintentie, moeilijkheid, unieke invalshoek (`contentbrief.php`)
- Content-scoring tegen de brief (6 metrieken)

### MODULE 4 — Keyword Research & Management
Eigen keyworddatabase met AI-gedreven research.
- Keyworddatabase met volume, difficulty, CPC, intent, clusters; CSV-import/export (`keywords.php`)
- AI-keywordgeneratie vanuit onderwerp/branche, meertalig (NL/EN/DE/FR/ES) (`keywords.php`)
- Keyword Explorer: expansie van seed-keywords via SERP-titels + clustering (Jaccard-similarity) (`keywordexplorer.php`)
- Gepersonaliseerde Keyword Difficulty: combineert generieke KD met de eigen topical authority en contentvoorraad van de site (`keyworddifficulty.php`)

### MODULE 5 — Rank Tracking & SERP-monitoring
Dagelijkse bewaking van zoekposities en SERP-features.
- Rank Tracker: dagelijkse positiecheck per keyword/land via SaaS-SERP-proxy, 90 dagen historie, trendweergave (`ranktracker.php`)
- SERP Feature Tracker: optimalisatie voor featured snippets, People Also Ask, image/video pack, met AI-snippetgeneratie en optimalisatiescore per post (`serpfeaturetracker.php`)
- SERP-snapshots voor historische vergelijking (`snapshotrepository.php`)

### MODULE 6 — Concurrentie- & Backlinkanalyse
Inzicht in wat concurrenten doen en waar kansen liggen.
- SERP Competitor: analyse van top-rankende pagina's, topic-heatmap, winnende patronen, content-gaps, vergelijking met eigen content (`serpcompetitor.php`)
- Competitor Research: keywordstrategie, publicatiekalender, AI-content-detectie, win/loss-analyse, advertentieteksten van concurrenten (`competitorresearch.php`)
- Backlink Analyzer: domain authority, backlinkprofiel, toxische links, concurrentvergelijking (Ahrefs of Semrush API) (`backlinkanalyzer.php`, `ahrefsclient.php`)
- Advanced Backlinks: broken-backlink-prospecting, concurrent-linkdoelen, anchor-tekst-risicoanalyse met AI-aanbevelingen (`advancedbacklinks.php`)
- SE Ranking API-client als extra databron (`serankingclient.php`)

### MODULE 7 — AI-Contentcreatie
Volledige contentproductie met AI.
- AI Content Writer: complete SEO-artikelen (titel, intro, secties, FAQ, conclusie, meta-omschrijving), direct als WordPress-concept (`contentwriter.php`)
- Simple Content Generator: contentgeneratie via metabox in de editor (`simplecontentgenerator.php`)
- Content Rewriter: 7 herschrijfmodi (improve, seo_optimize, readability, expand, condense, paraphrase, tone_shift) (`contentrewriter.php`)
- AI Repurposer: 7 outputformaten (Twitter/X, LinkedIn, Facebook, nieuwsbrief, key points, thread, Instagram) (`airepurposer.php`)
- Ideas: AI-contentideeën genereren, beheren en converteren naar (geplande) posts (`ideas.php`)
- Topic Clusters: pillar-cluster-architectuur (10–30 clusters) met interne linkstrategie, contentkalender en bulk-contentgeneratie (`topiccluster.php`)
- Plagiarism/Originality Checker: originaliteitsscore via heuristieken + AI-analyse (`plagiarismchecker.php`)
- Created Posts: beheeroverzicht van alle AI-gegenereerde posts met filters, statussen en bulk-acties (`createdposts.php`)

### MODULE 8 — AI-Beeldgeneratie & Media-SEO
- AI Image Generator: featured images + social images (OG 1200×630, Twitter 1200×600) via DALL-E of Stability AI, incl. bulk (`aiimagegenerator.php`)
- Image Alt Generator: AI-alt-teksten, automatisch bij upload of in bulk, met dekkingsstatistiek (`imagealtgenerator.php`)
- Video SEO: VideoObject-schema, AI-transcriptgeneratie, validatie (`videoseo.php`)

### MODULE 9 — Meta Tags & Social Presentatie
Hoe de site eruitziet in zoekresultaten en op social media.
- Smart Tags: AI-gegenereerde posttags, optioneel automatisch bij publicatie (`smarttags.php`)
- Open Graph & Twitter Cards: per-post overrides, live previews per platform, AI-generatie (`opengraph.php`)
- Bulk AI Optimizer: ontbrekende meta-titels/omschrijvingen/OG-tags/alt-teksten sitebreed scannen en in batch genereren (`bulkactions.php`)

### MODULE 10 — Structured Data & Rich Snippets
- Schema Markup: 17+ JSON-LD-types (Article, Product, LocalBusiness, FAQ, HowTo, Recipe, Event, Review, …) met auto-detectie en custom override (`schemamarkup.php`)
- FAQ Schema: automatische extractie uit content + AI-generatie van FAQ's (`faqschema.php`)
- Local SEO: LocalBusiness-schema met openingstijden, geo-coördinaten, reviews, locatiepagina's (`localseo.php`)
- WooCommerce SEO: Product-schema (prijs, voorraad, ratings, GTIN/MPN) + AI-productomschrijvingen en -meta (`woocommerceseo.php`)
- Breadcrumbs: HTML-breadcrumbs (shortcode) + BreadcrumbList-schema (`breadcrumbs.php`)

### MODULE 11 — Technische SEO & Sitebeheer
- XML Sitemap Generator + extended sitemaps (video, news, images, RSS, authors) (`sitemapgenerator.php`, `extendedsitemaps.php`)
- Robots.txt-editor met regels per user-agent (`robotstxt.php`)
- Canonical URL-beheer met per-post override (`canonicalurl.php`)
- IndexNow: instant indexering bij Bing/Yandex/Naver bij publiceren/wijzigen (`indexnow.php`)
- Redirect Manager: 301/302/307, regex, auto-redirect bij slugwijziging, CSV-import/export, hit-tracking (`redirectionmanager.php`)
- 404 Monitor: logging van 404's met verwijzers, broken-link-scanning in content (`notfoundmonitor.php`)
- Technical SEO Auditor: volledige audit (crawlability, crawl budget, URL-structuur, sitemap-health, performance) met scores en wekelijkse cron (`technicalseoauditor.php`, `techchecker.php`, `auditservice.php`)
- PageSpeed Insights-integratie (performance, LCP, INP, CLS, TTFB, FCP) (`pagespeedclient.php`)

### MODULE 12 — Internationale & Meertalige SEO
- Hreflang-tags: automatisch via WPML/Polylang/TranslatePress of handmatige mapping (`hreflang.php`)
- International SEO: doelmarkten, multi-country keyword tracking, AI-keywordvariaties per land, landspecifieke contentaanbevelingen (`internationalseo.php`)
- Nederlandse taalondersteuning in de hele plugin (NL-vertaalbestanden, Flesch-Douma, NL-fallbacks)

### MODULE 13 — Interne Linking
- Smart Internal Linking: orphan-page-detectie (dagelijkse cron), prioritering, AI-anchortekst-suggesties, auto-fix (`smartinternallinking.php`)
- Link Assistant: relevantiegebaseerde linksuggesties in de editor (`linkassistant.php`)

### MODULE 14 — Dashboards, Monitoring & Rapportage
- SEO Dashboard: sitebrede gezondheidsscore, issues en quick wins (`seodashboard.php`)
- Google Search Console Dashboard: OAuth2-koppeling, impressies/klikken/CTR/positie, topqueries en -pagina's (`gscdashboard.php`, `gscoauth.php`, `gscclient.php`)
- SEO Data Dashboard: geünificeerde weergave van SE Ranking- en Ahrefs-data (`seodatadashboard.php`)
- Content Performance Monitor: GA4-koppeling, conversiedoelen, ROI-trends (`contentperformancemonitor.php`)
- Content Decay Monitor: dagelijkse detectie van dalende rankings met severity-niveaus en suggesties (`contentdecay.php`)
- A/B Testing: split-tests op titel/content/meta met traffic-split, 4 conversiedoelen en statistieken (`abtesting.php`)
- SEO Revisions: volledige historie van SEO-metawijzigingen met restore (laatste 50 per post) (`seorevisions.php`)
- SEO Report Export: CSV- en PDF/HTML-rapporten (`seoreportexport.php`)
- AI-verbruikslogging: alle AI-calls met tokens, kosten en doorlooptijd (`llmtracker.php`)

### MODULE 15 — Integraties, Automatisering & Gebruikersbeheer
- Integratiehub: Slack, Zapier/Make-webhooks, e-mailrapportages (dagelijks/wekelijks/maandelijks), Google Drive, Notion (`externalintegrations.php`)
- Event-hooks: rankwijziging, content gepubliceerd, SEO-scorewijziging
- Content Calendar: visuele redactiekalender met workflow (toewijzen, goedkeuren/afwijzen, deadlines), content-gap-detectie en Slack/e-mailnotificaties (`contentcalendar.php`)
- Role Permissions: granulaire rechten per WordPress-rol (admin/editor/author/contributor) (`rolepermissions.php`)

### MODULE 16 — SaaS-Platform, White-label & Commercieel Beheer (Fyndable-zijde)
- Multi-tenant-architectuur: tenants, per-tenant instellingen en verbruik (`tenantrepository.php`)
- Licentiegeneratie & -beheer: types (trial/paid/lifetime/test), tiers, statussen, revoke (`licensekeygenerator.php`, `licenseadmin.php`)
- License API: activeren, valideren, status, deactiveren, usage-rapportage (`licenseapi.php`)
- API Gateway: proxy voor OpenAI- en SERP-aanvragen met rate limiting, kosten- en verbruikslimieten per tier (`apigateway.php`)
- Feature Toggles: 45+ features per tier, met per-licentie overrides (maatwerkbundels) (`licensefeaturemanager.php`)
- Billing: Stripe (wereldwijd) en Mollie (iDEAL/Bancontact/SEPA) met webhook-verwerking voor upgrades/downgrades/opzeggingen (`paymentprocessor.php`, `webhookhandler.php`)
- White-Label Admin: client portal, teambeheer, billing-dashboard; branding (logo, kleuren, bedrijfsnaam) gesynchroniseerd naar client-plugins (`whitelabeladmin.php`, `whitelabelmanager.php`)
- SaaS-instellingen: AI-provider-keys, SERP-provider (DataForSEO/SerpAPI/SE Ranking), limieten en prijzen per tier (`saassettings.php`)

---

# DEEL 2 (STAP 2) — UITWERKING PER HOOFDFUNCTIONALITEIT

Elke hoofdmodule volgens de vaste 10-puntsstructuur.

---

## HOOFDFUNCTIONALITEIT 1 — Onboarding, Licentie & Connectiviteit

**2. Korte beschrijving:** De toegangspoort van het platform: één licentiesleutel verbindt de klantsite met het Fyndable-platform en ontgrendelt automatisch alle features van het gekozen abonnement.

**3. Doel:** Frictieloze onboarding (geen API-keys, geen technische configuratie voor de klant) en gecontroleerde, veilige toegang per abonnement — de basis van het SaaS-verdienmodel.

**4. Hoe de gebruiker het gebruikt:** Klant installeert de plugin, gaat naar "Connection", voert de dashboard-URL en licentiesleutel in en klikt "Activate License". Daarna verschijnen automatisch alle menu's die bij het abonnement horen. Validatie gebeurt daarna automatisch (uurlijkse cache, dagelijkse cron).

**5. Input:** Licentiesleutel (formaat `SSEO-XXXX-XXXX-XXXX-XXXX`) + dashboard-URL. Verder niets.

**6. Output:** Actieve verbinding (tenant-key), zichtbare featureset passend bij tier, AI-tegoed/limieten, white-label branding (indien ingesteld), health-status en automatische e-mailalerts bij verbindingsproblemen.

**7. Subfunctionaliteiten:**
- Licentie-activatie/-deactivatie/handmatige hervalidatie
- Uurlijkse validatiecache + offline-fallback (site blijft werken bij tijdelijke storing)
- Tier-afhankelijke AI-modellen (Starter: GPT-3.5 → Agency: incl. GPT-4-turbo en hoger)
- Rate limiting per tenant; verbruiksrapportage terug naar het platform
- Health Logger (laatste 20 events) + Alert Notifier (e-mail bij errors)
- Dubbele HTTP-fallback (WordPress HTTP API → native cURL) voor shared-hostingomgevingen

**8. Samenhang:** Fundament voor álle andere modules: elke AI-functie loopt via `LlmClient` → SaaS-gateway; elke SERP-functie via `DashboardAPI`. Feature-gating bepaalt welke modules zichtbaar zijn. White-label branding komt via dit kanaal binnen.

**9. Klantwaarde:** Binnen 2 minuten live, zonder zelf OpenAI- of SERP-accounts te beheren. Eén factuur, één sleutel, nul technische drempels.

**10. Commerciële boodschap:** *"Eén licentiesleutel. Alles werkt."* — Geen API-keys, geen koppelgedoe, geen technische kennis nodig. Activeer en begin direct met optimaliseren.

---

## HOOFDFUNCTIONALITEIT 2 — On-page SEO-analyse & Contentscore (TruSEO)

**2. Korte beschrijving:** Real-time SEO-, leesbaarheids- en E-E-A-T-analyse direct in de WordPress-editor, samengevat in één begrijpelijke score van 0–100.

**3. Doel:** SEO-kennis democratiseren: iedere contentmaker — ook zonder SEO-achtergrond — weet tijdens het schrijven precies wat goed is en wat beter moet.

**4. Hoe de gebruiker het gebruikt:** In de post-editor verschijnt de TruSEO-metabox: focus keyphrase invoeren, SEO-titel en meta-omschrijving schrijven (met live SERP-preview), en de checklist afwerken tot de score groen is (≥80). LSI-keywords zijn één klik; E-E-A-T-scores en aanbevelingen staan in een eigen paneel.

**5. Input:** Focus keyphrase (verplicht voor volledige analyse), optioneel secundaire keyphrases, SEO-titel (max 60 tekens), meta-omschrijving (max 160 tekens). Voor E-E-A-T: auteursprofiel (credentials, expertisegebieden, social profielen).

**6. Output:**
- TruSEO-score 0–100 (kleurgecodeerd) met itemized checklist: contentlengte, keyphrase in titel/intro/headings, interne & uitgaande links, afbeeldingen met alt-tekst, H1-gebruik, URL
- Readability-score met grade (A–D), Flesch-score, percentage passieve zinnen, signaalwoorden, te lange zinnen — **met Nederlandse ondersteuning (Flesch-Douma)**
- E-E-A-T-scores per dimensie (post- én siteniveau), trust-signal-checklist (HTTPS, privacy-, contact-, over-ons-pagina, auteursbio's), auteurs-scoreboard
- LSI-keywordcloud met dekkingspercentage

**7. Subfunctionaliteiten:** Scoreberekening over 8 gewogen categorieën; SERP-preview; AI-verbetersuggesties; LSI-generatie met AI (incl. fallback); E-E-A-T-auteursprofielvelden; sitebrede E-E-A-T-analyse; REST-endpoints voor analyse en suggesties.

**8. Samenhang:** De focus keyphrase is de spil: LSI Keywords, Open Graph, Smart Tags en het SEO Dashboard lezen hem. De score voedt het sitebrede SEO Dashboard en de rapport-export. Content Optimizer en Content Brief bouwen erop voort voor diepere optimalisatie.

**9. Klantwaarde:** Direct feedback in plaats van achteraf gokken; consistente kwaliteit over alle auteurs heen; uniek in de Nederlandse markt: leesbaarheidsanalyse die écht voor Nederlands ontwikkeld is.

**10. Commerciële boodschap:** *"Weet of je content scoort — vóórdat je publiceert."* — Eén score, een concrete checklist, en AI die je vertelt hoe je van oranje naar groen komt. Inclusief E-E-A-T: het kwaliteitskader waar Google op stuurt.

---

## HOOFDFUNCTIONALITEIT 3 — Content Optimizer & Content Brief

**2. Korte beschrijving:** SERP-gedreven contentoptimalisatie: de tool analyseert wat er werkelijk rankt voor jouw keyword en vertaalt dat naar een concreet termen- en structuurplan voor jouw pagina (vergelijkbaar met SurferSEO/MarketMuse).

**3. Doel:** Het giswerk uit contentoptimalisatie halen: niet "schrijf goede content", maar "deze 38 termen, deze headings, dit woordaantal en deze vragen — dat is wat de top-10 doet en jij nog niet".

**4. Hoe de gebruiker het gebruikt:**
- **Optimizer:** keyword invoeren → AI bouwt topicmodel uit SERP-data → content plakken/schrijven → score (0–100, grade A–F) met term-heatmap → ontbrekende termen met één klik laten "inweven" door AI.
- **Brief:** keyword + land invoeren → complete contentbrief met top-10-overzicht, aanbevolen woordaantal, headingstructuur, FAQ-vragen, entiteiten, zoekintentie, moeilijkheid en unieke invalshoek. De brief is direct doorstuurbaar naar de AI Content Writer.

**5. Input:** Doelkeyword (verplicht), land (optioneel, default us), eigen content (voor scoring), titel (optioneel).

**6. Output:** Topicmodel met 30–50 gewogen termen in 4 categorieën (core/supporting/entities/questions); contentscore met per-term status (missing/low/good/overused); zoekintentie en moeilijkheidsindicatie; gedetecteerde SERP-features; volledige contentbrief; AI-tekstsuggesties voor ontbrekende termen; interne linkkansen.

**7. Subfunctionaliteiten:** SERP-fetch via SaaS-proxy; AI-topicmodelgeneratie (7 dagen cache); structuurscoring (woordaantal, headings, afbeeldingen, alinea's); brief-scoring op 6 metrieken; keyword-moeilijkheid op basis van autoriteitsdomeinen in de SERP; AI-fallback als SERP-data niet beschikbaar is.

**8. Samenhang:** Gebruikt DashboardAPI (SERP) en LlmClient (AI). De brief voedt de AI Content Writer (zelfde outline en aanbevelingen). De Optimizer-score is complementair aan TruSEO: TruSEO checkt SEO-hygiëne, de Optimizer checkt inhoudelijke volledigheid t.o.v. de concurrentie.

**9. Klantwaarde:** Vervangt losse, dure optimalisatietools; verkort de tijd van keyword naar publicatieklare, competitieve content drastisch; geeft schrijvers een objectief doel in plaats van een onderbuikgevoel.

**10. Commerciële boodschap:** *"Schrijf niet wat jíj denkt dat Google wil. Schrijf wat de top-10 bewijst."* — Fyndable leest de zoekresultaten voor je uit en geeft je het exacte recept: termen, structuur, vragen en lengte.

---

## HOOFDFUNCTIONALITEIT 4 — Keyword Research & Management

**2. Korte beschrijving:** Een eigen keyworddatabase in het portaal, gevuld via AI-research op basis van product, dienst of branche — meertalig en geclusterd.

**3. Doel:** Het startpunt van elke SEO-strategie: ontdekken met welke termen, vragen en problemen de doelgroep daadwerkelijk zoekt, en die georganiseerd beheren richting content.

**4. Hoe de gebruiker het gebruikt:** Gebruiker opent Keywords, voert een onderwerp/branche in en kiest taal (NL/EN/DE/FR/ES) → AI genereert relevante keywords met metrics → gebruiker filtert op moeilijkheid en intentie, organiseert in clusters, of importeert bestaande lijsten via CSV. Vanuit de Keyword Explorer kunnen seed-keywords worden uitgebreid en automatisch geclusterd.

**5. Input:** Onderwerp/seed-keyword + taal; optioneel eigen CSV-lijsten, clusterindeling.

**6. Output:** Keywordtabel met zoekvolume, difficulty (laag/middel/hoog), CPC, zoekintentie, rankstatus en positiewijziging; gerelateerde keywords met frequentiescores; automatische clusters; CSV-export; gepersonaliseerde difficulty-analyse (zie hieronder).

**7. Subfunctionaliteiten:**
- AI-keywordgeneratie en keyword-ideeëngeneratie (gekoppeld aan de Ideas-module)
- Keyword Explorer: n-gram-extractie uit SERP-titels + Jaccard-clustering
- **Gepersonaliseerde Keyword Difficulty** (onderscheidend): combineert generieke moeilijkheid met de topical authority en contentvoorraad van de éigen site → "voor jou is dit keyword makkelijker/moeilijker dan gemiddeld", incl. aanbeveling en geschatte inspanning
- Bulk-operaties, filters, eigen databasetabel

**8. Samenhang:** Keywords stromen door naar Rank Tracker (tracking), Topic Clusters (architectuur), Content Brief/Writer (productie) en Ideas (planning). Cluster-ID's verbinden keywords met gegenereerde posts (Created Posts).

**9. Klantwaarde:** Geen losse keywordtool meer nodig; research in de eigen taal en markt; en uniek: moeilijkheid berekend voor jóuw site in plaats van een generiek getal — dus realistische prioriteiten.

**10. Commerciële boodschap:** *"Ontdek waar jouw klanten écht op zoeken — en welke keywords jíj kunt winnen."* — Voer je product, dienst of branche in en krijg een geclusterde keywordstrategie, gepersonaliseerd op de autoriteit van je eigen website.

---

## HOOFDFUNCTIONALITEIT 5 — Rank Tracking & SERP-monitoring

**2. Korte beschrijving:** Dagelijkse, automatische bewaking van zoekposities per keyword en land, plus gerichte optimalisatie voor SERP-features zoals featured snippets en People Also Ask.

**3. Doel:** Bewijzen dat SEO werkt en direct kunnen ingrijpen bij dalingen — zonder handmatig posities te checken.

**4. Hoe de gebruiker het gebruikt:** Keywords toevoegen met doel-URL en land (o.a. NL/BE/DE/FR/US) → dagelijkse automatische check via cron → tabel met huidige/vorige/beste positie en verandering (▲/▼) → klik voor 90-dagen-historiegrafiek; "Check All Now" voor directe controle. In de editor: SERP-feature-metabox met doelfeatures, AI-gegenereerde featured-snippet-tekst en PAA-vragen, en een optimalisatiescore per post.

**5. Input:** Keyword, doel-URL, landcode. Voor SERP-features: gewenste features + (AI-)snippetcontent en PAA-vraag/antwoordparen.

**6. Output:** Posities en trendhistorie (90 dagen, dagelijkse meting); positieveranderingen; SERP-feature-optimalisatiescore (0–100%) met 7-puntschecklist; featured-snippet-preview; dashboard met alle posts en hun feature-status.

**7. Subfunctionaliteiten:** Dagelijkse WP-cron-check via de SaaS-SERP-proxy; twee databasetabellen (tracked keywords + rank history); historiegrafieken; AI-generatie van snippets en PAA-antwoorden; SERP-snapshotopslag voor historische vergelijking; event-hook `sseo_ai_rank_change` die alerts naar Slack/webhooks kan sturen.

**8. Samenhang:** Voedt Content Decay Monitor (dalingsdetectie), Content Performance Monitor (correlatie met traffic), Integratiehub (alerts) en rapportages. Keywords komen uit de Keyword-module.

**9. Klantwaarde:** Dagelijks objectief bewijs van voortgang; vroege waarschuwing bij dalingen; extra SERP-vastgoed via snippets en PAA zonder hoger te hoeven ranken.

**10. Commerciële boodschap:** *"Elke dag weten waar je staat in Google — automatisch."* — Posities, trends en alerts in je eigen dashboard. En met SERP-feature-optimalisatie pak je de posities bóven positie 1.

---

## HOOFDFUNCTIONALITEIT 6 — Concurrentie- & Backlinkanalyse

**2. Korte beschrijving:** Volledige concurrentie-intelligentie: wat publiceren concurrenten, met welke keywords winnen ze, hoe ziet hun linkprofiel eruit en waar liggen jouw kansen.

**3. Doel:** SEO-beslissingen baseren op de werkelijke concurrentiesituatie in plaats van op aannames; concrete gaten vinden die snel te winnen zijn.

**4. Hoe de gebruiker het gebruikt:** Concurrent-domein invoeren → tabbladen met keywordstrategie, contentkalender (publicatiefrequentie), AI-content-detectie, win/loss per keyword, backlinkstrategie en advertentieteksten. In SERP Competitor: keyword invoeren → heatmap van topics bij de top-10 → eigen content plakken → competitive score + gap-analyse. In Backlink Analyzer: domain authority, linkprofiel, toxische links; in Advanced Backlinks: broken-link-kansen en anchor-risicoanalyse.

**5. Input:** Concurrent-domein(en); keyword (+ land); eigen content voor vergelijking; voor backlinkdata: Ahrefs- of Semrush-API-key (instelbaar).

**6. Output:** Concurrentprofielen (formaat, woordaantal, sterktes/zwaktes, unieke invalshoek); topic-heatmap met dekkingspercentages; content-gaps met prioriteit; win/loss-overzicht met win-rate; AI-content-percentage bij concurrenten; domain rating, referring domains, ankerverdeling met risiconiveau (penalty-preventie); broken-backlink-kansen met opportunity-score; AI-aanbevelingen voor linkstrategie.

**7. Subfunctionaliteiten:** SERP-analyse via proxy + AI-duiding; sitemap-parsing en contentscraping van concurrenten; AI-detectie van machinaal geschreven concurrentcontent; toxic-link-detectie; opportunity-scoring (DR + ankerrelevantie + dofollow); prioritering van linkdoelen; 1-dags-caching per concurrent.

**8. Samenhang:** Win/loss gebruikt de eigen keyworddatabase; content-gaps stromen door naar Content Brief/Writer; linkkansen ondersteunen de outreach-werkstroom; alle AI-duiding loopt via de centrale gateway.

**9. Klantwaarde:** Het strategische voordeel van een duur concurrentieonderzoek, doorlopend en in eigen beheer; voorkomt linkprofiel-risico's; maakt de "waarom staan zij boven ons?"-vraag beantwoordbaar.

**10. Commerciële boodschap:** *"Kijk in de keuken van je concurrent."* — Zie welke keywords ze winnen, hoe vaak ze publiceren, waar hun links vandaan komen — en precies waar jij ze kunt inhalen.

---

## HOOFDFUNCTIONALITEIT 7 — AI-Contentcreatie

**2. Korte beschrijving:** Complete AI-contentproductielijn: van idee en topical-authority-plan tot volledig SEO-geoptimaliseerd artikel als WordPress-concept, inclusief herschrijven, hergebruik en originaliteitscontrole.

**3. Doel:** De grootste kostenpost en bottleneck van SEO — contentproductie — radicaal versnellen, zonder kwaliteitsverlies en geïntegreerd met de SEO-data uit de rest van het platform.

**4. Hoe de gebruiker het gebruikt:**
- **Ideas:** onderwerp invoeren → 5–20 AI-contentideeën (meertalig) → beheren, filteren en met één klik converteren naar concept of ingeplande post.
- **Topic Clusters:** kernonderwerp invoeren → AI bouwt een pillar-cluster-architectuur (10–30 clusters met hub- en ondersteunende pagina's, keywords, woordaantallen, prioriteiten, interne linkstrategie en weekplanning) → bulk-contentgeneratie met voortgangsbalk.
- **AI Content Writer:** keyword + toon + woordaantal (en optioneel een Content Brief) → volledig artikel met intro, secties, FAQ, conclusie en meta-omschrijving → direct als WordPress-concept.
- **Rewriter/Repurposer:** bestaande content verbeteren in 7 modi of omzetten naar 7 socialmedia-/nieuwsbriefformaten.
- **Originality Checker:** één klik → originaliteitsscore met verdachte segmenten.
- **Created Posts:** centraal beheer van alle gegenereerde content met edit-/reviewstatussen en bulk-acties.

**5. Input:** Doelkeyword of onderwerp; toon (professional/casual/friendly/technical/authoritative); doelwoordaantal; taal (NL/EN/DE/FR/ES e.a.); optioneel eigen outline, extra context of een contentbrief.

**6. Output:** Publicatieklare HTML-artikelen als concept (incl. titel en meta-omschrijving); contentideeën-pijplijn; volledige cluster-architectuur met contentkalender; herschreven/geherformatteerde content; originaliteitsscore (0–100, grade A–D); beheerdashboard met statussen.

**7. Subfunctionaliteiten:** Sectie-voor-sectie-generatie; automatische outline als die ontbreekt; FAQ-generatie op basis van aanbevolen vragen; append- of vervangmodus in de editor (Gutenberg én Classic); 4 heuristische originaliteitschecks (interne duplicaten, zinsvariatie, AI-frasedetectie, woordenschatrijkdom) + AI-analyse; kostenindicatie bij bulkgeneratie; taalspecifieke fallbacks (Nederlands).

**8. Samenhang:** Het sluitstuk van de funnel: Keywords → Brief → Writer → TruSEO/Optimizer-score → Smart Tags/Schema → Rank Tracker. Topic Clusters orkestreert de Writer voor bulkproductie; Ideas plant via de Content Calendar; alles is traceerbaar in Created Posts en het AI-verbruikslog.

**9. Klantwaarde:** Contentproductie van dagen naar minuten; topical authority systematisch opbouwen in plaats van losse blogjes; volledige controle en reviewworkflow over wat de AI maakt; meertalig dus direct bruikbaar voor de Nederlandse markt.

**10. Commerciële boodschap:** *"Van zoekwoord naar publicatieklaar artikel — in minuten, niet dagen."* — En niet zomaar tekst: content gebouwd op SERP-data, gestructureerd voor topical authority en gecheckt op originaliteit.

---

## HOOFDFUNCTIONALITEIT 8 — AI-Beeldgeneratie & Media-SEO

**2. Korte beschrijving:** AI-gegenereerde featured- en social-afbeeldingen, automatische alt-teksten en video-SEO met schema en AI-transcripten.

**3. Doel:** De visuele kant van SEO — vaak verwaarloosd — automatiseren: elke post een passend beeld, elke afbeelding een alt-tekst (toegankelijkheid + image-SEO), elke video vindbaar.

**4. Hoe de gebruiker het gebruikt:** In de editor of via de bulk-pagina: stijl kiezen (photorealistic, illustration, abstract, minimalist, 3D-render) → AI genereert featured image + OG-afbeelding (1200×630) + Twitter-afbeelding (1200×600) en koppelt ze automatisch. Alt-teksten worden bij upload automatisch gegenereerd of in bulk aangevuld. Voor video's: URL + metadata invoeren, AI-transcript genereren, schema valideren.

**5. Input:** Post(content) als context; beeldstijl; optioneel eigen prompt. Voor video: video-URL (YouTube/Vimeo/self-hosted), duur, uploaddatum.

**6. Output:** WordPress-attachments (featured + social images); alt-teksten (max 125 tekens) met dekkingsstatistiek; VideoObject-schema; AI-transcript; statistiekendashboard (gegenereerd/ontbrekend).

**7. Subfunctionaliteiten:** Twee beeldproviders (OpenAI DALL-E en Stability AI); AI-promptgeneratie uit postcontent; bulkgeneratie met voortgang; lijst van afbeeldingen zonder alt-tekst; schema-validatiechecklist voor video.

**8. Samenhang:** Featured/OG-beelden voeden Open Graph; alt-teksten tellen mee in de TruSEO-score; videoschema sluit aan op de Schema Markup-module; alles via de centrale AI-gateway.

**9. Klantwaarde:** Geen stockfoto-abonnement of designer nodig voor blogbeelden; alt-tekst-achterstanden (vaak duizenden afbeeldingen) in bulk opgelost; video's die eindelijk meedoen in Google.

**10. Commerciële boodschap:** *"Ook je beelden en video's verdienen SEO."* — Unieke AI-beelden per artikel, automatische alt-teksten voor je hele mediabibliotheek en video's die Google begrijpt.

---

## HOOFDFUNCTIONALITEIT 9 — Meta Tags & Social Presentatie

**2. Korte beschrijving:** Automatische en AI-ondersteunde generatie van posttags, Open Graph- en Twitter Card-tags — per post of in bulk over de hele site.

**3. Doel:** Maximale klikwaarde uit bestaande posities halen: hoe content eruitziet in Google en op social bepaalt de CTR, en dat mag nooit afhangen van vergeten metavelden.

**4. Hoe de gebruiker het gebruikt:** In de editor: tab Facebook/Twitter met live preview, velden met tekentellers en een "AI Generate"-knop; Smart Tags-metabox met klikbare tagsuggesties (optioneel automatisch bij publicatie). Sitebreed: Bulk AI Optimizer scant alle posts op ontbrekende meta en genereert die in batch, met statuskolom (✓/!) in het postoverzicht.

**5. Input:** Postcontent (automatisch); optioneel handmatige overrides per veld; selectie van posts voor bulk.

**6. Output:** Volledige OG-/Twitter-tags in de frontend met intelligente fallback-keten (override → SEO-meta → featured image → default); 5–10 relevante posttags; in bulk gegenereerde meta-titels/-omschrijvingen/alt-teksten; platform-previews.

**7. Subfunctionaliteiten:** Context-specifieke meta voor home/archief/auteurspagina's; article-tags (publicatiedatum, auteur, sectie); AI-generatie per post; frequentie-gebaseerde tag-fallback zonder AI; batchverwerking met voortgang.

**8. Samenhang:** Leest SEO-meta uit TruSEO; gebruikt AI-beelden uit de Image Generator als OG-image; bulkresultaten verbeteren direct de sitebrede dashboard-score.

**9. Klantwaarde:** Nooit meer kale, lelijke links op social of in Google; legacy-content (honderden posts zonder meta) in één batch op orde; consistente merkuitstraling in elk zoekresultaat.

**10. Commerciële boodschap:** *"Elke post perfect gepresenteerd — in Google én op social. Automatisch."* — Meer klikken uit dezelfde posities, ook voor je oude content.

---

## HOOFDFUNCTIONALITEIT 10 — Structured Data & Rich Snippets

**2. Korte beschrijving:** Automatische JSON-LD structured data voor 17+ contenttypes — van artikelen en FAQ's tot producten en lokale bedrijven — voor opvallende rich snippets in Google.

**3. Doel:** Extra zichtbaarheid en CTR claimen zonder hoger te ranken: sterren, prijzen, FAQ-uitklappers en breadcrumbs direct in het zoekresultaat.

**4. Hoe de gebruiker het gebruikt:** Grotendeels automatisch: het juiste schema wordt gedetecteerd en uitgevoerd (Product bij WooCommerce, FAQPage bij FAQ-blokken, HowTo bij stappenpatronen; WebSite/Organization/BreadcrumbList altijd). Per post kan een type worden gekozen of custom JSON-LD worden ingevoerd. FAQ's: automatisch extraheren uit content of door AI laten genereren (1–10 vragen, optioneel direct in de content). Local SEO: bedrijfsgegevens, openingstijden en locatie invullen → LocalBusiness-schema. WooCommerce: AI genereert productomschrijvingen (kort/lang) en SEO-meta per product.

**5. Input:** Meestal niets (auto-detectie). Optioneel: schematype, custom JSON, bedrijfsgegevens (adres, openingstijden "Mo-Fr 09:00-17:00", coördinaten, prijsklasse), productvelden (merk, GTIN, MPN).

**6. Output:** Valide JSON-LD in de `<head>`: Article, BlogPosting, NewsArticle, Product (incl. prijs/voorraad/reviews/varianten), LocalBusiness, Organization, Person, FAQPage, HowTo, Recipe, Event, Course, SoftwareApplication, Review, AggregateRating, BreadcrumbList, WebSite, VideoObject, CollectionPage (shoppagina's); AI-productcontent; FAQ-statistiekendashboard.

**7. Subfunctionaliteiten:** Auto-detectie van schematype; FAQ-extractie uit Gutenberg-blokken en Q&A-patronen; AI-FAQ-generatie; reviewschema-aggregatie; AggregateOffer voor variabele producten; breadcrumb-schema met primaire-categorie-detectie (compatibel met Yoast/RankMath-data bij migratie).

**8. Samenhang:** FAQ-schema versterkt de People Also Ask-strategie van de SERP Feature Tracker; productschema bouwt op WooCommerce-data; breadcrumbs delen logica met de Breadcrumbs-module; AI-generatie via de centrale gateway.

**9. Klantwaarde:** Rich snippets zonder ontwikkelaar of technische kennis; e-commerce toont prijs en voorraad al in Google (gekwalificeerder verkeer); lokale bedrijven worden correct begrepen door Google.

**10. Commerciële boodschap:** *"Val op in Google met sterren, prijzen en uitklapbare vragen."* — Structured data die normaal een developer vereist, hier volledig automatisch.

---

## HOOFDFUNCTIONALITEIT 11 — Technische SEO & Sitebeheer

**2. Korte beschrijving:** Het complete technische SEO-fundament: sitemaps, robots.txt, canonicals, instant indexering, redirects, 404-bewaking en een volledige technische audit met scores.

**3. Doel:** Garanderen dat al het contentwerk niet weglekt door technische problemen — crawlbaarheid, indexeerbaarheid en sitestructuur permanent op orde.

**4. Hoe de gebruiker het gebruikt:** Grotendeels "set & forget": sitemaps en canonicals worden automatisch gegenereerd, IndexNow pingt zoekmachines automatisch bij publiceren/wijzigen, slugwijzigingen krijgen automatisch een 301-redirect. Actief: robots.txt-editor, redirectbeheer (incl. CSV-import/export en regex), 404-log met fix-suggesties, en de "Run Full Audit"-knop voor het complete technische rapport (ook wekelijks automatisch via cron).

**5. Input:** Minimaal. Optioneel: robots.txt-regels, redirects (bron/doel/type), PageSpeed API-key.

**6. Output:** XML-sitemaps (index + per posttype, 1000 URL's per bestand, met hreflang) en extended sitemaps (video, news, images, RSS, authors); robots.txt; canonical-tags voor elk paginatype; IndexNow-submissielog; redirecttabel met hit-tracking; 404-log met verwijzers en hit-counts; auditrapport met vier scores (crawlability, performance, structuur, sitemap-health) en concrete issues (redirect chains, orphaned pages, thin content <300 woorden, duplicaten, te lange URL's, ontbrekende compressie/caching/CDN); PageSpeed-metrics (LCP, INP, CLS, TTFB, FCP).

**7. Subfunctionaliteiten:** Automatische pings naar Google/Bing bij sitemapupdates; broken-link-scanning binnen content; vergelijkbare-URL-suggesties voor 404's; redirect-chain-detectie (max 5 hops); URL-structuuranalyse; sitemap-validatie met steekproef; wekelijkse auditcron.

**8. Samenhang:** 404 Monitor → Redirect Manager (fix-workflow); auditresultaten verschijnen in het SEO Dashboard; sitemaps gebruiken hreflang-data uit de meertaligheidsmodule; IndexNow versnelt het effect van alle contentmodules.

**9. Klantwaarde:** Vervangt drie tot vier losse plugins (sitemap, redirects, 404, audit); voorkomt stille rankingverliezen door technische regressies; geeft niet-techneuten een begrijpelijk technisch rapport.

**10. Commerciële boodschap:** *"De technische basis altijd op orde — zonder techneut."* — Sitemaps, redirects, 404's en audits: automatisch geregeld en wekelijks gecontroleerd.

---

## HOOFDFUNCTIONALITEIT 12 — Internationale & Meertalige SEO

**2. Korte beschrijving:** Hreflang-automatisering plus AI-gedreven internationale keywordresearch en landspecifieke contentaanbevelingen voor sites die meerdere markten bedienen.

**3. Doel:** Correct vindbaar zijn in elke doelmarkt: de juiste taalversie aan de juiste zoeker tonen en per land de juiste zoektermen gebruiken.

**4. Hoe de gebruiker het gebruikt:** Bij gebruik van WPML, Polylang of TranslatePress worden hreflang-tags volledig automatisch gegenereerd; anders handmatige mapping per post. In het International SEO-dashboard: doelmarkten kiezen → AI genereert keywordvariaties per land → multi-country tracking → per post doelland, taal en valuta instellen → landspecifieke contentaanbevelingen ophalen.

**5. Input:** Doellanden/-talen; keywords; optioneel per post: doelland, valuta, geo-keywords.

**6. Output:** `hreflang`-tags (incl. x-default) in head én sitemaps; keywordvariaties per markt; rankings per land; internationale linkbuilding-suggesties; contentaanbevelingen per land.

**7. Subfunctionaliteiten:** Auto-detectie van WPML/Polylang/TranslatePress; 12 voorgedefinieerde taalregio's (o.a. nl-nl, nl-be, de-de, fr-fr); AI-research- en aanbevelingsendpoints; meertalige UI (volledige Nederlandse vertaalbestanden aanwezig).

**8. Samenhang:** Hreflang voedt de Sitemap Generator; landgebonden tracking sluit aan op de Rank Tracker (landcodes); AI-research gebruikt dezelfde gateway als de keywordmodule.

**9. Klantwaarde:** Voorkomt het klassieke probleem dat de verkeerde taalversie rankt; opent buurmarkten (België, Duitsland) met onderbouwde keyworddata in plaats van letterlijke vertalingen.

**10. Commerciële boodschap:** *"Vindbaar in elke markt die je bedient."* — Van Nederland tot België en Duitsland: de juiste taalversie, de juiste zoektermen, automatisch geregeld.

---

## HOOFDFUNCTIONALITEIT 13 — Interne Linking

**2. Korte beschrijving:** Automatische detectie van wees-pagina's (pagina's zonder inkomende links) en AI-ondersteunde interne linksuggesties met passende ankerteksten.

**3. Doel:** De interne linkstructuur — een van de meest onderschatte rankingfactoren — systematisch versterken zonder handwerk: autoriteit verdelen, crawlbaarheid verbeteren en content met elkaar verbinden.

**4. Hoe de gebruiker het gebruikt:** Dashboard toont orphan pages (dagelijks automatisch gedetecteerd) met prioriteit en een "Auto-fix"-optie; in de editor toont de Link Assistant relevante interne linkkansen op basis van keyword-overeenkomst, met voorgestelde ankertekst.

**5. Input:** Geen — werkt op bestaande content. Optioneel: acceptatie/afwijzing per suggestie.

**6. Output:** Orphan-page-lijst met prioriteitsscore (relevantie × traffic × diepte); linksuggesties met AI-ankerteksten; linkstatistieken.

**7. Subfunctionaliteiten:** Dagelijkse orphan-detectiecron; relevantiescoring met drempelwaarde; AI-anchorgeneratie; interne-linkindex.

**8. Samenhang:** Gebruikt focus keyphrases uit TruSEO voor relevantiebepaling; orphan-fixes verhogen de dashboard-score; Topic Clusters levert de strategische linkarchitectuur die deze module operationeel maakt.

**9. Klantwaarde:** Verborgen content wordt weer vindbaar; bestaande autoriteit wordt beter benut (gratis rankingwinst uit eigen site); bespaart het monnikenwerk van handmatige linkrondes.

**10. Commerciële boodschap:** *"Geen pagina blijft onvindbaar."* — Fyndable vindt je vergeten pagina's en verbindt je content automatisch met slimme interne links.

---

## HOOFDFUNCTIONALITEIT 14 — Dashboards, Monitoring & Rapportage

**2. Korte beschrijving:** Het bewijscentrum van het platform: sitebrede gezondheidsscore, Google Search Console- en GA4-data, content-decay-alerts, A/B-tests, revisiehistorie en exporteerbare rapporten.

**3. Doel:** SEO meetbaar en verantwoordbaar maken — voor de ondernemer ("werkt het?"), het team ("wat eerst?") en het bureau ("dit hebben we geleverd").

**4. Hoe de gebruiker het gebruikt:** Het SEO Dashboard opent met een gezondheidsscore, issuelijst en quick wins. Eén keer Google Search Console koppelen (OAuth) geeft 28-dagen-trends, topqueries en toppagina's in het portaal. Content Decay waarschuwt automatisch bij structureel dalende content (severity: low → critical). A/B-tests op titel/content/meta worden aangemaakt met traffic-split en conversiedoel; statistieken tonen de winnaar. Rapporten exporteer je als CSV of PDF; elke SEO-wijziging is terug te draaien via SEO Revisions.

**5. Input:** Eenmalige koppelingen (GSC OAuth, GA4 Measurement ID, optioneel SE Ranking/Ahrefs-keys); voor A/B-tests: varianten + doeltype (page_view, click, form_submit, time_on_page).

**6. Output:** Gezondheidsscore + quick wins; GSC-metrics (impressies, klikken, CTR, positie); GA4-traffic en conversies met ROI-trends; decay-alerts met suggesties; A/B-teststatistieken (impressies, conversies per variant); CSV/PDF-rapporten (per post: scores, meta, issues); revisiehistorie (laatste 50 per post) met restore; AI-verbruikslog (calls, tokens, kosten, succespercentage).

**7. Subfunctionaliteiten:** Dagelijkse performance- en decay-crons; positietrend-tabellen; cookie-gebaseerde A/B-sessietoewijzing (30 dagen) met frontend-variantinjectie; conversietracking-script; baseline-vs-optimalisatievergelijking; time-to-rank-tracking; automatische log-pruning (90 dagen).

**8. Samenhang:** Aggregeert vrijwel alle modules: TruSEO-scores, Rank Tracker-posities, GSC-data, decay-detectie en auditresultaten komen hier samen. De rapport-export en e-mail-/Slack-rapportages (Module 15) zijn de leveringskanalen richting klant of management.

**9. Klantwaarde:** Eén plek voor de "werkt het?"-vraag; vroege waarschuwing vóórdat traffic-verlies pijn doet; A/B-bewijs in plaats van meningen; voor bureaus: professionele klantrapportages zonder extra tooling.

**10. Commerciële boodschap:** *"SEO zonder bewijs is een mening. Fyndable levert het bewijs."* — Scores, posities, verkeer en conversies in één dashboard, met automatische alerts als content begint te zakken.

---

## HOOFDFUNCTIONALITEIT 15 — Integraties, Automatisering & Teamworkflow

**2. Korte beschrijving:** Verbindt het portaal met de werkomgeving van het team: Slack-alerts, Zapier/Make-webhooks, automatische e-mailrapportages, Google Drive- en Notion-koppelingen, een redactiekalender met goedkeurworkflow en granulaire gebruikersrechten.

**3. Doel:** SEO verankeren in de dagelijkse operatie: signalen komen naar het team toe (in plaats van andersom) en contentproductie verloopt via een gecontroleerd proces.

**4. Hoe de gebruiker het gebruikt:** In de integratiehub webhooks/API-keys instellen per kanaal; rapportagefrequentie kiezen (dagelijks 9:00, wekelijks maandag, maandelijks). In de Content Calendar: content inplannen, toewijzen aan teamleden met deadline en goedkeurder; goedkeuren/afwijzen vanuit de wachtrij; content-gaps zien op basis van de gewenste publicatiefrequentie. Rollen bepalen wie wat mag (instellingen, AI-features, bulkacties, metabewerking).

**5. Input:** Webhook-URL's/API-keys (Slack, Zapier/Make, Notion, Google Drive); rapportage-e-mail + frequentie; workflowtoewijzingen; publicatiefrequentie-doel.

**6. Output:** Realtime notificaties bij rankwijzigingen, publicaties en scoreveranderingen; periodieke rapporten per mail/Drive; visuele kalender met statuskleuren; goedkeuringswachtrij; gap-analyse ("verwachte publicatiedagen zonder content"); optimale publicatietijdstip-suggesties.

**7. Subfunctionaliteiten:** Drie event-hooks (`rank_change`, `content_published`, `seo_score_change`); drie rapportagecrons; workflowstatussen (draft, in_progress, pending_review, approved, rejected); Slack/e-mailnotificaties bij toewijzing en goedkeuring; capability-systeem met 9 rechten over 4 rollen.

**8. Samenhang:** De kalender plant content uit Ideas en Topic Clusters; alerts komen uit Rank Tracker en Content Decay; rapporten bundelen dashboarddata; rechten gelden over alle modules heen.

**9. Klantwaarde:** SEO wordt teamwerk in plaats van eenmansactie; managers houden grip via goedkeuring; niemand hoeft het portaal te bewaken — het portaal meldt zichzelf.

**10. Commerciële boodschap:** *"SEO die zich meldt in jouw workflow."* — Alerts in Slack, rapporten in je inbox, en een redactiekalender met goedkeuring — zodat het hele team in hetzelfde ritme werkt.

---

## HOOFDFUNCTIONALITEIT 16 — SaaS-Platform, White-label & Commercieel Beheer

**2. Korte beschrijving:** De Fyndable-machinekamer: multi-tenant licentie- en abonnementsbeheer, AI-/SERP-API-gateway met kostenbewaking, betalingsverwerking (Stripe & Mollie) en volledige white-label-mogelijkheden voor bureaus en resellers.

**3. Doel:** Het platform schaalbaar en winstgevend exploiteren: elke klant geïsoleerd, elk verbruik gemeten, elke feature stuurbaar — en partners in staat stellen het product onder eigen merk te verkopen.

**4. Hoe de gebruiker (beheerder/partner) het gebruikt:** De Fyndable-beheerder genereert licenties (type: trial/paid/lifetime; tier: Starter t/m Agency), beheert tenants, ziet verbruiks- en kostenrapportages en stelt per licentie feature-overrides in ("Manage Features": features aan/uit per individuele klant). Betalingen lopen automatisch via Stripe of Mollie (iDEAL/Bancontact/SEPA) met webhook-gestuurde tierwijzigingen en suspensies. Bureaus richten white-label branding in (logo, kleuren, bedrijfsnaam, support-e-mail) die automatisch naar de client-plugins van hun klanten synchroniseert, plus client portal en teambeheer.

**5. Input:** Beheerder: AI-/SERP-provider-keys, tierlimieten en -prijzen, betalingsconfiguratie. Partner: brandinggegevens. Klant: alleen de licentiesleutel.

**6. Output:** Werkende licenties met automatische feature-ontgrendeling; verbruiks- en kostendashboards per tenant; abonnementsfacturatie; white-labeled klantomgevingen; per-licentie maatwerkbundels.

**7. Subfunctionaliteiten:**
- Vier databasetabellen: tenants, tenant-settings, tenant-usage (per maand: API-calls, kosten, SERP-requests, gegenereerde content, getrackte keywords), license-keys
- API Gateway met ingebouwde kostprijsberekening per AI-model en SERP-provider, plus dubbele limieten (calls én kosten) per tier
- Feature-togglesysteem: 45+ features in 5 categorieën met tier-defaults en per-licentie-overrides (extra feature aanzetten voor een Starter-klant, of een dure feature tijdelijk uitzetten)
- Tiers met prijsstelling: Starter €19, Professional €49, Business €99, Agency €199 per maand (configureerbaar)
- Stripe- en Mollie-webhooks: upgrade/downgrade/opzegging → automatische tenantaanpassing
- Licentietypes: trial (14 dagen volledige features), paid, lifetime, test/dev

**8. Samenhang:** Dit platform ís de tegenhanger van Module 1 op elke klantsite: het valideert licenties, proxiet alle AI- en SERP-verkeer, meet verbruik en stuurt branding en featurelijsten terug. Elke module in de client-plugin is hiervan afhankelijk voor AI en data.

**9. Klantwaarde:**
- Voor eindklanten: zorgeloos — geen eigen AI-accounts, voorspelbare kosten, eerlijke limieten per abonnement.
- Voor bureaus/resellers: een compleet SEO-product onder eigen merk, zonder ontwikkelkosten, met centraal beheer van alle klantlicenties en marges via het reseller-model.
- Voor Fyndable zelf: volledige grip op marges (kosten per tenant zichtbaar), flexibele bundels per klant en gecontroleerde uitrol van nieuwe features (beta-toggles per licentie).

**10. Commerciële boodschap:**
- Naar eindklanten: *"Alle kracht van AI-SEO, zonder de complexiteit — voor een vaste prijs per maand."*
- Naar bureaus: *"Jouw merk, jouw klanten, ons platform."* — Lever een volwaardig AI-SEO-portaal onder eigen naam, met client portals, teambeheer en facturatie inbegrepen.

---

# DEEL 3 (STAP 3) — UITWERKING VAN ALLE ONDERLIGGENDE SUBFUNCTIONALITEITEN

Per hoofdfunctionaliteit alle subfunctionaliteiten, functioneel gedetailleerd (zonder code-niveau). Vast format per subfunctionaliteit: **Functie · Gebruikershandeling · Verwerking · Resultaat · Commercieel relevant**.

---

## HF1 — Onboarding, Licentie & Connectiviteit

### 1.1 Licentie-activatie
- **Functie:** Verbindt de klantsite met het Fyndable-platform via één sleutel.
- **Gebruikershandeling:** Dashboard-URL + licentiesleutel invoeren, op "Activate License" klikken.
- **Verwerking:** De sleutel wordt bij het SaaS-platform gevalideerd; daar wordt een tenant (klantomgeving) aangemaakt en een unieke tenant-key teruggestuurd. Tier, type, limieten en vervaldatum worden lokaal opgeslagen.
- **Resultaat:** Statusmelding "Licentie actief" met tier en vervaldatum; alle bijbehorende menu's verschijnen direct.
- **Commercieel relevant:** De hele onboarding is één veld en één klik — het sterkste "easy to start"-argument in demo's en trials.

### 1.2 Periodieke hervalidatie & offline-fallback
- **Functie:** Houdt de licentiestatus actueel zonder de gebruiker te storen.
- **Gebruikershandeling:** Geen (automatisch); handmatige "Valideer nu"-knop beschikbaar.
- **Verwerking:** Validatieresultaat wordt een uur gecached; een dagelijkse achtergrondtaak hervalideert. Bij een tijdelijk onbereikbaar platform blijft de laatst bekende status gelden.
- **Resultaat:** De plugin werkt stabiel door, ook bij hiccups; misbruik (gedeelde sleutels) wordt toch afgevangen.
- **Commercieel relevant:** Betrouwbaarheidsbelofte: "jouw site is nooit afhankelijk van onze uptime".

### 1.3 Feature-gating per abonnement
- **Functie:** Toont en activeert precies de features die bij het abonnement horen.
- **Gebruikershandeling:** Geen — de menustructuur past zich automatisch aan.
- **Verwerking:** Bij validatie stuurt het platform de featurelijst mee (tier-defaults + eventuele per-licentie-overrides); de plugin checkt elke functie tegen deze lijst.
- **Resultaat:** Een opgeruimde interface zonder "grijze" functies; na een upgrade verschijnen nieuwe features direct, zonder herinstallatie.
- **Commercieel relevant:** Upsell-machine: upgraden is realtime feature-ontgrendeling, en sales kan individuele features als proef aanzetten.

### 1.4 Centrale AI-proxy-client
- **Functie:** Stuurt alle AI-aanvragen van alle modules via het Fyndable-platform.
- **Gebruikershandeling:** Geen — elke "Generate"-knop in het portaal gebruikt dit kanaal.
- **Verwerking:** Aanvraag wordt voorzien van licentie- en tenant-key, getoetst aan het uurlijkse rate-limit en het maandelijkse tegoed, en doorgestuurd; het beschikbare AI-model hangt af van de tier (instap: GPT-3.5-klasse, hogere tiers: krachtigere modellen).
- **Resultaat:** AI werkt overal direct, zonder dat de klant ooit een AI-account of API-key nodig heeft.
- **Commercieel relevant:** "AI inbegrepen" als propositie + tier-differentiatie ("betere modellen in hogere abonnementen") als upgrade-reden.

### 1.5 Health-logging & alertmail
- **Functie:** Bewaakt de gezondheid van alle koppelingen.
- **Gebruikershandeling:** Geen; log inzien kan via instellingen.
- **Verwerking:** Elke API-fout wordt gelogd (laatste 20 events met type, provider, status, tijd); bij een error gaat automatisch een e-mail naar de beheerder.
- **Resultaat:** Problemen worden gemeld vóórdat de klant ze zelf merkt; support kan met het log direct diagnosticeren.
- **Commercieel relevant:** Verlaagt supportkosten en onderbouwt een professionele SLA-belofte.

### 1.6 Instellingenbeheer
- **Functie:** Eén centrale plek voor alle platforminstellingen.
- **Gebruikershandeling:** Settings-pagina: AI-creativiteit (temperature), SSL-verificatie, verbinding, deactivatie.
- **Verwerking:** Instellingen worden centraal opgeslagen en door alle modules gelezen.
- **Resultaat:** Consistent gedrag over 70+ modules zonder versnipperde configuratie.
- **Commercieel relevant:** "Eén instellingenscherm" versus de wirwar van losse plugins.

---

## HF2 — On-page SEO-analyse & Contentscore (TruSEO)

### 2.1 TruSEO-scoreberekening
- **Functie:** Vat de SEO-kwaliteit van een post samen in één score (0–100).
- **Gebruikershandeling:** Geen — de score verschijnt en ververst automatisch in de editor.
- **Verwerking:** Acht gewogen categorieën worden beoordeeld: contentlengte, keyphrase-gebruik, SEO-titel/meta, interne links, uitgaande links, afbeeldingen met alt-tekst, leesbaarheid en URL/H1-structuur.
- **Resultaat:** Kleurgecodeerde scorecirkel (groen ≥80, oranje 50–79, rood <50) met uitklapbare checklist per onderdeel.
- **Commercieel relevant:** Het meest demobare element van het product: iedereen begrijpt "van rood naar groen".

### 2.2 Focus keyphrase-analyse
- **Functie:** Controleert of het gekozen zoekwoord op de juiste plekken wordt gebruikt.
- **Gebruikershandeling:** Focus keyphrase (en optioneel secundaire keyphrases) invoeren in de metabox.
- **Verwerking:** Checkt aanwezigheid in titel, eerste alinea, tussenkoppen, meta-omschrijving en URL, en weegt dit mee in de score.
- **Resultaat:** Concrete checklist-items als "Keyphrase ontbreekt in de inleiding".
- **Commercieel relevant:** Vervangt de basisfunctie waarvoor klanten nu Yoast/RankMath gebruiken — migratiedrempel weg.

### 2.3 SEO-titel & meta-editor met SERP-preview
- **Functie:** Laat zien hoe de post er in Google uit komt te zien, tijdens het typen.
- **Gebruikershandeling:** Titel (max 60 tekens) en omschrijving (max 160 tekens) typen.
- **Verwerking:** Live tekentellers en een gesimuleerde Google-weergave; waarden worden als SEO-meta opgeslagen en sitebreed hergebruikt.
- **Resultaat:** Pixel-realistische preview van het zoekresultaat vóór publicatie.
- **Commercieel relevant:** Visueel, direct begrijpelijk verkoopmoment in elke demo.

### 2.4 Leesbaarheidsanalyse (NL + EN)
- **Functie:** Beoordeelt hoe prettig de tekst leest.
- **Gebruikershandeling:** Geen — draait automatisch mee; taal is instelbaar.
- **Verwerking:** Flesch Reading Ease voor Engels en Flesch-Douma voor Nederlands; daarnaast zinslengte, alinealengte, percentage passieve zinnen, signaalwoorden en te lange zinnen. AI kan gerichte herschrijfsuggesties per probleem geven.
- **Resultaat:** Leesbaarheidsscore met grade (A–D) en een issuelijst ("23% van de zinnen is langer dan 20 woorden").
- **Commercieel relevant:** Echte Nederlandse leesbaarheidsformule — een hard differentiator t.o.v. internationale tools die alleen Engels goed doen.

### 2.5 E-E-A-T-analyse per post
- **Functie:** Scoort content op Google's kwaliteitsdimensies (Experience, Expertise, Authoritativeness, Trust).
- **Gebruikershandeling:** Metabox openen; optioneel "Get AI Recommendations" klikken.
- **Verwerking:** Per dimensie worden signalen gewogen: eigen ervaring (ik-perspectief, voorbeelden, eigen beeld), expertise (auteurscredentials, diepgang, bronnen), autoriteit (auteursbio, gezaghebbende externe links, actualiteit), vertrouwen (bronvermelding, datum, auteursblok, affiliate-dichtheid).
- **Resultaat:** Vier voortgangsbalken + overall-score met de drie belangrijkste verbeterpunten.
- **Commercieel relevant:** E-E-A-T is hét actuele Google-thema; vrijwel geen concurrent maakt dit meetbaar — sterk thought-leadership-argument.

### 2.6 Sitebrede E-E-A-T & auteursprofielen
- **Functie:** Tilt E-E-A-T van post- naar siteniveau en versterkt auteurspagina's.
- **Gebruikershandeling:** Auteurs vullen hun profiel aan: credentials (titel + verstrekker), expertisegebieden, social profielen.
- **Verwerking:** Sitebrede analyse van trust-signalen (HTTPS, privacy-, contact-, over-ons-pagina, auteursbio's) en een scoreboard per auteur (bio-compleetheid, credentials, aantal posts).
- **Resultaat:** Site-E-E-A-T-dashboard met checklist van aanwezige/ontbrekende vertrouwenssignalen.
- **Commercieel relevant:** Maakt "autoriteit opbouwen" concreet en meetbaar — ideaal voor B2B- en YMYL-doelgroepen (financieel, gezondheid, juridisch).

### 2.7 LSI-keywordgenerator met dekkingsmeting
- **Functie:** Levert semantisch verwante termen en meet of ze gebruikt worden.
- **Gebruikershandeling:** "Generate LSI Keywords" klikken; termen aan-/afvinken in de keywordcloud.
- **Verwerking:** AI genereert 10–15 verwante termen bij de focus keyphrase (met niet-AI-fallback); het systeem telt het gebruik van elke term in de content en berekent een dekkingspercentage. Resultaten worden 7 dagen gecached.
- **Resultaat:** Klikbare termencloud met per term gebruikt/ongebruikt en een dekkings-%.
- **Commercieel relevant:** Verbreedt content van "één zoekwoord" naar "het hele onderwerp" — de semantische SEO-belofte concreet gemaakt.

---

## HF3 — Content Optimizer & Content Brief

### 3.1 Topicmodel-generator
- **Functie:** Bouwt het "recept" voor een winnende pagina op basis van de werkelijke top-resultaten.
- **Gebruikershandeling:** Keyword invoeren en op analyseren klikken.
- **Verwerking:** SERP-data wordt via het platform opgehaald; AI destilleert daaruit 30–50 gewogen termen in vier categorieën (kerntermen, ondersteunende termen, entiteiten, te beantwoorden vragen) plus zoekintentie, moeilijkheidsindicatie en aanwezige SERP-features. Het model wordt 7 dagen bewaard.
- **Resultaat:** Een concreet termen- en structuurplan: wat moet erin, hoe vaak, en welke vragen beantwoord moeten worden.
- **Commercieel relevant:** Dit is de "SurferSEO/MarketMuse-functie" — de duurste categorie losse tools, hier inbegrepen.

### 3.2 Real-time contentscoring met term-heatmap
- **Functie:** Meet hoe volledig de eigen content het topicmodel dekt.
- **Gebruikershandeling:** Content plakken of schrijven en op "Score" klikken.
- **Verwerking:** Per term wordt het werkelijke gebruik vergeleken met het aanbevolen aantal (status: missing/low/good/overused); daarnaast structuurscore op woordaantal, koppen, afbeeldingen en alinea's. Weging: 70% termdekking, 30% structuur.
- **Resultaat:** Score 0–100 met grade A–F en een kleuren-heatmap per term, plus telling van gedekte/ontbrekende/overgebruikte termen.
- **Commercieel relevant:** Maakt optimalisatie een meetbaar spel ("haal de A") — sterk voor retentie en dagelijks gebruik.

### 3.3 AI-terminpassing
- **Functie:** Helpt ontbrekende termen natuurlijk in de tekst te verwerken.
- **Gebruikershandeling:** Bij een ontbrekende term op "suggest" klikken.
- **Verwerking:** AI schrijft een natuurlijke zin of passage waarin de term past binnen de bestaande context.
- **Resultaat:** Kant-en-klare tekstsuggesties die direct overgenomen kunnen worden — geen keyword-stuffing.
- **Commercieel relevant:** Neemt de laatste handmatige stap weg: van "weten wat mist" naar "het staat erin" in één klik.

### 3.4 Content Brief-generator
- **Functie:** Maakt een compleet redactioneel briefingdocument per keyword.
- **Gebruikershandeling:** Keyword + land invoeren.
- **Verwerking:** Top-10-analyse → aanbevolen woordaantal (op basis van concurrenten), voorgestelde H2/H3-structuur, FAQ-vragen, entiteiten en LSI-termen, zoekintentie (informational/commercial/transactional), moeilijkheid (easy/medium/hard) en een unieke invalshoek; plus interne linkkansen binnen de eigen site. Werkt ook met AI-fallback als SERP-data ontbreekt; briefs worden 7 dagen bewaard.
- **Resultaat:** Een uitdeelbare brief waarmee elke (externe) tekstschrijver direct aan de slag kan.
- **Commercieel relevant:** Bespaart het uurwerk van een SEO-specialist per artikel; perfect verhaal richting bureaus en redacties.

### 3.5 Scoring tegen de brief
- **Functie:** Toetst geschreven content aan de eigen briefing.
- **Gebruikershandeling:** Content indienen bij een bestaande brief.
- **Verwerking:** Zes metrieken worden vergeleken: woordaantal, keywordgebruik, heading-dekking, entiteit-dekking, LSI-dekking en vraag-dekking.
- **Resultaat:** Per metriek een voldaan/niet-voldaan-status — objectieve acceptatiecriteria voor opgeleverde content.
- **Commercieel relevant:** Kwaliteitsborging voor teams en uitbesteding: "wij keuren content op data, niet op gevoel".

### 3.6 Intentie- & moeilijkheidsdetectie
- **Functie:** Bepaalt wat de zoeker wil en hoe zwaar de concurrentie is.
- **Gebruikershandeling:** Geen — onderdeel van elke analyse.
- **Verwerking:** Zoekintentie wordt afgeleid uit de aard van de top-resultaten; moeilijkheid uit het aandeel autoriteitsdomeinen in de SERP.
- **Resultaat:** Labels als "commercieel, moeilijkheid: medium" bij elk keyword/brief.
- **Commercieel relevant:** Voorkomt de klassieke fout (blog schrijven voor een koopintentie-keyword) — adviseurswaarde in het product ingebakken.

---

## HF4 — Keyword Research & Management

### 4.1 Keyworddatabase & beheer
- **Functie:** Centrale opslag en organisatie van alle keywords.
- **Gebruikershandeling:** Keywords toevoegen/bewerken/verwijderen, filteren op moeilijkheid en intentie, sorteren, bulk-verwijderen.
- **Verwerking:** Eigen databasetabel met per keyword zoekvolume, moeilijkheid (laag/middel/hoog), CPC, intentie, rankstatus, positiewijziging en clusterkoppeling.
- **Resultaat:** Eén doorzoekbaar keyword-werkstation in plaats van losse spreadsheets.
- **Commercieel relevant:** "Je hele keywordstrategie op één plek" — vervangt spreadsheetchaos.

### 4.2 AI-keywordgeneratie vanuit onderwerp
- **Functie:** Vertaalt een product/dienst/branche naar concrete zoektermen.
- **Gebruikershandeling:** Onderwerp invoeren, taal kiezen (NL/EN/DE/FR/ES), genereren.
- **Verwerking:** AI genereert relevante keywords met bijbehorende metrics en schrijft ze direct in de database; gekoppelde contentideeën kunnen meteen worden aangemaakt.
- **Resultaat:** Binnen een minuut een gevulde, meertalige keywordlijst voor de eigen markt.
- **Commercieel relevant:** Dit ís de kerninput van het Fyndable-verhaal: "vertel wat je doet, wij vertellen waar men op zoekt".

### 4.3 Keyword Explorer (expansie)
- **Functie:** Breidt één seed-keyword uit naar het omliggende zoeklandschap.
- **Gebruikershandeling:** Seed-keyword invoeren.
- **Verwerking:** De titels van de actuele zoekresultaten worden geanalyseerd op veelvoorkomende woordcombinaties; de top-25 gerelateerde termen met frequentiescores komt terug (3 dagen cache).
- **Resultaat:** Lijst gerelateerde keywords gebaseerd op wat nú daadwerkelijk rankt — niet op een statische database.
- **Commercieel relevant:** "Live uit Google" als verhaal tegenover verouderde keyword-databases.

### 4.4 Automatische keyword-clustering
- **Functie:** Groepeert keywords die op dezelfde pagina thuishoren.
- **Gebruikershandeling:** Keywordset selecteren en clusteren.
- **Verwerking:** Overlap-analyse (similariteitsmeting) groepeert termen boven een drempelwaarde; clusters worden bewaard.
- **Resultaat:** Logische keywordgroepen → één pagina per cluster in plaats van tien kannibaliserende pagina's.
- **Commercieel relevant:** Voorkomt keyword-kannibalisatie — een herkenbaar pijnpunt bij elke prospect met een bestaand blog.

### 4.5 Gepersonaliseerde Keyword Difficulty
- **Functie:** Berekent hoe moeilijk een keyword is voor déze specifieke site.
- **Gebruikershandeling:** Keyword (of batch tot 20) laten analyseren.
- **Verwerking:** Drie lagen worden gecombineerd: (a) generieke moeilijkheid via AI-inschatting (7 dagen cache); (b) topical authority van de eigen site (hoeveel gerelateerde content, woordvolume, SEO-scores); (c) contentvoorraad (aanwezige pillar pages van 2500+ woorden, clusters van 5+ gerelateerde posts, interne links). Authority en voorraad verlagen de persoonlijke moeilijkheid.
- **Resultaat:** Twee getallen naast elkaar ("generiek 68, voor jou 51") plus niveau (easy → very hard), je voordeel, een aanbeveling en de geschatte inspanning.
- **Commercieel relevant:** Uniek t.o.v. vrijwel alle concurrenten — "wij vertellen welke keywords JIJ kunt winnen" is een onderscheidende headline.

### 4.6 Import & export (CSV)
- **Functie:** Verbindt Fyndable met bestaande keyword-werkstromen.
- **Gebruikershandeling:** CSV uploaden of exporteren.
- **Verwerking:** Bulkverwerking van keywordlijsten in en uit de database.
- **Resultaat:** Bestaande research (uit andere tools of van bureaus) direct bruikbaar; data nooit opgesloten.
- **Commercieel relevant:** Verlaagt de overstapdrempel ("neem je bestaande keywords gewoon mee") en neutraliseert lock-in-bezwaren.

---

## HF5 — Rank Tracking & SERP-monitoring

### 5.1 Dagelijkse automatische positiecheck
- **Functie:** Meet elke dag de Google-positie van elk gevolgd keyword.
- **Gebruikershandeling:** Eenmalig keyword + doel-URL + land toevoegen.
- **Verwerking:** Een dagelijkse achtergrondtaak vraagt per keyword de SERP-positie op via het platform; huidige, vorige en beste positie worden bijgewerkt en elke meting wordt in de historie opgeslagen.
- **Resultaat:** Altijd actuele posities zonder ooit handmatig te googelen.
- **Commercieel relevant:** Vervangt losse rank-tracker-abonnementen; "automatisch, dagelijks, inbegrepen".

### 5.2 Positiehistorie & trendweergave
- **Functie:** Maakt de ontwikkeling per keyword zichtbaar.
- **Gebruikershandeling:** Op een keyword klikken voor de historiegrafiek.
- **Verwerking:** Tot 90 dagen aan dagelijkse metingen wordt als grafiek gerenderd; veranderingen worden met ▲/▼ in de tabel getoond.
- **Resultaat:** In één oogopslag zien of een keyword stijgt, daalt of stabiel is.
- **Commercieel relevant:** De trendgrafiek is het bewijsplaatje voor rapportages en klantgesprekken.

### 5.3 Directe hercheck ("Check All Now")
- **Functie:** On-demand controle buiten het dagelijkse ritme.
- **Gebruikershandeling:** Eén klik op "Check All Now".
- **Verwerking:** Alle gevolgde keywords worden direct opnieuw gemeten.
- **Resultaat:** Actuele cijfers op het moment dat het ertoe doet (na een grote wijziging of Google-update).
- **Commercieel relevant:** Gevoel van controle — belangrijk voor de doe-het-zelf-doelgroep.

### 5.4 SERP-feature-doelen per post
- **Functie:** Richt een post op extra SERP-posities (featured snippet, People Also Ask, image/video pack).
- **Gebruikershandeling:** In de editor-metabox doelfeatures aanvinken en de checklist afwerken.
- **Verwerking:** Zeven criteria worden gecontroleerd (snippet-tekst aanwezig, schema, 3+ PAA-vragen, lijsten/tabellen, afbeeldingen, video, 1500+ woorden) en vertaald naar een optimalisatiescore.
- **Resultaat:** Per post een feature-score (0–100%) en een dashboard met de status van alle posts.
- **Commercieel relevant:** "Win posities bóven nummer 1" — een verrassend verhaal dat verder gaat dan klassiek ranken.

### 5.5 AI-snippet- & PAA-generator
- **Functie:** Schrijft de content die features daadwerkelijk wint.
- **Gebruikershandeling:** "Generate" klikken voor snippet-tekst of PAA-vraag/antwoordparen.
- **Verwerking:** AI genereert een featured-snippet-antwoord van 40–60 woorden en gestructureerde Q&A's op basis van de postcontent; een preview toont hoe het snippet eruit zou zien.
- **Resultaat:** Direct bruikbare snippetcontent met visuele SERP-preview.
- **Commercieel relevant:** Concreet en demobaar: input → AI → "zo ziet jouw featured snippet eruit".

### 5.6 SERP-snapshots
- **Functie:** Bewaart momentopnames van zoekresultaten voor vergelijking over tijd.
- **Gebruikershandeling:** Geen — gebeurt bij analyses automatisch.
- **Verwerking:** Volledige resultaatsets worden per keyword met tijdstempel opgeslagen.
- **Resultaat:** Terugkijken hoe de SERP er vroeger uitzag: wie erbij kwam, wie verdween.
- **Commercieel relevant:** Onderbouwt analyses ("sinds maart staan er 3 nieuwe concurrenten") met bewaard bewijs.

---

## HF6 — Concurrentie- & Backlinkanalyse

### 6.1 SERP-concurrentprofielen
- **Functie:** Ontleedt de pagina's die voor een keyword in de top staan.
- **Gebruikershandeling:** Keyword (+ land) invoeren in SERP Competitor.
- **Verwerking:** De top-20 resultaten wordt opgehaald; AI profileert per concurrent het contentformaat, woordaantal, sterktes/zwaktes en de unieke invalshoek, plus gedeelde patronen (gemiddelde lengte, kopgebruik, FAQ-/tabel-/video-gebruik). Resultaten worden 3 dagen bewaard.
- **Resultaat:** Overzichtskaarten ("gemiddeld 2.100 woorden, 8 koppen, 70% gebruikt FAQ") en per-concurrent-profielen.
- **Commercieel relevant:** Beantwoordt dé klantvraag — "waarom staan zij boven mij?" — met data in plaats van een mening.

### 6.2 Topic-heatmap
- **Functie:** Laat zien welke onderwerpen de top-10 wel behandelt en jij niet.
- **Gebruikershandeling:** Tab "Heatmap" openen na de analyse.
- **Verwerking:** Per topic wordt dekking bij concurrenten en belangrijkheid bepaald.
- **Resultaat:** Visuele matrix: groen = jij dekt het, rood = gat met hoge prioriteit.
- **Commercieel relevant:** Het meest visuele "aha-moment" voor prospects: hun content-gaten in één beeld.

### 6.3 Contentvergelijking met competitive score
- **Functie:** Zet de eigen pagina naast het top-10-gemiddelde.
- **Gebruikershandeling:** Eigen content plakken en op "Compare" klikken.
- **Verwerking:** Woordaantal, kopstructuur en topicdekking worden vergeleken; uitkomst is een competitive score (0–100) met dekkingsratio.
- **Resultaat:** "Jij dekt 6 van de 11 kerntopics; concurrentgemiddelde is 9" — direct werkbaar.
- **Commercieel relevant:** Maakt verbeterpotentieel kwantificeerbaar — sterke basis voor audits en offertes.

### 6.4 Diepe gap-analyse
- **Functie:** Vertaalt de vergelijking naar een concreet verbeterplan.
- **Gebruikershandeling:** "Deep Gap Analysis" klikken.
- **Verwerking:** AI benoemt ontbrekende topics (met belang), eigen sterktes, unieke kansen, structuursuggesties en het aanbevolen aantal extra woorden.
- **Resultaat:** Een prioriteitenlijst die direct in de Content Brief/Writer kan worden doorgezet.
- **Commercieel relevant:** Sluit de cirkel van analyse naar actie — geen "rapport in de la".

### 6.5 Competitor Research-dossier
- **Functie:** Bouwt een doorlopend dossier per concurrent-domein.
- **Gebruikershandeling:** Concurrent-domein toevoegen; tabbladen doorlopen.
- **Verwerking:** Vier analyses per concurrent: (a) keywordstrategie + thema's, (b) contentkalender via sitemap-analyse (publicatiefrequentie), (c) AI-content-detectie (welk deel van hun content is machinaal geschreven, met confidence en indicatoren), (d) win/loss per gedeeld keyword (jouw positie vs. die van hen, met win-rate) en analyse van hun advertentieteksten. Resultaten worden per dag gecached.
- **Resultaat:** Een levend concurrentiedossier met strategische duiding per tab.
- **Commercieel relevant:** AI-content-detectie bij concurrenten is een uniek, nieuwsgierig-makend verkooppunt ("zie hoeveel van hun content AI is").

### 6.6 Domeinautoriteit & backlinkprofiel
- **Functie:** Meet de linkkracht van het eigen domein.
- **Gebruikershandeling:** Backlink-dashboard openen (met Ahrefs- of Semrush-key ingesteld).
- **Verwerking:** Domain rating, verwijzende domeinen, totaal backlinks, organisch verkeer en de verdeling dofollow/nofollow plus top-ankerteksten worden opgehaald en een dag gecached.
- **Resultaat:** Autoriteits-dashboard met de kerncijfers van het linkprofiel.
- **Commercieel relevant:** Brengt de "autoriteitskant" van SEO in hetzelfde portaal — completeert het alles-in-één-verhaal.

### 6.7 Toxische-linkdetectie
- **Functie:** Spoort schadelijke backlinks op.
- **Gebruikershandeling:** "Check Toxic Links" klikken.
- **Verwerking:** Verdachte links worden gescoord op toxiciteit met reden.
- **Resultaat:** Lijst risicovolle links als input voor een disavow-actie.
- **Commercieel relevant:** Risicobeheersing als verkoopargument: "wij waarschuwen vóór een Google-penalty".

### 6.8 Broken-backlink-prospecting & ankeranalyse
- **Functie:** Vindt snelle linkbuilding-kansen en bewaakt het ankerprofiel.
- **Gebruikershandeling:** Concurrent-domein invoeren; tabellen doorlopen; "Generate Outreach" voor benaderteksten.
- **Verwerking:** Dode links naar concurrenten worden gedetecteerd (404-check) en gescoord op kans (autoriteit + ankerrelevantie + dofollow); concurrentlinkbronnen worden geprioriteerd (high/medium/low); de eigen ankerverdeling (branded/exact/partial/generic) wordt geclassificeerd met risiconiveau, plus AI-aanbevelingen.
- **Resultaat:** Een geprioriteerde outreach-lijst en een ankerprofiel-gezondheidscheck.
- **Commercieel relevant:** Maakt linkbuilding — normaal specialistenwerk — uitvoerbaar voor de eigen marketeer.

---

## HF7 — AI-Contentcreatie

### 7.1 Artikelgeneratie-pijplijn (AI Content Writer)
- **Functie:** Schrijft een compleet SEO-artikel van titel tot conclusie.
- **Gebruikershandeling:** Keyword, toon en doelwoordaantal invoeren (optioneel eigen outline of een Content Brief koppelen); "Generate" en optioneel "maak WordPress-concept" aanvinken.
- **Verwerking:** Het artikel wordt in stappen opgebouwd: titel (indien leeg), inleiding, secties per kop, FAQ op basis van aanbevolen vragen, conclusie en een meta-omschrijving (max 160 tekens); alles in nette HTML.
- **Resultaat:** Publicatieklaar concept in WordPress met directe bewerkingslink en woordtelling.
- **Commercieel relevant:** Het vlaggenschip van de Business-tier: "van zoekwoord naar artikel in minuten".

### 7.2 Sectie-generatie & herschrijven per blok
- **Functie:** Genereert of vernieuwt één artikeldeel in plaats van alles.
- **Gebruikershandeling:** Een sectie selecteren en laten (her)genereren.
- **Verwerking:** AI schrijft de sectie met de rest van het artikel als context, zodat stijl en lijn behouden blijven.
- **Resultaat:** Gerichte verbetering zonder het hele artikel te verliezen.
- **Commercieel relevant:** Adresseert de grootste AI-scepsis ("ik wil controle houden") — de mens blijft regisseur.

### 7.3 Contentgeneratie in de editor (Simple Generator)
- **Functie:** Laagdrempelige generatie direct in de post-editor.
- **Gebruikershandeling:** In de metabox onderwerp + context + toon + woordaantal (100–3000) invoeren; preview bekijken; invoegen of toevoegen.
- **Verwerking:** AI genereert HTML-content; werkt met zowel Gutenberg als de klassieke editor; keuze tussen vervangen of toevoegen.
- **Resultaat:** Content verschijnt na akkoord direct in de editor.
- **Commercieel relevant:** De instap-AI-ervaring voor niet-specialisten — drempelloos eerste succesmoment.

### 7.4 Content Rewriter (7 modi)
- **Functie:** Transformeert bestaande tekst doelgericht.
- **Gebruikershandeling:** Content + modus kiezen: verbeteren, SEO-optimaliseren (met keyword), leesbaarder maken, uitbreiden, inkorten, parafraseren of toon wijzigen.
- **Verwerking:** Modus-specifieke AI-bewerking met behoud van de HTML-structuur; toont oud vs. nieuw woordaantal.
- **Resultaat:** Herschreven versie, klaar om te vervangen.
- **Commercieel relevant:** Activeert de bestaande contentbibliotheek — "je oude posts zijn je goudmijn".

### 7.5 AI Repurposer (7 formaten)
- **Functie:** Zet één blogpost om naar meerdere kanalen.
- **Gebruikershandeling:** In de metabox een formaat kiezen: Twitter/X-post, LinkedIn-post, Facebook-post, nieuwsbrief, key points (TL;DR), Twitter-thread of Instagram-caption; kopiëren met één klik.
- **Verwerking:** AI herschrijft de kern van de post naar de conventies en lengtes van elk platform (incl. hashtags/emoji/CTA waar passend).
- **Resultaat:** Direct plakbare social/e-mailcontent per kanaal.
- **Commercieel relevant:** Verbreedt de waarde van SEO-content naar het hele marketingkanaal — "schrijf één keer, publiceer zeven keer".

### 7.6 Ideeënbeheer (Ideas)
- **Functie:** Genereert en beheert de contentpijplijn.
- **Gebruikershandeling:** Onderwerp invoeren, 5–20 ideeën genereren (taal naar keuze); ideeën filteren, handmatig aanvullen en converteren naar concept of ingeplande post.
- **Verwerking:** AI levert gestructureerde ideeën (titel, omschrijving, keywords) met Nederlandse fallback; statussen (actief/geconverteerd/gepland) worden bijgehouden; conversie kan direct de Content Writer aanroepen.
- **Resultaat:** Een gevulde, beheerde ideeënlijst die nooit meer "waar schrijven we over?" oplevert.
- **Commercieel relevant:** Lost het echte startersprobleem op: niet schrijven is moeilijk, maar wéten wat je moet schrijven.

### 7.7 Topic Cluster-generator met site-audit en bulkproductie
- **Functie:** Ontwerpt en bouwt complete topical-authority-structuren.
- **Gebruikershandeling:** Kernonderwerp + diepte (standaard 10–15 of diep 20–30 clusters) + taal kiezen; daarna optioneel "audit bestaande content" en "genereer geselecteerde pagina's in bulk".
- **Verwerking:** AI ontwerpt een pillarpagina (±3000 woorden spec) met clusters van hubpagina's en ondersteunende pagina's (elk met keyword, woordaantal, contenttype, prioriteit), een interne linkstrategie en een weekplanning. De audit matcht bestaande posts op relevantie zodat alleen ontbrekende pagina's worden gegenereerd; bulkproductie loopt met voortgangsbalk en kostenindicatie.
- **Resultaat:** Een complete, deels al gevulde contentarchitectuur in plaats van losse artikelen.
- **Commercieel relevant:** "Wij bouwen geen blogposts, wij bouwen autoriteit" — het strategische verhaal dat hogere prijzen rechtvaardigt.

### 7.8 Originaliteitscontrole
- **Functie:** Bewaakt de uniciteit en menselijkheid van content.
- **Gebruikershandeling:** "Check Originality" klikken (min. 50 woorden).
- **Verwerking:** Vier heuristieken (interne duplicaatdetectie binnen de eigen site, zinsvariatie, herkenning van 40+ typische AI-frasen, woordenschatrijkdom) gecombineerd met een AI-beoordeling (weging 40/60).
- **Resultaat:** Originaliteitsscore 0–100 met grade en gemarkeerde verdachte passages.
- **Commercieel relevant:** Neutraliseert hét bezwaar tegen AI-content ("straft Google dit niet af?") met een ingebouwde kwaliteitscheck.

### 7.9 Created Posts-beheer
- **Functie:** Houdt overzicht en regie over alle AI-gegenereerde content.
- **Gebruikershandeling:** Filteren op status, reviewstatus, cluster, taal, datum en woordaantal; bulk-acties uitvoeren (status wijzigen, reviewen, verwijderen).
- **Verwerking:** Elke gegenereerde post is gemarkeerd en gekoppeld aan zijn cluster en statussen (gereed/in behandeling/mislukt; gereviewd/te reviewen).
- **Resultaat:** Een controlecentrum waarin niets ongezien gepubliceerd wordt.
- **Commercieel relevant:** Governance-verhaal voor teams en bureaus: AI op schaal, maar met menselijke eindcontrole.

---

## HF8 — AI-Beeldgeneratie & Media-SEO

### 8.1 AI-beeldprompt uit content
- **Functie:** Vertaalt het artikel automatisch naar een visuele opdracht.
- **Gebruikershandeling:** Geen (automatisch) of eigen context meegeven.
- **Verwerking:** AI leest de postcontent en formuleert een passende beeldbeschrijving in de gekozen stijl.
- **Resultaat:** Beelden die inhoudelijk bij het artikel passen in plaats van generieke stock.
- **Commercieel relevant:** "Unieke beelden per artikel" zonder briefing of designer.

### 8.2 Featured- & social-beeldgeneratie
- **Functie:** Maakt en koppelt de benodigde beelden per post.
- **Gebruikershandeling:** Stijl kiezen (photorealistic/illustration/abstract/minimalist/3D) en genereren.
- **Verwerking:** Via DALL-E of Stability AI worden featured image, OG-beeld (1200×630) en Twitter-beeld (1200×600) gegenereerd, gedownload en als WordPress-media gekoppeld.
- **Resultaat:** Post volledig visueel aangekleed, ook voor social shares.
- **Commercieel relevant:** Bespaart stockfoto-abonnementen én tijd — tastbare kostenbesparing in de pitch.

### 8.3 Bulk-beeldgeneratie
- **Functie:** Vult beeldachterstanden sitebreed aan.
- **Gebruikershandeling:** Doelgroep kiezen (posts zonder beeld / alle / recente), beeldtypes selecteren, starten.
- **Verwerking:** Batchverwerking met voortgangsbalk; statistieken (gegenereerd/ontbrekend) worden bijgehouden.
- **Resultaat:** Honderden posts in één run van beeld voorzien.
- **Commercieel relevant:** Klassiek "achterstallig onderhoud"-aanbod voor sites met veel oude content.

### 8.4 AI-alt-tekstgenerator (auto + bulk)
- **Functie:** Geeft elke afbeelding een beschrijvende alt-tekst.
- **Gebruikershandeling:** Automatisch bij upload (instelbaar), per afbeelding, of in bulk voor de hele bibliotheek.
- **Verwerking:** AI genereert een specifieke alt-tekst (max 125 tekens, zonder "afbeelding van…") op basis van bestandsnaam, titel en context; dekkingsstatistieken worden bijgehouden.
- **Resultaat:** Volledige alt-dekking met percentage in beeld.
- **Commercieel relevant:** Raakt drie thema's tegelijk: image-SEO, toegankelijkheid (digitoegankelijkheidswetgeving) en professionaliteit.

### 8.5 Video-SEO met AI-transcript
- **Functie:** Maakt video's vindbaar in Google.
- **Gebruikershandeling:** Video-URL, duur en uploaddatum invoeren; optioneel "Generate Transcript" klikken.
- **Verwerking:** VideoObject-schema wordt gegenereerd en gevalideerd (checklist van verplichte velden); AI schrijft een transcript op basis van de postcontext.
- **Resultaat:** Video's met volledige metadata en transcript; dashboard toont welke video's nog optimalisatie nodig hebben.
- **Commercieel relevant:** Videocontent is voor de meeste mkb'ers volledig onvindbaar — onontgonnen winst die makkelijk verkoopt.

---

## HF9 — Meta Tags & Social Presentatie

### 9.1 Open Graph-/Twitter-output met fallback-keten
- **Functie:** Zorgt dat elke pagina er overal goed uitziet, ook zonder handwerk.
- **Gebruikershandeling:** Geen (automatisch); overrides per veld mogelijk.
- **Verwerking:** Per pagina wordt de beste beschikbare bron gekozen: handmatige override → SEO-meta → featured image → site-default; contextspecifieke varianten voor home, archieven en auteurspagina's; artikelen krijgen extra metadata (datum, auteur, sectie).
- **Resultaat:** Nooit een kale link op Facebook, LinkedIn of X.
- **Commercieel relevant:** "Zero-config maar altijd netjes" — kwaliteit zonder inspanning als kernbelofte.

### 9.2 Social-previews per platform
- **Functie:** Toont vooraf hoe een share eruitziet.
- **Gebruikershandeling:** Tabblad Facebook of Twitter openen in de metabox.
- **Verwerking:** Live preview met beeld, domein, titel en omschrijving plus tekentellers per veld.
- **Resultaat:** Afgekapte teksten of verkeerde beelden worden vóór publicatie zichtbaar.
- **Commercieel relevant:** Klein maar overtuigend demo-element; voorkomt publieke slordigheden.

### 9.3 AI-generatie van OG-tags
- **Functie:** Schrijft social-titels en -omschrijvingen automatisch.
- **Gebruikershandeling:** "AI Generate OG Tags" klikken.
- **Verwerking:** AI genereert op basis van titel, keyword en contentbegin een set OG- en Twitter-velden.
- **Resultaat:** Ingevulde, platform-geoptimaliseerde velden in seconden.
- **Commercieel relevant:** Het verschil tussen "verplicht invulwerk" en "automatisch goed".

### 9.4 Smart Tags (AI-posttags)
- **Functie:** Voorziet posts van relevante tags voor structuur en vindbaarheid.
- **Gebruikershandeling:** "Generate Smart Tags" klikken en suggesties aanklikken — of auto-tagging bij publicatie aanzetten.
- **Verwerking:** AI stelt 5–10 tags voor op basis van titel en contentbegin (met frequentie-gebaseerde fallback); bij auto-modus worden de beste 5 toegepast tenzij er al tags zijn.
- **Resultaat:** Consequent getagde content zonder nadenken.
- **Commercieel relevant:** Klein gemak dat optelt — onderdeel van het "alles automatisch netjes"-verhaal.

### 9.5 Bulk AI Optimizer (meta op schaal)
- **Functie:** Repareert ontbrekende meta over de hele site.
- **Gebruikershandeling:** Scan starten → lijst met posts zonder titel/omschrijving/OG/alt → selectie → batch-generatie.
- **Verwerking:** Sitebrede scan markeert de status per post (✓/!) in het postoverzicht; AI genereert ontbrekende velden in batches met voortgang.
- **Resultaat:** Een complete meta-dekking, ook voor honderden legacy-posts.
- **Commercieel relevant:** Het ideale eerste-week-resultaat voor nieuwe klanten: meetbaar effect zonder één woord nieuwe content.

---

## HF10 — Structured Data & Rich Snippets

### 10.1 Automatische schema-detectie & -output
- **Functie:** Kiest en plaatst zelf het juiste schematype per pagina.
- **Gebruikershandeling:** Geen; per post desgewenst type kiezen of eigen JSON-LD invoeren.
- **Verwerking:** Contentkenmerken bepalen het type (product → Product, FAQ-blokken → FAQPage, stappen → HowTo); WebSite-, Organization- en BreadcrumbList-schema worden altijd meegegeven.
- **Resultaat:** Valide structured data op elke pagina zonder configuratie.
- **Commercieel relevant:** Developer-werk geautomatiseerd — directe besparing op technische uren.

### 10.2 Schema-bibliotheek (17+ types)
- **Functie:** Dekt vrijwel elk contenttype af.
- **Gebruikershandeling:** Type kiezen in de metabox waar gewenst.
- **Verwerking:** Ondersteund: Article, BlogPosting, NewsArticle, Product, LocalBusiness, Organization, Person, FAQPage, HowTo, Recipe, Event, Course, SoftwareApplication, Review, AggregateRating, BreadcrumbList, WebSite (+ VideoObject en CollectionPage via aanpalende modules).
- **Resultaat:** Recepten, events, cursussen, reviews — alles kan rich snippets krijgen.
- **Commercieel relevant:** Breedte als koopargument voor niches (food, events, opleiders, software).

### 10.3 FAQ-extractie uit bestaande content
- **Functie:** Herkent vraag-antwoordstructuren en zet ze om in FAQ-schema.
- **Gebruikershandeling:** "Auto-extract" klikken in de FAQ-metabox.
- **Verwerking:** Gutenberg-blokken, Q&A-patronen en kop+alinea-structuren worden herkend (max 20 per post) en als bewerkbare FAQ-items opgeslagen.
- **Resultaat:** Bestaande FAQ's verschijnen als uitklapbare vragen in Google.
- **Commercieel relevant:** Onbenut bezit activeren: "je hebt de FAQ al — wij maken hem zichtbaar in Google".

### 10.4 AI-FAQ-generatie
- **Functie:** Maakt FAQ's waar ze nog niet bestaan.
- **Gebruikershandeling:** Aantal kiezen (1–10), genereren, optioneel direct in de content invoegen.
- **Verwerking:** AI formuleert vragen + antwoorden passend bij de postcontent; een dashboard toont sitebrede FAQ-statistieken.
- **Resultaat:** Complete FAQ-secties inclusief schema, in één handeling.
- **Commercieel relevant:** Dubbele winst in één klik: betere content én extra SERP-ruimte.

### 10.5 Local Business-schema
- **Functie:** Maakt lokale bedrijven correct leesbaar voor Google.
- **Gebruikershandeling:** Eenmalig bedrijfsgegevens invullen: type (restaurant, winkel, praktijk…), adres, coördinaten, telefoon, openingstijden ("Mo-Fr 09:00-17:00"), prijsklasse; per pagina optioneel een vestigingslocatie.
- **Verwerking:** Gegevens worden vertaald naar LocalBusiness-schema met openingstijden-specificatie en (geaggregeerde) reviews; locatiepagina's krijgen eigen schema.
- **Resultaat:** Correcte lokale weergave in Google, ook met meerdere vestigingen.
- **Commercieel relevant:** Opent het lokale mkb-segment (horeca, retail, zorg, diensten) — groot volume, lage kennis, hoge waardering.

### 10.6 WooCommerce-productschema + AI-productcontent
- **Functie:** Maakt webshopproducten rijk zichtbaar én goed beschreven.
- **Gebruikershandeling:** Optioneel merk/GTIN/MPN invullen; drie knoppen: genereer korte omschrijving, lange omschrijving (300–500 woorden, gestructureerd), of SEO-meta (titel, omschrijving, focus keyword).
- **Verwerking:** Productschema (naam, prijs, voorraad, reviews, varianten via AggregateOffer, afbeeldingen, afmetingen) wordt automatisch uitgevoerd; shop- en categoriepagina's krijgen CollectionPage-schema; AI gebruikt prijs, categorie en attributen als context.
- **Resultaat:** Producten met prijs/voorraad/sterren in Google en unieke productteksten in plaats van leveranciersteksten.
- **Commercieel relevant:** De e-commerce-pitch: duizenden producten met dunne beschrijvingen zijn in dagen gevuld — direct omzetrelevant.

---

## HF11 — Technische SEO & Sitebeheer

### 11.1 XML-sitemaps (basis + extended)
- **Functie:** Vertelt zoekmachines continu wat er te indexeren valt.
- **Gebruikershandeling:** Geen — automatisch; video-/news-sitemaps zijn aan/uit te zetten.
- **Verwerking:** Index-sitemap met deelsitemaps per posttype (1000 URL's per bestand) met lastmod/priority en hreflang; daarnaast aparte sitemaps voor video's (YouTube/Vimeo-detectie in content), nieuws (laatste 48 uur), afbeeldingen (max 20 per URL), RSS en auteurs; zoekmachines worden bij wijziging gepingd.
- **Resultaat:** Volledige, actuele sitemaps zonder onderhoud.
- **Commercieel relevant:** Hygiënefactor die meerdere losse plugins vervangt — onderdeel van het consolidatieverhaal.

### 11.2 Robots.txt-editor
- **Functie:** Beheert crawlerregels zonder FTP of techniek.
- **Gebruikershandeling:** Regels opstellen per user-agent (allow/disallow, crawl-delay), of volledige eigen inhoud; preview bekijken.
- **Verwerking:** De gegenereerde robots.txt wordt live geserveerd, inclusief sitemap-verwijzing en presets voor bekende bots.
- **Resultaat:** Correcte robots.txt, aanpasbaar door een marketeer.
- **Commercieel relevant:** Neemt angst voor "technische SEO" weg — alles in de UI.

### 11.3 Canonical-beheer
- **Functie:** Voorkomt duplicate-content-problemen.
- **Gebruikershandeling:** Geen; per post optioneel een afwijkende canonical-URL.
- **Verwerking:** Contextbewuste canonical-tags voor elk paginatype, inclusief paginering, hiërarchische pagina's en archieven.
- **Resultaat:** Eenduidige signalen naar Google over de "echte" versie van elke pagina.
- **Commercieel relevant:** Onzichtbaar maar essentieel — onderdeel van de "alles klopt onder de motorkap"-belofte.

### 11.4 IndexNow (instant indexering)
- **Functie:** Meldt nieuwe en gewijzigde pagina's direct bij zoekmachines.
- **Gebruikershandeling:** Geen — gebeurt bij publiceren, wijzigen en verwijderen automatisch.
- **Verwerking:** Automatische sleutelgeneratie en verificatie; submissies naar IndexNow-zoekmachines (o.a. Bing) met logboek van de laatste 100 inzendingen; ook batch-submissie mogelijk.
- **Resultaat:** Content sneller in de index; log als bewijs.
- **Commercieel relevant:** "Je nieuwe pagina binnen minuten aangemeld" — snelheid als tastbaar voordeel.

### 11.5 Redirect Manager
- **Functie:** Beheert alle doorverwijzingen en voorkomt linkverlies.
- **Gebruikershandeling:** Redirects aanmaken (bron → doel, 301/302/307, optioneel regex), importeren/exporteren via CSV.
- **Verwerking:** Redirects worden vóór paginaweergave afgehandeld; bij een slugwijziging wordt automatisch een 301 aangemaakt; hits en laatste gebruik worden geteld.
- **Resultaat:** Geen verloren bezoekers of linkwaarde bij URL-wijzigingen; statistieken per redirect.
- **Commercieel relevant:** Onmisbaar bij migraties en redesigns — vaak het instapmoment voor nieuwe klanten.

### 11.6 404-monitor met fix-suggesties
- **Functie:** Maakt onzichtbaar bezoekersverlies zichtbaar.
- **Gebruikershandeling:** 404-log bekijken; per regel een redirect aanmaken.
- **Verwerking:** Elke 404 wordt gelogd met URL, verwijzer, user-agent en teller (ruis wordt gefilterd); het systeem stelt vergelijkbare bestaande URL's voor als fix; content wordt periodiek gescand op kapotte links.
- **Resultaat:** Top-404-lijst met één-klik-oplossingen en opgelost/onopgelost-status.
- **Commercieel relevant:** "Hoeveel bezoekers verlies jij ongemerkt?" — een sterke confronterende demovraag.

### 11.7 Technische audit met vier scores
- **Functie:** Doorlicht de hele site technisch, op verzoek en wekelijks automatisch.
- **Gebruikershandeling:** "Run Full Audit" klikken; rapport doorlopen.
- **Verwerking:** Checks op crawlbaarheid (robots.txt, sitemap, redirect-ketens, wees-pagina's, klikdiepte), crawlbudget (duplicaten, dunne content <300 woorden, paginering), URL-structuur (lengte, tekens, stopwoorden), sitemap-gezondheid (validatie met steekproef) en performance (responstijd, CDN, compressie, browser-caching) — samengevat in vier scores van 0–100.
- **Resultaat:** Een begrijpelijk auditrapport met scores en concrete issues, automatisch wekelijks ververst.
- **Commercieel relevant:** Het auditrapport is een leadmagneet én de structurele rechtvaardiging van het abonnement ("elke week opnieuw gecontroleerd").

### 11.8 PageSpeed-metingen
- **Functie:** Meet laadsnelheid en Core Web Vitals.
- **Gebruikershandeling:** URL en desktop/mobiel kiezen (Google API-key instelbaar).
- **Verwerking:** Google PageSpeed Insights levert performance-score plus LCP, INP, CLS, TTFB en FCP.
- **Resultaat:** Snelheidscijfers in hetzelfde portaal als de rest van de SEO-data.
- **Commercieel relevant:** Core Web Vitals zijn een bekende Google-rankingfactor — herkenbaar koopargument.

---

## HF12 — Internationale & Meertalige SEO

### 12.1 Automatische hreflang-generatie
- **Functie:** Verbindt taalversies van pagina's correct met elkaar.
- **Gebruikershandeling:** Geen — bij WPML, Polylang of TranslatePress volledig automatisch.
- **Verwerking:** Taalkoppelingen worden uit de vertaalplugin gelezen en als hreflang-tags (incl. x-default) in de head én de sitemaps geplaatst.
- **Resultaat:** Elke zoeker krijgt de juiste taalversie te zien.
- **Commercieel relevant:** Hreflang is berucht foutgevoelig; "automatisch goed" is hier een echte geruststelling.

### 12.2 Handmatige taalmapping
- **Functie:** Meertalige SEO zonder vertaalplugin.
- **Gebruikershandeling:** Per post de taalvarianten en hun URL's invoeren in een metabox.
- **Verwerking:** Twaalf voorgedefinieerde taalregio's (nl-nl, nl-be, de-de, fr-fr, en-us, …) worden naar hreflang-tags vertaald.
- **Resultaat:** Ook custom meertalige opzetten zijn correct gemarkeerd.
- **Commercieel relevant:** Flexibiliteit — geen verplichte plugin-stack als voorwaarde.

### 12.3 AI-keywordresearch per land
- **Functie:** Vindt hoe dezelfde behoefte per markt anders wordt gezocht.
- **Gebruikershandeling:** Doelmarkten kiezen, keyword invoeren, "Research" klikken.
- **Verwerking:** AI genereert landspecifieke keywordvariaties (lokale termen, geen letterlijke vertalingen) en landgerichte contentaanbevelingen.
- **Resultaat:** Per land een eigen keywordlijst en concrete contentadviezen.
- **Commercieel relevant:** "Vlaanderen zoekt anders dan Nederland" — een inzicht dat direct verkoopt bij grensoverschrijdende bedrijven.

### 12.4 Multi-country tracking & geo-instellingen per post
- **Functie:** Volgt prestaties per markt en richt pagina's op een land.
- **Gebruikershandeling:** Keywords per land laten tracken; per post doelland, taal en valuta instellen.
- **Verwerking:** Rankings worden per landcode bijgehouden; geo-metadata wordt per post opgeslagen.
- **Resultaat:** Een internationaal prestatie-overzicht plus correct geo-gerichte pagina's.
- **Commercieel relevant:** Maakt internationale ambitie meetbaar — relevant voor scale-ups en exporteurs.

---

## HF13 — Interne Linking

### 13.1 Orphan-page-detectie
- **Functie:** Vindt pagina's waar geen enkele interne link naartoe wijst.
- **Gebruikershandeling:** Geen — dagelijkse automatische scan; dashboard tonen.
- **Verwerking:** De interne linkgraaf wordt geanalyseerd; pagina's zonder inkomende links worden gemarkeerd.
- **Resultaat:** Lijst van "onzichtbare" pagina's die Google nauwelijks kan vinden.
- **Commercieel relevant:** Vrijwel elke bestaande site heeft orphans — gegarandeerd "aha"-moment in audits.

### 13.2 Prioritering van linkkansen
- **Functie:** Bepaalt welke linkfix het meeste oplevert.
- **Gebruikershandeling:** Gesorteerde lijst doorlopen.
- **Verwerking:** Prioriteitsscore op basis van relevantie × traffic × klikdiepte.
- **Resultaat:** Niet 200 taken, maar de 10 die ertoe doen, bovenaan.
- **Commercieel relevant:** Focus als feature — past bij de tijdarme mkb-doelgroep.

### 13.3 AI-ankertekst-suggesties & auto-fix
- **Functie:** Maakt het leggen van de link zelf moeiteloos.
- **Gebruikershandeling:** Suggestie accepteren of "Auto-fix orphans" klikken.
- **Verwerking:** AI formuleert een natuurlijke ankertekst die past in de brontekst; de link wordt geplaatst.
- **Resultaat:** Natuurlijke interne links zonder geforceerde formuleringen.
- **Commercieel relevant:** Van inzicht tot uitgevoerde fix binnen dezelfde minuut — automation als bewijsbaar verschil.

### 13.4 Link Assistant in de editor
- **Functie:** Stelt tijdens het schrijven relevante interne links voor.
- **Gebruikershandeling:** Suggestielijst in de metabox bekijken en overnemen.
- **Verwerking:** Relevantiescoring op basis van focus keyphrases en keywords van andere posts (boven een drempel), met voorgestelde ankertekst; bestaande links worden meegewogen.
- **Resultaat:** Elke nieuwe post is vanaf publicatie goed verweven met de rest van de site.
- **Commercieel relevant:** Bouwt de linkstructuur preventief op — "goed vanaf dag één" in plaats van achteraf repareren.

---

## HF14 — Dashboards, Monitoring & Rapportage

### 14.1 Sitebrede gezondheidsscore & quick wins
- **Functie:** Vat de SEO-staat van de hele site samen.
- **Gebruikershandeling:** Dashboard openen.
- **Verwerking:** De 100 recentste posts worden gescand op ontbrekende titels/omschrijvingen/keyphrases/OG/alt-teksten, dunne content (<300 woorden), ontbrekende interne links en thumbnails; resultaat wordt 5 minuten gecached.
- **Resultaat:** Eén sitescore, een issuelijst en een "quick wins"-lijst met de snelste verbeteringen.
- **Commercieel relevant:** Het openingsscherm van elke demo en elk klantgesprek: status in één getal.

### 14.2 Google Search Console-koppeling (OAuth)
- **Functie:** Haalt de echte Google-data de tool in.
- **Gebruikershandeling:** Eenmalig "Verbind met Google" en toestemming geven.
- **Verwerking:** Veilige OAuth2-flow met automatische tokenverversing; ondersteunt domein- en URL-properties.
- **Resultaat:** Permanente, onderhoudsvrije GSC-verbinding.
- **Commercieel relevant:** "Geen aparte tools meer openen" — consolidatie als kernbelofte.

### 14.3 GSC-prestatie-rapportage
- **Functie:** Toont hoe de site echt presteert in Google.
- **Gebruikershandeling:** GSC-dashboard openen.
- **Verwerking:** 28-dagen-overzicht van impressies, klikken, CTR en gemiddelde positie, plus topzoekopdrachten en toppagina's.
- **Resultaat:** Het officiële Google-beeld naast de eigen tracking — dubbel onderbouwd.
- **Commercieel relevant:** Google's eigen cijfers zijn de meest geloofwaardige bewijsvoering richting klant of directie.

### 14.4 Content Performance & ROI-tracking (GA4)
- **Functie:** Verbindt SEO-werk met verkeer en conversies.
- **Gebruikershandeling:** GA4 Measurement ID invoeren; conversiedoelen kiezen (formulieren, klikken).
- **Verwerking:** Dagelijkse meting; baseline vóór optimalisatie wordt vergeleken met de periode erna; time-to-rank wordt bijgehouden.
- **Resultaat:** Per content-item: levert de optimalisatie verkeer en conversies op?
- **Commercieel relevant:** De brug van "rankings" naar "omzet" — het taalniveau van de beslisser.

### 14.5 Content Decay-detectie
- **Functie:** Waarschuwt als goed presterende content begint te zakken.
- **Gebruikershandeling:** Geen — automatisch; alerts en dashboard bekijken.
- **Verwerking:** Dagelijkse vergelijking van positietrends per keyword tegen de baseline; dalingen krijgen een ernstniveau (low/medium/high/critical) en verbetersuggesties.
- **Resultaat:** Vroege waarschuwing met prioriteit, vóórdat traffic-verlies in de omzet zichtbaar is.
- **Commercieel relevant:** Verzekeringslogica: het abonnement "bewaakt wat je hebt opgebouwd" — sterk retentieargument.

### 14.6 A/B-testen op titel/content/meta
- **Functie:** Test welke variant beter converteert of klikt.
- **Gebruikershandeling:** Test aanmaken: varianten + traffic-verdeling + doel (paginaweergave, klik, formulier, tijd op pagina); resultaten volgen; test afsluiten.
- **Verwerking:** Bezoekers worden via een 30-dagen-sessiecookie consistent aan een variant toegewezen; titel/content/meta worden realtime gewisseld; impressies en conversies per variant worden geteld.
- **Resultaat:** Statistieken per variant en een aanwijsbare winnaar.
- **Commercieel relevant:** Zeldzaam in SEO-tools: optimaliseren op bewijs — premium-feature met conversion-verhaal.

### 14.7 SEO-revisiehistorie met herstel
- **Functie:** Maakt elke SEO-wijziging traceerbaar en omkeerbaar.
- **Gebruikershandeling:** Historie per post bekijken; "Restore" klikken bij spijt.
- **Verwerking:** Elke wijziging aan titel, omschrijving, keyphrase en score wordt met gebruiker en tijdstip vastgelegd (laatste 50 per post).
- **Resultaat:** "Wie veranderde wat wanneer" + één-klik-herstel.
- **Commercieel relevant:** Governance voor teams en bureaus; veiligheidsgevoel dat experimenteren stimuleert.

### 14.8 Rapport-export (CSV/PDF)
- **Functie:** Levert het werk op papier op.
- **Gebruikershandeling:** Export kiezen (CSV of PDF).
- **Verwerking:** Tot 500 posts worden samengevat met scores, meta-velden en issues.
- **Resultaat:** Een deelbaar rapport voor klant, management of dossier.
- **Commercieel relevant:** Voor bureaus de maandelijkse deliverable; voor bedrijven de interne verantwoording.

### 14.9 AI-verbruikslog
- **Functie:** Maakt het AI-gebruik volledig transparant.
- **Gebruikershandeling:** Log raadplegen.
- **Verwerking:** Elke AI-aanroep wordt vastgelegd met model, tokens, kosten, duur en succes/fout; statistieken per periode; automatische opschoning na 90 dagen.
- **Resultaat:** Inzicht in verbruik en kosten, plus debuginformatie bij problemen.
- **Commercieel relevant:** Transparantie over AI-verbruik voorkomt billing-discussies en ondersteunt fair-use-communicatie.

---

## HF15 — Integraties, Automatisering & Teamworkflow

### 15.1 Notificatiekanalen (Slack/Zapier/Make)
- **Functie:** Brengt SEO-signalen naar waar het team al werkt.
- **Gebruikershandeling:** Webhook-URL invoeren en notificatietypes kiezen.
- **Verwerking:** Drie events triggeren berichten: rankwijziging (met positieverschil), content gepubliceerd, SEO-score gewijzigd; via Zapier/Make is elk extern systeem aansluitbaar.
- **Resultaat:** Realtime meldingen in Slack of elke gekoppelde tool.
- **Commercieel relevant:** "Past in jullie workflow" neutraliseert het volgende-tool-moeheid-bezwaar.

### 15.2 Automatische e-mail-/Drive-rapportages
- **Functie:** Periodieke rapportage zonder handwerk.
- **Gebruikershandeling:** Ontvanger en frequentie kiezen (dagelijks 9:00 / wekelijks maandag / maandelijks); optioneel auto-export naar Google Drive of Notion-koppeling.
- **Verwerking:** Geplande achtergrondtaken stellen het rapport samen en versturen/archiveren het.
- **Resultaat:** De stakeholder krijgt het overzicht vanzelf in de inbox of map.
- **Commercieel relevant:** Voor bureaus: klantrapportage geautomatiseerd = marge; voor bedrijven: management vanzelf geïnformeerd.

### 15.3 Redactiekalender met workflow
- **Functie:** Plant en bewaakt het hele contentproces.
- **Gebruikershandeling:** Kalender bekijken; content toewijzen (uitvoerder, goedkeurder, deadline); goedkeuren/afwijzen vanuit de wachtrij.
- **Verwerking:** Posts doorlopen statussen (concept → in uitvoering → ter review → goedgekeurd/afgewezen); toewijzingen en besluiten triggeren Slack-/e-mailnotificaties.
- **Resultaat:** Visuele kalender met kleurstatussen en een goedkeuringswachtrij — niemand publiceert ongezien.
- **Commercieel relevant:** Verandert het product van "tool voor één marketeer" in "systeem voor het hele team" — hogere seats, lagere churn.

### 15.4 Content-gap-detectie & timingadvies
- **Functie:** Bewaakt het publicatieritme.
- **Gebruikershandeling:** Gewenste frequentie instellen (dagelijks/wekelijks/tweewekelijks).
- **Verwerking:** Verwachte publicatiedagen zonder ingeplande content worden gemarkeerd; historische prestaties voeden suggesties voor optimale publicatietijdstippen.
- **Resultaat:** Gaten in de kalender zichtbaar vóórdat ze ontstaan.
- **Commercieel relevant:** Consistentie is de bekendste SEO-succesfactor — de tool bewaakt hem actief.

### 15.5 Rollen & rechten
- **Functie:** Regelt wie wat mag binnen het portaal.
- **Gebruikershandeling:** Standaard WordPress-rollen gebruiken; rechten zijn per rol bepaald.
- **Verwerking:** Negen capabilities (instellingen, dashboard, meta bewerken, SERP inzien, redirects, 404's, schema, AI-features, bulkacties) zijn verdeeld over administrator/editor/author/contributor; pagina-toegang wordt per recht afgeschermd.
- **Resultaat:** Stagiairs bewerken meta, maar slopen geen instellingen.
- **Commercieel relevant:** Enterprise-hygiëne die grotere organisaties als voorwaarde stellen.

---

## HF16 — SaaS-Platform, White-label & Commercieel Beheer

### 16.1 Tenantbeheer (multi-tenant)
- **Functie:** Houdt elke klant strikt gescheiden in eigen omgeving.
- **Gebruikershandeling (beheerder):** Tenantoverzicht raadplegen, status/tier aanpassen, per-tenant instellingen beheren.
- **Verwerking:** Per tenant worden identiteit, status (actief/geschorst/opgezegd), tier, limieten en maandverbruik bijgehouden; instellingen kunnen per tenant afwijken.
- **Resultaat:** Onbeperkt klanten op één platform zonder datavermenging.
- **Commercieel relevant:** De schaalbaarheidsbasis van het hele businessmodel.

### 16.2 Licentiegeneratie & -beheer
- **Functie:** Maakt en beheert de toegangssleutels tot het product.
- **Gebruikershandeling (beheerder):** Sleutels genereren (type: trial/paid/lifetime/test; tier; geldigheidsduur; max. sites), filteren, intrekken.
- **Verwerking:** Unieke sleutels met status-levenscyclus (actief → gebruikt → ingetrokken/verlopen); statistieken (totaal, vandaag, deze maand).
- **Resultaat:** Volledige controle over distributie — ook offline verkoop, partners en proeflicenties.
- **Commercieel relevant:** Sales-flexibiliteit: trials uitdelen op een beurs, lifetime-deals, partnerbatches — zonder ontwikkelwerk.

### 16.3 License API (activatie/validatie/status)
- **Functie:** De machinekoppeling tussen klantsites en het platform.
- **Gebruikershandeling:** Geen (machine-naar-machine).
- **Verwerking:** Endpoints voor activeren (maakt de tenant aan), valideren, status opvragen (tier, limieten, features), deactiveren en verbruik rapporteren.
- **Resultaat:** Volledig geautomatiseerde klantlevenscyclus van activatie tot opzegging.
- **Commercieel relevant:** Nul handmatige provisioning = lage operationele kosten per klant.

### 16.4 API-gateway met kostenbewaking
- **Functie:** Bewaakt verbruik en marge op elke AI-/SERP-aanvraag.
- **Gebruikershandeling (beheerder):** Kostendashboard raadplegen; limieten per tier instellen.
- **Verwerking:** Elke aanvraag wordt getoetst aan twee limieten per tenant (aantal calls én kosten per maand); kostprijs wordt per AI-model en SERP-provider berekend en geboekt op de tenant.
- **Resultaat:** Per klant exact zichtbaar wat hij kost versus wat hij betaalt.
- **Commercieel relevant:** Marge-management ingebouwd — onmisbaar voor gezonde SaaS-pricing en fair-use-handhaving.

### 16.5 Feature-toggles per tier én per licentie
- **Functie:** Bepaalt op feature-niveau wat elke klant krijgt.
- **Gebruikershandeling (beheerder):** "Manage Features" bij een licentie openen en features aan-/uitzetten; opslaan.
- **Verwerking:** 45+ features in vijf categorieën hebben tier-defaults; per licentie kunnen features worden toegevoegd of uitgezet; de featurelijst synchroniseert automatisch naar de klantsite bij validatie.
- **Resultaat:** Maatwerkbundels zonder maatwerkcode: een Starter-klant één Professional-feature geven, of een beta alleen voor specifieke klanten openzetten.
- **Commercieel relevant:** Pricing-flexibiliteit als concurrentievoordeel: upsell per feature, gerichte pilots, churn-redding ("we zetten X voor je aan").

### 16.6 Billing & webhooks (Stripe/Mollie)
- **Functie:** Verwerkt abonnementen en betalingsmutaties automatisch.
- **Gebruikershandeling (klant):** Checkout doorlopen; (beheerder): provider en prijzen configureren.
- **Verwerking:** Stripe (creditcard/SEPA, wereldwijd) of Mollie (iDEAL/Bancontact/SEPA, Europa); webhooks vertalen upgrades, downgrades, opzeggingen en refunds direct naar tenant-status en -tier.
- **Resultaat:** Betaling → toegang, opzegging → schorsing; zonder handmatige tussenkomst.
- **Commercieel relevant:** iDEAL-ondersteuning is essentieel voor de Nederlandse markt; automatische dunning-afhandeling beschermt omzet.

### 16.7 White-label & client portal
- **Functie:** Laat partners het product onder eigen merk voeren.
- **Gebruikershandeling (partner):** Logo, kleuren, bedrijfsnaam, footer en support-gegevens instellen; client portal en teamleden beheren.
- **Verwerking:** Branding wordt centraal opgeslagen en bij licentievalidatie naar de client-plugins van de eindklanten gesynchroniseerd (admin-uitstraling, login, footer); reseller-instellingen (o.a. commissie) zijn voorbereid.
- **Resultaat:** De eindklant ziet overal het merk van het bureau — Fyndable blijft onzichtbaar.
- **Commercieel relevant:** Opent het partnerkanaal: bureaus verkopen "hun eigen" SEO-platform, met terugkerende omzet voor beide partijen.

---

# DEEL 4 — TRANSPARANTIENOTITIES (commercieel relevant)

Voor geloofwaardig commercieel materiaal: dit zijn de punten waar claims genuanceerd moeten worden of waar het product nog in ontwikkeling is. Geverifieerd in de code.

1. **SERP- en keyword-data zijn afhankelijk van de geconfigureerde provider.** De SaaS-gateway ondersteunt DataForSEO, SerpAPI en SE Ranking; zonder geconfigureerde provider vallen sommige modules (keywordmetrics, SERP-analyse) terug op AI-gegenereerde of mock-data. Commercieel: claim "live SERP-data" alleen wanneer een provider actief is.
2. **Backlinkdata vereist een Ahrefs- of Semrush-API-key.** Zonder key toont de Backlink Analyzer voorbeelddata. De SE Ranking-client bestaat maar wordt nog niet overal actief aangeroepen.
3. **Enkele functies zijn voorbereid maar niet afgebouwd:** YouTube-captions ophalen (placeholder), vision-gebaseerde alt-tekstanalyse (voorbereid), Mollie-integratie (gedeeltelijk; Stripe is verder uitgewerkt), Free-tier (verwijst nu naar licentie-activatie).
4. **Prijzen verschillen tussen documenten:** FEATURES.md noemt €9/€29/€79/€199; de PaymentProcessor hanteert €19/€49/€99/€199. Vóór commerciële uitingen één prijslijn vaststellen.
5. **Cijfermatige claims (CTR-percentages, ROI-berekeningen) uit eerdere interne documenten zijn niet door productdata onderbouwd** — gebruik ze in marketing alleen als indicatieve branchecijfers met bronvermelding, of vervang ze door eigen casedata zodra beschikbaar.
6. **Sterke, wél hard te claimen differentiatie:** Nederlandse leesbaarheidsanalyse (Flesch-Douma), gepersonaliseerde keyword difficulty op basis van eigen site-autoriteit, AI-content-detectie bij concurrenten, volledige white-label met per-licentie feature-toggles, en de breedte (één platform i.p.v. 5–7 losse tools: contentoptimalisatie, rank tracking, technische audit, AI-schrijver, beeldgeneratie, A/B-testing, integraties).

---

# DEEL 5 (STAP 4) — END-TO-END GEBRUIKERSFLOW & AHA-MOMENTEN

De flow beschrijft het volledige traject van eerste contact tot tastbaar SEO-resultaat, in 10 fases. Per fase: **(1) wat de gebruiker doet, (2) wat het systeem doet, (3) wat de gebruiker ervaart, (4) welke waarde daar ontstaat.** De aha-momenten zijn gemarkeerd met 💡.

## Flow in vogelvlucht

```
FASE 1          FASE 2          FASE 3            FASE 4           FASE 5
Eerste contact → Activatie    → Nulmeting       → Input &        → Strategie
& licentie       (2 min)        (dashboard +      keyword-          (clusters +
                                audit)            research)         briefs)
                     💡A             💡B               💡C              💡D

FASE 6          FASE 7          FASE 8            FASE 9           FASE 10
Productie &   → Publicatie &  → Monitoring      → Rapportage &   → Opschalen
optimalisatie    afronding       (ranks + GSC)     bewaking         (bulk, A/B,
   💡E              💡F              💡G               💡H            team, label)
```

De flow is geen eenmalige lijn maar een **vliegwiel**: fase 4 t/m 9 herhalen zich per onderwerp/cluster, waarbij elke ronde sneller gaat omdat keyworddata, clusters en monitoring al staan.

---

## FASE 1 — Eerste contact & licentie

1. **Gebruiker doet:** Sluit een abonnement af (of start een 14-daagse trial met volledige features) en ontvangt één licentiesleutel. Bij een white-label-partner gebeurt dit onder het merk van het bureau.
2. **Systeem doet:** Genereert de licentiesleutel met het juiste tier-profiel (featureset, AI-tegoed, limieten); bij online betaling regelen Stripe/Mollie-webhooks dit volautomatisch.
3. **Gebruiker ervaart:** Geen intake-formulieren, geen API-keys, geen onboarding-call nodig — alleen een sleutel in de mail.
4. **Waarde:** De drempel om te beginnen is minimaal; de trial maakt het risico nul.

## FASE 2 — Installatie & activatie (≈ 2 minuten)

1. **Gebruiker doet:** Installeert de plugin op WordPress, opent "Connection", plakt de sleutel en klikt "Activate License".
2. **Systeem doet:** Valideert de sleutel, maakt op het platform een eigen klantomgeving (tenant) aan, haalt de featurelijst en eventuele white-label-branding op en bouwt de menustructuur op.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT A — "Het werkt gewoon."** Na één klik verschijnt een compleet SEO-portaal in de vertrouwde WordPress-omgeving, inclusief werkende AI — zonder ook maar één technische handeling.
4. **Waarde:** Time-to-value van minuten in plaats van dagen; het contrast met "tool-stapels koppelen" is meteen voelbaar.

## FASE 3 — Nulmeting: dashboard & technische audit

1. **Gebruiker doet:** Opent het SEO Dashboard en klikt op "Run Full Audit".
2. **Systeem doet:** Scant de recentste content op ontbrekende meta, dunne content, ontbrekende alt-teksten en interne links; berekent de sitebrede gezondheidsscore met quick wins. De audit checkt crawlbaarheid, sitemap-gezondheid, URL-structuur en performance (vier scores); de 404-monitor en orphan-detectie beginnen op de achtergrond te verzamelen.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT B — "Dít is er dus aan de hand."** Eén score voor de hele site plus een concrete lijst: "23 posts zonder meta-omschrijving, 8 wees-pagina's, 14 dode links". Problemen waarvan de gebruiker het bestaan niet kende, worden in één scherm zichtbaar — mét fix-knop ernaast.
4. **Waarde:** De nulmeting maakt de uitgangspositie objectief (basis voor latere bewijsvoering) en levert direct uitvoerbare quick wins — vaak het eerste tastbare resultaat binnen het eerste uur.

## FASE 4 — Input & keywordresearch: "vertel wat je doet"

1. **Gebruiker doet:** Voert in de Keywords-module zijn product, dienst of branche in, kiest taal/markt (NL, BE, DE, …) en klikt op genereren; importeert eventueel bestaande keywordlijsten via CSV.
2. **Systeem doet:** AI vertaalt de bedrijfsomschrijving naar concrete zoektermen met volume, moeilijkheid, CPC en zoekintentie; de Keyword Explorer breidt uit op basis van wat nú in Google rankt; clustering groepeert termen die op één pagina horen; de gepersonaliseerde difficulty-analyse vergelijkt elke term met de autoriteit en contentvoorraad van de eigen site.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT C — "Zó zoekt mijn klant dus écht."** De gebruiker ziet zijn eigen vakgebied terug in de taal van de zoeker — inclusief termen, vragen en probleemformuleringen waar hij zelf nooit aan gedacht had. En direct daarbij: "deze 12 keywords kun jíj winnen."
4. **Waarde:** Dit is de kern van de Fyndable-belofte: van bedrijfscontext naar gevalideerde zoekvraag. De gebruiker heeft nu een onderbouwde, geprioriteerde lijst in plaats van aannames.

## FASE 5 — Strategie: clusters & contentbriefs

1. **Gebruiker doet:** Kiest een kernonderwerp en laat Topic Clusters een contentarchitectuur ontwerpen; laat de audit bestaande content matchen; genereert per prioriteitskeyword een Content Brief.
2. **Systeem doet:** Bouwt een pillar-clusterplan (hubpagina's + ondersteunende pagina's met keywords, woordaantallen en prioriteiten), een interne linkstrategie en een weekplanning; herkent welke bestaande posts al in het plan passen. De brief-generator analyseert per keyword de top-10 en levert woordaantal, headingstructuur, FAQ-vragen, entiteiten en een unieke invalshoek.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT D — "Ik heb opeens een plan."** Wat normaal een strategietraject van een bureau is (contentplan, briefings, planning), staat na enkele minuten klaar — afgestemd op wat er al op de site staat.
4. **Waarde:** Richting en volgorde: de gebruiker weet niet alleen wát te maken, maar ook waarom, in welke volgorde en hoe het samenhangt (topical authority in plaats van losse blogs).

## FASE 6 — Productie & optimalisatie

1. **Gebruiker doet:** Twee routes, vaak parallel:
   - **Nieuw:** stuurt een brief naar de AI Content Writer (keyword, toon, woordaantal) en reviewt het gegenereerde concept; of werkt vanuit Ideas/bulk-clustergeneratie.
   - **Bestaand:** opent een belangrijke pagina in de Content Optimizer, plakt de content en werkt de term-heatmap weg; verbetert in de editor de TruSEO-checklist tot de score groen is.
2. **Systeem doet:** Genereert complete artikelen (intro, secties, FAQ, conclusie, meta) als WordPress-concept; scoort content realtime tegen het SERP-topicmodel; stelt LSI-termen, interne links met ankerteksten en herschrijvingen voor; checkt originaliteit; genereert featured/social-beelden en alt-teksten.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT E — twee varianten:**
   - *"In vijf minuten een artikel waar ik normaal een dag over doe"* (eerste AI-artikel als reviewbaar concept, inclusief beeld);
   - *"Van score 52 naar 88 terwijl ik typte"* (de live meelopende score maakt verbetering verslavend zichtbaar).
4. **Waarde:** De grootste bottleneck van SEO — productiecapaciteit — valt weg, terwijl de gebruiker via review en scores de kwaliteit zelf in de hand houdt.

## FASE 7 — Publicatie & technische afronding

1. **Gebruiker doet:** Publiceert (of laat via de redactiekalender goedkeuren en inplannen). Vrijwel alles in deze fase gebeurt zonder handeling.
2. **Systeem doet:** Genereert automatisch meta/OG-tags en smart tags, plaatst het juiste schema (Article/FAQ/Product/…), neemt de pagina op in de sitemaps, pingt zoekmachines via IndexNow, legt een SEO-revisie vast en bewaakt dat er geen orphan ontstaat (interne linksuggesties).
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT F — "Alles eromheen is al geregeld."** Waar publicatie normaal een checklist van tien nazorgtaken is, is hier alles al gebeurd — zichtbaar in het IndexNow-log en de schema-preview.
4. **Waarde:** Consistente technische kwaliteit op elke publicatie, onafhankelijk van wie er publiceert; geen kennis- of disciplineafhankelijkheid meer.

## FASE 8 — Monitoring: het bewijs komt binnen

1. **Gebruiker doet:** Voegt de doelkeywords toe aan de Rank Tracker (land + URL) en koppelt eenmalig Google Search Console (en optioneel GA4).
2. **Systeem doet:** Checkt dagelijks automatisch alle posities en bouwt 90 dagen historie op; haalt impressies, klikken, CTR en posities uit GSC; meet conversies via GA4; stuurt bij positiewijzigingen alerts naar e-mail/Slack.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT G — "Het wérkt."** De eerste stijging in de trendgrafiek — of de eerste Slack-melding "van positie 14 naar 8" — is het moment waarop SEO van kostenpost in investering verandert. Dit is emotioneel het sterkste moment van de hele flow.
4. **Waarde:** Objectief, dagelijks bewijs van resultaat zonder enige handmatige controle; het vertrouwen dat het abonnement zich terugverdient.

## FASE 9 — Rapportage & bewaking (continu)

1. **Gebruiker doet:** Stelt eenmalig rapportagefrequentie en kanalen in; leest daarna vooral wat binnenkomt. Bij een decay-alert klikt hij door naar de betreffende content en verbetert die (terug naar fase 6).
2. **Systeem doet:** Verstuurt dagelijkse/wekelijkse/maandelijkse rapporten per mail (of naar Drive/Notion); de Content Decay Monitor vergelijkt elke dag posities met de baseline en alarmeert met ernstniveau en suggesties; rapporten zijn exporteerbaar als CSV/PDF.
3. **Gebruiker ervaart:** 💡 **AHA-MOMENT H — "De tool let op, ook als ik er niet ben."** Een decay-alert die een daling signaleert vóórdat het verkeer instort, bewijst de bewakingswaarde van het abonnement.
4. **Waarde:** Behoud van opgebouwd resultaat (verzekeringswaarde) + automatische verantwoording richting management of klant — de twee sterkste churn-remmers van het product.

## FASE 10 — Opschalen: van gebruiker naar systeem

1. **Gebruiker doet:** Pakt door op wat werkt: bulk-optimalisatie van legacy-content, A/B-tests op toppagina's, extra teamleden met rollen en goedkeurworkflow, nieuwe markten (international SEO), en — voor bureaus — white-label-uitrol naar eigen klanten.
2. **Systeem doet:** Verwerkt batches met voortgangsbewaking, draait split-tests met conversiemeting, handhaaft rechten en workflow, synct branding naar klantsites en houdt per licentie verbruik en features bij.
3. **Gebruiker ervaart:** Het portaal groeit mee van persoonlijke tool naar teamsysteem; voor bureaus wordt het een eigen productlijn.
4. **Waarde:** Schaal zonder evenredige kosten — en voor Fyndable: natuurlijk upsell-pad (Starter → Professional → Business → Agency) dat de gebruiker zelf ontdekt op het moment dat hij de behoefte voelt.

---

## De aha-momenten samengevat (voor marketing & demo-script)

| # | Moment | Fase | Trigger | Commercieel gebruik |
|---|---|---|---|---|
| 💡A | "Het werkt gewoon" | 2 | Eén sleutel → compleet portaal | Onboarding-belofte in ads/landingpages ("live in 2 minuten") |
| 💡B | "Dít is er aan de hand" | 3 | Nulmeting + auditlijst | Demo-opener en leadmagneet (gratis audit) |
| 💡C | "Zó zoekt mijn klant écht" | 4 | AI-keywordresearch + winbare keywords | De kernbelofte van Fyndable — hero-boodschap website |
| 💡D | "Ik heb opeens een plan" | 5 | Clusterplan + briefs | Onderscheid t.o.v. "losse tools": strategie inbegrepen |
| 💡E | "Artikel in minuten" / "score live omhoog" | 6 | AI Writer / live TruSEO-score | Hét live-demomoment; video-content voor social |
| 💡F | "Alles eromheen is al geregeld" | 7 | Automatische nazorg bij publicatie | Bezwaarweerlegging ("ik heb geen tijd/kennis") |
| 💡G | "Het wérkt" | 8 | Eerste positiestijging / alert | Case studies, testimonials, retentie |
| 💡H | "De tool let op, ook zonder mij" | 9 | Decay-alert vóór trafficverlies | Churn-preventie en abonnementsrechtvaardiging |

**Demo-advies:** de ideale demovolgorde is B → C → E (probleem tonen → herkenning creëren → magie laten zien) in maximaal 15 minuten, met G als beloofde vervolgafspraak ("over drie weken kijken we samen naar je eerste trends").

**Funnel-advies:** de fases corresponderen met de klantreis — fase 3 (audit) als gratis leadmagneet, fase 4–6 als trial-ervaring, fase 8–9 als conversie- en retentiemotor, fase 10 als expansie-omzet.

---

## Bijlage — Modulematrix per licentietier (zoals geconfigureerd)

| Tier | Featureset (samengevat) |
|---|---|
| **Starter** | Core SEO (TruSEO, sitemaps, robots.txt, OG, canonical, breadcrumbs, hreflang, LSI, readability, IndexNow, kalender, interne linking, E-E-A-T, video-SEO, FAQ-schema, AI-images) + Link Assistant, Redirect Manager, Alt-generator, Content Rewriter |
| **Professional** | + Schema Markup, Local SEO, 404 Monitor, Rank Tracker, Rapport-export, WooCommerce SEO, Content Optimizer, SERP Competitor, Topic Clusters, Keyword Difficulty, Content Brief, Keyword Explorer, GSC Dashboard, SERP Features, Backlink Analyzer, Competitor Research, International SEO, Technical Audit, Advanced Backlinks |
| **Business** | + AI Content Writer, Content Repurposer, Bulk Optimizer, Content Decay, Audit Service |
| **Agency** | + SEO Revisions, Plagiarism Checker, White-label |
| **Trial** | 14 dagen volledige features |

*Per licentie kunnen features individueel worden aan-/uitgezet (maatwerkbundels) via het SaaS-portaal.*

---

*Einde rapport. Dit document vormt de feitelijke basis voor sales decks, websiteteksten, brochures, e-mailtemplates en advertenties; elke functionele claim hierin is herleidbaar tot de broncode.*

# Plan — Missende features Fyndable WordPress-plugin

*Opgesteld 2026-11-04 op basis van `fyndable-client/includes/` (~115 modules), de feature-inventaris en de open punten in `AGENTS.md`.*

## Uitgangspunt

De plugin is al breder dan Yoast of Rank Math (ApexFlow, GEO-scans, llms.txt, LLM-tracker, local geo-grid, backlinks, A/B-testing). Het probleem is niet de breedte maar:

1. **onafgemaakte onderdelen** (UI zonder handler),
2. **ontbrekende GEO-bouwstenen** die precies bij de positionering "AI-vindbaarheid" horen,
3. **schaal en kwaliteit** (115 modules, geen tests).

---

## Fase 0 — Afmaken wat half staat (snel, laag risico)

| # | Punt | Status nu |
|---|------|-----------|
| 0.1 | **Competitor Research-tabs** `sseo_ai_get_keyword_strategy`, `_content_calendar`, `_win_loss`, `_ad_copy` | De UI roept handlers aan die niet bestaan, dus de gebruiker ziet lege of kapotte tabs. Bouwen of tabs verbergen. |
| 0.2 | **Disavow** (`sseo_ai_disavow_link`) | De knop bestaat, de handler niet. Bouwen: disavow-lijst bijhouden + export naar `disavow.txt` voor Google. |
| 0.3 | **Backlinkhistorie + alerts** | Nu alleen een momentopname. Bouwen: wekelijkse snapshot, nieuwe/verloren links, DR-trend en een melding via de alert notifier bij verlies van sterke links. |
| 0.4 | **Image-proxy + rotatie van de centrale key** | Staat open sinds security patch 1. Beeldgeneratie via de SaaS laten lopen, zodat er geen provider-key meer naar de client gaat. |
| 0.5 | **Testsuite + CI** | Geen tests. Minimaal PHPUnit voor licentie-validatie, REST-permissies, ApexFlow-pipeline en sitemap/robots-output, plus `php -l` en PHPStan in CI. |

---

## Fase 1 — Key features die nu missen

| # | Feature | Waarom belangrijk |
|---|---------|-------------------|
| 1.1 | **Modulebeheer (aan/uit per module)** | 115 modules laden standaard. Concurrenten bieden een moduleoverzicht. Het levert performance op (alleen actieve modules laden), een rustiger menu en minder conflicten met andere plugins. |
| 1.2 | **Schema-validatie in de editor** | Schema wordt gegenereerd maar niet gevalideerd. Verplichte velden per type (Product, Article, FAQ, LocalBusiness) live controleren, plus een link naar de Rich Results Test. Dubbel schema van theme of andere plugins detecteren. |
| 1.3 | **Core Web Vitals-monitoring over tijd** | PageSpeed bestaat als losse meting. Toevoegen: CrUX-velddata (echte gebruikers), een trend per template/URL-groep en een alert bij regressie. |
| 1.4 | **Google Business Profile-integratie** | Past bij de bestaande local SERP/geo-grid: GBP-posts vanuit WP (hergebruik van de AI-content), AI-antwoorden op reviews, NAP-consistentiecheck tussen site, schema en GBP. Lokale ondernemers zijn een kernmarkt. |
| 1.5 | **AI-vertaling met WPML/Polylang** | Hreflang/international bestaan, maar er is geen vertaalfunctie (TranslationHelper genereert alleen `.mo`-bestanden). Bouwen: posts plus SEO-meta vertalen in de merkstem en direct koppelen als vertaling. Groot voordeel in de NL/BE/DE-markt. |
| 1.6 | **Content-refresh-autopilot** | Content decay detecteert dalers maar doet er niets mee. Koppeling met ApexFlow: dalende posts automatisch inplannen voor herschrijven, met diff/review voor publicatie. |
| 1.7 | **Bing Webmaster Tools-koppeling** | ChatGPT Search en Copilot leunen op de Bing-index. IndexNow bestaat al, maar de rapportage niet: Bing-klikken/impressies en indexatiestatus. Dit is GEO-relevant en wordt door geen enkele concurrent goed ontsloten. |

---

## Fase 2 — Onderscheidend (de GEO-voorsprong uitbouwen)

| # | Feature | Waarom onderscheidend |
|---|---------|----------------------|
| 2.1 | **AI-crawlerbeheer + AI-botlogboek** | Er bestaat nu geen enkele regel voor GPTBot/ClaudeBot/PerplexityBot/Google-Extended in robots.txt. Bouwen: per AI-bot toestaan of blokkeren (training vs. search apart, bijvoorbeeld GPTBot vs. OAI-SearchBot), **plus logging welke AI-crawlers welke pagina's bezoeken** (lichte user-agent-logger met IP-verificatie). "ChatGPT heeft deze week 43 pagina's gecrawld, je prijzenpagina niet" is uniek en tastbaar. |
| 2.2 | **AI-referralverkeer en conversies** | GA4 is gekoppeld, maar AI-bronnen (chatgpt.com, perplexity.ai, gemini, copilot) worden niet apart getoond. Een dashboard "AI-verkeer" met landingspagina's en conversies (WooCommerce-omzet) bewijst de ROI van GEO. |
| 2.3 | **Citatie → pagina-koppeling** | LLM-tracker en brand visibility meten *of* je genoemd wordt. Toevoegen: *welke URL* geciteerd wordt, en voor prompts zonder citatie automatisch een contentbrief ("schrijf hierop een antwoordpagina") naar ApexFlow. Zo sluit de cirkel meten → actie → meten. |
| 2.4 | **WooCommerce-productfeed voor AI-shopping** | Google Merchant Center + OpenAI-productfeedformaat, AI-ready score per product en ontbrekende attributen aanvullen met AI. Zelfde concept als in de Shopify-app: één productlijn, één verhaal. |
| 2.5 | **MCP / WordPress Abilities API** | Fyndable-acties (audit, meta genereren, rank-data, GEO-scan) beschikbaar maken voor AI-agents (Claude, ChatGPT) via de Abilities API + MCP-adapter. Een vroege speler hier krijgt veel aandacht ("beheer je SEO vanuit Claude"). Er is al een `bs-bouw-elementor`-MCP-ervaring in huis. |
| 2.6 | **Entity/Knowledge Graph-builder** | Organisatie-entiteit centraal beheren (sameAs naar Wikidata/LinkedIn/KvK, oprichters, auteurs met E-E-A-T-profielen) en consistent in schema uitrollen. De E-E-A-T-validator controleert alleen. Dit bouwt actief de entiteit op die LLM's gebruiken. |
| 2.7 | **Proactieve wekelijkse AI-briefing** | Eén e-mail of dashboardkaart: "dit veranderde (rankings, AI-mentions, crawls, decay) en dit zijn de 3 acties met de meeste impact", met één-klik-uitvoering. Zet de grote hoeveelheid modules om in een duidelijk advies, wat het grootste UX-probleem van een plugin met 115 modules oplost. |

---

## Voorgestelde volgorde

1. **Sprint 1:** 0.1 Competitor-tabs, 0.2 disavow, 0.4 image-proxy (lopende schuld weg)
2. **Sprint 2:** 0.5 tests/CI, 1.1 modulebeheer
3. **Sprint 3–4:** 2.1 AI-crawlerbeheer + botlog, 2.2 AI-referralverkeer (snel zichtbaar onderscheid)
4. **Sprint 5:** 0.3 backlinkhistorie, 1.2 schema-validatie, 1.6 content-refresh-autopilot
5. **Sprint 6–7:** 2.3 citatie→pagina, 2.7 AI-briefing, 1.7 Bing
6. **Daarna:** 1.4 GBP, 1.5 AI-vertaling, 2.4 productfeed (samen met Shopify), 2.5 MCP, 2.6 entity-builder, 1.3 CWV

**Positionering:** Yoast en Rank Math optimaliseren voor Google. Fyndable laat zien *of en waar* je in AI-antwoorden staat, *welke AI-bots* je site lezen en *wat het oplevert*, en zet dat automatisch om in content. 2.1, 2.2 en 2.3 maken dat meetbaar. De rest van Fase 2 is daarop een verdieping.

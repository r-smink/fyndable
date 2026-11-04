# Fyndable Demo Tour — Voiceover Script

Nederlandse voiceover-teksten voor de begeleide salesdemo-tour van de Fyndable Demo-plugin.

## Instructies voor AI-stemgeneratie

- **Taal:** Nederlands (nl-NL), professionele maar vlotte toon. Denk: een consultant die een klant meeneemt, geen nieuwslezer.
- **Voorgestelde tools:** ElevenLabs (bijv. stem "Adam" of een Nederlandse professionele stem), OpenAI TTS, Play.ht of Azure Neural (`nl-NL-ColetteNeural` / `nl-NL-MaartenNeural` werken goed).
- **Snelheid:** ~0,95× — iets rustiger dan normaal, de kijker moet mee kunnen klikken.
- **Formaat:** MP3, 44.1 kHz, 128 kbps, mono of stereo.
- **Bestandsnamen:** exact zoals hieronder per stap vermeld. Plaats de bestanden in `wp-content/plugins/fyndable-demo/assets/audio/`. De tour speelt het bestand automatisch af bij de bijbehorende popup; ontbreekt een bestand, dan valt de tour geruisloos terug op tekst.
- **Timing:** autoplay wacht tot de voiceover klaar is (+2,5 s pauze) voordat de volgende stap verschijnt.

## Overzicht stappen

| # | Bestandsnaam | Scherm | Onderdeel |
|---|--------------|--------|-----------|
| 01 | `step-01-dashboard-score.mp3` | Dashboard | SEO-score & site-analyse |
| 02 | `step-02-dashboard-breakdown.mp3` | Dashboard | SEO Breakdown |
| 03 | `step-03-dashboard-issues.mp3` | Dashboard | Issues & Quick Wins |
| 04 | `step-04-keywords-toolbar.mp3` | Keywords | Werkbalk |
| 05 | `step-05-keywords-filters.mp3` | Keywords | Filter-tabs |
| 06 | `step-06-keywords-table.mp3` | Keywords | Zoekwoordentabel |
| 07 | `step-07-competitors-add.mp3` | Competitors | Concurrent toevoegen |
| 08 | `step-08-competitors-overview.mp3` | Competitors | Analyse-tabs |
| 09 | `step-09-clusters-generator.mp3` | Topic Clusters | Generator |
| 10 | `step-10-clusters-pillar.mp3` | Topic Clusters | Pillar & clusters |
| 11 | `step-11-clusters-generate.mp3` | Topic Clusters | Bulk-genereren |
| 12 | `step-12-calendar-grid.mp3` | Content Calendar | Kalender |
| 13 | `step-13-calendar-workflow.mp3` | Content Calendar | Gaps & workflow |
| 14 | `step-14-apexflow-profile.mp3` | ApexFlow | Site-profiel |
| 15 | `step-15-apexflow-schedule.mp3` | ApexFlow | Publicatie-instellingen |
| 16 | `step-16-apexflow-pool.mp3` | ApexFlow | Keyword pool & planning |
| 17 | `step-17-rank-national.mp3` | Rank Tracker | Landelijke posities |
| 18 | `step-18-rank-local.mp3` | Rank Tracker | Local SERP & cannibalization |
| 19 | `step-19-llm-config.mp3` | LLM Tracker | Merk & prompts |
| 20 | `step-20-llm-results.mp3` | LLM Tracker | Resultaten |
| 21 | `step-21-posts-filters.mp3` | Created Posts | Filters |
| 22 | `step-22-posts-table.mp3` | Created Posts | Overzicht & bulk-acties |

---

## Spreekteksten

### Stap 1 — `step-01-dashboard-score.mp3` — SEO-score & site-analyse
*Scherm: Dashboard → scorecirkel*

> Welkom in Fyndable. We beginnen bij de gezondheidscheck van de website. Met één klik op "Analyze Site" scant de plugin alle pagina's en posts en vertaalt dat naar een totale SEO-score. Groen betekent gezond, oranje vraagt aandacht, rood is kritiek. Deze demowebsite van Van Dijk Klimaattechniek scoort 74 — er is dus ruimte voor winst. Onder de score zie je hoeveel pagina's zijn meegenomen in de analyse.

### Stap 2 — `step-02-dashboard-breakdown.mp3` — SEO Breakdown
*Scherm: Dashboard → breakdown-balken*

> Naast de score zie je de breakdown: per SEO-onderdeel hoeveel pagina's al op orde zijn. Denk aan titels, meta descriptions, focus keyphrases, Open Graph-tags, interne links en contentlengte. Elke balk is een voortgangsmeter. De oranje balken zijn je snelste verbeterpunten — daar ligt de grootste relatieve winst voor de klant.

### Stap 3 — `step-03-dashboard-issues.mp3` — Issues & Quick Wins
*Scherm: Dashboard → issues & quick wins*

> Hieronder de concrete bevindingen. Links de issues: acht pagina's missen bijvoorbeeld een SEO-titel. Klik erop en je ziet precies welke pagina's. Rechts de quick wins — pagina's met goede content maar ontbrekende meta-data. Met de Bulk Optimizer los je die in minuten op. Perfecte openingscasus voor een demo: dit is precies het werk dat Fyndable geautomatiseerd oppakt.

### Stap 4 — `step-04-keywords-toolbar.mp3` — Zoekwoorden toevoegen & verkennen
*Scherm: Keywords → werkbalk*

> Dit is het centrale zoekwoordbeheer. Typ een zoekwoord in en voeg het toe, of laat AI via "Generate keywords" en de "Keywords explorer" nieuwe varianten voorstellen — compleet met zoekvolume en moeilijkheidsgraad. Probeer het gerust: voeg bijvoorbeeld "airco onderhoud" toe. In de demo blijft je invoer bewaard tot je de demo reset.

### Stap 5 — `step-05-keywords-filters.mp3` — Filter-tabs & snelle inzichten
*Scherm: Keywords → tabs*

> De tabs boven de tabel filteren je portfolio: alle zoekwoorden, alleen getrackte, gerangschikte, positieveranderingen en nieuwe rankings. De tellers geven in één blik de omvang. Sorteer op volume, difficulty of rank via de kolomkoppen, of filter op onderwerpgroep met de cluster-dropdown.

### Stap 6 — `step-06-keywords-table.mp3` — De zoekwoordentabel
*Scherm: Keywords → tabel*

> Elke regel combineert alles wat je nodig hebt om te prioriteren: zoekvolume, difficulty, cost-per-click, actuele positie, zoekintentie en SERP-concurrenten. De truc: veel volume plus lage difficulty is een ideale kans. Let op "subsidie warmtepomp 2026" — dertienhonderd zoekopdrachten per maand. Een perfecte case voor een informatief artikel.

### Stap 7 — `step-07-competitors-add.mp3` — Concurrent toevoegen
*Scherm: Competitors → invoerveld*

> Bij Competitor Research vul je het domein van een concurrent in en bouwt Fyndable een compleet profiel: zoekwoordstrategie, publicatietempo, AI-content-percentage, win-loss-analyse, backlinks en advertenties. Optioneel geef je een sitemap mee en vink je "Full site" aan om alle sub-sitemaps te volgen. De eerste analyse duurt enkele minuten; daarna staat de concurrent in je dashboard.

### Stap 8 — `step-08-competitors-overview.mp3` — Concurrentieprofiel & tabs
*Scherm: Competitors → overzicht + tabs*

> Per concurrent vergelijk je vijf dimensies via de tabs: Keyword Strategy, Content Calendar, Win/Loss, Backlink Strategy en Ad Copy. De Win/Loss-tab is de sterkste in een salesgesprek: je ziet zwart op wit waar de concurrent wint en waar jij wint. En de AI-content-detectie laat zien welke concurrenten massaal geautomatiseerd publiceren — een opening om met kwaliteit te winnen.

### Stap 9 — `step-09-clusters-generator.mp3` — Topic Cluster Generator
*Scherm: Topic Clusters → generator*

> Nu komt het strategische deel. De Topic Cluster Generator zet één kernthema om in een complete contentarchitectuur: een pillar page met hub- en ondersteunende pagina's. Voor Van Dijk is "klimaatbeheersing utrecht" het kernthema. Kies taal en diepte, audit eventueel je bestaande content, en klik "Generate Map". Hieronder staat al een opgeslagen voorbeeld.

### Stap 10 — `step-10-clusters-pillar.mp3` — Pillar page & clusters
*Scherm: Topic Clusters → geopende map*

> Dit is een gegenereerde clustermap. Bovenaan een complete gids — de pillar page — waar alle clusters naartoe linken. Eronder drie clusters met elk een hubpagina en ondersteunende artikelen, compleet met doelzoekwoord, woordenaantal en prioriteit. Dertien geplande pagina's, gespreid over drie maanden: een hele kwartaalplanning in één scherm.

### Stap 11 — `step-11-clusters-generate.mp3` — Reviewen & bulk-genereren
*Scherm: Topic Clusters → review-sectie*

> Van plan naar publicatie. Klik "Review All Pages", selecteer welke pagina's je wilt schrijven en klik "Generate Selected". De content wordt op de achtergrond via WP-Cron geschreven — je kunt de pagina gewoon sluiten. Met "Sync to Content Calendar" plan je de hele map automatisch op basis van startdatum en interval. De geschatte API-kosten zie je vooraf.

### Stap 12 — `step-12-calendar-grid.mp3` — Visuele publicatiekalender
*Scherm: Content Calendar → kalender*

> De contentkalender geeft een maandoverzicht van alles: gepubliceerd, gepland, concept en ter review. De kleurcode is intuïtief: groen is gepland of live, geel is concept, blauw wacht op review. Concepten en geplande posts sleep je eenvoudig naar een andere dag. Laat de klant gerust zelf even verslepen — dat maakt de planning meteen tastbaar.

### Stap 13 — `step-13-calendar-workflow.mp3` — Content gaps & workflow
*Scherm: Content Calendar → rechterkolom*

> Rechts zie je de operationele sturing. De Content Gap Calendar wijst dagen aan zonder content — één klik op "Fill Gap" vult ze. De vier statuskaarten tonen waar content in de pijplijn zit: drafts, pending review, gepland en gepubliceerd. En onder Pending Approvals keurt het team ingediende stukken goed of af. Volledig onderaan vind je de Keyword Opportunity Timeline met voorstellen op datum.

### Stap 14 — `step-14-apexflow-profile.mp3` — Site-profiel & context
*Scherm: ApexFlow → profielvelden*

> ApexFlow is de content-autopilot. Hij scant de site en bepaalt zelf niche, doelgroep en kernthema's. Hier stuur je dat bij: industry, doelgroep, business-context en eigen zoekwoorden die voorrang krijgen. Onderwerpen die je uitsluit, blijven buiten de deur. Hoe beter de context, hoe scherper de output — denk aan USP's, regio en no-go-onderwerpen.

### Stap 15 — `step-15-apexflow-schedule.mp3` — Publicatie-instellingen
*Scherm: ApexFlow → instellingen*

> Hier bepaal je ritme en veiligheid: het aantal posts per week — begrensd door de licentie — de publicatiedagen, het tijdstip en de modus. Auto-publish publiceert direct; draft-modus legt alles eerst in concepten ter review. Onze tip: start een week in draft-modus. Dat bouwt vertrouwen, daarna kan de autopilot volledig.

### Stap 16 — `step-16-apexflow-pool.mp3` — Keyword pool & geplande posts
*Scherm: ApexFlow → pool & planning*

> ApexFlow bouwt een keyword pool van maximaal twintig kandidaten uit SERP-data, volumes en lokale intentie — het pinnetje betekent lokaal zoekgedrag. Met "Preview Calendar" doe je een droge run; met "Reject" schuif je een keyword naar de uitsluitingen. Onderaan staat het plan: welke post, welke dag, welk zoekwoord. "Run ApexFlow Now" forceert meteen een run.

### Stap 17 — `step-17-rank-national.mp3` — Landelijke posities volgen
*Scherm: Rank Tracker → tabel*

> De Rank Tracker controleert dagelijks je Google-posities per zoekwoord, land en doelpagina. Groen omhoog betekent gestegen, rood omlaag betekent gezakt. Klik op een zoekwoord voor de dertigdaagse positiereeks. "airco installeren utrecht" ging hier van positie zeven naar vier — meetbaar bewijs dat het traject werkt. "Check All Now" forceert een verse meting.

### Stap 18 — `step-18-rank-local.mp3` — Local SERP & cannibalization
*Scherm: Rank Tracker → tabs*

> Twee extra tabbladen. Local SERP meet je positie in de Google Maps-pack via een geo-grid rond de vestiging — per meetpunt zie je of je bedrijf in de local pack staat. Voor een lokale dienstverlener als Van Dijk is dat dé eye-opener: zichtbaarheid verschilt echt per stadswijk. De Cannibalization-tab detecteert pagina's die onderling om hetzelfde zoekwoord strijden.

### Stap 19 — `step-19-llm-config.mp3` — Merk & prompts instellen
*Scherm: LLM Tracker → instellingen*

> Tot slot: de nieuwe vorm van vindbaarheid. De LLM Tracker vraagt ChatGPT, Perplexity, Gemini en Claude om aanbevelingen en meet of jouw merk wordt genoemd. Vul merknaam, categorie, producten en concurrenten in, pas de zoekopdrachten aan met placeholders en vink de platforms aan. Met "Run Scan Now" start je direct een meetronde.

### Stap 20 — `step-20-llm-results.mp3` — Visibility score & sentiment
*Scherm: LLM Tracker → resultaten*

> Na een scan zie je de Visibility Score: in hoeveel procent van de AI-antwoorden wordt je merk genoemd. Plus vermeldingen, gemiddelde positie en sentiment — positief of negatief. De platform-breakdown toont waar je zwak scoort, en per vermelding lees je het antwoordfragment. Koppel dit aan de GEO-scan en llms.txt, en je meet én verbetert AI-vindbaarheid in dezelfde plugin.

### Stap 21 — `step-21-posts-filters.mp3` — Filters & reviewstatus
*Scherm: Created Posts → filters*

> Alle door AI gemaakte content komt terecht in dit controlepaneel. Filter op zoekterm, cluster, woordenaantal, datumbereiken en status. De statuspills tonen met tellers hoeveel posts gereed zijn, aandacht vragen of in review zijn. Zo behoudt het team volledige grip op geautomatiseerde output.

### Stap 22 — `step-22-posts-table.mp3` — Overzicht & bulk-acties
*Scherm: Created Posts → tabel*

> En dit is het bewijs: elke post met afbeelding, status, edit- en reviewstatus. Selecteer posts voor bulk-acties — status wijzigen, reviewstatus aanpassen of verwijderen — of open een post om te bewerken of opnieuw te laten genereren. Fyndable schrijft, maar de mens blijft eindverantwoordelijk. Dat is de tour. Vragen? De demo blijft openstaan om zelf te verkennen.

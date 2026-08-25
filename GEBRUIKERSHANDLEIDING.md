# Fyndable Gebruikershandleiding

> Een praktische, uitgebreide handleiding voor het gebruik van de Fyndable client-plugin (v1.5.1). Deze gids loopt met je door elke huidige feature heen: wat het oplevert, hoe je het stap voor stap gebruikt, welke problemen je kunt tegenkomen en met welke prioriteit je ze aanpakt. Het SaaS-dashboard valt buiten het bereik van dit document.

---

## Inhoudsopgave

1. [Eerste keer opzetten](#1-eerste-keer-opzetten)
2. [Navigatie & dashboard](#2-navigatie--dashboard)
3. [Content maken & optimaliseren](#3-content-maken--optimaliseren)
4. [Ideeën, planning & content-decay](#4-ideeën-planning--content-decay)
5. [Keywords & onderzoek](#5-keywords--onderzoek)
6. [Link management](#6-link-management)
7. [Technische SEO](#7-technische-seo)
8. [Schema & gespecialiseerde SEO](#8-schema--gespecialiseerde-seo)
9. [Ranking, monitoring & rapportage](#9-ranking-monitoring--rapportage)
10. [Integraties](#10-integraties)
11. [Post-editor & SEO-meta-box](#11-post-editor--seo-meta-box)
12. [Instellingen, white-label & tools](#12-instellingen-white-label--tools)
13. [Bijlage A: Site Audit issue-referentie](#13-bijlage-a-site-audit-issue-referentie)
14. [Bijlage B: FAQ](#14-bijlage-b-faq)

---

## 1. Eerste keer opzetten

### Licentie activeren

**Wat doet het en wat levert het jou op?**  
De Connection-pagina koppelt je WordPress-site aan je Fyndable-licentie. Pas na activatie verschijnen de rest van de menu's en worden AI- en data-features ontsloten.

**Hoe gebruik je het? (stap-voor-stap)**

1. Log in als beheerder in WordPress.
2. Klik in het linker menu op **Fyndable → Connection**.
3. Vul het **Dashboard URL**-veld in, bijvoorbeeld `https://saas.jouwdomein.nl`.
4. Plak je **license key** in het daarvoor bestemde veld.
5. Klik op **Activate License**.
6. Wacht tot het groene vinkje verschijnt. Het menu wordt opnieuw opgebouwd.

**Voorbeeld**  
Je hebt een licentie gekregen met key `fynd_abc123`. Vul bij Dashboard URL `https://dashboard.jouwliveomgeving.nl` in en klik op activeren. Na 2-3 seconden verschijnen Dashboard, Keywords en Site Audit.

**Mogelijke problemen & oplossingen**

- **"Invalid license"**: controleer of de key geen spatie bevat en of het dashboard-URL correct is (inclusief `https://`).
- **"Could not connect"**: de client moet het SaaS-dashboard kunnen bereiken. Controleer of je server `wp_remote_get/post` mag uitvoeren en geen firewall het dashboard blokkeert.
- **Menu blijft leeg**: herlaad de pagina (Ctrl+F5) of deactiveer en activeer de licentie opnieuw.

**Prioriteit:** hoog — zonder actieve licentie werken de meeste tools niet.  
**Beschikbaar:** alle tiers.

### Onboarding Wizard

**Wat doet het en wat levert het jou op?**  
De wizard helpt je om binnen een paar minuten de basis van Fyndable correct in te stellen, zodat je niet handmatig door alle settings hoeft te waden.

**Hoe gebruik je het?**

1. Na activatie verschijnt automatisch de wizard. Klik **Start**.
2. Stap 1 — **Licentie**: controleer of je licentie actief en herkend is.
3. Stap 2 — **Basis-SEO**: vul title separators, sitenaam, social profielen en standaard Open Graph-afbeelding in.
4. Stap 3 — **Content types**: vink aan voor welke post types SEO-tools actief moeten zijn (meestal `post`, `page` en eventueel `product`).
5. Stap 4 — **Google-integraties**: koppel optioneel Search Console, GA4 of PageSpeed.
6. Klik **Finish**; je wordt naar het dashboard doorgestuurd.

**Voorbeeld**  
Voor een blog selecteer je alleen `post` en `page`. Voor een WooCommerce-winkel voeg je ook `product` toe, zodat product-schema en WooCommerce SEO-panelen beschikbaar zijn.

**Mogelijke problemen & oplossingen**

- **Wizard start niet op**: ga handmatig naar `/wp-admin/admin.php?page=ai-seo-onboarding`.
- **Google-koppeling lukt niet**: sla de wizard over en koppel later via **Integrations**.

**Prioriteit:** hoog — correcte basisinstellingen bepalen de kwaliteit van alle SEO-uitvoer.  
**Beschikbaar:** alle tiers.

### Licentietiers

| Tier | Prijsindicatie | Kenmerkend voor |
|------|----------------|-----------------|
| Free | Gratis | Basis SEO-tools, activatie vereist voor volledig menu |
| Starter | €9/maand | Kern SEO + Link Assistant, Redirect Manager, Alt-text, Rewriter |
| Professional | €29/maand | Uitgebreide SEO + AI-tools, GSC, Rank Tracker, Schema, Local SEO |
| Business | €79/maand | Volledige AI-content suite + Bulk Optimizer, Content Decay |
| Agency | €199/maand | Multi-site, white-label, Plagiarism Checker, SEO Revisions |
| Trial | 14 dagen | Volledige Professional-features, verloopt na 14 dagen |
| DEV | Intern | Alle features, onbeperkte API (uitsluitend voor ontwikkeling) |

**Tip:** twijfel je welke tier je hebt? Kijk in **Fyndable → Connection** of **Settings → License**.

---

## 2. Navigatie & dashboard

### Dashboard Shell

**Wat doet het en wat levert het jou op?**  
Fyndable vervangt de standaard WordPress-admin door een full-page dashboard met een linker sidebar. Hierdoor heb je alle SEO-tools binnen één omgeving.

**Hoe gebruik je het?**

1. Klik op **Fyndable** in het WordPress-menu om de shell te openen.
2. Klik in de linker sidebar door de menu's.
3. Wil je terug naar standaard WordPress? Klik rechtsboven op het **×**-icoon of open een pagina buiten Fyndable.

**Voorbeeld**  
Je bent bezig met een Site Audit en wilt tegelijk een post bewerken. Klik op ×, bewerk de post in WordPress en open daarna opnieuw Fyndable.

**Mogelijke problemen & oplossingen**

- **Sidebar is leeg**: je licentie is niet (meer) actief, of je tier heeft geen toegang tot dat menu. Controleer **Connection**.
- **Pagina laadt niet in het iframe**: sommige WordPress-pagina's weigeren iframe-loading vanwege X-Frame-Options. Open de pagina dan via de linkerbalk buiten de shell.

**Prioriteit:** laag — gebruikersgemak, geen directe SEO-impact.  
**Beschikbaar:** alle tiers.

### Beschikbare menu-items

| Menu | Beschrijving | Min. tier |
|------|--------------|-----------|
| Connection | Licentiestatus en dashboard-URL beheren | Free |
| Dashboard | Site-overzicht met SEO-health score | Free |
| Content Calendar | Redactionele kalender en workflow | Free |
| Ideas | AI-ideeën voor content | Free |
| Created Posts | Overzicht van AI-gegenereerde posts | Free |
| Keywords | Keyword-bibliotheek en -onderzoek | Free |
| Link Manager | Interne link-suggesties en wees pagina's | Free |
| Competitors | Concurrentieanalyse | Free |
| Sitemaps | Sitemap-overzicht, robots.txt en redirects | Free |
| Bulk Optimizer | Bulk AI-optimalisatie van meta-gegevens | Business+ |
| SEO Data | SE Ranking / Ahrefs dashboard | Free |
| LLM Tracker / AI Search Visibility | Merk-zichtbaarheid in AI-zoekmachines | Free |
| Topic Clusters | Topic-cluster analyse | Professional+ |
| Site Audit | Technische SEO-audit | Professional+ |
| Rank Tracker | Dagelijkse positietracking | Professional+ |
| Google Data | GSC + GA4 + Google Ads dashboard | Professional+ |
| A/B Testing | Test varianten van titels/content | Professional+ |
| AI Tools | Verzamelpagina AI-tools | Free |
| Integrations | Koppelingen met externe diensten | Free |
| Support | Supporttickets aanmaken en beheren | Free |
| Settings | Plugin-instellingen | Free |

**Tip:** menu's die grijs of onzichtbaar zijn, horen bij een hogere tier. Upgrade of start een Trial om ze te testen.

---

## 3. Content maken & optimaliseren

### TruSEO Score

**Wat doet het en wat levert het jou op?**  
TruSEO geeft je content een score van 0-100 op basis van titel, meta, koppenstructuur, keyworddichtheid, interne links en afbeeldingen. Het is je realtime SEO-checklist binnen de editor.

**Hoe gebruik je het?**

1. Open een post of pagina.
2. Scroll naar de Fyndable SEO-meta-box.
3. Klap de **SEO Score**-sectie open.
4. Lees de feedback: groen = goed, oranje = verbeterpunt, rood = belangrijk tekort.
5. Pas titel, meta of structuur aan.
6. Sla de post op en herhaal tot de score stabiel boven de 80 blijft.

**Voorbeeld**  
Je schrijft een artikel over "duurzame koffie". TruSEO meldt dat je focus-keyword nog niet in de H1 staat en dat de meta-description te lang is. Pas de H1 aan naar "Duurzame koffie: wat je moet weten" en kort de description in tot onder 160 tekens.

**Mogelijke problemen & oplossingen**

- **Score blijft laag ondanks goede content**: controleer of je een focus-keyword hebt ingevuld en of de koppenstructuur logisch is (H1 → H2 → H3).
- **Foutieve keyworddichtheid**: gebruik je keyword natuurlijk; Fyndable waarschuwt bij over-optimalisatie.

**Prioriteit:** middelhoog — helpt bij contentkwaliteit, maar is geen garantie voor rankings.  
**Beschikbaar:** alle tiers.

### Readability Score

**Wat doet het en wat levert het jou op?**  
Meet hoe makkelijk je tekst leest. Je krijgt Flesch Reading Ease, Flesch-Kincaid Grade Level en de Nederlandse Flesch-Douma-score. Daarnaast signaleert het lange zinnen, passive voice en overbodige tussenwoordjes.

**Hoe gebruik je het?**

1. Bewerk een post.
2. Open **Readability** in de meta-box.
3. Bekijk de score en de specifieke verbeterpunten.
4. Knip lange zinnen op, vervang passieve constructies en vermijd vakjargon.
5. Herhaal totdat de indicator groen wordt.

**Voorbeeld**  
Je tekst over "hypotheekadvies" scoort 35 (moeilijk). De tool geeft aan dat 40% van je zinnen langer is dan 20 woorden. Knip die zinnen in tweeën en gebruik meer opsommingen.

**Mogelijke problemen & oplossingen**

- **Score is té hoog**: voor gespecialiseerde B2B-content mag de tekst best wat lastiger zijn; richt je dan op heldere structuur in plaats van een kindertaalniveau.
- **Nederlandse tekst wordt als Engels geanalyseerd**: controleer in **Settings → General** of de site-taal correct staat ingesteld.

**Prioriteit:** middelhoog — leesbaarheid heeft indirect effect op bounce en rankings.  
**Beschikbaar:** alle tiers.

### Smart Tags

**Wat doet het en wat levert het jou op?**  
Genereert en beheert SEO-title, meta-description, focus keyword en Open Graph-tags. Je kunt dynamische variabelen gebruiken voor titles, zodat je nooit handmatig elke pagina hoeft in te vullen.

**Hoe gebruik je het?**

1. Open in de editor de **Smart Tags** accordion.
2. Klik **Generate** voor een AI-voorstel.
3. Bewerk title en description zodat ze aantrekkelijk én onder de 60/160 tekens blijven.
4. Klik **Update**.

**Voorbeeld**  
Voor een pagina "Over ons" genereert Fyndable: title "Over ons - [Bedrijfsnaam]" en description "Leer meer over [Bedrijfsnaam] en onze diensten." Je past dit aan naar "Over [Bedrijfsnaam]: jouw partner voor duurzame energie".

**Mogelijke problemen & oplossingen**

- **Title wordt afgekapt in Google**: houd title onder 60 tekens; Fyndable toont een preview.
- **Description niet uniek**: elke pagina moet een eigen description hebben. Gebruik de bulk-optimalisatie om dubbele descriptions op te sporen.

**Prioriteit:** hoog — titles en descriptions beïnvloeden CTR en rankings direct.  
**Beschikbaar:** alle tiers.

### LSI Keywords

**Wat doet het en wat levert het jou op?**  
LSI (Latent Semantic Indexing) keywords zijn gerelateerde termen en synoniemen. Door deze te verwerken, wordt je content semantischer en beter begrepen door zoekmachines.

**Hoe gebruik je het?**

1. Vul eerst een focus-keyword in bij Smart Tags of in het Keywords-paneel.
2. Open **Keywords** (LSI) in de meta-box.
3. Klik **Generate LSI Keywords**.
4. Selecteer 3-5 relevante termen en verwerk die natuurlijk in je tekst.

**Voorbeeld**  
Focus-keyword: "elektrische fiets". LSI-suggesties: "e-bike", "accucapaciteit", "actieradius", "fietsverzekering". Verwerk "accucapaciteit" in een kop over batterijduur.

**Mogelijke problemen & oplossingen**

- **Suggesties zijn te algemeen**: geef meer context op in het focus-keyword, bijv. "elektrische fiets kopen".
- **Keyword-stuffing**: gebruik niet alle suggesties; kies alleen termen die echt passen.

**Prioriteit:** middelhoog — versterkt de relevantie van je content.  
**Beschikbaar:** alle tiers.

### Content Optimizer

**Wat doet het en wat levert het jou op?**  
Analyseert de top-10 in Google voor een keyword en bouwt een topic-model. Vervolgens scoort je tekst (0-100) tegen dat model. Je ziet precies welke termen je mist, overgebruikt of juist goed hebt verwerkt.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → Content Optimizer** (of open het paneel in de editor).
2. Voer je focus-keyword in.
3. Klik **Analyze**. Fyndable haalt SERP-data op via de SaaS-proxy.
4. Bekijk de lijst met missing, overused en used terms.
5. Werk je tekst bij.
6. Klik **Re-analyze** om je score te verbeteren.

**Voorbeeld**  
Je target "planten in huis". Het model toont dat concurrenten "luchtzuiverende planten", "verzorging" en "lichtbehoefte" noemen, maar jij alleen "groen in huis". Voeg die onderwerpen toe.

**Mogelijke problemen & oplossingen**

- **Analyze geeft geen resultaat**: controleer of je licentie actief is en of de SaaS-dashboard URL bereikbaar is.
- **Score verbetert niet**: je gebruikt missende termen nog te weinig of te geforceerd; schrijf natuurlijke alinea's rond die termen.

**Prioriteit:** hoog — content die aansluit bij het topic-model scoort vaak beter.  
**Beschikbaar:** Professional+.

### Content Brief

**Wat doet het en wat levert het jou op?**  
Genereert een volledige SEO-brief op basis van topresultaten: structuur, koppen, vragen, entiteiten, gemiddelde lengte en interne-linkkansen. Handig om zelf te schrijven of uit te delen.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → Content Brief**.
2. Voer het doel-keyword in.
3. Klik **Generate Brief**.
4. Lees de brief: H1, H2's, benodigde woordenaantal en PAA-vragen.
5. Gebruik de brief als leidraad in de editor.

**Voorbeeld**  
Voor "duurzaam reizen" geeft de brief aan: target 1.800 woorden, behandel treinreizen, CO2-compensatie, slow travel en geef antwoord op "Wat is duurzaam reizen?".

**Mogelijke problemen & oplossingen**

- **Brief is te breed**: specificeer je keyword, bijv. "duurzaam reizen per trein".
- **AI haalt oude SERP-data op**: data wordt 7 dagen gecachet; klik **Refresh** als je twijfelt.

**Prioriteit:** middelhoog — helpt schrijvers en strategen, geen live SEO-impact.  
**Beschikbaar:** Professional+.

### AI Content Writer

**Wat doet het en wat levert het jou op?**  
Schrijft complete artikelen op basis van een keyword. Je kiest type (blog, how-to, review), toon, lengte en structuur. Fyndable voegt automatisch relevante interne links toe.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → Content Writer**.
2. Voer een keyword / topic in.
3. Kies artikeltype, toon (formeel, vriendelijk, zakelijk) en gewenst aantal woorden.
4. Klik **Generate**.
5. Controleer het concept op feiten, leesbaarheid en merkstem.
6. Publiceer of plan in.

**Voorbeeld**  
Je vraagt een 1.200-woorden artikel in vriendelijke toon over "klimaatneutraal ondernemen". Fyndable genereert een concept met H2's en interne links naar je duurzaamheids-pagina.

**Mogelijke problemen & oplossingen**

- **Output is te generiek**: voeg extra instructies toe, bijv. "noem specifiek onze dienst X".
- **Foutieve feiten**: AI kan hallucineren. Controleer alle cijfers, datums en claims.

**Prioriteit:** middelhoog — snel content produceren, maar altijd redigeren.  
**Beschikbaar:** Business+.

### Content Rewriter

**Wat doet het en wat levert het jou op?**  
Herschrijft bestaande tekst. Je kiest een modus: SEO-optimalisatie, leesbaarheid, uitschrijven, inkorten, parafraseren of toon veranderen.

**Hoe gebruik je het?**

1. Open een post of ga naar **AI Tools → Content Rewriter**.
2. Selecteer de tekst die je wilt herschrijven.
3. Kies de modus.
4. Klik **Rewrite**.
5. Plak de verbeterde tekst terug.

**Voorbeeld**  
Je hebt een alinea over "onze diensten" die lomp geschreven is. Je kiest "Improve readability"; de AI maakt de zinnen korter en actiever.

**Mogelijke problemen & oplossingen**

- **Betekenis is veranderd**: vergelijk altijd de originele en herschreven versie.
- **Output te kort**: kies "Expand" in plaats van "Rewrite".

**Prioriteit:** middelhoog — nuttig voor verversen van oude content.  
**Beschikbaar:** Starter+.

### AI Content Repurposer

**Wat doet het en wat levert het jou op?**  
Zet een blog om naar andere formaten: LinkedIn-post, nieuwsbrief, Instagram-caption of samenvatting.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → AI Repurposer**.
2. Selecteer een bestaande post of plak content.
3. Kies het doelformaat.
4. Klik **Repurpose**.
5. Bewerk tot het past bij het kanaal.

**Voorbeeld**  
Je hebt een 2.000-woorden gids over "zonnepanelen". Je maakt er een LinkedIn-post van 150 woorden met een call-to-action.

**Mogelijke problemen & oplossingen**

- **Te formeel voor social**: pas de toon aan in de instellingen of vraag expliciet om een "casual variant".

**Prioriteit:** laag — handig voor content-distributie, geen directe SEO.  
**Beschikbaar:** Business+.

### Bulk Optimizer

**Wat doet het en wat levert het jou op?**  
Scant je hele site op ontbrekende SEO-titles, meta-descriptions, Open Graph-tags en focus-keyphrases. Je kunt vervolgens per post of in bulk AI-gegenereerde meta-gegevens laten invullen.

**Hoe gebruik je het?**

1. Ga naar **Bulk Optimizer** (onder **AI Tools** of in het hoofdmenu, afhankelijk van je tier).
2. Klik **Scan Site**.
3. Wacht tot de scan alle gepubliceerde posts heeft geanalyseerd.
4. Bekijk de samenvatting: hoeveel posts missen title, description, OG of keyphrase.
5. Filter op een specifiek probleem (bijv. **Missing Title**).
6. Vink de velden aan die je wilt genereren: SEO Title, Meta Description, OG Title, OG Description, Focus Keyphrase.
7. Selecteer één of meer posts en klik **Generate for Selected**.
8. Controleer na afloop willekeurig enkele resultaten.

**Voorbeeld**  
Je hebt 80 blogposts die geen SEO-titles hebben. Je filtert op "Missing Title", selecteert alle 80 posts, vinkt **SEO Title** en **Meta Description** aan en start de bulk-generatie. De AI vult automatisch de velden in, gebaseerd op de titel en eerste 800 tekens van de content.

**Mogelijke problemen & oplossingen**

- **Generatie stopt halverwege**: het proces verwerkt één post per keer om quota te sparen. Herlaad de pagina en start opnieuw; reeds verwerkte posts blijven bewaard.
- **Output is niet relevant**: controleer of de focus-keyphrase correct is of pas de content van de post aan voor betere context.
- **Slimme labels in de postlijst tonen "Missing" terwijl je net hebt gegenereerd**: herlaad de pagina; de kolom-update vereist een refresh.

**Prioriteit:** middelhoog — scheelt enorm veel tijd bij grote sites, maar handmatige controle blijft nodig.  
**Beschikbaar:** Business+.

### Simple Content Generator

**Wat doet het en wat levert het jou op?**  
Snelle AI-generatie direct in de editor voor een alinea, intro of CTA. Je geeft context en een woordlimiet.

**Hoe gebruik je het?**

1. Open in de editor de **AI Content** accordion.
2. Beschrijf in het veld wat je nodig hebt.
3. Stel het gewenste aantal woorden in.
4. Klik **Generate**.
5. Plak het resultaat in je content.

**Voorbeeld**  
Je schrijft een pagina over "werkmethodes" en vraagt: "schrijf een 80-woorden intro over scrum". De AI levert een korte paragraaf.

**Mogelijke problemen & oplossingen**

- **Output is te lang of te kort**: pas de woordlimiet in het paneel aan.

**Prioriteit:** laag-middel — productiviteitstool, geen strategische SEO.  
**Beschikbaar:** alle tiers.

### E-E-A-T Validator

**Wat doet het en wat levert het jou op?**  
Controleert of je content voldoet aan Google's E-E-A-T richtlijnen: Experience, Expertise, Authoritativeness, Trustworthiness. Belangrijk voor YMYL-onderwerpen (gezondheid, financiën, juridisch).

**Hoe gebruik je het?**

1. Open in de editor de **E-E-A-T** accordion.
2. Klik **Analyze**.
3. Bekijk de scores per pijler.
4. Voeg auteursinformatie, bronvermelding, datums en bewijs toe waar dat nodig is.

**Voorbeeld**  
Je publiceert een artikel over "hypotheekrente". E-E-A-T signaleert dat er geen auteur of bronnen zijn. Voeg een auteur met financiële achtergrond toe en link naar De Nederlandsche Bank.

**Mogelijke problemen & oplossingen**

- **Score blijft laag**: zorg voor een uitgebreide auteur-bio, externe bronnen en up-to-date informatie.

**Prioriteit:** hoog — voor veel niches is dit inmiddels een rankingfactor.  
**Beschikbaar:** alle tiers.

### Plagiarism Checker

**Wat doet het en wat levert het jou op?**  
Controleert of tekst mogelijk gekopieerd is of sterk op AI-generatie lijkt. Geeft een originaliteitsscore.

**Hoe gebruik je het?**

1. Open in de editor de **Originality** accordion.
2. Klik **Check Originality**.
3. Bekijk de score en de aangeduide segmenten.
4. Herschrijf twijfelachtige passages.

**Voorbeeld**  
Je laat een AI-tekst lopen en ziet een score van 45%. De tool markeert alinea's die veel voorkomen op andere sites. Herschrijf die in je eigen woorden.

**Mogelijke problemen & oplossingen**

- **AI-gegenereerde content wordt als "unoriginal" aangemerkt**: gebruik de rewriter om de tekst menselijker te maken.

**Prioriteit:** laag — vooral relevant voor agencies en gevoelige sectoren.  
**Beschikbaar:** Agency.

### AI Image Generator

**Wat doet het en wat levert het jou op?**  
Genereert featured images, Open Graph-afbeeldingen of illustraties op basis van een prompt.

**Hoe gebruik je het?**

1. Open in de editor de **AI Image** accordion.
2. Kies het type afbeelding.
3. Voer een prompt in, bijv. "minimalistisch plat ontwerp van een elektrische fiets, mintgroene achtergrond".
4. Klik **Generate Image**.
5. Selecteer een resultaat en stel het in als featured image.

**Voorbeeld**  
Voor een artikel over "werkplezier" vraag je een afbeelding van "een vrolijk team dat op kantoor werkt, natuurlijk licht". Je gebruikt het als featured image.

**Mogelijke problemen & oplossingen**

- **Afbeeldingen genereren niet**: controleer in het dashboard of de image-API (OpenAI/DALL-E of Stability) is geconfigureerd.
- **Merkwending klopt niet**: voeg merktermen toe in de prompt of bewerk de afbeelding naderhand.

**Prioriteit:** laag-middel — afbeeldingen verbeteren CTR en social sharing.  
**Beschikbaar:** alle tiers (vereist geconfigureerde image-API).

### Image Alt Generator

**Wat doet het en wat levert het jou op?**  
Genereert beschrijvende alt-teksten voor afbeeldingen. Goed voor toegankelijkheid en image SEO.

**Hoe gebruik je het?**

1. Open de mediabibliotheek of een afbeelding in de editor.
2. Klap de **Alt Text** accordion open.
3. Klik **Generate Alt Text**.
4. Bewerk de tekst zodat deze beschrijvend en beknopt is.

**Voorbeeld**  
Afbeelding: foto van een rode fiets in een stadspark. Fyndable levert "rode stadsfiets geparkeerd in park". Pas eventueel aan naar "rode stadsfiets geparkeerd in het Vondelpark".

**Mogelijke problemen & oplossingen**

- **Alt-text is te algemeen**: voeg context toe zoals locatie of productnaam.
- **Keyword-stuffing in alt-text**: houd het natuurlijk; alt is eerst voor toegankelijkheid.

**Prioriteit:** middelhoog — alt-teksten helpen image search en toegankelijkheid.  
**Beschikbaar:** Starter+.

### Open Graph & Social Sharing

**Wat doet het en wat levert het jou op?**  
Bepalt hoe je pagina eruitziet als iemand de link deelt op Facebook, LinkedIn of Twitter/X (Open Graph). Social Sharing voegt eventueel share-knoppen toe op je pagina.

**Hoe gebruik je het?**

1. Open in de editor de **Social Preview** accordion.
2. Pas de Open Graph-title, -description en -image aan.
3. Sla de post op.
4. Test via de Facebook Sharing Debugger of LinkedIn Post Inspector.

**Voorbeeld**  
Voor je homepage gebruik je een OG-title "Fyndable - Slimme SEO voor groeiende bedrijven" en een OG-image met je logo.

**Mogelijke problemen & oplossingen**

- **Social preview toont oude afbeelding**: platforms cachen OG-data. Gebruik hun debugger om te verversen.
- **Image is te groot of te klein**: gebruik afbeeldingen van 1200×630 px voor de beste weergave.

**Prioriteit:** middelhoog — goede social previews verbeteren CTR en branding.  
**Beschikbaar:** alle tiers.

---

## 4. Ideeën, planning & content-decay

### Ideas

**Wat doet het en wat levert het jou op?**  
Genereert contentideeën op basis van een topic of keyword. Je kunt ideeën bewaren, bewerken, omzetten naar draft, outline of geplande post.

**Hoe gebruik je het?**

1. Ga naar **Ideas** in het menu.
2. Typ een topic of keyword, bijv. "duurzaam wonen".
3. Klik **Generate Ideas**.
4. Bekijk de lijst en klik het sterretje om ideeën op te slaan.
5. Klik bij een idee op **Convert to Draft** of **Schedule**.

**Voorbeeld**  
Topic: "huisdierverzorging". Fyndable levert ideeën als "Beste vachtborstel voor honden", "Hoe vaak moet je een kat ontwormen?" en "Hondenbrokken: natvoer versus droogvoer".

**Mogelijke problemen & oplossingen**

- **Ideeën zijn te breed**: geef een specifieker topic op.
- **AI geeft geen ideeën**: controleer of je licentiequota niet is bereikt.

**Prioriteit:** middelhoog — helpt bij contentplanning.  
**Beschikbaar:** alle tiers.

### Created Posts

**Wat doet het en wat levert het jou op?**  
Centraal overzicht van alle AI-gegenereerde posts, gesorteerd op status: concept, gepland, gepubliceerd, review nodig.

**Hoe gebruik je het?**

1. Ga naar **Created Posts**.
2. Gebruik filters om snel concepten of geplande posts te vinden.
3. Selecteer één of meer posts.
4. Kies **Bulk Publish**, **Bulk Draft** of **Regenerate**.

**Voorbeeld**  
Je hebt 10 concepten laten genereren. Je selecteert er 5, bewerkt ze snel en klikt **Bulk Publish** om ze in één keer live te zetten.

**Mogelijke problemen & oplossingen**

- **Post mist afbeeldingen**: voeg handmatig een featured image toe of gebruik AI Image Generator.
- **Bulk-actie mislukt**: herlaad de pagina en controleer of je voldoende rechten hebt.

**Prioriteit:** middelhoog — handig voor bulkbeheer van AI-content.  
**Beschikbaar:** alle tiers.

### Content Calendar

**Wat doet het en wat levert het jou op?**  
Een visuele kalender waarin je posts plant, toewijst aan teamleden en content-gaps signaleert.

**Hoe gebruik je het?**

1. Ga naar **Content Calendar**.
2. Klik op een datum om een nieuw idee of post toe te voegen.
3. Sleep een bestaande post naar een andere datum om te verplaatsen.
4. Open in de editor de **Workflow** accordion om status en toegewezen auteur in te stellen.

**Voorbeeld**  
Je plant drie artikelen voor volgende maand: een pillar op de 1ste, twee clusters op de 8ste en 15de. Je koppelt de artikelen aan je content-manager.

**Mogelijke problemen & oplossingen**

- **Kalender is leeg**: zorg dat er minimaal één post of idee bestaat.
- **Workflow-velden niet zichtbaar**: deze zijn alleen zichtbaar bij Business+ tier.

**Prioriteit:** middelhoog — vooral handig voor teams en redactionele planning.  
**Beschikbaar:** alle tiers.

### Content Decay Monitor

**Wat doet het en wat levert het jou op?**  
Detecteert pagina's die posities of verkeer verliezen. Het geeft per post een historisch overzicht en verversingsadvies.

**Hoe gebruik je het?**

1. Ga naar **Content Decay** (indien zichtbaar in je menu) of open **Position Trends** in de editor.
2. Sorteer op dalende posities of dalend verkeer.
3. Klik een post aan.
4. Lees het advies: bijv. "werk statistieken bij", "breid het topic uit" of "verbeter interne links".

**Voorbeeld**  
Je ziet dat je artikel "SEO-tips 2024" van positie 3 naar 12 is gezakt. Het advies is: update naar 2025, verwerk nieuwe trends en vervang oude screenshots.

**Mogelijke problemen & oplossingen**

- **Geen data**: koppel eerst Search Console of GA4 in **Integrations**.
- **Alle posts lijken te dalen**: controleer of er een algoritme-update of seizoenspatroon is.

**Prioriteit:** hoog — verversen van dalende content is vaak de snelste SEO-win.  
**Beschikbaar:** Business+.

---

## 5. Keywords & onderzoek

### Keywords-beheer

**Wat doet het en wat levert het jou op?**  
Centrale bibliotheek voor al je keywords. Je kunt handmatig toevoegen, importeren via CSV, exporteren, clusters maken en vanuit een keyword contentideeën genereren.

**Hoe gebruik je het?**

1. Ga naar **Keywords**.
2. Klik **Add Keyword**.
3. Voer het keyword in, optioneel een cluster en zoekvolume (indien bekend).
4. Klik **Save**.
5. Selecteer één of meer keywords en klik **Generate Ideas** voor content-voorstellen.

**Voorbeeld**  
Je voegt "elektrische fiets verzekering" toe met cluster "verzekeringen" en "autoverzekering" met cluster "autoverzekeringen". Je kunt nu per cluster filteren.

**Mogelijke problemen & oplossingen**

- **Keywords raken kwijt**: gebruik de export-functie regelmatig als backup.
- **Importeren mislukt**: zorg dat je CSV de kolommen `keyword` en optioneel `cluster` bevat.

**Prioriteit:** hoog — keywords vormen de basis van je contentstrategie.  
**Beschikbaar:** alle tiers.

### Keyword Explorer

**Wat doet het en wat levert het jou op?**  
Vergroot een seed-keyword met SERP-titelanalyse en groepeert gerelateerde termen op Jaccard-similariteit. Handig om nieuwe hoeken voor content te ontdekken.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → Keyword Explorer**.
2. Voer een breed keyword in, bijv. "planten".
3. Klik **Explore**.
4. Bekijk clusters zoals "kamerplanten", "planten verzorgen", "giftige planten".

**Voorbeeld**  
Seed: "fietsen". Clusters die verschijnen: "elektrische fietsen", "racefietsen", "fietsen onderhoud". Je kiest "fietsen onderhoud" voor een blogserie.

**Mogelijke problemen & oplossingen**

- **Resultaten lijken willekeurig**: gebruik een specifiekere seed of combineer met Keyword Difficulty.

**Prioriteit:** middelhoog — helpt bij topic-uitbreiding.  
**Beschikbaar:** Professional+.

### Keyword Difficulty

**Wat doet het en wat levert het jou op?**  
Berekent de kans dat jij kunt ranken voor een keyword, gebaseerd op jouw eigen site's authority, bestaande content en interne linkstructuur.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → Keyword Difficulty**.
2. Voer een keyword in.
3. Bekijk de gepersonaliseerde score en het advies.
4. Als de score laag is, focus je eerst op long-tail varianten.

**Voorbeeld**  
"Zorgverzekering" heeft een difficulty van 85 voor jouw kleine site. Het advies is om eerst "zorgverzekering vergelijken zzp" te targeten.

**Mogelijke problemen & oplossingen**

- **Score is altijd hoog**: bouw eerst authority door middelzware keywords te scoren.

**Prioriteit:** middelhoog — voorkomt tijdverspilling op te moeilijke keywords.  
**Beschikbaar:** Professional+.

### LSI Keywords

**Wat doet het en wat levert het jou op?**  
LSI-keywords zijn semantisch gerelateerde termen die je content breder en begrijpelijker maken voor zoekmachines. Zie ook de LSI Keywords-sectie in hoofdstuk 3.

**Hoe gebruik je het?**  
Open de **Keywords** accordion in de editor, genereer suggesties en verwerk 3-5 relevante termen.

**Prioriteit:** middelhoog.  
**Beschikbaar:** alle tiers.

### Topic Clusters

**Wat doet het en wat levert het jou op?**  
Genereert een pillar-cluster map: één hoofdtopic (pillar) met onderliggende artikelen (clusters). Helpt je om topische autoriteit op te bouwen.

**Hoe gebruik je het?**

1. Ga naar **Topic Clusters**.
2. Voer een hoofdtopic in, bijv. "duurzame energie".
3. Klik **Generate Cluster**.
4. Bekijk de voorgestelde pillar en clusters.
5. Maak de pillar-pagina eerst aan, daarna de cluster-artikelen, en link die naar de pillar.

**Voorbeeld**  
Pillar: "Alles over zonnepanelen". Clusters: "kosten zonnepanelen", "vergunning zonnepanelen", "opbrengst zonnepanelen", "onderhoud zonnepanelen".

**Mogelijke problemen & oplossingen**

- **Cluster-ideeën overlappen**: combineer of splits ze.
- **Te veel clusters**: begin met 3-5 clusters per pillar.

**Prioriteit:** hoog — clusters versterken je site's autoriteit voor een onderwerp.  
**Beschikbaar:** Professional+.

### SERP Competitor

**Wat doet het en wat levert het jou op?**  
Analyseert de top-10 in Google voor een keyword en vergelijkt die met jouw content. Je ziet topic-gaps, koppenstructuur en welke entiteiten concurrenten wel gebruiken.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → SERP Competitor**.
2. Voer een keyword in.
3. Klik **Analyze**.
4. Bekijk de heatmap: groen = jij hebt het onderwerp, rood = mist.
5. Werk de rode velden in je content bij.

**Voorbeeld**  
Voor "thuisbatterij kopen" ontbreken bij jou "subsidie", "levensduur" en "terugverdientijd". Je voegt aparte secties toe.

**Mogelijke problemen & oplossingen**

- **Geen concurrerende data**: controleer of het keyword in je land wordt gezocht en of de proxy verbinding werkt.

**Prioriteit:** hoog — directe input voor betere content.  
**Beschikbaar:** Professional+.

### Competitor Research

**Wat doet het en wat levert het jou op?**  
Diepgaande concurrentieanalyse: welke keywords een concurrent target, welke content ze publiceren, hun backlinkprofiel, advertentiekopieën en AI-gegenereerde content-detectie.

**Hoe gebruik je het?**

1. Ga naar **Competitors**.
2. Voer een concurrentiedomein in, bijv. `voorbeeld.nl`.
3. Klik **Analyze**.
4. Bekijk de tabbladen: Keywords, Content, Backlinks, Ads.

**Voorbeeld**  
Je analyseert `concurrentverzekeringen.nl` en ziet dat ze veel content hebben over "zorgverzekering voor studenten". Jij schrijft een beter, uitgebreider artikel over dat topic.

**Mogelijke problemen & oplossingen**

- **Data is verouderd**: concurrentiedata wordt gecachet; klik **Refresh**.
- **Externe API-data ontbreekt**: sommige metrics vereisen een Ahrefs- of Semrush-koppeling.

**Prioriteit:** middelhoog — strategisch inzicht, geen directe technische wijziging.  
**Beschikbaar:** Professional+.

---

## 6. Link management

### Link Manager / Smart Internal Linking

**Wat doet het en wat levert het jou op?**  
Detecteert wees-pagina's (orphans), scoort interne linkkansen en geeft geoptimaliseerde anchor-teksten. Goede interne linkstructuur verdeelt linkwaarde en helpt gebruikers en crawlers.

**Hoe gebruik je het?**

1. Ga naar **Link Manager**.
2. Bekijk het tabblad **Orphan Pages**.
3. Klik een pagina aan om link-suggesties te zien.
4. Klik **Add Link** of kopieer de anchor tekst en URL naar een geschikte post.

**Voorbeeld**  
Je ziet dat de pagina "Over ons" geen interne links heeft. Fyndable suggereert om in je homepage-intro te linken met anchor "lees meer over ons team".

**Mogelijke problemen & oplossingen**

- **Suggesties zijn irrelevant**: pas het bron-keyword aan of verfijn de content van de doelpagina.
- **Orphan pages zijn bewust losstaand**: sommige landingspagina's hoeven niet intern gelinkt te worden.

**Prioriteit:** hoog — interne links zijn cruciaal voor crawlbaarheid en autoriteit.  
**Beschikbaar:** alle tiers.

### Link Genius

**Wat doet het en wat levert het jou op?**  
Automatische interne linkregels: je bepaalt welke woorden of zinnen automatisch linken naar welke URL. Handig voor evergreen links zoals productcategorieën.

**Hoe gebruik je het?**

1. Ga naar **Link Genius**.
2. Schakel **Enable Link Genius** in.
3. Voeg een regel toe: Keyword(s) → Doel-URL.
4. Stel het maximum aantal links per pagina in (bijv. 3).
5. Sla op.

**Voorbeeld**  
Regel: "zonnepanelen" → `https://jouwsite.nl/zonnepanelen/`. Overal waar het woord voorkomt in content, wordt automatisch gelinkt, maximaal 3 keer per pagina.

**Mogelijke problemen & oplossingen**

- **Te veel automatische links**: verlaag het maximum of gebruik exact-match in plaats van partial-match.
- **Links breken na verwijdering doelpagina**: controleer regelmatig of de doel-URL's nog bestaan.

**Prioriteit:** middelhoog — handige automatisering, maar blijf monitoren.  
**Beschikbaar:** Starter+.

### Redirect Manager

**Wat doet het en wat levert het jou op?**  
Beheert 301- en 302-omleidingen. Hiermee voorkom je 404-fouten en behoud je linkwaarde bij verwijderde of verplaatste pagina's.

**Hoe gebruik je het?**

1. Ga naar **Sitemaps → Redirect Manager**.
2. Klik **Add Redirect**.
3. Vul de bron-URL in (oud pad zonder domein, bijv. `/oud-artikel`).
4. Vul de doel-URL in (bijv. `/nieuw-artikel`).
5. Kies **301** (permanent) of **302** (tijdelijk).
6. Sla op en test de redirect.

**Voorbeeld**  
Je verwijdert een oude post over "SEO-tips 2022". Je maakt een 301-redirect naar je nieuwe post "SEO-tips 2026".

**Mogelijke problemen & oplossingen**

- **Redirect werkt niet**: controleer of de bron-URL geen querystring heeft en of er geen conflict is met bestaande pagina's.
- **Redirect-loops**: zorg dat doel niet teruglinkt naar bron.

**Prioriteit:** hoog — gebroken links en 404's schaden gebruikerservaring en SEO.  
**Beschikbaar:** Starter+.

### Backlink Analyzer

**Wat doet het en wat levert het jou op?**  
Analyseert je backlinkprofiel via Ahrefs of Semrush. Je ziet domeinautoriteit, aantal verwijzende domeinen, toxische links en concurrentievergelijking.

**Hoe gebruik je het?**

1. Ga naar **SEO Data → Backlinks** of **AI Tools → Backlink Analyzer**.
2. Voer een domein in.
3. Klik **Analyze**.
4. Bekijk de metrics en exporteer de lijst.

**Voorbeeld**  
Je analyseert je eigen domein en ziet 120 verwijzende domeinen. Je merkt dat 5 links van spammy directories komen en noteert ze voor disavow.

**Mogelijke problemen & oplossingen**

- **API-key ontbreekt**: voeg je Ahrefs- of Semrush-key toe in **Integrations**.
- **Data verschilt met dashboard**: elke API gebruikt eigen index; vergelijk trends, niet absolute getallen.

**Prioriteit:** middelhoog — backlinks blijven belangrijk, maar focus eerst op content en techniek.  
**Beschikbaar:** Professional+.

### Advanced Backlinks

**Wat doet het en wat levert het jou op?**  
Geavanceerde backlinkanalyse: gebroken backlinks, concurrent backlink-doelen, anchor-text scoring en link-opportunities.

**Hoe gebruik je het?**

1. Ga naar **Advanced Backlinks**.
2. Voer een concurrent of je eigen domein in.
3. Bekijk de lijst met opportunities.
4. Exporteer een prospect-lijst en start outreach.

**Voorbeeld**  
Je ziet dat `concurrent.nl` een link heeft van een brancheblog waar jij ook een gastartikel zou kunnen plaatsen. Je noteert het contact en stuurt een pitch.

**Mogelijke problemen & oplossingen**

- **Opportunities zijn te algemeen**: filter op domeinautoriteit en relevantie.
- **Outreach levert niets op**: personaliseer je e-mail en bied waardevolle content aan.

**Prioriteit:** middelhoog — linkbuilding is waardevol, maar vraagt tijd.  
**Beschikbaar:** Professional+.

---

## 7. Technische SEO

### Technical SEO Auditor / Site Audit

**Wat doet het en wat levert het jou op?**  
De Technical SEO Auditor doorloopt je hele site en controleert zes pijlers: crawlability, crawl budget, URL-structuur, sitemap-health, robots.txt en server/CDN-prestaties. Je krijgt per pijler een score en een lijst met concrete acties.

**Hoe gebruik je het?**

1. Ga naar **Site Audit**.
2. Klik op **Run Full Technical Audit**.
3. Wacht tot de scan is afgerond (dit kan enkele minuten duren).
4. Bekijk de scores bovenaan: Crawlability, Performance, URL Structure en Sitemap Health.
5. Scroll naar de secties met waarschuwingen (oranje) en fouten (rood).
6. Klik **Fix** als er een knop beschikbaar is, of volg het advies onder **Recommendation**.
7. Pak de rode items als eerste aan.

**Voorbeeld**  
Je draait een audit en ziet dat de Sitemap Health-score 65 is. Onder Sitemap Issues staat "Unreachable URL: https://jouwsite.nl/verwijderde-pagina". Je verwijdert de URL uit de sitemap of maakt een redirect aan.

**Mogelijke problemen & oplossingen**

- **Audit blijft hangen**: grotere sites duren langer. Laat het tabblad open staan of draai de audit op een rustig moment.
- **Geen toegang tot robots.txt/sitemap**: controleer of `home_url()` correct is ingesteld en of er geen plugin de toegang blokkeert.
- **Crawl-budget is negatief**: dat gebeurt als er meer "wasted" pagina's zijn dan totale gepubliceerde pagina's. Corrigeer duplicate content en thin content.

**Prioriteit:** hoog — technische problemen kunnen crawlen en indexeren blokkeren.  
**Beschikbaar:** Professional+.

### TechChecker

**Wat doet het en wat levert het jou op?**  
Een snelle controletool voor één specifieke URL: HTTP-status, schema/JSON-LD, hreflang-tags, robots.txt-toegang en redirect chains.

**Hoe gebruik je het?**

1. Open de **Tech Checker** vanuit de Site Audit of via de meta-box.
2. Plak de URL die je wilt testen.
3. Klik **Check**.
4. Bekijk het rapport per controlepunt.

**Voorbeeld**  
Je wilt controleren of `https://jouwsite.nl/nl/blog/` correct redirect naar de Nederlandse taalvariant. TechChecker laat zien dat de hreflang `nl-nl` correct is ingesteld.

**Mogelijke problemen & oplossingen**

- **URL geeft 404 terwijl pagina bestaat**: controleer permalinks en eventuele redirect-conflicten.
- **Schema ontbreekt**: voeg schema toe via de **Schema** accordion in de editor.

**Prioriteit:** middelhoog — handig voor spot-checks en internationale sites.  
**Beschikbaar:** Professional+.

### XML Sitemap

**Wat doet het en wat levert het jou op?**  
Genereert automatisch een XML-sitemap met alle publieke URLs. Zoekmachines gebruiken deze als routekaart om je site te crawlen.

**Hoe gebruik je het?**

1. Ga naar **Sitemaps**.
2. Controleer of de hoofdsitemap actief is (meestal `https://jouwsite.nl/sitemap.xml`).
3. Kopieer de URL.
4. Indien gewenst, dien deze in via **Google Search Console → Sitemaps**.

**Voorbeeld**  
Na installatie genereert Fyndable `https://jouwsite.nl/sitemap.xml`. Je voegt deze URL toe aan GSC zodat Google je pagina's sneller vindt.

**Mogelijke problemen & oplossingen**

- **Sitemap is leeg**: controleer in **Settings → Post Types** welke post types zijn opgenomen.
- **Sitemap geeft 404**: reset de permalinks via **Instellingen → Permalinks**.

**Prioriteit:** hoog — zonder sitemap kan Google pagina's over het hoofd zien.  
**Beschikbaar:** alle tiers.

### Extended Sitemaps

**Wat doet het en wat levert het jou op?**  
Genereert aanvullende sitemaps: video, afbeeldingen, nieuws, RSS en auteur. Deze helpen zoekmachines om specifieke content beter te begrijpen.

**Hoe gebruik je het?**

1. Ga naar **Sitemaps**.
2. Schakel de gewenste extra sitemaps in.
3. Test de URL's (bijv. `https://jouwsite.nl/sitemap-video.xml`).
4. Dien de aparte URL's in bij Google Search Console.

**Voorbeeld**  
Je hebt veel video-content. Door de video-sitemap in te schakelen, kan Google je video's indexeren en rich snippets tonen.

**Mogelijke problemen & oplossingen**

- **Video-sitemap is leeg**: zorg dat er video's embedded zijn in gepubliceerde posts.
- **Nieuws-sitemap is niet toegestaan**: hiervoor moet je site zijn goedgekeurd door Google News.

**Prioriteit:** middelhoog — nuttig voor rich media, maar niet voor elke site essentieel.  
**Beschikbaar:** alle tiers.

### Robots.txt Editor

**Wat doet het en wat levert het jou op?**  
Bewerk `robots.txt` vanuit WordPress. Je bepaalt welke pagina's zoekmachines mogen crawlen en voegt de sitemap-URL toe.

**Hoe gebruik je het?**

1. Ga naar **Sitemaps → Robots.txt**.
2. Bekijk de huidige inhoud.
3. Voeg regels toe of pas ze aan, bijv. `Disallow: /wp-admin/`.
4. Zorg dat er een regel `Sitemap: https://jouwsite.nl/sitemap.xml` staat.
5. Klik **Save**.

**Voorbeeld**  
Je hebt een PDF-map die niet geïndexeerd hoeft te worden. Je voegt toe: `Disallow: /pdf/`. Je zorgt dat `Sitemap:` wel blijft staan.

**Mogelijke problemen & oplossingen**

- **Hele site wordt geblokkeerd**: pas op met `Disallow: /`. Test altijd met de URL `/robots.txt` en de robots.txt-tester in GSC.
- **Wijzigingen worden niet opgeslagen**: controleer of je serverbestandsrechten toestaan dat WordPress robots.txt serveert.

**Prioriteit:** hoog — verkeerde robots.txt kan je site volledig uit zoekmachines houden.  
**Beschikbaar:** alle tiers.

### Canonical URL

**Wat doet het en wat levert het jou op?**  
Geeft aan welke URL de voorkeursversie is van een pagina. Voorkomt duplicate-content-problemen bij paginering, filters en gelijke content.

**Hoe gebruik je het?**

1. Open in de editor de **Canonical URL** accordion.
2. Vul een custom canonical in als de standaard niet correct is (bijv. bij guest posts).
3. Laat het veld leeg om automatisch de huidige URL te gebruiken.
4. Sla op.

**Voorbeeld**  
Je hebt een artikel dat ook op een partnerwebsite staat. Je stelt de canonical in op de originele URL op jouw site.

**Mogelijke problemen & oplossingen**

- **Canonical verwijst naar verkeerde URL**: controleer of alle varianten (met/without trailing slash, www/non-www) goed staan.
- **Google negeert canonical**: canonical is een hint, geen commando; zorg dat de inhoud ook daadwerkelijk overeenkomt.

**Prioriteit:** hoog — essentieel voor duplicate-content.  
**Beschikbaar:** alle tiers.

### Hreflang

**Wat doet het en wat levert het jou op?**  
Voegt taal- en regiotags toe zodat zoekmachines de juiste taalvariant tonen. Ondersteunt WPML, Polylang, TranslatePress en handmatige mapping.

**Hoe gebruik je het?**

1. Open in de editor de **Hreflang** accordion.
2. Koppel de vertaalde versies of vul handmatig `x-default` en taalcodes in.
3. Sla op.
4. Test met een hreflang-testtool.

**Voorbeeld**  
Je hebt een pagina in het Nederlands (`nl-nl`), Engels (`en-gb`) en Duits (`de-de`). Je koppelt de drie varianten en stelt `x-default` in op de Engelse versie.

**Mogelijke problemen & oplossingen**

- **Hreflang-tags zijn inconsistent**: zorg dat elke taalvariant terugverwijst naar de anderen.
- **Verkeerde taalcodes**: gebruik `nl-nl`, niet alleen `nl`, tenzij je geen regiospecifieke variant hebt.

**Prioriteit:** middelhoog — belangrijk voor meertalige sites.  
**Beschikbaar:** alle tiers.

### International SEO

**Wat doet het en wat levert het jou op?**  
Extra tools voor internationaal SEO: land-specifiek keywordonderzoek, geo-SERP-analyse, currency/taal-optimalisatie en internationale link-suggesties.

**Hoe gebruik je het?**

1. Ga naar **AI Tools → International SEO**.
2. Kies een doelland en taal.
3. Voer een keyword in.
4. Bekijk zoekvolume, concurrentie en lokale aanbevelingen.

**Voorbeeld**  
Je verkoopt in Nederland en België. Je analyseert "zonnepanelen" voor `nl-be` en ontdekt dat Belgen vaker zoeken naar "premie zonnepanelen Vlaanderen".

**Mogelijke problemen & oplossingen**

- **Geen lokale data**: zorg dat je SE Ranking of Ahrefs is gekoppeld voor betrouwbaar volume.

**Prioriteit:** middelhoog — alleen relevant als je internationaal richt.  
**Beschikbaar:** Professional+.

### Breadcrumbs

**Wat doet het en wat levert het jou op?**  
Genereert frontend-kruimelpaden en JSON-LD BreadcrumbList schema. Breadcrumbs helpen gebruikers en zoekmachines met de hiërarchie van je site.

**Hoe gebruik je het?**

1. Ga naar **Settings → Breadcrumbs**.
2. Schakel breadcrumbs in.
3. Kies of je ze via een shortcode, widget of template-functie wilt tonen.
4. Test of het kruimelpad correct verschijnt.

**Voorbeeld**  
Je activeert breadcrumbs en voegt de shortcode `[fyndable_breadcrumbs]` toe bovenaan je single-post template.

**Mogelijke problemen & oplossingen**

- **Breadcrumbs tonen verkeerde structuur**: controleer je categorie- en paginahiërarchie.
- **Schema-fout**: gebruik de Google Rich Results Test om het BreadcrumbList schema te valideren.

**Prioriteit:** middelhoog — verbetert gebruikerservaring en structured data.  
**Beschikbaar:** alle tiers.

### 404 Monitor

**Wat doet het en wat levert het jou op?**  
Houdt 404-fouten bij die bezoekers en crawlers tegenkomen. Zo voorkom je verloren verkeer en een slechte gebruikerservaring.

**Hoe gebruik je het?**

1. Ga naar **Site Audit → 404 Monitor**.
2. Bekijk de lijst met gebroken URL's, gesorteerd op aantal hits.
3. Klik een URL aan.
4. Maak een 301-redirect naar de meest relevante pagina of repareer de interne link.

**Voorbeeld**  
Je ziet dat `/oude-dienst` 45 keer is aangeroepen. Je maakt een redirect naar `/diensten`.

**Mogelijke problemen & oplossingen**

- **404's door oude campagnes**: check de bron (referrer) om te zien of er externe links naar die URL verwijzen.
- **Monitor blijft leeg**: zorg dat de plugin logs bijhoudt; soms is er een conflict met caching-plugins.

**Prioriteit:** hoog — 404's veroorzaken verloren verkeer en frustratie.  
**Beschikbaar:** Professional+.

---

## 8. Schema & gespecialiseerde SEO

### Schema Markup Manager

**Wat doet het en wat levert het jou op?**  
Voegt gestructureerde data (JSON-LD) toe aan je pagina's. Zoekmachines gebruiken dit voor rich snippets: sterren, FAQ-uitklappers, carrousels en meer.

**Hoe gebruik je het?**

1. Open in de editor de **Schema** accordion.
2. Klik **Add Schema**.
3. Kies een type, bijv. Article, Product, LocalBusiness, Review.
4. Vul de verplichte velden in.
5. Klik **Validate** om te testen.
6. Sla de post op.

**Voorbeeld**  
Voor een blogpost kies je `Article`, vult auteur, publicatiedatum en uitgever in. Google kan dit tonen in de zoekresultaten met datum en auteur.

**Mogelijke problemen & oplossingen**

- **Fout in Google Rich Results Test**: controleer of alle verplichte velden correct zijn ingevuld.
- **Dubbel schema**: schakel conflicterende schema-plugins (Yoast, Rank Math) uit als je Fyndable als leidend schema wilt gebruiken.

**Prioriteit:** hoog — schema is een sterke ranking- en zichtbaarheidsfactor.  
**Beschikbaar:** Professional+.

### FAQ Schema

**Wat doet het en wat levert het jou op?**  
Markeert vragen en antwoorden zodat Google een FAQ-uitklapper in de SERP kan tonen.

**Hoe gebruik je het?**

1. Open de **FAQ Schema** accordion in de editor.
2. Klik **Add Question**.
3. Vul vraag en antwoord in.
4. Herhaal voor elke FAQ.
5. Sla op.

**Voorbeeld**  
Op een pagina over "zonnepanelen" voeg je toe: "Wat kosten zonnepanelen?" met antwoord. Google toont deze vraag mogelijk direct in de zoekresultaten.

**Mogelijke problemen & oplossingen**

- **FAQ wordt niet getoond**: Google beslist zelf of FAQ-uitklappers getoond worden; zorg dat de antwoorden objectief en beknopt zijn.
- **Meer dan één FAQ-block per pagina**: houdt het bij één block om schema-fouten te voorkomen.

**Prioriteit:** middelhoog — kan de CTR flink verhogen.  
**Beschikbaar:** Professional+.

### Video SEO

**Wat doet het en wat levert het jou op?**  
Voegt VideoObject schema en video-sitemap-ondersteuning toe, zodat video's beter vindbaar worden in Google Video en YouTube-koppelingen.

**Hoe gebruik je het?**

1. Zorg dat een video is geëmbed in een post.
2. Open **Video SEO** in de meta-box.
3. Vul titel, beschrijving, upload- en duur-informatie in.
4. Activeer de video-sitemap in **Sitemaps**.

**Voorbeeld**  
Je hebt een YouTube-video over "zonnepanelen plaatsen". Fyndable vult automatisch delen van het schema en voegt de video toe aan `sitemap-video.xml`.

**Mogelijke problemen & oplossingen**

- **Video wordt niet geïndexeerd**: zorg dat de video openbaar toegankelijk is en geen noindex heeft.

**Prioriteit:** middelhoog — belangrijk voor video-content.  
**Beschikbaar:** Professional+.

### Local SEO

**Wat doet het en wat levert het jou op?**  
Optimaliseert je site voor lokale zoekopdrachten: bedrijfsnaam, adres, telefoon, openingsuren en LocalBusiness schema.

**Hoe gebruik je het?**

1. Ga naar **Settings → Local SEO**.
2. Vul bedrijfsnaam, adres, telefoonnummer, e-mail, openingsuren en URL's in.
3. Kies je bedrijfstype (bijv. `LocalBusiness`, `Restaurant`, `Dentist`).
4. Sla op.
5. Open een post en voeg indien nodig **Local SEO** schema toe.

**Voorbeeld**  
Je hebt een tandartspraktijk in Rotterdam. Fyndable voegt LocalBusiness schema toe met adres, telefoon en openingsuren, waardoor je kans op de local pack toeneemt.

**Mogelijke problemen & oplossingen**

- **NAP is inconsistent**: zorg dat naam, adres en telefoon op elke pagina exact hetzelfde zijn.
- **Local pack blijft uit**: dit vraagt ook Google Business Profile-optimalisatie; Fyndable helpt aan de site-kant.

**Prioriteit:** hoog — voor lokale bedrijven is dit vaak de belangrijkste SEO-pijler.  
**Beschikbaar:** Professional+.

### WooCommerce SEO

**Wat doet het en wat levert het jou op?**  
Voegt product- en review-schema toe, optimaliseert productcategorieën en biedt SEO-tools specifiek voor WooCommerce.

**Hoe gebruik je het?**

1. Ga naar **Settings → Post Types** en zorg dat `product` is ingeschakeld.
2. Open een product in de editor.
3. Vul SEO-titel, meta-description en product-schema in.
4. Laat prijs, beschikbaarheid en SKU automatisch invullen.

**Voorbeeld**  
Je verkoopt een fiets. Fyndable voegt `Product` schema toe met naam, prijs, beschikbaarheid en aggregate rating, waardoor rich snippets in Google kunnen verschijnen.

**Mogelijke problemen & oplossingen**

- **Prijs klopt niet in Google**: Google moet je pagina opnieuw crawlen; vraag in Search Console een recrawl aan.
- **Schema-dubbelingen**: schakel schema van WooCommerce zelf of Rank Math uit.

**Prioriteit:** hoog — voor webshops is product-schema essentieel.  
**Beschikbaar:** Professional+.

---

## 9. Ranking, monitoring & rapportage

### Rank Tracker

**Wat doet het en wat levert het jou op?**  
Volgt dagelijks de posities van je belangrijkste keywords. Je ziet historische trends en eventuele positieschommelingen.

**Hoe gebruik je het?**

1. Ga naar **Rank Tracker**.
2. Klik **Add Keyword**.
3. Voer het keyword in en selecteer het land/apparaat.
4. Fyndable checkt dagelijks de positie.
5. Bekijk het trendoverzicht na een paar dagen.

**Voorbeeld**  
Je trackt "elektrische fiets kopen" voor Nederland op desktop. Na twee weken zie je dat je van positie 18 naar 12 bent gestegen.

**Mogelijke problemen & oplossingen**

- **Positie is 0**: het keyword is nog niet geïndexeerd of de ranking ligt buiten de top 100.
- **Data komt niet binnen**: er is een dagelijkse cronjob nodig; controleer of WordPress-cron werkt.

**Prioriteit:** middelhoog — meetbaarheid is belangrijk, maar laat je niet gek maken door dagelijkse fluctuaties.  
**Beschikbaar:** Professional+.

### AI Search Visibility / LLM Tracker

**Wat doet het en wat levert het jou op?**  
Meet hoe vaak en in welke context je merk, producten of concurrenten worden genoemd in antwoorden van AI-zoekmachines en chatbots zoals ChatGPT, Perplexity en Google Gemini. Je ziet brand presence, link presence, sentiment, positie en concurrentievergelijking.

**Hoe gebruik je het?**

1. Ga naar **AI Search Visibility** (ook wel **LLM Tracker** genoemd in het menu).
2. Vul je **brand name** in.
3. Vul **product names** en **concurrenten** in, elk op een aparte regel.
4. Vul je **categorie** in, bijv. "SEO software" of "zonnepanelen".
5. Pas de voorbeeldvragen aan of gebruik de standaard set met `{category}`-plaatsvervangers.
6. Kies de platforms die je wilt scannen (ChatGPT, Perplexity, Gemini).
7. Stel de frequentie in (handmatig of gepland).
8. Klik **Save Settings** en daarna **Run Scan**.
9. Bekijk de resultaten: wordt je merk genoemd, op welke positie, met welk sentiment, en welke concurrenten wel worden genoemd?

**Voorbeeld**  
Je hebt een SaaS-bedrijf "BrightSEO". Je vult in: categorie "SEO software", concurrenten "Ahrefs, SEMrush, Moz". Na de scan zie je dat BrightSEO in 2 van de 15 vragen wordt genoemd, meestal na concurrenten. Het advies is om meer top-of-funnel content te maken en backlinks van autoritaire bronnen te vergaren zodat AI-modellen je merk vaker noemen.

**Mogelijke problemen & oplossingen**

- **Scan geeft geen resultaten**: controleer of je brand name exact is ingevuld en of je voldoende AI-credits/quota hebt.
- **Resultaten lijken willekeurig**: AI-antwoorden zijn niet-deterministisch; gebruik meerdere scans en vergelijk trends.
- **Je merk wordt nooit genoemd**: dit is vaak een teken dat er nog weinig online autoriteit of content is; focus op brand building en PR.

**Prioriteit:** middelhoog — AI-zoekopdrachten worden steeds belangrijker, maar dit is een lange-termijn inzicht.  
**Beschikbaar:** alle tiers.

### SERP Feature Tracker

**Wat doet het en wat levert het jou op?**  
Laat zien in welke rich resultaten (featured snippets, PAA, video carrousels, local pack) je of je concurrenten verschijnen.

**Hoe gebruik je het?**

1. Ga naar **SERP Feature Tracker**.
2. Voer een keyword in.
3. Klik **Track**.
4. Bekijk welke SERP-features er zijn en wie ze bezit.

**Voorbeeld**  
Voor "beste zonnepanelen" zie je dat er een featured snippet is met een lijst. Jij optimaliseert je content met een genummerde lijst om die snippet te claimen.

**Mogelijke problemen & oplossingen**

- **Data is af en toe leeg**: SERP-features veranderen snel; gebruik de data als trend, niet als absoluut.

**Prioriteit:** middelhoog — kan richting geven voor snippet-optimalisatie.  
**Beschikbaar:** Professional+.

### Content Performance

**Wat doet het en wat levert het jou op?**  
Koppelt rankings aan verkeersdata uit Search Console en/of GA4. Je ziet welke content daalt, groeit of stilzit.

**Hoe gebruik je het?**

1. Koppel eerst **Search Console** en/of **GA4** in **Integrations**.
2. Ga naar **Content Performance**.
3. Filter op periode, auteur of post type.
4. Analyseer klikken, impressies, CTR en gemiddelde positie.

**Voorbeeld**  
Je ziet dat je artikel over "thuisbatterij" veel impressies maar weinig klikken heeft. Je past de title en description aan om de CTR te verhogen.

**Mogelijke problemen & oplossingen**

- **Geen data**: controleer of de Google-integratie correct is en of er voldoende historie is.
- **Cijfers komen niet overeen**: GSC- en GA4-metingen verschillen altijd; gebruik ze voor trends, niet exacte absolute waarden.

**Prioriteit:** middelhoog — inzicht in wat werkt, essentieel voor contentstrategie.  
**Beschikbaar:** Professional+.

### SEO Report Export

**Wat doet het en wat levert het jou op?**  
Exporteert SEO-rapporten naar PDF, CSV of HTML. Handig voor klantrapportages of interne reviews.

**Hoe gebruik je het?**

1. Ga naar **SEO Reports** (of via een **Export**-knop in een dashboard).
2. Selecteer een template of periode.
3. Klik **Generate**.
4. Download het rapport.

**Voorbeeld**  
Aan het einde van de maand genereer je een PDF met rankings, site-audit-score en nieuwe content. Je deelt deze met je team of klant.

**Mogelijke problemen & oplossingen**

- **PDF-generatie mislukt**: controleer of je server over voldoende geheugen beschikt.
- **Rapport is leeg**: selecteer een periode waarin er daadwerkelijk data is verzameld.

**Prioriteit:** laag — handig voor communicatie, geen directe SEO-waarde.  
**Beschikbaar:** Agency.

### SEO Revisions

**Wat doet het en wat levert het jou op?**  
Slaat snapshots van SEO-wijzigingen op (titles, meta, schema, canonical). Je kunt terug naar een eerdere versie of wijzigingen vergelijken.

**Hoe gebruik je het?**

1. Open een post.
2. Bewerk SEO-velden en sla op.
3. Ga naar **SEO Revisions**.
4. Klik een datum aan om de oude waarden te zien.
5. Klik **Restore** als je terug wilt.

**Voorbeeld**  
Je verandert een title en ziet na twee weken een daling. Je herstelt via SEO Revisions de oude title.

**Mogelijke problemen & oplossingen**

- **Geen revisies zichtbaar**: de feature moet ingeschakeld zijn in **Settings → SEO Revisions**.
- **Terugzetten werkt niet**: sommige velden vereisen een extra handmatige controle na restore.

**Prioriteit:** laag — handig voor troubleshooting en klantoverleg.  
**Beschikbaar:** Agency.

### A/B Testing (SEO)

**Wat doet het en wat levert het jou op?**  
Test varianten van titles of descriptions en meet welke variant meer klikken of betere posities oplevert.

**Hoe gebruik je het?**

1. Ga naar **A/B Testing**.
2. Kies een post en een element (title of description).
3. Maak variant A en variant B.
4. Stel de duur in (bijv. 30 dagen).
5. Start de test.
6. Na afloop bekijk je de winnaar en pas deze toe.

**Voorbeeld**  
Variant A: "De beste elektrische fietsen van 2025". Variant B: "Elektrische fietsen vergelijken 2025: top 5". Na 30 dagen blijkt variant B 15% meer CTR te hebben.

**Mogelijke problemen & oplossingen**

- **Test duurt te lang**: je hebt voldoende verkeer nodig voor statistische significantie; kleine sites hebben hier meer tijd voor nodig.
- **Resultaten zijn niet significant**: verleng de test of test op een post met meer verkeer.

**Prioriteit:** middelhoog — waardevol voor optimalisatie van CTR.  
**Beschikbaar:** Professional+.

---

## 10. Integraties

### External Integrations

**Wat doet het en wat levert het jou op?**  
Koppelt externe diensten zoals Google Search Console, GA4, Google Ads, PageSpeed Insights, SE Ranking, Ahrefs, IndexNow en de Google Indexing API. Deze data wordt gebruikt in dashboards en AI-analyses.

**Hoe gebruik je het?**

1. Ga naar **Integrations**.
2. Kies de gewenste dienst.
3. Volg de OAuth-stroom of voer een API-key in.
4. Sla de koppeling op.
5. Test in het bijbehorende dashboard of de data binnenkomt.

**Voorbeeld**  
Je koppelt Search Console. In **Google Data** zie je nu klikken, impressies en CTR per pagina.

**Mogelijke problemen & oplossingen**

- **OAuth laat geen juiste domein toe**: zorg dat je domein is toegevoegd in de Google Cloud OAuth-instellingen.
- **API-quota is op sommige features beperkt**: zie **Settings → License** voor je quotum.
- **Scope-fouten**: voor Direct Index heb je extra OAuth-scope `indexing` nodig; lees daarvoor de meldingen op het scherm.

**Prioriteit:** middelhoog tot hoog (afhankelijk van integratie) — GSC en GA4 zijn essentieel voor inzicht.  
**Beschikbaar:** Professional+ voor de meeste integraties; PageSpeed en IndexNow zijn vaak al in lagere tiers beschikbaar.

### Google Search Console

**Wat doet het en wat levert het jou op?**  
Haalt prestatiedata op uit GSC: queries, pagina's, CTR, posities en indexstatus.

**Hoe gebruik je het?**

1. Ga naar **Integrations → Google Search Console**.
2. Klik **Connect with Google**.
3. Kies het juiste property.
4. Open **Google Data → Search Console**.

**Mogelijke problemen & oplossingen**

- **Property niet zichtbaar**: zorg dat je site is geverifieerd in GSC en dat je met een beheerdersaccount inlogt.
- **Data is 2-3 dagen achter**: dit is normaal voor GSC.

**Prioriteit:** hoog — zonder GSC-data kun je SEO nauwelijks meten.  
**Beschikbaar:** Professional+.

### Google Data / SEO Data

**Wat doet het en wat levert het jou op?**  
Combineert data van GSC, GA4, Google Ads, SE Ranking en Ahrefs in één dashboard.

**Hoe gebruik je het?**

1. Koppel de gewenste diensten in **Integrations**.
2. Ga naar **Google Data** of **SEO Data**.
3. Kies periode en metrics.
4. Bekijk de grafieken en tabellen.

**Mogelijke problemen & oplossingen**

- **Sommige widgets blijven leeg**: controleer of de desbetreffende API-koppeling actief is.
- **Data is inconsistent**: dashboards cachen data; forceer een refresh via de knop in de widget.

**Prioriteit:** middelhoog — essentieel voor rapportage, minder voor directe optimalisatie.  
**Beschikbaar:** Professional+.

### IndexNow

**Wat doet het en wat levert het jou op?**  
Stuurt automatisch zoekmachines (Bing, Yandex) een seintje wanneer je content wijzigt, zodat je sneller geïndexeerd wordt.

**Hoe gebruik je het?**

1. Ga naar **Integrations → IndexNow**.
2. Schakel **Enable IndexNow** in.
3. Genereer of upload je API-key.
4. Sla op.
5. Fyndable pingt nu automatisch bij publicatie en update.

**Mogelijke problemen & oplossingen**

- **Key-bestand niet aangemaakt**: Fyndable probeert automatisch `indexnow-key.txt` aan te maken; controleer of de uploads-map schrijfbaar is.
- **Bing toont geen wijzigingen**: IndexNow is geen garantie; het duurt soms enkele uren tot dagen.

**Prioriteit:** middelhoog — snellere indexering, maar voorrang voor Google Indexing API als je die hebt.  
**Beschikbaar:** alle tiers.

### Direct Index (Google Indexing API)

**Wat doet het en wat levert het jou op?**  
Vraagt direct bij Google indexering aan voor gepubliceerde of bijgewerkte posts. Dit gaat via de Google Indexing API en je Google OAuth-token.

**Hoe gebruik je het?**

1. Ga naar **Integrations → Direct Index**.
2. Koppel je Google-account met de juiste OAuth-scope (`https://www.googleapis.com/auth/indexing`).
3. Open een gepubliceerde post in de editor.
4. Klik in de meta-box op **Submit to Google**.
5. Je ziet een succes- of foutmelding.

**Voorbeeld**  
Je publiceert een nieuw artikel. Na publicatie klik je **Submit to Google**. Binnen enkele minuten/uren verschijnt de URL in de GSC-indexstatus.

**Mogelijke problemen & oplossingen**

- **"Insufficient Permission"**: de OAuth-scope voor indexing ontbreekt. Koppel je account opnieuw en accepteer de extra permissie.
- **"URL not allowed"**: de URL is geen gepubliceerde post of pagina; bijlagen en concepten worden genegeerd.
- **Quota overschreden**: het dagelijkse quotum voor de Indexing API is beperkt; prioriteer belangrijke posts.
- **Knop is niet zichtbaar**: alleen voor gepubliceerde content en als Direct Index correct is geconfigureerd.

**Prioriteit:** hoog — versnelt indexering van nieuwe en geüpdatete content.  
**Beschikbaar:** Professional+.

### PageSpeed Insights

**Wat doet het en wat levert het jou op?**  
Meet Core Web Vitals en prestatiescores voor mobiel en desktop. Fyndable slaat de resultaten op zodat je trends kunt volgen.

**Hoe gebruik je het?**

1. Ga naar **Integrations → PageSpeed**.
2. Voer een URL in.
3. Klik **Analyze**.
4. Bekijk de scores en aanbevelingen.

**Voorbeeld**  
Je homepage scoort 45 op mobiel. PageSpeed adviseert afbeeldingen te optimaliseren en ongebruikte JavaScript uit te stellen.

**Mogelijke problemen & oplossingen**

- **API-quota is op**: PageSpeed heeft een quotum; probeer het later opnieuw.
- **Scores schommelen**: prestatie hangt af van netwerk en serverbelasting; meet meerdere keren.

**Prioriteit:** hoog — Core Web Vitals zijn een rankingfactor en beïnvloeden conversie.  
**Beschikbaar:** alle tiers.

### SE Ranking / Ahrefs

**Wat doet het en wat levert het jou op?**  
Haalt keyworddata, backlinks en concurrentie-inzichten op uit externe SEO-tools.

**Hoe gebruik je het?**

1. Ga naar **Integrations**.
2. Voer je API-key van SE Ranking of Ahrefs in.
3. Sla op.
4. Gebruik de data in **Keywords**, **Competitors** en **Google Data**.

**Mogelijke problemen & oplossingen**

- **API-key ongeldig**: controleer of je de juiste key hebt (niet de login-wachtwoord).
- **Geen data voor Nederland/België**: controleer in de tool zelf of je abonnement het juiste land ondersteunt.

**Prioriteit:** middelhoog — verrijkt data, maar niet verplicht als je GSC hebt.  
**Beschikbaar:** Professional+.

---

## 11. Post-editor & SEO-meta-box

### Editor Assistant

**Wat doet het en wat levert het jou op?**  
Een Gutenberg-zijbalk met AI-acties: genereer een title, herschrijf een alinea, maak een samenvatting, pas toon aan en genereer afbeeldingen.

**Hoe gebruik je het?**

1. Open een post in de Gutenberg-editor.
2. Klik rechtsboven op het Fyndable-pictogram.
3. Kies een actie, bijv. **Generate Conclusion**.
4. Bekijk het resultaat en klik **Insert**.

**Voorbeeld**  
Je schrijft een artikel maar weet niet hoe je moet afsluiten. Je vraagt de Editor Assistant om een 100-woorden conclusie met call-to-action.

**Mogelijke problemen & oplossingen**

- **Zijbalk verschijnt niet**: zorg dat Gutenberg actief is (de plugin werkt niet met de klassieke editor).
- **Actie levert geen output**: controleer of je voldoende AI-credits hebt.

**Prioriteit:** middelhoog — verhoogt schrijfsnelheid.  
**Beschikbaar:** Professional+.

### Unified SEO Meta-box

**Wat doet het en wat levert het jou op?**  
De Fyndable Post Meta Box vervangt losse meta-boxen door één overzichtelijk accordion-paneel. Hierin zitten Smart Tags, TruSEO, Readability, Schema, Hreflang, Canonical, EEAT, Links, LSI en meer.

**Hoe gebruik je het?**

1. Open een post of pagina.
2. Scroll naar de Fyndable meta-box onder de editor.
3. Klap de gewenste sectie open.
4. Bewerk en sla op.

**Voorbeeld**  
In één meta-box vul je title, description, focus keyword, canonical en FAQ schema in. Je hoeft niet meer tussen schermen te wisselen.

**Mogelijke problemen & oplossingen**

- **Meta-box is niet zichtbaar**: controleer **Settings → Post Types** of het huidige post type is ingeschakeld.
- **Accordion wilt niet openen**: herlaad de pagina en controleer browserconsole op JavaScript-fouten.

**Prioriteit:** hoog — de meta-box is de dagelijkse werkplek voor SEO per pagina.  
**Beschikbaar:** alle tiers.

### Post-level History & Analytics

**Wat doet het en wat levert het jou op?**  
Toont per post historische data: posities, klikken, impressies en score-trends. Handig om te zien of wijzigingen effect hebben.

**Hoe gebruik je het?**

1. Open een post.
2. Klap in de meta-box de **History** of **Analytics** sectie open.
3. Bekijk de grafieken.

**Voorbeeld**  
Je past de title aan en ziet twee weken later een stijging in CTR van 2,1% naar 3,4%.

**Mogelijke problemen & oplossingen**

- **Geen historie**: data wordt pas bijgehouden nadat GSC is gekoppeld en er voldoende tijd is verstreken.

**Prioriteit:** middelhoog — helpt bij iteratief optimaliseren.  
**Beschikbaar:** Professional+.

---

## 12. Instellingen, white-label & tools

### Settings

**Wat doet het en wat levert het jou op?**  
Centrale plek voor alle plugin-instellingen: sitenaam, separators, post types, schema-defaults, sitemap, social en geavanceerde opties.

**Hoe gebruik je het?**

1. Ga naar **Settings**.
2. Doorloop de tabbladen: General, Post Types, Social, Sitemap, Advanced.
3. Pas de instellingen aan.
4. Klik **Save**.

**Voorbeeld**  
Je stelt in dat alleen `post`, `page` en `product` SEO-tools tonen. Zo blijft de editor overzichtelijk.

**Mogelijke problemen & oplossingen**

- **Wijzigingen worden niet opgeslagen**: controleer of je voldoende rechten hebt en er geen beveiligingsplugin de request blokkeert.
- **Instellingen conflicteren met andere SEO-plugin**: gebruik slechts één SEO-plugin om schema, sitemap en redirects te beheren.

**Prioriteit:** middelhoog — basisconfiguratie beïnvloedt alle pagina's.  
**Beschikbaar:** alle tiers.

### White-label / Fyndable Login

**Wat doet het en wat levert het jou op?**  
Pas het login-scherm en het menu aan met je eigen logo, kleuren en tekst. Handig voor agencies die de plugin onder eigen merk willen aanbieden.

**Hoe gebruik je het?**

1. Ga naar **Settings → White Label**.
2. Upload je logo.
3. Pas de kleuren en teksten aan.
4. Sla op.
5. Log uit en in om het resultaat te zien.

**Voorbeeld**  
Je verkoopt SEO-pakketten onder je eigen merk. Je zet het logo van je bureau op het Fyndable-dashboard en het WordPress-login-scherm.

**Mogelijke problemen & oplossingen**

- **Logo wordt niet getoond**: controleer of het bestand niet te groot is (max. 2 MB) en of het pad correct is.
- **Menu-namen blijven staan**: leeg het browsercache en herlaad.

**Prioriteit:** laag — branding, geen SEO-impact.  
**Beschikbaar:** Agency.

### SEO Importer

**Wat doet het en wat levert het jou op?**  
Importeert meta-gegevens uit andere SEO-plugins zoals Yoast, Rank Math of All in One SEO. Zo hoef je niet alles handmatig over te zetten.

**Hoe gebruik je het?**

1. Ga naar **Settings → Importer**.
2. Kies de bron-plugin.
3. Klik **Import**.
4. Controleer na afloop willekeurig enkele posts.

**Voorbeeld**  
Je hebt Yoast gebruikt. Fyndable kopieert titles, descriptions, focus keywords en canonicals over naar de Fyndable-meta-velden.

**Mogelijke problemen & oplossingen**

- **Sommige velden ontbreken**: niet alle plugins slaan dezelfde velden op; vul na import de rest handmatig aan.
- **Import loopt vast**: importeer in batches bij grote sites.

**Prioriteit:** middelhoog — scheelt veel tijd bij migratie.  
**Beschikbaar:** alle tiers.

### Role-based Permissions

**Wat doet het en wat levert het jou op?**  
Bepaalt welke WordPress-rollen toegang hebben tot welke Fyndable-features. Zo voorkom je dat auteurs per ongeluk belangrijke instellingen wijzigen.

**Hoe gebruik je het?**

1. Ga naar **Settings → Permissions**.
2. Selecteer een rol (bijv. Editor, Author).
3. Vink aan welke menu's en acties ze mogen gebruiken.
4. Sla op.

**Voorbeeld**  
Je geeft auteurs toegang tot de SEO-meta-box, maar niet tot Bulk Optimizer en Site Audit.

**Mogelijke problemen & oplossingen**

- **Gebruiker ziet menu ondanks restrictie**: controleer of de gebruiker meerdere rollen heeft of een caching-plugin ingrijpt.

**Prioriteit:** middelhoog — belangrijk voor governance in grotere teams.  
**Beschikbaar:** Professional+.

### Support Tickets

**Wat doet het en wat levert het jou op?**  
Maakt supporttickets aan binnen Fyndable. Je kunt een onderwerp, prioriteit en beschrijving invoeren en historie volgen.

**Hoe gebruik je het?**

1. Ga naar **Support**.
2. Klik **New Ticket**.
3. Vul onderwerp, prioriteit en beschrijving in.
4. Klik **Submit**.

**Voorbeeld**  
Je merkt dat de Site Audit niet afdraait. Je maakt een ticket aan met prioriteit **High** en voegt screenshots toe.

**Mogelijke problemen & oplossingen**

- **Ticket wordt niet verstuurd**: controleer of je licentie actief is en of de support-API bereikbaar is.
- **Geen reactie**: controleer je spamfolder of het ticketnummer.

**Prioriteit:** laag — ondersteuning, geen SEO-impact.  
**Beschikbaar:** alle tiers.

---

## 13. Bijlage A: Site Audit issue-referentie

De Site Audit controleert zes gebieden. Hieronder vind je de meest voorkomende problemen, hun prioriteit en een concrete oplossing. Pak bij voorkeur eerst de **hoge** prioriteit items aan.

### Legenda

- **Hoge prioriteit** — kan crawlen, indexeren of zichtbaarheid blokkeren.
- **Middelhoge prioriteit** — beïnvloedt rankings en gebruikerservaring op termijn.
- **Lage prioriteit** — verbetering, maar geen directe dring.

### Crawlability

| Probleem | Prioriteit | Wat je doet |
|----------|------------|-------------|
| Robots.txt niet toegankelijk | Hoog | Maak een `robots.txt` aan in de root of gebruik de Robots.txt Editor. |
| XML-sitemap niet toegankelijk | Hoog | Controleer de sitemap-URL, reset permalinks, controleer servertoegang. |
| Redirect chains | Middel | Herstel lange redirect-ketens; gebruik maximaal één 301. |
| Orphan pages | Middel | Voeg interne links toe vanuit gerelateerde content. |
| Te diepe pagina's (>3 klikken) | Middel | Verbeter de navigatiehiërarchie of voeg links toe vanaf de homepage. |

### Crawl Budget

| Probleem | Prioriteit | Wat je doet |
|----------|------------|-------------|
| Duplicate content | Hoog | Voeg canonicals toe of gebruik noindex. |
| Thin content (<300 tekens) | Hoog | Breid content uit of zet pagina's op noindex. |
| Excessive pagination | Middel | Overweeg "load more" of infinite scroll. |
| Negatief crawl budget | Hoog | Corrigeer duplicate/thin content zodat wasted budget realistisch is. |

### URL Structure

| Probleem | Prioriteit | Wat je doet |
|----------|------------|-------------|
| Lange URLs (>75 tekens) | Middel | Verkort de slug tot de kern. |
| Speciale tekens in URLs | Middel | Gebruik alleen lowercase letters, cijfers en koppeltekens. |
| Stopwoorden in URLs | Laag | Verwijder woorden als "de", "het", "een" uit de slug. |

### Sitemap Health

| Probleem | Prioriteit | Wat je doet |
|----------|------------|-------------|
| Sitemap onbereikbaar | Hoog | Controleer sitemap-URL, permalinks en server. |
| Sub-sitemap fout | Hoog | Controleer de betreffende sub-sitemap op XML-fouten. |
| Onbereikbare URL in sitemap | Hoog | Verwijder de URL uit de sitemap of maak een redirect. |
| Ontbrekende URLs (>20%) | Middel | Controleer welke post types/categorieën ontbreken en voeg toe. |
| XML-parseerfout | Hoog | Repareer het XML-bestand of hergenereer de sitemap. |

### Robots.txt

| Probleem | Prioriteit | Wat je doet |
|----------|------------|-------------|
| Robots.txt ontbreekt | Hoog | Maak een robots.txt aan met sitemap-verwijzing. |
| Geen sitemap-verwijzing | Middel | Voeg `Sitemap: https://jouwsite.nl/sitemap.xml` toe. |
| `Disallow: /` blokkeert alles | Hoog | Verwijder of pas deze regel aan. |
| `Crawl-delay` aanwezig | Laag | Verwijder Crawl-delay; Google negeert dit. |

### Performance

| Probleem | Prioriteit | Wat je doet |
|----------|------------|-------------|
| Server response time >500ms | Hoog | Optimaliseer hosting, caching of database. |
| Geen CDN | Middel | Overweeg Cloudflare of een vergelijkbare CDN. |
| Geen compressie (gzip/brotli) | Hoog | Schakel compressie in bij je hosting of via .htaccess/nginx. |
| Geen browser caching | Middel | Voeg cache-control headers toe. |

---

## 14. Bijlage B: FAQ

**V: Kan ik Fyndable gebruiken naast Yoast of Rank Math?**  
A: Ja, maar we raden aan om één plugin als leidend SEO-hulpmiddel te gebruiken. Dubbele sitemaps, schema's en redirects kunnen conflicten veroorzaken.

**V: Waarom zie ik bepaalde menu's niet?**  
A: Controleer of je licentie actief is en of je tier toegang geeft tot dat menu. Trial en DEV tonen alle menu's.

**V: Hoe vaak moet ik een Site Audit draaien?**  
A: Minimaal één keer per maand, of direct na grote wijzigingen (nieuwe site, migratie, grote content-release).

**V: Hoe forceer ik een Google-crawl?**  
A: Gebruik **Direct Index** (Google Indexing API) voor belangrijke URLs. Vergeet niet dat het quotum beperkt is.

**V: Mijn AI-tools genereren geen output, wat nu?**  
A: Controleer je licentiequota, de SaaS-dashboard-verbinding en of de juiste API-key is geconfigureerd.

**V: Werkt Fyndable met de klassieke editor?**  
A: De Editor Assistant werkt alleen in Gutenberg. De SEO-meta-box werkt in zowel Gutenberg als de klassieke editor.

**V: Hoe update ik white-label instellingen?**  
A: Ga naar **Settings → White Label**, pas de instellingen aan, log uit en opnieuw in om alles te zien.

**V: Waarom verschilt GSC-data van de Fyndable dashboards?**  
A: Dashboards cachen data en kunnen andere aggregaties gebruiken. Gebruik de cijfers voor trends, niet voor exacte vergelijkingen.

**V: Kan ik het dashboard volledig uitschakelen?**  
A: Ja, via **Settings → Advanced** kun je de full-page dashboard-shell uitschakelen. De menu's blijven dan wel in de standaard WordPress-admin staan.

**V: Wie kan ik vragen als het echt niet lukt?**  
A: Maak een ticket aan via **Support** in het Fyndable-menu of neem contact op via het door Fyndable aangegeven supportkanaal.

---

*Laatst bijgewerkt: huidige versie van de Fyndable client-plugin v1.5.1. Voor de meest actuele functielijst en tiergrens raadpleeg je licentiepagina of het Fyndable supportkanaal.*

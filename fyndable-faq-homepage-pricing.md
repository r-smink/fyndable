# Fyndable FAQ's

Gebaseerd op de daadwerkelijke functionaliteit in de `fynDASH`-branch (client-plugin + SaaS-dashboard). Let op de opmerking onderaan over de prijzen voordat je dit publiceert.

---

## FAQ homepage

Algemene, product-brede vragen. Bedoeld voor bezoekers die nog niet weten wat Fyndable precies is.

**Wat is Fyndable Smart SEO precies?**
Fyndable is een AI-gedreven SEO-platform voor WordPress. Je installeert één plugin en krijgt daarmee onder andere on-page SEO-analyse, contentoptimalisatie, rank tracking, schema markup en AI-content tools, aangestuurd door actuele SERP-data. Alles draait via jouw WordPress-omgeving, je hoeft geen losse tools naast elkaar te gebruiken.

**Hoe werkt het in de praktijk?**
Je installeert de plugin, activeert je licentiesleutel en Fyndable analyseert direct je site. Op basis van live SERP-data en AI-modellen krijg je concrete aanbevelingen: welke zoekwoorden kansrijk zijn, welke content moet worden aangescherpt, en welke technische issues je scores omlaag trekken. Je werkt alles af in hetzelfde WordPress-dashboard waar je toch al content beheert.

**Moet ik zelf een OpenAI-account of API-key regelen?**
Nee. Fyndable proxyt alle AI-aanvragen (OpenAI, Anthropic, Mistral) via het platform. Je activeert één licentiesleutel en alles werkt direct, zonder losse accounts, API-keys of technische koppelingen.

**Kan ik Fyndable naast Yoast of RankMath gebruiken?**
Dat kan, maar we raden aan om één plugin leidend te maken. Als twee plugins tegelijk sitemaps, schema-markup of redirects genereren, kan dat conflicten geven. De meeste gebruikers vervangen hun bestaande SEO-plugin volledig door Fyndable.

**Voor wie is Fyndable geschikt?**
Voor freelance SEO-specialisten, in-house marketeers en bureaus die meerdere klantsites beheren. Bureaus kunnen het platform bovendien white-label onder eigen merknaam aanbieden aan hun eigen klanten.

**Hoe snel kan ik live?**
Binnen enkele minuten. Plugin installeren, licentiesleutel activeren, en de features van jouw pakket verschijnen direct in het menu. Geen technische configuratie of losse integraties nodig.

**Is mijn data veilig?**
Ja. Data-export en -verwijdering op verzoek zijn ingebouwd, en alle koppelingen (licentievalidatie, betalingen, AI-verkeer) lopen via geverifieerde, versleutelde verbindingen.

**Wat als ik er niet uitkom?**
Je kunt via het Fyndable-menu in WordPress direct een supportticket aanmaken, of contact opnemen via het aangegeven supportkanaal.

---

## FAQ pricing pagina

Vragen die specifiek spelen op het moment dat iemand een pakket overweegt.

**Wat is het verschil tussen de pakketten?**
Elk pakket bouwt voort op het vorige: je begint met de kernfeatures voor on-page SEO en breidt uit met bijvoorbeeld rank tracking, content-optimalisatie, AI-content generatie of white-label opties naarmate je pakket groeit. Bekijk de tabel hierboven voor de exacte featureset en prijs per pakket.

**Kan ik Fyndable eerst gratis proberen?**
Ja, je kunt Fyndable 14 dagen gratis proberen met volledige toegang tot de Professional-functionaliteit. Zo test je precies wat het platform voor jouw site kan doen voordat je een keuze maakt.

**Kan ik op elk moment upgraden of downgraden?**
Ja. Wijzigingen in je abonnement worden automatisch verwerkt en je krijgt direct toegang tot de features die bij je nieuwe pakket horen.

**Zit ik vast aan een langlopend contract?**
Nee, abonnementen zijn maandelijks opzegbaar. Opzeggen verwerken we automatisch, zonder dat je daarvoor contact hoeft op te nemen.

**Welke betaalmethoden ondersteunen jullie?**
Voor de Nederlandse en Europese markt via iDEAL, Bancontact en SEPA-incasso. Internationaal via creditcard.

**Wat gebeurt er als ik mijn maandelijkse limiet bereik?**
Elk pakket heeft een maandelijkse limiet aan AI- en SERP-aanvragen die past bij het gebruiksniveau van dat pakket. Kom je structureel boven je limiet uit, dan is upgraden naar een groter pakket de logische stap.

**Ik beheer meerdere klantsites, kan dat met één account?**
Ja, dat is precies waar het Agency-pakket voor bedoeld is. Je beheert meerdere sites vanuit één dashboard, kunt extra licenties toevoegen voor extra klantsites, en het platform volledig white-label onder je eigen merknaam aanbieden.

**Kan ik per klant andere features aan- of uitzetten?**
Ja, binnen het Agency-pakket kun je per licentie features individueel toevoegen of uitschakelen, zodat je maatwerkbundels kunt samenstellen zonder dat daar development voor nodig is.

---

## Let op voordat je dit publiceert

Tijdens het doorlopen van de branch kwam ik drie verschillende prijslijsten tegen in de codebase zelf:

- `README.md`: Starter 99, Professional 199, Business 299, Agency 499 per maand
- `GEBRUIKERSHANDLEIDING.md`: Starter 9, Professional 29, Business 79, Agency 199 per maand
- `paymentprocessor.php` (de daadwerkelijke billingcode): Starter 19, Professional 49, Business 99, Agency 199 per maand

Dit is intern in `report-claude.md` ook al gesignaleerd als openstaand punt: er moet één definitieve prijslijn worden vastgesteld voordat dit commercieel naar buiten gaat. Ik heb de FAQ's daarom bewust prijsneutraal gehouden (ze verwijzen naar "de tabel hierboven" in plaats van bedragen te noemen). Wil je dat ik de definitieve bedragen erin verwerk zodra die vaststaan, of wil je dat ik ook naar de pricing-tabel zelf kijk?

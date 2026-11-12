# Handoff: Fyndable Shopify Addon — launch landing page

## Overview
A one-page marketing landing page for the launch of the **Fyndable Shopify SEO addon** (fyndable.ai). It's a dark, animated, "wow" page in the Fyndable brand (purple `#8F39AC` → blue `#379FD3` gradient, Outfit, Dashicons). Design reference: `Fyndable Shopify Landing.dc.html` in this bundle.

## About the files
- `Fyndable Shopify Landing.dc.html` — the **design reference** (an HTML/React prototype). Open it in a browser to see the exact look, animations and interactions. NOT production code to paste in.
- `Fyndable Shopify Landing.html` — a **standalone, fully-inlined** single file (fonts, CSS, icons, logo embedded; works offline). Use it to preview, or embed as-is.
- `fyndable-shopify-landing.json` — an **Elementor Pro** import (container-based, no sections) mirroring the page. Good starting point if the site is built in Elementor.
- `parts/*.json` — the same page split into **per-section Elementor templates** (hero, features, how-it-works, app-info, pricing, faq, cta).

## The task for Claude Code
The user's SEO app must be able to **read the page content as real DOM text** (headings, paragraphs, lists) — so the page must be built as **native markup/components, not an HTML-widget blob or an iframe**. Rebuild this design in the target stack (likely a Next.js/React page for fyndable.ai, or native Elementor widgets) using **semantic HTML**: one `<h1>`, section `<h2>`s, real `<p>`, `<ul>`, `<a>`. Keep all copy below verbatim.

## Fidelity
High-fidelity. Match colours, type, spacing, radii, shadows and the animations described per section.

## Brand tokens
- Purple `#8F39AC`, blue `#379FD3`; signature gradient `linear-gradient(135deg,#8F39AC,#379FD3)` (90° for bars).
- Dark fields: hero/CTA gradient `#2A1533 → #132A38` (135°); deep CTA bg `#0c0715`.
- Ink `#171226`, body `#3F384F`, muted `#6F6980`, page `#F6F5F9`, card `#fff`, border `#ECECF1`.
- Semantic: success `#10b981`/#28c840, warning `#f59e0b`, info `#379FD3`. Accent text gradient `#C77DFF → #5BC5F2`.
- Type: **Outfit** (400–700). H1 clamp(34–58px)/700 `-0.03em`; H2 clamp(28–40px)/700; body 15–19px; micro-labels 11–13px/700 uppercase.
- Radii: cards 15–18px, buttons/inputs 10–12px, pills/badges 999px. Shadows neutral low-alpha; primary buttons + featured cards cast purple shadow.

## Sections (in order) — copy is final, use verbatim

1. **Fixed header** (floating pill navbar, dark blur): Fyndable F-mark logo (`uploads/FYN-LOGO-DIA-SHORT300ppi.png`) + a "Shopify Partner" pill; centred nav links (Functies, Zo werkt het, Prijzen, FAQ); right: gradient button "Start gratis proef". Links hide < 780px.

2. **Hero** (dark gradient + animated aurora blobs + faint grid). Badge "Nieuw — nu beschikbaar voor Shopify". H1: "SEO voor Shopify, nu op *autopilot*" (last word gradient-clipped). Sub: "De Fyndable Shopify-addon brengt AI-content, keyword-tracking, technische SEO en rank-monitoring rechtstreeks in je Shopify-admin. Rank hoger, verkoop meer — zonder bureau." Buttons: "Start gratis proef" (→#pricing), "Neem contact op" (→#contact). Stat row (count-up on view): **2 min** Tot volledig live · **98%** Tevredenheid · **14** SEO-modules · **3×** Meer organisch verkeer. Right: a **live Shopify-admin mockup** (window chrome, SEO-score ring animating 0→90, optimisation bars, live feed) with two floating proof chips ("+218% organisch verkeer", "#3 → #1 keyword-positie"). Chips rotate/swap every few seconds to new statements.

3. **Trust marquee**: "Werkt naadloos met je Shopify-stack" + infinite-scrolling logos: Shopify, Search Console, DataForSEO, OpenRouter AI, IndexNow, AI-crawlers.

4. **Features** — H2 "Alles wat je Shopify-store nodig heeft om te ranken", sub "Eén addon vervangt een handvol losse tools én je SEO-bureau." A large **showcase panel** (left) that changes on hover of the feature tiles (right). The six features (tile title + desc, and the showcase view each swaps in):
   - **Content Optimizer** — "Genereer en verbeter meta-titels, beschrijvingen en alt-tekst voor je hele catalogus — in jouw merkstem, in bulk of per product." (view: progress bars Meta-titels/Schema/Alt-teksten + "+218% organisch verkeer" sparkline)
   - **Rank Tracker & Keyword Explorer** — "Volg dagelijkse posities en ontdek nieuwe zoekwoorden waar je producten en collecties op kunnen winnen." (view: list of newly-discovered keywords with volume + Nieuw/+positie badges)
   - **Technische SEO** — "Schema markup, redirects, IndexNow en llms.txt — automatisch toegepast via de officiële Shopify-API, geen themacode." (view: JSON-LD schema code block + "Geldig · Rich result" badge)
   - **AI-zichtbaarheid & GEO** — "Meet hoe vaak je merk verschijnt in ChatGPT, Gemini en Perplexity, en stuur AI-crawlers gericht aan." (view: ChatGPT/Gemini/Perplexity bars)
   - **Blog Writer & Contentkalender** — "Schrijf SEO-geoptimaliseerde artikelen en plan je contentkalender — tot 50 artikelen per maand." (view: a month calendar with coloured planned/published post chips)
   - **Concurrentie & Markten** — "Analyseer concurrenten, beheer hreflang voor internationale markten en bouw geoptimaliseerde collectiepagina's." (view: content-gap bars)
   Below the grid: a "Alle 14 modules inbegrepen" chip row.

5. **Live in drie stappen** (H2) with an animated horizontal gradient line: 1) Installeer de addon — "Voeg Fyndable toe vanuit de Shopify App Store — in 2 minuten live, geen code." 2) Verbind je data — "Koppel Google Search Console met één klik; Fyndable analyseert je hele catalogus." 3) Laat Fyndable werken — "Fyndable optimaliseert listings, schrijft content en volgt je rankings terwijl jij verkoopt."

6. **Gebouwd vóór Shopify, niet ernaast** — badge "In Shopify embedded", sub "De addon leeft in je Shopify-admin en werkt met je bestaande catalogus — niets te installeren in je theme." Centre: a Shopify-admin **browser-frame mockup** with an animated vertical scan line + product rows. Four numbered annotation pins (2 left, 2 right): 1) Draait in je Shopify-admin — "Volledig embedded met App Bridge en Polaris — geen aparte login of dashboards." 2) Per product & collectie — "Optimaliseer losse producten, collecties en blogs, of pas bulk toe over je catalogus." 3) Meertalig & internationaal — "Werkt met Shopify Markets: vertalingen en hreflang voor elke markt." 4) Veilig via de officiële API — "Alle wijzigingen lopen via de Shopify Admin API — niets in je theme-bestanden."

7. **Pricing** — H2 "Eén prijs. Al je stores. Geen bureau." Sub: "Facturering via **Shopify App Pricing** — op je gewone Shopify-factuur. Elk plan bevat een maandelijkse pool API-calls." Three plans (prices are set in the Shopify Partner dashboard):
   - **Starter — $60/maand · 500 API-calls/maand** — "Voor nieuwe stores die de basis op orde willen." Features: llms.txt & IndexNow · Meta, beschrijving & alt-tekst · Schema markup · Redirects · AI-crawlers aansturen.
   - **Professional — $199/maand · 2.000 API-calls/maand** (featured, rotating conic "beam" border) — "Volledige SEO-suite met tracking en inzichten." Features: Alles uit Starter · Rank tracker & keyword explorer · Concurrenten & GEO-scans (10/mnd) · AI-tracker & SEO-audit · Search Console & rapporten · Vertalingen & collectiepagina's.
   - **Business — $299/maand · 5.000 API-calls/maand** — "Voor stores die op volume content produceren." Features: Alles uit Professional · AI Blog Writer (50 artikelen/mnd) · Contentkalender · Bulk optimizer.
   Note line: "Via Shopify App Pricing — activeren en opzeggen in je Shopify-admin, maandelijks opzegbaar. Test gratis tot 5 producten." (NO agency/enterprise tier.)

8. **FAQ** as a **support-chat UI**: left intro ("Vragen? Wij antwoorden direct." + avatars + "± 2 minuten · ma–vr"); right a chat window (Fyndable Support · online). Clicking a question chip shows it as a purple user bubble + Fyndable's answer bubble. Questions/answers:
   - Werkt de addon met mijn bestaande Shopify-thema? — "Ja. Fyndable draait in je Shopify-admin en past geen themacode aan — alle optimalisaties gebeuren via de officiële Shopify-API."
   - Heb ik technische SEO-kennis nodig? — "Nee. De addon doet het zware werk en legt elke aanbeveling in gewone taal uit, met directe toepassing via de Shopify-API."
   - Kan ik mijn Google-data koppelen? — "Ja — koppel Google Search Console met één klik voor je echte posities, vertoningen en klikken, plus SEO-rapporten (vanaf Professional)."
   - Zit ik vast aan een contract? — "Nee. Billing loopt via Shopify App Pricing en is maandelijks opzegbaar; je test eerst gratis tot 5 producten."
   Section id is `#contact` (the header "Neem contact op" links here).

9. **Final CTA** (deep `#0c0715`, aurora + grid, glass card with rotating conic beam border, two floating proof chips "+218% verkeer" and "Shopify App Store"): badge "Gratis starten · geen creditcard", H2 "Klaar om je Shopify-store te laten *ranken*?" sub "Laat je SEO draaien door Fyndable — direct in je Shopify-admin. Test gratis tot 5 producten — upgrade wanneer je klaar bent." Buttons "Start gratis proef" + "Praat met sales". Trust line: In 2 minuten live · Geen themacode · Maandelijks opzegbaar.

10. **Footer**: Fyndable logo + "© 2026 Fyndable B.V. · Alle rechten voorbehouden."

## Important content rules (already applied — keep)
- **No "12.000+ merchants / Shopify-stores" claims** anywhere — not yet true.
- No "AI Autopilot" / "agents" framing for the Shopify app — it has no autopilot.
- Only **Google Search Console** integrates (no GA4/Ads/Meta/Klaviyo as connected integrations).
- Billing is **Shopify App Pricing**, monthly, free up to 5 products; no 14-day trial wording, no agency tier.

## SEO (page metadata to set)
- `<title>`: "Fyndable voor Shopify — AI SEO-addon voor je Shopify-store" (≤ 60 chars target).
- meta description: "SEO voor Shopify op autopilot: AI-content, keyword-tracking, technische SEO en rank-monitoring in je Shopify-admin. Start gratis." (≤ 155 chars).
- One `<h1>` (the hero), `<h2>` per section. OpenGraph title/description same; OG image = a branded gradient card. Add `SoftwareApplication` + `FAQPage` JSON-LD (the FAQ Q&A above).

## Assets
- `uploads/FYN-LOGO-DIA-SHORT300ppi.png` — Fyndable F-mark (header + footer). Also a long lockup exists.
- Dashicons for icons (or map to the target app's icon set). No product photography — mockups are brand-gradient tiles.

## Where to build
If fyndable.ai is Next.js/React: one route/page, semantic sections, Tailwind or CSS modules using the tokens above; animations via CSS keyframes + a small IntersectionObserver reveal. If Elementor: import `fyndable-shopify-landing.json` (or the `parts/`), then set global fonts to Outfit and brand colours.

## Target page
The page must end up applied to the **existing WordPress/Elementor page with ID 1297** (not a new page). Edit page 1297 with Elementor and build the content there as native widgets/containers (so the SEO app reads real DOM text — do not drop it into a single HTML widget or iframe). If importing the Elementor JSON: open page 1297 in Elementor → import the template into that page, then wire the brand global fonts/colours. Keep the page's existing permalink/ID 1297 intact.

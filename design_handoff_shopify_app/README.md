# Handoff: Fyndable Smart SEO — Shopify app redesign

## Overview
A full redesign of the **Fyndable Smart SEO Shopify app** (`shopify-app/` — a Laravel + Shopify App Bridge embedded app). It restyles the current plain tabbed dashboard into the same Fyndable visual language used by the WordPress Client and SaaS Dashboard redesigns:
- A dark brand header band (logo, shop domain, light/dark toggle, licence pill).
- A **horizontal tab bar** below the band (the app's real navigation model — `showTab()` in the current blade).
- **Light + dark themes** via CSS custom-property overrides.
- Dense, card-based content on every tab.

Because this is an **embedded Shopify app** (it renders in an iframe inside Shopify admin via App Bridge), it uses horizontal tabs — not a WordPress-style sidebar. Everything else (tokens, cards, tables, badges) is shared with the other two Fyndable redesigns, so if you implemented those, reuse the same variables and patterns.

## About the design files
`Fyndable Shopify.dc.html` is a **design reference** — an HTML/React prototype of the intended look, layout, theming and interaction. It is **not** production code to paste in. The target is the existing app: a single Blade view **`resources/views/dashboard/index.blade.php`** (the whole embedded UI lives here as server-rendered HTML + inline CSS + vanilla-JS `fetch` calls to `/api/*`). Recreate this design inside that file's `<style>` block and tab markup — keep all the existing `onclick=` handlers, element ids and `/api/*` calls; only restructure markup and restyle.

## Fidelity
**High-fidelity.** Final colours, type, spacing, radii, shadows, interactions. Recreate pixel-accurately; all values under **Design tokens** (identical to the Client/SaaS handoffs).

---

## Structure (every tab shares this shell)
1. **Brand header band** (`linear-gradient(135deg,#2A1533 0%,#132A38 100%)`, 60px, inner `max-width:1340px`): logo (`height:24px`) + "Shopify-app" micro-label (1px left divider) · spacer · **shop-domain chip** (store Dashicon + `northgear.myshopify.com` on `rgba(255,255,255,.1)`) · **theme toggle** (☀/☾ pill) · **licence pill** (green, "Business").
2. **Horizontal tab bar** (same dark band, `max-width:1340px`, horizontally scrollable): one button per tab — icon + label, 13px/600; active = white text + a 3px solid white bottom border; rest = `rgba(255,255,255,.62)`.
3. **Content area** (`--page` bg, inner `max-width:1340px`, `.3s` fade-in on tab change): a page header (H1 25px/700 + one-line description + optional primary gradient action), then — on data tabs — a 4-up **stat-tile** row, then the tab body.

### Tabs (exact order + Dashicon; the real tab ids from `showTab()`)
`dashboard` Dashboard (`chart-bar`) · `content` Content Optimizer (`edit`) · `llmstxt` llms.txt (`media-code`) · `bulk` Bulk Optimizer (`yes-alt`) · `ranktracker` Rank Tracker (`chart-line`) · `backlinks` Backlinks (`admin-links`) · `insights` AI-zichtbaarheid / AI Visibility (`visibility`) · `blogwriter` Blog Writer (`edit-page`) · `tools` Tools / IndexNow (`admin-tools`) · `license` Licentie / License (`admin-network`).

---

## Theming — CSS custom properties (identical to the other Fyndable handoffs)
Light default; `fyn-dark` (or the app's body/root dark class) flips it. Note: the Shopify app may follow Shopify admin's own light/dark preference via App Bridge — wire the toggle to that if available, else persist to the shop record.

```css
/* LIGHT */
--page:#f1f0f5; --card:#ffffff; --card-border:#e7e6ee; --panel:#ffffff;
--text-h:#1a1430; --text-b:#413b52; --text-m:#746e82; --text-s:#a39dae;
--well:#f7f6fa; --well-border:#edecf2; --row-hover:#faf7fe; --divide:#efeef4;
--input-bg:#ffffff; --input-border:#d8d6e0; --sev-track:#e9e7f0;
--serp-bg:#f8f8fb; --serp-title:#1a0dab; --serp-url:#0f7a37;
/* DARK */
--page:#120c1c; --card:#1d1630; --card-border:rgba(255,255,255,.07); --panel:#19122a;
--text-h:#f4f1fa; --text-b:#cdc6dd; --text-m:#958da7; --text-s:#6f6784;
--well:#211a35; --well-border:rgba(255,255,255,.07); --row-hover:rgba(143,57,172,.12); --divide:rgba(255,255,255,.07);
--input-bg:#211a35; --input-border:rgba(255,255,255,.12); --sev-track:rgba(255,255,255,.1);
--serp-bg:#17112a; --serp-title:#8ab4f8; --serp-url:#6ee7a8;
```
The dark header-band gradient (`#2A1533 → #132A38`) is used in both themes.

## Brand design tokens (Fyndable design system — do not invent values)
Same as the Client/SaaS handoffs: purple `#8F39AC`, blue `#379FD3`, ink `#333333`; signature gradient `linear-gradient(135deg,#8F39AC,#379FD3)` (90° for bars). Semantic solid+tint pairs: success `#10b981`/`#d1fae5`→`#047857`, warning `#f59e0b`/`#fef3c7`→`#92700a`, danger `#ef4444`/`#fee2e2`→`#b91c1c`, info `#3b82f6`/`#dbeafe`→`#1d4ed8`. Type: **Outfit** (page title 25px/700 `-0.02em`, card title 15–16px/700, body 13–14px, table 13px, micro-labels 11px/700 uppercase `.04em`; nothing below 11px; monospace for licence keys, URLs and the llms.txt/code preview). Radii: cards 13–14px, icon chips 9–11px, buttons/inputs 9px, pills 999px. Shadows: neutral low-alpha outer; primary button + hovered tiles cast purple `rgba(143,57,172,.2–.35)`. Motion: `all .2s ease`, progress `width .3s`, `.3s` fade-in. Hover = lift (`-2px`), outline/tiles → purple border + `--well`/tint fill. **Icons: Dashicons only.** No emoji except WP-menu labels (N/A here). Unicode `✓ ▲ ▼` as literal marks.

**Stat tile** (4-up, `gap:16px`): card `padding:18px 20px` + 3px gradient top bar; 11px/700 uppercase label + 17px purple Dashicon; value 28px/700; muted sub-text. **Tables:** header 11px/700 uppercase + 2px `--divide` rule; rows 13px, 1px `--divide`, hover `--row-hover`. **Inputs:** `.fyn-inp` 44px / `.fyn-ta` textarea, 2px border → purple on focus.

---

## Tabs / Views (what each does — ground truth in the controllers)
1. **Dashboard** (`DashboardController::overview`) — 4 stat tiles (Licence tier, Products, Tracked keywords + top-10, llms.txt status); a "SEO-dekking van je winkel" card (labelled progress bars: meta-title / meta-description / alt-text / schema coverage, green at 100% / amber below); a "Snelle acties" card (tappable rows routing to Bulk, llms.txt, Rank, Blog).
2. **Content Optimizer** (`ContentController` + `ProductController`) — a content-type switch (Producten / Collecties / Pagina's / Artikelen, each with a count); a searchable item list (icon chip + title + price/SKU + SEO-score badge, colour-graded ≥80/≥50/else); a detail panel for the selected item: meta-title + meta-description fields with char counts, a 2×3 grid of AI actions (Meta genereren, Beschrijving, Schema markup, FAQ-blok, Alt-tekst, Interne links), and **Opslaan naar Shopify** (primary) + Preview. Maps to the generate-/save-/push- routes per type.
3. **llms.txt** (`LlmsTxtController`) — left: two toggles (`/llms.txt`, `/llms-full.txt`), an "Opnemen in output" chip row (Producten/Collecties/Pagina's/Blogartikelen), max-products + max-chars fields, site-description field, custom-sections textarea, and Save / Regenereer / Maak root-redirects buttons; right: a monospace **preview card** of the generated llms.txt on `--serp-bg` with an "Actief" badge.
4. **Bulk Optimizer** (`BulkOptimizerController`) — 4 stat tiles (products, optimised, missing meta, last run); left: action select + max-products + Start / Voortgang / Annuleer; right: a running-run card with a header progress bar + per-product status table (Voltooid / Bezig / In wachtrij).
5. **Rank Tracker** (`RankTrackerController`) — 4 stat tiles (tracked, top-10, avg position, risers); an add-keyword bar (keyword + target URL + "Volg zoekwoord"); a keywords table (keyword, position badge colour-graded by rank, ▲/▼ change green/red, a 7-day inline SVG sparkline, delete).
6. **Backlinks** (`InsightsController::backlinks`) — 4 stat tiles (backlinks, referring domains, DR, lost); Samenvatting / Live-backlinks toggle; a referring-domains table (domain link, DR chip, backlink count, Dofollow/Nofollow badge, first-seen).
7. **AI-zichtbaarheid** (`InsightsController::llmMentions` / `llmCheck`) — left: a "Live LLM-antwoord" card (question input + provider select ChatGPT/Gemini/Perplexity + Vraag), the answer in a `--well` block with the brand highlighted, and a success note "Je merk werd genoemd op positie X"; right: a "Merkvermeldingen" card (domain input + search + per-platform mention-% bars).
8. **Blog Writer** (`BlogWriterController`) — left: a form (blog select, topic/keyword, tone, Genereer artikel); right: a generated-draft card (title, word-count/score/reading-level meta, body paragraphs, "Niet gepubliceerd" badge, Publiceer naar blog / Bewerk / Regenereer).
9. **Tools / IndexNow** (`IndexNowController`) — left: an IndexNow card (installed-key success note, URLs textarea, Submit / Setup key-redirect); right: a "Technische status" list (IndexNow, llms.txt, Sitemap, App-proxy, Search Console — each a row with icon chip + status badge).
10. **License** (`LicenseController`) — a licence card ("Licentie actief" success chip, plan + renewal, monospace licence-key field with copy icon, Opnieuw valideren + Deactiveren) + an "Inbegrepen in Business" ✓ feature list.

## Interactions & behaviour
- **Tabs** switch the active panel (keep `showTab(id)`); active = white label + 3px white underline on the dark bar.
- **Content-type switch** filters the item list; active type = gradient pill.
- **Theme toggle** flips the dark class (ideally following Shopify admin's colour scheme via App Bridge).
- **Hover:** cards/buttons lift `-2px`; table rows tint `--row-hover`; outline buttons/tiles → purple border + `--well`/purple-tint fill; tabs brighten.
- All destructive/write actions (save to Shopify, deactivate licence, cancel bulk) keep their existing scope checks — this is a restyle only.
- Responsive: 4-up stat grids → 2-up < 1200px → 1-up < 1024px; the tab bar scrolls horizontally on narrow widths (App Bridge iframe can be narrow).

## Assets
- **`FYN-LOGO-DIA-LONG300ppi.png`** — white horizontal Fyndable lockup for the dark band. Included; drop into `public/` (or `resources/`) and reference from the blade.
- **Dashicons** — vendor the Dashicons font into the app's `public/` assets (the WP plugins already ship it) and load it, OR swap each listed glyph for the Polaris/your-own equivalent. The prototype uses Dashicons for brand consistency across all three Fyndable surfaces.
- No photography/illustration (deliberate brand constraint).

## Files in this bundle
- `Fyndable Shopify.dc.html` — the full interactive design reference (all 10 tabs, both themes). Open in a browser; use dev tools to read computed values.
- `FYN-LOGO-DIA-LONG300ppi.png` — header logo asset.
- `tokens-colors.css` — Fyndable design-system colour tokens for reference.

## Where to apply in the real app (`shopify-app/`)
- **The entire embedded UI** is `resources/views/dashboard/index.blade.php` (~72KB: markup + inline `<style>` + vanilla-JS `fetch` to `/api/*`). Put the token CSS variables + both theme blocks at the top of its `<style>`, rebuild the header band + tab bar, and restyle each `.tab-content` section (`#llmstxt`, `#bulk`, `#ranktracker`, `#backlinks`, `#insights`, `#blogwriter`, `#tools`, `#license`, the content + dashboard sections) to the card/table/stat patterns above. Keep every `id`, `onclick` and `api()` call.
- **Data/behaviour** is unchanged — it lives in `app/Http/Controllers/*` (`DashboardController`, `ContentController`, `ProductController`, `LlmsTxtController`, `BulkOptimizerController`, `RankTrackerController`, `InsightsController`, `BlogWriterController`, `IndexNowController`, `LicenseController`) and `routes/api.php`. Do not change controllers, routes, scopes or the App Bridge/session middleware — restyle the view only.
- The landing/install pages (`resources/views/welcome.blade.php`, `install.blade.php`) are out of scope unless you want them matched too.

# Handoff: Fyndable Smart SEO — Client plugin shell & screen restyle

## Overview
A full redesign of the **Fyndable Client** WordPress plugin admin (`wp-content/plugins/fyndable-client`). It replaces the current full-page shell and 23 flat emoji menu items with:
- A refined dark brand top bar (logo, global search, light/dark toggle, notifications, help, account, "Afsluiten").
- A **grouped sidebar nav** (6 labelled sections) using **Dashicons** instead of emoji.
- **Light + dark themes** driven entirely by CSS custom-property overrides.
- Denser, full-width content on all 23 screens.

## About the design files
The file `Fyndable Client.dc.html` in this bundle is a **design reference** — an HTML/React prototype that shows the intended look, layout, theming and interaction. It is **not production code to paste in**. The target codebase is the existing plugin: **PHP page renderers + CSS** (`includes/client.php` → `DashboardShell`, feature `render*Page()` methods, `assets/client-admin.css`, `assets/admin-modern.css`). The task is to **recreate this design in that PHP/CSS environment** using its existing structure — i.e. port the tokens and markup patterns below into the plugin's CSS and the shell/page PHP, not to ship the React prototype.

## Fidelity
**High-fidelity.** Final colors, typography, spacing, radii, shadows and interactions. Recreate pixel-accurately. All values are listed under **Design tokens**.

---

## The shell (applies to every screen)

Structure (top-level flex column, `100vh`):
1. **Top bar** — fixed-height 58px, full width, background `linear-gradient(135deg,#2A1533 0%,#132A38 100%)`, white text, `padding:0 22px`, `box-shadow:0 1px 0 rgba(0,0,0,.2)`. Left→right:
   - **Logo**: `assets/FYN-LOGO-DIA-LONG300ppi.png` (white lockup) at `height:26px`, then a `Client` micro-label (10px/600, uppercase, `letter-spacing:.05em`, `rgba(255,255,255,.55)`) separated by a 1px left divider `rgba(255,255,255,.2)` with `padding-left:12px`.
   - **Search**: flex field, `max-width:460px`, 36px tall, `background:rgba(255,255,255,.1)`, `border:1px solid rgba(255,255,255,.12)`, `border-radius:9px`, search Dashicon + input + `⌘K` hint pill.
   - Spacer, then **theme toggle** (pill with ☀/☾), **notifications** bell (34px icon button, red dot), **help** (?), a 1px divider, **avatar** (32px gradient circle "RS"), **Afsluiten** button.
   - Icon buttons: 34px square, `border-radius:9px`, `background:rgba(255,255,255,.1)`, `border:1px solid rgba(255,255,255,.12)`; hover → `rgba(255,255,255,.2)`.
2. **Body** — flex row below the bar:
   - **Sidebar** 248px, `background:var(--shell-sidebar)`, `border-right:1px solid var(--shell-sb-border)`, `padding:16px 14px 28px`, scrollable.
   - **Main** — scrollable, `padding:28px 32px 48px`; inner content `max-width:1440px;margin:0 auto`; new content fades in `@keyframes` (opacity 0→1, `translateY(8px)→0`, .3s ease) on screen change.

### Sidebar nav grouping (exact order, Dutch label + Dashicon)
Section headers are 10.5px/700, uppercase, `letter-spacing:.07em`, `color:var(--nav-section)`, `padding:14px 10px 6px`.

- **Overzicht** — Dashboard (`chart-bar`), Verbinding (`admin-links`)
- **Content** — Ideeën (`lightbulb`), Contentkalender (`calendar-alt`), Aangemaakte berichten (`edit-page`), Onderwerpgroepen (`admin-site-alt3`), ApexFlow (`controls-play`), Bulkoptimalisatie (`yes-alt`)
- **Zoekwoorden & ranking** — Zoekwoorden (`search`), Ranktracker (`chart-line`), Concurrenten (`groups`), A/B-testen (`randomize`)
- **Links & techniek** — Linkbeheer (`admin-links`), Backlinks (`admin-links`), Adv. Backlinks (`admin-links`), Site Audit (`clipboard`), Sitemaps (`networking`)
- **Data & inzichten** — SEO-data (`chart-area`), Google-data (`google`), LLM-tracker (`visibility`)
- **Systeem** — AI-hulpmiddelen (`superhero-alt`), Integraties (`admin-plugins`), Ondersteuning (`sos`, with unread badge)

Nav item: flex row, `gap:11px`, `padding:9px 11px`, `border-radius:9px`, 13.5px/600, `color:var(--nav-fg)`, 17px Dashicon.
- **Rest hover** → `background:var(--nav-hover)`, `color:var(--text-h)`.
- **Active** → `background:linear-gradient(135deg,#8F39AC,#379FD3)`, `color:#fff`, `box-shadow:0 5px 14px rgba(143,57,172,.32)`.
- Support item carries a numeric red badge (`--fyn-danger`, white) at the right; on the active item the badge is `rgba(255,255,255,.25)`.

Below the nav: a small **plan card** (gradient-tinted) with API-usage progress bar.

### Page header (inside main, per screen)
Breadcrumb `Fyndable › <crumb>` (11.5px/600, crumb in purple), H1 27px/700 `letter-spacing:-.025em` `color:var(--text-h)`, one-line description 14px `color:var(--text-m)` `max-width:640px`. Right side: a secondary **Ververs** button (outline) + a primary gradient action button (label + Dashicon per screen). Primary hover = lift `translateY(-2px)` + stronger purple shadow.

---

## Theming — the core mechanism
Everything themes off CSS custom properties on a root wrapper. Light is the default; add class `fyn-dark` to flip. In the plugin this maps to the existing `body.fyndable-ui` / `body.fyndable-dark` classes already set by `Client::addAdminBodyClass()` (it reads `?fyndable_dark=1` and option `sseo_ai_client_dark_theme`). Port these variable sets into `client-admin.css`:

```css
/* LIGHT (default) */
--shell-page:#f4f5f8;  --shell-sidebar:#ffffff;  --shell-sb-border:#e9eaef;
--nav-fg:#5a5568;  --nav-section:#9a95a8;  --nav-hover:#f5f2fb;
--card:#ffffff;  --card-border:#ececf1;
--card-shadow:0 1px 3px rgba(20,14,30,.07),0 1px 2px rgba(20,14,30,.04);
--card-shadow-lg:0 10px 24px -8px rgba(20,14,30,.14);
--text-h:#1a1430;  --text-b:#413b52;  --text-m:#746e82;  --text-s:#a39dae;
--well:#f7f6fa;  --well-border:#edecf2;  --row-hover:#faf7fe;  --divide:#efeef4;
--input-bg:#ffffff;  --input-border:#dcdae4;  --sev-track:#e9e7f0;

/* DARK (body.fyndable-dark) */
--shell-page:#120c1c;  --shell-sidebar:#19122a;  --shell-sb-border:rgba(255,255,255,.06);
--nav-fg:#b4adc6;  --nav-section:#726a86;  --nav-hover:rgba(143,57,172,.16);
--card:#1d1630;  --card-border:rgba(255,255,255,.07);  --card-shadow:0 1px 3px rgba(0,0,0,.45);
--card-shadow-lg:0 14px 30px -10px rgba(0,0,0,.6);
--text-h:#f4f1fa;  --text-b:#cdc6dd;  --text-m:#958da7;  --text-s:#6f6784;
--well:#17112440;  --well-border:rgba(255,255,255,.07);  --row-hover:rgba(143,57,172,.12);  --divide:rgba(255,255,255,.06);
--input-bg:#211a35;  --input-border:rgba(255,255,255,.1);  --sev-track:rgba(255,255,255,.1);
```

The dark top bar gradient (`#2A1533→#132A38`) is used in **both** themes — do not change it per theme.

---

## Brand design tokens (from the Fyndable design system — do not invent new values)
- **Brand:** purple `#8F39AC`, blue `#379FD3`, ink `#333333`, mist `#E6E7E8`.
- **Signature gradient:** `linear-gradient(135deg,#8F39AC 0%,#379FD3 100%)` (progress fills / flat bars use the 90° form).
- **Tints:** purple-tint `#F4E9F8`, blue-tint `#E6F3FB`.
- **Semantic (solid + tint pairs — tint = background, solid = text/rule):** success `#10b981`/`#d1fae5` (text `#047857`), warning `#f59e0b`/`#fef3c7` (text `#92700a`), danger `#ef4444`/`#fee2e2` (text `#b91c1c`), info `#3b82f6`/`#dbeafe` (text `#1d4ed8`), accent `#a855f7`/`#f3e8ff` (text `#9333ea`).
- **Type:** Outfit (200–700). Page title 27px/700 `-0.025em`; card title 16–18px/700; body 13–15px; table cells 13px; micro-labels 11px/700 uppercase `letter-spacing:.04–.05em`. Nothing below 11px. Monospace (licence keys / URLs) on a `--well` field, `letter-spacing:1px`.
- **Radii:** cards 13–14px, icon chips 11–12px, buttons 8–9px, inputs 8–9px, small tiles 6–7px, pills/badges 999px, dots 50%.
- **Shadows:** neutral low-alpha outer only (`--card-shadow`); primary button + hovered tool tiles cast a **purple** shadow `rgba(143,57,172,.2–.3)`.
- **Motion:** `all .2s ease`; progress `width .3s`; score ring `stroke-dasharray 1s`; content fade-in `.3s` with 8–10px rise. Hover = lift (cards/buttons `-2px`, tool tiles `-4px`), never grow.
- **Icons:** WordPress **Dashicons** only (already vendored in the plugin). Three forms: bare purple glyph, 48px gradient chip, 40px glyph in an 80px well. **No emoji** anywhere except (optionally) WP top-level menu labels.

---

## Screens / Views (23)
All share: page header + content in `max-width:1440px`. Cards are `background:var(--card)`, `border:1px solid var(--card-border)`, `border-radius:13–14px`, `box-shadow:var(--card-shadow)`, body padding 22–26px. Optional card header strip: 16–18px title with a purple Dashicon + 1px `--divide` bottom rule. Tables: header row 11px/700 uppercase `--text-m` with a 2px `--divide` bottom rule; body rows 13–13.5px separated by 1px `--divide`, hover `background:var(--row-hover)`.

Recurring **compact stat tile** (4-up grid, `gap:16px`): card, `padding:16px 18px`; top row = 11px/700 uppercase label + 16px purple Dashicon; value 27px/700 `-0.02em`; footer = a semantic delta pill (`--fyn-*-light` bg + matching text) + muted sub-text.

1. **Statistieken (Dashboard)** — 4 hero stat cards (each with a 4px gradient top accent bar), score ring (SVG, gradient stroke, "98/100", "Great" success pill), SEO Breakdown card (6 labelled progress bars, green at 100% / amber below), Problemen-gevonden list (left-border severity, info/warning Dashicon, "Bekijk →"), Quick Wins list ("Fix met AI" buttons).
2. **Verbinding** — 4 stat tiles (status/plan/API usage/renewal) + a licence card: success chip "Licentie actief", monospace licence-key field + copy icon, dashboard-URL field, "Valideer opnieuw" (outline) + "Deactiveer licentie" (danger outline); side card "Inbegrepen in Agency" with ✓ feature list.
3. **Ideeën** — 3-col grid of idea cards: gradient icon chip + score pill, title, keyword · volume · intent badge, "Schrijf met AI" (primary) + "Brief" (outline).
4. **Contentkalender** — month nav bar (Vorige / Oktober 2026 / Volgende); left: Publicatiekalender 7-col month grid (day cells `min-height:86px`, content chips purple/amber, a `⚠ Gap` amber cell) + "Plan nieuwe content" / "Optimaliseer planning"; right column: Content Gap Kalender (dates + "Vul gap" outline buttons) and Workflow-status (4 tinted count tiles). Bottom: Zoekwoord-kansen tijdlijn table with priority badges + "Maak content".
5. **Aangemaakte berichten** — filter card (search, cluster select, dual-handle word-range slider; three rows of count pills: mass-edit / review / status) + results card with a 44px gradient thumbnail per row, status / mass-edit / review badges.
6. **Onderwerpgroepen** — 3-col cluster cards: title + small coverage ring (%), purple-tint "Pillar" block, supporting-pages list with document Dashicons.
7. **ApexFlow** — gradient "Autopilot is actief" banner with an on/off switch; 5-step pipeline (Zoekwoord→Brief→Concept→Review→Gepubliceerd) with icon chips + counts; Wachtrij table with stage badges + progress bars + ETA.
8. **Bulkoptimalisatie** — left task picker (4 bordered tiles w/ icon chip + count, hover→purple) + "Start bulk-run"; right running-run card with a header progress bar + per-post status table.
9. **Zoekwoorden** — toolbar (search field, gradient "Genereer ideeën", Clusters, Keywords explorer, trash); underlined tab bar (Alle/Gevolgd/Gerankt/Positiewijzigingen/Nieuw gerankt) with count pills; table (Zoekwoord+url, Cluster pill, Volume, Moeilijkheid mini-bar+label, CPC, Rank badge, Intentie badge, action icons) + pager.
10. **Ranktracker** — 4 stat tiles; a 30-day average-position SVG area chart (gradient fill + stroke); table (keyword+url, volume, position badge colour-graded by rank, ▲/▼ change in green/red).
11. **Concurrenten** — 4 stat tiles; 3 competitor cards (domain, DR/traffic/keywords mini-metrics, overlap bar); content-gap table (their pos green, your pos muted, difficulty badge, "Maak content").
12. **A/B-testen** — stacked test cards: title + status badge, Variant A (outline) vs Variant B (purple, highlighted) with CTR, confidence bar + winner.
13. **Linkbeheer** — 4 stat tiles (orphan count in warning tone); Orphan-pages card ("Scan opnieuw", table: page link, type, priority score colour-graded, sources, "Auto-fix"/"Bekijk").
14. **Backlinks** — 4 stat tiles; recent-backlinks table (source link, DR chip, anchor, Dofollow/Nofollow badge, first-seen).
15. **Adv. Backlinks** — 4 stat tiles (toxicity); toxic-links table with toxicity bar (green/amber/red by score) + "Disavow" danger button; "Exporteer disavow-bestand".
16. **Site Audit** — left: health ring (81, "Needs Work" amber pill) + category counts (errors/warnings/notices/passed with tinted icon chips); right: issue list with severity badge, title, fix description, "Los op".
17. **Sitemaps** — left sitemaps table (name, URLs, updated, status); right settings card with 6 toggle switches (purple when on).
18. **SEO-data** — 4 stat tiles; 3 source cards (SE Ranking/Ahrefs/DataForSEO, connected ✓ or "Verbind"); top-keywords table.
19. **Google-data** — 4 GSC stat tiles; 3 source cards (GSC/GA4/Ads); top-queries table (clicks/impressions/CTR/position).
20. **LLM-tracker** — 4 stat tiles; 3 platform cards (ChatGPT/Gemini/Perplexity mention % + bar); tracked-prompts table with ✓/✕ per platform + position badge.
21. **AI-hulpmiddelen** — 4-col grid of tool cards: 2px border, 48px gradient icon chip, title + description; hover → purple border, `-4px` lift, purple shadow.
22. **Integraties** — 2-col grid of integration rows: 48px purple-tint icon chip, name + description, "Verbonden"/"Verbinden" status pill.
23. **Ondersteuning** — left tickets list (chat icon chip, subject, id + last-reply, status badge) + "Nieuw ticket"; right kennisbank card (search + article links).

## Interactions & behavior
- **Theme toggle** flips `fyndable-dark` on the body; the plugin already persists this to option `sseo_ai_client_dark_theme` and passes `?fyndable_dark=` so iframe pages load correct immediately — keep that mechanism.
- **Nav** sets the active page (in the plugin, normal `admin.php?page=…` links); active = gradient pill.
- **Hover**: cards/buttons lift `-2px`; tool tiles `-4px`; table rows tint `--row-hover`; nav items tint `--nav-hover`; outline buttons → purple border + purple-tint fill.
- **Tabs** (Zoekwoorden) switch the filtered list; active tab = purple text + 2px purple underline.
- Responsive: 4-up stat grids → 2-up < 1200px → 1-up < 1024px; respect WP's 782px admin breakpoint.

## Assets
- **`FYN-LOGO-DIA-LONG300ppi.png`** — white horizontal Fyndable lockup (2929×506, transparent) for the dark top bar. Included in this bundle; drop into `assets/`.
- **Dashicons** — already vendored in the plugin (`assets/` / core WP). Use the glyph names listed per nav item and per screen.
- No photography/illustration anywhere (deliberate brand constraint).

## The single-post editor meta box (`Fyndable Post Editor.dc.html`)
The plugin also injects SEO fields into the WordPress **post/page editor** (the `Fyndable Smart SEO` meta box). This is redesigned in the same token system — same dark brand header band, same light/dark variables, same card/field/badge vocabulary — but as a self-contained panel (max-width ~1040px) rather than the full-page shell.

Structure:
- **Brand header band** (`#2A1533 → #132A38`): logo + theme toggle + a "Postoptimalisatie" pill, 56px tall.
- **Collapsible accordion sections**, each a full-width header row with a 3px coloured left border, a Dashicon in the section accent colour, the title, a count badge, and an up/down chevron. Open section header uses `--sect-head` background. Sections: **Content & zoekwoorden** (accent blue `#379FD3`), **Technische SEO** (purple `#8F39AC`), **Social & schema** (accent `#a855f7`), **AI-hulpmiddelen** (green `#10b981`).
- **Content & zoekwoorden** opens to a **tab bar** (SEO-score, Zoekwoorden, Slimme tags, Interne links, E-E-A-T, Prestaties, Workflow — active tab = purple text + 2px purple underline).
  - **SEO-score tab**: an 84px solid score disc coloured by band (≥80 green, ≥50 amber, else red) with the number in white + verdict sentence; form fields **Focus-zoekterm**, **Secundaire zoektermen** (empty → real input w/ placeholder), **SEO-titel** (char-count hint turns amber when over 60), **Meta-beschrijving** (textarea, char-count hint); a **Google SERP-preview** card (`--serp-bg`, blue title, green URL, muted desc); an **Analyseresultaten** list (each row: severity left-border + tint bg — green `ok` / amber `warn` / red `danger` — Dashicon, bold label, finding text); two actions: **Content opnieuw analyseren** (primary gradient) + **AI-verbeteringen voorstellen** (outline).
  - Other tabs render a labelled row-list (icon chip + label + detail + status badge) populated with that tab's real signals (keyword density, smart tags, internal-link opportunities, E-E-A-T checks, GSC performance, workflow status).
- **Technische SEO / Social & schema / AI-hulpmiddelen** open to field bodies built from the same controls: select rows (value + chevron), toggle rows (purple switch + description), text field rows (filled value or placeholder input), and action rows (gradient icon chip + label + "Genereer" button) for AI/image generation.
- Form controls: `.fyn-inp` (46px, 2px border → purple on focus) and `.fyn-ta` (textarea); labels 13px/700 `--text-b`; hints 12px `--text-s` (amber/red when a limit is exceeded).

Where to apply: the meta-box renderer in the client plugin — the `add_meta_box` callback and its `render*MetaBox()` / tab-render methods (search `includes/` for the post-editor SEO panel, e.g. `postmetabox.php` / `seometabox.php`), plus the matching meta-box CSS. Reuse the exact same CSS variables as the shell so light/dark stay consistent across admin pages and the editor.

## Files in this bundle
- `Fyndable Client.dc.html` — the full interactive design reference (all 23 admin screens, both themes). Open in a browser to inspect exact markup, styles and behavior; use browser dev tools to read computed values.
- `Fyndable Post Editor.dc.html` — the single-post editor meta box (accordion sections + tabs, both themes).
- `FYN-LOGO-DIA-LONG300ppi.png` — header logo asset.
- `tokens-colors.css` — the Fyndable design-system colour tokens for reference.

## Where to apply in the real plugin (`wp-content/plugins/fyndable-client`)
- **Shell + nav + top bar + grouping**: `includes/client.php` → `DashboardShell` (renders the full-page shell) and `registerAdminMenu()` (menu order/labels — replace emoji with Dashicons, add section grouping).
- **Theme classes**: `Client::addAdminBodyClass()` already emits `fyndable-ui` / `fyndable-dark` — keep; move all styling onto the CSS variables above.
- **Global styles / tokens / dark overrides**: `assets/client-admin.css` and `assets/admin-modern.css`.
- **Per-screen markup**: each feature's `render*Page()` method (e.g. `renderKeywordsPage`, `renderContentCalendarPage`, `renderLinkManagerPage`, …) — restructure to the card/table/stat patterns above.

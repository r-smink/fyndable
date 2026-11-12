# Handoff: Fyndable GEO Scan Report — PDF redesign

## What this is
A redesign of the **GEO Scan Report PDF** that the Fyndable SaaS Dashboard generates per scan. The design reference is `Fyndable GEO Report.dc.html` (open in a browser — it's an A4, 6-page report). The target to modify in the real codebase is **`wp-content/plugins/fyndable-saas-dashboard/includes/geoscanpdf.php`** (the Dompdf renderer). The data model is unchanged — this is a visual/layout restyle of the existing `buildHtml()` output, plus the two new logos.

## Important: the data model already exists — do not change it
`geoscanpdf.php` already reads every field this design shows, from `$scan['result']`:
- `score` (0–100), `management_headline`, `executive_summary`
- `breakdown` (13 metrics: direct_answer, structure, schema_markup, entities, citation_worthiness, readability, eeat, content_freshness, mobile_friendly, internal_linking, page_metadata, entity_coverage, competitive_gap)
- `platform_visibility` (chatgpt, perplexity, gemini, google_ai_overviews — each `{status, tests, mentions, visibility}`; status ∈ visible|sometimes|not_mentioned|unavailable)
- `competitive_benchmark` (own_visibility, average_competitor, best_competitor, tests)
- `structured_actions` (title, description, priority high|medium|low, category, effort low|medium|high)
- `strengths`, `weaknesses`, `findings`, `recommendations` (string arrays)
- `models_used` ([{model, score}]), `multi_model`
- `keywords_analysis` ([{keyword, has_ai_overview, target_cited, competitor_citations:[{host}]}])
- `url`, `scanned_at`, `language` (nl|en)

Keep the NL/EN `labels()`, `metricLabels()`, `platformLabels()`, `actions()`, status/score helpers and the `page_script` page numbering **as they are**. Only the HTML/CSS in `buildHtml()` and the logo assets change.

## Page structure (6 content pages, matches the reference)
1. **Cover** — dark brand gradient (`#2A1533 → #132A38`, 150°) with two soft radial glows + a faint 44px grid masked to the top. WHITE Fyndable logo top-left. Eyebrow "Generative Engine Optimization", H1 "GEO Scan **Rapport**" (second word gradient-clipped `#C77DFF→#5BC5F2`), subtitle (the `subtitle` label). Bottom glass card: left = Gescande website + Scandatum; right = score ring (gradient stroke) + "GEO-score · {verdict}" pill. Confidential line.
2. **Managementoverzicht** — kicker + `management_headline`, `executive_summary` lead, 3 stat tiles (total score / platforms visible X÷4 / actions found), then "Score per categorie" = 4 summary metrics (structure, schema_markup, direct_answer, citation_worthiness) as icon + label + gradient bar + status + value. Plus a "Belangrijkste bevinding" callout.
3. **Zichtbaarheid per AI-platform** — 2×2 grid of the 4 platforms: coloured monogram chip, name, status badge (Zichtbaar green / Soms genoemd amber / Niet genoemd red / Onvoldoende data gray), a visibility bar, and the copy "Genoemd of geciteerd in {mentions} van {tests} geteste prompts ({visibility}%)." Then "Benchmark t.o.v. concurrentie" (Jouw site / Gemiddelde concurrent / Beste concurrent bars) + snapshot note with `tests` count.
4. **Concrete actiepunten** — "{n} ingrepen, op prioriteit gerangschikt"; each action row: priority chip (Hoog/Midden/Laag) + title + description + category tag + "Inspanning: {effort}".
5. **Vervolgstappen** — 3 numbered steps (from `labels()['steps']`) + dark CTA card ("Klaar om zichtbaar te worden in AI?" + contact fyndable.ai · hallo@fyndable.ai).
6. **Bijlage** — model chips ({model} · {score}/100), Sterke punten / Aandachtspunten / Bevindingen / Aanbevelingen lists, all 13 category scores as bars.
7. **Bijlage — Zoekwoordanalyse** — table: Zoekwoord / AI-overzicht (Ja/Nee) / Jouw site geciteerd (Ja/Nee) / Concurrenten geciteerd (hosts).

(Reference numbers pages 1–6 in the footer after the cover; keep the existing `page_script` which already prints "Pagina X / Y".)

## Brand tokens (already partly in the file — align to these)
- Purple `#8F39AC`, blue `#379FD3`, signature gradient 135°. Ink `#1a1430`, body `#413b52`, muted `#746e82`, lines `#ece9f2`, wells `#f7f5fb`. Dark band `#2A1533→#132A38`.
- Status pairs: visible `#ccf5e2/#087b55`, sometimes `#fff0bf/#aa6200`, not_mentioned `#ffdfe1/#bd202b`, unavailable `#eceef3/#687083`. Priority: high `#ffdfe1/#b71925`, medium `#fff0bf/#a85d00`, low `#ccf5e2/#087b55`.
- Font **Outfit** (the file already embeds `Outfit-Regular.ttf`/`Outfit-Bold.ttf` as data URIs — keep). Headings 700 with negative tracking.
- Inner-page header: 2px bottom rule, logo left ~30px, uppercase "GEO scan rapport · {host}" right. Footer: 1px top rule, host left, page label right.

## The two logos (replace the current ones)
The reference uses two files — add them to the plugin's `assets/` and reference via the existing `logoDataUri()` helper:
- **Cover (dark bg): WHITE logo** → `FYN-LOGO-DIA-LONG300ppi-2fa08701.png` in this bundle. In the plugin this maps to `assets/logo-white.png` (the current `logoDataUri(true)` path). The cover logo must be the **mark + white wordmark, with no extra "Fyndable" text element beside it** (the reference dropped the separate text span).
- **Inner pages (white bg): BLACK logo** → `FYN-LOGO-LONG300ppi.png` in this bundle → `assets/logo_long_black.png` (the current `logoDataUri(false)` / header path). Full-colour mark + black "Fyndable Smart SEO" wordmark.

## Blank vs demo
The reference DC has a `demo` toggle:
- **demo = true** (default) → a fully populated example report (brand "NorthGear Studio", score 68) — this is what a generated report looks like.
- **demo = false** → a blank template: scores show "—", lists show a single placeholder, score 0, status "Onvoldoende data". Useful as the empty-state / layout skeleton.
In the real plugin there is no blank mode — Dompdf always renders real `$scan['result']` data; the blank version is only a design aid. Both are in `Fyndable GEO Report.dc.html` via the toggle.

## Files in this bundle
- `Fyndable GEO Report.dc.html` — the design reference (6-page A4). Flip the `demo` tweak to see blank vs demo.
- `doc-page.js` — the paged-document shell used by the reference (not needed in the PHP/Dompdf target).
- `FYN-LOGO-DIA-LONG300ppi-2fa08701.png` — white logo for the cover (`assets/logo-white.png`).
- `FYN-LOGO-LONG300ppi.png` — black logo for inner pages (`assets/logo_long_black.png`).

## Where to implement
Edit **`wp-content/plugins/fyndable-saas-dashboard/includes/geoscanpdf.php`** → `buildHtml()` CSS + markup to match the reference; drop the two PNGs into `assets/`. The on-screen admin report (`geoscanreport.php`) can optionally be aligned to the same look, but the PDF is the priority. Verify both `cover = 'light'` and `cover = 'dark'` still render (the dark cover is the primary design here).

## Rendering engine: mPDF (switching from Dompdf)
The report will be rendered with **mPDF**, not Dompdf — write the `buildHtml()` CSS for mPDF's engine:
- **Pages & size:** use `@page { margin: ... }` and force page breaks between the 6 sections with `page-break-before: always` (mPDF honours it) or mPDF's `<pagebreak />` tag. A4 portrait. For the full-bleed dark cover, give that page zero margin via a named page (`@page cover { margin: 0; }` + `page: cover`) or mPDF's `<sethtmlpageheader>`-free approach with a full-size positioned block; the inner pages keep a normal margin (≈12–14mm) and the running footer page number.
- **mPDF supports much more CSS than Dompdf** — real gradients via `background: linear-gradient(...)`, `border-radius`, opacity, and most fl/float layout. You can keep the reference's gradients and rounded cards rather than flattening them. BUT mPDF still does **not** support flexbox/grid — convert the reference's `display:flex`/`display:grid` rows into **tables** or `display:inline-block` with widths (the 2×2 platform grid, the stat tiles, the header rows). CSS `gap` does nothing; use cell padding / `border-spacing`.
- **Running header/footer:** use mPDF's `<htmlpageheader>` / `<htmlpagefooter>` + `<sethtmlpageheader>` / `<sethtmlpagefooter>` (or `$mpdf->SetHTMLHeader/Footer`) for the per-page inner-page logo header and the "Pagina X / Y" footer — mPDF exposes `{PAGENO}` / `{nbpg}`. Suppress the header/footer on the cover page (`$mpdf->SetHTMLHeader('','',true)` reset, or a distinct page selector).
- **Fonts:** register **Outfit** in mPDF's `fontdata` (Outfit-Regular.ttf / Outfit-Bold.ttf, already in `assets/fonts/outfit/`) and set it as default, instead of Dompdf's `@font-face` data-URIs. Keep DejaVu Sans as fallback for glyphs.
- **Score ring / icons:** keep the score ring as an inline SVG or pre-rendered SVG data-URI (mPDF renders SVG). The reference uses Dashicons glyphs in the category icons — in mPDF either embed the Dashicons font or swap those for small inline SVGs / the Unicode marks the product already uses.
- **Colour fidelity:** mPDF renders CSS colours directly (no `print-color-adjust` needed).
Net: you can stay closer to the reference's visual richness than Dompdf allowed; the main porting work is flex/grid → tables and the header/footer/page-break mechanics.

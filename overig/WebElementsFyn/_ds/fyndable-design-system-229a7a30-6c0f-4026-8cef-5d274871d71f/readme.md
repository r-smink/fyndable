# Fyndable Design System

**Fyndable Smart SEO** is a multi-tenant, AI-powered SEO platform for WordPress, sold as a subscription and resold white-label by agencies. It ships as two WordPress plugins that talk to each other over REST:

1. **Fyndable SaaS Dashboard** (provider side) — licence generation and validation, tenant repository, AI/SERP API gateway, usage and cost tracking, white-label branding, tiered plans (Starter €99 → Agency €499 per month).
2. **Fyndable Client** (customer side) — the plugin installed on a customer's WordPress site. 40+ SEO features: AI content writer, content optimizer, briefs, ideas, keyword tracking, topic clusters, rank tracker, Search Console + GA4 + Google Ads integration, schema/FAQ markup, internal-link assistant, sitemaps, hreflang, redirects, A/B testing, content-decay monitoring, E-E-A-T validation.

Both surfaces live **inside the WordPress admin**. That is the single most important context for anyone designing for Fyndable: the design system does not own the page chrome, it owns a band of interface that starts below WordPress's own admin bar and to the right of WordPress's own menu.

## Sources used to build this system

| Source | What came from it |
| --- | --- |
| `uploads/Fyndable_CVI.pdf` (brand guidelines) | The brand: colours `#8F39AC`, `#379FD3`, `#333333`, `#E6E7E8`, the 135° purple→blue gradient, the Outfit variable typeface, logo lockups, the circular-arrow mark (extracted to `assets/fyndable-mark.png`) |
| `uploads/FYN-LOGO-SHORT.png` | The horizontal logo lockup (`assets/fyndable-logo-horizontal.png`) |
| **GitHub — https://github.com/r-smink/fyndable** (branch `main`) | Component inventory, exact paddings/radii/shadows/type sizes, screen structure, real copy, feature names, menu labels. Read: `README.md`, `wp-content/plugins/fyndable-client/assets/client-admin.css`, `.../assets/admin-modern.css`, `.../includes/seodashboard.php`, `.../includes/gscdashboard.php`, `.../includes/client.php` (menus, AI-tools grid), `wp-content/plugins/fyndable-saas-dashboard/assets/license-admin.css` |
| **GitHub — https://github.com/WordPress/dashicons** | The Dashicons icon font, copied into `assets/dashicons/` (the product's real icon set) |

Explore the Fyndable repository further when you need behaviour, feature copy or a screen this kit does not cover — the PHP feature classes in `wp-content/plugins/fyndable-client/includes/` are the ground truth for what each screen actually does.

### Colour rebase — read this before using the CSS in the repo

The repository's CSS predates the brand identity. It uses an orange primary (`#FF4D00`), a generic indigo/violet gradient (`#667eea → #764ba2`), and a placeholder page background gradient (`blue → pink → orange`). **None of those are Fyndable colours.** The CVI is authoritative, so this design system rebases every one of those roles onto the brand palette while keeping the repo's exact geometry (paddings, radii, borders, shadows, type sizes). Where you see purple where the repo has orange, that is deliberate.

## Content fundamentals

The product speaks like a competent colleague, not like a marketing site.

- **Voice:** second person, active, imperative. "Connect this site", "Analyze site", "Load data", "Review your title tag and meta description for better CTR." Never "we"; the product does not narrate itself.
- **Casing:** sentence case for descriptions and helper text; **Title Case for feature and screen names** ("Content Optimizer", "Rank Tracker", "Topic Clusters", "Quick Wins"). Buttons are sentence case ("Analyze site", "Add keyword", "Save settings").
- **Length:** one line. Screen descriptions are a single sentence, often ending with what to do next: *"Performance data from Google Search Console. Connect via Fyndable → Settings to enable."*
- **Numbers carry the message.** Findings are phrased count-first: "42 posts missing meta description", "14 posts have thin content (<300 words)", "118 posts analyzed". Never "some posts".
- **Explain the fix, not the rule.** *"Good content but missing SEO meta — easy to fix with AI."* / *"Your content may be outdated. Consider adding new insights, statistics, or recent developments."* Em dashes are used for exactly this aside-then-payoff construction.
- **Verdicts are blunt, three-valued:** Great / Needs Work / Poor. Low / Medium / High. Nothing softer.
- **Occasional light exclamation** in positive states — "No issues found!", "easy to fix!" — and nowhere else.
- **Emoji:** used in exactly one place: the WordPress admin **menu labels** (`📊 Dashboard`, `🤖 AI Tools`, `🎯 Keywords`, `💡 Ideas`, `⚙️ Settings`). This is a WordPress-menu convention for scannability at 13px. Do **not** use emoji in headings, body copy, buttons, badges or cards.
- **Unicode marks:** `✓` and `✕` for included/locked features, `●▲ℹ` for issue severity, `‹ ›` in pagers. These are literal characters in the product, not icons.
- **Language:** English UI with a Dutch translation (`nl_NL`); the company is Dutch. Keep copy short enough that Dutch (≈20% longer) still fits.

## Visual foundations

**Type.** One family: **Outfit** (variable, 200–700). Geometric, near-circular, slightly technical — it reads as software, not editorial. Page titles 28px/700 at `-0.025em`; card titles 18px/600; body 14–15px; table cells 13px; micro-labels 12px/600 **uppercase with 0.05em tracking**. Machine values (licence keys, endpoints, tenant ids) are monospace with 1px letter-spacing on a gray-50 well. Nothing below 11px.

**Colour.** Purple `#8F39AC` leads, blue `#379FD3` supports, and the **135° purple→blue gradient is the brand signature** — used for the primary button, the 4px accent bar on stat cards, 48px icon chips, progress fills, and gradient-clipped text on one word of a hero line. Body text is `#333333`, not black. Neutrals are the product's own gray ramp (50→900). Semantic states always ship as a **solid + a tint pair** (e.g. `#10b981` on `#d1fae5`) — the tint is the background, the solid is the text and the rule. Maximum two background fields per screen.

**Backgrounds.** No photography, no illustration, no texture, no pattern anywhere in the product. Two field types only: a flat light `--surface-page` (SaaS dashboard) or the full-bleed brand gradient with translucent cards floating on it (client plugin). Above either sits the dark header band (`#2A1533 → #132A38`, 135°) with 30px/40px padding.

**Transparency and blur.** Exactly one use: cards over the gradient field are `rgba(255,255,255,.95)` + `backdrop-filter: blur(10px)`. Never blur over light backgrounds, never a translucent overlay other than the modal's 50% black scrim.

**Cards.** White, 12px radius, no border, `0 10px 15px -3px rgba(0,0,0,.1)`; nested cards drop to the 1px/3px shadow so the hierarchy stays readable. Optional header strip: 20px/25px padding, 1px `--border-subtle` bottom rule, 18px semibold title with a purple Dashicon. Body padding 25px (40–60px for the centred connection card). **Interactive** tiles (tool cards) differ: 2px gray border, 6px radius, no shadow at rest.

**Borders.** 1px `--fyn-gray-200` for structure; **2px for anything the user can interact with** — form controls, tool cards, outline buttons — turning purple on focus/hover. Table headers close with a 2px bottom rule; rows separate with 1px.

**Corner radii.** 12px cards and icon chips, 8px buttons and notices, 6px inputs and small tiles, 20px pills and badges, 50% dots, score rings and empty-state wells. Nothing square, nothing above 12px except pills.

**Shadows.** Outer only, always neutral black at low alpha — except the primary button and hovered tiles, which cast a **purple** shadow (`rgba(143,57,172,.2–.3)`). No inner shadows, no glows, no protection gradients: text over the gradient field is always white on a dark band or black on a card, never floating on the gradient itself.

**Motion.** `all .2s ease` on everything; `width .3s ease` on progress fills; `stroke-dasharray 1s ease` on the score ring; `0.3s fadeIn` (10px rise) for content arriving after a fetch. Hover = lift, never grow: cards and buttons `translateY(-2px)`, tool tiles `translateY(-4px)`. There is no press animation in the product — the press state is the colour change plus loss of lift. Disabled = 55% opacity, no lift.

**Hover states.** Primary button: lift + stronger purple shadow (never a colour change). Secondary: `gray-100 → gray-200`. Outline/tool card: border turns purple, background goes to white or purple tint. Table rows: `gray-50`. Pills: fill with their own accent colour, text flips to white. Icon buttons: tinted background in their tone.

**Layout.** Max content width 1400px (forms 900px, the connection card 600px). Page padding 40px. Grid gaps 20px (15px for tight stat rows, 25px between sections). Stat grids are `repeat(auto-fit,minmax(250px,1fr))`. Nothing is sticky or fixed inside the plugin — WordPress owns the fixed chrome. Breakpoints: 1200px (4-up → 2-up), 1024px (2-up → 1-up), 782px (WordPress's own mobile admin breakpoint), 480px.

**Imagery.** There is none, and that is a deliberate constraint of this system. If a design needs an image, ask the user for one rather than inventing a style.

## Iconography

- **The icon set is WordPress Dashicons** — the product uses it exclusively (`<span class="dashicons dashicons-chart-line">`). The real font is vendored in **`assets/dashicons/`** (CSS with the base64 woff inline, plus `.woff2`/`.ttf`) and is loaded through `styles.css`, so every card, kit and mock renders the genuine glyphs. Nothing here is hand-drawn.
- Icons appear in three ways: **bare purple glyph** (card titles, buttons, table actions, inline), **48px gradient chip** (quick actions, stat cards, feature tiles), **40px glyph in an 80px gray well** (empty states).
- Default Dashicons metrics are 20px/20px; scale via `font-size` + matching `width`/`height`.
- Common glyphs in Fyndable: `chart-line`, `chart-bar`, `search`, `lightbulb`, `edit`, `admin-links`, `images-alt2`, `calendar-alt`, `update`, `trash`, `media-document`, `admin-settings`, `yes-alt`, `warning`, `info`, `plus`, `admin-network`, `art`, `cloud`.
- **Emoji** are only ever menu-label prefixes (see Content fundamentals). **Unicode** `✓ ✕ ● ▲ ℹ ‹ ›` are used as literal marks in feature lists, issue lists and pagers.
- The brand mark (`assets/fyndable-mark.png`) is a *logo*, not an icon — do not put it in an icon slot.

## Index

Root files:
- `styles.css` — the single entry point consumers link. `@import`s only.
- `tokens/` — `colors.css`, `typography.css`, `spacing.css`, `elevation.css`, `fonts.css`, `base.css`.
- `assets/` — `fyndable-logo-horizontal.png`, `fyndable-mark.png`, `dashicons/` (icon font).
- `guidelines/` — 18 foundation specimen cards (Brand, Colors, Type, Spacing).
- `thumbnail.html` — homepage tile.
- `SKILL.md` — Agent-Skills wrapper for using this system outside this project.
- `github.md` — upstream source association and screen map.

### Components

25 components, grouped by concern. Each has a `.jsx`, a `.d.ts` props contract and a `.prompt.md` usage note; each directory has a `@dsCard` HTML showing its states.

**`components/core/`** — `Button`, `IconButton`, `Badge`, `StatusPill`, `Spinner`, `Tooltip`
**`components/layout/`** — `PageHeader`, `Card`, `ToolCard`, `QuickAction`, `Tabs`, `NavPills`, `EmptyState`
**`components/data/`** — `StatCard`, `ProgressBar`, `ScoreRing`, `DataTable`, `Pagination`, `SerpPreview`, `AnalysisList`, `FeatureList`
**`components/forms/`** — `FormField`, `LicenseKeyField`
**`components/feedback/`** — `Alert`, `Modal`

Import them as `const { Button } = window.FyndableDesignSystem_229a7a` after loading `_ds_bundle.js`.

#### Intentional additions

The repo is plain PHP + CSS, so it defines *styles*, not React components; the component list above is a one-to-one reading of the CSS/markup families it does define. Two entries are named more usefully than their source:

- **`FormField`** consolidates the repo's separate `.sseo-input` / `.sseo-select` / `.sseo-textarea` rules into one control with an `as` prop — the label/description/focus treatment is identical across all three in the source.
- **`LicenseKeyField`** wraps the repeated "monospace value in a gray well" pattern (`.detail-value`, `.sseo-ai-license-input`) that appears on the connection, licence and settings screens.

There is **no** Toast, Avatar, Accordion, Breadcrumb or Switch component, because the product has none.

### UI kits

- **`ui_kits/wp_client/`** — the customer plugin inside the WordPress admin: Statistics, AI Tools, Search Console, Keywords, Connection. See its README for the file-by-file provenance.
- **`ui_kits/saas_dashboard/`** — the provider console: Overview, Licences, Tenants & usage, White-label, Settings.

Both are click-through: navigation, filters, modals, licence activation and the analyze/load actions all work against fake data.

## Known gaps

- **Fonts are not self-hosted.** No Outfit binaries were supplied, so `tokens/fonts.css` loads Outfit from Google Fonts. Drop the real `.woff2` files into `assets/fonts/` and swap in local `@font-face` rules.
- **No slide template** exists — none was provided, so none was invented.
- **No product photography or illustration** exists in the sources.
- Screens not recreated (they exist in the plugin): Content Calendar, Ideas, Created Posts, Link Manager, Sitemaps, Integrations, LLM Tracker, Topic Clusters, Site Audit, Rank Tracker, A/B Testing. The client kit routes them to an explicit "not recreated" state.

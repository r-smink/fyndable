# Handoff: Fyndable SaaS Dashboard — provider + agency console restyle

## Overview
A full redesign of the **Fyndable SaaS Dashboard** plugin admin (`wp-content/plugins/fyndable-saas-dashboard`) — the provider/agency side that sells and manages Fyndable licences. It replaces the current plain WP-admin pages with the same shell language used in the Client plugin redesign:
- A dark brand top bar (logo, **Provider / Agency portal switcher**, global search, light/dark toggle, notifications, account).
- A **grouped sidebar nav** (labelled sections) using **Dashicons** — different nav per portal.
- **Light + dark themes** driven entirely by CSS custom-property overrides.
- Dense, full-width content on every screen.

The design mirrors the Client handoff (same tokens, same shell), so if you implemented that, reuse the same CSS variables and shell CSS here.

## About the design files
`Fyndable SaaS.dc.html` is a **design reference** — an HTML/React prototype showing the intended look, layout, theming and interaction. It is **not production code to paste in**. The target is the existing plugin: **PHP page renderers + CSS** (`includes/saasdashboardshell.php`, `licenseadmin.php`, `agencyportal.php`, `saassettings.php`, the feature-admin classes, and `assets/license-admin.css`). Recreate this design in that PHP/CSS environment using its existing structure — port the tokens and markup patterns below into the plugin's CSS and the shell/page PHP.

## Fidelity
**High-fidelity.** Final colours, type, spacing, radii, shadows and interactions — recreate pixel-accurately. All values under **Design tokens** (identical to the Client handoff).

---

## Two portals, one shell
The SaaS side is two consoles that share the shell; a segmented **Provider / Agency** switch in the top bar flips between them (in the real plugin these are two separate WP menu trees: `sseo-ai-licenses` and `sseo-ai-agency` — the switch maps to navigating between the two menu roots). The dark top bar gradient (`#2A1533 → #132A38`) is used in both themes.

Top bar left→right: logo + portal micro-label ("SaaS Provider" / "Agency Portal") · Provider/Agency segmented switch · global search (⌘K) · spacer · theme toggle (☀/☾) · notifications bell (red dot) · divider · account avatar + name/role.

Body: 252px sidebar (`var(--shell-sidebar)`, 1px right border, scrollable) + scrollable main (`padding:28px 32px 48px`, inner `max-width:1460px;margin:0 auto`, `.3s` fade-in on screen change). Each screen opens with a breadcrumb (`Provider › <crumb>` or `Agency › <crumb>`), H1 27px/700, one-line description, a secondary **Ververs** button + a primary gradient action button.

### Provider nav (grouped, Dutch label + Dashicon)
Section headers 10.5px/700 uppercase, `letter-spacing:.07em`, `color:var(--nav-section)`.
- **Overzicht** — Dashboard (`chart-bar`)
- **Licenties** — Genereer keys (`admin-network`), Alle licenties (`list-view`), Agency-accounts (`groups`), Client-versies (`update`)
- **Klanten** — Tenants (`admin-multisite`), Gebruik & rapporten (`chart-area`)
- **Financieel** — Omzet (`chart-line`), Kosten-dashboard (`money-alt`), Checkout (`cart`), Boekhouding (`media-spreadsheet`)
- **Groei & support** — Support (`sos`, badge), Feedback (`format-status`, badge), GEO Scans (`visibility`), E-mail templates (`email-alt`), Chatbot (`format-chat`)
- **Systeem** — AI-modellen (`superhero-alt`), White-label (`art`), Instellingen (`admin-settings`)

### Agency nav
- **Overzicht** — Dashboard (`chart-bar`)
- **Licenties** — Genereer licenties (`admin-network`), Alle licenties (`list-view`), Extra licenties (`plus-alt2`)
- **Klanten** — Tenants (`admin-multisite`), Gebruik & kosten (`chart-area`)
- **Financieel** — Facturen (`media-text`), Mijn account (`admin-users`)
- **Support** — Support (`sos`, badge), White-label (`art`)

Nav item: flex row `gap:11px`, `padding:9px 11px`, `border-radius:9px`, 13.5px/600, 17px Dashicon. Rest hover → `background:var(--nav-hover)`, `color:var(--text-h)`. Active → `background:linear-gradient(135deg,#8F39AC,#379FD3)`, `color:#fff`, `box-shadow:0 5px 14px rgba(143,57,172,.32)`. Support/Feedback carry a red count badge. Below the nav: a plan/quota card with a usage progress bar (provider: platform status; agency: quota used).

---

## Theming — CSS custom properties (identical to Client handoff)
Light is default; `fyndable-dark` on the body flips it. Port both sets into `license-admin.css`:

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

---

## Brand design tokens (Fyndable design system — do not invent values)
- **Brand:** purple `#8F39AC`, blue `#379FD3`, ink `#333333`, mist `#E6E7E8`. **Signature gradient** `linear-gradient(135deg,#8F39AC 0%,#379FD3 100%)` (progress fills / flat bars use the 90° form). Tints: purple `#F4E9F8`, blue `#E6F3FB`.
- **Semantic (solid + tint, tint=bg / solid=text):** success `#10b981`/`#d1fae5` (text `#047857`), warning `#f59e0b`/`#fef3c7` (text `#92700a`), danger `#ef4444`/`#fee2e2` (text `#b91c1c`), info `#3b82f6`/`#dbeafe` (text `#1d4ed8`), accent `#a855f7`/`#f3e8ff` (text `#9333ea`).
- **Tier colours (used on tier badges):** Trial/Starter = info blue, Professional = accent purple, Business = brand purple, Agency = success green, Free = neutral gray, Lifetime = warning amber.
- **Type:** Outfit (200–700). Page title 27px/700 `-0.025em`; card title 16–18px/700; body 13–15px; table cells 13px; micro-labels 11px/700 uppercase `letter-spacing:.04–.05em`. Nothing below 11px. Monospace (licence keys, model IDs, endpoints) on a `--well` field, `letter-spacing:.5px`.
- **Radii:** cards 13–14px, icon chips 11–12px, buttons 8–9px, inputs 8–9px, small tiles 6–7px, pills/badges 999px, dots 50%.
- **Shadows:** neutral low-alpha outer (`--card-shadow`); primary button + hovered tiles cast a **purple** shadow `rgba(143,57,172,.2–.3)`.
- **Motion:** `all .2s ease`; progress `width .3s`; ring `stroke-dasharray 1s`; content fade-in `.3s` + 8px rise. Hover = lift (cards/buttons `-2px`, tiles `-4px`), never grow.
- **Icons:** WordPress **Dashicons** only (vendored). No emoji except WP top-level menu labels. Unicode `✓ ✕ ★ ☆ ‹ ›` as literal marks.

Recurring **stat tile** (4-up grid, `gap:16px`): card `padding:18px 20px` with a 3px gradient top-accent bar; top row = 11px/700 uppercase label + 17px purple Dashicon; value 30px/700 `-0.02em`; footer = optional semantic delta pill + muted sub-text. Tables: header row 11px/700 uppercase `--text-m` + 2px `--divide` bottom rule; body rows 13px, 1px `--divide` separators, hover `--row-hover`.

---

## Screens / Views

### Provider console
1. **Dashboard** — 4 stat tiles (totaal licenties, vandaag, deze maand, actieve tenants); 3 breakdown cards (per status / tier / platform, coloured dots + counts); recent-licences table (monospace key, tier badge, status badge, assigned, created).
2. **Genereer keys** — two-col: a licence-generation form (aantal, type, tier, max sites, rate limit, maand-API-limiet, verloopt-na, toewijzen-aan) + a side "Tiers" reference card (name badge, spec, price). Tier change updates limit defaults.
3. **Alle licenties** — search + filter dropdowns (status/type/tier/platform) + Export CSV; dense table (monospace key, type, tier badge, status badge, platform w/ icon, assigned, expires, Features/Intrekken actions) + pager "1–10 van 1.284".
4. **Agency-accounts** — 4 stat tiles; table of reseller agencies (icon chip + name/email, quota-usage bar `used/quota`, revenue/mnd, status badge, Beheer).
5. **Client-versies** — 4 stat tiles; version-distribution table (version, description, sites, status badge) + an "upload new version" side card with a dashed drop zone.
6. **Tenants** — search + filters; 2-col grid of tenant cards (site icon, domain/email, status badge, tier badge + platform + site count, API-usage bar colour-graded by %).
7. **Gebruik & rapporten** — a 30-day API-usage + cost area chart (SVG, two series w/ legend); a "kosten per provider" side card (bars) with month total; top-usage-per-tenant table (tier, calls, limit bar, cost, margin coloured).
8. **Omzet** — 4 stat tiles (MRR/ARR/nieuwe klanten/churn); a 12-month MRR bar chart (gradient bars); "omzet per tier" bars.
9. **Kosten-dashboard** — 4 stat tiles; kosten-per-model table (monospace model, use, cost, share); kosten-per-tier bars.
10. **Checkout** — 2×2 grid of tier cards with a visibility toggle each (featured = purple border + "Populair" ribbon, price, spec) + a checkout-settings side card (self-serve, trial, provider, currency, VAT).
11. **Boekhouding / Facturen** — 4 stat tiles (omzet jaar, openstaand, betaald mnd, BTW); invoices table (monospace number, client, date, amount, status badge, PDF action).
12. **Support** — 4 stat tiles; tickets table (chat icon chip + subject/id, from, priority badge, status badge, age).
13. **Feedback** — 4 stat tiles (NPS, feedback count, feature requests, avg rating); stacked feedback cards (quote, source, ★ rating, tag badge).
14. **GEO Scans** — 4 stat tiles; scans-per-site table (domain, models list, visibility %, status badge, last scan).
15. **E-mail templates** — templates table (icon chip + name, trigger, status badge, Bewerk) + an automations side card (name, detail, sent count).
16. **Chatbot** — 4 stat tiles; config card (enabled, model, knowledge source, escalation, languages) + a chat-preview card (user/bot bubbles, user = gradient right-aligned, bot = well left-aligned).
17. **AI-modellen** — 3 provider cards (OpenRouter/OpenAI/DataForSEO, connected badge, model summary); model-routing-per-feature table (feature, monospace model, niveau badge, Wijzig).
18. **White-label** — branding form (merknaam, menu-label, support-email, primary/secondary colour swatch fields, enabled toggle) + a live "client-header preview" card showing the rebranded dark header.
19. **Instellingen** — a 5-tab bar (API-credentials / DataForSEO / Beeldgeneratie / Integraties / E-mail-SMTP); per-tab form (selects, monospace secret fields, toggles) + a side "Status" card (per-integration connected/not-tested badges). Save + Verbinding testen buttons.

### Agency portal
20. **Dashboard** — 4 stat tiles (je licenties/actief/beschikbaar/MRR); a gradient welcome banner (agency name, plan, generate/buy buttons); licence-distribution card (donut + legend); monthly-revenue card (big number + delta + sparkline); recent client-sites table.
21. **Genereer licenties** — same form as provider Genereer keys (from the agency's own quota).
22. **Alle licenties** — same table pattern as provider, scoped to the agency; 4 stat tiles (quota/actief/beschikbaar/verlopen).
23. **Extra licenties** — 4 pricing-package cards (featured = purple border + "Beste waarde", licences count, price, per-licence, Koop pakket).
24. **Tenants** — same tenant-card grid as provider, scoped to the agency.
25. **Gebruik & kosten** — same usage chart + table as provider, scoped; 4 stat tiles.
26. **Facturen** — same invoices table as provider Boekhouding (the agency's own invoices to Fyndable).
27. **Mijn account** — company-details form (bedrijfsnaam, email, KvK, BTW, adres) + a team-members side card (avatar initials, name/email, role badge).
28. **Support** — same tickets table as provider.
29. **White-label** — same branding form + preview as provider White-label.

## Interactions & behaviour
- **Portal switch** flips Provider ↔ Agency (two WP menu roots in the real plugin); remember last screen per portal.
- **Theme toggle** sets `fyndable-dark` on the body — persist to an option and apply on load.
- **Nav** sets active page (normal `admin.php?page=…` links); active = gradient pill.
- **Settings tabs** switch the active form panel (active tab = purple text + 2px purple underline) — the real page already uses `.nav-tab` tabs; keep the panels, restyle.
- **Hover:** cards/buttons lift `-2px`; tiles `-4px`; table rows tint `--row-hover`; nav items tint `--nav-hover`; outline buttons → purple border + purple-tint fill.
- Responsive: 4-up stat grids → 2-up < 1200px → 1-up < 1024px; respect WP's 782px admin breakpoint.

## Assets
- **`FYN-LOGO-DIA-LONG300ppi.png`** — white horizontal Fyndable lockup for the dark top bar. Included; drop into `assets/`.
- **Dashicons** — already vendored / core WP. Use the glyph names listed per nav item and screen.
- No photography/illustration (deliberate brand constraint).

## Files in this bundle
- `Fyndable SaaS.dc.html` — the full interactive design reference (all provider + agency screens, both themes, portal switch). Open in a browser; use dev tools to read computed values.
- `FYN-LOGO-DIA-LONG300ppi.png` — header logo asset.
- `tokens-colors.css` — Fyndable design-system colour tokens for reference.

## Where to apply in the real plugin (`wp-content/plugins/fyndable-saas-dashboard`)
- **Shell + top bar + portal switch + nav grouping**: `includes/saasdashboardshell.php` (the provider shell) and the menu registrations in `licenseadmin.php` (`register()`), `saassettings.php` (`addSettingsMenu()`), and the feature-admin classes; `includes/agencyportal.php` for the agency menu tree.
- **Theme classes**: add a `fyndable-ui` / `fyndable-dark` body class (mirror the Client plugin's `addAdminBodyClass()` mechanism) and move all styling onto the CSS variables above.
- **Global styles / tokens / dark overrides**: `assets/license-admin.css` (and any feature-admin CSS: `bookkeeping-admin.css`, `geo-scan-admin.css`, `chatbot-admin.css`, `revenue.css`).
- **Per-screen markup**: the `render*` methods — `LicenseAdmin::renderLicenseDashboard/renderGeneratePage/renderAllLicenses/renderTenantsPage/renderUsageReports/renderAgencyAccountsPage`, `SaaSSettings::renderSettingsPage/renderCostDashboard/renderAiModelsPage/renderClientVersionsPage/renderCheckoutPage`, `RevenueDashboard`, `BookkeepingAdmin`, `SupportAdmin`, `FeedbackAdmin`, `GeoScanAdmin`, `EmailTemplateAdmin`, `ChatbotAdmin`, `WhiteLabelAdmin`, and `AgencyPortal::render*` (Dashboard, GeneratePage, LicensesPage, TenantsPage, TenantDetailPage, UsagePage, SupportPage, WhiteLabelSettings, AccountPage, InvoicesPage, AddLicensesPage).

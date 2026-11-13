# Handoff: Fyndable Client Portal (customer self-service)

## Overview
A redesign of the **customer self-service portal** inside the Fyndable SaaS Dashboard — the area where **non-agency** customers view their licence, invoices and subscription. Built in the Fyndable style (dark brand top bar, grouped sidebar, Dashicons, light + dark, Dutch). It is the single-account counterpart to the provider/agency consoles — **no provider/agency portal switcher**.

## About the design files
`Fyndable Klantportaal.dc.html` is a **design reference** — an HTML/React prototype of the intended look, layout, theming and interaction. It is **not** production code to paste in. The target is the existing SaaS Dashboard plugin: **PHP page renderers + CSS** under `wp-content/plugins/fyndable-saas-dashboard/`. Recreate this design there using the plugin's existing structure (the same shell/token system already used by the provider console). This is a presentation restyle + a new customer-scoped area, not a rebuild of billing logic.

## Fidelity
**High-fidelity.** Final colours, type, spacing, radii, shadows, interactions. All tokens below are the same light/dark variable sets used across the other Fyndable redesigns in this project (see `design_handoff_saas_dashboard/README.md` for the shared shell spec — top bar, sidebar nav, page header, stat tiles, tables, badges).

## Theming
Reuse the shared `--shell-*`, `--card`, `--text-*`, `--well`, `--divide`, `--input-*`, `--sev-track` CSS variables (light default, `.fyn-dark` override) exactly as in the client/SaaS handoffs. The dark top-bar gradient `#2A1533 → #132A38` is used in both themes.

## Shell
- **Top bar** (58px): Fyndable logo + "Mijn account" label, search, theme toggle, notifications bell, and an account chip (company initials + name + email) — **no** provider/agency switcher.
- **Sidebar** (248px), grouped:
  - **Overzicht** — Overzicht (`chart-bar`)
  - **Abonnement** — Mijn licentie (`admin-network`), Facturen (`media-text`, unread badge), Abonnement (`star-filled`)
  - **Account** — Instellingen (`admin-settings`)
  - Plan card at the bottom (Business €199/mnd, verlengt 14 nov) with a "Beheer abonnement" button → Abonnement screen.
- **Page header**: breadcrumb `Mijn account › <crumb>`, 27px title, one-line description, one primary gradient action button per screen.

## Screens
1. **Overzicht** — 4 stat tiles (Abonnement = Business €199/mnd, Volgende incasso, Openstaand €0,00, Actieve sites 3/3) each with a 3px gradient top accent; a recent-invoices table (last 4, status badges); a licence card (Actief pill, Business · 3 sites, monospace licence key with copy icon, auto-renew date).
2. **Mijn licentie** — licence card: success chip "Licentie actief", full monospace licence key, list of the 3 connected sites (site icon, domain, connected-since, Actief badge, "Ontkoppel" button). Side card "Inbegrepen" with the Business feature list (✓).
3. **Facturen** — 3 stat cards (Betaald dit jaar, Openstaand, Volgende factuur) + full invoices table (Factuurnr. mono, Datum, Periode, Bedrag, Status badge [Betaald/Openstaand/Te laat/Komend], per-row "PDF" download button).
4. **Abonnement** — current-plan card (Business €199/mnd, Actief pill; next charge / billing cadence / sites; "Upgrade naar Agency", "Wijzig facturatie", "Opzeggen" danger-outline); a usage card (API-calls, AI-artikelen, GEO-scans progress bars, amber when near cap); a payment-method card (card brand •••• 4242, "Betaalmethode wijzigen", invoice/billing details).
5. **Instellingen** — account fields (Bedrijfsnaam, E-mail, BTW-nummer, Adres) + preference toggles (Factuur-e-mails, Verloop-herinneringen, Productupdates) + Save.

## Interactions & behaviour
- Nav sets the active screen (normal `admin.php?page=…&view=…` links in the plugin); active = gradient pill. Theme toggle flips `.fyn-dark` (reuse the existing `fyndable-dark` body-class mechanism). Table rows tint `--row-hover` on hover; buttons/cards lift `-2px`. PDF buttons link to the existing invoice-PDF endpoint; licence-key copy uses the existing copy handler.
- **Customer scope only:** every query is scoped to the logged-in customer's own tenant — this area must never expose other tenants, provider totals, or agency reseller data. Keep the existing capability/ownership checks.

## Where to apply in the real plugin (`wp-content/plugins/fyndable-saas-dashboard/`)
- **Shell + customer nav**: the dashboard shell renderer (same one the provider console uses) — add a customer-scoped menu with the 4 items above; hide the provider/agency switcher for the customer role.
- **Licence + sites**: `includes/licenseadmin.php` (customer-scoped: own licence + connected sites only).
- **Invoices / billing / plan**: `includes/bookkeepingadmin.php` / the billing + checkout classes (`revenuedashboard.php`, `saassettings.php` for payment method) — read-only customer views + the existing cancel/upgrade/payment-update actions.
- **Settings**: the account/profile renderer.
- **Styles / tokens**: `assets/*.css` (reuse the light + `.fyn-dark` variable sets from the SaaS/client handoffs).
- **Do not change**: billing/charge logic, licence validation, the Shopify/WordPress REST endpoints, or capability checks — presentation + customer-scoped read views only.

## Files in this bundle
- `Fyndable Klantportaal.dc.html` — the full interactive design reference (5 screens, light + dark). Open in a browser; use dev tools to read computed values.
- `FYN-LOGO-DIA-LONG300ppi.png` — white top-bar logo.
- `tokens-colors.css` — Fyndable design-system colour tokens for reference.

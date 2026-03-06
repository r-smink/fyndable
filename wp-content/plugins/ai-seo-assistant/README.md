# AI SEO Assistant (MVP)

Self-contained WordPress plugin that lets you choose between SERP API or a built-in scraper for keyword/SERP snapshots. Targets PHP 8.1+ and WordPress 6.4+.

## Features (current)
- Pluggable SERP providers: `api` (SerpApi), `dataforseo` (Basic Auth), or `scrape` (cURL-based Google fetch).
- Settings page: select provider, SerpApi key, DataForSEO login/password, country, and enable fallback.
- Fallback: if enabled, tries the selected provider, then the others in order SerpApi → DataForSEO → Scrape.
- Healthcheck: button on SERP Snapshots screen to validate a provider with a test query.
- Hourly cron hook `aiseoassistant_serp_snapshot` to snapshot tracked keywords (filter-driven).
- Action hooks:
  - `aiseoassistant_tracked_keywords` — return array of keywords to track.
  - `aiseoassistant_snapshot_saved` — receives keyword + result array per run.
- Persistence: snapshots stored in `wp_aiseoassistant_snapshots`; view/filter/export in Admin > AI SEO > SERP Snapshots.
- CSV export + keyword filter in snapshots view.
- Manual fetch UI: run a keyword on demand from the snapshots screen.
- Gutenberg sidebar: “AI SEO Outline” sidebar; calls REST `aiseoassistant/v1/outline` and inserts the outline into the editor (uses chosen LLM, fallback OpenAI → Anthropic → Mistral). Supports preset dropdown + tone field.
- LLM healthcheck button in AI SEO settings.
- Daily LLM healthcheck cron keeps last 10 results; latest status shown in settings.
- Daily SERP healthcheck cron (selected provider) logs status in the same health log.
- Health log export (CSV) and clear button; cron covers all SERP providers.
- Dashboard (Admin > AI SEO > Dashboard) met bar-chart snapshots per provider + laatste healthchecks + laatste keywords.
- KPI-kaarten op dashboard: tracked keywords, gemiddelde beste positie, % top3; webhook-alerts bij healthcheck errors (Slack/webhook URL in settings).
- Dashboard donut-chart voor health status mix; alerts hebben emoji + TYPE • provider — melding.
- Topical Map: seed expand (n-grams uit SERP titels) + cluster tracked keywords; AI Overview tracker (cron + admin).
- Competitor Gap: SERP markering self/competitor/other + coverage.
- Editor actions: Outline, Rewrite/Improve/Expand/CTA, FAQ, Interne links, Afbeelding genereren — via Gutenberg sidebar -> REST `aiseoassistant/v1/editor-action` / `editor-image` (met diff/apply voor rewrite/improve/expand/cta).
- Audit: dunne content/H2/internal links/excerpt check + cannibalization op tracked keywords.
- Bulk Actions: lijst posts zonder meta/FAQ; genereer meta + FAQ via LLM per klik.
- Technical audit: HEAD check (status, canonical header, hreflang) + optionele PageSpeed (mobile) via API key.
- Technical audit uitgebreid: body check voor canonical/hreflang/JSON-LD + lichte schema validatie; PSI strategy selectable (mobile/desktop).
- Knowledge base & prompt library: CPT `aiseo_note` en `aiseo_prompt`; sidebar kan prompts en notes meesturen in acties.
- AI Image Gen: admin scherm + sidebar actie “Afbeelding genereren” (OpenAI `gpt-image-1`).
- GSC stub + Content Calendar CPT: configureer GSC proxy endpoint, fetch top queries, en beheer geplande items via `aiseo_calendar`.

## Install
1. Copy the `ai-seo-assistant` folder into `wp-content/plugins/`.
2. Activate the plugin in WP Admin.
3. Go to **AI SEO** menu and pick provider:
   - API: add your SerpAPI key.
   - Scrape: no key, but ensure your host allows outbound HTTPS and respect local law/robots.txt.

## Usage examples
- On your theme/plugin, add keywords to track:
  ```php
  add_filter('aiseoassistant_tracked_keywords', function () {
      return ['ai seo plugin', 'best seo ai'];
  });
  ```
- Listen for snapshots to store or display:
  ```php
  add_action('aiseoassistant_snapshot_saved', function ($keyword, $results) {
      // Persist to custom table or log.
      error_log($keyword . ': ' . wp_json_encode($results));
  }, 10, 2);
  ```

## Provider notes
- SerpApi: `https://serpapi.com/search` with `engine=google`. Configure key in settings.
- DataForSEO: POST `https://api.dataforseo.com/v3/serp/google/organic/live/regular` with Basic Auth (login/password).
- Scrape provider is lightweight DOM parsing; swap with a dedicated parser (e.g., `paquettg/php-html-parser`) if you need richer data.

## LLM settings
- Choose LLM provider (OpenAI GPT-4.1, Anthropic Claude Opus 4.5, Mistral Large).
- Set API keys in the AI SEO settings page.
- Configure temperature (default 0.4) and max tokens (default 600).
- Tone of voice default + prompt presets (one per line) are passed to the editor sidebar.
- Fallback order is auto: selected provider → others.
- Default presets cover Blog, FAQ, Product, Topical map, Landing, Category/Comparison; editable in settings.

## Next steps to reach WPSEOAI parity
- Swap outline stub for real LLM call (OpenAI/Claude/Gemini) and richer prompt presets.
- Internal linking suggester using stored SERP/topic graph.
- Bulk “improve content” actions with background jobs.
- Citation/AI Overview tracker using search API results.

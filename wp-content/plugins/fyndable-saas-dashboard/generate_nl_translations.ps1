# Generate Dutch (nl_NL) .po and .mo translation files for Fyndable SaaS Dashboard
# text domain: sseo-ai-saas
#
# Usage: powershell -ExecutionPolicy Bypass -File generate_nl_translations.ps1

$ErrorActionPreference = "Stop"

$PluginDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$LanguagesDir = Join-Path $PluginDir "languages"
$IncludesDir = Join-Path $PluginDir "includes"

if (-not (Test-Path $LanguagesDir)) {
    New-Item -ItemType Directory -Path $LanguagesDir -Force | Out-Null
}

# ─── Dutch translation dictionary ────────────────────────────────────────────
$Translations = [ordered]@{
    # ── License Admin ──
    "License Management" = "Licentiebeheer"
    "Licenses" = "Licenties"
    "License Dashboard" = "Licentie Dashboard"
    "Dashboard" = "Dashboard"
    "Generate License Keys" = "Licentiesleutels Genereren"
    "Generate Keys" = "Sleutels Genereren"
    "View All Licenses" = "Alle Licenties Bekijken"
    "All Licenses" = "Alle Licenties"
    "Tenants" = "Tenants"
    "Usage Reports" = "Gebruiksrapporten"
    "License Features" = "Licentie Functies"
    "Agency Accounts" = "Agency Accounts"
    "License Management Dashboard" = "Licentiebeheer Dashboard"
    "Total Licenses" = "Totale Licenties"
    "Created Today" = "Vandaag Aangemaakt"
    "This Month" = "Deze Maand"
    "Active Tenants" = "Actieve Tenants"
    "Quick Actions" = "Snelle Acties"
    "Manage Tenants" = "Tenants Beheren"
    "Licenses by Status" = "Licenties per Status"
    "Licenses by Type" = "Licenties per Type"
    "Status" = "Status"
    "Count" = "Aantal"
    "Type" = "Type"
    "All Statuses" = "Alle Statussen"
    "All Types" = "Alle Types"
    "All Tiers" = "Alle Tiers"
    "Active" = "Actief"
    "Used" = "Gebruikt"
    "Revoked" = "Ingetrokken"
    "Expired" = "Verlopen"
    "Test" = "Test"
    "Free" = "Gratis"
    "Trial" = "Trial"
    "Paid" = "Betaald"
    "Lifetime" = "Levenslang"
    "Starter" = "Starter"
    "Early Adopters" = "Early Adopters"
    "Professional" = "Professional"
    "Business" = "Business"
    "Agency" = "Agency"
    "Filter" = "Filteren"
    "Export CSV" = "Exporteer CSV"
    "License Key" = "Licentiesleutel"
    "Tier" = "Tier"
    "Assigned To" = "Toegewezen Aan"
    "Created" = "Aangemaakt"
    "Expires" = "Verloopt"
    "Never" = "Nooit"
    "Actions" = "Acties"
    "Manage Features" = "Functies Beheren"
    "Revoke" = "Intrekken"
    "Are you sure you want to revoke this license? This will also suspend the associated tenant." = "Weet je zeker dat je deze licentie wilt intrekken? Dit zal ook de bijbehorende tenant opschorten."
    "License revoked successfully." = "Licentie succesvol ingetrokken."
    "Successfully generated %1$d license keys. %2$d failed." = "Succesvol %1$d licentiesleutels gegenereerd. %2$d mislukt."
    "%s items" = "%s items"
    "Security check failed" = "Beveiligingscontrole mislukt"
    "License key required" = "Licentiesleutel vereist"
    "License not found" = "Licentie niet gevonden"
    "License key not found" = "Licentiesleutel niet gevonden"
    "Manage Features for License: %s" = "Functies Beheren voor Licentie: %s"
    "Valid email is required." = "Geldig e-mailadres is vereist."
    "Company name is required." = "Bedrijfsnaam is vereist."
    "Create Agency Account" = "Agency Account Aanmaken"
    "Agency account created for %s with %d sub-license quota. A welcome email has been sent with login details." = "Agency account aangemaakt voor %s met %d sub-licentie quota. Een welkomstmail is verzonden met inloggegevens."
    "Agency account not found" = "Agency account niet gevonden"
    "Agency account not found. Please contact the administrator." = "Agency account niet gevonden. Neem contact op met de beheerder."
    "Agency Partner" = "Agency Partner"
    "Agency Portal" = "Agency Portal"
    "Agency account" = "Agency account"
    "Generate Licenses" = "Licenties Genereren"

    # ── SaaS Settings ──
    "SaaS Settings" = "SaaS Instellingen"
    "Settings" = "Instellingen"
    "Checkout" = "Afrekenen"
    "Cost Dashboard" = "Kosten Dashboard"
    "AI Models" = "AI Modellen"
    "Client Versions" = "Client Versies"
    "Save Settings" = "Instellingen Opslaan"
    "Save Checkout Settings" = "Afrekeninstellingen Opslaan"
    "Save AI Models" = "AI Modellen Opslaan"
    "Save Client Versions" = "Client Versies Opslaan"
    "Create a WordPress page and add the shortcode %s to it, then select that page here. Paying customers are redirected to this page after login. If no page is selected, customers will be redirected to the homepage instead of getting a 404." = "Maak een WordPress pagina aan en voeg de shortcode %s toe, selecteer deze pagina hier. Betalende klanten worden na het inloggen naar deze pagina doorgestuurd. Als er geen pagina is geselecteerd, worden klanten doorgestuurd naar de homepage in plaats van een 404 te krijgen."
    "Customer Portal Page" = "Klantenportaal Pagina"
    "Currency" = "Valuta"
    "Payment Provider" = "Betalingsprovider"
    "Stripe" = "Stripe"
    "Mollie" = "Mollie"
    "You do not have permission to manage client versions." = "Je hebt geen rechten om client versies te beheren."
    "Uploaded client plugin zip: %s" = "Client plugin zip geupload: %s"
    "Please upload a .zip file." = "Upload een .zip bestand."
    "WordPress uploads directory is unavailable: %s" = "WordPress uploads map is niet beschikbaar: %s"
    "Could not create the client versions directory: %s" = "Kon de client versies map niet aanmaken: %s"
    "The client versions directory is not writable: %s" = "De client versies map is niet schrijfbaar: %s"
    "Could not move uploaded file to %s" = "Kon het geuploade bestand niet verplaatsen naar %s"
    "The uploaded zip does not appear to contain a WordPress plugin." = "De geuploade zip bevat geen WordPress plugin."
    "Client version settings saved." = "Client versie instellingen opgeslagen."
    "Client plugin source not found." = "Client plugin bron niet gevonden."
    "Upload New Version" = "Nieuwe Versie Uploaden"
    "Current Version" = "Huidige Versie"
    "Upload" = "Uploaden"
    "Default AI Provider" = "Standaard AI Provider"
    "Default Model" = "Standaard Model"
    "OpenAI (direct)" = "OpenAI (direct)"
    "OpenRouter (recommended)" = "OpenRouter (aanbevolen)"
    "OpenRouter (Multi-Provider Image API)" = "OpenRouter (Multi-Provider Image API)"
    "OpenArt (Flux Models)" = "OpenArt (Flux Modellen)"
    "OpenAI API Key" = "OpenAI API Sleutel"
    "OpenRouter API Key" = "OpenRouter API Sleutel"
    "OpenArt API Key (Flux)" = "OpenArt API Sleutel (Flux)"
    "SERP API Key" = "SERP API Sleutel"
    "SERP Provider" = "SERP Provider"
    "Image API Provider" = "Image API Provider"
    "Image API Key" = "Image API Sleutel"
    "Image Generation" = "Afbeelding Generatie"
    "Images" = "Afbeeldingen"
    "Image generation is routed through OpenRouter using the selected model below." = "Afbeelding generatie wordt via OpenRouter gerouteerd met het hieronder geselecteerde model."
    "OpenAI Model" = "OpenAI Model"
    "OpenAI DALL-E 3" = "OpenAI DALL-E 3"
    "GEO Scan Model" = "GEO Scan Model"
    "Get your key:" = "Verkrijg je sleutel:"
    "Get your API key at" = "Verkrijg je API sleutel bij"
    "API Credentials" = "API Inloggegevens"
    "API Key" = "API Sleutel"
    "API key is not configured" = "API sleutel is niet geconfigureerd"
    "API key for SERP data (DataForSEO, SerpApi, etc.)" = "API sleutel voor SERP data (DataForSEO, SerpApi, etc.)"
    "Firecrawl API Key" = "Firecrawl API Sleutel"
    "HTML Fetcher" = "HTML Fetcher"
    "Optional fallback for HTML extraction when Jina Reader fails." = "Optionele fallback voor HTML extractie wanneer Jina Reader faalt."
    "Integrations" = "Integraties"
    "DataForSEO" = "DataForSEO"
    "Serper" = "Serper"
    "SerpApi" = "SerpApi"
    "Seranking" = "Seranking"
    "All providers" = "Alle providers"

    # ── Cost Dashboard ──
    "Current Month" = "Huidige Maand"
    "Total API Costs" = "Totale API Kosten"
    "Total API Calls" = "Totale API Calls"
    "Avg Cost per Tenant" = "Gemiddelde Kosten per Tenant"
    "Top 10 Customers by Cost" = "Top 10 Klanten op Kosten"
    "Site" = "Site"
    "API Calls" = "API Calls"
    "Cost" = "Kosten"
    "Tier Distribution" = "Tier Verdeling"
    "API Cost Breakdown by Service" = "API Kosten Uitsplitsing per Service"
    "Service" = "Service"
    "Google API Costs per Klant" = "Google API Kosten per Klant"
    "Revenue" = "Omzet"
    "Totale Google API Kosten" = "Totale Google API Kosten"
    "Totale API Calls" = "Totale API Calls"
    "Actieve Klanten" = "Actieve Klanten"
    "Gemiddeld per Klant" = "Gemiddeld per Klant"
    "Kosten per Klant" = "Kosten per Klant"
    "Klant" = "Klant"
    "Domein" = "Domein"
    "GSC" = "GSC"
    "GA4" = "GA4"
    "Ads" = "Ads"
    "OAuth" = "OAuth"
    "Totaal Calls" = "Totaal Calls"
    "Totaal Kosten" = "Totaal Kosten"
    "Kosten per Service" = "Kosten per Service"
    "Calls" = "Calls"
    "Klanten" = "Klanten"
    "Totaal" = "Totaal"
    "Maand:" = "Maand:"
    "Note: GEO scan costs of %1$s in this period are not yet allocated per tenant (per-tenant GEO cost tracking is a follow-up)." = "Let op: GEO scan kosten van %1$s in deze periode zijn nog niet per tenant toegewezen (per-tenant GEO kostentracking is een vervolgstap)."

    # ── Bookkeeping ──
    "Bookkeeping" = "Boekhouding"
    "Profit" = "Winst"
    "Invoice Template" = "Factuur Sjabloon"
    "From:" = "Van:"
    "To:" = "Tot:"
    "Update" = "Bijwerken"
    "Total Revenue" = "Totale Omzet"
    "Total Cost (AI)" = "Totale Kosten (AI)"
    "Total Profit" = "Totale Winst"
    "Margin" = "Marge"
    "Customer" = "Klant"
    "No data for the selected period." = "Geen gegevens voor de geselecteerde periode."
    "(unknown)" = "(onbekend)"

    # ── Invoice Manager ──
    "Invoice #" = "Factuur #"
    "Invoices" = "Facturen"
    "Date" = "Datum"
    "Description" = "Omschrijving"
    "Period" = "Periode"
    "Amount" = "Bedrag"
    "Subtotal" = "Subtotaal"
    "VAT" = "BTW"
    "Total" = "Totaal"
    "Paid on" = "Betaald op"
    "Payment Confirmation" = "Betaling Bevestiging"
    "Payment successful" = "Betaling succesvol"
    "Payment Receipt" = "Betalingsbewijs"
    "Payment failed" = "Betaling mislukt"
    "Plan" = "Abonnement"
    "Transaction ID" = "Transactie ID"
    "View Details" = "Details Bekijken"
    "Company Name" = "Bedrijfsnaam"
    "Company name is required" = "Bedrijfsnaam is vereist"
    "Company name is required to build a white-label package." = "Bedrijfsnaam is vereist om een white-label pakket te bouwen."
    "Credit Card" = "Credit Card"
    "Billing" = "Facturatie"
    "Billing Interval" = "Facturatie Interval"
    "Current Tier" = "Huidige Tier"
    "Monthly Recurring Revenue" = "Maandelijkse Terugkerende Omzet"

    # ── Customer Portal ──
    "Your subscription has been cancelled. You will retain access until the end of your current billing period." = "Je abonnement is opgezegd. Je behoudt toegang tot het einde van je huidige factureringsperiode."
    "Are you sure you want to cancel your subscription? You will retain access until the end of your current billing period." = "Weet je zeker dat je je abonnement wilt opzeggen? Je behoudt toegang tot het einde van je huidige factureringsperiode."
    "Loading..." = "Laden..."
    "Something went wrong. Please try again." = "Er ging iets mis. Probeer het opnieuw."
    "Subscription cancelled successfully." = "Abonnement succesvol opgezegd."
    "Copied to clipboard!" = "Gekopieerd naar klembord!"
    "Customer Portal" = "Klantenportaal"
    "Client Portal" = "Client Portaal"
    "Manage your subscription" = "Beheer je abonnement"
    "Account" = "Account"
    "Language" = "Taal"
    "Dutch" = "Nederlands"
    "English" = "Engels"

    # ── Email Templates ──
    "Email Templates" = "E-mail Sjablonen"
    "Email / SMTP" = "E-mail / SMTP"
    "Global Email Brand" = "Globaal E-mail Merk"
    "Brand settings saved. These apply to all email templates unless a template overrides them." = "Merkinstellingen opgeslagen. Deze zijn van toepassing op alle e-mailsjablonen tenzij een sjabloon ze overschrijft."
    "Logo & Colours" = "Logo & Kleuren"
    "Logo" = "Logo"
    "Choose Logo" = "Logo Kiezen"
    "Remove" = "Verwijderen"
    "Primary colour" = "Primaire kleur"
    "Secondary colour" = "Secundaire kleur"
    "Button colour" = "Knopkleur"
    "Company Details" = "Bedrijfsgegevens"
    "Street address" = "Straatadres"
    "Postal code" = "Postcode"
    "City" = "Stad"
    "Country" = "Land"
    "VAT number" = "BTW-nummer"
    "Chamber of Commerce (KvK)" = "Kamer van Koophandel (KvK)"
    "IBAN" = "IBAN"
    "KvK" = "KvK"
    "Email" = "E-mail"
    "Website" = "Website"
    "Footer text" = "Footertekst"
    "Branding" = "Branding"
    "Back to templates" = "Terug naar sjablonen"
    "Template not found." = "Sjabloon niet gevonden."
    "Edit Template: %s" = "Sjabloon Bewerken: %s"
    "Template saved." = "Sjabloon opgeslagen."
    "Test email sent." = "Test-e-mail verzonden."
    "Could not send test email. Check the address and template." = "Kon geen test-e-mail verzenden. Controleer het adres en sjabloon."
    "Content" = "Inhoud"
    "Name" = "Naam"
    "Subject" = "Onderwerp"
    "Body HTML" = "Body HTML"
    "Layout" = "Layout"
    "Logo URL" = "Logo URL"
    "Primary color" = "Primaire kleur"
    "Secondary color" = "Secundaire kleur"
    "Button color" = "Knopkleur"
    "Available placeholders" = "Beschikbare placeholders"
    "Use the placeholders below in subject and body. They will be replaced when the email is sent." = "Gebruik de onderstaande placeholders in onderwerp en body. Ze worden vervangen wanneer de e-mail wordt verzonden."
    "Save Template" = "Sjabloon Opslaan"
    "Save & Preview" = "Opslaan & Voorbeeld"
    "Send Test" = "Test Verzenden"
    "Test Email" = "Test E-mail"
    "Manage Layouts" = "Layouts Beheren"
    "Email Layouts" = "E-mail Layouts"
    "Custom layouts can be added, edited or removed. Built-in layouts cannot be deleted." = "Aangepaste layouts kunnen worden toegevoegd, bewerkt of verwijderd. Ingebouwde layouts kunnen niet worden verwijderd."
    "Add Layout" = "Layout Toevoegen"
    "Built-in layouts" = "Ingebouwde layouts"
    "Custom layouts" = "Aangepaste layouts"
    "No custom layouts found." = "Geen aangepaste layouts gevonden."
    "Delete this layout?" = "Deze layout verwijderen?"
    "Built-in layouts cannot be deleted." = "Ingebouwde layouts kunnen niet worden verwijderd."
    "Edit Layout" = "Layout Bewerken"
    "Slug" = "Slug"
    "Layout HTML" = "Layout HTML"
    "Save Layout" = "Layout Opslaan"

    # ── GEO Scan (already Dutch, kept as-is) ──
    "Scan niet gevonden." = "Scan niet gevonden."
    "GEO-scanrapport" = "GEO-scanrapport"
    "GEO Scan" = "GEO Scan"
    "Geo Scan Admin" = "Geo Scan Beheer"
    "Webadres:" = "Webadres:"
    "Datum:" = "Datum:"
    "Taal:" = "Taal:"
    "Totaalscore" = "Totaalscore"
    "Hoe geschikt is deze pagina om als bron te worden geciteerd door AI-zoekmachines?" = "Hoe geschikt is deze pagina om als bron te worden geciteerd door AI-zoekmachines?"
    "Quickscan" = "Quickscan"
    "Sterke punten" = "Sterke punten"
    "Aandachtspunten" = "Aandachtspunten"
    "Scoreverdeling" = "Scoreverdeling"
    "Bevindingen" = "Bevindingen"
    "Aanbevelingen" = "Aanbevelingen"
    "Prioriteitsacties" = "Prioriteitsacties"
    "Zoekwoordanalyse" = "Zoekwoordanalyse"
    "Zoekwoord" = "Zoekwoord"
    "AI-overzicht" = "AI-overzicht"
    "Jouw bedrijf vermeld" = "Jouw bedrijf vermeld"
    "Concurrenten vermeld" = "Concurrenten vermeld"
    "Ja" = "Ja"
    "Nee" = "Nee"
    "Geen concurrenten gevonden" = "Geen concurrenten gevonden"
    "Printen / Opslaan als PDF" = "Printen / Opslaan als PDF"
    "Terug naar scans" = "Terug naar scans"
    "Direct antwoord" = "Direct antwoord"
    "Structuur" = "Structuur"
    "Schema markup" = "Schema markup"
    "Entiteiten" = "Entiteiten"
    "Citeerwaardigheid" = "Citeerwaardigheid"
    "Leesbaarheid" = "Leesbaarheid"
    "E-E-A-T" = "E-E-A-T"
    "Actualiteit" = "Actualiteit"
    "Mobiel vriendelijk" = "Mobiel vriendelijk"
    "Interne links" = "Interne links"
    "Metadata" = "Metadata"
    "Entiteitsdekking" = "Entiteitsdekking"
    "Concurrentieverschil" = "Concurrentieverschil"
    "Scanning, please wait..." = "Scannen, even geduld..."
    "Extracting..." = "Extraheren..."
    "Analyze" = "Analyseren"
    "Scan failed. Please try again." = "Scan mislukt. Probeer het opnieuw."
    "No scans yet." = "Nog geen scans."
    "Scans" = "Scans"
    "Enter 1 to 10 keywords, one per line." = "Voer 1 tot 10 zoekwoorden in, een per regel."
    "Keywords" = "Zoekwoorden"
    "Score" = "Score"
    "Prospect URL" = "Prospect URL"
    "Prospects" = "Prospects"

    # ── White-label ──
    "Enable White-Label" = "White-Label Inschakelen"
    "Global White-Label" = "Globale White-Label"
    "Build package" = "Pakket Bouwen"
    "Client Details: %s" = "Client Details: %s"
    "Advanced AI-powered SEO plugin by %s" = "Geavanceerde AI-aangedreven SEO plugin door %s"
    "Powered by" = "Mogelijk gemaakt door"
    "Fyndable" = "Fyndable"
    "Fyndable Customer" = "Fyndable Klant"
    "Fyndable Starter" = "Fyndable Starter"
    "Fyndable Professional" = "Fyndable Professional"

    # ── Support ──
    "No tickets found." = "Geen tickets gevonden."
    "Reply message is required." = "Antwoordbericht is vereist."

    # ── Revenue Dashboard ──
    "Revenue by Tier" = "Omzet per Tier"
    "New This Month" = "Nieuw Deze Maand"
    "Paid Tenants" = "Betalende Tenants"
    "Just expired (within last 24h)" = "Net verlopen (binnen laatste 24u)"

    # ── AI Provider errors ──
    "OpenAI API key is not configured. Add it in SaaS Settings." = "OpenAI API sleutel is niet geconfigureerd. Voeg deze toe in SaaS Instellingen."
    "OpenRouter API key is not configured. Add it in SaaS Settings." = "OpenRouter API sleutel is niet geconfigureerd. Voeg deze toe in SaaS Instellingen."
    "OpenArt API key is not configured. Add it in SaaS Settings." = "OpenArt API sleutel is niet geconfigureerd. Voeg deze toe in SaaS Instellingen."
    "Invalid OpenAI API key. Check your settings." = "Ongeldige OpenAI API sleutel. Controleer je instellingen."
    "Invalid OpenRouter API key. Check your settings." = "Ongeldige OpenRouter API sleutel. Controleer je instellingen."
    "OpenAI request failed: %s" = "OpenAI verzoek mislukt: %s"
    "OpenRouter request failed: %s" = "OpenRouter verzoek mislukt: %s"
    "OpenArt request failed: %s" = "OpenArt verzoek mislukt: %s"
    "OpenAI rate limit reached. Try again shortly." = "OpenAI rate limit bereikt. Probeer het zo opnieuw."
    "OpenRouter rate limit reached. Try again shortly." = "OpenRouter rate limit bereikt. Probeer het zo opnieuw."
    "OpenArt rate limit reached. Try again shortly." = "OpenArt rate limit bereikt. Probeer het zo opnieuw."
    "OpenRouter credits exhausted. Top up at openrouter.ai." = "OpenRouter credits opgebruikt. Vul bij op openrouter.ai."
    "OpenArt credits exhausted. Top up your account." = "OpenArt credits opgebruikt. Vul je account bij."
    "OpenArt did not return an image URL." = "OpenArt heeft geen afbeelding URL teruggegeven."
    "AI generation failed" = "AI generatie mislukt"
    "AI content generation. Keep this secret!" = "AI content generatie. Houd dit geheim!"
    "AI service is temporarily unavailable due to recent failures. Please try again later." = "AI service is tijdelijk niet beschikbaar door recente storingen. Probeer het later opnieuw."
    "AI Overview Extractor" = "AI Overzicht Extractor"
    "No AI model candidates available for this request." = "Geen AI model kandidaten beschikbaar voor dit verzoek."
    "SERP service is not configured" = "SERP service is niet geconfigureerd"
    "Jina Reader returned an empty response" = "Jina Reader gaf een lege reactie"
    "Jina Reader returned status %d" = "Jina Reader gaf status %d"

    # ── Misc / Common ──
    "About" = "Over"
    "Activate" = "Activeren"
    "Available" = "Beschikbaar"
    "Buy" = "Kopen"
    "Download" = "Downloaden"
    "Error" = "Fout"
    "Failed" = "Mislukt"
    "ID" = "ID"
    "Model" = "Model"
    "Number" = "Nummer"
    "Overview" = "Overzicht"
    "Page" = "Pagina"
    "Point" = "Punt"
    "Points" = "Punten"
    "Port" = "Poort"
    "Port:" = "Poort:"
    "Post" = "Bericht"
    "Posts" = "Berichten"
    "Premium" = "Premium"
    "Price" = "Prijs"
    "Priority" = "Prioriteit"
    "Provider" = "Provider"
    "Reset" = "Reset"
    "Search" = "Zoeken"
    "Search tenant..." = "Tenant zoeken..."
    "Max Sites" = "Max Sites"
    "Rate Limit" = "Rate Limit"
    "Last active" = "Laatst actief"
    "Last update" = "Laatste update"
    "Last Updated" = "Laatst Bijgewerkt"
    "Created At" = "Aangemaakt Op"
    "Not Found" = "Niet Gevonden"
    "Not Writable" = "Niet Schrijfbaar"
    "No invoices found." = "Geen facturen gevonden."
    "No licenses found." = "Geen licenties gevonden."
    "No tenants found." = "Geen tenants gevonden."
    "All invoices found." = "Alle facturen gevonden."
    "Export feature coming soon" = "Export functie binnenkort beschikbaar"
    "Auto-posts" = "Auto-berichten"
    "Competitor Research" = "Concurrentieonderzoek"
    "Competitive Gap" = "Concurrentieverschil"
    "Content Analysis" = "Content Analyse"
    "Content Decay Monitor" = "Content Verval Monitor"
    "Content Optimizer" = "Content Optimalisator"
    "Content Repurposer" = "Content Herbestemmer"
    "Content Workflow" = "Content Workflow"
    "Go to Dashboard" = "Naar Dashboard"
    "Redirect to Stripe to complete payment setup." = "Doorsturen naar Stripe om betalingsinstellingen te voltooien."
    "Invalid payment provider" = "Ongeldige betalingsprovider"
    "Invalid subscription tier" = "Ongeldig abonnement tier"
    "Invalid URL provided" = "Ongeldige URL opgegeven"
    "A valid email is required" = "Een geldig e-mailadres is vereist"
    "Security check failed." = "Beveiligingscontrole mislukt."
    "Select a page" = "Selecteer een pagina"
    "Admin: Technical Alert" = "Beheerder: Technische Melding"
    "2 maanden gratis" = "2 maanden gratis"
    "Kopieer" = "Kopieer"
    "OF" = "OF"
    "OF:" = "OF:"
    "Quota: %1$d / %2$d used — %3$d remaining" = "Quota: %1$d / %2$d gebruikt — %3$d resterend"
    "%1$d / %2$d used — %3$d remaining" = "%1$d / %2$d gebruikt — %3$d resterend"
    "Organic Top" = "Organische Top"
    "Factuur" = "Factuur"
    "Factuur aan" = "Factuur aan"
    'Kies in het printvenster "Opslaan als PDF".' = 'Kies in het printvenster "Opslaan als PDF".'
    'Need help? <a href=\"{{support_url}}\">Contact support</a>' = 'Hulp nodig? <a href=\"{{support_url}}\">Neem contact op met support</a>'

    # ── Additional missing strings ──
    "— Select a page —" = "— Selecteer een pagina —"
    '"Amount" label' = '"Bedrag" label'
    '"Billed to" label' = '"Gefactureerd aan" label'
    '"Description" label' = '"Omschrijving" label'
    '"From" label' = '"Van" label'
    '"Invoice" label' = '"Factuur" label'
    '"Paid on" label' = '"Betaald op" label'
    '"Period" label' = '"Periode" label'
    '"Subtotal" label' = '"Subtotaal" label'
    '"Total" label' = '"Totaal" label'
    '"VAT" label' = '"BTW" label'
    "[%s] Notification" = "[%s] Melding"
    "[%s] Payment Failed: %s" = "[%s] Betaling Mislukt: %s"
    "[{{site_name}}] New reply on ticket #{{ticket_id}} from {{tenant_name}}" = "[{{site_name}}] Nieuw antwoord op ticket #{{ticket_id}} van {{tenant_name}}"
    "[{{site_name}}] New support ticket #{{ticket_id}} from {{tenant_name}}" = "[{{site_name}}] Nieuwe support ticket #{{ticket_id}} van {{tenant_name}}"
    "[{{site_name}}] Reply to your support ticket #{{ticket_id}}" = "[{{site_name}}] Antwoord op je support ticket #{{ticket_id}}"
    "{{company_name}} Alert" = "{{company_name}} Melding"
    "A client replied to a support ticket" = "Een klant heeft geantwoord op een support ticket"
    "A reply has been added to your support ticket" = "Er is een antwoord toegevoegd aan je support ticket"
    "Address" = "Adres"
    "Agency account already exists for this user" = "Agency account bestaat al voor deze gebruiker"
    "Agency account not found." = "Agency account niet gevonden."
    "ago" = "geleden"
    "All AI models failed. Attempted: %s. Last error: %s" = "Alle AI modellen faalden. Geprobeerd: %s. Laatste fout: %s"
    "All SERP providers failed" = "Alle SERP providers faalden"
    "Announcement" = "Aankondiging"
    "Closed" = "Gesloten"
    "Content Generation" = "Content Generatie"
    "Could not create extraction directory." = "Kon de extractie map niet aanmaken."
    "Could not create SE Ranking SERP task" = "Kon SE Ranking SERP taak niet aanmaken"
    "Could not open client source zip." = "Kon de client source zip niet openen."
    "Count must be between 1 and 100" = "Aantal moet tussen 1 en 100 zijn"
    "Default" = "Standaard"
    "Email or username" = "E-mail of gebruikersnaam"
    "Failed to create agency account" = "Agency account aanmaken mislukt"
    "Failed to create tenant" = "Tenant aanmaken mislukt"
    "Failed to create user account." = "Gebruikersaccount aanmaken mislukt."
    "Failed to generate any licenses" = "Geen licenties kunnen genereren"
    "Failed to generate license key" = "Licentiesleutel genereren mislukt"
    "FAQ Generation" = "FAQ Generatie"
    "Firecrawl scrape failed" = "Firecrawl scrape mislukt"
    "From" = "Van"
    "Fyndable Business" = "Fyndable Business"
    "Fyndable SmartSEO %s subscription" = "Fyndable SmartSEO %s abonnement"
    "Generate Sub-License" = "Sub-Licentie Genereren"
    "GEO Readiness Scan" = " GEO Readiness Scan"
    "Google OAuth is not configured on the SaaS dashboard." = "Google OAuth is niet geconfigureerd op het SaaS dashboard."
    "Hello," = "Hallo,"
    "Hi %s," = "Hoi %s,"
    "High" = "Hoog"
    "Image Alt Text" = "Afbeelding Alt Tekst"
    "Insufficient permissions." = "Onvoldoende rechten."
    "Invalid credentials." = "Ongeldige inloggegevens."
    "Invalid OpenArt API key. Check your settings." = "Ongeldige OpenArt API sleutel. Controleer je instellingen."
    "Invalid or expired login link." = "Ongeldige of verlopen inloglink."
    "Invoice not found." = "Factuur niet gevonden."
    "Keyword and target_url are required" = "Zoekwoord en target_url zijn vereist"
    "Keyword Research" = "Zoekwoord Onderzoek"
    "License" = "Licentie"
    "License Expired Email" = "Licentie Verlopen E-mail"
    "License key has been revoked" = "Licentiesleutel is ingetrokken"
    "License key has expired" = "Licentiesleutel is verlopen"
    "Low" = "Laag"
    "Meta Optimization" = "Meta Optimalisatie"
    "Middle" = "Midden"
    "Minimal" = "Minimal"
    "Mollie API connection failed:" = "Mollie API verbinding mislukt:"
    "Mollie API key is not configured" = "Mollie API sleutel is niet geconfigureerd"
    "Mollie API request failed (HTTP %d): %s" = "Mollie API verzoek mislukt (HTTP %d): %s"
    "Mollie customer not configured for this tenant" = "Mollie klant niet geconfigureerd voor deze tenant"
    "Mollie payment does not have a customer ID" = "Mollie betaling heeft geen klant ID"
    "Mollie subscription not configured for this tenant" = "Mollie abonnement niet geconfigureerd voor deze tenant"
    "Monthly API limit reached" = "Maandelijkse API limiet bereikt"
    "Name and email are required" = "Naam en e-mail zijn vereist"
    "New support ticket received" = "Nieuwe support ticket ontvangen"
    "No AI provider is configured. Set up OpenRouter or OpenAI API key in SaaS settings." = "Geen AI provider geconfigureerd. Stel OpenRouter of OpenAI API sleutel in in SaaS instellingen."
    "No data to update" = "Geen gegevens om bij te werken"
    "No Mollie payments found for this customer" = "Geen Mollie betalingen gevonden voor deze klant"
    "No screenshot uploaded." = "Geen screenshot geupload."
    "No SE Ranking location for country %s" = "Geen SE Ranking locatie voor land %s"
    "No successful first payment found to base a subscription on" = "Geen succesvolle eerste betaling gevonden om een abonnement op te baseren"
    "No valid fields to update" = "Geen geldige velden om bij te werken"
    "Open" = "Open"
    "Password" = "Wachtwoord"
    "Payment could not be processed" = "Betaling kon niet worden verwerkt"
    "Payment Failed - Action Required" = "Betaling Mislukt - Actie Vereist"
    "Payment Failed: %s" = "Betaling Mislukt: %s"
    "Payment Receipt - {{amount}}" = "Betalingsbewijs - {{amount}}"
    "Please set a Company Name on the White-Label settings page first." = "Stel eerst een bedrijfsnaam in op de White-Label instellingen pagina."
    "Prefix is required and must be 1-6 alphanumeric characters." = "Prefix is vereist en moet 1-6 alfanumerieke tekens zijn."
    "Provide between 1 and 10 keywords" = "Geef tussen 1 en 10 zoekwoorden op"
    "Reaction" = "Reactie"
    "Redirect to Mollie to complete the first payment." = "Doorsturen naar Mollie om de eerste betaling te voltooien."
    "Remember me" = "Onthoud mij"
    "Renew License" = "Licentie Vernieuwen"
    "Save Brand Settings" = "Merkinstellingen Opslaan"
    "Save Tenant White-Label Settings" = "Tenant White-Label Instellingen Opslaan"
    "Save White-Label Settings" = "White-Label Instellingen Opslaan"
    "SE Ranking SERP task failed" = "SE Ranking SERP taak mislukt"
    "SE Ranking SERP task timed out" = "SE Ranking SERP taak time-out"
    "Select Company Logo" = "Bedrijfslogo Selecteren"
    "Send reply" = "Antwoord versturen"
    "Send test email" = "Test-e-mail versturen"
    "SERP provider %s is temporarily skipped due to recent failures" = "SERP provider %s is tijdelijk overgeslagen door recente storingen"
    "SerpApi key is not configured" = "SerpApi sleutel is niet geconfigureerd"
    "Showing usage for %s. Total values include all sub-tenants under your agency." = "Gebruik weergegeven voor %s. Totale waarden omvatten alle sub-tenants onder je agency."
    "Sign in" = "Inloggen"
    "Smart SEO" = "Smart SEO"
    "Stripe API request failed" = "Stripe API verzoek mislukt"
    "Stripe secret key is not configured" = "Stripe secret sleutel is niet geconfigureerd"
    "Subject and message are required." = "Onderwerp en bericht zijn vereist."
    "Subscription cancelled successfully" = "Abonnement succesvol opgezegd"
    "Subscription upgraded to %s" = "Abonnement opgewaardeerd naar %s"
    "Support" = "Support"
    "Support Tickets" = "Support Tickets"
    "Support: New Ticket (admin)" = "Support: Nieuwe Ticket (beheerder)"
    "Support: Reply to Customer" = "Support: Antwoord aan Klant"
    "Support: Reply to Staff" = "Support: Antwoord aan Medewerker"
    "Team" = "Team"
    "Team Management" = "Team Beheer"
    "Temporary directory is not writable." = "Tijdelijke map is niet schrijfbaar."
    "Tenant" = "Tenant"
    "Tenant Detail" = "Tenant Detail"
    "Tenant has no email address for login." = "Tenant heeft geen e-mailadres voor inloggen."
    "Tenant is not active." = "Tenant is niet actief."
    "Tenant key already exists" = "Tenant sleutel bestaat al"
    "Tenant not found" = "Tenant niet gevonden"
    "Tenant not found or not part of your agency." = "Tenant niet gevonden of geen onderdeel van je agency."
    "Tenant not found." = "Tenant niet gevonden."
    "Tenant white-label settings saved successfully!" = "Tenant white-label instellingen succesvol opgeslagen!"
    "TEST:" = "TEST:"
    "The AI model did not return a valid JSON response" = "Het AI model gaf geen geldige JSON reactie"
    "The ZipArchive PHP extension is required to build packages." = "De ZipArchive PHP extensie is vereist om pakketten te bouwen."
    "This is an automated email, please do not reply." = "Dit is een geautomatiseerde e-mail, gelieve niet te antwoorden."
    "This is an automated notification." = "Dit is een geautomatiseerde melding."
    "This license key has been revoked" = "Deze licentiesleutel is ingetrokken"
    "This license key has expired" = "Deze licentiesleutel is verlopen"
    "This tenant does not belong to your agency." = "Deze tenant behoort niet tot je agency."
    "Ticket" = "Ticket"
    "Ticket #%d: %s" = "Ticket #%d: %s"
    "Ticket not found." = "Ticket niet gevonden."
    "Trial Expiring Email" = "Trial Verloopt E-mail"
    "Unable to create zip archive." = "Kon geen zip archief aanmaken."
    "Unknown OpenAI error" = "Onbekende OpenAI fout"
    "Unknown OpenArt error" = "Onbekende OpenArt fout"
    "Unknown OpenRouter error" = "Onbekende OpenRouter fout"
    "Unknown SERP provider" = "Onbekende SERP provider"
    "Unknown SerpApi error" = "Onbekende SerpApi fout"
    "Update Payment" = "Betaling Bijwerken"
    "Update ticket" = "Ticket Bijwerken"
    "Upgrade Now" = "Nu Upgraden"
    "Upgrade Plan" = "Abonnement Upgraden"
    "Usage & Costs" = "Gebruik & Kosten"
    "Usage Limit Reached" = "Gebruikslimiet Bereikt"
    "Usage Statistics - %s" = "Gebruiksstatistieken - %s"
    "Use as logo" = "Als logo gebruiken"
    "Use this logo" = "Dit logo gebruiken"
    "We were unable to process your payment of {{amount}}. Please update your payment method to avoid service interruption." = "We konden je betaling van {{amount}} niet verwerken. Werk je betalingsmethode bij om serviceonderbreking te voorkomen."
    "Welcome aboard!" = "Welkom aan boord!"
    "Welcome Email" = "Welkomstmail"
    "Welcome to %s - sign in to your dashboard" = "Welkom bij %s - log in op je dashboard"
    "Welcome to {{site_name}} - Your SEO Journey Starts Here" = "Welkom bij {{site_name}} - Je SEO Reis Begint Hier"
    "Welcome to Fyndable SmartSEO - Your account is ready" = "Welkom bij Fyndable SmartSEO - Je account is klaar"
    "White-Label" = "White-Label"
    "White-Label Settings for: %s" = "White-Label Instellingen voor: %s"
    "You are already logged in." = "Je bent al ingelogd."
    "You do not have permission to download packages." = "Je hebt geen rechten om pakketten te downloaden."
    "You do not have permission to export invoices." = "Je hebt geen rechten om facturen te exporteren."
    "You do not have permission to generate login links." = "Je hebt geen rechten om inloglinks te genereren."
    "You do not have permission to manage white-label settings." = "Je hebt geen rechten om white-label instellingen te beheren."
    "You do not have permission to preview invoices." = "Je hebt geen rechten om facturen te bekijken."
    "You do not have permission to run scans." = "Je hebt geen rechten om scans uit te voeren."
    "You do not have permission to view invoices." = "Je hebt geen rechten om facturen te bekijken."
    "You have reached your maximum sub-license quota." = "Je hebt je maximale sub-licentie quota bereikt."
    "You must be logged in." = "Je moet ingelogd zijn."
    "Your {{site_name}} License Has Expired" = "Je {{site_name}} Licentie Is Verlopen"
    "Your license has expired" = "Je licentie is verlopen"
    "Your License Key" = "Je Licentiesleutel"
    "Your trial expires in {{days_left}} day(s)" = "Je trial verloopt over {{days_left}} dag(en)"
    "No valid mandate found for Mollie customer. The payment method may not support recurring payments." = "Geen geldig mandaat gevonden voor Mollie klant. De betalingsmethode ondersteunt mogelijk geen terugkerende betalingen."
    'Mollie is set to %s mode but the API key does not start with "%s".' = 'Mollie is ingesteld op %s modus maar de API sleutel begint niet met "%s".'
    "Payment failed for tenant: %s\nProvider: %s\nTenant Email: %s\n\nPlease check the payment status and follow up if needed." = "Betaling mislukt voor tenant: %s\nProvider: %s\nTenant E-mail: %s\n\nControleer de betalingsstatus en volg op indien nodig."
}

# ─── Extract strings from PHP files ──────────────────────────────────────────
function Extract-StringsFromPhp {
    param([string]$FilePath)
    $content = Get-Content $FilePath -Raw -Encoding UTF8
    $strings = [System.Collections.Generic.HashSet[string]]::new()
    # Simple pattern: match strings without escaped quotes (covers 99% of cases)
    $pattern1 = "(?:__|esc_html__|esc_attr__)\(\s*'([^']*)'\s*,\s*'sseo-ai-saas'"
    $pattern2 = '(?:__|esc_html__|esc_attr__)\(\s*"([^"]*)"\s*,\s*''sseo-ai-saas'''
    foreach ($match in [regex]::Matches($content, $pattern1)) {
        $s = $match.Groups[1].Value -replace "\\\'", "'"
        [void]$strings.Add($s)
    }
    foreach ($match in [regex]::Matches($content, $pattern2)) {
        $s = $match.Groups[1].Value -replace '\\"', '"'
        [void]$strings.Add($s)
    }
    return $strings
}

Write-Host "Extracting translatable strings from PHP files..."
$allStrings = [System.Collections.Generic.HashSet[string]]::new()

# Scan includes directory
Get-ChildItem -Path $IncludesDir -Filter "*.php" | ForEach-Object {
    $found = Extract-StringsFromPhp -FilePath $_.FullName
    foreach ($s in $found) { [void]$allStrings.Add($s) }
}

# Scan main plugin file
$mainFile = Join-Path $PluginDir "ai-seo-saas-dashboard.php"
if (Test-Path $mainFile) {
    $found = Extract-StringsFromPhp -FilePath $mainFile
    foreach ($s in $found) { [void]$allStrings.Add($s) }
}

Write-Host "Found $($allStrings.Count) unique strings."

# Build final translations dict (case-sensitive lookup with case-insensitive fallback)
$finalTranslations = [ordered]@{}
$missing = @()
foreach ($s in ($allStrings | Sort-Object)) {
    if ($Translations.Contains($s)) {
        $finalTranslations[$s] = $Translations[$s]
    } else {
        # Case-insensitive fallback (e.g. "Company name" matches "Company Name")
        $ciMatch = $Translations.Keys | Where-Object { $_ -ieq $s } | Select-Object -First 1
        if ($ciMatch) {
            $finalTranslations[$s] = $Translations[$ciMatch]
        } else {
            $finalTranslations[$s] = $s
            $missing += $s
        }
    }
}

if ($missing.Count -gt 0) {
    Write-Host ""
    Write-Host "WARNING: $($missing.Count) strings not in translation dictionary (kept as-is):"
    foreach ($s in $missing) {
        Write-Host "  - $($s.Substring(0, [Math]::Min(80, $s.Length)))"
    }
}

# ─── Write .po file ──────────────────────────────────────────────────────────
$poPath = Join-Path $LanguagesDir "sseo-ai-saas-nl_NL.po"
Write-Host ""
Write-Host "Writing .po file: $poPath"

$poLines = @()
$poLines += 'msgid ""'
$poLines += 'msgstr ""'
$poLines += '"Project-Id-Version: Fyndable SaaS Dashboard\n"'
$poLines += '"Report-Msgid-Bugs-To: \n"'
$poLines += '"POT-Creation-Date: 2026-08-07 00:00+0000\n"'
$poLines += '"PO-Revision-Date: 2026-08-07 00:00+0000\n"'
$poLines += '"Last-Translator: Devin\n"'
$poLines += '"Language-Team: Dutch\n"'
$poLines += '"Language: nl_NL\n"'
$poLines += '"MIME-Version: 1.0\n"'
$poLines += '"Content-Type: text/plain; charset=UTF-8\n"'
$poLines += '"Content-Transfer-Encoding: 8bit\n"'
$poLines += '"Plural-Forms: nplurals=2; plural=(n != 1);\n"'
$poLines += '"X-Generator: Devin Translation Script\n"'
$poLines += ''

function Escape-PoString($s) {
    return $s -replace '\\', '\\' -replace '"', '\"' -replace "`n", '\n'
}

foreach ($msgid in ($finalTranslations.Keys | Sort-Object)) {
    $msgstr = $finalTranslations[$msgid]
    $poLines += "msgid `"$(Escape-PoString $msgid)`""
    $poLines += "msgstr `"$(Escape-PoString $msgstr)`""
    $poLines += ''
}

$poLines -join "`n" | Set-Content -Path $poPath -Encoding UTF8 -NoNewline

# ─── Write .mo file (binary) ─────────────────────────────────────────────────
$moPath = Join-Path $LanguagesDir "sseo-ai-saas-nl_NL.mo"
Write-Host "Writing .mo file: $moPath"

# Sort entries by msgid bytes (binary sort for MO format)
$sortedKeys = $finalTranslations.Keys | Sort-Object

$n = $sortedKeys.Count
$headerSize = 28
$oTableOffset = $headerSize
$tTableOffset = $oTableOffset + ($n * 8)
$oDataOffset = $tTableOffset + ($n * 8)

# Build string data
$oEntries = @()
$tEntries = @()
$oDataBytes = [System.Collections.Generic.List[byte]]::new()
$tDataBytes = [System.Collections.Generic.List[byte]]::new()

$oOffset = $oDataOffset
foreach ($msgid in $sortedKeys) {
    $msgidBytes = [System.Text.Encoding]::UTF8.GetBytes($msgid)
    $oEntries += @{ length = $msgidBytes.Length; offset = $oOffset }
    $oDataBytes.AddRange($msgidBytes)
    $oDataBytes.Add([byte]0)  # null terminator
    $oOffset += $msgidBytes.Length + 1
}

$tOffset = $oOffset
foreach ($msgid in $sortedKeys) {
    $msgstr = $finalTranslations[$msgid]
    $msgstrBytes = [System.Text.Encoding]::UTF8.GetBytes($msgstr)
    $tEntries += @{ length = $msgstrBytes.Length; offset = $tOffset }
    $tDataBytes.AddRange($msgstrBytes)
    $tDataBytes.Add([byte]0)  # null terminator
    $tOffset += $msgstrBytes.Length + 1
}

# Assemble MO file
$stream = [System.IO.File]::Create($moPath)
$writer = [System.IO.BinaryWriter]::new($stream)

# Header
$writer.Write([byte[]]@(0xde, 0x12, 0x04, 0x95))  # magic 0x950412de (little-endian)
$writer.Write([uint32]0)             # version
$writer.Write([uint32]$n)            # number of strings
$writer.Write([uint32]$oTableOffset) # offset of original table
$writer.Write([uint32]$tTableOffset) # offset of translation table
$writer.Write([uint32]0)             # hash table size
$writer.Write([uint32]0)             # hash table offset

# Original string table
foreach ($entry in $oEntries) {
    $writer.Write([uint32]$entry.length)
    $writer.Write([uint32]$entry.offset)
}

# Translation string table
foreach ($entry in $tEntries) {
    $writer.Write([uint32]$entry.length)
    $writer.Write([uint32]$entry.offset)
}

# String data
$writer.Write($oDataBytes.ToArray())
$writer.Write($tDataBytes.ToArray())

$writer.Close()
$stream.Close()

Write-Host ""
Write-Host "Done! $($finalTranslations.Count) translations written."
Write-Host "  .po: $poPath"
Write-Host "  .mo: $moPath"

# updates.fyndable.ai — webroot

Upload de inhoud van deze map naar de document root van updates.fyndable.ai:

    /var/www/updates.fyndable.ai/public_html/

Structuur:

    fyndable-client/
        latest.json              ← metadata (versie, download_url, changelog)
        latest.json.sha256       ← integrity hash van latest.json
        fyndable-client-1.7.0.zip ← de plugin zip
        archive/                 ← oudere zips (leeg)
        beta/
            latest-beta.json     ← beta metadata (leeg placeholder)

Zie tools/UPDATES-SERVER.md voor de volledige Apache-setup.

## Changelog invullen

latest.json wordt door het build-script met een leeg `changelog` veld
gegenereerd. Vul het handmatig in na een build, anders zien klanten een lege
changelog in de "View details" popup in WordPress.

## Nieuwe release uitrollen

1. Bump versie in wp-content/plugins/fyndable-client/ai-seo-client.php
   (regel 5 `Version:` en regel 19 `SSEO_AI_CLIENT_VERSION`).
2. Build:  powershell -ExecutionPolicy Bypass -File tools\build-fyndable-client.ps1
3. Optioneel: vul changelog in tools\updates.fyndable.ai\fyndable-client\latest.json
4. Upload de fyndable-client/ map naar de server.
5. Verwijder oude zips of verplaats ze naar archive/.

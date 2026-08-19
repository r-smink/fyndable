# Setup: updates.fyndable.ai op een Apache VPS

Update-server voor de `fyndable-client` plugin. De client-plugin checkt via het
SaaS-dashboard (`UpdateServer`) of er een nieuwe versie is; het SaaS-dashboard
haalt de metadata op van `https://updates.fyndable.ai/fyndable-client/latest.json`.

## 1. Voorbereiding op de VPS

```bash
sudo apt update && sudo apt install -y apache2 certbot python3-certbot-apache
sudo systemctl enable --now apache2
```

## 2. Directory-structuur

De deploy-scripts gaan uit van deze layout (zie `tools/deploy-updates.ps1`):

```bash
sudo mkdir -p /var/www/updates.fyndable.ai/public_html/fyndable-client/archive
sudo mkdir -p /var/www/updates.fyndable.ai/public_html/fyndable-client/beta
sudo chown -R $USER:www-data /var/www/updates.fyndable.ai/public_html
sudo chmod -R 755 /var/www/updates.fyndable.ai/public_html
```

Resultaat:

```
/var/www/updates.fyndable.ai/public_html/
└── fyndable-client/
    ├── latest.json              ← door deploy-script geüpload
    ├── latest.json.sha256
    ├── fyndable-client-1.8.0.zip
    ├── archive/                 ← oudere zips
    └── beta/
        └── latest-beta.json     ← handmatig of apart script
```

## 3. Apache virtual host

`/etc/apache2/sites-available/updates.fyndable.ai.conf`:

```apache
<VirtualHost *:80>
    ServerName updates.fyndable.ai
    DocumentRoot /var/www/updates.fyndable.ai/public_html

    # Directory listing uit (veiligheid + netheid)
    <Directory /var/www/updates.fyndable.ai/public_html>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    # Juiste MIME types voor JSON en SHA256
    AddType application/json .json
    AddType text/plain .sha256

    # Geen caching op JSON metadata (clients moeten verse versie zien)
    <FilesMatch "\.json$">
        Header set Cache-Control "no-cache, must-revalidate"
        Header set Access-Control-Allow-Origin "*"
    </FilesMatch>

    # Zips mogen lang gecached worden
    <FilesMatch "\.zip$">
        Header set Cache-Control "public, max-age=86400"
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/updates_error.log
    CustomLog ${APACHE_LOG_DIR}/updates_access.log combined
</VirtualHost>
```

Activeren:

```bash
sudo a2ensite updates.fyndable.ai
sudo a2dissite 000-default
sudo systemctl reload apache2
```

## 4. HTTPS (verplicht — client gebruikt `https://`)

```bash
sudo certbot --apache -d updates.fyndable.ai --redirect --agree-tos -m jouw@email.nl
```

Certbot voegt automatisch een `<VirtualHost *:443>` block toe met SSL en een
80→443 redirect.

## 5. DNS

Zorg dat `updates.fyndable.ai` (A/AAAA-record) wijst naar het IP van deze VPS
voordat je certbot draait.

## 6. Upload-rechten voor deploy-script

Het `deploy-updates.ps1` script SCP't als `$User` naar
`/var/www/updates.fyndable.ai/public_html/fyndable-client/`. Zorg dat die
gebruiker schrijfrechten heeft:

```bash
sudo usermod -aG www-data <ssh-user>
sudo chown -R <ssh-user>:www-data /var/www/updates.fyndable.ai/public_html/fyndable-client
sudo chmod -R 775 /var/www/updates.fyndable.ai/public_html/fyndable-client
```

## 7. SaaS-dashboard koppelen

In het SaaS-dashboard: ga naar `admin.php?page=sseo-ai-client-versions` en vul
bij **Update server URL** in:

```
https://updates.fyndable.ai
```

Dit wordt opgeslagen in `sseo_ai_saas_update_server_url`. De `UpdateServer` haalt
dan automatisch `<url>/fyndable-client/latest.json` op en gebruikt dat boven de
handmatig ingevulde velden.

## 8. Eerste release uitrollen

Vanaf je Windows-machine in de repo:

```powershell
# 1. Versie bumpen in wp-content/plugins/fyndable-client/ai-seo-client.php (regels 5 en 19)
# 2. Builden
powershell -ExecutionPolicy Bypass -File tools\build-fyndable-client.ps1

# 3. Deployen
powershell -ExecutionPolicy Bypass -File tools\deploy-updates.ps1 -Version 1.8.0 -User <ssh-user> -Key <pad-naar-private-key> -Archive

# 4. Valideren
powershell -ExecutionPolicy Bypass -File tools\validate-release.ps1
```

## 9. Handmatige controle

```bash
curl -s https://updates.fyndable.ai/fyndable-client/latest.json | jq .
curl -sI https://updates.fyndable.ai/fyndable-client/latest.json   # check Cache-Control header
```

## 10. Belangrijke valkuilen

- **Changelog-veld leeg** na build (`tools/build-fyndable-client.ps1` schrijft
  `changelog = ""` in `latest.json`). Vul `latest.json` na de build handmatig, of
  pas het script aan om CHANGELOG.md te parsen. Bij Pad A verliest de
  optie-changelog het van de remote JSON in `UpdateServer::checkForUpdates()`.
- **`AllowOverride None`** in de vhost — er hoeft geen `.htaccess` te zijn op de
  update-server; alles staat in de vhost. Dat is sneller en veiliger.
- **`AddType application/json .json`** is kritisch — zonder dit serveert Apache
  JSON als `text/plain` en sommige clients/libraries weigeren het dan.
- **Cache-Control `no-cache` op JSON** — anders zien clients tot 6 uur (of langer
  met tussenliggende CDN's) de oude `latest.json`. De `UpdateServer` cached zelf
  ook nog 6u (`fetchRemoteMetadata`), dus na een geforceerde uitrol moet je daar
  ook de `sseo_ai_update_meta_*` transients legen.
- **Beta-kanaal** verwacht `<url>/fyndable-client/beta/latest-beta.json`. Dat
  bestand wordt door geen van de scripts gegenereerd — handmatig aanmaken als je
  beta's wilt uitrollen.

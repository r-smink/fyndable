# Setup: updates.fyndable.ai op een Nginx VPS

Update-server voor de `fyndable-client` plugin. De client-plugin checkt via het
SaaS-dashboard (`UpdateServer`) of er een nieuwe versie is; het SaaS-dashboard
haalt de metadata op van `https://updates.fyndable.ai/fyndable-client/latest.json`.

De onderstaande setup gebruikt **Nginx** als aanbevolen webserver. Bestaande
Apache-VPS'en blijven werken — zie [Appendix A](#appendix-a-apache-setup-legacy)
voor de Apache-instructies.

## 1. Voorbereiding op de VPS

```bash
sudo apt update && sudo apt install -y nginx certbot python3-certbot-nginx
sudo systemctl enable --now nginx
```

## 2. Directory-structuur

De deploy-scripts gaan uit van deze layout (zie `tools/deploy-updates.ps1`):

```bash
sudo mkdir -p /var/www/updates.fyndable.ai/public_html/fyndable-client/archive
sudo mkdir -p /var/www/updates.fyndable.ai/public_html/fyndable-client/beta
sudo chown -R $USER:www-data /var/www/updates.fyndable.ai/public_html
sudo chmod -R 755 /var/www/updates.fyndable.ai/public_html
```

> **Opmerking:** `www-data` is de Nginx-user op Debian/Ubuntu. Op andere
> distro's (Alpine, RHEL/Fedora) kan dit `nginx` zijn — pas de `chown`-groep
> dan dienovereenkomstig aan.

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

## 3. Nginx virtual host

Een kant-en-klare vhost-config staat in de repo:
`tools/updates.fyndable.ai/nginx/updates.fyndable.ai.conf`.

Kopieer deze naar de VPS en activeer:

```bash
sudo cp updates.fyndable.ai.conf /etc/nginx/sites-available/updates.fyndable.ai
sudo ln -s /etc/nginx/sites-available/updates.fyndable.ai /etc/nginx/sites-enabled/updates.fyndable.ai

# (optioneel) verwijder de default-site als die nog actief is
sudo rm /etc/nginx/sites-enabled/default

# Config testen en herladen
sudo nginx -t
sudo systemctl reload nginx
```

De vhost regelt:

- `server_name updates.fyndable.ai` met `root /var/www/updates.fyndable.ai/public_html`
- `autoindex off` (geen directory listing — veiligheid + netheid)
- Juiste MIME-types via `default_type`:
  - `.json` → `application/json` (kritiek — zonder dit serveert Nginx JSON
    als `application/octet-stream` en sommige clients/libraries weigeren het)
  - `.sha256` → `text/plain`
  - `.zip` → `application/zip`
- `Cache-Control: no-cache, must-revalidate` + `Access-Control-Allow-Origin: *`
  op `.json` (clients moeten verse metadata zien)
- `Cache-Control: public, max-age=86400` op `.zip` (grote bestanden, naam
  verandert per release)
- Verbergen van verborgen bestanden (`location ~ /\.` → `deny all`)
- Eigen access/error logs naar `/var/log/nginx/updates.fyndable.ai-*.log`

Er is geen `.htaccess`-mechanisme nodig — alles staat in de vhost. Dat is
sneller en veiliger.

## 4. HTTPS (verplicht — client gebruikt `https://`)

```bash
sudo certbot --nginx -d updates.fyndable.ai --redirect --agree-tos -m jouw@email.nl
```

Certbot voegt automatisch een `listen 443 ssl;` server-block toe met de
certificaatpaden, en een 80→443 redirect in het bestaande `:80`-block.

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
curl -sI https://updates.fyndable.ai/fyndable-client/latest.json   # check Cache-Control + Content-Type
curl -sI https://updates.fyndable.ai/fyndable-client/              # moet 403 zijn (autoindex off)
```

Verwachte headers op `latest.json`:

```
HTTP/1.1 200 OK
Content-Type: application/json
Cache-Control: no-cache, must-revalidate
Access-Control-Allow-Origin: *
```

## 10. Belangrijke valkuilen

- **Changelog-veld leeg** na build (`tools/build-fyndable-client.ps1` schrijft
  `changelog = ""` in `latest.json`). Vul `latest.json` na de build handmatig, of
  pas het script aan om CHANGELOG.md te parsen. Bij Pad A verliest de
  optie-changelog het van de remote JSON in `UpdateServer::checkForUpdates()`.
- **`autoindex off`** in de vhost — geen directory listing op de update-server.
  Dit is de Nginx-equivalent van Apache's `Options -Indexes`.
- **`default_type application/json`** in `location ~ \.json$` is kritiek — zonder
  dit serveert Nginx JSON als `application/octet-stream` en sommige clients/
  libraries weigeren het dan. Gebruik `default_type` en **niet** een `types {}`
  -blok binnen een `location`, want dat vervangt de globale MIME-map voor die
  location en geeft onverwachte MIME-wijzigingen voor andere extensies.
- **Cache-Control `no-cache` op JSON** — anders zien clients tot 6 uur (of langer
  met tussenliggende CDN's) de oude `latest.json`. De `UpdateServer` cached zelf
  ook nog 6u (`fetchRemoteMetadata`), dus na een geforceerde uitrol moet je daar
  ook de `sseo_ai_update_meta_*` transients legen.
- **Beta-kanaal** verwacht `<url>/fyndable-client/beta/latest-beta.json`. Dat
  bestand wordt door geen van de scripts gegenereerd — handmatig aanmaken als je
  beta's wilt uitrollen.

---

## Appendix A: Apache setup (legacy)

Voor bestaande VPS'en die al op Apache draaien. Nieuwe installaties gebruiken
de Nginx-setup hierboven.

### A.1 Voorbereiding

```bash
sudo apt update && sudo apt install -y apache2 certbot python3-certbot-apache
sudo systemctl enable --now apache2
```

### A.2 Apache virtual host

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

### A.3 HTTPS (Apache)

```bash
sudo certbot --apache -d updates.fyndable.ai --redirect --agree-tos -m jouw@email.nl
```

Certbot voegt automatisch een `<VirtualHost *:443>` block toe met SSL en een
80→443 redirect.

### A.4 Apache-specifieke valkuilen

- **`AllowOverride None`** in de vhost — er hoeft geen `.htaccess` te zijn op de
  update-server; alles staat in de vhost. Dat is sneller en veiliger.
- **`AddType application/json .json`** is kritiek — zonder dit serveert Apache
  JSON als `text/plain` en sommige clients/libraries weigeren het dan.

# AI SEO Assistant - Eigen License Server

Deze map bevat een complete license server implementatie die je zelf kunt hosten om licenties te beheren voor de AI SEO Assistant plugin.

## Bestanden

- `license-server.php` - De hoofd API endpoint
- `index.html` - Admin interface voor licentie beheer

## Installatie

### 1. Database Aanmaken

```sql
CREATE DATABASE licenses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'license_user'@'localhost' IDENTIFIED BY 'jouw_wachtwoord';
GRANT ALL PRIVILEGES ON licenses.* TO 'license_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Configuratie

Open `license-server.php` en pas de volgende regels aan:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'licenses');
define('DB_USER', 'license_user');
define('DB_PASS', 'jouw_wachtwoord');
define('API_SECRET', 'jouw_geheime_key_hier');
```

### 3. Installatie

Upload de bestanden naar je server (bijvoorbeeld: `https://jouw-server.com/license-server/`)

Open de installatie URL:
```
https://jouw-server.com/license-server/license-server.php?action=install
```

Je zou "Database tables created successfully!" moeten zien.

### 4. Plugin Configuratie

Ga in WordPress naar: **AI SEO → Settings → SaaS**

Vul bij "License Server URL" je license server URL in:
```
https://jouw-server.com/license-server/license-server.php
```

## Gebruik

### Admin Interface

Ga naar:
```
https://jouw-server.com/license-server/
```

Hier kun je:
- Nieuwe licenties aanmaken
- Bestaande licenties bekijken
- Statistieken zien

### API Endpoints

De license server ondersteunt de volgende endpoints:

#### POST /api/activate
Activeert een licentie voor een site.

**Parameters:**
- `license_key` - De licentie key
- `product_id` - Product ID (bijv. "ai-seo-assistant-pro")
- `site_url` - URL van de WordPress site
- `site_name` - Naam van de site

**Response:**
```json
{
  "success": true,
  "message": "License activated",
  "tier": "professional",
  "expires": "2025-12-31 23:59:59",
  "max_sites": 3,
  "site_count": 1,
  "features": ["basic_seo", "sitemap", ...]
}
```

#### POST /api/deactivate
Deactiveert een licentie voor een site.

**Parameters:**
- `license_key` - De licentie key
- `product_id` - Product ID
- `site_url` - URL van de WordPress site

#### POST /api/validate
Valideert een licentie.

**Parameters:**
- `license_key` - De licentie key
- `product_id` - Product ID
- `site_url` - URL van de WordPress site

**Response:**
```json
{
  "valid": true,
  "status": "active",
  "tier": "professional",
  "expires": "2025-12-31 23:59:59",
  "max_sites": 3,
  "site_count": 1,
  "features": [...]
}
```

#### POST /api/create_license
Maakt een nieuwe licentie aan (admin only).

**Parameters:**
- `product_id` - Product ID
- `tier` - Licentie tier (starter, professional, business, agency)
- `expires_days` - Aantal dagen geldig (leeg = lifetime)
- `customer_email` - Email van klant
- `customer_name` - Naam van klant
- `notes` - Optionele notities

**Response:**
```json
{
  "success": true,
  "message": "License created",
  "license_key": "AISEO-A1B2C3D4-E5F6G7H8-I9J0K1L2"
}
```

#### GET /api/list_licenses
Lijst van alle licenties (admin only).

**Parameters:**
- `status` - Filter op status (optional)
- `product` - Filter op product (optional)

## Licentie Tiers

| Tier | Max Sites | Features |
|------|-----------|----------|
| **Starter** | 1 | Basic SEO, Sitemap, Schema, Redirects, 404 Monitor, TruSEO |
| **Professional** | 3 | + Extended Sitemaps, LSI Keywords, Smart Tags, Link Assistant, AI Alt Text |
| **Business** | 10 | + Local SEO, WooCommerce SEO, Role Permissions |
| **Agency** | 100 | + White Label, Priority Support, API Access, AI Features |

## Beveiliging

1. **Gebruik altijd HTTPS** - De license server vereist SSL in productie
2. **Beperk toegang tot de admin interface** - Gebruik .htaccess of IP whitelisting
3. **Wijzig de API_SECRET** - Gebruik een unieke, sterke key
4. **Beveilig je database credentials** - Gebruik aparte database user met beperkte rechten

## Troubleshooting

### Licentie activatie werkt niet

Controleer:
1. Is de license server URL correct ingevuld in de plugin instellingen?
2. Is HTTPS correct geconfigureerd?
3. Zijn de database credentials correct?
4. Staat de server URL in de allowlist van de plugin?

### Database connection error

- Controleer DB_HOST, DB_NAME, DB_USER, DB_PASS in license-server.php
- Zorg dat de database user toegang heeft vanaf de webserver
- Controleer of de database tabellen zijn aangemaakt (actie=install)

## Voorbeeld .htaccess (beveiliging)

```apache
# Beveilig admin interface met basis auth
<Files "index.html">
    AuthType Basic
    AuthName "License Server Admin"
    AuthUserFile /pad/naar/.htpasswd
    Require valid-user
</Files>

# Blokkeer directe toegang tot database (indien aanwezig)
<Files "licenses.db">
    Order allow,deny
    Deny from all
</Files>
```

## Updates

Bij een update van de plugin:
1. Backup de database
2. Upload de nieuwe license-server.php
3. Controleer of alle functionaliteit nog werkt

## Support

Voor vragen over de license server implementatie, raadpleeg de plugin documentatie of neem contact op met de ontwikkelaar.

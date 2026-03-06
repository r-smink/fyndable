<?php
/**
 * AI SEO Assistant - Example License Server
 * 
 * Dit is een voorbeeld implementatie van een license server die je zelf kunt hosten.
 * Plaats dit bestand op je eigen server en configureer de URL in de plugin instellingen.
 * 
 * Vereisten:
 * - PHP 7.4+
 * - MySQL database
 * - SSL (HTTPS) - verplicht voor productie
 * 
 * Installatie:
 * 1. Kopieer dit bestand naar je server (bijv. /license-server/)
 * 2. Maak een database aan en configureer de credentials hieronder
 * 3. Draai de installatie: https://jouw-server.com/license-server/?action=install
 * 4. Configureer de license server URL in de plugin: AI SEO → Settings → SaaS
 */

// Database configuratie - PAS DIT AAN
define('DB_HOST', 'localhost');
define('DB_NAME', 'licenses');
define('DB_USER', 'license_user');
define('DB_PASS', 'jouw_wachtwoord');
define('DB_TABLE', 'licenses');

// Beveiliging - PAS DIT AAN
// Deze key moet overeenkomen met wat de plugin verwacht (optioneel)
define('API_SECRET', 'jouw_geheime_key_hier');

// Product configuratie
$PRODUCTS = [
    'ai-seo-assistant-pro' => [
        'name' => 'AI SEO Assistant Pro',
        'tiers' => [
            'starter' => ['name' => 'Starter', 'max_sites' => 1, 'price' => 49],
            'professional' => ['name' => 'Professional', 'max_sites' => 3, 'price' => 99],
            'business' => ['name' => 'Business', 'max_sites' => 10, 'price' => 199],
            'agency' => ['name' => 'Agency', 'max_sites' => 100, 'price' => 499],
        ],
    ],
];

// Features per tier
$TIER_FEATURES = [
    'starter' => ['basic_seo', 'title_meta', 'social_preview', 'sitemap', 'schema_markup', 'redirects', '404_monitor', 'truseo_score', 'focus_keyphrase'],
    'professional' => ['basic_seo', 'title_meta', 'social_preview', 'sitemap', 'extended_sitemaps', 'schema_markup', 'redirects', '404_monitor', 'truseo_score', 'focus_keyphrase', 'lsi_keywords', 'smart_tags', 'serp_preview', 'link_assistant', 'image_alt', 'seo_revisions', 'import_export'],
    'business' => ['basic_seo', 'title_meta', 'social_preview', 'sitemap', 'extended_sitemaps', 'schema_markup', 'redirects', '404_monitor', 'truseo_score', 'focus_keyphrase', 'lsi_keywords', 'smart_tags', 'serp_preview', 'link_assistant', 'image_alt', 'local_seo', 'woocommerce_seo', 'seo_revisions', 'role_permissions', 'import_export'],
    'agency' => ['basic_seo', 'title_meta', 'social_preview', 'sitemap', 'extended_sitemaps', 'schema_markup', 'redirects', '404_monitor', 'truseo_score', 'focus_keyphrase', 'lsi_keywords', 'smart_tags', 'serp_preview', 'link_assistant', 'image_alt', 'ai_repurposing', 'ai_alt_text', 'ai_keypoints', 'local_seo', 'woocommerce_seo', 'seo_revisions', 'role_permissions', 'import_export', 'white_label', 'priority_support', 'api_access'],
];

/**
 * Database class
 */
class LicenseDB {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Database error'], 500);
        }
    }
    
    public function install(): void {
        $sql = "CREATE TABLE IF NOT EXISTS " . DB_TABLE . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_key VARCHAR(50) NOT NULL UNIQUE,
            product_id VARCHAR(50) NOT NULL,
            tier VARCHAR(20) NOT NULL DEFAULT 'starter',
            status ENUM('active', 'inactive', 'expired', 'suspended') DEFAULT 'active',
            max_sites INT DEFAULT 1,
            site_count INT DEFAULT 0,
            sites TEXT, -- JSON array of activated sites
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            activated_at DATETIME,
            last_check DATETIME,
            customer_email VARCHAR(100),
            customer_name VARCHAR(100),
            notes TEXT,
            INDEX idx_license_key (license_key),
            INDEX idx_status (status),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->pdo->exec($sql);
        
        // Create admin users table
        $sql2 = "CREATE TABLE IF NOT EXISTS license_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->pdo->exec($sql2);
        
        echo "Database tables created successfully!";
    }
    
    public function getLicense(string $key): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM " . DB_TABLE . " WHERE license_key = ?");
        $stmt->execute([$key]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($license) {
            $license['sites'] = json_decode($license['sites'] ?? '[]', true) ?: [];
        }
        
        return $license ?: null;
    }
    
    public function createLicense(array $data): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO " . DB_TABLE . " 
            (license_key, product_id, tier, max_sites, expires_at, customer_email, customer_name, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['license_key'],
            $data['product_id'],
            $data['tier'] ?? 'starter',
            $data['max_sites'] ?? 1,
            $data['expires_at'] ?? null,
            $data['customer_email'] ?? null,
            $data['customer_name'] ?? null,
            $data['notes'] ?? null,
        ]);
    }
    
    public function activateSite(string $key, string $siteUrl, string $siteName): bool {
        $license = $this->getLicense($key);
        if (!$license) return false;
        
        $sites = $license['sites'];
        
        // Check if already activated for this site
        foreach ($sites as $site) {
            if ($site['url'] === $siteUrl) {
                return true; // Already activated
            }
        }
        
        // Check site limit
        if (count($sites) >= $license['max_sites']) {
            return false;
        }
        
        $sites[] = [
            'url' => $siteUrl,
            'name' => $siteName,
            'activated_at' => date('Y-m-d H:i:s'),
        ];
        
        $stmt = $this->pdo->prepare("
            UPDATE " . DB_TABLE . " 
            SET sites = ?, site_count = ?, status = 'active', activated_at = COALESCE(activated_at, NOW()) 
            WHERE license_key = ?
        ");
        
        return $stmt->execute([json_encode($sites), count($sites), $key]);
    }
    
    public function deactivateSite(string $key, string $siteUrl): bool {
        $license = $this->getLicense($key);
        if (!$license) return false;
        
        $sites = array_filter($license['sites'], function($site) use ($siteUrl) {
            return $site['url'] !== $siteUrl;
        });
        
        $sites = array_values($sites); // Re-index
        
        $stmt = $this->pdo->prepare("
            UPDATE " . DB_TABLE . " 
            SET sites = ?, site_count = ?, status = ? 
            WHERE license_key = ?
        ");
        
        $status = empty($sites) ? 'inactive' : 'active';
        
        return $stmt->execute([json_encode($sites), count($sites), $status, $key]);
    }
    
    public function updateLastCheck(string $key): void {
        $stmt = $this->pdo->prepare("UPDATE " . DB_TABLE . " SET last_check = NOW() WHERE license_key = ?");
        $stmt->execute([$key]);
    }
    
    public function listLicenses(string $status = null, string $product = null): array {
        $sql = "SELECT * FROM " . DB_TABLE . " WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        if ($product) {
            $sql .= " AND product_id = ?";
            $params[] = $product;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($licenses as &$license) {
            $license['sites'] = json_decode($license['sites'] ?? '[]', true) ?: [];
        }
        
        return $licenses;
    }
}

/**
 * API Handler
 */
class LicenseAPI {
    private $db;
    
    public function __construct() {
        $this->db = new LicenseDB();
    }
    
    public function handle(): void {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';
        
        switch ($action) {
            case 'install':
                $this->db->install();
                break;
                
            case 'activate':
                $this->activate();
                break;
                
            case 'deactivate':
                $this->deactivate();
                break;
                
            case 'validate':
                $this->validate();
                break;
                
            case 'create_license':
                $this->createLicense();
                break;
                
            case 'list_licenses':
                $this->listLicenses();
                break;
                
            default:
                $this->jsonResponse(['success' => false, 'message' => 'Unknown action']);
        }
    }
    
    private function activate(): void {
        $key = sanitize_text_field($_POST['license_key'] ?? '');
        $productId = sanitize_text_field($_POST['product_id'] ?? '');
        $siteUrl = sanitize_url($_POST['site_url'] ?? '');
        $siteName = sanitize_text_field($_POST['site_name'] ?? '');
        
        if (empty($key) || empty($productId) || empty($siteUrl)) {
            $this->jsonResponse(['success' => false, 'message' => 'Missing required parameters']);
        }
        
        $license = $this->db->getLicense($key);
        
        if (!$license) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid license key']);
        }
        
        if ($license['product_id'] !== $productId) {
            $this->jsonResponse(['success' => false, 'message' => 'License not valid for this product']);
        }
        
        if ($license['status'] === 'expired' || ($license['expires_at'] && strtotime($license['expires_at']) < time())) {
            $this->jsonResponse(['success' => false, 'message' => 'License has expired']);
        }
        
        if ($license['status'] === 'suspended') {
            $this->jsonResponse(['success' => false, 'message' => 'License is suspended']);
        }
        
        // Activate site
        $result = $this->db->activateSite($key, $siteUrl, $siteName);
        
        if (!$result) {
            $this->jsonResponse(['success' => false, 'message' => 'Site limit reached']);
        }
        
        // Refresh license data
        $license = $this->db->getLicense($key);
        
        $this->jsonResponse([
            'success' => true,
            'message' => 'License activated',
            'tier' => $license['tier'],
            'expires' => $license['expires_at'],
            'max_sites' => $license['max_sites'],
            'site_count' => $license['site_count'],
            'features' => $this->getFeaturesForTier($license['tier']),
        ]);
    }
    
    private function deactivate(): void {
        $key = sanitize_text_field($_POST['license_key'] ?? '');
        $productId = sanitize_text_field($_POST['product_id'] ?? '');
        $siteUrl = sanitize_url($_POST['site_url'] ?? '');
        
        if (empty($key) || empty($siteUrl)) {
            $this->jsonResponse(['success' => false, 'message' => 'Missing required parameters']);
        }
        
        $this->db->deactivateSite($key, $siteUrl);
        
        $this->jsonResponse(['success' => true, 'message' => 'License deactivated']);
    }
    
    private function validate(): void {
        $key = sanitize_text_field($_POST['license_key'] ?? '');
        $productId = sanitize_text_field($_POST['product_id'] ?? '');
        $siteUrl = sanitize_url($_POST['site_url'] ?? '');
        
        if (empty($key)) {
            $this->jsonResponse(['success' => false, 'message' => 'Missing license key']);
        }
        
        $license = $this->db->getLicense($key);
        
        if (!$license) {
            $this->jsonResponse(['valid' => false, 'message' => 'Invalid license key']);
        }
        
        if ($license['product_id'] !== $productId) {
            $this->jsonResponse(['valid' => false, 'message' => 'License not valid for this product']);
        }
        
        // Check expiration
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            $this->jsonResponse(['valid' => false, 'status' => 'expired', 'message' => 'License has expired']);
        }
        
        // Check if site is activated
        $siteActivated = false;
        foreach ($license['sites'] as $site) {
            if ($site['url'] === $siteUrl) {
                $siteActivated = true;
                break;
            }
        }
        
        if (!$siteActivated && !empty($siteUrl)) {
            $this->jsonResponse(['valid' => false, 'message' => 'Site not activated for this license']);
        }
        
        $this->db->updateLastCheck($key);
        
        $this->jsonResponse([
            'valid' => true,
            'status' => $license['status'],
            'tier' => $license['tier'],
            'expires' => $license['expires_at'],
            'max_sites' => $license['max_sites'],
            'site_count' => $license['site_count'],
            'features' => $this->getFeaturesForTier($license['tier']),
        ]);
    }
    
    private function createLicense(): void {
        // Admin only - implementeer authenticatie
        $data = [
            'license_key' => $this->generateLicenseKey(),
            'product_id' => sanitize_text_field($_POST['product_id'] ?? 'ai-seo-assistant-pro'),
            'tier' => sanitize_text_field($_POST['tier'] ?? 'starter'),
            'max_sites' => intval($_POST['max_sites'] ?? 1),
            'expires_at' => !empty($_POST['expires_days']) ? date('Y-m-d H:i:s', strtotime('+' . intval($_POST['expires_days']) . ' days')) : null,
            'customer_email' => sanitize_email($_POST['customer_email'] ?? ''),
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        ];
        
        if ($this->db->createLicense($data)) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'License created',
                'license_key' => $data['license_key'],
            ]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to create license']);
        }
    }
    
    private function listLicenses(): void {
        $status = sanitize_text_field($_GET['status'] ?? '');
        $product = sanitize_text_field($_GET['product'] ?? '');
        
        $licenses = $this->db->listLicenses($status ?: null, $product ?: null);
        
        $this->jsonResponse(['success' => true, 'licenses' => $licenses]);
    }
    
    private function generateLicenseKey(): string {
        return 'AISEO-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
    }
    
    private function getFeaturesForTier(string $tier): array {
        global $TIER_FEATURES;
        return $TIER_FEATURES[$tier] ?? $TIER_FEATURES['starter'];
    }
    
    private function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

// Helper functions
function sanitize_text_field(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function sanitize_url(string $url): string {
    return filter_var(trim($url), FILTER_SANITIZE_URL);
}

function sanitize_email(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function sanitize_textarea_field(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Handle CORS for WordPress requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Run API
$api = new LicenseAPI();
$api->handle();

<?php

namespace SSEOAISaaS;

/**
 * Tenant Management Repository
 * 
 * Manages multi-tenant data isolation for SaaS deployment.
 * Each tenant gets their own isolated data slice across all tables.
 */
class TenantRepository
{
    private const TENANTS_TABLE = 'sseo_ai_tenants';
    private const TENANT_SETTINGS_TABLE = 'sseo_ai_tenant_settings';
    private const TENANT_USAGE_TABLE = 'sseo_ai_tenant_usage';
    private const LICENSE_KEYS_TABLE = 'sseo_ai_license_keys';
    
    private ?string $currentTenantId = null;
    
    /**
     * Create tenant tables
     */
    public function maybeCreateTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $charsetCollate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        
        // Main tenants table
        $sql1 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::TENANTS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_key varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            domain varchar(255) DEFAULT NULL,
            email varchar(255) NOT NULL,
            status enum('active', 'suspended', 'cancelled') NOT NULL DEFAULT 'active',
            tier enum('free', 'trial', 'starter', 'professional', 'business', 'agency') NOT NULL DEFAULT 'free',
            license_key varchar(255) DEFAULT NULL,
            max_sites int(11) NOT NULL DEFAULT 1,
            rate_limit int(11) NOT NULL DEFAULT 60,
            api_calls_limit int(11) NOT NULL DEFAULT 1000,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            last_active datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_key (tenant_key),
            UNIQUE KEY license_key (license_key),
            KEY status (status),
            KEY tier (tier),
            KEY expires_at (expires_at)
        ) $charsetCollate;";
        
        // Tenant settings (override global settings)
        $sql2 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::TENANT_SETTINGS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext DEFAULT NULL,
            is_encrypted tinyint(1) NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_setting (tenant_id, setting_key),
            KEY tenant_id (tenant_id)
        ) $charsetCollate;";
        
        // Tenant usage tracking (for billing/limits)
        $sql3 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::TENANT_USAGE_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            period varchar(7) NOT NULL COMMENT 'YYYY-MM format',
            api_calls int(11) NOT NULL DEFAULT 0,
            api_cost decimal(10,4) NOT NULL DEFAULT 0.0000,
            serp_requests int(11) NOT NULL DEFAULT 0,
            content_generated int(11) NOT NULL DEFAULT 0,
            keywords_tracked int(11) NOT NULL DEFAULT 0,
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_period (tenant_id, period),
            KEY tenant_id (tenant_id),
            KEY period (period)
        ) $charsetCollate;";
        
        // License keys table (self-hosted license management)
        $sql4 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::LICENSE_KEYS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key varchar(255) NOT NULL,
            license_type enum('test', 'free', 'paid', 'lifetime', 'trial') NOT NULL DEFAULT 'paid',
            tier enum('free', 'trial', 'starter', 'professional', 'business', 'agency') NOT NULL DEFAULT 'starter',
            status enum('active', 'used', 'revoked', 'expired') NOT NULL DEFAULT 'active',
            max_sites int(11) NOT NULL DEFAULT 1,
            rate_limit int(11) NOT NULL DEFAULT 60,
            api_calls_limit int(11) NOT NULL DEFAULT 1000,
            expires_days int(11) DEFAULT NULL COMMENT 'Days until expiration from activation',
            created_by bigint(20) unsigned DEFAULT NULL COMMENT 'Admin user ID',
            assigned_to varchar(255) DEFAULT NULL COMMENT 'Email or tenant_key',
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            activated_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            revoked_at datetime DEFAULT NULL,
            revoked_reason text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY status (status),
            KEY license_type (license_type),
            KEY tier (tier),
            KEY created_at (created_at)
        ) $charsetCollate;";
        
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }
    
    /**
     * Set current tenant context
     */
    public function setCurrentTenant(string $tenantKey): void
    {
        $this->currentTenantId = $tenantKey;
    }
    
    /**
     * Get current tenant context
     */
    public function getCurrentTenant(): ?string
    {
        return $this->currentTenantId;
    }
    
    /**
     * Clear tenant context
     */
    public function clearCurrentTenant(): void
    {
        $this->currentTenantId = null;
    }
    
    /**
     * Create new tenant
     */
    public function createTenant(array $data): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;
        
        // Generate tenant key if not provided
        if (empty($data['tenant_key'])) {
            $data['tenant_key'] = $this->generateTenantKey();
        }
        
        // Validate required fields
        if (empty($data['name']) || empty($data['email'])) {
            return new \WP_Error('missing_fields', __('Name and email are required', 'ai-seo-saas'));
        }
        
        // Check for duplicate tenant key
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE tenant_key = %s",
            $data['tenant_key']
        ));
        
        if ($existing) {
            return new \WP_Error('duplicate_key', __('Tenant key already exists', 'ai-seo-saas'));
        }
        
        $result = $wpdb->insert($table, [
            'tenant_key' => sanitize_text_field($data['tenant_key']),
            'name' => sanitize_text_field($data['name']),
            'domain' => !empty($data['domain']) ? sanitize_text_field($data['domain']) : null,
            'email' => sanitize_email($data['email']),
            'status' => $data['status'] ?? 'active',
            'tier' => $data['tier'] ?? 'free',
            'license_key' => !empty($data['license_key']) ? $data['license_key'] : null,
            'max_sites' => (int)($data['max_sites'] ?? 1),
            'rate_limit' => (int)($data['rate_limit'] ?? 60),
            'api_calls_limit' => (int)($data['api_calls_limit'] ?? 1000),
            'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            'metadata' => !empty($data['metadata']) ? wp_json_encode($data['metadata']) : null,
        ]);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create tenant', 'ai-seo-saas'));
        }
        
        $tenantId = $wpdb->insert_id;
        
        return [
            'id' => $tenantId,
            'tenant_key' => $data['tenant_key'],
            'success' => true,
        ];
    }
    
    /**
     * Get tenant by key
     */
    public function getTenant(string $tenantKey): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tenant_key = %s",
            $tenantKey
        ), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        $row['metadata'] = !empty($row['metadata']) ? json_decode($row['metadata'], true) : [];
        
        return $row;
    }
    
    /**
     * Get tenant by license key
     */
    public function getTenantByLicense(string $licenseKey): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s",
            $licenseKey
        ), ARRAY_A);
        
        if (!$row) {
            return null;
        }
        
        $row['metadata'] = !empty($row['metadata']) ? json_decode($row['metadata'], true) : [];
        
        return $row;
    }
    
    /**
     * Update tenant
     */
    public function updateTenant(string $tenantKey, array $data): bool|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;
        
        $allowedFields = ['name', 'domain', 'email', 'status', 'tier', 'max_sites', 
                         'rate_limit', 'api_calls_limit', 'expires_at', 'license_key'];
        
        $update = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }
        
        if (isset($data['metadata'])) {
            $update['metadata'] = wp_json_encode($data['metadata']);
        }
        
        if (empty($update)) {
            return new \WP_Error('no_data', __('No data to update', 'ai-seo-saas'));
        }
        
        $result = $wpdb->update(
            $table,
            $update,
            ['tenant_key' => $tenantKey],
            null,
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete tenant (soft delete by suspending)
     */
    public function suspendTenant(string $tenantKey, string $reason = ''): bool
    {
        return $this->updateTenant($tenantKey, [
            'status' => 'suspended'
        ]);
    }
    
    /**
     * Get all tenants (wrapper for getTenants without filters)
     */
    public function getAllTenants(int $limit = 100): array
    {
        return $this->getTenants([], $limit, 0);
    }

    /**
     * Get all tenants with optional filtering
     */
    public function getTenants(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['tier'])) {
            $where[] = 'tier = %s';
            $params[] = $filters['tier'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE %s OR email LIKE %s OR tenant_key LIKE %s)';
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table WHERE $whereClause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        foreach ($rows as &$row) {
            $row['metadata'] = !empty($row['metadata']) ? json_decode($row['metadata'], true) : [];
        }
        
        return $rows;
    }
    
    /**
     * Count tenants
     */
    public function countTenants(array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['tier'])) {
            $where[] = 'tier = %s';
            $params[] = $filters['tier'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $whereClause",
            $params
        ));
    }
    
    /**
     * Get or create tenant setting
     */
    public function getTenantSetting(string $tenantKey, string $key, $default = null)
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . self::TENANT_SETTINGS_TABLE;
        
        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) {
            return $default;
        }
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $settingsTable WHERE tenant_id = %d AND setting_key = %s",
            $tenant['id'],
            $key
        ));
        
        if ($value === null) {
            return $default;
        }
        
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }
    
    /**
     * Set tenant setting
     */
    public function setTenantSetting(string $tenantKey, string $key, $value): bool
    {
        global $wpdb;
        $settingsTable = $wpdb->prefix . self::TENANT_SETTINGS_TABLE;
        
        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) {
            return false;
        }
        
        $serialized = is_array($value) || is_object($value) ? wp_json_encode($value) : $value;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $settingsTable WHERE tenant_id = %d AND setting_key = %s",
            $tenant['id'],
            $key
        ));
        
        if ($existing) {
            return $wpdb->update(
                $settingsTable,
                ['setting_value' => $serialized],
                ['id' => $existing]
            ) !== false;
        }
        
        return $wpdb->insert($settingsTable, [
            'tenant_id' => $tenant['id'],
            'setting_key' => $key,
            'setting_value' => $serialized,
        ]) !== false;
    }
    
    /**
     * Track tenant usage
     */
    public function trackUsage(string $tenantKey, string $metric, int $count = 1, float $cost = 0): void
    {
        global $wpdb;
        $usageTable = $wpdb->prefix . self::TENANT_USAGE_TABLE;
        
        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) {
            return;
        }
        
        $period = current_time('Y-m');
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $usageTable WHERE tenant_id = %d AND period = %s",
            $tenant['id'],
            $period
        ));
        
        if ($existing) {
            // Update existing record
            $update = [];
            switch ($metric) {
                case 'api_calls':
                    $update['api_calls'] = "api_calls + $count";
                    break;
                case 'api_cost':
                    $update['api_cost'] = "api_cost + $cost";
                    break;
                case 'serp_requests':
                    $update['serp_requests'] = "serp_requests + $count";
                    break;
                case 'content_generated':
                    $update['content_generated'] = "content_generated + $count";
                    break;
                case 'keywords_tracked':
                    $update['keywords_tracked'] = "keywords_tracked + $count";
                    break;
            }
            
            if (!empty($update)) {
                $setClause = [];
                foreach ($update as $col => $expr) {
                    $setClause[] = "$col = $expr";
                }
                $wpdb->query("UPDATE $usageTable SET " . implode(', ', $setClause) . 
                    " WHERE id = " . (int)$existing);
            }
        } else {
            // Create new record
            $data = [
                'tenant_id' => $tenant['id'],
                'period' => $period,
                'api_calls' => $metric === 'api_calls' ? $count : 0,
                'api_cost' => $metric === 'api_cost' ? $cost : 0,
                'serp_requests' => $metric === 'serp_requests' ? $count : 0,
                'content_generated' => $metric === 'content_generated' ? $count : 0,
                'keywords_tracked' => $metric === 'keywords_tracked' ? $count : 0,
            ];
            
            $wpdb->insert($usageTable, $data);
        }
        
        // Update last active
        $wpdb->update(
            $wpdb->prefix . self::TENANTS_TABLE,
            ['last_active' => current_time('mysql')],
            ['id' => $tenant['id']]
        );
    }
    
    /**
     * Get tenant usage for period
     */
    public function getTenantUsage(string $tenantKey, string $period = null): array
    {
        global $wpdb;
        $usageTable = $wpdb->prefix . self::TENANT_USAGE_TABLE;
        
        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) {
            return [];
        }
        
        if ($period === null) {
            $period = current_time('Y-m');
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $usageTable WHERE tenant_id = %d AND period = %s",
            $tenant['id'],
            $period
        ), ARRAY_A) ?: [
            'api_calls' => 0,
            'api_cost' => 0,
            'serp_requests' => 0,
            'content_generated' => 0,
            'keywords_tracked' => 0,
        ];
    }
    
    /**
     * Get usage history
     */
    public function getTenantUsageHistory(string $tenantKey, int $months = 12): array
    {
        global $wpdb;
        $usageTable = $wpdb->prefix . self::TENANT_USAGE_TABLE;
        
        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $usageTable WHERE tenant_id = %d ORDER BY period DESC LIMIT %d",
            $tenant['id'],
            $months
        ), ARRAY_A);
    }
    
    /**
     * Update tenant's monthly API cost
     */
    public function updateMonthlyCost(int $tenantId, float $cost): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sseo_ai_tenants';
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} 
             SET monthly_api_cost = monthly_api_cost + %f,
                 updated_at = NOW()
             WHERE id = %d",
            $cost,
            $tenantId
        ));
    }

    /**
     * Check if tenant has exceeded limits
     */
    public function checkTenantLimits(string $tenantKey, array $tierLimits = []): array
    {
        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) {
            return ['valid' => false, 'error' => 'Tenant not found'];
        }
        
        if ($tenant['status'] !== 'active') {
            return ['valid' => false, 'error' => 'Tenant account is ' . $tenant['status']];
        }
        
        if (!empty($tenant['expires_at']) && strtotime($tenant['expires_at']) < time()) {
            return ['valid' => false, 'error' => 'Tenant subscription has expired'];
        }
        
        $usage = $this->getTenantUsage($tenantKey);
        
        // Get limits from tier settings or use defaults
        $apiLimit = $tierLimits['api_calls'] ?? (int)$tenant['api_calls_limit'];
        $costLimit = $tierLimits['api_cost'] ?? 100; // Default $100 cost limit
        $currentCost = (float)($usage['api_cost'] ?? 0);
        
        $checks = [
            'api_calls' => [
                'used' => (int)$usage['api_calls'],
                'limit' => $apiLimit,
                'exceeded' => (int)$usage['api_calls'] >= $apiLimit,
            ],
            'api_cost' => [
                'used' => $currentCost,
                'limit' => $costLimit,
                'exceeded' => $currentCost >= $costLimit,
            ],
        ];
        
        $anyExceeded = array_filter($checks, fn($c) => $c['exceeded']);
        
        return [
            'valid' => empty($anyExceeded),
            'checks' => $checks,
            'tenant' => [
                'tier' => $tenant['tier'],
                'rate_limit' => (int)$tenant['rate_limit'],
            ],
        ];
    }
    
    /**
     * Add tenant_id column to existing tables
     */
    public function migrateExistingTables(): void
    {
        global $wpdb;
        
        $tables = [
            'sseo_ai_snapshots',
            'sseo_ai_ai_overviews',
            'sseo_ai_content_decay',
            'sseo_ai_position_trends',
        ];
        
        foreach ($tables as $table) {
            $fullTable = $wpdb->prefix . $table;
            
            // Check if table exists first
            $tableExists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $fullTable
            ));
            
            if (!$tableExists) {
                continue; // Skip if table doesn't exist
            }
            
            // Check if tenant_id column exists
            $columnExists = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'tenant_id'",
                $fullTable
            ));
            
            if (empty($columnExists)) {
                $wpdb->query("ALTER TABLE $fullTable ADD COLUMN tenant_id varchar(64) NULL AFTER id");
                $wpdb->query("ALTER TABLE $fullTable ADD KEY tenant_id (tenant_id)");
            }
        }
    }
    
    /**
     * Generate unique tenant key
     */
    private function generateTenantKey(): string
    {
        return 'tn_' . bin2hex(random_bytes(16));
    }
}

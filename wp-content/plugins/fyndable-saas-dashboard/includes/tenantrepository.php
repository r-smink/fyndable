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
    private const GOOGLE_API_USAGE_TABLE = 'sseo_ai_google_api_usage';
    private const SUPPORT_TICKETS_TABLE = 'sseo_ai_support_tickets';
    private const SUPPORT_REPLIES_TABLE = 'sseo_ai_support_replies';
    private const AGENCY_ACCOUNTS_TABLE = 'sseo_ai_agency_accounts';
    
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
            payment_status varchar(20) DEFAULT NULL,
            last_payment_at datetime DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            parent_tenant_id bigint(20) unsigned DEFAULT NULL COMMENT 'Agency tenant ID for sub-tenants',
            PRIMARY KEY (id),
            UNIQUE KEY tenant_key (tenant_key),
            UNIQUE KEY license_key (license_key),
            KEY status (status),
            KEY tier (tier),
            KEY expires_at (expires_at),
            KEY parent_tenant_id (parent_tenant_id)
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
            agency_tenant_id bigint(20) unsigned DEFAULT NULL COMMENT 'Agency tenant that generated this sub-license',
            key_prefix varchar(10) DEFAULT NULL COMMENT 'Custom prefix for agency sub-licenses',
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY status (status),
            KEY license_type (license_type),
            KEY tier (tier),
            KEY created_at (created_at),
            KEY agency_tenant_id (agency_tenant_id)
        ) $charsetCollate;";
        
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);

        // Google API usage tracking (per-tenant, per-service, per-month)
        $sql5 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::GOOGLE_API_USAGE_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            period varchar(7) NOT NULL COMMENT 'YYYY-MM format',
            service varchar(20) NOT NULL COMMENT 'gsc, ga4, ads, oauth',
            api_calls int(11) NOT NULL DEFAULT 0,
            api_cost decimal(10,4) NOT NULL DEFAULT 0.0000,
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_period_service (tenant_id, period, service),
            KEY tenant_id (tenant_id),
            KEY period (period),
            KEY service (service)
        ) $charsetCollate;";
        dbDelta($sql5);

        // Support ticket system
        $sql6 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::SUPPORT_TICKETS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            priority enum('low', 'middle', 'high') NOT NULL DEFAULT 'middle',
            status enum('open', 'reaction', 'closed') NOT NULL DEFAULT 'open',
            screenshots longtext DEFAULT NULL COMMENT 'JSON array of attachment URLs',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY tenant_id (tenant_id),
            KEY status (status),
            KEY priority (priority),
            KEY created_at (created_at)
        ) $charsetCollate;";

        $sql7 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::SUPPORT_REPLIES_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ticket_id bigint(20) unsigned NOT NULL,
            is_staff tinyint(1) NOT NULL DEFAULT 0,
            author_name varchar(255) DEFAULT NULL,
            message longtext NOT NULL,
            screenshots longtext DEFAULT NULL COMMENT 'JSON array of attachment URLs',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ticket_id (ticket_id),
            KEY is_staff (is_staff),
            KEY created_at (created_at)
        ) $charsetCollate;";

        dbDelta($sql6);
        dbDelta($sql7);

        // Agency accounts table (links WP users to agency tenants)
        $sql8 = "CREATE TABLE IF NOT EXISTS {$prefix}" . self::AGENCY_ACCOUNTS_TABLE . " (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL COMMENT 'WordPress user ID with agency_partner role',
            tenant_id bigint(20) unsigned NOT NULL COMMENT 'Agency tenant ID',
            max_sub_licenses int(11) NOT NULL DEFAULT 10 COMMENT 'Maximum sub-licenses this agency can generate',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY tenant_id (tenant_id)
        ) $charsetCollate;";
        dbDelta($sql8);
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
            return new \WP_Error('missing_fields', __('Name and email are required', 'sseo-ai-saas'));
        }
        
        // Check for duplicate tenant key
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE tenant_key = %s",
            $data['tenant_key']
        ));
        
        if ($existing) {
            return new \WP_Error('duplicate_key', __('Tenant key already exists', 'sseo-ai-saas'));
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
            'parent_tenant_id' => !empty($data['parent_tenant_id']) ? (int)$data['parent_tenant_id'] : null,
        ]);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create tenant', 'sseo-ai-saas'));
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
                         'rate_limit', 'api_calls_limit', 'expires_at', 'license_key',
                         'payment_status', 'last_payment_at', 'last_active'];
        
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
            return new \WP_Error('no_data', __('No data to update', 'sseo-ai-saas'));
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
        $result = $this->updateTenant($tenantKey, [
            'status' => 'suspended'
        ]);

        if ($result && $reason) {
            $this->setTenantSetting($tenantKey, 'suspend_reason', $reason);
            $this->setTenantSetting($tenantKey, 'suspended_at', current_time('mysql'));
        }

        return $result;
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

        if (array_key_exists('parent_tenant_id', $filters)) {
            if ($filters['parent_tenant_id'] === null) {
                $where[] = 'parent_tenant_id IS NULL';
            } else {
                $where[] = 'parent_tenant_id = %d';
                $params[] = (int)$filters['parent_tenant_id'];
            }
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
            // Update existing record using prepared statements
            $column = match ($metric) {
                'api_calls' => 'api_calls',
                'api_cost' => 'api_cost',
                'serp_requests' => 'serp_requests',
                'content_generated' => 'content_generated',
                'keywords_tracked' => 'keywords_tracked',
                default => null,
            };
            
            if ($column) {
                if ($column === 'api_cost') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$usageTable} SET {$column} = {$column} + %f WHERE id = %d",
                        $cost,
                        (int)$existing
                    ));
                } else {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$usageTable} SET {$column} = {$column} + %d WHERE id = %d",
                        $count,
                        (int)$existing
                    ));
                }
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
     * Update tenant's monthly API cost in usage table
     */
    public function updateMonthlyCost(int $tenantId, float $cost): void
    {
        global $wpdb;
        $usageTable = $wpdb->prefix . self::TENANT_USAGE_TABLE;
        $period = current_time('Y-m');
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$usageTable} WHERE tenant_id = %d AND period = %s",
            $tenantId,
            $period
        ));
        
        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$usageTable} SET api_cost = api_cost + %f WHERE id = %d",
                $cost,
                $existing
            ));
        } else {
            $wpdb->insert($usageTable, [
                'tenant_id' => $tenantId,
                'period' => $period,
                'api_calls' => 0,
                'api_cost' => $cost,
                'serp_requests' => 0,
                'content_generated' => 0,
                'keywords_tracked' => 0,
            ]);
        }
        
        // Update last_active on the tenant
        $wpdb->update(
            $wpdb->prefix . self::TENANTS_TABLE,
            ['last_active' => current_time('mysql')],
            ['id' => $tenantId]
        );
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
        
        // Add parent_tenant_id to tenants table if missing
        $tenantsTable = $wpdb->prefix . self::TENANTS_TABLE;
        $tenantsExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tenantsTable));
        if ($tenantsExists) {
            $parentCol = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'parent_tenant_id'",
                $tenantsTable
            ));
            if (empty($parentCol)) {
                $wpdb->query("ALTER TABLE $tenantsTable ADD COLUMN parent_tenant_id bigint(20) unsigned DEFAULT NULL COMMENT 'Agency tenant ID for sub-tenants' AFTER metadata");
                $wpdb->query("ALTER TABLE $tenantsTable ADD KEY parent_tenant_id (parent_tenant_id)");
            }
        }
        
        // Add agency_tenant_id and key_prefix to license keys table if missing
        $licenseTable = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        $licenseExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $licenseTable));
        if ($licenseExists) {
            $agencyCol = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'agency_tenant_id'",
                $licenseTable
            ));
            if (empty($agencyCol)) {
                $wpdb->query("ALTER TABLE $licenseTable ADD COLUMN agency_tenant_id bigint(20) unsigned DEFAULT NULL COMMENT 'Agency tenant that generated this sub-license' AFTER revoked_reason");
                $wpdb->query("ALTER TABLE $licenseTable ADD KEY agency_tenant_id (agency_tenant_id)");
            }
            
            $prefixCol = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'key_prefix'",
                $licenseTable
            ));
            if (empty($prefixCol)) {
                $wpdb->query("ALTER TABLE $licenseTable ADD COLUMN key_prefix varchar(10) DEFAULT NULL COMMENT 'Custom prefix for agency sub-licenses' AFTER agency_tenant_id");
            }
        }
        
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

    /**
     * Track Google API usage for a tenant
     */
    public function trackGoogleApiUsage(string $tenantKey, string $service, int $calls = 1, float $cost = 0): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::GOOGLE_API_USAGE_TABLE;

        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) return;

        $period = current_time('Y-m');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE tenant_id = %d AND period = %s AND service = %s",
            $tenant['id'], $period, $service
        ));

        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET api_calls = api_calls + %d, api_cost = api_cost + %f WHERE id = %d",
                $calls, $cost, (int)$existing
            ));
        } else {
            $wpdb->insert($table, [
                'tenant_id' => $tenant['id'],
                'period' => $period,
                'service' => $service,
                'api_calls' => $calls,
                'api_cost' => $cost,
            ]);
        }
    }

    /**
     * Get Google API usage for a tenant in a period
     */
    public function getGoogleApiUsage(string $tenantKey, string $period = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::GOOGLE_API_USAGE_TABLE;

        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) return [];

        if ($period === null) $period = current_time('Y-m');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE tenant_id = %d AND period = %s ORDER BY service",
            $tenant['id'], $period
        ), ARRAY_A);
    }

    /**
     * Get Google API usage for all tenants in a period (for admin overview)
     */
    public function getAllGoogleApiUsage(string $period = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::GOOGLE_API_USAGE_TABLE;
        $tenantsTable = $wpdb->prefix . self::TENANTS_TABLE;

        if ($period === null) $period = current_time('Y-m');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                t.tenant_key, t.name, t.domain, t.tier, t.status,
                g.service, g.api_calls, g.api_cost
            FROM {$table} g
            JOIN {$tenantsTable} t ON g.tenant_id = t.id
            WHERE g.period = %s
            ORDER BY g.api_cost DESC, g.api_calls DESC",
            $period
        ), ARRAY_A);
    }

    /**
     * Get Google API usage summary aggregated by service for a period
     */
    public function getGoogleApiUsageSummary(string $period = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::GOOGLE_API_USAGE_TABLE;

        if ($period === null) $period = current_time('Y-m');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                service,
                SUM(api_calls) as total_calls,
                SUM(api_cost) as total_cost,
                COUNT(DISTINCT tenant_id) as active_tenants
            FROM {$table}
            WHERE period = %s
            GROUP BY service
            ORDER BY total_cost DESC",
            $period
        ), ARRAY_A);
    }

    /**
     * Get Google API usage history for a tenant (multiple months)
     */
    public function getGoogleApiUsageHistory(string $tenantKey, int $months = 6): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::GOOGLE_API_USAGE_TABLE;

        $tenant = $this->getTenant($tenantKey);
        if (!$tenant) return [];

        return $wpdb->get_results($wpdb->prepare(
            "SELECT period, service, api_calls, api_cost
            FROM {$table}
            WHERE tenant_id = %d
            ORDER BY period DESC, service
            LIMIT %d",
            $tenant['id'], $months * 4
        ), ARRAY_A);
    }

    // -------------------------------------------------------------------------
    // Agency account management
    // -------------------------------------------------------------------------

    /**
     * Create an agency account linking a WP user to an agency tenant.
     */
    public function createAgencyAccount(array $data): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::AGENCY_ACCOUNTS_TABLE;

        if (empty($data['user_id']) || empty($data['tenant_id'])) {
            return new \WP_Error('missing_fields', __('user_id and tenant_id are required', 'sseo-ai-saas'));
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d",
            (int)$data['user_id']
        ));

        if ($existing) {
            return new \WP_Error('duplicate', __('Agency account already exists for this user', 'sseo-ai-saas'));
        }

        $result = $wpdb->insert($table, [
            'user_id' => (int)$data['user_id'],
            'tenant_id' => (int)$data['tenant_id'],
            'max_sub_licenses' => (int)($data['max_sub_licenses'] ?? 10),
        ]);

        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to create agency account', 'sseo-ai-saas'));
        }

        return [
            'id' => $wpdb->insert_id,
            'user_id' => (int)$data['user_id'],
            'tenant_id' => (int)$data['tenant_id'],
            'max_sub_licenses' => (int)($data['max_sub_licenses'] ?? 10),
            'success' => true,
        ];
    }

    /**
     * Get agency account by WP user ID.
     */
    public function getAgencyAccount(int $userId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::AGENCY_ACCOUNTS_TABLE;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $userId
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * Get agency account by tenant ID.
     */
    public function getAgencyAccountByTenant(int $tenantId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::AGENCY_ACCOUNTS_TABLE;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE tenant_id = %d",
            $tenantId
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * Update agency account settings.
     */
    public function updateAgencyAccount(int $accountId, array $data): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::AGENCY_ACCOUNTS_TABLE;

        $update = [];
        if (isset($data['max_sub_licenses'])) {
            $update['max_sub_licenses'] = (int)$data['max_sub_licenses'];
        }

        if (empty($update)) {
            return false;
        }

        return $wpdb->update($table, $update, ['id' => $accountId]) !== false;
    }

    /**
     * Get all sub-tenants for an agency tenant.
     */
    public function getSubTenants(int $agencyTenantId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE parent_tenant_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $agencyTenantId, $limit, $offset
        ), ARRAY_A) ?: [];
    }

    /**
     * Count sub-tenants for an agency tenant.
     */
    public function countSubTenants(int $agencyTenantId, array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;

        $where = ['parent_tenant_id = %d'];
        $params = [$agencyTenantId];

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE " . implode(' AND ', $where);

        return (int)$wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * Get sub-tenant IDs for an agency tenant.
     */
    public function getSubTenantIds(int $agencyTenantId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM $table WHERE parent_tenant_id = %d",
            $agencyTenantId
        ), ARRAY_A) ?: [];

        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Get aggregated API usage for all sub-tenants of an agency in a period.
     */
    public function getAgencySubTenantsUsage(int $agencyTenantId, ?string $period = null): array
    {
        global $wpdb;
        $usageTable = $wpdb->prefix . self::TENANT_USAGE_TABLE;
        $tenantsTable = $wpdb->prefix . self::TENANTS_TABLE;

        if ($period === null) {
            $period = current_time('Y-m');
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COALESCE(SUM(u.api_calls), 0) AS total_api_calls,
                COALESCE(SUM(u.api_cost), 0) AS total_api_cost,
                COALESCE(SUM(u.serp_requests), 0) AS total_serp_requests,
                COALESCE(SUM(u.content_generated), 0) AS total_content_generated,
                COALESCE(SUM(u.keywords_tracked), 0) AS total_keywords_tracked
            FROM {$usageTable} u
            INNER JOIN {$tenantsTable} t ON t.id = u.tenant_id
            WHERE t.parent_tenant_id = %d AND u.period = %s",
            $agencyTenantId, $period
        ), ARRAY_A) ?: [
            'total_api_calls' => 0,
            'total_api_cost' => 0,
            'total_serp_requests' => 0,
            'total_content_generated' => 0,
            'total_keywords_tracked' => 0,
        ];
    }

    /**
     * Get tenant by ID.
     */
    public function getTenantById(int $tenantId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TENANTS_TABLE;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $tenantId
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['metadata'] = !empty($row['metadata']) ? json_decode($row['metadata'], true) : [];

        return $row;
    }
}

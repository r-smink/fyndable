<?php

namespace SSEOAISaaS;

/**
 * Self-Hosted License Key Generator
 * 
 * Allows SaaS operators to generate and manage license keys internally
 * without relying on external payment providers like Stripe.
 * Supports: test licenses, free licenses, paid licenses, lifetime licenses, trial licenses
 */
class LicenseKeyGenerator
{
    private const LICENSE_KEYS_TABLE = 'sseo_ai_license_keys';
    
    /**
     * Default rate limits (requests per hour) per tier
     */
    private const TIER_RATE_LIMITS = [
        'starter'      => 60,
        'early_adopters' => 60,
        'trial'        => 200,
        'professional' => 200,
        'business'     => 500,
        'agency'       => 1000,
        'dev'          => 10000,
    ];
    
    /**
     * Default API call limits (per month) per tier
     */
    private const TIER_API_LIMITS = [
        'starter'      => 1000,
        'early_adopters' => 1000,
        'trial'        => 5000,
        'professional' => 10000,
        'business'     => 50000,
        'agency'       => 200000,
        'dev'          => 1000000,
    ];
    
    private TenantRepository $tenants;
    
    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }
    
    /**
     * Get default rate limit for a tier
     */
    public static function getDefaultRateLimit(string $tier): int
    {
        return self::TIER_RATE_LIMITS[$tier] ?? 60;
    }
    
    /**
     * Get default API limit for a tier
     */
    public static function getDefaultApiLimit(string $tier): int
    {
        return self::TIER_API_LIMITS[$tier] ?? 1000;
    }
    
    /**
     * Generate a new license key
     * 
     * @param array $options License options
     *   - tier: trial|starter|early_adopters|professional|business|agency
     *   - type: test|free|paid|lifetime|trial
     *   - max_sites: int
     *   - rate_limit: int (per hour)
     *   - api_calls_limit: int (per month)
     *   - expires_days: int|null (days until expiration from activation, null = never)
     *   - assigned_to: string|null (email or identifier)
     *   - notes: string|null
     * @return array|\WP_Error License key data or error
     */
    public function generateLicense(array $options = []): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        // Generate unique license key
        $keyPrefix = !empty($options['key_prefix']) ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $options['key_prefix'])) : null;
        $licenseKey = $this->generateUniqueKey($keyPrefix);
        
        $licenseType = $options['type'] ?? 'paid';
        $tier = $options['tier'] ?? 'starter';
        
        // Use tier-based defaults for rate limits
        $defaultRateLimit = self::getDefaultRateLimit($tier);
        $defaultApiLimit = self::getDefaultApiLimit($tier);
        
        $data = [
            'license_key' => $licenseKey,
            'license_type' => $licenseType,
            'tier' => $options['tier'] ?? 'starter',
            'status' => 'active',
            'max_sites' => (int)($options['max_sites'] ?? 1),
            'rate_limit' => (int)($options['rate_limit'] ?? $defaultRateLimit),
            'api_calls_limit' => (int)($options['api_calls_limit'] ?? $defaultApiLimit),
            'expires_days' => isset($options['expires_days']) ? (int)$options['expires_days'] : null,
            'created_by' => get_current_user_id(),
            'assigned_to' => !empty($options['assigned_to']) ? sanitize_text_field($options['assigned_to']) : null,
            'notes' => !empty($options['notes']) ? sanitize_textarea_field($options['notes']) : null,
            'created_at' => current_time('mysql'),
            'agency_tenant_id' => !empty($options['agency_tenant_id']) ? (int)$options['agency_tenant_id'] : null,
            'key_prefix' => $keyPrefix,
        ];
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to generate license key', 'sseo-ai-saas'));
        }
        
        return [
            'id' => $wpdb->insert_id,
            'license_key' => $licenseKey,
            'type' => $data['license_type'],
            'tier' => $data['tier'],
            'created_at' => $data['created_at'],
        ];
    }
    
    /**
     * Batch generate multiple license keys
     */
    public function batchGenerateLicenses(int $count, array $options = []): array|\WP_Error
    {
        if ($count < 1 || $count > 100) {
            return new \WP_Error('invalid_count', __('Count must be between 1 and 100', 'sseo-ai-saas'));
        }
        
        $licenses = [];
        $errors = [];
        
        for ($i = 0; $i < $count; $i++) {
            $result = $this->generateLicense($options);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } else {
                $licenses[] = $result;
            }
        }
        
        if (empty($licenses)) {
            return new \WP_Error('batch_failed', __('Failed to generate any licenses', 'sseo-ai-saas'));
        }
        
        return [
            'generated' => count($licenses),
            'failed' => count($errors),
            'licenses' => $licenses,
            'errors' => $errors,
        ];
    }
    
    /**
     * Validate and activate a license key
     * 
     * @param string $licenseKey The license key to activate
     * @param array $activationData Information about the activation
     *   - site_url: string
     *   - site_name: string
     *   - ip_address: string
     * @return array|\WP_Error Activation result
     */
    public function activateLicense(string $licenseKey, array $activationData = []): array|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        // Get license
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return new \WP_Error('invalid_key', __('License key not found', 'sseo-ai-saas'));
        }
        
        // Check status
        if ($license['status'] === 'revoked') {
            return new \WP_Error('revoked', __('This license key has been revoked', 'sseo-ai-saas'));
        }
        
        if ($license['status'] === 'expired') {
            return new \WP_Error('expired', __('This license key has expired', 'sseo-ai-saas'));
        }
        
        // Check if already used and calculate expiration
        $expiresAt = null;
        if (!empty($license['expires_days'])) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$license['expires_days']} days"));
        }
        
        // Check if this is a re-activation of the same site
        $existingTenant = $this->tenants->getTenantByLicense($licenseKey);
        
        if ($existingTenant) {
            // Re-activation - restore to active, update last active and refresh domain/name
            $this->tenants->updateTenant($existingTenant['tenant_key'], [
                'status' => 'active',
                'last_active' => current_time('mysql'),
                'domain' => $activationData['site_url'] ?? $existingTenant['domain'],
                'name' => $activationData['site_name'] ?? $existingTenant['name'],
            ]);
            
            return [
                'success' => true,
                'reactivation' => true,
                'tenant_key' => $existingTenant['tenant_key'],
                'tier' => $existingTenant['tier'],
                'expires_at' => $existingTenant['expires_at'],
                'max_sites' => $existingTenant['max_sites'] ?? 1,
                'rate_limit' => $existingTenant['rate_limit'] ?? 60,
                'api_calls_limit' => $existingTenant['api_calls_limit'] ?? 1000,
            ];
        }
        
        // Mark license as used
        $wpdb->update(
            $table,
            [
                'status' => 'used',
                'activated_at' => current_time('mysql'),
                'expires_at' => $expiresAt,
            ],
            ['id' => $license['id']]
        );
        
        // Create tenant for this license
        $tenantResult = $this->tenants->createTenant([
            'name' => $activationData['site_name'] ?? 'Unknown Site',
            'domain' => $activationData['site_url'] ?? null,
            'email' => $license['assigned_to'] ?: 'unknown@example.com',
            'tier' => $license['tier'],
            'license_key' => $licenseKey,
            'max_sites' => $license['max_sites'],
            'rate_limit' => $license['rate_limit'],
            'api_calls_limit' => $license['api_calls_limit'],
            'expires_at' => $expiresAt,
            'status' => 'active',
            'metadata' => [
                'activated_from' => $activationData['site_url'] ?? 'unknown',
                'ip_address' => $activationData['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'license_type' => $license['license_type'],
            ],
        ]);
        
        if (is_wp_error($tenantResult)) {
            // Rollback license status
            $wpdb->update($table, ['status' => 'active'], ['id' => $license['id']]);
            return $tenantResult;
        }
        
        return [
            'success' => true,
            'tenant_key' => $tenantResult['tenant_key'],
            'tier' => $license['tier'],
            'expires_at' => $expiresAt,
            'max_sites' => $license['max_sites'],
            'rate_limit' => $license['rate_limit'],
            'api_calls_limit' => $license['api_calls_limit'],
        ];
    }
    
    /**
     * Validate a license key without activating
     */
    public function validateLicense(string $licenseKey): array|\WP_Error
    {
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return new \WP_Error('invalid_key', __('License key not found', 'sseo-ai-saas'));
        }
        
        // Check if expired
        if (!empty($license['expires_at']) && strtotime($license['expires_at']) < time()) {
            return new \WP_Error('expired', __('License key has expired', 'sseo-ai-saas'));
        }
        
        // Check if revoked
        if ($license['status'] === 'revoked') {
            return new \WP_Error('revoked', __('License key has been revoked', 'sseo-ai-saas'));
        }
        
        return [
            'valid' => true,
            'status' => $license['status'],
            'tier' => $license['tier'],
            'type' => $license['license_type'],
            'max_sites' => (int)$license['max_sites'],
            'rate_limit' => (int)$license['rate_limit'],
            'api_calls_limit' => (int)$license['api_calls_limit'],
            'expires_at' => $license['expires_at'],
            'assigned_to' => $license['assigned_to'],
        ];
    }
    
    /**
     * Revoke a license key
     */
    public function revokeLicense(string $licenseKey, string $reason = ''): bool|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return new \WP_Error('invalid_key', __('License key not found', 'sseo-ai-saas'));
        }
        
        // Suspend associated tenant
        $tenant = $this->tenants->getTenantByLicense($licenseKey);
        if ($tenant) {
            $this->tenants->suspendTenant($tenant['tenant_key'], $reason);
        }
        
        return $wpdb->update(
            $table,
            [
                'status' => 'revoked',
                'revoked_at' => current_time('mysql'),
                'revoked_reason' => sanitize_textarea_field($reason),
            ],
            ['id' => $license['id']]
        ) !== false;
    }
    
    /**
     * Get license details
     */
    public function getLicense(string $licenseKey): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s",
            $licenseKey
        ), ARRAY_A) ?: null;
    }

    /**
     * Mark license as used (for direct PHP activation)
     */
    public function markLicenseUsed(string $licenseKey): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        return $wpdb->update(
            $table,
            [
                'status' => 'used',
                'activated_at' => current_time('mysql'),
            ],
            ['license_key' => $licenseKey]
        ) !== false;
    }
    
    /**
     * Get all licenses with filtering
     */
    public function getLicenses(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $where[] = 'license_type = %s';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['tier'])) {
            $where[] = 'tier = %s';
            $params[] = $filters['tier'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(license_key LIKE %s OR assigned_to LIKE %s OR notes LIKE %s)';
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if (!empty($filters['created_by'])) {
            $where[] = 'created_by = %d';
            $params[] = (int)$filters['created_by'];
        }

        if (array_key_exists('agency_tenant_id', $filters)) {
            if ($filters['agency_tenant_id'] === null) {
                $where[] = 'agency_tenant_id IS NULL';
            } else {
                $where[] = 'agency_tenant_id = %d';
                $params[] = (int)$filters['agency_tenant_id'];
            }
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT * FROM $table WHERE $whereClause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];
    }
    
    /**
     * Count licenses
     */
    public function countLicenses(array $filters = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $where[] = 'license_type = %s';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['tier'])) {
            $where[] = 'tier = %s';
            $params[] = $filters['tier'];
        }
        
        $whereClause = implode(' AND ', $where);
        
        if (empty($params)) {
            return (int)$wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $whereClause");
        }
        
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE $whereClause",
            $params
        ));
    }
    
    /**
     * Update license notes/assignment
     */
    public function updateLicense(string $licenseKey, array $data): bool|\WP_Error
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        $license = $this->getLicense($licenseKey);
        if (!$license) {
            return new \WP_Error('invalid_key', __('License key not found', 'sseo-ai-saas'));
        }
        
        $allowed = ['assigned_to', 'notes', 'max_sites', 'rate_limit', 'api_calls_limit'];
        $update = [];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }
        
        if (empty($update)) {
            return new \WP_Error('no_data', __('No valid fields to update', 'sseo-ai-saas'));
        }
        
        return $wpdb->update($table, $update, ['id' => $license['id']]) !== false;
    }
    
    /**
     * Get license statistics
     */
    public function getLicenseStats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        return [
            'total' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'by_status' => $wpdb->get_results("SELECT status, COUNT(*) as count FROM $table GROUP BY status", ARRAY_A),
            'by_type' => $wpdb->get_results("SELECT license_type, COUNT(*) as count FROM $table GROUP BY license_type", ARRAY_A),
            'by_tier' => $wpdb->get_results("SELECT tier, COUNT(*) as count FROM $table GROUP BY tier", ARRAY_A),
            'created_today' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            )),
            'created_this_month' => (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE DATE_FORMAT(created_at, '%Y-%m') = %s",
                current_time('Y-m')
            )),
        ];
    }
    
    /**
     * Export licenses to CSV
     */
    public function exportLicenses(array $filters = []): string
    {
        $licenses = $this->getLicenses($filters, 10000, 0);
        
        $output = fopen('php://temp', 'r+');
        
        // Header
        fputcsv($output, ['License Key', 'Type', 'Tier', 'Status', 'Max Sites', 'Rate Limit', 'API Limit', 
                         'Assigned To', 'Created At', 'Activated At', 'Expires At']);
        
        foreach ($licenses as $license) {
            fputcsv($output, [
                $license['license_key'],
                $license['license_type'],
                $license['tier'],
                $license['status'],
                $license['max_sites'],
                $license['rate_limit'],
                $license['api_calls_limit'],
                $license['assigned_to'],
                $license['created_at'],
                $license['activated_at'],
                $license['expires_at'],
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Generate unique license key
     */
    private function generateUniqueKey(?string $prefix = null): string
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;
        
        $keyPrefix = $prefix ? $prefix . '-AI' : 'FYN-SSAI';
        
        $attempts = 0;
        do {
            // Format: {PREFIX}-AI-XXXX-XXXX-XXXX
            $parts = [];
            for ($i = 0; $i < 3; $i++) {
                $parts[] = strtoupper(bin2hex(random_bytes(4)));
            }
            $key = $keyPrefix . '-' . implode('-', $parts);
            
            // Check uniqueness
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE license_key = %s",
                $key
            ));
            
            $attempts++;
        } while ($exists && $attempts < 10);
        
        if ($exists) {
            throw new \Exception('Failed to generate unique license key');
        }
        
        return $key;
    }

    /**
     * Get all licenses generated by a specific agency.
     */
    public function getLicensesByAgency(int $agencyTenantId, int $limit = 50, int $offset = 0): array
    {
        return $this->getLicenses(['agency_tenant_id' => $agencyTenantId], $limit, $offset);
    }

    /**
     * Count licenses generated by a specific agency.
     */
    public function countLicensesByAgency(int $agencyTenantId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . self::LICENSE_KEYS_TABLE;

        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE agency_tenant_id = %d",
            $agencyTenantId
        ));
    }
}

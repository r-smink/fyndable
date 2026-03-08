<?php
/**
 * Shared Configuration for AI SEO SaaS
 * 
 * This file defines constants shared between:
 * - ai-seo-saas-dashboard (SaaS Dashboard plugin)
 * - ai-seo-client (Client plugin)
 * 
 * Use this to keep API versions, table names, and key formats in sync.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// API Version (must match between plugins)
if (!defined('AISEO_SAAS_API_VERSION')) {
    define('AISEO_SAAS_API_VERSION', 'v1');
}

// API Namespace
if (!defined('AISEO_SAAS_API_NAMESPACE')) {
    define('AISEO_SAAS_API_NAMESPACE', 'ai-seo-saas/' . AISEO_SAAS_API_VERSION);
}

// License Key Format
if (!defined('AISEO_LICENSE_KEY_PATTERN')) {
    define('AISEO_LICENSE_KEY_PATTERN', '/^AISEO-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/');
}

// Database Table Names (without prefix)
if (!defined('AISEO_TABLE_TENANTS')) {
    define('AISEO_TABLE_TENANTS', 'aiseo_tenants');
}
if (!defined('AISEO_TABLE_TENANT_SETTINGS')) {
    define('AISEO_TABLE_TENANT_SETTINGS', 'aiseo_tenant_settings');
}
if (!defined('AISEO_TABLE_TENANT_USAGE')) {
    define('AISEO_TABLE_TENANT_USAGE', 'aiseo_tenant_usage');
}
if (!defined('AISEO_TABLE_LICENSE_KEYS')) {
    define('AISEO_TABLE_LICENSE_KEYS', 'aiseo_license_keys');
}

// License Types
if (!defined('AISEO_LICENSE_TYPES')) {
    define('AISEO_LICENSE_TYPES', ['test', 'free', 'trial', 'paid', 'lifetime']);
}

// License Tiers
if (!defined('AISEO_LICENSE_TIERS')) {
    define('AISEO_LICENSE_TIERS', ['free', 'trial', 'starter', 'professional', 'business', 'agency']);
}

// Tenant Status
if (!defined('AISEO_TENANT_STATUS')) {
    define('AISEO_TENANT_STATUS', ['active', 'suspended', 'cancelled']);
}

// License Status
if (!defined('AISEO_LICENSE_STATUS')) {
    define('AISEO_LICENSE_STATUS', ['active', 'used', 'revoked', 'expired']);
}

// Usage Metrics
if (!defined('AISEO_USAGE_METRICS')) {
    define('AISEO_USAGE_METRICS', [
        'api_calls',
        'api_cost',
        'serp_requests',
        'content_generated',
        'keywords_tracked'
    ]);
}

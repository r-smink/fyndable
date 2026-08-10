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
if (!defined('SSEO_AI_SAAS_API_VERSION')) {
    define('SSEO_AI_SAAS_API_VERSION', 'v1');
}

// API Namespace
if (!defined('SSEO_AI_SAAS_API_NAMESPACE')) {
    define('SSEO_AI_SAAS_API_NAMESPACE', 'ai-seo-saas/' . SSEO_AI_SAAS_API_VERSION);
}

// License Key Format
if (!defined('SSEO_AI_LICENSE_KEY_PATTERN')) {
    define('SSEO_AI_LICENSE_KEY_PATTERN', '/^FYN-SSAI-[A-Z0-9]{8}-[A-Z0-9]{8}-[A-Z0-9]{8}$/');
}

// Database Table Names (without prefix)
if (!defined('SSEO_AI_TABLE_TENANTS')) {
    define('SSEO_AI_TABLE_TENANTS', 'sseo_ai_tenants');
}
if (!defined('SSEO_AI_TABLE_TENANT_SETTINGS')) {
    define('SSEO_AI_TABLE_TENANT_SETTINGS', 'sseo_ai_tenant_settings');
}
if (!defined('SSEO_AI_TABLE_TENANT_USAGE')) {
    define('SSEO_AI_TABLE_TENANT_USAGE', 'sseo_ai_tenant_usage');
}
if (!defined('SSEO_AI_TABLE_LICENSE_KEYS')) {
    define('SSEO_AI_TABLE_LICENSE_KEYS', 'sseo_ai_license_keys');
}

// License Types
if (!defined('SSEO_AI_LICENSE_TYPES')) {
    define('SSEO_AI_LICENSE_TYPES', ['test', 'free', 'trial', 'paid', 'lifetime']);
}

// License Tiers
if (!defined('SSEO_AI_LICENSE_TIERS')) {
    define('SSEO_AI_LICENSE_TIERS', ['free', 'trial', 'starter', 'professional', 'business', 'agency']);
}

// Tenant Status
if (!defined('SSEO_AI_TENANT_STATUS')) {
    define('SSEO_AI_TENANT_STATUS', ['active', 'inactive', 'suspended', 'cancelled']);
}

// License Status
if (!defined('SSEO_AI_LICENSE_STATUS')) {
    define('SSEO_AI_LICENSE_STATUS', ['active', 'used', 'revoked', 'expired']);
}

// Usage Metrics
if (!defined('SSEO_AI_USAGE_METRICS')) {
    define('SSEO_AI_USAGE_METRICS', [
        'api_calls',
        'api_cost',
        'serp_requests',
        'content_generated',
        'keywords_tracked'
    ]);
}

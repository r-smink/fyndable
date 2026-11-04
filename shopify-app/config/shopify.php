<?php

return [
    'app_name' => env('SHOPIFY_APP_NAME', 'Fyndable SEO'),
    'api_key' => env('SHOPIFY_API_KEY', ''),
    'api_secret' => env('SHOPIFY_API_SECRET', ''),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_content,write_content,read_themes,read_metaobjects,write_metaobjects,write_app_proxy,read_markets,read_locales,read_translations'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI', '/auth/callback'),
    'webhook_uri' => env('SHOPIFY_WEBHOOK_URI', '/webhooks'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'is_embedded' => env('SHOPIFY_IS_EMBEDDED', true),
    'billing_enabled' => env('SHOPIFY_BILLING_ENABLED', false),

    // Shopify App Pricing. Plans live in the Partner Dashboard; the app reads
    // the active subscription via the Partner API (no billing webhooks).
    'billing' => [
        'partner_id' => env('SHOPIFY_PARTNER_ID', ''),
        'partner_api_token' => env('SHOPIFY_PARTNER_API_TOKEN', ''),
        'partner_api_version' => env('SHOPIFY_PARTNER_API_VERSION', '2026-10'),
        'app_id' => env('SHOPIFY_APP_ID', ''),
        'app_handle' => env('SHOPIFY_APP_HANDLE', 'fyndable-smart-seo'),

        // Partner Dashboard plan_handle => Fyndable tier (config/tiers.php).
        'plans' => [
            'starter' => 'starter',
            'professional' => 'professional',
            'business' => 'business',
        ],

        // Seconds a Partner API result is reused on app load (rate limit is
        // 4 requests/second for the whole Partner organization).
        'check_ttl' => (int) env('SHOPIFY_BILLING_CHECK_TTL', 600),

        // In-app trial: AI optimization of this many distinct products, then
        // a paid plan is required. No Shopify-side trial days.
        'trial_products' => (int) env('SHOPIFY_TRIAL_PRODUCTS', 5),

        // Manually entered license keys are only accepted for these tiers.
        'manual_license_tiers' => ['agency', 'dev'],

        // Shared secret for the signed sync to the SaaS dashboard.
        'sync_secret' => env('FYNDABLE_SHOPIFY_SYNC_SECRET', ''),
    ],

    // Fyndable SaaS dashboard connection
    'saas_dashboard_url' => env('SAAS_DASHBOARD_URL', 'https://portal.fyndable.ai'),
    'saas_api_namespace' => env('SAAS_API_NAMESPACE', 'ai-seo-saas/v1'),
];

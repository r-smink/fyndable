<?php

return [
    'app_name' => env('SHOPIFY_APP_NAME', 'Fyndable SEO'),
    'api_key' => env('SHOPIFY_API_KEY', ''),
    'api_secret' => env('SHOPIFY_API_SECRET', ''),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_content,write_content,read_themes,read_metaobjects,write_metaobjects'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI', '/auth/callback'),
    'webhook_uri' => env('SHOPIFY_WEBHOOK_URI', '/webhooks'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-07'),
    'is_embedded' => env('SHOPIFY_IS_EMBEDDED', true),
    'billing_enabled' => env('SHOPIFY_BILLING_ENABLED', false),

    // Fyndable SaaS dashboard connection
    'saas_dashboard_url' => env('SAAS_DASHBOARD_URL', 'https://portal.fyndable.ai'),
    'saas_api_namespace' => env('SAAS_API_NAMESPACE', 'ai-seo-saas/v1'),
];

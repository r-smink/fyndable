<?php

/*
|--------------------------------------------------------------------------
| Tier gating for the Shopify app
|--------------------------------------------------------------------------
|
| `levels`   — tier rank; a shop may use a feature when its rank >= the
|              feature's minimum tier. No/invalid license counts as 'free'.
|              Trial is ranked as Professional.
| `features` — minimum tier per feature (applied via the shopify.tier
|              route middleware).
| `limits`   — monthly Shopify-only usage caps, counted locally in
|              shop_usage. null = unlimited, 0 = not available.
|
| AI/API calls per month are enforced by the SaaS dashboard
| (SaaSSettings::shopifyApiLimit, option ai_seo_saas_shopify_{tier}_api_calls).
|
*/

return [
    'levels' => [
        'free' => 0,
        'starter' => 1,
        'early_adopters' => 1,
        'professional' => 2,
        'trial' => 2,
        'business' => 3,
        'agency' => 4,
        'dev' => 5,
    ],

    'features' => [
        'llmstxt' => 'starter',
        'indexnow' => 'starter',
        'content_meta' => 'starter',
        'content_description' => 'starter',
        'alt_text' => 'starter',
        'schema' => 'starter',
        'faq' => 'professional',
        'link_suggestions' => 'professional',
        'ai_image' => 'professional',
        'variant_descriptions' => 'professional',
        'rank_tracker' => 'professional',
        'keyword_explorer' => 'professional',
        'competitor' => 'professional',
        'geo_scan' => 'professional',
        'ai_tracker' => 'professional',
        'backlinks' => 'professional',
        'llm_insights' => 'professional',
        'keyword_data' => 'professional',
        'markets' => 'professional',
        'blog_writer' => 'business',
        'content_calendar' => 'business',
        'bulk_optimizer' => 'business',
    ],

    'limits' => [
        'geo_scans' => [
            'free' => 0,
            'starter' => 0,
            'early_adopters' => 0,
            'professional' => 10,
            'trial' => 10,
            'business' => 30,
            'agency' => null,
            'dev' => null,
        ],
        'blog_articles' => [
            'free' => 0,
            'starter' => 0,
            'early_adopters' => 0,
            'professional' => 0,
            'trial' => 0,
            'business' => 50,
            'agency' => null,
            'dev' => null,
        ],
    ],
];

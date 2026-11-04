<?php

use App\Http\Controllers\AiTrackerController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BlogWriterController;
use App\Http\Controllers\BulkOptimizerController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\ContentCalendarController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeoScanController;
use App\Http\Controllers\IndexNowController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\KeywordExplorerController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\MarketsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RankTrackerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes require the Shopify session middleware, which resolves the
| shop from the `shop` query parameter or the session JWT token.
|
*/

// App Proxy root: Shopify proxies all /apps/fyndable/* requests here.
// Use ?full=1 to get /llms-full.txt, otherwise /llms.txt is served.
Route::get('/', [LlmsTxtController::class, 'proxy']);

// IndexNow key file via app proxy (shop resolved from the ?shop= proxy param).
Route::get('/indexnow-key.txt', [IndexNowController::class, 'keyFile']);

Route::middleware(['shopify.session'])->group(function () {

    // Dashboard
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);

    // Billing (Shopify App Pricing)
    Route::get('/billing/status', [BillingController::class, 'status']);
    Route::post('/billing/confirm', [BillingController::class, 'confirm']);

    // Onboarding wizard (store profile + first products)
    Route::get('/onboarding', [OnboardingController::class, 'show']);
    Route::post('/onboarding/profile', [OnboardingController::class, 'saveProfile']);
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip']);

    // License
    Route::get('/license/status', [LicenseController::class, 'status']);
    Route::post('/license/activate', [LicenseController::class, 'activate']);
    Route::post('/license/deactivate', [LicenseController::class, 'deactivate']);

    // llms.txt
    Route::get('/llmstxt/status', [LlmsTxtController::class, 'status'])->middleware('shopify.tier:llmstxt');
    Route::post('/llmstxt/settings', [LlmsTxtController::class, 'updateSettings'])->middleware('shopify.tier:llmstxt');
    Route::post('/llmstxt/regenerate', [LlmsTxtController::class, 'regenerate'])->middleware('shopify.tier:llmstxt');
    Route::post('/llmstxt/setup-redirects', [LlmsTxtController::class, 'setupRedirects'])->middleware('shopify.tier:llmstxt')->middleware('shopify.scope:write_content');
    Route::get('/llmstxt/preview', [LlmsTxtController::class, 'preview'])->middleware('shopify.tier:llmstxt');

    // Content (products, collections, pages, articles) — generic surface.
    // Products are delegated to ProductController internally.
    Route::get('/content/{type}', [ContentController::class, 'index'])
        ->whereIn('type', ['product', 'collection', 'page', 'article']);
    Route::get('/content/{type}/{id}', [ContentController::class, 'show'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])
        ->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-meta', [ContentController::class, 'generateMeta'])->middleware('shopify.tier:content_meta')->middleware('shopify.trial')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-description', [ContentController::class, 'generateDescription'])->middleware('shopify.tier:content_description')->middleware('shopify.trial')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-schema', [ContentController::class, 'generateSchema'])->middleware('shopify.tier:schema')->middleware('shopify.trial')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');

    // Content writes check the matching Shopify scope per type inside the
    // controller (product → write_products, others → write_content).
    Route::post('/content/{type}/{id}/save-meta', [ContentController::class, 'saveMeta'])->middleware('shopify.tier:content_meta')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/save-description', [ContentController::class, 'saveDescription'])->middleware('shopify.tier:content_description')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/save-schema', [ContentController::class, 'saveSchema'])->middleware('shopify.tier:schema')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-faq', [ContentController::class, 'generateFaq'])->middleware('shopify.tier:faq')->middleware('shopify.trial')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/save-faq', [ContentController::class, 'saveFaq'])->middleware('shopify.tier:faq')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/link-suggestions', [ContentController::class, 'linkSuggestions'])->middleware('shopify.tier:link_suggestions')->middleware('shopify.trial')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/apply-link', [ContentController::class, 'applyLink'])->middleware('shopify.tier:link_suggestions')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-image', [ContentController::class, 'generateImage'])->middleware('shopify.tier:ai_image')->middleware('shopify.trial')
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');

    // Blog writer
    Route::get('/blogs', [BlogWriterController::class, 'blogs'])->middleware('shopify.tier:blog_writer');
    Route::post('/articles/generate', [BlogWriterController::class, 'generate'])->middleware('shopify.tier:blog_writer');
    Route::post('/blogs/{blogId}/articles', [BlogWriterController::class, 'create'])->middleware('shopify.tier:blog_writer')->where('blogId', '.+');

    // Insights: backlinks, LLM visibility, keyword data
    Route::get('/insights/backlinks', [InsightsController::class, 'backlinks'])->middleware('shopify.tier:backlinks');
    Route::post('/insights/llm-mentions', [InsightsController::class, 'llmMentions'])->middleware('shopify.tier:llm_insights');
    Route::post('/insights/llm-check', [InsightsController::class, 'llmCheck'])->middleware('shopify.tier:llm_insights');
    Route::post('/insights/keyword-data', [InsightsController::class, 'keywordData'])->middleware('shopify.tier:keyword_data');

    // IndexNow
    Route::get('/indexnow/status', [IndexNowController::class, 'status'])->middleware('shopify.tier:indexnow');
    Route::post('/indexnow/setup', [IndexNowController::class, 'setup'])->middleware('shopify.tier:indexnow')->middleware('shopify.scope:write_content');
    Route::post('/indexnow/submit', [IndexNowController::class, 'submit'])->middleware('shopify.tier:indexnow');

    // Products
    Route::post('/products/{productId}/generate-description', [ProductController::class, 'generateDescription'])->middleware('shopify.tier:content_description')->middleware('shopify.trial');
    Route::post('/products/{productId}/save-description', [ProductController::class, 'saveDescription'])->middleware('shopify.tier:content_description')->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/generate-meta', [ProductController::class, 'generateMeta'])->middleware('shopify.tier:content_meta')->middleware('shopify.trial');
    Route::post('/products/{productId}/generate-alt-text', [ProductController::class, 'generateAltText'])->middleware('shopify.tier:alt_text')->middleware('shopify.trial');
    Route::post('/products/{productId}/save-meta', [ProductController::class, 'saveMeta'])->middleware('shopify.tier:content_meta')->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/generate-schema', [ProductController::class, 'generateSchema'])->middleware('shopify.tier:schema')->middleware('shopify.trial');
    Route::post('/products/{productId}/save-schema', [ProductController::class, 'saveSchema'])->middleware('shopify.tier:schema')->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-description', [ProductController::class, 'pushDescription'])->middleware('shopify.tier:content_description')->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-title', [ProductController::class, 'pushTitle'])->middleware('shopify.tier:content_meta')->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-alt-text', [ProductController::class, 'pushAltText'])->middleware('shopify.tier:alt_text')->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-all', [ProductController::class, 'pushAll'])->middleware('shopify.tier:content_meta')->middleware('shopify.scope:write_products');

    // Bulk optimizer
    Route::post('/bulk/optimize', [BulkOptimizerController::class, 'optimize'])->middleware('shopify.tier:bulk_optimizer')->middleware('shopify.scope:write_products');
    Route::get('/bulk/progress', [BulkOptimizerController::class, 'progress'])->middleware('shopify.tier:bulk_optimizer');
    Route::post('/bulk/cancel', [BulkOptimizerController::class, 'cancel'])->middleware('shopify.tier:bulk_optimizer');

    // Rank tracker
    Route::get('/rank-tracker/keywords', [RankTrackerController::class, 'listKeywords'])->middleware('shopify.tier:rank_tracker');
    Route::post('/rank-tracker/keywords', [RankTrackerController::class, 'addKeyword'])->middleware('shopify.tier:rank_tracker');
    Route::delete('/rank-tracker/keywords/{id}', [RankTrackerController::class, 'deleteKeyword'])->middleware('shopify.tier:rank_tracker');
    Route::post('/rank-tracker/check', [RankTrackerController::class, 'checkRankings'])->middleware('shopify.tier:rank_tracker');
    Route::get('/rank-tracker/keywords/{id}/history', [RankTrackerController::class, 'keywordHistory'])->middleware('shopify.tier:rank_tracker');
    Route::get('/rank-tracker/stats', [RankTrackerController::class, 'stats'])->middleware('shopify.tier:rank_tracker');

    // GEO Scan (AI Visibility)
    Route::post('/geo-scan/start', [GeoScanController::class, 'start'])->middleware('shopify.tier:geo_scan');
    Route::get('/geo-scan/{id}/status', [GeoScanController::class, 'status'])->middleware('shopify.tier:geo_scan')->where('id', '[0-9]+');
    Route::get('/geo-scan/recent', [GeoScanController::class, 'recent'])->middleware('shopify.tier:geo_scan');

    // Keyword Explorer
    Route::post('/keywords/explore', [KeywordExplorerController::class, 'explore'])->middleware('shopify.tier:keyword_explorer');
    Route::post('/keywords/volume', [KeywordExplorerController::class, 'volume'])->middleware('shopify.tier:keyword_explorer');
    Route::post('/keywords/cluster', [KeywordExplorerController::class, 'cluster'])->middleware('shopify.tier:keyword_explorer');
    Route::post('/keywords/trends', [KeywordExplorerController::class, 'trends'])->middleware('shopify.tier:keyword_explorer');

    // SERP Competitor Analysis
    Route::post('/competitor/analyze', [CompetitorController::class, 'analyze'])->middleware('shopify.tier:competitor');
    Route::post('/competitor/compare', [CompetitorController::class, 'compare'])->middleware('shopify.tier:competitor');

    // AI Optimization Tracker
    Route::get('/ai-tracker/overview', [AiTrackerController::class, 'overview'])->middleware('shopify.tier:ai_tracker');
    Route::get('/ai-tracker/timeseries', [AiTrackerController::class, 'timeseries'])->middleware('shopify.tier:ai_tracker');
    Route::post('/ai-tracker/check', [AiTrackerController::class, 'check'])->middleware('shopify.tier:ai_tracker');
    Route::get('/ai-tracker/recommendations', [AiTrackerController::class, 'recommendations'])->middleware('shopify.tier:ai_tracker');

    // Content Calendar
    Route::get('/calendar/articles', [ContentCalendarController::class, 'index'])->middleware('shopify.tier:content_calendar');
    Route::post('/calendar/schedule', [ContentCalendarController::class, 'schedule'])->middleware('shopify.tier:content_calendar');
    Route::put('/calendar/{id}', [ContentCalendarController::class, 'update'])->middleware('shopify.tier:content_calendar')->where('id', '[0-9]+');
    Route::delete('/calendar/{id}', [ContentCalendarController::class, 'destroy'])->middleware('shopify.tier:content_calendar')->where('id', '[0-9]+');
    Route::get('/calendar/published', [ContentCalendarController::class, 'published'])->middleware('shopify.tier:content_calendar');

    // Product Variant SEO
    Route::get('/products/{productId}/variants', [ProductController::class, 'variants'])->middleware('shopify.tier:variant_descriptions');
    Route::post('/products/{productId}/variants/{variantId}/generate-description', [ProductController::class, 'generateVariantDescription'])->middleware('shopify.tier:variant_descriptions')->middleware('shopify.trial');
    Route::post('/products/{productId}/variants/{variantId}/save-description', [ProductController::class, 'saveVariantDescription'])->middleware('shopify.tier:variant_descriptions')->middleware('shopify.scope:write_products');

    // Product Review Schema
    Route::get('/products/{productId}/review-schema', [ProductController::class, 'reviewSchema'])->middleware('shopify.tier:schema');

    // Shopify Markets SEO Check
    Route::get('/markets/overview', [MarketsController::class, 'overview'])->middleware('shopify.tier:markets');
    Route::get('/markets/audit', [MarketsController::class, 'audit'])->middleware('shopify.tier:markets');
    Route::get('/markets/{marketId}/content-status', [MarketsController::class, 'contentStatus'])->middleware('shopify.tier:markets')->where('marketId', '.+');
});

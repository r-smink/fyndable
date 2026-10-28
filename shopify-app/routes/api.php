<?php

use App\Http\Controllers\AiTrackerController;
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

    // License
    Route::get('/license/status', [LicenseController::class, 'status']);
    Route::post('/license/activate', [LicenseController::class, 'activate']);
    Route::post('/license/deactivate', [LicenseController::class, 'deactivate']);

    // llms.txt
    Route::get('/llmstxt/status', [LlmsTxtController::class, 'status']);
    Route::post('/llmstxt/settings', [LlmsTxtController::class, 'updateSettings']);
    Route::post('/llmstxt/regenerate', [LlmsTxtController::class, 'regenerate']);
    Route::post('/llmstxt/setup-redirects', [LlmsTxtController::class, 'setupRedirects'])->middleware('shopify.scope:write_content');
    Route::get('/llmstxt/preview', [LlmsTxtController::class, 'preview']);

    // Content (products, collections, pages, articles) — generic surface.
    // Products are delegated to ProductController internally.
    Route::get('/content/{type}', [ContentController::class, 'index'])
        ->whereIn('type', ['product', 'collection', 'page', 'article']);
    Route::get('/content/{type}/{id}', [ContentController::class, 'show'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])
        ->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-meta', [ContentController::class, 'generateMeta'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-description', [ContentController::class, 'generateDescription'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-schema', [ContentController::class, 'generateSchema'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');

    // Content writes check the matching Shopify scope per type inside the
    // controller (product → write_products, others → write_content).
    Route::post('/content/{type}/{id}/save-meta', [ContentController::class, 'saveMeta'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/save-description', [ContentController::class, 'saveDescription'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/save-schema', [ContentController::class, 'saveSchema'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-faq', [ContentController::class, 'generateFaq'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/save-faq', [ContentController::class, 'saveFaq'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/link-suggestions', [ContentController::class, 'linkSuggestions'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/apply-link', [ContentController::class, 'applyLink'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');
    Route::post('/content/{type}/{id}/generate-image', [ContentController::class, 'generateImage'])
        ->whereIn('type', ['product', 'collection', 'page', 'article'])->where('id', '.+');

    // Blog writer
    Route::get('/blogs', [BlogWriterController::class, 'blogs']);
    Route::post('/articles/generate', [BlogWriterController::class, 'generate']);
    Route::post('/blogs/{blogId}/articles', [BlogWriterController::class, 'create'])->where('blogId', '.+');

    // Insights: backlinks, LLM visibility, keyword data
    Route::get('/insights/backlinks', [InsightsController::class, 'backlinks']);
    Route::post('/insights/llm-mentions', [InsightsController::class, 'llmMentions']);
    Route::post('/insights/llm-check', [InsightsController::class, 'llmCheck']);
    Route::post('/insights/keyword-data', [InsightsController::class, 'keywordData']);

    // IndexNow
    Route::get('/indexnow/status', [IndexNowController::class, 'status']);
    Route::post('/indexnow/setup', [IndexNowController::class, 'setup'])->middleware('shopify.scope:write_content');
    Route::post('/indexnow/submit', [IndexNowController::class, 'submit']);

    // Products
    Route::post('/products/{productId}/generate-description', [ProductController::class, 'generateDescription']);
    Route::post('/products/{productId}/save-description', [ProductController::class, 'saveDescription'])->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/generate-meta', [ProductController::class, 'generateMeta']);
    Route::post('/products/{productId}/generate-alt-text', [ProductController::class, 'generateAltText']);
    Route::post('/products/{productId}/save-meta', [ProductController::class, 'saveMeta'])->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/generate-schema', [ProductController::class, 'generateSchema']);
    Route::post('/products/{productId}/save-schema', [ProductController::class, 'saveSchema'])->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-description', [ProductController::class, 'pushDescription'])->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-title', [ProductController::class, 'pushTitle'])->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-alt-text', [ProductController::class, 'pushAltText'])->middleware('shopify.scope:write_products');
    Route::post('/products/{productId}/push-all', [ProductController::class, 'pushAll'])->middleware('shopify.scope:write_products');

    // Bulk optimizer
    Route::post('/bulk/optimize', [BulkOptimizerController::class, 'optimize'])->middleware('shopify.scope:write_products');
    Route::get('/bulk/progress', [BulkOptimizerController::class, 'progress']);
    Route::post('/bulk/cancel', [BulkOptimizerController::class, 'cancel']);

    // Rank tracker
    Route::get('/rank-tracker/keywords', [RankTrackerController::class, 'listKeywords']);
    Route::post('/rank-tracker/keywords', [RankTrackerController::class, 'addKeyword']);
    Route::delete('/rank-tracker/keywords/{id}', [RankTrackerController::class, 'deleteKeyword']);
    Route::post('/rank-tracker/check', [RankTrackerController::class, 'checkRankings']);
    Route::get('/rank-tracker/keywords/{id}/history', [RankTrackerController::class, 'keywordHistory']);
    Route::get('/rank-tracker/stats', [RankTrackerController::class, 'stats']);

    // GEO Scan (AI Visibility)
    Route::post('/geo-scan/start', [GeoScanController::class, 'start']);
    Route::get('/geo-scan/{id}/status', [GeoScanController::class, 'status'])->where('id', '[0-9]+');
    Route::get('/geo-scan/recent', [GeoScanController::class, 'recent']);

    // Keyword Explorer
    Route::post('/keywords/explore', [KeywordExplorerController::class, 'explore']);
    Route::post('/keywords/volume', [KeywordExplorerController::class, 'volume']);
    Route::post('/keywords/cluster', [KeywordExplorerController::class, 'cluster']);
    Route::post('/keywords/trends', [KeywordExplorerController::class, 'trends']);

    // SERP Competitor Analysis
    Route::post('/competitor/analyze', [CompetitorController::class, 'analyze']);
    Route::post('/competitor/compare', [CompetitorController::class, 'compare']);

    // AI Optimization Tracker
    Route::get('/ai-tracker/overview', [AiTrackerController::class, 'overview']);
    Route::get('/ai-tracker/timeseries', [AiTrackerController::class, 'timeseries']);
    Route::post('/ai-tracker/check', [AiTrackerController::class, 'check']);
    Route::get('/ai-tracker/recommendations', [AiTrackerController::class, 'recommendations']);

    // Content Calendar
    Route::get('/calendar/articles', [ContentCalendarController::class, 'index']);
    Route::post('/calendar/schedule', [ContentCalendarController::class, 'schedule']);
    Route::put('/calendar/{id}', [ContentCalendarController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/calendar/{id}', [ContentCalendarController::class, 'destroy'])->where('id', '[0-9]+');
    Route::get('/calendar/published', [ContentCalendarController::class, 'published']);

    // Product Variant SEO
    Route::get('/products/{productId}/variants', [ProductController::class, 'variants']);
    Route::post('/products/{productId}/variants/{variantId}/generate-description', [ProductController::class, 'generateVariantDescription']);
    Route::post('/products/{productId}/variants/{variantId}/save-description', [ProductController::class, 'saveVariantDescription'])->middleware('shopify.scope:write_products');

    // Product Review Schema
    Route::get('/products/{productId}/review-schema', [ProductController::class, 'reviewSchema']);

    // Shopify Markets SEO Check
    Route::get('/markets/overview', [MarketsController::class, 'overview']);
    Route::get('/markets/audit', [MarketsController::class, 'audit']);
    Route::get('/markets/{marketId}/content-status', [MarketsController::class, 'contentStatus'])->where('marketId', '.+');
});

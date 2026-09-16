<?php

use App\Http\Controllers\BulkOptimizerController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LlmsTxtController;
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
});

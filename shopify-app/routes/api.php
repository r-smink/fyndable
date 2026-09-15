<?php

use App\Http\Controllers\BulkOptimizerController;
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
    Route::get('/llmstxt/preview', [LlmsTxtController::class, 'preview']);

    // Products
    Route::post('/products/{productId}/generate-description', [ProductController::class, 'generateDescription']);
    Route::post('/products/{productId}/save-description', [ProductController::class, 'saveDescription']);
    Route::post('/products/{productId}/generate-meta', [ProductController::class, 'generateMeta']);
    Route::post('/products/{productId}/generate-alt-text', [ProductController::class, 'generateAltText']);
    Route::post('/products/{productId}/save-meta', [ProductController::class, 'saveMeta']);
    Route::post('/products/{productId}/generate-schema', [ProductController::class, 'generateSchema']);

    // Bulk optimizer
    Route::post('/bulk/optimize', [BulkOptimizerController::class, 'optimize']);
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

// llms.txt serving (public, no session required — shop resolved via query param)
Route::get('/llms.txt', [LlmsTxtController::class, 'summary']);
Route::get('/llms-full.txt', [LlmsTxtController::class, 'full']);

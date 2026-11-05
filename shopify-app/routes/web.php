<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Shopify OAuth
Route::get('/install', [AuthController::class, 'install'])->name('install');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

// llms.txt serving (public, no session required — shop resolved via query param)
Route::get('/llms.txt', [LlmsTxtController::class, 'summary']);
Route::get('/llms-full.txt', [LlmsTxtController::class, 'full']);

// Webhooks (POST from Shopify)
Route::post('/webhooks', [WebhookController::class, 'handle'])->name('webhooks');

// Embedded app pages (App Bridge s-app-nav links to /dashboard/{page}).
Route::get('/dashboard/{page?}', [DashboardController::class, 'index'])
    ->where('page', implode('|', DashboardController::PAGES))
    ->name('dashboard');

// Weekly SEO report (1.7): printable page and one-click unsubscribe, opened
// from the email or the Reports page, so they use signed URLs.
Route::get('/report/unsubscribe/{shop}', [ReportController::class, 'unsubscribe'])->middleware('signed:relative')->name('report.unsubscribe');
Route::get('/report/{report}', [ReportController::class, 'show'])->middleware('signed:relative')->whereNumber('report')->name('report.show');

// Product feeds for AI shopping and Google Merchant Center (2.1), secret token.
Route::get('/feeds/{token}/{file}', [FeedController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32,64}')->where('file', 'google\.xml|openai\.jsonl')
    ->middleware('throttle:60,1')->name('feeds.show');

// Install form (for shops that need to enter their license key)
Route::get('/install/form', function () {
    return view('install');
})->name('install.form');

// Landing page
Route::get('/', function () {
    return view('welcome');
});

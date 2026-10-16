<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LlmsTxtController;
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

// Embedded app dashboard (served with Shopify session middleware)
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Install form (for shops that need to enter their license key)
Route::get('/install/form', function () {
    return view('install');
})->name('install.form');

// Landing page
Route::get('/', function () {
    return view('welcome');
});

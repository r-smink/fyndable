<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google Search Console (1.5): the shop's Google connection (tokens
 * encrypted with APP_KEY) and the latest 28-day snapshot per page and per
 * query + page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->text('access_token')->nullable();
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes', 500)->nullable();
            $table->string('status', 20)->default('connected');   // connected|needs_reconnect
            $table->string('site_url', 255)->nullable();           // https://shop.example/ or sc-domain:shop.example
            $table->json('totals')->nullable();                    // clicks, impressions, ctr, position
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_error', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('gsc_page_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('page', 1024);
            $table->string('path', 512);
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->float('ctr');
            $table->float('position');
            $table->index(['shop_id', 'path']);
            $table->index(['shop_id', 'clicks']);
        });

        Schema::create('gsc_query_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('query', 255);
            $table->string('page', 1024);
            $table->string('path', 512);
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('impressions');
            $table->float('ctr');
            $table->float('position');
            $table->index(['shop_id', 'query']);
            $table->index(['shop_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_query_stats');
        Schema::dropIfExists('gsc_page_stats');
        Schema::dropIfExists('google_connections');
    }
};

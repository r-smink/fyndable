<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 404 monitor + redirect manager (1.2).
 *
 * resource_handles: last known handle per product/collection, so a handle
 *                   change or deletion (products/delete only sends the id)
 *                   can get an automatic redirect.
 * not_found_urls:   storefront 404s reported by the theme app embed.
 * redirect_events:  redirects the app created (automatic, import, 404 fix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_handles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('type', 16);                      // product|collection
            $table->string('resource_id', 32);               // numeric Shopify id
            $table->string('handle', 255);
            $table->string('title', 255)->nullable();
            $table->string('collection_handle', 255)->nullable(); // products: first collection
            $table->timestamp('collection_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'type', 'resource_id']);
        });

        Schema::create('not_found_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('path', 512);
            $table->char('path_hash', 40);
            $table->unsignedInteger('hits')->default(1);
            $table->string('referrer', 512)->nullable();     // host + path only
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_target', 512)->nullable(); // null + resolved_at = ignored
            $table->unique(['shop_id', 'path_hash']);
            $table->index(['shop_id', 'resolved_at', 'hits']);
        });

        Schema::create('redirect_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('source', 16);                    // handle_change|deleted|import|not_found|manual
            $table->string('path', 512);
            $table->string('target', 512);
            $table->timestamp('created_at');
            $table->index(['shop_id', 'id']);
        });

        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->boolean('redirect_on_handle_change')->default(true);
            $table->boolean('redirect_on_delete')->default(true);
            $table->string('deleted_redirect_target', 16)->default('collection'); // collection|all|home|search
            $table->boolean('not_found_monitor')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->dropColumn(['redirect_on_handle_change', 'redirect_on_delete', 'deleted_redirect_target', 'not_found_monitor']);
        });
        Schema::dropIfExists('redirect_events');
        Schema::dropIfExists('not_found_urls');
        Schema::dropIfExists('resource_handles');
    }
};

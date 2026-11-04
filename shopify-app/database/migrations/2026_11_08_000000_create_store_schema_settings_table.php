<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store-wide structured data (1.4): settings for the schema app embed, the
 * last theme check (duplicate JSON-LD in the theme) and publish status.
 * The published config itself lives in the app-data metafield
 * fyndable.schema_config on the AppInstallation (read by the app embed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_schema_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->json('settings');
            $table->json('theme_check')->nullable();
            $table->timestamp('theme_checked_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('publish_error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_schema_settings');
    }
};

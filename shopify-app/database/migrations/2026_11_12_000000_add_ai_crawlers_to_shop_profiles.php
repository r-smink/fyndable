<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI crawler choices (2.2): user agent => allow|block, used to generate the
 * Fyndable block for the theme's templates/robots.txt.liquid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->json('ai_crawlers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->dropColumn('ai_crawlers');
        });
    }
};

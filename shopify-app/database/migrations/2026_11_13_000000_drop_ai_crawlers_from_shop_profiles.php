<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI crawler choices are gone (2.2 is read only: App Store requirement
 * 5.1.1 does not allow asking merchants to edit theme code).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_profiles', 'ai_crawlers')) {
            Schema::table('shop_profiles', function (Blueprint $table) {
                $table->dropColumn('ai_crawlers');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->json('ai_crawlers')->nullable();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The theme check is gone (no read_themes, App Store requirement 5.1.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['theme_check', 'theme_checked_at'] as $column) {
            if (Schema::hasColumn('store_schema_settings', $column)) {
                Schema::table('store_schema_settings', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('store_schema_settings', function (Blueprint $table) {
            $table->json('theme_check')->nullable();
            $table->timestamp('theme_checked_at')->nullable();
        });
    }
};

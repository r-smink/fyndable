<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporting (1.7): what Fyndable changed in the store (activity_events),
 * weekly report snapshots (seo_reports) and the report email settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('kind', 32);          // meta|description|schema|alt_text|...
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('created_at');
            $table->index(['shop_id', 'created_at']);
        });

        Schema::create('seo_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('metrics');
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to', 255)->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'id']);
        });

        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->boolean('report_enabled')->default(false);
            $table->string('report_email', 255)->nullable();
            $table->string('report_language', 5)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shop_profiles', function (Blueprint $table) {
            $table->dropColumn(['report_enabled', 'report_email', 'report_language']);
        });
        Schema::dropIfExists('seo_reports');
        Schema::dropIfExists('activity_events');
    }
};

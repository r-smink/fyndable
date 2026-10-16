<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->string('url')->nullable();
            $table->string('country', 5)->default('us');
            $table->string('language', 5)->default('en');
            $table->string('search_type', 20)->default('organic');
            $table->string('target_business_name')->nullable();
            $table->integer('last_position')->nullable();
            $table->integer('best_position')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'keyword', 'url']);
        });

        Schema::create('rank_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_keyword_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->string('search_engine', 30)->default('google');
            $table->string('result_url')->nullable();
            $table->json('serp_features')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
            $table->index(['tracked_keyword_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_history');
        Schema::dropIfExists('tracked_keywords');
    }
};

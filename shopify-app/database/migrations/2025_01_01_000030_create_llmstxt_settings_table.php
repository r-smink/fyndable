<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llmstxt_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('full_enabled')->default(true);
            $table->boolean('include_products')->default(true);
            $table->boolean('include_collections')->default(true);
            $table->boolean('include_pages')->default(true);
            $table->boolean('include_blogs')->default(true);
            $table->integer('max_products')->default(100);
            $table->integer('max_pages')->default(50);
            $table->integer('max_articles')->default(250);
            $table->integer('max_collections')->default(100);
            $table->integer('full_max_chars')->default(50000);
            $table->boolean('include_excerpt')->default(true);
            $table->text('description')->nullable();
            $table->text('custom_sections')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llmstxt_settings');
    }
};

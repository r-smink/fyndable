<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI-commerce readiness (2.1): product feed for Google Merchant Center and
 * the OpenAI product feed (public URL with a secret token) and the
 * AI-ready score per product from the last feed build.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->boolean('enabled')->default(false);
            $table->string('status', 16)->default('idle');      // idle|building|ready|failed
            $table->unsignedInteger('products_count')->default(0);
            $table->unsignedInteger('items_count')->default(0);  // variants in the feed
            $table->unsignedTinyInteger('average_score')->nullable();
            $table->json('summary')->nullable();                 // issue code => products
            $table->string('error', 255)->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_readiness_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('product_id', 32);                    // numeric Shopify id
            $table->string('title', 255);
            $table->unsignedTinyInteger('score');
            $table->json('issues');
            $table->index(['shop_id', 'score']);
            $table->unique(['shop_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_readiness_items');
        Schema::dropIfExists('product_feeds');
    }
};

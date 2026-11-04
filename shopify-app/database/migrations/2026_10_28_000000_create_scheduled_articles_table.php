<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('blog_id');                       // Shopify GID or numeric ID
            $table->string('title');
            $table->longText('body_html');
            $table->string('tags')->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status', 20)->default('scheduled'); // scheduled, published, failed, cancelled
            $table->string('shopify_article_id')->nullable();   // Filled after publish
            $table->json('meta')->nullable();                    // Extra data (author, language, etc.)
            $table->text('error')->nullable();                   // Failure reason
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_articles');
    }
};

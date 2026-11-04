<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('metric', 40);        // geo_scans, blog_articles
            $table->char('period', 7);           // YYYY-MM
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_usage');
    }
};

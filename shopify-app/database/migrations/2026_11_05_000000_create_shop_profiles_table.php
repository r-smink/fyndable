<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store profile from the onboarding wizard: content language, target market
 * and brand voice used by every AI text generator (BrandVoice service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained('shops')->cascadeOnDelete();
            $table->string('content_language', 8)->nullable();   // en, nl, de, ...
            $table->char('target_country', 2)->nullable();       // ISO 3166-1 alpha-2
            $table->string('brand_tone', 32)->nullable();        // see BrandVoice::TONES
            $table->string('brand_audience', 255)->nullable();
            $table->text('brand_description')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('onboarding_skipped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_profiles');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop_domain')->unique();
            $table->string('access_token')->nullable();
            $table->string('scope')->nullable();
            $table->string('license_key')->nullable();
            $table->string('tenant_key')->nullable();
            $table->string('license_tier')->default('free');
            $table->timestamp('license_validated_at')->nullable();
            $table->boolean('is_installed')->default(false);
            $table->boolean('is_uninstalled')->default(false);
            $table->string('shop_name')->nullable();
            $table->string('shop_email')->nullable();
            $table->string('currency')->nullable();
            $table->string('country_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};

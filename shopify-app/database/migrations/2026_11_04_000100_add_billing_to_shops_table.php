<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopify App Pricing state per shop + the products used by the in-app trial.
 *
 * billing_state: trial | active | none (null = never checked).
 * license_source: app_pricing (tenant created by the billing sync) or
 * manual (agency/enterprise license key entered by the merchant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('shopify_shop_gid')->nullable()->after('shop_domain');
            $table->string('license_source', 20)->nullable()->after('license_tier');
            $table->string('billing_state', 20)->nullable()->after('license_source');
            $table->string('billing_plan', 64)->nullable()->after('billing_state');
            $table->timestamp('billing_trial_ends_at')->nullable()->after('billing_plan');
            $table->timestamp('billing_cycle_ends_at')->nullable()->after('billing_trial_ends_at');
            $table->boolean('billing_cancel_at_cycle_end')->default(false)->after('billing_cycle_ends_at');
            $table->timestamp('billing_checked_at')->nullable()->after('billing_cancel_at_cycle_end');
            $table->timestamp('trial_ended_at')->nullable()->after('billing_checked_at');
        });

        Schema::create('trial_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('product_id', 32);    // numeric Shopify product id
            $table->timestamps();

            $table->unique(['shop_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_products');

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_shop_gid',
                'license_source',
                'billing_state',
                'billing_plan',
                'billing_trial_ends_at',
                'billing_cycle_ends_at',
                'billing_cancel_at_cycle_end',
                'billing_checked_at',
                'trial_ended_at',
            ]);
        });
    }
};

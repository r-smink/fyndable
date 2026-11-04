<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store-wide SEO audits (SeoAuditJob) and the per-item results. Only the
 * last few audits per shop are kept (SeoAuditJob::KEEP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('status', 16)->default('queued');   // queued|running|completed|failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->json('summary')->nullable();                 // issue code => item count
            $table->string('error', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'id']);
        });

        Schema::create('seo_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_audit_id')->constrained('seo_audits')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('type', 16);                          // product|collection|page|article
            $table->string('resource_id', 128);                  // Shopify GID
            $table->string('title', 255);
            $table->string('url', 512)->nullable();
            $table->unsignedTinyInteger('score');
            $table->json('issues');
            $table->index(['seo_audit_id', 'score']);
            $table->index(['shop_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_items');
        Schema::dropIfExists('seo_audits');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopProfile extends Model
{
    protected $fillable = [
        'shop_id',
        'content_language',
        'target_country',
        'brand_tone',
        'brand_audience',
        'brand_description',
        'onboarding_completed_at',
        'onboarding_skipped_at',
        'redirect_on_handle_change',
        'redirect_on_delete',
        'deleted_redirect_target',
        'not_found_monitor',
        'report_enabled',
        'report_email',
        'report_language',
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'onboarding_skipped_at' => 'datetime',
        'redirect_on_handle_change' => 'boolean',
        'redirect_on_delete' => 'boolean',
        'not_found_monitor' => 'boolean',
        'report_enabled' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Whether the merchant finished or explicitly skipped the wizard.
     */
    public function onboardingDone(): bool
    {
        return $this->onboarding_completed_at !== null || $this->onboarding_skipped_at !== null;
    }
}

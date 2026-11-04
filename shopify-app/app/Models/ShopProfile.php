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
    ];

    protected $casts = [
        'onboarding_completed_at' => 'datetime',
        'onboarding_skipped_at' => 'datetime',
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

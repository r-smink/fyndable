<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedKeyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'keyword',
        'url',
        'country',
        'language',
        'search_type',
        'target_business_name',
        'last_position',
        'best_position',
        'last_checked_at',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(RankHistory::class);
    }
}

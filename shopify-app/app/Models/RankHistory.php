<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RankHistory extends Model
{
    protected $fillable = [
        'tracked_keyword_id',
        'position',
        'search_engine',
        'result_url',
        'serp_features',
        'checked_at',
    ];

    protected $casts = [
        'serp_features' => 'array',
        'checked_at' => 'datetime',
    ];

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(TrackedKeyword::class);
    }
}

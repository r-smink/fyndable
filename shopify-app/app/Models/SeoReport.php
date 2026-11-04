<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weekly SEO report snapshot (1.7).
 */
class SeoReport extends Model
{
    protected $fillable = ['shop_id', 'period_start', 'period_end', 'metrics', 'sent_at', 'sent_to'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metrics' => 'array',
        'sent_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

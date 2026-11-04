<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoAudit extends Model
{
    protected $fillable = [
        'shop_id',
        'status',
        'progress',
        'score',
        'items_count',
        'summary',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SeoAuditItem::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    /**
     * Latest completed audit of a shop.
     */
    public static function latestCompleted(int $shopId): ?self
    {
        return self::where('shop_id', $shopId)->where('status', 'completed')->latest('id')->first();
    }
}

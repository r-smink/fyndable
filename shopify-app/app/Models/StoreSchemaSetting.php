<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSchemaSetting extends Model
{
    protected $fillable = [
        'shop_id',
        'settings',
        'theme_check',
        'theme_checked_at',
        'published_at',
        'publish_error',
    ];

    protected $casts = [
        'settings' => 'array',
        'theme_check' => 'array',
        'theme_checked_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shop's Google account connection (Search Console, 1.5). Tokens are
 * encrypted at rest with APP_KEY and never sent to the browser.
 */
class GoogleConnection extends Model
{
    protected $fillable = [
        'shop_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'site_url',
        'totals',
        'period_start',
        'period_end',
        'synced_at',
        'sync_error',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'totals' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'synced_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}

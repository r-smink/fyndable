<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Storefront 404 reported by the theme app embed (1.2 404 monitor).
 */
class NotFoundUrl extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shop_id',
        'path',
        'path_hash',
        'hits',
        'referrer',
        'first_seen_at',
        'last_seen_at',
        'resolved_at',
        'resolved_target',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public static function hashPath(string $path): string
    {
        return sha1(mb_strtolower($path));
    }
}

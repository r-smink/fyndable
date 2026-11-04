<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Last known handle of a product or collection (1.2 automatic redirects).
 */
class ResourceHandle extends Model
{
    protected $fillable = [
        'shop_id',
        'type',
        'resource_id',
        'handle',
        'title',
        'collection_handle',
        'collection_checked_at',
    ];

    protected $casts = [
        'collection_checked_at' => 'datetime',
    ];
}

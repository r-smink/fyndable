<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A redirect the app created (automatic, import or 404 fix).
 */
class RedirectEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'shop_id',
        'source',
        'path',
        'target',
    ];
}

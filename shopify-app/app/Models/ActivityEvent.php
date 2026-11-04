<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Something Fyndable changed in the store (reporting, 1.7).
 */
class ActivityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['shop_id', 'kind', 'count'];

    public static function record(int $shopId, string $kind, int $count = 1): void
    {
        if ($count > 0) {
            self::create(['shop_id' => $shopId, 'kind' => $kind, 'count' => $count]);
        }
    }
}

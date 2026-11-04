<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiReadinessItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['shop_id', 'product_id', 'title', 'score', 'issues'];

    protected $casts = ['issues' => 'array'];
}

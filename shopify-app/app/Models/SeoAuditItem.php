<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAuditItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'seo_audit_id',
        'shop_id',
        'type',
        'resource_id',
        'title',
        'url',
        'score',
        'issues',
    ];

    protected $casts = [
        'issues' => 'array',
    ];
}

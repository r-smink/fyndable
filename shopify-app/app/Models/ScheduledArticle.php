<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledArticle extends Model
{
    protected $fillable = [
        'shop_id',
        'blog_id',
        'title',
        'body_html',
        'tags',
        'scheduled_at',
        'status',
        'shopify_article_id',
        'meta',
        'error',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'meta' => 'array',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isDue(): bool
    {
        return $this->isScheduled() && $this->scheduled_at->isPast();
    }
}

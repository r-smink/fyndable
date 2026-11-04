<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Product feed of a shop for AI shopping and Google Merchant Center (2.1).
 * Files: storage/app/feeds/{shop_id}/google.xml and openai.jsonl.
 */
class ProductFeed extends Model
{
    public const FORMATS = ['google' => 'google.xml', 'openai' => 'openai.jsonl'];

    protected $fillable = [
        'shop_id', 'token', 'enabled', 'status', 'products_count', 'items_count',
        'average_score', 'summary', 'error', 'built_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'summary' => 'array',
        'built_at' => 'datetime',
    ];

    public static function forShop(Shop $shop): self
    {
        return self::firstOrCreate(['shop_id' => $shop->id], ['token' => Str::random(48)]);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function path(string $format): string
    {
        return 'feeds/'.$this->shop_id.'/'.self::FORMATS[$format];
    }

    public function url(string $format): string
    {
        return url('/feeds/'.$this->token.'/'.self::FORMATS[$format]);
    }
}

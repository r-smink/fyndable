<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmsTxtSettings extends Model
{
    protected $table = 'llmstxt_settings';

    protected $fillable = [
        'shop_id',
        'enabled',
        'full_enabled',
        'include_products',
        'include_collections',
        'include_pages',
        'include_blogs',
        'max_products',
        'max_pages',
        'max_articles',
        'max_collections',
        'full_max_chars',
        'include_excerpt',
        'description',
        'custom_sections',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'full_enabled' => 'boolean',
        'include_products' => 'boolean',
        'include_collections' => 'boolean',
        'include_pages' => 'boolean',
        'include_blogs' => 'boolean',
        'include_excerpt' => 'boolean',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get or create default settings for a shop.
     */
    public static function getForShop(int $shopId): self
    {
        return self::firstOrCreate(['shop_id' => $shopId]);
    }
}

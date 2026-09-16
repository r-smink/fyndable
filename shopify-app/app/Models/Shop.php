<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_domain',
        'access_token',
        'scope',
        'license_key',
        'tenant_key',
        'license_tier',
        'license_validated_at',
        'is_installed',
        'is_uninstalled',
        'shop_name',
        'shop_email',
        'currency',
        'country_code',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $casts = [
        'is_installed' => 'boolean',
        'is_uninstalled' => 'boolean',
        'license_validated_at' => 'datetime',
    ];

    public function trackedKeywords(): HasMany
    {
        return $this->hasMany(TrackedKeyword::class);
    }

    public function llmsTxtSettings(): HasOne
    {
        return $this->hasOne(LlmsTxtSettings::class);
    }

    /**
     * Find a shop by its Shopify domain (e.g. "store.myshopify.com").
     */
    public static function findByDomain(string $domain): ?self
    {
        $domain = strtolower(trim($domain));

        return self::where('shop_domain', $domain)->first();
    }

    /**
     * Check if this shop has a valid (non-empty) access token.
     */
    public function hasAccessToken(): bool
    {
        return ! empty($this->access_token);
    }

    /**
     * Check if this shop has a linked Fyndable license.
     */
    public function hasLicense(): bool
    {
        return ! empty($this->license_key) && ! empty($this->tenant_key);
    }

    /**
     * Access scopes required by the app but missing from the stored token.
     *
     * With the legacy install flow a token keeps the scopes it was granted at
     * install time. When the app adds scopes later, existing installs must
     * re-authorize — until then the affected Admin API calls fail.
     *
     * @return array<int, string>
     */
    public function missingScopes(): array
    {
        $required = array_filter(array_map('trim', explode(',', (string) config('shopify.scopes'))));
        $granted = array_filter(array_map('trim', explode(',', (string) $this->scope)));

        // Shopify treats write_X as implying read_X: accessScopes reports only
        // write_products even though the token can also read products. Count
        // the implied read scopes as granted.
        $impliedReads = array_map(
            fn (string $scope) => 'read_'.substr($scope, 6),
            array_filter($granted, fn (string $scope) => str_starts_with($scope, 'write_'))
        );
        $effective = array_merge($granted, $impliedReads);

        return array_values(array_diff($required, $effective));
    }
}

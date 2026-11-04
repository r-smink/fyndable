<?php

namespace App\Services;

use App\Models\NotFoundUrl;
use App\Models\RedirectEvent;
use App\Models\ResourceHandle;
use App\Models\Shop;
use App\Models\ShopProfile;

/**
 * RedirectManager (1.2)
 *
 * - Automatic redirects when a product/collection handle changes or the
 *   item is deleted (called from TrackHandleChangeJob, fed by webhooks).
 * - 404 reports from the theme app embed (NotFoundController).
 * - Redirect settings and the log of redirects the app created.
 */
class RedirectManager
{
    public const DELETE_TARGETS = ['collection', 'all', 'home', 'search'];

    public const SOURCES = ['handle_change', 'deleted', 'import', 'not_found', 'manual'];

    /** Distinct open 404 paths kept per shop (bots can generate endless URLs). */
    public const MAX_NOT_FOUND = 2000;

    /** How long a product's first collection stays cached for delete redirects. */
    private const COLLECTION_TTL_HOURS = 24;

    public function __construct(
        private ShopifyUrlRedirect $redirects,
        private ShopifyContentFetcher $fetcher
    ) {}

    /**
     * @return array{redirect_on_handle_change:bool, redirect_on_delete:bool, deleted_redirect_target:string, not_found_monitor:bool}
     */
    public function settings(Shop $shop): array
    {
        $profile = ShopProfile::where('shop_id', $shop->id)->first();

        return [
            'redirect_on_handle_change' => $profile?->redirect_on_handle_change ?? true,
            'redirect_on_delete' => $profile?->redirect_on_delete ?? true,
            'deleted_redirect_target' => $profile?->deleted_redirect_target ?? 'collection',
            'not_found_monitor' => $profile?->not_found_monitor ?? true,
        ];
    }

    public function saveSettings(Shop $shop, array $settings): array
    {
        $profile = ShopProfile::firstOrCreate(['shop_id' => $shop->id]);
        $profile->fill(array_intersect_key($settings, array_flip([
            'redirect_on_handle_change', 'redirect_on_delete', 'deleted_redirect_target', 'not_found_monitor',
        ])));
        $profile->save();

        return $this->settings($shop);
    }

    /**
     * products/update, collections/update (and create): remember the handle
     * and redirect the old URL when it changed.
     */
    public function resourceUpdated(Shop $shop, string $type, string $id, string $handle, ?string $title): ?array
    {
        if ($handle === '') {
            return null;
        }

        $row = ResourceHandle::where(['shop_id' => $shop->id, 'type' => $type, 'resource_id' => $id])->first();
        $result = null;

        if ($row && $row->handle !== $handle && $this->settings($shop)['redirect_on_handle_change']) {
            $result = $this->redirect($shop, $this->path($type, $row->handle), $this->path($type, $handle), 'handle_change');
        }

        $row ??= new ResourceHandle(['shop_id' => $shop->id, 'type' => $type, 'resource_id' => $id]);
        $handleChanged = $row->handle !== $handle;
        $row->fill(['handle' => $handle, 'title' => $title !== null ? mb_substr($title, 0, 255) : $row->title]);

        // Products: remember the first collection for a redirect on delete.
        if ($type === 'product' && ($handleChanged || ! $row->collection_checked_at || $row->collection_checked_at->lt(now()->subHours(self::COLLECTION_TTL_HOURS)))) {
            $collection = $this->fetcher->getProductCollectionHandle($shop, "gid://shopify/Product/{$id}");
            if ($collection !== false) {
                $row->collection_handle = $collection;
                $row->collection_checked_at = now();
            }
        }
        $row->save();

        return $result;
    }

    /**
     * products/delete, collections/delete: redirect the removed URL.
     */
    public function resourceDeleted(Shop $shop, string $type, string $id): ?array
    {
        $row = ResourceHandle::where(['shop_id' => $shop->id, 'type' => $type, 'resource_id' => $id])->first();
        if (! $row) {
            return null;
        }
        $row->delete();

        $settings = $this->settings($shop);
        if (! $settings['redirect_on_delete']) {
            return null;
        }

        $target = match ($settings['deleted_redirect_target']) {
            'home' => '/',
            'search' => '/search?q='.rawurlencode((string) ($row->title ?: $row->handle)),
            'collection' => $type === 'product' && $row->collection_handle ? '/collections/'.$row->collection_handle : '/collections/all',
            default => '/collections/all',
        };

        return $this->redirect($shop, $this->path($type, $row->handle), $target, 'deleted');
    }

    /**
     * Create (or repoint) a redirect and log it.
     */
    public function redirect(Shop $shop, string $path, string $target, string $source): array
    {
        if (strcasecmp(rtrim($path, '/'), rtrim($target, '/')) === 0) {
            return ['path' => $path, 'status' => 'error', 'message' => 'same_path'];
        }

        $result = $this->redirects->ensure($shop, $path, $target);
        if (in_array($result['status'], ['created', 'updated'], true)) {
            RedirectEvent::create(['shop_id' => $shop->id, 'source' => $source, 'path' => mb_substr($path, 0, 512), 'target' => mb_substr($target, 0, 512)]);
            // A redirect for a reported 404 resolves it.
            NotFoundUrl::where('shop_id', $shop->id)->where('path_hash', NotFoundUrl::hashPath($path))->whereNull('resolved_at')
                ->update(['resolved_at' => now(), 'resolved_target' => mb_substr($target, 0, 512)]);
        }

        return $result;
    }

    /**
     * Record a storefront 404 (from the theme app embed via the app proxy).
     */
    public function recordNotFound(Shop $shop, string $path, ?string $referrer): bool
    {
        $path = $this->cleanPath($path);
        if ($path === null || ! $this->settings($shop)['not_found_monitor']) {
            return false;
        }

        $hash = NotFoundUrl::hashPath($path);
        $existing = NotFoundUrl::where('shop_id', $shop->id)->where('path_hash', $hash)->first();
        if ($existing) {
            $existing->hits++;
            $existing->last_seen_at = now();
            $existing->referrer = $this->cleanReferrer($referrer) ?? $existing->referrer;
            // A 404 that comes back after it was fixed (redirect removed) reopens.
            if ($existing->resolved_target !== null) {
                $existing->resolved_at = null;
                $existing->resolved_target = null;
            }
            $existing->save();

            return true;
        }

        if (NotFoundUrl::where('shop_id', $shop->id)->whereNull('resolved_at')->count() >= self::MAX_NOT_FOUND) {
            return false;
        }

        NotFoundUrl::create([
            'shop_id' => $shop->id,
            'path' => $path,
            'path_hash' => $hash,
            'hits' => 1,
            'referrer' => $this->cleanReferrer($referrer),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return true;
    }

    public function path(string $type, string $handle): string
    {
        return ($type === 'collection' ? '/collections/' : '/products/').$handle;
    }

    /**
     * Storefront path without query, fragment or locale noise; null for
     * paths that are not worth tracking (assets, app proxy, admin).
     */
    public function cleanPath(string $path): ?string
    {
        $path = (string) parse_url(trim($path), PHP_URL_PATH);
        $path = '/'.ltrim(rawurldecode($path), '/');
        if ($path === '/' || mb_strlen($path) > 500 || preg_match('/[\x00-\x1F<>"]/', $path)) {
            return null;
        }
        if (preg_match('#^/(apps|cdn|admin|cart|checkout|account|checkouts|services|wpm)(/|$)#i', $path)
            || preg_match('/\.(js|css|map|png|jpe?g|gif|webp|svg|ico|woff2?|php|env|xml)$/i', $path)) {
            return null;
        }

        return $path;
    }

    /**
     * Referrer as host + path only (no query string: may hold personal data).
     */
    private function cleanReferrer(?string $referrer): ?string
    {
        if (! $referrer) {
            return null;
        }
        $parts = parse_url($referrer);
        if (empty($parts['host'])) {
            return null;
        }

        return mb_substr($parts['host'].($parts['path'] ?? ''), 0, 512);
    }
}

<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ShopifyBillingService
 *
 * Shopify App Pricing integration. Plans are managed in the Partner
 * Dashboard; App Pricing sends no subscription webhooks, so the subscription
 * is read from the Partner API:
 *  - on every app load (cached for config shopify.billing.check_ttl seconds),
 *  - after the plan selection redirect (plan_handle is only a hint and is
 *    always confirmed via the Partner API),
 *  - by the hourly SyncShopBillingJob (cancellations and freezes).
 *
 * Billing states (shops.billing_state):
 *  - trial:  no subscription yet, in-app trial (AI optimization of
 *            config shopify.billing.trial_products distinct products).
 *  - active: paid App Pricing subscription; plan_handle maps to a tier.
 *  - none:   no subscription and the trial is used up or ended.
 *
 * Every state change is pushed to the SaaS dashboard, which owns the tenant +
 * license (platform=shopify). Feature gating keeps running through the
 * normal license validation (LicenseService / RequireTier).
 */
class ShopifyBillingService
{
    public function __construct(
        private PartnerApiClient $partner,
        private SaasProxyClient $saas,
        private ShopifyContentFetcher $fetcher
    ) {}

    public function enabled(): bool
    {
        return (bool) config('shopify.billing_enabled');
    }

    /**
     * Re-read the subscription when the cached result is stale (or $force)
     * and sync changes to the SaaS. Returns the status for the dashboard.
     */
    public function refresh(Shop $shop, bool $force = false): array
    {
        if (! $this->enabled()) {
            return $this->status($shop);
        }

        // An expired trial is re-checked right away (the merchant may have
        // picked a plan meanwhile), regardless of the cache.
        $trialExpired = $shop->billing_state === 'trial' && $this->trialExpired($shop);

        $fresh = $shop->billing_checked_at !== null
            && $shop->billing_checked_at->gt(now()->subSeconds((int) config('shopify.billing.check_ttl', 600)));
        if ($fresh && ! $force && ! $trialExpired && $shop->billing_state !== null) {
            return $this->status($shop);
        }

        $previous = [$shop->billing_state, $shop->license_tier];

        $subscription = $this->readSubscription($shop);
        if ($subscription === false) {
            // Partner API unavailable: keep the last known state. A shop that
            // was never checked and never had a trial starts in the trial (it
            // cannot be paying yet); otherwise the state stays unknown. An
            // expired trial still ends.
            if ($trialExpired) {
                $this->endTrial($shop);
            } elseif ($shop->billing_state === null && $shop->trial_ended_at === null) {
                $this->applyNoSubscription($shop);
                $this->syncToSaas($shop);
            }

            return $this->status($shop);
        }

        if ($subscription === null) {
            $this->applyNoSubscription($shop);
        } else {
            $this->applySubscription($shop, $subscription);
        }

        $shop->billing_checked_at = now();
        $shop->save();

        $changed = $previous !== [$shop->billing_state, $shop->license_tier];
        $needsTenant = in_array($shop->billing_state, ['trial', 'active'], true) && ! $shop->hasLicense();
        if ($changed || $needsTenant) {
            $this->syncToSaas($shop);
        }

        return $this->status($shop);
    }

    /**
     * Status payload for the dashboard / billing endpoints.
     */
    public function status(Shop $shop): array
    {
        $state = ! $this->enabled() ? 'disabled' : ($shop->billing_state ?? 'unknown');

        return [
            'enabled' => $this->enabled(),
            'state' => $state,
            'plan' => $shop->billing_plan,
            'tier' => $shop->license_tier,
            'required' => $this->enabled() && $state === 'none',
            'trial' => [
                'used' => $this->trialProductsUsed($shop),
                'max' => $this->trialLimit(),
                'ended' => $shop->trial_ended_at !== null,
                'days' => $this->trialDays(),
                'ends_at' => $this->trialExpiresAt($shop)?->toIso8601String(),
            ],
            'trial_ends_at' => $shop->billing_trial_ends_at?->toIso8601String(),
            'cycle_ends_at' => $shop->billing_cycle_ends_at?->toIso8601String(),
            'cancel_at_cycle_end' => (bool) $shop->billing_cancel_at_cycle_end,
            'pricing_url' => $this->pricingPageUrl($shop),
        ];
    }

    /**
     * Shopify-hosted plan selection page.
     */
    public function pricingPageUrl(Shop $shop): string
    {
        $storeHandle = str_replace('.myshopify.com', '', $shop->shop_domain);
        $appHandle = (string) config('shopify.billing.app_handle');

        return 'https://admin.shopify.com/store/'.rawurlencode($storeHandle)
            .'/charges/'.rawurlencode($appHandle).'/pricing_plans';
    }

    /**
     * Fyndable tier for a Partner Dashboard plan handle (null = unknown).
     */
    public function tierForPlan(?string $planHandle): ?string
    {
        if ($planHandle === null || $planHandle === '') {
            return null;
        }

        return config('shopify.billing.plans', [])[$planHandle] ?? null;
    }

    public function trialLimit(): int
    {
        return max(0, (int) config('shopify.billing.trial_products', 5));
    }

    public function trialDays(): int
    {
        return max(0, (int) config('shopify.billing.trial_days', 7));
    }

    /**
     * End of the in-app trial window (null = trial not started).
     */
    public function trialExpiresAt(Shop $shop): ?Carbon
    {
        return $shop->trial_started_at?->copy()->addDays($this->trialDays());
    }

    public function trialExpired(Shop $shop): bool
    {
        $expiresAt = $this->trialExpiresAt($shop);

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function trialProductsUsed(Shop $shop): int
    {
        return (int) DB::table('trial_products')->where('shop_id', $shop->id)->count();
    }

    /**
     * Count a product towards the in-app trial.
     *
     * Products already counted stay usable. A new product beyond the limit,
     * or any product after the trial window, ends the trial (state none,
     * tenant suspended) and returns false.
     */
    public function claimTrialProduct(Shop $shop, string $productId): bool
    {
        if ($this->trialExpired($shop)) {
            $this->endTrial($shop);

            return false;
        }

        if (DB::table('trial_products')->where('shop_id', $shop->id)->where('product_id', $productId)->exists()) {
            return true;
        }

        if ($this->trialProductsUsed($shop) < $this->trialLimit()) {
            DB::table('trial_products')->insertOrIgnore([
                'shop_id' => $shop->id,
                'product_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }

        $this->endTrial($shop);

        return false;
    }

    /**
     * End the in-app trial: the shop needs a paid plan from here on.
     */
    public function endTrial(Shop $shop): void
    {
        if ($shop->trial_ended_at === null) {
            $shop->trial_ended_at = now();
        }

        if ($shop->billing_state === 'trial') {
            $shop->billing_state = 'none';
            $shop->save();
            $this->syncToSaas($shop);

            return;
        }

        $shop->save();
    }

    /**
     * Uninstall: Shopify cancels the subscription; suspend the SaaS tenant.
     */
    public function handleUninstall(Shop $shop): void
    {
        if (! $shop->hasLicense()) {
            return;
        }

        $result = $this->saas->syncShopifySubscription($this->payload($shop, 'uninstalled'));
        if (isset($result['error'])) {
            Log::warning('ShopifyBillingService: uninstall sync failed', [
                'shop' => $shop->shop_domain,
                'error' => $result['error'],
            ]);
        }

        $shop->billing_state = null;
        $shop->billing_checked_at = null;
        $shop->save();
        Cache::forget("shop:{$shop->id}:license_status");
    }

    /**
     * Push the current state to the SaaS and store the returned credentials.
     */
    public function syncToSaas(Shop $shop): bool
    {
        $state = $shop->billing_state ?? 'none';
        $result = $this->saas->syncShopifySubscription($this->payload($shop, $state));

        if (isset($result['error'])) {
            Log::warning('ShopifyBillingService: SaaS sync failed', [
                'shop' => $shop->shop_domain,
                'state' => $state,
                'error' => $result['error'],
            ]);

            return false;
        }

        if (! empty($result['license_key']) && ! empty($result['tenant_key'])) {
            $shop->license_key = $result['license_key'];
            $shop->tenant_key = $result['tenant_key'];
            $shop->license_source = 'app_pricing';
        }
        if (! empty($result['tier']) && in_array($state, ['trial', 'active'], true)) {
            $shop->license_tier = $result['tier'];
        }
        $shop->license_validated_at = null;
        $shop->save();

        // Feature gating reads the cached validation; drop it so the new
        // tier/status applies on the next request.
        Cache::forget("shop:{$shop->id}:license_status");

        return true;
    }

    /**
     * @return array|null|false subscription, null when none, false on API error
     */
    private function readSubscription(Shop $shop): array|null|false
    {
        $gid = $this->shopGid($shop);
        if ($gid === null) {
            return false;
        }

        $result = $this->partner->activeSubscription($gid);
        if (! $result['ok']) {
            return false;
        }

        return $result['subscription'];
    }

    private function applySubscription(Shop $shop, array $subscription): void
    {
        $handles = array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? ($item['handle'] ?? null) : null,
            $subscription['items'] ?? []
        )));

        $planHandle = null;
        $tier = null;
        foreach ($handles as $handle) {
            if ($this->tierForPlan($handle) !== null) {
                $planHandle = $handle;
                $tier = $this->tierForPlan($handle);
                break;
            }
        }

        if ($tier === null) {
            // A paying merchant must never be locked out by a config mismatch:
            // grant the lowest paid tier and flag it for follow-up.
            Log::error('ShopifyBillingService: unknown plan handle, falling back to starter', [
                'shop' => $shop->shop_domain,
                'handles' => $handles,
            ]);
            $planHandle = $handles[0] ?? null;
            $tier = 'starter';
        }

        $shop->billing_state = 'active';
        $shop->billing_plan = $planHandle;
        $shop->license_tier = $tier;
        $shop->billing_trial_ends_at = $this->parseDate($subscription['trialEndsAt'] ?? null);
        $shop->billing_cycle_ends_at = $this->parseDate($subscription['currentBillingCycle']['endTime'] ?? null);
        $shop->billing_cancel_at_cycle_end = (bool) ($subscription['cancelAtEndOfCycle'] ?? false);

        // A paid plan consumes the in-app trial for good.
        if ($shop->trial_ended_at === null) {
            $shop->trial_ended_at = now();
        }
    }

    private function applyNoSubscription(Shop $shop): void
    {
        // The trial ends after trial_days, via endTrial() (a product beyond
        // the limit) or with a paid plan. A full product counter alone does
        // not end it, so the results of the last trial product can be saved.
        if ($shop->trial_ended_at === null && $this->trialExpired($shop)) {
            $shop->trial_ended_at = now();
        }
        $inTrial = $shop->trial_ended_at === null;
        if ($inTrial && $shop->trial_started_at === null) {
            $shop->trial_started_at = now();
        }

        $shop->billing_state = $inTrial ? 'trial' : 'none';
        $shop->billing_plan = null;
        $shop->billing_trial_ends_at = null;
        $shop->billing_cycle_ends_at = null;
        $shop->billing_cancel_at_cycle_end = false;
        $shop->license_tier = $inTrial ? 'trial' : 'free';
        $shop->billing_checked_at = now();
        $shop->save();
    }

    /**
     * gid://shopify/Shop/{id}, fetched once from the Admin API.
     */
    private function shopGid(Shop $shop): ?string
    {
        if (! empty($shop->shopify_shop_gid)) {
            return $shop->shopify_shop_gid;
        }

        $details = $this->fetcher->getShopDetails($shop);
        if (empty($details['gid'])) {
            return null;
        }

        $shop->shopify_shop_gid = $details['gid'];
        if (! empty($details['name']) && empty($shop->shop_name)) {
            $shop->shop_name = $details['name'];
        }
        if (! empty($details['email'])) {
            $shop->shop_email = $details['email'];
        }
        $shop->save();

        return $shop->shopify_shop_gid;
    }

    private function payload(Shop $shop, string $state): array
    {
        $tier = match ($state) {
            'active' => $shop->license_tier,
            'trial' => 'trial',
            default => null,
        };

        return [
            'shop_domain' => $shop->shop_domain,
            'shop_name' => (string) $shop->shop_name,
            'shop_email' => (string) $shop->shop_email,
            'state' => $state,
            'tier' => $tier,
            'plan_handle' => $shop->billing_plan,
            // In-app trial: its end date lets the SaaS expire the tenant even
            // when a later sync fails. Paid plans: the Shopify trial end.
            'trial_ends_at' => $state === 'trial'
                ? $this->trialExpiresAt($shop)?->toIso8601String()
                : $shop->billing_trial_ends_at?->toIso8601String(),
            'cycle_ends_at' => $shop->billing_cycle_ends_at?->toIso8601String(),
            'cancel_at_end_of_cycle' => (bool) $shop->billing_cancel_at_cycle_end,
        ];
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace SSEOAISaaS;

/**
 * Custom Plan
 *
 * Per-customer custom pricing on top of the agency-tier infrastructure.
 * A custom plan is stored as a tenant setting (`custom_plan`) on an
 * agency-tier tenant that is linked to an agency account. The account's
 * `max_sub_licenses` doubles as the number of paid licenses.
 *
 * Plan settings shape:
 *   price_per_license  float   Monthly price per license (EUR)
 *   discounts          array   [{min: int, pct: float}] volume discount
 *                              brackets — highest applicable wins
 *   max_licenses       int     Hard cap on paid licenses (0 = unlimited)
 *   initial_licenses   int     Licenses covered by the first payment
 */
class CustomPlan
{
    public const SETTING_KEY = 'custom_plan';
    public const CONSENT_LOG_KEY = 'custom_plan_consent_log';
    private const CONSENT_LOG_MAX = 200;

    private TenantRepository $tenants;

    public function __construct(TenantRepository $tenants)
    {
        $this->tenants = $tenants;
    }

    /**
     * Get the custom plan config for a tenant, or null when this tenant
     * has no custom plan.
     */
    public function getPlan(string $tenantKey): ?array
    {
        $plan = $this->tenants->getTenantSetting($tenantKey, self::SETTING_KEY, null);
        if (!is_array($plan) || !isset($plan['price_per_license'])) {
            return null;
        }

        $discounts = [];
        foreach ((array) ($plan['discounts'] ?? []) as $row) {
            $min = (int) ($row['min'] ?? 0);
            $pct = (float) ($row['pct'] ?? 0);
            if ($min >= 1 && $pct > 0 && $pct < 100) {
                $discounts[] = ['min' => $min, 'pct' => $pct];
            }
        }
        usort($discounts, fn($a, $b) => $a['min'] <=> $b['min']);

        return [
            'price_per_license' => max(0.0, (float) $plan['price_per_license']),
            'discounts' => $discounts,
            'max_licenses' => max(0, (int) ($plan['max_licenses'] ?? 0)),
            'initial_licenses' => max(1, (int) ($plan['initial_licenses'] ?? 1)),
            'name' => (string) ($plan['name'] ?? ''),
        ];
    }

    /**
     * Whether the tenant is on a custom plan.
     */
    public function isCustomPlan(array $tenant): bool
    {
        $tenantKey = $tenant['tenant_key'] ?? '';
        return $tenantKey !== '' && $this->getPlan($tenantKey) !== null;
    }

    /**
     * Create or update the custom plan config for a tenant.
     */
    public function savePlan(string $tenantKey, array $plan): bool
    {
        $clean = [
            'price_per_license' => max(0.0, (float) ($plan['price_per_license'] ?? 0)),
            'discounts' => array_values(array_map(function ($row) {
                return ['min' => max(1, (int) ($row['min'] ?? 0)), 'pct' => (float) ($row['pct'] ?? 0)];
            }, (array) ($plan['discounts'] ?? []))),
            'max_licenses' => max(0, (int) ($plan['max_licenses'] ?? 0)),
            'initial_licenses' => max(1, (int) ($plan['initial_licenses'] ?? 1)),
            'name' => sanitize_text_field((string) ($plan['name'] ?? '')),
        ];

        return $this->tenants->setTenantSetting($tenantKey, self::SETTING_KEY, $clean);
    }

    /**
     * Monthly amount for a given number of paid licenses, including the
     * applicable volume discount (discount applies to the whole total).
     */
    public function monthlyAmount(array $plan, int $licenseCount): float
    {
        $count = max(0, $licenseCount);
        $subtotal = $count * (float) $plan['price_per_license'];
        $discount = $this->discountFor($plan, $count);

        return round($subtotal * (1 - $discount / 100), 2);
    }

    /**
     * Discount percentage that applies at a given license count
     * (highest bracket whose minimum is reached).
     */
    public function discountFor(array $plan, int $licenseCount): float
    {
        $pct = 0.0;
        foreach ((array) ($plan['discounts'] ?? []) as $row) {
            if ($licenseCount >= (int) $row['min'] && (float) $row['pct'] > $pct) {
                $pct = (float) $row['pct'];
            }
        }
        return $pct;
    }

    /**
     * Number of paid licenses for a custom-plan tenant.
     * The linked agency account's max_sub_licenses doubles as the paid
     * license count.
     */
    public function paidLicenseCount(array $tenant): int
    {
        $account = $this->tenants->getAgencyAccountByTenant((int) ($tenant['id'] ?? 0));
        return $account ? max(0, (int) $account['max_sub_licenses']) : 0;
    }

    /**
     * Set the number of paid licenses on the linked agency account.
     */
    public function setPaidLicenseCount(array $tenant, int $count): bool
    {
        $account = $this->tenants->getAgencyAccountByTenant((int) ($tenant['id'] ?? 0));
        if (!$account) {
            return false;
        }
        return $this->tenants->updateAgencyAccount((int) $account['id'], [
            'max_sub_licenses' => max(0, $count),
        ]);
    }

    /**
     * Append a consent entry to the plan's consent log.
     */
    public function appendConsentLog(string $tenantKey, array $entry): void
    {
        $log = $this->getConsentLog($tenantKey);
        $log[] = array_merge([
            'at' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'ip' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ], $entry);

        if (count($log) > self::CONSENT_LOG_MAX) {
            $log = array_slice($log, -self::CONSENT_LOG_MAX);
        }

        $this->tenants->setTenantSetting($tenantKey, self::CONSENT_LOG_KEY, $log);
    }

    /**
     * Get the consent log for a custom-plan tenant (oldest first).
     */
    public function getConsentLog(string $tenantKey): array
    {
        $log = $this->tenants->getTenantSetting($tenantKey, self::CONSENT_LOG_KEY, []);
        return is_array($log) ? $log : [];
    }

    /**
     * List all tenants that have a custom plan configured.
     */
    public function getCustomPlanTenants(): array
    {
        $result = [];
        foreach ($this->tenants->getAllTenants(500) as $tenant) {
            $plan = $this->getPlan($tenant['tenant_key'] ?? '');
            if ($plan !== null) {
                $tenant['custom_plan'] = $plan;
                $result[] = $tenant;
            }
        }
        return $result;
    }
}

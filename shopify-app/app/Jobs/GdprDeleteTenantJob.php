<?php

namespace App\Jobs;

use App\Services\SaasProxyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GdprDeleteTenantJob
 *
 * shop/redact: deletes the shop's App Pricing tenant on the SaaS dashboard
 * (/gdpr/delete). Runs outside the webhook request; retried because Shopify
 * expects the data to be erased.
 */
class GdprDeleteTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 300;

    public function __construct(
        public string $licenseKey,
        public string $tenantKey,
        public string $shopDomain
    ) {}

    public function handle(SaasProxyClient $saas): void
    {
        $result = $saas->gdprDelete($this->licenseKey, $this->tenantKey);

        // 403 = tenant already deleted or anonymized: nothing left to erase.
        if (isset($result['error']) && ($result['status'] ?? null) !== 403) {
            throw new RuntimeException('SaaS GDPR delete failed: '.$result['error']);
        }

        Log::info('GDPR shop/redact: SaaS tenant deleted', ['shop' => $this->shopDomain]);
    }
}

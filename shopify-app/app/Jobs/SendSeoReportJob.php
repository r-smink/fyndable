<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\LicenseService;
use App\Services\ReportMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * SendSeoReportJob (1.7): weekly report for one shop (Monday scheduler) or
 * on request from the Reports page ($manual skips the "enabled" switch).
 */
class SendSeoReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 900;

    public function __construct(public int $shopId, public bool $manual = false) {}

    public function handle(ReportMailer $mailer, LicenseService $license): void
    {
        $shop = Shop::find($this->shopId);
        if (! $shop || ! $shop->is_installed || $shop->is_uninstalled || ! $license->canUse($shop, 'reports')) {
            return;
        }
        if (! $this->manual && ! $mailer->settings($shop)['enabled']) {
            return;
        }

        $result = $mailer->createAndSend($shop);
        if (is_string($result)) {
            Log::info('SendSeoReportJob: not sent', ['shop_id' => $shop->id, 'reason' => $result]);
        }
    }
}

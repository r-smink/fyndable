<?php

namespace App\Services;

use App\Mail\WeeklyReportMail;
use App\Models\SeoReport;
use App\Models\Shop;
use App\Models\ShopProfile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * ReportMailer (1.7): settings, links and sending of the weekly report.
 */
class ReportMailer
{
    /** Reports kept per shop (about half a year of weekly reports). */
    public const KEEP = 26;

    public function __construct(private ReportBuilder $builder) {}

    public function settings(Shop $shop): array
    {
        $profile = ShopProfile::where('shop_id', $shop->id)->first();

        return [
            'enabled' => (bool) ($profile?->report_enabled ?? false),
            'email' => $profile?->report_email ?: (string) $shop->shop_email,
            'language' => $this->language($shop, $profile),
        ];
    }

    public function language(Shop $shop, ?ShopProfile $profile = null): string
    {
        $profile ??= ShopProfile::where('shop_id', $shop->id)->first();
        $lang = $profile?->report_language ?: $profile?->content_language;

        return in_array($lang, ['en', 'nl'], true) ? $lang : 'en';
    }

    /**
     * Build this week's report and email it.
     *
     * @return SeoReport|string the report, or an error code
     */
    public function createAndSend(Shop $shop): SeoReport|string
    {
        $settings = $this->settings($shop);
        if (! filter_var($settings['email'], FILTER_VALIDATE_EMAIL)) {
            return 'no_email';
        }

        $report = $this->builder->create($shop, now());
        $shopName = (string) ($shop->shop_name ?: str_replace('.myshopify.com', '', $shop->shop_domain));

        Mail::to($settings['email'])->send(new WeeklyReportMail(
            $report,
            $shopName,
            $this->reportUrl($report, now()->addDays(30)),
            $this->appUrl($shop),
            $this->absolute(URL::signedRoute('report.unsubscribe', ['shop' => $shop->id], null, false)),
            $settings['language']
        ));

        $report->update(['sent_at' => now(), 'sent_to' => mb_substr($settings['email'], 0, 255)]);
        $this->prune($shop);

        return $report;
    }

    /**
     * Signed link to the printable report (opens outside the Shopify admin,
     * so it cannot use a session token).
     */
    public function reportUrl(SeoReport $report, \DateTimeInterface $expires): string
    {
        return $this->absolute(URL::temporarySignedRoute('report.show', $expires, ['report' => $report->id], false));
    }

    /**
     * Signed links are signed on path + query only (route middleware
     * signed:relative), so they keep working after a change of APP_URL.
     */
    private function absolute(string $relative): string
    {
        return rtrim((string) config('app.url'), '/').$relative;
    }

    public function appUrl(Shop $shop): string
    {
        $store = str_replace('.myshopify.com', '', $shop->shop_domain);

        return "https://admin.shopify.com/store/{$store}/apps/".config('shopify.billing.app_handle', 'fyndable-smart-seo');
    }

    private function prune(Shop $shop): void
    {
        $old = SeoReport::where('shop_id', $shop->id)->orderByDesc('id')->skip(self::KEEP)->take(100)->pluck('id');
        if ($old->isNotEmpty()) {
            SeoReport::whereIn('id', $old)->delete();
        }
    }
}

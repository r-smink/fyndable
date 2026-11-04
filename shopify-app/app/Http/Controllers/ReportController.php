<?php

namespace App\Http\Controllers;

use App\Jobs\SendSeoReportJob;
use App\Models\SeoReport;
use App\Models\Shop;
use App\Models\ShopProfile;
use App\Services\ReportMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * ReportController (1.7)
 *
 * GET  /api/reports              - settings + recent reports (with fresh signed links)
 * POST /api/reports/settings     - {enabled, email, language}
 * POST /api/reports/send         - create and email a report now (max every 10 min)
 * GET  /report/{report}          - printable report (signed URL, outside the admin)
 * GET  /report/unsubscribe/{shop} - one-click unsubscribe from the email (signed)
 */
class ReportController extends Controller
{
    public function __construct(private ReportMailer $mailer) {}

    public function index(Request $request): array
    {
        $shop = $this->shop($request);
        $reports = SeoReport::where('shop_id', $shop->id)->latest('id')->limit(12)->get()->map(fn (SeoReport $r) => [
            'id' => $r->id,
            'period_start' => $r->period_start->toDateString(),
            'period_end' => $r->period_end->toDateString(),
            'sent_at' => $r->sent_at?->toIso8601String(),
            'sent_to' => $r->sent_to,
            'url' => $this->mailer->reportUrl($r, now()->addHour()),
        ])->all();

        return ['success' => true, 'settings' => $this->mailer->settings($shop), 'reports' => $reports];
    }

    public function saveSettings(Request $request): array
    {
        $validator = validator($request->all(), [
            'enabled' => ['required', 'boolean'],
            'email' => ['nullable', 'required_if:enabled,true', 'email', 'max:255'],
            'language' => ['required', Rule::in(['en', 'nl'])],
        ]);
        if ($validator->fails()) {
            return ['error' => 'invalid_settings', 'fields' => $validator->errors()->keys()];
        }

        $data = $validator->validated();
        $profile = ShopProfile::firstOrCreate(['shop_id' => $this->shop($request)->id]);
        $profile->update(['report_enabled' => $data['enabled'], 'report_email' => $data['email'] ?? null, 'report_language' => $data['language']]);

        return ['success' => true, 'settings' => $this->mailer->settings($this->shop($request))];
    }

    public function send(Request $request): array
    {
        $shop = $this->shop($request);
        if (! filter_var($this->mailer->settings($shop)['email'], FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'no_email'];
        }

        $key = 'report_send:'.$shop->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return ['error' => 'too_soon', 'retry_after' => RateLimiter::availableIn($key)];
        }
        RateLimiter::hit($key, 600);
        SendSeoReportJob::dispatch($shop->id, true);

        return ['success' => true];
    }

    public function show(SeoReport $report)
    {
        $shop = $report->shop;
        app()->setLocale($this->mailer->language($shop));

        return response()->view('reports.show', [
            'report' => $report,
            'shopName' => (string) ($shop->shop_name ?: str_replace('.myshopify.com', '', $shop->shop_domain)),
        ], 200, ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store']);
    }

    public function unsubscribe(Shop $shop)
    {
        ShopProfile::where('shop_id', $shop->id)->update(['report_enabled' => false]);
        app()->setLocale($this->mailer->language($shop));

        return response()->view('reports.unsubscribed', [], 200, ['X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}

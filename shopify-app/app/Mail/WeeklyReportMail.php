<?php

namespace App\Mail;

use App\Models\SeoReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Weekly SEO report email (1.7). Rendered in the report language set by
 * the merchant (lang/{en,nl}/reports.php).
 */
class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SeoReport $report,
        public string $shopName,
        public string $reportUrl,
        public string $appUrl,
        public string $unsubscribeUrl,
        string $lang
    ) {
        $this->locale($lang);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('reports.subject', ['shop' => $this->shopName]));
    }

    public function content(): Content
    {
        return new Content(view: 'reports.email');
    }
}

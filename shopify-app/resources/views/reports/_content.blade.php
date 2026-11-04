{{--
    Weekly SEO report body (1.7), shared by the email and the printable page.
    Inline styles and tables for email clients. Variables: $report, $shopName.
--}}
@php
    $m = $report->metrics;
    $fmt = fn ($date) => \Illuminate\Support\Carbon::parse($date)->locale(app()->getLocale())->isoFormat('D MMM YYYY');
    $signed = fn ($n) => ($n > 0 ? '+' : '').$n;
    $h2 = 'font-size:16px;margin:28px 0 8px;color:#303030;';
    $muted = 'color:#616161;font-size:13px;';
    $big = 'font-size:26px;font-weight:700;color:#303030;';
    $cell = 'padding:6px 12px 6px 0;vertical-align:top;';
@endphp
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#303030;line-height:1.5;">
    <h1 style="font-size:22px;margin:0 0 4px;">{{ __('reports.title') }}</h1>
    <div style="{{ $muted }}">{{ $shopName }} - {{ __('reports.period', ['start' => $fmt($report->period_start), 'end' => $fmt($report->period_end)]) }}</div>
    <p style="margin:16px 0 0;">{{ __('reports.intro', ['shop' => $shopName]) }}</p>

    {{-- What Fyndable did --}}
    <h2 style="{{ $h2 }}">{{ __('reports.activity_title') }}</h2>
    @if (($m['activity']['total'] ?? 0) > 0)
        <div style="{{ $big }}">{{ __('reports.activity_total', ['count' => $m['activity']['total']]) }}</div>
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:8px;">
            @foreach ($m['activity']['counts'] as $kind => $count)
                <tr><td style="{{ $cell }}">{{ \Illuminate\Support\Facades\Lang::has('reports.kind_'.$kind) ? __('reports.kind_'.$kind) : $kind }}</td><td style="{{ $cell }}font-weight:600;">{{ $count }}</td></tr>
            @endforeach
        </table>
    @else
        <p style="{{ $muted }}">{{ __('reports.activity_none') }}</p>
    @endif

    {{-- SEO score --}}
    <h2 style="{{ $h2 }}">{{ __('reports.seo_title') }}</h2>
    @if ($m['seo'])
        <div style="{{ $big }}">{{ $m['seo']['score'] }}/100</div>
        @if ($m['seo']['previous'] !== null && $m['seo']['previous'] !== $m['seo']['score'])
            <div style="{{ $muted }}">{{ __('reports.seo_change', ['change' => $signed($m['seo']['score'] - $m['seo']['previous'])]) }}</div>
        @endif
        @if ($m['seo']['critical'] > 0)
            <div style="color:#8e1f0b;font-size:13px;">{{ __('reports.seo_critical', ['count' => $m['seo']['critical']]) }}</div>
        @endif
    @else
        <p style="{{ $muted }}">{{ __('reports.seo_none') }}</p>
    @endif

    {{-- Rankings --}}
    @if ($m['rankings'] && $m['rankings']['tracked'] > 0)
        <h2 style="{{ $h2 }}">{{ __('reports.rank_title') }}</h2>
        <table role="presentation" cellpadding="0" cellspacing="0">
            <tr>
                <td style="{{ $cell }}padding-right:28px;"><div style="{{ $muted }}">{{ __('reports.rank_tracked') }}</div><div style="{{ $big }}">{{ $m['rankings']['tracked'] }}</div></td>
                <td style="{{ $cell }}padding-right:28px;"><div style="{{ $muted }}">{{ __('reports.rank_top10') }}</div><div style="{{ $big }}">{{ $m['rankings']['top10'] }}</div></td>
                <td style="{{ $cell }}"><div style="{{ $muted }}">{{ __('reports.rank_top3') }}</div><div style="{{ $big }}">{{ $m['rankings']['top3'] }}</div></td>
            </tr>
        </table>
        <div style="{{ $muted }}">{{ __('reports.rank_moves', ['improved' => $m['rankings']['improved'], 'declined' => $m['rankings']['declined']]) }}</div>
        @if (! empty($m['rankings']['movers']))
            <div style="margin-top:8px;font-weight:600;">{{ __('reports.rank_movers') }}</div>
            <table role="presentation" cellpadding="0" cellspacing="0">
                @foreach ($m['rankings']['movers'] as $mover)
                    <tr>
                        <td style="{{ $cell }}">{{ $mover['keyword'] }}</td>
                        <td style="{{ $cell }}{{ $mover['change'] > 0 ? 'color:#0c5132;' : 'color:#8e1f0b;' }}">{{ $mover['from'] ?? __('reports.not_ranked') }} &rarr; {{ $mover['to'] ?? __('reports.not_ranked') }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endif

    {{-- Search Console --}}
    @if ($m['search_console'])
        @php($gsc = $m['search_console'])
        <h2 style="{{ $h2 }}">{{ __('reports.gsc_title') }}</h2>
        <div style="{{ $muted }}">{{ __('reports.gsc_period', ['start' => $fmt($gsc['period_start']), 'end' => $fmt($gsc['period_end'])]) }}</div>
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:6px;">
            <tr>
                <td style="{{ $cell }}padding-right:28px;"><div style="{{ $muted }}">{{ __('reports.gsc_clicks') }}</div><div style="{{ $big }}">{{ number_format($gsc['clicks']) }}</div></td>
                <td style="{{ $cell }}padding-right:28px;"><div style="{{ $muted }}">{{ __('reports.gsc_impressions') }}</div><div style="{{ $big }}">{{ number_format($gsc['impressions']) }}</div></td>
                <td style="{{ $cell }}"><div style="{{ $muted }}">{{ __('reports.gsc_position') }}</div><div style="{{ $big }}">{{ $gsc['position'] }}</div></td>
            </tr>
        </table>
        @if ($gsc['previous_clicks'] !== null && $gsc['previous_clicks'] !== $gsc['clicks'])
            <div style="{{ $muted }}">{{ __('reports.gsc_change', ['change' => $signed($gsc['clicks'] - $gsc['previous_clicks'])]) }}</div>
        @endif
        @if (! empty($gsc['opportunities']))
            <div style="margin-top:8px;font-weight:600;">{{ __('reports.gsc_opportunities') }}</div>
            <ul style="margin:4px 0 0;padding-left:18px;">
                @foreach ($gsc['opportunities'] as $o)
                    <li>{{ __('reports.gsc_opportunity', ['query' => $o['query'], 'position' => $o['position'], 'impressions' => $o['impressions']]) }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    {{-- AI visibility --}}
    @if ($m['geo'])
        <h2 style="{{ $h2 }}">{{ __('reports.geo_title') }}</h2>
        <div>{{ __('reports.geo_score', ['score' => $m['geo']['score']]) }}</div>
    @endif
</div>

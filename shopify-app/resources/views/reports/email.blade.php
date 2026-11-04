<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('reports.subject', ['shop' => $shopName]) }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f1f1;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f1f1;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;">
                    <tr>
                        <td style="padding:28px;">
                            @include('reports._content')
                            <p style="margin:32px 0 0;">
                                <a href="{{ $reportUrl }}" style="display:inline-block;background:#303030;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:8px;font-family:Arial,sans-serif;font-size:14px;">{{ __('reports.view_report') }}</a>
                                &nbsp;
                                <a href="{{ $appUrl }}" style="font-family:Arial,sans-serif;font-size:14px;color:#303030;">{{ __('reports.open_app') }}</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="max-width:600px;font-family:Arial,sans-serif;font-size:12px;color:#616161;margin:16px auto 0;">
                    {{ __('reports.footer') }}
                    <a href="{{ $unsubscribeUrl }}" style="color:#616161;">{{ __('reports.unsubscribe') }}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>

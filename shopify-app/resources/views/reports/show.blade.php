<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('reports.title') }} - {{ $shopName }}</title>
    <style>
        body { margin: 0; background: #f1f1f1; }
        .sheet { max-width: 760px; margin: 24px auto; background: #fff; border-radius: 12px; padding: 36px; }
        .actions { max-width: 760px; margin: 24px auto 0; text-align: right; }
        .actions button { font: 14px -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #303030; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; padding: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">{{ __('reports.print') }}</button></div>
    <div class="sheet">
        @include('reports._content')
    </div>
</body>
</html>

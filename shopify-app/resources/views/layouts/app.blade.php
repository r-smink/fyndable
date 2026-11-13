@php
    // Cache-busted static assets (public/css, public/js). Pages are plain
    // scripts, so the browser caches them between page loads.
    $asset = fn (string $path) => asset($path).'?v='.(@filemtime(public_path($path)) ?: '1');
    $pageUrl = fn (string $p) => ($p === 'home' ? '/dashboard' : '/dashboard/'.$p).($navQuery !== '' ? '?'.$navQuery : '');
    $pageI18n = \Illuminate\Support\Facades\Lang::has($page.'.js') ? __($page.'.js') : [];
    $pageScript = 'js/pages/'.$page.'.js';
    $fynConfig = [
        'shop' => $shopDomain,
        'apiKey' => $apiKey,
        'page' => $page,
        'feature' => $feature,
        'lang' => $lang,
        'navQuery' => $navQuery,
        // Shopify plan page, without a server round trip: the Plan page must offer plan
        // changes even when the API is unavailable (App Store review 1.2.3).
        'pricingUrl' => $shopDomain
            ? 'https://admin.shopify.com/store/'.rawurlencode(str_replace('.myshopify.com', '', $shopDomain)).'/charges/'.rawurlencode((string) config('shopify.billing.app_handle')).'/pricing_plans'
            : '',
        'tabFeatures' => config('tiers.tabs', []),
        'i18n' => array_merge(__('app.js'), is_array($pageI18n) ? $pageI18n : []),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.nav.'.$page) }} - Fyndable SEO</title>
    <meta name="shopify-api-key" content="{{ $apiKey }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <script src="https://cdn.shopify.com/shopifycloud/polaris-2.js"></script>
    <link rel="stylesheet" href="{{ $asset('css/fyndable.css') }}">
    <script>
        window.FYN = {!! json_encode($fynConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!};
    </script>
    <script src="{{ $asset('js/fyndable.js') }}" defer></script>
    {{-- Shared scripts a page needs, e.g. @php($scripts = ['onboarding']) in the page view. --}}
    @foreach ($scripts ?? [] as $extra)
        <script src="{{ $asset('js/'.trim($extra).'.js') }}" defer></script>
    @endforeach
    @if (file_exists(public_path($pageScript)))
        <script src="{{ $asset($pageScript) }}" defer></script>
    @endif
</head>
<body>
    {{-- App Bridge navigation in the Shopify admin sidebar (Built for Shopify 4.1.4). --}}
    {{-- Shopify allows one menu level: Home + the groups (first page of each group). --}}
    <s-app-nav>
        <s-link href="{{ $pageUrl('home') }}" rel="home">{{ __('app.nav.home') }}</s-link>
        @foreach ($groups as $g => $groupPages)
            <s-link href="{{ $pageUrl($groupPages[0]) }}">{{ __('app.groups.'.$g) }}</s-link>
        @endforeach
    </s-app-nav>

    <s-page heading="@yield('heading', __('app.nav.'.$page))" @hasSection('inline-size') inline-size="@yield('inline-size')" @endif>
        @yield('actions')
        @if ($group && count($groups[$group]) > 1)
            {{-- Sub navigation of the menu group. Plan-locked pages get their tier label from fyndable.js. --}}
            <s-stack id="fyn-subnav" slot="supplemental-start" direction="inline" gap="small-200" paddingBlockEnd="base" accessibilityRole="navigation" accessibilityLabel="{{ __('app.groups.'.$group) }}">
                @foreach ($groups[$group] as $p)
                    <s-button href="{{ $pageUrl($p) }}" variant="{{ $p === $page ? 'primary' : 'secondary' }}" data-feature="{{ $pageFeatures[$p] ?? '' }}" @if ($p === $page) aria-current="page" @endif>{{ __('app.nav.'.$p) }}</s-button>
                @endforeach
            </s-stack>
        @endif
        <div id="fyn-banners" slot="supplemental-start"></div>
        <s-section id="fyn-lock" hidden></s-section>
        @yield('content')
    </s-page>

    {{-- Shared confirm dialog (replaces window.confirm, Built for Shopify 4.1.6). --}}
    <s-modal id="fyn-confirm" heading="{{ __('app.js.confirm') }}" size="small">
        <s-paragraph id="fyn-confirm-text"></s-paragraph>
        <s-button slot="primary-action" variant="primary" id="fyn-confirm-ok">{{ __('app.js.confirm') }}</s-button>
        <s-button slot="secondary-actions" commandFor="fyn-confirm" command="--hide">{{ __('app.js.cancel') }}</s-button>
    </s-modal>

    @stack('modals')
</body>
</html>

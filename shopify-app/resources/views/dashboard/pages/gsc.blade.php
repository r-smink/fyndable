@extends('layouts.app')

@section('actions')
    <s-button slot="secondary-actions" id="gsc-sync-btn" onclick="syncGsc(this)" hidden>{{ __('gsc.sync') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('gsc.connect_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('gsc.intro') }}</s-paragraph>
            <div id="gsc-status"><s-spinner></s-spinner></div>
            <s-stack gap="base" id="gsc-site-box" hidden>
                <s-select id="gsc-site" label="{{ __('gsc.site') }}" details="{{ __('gsc.site_details') }}"></s-select>
                <s-stack direction="inline">
                    <s-button variant="primary" onclick="saveGscSite(this)">{{ __('gsc.save_site') }}</s-button>
                </s-stack>
            </s-stack>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" id="gsc-connect-btn" onclick="connectGsc(this)" hidden>{{ __('gsc.connect') }}</s-button>
                <s-button tone="critical" id="gsc-disconnect-btn" onclick="disconnectGsc(this)" hidden>{{ __('gsc.disconnect') }}</s-button>
            </s-stack>
            <s-text color="subdued">{{ __('gsc.privacy') }}</s-text>
        </s-stack>
    </s-section>

    <s-section id="gsc-stats-section" hidden>
        <s-stack gap="small-200">
            <s-text color="subdued" id="gsc-period"></s-text>
            <div id="gsc-stats"></div>
        </s-stack>
    </s-section>

    <s-section id="gsc-opportunities-section" heading="{{ __('gsc.opportunities_title') }}" hidden>
        <s-stack gap="base">
            <s-paragraph>{{ __('gsc.opportunities_intro') }}</s-paragraph>
            <div id="gsc-opportunities"></div>
        </s-stack>
    </s-section>

    <s-section id="gsc-pages-section" heading="{{ __('gsc.pages_title') }}" hidden>
        <div id="gsc-pages"></div>
    </s-section>
@endsection

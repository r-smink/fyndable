@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="Fyn.busy(this, loadMarketsAudit)">{{ __('markets.run_audit') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('markets.intro') }}</s-paragraph>
            <s-banner tone="info">
                {{ __('markets.translate_hint') }}
                <s-button slot="secondary-actions" href="/dashboard/translations{{ $navQuery !== '' ? '?'.$navQuery : '' }}">{{ __('markets.translate_open') }}</s-button>
            </s-banner>
        </s-stack>
    </s-section>

    <s-section>
        <div id="markets-stats"><s-spinner></s-spinner></div>
    </s-section>

    <s-section heading="{{ __('markets.checks') }}">
        <div id="markets-checks"><s-spinner></s-spinner></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('markets.recommendations') }}">
        <div id="markets-recommendations"><s-spinner></s-spinner></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('markets.overview') }}">
        <div id="markets-overview"><s-spinner></s-spinner></div>
    </s-section>
@endsection

@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="Fyn.busy(this, loadMarketsAudit)">{{ __('markets.run_audit') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-paragraph>{{ __('markets.intro') }}</s-paragraph>
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

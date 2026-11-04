@extends('layouts.app')
@php($scripts = ['onboarding'])

@section('heading', __('app.home.title'))

@section('actions')
    <s-button slot="secondary-actions" onclick="Fyn.busy(this, loadOverview)">{{ __('app.home.refresh') }}</s-button>
@endsection

@section('content')
    {{-- Setup card until onboarding is completed or skipped (Built for Shopify 4.2.2). --}}
    <s-section id="onb-card" hidden>
        <s-stack gap="base">
            <s-heading>{{ __('app.js.onb_card_title') }}</s-heading>
            <s-paragraph>{{ __('app.js.onb_card_text') }}</s-paragraph>
            <s-stack direction="inline" gap="base">
                <s-button variant="primary" onclick="FynOnboarding.open()">{{ __('app.js.onb_card_start') }}</s-button>
                <s-button onclick="FynOnboarding.skip()">{{ __('app.js.onb_skip') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <div id="home-alerts"></div>

    <s-section>
        <div id="home-stats">
            <s-spinner></s-spinner>
        </div>
    </s-section>

    <s-section heading="{{ __('app.home.status') }}">
        <div id="home-status"></div>
    </s-section>

    <s-section slot="aside" heading="{{ __('app.home.quick_actions') }}">
        <s-stack gap="base">
            @foreach ([['seoaudit', 'qa_audit'], ['bulk', 'qa_bulk'], ['llmstxt', 'qa_llms'], ['ranktracker', 'qa_rank'], ['blogwriter', 'qa_blog']] as [$target, $key])
                <s-stack gap="small-500">
                    <s-link href="/dashboard/{{ $target }}{{ $navQuery !== '' ? '?'.$navQuery : '' }}">{{ __('app.home.'.$key) }}</s-link>
                    <s-text color="subdued">{{ __('app.home.'.$key.'_sub') }}</s-text>
                </s-stack>
            @endforeach
        </s-stack>
    </s-section>
@endsection

@push('modals')
    @include('dashboard.partials.onboarding')
@endpush

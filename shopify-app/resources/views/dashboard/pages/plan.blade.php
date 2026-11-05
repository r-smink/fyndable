@extends('layouts.app')

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('app.plan.intro') }}</s-paragraph>
            <div id="billing-card">
                <s-spinner></s-spinner>
            </div>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('app.plan.modules') }}">
        <s-unordered-list>
            @foreach (['content', 'bulk', 'blogwriter', 'calendar', 'ranktracker', 'kwexplorer', 'competitor', 'backlinks', 'markets', 'aivisibility', 'geoscan', 'aitracker', 'llmstxt', 'tools'] as $module)
                <s-list-item>{{ __('app.nav.'.$module) }}</s-list-item>
            @endforeach
        </s-unordered-list>
    </s-section>
@endsection

@extends('layouts.app')

@section('actions')
    <s-button slot="secondary-actions" onclick="sendReportNow(this)">{{ __('reports.send_now') }}</s-button>
@endsection

@section('content')
    <s-section heading="{{ __('reports.settings_title') }}">
        <s-stack gap="base">
            <s-checkbox id="rep-enabled" label="{{ __('reports.enabled') }}"></s-checkbox>
            <s-email-field id="rep-email" label="{{ __('reports.email') }}" details="{{ __('reports.email_details') }}"></s-email-field>
            <s-select id="rep-language" label="{{ __('reports.language') }}">
                <s-option value="en">English</s-option>
                <s-option value="nl">Nederlands</s-option>
            </s-select>
            <s-stack direction="inline">
                <s-button variant="primary" onclick="saveReportSettings(this)">{{ __('reports.save') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('reports.history_title') }}">
        <div id="rep-list"><s-spinner></s-spinner></div>
    </s-section>
@endsection

@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" onclick="saveSchema(this)">{{ __('schema.save') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('schema.intro') }}</s-paragraph>
            <div id="schema-embed"></div>
            <div id="schema-status"></div>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('schema.types_title') }}">
        <s-stack gap="base">
            @foreach (\App\Services\StoreSchemaService::FLAGS as $flag)
                <s-checkbox id="flag-{{ $flag }}" label="{{ __('schema.flag_'.$flag) }}"></s-checkbox>
            @endforeach
            <div id="local-locked"></div>
            <s-text color="subdued">{{ __('schema.item_note') }}</s-text>
        </s-stack>
    </s-section>

    <s-section heading="{{ __('schema.org_title') }}">
        <s-stack gap="base">
            <s-text-field id="org-name" label="{{ __('schema.org_name') }}" details="{{ __('schema.org_name_details') }}" maxLength="255"></s-text-field>
            <s-url-field id="org-logo" label="{{ __('schema.org_logo') }}" details="{{ __('schema.org_logo_details') }}"></s-url-field>
            <s-text-area id="org-description" label="{{ __('schema.org_description') }}" rows="2" maxLength="1000"></s-text-area>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-email-field id="org-email" label="{{ __('schema.org_email') }}"></s-email-field>
                <s-text-field id="org-phone" label="{{ __('schema.org_phone') }}" maxLength="50"></s-text-field>
            </s-grid>
            <s-text-area id="org-same-as" label="{{ __('schema.org_same_as') }}" details="{{ __('schema.org_same_as_details') }}" rows="3"></s-text-area>
        </s-stack>
    </s-section>

    <s-section id="local-section" heading="{{ __('schema.local_title') }}">
        <s-stack gap="base">
            <s-select id="lb-type" label="{{ __('schema.local_type') }}">
                @foreach (\App\Services\StoreSchemaService::BUSINESS_TYPES as $type)
                    <s-option value="{{ $type }}">{{ $type }}</s-option>
                @endforeach
            </s-select>
            <s-text-field id="lb-street" label="{{ __('schema.local_street') }}" maxLength="255"></s-text-field>
            <s-grid gridTemplateColumns="1fr 2fr" gap="base">
                <s-text-field id="lb-postal" label="{{ __('schema.local_postal') }}" maxLength="20"></s-text-field>
                <s-text-field id="lb-city" label="{{ __('schema.local_city') }}" maxLength="120"></s-text-field>
            </s-grid>
            <s-grid gridTemplateColumns="2fr 1fr" gap="base">
                <s-text-field id="lb-region" label="{{ __('schema.local_region') }}" maxLength="120"></s-text-field>
                <s-text-field id="lb-country" label="{{ __('schema.local_country') }}" details="{{ __('schema.local_country_details') }}" maxLength="2"></s-text-field>
            </s-grid>
            <s-text-field id="lb-phone" label="{{ __('schema.local_phone') }}" maxLength="50"></s-text-field>
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-text-field id="lb-lat" label="{{ __('schema.local_lat') }}"></s-text-field>
                <s-text-field id="lb-lng" label="{{ __('schema.local_lng') }}"></s-text-field>
            </s-grid>
            <s-text-area id="lb-hours" label="{{ __('schema.local_hours') }}" details="{{ __('schema.local_hours_details') }}" rows="3"></s-text-area>
            <s-text-field id="lb-price" label="{{ __('schema.local_price') }}" details="{{ __('schema.local_price_details') }}" maxLength="20"></s-text-field>
        </s-stack>
    </s-section>

    <s-section slot="aside" heading="{{ __('schema.theme_title') }}">
        <s-stack gap="base">
            <s-paragraph>{{ __('schema.theme_intro') }}</s-paragraph>
            <s-stack direction="inline">
                <s-button onclick="runThemeCheck(this)">{{ __('schema.theme_run') }}</s-button>
            </s-stack>
            <div id="theme-check"></div>
        </s-stack>
    </s-section>
@endsection

@extends('layouts.app')

@section('actions')
    <s-button slot="primary-action" variant="primary" id="audit-run" onclick="startAudit(this)">{{ __('seoaudit.run') }}</s-button>
@endsection

@section('content')
    <s-section>
        <s-stack gap="base">
            <s-paragraph>{{ __('seoaudit.intro') }}</s-paragraph>
            <div id="audit-status"><s-spinner></s-spinner></div>
        </s-stack>
    </s-section>

    <s-section id="audit-stats-section" hidden>
        <div id="audit-stats"></div>
    </s-section>

    <s-section id="audit-issues-section" heading="{{ __('seoaudit.issues_title') }}" hidden>
        <div id="audit-issues"></div>
    </s-section>

    <s-section id="audit-items-section" heading="{{ __('seoaudit.items_title') }}" hidden>
        <s-stack gap="base">
            <s-grid gridTemplateColumns="1fr 1fr" gap="base">
                <s-select id="audit-filter-type" label="{{ __('seoaudit.filter_type') }}" onchange="loadItems(1)"></s-select>
                <s-select id="audit-filter-issue" label="{{ __('seoaudit.filter_issue') }}" onchange="loadItems(1)"></s-select>
            </s-grid>
            <div id="audit-items"></div>
            <s-stack direction="inline" gap="base" alignItems="center" id="audit-pager" hidden>
                <s-button id="audit-prev" onclick="loadItems(auditPage - 1)">{{ __('seoaudit.js.prev') }}</s-button>
                <s-text color="subdued" id="audit-page-label"></s-text>
                <s-button id="audit-next" onclick="loadItems(auditPage + 1)">{{ __('seoaudit.js.next') }}</s-button>
            </s-stack>
        </s-stack>
    </s-section>
@endsection

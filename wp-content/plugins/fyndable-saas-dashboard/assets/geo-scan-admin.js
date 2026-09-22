(function ($) {
    'use strict';

    $(function () {
        var $form = $('#sseo-geo-scan-form');
        var $submit = $('#sseo-geo-scan-submit');
        var $error = $('#sseo-geo-scan-error');
        var $progress = $('#sseo-geo-progress');
        var $fill = $progress.find('.sseo-geo-progress-fill');
        var $pct = $progress.find('.sseo-geo-progress-pct');
        var $label = $progress.find('.sseo-geo-progress-label');

        if (!$form.length) {
            return;
        }

        var pollTimer = null;
        var saasShell = window.location.search.indexOf('saas_shell=1') !== -1 ? '1' : '';

        function setProgress(pct, label) {
            pct = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
            $fill.css('width', pct + '%');
            $pct.text(pct + '%');
            $label.text(label || '');
        }

        function resetUI() {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = null;
            }
            $submit.prop('disabled', false);
            $progress.hide();
        }

        function fail(msg) {
            $error.text(msg || sseoGeoScan.strings.error).show();
            resetUI();
        }

        function pollStatus(scanId) {
            $.ajax({
                url: sseoGeoScan.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sseo_geo_scan_status',
                    nonce: sseoGeoScan.nonce,
                    scan_id: scanId,
                    saas_shell: saasShell
                }
            })
            .done(function (response) {
                if (!response.success || !response.data) {
                    fail((response && response.data) || sseoGeoScan.strings.error);
                    return;
                }

                var data = response.data;
                setProgress(data.progress, data.progress_label);

                if (data.status === 'completed' && data.redirect) {
                    setProgress(100, sseoGeoScan.strings.completed);
                    window.location.href = data.redirect;
                    return;
                }

                if (data.status === 'failed') {
                    fail(data.error || sseoGeoScan.strings.error);
                    return;
                }

                // queued/running — keep polling
                pollTimer = setTimeout(function () { pollStatus(scanId); }, 2500);
            })
            .fail(function () {
                // Transient network hiccup — keep polling a few times rather than dying.
                pollTimer = setTimeout(function () { pollStatus(scanId); }, 5000);
            });
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            $error.hide().empty();
            $submit.prop('disabled', true);
            $progress.show();
            setProgress(0, sseoGeoScan.strings.queued);

            var keywords = $('#sseo_geo_keywords')
                .val()
                .split('\n')
                .map(function (k) { return k.trim(); })
                .filter(function (k) { return k.length > 0; });

            if (keywords.length === 0 || keywords.length > 10) {
                fail('Please enter between 1 and 10 keywords.');
                return;
            }

            $.ajax({
                url: sseoGeoScan.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sseo_geo_scan_run',
                    nonce: sseoGeoScan.nonce,
                    url: $('#sseo_geo_url').val().trim(),
                    keywords: $('#sseo_geo_keywords').val().trim(),
                    language: $('#sseo_geo_language').val(),
                    saas_shell: saasShell
                }
            })
            .done(function (response) {
                if (response.success && response.data && response.data.scan_id) {
                    pollStatus(response.data.scan_id);
                } else {
                    fail((response && response.data) || sseoGeoScan.strings.error);
                }
            })
            .fail(function () {
                fail(sseoGeoScan.strings.error);
            });
        });
    });
})(jQuery);

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

    /* ===== Multi-model pill select ===== */
    $(function () {
        var $dropdown = $('#sseo_geo_multi_add');
        var $container = $('#sseo_geo_pill_container');
        var $hiddenWrap = $('#sseo_geo_multi_hidden_inputs');
        var $countText = $('.sseo-geo-pill-count');

        if (!$dropdown.length) {
            return;
        }

        // Build initial pills from the hidden inputs already in the form.
        $hiddenWrap.find('input[name="multi_models[]"]').each(function () {
            var value = $(this).val();
            var label = '';
            $dropdown.find('option').each(function () {
                if ($(this).val() === value) { label = $(this).text(); return false; }
            });
            if (label) {
                addPill(value, label, true);
            }
        });
        hideSelectedOptions();

        $dropdown.on('change', function () {
            var value = $(this).val();
            if (!value) return;
            var label = $(this).find('option:selected').text();
            addPill(value, label, false);
            $(this).val('');
            hideSelectedOptions();
            updateCount();
        });

        function addPill(value, label, skipHidden) {
            // Avoid duplicates.
            var exists = false;
            $container.find('.sseo-geo-pill').each(function () {
                if ($(this).attr('data-model') === value) exists = true;
            });
            if (exists) return;

            var $pill = $('<span class="sseo-geo-pill"></span>').attr('data-model', value).text(label);
            var $btn = $('<button type="button" class="sseo-geo-pill-remove" title="Verwijderen">&times;</button>');
            $btn.on('click', function () {
                $pill.remove();
                $hiddenWrap.find('input').filter(function () { return $(this).val() === value; }).remove();
                hideSelectedOptions();
                updateCount();
            });
            $pill.append($btn);
            $container.append($pill);

            if (!skipHidden) {
                $hiddenWrap.append($('<input type="hidden" name="multi_models[]">').val(value));
            }
        }

        function hideSelectedOptions() {
            var selected = [];
            $hiddenWrap.find('input[name="multi_models[]"]').each(function () {
                selected.push($(this).val());
            });
            $dropdown.find('option').each(function () {
                var val = $(this).val();
                if (!val) return; // keep the placeholder
                $(this).prop('disabled', selected.indexOf(val) !== -1);
            });
        }

        function updateCount() {
            var count = $hiddenWrap.find('input[name="multi_models[]"]').length;
            var multiplier = Math.max(1, count);
            $countText.html(
                'Momenteel ' + count + ' model(len) geselecteerd. Kosten per scan: ~' + multiplier + 'x de normale prijs.'
            );
        }
    });
})(jQuery);

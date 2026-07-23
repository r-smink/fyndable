(function ($) {
    'use strict';

    $(function () {
        var $form = $('#sseo-geo-scan-form');
        var $submit = $('#sseo-geo-scan-submit');
        var $spinner = $('.sseo-geo-spinner');
        var $error = $('#sseo-geo-scan-error');

        if (!$form.length) {
            return;
        }

        $form.on('submit', function (e) {
            e.preventDefault();

            $error.hide().empty();
            $submit.prop('disabled', true);
            $spinner.show();

            var keywords = $('#sseo_geo_keywords')
                .val()
                .split('\n')
                .map(function (k) { return k.trim(); })
                .filter(function (k) { return k.length > 0; });

            if (keywords.length === 0 || keywords.length > 10) {
                $error.text('Please enter between 1 and 10 keywords.').show();
                $submit.prop('disabled', false);
                $spinner.hide();
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
                    language: $('#sseo_geo_language').val()
                }
            })
            .done(function (response) {
                if (response.success && response.data && response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else {
                    $error.text(response.data || sseoGeoScan.strings.error).show();
                    $submit.prop('disabled', false);
                    $spinner.hide();
                }
            })
            .fail(function () {
                $error.text(sseoGeoScan.strings.error).show();
                $submit.prop('disabled', false);
                $spinner.hide();
            });
        });
    });
})(jQuery);

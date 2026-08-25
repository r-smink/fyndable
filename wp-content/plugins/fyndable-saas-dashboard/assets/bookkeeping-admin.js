(function ($) {
    'use strict';

    $(function () {
        // Media uploader for invoice template (logo + background).
        $(document).on('click', '.sseo-ai-bk-media-upload', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var targetId = $btn.data('target');
            var previewId = $btn.data('preview');

            var frame = wp.media({
                title: 'Choose image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#' + targetId).val(attachment.id);
                var $preview = $('#' + previewId);
                $preview.empty();
                if (attachment.url) {
                    $preview.append('<img src="' + attachment.url + '" alt="">');
                }
            });

            frame.open();
        });

        $(document).on('click', '.sseo-ai-bk-media-remove', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var targetId = $btn.data('target');
            var previewId = $btn.data('preview');
            $('#' + targetId).val('');
            $('#' + previewId).empty();
        });

        // Refresh preview iframe after saving template (detect settings-saved query arg).
        var params = new URLSearchParams(window.location.search);
        if (params.get('settings-updated') === 'true' && params.get('tab') === 'template') {
            var $frame = $('#sseo-ai-bk-preview-frame');
            if ($frame.length) {
                $frame[0].src = $frame[0].src; // reload
            }
        }
    });
})(jQuery);

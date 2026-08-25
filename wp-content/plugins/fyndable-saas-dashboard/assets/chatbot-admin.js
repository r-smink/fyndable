(function ($) {
    'use strict';

    $(function () {
        var l10n = window.ChatbotAdmin || {};

        // WordPress media uploader for avatar
        $('#sseo-ai-chatbot-pick-avatar').on('click', function (e) {
            e.preventDefault();

            var frame = wp.media({
                title: l10n.avatarModalTitle || 'Select avatar',
                button: { text: l10n.avatarModalButton || 'Use as avatar' },
                library: { type: 'image' },
                multiple: false,
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.url;
                $('#sseo-ai-chatbot-avatar-url').val(url).trigger('change');
                updateAvatarPreview(url);
            });

            frame.open();
        });

        // Live avatar preview when URL field changes
        $('#sseo-ai-chatbot-avatar-url').on('input change', function () {
            updateAvatarPreview($(this).val());
        });

        function updateAvatarPreview(url) {
            var $img = $('#sseo-ai-chatbot-avatar-img');
            var $placeholder = $('#sseo-ai-chatbot-avatar-placeholder');
            if (url) {
                if ($img.length) {
                    $img.attr('src', url);
                } else {
                    $placeholder.replaceWith('<img src="' + escapeAttr(url) + '" alt="" id="sseo-ai-chatbot-avatar-img">');
                }
            } else {
                $img.remove();
                $('.sseo-ai-chatbot-avatar-preview').html('<div class="sseo-ai-chatbot-avatar-placeholder" id="sseo-ai-chatbot-avatar-placeholder">?</div>');
            }
        }

        function escapeAttr(s) {
            return String(s).replace(/"/g, '&quot;');
        }
    });
})(jQuery);

<?php

namespace SSEOAIClient;

/**
 * Review Prompt
 *
 * Shows a "Rate Fyndable" admin notice after the plugin has been
 * active for 7 days. Dismissible via AJAX.
 */
class ReviewPrompt
{
    private const ACTIVATION_OPTION = 'sseo_ai_client_first_activation';
    private const DISMISSED_OPTION  = 'sseo_ai_client_review_dismissed';

    public function register(): void
    {
        add_action('admin_notices', [$this, 'maybeShowNotice']);
        add_action('wp_ajax_sseo_ai_dismiss_review', [$this, 'ajaxDismiss']);
    }

    /**
     * Store activation timestamp on plugin activation.
     */
    public function onActivate(): void
    {
        if (!get_option(self::ACTIVATION_OPTION)) {
            update_option(self::ACTIVATION_OPTION, time());
        }
    }

    /**
     * Show the review notice if 7 days have passed and not dismissed.
     */
    public function maybeShowNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (get_option(self::DISMISSED_OPTION)) {
            return;
        }

        $firstActivation = (int) get_option(self::ACTIVATION_OPTION, 0);
        if (!$firstActivation) {
            return;
        }

        $daysActive = (time() - $firstActivation) / DAY_IN_SECONDS;
        if ($daysActive < 7) {
            return;
        }

        $currentScreen = get_current_screen();
        if ($currentScreen && strpos($currentScreen->id, 'ai-seo') === false) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible sseo-ai-review-notice" style="border-left-color: #3b82f6;">
            <p style="font-size: 14px; margin: 12px 0;">
                <?php
                printf(
                    /* translators: %s: plugin name */
                    esc_html__('You\'ve been using %s for a while now. If it\'s helping your SEO, would you mind leaving a review?', 'ai-seo-client'),
                    '<strong>Fyndable</strong>'
                );
                ?>
            </p>
            <p style="margin-bottom: 12px;">
                <a href="https://wordpress.org/plugins/fyndable/#reviews" target="_blank" rel="noopener" class="button button-primary" style="margin-right: 8px;">
                    <?php esc_html_e('Leave a Review', 'ai-seo-client'); ?>
                </a>
                <a href="#" class="button sseo-ai-review-later" style="margin-right: 8px;">
                    <?php esc_html_e('Maybe Later', 'ai-seo-client'); ?>
                </a>
                <a href="#" class="sseo-ai-review-dismiss" style="color: #6b7280; text-decoration: none; line-height: 32px;">
                    <?php esc_html_e('Already Reviewed', 'ai-seo-client'); ?>
                </a>
            </p>
        </div>
        <script>
        (function() {
            var notice = document.querySelector('.sseo-ai-review-notice');
            if (!notice) return;

            function dismiss() {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=sseo_ai_dismiss_review&_wpnonce=' + (window.sseoReviewNonce || '')
                }).then(function() {
                    notice.style.display = 'none';
                });
            }

            notice.addEventListener('click', function(e) {
                if (e.target.classList.contains('sseo-ai-review-dismiss') || e.target.classList.contains('sseo-ai-review-later')) {
                    e.preventDefault();
                    dismiss();
                }
                if (e.target.classList.contains('notice-dismiss')) {
                    dismiss();
                }
            });
        })();
        </script>
        <?php
        wp_nonce_field('sseo_ai_review_nonce', 'sseo_review_nonce');
        echo '<script>window.sseoReviewNonce = "' . esc_js(wp_create_nonce('sseo_ai_review_nonce')) . '";</script>';
    }

    /**
     * AJAX handler to dismiss the review notice.
     */
    public function ajaxDismiss(): void
    {
        check_ajax_referer('sseo_ai_review_nonce', '_wpnonce');
        update_option(self::DISMISSED_OPTION, '1');
        wp_die('ok');
    }
}

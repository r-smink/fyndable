<?php
/**
 * Frontend widget rendering and assets.
 *
 * @package Fynn
 */

namespace Fynn;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_footer', [$this, 'renderWidget']);
    }

    public function enqueueAssets(): void {
        if (!$this->isEnabled()) {
            return;
        }

        wp_enqueue_style(
            'fyndable-fynn',
            FYNN_PLUGIN_URL . 'assets/fynn-chat.css',
            [],
            FYNN_VERSION
        );

        wp_enqueue_script(
            'fyndable-fynn',
            FYNN_PLUGIN_URL . 'assets/fynn-chat.js',
            [],
            FYNN_VERSION,
            true
        );

        wp_localize_script('fyndable-fynn', 'fynnConfig', [
            'restUrl' => rest_url('sseo-ai/v1/fynn'),
            'assetsUrl' => FYNN_PLUGIN_URL . 'assets/',
        ]);
    }

    public function renderWidget(): void {
        if (!$this->isEnabled()) {
            return;
        }

        $buttonLabel = esc_attr__('Open Fynn chat', 'fyndable-fynn');
        $closeLabel = esc_attr__('Sluit chat', 'fyndable-fynn');
        ?>
        <div id="fynn-chat-root" role="region" aria-label="<?php echo esc_attr__('Fynn chat', 'fyndable-fynn'); ?>">
            <button id="fynn-fab" class="fynn-fab" aria-label="<?php echo $buttonLabel; ?>">
                <span class="fynn-avatar" data-pose="idle" aria-hidden="true"></span>
            </button>
            <div id="fynn-panel" class="fynn-panel fynn-hidden" aria-hidden="true">
                <div class="fynn-header">
                    <span class="fynn-avatar" data-pose="wave" aria-hidden="true"></span>
                    <div class="fynn-header-info">
                        <div class="fynn-name">Fynn</div>
                        <div class="fynn-status"><?php esc_html_e('Klaar om te helpen', 'fyndable-fynn'); ?></div>
                    </div>
                    <button id="fynn-close" class="fynn-close" aria-label="<?php echo $closeLabel; ?>">×</button>
                </div>
                <div id="fynn-messages" class="fynn-messages"></div>
                <div id="fynn-suggestions" class="fynn-suggestions"></div>
                <div class="fynn-footer">
                    <input type="text" id="fynn-input" class="fynn-input" placeholder="<?php esc_attr_e('Typ je vraag...', 'fyndable-fynn'); ?>" autocomplete="off">
                    <button id="fynn-send" class="fynn-send" aria-label="<?php esc_attr_e('Verstuur', 'fyndable-fynn'); ?>">➤</button>
                </div>
            </div>
        </div>
        <?php
    }

    private function isEnabled(): bool {
        return (bool) get_option('sseo_ai_fynn_frontend_enabled', 1);
    }
}

<?php
/**
 * Plugin settings and encrypted key storage.
 *
 * @package Fynn
 */

namespace Fynn;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {

    private const OPTION_KEY = 'sseo_ai_fynn_openrouter_key';
    private const OPTION_MODEL = 'sseo_ai_fynn_model';
    private const OPTION_TEMP = 'sseo_ai_fynn_temperature';
    private const OPTION_MAX_TOKENS = 'sseo_ai_fynn_max_tokens';
    private const OPTION_FRONTEND = 'sseo_ai_fynn_frontend_enabled';
    private const OPTION_SUPPORT_EMAIL = 'sseo_ai_fynn_support_email';
    private const OPTION_RATE_LIMIT = 'sseo_ai_fynn_rate_limit';

    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addAdminMenu(): void {
        add_options_page(
            __('Fynn instellingen', 'fyndable-fynn'),
            'Fynn',
            'manage_options',
            'fyndable-fynn',
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void {
        register_setting('fyndable_fynn', self::OPTION_KEY, ['sanitize_callback' => [self::class, 'encrypt']]);
        register_setting('fyndable_fynn', self::OPTION_MODEL, ['sanitize_callback' => 'sanitize_text_field', 'default' => 'openai/gpt-4o-mini']);
        register_setting('fyndable_fynn', self::OPTION_TEMP, ['sanitize_callback' => 'floatval', 'default' => 0.7]);
        register_setting('fyndable_fynn', self::OPTION_MAX_TOKENS, ['sanitize_callback' => 'intval', 'default' => 1000]);
        register_setting('fyndable_fynn', self::OPTION_FRONTEND, ['sanitize_callback' => 'intval', 'default' => 1]);
        register_setting('fyndable_fynn', self::OPTION_SUPPORT_EMAIL, ['sanitize_callback' => 'sanitize_email']);
        register_setting('fyndable_fynn', self::OPTION_RATE_LIMIT, ['sanitize_callback' => 'intval', 'default' => 20]);
    }

    public function renderPage(): void {
        $key = self::decrypt(get_option(self::OPTION_KEY, ''));
        $model = get_option(self::OPTION_MODEL, 'openai/gpt-4o-mini');
        $temperature = (float) get_option(self::OPTION_TEMP, 0.7);
        $maxTokens = (int) get_option(self::OPTION_MAX_TOKENS, 1000);
        $frontendEnabled = (int) get_option(self::OPTION_FRONTEND, 1);
        $supportEmail = get_option(self::OPTION_SUPPORT_EMAIL, '');
        $rateLimit = (int) get_option(self::OPTION_RATE_LIMIT, 20);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('fyndable_fynn'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_KEY); ?>"><?php esc_html_e('OpenRouter API-key', 'fyndable-fynn'); ?></label></th>
                        <td>
                            <input id="<?php echo esc_attr(self::OPTION_KEY); ?>" name="<?php echo esc_attr(self::OPTION_KEY); ?>" type="password" value="<?php echo esc_attr($key); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Wordt versleuteld opgeslagen.', 'fyndable-fynn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_MODEL); ?>"><?php esc_html_e('Model', 'fyndable-fynn'); ?></label></th>
                        <td>
                            <input id="<?php echo esc_attr(self::OPTION_MODEL); ?>" name="<?php echo esc_attr(self::OPTION_MODEL); ?>" type="text" value="<?php echo esc_attr($model); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_TEMP); ?>"><?php esc_html_e('Temperature', 'fyndable-fynn'); ?></label></th>
                        <td>
                            <input id="<?php echo esc_attr(self::OPTION_TEMP); ?>" name="<?php echo esc_attr(self::OPTION_TEMP); ?>" type="number" step="0.1" min="0" max="2" value="<?php echo esc_attr($temperature); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_MAX_TOKENS); ?>"><?php esc_html_e('Max tokens', 'fyndable-fynn'); ?></label></th>
                        <td>
                            <input id="<?php echo esc_attr(self::OPTION_MAX_TOKENS); ?>" name="<?php echo esc_attr(self::OPTION_MAX_TOKENS); ?>" type="number" min="50" max="8000" value="<?php echo esc_attr($maxTokens); ?>" class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_RATE_LIMIT); ?>"><?php esc_html_e('Rate limit', 'fyndable-fynn'); ?></label></th>
                        <td>
                            <input id="<?php echo esc_attr(self::OPTION_RATE_LIMIT); ?>" name="<?php echo esc_attr(self::OPTION_RATE_LIMIT); ?>" type="number" min="1" value="<?php echo esc_attr($rateLimit); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Aantal calls per uur per IP voor het frontend widget.', 'fyndable-fynn'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(self::OPTION_SUPPORT_EMAIL); ?>"><?php esc_html_e('Support e-mailadres', 'fyndable-fynn'); ?></label></th>
                        <td>
                            <input id="<?php echo esc_attr(self::OPTION_SUPPORT_EMAIL); ?>" name="<?php echo esc_attr(self::OPTION_SUPPORT_EMAIL); ?>" type="email" value="<?php echo esc_attr($supportEmail); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Frontend widget', 'fyndable-fynn'); ?></th>
                        <td>
                            <label for="<?php echo esc_attr(self::OPTION_FRONTEND); ?>">
                                <input id="<?php echo esc_attr(self::OPTION_FRONTEND); ?>" name="<?php echo esc_attr(self::OPTION_FRONTEND); ?>" type="checkbox" value="1" <?php checked($frontendEnabled, 1); ?>>
                                <?php esc_html_e('Fynn tonen op de publieke website', 'fyndable-fynn'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function getOpenRouterKey(): string {
        return self::decrypt(get_option(self::OPTION_KEY, ''));
    }

    public static function encrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_random_pseudo_bytes')) {
            return $value;
        }
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return $value;
        }
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        if (!function_exists('openssl_decrypt')) {
            return $value;
        }
        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) <= 16) {
            return $value;
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = self::getEncryptionKey();
        $decrypted = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return ($decrypted === false) ? $value : $decrypted;
    }

    private static function getEncryptionKey(): string {
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'fyndable-fynn-default-key');
        return substr(hash('sha256', $salt, true), 0, 32);
    }
}

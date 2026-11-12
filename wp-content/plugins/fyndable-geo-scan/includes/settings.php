<?php

namespace FyndableGeoScan;

/**
 * Plugin settings: portal URL + shared API key.
 * Lives under Settings → GEO Scan.
 */
class Settings
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenu(): void
    {
        add_options_page(
            __('GEO Scan', 'fyndable-geo-scan'),
            __('GEO Scan', 'fyndable-geo-scan'),
            'manage_options',
            'fyndable-geo-scan',
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting('fyndable_geoscan', FYNDABLE_GEOSCAN_PORTAL_URL_OPTION, [
            'type' => 'string',
            'sanitize_callback' => fn($v) => esc_url_raw(trim((string)$v)),
            'default' => 'https://portal.fyndable.ai',
        ]);
        register_setting('fyndable_geoscan', FYNDABLE_GEOSCAN_API_KEY_OPTION, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]);
        register_setting('fyndable_geoscan', FYNDABLE_GEOSCAN_NOTIFY_ENABLED_OPTION, [
            'type' => 'boolean',
            'sanitize_callback' => fn($v) => !empty($v),
            'default' => true,
        ]);
        register_setting('fyndable_geoscan', FYNDABLE_GEOSCAN_NOTIFY_EMAIL_OPTION, [
            'type' => 'string',
            'sanitize_callback' => [Notifier::class, 'sanitizeRecipients'],
            'default' => '',
        ]);
    }

    public function render(): void
    {
        $portalUrl = self::portalUrl();
        $apiKey = self::apiKey();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Fyndable GEO Scan', 'fyndable-geo-scan'); ?></h1>
            <p><?php esc_html_e('Verbindt het gratis GEO-scan formulier op deze site met het Fyndable SaaS-portaal.', 'fyndable-geo-scan'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('fyndable_geoscan'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="fyndable_geoscan_portal_url"><?php esc_html_e('Portaal URL', 'fyndable-geo-scan'); ?></label></th>
                        <td>
                            <input type="url" class="regular-text" id="fyndable_geoscan_portal_url"
                                   name="<?php echo esc_attr(FYNDABLE_GEOSCAN_PORTAL_URL_OPTION); ?>"
                                   value="<?php echo esc_attr($portalUrl); ?>" placeholder="https://portal.fyndable.ai">
                            <p class="description"><?php esc_html_e('Bijv. https://portal.fyndable.ai — zonder slash aan het einde.', 'fyndable-geo-scan'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fyndable_geoscan_api_key"><?php esc_html_e('API key', 'fyndable-geo-scan'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="fyndable_geoscan_api_key"
                                   name="<?php echo esc_attr(FYNDABLE_GEOSCAN_API_KEY_OPTION); ?>"
                                   value="<?php echo esc_attr($apiKey); ?>">
                            <p class="description"><?php esc_html_e('Te vinden in het portaal onder GEO Scan → Website Scan integratie.', 'fyndable-geo-scan'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Melding bij nieuwe aanvraag', 'fyndable-geo-scan'); ?></th>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr(FYNDABLE_GEOSCAN_NOTIFY_ENABLED_OPTION); ?>" value="0">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(FYNDABLE_GEOSCAN_NOTIFY_ENABLED_OPTION); ?>" value="1" <?php checked(Notifier::enabled()); ?>>
                                <?php esc_html_e('Stuur een e-mail zodra een bezoeker het scanformulier verstuurt', 'fyndable-geo-scan'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fyndable_geoscan_notify_email"><?php esc_html_e('Ontvanger(s)', 'fyndable-geo-scan'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" id="fyndable_geoscan_notify_email"
                                   name="<?php echo esc_attr(FYNDABLE_GEOSCAN_NOTIFY_EMAIL_OPTION); ?>"
                                   value="<?php echo esc_attr((string)get_option(FYNDABLE_GEOSCAN_NOTIFY_EMAIL_OPTION, '')); ?>"
                                   placeholder="<?php echo esc_attr((string)get_option('admin_email')); ?>">
                            <p class="description"><?php esc_html_e('Meerdere adressen scheiden met een komma. Leeg = het beheerdersadres van deze site. De mail gaat via de mailinstellingen van deze site; met Beantwoorden mail je direct de aanvrager.', 'fyndable-geo-scan'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Gebruik', 'fyndable-geo-scan'); ?></h2>
            <p><?php esc_html_e('Plaats de shortcode op een pagina of in een template:', 'fyndable-geo-scan'); ?></p>
            <p><code>[fyndable_geo_scan]</code></p>
        </div>
        <?php
    }

    public static function portalUrl(): string
    {
        $url = get_option(FYNDABLE_GEOSCAN_PORTAL_URL_OPTION, 'https://portal.fyndable.ai');
        return rtrim((string)$url, '/');
    }

    public static function apiKey(): string
    {
        return (string)get_option(FYNDABLE_GEOSCAN_API_KEY_OPTION, '');
    }
}

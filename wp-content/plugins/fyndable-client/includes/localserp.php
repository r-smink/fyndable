<?php

namespace SSEOAIClient;

/**
 * Local SERP / Local Pack scanner
 *
 * Provides a km-radius local pack scan around the business address.
 * Used by the Rank Tracker "Local" tab and the REST API.
 */
class LocalSerp
{
    private Settings $settings;
    private DashboardAPI $dashboardAPI;
    private LicenseValidator $licenseValidator;

    public function __construct(Settings $settings, DashboardAPI $dashboardAPI, LicenseValidator $licenseValidator)
    {
        $this->settings = $settings;
        $this->dashboardAPI = $dashboardAPI;
        $this->licenseValidator = $licenseValidator;
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('sseo-ai/v1', '/local-serp/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'restScan'],
            'permission_callback' => fn() => current_user_can('manage_options'),
            'args' => [
                'keyword' => ['type' => 'string', 'required' => true],
                'latitude' => ['type' => 'number', 'required' => true],
                'longitude' => ['type' => 'number', 'required' => true],
                'radius' => ['type' => 'number', 'default' => 10],
                'grid' => ['type' => 'integer', 'default' => 1],
                'country' => ['type' => 'string', 'default' => 'nl'],
                'language' => ['type' => 'string', 'default' => 'nl'],
                'business_name' => ['type' => 'string'],
            ],
        ]);

        register_rest_route('sseo-ai/v1', '/local-serp/center', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetCenter'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * Get the configured local business centre (address + coordinates + defaults).
     */
    public function restGetCenter(): array
    {
        $options = $this->settings->all();
        return [
            'business_name' => $options['local_business_name'] ?? '',
            'business_type' => $options['local_business_type'] ?? 'LocalBusiness',
            'address' => [
                'street' => $options['local_street'] ?? '',
                'city' => $options['local_city'] ?? '',
                'postal' => $options['local_postal'] ?? '',
                'country' => $options['local_country'] ?? 'NL',
            ],
            'coordinates' => [
                'lat' => $options['local_latitude'] ?? '',
                'lng' => $options['local_longitude'] ?? '',
            ],
            'radius' => (int) ($options['local_search_radius'] ?? 10),
            'grid' => (int) ($options['local_search_grid'] ?? 1),
        ];
    }

    /**
     * Run a local pack / local grid scan via the SaaS dashboard.
     */
    public function restScan(\WP_REST_Request $request): array|\WP_Error
    {
        $tier = $this->licenseValidator->getLicenseTier();
        if (!in_array($tier, ['professional', 'business', 'agency', 'trial', 'dev'], true)) {
            return new \WP_Error('tier_not_allowed', __('Local SERP scans require a Professional or higher license.', 'ai-seo-client'));
        }

        $keyword = sanitize_text_field($request->get_param('keyword'));
        $lat = (float) $request->get_param('latitude');
        $lng = (float) $request->get_param('longitude');
        $radius = (float) $request->get_param('radius');
        $grid = (int) $request->get_param('grid');
        $country = sanitize_text_field($request->get_param('country'));
        $language = $this->countryToLanguage(sanitize_text_field($request->get_param('language')) ?: $country);
        $businessName = sanitize_text_field($request->get_param('business_name') ?? '');

        if (empty($keyword) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || $radius <= 0) {
            return new \WP_Error('invalid_params', __('Keyword and valid coordinates/radius are required', 'ai-seo-client'));
        }

        // Enforce grid size by tier
        $maxGrid = $this->maxGridForTier($tier);
        $grid = max(1, min($grid, $maxGrid));

        $businessName = $businessName ?: ($this->settings->get('local_business_name') ?: '');

        $params = [
            'keyword' => $keyword,
            'latitude' => $lat,
            'longitude' => $lng,
            'radius' => $radius,
            'country' => $country,
            'language' => $language,
            'search_type' => 'maps',
            'target_business_name' => $businessName,
        ];

        $endpoint = $grid > 1 ? 'serp/local-grid' : 'serp/local-pack';
        if ($grid > 1) {
            $params['grid_size'] = $grid;
        }

        $result = $this->dashboardAPI->request($endpoint, $params);

        if (is_wp_error($result)) {
            return new \WP_Error('scan_failed', $result->get_error_message());
        }

        return [
            'success' => true,
            'keyword' => $keyword,
            'center' => ['lat' => $lat, 'lng' => $lng, 'radius_km' => $radius],
            'grid' => $grid,
            'results' => $result['results'] ?? [],
            'own_position' => $result['own_position'] ?? 0,
            'own_presence' => $result['own_presence'] ?? 0,
            'points_scanned' => $result['points_scanned'] ?? 1,
            'provider' => $result['provider'] ?? '',
        ];
    }

    private function maxGridForTier(string $tier): int
    {
        return match ($tier) {
            'agency', 'dev' => 7,
            'business', 'trial' => 5,
            'professional' => 3,
            default => 1,
        };
    }

    /**
     * Map country code to a valid Google/DataForSEO language code.
     */
    private function countryToLanguage(string $country): string
    {
        $map = [
            'nl' => 'nl',
            'be' => 'nl',
            'de' => 'de',
            'fr' => 'fr',
            'gb' => 'en',
            'uk' => 'en',
            'us' => 'en',
            'ca' => 'en',
            'au' => 'en',
            'es' => 'es',
            'it' => 'it',
        ];
        return $map[strtolower($country)] ?? 'en';
    }

    /**
     * Render a compact local SERP panel for use inside the Rank Tracker page.
     */
    public function renderPanel(): void
    {
        $center = $this->restGetCenter();
        $hasCoordinates = !empty($center['coordinates']['lat']) && !empty($center['coordinates']['lng']);
        ?>
        <div id="local-serp-inner" class="sseo-ai-dashboard-card">
            <h2><?php esc_html_e('Local SERP Scan', 'ai-seo-client'); ?></h2>
            <p class="description">
                <?php esc_html_e('Scan the Google local pack around your business address for any keyword.', 'ai-seo-client'); ?>
            </p>

            <?php if (!$hasCoordinates): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Set your business coordinates in Settings → Local Business to use local SERP scans.', 'ai-seo-client'); ?></p>
                </div>
            <?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:20px;">
                <div>
                    <label><strong><?php esc_html_e('Keyword', 'ai-seo-client'); ?></strong></label><br>
                    <input type="text" id="local-keyword" style="width:250px;" placeholder="e.g. tandarts Amsterdam">
                </div>
                <div>
                    <label><strong><?php esc_html_e('Radius (km)', 'ai-seo-client'); ?></strong></label><br>
                    <select id="local-radius" style="width:100px;">
                        <?php foreach ([1, 2, 5, 10, 15, 25, 50, 100] as $km): ?>
                            <option value="<?php echo esc_attr($km); ?>" <?php selected($center['radius'], $km); ?>><?php echo esc_html($km); ?> km</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label><strong><?php esc_html_e('Grid', 'ai-seo-client'); ?></strong></label><br>
                    <select id="local-grid" style="width:140px;">
                        <option value="1" <?php selected($center['grid'], 1); ?>><?php esc_html_e('Single center', 'ai-seo-client'); ?></option>
                        <option value="3" <?php selected($center['grid'], 3); ?>>3x3</option>
                        <option value="5" <?php selected($center['grid'], 5); ?>>5x5</option>
                        <option value="7" <?php selected($center['grid'], 7); ?>>7x7</option>
                    </select>
                </div>
                <button type="button" class="button button-primary" id="local-scan" <?php disabled(!$hasCoordinates); ?>>
                    <?php esc_html_e('Scan Local Pack', 'ai-seo-client'); ?>
                </button>
                <span class="spinner" id="local-spinner" style="float:none;"></span>
            </div>

            <div id="local-result-summary" style="margin-bottom:15px;font-weight:600;"></div>
            <table class="wp-list-table widefat fixed striped" id="local-results-table" style="display:none;">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th><?php esc_html_e('Business', 'ai-seo-client'); ?></th>
                        <th><?php esc_html_e('Address', 'ai-seo-client'); ?></th>
                        <th style="width:80px;"><?php esc_html_e('Rating', 'ai-seo-client'); ?></th>
                        <th style="width:80px;"><?php esc_html_e('Distance', 'ai-seo-client'); ?></th>
                    </tr>
                </thead>
                <tbody id="local-results-body"></tbody>
            </table>

            <input type="hidden" id="local-lat" value="<?php echo esc_attr($center['coordinates']['lat']); ?>">
            <input type="hidden" id="local-lng" value="<?php echo esc_attr($center['coordinates']['lng']); ?>">
        </div>
        <?php
    }
}

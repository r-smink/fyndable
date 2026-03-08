<?php

namespace AISEOClient;

class LocalSEO
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('wp_head', [$this, 'outputLocalSchema'], 5);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveLocalMeta'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    public function registerSettings(): void
    {
        $fields = [
            'local_business_name',
            'local_business_type',
            'local_description',
            'local_street',
            'local_city',
            'local_state',
            'local_postal',
            'local_country',
            'local_phone',
            'local_email',
            'local_url',
            'local_latitude',
            'local_longitude',
            'local_image',
            'local_price_range',
            'local_opening_hours',
        ];

        foreach ($fields as $field) {
            register_setting(Settings::OPTION_KEY, $field, ['sanitize_callback' => 'sanitize_text_field']);
        }
    }

    public function addMetaBoxes(): void
    {
        // Add to pages for location-specific info
        add_meta_box(
            'aiseo_local_seo',
            __('Local SEO', 'ai-seo-client'),
            [$this, 'renderMetaBox'],
            'page',
            'normal',
            'default'
        );
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        $isLocationPage = get_post_meta($post->ID, '_aiseo_is_location_page', true);
        $locationName = get_post_meta($post->ID, '_aiseo_location_name', true);
        $locationAddress = get_post_meta($post->ID, '_aiseo_location_address', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="aiseo_is_location_page" value="1" <?php checked($isLocationPage); ?>>
                <?php esc_html_e('This is a location/branch page', 'ai-seo-client'); ?>
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Location Name', 'ai-seo-client'); ?></label>
            <input type="text" name="aiseo_location_name" value="<?php echo esc_attr($locationName); ?>" class="regular-text">
        </p>
        <p>
            <label><?php esc_html_e('Full Address', 'ai-seo-client'); ?></label>
            <textarea name="aiseo_location_address" rows="3" class="large-text"><?php echo esc_textarea($locationAddress); ?></textarea>
        </p>
        <?php wp_nonce_field('aiseo_local_save', 'aiseo_local_nonce'); ?>
        <?php
    }

    public function saveLocalMeta(int $postId, \WP_Post $post): void
    {
        if (!isset($_POST['aiseo_local_nonce']) || !wp_verify_nonce($_POST['aiseo_local_nonce'], 'aiseo_local_save')) {
            return;
        }

        update_post_meta($postId, '_aiseo_is_location_page', !empty($_POST['aiseo_is_location_page']) ? 1 : 0);
        
        if (isset($_POST['aiseo_location_name'])) {
            update_post_meta($postId, '_aiseo_location_name', sanitize_text_field($_POST['aiseo_location_name']));
        }
        if (isset($_POST['aiseo_location_address'])) {
            update_post_meta($postId, '_aiseo_location_address', sanitize_textarea_field($_POST['aiseo_location_address']));
        }
    }

    public function outputLocalSchema(): void
    {
        if (!is_front_page() && !is_page()) {
            return;
        }

        $options = $this->settings->all();
        
        if (empty($options['local_business_name'])) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $options['local_business_type'] ?? 'LocalBusiness',
            'name' => $options['local_business_name'],
            'description' => $options['local_description'] ?? '',
            'url' => $options['local_url'] ?: home_url(),
            'telephone' => $options['local_phone'] ?? '',
            'email' => $options['local_email'] ?? '',
            'priceRange' => $options['local_price_range'] ?? '$$',
        ];

        // Address
        if (!empty($options['local_street'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $options['local_street'],
                'addressLocality' => $options['local_city'] ?? '',
                'addressRegion' => $options['local_state'] ?? '',
                'postalCode' => $options['local_postal'] ?? '',
                'addressCountry' => $options['local_country'] ?? 'NL',
            ];
        }

        // Geo coordinates
        if (!empty($options['local_latitude']) && !empty($options['local_longitude'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $options['local_latitude'],
                'longitude' => $options['local_longitude'],
            ];
        }

        // Image
        if (!empty($options['local_image'])) {
            $schema['image'] = $options['local_image'];
        }

        // Opening hours
        $hours = $this->parseOpeningHours($options['local_opening_hours'] ?? '');
        if ($hours) {
            $schema['openingHoursSpecification'] = $hours;
        }

        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "<script type=\"application/ld+json\">{$json}</script>\n";

        // Also output review schema if reviews exist
        $this->outputReviewSchema($schema['@type']);
    }

    private function parseOpeningHours(string $hours): array
    {
        if (empty($hours)) {
            return [];
        }

        // Format: Mo-Fr 09:00-17:00, Sa 10:00-14:00
        $lines = explode("\n", $hours);
        $specs = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parse standard format
            if (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su|-)+\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/', $line, $matches)) {
                $days = $matches[1];
                $open = $matches[2];
                $close = $matches[3];

                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $this->expandDays($days),
                    'opens' => $open,
                    'closes' => $close,
                ];
            }
        }

        return $specs;
    }

    private function expandDays(string $days): array
    {
        $dayMap = [
            'Mo' => 'Monday',
            'Tu' => 'Tuesday',
            'We' => 'Wednesday',
            'Th' => 'Thursday',
            'Fr' => 'Friday',
            'Sa' => 'Saturday',
            'Su' => 'Sunday',
        ];

        if (strpos($days, '-') !== false) {
            list($start, $end) = explode('-', $days);
            $result = [];
            $started = false;
            foreach ($dayMap as $code => $full) {
                if ($code === $start) $started = true;
                if ($started) $result[] = $full;
                if ($code === $end) break;
            }
            return $result;
        }

        return [$dayMap[$days] ?? $days];
    }

    private function outputReviewSchema(string $businessType): void
    {
        // Fetch reviews from post meta or database
        $reviews = get_option('aiseo_reviews', []);
        if (empty($reviews)) {
            return;
        }

        $total = count($reviews);
        $sum = array_sum(array_column($reviews, 'rating'));
        $avg = round($sum / $total, 1);

        $reviewSchema = [
            '@context' => 'https://schema.org',
            '@type' => $businessType,
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $avg,
                'reviewCount' => $total,
            ],
            'review' => array_slice(array_map(function($review) {
                return [
                    '@type' => 'Review',
                    'author' => [
                        '@type' => 'Person',
                        'name' => $review['author'],
                    ],
                    'datePublished' => $review['date'],
                    'reviewRating' => [
                        '@type' => 'Rating',
                        'ratingValue' => $review['rating'],
                    ],
                    'reviewBody' => $review['content'],
                ];
            }, $reviews), 0, 5),
        ];

        $json = wp_json_encode($reviewSchema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        echo "<script type=\"application/ld+json\">{$json}</script>\n";
    }

    public function renderSettings(): void
    {
        $options = $this->settings->all();
        ?>
        <h3><?php esc_html_e('Local SEO Settings', 'ai-seo-client'); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Business Name', 'ai-seo-client'); ?></th>
                <td><input type="text" name="aiseoassistant[local_business_name]" value="<?php echo esc_attr($options['local_business_name'] ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Business Type', 'ai-seo-client'); ?></th>
                <td>
                    <select name="aiseoassistant[local_business_type]">
                        <option value="LocalBusiness"><?php esc_html_e('Local Business', 'ai-seo-client'); ?></option>
                        <option value="Restaurant"><?php esc_html_e('Restaurant', 'ai-seo-client'); ?></option>
                        <option value="Store"><?php esc_html_e('Store', 'ai-seo-client'); ?></option>
                        <option value="Dentist"><?php esc_html_e('Dentist', 'ai-seo-client'); ?></option>
                        <option value="Doctor"><?php esc_html_e('Doctor', 'ai-seo-client'); ?></option>
                        <option value="Plumber"><?php esc_html_e('Plumber', 'ai-seo-client'); ?></option>
                        <option value="Electrician"><?php esc_html_e('Electrician', 'ai-seo-client'); ?></option>
                        <option value="Lawyer"><?php esc_html_e('Lawyer', 'ai-seo-client'); ?></option>
                        <option value="RealEstateAgent"><?php esc_html_e('Real Estate Agent', 'ai-seo-client'); ?></option>
                        <option value="HairSalon"><?php esc_html_e('Hair Salon', 'ai-seo-client'); ?></option>
                        <option value="AutoRepair"><?php esc_html_e('Auto Repair', 'ai-seo-client'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Description', 'ai-seo-client'); ?></th>
                <td><textarea name="aiseoassistant[local_description]" rows="3" class="large-text"><?php echo esc_textarea($options['local_description'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Address', 'ai-seo-client'); ?></th>
                <td>
                    <p><input type="text" name="aiseoassistant[local_street]" value="<?php echo esc_attr($options['local_street'] ?? ''); ?>" class="regular-text" placeholder="Street Address"></p>
                    <p><input type="text" name="aiseoassistant[local_city]" value="<?php echo esc_attr($options['local_city'] ?? ''); ?>" placeholder="City" style="width:200px;"></p>
                    <p><input type="text" name="aiseoassistant[local_state]" value="<?php echo esc_attr($options['local_state'] ?? ''); ?>" placeholder="State/Province" style="width:150px;"></p>
                    <p><input type="text" name="aiseoassistant[local_postal]" value="<?php echo esc_attr($options['local_postal'] ?? ''); ?>" placeholder="Postal Code" style="width:100px;"></p>
                    <p><input type="text" name="aiseoassistant[local_country]" value="<?php echo esc_attr($options['local_country'] ?? 'NL'); ?>" placeholder="Country" style="width:100px;"></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Coordinates', 'ai-seo-client'); ?></th>
                <td>
                    Lat: <input type="text" name="aiseoassistant[local_latitude]" value="<?php echo esc_attr($options['local_latitude'] ?? ''); ?>" style="width:100px;">
                    Lng: <input type="text" name="aiseoassistant[local_longitude]" value="<?php echo esc_attr($options['local_longitude'] ?? ''); ?>" style="width:100px;">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Contact', 'ai-seo-client'); ?></th>
                <td>
                    <p>Phone: <input type="text" name="aiseoassistant[local_phone]" value="<?php echo esc_attr($options['local_phone'] ?? ''); ?>"></p>
                    <p>Email: <input type="email" name="aiseoassistant[local_email]" value="<?php echo esc_attr($options['local_email'] ?? ''); ?>"></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Opening Hours', 'ai-seo-client'); ?></th>
                <td>
                    <textarea name="aiseoassistant[local_opening_hours]" rows="5" class="large-text" placeholder="Mo-Fr 09:00-17:00&#10;Sa 10:00-14:00"><?php echo esc_textarea($options['local_opening_hours'] ?? ''); ?></textarea>
                    <p class="description"><?php esc_html_e('One per line. Format: Mo-Fr 09:00-17:00', 'ai-seo-client'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Business Image', 'ai-seo-client'); ?></th>
                <td><input type="url" name="aiseoassistant[local_image]" value="<?php echo esc_attr($options['local_image'] ?? ''); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Price Range', 'ai-seo-client'); ?></th>
                <td>
                    <select name="aiseoassistant[local_price_range]">
                        <option value="$">$ (Inexpensive)</option>
                        <option value="$$">$$ (Moderate)</option>
                        <option value="$$$">$$$ (Expensive)</option>
                        <option value="$$$$">$$$$ (Very Expensive)</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('aiseoassistant/v1', '/local-schema', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetLocalSchema'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function restGetLocalSchema(): array
    {
        $options = $this->settings->all();
        
        return [
            'business_name' => $options['local_business_name'] ?? '',
            'business_type' => $options['local_business_type'] ?? 'LocalBusiness',
            'address' => [
                'street' => $options['local_street'] ?? '',
                'city' => $options['local_city'] ?? '',
                'postal' => $options['local_postal'] ?? '',
            ],
            'coordinates' => [
                'lat' => $options['local_latitude'] ?? '',
                'lng' => $options['local_longitude'] ?? '',
            ],
        ];
    }
}

<?php

namespace FyndableGeoScan;

/**
 * Renders the public GEO scan form (shortcode) and proxies scan requests
 * to the Fyndable SaaS portal. Scans run asynchronously on the portal —
 * this plugin only queues them and polls for status.
 */
class Widget
{
    private const RATE_LIMIT_PER_HOUR = 3;

    public function register(): void
    {
        add_shortcode('fyndable_geo_scan', [$this, 'renderShortcode']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
    }

    public function registerAssets(): void
    {
        wp_register_style(
            'fyndable-geo-scan',
            FYNDABLE_GEOSCAN_PLUGIN_URL . 'assets/geo-scan.css',
            [],
            FYNDABLE_GEOSCAN_VERSION
        );

        wp_register_script(
            'fyndable-geo-scan',
            FYNDABLE_GEOSCAN_PLUGIN_URL . 'assets/geo-scan.js',
            [],
            FYNDABLE_GEOSCAN_VERSION,
            true
        );

        wp_localize_script('fyndable-geo-scan', 'fyndableGeoScan', [
            'restUrl' => esc_url_raw(rest_url('fyndable/v1')),
            'strings' => [
                'error'           => __('Er ging iets mis. Probeer het later opnieuw.', 'fyndable-geo-scan'),
                'queued'          => __('Scan wordt voorbereid…', 'fyndable-geo-scan'),
                'timeout'         => __('De scan duurt langer dan verwacht, maar loopt op de achtergrond door. Vernieuw deze pagina over een paar minuten — je resultaat verschijnt dan automatisch.', 'fyndable-geo-scan'),
                'ctaTitle'        => __('Wil je het volledige rapport?', 'fyndable-geo-scan'),
                'ctaText'         => __('Wij nemen contact met je op om de volledige analyse en verbeterpunten door te nemen.', 'fyndable-geo-scan'),
                'resultBadge'     => __('Jouw resultaat', 'fyndable-geo-scan'),
                'resultTitle'     => __('Jouw GEO Scan resultaat', 'fyndable-geo-scan'),
                'printResult'     => __('Printen / opslaan als PDF', 'fyndable-geo-scan'),
                'totalScore'      => __('Totale GEO Score', 'fyndable-geo-scan'),
                'keywordsWord'    => __('keywords', 'fyndable-geo-scan'),
                'tileGoogle'      => __('Google', 'fyndable-geo-scan'),
                'tileAi'          => __('AI Visibility', 'fyndable-geo-scan'),
                'tileEeat'        => __('E-E-A-T', 'fyndable-geo-scan'),
                'tileReadability' => __('Readability', 'fyndable-geo-scan'),
                'verdictStrong'   => __('Sterk', 'fyndable-geo-scan'),
                'verdictNeedsWork'=> __('Kan beter', 'fyndable-geo-scan'),
                'verdictWeak'     => __('Zwak', 'fyndable-geo-scan'),
                'aiOverview'      => __('AI Overview', 'fyndable-geo-scan'),
                'aiOverviewYes'   => __('Aanwezig', 'fyndable-geo-scan'),
                'aiOverviewNo'    => __('Geen', 'fyndable-geo-scan'),
                'citation'        => __('Citatie', 'fyndable-geo-scan'),
                'cited'           => __('Geciteerd', 'fyndable-geo-scan'),
                'notCited'        => __('Niet gevonden', 'fyndable-geo-scan'),
                'pillTop3'        => __('Top 3 positie', 'fyndable-geo-scan'),
                'pillPage1'       => __('Pagina 1', 'fyndable-geo-scan'),
                'pillNoTop10'     => __('Niet in top 10', 'fyndable-geo-scan'),
                'pillOverview'    => __('AI Overview aanwezig', 'fyndable-geo-scan'),
                'pillCited'       => __('Geciteerd in AI Overview', 'fyndable-geo-scan'),
                'pillNotCited'    => __('Niet geciteerd', 'fyndable-geo-scan'),
                'pillNoOverview'  => __('Geen AI Overview', 'fyndable-geo-scan'),
                'pillCompetitors' => __('concurrenten geciteerd', 'fyndable-geo-scan'),
                'recTitle'        => __('Aanbevolen acties', 'fyndable-geo-scan'),
                'recSub'          => __('Prioriteer deze verbeterpunten om je GEO Score te verhogen.', 'fyndable-geo-scan'),
                'prioHigh'        => __('Hoog', 'fyndable-geo-scan'),
                'prioMedium'      => __('Medium', 'fyndable-geo-scan'),
                'prioLow'         => __('Laag', 'fyndable-geo-scan'),
            ],
        ]);
    }

    public function registerRestRoutes(): void
    {
        register_rest_route('fyndable/v1', '/geo-scan', [
            'methods' => 'POST',
            'callback' => [$this, 'restRunScan'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('fyndable/v1', '/geo-scan/(?P<id>\d+)/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restScanStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Shortcode: [fyndable_geo_scan]
     */
    public function renderShortcode(): string
    {
        wp_enqueue_style('fyndable-geo-scan');
        wp_enqueue_script('fyndable-geo-scan');

        ob_start();
        ?>
        <div class="fgs" id="fyndable-geo-scan">
            <form class="fgs-card" id="fyndable-geo-scan-form" novalidate>
                <div class="fgs-card-head">
                    <div class="fgs-card-icon" aria-hidden="true">⎔</div>
                    <div>
                        <h2 class="fgs-card-title"><?php esc_html_e('Start je GEO Scan', 'fyndable-geo-scan'); ?></h2>
                        <p class="fgs-card-sub"><?php esc_html_e('Vul onderstaande gegevens in en ontvang direct je analyse.', 'fyndable-geo-scan'); ?></p>
                    </div>
                </div>

                <div class="fgs-section-label"><?php esc_html_e('Persoonlijke gegevens', 'fyndable-geo-scan'); ?></div>
                <div class="fgs-grid-3">
                    <div class="fgs-field">
                        <label for="fgs-name"><?php esc_html_e('Naam', 'fyndable-geo-scan'); ?></label>
                        <input type="text" id="fgs-name" name="name" placeholder="<?php esc_attr_e('Jan de Vries', 'fyndable-geo-scan'); ?>">
                    </div>
                    <div class="fgs-field">
                        <label for="fgs-email"><?php esc_html_e('E-mailadres', 'fyndable-geo-scan'); ?></label>
                        <input type="email" id="fgs-email" name="email" placeholder="jan@bedrijf.nl" required>
                    </div>
                    <div class="fgs-field">
                        <label for="fgs-company"><?php esc_html_e('Bedrijfsnaam', 'fyndable-geo-scan'); ?></label>
                        <input type="text" id="fgs-company" name="company" placeholder="<?php esc_attr_e('Bedrijf B.V.', 'fyndable-geo-scan'); ?>">
                    </div>
                </div>

                <div class="fgs-section-label"><?php esc_html_e('Website', 'fyndable-geo-scan'); ?></div>
                <div class="fgs-field">
                    <label for="fgs-url"><?php esc_html_e('Website URL', 'fyndable-geo-scan'); ?></label>
                    <div class="fgs-url-group">
                        <span class="fgs-url-prefix" aria-hidden="true">https://</span>
                        <input type="text" id="fgs-url" name="url" inputmode="url" placeholder="jouwwebsite.nl" required>
                    </div>
                </div>

                <div class="fgs-section-label"><?php esc_html_e('Zoektermen', 'fyndable-geo-scan'); ?></div>
                <div class="fgs-field">
                    <label><?php esc_html_e('Keywords of zoekzinnen (max. 2)', 'fyndable-geo-scan'); ?></label>
                    <div class="fgs-grid-2">
                        <div class="fgs-kw-wrap">
                            <input type="text" class="fgs-keyword" name="keyword1" placeholder="<?php esc_attr_e('seo specialist', 'fyndable-geo-scan'); ?>" required>
                            <span class="fgs-kw-num" aria-hidden="true">1</span>
                        </div>
                        <div class="fgs-kw-wrap">
                            <input type="text" class="fgs-keyword" name="keyword2" placeholder="<?php esc_attr_e('wordpress seo plugin', 'fyndable-geo-scan'); ?>">
                            <span class="fgs-kw-num" aria-hidden="true">2</span>
                        </div>
                    </div>
                </div>

                <div class="fgs-actions">
                    <label class="fgs-consent" for="fgs-consent">
                        <input type="checkbox" id="fgs-consent" name="consent" required>
                        <span class="fgs-consent-box" aria-hidden="true"></span>
                        <span class="fgs-consent-text"><?php esc_html_e('Ik ga akkoord met de algemene voorwaarden en geef Fyndable toestemming om contact met mij op te nemen naar aanleiding van deze scan.', 'fyndable-geo-scan'); ?></span>
                    </label>
                    <button type="submit" class="fgs-submit" id="fgs-submit">
                        <?php esc_html_e('Scan starten →', 'fyndable-geo-scan'); ?>
                    </button>
                </div>

                <!-- Honeypot: invisible to humans, bots fill it in -->
                <div class="fgs-hp" aria-hidden="true">
                    <label><?php esc_html_e('Laat dit veld leeg', 'fyndable-geo-scan'); ?>
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </label>
                </div>

                <div class="fgs-progress" id="fgs-progress" hidden>
                    <div class="fgs-progress-track">
                        <div class="fgs-progress-fill"></div>
                    </div>
                    <div class="fgs-progress-meta">
                        <span class="fgs-progress-label"></span>
                        <span class="fgs-progress-pct">0%</span>
                    </div>
                </div>

                <div class="fgs-error" id="fgs-error" hidden></div>
            </form>

            <div class="fgs-result" id="fgs-result" hidden></div>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    /**
     * POST /fyndable/v1/geo-scan — validate + forward to portal.
     */
    public function restRunScan(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_json_params() ?: $request->get_params();

        // Honeypot — pretend success so bots move on.
        if (!empty($body['website'])) {
            return new \WP_REST_Response(['success' => true, 'scan_id' => 0], 200);
        }

        $url = sanitize_text_field((string)($body['url'] ?? ''));
        // The form shows a fixed https:// prefix and submits the domain only —
        // prepend the scheme here too so direct API callers get the same result.
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $url = esc_url_raw($url);
        $email = sanitize_email($body['email'] ?? '');
        $name = sanitize_text_field($body['name'] ?? '');
        $company = sanitize_text_field($body['company'] ?? '');
        $consent = !empty($body['consent']);

        $keywords = [];
        foreach ((array)($body['keywords'] ?? []) as $kw) {
            $kw = sanitize_text_field((string)$kw);
            if ($kw !== '') {
                $keywords[] = $kw;
            }
        }

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('invalid_url', __('Vul een geldige URL in.', 'fyndable-geo-scan'), 400);
        }
        if (empty($keywords) || count($keywords) > 2) {
            return $this->error('invalid_keywords', __('Vul 1 tot 2 keywords in.', 'fyndable-geo-scan'), 400);
        }
        if (empty($email) || !is_email($email)) {
            return $this->error('invalid_email', __('Vul een geldig e-mailadres in.', 'fyndable-geo-scan'), 400);
        }
        if (!$consent) {
            return $this->error('consent_required', __('Vink de toestemming aan om door te gaan.', 'fyndable-geo-scan'), 400);
        }

        if (($err = $this->checkRateLimit()) !== null) {
            return $err;
        }

        if (empty(Settings::apiKey())) {
            return $this->error('not_configured', __('De scan is tijdelijk niet beschikbaar.', 'fyndable-geo-scan'), 503);
        }

        $response = wp_remote_post(
            Settings::portalUrl() . '/wp-json/ai-seo-saas/v1/public/geo-scan',
            [
                'timeout' => 20,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Fyndable-Scan-Key' => Settings::apiKey(),
                ],
                'body' => wp_json_encode([
                    'url' => $url,
                    'keywords' => $keywords,
                    'email' => $email,
                    'name' => $name,
                    'company' => $company,
                    'consent' => true,
                ]),
            ]
        );

        if (is_wp_error($response)) {
            return $this->error('portal_unreachable', __('De scandienst is tijdelijk niet bereikbaar. Probeer het later opnieuw.', 'fyndable-geo-scan'), 502);
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 409 || (($data['error'] ?? '') === 'already_scanned')) {
            return $this->error('already_scanned', __('Voor dit e-mailadres is al een scan aangevraagd.', 'fyndable-geo-scan'), 409);
        }

        // 201 = new scan queued, 200 = existing scan resumed (idempotent submit).
        if (($code !== 201 && $code !== 200) || empty($data['scan_id'])) {
            $msg = $data['message'] ?? __('De scan kon niet worden gestart.', 'fyndable-geo-scan');
            return $this->error('scan_failed', $msg, 502);
        }

        return new \WP_REST_Response([
            'success' => true,
            'scan_id' => (int)$data['scan_id'],
        ], 200);
    }

    /**
     * GET /fyndable/v1/geo-scan/{id}/status — proxy status poll to portal.
     */
    public function restScanStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $scanId = (int)$request->get_param('id');

        if ($scanId <= 0) {
            return $this->noCache($this->error('invalid_scan', __('Ongeldige scan.', 'fyndable-geo-scan'), 400));
        }

        $statusUrl = add_query_arg(
            '_',
            sprintf('%.6F', microtime(true)),
            Settings::portalUrl() . '/wp-json/ai-seo-saas/v1/public/geo-scan/' . $scanId . '/status'
        );
        $response = wp_remote_get(
            $statusUrl,
            [
                'timeout' => 15,
                'headers' => [
                    'X-Fyndable-Scan-Key' => Settings::apiKey(),
                    'Cache-Control' => 'no-cache, no-store, max-age=0',
                    'Pragma' => 'no-cache',
                ],
            ]
        );

        if (is_wp_error($response)) {
            // Transient portal hiccup — report as still running so the frontend keeps polling.
            return $this->noCache(new \WP_REST_Response(['success' => true, 'status' => 'running', 'progress' => -1], 200));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['success'])) {
            return $this->noCache($this->error('status_failed', __('Status kon niet worden opgehaald.', 'fyndable-geo-scan'), 502));
        }

        return $this->noCache(new \WP_REST_Response($data, 200));
    }

    private function noCache(\WP_REST_Response $response): \WP_REST_Response
    {
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
        $response->header('X-LiteSpeed-Cache-Control', 'no-cache');
        return $response;
    }

    private function checkRateLimit(): ?\WP_REST_Response
    {
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $key = 'fgs_rate_' . md5($ip);
        $count = (int)get_transient($key);

        if ($count >= self::RATE_LIMIT_PER_HOUR) {
            return $this->error('rate_limited', __('Te veel aanvragen. Probeer het later opnieuw.', 'fyndable-geo-scan'), 429);
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return null;
    }

    private function error(string $code, string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error' => $code,
            'message' => $message,
        ], $status);
    }
}

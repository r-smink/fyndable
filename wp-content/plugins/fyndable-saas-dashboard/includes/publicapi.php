<?php

namespace SSEOAISaaS;

/**
 * Public API
 *
 * Unauthenticated-by-WP endpoints for public-facing integrations (e.g. the
 * free GEO scan on fyndable.ai). Access is controlled by a shared key
 * configured on the GEO Scan settings card, sent as X-Fyndable-Scan-Key.
 *
 * All endpoints are fast: scans are queued and processed by GeoScanQueue —
 * callers poll the status endpoint for progress and results.
 */
class PublicApi
{
    private string $namespace = 'ai-seo-saas/v1';

    private const RATE_LIMIT_PER_HOUR = 10;

    private GeoScanRepository $geoScanRepository;
    private GeoScanQueue $geoScanQueue;
    private SaaSSettings $settings;

    public function __construct(
        GeoScanRepository $geoScanRepository,
        GeoScanQueue $geoScanQueue,
        SaaSSettings $settings
    ) {
        $this->geoScanRepository = $geoScanRepository;
        $this->geoScanQueue = $geoScanQueue;
        $this->settings = $settings;
    }

    public function register(): void
    {
        register_rest_route($this->namespace, '/public/geo-scan', [
            'methods' => 'POST',
            'callback' => [$this, 'runGeoScan'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/public/geo-scan/(?P<id>\d+)/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getGeoScanStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * POST /public/geo-scan — queue a website (lead) scan.
     *
     * Body: { url, keywords[1-3], email, consent: true }
     * Returns: 201 { success, scan_id }
     */
    public function runGeoScan(\WP_REST_Request $request): \WP_REST_Response
    {
        if (($err = $this->checkKey($request)) !== null) {
            return $err;
        }

        $body = $request->get_json_params();
        $url = esc_url_raw($body['url'] ?? '');
        $email = sanitize_email($body['email'] ?? '');
        $name = sanitize_text_field($body['name'] ?? '');
        $company = sanitize_text_field($body['company'] ?? '');
        $consent = !empty($body['consent']);

        $rawKeywords = $body['keywords'] ?? [];
        if (is_string($rawKeywords)) {
            $rawKeywords = array_filter(array_map('trim', explode("\n", $rawKeywords)));
        }
        $keywords = array_values(array_filter(array_map('sanitize_text_field', (array)$rawKeywords)));

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('invalid_url', __('Invalid URL provided', 'sseo-ai-saas'), 400);
        }
        if (count($keywords) < 1 || count($keywords) > 2) {
            return $this->error('invalid_keywords', __('Provide between 1 and 2 keywords', 'sseo-ai-saas'), 400);
        }
        if (empty($email) || !is_email($email)) {
            return $this->error('invalid_email', __('A valid email address is required', 'sseo-ai-saas'), 400);
        }
        if (!$consent) {
            return $this->error('consent_required', __('Consent to be contacted is required', 'sseo-ai-saas'), 400);
        }

        $ip = $this->clientIp();
        if (($err = $this->checkRateLimit($ip)) !== null) {
            return $err;
        }

        // Idempotent submit: an earlier scan for this email may still be
        // running or already completed — hand its id back so the visitor can
        // pick up progress/result instead of paying for a duplicate scan.
        $existingId = $this->geoScanRepository->findActiveWebsiteScanId($email);
        if ($existingId !== null) {
            return new \WP_REST_Response([
                'success'   => true,
                'scan_id'   => $existingId,
                'duplicate' => true,
            ], 200);
        }

        $scanId = $this->geoScanRepository->insertQueued($url, $keywords, 'auto', [
            'source'     => 'website',
            'email'      => $email,
            'consent'    => true,
            'ip'         => $ip,
            'user_agent' => substr((string)($request->get_header('user_agent') ?? ''), 0, 255),
            'name'       => $name,
            'company'    => $company,
        ]);

        $this->geoScanQueue->enqueue($scanId);

        return new \WP_REST_Response([
            'success' => true,
            'scan_id' => $scanId,
        ], 201);
    }

    /**
     * GET /public/geo-scan/{id}/status — poll progress; returns teaser on completion.
     */
    public function getGeoScanStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        if (($err = $this->checkKey($request)) !== null) {
            return $err;
        }

        $scanId = (int)$request->get_param('id');
        $scan = $this->geoScanRepository->getStatus($scanId);

        if (!$scan || ($scan['source'] ?? 'admin') !== 'website') {
            return $this->error('not_found', __('Scan not found', 'sseo-ai-saas'), 404);
        }

        $response = [
            'success'        => true,
            'status'         => $scan['status'],
            'progress'       => (int)$scan['progress'],
            'progress_label' => $scan['progress_label'],
        ];

        if ($scan['status'] === 'failed') {
            $response['error'] = $scan['error'] ?: __('Scan failed', 'sseo-ai-saas');
        }

        if ($scan['status'] === 'completed') {
            $response['teaser'] = $this->buildTeaser($scanId);
        }

        return new \WP_REST_Response($response, 200);
    }

    /**
     * Trimmed result for public consumption — the full report stays internal.
     */
    private function buildTeaser(int $scanId): array
    {
        $scan = $this->geoScanRepository->getById($scanId);
        $result = $scan['result'] ?? [];

        $keywordsAnalysis = [];
        foreach ($result['keywords_analysis'] ?? [] as $kw) {
            $keywordsAnalysis[] = [
                'keyword'           => $kw['keyword'] ?? '',
                'has_ai_overview'   => (bool)($kw['has_ai_overview'] ?? false),
                'target_cited'      => (bool)($kw['target_cited'] ?? false),
                'organic_position'  => isset($kw['target_organic_position']) ? (int)$kw['target_organic_position'] : null,
                'ai_sources_count'  => (int)($kw['ai_sources_count'] ?? 0),
                'competitors_count' => count($kw['competitor_citations'] ?? []),
            ];
        }

        $breakdown = $result['breakdown'] ?? [];
        $subscores = [
            'google'        => (int)($breakdown['competitive_gap'] ?? 0),
            'ai_visibility' => (int)($breakdown['citation_worthiness'] ?? 0),
            'eeat'          => (int)($breakdown['eeat'] ?? 0),
            'readability'   => (int)($breakdown['readability'] ?? 0),
        ];

        return [
            'score'              => (int)($result['score'] ?? 0),
            'label'              => $this->scoreLabel((int)($result['score'] ?? 0), $subscores),
            'subscores'          => $subscores,
            'strengths'          => array_slice($result['strengths'] ?? [], 0, 3),
            'weaknesses'         => array_slice($result['weaknesses'] ?? [], 0, 3),
            'recommendations'    => array_slice($result['priority_ranked_recommendations'] ?? $result['recommendations'] ?? [], 0, 5),
            'keywords_analysis'  => $keywordsAnalysis,
        ];
    }

    /**
     * Human label + the two weakest sub-score areas for the teaser subtitle.
     */
    private function scoreLabel(int $score, array $subscores): string
    {
        if ($score >= 80) {
            $label = __('Uitstekend', 'sseo-ai-saas');
        } elseif ($score >= 60) {
            $label = __('Goed', 'sseo-ai-saas');
        } elseif ($score >= 40) {
            $label = __('Verbetering nodig', 'sseo-ai-saas');
        } else {
            $label = __('Werk aan de winkel', 'sseo-ai-saas');
        }

        $names = [
            'google'        => __('Google-ranking', 'sseo-ai-saas'),
            'ai_visibility' => __('AI-zichtbaarheid', 'sseo-ai-saas'),
            'eeat'          => __('E-E-A-T', 'sseo-ai-saas'),
            'readability'   => __('leesbaarheid', 'sseo-ai-saas'),
        ];
        asort($subscores);
        $weakest = array_slice(array_keys($subscores), 0, 2);
        $weakestNames = array_map(fn($k) => $names[$k] ?? $k, $weakest);

        return $label . ' — ' . sprintf(
            __('meeste winst op %s', 'sseo-ai-saas'),
            implode(' ' . __('en', 'sseo-ai-saas') . ' ', $weakestNames)
        );
    }

    private function checkKey(\WP_REST_Request $request): ?\WP_REST_Response
    {
        $configured = $this->settings->getWebsiteScanKey();
        $provided = (string)($request->get_header('x-fyndable-scan-key') ?? '');

        if (empty($configured)) {
            return $this->error('not_configured', __('Public GEO scan is not configured', 'sseo-ai-saas'), 503);
        }
        if (empty($provided) || !hash_equals($configured, $provided)) {
            return $this->error('unauthorized', __('Invalid API key', 'sseo-ai-saas'), 401);
        }

        return null;
    }

    private function checkRateLimit(string $ip): ?\WP_REST_Response
    {
        $key = 'sseo_geo_pub_' . md5($ip);
        $count = (int)get_transient($key);

        if ($count >= self::RATE_LIMIT_PER_HOUR) {
            return $this->error('rate_limited', __('Too many requests. Please try again later.', 'sseo-ai-saas'), 429);
        }

        set_transient($key, $count + 1, HOUR_IN_SECONDS);
        return null;
    }

    private function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return sanitize_text_field((string)$ip);
    }

    private function error(string $code, string $message, int $status): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'error'   => $code,
            'message' => $message,
        ], $status);
    }
}

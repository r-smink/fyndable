<?php

namespace SSEOAIClient;

/**
 * Google Analytics 4 API Client
 * 
 * Uses the Google Analytics Data API v1 beta to fetch GA4 data.
 * Relies on GscOAuth (unified Google OAuth) for token management.
 * 
 * @see https://developers.google.com/analytics/devguides/reporting/data/v1
 */
class GA4Client
{
    private Settings $settings;
    private GscOAuth $oauth;
    private DashboardAPI $dashboardAPI;

    private const API_BASE = 'https://analyticsdata.googleapis.com/v1beta/properties/';

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->oauth = new GscOAuth($settings);
        $this->dashboardAPI = new DashboardAPI($settings);
    }

    /**
     * Check if GA4 is connected (has valid tokens and property ID)
     */
    public function isConnected(): bool
    {
        $tokens = get_option('aiseoclient_gsc_tokens', []);
        $propertyId = get_option('sseo_ai_ga4_property_id', '');
        return !empty($tokens['access_token']) && !empty($propertyId);
    }

    /**
     * Get the GA4 property ID
     */
    private function getPropertyId(): string
    {
        return get_option('sseo_ai_ga4_property_id', '');
    }

    /**
     * Run a GA4 Data API report.
     * 
     * @param array $reportRequest Report request body (dateRanges, dimensions, metrics, etc.)
     * @return array|\WP_Error
     * @see https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runReport
     */
    public function runReport(array $reportRequest): array|\WP_Error
    {
        if (!$this->isConnected()) {
            return new \WP_Error(
                'ga4_not_connected',
                __('Google Analytics 4 is not connected. Please connect via AI SEO → Integrations and set your GA4 Property ID.', 'ai-seo-client')
            );
        }

        $accessToken = $this->oauth->getAccessToken();
        if (empty($accessToken)) {
            return new \WP_Error('ga4_auth', __('Unable to get access token. Please reconnect your Google account.', 'ai-seo-client'));
        }

        $propertyId = $this->getPropertyId();
        $apiUrl = self::API_BASE . $propertyId . ':runReport';

        $resp = wp_remote_post($apiUrl, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($reportRequest),
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code === 401) {
            $refreshed = $this->oauth->refresh();
            if (!is_wp_error($refreshed)) {
                return $this->runReport($reportRequest);
            }
            return new \WP_Error('ga4_auth_expired', __('Google authentication expired. Please reconnect.', 'ai-seo-client'));
        }

        if ($code !== 200) {
            $errorMsg = $body['error']['message'] ?? __('GA4 API error', 'ai-seo-client');
            return new \WP_Error('ga4_api', $errorMsg, ['code' => $code]);
        }

        $this->dashboardAPI->reportGoogleUsage('ga4');

        return $body;
    }

    /**
     * Get overview stats (sessions, total users, page views, conversions) for a date range.
     * 
     * @param int $days Number of days to look back
     * @return array|\WP_Error
     */
    public function getOverview(int $days = 30): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $result = $this->runReport([
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'conversions'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
            ],
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $totals = [];
        if (!empty($result['rows'][0]['metricValues'])) {
            foreach ($result['rows'][0]['metricValues'] as $i => $val) {
                $metricName = $result['metricHeaders'][$i]['name'] ?? '';
                $totals[$metricName] = isset($val['value']) ? (float)$val['value'] : 0;
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'sessions' => $totals['sessions'] ?? 0,
            'users' => $totals['totalUsers'] ?? 0,
            'page_views' => $totals['screenPageViews'] ?? 0,
            'conversions' => $totals['conversions'] ?? 0,
            'avg_session_duration' => round($totals['averageSessionDuration'] ?? 0, 1),
            'bounce_rate' => round(($totals['bounceRate'] ?? 0) * 100, 1),
        ];
    }

    /**
     * Get daily traffic breakdown for a date range.
     * 
     * @param int $days
     * @return array|\WP_Error
     */
    public function getDailyTraffic(int $days = 30): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $result = $this->runReport([
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'date']],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
            ],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $daily = [];
        if (!empty($result['rows'])) {
            foreach ($result['rows'] as $row) {
                $dateStr = $row['dimensionValues'][0]['value'] ?? '';
                $date = substr($dateStr, 0, 4) . '-' . substr($dateStr, 4, 2) . '-' . substr($dateStr, 6, 2);
                $daily[] = [
                    'date' => $date,
                    'sessions' => (int)($row['metricValues'][0]['value'] ?? 0),
                    'users' => (int)($row['metricValues'][1]['value'] ?? 0),
                    'page_views' => (int)($row['metricValues'][2]['value'] ?? 0),
                ];
            }
        }

        return ['daily' => $daily];
    }

    /**
     * Get top pages by traffic.
     * 
     * @param int $days
     * @param int $limit
     * @return array|\WP_Error
     */
    public function getTopPages(int $days = 30, int $limit = 10): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $result = $this->runReport([
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'sessions'],
                ['name' => 'averageSessionDuration'],
            ],
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit' => $limit,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $pages = [];
        if (!empty($result['rows'])) {
            foreach ($result['rows'] as $row) {
                $pages[] = [
                    'path' => $row['dimensionValues'][0]['value'] ?? '',
                    'title' => $row['dimensionValues'][1]['value'] ?? '',
                    'page_views' => (int)($row['metricValues'][0]['value'] ?? 0),
                    'sessions' => (int)($row['metricValues'][1]['value'] ?? 0),
                    'avg_duration' => round((float)($row['metricValues'][2]['value'] ?? 0), 1),
                ];
            }
        }

        return ['pages' => $pages];
    }

    /**
     * Get top traffic sources.
     * 
     * @param int $days
     * @param int $limit
     * @return array|\WP_Error
     */
    public function getTrafficSources(int $days = 30, int $limit = 10): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $result = $this->runReport([
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
            ],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            'limit' => $limit,
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $sources = [];
        if (!empty($result['rows'])) {
            foreach ($result['rows'] as $row) {
                $sources[] = [
                    'channel' => $row['dimensionValues'][0]['value'] ?? '',
                    'sessions' => (int)($row['metricValues'][0]['value'] ?? 0),
                    'users' => (int)($row['metricValues'][1]['value'] ?? 0),
                ];
            }
        }

        return ['sources' => $sources];
    }

    /**
     * Get data for a specific page URL.
     * 
     * @param string $path The page path (e.g. /about-us)
     * @param int $days
     * @return array|\WP_Error
     */
    public function getPageData(string $path, int $days = 30): array|\WP_Error
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $result = $this->runReport([
            'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'sessions'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'conversions'],
            ],
            'dimensionFilter' => [
                'filter' => [
                    'fieldName' => 'pagePath',
                    'stringFilter' => ['value' => $path],
                ],
            ],
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        if (empty($result['rows'])) {
            return [
                'path' => $path,
                'page_views' => 0,
                'sessions' => 0,
                'avg_duration' => 0,
                'conversions' => 0,
            ];
        }

        $row = $result['rows'][0];
        return [
            'path' => $path,
            'page_views' => (int)($row['metricValues'][0]['value'] ?? 0),
            'sessions' => (int)($row['metricValues'][1]['value'] ?? 0),
            'avg_duration' => round((float)($row['metricValues'][2]['value'] ?? 0), 1),
            'conversions' => (int)($row['metricValues'][3]['value'] ?? 0),
        ];
    }

    /**
     * List available GA4 properties (account summaries).
     * 
     * @return array|\WP_Error
     */
    public function listProperties(): array|\WP_Error
    {
        $accessToken = $this->oauth->getAccessToken();
        if (empty($accessToken)) {
            return new \WP_Error('ga4_auth', __('Unable to get access token.', 'ai-seo-client'));
        }

        $resp = wp_remote_get('https://analyticsadmin.googleapis.com/v1beta/accountSummaries', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        if (is_wp_error($resp)) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($code !== 200) {
            return new \WP_Error('ga4_list', $body['error']['message'] ?? __('Failed to list properties', 'ai-seo-client'));
        }

        $properties = [];
        if (!empty($body['accountSummaries'])) {
            foreach ($body['accountSummaries'] as $account) {
                if (!empty($account['propertySummaries'])) {
                    foreach ($account['propertySummaries'] as $prop) {
                        $properties[] = [
                            'property_id' => str_replace('properties/', '', $prop['property'] ?? ''),
                            'display_name' => $prop['displayName'] ?? '',
                            'account' => $account['displayName'] ?? '',
                        ];
                    }
                }
            }
        }

        return ['properties' => $properties];
    }
}
